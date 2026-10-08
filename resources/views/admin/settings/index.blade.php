@extends('layouts.app')

@section('title', 'External Systems')
@section('page_title', 'External Systems')
@section('meta_description', 'Configure external tool URLs for the Digital Board menus.')

@section('content')
@php
    // We prepare the tools by ensuring they have '_static_key' if they are static,
    // and injecting the current overrides from DB settings.
    $prepareTools = function($toolsArray) use ($settings) {
        return collect($toolsArray)->map(function($tool) use ($settings) {
            if (isset($tool['key'])) {
                $tool['_static_key'] = $tool['key'];
                $tool['label'] = $settings[$tool['key'] . '_label'] ?? ($tool['label'] ?? '');
                $tool['url']   = $settings[$tool['key']] ?? ($tool['url'] ?? '');
                $tool['icon_url'] = $settings[$tool['key'] . '_icon'] ?? ($tool['icon_url'] ?? '');
            } else {
                $tool['custom_id'] = $tool['custom_id'] ?? ('custom_' . Str::random(9));
            }
            return $tool;
        })->values();
    };

    $boardTools     = $prepareTools(\App\Models\Setting::externalToolsForGroup('board'));
    $generatorTools = $prepareTools(\App\Models\Setting::externalToolsForGroup('generator'));
    $workspaceTools = $prepareTools(\App\Models\Setting::externalToolsForGroup('workspace'));
    $aiTools        = $prepareTools(\App\Models\Setting::externalToolsForGroup('ai'));
@endphp

