@extends('layouts.app')
@section('title', 'CRM Dashboard')
@section('page_title', 'CRM Dashboard')

@section('content')
<div class="animate-fade-in space-y-6">

  {{-- ── Cross-team KPI tiles ────────────────────────────────────────────── --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="card p-3 text-center">
      <div class="text-2xl font-bold text-indigo-700">{{ $dedupedCustomers }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Total Customers (deduped)</div>
    </div>
    <div class="card p-3 text-center">
      <div class="text-2xl font-bold text-red-600">{{ $techIssuesOpen }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Open Technical Issues</div>
    </div>
    <div class="card p-3 text-center">
      <div class="text-2xl font-bold text-amber-600">{{ $negFeedbackOpen }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Open Negative Feedback</div>
    </div>
    <div class="card p-3 text-center">
      <div class="text-2xl font-bold text-blue-600">{{ $activeShipments }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Active Shipments</div>
    </div>
    <div class="card p-3 text-center">
      <div class="text-2xl font-bold text-slate-700">{{ $truckingCompanyCount }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Trucking Companies</div>
    </div>
    <div class="card p-3 text-center">
      <div class="text-2xl font-bold text-slate-700">{{ $ebayStoreCount }}</div>
      <div class="text-xs text-slate-500 mt-0.5">eBay Stores</div>
    </div>
    <div class="card p-3 text-center">
      <div class="text-2xl font-bold text-emerald-600">{{ $pendingCallRequests }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Pending Call Requests</div>
    </div>
  </div>

  {{-- ── 3-Panel Stats Row ────────────────────────────────────────────────── --}}
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- Website CRM Panel --}}
    <div class="card border-t-4" style="border-top-color:#6366f1">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="font-display font-bold text-slate-800">🌐 Website CRM</h3>
          <p class="text-xs text-slate-400">Lead management & nurturing</p>
        </div>
        <a href="{{ route('crm.website.create') }}" class="btn btn-primary text-xs py-1.5 px-3" id="btn-new-lead">+ New Lead</a>
      </div>
      <div class="grid grid-cols-2 gap-3 mb-4">
        <div class="bg-indigo-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-indigo-700">{{ $websiteStats['new_today'] }}</div>
          <div class="text-xs text-slate-500 mt-0.5">New Today</div>
        </div>
        <div class="bg-red-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-red-600">{{ $websiteStats['hot_leads'] }}</div>
          <div class="text-xs text-slate-500 mt-0.5">🔥 Hot Leads</div>
        </div>
        <div class="bg-amber-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-amber-600">{{ $websiteStats['follow_up_due'] }}</div>
          <div class="text-xs text-slate-500 mt-0.5">Follow-Ups Due</div>
        </div>
        <div class="bg-emerald-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-emerald-600">{{ $websiteStats['successful'] }}</div>
          <div class="text-xs text-slate-500 mt-0.5">Successful</div>
        </div>
      </div>
      {{-- Mini pipeline --}}
      <div class="space-y-1.5">
        @foreach($websiteStats['pipeline'] as $p)
        <div class="flex items-center gap-2">
          <div class="w-24 text-xs text-slate-500 truncate">{{ $p['status']->label() }}</div>
          <div class="flex-1 bg-slate-100 rounded-full h-1.5">
            @php $pct = $p['count'] > 0 ? min(100, ($p['count'] / max(1, collect($websiteStats['pipeline'])->sum('count'))) * 100) : 0; @endphp
            <div class="h-1.5 rounded-full transition-all" style="width:{{ $pct }}%;background:{{ $p['status']->color() }}"></div>
          </div>
          <div class="text-xs font-semibold text-slate-700 w-6 text-right">{{ $p['count'] }}</div>
        </div>
        @endforeach
      </div>
      <a href="{{ route('crm.website.index') }}" class="mt-4 text-xs text-indigo-600 hover:text-indigo-800 font-semibold flex items-center gap-1">
        View All Leads →
      </a>
    </div>

    {{-- eBay CRM Panel --}}
    <div class="card border-t-4" style="border-top-color:#f59e0b">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="font-display font-bold text-slate-800">🛒 eBay CRM</h3>
          <p class="text-xs text-slate-400">Offers & authorization</p>
        </div>
        <a href="{{ route('crm.ebay.create') }}" class="btn btn-primary text-xs py-1.5 px-3 bg-amber-500 hover:bg-amber-600" id="btn-new-offer">+ Log Offer</a>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="bg-slate-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-slate-700">{{ $ebayStats['new_inquiries'] }}</div>
          <div class="text-xs text-slate-500 mt-0.5">New Inquiries</div>
        </div>
        <div class="bg-amber-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-amber-600">{{ $ebayStats['waiting_auth'] }}</div>
          <div class="text-xs text-slate-500 mt-0.5">⏳ Awaiting Auth</div>
        </div>
        <div class="bg-emerald-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-emerald-600">{{ $ebayStats['converted'] }}</div>
          <div class="text-xs text-slate-500 mt-0.5">Converted</div>
        </div>
        <div class="bg-green-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-green-600">{{ $ebayStats['orders_confirmed'] }}</div>
          <div class="text-xs text-slate-500 mt-0.5">Orders Confirmed</div>
        </div>
      </div>
      @if($ebayStats['waiting_auth'] > 0)
      <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-center gap-2">
        <span class="text-amber-600 text-lg">⚠️</span>
        <div>
          <p class="text-xs font-semibold text-amber-800">{{ $ebayStats['waiting_auth'] }} offer(s) need authorization</p>
          <a href="{{ route('crm.ebay.index', ['auth_status' => 'pending']) }}" class="text-xs text-amber-700 underline">Review now →</a>
        </div>
      </div>
      @endif
      <a href="{{ route('crm.ebay.index') }}" class="mt-4 text-xs text-amber-600 hover:text-amber-800 font-semibold flex items-center gap-1">
        View All eBay Offers →
      </a>
    </div>

    {{-- Logistic CRM Panel --}}
    <div class="card border-t-4" style="border-top-color:#10b981">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="font-display font-bold text-slate-800">🚛 Logistic CRM</h3>
          <p class="text-xs text-slate-400">Shipment tracking</p>
        </div>
        <a href="{{ route('crm.logistics.create') }}" class="btn btn-primary text-xs py-1.5 px-3 bg-emerald-500 hover:bg-emerald-600" id="btn-new-shipment">+ New Shipment</a>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="bg-slate-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-slate-700">{{ $logisticStats['waiting_verify'] }}</div>
          <div class="text-xs text-slate-500 mt-0.5">Awaiting Verify</div>
        </div>
        <div class="bg-amber-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-amber-600">{{ $logisticStats['truck_searching'] }}</div>
          <div class="text-xs text-slate-500 mt-0.5">🔍 Truck Needed</div>
        </div>
        <div class="bg-blue-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-blue-600">{{ $logisticStats['in_transit'] }}</div>
          <div class="text-xs text-slate-500 mt-0.5">🛣️ In Transit</div>
        </div>
        <div class="bg-emerald-50 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold text-emerald-600">{{ $logisticStats['delivered_today'] }}</div>
          <div class="text-xs text-slate-500 mt-0.5">✅ Delivered Today</div>
        </div>
      </div>
      <a href="{{ route('crm.logistics.index') }}" class="mt-4 text-xs text-emerald-600 hover:text-emerald-800 font-semibold flex items-center gap-1">
        View All Shipments →
      </a>
    </div>
  </div>

  {{-- ── Recent Activity Row ────────────────────────────────────────────────── --}}
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- Recent Leads --}}
    <div class="card p-0 overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <h4 class="font-semibold text-slate-700 text-sm">Recent Leads</h4>
        <a href="{{ route('crm.website.index') }}" class="text-xs text-indigo-600 hover:underline">View all</a>
      </div>
      <div class="divide-y divide-slate-50">
        @forelse($recentLeads as $lead)
        <a href="{{ route('crm.website.show', $lead) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition-colors">
          <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
               style="background:{{ $lead->status?->color() ?? '#94a3b8' }}">
            {{ strtoupper(substr($lead->client_name, 0, 1)) }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-slate-800 truncate">{{ $lead->client_name }}</p>
            <p class="text-xs text-slate-400">{{ $lead->source?->icon() }} {{ $lead->source?->label() }} · {{ $lead->received_at?->diffForHumans() }}</p>
          </div>
          <span class="text-xs font-semibold shrink-0" style="color:{{ $lead->temperature?->color() ?? '#94a3b8' }}">
            {{ $lead->temperature?->icon() }}
          </span>
        </a>
        @empty
        <p class="text-center text-slate-400 text-sm py-6">No leads yet.</p>
        @endforelse
      </div>
    </div>

    {{-- Recent eBay Offers --}}
    <div class="card p-0 overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <h4 class="font-semibold text-slate-700 text-sm">Recent eBay Offers</h4>
        <a href="{{ route('crm.ebay.index') }}" class="text-xs text-amber-600 hover:underline">View all</a>
      </div>
      <div class="divide-y divide-slate-50">
        @forelse($recentOffers as $offer)
        <a href="{{ route('crm.ebay.show', $offer) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition-colors">
          <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center text-amber-700 text-xs font-bold shrink-0">
            {{ strtoupper(substr($offer->client_name ?? $offer->ebay_username ?? '?', 0, 1)) }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-slate-800 truncate">{{ $offer->client_name ?? $offer->ebay_username }}</p>
            <p class="text-xs text-slate-400">{{ $offer->offer_amount ? '$'.number_format($offer->offer_amount) : 'No amount' }} · {{ $offer->received_at?->diffForHumans() }}</p>
          </div>
          <span class="badge badge-amber text-[10px]">{{ $offer->authorization_status?->label() }}</span>
        </a>
        @empty
        <p class="text-center text-slate-400 text-sm py-6">No offers yet.</p>
        @endforelse
      </div>
    </div>

    {{-- Recent Logistics --}}
    <div class="card p-0 overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <h4 class="font-semibold text-slate-700 text-sm">Active Shipments</h4>
        <a href="{{ route('crm.logistics.index') }}" class="text-xs text-emerald-600 hover:underline">View all</a>
      </div>
      <div class="divide-y divide-slate-50">
        @forelse($recentLogistic as $ship)
        <a href="{{ route('crm.logistics.show', $ship) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition-colors">
          <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xl shrink-0"
               style="background:{{ $ship->status?->color() ?? '#94a3b8' }}">
            <span class="text-sm">{{ $ship->status?->icon() }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-slate-800 truncate">{{ $ship->customer?->name }}</p>
            <p class="text-xs text-slate-400">{{ $ship->order_id ?? '#'.$ship->id }} · {{ $ship->status?->label() }}</p>
          </div>
          @if($ship->estimated_arrival)
          <span class="text-xs text-slate-400 shrink-0">{{ $ship->estimated_arrival->format('d M') }}</span>
          @endif
        </a>
        @empty
        <p class="text-center text-slate-400 text-sm py-6">No shipments yet.</p>
        @endforelse
      </div>
    </div>
  </div>

  {{-- ── Charts ──────────────────────────────────────────────────────────── --}}
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <div class="card p-4">
      <h4 class="font-semibold text-slate-700 text-sm mb-3">CRM Website — Status Breakdown</h4>
      <canvas id="crmStatusChart" height="180"></canvas>
    </div>
    <div class="card p-4">
      <h4 class="font-semibold text-slate-700 text-sm mb-3">Shipment Status Breakdown</h4>
      <canvas id="crmShipmentChart" height="180"></canvas>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
function initCrmDashboardCharts() {
    if (!window.Chart) return;

    const statusChartEl = document.getElementById('crmStatusChart');
    if (statusChartEl) {
        Chart.getChart(statusChartEl)?.destroy();
        new Chart(statusChartEl, {
            type: 'bar',
            data: {
                labels: @json($statusChart['labels']),
                datasets: [{ label: 'Leads', data: @json($statusChart['data']), backgroundColor: '#6366f1' }],
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { x: { ticks: { autoSkip: false, maxRotation: 60, minRotation: 30 } } },
            },
        });
    }

    const shipmentChartEl = document.getElementById('crmShipmentChart');
    if (shipmentChartEl) {
        Chart.getChart(shipmentChartEl)?.destroy();
        new Chart(shipmentChartEl, {
            type: 'doughnut',
            data: {
                labels: @json($shipmentChart['labels']),
                datasets: [{ data: @json($shipmentChart['data']), backgroundColor: ['#94a3b8', '#3b82f6', '#22c55e', '#ef4444'] }],
            },
        });
    }
}

document.addEventListener('DOMContentLoaded', initCrmDashboardCharts);
document.addEventListener('turbo:load', initCrmDashboardCharts);
</script>
@endpush
