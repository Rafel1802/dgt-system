{{--
  Customer Combobox — reusable across CRM forms.
  Supports search, inline quick-create, and optional recipient autofill.
--}}
@php
  $fieldName = $fieldName ?? 'customer_id';
  $fieldId   = $fieldId   ?? 'customer_combobox';
  $required  = $required  ?? false;
  $selected  = old($fieldName, $selected ?? '');
  $autofill  = $autofill  ?? false;
  $allowCreate = $allowCreate ?? true;
  $quickCreateUrl = $quickCreateUrl ?? route('crm.customers.quick-create');
  $quickCreateSource = $quickCreateSource ?? '';
  $autofillNameId = $autofillNameId ?? 'field-recipient-name';
  $autofillPhoneId = $autofillPhoneId ?? 'field-recipient-phone';
  $autofillEmailId = $autofillEmailId ?? '';
  $autofillAddressId = $autofillAddressId ?? '';
  $autofillUsernameId = $autofillUsernameId ?? '';
  // Leads (people who haven't become a full Customer record yet) can optionally be searched
  // alongside customers — used by the shipment "Add Customer" picker. Off by default so the
  // many other forms using this partial are unaffected.
  $leads = $leads ?? collect();
  $includeLatestOrder = $includeLatestOrder ?? false;

  $customerJson = $customers->map(fn($c) => [
      'id'      => $c->id,
      'type'    => 'customer',
      'name'    => $c->name,
      'company' => $c->company ?? '',
      'phone'   => $c->phone ?? '',
      'email'   => $c->email ?? '',
      'address' => $c->address ?? '',
      'label'   => $c->name . ($c->company ? ' — '.$c->company : '') . ($c->phone ? ' · '.$c->phone : ''),
      'latest_order_product' => $includeLatestOrder ? ($c->latest_order_product ?? null) : null,
  ])->values()->toJson();

  $leadJson = $leads->map(fn($l) => [
      'id'      => $l->id,
      'type'    => 'lead',
      'name'    => $l->client_name,
      'company' => '',
      'phone'   => $l->client_phone ?? '',
      'email'   => $l->client_email ?? '',
      'address' => '',
      'label'   => $l->client_name . ' — Lead' . ($l->client_phone ? ' · '.$l->client_phone : ''),
      'latest_order_product' => $includeLatestOrder ? ($l->latest_order_product ?? null) : null,
  ])->values()->toJson();

  $oldLabel = '';
  if ($selected) {
      $found = $customers->firstWhere('id', (int) $selected);
      if ($found) {
          $oldLabel = $found->name . ($found->company ? ' — '.$found->company : '') . ($found->phone ? ' · '.$found->phone : '');
      }
  }
@endphp

<div
  class="relative"
  id="{{ $fieldId }}-wrap"
  data-combobox
  data-autofill="{{ $autofill ? 'true' : 'false' }}"
  data-autofill-name-id="{{ $autofillNameId }}"
  data-autofill-phone-id="{{ $autofillPhoneId }}"
  data-autofill-email-id="{{ $autofillEmailId }}"
  data-autofill-address-id="{{ $autofillAddressId }}"
  data-autofill-username-id="{{ $autofillUsernameId }}"
  data-allow-create="{{ $allowCreate ? 'true' : 'false' }}"
  data-quick-create-url="{{ $quickCreateUrl }}"
  data-quick-create-source="{{ $quickCreateSource }}"
  data-include-leads="{{ $leads->isNotEmpty() ? 'true' : 'false' }}"
  data-include-latest-order="{{ $includeLatestOrder ? 'true' : 'false' }}"
