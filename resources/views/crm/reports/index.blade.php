@extends('layouts.app')
@section('title', 'Team Report')
@section('page_title', 'Executive Team Performance')
@section('hide_back', true)

@section('content')
<div id="crm-reports-page" class="animate-fade-in space-y-6" x-data="{ reportTab: '{{ $activeTab }}' }">

  @if(session('share_url'))
  <div class="rounded-2xl bg-indigo-50/90 backdrop-blur border border-indigo-100 text-indigo-900 px-5 py-4 text-sm font-medium flex items-center justify-between gap-4 flex-wrap shadow-sm">
    <div class="flex items-center gap-2.5">
      <span class="text-lg">🔗</span>
      <span><strong>Public Share Link Ready</strong> — anyone with this secure link can view live report data without logging in:</span>
    </div>
    <div class="flex items-center gap-2">
      <input id="share-url-input" type="text" readonly value="{{ session('share_url') }}" class="form-input text-xs py-1.5 px-3 w-72 bg-white rounded-lg border-indigo-200" onclick="this.select()">
      <button type="button" class="btn btn-primary text-xs py-1.5 px-3 rounded-lg shadow-sm" onclick="navigator.clipboard.writeText(document.getElementById('share-url-input').value)">Copy Link</button>
    </div>
  </div>
  @endif

  {{-- ── Header Control Bar & Period Filters ──────────────────────────────── --}}
  <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex items-center justify-between gap-4 flex-wrap">
    {{-- Page switcher --}}
    <div class="inline-flex p-1 bg-slate-100 rounded-2xl border border-slate-200/30 shadow-sm shadow-slate-100/50">
      <a href="{{ route('crm.reports.index') }}" class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 bg-white text-slate-800 shadow-sm border border-slate-200/40 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        Team Report
      </a>
      <a href="{{ route('crm.reports.staff') }}" class="px-5 py-2.5 rounded-xl text-xs font-semibold text-slate-500 hover:text-slate-800 transition-all duration-200 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        Staff Report
      </a>
    </div>

    {{-- Period Filter & Actions --}}
    <div class="flex items-center gap-4 flex-wrap">
      <div x-show="reportTab === 'general'" x-cloak class="flex items-center gap-4 flex-wrap">
        <div class="inline-flex p-1 bg-slate-100 rounded-2xl border border-slate-200/30">
          @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
          @php $isActive = ($granularity === $key); @endphp
          <a href="{{ route('crm.reports.index', ['period' => $key]) }}" data-turbo="false"
             class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-all duration-200 {{ $isActive ? 'bg-white text-slate-800 shadow-sm border border-slate-200/40' : 'text-slate-500 hover:text-slate-800 hover:bg-white/40 border border-transparent' }}">
            {{ $label }}
          </a>
          @endforeach
        </div>

        <form method="GET" action="{{ route('crm.reports.index') }}" autocomplete="off" data-turbo="false" class="flex items-center gap-2 flex-wrap">
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
          <a href="{{ route('crm.reports.index') }}" data-turbo="false" class="px-4 py-2 text-xs font-bold text-rose-600 bg-white border border-rose-100 rounded-xl hover:bg-rose-50/50 shadow-sm transition-all">Clear</a>
          @endif
        </form>
      </div>

      <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

      {{-- Export Buttons --}}
      <div class="flex items-center gap-2">
        <form method="POST" action="{{ route('crm.reports.share') }}" data-turbo="false">
          @csrf
          <input type="hidden" name="date_from" value="{{ request('date_from') }}">
          <input type="hidden" name="date_to" value="{{ request('date_to') }}">
          <input type="hidden" name="period" value="{{ $granularity }}">
          <button type="submit" class="px-3.5 py-2 text-xs font-semibold rounded-xl border border-slate-200 hover:border-indigo-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-indigo-600 shadow-sm transition-all flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            <span>Share Link</span>
          </button>
        </form>
        <a :href="'{{ route('crm.reports.export.pdf', collect(request()->query())->except(['tab'])->merge(['period' => $granularity])->all()) }}' + (reportTab !== 'general' ? '&tab=' + reportTab : '')" class="px-3.5 py-2 text-xs font-semibold rounded-xl border border-slate-200 hover:border-indigo-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-indigo-600 shadow-sm transition-all flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          <span>Export PDF</span>
        </a>
        <a :href="'{{ route('crm.reports.export.csv', collect(request()->query())->except(['tab'])->merge(['period' => $granularity])->all()) }}' + (reportTab !== 'general' ? '&tab=' + reportTab : '')" class="px-3.5 py-2 text-xs font-semibold rounded-xl border border-slate-200 hover:border-indigo-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-indigo-600 shadow-sm transition-all flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          <span>Export CSV</span>
        </a>
      </div>
    </div>
  </div>

  {{-- ── Domain Tabs Bar ─────────────────────────────────────────────────── --}}
  <div class="flex gap-2 flex-wrap items-center bg-slate-100/60 p-1.5 rounded-2xl w-fit border border-slate-200/30 shadow-sm shadow-slate-100/50">
    <button type="button" @click="reportTab = 'general'; $nextTick(() => initReportChart('general'))"
            class="px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 flex items-center gap-2 border border-transparent"
            :class="reportTab === 'general' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-500 hover:text-slate-800 hover:bg-white/40'">
      📋 General Executive Report
    </button>
    @foreach($domainReports as $domainKey => $domain)
    <button type="button" @click="reportTab = '{{ $domainKey }}'; $nextTick(() => initReportChart('{{ $domainKey }}'))"
            class="px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 flex items-center gap-2 border border-transparent"
            :class="reportTab === '{{ $domainKey }}' ? 'text-white shadow-md' : 'text-slate-500 hover:text-slate-800 hover:bg-white/40'"
            :style="reportTab === '{{ $domainKey }}' ? 'background:{{ $domain['color'] }}' : ''">
      <span>{{ $domain['icon'] }}</span>
      <span>{{ $domain['label'] }}</span>
    </button>
    @endforeach
  </div>

  @php
    $ebaySales = (float) ($domainReports['ebay']['metrics']['Sales'] ?? 0);
    $websiteSales = (float) ($domainReports['website']['metrics']['Sales'] ?? 0);
    $headline = collect($domainReports)->map(fn ($d) => reset($d['metrics']));
    $maxHeadline = $headline->max() ?: 1;
    $totalSalesSafe = max($totalSales, 0.01);
    $websitePct = round(($websiteSales / $totalSalesSafe) * 100, 1);
    $ebayPct = round(($ebaySales / $totalSalesSafe) * 100, 1);
  @endphp

  {{-- ── General Executive Report Tab ────────────────────────────────────── --}}
  <div x-show="reportTab === 'general'" x-cloak>
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

      {{-- MAIN COLUMN --}}
      <div class="xl:col-span-2 space-y-6">

        {{-- Hero Revenue Card --}}
        <div class="relative overflow-hidden rounded-3xl shadow-md text-white border border-indigo-950/10" style="background:radial-gradient(circle at 70% 30%, #4338ca 0%, #312e81 100%);">
          <div class="absolute -right-10 -top-10 w-48 h-48 bg-white/5 rounded-full blur-2xl pointer-events-none"></div>
          <div class="p-8 relative z-10">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-extrabold uppercase tracking-widest text-indigo-200 bg-white/10 backdrop-blur px-3.5 py-1.5 rounded-xl border border-white/10">
                Company Total Revenue
              </span>
              <span class="text-xs font-semibold text-indigo-200 bg-black/20 backdrop-blur-md px-3.5 py-1.5 rounded-xl border border-white/5">
                {{ $periodLabel }}
              </span>
            </div>
            
            <div class="flex items-baseline gap-2 my-4">
              <span class="text-5xl font-black tracking-tight text-white">${{ number_format($totalSales, 2) }}</span>
              <span class="text-sm font-semibold text-indigo-200 uppercase">USD</span>
            </div>

            {{-- Distribution Bar --}}
            <div class="mt-6">
              <div class="flex justify-between text-xs font-semibold text-indigo-100 mb-2">
                <span>Revenue Channel Distribution</span>
                <span>Website {{ $websitePct }}% &nbsp;·&nbsp; eBay {{ $ebayPct }}%</span>
              </div>
              <div class="h-3 w-full bg-black/20 rounded-full p-0.5 overflow-hidden flex">
                <div style="width:{{ max($websitePct, 2) }}%; background:{{ $domainReports['website']['color'] }};" class="h-full rounded-l-full transition-all duration-500"></div>
                <div style="width:{{ max($ebayPct, 2) }}%; background:{{ $domainReports['ebay']['color'] }};" class="h-full rounded-r-full transition-all duration-500"></div>
              </div>
            </div>

            {{-- Breakdown Pills --}}
            <div class="grid grid-cols-2 gap-4 mt-8 pt-6 border-t border-white/10">
              <div class="flex items-center gap-3 bg-white/5 backdrop-blur p-4 rounded-2xl border border-white/15">
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center text-lg border border-purple-400/20">🌐</div>
                <div>
                  <div class="text-[11px] text-indigo-200 font-bold uppercase tracking-wider">Website Sales</div>
                  <div class="text-lg font-black text-white">${{ number_format($websiteSales, 2) }}</div>
                </div>
              </div>

              <div class="flex items-center gap-3 bg-white/5 backdrop-blur p-4 rounded-2xl border border-white/15">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-lg border border-amber-400/20">🛒</div>
                <div>
                  <div class="text-[11px] text-indigo-200 font-bold uppercase tracking-wider">eBay Sales</div>
                  <div class="text-lg font-black text-white">${{ number_format($ebaySales, 2) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Domain KPI Grid --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          @foreach($domainReports as $domainKey => $domain)
          <div class="relative overflow-hidden bg-white border border-slate-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-300 flex flex-col justify-between group">
            <div class="flex items-center justify-between mb-4">
              <span class="flex h-10 w-10 items-center justify-center rounded-xl text-base shadow-sm group-hover:scale-110 transition-transform duration-300" style="background:{{ $domain['color'] }}12">{{ $domain['icon'] }}</span>
              <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $domain['label'] }}</span>
            </div>
            <p class="text-3xl font-black text-slate-800 tracking-tight">{{ number_format($headline[$domainKey]) }}</p>
            <p class="text-xs font-semibold text-slate-400 mb-3 truncate">{{ array_key_first($domain['metrics']) }}</p>
            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
              <div class="h-full rounded-full transition-all duration-500" style="width:{{ round($headline[$domainKey] / $maxHeadline * 100) }}%; background:{{ $domain['color'] }}"></div>
            </div>
          </div>
          @endforeach
        </div>

        {{-- Activity Trend Chart --}}
        <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
          <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
              <h4 class="font-bold text-slate-800 text-base">Company Activity Trend</h4>
              <p class="text-xs text-slate-400">Activity performance over {{ strtolower($periodLabel) }}</p>
            </div>
            <span class="text-xs font-semibold px-3 py-1 rounded-full bg-indigo-50 text-indigo-600 border border-indigo-100/60">
              {{ $periodLabel }}
            </span>
          </div>

          @if(array_sum($trend['data']->all()) > 0)
          <div style="height:280px; position:relative;">
            <canvas id="teamTrendChart"></canvas>
          </div>
          @else
          <div class="flex flex-col items-center justify-center py-12 text-center text-slate-400">
            <span class="text-3xl mb-2">📈</span>
            <p class="text-sm font-medium">No activity recorded for this period.</p>
          </div>
          @endif
        </div>
      </div>

      {{-- SIDEBAR --}}
      <div class="space-y-6">
        <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
          <div class="flex items-center justify-between mb-5 pb-3 border-b border-slate-100">
            <h4 class="font-bold text-slate-800 text-base">Domain Detailed Metrics</h4>
            <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Breakdown</span>
          </div>

          <div class="space-y-5">
            @foreach($domainReports as $domainKey => $domain)
            @php $rest = collect($domain['metrics'])->except(array_key_first($domain['metrics'])); @endphp
            @if($rest->isNotEmpty())
            <div class="{{ !$loop->first ? 'pt-4 border-t border-slate-100' : '' }}">
              <div class="flex items-center gap-2 mb-3">
                <span class="flex h-7 w-7 items-center justify-center rounded-xl text-xs font-bold shadow-inner" style="background:{{ $domain['color'] }}12">{{ $domain['icon'] }}</span>
                <span class="text-[11px] font-bold text-slate-700 uppercase tracking-wider">{{ $domain['label'] }}</span>
              </div>
              <div class="space-y-2">
                @foreach($rest as $metricLabel => $value)
                <div class="flex justify-between items-center text-xs py-1.5 px-3 rounded-xl bg-slate-50 border border-slate-100/50">
                  <span class="text-slate-500 font-medium">{{ $metricLabel }}</span>
                  <b class="text-slate-800 font-bold">{{ in_array($metricLabel, $domain['money_keys']) ? '$' . number_format($value, 2) : number_format($value) }}</b>
                </div>
                @endforeach
              </div>
            </div>
            @endif
            @endforeach
          </div>
        </div>

        {{-- Export Action Box --}}
        <div class="bg-gradient-to-br from-slate-900 to-indigo-950 text-white rounded-3xl p-6 shadow-md text-center border border-slate-950/20 relative overflow-hidden">
          <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-white/5 rounded-full blur-xl pointer-events-none"></div>
          <div class="w-12 h-12 rounded-2xl bg-white/10 flex items-center justify-center text-2xl mx-auto mb-3 border border-white/15">📑</div>
          <h5 class="font-bold text-base text-white mb-1" x-text="reportTab === 'general' ? 'Executive Report Export' : (reportTab === 'ebay' ? 'eBay' : reportTab === 'tech_support' ? 'Technical Support' : reportTab.charAt(0).toUpperCase() + reportTab.slice(1)) + ' Report Export'">Executive Report Export</h5>
          <p class="text-xs text-slate-300 mb-5">Download official PDF or CSV report for {{ $periodLabel }}.</p>
          <div class="flex flex-col gap-2.5">
            <a :href="'{{ route('crm.reports.export.pdf', collect(request()->query())->except(['tab'])->merge(['period' => $granularity])->all()) }}' + (reportTab !== 'general' ? '&tab=' + reportTab : '')" class="btn btn-primary text-xs py-2.5 w-full rounded-xl flex items-center justify-center gap-2 shadow-md">
              <span>📄</span> <span>Download Official PDF</span>
            </a>
            <a :href="'{{ route('crm.reports.export.csv', collect(request()->query())->except(['tab'])->merge(['period' => $granularity])->all()) }}' + (reportTab !== 'general' ? '&tab=' + reportTab : '')" class="btn btn-secondary text-xs py-2.5 w-full rounded-xl flex items-center justify-center gap-2 bg-white/10 border-white/20 text-white hover:bg-white/20">
              <span>📊</span> <span>Download Raw CSV</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ── Domain Individual Tabs ─────────────────────────────────────────── --}}
  @foreach($domainTabReports as $domainKey => $domain)
  @php
    $countMax = collect($domain['metrics'])->except($domain['money_keys'])->max() ?: 1;
    $domainHeadlineLabel = array_key_first($domain['metrics']);
    $domainHeadlineValue = reset($domain['metrics']);
    $dp = $domainPeriods[$domainKey];
    $domainOtherQuery = collect(request()->query())->except(["{$domainKey}_period", "{$domainKey}_date_from", "{$domainKey}_date_to", 'tab'])->all();
  @endphp
  <div x-show="reportTab === '{{ $domainKey }}'" x-cloak class="space-y-6">
    <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm flex items-center justify-between gap-4 flex-wrap">
      <div class="flex items-center gap-3 flex-wrap">
        <div class="inline-flex p-1 bg-slate-100 rounded-2xl border border-slate-200/30">
          @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $pKey => $pLabel)
          @php $isActive = ($dp['granularity'] === $pKey); @endphp
          <a href="{{ route('crm.reports.index', array_merge($domainOtherQuery, ['tab' => $domainKey, "{$domainKey}_period" => $pKey])) }}" data-turbo="false"
             class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-all duration-200 {{ $isActive ? 'text-white shadow-sm shadow-slate-200' : 'text-slate-500 hover:text-slate-800' }}"
             style="{{ $isActive ? 'background:' . $domain['color'] : '' }}">
            {{ $pLabel }}
          </a>
          @endforeach
        </div>
        <form method="GET" action="{{ route('crm.reports.index') }}" autocomplete="off" data-turbo="false" class="flex items-center gap-2 flex-wrap">
          @foreach($domainOtherQuery as $qKey => $qVal)
            @foreach((array) $qVal as $qv)
            <input type="hidden" name="{{ is_array($qVal) ? $qKey . '[]' : $qKey }}" value="{{ $qv }}">
            @endforeach
          @endforeach
          <input type="hidden" name="tab" value="{{ $domainKey }}">
          <div class="flex items-center gap-2 pl-3 border-l border-slate-100">
            <div class="flex items-center gap-2 bg-slate-50 border border-slate-200/80 rounded-xl px-3 py-1.5 focus-within:bg-white focus-within:ring-2 focus-within:ring-indigo-500/10 focus-within:border-indigo-500 transition-all">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">From</span>
              <input type="date" name="{{ $domainKey }}_date_from" value="{{ request("{$domainKey}_date_from") }}" autocomplete="off" class="bg-transparent border-0 text-xs p-0 focus:ring-0 text-slate-700 font-semibold w-28">
            </div>
            <span class="text-xs text-slate-400">to</span>
            <div class="flex items-center gap-2 bg-slate-50 border border-slate-200/80 rounded-xl px-3 py-1.5 focus-within:bg-white focus-within:ring-2 focus-within:ring-indigo-500/10 focus-within:border-indigo-500 transition-all">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">To</span>
              <input type="date" name="{{ $domainKey }}_date_to" value="{{ request("{$domainKey}_date_to") }}" autocomplete="off" class="bg-transparent border-0 text-xs p-0 focus:ring-0 text-slate-700 font-semibold w-28">
            </div>
          </div>
          <button type="submit" class="px-4 py-2 text-xs font-bold text-white border rounded-xl hover:bg-slate-50/50 transition-all shadow-sm" style="background:{{ $domain['color'] }}; border-color:{{ $domain['color'] }}">Filter</button>
          @if(request("{$domainKey}_date_from") || request("{$domainKey}_date_to"))
          <a href="{{ route('crm.reports.index', array_merge($domainOtherQuery, ['tab' => $domainKey])) }}" data-turbo="false" class="px-4 py-2 text-xs font-bold text-rose-600 bg-white border border-rose-100 rounded-xl hover:bg-rose-50/50 shadow-sm transition-all">Clear</a>
          @endif
        </form>
      </div>
    </div>

    <div class="rounded-3xl p-8 text-white shadow-md relative overflow-hidden" style="background:radial-gradient(circle at 70% 30%, {{ $domain['color'] }} 0%, {{ $domain['color'] }}cc 100%)">
      <div class="absolute -right-10 -top-10 w-48 h-48 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
      <p class="text-xs font-extrabold uppercase tracking-widest text-white/90 mb-2">{{ $domain['icon'] }} {{ $domain['label'] }} — {{ $domainHeadlineLabel }}</p>
      <div class="text-5xl font-black text-white mb-2">{{ in_array($domainHeadlineLabel, $domain['money_keys']) ? '$' . number_format($domainHeadlineValue, 2) : number_format($domainHeadlineValue) }}</div>
      <p class="text-white/80 text-xs font-medium">{{ $dp['label'] }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      @foreach($domain['metrics'] as $metricLabel => $value)
      <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-300 group">
        <div class="flex items-center gap-2.5 mb-3">
          <span class="flex h-9 w-9 items-center justify-center rounded-xl text-sm shadow-inner group-hover:scale-115 transition-transform duration-300" style="background:{{ $domain['color'] }}12">{{ $domain['icon'] }}</span>
          <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">{{ $metricLabel }}</span>
        </div>
        <p class="text-3xl font-black" style="color:{{ $domain['color'] }}">
          {{ in_array($metricLabel, $domain['money_keys']) ? '$' . number_format($value, 2) : number_format($value) }}
        </p>
        @unless(in_array($metricLabel, $domain['money_keys']))
        <div class="h-2 rounded-full bg-slate-100 overflow-hidden mt-3">
          <div class="h-full rounded-full transition-all duration-500" style="width:{{ round($value / $countMax * 100) }}%; background:{{ $domain['color'] }}"></div>
        </div>
        @endunless
      </div>
      @endforeach
    </div>

    <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
      <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <h4 class="font-bold text-slate-800 text-base">{{ $domain['label'] }} Activity Trend</h4>
        <span class="text-xs font-semibold px-3 py-1 rounded-full bg-slate-100 text-slate-600">{{ $dp['label'] }}</span>
      </div>
      @if(array_sum($domainTabTrends[$domainKey]['data']->all()) > 0)
      <div style="height:250px; position:relative;">
        <canvas id="domainTrendChart-{{ $domainKey }}"></canvas>
      </div>
      @else
      <div class="flex flex-col items-center justify-center py-12 text-center text-slate-400">
        <span class="text-3xl mb-2">📈</span>
        <p class="text-sm font-medium">No activity recorded for this period.</p>
      </div>
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
(function () {
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
                    borderWidth: 2.5,
                    pointRadius: 0,
                    pointHoverRadius: 6,
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

    window.initReportChart = async function (key) {
        if (!window.Chart && window.loadChart) {
            await window.loadChart();
        }
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

    const activeTabKey = @json($activeTab);
    
    // Bind to turbo:load for seamless Hotwire navigation
    document.addEventListener('turbo:load', function () {
        window.initReportChart(activeTabKey);
    });
})();
</script>
@endpush
