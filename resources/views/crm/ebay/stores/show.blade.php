@extends('layouts.app')
@section('title', 'Store Profile — ' . $store->store_name)
@section('page_title', 'Store Details')

@section('content')
<div class="animate-fade-in">
  <div class="mb-5 flex justify-between">
    <a href="{{ route('crm.ebay.stores.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Stores</a>
    <div class="flex gap-2">
      <a href="{{ route('crm.ebay.stores.export', $store) }}" class="btn btn-secondary text-sm">Export CSV</a>
      <a href="{{ route('crm.ebay.stores.edit', $store) }}" class="btn btn-secondary text-sm">Edit Store</a>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Left Col: Details --}}
    <div class="lg:col-span-1 space-y-6">
      <div class="card p-6">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h2 class="font-display font-bold text-slate-800 text-xl">{{ $store->store_name }}</h2>
            @if($store->ebay_username)
              <p class="text-sm text-slate-500 font-medium">@{{ $store->ebay_username }}</p>
            @endif
          </div>
          <span class="badge {{ $store->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
            {{ $store->is_active ? 'Active' : 'Inactive' }}
          </span>
        </div>

        @if($store->store_url)
        <div class="mb-5">
          <a href="{{ $store->store_url }}" target="_blank" rel="noopener" class="text-sm text-indigo-600 hover:underline flex items-center gap-1">
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
            Visit Store URL
          </a>
        </div>
        @endif

        <div class="space-y-4">
          <div>
            <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Handled By</span>
            @if($store->handler)
              <div class="flex items-center gap-2">
                <img src="{{ $store->handler->avatar_url }}" class="w-6 h-6 rounded-full" alt="">
                <div>
                  <p class="text-sm font-medium text-slate-800">{{ $store->handler->name }}</p>
                  <p class="text-xs text-slate-500">{{ $store->handler->crm_role_display }}</p>
                </div>
              </div>
            @else
              <span class="text-sm text-slate-500">Unassigned</span>
            @endif
          </div>

          @if($store->notes)
          <div>
            <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Notes</span>
            <div class="text-sm text-slate-600 whitespace-pre-wrap">{{ $store->notes }}</div>
          </div>
          @endif
        </div>
      </div>

      <div class="card p-6 grid grid-cols-2 gap-4">
        <div>
          <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Customers</span>
          <p class="text-2xl font-bold text-slate-800">{{ $store->customer_records_count }}</p>
        </div>
        <div>
          <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Total Sales</span>
          <p class="text-2xl font-bold text-slate-800">${{ number_format($store->total_sales, 2) }}</p>
        </div>
      </div>
    </div>

    {{-- Right Col: Customers/Offers --}}
    <div class="lg:col-span-2">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h3 class="font-display font-bold text-slate-800 text-lg">Customers Purchased From eBay</h3>
        <a href="{{ route('crm.ebay.create', ['store_id' => $store->id]) }}" class="btn btn-primary text-sm">
          + Log Offer
        </a>
      </div>

      <div class="card p-0 overflow-hidden">
        <div class="p-4 border-b border-slate-100 bg-slate-50/50">
          <form method="GET" action="{{ route('crm.ebay.stores.show', $store) }}" class="flex gap-2">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search offers/customers..." class="form-input text-sm flex-1">
            <button type="submit" class="btn btn-secondary text-sm">Search</button>
          </form>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                <th class="px-5 py-3 text-left">Customer</th>
                <th class="px-4 py-3 text-left">Product</th>
                <th class="px-4 py-3 text-left">Offer Amount</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              @forelse($offers as $offer)
              <tr class="hover:bg-slate-50/70 transition-colors">
                <td class="px-5 py-3">
                  <p class="font-semibold text-slate-800">{{ $offer->client_name ?? '(No name)' }}</p>
                  @if($offer->client_email)
                    <p class="text-xs text-slate-500">{{ $offer->client_email }}</p>
                  @endif
                </td>
                <td class="px-4 py-3 text-xs text-slate-600">{{ $offer->product?->name ?? '—' }}</td>
                <td class="px-4 py-3 font-medium text-slate-800">
                  @if($offer->offer_amount)
                    ${{ number_format($offer->offer_amount) }}
                  @else
                    —
                  @endif
                </td>
                <td class="px-4 py-3">
                  <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $offer->status?->color() }}22; color:{{ $offer->status?->color() }}">
                    {{ $offer->status?->label() }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex justify-end gap-1">
                    <a href="{{ route('crm.ebay.show', $offer) }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;">
                      <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    </a>
                  </div>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="5" class="text-center py-10">
                  <p class="text-slate-500 font-medium">No customers found for this store</p>
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if($offers->hasPages())
        <div class="px-6 py-4 border-t border-slate-100">{{ $offers->links() }}</div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
