@extends('layouts.app')
@section('title', 'Add Customer')
@section('page_title', 'Add Customer')

@section('content')
<div class="max-w-3xl animate-fade-in">

  <div class="mb-5">
    <a href="{{ route('crm.customers.index') }}" class="text-sm text-slate-400 hover:text-indigo-600 flex items-center gap-1">
      ← Back to Customers
    </a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <h2 class="font-display font-bold text-slate-800 text-lg">New Customer</h2>
      <p class="text-slate-400 text-sm mt-1">Fill in the customer details below. All fields except name are optional.</p>
    </div>

    <form method="POST" action="{{ route('crm.customers.store') }}" class="divide-y divide-slate-100">
      @csrf

      {{-- Identity --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="font-semibold text-slate-700 text-sm">Basic Information</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Full Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" class="form-input @error('name') border-red-300 @enderror" placeholder="John Smith" id="customer-name" required>
            @error('name')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="form-input @error('email') border-red-300 @enderror" placeholder="john@example.com" id="customer-email">
            @error('email')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">Phone</label>
            <input type="text" name="phone" value="{{ old('phone') }}" class="form-input" placeholder="+61 4XX XXX XXX" id="customer-phone">
          </div>
          <div>
            <label class="form-label">Company</label>
            <input type="text" name="company" value="{{ old('company') }}" class="form-input" placeholder="Acme Pty Ltd" id="customer-company">
          </div>
          <div>
            <label class="form-label">Job Title</label>
            <input type="text" name="job_title" value="{{ old('job_title') }}" class="form-input" placeholder="Marketing Manager" id="customer-title">
          </div>
          <div>
            <label class="form-label">Website</label>
            <input type="url" name="website" value="{{ old('website') }}" class="form-input" placeholder="https://example.com" id="customer-website">
          </div>
        </div>
      </div>

      {{-- Location --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="font-semibold text-slate-700 text-sm">Location</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <div>
            <label class="form-label">Country</label>
            <input type="text" name="country" value="{{ old('country', 'AU') }}" class="form-input" placeholder="AU" id="customer-country">
          </div>
          <div>
            <label class="form-label">State</label>
            <input type="text" name="state" value="{{ old('state') }}" class="form-input" placeholder="NSW" id="customer-state">
          </div>
          <div>
            <label class="form-label">City</label>
            <input type="text" name="city" value="{{ old('city') }}" class="form-input" placeholder="Sydney" id="customer-city">
          </div>
          <div class="col-span-2">
            <label class="form-label">Address</label>
            <input type="text" name="address" value="{{ old('address') }}" class="form-input" placeholder="123 Main St" id="customer-address">
          </div>
          <div>
            <label class="form-label">Postcode</label>
            <input type="text" name="postcode" value="{{ old('postcode') }}" class="form-input" placeholder="2000" id="customer-postcode">
          </div>
        </div>
      </div>

      {{-- CRM Classification --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="font-semibold text-slate-700 text-sm">CRM Classification</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="form-label">Status <span class="text-red-500">*</span></label>
            <select name="status" class="form-input" id="customer-status" required>
              @foreach($statuses as $s)
                <option value="{{ $s->value }}" {{ old('status', 'lead') === $s->value ? 'selected' : '' }}>{{ $s->label() }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Source</label>
            <select name="source" class="form-input" id="customer-source">
              <option value="">Select source…</option>
              @foreach($sources as $s)
                <option value="{{ $s->value }}" {{ old('source') === $s->value ? 'selected' : '' }}>{{ $s->icon() }} {{ $s->label() }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Pipeline Stage</label>
            <select name="pipeline_stage" class="form-input" id="customer-stage">
              @foreach($stages as $s)
                <option value="{{ $s->value }}" {{ old('pipeline_stage', 'new_lead') === $s->value ? 'selected' : '' }}>{{ $s->label() }}</option>
              @endforeach
            </select>
          </div>
        </div>

        {{-- Product Interests --}}
        <div>
          <label class="form-label">Product Interests</label>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            @php
              $interests = ['Video Production', 'Graphic Design', 'eBay Listing', 'Website Creation', 'SEO', 'Marketing', 'Product Photography', 'Social Media', 'Other'];
            @endphp
            @foreach($interests as $interest)
            <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
              <input type="checkbox" name="product_interests[]" value="{{ $interest }}"
                     {{ in_array($interest, old('product_interests', [])) ? 'checked' : '' }}
                     class="accent-indigo-600">
              {{ $interest }}
            </label>
            @endforeach
          </div>
        </div>

        {{-- Assignment --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Assigned To</label>
            <select name="assigned_to" class="form-input" id="customer-assignee">
              <option value="">Unassigned</option>
              @foreach($users as $u)
                <option value="{{ $u->id }}" {{ old('assigned_to') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Tags <span class="text-slate-400 text-xs">(comma separated)</span></label>
            <input type="text" name="tags" value="{{ old('tags') }}" class="form-input" placeholder="vip, wholesale, follow-up" id="customer-tags">
          </div>
        </div>
      </div>

      {{-- Notes --}}
      <div class="px-6 py-5">
        <label class="form-label">Initial Notes</label>
        <textarea name="notes" rows="4" class="form-input" placeholder="Any background info, first contact summary, requirements…" id="customer-notes">{{ old('notes') }}</textarea>
      </div>

      {{-- Actions --}}
      <div class="px-6 py-4 flex gap-3 justify-end bg-slate-50">
        <a href="{{ route('crm.customers.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" id="btn-save-customer">
          Create Customer
        </button>
      </div>
    </form>
  </div>

</div>
@endsection
