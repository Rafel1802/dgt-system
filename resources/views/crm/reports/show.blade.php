@extends('layouts.app')
@section('title', $user->name . ' — Staff Report')
@section('page_title', 'Staff Report')

@section('content')
<div class="animate-fade-in">

  <div class="mb-5 flex items-center justify-between flex-wrap gap-3">
    <a href="{{ route('crm.reports.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Reports</a>
    <a href="{{ route('crm.reports.export', ['user' => $user] + request()->query() + ['period' => $granularity]) }}" class="btn btn-secondary text-sm">⬇ Export CSV</a>
  </div>

  <div class="card mb-5">
    <div class="flex items-center gap-3">
      <img src="{{ $user->avatar_url }}" class="w-12 h-12 rounded-full">
      <div>
        <h2 class="font-display font-bold text-slate-800 text-lg">{{ $user->name }}</h2>
        <p class="text-sm text-slate-500">{{ $user->crm_role_display }}</p>
      </div>
    </div>
  </div>

  {{-- ── Summary, one card per domain this user is active in ─────────────── --}}
  @php $domainColors = ['website' => '#6366f1', 'ebay' => '#f59e0b', 'tech_support' => '#ef4444', 'logistic' => '#10b981']; @endphp
  <div class="flex items-center justify-between mb-3 flex-wrap gap-3">
    <p class="text-sm text-slate-500">Showing activity for <span class="font-semibold text-slate-700">{{ $periodLabel }}</span></p>
    <div class="flex items-center gap-3 flex-wrap">
      <div class="flex gap-1">
        @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
        <a href="{{ route('crm.reports.show', ['user' => $user, 'period' => $key]) }}"
           class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold {{ $granularity === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
          {{ $label }}
        </a>
        @endforeach
      </div>
      <form method="GET" action="{{ route('crm.reports.show', $user) }}" class="flex flex-wrap gap-2 items-end">
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
        <a href="{{ route('crm.reports.show', $user) }}" class="btn btn-secondary text-xs py-1.5 px-3">Clear</a>
        @endif
      </form>
    </div>
  </div>
  @if(count($activeDomains) > 0)
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @if(in_array('website', $activeDomains))
    <div class="card p-4">
      <div class="h-1.5 -mx-4 -mt-4 mb-3 rounded-t-2xl" style="background:{{ $domainColors['website'] }}"></div>
      <p class="text-xs font-semibold uppercase tracking-wide mb-2" style="color:{{ $domainColors['website'] }}">CRM Website</p>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Handled</span><b class="text-slate-800">{{ $summary['website']['crm_handled'] }}</b></div>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Successful leads</span><b class="text-slate-800">{{ $summary['website']['crm_sales'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Calls answered</span><b class="text-slate-800">{{ $summary['website']['calls_answered'] }}</b></div>
    </div>
    @endif
    @if(in_array('ebay', $activeDomains))
    <div class="card p-4">
      <div class="h-1.5 -mx-4 -mt-4 mb-3 rounded-t-2xl" style="background:{{ $domainColors['ebay'] }}"></div>
      <p class="text-xs font-semibold uppercase tracking-wide mb-2" style="color:{{ $domainColors['ebay'] }}">eBay</p>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Handled</span><b class="text-slate-800">{{ $summary['ebay']['ebay_handled'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Neg. feedback solved</span><b class="text-slate-800">{{ $summary['ebay']['neg_solved'] }}</b></div>
    </div>
    @endif
    @if(in_array('tech_support', $activeDomains))
    <div class="card p-4">
      <div class="h-1.5 -mx-4 -mt-4 mb-3 rounded-t-2xl" style="background:{{ $domainColors['tech_support'] }}"></div>
      <p class="text-xs font-semibold uppercase tracking-wide mb-2" style="color:{{ $domainColors['tech_support'] }}">Technical Support</p>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Cases assigned</span><b class="text-slate-800">{{ $summary['tech_support']['assigned'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Cases resolved</span><b class="text-slate-800">{{ $summary['tech_support']['resolved'] }}</b></div>
    </div>
    @endif
    @if(in_array('logistic', $activeDomains))
    <div class="card p-4">
      <div class="h-1.5 -mx-4 -mt-4 mb-3 rounded-t-2xl" style="background:{{ $domainColors['logistic'] }}"></div>
      <p class="text-xs font-semibold uppercase tracking-wide mb-2" style="color:{{ $domainColors['logistic'] }}">Logistic</p>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Shipments assigned</span><b class="text-slate-800">{{ $summary['logistic']['assigned'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Complete</span><b class="text-slate-800">{{ $summary['logistic']['complete'] }}</b></div>
    </div>
    @endif
  </div>
  @else
  <p class="text-sm text-slate-400 mb-6">No staff activity recorded for {{ $user->name }} yet.</p>
  @endif

  {{-- ── Activity Breakdown ───────────────────────────────────────────────── --}}
  @php
    $pieTotals = [
        'website'      => array_sum($chart['datasets']['website']),
        'ebay'         => array_sum($chart['datasets']['ebay']),
        'tech_support' => array_sum($chart['datasets']['tech_support']),
        'logistic'     => array_sum($chart['datasets']['logistic']),
    ];
  @endphp
  <div class="card p-4">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
      <h4 class="font-semibold text-slate-700 text-sm">Activity Breakdown by Domain</h4>
      <span class="text-xs text-slate-400">{{ $periodLabel }}</span>
    </div>
    @if(array_sum($pieTotals) > 0)
    <div class="max-w-sm mx-auto">
      <canvas id="staffActivityChart" height="260"></canvas>
    </div>
    @else
    <p class="text-sm text-slate-400 text-center py-10">No activity in this period.</p>
    @endif
  </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart) return;

    const el = document.getElementById('staffActivityChart');
    if (!el) return;

    new Chart(el, {
        type: 'pie',
        data: {
            labels: ['CRM Website', 'eBay', 'Technical Support', 'Logistic'],
            datasets: [{
                data: @json(array_values($pieTotals)),
                backgroundColor: @json(array_values($domainColors)),
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
        },
    });
});
</script>
@endpush
