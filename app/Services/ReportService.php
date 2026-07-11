<?php

namespace App\Services;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\InquirySource;
use App\Enums\LogisticStatus;
use App\Enums\ProductCategory;
use App\Enums\WebsiteLeadStatus;
use App\Models\Card;
use App\Models\Customer;
use App\Models\EbayOffer;
use App\Models\EbayOrder;
use App\Models\Lead;
use App\Models\Logistic;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    // ─── Date Range Resolver ────────────────────────────────────────────────

    public function resolveDateRange(string $range, ?string $from, ?string $to): array
    {
        return match($range) {
            'today'  => [today()->startOfDay(), today()->endOfDay()],
            'week'   => [today()->startOfWeek(), today()->endOfWeek()],
            'month'  => [today()->startOfMonth(), today()->endOfMonth()],
            'year'   => [today()->startOfYear(), today()->endOfYear()],
            'custom' => [
                $from ? Carbon::parse($from)->startOfDay() : today()->subDays(30)->startOfDay(),
                $to   ? Carbon::parse($to)->endOfDay()   : today()->endOfDay(),
            ],
            default  => [today()->startOfMonth(), today()->endOfMonth()],
        };
    }

    // ─── KPIs ────────────────────────────────────────────────────────────────

    public function getKpis(Carbon $from, Carbon $to): array
    {
        // Website revenue: customers with purchase in period
        $websiteRevenue = Customer::whereBetween('last_purchase_date', [$from, $to])
            ->where('has_purchased', true)->sum('lifetime_value') ?? 0;

        // eBay revenue: confirmed orders in period
        $ebayRevenue = EbayOrder::whereBetween('created_at', [$from, $to])
            ->where('payment_status', 'paid')->sum('sale_amount') ?? 0;

        // Leads stats
        $newLeads       = Lead::whereBetween('received_at', [$from, $to])->count();
        $convertedLeads = Lead::whereBetween('received_at', [$from, $to])->where('converted', true)->count();
        $hotLeads       = Lead::where('temperature', 'hot')->whereNotIn('status',
            [WebsiteLeadStatus::Delivered->value, WebsiteLeadStatus::Lost->value])->count();

        // eBay stats
        $ebayOffers       = EbayOffer::whereBetween('received_at', [$from, $to])->count();
        $ebayAuthorized   = EbayOffer::whereBetween('received_at', [$from, $to])
            ->where('authorization_status', 'approved')->count();
        $ebayOrders       = EbayOrder::whereBetween('created_at', [$from, $to])->count();

        // Logistic stats
        $shipmentsActive  = Logistic::whereNotIn('status',
            [LogisticStatus::Delivered->value, LogisticStatus::Problem->value])->count();
        $shipmentsDelivered = Logistic::where('status', LogisticStatus::Delivered->value)
            ->whereBetween('actual_arrival', [$from, $to])->count();
        $logisticCostTotal  = Logistic::whereBetween('created_at', [$from, $to])
            ->whereNotNull('final_shipping_cost')->sum('final_shipping_cost') ?? 0;

        // Kanban
        $tasksCompleted = Card::where('status', 'done')->whereBetween('updated_at', [$from, $to])->count();
        $tasksCreated   = Card::whereBetween('created_at', [$from, $to])->count();

        return compact(
            'websiteRevenue', 'ebayRevenue',
            'newLeads', 'convertedLeads', 'hotLeads',
            'ebayOffers', 'ebayAuthorized', 'ebayOrders',
            'shipmentsActive', 'shipmentsDelivered', 'logisticCostTotal',
            'tasksCompleted', 'tasksCreated'
        );
    }

    // ─── Revenue Over Time (Website + eBay combined) ─────────────────────────

    public function getRevenueOverTime(Carbon $from, Carbon $to): array
    {
        $days   = $from->diffInDays($to);
        $fmt    = $days <= 31 ? '%Y-%m-%d' : '%Y-%m';
        $label  = $days <= 31 ? 'D d M' : 'M Y';

        // Website CRM revenue by period
        $website = Customer::whereBetween('last_purchase_date', [$from, $to])
            ->where('has_purchased', true)
            ->selectRaw("DATE_FORMAT(last_purchase_date, '{$fmt}') as period, SUM(lifetime_value) as total")
            ->groupByRaw("DATE_FORMAT(last_purchase_date, '{$fmt}')")
            ->orderByRaw('MIN(last_purchase_date)')
            ->pluck('total', 'period');

        // eBay revenue by period
        $ebay = EbayOrder::whereBetween('ebay_orders.created_at', [$from, $to])
            ->where('payment_status', 'paid')
            ->selectRaw("DATE_FORMAT(ebay_orders.created_at, '{$fmt}') as period, SUM(sale_amount) as total")
            ->groupByRaw("DATE_FORMAT(ebay_orders.created_at, '{$fmt}')")
            ->orderByRaw('MIN(ebay_orders.created_at)')
            ->pluck('total', 'period');

        $periods = $website->keys()->merge($ebay->keys())->unique()->sort()->values();

        return [
            'labels'   => $periods->toArray(),
            'website'  => $periods->map(fn($p) => round($website[$p] ?? 0, 2))->toArray(),
            'ebay'     => $periods->map(fn($p) => round($ebay[$p] ?? 0, 2))->toArray(),
        ];
    }

    // ─── Sales by Source (Leads + eBay) ──────────────────────────────────────

    public function getSalesBySource(Carbon $from, Carbon $to): array
    {
        // Website leads by source
        $leadSources = Lead::whereBetween('received_at', [$from, $to])
            ->selectRaw('source, COUNT(*) as total')
            ->groupBy('source')->pluck('total', 'source');

        // eBay as its own source
        $ebayTotal = EbayOffer::whereBetween('received_at', [$from, $to])->count();

        $data = [];
        foreach (InquirySource::cases() as $s) {
            $data[$s->label()] = (int)($leadSources[$s->value] ?? 0);
        }
        if ($ebayTotal > 0) {
            $data['🛒 eBay'] = $ebayTotal;
        }
        arsort($data);

        return [
            'labels' => array_keys($data),
            'values' => array_values($data),
        ];
    }

    // ─── Sales by Product Category ────────────────────────────────────────────

    public function getSalesByProduct(Carbon $from, Carbon $to): array
    {
        // From leads
        $leadProducts = Lead::whereBetween('leads.received_at', [$from, $to])
            ->whereNotNull('leads.product_id')
            ->join('products', 'products.id', '=', 'leads.product_id')
            ->selectRaw('products.category, COUNT(leads.id) as total')
            ->groupBy('products.category')
            ->pluck('total', 'category');

        // From eBay orders
        $ebayProducts = EbayOrder::whereBetween('ebay_orders.created_at', [$from, $to])
            ->whereNotNull('ebay_orders.product_id')
            ->join('products', 'products.id', '=', 'ebay_orders.product_id')
            ->selectRaw('products.category, COUNT(ebay_orders.id) as total')
            ->groupBy('products.category')
            ->pluck('total', 'category');

        // eBay revenue by product
        $ebayRevenue = EbayOrder::whereBetween('ebay_orders.created_at', [$from, $to])
            ->whereNotNull('ebay_orders.product_id')
            ->join('products', 'products.id', '=', 'ebay_orders.product_id')
            ->selectRaw('products.category, SUM(sale_amount) as revenue')
            ->groupBy('products.category')
            ->pluck('revenue', 'category');

        $results = [];
        foreach (ProductCategory::cases() as $cat) {
            $total = ($leadProducts[$cat->value] ?? 0) + ($ebayProducts[$cat->value] ?? 0);
            if ($total > 0) {
                $results[$cat->value] = [
                    'label'   => $cat->icon() . ' ' . $cat->label(),
                    'count'   => $total,
                    'revenue' => round($ebayRevenue[$cat->value] ?? 0, 2),
                    'color'   => $cat->color(),
                ];
            }
        }
        uasort($results, fn($a, $b) => $b['count'] - $a['count']);

        return [
            'labels'  => array_column($results, 'label'),
            'counts'  => array_column($results, 'count'),
            'revenue' => array_column($results, 'revenue'),
            'colors'  => array_column($results, 'color'),
        ];
    }

    // ─── Lead Conversion Funnel ───────────────────────────────────────────────

    public function getLeadConversionFunnel(Carbon $from, Carbon $to): array
    {
        $counts = Lead::whereBetween('received_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->get()
            ->mapWithKeys(fn($r) => [
                $r->status instanceof \BackedEnum ? $r->status->value : $r->status => $r->total
            ]);

        $stages = WebsiteLeadStatus::cases();
        $total  = $counts->sum();

        return [
            'labels'  => array_map(fn($s) => $s->label(), $stages),
            'values'  => array_map(fn($s) => (int)($counts[$s->value] ?? 0), $stages),
            'colors'  => array_map(fn($s) => $s->color(), $stages),
            'total'   => $total,
        ];
    }

    // ─── Staff CRM Performance (leads handled + converted) ───────────────────

    public function getStaffCrmPerformance(Carbon $from, Carbon $to): array
    {
        $handled = Lead::whereBetween('leads.received_at', [$from, $to])
            ->join('users', 'users.id', '=', 'leads.handled_by')
            ->selectRaw('users.name, COUNT(leads.id) as handled')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('handled')
            ->limit(10)
            ->get();

        $converted = Lead::whereBetween('leads.received_at', [$from, $to])
            ->where('leads.converted', true)
            ->join('users', 'users.id', '=', 'leads.handled_by')
            ->selectRaw('users.name, COUNT(leads.id) as converted')
            ->groupBy('users.id', 'users.name')
            ->pluck('converted', 'name');

        $ebayHandled = EbayOffer::whereBetween('ebay_offers.received_at', [$from, $to])
            ->join('users', 'users.id', '=', 'ebay_offers.handled_by')
            ->selectRaw('users.name, COUNT(ebay_offers.id) as ebay_offers')
            ->groupBy('users.id', 'users.name')
            ->pluck('ebay_offers', 'name');

        return [
            'labels'       => $handled->pluck('name')->toArray(),
            'leadsHandled' => $handled->pluck('handled')->toArray(),
            'converted'    => $handled->map(fn($r) => (int)($converted[$r->name] ?? 0))->toArray(),
            'ebayHandled'  => $handled->map(fn($r) => (int)($ebayHandled[$r->name] ?? 0))->toArray(),
        ];
    }

    // ─── Kanban: Staff task performance ──────────────────────────────────────

    public function getStaffPerformance(Carbon $from, Carbon $to): array
    {
        $assigned = DB::table('card_assignees')
            ->join('cards', 'cards.id', '=', 'card_assignees.card_id')
            ->join('users', 'users.id', '=', 'card_assignees.user_id')
            ->whereBetween('cards.created_at', [$from, $to])
            ->selectRaw('users.name, COUNT(DISTINCT cards.id) as assigned')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('assigned')->limit(10)->get();

        $completed = DB::table('card_assignees')
            ->join('cards', 'cards.id', '=', 'card_assignees.card_id')
            ->join('users', 'users.id', '=', 'card_assignees.user_id')
            ->where('cards.status', 'done')
            ->whereBetween('cards.updated_at', [$from, $to])
            ->selectRaw('users.name, COUNT(DISTINCT cards.id) as completed')
            ->groupBy('users.id', 'users.name')
            ->pluck('completed', 'name');

        return [
            'labels'    => $assigned->pluck('name')->toArray(),
            'assigned'  => $assigned->pluck('assigned')->toArray(),
            'completed' => $assigned->map(fn($r) => (int)($completed[$r->name] ?? 0))->toArray(),
        ];
    }

    // ─── eBay Authorization Stats ─────────────────────────────────────────────

    public function getEbayAuthStats(Carbon $from, Carbon $to): array
    {
        $rows = EbayOffer::whereBetween('received_at', [$from, $to])
            ->selectRaw('authorization_status, COUNT(*) as total')
            ->groupBy('authorization_status')->get();

        $map = $rows->mapWithKeys(fn($r) => [
            $r->authorization_status instanceof \BackedEnum ? $r->authorization_status->value : $r->authorization_status => $r->total
        ]);
        $statuses = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'negotiation' => 'Negotiation'];
        $colors   = ['pending' => '#f59e0b', 'approved' => '#10b981', 'rejected' => '#ef4444', 'negotiation' => '#6366f1'];

        return [
            'labels' => array_values($statuses),
            'values' => array_map(fn($k) => (int)($map[$k] ?? 0), array_keys($statuses)),
            'colors' => array_values($colors),
        ];
    }

    // ─── Logistics Cost Report ────────────────────────────────────────────────

    public function getLogisticsCostReport(Carbon $from, Carbon $to): array
    {
        $rows = Logistic::whereBetween('created_at', [$from, $to])
            ->whereNotNull('shipping_budget')
            ->orWhereNotNull('final_shipping_cost')
            ->with('product:id,name,category')
            ->get(['id', 'order_id', 'shipping_budget', 'final_shipping_cost', 'status', 'product_id']);

        // Summary by status
        $byStatus = Logistic::whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as count, SUM(final_shipping_cost) as cost, SUM(shipping_budget) as budget')
            ->groupBy('status')->get();

        $totalBudget = $byStatus->sum('budget');
        $totalActual = $byStatus->sum('cost');

        // Budget vs actual per shipment (last 10)
        $shipments = $rows->sortByDesc('created_at')->take(10);

        return [
            'summary' => [
                'total_budget' => round($totalBudget, 2),
                'total_actual' => round($totalActual, 2),
                'savings'      => round($totalBudget - $totalActual, 2),
                'count'        => $rows->count(),
            ],
            'labels'  => $shipments->map(fn($s) => $s->order_id ?? '#'.$s->id)->values()->toArray(),
            'budget'  => $shipments->map(fn($s) => round($s->shipping_budget ?? 0, 2))->values()->toArray(),
            'actual'  => $shipments->map(fn($s) => round($s->final_shipping_cost ?? 0, 2))->values()->toArray(),
        ];
    }

    // ─── Logistic Delivery Status Breakdown ──────────────────────────────────

    public function getLogisticsByStatus(Carbon $from, Carbon $to): array
    {
        $rows = Logistic::whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->get();
        $map = $rows->mapWithKeys(fn($r) => [
            $r->status instanceof \BackedEnum ? $r->status->value : $r->status => $r->total
        ]);

        return [
            'labels' => array_map(fn($s) => $s->icon().' '.$s->label(), LogisticStatus::cases()),
            'values' => array_map(fn($s) => (int)($map[$s->value] ?? 0), LogisticStatus::cases()),
            'colors' => array_map(fn($s) => $s->color(), LogisticStatus::cases()),
        ];
    }

    // ─── Existing helpers (kept for Kanban reports) ───────────────────────────

    public function getTasksByLabel(Carbon $from, Carbon $to): array
    {
        $rows = Card::whereBetween('created_at', [$from, $to])
            ->selectRaw('label, COUNT(*) as total')
            ->groupBy('label')->orderByDesc('total')->get();
        return [
            'labels' => $rows->pluck('label')->toArray(),
            'values' => $rows->pluck('total')->toArray(),
        ];
    }

    public function getTasksByStatus(Carbon $from, Carbon $to): array
    {
        $rows = Card::whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->orderByDesc('total')->get();
        $statusLabels = [
            'todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'In Review',
            'approved' => 'Approved', 'rejected' => 'Rejected', 'done' => 'Done',
        ];
        return [
            'labels' => $rows->map(function($r) use ($statusLabels) {
                $statusVal = $r->status instanceof \BackedEnum ? $r->status->value : $r->status;
                return $statusLabels[$statusVal] ?? $statusVal ?? 'Unknown';
            })->toArray(),
            'values' => $rows->pluck('total')->toArray(),
            'raw'    => $rows->map(fn($r) => $r->status instanceof \BackedEnum ? $r->status->value : $r->status)->toArray(),
        ];
    }

    public function getCustomersBySource(Carbon $from, Carbon $to): array
    {
        $rows = Customer::whereBetween('created_at', [$from, $to])
            ->selectRaw('source, COUNT(*) as total')
            ->groupBy('source')->orderByDesc('total')->get();
        return [
            'labels' => $rows->map(fn($r) => CustomerSource::tryFrom($r->source ?? '')?->label() ?? $r->source)->toArray(),
            'values' => $rows->pluck('total')->toArray(),
        ];
    }

    public function getCustomersByStatus(Carbon $from, Carbon $to): array
    {
        $rows = Customer::whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->get();
        return [
            'labels' => $rows->map(fn($r) => ($r->status instanceof CustomerStatus ? $r->status : CustomerStatus::tryFrom($r->status))?->label() ?? ($r->status instanceof CustomerStatus ? $r->status->value : $r->status))->toArray(),
            'values' => $rows->pluck('total')->toArray(),
            'colors' => $rows->map(fn($r) => ($r->status instanceof CustomerStatus ? $r->status : CustomerStatus::tryFrom($r->status))?->color() ?? '#94a3b8')->toArray(),
        ];
    }

    public function getSalesByStaff(Carbon $from, Carbon $to): array
    {
        $rows = Customer::whereBetween('last_purchase_date', [$from, $to])
            ->where('has_purchased', true)
            ->join('users', 'users.id', '=', 'customers.assigned_to')
            ->selectRaw('users.name, COUNT(customers.id) as customers, SUM(customers.lifetime_value) as revenue')
            ->groupBy('users.id', 'users.name')->orderByDesc('revenue')->limit(10)->get();
        return [
            'labels'    => $rows->pluck('name')->toArray(),
            'customers' => $rows->pluck('customers')->toArray(),
            'revenue'   => $rows->pluck('revenue')->map(fn($v) => round($v, 2))->toArray(),
        ];
    }

    public function getSalesByProductType(Carbon $from, Carbon $to): array
    {
        $customers = Customer::whereBetween('last_purchase_date', [$from, $to])
            ->where('has_purchased', true)->whereNotNull('product_interests')
            ->get(['product_interests']);
        $counts = [];
        foreach ($customers as $c) {
            foreach ($c->product_interests as $interest) {
                $counts[$interest] = ($counts[$interest] ?? 0) + 1;
            }
        }
        arsort($counts);
        return ['labels' => array_keys($counts), 'values' => array_values($counts)];
    }
}
