@extends('layouts.app')
@section('title', 'All Websites')
@section('page_title', 'All Websites')

@section('content')
<style>
    .image-modal {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .85);
        backdrop-filter: blur(8px);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10040;
        animation: fadeIn .2s ease;
        touch-action: none;
        overflow: hidden;
    }

    .image-modal img {
        max-width: 90vw;
        max-height: 90vh;
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .45);
        user-select: none;
        -webkit-user-drag: none;
        transform-origin: center center;
        will-change: transform;
        transition: transform .08s ease-out;
    }

    .close-image {
        position: fixed;
        top: 20px;
        right: 20px;
        width: 44px;
        height: 44px;
        border: none;
        border-radius: 50%;
        background: #fff;
        color: #000;
        font-size: 22px;
        cursor: pointer;
        box-shadow: 0 8px 20px rgba(0, 0, 0, .3);
        z-index: 10050;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: transform .15s ease;
    }

    .close-image:hover {
        transform: scale(1.08);
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @media (max-width: 768px) {
        .image-modal img {
            max-width: 95vw;
            max-height: 80vh;
        }

        .close-image {
            top: 12px;
            right: 12px;
        }
    }
</style>
<div class="animate-fade-in w-full pb-28 md:pb-8" x-data="websitesApp()" x-init="init()">

{{-- ── Flash Messages ──────────────────────────────────────────────────────── --}}
@if(session('success'))
<div class="mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 text-sm text-emerald-800">
    <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    <span class="font-semibold">{{ session('success') }}</span>
</div>
@endif

{{-- ── Hero Banner ─────────────────────────────────────────────────────────── --}}
<div class="mb-6 overflow-hidden rounded-2xl relative" style="background: linear-gradient(135deg, #1e3a5f 0%, #1d4ed8 50%, #4f46e5 100%);">
    <div class="absolute inset-0 opacity-10" style="background-image: url(\"data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23fff' fill-opacity='1' fill-rule='evenodd'%3E%3Cpath d='M0 40L40 0H20L0 20M40 40V20L20 40'/%3E%3C/g%3E%3C/svg%3E\");"></div>
    <div class="relative p-6 flex flex-col sm:flex-row sm:items-center gap-5">
        <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-2xl bg-white/20 shadow-inner ring-1 ring-white/30 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-white">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253M3 12a8.959 8.959 0 0 0 .284 2.253" />
            </svg>
        </div>
        <div class="flex-1">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-200 mb-1">Website Management</p>
            <h1 class="font-display text-2xl font-black text-white sm:text-3xl">All Websites</h1>
            <p class="mt-1 text-sm text-blue-100">Manage build, progress, live status, maintenance, and follow-ups for all websites.</p>
        </div>
        <div class="flex-shrink-0 flex flex-wrap gap-3 sm:flex-col sm:items-end">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="text-white/90 text-xs font-semibold">{{ $stats['live'] }} Live</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-blue-300"></span>
                <span class="text-white/90 text-xs font-semibold">{{ $stats['building'] }} Building</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                <span class="text-white/90 text-xs font-semibold">{{ $stats['maintenance'] }} Maintenance</span>
            </div>
        </div>
    </div>
</div>


{{-- ── Tab Navigation ──────────────────────────────────────────────────────── --}}
@if($tab !== 'follow-up')
<div class="flex items-center justify-end mb-4">
    <div class="flex items-center gap-2 w-full sm:w-auto">
        <div class="relative flex-1 sm:flex-none">
            <input type="text" x-model="searchQuery" placeholder="Search websites..." class="form-input text-xs py-1.5 pl-8 pr-3 rounded-lg w-full sm:w-56 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition-all">
            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
        </div>
        <select x-model="filterMember" class="form-select text-xs py-1.5 px-3 rounded-lg w-full sm:w-48 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition-all ml-2 bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
            <option value="">All Members</option>
            @php
                $memberUserIds = $websiteMembers->pluck('user_id')->unique()->toArray();
                $activeUsers = $users->filter(fn($u) => in_array($u->id, $memberUserIds));
            @endphp
            @foreach($activeUsers as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
        </select>
        
        {{-- Export button --}}
        <button type="button" @click="showExportModal = true"
           class="btn btn-primary px-3 py-1.5 text-sm flex-shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            <span class="hidden sm:inline">Export Report</span>
            <span class="sm:hidden">Export</span>
        </button>
    </div>
</div>

<div class="mb-6 flex items-center gap-2 overflow-x-auto hide-scrollbar border-b border-slate-200 dark:border-slate-700 pb-0 -mb-px">
    @php
        $tabs = [
            'build'          => ['label' => 'Build Website',       'count' => $buildWebsites->count(),            'icon' => 'M12 4.5v15m7.5-7.5h-15',                                                                       'color' => 'slate'],
            'build-progress' => ['label' => 'Build Progress',      'count' => $buildProgressWebsites->count(),    'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z', 'color' => 'blue'],
            'live'           => ['label' => 'Live Websites',        'count' => $liveWebsites->count(),             'icon' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',                                'color' => 'emerald'],
            'maintenance'    => ['label' => 'Update / Maintenance', 'count' => $maintenanceWebsites->count(),      'icon' => 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654', 'color' => 'orange'],
            'qc-error'       => ['label' => 'QC Error',             'count' => $qcErrorWebsites->count(),          'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z', 'color' => 'red'],
            'supervisor-error'=> ['label' => 'Supervisor Error',    'count' => $supervisorErrorWebsites->count(),  'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z', 'color' => 'rose'],
        ];
        $colorMap = [
            'slate'   => ['active' => 'border-slate-600 text-slate-700 dark:text-slate-200',   'dot' => 'bg-slate-500'],
            'blue'    => ['active' => 'border-blue-600 text-blue-700 dark:text-blue-300',       'dot' => 'bg-blue-500'],
            'emerald' => ['active' => 'border-emerald-600 text-emerald-700 dark:text-emerald-300', 'dot' => 'bg-emerald-500'],
            'orange'  => ['active' => 'border-orange-600 text-orange-700 dark:text-orange-300', 'dot' => 'bg-orange-500'],
            'red'     => ['active' => 'border-red-600 text-red-700 dark:text-red-300',         'dot' => 'bg-red-500'],
            'rose'    => ['active' => 'border-rose-600 text-rose-700 dark:text-rose-300',       'dot' => 'bg-rose-500'],
        ];
    @endphp
    @foreach($tabs as $key => $tabInfo)
    @php $isActive = $tab === $key; $c = $colorMap[$tabInfo['color']]; @endphp
    <a href="{{ route('websites.index', ['tab' => $key]) }}"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold border-b-2 transition-all duration-150 -mb-px rounded-t-lg whitespace-nowrap flex-shrink-0
              {{ $isActive ? $c['active'] . ' bg-white dark:bg-slate-800/50' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300' }}">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tabInfo['icon'] }}" />
        </svg>
        {{ $tabInfo['label'] }}
        @if($tabInfo['count'] > 0)
        <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs font-bold {{ $isActive ? 'bg-current/10' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400' }}">{{ $tabInfo['count'] }}</span>
        @endif
    </a>
    @endforeach
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════
     TAB 1: BUILD WEBSITE
════════════════════════════════════════════════════════════════ --}}
@if($tab === 'build')
<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Build Website</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">New website projects waiting to start.</p>
        </div>
        @if(auth()->user()->canUpdateWebsiteProgress())
        <div class="flex items-center gap-2">
            @if(auth()->user()->hasAnyRole(['super-admin','admin-digital']))
            <button type="button" @click="showManageMembersModal = true" class="btn btn-secondary flex items-center gap-2 text-sm">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                Manage Members
            </button>
            @endif
            <button type="button" @click="showManageClassesModal = true" class="btn btn-secondary flex items-center gap-2 text-sm">
                <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
                Manage Classes
            </button>
            @if(!auth()->user()->isWebsiteViewer() && !auth()->user()->hasRole('boss'))
            <button type="button" @click="showCreateModal = true"
                    class="btn btn-primary flex items-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Add Website
            </button>
            @endif
        </div>
        @endif
    </div>

    @if($buildWebsites->isEmpty())
    <div class="card border border-dashed border-slate-200 dark:border-slate-700 p-16 text-center">
        <div class="text-5xl mb-4">🌐</div>
        <h3 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-2">No websites in Build stage</h3>
        <p class="text-slate-500 dark:text-slate-400 text-sm mb-5">Click "Add Website" to create a new website project.</p>
    </div>
    @else
    <div>
            @php
        $groups = [];
        foreach ($orderArray as $cat) {
            $groups[$cat] = $buildWebsites->where('category', $cat);
        }
        $groups['Uncategorized'] = $buildWebsites->whereNull('category');
        $realCats = array_values($orderArray);
    @endphp
    @foreach($groups as $groupName => $groupWebsites)
        @if($groupWebsites->isNotEmpty())
        @php
            $catIndex = array_search($groupName, $realCats);
            $isRealCat = ($catIndex !== false);
        @endphp
        <div class="mb-8" x-show="hasMatchingWebsites({{ json_encode($groupWebsites->map(fn($w) => ['name' => $w->name, 'url' => $w->url, 'handled_by' => $w->handled_by])->values()) }})">
            <h3 @click="toggleGroup('build-{{ addslashes($groupName) }}')" class="font-bold text-slate-700 dark:text-slate-200 mb-4 flex items-center gap-2 cursor-pointer select-none hover:text-indigo-600 transition-colors">
                <svg class="w-4 h-4 text-slate-400 transform transition-transform duration-200 flex-shrink-0" :class="isGroupCollapsed('build-{{ addslashes($groupName) }}') ? '-rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 flex-shrink-0"></span>
                <span class="truncate">{{ $groupName }}</span>
                <span class="text-xs font-normal text-slate-400">({{ $groupWebsites->count() }})</span>
                @if($isRealCat && auth()->user()->hasAnyRole(['super-admin','admin-digital']))
                <div class="inline-flex items-center gap-1 ml-2" @click.stop>
                    @if($catIndex > 0)
                    <form action="{{ route('websites.reorderCategory') }}" method="POST" class="inline">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="category" value="{{ $groupName }}">
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" class="p-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 rounded transition-colors" title="Move Up">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" /></svg>
                        </button>
                    </form>
                    @endif
                    @if($catIndex < count($realCats) - 1)
                    <form action="{{ route('websites.reorderCategory') }}" method="POST" class="inline">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="category" value="{{ $groupName }}">
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" class="p-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 rounded transition-colors" title="Move Down">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                    </form>
                    @endif
                </div>
                @endif
            </h3>
            <div x-show="!isGroupCollapsed('build-{{ addslashes($groupName) }}')" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($groupWebsites as $website)
        <div class="card flex flex-col w-full border border-slate-200 dark:border-slate-700 hover:shadow-lg transition-all duration-200 overflow-hidden" x-show="matchesSearch('{{ addslashes($website->name) }}', '{{ addslashes($website->url) }}', '{{ $website->handled_by }}')">
            <div class="h-1 w-full bg-gradient-to-r from-slate-400 to-slate-600"></div>
            <div class="p-5 flex flex-col flex-1 w-full">
                <div class="flex items-start gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center flex-shrink-0">
                        @if($website->logo_path)
                            <img src="{{ $website->logo_src }}" alt="" class="w-8 h-8 object-contain rounded">
                        @else
                            <span class="text-xl">🌐</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $website->name }}</h3>
                        <a href="{{ $website->url }}" target="_blank" class="text-xs text-indigo-500 hover:text-indigo-700 truncate block">{{ $website->clean_domain }}</a>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 flex-shrink-0">{{ $website->status }}</span>
                </div>
                <div class="space-y-2 text-xs text-slate-500 dark:text-slate-400">
                    @if($website->handler)
                    <div class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                        {{ $website->handler->name }}
                    </div>
                    @endif
                    @if($website->deadline)
                    <div class="flex items-center gap-1.5 {{ $website->isOverdue() ? 'text-rose-500 font-semibold' : '' }}">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                        Deadline: {{ $website->deadline->format('d M Y') }}
                        @if($website->isOverdue()) <span class="text-rose-500">(Overdue)</span> @endif
                    </div>
                    @endif
                    @if($website->category)
                    <div class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
                        <a href="{{ route('websites.index', ['tab' => $tab, 'class' => $website->category]) }}" class="text-indigo-500 hover:text-indigo-700 font-semibold">{{ $website->category }}</a>
                    </div>
                    @endif
                    <div class="flex items-center gap-1.5 text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        Added {{ $website->created_at->diffForHumans() }}
                    </div>
                </div>
                <div class="mt-auto pt-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-center gap-2 w-full">
                    <form action="{{ route('websites.progress.update', $website) }}" method="POST" class="flex-1" data-confirm="Are you sure you want to start building {{ addslashes($website->name) }}?" data-confirm-title="Start Build Progress">
                        @csrf
                        <input type="hidden" name="percent" value="10">
                        <input type="hidden" name="note" value="Build Progress Started">
                        <button type="submit" class="btn btn-primary text-xs py-1.5 px-3 w-full">
                            Start Build Progress →
                        </button>
                    </form>
                    @if(auth()->user()->canUpdateWebsiteProgress())
                    <button type="button" @click="openEditModal({{ $website->id }}, {{ json_encode($website->only(['name', 'url', 'category', 'logo_url', 'handled_by', 'start_date', 'deadline', 'notes'])) }})"
                            class="btn btn-secondary text-xs py-1.5 px-2.5" title="Edit">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                    </button>
                    @endif
                    @if(auth()->user()->hasAnyRole(['super-admin','admin-digital']))
                    <form action="{{ route('websites.destroy', $website) }}" method="POST" data-confirm="Delete {{ addslashes($website->name) }}?" data-confirm-title="Delete Website">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-secondary text-xs py-1.5 px-2.5 text-rose-500 hover:text-white hover:bg-rose-500 hover:border-rose-500" title="Delete">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
                @endforeach
            </div>
        </div>
        @endif
    @endforeach
    </div>
    @endif
</div>
@endif

    {{-- ════════════════════════════════════════════════════════════════
     TAB 2: BUILD PROGRESS
════════════════════════════════════════════════════════════════ --}}
@if($tab === 'build-progress')
<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Build Progress</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Track website build progress from 0% to 100%, then QC before going Live.</p>
        </div>
    </div>

    @if($buildProgressWebsites->isEmpty())
    <div class="card border border-dashed border-slate-200 dark:border-slate-700 p-16 text-center">
        <div class="text-5xl mb-4">📊</div>
        <h3 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-2">No websites in progress</h3>
        <p class="text-slate-500 text-sm">Start a website from the <a href="{{ route('websites.index', ['tab' => 'build']) }}" class="text-indigo-500 font-semibold">Build Website</a> tab.</p>
    </div>
    @else
    <div>
            @php
        $groups = [];
        foreach ($orderArray as $cat) {
            $groups[$cat] = $buildProgressWebsites->where('category', $cat);
        }
        $groups['Uncategorized'] = $buildProgressWebsites->whereNull('category');
        $realCats = array_values($orderArray);
    @endphp
    @foreach($groups as $groupName => $groupWebsites)
        @if($groupWebsites->isNotEmpty())
        @php
            $catIndex = array_search($groupName, $realCats);
            $isRealCat = ($catIndex !== false);
        @endphp
        <div class="mb-8" x-show="hasMatchingWebsites({{ json_encode($groupWebsites->map(fn($w) => ['name' => $w->name, 'url' => $w->url, 'handled_by' => $w->handled_by])->values()) }})">
            <h3 @click="toggleGroup('progress-{{ addslashes($groupName) }}')" class="font-bold text-slate-700 dark:text-slate-200 mb-4 flex items-center gap-2 cursor-pointer select-none hover:text-indigo-600 transition-colors">
                <svg class="w-4 h-4 text-slate-400 transform transition-transform duration-200 flex-shrink-0" :class="isGroupCollapsed('progress-{{ addslashes($groupName) }}') ? '-rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 flex-shrink-0"></span>
                <span class="truncate">{{ $groupName }}</span>
                <span class="text-xs font-normal text-slate-400">({{ $groupWebsites->count() }})</span>
                @if($isRealCat && auth()->user()->hasAnyRole(['super-admin','admin-digital']))
                <div class="inline-flex items-center gap-1 ml-2" @click.stop>
                    @if($catIndex > 0)
                    <form action="{{ route('websites.reorderCategory') }}" method="POST" class="inline">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="category" value="{{ $groupName }}">
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" class="p-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 rounded transition-colors" title="Move Up">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" /></svg>
                        </button>
                    </form>
                    @endif
                    @if($catIndex < count($realCats) - 1)
                    <form action="{{ route('websites.reorderCategory') }}" method="POST" class="inline">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="category" value="{{ $groupName }}">
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" class="p-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 rounded transition-colors" title="Move Down">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                    </form>
                    @endif
                </div>
                @endif
            </h3>
            <div x-show="!isGroupCollapsed('progress-{{ addslashes($groupName) }}')" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($groupWebsites as $website)
        @php
            $pct = $website->progress_percent;
            $isQc = $website->status === \App\Models\Website::STATUS_QC_CHECKING;
            $isSupervisor = $website->status === \App\Models\Website::STATUS_SUPERVISOR_CHECKING;
            $progressColor = match(true) {
                $pct >= 100 => '#10b981',
                $pct >= 75  => '#3b82f6',
                $pct >= 50  => '#6366f1',
                $pct >= 25  => '#f59e0b',
                default     => '#94a3b8',
            };
        @endphp
        <div class="card flex flex-col w-full border {{ $isQc ? 'border-amber-300 dark:border-amber-700 shadow-amber-100 dark:shadow-amber-900/20 shadow-md' : ($isSupervisor ? 'border-cyan-300 dark:border-cyan-700 shadow-cyan-100 dark:shadow-cyan-900/20 shadow-md' : 'border-slate-200 dark:border-slate-700') }} overflow-hidden transition-all hover:shadow-lg" x-show="matchesSearch('{{ addslashes($website->name) }}', '{{ addslashes($website->url) }}', '{{ $website->handled_by }}')">
            <div class="h-1 w-full" style="background: linear-gradient(90deg, {{ $progressColor }}, {{ $progressColor }}88);"></div>
            <div class="p-5 flex flex-col flex-1 w-full">
                {{-- Header --}}
                <div class="flex items-start gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center flex-shrink-0">
                        @if($website->logo_path)
                            <img src="{{ $website->logo_src }}" alt="" class="w-8 h-8 object-contain rounded">
                        @else
                            <span class="text-xl">🌐</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $website->name }}</h3>
                            @if($isQc)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                QC Checking
                            </span>
                            @endif
                            @if($isSupervisor)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-cyan-100 text-cyan-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-cyan-500 animate-pulse"></span>
                                Supervisor Checking
                            </span>
                            @endif
                        </div>
                        <a href="{{ $website->url }}" target="_blank" class="text-xs text-indigo-500 hover:text-indigo-700 truncate block">{{ $website->clean_domain }}</a>
                    </div>
                </div>

                {{-- Progress Bar --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Build Progress</span>
                        <span class="text-sm font-black" style="color: {{ $progressColor }}">{{ $pct }}%</span>
                    </div>
                    <div class="h-3 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500" style="width: {{ $pct }}%; background: {{ $progressColor }};"></div>
                    </div>
                    <div class="relative w-full h-4 mt-1">
                        @foreach([0,10,25,50,75,100] as $step)
                        <span class="absolute text-[9px] font-semibold {{ $pct >= $step ? 'text-slate-500' : 'text-slate-300 dark:text-slate-600' }}"
                              style="left: {{ $step }}%; transform: {{ $step === 0 ? 'none' : ($step === 100 ? 'translateX(-100%)' : 'translateX(-50%)') }};">
                            {{ $step }}%
                        </span>
                        @endforeach
                    </div>
                </div>

                {{-- Last Progress Note --}}
                @if($website->progressLogs->isNotEmpty())
                @php $lastLog = $website->progressLogs->first(); @endphp
                <div class="mb-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg p-3 border border-slate-100 dark:border-slate-700">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1">Last Update</div>
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">{{ Str::limit($lastLog->note, 100) }}</p>
                    <p class="text-[10px] text-slate-400 mt-1">
                        {{ $lastLog->user?->name ?? 'Unknown' }} · {{ $lastLog->created_at->diffForHumans() }}
                    </p>
                </div>
                @endif

                {{-- Handler --}}
                @if($website->handler)
                <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 mb-4">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                    {{ $website->handler->name }}
                </div>
                @endif

                    {{-- Actions --}}
                <div class="mt-auto pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-center gap-2 w-full">
                    @if(!$isQc && !$isSupervisor)
                    <button type="button"
                            @click="openProgressModal({{ $website->id }}, '{{ addslashes($website->name) }}', {{ $pct }}, 'build')"
                            class="btn btn-primary text-xs py-1.5 px-3 flex-1">
                        Update Progress
                    </button>
                    @elseif($isQc)
                    @if(auth()->user()->canApproveWebsiteQc())
                    <button type="button"
                            @click="openQcModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                            class="btn text-xs py-1.5 px-3 flex-1 bg-amber-500 hover:bg-amber-600 text-white">
                        ✓ QC Approve
                    </button>
                    <button type="button"
                            @click="openQcErrorModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                            class="btn text-xs py-1.5 px-2.5 bg-red-500 hover:bg-red-600 text-white">
                        ✗ QC Error
                    </button>
                    @else
                    <span class="text-xs text-amber-600 dark:text-amber-400 font-semibold flex-1 text-center">Awaiting QC Approval</span>
                    @endif
                    @elseif($isSupervisor)
                    @if(auth()->user()->canApproveWebsiteSupervisor())
                    <button type="button"
                            @click="openSupervisorModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                            class="btn text-xs py-1.5 px-3 flex-1 bg-cyan-500 hover:bg-cyan-600 text-white">
                        ✓ Supervisor Approve
                    </button>
                    <button type="button"
                            @click="openSupervisorErrorModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                            class="btn text-xs py-1.5 px-2.5 bg-red-500 hover:bg-red-600 text-white">
                        ✗ Sup. Error
                    </button>
                    @else
                    <span class="text-xs text-cyan-600 dark:text-cyan-400 font-semibold flex-1 text-center">Awaiting Supervisor Approval</span>
                    @endif
                    @endif
                    <button type="button"
                            @click="openHistoryModal({{ $website->id }}, '{{ addslashes($website->name) }}', 'build', JSON.parse($event.currentTarget.dataset.logs))"
                            data-logs="{{ $website->serialized_logs }}"
                            class="btn btn-secondary text-xs py-1.5 px-2.5" title="View History">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </button>
                    @if(auth()->user()->canUpdateWebsiteProgress())
                    <button type="button" @click="openEditModal({{ $website->id }}, {{ json_encode($website->only(['name', 'url', 'category', 'logo_url', 'handled_by', 'start_date', 'deadline', 'notes'])) }})"
                            class="btn btn-secondary text-xs py-1.5 px-2.5" title="Edit">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                    </button>
                    @endif
                    @if(auth()->user()->hasAnyRole(['super-admin','admin-digital']))
                    <form action="{{ route('websites.destroy', $website) }}" method="POST" data-confirm="Delete {{ addslashes($website->name) }}?" data-confirm-title="Delete Website">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-secondary text-xs py-1.5 px-2.5 text-rose-500 hover:text-white hover:bg-rose-500 hover:border-rose-500" title="Delete">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
                @endforeach
            </div>
        </div>
        @endif
    @endforeach
    </div>
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════
     TAB 3: LIVE WEBSITES
════════════════════════════════════════════════════════════════ --}}
@if($tab === 'live')
<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Live Websites</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">All currently live websites. Maintenance websites are highlighted.</p>
        </div>
    </div>

    @if($liveWebsites->isEmpty())
    <div class="card border border-dashed border-slate-200 dark:border-slate-700 p-16 text-center">
        <div class="text-5xl mb-4">✅</div>
        <h3 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-2">No live websites yet</h3>
        <p class="text-slate-500 text-sm">Websites appear here after QC approval.</p>
    </div>
    @else
    <div>
            @php
        $groups = [];
        foreach ($orderArray as $cat) {
            $groups[$cat] = $liveWebsites->where('category', $cat);
        }
        $groups['Uncategorized'] = $liveWebsites->whereNull('category');
        $realCats = array_values($orderArray);
    @endphp
    @foreach($groups as $groupName => $groupWebsites)
        @if($groupWebsites->isNotEmpty())
        @php
            $catIndex = array_search($groupName, $realCats);
            $isRealCat = ($catIndex !== false);
        @endphp
        <div class="mb-8" x-show="hasMatchingWebsites({{ json_encode($groupWebsites->map(fn($w) => ['name' => $w->name, 'url' => $w->url, 'handled_by' => $w->handled_by])->values()) }})">
            <h3 @click="toggleGroup('live-{{ addslashes($groupName) }}')" class="font-bold text-slate-700 dark:text-slate-200 mb-4 flex items-center gap-2 cursor-pointer select-none hover:text-indigo-600 transition-colors">
                <svg class="w-4 h-4 text-slate-400 transform transition-transform duration-200 flex-shrink-0" :class="isGroupCollapsed('live-{{ addslashes($groupName) }}') ? '-rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 flex-shrink-0"></span>
                <span class="truncate">{{ $groupName }}</span>
                <span class="text-xs font-normal text-slate-400">({{ $groupWebsites->count() }})</span>
                @if($isRealCat && auth()->user()->hasAnyRole(['super-admin','admin-digital']))
                <div class="inline-flex items-center gap-1 ml-2" @click.stop>
                    @if($catIndex > 0)
                    <form action="{{ route('websites.reorderCategory') }}" method="POST" class="inline">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="category" value="{{ $groupName }}">
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" class="p-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 rounded transition-colors" title="Move Up">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" /></svg>
                        </button>
                    </form>
                    @endif
                    @if($catIndex < count($realCats) - 1)
                    <form action="{{ route('websites.reorderCategory') }}" method="POST" class="inline">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="category" value="{{ $groupName }}">
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" class="p-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 rounded transition-colors" title="Move Down">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                    </form>
                    @endif
                </div>
                @endif
            </h3>
            <div x-show="!isGroupCollapsed('live-{{ addslashes($groupName) }}')" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($groupWebsites as $website)
        @php $isUnderMaintenance = $website->isMaintenance(); @endphp
        <div class="card flex flex-col w-full border {{ $isUnderMaintenance ? 'border-orange-300 dark:border-orange-500/40 bg-orange-50/50 dark:bg-orange-900/20 shadow-[0_0_15px_rgba(249,115,22,0.1)] dark:shadow-[0_0_20px_rgba(249,115,22,0.1)]' : 'border-slate-200 dark:border-slate-700' }} overflow-hidden transition-all hover:shadow-lg" x-show="matchesSearch('{{ addslashes($website->name) }}', '{{ addslashes($website->url) }}', '{{ $website->handled_by }}')">
            <div class="h-1 w-full {{ $isUnderMaintenance ? 'bg-orange-400' : 'bg-gradient-to-r from-emerald-400 to-emerald-600' }}"></div>
            <div class="p-5 flex flex-col flex-1 w-full">
                <div class="flex items-start gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center flex-shrink-0">
                        @if($website->logo_path)
                            <img src="{{ $website->logo_src }}" alt="" class="w-8 h-8 object-contain rounded">
                        @else
                            <span class="text-xl">🌐</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $website->name }}</h3>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                LIVE
                            </span>
                            @if($isUnderMaintenance)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-100 text-orange-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                                Maintenance
                            </span>
                            @endif
                        </div>
                        <a href="{{ $website->url }}" target="_blank" class="text-xs text-indigo-500 hover:text-indigo-700 truncate block">{{ $website->clean_domain }}</a>
                    </div>
                </div>

                @if($website->handler)
                <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 mb-3">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                    {{ $website->handler->name }}
                </div>
                @endif

                @if($website->live_at)
                <div class="flex items-center gap-1.5 text-xs text-slate-400 mb-4">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    Live since {{ $website->live_at->format('d M Y') }}
                </div>
                @endif

                @if($isUnderMaintenance)
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold text-orange-600 dark:text-orange-400">Maintenance Progress</span>
                        <span class="text-xs font-bold text-orange-600 dark:text-orange-400">{{ $website->maintenance_percent }}%</span>
                    </div>
                    <div class="h-2 bg-orange-100 dark:bg-orange-900/50 rounded-full overflow-hidden">
                        <div class="h-full bg-orange-500 rounded-full" style="width: {{ $website->maintenance_percent }}%"></div>
                    </div>
                </div>
                @endif

                <div class="mt-auto pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-center gap-2 w-full">
                    <a href="{{ $website->url }}" target="_blank"
                       class="btn btn-secondary text-xs py-1.5 px-3 flex-1 flex items-center justify-center gap-1 dark:text-slate-200 whitespace-nowrap">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                        Visit Site
                    </a>
                        @if(!$isUnderMaintenance && auth()->user()->canUpdateWebsiteProgress())
                        <button type="button"
                                @click="openMaintenanceModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                            class="btn btn-secondary text-xs py-1.5 px-2.5 group hover:bg-amber-500 hover:text-white hover:border-amber-500 dark:hover:bg-amber-600 transition-colors" title="Start Maintenance">
                        <svg class="w-4 h-4 text-amber-500 dark:text-amber-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75a4.5 4.5 0 0 1-4.884 4.484c-1.076-.091-2.264.071-2.95.904l-7.152 8.684a2.548 2.548 0 1 1-3.586-3.586l8.684-7.152c.833-.686.995-1.874.904-2.95a4.5 4.5 0 0 1 6.336-4.486l-3.276 3.276a3.004 3.004 0 0 0 2.25 2.25l3.276-3.276c.256.565.398 1.192.398 1.852Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M4.867 19.125h.008v.008h-.008v-.008Z" /></svg>
                    </button>
                    @endif
                    <button type="button"
                            @click="openHistoryModal({{ $website->id }}, '{{ addslashes($website->name) }}', 'maintenance', JSON.parse($event.currentTarget.dataset.logs))"
                            data-logs="{{ $website->serialized_logs }}"
                            class="btn btn-secondary text-xs py-1.5 px-2.5" title="View History">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </button>
                    @if(auth()->user()->canUpdateWebsiteProgress())
                    <button type="button" @click="openEditModal({{ $website->id }}, {{ json_encode($website->only(['name', 'url', 'category', 'logo_url', 'handled_by', 'start_date', 'deadline', 'notes'])) }})"
                            class="btn btn-secondary text-xs py-1.5 px-2.5" title="Edit">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                    </button>
                    @endif
                    @if(auth()->user()->hasAnyRole(['super-admin','admin-digital']))
                    <form action="{{ route('websites.destroy', $website) }}" method="POST" data-confirm="Delete {{ addslashes($website->name) }}?" data-confirm-title="Delete Website">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-secondary text-xs py-1.5 px-2.5 text-rose-500 hover:text-white hover:bg-rose-500 hover:border-rose-500" title="Delete">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
                @endforeach
            </div>
        </div>
        @endif
    @endforeach
    </div>
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════
     TAB 4: UPDATE / MAINTENANCE PROGRESS
