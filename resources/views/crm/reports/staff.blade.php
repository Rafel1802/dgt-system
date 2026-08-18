@extends('layouts.app')
@section('title', 'Staff Report')
@section('page_title', 'Staff Activity Overview')

@section('content')
<div id="crm-staff-reports-page" class="animate-fade-in space-y-6">

  @php
    $domainColors = ['website' => '#6366f1', 'ebay' => '#f59e0b', 'tech_support' => '#ef4444', 'logistic' => '#10b981'];
    $domainIcons  = ['website' => '🌐', 'ebay' => '🛒', 'tech_support' => '🛠️', 'logistic' => '🚚'];
    $domainLabels = ['website' => 'Website', 'ebay' => 'eBay', 'tech_support' => 'Tech Support', 'logistic' => 'Logistic'];
  @endphp

  {{-- ── Control Bar & Period Filters ────────────────────────────────────── --}}
  <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex items-center justify-between gap-4 flex-wrap">
    <div class="inline-flex p-1 bg-slate-100 rounded-2xl border border-slate-200/30 shadow-sm shadow-slate-100/50">
      <a href="{{ route('crm.reports.index') }}" class="px-5 py-2.5 rounded-xl text-xs font-semibold text-slate-500 hover:text-slate-800 transition-all duration-200 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        Team Report
      </a>
      <a href="{{ route('crm.reports.staff') }}" class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 bg-white text-slate-800 shadow-sm border border-slate-200/40 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        Staff Report
      </a>
    </div>

    <div class="flex items-center gap-4 flex-wrap">
      <div class="inline-flex p-1 bg-slate-100 rounded-2xl border border-slate-200/30">
        @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
        @php $isActive = ($granularity === $key); @endphp
        <a href="{{ route('crm.reports.staff', ['period' => $key]) }}"
           class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-all duration-200 {{ $isActive ? 'bg-white text-slate-800 shadow-sm border border-slate-200/40' : 'text-slate-500 hover:text-slate-800 hover:bg-white/40 border border-transparent' }}">
          {{ $label }}
        </a>
        @endforeach
      </div>

      <form method="GET" action="{{ route('crm.reports.staff') }}" autocomplete="off" class="flex items-center gap-2 flex-wrap">
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
        <a href="{{ route('crm.reports.staff') }}" class="px-4 py-2 text-xs font-bold text-rose-600 bg-white border border-rose-100 rounded-xl hover:bg-rose-50/50 shadow-sm transition-all">Clear</a>
        @endif
      </form>
    </div>
  </div>

  <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider px-1">Showing staff activity metrics for <span class="text-indigo-600 font-bold">{{ $periodLabel }}</span>. Click any team member below to view their detailed performance.</p>

  @if($members->isEmpty())
  <div class="bg-white rounded-2xl p-12 text-center border border-slate-100 shadow-sm text-slate-400">
    <span class="text-3xl block mb-2">👤</span>
    <p class="text-sm font-medium">No staff activity recorded for this period yet.</p>
  </div>
  @else
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
    @foreach($members as $row)
    <a href="{{ route('crm.reports.show', $row['user']) }}" class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-300 block group">
      <div class="flex items-center gap-3.5 mb-4">
        <img src="{{ $row['user']->avatar_url }}" class="w-12 h-12 rounded-2xl ring-4 ring-slate-50 object-cover group-hover:scale-105 transition-transform duration-300">
        <div class="min-w-0 flex-1">
          <h4 class="font-bold text-slate-800 text-sm group-hover:text-indigo-600 transition-colors truncate">{{ $row['user']->name }}</h4>
          <p class="text-xs text-slate-400 truncate font-semibold uppercase tracking-wider mt-0.5">{{ $row['user']->crm_role_display }}</p>
        </div>
        <div class="text-right shrink-0 bg-indigo-50/40 px-3 py-2 rounded-xl border border-indigo-100/50">
          <p class="text-xl font-black text-indigo-600 leading-none">{{ number_format($row['totalHandled']) }}</p>
          <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-1">Handled</p>
        </div>
      </div>

      <div class="flex h-2 rounded-full overflow-hidden bg-slate-100">
        @foreach($row['activeDomains'] as $d)
        <div style="background:{{ $domainColors[$d] }}; flex-grow:{{ max($row['headline'][$d], 0.001) }};" class="h-full"></div>
        @endforeach
      </div>

      <div class="flex flex-wrap gap-x-4 gap-y-1.5 mt-4 pt-3.5 border-t border-slate-100">
        @foreach($row['activeDomains'] as $d)
        <div class="flex items-center gap-1.5 text-xs">
          <span>{{ $domainIcons[$d] }}</span>
          <span class="text-slate-400 font-semibold">{{ $domainLabels[$d] }}:</span>
          <b class="text-slate-800 font-black">{{ number_format($row['headline'][$d]) }}</b>
        </div>
        @endforeach
      </div>
    </a>
    @endforeach
  </div>
  @endif

</div>
@endsection
