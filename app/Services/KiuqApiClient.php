<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Setting;

/**
 * KiuqApiClient
 *
 * Fetches live "All Websites Summary" data from the remote kiuq.kiuq.net system.
 * Endpoint: GET /api/v1/websites-summary
 *
 * Returns a normalized Collection keyed by domain (lowercase, no www).
 */
class KiuqApiClient
{
    private string $baseUrl;
    private string $token;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('kiuq.api_url', 'https://mediumspringgreen-salamander-346021.hostingersite.com/'), '/');
        $this->timeout = (int) config('kiuq.timeout', 15);

        // Token priority: .env → DB setting → empty
        $envToken = config('kiuq.token', '');
        if ($envToken !== '') {
            $this->token = $envToken;
        } else {
            $this->token = (string) Setting::get('kiuq_api_token', '');
        }
    }

    // ─── Public Methods ───────────────────────────────────────────────────────

    /**
     * Check whether an API token is configured.
     */
    public function isConfigured(): bool
    {
        return $this->token !== '';
    }

    /**
     * Fetch website summary data from kiuq.kiuq.net.
     *
     * @param array{
     *   date_scope: string,
     *   date_from: string|null,
     *   date_to: string|null,
     * } $filters
     *
     * @return Collection<string, array>  Keyed by normalized domain (e.g. "miniexcavator.org")
     */
    public function fetchSummary(array $filters = []): Collection
    {
        if (!$this->isConfigured()) {
            Log::warning('KiuqApiClient: No API token configured. Set KIUQ_API_TOKEN in .env or Admin Settings.');
            return collect();
        }

        $params = $this->buildQueryParams($filters);

        try {
            $url = str_contains($this->baseUrl, 'supabase.co')
                ? "{$this->baseUrl}/websites-summary"
                : "{$this->baseUrl}/api/v1/websites-summary";

            $response = Http::withToken($this->token)
                ->acceptJson()
                ->timeout($this->timeout)
                ->get($url, $params);

            if ($response->failed()) {
                Log::error('KiuqApiClient: API request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return collect();
            }

            $json = $response->json();

            if (!($json['success'] ?? false) || empty($json['data'])) {
                return collect();
            }

            return $this->normalizeResponse($json['data']);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('KiuqApiClient: Connection error', ['message' => $e->getMessage()]);
            return collect();
        } catch (\Exception $e) {
            Log::error('KiuqApiClient: Unexpected error', ['message' => $e->getMessage()]);
            return collect();
        }
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Convert dashboard filter format into the API's query parameter format.
     * API accepts: range (day|week|month|all), start_date, end_date
     */
    private function buildQueryParams(array $filters): array
    {
        $scope = $filters['date_scope'] ?? 'week';

        if ($scope === 'custom' && !empty($filters['date_from']) && !empty($filters['date_to'])) {
            return [
                'start_date' => $filters['date_from'],
                'end_date'   => $filters['date_to'],
            ];
        }

        $range = match ($scope) {
            'today' => 'day',
            'week'  => 'week',
            'month' => 'month',
            default => 'week',
        };

        return ['range' => $range];
    }

    /**
     * Normalize the raw API response array into a flat Collection.
     * Key: lowercase domain without protocol/www (e.g. "miniexcavator.org")
     * Value: normalized data array for use in WebsitesDashboardService
     */
    private function normalizeResponse(array $data): Collection
    {
        $result = collect();

        foreach ($data as $site) {
            $domain = $this->normalizeDomain($site['domain'] ?? '');
            if (!$domain) {
                continue;
            }

            $followups = $site['followups'] ?? [];
            $tasks     = $site['tasks'] ?? [];
            $qcData    = $site['quality_check'] ?? [];

            // ── Pillar indicators ──────────────────────────────────────────
            // A pillar is "active" if there's at least one completed entry in scope
            $ebayLogs        = $followups['ebay_click_logs'] ?? [];
            $blogAudits      = $followups['blog_audits'] ?? [];
            $pluginUpdates   = $followups['plugin_updates'] ?? [];
            $optimizations   = $followups['website_optimizations'] ?? [];

            $pillars = [
                'ebay_click'   => $this->hasCompleted($ebayLogs),
                'blogs'        => $this->hasCompleted($blogAudits),
                'plugins'      => $this->hasCompleted($pluginUpdates),
                'optimization' => $this->hasCompleted($optimizations),
            ];

            // ── QC Badges ──────────────────────────────────────────────────
            $qcBadges = [
                'new_issue' => (int) ($qcData['new_issues_count'] ?? 0),
                'fixed'     => (int) ($qcData['fixed_count'] ?? 0),
                'approved'  => (int) ($qcData['approved_count'] ?? 0),
            ];

            // ── Task Status Matrix ─────────────────────────────────────────
            // Map remote task statuses to our local CardStatus values
            $statusMatrix = $this->buildStatusMatrix($tasks);

            // ── Followup Schedule ─────────────────────────────────────────
            $schedule = $site['followup_schedule'] ?? null;

            // ── Completion Rate ────────────────────────────────────────────
            $totalTasks     = count($tasks);
            $doneTasks      = count(array_filter($tasks, fn($t) => in_array(strtolower($t['status'] ?? ''), ['done', 'completed', 'approved'])));
            $completionRate = $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0;

            // ── Raw counts for global metrics ──────────────────────────────
            $openIssues = count(array_filter($tasks, fn($t) => strtolower($t['status'] ?? '') === 'to do'));

            $result->put($domain, [
                'domain'          => $domain,
                'remote_name'     => $site['name'] ?? '',
                'remote_status'   => $site['status'] ?? 'unknown',
                'created_by'      => $site['created_by'] ?? null,
                'pillars'         => $pillars,
                'qc_badges'       => $qcBadges,
                'status_matrix'   => $statusMatrix,
                'completion_rate' => $completionRate,
                'total_cards'     => $totalTasks,
                'open_issues'     => $openIssues,
                'has_remote'      => true,
                'followup_schedule' => $schedule,
                // Pillar detail counts (for tooltip/export)
                'ebay_count'      => count($ebayLogs),
                'blog_count'      => count($blogAudits),
                'plugin_count'    => count($pluginUpdates),
                'optim_count'     => count($optimizations),
            ]);
        }

        return $result;
    }

    /**
     * Check if any entry in a log array has status "done" or "completed".
     */
    private function hasCompleted(array $logs): bool
    {
        foreach ($logs as $entry) {
            if (in_array(strtolower($entry['status'] ?? ''), ['done', 'completed'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build a status matrix (todo/in_progress/review/approved/done/rejected counts)
     * from the remote tasks array.
     */
    private function buildStatusMatrix(array $tasks): array
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
            $status = strtolower(str_replace(' ', '_', $task['status'] ?? ''));
            $status = match ($status) {
                'to_do', 'todo', 'pending'          => 'todo',
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

    /**
     * Normalize a domain string: strip protocol, www, trailing slash.
     * e.g. "https://www.miniexcavator.org/" → "miniexcavator.org"
     */
    private function normalizeDomain(string $domain): string
    {
        if ($domain === '') {
            return '';
        }

        // Remove protocol
        $domain = preg_replace('#^https?://#', '', $domain);

        // Remove www.
        $domain = preg_replace('#^www\.#', '', $domain);

        // Remove path/trailing slash
        $domain = strtolower(explode('/', $domain)[0]);

        return $domain;
    }
}
