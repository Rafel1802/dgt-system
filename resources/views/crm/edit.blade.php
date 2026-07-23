@extends('layouts.app')
@section('title', 'Edit ' . $customer->name)
@section('page_title', 'Edit Customer')

@section('content')
<div class="max-w-3xl animate-fade-in">
  <div class="mb-5">
    <a href="{{ route('crm.customers.show', $customer) }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Profile</a>
  </div>
  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <h2 class="font-display font-bold text-slate-800 text-lg">Edit: {{ $customer->name }}</h2>
      @unless($fullEdit)
      <p class="text-amber-600 text-xs mt-1">You can update Status and Notes only. Other fields are locked to Admin/Supervisor.</p>
      @endunless
    </div>
    <form method="POST" action="{{ route('crm.customers.update', $customer) }}" class="divide-y divide-slate-100">
      @csrf @method('PUT')
      @php $ro = $fullEdit ? '' : 'readonly'; $dis = $fullEdit ? '' : 'disabled'; @endphp

      <div class="px-6 py-5 space-y-4">
        <h3 class="font-semibold text-slate-700 text-sm">Basic Information</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Full Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $customer->name) }}" class="form-input" required {{ $ro }}>
            @error('name')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="form-input" {{ $ro }}>
            @error('email')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" class="form-input @error('phone') border-red-300 @enderror" placeholder="+1 (207) 213-9077" {{ $ro }}>
            @error('phone')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">Company</label>
            <input type="text" name="company" value="{{ old('company', $customer->company) }}" class="form-input" {{ $ro }}>
          </div>
          <div>
            <label class="form-label">Job Title</label>
            <input type="text" name="job_title" value="{{ old('job_title', $customer->job_title) }}" class="form-input" {{ $ro }}>
          </div>
          <div>
            <label class="form-label">Website</label>
            <input type="url" name="website" value="{{ old('website', $customer->website) }}" class="form-input" {{ $ro }}>
          </div>
        </div>
      </div>

      <div class="px-6 py-5 space-y-4">
        <h3 class="font-semibold text-slate-700 text-sm">CRM Classification</h3>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
          <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-input">
              @foreach($statuses as $s)
                <option value="{{ $s->value }}" {{ old('status', $customer->status?->value) === $s->value ? 'selected' : '' }}>{{ $s->label() }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Source</label>
            <select name="source" class="form-input" {{ $dis }}>
              <option value="">Select source…</option>
              @foreach($sources as $s)
                <option value="{{ $s->value }}" {{ old('source', $customer->source) === $s->value ? 'selected' : '' }}>{{ $s->icon() }} {{ $s->label() }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Pipeline Stage</label>
            <select name="pipeline_stage" class="form-input" {{ $dis }}>
              @foreach($stages as $s)
                <option value="{{ $s->value }}" {{ old('pipeline_stage', $customer->pipeline_stage?->value) === $s->value ? 'selected' : '' }}>{{ $s->label() }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Purchase Date @if($customer->first_purchase_date)<span class="text-red-500">*</span>@endif</label>
            <input type="date" name="first_purchase_date" value="{{ old('first_purchase_date', $customer->first_purchase_date?->toDateString()) }}" class="form-input @error('first_purchase_date') border-red-300 @enderror" max="{{ now()->toDateString() }}" {{ $ro }} {{ $customer->first_purchase_date ? 'required' : '' }}>
            @error('first_purchase_date')<p class="form-error">{{ $message }}</p>@enderror
          </div>
        </div>

        <div>
          <label class="form-label">Product Interests</label>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            @php $interests = ['Video Production','Graphic Design','eBay Listing','Website Creation','SEO','Marketing','Product Photography','Social Media','Other']; @endphp
            @foreach($interests as $interest)
            <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
              <input type="checkbox" name="product_interests[]" value="{{ $interest }}"
                     {{ in_array($interest, old('product_interests', $customer->product_interests ?? [])) ? 'checked' : '' }}
                     class="accent-indigo-600" {{ $dis }}>
              {{ $interest }}
            </label>
            @endforeach
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Assigned To</label>
            <select name="assigned_to" class="form-input" {{ $dis }}>
              <option value="">Unassigned</option>
              @foreach($users as $u)
                <option value="{{ $u->id }}" {{ old('assigned_to', $customer->assigned_to) == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Tags <span class="text-slate-400 text-xs">(comma separated)</span></label>
            <input type="text" name="tags" value="{{ old('tags', implode(', ', $customer->tags ?? [])) }}" class="form-input" {{ $ro }}>
          </div>
        </div>
      </div>

      <div class="px-6 py-5">
        <label class="form-label">Notes</label>
        <textarea name="notes" rows="4" class="form-input">{{ old('notes', $customer->notes) }}</textarea>
      </div>

      <div class="px-6 py-4 flex gap-3 justify-between bg-slate-50">
        @can('delete', $customer)
        <button type="submit" form="delete-customer-form" class="btn btn-danger text-sm">Delete Customer</button>
        @endcan
        <div class="flex gap-3">
          <a href="{{ route('crm.customers.show', $customer) }}" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </div>
    </form>

    @can('delete', $customer)
    <form id="delete-customer-form"
          method="POST"
          action="{{ route('crm.customers.destroy', $customer) }}"
          data-confirm-title="Delete customer?"
          data-confirm="Delete this customer? This cannot be undone."
          data-confirm-text="Delete customer"
          data-confirm-tone="danger"
          class="hidden">
      @csrf @method('DELETE')
    </form>
    @endcan
  </div>
</div>
@endsection
