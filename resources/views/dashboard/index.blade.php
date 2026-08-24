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
    .blob-1 { top: -10%; left: -10%; width: 50vw; height: 50vw; background: radial-gradient(circle, rgba(99,102,241,0.2) 0%, rgba(255,255,255,0) 70%); }
    .blob-2 { bottom: -10%; right: -10%; width: 60vw; height: 60vw; background: radial-gradient(circle, rgba(14,165,233,0.15) 0%, rgba(255,255,255,0) 70%); animation-delay: -10s; }
    
    [data-theme="dark"] .blob-1 { background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, rgba(15,23,42,0) 70%); }
    [data-theme="dark"] .blob-2 { background: radial-gradient(circle, rgba(14,165,233,0.1) 0%, rgba(15,23,42,0) 70%); }

    @keyframes float {
        0% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(5%, 10%) scale(1.1); }
        100% { transform: translate(-5%, -5%) scale(0.9); }
    }

    /* Modern Bento Cards */
    .bento-card {
        background: rgba(255, 255, 255, 0.6);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: 2rem;
        box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05), inset 0 1px 0 rgba(255,255,255,0.7);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
        overflow: hidden;
    }
    
    .bento-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1), inset 0 1px 0 rgba(255,255,255,0.8);
    }

    [data-theme="dark"] .bento-card {
        background: rgba(30, 41, 59, 0.6);
        border-color: rgba(255, 255, 255, 0.05);
        box-shadow: 0 10px 40px -10px rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,255,255,0.05);
    }
    [data-theme="dark"] .bento-card:hover {
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.08);
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

<div class="max-w-7xl mx-auto space-y-6 sm:space-y-8 animate-fade-in pb-28 md:pb-12 px-2 sm:px-0">
    
    <!-- Hero Bento Section -->
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Main Welcome Card -->
        <article class="bento-card xl:col-span-2 p-8 sm:p-12 relative flex flex-col justify-center min-h-[300px]">
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-purple-500/5 dark:from-indigo-500/10 dark:to-purple-500/10 pointer-events-none"></div>
            
            <div class="relative z-10">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/60 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 shadow-sm mb-6 backdrop-blur-md">
                    {!! $greetingIcon !!}
                    <span class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $greeting }},</span>
                </div>
                
                <h1 class="text-5xl sm:text-6xl font-black tracking-tight mb-4">
                    <span class="text-slate-900 dark:text-white">Welcome back,</span><br/>
                    <span class="text-gradient">{{ $user->name }}</span>
                </h1>
                
                <p class="text-base sm:text-lg text-slate-500 dark:text-slate-400 font-medium max-w-xl">
                    Here is what is happening in your workspace today. You have <strong class="text-indigo-600 dark:text-indigo-400">{{ $dashboardUnreadCount }}</strong> unread alerts requiring your attention.
                </p>
                
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-indigo-600 text-white font-bold hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-600/30">
                        View Profile
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
                    </a>
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

    <!-- Analytics Section -->
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <article class="bento-card-primary p-6 sm:p-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-black text-white">Active Users</h3>
                    <p class="text-sm font-medium text-indigo-100">Workspace presence</p>
                </div>
                <div class="p-2 bg-white/20 rounded-xl text-white">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" /></svg>
                </div>
            </div>
            <div class="h-[280px] relative w-full flex items-center justify-center">
                <canvas id="dashboardUserChart"></canvas>
            </div>
        </article>

        <article class="bento-card-primary p-6 sm:p-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-black text-white">Activity Trend</h3>
                    <p class="text-sm font-medium text-indigo-100">Actions over last days</p>
                </div>
                <div class="p-2 bg-white/20 rounded-xl text-white">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                </div>
            </div>
            <div class="h-[280px] relative w-full">
                <canvas id="dashboardActivityChart"></canvas>
            </div>
        </article>
    </section>

    <!-- Recent Activity -->
    <section class="bento-card p-6 sm:p-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Recent Activities</h3>
                <p class="text-sm font-medium text-slate-500">Live feed of what's happening across the system.</p>
            </div>
            <span class="px-3 py-1 bg-slate-100 dark:bg-slate-800 rounded-full text-xs font-black uppercase text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">Audit Log</span>
        </div>
        
        @php $recentActivities = $recentActivitiesFn(); @endphp
        @if($recentActivities->isEmpty())
            <div class="py-12 text-center bg-white/40 dark:bg-slate-800/40 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700">
                <svg class="mx-auto w-12 h-12 text-slate-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                <p class="text-base font-bold text-slate-700 dark:text-slate-300">No activity recorded yet</p>
                <p class="mt-1 text-sm font-medium text-slate-500">System actions will stream here in real-time.</p>
            </div>
        @else
            <div class="space-y-6 max-h-[500px] overflow-y-auto pr-2 scrollbar-thin">
                @foreach($recentActivities as $log)
                    <div class="group flex items-start gap-4 p-4 rounded-2xl hover:bg-white/60 dark:hover:bg-slate-800/60 transition-colors border border-transparent hover:border-slate-200 dark:hover:border-slate-700">
                        <img src="{{ $log->user?->avatar_url ?? 'https://ui-avatars.com/api/?name=System&size=64&background=6366f1&color=fff' }}"
                             alt="{{ $log->user?->name ?? 'System' }}"
                             class="w-10 h-10 rounded-full object-cover shadow-sm ring-2 ring-white dark:ring-slate-800 flex-shrink-0">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-slate-700 dark:text-slate-300 leading-snug">
                                <span class="font-bold text-slate-900 dark:text-white">{{ $log->user?->name ?? 'System' }}</span>
                                {{ $log->description }}
                            </p>
                            <div class="mt-1.5 flex items-center gap-3 text-xs font-bold text-slate-400">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                    {{ $log->created_at?->diffForHumans() ?? 'live' }}
                                </span>
                                @if($log->module)
                                <span class="px-2 py-0.5 bg-slate-200/50 dark:bg-slate-700/50 rounded text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    {{ $log->module }}
                                </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

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
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    Chart.defaults.color = isDark ? '#94a3b8' : '#64748b';

    const userChart = document.getElementById('dashboardUserChart');
    if (userChart) {
        Chart.getChart(userChart)?.destroy();
        new Chart(userChart, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Offline'],
                datasets: [{
                    data: [{{ $onlineUsers }}, {{ $offlineUsers }}],
                    backgroundColor: ['#6366f1', isDark ? '#334155' : '#e2e8f0'],
                    borderWidth: 0,
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
                        labels: { usePointStyle: true, boxWidth: 8, padding: 20, font: { weight: 700 } },
                    },
                    tooltip: {
                        backgroundColor: isDark ? 'rgba(15, 23, 42, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                        titleColor: isDark ? '#fff' : '#0f172a',
                        bodyColor: isDark ? '#e2e8f0' : '#475569',
                        borderColor: isDark ? '#334155' : '#e2e8f0',
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
        gradient.addColorStop(0, '#a855f7');
        gradient.addColorStop(1, '#6366f1');

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
                        ticks: { font: { weight: 600 } },
                        border: { display: false }
                    },
                    y: { 
                        beginAtZero: true, 
                        ticks: { precision: 0, padding: 10 }, 
                        grid: { color: isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)', drawBorder: false },
                        border: { display: false }
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? 'rgba(15, 23, 42, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                        titleColor: isDark ? '#fff' : '#0f172a',
                        bodyColor: isDark ? '#e2e8f0' : '#475569',
                        borderColor: isDark ? '#334155' : '#e2e8f0',
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
