{{--
  Reusable Searchable Store Dropdown
  Usage: @include('crm.partials.store-searchable-select', ['name' => 'store_id', 'selected' => $selectedId, 'stores' => $stores])
--}}
@php $uid = 'store_sel_' . str_replace([' ','.','-'], '_', $name . '_' . uniqid()); @endphp
<div x-data="{
    open: false,
    search: '',
    selected: {{ $selected ?? 'null' }},
    selectedLabel: '{{ $stores->firstWhere('id', $selected ?? 0)?->store_name ?? '' }}',
    stores: {{ $stores->map(fn($s) => ['id' => $s->id, 'label' => $s->store_name])->toJson() }},
    get filtered() {
        if (!this.search) return this.stores;
        const q = this.search.toLowerCase();
        return this.stores.filter(s => s.label.toLowerCase().includes(q));
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
        <span x-text="selectedLabel || '— Select Store —'"></span>
        <svg class="w-4 h-4 text-slate-400 flex-shrink-0 transition-transform" :class="{'rotate-180': open}"
             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
        </svg>
    </button>

    {{-- Dropdown --}}
    <div x-show="open" @click.outside="open=false" x-transition x-cloak
         class="absolute z-50 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden">
        <div class="p-2 border-b border-slate-100">
            <input type="search" x-model="search" placeholder="Search store…"
                   class="w-full text-sm px-3 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   @click.stop>
        </div>
        <div class="max-h-52 overflow-y-auto">
            <button type="button" @click="clear()"
                    class="w-full text-left px-3 py-2 text-sm text-slate-400 hover:bg-slate-50">— Clear selection —</button>
            <template x-for="s in filtered" :key="s.id">
                <button type="button" @click="select(s.id, s.label)"
                        class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 hover:text-indigo-700 transition-colors"
                        :class="selected === s.id ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-700'"
                        x-text="s.label">
                </button>
            </template>
            <div x-show="filtered.length === 0" class="px-3 py-4 text-sm text-slate-400 text-center">No stores found</div>
        </div>
    </div>
</div>
