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
      @if(session()->has('shipment_import_failed_rows') && count(session('shipment_import_failed_rows')) > 0)
      <a href="{{ route('crm.logistics.shipments.customers.import.failed') }}" class="btn btn-secondary text-sm">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v6.75m0 0-3-3m3 3 3-3m-8.25 6h10.5a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-1.5m-9 0H5.25A2.25 2.25 0 0 0 3 5.25v9a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
        Download Failed Rows ({{ count(session('shipment_import_failed_rows')) }})
      </a>
      @endif
      @endif
      @if($mode === 'delivered')
      <a href="{{ route('crm.logistics.delivered.export', request()->only('search')) }}" class="btn btn-secondary text-sm">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        Export CSV
      </a>
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
      @if($mode === 'delivered')
      <div class="min-w-[160px]">
        <label class="form-label text-xs">Sort by</label>
        <select name="sort_by" class="form-input py-2 text-sm" @change="$el.closest('form').submit()">
          <option value="delivery" {{ $sortBy === 'delivery' ? 'selected' : '' }}>Delivery Date</option>
          <option value="purchase" {{ $sortBy === 'purchase' ? 'selected' : '' }}>Purchase Date</option>
        </select>
      </div>
      @endif
    </div>
  </form>

  {{-- ── Table (customer-grain: Process Trucking / Loaded / Delivered) ─────── --}}
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
    bulkIssueDate: '{{ now()->toDateString() }}',
    bulkShipmentId: '',
    newShipmentCode: '',
    statusLabels: {{ Js::from($statusLabels) }},
    editModal: false,
    editingCustomer: {
      id: '',
      recipient_name: '',
      recipient_phone: '',
      recipient_email: '',
      shipping_address: '',
      tracking_number: '',
      status: 'pending',
      handled_by: '',
      shipment_id: '',
      notes: '',
      issue_date: '{{ now()->toDateString() }}',
      products: [],
    },
    openEditModal(sc) {
      this.editingCustomer = {
        id: sc.id,
        recipient_name: sc.recipient_name || '',
        recipient_phone: sc.recipient_phone || '',
        recipient_email: sc.recipient_email || '',
        shipping_address: sc.shipping_address || '',
        tracking_number: sc.tracking_number || '',
        status: sc.status || 'pending',
        handled_by: sc.handled_by || '',
        shipment_id: sc.shipment_id || '',
        notes: sc.notes || '',
        issue_date: '{{ now()->toDateString() }}',
        products: sc.products && sc.products.length ? JSON.parse(JSON.stringify(sc.products)) : [{ product_name: '', quantity: 1, price: 0 }],
      };
      this.editModal = true;
    },
    addProductRow() {
      this.editingCustomer.products.push({ product_name: '', quantity: 1, price: 0 });
    },
    removeProductRow(index) {
      this.editingCustomer.products.splice(index, 1);
    },
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
            @if($mode === 'delivered')
            <th class="px-4 py-3">Purchase Date</th>
            <th class="px-4 py-3">Follow-up History</th>
            <th class="px-4 py-3">Notes</th>
            @endif
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
            @if($mode === 'delivered')
            <td class="px-4 py-3 text-slate-500 text-xs">
              {{ $sc->customer?->first_purchase_date?->format('d/m/Y') ?? '—' }}
            </td>
            <td class="px-4 py-3 text-slate-500 text-xs">
              @forelse(($sc->customer?->interactions ?? []) as $interaction)
                <p class="truncate max-w-[160px]" title="{{ $interaction->content }}">{{ $interaction->interacted_at?->format('d/m/Y') }} — {{ \Illuminate\Support\Str::limit($interaction->content, 30) }}</p>
              @empty
                <span>—</span>
              @endforelse
            </td>
            <td class="px-4 py-3 text-slate-500 text-xs">
              <p class="truncate max-w-[160px]" title="{{ $sc->notes }}">{{ $sc->notes ?: '—' }}</p>
            </td>
            @endif
            <td class="px-4 py-3">
              <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $sc->statusColor() }}22; color:{{ $sc->statusColor() }}">
                {{ $sc->statusLabel() }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex justify-end items-center gap-1">
                <button type="button" @click="openEditModal({{ Js::from($sc) }})" class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="Edit">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                </button>
                <form method="POST" action="{{ route('crm.logistics.shipments.customers.destroy', $sc) }}"
                      data-confirm="Delete this customer record? This cannot be undone." data-confirm-tone="danger" class="inline">
                  @csrf @method('DELETE')
                  <input type="hidden" name="redirect_status" value="{{ $mode }}">
                  <button type="submit" class="btn btn-danger btn-icon" style="width:28px;height:28px;" title="Delete">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="{{ $mode === 'delivered' ? 11 : 8 }}" class="text-center py-14">
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
        <form method="POST" action="{{ route('crm.logistics.shipments.customers.bulkStatus') }}" class="flex flex-wrap items-center gap-2"
              @submit="if (bulkStatus === '{{ \App\Models\ShipmentCustomer::STATUS_PROBLEM }}') { if (!bulkNotes.trim()) { $event.preventDefault(); alert('A note is required for Logistic issues (Problem status).'); } else if (!bulkIssueDate) { $event.preventDefault(); alert('An issue date is required for Logistic issues (Problem status).'); } }">
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
          <input type="date" name="issue_date" x-model="bulkIssueDate" x-show="bulkStatus === '{{ \App\Models\ShipmentCustomer::STATUS_PROBLEM }}'" class="form-input py-1.5 text-sm w-36">
          <input type="text" name="notes" x-model="bulkNotes" x-show="bulkStatus === '{{ \App\Models\ShipmentCustomer::STATUS_PROBLEM }}'"
                 placeholder="Note explaining the issue (required)" class="form-input py-1.5 text-sm w-48">
          <button type="submit" class="btn btn-primary text-sm py-1.5" x-text="actionLabel"></button>
        </form>

        <div class="w-px h-6 bg-slate-200"></div>

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

    {{-- ── Edit Customer Modal ─────────────────────────────────────────────── --}}
    <div x-show="editModal" x-cloak class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden text-left my-8" @click.away="editModal = false">
        <form :action="'{{ route('crm.logistics.shipments.customers.updateDirect', 0) }}'.replace('/0/', '/' + editingCustomer.id + '/')" method="POST">
          @csrf
          @method('PUT')
          <input type="hidden" name="redirect_status" value="{{ $mode }}">

          <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <div>
              <h3 class="font-bold text-lg text-slate-800">Edit Logistics Customer</h3>
              <p class="text-xs text-slate-400">Update customer details, shipment, tracking, and status.</p>
            </div>
            <button type="button" @click="editModal = false" class="text-slate-400 hover:text-slate-600">
              <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="form-label text-xs font-semibold">Recipient Name <span class="text-rose-500">*</span></label>
                <input type="text" name="recipient_name" x-model="editingCustomer.recipient_name" required class="form-input text-sm">
              </div>
              <div>
                <label class="form-label text-xs font-semibold">Recipient Phone</label>
                <input type="text" name="recipient_phone" x-model="editingCustomer.recipient_phone" class="form-input text-sm">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="form-label text-xs font-semibold">Recipient Email</label>
                <input type="email" name="recipient_email" x-model="editingCustomer.recipient_email" class="form-input text-sm">
              </div>
              <div>
                <label class="form-label text-xs font-semibold">Tracking #</label>
                <input type="text" name="tracking_number" x-model="editingCustomer.tracking_number" class="form-input text-sm">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="form-label text-xs font-semibold">Status <span class="text-rose-500">*</span></label>
                <select name="status" x-model="editingCustomer.status" required class="form-input text-sm">
                  @foreach(\App\Models\ShipmentCustomer::statuses() as $val => $lbl)
                  <option value="{{ $val }}">{{ $lbl }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="form-label text-xs font-semibold">Handled By</label>
                <select name="handled_by" x-model="editingCustomer.handled_by" class="form-input text-sm">
                  <option value="">Unassigned</option>
                  @foreach($crmUsers as $u)
                  <option value="{{ $u->id }}">{{ $u->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="form-label text-xs font-semibold">Assigned Shipment</label>
                <select name="shipment_id" x-model="editingCustomer.shipment_id" class="form-input text-sm">
                  <option value="">Unassigned</option>
                  @foreach($assignableShipments as $s)
                  <option value="{{ $s->id }}">{{ $s->shipment_code }}</option>
                  @endforeach
                </select>
              </div>
              <div x-show="editingCustomer.status === '{{ \App\Models\ShipmentCustomer::STATUS_PROBLEM }}'">
                <label class="form-label text-xs font-semibold">Issue Date <span class="text-rose-500">*</span></label>
                <input type="date" name="issue_date" x-model="editingCustomer.issue_date" class="form-input text-sm">
              </div>
            </div>

            <div>
              <label class="form-label text-xs font-semibold">Shipping Address</label>
              <textarea name="shipping_address" x-model="editingCustomer.shipping_address" rows="2" class="form-input text-sm"></textarea>
            </div>

            <div x-show="editingCustomer.status === '{{ \App\Models\ShipmentCustomer::STATUS_PROBLEM }}'">
              <label class="form-label text-xs font-semibold">Logistics Problem Notes <span class="text-rose-500">*</span></label>
              <textarea name="notes" x-model="editingCustomer.notes" rows="2" placeholder="Required for Logistic issues" class="form-input text-sm"></textarea>
            </div>

            {{-- Product Items --}}
            <div class="pt-3 border-t border-slate-100">
              <div class="flex items-center justify-between mb-2">
                <label class="form-label text-xs font-bold uppercase tracking-wider text-slate-500">Products</label>
                <button type="button" @click="addProductRow()" class="text-xs text-indigo-600 hover:underline font-semibold">+ Add Product</button>
              </div>
              <div class="space-y-2">
                <template x-for="(prod, idx) in editingCustomer.products" :key="idx">
                  <div class="flex items-center gap-2">
                    <input type="text" :name="'products[' + idx + '][product_name]'" x-model="prod.product_name" placeholder="Product name" class="form-input text-xs flex-1">
                    <input type="number" :name="'products[' + idx + '][quantity]'" x-model="prod.quantity" placeholder="Qty" min="1" class="form-input text-xs w-16">
                    <input type="number" step="0.01" :name="'products[' + idx + '][price]'" x-model="prod.price" placeholder="Price" class="form-input text-xs w-24">
                    <button type="button" @click="removeProductRow(idx)" class="text-rose-500 hover:text-rose-700 p-1">
                      <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                  </div>
                </template>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 bg-slate-50">
            <button type="button" @click="editModal = false" class="btn btn-secondary text-sm">Cancel</button>
            <button type="submit" class="btn btn-primary text-sm">Save Changes</button>
          </div>
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
        <button type="button" onclick="document.getElementById('importCustomersModal').classList.add('hidden')" class="btn btn-cancel btn-secondary text-sm">Cancel</button>
        <button type="submit" class="btn btn-primary text-sm">Preview Import</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection
