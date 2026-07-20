@php
  $modeLabels = ['processing' => 'Process Trucking', 'loaded' => 'Loaded', 'delivered' => 'Delivered'];
  $modeRoutes = ['processing' => 'crm.logistics.processTrucking', 'loaded' => 'crm.logistics.loaded', 'delivered' => 'crm.logistics.delivered'];
@endphp
@extends('layouts.app')
@section('title', $modeLabels[$mode])
@section('page_title', $modeLabels[$mode])

@section('content')
<div class="animate-fade-in">
  {{-- ── Toolbar ──────────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex gap-2 flex-wrap">
      @foreach($modeLabels as $m => $label)
      <a href="{{ route($modeRoutes[$m]) }}" class="btn text-xs py-1.5 px-3 {{ $mode === $m ? 'btn-primary' : 'btn-secondary' }}">
        {{ $label }}
      </a>
      @endforeach
    </div>
    <div class="flex gap-2">
      <a href="{{ route('crm.logistics.shipments.index') }}" class="btn btn-secondary text-sm">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/></svg>
        Shipment Management
      </a>
      @if($mode === 'processing')
      <button type="button" onclick="document.getElementById('importCustomersModal').classList.remove('hidden')" class="btn btn-secondary text-sm">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        Import from Excel
      </button>
      @endif
    </div>
  </div>

  {{-- ── Search ────────────────────────────────────────────────────────────── --}}
  <form method="GET" action="{{ route($modeRoutes[$mode]) }}" class="card p-4 mb-5" x-data>
    <div class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[200px]">
        <label class="form-label text-xs">Search</label>
        <div class="relative">
          <input type="search" name="search" value="{{ request('search') }}"
                 @input.debounce.500ms="$el.closest('form').submit()"
                 placeholder="Recipient, phone, tracking #, shipment code…" class="form-input pl-9 py-2 text-sm">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        </div>
      </div>
    </div>
  </form>

  {{-- ── Table (customer-grain: Process Trucking / Loaded / Delivered) ───────
       Bulk-select customers — possibly from different shipments, or none yet
       — and move them all to the next status, a shipment, or delete. --}}
  @php
    $nextStatus = match ($mode) {
      'processing' => \App\Models\ShipmentCustomer::STATUS_IN_TRANSIT,
      'loaded'      => \App\Models\ShipmentCustomer::STATUS_IN_DELIVERY,
      'delivered'   => \App\Models\ShipmentCustomer::STATUS_DELIVERED,
    };
    $statusLabels = \App\Models\ShipmentCustomer::statuses();
  @endphp
  <div class="card p-0 overflow-hidden" x-data="{
    selected: [],
    bulkStatus: '{{ $nextStatus }}',
    bulkNotes: '',
    bulkShipmentId: '',
    newShipmentCode: '',
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
                <input type="hidden" name="redirect_status" value="{{ $mode }}">
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
              @if($mode === 'processing')
              <p class="text-slate-500 font-medium">No customers waiting to be loaded</p>
              <p class="text-slate-400 text-xs mt-1">Every customer has already been marked Loaded or Delivered.</p>
              @elseif($mode === 'loaded')
              <p class="text-slate-500 font-medium">No customers currently loaded or in delivery</p>
              <p class="text-slate-400 text-xs mt-1">Mark customers as Loaded from the Process Trucking page first.</p>
              @else
              <p class="text-slate-500 font-medium">No customers delivered yet</p>
              <p class="text-slate-400 text-xs mt-1">Mark customers as Delivered from the Loaded page once they arrive.</p>
              @endif
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
         class="sticky bottom-0 border-t border-slate-200 bg-white/95 backdrop-blur px-5 py-3 space-y-2">
      <span class="text-xs font-semibold text-slate-600" x-text="selected.length + ' selected'"></span>

      <div class="flex flex-wrap items-center gap-3">
        {{-- Change status --}}
        <form method="POST" action="{{ route('crm.logistics.shipments.customers.bulkStatus') }}" class="flex flex-wrap items-center gap-2"
              @submit="if (bulkStatus === '{{ \App\Models\ShipmentCustomer::STATUS_PROBLEM }}' && !bulkNotes.trim()) { $event.preventDefault(); alert('A note is required for Logistic issues (Problem status).'); }">
          @csrf
          <template x-for="id in selected" :key="id">
            <input type="hidden" name="customer_ids[]" :value="id">
          </template>
          <input type="hidden" name="redirect_status" value="{{ $mode }}">
          <select name="status" x-model="bulkStatus" class="form-input py-1.5 text-sm w-auto">
            @foreach(\App\Models\ShipmentCustomer::statuses() as $val => $lbl)
            <option value="{{ $val }}">{{ $lbl }}</option>
            @endforeach
          </select>
          <input type="text" name="notes" x-model="bulkNotes" x-show="bulkStatus === '{{ \App\Models\ShipmentCustomer::STATUS_PROBLEM }}'"
                 placeholder="Note explaining the issue (required)" class="form-input py-1.5 text-sm w-48">
          <button type="submit" class="btn btn-primary text-sm py-1.5" x-text="actionLabel"></button>
        </form>

        <div class="w-px h-6 bg-slate-200"></div>

        {{-- Add to shipment — existing shipment, or create a new one on the spot --}}
        <form method="POST" action="{{ route('crm.logistics.shipments.customers.assign') }}" class="flex flex-wrap items-center gap-2"
              @submit="if (!bulkShipmentId) { $event.preventDefault(); alert('Pick a shipment, or choose to create a new one.'); }">
          @csrf
          <template x-for="id in selected" :key="id">
            <input type="hidden" name="customer_ids[]" :value="id">
          </template>
          <input type="hidden" name="redirect_status" value="{{ $mode }}">
          <input type="hidden" name="shipment_id" :value="bulkShipmentId === '__new__' ? '' : bulkShipmentId">
          <input type="hidden" name="new_shipment_code" :value="bulkShipmentId === '__new__' ? newShipmentCode : ''">
          <select x-model="bulkShipmentId" class="form-input py-1.5 text-sm w-auto">
            <option value="">Add to shipment…</option>
            <option value="__new__">+ Create New Shipment</option>
            @foreach($assignableShipments as $s)
            <option value="{{ $s->id }}">{{ $s->shipment_code }}</option>
            @endforeach
          </select>
          <input type="text" x-model="newShipmentCode" x-show="bulkShipmentId === '__new__'"
                 placeholder="Shipment code (optional, auto-generated if blank)" class="form-input py-1.5 text-sm w-64">
          <button type="submit" class="btn btn-secondary text-sm py-1.5">Assign</button>
        </form>

        <div class="w-px h-6 bg-slate-200"></div>

        {{-- Delete --}}
        <form method="POST" action="{{ route('crm.logistics.shipments.customers.bulkDelete') }}"
              data-confirm="Delete the selected customer(s)? This cannot be undone." data-confirm-tone="danger">
          @csrf
          <template x-for="id in selected" :key="id">
            <input type="hidden" name="customer_ids[]" :value="id">
          </template>
          <input type="hidden" name="redirect_status" value="{{ $mode }}">
          <button type="submit" class="btn btn-danger text-sm py-1.5">Delete Selected</button>
        </form>
      </div>
    </div>
  </div>
</div>

@if($mode === 'processing')
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
