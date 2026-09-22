@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Overview')
@section('meta_description', 'DIGITAL SYSTEM overview: tasks, CRM, sales, notifications, and team activity at a glance.')

@section('content')
@php
    $totalUsers = (int) $stats['total_users'];
    $onlineUsers = (int) $stats['online_users'];
    $offlineUsers = max($totalUsers - $onlineUsers, 0);
    $dashboardUnreadCount = (int) ($dashboardUnreadCount ?? 0);
    $permissionsCount = (int) ($permissionsCount ?? 0);
    
    // Generate an animated greeting
    $hour = now()->hour;
    if ($hour < 12) {
        $greeting = 'Good morning';
        $greetingIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-amber-400 animate-[spin_10s_linear_infinite]"><path d="M12 2.25a.75.75 0 0 1 .75.75v2.25a.75.75 0 0 1-1.5 0V3a.75.75 0 0 1 .75-.75ZM7.5 12a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM18.894 6.166a.75.75 0 0 0-1.06-1.06l-1.591 1.59a.75.75 0 1 0 1.06 1.061l1.591-1.59ZM21.75 12a.75.75 0 0 1-.75.75h-2.25a.75.75 0 0 1 0-1.5H21a.75.75 0 0 1 .75.75ZM17.834 18.894a.75.75 0 0 0 1.06-1.06l-1.59-1.591a.75.75 0 1 0-1.061 1.06l1.59 1.591ZM12 18a.75.75 0 0 1 .75.75V21a.75.75 0 0 1-1.5 0v-2.25A.75.75 0 0 1 12 18ZM7.22 17.834a.75.75 0 0 0-1.06 1.06l1.591 1.59a.75.75 0 0 0 1.06-1.061l-1.591-1.59ZM2.25 12a.75.75 0 0 1 .75-.75h2.25a.75.75 0 0 1 0 1.5H3a.75.75 0 0 1-.75-.75ZM6.166 5.106a.75.75 0 0 0-1.06 1.06l1.59 1.591a.75.75 0 1 0 1.061-1.06l-1.59-1.591Z" /></svg>';
    } elseif ($hour < 17) {
        $greeting = 'Good afternoon';
        $greetingIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-orange-400"><path fill-rule="evenodd" d="M12 2.25a.75.75 0 0 1 .75.75v2.25a.75.75 0 0 1-1.5 0V3a.75.75 0 0 1 .75-.75ZM7.5 12a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0Zm11.394-5.834a.75.75 0 0 0-1.06-1.06l-1.591 1.59a.75.75 0 1 0 1.06 1.061l1.591-1.59ZM21.75 12a.75.75 0 0 1-.75.75h-2.25a.75.75 0 0 1 0-1.5H21a.75.75 0 0 1 .75.75Zm-3.916 6.894a.75.75 0 0 0 1.06-1.06l-1.59-1.591a.75.75 0 1 0-1.061 1.06l1.59 1.591ZM12 18a.75.75 0 0 1 .75.75V21a.75.75 0 0 1-1.5 0v-2.25A.75.75 0 0 1 12 18Zm-4.78 1.834a.75.75 0 0 0-1.06 1.06l1.591 1.59a.75.75 0 0 0 1.06-1.061l-1.591-1.59ZM2.25 12a.75.75 0 0 1 .75-.75h2.25a.75.75 0 0 1 0 1.5H3a.75.75 0 0 1-.75-.75ZM6.166 5.106a.75.75 0 0 0-1.06 1.06l1.59 1.591a.75.75 0 1 0 1.061-1.06l-1.59-1.591Z" clip-rule="evenodd" /></svg>';
    } else {
        $greeting = 'Good evening';
        $greetingIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-indigo-300"><path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 0 1 .162.819A8.97 8.97 0 0 0 9 6a9 9 0 0 0 9 9 8.97 8.97 0 0 0 3.463-.69.75.75 0 0 1 .981.98 10.503 10.503 0 0 1-9.694 6.46c-5.799 0-10.5-4.7-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 0 1 .818.162Z" clip-rule="evenodd" /></svg>';
    }
@endphp

