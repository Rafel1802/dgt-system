@extends('layouts.app')
@section('title', 'Edit Shipment')
@section('page_title', 'Edit Shipment')

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5 flex justify-between">
    <a href="{{ route('crm.logistics.shipments.show', $shipment) }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Shipment</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100 flex items-center gap-3">
      <h2 class="font-display font-bold text-slate-800 text-lg">Edit {{ $shipment->shipment_code }}</h2>
    </div>

    <form method="POST" action="{{ route('crm.logistics.shipments.update', $shipment) }}" class="divide-y divide-slate-100">
      @csrf @method('PUT')

      <div class="px-6 py-5 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Shipment Code <span class="text-red-500">*</span></label>
            <input type="text" name="shipment_code" value="{{ old('shipment_code', $shipment->shipment_code) }}" class="form-input font-mono" required>
          </div>
          <div>
            <label class="form-label">Status <span class="text-red-500">*</span></label>
            <select name="status" class="form-input" required>
              <option value="pending" {{ old('status', $shipment->status) === 'pending' ? 'selected' : '' }}>Pending</option>
              <option value="in_transit" {{ old('status', $shipment->status) === 'in_transit' ? 'selected' : '' }}>In Transit</option>
              <option value="completed" {{ old('status', $shipment->status) === 'completed' ? 'selected' : '' }}>Completed</option>
              <option value="cancelled" {{ old('status', $shipment->status) === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Estimated Arrival <span class="text-red-500">*</span></label>
            <input type="date" name="estimated_arrival" value="{{ old('estimated_arrival', $shipment->estimated_arrival ? $shipment->estimated_arrival->format('Y-m-d') : '') }}" class="form-input" required>
          </div>
          <div>
            <label class="form-label">Actual Arrival</label>
            <input type="date" name="actual_arrival" value="{{ old('actual_arrival', $shipment->actual_arrival ? $shipment->actual_arrival->format('Y-m-d') : '') }}" class="form-input">
          </div>
        </div>

        <div>
          <label class="form-label">Truck Company</label>
          <select name="trucking_company_id" class="form-input">
            <option value="">— Select Company (Optional) —</option>
            @foreach($truckingCompanies as $tc)
              <option value="{{ $tc->id }}" {{ old('trucking_company_id', $shipment->trucking_company_id) == $tc->id ? 'selected' : '' }}>
                {{ $tc->company_name }}
              </option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="form-label">Handled By (CRM Member)</label>
          <select name="assigned_to" class="form-input">
            <option value="">Unassigned</option>
            @foreach($crmUsers as $u)
              <option value="{{ $u->id }}" {{ old('assigned_to', $shipment->assigned_to) == $u->id ? 'selected' : '' }}>
                {{ $u->name }} — {{ $u->crm_role_display }}
              </option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="3" class="form-input">{{ old('notes', $shipment->notes) }}</textarea>
        </div>
      </div>

      <div class="px-6 py-4 flex gap-3 justify-between bg-slate-50">
        <button type="submit" form="delete-shipment-form" class="btn btn-danger text-sm">Delete</button>
        <div class="flex gap-3">
          <a href="{{ route('crm.logistics.shipments.show', $shipment) }}" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </div>
    </form>

    <form id="delete-shipment-form"
          method="POST"
          action="{{ route('crm.logistics.shipments.destroy', $shipment) }}"
          data-confirm-title="Delete Shipment?"
          data-confirm="Are you sure you want to delete this shipment? This cannot be undone."
          data-confirm-text="Delete Shipment"
          data-confirm-tone="danger"
          class="hidden">
      @csrf @method('DELETE')
    </form>
  </div>
</div>
@endsection
