@extends('layouts.app')

@section('title', 'CRM External Links')
@section('page_title', 'CRM External Links')

@section('content')
@php
    $prepareTools = function($links) {
        return collect($links)->map(function($link) {
            return [
                'id'          => $link->id,
                'custom_id'   => 'link_' . $link->id,
                'label'       => $link->name,
                'url'         => $link->url,
                'icon_url'    => $link->icon_url,
                'description' => $link->description,
            ];
        })->values();
    };
    $tools = $prepareTools($links);
@endphp

<div class="animate-fade-in w-full space-y-8">
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  {{-- ── Hero Banner ─────────────────────────────────────────────────────── --}}
  <div class="overflow-hidden rounded-2xl relative" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #1d4ed8 100%);">
    <div class="absolute inset-0 opacity-10" style="background-image: url(&quot;data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E&quot;);"></div>
    <div class="relative p-8 flex flex-col sm:flex-row sm:items-center gap-6">
      <div class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded-2xl bg-white/15 shadow-inner ring-1 ring-white/25 backdrop-blur-sm">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-10 w-10 text-white">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/>
        </svg>
      </div>
      <div class="flex-1">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-300 mb-1">Admin Controls</p>
        <h1 class="font-display text-3xl font-black text-white sm:text-4xl">External System Links</h1>
        <p class="mt-2 text-sm font-medium text-blue-100 max-w-2xl">Configure URLs that power the CRM Dashboard buttons and sidebar shortcuts. <strong class="text-white">Drag</strong> any card to reorder within its section.</p>
      </div>
    </div>
  </div>

  {{-- ── Form ─────────────────────────────────────────────────────────────── --}}
  <form action="{{ route('crm.links.bulkUpdate') }}" method="POST" class="space-y-8" id="crm-external-systems-form">
    @csrf

    @php
        $color = 'blue';
        $c = [
            'bg' => 'bg-blue-50/50 dark:bg-blue-950/20',
            'border' => 'border-blue-100 dark:border-blue-900/30',
            'accent' => 'bg-blue-500 text-white',
            'hover' => 'hover:border-blue-300 dark:hover:border-blue-800',
            'iconBg' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400'
        ];
    @endphp

    <section x-data="dynamicToolsEditor(@js($tools), 'crm_tools_order')" x-init="initSortable('crm-tools-sortable')" class="space-y-4">
        {{-- Hidden inputs to serialize state back to the server --}}
        <input type="hidden" name="crm_tools_order" :value="JSON.stringify(tools.map(t => t.custom_id))">
        <input type="hidden" name="custom_crm_tools" :value="JSON.stringify(tools.map(t => ({ id: t.id, custom_id: t.custom_id, label: t.label, url: t.url, icon_url: t.icon_url, description: t.description })))">

        {{-- Section Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">CRM External Links</h2>
                <p class="mt-1 text-sm font-semibold text-slate-400">Quick access links for the CRM team.</p>
            </div>
            <button type="button" @click="addTool()" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-300 text-xs font-bold transition-all border border-slate-200/50 dark:border-slate-700/50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add Web Tool
            </button>
        </div>

        {{-- Cards Grid --}}
        <div id="crm-tools-sortable" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <template x-for="(tool, index) in tools" :key="tool._id">
                <div 
                    class="card p-5 border-2 transition-all duration-200 hover:shadow-md relative group flex flex-col justify-between bg-slate-50 dark:bg-slate-800 border-dashed border-slate-200 dark:border-slate-700 {{ $c['hover'] }}"
                >
                    <div>
                        {{-- Drag Handle + Remove Button --}}
                        <div class="flex items-center justify-between mb-4">
                            <div class="drag-handle w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 cursor-grab active:cursor-grabbing transition-all select-none" title="Drag to reorder">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/>
                                </svg>
                            </div>
                            
                            <button type="button" @click="removeTool(index)" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/20 transition-all" title="Remove tool">
                                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </button>
                        </div>

                        {{-- Tool Header: Icon & Label --}}
                        <div class="flex items-center gap-3 mb-4 pl-1">
                            <div class="h-11 w-11 rounded-xl flex items-center justify-center flex-shrink-0 {{ $c['iconBg'] }}">
                                <template x-if="tool.icon_url">
                                    <img :src="tool.icon_url" class="h-7 w-7 object-contain rounded" alt="">
                                </template>
                                <template x-if="!tool.icon_url">
                                    <span class="flex items-center justify-center text-slate-400 dark:text-slate-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                        </svg>
                                    </span>
                                </template>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate" x-text="tool.label || 'Custom Tool'"></div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5" x-text="tool.description || 'Custom External Link'"></div>
                            </div>
                        </div>

                        {{-- Inputs --}}
                        <div class="space-y-3">
                            {{-- URL Field --}}
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">URL</label>
                                <input 
                                    type="url"
                                    x-model="tool.url"
                                    placeholder="https://example.com"
                                    class="form-input w-full text-xs"
                                    required
                                >
                            </div>

                            {{-- Label Field --}}
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">Custom Label</label>
                                <input 
                                    type="text"
                                    x-model="tool.label"
                                    placeholder="Label"
                                    class="form-input w-full text-xs"
                                    required
                                >
                            </div>

                            {{-- Description Field --}}
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">Description <span class="text-slate-400/80 normal-case font-normal">(optional)</span></label>
                                <input 
                                    type="text"
                                    x-model="tool.description"
                                    placeholder="Brief description"
                                    class="form-input w-full text-xs"
                                >
                            </div>

                            {{-- Icon Field --}}
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">Icon URL <span class="text-slate-400/80 normal-case font-normal">(optional)</span></label>
                                <input 
                                    type="url"
                                    x-model="tool.icon_url"
                                    placeholder="https://.../icon.png"
                                    class="form-input w-full text-xs"
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </section>

    {{-- ── Save Footer ──────────────────────────────────────────────────── --}}
    <div class="card border border-slate-200 dark:border-slate-700 p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
          </svg>
        </div>
        <p class="text-sm text-slate-600 dark:text-slate-400">Add or edit your CRM quick links. Changes save instantly.</p>
      </div>
      <button type="submit" class="btn btn-primary px-8 py-2.5 text-sm flex-shrink-0 flex items-center gap-2">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/>
        </svg>
        Save External Links
      </button>
    </div>

  </form>
</div>

{{-- SortableJS loaded before inline JS --}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>

<script>
  function waitForSortable(cb, tries = 0) {
    if (typeof Sortable !== 'undefined') { cb(); return; }
    if (tries > 30) return;
    setTimeout(() => waitForSortable(cb, tries + 1), 100);
  }

  function dynamicToolsEditor(initialTools, orderInputId) {
    return {
      tools: initialTools.map((t, i) => ({ ...t, _id: i })),
      _nextId: initialTools.length,

      addTool() {
        this.tools.push({ 
          label: '', 
          url: '', 
          icon_url: '', 
          description: '',
          custom_id: 'custom_' + Math.random().toString(36).substr(2, 9),
          _id: this._nextId++ 
        });
      },

      removeTool(index) {
        this.tools.splice(index, 1);
      },

      initSortable(sortableId) {
        waitForSortable(() => {
          this.$nextTick(() => {
            const el = document.getElementById(sortableId);
            if (!el) return;

            Sortable.create(el, {
              handle: '.drag-handle',
              animation: 150,
              ghostClass: 'sortable-ghost',
              dragClass: 'sortable-drag',
              chosenClass: 'sortable-chosen',
              onEnd: (evt) => {
                if (evt.oldIndex === evt.newIndex) return;

                // Revert physical DOM move so Alpine.js handles re-rendering reactively
                if (evt.oldIndex < evt.newIndex) {
                  evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
                } else {
                  evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex + 1] || null);
                }

                // Update the reactive Alpine array
                const moved = this.tools.splice(evt.oldIndex, 1)[0];
                this.tools.splice(evt.newIndex, 0, moved);
              }
            });
          });
        });
      }
    };
  }
</script>
@endsection