════════════════════════════════════════════════════════════════ --}}
@if($tab === 'maintenance')
<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Update / Maintenance Progress</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Track maintenance progress. At 100%, the website returns to Live status.</p>
        </div>
    </div>

    @if($maintenanceWebsites->isEmpty())
    <div class="card border border-dashed border-slate-200 dark:border-slate-700 p-16 text-center">
        <div class="text-5xl mb-4">🔧</div>
        <h3 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-2">No websites under maintenance</h3>
        <p class="text-slate-500 text-sm">Start maintenance from the <a href="{{ route('websites.index', ['tab' => 'live']) }}" class="text-indigo-500 font-semibold">Live Websites</a> tab.</p>
    </div>
    @else
    <div>
            @php
        $groups = [];
        foreach ($orderArray as $cat) {
            $groups[$cat] = $maintenanceWebsites->where('category', $cat);
        }
        $groups['Uncategorized'] = $maintenanceWebsites->whereNull('category');
        $realCats = array_values($orderArray);
    @endphp
    @foreach($groups as $groupName => $groupWebsites)
        @if($groupWebsites->isNotEmpty())
        @php
            $catIndex = array_search($groupName, $realCats);
            $isRealCat = ($catIndex !== false);
        @endphp
        <div class="mb-8" x-show="hasMatchingWebsites({{ json_encode($groupWebsites->map(fn($f) => ['name' => $f->website->name ?? '', 'url' => $f->website->url ?? '', 'handled_by' => $f->website->handled_by ?? null])->values()) }})">
            <h3 @click="toggleGroup('maintenance-{{ addslashes($groupName) }}')" class="font-bold text-slate-700 dark:text-slate-200 mb-4 flex items-center gap-2 cursor-pointer select-none hover:text-indigo-600 transition-colors">
                <svg class="w-4 h-4 text-slate-400 transform transition-transform duration-200 flex-shrink-0" :class="isGroupCollapsed('maintenance-{{ addslashes($groupName) }}') ? '-rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 flex-shrink-0"></span>
                <span class="truncate">{{ $groupName }}</span>
                <span class="text-xs font-normal text-slate-400">({{ $groupWebsites->count() }})</span>
                @if($isRealCat && auth()->user()->hasAnyRole(['super-admin','admin-digital']))
                <div class="inline-flex items-center gap-1 ml-2" @click.stop>
                    @if($catIndex > 0)
                    <form action="{{ route('websites.reorderCategory') }}" method="POST" class="inline">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="category" value="{{ $groupName }}">
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" class="p-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 rounded transition-colors" title="Move Up">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" /></svg>
                        </button>
                    </form>
                    @endif
                    @if($catIndex < count($realCats) - 1)
                    <form action="{{ route('websites.reorderCategory') }}" method="POST" class="inline">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="category" value="{{ $groupName }}">
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" class="p-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 rounded transition-colors" title="Move Down">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                    </form>
                    @endif
                </div>
                @endif
            </h3>
            <div x-show="!isGroupCollapsed('maintenance-{{ addslashes($groupName) }}')" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($groupWebsites as $website)
        @php
            $pct = $website->maintenance_percent;
            $isMaintQc = $website->status === \App\Models\Website::STATUS_MAINTENANCE_QC_CHECKING;
            $isMaintSupervisor = $website->status === \App\Models\Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING;
            $maintColor = match(true) {
                $pct >= 100 => '#10b981',
                $pct >= 75  => '#3b82f6',
                $pct >= 50  => '#f97316',
                $pct >= 25  => '#f59e0b',
                default     => '#fb923c',
            };
        @endphp
        <div class="card flex flex-col w-full border {{ $isMaintQc ? 'border-amber-300 dark:border-amber-700 bg-amber-50/10 dark:bg-amber-900/5 shadow-amber-100 dark:shadow-amber-900/20 shadow-md' : ($isMaintSupervisor ? 'border-cyan-300 dark:border-cyan-700 bg-cyan-50/10 dark:bg-cyan-900/5 shadow-cyan-100 dark:shadow-cyan-900/20 shadow-md' : 'border-orange-200 dark:border-orange-800 bg-orange-50/20 dark:bg-orange-900/10') }} overflow-hidden transition-all hover:shadow-lg" x-show="matchesSearch('{{ addslashes($website->name) }}', '{{ addslashes($website->url) }}', '{{ $website->handled_by }}')">
            <div class="h-1 w-full" style="background: linear-gradient(90deg, {{ $maintColor }}, {{ $maintColor }}88);"></div>
            <div class="p-5 flex flex-col flex-1 w-full">
                <div class="flex items-start gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center flex-shrink-0">
                        @if($website->logo_path)
                            <img src="{{ $website->logo_src }}" alt="" class="w-8 h-8 object-contain rounded">
                        @else
                            <span class="text-xl">🔧</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $website->name }}</h3>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 flex-shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                LIVE
                            </span>
                            @if($isMaintQc)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 flex-shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                QC Checking
                            </span>
                            @elseif($isMaintSupervisor)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-cyan-100 text-cyan-700 flex-shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-cyan-500 animate-pulse"></span>
                                Supervisor Checking
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-100 text-orange-700 flex-shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                                Maintenance
                            </span>
                            @endif
                        </div>
                        <a href="{{ $website->url }}" target="_blank" class="text-xs text-indigo-500 hover:text-indigo-700 truncate block">{{ $website->clean_domain }}</a>
                    </div>
                </div>

                {{-- Maintenance Progress Bar --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-bold text-orange-600">Maintenance Progress</span>
                        <span class="text-sm font-black" style="color: {{ $maintColor }}">{{ $pct }}%</span>
                    </div>
                    <div class="h-3 bg-orange-100 dark:bg-orange-900/30 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500" style="width: {{ $pct }}%; background: {{ $maintColor }};"></div>
                    </div>
                    <div class="relative w-full h-4 mt-1">
                        @foreach([0,10,25,50,75,100] as $step)
                        <span class="absolute text-[9px] font-semibold {{ $pct >= $step ? 'text-orange-400' : 'text-slate-300 dark:text-slate-600' }}"
                              style="left: {{ $step }}%; transform: {{ $step === 0 ? 'none' : ($step === 100 ? 'translateX(-100%)' : 'translateX(-50%)') }};">
                            {{ $step }}%
                        </span>
                        @endforeach
                    </div>
                </div>

                {{-- Last Maintenance Note --}}
                @if($website->maintenanceLogs->isNotEmpty())
                @php $lastMaint = $website->maintenanceLogs->first(); @endphp
                <div class="mb-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg p-3 border border-orange-100 dark:border-orange-800">
                    <div class="text-[10px] font-bold text-orange-400 uppercase tracking-wide mb-1">Last Update</div>
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">{{ Str::limit($lastMaint->note, 100) }}</p>
                    <p class="text-[10px] text-slate-400 mt-1">{{ $lastMaint->user?->name ?? 'Unknown' }} · {{ $lastMaint->created_at->diffForHumans() }}</p>
                </div>
                @endif

                @if($website->maintenance_started_at)
                <div class="text-xs text-slate-400 mb-4">Started: {{ $website->maintenance_started_at->format('d M Y H:i') }}</div>
                @endif

                <div class="mt-auto pt-3 border-t border-orange-100 dark:border-orange-800 flex items-center justify-center gap-2 w-full">
                    @if(!$isMaintQc && !$isMaintSupervisor)
                    <button type="button"
                            @click="openProgressModal({{ $website->id }}, '{{ addslashes($website->name) }}', {{ $pct }}, 'maintenance')"
                            class="btn text-xs py-1.5 px-3 flex-1 bg-orange-500 hover:bg-orange-600 text-white">
                        Update Maintenance
                    </button>
                    @elseif($isMaintQc)
                    @if(auth()->user()->canApproveWebsiteQc())
                    <button type="button"
                            @click="openQcModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                            class="btn text-xs py-1.5 px-3 flex-1 bg-amber-500 hover:bg-amber-600 text-white">
                        ✓ QC Approve
                    </button>
                    <button type="button"
                            @click="openQcErrorModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                            class="btn text-xs py-1.5 px-2.5 bg-red-500 hover:bg-red-600 text-white">
                        ✗ QC Error
                    </button>
                    @else
                    <span class="text-xs text-amber-600 dark:text-amber-400 font-semibold flex-1 text-center">Awaiting QC Approval</span>
                    @endif
                    @elseif($isMaintSupervisor)
                    @if(auth()->user()->canApproveWebsiteSupervisor())
                    <button type="button"
                            @click="openSupervisorModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                            class="btn text-xs py-1.5 px-3 flex-1 bg-cyan-500 hover:bg-cyan-600 text-white">
                        ✓ Supervisor Approve
                    </button>
                    <button type="button"
                            @click="openSupervisorErrorModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                            class="btn text-xs py-1.5 px-2.5 bg-red-500 hover:bg-red-600 text-white">
                        ✗ Sup. Error
                    </button>
                    @else
                    <span class="text-xs text-cyan-600 dark:text-cyan-400 font-semibold flex-1 text-center">Awaiting Supervisor Approval</span>
                    @endif
                    @endif
                    <div class="flex items-center gap-1.5 shrink-0">
                    <button type="button"
                            @click="openHistoryModal({{ $website->id }}, '{{ addslashes($website->name) }}', 'maintenance', JSON.parse($event.currentTarget.dataset.logs))"
                            data-logs="{{ $website->serialized_logs }}"
                            class="btn btn-secondary text-xs py-1.5 px-2.5" title="View History">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </button>
                    @if(auth()->user()->canUpdateWebsiteProgress())
                    <button type="button" @click="openEditModal({{ $website->id }}, {{ json_encode($website->only(['name', 'url', 'category', 'logo_url', 'handled_by', 'start_date', 'deadline', 'notes'])) }})"
                            class="btn btn-secondary text-xs py-1.5 px-2.5" title="Edit">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                    </button>
                    @endif
                    @if(auth()->user()->hasAnyRole(['super-admin','admin-digital']))
                    <form action="{{ route('websites.destroy', $website) }}" method="POST" data-confirm="Delete {{ addslashes($website->name) }}?" data-confirm-title="Delete Website">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-secondary text-xs py-1.5 px-2.5 text-rose-500 hover:text-white hover:bg-rose-500 hover:border-rose-500" title="Delete">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </form>
                    @endif
                    </div>
                </div>
            </div>
        </div>
                @endforeach
            </div>
        </div>
        @endif
    @endforeach
    </div>
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════
     TAB 6: QC ERROR
════════════════════════════════════════════════════════════════ --}}
@if($tab === 'qc-error')
<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span class="text-red-500">⚠</span> QC Error
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Websites flagged with QC errors. Fix issues, update progress to 100%, then click Complete.</p>
        </div>
    </div>
    @if($qcErrorWebsites->isEmpty())
    <div class="card border border-dashed border-slate-200 dark:border-slate-700 p-16 text-center">
        <div class="text-5xl mb-4">✅</div>
        <h3 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-2">No QC Errors</h3>
        <p class="text-slate-500 text-sm">All websites have passed QC review.</p>
    </div>
    @else
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($qcErrorWebsites as $website)
        @php
            $errPct = $website->error_progress_percent ?? 0;
            $isMaintenanceError = $website->status === \App\Models\Website::STATUS_MAINTENANCE_QC_ERROR;
        @endphp
        <div class="card flex flex-col w-full border-2 border-red-300 dark:border-red-600 bg-red-50/30 dark:bg-red-900/10 overflow-hidden shadow-md hover:shadow-lg transition-all">
            <div class="h-1.5 w-full bg-gradient-to-r from-red-500 to-red-600"></div>
            <div class="p-5 flex flex-col flex-1 w-full">
                {{-- Header --}}
                <div class="flex items-start gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                        @if($website->logo_path)
                            <img src="{{ $website->logo_src }}" alt="" class="w-8 h-8 object-contain rounded">
                        @else
                            <span class="text-xl">🌐</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $website->name }}</h3>
                            @if($isMaintenanceError)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 flex-shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                LIVE
                            </span>
                            @endif
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400 flex-shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                QC Error {{ $isMaintenanceError ? '· Maintenance' : '· Build' }}
                            </span>
                        </div>
                        <a href="{{ $website->url }}" target="_blank" class="text-xs text-indigo-500 hover:text-indigo-700 truncate block">{{ $website->clean_domain }}</a>
                    </div>
                </div>
                {{-- Error Info --}}
                @if($website->error_note)
                <div class="mb-4 bg-red-50 dark:bg-red-900/30 rounded-lg p-3 border border-red-100 dark:border-red-700">
                    <div class="text-[10px] font-bold text-red-400 uppercase tracking-wide mb-1">Error Description</div>
                    <p class="text-xs text-slate-700 dark:text-slate-200 leading-relaxed">{{ $website->error_note }}</p>
                    @if($website->error_link)
                    <a href="{{ $website->error_link }}" target="_blank" class="inline-flex items-center gap-1 mt-2 text-[10px] font-semibold text-indigo-500 hover:text-indigo-700">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                        View Reference
                    </a>
                    @endif
                    @if($website->error_attachment_path)
                    <div class="inline-flex items-center gap-2 mt-2 ml-2 align-middle">
                        <button type="button" @click="openGenericAttachmentPreview(@js($website->error_attachment_name ?? 'Reference file'), @js(route('websites.error-attachment.view', $website)), @js(route('websites.error-attachment.download', $website)))" class="inline-flex items-center gap-1 text-[10px] font-semibold text-red-500 hover:text-red-700">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25A2.25 2.25 0 0 0 6 4.5v15A2.25 2.25 0 0 0 8.25 21h7.5A2.25 2.25 0 0 0 18 18.75M15 2.25V6a2.25 2.25 0 0 0 2.25 2.25H21"/></svg>
                            {{ Str::limit($website->error_attachment_name ?? 'Reference file', 22) }}
                        </button>
                        <a href="{{ route('websites.error-attachment.download', $website) }}" class="text-[10px] font-bold uppercase tracking-wide text-slate-500 hover:text-slate-700">Download</a>
                    </div>
                    @endif
                    @if($website->error_flagged_at)
                    <p class="text-[10px] text-slate-400 mt-1">Flagged {{ $website->error_flagged_at->diffForHumans() }}</p>
                    @endif
                </div>
                @endif
                {{-- Fix Progress --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Fix Progress</span>
                        <span class="text-sm font-black text-red-500">{{ $errPct }}%</span>
                    </div>
                    <div class="h-3 bg-red-100 dark:bg-red-900/30 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 bg-gradient-to-r from-red-500 to-orange-400" style="width: {{ $errPct }}%"></div>
                    </div>
                </div>
                {{-- Actions --}}
                <div class="mt-auto pt-3 border-t border-red-200 dark:border-red-800/50 flex items-center justify-center gap-2 w-full">
                    @if($errPct < 100)
                        @if(auth()->user()->canUpdateWebsiteProgress())
                        <button type="button"
                                @click="openErrorProgressModal({{ $website->id }}, '{{ addslashes($website->name) }}', {{ $errPct }})"
                                class="btn text-xs py-1.5 px-3 flex-1 bg-indigo-500 hover:bg-indigo-600 text-white">
                            Update Fix Progress
                        </button>
                        @else
                        <span class="text-xs text-slate-500 flex-1 text-center">Fix in progress...</span>
                        @endif
                    @else
                        @if(auth()->user()->canApproveWebsiteQc())
                        <button type="button"
                                @click="openQcModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                                class="btn text-xs py-1.5 px-3 flex-1 bg-amber-500 hover:bg-amber-600 text-white">
                            ✓ QC Approve
                        </button>
                        <button type="button"
                                @click="openQcErrorModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                                class="btn text-xs py-1.5 px-2.5 bg-red-500 hover:bg-red-600 text-white">
                            ✗ QC Error
                        </button>
                        @else
                        <span class="text-xs text-amber-500 font-semibold flex-1 text-center">Awaiting QC Check</span>
                        @endif
                    @endif
                    
                    <button type="button"
                            @click="openHistoryModal({{ $website->id }}, '{{ addslashes($website->name) }}', '{{ $website->status === \App\Models\Website::STATUS_MAINTENANCE_QC_ERROR ? 'maintenance' : 'build' }}', JSON.parse($event.currentTarget.dataset.logs))"
                            data-logs="{{ $website->serialized_logs }}"
                            class="btn btn-secondary text-xs py-1.5 px-2.5" title="View History">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </button>
                    @if(auth()->user()->canUpdateWebsiteProgress())
                    <button type="button" @click="openEditModal({{ $website->id }}, {{ json_encode($website->only(['name', 'url', 'category', 'logo_url', 'handled_by', 'start_date', 'deadline', 'notes'])) }})"
                            class="btn btn-secondary text-xs py-1.5 px-2.5" title="Edit">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                    </button>
                    @endif
                    @if(auth()->user()->hasAnyRole(['super-admin','admin-digital']))
                    <form action="{{ route('websites.destroy', $website) }}" method="POST" data-confirm="Delete {{ addslashes($website->name) }}?" data-confirm-title="Delete Website">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-secondary text-xs py-1.5 px-2.5 text-rose-500 hover:text-white hover:bg-rose-500 hover:border-rose-500" title="Delete">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════
     TAB 7: SUPERVISOR ERROR
════════════════════════════════════════════════════════════════ --}}
@if($tab === 'supervisor-error')
<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span class="text-orange-500">⚠</span> Supervisor Error
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Websites flagged with Supervisor errors. Fix, update progress to 100%, then click Complete.</p>
        </div>
    </div>
    @if($supervisorErrorWebsites->isEmpty())
    <div class="card border border-dashed border-slate-200 dark:border-slate-700 p-16 text-center">
        <div class="text-5xl mb-4">✅</div>
        <h3 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-2">No Supervisor Errors</h3>
        <p class="text-slate-500 text-sm">All websites have passed Supervisor review.</p>
    </div>
    @else
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($supervisorErrorWebsites as $website)
        @php
            $errPct = $website->error_progress_percent ?? 0;
            $isMaintenanceError = $website->status === \App\Models\Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR;
        @endphp
        <div class="card flex flex-col w-full border-2 border-orange-300 dark:border-orange-600 bg-orange-50/30 dark:bg-orange-900/10 overflow-hidden shadow-md hover:shadow-lg transition-all">
            <div class="h-1.5 w-full bg-gradient-to-r from-orange-500 to-amber-500"></div>
            <div class="p-5 flex flex-col flex-1 w-full">
                {{-- Header --}}
                <div class="flex items-start gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center flex-shrink-0">
                        @if($website->logo_path)
                            <img src="{{ $website->logo_src }}" alt="" class="w-8 h-8 object-contain rounded">
                        @else
                            <span class="text-xl">🌐</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $website->name }}</h3>
                            @if($isMaintenanceError)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 flex-shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                LIVE
                            </span>
                            @endif
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400 flex-shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                                Sup. Error {{ $isMaintenanceError ? '· Maintenance' : '· Build' }}
                            </span>
                        </div>
                        <a href="{{ $website->url }}" target="_blank" class="text-xs text-indigo-500 hover:text-indigo-700 truncate block">{{ $website->clean_domain }}</a>
                    </div>
                </div>
                {{-- Error Info --}}
                @if($website->error_note)
                <div class="mb-4 bg-orange-50 dark:bg-orange-900/30 rounded-lg p-3 border border-orange-100 dark:border-orange-700">
                    <div class="text-[10px] font-bold text-orange-400 uppercase tracking-wide mb-1">Error Description</div>
                    <p class="text-xs text-slate-700 dark:text-slate-200 leading-relaxed">{{ $website->error_note }}</p>
                    @if($website->error_link)
                    <a href="{{ $website->error_link }}" target="_blank" class="inline-flex items-center gap-1 mt-2 text-[10px] font-semibold text-indigo-500 hover:text-indigo-700">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                        View Reference
                    </a>
                    @endif
                    @if($website->error_attachment_path)
                    <div class="inline-flex items-center gap-2 mt-2 ml-2 align-middle">
                        <button type="button" @click="openGenericAttachmentPreview(@js($website->error_attachment_name ?? 'Reference file'), @js(route('websites.error-attachment.view', $website)), @js(route('websites.error-attachment.download', $website)))" class="inline-flex items-center gap-1 text-[10px] font-semibold text-orange-500 hover:text-orange-700">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25A2.25 2.25 0 0 0 6 4.5v15A2.25 2.25 0 0 0 8.25 21h7.5A2.25 2.25 0 0 0 18 18.75M15 2.25V6a2.25 2.25 0 0 0 2.25 2.25H21"/></svg>
                            {{ Str::limit($website->error_attachment_name ?? 'Reference file', 22) }}
                        </button>
                        <a href="{{ route('websites.error-attachment.download', $website) }}" class="text-[10px] font-bold uppercase tracking-wide text-slate-500 hover:text-slate-700">Download</a>
                    </div>
                    @endif
                    @if($website->error_flagged_at)
                    <p class="text-[10px] text-slate-400 mt-1">Flagged {{ $website->error_flagged_at->diffForHumans() }}</p>
                    @endif
                </div>
                @endif
                {{-- Fix Progress --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Fix Progress</span>
                        <span class="text-sm font-black text-orange-500">{{ $errPct }}%</span>
                    </div>
                    <div class="h-3 bg-orange-100 dark:bg-orange-900/30 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 bg-gradient-to-r from-orange-500 to-amber-400" style="width: {{ $errPct }}%"></div>
                    </div>
                </div>
                {{-- Actions --}}
                <div class="mt-auto pt-3 border-t border-orange-100 dark:border-orange-800 flex items-center justify-center gap-2 w-full">
                    @if($errPct < 100)
                        @if(auth()->user()->canUpdateWebsiteProgress())
                        <button type="button"
                                @click="openErrorProgressModal({{ $website->id }}, '{{ addslashes($website->name) }}', {{ $errPct }})"
                                class="btn text-xs py-1.5 px-3 flex-1 bg-indigo-500 hover:bg-indigo-600 text-white">
                            Update Fix Progress
                        </button>
                        @else
                        <span class="text-xs text-slate-500 flex-1 text-center">Fix in progress...</span>
                        @endif
                    @else
                        @if(auth()->user()->canApproveWebsiteSupervisor())
                        <button type="button"
                                @click="openSupervisorModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                                class="btn text-xs py-1.5 px-3 flex-1 bg-cyan-500 hover:bg-cyan-600 text-white">
                            ✓ Supervisor Approve
                        </button>
                        <button type="button"
                                @click="openSupervisorErrorModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                                class="btn text-xs py-1.5 px-2.5 bg-red-500 hover:bg-red-600 text-white">
                            ✗ Sup. Error
                        </button>
                        @else
                        <span class="text-xs text-cyan-500 font-semibold flex-1 text-center">Awaiting Sup. Check</span>
                        @endif
                    @endif
                    
                    <button type="button"
                            @click="openHistoryModal({{ $website->id }}, '{{ addslashes($website->name) }}', '{{ $website->status === \App\Models\Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR ? 'maintenance' : 'build' }}', JSON.parse($event.currentTarget.dataset.logs))"
                            data-logs="{{ $website->serialized_logs }}"
                            class="btn btn-secondary text-xs py-1.5 px-2.5" title="View History">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </button>
                    @if(auth()->user()->canUpdateWebsiteProgress())
                    <button type="button" @click="openEditModal({{ $website->id }}, {{ json_encode($website->only(['name', 'url', 'category', 'logo_url', 'handled_by', 'start_date', 'deadline', 'notes'])) }})"
                            class="btn btn-secondary text-xs py-1.5 px-2.5" title="Edit">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                    </button>
                    @endif
                    @if(auth()->user()->hasAnyRole(['super-admin','admin-digital']))
                    <form action="{{ route('websites.destroy', $website) }}" method="POST" data-confirm="Delete {{ addslashes($website->name) }}?" data-confirm-title="Delete Website">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-secondary text-xs py-1.5 px-2.5 text-rose-500 hover:text-white hover:bg-rose-500 hover:border-rose-500" title="Delete">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════
     TAB 5: FOLLOW UP
════════════════════════════════════════════════════════════════ --}}
@if($tab === 'follow-up')
<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Follow Up</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Track blog posts, indexed pages, and website follow-ups.</p>
        </div>
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <div class="relative flex-1 sm:flex-none">
                <input type="text" x-model="searchQuery" placeholder="Search follow-ups..." class="form-input text-xs py-1.5 pl-8 pr-3 rounded-lg w-full sm:w-56 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
            </div>
            
            <button type="button" @click="showExportModal = true"
               class="btn btn-primary px-3 py-1.5 text-sm flex-shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                <span class="hidden sm:inline">Export Report</span>
                <span class="sm:hidden">Export</span>
            </button>

            @if(!auth()->user()->isWebsiteViewer() && !auth()->user()->hasRole('boss'))
            <button type="button" @click="showFollowUpModal = true" class="btn btn-primary flex items-center gap-2 text-sm ml-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Add Follow Up
            </button>
            @endif
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('websites.index', ['tab' => 'follow-up']) }}" class="card border border-slate-200 dark:border-slate-700 p-4 mb-5">
        <input type="hidden" name="tab" value="follow-up">
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Class</label>
                <select name="fu_class" class="form-select text-sm rounded-lg py-1.5 min-w-[140px]" onchange="this.form.submit()">
                    <option value="">All Classes</option>
                    @foreach($allClasses as $cls)
                    <option value="{{ $cls }}" {{ ($followUpFilter['fu_class'] ?? '') === $cls ? 'selected' : '' }}>{{ $cls }}</option>
                    @endforeach
                    @if(\App\Models\Website::where('is_archived', false)->whereNull('category')->exists())
                    <option value="__none__" {{ ($followUpFilter['fu_class'] ?? '') === '__none__' ? 'selected' : '' }}>Uncategorized</option>
                    @endif
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Website</label>
                <select name="fu_website" class="form-select text-sm rounded-lg py-1.5 min-w-[140px]" onchange="this.form.submit()">
                    <option value="">All Websites</option>
                    @php
                        $fuWebsitesList = $allWebsites;
                        if (!empty($followUpFilter['fu_class'])) {
                            if ($followUpFilter['fu_class'] === '__none__') {
                                $fuWebsitesList = $allWebsites->whereNull('category');
                            } else {
                                $fuWebsitesList = $allWebsites->where('category', $followUpFilter['fu_class']);
                            }
                        }
                    @endphp
                    @foreach($fuWebsitesList as $ws)
                    <option value="{{ $ws->id }}" {{ ($followUpFilter['fu_website'] ?? '') == $ws->id ? 'selected' : '' }}>{{ $ws->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Type</label>
                <select name="fu_type" class="form-select text-sm rounded-lg py-1.5 min-w-[130px]" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    @foreach(\App\Models\WebsiteFollowUp::TYPES as $key => $label)
                    <option value="{{ $key }}" {{ ($followUpFilter['fu_type'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Member</label>
                <select name="fu_member" class="form-select text-sm rounded-lg py-1.5 min-w-[140px]" onchange="this.form.submit()">
                    <option value="">All Members</option>
                    @foreach($websiteTeamMembers as $u)
                    <option value="{{ $u->id }}" {{ ($followUpFilter['fu_member'] ?? '') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Date</label>
                <input type="date" name="fu_date" value="{{ $followUpFilter['fu_date'] ?? '' }}" class="form-input text-sm rounded-lg py-1.5 min-w-[130px] border border-slate-300 dark:border-slate-600 dark:bg-slate-800" onchange="this.form.submit()">
            </div>
            <div class="flex items-center">
                <a href="{{ route('websites.index', ['tab' => 'follow-up']) }}" class="btn btn-secondary text-sm py-1.5 px-3">Clear</a>
            </div>
            
            @if(!empty($followUpFilter['fu_class']))
            <div class="ml-2 flex items-center gap-1.5 text-xs text-slate-500">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                Showing: <strong class="text-indigo-600 dark:text-indigo-400">{{ $followUpFilter['fu_class'] === '__none__' ? 'Uncategorized' : $followUpFilter['fu_class'] }}</strong>
                <span class="text-slate-400">({{ $fuWebsitesList->count() }} sites)</span>
            </div>
            @endif
        </div>
    </form>

    @if($followUps->isEmpty())
    <div class="card border border-dashed border-slate-200 dark:border-slate-700 p-16 text-center">
        <div class="text-5xl mb-4">📝</div>
        <h3 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-2">No follow-ups found</h3>
        <p class="text-slate-500 text-sm">Add your first follow-up entry above.</p>
    </div>
    @else
    @php
        $fuGroups = [];
        foreach ($orderArray as $cat) {
            $fuGroups[$cat] = $followUps->filter(fn($f) => $f->website && $f->website->category === $cat);
        }
        $fuGroups['Uncategorized'] = $followUps->filter(fn($f) => !$f->website || empty($f->website->category));
    @endphp
    @foreach($fuGroups as $groupName => $groupFollowUps)
        @if($groupFollowUps->isNotEmpty())
        <div class="mb-8" x-show="hasMatchingWebsites({{ json_encode($groupFollowUps->map(fn($f) => ['name' => $f->website?->name ?? '', 'url' => $f->website?->url ?? '', 'handled_by' => $f->website?->handled_by ?? null])->values()) }})">
            <h3 @click="toggleGroup('followup-{{ addslashes($groupName) }}')" class="font-bold text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2 cursor-pointer select-none hover:text-indigo-600 transition-colors">
                <svg class="w-4 h-4 text-slate-400 transform transition-transform duration-200 flex-shrink-0" :class="isGroupCollapsed('followup-{{ addslashes($groupName) }}') ? '-rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 flex-shrink-0"></span>
                <span class="truncate">{{ $groupName }}</span>
                <span class="text-xs font-normal text-slate-400">({{ $groupFollowUps->count() }})</span>
            </h3>
            <div x-show="!isGroupCollapsed('followup-{{ addslashes($groupName) }}')" class="card overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-xl mb-4">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                            <th class="p-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Website</th>
                            <th class="p-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Type</th>
                            <th class="p-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">URL</th>
                            <th class="p-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">QC By</th>
                            <th class="p-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Handle by</th>
                            <th class="p-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                            <th class="p-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($groupFollowUps as $fu)
                        <tr x-show="matchesSearch('{{ addslashes($fu->website?->name ?? '') }}', '{{ addslashes($fu->website?->clean_domain ?? '') }}', '{{ $fu->website?->handled_by ?? '' }}')" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                <div>{{ $fu->website?->name ?? '–' }}</div>
                                @if($fu->website)
                                    <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5 font-normal">Status: {{ ucfirst(str_replace('_', ' ', $fu->website->status)) }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $typeColors = ['blog_post' => 'bg-blue-100 text-blue-700', 'indexed_page' => 'bg-violet-100 text-violet-700', 'website_page' => 'bg-slate-100 text-slate-700', 'other' => 'bg-stone-100 text-stone-700'];
                                @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $typeColors[$fu->type] ?? 'bg-slate-100 text-slate-600' }}">{{ $fu->getTypeLabel() }}</span>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                @if($fu->url) <a href="{{ $fu->url }}" target="_blank" class="text-indigo-500 hover:text-indigo-700 text-xs truncate block">{{ Str::limit($fu->url, 40) }}</a> @endif
                                @if($fu->note) <p class="text-xs text-slate-400 mt-0.5">{{ Str::limit($fu->note, 60) }}</p> @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex flex-col items-start gap-1">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $fu->getQcStatusBadgeClass() }} leading-none">{{ ucfirst($fu->qc_status) }}</span>
                                    @if($fu->qc_checked_at)
                                        <div class="flex flex-col">
                                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $fu->qcChecker?->name ?? 'System' }}</span>
                                            <span class="text-[10px] text-slate-400">{{ $fu->qc_checked_at->format('d M, Y') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ $fu->assignee?->name ?? '–' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">{{ $fu->created_at->format('d M Y') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @if(auth()->user()->hasAnyRole(['super-admin','admin-digital']) && $fu->qc_status !== 'approved')
                                    <form action="{{ route('websites.followups.qc', $fu) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="qc_status" value="approved">
                                        <button type="submit" class="btn btn-secondary text-xs py-1 px-1.5" title="Approve QC">✓</button>
                                    </form>
                                    @endif
                                    @if(!auth()->user()->isWebsiteViewer() && !auth()->user()->hasRole('boss'))
                                    <button type="button" 
                                            @click="openEditFollowUpModal({{ $fu->id }}, {{ json_encode([
                                                'website_id' => $fu->website_id,
                                                'type' => $fu->type,
                                                'url' => $fu->url,
                                                'assigned_to' => $fu->assigned_to,
                                                'note' => $fu->note,
                                                'created_at' => $fu->created_at->format('Y-m-d')
                                            ]) }})"
                                            class="btn btn-secondary text-xs py-1 px-1.5 text-indigo-500 hover:text-indigo-700 animate-pulse-slow" 
                                            title="Edit">
                                        ✎
                                    </button>
                                    @endif
                                    <form action="{{ route('websites.followups.destroy', $fu) }}" method="POST" data-confirm="Are you sure you want to delete this follow-up?" data-confirm-title="Delete Follow-up">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-secondary text-xs py-1 px-1.5 text-rose-500 hover:text-rose-700" title="Delete">×</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    @endforeach
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════
     MODALS
════════════════════════════════════════════════════════════════ --}}

