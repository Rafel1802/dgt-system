<?php
// PHP script to build the new index.blade.php
$content = <<<'BLADE'
@extends('layouts.app')

@section('title', 'External Systems')
@section('page_title', 'External Systems')
@section('meta_description', 'Configure external tool URLs for the Digital Board menus.')

@section('content')
@php
    $tools = collect($tools ?? []);

    // Helper: apply saved order to a collection of tools
    $applyOrder = function($toolCollection, $orderKey) use ($settings) {
        $savedOrder = json_decode($settings[$orderKey] ?? '[]', true) ?: [];
        if (empty($savedOrder)) return $toolCollection;
        return $toolCollection->sortBy(function($t) use ($savedOrder) {
            $key = $t['key'] ?? $t['custom_id'] ?? null;
            $pos = $key ? array_search($key, $savedOrder) : false;
            return $pos !== false ? $pos : 999;
        })->values();
    };

    // We prepare the tools by ensuring they have '_static_key' if they are static
    $prepareTools = function($collection) use ($settings) {
        return $collection->map(function($tool) use ($settings) {
            // If it has a 'key', it's static
            if (isset($tool['key'])) {
                $tool['_static_key'] = $tool['key'];
                // We also inject the current saved value from settings overrides just in case
                $tool['label'] = $settings[$tool['key'] . '_label'] ?? ($tool['label'] ?? '');
                $tool['url']   = $settings[$tool['key']] ?? ($tool['url'] ?? '');
                $tool['icon_url'] = $settings[$tool['key'] . '_icon'] ?? ($tool['icon_url'] ?? '');
            } else {
                // It's a custom tool, ensure it has a custom_id
                $tool['custom_id'] = $tool['custom_id'] ?? ('custom_' . Str::random(9));
            }
            return $tool;
        })->values();
    };

    $boardTools     = $applyOrder($prepareTools($tools->where('group', 'board')),     'board_tools_order');
    $generatorTools = $applyOrder($prepareTools($tools->where('group', 'generator')), 'generator_tools_order');
    $workspaceTools = $applyOrder($prepareTools($tools->where('group', 'workspace')), 'workspace_tools_order');
    $aiTools        = $applyOrder($prepareTools($tools->where('group', 'ai')),        'custom_ai_tools_order'); // AI tools order is implicitly their array order, but let's just pass them. wait, AI tools don't have ai_tools_order currently.

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
        <p class="mt-2 text-sm font-medium text-blue-100 max-w-2xl">Configure URLs that power the Digital Dashboard buttons, board toolbar links, and sidebar shortcuts. <strong class="text-white">Drag</strong> any card to reorder within its section.</p>
      </div>
      <div class="flex-shrink-0">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white/15 hover:bg-white/25 text-white text-sm font-semibold ring-1 ring-white/25 transition-all">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
          </svg>
          Back to Dashboard
        </a>
      </div>
    </div>
  </div>

  {{-- ── Form ─────────────────────────────────────────────────────────────── --}}
  <form action="{{ route('admin.settings.store') }}" method="POST" class="space-y-8" id="external-systems-form">
    @csrf

    @include('admin.settings._dynamic_section', [
      'title' => 'eBay & Web Supporter',
      'description' => 'Image hosting, backup server, and eBay template systems.',
      'tools' => $boardTools,
      'color' => 'blue',
      'orderInput' => 'board_tools_order',
      'customInput' => 'custom_board_tools',
      'sortableId' => 'board-tools-sortable',
      'buttonText' => 'Add Web Tool'
    ])

    <hr class="border-slate-100 dark:border-slate-700/60">

    @include('admin.settings._dynamic_section', [
      'title' => 'System Supporter',
      'description' => 'Prompt, selling point, thumbnail, spec, and approval workflows.',
      'tools' => $generatorTools,
      'color' => 'violet',
      'orderInput' => 'generator_tools_order',
      'customInput' => 'custom_generator_tools',
      'sortableId' => 'generator-tools-sortable',
      'buttonText' => 'Add System Tool'
    ])

    <hr class="border-slate-100 dark:border-slate-700/60">

    @include('admin.settings._dynamic_section', [
      'title' => 'Google Workspace',
      'description' => 'Configure external links for Google Workspace integration.',
      'tools' => $workspaceTools,
      'color' => 'emerald',
      'orderInput' => 'workspace_tools_order',
      'customInput' => 'custom_workspace_tools',
      'sortableId' => 'workspace-tools-sortable',
      'buttonText' => 'Add Workspace Tool'
    ])

    <hr class="border-slate-100 dark:border-slate-700/60">

    @include('admin.settings._dynamic_section', [
      'title' => 'AI Tools Collapsible Menu',
      'description' => 'All tools appear in the sidebar in this exact order. Drag any card to reorder.',
      'tools' => $aiTools,
      'color' => 'amber',
      'orderInput' => 'ai_tools_order', // Custom AI tools just need custom array, but we can reuse the order input safely
      'customInput' => 'custom_ai_tools',
      'sortableId' => 'ai-tools-sortable',
      'buttonText' => 'Add AI Tool'
    ])

    {{-- ── Save Footer ──────────────────────────────────────────────────── --}}
    <div class="card border border-slate-200 dark:border-slate-700 p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
          </svg>
        </div>
        <p class="text-sm text-slate-600 dark:text-slate-400">Leave a field empty to hide that shortcut from non-admin users.</p>
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
              animation: 200,
              ghostClass: 'opacity-30',
              chosenClass: '!shadow-2xl !ring-2 !ring-blue-400',
              forceFallback: true,
              fallbackClass: 'sortable-fallback',
              fallbackOnBody: true,
              onEnd: (evt) => {
                if (evt.oldIndex === evt.newIndex) return;
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
BLADE;
file_put_contents('resources/views/admin/settings/index.blade.php', $content);
