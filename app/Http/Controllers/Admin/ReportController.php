<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function index(Request $request): View
    {
        abort_unless(auth()->user()->hasAnyRole(['super-admin','admin-digital','admin-crm','sales-crm','boss']), 403);

        $range = $request->get('range', 'month');
        [$dateFrom, $dateTo] = $this->reportService->resolveDateRange(
            $range, $request->get('from'), $request->get('to')
        );

        $kpis               = $this->reportService->getKpis($dateFrom, $dateTo);
        $revenueOverTime    = $this->reportService->getRevenueOverTime($dateFrom, $dateTo);
        $tasksByLabel       = $this->reportService->getTasksByLabel($dateFrom, $dateTo);
        $tasksByStatus      = $this->reportService->getTasksByStatus($dateFrom, $dateTo);
        $staffPerformance   = $this->reportService->getStaffPerformance($dateFrom, $dateTo);
        $customersBySource  = $this->reportService->getCustomersBySource($dateFrom, $dateTo);
        $customersByStatus  = $this->reportService->getCustomersByStatus($dateFrom, $dateTo);
        $salesByStaff       = $this->reportService->getSalesByStaff($dateFrom, $dateTo);
        $salesByProductType = $this->reportService->getSalesByProductType($dateFrom, $dateTo);
        // CRM Phase 5
        $salesBySource      = $this->reportService->getSalesBySource($dateFrom, $dateTo);
        $salesByProduct     = $this->reportService->getSalesByProduct($dateFrom, $dateTo);
        $leadFunnel         = $this->reportService->getLeadConversionFunnel($dateFrom, $dateTo);
        $staffCrmPerf       = $this->reportService->getStaffCrmPerformance($dateFrom, $dateTo);
        $ebayAuthStats      = $this->reportService->getEbayAuthStats($dateFrom, $dateTo);
        $logisticsCost      = $this->reportService->getLogisticsCostReport($dateFrom, $dateTo);
        $logisticsByStatus  = $this->reportService->getLogisticsByStatus($dateFrom, $dateTo);

        return view('reports.index', compact(
            'range', 'dateFrom', 'dateTo', 'kpis',
            'revenueOverTime', 'tasksByLabel', 'tasksByStatus',
            'staffPerformance', 'customersBySource',
            'customersByStatus', 'salesByStaff', 'salesByProductType',
            'salesBySource', 'salesByProduct', 'leadFunnel',
            'staffCrmPerf', 'ebayAuthStats', 'logisticsCost', 'logisticsByStatus',
        ));
    }
}
