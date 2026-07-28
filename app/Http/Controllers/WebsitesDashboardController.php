<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Website;
use App\Services\WebsitesDashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WebsitesDashboardController extends Controller
{
    const ALLOWED_ROLES = ['super-admin', 'admin-digital', 'digital-team', 'boss'];

    public function __construct(
        private readonly WebsitesDashboardService $service
    ) {}

    // ─── INDEX ────────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403);

        $filters = $this->resolveFilters($request);

        $payload = $this->service->aggregate($filters);

        // All non-archived websites for the site filter dropdown
        $allWebsites = Website::where('is_archived', false)
            ->orderBy('name')
            ->get(['id', 'name', 'logo_path']);

        // All users for the member dropdown
        $members = User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'avatar']);

        return view('websites.dashboard', [
            'globalMetrics'  => $payload['global_metrics'],
            'websiteCards'   => $payload['website_cards'],
            'allWebsites'    => $allWebsites,
            'members'        => $members,
            'filters'        => $filters,
            'dateFrom'       => $payload['date_from']->format('Y-m-d'),
            'dateTo'         => $payload['date_to']->format('Y-m-d'),
            'totalWebsites'  => $payload['total_websites'],
            'apiConfigured'  => $payload['api_configured'],
            'apiConnected'   => $payload['api_connected'],
        ]);

    }

    // ─── EXPORT ───────────────────────────────────────────────────────────────

    public function export(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403);

        $filters = $this->resolveFilters($request);
        $rows    = $this->service->exportRows($filters);

        $scope    = $filters['date_scope'] === 'custom'
            ? $filters['date_from'] . '_to_' . $filters['date_to']
            : $filters['date_scope'];
        $filename = 'websites-report-' . $scope . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            if ($rows->isNotEmpty()) {
                fputcsv($handle, array_keys($rows->first()));
            } else {
                fputcsv($handle, [
                    'Website Name', 'Module', 'Task/Action Name',
                    'Assigned Member', 'Status', 'Due Date', 'Completion/Sync Timestamp',
                ]);
            }

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────────

    private function resolveFilters(Request $request): array
    {
        $scope = $request->get('date_scope', 'month');
        if (!in_array($scope, ['today', 'week', 'month', 'custom'])) {
            $scope = 'month';
        }

        return [
            'date_scope' => $scope,
            'date_from'  => $request->get('date_from'),
            'date_to'    => $request->get('date_to'),
            'member_id'  => $request->filled('member_id') ? (int) $request->get('member_id') : null,
            'site_ids'   => array_filter(
                array_map('intval', (array) $request->get('site_ids', []))
            ),
        ];
    }
}
