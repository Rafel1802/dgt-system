@extends('layouts.auth')
@section('title', 'Shared Team Report')

@section('content')
<div class="min-h-full bg-slate-50 py-8 px-4" x-data="{ reportTab: 'general' }">
  <div class="max-w-5xl mx-auto">

    <p class="text-xs font-semibold text-indigo-600 mb-4">🔗 Shared read-only report · This link stays live and shows current data</p>

    <div class="mb-5">
      <h1 class="font-display font-bold text-2xl text-slate-800">📊 Team Report</h1>
      <p class="text-sm text-slate-500 mt-1">Company-wide totals only — no individual staff data. Showing activity for <span class="font-semibold text-slate-700">{{ $periodLabel }}</span>.</p>
    </div>

    <div class="flex gap-1 flex-wrap mb-5">
      <button type="button" @click="reportTab = 'general'"
              class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold" :class="reportTab === 'general' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'">
        📋 General Report
      </button>
      @foreach($domainReports as $domainKey => $domain)
      <button type="button" @click="reportTab = '{{ $domainKey }}'; $nextTick(() => initDomainChart('{{ $domainKey }}'))"
              class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold" :class="reportTab === '{{ $domainKey }}' ? 'text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
              :style="reportTab === '{{ $domainKey }}' ? 'background:{{ $domain['color'] }}' : ''">
        {{ $domain['icon'] }} {{ $domain['label'] }}
      </button>
      @endforeach
    </div>

    @php
      $ebaySales = $domainReports['ebay']['metrics']['Sales'];
      $websiteSales = $domainReports['website']['metrics']['Sales'];
      $headline = collect($domainReports)->map(fn ($d) => reset($d['metrics']));
      $maxHeadline = $headline->max() ?: 1;
    @endphp

    <div x-show="reportTab === 'general'">
      <div class="card p-0 overflow-hidden mb-5">
        <div class="p-6" style="background:linear-gradient(135deg,#4338ca,#6366f1)">
          <p class="text-xs font-semibold text-indigo-100 uppercase tracking-wide mb-1">Total Sales (eBay + Website)</p>
          <div class="text-3xl font-bold text-white">${{ number_format($totalSales, 2) }}</div>
          <p class="text-indigo-200 text-xs mt-1">{{ $periodLabel }}</p>
        </div>
        <div class="flex h-2">
          <div style="background:{{ $domainReports['website']['color'] }}; flex-grow:{{ max($websiteSales, 0.001) }};" class="border-r-2 border-white"></div>
          <div style="background:{{ $domainReports['ebay']['color'] }}; flex-grow:{{ max($ebaySales, 0.001) }};"></div>
        </div>
        <div class="flex flex-wrap gap-x-5 gap-y-2 px-6 py-3 bg-slate-50 border-t border-slate-100">
          <div class="flex items-center gap-1.5 text-xs">
            <span class="w-2 h-2 rounded-full inline-block" style="background:{{ $domainReports['website']['color'] }}"></span>
            <span class="text-slate-500">Website</span>
            <b class="text-slate-800">${{ number_format($websiteSales, 2) }}</b>
          </div>
          <div class="flex items-center gap-1.5 text-xs">
            <span class="w-2 h-2 rounded-full inline-block" style="background:{{ $domainReports['ebay']['color'] }}"></span>
            <span class="text-slate-500">eBay</span>
            <b class="text-slate-800">${{ number_format($ebaySales, 2) }}</b>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        @foreach($domainReports as $domainKey => $domain)
        <div class="card p-4">
          <div class="flex items-center gap-2 mb-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl text-sm" style="background:{{ $domain['color'] }}1a">{{ $domain['icon'] }}</span>
            <span class="text-xs font-semibold text-slate-500">{{ $domain['label'] }}</span>
          </div>
          <p class="text-2xl font-black text-slate-800">{{ $headline[$domainKey] }}</p>
          <p class="text-xs text-slate-400 mb-2">{{ array_key_first($domain['metrics']) }}</p>
          <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full rounded-full" style="width:{{ round($headline[$domainKey] / $maxHeadline * 100) }}%; background:{{ $domain['color'] }}"></div>
          </div>
        </div>
        @endforeach
      </div>

      <div class="card p-5 mb-5">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
          <h4 class="font-semibold text-slate-700 text-sm">Company Activity Trend</h4>
          <span class="text-xs text-slate-400">{{ $periodLabel }}</span>
        </div>
        @if(array_sum($trend['data']->all()) > 0)
        <div style="height:260px; position:relative;">
          <canvas id="teamTrendChart"></canvas>
        </div>
        @else
        <p class="text-sm text-slate-400 text-center py-10">No activity in this period.</p>
        @endif
      </div>

      <div class="card p-5">
        <h4 class="font-semibold text-slate-700 text-sm mb-4">Details</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
          @foreach($domainReports as $domainKey => $domain)
          @php $rest = collect($domain['metrics'])->except(array_key_first($domain['metrics'])); @endphp
          @if($rest->isNotEmpty())
          <div>
            <div class="flex items-center gap-2 mb-2">
              <span class="flex h-7 w-7 items-center justify-center rounded-lg text-xs" style="background:{{ $domain['color'] }}1a">{{ $domain['icon'] }}</span>
              <span class="text-xs font-semibold text-slate-600">{{ $domain['label'] }}</span>
            </div>
            @foreach($rest as $metricLabel => $value)
            <div class="flex justify-between text-sm mb-1"><span class="text-slate-500">{{ $metricLabel }}</span><b class="text-slate-800">{{ in_array($metricLabel, $domain['money_keys']) ? '$' . number_format($value, 2) : $value }}</b></div>
            @endforeach
          </div>
          @endif
          @endforeach
        </div>
      </div>
    </div>

    @foreach($domainReports as $domainKey => $domain)
    @php
      $countMax = collect($domain['metrics'])->except($domain['money_keys'])->max() ?: 1;
      $domainHeadlineLabel = array_key_first($domain['metrics']);
      $domainHeadlineValue = reset($domain['metrics']);
    @endphp
    <div x-show="reportTab === '{{ $domainKey }}'" x-cloak>
      <div class="card p-0 overflow-hidden mb-4">
        <div class="p-6" style="background:linear-gradient(135deg,{{ $domain['color'] }},{{ $domain['color'] }}cc)">
          <p class="text-xs font-semibold text-white/80 uppercase tracking-wide mb-1">{{ $domain['icon'] }} {{ $domain['label'] }} — {{ $domainHeadlineLabel }}</p>
          <div class="text-3xl font-bold text-white">{{ in_array($domainHeadlineLabel, $domain['money_keys']) ? '$' . number_format($domainHeadlineValue, 2) : $domainHeadlineValue }}</div>
          <p class="text-white/70 text-xs mt-1">{{ $periodLabel }}</p>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        @foreach($domain['metrics'] as $metricLabel => $value)
        <div class="card p-4">
          <div class="flex items-center gap-2 mb-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl text-sm" style="background:{{ $domain['color'] }}1a">{{ $domain['icon'] }}</span>
            <span class="text-xs font-semibold text-slate-500">{{ $metricLabel }}</span>
          </div>
          <p class="text-2xl font-black" style="color:{{ $domain['color'] }}">
            {{ in_array($metricLabel, $domain['money_keys']) ? '$' . number_format($value, 2) : $value }}
          </p>
          @unless(in_array($metricLabel, $domain['money_keys']))
          <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden mt-2">
            <div class="h-full rounded-full" style="width:{{ round($value / $countMax * 100) }}%; background:{{ $domain['color'] }}"></div>
          </div>
          @endunless
        </div>
        @endforeach
      </div>

      <div class="card p-5">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
          <h4 class="font-semibold text-slate-700 text-sm">{{ $domain['label'] }} Activity Trend</h4>
          <span class="text-xs text-slate-400">{{ $periodLabel }}</span>
        </div>
        @if(array_sum($trend['series'][$domainKey]->all()) > 0)
        <div style="height:220px; position:relative;">
          <canvas id="domainTrendChart-{{ $domainKey }}"></canvas>
        </div>
        @else
        <p class="text-sm text-slate-400 text-center py-10">No activity in this period.</p>
        @endif
      </div>
    </div>
    @endforeach

    <p class="text-xs text-slate-400 mt-6 text-center">Shared from KIUQ SYSTEM CRM · This link stays live and shows current data.</p>
  </div>
