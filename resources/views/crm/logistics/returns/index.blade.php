@extends('layouts.app')
@section('title', 'Machine Returns')
@section('page_title', 'Machine Returns')
@section('hide_back', true)

@section('content')
<script>window.CRM_PAGE_CONTEXT = { type: 'list' };</script>
<div id="crm-logistics-returns-list" class="animate-fade-in live-swap-target">
  {{-- ── Toolbar ──────────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex gap-2 flex-wrap">
      <a href="{{ route('crm.logistics.returns.index') }}"
         class="btn text-xs py-1.5 px-3 {{ request('status') ? 'btn-secondary' : 'btn-primary' }}">
        All Returns
      </a>
      @foreach($statuses as $val => $lbl)
      <a href="{{ route('crm.logistics.returns.index', array_merge(request()->query(), ['status' => $val])) }}"
         class="btn text-xs py-1.5 px-3 {{ request('status') === $val ? 'btn-primary' : 'btn-secondary' }}">
        {{ $lbl }}
      </a>
      @endforeach
    </div>
  </div>

  {{-- ── Search ────────────────────────────────────────────────────────────── --}}
  <form method="GET" action="{{ route('crm.logistics.returns.index') }}" class="card p-4 mb-5" x-data>
    <div class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[200px]">
        <label class="form-label text-xs">Search</label>
        <div class="relative">
          <input type="search" name="search" value="{{ request('search') }}"
                 @input.debounce.500ms="$el.closest('form').submit()"
                 placeholder="Customer name or phone…" class="form-input pl-9 py-2 text-sm">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        </div>
      </div>
      <input type="hidden" name="status" value="{{ request('status') }}">
    </div>
  </form>

  {{-- ── Table ───────────────────────────────────────────────────────────── --}}
  <div class="card p-0 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead class="bg-slate-50 text-slate-500 font-semibold text-xs uppercase tracking-wide border-b border-slate-100">
          <tr>
            <th class="px-5 py-3">Customer</th>
            <th class="px-4 py-3">Original Issue</th>
            <th class="px-4 py-3">Return Status</th>
            <th class="px-4 py-3">Last Updated</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($returns as $return)
          <tr class="hover:bg-slate-50/70 transition-colors">
            <td class="px-5 py-3">
              <a href="{{ route('crm.logistics.returns.show', $return) }}" class="font-semibold text-indigo-600 hover:underline">
                {{ $return->customer?->name ?? 'Unknown' }}
              </a>
              @if($return->customer?->phone)
              <div class="text-xs text-slate-400 mt-0.5">{{ $return->customer->phone }}</div>
              @endif
            </td>
            <td class="px-4 py-3">
              <div class="text-xs text-slate-600 max-w-[250px] truncate" title="{{ $return->notes }}">
                {{ Str::limit($return->notes ?? 'No reason provided', 50) }}
              </div>
            </td>
            <td class="px-4 py-3">
              <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $return->statusColor() }}22; color:{{ $return->statusColor() }}">
                {{ $return->statusLabel() }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-500 text-xs">
              {{ $return->updated_at->diffForHumans() }}
              <div class="text-[10px] text-slate-400 mt-0.5">by {{ $return->handler?->name ?? 'System' }}</div>
            </td>
            <td class="px-4 py-3 text-right">
              <a href="{{ route('crm.logistics.returns.show', $return) }}" class="btn btn-secondary btn-sm py-1 px-2 text-xs">
                Manage Return
              </a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="text-center py-14">
              <div class="text-4xl mb-3">🔄</div>
              <p class="text-slate-500 font-medium">No machine returns found</p>
              <p class="text-slate-400 text-xs mt-1">When Tech Support requests a return, it will appear here.</p>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($returns->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">{{ $returns->links() }}</div>
    @endif
  </div>
</div>
@endsection
