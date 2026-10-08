@extends('layouts.app')

@section('title', 'Manage Classes - Social Media')
@section('back_url', route('social-media.dashboard'))

@section('content')
<style>
/* Custom animations & refinements for Social Media Manage */
.smm-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
[data-theme="dark"] .smm-card {
    background: #0f172a;
    border-color: #1e293b;
}
[data-theme="neon"] .smm-card {
    background: rgba(4, 20, 56, 0.92);
    border-color: rgba(0, 160, 255, 0.35);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45);
}

.smm-item-row {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    transition: all 0.18s ease;
}
.smm-item-row:hover {
    border-color: #c7d2fe;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.08);
}
[data-theme="dark"] .smm-item-row {
    background: #131d33;
    border-color: #1e293b;
}
[data-theme="dark"] .smm-item-row:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
}
[data-theme="neon"] .smm-item-row {
    background: rgba(7, 28, 76, 0.7);
    border-color: rgba(0, 160, 255, 0.2);
}
[data-theme="neon"] .smm-item-row:hover {
    border-color: rgba(0, 210, 255, 0.6);
    box-shadow: 0 0 16px rgba(0, 160, 255, 0.25);
}

/* Custom switch toggle */
.smm-toggle-track {
    width: 2.75rem;
    height: 1.5rem;
    border-radius: 9999px;
    transition: background-color 0.2s ease;
    display: inline-flex;
    align-items: center;
    padding: 0.125rem;
    cursor: pointer;
}
.smm-toggle-thumb {
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 9999px;
    background: #ffffff;
    transition: transform 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.25);
}

/* Glass modals */
.smm-modal-backdrop {
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}
</style>

