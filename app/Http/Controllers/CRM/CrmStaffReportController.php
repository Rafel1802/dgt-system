<?php

namespace App\Http\Controllers\CRM;

use App\Enums\WebsiteLeadStatus;
use App\Http\Controllers\Controller;
use App\Models\CallReport;
use App\Models\EbayCustomerHandlerHistory;
use App\Models\EbayCustomerRecord;
use App\Models\EbayStore;
use App\Models\Lead;
use App\Models\Shipment;
use App\Models\TechSupportCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmStaffReportController extends Controller
{
    /** Team-grouped, clickable staff profiles + team KPI tiles, mirroring the demo's Reports tab */
    public function index(): View
    {
        $monthStart = now()->startOfMonth();
        $staffPool = $this->staffPool();

        // A user shows up under a team if they have ever handled/been assigned
        // anything in that domain — there's no separate department field, so
        // team membership is inferred from activity, same as the rest of this
        // report already does per-metric.
        $teams = [
            'website' => [
                'label' => 'CRM-Website',
                'members' => $staffPool->filter(fn ($u) => Lead::where('handled_by', $u->id)->exists())
                    ->map(fn ($u) => $this->websiteSummary($u, $monthStart))->values(),
            ],
            'ebay' => [
                'label' => 'eBay',
                'members' => $staffPool->filter(fn ($u) => EbayCustomerHandlerHistory::where('user_id', $u->id)->exists())
                    ->map(fn ($u) => $this->ebaySummary($u, $monthStart))->values(),
            ],
            'tech_support' => [
                'label' => 'Technical Support',
                'members' => $staffPool->filter(fn ($u) => TechSupportCase::where('assigned_to', $u->id)->exists())
                    ->map(fn ($u) => $this->techSupportSummary($u, $monthStart))->values(),
            ],
            'logistic' => [
                'label' => 'Logistic',
                'members' => $staffPool->filter(fn ($u) => Shipment::where('assigned_to', $u->id)->exists())
                    ->map(fn ($u) => $this->logisticSummary($u, $monthStart))->values(),
            ],
        ];

        $negTotalMonth  = EbayCustomerRecord::where('tab_type', EbayCustomerRecord::TAB_NEGATIVES)->where('created_at', '>=', $monthStart)->count();
        $negSolvedMonth = EbayCustomerRecord::where('negative_feedback_resolved', true)->where('negative_feedback_resolved_at', '>=', $monthStart)->count();
        $salesMonth     = EbayStore::sum('total_sales');

        $completeWeek  = Shipment::where('status', Shipment::STATUS_COMPLETE)->where('updated_at', '>=', now()->subDays(7))->count();
        $completeMonth = Shipment::where('status', Shipment::STATUS_COMPLETE)->where('updated_at', '>=', $monthStart)->count();

        $techWeek  = Lead::where('status', WebsiteLeadStatus::TechnicalSupport)->where('created_at', '>=', now()->subDays(7))->count()
            + EbayCustomerRecord::where('tab_type', EbayCustomerRecord::TAB_TECHNICAL)->where('created_at', '>=', now()->subDays(7))->count();
        $techMonth = Lead::where('status', WebsiteLeadStatus::TechnicalSupport)->where('created_at', '>=', $monthStart)->count()
            + EbayCustomerRecord::where('tab_type', EbayCustomerRecord::TAB_TECHNICAL)->where('created_at', '>=', $monthStart)->count();

        return view('crm.reports.index', compact(
            'teams', 'negTotalMonth', 'negSolvedMonth', 'salesMonth',
            'completeWeek', 'completeMonth', 'techWeek', 'techMonth'
        ));
    }

    /** Per-staff profile: KPI summary + a day/week/month activity chart across all 4 CRM domains */
    public function show(User $user, Request $request): View
    {
        $granularity = in_array($request->get('period'), ['day', 'week', 'month']) ? $request->get('period') : 'week';
        $periods = ['day' => 14, 'week' => 12, 'month' => 12][$granularity];

        $chart = $this->buildChart($user, $granularity, $periods);
        $monthStart = now()->startOfMonth();

        $summary = [
            'website'      => $this->websiteSummary($user, $monthStart),
            'ebay'         => $this->ebaySummary($user, $monthStart),
            'tech_support' => $this->techSupportSummary($user, $monthStart),
            'logistic'     => $this->logisticSummary($user, $monthStart),
        ];

        return view('crm.reports.show', compact('user', 'chart', 'granularity', 'summary'));
    }

    /** Export the same day/week/month bucketed activity report as CSV */
    public function export(User $user, Request $request): StreamedResponse
    {
        $granularity = in_array($request->get('period'), ['day', 'week', 'month']) ? $request->get('period') : 'week';
        $periods = ['day' => 14, 'week' => 12, 'month' => 12][$granularity];
        $chart = $this->buildChart($user, $granularity, $periods);

        $filename = 'staff-report-' . Str::slug($user->name) . '-' . $granularity . '.csv';

        return response()->streamDownload(function () use ($chart) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Period', 'CRM Website', 'eBay', 'Technical Support', 'Logistic', 'Total']);
            foreach ($chart['labels'] as $i => $label) {
                $row = [
                    $label,
                    $chart['datasets']['website'][$i],
                    $chart['datasets']['ebay'][$i],
                    $chart['datasets']['tech_support'][$i],
                    $chart['datasets']['logistic'][$i],
                ];
                $row[] = array_sum([$row[1], $row[2], $row[3], $row[4]]);
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Everyone eligible to appear on this report: any CRM-facing role, including Tech Support */
    private function staffPool()
    {
        return User::where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super-admin', 'admin-crm', 'sales-crm', 'boss', 'tech-support']))
            ->orderBy('name')
            ->get();
    }

    private function websiteSummary(User $user, $monthStart): array
    {
        return [
            'user'           => $user,
            'crm_handled'    => Lead::where('handled_by', $user->id)->where('created_at', '>=', $monthStart)->count(),
            'crm_sales'      => Lead::where('handled_by', $user->id)->where('status', WebsiteLeadStatus::Successful)->count(),
            'calls_answered' => CallReport::where('answered_by', $user->id)->where('occurred_at', '>=', $monthStart)->count(),
        ];
    }

    private function ebaySummary(User $user, $monthStart): array
    {
        return [
            'user'         => $user,
            'ebay_handled' => EbayCustomerHandlerHistory::where('user_id', $user->id)->count(),
            'neg_solved'   => EbayCustomerHandlerHistory::where('user_id', $user->id)
                ->whereHas('record', fn ($q) => $q->where('negative_feedback_resolved', true))
                ->count(),
        ];
    }

    private function techSupportSummary(User $user, $monthStart): array
    {
        return [
            'user'      => $user,
            'assigned'  => TechSupportCase::where('assigned_to', $user->id)->count(),
            'resolved'  => TechSupportCase::where('assigned_to', $user->id)->where('status', TechSupportCase::STATUS_RESOLVED)->count(),
        ];
    }

    private function logisticSummary(User $user, $monthStart): array
    {
        return [
            'user'     => $user,
            'assigned' => Shipment::where('assigned_to', $user->id)->count(),
            'complete' => Shipment::where('assigned_to', $user->id)->where('status', Shipment::STATUS_COMPLETE)->count(),
        ];
    }

    /**
     * Bucket a user's activity across all 4 CRM domains into day/week/month
     * periods for the chart + CSV export. Buckets in PHP (not SQL date
     * functions) so this behaves identically on MySQL and SQLite.
     */
    private function buildChart(User $user, string $granularity, int $periods): array
    {
        $since = match ($granularity) {
            'day'   => now()->subDays($periods - 1)->startOfDay(),
            'week'  => now()->subWeeks($periods - 1)->startOfWeek(),
            'month' => now()->subMonths($periods - 1)->startOfMonth(),
        };

        $bucketKey = fn (Carbon $date) => match ($granularity) {
            'day'   => $date->format('Y-m-d'),
            'week'  => $date->startOfWeek()->format('Y-m-d'),
            'month' => $date->format('Y-m'),
        };

        $labels = [];
        $cursor = $since->copy();
        for ($i = 0; $i < $periods; $i++) {
            $labels[] = match ($granularity) {
                'day'   => $cursor->format('d M'),
                'week'  => 'Wk of ' . $cursor->format('d M'),
                'month' => $cursor->format('M Y'),
            };
            $cursor = match ($granularity) {
                'day'   => $cursor->addDay(),
                'week'  => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
            };
        }

        $bucketKeys = [];
        $cursor = $since->copy();
        for ($i = 0; $i < $periods; $i++) {
            $bucketKeys[] = $bucketKey($cursor->copy());
            $cursor = match ($granularity) {
                'day'   => $cursor->addDay(),
                'week'  => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
            };
        }

        $tally = function ($dates) use ($bucketKey, $bucketKeys) {
            $counts = array_fill_keys($bucketKeys, 0);
            foreach ($dates as $date) {
                $key = $bucketKey(Carbon::parse($date));
                if (array_key_exists($key, $counts)) {
                    $counts[$key]++;
                }
            }
            return array_values($counts);
        };

        return [
            'labels' => $labels,
            'datasets' => [
                'website'      => $tally(Lead::where('handled_by', $user->id)->where('created_at', '>=', $since)->pluck('created_at')),
                'ebay'         => $tally(EbayCustomerHandlerHistory::where('user_id', $user->id)->where('started_at', '>=', $since)->pluck('started_at')),
                'tech_support' => $tally(TechSupportCase::where('assigned_to', $user->id)->where('created_at', '>=', $since)->pluck('created_at')),
                'logistic'     => $tally(Shipment::where('assigned_to', $user->id)->where('created_at', '>=', $since)->pluck('created_at')),
            ],
        ];
    }
}
