@extends('layouts.app')
@section('title', 'Website CRM — Leads')
@section('page_title', 'Website CRM')
@section('hide_back', true)

@section('content')
<script>window.CRM_PAGE_CONTEXT = { type: 'list' };</script>
<div id="crm-website-leads-list" class="animate-fade-in live-swap-target">

  {{-- ── Toolbar ─────────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex gap-2 flex-wrap">
      @php
        $filters = ['' => 'All'];
        foreach(\App\Enums\WebsiteLeadStatus::cases() as $case) {
            $filters[$case->value] = $case->label();
        }
      @endphp
      @foreach($filters as $val => $lbl)
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
      <a href="{{ route('crm.website.call-reports.index') }}" class="btn btn-secondary text-sm" id="btn-call-reports">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.362-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
        Call Reports
      </a>
      <a href="{{ route('crm.website.call-requests.index') }}" class="btn btn-secondary text-sm relative" id="btn-call-requests">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/></svg>
        Call Requests
        @if($pendingCallRequestsCount > 0)
        <span class="absolute -top-1.5 -right-1.5 bg-amber-500 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">{{ $pendingCallRequestsCount }}</span>
        @endif
      </a>
      @include('crm.partials.report_export_modal', ['type' => 'website', 'btnClass' => 'btn btn-secondary text-sm py-1.5'])
      @if(auth()->user()->hasAnyRole(['super-admin', 'admin-crm', 'boss']))
      <a href="{{ route('crm.reports.index') }}" class="btn btn-secondary text-sm">📊 Team Report</a>
      @endif
      <a href="{{ route('crm.reports.show', auth()->user()) }}" class="btn btn-secondary text-sm">📈 My Report</a>
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
      <div>
        <label class="form-label text-xs">Handler</label>
        <select name="handled_by" class="form-input py-2 text-sm" id="filter-handled-by">
          <option value="">All Handlers</option>
          @foreach($crmUsers as $crmUser)
            <option value="{{ $crmUser->id }}" {{ request('handled_by') == $crmUser->id ? 'selected' : '' }}>{{ $crmUser->name }}</option>
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
  <div class="card p-0 overflow-hidden" x-data="{
    selected: [],
    get allChecked() { return {{ $leads->count() }} > 0 && this.selected.length === {{ $leads->count() }}; },
    toggleAll(e) { this.selected = e.target.checked ? {{ Js::from($leads->pluck('id')) }} : []; }
  }">
    {{-- Bulk Action Bar --}}
    <div x-show="selected.length > 0" x-cloak class="flex items-center justify-between bg-slate-900 text-white px-5 py-3 border-b border-slate-800">
      <div class="flex items-center gap-2 text-sm">
        <span class="inline-flex items-center justify-center bg-indigo-500 text-white text-xs font-bold w-6 h-6 rounded-full" x-text="selected.length"></span>
        <span class="font-medium" x-text="selected.length + ' lead(s) selected'"></span>
      </div>
      <div class="flex items-center gap-2">
        @if(auth()->user()->canDeleteCrmRecords('website'))
        <form method="POST" action="{{ route('crm.website.bulk-destroy') }}"
              data-confirm="Delete the selected lead(s)? Linked customer profiles will also be deleted."
              data-confirm-title="Delete Selected Leads"
              data-confirm-text="Delete Leads"
              data-confirm-tone="danger" class="inline">
          @csrf
          <template x-for="id in selected" :key="id">
            <input type="hidden" name="lead_ids[]" :value="id">
          </template>
          <button type="submit" class="btn btn-sm bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1.5 rounded-lg flex items-center gap-1.5 shadow-sm">
            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
            Delete Selected
          </button>
        </form>
        @endif
        <button type="button" @click="selected = []" class="text-xs text-slate-400 hover:text-white ml-2 underline">Clear selection</button>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 w-10 border-r border-slate-100">
              <input type="checkbox" class="accent-indigo-600 w-4 h-4 rounded cursor-pointer" :checked="allChecked" @change="toggleAll($event)">
            </th>
            <th class="px-4 py-3 text-left border-r border-slate-100">Client</th>
            <th class="px-4 py-3 text-left border-r border-slate-100">Source</th>
            <th class="px-4 py-3 text-left border-r border-slate-100">Product</th>
            <th class="px-4 py-3 text-left border-r border-slate-100">Status</th>
            <th class="px-4 py-3 text-left border-r border-slate-100">Follow-Up</th>
            <th class="px-4 py-3 text-left border-r border-slate-100">Handled By</th>
            <th class="px-4 py-3 text-left border-r border-slate-100">Received</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($leads as $lead)
          <tr class="odd:bg-white even:bg-slate-50/50 hover:bg-indigo-50/30 transition-colors duration-100 {{ $lead->is_overdue ? '!bg-red-50/20' : '' }}">
            <td class="px-5 py-3 border-r border-slate-100">
              <input type="checkbox" class="accent-indigo-600 w-4 h-4 rounded cursor-pointer" value="{{ $lead->id }}" x-model.number="selected">
            </td>
            <td class="px-4 py-3 border-r border-slate-100">
              <div>
                <div class="flex items-center gap-1.5 flex-wrap">
                  <p class="font-semibold text-slate-800">{{ $lead->client_name }}</p>
                  @if(isset($duplicateMap[$lead->id]))
                    @php $dup = $duplicateMap[$lead->id]; @endphp
                    @if($dup['level'] === 'definite')
                      <span class="inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-200 cursor-help whitespace-nowrap"
                            title="{{ $dup['message'] }}">
                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                        Duplicate
                      </span>
                    @else
                      <span class="inline-flex items-center gap-0.5 text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 border border-slate-200 cursor-help whitespace-nowrap"
                            title="{{ $dup['message'] }}">
                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z"/></svg>
                        Possible Duplicate
                      </span>
                    @endif
                  @endif
                </div>
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
            <td class="px-4 py-3 border-r border-slate-100">
              <span class="text-sm">{{ $lead->source?->icon() }}</span>
              <span class="text-xs text-slate-500">{{ $lead->source?->label() }}</span>
            </td>
            <td class="px-4 py-3 text-xs text-slate-600 border-r border-slate-100">
              {{ $lead->product?->name ?? $lead->product_interested ?? '—' }}
            </td>
            <td class="px-4 py-3 border-r border-slate-100">
              <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full"
                    style="background:{{ $lead->status?->color() }}22; color:{{ $lead->status?->color() }}">
                {{ $lead->status?->label() }}
              </span>
              @if($lead->techSupportCase?->occurrence_label)
                <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full block mt-1 w-fit bg-amber-50 text-amber-700" title="Repeat technical issue">
                  🔁 {{ $lead->techSupportCase->occurrence_label }}
                </span>
              @endif
            </td>
            <td class="px-4 py-3 border-r border-slate-100">
              @if($lead->follow_up_date)
                <span class="text-xs {{ $lead->is_overdue ? 'text-red-600 font-bold' : 'text-slate-500' }}">
                  {{ $lead->is_overdue ? '⚠️ ' : '' }}{{ $lead->follow_up_date->format('d M Y') }}
                </span>
              @else
                <span class="text-xs text-slate-300">Not set</span>
              @endif
            </td>
            <td class="px-4 py-3 border-r border-slate-100">
              @if($lead->handler)
              <div class="flex items-center gap-1.5">
                <img src="{{ $lead->handler->avatar_url }}" class="w-5 h-5 rounded-full" alt="{{ $lead->handler->name }}">
                <span class="text-xs text-slate-600 truncate max-w-[80px]">{{ $lead->handler->name }}</span>
              </div>
              @endif
            </td>
            <td class="px-4 py-3 text-xs text-slate-400 border-r border-slate-100">{{ $lead->received_at?->diffForHumans() }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1 justify-end">
                <a href="{{ route('crm.website.show', $lead) }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="View">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                </a>
                @if(auth()->user()->canDeleteCrmRecords('website'))
                <a href="{{ route('crm.website.edit', $lead) }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="Edit">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                </a>
                @endif
                @if(auth()->user()->canDeleteCrmRecords('website'))
                @php
                  $leadDeleteConfirmMsg = $lead->customer
                      ? 'This lead is linked to "' . $lead->customer->name . '" — deleting it will PERMANENTLY delete that customer and everything tied to them across every CRM domain. This cannot be undone.'
                      : 'Delete this lead?';
                @endphp
                <form method="POST" action="{{ route('crm.website.destroy', $lead) }}"
                      data-confirm="{{ $leadDeleteConfirmMsg }}"
                      data-confirm-title="{{ $lead->customer ? 'Delete Lead & Linked Customer?' : 'Delete Lead?' }}"
                      data-confirm-text="Delete"
                      data-confirm-tone="danger" class="inline">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-secondary btn-icon text-red-400 hover:text-red-600" style="width:28px;height:28px;" title="Delete">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                  </button>
                </form>
                @endif
              </div>
            </td>
          </tr>
          @empty
          @if(!isset($leads) || $leads->isEmpty())
            <tr id="initial-empty-state" class="hidden">
              <td colspan="9" class="text-center py-14"></td>
            </tr>
          @endif
          @endforelse
        </tbody>

        @php $customerOnlyRows = $customerOnlyRowsFn(); @endphp
          @if($customerOnlyRows->isEmpty() && $leads->isEmpty())
            <tbody class="bg-white">
              <tr>
                <td colspan="9" class="text-center py-14">
                  <div class="text-4xl mb-3">🌐</div>
                  <p class="text-slate-500 font-medium">No leads found</p>
                  <p class="text-slate-400 text-xs mt-1">Try adjusting your filters or log a new inquiry</p>
                  <a href="{{ route('crm.website.create') }}" class="btn btn-primary text-sm mt-4 inline-flex">+ New Inquiry</a>
                </td>
              </tr>
            </tbody>
          @endif
          
          @if($customerOnlyRows->isNotEmpty())
          <tbody class="divide-y divide-slate-100 bg-white">

          {{-- Customers with a Website-channel source but no Lead of their own yet
               (same "Website" bucket the All Customers page's source filter shows).
               Read-only rows — no pipeline/status/follow-up controls since they
               aren't Leads. --}}
          @foreach($customerOnlyRows as $c)
          <tr class="hover:bg-slate-50/70 transition-colors bg-violet-50/20">
            <td class="px-5 py-3">
              <div>
                <div class="flex items-center gap-1.5">
                  <p class="font-semibold text-slate-800">{{ $c['name'] }}</p>
                  <span class="badge text-[10px] px-1.5 py-0.5 rounded-full bg-violet-50 text-violet-700">Customer</span>
                </div>
                <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                  @if($c['phone'])
                    <a href="tel:{{ $c['phone'] }}" class="text-xs text-slate-400 hover:text-indigo-600">{{ $c['phone'] }}</a>
                  @endif
                  @if($c['email'])
                    <a href="mailto:{{ $c['email'] }}" class="text-xs text-slate-400 hover:text-indigo-600 truncate max-w-[160px]">{{ $c['email'] }}</a>
                  @endif
                </div>
              </div>
            </td>
            <td class="px-4 py-3">
              <span class="text-sm">{{ $c['source_icon'] }}</span>
              <span class="text-xs text-slate-500">{{ $c['source'] }}</span>
            </td>
            <td class="px-4 py-3 text-xs text-slate-300">—</td>
            <td class="px-4 py-3">
              <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                {{ $c['status_label'] }}
              </span>
            </td>
            <td class="px-4 py-3"><span class="text-xs text-slate-300">Not a lead</span></td>
            <td class="px-4 py-3">
              @if($c['handler'])
                <span class="text-xs text-slate-600 truncate max-w-[80px]">{{ $c['handler'] }}</span>
              @else
                <span class="text-xs text-slate-300">—</span>
              @endif
            </td>
            <td class="px-4 py-3 text-xs text-slate-300">—</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1 justify-end">
                <a href="{{ $c['link'] }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="View Customer">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                </a>
              </div>
            </td>
          </tr>
          @endforeach
          </tbody>
          @endif

      </table>
    </div>
    @if($leads->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">{{ $leads->links() }}</div>
    @endif
  </div>

</div>
@endsection