<div class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-8 py-3 sm:py-6 pb-32 md:pb-16" x-data="{
    showCreateModal: false,
    showRolesModal: false,
    searchQuery: '',
    filterClass: '',
    activeFilterStatus: 'all',

    /* ── Delete confirm modal ── */
    deleteModal: false,
    deleteAction: '',
    deleteLabel: '',
    confirmDelete(action, label) {
        this.deleteAction = action;
        this.deleteLabel  = label;
        this.deleteModal  = true;
    },
    submitDelete() {
        this.$refs.deleteForm.action = this.deleteAction;
        this.$refs.deleteForm.submit();
    }
}">

    {{-- Hidden delete form (shared by all delete buttons) --}}
    <form x-ref="deleteForm" method="POST" style="display:none;">
        @csrf
        @method('DELETE')
    </form>

    {{-- ── Pretty Delete Confirm Modal ──────────────────────────────────── --}}
    <div x-show="deleteModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[80] flex items-center justify-center px-4 smm-modal-backdrop"
         x-cloak style="display:none;">
        <div class="absolute inset-0" @click="deleteModal = false"></div>
        <div x-show="deleteModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-3"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-3"
             class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden z-10">
            <div class="h-1.5 w-full bg-gradient-to-r from-rose-500 via-red-500 to-amber-500"></div>
            <div class="p-6 sm:p-8">
                <div class="flex items-center justify-center w-14 h-14 rounded-2xl bg-rose-500/10 text-rose-500 ring-8 ring-rose-500/5 mx-auto mb-4">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                </div>
                <h3 class="text-center font-bold text-slate-800 dark:text-white text-xl mb-1">Confirm Deletion</h3>
                <p class="text-center text-xs sm:text-sm text-slate-500 dark:text-slate-400 mb-3">Are you sure you want to permanently delete:</p>
                <div class="text-center font-semibold text-slate-700 dark:text-slate-200 text-sm bg-slate-50 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 rounded-xl px-4 py-2.5 mb-4 break-words" x-text="deleteLabel"></div>
                <p class="text-center text-xs text-rose-500 dark:text-rose-400 font-semibold mb-6 flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>This action cannot be undone.</span>
                </p>
                <div class="flex gap-3">
                    <button type="button" @click="deleteModal = false"
                        class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 transition-all duration-200 active:scale-95">
                        Cancel
                    </button>
                    <button type="button" @click="submitDelete()"
                        class="flex-1 px-4 py-2.5 rounded-xl text-sm font-bold bg-rose-600 hover:bg-rose-500 text-white shadow-lg shadow-rose-600/30 transition-all duration-200 active:scale-95">
                        Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Modern Page Header ─────────────────────────────────────────── --}}
    <div class="mb-6 sm:mb-8">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            {{-- Title & Stats --}}
            <div>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-sky-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/25 flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75L2.25 12l4.179 2.25m0-4.5l5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0l4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0l-5.571 3-5.571-3" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl sm:text-3xl font-black text-slate-800 dark:text-white tracking-tight">Manage Classes</h1>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
                                {{ $classes->count() }} {{ Str::plural('Class', $classes->count()) }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                {{ $classes->sum('items_count') }} Channels
                            </span>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">Configure brand clusters, social media platform channels, and member assignments.</p>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-wrap items-center gap-2.5 self-start lg:self-auto">
                <button @click="showRolesModal = true"
                    class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/70 shadow-xs active:scale-95 transition-all">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.199l-.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.999-3.199a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                    <span>Team Roles</span>
                </button>

                <button @click="showCreateModal = true"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-gradient-to-r from-indigo-600 via-indigo-500 to-sky-500 hover:from-indigo-500 hover:to-sky-400 text-white shadow-lg shadow-indigo-500/25 active:scale-95 transition-all">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Create Class</span>
                </button>

                <a href="{{ route('social-media.dashboard') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl text-xs sm:text-sm font-semibold bg-slate-100 hover:bg-slate-200 text-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 border border-transparent transition-all">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    <span>Back</span>
                </a>
            </div>
        </div>

        {{-- ── Search & Filter Toolbar ──────────────────────────────────── --}}
        <div class="mt-5 p-2.5 sm:p-3 bg-white/70 dark:bg-slate-800/60 backdrop-blur-md rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs flex flex-col md:flex-row items-stretch md:items-center gap-2.5">
            {{-- Search Bar --}}
            <div class="relative flex-1">
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input type="text" x-model="searchQuery" placeholder="Search classes by name..."
                    class="w-full pl-10 pr-9 py-2 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all">
                <button type="button" x-show="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5 rounded-md">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Quick Filter Select --}}
            <div class="flex items-center gap-2">
                <div class="relative flex-1 md:w-56">
                    <select x-model="filterClass"
                        class="w-full py-2 pl-3 pr-8 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 appearance-none transition-all">
                        <option value="">All Classes ({{ $classes->count() }})</option>
                        @foreach($classes as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->items_count }})</option>
                        @endforeach
                    </select>
                    <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-5 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-300 flex items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                </div>
                <span class="text-xs sm:text-sm font-medium">{{ session('success') }}</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-5 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-300 flex items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <span class="text-xs sm:text-sm font-medium">{{ session('error') }}</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700 dark:hover:text-rose-200"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
    @endif

    {{-- ── Class Cards List ───────────────────────────────────────────── --}}
    <div class="space-y-6">
        @forelse($classes as $class)
        <div class="smm-card rounded-3xl overflow-hidden shadow-xs"
             x-data="{
                 activeTab: 'platforms', // 'platforms' | 'members' | 'edit'
                 showAddPlatform: false,
                 newPlatformName: '',
                 newPlatformUrl: '',
                 newPlatformIcon: '',
                 selectMemberToAdd: '',
                 setPreset(name, icon, urlPlaceholder) {
                     this.newPlatformName = name;
                     this.newPlatformIcon = icon;
                     this.newPlatformUrl = urlPlaceholder || '';
                 }
             }"
             x-show="(filterClass === '' || filterClass === '{{ $class->id }}') && ('{{ strtolower(addslashes($class->name)) }}'.includes(searchQuery.toLowerCase()))">

            {{-- ── Class Header ── --}}
            <div class="p-5 sm:p-6 border-b border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                {{-- Left: Class Identity --}}
                <div class="flex items-center gap-3.5 min-w-0">
                    {{-- Class Icon / Monogram --}}
                    @if($class->icon)
                        <img src="{{ asset(ltrim($class->icon, '/')) }}" alt="{{ $class->name }}"
                             class="w-12 h-12 rounded-2xl object-cover border border-slate-200/80 dark:border-slate-700 shadow-xs flex-shrink-0">
                    @else
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 via-indigo-600 to-sky-600 text-white font-extrabold text-lg flex items-center justify-center shadow-md shadow-indigo-500/20 flex-shrink-0 select-none">
                            {{ strtoupper(substr($class->name, 0, 2)) }}
                        </div>
                    @endif

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-extrabold text-lg sm:text-xl text-slate-800 dark:text-white truncate tracking-tight">
                                {{ $class->name }}
                            </h3>

                            {{-- Active/Inactive status badge --}}
                            @if($class->status === 'active')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    ACTIVE
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                    INACTIVE
                                </span>
                            @endif
                        </div>

                        {{-- Sub-row: Platform count, Member count chip, description snippet --}}
                        <div class="flex flex-wrap items-center gap-3 mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <span class="inline-flex items-center gap-1 font-semibold text-slate-600 dark:text-slate-300">
                                <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                                {{ $class->items->count() }} {{ Str::plural('Platform', $class->items->count()) }}
                            </span>
                            <span class="text-slate-300 dark:text-slate-700">•</span>
                            {{-- Member avatar stack preview --}}
                            <div class="inline-flex items-center gap-1.5 font-medium">
                                <div class="flex -space-x-1.5 overflow-hidden py-0.5">
                                    @forelse($class->assignedUsers->take(4) as $assignee)
                                        <img src="{{ $assignee->avatar_url }}" alt="{{ $assignee->name }}" title="{{ $assignee->name }}"
                                             class="inline-block w-5 h-5 rounded-full ring-2 ring-white dark:ring-slate-900 object-cover">
                                    @empty
                                    @endforelse
                                </div>
                                <span>{{ $class->assignedUsers->count() }} {{ Str::plural('Member', $class->assignedUsers->count()) }}</span>
                            </div>

                            @if($class->description)
                                <span class="text-slate-300 dark:text-slate-700 hidden sm:inline">•</span>
                                <span class="hidden sm:inline truncate max-w-xs text-slate-400 dark:text-slate-500" title="{{ $class->description }}">{{ $class->description }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Right: Segmented Navigation & Action Buttons --}}
                <div class="flex flex-wrap items-center gap-2 self-start md:self-auto">
                    {{-- Tab Switcher Pills --}}
                    <div class="inline-flex p-1 rounded-xl bg-slate-200/70 dark:bg-slate-800 border border-slate-200/50 dark:border-slate-700/60">
                        <button type="button" @click="activeTab = 'platforms'"
                            :class="activeTab === 'platforms' ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 font-semibold'"
                            class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                            <span>Platforms ({{ $class->items->count() }})</span>
                        </button>

                        <button type="button" @click="activeTab = 'members'"
                            :class="activeTab === 'members' ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 font-semibold'"
                            class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                            <span>Members ({{ $class->assignedUsers->count() }})</span>
                        </button>

                        <button type="button" @click="activeTab = 'edit'"
                            :class="activeTab === 'edit' ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 font-semibold'"
                            class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                            <span>Settings</span>
                        </button>
                    </div>

                    {{-- Quick + Add Platform button --}}
                    <button type="button" @click="showAddPlatform = !showAddPlatform; activeTab = 'platforms'"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800/60 hover:bg-indigo-600 hover:text-white dark:hover:bg-indigo-600 dark:hover:text-white transition-all shadow-xs active:scale-95">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        <span>Add Platform</span>
                    </button>

                    {{-- Delete Class Button --}}
                    <button type="button"
                        @click="confirmDelete('{{ route('social-media.classes.destroy', $class) }}', 'Class: {{ addslashes($class->name) }} (and all associated platforms)')"
                        class="p-2 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 border border-transparent hover:border-rose-200 dark:hover:border-rose-800/50 transition-all active:scale-95"
                        title="Delete this class">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                    </button>
                </div>
            </div>

            {{-- ═════════════════════════════════════════════════════════════════ --}}
            {{-- TAB 1: PLATFORMS VIEW                                            --}}
            {{-- ═════════════════════════════════════════════════════════════════ --}}
            <div x-show="activeTab === 'platforms'" class="p-5 sm:p-6 space-y-4">

                {{-- ── Add Platform Drawer ─────────────────────────────────── --}}
                <div x-show="showAddPlatform" x-transition x-cloak class="p-5 rounded-2xl bg-indigo-50/60 dark:bg-slate-800/80 border border-indigo-100 dark:border-indigo-900/50 shadow-inner">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                            <h4 class="text-sm font-bold text-slate-800 dark:text-white">Add New Social Platform Channel</h4>
                        </div>
                        <button type="button" @click="showAddPlatform = false" class="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            Dismiss
                        </button>
                    </div>

                    {{-- Quick Platform Presets --}}
                    <div class="mb-4">
                        <label class="text-[11px] font-bold text-indigo-900 dark:text-indigo-300 uppercase tracking-wider block mb-2">⚡ Quick Presets (Click to autofill):</label>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" @click="setPreset('Facebook', '/images/social/facebook.svg', 'https://facebook.com/')"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-400 shadow-xs flex items-center gap-1.5 transition-all">
                                📘 Facebook
                            </button>
                            <button type="button" @click="setPreset('Instagram', '/images/social/instagram.svg', 'https://instagram.com/')"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:border-pink-400 hover:text-pink-600 dark:hover:text-pink-400 shadow-xs flex items-center gap-1.5 transition-all">
                                📸 Instagram
                            </button>
                            <button type="button" @click="setPreset('TikTok', '/images/social/tiktok.png', 'https://tiktok.com/@')"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:border-slate-400 shadow-xs flex items-center gap-1.5 transition-all">
                                🎵 TikTok
                            </button>
                            <button type="button" @click="setPreset('YouTube', '/images/social/youtube.svg', 'https://youtube.com/@')"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:border-red-400 hover:text-red-600 dark:hover:text-red-400 shadow-xs flex items-center gap-1.5 transition-all">
                                ▶️ YouTube
                            </button>
                            <button type="button" @click="setPreset('Pinterest', '/images/social/pinterest.png', 'https://pinterest.com/')"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:border-red-400 hover:text-red-600 dark:hover:text-red-400 shadow-xs flex items-center gap-1.5 transition-all">
                                📌 Pinterest
                            </button>
                            <button type="button" @click="setPreset('X(Twitter)', '/images/social/x.svg', 'https://x.com/')"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:border-slate-400 shadow-xs flex items-center gap-1.5 transition-all">
                                𝕏 X(Twitter)
                            </button>
                            <button type="button" @click="setPreset('Tumblr', 'https://cdn-icons-png.flaticon.com/512/1409/1409942.png', 'https://tumblr.com/')"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:border-blue-400 shadow-xs flex items-center gap-1.5 transition-all">
                                📝 Tumblr
                            </button>
                            <button type="button" @click="setPreset('LinkedIn', 'https://cdn-icons-png.flaticon.com/512/3536/3536505.png', 'https://linkedin.com/company/')"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:border-sky-400 shadow-xs flex items-center gap-1.5 transition-all">
                                💼 LinkedIn
                            </button>
                            <button type="button" @click="setPreset('Telegram', 'https://cdn-icons-png.flaticon.com/512/2111/2111646.png', 'https://t.me/')"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:border-sky-400 shadow-xs flex items-center gap-1.5 transition-all">
                                ✈️ Telegram
                            </button>
                        </div>
                    </div>

                    <form action="{{ route('social-media.items.store', $class) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                            {{-- Platform Name --}}
                            <div>
                                <label class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider block mb-1">Platform Name <span class="text-rose-500">*</span></label>
                                <input type="text" name="name" x-model="newPlatformName" placeholder="e.g. Facebook" required
                                    class="w-full px-3 py-2 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                            </div>

                            {{-- URL link --}}
                            <div>
                                <label class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider block mb-1">Channel / Page URL</label>
                                <input type="url" name="url" x-model="newPlatformUrl" placeholder="https://..."
                                    class="w-full px-3 py-2 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                            </div>

                            {{-- Icon URL or upload --}}
                            <div>
                                <label class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider block mb-1">Icon (URL or Upload)</label>
                                <div class="flex items-center gap-2">
                                    <input type="text" name="icon_url" x-model="newPlatformIcon" placeholder="https://... or emoji"
                                        class="flex-1 min-w-0 px-3 py-2 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                                    <div class="flex-shrink-0">
                                        @include('social-media._file-picker', ['name' => 'icon_file', 'accept' => 'image/*', 'placeholder' => 'Upload'])
                                    </div>
                                </div>
                            </div>

                            {{-- Submit button --}}
                            <div class="flex items-center gap-2">
                                <input type="hidden" name="status" value="active">
                                <button type="submit"
                                    class="flex-1 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/20 active:scale-95 transition-all text-center">
                                    Add Channel
                                </button>
                                <button type="button" @click="showAddPlatform = false"
                                    class="px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600 transition-all">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- ── Platforms Items List ─────────────────────────────────── --}}
                @if($class->items->isEmpty())
                    <div class="text-center py-10 px-4 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h4 class="font-bold text-slate-700 dark:text-slate-200 text-sm">No social media platforms added yet</h4>
                        <p class="text-xs text-slate-400 dark:text-slate-500 max-w-sm mx-auto mt-1 mb-4">Start by adding default platforms or creating your own custom platform channels.</p>
                        <div class="flex flex-wrap items-center justify-center gap-2.5">
                            <form action="{{ route('social-media.items.store-template', $class) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/20 active:scale-95 transition-all flex items-center gap-1.5">
                                    <span>⚡ Load 7 Default Platforms</span>
                                </button>
                            </form>
                            <button type="button" @click="showAddPlatform = true" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition-all">
                                <span>+ Add Custom Platform</span>
                            </button>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-2.5">
                        @foreach($class->items as $item)
                        <div x-data="{ editing: false }" class="smm-item-row rounded-2xl p-3.5 sm:p-4">
                            {{-- Normal Row View --}}
                            <div x-show="!editing" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                {{-- Left: Icon & Info --}}
                                <div class="flex items-center gap-3.5 min-w-0">
                                    {{-- Brand Squircle Icon --}}
                                    <div class="w-10 h-10 rounded-xl bg-slate-50 dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 flex items-center justify-center p-2 shadow-xs flex-shrink-0">
                                        {!! $item->icon_html !!}
                                    </div>

                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-sm text-slate-800 dark:text-white truncate">{{ $item->name }}</span>

                                            {{-- Status Chip --}}
                                            @if($item->status === 'active')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50">
                                                    ACTIVE
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-slate-700">
                                                    INACTIVE
                                                </span>
                                            @endif
                                        </div>

                                        {{-- URL display --}}
                                        <div class="mt-0.5">
                                            @if($item->url)
                                                <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer"
                                                    class="inline-flex items-center gap-1 text-xs text-indigo-500 hover:text-indigo-600 dark:text-indigo-400 hover:underline truncate max-w-xs sm:max-w-md">
                                                    <span>{{ $item->url }}</span>
                                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                                </a>
                                            @else
                                                <span class="text-xs text-slate-400 dark:text-slate-500 italic">No link configured</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Right: Status Switch & Action Buttons --}}
                                <div class="flex items-center gap-2 self-end sm:self-auto">
                                    {{-- Interactive Switch Toggle (replaces the clunky text button) --}}
                                    <form action="{{ route('social-media.items.toggle', $item) }}" method="POST" class="inline-flex items-center">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-semibold border transition-all active:scale-95 {{ $item->status === 'active' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800/60 hover:bg-emerald-100' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700 hover:bg-slate-200' }}"
                                            title="Click to {{ $item->status === 'active' ? 'Disable' : 'Enable' }}">
                                            <span class="w-2 h-2 rounded-full {{ $item->status === 'active' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                            <span>{{ $item->status === 'active' ? 'Enabled' : 'Disabled' }}</span>
                                        </button>
                                    </form>

                                    {{-- Edit Button --}}
                                    <button @click="editing = true" type="button"
                                        class="p-2 rounded-xl text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 dark:text-slate-400 dark:hover:text-indigo-300 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition-all active:scale-95"
                                        title="Edit platform details">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                    </button>

                                    {{-- Delete Button (Sleek red ghost) --}}
                                    <button type="button"
                                        @click="confirmDelete('{{ route('social-media.items.destroy', $item) }}', 'Platform: {{ addslashes($item->name) }} (from {{ addslashes($class->name) }})')"
                                        class="p-2 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 border border-transparent hover:border-rose-200 dark:hover:border-rose-800/50 transition-all active:scale-95"
                                        title="Delete platform">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Inline Edit Form --}}
                            <form x-show="editing" x-cloak action="{{ route('social-media.items.update', $item) }}" method="POST" enctype="multipart/form-data"
                                class="mt-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3">
                                @csrf @method('PUT')
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                                    <div>
                                        <label class="text-[11px] font-bold text-slate-500 uppercase block mb-1">Name</label>
                                        <input type="text" name="name" value="{{ $item->name }}" required
                                            class="w-full px-3 py-1.5 text-xs sm:text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-white">
                                    </div>
                                    <div>
                                        <label class="text-[11px] font-bold text-slate-500 uppercase block mb-1">Channel URL</label>
                                        <input type="url" name="url" value="{{ $item->url }}" placeholder="https://..."
                                            class="w-full px-3 py-1.5 text-xs sm:text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-white">
                                    </div>
                                    <div>
                                        <label class="text-[11px] font-bold text-slate-500 uppercase block mb-1">Icon (URL/Upload)</label>
                                        <div class="flex items-center gap-1.5">
                                            <input type="text" name="icon_url" value="{{ $item->icon }}" placeholder="https://..."
                                                class="flex-1 min-w-0 px-3 py-1.5 text-xs sm:text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-white">
                                            @include('social-media._file-picker', ['name' => 'icon_file', 'accept' => 'image/*', 'placeholder' => 'Icon'])
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="w-28">
                                            <label class="text-[11px] font-bold text-slate-500 uppercase block mb-1">Status</label>
                                            <select name="status" class="w-full px-2 py-1.5 text-xs sm:text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-white">
                                                <option value="active" {{ $item->status === 'active' ? 'selected' : '' }}>Active</option>
                                                <option value="inactive" {{ $item->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                            </select>
                                        </div>
                                        <div class="flex-1 flex gap-1.5 pt-4">
                                            <button type="submit" class="flex-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white">Save</button>
                                            <button type="button" @click="editing = false" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ═════════════════════════════════════════════════════════════════ --}}
            {{-- TAB 2: ASSIGNED MEMBERS MANAGEMENT (Resolving Screenshot 3)     --}}
            {{-- ═════════════════════════════════════════════════════════════════ --}}
            <div x-show="activeTab === 'members'" class="p-5 sm:p-6 space-y-5" x-cloak>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h4 class="font-bold text-slate-800 dark:text-white text-base flex items-center gap-2">
                            <span>Assigned Digital Team Members</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800/50">
                                {{ $class->assignedUsers->count() }} active
                            </span>
                        </h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Assigned members have access to manage and track posts for this class.</p>
                    </div>
                </div>

                {{-- ── Quick Add / Search Member Section ── --}}
                @php
                    $assignedIds = $class->assignedUsers->pluck('id')->all();
                    $unassignedUsers = $allUsers->reject(fn($u) => in_array($u->id, $assignedIds));
                @endphp

                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80">
                    <form action="{{ route('social-media.classes.assign', $class) }}" method="POST" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        @csrf
                        <div class="relative flex-1">
                            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z" />
                            </svg>
                            <select name="user_ids[]" required
                                class="w-full pl-10 pr-9 py-2.5 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 appearance-none">
                                <option value="">-- Choose team member to assign --</option>
                                @foreach($unassignedUsers as $u)
                                    @php
                                        $role = $u->roles->first(fn($r) => strpos($r->name, 'social_') === 0)?->name;
                                        $roleLabel = match($role) {
                                            'social_admin' => ' (Social Admin)',
                                            'social_qc' => ' (Social QC)',
                                            default => ''
                                        };
                                    @endphp
                                    <option value="{{ $u->id }}">{{ $u->name }}{{ $roleLabel }}</option>
                                @endforeach
                            </select>
                            <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>

                        <button type="submit"
                            @if($unassignedUsers->isEmpty()) disabled @endif
                            class="px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white shadow-md shadow-indigo-600/20 active:scale-95 transition-all flex items-center justify-center gap-1.5 flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            <span>Assign to Class</span>
                        </button>
                    </form>

                    {{-- Quick 1-click unassigned member pill suggestion --}}
                    @if($unassignedUsers->isNotEmpty())
                        <div class="mt-3 pt-3 border-t border-slate-200/50 dark:border-slate-700/50 flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mr-1">Quick Add:</span>
                            @foreach($unassignedUsers->take(6) as $u)
                                <form action="{{ route('social-media.classes.assign', $class) }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="user_ids[]" value="{{ $u->id }}">
                                    <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-white dark:bg-slate-700/70 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600 hover:border-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-300 transition-all shadow-xs">
                                        <img src="{{ $u->avatar_url }}" alt="" class="w-4 h-4 rounded-full object-cover">
                                        <span>{{ $u->name }}</span>
                                        <span class="text-indigo-500 font-bold">+</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ── Assigned Members Grid ── --}}
                @if($class->assignedUsers->isEmpty())
                    <div class="text-center py-8 text-slate-400 dark:text-slate-500 text-xs italic bg-slate-50/50 dark:bg-slate-900/40 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800">
                        No team members currently assigned. Select a member above to assign them.
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($class->assignedUsers as $u)
                            @php
                                $role = $u->roles->first(fn($r) => strpos($r->name, 'social_') === 0)?->name;
                            @endphp
                            <div class="flex items-center justify-between gap-3 p-3 rounded-2xl bg-white dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 shadow-xs hover:border-indigo-200 dark:hover:border-indigo-800 transition-all group">
                                <div class="flex items-center gap-3 min-w-0">
                                    <img src="{{ $u->avatar_url }}" alt="{{ $u->name }}"
                                         class="w-10 h-10 rounded-full object-cover ring-2 ring-indigo-500/20 flex-shrink-0">
                                    <div class="min-w-0">
                                        <div class="font-bold text-xs sm:text-sm text-slate-800 dark:text-white truncate">
                                            {{ $u->name }}
                                        </div>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            @if($role === 'social_admin')
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                                                    Admin
                                                </span>
                                            @elseif($role === 'social_qc')
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">
                                                    QC
                                                </span>
                                            @else
                                                <span class="text-[10px] text-slate-400 dark:text-slate-500">Member</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Remove Member Button --}}
                                <button type="button"
                                    @click="confirmDelete('{{ route('social-media.classes.remove-user', [$class, $u]) }}', 'Remove {{ addslashes($u->name) }} from {{ addslashes($class->name) }}?')"
                                    class="p-1.5 rounded-lg text-slate-300 dark:text-slate-600 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-all flex-shrink-0"
                                    title="Remove from class">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ═════════════════════════════════════════════════════════════════ --}}
            {{-- TAB 3: EDIT CLASS SETTINGS                                       --}}
            {{-- ═════════════════════════════════════════════════════════════════ --}}
            <div x-show="activeTab === 'edit'" class="p-5 sm:p-6" x-cloak>
                <div class="mb-4 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h4 class="font-bold text-slate-800 dark:text-white text-base">Class Details & Settings</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Update class identification, brand logo icon, and visibility status.</p>
                </div>

                <form action="{{ route('social-media.classes.update', $class) }}" method="POST" enctype="multipart/form-data" class="space-y-4 max-w-3xl">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Class Name --}}
                        <div>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-200 block mb-1">Class Name <span class="text-rose-500">*</span></label>
                            <input type="text" name="name" value="{{ $class->name }}" required
                                class="w-full px-3.5 py-2 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                        </div>

                        {{-- Status --}}
                        <div>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-200 block mb-1">Status</label>
                            <select name="status" class="w-full px-3 py-2 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                                <option value="active" {{ $class->status === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $class->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>

                    {{-- Icon URL or upload --}}
                    <div>
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-200 block mb-1">Brand Icon / Logo</label>
                        <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                            <input type="text" name="icon_url" value="{{ $class->icon }}" placeholder="https://... or image link"
                                class="flex-1 px-3.5 py-2 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                            <div class="flex-shrink-0">
                                @include('social-media._file-picker', ['name' => 'icon_file', 'accept' => 'image/*', 'placeholder' => 'Upload image…'])
                            </div>
                        </div>
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-200 block mb-1">Description</label>
                        <textarea name="description" rows="2" placeholder="Brief notes about this brand or client cluster..."
                            class="w-full px-3.5 py-2 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 resize-none">{{ $class->description }}</textarea>
                    </div>

                    {{-- Buttons --}}
                    <div class="flex items-center gap-2 pt-2">
                        <button type="submit" class="px-5 py-2 rounded-xl text-xs sm:text-sm font-bold bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/20 active:scale-95 transition-all">
                            Save Changes
                        </button>
                        <button type="button" @click="activeTab = 'platforms'" class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-all">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @empty
            <div class="text-center py-16 px-4 rounded-3xl bg-white dark:bg-slate-900 border-2 border-dashed border-slate-200 dark:border-slate-800">
                <div class="w-16 h-16 rounded-3xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-500 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">No classes created yet</h3>
                <p class="text-xs sm:text-sm text-slate-400 dark:text-slate-500 max-w-sm mx-auto mt-1 mb-6">Create your first class brand cluster to begin managing social media channels and schedules.</p>
                <button @click="showCreateModal = true" class="px-5 py-2.5 rounded-xl text-sm font-bold bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/25 active:scale-95 transition-all">
                    + Create First Class
                </button>
            </div>
        @endforelse
    </div>

    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL 1: CREATE NEW CLASS (Resolving Screenshot 1)                    --}}
    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    <div x-show="showCreateModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-4 smm-modal-backdrop overflow-y-auto"
         x-cloak style="display: none;">
        <div class="fixed inset-0" @click="showCreateModal = false"></div>

        <div x-show="showCreateModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-3"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-3"
             class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden z-10 my-8"
             x-data="{
                 createMemberSearch: '',
                 previewIconUrl: '',
                 useTemplate: '1',
                 selectAllMembers() {
                     this.$el.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = true);
                 },
                 deselectAllMembers() {
                     this.$el.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = false);
                 }
             }">

            {{-- Top Accent Line --}}
            <div class="h-1.5 w-full bg-gradient-to-r from-indigo-500 via-sky-500 to-emerald-500"></div>

            {{-- Modal Header --}}
            <div class="px-6 py-4 sm:px-8 sm:py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/50 dark:bg-slate-900/60">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-800 dark:text-white text-lg tracking-tight">Create New Class</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Define a brand cluster and assign initial platform channels.</p>
                    </div>
                </div>
                <button type="button" @click="showCreateModal = false" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Modal Form --}}
            <form method="POST" action="{{ route('social-media.classes.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="p-6 sm:p-8 space-y-5 max-h-[75vh] overflow-y-auto">
                    {{-- Row 1: Name & Icon --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider block mb-1.5">
                                Class Name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="name" required placeholder="e.g. Acme Corporation"
                                class="w-full px-3.5 py-2.5 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider block mb-1.5">
                                Icon (URL or Upload)
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="url" name="icon_url" x-model="previewIconUrl" placeholder="https://..."
                                    class="flex-1 min-w-0 px-3.5 py-2.5 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                                <div class="flex-shrink-0">
                                    @include('social-media._file-picker', ['name' => 'icon_file', 'accept' => 'image/*', 'placeholder' => 'Upload'])
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider block mb-1.5">
                            Description (Optional)
                        </label>
                        <textarea name="description" rows="2" placeholder="Brief details about this brand cluster..."
                            class="w-full px-3.5 py-2 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 resize-none"></textarea>
                    </div>

                    {{-- Row 2: Status & Template Selection --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Status --}}
                        <div>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider block mb-1.5">Status</label>
                            <select name="status"
                                class="w-full px-3.5 py-2.5 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        {{-- Initial Setup Template --}}
                        <div>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider block mb-1.5">Initial Setup</label>
                            <select name="use_template" x-model="useTemplate"
                                class="w-full px-3.5 py-2.5 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                                <option value="1" selected>Template (7 default platforms)</option>
                                <option value="0">None (Empty Class)</option>
                            </select>
                        </div>
                    </div>

                    {{-- Template preview banner --}}
                    <div x-show="useTemplate === '1'" class="p-3 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/50 flex items-center gap-3">
                        <span class="text-base flex-shrink-0">⚡</span>
                        <div class="text-xs text-indigo-900 dark:text-indigo-200">
                            <span class="font-bold">Includes 7 standard platforms:</span> Facebook, Instagram, X(Twitter), Pinterest, YouTube, TikTok, Tumblr.
                        </div>
                    </div>

                    {{-- Assign Members Multi-select --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">
                                Assign Members (Optional)
                            </label>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="selectAllMembers()" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">Select All</button>
                                <span class="text-slate-300 dark:text-slate-700">•</span>
                                <button type="button" @click="deselectAllMembers()" class="text-[11px] font-bold text-slate-400 hover:underline">Clear</button>
                            </div>
                        </div>

                        {{-- Search member filter --}}
                        <div class="relative mb-2">
                            <svg class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" x-model="createMemberSearch" placeholder="Filter team members by name..."
                                class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-white placeholder-slate-400">
                        </div>

                        {{-- Scrollable member cards --}}
                        <div class="max-h-52 overflow-y-auto border border-slate-200 dark:border-slate-700 rounded-2xl p-2 bg-slate-50/50 dark:bg-slate-900/40 space-y-1">
                            @foreach($allUsers as $u)
                                @php
                                    $role = $u->roles->first(fn($r) => strpos($r->name, 'social_') === 0)?->name;
                                @endphp
                                <label x-show="'{{ strtolower(addslashes($u->name)) }}'.includes(createMemberSearch.toLowerCase())"
                                    class="flex items-center justify-between p-2.5 rounded-xl hover:bg-white dark:hover:bg-slate-800 cursor-pointer transition-colors border border-transparent hover:border-slate-200 dark:hover:border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" name="user_ids[]" value="{{ $u->id }}"
                                            class="member-checkbox rounded-md border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                        <img src="{{ $u->avatar_url }}" alt="{{ $u->name }}" class="w-7 h-7 rounded-full object-cover">
                                        <div class="min-w-0">
                                            <span class="text-xs sm:text-sm font-semibold text-slate-800 dark:text-slate-100 block">{{ $u->name }}</span>
                                        </div>
                                    </div>
                                    @if($role === 'social_admin')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">Admin</span>
                                    @elseif($role === 'social_qc')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">QC</span>
                                    @endif
                                </label>
                            @endforeach
                            @if($allUsers->isEmpty())
                                <div class="p-3 text-xs text-slate-400 italic text-center">No active digital team members available.</div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="px-6 py-4 sm:px-8 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/60 flex items-center justify-end gap-3">
                    <button type="button" @click="showCreateModal = false"
                        class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-all">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-6 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-gradient-to-r from-indigo-600 to-sky-500 hover:from-indigo-500 hover:to-sky-400 text-white shadow-lg shadow-indigo-600/25 active:scale-95 transition-all">
                        Create Class
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL 2: DIGITAL TEAM ROLES                                           --}}
    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    <div x-show="showRolesModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-4 smm-modal-backdrop overflow-y-auto"
         x-cloak style="display: none;">
        <div class="fixed inset-0" @click="showRolesModal = false"></div>

        <div x-show="showRolesModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-3"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-3"
             class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden z-10 my-8 flex flex-col max-h-[85vh]"
             x-data="{ rolesUserSearch: '' }">

            <div class="h-1.5 w-full bg-gradient-to-r from-indigo-500 via-sky-500 to-indigo-600"></div>

            {{-- Header --}}
            <div class="px-6 py-4 sm:px-8 sm:py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/50 dark:bg-slate-900/60 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.199l-.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.999-3.199a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-800 dark:text-white text-lg tracking-tight">Digital Team Roles</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Assign module roles (Social Admin, Social QC) to Digital Team members.</p>
                    </div>
                </div>
                <button type="button" @click="showRolesModal = false" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Filter Bar --}}
            <div class="px-6 py-2.5 sm:px-8 border-b border-slate-100 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-900/30">
                <div class="relative">
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="rolesUserSearch" placeholder="Search members by name..."
                        class="w-full pl-9 pr-3 py-1.5 text-xs bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-white placeholder-slate-400">
                </div>
            </div>

            {{-- Roster Form --}}
            <form action="{{ route('social-media.users.roles.bulk') }}" method="POST" class="flex flex-col flex-1 overflow-hidden">
                @csrf
                <div class="overflow-y-auto flex-1 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($allUsers as $user)
                        @php
                            $currentSocialRole = $user->roles->first(fn($r) => strpos($r->name, 'social_') === 0)?->name ?? 'none';
                        @endphp
                        <div x-show="'{{ strtolower(addslashes($user->name)) }}'.includes(rolesUserSearch.toLowerCase())"
                            class="p-4 sm:px-8 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/70 dark:hover:bg-slate-800/50 transition-colors">
                            <div class="flex items-center gap-3.5">
                                <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-10 h-10 rounded-full object-cover ring-2 ring-indigo-500/20">
                                <div>
                                    <div class="font-bold text-sm text-slate-800 dark:text-white">{{ $user->name }}</div>
                                    <div class="text-xs text-slate-400 dark:text-slate-500">
                                        @if($currentSocialRole === 'social_admin')
                                            <span class="text-indigo-600 dark:text-indigo-400 font-semibold">Social Admin</span>
                                        @elseif($currentSocialRole === 'social_qc')
                                            <span class="text-blue-600 dark:text-blue-400 font-semibold">Social QC</span>
                                        @else
                                            <span class="text-slate-400">Standard Member</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <select name="roles[{{ $user->id }}]"
                                class="w-full sm:w-44 py-1.5 px-3 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/30">
                                <option value="none" {{ $currentSocialRole === 'none' ? 'selected' : '' }}>None (Standard)</option>
                                <option value="social_admin" {{ $currentSocialRole === 'social_admin' ? 'selected' : '' }}>Social Admin</option>
                                <option value="social_qc" {{ $currentSocialRole === 'social_qc' ? 'selected' : '' }}>Social QC</option>
                            </select>
                        </div>
                    @endforeach

                    @if($allUsers->isEmpty())
                        <div class="p-8 text-center text-slate-400 italic text-sm">
                            No active digital team members found.
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="px-6 py-4 sm:px-8 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/60 shrink-0 flex justify-end gap-3">
                    <button type="button" @click="showRolesModal = false" class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-all">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2 rounded-xl text-xs sm:text-sm font-bold bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/20 active:scale-95 transition-all">
                        Save All Roles
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