{{-- Manage Classes Modal --}}
<div x-show="showManageClassesModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
    <div class="card border border-slate-200 dark:border-slate-700 w-full max-w-4xl max-h-[90vh] overflow-y-auto" @click.stop>
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
                <h3 class="font-bold text-slate-800 dark:text-slate-100">Manage Classes / Groups</h3>
            </div>
            <button @click="showManageClassesModal = false" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-6">
            <!-- inner block -->
            <?php // We need to replace the extracted block's header since we made a modal header ?>{{-- Group Management Section --}}
    <div>
        <div class="mb-5 flex flex-wrap sm:flex-nowrap items-center gap-3 w-full max-w-2xl">
            <form action="{{ route('websites.storeCategory') }}" method="POST" class="flex-1 relative">
                @csrf
                <input type="text" name="name" placeholder="Type new class name..." required class="form-input text-sm py-2.5 pl-4 pr-20 rounded-xl w-full border-slate-200 dark:border-slate-600 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                <button type="submit" class="absolute right-1.5 top-1.5 px-3 py-1 text-xs font-bold uppercase tracking-wide bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors shadow-sm">Add</button>
            </form>
            <div class="relative flex-1">
                <input type="text" x-model="classSearchQuery" placeholder="Search classes..." class="form-input text-sm py-2.5 pl-9 pr-3 rounded-xl w-full border-slate-200 dark:border-slate-600 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
            </div>
        </div>
        
        <div id="manage-classes-list" class="flex flex-col gap-2 bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-100 dark:border-slate-700 max-h-[60vh] overflow-y-auto"
             x-init="
                if(window.Sortable) {
                    Sortable.create($el, {
                        handle: '.class-drag-handle',
                        animation: 150,
                        ghostClass: 'opacity-50',
                        onEnd: (evt) => {
                            const newOrder = Array.from($el.children).map(child => child.dataset.id).filter(Boolean);
                            fetch('{{ route('websites.reorderCategory') }}', {
                                method: 'PUT',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrf() },
                                body: JSON.stringify({ categories: newOrder })
                            }).then(() => window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Group order updated!', type: 'success' } })));
                        }
                    });
                }
             ">
            @foreach($orderArray as $cat)
            <div data-id="{{ $cat }}" x-show="!classSearchQuery || '{{ addslashes($cat) }}'.toLowerCase().includes(classSearchQuery.toLowerCase())" class="flex items-center justify-between bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg shadow-sm px-3 py-2 w-full group hover:border-indigo-300 dark:hover:border-indigo-500/50 transition-colors">
                
                <div class="flex items-center gap-2 overflow-hidden flex-1">
                    <div class="class-drag-handle cursor-move p-1 text-slate-300 hover:text-slate-500 dark:hover:text-slate-400 transition-colors" title="Drag to reorder">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5" /></svg>
                    </div>
                    <span x-show="editingClass !== '{{ addslashes($cat) }}'" class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate pr-4">{{ $cat }}</span>
                    
                    <form x-show="editingClass === '{{ addslashes($cat) }}'" action="{{ route('websites.renameCategory') }}" method="POST" class="flex-1 flex items-center gap-2 mr-2" style="display: none;">
                    @csrf @method('PUT')
                    <input type="hidden" name="old_category" value="{{ $cat }}">
                    <input type="text" name="new_category" x-model="editingClassName" required class="form-input w-full text-xs py-1 px-2 rounded-md border-slate-300 dark:border-slate-600 focus:ring-indigo-500 h-7" @keydown.escape="editingClass = null">
                    <button type="submit" class="p-1 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-md transition-colors" title="Save">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    </button>
                    <button type="button" @click="editingClass = null" class="p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-colors" title="Cancel">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </form>
                </div>

                <div x-show="editingClass !== '{{ addslashes($cat) }}'" class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                    <button type="button" @click="editingClass = '{{ addslashes($cat) }}'; editingClassName = '{{ addslashes($cat) }}'" class="p-1.5 text-slate-400 hover:text-indigo-500 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors" title="Edit Class">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                    </button>
                    <form :id="'delete-class-' + '{{ md5($cat) }}'" action="{{ route('websites.destroyCategory') }}" method="POST" class="inline">
                        @csrf @method('DELETE')
                        <input type="hidden" name="category" value="{{ $cat }}">
                        <button type="button" @click="classToDelete = '{{ addslashes($cat) }}'; classToDeleteId = 'delete-class-' + '{{ md5($cat) }}'; showDeleteClassModal = true;" class="p-1.5 text-slate-400 hover:text-rose-500 rounded-md hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors" title="Remove Class">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </div>
        </div>
    </div>