>
  <script type="application/json" id="{{ $fieldId }}-data">{!! $customerJson !!}</script>
  @if($leads->isNotEmpty())
    <script type="application/json" id="{{ $fieldId }}-leads-data">{!! $leadJson !!}</script>
  @endif

  <input type="hidden"
         name="{{ $fieldName }}"
         id="{{ $fieldId }}-hidden"
         value="{{ $selected }}"
         {{ $required ? 'required' : '' }}>

  <div class="relative">
    <input
      type="text"
      id="{{ $fieldId }}-input"
      class="form-input pr-8 cursor-pointer @error($fieldName) error @enderror"
      placeholder="— Click to select or type to search —"
      autocomplete="off"
      value="{{ $oldLabel }}"
      {{ $required ? 'required' : '' }}
      readonly
    >
    <span id="{{ $fieldId }}-chevron"
          class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 transition-transform duration-150">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
      </svg>
    </span>
    <button type="button"
            id="{{ $fieldId }}-clear"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-rose-500 transition-colors hidden"
            title="Clear selection"
            tabindex="-1">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <div id="{{ $fieldId }}-panel"
       class="hidden absolute z-[100] left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden"
       role="listbox">
    <div class="px-3 pt-3 pb-2 border-b border-slate-100">
      <div class="relative">
        <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
        <input type="text"
               id="{{ $fieldId }}-search"
               class="w-full pl-8 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400 bg-slate-50"
               placeholder="{{ $leads->isNotEmpty() ? 'Type to filter customers or leads...' : 'Type to filter customers...' }}"
               autocomplete="off">
      </div>
    </div>

    <ul id="{{ $fieldId }}-list" class="max-h-52 overflow-y-auto py-1" role="listbox"></ul>

    <p id="{{ $fieldId }}-empty" class="hidden px-4 py-3 text-sm text-slate-400 italic text-center">
      {{ $leads->isNotEmpty() ? 'No customers or leads match your search.' : 'No customers match your search.' }}
    </p>

    @if($allowCreate)
      <div class="border-t border-slate-100 bg-slate-50 p-2">
        <button type="button"
                id="{{ $fieldId }}-new"
                class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-bold text-indigo-700 ring-1 ring-slate-200 hover:bg-indigo-50">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
          </svg>
          Add new customer
        </button>
      </div>
    @else
      <div class="px-3 py-2 border-t border-slate-100 bg-slate-50 text-xs text-slate-400">
        Add customers from the Customers menu.
      </div>
    @endif
  </div>

  @if($allowCreate)
    <div id="{{ $fieldId }}-modal" class="fixed inset-0 z-[220] hidden items-center justify-center">
      <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" id="{{ $fieldId }}-modal-backdrop"></div>
      <div class="relative w-full max-w-md mx-4 overflow-hidden rounded-2xl bg-white shadow-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <div>
            <h3 class="font-display text-base font-black text-slate-900">Add New Customer</h3>
            <p class="mt-0.5 text-xs font-semibold text-slate-400">Only full name, email, and phone are needed here.</p>
          </div>
          <button type="button" id="{{ $fieldId }}-modal-close" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div class="space-y-4 p-5 overflow-y-auto">
          <div id="{{ $fieldId }}-modal-error" class="hidden rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-600"></div>
          <div>
            <label class="form-label">Full Name <span class="text-red-500">*</span></label>
            <input type="text" id="{{ $fieldId }}-new-name" class="form-input" placeholder="Customer full name" autocomplete="off">
          </div>
          <div>
            <label class="form-label">Email</label>
            <input type="email" id="{{ $fieldId }}-new-email" class="form-input" placeholder="customer@example.com" autocomplete="off">
          </div>
          <div>
            <label class="form-label">Phone</label>
            <input type="text" id="{{ $fieldId }}-new-phone" class="form-input" placeholder="+1 (207) 213-9077" autocomplete="off">
          </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4">
          <button type="button" id="{{ $fieldId }}-modal-cancel" class="btn btn-secondary text-sm">Cancel</button>
          <button type="button" id="{{ $fieldId }}-modal-save" class="btn btn-primary text-sm">
            <span>Add Customer</span>
            <span id="{{ $fieldId }}-modal-spin" class="hidden ml-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
          </button>
        </div>
      </div>
    </div>
  @endif
