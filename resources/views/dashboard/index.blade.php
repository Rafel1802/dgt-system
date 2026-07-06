@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')
@section('meta_description', 'KIUQ SYSTEM overview: tasks, CRM, sales, notifications, and team activity at a glance.')

@section('content')
@php
    $recentActivities = $stats['recent_activities'];
    $totalUsers = (int) $stats['total_users'];
    $onlineUsers = (int) $stats['online_users'];
    $offlineUsers = max($totalUsers - $onlineUsers, 0);
    $activityModules = $recentActivities
        ->groupBy(fn($log) => $log->module ?: 'system')
        ->map(fn($items, $module) => ['module' => ucfirst(str_replace(['-', '_'], ' ', $module)), 'count' => $items->count()])
        ->values();
    $activityDays = collect(range(5, 0))->map(function ($daysAgo) use ($recentActivities) {
        $date = now()->subDays($daysAgo);

        return [
            'label' => $date->format('D'),
            'count' => $recentActivities->filter(fn($log) => $log->created_at?->isSameDay($date))->count(),
        ];
    });
    $dashboardNotifications = auth()->user()->notifications()->latest()->take(5)->get();
    $dashboardUnreadCount = auth()->user()->unreadNotifications()->count();
    $permissionsCount = auth()->user()->getAllPermissions()->count();
    $appearance = $appearance ?? [
        'background_type' => 'gradient',
        'background_value' => 'linear-gradient(180deg,#f8fafc,#eef2f7)',
        'cover_type' => 'gradient',
        'cover_value' => 'linear-gradient(135deg,#2F68ED 0%,#2457cf 46%,#173a92 100%)',
    ];
    $externalTools = collect($externalTools ?? []);
    $boardExternalTools = $externalTools->where('group', 'board')->values();
    $generatorExternalTools = $externalTools->where('group', 'generator')->values();
    $canManageExternalTools = $canManageExternalTools ?? auth()->user()->hasAnyRole(['super-admin', 'admin']);
@endphp

