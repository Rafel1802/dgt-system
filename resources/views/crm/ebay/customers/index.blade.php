@extends('layouts.app')
@section('title', 'eBay — Manage Customer')
@section('page_title', 'eBay Manage Customer')

@push('styles')
<style>
.tab-btn {
    padding: 0.45rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
    border: none;
    background: transparent;
}
.tab-btn.active {
    background: #6366f1;
    color: #fff;
    box-shadow: 0 2px 8px rgba(99,102,241,0.25);
}
.tab-btn:not(.active) {
    color: #64748b;
    background: #f1f5f9;
}
.tab-btn:not(.active):hover {
    background: #e2e8f0;
    color: #3730a3;
}
</style>
@endpush

@section('content')
<div class="animate-fade-in">

  {{-- ── Status Filter (buttons, same UI as before) ─────────────────────── --}}
  <div class="flex gap-2 flex-wrap mb-5 overflow-x-auto pb-1">
    <a href="{{ route('crm.ebay.customers.index', array_merge(request()->except('tab_type'), ['tab_type' => ''])) }}"
       class="tab-btn {{ ! $tabType ? 'active' : '' }}" id="tab-all">
      All
    </a>
    @foreach($tabs as $key => $label)
    <a href="{{ route('crm.ebay.customers.index', array_merge(request()->except('tab_type'), ['tab_type' => $key])) }}"
       class="tab-btn {{ $tabType === $key ? 'active' : '' }}" id="tab-{{ $key }}">
      {{ $label }}
      @php $cnt = \App\Models\EbayCustomerRecord::forTab($key)->count(); @endphp
      @if($cnt > 0)
        <span class="ml-1 text-xs {{ $tabType === $key ? 'opacity-70' : 'text-indigo-500' }}">({{ $cnt }})</span>
      @endif
    </a>
    @endforeach
  </div>

  {{-- ── Toolbar ───────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h2 class="font-bold text-slate-800 text-base">{{ $tabType ? $tabs[$tabType] : 'All Records' }}</h2>
    <a href="{{ route('crm.ebay.customers.create', $tabType ? ['tab_type' => $tabType] : []) }}"
       class="btn btn-primary text-sm" id="btn-new-ebay-customer">
      <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      + New Record
    </a>
  </div>

  {{-- ── Flash ─────────────────────────────────────────────────────────── --}}
  @if(session('success'))
  <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm font-medium">
    {{ session('success') }}
  </div>
  @endif

  {{-- ── Search & Store Filter ───────────────────────────────────────────── --}}
  <form method="GET" action="{{ route('crm.ebay.customers.index') }}" class="card p-4 mb-4" x-data>
    <input type="hidden" name="tab_type" value="{{ $tabType }}">
    <div class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[180px]">
        <label class="form-label text-xs">Search</label>
        <input type="search" name="search" value="{{ request('search') }}"
               @input.debounce.500ms="$el.closest('form').submit()"
               placeholder="Username, buyer, order ID…" class="form-input py-2 text-sm">
      </div>
      <div class="min-w-[180px]">
        <label class="form-label text-xs">eBay Store</label>
        <select name="store_id" class="form-input py-2 text-sm" @change="$el.closest('form').submit()">
          <option value="">All Stores</option>
          @foreach($stores as $store)
            <option value="{{ $store->id }}" {{ request('store_id') == $store->id ? 'selected' : '' }}>
              {{ $store->store_name }}
            </option>
          @endforeach
        </select>
      </div>
      @if(request('search') || request('store_id'))
      <a href="{{ route('crm.ebay.customers.index', ['tab_type' => $tabType]) }}" class="btn btn-secondary text-sm py-2">Clear Filters</a>
      @endif
    </div>
  </form>

  {{-- ── Table ─────────────────────────────────────────────────────────── --}}
  <div class="card p-0 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Customer</th>
            <th class="px-4 py-3 text-left">eBay Store</th>
            <th class="px-4 py-3 text-left">Order ID</th>
            <th class="px-4 py-3 text-left">Summary</th>
            <th class="px-4 py-3 text-left">Date</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($records as $record)
          <tr class="hover:bg-slate-50/70 transition-colors">
            <td class="px-4 py-3">
              @php $tabColor = \App\Models\EbayCustomerRecord::tabColor($record->tab_type); @endphp
              <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $tabColor }}22; color:{{ $tabColor }}">
                {{ $tabs[$record->tab_type] ?? $record->tab_type }}
              </span>
              @if($record->techSupportCase?->occurrence_label)
                <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full block mt-1 w-fit bg-amber-50 text-amber-700" title="Repeat technical issue">
                  🔁 {{ $record->techSupportCase->occurrence_label }}
                </span>
              @endif
              @if($record->shipment_delay)
                <span class="badge text-xs px-2 py-0.5 rounded-full block mt-1 w-fit"
                      style="background:{{ \App\Models\EbayCustomerRecord::LOGISTIC_ISSUES_COLOR }}22; color:{{ \App\Models\EbayCustomerRecord::LOGISTIC_ISSUES_COLOR }}">
                  ⚠ Logistic Issues
                </span>
              @elseif($record->shipment_delivered)
                <span class="badge text-xs px-2 py-0.5 rounded-full block mt-1 w-fit"
                      style="background:{{ \App\Models\EbayCustomerRecord::DELIVERED_COLOR }}22; color:{{ \App\Models\EbayCustomerRecord::DELIVERED_COLOR }}">
                  ✅ Delivered
                </span>
              @endif
            </td>
            <td class="px-4 py-3">
              <p class="font-medium text-slate-800">{{ $record->buyer_name ?: $record->username ?: '—' }}</p>
              @if($record->username && $record->buyer_name)
                <p class="text-xs text-slate-400">@{{ $record->username }}</p>
              @endif
              @if($record->email)
                <p class="text-xs text-slate-400">{{ $record->email }}</p>
              @endif
            </td>
            <td class="px-4 py-3 text-xs text-indigo-600">{{ $record->store?->store_name ?? '—' }}</td>
            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $record->order_id ?? '—' }}</td>
            <td class="px-4 py-3 text-xs text-slate-600 max-w-[220px] truncate" title="{{ $record->summary }}">{{ Str::limit($record->summary, 60) ?: '—' }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ ($record->date ?? $record->order_date)?->format('d/m/Y') ?? '—' }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1 justify-end">
                <a href="{{ route('crm.ebay.customers.show', $record) }}"
                   class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="View">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                </a>
                <a href="{{ route('crm.ebay.customers.edit', $record) }}"
                   class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="Edit">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                </a>
                @if(auth()->user()->canDeleteCrmRecords('ebay'))
                @php
                  $ebayDeleteConfirmMsg = $record->customer
                      ? 'This record is linked to "' . $record->customer->name . '" — deleting it will PERMANENTLY delete that customer and everything tied to them across every CRM domain (leads, other eBay records, shipments, tech support cases). This cannot be undone.'
                      : 'Delete this record?';
                @endphp
                <form method="POST" action="{{ route('crm.ebay.customers.destroy', $record) }}"
                      onsubmit="return confirm({{ \Illuminate\Support\Js::from($ebayDeleteConfirmMsg) }})" class="inline">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-secondary btn-icon text-red-400 hover:text-red-600"
                          style="width:28px;height:28px;" title="Delete">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                  </button>
                </form>
                @endif
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center py-14">
              <div class="text-4xl mb-3">📋</div>
              <p class="text-slate-500 font-medium">No records found</p>
              <a href="{{ route('crm.ebay.customers.create', $tabType ? ['tab_type' => $tabType] : []) }}"
                 class="btn btn-primary text-sm mt-4 inline-flex">+ New Record</a>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($records->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">{{ $records->links() }}</div>
    @endif
  </div>

</div>
@endsection
