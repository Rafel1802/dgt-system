@extends('layouts.app')
@section('title', 'CRM — Customers')
@section('page_title', 'Customer Database')
@section('meta_description', 'All customers across CRM Website, eBay, and Logistics, deduplicated and searchable.')

@section('content')
<div class="animate-fade-in">

  {{-- ── Stats Row ─────────────────────────────────────────────────────────── --}}
  <div class="mobile-scroll-x lg:grid lg:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
    <div class="stat-card flex-shrink-0 w-[240px] lg:w-auto">
      <div class="stat-icon" style="background:linear-gradient(135deg,#e0e7ff,#c7d2fe)">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#4f46e5" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
      </div>
      <div><div class="stat-value">{{ $totalUnique }}</div><div class="stat-label">Total Customers (deduped)</div></div>
    </div>
    <div class="stat-card flex-shrink-0 w-[240px] lg:w-auto">
      <div class="stat-icon" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0)">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#059669" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
      </div>
      <div><div class="stat-value">{{ $stats['active'] }}</div><div class="stat-label">Active Customers</div></div>
    </div>
    <div class="stat-card flex-shrink-0 w-[240px] lg:w-auto">
      <div class="stat-icon" style="background:linear-gradient(135deg,#fef3c7,#fde68a)">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#d97706" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/></svg>
      </div>
      <div>
        <div class="stat-value text-xl">{{ number_format($stats['total_value'], 0) }}</div>
        <div class="stat-label">Total Revenue (USD)</div>
      </div>
    </div>
  </div>

  {{-- ── Status Filter + Actions ───────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
    <div class="flex gap-2 flex-wrap">
      @foreach(['All', 'Technical issues', 'Logistic issues', 'Negative feedback'] as $val)
      <a href="{{ route('crm.customers.index', array_merge(request()->query(), ['status_filter' => $val])) }}"
         class="btn text-xs py-1.5 px-3 {{ $statusFilter === $val ? 'btn-primary' : 'btn-secondary' }}">
        {{ $val }}
      </a>
      @endforeach
    </div>
    <div class="flex gap-2 items-center flex-wrap">
      @include('crm.partials.report_export_modal', ['type' => 'customers', 'btnClass' => 'btn btn-secondary py-2'])
      @can('crm.create')
      <a href="{{ route('crm.customers.create') }}" class="btn btn-primary py-2" id="btn-add-customer">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Add Customer
      </a>
      @endcan
    </div>
  </div>

  {{-- ── Search + Date Filters ─────────────────────────────────────────────── --}}
  <div class="card p-4 mb-5">
    <form method="GET" action="{{ route('crm.customers.index') }}" class="flex flex-wrap items-end gap-x-6 gap-y-4">
      <input type="hidden" name="status_filter" value="{{ $statusFilter }}">
      <input type="hidden" name="source_filter" value="{{ $sourceFilter }}">

      <div class="min-w-[220px]">
        <label class="form-label text-xs">Search</label>
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Name/email/phone…" class="form-input text-sm py-2 w-full">
      </div>

      <div class="flex items-end gap-2 pl-4 border-l border-slate-100">
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1.5">Created Date</p>
          <div class="flex items-center gap-2">
            <input type="date" name="created_from" value="{{ $createdFrom }}" class="form-input text-sm py-2 w-36" title="From">
            <span class="text-xs text-slate-400">to</span>
            <input type="date" name="created_to" value="{{ $createdTo }}" class="form-input text-sm py-2 w-36" title="To">
          </div>
        </div>
      </div>

      <div class="flex items-end gap-2 pl-4 border-l border-slate-100">
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1.5">Purchase Date</p>
          <div class="flex items-center gap-2">
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-input text-sm py-2 w-36" title="From">
            <span class="text-xs text-slate-400">to</span>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-input text-sm py-2 w-36" title="To">
          </div>
        </div>
      </div>

      <div class="flex gap-2">
        <button type="submit" class="btn btn-secondary text-sm">Search</button>
        @if($dateFrom || $dateTo || $createdFrom || $createdTo || request('search'))
        <a href="{{ route('crm.customers.index', ['status_filter' => $statusFilter, 'source_filter' => $sourceFilter]) }}" class="btn btn-secondary text-sm">Clear</a>
        @endif
      </div>
    </form>
  </div>

  <div class="flex items-center gap-2 mb-5">
    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Source:</span>
    @foreach(['All', 'eBay', 'Logistics', 'Website'] as $val)
    <a href="{{ route('crm.customers.index', array_merge(request()->query(), ['source_filter' => $val])) }}"
       class="btn text-xs py-1.5 px-3 {{ $sourceFilter === $val ? 'btn-primary' : 'btn-secondary' }}">
      {{ $val }}
    </a>
    @endforeach
  </div>

  <p class="text-xs text-slate-400 mb-3">{{ $totalUnique }} unique customer(s) across CRM, eBay, and Logistics (deduplicated by email/phone).</p>

  {{-- ── Customer Table ────────────────────────────────────────────────────── --}}
  <div class="card p-0 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <p class="text-sm text-slate-500">Showing <strong>{{ $customers->count() }}</strong> customer(s)</p>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm" id="customer-table">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left">Name</th>
            <th class="px-4 py-3 text-left">Contact</th>
            <th class="px-4 py-3 text-left">Source</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Created</th>
            <th class="px-4 py-3 text-left">Purchase Date</th>
            <th class="px-4 py-3 text-left">Handler</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($customers as $customer)
          <tr class="hover:bg-slate-50/70 transition-colors">
            <td class="px-5 py-3">
              @if($customer['link'])
                <a href="{{ $customer['link'] }}" class="font-semibold text-slate-800 hover:text-indigo-600 transition-colors">{{ $customer['name'] }}</a>
              @else
                <span class="font-semibold text-slate-800">{{ $customer['name'] }}</span>
              @endif
            </td>
            <td class="px-4 py-3 text-xs text-slate-500">
              {{ $customer['email'] ?: '—' }}<br>{{ $customer['phone'] ?: '' }}
            </td>
            <td class="px-4 py-3">
              <span class="badge text-xs px-2 py-0.5 rounded-full"
                    style="background:{{ $customer['source_color'] }}22; color:{{ $customer['source_color'] }}">
                {{ $customer['source_icon'] }} {{ $customer['source'] }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $customer['status_color'] ?? '#94a3b8' }}22; color:{{ $customer['status_color'] ?? '#94a3b8' }}">
                {{ $customer['status_label'] }}
              </span>
              @if($customer['occurrence_label'] ?? null)
                <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700" title="Repeat technical issue">
                  🔁 {{ $customer['occurrence_label'] }}
                </span>
              @endif
            </td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ $customer['created_date']?->format('d/m/Y') ?? '—' }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ $customer['purchase_date']?->format('d/m/Y') ?? '—' }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ $customer['handler'] ?: '—' }}</td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-1">
                @if($customer['link'])
                <a href="{{ $customer['link'] }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="View">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                </a>
                @endif
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center py-16 text-slate-400">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="#cbd5e1" class="w-12 h-12 mx-auto mb-3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
              </svg>
              No customers found. <a href="{{ route('crm.customers.create') }}" class="text-indigo-600 hover:underline">Add the first one →</a>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