<style>
    .dash-shell {
        position: relative;
        isolation: isolate;
    }

    .dash-shell::before {
        content: "";
        position: fixed;
        inset: 64px 0 0 var(--sidebar-width);
        pointer-events: none;
        z-index: -1;
        background: var(--dashboard-bg,
            linear-gradient(135deg, rgba(14, 165, 233, 0.08), transparent 30%),
            linear-gradient(225deg, rgba(16, 185, 129, 0.08), transparent 34%),
            linear-gradient(180deg, #f8fafc, #eef2f7));
        background-size: cover;
        background-position: center;
    }

    .sidebar-is-collapsed .dash-shell::before {
        inset-left: 0;
    }

    .dash-glass {
        border: 1px solid rgba(255, 255, 255, 0.74);
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.82), rgba(255, 255, 255, 0.62));
        box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.82);
        backdrop-filter: blur(22px) saturate(140%);
        -webkit-backdrop-filter: blur(22px) saturate(140%);
    }

    .dash-hero {
        background: var(--dashboard-cover,
            radial-gradient(circle at 82% 18%, rgba(255, 255, 255, 0.28), transparent 30%),
            linear-gradient(135deg, #2F68ED 0%, #2457cf 46%, #173a92 100%));
        background-size: cover;
        background-position: center;
        box-shadow: 0 22px 52px rgba(47, 104, 237, 0.24);
    }

    .dash-card {
        border: 1px solid rgba(226, 232, 240, 0.78);
        background: rgba(255, 255, 255, 0.78);
        box-shadow: 0 14px 42px rgba(15, 23, 42, 0.06);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
    }

    .dash-panel-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.95rem;
        font-weight: 900;
        color: #0f172a;
    }

    .dash-action {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border-radius: 14px;
        border: 1px solid rgba(226, 232, 240, 0.86);
        background: rgba(248, 250, 252, 0.72);
        padding: 0.875rem;
        transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
    }

    .dash-action:hover {
        transform: translateY(-1px);
        border-color: rgba(99, 102, 241, 0.34);
        background: rgba(255, 255, 255, 0.94);
    }

    .dash-tool-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.6rem;
    }

    .dash-tool-link {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        width: 100%;
        border-radius: 14px;
        border: 1px solid rgba(203, 213, 225, 0.74);
        background: rgba(255, 255, 255, 0.76);
        padding: 0.75rem;
        text-align: left;
        transition: transform 160ms ease, border-color 160ms ease, background 160ms ease, box-shadow 160ms ease;
    }

    .dash-tool-link:hover {
        transform: translateY(-1px);
        border-color: rgba(47, 104, 237, 0.42);
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 12px 28px rgba(47, 104, 237, 0.12);
    }

    .dash-tool-link.is-disabled {
        cursor: not-allowed;
        opacity: 0.62;
    }

    .dash-tool-link.is-disabled:hover {
        transform: none;
        border-color: rgba(203, 213, 225, 0.74);
        box-shadow: none;
    }

    .dash-tool-icon {
        display: inline-flex;
        height: 2.35rem;
        width: 2.35rem;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        border-radius: 13px;
        background: linear-gradient(135deg, rgba(47, 104, 237, 0.12), rgba(14, 165, 233, 0.10));
        color: #2F68ED;
    }

    [data-theme="dark"] .dash-tool-link {
        border-color: rgba(51, 65, 85, 0.95);
        background: rgba(15, 23, 42, 0.72);
    }

    [data-theme="dark"] .dash-tool-link:hover {
        border-color: rgba(96, 165, 250, 0.6);
        background: rgba(30, 41, 59, 0.86);
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.42);
    }

    [data-theme="dark"] .dash-tool-icon {
        background: linear-gradient(135deg, rgba(47, 104, 237, 0.28), rgba(14, 165, 233, 0.18));
        color: #bfdbfe;
    }

    [data-theme="dark"] .dash-tool-link.is-disabled:hover {
        transform: none;
        border-color: rgba(51, 65, 85, 0.95);
        box-shadow: none;
    }

    [data-theme="dark"] .supporter-container {
        background: #08090c !important;
        border-color: rgba(255, 255, 255, 0.08) !important;
    }

    [data-theme="dark"] .supporter-container p {
        color: #ffffff !important;
    }

    [data-theme="dark"] .supporter-container p.text-slate-400,
    [data-theme="dark"] .supporter-container p.text-\[11px\] {
        color: #94a3b8 !important;
    }

    [data-theme="dark"] .dash-empty-box {
        background: #08090c !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
    }

    [data-theme="dark"] .dash-empty-box p {
        color: #94a3b8 !important;
    }

    [data-theme="dark"] .dash-empty-box p.text-slate-700 {
        color: #ffffff !important;
    }

    [data-theme="dark"] p.dash-empty-box.text-slate-500 {
        color: #ffffff !important;
    }

    .dash-chart-box {
        height: 260px;
        position: relative;
    }

    @media (max-width: 1023px) {
        .dash-shell::before {
            inset-left: 0;
        }
    }
</style>

