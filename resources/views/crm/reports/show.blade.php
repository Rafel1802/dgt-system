@extends('layouts.app')
@section('title', $user->name . ' — Staff Report')
@section('page_title', 'Staff Report')

@section('content')
<div class="animate-fade-in">

  <div class="mb-5 flex items-center justify-between flex-wrap gap-3">
    <a href="{{ route('crm.reports.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Reports</a>
    <a href="{{ route('crm.reports.export', ['user' => $user, 'period' => $granularity]) }}" class="btn btn-secondary text-sm">⬇ Export CSV</a>
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

  {{-- ── This-month summary across all 4 domains ────────────────────────── --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card p-4">
      <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">CRM Website</p>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Handled / mo</span><b class="text-slate-800">{{ $summary['website']['crm_handled'] }}</b></div>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Successful leads</span><b class="text-slate-800">{{ $summary['website']['crm_sales'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Calls answered / mo</span><b class="text-slate-800">{{ $summary['website']['calls_answered'] }}</b></div>
    </div>
    <div class="card p-4">
      <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">eBay</p>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Handled</span><b class="text-slate-800">{{ $summary['ebay']['ebay_handled'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Neg. feedback solved</span><b class="text-slate-800">{{ $summary['ebay']['neg_solved'] }}</b></div>
    </div>
    <div class="card p-4">
      <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Technical Support</p>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Cases assigned</span><b class="text-slate-800">{{ $summary['tech_support']['assigned'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Cases resolved</span><b class="text-slate-800">{{ $summary['tech_support']['resolved'] }}</b></div>
    </div>
    <div class="card p-4">
      <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Logistic</p>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Shipments assigned</span><b class="text-slate-800">{{ $summary['logistic']['assigned'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Complete</span><b class="text-slate-800">{{ $summary['logistic']['complete'] }}</b></div>
    </div>
  </div>

  {{-- ── Activity Chart ───────────────────────────────────────────────────── --}}
  <div class="card p-4">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
      <h4 class="font-semibold text-slate-700 text-sm">All Reports — Activity Over Time</h4>
      <div class="flex gap-1">
        @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
        <a href="{{ route('crm.reports.show', ['user' => $user, 'period' => $key]) }}"
           class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold {{ $granularity === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
          {{ $label }}
        </a>
        @endforeach
      </div>
    </div>
    <canvas id="staffActivityChart" height="90"></canvas>
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
        type: 'bar',
        data: {
            labels: @json($chart['labels']),
            datasets: [
                { label: 'CRM Website', data: @json($chart['datasets']['website']), backgroundColor: '#6366f1' },
                { label: 'eBay', data: @json($chart['datasets']['ebay']), backgroundColor: '#f59e0b' },
                { label: 'Technical Support', data: @json($chart['datasets']['tech_support']), backgroundColor: '#ef4444' },
                { label: 'Logistic', data: @json($chart['datasets']['logistic']), backgroundColor: '#10b981' },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                x: { stacked: true, ticks: { autoSkip: false, maxRotation: 60, minRotation: 30 } },
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
            },
        },
    });
});
</script>
@endpush
