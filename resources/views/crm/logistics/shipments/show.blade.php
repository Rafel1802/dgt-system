@extends('layouts.app')
@section('title', 'Shipment ' . $shipment->shipment_code)
@section('page_title', 'Shipment Details')

@section('content')
<div class="animate-fade-in">
  <div class="mb-5 flex justify-between">
    <a href="{{ route('crm.logistics.shipments.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Shipments</a>
    <a href="{{ route('crm.logistics.shipments.edit', $shipment) }}" class="btn btn-secondary text-sm">Edit Shipment</a>
  </div>

  @if(session('success'))
  <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm font-medium">
    {{ session('success') }}
  </div>
  @endif
  @if($errors->any())
  <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 text-sm font-medium">
    <ul class="space-y-1">
      @foreach($errors->all() as $error)<li>• {{ $error }}</li>@endforeach
    </ul>
  </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Left Col: Details --}}
    <div class="lg:col-span-1 space-y-6">
      <div class="card p-6">
        <div class="flex justify-between items-start mb-4 gap-3">
          <h2 class="font-display font-bold text-slate-800 text-xl">{{ $shipment->shipment_code }}</h2>
          @php $statusCounts = $shipment->customerStatusCounts(); @endphp
          @if(count($statusCounts) > 1)
          <div class="flex flex-wrap gap-1 justify-end">
            @foreach($statusCounts as $status => $count)
            @php $color = \App\Models\ShipmentCustomer::colorForStatus($status); @endphp
            <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $color }}22; color:{{ $color }}">
              {{ $count }} {{ \App\Models\ShipmentCustomer::statuses()[$status] ?? $status }}
            </span>
            @endforeach
          </div>
          @else
          <span class="badge" style="background:{{ $shipment->statusColor() }}22; color:{{ $shipment->statusColor() }}">
            {{ $shipment->statusLabel() }}
          </span>
          @endif
        </div>

        <div class="space-y-4">
          <div>
            <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Estimated Arrival</span>
            <p class="text-sm text-slate-800">{{ $shipment->estimated_arrival ? $shipment->estimated_arrival->format('d M Y') : '-' }}</p>
          </div>
          @if($shipment->actual_arrival)
          <div>
            <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Actual Arrival</span>
            <p class="text-sm text-slate-800">{{ $shipment->actual_arrival->format('d M Y') }}</p>
          </div>
          @endif
          <div>
            <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Handled By</span>
            @if($shipment->assignee)
              <div class="flex items-center gap-2">
                <img src="{{ $shipment->assignee->avatar_url }}" class="w-6 h-6 rounded-full" alt="">
                <div>
                  <p class="text-sm font-medium text-slate-800">{{ $shipment->assignee->name }}</p>
                  <p class="text-xs text-slate-500">{{ $shipment->assignee->crm_role_display }}</p>
                </div>
              </div>
            @else
              <span class="text-sm text-slate-500">Unassigned</span>
            @endif
          </div>
          
          <div class="pt-4 border-t border-slate-100">
            <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Trucking Company</span>
            @if($shipment->truckingCompany)
              <a href="{{ route('crm.logistics.trucking.show', $shipment->truckingCompany) }}" class="text-sm font-medium text-indigo-600 hover:underline">
                {{ $shipment->truckingCompany->company_name }}
              </a>
            @else
              <p class="text-sm text-slate-500">Unassigned</p>
            @endif
          </div>

          <div>
            <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Driver</span>
            @if($shipment->driver)
              <p class="text-sm text-slate-800">{{ $shipment->driver->name }}</p>
              @if($shipment->driver->phone)<p class="text-xs text-slate-500">{{ $shipment->driver->phone }}</p>@endif
            @else
              <p class="text-sm text-slate-500">Unassigned</p>
            @endif
          </div>

          @if($shipment->notes)
          <div>
            <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Notes</span>
            <div class="text-sm text-slate-600 whitespace-pre-wrap">{{ $shipment->notes }}</div>
          </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Right Col: Customers in Shipment --}}
    <div class="lg:col-span-2">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h3 class="font-display font-bold text-slate-800 text-lg">Customers in Shipment</h3>
        <div class="flex gap-2">
          <button onclick="document.getElementById('addFromProcessTruckingModal').classList.remove('hidden')" class="btn btn-secondary text-sm">
            + From Process Trucking
          </button>
          <button onclick="document.getElementById('addCustomerModal').classList.remove('hidden')" class="btn btn-primary text-sm">
            + Add Customer
          </button>
        </div>
      </div>

      <div class="card p-0 overflow-hidden" x-data="{
        selected: [],
        bulkStatus: '{{ \App\Models\ShipmentCustomer::STATUS_IN_TRANSIT }}',
        bulkNotes: '',
        statusLabels: {{ Js::from(\App\Models\ShipmentCustomer::statuses()) }},
        get allChecked() { return {{ $shipment->shipmentCustomers->count() }} > 0 && this.selected.length === {{ $shipment->shipmentCustomers->count() }}; },
        get actionLabel() { return 'Mark as ' + (this.statusLabels[this.bulkStatus] || this.bulkStatus); },
        toggleAll(e) { this.selected = e.target.checked ? {{ Js::from($shipment->shipmentCustomers->pluck('id')) }} : []; },
      }">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                <th class="px-5 py-3 w-10">
                  <input type="checkbox" class="accent-indigo-600 w-4 h-4" :checked="allChecked" @change="toggleAll($event)">
                </th>
                <th class="px-5 py-3 text-left">Customer</th>
                <th class="px-4 py-3 text-left">Products</th>
                <th class="px-4 py-3 text-left">Shipping Address</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              @forelse($shipment->shipmentCustomers as $sc)
              <tr class="hover:bg-slate-50/70 transition-colors">
                <td class="px-5 py-3">
                  <input type="checkbox" class="accent-indigo-600 w-4 h-4" value="{{ $sc->id }}" x-model="selected">
                </td>
                <td class="px-5 py-3">
                  <p class="font-semibold text-slate-800">{{ $sc->customer?->name ?? '—' }}</p>
                  @if($sc->recipient_name)
                    <p class="text-xs text-slate-500">Recp: {{ $sc->recipient_name }}</p>
                  @endif
                  @if($sc->recipient_phone)
                    <p class="text-xs text-slate-400">Phone: {{ $sc->recipient_phone }}</p>
                  @endif
                </td>
                <td class="px-4 py-3 text-slate-600 text-xs">
                  @forelse($sc->products as $p)
                    <p>{{ $p->product_name }}{{ $p->sku ? ' (' . $p->sku . ')' : '' }} × {{ $p->quantity }}</p>
                  @empty
                    <p>—</p>
                  @endforelse
                </td>
                <td class="px-4 py-3 text-xs text-slate-600 max-w-xs truncate" title="{{ $sc->shipping_address }}">
                  {{ $sc->shipping_address ?? '—' }}
                </td>
                <td class="px-4 py-3">
                  <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $sc->statusColor() }}22; color:{{ $sc->statusColor() }}">
                    {{ $sc->statusLabel() }}
                  </span>
                  @if($sc->status === \App\Models\ShipmentCustomer::STATUS_PROBLEM && $sc->notes)
                    <p class="text-xs text-red-500 mt-1">{{ $sc->notes }}</p>
                  @endif
                  @if($sc->tracking_number)
                    <p class="text-xs text-slate-500 mt-1">📦 {{ $sc->tracking_number }}</p>
                  @endif
                </td>
                <td class="px-4 py-3">
                  <div class="flex justify-end gap-1">
                    <button onclick="document.getElementById('editCustomerModal{{ $sc->id }}').classList.remove('hidden')" class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="Edit">
                      <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                    </button>
                    <form method="POST" action="{{ route('crm.logistics.shipments.customers.remove', [$shipment, $sc]) }}"
                          data-confirm="Remove this customer from the shipment?" data-confirm-tone="danger" class="inline">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn btn-danger btn-icon" style="width:28px;height:28px;" title="Remove">
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                      </button>
                    </form>
                  </div>
                  
                  {{-- Edit Modal --}}
                  <div id="editCustomerModal{{ $sc->id }}" class="fixed inset-0 z-50 hidden bg-slate-900/50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col text-left">
                      <form method="POST" action="{{ route('crm.logistics.shipments.customers.update', [$shipment, $sc]) }}"
                            x-data="{
                              status: '{{ $sc->status }}',
                              lines: {{ $sc->products->isNotEmpty() ? Js::from($sc->products->map(fn($p) => ['product_id' => $p->product_id, 'product_name' => $p->product_name, 'price' => $p->price, 'quantity' => $p->quantity])) : Js::from([['product_id' => null, 'product_name' => '', 'price' => '', 'quantity' => 1]]) }},
                              catalog: {{ Js::from($catalogProducts->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'price' => $p->price])) }},
                              addLine() { this.lines.push({ product_id: null, product_name: '', price: '', quantity: 1 }); },
                              removeLine(i) { if (this.lines.length > 1) this.lines.splice(i, 1); },
                              matchCatalogProduct(line) {
                                const typed = (line.product_name || '').trim().toLowerCase();
                                const match = this.catalog.find(p => p.name.toLowerCase() === typed);
                                if (match) {
                                  line.product_id = match.id;
                                  if (!line.price) line.price = match.price;
                                } else {
                                  line.product_id = null;
                                }
                              },
                              fillLatestOrderProduct(name) {
                                // Product name only, from the newly-picked customer/lead's most recent
                                // order — deliberately skips matchCatalogProduct() so price never carries over.
                                // Only runs when staff actively re-picks someone in the combobox below, so
                                // it never silently overwrites this row's already-saved product on open.
                                if (!name) return;
                                this.lines[0].product_name = name;
                                this.lines[0].product_id = null;
                                this.lines[0].price = '';
                              },
                            }"
                            x-on:dgt:latest-order-product="if ($event.detail.fieldId === 'shipment-customer-{{ $sc->id }}') fillLatestOrderProduct($event.detail.productName)"
                            class="flex flex-col min-h-0">
                        @csrf @method('PUT')
                        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center shrink-0">
                          <h3 class="font-display font-bold text-lg text-slate-800">Edit Shipment Customer</h3>
                          <button type="button" onclick="document.getElementById('editCustomerModal{{ $sc->id }}').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                          </button>
                        </div>
                        <div class="p-6 space-y-4 overflow-y-auto min-h-0">
                          <div>
                            <label class="form-label">Customer</label>
                            @include('crm.partials.customer_combobox', [
                              'customers' => $customers,
                              'leads' => $leads,
                              'includeLatestOrder' => true,
                              'fieldId' => 'shipment-customer-'.$sc->id,
                              'fieldName' => 'customer_id',
                              'selected' => $sc->customer_id,
                              'autofill' => true,
                              'allowCreate' => true,
                              'quickCreateSource' => \App\Enums\CustomerSource::Logistic->value,
                              'autofillNameId' => 'edit-recipient-name-'.$sc->id,
                              'autofillPhoneId' => 'edit-recipient-phone-'.$sc->id,
                              'autofillAddressId' => 'edit-shipping-address-'.$sc->id,
                            ])
                          </div>
                          <div class="grid grid-cols-2 gap-4">
                            <div>
                              <label class="form-label">Recipient Name</label>
                              <input type="text" id="edit-recipient-name-{{ $sc->id }}" name="recipient_name" value="{{ $sc->recipient_name }}" class="form-input">
                            </div>
                            <div>
                              <label class="form-label">Recipient Phone</label>
                              <input type="text" id="edit-recipient-phone-{{ $sc->id }}" name="recipient_phone" value="{{ $sc->recipient_phone }}" class="form-input">
                            </div>
                          </div>
                          <div>
                            <label class="form-label">Recipient Email</label>
                            <input type="email" name="recipient_email" value="{{ $sc->recipient_email }}" class="form-input" placeholder="Used to match against CRM/eBay if marked Problem">
                          </div>
                          <div>
                            <label class="form-label">Shipping Address</label>
                            <textarea id="edit-shipping-address-{{ $sc->id }}" name="shipping_address" rows="2" class="form-input">{{ $sc->shipping_address }}</textarea>
                          </div>
                          <div>
                            <label class="form-label">Products</label>
                            <div class="space-y-2">
                              <template x-for="(line, i) in lines" :key="i">
                                <div class="flex gap-2 items-start">
                                  <input type="text" list="shipment-catalog-products" :name="`products[${i}][product_name]`" x-model="line.product_name" @input="matchCatalogProduct(line)"
                                         placeholder="Search or type a product" class="form-input flex-1">
                                  <input type="hidden" :name="`products[${i}][product_id]`" :value="line.product_id">
                                  <input type="number" step="0.01" min="0" :name="`products[${i}][price]`" x-model="line.price" placeholder="Price" class="form-input w-24">
                                  <input type="number" min="1" :name="`products[${i}][quantity]`" x-model.number="line.quantity" placeholder="Qty" class="form-input w-16">
                                  <button type="button" @click="removeLine(i)" x-show="lines.length > 1"
                                          class="btn btn-secondary btn-icon text-red-400 hover:text-red-600 shrink-0" style="width:38px;height:38px;">
                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                  </button>
                                </div>
                              </template>
                            </div>
                            <button type="button" @click="addLine()" class="btn btn-secondary text-xs mt-2">+ Add Another Product</button>
                          </div>
                          <div>
                            <label class="form-label">Status</label>
                            <select name="status" class="form-input" x-model="status">
                              @foreach($custStatuses as $value => $label)
                              <option value="{{ $value }}" {{ $sc->status === $value ? 'selected' : '' }}>{{ $label }}</option>
                              @endforeach
                            </select>
                          </div>
                          <div x-show="status === 'delivered'" x-cloak>
                            <label class="form-label">Tracking Number <span class="text-slate-400 normal-case font-normal">(optional)</span></label>
                            <input type="text" name="tracking_number" value="{{ $sc->tracking_number }}" class="form-input" placeholder="Leave blank if not available">
                          </div>
                          <div>
                            <label class="form-label">Note <span class="text-red-500" x-show="status === 'problem'" x-cloak>*</span></label>
                            <textarea name="notes" rows="2" class="form-input">{{ $sc->notes }}</textarea>
                            <p class="text-xs text-slate-400 mt-1" x-show="status === 'problem'" x-cloak>Required for Logistic issues (Problem status).</p>
                          </div>
                        </div>
                        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 bg-slate-50 rounded-b-xl shrink-0">
                          <button type="button" onclick="document.getElementById('editCustomerModal{{ $sc->id }}').classList.add('hidden')" class="btn btn-secondary text-sm">Cancel</button>
                          <button type="submit" class="btn btn-primary text-sm">Save Changes</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="6" class="text-center py-10">
                  <p class="text-slate-500 font-medium">No customers added to this shipment yet</p>
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        {{-- ── Sticky bulk-action bar ─────────────────────────────────────── --}}
        <div x-show="selected.length > 0" x-cloak x-transition
             class="sticky bottom-0 border-t border-slate-200 bg-white/95 backdrop-blur px-5 py-3 space-y-2">
          <span class="text-xs font-semibold text-slate-600" x-text="selected.length + ' selected'"></span>

          <div class="flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('crm.logistics.shipments.customers.bulkStatus') }}" class="flex flex-wrap items-center gap-2"
                  @submit="if (bulkStatus === '{{ \App\Models\ShipmentCustomer::STATUS_PROBLEM }}' && !bulkNotes.trim()) { $event.preventDefault(); alert('A note is required for Logistic issues (Problem status).'); }">
              @csrf
              <template x-for="id in selected" :key="id">
                <input type="hidden" name="customer_ids[]" :value="id">
              </template>
              <input type="hidden" name="redirect_shipment_id" value="{{ $shipment->id }}">
              <select name="status" x-model="bulkStatus" class="form-input py-1.5 text-sm w-auto">
                @foreach($custStatuses as $val => $lbl)
                <option value="{{ $val }}">{{ $lbl }}</option>
                @endforeach
              </select>
              <input type="text" name="notes" x-model="bulkNotes" x-show="bulkStatus === '{{ \App\Models\ShipmentCustomer::STATUS_PROBLEM }}'"
                     placeholder="Note explaining the issue (required)" class="form-input py-1.5 text-sm w-48">
              <button type="submit" class="btn btn-primary text-sm py-1.5" x-text="actionLabel"></button>
            </form>

            <div class="w-px h-6 bg-slate-200"></div>

            <form method="POST" action="{{ route('crm.logistics.shipments.customers.bulkDelete') }}"
                  data-confirm="Delete the selected customer(s)? This cannot be undone." data-confirm-tone="danger">
              @csrf
              <template x-for="id in selected" :key="id">
                <input type="hidden" name="customer_ids[]" :value="id">
              </template>
              <input type="hidden" name="redirect_shipment_id" value="{{ $shipment->id }}">
              <button type="submit" class="btn btn-danger text-sm py-1.5">Delete Selected</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<datalist id="shipment-catalog-products">
  @foreach($catalogProducts as $p)
  <option value="{{ $p->name }}">{{ $p->sku ? '('.$p->sku.')' : '' }} — ${{ number_format($p->price, 2) }}</option>
  @endforeach
