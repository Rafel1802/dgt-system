<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Models\Board;
use App\Models\Card;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * WebsitesDashboardService
 *
 * Aggregates data from TWO sources:
 *   1. LOCAL   — Boards/Cards in this dgt-system database (local Kanban boards)
 *   2. REMOTE  — kiuq.kiuq.net REST API (live followup logs, QC, plugin updates, etc.)
 *
 * The two sources are merged by domain match. Remote data takes priority for
 * pillars/QC/followup details; local board data fills in task status matrix.
 */
class WebsitesDashboardService
{
    private KiuqDatabaseClient $apiClient;

    public function __construct()
    {
        $this->apiClient = new KiuqDatabaseClient();
    }

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Build the full executive dashboard payload.
     *
     * @param array{
     *   date_scope: string,
     *   date_from: string|null,
     *   date_to: string|null,
     *   member_id: int|null,
     *   site_ids: int[],
     * } $filters
     */
    public function aggregate(array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);

        $websites = $this->resolveWebsites($filters['site_ids'] ?? []);

        // Resolve member email for remote filtering
        $memberEmail = null;
        if (!empty($filters['member_id'])) {
            $member = \App\Models\User::find($filters['member_id']);
            $memberEmail = $member ? $member->email : null;
        }
        $filters['member_email'] = $memberEmail;

        // ── Fetch remote API data (all websites at once) ──────────────────────
        $apiData        = $this->apiClient->fetchSummary($filters);
        $apiConfigured  = $this->apiClient->isConfigured();

        // ── Build per-website card data ───────────────────────────────────────
        $websiteData = $websites->map(function (Website $website) use ($from, $to, $filters, $apiData) {
            $remoteEntry = $this->findRemoteEntry($website, $apiData);
            return $this->buildWebsiteCard($website, $from, $to, $filters['member_id'] ?? null, $remoteEntry);
        })->values();

        // ── Global metrics (aggregate across all sites) ───────────────────────
        $allRawCards = $websiteData->flatMap(fn($wd) => $wd['_raw_cards']);

        // If API has data, add API-sourced open issues + blog/plugin counts
        $apiOpenIssues    = $apiData->sum('open_issues');
        $apiBlogCount     = $apiData->sum('blog_count');
        $apiPluginCount   = $apiData->sum('plugin_count');
        $apiApproved      = $apiData->sum(fn($d) => $d['qc_badges']['approved'] ?? 0);
        $apiTotalTasks    = $apiData->sum('total_cards');

        // Compute global from merged sources
        $localOpenIssues  = $allRawCards->where('status_value', 'todo')->count();
        $localBlogCount   = $allRawCards->filter(fn($c) => str_contains(strtolower($c['sub_label'] ?? ''), 'blog'))->count();
        $localPluginCount = $allRawCards->filter(fn($c) => str_contains(strtolower($c['sub_label'] ?? ''), 'plugin'))->count();

        // Prefer API data when available, add local for any gaps
        $globalMetrics = [
            'total_open_issues'       => $apiData->isNotEmpty() ? $apiOpenIssues : $localOpenIssues,
            'overall_completion_rate' => $this->globalCompletionRate($websiteData),
            'active_blog_schedules'   => $apiData->isNotEmpty() ? $apiBlogCount   : $localBlogCount,
            'pending_plugin_updates'  => $apiData->isNotEmpty() ? $apiPluginCount  : $localPluginCount,
        ];

        // Strip raw cards (not needed by view)
        $websiteData = $websiteData->map(function ($wd) {
            unset($wd['_raw_cards']);
            return $wd;
        });

