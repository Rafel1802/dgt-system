@extends('layouts.app')
@section('title', 'Logistics')
@section('page_title', 'Logistic CRM')

@section('content')
<div class="animate-fade-in">

  {{-- Toolbar --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex gap-2 flex-wrap">
      @php
        $statusFilters = [''=>'All','order_confirmed'=>'📋 Confirmed','client_verified'=>'✅ Verified','truck_searching'=>'🔍 Truck Needed','in_transit'=>'🛣️ In Transit','delivered'=>'🎉 Delivered','problem'=>'⚠️ Problem'];
      @endphp
      @foreach($statusFilters as $val => $lbl)
      <a href="{{ route('crm.logistics.index', array_merge(request()->query(), ['status' => $val])) }}"
         class="btn text-xs py-1.5 px-3 {{ request('status') === $val ? 'btn-primary' : 'btn-secondary' }}">{{ $lbl }}</a>
      @endforeach
    </div>
    <a href="{{ route('crm.logistics.create') }}" class="btn btn-primary text-sm" id="btn-new-shipment">
      <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      New Shipment
    </a>
  </div>

  {{-- Search --}}
  <form method="GET" action="{{ route('crm.logistics.index') }}" class="card p-4 mb-5">
    <div class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[200px]">
        <label class="form-label text-xs">Search</label>
        <div class="relative">
          <input type="search" name="search" value="{{ request('search') }}"
                 placeholder="Order ID, tracking number, recipient…" class="form-input pl-9 py-2 text-sm">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        </div>
      </div>
      <input type="hidden" name="status" value="{{ request('status') }}">
      <div class="flex gap-2">
        <button type="submit" class="btn btn-primary py-2 text-sm">Search</button>
        <a href="{{ route('crm.logistics.index') }}" class="btn btn-secondary py-2 text-sm">Reset</a>
      </div>
    </div>
  </form>

  {{-- Table --}}
  <div class="card p-0 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left">Order / Customer</th>
            <th class="px-4 py-3 text-left">Product</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Tracking</th>
            <th class="px-4 py-3 text-left">Driver</th>
            <th class="px-4 py-3 text-left">ETA</th>
            <th class="px-4 py-3 text-left">Cost</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($logistics as $ship)
          @php $overdue = $ship->estimated_arrival?->isPast() && !$ship->status?->isTerminal(); @endphp
          <tr class="hover:bg-slate-50/70 transition-colors {{ $overdue ? 'bg-red-50/30' : '' }}">
            <td class="px-5 py-3">
              <p class="font-semibold text-slate-800">{{ $ship->order_id ?? '#'.$ship->id }}</p>
              <p class="text-xs text-slate-400">{{ $ship->customer?->name }}</p>
              <p class="text-xs text-slate-400">📍 {{ Str::limit($ship->shipping_address, 40) }}</p>
            </td>
            <td class="px-4 py-3 text-xs text-slate-600">
              {{ $ship->product?->name ?? Str::limit($ship->product_description, 30) ?? '—' }}
            </td>
            <td class="px-4 py-3">
              <span class="flex items-center gap-1 text-xs font-semibold"
                    style="color:{{ $ship->status?->color() }}">
                {{ $ship->status?->icon() }} {{ $ship->status?->label() }}
              </span>
            </td>
            <td class="px-4 py-3">
              @if($ship->tracking_number)
                <span class="text-xs font-mono text-slate-700 bg-slate-100 px-2 py-0.5 rounded">{{ $ship->tracking_number }}</span>
              @else
                <span class="text-slate-300 text-xs">Not yet</span>
              @endif
            </td>
            <td class="px-4 py-3">
              @if($ship->driver_name)
                <p class="text-xs text-slate-700">{{ $ship->driver_name }}</p>
                @if($ship->driver_phone)
                  <a href="tel:{{ $ship->driver_phone }}" class="text-xs text-slate-400 hover:text-indigo-600">{{ $ship->driver_phone }}</a>
                @endif
              @else
                <span class="text-slate-300 text-xs">Not assigned</span>
              @endif
            </td>
            <td class="px-4 py-3">
              @if($ship->estimated_arrival)
                <span class="text-xs {{ $overdue ? 'text-red-600 font-bold' : 'text-slate-600' }}">
                  {{ $overdue ? '⚠️ ' : '' }}{{ $ship->estimated_arrival->format('d M Y') }}
                </span>
              @else
                <span class="text-slate-300 text-xs">TBD</span>
              @endif
            </td>
            <td class="px-4 py-3">
              @if($ship->final_shipping_cost)
                <p class="text-xs font-semibold text-slate-800">${{ number_format($ship->final_shipping_cost) }}</p>
              @elseif($ship->shipping_budget)
                <p class="text-xs text-slate-400">Budget: ${{ number_format($ship->shipping_budget) }}</p>
              @endif
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-1 justify-end">
                <a href="{{ route('crm.logistics.show', $ship) }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                </a>
                <a href="{{ route('crm.logistics.edit', $ship) }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                </a>
              </div>
            </td>
          </tr>
          @empty
          <tr><td colspan="8" class="text-center py-14">
            <div class="text-4xl mb-3">🚛</div>
            <p class="text-slate-500 font-medium">No shipments found</p>
            <a href="{{ route('crm.logistics.create') }}" class="btn btn-primary text-sm mt-4 inline-flex">+ New Shipment</a>
          </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($logistics->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">{{ $logistics->links() }}</div>
    @endif
  </div>
</div>
@endsection
