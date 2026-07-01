{{--
  Reusable Searchable Product Dropdown
  Usage: @include('crm.partials.product-searchable-select', ['name' => 'product_id', 'selected' => $selectedId, 'products' => $products])
  Also supports AJAX search via route: crm.products.search
--}}
@php $uid = 'prod_sel_' . str_replace([' ','.','-'], '_', $name . '_' . uniqid()); @endphp
<div x-data="{
    open: false,
    search: '',
    loading: false,
    selected: {{ $selected ?? 'null' }},
    selectedLabel: '{{ $products->firstWhere('id', $selected ?? 0) ? $products->firstWhere('id', $selected ?? 0)->name . ($products->firstWhere('id', $selected ?? 0)->sku ? ' (' . $products->firstWhere('id', $selected ?? 0)->sku . ')' : '') : '' }}',
    results: {{ $products->map(fn($p) => ['id' => $p->id, 'label' => $p->name . ($p->sku ? ' (' . $p->sku . ')' : ''), 'sku' => $p->sku])->toJson() }},
    get filtered() {
        if (!this.search) return this.results;
        const q = this.search.toLowerCase();
        return this.results.filter(p => p.label.toLowerCase().includes(q));
    },
    select(id, label) {
        this.selected = id;
        this.selectedLabel = label;
        this.open = false;
        this.search = '';
    },
    clear() { this.selected = null; this.selectedLabel = ''; }
}" class="relative" id="{{ $uid }}_wrap">

    <input type="hidden" name="{{ $name }}" :value="selected">

    {{-- Trigger button --}}
    <button type="button"
        @click="open = !open"
        class="form-input w-full text-left flex items-center justify-between cursor-pointer"
        :class="selected ? 'text-slate-800' : 'text-slate-400'">
        <span x-text="selectedLabel || '— Select Product —'"></span>
        <svg class="w-4 h-4 text-slate-400 flex-shrink-0 transition-transform" :class="{'rotate-180': open}"
             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
        </svg>
    </button>

    {{-- Dropdown --}}
    <div x-show="open" @click.outside="open=false" x-transition x-cloak
         class="absolute z-50 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden">
        <div class="p-2 border-b border-slate-100">
            <div class="relative">
                <input type="search" x-model.debounce.300="search" placeholder="Search by name or SKU…"
                       class="w-full text-sm px-3 py-1.5 pl-8 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       @click.stop>
                <svg class="absolute left-2 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            </div>
        </div>
        <div class="max-h-60 overflow-y-auto">
            <button type="button" @click="clear()"
                    class="w-full text-left px-3 py-2 text-sm text-slate-400 hover:bg-slate-50">— No product —</button>
            <template x-for="p in filtered" :key="p.id">
                <button type="button" @click="select(p.id, p.label)"
                        class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 hover:text-indigo-700 transition-colors"
                        :class="selected === p.id ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-700'">
                    <span x-text="p.label"></span>
                    <template x-if="p.sku">
                        <span class="ml-1 text-xs text-slate-400 font-mono" x-text="'SKU: ' + p.sku"></span>
                    </template>
                </button>
            </template>
            <div x-show="filtered.length === 0" class="px-3 py-4 text-sm text-slate-400 text-center">No products found</div>
        </div>
    </div>
</div>