</div>

{{-- Delete Class Confirmation Modal --}}
<div x-show="showDeleteClassModal" x-cloak class="fixed inset-0 z-[110] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
    <div class="card border border-rose-200 dark:border-rose-800 w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-xl overflow-hidden" @click.stop>
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-rose-100 dark:bg-rose-900/30 text-rose-500 mb-4">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-2">Remove Class</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-2">
                Are you sure you want to remove the class <span class="font-bold text-slate-800 dark:text-slate-200" x-text="'\'' + classToDelete + '\''"></span>?
            </p>
            <p class="text-xs text-amber-600 dark:text-amber-400 font-medium bg-amber-50 dark:bg-amber-900/20 p-3 rounded-lg border border-amber-100 dark:border-amber-800/30">
                Don't worry, the websites inside this class will <strong>NOT</strong> be deleted. They will just become Uncategorized and you can assign them to a new class later.
            </p>
        </div>
        <div class="p-4 bg-slate-50 dark:bg-slate-900/40 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-3">
            <button @click="showDeleteClassModal = false; classToDelete = ''; classToDeleteId = '';" type="button" class="btn btn-secondary text-sm px-4">Cancel</button>
            <button @click="document.getElementById(classToDeleteId).submit()" type="button" class="btn btn-danger text-sm px-4">Yes, Remove</button>
        </div>
    </div>
