@extends('layouts.app')
@section('title', 'New eBay Store')
@section('page_title', 'Create Store Profile')

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5">
    <a href="{{ route('crm.ebay.stores.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Stores</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <h2 class="font-display font-bold text-slate-800 text-lg">New eBay Store Profile</h2>
      <p class="text-slate-400 text-sm mt-1">Create a profile to group customers and offers from this store.</p>
    </div>

    <form method="POST" action="{{ route('crm.ebay.stores.store') }}" class="divide-y divide-slate-100">
      @csrf

      <div class="px-6 py-5 space-y-4">
        <div>
          <label class="form-label">Store Name <span class="text-red-500">*</span></label>
          <input type="text" name="store_name" value="{{ old('store_name') }}" class="form-input @error('store_name') error @enderror" required id="field-store-name">
          @error('store_name')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
          <label class="form-label">Store Logo URL <span class="text-slate-400 normal-case font-normal">(optional)</span></label>
          <input type="url" name="logo_url" value="{{ old('logo_url') }}" class="form-input" placeholder="https://..." id="field-logo-url">
        </div>

        <div>
          <label class="form-label">Store URL <span class="text-slate-400 normal-case font-normal">(optional)</span></label>
          <input type="url" name="store_url" value="{{ old('store_url') }}" class="form-input" placeholder="https://www.ebay.com/str/..." id="field-store-url">
        </div>

        <div>
          <label class="form-label">Main eBay Username <span class="text-slate-400 normal-case font-normal">(optional)</span></label>
          <input type="text" name="ebay_username" value="{{ old('ebay_username') }}" class="form-input" id="field-ebay-username">
        </div>

        <div>
          <label class="form-label">Handled By (CRM Member)</label>
          @include('crm.partials.member-searchable-select', [
            'name'     => 'handled_by',
            'selected' => old('handled_by'),
            'members'  => $crmUsers
          ])
        </div>

        <div>
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="3" class="form-input" id="field-notes">{{ old('notes') }}</textarea>
        </div>
      </div>

      <div class="px-6 py-4 flex gap-3 justify-end bg-slate-50">
        <a href="{{ route('crm.ebay.stores.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" id="btn-save-store">Save Store Profile</button>
      </div>
    </form>
  </div>
</div>
@endsection
