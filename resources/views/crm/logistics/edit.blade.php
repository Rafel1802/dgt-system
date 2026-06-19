@extends('layouts.app')
@section('title', 'Edit Shipment')
@section('page_title', 'Edit Shipment')

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5">
    <a href="{{ route('crm.logistics.show', $logistic) }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Shipment</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100 flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center text-2xl"
           style="background:{{ $logistic->status?->color() }}22">
        {{ $logistic->status?->icon() }}
      </div>
      <div>
        <h2 class="font-display font-bold text-slate-800">{{ $logistic->order_id ?? 'Shipment #'.$logistic->id }}</h2>
        <span class="text-xs font-semibold" style="color:{{ $logistic->status?->color() }}">{{ $logistic->status?->label() }}</span>
      </div>
    </div>

    <form method="POST" action="{{ route('crm.logistics.update', $logistic) }}" class="divide-y divide-slate-100">
      @csrf @method('PUT')

      {{-- Recipient & Address --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Recipient & Delivery Address</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Recipient Name <span class="text-red-500">*</span></label>
            <input type="text" name="recipient_name" value="{{ old('recipient_name', $logistic->recipient_name) }}" class="form-input" required>
          </div>
          <div>
            <label class="form-label">Recipient Phone <span class="text-red-500">*</span></label>
            <input type="tel" name="recipient_phone" value="{{ old('recipient_phone', $logistic->recipient_phone) }}" class="form-input" required>
          </div>
        </div>
        <div>
          <label class="form-label">Shipping Address <span class="text-red-500">*</span></label>
          <textarea name="shipping_address" rows="3" class="form-input" required>{{ old('shipping_address', $logistic->shipping_address) }}</textarea>
        </div>
      </div>

      {{-- Product --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Product</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Product</label>
            <select name="product_id" class="form-input">
              <option value="">— None —</option>
              @foreach($products as $p)
                <option value="{{ $p->id }}" {{ old('product_id', $logistic->product_id) == $p->id ? 'selected' : '' }}>
                  {{ $p->category?->icon() }} {{ $p->name }}
                </option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Product Description</label>
            <input type="text" name="product_description" value="{{ old('product_description', $logistic->product_description) }}" class="form-input">
          </div>
        </div>
      </div>

      {{-- Truck & Driver --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Truck & Driver</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Truck Company</label>
            <input type="text" name="truck_company" value="{{ old('truck_company', $logistic->truck_company) }}" class="form-input" placeholder="Company name">
          </div>
          <div>
            <label class="form-label">Driver Name</label>
            <input type="text" name="driver_name" value="{{ old('driver_name', $logistic->driver_name) }}" class="form-input">
          </div>
          <div>
            <label class="form-label">Driver Phone</label>
            <input type="tel" name="driver_phone" value="{{ old('driver_phone', $logistic->driver_phone) }}" class="form-input">
          </div>
          <div>
            <label class="form-label">Tracking Number</label>
            <input type="text" name="tracking_number" value="{{ old('tracking_number', $logistic->tracking_number) }}" class="form-input font-mono" placeholder="TRK-XXXX-XXXXXX">
          </div>
        </div>
      </div>

      {{-- Financials --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Shipping Cost</h3>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Budget (AUD)</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
              <input type="number" name="shipping_budget" value="{{ old('shipping_budget', $logistic->shipping_budget) }}" class="form-input pl-7" step="0.01">
            </div>
          </div>
          <div>
            <label class="form-label">Final Cost (AUD)</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
              <input type="number" name="final_shipping_cost" value="{{ old('final_shipping_cost', $logistic->final_shipping_cost) }}" class="form-input pl-7" step="0.01">
            </div>
          </div>
        </div>
      </div>

      {{-- Schedule --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Schedule</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Pickup Date & Time</label>
            <input type="datetime-local" name="pickup_datetime"
                   value="{{ old('pickup_datetime', $logistic->pickup_datetime?->format('Y-m-d\TH:i')) }}"
                   class="form-input">
          </div>
          <div>
            <label class="form-label">Estimated Arrival</label>
            <input type="date" name="estimated_arrival"
                   value="{{ old('estimated_arrival', $logistic->estimated_arrival?->format('Y-m-d')) }}"
                   class="form-input">
          </div>
          <div>
            <label class="form-label">Actual Arrival</label>
            <input type="date" name="actual_arrival"
                   value="{{ old('actual_arrival', $logistic->actual_arrival?->format('Y-m-d')) }}"
                   class="form-input">
          </div>
          <div>
            <label class="form-label">Assigned Staff</label>
            <select name="assigned_to" class="form-input">
              <option value="">Unassigned</option>
              @foreach($users as $u)
                <option value="{{ $u->id }}" {{ old('assigned_to', $logistic->assigned_to) == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div>
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="2" class="form-input">{{ old('notes', $logistic->notes) }}</textarea>
        </div>
      </div>

      <div class="px-6 py-4 flex gap-3 justify-between bg-slate-50">
        <button type="submit" form="delete-logistic-form" class="btn btn-danger text-sm">Delete</button>
        <div class="flex gap-3">
          <a href="{{ route('crm.logistics.show', $logistic) }}" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </div>
    </form>

    <form id="delete-logistic-form"
          method="POST"
          action="{{ route('crm.logistics.destroy', $logistic) }}"
          data-confirm-title="Delete shipment?"
          data-confirm="Delete this shipment record? This cannot be undone."
          data-confirm-text="Delete shipment"
          data-confirm-tone="danger"
          class="hidden">
      @csrf @method('DELETE')
    </form>
  </div>
</div>
@endsection
