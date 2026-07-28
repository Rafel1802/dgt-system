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
              <p class="text-sm text-slate-500 font-medium">{{ '@' . $store->ebay_username }}</p>
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
          <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Total Orders</span>
          <p class="text-2xl font-bold text-slate-800">{{ $totalOrders }}</p>
        </div>
        <div class="col-span-2">
          <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Total Sales</span>
          <p class="text-2xl font-bold text-slate-800 break-words">${{ number_format($totalSales, 2) }}</p>
        </div>
      </div>
    </div>

    {{-- Right Col: Orders --}}
    <div class="lg:col-span-2">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h3 class="font-display font-bold text-slate-800 text-lg">Clients Who Ordered From This Store</h3>
      </div>

      <div class="card p-0 overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30">
          <form method="GET" action="{{ route('crm.ebay.stores.show', $store) }}" class="flex gap-2">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search order ID / customer..." class="form-input text-sm flex-1">
            <button type="submit" class="btn btn-secondary text-sm">Search</button>
          </form>
        </div>

        <div class="p-4 space-y-4">
          @forelse($orders as $order)
          <div class="border border-slate-100 rounded-xl p-4">
            <div class="flex items-start justify-between flex-wrap gap-2 mb-3">
              <div>
                @if($order->record)
                <a href="{{ route('crm.ebay.customers.show', $order->record) }}" class="text-base font-bold text-slate-800 hover:text-indigo-600 transition-colors">
                  {{ $order->record->buyer_name ?: $order->record->username }}
                </a>
                @else
                <span class="text-base font-bold text-slate-800">Unknown Customer</span>
                @endif
                <p class="font-mono text-xs text-slate-400 mt-0.5">Order #{{ $order->order_id }}</p>
              </div>
              <span class="text-xs text-slate-400 shrink-0">{{ $order->ordered_at?->format('d M Y') }}</span>
            </div>
            <div class="divide-y divide-slate-50">
              @forelse($order->items as $item)
              <div class="flex items-center justify-between py-1.5 text-sm">
                <span class="text-slate-700">
                  <span class="text-[10px] text-slate-400 uppercase font-semibold tracking-wide mr-1.5">SKU</span>{{ $item->product_name }}
                </span>
                <span class="text-slate-500">{{ $item->price !== null ? '$'.number_format($item->price, 2) : '—' }}</span>
              </div>
              @empty
              <p class="text-slate-400 text-sm py-1.5">No products logged for this order.</p>
              @endforelse
            </div>
          </div>
          @empty
          <p class="text-slate-500 font-medium text-center py-10">No orders found for this store</p>
          @endforelse
        </div>
        @if($orders->hasPages())
        <div class="px-6 py-4 border-t border-slate-100">{{ $orders->links() }}</div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
