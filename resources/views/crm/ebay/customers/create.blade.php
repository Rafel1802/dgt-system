@extends('layouts.app')
@section('title', 'New eBay Customer Record')
@section('page_title', 'eBay Manage Customer — Add Record')

@section('content')
<div class="max-w-2xl animate-fade-in" x-data="{
  status: '{{ old('tab_type', request('tab_type', 'urgent_client')) }}',
  products: {{ old('products') ? Js::from(old('products')) : Js::from([['name' => '', 'price' => '']]) }},
  addProduct() { this.products.push({ name: '', price: '' }); },
  removeProduct(i) { if (this.products.length > 1) this.products.splice(i, 1); },
}">
  <div class="mb-5 flex items-center gap-2">
    <a href="{{ route('crm.ebay.customers.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Records</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <h2 class="font-display font-bold text-slate-800 text-lg">New eBay Customer Record</h2>
    </div>

    <form method="POST" action="{{ route('crm.ebay.customers.store') }}" class="divide-y divide-slate-100">
      @csrf

      @if($errors->any())
      <div class="px-6 py-3 bg-red-50 border-b border-red-100">
        <ul class="text-sm text-red-600 space-y-1">
          @foreach($errors->all() as $err)<li>• {{ $err }}</li>@endforeach
        </ul>
      </div>
      @endif

      <div class="px-6 py-5 space-y-4">

        <div>
          <label class="form-label">Customer <span class="text-slate-400 normal-case font-normal">(search existing, add new, or leave blank to auto-create from the fields below)</span></label>
          @include('crm.partials.customer_combobox', [
            'customers' => $customers,
            'fieldId' => 'ebay-record-customer',
            'fieldName' => 'customer_id',
            'selected' => old('customer_id'),
            'required' => false,
            'allowCreate' => true,
            'quickCreateSource' => \App\Enums\CustomerSource::Ebay->value,
            'autofill' => true,
            'autofillNameId' => 'field-buyer-name',
            'autofillEmailId' => 'field-email',
            'autofillPhoneId' => 'field-phone',
            'autofillUsernameId' => 'field-username',
          ])
        </div>

        <div>
          <label class="form-label">Full Name</label>
          <input id="field-buyer-name" type="text" name="buyer_name" value="{{ old('buyer_name') }}" class="form-input">
        </div>

        <div>
          <label class="form-label">Username <span class="text-red-500">*</span></label>
          <input id="field-username" type="text" name="username" value="{{ old('username') }}" class="form-input" required>
        </div>

        <div>
          <label class="form-label">Status <span class="text-red-500">*</span></label>
          <select name="tab_type" class="form-input" x-model="status" required>
            @foreach($tabs as $key => $label)
            <option value="{{ $key }}" {{ old('tab_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Email</label>
            <input id="field-email" type="email" name="email" value="{{ old('email') }}" class="form-input">
          </div>
          <div>
            <label class="form-label">Phone</label>
            <input id="field-phone" type="text" name="phone" value="{{ old('phone') }}" class="form-input">
          </div>
        </div>

        <div x-show="status !== 'new_order'" x-cloak>
          <label class="form-label">eBay Store</label>
          @include('crm.partials.store-searchable-select', [
            'name'     => 'ebay_store_id',
            'selected' => old('ebay_store_id'),
            'stores'   => $stores,
          ])
        </div>

        <div x-show="status === 'new_order'" x-cloak class="space-y-4 pt-2 border-t border-slate-100">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label">Order ID <span class="text-red-500">*</span></label>
              <input type="text" name="order_id" value="{{ old('order_id') }}" class="form-input font-mono">
            </div>
            <div>
              <label class="form-label">Order Date <span class="text-red-500">*</span></label>
              <input type="date" name="order_date" value="{{ old('order_date') }}" class="form-input" x-bind:required="status === 'new_order'">
            </div>
          </div>
          <div>
            <label class="form-label">Purchased From (Store)</label>
            @include('crm.partials.store-searchable-select', [
              'name'     => 'order_store_id',
              'selected' => old('order_store_id'),
              'stores'   => $stores,
            ])
          </div>

          <div>
            <label class="form-label">Product(s) <span class="text-red-500">*</span> <span class="text-slate-400 normal-case font-normal">(manual entry — pricing varies per sale)</span></label>
            <div class="space-y-2">
              <template x-for="(product, i) in products" :key="i">
                <div class="flex gap-2 items-start">
                  <input type="text" :name="`products[${i}][name]`" x-model="product.name" list="ebay-catalog-products"
                         placeholder="Search or type a product" class="form-input flex-1" x-bind:required="status === 'new_order'">
                  <input type="number" step="0.01" min="0" :name="`products[${i}][price]`" x-model="product.price"
                         placeholder="Price" class="form-input w-28" x-bind:required="status === 'new_order'">
                  <button type="button" @click="removeProduct(i)" x-show="products.length > 1"
                          class="btn btn-secondary btn-icon text-red-400 hover:text-red-600 shrink-0" style="width:38px;height:38px;">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                  </button>
                </div>
              </template>
            </div>
            <button type="button" @click="addProduct()" class="btn btn-secondary text-xs mt-2">+ Add Another Product</button>
          </div>
        </div>

        <div x-show="status === 'cancelation_client'" x-cloak>
          <label class="form-label">Reason</label>
          <textarea name="summary" rows="2" class="form-input">{{ old('summary') }}</textarea>
        </div>

        <div x-show="['potential_negatives', 'negatives_feedbacks'].includes(status)" x-cloak class="pt-2 border-t border-slate-100">
          <label class="form-label">Negative Feedback Cause(s)</label>
          <div class="flex flex-wrap gap-3 mt-1">
            @foreach($negativeCauses as $cause)
            <label class="flex items-center gap-1.5 text-sm text-slate-600 cursor-pointer">
              <input type="checkbox" name="negative_feedback_causes[]" value="{{ $cause }}"
                     {{ in_array($cause, old('negative_feedback_causes', [])) ? 'checked' : '' }}
                     class="accent-indigo-600">
              {{ $cause }}
            </label>
            @endforeach
          </div>
          <label class="flex items-center gap-2 mt-3 text-sm text-slate-600 cursor-pointer">
            <input type="checkbox" name="negative_feedback_resolved" value="1" {{ old('negative_feedback_resolved') ? 'checked' : '' }} class="accent-indigo-600">
            Mark negative feedback resolved
          </label>
        </div>

        <div>
          <label class="form-label">Note <span class="text-red-500" x-show="['technical_issues', 'potential_negatives', 'negatives_feedbacks'].includes(status)" x-cloak>*</span></label>
          <textarea name="informations" rows="3" class="form-input" x-bind:required="['technical_issues', 'potential_negatives', 'negatives_feedbacks'].includes(status)">{{ old('informations') }}</textarea>
        </div>

      </div>

      <div class="px-6 py-4 flex gap-3 justify-end bg-slate-50">
        <a href="{{ route('crm.ebay.customers.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Record</button>
      </div>
    </form>
  </div>

  <datalist id="ebay-catalog-products">
    @foreach($catalogProducts as $p)
    <option value="{{ $p->name }}">{{ $p->sku }}</option>
    @endforeach
  </datalist>
</div>
@endsection
