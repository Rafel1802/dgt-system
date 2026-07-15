@extends('layouts.auth')
@section('title', $user->name . ' — Shared Staff Report')

@section('content')
<div class="min-h-full bg-slate-50 py-8 px-4">
  <div class="max-w-5xl mx-auto">

    @php
      $domainColors = ['website' => '#6366f1', 'ebay' => '#f59e0b', 'tech_support' => '#ef4444', 'logistic' => '#10b981'];
      $domainIcons  = ['website' => '🌐', 'ebay' => '🛒', 'tech_support' => '🛠️', 'logistic' => '🚚'];
      $domainLabels = ['website' => 'Website', 'ebay' => 'eBay', 'tech_support' => 'Tech Support', 'logistic' => 'Logistic'];
      $headline = [
          'website'      => $summary['website']['crm_handled'] ?? 0,
          'ebay'         => $summary['ebay']['ebay_handled'] ?? 0,
          'tech_support' => $summary['tech_support']['assigned'] ?? 0,
          'logistic'     => $summary['logistic']['assigned'] ?? 0,
      ];
      $totalHandled = collect($activeDomains)->sum(fn ($d) => $headline[$d]);
      $maxHeadline = collect($activeDomains)->map(fn ($d) => $headline[$d])->max() ?: 1;
      $pieTotals = collect($activeDomains)->mapWithKeys(fn ($d) => [$d => array_sum($chart['datasets'][$d])]);
    @endphp

    <p class="text-xs font-semibold text-indigo-600 mb-1">🔗 Shared read-only report · This link stays live and shows current data</p>

    @if(count($activeDomains) === 0)
    <div class="card p-10 text-center text-slate-400 text-sm">No staff activity recorded for {{ $user->name }} yet.</div>
    @else

    <div class="card p-0 overflow-hidden mb-5">
      <div class="p-6 flex items-center justify-between gap-6 flex-wrap" style="background:linear-gradient(135deg,#4338ca,#6366f1)">
        <div class="flex items-center gap-4">
          <img src="{{ $user->avatar_url }}" class="w-14 h-14 rounded-full ring-2 ring-white/40">
          <div>
            <p class="text-indigo-100 text-xs font-semibold uppercase tracking-wide">Hello,</p>
            <h2 class="font-display font-bold text-white text-xl leading-tight">{{ $user->name }}</h2>
            <p class="text-indigo-200 text-xs mt-0.5">{{ $user->crm_role_display }} · {{ $periodLabel }}</p>
          </div>
        </div>
        <div class="text-right">
          <p class="text-indigo-100 text-xs font-semibold uppercase tracking-wide">Total Handled</p>
          <p class="text-white text-4xl font-black leading-none mt-1">{{ $totalHandled }}</p>
        </div>
      </div>
      <div class="flex h-2" role="img" aria-label="Handled breakdown by domain">
        @foreach($activeDomains as $d)
        <div style="background:{{ $domainColors[$d] }}; flex-grow:{{ max($headline[$d], 0.001) }};" class="{{ !$loop->first ? 'border-l-2 border-white' : '' }}"></div>
        @endforeach
      </div>
      <div class="flex flex-wrap gap-x-5 gap-y-2 px-6 py-3 bg-slate-50 border-t border-slate-100">
        @foreach($activeDomains as $d)
        <div class="flex items-center gap-1.5 text-xs">
          <span class="w-2 h-2 rounded-full inline-block" style="background:{{ $domainColors[$d] }}"></span>
          <span class="text-slate-500">{{ $domainLabels[$d] }}</span>
          <b class="text-slate-800">{{ $headline[$d] }}</b>
        </div>
        @endforeach
      </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
      @foreach($activeDomains as $d)
      <div class="card p-4">
        <div class="flex items-center gap-2 mb-3">
          <span class="flex h-8 w-8 items-center justify-center rounded-xl text-sm" style="background:{{ $domainColors[$d] }}1a">{{ $domainIcons[$d] }}</span>
          <span class="text-xs font-semibold text-slate-500">{{ $domainLabels[$d] }}</span>
        </div>
        <p class="text-2xl font-black text-slate-800">{{ $headline[$d] }}</p>
        <p class="text-xs text-slate-400 mb-2">Handled</p>
        <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
          <div class="h-full rounded-full" style="width:{{ round($headline[$d] / $maxHeadline * 100) }}%; background:{{ $domainColors[$d] }}"></div>
        </div>
      </div>
      @endforeach
    </div>

    <div class="card p-5 mb-5">
      <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <h4 class="font-semibold text-slate-700 text-sm">Activity Trend</h4>
        <span class="text-xs text-slate-400">{{ $periodLabel }}</span>
      </div>
      @if(array_sum($trend['data']->all()) > 0)
      <div style="height:260px; position:relative;">
        <canvas id="staffTrendChart"></canvas>
      </div>
      @else
      <p class="text-sm text-slate-400 text-center py-10">No activity in this period.</p>
      @endif
    </div>

    <div class="card p-5">
      <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <h4 class="font-semibold text-slate-700 text-sm">Activity Breakdown by Domain</h4>
        <span class="text-xs text-slate-400">{{ $periodLabel }}</span>
      </div>
      @if($pieTotals->sum() > 0)
      <div class="max-w-sm mx-auto">
        <canvas id="staffActivityChart" height="260"></canvas>
      </div>
      @else
      <p class="text-sm text-slate-400 text-center py-10">No activity in this period.</p>
      @endif
    </div>
    @endif

    <p class="text-xs text-slate-400 mt-6 text-center">Shared from KIUQ SYSTEM CRM · This link stays live and shows current data.</p>
  </div>
</div>

<script>
if (window.Chart) {
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#94a3b8';

    const trendEl = document.getElementById('staffTrendChart');
    if (trendEl) {
        new Chart(trendEl, {
            type: 'line',
            data: {
                labels: @json($trend['labels'] ?? []),
                datasets: [{
                    data: @json($trend['data'] ?? []),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.12)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: '#6366f1',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
                },
            },
        });
    }

    const pieEl = document.getElementById('staffActivityChart');
    if (pieEl) {
        new Chart(pieEl, {
            type: 'doughnut',
            data: {
                labels: @json(collect($activeDomains)->map(fn ($d) => $domainLabels[$d])->values()),
                datasets: [{
                    data: @json($pieTotals->values()),
                    backgroundColor: @json(collect($activeDomains)->map(fn ($d) => $domainColors[$d])->values()),
                    borderWidth: 2,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                cutout: '65%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } } },
            },
        });
    }
}
</script>
@endsection
