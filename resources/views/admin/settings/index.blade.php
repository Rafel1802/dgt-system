@extends('layouts.app')

@section('title', 'External Systems')
@section('page_title', 'External Systems')
@section('meta_description', 'Configure external tool URLs for the Digital Board menus.')

@section('content')
@php
    $tools = collect($tools ?? []);
    $boardTools = $tools->where('group', 'board')->values();
    $generatorTools = $tools->where('group', 'generator')->values();
    $workspaceTools = $tools->where('group', 'workspace')->values();
    $aiTools = $tools->where('group', 'ai')->values();
@endphp

<div class="external-systems-page max-w-5xl animate-fade-in space-y-6">
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700 shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="external-systems-shell overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm">
        <div class="external-systems-hero border-b border-slate-100 bg-gradient-to-r from-blue-50 via-white to-sky-50 px-6 py-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-wider text-[#2F68ED]">Admin controls</p>
                    <h1 class="mt-1 font-display text-2xl font-black text-slate-950">External system links</h1>
                    <p class="mt-2 max-w-2xl text-sm font-semibold leading-6 text-slate-500">
                        These seven URLs power the Digital Dashboard buttons, the board toolbar links, and the sidebar shortcuts below Boards.
                    </p>
                </div>
                <a href="{{ route('dashboard') }}" class="btn btn-secondary w-fit">
                    Back to dashboard
                </a>
            </div>
        </div>

        <form action="{{ route('admin.settings.store') }}" method="POST" class="p-6">
            @csrf

            <div class="grid gap-6 lg:grid-cols-[1fr_1fr]">
                <section class="space-y-4">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">eBay &amp; Web Supporter</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-400">Image hosting, backup server, and eBay template systems.</p>
                    </div>

                    @foreach($boardTools as $tool)
                        <label for="{{ $tool['key'] }}" class="external-systems-tool-card block rounded-2xl border border-slate-200 bg-slate-50/70 p-4 transition hover:border-blue-200 hover:bg-white hover:shadow-sm">
                            <span class="flex items-center gap-3">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-[#2F68ED]">
                                    @if(isset($settings[$tool['key'] . '_icon']) && $settings[$tool['key'] . '_icon'])
                                        <img src="{{ $settings[$tool['key'] . '_icon'] }}" class="h-6 w-6 object-contain" alt="">
                                    @else
                                        <x-external-tool-icon :name="$tool['icon']" />
                                    @endif
                                </span>
                                <span>
                                    <span class="block text-sm font-black text-slate-800">{{ $tool['label'] }}</span>
                                    <span class="block text-xs font-semibold text-slate-500">{{ $tool['description'] }}</span>
                                </span>
                            </span>

                            <input
                                id="{{ $tool['key'] }}"
                                type="url"
                                name="{{ $tool['key'] }}"
                                value="{{ old($tool['key'], $settings[$tool['key']] ?? $tool['url']) }}"
                                placeholder="https://your-system.example.com"
                                class="form-input mt-3 w-full"
                            >
                            @error($tool['key'])
                                <span class="mt-1 block text-sm font-bold text-rose-600">{{ $message }}</span>
                            @enderror

                            <input
                                type="url"
                                name="{{ $tool['key'] }}_icon"
                                value="{{ old($tool['key'] . '_icon', $settings[$tool['key'] . '_icon'] ?? $tool['icon_url'] ?? '') }}"
                                placeholder="Custom Icon URL (Optional)"
                                class="form-input mt-2 w-full text-xs bg-white"
                            >
                            @error($tool['key'] . '_icon')
                                <span class="mt-1 block text-sm font-bold text-rose-600">{{ $message }}</span>
                            @enderror
                        </label>
                    @endforeach
                </section>

                <section class="space-y-4">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">System Supporter</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-400">Prompt, selling point, thumbnail, spec, and approval workflows.</p>
                    </div>

                    @foreach($generatorTools as $tool)
                        <label for="{{ $tool['key'] }}" class="external-systems-tool-card block rounded-2xl border border-slate-200 bg-slate-50/70 p-4 transition hover:border-blue-200 hover:bg-white hover:shadow-sm">
                            <span class="flex items-center gap-3">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-[#2F68ED]">
                                    @if(isset($settings[$tool['key'] . '_icon']) && $settings[$tool['key'] . '_icon'])
                                        <img src="{{ $settings[$tool['key'] . '_icon'] }}" class="h-6 w-6 object-contain" alt="">
                                    @else
                                        <x-external-tool-icon :name="$tool['icon']" />
                                    @endif
                                </span>
                                <span>
                                    <span class="block text-sm font-black text-slate-800">{{ $tool['label'] }}</span>
                                    <span class="block text-xs font-semibold text-slate-500">{{ $tool['description'] }}</span>
                                </span>
                            </span>

                            <input
                                id="{{ $tool['key'] }}"
                                type="url"
                                name="{{ $tool['key'] }}"
                                value="{{ old($tool['key'], $settings[$tool['key']] ?? $tool['url']) }}"
                                placeholder="https://your-system.example.com"
                                class="form-input mt-3 w-full"
                            >
                            @error($tool['key'])
                                <span class="mt-1 block text-sm font-bold text-rose-600">{{ $message }}</span>
                            @enderror

                            <input
                                type="url"
                                name="{{ $tool['key'] }}_icon"
                                value="{{ old($tool['key'] . '_icon', $settings[$tool['key'] . '_icon'] ?? $tool['icon_url'] ?? '') }}"
                                placeholder="Custom Icon URL (Optional)"
                                class="form-input mt-2 w-full text-xs bg-white"
                            >
                            @error($tool['key'] . '_icon')
                                <span class="mt-1 block text-sm font-bold text-rose-600">{{ $message }}</span>
                            @enderror
                        </label>
                    @endforeach
                </section>

                <section class="space-y-4 lg:col-span-2">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Google Workspace</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-400">Configure external links for Google Workspace integration.</p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        @foreach($workspaceTools as $tool)
                            <label for="{{ $tool['key'] }}" class="external-systems-tool-card block rounded-2xl border border-slate-200 bg-slate-50/70 p-4 transition hover:border-blue-200 hover:bg-white hover:shadow-sm">
                                <span class="flex items-center gap-3">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-[#2F68ED]">
                                        @if(isset($settings[$tool['key'] . '_icon']) && $settings[$tool['key'] . '_icon'])
                                            <img src="{{ $settings[$tool['key'] . '_icon'] }}" class="h-6 w-6 object-contain" alt="">
                                        @else
                                            <x-external-tool-icon :name="$tool['icon']" />
                                        @endif
                                    </span>
                                    <span>
                                        <span class="block text-sm font-black text-slate-800">{{ $tool['label'] }}</span>
                                        <span class="block text-xs font-semibold text-slate-500">{{ $tool['description'] }}</span>
                                    </span>
                                </span>

                                <input
                                    id="{{ $tool['key'] }}"
                                    type="url"
                                    name="{{ $tool['key'] }}"
                                    value="{{ old($tool['key'], $settings[$tool['key']] ?? $tool['url']) }}"
                                    placeholder="https://docs.google.com/a/domain.com"
                                    class="form-input mt-3 w-full"
                                >
                                @error($tool['key'])
                                    <span class="mt-1 block text-sm font-bold text-rose-600">{{ $message }}</span>
                                @enderror

                                <input
                                    type="url"
                                    name="{{ $tool['key'] }}_icon"
                                    value="{{ old($tool['key'] . '_icon', $settings[$tool['key'] . '_icon'] ?? $tool['icon_url'] ?? '') }}"
                                    placeholder="Custom Icon URL (Optional)"
                                    class="form-input mt-2 w-full text-xs bg-white"
                                >
                                @error($tool['key'] . '_icon')
                                    <span class="mt-1 block text-sm font-bold text-rose-600">{{ $message }}</span>
                                @enderror
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="space-y-4 lg:col-span-2 mt-4" x-data="customAiToolsEditor()">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">AI Tools Collapsible Menu</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-400">Configure external AI tools that appear under the collapsible AI Tools sidebar menu.</p>
                    </div>

                    <!-- Single combined grid for both static slots and dynamic additions -->
                    <div class="grid gap-6 sm:grid-cols-2 mt-4">
                        <!-- Default 5 slots to preserve user data -->
                        @foreach($aiTools as $tool)
                            <div class="external-systems-tool-card block rounded-2xl border border-slate-200 bg-slate-50/70 p-4 transition hover:border-blue-200 hover:bg-white hover:shadow-sm">
                                <span class="flex items-center gap-3">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-[#2F68ED]">
                                        @if(isset($settings[$tool['key'] . '_icon']) && $settings[$tool['key'] . '_icon'])
                                            <img src="{{ $settings[$tool['key'] . '_icon'] }}" class="h-6 w-6 object-contain" alt="">
                                        @else
                                            <x-external-tool-icon :name="$tool['icon']" />
                                        @endif
                                    </span>
                                    <span>
                                        <span class="block text-sm font-black text-slate-800">{{ $tool['label'] }}</span>
                                        <span class="block text-xs font-semibold text-slate-500">{{ $tool['description'] }}</span>
                                    </span>
                                </span>

                                <input
                                    id="{{ $tool['key'] }}"
                                    type="url"
                                    name="{{ $tool['key'] }}"
                                    value="{{ old($tool['key'], $settings[$tool['key']] ?? $tool['url']) }}"
                                    placeholder="https://chatgpt.com"
                                    class="form-input mt-3 w-full"
                                >
                                @error($tool['key'])
                                    <span class="mt-1 block text-sm font-bold text-rose-600">{{ $message }}</span>
                                @enderror

                                <input
                                    type="text"
                                    name="{{ $tool['key'] }}_label"
                                    value="{{ old($tool['key'] . '_label', $settings[$tool['key'] . '_label'] ?? '') }}"
                                    placeholder="Custom Label (e.g. ChatGPT)"
                                    class="form-input mt-2 w-full text-xs bg-white"
                                >
                                @error($tool['key'] . '_label')
                                    <span class="mt-1 block text-sm font-bold text-rose-600">{{ $message }}</span>
                                @enderror

                                <input
                                    type="url"
                                    name="{{ $tool['key'] }}_icon"
                                    value="{{ old($tool['key'] . '_icon', $settings[$tool['key'] . '_icon'] ?? $tool['icon_url'] ?? '') }}"
                                    placeholder="Custom Icon URL (Optional)"
                                    class="form-input mt-2 w-full text-xs bg-white"
                                >
                                @error($tool['key'] . '_icon')
                                    <span class="mt-1 block text-sm font-bold text-rose-600">{{ $message }}</span>
                                @enderror
                            </div>
                        @endforeach

                        <!-- Dynamic additions inside the same grid -->
                        <template x-for="(tool, index) in tools" :key="index">
                            <div class="external-systems-tool-card relative block rounded-2xl border border-slate-200 bg-slate-50/70 p-4 transition hover:border-blue-200 hover:bg-white hover:shadow-sm">
                                <button type="button" @click="removeTool(index)" class="absolute top-4 right-4 text-slate-400 hover:text-rose-600 transition" title="Remove Tool">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </button>

                                <span class="flex items-center gap-3">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-[#2F68ED]">
                                        <template x-if="tool.icon_url">
                                            <img :src="tool.icon_url" class="h-6 w-6 object-contain" alt="">
                                        </template>
                                        <template x-if="!tool.icon_url">
                                            <x-external-tool-icon name="sparkles" />
                                        </template>
                                    </span>
                                    <span>
                                        <span class="block text-sm font-black text-slate-800" x-text="tool.label || 'Additional AI Tool'"></span>
                                        <span class="block text-xs font-semibold text-slate-500">Configure custom AI tool link</span>
                                    </span>
                                </span>

                                <input
                                    type="url"
                                    :name="'custom_ai_tools['+index+'][url]'"
                                    x-model="tool.url"
                                    placeholder="https://your-system.example.com"
                                    class="form-input mt-3 w-full"
                                    required
                                >

                                <input
                                    type="text"
                                    :name="'custom_ai_tools['+index+'][label]'"
                                    x-model="tool.label"
                                    placeholder="Custom Label (e.g. ChatGPT)"
                                    class="form-input mt-2 w-full text-xs bg-white"
                                    required
                                >

                                <input
                                    type="url"
                                    :name="'custom_ai_tools['+index+'][icon_url]'"
                                    x-model="tool.icon_url"
                                    placeholder="Custom Icon URL (Optional)"
                                    class="form-input mt-2 w-full text-xs bg-white"
                                >
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="addTool()" class="btn btn-secondary flex items-center gap-2 w-full justify-center py-2.5 mt-4">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add More AI Tool
                    </button>
                </section>

                <script>
                    function customAiToolsEditor() {
                        return {
                            tools: @json(json_decode($settings['custom_ai_tools'] ?? '[]', true)),
                            addTool() {
                                this.tools.push({ label: '', url: '', icon_url: '' });
                            },
                            removeTool(index) {
                                this.tools.splice(index, 1);
                            }
                        };
                    }
                </script>
            </div>

            <div class="mt-6 flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs font-semibold text-slate-500">
                    Leave a field empty to hide that shortcut from non-admin users.
                </p>
                <button type="submit" class="btn btn-primary justify-center">
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.59 3.32c1.1.13 1.91 1.08 1.91 2.19V21L12 17.25 4.5 21V5.51c0-1.11.81-2.06 1.91-2.19a48.5 48.5 0 0 1 11.18 0Z" />
                    </svg>
                    Save external links
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
