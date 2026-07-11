@extends('layouts.app')
@section('title', 'New Trucking Company')
@section('page_title', 'Create Trucking Company')

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5">
    <a href="{{ route('crm.logistics.trucking.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Trucking Companies</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <h2 class="font-display font-bold text-slate-800 text-lg">New Trucking Company</h2>
      <p class="text-slate-400 text-sm mt-1">Create a profile to manage shipments and performance for this company.</p>
    </div>

    <form method="POST" action="{{ route('crm.logistics.trucking.store') }}" class="divide-y divide-slate-100">
      @csrf

      <div class="px-6 py-5 space-y-4">
        <div>
          <label class="form-label">Company Name <span class="text-red-500">*</span></label>
          <input type="text" name="company_name" value="{{ old('company_name') }}" class="form-input @error('company_name') error @enderror" required>
          @error('company_name')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Contact Person</label>
            <input type="text" name="pic_name" value="{{ old('pic_name') }}" class="form-input">
          </div>
          <div>
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" value="{{ old('phone') }}" class="form-input">
          </div>
          <div class="sm:col-span-2">
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="form-input">
          </div>
        </div>

        <div>
          <label class="form-label">Address</label>
          <textarea name="address" rows="2" class="form-input">{{ old('address') }}</textarea>
        </div>

        <div>
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="3" class="form-input" placeholder="Service notes, pricing agreements, preferred routes...">{{ old('notes') }}</textarea>
        </div>
      </div>

      <div class="px-6 py-4 flex gap-3 justify-end bg-slate-50">
        <a href="{{ route('crm.logistics.trucking.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Company</button>
      </div>
    </form>
  </div>
</div>
@endsection