</div>

@once
@push('scripts')
<script>
(function() {
  'use strict';

  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

  function normalizeCustomer(c) {
    const label = c.label || c.text || [c.name, c.company ? '— ' + c.company : '', c.phone ? '· ' + c.phone : '']
      .filter(Boolean)
      .join(' ');

    return {
      id: String(c.id),
      type: c.type === 'lead' ? 'lead' : 'customer',
      name: c.name || label || 'Customer',
      company: c.company || '',
      phone: c.phone || '',
      email: c.email || '',
      address: c.address || '',
      label,
      latestOrderProduct: c.latest_order_product || '',
    };
  }

  function setIfEmpty(id, value) {
    if (!id || !value) return;
    const el = document.getElementById(id);
    if (el && !el.value) el.value = value;
  }

  function setError(el, message) {
    if (!el) return;
    el.textContent = message;
    el.classList.remove('hidden');
  }

  function clearError(el) {
    if (!el) return;
    el.textContent = '';
    el.classList.add('hidden');
  }

  function initCombobox(wrap) {
    if (wrap.dataset.comboboxInit === 'true') return;
    wrap.dataset.comboboxInit = 'true';

    const id = wrap.id.replace('-wrap', '');
    const dataNode = document.getElementById(id + '-data');
    const leadsDataNode = document.getElementById(id + '-leads-data');
    let customerData = [];

    try {
      customerData = JSON.parse(dataNode?.textContent || '[]').map(normalizeCustomer);
    } catch (_) {
      customerData = [];
    }

    if (leadsDataNode) {
      try {
        customerData = customerData.concat(JSON.parse(leadsDataNode.textContent || '[]').map(normalizeCustomer));
      } catch (_) { /* ignore malformed lead data */ }
    }

    const hidden = document.getElementById(id + '-hidden');
    const input = document.getElementById(id + '-input');
    const panel = document.getElementById(id + '-panel');
    const search = document.getElementById(id + '-search');
    const list = document.getElementById(id + '-list');
    const empty = document.getElementById(id + '-empty');
    const clearBtn = document.getElementById(id + '-clear');
    const chevron = document.getElementById(id + '-chevron');
    const newBtn = document.getElementById(id + '-new');
    const modal = document.getElementById(id + '-modal');
    const modalError = document.getElementById(id + '-modal-error');
    const modalName = document.getElementById(id + '-new-name');
    const modalEmail = document.getElementById(id + '-new-email');
    const modalPhone = document.getElementById(id + '-new-phone');
    const modalSave = document.getElementById(id + '-modal-save');
    const modalSpin = document.getElementById(id + '-modal-spin');
    const quickCreateUrl = wrap.dataset.quickCreateUrl || '';
    const quickCreateSource = wrap.dataset.quickCreateSource || '';
    const autofill = wrap.dataset.autofill === 'true';
    let open = false;

    // Mirrors PhoneNumberFormatter server-side: only a clean 10-digit US
    // number (with or without a leading 1) gets reformatted, so partial
    // typing or non-US numbers are left alone rather than mangled.
    function formatUsPhone(raw) {
      const trimmed = raw.trim();
      if (!trimmed) return trimmed;
      let digits = trimmed.replace(/\D+/g, '');
      if (digits.length === 11 && digits.startsWith('1')) digits = digits.slice(1);
      if (digits.length !== 10) return trimmed;
      return `+1 (${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
    }

    modalPhone?.addEventListener('blur', () => {
      modalPhone.value = formatUsPhone(modalPhone.value);
    });

    function addCustomer(customer) {
      const c = normalizeCustomer(customer);
      if (!customerData.some(existing => existing.id === c.id)) {
        customerData.push(c);
        customerData.sort((a, b) => a.name.localeCompare(b.name));
      }
      if (open) renderList(filterCustomers(search.value));
      return c;
    }

    function filterCustomers(query) {
      const q = (query || '').trim().toLowerCase();
      if (!q) return customerData;

      return customerData.filter(c =>
        c.name.toLowerCase().includes(q) ||
        c.company.toLowerCase().includes(q) ||
        c.phone.toLowerCase().includes(q) ||
        c.email.toLowerCase().includes(q)
      );
    }

    function renderList(items) {
      list.innerHTML = '';

      if (items.length === 0) {
        empty.classList.remove('hidden');
        list.classList.add('hidden');
        return;
      }

      empty.classList.add('hidden');
      list.classList.remove('hidden');

      items.forEach(c => {
        const li = document.createElement('li');
        li.setAttribute('role', 'option');
        li.dataset.id = c.id;
        li.dataset.type = c.type;
        li.className = 'px-4 py-2.5 cursor-pointer text-sm transition-colors hover:bg-indigo-50 hover:text-indigo-700 flex flex-col';
        if (hidden.value === c.id && wrap.dataset.selectedType === c.type) li.classList.add('bg-indigo-50', 'text-indigo-700', 'font-semibold');

        const name = document.createElement('span');
        name.className = 'font-medium';
        name.textContent = c.name;
        if (c.type === 'lead') {
          const badge = document.createElement('span');
          badge.className = 'ml-1.5 inline-block rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700 align-middle';
          badge.textContent = 'Lead';
          name.appendChild(badge);
        }

        const metaText = [c.company, c.phone, c.email].filter(Boolean).join(' · ');
        li.appendChild(name);
        if (metaText) {
          const meta = document.createElement('span');
          meta.className = 'text-xs text-slate-400 mt-0.5';
          meta.textContent = metaText;
          li.appendChild(meta);
        }

        li.addEventListener('mousedown', e => {
          e.preventDefault();
          selectCustomer(c);
        });
        list.appendChild(li);
      });
    }

    function openPanel() {
      if (open) return;
      open = true;
      panel.classList.remove('hidden');
      chevron.classList.add('rotate-180');
      input.removeAttribute('readonly');
      highlighted = -1;
      renderList(filterCustomers(search.value));
      setTimeout(() => search.focus(), 50);
    }

    function closePanel() {
      if (!open) return;
      open = false;
      panel.classList.add('hidden');
      chevron.classList.remove('rotate-180');
      input.setAttribute('readonly', '');
      search.value = '';
    }

    function selectCustomer(c) {
      // Leads aren't Customer records — the hidden field is an FK to `customers`,
      // so a lead selection is used purely as a data source for autofill below,
      // never submitted as customer_id.
      hidden.value = c.type === 'lead' ? '' : c.id;
      wrap.dataset.selectedType = c.type;
      input.value = c.label;
      clearBtn.classList.remove('hidden');
      chevron.classList.add('hidden');
      closePanel();

      if (autofill) {
        setIfEmpty(wrap.dataset.autofillNameId, c.name);
        setIfEmpty(wrap.dataset.autofillPhoneId, c.phone);
        setIfEmpty(wrap.dataset.autofillEmailId, c.email);
        setIfEmpty(wrap.dataset.autofillAddressId, c.address);
        setIfEmpty(wrap.dataset.autofillUsernameId, c.name);
      }

      if (wrap.dataset.includeLatestOrder === 'true') {
        wrap.dispatchEvent(new CustomEvent('dgt:latest-order-product', {
          bubbles: true,
          detail: { fieldId: id, productName: c.latestOrderProduct || '' },
        }));
      }
    }

    function openCreateModal() {
      if (!modal) return;
      clearError(modalError);
      modalName.value = search.value.trim();
      modalEmail.value = '';
      modalPhone.value = '';
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      closePanel();
      setTimeout(() => modalName.focus(), 80);
    }

    function closeCreateModal() {
      if (!modal) return;
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      clearError(modalError);
    }

    function saveNewCustomer() {
      if (!quickCreateUrl || !modalSave) return;

      const name = modalName.value.trim();
      const email = modalEmail.value.trim();
      const phone = modalPhone.value.trim();

      clearError(modalError);
      if (!name) {
        setError(modalError, 'Full name is required.');
        return;
      }

      modalSave.disabled = true;
      modalSpin?.classList.remove('hidden');

      const payload = { name, email, phone };
      if (quickCreateSource) payload.source = quickCreateSource;

      fetch(quickCreateUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
      })
      .then(async response => {
        const data = await response.json();
        if (!response.ok) {
          const message = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Could not create customer.');
          setError(modalError, message);
          return;
        }

        const created = addCustomer(data);
        selectCustomer(created);
        window.dispatchEvent(new CustomEvent('dgt:customer-created', { detail: created }));
        closeCreateModal();
      })
      .catch(() => setError(modalError, 'Network error. Please try again.'))
      .finally(() => {
        modalSave.disabled = false;
        modalSpin?.classList.add('hidden');
      });
    }

    clearBtn.addEventListener('click', e => {
      e.stopPropagation();
      hidden.value = '';
      input.value = '';
      delete wrap.dataset.selectedType;
      clearBtn.classList.add('hidden');
      chevron.classList.remove('hidden');
    });

    // Enter only ever selects a name-searched match when the user has actually
    // arrow-key-highlighted it first — never the "first result" just because
    // they typed a name and hit Enter out of habit. Names alone are ambiguous
    // (two real, different customers can share a name), so a blind Enter used
    // to silently link whatever record was on top — including someone
    // unrelated to what was actually typed. Enter with zero results (safe,
    // unambiguous) still opens the create-new shortcut.
    let highlighted = -1;

    function applyHighlight() {
      const items = list.querySelectorAll('li');
      items.forEach((li, i) => li.classList.toggle('bg-indigo-50', i === highlighted));
    }

    input.addEventListener('click', () => { open ? closePanel() : openPanel(); });
    search.addEventListener('input', () => {
      highlighted = -1;
      renderList(filterCustomers(search.value));
    });
    search.addEventListener('keydown', e => {
      const items = list.querySelectorAll('li');

      if (e.key === 'Escape') closePanel();

      if (e.key === 'ArrowDown') {
        if (items.length) highlighted = (highlighted + 1) % items.length;
        applyHighlight();
        e.preventDefault();
      }

      if (e.key === 'ArrowUp') {
        if (items.length) highlighted = (highlighted - 1 + items.length) % items.length;
        applyHighlight();
        e.preventDefault();
      }

      if (e.key === 'Enter') {
        if (items.length && highlighted >= 0 && highlighted < items.length) {
          const id = items[highlighted].dataset.id;
          const type = items[highlighted].dataset.type;
          const c = customerData.find(item => item.id === id && item.type === type);
          if (c) selectCustomer(c);
        } else if (items.length === 0 && newBtn) {
          openCreateModal();
        }
        e.preventDefault();
      }
    });

    newBtn?.addEventListener('click', openCreateModal);
    document.getElementById(id + '-modal-close')?.addEventListener('click', closeCreateModal);
    document.getElementById(id + '-modal-cancel')?.addEventListener('click', closeCreateModal);
    document.getElementById(id + '-modal-backdrop')?.addEventListener('click', closeCreateModal);
    modalSave?.addEventListener('click', saveNewCustomer);
    [modalName, modalEmail, modalPhone].forEach(el => el?.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        saveNewCustomer();
      }
    }));

    document.addEventListener('click', e => {
      if (!wrap.contains(e.target)) closePanel();
    });

    window.addEventListener('dgt:customer-created', e => addCustomer(e.detail));

    if (hidden.value) {
      clearBtn.classList.remove('hidden');
      chevron.classList.add('hidden');
    }
  }

  function initAllCombobox() {
    document.querySelectorAll('[data-combobox]').forEach(initCombobox);
  }

  document.addEventListener('DOMContentLoaded', initAllCombobox);
  document.addEventListener('turbo:load', initAllCombobox);
})();
</script>
@endpush
@endonce
