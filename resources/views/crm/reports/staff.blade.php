@extends('layouts.app')
@section('title', 'Staff Report')
@section('page_title', 'Staff Report')

@section('content')
<div class="animate-fade-in">

  @php
    $domainColors = ['website' => '#6366f1', 'ebay' => '#f59e0b', 'tech_support' => '#ef4444', 'logistic' => '#10b981'];
    $domainIcons  = ['website' => '🌐', 'ebay' => '🛒', 'tech_support' => '🛠️', 'logistic' => '🚚'];
    $domainLabels = ['website' => 'Website', 'ebay' => 'eBay', 'tech_support' => 'Tech Support', 'logistic' => 'Logistic'];
  @endphp

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
      <form method="GET" action="{{ route('crm.reports.staff') }}" class="flex items-center flex-wrap gap-2">
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
        <a href="{{ route('crm.reports.staff') }}" class="btn btn-secondary text-xs py-1.5 px-3">Clear</a>
        @endif
      </form>
    </div>
  </div>
  <p class="text-sm text-slate-500 mb-5">Every staff member with CRM activity, showing activity for <span class="font-semibold text-slate-700">{{ $periodLabel }}</span>. Click a profile to see their full report.</p>

  @if($members->isEmpty())
  <div class="card p-10 text-center text-slate-400 text-sm">No staff activity recorded for this period yet.</div>
  @else
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($members as $row)
    <a href="{{ route('crm.reports.show', $row['user']) }}" class="card p-4 block hover:shadow-md hover:border-indigo-200 transition-all">
      <div class="flex items-center gap-3 mb-4">
        <img src="{{ $row['user']->avatar_url }}" class="w-10 h-10 rounded-full">
        <div class="min-w-0 flex-1">
          <h4 class="font-semibold text-slate-800 text-sm truncate">{{ $row['user']->name }}</h4>
          <p class="text-xs text-slate-400 truncate">{{ $row['user']->crm_role_display }}</p>
        </div>
        <div class="text-right shrink-0">
          <p class="text-xl font-black text-slate-800 leading-none">{{ $row['totalHandled'] }}</p>
          <p class="text-[10px] text-slate-400 uppercase tracking-wide">Handled</p>
        </div>
      </div>

      {{-- Per-domain share bar, direct-labeled below (never color-alone) --}}
      <div class="flex h-1.5 rounded-full overflow-hidden bg-slate-100">
        @foreach($row['activeDomains'] as $d)
        <div style="background:{{ $domainColors[$d] }}; flex-grow:{{ max($row['headline'][$d], 0.001) }};"></div>
        @endforeach
      </div>
      <div class="flex flex-wrap gap-x-4 gap-y-1 mt-3">
        @foreach($row['activeDomains'] as $d)
        <div class="flex items-center gap-1 text-xs">
          <span>{{ $domainIcons[$d] }}</span>
          <span class="text-slate-500">{{ $domainLabels[$d] }}</span>
          <b class="text-slate-700">{{ $row['headline'][$d] }}</b>
        </div>
        @endforeach
      </div>
    </a>
    @endforeach
  </div>
  @endif

</div>
@endsection
