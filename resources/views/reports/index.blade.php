@extends('layouts.app')
@section('title', 'Reports & Analytics')
@section('page_title', 'Reports & Analytics')

@section('content')
<div class="animate-fade-in space-y-6" x-data>

  {{-- ── Date Range Filter ────────────────────────────────────────────────── --}}
  <div class="card p-4">
    <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-3">
      <div class="flex gap-1">
        @foreach(['today'=>'Today','week'=>'This Week','month'=>'This Month','year'=>'This Year','custom'=>'Custom'] as $val=>$lbl)
        <a href="{{ route('reports.index', ['range'=>$val]) }}"
           class="btn text-xs py-1.5 px-3 {{ $range===$val ? 'btn-primary' : 'btn-secondary' }}">{{ $lbl }}</a>
        @endforeach
      </div>
      @if($range==='custom')
      <div class="flex gap-2 items-center">
        <input type="date" name="from" value="{{ request('from', $dateFrom->format('Y-m-d')) }}" class="form-input py-1.5 text-sm">
        <span class="text-slate-400 text-sm">→</span>
        <input type="date" name="to"   value="{{ request('to',   $dateTo->format('Y-m-d')) }}"   class="form-input py-1.5 text-sm">
        <input type="hidden" name="range" value="custom">
        <button type="submit" class="btn btn-primary py-1.5 text-sm">Apply</button>
      </div>
      @endif
      <span class="text-xs text-slate-400 ml-auto">
        {{ $dateFrom->format('d M Y') }} — {{ $dateTo->format('d M Y') }}
      </span>
    </form>
  </div>

  {{-- ── KPI Cards ────────────────────────────────────────────────────────── --}}
  @unless(auth()->user()->hasRole('admin-digital'))
  <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4">
    @php
    $kpiCards = [
      ['label'=>'New Leads',       'value'=>number_format($kpis['newLeads']),         'icon'=>'🌐','color'=>'#6366f1','sub'=>$kpis['convertedLeads'].' converted'],
      ['label'=>'eBay Offers',     'value'=>number_format($kpis['ebayOffers']),        'icon'=>'🛒','color'=>'#f59e0b','sub'=>$kpis['ebayAuthorized'].' authorized'],
      ['label'=>'eBay Orders',     'value'=>number_format($kpis['ebayOrders']),        'icon'=>'📦','color'=>'#10b981','sub'=>'Confirmed orders'],
      ['label'=>'Logistics',       'value'=>number_format($kpis['shipmentsActive']),   'icon'=>'🚛','color'=>'#0ea5e9','sub'=>$kpis['shipmentsDelivered'].' delivered'],
      ['label'=>'Shipping Spend',  'value'=>'$'.number_format($kpis['logisticCostTotal']),  'icon'=>'💰','color'=>'#ec4899','sub'=>'Total this period'],
    ];
    @endphp
    @foreach($kpiCards as $k)
    <div class="card border-l-4 !py-4" style="border-left-color:{{ $k['color'] }}">
      <div class="flex items-center justify-between mb-2">
        <span class="text-2xl">{{ $k['icon'] }}</span>
        <span class="text-2xl font-extrabold font-display" style="color:{{ $k['color'] }}">{{ $k['value'] }}</span>
      </div>
      <p class="text-xs font-semibold text-slate-700">{{ $k['label'] }}</p>
      <p class="text-xs text-slate-400 mt-0.5">{{ $k['sub'] }}</p>
    </div>
    @endforeach
  </div>
  @endunless

  {{-- ── Row 1: Revenue Over Time + Sales by Source ──────────────────────── --}}
  @unless(auth()->user()->hasRole('admin-digital'))
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
    <div class="card">
      <h4 class="font-semibold text-slate-700 mb-4">💹 Revenue Over Time</h4>
      <canvas id="chartRevenue" height="200"></canvas>
    </div>
    <div class="card">
      <h4 class="font-semibold text-slate-700 mb-4">📡 Inquiries by Source</h4>
      <canvas id="chartSource" height="200"></canvas>
    </div>
  </div>
  @endunless

  {{-- ── Row 2: Lead Funnel + Product Mix ────────────────────────────────── --}}
  @unless(auth()->user()->hasRole('admin-digital'))
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
    <div class="card">
      <h4 class="font-semibold text-slate-700 mb-1">🔽 Lead Conversion Funnel</h4>
      <p class="text-xs text-slate-400 mb-4">{{ $leadFunnel['total'] }} total leads this period</p>
      <div class="space-y-2">
        @php $max = max(1, max($leadFunnel['values'] ?: [1])); @endphp
        @foreach($leadFunnel['labels'] as $i => $label)
        @php $val = $leadFunnel['values'][$i]; $pct = round($val/$max*100); $color = $leadFunnel['colors'][$i]; @endphp
        <div>
          <div class="flex justify-between text-xs mb-1">
            <span class="font-medium text-slate-700">{{ $label }}</span>
            <span class="font-bold" style="color:{{ $color }}">{{ $val }}</span>
          </div>
          <div class="h-5 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all duration-700" style="width:{{ $pct }}%;background:{{ $color }}"></div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
    <div class="card">
      <h4 class="font-semibold text-slate-700 mb-4">🏗️ Sales by Product Category</h4>
      <canvas id="chartProduct" height="200"></canvas>
    </div>
  </div>
  @endunless

  {{-- ── Row 3: Staff CRM Performance ───────────────────────────────────── --}}
  @unless(auth()->user()->hasRole('admin-digital'))
  <div class="card">
    <h4 class="font-semibold text-slate-700 mb-4">👥 Staff CRM Performance</h4>
    @if(count($staffCrmPerf['labels']))
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-xs font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-100">
            <th class="py-2 text-left px-3">Staff</th>
            <th class="py-2 text-center px-3">Leads Handled</th>
            <th class="py-2 text-center px-3">Converted</th>
            <th class="py-2 text-center px-3">eBay Offers</th>
            <th class="py-2 text-left px-3">Conversion Rate</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @foreach($staffCrmPerf['labels'] as $i => $name)
          @php
            $handled   = $staffCrmPerf['leadsHandled'][$i] ?? 0;
            $converted = $staffCrmPerf['converted'][$i] ?? 0;
            $ebay      = $staffCrmPerf['ebayHandled'][$i] ?? 0;
            $rate      = $handled > 0 ? round($converted/$handled*100) : 0;
          @endphp
          <tr class="hover:bg-slate-50">
            <td class="py-2.5 px-3 font-semibold text-slate-800">{{ $name }}</td>
            <td class="py-2.5 px-3 text-center">{{ $handled }}</td>
            <td class="py-2.5 px-3 text-center font-semibold text-emerald-600">{{ $converted }}</td>
            <td class="py-2.5 px-3 text-center text-amber-600">{{ $ebay }}</td>
            <td class="py-2.5 px-3">
              <div class="flex items-center gap-2">
                <div class="flex-1 bg-slate-100 rounded-full h-2">
                  <div class="h-2 rounded-full bg-indigo-500" style="width:{{ $rate }}%"></div>
                </div>
                <span class="text-xs font-semibold text-slate-600 w-10">{{ $rate }}%</span>
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @else
    <p class="text-slate-400 text-sm text-center py-8">No CRM activity this period.</p>
    @endif
  </div>
  @endunless

  {{-- ── Row 4: eBay Auth + Logistics Status ─────────────────────────────── --}}
  @unless(auth()->user()->hasRole('admin-digital'))
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
    <div class="card">
      <h4 class="font-semibold text-slate-700 mb-4">🔐 eBay Authorization Results</h4>
      <canvas id="chartEbayAuth" height="200"></canvas>
    </div>
    <div class="card">
      <h4 class="font-semibold text-slate-700 mb-4">🚛 Logistics by Status</h4>
      <canvas id="chartLogisticStatus" height="200"></canvas>
    </div>
  </div>
  @endunless

  {{-- ── Row 5: Logistics Cost ────────────────────────────────────────────── --}}
  @unless(auth()->user()->hasRole('admin-digital'))
  <div class="card">
    <div class="flex items-start justify-between flex-wrap gap-3 mb-5">
      <div>
        <h4 class="font-semibold text-slate-700">💰 Logistics Cost — Budget vs Actual</h4>
        <p class="text-xs text-slate-400 mt-0.5">{{ $logisticsCost['summary']['count'] }} shipments this period</p>
      </div>
      <div class="flex gap-4">
        <div class="text-center">
          <p class="text-xs text-slate-400">Total Budget</p>
          <p class="font-bold text-slate-700">${{ number_format($logisticsCost['summary']['total_budget']) }}</p>
        </div>
        <div class="text-center">
          <p class="text-xs text-slate-400">Total Actual</p>
          <p class="font-bold text-emerald-600">${{ number_format($logisticsCost['summary']['total_actual']) }}</p>
        </div>
        @php $savings = $logisticsCost['summary']['savings']; @endphp
        <div class="text-center">
          <p class="text-xs text-slate-400">{{ $savings >= 0 ? 'Saved' : 'Over' }}</p>
          <p class="font-bold {{ $savings >= 0 ? 'text-emerald-600' : 'text-red-500' }}">${{ number_format(abs($savings)) }}</p>
        </div>
      </div>
    </div>
    <canvas id="chartLogisticsCost" height="100"></canvas>
  </div>
  @endunless

  {{-- ── Row 6: Kanban Tasks + Staff Tasks ───────────────────────────────── --}}
  @unless(auth()->user()->hasRole('admin-crm'))
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
    <div class="card">
      <h4 class="font-semibold text-slate-700 mb-4">📋 Tasks by Status</h4>
      <canvas id="chartTaskStatus" height="200"></canvas>
    </div>
    <div class="card">
      <h4 class="font-semibold text-slate-700 mb-4">🏆 Staff Task Performance</h4>
      <canvas id="chartStaffTasks" height="200"></canvas>
    </div>
  </div>
  @endunless

