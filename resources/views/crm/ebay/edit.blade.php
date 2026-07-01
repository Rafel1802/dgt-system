@extends('layouts.app')
@section('title', 'Edit eBay Offer')
@section('page_title', 'Edit eBay Offer')

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5">
    <a href="{{ route('crm.ebay.show', $offer) }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Offer</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100 flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center text-xl">🛒</div>
      <div>
        <h2 class="font-display font-bold text-slate-800 text-lg">Edit eBay Offer</h2>
        <span class="badge text-xs" style="background:{{ $offer->status?->color() }}22; color:{{ $offer->status?->color() }}">
          {{ $offer->status?->label() }}
        </span>
      </div>
    </div>

    <form method="POST" action="{{ route('crm.ebay.update', $offer) }}" class="divide-y divide-slate-100">
      @csrf @method('PUT')

      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Client / eBay Identity</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Client Name</label>
            <input type="text" name="client_name" value="{{ old('client_name', $offer->client_name) }}" class="form-input">
          </div>
          <div>
            <label class="form-label">Client Email</label>
            <input type="email" name="client_email" value="{{ old('client_email', $offer->client_email) }}" class="form-input">
          </div>
          <div>
            <label class="form-label">eBay Store</label>
            <select name="store_id" class="form-input">
              <option value="">— Unassigned —</option>
              @foreach($stores as $store)
                <option value="{{ $store->id }}" {{ old('store_id', $offer->store_id) == $store->id ? 'selected' : '' }}>{{ $store->store_name }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">eBay Username <span class="text-slate-400 text-xs font-normal ml-1">(Optional)</span></label>
            <input type="text" name="ebay_username" value="{{ old('ebay_username', $offer->ebay_username) }}" class="form-input">
          </div>
          <div>
            <label class="form-label">eBay Message ID</label>
            <input type="text" name="ebay_message_id" value="{{ old('ebay_message_id', $offer->ebay_message_id) }}" class="form-input">
          </div>
          <div>
            <label class="form-label">eBay Item ID</label>
            <input type="text" name="ebay_item_id" value="{{ old('ebay_item_id', $offer->ebay_item_id) }}" class="form-input">
          </div>
          <div>
            <label class="form-label">Handled By (CRM Member)</label>
            @include('crm.partials.member-searchable-select', [
              'name'     => 'handled_by',
              'selected' => old('handled_by', $offer->handled_by),
              'members'  => $crmUsers
            ])
          </div>
        </div>
      </div>

      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Product & Offer</h3>
        <div>
          <label class="form-label">Product</label>
          <select name="product_id" class="form-input">
            <option value="">— None —</option>
            @foreach($products as $p)
              <option value="{{ $p->id }}" {{ old('product_id', $offer->product_id) == $p->id ? 'selected' : '' }}>
                {{ $p->category?->icon() }} {{ $p->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="form-label">Inquiry / Message Notes</label>
          <textarea name="inquiry_notes" rows="3" class="form-input">{{ old('inquiry_notes', $offer->inquiry_notes) }}</textarea>
        </div>
        <div>
          <label class="form-label">Offer Details</label>
          <textarea name="offer_details" rows="2" class="form-input">{{ old('offer_details', $offer->offer_details) }}</textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Offer Amount (AUD)</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
              <input type="number" name="offer_amount" value="{{ old('offer_amount', $offer->offer_amount) }}" class="form-input pl-7" step="0.01">
            </div>
          </div>
          <div>
            <label class="form-label">Final Amount (AUD)</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
              <input type="number" name="final_amount" value="{{ old('final_amount', $offer->final_amount) }}" class="form-input pl-7" step="0.01">
            </div>
          </div>
          <div>
            <label class="form-label">Payment Status</label>
            <select name="payment_status" class="form-input">
              @foreach(['unpaid'=>'Unpaid','paid'=>'Paid','partial'=>'Partial','refunded'=>'Refunded'] as $v => $l)
                <option value="{{ $v }}" {{ old('payment_status', $offer->payment_status) === $v ? 'selected' : '' }}>{{ $l }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-input">
              @foreach($statuses as $s)
                <option value="{{ $s->value }}" {{ old('status', $offer->status?->value) === $s->value ? 'selected' : '' }}>
                  {{ $s->label() }}
                </option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      <div class="px-6 py-4 flex gap-3 justify-between bg-slate-50">
        <button type="submit" form="delete-ebay-offer-form" class="btn btn-danger text-sm">Delete</button>
        <div class="flex gap-3">
          <a href="{{ route('crm.ebay.show', $offer) }}" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </div>
    </form>

    <form id="delete-ebay-offer-form"
          method="POST"
          action="{{ route('crm.ebay.destroy', $offer) }}"
          data-confirm-title="Delete eBay offer?"
          data-confirm="Delete this eBay offer? This cannot be undone."
          data-confirm-text="Delete offer"
          data-confirm-tone="danger"
          class="hidden">
      @csrf @method('DELETE')
    </form>
  </div>
</div>
@endsection
