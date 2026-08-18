@extends('layouts.app')
@section('title', 'Shipments')
@section('page_title', 'Shipment Management')
@section('hide_back', true)

@section('content')
<div class="animate-fade-in">
  {{-- ── Toolbar ──────────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex gap-2 flex-wrap">
      @foreach(['' => 'Active', 'all' => 'All', 'complete' => 'Complete', 'problem' => 'Problem'] as $val => $lbl)
      <a href="{{ route('crm.logistics.shipments.index', array_merge(request()->query(), ['status' => $val])) }}"
         class="btn text-xs py-1.5 px-3 {{ request('status', '') === $val ? 'btn-primary' : 'btn-secondary' }}">
        {{ $lbl }}
      </a>
      @endforeach
    </div>
    <div class="flex gap-2 items-center">
      @include('crm.partials.report_export_modal', ['type' => 'logistics', 'btnClass' => 'btn btn-secondary text-sm py-1.5'])
      <a href="{{ route('crm.logistics.processTrucking') }}" class="btn btn-secondary text-sm">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
        Process Trucking
      </a>
      <a href="{{ route('crm.logistics.loaded') }}" class="btn btn-secondary text-sm">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
        Loaded
      </a>
      <a href="{{ route('crm.logistics.shipments.create') }}" class="btn btn-primary text-sm">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        New Shipment
      </a>
    </div>
  </div>

  {{-- ── Search ────────────────────────────────────────────────────────────── --}}
  <form method="GET" action="{{ route('crm.logistics.shipments.index') }}" class="card p-4 mb-5" x-data>
    <div class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[200px]">
        <label class="form-label text-xs">Search</label>
        <div class="relative">
          <input type="search" name="search" value="{{ request('search') }}"
                 @input.debounce.500ms="$el.closest('form').submit()"
                 placeholder="Shipment code, note…" class="form-input pl-9 py-2 text-sm">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        </div>
      </div>
      <input type="hidden" name="status" value="{{ request('status') }}">
    </div>
  </form>

  {{-- ── Table (shipment-grain) ───────────────────────────────────────────── --}}
  <div class="card p-0 overflow-hidden" x-data="{
    selected: [],
    get allChecked() { return {{ $shipments->count() }} > 0 && this.selected.length === {{ $shipments->count() }}; },
    toggleAll(e) { this.selected = e.target.checked ? {{ Js::from($shipments->pluck('id')) }} : []; }
  }">
    {{-- Bulk Action Bar --}}
    <div x-show="selected.length > 0" x-cloak class="flex items-center justify-between bg-slate-900 text-white px-5 py-3 border-b border-slate-800">
      <div class="flex items-center gap-2 text-sm">
        <span class="inline-flex items-center justify-center bg-indigo-500 text-white text-xs font-bold w-6 h-6 rounded-full" x-text="selected.length"></span>
        <span class="font-medium" x-text="selected.length + ' shipment(s) selected'"></span>
      </div>
      <div class="flex items-center gap-2">
        @if(auth()->user()->canDeleteCrmRecords('logistic'))
        <form method="POST" action="{{ route('crm.logistics.shipments.bulk-destroy') }}"
              data-confirm="Delete the selected shipment(s)? This cannot be undone."
              data-confirm-title="Delete Selected Shipments"
              data-confirm-text="Delete Shipments"
              data-confirm-tone="danger" class="inline">
          @csrf
          <template x-for="id in selected" :key="id">
            <input type="hidden" name="shipment_ids[]" :value="id">
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
      <table class="w-full text-sm text-left">
        <thead class="bg-slate-50 text-slate-500 font-semibold text-xs uppercase tracking-wide border-b border-slate-100">
          <tr>
            <th class="px-5 py-3 w-10">
              <input type="checkbox" class="accent-indigo-600 w-4 h-4 rounded cursor-pointer" :checked="allChecked" @change="toggleAll($event)">
            </th>
            <th class="px-4 py-3">Shipment Code</th>
            <th class="px-4 py-3">Customers</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Handled By</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($shipments as $shipment)
          <tr class="hover:bg-slate-50/70 transition-colors">
            <td class="px-5 py-3">
              <input type="checkbox" class="accent-indigo-600 w-4 h-4 rounded cursor-pointer" value="{{ $shipment->id }}" x-model.number="selected">
            </td>
            <td class="px-4 py-3">
              <a href="{{ route('crm.logistics.shipments.show', $shipment) }}" class="font-semibold text-indigo-600 hover:underline">
                {{ $shipment->shipment_code }}
              </a>
            </td>

            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-1">
                <span class="badge bg-slate-100 text-slate-600">{{ $shipment->shipment_customers_count }} customers</span>
              </div>
            </td>
            <td class="px-4 py-3">
              @php $statusCounts = $shipment->customerStatusCounts(); @endphp
              @if(count($statusCounts) > 1)
              <div class="flex flex-wrap gap-1">
                @foreach($statusCounts as $status => $count)
                @php $color = \App\Models\ShipmentCustomer::colorForStatus($status); @endphp
                <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $color }}22; color:{{ $color }}">
                  {{ $count }} {{ \App\Models\ShipmentCustomer::statuses()[$status] ?? $status }}
                </span>
                @endforeach
              </div>
              @else
              <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $shipment->statusColor() }}22; color:{{ $shipment->statusColor() }}">
                {{ $shipment->statusLabel() }}
              </span>
              @endif
            </td>
            <td class="px-4 py-3 text-slate-500 text-xs">
              {{ $shipment->assignee?->name ?? 'Unassigned' }}
            </td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-1">
                <a href="{{ route('crm.logistics.shipments.show', $shipment) }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="View">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                </a>
                <a href="{{ route('crm.logistics.shipments.edit', $shipment) }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="Edit">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                </a>
                @if(auth()->user()->canDeleteCrmRecords('logistic'))
                <form method="POST" action="{{ route('crm.logistics.shipments.destroy', $shipment) }}"
                      data-confirm="Delete shipment {{ $shipment->shipment_code }}?"
                      data-confirm-title="Delete Shipment"
                      data-confirm-text="Delete Shipment"
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
          <tr>
            <td colspan="7" class="text-center py-14">
              <div class="text-4xl mb-3">📦</div>
              <p class="text-slate-500 font-medium">No shipments found</p>
              <p class="text-slate-400 text-xs mt-1">Create your first shipment to start grouping customers</p>
              <a href="{{ route('crm.logistics.shipments.create') }}" class="btn btn-primary text-sm mt-4 inline-flex">+ New Shipment</a>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($shipments->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">{{ $shipments->links() }}</div>
    @endif
  </div>
</div>
@endsection
