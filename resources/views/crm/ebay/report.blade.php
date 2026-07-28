@extends('layouts.app')
@section('title', 'eBay CRM — General Report')
@section('page_title', 'eBay CRM — General Report')

@section('content')
<div class="animate-fade-in">

  <div class="mb-5">
    <a href="{{ route('crm.ebay.stores.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Stores</a>
  </div>

  {{-- ── Period filter + export ────────────────────────────────────────────── --}}
  <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div class="flex items-center gap-3 flex-wrap">
      <div class="flex gap-1">
        @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
        <a href="{{ route('crm.ebay.report', ['period' => $key]) }}"
           class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold {{ $granularity === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
          {{ $label }}
        </a>
        @endforeach
      </div>
      <form method="GET" action="{{ route('crm.ebay.report') }}" class="flex flex-wrap gap-2 items-end">
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
        <a href="{{ route('crm.ebay.report') }}" class="btn btn-secondary text-xs py-1.5 px-3">Clear</a>
        @endif
      </form>
    </div>
    <div class="flex gap-2">
      <a href="{{ route('crm.ebay.report.export.pdf', request()->query()) }}" class="btn btn-secondary text-xs py-1.5 px-3">📄 Export PDF</a>
      <a href="{{ route('crm.ebay.report.export.csv', request()->query()) }}" class="btn btn-secondary text-xs py-1.5 px-3">📊 Export CSV</a>
    </div>
  </div>

  <p class="text-sm text-slate-500 mb-5">Showing activity for <span class="font-semibold text-slate-700">{{ $periodLabel }}</span>.</p>

  {{-- ── Summary tiles ─────────────────────────────────────────────────────── --}}
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="card p-4 text-center">
      <div class="text-2xl font-bold text-red-600">{{ $negTotal }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Negative Feedback</div>
    </div>
    <div class="card p-4 text-center">
      <div class="text-2xl font-bold text-emerald-600">{{ $negSolved }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Solved Negative Feedback</div>
    </div>
    <div class="card p-4 text-center">
      <div class="text-2xl font-bold text-indigo-700">{{ $totalOrders }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Total Orders (all stores)</div>
    </div>
    <div class="card p-4 text-center">
      <div class="text-2xl font-bold text-indigo-700">${{ number_format($totalSales, 2) }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Total Sales (all stores)</div>
    </div>
  </div>

  {{-- ── Per-store breakdown ───────────────────────────────────────────────── --}}
  <div class="card p-0 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100">
      <p class="text-sm font-semibold text-slate-700">Sales & Orders by Store</p>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left">Store</th>
            <th class="px-4 py-3 text-right">Orders</th>
            <th class="px-4 py-3 text-right">Sales</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($storeReports as $row)
          <tr class="hover:bg-slate-50/70 transition-colors">
            <td class="px-5 py-3">
              <a href="{{ route('crm.ebay.stores.show', $row['store']) }}" class="font-semibold text-slate-800 hover:text-indigo-600 transition-colors">
                {{ $row['store']->store_name }}
              </a>
            </td>
            <td class="px-4 py-3 text-right text-slate-600">{{ $row['orders'] }}</td>
            <td class="px-4 py-3 text-right font-semibold text-indigo-700">${{ number_format($row['sales'], 2) }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="3" class="text-center py-14">
              <p class="text-slate-500 font-medium">No stores found</p>
            </td>
          </tr>
          @endforelse
        </tbody>
        @if($storeReports->isNotEmpty())
        <tfoot>
          <tr class="bg-slate-50 border-t-2 border-slate-200 font-bold text-slate-800">
            <td class="px-5 py-3">Total</td>
            <td class="px-4 py-3 text-right">{{ $totalOrders }}</td>
            <td class="px-4 py-3 text-right text-indigo-700">${{ number_format($totalSales, 2) }}</td>
          </tr>
        </tfoot>
        @endif
      </table>
    </div>
  </div>

</div>
@endsection
