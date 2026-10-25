@extends('layouts.app')
@section('title', 'Central Website Integration Platform')
@section('page_title', 'Central Website Integration Platform')

@section('content')
<style>
/* ── Central Integration Platform Styles ── */
.conn-stat-card {
    background: var(--card-bg, #ffffff);
    border: 1.5px solid var(--border-color, #e2e8f0);
    border-radius: 1rem;
    padding: 1.25rem;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}
.conn-stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.06); }
[data-theme="dark"] .conn-stat-card { background: #0f172a; border-color: #1e293b; }
[data-theme="neon"] .conn-stat-card { background: rgba(4, 20, 56, 0.95); border-color: rgba(0, 160, 255, 0.4); }

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

.pulse-dot {
    width: 8px; height: 8px; border-radius: 9999px; display: inline-block;
}
.pulse-green { background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25); animation: pulseGreen 2s infinite; }
.pulse-amber { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25); animation: pulseAmber 2s infinite; }
.pulse-red   { background: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25); }
.pulse-gray  { background: #94a3b8; }

@keyframes pulseGreen { 0%,100%{box-shadow:0 0 0 0 rgba(16,185,129,0.5)} 50%{box-shadow:0 0 0 6px rgba(16,185,129,0)} }
@keyframes pulseAmber { 0%,100%{box-shadow:0 0 0 0 rgba(245,158,11,0.5)} 50%{box-shadow:0 0 0 6px rgba(245,158,11,0)} }

.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.4); border-radius: 9999px; }
</style>

