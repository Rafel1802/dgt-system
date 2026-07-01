@extends('layouts.app')
@section('title', 'eBay CRM — Stores')
@section('page_title', 'eBay CRM — Stores')

@section('content')
<div class="animate-fade-in">

  {{-- ── Toolbar ──────────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex gap-2 flex-wrap">
      @foreach(['' => 'Active', 'all' => 'All', 'inactive' => 'Inactive'] as $val => $lbl)
      <a href="{{ route('crm.ebay.stores.index', array_merge(request()->query(), ['status' => $val])) }}"
         class="btn text-xs py-1.5 px-3 {{ request('status', '') === $val ? 'btn-primary' : 'btn-secondary' }}">
        {{ $lbl }}
      </a>
      @endforeach
    </div>
    <div class="flex gap-2">
      <a href="{{ route('crm.ebay.index') }}" class="btn btn-secondary text-sm" id="btn-ebay-offers">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
        All Offers
      </a>
      <a href="{{ route('crm.ebay.stores.create') }}" class="btn btn-primary text-sm" id="btn-new-store">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        New Store
      </a>
    </div>
  </div>

  {{-- ── Search ────────────────────────────────────────────────────────────── --}}
  <form method="GET" action="{{ route('crm.ebay.stores.index') }}" class="card p-4 mb-5">
    <div class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[200px]">
        <label class="form-label text-xs">Search</label>
        <div class="relative">
          <input type="search" name="search" value="{{ request('search') }}"
                 placeholder="Store name, eBay username…" class="form-input pl-9 py-2 text-sm" id="store-search">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        </div>
      </div>
      <input type="hidden" name="status" value="{{ request('status') }}">
      <div class="flex gap-2">
        <button type="submit" class="btn btn-primary py-2 text-sm">Search</button>
        <a href="{{ route('crm.ebay.stores.index') }}" class="btn btn-secondary py-2 text-sm">Reset</a>
      </div>
    </div>
  </form>

  {{-- ── Stores Grid ──────────────────────────────────────────────────────── --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($stores as $store)
    <div class="card p-5 flex flex-col gap-3 hover:shadow-md transition-shadow">
      <div class="flex items-start justify-between gap-2">
        <div>
          <h3 class="font-semibold text-slate-800 text-base">{{ $store->store_name }}</h3>
          @if($store->ebay_username)
            <p class="text-xs text-slate-400 mt-0.5">@{{ $store->ebay_username }}</p>
          @endif
        </div>
        <span class="badge text-xs px-2 py-0.5 rounded-full {{ $store->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
          {{ $store->is_active ? 'Active' : 'Inactive' }}
        </span>
      </div>

      @if($store->store_url)
        <a href="{{ $store->store_url }}" target="_blank" rel="noopener"
           class="text-xs text-indigo-600 hover:underline truncate">{{ $store->store_url }}</a>
      @endif

      <div class="flex items-center gap-2">
        @if($store->handler)
          <img src="{{ $store->handler->avatar_url }}" class="w-5 h-5 rounded-full" alt="{{ $store->handler->name }}">
          <span class="text-xs text-slate-500">{{ $store->handler->name }}</span>
          <span class="text-xs text-slate-400">· {{ $store->handler->crm_role_display }}</span>
        @else
          <span class="text-xs text-slate-300">Unassigned</span>
        @endif
      </div>

      <div class="mt-auto flex gap-2 pt-2 border-t border-slate-100">
        <a href="{{ route('crm.ebay.stores.show', $store) }}" class="btn btn-secondary btn-sm flex-1 text-center text-xs" id="btn-store-view-{{ $store->id }}">
          View Customers
        </a>
        <a href="{{ route('crm.ebay.stores.edit', $store) }}" class="btn btn-secondary btn-icon" style="width:32px;height:32px;" title="Edit">
          <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
        </a>
      </div>
    </div>
    @empty
    <div class="col-span-full">
      <div class="card p-14 text-center">
        <div class="text-5xl mb-3">🛒</div>
        <p class="text-slate-500 font-medium">No eBay stores found</p>
        <p class="text-slate-400 text-xs mt-1">Create your first store profile to start tracking customers</p>
        <a href="{{ route('crm.ebay.stores.create') }}" class="btn btn-primary text-sm mt-4 inline-flex">+ New Store</a>
      </div>
    </div>
    @endforelse
  </div>

  @if($stores->hasPages())
  <div class="mt-5">{{ $stores->links() }}</div>
  @endif

</div>
@endsection
