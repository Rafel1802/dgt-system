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

<div class="space-y-6 pb-12">

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
            <a href="{{ route('websites.blogs.export.csv', request()->query()) }}" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </a>
            <a href="{{ route('websites.blogs.export.pdf', request()->query()) }}" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Export PDF
            </a>
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
                            <span class="text-xs text-slate-400">Total Views</span>
                            <span class="text-sm font-black text-slate-900 dark:text-white">
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
            items: [
                @foreach($allWebsites as $w)
                {
                    id: '{{ $w->id }}',
                    name: '{{ addslashes($w->name) }}',
                    category: '{{ addslashes($w->category ?: 'Uncategorized') }}',
                    handler: '{{ addslashes($w->handler?->name ?: 'Unassigned') }}'
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
                } else {
                    this.selectedId = site.id;
                    this.selectedName = site.name;
                    this.selectedClass = site.category;
                    this.selectedHandler = site.handler;
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
                    class="flex items-center justify-between gap-2.5 px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:border-indigo-400 dark:hover:border-indigo-500 transition min-w-[220px] max-w-[340px]">
                <div class="flex items-center gap-2 overflow-hidden text-left">
                    <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    <div class="truncate">
                        <span class="font-bold text-xs" x-text="selectedName"></span>
                        <template x-if="selectedClass">
                            <span class="ml-1 px-1.5 py-0.2 text-[10px] font-bold rounded bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300" x-text="selectedClass"></span>
                        </template>
                        <template x-if="selectedHandler && selectedHandler !== 'Unassigned'">
                            <span class="ml-1 text-[11px] text-slate-500 dark:text-slate-400" x-text="'(' + selectedHandler + ')'"></span>
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
                
                <!-- Search Input Header -->
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

                <!-- List of Websites -->
                <div class="max-h-72 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/50 p-1.5 custom-scrollbar">
                    <!-- Option: All Websites -->
                    <button type="button" @click="select(null)"
                            class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold transition flex items-center justify-between"
                            :class="!selectedId ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'">
                        <span class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                            All Websites (Reset filter)
                        </span>
                        <span class="text-[10px] text-slate-400" x-text="'(' + items.length + ')'"></span>
                    </button>

                    <!-- Website Items -->
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
                                <span class="text-slate-600 dark:text-slate-300 flex items-center gap-1 font-medium">
                                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <span x-text="item.handler"></span>
                                </span>
                            </div>
                        </button>
                    </template>

                    <!-- Empty State -->
                    <div x-show="filtered.length === 0" class="py-6 text-center text-xs text-slate-400">
                        No websites match "<span class="font-bold text-slate-600 dark:text-slate-300" x-text="searchQuery"></span>"
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Dropdown -->
        <select name="category" onchange="this.form.submit()" class="px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
            <option value="">All Categories</option>
            @foreach($categories as $c)
                <option value="{{ $c }}" {{ $category === $c ? 'selected' : '' }}>{{ $c }}</option>
            @endforeach
        </select>

        <!-- Member Dropdown -->
        <select name="member_id" onchange="this.form.submit()" class="px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
            <option value="">All Members</option>
            @foreach($members as $m)
                <option value="{{ $m->id }}" {{ $memberId == $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
            @endforeach
        </select>

        <!-- Sort -->
        <select name="sort" onchange="this.form.submit()" class="px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
            <option value="views_desc" {{ $sort === 'views_desc' ? 'selected' : '' }}>Most Views</option>
            <option value="period_desc" {{ $sort === 'period_desc' ? 'selected' : '' }}>Most Period Views (30d)</option>
            <option value="published_desc" {{ $sort === 'published_desc' ? 'selected' : '' }}>Newest Published</option>
            <option value="published_asc" {{ $sort === 'published_asc' ? 'selected' : '' }}>Oldest Published</option>
        </select>

        <!-- Date Range -->
        <div class="flex items-center gap-1.5 text-xs text-slate-500">
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="px-2 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
            <span>to</span>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="px-2 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
            <button type="submit" class="px-2.5 py-1.5 text-xs font-bold rounded-lg bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200">Go</button>
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
                                <div class="font-bold text-slate-800 dark:text-slate-200">
                                    {{ $post->website?->name }}
                                </div>
                                <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                    <span class="inline-block px-1.5 py-0.2 text-[10px] font-bold rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/50 dark:border-indigo-800/50">
                                        {{ $post->website?->category ?: '-' }}
                                    </span>
                                    @if($post->website?->handler)
                                        <span class="inline-flex items-center gap-1 text-[11px] text-slate-500 dark:text-slate-400 font-medium">
                                            <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            {{ $post->website->handler->name }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Author -->
                            <td class="py-3.5 px-3 text-xs text-slate-600 dark:text-slate-400">
                                {{ $post->author_name ?: '-' }}
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
                                <span class="inline-block px-2 py-0.5 text-[10px] font-bold rounded-md uppercase tracking-wider
                                    {{ $post->analytics_source === 'ga4' ? 'bg-sky-50 dark:bg-sky-950/60 text-sky-600 border border-sky-200' :
                                       ($post->analytics_source !== 'none' ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 border border-emerald-200' : 'bg-slate-100 text-slate-500') }}">
                                    {{ str_replace('_', ' ', $post->analytics_source) }}
                                </span>
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

</div>
@endsection
