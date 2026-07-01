@extends('layouts.app')
@section('title', 'Log eBay Offer')
@section('page_title', 'Log eBay Offer')

@push('scripts')
<script>
window.__DGT_CUSTOMERS__ = {!! $customers->map(fn($c) => ['id'=>$c->id,'name'=>$c->name,'company'=>$c->company??'','phone'=>$c->phone??'','label'=>$c->name.($c->company?' — '.$c->company:'').($c->phone?' · '.$c->phone:'')])->values()->toJson() !!};
</script>
@endpush

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5">
    <a href="{{ route('crm.ebay.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to eBay CRM</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center text-2xl">🛒</div>
        <div>
          <h2 class="font-display font-bold text-slate-800 text-lg">Log eBay Offer</h2>
          <p class="text-slate-400 text-sm mt-0.5">Record a new eBay message or purchase offer.</p>
        </div>
      </div>
    </div>

    <form method="POST" action="{{ route('crm.ebay.store') }}" class="divide-y divide-slate-100">
      @csrf

      {{-- Client / eBay Identity --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Client / eBay Identity</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="form-label">Customer <span class="text-red-500">*</span></label>
            @include('crm.partials.customer_combobox', [
                'customers' => $customers,
                'fieldId'   => 'ebay-customer',
                'fieldName' => 'customer_id',
                'required'  => true,
            ])
            @error('customer_id')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">eBay Store</label>
            <select name="store_id" class="form-input">
              <option value="">— Unassigned —</option>
              @foreach($stores as $store)
                <option value="{{ $store->id }}" {{ old('store_id', request('store_id')) == $store->id ? 'selected' : '' }}>{{ $store->store_name }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">eBay Username <span class="text-slate-400 text-xs font-normal ml-1">(Optional)</span></label>
            <input type="text" name="ebay_username" value="{{ old('ebay_username') }}" class="form-input" id="field-ebay-username">
          </div>
          <div>
            <label class="form-label">eBay Message ID</label>
            <input type="text" name="ebay_message_id" value="{{ old('ebay_message_id') }}"
                   class="form-input" placeholder="MSG-XXXXXXX" id="field-message-id">
          </div>
          <div>
            <label class="form-label">eBay Item ID</label>
            <input type="text" name="ebay_item_id" value="{{ old('ebay_item_id') }}"
                   class="form-input" placeholder="110123456789" id="field-item-id">
          </div>
          <div>
            <label class="form-label">Date/Time Received <span class="text-red-500">*</span></label>
            <input type="datetime-local" name="received_at"
                   value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"
                   class="form-input" required id="field-received-at">
          </div>
          <div>
            <label class="form-label">Handled By (CRM Member)</label>
            <select name="handled_by" class="form-input" id="field-handled-by">
              <option value="">Unassigned</option>
              @foreach($crmUsers as $u)
                <option value="{{ $u->id }}" {{ old('handled_by', auth()->id()) == $u->id ? 'selected' : '' }}>
                  {{ $u->name }} — {{ $u->crm_role_display }}
                </option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      {{-- Product --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Product</h3>
        <div>
          <label class="form-label">Product from Catalogue</label>
          <select name="product_id" class="form-input" id="field-product">
            <option value="">— Select product —</option>
            @foreach($products as $p)
              <option value="{{ $p->id }}" {{ old('product_id') == $p->id ? 'selected' : '' }}>
                {{ $p->category?->icon() }} {{ $p->name }} @if($p->price)— ${{ number_format($p->price) }}@endif
              </option>
            @endforeach
          </select>
        </div>
      </div>

      {{-- Inquiry / Offer Details --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Inquiry / Offer Details</h3>
        <div>
          <label class="form-label">Inquiry / Message Notes</label>
          <textarea name="inquiry_notes" rows="3" class="form-input"
                    placeholder="Summary of what the buyer asked or said…">{{ old('inquiry_notes') }}</textarea>
        </div>
        <div>
          <label class="form-label">Offer Details</label>
          <textarea name="offer_details" rows="2" class="form-input"
                    placeholder="Terms, conditions, special requests from the buyer…">{{ old('offer_details') }}</textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Offer Amount (AUD)</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
              <input type="number" name="offer_amount" value="{{ old('offer_amount') }}"
                     class="form-input pl-7" placeholder="0.00" step="0.01" id="field-offer-amount">
            </div>
          </div>
          <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-input" id="field-status">
              @foreach($statuses as $s)
                <option value="{{ $s->value }}" {{ old('status', 'inquiry') === $s->value ? 'selected' : '' }}>
                  {{ $s->label() }}
                </option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      <div class="px-6 py-4 flex gap-3 justify-end bg-slate-50">
        <a href="{{ route('crm.ebay.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" id="btn-save-offer">Save Offer</button>
      </div>
    </form>
  </div>
</div>
@endsection