</div>

{{-- Manage Website Members Modal --}}
<div x-show="showManageMembersModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
    <div class="card border border-slate-200 dark:border-slate-700 w-full max-w-6xl max-h-[90vh] overflow-y-auto" @click.stop>
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                </svg>
                <h3 class="font-bold text-slate-800 dark:text-slate-100">Manage Website Members</h3>
            </div>
            <button @click="showManageMembersModal = false; selectedUserIds = []; memberUserSearch = ''; isEditing = false;" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <div class="p-6 space-y-6 bg-slate-50/50 dark:bg-slate-900/20">
            {{-- Add Member Form --}}
            <form action="{{ route('websites.members.store') }}" method="POST" class="space-y-4 bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-100 dark:border-slate-700 shadow-sm">
                @csrf
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Add or Edit Member</h4>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Select User(s)</label>
                        <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-3 bg-white dark:bg-slate-850">
                            <input type="text" x-model="memberUserSearch" placeholder="Search users..." class="form-input text-xs py-1 px-2.5 mb-2 w-full rounded-lg border-slate-200 dark:border-slate-650 bg-white dark:bg-slate-800 focus:ring-indigo-500">
                            
                            <div class="max-h-36 overflow-y-auto space-y-1.5 scrollbar-thin">
                                @foreach($users as $u)
                                <label class="flex items-center gap-2 cursor-pointer py-1 px-1.5 rounded hover:bg-slate-50 dark:hover:bg-slate-800 text-xs w-full"
                                       x-show="!memberUserSearch || '{{ addslashes(strtolower($u->name)) }}'.includes(memberUserSearch.toLowerCase())">
                                    <input type="checkbox" name="user_ids[]" value="{{ $u->id }}" 
                                           :checked="selectedUserIds.includes({{ $u->id }}) || ({{ isset($memberRolesMap[$u->id]) ? 'true' : 'false' }} && !isEditing)"
                                           :disabled="{{ isset($memberRolesMap[$u->id]) ? 'true' : 'false' }} && !isEditing"
                                           @change="if($event.target.checked && !selectedUserIds.includes({{ $u->id }})) selectedUserIds.push({{ $u->id }}); else selectedUserIds = selectedUserIds.filter(id => id != {{ $u->id }})"
                                           class="rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500 w-4 h-4 disabled:opacity-50 disabled:bg-slate-100 dark:disabled:bg-slate-700 disabled:cursor-not-allowed">
                                    <span class="text-slate-700 dark:text-slate-300 font-medium">{{ $u->name }}</span>
                                    @if(isset($memberRolesMap[$u->id]))
                                        <span class="ml-auto text-emerald-600 dark:text-emerald-400 font-semibold text-[10px] bg-emerald-50 dark:bg-emerald-950/30 px-1.5 py-0.5 rounded flex items-center gap-1">
                                            ✓ {{ $memberRolesMap[$u->id] }}
                                        </span>
                                    @endif
                                </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Set Role</label>
                        <select name="role" x-model="memberForm.role" required class="form-select w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-800">
                            <option value="Developer">Developer</option>
                            <option value="QC">QC</option>
                            <option value="Supervisor">Supervisor</option>
                            <option value="Viewer">Viewer</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit" :disabled="selectedUserIds.length === 0" class="btn btn-primary text-xs flex items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Add / Update Member(s)
                    </button>
                </div>
            </form>

            {{-- Members List --}}
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Current Members</h4>
                <div class="border border-slate-100 dark:border-slate-700 rounded-xl overflow-x-auto overflow-y-hidden shadow-sm bg-white dark:bg-slate-800">
                    <table class="w-full border-collapse text-left min-w-[600px]">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-100 dark:border-slate-700">
                                <th class="p-3 text-xs font-bold text-slate-500 uppercase tracking-wider">User</th>
                                <th class="p-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Role</th>
                                <th class="p-3 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700 text-sm" x-data="{ editingMemberId: null, editingMemberRole: '' }">
                            @forelse($websiteMembers as $m)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-850/50 transition-colors">
                                <td class="p-3 flex items-center gap-2">
                                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $m->user?->name ?? 'Unknown User' }}</span>
                                    <span class="text-xs text-slate-400">({{ $m->user?->email }})</span>
                                </td>
                                <td class="p-3">
                                    {{-- Display Mode --}}
                                    @php
                                        $roleClasses = match($m->role) {
                                            'QC' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
                                            'Supervisor' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-500/20 dark:text-cyan-300',
                                            'Developer' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/20 dark:text-indigo-300',
                                            'Boss' => 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-300',
                                            'Viewer' => 'bg-slate-200 text-slate-800 dark:bg-slate-600/30 dark:text-slate-300',
                                            default => 'bg-slate-100 text-slate-800 dark:bg-slate-700/30 dark:text-slate-400'
                                        };
                                    @endphp
                                    <span x-show="editingMemberId !== {{ $m->id }}" class="px-2 py-0.5 rounded-full text-xs font-bold {{ $roleClasses }}">
                                        {{ $m->role }}
                                    </span>
                                    
                                    {{-- Edit Mode --}}
                                    <form x-show="editingMemberId === {{ $m->id }}" action="{{ route('websites.members.store') }}" method="POST" class="flex items-center gap-2" style="display: none;">
                                        @csrf
                                        <input type="hidden" name="user_ids[]" value="{{ $m->user_id }}">
                                        <select name="role" x-model="editingMemberRole" required class="form-select text-xs py-1 px-2 rounded border-slate-200 dark:border-slate-600 focus:ring-indigo-500 h-7 w-32">
                                            <option value="Developer">Developer</option>
                                            <option value="QC">QC</option>
                                            <option value="Supervisor">Supervisor</option>
                                            <option value="Viewer">Viewer</option>
                                        </select>
                                        <button type="submit" class="p-1 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-md transition-colors" title="Save">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                        </button>
                                        <button type="button" @click="editingMemberId = null" class="p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-colors" title="Cancel">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                        </button>
                                    </form>
                                </td>
                                <td class="p-3 text-right">
                                    <div class="inline-flex items-center gap-2 justify-end">
                                        <button x-show="editingMemberId !== {{ $m->id }}" type="button" @click="editingMemberId = {{ $m->id }}; editingMemberRole = '{{ addslashes($m->role) }}'" class="btn text-xs py-1 px-2.5 border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-950/20 text-slate-600 hover:text-indigo-600 rounded transition-colors bg-white dark:bg-slate-800" title="Edit Role">
                                            Edit
                                        </button>
                                        <form action="{{ route('websites.members.destroy', $m->id) }}" method="POST" data-confirm="Are you sure you want to remove member {{ addslashes($m->user?->name ?? '') }}?" data-confirm-title="Remove Member">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn text-xs py-1 px-2 border border-rose-200 hover:border-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20 text-rose-500 rounded transition-colors bg-white dark:bg-slate-800" title="Remove">
                                                Remove
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="p-8 text-center text-slate-400 text-xs font-semibold">No website members added yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-700 text-right bg-slate-50/50 dark:bg-slate-900/20">
            <button type="button" @click="showManageMembersModal = false; selectedUserIds = []; memberUserSearch = ''; isEditing = false;" class="btn btn-secondary text-sm">Close</button>
        </div>
    </div>
</div>

