@extends('layouts.app')
@section('title', 'New Shipment')
@section('page_title', 'New Logistic Record')

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5">
    <a href="{{ route('crm.logistics.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Logistic CRM</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-2xl">🚛</div>
        <div>
          <h2 class="font-display font-bold text-slate-800 text-lg">Create Shipment Record</h2>
          <p class="text-slate-400 text-sm mt-0.5">Log a new delivery after order confirmation.</p>
        </div>
      </div>
    </div>

    <form method="POST" action="{{ route('crm.logistics.store') }}" class="divide-y divide-slate-100">
      @csrf

      {{-- Order Reference --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Order Reference</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Customer <span class="text-red-500">*</span></label>
            <select name="customer_id" class="form-input @error('customer_id') error @enderror" required id="field-customer">
              <option value="">— Select customer —</option>
              @foreach(App\Models\Customer::orderBy('name')->get() as $c)
                <option value="{{ $c->id }}" {{ old('customer_id', request('customer_id')) == $c->id ? 'selected' : '' }}>
                  {{ $c->name }} @if($c->phone)· {{ $c->phone }}@endif
                </option>
              @endforeach
            </select>
            @error('customer_id')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">Internal Order ID</label>
            <input type="text" name="order_id" value="{{ old('order_id') }}"
                   class="form-input" placeholder="e.g. ORD-2026-001" id="field-order-id">
          </div>
          @if($ebayOrders->count())
          <div class="sm:col-span-2">
            <label class="form-label">Link to eBay Order</label>
            <select name="ebay_order_id" class="form-input" id="field-ebay-order">
              <option value="">— None (website/direct sale) —</option>
              @foreach($ebayOrders as $o)
                <option value="{{ $o->id }}" {{ old('ebay_order_id', request('ebay_order_id')) == $o->id ? 'selected' : '' }}>
                  {{ $o->ebay_order_id ?? '#'.$o->id }} — {{ $o->customer?->name }} (${{ number_format($o->sale_amount) }})
                </option>
              @endforeach
            </select>
          </div>
          @endif
          <div>
            <label class="form-label">Product</label>
            <select name="product_id" class="form-input" id="field-product">
              <option value="">— None / see description —</option>
              @foreach($products as $p)
                <option value="{{ $p->id }}" {{ old('product_id') == $p->id ? 'selected' : '' }}>
                  {{ $p->category?->icon() }} {{ $p->name }}
                </option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Product Description</label>
            <input type="text" name="product_description" value="{{ old('product_description') }}"
                   class="form-input" placeholder="Extra notes on product / spec" id="field-product-desc">
          </div>
        </div>
      </div>

      {{-- Recipient / Delivery Details --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Recipient & Delivery Address</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Recipient Name <span class="text-red-500">*</span></label>
            <input type="text" name="recipient_name" value="{{ old('recipient_name') }}"
                   class="form-input" placeholder="Who receives the delivery?" id="field-recipient-name" required>
            @error('recipient_name')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">Recipient Phone <span class="text-red-500">*</span></label>
            <input type="tel" name="recipient_phone" value="{{ old('recipient_phone') }}"
                   class="form-input" placeholder="+61 4xx xxx xxx" id="field-recipient-phone" required>
            @error('recipient_phone')<p class="form-error">{{ $message }}</p>@enderror
          </div>
        </div>
        <div>
          <label class="form-label">Shipping / Delivery Address <span class="text-red-500">*</span></label>
          <textarea name="shipping_address" rows="3" class="form-input @error('shipping_address') error @enderror"
                    placeholder="Full delivery address including suburb, state, postcode" required>{{ old('shipping_address') }}</textarea>
          @error('shipping_address')<p class="form-error">{{ $message }}</p>@enderror
        </div>
      </div>

      {{-- Truck & Driver --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Truck & Driver (fill when confirmed)</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Truck Company</label>
            <input type="text" name="truck_company" value="{{ old('truck_company') }}"
                   class="form-input" placeholder="Company name" id="field-truck-company">
          </div>
          <div>
            <label class="form-label">Driver Name</label>
            <input type="text" name="driver_name" value="{{ old('driver_name') }}"
                   class="form-input" placeholder="Driver full name" id="field-driver-name">
          </div>
          <div>
            <label class="form-label">Driver Phone</label>
            <input type="tel" name="driver_phone" value="{{ old('driver_phone') }}"
                   class="form-input" placeholder="+61 4xx xxx xxx" id="field-driver-phone">
          </div>
          <div>
            <label class="form-label">Shipping Budget (AUD)</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
              <input type="number" name="shipping_budget" value="{{ old('shipping_budget') }}"
                     class="form-input pl-7" placeholder="0.00" step="0.01" id="field-budget">
            </div>
          </div>
        </div>
      </div>

      {{-- Dates & Assignment --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Schedule & Assignment</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Pickup Date & Time</label>
            <input type="datetime-local" name="pickup_datetime" value="{{ old('pickup_datetime') }}"
                   class="form-input" id="field-pickup">
          </div>
          <div>
            <label class="form-label">Estimated Arrival Date</label>
            <input type="date" name="estimated_arrival" value="{{ old('estimated_arrival') }}"
                   class="form-input" id="field-eta">
          </div>
          <div>
            <label class="form-label">Assigned Staff</label>
            <select name="assigned_to" class="form-input" id="field-assigned">
              <option value="">Unassigned</option>
              @foreach($users as $u)
                <option value="{{ $u->id }}" {{ old('assigned_to') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div>
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="2" class="form-input" placeholder="Any special instructions or notes…">{{ old('notes') }}</textarea>
        </div>
      </div>

      <div class="px-6 py-4 flex gap-3 justify-end bg-slate-50">
        <a href="{{ route('crm.logistics.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" id="btn-create-shipment">Create Shipment</button>
      </div>
    </form>
  </div>
</div>
@endsection
