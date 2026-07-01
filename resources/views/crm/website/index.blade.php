@extends('layouts.app')
@section('title', 'Website CRM — Leads')
@section('page_title', 'Website CRM')

@section('content')
<div class="animate-fade-in">

  {{-- ── Toolbar ─────────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex gap-2 flex-wrap">
        {{-- Status quick filters matching flowchart exactly --}}
      @foreach(['' => 'All', 'new_lead' => 'New Customer', 'successful' => 'Successful Lead', 'in_delivery' => 'In Delivery', 'delivered' => 'Delivered', 'lost' => 'Lost Interested', 'technical_support' => 'In Technical'] as $val => $lbl)
      <a href="{{ route('crm.website.index', array_merge(request()->query(), ['status' => $val])) }}"
         class="btn text-xs py-1.5 px-3 {{ request('status') === $val ? 'btn-primary' : 'btn-secondary' }}">
        {{ $lbl }}
      </a>
      @endforeach
      @if(request('handled_by') || request('follow_up_due'))
      <a href="{{ route('crm.website.index') }}" class="btn btn-secondary text-xs py-1.5 px-3">Clear Filters</a>
      @endif
    </div>
    <div class="flex gap-2 items-center">
      @include('crm.partials.report_export_modal', ['type' => 'website', 'btnClass' => 'btn btn-secondary text-sm py-1.5'])
      <a href="{{ route('crm.website.create') }}" class="btn btn-primary text-sm" id="btn-new-lead">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        New Inquiry
      </a>
    </div>
  </div>

  {{-- ── Search & Filters ────────────────────────────────────────────────── --}}
  <form method="GET" action="{{ route('crm.website.index') }}" class="card p-4 mb-5">
    <div class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[200px]">
        <label class="form-label text-xs">Search</label>
        <div class="relative">
          <input type="search" name="search" value="{{ request('search') }}"
                 placeholder="Name, phone, email…" class="form-input pl-9 py-2 text-sm" id="lead-search">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        </div>
      </div>
      <div>
        <label class="form-label text-xs">Status</label>
        <select name="status" class="form-input py-2 text-sm" id="filter-status">
          <option value="">All Statuses</option>
          @foreach($statuses as $s)
            <option value="{{ $s->value }}" {{ request('status') === $s->value ? 'selected' : '' }}>{{ $s->label() }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="form-label text-xs">Source</label>
        <select name="source" class="form-input py-2 text-sm" id="filter-source">
          <option value="">All Sources</option>
          @foreach($sources as $s)
            <option value="{{ $s->value }}" {{ request('source') === $s->value ? 'selected' : '' }}>{{ $s->icon() }} {{ $s->label() }}</option>
          @endforeach
        </select>
      </div>
      <input type="hidden" name="status" value="{{ request('status') }}">
      <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
        <input type="checkbox" name="follow_up_due" value="1" {{ request('follow_up_due') ? 'checked' : '' }} class="accent-indigo-600">
        Follow-ups due
      </label>
      <div class="flex gap-2">
        <button type="submit" class="btn btn-primary py-2 text-sm">Search</button>
        <a href="{{ route('crm.website.index') }}" class="btn btn-secondary py-2 text-sm">Reset</a>
      </div>
    </div>
  </form>

  {{-- ── Lead Table ──────────────────────────────────────────────────────── --}}
  <div class="card p-0 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left">Client</th>
            <th class="px-4 py-3 text-left">Source</th>
            <th class="px-4 py-3 text-left">Product</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Follow-Up</th>
            <th class="px-4 py-3 text-left">Handled By</th>
            <th class="px-4 py-3 text-left">Received</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($leads as $lead)
          <tr class="hover:bg-slate-50/70 transition-colors {{ $lead->is_overdue ? 'bg-red-50/30' : '' }}">
            <td class="px-5 py-3">
              <div>
                <p class="font-semibold text-slate-800">{{ $lead->client_name }}</p>
                <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                  @if($lead->client_phone)
                    <a href="tel:{{ $lead->client_phone }}" class="text-xs text-slate-400 hover:text-indigo-600">{{ $lead->client_phone }}</a>
                  @endif
                  @if($lead->client_email)
                    <a href="mailto:{{ $lead->client_email }}" class="text-xs text-slate-400 hover:text-indigo-600 truncate max-w-[160px]">{{ $lead->client_email }}</a>
                  @endif
                </div>
              </div>
            </td>
            <td class="px-4 py-3">
              <span class="text-sm">{{ $lead->source?->icon() }}</span>
              <span class="text-xs text-slate-500">{{ $lead->source?->label() }}</span>
            </td>
            <td class="px-4 py-3 text-xs text-slate-600">
              {{ $lead->product?->name ?? $lead->product_interested ?? '—' }}
            </td>
            <td class="px-4 py-3">
              <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full"
                    style="background:{{ $lead->status?->color() }}22; color:{{ $lead->status?->color() }}">
                {{ $lead->status?->label() }}
              </span>
            </td>
            <td class="px-4 py-3">
              @if($lead->follow_up_date)
                <span class="text-xs {{ $lead->is_overdue ? 'text-red-600 font-bold' : 'text-slate-500' }}">
                  {{ $lead->is_overdue ? '⚠️ ' : '' }}{{ $lead->follow_up_date->format('d M Y') }}
                </span>
              @else
                <span class="text-xs text-slate-300">Not set</span>
              @endif
            </td>
            <td class="px-4 py-3">
              @if($lead->handler)
              <div class="flex items-center gap-1.5">
                <img src="{{ $lead->handler->avatar_url }}" class="w-5 h-5 rounded-full" alt="{{ $lead->handler->name }}">
                <span class="text-xs text-slate-600 truncate max-w-[80px]">{{ $lead->handler->name }}</span>
              </div>
              @endif
            </td>
            <td class="px-4 py-3 text-xs text-slate-400">{{ $lead->received_at?->diffForHumans() }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1 justify-end">
                <a href="{{ route('crm.website.show', $lead) }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="View">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                </a>
                <a href="{{ route('crm.website.edit', $lead) }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="Edit">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                </a>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="8" class="text-center py-14">
              <div class="text-4xl mb-3">🌐</div>
              <p class="text-slate-500 font-medium">No leads found</p>
              <p class="text-slate-400 text-xs mt-1">Try adjusting your filters or log a new inquiry</p>
              <a href="{{ route('crm.website.create') }}" class="btn btn-primary text-sm mt-4 inline-flex">+ New Inquiry</a>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($leads->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">{{ $leads->links() }}</div>
    @endif
  </div>

</div>
@endsection