</datalist>

{{-- Add from Process Trucking Modal — search + multi-select unassigned records, assign to this shipment --}}
<div id="addFromProcessTruckingModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col text-left"
       x-data="{
         search: '',
         selected: [],
         records: {{ Js::from($unassignedCustomers->map(fn ($sc) => [
             'id' => $sc->id,
             'name' => $sc->recipient_name,
             'phone' => $sc->recipient_phone,
             'product' => $sc->products->pluck('product_name')->filter()->implode(', '),
         ])) }},
         get filtered() {
           if (!this.search) return this.records;
           const q = this.search.toLowerCase();
           return this.records.filter(r => (r.name + ' ' + (r.phone||'') + ' ' + (r.product||'')).toLowerCase().includes(q));
         },
         toggle(id) {
           this.selected = this.selected.includes(id) ? this.selected.filter(x => x !== id) : [...this.selected, id];
         },
       }">
    <form method="POST" action="{{ route('crm.logistics.shipments.customers.assign') }}" class="flex flex-col min-h-0"
          @submit="if (selected.length === 0) { $event.preventDefault(); alert('Pick at least one customer.'); }">
      @csrf
      <input type="hidden" name="shipment_id" value="{{ $shipment->id }}">
      <input type="hidden" name="redirect_shipment_id" value="{{ $shipment->id }}">
      <template x-for="id in selected" :key="id">
        <input type="hidden" name="customer_ids[]" :value="id">
      </template>
      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center shrink-0">
        <h3 class="font-display font-bold text-lg text-slate-800">Add from Process Trucking</h3>
        <button type="button" onclick="document.getElementById('addFromProcessTruckingModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 space-y-3 overflow-y-auto min-h-0">
        <input type="search" x-model="search" placeholder="Search name, phone, product…" class="form-input">
        <div class="border border-slate-200 rounded-xl divide-y divide-slate-100 max-h-72 overflow-y-auto">
          <template x-for="r in filtered" :key="r.id">
            <label class="flex items-center gap-3 px-3 py-2.5 hover:bg-slate-50 cursor-pointer">
              <input type="checkbox" class="accent-indigo-600 w-4 h-4" :checked="selected.includes(r.id)" @change="toggle(r.id)">
              <span class="flex-1 min-w-0">
                <span class="block text-sm font-semibold text-slate-800" x-text="r.name"></span>
                <span class="block text-xs text-slate-400" x-text="[r.phone, r.product].filter(Boolean).join(' — ')"></span>
              </span>
            </label>
          </template>
          <div x-show="filtered.length === 0" class="px-3 py-6 text-sm text-slate-400 text-center">No unassigned Process Trucking customers found</div>
        </div>
        <p class="text-xs text-slate-400" x-text="selected.length + ' selected'"></p>
      </div>
      <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 bg-slate-50 rounded-b-xl shrink-0">
        <button type="button" onclick="document.getElementById('addFromProcessTruckingModal').classList.add('hidden')" class="btn btn-secondary text-sm">Cancel</button>
        <button type="submit" class="btn btn-primary text-sm">Add Selected</button>
      </div>
    </form>
  </div>
