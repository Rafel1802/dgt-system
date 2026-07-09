@extends('layouts.app')
@section('title', 'Shipment ' . $shipment->shipment_code)
@section('page_title', 'Shipment Details')

@section('content')
<div class="animate-fade-in">
  <div class="mb-5 flex justify-between">
    <a href="{{ route('crm.logistics.shipments.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Shipments</a>
    <a href="{{ route('crm.logistics.shipments.edit', $shipment) }}" class="btn btn-secondary text-sm">Edit Shipment</a>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Left Col: Details --}}
    <div class="lg:col-span-1 space-y-6">
      <div class="card p-6">
        <div class="flex justify-between items-start mb-4">
          <h2 class="font-display font-bold text-slate-800 text-xl">{{ $shipment->shipment_code }}</h2>
          <span class="badge" style="background:{{ $shipment->statusColor() }}22; color:{{ $shipment->statusColor() }}">
            {{ $shipment->statusLabel() }}
          </span>
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
        <button onclick="document.getElementById('addCustomerModal').classList.remove('hidden')" class="btn btn-primary text-sm">
          + Add Customer
        </button>
      </div>

      <div class="card p-0 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                <th class="px-5 py-3 text-left">Customer</th>
                <th class="px-4 py-3 text-left">Machine / Attachment SKU</th>
                <th class="px-4 py-3 text-left">Shipping Address</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              @forelse($shipment->shipmentCustomers as $sc)
              <tr class="hover:bg-slate-50/70 transition-colors">
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
                  <p>{{ $sc->machine_sku ?: '—' }}</p>
                  @if($sc->attachment_sku)<p class="text-slate-400">+ {{ $sc->attachment_sku }}</p>@endif
                  @if($sc->product_description)<p class="text-slate-400 mt-0.5">{{ $sc->product_description }}</p>@endif
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
                  <div id="editCustomerModal{{ $sc->id }}" class="fixed inset-0 z-50 hidden bg-slate-900/50 flex items-center justify-center">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 text-left">
                      <form method="POST" action="{{ route('crm.logistics.shipments.customers.update', [$shipment, $sc]) }}">
                        @csrf @method('PUT')
                        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                          <h3 class="font-display font-bold text-lg text-slate-800">Edit Shipment Customer</h3>
                          <button type="button" onclick="document.getElementById('editCustomerModal{{ $sc->id }}').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                          </button>
                        </div>
                        <div class="p-6 space-y-4">
                          <div>
                            <label class="form-label">Customer</label>
                            @include('crm.partials.customer_combobox', [
                              'customers' => $customers,
                              'fieldId' => 'shipment-customer-'.$sc->id,
                              'fieldName' => 'customer_id',
                              'selected' => $sc->customer_id,
                              'required' => true,
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
                          <div class="grid grid-cols-2 gap-4">
                            <div>
                              <label class="form-label">Machine SKU</label>
                              <input type="text" name="machine_sku" value="{{ $sc->machine_sku }}" list="machine-sku-list" class="form-input">
                            </div>
                            <div>
                              <label class="form-label">Attachment SKU</label>
                              <input type="text" name="attachment_sku" value="{{ $sc->attachment_sku }}" list="attachment-sku-list" class="form-input">
                            </div>
                          </div>
                          <div>
                            <label class="form-label">Product Description</label>
                            <input type="text" name="product_description" value="{{ $sc->product_description }}" class="form-input">
                          </div>
                          <div>
                            <label class="form-label">Status</label>
                            <select name="status" class="form-input">
                              @foreach($custStatuses as $value => $label)
                              <option value="{{ $value }}" {{ $sc->status === $value ? 'selected' : '' }}>{{ $label }}</option>
                              @endforeach
                            </select>
                          </div>
                          @if($sc->status === \App\Models\ShipmentCustomer::STATUS_PROBLEM)
                          <div>
                            <label class="form-label">Problem Note</label>
                            <textarea name="notes" rows="2" class="form-input">{{ $sc->notes }}</textarea>
                          </div>
                          @endif
                        </div>
                        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 bg-slate-50 rounded-b-xl">
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
                <td colspan="5" class="text-center py-10">
                  <p class="text-slate-500 font-medium">No customers added to this shipment yet</p>
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- SKU autocomplete lists (shared by add/edit modals) --}}
<datalist id="machine-sku-list">
  @foreach($machineSkus as $sku)<option value="{{ $sku }}">@endforeach
</datalist>
<datalist id="attachment-sku-list">
  @foreach($attachmentSkus as $sku)<option value="{{ $sku }}">@endforeach
</datalist>

{{-- Add Customer Modal --}}
<div id="addCustomerModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 flex items-center justify-center">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 text-left">
    <form method="POST" action="{{ route('crm.logistics.shipments.customers.add', $shipment) }}">
      @csrf
      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
        <h3 class="font-display font-bold text-lg text-slate-800">Add Customer to Shipment</h3>
        <button type="button" onclick="document.getElementById('addCustomerModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="form-label">Customer <span class="text-red-500">*</span></label>
          @include('crm.partials.customer_combobox', [
            'customers' => $customers,
            'fieldId' => 'shipment-add-customer',
            'fieldName' => 'customer_id',
            'required' => true,
            'autofill' => true,
            'allowCreate' => true,
            'quickCreateSource' => \App\Enums\CustomerSource::Logistic->value,
            'autofillNameId' => 'add-recipient-name',
            'autofillPhoneId' => 'add-recipient-phone',
            'autofillAddressId' => 'add-shipping-address',
          ])
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
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Machine SKU</label>
            <input type="text" name="machine_sku" list="machine-sku-list" class="form-input" placeholder="Search or type new">
          </div>
          <div>
            <label class="form-label">Attachment SKU <span class="text-slate-400 normal-case font-normal">(optional)</span></label>
            <input type="text" name="attachment_sku" list="attachment-sku-list" class="form-input">
          </div>
        </div>
        <div>
          <label class="form-label">Product Description</label>
          <input type="text" name="product_description" class="form-input" placeholder="Item or Machine details...">
        </div>
      </div>
      <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 bg-slate-50 rounded-b-xl">
        <button type="button" onclick="document.getElementById('addCustomerModal').classList.add('hidden')" class="btn btn-secondary text-sm">Cancel</button>
        <button type="submit" class="btn btn-primary text-sm">Add Customer</button>
      </div>
    </form>
  </div>
</div>
@endsection
