@extends('layouts.app')
@section('title', 'Cross-Website Blog Performance & Analytics')
@section('page_title', 'Cross-Website Blog Performance & Analytics')

@section('content')
<style>
.blog-rank-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 9999px;
    font-size: 0.75rem; font-weight: 900;
}
.rank-gold   { background: #fef3c7; color: #b45309; border: 1.5px solid #fde68a; }
.rank-silver { background: #f1f5f9; color: #475569; border: 1.5px solid #cbd5e1; }
.rank-bronze { background: #ffedd5; color: #c2410c; border: 1.5px solid #fed7aa; }
.rank-normal { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

.nav-pill-btn {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 1rem; border-radius: 0.75rem;
    font-size: 0.875rem; font-weight: 700;
    transition: all 0.18s ease;
    border: 1.5px solid var(--border-color, #e2e8f0);
    background: var(--card-bg, #fff);
    color: var(--text-secondary, #64748b);
}
.nav-pill-btn:hover { border-color: #6366f1; color: #4f46e5; }
.nav-pill-btn.active {
    background: linear-gradient(135deg, #4f46e5, #3b82f6);
    color: #ffffff;
    border-color: transparent;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
}
[data-theme="dark"] .nav-pill-btn { background: #1e293b; border-color: #334155; color: #94a3b8; }
[data-theme="dark"] .nav-pill-btn:hover { border-color: #818cf8; color: #c7d2fe; }

.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.4); border-radius: 9999px; }
</style>

<div class="space-y-6 pb-12" x-data="{
    exportModalOpen: false,
    exportSiteId: '{{ $siteId ?? '' }}',
    exportCategory: '{{ $category ?? '' }}',
    exportMemberId: '{{ $memberId ?? '' }}',
    exportLimit: '10',
    exportDateFrom: '{{ $dateFrom ?? '' }}',
    exportDateTo: '{{ $dateTo ?? '' }}',
    exportSort: '{{ $sort ?? 'views_desc' }}',
    setDateRange(range) {
        const today = new Date();
        const pad = n => String(n).padStart(2, '0');
        const fmt = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
        if (range === 'all') {
            this.exportDateFrom = '';
            this.exportDateTo = '';
        } else if (range === 'today') {
            this.exportDateFrom = fmt(today);
            this.exportDateTo = fmt(today);
        } else if (range === '7days') {
            const p = new Date();
            p.setDate(p.getDate() - 7);
            this.exportDateFrom = fmt(p);
            this.exportDateTo = fmt(today);
        } else if (range === '30days') {
            const p = new Date();
            p.setDate(p.getDate() - 30);
            this.exportDateFrom = fmt(p);
            this.exportDateTo = fmt(today);
        } else if (range === 'this_month') {
            const f = new Date(today.getFullYear(), today.getMonth(), 1);
            this.exportDateFrom = fmt(f);
            this.exportDateTo = fmt(today);
        }
    },
    submitExport(format) {
        const form = this.$refs.exportReportForm;
        if (format === 'csv') {
            form.action = '{{ route('websites.blogs.export.csv') }}';
        } else {
            form.action = '{{ route('websites.blogs.export.pdf') }}';
        }
        form.submit();
        this.exportModalOpen = false;
    }
}" @keydown.escape.window="exportModalOpen = false">

    <!-- ── Top Navigation Bar ── -->
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200/80 dark:border-slate-800 pb-4">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('websites.dashboard') }}" class="nav-pill-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Executive Dashboard
            </a>
            @if(auth()->user()?->canAccessCentralConnectors())
            <a href="{{ route('websites.integrations.index') }}" class="nav-pill-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Central Connectors
            </a>
            @endif
            <a href="{{ route('websites.blogs.index') }}" class="nav-pill-btn active">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                Blog Performance & Analytics
            </a>
            <a href="{{ route('websites.search-console.index') }}" class="nav-pill-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Search Console
            </a>
            <a href="{{ route('websites.index') }}" class="nav-pill-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Lifecycle Workflow
            </a>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" @click="exportModalOpen = true" class="inline-flex items-center gap-2.5 px-4 py-2 text-xs font-black rounded-xl bg-gradient-to-r from-indigo-600 via-indigo-700 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white shadow-md hover:shadow-indigo-500/25 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Export Report</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] bg-white/20 font-bold uppercase tracking-wider">CSV • PDF</span>
            </button>
        </div>
    </div>

    <!-- ── Top 5 Blog Rankings Leaderboard ── -->
    @if($topRanked->isNotEmpty())
        <div>
            <h3 class="text-sm font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                Top Performing Blogs (Cross-Website Leaderboard)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                @foreach($topRanked as $index => $rankPost)
                    @php
                        $badgeClass = match($index) {
                            0 => 'rank-gold',
                            1 => 'rank-silver',
                            2 => 'rank-bronze',
                            default => 'rank-normal',
                        };
                    @endphp
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm relative overflow-hidden flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="blog-rank-badge {{ $badgeClass }}">#{{ $index + 1 }}</span>
                                <div class="flex items-center gap-1 flex-wrap justify-end">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                                        {{ $rankPost->website?->name }}
                                    </span>
                                    @if($rankPost->website?->category)
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                            {{ $rankPost->website->category }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <h4 class="mt-3 text-xs font-bold text-slate-900 dark:text-white line-clamp-2" title="{{ $rankPost->title }}">
                                {{ $rankPost->title }}
                            </h4>
                        </div>
                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                            @if($rankPost->website?->handler)
                                <div class="flex items-center gap-1.5 min-w-0" title="Handler: {{ $rankPost->website->handler->name }}">
                                    <img src="{{ $rankPost->website->handler->avatar_url }}" alt="{{ $rankPost->website->handler->name }}" class="w-4.5 h-4.5 rounded-full object-cover shrink-0 ring-1 ring-indigo-500/30" loading="lazy">
                                    <span class="text-[10px] font-semibold text-slate-600 dark:text-slate-300 truncate max-w-[85px]">{{ $rankPost->website->handler->name }}</span>
                                </div>
                            @else
                                <span class="text-xs text-slate-400">Total Views</span>
                            @endif
                            <span class="text-sm font-black text-slate-900 dark:text-white shrink-0">
                                {{ number_format($rankPost->cumulative_views) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- ── Filter & Search Bar ── -->
    <form method="GET" action="{{ route('websites.blogs.index') }}" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-wrap items-center gap-3">
        <!-- Search -->
        <div class="relative flex-1 min-w-[220px]">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search title, author, website, handler, class..."
                   class="w-full pl-9 pr-4 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>

        <!-- Searchable Website Dropdown with Handler & Class -->
        <div class="relative" x-data="{
            open: false,
            searchQuery: '',
            selectedId: '{{ $siteId }}',
            selectedName: '{{ $selectedWebsite ? addslashes($selectedWebsite->name) : 'All Websites' }}',
            selectedClass: '{{ $selectedWebsite ? addslashes($selectedWebsite->category ?: '') : '' }}',
            selectedHandler: '{{ $selectedWebsite && $selectedWebsite->handler ? addslashes($selectedWebsite->handler->name) : '' }}',
            selectedHandlerAvatar: '{{ $selectedWebsite && $selectedWebsite->handler ? addslashes($selectedWebsite->handler->avatar_url) : '' }}',
            items: [
                @foreach($allWebsites as $w)
                {
                    id: '{{ $w['id'] ?? $w->id }}',
                    name: '{{ addslashes($w['name'] ?? $w->name) }}',
                    category: '{{ addslashes(($w['category'] ?? $w->category) ?: 'Uncategorized') }}',
                    handler: '{{ addslashes(($w['handler'] ?? $w->handler?->name) ?: 'Unassigned') }}',
                    handler_avatar: '{{ addslashes($w->handler?->avatar_url ?? ($w['handler_avatar'] ?? '')) }}'
                },
                @endforeach
            ],
            get filtered() {
                if (!this.searchQuery.trim()) return this.items;
                const terms = this.searchQuery.toLowerCase().trim().split(/\s+/);
                return this.items.filter(item => {
                    const haystack = (item.name + ' ' + item.category + ' ' + item.handler).toLowerCase();
                    return terms.every(t => haystack.includes(t));
                });
            },
            select(site) {
                if (!site) {
                    this.selectedId = '';
                    this.selectedName = 'All Websites';
                    this.selectedClass = '';
                    this.selectedHandler = '';
                    this.selectedHandlerAvatar = '';
                } else {
                    this.selectedId = site.id;
                    this.selectedName = site.name;
                    this.selectedClass = site.category;
                    this.selectedHandler = site.handler;
                    this.selectedHandlerAvatar = site.handler_avatar;
                }
                this.open = false;
                this.$refs.hiddenInput.value = this.selectedId;
                this.$refs.hiddenInput.form.submit();
            }
        }" @keydown.window.escape="open = false">
            <!-- Hidden input for form submission -->
            <input type="hidden" name="website_id" x-ref="hiddenInput" value="{{ $siteId }}">

            <!-- Trigger Button -->
            <button type="button" @click="open = !open; if(open) $nextTick(() => $refs.searchInput.focus())"
                    class="flex items-center justify-between gap-2.5 px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:border-indigo-400 dark:hover:border-indigo-500 transition min-w-[200px] max-w-[320px]">
                <div class="flex items-center gap-2 overflow-hidden text-left">
                    <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    <div class="truncate">
                        <span class="font-bold text-xs" x-text="selectedName"></span>
                        <template x-if="selectedClass">
                            <span class="ml-1 px-1.5 py-0.2 text-[10px] font-bold rounded bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300" x-text="selectedClass"></span>
                        </template>
                        <template x-if="selectedHandler && selectedHandler !== 'Unassigned'">
                            <span class="ml-1 text-[11px] text-slate-500 dark:text-slate-400 font-medium inline-flex items-center gap-1">
                                <template x-if="selectedHandlerAvatar">
                                    <img :src="selectedHandlerAvatar" :alt="selectedHandler" class="w-3.5 h-3.5 rounded-full object-cover shrink-0 ring-1 ring-indigo-400/40">
                                </template>
                                <span x-text="'(' + selectedHandler + ')'"></span>
                            </span>
                        </template>
                    </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <template x-if="selectedId">
                        <span @click.stop="select(null)" title="Clear website filter" class="p-0.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </span>
                    </template>
                    <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </button>

            <!-- Dropdown Panel -->
            <div x-show="open" @click.outside="open = false" x-cloak
                 class="absolute left-0 z-50 mt-1.5 w-80 sm:w-96 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden ring-1 ring-black/5">
                <div class="p-2.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/50">
                    <div class="relative">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" x-ref="searchInput" x-model="searchQuery"
                               placeholder="Search website, handler, class..."
                               @keydown.escape="open = false"
                               class="w-full pl-9 pr-7 py-1.5 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <button type="button" x-show="searchQuery" @click="searchQuery = ''; $refs.searchInput.focus()" class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs">✕</button>
                    </div>
                    <div class="flex items-center justify-between mt-2 px-1 text-[11px] text-slate-400">
                        <span x-text="'Showing ' + filtered.length + ' of ' + items.length + ' websites'"></span>
                        <span class="text-[10px] uppercase font-bold text-slate-400">Name • Class • Handler</span>
                    </div>
                </div>

                <div class="max-h-72 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/50 p-1.5 custom-scrollbar">
                    <button type="button" @click="select(null)"
                            class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold transition flex items-center justify-between"
                            :class="!selectedId ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'">
                        <span class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                            All Websites (Reset filter)
                        </span>
                        <span class="text-[10px] text-slate-400" x-text="'(' + items.length + ')'"></span>
                    </button>

                    <template x-for="item in filtered" :key="item.id">
                        <button type="button" @click="select(item)"
                                class="w-full text-left px-3 py-2.5 rounded-xl transition flex flex-col gap-1 border-b border-slate-50 dark:border-slate-800/40 last:border-b-0"
                                :class="selectedId == item.id ? 'bg-indigo-50/90 dark:bg-indigo-950/80 text-indigo-900 dark:text-indigo-200' : 'hover:bg-slate-50 dark:hover:bg-slate-800/70'">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-extrabold text-xs text-slate-900 dark:text-white truncate" x-text="item.name"></span>
                                <span x-show="selectedId == item.id" class="text-indigo-600 dark:text-indigo-400 shrink-0 font-bold text-xs">✓</span>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap text-[11px]">
                                <span class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/50 dark:border-indigo-800/50" x-text="item.category"></span>
                                <template x-if="item.handler && item.handler !== 'Unassigned'">
                                    <span class="text-slate-600 dark:text-slate-300 flex items-center gap-1.5 font-medium">
                                        <template x-if="item.handler_avatar">
                                            <img :src="item.handler_avatar" :alt="item.handler" class="w-3.5 h-3.5 rounded-full object-cover shrink-0 ring-1 ring-slate-200 dark:ring-slate-700">
                                        </template>
                                        <span x-text="item.handler"></span>
                                    </span>
                                </template>
                            </div>
                        </button>
                    </template>

                    <div x-show="filtered.length === 0" class="py-6 text-center text-xs text-slate-400">
                        No websites match "<span class="font-bold text-slate-600 dark:text-slate-300" x-text="searchQuery"></span>"
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Dropdown -->
        <div class="relative" x-data="{
            open: false,
            selected: '{{ $category }}',
            categories: [
                @foreach($categories as $c)
                '{{ addslashes($c) }}',
                @endforeach
            ],
            select(cat) {
                this.selected = cat;
                this.open = false;
                this.$refs.catInput.value = cat;
                this.$refs.catInput.form.submit();
            }
        }" @keydown.window.escape="open = false">
            <input type="hidden" name="category" x-ref="catInput" value="{{ $category }}">
            <button type="button" @click="open = !open"
                    class="flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:border-indigo-400 dark:hover:border-indigo-500 transition min-w-[140px] max-w-[220px]">
                <div class="flex items-center gap-2 overflow-hidden text-left">
                    <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    <span class="font-bold text-xs truncate" x-text="selected ? selected : 'All Categories'"></span>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <template x-if="selected">
                        <span @click.stop="select('')" title="Clear category filter" class="p-0.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </span>
                    </template>
                    <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak
                 class="absolute left-0 z-50 mt-1.5 w-60 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden p-1.5 custom-scrollbar max-h-64 overflow-y-auto ring-1 ring-black/5">
                <button type="button" @click="select('')"
                        class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold transition flex items-center justify-between"
                        :class="!selected ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'">
                    <span>All Categories</span>
                    <span x-show="!selected" class="text-indigo-600 font-bold">✓</span>
                </button>
                <template x-for="cat in categories" :key="cat">
                    <button type="button" @click="select(cat)"
                            class="w-full text-left px-3 py-2 rounded-xl text-xs transition flex items-center justify-between"
                            :class="selected === cat ? 'bg-indigo-50/90 dark:bg-indigo-950/80 text-indigo-900 dark:text-indigo-200 font-bold' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/70 font-medium'">
                        <span x-text="cat"></span>
                        <span x-show="selected === cat" class="text-indigo-600 dark:text-indigo-400 font-bold">✓</span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Searchable Member Dropdown with Profile Image / Avatar -->
        <div class="relative" x-data="{
            open: false,
            searchQuery: '',
            selectedId: '{{ $memberId }}',
            selectedName: '{{ $selectedMember ? addslashes($selectedMember->name) : 'All Members' }}',
            selectedAvatar: '{{ $selectedMember ? addslashes($selectedMember->avatar_url) : '' }}',
            members: [
                @foreach($members as $m)
                {
                    id: '{{ $m->id }}',
                    name: '{{ addslashes($m->name) }}',
                    avatar: '{{ addslashes($m->avatar_url) }}',
                    role: '{{ addslashes($m->team_role ?? $m->crm_role ?? 'Team Member') }}'
                },
                @endforeach
            ],
            get filtered() {
                if (!this.searchQuery.trim()) return this.members;
                const q = this.searchQuery.toLowerCase().trim();
                return this.members.filter(m => m.name.toLowerCase().includes(q) || (m.role && m.role.toLowerCase().includes(q)));
            },
            select(m) {
                if (!m) {
                    this.selectedId = '';
                    this.selectedName = 'All Members';
                    this.selectedAvatar = '';
                } else {
                    this.selectedId = m.id;
                    this.selectedName = m.name;
                    this.selectedAvatar = m.avatar;
                }
                this.open = false;
                this.$refs.hiddenMemberInput.value = this.selectedId;
                this.$refs.hiddenMemberInput.form.submit();
            }
        }" @keydown.window.escape="open = false">
            <input type="hidden" name="member_id" x-ref="hiddenMemberInput" value="{{ $memberId }}">

            <!-- Trigger Button with User Avatar -->
            <button type="button" @click="open = !open; if(open) $nextTick(() => $refs.memberSearchInput.focus())"
                    class="flex items-center justify-between gap-2.5 px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:border-indigo-400 dark:hover:border-indigo-500 transition min-w-[170px] max-w-[260px]">
                <div class="flex items-center gap-2 overflow-hidden text-left">
                    <template x-if="selectedAvatar">
                        <img :src="selectedAvatar" :alt="selectedName" class="w-5 h-5 rounded-full object-cover shrink-0 ring-1.5 ring-indigo-500/40 shadow-xs">
                    </template>
                    <template x-if="!selectedAvatar">
                        <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </template>
                    <span class="font-bold text-xs truncate" x-text="selectedName"></span>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <template x-if="selectedId">
                        <span @click.stop="select(null)" title="Clear member filter" class="p-0.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </span>
                    </template>
                    <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </button>

            <!-- Dropdown Panel -->
            <div x-show="open" @click.outside="open = false" x-cloak
                 class="absolute left-0 z-50 mt-1.5 w-72 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden ring-1 ring-black/5">
                <div class="p-2.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/50">
                    <div class="relative">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" x-ref="memberSearchInput" x-model="searchQuery"
                               placeholder="Search member name or role..."
                               @keydown.escape="open = false"
                               class="w-full pl-9 pr-7 py-1.5 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <button type="button" x-show="searchQuery" @click="searchQuery = ''; $refs.memberSearchInput.focus()" class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs">✕</button>
                    </div>
                    <div class="flex items-center justify-between mt-2 px-1 text-[11px] text-slate-400">
                        <span x-text="'Showing ' + filtered.length + ' of ' + members.length + ' members'"></span>
                        <span class="text-[10px] uppercase font-bold text-slate-400">Avatar • Name • Role</span>
                    </div>
                </div>

                <div class="max-h-72 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/50 p-1.5 custom-scrollbar">
                    <button type="button" @click="select(null)"
                            class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold transition flex items-center justify-between"
                            :class="!selectedId ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'">
                        <span class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                            All Members (Reset)
                        </span>
                        <span class="text-[10px] text-slate-400" x-text="'(' + members.length + ')'"></span>
                    </button>

                    <template x-for="m in filtered" :key="m.id">
                        <button type="button" @click="select(m)"
                                class="w-full text-left px-3 py-2 rounded-xl transition flex items-center justify-between gap-2.5"
                                :class="selectedId == m.id ? 'bg-indigo-50/90 dark:bg-indigo-950/80 text-indigo-900 dark:text-indigo-200' : 'hover:bg-slate-50 dark:hover:bg-slate-800/70'">
                            <div class="flex items-center gap-2.5 overflow-hidden">
                                <img :src="m.avatar" :alt="m.name" class="w-7 h-7 rounded-full object-cover shrink-0 ring-1 ring-slate-200 dark:ring-slate-700 shadow-xs" loading="lazy">
                                <div class="truncate">
                                    <span class="font-extrabold text-xs text-slate-900 dark:text-white block truncate" x-text="m.name"></span>
                                    <span class="text-[10px] text-slate-500 dark:text-slate-400 block truncate" x-text="m.role || 'Team Member'"></span>
                                </div>
                            </div>
                            <span x-show="selectedId == m.id" class="text-indigo-600 dark:text-indigo-400 shrink-0 font-bold text-xs">✓</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Sort Dropdown -->
        <div class="relative" x-data="{
            open: false,
            selected: '{{ $sort }}',
            options: [
                { value: 'views_desc', label: 'Most Views' },
                { value: 'period_desc', label: 'Period Views (30d)' },
                { value: 'published_desc', label: 'Newest Published' },
                { value: 'published_asc', label: 'Oldest Published' }
            ],
            get currentLabel() {
                const found = this.options.find(o => o.value === this.selected);
                return found ? found.label : 'Most Views';
            },
            select(val) {
                this.selected = val;
                this.open = false;
                this.$refs.sortInput.value = val;
                this.$refs.sortInput.form.submit();
            }
        }" @keydown.window.escape="open = false">
            <input type="hidden" name="sort" x-ref="sortInput" value="{{ $sort }}">
            <button type="button" @click="open = !open"
                    class="flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:border-indigo-400 dark:hover:border-indigo-500 transition min-w-[155px]">
                <div class="flex items-center gap-2 overflow-hidden text-left">
                    <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
                    <span class="font-bold text-xs truncate" x-text="currentLabel"></span>
                </div>
                <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak
                 class="absolute right-0 z-50 mt-1.5 w-56 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden p-1.5 ring-1 ring-black/5">
                <template x-for="opt in options" :key="opt.value">
                    <button type="button" @click="select(opt.value)"
                            class="w-full text-left px-3 py-2 rounded-xl text-xs transition flex items-center justify-between"
                            :class="selected === opt.value ? 'bg-indigo-50 dark:bg-indigo-950/80 text-indigo-900 dark:text-indigo-200 font-bold' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/70 font-medium'">
                        <span x-text="opt.label"></span>
                        <span x-show="selected === opt.value" class="text-indigo-600 dark:text-indigo-400 font-bold">✓</span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Date Range -->
        <div class="flex items-center gap-1.5 text-xs text-slate-500 bg-slate-50 dark:bg-slate-800 px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700">
            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="bg-transparent text-xs text-slate-800 dark:text-slate-200 focus:outline-none">
            <span class="text-slate-400 font-bold">to</span>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="bg-transparent text-xs text-slate-800 dark:text-slate-200 focus:outline-none">
            <button type="submit" class="px-2 py-0.5 text-xs font-bold rounded-md bg-indigo-600 hover:bg-indigo-700 text-white transition">Go</button>
        </div>

        @if($search || $siteId || $category || $memberId || $dateFrom || $dateTo)
            <a href="{{ route('websites.blogs.index') }}" class="text-xs font-bold text-rose-500 hover:underline">
                Reset
            </a>
        @endif
    </form>

    <!-- ── Blog Posts Comparison Table ── -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700/80 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <th class="py-3.5 px-4">Post Title & URL</th>
                        <th class="py-3.5 px-3">Website, Class & Handler</th>
                        <th class="py-3.5 px-3">Author</th>
                        <th class="py-3.5 px-3">Published Date</th>
                        <th class="py-3.5 px-3">Cumulative Views</th>
                        <th class="py-3.5 px-3">Period Views (30d)</th>
                        <th class="py-3.5 px-3">GA4 Page Views</th>
                        <th class="py-3.5 px-3">Analytics Source</th>
                        <th class="py-3.5 px-4">Last Synced</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 text-sm">
                    @forelse($posts as $post)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                            <!-- Title & Canonical Link -->
                            <td class="py-3.5 px-4 max-w-[340px]">
                                <div class="font-bold text-slate-900 dark:text-white leading-snug line-clamp-2">
                                    {{ $post->title }}
                                </div>
                                <a href="{{ $post->canonical_url }}" target="_blank" rel="noopener noreferrer" class="text-xs text-slate-400 hover:text-indigo-500 transition inline-flex items-center gap-1 mt-0.5 truncate max-w-full">
                                    {{ $post->canonical_url }}
                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            </td>

                            <!-- Website, Class & Handler -->
                            <td class="py-3.5 px-3">
                                <div class="font-bold text-slate-900 dark:text-white leading-snug">
                                    {{ $post->website?->name }}
                                </div>
                                <div class="flex items-center gap-1.5 flex-wrap mt-1">
                                    @if($post->website?->category)
                                        <span class="inline-block px-1.5 py-0.5 text-[10px] font-bold rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/50 dark:border-indigo-800/50">
                                            {{ $post->website->category }}
                                        </span>
                                    @endif
                                    @if($post->website?->handler)
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-[11px] font-semibold text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700/80 shadow-2xs" title="Handler: {{ $post->website->handler->name }}">
                                            <img src="{{ $post->website->handler->avatar_url }}"
                                                 alt="{{ $post->website->handler->name }}"
                                                 class="w-4 h-4 rounded-full object-cover shrink-0 ring-1 ring-indigo-500/30"
                                                 loading="lazy">
                                            <span class="truncate max-w-[110px]">{{ $post->website->handler->name }}</span>
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Author -->
                            <td class="py-3.5 px-3 text-xs text-slate-700 dark:text-slate-300">
                                <div class="flex items-center gap-1.5 font-medium truncate max-w-[160px]" title="{{ $post->author_name ?: 'System' }}">
                                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    <span class="truncate">{{ $post->author_name ?: '-' }}</span>
                                </div>
                            </td>

                            <!-- Published Date -->
                            <td class="py-3.5 px-3 text-xs text-slate-600 dark:text-slate-400">
                                {{ $post->published_at ? $post->published_at->format('M d, Y') : '-' }}
                            </td>

                            <!-- Cumulative Views -->
                            <td class="py-3.5 px-3">
                                <span class="font-black text-slate-900 dark:text-white">
                                    {{ number_format($post->cumulative_views) }}
                                </span>
                            </td>

                            <!-- Period Views (30d) -->
                            <td class="py-3.5 px-3">
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ number_format($post->period_views) }}
                                </span>
                            </td>

                            <!-- GA4 Views -->
                            <td class="py-3.5 px-3 text-xs">
                                @if($post->page_views > 0)
                                    <span class="font-bold text-sky-600 dark:text-sky-400">{{ number_format($post->page_views) }}</span>
                                    <span class="text-[10px] text-slate-400 block">{{ number_format($post->unique_visitors) }} users</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>

                            <!-- Analytics Source -->
                            <td class="py-3.5 px-3">
                                @if($post->analytics_source === 'none')
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-[10px] font-bold rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700" title="WordPress website has no view counter plugin installed yet (0 views recorded)">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        No Tracker (0 Views)
                                    </span>
                                @elseif($post->analytics_source === 'ga4')
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-[10px] font-bold rounded-md bg-sky-50 dark:bg-sky-950/60 text-sky-600 border border-sky-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-400 animate-pulse"></span>
                                        GA4
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-[10px] font-bold rounded-md bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        {{ str_replace('_', ' ', $post->analytics_source) }}
                                    </span>
                                @endif
                            </td>

                            <!-- Last Synced -->
                            <td class="py-3.5 px-4 text-xs text-slate-400">
                                {{ $post->last_synced_at ? $post->last_synced_at->diffForHumans() : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-400">
                                No blog posts found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($posts->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                {{ $posts->links() }}
            </div>
        @endif
    </div>

    <!-- ── Export Report Modal (CSV & PDF with Custom Filters) ── -->
    <div x-show="exportModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-opacity">
        <div @click.outside="exportModalOpen = false"
             class="w-full max-w-xl bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden ring-1 ring-black/10 transform transition-all">
            
            <!-- Modal Header -->
            <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-start justify-between bg-gradient-to-r from-slate-50 to-indigo-50/40 dark:from-slate-800/50 dark:to-indigo-950/20">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-600/30 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">Export Blog Performance Report</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Customize date range, ranking limits, and filter criteria for CSV or PDF.</p>
                    </div>
                </div>
                <button type="button" @click="exportModalOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Modal Form -->
            <form x-ref="exportReportForm" method="GET" target="_blank" class="p-6 space-y-5">
                <input type="hidden" name="search" value="{{ $search }}">

                <!-- Date Range Section -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Custom Date Range (Published Date)
                        </label>
                    </div>

                    <!-- Preset Chips -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button type="button" @click="setDateRange('all')" class="px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-200 dark:border-slate-700 hover:border-indigo-500 transition text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400">All Time</button>
                        <button type="button" @click="setDateRange('today')" class="px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-200 dark:border-slate-700 hover:border-indigo-500 transition text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400">Today</button>
                        <button type="button" @click="setDateRange('7days')" class="px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-200 dark:border-slate-700 hover:border-indigo-500 transition text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400">Last 7 Days</button>
                        <button type="button" @click="setDateRange('30days')" class="px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-200 dark:border-slate-700 hover:border-indigo-500 transition text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400">Last 30 Days</button>
                        <button type="button" @click="setDateRange('this_month')" class="px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-200 dark:border-slate-700 hover:border-indigo-500 transition text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400">This Month</button>
                    </div>

                    <!-- Inputs -->
                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <div>
                            <span class="block text-[11px] font-semibold text-slate-400 mb-1">From Date</span>
                            <input type="date" name="date_from" x-model="exportDateFrom"
                                   class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <span class="block text-[11px] font-semibold text-slate-400 mb-1">To Date</span>
                            <input type="date" name="date_to" x-model="exportDateTo"
                                   class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                <!-- Ranking Limit / Total of Ranking -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            Total of Ranking (Top N Articles)
                        </label>
                        <span class="text-[11px] text-slate-400" x-text="exportLimit ? 'Exporting top ' + exportLimit + ' articles' : 'Exporting all matched articles'"></span>
                    </div>

                    <!-- Limit Preset Chips -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button type="button" @click="exportLimit = '10'" :class="exportLimit === '10' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'" class="px-3 py-1 text-[11px] font-extrabold rounded-lg border transition">Top 10</button>
                        <button type="button" @click="exportLimit = '20'" :class="exportLimit === '20' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'" class="px-3 py-1 text-[11px] font-extrabold rounded-lg border transition">Top 20</button>
                        <button type="button" @click="exportLimit = '50'" :class="exportLimit === '50' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'" class="px-3 py-1 text-[11px] font-extrabold rounded-lg border transition">Top 50</button>
                        <button type="button" @click="exportLimit = '100'" :class="exportLimit === '100' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'" class="px-3 py-1 text-[11px] font-extrabold rounded-lg border transition">Top 100</button>
                        <button type="button" @click="exportLimit = ''" :class="!exportLimit ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'" class="px-3 py-1 text-[11px] font-extrabold rounded-lg border transition">All (No Limit)</button>
                    </div>

                    <div class="relative pt-1">
                        <input type="number" name="limit" x-model="exportLimit" min="1" max="1000" placeholder="Custom ranking number, e.g. 10 or 20 (leave blank for all)"
                               class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Scope Filters Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <!-- Website Selection -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Website</label>
                        <select name="website_id" x-model="exportSiteId" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">All Websites</option>
                            @foreach($allWebsites as $w)
                                <option value="{{ $w->id }}">{{ $w->name }} ({{ $w->category ?: 'General' }}{{ $w->handler ? ' • ' . $w->handler->name : '' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Class Selection -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Class / Category</label>
                        <select name="category" x-model="exportCategory" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">All Classes</option>
                            @foreach($categories as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Member Selection -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Assigned Member (Handler)</label>
                        <select name="member_id" x-model="exportMemberId" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">All Members</option>
                            @foreach($members as $m)
                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Sort Selection -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Ranking Metric</label>
                        <select name="sort" x-model="exportSort" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="views_desc">Most Views (Cumulative Views)</option>
                            <option value="period_desc">Most Period Views (30 Days)</option>
                            <option value="published_desc">Newest Published</option>
                            <option value="published_asc">Oldest Published</option>
                        </select>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                    <button type="button" @click="exportModalOpen = false" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-bold text-xs hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                        Cancel
                    </button>
                    <div class="flex items-center gap-2.5">
                        <button type="button" @click="submitExport('csv')" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-2 transition shadow-sm hover:shadow-emerald-600/25">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Export CSV
                        </button>
                        <button type="button" @click="submitExport('pdf')" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center gap-2 transition shadow-md hover:shadow-indigo-600/30">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            Export PDF
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
