<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\EbayCustomerOrder;
use App\Models\EbayCustomerOrderItem;
use App\Models\EbayCustomerRecord;
use App\Models\EbayStore;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EbayReportController extends Controller
{
    /** eBay Team Report: per-store sales/orders (+ totals) and negative-feedback tiles, filterable by day/week/month or a custom date range. */
    public function index(Request $request): View
    {
        [$since, $until, $periodLabel, $granularity] = $this->resolvePeriod($request);

        $storeReports = $this->buildStoreReports($since, $until);
        $totalOrders = $storeReports->sum('orders');
        $totalSales = $storeReports->sum('sales');

        $negTotal = EbayCustomerRecord::where('tab_type', EbayCustomerRecord::TAB_NEGATIVES)
            ->whereBetween('created_at', [$since, $until])->count();
        $negSolved = EbayCustomerRecord::where('negative_feedback_resolved', true)
            ->whereBetween('negative_feedback_resolved_at', [$since, $until])->count();

        return view('crm.ebay.report', compact(
            'storeReports', 'totalOrders', 'totalSales', 'negTotal', 'negSolved', 'periodLabel', 'granularity'
        ));
    }

    /** Export the eBay Report (same period filter as the page) as a PDF. */
    public function exportPdf(Request $request)
    {
        [$since, $until, $periodLabel] = $this->resolvePeriod($request);

        $storeReports = $this->buildStoreReports($since, $until);
        $totalOrders = $storeReports->sum('orders');
        $totalSales = $storeReports->sum('sales');

        $filename = 'ebay-report-' . now()->format('Ymd_His') . '.pdf';

        return Pdf::loadView('reports.ebay_store_report_export', compact('storeReports', 'totalOrders', 'totalSales', 'periodLabel'))
            ->download($filename);
    }

    /** Export the eBay Report (same period filter as the page) as a CSV. */
    public function exportCsv(Request $request): StreamedResponse
    {
        [$since, $until, $periodLabel] = $this->resolvePeriod($request);

        $storeReports = $this->buildStoreReports($since, $until);

        $filename = 'ebay-report-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($storeReports, $periodLabel) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['eBay Report', $periodLabel]);
            fputcsv($out, []);
            fputcsv($out, ['Store', 'Orders', 'Sales']);
            foreach ($storeReports as $row) {
                fputcsv($out, [$row['store']->store_name, $row['orders'], number_format($row['sales'], 2)]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Total', $storeReports->sum('orders'), number_format($storeReports->sum('sales'), 2)]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Per-store sales + order count for the given window. Sales are summed
     * from real order-item prices (EbayCustomerOrder/Item — the only eBay
     * money-tracking path actually populated by usage), not the
     * unmaintained EbayStore.total_sales field.
     */
    private function buildStoreReports(Carbon $since, Carbon $until): Collection
    {
        return EbayStore::orderBy('store_name')->get()->map(function (EbayStore $store) use ($since, $until) {
            $orders = EbayCustomerOrder::where('ebay_store_id', $store->id)
                ->whereBetween('ordered_at', [$since, $until]);

            $sales = (float) EbayCustomerOrderItem::whereHas('order', fn ($q) => $q
                ->where('ebay_store_id', $store->id)
                ->whereBetween('ordered_at', [$since, $until])
            )->sum('price');

            return [
                'store'  => $store,
                'orders' => (clone $orders)->count(),
                'sales'  => $sales,
            ];
        });
    }

    /**
     * A custom date range (date_from/date_to) always wins over the
     * day/week/month tabs — same "explicit filter wins" convention used by
     * the Call Reports page.
     */
    private function resolvePeriod(Request $request): array
    {
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $from = $request->filled('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : Carbon::createFromDate(2000, 1, 1);
            $to = $request->filled('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : now()->endOfDay();
            $label = $from->format('d M Y') . ' – ' . $to->format('d M Y');

            return [$from, $to, $label, null];
        }

        $granularity = in_array($request->get('period'), ['day', 'week', 'month']) ? $request->get('period') : 'month';

        return match ($granularity) {
            'day'   => [now()->startOfDay(), now()->endOfDay(), 'Today', $granularity],
            'week'  => [now()->startOfWeek(), now()->endOfWeek(), 'This Week', $granularity],
            'month' => [now()->startOfMonth(), now()->endOfMonth(), 'This Month', $granularity],
        };
    }
}
