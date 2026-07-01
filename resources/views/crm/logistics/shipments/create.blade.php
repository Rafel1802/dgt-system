@extends('layouts.app')
@section('title', 'New Shipment')
@section('page_title', 'Create Shipment')

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5">
    <a href="{{ route('crm.logistics.shipments.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Shipments</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <h2 class="font-display font-bold text-slate-800 text-lg">New Shipment Group</h2>
      <p class="text-slate-400 text-sm mt-1">Create a shipment record to add multiple customers to it.</p>
    </div>

    <form method="POST" action="{{ route('crm.logistics.shipments.store') }}" class="divide-y divide-slate-100">
      @csrf

      @if($errors->any())
      <div class="px-6 py-3 bg-red-50 border-b border-red-100">
        <ul class="text-sm text-red-600 space-y-1">
          @foreach($errors->all() as $error)
            <li>• {{ $error }}</li>
          @endforeach
        </ul>
      </div>
      @endif

      <div class="px-6 py-5 space-y-4">

        {{-- Shipment Code --}}
        <div>
          <label class="form-label">Shipment Code</label>
          <input type="text" name="shipment_code"
                 value="{{ old('shipment_code', 'SHP-'.date('Ymd-Hi')) }}"
                 class="form-input font-mono @error('shipment_code') error @enderror"
                 placeholder="Auto-generated code">
          @error('shipment_code')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        {{-- Status (REQUIRED — this was the bug! was missing from form) --}}
        <div>
          <label class="form-label">Status <span class="text-red-500">*</span></label>
          <select name="status" class="form-input @error('status') error @enderror" required>
            @foreach($statuses as $val => $lbl)
              <option value="{{ $val }}" {{ old('status', 'pending') === $val ? 'selected' : '' }}>
                {{ $lbl }}
              </option>
            @endforeach
          </select>
          @error('status')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Estimated Arrival Date</label>
            <input type="date" name="estimated_arrival"
                   value="{{ old('estimated_arrival', date('Y-m-d')) }}"
                   class="form-input">
          </div>
          <div>
            <label class="form-label">Truck Company</label>
            @include('crm.partials.trucking-searchable-select', [
              'name'      => 'trucking_company_id',
              'selected'  => old('trucking_company_id', request('truck_company_id')),
              'companies' => $truckingCompanies
            ])
          </div>
        </div>

        <div>
          <label class="form-label">Handled By (CRM Member)</label>
          @include('crm.partials.member-searchable-select', [
            'name'     => 'assigned_to',
            'selected' => old('assigned_to', auth()->id()),
            'members'  => $crmUsers
          ])
        </div>

        <div>
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="3" class="form-input"
                    placeholder="General instructions for this shipment group...">{{ old('notes') }}</textarea>
        </div>
      </div>

      <div class="px-6 py-4 flex gap-3 justify-end bg-slate-50">
        <a href="{{ route('crm.logistics.shipments.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
          Save Shipment
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
