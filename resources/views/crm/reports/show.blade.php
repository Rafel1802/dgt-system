@extends('layouts.app')
@section('title', $user->name . ' — Staff Report')
@section('page_title', 'Staff Report')

@section('content')
<div class="animate-fade-in">

  @php
    $domainColors = ['website' => '#6366f1', 'ebay' => '#f59e0b', 'tech_support' => '#ef4444', 'logistic' => '#10b981'];
    $domainIcons  = ['website' => '🌐', 'ebay' => '🛒', 'tech_support' => '🛠️', 'logistic' => '🚚'];
    $domainLabels = ['website' => 'Website', 'ebay' => 'eBay', 'tech_support' => 'Tech Support', 'logistic' => 'Logistic'];
    // One consistent "how much did this domain hand this person" headline per
    // domain — the outcome-side numbers (successful leads, calls answered,
    // cases resolved, shipments complete) live in the Details panel instead,
    // so the 4 headline cards stay directly comparable to each other.
    $headline = [
        'website'      => $summary['website']['crm_handled'],
        'ebay'         => $summary['ebay']['ebay_handled'],
        'tech_support' => $summary['tech_support']['assigned'],
        'logistic'     => $summary['logistic']['assigned'],
    ];
    $totalHandled = collect($activeDomains)->sum(fn ($d) => $headline[$d]);
    $maxHeadline = collect($activeDomains)->map(fn ($d) => $headline[$d])->max() ?: 1;
    // Computed unconditionally (not inside the @if/@else below) since the
    // scripts block at the bottom of this view always renders and references
    // it regardless of which branch of the page's own markup was taken.
    $pieTotals = collect($activeDomains)->mapWithKeys(fn ($d) => [$d => array_sum($chart['datasets'][$d])]);
  @endphp

  @if(session('share_url'))
  <div class="mb-4 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-700 px-4 py-3 text-sm font-medium flex items-center justify-between gap-3 flex-wrap">
    <span>🔗 Share link ready — anyone with this link can view this report (no login required):</span>
    <div class="flex items-center gap-2">
      <input id="share-url-input" type="text" readonly value="{{ session('share_url') }}" class="form-input text-xs py-1.5 w-72" onclick="this.select()">
      <button type="button" class="btn btn-secondary text-xs py-1.5 px-3" onclick="navigator.clipboard.writeText(document.getElementById('share-url-input').value)">Copy</button>
    </div>
  </div>
  @endif

  {{-- ── Top bar: back link + period controls + export ─────────────────────── --}}
  <div class="mb-5 flex items-center justify-between flex-wrap gap-3">
    <a href="{{ route('crm.reports.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Reports</a>
    <div class="flex items-center gap-3 flex-wrap">
      <div class="flex gap-1">
        @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
        <a href="{{ route('crm.reports.show', ['user' => $user, 'period' => $key]) }}"
           class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold {{ $granularity === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
          {{ $label }}
        </a>
        @endforeach
      </div>
      <form method="GET" action="{{ route('crm.reports.show', $user) }}" class="flex items-center flex-wrap gap-2">
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
        <a href="{{ route('crm.reports.show', $user) }}" class="btn btn-secondary text-xs py-1.5 px-3">Clear</a>
        @endif
      </form>
      <form method="POST" action="{{ route('crm.reports.staff.share', $user) }}">
        @csrf
        <input type="hidden" name="date_from" value="{{ request('date_from') }}">
        <input type="hidden" name="date_to" value="{{ request('date_to') }}">
        <input type="hidden" name="period" value="{{ $granularity }}">
        <button type="submit" class="btn btn-secondary text-sm">🔗 Share Link</button>
      </form>
      <a href="{{ route('crm.reports.show.export.pdf', ['user' => $user] + request()->query() + ['period' => $granularity]) }}" class="btn btn-secondary text-sm">📄 Export PDF</a>
      <a href="{{ route('crm.reports.export', ['user' => $user] + request()->query() + ['period' => $granularity]) }}" class="btn btn-secondary text-sm">⬇ Export CSV</a>
    </div>
  </div>

  @if(count($activeDomains) === 0)
  <div class="card p-10 text-center text-slate-400 text-sm">No staff activity recorded for {{ $user->name }} yet.</div>
  @else

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- ══════════════════════ MAIN COLUMN ══════════════════════ --}}
    <div class="xl:col-span-2 space-y-5">

      {{-- ── Hero: identity + headline total + per-domain share bar ─────────── --}}
      <div class="card p-0 overflow-hidden">
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
        {{-- Per-domain share of the total — a stacked proportional bar, direct-labeled below (never color-alone) --}}
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

      {{-- ── 4 KPI cards, one per active domain ──────────────────────────────── --}}
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
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

      {{-- ── Activity trend ───────────────────────────────────────────────────── --}}
      <div class="card p-5">
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

      {{-- ── Activity breakdown by domain (existing pie, restyled) ──────────── --}}
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
    </div>

    {{-- ══════════════════════ SIDEBAR ══════════════════════ --}}
    <div class="space-y-5">

      {{-- ── Details: the outcome-side metric per domain ─────────────────────── --}}
      <div class="card p-5">
        <h4 class="font-semibold text-slate-700 text-sm mb-4">Details</h4>
        <div class="space-y-4">
          @if(in_array('website', $activeDomains))
          <div>
            <div class="flex items-center gap-2 mb-2">
              <span class="flex h-7 w-7 items-center justify-center rounded-lg text-xs" style="background:{{ $domainColors['website'] }}1a">{{ $domainIcons['website'] }}</span>
              <span class="text-xs font-semibold text-slate-600">Website</span>
            </div>
            <div class="flex justify-between text-sm mb-1"><span class="text-slate-500">Successful leads</span><b class="text-slate-800">{{ $summary['website']['crm_sales'] }}</b></div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Calls answered</span><b class="text-slate-800">{{ $summary['website']['calls_answered'] }}</b></div>
          </div>
          @endif
          @if(in_array('tech_support', $activeDomains))
          <div class="{{ in_array('website', $activeDomains) ? 'pt-4 border-t border-slate-100' : '' }}">
            <div class="flex items-center gap-2 mb-2">
              <span class="flex h-7 w-7 items-center justify-center rounded-lg text-xs" style="background:{{ $domainColors['tech_support'] }}1a">{{ $domainIcons['tech_support'] }}</span>
              <span class="text-xs font-semibold text-slate-600">Tech Support</span>
            </div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Cases resolved</span><b class="text-slate-800">{{ $summary['tech_support']['resolved'] }}</b></div>
          </div>
          @endif
          @if(in_array('logistic', $activeDomains))
          <div class="{{ (in_array('website', $activeDomains) || in_array('tech_support', $activeDomains)) ? 'pt-4 border-t border-slate-100' : '' }}">
            <div class="flex items-center gap-2 mb-2">
              <span class="flex h-7 w-7 items-center justify-center rounded-lg text-xs" style="background:{{ $domainColors['logistic'] }}1a">{{ $domainIcons['logistic'] }}</span>
              <span class="text-xs font-semibold text-slate-600">Logistic</span>
            </div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Shipments complete</span><b class="text-slate-800">{{ $summary['logistic']['complete'] }}</b></div>
          </div>
          @endif
          @unless(in_array('website', $activeDomains) || in_array('tech_support', $activeDomains) || in_array('logistic', $activeDomains))
          <p class="text-sm text-slate-400">No additional outcomes to show for this period.</p>
          @endunless
        </div>
      </div>

      {{-- ── Export ────────────────────────────────────────────────────────────── --}}
      <div class="card p-5 text-center">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">{{ $periodLabel }} report</p>
        <p class="text-sm text-slate-500 mb-4">Download this period's activity as a spreadsheet.</p>
        <a href="{{ route('crm.reports.export', ['user' => $user] + request()->query() + ['period' => $granularity]) }}" class="btn btn-primary text-sm w-full">⬇ Export CSV</a>
      </div>
    </div>
  </div>
  @endif

</div>
@endsection

@push('scripts')
<script>
function initStaffReportCharts() {
    if (!window.Chart) return;

    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#94a3b8';

    const trendEl = document.getElementById('staffTrendChart');
    if (trendEl) {
        Chart.getChart(trendEl)?.destroy();
        new Chart(trendEl, {
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

    const pieEl = document.getElementById('staffActivityChart');
    if (pieEl) {
        Chart.getChart(pieEl)?.destroy();
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

document.addEventListener('DOMContentLoaded', initStaffReportCharts);
document.addEventListener('turbo:load', initStaffReportCharts);
</script>
@endpush