</div>

{{-- Add Customer Modal --}}
<div id="addCustomerModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col text-left">
    <form method="POST" action="{{ route('crm.logistics.shipments.customers.add', $shipment) }}"
          x-data="{
            lines: [{ product_id: null, product_name: '', price: '', quantity: 1 }],
            catalog: {{ Js::from($catalogProducts->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'price' => $p->price])) }},
            addLine() { this.lines.push({ product_id: null, product_name: '', price: '', quantity: 1 }); },
            removeLine(i) { if (this.lines.length > 1) this.lines.splice(i, 1); },
            matchCatalogProduct(line) {
              const typed = (line.product_name || '').trim().toLowerCase();
              const match = this.catalog.find(p => p.name.toLowerCase() === typed);
              if (match) {
                line.product_id = match.id;
                if (!line.price) line.price = match.price;
              } else {
                line.product_id = null;
              }
            },
            fillLatestOrderProduct(name) {
              // Product name only, from the selected customer/lead's most recent order —
              // deliberately skips matchCatalogProduct() so no price ever gets carried over.
              if (!name) return;
              this.lines[0].product_name = name;
              this.lines[0].product_id = null;
              this.lines[0].price = '';
            },
          }"
          x-on:dgt:latest-order-product="if ($event.detail.fieldId === 'shipment-add-customer') fillLatestOrderProduct($event.detail.productName)"
          class="flex flex-col min-h-0">
      @csrf
      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center shrink-0">
        <h3 class="font-display font-bold text-lg text-slate-800">Add Customer to Shipment</h3>
        <button type="button" onclick="document.getElementById('addCustomerModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 space-y-4 overflow-y-auto min-h-0">
        <div>
          <label class="form-label">Customer <span class="text-red-500">*</span></label>
          @include('crm.partials.customer_combobox', [
            'customers' => $customers,
            'leads' => $leads,
            'includeLatestOrder' => true,
            'fieldId' => 'shipment-add-customer',
            'fieldName' => 'customer_id',
            'autofill' => true,
            'allowCreate' => true,
            'quickCreateSource' => \App\Enums\CustomerSource::Logistic->value,
            'autofillNameId' => 'add-recipient-name',
            'autofillPhoneId' => 'add-recipient-phone',
            'autofillAddressId' => 'add-shipping-address',
          ])
          <p class="mt-1 text-xs text-slate-400">Pick an existing customer, or a lead who hasn't been converted yet — either way their info and most recent order's product autofill below.</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Recipient Name</label>
            <input type="text" id="add-recipient-name" name="recipient_name" class="form-input" placeholder="Leave blank if same as customer">
          </div>
          <div>
            <label class="form-label">Recipient Phone</label>
            <input type="text" id="add-recipient-phone" name="recipient_phone" class="form-input" placeholder="Leave blank if same as customer">
          </div>
        </div>
        <div>
          <label class="form-label">Recipient Email</label>
          <input type="email" name="recipient_email" class="form-input" placeholder="Used to match against CRM/eBay if marked Problem">
        </div>
        <div>
          <label class="form-label">Shipping Address</label>
          <textarea id="add-shipping-address" name="shipping_address" rows="2" class="form-input" placeholder="Leave blank if same as customer"></textarea>
        </div>
        <div>
          <label class="form-label">Tracking Number (optional)</label>
          <input type="text" name="tracking_number" class="form-input" placeholder="Leave blank if not available yet">
        </div>
        <div>
          <label class="form-label">Products</label>
          <div class="space-y-2">
            <template x-for="(line, i) in lines" :key="i">
              <div class="flex gap-2 items-start">
                <input type="text" list="shipment-catalog-products" :name="`products[${i}][product_name]`" x-model="line.product_name" @input="matchCatalogProduct(line)"
                       placeholder="Search or type a product" class="form-input flex-1">
                <input type="hidden" :name="`products[${i}][product_id]`" :value="line.product_id">
                <input type="number" step="0.01" min="0" :name="`products[${i}][price]`" x-model="line.price" placeholder="Price" class="form-input w-24">
                <input type="number" min="1" :name="`products[${i}][quantity]`" x-model.number="line.quantity" placeholder="Qty" class="form-input w-16">
                <button type="button" @click="removeLine(i)" x-show="lines.length > 1"
                        class="btn btn-secondary btn-icon text-red-400 hover:text-red-600 shrink-0" style="width:38px;height:38px;">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
              </div>
            </template>
          </div>
          <button type="button" @click="addLine()" class="btn btn-secondary text-xs mt-2">+ Add Another Product</button>
        </div>
      </div>
      <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 bg-slate-50 rounded-b-xl shrink-0">
        <button type="button" onclick="document.getElementById('addCustomerModal').classList.add('hidden')" class="btn btn-secondary text-sm">Cancel</button>
        <button type="submit" class="btn btn-primary text-sm">Add Customer</button>
      </div>
    </form>
  </div>
</div>
@endsection
