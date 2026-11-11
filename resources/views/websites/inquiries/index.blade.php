@extends('layouts.app')
@section('title', 'Centralized Website Inquiries')
@section('page_title', 'Centralized Website Inquiries')

@section('content')
<style>
/* ── Navigation Pill Buttons ── */
.nav-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1.15rem;
    border-radius: 0.85rem;
    font-size: 0.85rem;
    font-weight: 700;
    line-height: 1.25;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1.5px solid #cbd5e1;
    background: #ffffff;
    color: #1e293b;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
}
.nav-pill-btn svg {
    width: 1rem;
    height: 1rem;
    color: #475569;
    flex-shrink: 0;
    transition: all 0.2s ease;
}
.nav-pill-btn:hover {
    border-color: #6366f1;
    background: #f8faff;
    color: #4338ca;
    box-shadow: 0 3px 10px rgba(99, 102, 241, 0.15);
    transform: translateY(-1px);
}
.nav-pill-btn:hover svg {
    color: #4f46e5;
    transform: scale(1.08);
}
.nav-pill-btn.active {
    background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%) !important;
    color: #ffffff !important;
    border-color: transparent !important;
    box-shadow: 0 4px 16px rgba(79, 70, 229, 0.45) !important;
}
.nav-pill-btn.active svg {
    color: #ffffff !important;
}
[data-theme="dark"] .nav-pill-btn,
html.dark .nav-pill-btn {
    background: #1e293b !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35) !important;
}
[data-theme="dark"] .nav-pill-btn:hover,
html.dark .nav-pill-btn:hover {
    background: #27354a !important;
    border-color: #6366f1 !important;
    color: #a5b4fc !important;
}

/* ── Live Pulse & Animations ── */
@keyframes highlightNewRow {
    0% { background-color: rgba(99, 102, 241, 0.25); }
    100% { background-color: transparent; }
}
.new-inquiry-row {
    animation: highlightNewRow 4s ease-out forwards;
}
</style>

