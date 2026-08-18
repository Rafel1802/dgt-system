@extends('layouts.app')
@section('title', $user->name . ' — Staff Report')
@section('page_title', 'Staff Member Performance')
@section('back_url', route('crm.reports.staff'))

@section('content')
<div id="crm-staff-profile-report" class="animate-fade-in space-y-6">

  @php
    $domainColors = ['website' => '#6366f1', 'ebay' => '#f59e0b', 'tech_support' => '#ef4444', 'logistic' => '#10b981'];
    $domainIcons  = ['website' => '🌐', 'ebay' => '🛒', 'tech_support' => '🛠️', 'logistic' => '🚚'];
    $domainLabels = ['website' => 'Website', 'ebay' => 'eBay', 'tech_support' => 'Tech Support', 'logistic' => 'Logistic'];
    $headline = [
        'website'      => $summary['website']['crm_handled'],
        'ebay'         => $summary['ebay']['ebay_handled'],
        'tech_support' => $summary['tech_support']['assigned'],
        'logistic'     => $summary['logistic']['assigned'],
    ];
    $totalHandled = collect($activeDomains)->sum(fn ($d) => $headline[$d]);
    $maxHeadline = collect($activeDomains)->map(fn ($d) => $headline[$d])->max() ?: 1;
    $pieTotals = collect($activeDomains)->mapWithKeys(fn ($d) => [$d => array_sum($chart['datasets'][$d])]);
  @endphp

  @if(session('share_url'))
  <div class="rounded-2xl bg-indigo-50/90 backdrop-blur border border-indigo-100 text-indigo-900 px-5 py-4 text-sm font-medium flex items-center justify-between gap-4 flex-wrap shadow-sm">
    <div class="flex items-center gap-2.5">
      <span class="text-lg">🔗</span>
      <span><strong>Staff Share Link Ready</strong> — anyone with this secure link can view {{ $user->name }}'s live activity report without logging in:</span>
    </div>
    <div class="flex items-center gap-2">
      <input id="share-url-input" type="text" readonly value="{{ session('share_url') }}" class="form-input text-xs py-1.5 px-3 w-72 bg-white rounded-lg border-indigo-200" onclick="this.select()">
      <button type="button" class="btn btn-primary text-xs py-1.5 px-3 rounded-lg shadow-sm" onclick="navigator.clipboard.writeText(document.getElementById('share-url-input').value)">Copy Link</button>
    </div>
  </div>
  @endif

  {{-- ── Control Bar & Period Filters ────────────────────────────────────── --}}
  <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex items-center justify-between gap-4 flex-wrap">
    <div class="flex items-center gap-3">
      <a href="{{ route('crm.reports.staff') }}" class="px-3.5 py-2 text-xs font-semibold rounded-xl border border-slate-200 hover:border-indigo-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-indigo-600 shadow-sm transition-all flex items-center gap-1.5">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        <span>All Staff</span>
      </a>
      <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider pl-1.5 border-l border-slate-100">Staff Activity Profile</span>
    </div>

    <div class="flex items-center gap-4 flex-wrap">
      <div class="inline-flex p-1 bg-slate-100 rounded-2xl border border-slate-200/30">
        @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
        @php $isActive = ($granularity === $key); @endphp
        <a href="{{ route('crm.reports.show', ['user' => $user, 'period' => $key]) }}"
           class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-all duration-200 {{ $isActive ? 'bg-white text-slate-800 shadow-sm border border-slate-200/40' : 'text-slate-500 hover:text-slate-800 hover:bg-white/40 border border-transparent' }}">
          {{ $label }}
        </a>
        @endforeach
      </div>

      <form method="GET" action="{{ route('crm.reports.show', $user) }}" autocomplete="off" class="flex items-center gap-2 flex-wrap">
        <div class="flex items-center gap-2 pl-3 border-l border-slate-100">
          <div class="flex items-center gap-2 bg-slate-50 border border-slate-200/80 rounded-xl px-3 py-1.5 focus-within:bg-white focus-within:ring-2 focus-within:ring-indigo-500/10 focus-within:border-indigo-500 transition-all">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">From</span>
            <input type="date" name="date_from" value="{{ request('date_from') }}" autocomplete="off" class="bg-transparent border-0 text-xs p-0 focus:ring-0 text-slate-700 font-semibold w-28">
          </div>
          <span class="text-xs text-slate-400">to</span>
          <div class="flex items-center gap-2 bg-slate-50 border border-slate-200/80 rounded-xl px-3 py-1.5 focus-within:bg-white focus-within:ring-2 focus-within:ring-indigo-500/10 focus-within:border-indigo-500 transition-all">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">To</span>
            <input type="date" name="date_to" value="{{ request('date_to') }}" autocomplete="off" class="bg-transparent border-0 text-xs p-0 focus:ring-0 text-slate-700 font-semibold w-28">
          </div>
        </div>
        <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 border border-indigo-600 rounded-xl hover:bg-indigo-700 shadow-sm transition-all">Filter</button>
        @if(request('date_from') || request('date_to'))
        <a href="{{ route('crm.reports.show', $user) }}" class="px-4 py-2 text-xs font-bold text-rose-600 bg-white border border-rose-100 rounded-xl hover:bg-rose-50/50 shadow-sm transition-all">Clear</a>
        @endif
      </form>

      <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

      <div class="flex items-center gap-2">
        <form method="POST" action="{{ route('crm.reports.staff.share', $user) }}">
          @csrf
          <input type="hidden" name="date_from" value="{{ request('date_from') }}">
          <input type="hidden" name="date_to" value="{{ request('date_to') }}">
          <input type="hidden" name="period" value="{{ $granularity }}">
          <button type="submit" class="px-3.5 py-2 text-xs font-semibold rounded-xl border border-slate-200 hover:border-indigo-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-indigo-600 shadow-sm transition-all flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            <span>Share Link</span>
          </button>
        </form>
        <a href="{{ route('crm.reports.show.export.pdf', ['user' => $user] + request()->query() + ['period' => $granularity]) }}" class="px-3.5 py-2 text-xs font-semibold rounded-xl border border-slate-200 hover:border-indigo-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-indigo-600 shadow-sm transition-all flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          <span>Export PDF</span>
        </a>
        <a href="{{ route('crm.reports.export', ['user' => $user] + request()->query() + ['period' => $granularity]) }}" class="px-3.5 py-2 text-xs font-semibold rounded-xl border border-slate-200 hover:border-indigo-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-indigo-600 shadow-sm transition-all flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          <span>Export CSV</span>
        </a>
      </div>
    </div>
  </div>

  @if(count($activeDomains) === 0)
  <div class="bg-white rounded-2xl p-12 text-center border border-slate-100 shadow-sm text-slate-400">
    <span class="text-3xl block mb-2">👤</span>
    <p class="text-sm font-medium">No activity recorded for {{ $user->name }} during {{ strtolower($periodLabel) }}.</p>
  </div>
  @else

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    {{-- MAIN COLUMN --}}
    <div class="xl:col-span-2 space-y-6">

      {{-- Staff Hero Card --}}
      <div class="relative overflow-hidden rounded-3xl shadow-md text-white border border-indigo-950/10" style="background:radial-gradient(circle at 70% 30%, #4338ca 0%, #312e81 100%);">
        <div class="absolute -right-10 -bottom-10 w-60 h-60 bg-white/5 rounded-full blur-2xl pointer-events-none"></div>
        <div class="p-8 relative z-10">
          <div class="flex items-center justify-between gap-6 flex-wrap">
            <div class="flex items-center gap-4">
              <img src="{{ $user->avatar_url }}" class="w-16 h-16 rounded-2xl ring-4 ring-white/20 object-cover shadow-md">
              <div>
                <span class="text-xs font-extrabold uppercase tracking-widest text-indigo-200 bg-white/10 backdrop-blur px-3.5 py-1 rounded-xl border border-white/10">
                  Staff Profile
                </span>
                <h2 class="font-bold text-white text-2xl leading-tight mt-2">{{ $user->name }}</h2>
                <p class="text-indigo-200 text-xs font-semibold mt-1 uppercase tracking-wider">{{ $user->crm_role_display }} &nbsp;·&nbsp; {{ $periodLabel }}</p>
              </div>
            </div>
            <div class="text-right bg-white/5 backdrop-blur px-5 py-3.5 rounded-2xl border border-white/15">
              <p class="text-indigo-200 text-[10px] font-bold uppercase tracking-wider">Total Handled</p>
              <p class="text-white text-4xl font-black leading-none mt-1.5">{{ number_format($totalHandled) }}</p>
            </div>
          </div>

          {{-- Domain Progress Bar --}}
          <div class="mt-6 pt-5 border-t border-white/10">
            <div class="flex justify-between text-xs font-semibold text-indigo-100 mb-2">
              <span>Domain Activity Share</span>
              <span>{{ count($activeDomains) }} Active Domains</span>
            </div>
            <div class="h-3 w-full bg-black/20 rounded-full p-0.5 overflow-hidden flex">
              @foreach($activeDomains as $d)
              <div style="background:{{ $domainColors[$d] }}; flex-grow:{{ max($headline[$d], 0.001) }};" class="h-full first:rounded-l-full last:rounded-r-full border-r border-white/20 last:border-0"></div>
              @endforeach
            </div>
            <div class="flex flex-wrap gap-x-5 gap-y-2 mt-3.5 text-xs">
              @foreach($activeDomains as $d)
              <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full inline-block" style="background:{{ $domainColors[$d] }}"></span>
                <span class="text-indigo-100 font-semibold">{{ $domainLabels[$d] }}:</span>
                <b class="text-white font-bold">{{ number_format($headline[$d]) }}</b>
              </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      {{-- 4 Domain KPI Cards --}}
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($activeDomains as $d)
        <div class="relative overflow-hidden bg-white border border-slate-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-300 flex flex-col justify-between group">
          <div class="flex items-center justify-between mb-4">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl text-base shadow-sm group-hover:scale-110 transition-transform duration-300" style="background:{{ $domainColors[$d] }}12">{{ $domainIcons[$d] }}</span>
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $domainLabels[$d] }}</span>
          </div>
          <p class="text-3xl font-black text-slate-800 tracking-tight">{{ number_format($headline[$d]) }}</p>
          <p class="text-xs font-semibold text-slate-400 mb-3">Handled Items</p>
          <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500" style="width:{{ round($headline[$d] / $maxHeadline * 100) }}%; background:{{ $domainColors[$d] }}"></div>
          </div>
        </div>
        @endforeach
      </div>

      {{-- Activity Trend Chart --}}
      <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
          <div>
            <h4 class="font-bold text-slate-800 text-base">Activity Performance Trend</h4>
            <p class="text-xs text-slate-400">Daily breakdown over {{ strtolower($periodLabel) }}</p>
          </div>
          <span class="text-xs font-semibold px-3 py-1 rounded-full bg-indigo-50 text-indigo-600 border border-indigo-100/60">
            {{ $periodLabel }}
          </span>
        </div>

        @if(array_sum($trend['data']->all()) > 0)
        <div style="height:270px; position:relative;">
          <canvas id="staffTrendChart"></canvas>
        </div>
        @else
        <div class="flex flex-col items-center justify-center py-12 text-center text-slate-400">
          <span class="text-3xl mb-2">📈</span>
          <p class="text-sm font-medium">No activity recorded for this period.</p>
        </div>
        @endif
      </div>

      {{-- Domain Share Chart --}}
      <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
          <div>
            <h4 class="font-bold text-slate-800 text-base">Domain Activity Distribution</h4>
            <p class="text-xs text-slate-400">Proportional share across active CRM domains</p>
          </div>
          <span class="text-xs font-semibold px-3 py-1 rounded-full bg-slate-100 text-slate-600">
            {{ $periodLabel }}
          </span>
        </div>

        @if($pieTotals->sum() > 0)
        <div class="max-w-xs mx-auto py-2">
          <canvas id="staffActivityChart" height="240"></canvas>
        </div>
        @else
        <div class="flex flex-col items-center justify-center py-12 text-center text-slate-400">
          <span class="text-3xl mb-2">📊</span>
          <p class="text-sm font-medium">No activity recorded for this period.</p>
        </div>
        @endif
      </div>
    </div>

    {{-- SIDEBAR --}}
    <div class="space-y-6">
      <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5 pb-3 border-b border-slate-100">
          <h4 class="font-bold text-slate-800 text-base">Detailed Outcomes</h4>
          <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Key Metrics</span>
        </div>

        <div class="space-y-4">
          @if(in_array('website', $activeDomains))
          <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100/50">
            <div class="flex items-center gap-2 mb-2">
              <span class="flex h-7 w-7 items-center justify-center rounded-xl text-xs font-bold shadow-inner" style="background:{{ $domainColors['website'] }}12">{{ $domainIcons['website'] }}</span>
              <span class="text-[11px] font-bold text-slate-700 uppercase tracking-wider">Website</span>
            </div>
            <div class="space-y-2">
              <div class="flex justify-between items-center text-xs py-1.5 px-3 rounded-xl bg-white border border-slate-100/50">
                <span class="text-slate-500 font-medium">Total Order</span>
                <b class="text-slate-800 font-bold">{{ number_format($summary['website']['crm_sales']) }}</b>
              </div>
              <div class="flex justify-between items-center text-xs py-1.5 px-3 rounded-xl bg-white border border-slate-100/50">
                <span class="text-slate-500 font-medium">Calls answered</span>
                <b class="text-slate-800 font-bold">{{ number_format($summary['website']['calls_answered']) }}</b>
              </div>
            </div>
          </div>
          @endif

          @if(in_array('tech_support', $activeDomains))
          <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100/50">
            <div class="flex items-center gap-2 mb-2">
              <span class="flex h-7 w-7 items-center justify-center rounded-xl text-xs font-bold shadow-inner" style="background:{{ $domainColors['tech_support'] }}12">{{ $domainIcons['tech_support'] }}</span>
              <span class="text-[11px] font-bold text-slate-700 uppercase tracking-wider">Tech Support</span>
            </div>
            <div class="space-y-2">
              <div class="flex justify-between items-center text-xs py-1.5 px-3 rounded-xl bg-white border border-slate-100/50">
                <span class="text-slate-500 font-medium">Cases resolved</span>
                <b class="text-slate-800 font-bold">{{ number_format($summary['tech_support']['resolved']) }}</b>
              </div>
              <div class="flex justify-between items-center text-xs py-1.5 px-3 rounded-xl bg-white border border-slate-100/50">
                <span class="text-slate-500 font-medium">Total Issues</span>
                <b class="text-slate-800 font-bold">{{ number_format($summary['tech_support']['Total Issues'] ?? 0) }}</b>
              </div>
              <div class="flex justify-between items-center text-xs py-1.5 px-3 rounded-xl bg-white border border-slate-100/50">
                <span class="text-slate-500 font-medium">Negative Feedback</span>
                <b class="text-slate-800 font-bold">{{ number_format($summary['tech_support']['Negative Feedback'] ?? 0) }}</b>
              </div>
            </div>
          </div>
          @endif

          @if(in_array('logistic', $activeDomains))
          <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100/50">
            <div class="flex items-center gap-2 mb-2">
              <span class="flex h-7 w-7 items-center justify-center rounded-xl text-xs font-bold shadow-inner" style="background:{{ $domainColors['logistic'] }}12">{{ $domainIcons['logistic'] }}</span>
              <span class="text-[11px] font-bold text-slate-700 uppercase tracking-wider">Logistic</span>
            </div>
            <div class="space-y-2">
              <div class="flex justify-between items-center text-xs py-1.5 px-3 rounded-xl bg-white border border-slate-100/50">
                <span class="text-slate-500 font-medium">Shipments complete</span>
                <b class="text-slate-800 font-bold">{{ number_format($summary['logistic']['complete']) }}</b>
              </div>
              <div class="flex justify-between items-center text-xs py-1.5 px-3 rounded-xl bg-white border border-slate-100/50">
                <span class="text-slate-500 font-medium">Total Issues</span>
                <b class="text-slate-800 font-bold">{{ number_format($summary['logistic']['Total Issues'] ?? 0) }}</b>
              </div>
              <div class="flex justify-between items-center text-xs py-1.5 px-3 rounded-xl bg-white border border-slate-100/50">
                <span class="text-slate-500 font-medium">Negative Feedback</span>
                <b class="text-slate-800 font-bold">{{ number_format($summary['logistic']['Negative Feedback'] ?? 0) }}</b>
              </div>
            </div>
          </div>
          @endif
        </div>
      </div>

      {{-- Export Box --}}
      <div class="bg-gradient-to-br from-slate-900 to-indigo-950 text-white rounded-3xl p-6 shadow-md text-center border border-slate-950/20 relative overflow-hidden">
        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-white/5 rounded-full blur-xl pointer-events-none"></div>
        <div class="w-12 h-12 rounded-2xl bg-white/10 flex items-center justify-center text-2xl mx-auto mb-3 border border-white/15">👤</div>
        <h5 class="font-bold text-base text-white mb-1">Staff Profile Export</h5>
        <p class="text-xs text-slate-300 mb-5">Download {{ $user->name }}'s performance report for {{ $periodLabel }}.</p>
        <div class="flex flex-col gap-2.5">
          <a href="{{ route('crm.reports.show.export.pdf', ['user' => $user] + request()->query() + ['period' => $granularity]) }}" class="btn btn-primary text-xs py-2.5 w-full rounded-xl flex items-center justify-center gap-2 shadow-md">
            <span>📄</span> <span>Download Official PDF</span>
          </a>
          <a href="{{ route('crm.reports.export', ['user' => $user] + request()->query() + ['period' => $granularity]) }}" class="btn btn-secondary text-xs py-2.5 w-full rounded-xl flex items-center justify-center gap-2 bg-white/10 border-white/20 text-white hover:bg-white/20">
            <span>📊</span> <span>Download Raw CSV</span>
          </a>
        </div>
      </div>
    </div>
  </div>
  @endif

</div>
@endsection

@push('scripts')
<script>
async function initStaffReportCharts() {
    if (!window.Chart && window.loadChart) {
        await window.loadChart();
    }
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
                    borderWidth: 2.5,
                    pointRadius: 0,
                    pointHoverRadius: 6,
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
                cutout: '68%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } } },
            },
        });
    }
}

function scheduleStaffReportCharts() {
    setTimeout(() => initStaffReportCharts(), 10);
}

document.addEventListener('turbo:load', scheduleStaffReportCharts);
</script>
@endpush
