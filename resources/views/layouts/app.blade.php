<?php
$appIcon = file_exists(public_path('storage/favicon.svg')) ? asset('storage/favicon.svg') : asset('favicon.svg');
?>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- SEO -->
    <title>@yield('title', 'DGT System') | Digital Team & CRM Management</title>
    <meta name="description" content="@yield('meta_description', 'Digital Team and CRM Management System — Manage tasks, customers, and sales pipelines efficiently.')">
    <meta name="robots" content="noindex, nofollow">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ $appIcon }}">
    <link rel="shortcut icon" href="{{ $appIcon }}">

    <!-- Fonts preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Vite assets (Tailwind CSS + Alpine.js) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
    @stack('head')

    <!-- Prevent dark mode flash (FOUC) -->
    <script>
        (function() {
            try {
                if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.removeAttribute('data-theme');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body class="h-full bg-[var(--bg-page)]" x-data="themeSystem()" x-init="initTheme()">

    <!-- ── Sidebar Overlay (mobile) ───────────────────────────────────── -->
    <div
        x-data="sidebar"
        x-on:keydown.escape.window="close"
        :class="{ 'sidebar-is-collapsed': collapsed }"
        class="relative h-full"
    >
        <!-- Mobile overlay backdrop -->
        <div
            x-show="mobileOpen && !isDesktop"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm z-30 lg:hidden"
            @click="close"
            x-cloak
        ></div>

        <!-- ── Sidebar ────────────────────────────────────────────────── -->
        <aside
            :class="{ 'open': mobileOpen }"
            class="sidebar"
            id="sidebar"
            aria-label="Main navigation"
        >
            <!-- Logo -->
            <div class="sidebar-logo">
                <div class="sidebar-logo-icon">
                    <img src="{{ $appIcon }}" alt="DGT System logo" class="h-full w-full object-contain">
                </div>
                <div>
                    <div class="sidebar-logo-text">DGT System</div>
                    <div class="sidebar-logo-sub">Digital & CRM</div>
                </div>
                <!-- Desktop collapse btn -->
                <button type="button"
                        class="sidebar-collapse-btn hidden lg:inline-flex"
                        @click="toggleCollapse()"
                        aria-label="Collapse sidebar"
                        title="Collapse sidebar">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                    </svg>
                </button>
                <!-- Mobile close btn -->
                <button type="button"
                        class="sidebar-mobile-close-btn lg:hidden"
                        @click="close()"
                        aria-label="Close menu">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="sidebar-nav" role="navigation">

                <!-- Main -->
                @can('dashboard.view')
                <span class="sidebar-section-label">Main</span>

                <a href="{{ route('dashboard') }}"
                   class="sidebar-item {{ request()->routeIs('dashboard*') ? 'active' : '' }}"
                   id="nav-dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
                    </svg>
                    Dashboard
                </a>
                @endcan

                <!-- All Members Directory -->
                <a href="{{ route('members.index') }}"
                   class="sidebar-item {{ request()->routeIs('members.*') ? 'active' : '' }}"
                   id="nav-all-members">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                    </svg>
                    All Members
                </a>

                <!-- Notes -->
                <span class="sidebar-section-label">Notes</span>
                <a href="{{ route('notes.team') }}"
                   class="sidebar-item {{ request()->routeIs('notes.team*') ? 'active' : '' }}"
                   id="nav-notes-team">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    Team Note
                </a>
                <a href="{{ route('notes.private') }}"
                   class="sidebar-item {{ request()->routeIs('notes.private*') ? 'active' : '' }}"
                   id="nav-notes-private">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    Private Note
                </a>

                <!-- Digital Team -->
                @can('kanban.view')
                <span class="sidebar-section-label">Digital Team</span>
                <?php
                    $sidebarWebTools = \App\Models\Setting::externalToolsForGroup('board', true);
                    $sidebarSystemTools = \App\Models\Setting::externalToolsForGroup('generator', true);
                    $sidebarWorkspaceTools = \App\Models\Setting::externalToolsForGroup('workspace', true);
                    $staticAiTools = \App\Models\Setting::externalToolsForGroup('ai', true);
                    $dynamicAiTools = json_decode(\App\Models\Setting::get('custom_ai_tools', '[]'), true);
                    $sidebarAiTools = array_merge($staticAiTools, $dynamicAiTools);
                    $canSeeApprovalQueue = auth()->user()->can('kanban.approve');
                ?>

                <a href="{{ route('boards.workspaces') }}"
                   class="sidebar-item {{ request()->routeIs('boards.*') ? 'active' : '' }}"
                   id="nav-boards">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
                    </svg>
                    Boards
                </a>

                {{-- Social Media Team --}}
                @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'social_admin', 'social_qc']))
                <a href="{{ route('social-media.dashboard') }}"
                   class="sidebar-item {{ request()->routeIs('social-media.*') ? 'active' : '' }}"
                   id="nav-social-media">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z"/>
                    </svg>
                    Social Media Team
                </a>
                @endif

                @if($canSeeApprovalQueue)
                <a href="{{ route('approvals.index') }}"
                   class="sidebar-item {{ request()->routeIs('approvals.*') ? 'active' : '' }}"
                   id="nav-approvals">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375"/>
                    </svg>
                    Approval Queue
                </a>
                @endif

                @if(auth()->user()->isQcOrSupervisor())
                <a href="{{ route('boards.reports.personal') }}"
                   class="sidebar-item {{ request()->routeIs('boards.reports.personal') ? 'active' : '' }}"
                   id="nav-personal-report">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                    </svg>
                    Personal Report
                </a>
                @endif

                @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'digital-team', 'boss']))
                {{-- All Websites accordion sub-menu --}}
                <div x-data="{ wsOpen: localStorage.getItem('dgt-websites-menu-open') === 'true' || {{ request()->routeIs('websites.*') ? 'true' : 'false' }} }" class="sidebar-accordion-group">
                    <button
                        @click="wsOpen = !wsOpen; localStorage.setItem('dgt-websites-menu-open', wsOpen)"
                        type="button"
                        class="sidebar-item w-full flex items-center justify-between text-left {{ request()->routeIs('websites.*') ? 'active' : '' }}"
                        aria-label="Toggle Websites menu"
                        id="nav-websites-toggle"
                    >
                        <span class="flex items-center gap-[0.625rem]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-[18px] h-[18px]">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253M3 12a8.959 8.959 0 0 0 .284 2.253" />
                            </svg>
                            <span>All Websites</span>
                        </span>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2.5"
                            stroke="currentColor"
                            class="w-3.5 h-3.5 transition-transform duration-200"
                            :class="{ 'rotate-180': wsOpen }"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div
                        id="submenu-websites"
                        x-show="wsOpen"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 transform -translate-y-2"
                        x-transition:enter-end="opacity-100 transform translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 transform translate-y-0"
                        x-transition:leave-end="opacity-0 transform -translate-y-2"
                        x-cloak
                        class="sidebar-submenu-list mt-1 space-y-1 relative"
                    >
                        {{-- Website List --}}
                        <a href="{{ route('websites.index') }}"
                           class="sidebar-submenu-item {{ request()->routeIs('websites.index') ? 'active' : '' }}"
                           id="nav-website-list">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                            <span>Website List</span>
                        </a>

                        {{-- All Websites Dashboard --}}
                        <a href="{{ route('websites.dashboard') }}"
                           class="sidebar-submenu-item {{ request()->routeIs('websites.dashboard*') ? 'active' : '' }}"
                           id="nav-websites-dashboard">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                            </svg>
                            <span>All Websites Dashboard</span>
                        </a>
                    </div>
                </div>
                @endif

                <?php
                    $weeklyReport = collect(\App\Models\Setting::externalToolsForGroup('board', true))->firstWhere('key', 'weekly_report_url');
                ?>
                @if($weeklyReport)
                    <a href="{{ $weeklyReport['url'] }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="sidebar-item">
                        @if(isset($weeklyReport['icon_url']) && $weeklyReport['icon_url'])
                            <img src="{{ $weeklyReport['icon_url'] }}" class="w-5 h-5 object-contain" alt="">
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        @endif
                        {{ $weeklyReport['name'] ?? 'Weekly Report' }}
                    </a>
                @endif

                @if(count($sidebarWorkspaceTools))
                    @php
                        $userEmail = auth()->user()->email ?? '';
                        // The shared Digital Drive — all @kiuq.com members have access
                        $digitalDriveUrl = 'https://drive.google.com/drive/shared-drives?authuser=' . urlencode($userEmail);
                    @endphp
                    <div class="sidebar-tool-group">
                        <span class="sidebar-tool-heading">Google Workspace</span>
                        @foreach($sidebarWorkspaceTools as $tool)
                            @php
                                $toolUrl = $tool['url'] ?? '#';
                                // For Google Drive: open digital@typhonmachinery.com's drive directly
                                // All @kiuq.com accounts already have access granted by typhonmachinery
                                if (str_contains($toolUrl, 'drive.google.com')) {
                                    $toolUrl = 'https://drive.google.com/drive/my-drive?authuser=digital@typhonmachinery.com';
                                } elseif (str_contains($toolUrl, 'docs.google.com') || str_contains($toolUrl, 'sheets.google.com') || str_contains($toolUrl, 'slides.google.com') || str_contains($toolUrl, 'mail.google.com') || str_contains($toolUrl, 'calendar.google.com')) {
                                    // Append authuser to other Google services
                                    $userEmail = auth()->user()->email ?? '';
                                    $separator = str_contains($toolUrl, '?') ? '&' : '?';
                                    $toolUrl = $toolUrl . $separator . 'authuser=' . urlencode($userEmail);
                                }
                            @endphp
                            <a href="{{ $toolUrl }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="sidebar-item sidebar-tool-item"
                               title="{{ $tool['label'] }}">
                                @if(isset($tool['icon_url']) && $tool['icon_url'])
                                    <img src="{{ $tool['icon_url'] }}" class="h-4 w-4 object-contain" alt="">
                                @else
                                    <x-external-tool-icon :name="$tool['icon']" />
                                @endif
                                <span>{{ $tool['short_label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <?php
                    $sidebarWebTools = collect(\App\Models\Setting::externalToolsForGroup('board', true))
                        ->reject(function($t) {
                            return $t['key'] === 'weekly_report_url';
                        })
                        ->all();
                ?>

                @if(count($sidebarWebTools))
                    <div class="sidebar-tool-group">
                        <span class="sidebar-tool-heading">eBay &amp; Web Supporter</span>
                        @foreach($sidebarWebTools as $tool)
                            <a href="{{ $tool['url'] }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="sidebar-item sidebar-tool-item sidebar-tool-item-web"
                               title="{{ $tool['label'] }}">
                                @if(isset($tool['icon_url']) && $tool['icon_url'])
                                    <img src="{{ $tool['icon_url'] }}" class="h-4 w-4 object-contain" alt="">
                                @else
                                    <x-external-tool-icon :name="$tool['icon']" />
                                @endif
                                <span>{{ $tool['short_label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if(count($sidebarSystemTools))
                    <div class="sidebar-tool-group sidebar-tool-group-system">
                        <span class="sidebar-tool-heading">System Supporter</span>
                        @foreach($sidebarSystemTools as $tool)
                            <a href="{{ $tool['url'] }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="sidebar-item sidebar-tool-item sidebar-tool-item-system"
                               title="{{ $tool['label'] }}">
                                @if(isset($tool['icon_url']) && $tool['icon_url'])
                                    <img src="{{ $tool['icon_url'] }}" class="h-4 w-4 object-contain" alt="">
                                @else
                                    <x-external-tool-icon :name="$tool['icon']" />
                                @endif
                                <span>{{ $tool['short_label'] }}</span>
                            </a>
                         @endforeach
                    </div>
                @endif

                @if(count($sidebarAiTools))
                    <div x-data="{ open: localStorage.getItem('dgt-ai-menu-open') === 'true' }" class="sidebar-accordion-group mt-3">
                        <button 
                            @click="open = !open; localStorage.setItem('dgt-ai-menu-open', open)"
                            type="button"
                            class="sidebar-item w-full flex items-center justify-between text-left"
                            aria-label="Toggle AI Tools"
                        >
                            <span class="flex items-center gap-[0.625rem]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-[18px] h-[18px]">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.25l-.25-1.5-1.5-.25 1.5-.25.25-1.5.25 1.5 1.5.25-1.5.25-.25 1.5ZM18.259 18.75l-.25-1.5-1.5-.25 1.5-.25.25-1.5.25 1.5 1.5.25-1.5.25-.25 1.5Z" />
                                </svg>
                                <span>AI Tools</span>
                            </span>
                            <svg 
                                xmlns="http://www.w3.org/2000/svg" 
                                fill="none" 
                                viewBox="0 0 24 24" 
                                stroke-width="2.5" 
                                stroke="currentColor" 
                                class="w-3.5 h-3.5 transition-transform duration-200"
                                :class="{ 'rotate-180': open }"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div 
                            id="submenu-ai"
                            x-show="open"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 transform -translate-y-2"
                            x-transition:enter-end="opacity-100 transform translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 transform translate-y-0"
                            x-transition:leave-end="opacity-0 transform -translate-y-2"
                            x-cloak
                            class="sidebar-submenu-list mt-1 space-y-1 relative"
                        >
                             @foreach($sidebarAiTools as $tool)
                                <a href="{{ $tool['url'] }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="sidebar-submenu-item"
                                   title="{{ $tool['label'] }}">
                                    @if(isset($tool['icon_url']) && $tool['icon_url'])
                                        <img src="{{ $tool['icon_url'] }}" class="h-4.5 w-4.5 object-contain" alt="">
                                    @else
                                        <x-external-tool-icon name="sparkles" />
                                    @endif
                                    <span>{{ $tool['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @endcan

                <!-- CRM -->
                @canany(['crm.view', 'sales.view'])
                <span class="sidebar-section-label">CRM & Sales</span>

                @can('crm.view')
                <a href="{{ route('crm.dashboard') }}"
                   class="sidebar-item {{ request()->routeIs('crm.dashboard') ? 'active' : '' }}"
                   id="nav-crm-dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
                    </svg>
                    CRM Dashboard
                </a>

                <a href="{{ route('crm.website.index') }}"
                   class="sidebar-item {{ request()->routeIs('crm.website.*') ? 'active' : '' }}"
                   id="nav-website-crm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253M3 12a8.959 8.959 0 0 0 .284 2.253"/>
                    </svg>
                    Website CRM
                </a>

                <a href="{{ route('crm.ebay.index') }}"
                   class="sidebar-item {{ request()->routeIs('crm.ebay.*') ? 'active' : '' }}"
                   id="nav-ebay-crm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                    </svg>
                    eBay CRM
                </a>

                <a href="{{ route('crm.logistics.index') }}"
                   class="sidebar-item {{ request()->routeIs('crm.logistics.*') ? 'active' : '' }}"
                   id="nav-logistic-crm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                    </svg>
                    Logistic CRM
                </a>

                <a href="{{ route('crm.customers.index') }}"
                   class="sidebar-item {{ request()->routeIs('crm.customers.*') ? 'active' : '' }}"
                   id="nav-customers">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                    </svg>
                    Customers
                </a>
                @endcan

                @can('sales.view')
                <a href="{{ route('crm.pipeline.index') }}"
                   class="sidebar-item {{ request()->routeIs('crm.pipeline.*') ? 'active' : '' }}"
                   id="nav-pipeline">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                    </svg>
                    Sales Pipeline
                </a>
                @endcan
                @endcanany


                <!-- Reports -->
                @can('reports.view')
                <span class="sidebar-section-label">Analytics</span>

                <a href="{{ route('reports.index') }}"
                   class="sidebar-item {{ request()->routeIs('reports.*') ? 'active' : '' }}"
                   id="nav-reports">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z"/>
                    </svg>
                    Reports
                </a>
                @endcan

                <!-- Admin -->
                @if(auth()->check() && (auth()->user()->canany(['users.view', 'roles.view', 'security.view', 'backup.view']) || auth()->user()->hasAnyRole(['super-admin', 'admin-crm', 'sales-crm'])))
                <span class="sidebar-section-label">Administration</span>

                @can('users.view')
                <a href="{{ route('admin.users.index') }}"
                   class="sidebar-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                   id="nav-users">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                    </svg>
                    Users
                </a>
                
                @hasanyrole('super-admin|admin-digital')
                <a href="{{ route('admin.labels.index') }}"
                   class="sidebar-item {{ request()->routeIs('admin.labels.*') ? 'active' : '' }}"
                   id="nav-labels">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/>
                    </svg>
                    Labels
                </a>
                @endhasanyrole
                @endcan

                @can('security.view')
                <a href="{{ route('admin.security.index') }}"
                   class="sidebar-item {{ request()->routeIs('admin.security.*') ? 'active' : '' }}"
                   id="nav-security">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                    </svg>
                    Security
                </a>
                @endcan

                @hasanyrole('super-admin|admin-crm|sales-crm')
                <a href="{{ route('admin.emails.index') }}"
                   class="sidebar-item {{ request()->routeIs('admin.emails.*') ? 'active' : '' }}"
                   aria-label="Manage Emails" title="Email Accounts">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                    </svg>
                    <span>All Mails</span>
                </a>
                @endhasanyrole

                @hasanyrole('super-admin|admin-digital')
                <a href="{{ route('admin.settings.index') }}"
                   class="sidebar-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"
                   aria-label="External Systems" title="External Systems">
                    <x-external-tool-icon name="link" class="w-5 h-5 flex-shrink-0" />
                    <span>External Systems</span>
                </a>
                <a href="{{ route('admin.maintenance.index') }}"
                   class="sidebar-item {{ request()->routeIs('admin.maintenance.*') ? 'active' : '' }}"
                   aria-label="Maintenance System" title="Maintenance System">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.829M11.42 15.17l-3.976-3.976c-.845-.845-2.023-1.12-3.136-.788l-.513.153a.75.75 0 01-.933-.933l.153-.513c.332-1.113.057-2.291-.788-3.136l-3.976-3.976a2.652 2.652 0 013.75-3.75l3.976 3.976c.845.845 2.023 1.12 3.136.788l.513-.153a.75.75 0 01.933.933l-.153.513c-.332 1.113-.057 2.291.788 3.136l3.976 3.976A2.652 2.652 0 0111.42 15.17z" />
                    </svg>
                    <span>Maintenance System</span>
                </a>
                @endhasanyrole
                @endif

            </nav>

            <!-- User profile at bottom -->
            <div class="sidebar-footer">
                <div x-data="dropdown" class="relative">
                    <button
                        @click="toggle"
                        class="sidebar-item w-full text-left"
                        id="sidebar-user-menu"
                        aria-haspopup="true"
                        :aria-expanded="open"
                    >
                        <img
                            src="{{ auth()->user()->avatar_url }}"
                            alt="{{ auth()->user()->name }}"
                            class="avatar avatar-sm"
                        >
                        <div class="flex-1 min-w-0">
                            <div class="text-[0.8125rem] font-semibold text-slate-200 truncate">{{ auth()->user()->name }}</div>
                            <div class="text-[0.7rem] text-slate-400 truncate">{{ auth()->user()->role_display }}</div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-slate-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </button>

                    <!-- User dropdown menu -->
                    <div
                        x-show="open"
                        @click.outside="close"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="dropdown-menu absolute bottom-full left-0 right-0 mb-2"
                        x-cloak
                        role="menu"
                    >
                        <a href="{{ route('profile.show') }}" class="dropdown-item hover:!bg-indigo-600 hover:!text-white" role="menuitem" id="menu-profile">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                            </svg>
                            My Profile
                        </a>
                        <a href="{{ route('settings') }}" class="dropdown-item hover:!bg-indigo-600 hover:!text-white" role="menuitem" id="menu-settings">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            </svg>
                            Settings
                        </a>
                        <hr class="border-[var(--border-color)] my-1">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item danger w-full hover:!bg-rose-600 hover:!text-white" role="menuitem" id="menu-logout">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25"/>
                                </svg>
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <script>
                (() => {
                    // Pre-expand submenus based on localStorage so scroll height is correct immediately
                    const websitesOpen = localStorage.getItem('dgt-websites-menu-open') === 'true' || {{ request()->routeIs('websites.*') ? 'true' : 'false' }};
                    if (websitesOpen) {
                        const el = document.getElementById('submenu-websites');
                        if (el) {
                            el.style.display = 'block';
                            el.removeAttribute('x-cloak');
                        }
                    }
                    const aiOpen = localStorage.getItem('dgt-ai-menu-open') === 'true';
                    if (aiOpen) {
                        const el = document.getElementById('submenu-ai');
                        if (el) {
                            el.style.display = 'block';
                            el.removeAttribute('x-cloak');
                        }
                    }

                    const sidebar = document.getElementById('sidebar');
                    if (! sidebar) return;

                    const savedScrollTop = Number(localStorage.getItem('dgt-sidebar-scroll-top') || 0);
                    if (savedScrollTop > 0) {
                        sidebar.scrollTop = savedScrollTop;
                        if (sidebar.scrollTop >= savedScrollTop - 5) {
                            sidebar.dataset.scrollRestored = 'true';
                        }
                    }
                })();
            </script>
        </aside>

        <!-- ── Main Content ─────────────────────────────────────────────── -->
        <div class="main-wrapper">

            <!-- Top Navigation Bar -->
            <header class="topbar" role="banner">
                <div class="flex items-center gap-3">
                    <!-- Toggle/Expand Sidebar Button (Desktop) -->
                    <button type="button"
                            x-show="collapsed"
                            @click="expandSidebar()"
                            class="sidebar-expand-btn btn btn-secondary btn-icon hidden lg:inline-flex active:scale-95 transition-all duration-150"
                            title="Show sidebar"
                            aria-label="Show sidebar"
                            x-cloak>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>

                    <!-- Hamburger Button (Mobile) -->
                    <button type="button"
                            @click="toggleMobile()"
                            class="mobile-menu-btn lg:hidden"
                            title="Toggle menu"
                            aria-label="Toggle menu">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>

                    <button type="button"
                            onclick="window.dgtGoBack ? window.dgtGoBack('{{ route('boards.workspaces') }}') : (window.location.href='{{ route('boards.workspaces') }}')"
                            class="mobile-back-btn"
                            title="Back"
                            aria-label="Back to previous page">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        <span class="hidden sm:inline text-xs font-bold">Back</span>
                    </button>
                    <!-- Page Title (visible on all screens) -->
                    <div class="mobile-topbar-title">
                        <p class="mobile-topbar-title-text">@yield('title', 'DGT System')</p>
                    </div>
                </div>

                <div class="topbar-actions ml-auto">
                    <!-- Dark Mode Pill Toggle (desktop) -->
                    <div class="theme-pill-toggle" @click="toggleTheme()" :title="theme === 'dark' ? 'Switch to Light' : 'Switch to Dark'" role="button" tabindex="0" @keydown.enter="toggleTheme()" @keydown.space.prevent="toggleTheme()" aria-label="Toggle dark mode" id="topbar-theme-toggle">
                        <!-- Sun icon -->
                        <span class="theme-pill-icon" :class="{ 'active': theme !== 'dark' }">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                            </svg>
                        </span>
                        <!-- Sliding knob -->
                        <span class="theme-pill-knob"></span>
                        <!-- Moon icon -->
                        <span class="theme-pill-icon" :class="{ 'active': theme === 'dark' }">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                            </svg>
                        </span>
                    </div>

                    <!-- Dark Mode Icon-only Button (mobile only) -->
                    <button type="button"
                            @click="toggleTheme()"
                            class="mobile-theme-icon-btn hidden items-center justify-center w-9 h-9 rounded-xl border border-[var(--border-color)] bg-[var(--bg-card)] text-[var(--text-secondary)] transition-all active:scale-90"
                            :title="theme === 'dark' ? 'Switch to Light' : 'Switch to Dark'"
                            aria-label="Toggle dark mode">
                        <!-- Sun icon (light mode) -->
                        <svg x-show="theme === 'dark'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                        </svg>
                        <!-- Moon icon (dark mode) -->
                        <svg x-show="theme !== 'dark'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                        </svg>
                    </button>

                    <div class="relative" x-data="notificationSystem()" x-init="initNotifications()">
                        <button class="btn btn-secondary btn-icon relative hover:!bg-blue-600 hover:!text-white hover:!border-blue-600 transition-all duration-150"
                                @click="toggleOpen()"
                                aria-label="Open notifications"
                                :aria-expanded="open">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                            </svg>
                            <template x-if="unreadCount > 0">
                                <span class="absolute -top-1.5 -right-1.5 min-w-4 h-4 px-1 rounded-full bg-rose-500 text-white text-[9px] font-black leading-4 text-center shadow shadow-rose-500/30 border border-white" x-text="badgeCount()"></span>
                            </template>
                        </button>

                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="notif-panel absolute right-0 mt-2 w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl z-50 sm:w-96"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             role="menu">
                            <div class="border-b border-slate-200/70 bg-slate-50/80 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-black text-slate-900">Recent activity</h3>
                                        <p class="mt-0.5 text-xs font-semibold text-slate-500" x-text="unreadCount > 0 ? unreadCount + ' unread notification' + (unreadCount === 1 ? '' : 's') : 'All notifications are read'"></p>
                                    </div>
                                    <button type="button"
                                            x-show="unreadCount > 0"
                                            @click="markAllAsRead()"
                                            class="rounded-lg px-2 py-1 text-[11px] font-black text-indigo-700 transition hover:bg-indigo-50">
                                        Mark all as read
                                    </button>
                                </div>
                                <button type="button"
                                        @click="requestBrowserPermission()"
                                        class="mt-3 flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 text-left text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                                        :disabled="permissionBusy">
                                    <span class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31"/>
                                        </svg>
                                        Browser notifications
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase text-slate-500" x-text="browserPermissionLabel()"></span>
                                </button>
                                
                                <button type="button"
                                        @click="toggleMute()"
                                        class="mt-2 flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 text-left text-xs font-bold text-slate-700 transition hover:bg-slate-50">
                                    <span class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                                        </svg>
                                        Mute in-app popups
                                    </span>
                                    <div class="relative inline-flex h-4 w-7 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
                                         :class="notificationsMuted ? 'bg-indigo-600' : 'bg-slate-200'"
                                         role="switch" :aria-checked="notificationsMuted.toString()">
                                        <span aria-hidden="true" class="pointer-events-none inline-block h-3 w-3 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                              :class="notificationsMuted ? 'translate-x-3' : 'translate-x-0'"></span>
                                    </div>
                                </button>
                            </div>

                            <div class="max-h-96 overflow-y-auto divide-y divide-slate-100 scrollbar-thin">
                                <template x-for="notif in notifications" :key="notif.id">
                                    <button type="button"
                                            class="flex w-full items-start gap-3 p-3.5 text-left transition hover:bg-slate-50"
                                            :class="isUnread(notif) ? 'bg-indigo-50/40' : 'bg-white'"
                                            @click="clickNotification(notif)">
                                        <img :src="actorAvatar(notif)"
                                             class="mt-0.5 h-9 w-9 flex-shrink-0 rounded-full border border-slate-200 object-cover shadow-sm"
                                             :alt="actorName(notif)">
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center gap-2">
                                                <span class="truncate text-xs font-black text-slate-900" x-text="actorName(notif)"></span>
                                                <span class="flex-shrink-0 text-[10px] font-bold text-slate-400" x-text="notificationTime(notif)"></span>
                                            </span>
                                            <span class="mt-0.5 block text-xs font-semibold leading-5 text-slate-600" x-text="notificationAction(notif)"></span>
                                            <span class="mt-1 flex flex-wrap items-center gap-1.5">
                                                <span x-show="boardName(notif)" class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-black text-slate-600" x-text="boardName(notif)"></span>
                                                <span x-show="cardName(notif)" class="rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-black text-indigo-700" x-text="cardName(notif)"></span>
                                            </span>
                                        </span>
                                        <span class="mt-2 h-2 w-2 flex-shrink-0 rounded-full"
                                              :class="isUnread(notif) ? 'bg-indigo-600 shadow-sm shadow-indigo-600/40' : 'bg-slate-200'"
                                              :title="isUnread(notif) ? 'Unread' : 'Read'"></span>
                                    </button>
                                </template>
                                <template x-if="notifications.length === 0">
                                    <div class="px-4 py-12 text-center">
                                        <p class="text-sm font-black text-slate-700">No recent activity</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-400">New board and card updates will appear here.</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>



                    <div class="relative" x-data="dropdown">
                        <button type="button"
                                @click="toggle"
                                class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-2 py-1.5 text-left shadow-sm transition hover:border-indigo-600 hover:bg-indigo-50 dark-user-btn"
                                id="topbar-user-menu"
                                aria-haspopup="true"
                                :aria-expanded="open">
                            <img src="{{ auth()->user()->avatar_url }}"
                                 alt="{{ auth()->user()->name }}"
                                 onerror="this.onerror=null; this.src='{{ \App\Models\User::initialsAvatarDataUri(auth()->user()->name, auth()->user()->avatar_color) }}';"
                                 class="avatar avatar-sm ring-2 ring-white">
                            <span class="hidden sm:block min-w-0">
                                <span class="block max-w-36 truncate text-sm font-black leading-none text-slate-800">{{ auth()->user()->name }}</span>
                                <span class="mt-0.5 block max-w-36 truncate text-[11px] font-semibold text-slate-400">{{ auth()->user()->role_display }}</span>
                            </span>
                            <svg class="hidden h-4 w-4 text-slate-400 sm:block" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </button>

                        <div x-show="open"
                             @click.outside="close"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="dropdown-menu absolute right-0 mt-2 w-64"
                             x-cloak
                             role="menu">
                            <div class="flex items-center gap-3 border-b border-slate-100 px-2 py-2.5">
                                <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" onerror="this.onerror=null; this.src='{{ \App\Models\User::initialsAvatarDataUri(auth()->user()->name, auth()->user()->avatar_color) }}';" class="avatar avatar-md">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-slate-900">{{ auth()->user()->name }}</p>
                                    <p class="truncate text-xs font-semibold text-slate-500">{{ auth()->user()->email }}</p>
                                </div>
                            </div>
                            <a href="{{ route('profile.show') }}" class="dropdown-item mt-1 hover:!bg-indigo-600 hover:!text-white" role="menuitem" id="topbar-menu-profile">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0"/>
                                </svg>
                                My Profile
                            </a>
                            <a href="{{ route('settings') }}" class="dropdown-item hover:!bg-indigo-600 hover:!text-white" role="menuitem" id="topbar-menu-settings">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87l.22.127c.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992v.255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124l-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87l-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991v-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124l.22-.128c.332-.183.582-.495.644-.869l.214-1.28Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                </svg>
                                Settings
                            </a>
                            <hr class="border-[var(--border-color)] my-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item danger w-full hover:!bg-rose-600 hover:!text-white" role="menuitem" id="topbar-menu-logout">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25"/>
                                    </svg>
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Flash Messages -->
            @if (session('success'))
                <div class="mx-6 mt-4">
                    <div class="alert alert-success animate-fade-in" role="alert">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/>
                        </svg>
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="mx-6 mt-4">
                    <div class="alert alert-error animate-fade-in" role="alert">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm-1.5-9.5a1.5 1.5 0 1 1 3 0v4a1.5 1.5 0 0 1-3 0v-4Zm1.5 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                        </svg>
                        {{ session('error') }}
                    </div>
                </div>
            @endif

            <!-- Main Content Slot -->
            <main class="page-content mobile-page-content" id="main-content" role="main">
                @yield('content')
            </main>

        </div>
    </div>

    <!-- ── Mobile Bottom Navigation Bar ──────────────────────────────────── -->
    <nav class="mobile-bottom-nav lg:hidden" id="mobile-bottom-nav" aria-label="Mobile navigation">
        <div class="mobile-bottom-nav-inner">

            @can('dashboard.view')
            <a href="{{ route('dashboard') }}"
               class="mobile-nav-item {{ request()->routeIs('dashboard*') ? 'active' : '' }}"
               aria-label="Dashboard">
                <span class="mobile-nav-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
                    </svg>
                </span>
                <span class="mobile-nav-label">Home</span>
            </a>
            @endcan

            @can('kanban.view')
            <a href="{{ route('boards.workspaces') }}"
               class="mobile-nav-item {{ request()->routeIs('boards.*') ? 'active' : '' }}"
               aria-label="Boards">
                <span class="mobile-nav-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125Z"/>
                    </svg>
                </span>
                <span class="mobile-nav-label">Boards</span>
            </a>
            @endcan

            @canany(['crm.view', 'sales.view'])
            <a href="{{ route('crm.dashboard') }}"
               class="mobile-nav-item {{ request()->routeIs('crm.*') ? 'active' : '' }}"
               aria-label="CRM">
                <span class="mobile-nav-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                    </svg>
                </span>
                <span class="mobile-nav-label">CRM</span>
            </a>
            @endcanany

            <a href="{{ route('notes.team') }}"
               class="mobile-nav-item {{ request()->routeIs('notes.*') ? 'active' : '' }}"
               aria-label="Notes">
                <span class="mobile-nav-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </span>
                <span class="mobile-nav-label">Notes</span>
            </a>

            <!-- More / Menu trigger -->
            <button type="button"
                    class="mobile-nav-item {{ request()->routeIs('admin.*') || request()->routeIs('reports.*') || request()->routeIs('profile.*') ? 'active' : '' }}"
                    x-data
                    @click="$dispatch('open-mobile-sidebar')"
                    aria-label="More">
                <span class="mobile-nav-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </span>
                <span class="mobile-nav-label">More</span>
            </button>

        </div>
    </nav>

    {{-- Global Toast Container --}}
    <div id="toast-container" class="fixed top-5 right-5 z-50 space-y-3 pointer-events-none max-w-[calc(100vw-2rem)] sm:max-w-sm"></div>

    <script>
    window.dgtInitialsAvatar = function(name = 'User', color = '#4f46e5') {
        const cleanName = String(name || 'User').trim().replace(/\s+/g, ' ');
        const parts = cleanName.includes('@') ? [cleanName.split('@')[0]] : cleanName.split(' ').filter(Boolean);
        const initials = (parts.length > 1
            ? (parts[0][0] || '') + (parts[parts.length - 1][0] || '')
            : (parts[0] || 'U').slice(0, 2)
        ).toUpperCase() || 'U';
        const safeColor = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(color) ? color : '#4f46e5';
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128"><rect width="128" height="128" rx="64" fill="${safeColor}"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="#fff" font-family="Inter, Arial, sans-serif" font-size="44" font-weight="800">${initials}</text></svg>`;
        return `data:image/svg+xml;base64,${btoa(svg)}`;
    };

    window.dgtEscapeHtml = function(value = '') {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    };

    // Custom Confirmation Modal
    window.confirmModal = function(input) {
        return new Promise((resolve) => {
            const options = typeof input === 'object' && input !== null ? input : { message: input };
            const title = options.title || 'Confirm action';
            const message = options.message || '';
            const confirmText = options.confirmText || 'Confirm';
            const cancelText = options.cancelText || 'Cancel';
            const tone = options.tone || 'danger';
            const toneMap = {
                danger: {
                    icon: 'bg-rose-100 text-rose-600 ring-rose-200',
                    button: 'bg-rose-600 hover:bg-rose-700 shadow-rose-500/20',
                    glow: 'from-rose-500/12',
                },
                warning: {
                    icon: 'bg-amber-100 text-amber-600 ring-amber-200',
                    button: 'bg-amber-500 hover:bg-amber-600 shadow-amber-500/20',
                    glow: 'from-amber-400/14',
                },
            };
            const theme = toneMap[tone] || toneMap.danger;
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 bg-slate-950/60 backdrop-blur-md z-[9999] flex items-center justify-center p-4 opacity-0 transition-opacity duration-200';
            
            const modal = document.createElement('div');
            modal.className = 'relative overflow-hidden bg-white/85 backdrop-blur-2xl border border-white/70 rounded-3xl shadow-2xl max-w-md w-full p-6 transform scale-95 opacity-0 transition-all duration-200 ring-1 ring-slate-900/5';
            
            modal.innerHTML = `
                <div class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b ${theme.glow} to-transparent"></div>
                <div class="relative flex items-start gap-3 mb-4">
                    <div class="w-11 h-11 rounded-2xl ${theme.icon} ring-1 flex items-center justify-center flex-shrink-0 shadow-sm">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base font-black text-slate-900 leading-tight"></h3>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Please confirm before continuing.</p>
                    </div>
                </div>
                <p class="relative text-sm text-slate-600 font-semibold mb-6 leading-relaxed" id="confirm-modal-msg"></p>
                <div class="relative flex gap-3">
                    <button id="btn-cancel" class="flex-1 py-2.5 px-4 rounded-xl text-xs font-black text-slate-700 bg-white/80 border border-slate-200 hover:bg-slate-100 transition-colors">${cancelText}</button>
                    <button id="btn-confirm" class="flex-1 py-2.5 px-4 rounded-xl text-xs font-black text-white ${theme.button} shadow-lg transition-all">${confirmText}</button>
                </div>
            `;
            
            modal.querySelector('h3').textContent = title;
            modal.querySelector('#confirm-modal-msg').innerHTML = message;
            
            overlay.appendChild(modal);
            document.body.appendChild(overlay);
            modal.querySelector('#btn-cancel').focus();
            
            requestAnimationFrame(() => {
                overlay.classList.remove('opacity-0');
                modal.classList.remove('scale-95', 'opacity-0');
            });
            
            let closed = false;
            const close = (result) => {
                if (closed) return;
                closed = true;
                document.removeEventListener('keydown', escapeHandler);
                overlay.classList.add('opacity-0');
                modal.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    if (overlay.parentNode) document.body.removeChild(overlay);
                    resolve(result);
                }, 200);
            };
            
            const escapeHandler = (event) => {
                if (event.key === 'Escape') close(false);
            };

            document.addEventListener('keydown', escapeHandler);
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) close(false);
            });
            modal.querySelector('#btn-cancel').addEventListener('click', () => close(false));
            modal.querySelector('#btn-confirm').addEventListener('click', () => close(true));
        });
    };

    window.promptModal = function(input = {}) {
        return new Promise((resolve) => {
            const options = typeof input === 'object' && input !== null ? input : { value: String(input || '') };
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 bg-slate-950/60 backdrop-blur-md z-[9999] flex items-center justify-center p-4 opacity-0 transition-opacity duration-200';
            const modal = document.createElement('div');
            modal.className = 'relative overflow-hidden bg-white/90 backdrop-blur-2xl border border-white/70 rounded-3xl shadow-2xl max-w-md w-full p-6 transform scale-95 opacity-0 transition-all duration-200 ring-1 ring-slate-900/5';
            modal.innerHTML = `
                <div class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-indigo-500/10 to-transparent"></div>
                <div class="relative mb-4">
                    <h3 class="text-base font-black text-slate-900"></h3>
                    <p class="mt-1 text-sm font-semibold text-slate-500"></p>
                </div>
                <label class="relative block">
                    <span class="mb-1.5 block text-xs font-black uppercase tracking-wide text-slate-400"></span>
                    <input id="prompt-modal-input" type="text" class="form-input w-full text-sm" autocomplete="off">
                    <span id="prompt-modal-error" class="mt-2 hidden text-xs font-bold text-rose-600"></span>
                </label>
                <div class="relative mt-6 flex gap-3">
                    <button id="prompt-cancel" class="btn btn-secondary flex-1 py-2.5"></button>
                    <button id="prompt-confirm" class="btn btn-primary flex-1 py-2.5"></button>
                </div>
            `;

            const title = modal.querySelector('h3');
            const message = modal.querySelector('p');
            const label = modal.querySelector('label span');
            const field = modal.querySelector('#prompt-modal-input');
            const error = modal.querySelector('#prompt-modal-error');
            const cancel = modal.querySelector('#prompt-cancel');
            const confirm = modal.querySelector('#prompt-confirm');

            title.textContent = options.title || 'Enter details';
            message.textContent = options.message || '';
            label.textContent = options.inputLabel || 'Value';
            field.value = options.value || '';
            field.placeholder = options.placeholder || '';
            field.readOnly = Boolean(options.readonly);
            cancel.textContent = options.cancelText || 'Cancel';
            confirm.textContent = options.confirmText || 'Save';

            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            let closed = false;
            const close = (value) => {
                if (closed) return;
                closed = true;
                document.removeEventListener('keydown', keyHandler);
                overlay.classList.add('opacity-0');
                modal.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    overlay.remove();
                    resolve(value);
                }, 200);
            };

            const submit = () => {
                const value = field.value.trim();
                if (options.required !== false && !value) {
                    error.textContent = options.errorText || 'Please enter a value.';
                    error.classList.remove('hidden');
                    field.focus();
                    return;
                }
                close(value);
            };

            const keyHandler = (event) => {
                if (event.key === 'Escape') close(null);
                if (event.key === 'Enter' && document.activeElement === field) submit();
            };

            requestAnimationFrame(() => {
                overlay.classList.remove('opacity-0');
                modal.classList.remove('scale-95', 'opacity-0');
                field.focus();
                field.select();
            });

            document.addEventListener('keydown', keyHandler);
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) close(null);
            });
            cancel.addEventListener('click', () => close(null));
            confirm.addEventListener('click', submit);
        });
    };

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-confirm]');
        if (!form || form.dataset.confirmSubmitting === 'true') return;

        event.preventDefault();
        const ok = await window.confirmModal({
            title: form.dataset.confirmTitle || 'Confirm action',
            message: form.dataset.confirm || 'Are you sure?',
            confirmText: form.dataset.confirmText || 'Confirm',
            tone: form.dataset.confirmTone || 'danger',
        });

        if (ok) {
            form.dataset.confirmSubmitting = 'true';
            form.submit();
        }
    });

    window.playNotificationSound = function() {
        if (localStorage.getItem('dgt_notifications_muted') === 'true') return;
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            const gain = ctx.createGain();
            gain.gain.setValueAtTime(0.0001, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(1.5, ctx.currentTime + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.28);
            gain.connect(ctx.destination);

            [660, 880].forEach((frequency, index) => {
                const oscillator = ctx.createOscillator();
                oscillator.type = 'square';
                oscillator.frequency.setValueAtTime(frequency, ctx.currentTime + index * 0.08);
                oscillator.connect(gain);
                oscillator.start(ctx.currentTime + index * 0.08);
                oscillator.stop(ctx.currentTime + 0.24 + index * 0.08);
            });

            setTimeout(() => ctx.close(), 500);
        } catch (_) {}
    };

    // Global Toast Notification Helper
    window.showToast = function(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `flex items-center gap-3 px-4 py-3 rounded-2xl shadow-2xl text-sm font-bold text-white pointer-events-auto transform translate-x-8 opacity-0 transition-all duration-300 border border-white/10 backdrop-blur-xl ${
            type === 'success' ? 'bg-slate-950/90' : type === 'error' ? 'bg-rose-950/90' : 'bg-slate-900/90'
        }`;
        toast.innerHTML = `
            <span>${type === 'success' ? '✨' : type === 'error' ? '⚠️' : 'ℹ️'}</span>
            <span>${message}</span>
        `;

        container.appendChild(toast);

        // Slide in
        setTimeout(() => {
            toast.classList.remove('translate-x-8', 'opacity-0');
        }, 50);

        // Slide out & remove
        setTimeout(() => {
            toast.classList.add('translate-x-8', 'opacity-0');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 3500);
    };

    // Global Premium Clickable Trello-style Rich Notification Toast Helper
    window.showRichNotificationToast = function(data) {
        if (localStorage.getItem('dgt_notifications_muted') === 'true') return;

        const container = document.getElementById('toast-container');
        if (!container) return;

        const appLogo = data.app_logo || '/favicon.svg';
        const toast = document.createElement('div');
        toast.className = 'flex items-start gap-3 p-4 rounded-3xl shadow-2xl bg-white/95 text-slate-900 border border-slate-200/60 pointer-events-auto transform translate-x-8 opacity-0 transition-all duration-300 max-w-sm cursor-pointer hover:border-slate-300/80 select-none backdrop-blur-2xl ring-1 ring-slate-900/5';
        
        const actorName = data.actor_name || 'System';
        const avatar = data.actor_avatar || window.dgtInitialsAvatar(actorName);
        const subject = data.card_title || data.board_name || data.customer_name || data.lead_name || data.offer_name || data.logistic_name || '';
        const time = data.created_at ? new Date(data.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'now';
        const actionText = (data.description || data.message || 'New activity').replace(/\*/g, '');
        const cardTitleMarkup = data.card_title 
            ? `<span class="mt-2 inline-flex max-w-full rounded-lg border border-indigo-200 bg-indigo-50 px-2 py-1 text-[11px] font-black text-indigo-700">${window.dgtEscapeHtml(data.card_title)}</span>`
            : '';
        
        toast.innerHTML = `
            <div class="flex-shrink-0 flex items-center gap-2">
                <img src="${appLogo}" alt="App" class="h-6 w-6 rounded" />
                <img src="${avatar}" class="h-6 w-6 rounded-full object-cover ring-1 ring-slate-200" alt="" />
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <p class="truncate text-sm font-black text-slate-900">${window.dgtEscapeHtml(actorName)}</p>
                    <span class="ml-auto text-[10px] font-bold text-slate-500">${window.dgtEscapeHtml(time)}</span>
                </div>
                <p class="mt-2 text-sm font-semibold leading-relaxed text-slate-600">${window.dgtEscapeHtml(actionText)}</p>
                ${subject && !data.card_title ? `<span class="mt-2 inline-flex max-w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-black text-slate-700">${window.dgtEscapeHtml(subject)}</span>` : cardTitleMarkup}
            </div>
            <button class="toast-close-btn flex-shrink-0 ml-1 -mt-1 -mr-1 w-6 h-6 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all" aria-label="Close">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        `;
        
        const closeBtn = toast.querySelector('.toast-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toast.classList.add('translate-x-8', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            });
        }
        
        if (data.link) {
            toast.addEventListener('click', (e) => {
                if (!e.target.closest('.toast-close-btn')) {
                    window.location.href = data.link;
                }
            });
        }
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.remove('translate-x-8', 'opacity-0');
            window.playNotificationSound?.();
        }, 50);

        setTimeout(() => {
            toast.classList.add('translate-x-8', 'opacity-0');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 6000);
    };

    // Global Browser Native Notification Helper (with custom icon support)
    window.sendBrowserNotification = function(title, body, iconUrl = null) {
        if (!("Notification" in window)) return;
        const options = {
            body: body,
            icon: iconUrl || window.dgtInitialsAvatar('DGT', '#4f46e5')
        };

        if (Notification.permission === "granted") {
            new Notification(title, options);
        }
    };

    // AlpineJS Notification Dropdown Component
    function notificationSystem() {
        return {
            open: false,
            notifications: [],
            unreadCount: 0,
            browserPermission: 'unsupported',
            permissionBusy: false,
            notificationsMuted: localStorage.getItem('dgt_notifications_muted') === 'true',

            initNotifications() {
                this.refreshBrowserPermission();
                this.fetchData();

                // Setup Laravel Echo Private Channel listener if available
                if (window.Echo) {
                    window.Echo.private(`App.Models.User.${ {{ auth()->id() }} }`)
                        .notification((n) => {
                            this.handleIncoming(n);
                        });
                }

                // Short polling failover backup (every 12 seconds)
                setInterval(() => this.fetchData(), 12000);
            },

            refreshBrowserPermission() {
                this.browserPermission = ("Notification" in window) ? Notification.permission : 'unsupported';
            },

            browserPermissionLabel() {
                if (this.permissionBusy) return 'Checking';
                return {
                    granted: 'Enabled',
                    denied: 'Blocked',
                    default: 'Ask',
                    unsupported: 'Unavailable',
                }[this.browserPermission] || 'Ask';
            },

            async requestBrowserPermission() {
                if (!("Notification" in window)) {
                    this.browserPermission = 'unsupported';
                    window.showToast('This browser does not support notifications.', 'error');
                    return;
                }

                if (Notification.permission === 'granted') {
                    this.refreshBrowserPermission();
                    window.showToast('Browser notifications are already enabled.');
                    return;
                }

                if (Notification.permission === 'denied') {
                    this.refreshBrowserPermission();
                    window.showToast('Browser notifications are blocked in your browser settings.', 'error');
                    return;
                }

                this.permissionBusy = true;
                const permission = await Notification.requestPermission();
                this.permissionBusy = false;
                this.browserPermission = permission;
                window.showToast(permission === 'granted' ? 'Browser notifications enabled.' : 'Browser notifications were not enabled.', permission === 'granted' ? 'success' : 'error');
            },

            toggleMute() {
                this.notificationsMuted = !this.notificationsMuted;
                localStorage.setItem('dgt_notifications_muted', this.notificationsMuted);
                window.showToast(this.notificationsMuted ? "In-app popups muted" : "In-app popups enabled");
            },

            async fetchData() {
                try {
                    const res = await fetch('{{ route('notifications.index') }}', {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const data = await res.json();
                    
                    // Trigger rich popup and browser notifications if unreadCount increased
                    if (data.unread_count > this.unreadCount && this.notifications.length > 0) {
                        const newNotifs = (data.notifications || []).filter(
                            n => !n.read_at && !this.notifications.some(x => x.id === n.id)
                        );
                        
                        newNotifs.forEach(newNotif => {
                            if (newNotif.data && newNotif.data.actor_name) {
                                window.showRichNotificationToast(newNotif.data);
                                if (newNotif.data.browser_notifications_enabled !== false) {
                                    window.sendBrowserNotification(
                                        "DGT Board Update", 
                                        `${newNotif.data.actor_name} ${newNotif.data.description.replace(/\*\*/g, '')}`,
                                        newNotif.data.actor_avatar
                                    );
                                }
                            } else {
                                window.showToast(newNotif.data.message || "New update received");
                                window.sendBrowserNotification("DGT System Update", newNotif.data.message || "New update");
                            }
                        });
                    }

                    this.notifications = data.notifications || [];
                    this.unreadCount = data.unread_count || 0;
                } catch (e) {
                    console.error('Error fetching notifications:', e);
                }
            },

            handleIncoming(n) {
                // Prepend incoming websocket notification
                const notifItem = {
                    id: n.id,
                    data: n.data || n,
                    read_at: null,
                    created_at: n.created_at || new Date().toISOString()
                };

                // Prevent duplicates
                if (this.notifications.some(x => x.id === notifItem.id)) return;

                this.notifications.unshift(notifItem);
                this.unreadCount++;

                // Trigger animations and toasts
                if (notifItem.data && notifItem.data.actor_name) {
                    window.showRichNotificationToast(notifItem.data);
                    if (notifItem.data.browser_notifications_enabled !== false) {
                        window.sendBrowserNotification(
                            "DGT Board Update",
                            `${notifItem.data.actor_name} ${notifItem.data.description ? notifItem.data.description.replace(/\*/g, '') : notifItem.data.message.replace(/\*/g, '')}`,
                            notifItem.data.actor_avatar
                        );
                        // Play notification sound
                        const audio = document.getElementById('notif-sound');
                        if (audio) {
                            audio.volume = 100.0;
                            audio.play().catch(e => console.log('Audio play blocked:', e));
                        }
                    }
                }
            },

            timeAgo(dateStr) {
                if (!dateStr) return 'just now';
                const date = new Date(dateStr);
                const now = new Date();
                const seconds = Math.floor((now - date) / 1000);
                
                if (seconds < 5) return 'just now';
                if (seconds < 60) return `${seconds}s ago`;
                const minutes = Math.floor(seconds / 60);
                if (minutes < 60) return `${minutes}m ago`;
                const hours = Math.floor(minutes / 60);
                if (hours < 24) return `${hours}h ago`;
                const days = Math.floor(hours / 24);
                if (days < 30) return `${days}d ago`;
                
                return date.toLocaleDateString();
            },

            toggleOpen() {
                this.open = !this.open;
                if (this.open) {
                    this.refreshBrowserPermission();
                    this.fetchData();
                }
            },

            badgeCount() {
                return this.unreadCount > 99 ? '99+' : this.unreadCount;
            },

            isUnread(notif) {
                return !notif.read_at;
            },

            actorAvatar(notif) {
                return notif?.data?.actor_avatar || window.dgtInitialsAvatar(notif?.data?.actor_name || 'System', '#64748b');
            },

            actorName(notif) {
                return notif?.data?.actor_name || 'DGT System';
            },

            stripMarkdown(value) {
                return String(value || '')
                    .replace(/\*/g, '')
                    .replace(/<[^>]*>/g, '')
                    .trim();
            },

            notificationAction(notif) {
                const data = notif?.data || {};
                if (data.description) return this.stripMarkdown(data.description);
                if (data.message) return this.stripMarkdown(data.message);
                return data.action ? this.stripMarkdown(String(data.action).replace(/_/g, ' ')) : 'sent a notification';
            },

            boardName(notif) {
                return notif?.data?.board_name || '';
            },

            cardName(notif) {
                return notif?.data?.card_title || '';
            },

            notificationTime(notif) {
                return notif?.time_ago || this.timeAgo(notif?.created_at);
            },

            async markAllAsRead() {
                try {
                    await fetch('{{ route('notifications.read-all') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    this.unreadCount = 0;
                    this.notifications.forEach(n => n.read_at = new Date().toISOString());
                    window.showToast("All notifications marked as read!");
                } catch(e) {
                    console.error(e);
                }
            },

            async clickNotification(notif) {
                try {
                    await fetch(`/notifications/${notif.id}/read`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    notif.read_at = new Date().toISOString();
                    if (this.unreadCount > 0) this.unreadCount--;

                    if (notif.data.link) {
                        window.location.href = notif.data.link;
                    }
                } catch(e) {
                    console.error(e);
                }
            }
        };
    }
    </script>

    @stack('scripts')
    <!-- Notification Sound Effect -->
    <audio id="notif-sound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>
</body>
</html>