<div x-data="inquiryDashboard()" x-init="init()" class="space-y-6 pb-12">
    {{-- ── Top Navigation Bar Tabs ── --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
        <a href="{{ route('websites.index', ['tab' => 'build']) }}" class="nav-pill-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m9 10 2 2 4-4" />
                <rect width="20" height="14" x="2" y="3" rx="2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 17v4" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8" />
            </svg>
            <span>Website Status</span>
        </a>
        <a href="{{ route('websites.search-console.index') }}" class="nav-pill-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
            </svg>
            <span>Google Search Console</span>
        </a>
        @if(auth()->user()?->canAccessCentralConnectors())
        <a href="{{ route('websites.integrations.index') }}" class="nav-pill-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            <span>Central Connectors</span>
        </a>
        @endif
        <a href="{{ route('websites.blogs.index') }}" class="nav-pill-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
            </svg>
            <span>Blog Analytics</span>
        </a>
        <a href="{{ route('websites.posts.index') }}" class="nav-pill-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
            </svg>
            <span>Blog Post</span>
        </a>
        <a href="{{ route('websites.index', ['tab' => 'follow-up']) }}" class="nav-pill-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="m9 14 2 2 4-4" />
            </svg>
            <span>Follow Up</span>
        </a>
        <a href="{{ route('websites.inquiries.index') }}" class="nav-pill-btn active">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
            </svg>
            <span>Inquiries</span>
            @if($stats['unread'] > 0)
                <span class="ml-1 px-1.5 py-0.5 text-[10px] font-black rounded-full bg-white/20 text-white" x-text="stats.unread">
                    {{ $stats['unread'] }}
                </span>
            @endif
        </a>
    </div>

    {{-- ── Header & Action Bar ── --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-slate-800/80 p-5 rounded-2xl border border-slate-200 dark:border-slate-700/60 shadow-sm backdrop-blur">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                        <span>Centralized Website Inquiries</span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Live Pusher Feed</span>
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Capturing quotes, contact requests, and form submissions across all 49 integrated websites.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <a href="{{ route('websites.inquiries.export.csv', request()->all()) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/80 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors border border-slate-300/70 dark:border-slate-600 shadow-sm"
               title="Export filtered inquiries as CSV">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                <span>Export CSV</span>
            </a>

            <button type="button" @click="triggerManualSync()"
                    :disabled="isSyncing"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 transition-colors border border-indigo-200 dark:border-indigo-800 shadow-sm cursor-pointer disabled:opacity-50">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncing }">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <span x-text="isSyncing ? 'Syncing...' : 'Sync Inquiries'">Sync Inquiries</span>
            </button>

            <button type="button" @click="resetFilters()"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/80 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors border border-slate-300/70 dark:border-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <span>Reset</span>
            </button>
        </div>
    </div>

    {{-- ── Metric KPI Cards ── --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3.5">
        {{-- Card 1: Total Inquiries --}}
        <div class="bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200 dark:border-slate-700/60 shadow-sm relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Inquiries</span>
                <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white" x-text="stats.total">
                {{ number_format($stats['total']) }}
            </div>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Across all websites</p>
        </div>

        {{-- Card 2: New & Unread --}}
        <div class="bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-indigo-200 dark:border-indigo-900/60 shadow-sm relative overflow-hidden group ring-1 ring-indigo-500/20">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">New & Unread</span>
                <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-indigo-600"></span>
                    </span>
                </div>
            </div>
            <div class="mt-2 text-2xl font-black text-indigo-600 dark:text-indigo-400" x-text="stats.unread">
                {{ number_format($stats['unread']) }}
            </div>
            <p class="text-[11px] text-indigo-500/80 dark:text-indigo-400/70 mt-1">Needs attention</p>
        </div>

        {{-- Card 3: In Progress / Pending --}}
        <div class="bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200 dark:border-slate-700/60 shadow-sm relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">In Progress</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-2 text-2xl font-black text-amber-600 dark:text-amber-400" x-text="stats.in_progress">
                {{ number_format($stats['in_progress']) }}
            </div>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Assigned or active</p>
        </div>

        {{-- Card 4: Resolved / Closed --}}
        <div class="bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200 dark:border-slate-700/60 shadow-sm relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Resolved</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-2 text-2xl font-black text-emerald-600 dark:text-emerald-400" x-text="stats.resolved">
                {{ number_format($stats['resolved']) }}
            </div>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Successfully handled</p>
        </div>

        {{-- Card 5: Spam / Filtered --}}
        <div class="bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200 dark:border-slate-700/60 shadow-sm relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-rose-500 dark:text-rose-400 uppercase tracking-wider">Spam Filtered</span>
                <div class="w-8 h-8 rounded-lg bg-rose-50 dark:bg-rose-950/40 text-rose-500 dark:text-rose-400 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-2 text-2xl font-black text-rose-500 dark:text-rose-400" x-text="stats.spam">
                {{ number_format($stats['spam']) }}
            </div>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Blocked honeypots</p>
        </div>
    </div>

    {{-- ── Filter Bar ── --}}
    <div class="bg-white dark:bg-slate-800/90 p-4 rounded-2xl border border-slate-200 dark:border-slate-700/60 shadow-sm space-y-3">
        <form method="GET" action="{{ route('websites.inquiries.index') }}" id="filterForm" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
            {{-- Website Selector --}}
            <div class="md:col-span-3">
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Source Website</label>
                <select name="website_id" onchange="this.form.submit()" class="w-full text-xs font-semibold rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                    <option value="">All Websites ({{ $websites->count() }})</option>
                    @foreach($websites as $ws)
                        <option value="{{ $ws->id }}" {{ (string)$websiteId === (string)$ws->id ? 'selected' : '' }}>
                            {{ $ws->name }} ({{ str_replace(['https://', 'http://', 'www.'], '', rtrim($ws->url, '/')) }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Status Filter --}}
            <div class="md:col-span-2">
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Status</label>
                <select name="status" onchange="this.form.submit()" class="w-full text-xs font-semibold rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                    <option value="all" {{ $status === 'all' || !$status ? 'selected' : '' }}>All Statuses</option>
                    <option value="unread" {{ $status === 'unread' ? 'selected' : '' }}>Unread Only</option>
                    @foreach($statuses as $k => $label)
                        <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Inquiry Type --}}
            <div class="md:col-span-2">
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Inquiry Type</label>
                <select name="type" onchange="this.form.submit()" class="w-full text-xs font-semibold rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                    <option value="all" {{ $type === 'all' || !$type ? 'selected' : '' }}>All Types</option>
                    @foreach($types as $k => $label)
                        <option value="{{ $k }}" {{ $type === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Date Range --}}
            <div class="md:col-span-2">
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Date Range</label>
                <select name="date_range" onchange="this.form.submit()" class="w-full text-xs font-semibold rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                    <option value="all" {{ $dateRange === 'all' ? 'selected' : '' }}>All Time</option>
                    <option value="today" {{ $dateRange === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="yesterday" {{ $dateRange === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                    <option value="last_7_days" {{ $dateRange === 'last_7_days' ? 'selected' : '' }}>Last 7 Days</option>
                    <option value="last_30_days" {{ $dateRange === 'last_30_days' ? 'selected' : '' }}>Last 30 Days</option>
                    <option value="this_month" {{ $dateRange === 'this_month' ? 'selected' : '' }}>This Month</option>
                </select>
            </div>

            {{-- Search Input --}}
            <div class="md:col-span-3">
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Search Details</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search customer, email, subject..."
                           class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 pl-8 pr-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
            </div>
        </form>
    </div>

    {{-- ── Inquiries Table ── --}}
    <div class="bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200 dark:border-slate-700/60 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/60 text-left text-xs">
                <thead class="bg-slate-50/80 dark:bg-slate-900/40 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-2">Status</th>
                        <th scope="col" class="py-3.5 px-3">Source Website</th>
                        <th scope="col" class="py-3.5 px-3">Customer</th>
                        <th scope="col" class="py-3.5 px-3">Type & Subject</th>
                        <th scope="col" class="py-3.5 px-3">Assigned</th>
                        <th scope="col" class="py-3.5 px-3">Submitted</th>
                        <th scope="col" class="py-3.5 pr-4 pl-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="inquiriesTableBody" class="divide-y divide-slate-100 dark:divide-slate-700/40 text-slate-700 dark:text-slate-200 font-medium">
                    @forelse($inquiries as $inquiry)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors {{ !$inquiry->is_read ? 'bg-indigo-50/20 dark:bg-indigo-950/10' : '' }}"
                            id="inquiry-row-{{ $inquiry->id }}"
                            @click="openInquiry({{ $inquiry->id }})"
                            role="button"
                            tabindex="0">
                            {{-- Status Badge --}}
                            <td class="py-3 pl-4 pr-2 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $inquiry->status_badge_class }}">
                                    @if(!$inquiry->is_read && $inquiry->status !== 'spam')
                                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                    @endif
                                    {{ $inquiry->status_label }}
                                </span>
                            </td>

                            {{-- Website --}}
                            <td class="py-3 px-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-md bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-600 dark:text-slate-300">
                                        {{ strtoupper(substr($inquiry->website?->name ?? 'W', 0, 1)) }}
                                    </div>
                                    <div class="truncate max-w-[150px]">
                                        <div class="font-bold text-slate-900 dark:text-white truncate" title="{{ $inquiry->website?->name }}">
                                            {{ $inquiry->website?->name ?? 'Unknown Website' }}
                                        </div>
                                        <a href="{{ $inquiry->website?->url }}" target="_blank" @click.stop class="text-[10px] text-slate-400 hover:text-indigo-600 truncate block">
                                            {{ str_replace(['http://', 'https://'], '', rtrim($inquiry->website?->url ?? '', '/')) }}
                                        </a>
                                    </div>
                                </div>
                            </td>

                            {{-- Customer --}}
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900 dark:text-white">
                                    {{ $inquiry->customer_name ?: 'Website Visitor' }}
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-2 flex-wrap">
                                    @if($inquiry->customer_email)
                                        <span class="inline-flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 text-slate-400">
                                                <path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z" />
                                                <path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z" />
                                            </svg>
                                            {{ $inquiry->customer_email }}
                                        </span>
                                    @endif
                                    @if($inquiry->customer_phone)
                                        <span class="inline-flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 text-slate-400">
                                                <path fill-rule="evenodd" d="M2 3.5A1.5 1.5 0 0 1 3.5 2h1.148a1.5 1.5 0 0 1 1.465 1.175l.716 3.223a1.5 1.5 0 0 1-1.052 1.767l-.933.267c-.41.117-.643.555-.48.95a11.542 11.542 0 0 0 6.254 6.254c.395.163.833-.07.95-.48l.267-.933a1.5 1.5 0 0 1 1.767-1.052l3.223.716A1.5 1.5 0 0 1 18 15.352V16.5a1.5 1.5 0 0 1-1.5 1.5H15c-1.149 0-2.263-.15-3.326-.43A13.022 13.022 0 0 1 2.43 8.326 13.019 13.019 0 0 1 2 5V3.5Z" clip-rule="evenodd" />
                                            </svg>
                                            {{ $inquiry->customer_phone }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Type & Subject --}}
                            <td class="py-3 px-3 max-w-[280px]">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        {{ $inquiry->type_label }}
                                    </span>
                                    @if($inquiry->product_service)
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                            {{ $inquiry->product_service }}
                                        </span>
                                    @endif
                                </div>
                                <div class="font-semibold text-slate-800 dark:text-slate-200 mt-1 truncate" title="{{ $inquiry->subject }}">
                                    {{ $inquiry->subject ?: 'No Subject' }}
                                </div>
                                <div class="text-[11px] text-slate-400 dark:text-slate-500 truncate" title="{{ $inquiry->message }}">
                                    {{ Str::limit($inquiry->message, 80) }}
                                </div>
                            </td>

                            {{-- Assigned --}}
                            <td class="py-3 px-3 whitespace-nowrap">
                                @if($inquiry->assignee)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                        {{ $inquiry->assignee->name }}
                                    </span>
                                @else
                                    <span class="text-[10px] text-slate-400 italic">Unassigned</span>
                                @endif
                            </td>

                            {{-- Submitted --}}
                            <td class="py-3 px-3 whitespace-nowrap text-slate-500 dark:text-slate-400 text-[11px]">
                                <div>{{ $inquiry->submitted_at?->diffForHumans() ?? 'N/A' }}</div>
                                <div class="text-[10px] text-slate-400">{{ $inquiry->submitted_at?->format('M d, Y H:i') }}</div>
                            </td>

                            {{-- Actions --}}
                            <td class="py-3 pr-4 pl-2 text-right whitespace-nowrap" @click.stop>
                                <button type="button" @click="openInquiry({{ $inquiry->id }})"
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 dark:bg-indigo-950/50 dark:hover:bg-indigo-900 dark:text-indigo-400 transition-colors">
                                    View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-700/60 flex items-center justify-center text-slate-400 mb-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                        </svg>
                                    </div>
                                    <p class="font-bold text-slate-700 dark:text-slate-300">No customer inquiries found</p>
                                    <p class="text-xs text-slate-400 mt-1">Try resetting your filters or submit a test inquiry using the connector.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($inquiries->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-700/60">
                {{ $inquiries->links() }}
            </div>
        @endif
    </div>

    {{-- ── Inquiry Detail Modal / Slide-over Drawer ── --}}
    <div x-show="drawerOpen" x-cloak class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        {{-- Backdrop --}}
        <div x-show="drawerOpen"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="closeDrawer()"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>

        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div x-show="drawerOpen"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-3xl border border-slate-200 dark:border-slate-700">

                    {{-- Drawer Loading State --}}
                    <div x-show="loadingInquiry" class="p-12 text-center">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-indigo-500 border-t-transparent"></div>
                        <p class="mt-2 text-xs font-semibold text-slate-500 dark:text-slate-400">Loading inquiry details...</p>
                    </div>

                    {{-- Drawer Content --}}
                    <div x-show="!loadingInquiry && activeInquiry" class="p-6 space-y-6">
                        {{-- Top Header --}}
                        <div class="flex items-start justify-between pb-4 border-b border-slate-200 dark:border-slate-700/60">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800"
                                          x-text="activeInquiry ? activeInquiry.type_label : ''"></span>
                                    <span class="text-xs text-slate-400" x-text="activeInquiry ? activeInquiry.submitted_at : ''"></span>
                                </div>
                                <h3 class="text-lg font-black text-slate-900 dark:text-white mt-1.5" x-text="activeInquiry ? (activeInquiry.subject || 'Website Inquiry') : ''"></h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-1.5">
                                    <span>From website:</span>
                                    <span class="font-bold text-slate-700 dark:text-slate-300" x-text="activeInquiry?.website?.name"></span>
                                    <span class="text-slate-400">&bull;</span>
                                    <a :href="activeInquiry?.source_url" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                                        <span x-text="activeInquiry?.source_url"></span>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                                            <path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 12.75 17h-8.5A2.25 2.25 0 0 1 2 14.75v-8.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Z" clip-rule="evenodd" />
                                            <path fill-rule="evenodd" d="M6.194 12.753a.75.75 0 0 0 1.06.053L16.5 4.44v2.81a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.553l-9.056 8.194a.75.75 0 0 0-.053 1.06Z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                </p>
                            </div>
                            <button type="button" @click="closeDrawer()" class="text-slate-400 hover:text-slate-500 rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-slate-700">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- Customer Info Cards Grid --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Customer Name</span>
                                <div class="text-sm font-bold text-slate-900 dark:text-white mt-0.5" x-text="activeInquiry?.customer_name || 'N/A'"></div>
                                <div class="text-xs text-slate-500" x-text="activeInquiry?.customer_company || ''"></div>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Email Address</span>
                                <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 mt-0.5 truncate">
                                    <a :href="'mailto:' + activeInquiry?.customer_email" x-text="activeInquiry?.customer_email || 'Not provided'"></a>
                                </div>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Phone / Contact</span>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                                    <a :href="'tel:' + activeInquiry?.customer_phone" x-text="activeInquiry?.customer_phone || 'Not provided'"></a>
                                </div>
                            </div>
                        </div>

                        {{-- Message Body --}}
                        <div class="bg-slate-50/60 dark:bg-slate-900/40 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Inquiry Message</span>
                            <div class="text-xs leading-relaxed text-slate-800 dark:text-slate-200 whitespace-pre-wrap font-sans" x-text="activeInquiry?.message || 'No message provided.'"></div>
                        </div>

                        {{-- Custom Fields (if any) --}}
                        <template x-if="activeInquiry && activeInquiry.custom_fields && Object.keys(activeInquiry.custom_fields).length > 0">
                            <div>
                                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-2">Submitted Form Fields</span>
                                <div class="grid grid-cols-2 gap-2 bg-slate-50 dark:bg-slate-900/40 p-3 rounded-xl border border-slate-200 dark:border-slate-700">
                                    <template x-for="(val, key) in activeInquiry.custom_fields" :key="key">
                                        <div class="text-xs">
                                            <span class="text-slate-400 font-semibold" x-text="key + ':'"></span>
                                            <span class="text-slate-700 dark:text-slate-200 font-bold ml-1" x-text="typeof val === 'object' ? JSON.stringify(val) : val"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Workflow Actions (Status & Assignment) --}}
                        <div class="bg-indigo-50/30 dark:bg-indigo-950/20 p-4 rounded-xl border border-indigo-100 dark:border-indigo-900/40 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Update Status</label>
                                <select @change="updateActiveStatus($event.target.value)" class="w-full text-xs font-semibold rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 py-2">
                                    @foreach($statuses as $k => $label)
                                        <option value="{{ $k }}" :selected="activeInquiry?.status === '{{ $k }}'">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Assign Team Member</label>
                                <select @change="assignActiveInquiry($event.target.value)" class="w-full text-xs font-semibold rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 py-2">
                                    <option value="">Unassigned</option>
                                    @foreach($teamMembers as $member)
                                        <option value="{{ $member->id }}" :selected="activeInquiry?.assigned_to == {{ $member->id }}">{{ $member->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Activity Log & Note Taking --}}
                        <div class="space-y-3 pt-2">
                            <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Activity Log & Internal Notes</span>

                            {{-- Add Note Form --}}
                            <div class="flex gap-2">
                                <input type="text" x-model="newNoteText" @keydown.enter.prevent="addActiveNote()" placeholder="Add internal note or follow-up status..."
                                       class="flex-1 text-xs rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 px-3 py-2">
                                <button type="button" @click="addActiveNote()" class="px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">
                                    Add Note
                                </button>
                            </div>

                            {{-- Activity Timeline --}}
                            <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                                <template x-for="act in activeInquiry?.activities" :key="act.id">
                                    <div class="text-xs p-2.5 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200/60 dark:border-slate-700/60 flex items-start justify-between gap-2">
                                        <div>
                                            <span class="font-bold text-slate-800 dark:text-slate-200" x-text="act.user ? act.user.name : 'System'"></span>
                                            <span class="text-slate-600 dark:text-slate-300 ml-1" x-text="act.description"></span>
                                        </div>
                                        <span class="text-[10px] text-slate-400 whitespace-nowrap" x-text="new Date(act.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function inquiryDashboard() {
    return {
        stats: @json($stats),
        drawerOpen: false,
        loadingInquiry: false,
        activeInquiry: null,
        newNoteText: '',
        isSyncing: false,

        init() {
            // Connect to real-time Pusher channel
            this.setupPusherListener();
        },

        triggerManualSync() {
            if (this.isSyncing) return;
            this.isSyncing = true;

            fetch('{{ route('websites.inquiries.sync') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.message) {
                    window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: data.message, type: data.synced > 0 ? 'success' : 'info' } }));
                }
                setTimeout(() => {
                    window.location.reload();
                }, 800);
            })
            .catch(() => {
                window.location.reload();
            })
            .finally(() => {
                this.isSyncing = false;
            });
        },

        setupPusherListener() {
            const pusher = window.kiuqGetPusherClient?.();
            if (!pusher) return;

            const channel = pusher.subscribe('private-inquiries');
            channel.bind('inquiry.received', (data) => {
                this.handleIncomingInquiry(data);
            });
        },

        handleIncomingInquiry(inquiry) {
            // Update stats
            this.stats.total++;
            this.stats.unread++;

            // Create and prepend new row to table
            const tbody = document.getElementById('inquiriesTableBody');
            if (tbody) {
                const tr = document.createElement('tr');
                tr.id = 'inquiry-row-' + inquiry.id;
                tr.className = 'new-inquiry-row hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors bg-indigo-50/20 dark:bg-indigo-950/10 cursor-pointer';
                tr.onclick = () => this.openInquiry(inquiry.id);

                tr.innerHTML = `
                    <td class="py-3 pl-4 pr-2 whitespace-nowrap">
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border bg-blue-100 text-blue-800 border-blue-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                            New
                        </span>
                    </td>
                    <td class="py-3 px-3 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-md bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-600 dark:text-slate-300">
                                ${(inquiry.website_name || 'W').charAt(0).toUpperCase()}
                            </div>
                            <div class="truncate max-w-[150px]">
                                <div class="font-bold text-slate-900 dark:text-white truncate">${inquiry.website_name}</div>
                                <span class="text-[10px] text-slate-400">${inquiry.website_domain}</span>
                            </div>
                        </div>
                    </td>
                    <td class="py-3 px-3">
                        <div class="font-bold text-slate-900 dark:text-white">${inquiry.customer_name}</div>
                        <div class="text-[11px] text-slate-500">${inquiry.customer_email || ''}</div>
                    </td>
                    <td class="py-3 px-3 max-w-[280px]">
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-slate-100 text-slate-600">
                            ${inquiry.type_label}
                        </span>
                        <div class="font-semibold text-slate-800 dark:text-slate-200 mt-1 truncate">${inquiry.subject || 'Website Inquiry'}</div>
                        <div class="text-[11px] text-slate-400 truncate">${inquiry.message_snippet || ''}</div>
                    </td>
                    <td class="py-3 px-3 whitespace-nowrap">
                        <span class="text-[10px] text-slate-400 italic">Unassigned</span>
                    </td>
                    <td class="py-3 px-3 whitespace-nowrap text-slate-500 text-[11px]">
                        Just now
                    </td>
                    <td class="py-3 pr-4 pl-2 text-right whitespace-nowrap">
                        <button type="button" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600">View</button>
                    </td>
                `;

                tbody.insertBefore(tr, tbody.firstChild);
            }
        },

        openInquiry(id) {
            this.drawerOpen = true;
            this.loadingInquiry = true;

            fetch(`/websites/inquiries/${id}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.activeInquiry = data.inquiry;
                }
            })
            .finally(() => {
                this.loadingInquiry = false;
            });
        },

        closeDrawer() {
            this.drawerOpen = false;
            this.activeInquiry = null;
            this.newNoteText = '';
        },

        updateActiveStatus(status) {
            if (!this.activeInquiry) return;

            fetch(`/websites/inquiries/${this.activeInquiry.id}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({ status })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.activeInquiry = data.inquiry;
                }
            });
        },

        assignActiveInquiry(userId) {
            if (!this.activeInquiry) return;

            fetch(`/websites/inquiries/${this.activeInquiry.id}/assign`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({ user_id: userId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.activeInquiry = data.inquiry;
                }
            });
        },

        addActiveNote() {
            if (!this.activeInquiry || !this.newNoteText.trim()) return;

            const note = this.newNoteText;
            this.newNoteText = '';

            fetch(`/websites/inquiries/${this.activeInquiry.id}/notes`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({ note })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (!this.activeInquiry.activities) {
                        this.activeInquiry.activities = [];
                    }
                    this.activeInquiry.activities.unshift(data.activity);
                }
            });
        },

        resetFilters() {
            window.location.href = "{{ route('websites.inquiries.index') }}";
        }
    }
}
</script>
@endsection
