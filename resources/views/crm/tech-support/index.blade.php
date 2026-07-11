@extends('layouts.app')
@section('title', 'Tech Support')
@section('page_title', 'Tech Support')

@section('content')
<div class="animate-fade-in">

  {{-- ── KPI tiles ───────────────────────────────────────────────────────── --}}
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
    <div class="card p-3 text-center">
      <div class="text-xl font-bold text-slate-700">{{ $stats['total'] }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Total Cases</div>
    </div>
    <div class="card p-3 text-center">
      <div class="text-xl font-bold text-indigo-600">{{ $stats['new'] }}</div>
      <div class="text-xs text-slate-500 mt-0.5">New Cases</div>
    </div>
    <div class="card p-3 text-center">
      <div class="text-xl font-bold text-amber-600">{{ $stats['in_progress'] }}</div>
      <div class="text-xs text-slate-500 mt-0.5">In Progress</div>
    </div>
    <div class="card p-3 text-center {{ $stats['red'] > 0 ? 'border-2 border-red-400 bg-red-50/60 animate-pulse' : '' }}">
      <div class="text-xl font-bold text-red-600">{{ $stats['red'] }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Red Cases</div>
    </div>
    <div class="card p-3 text-center">
      <div class="text-xl font-bold text-emerald-600">{{ $stats['resolved'] }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Resolved</div>
    </div>
  </div>

  {{-- ── Flash ─────────────────────────────────────────────────────────── --}}
  @if(session('success'))
  <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm font-medium">
    {{ session('success') }}
  </div>
  @endif

  {{-- ── Search & Filters ─────────────────────────────────────────────────── --}}
  <form method="GET" action="{{ route('crm.tech-support.index') }}" class="card p-4 mb-4" x-data>
    <div class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[180px]">
        <label class="form-label text-xs">Search</label>
        <input type="search" name="search" value="{{ request('search') }}"
               @input.debounce.500ms="$el.closest('form').submit()"
               placeholder="Customer or order number…" class="form-input py-2 text-sm">
      </div>
      <div class="min-w-[160px]">
        <label class="form-label text-xs">Status</label>
        <select name="status" class="form-input py-2 text-sm" @change="$el.closest('form').submit()">
          <option value="">All Statuses</option>
          @foreach($statuses as $key => $label)
            <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="min-w-[180px]">
        <label class="form-label text-xs">Assigned Technician</label>
        <select name="assigned_to" class="form-input py-2 text-sm" @change="$el.closest('form').submit()">
          <option value="">All Technicians</option>
          @foreach($technicians as $tech)
            <option value="{{ $tech->id }}" {{ request('assigned_to') == $tech->id ? 'selected' : '' }}>{{ $tech->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="min-w-[160px]">
        <label class="form-label text-xs">Date</label>
        <input type="date" name="date" value="{{ request('date') }}" class="form-input py-2 text-sm" @change="$el.closest('form').submit()">
      </div>
      @if(request('search') || request('status') || request('assigned_to') || request('date'))
      <a href="{{ route('crm.tech-support.index') }}" class="btn btn-secondary text-sm py-2">Clear Filters</a>
      @endif
    </div>
  </form>

  {{-- ── Case Table ────────────────────────────────────────────────────── --}}
  <div class="card p-0 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left">Customer</th>
            <th class="px-4 py-3 text-left">Order #</th>
            <th class="px-4 py-3 text-left">Source</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Assigned To</th>
            <th class="px-4 py-3 text-left">Created</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($cases as $case)
          @php $color = \App\Models\TechSupportCase::statusColor($case->status); @endphp
          <tr class="hover:bg-slate-50/70 transition-colors {{ $case->status === \App\Models\TechSupportCase::STATUS_RED ? 'bg-red-50/50' : '' }}">
            <td class="px-5 py-3 font-semibold text-slate-800">{{ $case->customer?->name ?? '—' }}</td>
            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $case->order_id ?? '—' }}</td>
            <td class="px-4 py-3">
              <span class="badge text-xs px-2 py-0.5 rounded-full {{ $case->source_type === \App\Models\Lead::class ? 'bg-indigo-50 text-indigo-700' : 'bg-amber-50 text-amber-700' }}">
                {{ $case->source_type === \App\Models\Lead::class ? 'CRM' : 'eBay' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $color }}22; color:{{ $color }}">
                {{ $statuses[$case->status] ?? $case->status }}
              </span>
              @if($case->occurrence_label)
                <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full block mt-1 w-fit bg-amber-50 text-amber-700" title="Repeat technical issue">
                  🔁 {{ $case->occurrence_label }}
                </span>
              @endif
              @if($case->latest_call_request)
                @if($case->latest_call_request->fulfilled)
                  @if(in_array($case->id, $unreadCallCompletedCaseIds))
                  <span class="badge text-xs font-bold px-2 py-0.5 rounded-full block mt-1 w-fit bg-rose-100 text-rose-700 ring-1 ring-rose-300 animate-pulse" title="Call completed — outcome not viewed yet">
                    📞 Call Completed <span class="ml-0.5">●&nbsp;New</span>
                  </span>
                  @else
                  <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full block mt-1 w-fit bg-emerald-50 text-emerald-700" title="Call completed — outcome already viewed">
                    📞 Call Completed
                  </span>
                  @endif
                @else
                <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full block mt-1 w-fit bg-amber-50 text-amber-700" title="Call requested, not yet completed">
                  📞 Call Requested
                </span>
                @endif
              @endif
            </td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ $case->assignee?->name ?: 'Unassigned' }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ $case->created_at->format('d M Y, g:ia') }}</td>
            <td class="px-4 py-3 text-right">
              <a href="{{ route('crm.tech-support.show', $case) }}" class="btn btn-secondary text-xs py-1 px-2.5">View</a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center py-14">
              <div class="text-4xl mb-3">🎉</div>
              <p class="text-slate-500 font-medium">No technical support cases found</p>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($cases->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">{{ $cases->links() }}</div>
    @endif
  </div>

</div>
@endsection
