@extends('layouts.app')
@section('title', 'Review Import')
@section('page_title', 'Review Import — Process Trucking')

@section('content')
<div class="animate-fade-in max-w-4xl mx-auto" x-data="{
  rows: {{ Js::from(collect($rows)->values()->map(fn ($r, $i) => array_merge($r, ['_key' => $i]))) }},
  removeRow(key) { this.rows = this.rows.filter(r => r._key !== key); },
}">
  <div class="mb-5">
    <a href="{{ route('crm.logistics.shipments.index', ['status' => 'processing']) }}" class="text-sm text-slate-400 hover:text-indigo-600">← Cancel and back to Process Trucking</a>
  </div>

  <div class="card p-4 mb-5 flex items-center justify-between flex-wrap gap-3">
    <div>
      <h2 class="font-display font-bold text-slate-800 text-lg">Review before importing</h2>
      <p class="text-sm text-slate-500 mt-1">Nothing has been saved yet. Edit any field directly, or remove a row you don't want — then confirm below.</p>
    </div>
    <span class="badge bg-slate-100 text-slate-600 text-sm px-3 py-1" x-text="rows.length + ' row' + (rows.length === 1 ? '' : 's')"></span>
  </div>

  <form method="POST" action="{{ route('crm.logistics.shipments.customers.import.confirm') }}">
    @csrf

    <div class="space-y-4">
      <template x-for="(row, idx) in rows" :key="row._key">
        <div class="card">
          <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
            <span class="badge bg-indigo-50 text-indigo-700 text-xs font-bold px-2.5 py-1" x-text="'Row ' + (idx + 1)"></span>
            <button type="button" @click="removeRow(row._key)" class="btn btn-secondary text-xs text-red-500 hover:text-red-600 hover:border-red-200">
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
              Remove row
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
              <label class="form-label">Recipient Name <span class="text-red-500">*</span></label>
              <input type="text" x-model="row.recipient_name" :name="'rows[' + row._key + '][recipient_name]'" class="form-input" required>
            </div>
            <div>
              <label class="form-label">Phone</label>
              <input type="text" x-model="row.phone" :name="'rows[' + row._key + '][phone]'" class="form-input">
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
            <div>
              <label class="form-label">Email</label>
              <input type="text" x-model="row.email" :name="'rows[' + row._key + '][email]'" class="form-input">
            </div>
            <div>
              <label class="form-label">Address Line</label>
              <input type="text" x-model="row.address_line" :name="'rows[' + row._key + '][address_line]'" class="form-input">
            </div>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
            <div>
              <label class="form-label">City</label>
              <input type="text" x-model="row.city" :name="'rows[' + row._key + '][city]'" class="form-input">
            </div>
            <div>
              <label class="form-label">State</label>
              <input type="text" x-model="row.state" :name="'rows[' + row._key + '][state]'" class="form-input">
            </div>
            <div>
              <label class="form-label">Zip</label>
              <input type="text" x-model="row.zip" :name="'rows[' + row._key + '][zip]'" class="form-input">
            </div>
            <div>
              <label class="form-label">Country</label>
              <input type="text" x-model="row.country" :name="'rows[' + row._key + '][country]'" class="form-input">
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-4 pt-4 border-t border-slate-100">
            <div class="sm:col-span-2">
              <label class="form-label">SKU <span class="text-slate-400 font-normal">(the product)</span></label>
              <input type="text" x-model="row.sku" :name="'rows[' + row._key + '][sku]'" class="form-input">
            </div>
            <div>
              <label class="form-label">Quantity</label>
              <input type="number" min="1" x-model="row.quantity" :name="'rows[' + row._key + '][quantity]'" class="form-input">
            </div>
            <div>
              <label class="form-label">Tracking Number</label>
              <input type="text" x-model="row.tracking_number" :name="'rows[' + row._key + '][tracking_number]'" class="form-input">
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
            <div class="sm:col-span-1">
              <label class="form-label">Product Name <span class="text-slate-400 font-normal">(optional override)</span></label>
              <input type="text" x-model="row.product_name" :name="'rows[' + row._key + '][product_name]'" class="form-input" placeholder="Leave blank to use the SKU's catalog name">
            </div>
            <div class="sm:col-span-2">
              <label class="form-label">Notes</label>
              <input type="text" x-model="row.notes" :name="'rows[' + row._key + '][notes]'" class="form-input">
            </div>
          </div>
        </div>
      </template>

      <div x-show="rows.length === 0" x-cloak class="card text-center py-14 text-slate-400 text-sm">
        Every row has been removed — nothing to import.
      </div>
    </div>

    <div class="flex justify-end gap-3 mt-6">
      <a href="{{ route('crm.logistics.shipments.index', ['status' => 'processing']) }}" class="btn btn-secondary text-sm">Cancel</a>
      <button type="submit" class="btn btn-primary text-sm" :disabled="rows.length === 0" x-text="'Confirm Import (' + rows.length + ')'"></button>
    </div>
  </form>
</div>
@endsection