<div class="dash-shell space-y-6 animate-fade-in pb-28 md:pb-8"
     x-data="dashboardAppearance(@json($appearance))"
     x-init="init()"
     :style="'--dashboard-bg:' + cssFor('background') + '; --dashboard-cover:' + cssFor('cover')">
    <section class="dash-hero overflow-hidden rounded-[1.75rem] p-6 text-white shadow-xl sm:p-8">
        <div class="grid gap-8 lg:grid-cols-[1fr_320px] lg:items-end">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1.5 text-xs font-black uppercase tracking-wider text-slate-200">
                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                    Live workspace overview
                </div>
                <p class="mt-6 text-sm font-semibold text-slate-300">
                    Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }},
                </p>
                <h1 class="mt-1 max-w-3xl font-display text-3xl font-black leading-tight sm:text-4xl">
                    {{ $user->name }}
                </h1>
                <p class="mt-3 max-w-2xl text-sm font-medium leading-7 text-slate-300">
                    {{ $user->role_display }} dashboard for boards, CRM, notifications, and recent system activity.
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-xl">
                <p class="text-xs font-black uppercase tracking-wider text-slate-300">{{ now()->format('l') }}</p>
                <p class="mt-2 font-display text-2xl font-black">{{ now()->format('F j, Y') }}</p>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-white/10 p-3">
                        <p class="text-2xl font-black">{{ $onlineUsers }}</p>
                        <p class="text-xs font-bold text-slate-300">Active now</p>
                    </div>
                    <div class="rounded-xl bg-white/10 p-3">
                        <p class="text-2xl font-black">{{ $dashboardUnreadCount }}</p>
                        <p class="text-xs font-bold text-slate-300">Unread alerts</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="dash-card rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72M15 11.25a3.75 3.75 0 1 0-7.5 0 3.75 3.75 0 0 0 7.5 0Z"/>
                    </svg>
                </div>
                <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-500">Users</span>
            </div>
            <p class="mt-5 font-display text-3xl font-black text-slate-950">{{ $totalUsers }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">Total active users</p>
        </article>

        <article class="dash-card rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z"/>
                    </svg>
                </div>
                <span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-black uppercase text-emerald-700">Online</span>
            </div>
            <p class="mt-5 font-display text-3xl font-black text-slate-950">{{ $onlineUsers }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">Active in last 30 minutes</p>
        </article>

        <article class="dash-card rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-black uppercase text-amber-700">Activity</span>
            </div>
            <p class="mt-5 font-display text-3xl font-black text-slate-950">{{ $recentActivities->count() }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">Recent system events</p>
        </article>

        <article class="dash-card rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 12h9.75M10.5 18h9.75M3.75 6h.008v.008H3.75V6Zm0 6h.008v.008H3.75V12Zm0 6h.008v.008H3.75V18Z"/>
                    </svg>
                </div>
                <span class="rounded-full bg-sky-50 px-2 py-1 text-[10px] font-black uppercase text-sky-700">Access</span>
            </div>
            <p class="mt-5 font-display text-3xl font-black text-slate-950">{{ $permissionsCount }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">Assigned permissions</p>
        </article>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2 space-y-6">
            <div class="dash-glass rounded-[1.5rem] p-5 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="dash-panel-title">Operational analytics</h2>
                        <p class="mt-1 text-sm font-medium text-slate-500">Live user presence and recent activity distribution.</p>
                    </div>
                    <span class="w-fit rounded-full bg-white/70 px-3 py-1 text-xs font-black uppercase text-slate-500 shadow-sm">Charts</span>
                </div>

                <div class="mt-6 grid gap-5 lg:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200/70 bg-white/70 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-sm font-black text-slate-800">User presence</p>
                            <p class="text-xs font-bold text-slate-400">{{ $totalUsers }} total</p>
                        </div>
                        <div class="dash-chart-box">
                            <canvas id="dashboardUserChart"></canvas>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200/70 bg-white/70 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-sm font-black text-slate-800">Activity trend</p>
                            <p class="text-xs font-bold text-slate-400">Recent logs</p>
                        </div>
                        <div class="dash-chart-box">
                            <canvas id="dashboardActivityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dash-glass rounded-[1.5rem] p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="dash-panel-title">Recent activities</h2>
                        <p class="mt-1 text-sm font-medium text-slate-500">Latest audited actions across the system.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black uppercase text-emerald-700">Live</span>
                    </div>
                </div>

                @if($recentActivities->isEmpty())
                    <div class="dash-empty-box mt-6 rounded-2xl border border-dashed border-slate-300 bg-white/60 p-10 text-center">
                        <p class="text-sm font-black text-slate-700">No activity recorded yet</p>
                        <p class="mt-1 text-xs font-semibold text-slate-400">Audited user actions will appear here.</p>
                    </div>
                @else
                    <div class="mt-5 divide-y divide-slate-200/70 max-h-[400px] overflow-y-auto pr-2 scrollbar-thin">
                        @foreach($recentActivities as $log)
                            <div class="flex items-start gap-3 py-4">
                                <img src="{{ $log->user?->avatar_url ?? 'https://ui-avatars.com/api/?name=System&size=64&background=6366f1&color=fff' }}"
                                     alt="{{ $log->user?->name ?? 'System' }}"
                                     class="avatar avatar-sm mt-0.5">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold leading-6 text-slate-700">
                                        <span class="font-black text-slate-950">{{ $log->user?->name ?? 'System' }}</span>
                                        <span class="text-slate-400">-</span>
                                        {{ $log->description }}
                                    </p>
                                    <p class="mt-1 text-xs font-bold text-slate-400">
                                        {{ $log->created_at?->format('d M Y, H:i') ?? 'just now' }}
                                        <span class="text-slate-300">·</span>
                                        {{ $log->created_at?->diffForHumans() ?? 'live' }}
                                    </p>
                                </div>
                                <span class="hidden rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase text-slate-500 sm:inline-flex">
                                    {{ $log->module ?: 'system' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <aside class="space-y-6">
            <div class="dash-glass rounded-[1.5rem] p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="dash-panel-title">Quick actions</h2>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Jump into common workflows.</p>
                    </div>
                </div>
                <div class="mt-5 space-y-3">
                    @if(Route::has('boards.workspaces'))
                        <a href="{{ route('boards.workspaces') }}" class="dash-action">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25A2.25 2.25 0 0 1 8.25 10.5H6A2.25 2.25 0 0 1 3.75 8.25V6Zm9.75 0a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6Z"/></svg>
                            </span>
                            <span>
                                <span class="block text-sm font-black text-slate-800">Open boards</span>
                                <span class="text-xs font-semibold text-slate-500">Manage team workspaces</span>
                            </span>
                        </a>
                    @endif

                    @if(Route::has('notes.private'))
                        <a href="{{ route('notes.private') }}" class="dash-action">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-yellow-50 text-yellow-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                            </span>
                            <span>
                                <span class="block text-sm font-black text-slate-800">My Notes</span>
                                <span class="text-xs font-semibold text-slate-500">Private & Team ideas</span>
                            </span>
                        </a>
                    @endif

                    @if($externalTools->isNotEmpty())
                        <div class="supporter-container rounded-2xl border border-slate-200/70 bg-white/50 p-3">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-wider text-slate-500">eBay &amp; Web Supporter</p>
                                    <p class="text-[11px] font-semibold text-slate-400">External systems</p>
                                </div>
                                @if($canManageExternalTools && Route::has('admin.settings.index'))
                                    <a href="{{ route('admin.settings.index') }}" class="text-[11px] font-black text-[#2F68ED] hover:text-blue-700">Edit URLs</a>
                                @endif
                            </div>

                            <div class="dash-tool-grid">
                                @foreach($boardExternalTools as $tool)
                                    @if(filled($tool['url']))
                                        <a href="{{ $tool['url'] }}" target="_blank" rel="noopener noreferrer" class="dash-tool-link">
                                            <span class="dash-tool-icon">
                                                <x-external-tool-icon :name="$tool['icon']" />
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-black text-slate-800">{{ $tool['short_label'] }}</span>
                                                <span class="block truncate text-xs font-semibold text-slate-500">{{ $tool['description'] }}</span>
                                            </span>
                                        </a>
                                    @elseif($canManageExternalTools)
                                        <button type="button" class="dash-tool-link is-disabled" disabled>
                                            <span class="dash-tool-icon">
                                                <x-external-tool-icon :name="$tool['icon']" />
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-black text-slate-800">{{ $tool['short_label'] }}</span>
                                                <span class="block truncate text-xs font-semibold text-slate-500">URL not set yet</span>
                                            </span>
                                        </button>
                                    @endif
                                @endforeach
                            </div>

                            <div class="mt-4 border-t border-slate-200/70 pt-3">
                                <p class="mb-2 text-xs font-black uppercase tracking-wider text-slate-500">System Supporter</p>
                                <div class="dash-tool-grid">
                                    @foreach($generatorExternalTools as $tool)
                                        @if(filled($tool['url']))
                                            <a href="{{ $tool['url'] }}" target="_blank" rel="noopener noreferrer" class="dash-tool-link">
                                                <span class="dash-tool-icon">
                                                    <x-external-tool-icon :name="$tool['icon']" />
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-black text-slate-800">{{ $tool['short_label'] }}</span>
                                                    <span class="block truncate text-xs font-semibold text-slate-500">{{ $tool['description'] }}</span>
                                                </span>
                                            </a>
                                        @elseif($canManageExternalTools)
                                            <button type="button" class="dash-tool-link is-disabled" disabled>
                                                <span class="dash-tool-icon">
                                                    <x-external-tool-icon :name="$tool['icon']" />
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-black text-slate-800">{{ $tool['short_label'] }}</span>
                                                    <span class="block truncate text-xs font-semibold text-slate-500">URL not set yet</span>
                                                </span>
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if(Route::has('crm.dashboard'))
                        <a href="{{ route('crm.dashboard') }}" class="dash-action">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75M9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25M16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75"/></svg>
                            </span>
                            <span>
                                <span class="block text-sm font-black text-slate-800">CRM dashboard</span>
                                <span class="text-xs font-semibold text-slate-500">Review sales movement</span>
                            </span>
                        </a>
                    @endif

                    @if(Route::has('reports.index'))
                        <a href="{{ route('reports.index') }}" class="dash-action">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z"/></svg>
                            </span>
                            <span>
                                <span class="block text-sm font-black text-slate-800">Reports</span>
                                <span class="text-xs font-semibold text-slate-500">Open analytics center</span>
                            </span>
                        </a>
                    @endif

                    @if(Route::has('settings'))
                        <a href="{{ route('settings') }}" class="dash-action">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                            </span>
                            <span>
                                <span class="block text-sm font-black text-slate-800">Profile settings</span>
                                <span class="text-xs font-semibold text-slate-500">Security and password</span>
                            </span>
                        </a>
                    @endif
                </div>
            </div>



            <div class="dash-glass rounded-[1.5rem] p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="dash-panel-title">Notification center</h2>
                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $dashboardUnreadCount }} unread notifications</p>
                    </div>
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022"/></svg>
                    </span>
                </div>

                @if($dashboardNotifications->isEmpty())
                    <div class="dash-empty-box mt-5 rounded-2xl border border-dashed border-slate-300 bg-white/60 p-6 text-center">
                        <p class="text-sm font-black text-slate-700">No notifications yet</p>
                        <p class="mt-1 text-xs font-semibold text-slate-400">Board and card updates will appear here.</p>
                    </div>
                @else
                    <div class="mt-5 space-y-3">
                        @foreach($dashboardNotifications as $notification)
                            @php
                                $data = $notification->data;
                                $actorName = data_get($data, 'actor_name', 'KIUQ SYSTEM');
                                $actorAvatar = data_get($data, 'actor_avatar', 'https://ui-avatars.com/api/?name=System&size=64&background=6366f1&color=fff');
                                $description = data_get($data, 'description') ?: data_get($data, 'message', 'New notification');
                                $boardName = data_get($data, 'board_name');
                                $cardTitle = data_get($data, 'card_title');
                            @endphp
                            <div class="rounded-2xl border {{ $notification->read_at ? 'border-slate-200 bg-white/60' : 'border-indigo-200 bg-indigo-50/60' }} p-3">
                                <div class="flex items-start gap-3">
                                    <img src="{{ $actorAvatar }}" alt="{{ $actorName }}" class="avatar avatar-sm">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="truncate text-sm font-black text-slate-900">{{ $actorName }}</p>
                                            <span class="text-[10px] font-bold text-slate-400">{{ $notification->created_at?->diffForHumans() }}</span>
                                        </div>
                                        <p class="mt-1 text-xs font-semibold leading-5 text-slate-600">{{ str_replace('**', '', strip_tags($description)) }}</p>
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @if($boardName)
                                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-black text-slate-600">{{ $boardName }}</span>
                                            @endif
                                            @if($cardTitle)
                                                <span class="rounded-md bg-indigo-100 px-1.5 py-0.5 text-[10px] font-black text-indigo-700">{{ $cardTitle }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="dash-glass rounded-[1.5rem] p-5">
                <h2 class="dash-panel-title">Activity modules</h2>
                <p class="mt-1 text-xs font-semibold text-slate-500">Distribution from recent activity logs.</p>
                <div class="mt-5 space-y-3">
                    @forelse($activityModules as $module)
                        @php $pct = $recentActivities->count() ? round(($module['count'] / $recentActivities->count()) * 100) : 0; @endphp
                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <span class="text-xs font-black text-slate-700">{{ $module['module'] }}</span>
                                <span class="text-xs font-bold text-slate-400">{{ $module['count'] }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-sky-500" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="dash-empty-box rounded-2xl border border-dashed border-slate-300 bg-white/60 p-5 text-center text-sm font-semibold text-slate-500">
                            No activity modules yet.
                        </p>
                    @endforelse
                </div>
            </div>
        </aside>
    </section>
</div>
@endsection

@push('scripts')
<script>
function dashboardAppearance(initial) {
    const fallbackBg = 'linear-gradient(180deg,#f8fafc,#eef2f7)';
    const fallbackCover = 'linear-gradient(135deg,#2F68ED 0%,#2457cf 46%,#173a92 100%)';

    return {
        backgroundType: initial.background_type || 'gradient',
        backgroundValue: initial.background_value || fallbackBg,
        backgroundColor: /^#/.test(initial.background_value || '') ? initial.background_value : '#2F68ED',
        backgroundImageUrl: initial.background_type === 'image' ? initial.background_value : '',
        coverType: initial.cover_type || 'gradient',
        coverValue: initial.cover_value || fallbackCover,
        coverColor: /^#/.test(initial.cover_value || '') ? initial.cover_value : '#2F68ED',
        coverImageUrl: initial.cover_type === 'image' ? initial.cover_value : '',
        gradients: [
            'linear-gradient(135deg,#e0f2fe,#f8fafc 45%,#dbeafe)',
            'linear-gradient(135deg,#eff6ff,#dbeafe 55%,#bfdbfe)',
            'linear-gradient(135deg,#f8fafc,#e2e8f0)',
            'linear-gradient(135deg,#ecfeff,#e0f2fe 55%,#dbeafe)',
        ],
        coverGradients: [
            'linear-gradient(135deg,#2F68ED 0%,#2457cf 46%,#173a92 100%)',
            'linear-gradient(135deg,#0891b2,#2F68ED 55%,#7c3aed)',
            'linear-gradient(135deg,#0f172a,#1e40af 55%,#2F68ED)',
            'linear-gradient(135deg,#14b8a6,#2F68ED 55%,#8b5cf6)',
        ],
        init() {},
        cssFor(target) {
            const type = target === 'cover' ? this.coverType : this.backgroundType;
            const value = target === 'cover' ? this.coverValue : this.backgroundValue;

            if (type === 'image' && value) {
                return `linear-gradient(rgba(15,23,42,.18), rgba(15,23,42,.24)), url("${String(value).replace(/"/g, '\\"')}")`;
            }

            return value || (target === 'cover' ? fallbackCover : fallbackBg);
        },
        previewUpload(event, target) {
            const file = event.target.files && event.target.files[0];
            if (!file || !file.type.startsWith('image/')) return;

            const url = URL.createObjectURL(file);
            if (target === 'cover') {
                this.coverType = 'image';
                this.coverValue = url;
                this.coverImageUrl = url;
                return;
            }

            this.backgroundType = 'image';
            this.backgroundValue = url;
            this.backgroundImageUrl = url;
        },
    };
}

document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart) return;

    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#64748b';

    const userChart = document.getElementById('dashboardUserChart');
    if (userChart) {
        new Chart(userChart, {
            type: 'doughnut',
            data: {
                labels: ['Active now', 'Not active'],
                datasets: [{
                    data: [{{ $onlineUsers }}, {{ $offlineUsers }}],
                    backgroundColor: ['#10b981', '#e2e8f0'],
                    borderWidth: 0,
                    hoverOffset: 8,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, boxWidth: 8, font: { weight: 700 } },
                    },
                },
            },
        });
    }

    const activityChart = document.getElementById('dashboardActivityChart');
    if (activityChart) {
        new Chart(activityChart, {
            type: 'bar',
            data: {
                labels: @json($activityDays->pluck('label')),
                datasets: [{
                    label: 'Activity',
                    data: @json($activityDays->pluck('count')),
                    backgroundColor: '#0ea5e9',
                    borderRadius: 10,
                    maxBarThickness: 34,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false }, ticks: { font: { weight: 700 } } },
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e2e8f0' } },
                },
                plugins: {
                    legend: { display: false },
                },
            },
        });
    }
});
</script>
@endpush
