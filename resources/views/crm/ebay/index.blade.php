@extends('layouts.app')
@section('title', 'eBay CRM')
@section('page_title', 'eBay CRM')

@section('content')
<div class="animate-fade-in">

  {{-- Toolbar --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex gap-2 flex-wrap">
      @foreach([''=>'All','inquiry'=>'📩 Inquiry','waiting_authorization'=>'⏳ Awaiting Auth','authorized'=>'✅ Authorized','rejected'=>'❌ Rejected','converted_lead'=>'🎯 Converted','order_confirmed'=>'📦 Confirmed'] as $val => $lbl)
      <a href="{{ route('crm.ebay.index', array_merge(request()->query(), ['status' => $val])) }}"
         class="btn text-xs py-1.5 px-3 {{ request('status') === $val ? 'btn-primary' : 'btn-secondary' }}">{{ $lbl }}</a>
      @endforeach
    </div>
    <div class="flex gap-2 items-center">
      @include('crm.partials.report_export_modal', ['type' => 'ebay', 'btnClass' => 'btn btn-secondary text-sm py-1.5'])
      <a href="{{ route('crm.ebay.create') }}" class="btn btn-primary text-sm" id="btn-log-offer">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Log eBay Offer
      </a>
    </div>
  </div>

  {{-- Search --}}
  <form method="GET" action="{{ route('crm.ebay.index') }}" class="card p-4 mb-5">
    <div class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[200px]">
        <label class="form-label text-xs">Search</label>
        <div class="relative">
          <input type="search" name="search" value="{{ request('search') }}"
                 placeholder="Name, eBay username, item ID…" class="form-input pl-9 py-2 text-sm">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        </div>
      </div>
      <div>
        <label class="form-label text-xs">Authorization</label>
        <select name="auth_status" class="form-input py-2 text-sm">
          <option value="">All</option>
          @foreach($authStatuses as $s)
            <option value="{{ $s->value }}" {{ request('auth_status') === $s->value ? 'selected' : '' }}>{{ $s->label() }}</option>
          @endforeach
        </select>
      </div>
      <input type="hidden" name="status" value="{{ request('status') }}">
      <div class="flex gap-2">
        <button type="submit" class="btn btn-primary py-2 text-sm">Search</button>
        <a href="{{ route('crm.ebay.index') }}" class="btn btn-secondary py-2 text-sm">Reset</a>
      </div>
    </div>
  </form>

  {{-- Table --}}
  <div class="card p-0 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left">Client / eBay</th>
            <th class="px-4 py-3 text-left">Product</th>
            <th class="px-4 py-3 text-left">Offer Amount</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Authorization</th>
            <th class="px-4 py-3 text-left">Received</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($offers as $offer)
          <tr class="hover:bg-slate-50/70 transition-colors {{ $offer->status?->value === 'waiting_authorization' ? 'bg-amber-50/40' : '' }}">
            <td class="px-5 py-3">
              <p class="font-semibold text-slate-800">{{ $offer->client_name ?? '(No name)' }}</p>
              @if($offer->ebay_username)
                <p class="text-xs text-slate-400">@{{ $offer->ebay_username }}</p>
              @endif
              @if($offer->client_email)
                <p class="text-xs text-slate-400">{{ $offer->client_email }}</p>
              @endif
            </td>
            <td class="px-4 py-3 text-xs text-slate-600">{{ $offer->product?->name ?? '—' }}</td>
            <td class="px-4 py-3">
              @if($offer->offer_amount)
                <p class="font-semibold text-slate-800">${{ number_format($offer->offer_amount) }}</p>
                @if($offer->final_amount && $offer->final_amount != $offer->offer_amount)
                  <p class="text-xs text-emerald-600">Final: ${{ number_format($offer->final_amount) }}</p>
                @endif
              @else
                <span class="text-slate-300">—</span>
              @endif
            </td>
            <td class="px-4 py-3">
              <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full"
                    style="background:{{ $offer->status?->color() }}22; color:{{ $offer->status?->color() }}">
                {{ $offer->status?->label() }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="badge text-xs {{ $offer->authorization_status?->badgeClass() }}">
                {{ $offer->authorization_status?->label() }}
              </span>
            </td>
            <td class="px-4 py-3 text-xs text-slate-400">{{ $offer->received_at?->diffForHumans() }}</td>
            <td class="px-4 py-3">
              <div class="flex gap-1 justify-end">
                <a href="{{ route('crm.ebay.show', $offer) }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                </a>
                <a href="{{ route('crm.ebay.edit', $offer) }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                </a>
              </div>
            </td>
          </tr>
          @empty
          <tr><td colspan="7" class="text-center py-14">
            <div class="text-4xl mb-3">🛒</div>
            <p class="text-slate-500 font-medium">No eBay offers logged</p>
            <a href="{{ route('crm.ebay.create') }}" class="btn btn-primary text-sm mt-4 inline-flex">+ Log Offer</a>
          </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($offers->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">{{ $offers->links() }}</div>
    @endif
  </div>
</div>
@endsection
