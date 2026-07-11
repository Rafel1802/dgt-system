@extends('layouts.app')
@section('title', 'Staff Report')
@section('page_title', 'Staff Report')

@section('content')
<div class="animate-fade-in">

  @php $domainColors = ['website' => '#6366f1', 'ebay' => '#f59e0b', 'tech_support' => '#ef4444', 'logistic' => '#10b981']; @endphp

  {{-- ── Page switcher + period filter ────────────────────────────────────── --}}
  <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div class="flex gap-2">
      <a href="{{ route('crm.reports.index') }}" class="btn btn-secondary text-sm">📊 Team Report</a>
      <a href="{{ route('crm.reports.staff') }}" class="btn btn-primary text-sm">👤 Staff Report</a>
    </div>
    <div class="flex items-center gap-3 flex-wrap">
      <div class="flex gap-1">
        @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
        <a href="{{ route('crm.reports.staff', ['period' => $key]) }}"
           class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold {{ $granularity === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
          {{ $label }}
        </a>
        @endforeach
      </div>
      <form method="GET" action="{{ route('crm.reports.staff') }}" class="flex flex-wrap gap-2 items-end">
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
        <a href="{{ route('crm.reports.staff') }}" class="btn btn-secondary text-xs py-1.5 px-3">Clear</a>
        @endif
      </form>
    </div>
  </div>
  <p class="text-sm text-slate-500 mb-5">Individual staff profiles, grouped by team. Showing activity for <span class="font-semibold text-slate-700">{{ $periodLabel }}</span>. Click a profile to see their full report.</p>

  @foreach($teams as $key => $team)
  <div class="flex items-center justify-between mb-3 {{ !$loop->first ? 'mt-8' : '' }}">
    <h3 class="font-display font-bold text-slate-800 text-lg flex items-center gap-2">
      <span class="w-2.5 h-2.5 rounded-full inline-block" style="background:{{ $domainColors[$key] }}"></span>
      {{ $team['label'] }} <span class="text-slate-400 text-sm font-normal">({{ $periodLabel }})</span>
    </h3>
  </div>

  @if($team['members']->isEmpty())
  <div class="card p-6 text-center text-slate-400 text-sm">No staff activity recorded for this team yet.</div>
  @else
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    @foreach($team['members'] as $row)
    <a href="{{ route('crm.reports.show', $row['user']) }}" class="card p-4 block hover:shadow-md hover:border-indigo-200 transition-all">
      <div class="h-1.5 -mx-4 -mt-4 mb-3 rounded-t-2xl" style="background:{{ $domainColors[$key] }}"></div>
      <div class="flex items-center gap-2 mb-3">
        <img src="{{ $row['user']->avatar_url }}" class="w-6 h-6 rounded-full">
        <h4 class="font-semibold text-slate-800 text-sm">{{ $row['user']->name }}</h4>
        <svg class="w-3.5 h-3.5 text-slate-300 ml-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
      </div>

      @if($key === 'website')
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Customers handled</span><b class="text-slate-800">{{ $row['crm_handled'] }}</b></div>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Successful leads</span><b class="text-slate-800">{{ $row['crm_sales'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Calls answered</span><b class="text-slate-800">{{ $row['calls_answered'] }}</b></div>
      @elseif($key === 'ebay')
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Customers handled</span><b class="text-slate-800">{{ $row['ebay_handled'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Neg. feedback solved</span><b class="text-slate-800">{{ $row['neg_solved'] }}</b></div>
      @elseif($key === 'tech_support')
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Cases assigned</span><b class="text-slate-800">{{ $row['assigned'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Cases resolved</span><b class="text-slate-800">{{ $row['resolved'] }}</b></div>
      @elseif($key === 'logistic')
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Shipments assigned</span><b class="text-slate-800">{{ $row['assigned'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Complete</span><b class="text-slate-800">{{ $row['complete'] }}</b></div>
      @endif
    </a>
    @endforeach
  </div>
  @endif
  @endforeach

</div>
@endsection
