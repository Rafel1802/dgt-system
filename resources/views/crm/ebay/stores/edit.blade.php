@extends('layouts.app')
@section('title', 'Edit Store — ' . $store->store_name)
@section('page_title', 'Edit Store Profile')

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5 flex justify-between">
    <a href="{{ route('crm.ebay.stores.show', $store) }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Profile</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <h2 class="font-display font-bold text-slate-800 text-lg">Edit eBay Store</h2>
    </div>

    <form method="POST" action="{{ route('crm.ebay.stores.update', $store) }}" class="divide-y divide-slate-100">
      @csrf @method('PUT')

      <div class="px-6 py-5 space-y-4">
        <div>
          <label class="form-label">Store Name <span class="text-red-500">*</span></label>
          <input type="text" name="store_name" value="{{ old('store_name', $store->store_name) }}" class="form-input" required>
        </div>

        <div>
          <label class="form-label">Store Logo URL <span class="text-slate-400 normal-case font-normal">(optional)</span></label>
          <input type="url" name="logo_url" value="{{ old('logo_url', $store->logo_url) }}" class="form-input" placeholder="https://...">
        </div>

        <div>
          <label class="form-label">Store URL <span class="text-slate-400 normal-case font-normal">(optional)</span></label>
          <input type="url" name="store_url" value="{{ old('store_url', $store->store_url) }}" class="form-input">
        </div>

        <div>
          <label class="form-label">Main eBay Username <span class="text-slate-400 normal-case font-normal">(optional)</span></label>
          <input type="text" name="ebay_username" value="{{ old('ebay_username', $store->ebay_username) }}" class="form-input">
        </div>

        <div>
          <label class="form-label">Handled By (CRM Member)</label>
          @include('crm.partials.member-searchable-select', [
            'name'     => 'handled_by',
            'selected' => old('handled_by', $store->handled_by),
            'members'  => $crmUsers
          ])
        </div>

        <div>
          <label class="form-label">Total Sales ($)</label>
          <input type="number" step="0.01" min="0" name="total_sales" value="{{ old('total_sales', $store->total_sales) }}" class="form-input">
        </div>

        <div>
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="3" class="form-input">{{ old('notes', $store->notes) }}</textarea>
        </div>

        <div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $store->is_active) ? 'checked' : '' }} class="accent-indigo-600">
            <span class="text-sm text-slate-700">Store is active</span>
          </label>
        </div>
      </div>

      <div class="px-6 py-4 flex gap-3 justify-between bg-slate-50">
        @if(auth()->user()->canDeleteCrmRecords('ebay'))
        <button type="submit" form="delete-store-form" class="btn btn-danger text-sm">Delete Store</button>
        @else
        <div></div>
        @endif
        <div class="flex gap-3">
          <a href="{{ route('crm.ebay.stores.show', $store) }}" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </div>
    </form>

    @if(auth()->user()->canDeleteCrmRecords('ebay'))
    <form id="delete-store-form" method="POST" action="{{ route('crm.ebay.stores.destroy', $store) }}"
          data-confirm-title="Delete Store?"
          data-confirm="Are you sure you want to delete this store profile? Offers linked to it will NOT be deleted, but will become unlinked."
          data-confirm-text="Delete Store"
          data-confirm-tone="danger"
          class="hidden">
      @csrf @method('DELETE')
    </form>
    @endif
  </div>
</div>
@endsection