        return [
            'global_metrics'  => $globalMetrics,
            'website_cards'   => $websiteData,
            'date_from'       => $from,
            'date_to'         => $to,
            'total_websites'  => $websites->count(),
            'api_configured'  => $apiConfigured,
            'api_connected'   => $apiData->isNotEmpty(),
        ];
    }

    /**
     * Build the export rows for all resolved websites.
     * Includes both local board cards AND remote tasks from the API.
     */
    public function exportRows(array $filters): Collection
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $websites    = $this->resolveWebsites($filters['site_ids'] ?? []);
        $memberId    = $filters['member_id'] ?? null;

        // Resolve member email for remote filtering
        $memberEmail = null;
        if ($memberId) {
            $member = \App\Models\User::find($memberId);
            $memberEmail = $member ? $member->email : null;
        }
        $filters['member_email'] = $memberEmail;

        $apiData     = $this->apiClient->fetchSummary($filters);
        $rows        = collect();

        foreach ($websites as $website) {
            // ── Local board cards ─────────────────────────────────────────────
            $boards = $this->getBoardsForWebsite($website);
            $cards  = $boards->isNotEmpty()
                ? $this->getCardsForBoards($boards->pluck('id')->toArray(), $from, $to, $memberId)
                : collect();

            foreach ($cards as $card) {
                $assignees = collect($card['assignees'] ?? [])->pluck('name')->implode(', ');
                $rows->push([
                    'Source'              => 'Local Board',
                    'Website Name'        => $website->name,
                    'Module'              => $card['label'] ?? '-',
                    'Task/Action Name'    => $card['title'] ?? '-',
                    'Assigned Member'     => $assignees ?: '-',
                    'Status'              => $card['status'] instanceof CardStatus
                                                ? $card['status']->label()
                                                : (string) ($card['status'] ?? '-'),
                    'Due Date'            => $card['due_at']
                                                ? Carbon::parse($card['due_at'])->format('Y-m-d')
                                                : ($card['deadline']
                                                    ? Carbon::parse($card['deadline'])->format('Y-m-d')
                                                    : '-'),
                    'Completion/Sync Timestamp' => $card['approved_at']
                                                ? Carbon::parse($card['approved_at'])->format('Y-m-d H:i')
                                                : ($card['updated_at']
                                                    ? Carbon::parse($card['updated_at'])->format('Y-m-d H:i')
                                                    : '-'),
                ]);
            }

            // ── Remote API tasks for this website ─────────────────────────────
            $remoteEntry = $this->findRemoteEntry($website, $apiData);
            if ($remoteEntry && !empty($remoteEntry['_raw_tasks'])) {
                foreach ($remoteEntry['_raw_tasks'] as $task) {
                    $rows->push([
                        'Source'              => 'Remote (kiuq.net)',
                        'Website Name'        => $website->name,
                        'Module'              => 'Website Task',
                        'Task/Action Name'    => $task['title'] ?? '-',
                        'Assigned Member'     => $task['assigned_member']['name'] ?? '-',
                        'Status'              => $task['status'] ?? '-',
                        'Due Date'            => '-',
                        'Completion/Sync Timestamp' => now()->format('Y-m-d H:i'),
                    ]);
                }
            }
        }

        return $rows;
    }

    // ─── Internal: Per-Website Card Builder ───────────────────────────────────

    /**
     * Build all data for a single website card on the dashboard.
     * Merges local board data (for status matrix) with remote API data (for pillars/QC).
     */
    private function buildWebsiteCard(
        Website $website,
        Carbon $from,
        Carbon $to,
        ?int $memberId,
        ?array $remoteEntry
    ): array {
        // ── Local board data ──────────────────────────────────────────────────
        $boards     = $this->getBoardsForWebsite($website);
        $hasBoards  = $boards->isNotEmpty();

        if ($hasBoards) {
            $cards = $this->getCardsForBoards($boards->pluck('id')->toArray(), $from, $to, $memberId);
        } else {
            $cards = collect();
        }

        // ── Status Matrix: prefer local boards; fall back to remote ───────────
        if ($hasBoards && $cards->isNotEmpty()) {
            $statusMatrix = [
                'todo'        => $cards->where('status_value', 'todo')->count(),
                'in_progress' => $cards->where('status_value', 'in_progress')->count(),
                'review'      => $cards->where('status_value', 'review')->count(),
                'approved'    => $cards->where('status_value', 'approved')->count(),
                'done'        => $cards->where('status_value', 'done')->count(),
                'rejected'    => $cards->where('status_value', 'rejected')->count(),
            ];
            $totalCards     = $cards->count();
            $completionRate = $this->completionRate($cards);
        } elseif ($remoteEntry) {
            $statusMatrix   = $remoteEntry['status_matrix'];
            $totalCards     = $remoteEntry['total_cards'];
            $completionRate = $remoteEntry['completion_rate'];
        } else {
            $statusMatrix   = ['todo' => 0, 'in_progress' => 0, 'review' => 0, 'approved' => 0, 'done' => 0, 'rejected' => 0];
            $totalCards     = 0;
            $completionRate = 0;
        }

        // ── Pillars: prefer remote (it has dedicated tracking tables) ─────────
        if ($remoteEntry) {
            $pillars = $remoteEntry['pillars'];
        } else {
            // Fall back to local sub_label detection
            $pillars = [
                'ebay_click'   => $cards->filter(fn($c) => $this->subLabelContains($c, ['new listing', 'edit listing', 'seo', 'price update', 'ebay']))->isNotEmpty(),
                'optimization' => $cards->filter(fn($c) => $this->subLabelContains($c, ['seo', 'optimization', 'landing page']))->isNotEmpty(),
                'blogs'        => $cards->filter(fn($c) => $this->subLabelContains($c, ['blog']))->isNotEmpty(),
                'plugins'      => $cards->filter(fn($c) => $this->subLabelContains($c, ['plugin', 'bug fix']))->isNotEmpty(),
            ];
        }

        // ── QC Badges: prefer remote (it has quality_issues table) ────────────
        if ($remoteEntry) {
            $qcBadges = $remoteEntry['qc_badges'];
        } else {
            $qcBadges = [
                'new_issue' => $cards->where('status_value', 'todo')->count(),
                'fixed'     => $cards->whereIn('status_value', ['in_progress', 'review'])->count(),
                'approved'  => $cards->whereIn('status_value', ['approved', 'done'])->count(),
            ];
        }

        // ── Raw cards for global aggregation ──────────────────────────────────
        $rawCards = $cards->map(fn($c) => [
            'status_value' => $c['status_value'],
            'sub_label'    => $c['sub_label'],
        ]);

        return [
            'website'            => $website,
            'has_boards'         => $hasBoards,
            'has_remote'         => $remoteEntry !== null,
            'boards_count'       => $boards->count(),
            'total_cards'        => $totalCards,
            'completion_rate'    => $completionRate,
            'status_matrix'      => $statusMatrix,
            'pillars'            => $pillars,
            'qc_badges'          => $qcBadges,
            'followup_schedule'  => $remoteEntry['followup_schedule'] ?? null,
            '_raw_cards'         => $rawCards,
            '_raw_tasks'         => $remoteEntry['_raw_tasks'] ?? [],
        ];
    }

    // ─── Internal: Remote Data Matching ───────────────────────────────────────

    /**
     * Find the matching remote API entry for a local website.
     * Match by: domain (stripped) first, then by name substring.
     */
    private function findRemoteEntry(Website $website, Collection $apiData): ?array
    {
        if ($apiData->isEmpty()) {
            return null;
        }

        // Try domain match first
        $localDomain = $this->normalizeDomain($website->url ?? '');
        if ($localDomain && $apiData->has($localDomain)) {
            $entry = $apiData->get($localDomain);
            $entry['_raw_tasks'] = []; // tasks not preserved in apiData (only counts)
            return $entry;
        }

        // Fall back: fuzzy name match
        $localName = strtolower($website->name);
        $matched   = $apiData->first(function ($entry) use ($localName) {
            $remoteName = strtolower($entry['remote_name'] ?? '');
            $remoteDomain = $entry['domain'] ?? '';
            return str_contains($remoteDomain, str_replace(' ', '', $localName))
                || str_contains($remoteName, $localName)
                || str_contains($localName, $remoteName);
        });

        if ($matched) {
            $matched['_raw_tasks'] = [];
            return $matched;
        }

        return null;
    }

    // ─── Internal: Local DB Helpers ───────────────────────────────────────────

    private function getBoardsForWebsite(Website $website): Collection
    {
        return Board::where('is_archived', false)
            ->where('name', 'LIKE', '%' . $website->name . '%')
            ->get();
    }

    private function getCardsForBoards(array $boardIds, Carbon $from, Carbon $to, ?int $memberId): Collection
    {
        if (empty($boardIds)) {
            return collect();
        }

        $query = Card::whereIn('board_id', $boardIds)
            ->where('is_archived', false)
            ->whereBetween('created_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->with(['assignees:id,name']);

        if ($memberId) {
            $query->whereHas('assignees', fn($q) => $q->where('users.id', $memberId));
        }

        return $query->get()->map(function (Card $card) {
            $statusValue = $card->status instanceof CardStatus
                ? $card->status->value
                : (string) $card->status;

            return [
                'id'           => $card->id,
                'title'        => $card->title,
                'label'        => $card->label,
                'sub_label'    => $card->sub_label,
                'status_value' => $statusValue,
                'due_at'       => $card->due_at,
                'deadline'     => $card->deadline,
                'approved_at'  => $card->approved_at,
                'updated_at'   => $card->updated_at,
                'assignees'    => $card->assignees,
                'status'       => $card->status,
            ];
        });
    }

    private function completionRate(Collection $cards): int
    {
        $total = $cards->count();
        if ($total === 0) {
            return 0;
        }
        $completed = $cards->filter(fn($c) => in_array($c['status_value'] ?? '', ['approved', 'done']))->count();
        return (int) round(($completed / $total) * 100);
    }

    private function globalCompletionRate(Collection $websiteData): int
    {
        $rates = $websiteData->pluck('completion_rate')->filter(fn($r) => $r > 0);
        return $rates->isEmpty() ? 0 : (int) round($rates->average());
    }

    private function subLabelContains(array $card, array $keywords): bool
    {
        $subLabel = strtolower($card['sub_label'] ?? '');
        foreach ($keywords as $kw) {
            if (str_contains($subLabel, $kw)) {
                return true;
            }
        }
        return false;
    }

    private function resolveWebsites(array $siteIds): Collection
    {
        $query = Website::with('handler')
            ->where('is_archived', false)
            ->orderBy('name');

        if (!empty($siteIds)) {
            $query->whereIn('id', $siteIds);
        }

        return $query->get();
    }

    private function resolveDateRange(array $filters): array
    {
        $scope = $filters['date_scope'] ?? 'month';

        return match ($scope) {
            'today'  => [Carbon::today(), Carbon::today()],
            'week'   => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'month'  => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'custom' => [
                Carbon::parse($filters['date_from'] ?? now()->startOfMonth()),
                Carbon::parse($filters['date_to']   ?? now()),
            ],
            default  => [Carbon::now()->startOfMonth(), Carbon::now()],
        };
    }

    private function normalizeDomain(string $url): string
    {
        if ($url === '') {
            return '';
        }
        $domain = preg_replace('#^https?://#', '', $url);
        $domain = preg_replace('#^www\.#', '', $domain);
        $domain = strtolower(explode('/', $domain)[0]);
        return $domain;
    }
}
