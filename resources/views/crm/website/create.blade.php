@extends('layouts.app')
@section('title', 'Log New Inquiry')
@section('page_title', 'New Client Inquiry')

@push('scripts')
<script>
window.__DGT_CUSTOMERS__ = {!! $customers->map(fn($c) => ['id'=>$c->id,'name'=>$c->name,'company'=>$c->company??'','phone'=>$c->phone??'','label'=>$c->name.($c->company?' — '.$c->company:'').($c->phone?' · '.$c->phone:'')])->values()->toJson() !!};
</script>
@endpush

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5">
    <a href="{{ route('crm.website.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Website CRM</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <h2 class="font-display font-bold text-slate-800 text-lg">Log Client Inquiry</h2>
      <p class="text-slate-400 text-sm mt-1">Record all details as soon as the inquiry comes in.</p>
    </div>

    <form method="POST" action="{{ route('crm.website.store') }}" class="divide-y divide-slate-100">
      @csrf

      {{-- Client Information --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Client Information</h3>
        <div class="grid grid-cols-1 gap-4">
          <div>
            <label class="form-label">Customer <span class="text-red-500">*</span></label>
            @include('crm.partials.customer_combobox', [
                'customers' => $customers,
                'fieldId'   => 'website-customer',
                'fieldName' => 'customer_id',
                'required'  => true,
                'allowCreate' => true,
            ])
            @error('customer_id')<p class="form-error">{{ $message }}</p>@enderror
          </div>
        </div>
      </div>

      {{-- Inquiry Details --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Inquiry Details</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Inquiry Source <span class="text-red-500">*</span></label>
            <select name="source" class="form-input @error('source') error @enderror" id="field-source" required>
              <option value="">— Select source —</option>
              @foreach($sources as $s)
                <option value="{{ $s->value }}" {{ old('source') === $s->value ? 'selected' : '' }}>
                  {{ $s->icon() }} {{ $s->label() }}
                </option>
              @endforeach
            </select>
            @error('source')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">Date & Time Received <span class="text-red-500">*</span></label>
            <input type="datetime-local" name="received_at"
                   value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"
                   class="form-input" id="field-received-at" required>
          </div>
          <div>
            <label class="form-label">Product Interested In</label>
            @include('crm.partials.product-searchable-select', [
                'name'     => 'product_id',
                'selected' => old('product_id'),
                'products' => $products,
            ])
          </div>
          <div>
            <label class="form-label">Or type product interest</label>
            <input type="text" name="product_interested" value="{{ old('product_interested') }}"
                   class="form-input" placeholder="e.g. Excavator, Forklift…" id="field-product-text">
          </div>
        </div>
        <div>
          <label class="form-label">Inquiry Details</label>
          <textarea name="inquiry_details" rows="3" class="form-input"
                    placeholder="What exactly did the client ask? What are they looking for?">{{ old('inquiry_details') }}</textarea>
        </div>
      </div>

      {{-- Lead Handling --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Handling</h3>
        <div>
          <label class="form-label">Handled By (CRM Member)</label>
          @include('crm.partials.member-searchable-select', [
            'name'     => 'assigned_to',
            'selected' => old('assigned_to'),
            'members'  => $crmUsers
          ])
        </div>
      </div>

      {{-- Follow-Up --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Follow-Up Plan</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Follow-Up Date</label>
            <input type="date" name="follow_up_date" value="{{ old('follow_up_date') }}"
                   class="form-input" id="field-follow-up-date">
          </div>
          <div>
            <label class="form-label">Next Action</label>
            <input type="text" name="next_action" value="{{ old('next_action') }}"
                   class="form-input" placeholder="e.g. Call to discuss pricing"
                   id="field-next-action">
          </div>
        </div>
      </div>

      <div class="px-6 py-4 flex gap-3 justify-end bg-slate-50">
        <a href="{{ route('crm.website.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" id="btn-save-lead">Save Inquiry</button>
      </div>
    </form>
  </div>
</div>
@endsection
