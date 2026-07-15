@extends('layouts.app')
@section('title', 'Team Report')
@section('page_title', 'Team Report')

@section('content')
<div class="animate-fade-in" x-data="{ reportTab: '{{ $activeTab }}' }">

  @if(session('share_url'))
  <div class="mb-4 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-700 px-4 py-3 text-sm font-medium flex items-center justify-between gap-3 flex-wrap">
    <span>🔗 Share link ready — anyone with this link can view this report (no login required):</span>
    <div class="flex items-center gap-2">
      <input id="share-url-input" type="text" readonly value="{{ session('share_url') }}" class="form-input text-xs py-1.5 w-72" onclick="this.select()">
      <button type="button" class="btn btn-secondary text-xs py-1.5 px-3" onclick="navigator.clipboard.writeText(document.getElementById('share-url-input').value)">Copy</button>
    </div>
  </div>
  @endif

  {{-- ── Page switcher + period filter ────────────────────────────────────── --}}
  <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div class="flex gap-2">
      <a href="{{ route('crm.reports.index') }}" class="btn btn-primary text-sm">📊 Team Report</a>
      <a href="{{ route('crm.reports.staff') }}" class="btn btn-secondary text-sm">👤 Staff Report</a>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <div x-show="reportTab === 'general'" x-cloak class="flex items-center gap-2 flex-wrap">
        <div class="flex gap-1">
          @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
          <a href="{{ route('crm.reports.index', ['period' => $key]) }}" data-turbo="false"
             class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold {{ $granularity === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
            {{ $label }}
          </a>
          @endforeach
        </div>
        <form method="GET" action="{{ route('crm.reports.index') }}" data-turbo="false" class="flex items-center flex-wrap gap-2">
          <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-400">
            From
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input text-sm py-1.5 w-36">
          </label>
          <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-400">
            To
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input text-sm py-1.5 w-36">
          </label>
          <button type="submit" class="btn btn-secondary text-xs py-1.5 px-3">Filter</button>
          @if(request('date_from') || request('date_to'))
          <a href="{{ route('crm.reports.index') }}" data-turbo="false" class="btn btn-secondary text-xs py-1.5 px-3">Clear</a>
          @endif
        </form>
      </div>
      <form method="POST" action="{{ route('crm.reports.share') }}" data-turbo="false">
        @csrf
        <input type="hidden" name="date_from" value="{{ request('date_from') }}">
        <input type="hidden" name="date_to" value="{{ request('date_to') }}">
        <input type="hidden" name="period" value="{{ $granularity }}">
        <button type="submit" class="btn btn-secondary text-xs py-1.5 px-3">🔗 Share Link</button>
      </form>
      <a href="{{ route('crm.reports.export.pdf', request()->query() + ['period' => $granularity]) }}" class="btn btn-secondary text-xs py-1.5 px-3">📄 Export PDF</a>
      <a href="{{ route('crm.reports.export.csv', request()->query() + ['period' => $granularity]) }}" class="btn btn-secondary text-xs py-1.5 px-3">📊 Export CSV</a>
    </div>
  </div>
  <p x-show="reportTab === 'general'" x-cloak class="text-sm text-slate-500 mb-5">Company-wide totals only — no individual staff data. Showing activity for <span class="font-semibold text-slate-700">{{ $periodLabel }}</span>.</p>

  {{-- ── Domain tabs — pick exactly one report to look at, so nothing is mixed together ─── --}}
  <div class="flex gap-1 flex-wrap mb-5">
    <button type="button" @click="reportTab = 'general'; $nextTick(() => initReportChart('general'))"
            class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold" :class="reportTab === 'general' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'">
      📋 General Report
    </button>
    @foreach($domainReports as $domainKey => $domain)
    <button type="button" @click="reportTab = '{{ $domainKey }}'; $nextTick(() => initReportChart('{{ $domainKey }}'))"
            class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold" :class="reportTab === '{{ $domainKey }}' ? 'text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
            :style="reportTab === '{{ $domainKey }}' ? 'background:{{ $domain['color'] }}' : ''">
      {{ $domain['icon'] }} {{ $domain['label'] }}
    </button>
    @endforeach
  </div>

  @php
    $ebaySales = $domainReports['ebay']['metrics']['Sales'];
    $websiteSales = $domainReports['website']['metrics']['Sales'];
    // Each domain's first metric (Total Customer / Cases Assigned /
    // Shipments Assigned) is its natural "how much came in" headline — the
    // same number buildDomainReports() already computes, just surfaced as
    // one comparable figure per domain instead of a full metrics grid.
    $headline = collect($domainReports)->map(fn ($d) => reset($d['metrics']));
    $maxHeadline = $headline->max() ?: 1;
  @endphp

  {{-- General Report — headline overview: hero total, one comparable number per domain, activity trend, and the remaining detail metrics in the sidebar --}}
  <div x-show="reportTab === 'general'" x-cloak>
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

      {{-- ══════════════════════ MAIN COLUMN ══════════════════════ --}}
      <div class="xl:col-span-2 space-y-5">

        <div class="card p-0 overflow-hidden">
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

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
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

        <div class="card p-5">
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
      </div>

      {{-- ══════════════════════ SIDEBAR ══════════════════════ --}}
      <div class="space-y-5">
        <div class="card p-5">
          <h4 class="font-semibold text-slate-700 text-sm mb-4">Details</h4>
          <div class="space-y-4">
            @foreach($domainReports as $domainKey => $domain)
            @php $rest = collect($domain['metrics'])->except(array_key_first($domain['metrics'])); @endphp
            @if($rest->isNotEmpty())
            <div class="{{ !$loop->first ? 'pt-4 border-t border-slate-100' : '' }}">
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

        <div class="card p-5 text-center">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">{{ $periodLabel }} report</p>
          <p class="text-sm text-slate-500 mb-4">Export or share this period's company-wide totals.</p>
          <div class="flex flex-col gap-2">
            <a href="{{ route('crm.reports.export.pdf', request()->query() + ['period' => $granularity]) }}" class="btn btn-primary text-sm w-full">📄 Export PDF</a>
            <a href="{{ route('crm.reports.export.csv', request()->query() + ['period' => $granularity]) }}" class="btn btn-secondary text-sm w-full">📊 Export CSV</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- One profile-style section per domain — its own Day/Week/Month + date-range filter, hero headline, KPI grid, then its own activity trend — filtered completely independently of the General Report and of every other domain tab. --}}
  @foreach($domainTabReports as $domainKey => $domain)
  @php
    $countMax = collect($domain['metrics'])->except($domain['money_keys'])->max() ?: 1;
    $domainHeadlineLabel = array_key_first($domain['metrics']);
    $domainHeadlineValue = reset($domain['metrics']);
    $dp = $domainPeriods[$domainKey];
    $domainOtherQuery = collect(request()->query())->except(["{$domainKey}_period", "{$domainKey}_date_from", "{$domainKey}_date_to", 'tab'])->all();
  @endphp
  <div x-show="reportTab === '{{ $domainKey }}'" x-cloak>
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
      <div class="flex items-center gap-2 flex-wrap">
        <div class="flex gap-1">
          @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $pKey => $pLabel)
          <a href="{{ route('crm.reports.index', array_merge($domainOtherQuery, ['tab' => $domainKey, "{$domainKey}_period" => $pKey])) }}" data-turbo="false"
             class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold {{ $dp['granularity'] === $pKey ? 'text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}"
             style="{{ $dp['granularity'] === $pKey ? 'background:' . $domain['color'] : '' }}">
            {{ $pLabel }}
          </a>
          @endforeach
        </div>
        <form method="GET" action="{{ route('crm.reports.index') }}" data-turbo="false" class="flex items-center flex-wrap gap-2">
          @foreach($domainOtherQuery as $qKey => $qVal)
            @foreach((array) $qVal as $qv)
            <input type="hidden" name="{{ is_array($qVal) ? $qKey . '[]' : $qKey }}" value="{{ $qv }}">
            @endforeach
          @endforeach
          <input type="hidden" name="tab" value="{{ $domainKey }}">
          <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-400">
            From
            <input type="date" name="{{ $domainKey }}_date_from" value="{{ request("{$domainKey}_date_from") }}" class="form-input text-sm py-1.5 w-36">
          </label>
          <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-400">
            To
            <input type="date" name="{{ $domainKey }}_date_to" value="{{ request("{$domainKey}_date_to") }}" class="form-input text-sm py-1.5 w-36">
          </label>
          <button type="submit" class="btn btn-secondary text-xs py-1.5 px-3">Filter</button>
          @if(request("{$domainKey}_date_from") || request("{$domainKey}_date_to"))
          <a href="{{ route('crm.reports.index', array_merge($domainOtherQuery, ['tab' => $domainKey])) }}" data-turbo="false" class="btn btn-secondary text-xs py-1.5 px-3">Clear</a>
          @endif
        </form>
      </div>
    </div>

    <div class="card p-0 overflow-hidden mb-4">
      <div class="p-6" style="background:linear-gradient(135deg,{{ $domain['color'] }},{{ $domain['color'] }}cc)">
        <p class="text-xs font-semibold text-white/80 uppercase tracking-wide mb-1">{{ $domain['icon'] }} {{ $domain['label'] }} — {{ $domainHeadlineLabel }}</p>
        <div class="text-3xl font-bold text-white">{{ in_array($domainHeadlineLabel, $domain['money_keys']) ? '$' . number_format($domainHeadlineValue, 2) : $domainHeadlineValue }}</div>
        <p class="text-white/70 text-xs mt-1">{{ $dp['label'] }}</p>
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
        <span class="text-xs text-slate-400">{{ $dp['label'] }}</span>
      </div>
      @if(array_sum($domainTabTrends[$domainKey]['data']->all()) > 0)
      <div style="height:220px; position:relative;">
        <canvas id="domainTrendChart-{{ $domainKey }}"></canvas>
      </div>
      @else
      <p class="text-sm text-slate-400 text-center py-10">No activity in this period.</p>
      @endif
    </div>
  </div>
  @endforeach

</div>
@endsection

@php
  $chartDefs = collect(['general' => ['el' => 'teamTrendChart', 'labels' => $trend['labels'], 'data' => $trend['data'], 'color' => '#6366f1']])
      ->merge(collect($domainTabReports)->mapWithKeys(fn ($d, $key) => [$key => [
          'el'     => "domainTrendChart-{$key}",
          'labels' => $domainTabTrends[$key]['labels'],
          'data'   => $domainTabTrends[$key]['data'],
          'color'  => $d['color'],
      ]]));
@endphp
@push('scripts')
<script>
// Wrapped in an IIFE: Turbo re-inserts (and re-executes) this whole <script>
// tag on every Turbo-driven visit to this page (Day/Week/Month tabs, Filter,
// Share Link, etc. are all Turbo-intercepted navigations, not hard reloads).
// Top-level `const`/`let` would throw "already declared" on the second
// execution since they share one lexical scope with the previous run's —
// the IIFE gives each execution its own fresh scope instead.
(function () {
    // One definition per tab's trend chart — the General Report's combined
    // trend plus each domain's own, independently-filtered trend. Since the
    // page can now land directly on any tab (via ?tab=logistic etc., from
    // that domain's own filter form), ANY of these — not just the domain
    // ones — can start out hidden behind x-show/x-cloak, so they all go
    // through the same lazy, ResizeObserver-backed init below rather than
    // assuming the General tab is always the one visible on load.
    const chartDefs = @json($chartDefs);
    const charts = {};

    function createChart(key) {
        if (charts[key]) return;
        const def = chartDefs[key];
        if (!def) return;
        const el = document.getElementById(def.el);
        if (!el) return;

        charts[key] = new Chart(el, {
            type: 'line',
            data: {
                labels: def.labels,
                datasets: [{
                    data: def.data,
                    borderColor: def.color,
                    backgroundColor: def.color + '1f',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: def.color,
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

    // Exposed on window: Alpine's inline @click="...initReportChart(...)"
    // expression on the tab buttons needs to reach this as a global. Safe to
    // call repeatedly — resizes an existing chart instead of recreating it.
    window.initReportChart = function (key) {
        if (!window.Chart) return;
        const def = chartDefs[key];
        if (!def) return;
        const el = document.getElementById(def.el);
        if (!el) return;

        if (charts[key]) {
            charts[key].resize();
            return;
        }

        if (el.getBoundingClientRect().width > 0) {
            createChart(key);
            return;
        }

        const ro = new ResizeObserver((entries) => {
            if (entries[0].contentRect.width > 0) {
                ro.disconnect();
                createChart(key);
            }
        });
        ro.observe(el.parentElement);
    };

    // Whichever tab is active on load (General by default, or a domain tab
    // when the URL carries its own filter) gets its chart created once
    // Vite's module script has set window.Chart — on a genuine hard load
    // that means waiting for DOMContentLoaded; on a Turbo-driven visit,
    // window.Chart is already set from the first hard load, so it's safe to
    // run immediately.
    const activeTabKey = @json($activeTab);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.initReportChart(activeTabKey), { once: true });
    } else {
        window.initReportChart(activeTabKey);
    }
})();
</script>
@endpush
