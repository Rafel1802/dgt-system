@extends('layouts.app')
@section('title', 'Staff Report')
@section('page_title', 'Staff Activity Overview')

@section('content')
<div class="animate-fade-in space-y-6">

  @php
    $domainColors = ['website' => '#6366f1', 'ebay' => '#f59e0b', 'tech_support' => '#ef4444', 'logistic' => '#10b981'];
    $domainIcons  = ['website' => '🌐', 'ebay' => '🛒', 'tech_support' => '🛠️', 'logistic' => '🚚'];
    $domainLabels = ['website' => 'Website', 'ebay' => 'eBay', 'tech_support' => 'Tech Support', 'logistic' => 'Logistic'];
  @endphp

  {{-- ── Control Bar & Period Filters ────────────────────────────────────── --}}
  <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center justify-between gap-4 flex-wrap">
    <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-900/60 rounded-xl border border-slate-200/60 dark:border-slate-700/50">
      <a href="{{ route('crm.reports.index') }}" class="px-4 py-2 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        Team Report
      </a>
      <a href="{{ route('crm.reports.staff') }}" class="px-4 py-2 rounded-lg text-xs font-bold transition-all bg-indigo-600 text-white shadow-sm flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        Staff Report
      </a>
    </div>

    <div class="flex items-center gap-3 flex-wrap">
      <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-900/60 rounded-xl border border-slate-200/60 dark:border-slate-700/50">
        @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
        <a href="{{ route('crm.reports.staff', ['period' => $key]) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $granularity === $key ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}">
          {{ $label }}
        </a>
        @endforeach
      </div>

      <form method="GET" action="{{ route('crm.reports.staff') }}" class="flex items-center gap-2 flex-wrap">
        <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-900/40 px-3 py-1 rounded-xl border border-slate-200 dark:border-slate-700">
          <span class="text-xs font-bold text-slate-400 uppercase">From</span>
          <input type="date" name="date_from" value="{{ request('date_from') }}" class="bg-transparent border-0 text-xs py-1 px-1 focus:ring-0 text-slate-700 dark:text-slate-200 font-medium">
        </div>
        <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-900/40 px-3 py-1 rounded-xl border border-slate-200 dark:border-slate-700">
          <span class="text-xs font-bold text-slate-400 uppercase">To</span>
          <input type="date" name="date_to" value="{{ request('date_to') }}" class="bg-transparent border-0 text-xs py-1 px-1 focus:ring-0 text-slate-700 dark:text-slate-200 font-medium">
        </div>
        <button type="submit" class="btn btn-secondary text-xs py-2 px-3 rounded-xl">Filter</button>
        @if(request('date_from') || request('date_to'))
        <a href="{{ route('crm.reports.staff') }}" class="btn btn-secondary text-xs py-2 px-3 rounded-xl text-rose-600 hover:bg-rose-50">Clear</a>
        @endif
      </form>
    </div>
  </div>

  <p class="text-xs text-slate-500 font-medium px-1">Showing staff activity metrics for <span class="font-bold text-slate-700 dark:text-slate-300">{{ $periodLabel }}</span>. Select any team member profile below to view full domain activity performance.</p>

  @if($members->isEmpty())
  <div class="bg-white dark:bg-slate-800 rounded-2xl p-12 text-center border border-slate-200 dark:border-slate-700 shadow-sm text-slate-400">
    <span class="text-3xl block mb-2">👤</span>
    <p class="text-sm font-medium">No staff activity recorded for this period yet.</p>
  </div>
  @else
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
    @foreach($members as $row)
    <a href="{{ route('crm.reports.show', $row['user']) }}" class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-700/80 shadow-sm hover:-translate-y-1 hover:shadow-md transition-all duration-200 block group">
      <div class="flex items-center gap-3.5 mb-4">
        <img src="{{ $row['user']->avatar_url }}" class="w-12 h-12 rounded-2xl ring-2 ring-indigo-50 dark:ring-indigo-900/50 object-cover">
        <div class="min-w-0 flex-1">
          <h4 class="font-bold text-slate-800 dark:text-white text-sm group-hover:text-indigo-600 transition-colors truncate">{{ $row['user']->name }}</h4>
          <p class="text-xs text-slate-400 truncate font-medium">{{ $row['user']->crm_role_display }}</p>
        </div>
        <div class="text-right shrink-0 bg-indigo-50 dark:bg-indigo-950/40 px-3 py-1.5 rounded-xl border border-indigo-100 dark:border-indigo-900/50">
          <p class="text-xl font-black text-indigo-600 dark:text-indigo-400 leading-none">{{ number_format($row['totalHandled']) }}</p>
          <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Handled</p>
        </div>
      </div>

      <div class="flex h-2 rounded-full overflow-hidden bg-slate-100 dark:bg-slate-700">
        @foreach($row['activeDomains'] as $d)
        <div style="background:{{ $domainColors[$d] }}; flex-grow:{{ max($row['headline'][$d], 0.001) }};" class="h-full"></div>
        @endforeach
      </div>

      <div class="flex flex-wrap gap-x-4 gap-y-1.5 mt-3.5 pt-3 border-t border-slate-100 dark:border-slate-700/60">
        @foreach($row['activeDomains'] as $d)
        <div class="flex items-center gap-1.5 text-xs">
          <span>{{ $domainIcons[$d] }}</span>
          <span class="text-slate-500 dark:text-slate-400 font-medium">{{ $domainLabels[$d] }}:</span>
          <b class="text-slate-800 dark:text-slate-200 font-bold">{{ number_format($row['headline'][$d]) }}</b>
        </div>
        @endforeach
      </div>
    </a>
    @endforeach
  </div>
  @endif

</div>
@endsection