<div class="space-y-6 pb-12" x-data="integrationPlatform()">

    <!-- ── Top Navigation Bar ── -->
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200/80 dark:border-slate-800 pb-4">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('websites.dashboard') }}" class="nav-pill-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Executive Dashboard
            </a>
            @if(auth()->user()?->canAccessCentralConnectors())
            <a href="{{ route('websites.integrations.index') }}" class="nav-pill-btn active">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Central Connectors
            </a>
            @endif
            <a href="{{ route('websites.blogs.index') }}" class="nav-pill-btn">
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
            <a href="{{ asset('connectors/wordpress/dgt-connector.php') }}" download="dgt-connector.php" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download WP Plugin
            </a>
            <a href="{{ asset('connectors/custom-php/dgt-connector-client.php') }}" download="dgt-connector-client.php" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">
                <svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                Download PHP SDK
            </a>
        </div>
    </div>

    <!-- ── Overview Stat Metrics ── -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <a href="{{ route('websites.integrations.index') }}" class="conn-stat-card hover:border-indigo-400 transition block {{ empty($syncStatus) ? 'ring-2 ring-indigo-500/30' : '' }}">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Websites</span>
            <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ $totalWebsites }}</div>
            <div class="text-[11px] text-slate-400 mt-1">3 Hostinger Accounts</div>
        </a>

        <a href="{{ route('websites.integrations.index', array_merge(request()->except('sync_status', 'page'), ['sync_status' => 'connected'])) }}" class="conn-stat-card hover:border-emerald-400 transition block {{ $syncStatus === 'connected' ? 'ring-2 ring-emerald-500/40 bg-emerald-50/20' : '' }}">
            <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Blog Synced</span>
            <div class="mt-2 text-2xl font-black text-emerald-600 dark:text-emerald-400 flex items-center gap-2">
                <span class="pulse-dot pulse-green"></span>
                {{ $connectedCmsCount }}
            </div>
            <div class="text-[11px] text-emerald-600/80 font-bold mt-1">Click to view connected</div>
        </a>

        <a href="{{ route('websites.integrations.index', array_merge(request()->except('sync_status', 'page'), ['sync_status' => 'not_connected'])) }}" class="conn-stat-card hover:border-slate-400 transition block {{ $syncStatus === 'not_connected' ? 'ring-2 ring-slate-500/40 bg-slate-50/30' : '' }}">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Not Synced</span>
            <div class="mt-2 text-2xl font-black text-slate-500 dark:text-slate-400">{{ $notConnectedCount }}</div>
            <div class="text-[11px] text-slate-400 font-medium mt-1">Click to view unsynced</div>
        </a>

        <div class="conn-stat-card">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">GA4 Analytics</span>
            <div class="mt-2 text-2xl font-black text-sky-600 dark:text-sky-400">{{ $connectedAnalyticsCount }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Data API Active</div>
        </div>

        <div class="conn-stat-card">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Blogs</span>
            <div class="mt-2 text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ number_format($totalBlogPosts) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Synchronized Posts</div>
        </div>

        <div class="conn-stat-card">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Cumulative Views</span>
            <div class="mt-2 text-2xl font-black text-violet-600 dark:text-violet-400">{{ number_format($totalCumulativeViews) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Real Verified Traffic</div>
        </div>
    </div>

    <!-- ── Quick Sync View Switcher ── -->
    <div class="flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3 shadow-xs">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 pl-2 pr-1">Blog Sync Filter:</span>
            <a href="{{ route('websites.integrations.index', request()->except('sync_status', 'page')) }}"
               class="px-3.5 py-1.5 text-xs font-bold rounded-xl transition flex items-center gap-2 {{ empty($syncStatus) ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-500/20' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300' }}">
                <span>All Websites</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ empty($syncStatus) ? 'bg-white/20 text-white' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300' }}">{{ $totalWebsites }}</span>
            </a>
            <a href="{{ route('websites.integrations.index', array_merge(request()->except('page'), ['sync_status' => 'connected'])) }}"
               class="px-3.5 py-1.5 text-xs font-bold rounded-xl transition flex items-center gap-2 {{ $syncStatus === 'connected' ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-500/20' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-emerald-700 dark:text-emerald-400' }}">
                <span class="w-2 h-2 rounded-full bg-emerald-400 {{ $syncStatus === 'connected' ? 'animate-pulse' : '' }}"></span>
                <span>Connected (Blog Sync Live)</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ $syncStatus === 'connected' ? 'bg-white/20 text-white' : 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300' }}">{{ $connectedCmsCount }}</span>
            </a>
            <a href="{{ route('websites.integrations.index', array_merge(request()->except('page'), ['sync_status' => 'not_connected'])) }}"
               class="px-3.5 py-1.5 text-xs font-bold rounded-xl transition flex items-center gap-2 {{ $syncStatus === 'not_connected' ? 'bg-slate-700 text-white shadow-sm' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400' }}">
                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                <span>Not Connected / Unsynced</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ $syncStatus === 'not_connected' ? 'bg-white/20 text-white' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400' }}">{{ $notConnectedCount }}</span>
            </a>
        </div>
        <div class="text-xs text-slate-400 pr-2">
            Showing <strong class="text-slate-800 dark:text-white">{{ $websites->total() }}</strong> websites
        </div>
    </div>

    <!-- ── Filter & Search Bar ── -->
    <form method="GET" action="{{ route('websites.integrations.index') }}" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-wrap items-center gap-3">
        @if($syncStatus)
            <input type="hidden" name="sync_status" value="{{ $syncStatus }}">
        @endif

        <!-- Search -->
        <div class="relative flex-1 min-w-[200px]">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search website name or domain..."
                   class="w-full pl-9 pr-4 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>

        <!-- Hostinger Account -->
        <select name="hostinger" onchange="this.form.submit()" class="px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
            <option value="">All Hostinger Accounts</option>
            @foreach($hostingerAccounts as $acc)
                <option value="{{ $acc }}" {{ $hostingerAccount === $acc ? 'selected' : '' }}>{{ $acc }}</option>
            @endforeach
        </select>

        <!-- Category -->
        <select name="category" onchange="this.form.submit()" class="px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
            <option value="">All Categories</option>
            @foreach($categories as $cat)
                <option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ $cat }}</option>
            @endforeach
        </select>

        <!-- CMS Status -->
        <select name="status" onchange="this.form.submit()" class="px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
            <option value="">All CMS Statuses</option>
            <option value="connected" {{ $cmsStatus === 'connected' ? 'selected' : '' }}>Connected</option>
            <option value="disconnected" {{ $cmsStatus === 'disconnected' ? 'selected' : '' }}>Disconnected</option>
            <option value="error" {{ $cmsStatus === 'error' ? 'selected' : '' }}>Error</option>
        </select>

        <!-- Assigned Member -->
        <select name="member_id" onchange="this.form.submit()" class="px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
            <option value="">All Members</option>
            @foreach($members as $m)
                <option value="{{ $m->id }}" {{ $memberId == $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
            @endforeach
        </select>

        @if($search || $hostingerAccount || $category || $cmsStatus || $memberId || $syncStatus)
            <a href="{{ route('websites.integrations.index') }}" class="px-3 py-2 text-xs font-bold text-rose-600 hover:text-rose-700 dark:text-rose-400">
                Reset
            </a>
        @endif
    </form>

    <!-- ── Main Websites Connector Table ── -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700/80 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <th class="py-3.5 px-4">Website & Domain</th>
                        <th class="py-3.5 px-3">Account & Category</th>
                        <th class="py-3.5 px-3">Connector & Stack</th>
                        <th class="py-3.5 px-3">Verification</th>
                        <th class="py-3.5 px-3">CMS Connection</th>
                        <th class="py-3.5 px-3">Analytics (GA4)</th>
                        <th class="py-3.5 px-3">Blogs & Views</th>
                        <th class="py-3.5 px-3">Last Sync</th>
                        <th class="py-3.5 px-3">Assigned</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 text-sm">
                    @forelse($websites as $site)
                        @php
                            $integ = $site->integration;
                            $cmsConn = $integ?->cms_status ?? 'disconnected';
                            $ga4Conn = $integ?->analytics_status ?? 'not_configured';
                            $connectorType = $integ?->connector_type ?? 'none';
                            $tech = $integ?->tech_stack ?? [];
                        @endphp
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                            <!-- Website Name & URL -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-3">
                                    @if($site->logo_src)
                                        <img src="{{ $site->logo_src }}" alt="{{ $site->name }}" class="w-8 h-8 rounded-lg object-contain bg-slate-100 dark:bg-slate-800 p-0.5 border border-slate-200 dark:border-slate-700">
                                    @else
                                        <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center font-black text-indigo-600 text-xs">
                                            {{ substr($site->name, 0, 2) }}
                                        </div>
                                    @endif
                                    <div>
                                        <div class="font-extrabold text-slate-900 dark:text-white leading-snug">
                                            {{ $site->name }}
                                        </div>
                                        <a href="{{ $site->url }}" target="_blank" rel="noopener noreferrer" class="text-xs text-slate-400 hover:text-indigo-500 transition inline-flex items-center gap-1">
                                            {{ $site->clean_domain }}
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </div>
                                </div>
                            </td>

                            <!-- Account & Category -->
                            <td class="py-3.5 px-3">
                                <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 break-all">
                                    {{ $integ?->hostinger_account ?: 'Hostinger (Default)' }}
                                </div>
                                <span class="inline-block mt-1 px-2 py-0.5 text-[10px] font-bold rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                    {{ $site->category ?: 'Uncategorized' }}
                                </span>
                            </td>

                            <!-- Connector Type & Tech Stack -->
                            <td class="py-3.5 px-3">
                                <div class="text-xs font-bold text-slate-900 dark:text-white capitalize">
                                    {{ str_replace('_', ' ', $connectorType) }}
                                </div>
                                @if(!empty($tech['php_version']) || !empty($tech['cms']))
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        {{ $tech['cms'] ?? '' }} {{ !empty($tech['php_version']) ? '· PHP ' . $tech['php_version'] : '' }}
                                    </div>
                                @endif
                            </td>

                            <!-- Ownership Verification -->
                            <td class="py-3.5 px-3">
                                @if($integ?->is_verified)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        Verified
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 border border-slate-200 dark:border-slate-700">
                                        Unverified
                                    </span>
                                @endif
                            </td>

                            <!-- CMS Connection -->
                            <td class="py-3.5 px-3">
                                @if($cmsConn === 'connected')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                        <span class="pulse-dot pulse-green"></span>
                                        Blog Sync Live
                                    </span>
                                @elseif($cmsConn === 'error')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-full bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800" title="{{ $integ?->last_sync_error }}">
                                        <span class="pulse-dot pulse-red"></span>
                                        Sync Error
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 border border-slate-200 dark:border-slate-700">
                                        <span class="pulse-dot pulse-gray"></span>
                                        Not Connected
                                    </span>
                                @endif
                            </td>

                            <!-- Analytics Status -->
                            <td class="py-3.5 px-3">
                                @if($ga4Conn === 'connected')
                                    <span class="inline-flex items-center gap-1 text-xs font-bold text-sky-600 dark:text-sky-400">
                                        <svg class="w-3.5 h-3.5 text-sky-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        GA4 Active
                                    </span>
                                @elseif($ga4Conn === 'error')
                                    <span class="text-xs font-bold text-rose-500">GA4 Error</span>
                                @else
                                    <span class="text-xs text-slate-400 font-medium">None</span>
                                @endif
                            </td>

                            <!-- Blogs & Views -->
                            <td class="py-3.5 px-3">
                                <div class="font-extrabold text-slate-900 dark:text-white">
                                    {{ number_format($site->posts_count) }} <span class="text-xs font-normal text-slate-400">posts</span>
                                </div>
                                <div class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 mt-0.5">
                                    {{ number_format((int)$site->posts_sum_cumulative_views) }} views
                                </div>
                            </td>

                            <!-- Last Sync -->
                            <td class="py-3.5 px-3">
                                <div class="text-xs text-slate-700 dark:text-slate-300">
                                    {{ $integ?->last_sync_at ? $integ->last_sync_at->diffForHumans() : 'Never' }}
                                </div>
                                @if($integ?->last_sync_status)
                                    <span class="text-[10px] uppercase font-bold {{ $integ->last_sync_status === 'success' ? 'text-emerald-500' : 'text-rose-500' }}">
                                        {{ $integ->last_sync_status }}
                                    </span>
                                @endif
                            </td>

                            <!-- Assigned Member -->
                            <td class="py-3.5 px-3">
                                @if($site->handler)
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center font-bold text-[10px] text-slate-600 dark:text-slate-300">
                                            {{ substr($site->handler->name, 0, 1) }}
                                        </div>
                                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 truncate max-w-[100px]">
                                            {{ $site->handler->name }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endif
                            </td>

                            <!-- Action Buttons -->
                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <!-- Test Connection -->
                                    <button type="button" @click="testSite({{ $site->id }})" title="Test Connection"
                                            class="p-2 rounded-lg text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    </button>

                                    <!-- Sync Now -->
                                    <button type="button" @click="syncSite({{ $site->id }})" title="Synchronize Now"
                                            class="p-2 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    </button>

                                    <!-- Unsync Website -->
                                    @if($cmsConn === 'connected' || $site->posts_count > 0 || ($integ && $integ->is_active))
                                        <button type="button" @click="unsyncSite({{ $site->id }}, '{{ addslashes($site->name) }}')" title="Unsync Website (Disconnect blog sync & clear posts)"
                                                class="p-2 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        </button>
                                    @endif

                                    <!-- Credentials / Settings Modal -->
                                    <button type="button" @click="openConfigModal({{ json_encode($site) }})" title="Credentials & Settings"
                                            class="p-2 rounded-lg text-slate-500 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                    </button>

                                    <!-- Sync Logs -->
                                    <button type="button" @click="openLogsModal({{ $site->id }}, '{{ addslashes($site->name) }}')" title="View Sync Logs"
                                            class="p-2 rounded-lg text-slate-500 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-12 text-center text-slate-400">
                                No websites match your selected criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($websites->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                {{ $websites->links() }}
            </div>
        @endif
    </div>

    <!-- ── Settings & Credentials Modal ── -->
    <div x-show="configModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-5" @click.away="configModalOpen = false">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <h3 class="text-lg font-black text-slate-900 dark:text-white" x-text="'Connector Settings: ' + activeSite.name"></h3>
                <button type="button" @click="configModalOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xl font-bold">&times;</button>
            </div>

            <!-- Credentials Display Box -->
            <div class="bg-slate-50 dark:bg-slate-800/80 rounded-xl p-4 border border-slate-200/80 dark:border-slate-700/80 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase">Integration API Key (Public)</span>
                    <button type="button" @click="rotateKeys()" class="text-xs font-bold text-indigo-600 hover:underline">Rotate Credential</button>
                </div>
                <div class="flex items-center gap-2">
                    <input type="text" readonly :value="activeSite.integration ? activeSite.integration.api_key : 'Generate on save'" class="w-full text-xs font-mono bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-slate-700 dark:text-slate-300">
                    <button type="button" @click="navigator.clipboard.writeText(activeSite.integration ? activeSite.integration.api_key : '')" class="px-3 py-2 text-xs font-bold bg-indigo-50 hover:bg-indigo-100 text-indigo-600 rounded-lg">Copy</button>
                </div>
                <p class="text-[11px] text-slate-400">Include this key in your request headers as <code class="text-indigo-500">X-DGT-Key</code>.</p>
            </div>

            <!-- Save Form -->
            <form :action="'/websites/' + activeSite.id + '/integration'" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Connector Architecture</label>
                    <select name="connector_type" x-model="formData.connector_type" class="w-full text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white">
                        <option value="none">None / Disabled</option>
                        <option value="wordpress_plugin">WordPress (DGT Plugin)</option>
                        <option value="wordpress_rest">WordPress (Native REST API)</option>
                        <option value="custom_laravel">Custom Laravel Web App</option>
                        <option value="custom_php">Custom Core PHP Website</option>
                        <option value="custom_api">Custom Headless / API</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Custom Endpoint URL (For Pull Mode)</label>
                    <input type="url" name="endpoint_url" x-model="formData.endpoint_url" placeholder="https://example.com/wp-json/dgt/v1" class="w-full text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white">
                    <p class="text-[11px] text-slate-400 mt-1">Leave empty to use base domain. Protected against SSRF and private-network access.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Hostinger Account</label>
                        <select name="hostinger_account" x-model="formData.hostinger_account" class="w-full text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white">
                            <option value="">Default Hostinger</option>
                            <option value="americanskidsteers@gmail.com">americanskidsteers@gmail.com (Account 1)</option>
                            <option value="americanskidsteer@gmail.com">americanskidsteer@gmail.com (Account 2)</option>
                            <option value="impossiblemachinery@gmail.com">impossiblemachinery@gmail.com (Account 3)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">GA4 Property ID</label>
                        <input type="text" name="ga4_property_id" x-model="formData.ga4_property_id" placeholder="properties/12345678" class="w-full text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white">
                    </div>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="configModalOpen = false" class="px-4 py-2 text-sm font-bold text-slate-600 dark:text-slate-400">Cancel</button>
                    <button type="submit" class="px-5 py-2 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md transition">Save Connector</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Sync Logs Modal ── -->
    <div x-show="logsModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl space-y-4" @click.away="logsModalOpen = false">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <h3 class="text-lg font-black text-slate-900 dark:text-white" x-text="'Sync History: ' + activeSiteName"></h3>
                <button type="button" @click="logsModalOpen = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <div class="max-h-[400px] overflow-y-auto custom-scrollbar space-y-2">
                <template x-if="siteLogs.length === 0">
                    <div class="py-8 text-center text-slate-400 text-sm">No sync logs recorded yet.</div>
                </template>
                <template x-for="log in siteLogs" :key="log.id">
                    <div class="p-3 rounded-xl border text-xs" :class="log.status === 'success' ? 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800' : 'bg-rose-50/50 dark:bg-rose-950/20 border-rose-200 dark:border-rose-800'">
                        <div class="flex items-center justify-between font-bold">
                            <span :class="log.status === 'success' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'" x-text="log.sync_type + ' · ' + log.status.toUpperCase()"></span>
                            <span class="text-slate-400" x-text="new Date(log.created_at).toLocaleString()"></span>
                        </div>
                        <div class="mt-1 text-slate-600 dark:text-slate-300">
                            Synced: <span class="font-bold" x-text="log.items_synced"></span> | Updated: <span class="font-bold" x-text="log.items_updated"></span> | Duration: <span class="font-bold" x-text="log.duration_ms + 'ms'"></span>
                        </div>
                        <template x-if="log.error_message">
                            <div class="mt-1 text-rose-600 dark:text-rose-400 font-mono text-[11px]" x-text="log.error_message"></div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>

</div>

<script>
function integrationPlatform() {
    return {
        configModalOpen: false,
        logsModalOpen: false,
        activeSite: {},
        activeSiteName: '',
        siteLogs: [],
        formData: {
            connector_type: 'none',
            endpoint_url: '',
            hostinger_account: '',
            ga4_property_id: ''
        },

        openConfigModal(site) {
            this.activeSite = site;
            const integ = site.integration || {};
            this.formData.connector_type = integ.connector_type || 'none';
            this.formData.endpoint_url = integ.endpoint_url || '';
            this.formData.hostinger_account = integ.hostinger_account || '';
            this.formData.ga4_property_id = integ.ga4_property_id || '';
            this.configModalOpen = true;
        },

        async openLogsModal(siteId, siteName) {
            this.activeSiteName = siteName;
            this.siteLogs = [];
            this.logsModalOpen = true;
            try {
                const res = await fetch(`/websites/${siteId}/integration/logs`);
                const data = await res.json();
                if (data.success) {
                    this.siteLogs = data.logs;
                }
            } catch (e) {
                console.error('Failed to load logs:', e);
            }
        },

        async testSite(siteId) {
            try {
                const res = await fetch(`/websites/${siteId}/integration/test`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                alert(data.message || (data.success ? 'Connection successful!' : 'Connection failed.'));
                if (data.success) window.location.reload();
            } catch (e) {
                alert('Connection test failed: ' + e.message);
            }
        },

        async syncSite(siteId) {
            try {
                const res = await fetch(`/websites/${siteId}/integration/sync`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                alert(data.message || 'Sync queued.');
            } catch (e) {
                alert('Sync dispatch failed: ' + e.message);
            }
        },

        async unsyncSite(siteId, siteName) {
            if (!confirm(`Are you sure you want to UNSYNC "${siteName}"?\n\nThis will disconnect blog synchronization and remove all synced blog posts for this website from DGT System.\n\nYou can re-sync anytime by clicking the Synchronize button.`)) {
                return;
            }
            try {
                const res = await fetch(`/websites/${siteId}/integration/unsync`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ clear_posts: true })
                });
                const data = await res.json();
                if (data.success) {
                    alert(data.message || 'Website unsynced successfully.');
                    window.location.reload();
                } else {
                    alert('Error unsyncing website: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                alert('Unsync failed: ' + e.message);
            }
        },

        async rotateKeys() {
            if (!confirm('Are you sure you want to rotate integration credentials? Connected clients must be updated with the new API secret.')) {
                return;
            }
            try {
                const res = await fetch(`/websites/${this.activeSite.id}/integration/rotate-key`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    alert('New API Secret (Copy now!):\n\n' + data.api_secret);
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (e) {
                alert('Rotation failed: ' + e.message);
            }
        }
    }
}
</script>
@endsection
