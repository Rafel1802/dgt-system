@extends('layouts.app')
@section('title', 'Edit Trucking Company')
@section('page_title', 'Edit Trucking Company')

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5 flex justify-between">
    <a href="{{ route('crm.logistics.trucking.show', $company) }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Profile</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <h2 class="font-display font-bold text-slate-800 text-lg">Edit Trucking Company</h2>
    </div>

    <form method="POST" action="{{ route('crm.logistics.trucking.update', $company) }}" class="divide-y divide-slate-100">
      @csrf @method('PUT')

      <div class="px-6 py-5 space-y-4">
        <div>
          <label class="form-label">Company Name <span class="text-red-500">*</span></label>
          <input type="text" name="company_name" value="{{ old('company_name', $company->company_name) }}" class="form-input" required>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Contact Person</label>
            <input type="text" name="pic_name" value="{{ old('pic_name', $company->pic_name) }}" class="form-input">
          </div>
          <div>
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" value="{{ old('phone', $company->phone) }}" class="form-input">
          </div>
          <div class="sm:col-span-2">
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email', $company->email) }}" class="form-input">
          </div>
        </div>

        <div>
          <label class="form-label">Address</label>
          <textarea name="address" rows="2" class="form-input">{{ old('address', $company->address) }}</textarea>
        </div>

        <div>
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="3" class="form-input">{{ old('notes', $company->notes) }}</textarea>
        </div>

        <div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $company->is_active) ? 'checked' : '' }} class="accent-indigo-600">
            <span class="text-sm text-slate-700">Company is active</span>
          </label>
        </div>
      </div>

      <div class="px-6 py-4 flex gap-3 justify-between bg-slate-50">
        <button type="submit" form="delete-company-form" class="btn btn-danger text-sm">Delete Company</button>
        <div class="flex gap-3">
          <a href="{{ route('crm.logistics.trucking.show', $company) }}" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </div>
    </form>

    <form id="delete-company-form" method="POST" action="{{ route('crm.logistics.trucking.destroy', $company) }}"
          data-confirm-title="Delete Trucking Company?"
          data-confirm="Are you sure you want to delete this company? Existing logistics records linked to it will not be deleted, but will become unassigned."
          data-confirm-text="Delete Company"
          data-confirm-tone="danger"
          class="hidden">
      @csrf @method('DELETE')
    </form>
  </div>
</div>
@endsection
