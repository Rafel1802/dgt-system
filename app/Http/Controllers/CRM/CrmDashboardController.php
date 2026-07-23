<?php

namespace App\Http\Controllers\CRM;

use App\Enums\EbayLeadStatus;
use App\Enums\LogisticStatus;
use App\Enums\WebsiteLeadStatus;
use App\Http\Controllers\Controller;
use App\Models\CallRequest;
use App\Models\EbayCustomerRecord;
use App\Models\EbayOffer;
use App\Models\EbayStore;
use App\Models\Lead;
use App\Models\Logistic;
use App\Models\Shipment;
use App\Models\TruckingCompany;
use App\Services\CrmCustomerMatchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CrmDashboardController extends Controller
{
    public function __construct(private CrmCustomerMatchService $matcher)
    {
    }

    public function index(): View
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'admin-crm', 'sales-crm', 'boss', 'logistic']),
            403
        );

        $today = today()->toDateString();

        // Aggregate KPI tiles: short-lived cache (60s). Not customer-row PII.
        // Same numbers as before — just not recomputed on every page load.
        $cached = Cache::remember("crm.dashboard.page_kpis.{$today}", 60, function () {
            $today = today();

            $websiteStats = [
                'new_today'      => Lead::whereDate('received_at', $today)->count(),
                'hot_leads'      => Lead::where('temperature', 'hot')->active()->count(),
                'follow_up_due'  => Lead::followUpDue()->count(),
                'successful'     => Lead::where('status', WebsiteLeadStatus::Successful->value)->count(),
                'pipeline'       => $this->getWebsitePipeline(),
            ];

            $ebayStats = [
                'new_inquiries'   => EbayOffer::where('status', EbayLeadStatus::Inquiry->value)->count(),
                'waiting_auth'    => EbayOffer::waitingAuthorization()->count(),
                'converted'       => EbayOffer::where('status', EbayLeadStatus::ConvertedLead->value)->count(),
                'orders_confirmed'=> EbayOffer::where('status', EbayLeadStatus::OrderConfirmed->value)->count(),
            ];

            $logisticStats = [
                'waiting_verify' => Logistic::where('status', LogisticStatus::OrderConfirmed->value)->count(),
                'truck_searching'=> Logistic::where('status', LogisticStatus::TruckSearching->value)->count(),
                'in_transit'     => Logistic::inTransit()->count(),
                'delivered_today'=> Logistic::where('status', LogisticStatus::Delivered->value)
                                        ->whereDate('actual_arrival', $today)->count(),
            ];

            $dedupedCustomers = $this->matcher->dedupedCustomerCount();
            $techIssuesOpen = Lead::technicalIssuesOpen()->count()
                + EbayCustomerRecord::where('tab_type', EbayCustomerRecord::TAB_TECHNICAL)->where('tech_resolved', false)->count();
            $negFeedbackOpen = EbayCustomerRecord::whereIn('tab_type', [EbayCustomerRecord::TAB_POT_NEGATIVES, EbayCustomerRecord::TAB_NEGATIVES])
                ->where('negative_feedback_resolved', false)
                ->count();
            $activeShipments = Shipment::where('status', '!=', Shipment::STATUS_COMPLETE)->count();
            $truckingCompanyCount = TruckingCompany::active()->count();
            $ebayStoreCount = EbayStore::active()->count();
            $pendingCallRequests = CallRequest::pending()->count();

            $statusChart = [
                'labels' => collect(WebsiteLeadStatus::cases())->map->label()->all(),
                'data'   => collect($websiteStats['pipeline'])->pluck('count')->all(),
            ];
            $shipmentCounts = Shipment::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $shipmentChart = [
                'labels' => array_values(Shipment::statuses()),
                'data'   => collect(array_keys(Shipment::statuses()))->map(fn ($s) => $shipmentCounts[$s] ?? 0)->all(),
            ];

            return compact(
                'websiteStats', 'ebayStats', 'logisticStats',
                'dedupedCustomers', 'techIssuesOpen', 'negFeedbackOpen', 'activeShipments',
                'truckingCompanyCount', 'ebayStoreCount', 'pendingCallRequests',
                'statusChart', 'shipmentChart'
            );
        });

        // Fresh activity feeds (small, limit 5) — not cached so they stay live.
        $recentLeads   = Lead::with(['handler:id,name', 'product:id,name'])
            ->latest('received_at')->limit(5)->get();
        $recentOffers  = EbayOffer::with(['handler:id,name', 'product:id,name'])
            ->latest()->limit(5)->get();

        return view('crm.dashboard', array_merge($cached, compact('recentLeads', 'recentOffers')));
    }

    private function getWebsitePipeline(): array
    {
        $counts = Lead::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return collect(WebsiteLeadStatus::cases())->map(fn($s) => [
            'status' => $s,
            'count'  => $counts[$s->value] ?? 0,
        ])->toArray();
    }
}
