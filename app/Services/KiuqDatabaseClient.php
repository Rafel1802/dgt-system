<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * KiuqDatabaseClient
 *
 * Fetches live "All Websites Summary" data directly from the kiuq database.
 * Bypasses the need for an external API and allows robust local and remote DB queries.
 *
 * Returns a normalized Collection keyed by domain (lowercase, no www).
 */
class KiuqDatabaseClient
{
    private string $connection;

    public function __construct()
    {
        // Default to kiuq_mysql, switchable via .env
        $this->connection = config('kiuq.db_connection', env('KIUQ_DB_CONNECTION', 'kiuq_mysql'));
    }

    /**
     * Check whether the database connection is configured and reachable.
     */
    public function isConfigured(): bool
    {
        try {
            DB::connection($this->connection)->getPdo();
            return true;
        } catch (\Exception $e) {
            Log::warning('KiuqDatabaseClient: Cannot connect to KIUQ database.', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Fetch website summary data directly from the database.
     *
     * @param array{
     *   date_scope: string,
     *   date_from: string|null,
     *   date_to: string|null,
     * } $filters
     *
     * @return Collection<string, array> Keyed by normalized domain (e.g. "miniexcavator.org")
     */
    public function fetchSummary(array $filters = []): Collection
    {
        if (!$this->isConfigured()) {
            return collect();
        }

        try {
            $db = DB::connection($this->connection);

            // Fetch all websites
            $websites = $db->table('websites')
                ->select('id', 'name', 'domain')
                ->get();

            if ($websites->isEmpty()) {
                return collect();
            }

            $websiteIds = $websites->pluck('id')->toArray();

            // Resolve dates for filtering section_tasks
            [$dateFrom, $dateTo] = $this->resolveDateRange($filters);

            // Fetch tasks via projects -> project_pages -> section_tasks
            $tasksQuery = $db->table('section_tasks')
                ->join('project_pages', 'section_tasks.page_id', '=', 'project_pages.id')
                ->join('projects', 'project_pages.project_id', '=', 'projects.id')
                ->leftJoin('profiles', 'section_tasks.assigned_member_id', '=', 'profiles.id')
                ->select(
                    'projects.website_id',
                    'section_tasks.id',
                    'section_tasks.name as title',
                    'section_tasks.status',
                    'section_tasks.created_at',
                    'profiles.full_name as assignee_name'
                )
                ->whereIn('projects.website_id', $websiteIds);

            if ($dateFrom && $dateTo) {
                $tasksQuery->whereBetween('section_tasks.created_at', [$dateFrom, $dateTo]);
            }

            if (!empty($filters['member_email'])) {
                $tasksQuery->where('profiles.email', $filters['member_email']);
            }

            $tasks = $tasksQuery->get()->groupBy('website_id');

            // Fetch Quality Issues
            $qcIssues = $db->table('quality_issues')
                ->select('website_id', 'status')
                ->whereIn('website_id', $websiteIds)
                ->get()
                ->groupBy('website_id');

            // Fetch Followups (Pillars)
            // ebay_click_followup, blog_followup_schedules, plugin_update_slots, speed_optimization_followup
            $ebayFollowups = $db->table('ebay_click_followup')
                ->select('website_id', 'status')
                ->whereIn('website_id', $websiteIds)
                ->get()
                ->groupBy('website_id');

            $blogFollowups = $db->table('blog_followup_schedules')
                ->select('website_id', 'status')
                ->whereIn('website_id', $websiteIds)
                ->get()
                ->groupBy('website_id');

            $pluginUpdates = $db->table('plugin_update_slots')
                ->select('website_id', 'status')
                ->whereIn('website_id', $websiteIds)
                ->get()
                ->groupBy('website_id');

            $speedOptimizations = $db->table('speed_optimization_slots')
                ->select('website_id', 'status')
                ->whereIn('website_id', $websiteIds)
                ->get()
                ->groupBy('website_id');

            return $this->normalizeData(
                $websites,
                $tasks,
                $qcIssues,
                $ebayFollowups,
                $blogFollowups,
                $pluginUpdates,
                $speedOptimizations
            );

        } catch (\Exception $e) {
            Log::error('KiuqDatabaseClient: Database error', ['message' => $e->getMessage()]);
            return collect();
        }
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function normalizeData(
        Collection $websites,
        Collection $allTasks,
        Collection $allQcIssues,
        Collection $allEbay,
        Collection $allBlogs,
        Collection $allPlugins,
        Collection $allSpeedOpt
    ): Collection {
        $result = collect();

        foreach ($websites as $site) {
            $domain = $this->normalizeDomain($site->domain ?: ($site->name . '.com'));
            if (!$domain) {
                continue;
            }

            $websiteId = $site->id;

            $tasks = $allTasks->get($websiteId, collect());
            $qcData = $allQcIssues->get($websiteId, collect());

            $ebayLogs = $allEbay->get($websiteId, collect());
            $blogAudits = $allBlogs->get($websiteId, collect());
            $pluginUp = $allPlugins->get($websiteId, collect());
            $optimizations = $allSpeedOpt->get($websiteId, collect());

            // ── Pillar indicators ──────────────────────────────────────────
            $pillars = [
                'ebay_click'   => $ebayLogs->isNotEmpty(),
                'blogs'        => $blogAudits->isNotEmpty(),
                'plugins'      => $pluginUp->isNotEmpty(),
                'optimization' => $optimizations->isNotEmpty(),
            ];

            // ── QC Badges ──────────────────────────────────────────────────
            $qcBadges = [
                'new_issue' => $qcData->whereIn('status', ['new_issue', 'pending'])->count(),
                'fixed'     => $qcData->where('status', 'fixed')->count(),
                'approved'  => $qcData->where('status', 'approved')->count(),
            ];

            // ── Task Status Matrix ─────────────────────────────────────────
            $statusMatrix = $this->buildStatusMatrix($tasks);

            // ── Completion Rate ────────────────────────────────────────────
            $totalTasks     = $tasks->count();
            $doneTasks      = $tasks->filter(fn($t) => in_array(strtolower($t->status ?? ''), ['done', 'completed', 'approved']))->count();
            $completionRate = $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0;

            // ── Raw counts for global metrics ──────────────────────────────
            $openIssues = $tasks->filter(fn($t) => strtolower($t->status ?? '') === 'not_started' || strtolower($t->status ?? '') === 'todo')->count();

            // Preserve tasks as array for exportRows functionality
            $rawTasksArray = $tasks->map(function ($t) {
                return [
                    'id' => $t->id,
                    'title' => $t->title,
                    'status' => $t->status,
                    'created_at' => $t->created_at,
                    'assigned_member' => ['name' => $t->assignee_name ?? '-'] 
                ];
            })->toArray();

            $result->put($domain, [
                'domain'          => $domain,
                'remote_name'     => $site->name ?? '',
                'remote_status'   => 'active',
                'created_by'      => null,
                'pillars'         => $pillars,
                'qc_badges'       => $qcBadges,
                'status_matrix'   => $statusMatrix,
                'completion_rate' => $completionRate,
                'total_cards'     => $totalTasks,
                'open_issues'     => $openIssues,
                'has_remote'      => true,
                'followup_schedule' => null,
                '_raw_tasks'      => $rawTasksArray,
                // Pillar detail counts
                'ebay_count'      => $ebayLogs->count(),
                'blog_count'      => $blogAudits->count(),
                'plugin_count'    => $pluginUp->count(),
                'optim_count'     => $optimizations->count(),
            ]);
        }

        return $result;
    }

    private function buildStatusMatrix(Collection $tasks): array
    {
        $matrix = [
            'todo'        => 0,
            'in_progress' => 0,
            'review'      => 0,
            'approved'    => 0,
            'done'        => 0,
            'rejected'    => 0,
        ];

        foreach ($tasks as $task) {
            $status = strtolower(str_replace(' ', '_', $task->status ?? ''));
            $status = match ($status) {
                'not_started', 'to_do', 'todo', 'pending' => 'todo',
                'in_progress', 'in-progress'         => 'in_progress',
                'review', 'under_review'             => 'review',
                'approved'                           => 'approved',
                'done', 'completed', 'finished'      => 'done',
                'rejected', 'cancelled'              => 'rejected',
                default                              => 'todo',
            };
            $matrix[$status]++;
        }

        return $matrix;
    }

    private function resolveDateRange(array $filters): array
    {
        $scope = $filters['date_scope'] ?? 'week';

        if ($scope === 'custom' && !empty($filters['date_from']) && !empty($filters['date_to'])) {
            return [
                $filters['date_from'] . ' 00:00:00',
                $filters['date_to'] . ' 23:59:59',
            ];
        }

        $now = now();
        $dateFrom = match ($scope) {
            'today' => $now->startOfDay()->toDateTimeString(),
            'week'  => $now->startOfWeek()->toDateTimeString(),
            'month' => $now->startOfMonth()->toDateTimeString(),
            default => $now->startOfWeek()->toDateTimeString(),
        };

        return [$dateFrom, now()->endOfDay()->toDateTimeString()];
    }

    private function normalizeDomain(string $domain): string
    {
        if ($domain === '') {
            return '';
        }

        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        $domain = strtolower(explode('/', $domain)[0]);

        return $domain;
    }
}
