@extends('layouts.app')
@section('title', 'Shipments')
@section('page_title', 'Shipment Management')

@section('content')
<div class="animate-fade-in">
  {{-- ── Toolbar ──────────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex gap-2 flex-wrap">
      @foreach(['' => 'Active', 'all' => 'All', 'processing' => 'Process Trucking', 'loaded' => 'Loaded', 'complete' => 'Complete', 'problem' => 'Problem'] as $val => $lbl)
      <a href="{{ route('crm.logistics.shipments.index', array_merge(request()->query(), ['status' => $val])) }}"
         class="btn text-xs py-1.5 px-3 {{ request('status', '') === $val ? 'btn-primary' : 'btn-secondary' }}">
        {{ $lbl }}
      </a>
      @endforeach
    </div>
    <div class="flex gap-2">
      <a href="{{ route('crm.logistics.index') }}" class="btn btn-secondary text-sm">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
        Legacy Logistics
      </a>
      @if(request('status') === 'processing')
      <button type="button" onclick="document.getElementById('importCustomersModal').classList.remove('hidden')" class="btn btn-secondary text-sm">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        Import from Excel
      </button>
      @endif
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

  @if($viewMode === 'shipments')
  {{-- ── Table (shipment-grain) ───────────────────────────────────────────── --}}
  <div class="card p-0 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead class="bg-slate-50 text-slate-500 font-semibold text-xs uppercase tracking-wide border-b border-slate-100">
          <tr>
            <th class="px-5 py-3">Shipment Code</th>
            <th class="px-4 py-3">Date</th>
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
              <a href="{{ route('crm.logistics.shipments.show', $shipment) }}" class="font-semibold text-indigo-600 hover:underline">
                {{ $shipment->shipment_code }}
              </a>
            </td>
            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
              {{ $shipment->estimated_arrival ? $shipment->estimated_arrival->format('d M Y') : '-' }}
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
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center py-14">
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

  @else
  {{-- ── Table (customer-grain: Process Trucking / Loaded) ───────────────────
       Bulk-select customers — possibly from different shipments — and move
       them all to the next status together. --}}
  @php
    $isProcessing = request('status') === 'processing';
    $nextStatus = $isProcessing ? \App\Models\ShipmentCustomer::STATUS_IN_TRANSIT : \App\Models\ShipmentCustomer::STATUS_DELIVERED;
    $statusLabels = \App\Models\ShipmentCustomer::statuses();
  @endphp
  <div class="card p-0 overflow-hidden" x-data="{
    selected: [],
    bulkStatus: '{{ $nextStatus }}',
    bulkNotes: '',
    statusLabels: {{ Js::from($statusLabels) }},
    get allChecked() { return {{ $shipmentCustomers->count() }} > 0 && this.selected.length === {{ $shipmentCustomers->count() }}; },
    get actionLabel() { return 'Mark as ' + (this.statusLabels[this.bulkStatus] || this.bulkStatus); },
    toggleAll(e) { this.selected = e.target.checked ? {{ Js::from($shipmentCustomers->pluck('id')) }} : []; },
  }">
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead class="bg-slate-50 text-slate-500 font-semibold text-xs uppercase tracking-wide border-b border-slate-100">
          <tr>
            <th class="px-5 py-3 w-10">
              <input type="checkbox" class="accent-indigo-600 w-4 h-4" :checked="allChecked" @change="toggleAll($event)">
            </th>
            <th class="px-4 py-3">Recipient</th>
            <th class="px-4 py-3">Shipment</th>
            <th class="px-4 py-3">Product</th>
            <th class="px-4 py-3">Tracking #</th>
            <th class="px-4 py-3">Handled By</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($shipmentCustomers as $sc)
          <tr class="hover:bg-slate-50/70 transition-colors">
            <td class="px-5 py-3">
              <input type="checkbox" class="accent-indigo-600 w-4 h-4" value="{{ $sc->id }}" x-model="selected">
            </td>
            <td class="px-4 py-3">
              <p class="font-semibold text-slate-800">{{ $sc->recipient_name ?: '—' }}</p>
              @if($sc->recipient_phone)
                <p class="text-xs text-slate-400">{{ $sc->recipient_phone }}</p>
              @endif
            </td>
            <td class="px-4 py-3">
              @if($sc->shipment)
              <a href="{{ route('crm.logistics.shipments.show', $sc->shipment) }}" class="font-semibold text-indigo-600 hover:underline text-xs">
                {{ $sc->shipment->shipment_code }}
              </a>
              @else
              <span class="text-slate-400 text-xs">—</span>
              @endif
            </td>
            <td class="px-4 py-3 text-slate-600 text-xs">
              @forelse($sc->products as $p)
                <p>{{ $p->product_name }} × {{ $p->quantity }}</p>
              @empty
                <p>—</p>
              @endforelse
            </td>
            <td class="px-4 py-3 text-slate-600 text-xs">
              {{ $sc->tracking_number ?: '—' }}
            </td>
            <td class="px-4 py-3 text-slate-500 text-xs">
              {{ $sc->handler?->name ?? 'Unassigned' }}
            </td>
            <td class="px-4 py-3">
              <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $sc->statusColor() }}22; color:{{ $sc->statusColor() }}">
                {{ $sc->statusLabel() }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <form method="POST" action="{{ route('crm.logistics.shipments.customers.destroy', $sc) }}"
                    data-confirm="Delete this customer record? This cannot be undone." data-confirm-tone="danger" class="inline">
                @csrf @method('DELETE')
                <input type="hidden" name="redirect_status" value="{{ request('status') }}">
                <button type="submit" class="btn btn-danger btn-icon" style="width:28px;height:28px;" title="Delete">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
              </form>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="8" class="text-center py-14">
              <div class="text-4xl mb-3">🚚</div>
              <p class="text-slate-500 font-medium">{{ $isProcessing ? 'No customers waiting to be loaded' : 'No customers currently loaded' }}</p>
              <p class="text-slate-400 text-xs mt-1">{{ $isProcessing ? 'Every customer has already been marked Loaded or Delivered.' : 'Mark customers as Loaded from the Process Trucking tab first.' }}</p>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($shipmentCustomers->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">{{ $shipmentCustomers->links() }}</div>
    @endif

    {{-- ── Sticky bulk-action bar ─────────────────────────────────────────── --}}
    <div x-show="selected.length > 0" x-cloak x-transition
         class="sticky bottom-0 border-t border-slate-200 bg-white/95 backdrop-blur px-5 py-3 flex flex-wrap items-center gap-3">
      <form method="POST" action="{{ route('crm.logistics.shipments.customers.bulkStatus') }}" class="flex flex-wrap items-center gap-3 w-full"
            @submit="if (bulkStatus === '{{ \App\Models\ShipmentCustomer::STATUS_PROBLEM }}' && !bulkNotes.trim()) { $event.preventDefault(); alert('A note is required for Logistic issues (Problem status).'); }">
        @csrf
        <template x-for="id in selected" :key="id">
          <input type="hidden" name="customer_ids[]" :value="id">
        </template>
        <input type="hidden" name="redirect_status" value="{{ request('status') }}">
        <span class="text-xs font-semibold text-slate-600" x-text="selected.length + ' selected'"></span>
        <select name="status" x-model="bulkStatus" class="form-input py-1.5 text-sm w-auto">
          @foreach(\App\Models\ShipmentCustomer::statuses() as $val => $lbl)
          <option value="{{ $val }}">{{ $lbl }}</option>
          @endforeach
        </select>
        <input type="text" name="notes" x-model="bulkNotes" x-show="bulkStatus === '{{ \App\Models\ShipmentCustomer::STATUS_PROBLEM }}'"
               placeholder="Note explaining the issue (required)" class="form-input py-1.5 text-sm flex-1 min-w-[200px]">
        <button type="submit" class="btn btn-primary text-sm py-1.5" x-text="actionLabel"></button>
      </form>
    </div>
  </div>
  @endif
</div>

@if(request('status') === 'processing')
{{-- ── Import from Excel modal ──────────────────────────────────────────── --}}
<div id="importCustomersModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden text-left">
    <form method="POST" action="{{ route('crm.logistics.shipments.customers.import.preview.store') }}" enctype="multipart/form-data">
      @csrf
      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
        <h3 class="font-display font-bold text-lg text-slate-800">Import from Excel</h3>
        <button type="button" onclick="document.getElementById('importCustomersModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <p class="text-xs text-slate-500">
          Upload a raw shipping-label export as-is, or use the
          <a href="{{ route('crm.logistics.shipments.customers.import.template') }}" class="text-indigo-600 hover:underline font-semibold">column template</a>
          if you prefer — either is detected automatically.
        </p>
        @error('file')
          <p class="form-error">{{ $message }}</p>
        @enderror
        <div>
          <label class="form-label">File (.xlsx or .csv)</label>
          <input type="file" name="file" accept=".xlsx,.csv" required class="form-input">
          <p class="mt-1 text-xs text-slate-400">You'll review and can edit every row before anything is saved. Imported customers aren't assigned to a shipment — that's a separate step.</p>
        </div>
      </div>
      <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 bg-slate-50">
        <button type="button" onclick="document.getElementById('importCustomersModal').classList.add('hidden')" class="btn btn-secondary text-sm">Cancel</button>
        <button type="submit" class="btn btn-primary text-sm">Preview Import</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection
