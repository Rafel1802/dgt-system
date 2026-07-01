@extends('layouts.app')
@section('title', 'CRM — Customers')
@section('page_title', 'Customer Database')
@section('meta_description', 'Search and manage your customer database, pipeline, and sales activities.')

@section('content')
<div class="animate-fade-in">

  {{-- ── Stats Row ─────────────────────────────────────────────────────────── --}}
  <div class="mobile-scroll-x lg:grid lg:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="stat-card flex-shrink-0 w-[240px] lg:w-auto">
      <div class="stat-icon" style="background:linear-gradient(135deg,#e0e7ff,#c7d2fe)">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#4f46e5" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
      </div>
      <div><div class="stat-value">{{ $stats['total'] }}</div><div class="stat-label">Total Customers</div></div>
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
        <div class="stat-label">Total Revenue (AUD)</div>
      </div>
    </div>
    <div class="stat-card flex-shrink-0 w-[240px] lg:w-auto">
      <div class="stat-icon" style="background:linear-gradient(135deg,#ede9fe,#ddd6fe)">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#7c3aed" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
      </div>
      <div><div class="stat-value">{{ $stats['pipeline_value'] > 0 ? '$'.number_format($stats['pipeline_value'],0) : '—' }}</div><div class="stat-label">Pipeline Value</div></div>
    </div>
  </div>

  {{-- ── Search & Filters ──────────────────────────────────────────────────── --}}
  <div class="card mb-5">
    <form method="GET" action="{{ route('crm.customers.index') }}" class="flex flex-wrap gap-3 items-end">
      {{-- Search --}}
      <div class="flex-1 min-w-[220px]">
        <label class="form-label text-xs">Search</label>
        <div class="relative">
          <input type="search" name="search" value="{{ request('search') }}"
                 placeholder="Name, email, company, phone…"
                 class="form-input pl-9 py-2 text-sm" id="crm-search">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        </div>
      </div>

      {{-- Status --}}
      <div class="min-w-[140px]">
        <label class="form-label text-xs">Status</label>
        <select name="status" class="form-input py-2 text-sm" id="filter-status">
          <option value="">All Status</option>
          @foreach($statuses as $s)
            <option value="{{ $s->value }}" {{ request('status') === $s->value ? 'selected' : '' }}>{{ $s->label() }}</option>
          @endforeach
        </select>
      </div>

      {{-- Source --}}
      <div class="min-w-[140px]">
        <label class="form-label text-xs">Source</label>
        <select name="source" class="form-input py-2 text-sm" id="filter-source">
          <option value="">All Sources</option>
          @foreach($sources as $s)
            <option value="{{ $s->value }}" {{ request('source') === $s->value ? 'selected' : '' }}>{{ $s->icon() }} {{ $s->label() }}</option>
          @endforeach
        </select>
      </div>

      {{-- Purchased --}}
      <div class="min-w-[130px]">
        <label class="form-label text-xs">Bought?</label>
        <select name="purchased" class="form-input py-2 text-sm" id="filter-purchased">
          <option value="">All</option>
          <option value="1" {{ request('purchased') === '1' ? 'selected' : '' }}>Purchased ✓</option>
          <option value="0" {{ request('purchased') === '0' ? 'selected' : '' }}>Not Purchased</option>
        </select>
      </div>

      {{-- Assignee --}}
      <div class="min-w-[150px]">
        <label class="form-label text-xs">Assigned To</label>
        <select name="assignee" class="form-input py-2 text-sm" id="filter-assignee">
          <option value="">Anyone</option>
          @foreach($users as $u)
            <option value="{{ $u->id }}" {{ request('assignee') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
          @endforeach
        </select>
      </div>

      {{-- Sort --}}
      <div class="min-w-[140px]">
        <label class="form-label text-xs">Sort by</label>
        <select name="sort" class="form-input py-2 text-sm" id="filter-sort">
          <option value="created_at" {{ request('sort') === 'created_at' ? 'selected' : '' }}>Date Added</option>
          <option value="name" {{ request('sort') === 'name' ? 'selected' : '' }}>Name</option>
          <option value="lifetime_value" {{ request('sort') === 'lifetime_value' ? 'selected' : '' }}>Lifetime Value</option>
          <option value="last_purchase_date" {{ request('sort') === 'last_purchase_date' ? 'selected' : '' }}>Last Purchase</option>
        </select>
      </div>

      <div class="flex gap-2">
        <button type="submit" class="btn btn-primary py-2" id="btn-search">Search</button>
        <a href="{{ route('crm.customers.index') }}" class="btn btn-secondary py-2">Reset</a>
      </div>

      <div class="flex gap-2 ml-auto">
        @include('crm.partials.report_export_modal', ['type' => 'customers', 'btnClass' => 'btn btn-secondary py-2'])
        @can('crm.create')
        <a href="{{ route('crm.customers.create') }}" class="btn btn-primary py-2" id="btn-add-customer">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
          Add Customer
        </a>
        @endcan
      </div>
    </form>
  </div>

  {{-- ── Customer Table ────────────────────────────────────────────────────── --}}
  <div class="card p-0 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <p class="text-sm text-slate-500">
        Showing <strong>{{ $customers->firstItem() }}–{{ $customers->lastItem() }}</strong> of
        <strong>{{ $customers->total() }}</strong> customers
      </p>
      <a href="{{ route('crm.pipeline.index') }}" class="btn btn-secondary text-sm py-1.5">
        📊 Pipeline View
      </a>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm" id="customer-table">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left">#</th>
            <th class="px-5 py-3 text-left">Customer</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Source</th>
            <th class="px-4 py-3 text-left">Pipeline Stage</th>
            <th class="px-4 py-3 text-left">Purchased</th>
            <th class="px-4 py-3 text-right">Value</th>
            <th class="px-4 py-3 text-left">Assigned To</th>
            <th class="px-4 py-3 text-left">Added</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($customers as $customer)
          <tr class="hover:bg-slate-50/70 transition-colors" id="customer-row-{{ $customer->id }}">
            <td class="px-5 py-3 text-slate-400 font-medium">
              {{ ($customers->currentPage() - 1) * $customers->perPage() + $loop->iteration }}
            </td>
            {{-- Customer --}}
            <td class="px-5 py-3">
              <div class="flex items-center gap-3">
                <img src="{{ $customer->avatar_url }}" alt="{{ $customer->name }}" class="avatar avatar-sm flex-shrink-0">
                <div>
                  <a href="{{ route('crm.customers.show', $customer) }}" class="font-semibold text-slate-800 hover:text-indigo-600 transition-colors">
                    {{ $customer->name }}
                  </a>
                  @if($customer->company)
                    <div class="text-xs text-slate-400">{{ $customer->company }}</div>
                  @endif
                  @if($customer->email)
                    <div class="text-xs text-slate-400">{{ $customer->email }}</div>
                  @endif
                </div>
              </div>
            </td>

            {{-- Status --}}
            <td class="px-4 py-3">
              <span class="badge {{ $customer->status?->badgeClass() ?? 'badge-slate' }}">
                {{ $customer->status?->label() ?? $customer->status }}
              </span>
            </td>

            {{-- Source --}}
            <td class="px-4 py-3 text-slate-500 text-xs">
              @php $src = \App\Enums\CustomerSource::tryFrom($customer->source ?? ''); @endphp
              {{ $src?->icon() }} {{ $src?->label() ?? $customer->source ?? '—' }}
            </td>

            {{-- Pipeline Stage --}}
            <td class="px-4 py-3">
              @php $stage = $customer->pipeline_stage; @endphp
              @if($stage)
                <span class="text-xs font-medium px-2 py-1 rounded-full"
                      style="background: {{ $stage->color() }}22; color: {{ $stage->color() }}">
                  {{ $stage->label() }}
                </span>
              @else
                <span class="text-slate-300">—</span>
              @endif
            </td>

            {{-- Purchased --}}
            <td class="px-4 py-3">
              @if($customer->has_purchased)
                <span class="badge badge-emerald">✓ Bought</span>
              @else
                <span class="badge badge-slate">Not yet</span>
              @endif
            </td>

            {{-- Value --}}
            <td class="px-4 py-3 text-right font-semibold text-slate-700">
              {{ $customer->lifetime_value > 0 ? '$'.number_format($customer->lifetime_value, 2) : '—' }}
            </td>

            {{-- Assigned --}}
            <td class="px-4 py-3">
              @if($customer->assignee)
                <div class="flex items-center gap-1.5">
                  <img src="{{ $customer->assignee->avatar_url }}" class="avatar" style="width:20px;height:20px;" alt="{{ $customer->assignee->name }}">
                  <span class="text-xs text-slate-500">{{ $customer->assignee->name }}</span>
                </div>
              @else
                <span class="text-slate-300 text-xs">Unassigned</span>
              @endif
            </td>

            {{-- Date --}}
            <td class="px-4 py-3 text-xs text-slate-400">{{ $customer->created_at->format('d M Y') }}</td>

            {{-- Actions --}}
            <td class="px-4 py-3">
              <div class="flex items-center gap-1">
                <a href="{{ route('crm.customers.show', $customer) }}"
                   class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="View">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                </a>
                @can('update', $customer)
                <a href="{{ route('crm.customers.edit', $customer) }}"
                   class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="Edit">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                </a>
                @endcan
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="10" class="text-center py-16 text-slate-400">
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

    {{-- Pagination --}}
    @if($customers->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">
      {{ $customers->links() }}
    </div>
    @endif
  </div>

</div>
@endsection
