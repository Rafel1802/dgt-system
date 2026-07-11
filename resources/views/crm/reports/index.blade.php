@extends('layouts.app')
@section('title', 'Team Report')
@section('page_title', 'Team Report')

@section('content')
<div class="animate-fade-in" x-data="{ reportTab: 'general' }">

  {{-- ── Page switcher + period filter ────────────────────────────────────── --}}
  <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div class="flex gap-2">
      <a href="{{ route('crm.reports.index') }}" class="btn btn-primary text-sm">📊 Team Report</a>
      <a href="{{ route('crm.reports.staff') }}" class="btn btn-secondary text-sm">👤 Staff Report</a>
    </div>
    <div class="flex items-center gap-3 flex-wrap">
      <div class="flex gap-1">
        @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
        <a href="{{ route('crm.reports.index', ['period' => $key]) }}"
           class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold {{ $granularity === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
          {{ $label }}
        </a>
        @endforeach
      </div>
      <form method="GET" action="{{ route('crm.reports.index') }}" class="flex flex-wrap gap-2 items-end">
        <div>
          <label class="form-label text-xs">From</label>
          <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input text-sm py-1.5">
        </div>
        <div>
          <label class="form-label text-xs">To</label>
          <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input text-sm py-1.5">
        </div>
        <button type="submit" class="btn btn-secondary text-xs py-1.5 px-3">Filter</button>
        @if(request('date_from') || request('date_to'))
        <a href="{{ route('crm.reports.index') }}" class="btn btn-secondary text-xs py-1.5 px-3">Clear</a>
        @endif
      </form>
      <div class="flex gap-2">
        <a href="{{ route('crm.reports.export.pdf', request()->query() + ['period' => $granularity]) }}" class="btn btn-secondary text-xs py-1.5 px-3">📄 Export PDF</a>
        <a href="{{ route('crm.reports.export.csv', request()->query() + ['period' => $granularity]) }}" class="btn btn-secondary text-xs py-1.5 px-3">📊 Export CSV</a>
      </div>
    </div>
  </div>
  <p class="text-sm text-slate-500 mb-5">Company-wide totals only — no individual staff data. Showing activity for <span class="font-semibold text-slate-700">{{ $periodLabel }}</span>.</p>

  {{-- ── Domain tabs — pick exactly one report to look at, so nothing is mixed together ─── --}}
  <div class="flex gap-1 flex-wrap mb-5">
    <button type="button" @click="reportTab = 'general'"
            class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold" :class="reportTab === 'general' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'">
      📋 General Report
    </button>
    @foreach($domainReports as $domainKey => $domain)
    <button type="button" @click="reportTab = '{{ $domainKey }}'"
            class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold" :class="reportTab === '{{ $domainKey }}' ? 'text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
            :style="reportTab === '{{ $domainKey }}' ? 'background:{{ $domain['color'] }}' : ''">
      {{ $domain['icon'] }} {{ $domain['label'] }}
    </button>
    @endforeach
  </div>

  {{-- General Report — every domain's numbers, each clearly grouped under its own heading, plus the combined sales total --}}
  <div x-show="reportTab === 'general'" x-cloak>
    <div class="card p-4 mb-5" style="background:linear-gradient(135deg,#4338ca,#6366f1)">
      <p class="text-xs font-semibold text-indigo-100 uppercase tracking-wide mb-1">Total Sales (eBay + Website)</p>
      <div class="text-3xl font-bold text-white">${{ number_format($totalSales, 2) }}</div>
    </div>

    @foreach($domainReports as $domainKey => $domain)
    <div class="mb-3 {{ !$loop->first ? 'mt-6' : '' }} flex items-center gap-2">
      <span class="w-2.5 h-2.5 rounded-full inline-block" style="background:{{ $domain['color'] }}"></span>
      <h4 class="font-display font-bold text-slate-800 text-sm">{{ $domain['icon'] }} {{ $domain['label'] }}</h4>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-2">
      @foreach($domain['metrics'] as $metricLabel => $value)
      <div class="card p-4 text-center">
        <div class="h-1.5 -mx-4 -mt-4 mb-3 rounded-t-2xl" style="background:{{ $domain['color'] }}"></div>
        <div class="text-2xl font-bold" style="color:{{ $domain['color'] }}">
          {{ in_array($metricLabel, $domain['money_keys']) ? '$' . number_format($value, 2) : $value }}
        </div>
        <div class="text-xs text-slate-500 mt-0.5">{{ $metricLabel }}</div>
      </div>
      @endforeach
    </div>
    @endforeach
  </div>

  {{-- One profile-style section per domain --}}
  @foreach($domainReports as $domainKey => $domain)
  <div x-show="reportTab === '{{ $domainKey }}'" x-cloak>
    <div class="card p-4 mb-4" style="background:linear-gradient(135deg,{{ $domain['color'] }},{{ $domain['color'] }}cc)">
      <p class="text-xs font-semibold text-white/80 uppercase tracking-wide mb-1">{{ $domain['icon'] }} {{ $domain['label'] }} — {{ $periodLabel }}</p>
      <div class="text-2xl font-bold text-white">{{ $domain['label'] }} Report</div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      @foreach($domain['metrics'] as $metricLabel => $value)
      <div class="card p-4 text-center">
        <div class="text-2xl font-bold" style="color:{{ $domain['color'] }}">
          {{ in_array($metricLabel, $domain['money_keys']) ? '$' . number_format($value, 2) : $value }}
        </div>
        <div class="text-xs text-slate-500 mt-0.5">{{ $metricLabel }}</div>
      </div>
      @endforeach
    </div>
  </div>
  @endforeach

</div>
@endsection