<div class="max-w-7xl mx-auto px-0 sm:px-4 lg:px-8 py-2 sm:py-4 pb-28 md:pb-12 animate-fade-in w-full space-y-8">

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

    </div>
  </div>

  {{-- ── Form ─────────────────────────────────────────────────────────────── --}}
  <form action="{{ route('admin.settings.store') }}" method="POST" enctype="multipart/form-data" class="space-y-8" id="external-systems-form">
        @csrf

    {{-- ── Company Branding & Login Header Section ──────────────────────── --}}
    <div class="card p-6 sm:p-8 bg-gradient-to-br from-slate-900 via-sky-950/80 to-slate-900 border border-sky-500/30 shadow-2xl rounded-2xl relative overflow-hidden"
         x-data="{
             companyName: '{{ addslashes($settings['company_name'] ?? 'KiuQ Digital Media') }}',
             companyTagline: '{{ addslashes($settings['company_tagline'] ?? 'Digital Media System') }}',
             companyLogoUrl: '{{ addslashes($settings['company_logo_url'] ?? asset('images/kiuqlogo.webp')) }}',
             previewLogo(event) {
                 const file = event.target.files[0];
                 if (file) {
                     const reader = new FileReader();
                     reader.onload = (e) => { this.companyLogoUrl = e.target.result; };
                     reader.readAsDataURL(file);
                 }
             },
             resetToDefault() {
                 this.companyLogoUrl = '{{ asset('images/kiuqlogo.webp') }}';
                 this.$refs.logoFileInput.value = '';
                 this.$refs.logoUrlInput.value = '{{ asset('images/kiuqlogo.webp') }}';
                 this.$refs.resetInput.value = '1';
             }
         }">
      <input type="hidden" name="reset_company_logo" x-ref="resetInput" value="0">

      {{-- Header with badge --}}
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-sky-400/20 pb-5 mb-6">
        <div class="flex items-center gap-4">
          <div class="h-12 w-12 rounded-xl bg-sky-500/20 border border-sky-400/40 flex items-center justify-center text-sky-400 shadow-[0_0_15px_rgba(14,165,233,0.3)]">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.75a1.5 1.5 0 0 1 1.5-1.5h1.5a1.5 1.5 0 0 1 1.5 1.5V21m-6-13.5h.75m-.75 3h.75m-.75 3h.75m6-6h.75m-.75 3h.75m-.75 3h.75" />
            </svg>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-xl font-black text-white font-display">Company Branding & Login Header</h2>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-400/30">
                Mr. Dara QC & Superadmin
              </span>
            </div>
            <p class="text-sm text-sky-200/80 mt-0.5">Customize the company name, logo image, and tagline displayed on the login page top-left and header.</p>
          </div>
        </div>

        <button type="button" @click="resetToDefault()" class="self-start sm:self-auto text-xs px-3.5 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 text-sky-200 border border-sky-400/20 hover:border-sky-400/40 transition flex items-center gap-1.5 cursor-pointer">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Reset to Default
        </button>
      </div>

      {{-- Grid layout --}}
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {{-- Inputs column --}}
        <div class="lg:col-span-7 space-y-5">
          {{-- Company Name Input --}}
          <div>
            <label class="block text-xs font-black uppercase tracking-wider text-sky-200 mb-2">
              Company Name <span class="text-cyan-400">*</span>
            </label>
            <div class="relative">
              <input type="text"
                     name="company_name"
                     x-model="companyName"
                     value="{{ old('company_name', $settings['company_name'] ?? 'KiuQ Digital Media') }}"
                     class="w-full rounded-xl bg-slate-950/60 border border-sky-400/30 px-4 py-3 text-sm font-semibold text-white placeholder-sky-400/40 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20 transition outline-none"
                     placeholder="e.g. KiuQ Digital Media"
                     required>
            </div>
            <p class="text-xs text-sky-300/60 mt-1.5">Displayed prominently as the main company title in the top-left of the login page.</p>
          </div>

          {{-- Company Tagline / Subtitle Input --}}
          <div>
            <label class="block text-xs font-black uppercase tracking-wider text-sky-200 mb-2">
              Company Tagline / Subtitle
            </label>
            <div class="relative">
              <input type="text"
                     name="company_tagline"
                     x-model="companyTagline"
                     value="{{ old('company_tagline', $settings['company_tagline'] ?? 'Digital Media System') }}"
                     class="w-full rounded-xl bg-slate-950/60 border border-sky-400/30 px-4 py-3 text-sm font-semibold text-white placeholder-sky-400/40 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20 transition outline-none"
                     placeholder="e.g. Digital Media System">
            </div>
            <p class="text-xs text-sky-300/60 mt-1.5">Small subtitle tag shown under company name (e.g., Digital Media System).</p>
          </div>

          {{-- Logo Upload & URL --}}
          <div class="space-y-3 pt-2">
            <label class="block text-xs font-black uppercase tracking-wider text-sky-200">
              Company Logo
            </label>
            
            <div class="flex flex-col sm:flex-row gap-3">
              {{-- File upload --}}
              <div class="flex-1">
                <label class="flex flex-col items-center justify-center border-2 border-dashed border-sky-400/30 hover:border-cyan-400/60 rounded-xl p-3 bg-slate-950/40 hover:bg-slate-900/50 cursor-pointer transition text-center group">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-sky-400 group-hover:text-cyan-300 mb-1 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                  </svg>
                  <span class="text-xs font-bold text-sky-200 group-hover:text-white transition">Upload New Logo</span>
                  <span class="text-[10px] text-sky-300/60 mt-0.5">PNG, WebP, SVG, JPG (Max 5MB)</span>
                  <input type="file"
                         name="company_logo_file"
                         x-ref="logoFileInput"
                         @change="previewLogo($event)"
                         accept="image/png,image/webp,image/svg+xml,image/jpeg"
                         class="hidden">
                </label>
              </div>

              {{-- Or direct URL --}}
              <div class="flex-1 flex flex-col justify-end">
                <span class="text-[11px] font-semibold text-sky-300/80 mb-1">Or Logo Image URL:</span>
                <input type="text"
                       name="company_logo_url"
                       x-ref="logoUrlInput"
                       x-model="companyLogoUrl"
                       value="{{ old('company_logo_url', $settings['company_logo_url'] ?? asset('images/kiuqlogo.webp')) }}"
                       class="w-full rounded-xl bg-slate-950/60 border border-sky-400/30 px-3 py-2 text-xs font-mono text-white placeholder-sky-400/40 focus:border-cyan-400 transition outline-none"
                       placeholder="https://... or /images/kiuqlogo.webp">
              </div>
            </div>
          </div>
        </div>

        {{-- Live Preview Column --}}
        <div class="lg:col-span-5">
          <label class="block text-xs font-black uppercase tracking-wider text-sky-200 mb-2">
            Live Preview (Login Page Top-Left)
          </label>
          <div class="relative rounded-2xl overflow-hidden border border-sky-400/30 p-6 bg-gradient-to-br from-[#07142e] via-[#091b48] to-[#02040a] shadow-inner min-h-[170px] flex flex-col justify-center">
            {{-- Background decorative grid & glow --}}
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_30%,rgba(56,189,248,0.2),transparent_60%)] pointer-events-none"></div>

            <div class="relative z-10 flex items-center gap-3">
              <template x-if="companyLogoUrl">
                <img :src="companyLogoUrl" :alt="companyName" class="h-10 w-auto max-w-[140px] object-contain drop-shadow-md">
              </template>
              <template x-if="!companyLogoUrl">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-500/20 border border-sky-400/40 text-sky-400 shadow-[0_0_15px_rgba(14,165,233,0.3)]">
                  <span class="font-black text-sm" x-text="companyName.charAt(0) || 'K'"></span>
                </div>
              </template>
              <div class="flex flex-col">
                <span class="font-display text-base font-extrabold tracking-wide text-white drop-shadow-sm" x-text="companyName || 'Company Name'"></span>
                <span class="text-[10px] font-bold tracking-wider uppercase text-cyan-200/80" x-text="companyTagline"></span>
              </div>
            </div>

            <div class="mt-4 pt-3 border-t border-sky-500/15 flex items-center justify-between text-[11px] text-sky-300/60">
              <span>Preview Badge Mode</span>
              <span class="text-emerald-400 font-semibold flex items-center gap-1">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Active on /login
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <hr class="border-slate-100 dark:border-slate-700/60">

    @include('admin.settings._dynamic_section', [
      'title' => 'Support System and Content',
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