</div>

<script>
async function initTeamTrendChart() {
    if (!window.Chart && window.loadChart) {
        await window.loadChart();
    }
    if (!window.Chart) return;
    const el = document.getElementById('teamTrendChart');
    if (el) {
        new Chart(el, {
            type: 'line',
            data: {
                labels: @json($trend['labels']),
                datasets: [{
                    data: @json($trend['data']),
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
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTeamTrendChart);
} else {
    initTeamTrendChart();
}

// Per-domain trend charts are created lazily (on first click into that tab)
// since their canvases sit inside x-cloak'd tab panes that start hidden —
// Chart.js can't size a chart drawn onto a display:none canvas. A single
// $nextTick isn't always enough (the x-show display toggle can take an
// extra paint before layout settles), so this falls back to a
// ResizeObserver that waits for the container to report a nonzero size.
const __domainTrendData = @json($trend['series']);
const __domainColors = @json(collect($domainReports)->map(fn ($d) => $d['color']));
const __domainCharts = {};

function createDomainChart(domainKey, el) {
    if (__domainCharts[domainKey]) return;
    const color = __domainColors[domainKey] || '#6366f1';
    __domainCharts[domainKey] = new Chart(el, {
        type: 'line',
        data: {
            labels: @json($trend['labels']),
            datasets: [{
                data: __domainTrendData[domainKey] || [],
                borderColor: color,
                backgroundColor: color + '1f',
                tension: 0.4,
                fill: true,
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: color,
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

async function initDomainChart(domainKey) {
    if (!window.Chart && window.loadChart) {
        await window.loadChart();
    }
    if (!window.Chart) return;
    const el = document.getElementById('domainTrendChart-' + domainKey);
    if (!el) return;

    if (__domainCharts[domainKey]) {
        __domainCharts[domainKey].resize();
        return;
    }

    if (el.getBoundingClientRect().width > 0) {
        createDomainChart(domainKey, el);
        return;
    }

    const ro = new ResizeObserver((entries) => {
        if (entries[0].contentRect.width > 0) {
            ro.disconnect();
            createDomainChart(domainKey, el);
        }
    });
    ro.observe(el.parentElement);
}
</script>
@endsection