{{-- Create Website Modal --}}
<div x-show="showCreateModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
    <div class="card border border-slate-200 dark:border-slate-700 w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 dark:text-slate-100">Add New Website</h3>
            <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form action="{{ route('websites.store') }}" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Website Name *</label>
                    <input type="text" name="name" required class="form-input w-full rounded-xl text-sm" placeholder="My Website">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">URL *</label>
                    <input type="url" name="url" required class="form-input w-full rounded-xl text-sm" placeholder="https://example.com">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Class</label>
                    <select name="category" class="form-select w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-800">
                        <option value="">None / Uncategorized</option>
                        @foreach($orderArray as $cat)<option value="{{ $cat }}">{{ $cat }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Assigned To</label>
                    <select name="handled_by" class="form-select w-full rounded-xl text-sm">
                        <option value="">Select member</option>
                        @php
                            $memberUserIds = $websiteMembers->pluck('user_id')->unique()->toArray();
                            $activeUsers = $users->filter(fn($u) => in_array($u->id, $memberUserIds));
                        @endphp
                        @foreach($activeUsers as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Start Date</label>
                    <input type="date" name="start_date" class="form-input w-full rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Deadline</label>
                    <input type="date" name="deadline" class="form-input w-full rounded-xl text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Logo URL (optional)</label>
                    <input type="url" name="logo_url" class="form-input w-full rounded-xl text-sm" placeholder="https://...logo.png">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="form-textarea w-full rounded-xl text-sm resize-none" placeholder="Project notes..."></textarea>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showCreateModal = false" class="btn btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn btn-primary text-sm">Create Website</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Website Modal --}}
<div x-show="showEditModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
    <div class="card border border-slate-200 dark:border-slate-700 w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 dark:text-slate-100">Edit Website</h3>
            <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form :action="editModalAction" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_tab" value="{{ $tab }}">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Website Name *</label>
                    <input type="text" name="name" x-model="editForm.name" required class="form-input w-full rounded-xl text-sm" placeholder="My Website">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">URL *</label>
                    <input type="url" name="url" x-model="editForm.url" required class="form-input w-full rounded-xl text-sm" placeholder="https://example.com">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Class</label>
                    <select name="category" x-model="editForm.category" class="form-select w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-800">
                        <option value="">None / Uncategorized</option>
                        @foreach($orderArray as $cat)<option value="{{ $cat }}">{{ $cat }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Assigned To</label>
                    <select name="handled_by" x-model="editForm.handled_by" class="form-select w-full rounded-xl text-sm">
                        <option value="">Select member</option>
                        @php
                            $memberUserIds = $websiteMembers->pluck('user_id')->unique()->toArray();
                            $activeUsers = $users->filter(fn($u) => in_array($u->id, $memberUserIds));
                        @endphp
                        @foreach($activeUsers as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Start Date</label>
                    <input type="date" name="start_date" x-model="editForm.start_date" class="form-input w-full rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Deadline</label>
                    <input type="date" name="deadline" x-model="editForm.deadline" class="form-input w-full rounded-xl text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Logo URL (optional)</label>
                    <input type="url" name="logo_url" x-model="editForm.logo_url" class="form-input w-full rounded-xl text-sm" placeholder="https://...logo.png">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Notes</label>
                    <textarea name="notes" x-model="editForm.notes" rows="3" class="form-textarea w-full rounded-xl text-sm resize-none" placeholder="Project notes..."></textarea>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showEditModal = false" class="btn btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn btn-primary text-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- Progress Update Modal --}}
<div x-show="showProgressModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
    <div class="card border border-slate-200 dark:border-slate-700 w-full max-w-md" @click.stop>
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-slate-800 dark:text-slate-100" x-text="progressModalTitle"></h3>
                <p class="text-xs text-slate-500 mt-0.5">Current: <span class="font-bold text-indigo-600" x-text="progressModalCurrent + '%'"></span></p>
            </div>
            <button @click="showProgressModal = false" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form :action="progressModalAction" method="POST" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-2">New Percentage *</label>
                <div class="grid grid-cols-3 gap-2">
                    @foreach([0, 10, 25, 50, 75, 100] as $step)
                    <label class="cursor-pointer">
                        <input type="radio" name="percent" :value="{{ $step }}" class="sr-only peer" x-model.number="progressModalCurrent" x-bind:required="true">
                        <div class="peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600 border-2 border-slate-200 dark:border-slate-600 rounded-xl p-3 text-center font-black text-sm transition-all hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-400">
                            {{ $step }}%
                            @if($step === 100) <div class="text-[10px] font-normal opacity-70">→ QC</div> @endif
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">
                    Note <span class="text-rose-500 font-extrabold">*</span> 
                    <span class="font-normal text-slate-400 dark:text-slate-500 normal-case ml-1">(required — explain what was completed)</span>
                </label>
                <textarea name="note" rows="4" required minlength="5" 
                          class="form-textarea w-full rounded-xl text-sm p-3.5 bg-slate-50 shadow-inner resize-none border border-slate-200 dark:border-slate-700 dark:bg-slate-800/80 focus:border-indigo-500 focus:ring focus:ring-indigo-500/20 placeholder-slate-400 dark:placeholder-slate-500 transition-all duration-150" 
                          placeholder="e.g. Homepage layout completed. Navigation menu added..."></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showProgressModal = false" class="btn btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn btn-primary text-sm" x-text="progressModalType === 'maintenance' ? 'Update Maintenance' : 'Update Progress'"></button>
            </div>
        </form>
    </div>
</div>

{{-- QC Approval Modal --}}
<div x-show="showQcModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
    <div class="card border border-amber-200 dark:border-amber-700 w-full max-w-md" @click.stop>
        <div class="p-5 border-b border-amber-100 dark:border-amber-800 flex items-center justify-between bg-amber-50/50 dark:bg-amber-900/20">
            <div>
                <h3 class="font-bold text-slate-800 dark:text-slate-100">✓ Approve QC</h3>
                <p class="text-xs text-amber-600 mt-0.5" x-text="'Approving: ' + qcModalName"></p>
            </div>
            <button @click="showQcModal = false" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form :action="qcModalAction" method="POST" class="p-5 space-y-4">
            @csrf
            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-4 text-sm text-amber-700 dark:text-amber-300">
                <p class="font-semibold">This will:</p>
                <ul class="mt-2 space-y-1 text-xs">
                    <li>• Transition website status to <strong>Supervisor Checking</strong></li>
                    <li>• Record QC approval with your name and timestamp</li>
                    <li>• Keep website in the current tab until Supervisor approval</li>
                </ul>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">QC Note <span class="font-normal text-slate-400 dark:text-slate-500 normal-case ml-1">(optional)</span></label>
                <textarea name="qc_note" rows="3" 
                          class="form-textarea w-full rounded-xl text-sm resize-none border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 focus:border-amber-500 focus:ring focus:ring-amber-500/20 placeholder-slate-400 dark:placeholder-slate-500 transition-all duration-150 shadow-sm" 
                          placeholder="e.g. All pages checked, mobile responsive, no broken links..."></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showQcModal = false" class="btn btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn text-sm bg-amber-500 hover:bg-amber-600 text-white">✓ Approve QC</button>
            </div>
        </form>
    </div>
</div>

{{-- Supervisor Approval Modal --}}
<div x-show="showSupervisorModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
    <div class="card border border-cyan-200 dark:border-cyan-700 w-full max-w-md" @click.stop>
        <div class="p-5 border-b border-cyan-100 dark:border-cyan-800 flex items-center justify-between bg-cyan-50/50 dark:bg-cyan-900/20">
            <div>
                <h3 class="font-bold text-slate-800 dark:text-slate-100">✓ Approve Supervisor & Set Live</h3>
                <p class="text-xs text-cyan-600 mt-0.5" x-text="'Approving: ' + supervisorModalName"></p>
            </div>
            <button @click="showSupervisorModal = false" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form :action="supervisorModalAction" method="POST" class="p-5 space-y-4">
            @csrf
            <div class="bg-cyan-50 dark:bg-cyan-900/20 rounded-xl p-4 text-sm text-cyan-700 dark:text-cyan-300">
                <p class="font-semibold">This will:</p>
                <ul class="mt-2 space-y-1 text-xs">
                    <li>• Set website status to <strong>Live</strong></li>
                    <li>• Record Supervisor approval with your name and timestamp</li>
                    <li>• Move website to the Live Websites tab</li>
                </ul>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Supervisor Note <span class="font-normal text-slate-400 dark:text-slate-500 normal-case ml-1">(optional)</span></label>
                <textarea name="supervisor_note" rows="3" 
                          class="form-textarea w-full rounded-xl text-sm resize-none border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 focus:border-cyan-500 focus:ring focus:ring-cyan-500/20 placeholder-slate-400 dark:placeholder-slate-500 transition-all duration-150 shadow-sm" 
                          placeholder="e.g. Final checklist verified, site ready for release."></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showSupervisorModal = false" class="btn btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn text-sm bg-cyan-500 hover:bg-cyan-600 text-white">✓ Approve & Go Live</button>
            </div>
        </form>
    </div>
</div>

{{-- Start Maintenance Modal --}}
<div x-show="showMaintenanceModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
    <div class="card border border-orange-200 dark:border-orange-700 w-full max-w-md" @click.stop>
        <div class="p-5 border-b border-orange-100 dark:border-orange-800 flex items-center justify-between bg-orange-50/50 dark:bg-orange-900/20">
            <div>
                <h3 class="font-bold text-slate-800 dark:text-slate-100">🔧 Start Maintenance</h3>
                <p class="text-xs text-orange-600 mt-0.5" x-text="maintenanceModalName"></p>
            </div>
            <button @click="showMaintenanceModal = false" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form :action="maintenanceModalAction" method="POST" class="p-5 space-y-4">
            @csrf
            <p class="text-sm text-slate-500">The website will remain visible as Live but with a Maintenance label. It will also appear in the Maintenance Progress tab.</p>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">
                    Reason for Maintenance <span class="text-rose-500 font-extrabold">*</span>
                </label>
                <textarea name="maintenance_note" rows="3" required minlength="5" 
                          class="form-textarea w-full rounded-xl text-sm resize-none border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 focus:border-orange-500 focus:ring focus:ring-orange-500/20 placeholder-slate-400 dark:placeholder-slate-500 transition-all duration-150 shadow-sm" 
                          placeholder="e.g. Plugin updates and SEO improvements needed..."></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showMaintenanceModal = false" class="btn btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn text-sm bg-orange-500 hover:bg-orange-600 text-white">Start Maintenance</button>
            </div>
        </form>
    </div>
</div>

{{-- QC Error Modal --}}
<div x-show="showQcErrorModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
    <div class="card border border-red-300 dark:border-red-700 w-full max-w-md" @click.stop>
        <div class="p-5 border-b border-red-100 dark:border-red-800 flex items-center justify-between bg-red-50/50 dark:bg-red-900/20">
            <div>
                <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <span class="text-red-500">⚠</span> Flag QC Error
                </h3>
                <p class="text-xs text-red-600 dark:text-red-400 mt-0.5" x-text="'Website: ' + qcErrorModalName"></p>
            </div>
            <button @click="showQcErrorModal = false" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form :action="qcErrorModalAction" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
            @csrf
            <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-3 text-xs text-red-700 dark:text-red-300">
                The website will move to the <strong>QC Error</strong> tab. The team must fix the issues and mark complete (0→100%) before this website returns to QC Checking.
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">
                    Error Description <span class="text-rose-500 font-extrabold">*</span>
                </label>
                <textarea name="error_note" rows="3" required minlength="5"
                          class="form-textarea w-full rounded-xl text-sm resize-none border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 focus:border-red-500 focus:ring focus:ring-red-500/20 placeholder-slate-400 dark:placeholder-slate-500 transition-all shadow-sm"
                          placeholder="e.g. Mobile layout is broken on homepage, images not optimised..."></textarea>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">
                    Reference Link <span class="font-normal text-slate-400 dark:text-slate-500 normal-case ml-1">(optional — Canva, screenshot, etc.)</span>
                </label>
                <input type="text" name="error_link"
                       class="form-input w-full rounded-xl text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 focus:border-red-500 focus:ring focus:ring-red-500/20 placeholder-slate-400 dark:placeholder-slate-500 transition-all shadow-sm"
                       placeholder="https://www.canva.com/... or any reference URL">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">
                    Reference Files <span class="font-normal text-slate-400 dark:text-slate-500 normal-case ml-1">(optional PDFs or images)</span>
                </label>
                <input type="file" name="error_files[]" accept=".pdf,image/png,image/jpeg,image/webp" multiple
                       class="form-input w-full rounded-xl text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 file:mr-3 file:rounded-lg file:border-0 file:bg-red-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-red-600">
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showQcErrorModal = false" class="btn btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn text-sm bg-red-500 hover:bg-red-600 text-white">⚠ Flag as QC Error</button>
            </div>
        </form>
    </div>
</div>

{{-- Supervisor Error Modal --}}
<div x-show="showSupervisorErrorModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
    <div class="card border border-red-300 dark:border-red-700 w-full max-w-md" @click.stop>
        <div class="p-5 border-b border-red-100 dark:border-red-800 flex items-center justify-between bg-red-50/50 dark:bg-red-900/20">
            <div>
                <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <span class="text-red-500">⚠</span> Flag Supervisor Error
                </h3>
                <p class="text-xs text-red-600 dark:text-red-400 mt-0.5" x-text="'Website: ' + supervisorErrorModalName"></p>
            </div>
            <button @click="showSupervisorErrorModal = false" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form :action="supervisorErrorModalAction" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
            @csrf
            <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-3 text-xs text-red-700 dark:text-red-300">
                The website will move to the <strong>Supervisor Error</strong> tab. The team must fix and complete before it goes back to QC → Supervisor approval.
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">
                    Error Description <span class="text-rose-500 font-extrabold">*</span>
                </label>
                <textarea name="error_note" rows="3" required minlength="5"
                          class="form-textarea w-full rounded-xl text-sm resize-none border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 focus:border-red-500 focus:ring focus:ring-red-500/20 placeholder-slate-400 dark:placeholder-slate-500 transition-all shadow-sm"
                          placeholder="e.g. Content doesn't meet brand guidelines, SEO structure needs rework..."></textarea>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">
                    Reference Link <span class="font-normal text-slate-400 dark:text-slate-500 normal-case ml-1">(optional — Canva, screenshot, etc.)</span>
                </label>
                <input type="text" name="error_link"
                       class="form-input w-full rounded-xl text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 focus:border-red-500 focus:ring focus:ring-red-500/20 placeholder-slate-400 dark:placeholder-slate-500 transition-all shadow-sm"
                       placeholder="https://www.canva.com/... or any reference URL">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">
                    Reference Files <span class="font-normal text-slate-400 dark:text-slate-500 normal-case ml-1">(optional PDFs or images)</span>
                </label>
                <input type="file" name="error_files[]" accept=".pdf,image/png,image/jpeg,image/webp" multiple
                       class="form-input w-full rounded-xl text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 file:mr-3 file:rounded-lg file:border-0 file:bg-orange-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-orange-600">
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showSupervisorErrorModal = false" class="btn btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn text-sm bg-red-500 hover:bg-red-600 text-white">⚠ Flag as Supervisor Error</button>
            </div>
        </form>
    </div>
</div>

{{-- Error Fix Progress Modal --}}
<div x-show="showErrorProgressModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
    <div class="card border border-slate-200 dark:border-slate-700 w-full max-w-md" @click.stop>
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-slate-800 dark:text-slate-100">Update Error Fix Progress</h3>
                <p class="text-xs text-slate-500 mt-0.5" x-text="errorProgressModalName"></p>
            </div>
            <button @click="showErrorProgressModal = false" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form :action="errorProgressModalAction" method="POST" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">
                    Fix Progress <span class="text-rose-500 font-extrabold">*</span>
                </label>
                <div class="grid grid-cols-6 gap-2">
                    @foreach([0,10,25,50,75,100] as $step)
                    <label class="cursor-pointer">
                        <input type="radio" name="percent" value="{{ $step }}" class="sr-only peer" {{ $step === 0 ? 'checked' : '' }}>
                        <span class="block text-center text-xs font-bold py-2 rounded-lg border border-slate-200 dark:border-slate-600 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600 hover:border-indigo-400 transition-all">
                            {{ $step }}%
                        </span>
                    </label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">
                    Update Note <span class="text-rose-500 font-extrabold">*</span>
                </label>
                <textarea name="note" rows="3" required minlength="5"
                          class="form-textarea w-full rounded-xl text-sm resize-none border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 focus:border-indigo-500 focus:ring focus:ring-indigo-500/20 placeholder-slate-400 dark:placeholder-slate-500 transition-all shadow-sm"
                          placeholder="Describe what was fixed..."></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showErrorProgressModal = false" class="btn btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn btn-primary text-sm">Update Progress</button>
            </div>
        </form>
    </div>
</div>

{{-- Follow Up Modal --}}
<div x-show="showFollowUpModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
    <div class="card border border-slate-200 dark:border-slate-700 w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 dark:text-slate-100">Add Follow Up</h3>
            <button @click="showFollowUpModal = false" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form action="{{ route('websites.followups.store') }}" method="POST" class="p-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Website *</label>
                    <div x-data="{ open: false, search: '', selectedId: '', selectedName: 'Select website...' }" class="relative">
                        <input type="hidden" name="website_id" x-model="selectedId" required>
                        <button type="button" @click="open = !open" @click.outside="open = false" class="form-select w-full rounded-xl text-sm text-left flex justify-between items-center bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-600">
                            <span x-text="selectedName" :class="{ 'text-slate-400': !selectedId }"></span>
                        </button>
                        <div x-show="open" x-cloak class="absolute z-50 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-xl shadow-lg max-h-60 overflow-y-auto overflow-x-hidden">
                            <div class="p-2 sticky top-0 bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700 z-10">
                                <input type="text" x-model="search" placeholder="Search websites..." class="form-input w-full text-xs rounded-lg py-1.5 border-slate-200 dark:border-slate-600 focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900">
                            </div>
                            <ul class="py-1">
                                <li class="px-3 py-2 text-sm text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer" @click="selectedId = ''; selectedName = 'Select website...'; open = false">Select website...</li>
                                @foreach($allWebsites as $ws)
                                <li x-show="search === '' || '{{ strtolower(addslashes($ws->name)) }}'.includes(search.toLowerCase())" class="px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-indigo-50 dark:hover:bg-indigo-900/50 cursor-pointer" @click="selectedId = '{{ $ws->id }}'; selectedName = '{{ addslashes($ws->name) }}'; open = false">{{ $ws->name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                <div x-data="{ selectedType: 'blog_post' }" class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Type *</label>
                    <select name="type" required x-model="selectedType" class="form-select w-full rounded-xl text-sm">
                        @foreach(\App\Models\WebsiteFollowUp::TYPES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="custom_type" x-show="selectedType === 'other'" x-transition placeholder="Type custom type..." class="form-input w-full rounded-xl text-sm mt-2 border-dashed" :required="selectedType === 'other'">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Page URL</label>
                    <input type="url" name="url" class="form-input w-full rounded-xl text-sm" placeholder="https://...">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Handle by</label>
                    <select name="assigned_to" class="form-select w-full rounded-xl text-sm">
                        <option value="">None</option>
                        @foreach($websiteTeamMembers as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Date *</label>
                    <input type="date" name="created_at" required class="form-input w-full rounded-xl text-sm border border-slate-300 dark:border-slate-600 dark:bg-slate-800" value="{{ now()->format('Y-m-d') }}">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Note</label>
                    <textarea name="note" rows="3" class="form-textarea w-full rounded-xl text-sm resize-none" placeholder="Optional notes..."></textarea>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showFollowUpModal = false" class="btn btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn btn-primary text-sm">Add Follow Up</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Follow Up Modal --}}
<div x-show="showEditFollowUpModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
    <div class="card border border-slate-200 dark:border-slate-700 w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 dark:text-slate-100">Edit Follow Up</h3>
            <button @click="showEditFollowUpModal = false" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form :action="editFollowUpAction" method="POST" class="p-5 space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Website *</label>
                    <div x-data="{ open: false, search: '', 
                        get selectedName() {
                            let option = this.$el.closest('.relative').querySelector(`li[data-id='${editFollowUpForm.website_id}']`);
                            return option ? option.innerText : 'Select website...';
                        }
                    }" class="relative">
                        <input type="hidden" name="website_id" x-model="editFollowUpForm.website_id" required>
                        <button type="button" @click="open = !open" @click.outside="open = false" class="form-select w-full rounded-xl text-sm text-left flex justify-between items-center bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-600">
                            <span x-text="selectedName" :class="{ 'text-slate-400': !editFollowUpForm.website_id }"></span>
                        </button>
                        <div x-show="open" x-cloak class="absolute z-50 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-xl shadow-lg max-h-60 overflow-y-auto overflow-x-hidden">
                            <div class="p-2 sticky top-0 bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700 z-10">
                                <input type="text" x-model="search" placeholder="Search websites..." class="form-input w-full text-xs rounded-lg py-1.5 border-slate-200 dark:border-slate-600 focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900">
                            </div>
                            <ul class="py-1">
                                <li class="px-3 py-2 text-sm text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer" @click="editFollowUpForm.website_id = ''; open = false">Select website...</li>
                                @foreach($allWebsites as $ws)
                                <li data-id="{{ $ws->id }}" x-show="search === '' || '{{ strtolower(addslashes($ws->name)) }}'.includes(search.toLowerCase())" class="px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-indigo-50 dark:hover:bg-indigo-900/50 cursor-pointer" @click="editFollowUpForm.website_id = '{{ $ws->id }}'; open = false">{{ $ws->name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Type *</label>
                    <select name="type" required x-model="editFollowUpForm.type" class="form-select w-full rounded-xl text-sm">
                        @foreach(\App\Models\WebsiteFollowUp::TYPES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="custom_type" x-model="editFollowUpForm.custom_type" x-show="editFollowUpForm.type === 'other'" x-transition placeholder="Type custom type..." class="form-input w-full rounded-xl text-sm mt-2 border-dashed" :required="editFollowUpForm.type === 'other'">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Page URL</label>
                    <input type="url" name="url" x-model="editFollowUpForm.url" class="form-input w-full rounded-xl text-sm" placeholder="https://...">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Handle by</label>
                    <select name="assigned_to" x-model="editFollowUpForm.assigned_to" class="form-select w-full rounded-xl text-sm">
                        <option value="">None</option>
                        @foreach($websiteTeamMembers as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Date *</label>
                    <input type="date" name="created_at" required x-model="editFollowUpForm.created_at" class="form-input w-full rounded-xl text-sm border border-slate-300 dark:border-slate-600 dark:bg-slate-800">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-1">Note</label>
                    <textarea name="note" x-model="editFollowUpForm.note" rows="3" class="form-textarea w-full rounded-xl text-sm resize-none" placeholder="Optional notes..."></textarea>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showEditFollowUpModal = false" class="btn btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn btn-primary text-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- Export Modal --}}
<div x-show="showExportModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
    <div class="card border border-slate-200 dark:border-slate-700 w-full max-w-lg" @click.stop>
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 dark:text-slate-100">Export Websites Report</h3>
            <button @click="showExportModal = false" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form action="{{ route('websites.export') }}" method="GET" x-data="{ format: 'csv' }" class="p-6 space-y-6">
            <input type="hidden" name="tab" value="{{ $tab }}">
            {{-- Format Selection --}}
            <div>
                <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide mb-3">Select Format</label>
                <input type="hidden" name="format" x-model="format">
                <div class="grid grid-cols-2 gap-4">
                    <label class="cursor-pointer">
                        <input type="radio" name="format_radio" value="csv" x-model="format" class="sr-only">
                        <div class="p-4 border-2 rounded-2xl flex items-center gap-4 transition-all"
                             :class="format === 'csv' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20 shadow-sm' : 'border-slate-200 dark:border-slate-700 hover:border-indigo-300 bg-white dark:bg-slate-800'">
                            <div class="w-12 h-12 rounded-xl flex-shrink-0 flex items-center justify-center transition-colors"
                                 :class="format === 'csv' ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-500 dark:bg-slate-700'">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M3 16.5l6-6 4 4 8-8"/></svg>
                            </div>
                            <div>
                                <div class="font-bold text-slate-800 dark:text-slate-100">CSV Excel</div>
                                <div class="text-[10px] text-slate-500">Spreadsheet format</div>
                            </div>
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input type="radio" name="format_radio" value="pdf" x-model="format" class="sr-only">
                        <div class="p-4 border-2 rounded-2xl flex items-center gap-4 transition-all"
                             :class="format === 'pdf' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20 shadow-sm' : 'border-slate-200 dark:border-slate-700 hover:border-indigo-300 bg-white dark:bg-slate-800'">
                            <div class="w-12 h-12 rounded-xl flex-shrink-0 flex items-center justify-center transition-colors"
                                 :class="format === 'pdf' ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-500 dark:bg-slate-700'">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                            </div>
                            <div>
                                <div class="font-bold text-slate-800 dark:text-slate-100">PDF Report</div>
                                <div class="text-[10px] text-slate-500">Printable document</div>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Member Filter --}}
            @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital']) || auth()->user()->hasRole('boss'))
            <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-100 dark:border-slate-700">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1 uppercase tracking-wide">Filter by Member</label>
                <select name="member_id" class="form-select w-full rounded-lg text-sm border-slate-200">
                    <option value="">All Members</option>
                    @foreach(($tab === 'follow-up' ? $websiteTeamMembers : $users) as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
                <p class="text-[10px] text-slate-500 mt-1">Export websites assigned to a specific member.</p>
            </div>
            @else
            <input type="hidden" name="member_id" value="{{ auth()->id() }}">
            @endif

            {{-- Date Range Selection --}}
            <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-100 dark:border-slate-700">
                <p class="text-xs text-slate-500 mb-3 font-semibold uppercase tracking-wide">Date Filter (Optional)</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Start Date</label>
                        <input type="date" name="start_date" class="form-input w-full rounded-lg text-sm border-slate-200">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">End Date</label>
                        <input type="date" name="end_date" class="form-input w-full rounded-lg text-sm border-slate-200">
                    </div>
                </div>
            </div>
            
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showExportModal = false" class="btn btn-secondary text-sm px-5">Cancel</button>
                <button type="submit" class="btn btn-primary text-sm bg-indigo-600 hover:bg-indigo-700 text-white px-6 shadow-md shadow-indigo-200" @click="showExportModal = false">
                    <svg class="w-4 h-4 mr-1.5 inline" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    <span x-text="format === 'pdf' ? 'Download PDF' : 'Download CSV'"></span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- History Modal --}}
<div x-show="showHistoryModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5)">
    <div class="card border border-slate-200 dark:border-slate-700 w-full max-w-lg" @click.stop>
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-slate-800 dark:text-slate-100">
                    <span x-text="historyType === 'maintenance' ? '🔧' : '📊'"></span> 
                    History: <span x-text="historyWebsiteName"></span>
                </h3>
            </div>
            <button @click="showHistoryModal = false" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-5 max-h-[60vh] overflow-y-auto space-y-4 bg-slate-50 dark:bg-slate-900/30">
            <template x-if="historyLogs.length === 0">
                <p class="text-center text-sm text-slate-500 py-4">No history records found.</p>
            </template>
            <template x-for="log in historyLogs" :key="log.id">
                <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-100 dark:border-slate-700 shadow-sm">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2">
                            <template x-if="log.new_status">
                                <span class="text-[10px] font-bold px-2 py-0.5 bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 rounded uppercase tracking-wider" x-text="formatStatusLabel(log.new_status)"></span>
                            </template>
                            <span class="text-xs font-bold px-2 py-0.5 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 rounded-md" x-text="(log.percent !== undefined ? log.percent : (log.new_progress !== undefined ? log.new_progress : '0')) + '%'"></span>
                        </div>
                        <span class="text-[10px] text-slate-400 shrink-0 pt-0.5" x-text="new Date(log.created_at).toLocaleString()"></span>
                    </div>
                    <p class="text-sm text-slate-700 dark:text-slate-300 mb-2 leading-relaxed" x-html="formatNoteText(log.note)"></p>
                    
                    <!-- Show extracted link if note has | Link: -->
                    <template x-if="extractLink(log.note)">
                        <a :href="extractLink(log.note)" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 mb-3 text-xs font-semibold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 border border-indigo-100 rounded-lg px-3 py-1.5 transition-colors">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                            <span class="truncate max-w-[220px]" x-text="extractLink(log.note)"></span>
                        </a>
                    </template>
                    <template x-if="log.attachments && log.attachments.length">
                        <div class="mt-3 space-y-2">
                            <template x-for="file in log.attachments" :key="file.id || file.path">
                                <div class="flex items-start gap-3 bg-slate-50 dark:bg-slate-900/50 p-2 rounded-lg border border-slate-100 dark:border-slate-700/50">
                                    <template x-if="isImageAttachment(file)">
                                        <button type="button" @click="openAttachmentPreview(log, file)" class="block w-16 h-16 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-600 shadow-sm hover:opacity-80 transition-opacity flex-shrink-0 bg-slate-100">
                                            <img :src="getHistoryAttachmentUrl(log, 'view', file)" class="w-full h-full object-cover" x-on:error="$el.parentElement.style.opacity='0.5'">
                                        </button>
                                    </template>
                                    <template x-if="!isImageAttachment(file)">
                                        <button type="button" @click="openAttachmentPreview(log, file)" class="w-16 h-16 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 flex flex-col items-center justify-center flex-shrink-0 shadow-sm text-slate-400 gap-1">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25A2.25 2.25 0 0 0 6 4.5v15A2.25 2.25 0 0 0 8.25 21h7.5A2.25 2.25 0 0 0 18 18.75M15 2.25V6a2.25 2.25 0 0 0 2.25 2.25H21"/></svg>
                                            <span class="text-[9px] uppercase tracking-wide font-bold" x-text="getAttachmentExtension(file)"></span>
                                        </button>
                                    </template>
                                    <div class="min-w-0 flex-1 pt-0.5">
                                        <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 truncate" x-text="file.name || 'Attached File'" :title="file.name"></span>
                                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                                            <button type="button" @click="openAttachmentPreview(log, file)" class="inline-flex items-center gap-1 text-[10px] uppercase tracking-wider text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 font-bold bg-indigo-50 hover:bg-indigo-100 rounded-md px-2 py-1 transition-colors">
                                                View
                                            </button>
                                            <a :href="getHistoryAttachmentUrl(log, 'download', file)" class="inline-flex items-center gap-1 text-[10px] uppercase tracking-wider text-slate-600 hover:text-slate-800 font-bold bg-slate-100 hover:bg-slate-200 rounded-md px-2 py-1 transition-colors">
                                                Download
                                            </a>
                                            <template x-if="canManageLog(log)">
                                                <button type="button" @click="deleteHistoryAttachment(log, file)" class="inline-flex items-center gap-1 text-[10px] uppercase tracking-wider text-rose-600 hover:text-rose-800 font-bold bg-rose-50 hover:bg-rose-100 rounded-md px-2 py-1 transition-colors">
                                                    Delete
                                                </button>
                                            </template>
                                            <template x-if="canManageLog(log)">
                                                <button type="button" @click="openHistoryEditModal(log)" class="inline-flex items-center gap-1 text-[10px] uppercase tracking-wider text-amber-600 hover:text-amber-800 font-bold bg-amber-50 hover:bg-amber-100 rounded-md px-2 py-1 transition-colors">
                                                    Edit
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 dark:border-slate-700 pt-3">
                        <div class="text-[11px] font-semibold text-slate-500">
                            Updated by: <span x-text="log.user ? log.user.name : 'Unknown'"></span>
                        </div>
                        <template x-if="canManageLog(log) && (!log.attachments || !log.attachments.length)">
                            <button type="button" @click="openHistoryEditModal(log)" class="text-[10px] uppercase tracking-wider font-bold text-amber-600 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 rounded-md px-2 py-1 shrink-0">
                                Edit
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-700 text-right">
            <button type="button" @click="showHistoryModal = false" class="btn btn-secondary text-sm">Close</button>
        </div>
    </div>
</div>

<template x-teleport="body">
    {{-- Attachment Preview Modal --}}
    <div x-show="showAttachmentPreview && previewIsImage"
         x-cloak
         style="display:none"
         class="image-modal"
         aria-modal="true"
         role="dialog"
         @click.self="closeAttachmentPreview()"
         @keydown.escape.window="closeAttachmentPreview()"
         @wheel.prevent="handlePreviewWheel($event)"
         @mousedown.prevent="startPreviewPan($event)"
         @mousemove.window="movePreviewPan($event)"
         @mouseup.window="endPreviewPan()"
         @touchstart="handlePreviewTouchStart($event)"
         @touchmove="handlePreviewTouchMove($event)"
         @touchend="endPreviewPan()"
         @dblclick="resetPreviewZoom()">
        <button type="button" class="close-image" @click="closeAttachmentPreview()" aria-label="Close image preview">&times;</button>
        <img :src="previewUrl"
             :alt="previewFile?.name || 'Attachment Preview'"
             :style="previewImageStyle()"
             :class="previewZoom > 100 ? 'cursor-grab active:cursor-grabbing' : 'cursor-zoom-in'"
             draggable="false"
             @load="handlePreviewImageLoad($event)">
        <div x-show="previewLoading" class="fixed inset-0 z-[10045] flex items-center justify-center pointer-events-none">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-white/30 border-t-white"></div>
        </div>
    </div>
</template>

<template x-teleport="body">
    {{-- PDF / Document Preview Modal --}}
    <div x-show="showAttachmentPreview && !previewIsImage" x-cloak style="display:none; z-index:10040" class="fixed inset-0 flex items-center justify-center p-4 sm:p-6" aria-modal="true" role="dialog" @keydown.escape.window="closeAttachmentPreview()">
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm" @click="closeAttachmentPreview()"></div>
        <div x-ref="previewPanel" class="relative flex h-[76vh] max-h-[760px] min-h-[420px] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10 dark:bg-slate-900 dark:ring-white/10">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-3 dark:border-slate-800 dark:bg-slate-900/50">
                <h3 class="truncate pr-4 text-sm font-black text-slate-800 dark:text-slate-100" x-text="previewFile?.name || 'Attachment Preview'"></h3>
                <div class="flex items-center gap-2 shrink-0">
                    <a :href="previewDownloadUrl" class="rounded-lg bg-slate-200/70 px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Download</a>
                    <button type="button" @click="closeAttachmentPreview()" class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200/70 text-slate-500 transition hover:bg-slate-300 hover:text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700" aria-label="Close preview">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <div class="relative flex-1 overflow-hidden bg-slate-100 dark:bg-slate-800">
                <div x-show="previewLoading" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-white/80 backdrop-blur-sm dark:bg-slate-900/80">
                    <div class="h-8 w-8 animate-spin rounded-full border-4 border-violet-200 border-t-violet-600"></div>
                    <p class="mt-3 text-xs font-bold text-slate-500">Loading preview...</p>
                </div>
                <iframe :src="previewUrl" class="h-full w-full border-0 bg-white" @load="previewLoading = false"></iframe>
            </div>
        </div>
    </div>
</template>

<template x-teleport="body">
    {{-- History Edit Modal --}}
    <div x-show="showHistoryEditModal" x-cloak style="display:none; z-index:10060" class="fixed inset-0 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm" @keydown.escape.window="closeHistoryEditModal()" @click.self="closeHistoryEditModal()">
        <div x-ref="historyEditPanel" tabindex="-1" class="card relative z-10 w-full max-w-lg border border-slate-200 shadow-2xl dark:border-slate-700" @click.stop>
            <div class="flex items-center justify-between border-b border-slate-100 p-5 dark:border-slate-700">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-slate-100">Edit Error History</h3>
                    <p class="mt-0.5 text-xs text-slate-400">Update text, remove files, or replace/add more files.</p>
                </div>
                <button type="button" @click="closeHistoryEditModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="space-y-4 p-5">
                <div>
                    <label class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Error Text</label>
                    <textarea x-model="historyEditNote" rows="4" class="form-textarea w-full resize-none rounded-xl border border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-800/50"></textarea>
                </div>
                <template x-if="visibleHistoryEditAttachments().length">
                    <div>
                        <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-500">Current Files</p>
                        <div class="space-y-2">
                            <template x-for="file in visibleHistoryEditAttachments()" :key="file.id || file.path">
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-800">
                                    <span class="truncate font-bold text-slate-700 dark:text-slate-200" x-text="file.name || 'Attached File'"></span>
                                    <button type="button" @click="removeHistoryEditFile(file)" class="shrink-0 rounded-md bg-rose-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-rose-600 transition hover:bg-rose-100 hover:text-rose-800">
                                        Remove
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                <div>
                    <label class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Replace / Add Files</label>
                    <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" x-ref="historyEditFiles" @change="updateHistoryEditSelectedFiles()" class="form-input w-full rounded-xl text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50">
                    <div class="mt-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-[11px] text-slate-500 dark:border-slate-700 dark:bg-slate-800/50">
                        <template x-if="historyEditSelectedFileNames.length">
                            <div>
                                <p class="font-bold text-slate-600 dark:text-slate-300" x-text="historyEditSelectedFileNames.length + ' file(s) selected'"></p>
                                <p class="mt-1 truncate" x-text="historyEditSelectedFileNames.join(', ')"></p>
                            </div>
                        </template>
                        <template x-if="!historyEditSelectedFileNames.length">
                            <p>Choose one or many image/PDF files to add.</p>
                        </template>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="closeHistoryEditModal()" class="btn btn-secondary text-sm">Cancel</button>
                    <button type="button" @click="saveHistoryLogEdit()" class="btn text-sm bg-indigo-600 text-white hover:bg-indigo-700">Save Edit</button>
                </div>
            </div>
        </div>
    </div>
</template>

</div>{{-- /x-data --}}

@push('scripts')
<script>
function websitesApp() {
    return {
        // Search state
        searchQuery: '',
        filterMember: '',
        matchesSearch(name, url, handlerId = '') {
            let matchSearch = true;
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                matchSearch = (name && name.toLowerCase().includes(q)) || (url && url.toLowerCase().includes(q));
            }
            let matchMember = true;
            if (this.filterMember) {
                matchMember = (handlerId == this.filterMember);
            }
            return matchSearch && matchMember;
        },
        hasMatchingWebsites(websites) {
            if (!this.searchQuery && !this.filterMember) return true;
            return websites.some(w => {
                let matchSearch = true;
                if (this.searchQuery) {
                    const q = this.searchQuery.toLowerCase();
                    matchSearch = (w.name && w.name.toLowerCase().includes(q)) || 
                                  (w.url && w.url.toLowerCase().includes(q));
                }
                let matchMember = true;
                if (this.filterMember) {
                    matchMember = (w.handled_by == this.filterMember);
                }
                return matchSearch && matchMember;
            });
        },

        // Member form state
        memberForm: {
            role: 'Developer'
        },
        selectedUserIds: [],
        memberUserSearch: '',
        isEditing: false,
        editMember(userId, role) {
            this.isEditing = true;
            this.selectedUserIds = [userId];
            this.memberForm.role = role;
        },

        // Modal state
        showCreateModal:      false,
        showManageClassesModal: localStorage.getItem('showManageClassesModal') === 'true',
        showManageMembersModal: localStorage.getItem('showManageMembersModal') === 'true',
        showProgressModal:    false,
        showQcModal:          false,
        showSupervisorModal:  false,
        showMaintenanceModal: false,
        showFollowUpModal:    false,
        showEditFollowUpModal:false,
        showExportModal:      false,
        editFollowUpAction:   '',
        editFollowUpForm: {
            website_id: '', type: '', title: '', url: '', google_indexed: '', assigned_to: '', note: '', created_at: ''
        },
        showHistoryModal:     false,

        // Collapsible groups state
        collapsedGroups: {},
        toggleGroup(groupKey) {
            this.collapsedGroups = {
                ...this.collapsedGroups,
                [groupKey]: !this.collapsedGroups[groupKey]
            };
            localStorage.setItem('collapsedGroups', JSON.stringify(this.collapsedGroups));
        },
        isGroupCollapsed(groupKey) {
            return !!this.collapsedGroups[groupKey];
        },

        // History modal
        historyLogs: [],
        historyWebsiteName: '',
        historyType: '',
        canManageErrorHistory: @json(auth()->user()?->canApproveWebsiteQc() || auth()->user()?->canApproveWebsiteSupervisor()),
        showAttachmentPreview: false,
        previewFile: null,
        previewUrl: '',
        previewDownloadUrl: '',
        previewLoading: false,
        previewIsImage: false,
        previewZoom: 100,
        previewFitNonce: 0,
        previewImageNaturalWidth: 0,
        previewImageNaturalHeight: 0,
        previewTouchStartDistance: 0,
        previewTouchStartZoom: 100,
        previewPanX: 0,
        previewPanY: 0,
        previewPanStartX: 0,
        previewPanStartY: 0,
        previewPanOriginX: 0,
        previewPanOriginY: 0,
        previewIsPanning: false,
        showHistoryEditModal: false,
        historyEditLog: null,
        historyEditNote: '',
        historyEditRemoveIds: [],
        historyEditSelectedFileNames: [],

        // Delete class modal
        showDeleteClassModal: false,
        classToDelete: '',
        classToDeleteId: '',
        
        // Edit class state
        editingClass: null,
        editingClassName: '',
        classSearchQuery: '',

        // Progress modal
        progressModalTitle:   '',
        progressModalCurrent: 0,
        progressModalAction:  '',
        progressModalType:    'build',

        // QC modal
        qcModalName:   '',
        qcModalAction: '',

        // Supervisor modal
        supervisorModalName:   '',
        supervisorModalAction: '',

        // Maintenance modal
        maintenanceModalName:   '',
        maintenanceModalAction: '',

        init() {
            // Initialize collapsedGroups from localStorage
            try {
                this.collapsedGroups = JSON.parse(localStorage.getItem('collapsedGroups') || '{}');
            } catch (e) {
                this.collapsedGroups = {};
            }

            // Watch visibility states to save to localStorage
            this.$watch('showManageClassesModal', value => localStorage.setItem('showManageClassesModal', value));
            this.$watch('showManageMembersModal', value => {
                localStorage.setItem('showManageMembersModal', value);
                if (!value) {
                    this.selectedUserIds = [];
                    this.memberUserSearch = '';
                    this.isEditing = false;
                }
            });

            // Close modals on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (this.showDeleteClassModal) {
                        this.showDeleteClassModal = false;
                        this.classToDelete = '';
                        this.classToDeleteId = '';
                        return;
                    }
                    this.showCreateModal = false;
                    this.showProgressModal = false;
                    this.showQcModal = false;
                    this.showSupervisorModal = false;
                    this.showMaintenanceModal = false;
                    this.showFollowUpModal = false;
                    this.showEditFollowUpModal = false;
                    this.showHistoryModal = false;
                    this.showExportModal = false;
                    if (this.showManageMembersModal) {
                        this.showManageMembersModal = false;
                        this.selectedUserIds = [];
                        this.memberUserSearch = '';
                        this.isEditing = false;
                    }
                }
            });

            // Restore scroll position after a reload (e.g. form submission)
            const scrollPos = sessionStorage.getItem('websitesScrollPos');
            if (scrollPos) {
                setTimeout(() => {
                    window.scrollTo({ top: parseInt(scrollPos), behavior: 'instant' });
                }, 50);
                sessionStorage.removeItem('websitesScrollPos');
            }

            // Save scroll position on any form submit to preserve it across the reload
            document.addEventListener('submit', () => {
                sessionStorage.setItem('websitesScrollPos', window.scrollY);
            });
        },

        openProgressModal(websiteId, websiteName, currentPct, type) {
            this.progressModalTitle   = (type === 'maintenance' ? '🔧 Maintenance' : '📊 Build') + ' Progress: ' + websiteName;
            this.progressModalCurrent = currentPct;
            this.progressModalType    = type;
            if (type === 'maintenance') {
                this.progressModalAction = `/websites/${websiteId}/maintenance-progress`;
            } else {
                this.progressModalAction = `/websites/${websiteId}/progress`;
            }
            this.showProgressModal = true;
        },

        openQcModal(websiteId, websiteName) {
            this.qcModalName   = websiteName;
            this.qcModalAction = `/websites/${websiteId}/approve-qc`;
            this.showQcModal   = true;
        },

        openSupervisorModal(websiteId, websiteName) {
            this.supervisorModalName   = websiteName;
            this.supervisorModalAction = `/websites/${websiteId}/approve-supervisor`;
            this.showSupervisorModal   = true;
        },

        openMaintenanceModal(websiteId, websiteName) {
            this.maintenanceModalName   = websiteName;
            this.maintenanceModalAction = `/websites/${websiteId}/start-maintenance`;
            this.showMaintenanceModal   = true;
        },

        // Error Modals
        showQcErrorModal: false,
        qcErrorModalName: '',
        qcErrorModalAction: '',
        openQcErrorModal(websiteId, websiteName) {
            this.qcErrorModalName   = websiteName;
            this.qcErrorModalAction = `/websites/${websiteId}/qc-error`;
            this.showQcErrorModal   = true;
        },

        showSupervisorErrorModal: false,
        supervisorErrorModalName: '',
        supervisorErrorModalAction: '',
        openSupervisorErrorModal(websiteId, websiteName) {
            this.supervisorErrorModalName   = websiteName;
            this.supervisorErrorModalAction = `/websites/${websiteId}/supervisor-error`;
            this.showSupervisorErrorModal   = true;
        },

        showErrorProgressModal: false,
        errorProgressModalName: '',
        errorProgressModalAction: '',
        errorProgressModalCurrent: 0,
        openErrorProgressModal(websiteId, websiteName, currentPct) {
            this.errorProgressModalName    = websiteName;
            this.errorProgressModalAction  = `/websites/${websiteId}/error-progress`;
            this.errorProgressModalCurrent = currentPct;
            this.showErrorProgressModal    = true;
        },

        showEditModal: false,
        editModalAction: '',
        editForm: {
            name: '', url: '', category: '', logo_url: '', handled_by: '', start_date: '', deadline: '', notes: ''
        },
        openEditModal(id, data) {
            this.editModalAction = `/websites/${id}`;
            this.editForm = {
                name: data.name || '',
                url: data.url || '',
                category: data.category || '',
                logo_url: data.logo_url || '',
                handled_by: data.handled_by || '',
                start_date: data.start_date ? data.start_date.substring(0, 10) : '',
                deadline: data.deadline ? data.deadline.substring(0, 10) : '',
                notes: data.notes || ''
            };
            this.showEditModal = true;
        },

        openEditFollowUpModal(id, data) {
            const standardTypes = ['blog_post', 'indexed_page', 'website_page', 'other'];
            let formType = data.type || '';
            let customType = '';
            
            if (formType && !standardTypes.includes(formType)) {
                customType = formType;
                formType = 'other';
            }

            this.editFollowUpAction = `/websites/follow-ups/${id}`;
            this.editFollowUpForm = {
                website_id: data.website_id || '',
                type: formType,
                custom_type: customType,
                url: data.url || '',
                assigned_to: data.assigned_to || '',
                note: data.note || '',
                created_at: data.created_at || ''
            };
            this.showEditFollowUpModal = true;
        },

        openHistoryModal(websiteId, websiteName, type, logs) {
            this.historyWebsiteName = websiteName;
            this.historyType = type;
            let parsedLogs = Array.isArray(logs) ? logs : (typeof logs === 'string' ? JSON.parse(logs) : []);
            
            // Retrofit old logs that stored files as text "| File: filename.ext"
            parsedLogs = parsedLogs.map(log => {
                if (!log.attachment_path && log.note && log.note.includes(' | File: ')) {
                    const parts = log.note.split(' | File: ');
                    log.note = parts[0];
                    log.attachment_name = parts[1];
                    log.attachment_path = 'website-error-references/' + parts[1];
                }
                if (!Array.isArray(log.attachments)) {
                    log.attachments = [];
                }
                if (!log.attachments.length && log.attachment_path) {
                    log.attachments = [{
                        id: 'legacy',
                        path: log.attachment_path,
                        name: log.attachment_name || 'Attached File'
                    }];
                }
                return log;
            });

            // Sort logs by created_at descending just to be safe
            parsedLogs.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            this.historyLogs = parsedLogs;
            this.showHistoryModal = true;
        },

        // Returns storage URL - works with both relative paths and full URLs
        getStorageUrl(path) {
            if (!path) return '';
            if (path.startsWith('http://') || path.startsWith('https://')) return path;
            return '/storage/' + path;
        },

        getHistoryAttachmentUrl(log, action = 'view', file = null) {
            const target = file || (log?.attachments?.[0] ?? null);
            if (!log || !target?.path) return '';
            const fileQuery = target.id ? `?file=${encodeURIComponent(target.id)}` : '';
            if (log.id) return `{{ url('/websites/history-logs') }}/${log.id}/attachment/${action}${fileQuery}`;
            return this.getStorageUrl(target.path);
        },

        isImageAttachment(file) {
            return !!((file?.name || file?.path || '').match(/\.(jpeg|jpg|gif|png|webp)$/i));
        },

        isPdfAttachment(file) {
            return !!((file?.name || file?.path || '').match(/\.pdf$/i));
        },

        getAttachmentExtension(file) {
            const name = file?.name || file?.path || '';
            return name.includes('.') ? name.split('.').pop().toUpperCase() : 'FILE';
        },

        canManageLog(log) {
            return this.canManageErrorHistory && ['qc_error', 'supervisor_error'].includes(log?.action);
        },

        openAttachmentPreview(log, file) {
            this.previewFile = file;
            this.previewUrl = this.getHistoryAttachmentUrl(log, 'view', file);
            this.previewDownloadUrl = this.getHistoryAttachmentUrl(log, 'download', file);
            this.previewIsImage = this.isImageAttachment(file);
            this.previewZoom = 100;
            this.previewImageNaturalWidth = 0;
            this.previewImageNaturalHeight = 0;
            this.resetPreviewPan();
            this.previewLoading = true;
            this.showAttachmentPreview = true;
            this.$nextTick(() => this.refreshPreviewFit());
        },

        openGenericAttachmentPreview(name, viewUrl, downloadUrl) {
            this.previewFile = { name };
            this.previewUrl = viewUrl;
            this.previewDownloadUrl = downloadUrl;
            this.previewIsImage = !!((name || '').match(/\.(jpeg|jpg|gif|png|webp)$/i));
            this.previewZoom = 100;
            this.previewImageNaturalWidth = 0;
            this.previewImageNaturalHeight = 0;
            this.resetPreviewPan();
            this.previewLoading = true;
            this.showAttachmentPreview = true;
            this.$nextTick(() => this.refreshPreviewFit());
        },

        closeAttachmentPreview() {
            this.showAttachmentPreview = false;
            this.previewFile = null;
            this.previewUrl = '';
            this.previewDownloadUrl = '';
            this.previewLoading = false;
            this.previewIsImage = false;
            this.previewZoom = 100;
            this.previewImageNaturalWidth = 0;
            this.previewImageNaturalHeight = 0;
            this.previewTouchStartDistance = 0;
            this.resetPreviewPan();
        },

        setPreviewZoom(value) {
            this.previewZoom = Math.min(400, Math.max(60, Math.round(value)));
            if (this.previewZoom <= 100) {
                this.resetPreviewPan();
            }
            this.refreshPreviewFit();
        },

        resetPreviewZoom() {
            this.setPreviewZoom(100);
            this.resetPreviewPan();
        },

        handlePreviewWheel(event) {
            if (!this.previewIsImage) return;
            this.setPreviewZoom(this.previewZoom + (event.deltaY < 0 ? 4 : -4));
        },

        handlePreviewImageLoad(event) {
            this.previewImageNaturalWidth = event.target.naturalWidth || 0;
            this.previewImageNaturalHeight = event.target.naturalHeight || 0;
            this.previewLoading = false;
            this.refreshPreviewFit();
        },

        previewTouchDistance(event) {
            if (!event.touches || event.touches.length < 2) return 0;
            const [first, second] = event.touches;
            return Math.hypot(first.clientX - second.clientX, first.clientY - second.clientY);
        },

        handlePreviewTouchStart(event) {
            if (!this.previewIsImage) return;
            if (event.touches.length >= 2) {
                event.preventDefault();
                this.previewTouchStartDistance = this.previewTouchDistance(event);
                this.previewTouchStartZoom = this.previewZoom;
                this.previewIsPanning = false;
                return;
            }

            if (event.touches.length === 1 && this.previewZoom > 100) {
                const touch = event.touches[0];
                this.startPreviewPan(touch);
            }
        },

        handlePreviewTouchMove(event) {
            if (!this.previewIsImage) return;
            if (event.touches.length >= 2 && this.previewTouchStartDistance) {
                event.preventDefault();
                const distance = this.previewTouchDistance(event);
                this.setPreviewZoom(this.previewTouchStartZoom * (distance / this.previewTouchStartDistance));
                return;
            }

            if (event.touches.length === 1 && this.previewIsPanning) {
                event.preventDefault();
                this.movePreviewPan(event.touches[0]);
            }
        },

        resetPreviewPan() {
            this.previewPanX = 0;
            this.previewPanY = 0;
            this.previewPanStartX = 0;
            this.previewPanStartY = 0;
            this.previewPanOriginX = 0;
            this.previewPanOriginY = 0;
            this.previewIsPanning = false;
        },

        startPreviewPan(event) {
            if (!this.previewIsImage || this.previewZoom <= 100) return;
            this.previewIsPanning = true;
            this.previewPanStartX = event.clientX;
            this.previewPanStartY = event.clientY;
            this.previewPanOriginX = this.previewPanX;
            this.previewPanOriginY = this.previewPanY;
        },

        movePreviewPan(event) {
            if (!this.previewIsPanning || this.previewZoom <= 100) return;
            this.previewPanX = this.previewPanOriginX + (event.clientX - this.previewPanStartX);
            this.previewPanY = this.previewPanOriginY + (event.clientY - this.previewPanStartY);
        },

        endPreviewPan() {
            this.previewIsPanning = false;
        },

        refreshPreviewFit() {
            this.previewFitNonce += 1;
        },

        previewImageStyle() {
            this.previewFitNonce;
            const zoomScale = this.previewZoom / 100;

            return [
                `transform:translate3d(${this.previewPanX}px, ${this.previewPanY}px, 0) scale(${zoomScale})`,
            ].join(';') + ';';
        },

        openHistoryEditModal(log) {
            if (!this.canManageLog(log)) return;
            this.historyEditLog = log;
            this.historyEditNote = (log.note || '').replace(/\s*\|\s*Link:\s*https?:\/\/[^\s]*/gi, '').trim();
            this.historyEditRemoveIds = [];
            this.historyEditSelectedFileNames = [];
            this.showHistoryEditModal = true;
            this.$nextTick(() => {
                if (this.$refs.historyEditFiles) {
                    this.$refs.historyEditFiles.value = '';
                }
                this.$refs.historyEditPanel?.focus();
            });
        },

        closeHistoryEditModal() {
            this.showHistoryEditModal = false;
            this.historyEditLog = null;
            this.historyEditNote = '';
            this.historyEditRemoveIds = [];
            this.historyEditSelectedFileNames = [];
            if (this.$refs.historyEditFiles) {
                this.$refs.historyEditFiles.value = '';
            }
        },

        historyEditFileKey(file) {
            return file?.id || file?.path || 'legacy';
        },

        visibleHistoryEditAttachments() {
            const files = this.historyEditLog?.attachments || [];
            return files.filter((file) => !this.historyEditRemoveIds.includes(this.historyEditFileKey(file)));
        },

        removeHistoryEditFile(file) {
            const key = this.historyEditFileKey(file);
            if (!this.historyEditRemoveIds.includes(key)) {
                this.historyEditRemoveIds = [...this.historyEditRemoveIds, key];
            }
        },

        updateHistoryEditSelectedFiles() {
            this.historyEditSelectedFileNames = [...(this.$refs.historyEditFiles?.files || [])].map((file) => file.name);
        },

        // Strip "| Link: URL" from note for clean display, return just the text
        formatNoteText(note) {
            if (!note) return '';
            const cleaned = note.replace(/\s*\|\s*Link:\s*https?:\/\/[^\s]*/gi, '').trim();
            return cleaned.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        },

        // Extract URL from "| Link: URL" pattern in note
        extractLink(note) {
            if (!note) return null;
            const match = note.match(/\|\s*Link:\s*(https?:\/\/[^\s]*)/i);
            return match ? match[1] : null;
        },
        submitDynamicForm(action, method = 'POST', fields = {}, fileFields = {}) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = action;
            form.enctype = 'multipart/form-data';

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);

            if (method !== 'POST') {
                const methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                methodInput.value = method;
                form.appendChild(methodInput);
            }

            Object.entries({ ...fields, redirect_to: window.location.href }).forEach(([name, value]) => {
                const values = Array.isArray(value) ? value : [value];
                values.forEach((item) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = item ?? '';
                    form.appendChild(input);
                });
            });

            Object.entries(fileFields).forEach(([name, files]) => {
                [...files].forEach((file) => {
                    const fileInput = document.createElement('input');
                    fileInput.type = 'file';
                    fileInput.name = name;
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    fileInput.files = transfer.files;
                    form.appendChild(fileInput);
                });
            });

            document.body.appendChild(form);
            form.submit();
        },

        saveHistoryLogEdit() {
            const log = this.historyEditLog;
            if (!this.canManageLog(log)) return;
            const note = (this.historyEditNote || '').trim();
            if (note.length < 5) {
                alert('Please enter at least 5 characters.');
                return;
            }
            this.submitDynamicForm(
                `/websites/history-logs/${log.id}`,
                'PUT',
                {
                    note,
                    'remove_file_ids[]': this.historyEditRemoveIds,
                },
                { 'attachments[]': this.$refs.historyEditFiles?.files || [] }
            );
        },

        async deleteHistoryAttachment(log, file) {
            if (!this.canManageLog(log)) return;
            const ok = await window.confirmModal({
                title: 'Delete Attachment',
                message: 'Are you sure you want to delete this attached file? The history text will remain untouched.',
                confirmText: 'Delete Attachment',
                tone: 'danger'
            });
            if (ok) {
                this.submitDynamicForm(`/websites/history-logs/${log.id}/attachments/${encodeURIComponent(file?.id || 'legacy')}`, 'DELETE');
            }
        },
        formatStatusLabel(status) {
            const map = {
                'build': 'Build Progress',
                'qc_checking': 'QC Checking',
                'qc_error': 'QC Error',
                'supervisor_checking': 'Supervisor Checking',
                'supervisor_error': 'Supervisor Error',
                'live': 'Live',
                'maintenance': 'Maintenance',
                'maintenance_qc_checking': 'Maint. QC Check',
                'maintenance_qc_error': 'Maint. QC Error',
                'maintenance_supervisor_checking': 'Maint. Sup. Check',
                'maintenance_supervisor_error': 'Maint. Sup. Error'
            };
            return map[status] || status;
        },
    };
}
</script>
@endpush

@endsection
