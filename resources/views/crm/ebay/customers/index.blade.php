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

  {{-- ── Tabs ──────────────────────────────────────────────────────────── --}}
  <div class="flex gap-2 flex-wrap mb-5 overflow-x-auto pb-1">
    @foreach($tabs as $key => $label)
    <a href="{{ route('crm.ebay.customers.index', $key) }}"
       class="tab-btn {{ $tab === $key ? 'active' : '' }}" id="tab-{{ $key }}">
      {{ $label }}
      @php $cnt = \App\Models\EbayCustomerRecord::forTab($key)->count(); @endphp
      @if($cnt > 0)
        <span class="ml-1 text-xs {{ $tab === $key ? 'opacity-70' : 'text-indigo-500' }}">({{ $cnt }})</span>
      @endif
    </a>
    @endforeach
  </div>

  {{-- ── Toolbar ───────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
      <h2 class="font-bold text-slate-800 text-base">{{ $tabs[$tab] }}</h2>
    </div>
    <a href="{{ route('crm.ebay.customers.create', $tab) }}"
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
  <form method="GET" action="{{ route('crm.ebay.customers.index', $tab) }}" class="card p-4 mb-4" x-data>
    <div class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[180px]">
        <label class="form-label text-xs">Search</label>
        <input type="search" name="search" value="{{ request('search') }}"
               @input.debounce.500ms="$el.closest('form').submit()"
               placeholder="Username, buyer, order ID…" class="form-input py-2 text-sm">
      </div>
      @if(in_array('ebay_store_id', $columns))
      <div>
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
      @endif
    </div>
  </form>

  {{-- ── Table ─────────────────────────────────────────────────────────── --}}
  <div class="card p-0 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            {{-- Dynamic headers based on tab --}}
            @if(in_array('n', $columns))          <th class="px-4 py-3 text-left">N</th>@endif
            @if(in_array('date', $columns))        <th class="px-4 py-3 text-left">Date</th>@endif
            @if(in_array('order_date', $columns))  <th class="px-4 py-3 text-left">Order Date</th>@endif
            @if(in_array('username', $columns))    <th class="px-4 py-3 text-left">Username</th>@endif
            @if(in_array('buyer_name', $columns))  <th class="px-4 py-3 text-left">Full Name</th>@endif
            @if(in_array('informations', $columns))<th class="px-4 py-3 text-left">Informations</th>@endif
            @if(in_array('email', $columns))       <th class="px-4 py-3 text-left">Email</th>@endif
            @if(in_array('ebay_store_id', $columns))<th class="px-4 py-3 text-left">eBay Store</th>@endif
            @if(in_array('order_id', $columns))    <th class="px-4 py-3 text-left">Order ID</th>@endif
            @if(in_array('sku_number', $columns))  <th class="px-4 py-3 text-left">SKU Number</th>@endif
            @if(in_array('summary', $columns))     <th class="px-4 py-3 text-left">Summary</th>@endif
            @if(in_array('attention_required', $columns)) <th class="px-4 py-3 text-left">Attention Required</th>@endif
            @if(in_array('required_attentions', $columns))<th class="px-4 py-3 text-left">Required Attentions</th>@endif
            @if(in_array('updates', $columns))     <th class="px-4 py-3 text-left">Updates</th>@endif
            @if(in_array('status', $columns))      <th class="px-4 py-3 text-left">Status</th>@endif
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($records as $record)
          <tr class="hover:bg-slate-50/70 transition-colors">
            @if(in_array('n', $columns))          <td class="px-4 py-3 text-xs font-mono text-slate-500">{{ $record->n }}</td>@endif
            @if(in_array('date', $columns))        <td class="px-4 py-3 text-xs text-slate-500">{{ $record->date?->format('d/m/Y') ?? '—' }}</td>@endif
            @if(in_array('order_date', $columns))  <td class="px-4 py-3 text-xs text-slate-500">{{ $record->order_date?->format('d/m/Y') ?? '—' }}</td>@endif
            @if(in_array('username', $columns))    <td class="px-4 py-3 font-medium text-slate-800">{{ $record->username ?? '—' }}</td>@endif
            @if(in_array('buyer_name', $columns))  <td class="px-4 py-3 text-slate-700">{{ $record->buyer_name ?? '—' }}</td>@endif
            @if(in_array('informations', $columns))<td class="px-4 py-3 text-xs text-slate-600 max-w-[200px] truncate" title="{{ $record->informations }}">{{ Str::limit($record->informations, 60) }}</td>@endif
            @if(in_array('email', $columns))       <td class="px-4 py-3 text-xs text-slate-500">{{ $record->email ?? '—' }}</td>@endif
            @if(in_array('ebay_store_id', $columns))<td class="px-4 py-3 text-xs text-indigo-600">{{ $record->store?->store_name ?? '—' }}</td>@endif
            @if(in_array('order_id', $columns))    <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $record->order_id ?? '—' }}</td>@endif
            @if(in_array('sku_number', $columns))  <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $record->sku_number ?? '—' }}</td>@endif
            @if(in_array('summary', $columns))     <td class="px-4 py-3 text-xs text-slate-600 max-w-[180px] truncate" title="{{ $record->summary }}">{{ Str::limit($record->summary, 50) }}</td>@endif
            @if(in_array('attention_required', $columns)) <td class="px-4 py-3 text-xs text-orange-600 max-w-[180px] truncate" title="{{ $record->attention_required }}">{{ Str::limit($record->attention_required, 50) }}</td>@endif
            @if(in_array('required_attentions', $columns))<td class="px-4 py-3 text-xs text-orange-600 max-w-[180px] truncate" title="{{ $record->required_attentions }}">{{ Str::limit($record->required_attentions, 50) }}</td>@endif
            @if(in_array('updates', $columns))     <td class="px-4 py-3 text-xs text-slate-500 max-w-[180px] truncate" title="{{ $record->updates }}">{{ Str::limit($record->updates, 50) }}</td>@endif
            @if(in_array('status', $columns))
              <td class="px-4 py-3">
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full
                  {{ $record->status === 'open' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500' }}">
                  {{ ucfirst($record->status ?? 'open') }}
                </span>
              </td>
            @endif
            <td class="px-4 py-3">
              <div class="flex items-center gap-1 justify-end">
                <a href="{{ route('crm.ebay.customers.edit', [$tab, $record]) }}"
                   class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="Edit">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                </a>
                <form method="POST" action="{{ route('crm.ebay.customers.destroy', [$tab, $record]) }}"
                      onsubmit="return confirm('Delete this record?')" class="inline">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-secondary btn-icon text-red-400 hover:text-red-600"
                          style="width:28px;height:28px;" title="Delete">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="20" class="text-center py-14">
              <div class="text-4xl mb-3">📋</div>
              <p class="text-slate-500 font-medium">No records in {{ $tabs[$tab] }}</p>
              <a href="{{ route('crm.ebay.customers.create', $tab) }}"
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