</div>
@endsection

@push('scripts')
<script>
async function initAdminReportCharts() {
if (!window.Chart && window.loadChart) {
  await window.loadChart();
}
if (!window.Chart) return;

Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = '#64748b';

const palette = ['#6366f1','#f59e0b','#10b981','#0ea5e9','#ec4899','#8b5cf6','#f97316','#14b8a6'];

function mkChart(id, type, data, opts = {}) {
  const el = document.getElementById(id);
  if (!el) return;
  return new Chart(el, { type, data, options: { responsive: true, plugins: { legend: { position: 'bottom' } }, ...opts } });
}

// Revenue Over Time (dual line)
mkChart('chartRevenue', 'line', {
  labels:   @json($revenueOverTime['labels']),
  datasets: [
    { label: 'Website',  data: @json($revenueOverTime['website']),  borderColor: '#6366f1', backgroundColor: '#6366f118', tension: 0.4, fill: true },
    { label: 'eBay',     data: @json($revenueOverTime['ebay']),     borderColor: '#f59e0b', backgroundColor: '#f59e0b18', tension: 0.4, fill: true },
  ],
}, { scales: { y: { beginAtZero: true, ticks: { callback: v => '$'+v.toLocaleString() } } }, plugins: { legend: { position: 'top' } } });

// Sales by Source (doughnut)
mkChart('chartSource', 'doughnut', {
  labels:   @json($salesBySource['labels']),
  datasets: [{ data: @json($salesBySource['values']), backgroundColor: palette }],
}, { plugins: { legend: { position: 'right' } } });

// Sales by Product (horizontal bar)
mkChart('chartProduct', 'bar', {
  labels:   @json($salesByProduct['labels']),
  datasets: [
    { label: 'Leads',    data: @json($salesByProduct['counts']),  backgroundColor: @json($salesByProduct['colors']) },
  ],
}, { indexAxis: 'y', scales: { x: { beginAtZero: true } }, plugins: { legend: { display: false } } });

// eBay Authorization (bar)
mkChart('chartEbayAuth', 'bar', {
  labels:   @json($ebayAuthStats['labels']),
  datasets: [{ label: 'Offers', data: @json($ebayAuthStats['values']), backgroundColor: @json($ebayAuthStats['colors']) }],
}, { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } });

