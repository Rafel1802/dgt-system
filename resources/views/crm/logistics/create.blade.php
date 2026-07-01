@extends('layouts.app')
@section('title', 'New Shipment')
@section('page_title', 'New Logistic Record')

@push('scripts')
<script>
window.__DGT_CUSTOMERS__ = {!! $customers->map(fn($c) => ['id'=>$c->id,'name'=>$c->name,'company'=>$c->company??'','phone'=>$c->phone??'','label'=>$c->name.($c->company?' — '.$c->company:'').($c->phone?' · '.$c->phone:'')])->values()->toJson() !!};
</script>
@endpush

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5">
    <a href="{{ route('crm.logistics.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Logistic CRM</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-2xl">🚛</div>
        <div>
          <h2 class="font-display font-bold text-slate-800 text-lg">Create Shipment Record</h2>
          <p class="text-slate-400 text-sm mt-0.5">Log a new delivery after order confirmation.</p>
        </div>
      </div>
    </div>

    <form method="POST" action="{{ route('crm.logistics.store') }}" class="divide-y divide-slate-100">
      @csrf

      {{-- Order Reference --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Order Reference</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

          {{-- ── Customer combobox (no New button) ── --}}
          <div>
            <label class="form-label">Customer <span class="text-red-500">*</span></label>
            @include('crm.partials.customer_combobox', [
                'customers' => $customers,
                'fieldId'   => 'logistic-customer',
                'fieldName' => 'customer_id',
                'required'  => true,
                'autofill'  => true,
            ])
            @error('customer_id')<p class="form-error">{{ $message }}</p>@enderror
          </div>

          <div>
            <label class="form-label">Internal Order ID</label>
            <input type="text" name="order_id" value="{{ old('order_id') }}"
                   class="form-input" placeholder="e.g. ORD-2026-001" id="field-order-id">
          </div>

          @if($ebayOrders->count())
          <div class="sm:col-span-2">
            <label class="form-label">Link to eBay Order</label>
            <select name="ebay_order_id" class="form-input" id="field-ebay-order">
              <option value="">— None (website/direct sale) —</option>
              @foreach($ebayOrders as $o)
                <option value="{{ $o->id }}" {{ old('ebay_order_id', request('ebay_order_id')) == $o->id ? 'selected' : '' }}>
                  {{ $o->ebay_order_id ?? '#'.$o->id }} — {{ $o->customer?->name }} (${{ number_format($o->sale_amount) }})
                </option>
              @endforeach
            </select>
          </div>
          @endif

          {{-- ── Product: combobox + keep "Add new product" modal ── --}}
          <div>
            <label class="form-label">Product</label>
            <div class="flex gap-2">
              <div class="flex-1 relative" id="product-search-wrap">
                <input type="hidden" name="product_id" id="product_id" value="{{ old('product_id') }}">
                <input type="text" id="product-search-input" autocomplete="off"
                       class="form-input pr-8 cursor-pointer"
                       placeholder="— Click to select or type to search —"
                       value="{{ old('product_id') ? App\Models\Product::find(old('product_id'))?->name : '' }}"
                       readonly>
                <span id="product-clear-btn"
                      class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-300 hover:text-rose-500 cursor-pointer hidden"
                      title="Clear">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </span>
                <span id="product-chevron"
                      class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 transition-transform duration-150{{ old('product_id') ? ' hidden' : '' }}">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                </span>
                
                {{-- Dropdown list --}}
                <div id="product-panel"
                     class="hidden absolute z-[100] left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden py-1 max-h-52 overflow-y-auto text-sm">
                  <div class="px-3 pt-2 pb-2 border-b border-slate-100">
                    <input type="text"
                           id="product-search"
                           class="w-full px-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-400 bg-slate-50"
                           placeholder="Filter products…"
                           autocomplete="off">
                  </div>
                  <ul id="product-dropdown">
                    @foreach($products as $p)
                      <li class="px-4 py-2.5 cursor-pointer hover:bg-indigo-50 hover:text-indigo-700 transition-colors"
                          data-pid="{{ $p->id }}"
                          data-pname="{{ $p->name }}">
                        {{ $p->category?->icon() }} {{ $p->name }}
                      </li>
                    @endforeach
                    @if($products->isEmpty())
                      <li class="px-4 py-3 text-slate-400 italic text-center">No active products in catalogue.</li>
                    @endif
                  </ul>
                  <p id="product-empty" class="hidden px-4 py-3 text-sm text-slate-400 italic text-center">No matching products.</p>
                </div>
              </div>
              <button type="button" id="btn-add-product"
                      class="btn btn-secondary px-3 shrink-0 flex items-center gap-1 whitespace-nowrap"
                      title="Add new product">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New
              </button>
            </div>
          </div>

          <div>
            <label class="form-label">Product Description</label>
            <input type="text" name="product_description" value="{{ old('product_description') }}"
                   class="form-input" placeholder="Extra notes on product / spec" id="field-product-desc">
          </div>
        </div>
      </div>

      {{-- Recipient / Delivery Details --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Recipient & Delivery Address</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Recipient Name <span class="text-red-500">*</span></label>
            <input type="text" name="recipient_name" value="{{ old('recipient_name') }}"
                   class="form-input" placeholder="Who receives the delivery?" id="field-recipient-name" required>
            @error('recipient_name')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">Recipient Phone <span class="text-red-500">*</span></label>
            <input type="tel" name="recipient_phone" value="{{ old('recipient_phone') }}"
                   class="form-input" placeholder="+61 4xx xxx xxx" id="field-recipient-phone" required>
            @error('recipient_phone')<p class="form-error">{{ $message }}</p>@enderror
          </div>
        </div>
        <div>
          <label class="form-label">Shipping / Delivery Address <span class="text-red-500">*</span></label>
          <textarea name="shipping_address" rows="3" class="form-input @error('shipping_address') error @enderror"
                    placeholder="Full delivery address including suburb, state, postcode" required>{{ old('shipping_address') }}</textarea>
          @error('shipping_address')<p class="form-error">{{ $message }}</p>@enderror
        </div>
      </div>

      {{-- Truck & Driver --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Truck & Driver (fill when confirmed)</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Truck Company</label>
            <select name="truck_company_id" class="form-input" id="field-truck-company">
              <option value="">— Select Company (Optional) —</option>
              @foreach($truckingCompanies as $tc)
                <option value="{{ $tc->id }}" {{ old('truck_company_id') == $tc->id ? 'selected' : '' }}>
                  {{ $tc->company_name }}
                </option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Driver Name</label>
            <input type="text" name="driver_name" value="{{ old('driver_name') }}"
                   class="form-input" placeholder="Driver full name" id="field-driver-name">
          </div>
          <div>
            <label class="form-label">Driver Phone</label>
            <input type="tel" name="driver_phone" value="{{ old('driver_phone') }}"
                   class="form-input" placeholder="+61 4xx xxx xxx" id="field-driver-phone">
          </div>
          <div>
            <label class="form-label">Shipping Budget (AUD)</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
              <input type="number" name="shipping_budget" value="{{ old('shipping_budget') }}"
                     class="form-input pl-7" placeholder="0.00" step="0.01" id="field-budget">
            </div>
          </div>
        </div>
      </div>

      {{-- Dates & Assignment --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Schedule & Assignment</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Pickup Date & Time</label>
            <input type="datetime-local" name="pickup_datetime" value="{{ old('pickup_datetime') }}"
                   class="form-input" id="field-pickup">
          </div>
          <div>
            <label class="form-label">Estimated Arrival Date</label>
            <input type="date" name="estimated_arrival" value="{{ old('estimated_arrival') }}"
                   class="form-input" id="field-eta">
          </div>
          <div>
            <label class="form-label">Assigned Staff (CRM Member)</label>
            @include('crm.partials.member-searchable-select', [
              'name'     => 'assigned_to',
              'selected' => old('assigned_to'),
              'members'  => $crmUsers
            ])
          </div>
        </div>
        <div>
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="2" class="form-input" placeholder="Any special instructions or notes…">{{ old('notes') }}</textarea>
        </div>
      </div>

      <div class="px-6 py-4 flex gap-3 justify-end bg-slate-50">
        <a href="{{ route('crm.logistics.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" id="btn-create-shipment">Create Shipment</button>
      </div>
    </form>
  </div>
</div>

{{-- ══ Modal: Add New Product ═══════════════════════════════════════════════ --}}
<div id="modal-add-product"
     class="fixed inset-0 z-[200] flex items-center justify-center hidden"
     role="dialog" aria-modal="true" aria-labelledby="modal-product-title">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" id="modal-product-backdrop"></div>
  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6 space-y-4">
    <div class="flex items-center justify-between">
      <h3 id="modal-product-title" class="font-display font-bold text-slate-800 text-base">Add New Product</h3>
      <button type="button" id="modal-product-close" class="text-slate-400 hover:text-slate-600 text-xl leading-none">×</button>
    </div>
    <div id="modal-product-error" class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>
    <div class="space-y-3">
      <div>
        <label class="form-label">Product Name <span class="text-red-500">*</span></label>
        <input type="text" id="qp-name" class="form-input" placeholder="e.g. CAT 320 Excavator" autocomplete="off">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="form-label">Category <span class="text-red-500">*</span></label>
          <select id="qp-category" class="form-input">
            <option value="">— Select —</option>
            @foreach(\App\Enums\ProductCategory::cases() as $cat)
              <option value="{{ $cat->value }}">{{ $cat->icon() }} {{ $cat->label() }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="form-label">SKU / Model</label>
          <input type="text" id="qp-sku" class="form-input" placeholder="Optional">
        </div>
      </div>
    </div>
    <div class="flex gap-3 justify-end pt-1">
      <button type="button" id="modal-product-cancel" class="btn btn-secondary">Cancel</button>
      <button type="button" id="modal-product-save" class="btn btn-primary">
        <span id="modal-product-save-text">Add Product</span>
        <span id="modal-product-save-spin" class="hidden ml-2 inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
      </button>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';

  const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const QUICK_PRODUCT_URL = "{{ route('crm.logistics.quick.product') }}";

  // ── Product Select Logic ──────────────────────────────────────────────────
  const pInput   = document.getElementById('product-search-input');
  const pHidden  = document.getElementById('product_id');
  const pClear   = document.getElementById('product-clear-btn');
  const pChevron = document.getElementById('product-chevron');
  const pPanel   = document.getElementById('product-panel');
  const pSearch  = document.getElementById('product-search');
  const pList    = document.getElementById('product-dropdown');
  const pItems   = pList.querySelectorAll('li');
  const pEmpty   = document.getElementById('product-empty');
  
  let pOpen = false;

  function closePPanel() {
    pOpen = false;
    pPanel.classList.add('hidden');
    pChevron.classList.remove('rotate-180');
    pInput.setAttribute('readonly', '');
    pSearch.value = '';
    filterPList('');
  }

  function openPPanel() {
    pOpen = true;
    pPanel.classList.remove('hidden');
    pChevron.classList.add('rotate-180');
    pInput.removeAttribute('readonly');
    setTimeout(() => pSearch.focus(), 50);
  }

  function filterPList(q) {
    q = q.toLowerCase();
    let count = 0;
    pItems.forEach(li => {
      const txt = li.textContent.toLowerCase();
      if (txt.includes(q)) {
        li.style.display = '';
        count++;
      } else {
        li.style.display = 'none';
      }
    });
    pEmpty.classList.toggle('hidden', count > 0);
    pList.classList.toggle('hidden', count === 0);
  }

  pInput.addEventListener('click', () => {
    pOpen ? closePPanel() : openPPanel();
  });

  pSearch.addEventListener('input', e => filterPList(e.target.value));
  pSearch.addEventListener('keydown', e => {
    if (e.key === 'Escape') closePPanel();
  });

  document.addEventListener('click', e => {
    const wrap = document.getElementById('product-search-wrap');
    if (wrap && !wrap.contains(e.target)) {
      closePPanel();
    }
  });

  pItems.forEach(li => {
    li.addEventListener('mousedown', e => {
      e.preventDefault();
      selectProduct(li.dataset.pid, li.dataset.pname);
    });
  });

  function selectProduct(id, name) {
    pHidden.value = id;
    pInput.value = name;
    pClear.classList.remove('hidden');
    pChevron.classList.add('hidden');
    closePPanel();
  }

  pClear.addEventListener('click', e => {
    e.stopPropagation();
    pHidden.value = '';
    pInput.value = '';
    pClear.classList.add('hidden');
    pChevron.classList.remove('hidden');
  });

  // ── Modal helpers ────────────────────────────────────────────────────────
  function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
  function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
  function setLoading(saveBtn, spinEl, loading) {
    saveBtn.disabled = loading;
    spinEl.classList.toggle('hidden', !loading);
  }
  function showError(el, msg) {
    el.textContent = msg;
    el.classList.remove('hidden');
  }
  function clearError(el) { el.classList.add('hidden'); el.textContent = ''; }

  // ── Add Product Modal ────────────────────────────────────────────────────
  const btnAddProduct = document.getElementById('btn-add-product');
  if (btnAddProduct) {
    btnAddProduct.addEventListener('click', () => {
      document.getElementById('qp-name').value     = '';
      document.getElementById('qp-category').value = '';
      document.getElementById('qp-sku').value      = '';
      clearError(document.getElementById('modal-product-error'));
      openModal('modal-add-product');
      setTimeout(() => document.getElementById('qp-name').focus(), 100);
    });

    ['modal-product-close','modal-product-cancel','modal-product-backdrop'].forEach(id => {
      document.getElementById(id).addEventListener('click', () => closeModal('modal-add-product'));
    });

    document.getElementById('modal-product-save').addEventListener('click', () => {
      const name     = document.getElementById('qp-name').value.trim();
      const category = document.getElementById('qp-category').value;
      const sku      = document.getElementById('qp-sku').value.trim();
      const errEl    = document.getElementById('modal-product-error');
      const saveBtn  = document.getElementById('modal-product-save');
      const spinEl   = document.getElementById('modal-product-save-spin');

      clearError(errEl);
      if (!name)     { showError(errEl, 'Product name is required.');     return; }
      if (!category) { showError(errEl, 'Please select a category.');     return; }

      setLoading(saveBtn, spinEl, true);

      fetch(QUICK_PRODUCT_URL, {
        method  : 'POST',
        headers : { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        body    : JSON.stringify({ name, category, sku }),
      })
      .then(async r => {
        const data = await r.json();
        if (!r.ok) {
          const msg = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message ?? 'Error saving product.');
          showError(errEl, msg);
          return;
        }
        
        // Add to DOM list
        const li = document.createElement('li');
        li.className = 'px-4 py-2.5 cursor-pointer hover:bg-indigo-50 hover:text-indigo-700 transition-colors';
        li.dataset.pid = data.id;
        li.dataset.pname = data.name;
        li.textContent = data.text || data.name;
        li.addEventListener('mousedown', e => {
          e.preventDefault();
          selectProduct(data.id, data.name);
        });
        
        // Remove empty state if any
        if (pEmpty) pEmpty.classList.add('hidden');
        pList.appendChild(li);
        
        selectProduct(data.id, data.name);
        closeModal('modal-add-product');
      })
      .catch(() => showError(errEl, 'Network error. Please try again.'))
      .finally(() => setLoading(saveBtn, spinEl, false));
    });

    // Keyboard
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeModal('modal-add-product');
    });

    ['qp-name','qp-sku'].forEach(id => {
      document.getElementById(id).addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('modal-product-save').click(); }
      });
    });
  }

})();
</script>
@endpush
