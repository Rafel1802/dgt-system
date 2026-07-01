{{--
  Customer Combobox — reusable across all CRM create forms.
  Usage:
    @include('crm.partials.customer_combobox', [
        'customers'     => $customers,           // Collection of customers
        'fieldName'     => 'customer_id',        // Hidden input name (optional, default = 'customer_id')
        'fieldId'       => 'customer_combobox',  // Unique DOM id prefix (optional)
        'required'      => false,                // Mark field required (optional)
        'autofill'      => true,                 // Auto-fill name/phone when customer selected (optional)
    ])
--}}
@php
  $fieldName = $fieldName ?? 'customer_id';
  $fieldId   = $fieldId   ?? 'customer_combobox';
  $required  = $required  ?? false;
  $autofill  = $autofill  ?? false;

  // Build a compact JSON list for JS — avoids re-querying
  $customerJson = $customers->map(fn($c) => [
      'id'      => $c->id,
      'name'    => $c->name,
      'company' => $c->company ?? '',
      'phone'   => $c->phone ?? '',
      'label'   => $c->name . ($c->company ? ' — '.$c->company : '') . ($c->phone ? ' · '.$c->phone : ''),
  ])->values()->toJson();

  $oldId = old($fieldName, '');
  $oldLabel = '';
  if ($oldId) {
      $found = $customers->firstWhere('id', $oldId);
      if ($found) {
          $oldLabel = $found->name . ($found->company ? ' — '.$found->company : '') . ($found->phone ? ' · '.$found->phone : '');
      }
  }
@endphp

<div class="relative" id="{{ $fieldId }}-wrap" data-combobox>

  {{-- Hidden real value submitted with the form --}}
  <input type="hidden"
         name="{{ $fieldName }}"
         id="{{ $fieldId }}-hidden"
         value="{{ $oldId }}"
         {{ $required ? 'required' : '' }}>

  {{-- Visible combobox input --}}
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
    {{-- Chevron / clear icon --}}
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

  {{-- Dropdown panel --}}
  <div id="{{ $fieldId }}-panel"
       class="hidden absolute z-[100] left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden"
       role="listbox">
    {{-- Search within panel --}}
    <div class="px-3 pt-3 pb-2 border-b border-slate-100">
      <div class="relative">
        <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
        <input type="text"
               id="{{ $fieldId }}-search"
               class="w-full pl-8 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400 bg-slate-50"
               placeholder="Type to filter customers…"
               autocomplete="off">
      </div>
    </div>
    {{-- Options list --}}
    <ul id="{{ $fieldId }}-list"
        class="max-h-52 overflow-y-auto py-1"
        role="listbox">
      {{-- Populated by JS --}}
    </ul>
    {{-- Empty state --}}
    <p id="{{ $fieldId }}-empty"
       class="hidden px-4 py-3 text-sm text-slate-400 italic text-center">
       No customers match your search.
    </p>
    {{-- Hint --}}
    <div class="px-3 py-2 border-t border-slate-100 bg-slate-50 text-xs text-slate-400 flex items-center gap-1">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
      </svg>
      Customer must exist first. Add them via <a href="{{ route('crm.customers.create') }}" target="_blank" class="text-indigo-500 hover:underline ml-0.5 font-medium">Customers →</a>
    </div>
  </div>
</div>

@once
@push('scripts')
<script>
(function() {
  // ── Customer Combobox Engine ──────────────────────────────────────────────
  // Initialises every [data-combobox] element on the page.

  function initCombobox(wrap, customerData) {
    const id       = wrap.id.replace('-wrap', '');
    const hidden   = document.getElementById(id + '-hidden');
    const input    = document.getElementById(id + '-input');
    const panel    = document.getElementById(id + '-panel');
    const search   = document.getElementById(id + '-search');
    const list     = document.getElementById(id + '-list');
    const empty    = document.getElementById(id + '-empty');
    const clearBtn = document.getElementById(id + '-clear');
    const chevron  = document.getElementById(id + '-chevron');
    const autofill = wrap.dataset.autofill === 'true';

    let open = false;

    // ── Render option list ──
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
        li.className = 'px-4 py-2.5 cursor-pointer text-sm transition-colors hover:bg-indigo-50 hover:text-indigo-700 flex flex-col';
        // Highlight selected
        if (hidden.value == c.id) li.classList.add('bg-indigo-50', 'text-indigo-700', 'font-semibold');
        li.innerHTML = `<span class="font-medium">${c.name}${c.company ? `<span class="ml-1 text-slate-400 font-normal">— ${c.company}</span>` : ''}</span>${c.phone ? `<span class="text-xs text-slate-400 mt-0.5">${c.phone}</span>` : ''}`;
        li.addEventListener('mousedown', e => {
          e.preventDefault();
          selectCustomer(c);
        });
        list.appendChild(li);
      });
    }

    // ── Open / close ──
    function openPanel() {
      if (open) return;
      open = true;
      panel.classList.remove('hidden');
      chevron.classList.add('rotate-180');
      input.removeAttribute('readonly');
      renderList(customerData);
      setTimeout(() => search.focus(), 50);
    }

    function closePanel() {
      if (!open) return;
      open = false;
      panel.classList.add('hidden');
      chevron.classList.remove('rotate-180');
      input.setAttribute('readonly', '');
      search.value = '';
      renderList(customerData);
    }

    // ── Select ──
    function selectCustomer(c) {
      hidden.value  = c.id;
      input.value   = c.label;
      clearBtn.classList.remove('hidden');
      chevron.classList.add('hidden');
      closePanel();

      // Autofill recipient fields if enabled
      if (autofill) {
        const nameEl  = document.getElementById('field-recipient-name');
        const phoneEl = document.getElementById('field-recipient-phone');
        if (nameEl  && !nameEl.value)  nameEl.value  = c.name;
        if (phoneEl && !phoneEl.value && c.phone) phoneEl.value = c.phone;
      }
    }

    // ── Clear ──
    clearBtn.addEventListener('click', e => {
      e.stopPropagation();
      hidden.value = '';
      input.value  = '';
      clearBtn.classList.add('hidden');
      chevron.classList.remove('hidden');
    });

    // ── Open on click ──
    input.addEventListener('click', () => { open ? closePanel() : openPanel(); });

    // ── Filter in panel ──
    search.addEventListener('input', () => {
      const q = search.value.trim().toLowerCase();
      const filtered = q ? customerData.filter(c =>
        c.name.toLowerCase().includes(q) ||
        c.company.toLowerCase().includes(q) ||
        c.phone.includes(q)
      ) : customerData;
      renderList(filtered);
    });

    // ── Keyboard ──
    search.addEventListener('keydown', e => {
      if (e.key === 'Escape') closePanel();
      if (e.key === 'Enter') {
        const first = list.querySelector('li');
        if (first) {
          const cid = first.dataset.id;
          const c   = customerData.find(x => x.id == cid);
          if (c) selectCustomer(c);
        }
        e.preventDefault();
      }
    });

    // ── Close on outside click ──
    document.addEventListener('click', e => {
      if (!wrap.contains(e.target)) closePanel();
    });

    // ── Init clear button state ──
    if (hidden.value) {
      clearBtn.classList.remove('hidden');
      chevron.classList.add('hidden');
    }
  }

  // ── Boot all comboboxes on page ──
  document.addEventListener('DOMContentLoaded', function() {
    const customerData = window.__DGT_CUSTOMERS__ || [];
    document.querySelectorAll('[data-combobox]').forEach(wrap => {
      initCombobox(wrap, customerData);
    });
  });
})();
</script>
@endpush
@endonce