// Logistics by Status (bar)
mkChart('chartLogisticStatus', 'bar', {
  labels:   @json($logisticsByStatus['labels']),
  datasets: [{ label: 'Shipments', data: @json($logisticsByStatus['values']), backgroundColor: @json($logisticsByStatus['colors']) }],
}, { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } });

// Logistics Cost — Budget vs Actual
mkChart('chartLogisticsCost', 'bar', {
  labels: @json($logisticsCost['labels']),
  datasets: [
    { label: 'Budget', data: @json($logisticsCost['budget']), backgroundColor: '#e0e7ff', borderColor: '#6366f1', borderWidth: 1 },
    { label: 'Actual', data: @json($logisticsCost['actual']), backgroundColor: '#d1fae5', borderColor: '#10b981', borderWidth: 1 },
  ],
}, { scales: { y: { beginAtZero: true, ticks: { callback: v => '$'+v.toLocaleString() } } } });

// Tasks by Status (doughnut)
mkChart('chartTaskStatus', 'doughnut', {
  labels:   @json($tasksByStatus['labels']),
  datasets: [{ data: @json($tasksByStatus['values']), backgroundColor: palette }],
}, { plugins: { legend: { position: 'right' } } });

// Staff task performance
mkChart('chartStaffTasks', 'bar', {
  labels: @json($staffPerformance['labels']),
  datasets: [
    { label: 'Assigned',  data: @json($staffPerformance['assigned']),  backgroundColor: '#e0e7ff', borderColor: '#6366f1', borderWidth: 1 },
    { label: 'Completed', data: @json($staffPerformance['completed']), backgroundColor: '#d1fae5', borderColor: '#10b981', borderWidth: 1 },
  ],
}, { scales: { y: { beginAtZero: true } } });
}

function scheduleAdminReportCharts() {
  const run = () => initAdminReportCharts();

  if ('requestIdleCallback' in window) {
    requestIdleCallback(run, { timeout: 1200 });
  } else {
    setTimeout(run, 80);
  }
}

document.addEventListener('DOMContentLoaded', scheduleAdminReportCharts);
document.addEventListener('turbo:load', scheduleAdminReportCharts);
</script>
@endpush