<style>
    /* Animated Gradient Background */
    .bg-animated-mesh {
        background: linear-gradient(120deg, #e0f2fe 0%, #bae6fd 100%);
        position: fixed;
        inset: 0;
        z-index: -2;
    }
    
    [data-theme="dark"] .bg-animated-mesh {
        background: #0f172a;
    }

    /* Ambient Glowing Blobs */
    .blob {
        position: absolute;
        filter: blur(80px);
        z-index: -1;
        opacity: 0.6;
        animation: float 20s infinite ease-in-out alternate;
    }
    .blob-1 { top: -10%; left: -10%; width: 50%; height: 50%; background: radial-gradient(circle, rgba(99,102,241,0.2) 0%, rgba(255,255,255,0) 70%); }
    .blob-2 { bottom: -10%; right: -10%; width: 60%; height: 60%; background: radial-gradient(circle, rgba(14,165,233,0.15) 0%, rgba(255,255,255,0) 70%); animation-delay: -10s; }
    
    [data-theme="dark"] .blob-1 { background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, rgba(15,23,42,0) 70%); }
    [data-theme="dark"] .blob-2 { background: radial-gradient(circle, rgba(14,165,233,0.1) 0%, rgba(15,23,42,0) 70%); }

    @keyframes float {
        0% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(5%, 10%) scale(1.1); }
        100% { transform: translate(-5%, -5%) scale(0.9); }
    }

    /* Modern Bento Cards */
    .bento-card {
        background: rgba(238, 242, 255, 0.7); /* Light blue tint */
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: 2rem;
        box-shadow: 0 10px 40px -10px rgba(79, 70, 229, 0.1), inset 0 1px 0 rgba(255,255,255,0.7);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
        overflow: hidden;
    }
    
    .bento-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px -10px rgba(79, 70, 229, 0.15), inset 0 1px 0 rgba(255,255,255,0.8);
    }

    [data-theme="dark"] .bento-card {
        background: rgba(30, 41, 59, 0.7); /* Slightly darker slate with blue hint */
        border-color: rgba(255, 255, 255, 0.05);
        box-shadow: 0 10px 40px -10px rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,255,255,0.05);
    }
    [data-theme="dark"] .bento-card:hover {
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.08);
    }

    /* ─── Neon Blue Theme for Bento Dashboard ─── */
    [data-theme="neon"] .bg-animated-mesh {
        background: #02081c !important;
    }
    [data-theme="neon"] .blob-1 {
        background: radial-gradient(circle, rgba(0, 119, 255, 0.28) 0%, rgba(2, 8, 28, 0) 70%) !important;
    }
    [data-theme="neon"] .blob-2 {
        background: radial-gradient(circle, rgba(0, 210, 255, 0.22) 0%, rgba(2, 8, 28, 0) 70%) !important;
    }
    [data-theme="neon"] .bento-card {
        background: rgba(4, 20, 56, 0.9) !important;
        backdrop-filter: blur(24px) !important;
        -webkit-backdrop-filter: blur(24px) !important;
        border: 1.5px solid rgba(0, 160, 255, 0.4) !important;
        box-shadow: 0 12px 40px -10px rgba(0, 0, 0, 0.65), 0 0 20px rgba(0, 140, 255, 0.2), inset 0 1px 0 rgba(0, 210, 255, 0.25) !important;
        color: #f0f9ff !important;
    }
    [data-theme="neon"] .bento-card:hover {
        transform: translateY(-4px);
        border-color: rgba(0, 220, 255, 0.75) !important;
        box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.75), 0 0 30px rgba(0, 180, 255, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.4) !important;
    }
    [data-theme="neon"] .bento-card-primary {
        background: linear-gradient(135deg, #0055ff 0%, #002b80 50%, #001440 100%) !important;
        border: 1.5px solid rgba(0, 180, 255, 0.55) !important;
        box-shadow: 0 12px 40px -10px rgba(0, 0, 0, 0.7), 0 0 25px rgba(0, 140, 255, 0.35), inset 0 1px 0 rgba(0, 220, 255, 0.3) !important;
    }
    [data-theme="neon"] .bento-card-primary:hover {
        box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.8), 0 0 35px rgba(0, 180, 255, 0.55) !important;
    }
    [data-theme="neon"] .text-gradient {
        background-image: linear-gradient(135deg, #00f0ff, #38bdf8, #818cf8) !important;
    }
    [data-theme="neon"] .animate-text-gradient {
        background: linear-gradient(to right, #00f0ff, #60a5fa, #00e5ff) !important;
        -webkit-background-clip: text !important;
        background-clip: text !important;
    }
    [data-theme="neon"] .bg-white\/80 {
        background-color: rgba(4, 20, 56, 0.9) !important;
        border-color: rgba(0, 160, 255, 0.35) !important;
    }
    [data-theme="neon"] a[href*="tasks/count"].bg-white {
        background-color: rgba(3, 14, 44, 0.95) !important;
        border-color: rgba(0, 160, 255, 0.4) !important;
        box-shadow: 0 0 15px rgba(0, 140, 255, 0.2) !important;
    }

    /* Hero Gradient Text */
    .text-gradient {
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-image: linear-gradient(135deg, #2563eb, #8b5cf6, #ec4899);
        background-size: 200% auto;
        animation: textGradient 6s linear infinite;
    }
    [data-theme="dark"] .text-gradient {
        background-image: linear-gradient(135deg, #60a5fa, #a78bfa, #f472b6);
    }
    
    @keyframes textGradient {
        0% { background-position: 0% center; }
        100% { background-position: 200% center; }
    }
    
    .animate-text-gradient {
        background: linear-gradient(to right, #f472b6, #38bdf8, #f472b6);
        background-size: 200% auto;
        color: transparent;
        -webkit-background-clip: text;
        background-clip: text;
        animation: textGradient 4s linear infinite;
    }
    
    .bento-card-primary {
        background: linear-gradient(135deg, #2563eb, #4f46e5);
        color: white;
        border: none;
        border-radius: 2rem;
        box-shadow: 0 10px 40px -10px rgba(79, 70, 229, 0.4);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
        overflow: hidden;
    }
    .bento-card-primary:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px -10px rgba(79, 70, 229, 0.6);
    }
</style>

<!-- Ambient Background -->
<div class="fixed inset-0 overflow-hidden pointer-events-none z-[-2]">
    <div class="bg-animated-mesh"></div>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
</div>

<div class="max-w-7xl mx-auto space-y-6 sm:space-y-8 animate-fade-in pb-28 md:pb-12 px-4 sm:px-6 lg:px-8 w-full">
    
    <!-- Hero Bento Section -->
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Main Welcome Card -->
        <article class="bento-card-primary xl:col-span-2 p-8 sm:p-12 relative flex flex-col justify-center min-h-[300px]">
            <div class="absolute -right-20 -bottom-20 w-64 h-64 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="relative z-10">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/20 border border-white/20 shadow-sm mb-6 backdrop-blur-md">
                    {!! $greetingIcon !!}
                    <span class="text-sm font-bold text-white">{{ $greeting }},</span>
                </div>
                
                <h1 class="text-5xl sm:text-6xl font-black tracking-tight mb-4 animate-text-gradient">
                    <span>Welcome back,</span><br/>
                    <span>{{ $user->name }}</span>
                </h1>
                
                <p class="text-base sm:text-lg text-indigo-100 font-medium max-w-xl">
                    Here is what is happening in your workspace today. You have <strong class="text-white">{{ $dashboardUnreadCount }}</strong> unread alerts requiring your attention.
                </p>
                
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-white text-indigo-600 font-bold hover:bg-indigo-50 transition-colors shadow-lg shadow-black/10">
                        View Profile
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
                    </a>
                    @if(($totalTasksCount ?? 0) > 0)
                    <a href="{{ route('tasks.count') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-white/15 hover:bg-white/25 border border-white/25 text-white font-bold transition-colors backdrop-blur-md">
                        <span>Tasks Count ({{ $totalTasksCount }})</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </a>
                    @endif
                </div>
            </div>
        </article>

        <!-- Time & Status Card -->
        <article class="bento-card-primary p-8 relative flex flex-col justify-between overflow-hidden">
            <!-- Decorative circle -->
            <div class="absolute -right-16 -top-16 w-48 h-48 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div>
                <p class="text-sm font-black uppercase tracking-widest text-indigo-200 mb-1">{{ now()->format('l') }}</p>
                <h2 class="text-3xl font-black text-white">{{ now()->format('F j, Y') }}</h2>
            </div>
            
            <div class="mt-8 space-y-4">
                <div class="bg-white/10 rounded-2xl p-4 border border-white/10 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-indigo-100">Total Users</p>
                        <p class="text-2xl font-black text-white">{{ $totalUsers }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-white">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                    </div>
                </div>
                
                <div class="bg-white/10 rounded-2xl p-4 border border-white/10 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-indigo-100">Online Now</p>
                        <p class="text-2xl font-black text-emerald-300">{{ $onlineUsers }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-emerald-400/20 flex items-center justify-center text-emerald-300">
                        <span class="relative flex h-4 w-4">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-300 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-4 w-4 bg-emerald-400"></span>
                        </span>
                    </div>
                </div>
            </div>
        </article>
    </section>

    {{-- ── Assigned Tasks & Deadline Warning Section (for planning board members) ── --}}
    @if(($totalTasksCount ?? 0) > 0 || ($totalWarningCount ?? 0) > 0)
    <section class="space-y-4">
        {{-- Task Overview Quick Stats Bar --}}
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl rounded-3xl p-5 sm:p-6 border border-slate-200/80 dark:border-slate-700/80 shadow-sm">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shadow-md shadow-indigo-500/20 shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">Your Assigned Tasks</h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-black bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300">
                            {{ $totalTasksCount }} total
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">Personal tasks overview across your team planning boards</p>
                </div>
            </div>

            {{-- Stat Cards matching the user's uploaded images --}}
            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                {{-- Total Tasks Card --}}
                <a href="{{ route('tasks.count') }}" class="bg-white dark:bg-slate-900/90 rounded-2xl px-5 py-3.5 flex items-center gap-4 shadow-sm border border-slate-200/80 dark:border-slate-700 hover:scale-[1.02] transition-transform min-w-[8.5rem]">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 dark:text-indigo-400">TOTAL TASKS</span>
                        <span class="text-2xl font-black text-slate-900 dark:text-white">{{ $totalTasksCount }}</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                        </svg>
                    </div>
                </a>

                {{-- Overdue Tasks Card --}}
                @if(!empty($overdueCount) && $overdueCount > 0)
                <a href="{{ route('tasks.count') }}" class="bg-rose-500 text-white rounded-2xl px-5 py-3.5 flex items-center gap-4 shadow-sm shadow-rose-500/20 border border-rose-400 hover:scale-[1.02] transition-transform min-w-[8.5rem]">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-wider text-rose-100">OVERDUE</span>
                        <span class="text-2xl font-black text-white">{{ $overdueCount }}</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                    </div>
                </a>
                @endif

                {{-- Due in 1 Day (Tomorrow) Card --}}
                @if(!empty($dueTomorrowCount) && $dueTomorrowCount > 0)
                <a href="{{ route('tasks.count') }}" class="bg-amber-500 text-white rounded-2xl px-5 py-3.5 flex items-center gap-4 shadow-sm shadow-amber-500/20 border border-amber-400 hover:scale-[1.02] transition-transform min-w-[8.5rem] animate-pulse">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-wider text-amber-100">DUE IN 1 DAY</span>
                        <span class="text-2xl font-black text-white">{{ $dueTomorrowCount }}</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </a>
                @endif

                {{-- Button to full Tasks Count page --}}
                <a href="{{ route('tasks.count') }}" class="inline-flex items-center gap-2 px-5 py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md shadow-indigo-500/20 transition-all">
                    <span>Tasks Count</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
            </div>
        </div>

        {{-- Deadline Warning Alert Banner (Due in 1 Day / Today / Overdue) --}}
        @if(!empty($totalWarningCount) && $totalWarningCount > 0)
            @include('partials.deadline-warning-alert')
        @endif
    </section>
    @endif

    <!-- Analytics Section -->
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <article class="bento-card p-6 sm:p-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white">Active Users</h3>
                    <p class="text-sm font-medium text-slate-500">Workspace presence</p>
                </div>
                <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl text-indigo-600 dark:text-indigo-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" /></svg>
                </div>
            </div>
            <div class="h-[280px] relative w-full flex items-center justify-center">
                <canvas id="dashboardUserChart"></canvas>
            </div>
        </article>

        <article class="bento-card p-6 sm:p-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white">Activity Trend</h3>
                    <p class="text-sm font-medium text-slate-500">Actions over last days</p>
                </div>
                <div class="p-2 bg-pink-50 dark:bg-pink-900/30 rounded-xl text-pink-600 dark:text-pink-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                </div>
            </div>
            <div class="h-[280px] relative w-full">
                <canvas id="dashboardActivityChart"></canvas>
            </div>
        </article>
    </section>

    {{-- Approval Queue & Review Pipeline (Moved into Dashboard for QC / Approvers) --}}
    @if(isset($approvalQueueData) && !empty($approvalQueueData))
        @include('dashboard.partials.approval-queue', ['data' => $approvalQueueData])
    @endif

</div>
@endsection

@push('scripts')
<script>
async function initDashboardCharts() {
    if (!window.Chart && window.loadChart) {
        await window.loadChart();
    }
    if (!window.Chart) return;

    Chart.defaults.font.family = "'Inter', sans-serif";
    const isNeon = document.documentElement.getAttribute('data-theme') === 'neon';
    const isDark = isNeon || document.documentElement.getAttribute('data-theme') === 'dark';
    Chart.defaults.color = isNeon ? '#7dd3fc' : (isDark ? '#94a3b8' : '#64748b');

    const userChart = document.getElementById('dashboardUserChart');
    if (userChart) {
        Chart.getChart(userChart)?.destroy();
        new Chart(userChart, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Offline'],
                datasets: [{
                    data: [{{ $onlineUsers }}, {{ $offlineUsers }}],
                    backgroundColor: [
                        isNeon ? '#00e5ff' : '#6366f1', 
                        isNeon ? '#0c2656' : (isDark ? '#334155' : '#e2e8f0')
                    ],
                    borderWidth: isNeon ? 1 : 0,
                    borderColor: isNeon ? 'rgba(0, 220, 255, 0.4)' : 'transparent',
                    hoverOffset: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { 
                            usePointStyle: true, 
                            boxWidth: 8, 
                            padding: 20, 
                            font: { weight: 700 },
                            color: isNeon ? '#bae6fd' : undefined
                        },
                    },
                    tooltip: {
                        backgroundColor: isNeon ? 'rgba(3, 14, 44, 0.95)' : (isDark ? 'rgba(15, 23, 42, 0.9)' : 'rgba(255, 255, 255, 0.9)'),
                        titleColor: '#fff',
                        bodyColor: isNeon ? '#bae6fd' : (isDark ? '#e2e8f0' : '#475569'),
                        borderColor: isNeon ? '#00c3ff' : (isDark ? '#334155' : '#e2e8f0'),
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 12,
                        boxPadding: 6
                    }
                },
            },
        });
    }

    const activityChart = document.getElementById('dashboardActivityChart');
    if (activityChart) {
        Chart.getChart(activityChart)?.destroy();
        
        // Create gradient for bars
        const ctx = activityChart.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        if (isNeon) {
            gradient.addColorStop(0, '#00f0ff');
            gradient.addColorStop(1, '#0066ff');
        } else {
            gradient.addColorStop(0, '#a855f7');
            gradient.addColorStop(1, '#6366f1');
        }

        new Chart(activityChart, {
            type: 'bar',
            data: {
                labels: @json($activityDays->pluck('label')),
                datasets: [{
                    label: 'Activity Count',
                    data: @json($activityDays->pluck('count')),
                    backgroundColor: gradient,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 24,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { 
                        grid: { display: false }, 
                        ticks: { 
                            font: { weight: 600 },
                            color: isNeon ? '#7dd3fc' : undefined
                        },
                        border: { display: false }
                    },
                    y: { 
                        beginAtZero: true, 
                        ticks: { 
                            precision: 0, 
                            padding: 10,
                            color: isNeon ? '#7dd3fc' : undefined
                        }, 
                        grid: { 
                            color: isNeon ? 'rgba(0, 160, 255, 0.15)' : (isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)'), 
                            drawBorder: false 
                        },
                        border: { display: false }
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isNeon ? 'rgba(3, 14, 44, 0.95)' : (isDark ? 'rgba(15, 23, 42, 0.9)' : 'rgba(255, 255, 255, 0.9)'),
                        titleColor: '#fff',
                        bodyColor: isNeon ? '#bae6fd' : (isDark ? '#e2e8f0' : '#475569'),
                        borderColor: isNeon ? '#00c3ff' : (isDark ? '#334155' : '#e2e8f0'),
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 12,
                        displayColors: false
                    }
                },
            },
        });
    }
}

function scheduleDashboardCharts() {
    setTimeout(() => initDashboardCharts(), 50);
}

document.addEventListener('DOMContentLoaded', scheduleDashboardCharts);
document.addEventListener('turbo:load', scheduleDashboardCharts);
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    scheduleDashboardCharts();
}
document.addEventListener('turbo:before-cache', () => {
    if (window.Chart) {
        const userChart = Chart.getChart('dashboardUserChart');
        if (userChart) userChart.destroy();
        
        const activityChart = Chart.getChart('dashboardActivityChart');
        if (activityChart) activityChart.destroy();
    }
});

// Watch for theme changes to redraw charts with correct colors
const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        if (mutation.attributeName === 'data-theme') {
            scheduleDashboardCharts();
        }
    });
});
observer.observe(document.documentElement, { attributes: true });
</script>
@endpush
