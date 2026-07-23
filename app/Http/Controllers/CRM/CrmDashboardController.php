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

        $today = today();

        // ── Website CRM Stats ────────────────────────────────────────────────
        $websiteStats = [
            'new_today'      => Lead::whereDate('received_at', $today)->count(),
            'hot_leads'      => Lead::where('temperature', 'hot')->active()->count(),
            'follow_up_due'  => Lead::followUpDue()->count(),
            'successful'     => Lead::where('status', WebsiteLeadStatus::Successful->value)->count(),
            'pipeline'       => $this->getWebsitePipeline(),
        ];

        // ── eBay CRM Stats ───────────────────────────────────────────────────
        $ebayStats = [
            'new_inquiries'   => EbayOffer::where('status', EbayLeadStatus::Inquiry->value)->count(),
            'waiting_auth'    => EbayOffer::waitingAuthorization()->count(),
            'converted'       => EbayOffer::where('status', EbayLeadStatus::ConvertedLead->value)->count(),
            'orders_confirmed'=> EbayOffer::where('status', EbayLeadStatus::OrderConfirmed->value)->count(),
        ];

        // ── Logistic CRM Stats ───────────────────────────────────────────────
        $logisticStats = [
            'waiting_verify' => Logistic::where('status', LogisticStatus::OrderConfirmed->value)->count(),
            'truck_searching'=> Logistic::where('status', LogisticStatus::TruckSearching->value)->count(),
            'in_transit'     => Logistic::inTransit()->count(),
            'delivered_today'=> Logistic::where('status', LogisticStatus::Delivered->value)
                                    ->whereDate('actual_arrival', $today)->count(),
        ];

        // ── Recent activity feeds ────────────────────────────────────────────
        $recentLeads   = Lead::with(['handler', 'product'])
            ->latest('received_at')->limit(5)->get();
        $recentOffers  = EbayOffer::with(['handler', 'product'])
            ->latest()->limit(5)->get();

        // ── Demo-parity KPI tiles ─────────────────────────────────────────────
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

        // ── Chart datasets ───────────────────────────────────────────────────
        $statusChart = [
            'labels' => collect(WebsiteLeadStatus::cases())->map->label()->all(),
            'data'   => collect($this->getWebsitePipeline())->pluck('count')->all(),
        ];
        $shipmentCounts = Shipment::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $shipmentChart = [
            'labels' => array_values(Shipment::statuses()),
            'data'   => collect(array_keys(Shipment::statuses()))->map(fn ($s) => $shipmentCounts[$s] ?? 0)->all(),
        ];

        return view('crm.dashboard', compact(
            'websiteStats', 'ebayStats', 'logisticStats',
            'recentLeads', 'recentOffers',
            'dedupedCustomers', 'techIssuesOpen', 'negFeedbackOpen', 'activeShipments',
            'truckingCompanyCount', 'ebayStoreCount', 'pendingCallRequests',
            'statusChart', 'shipmentChart'
        ));
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
