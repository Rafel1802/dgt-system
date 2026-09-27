@extends('layouts.app')
@section('title', 'Digital Media KPI Management')
@section('page_title', 'Digital Media KPI Dashboard')

@section('content')

<style>
/* 3D Claymorphic Design Tokens */
.clay-card {
    background: #ffffff;
    border-radius: 1.5rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    box-shadow: 6px 8px 24px -2px rgba(15, 23, 42, 0.08), -4px -4px 16px 0 rgba(255, 255, 255, 0.9), inset 1px 1px 2px rgba(255, 255, 255, 0.8);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.dark .clay-card {
    background: #1e293b;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 6px 8px 24px -2px rgba(0, 0, 0, 0.35), inset 1px 1px 2px rgba(255, 255, 255, 0.05);
}
.clay-card:hover {
    transform: translateY(-2px);
    box-shadow: 8px 12px 28px -2px rgba(15, 23, 42, 0.12), -4px -4px 18px 0 rgba(255, 255, 255, 0.95);
}
.dark .clay-card:hover {
    box-shadow: 8px 12px 28px -2px rgba(0, 0, 0, 0.45);
}
.clay-pill-blue {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    box-shadow: 4px 6px 14px rgba(37, 99, 235, 0.35), inset 1px 1px 2px rgba(255, 255, 255, 0.4);
}
.clay-pill-mint {
    background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    box-shadow: 4px 6px 14px rgba(16, 185, 129, 0.35), inset 1px 1px 2px rgba(255, 255, 255, 0.4);
}
.clay-pill-purple {
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
    box-shadow: 4px 6px 14px rgba(139, 92, 246, 0.35), inset 1px 1px 2px rgba(255, 255, 255, 0.4);
}
.clay-pill-amber {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    box-shadow: 4px 6px 14px rgba(245, 158, 11, 0.35), inset 1px 1px 2px rgba(255, 255, 255, 0.4);
}
.clay-btn {
    border-radius: 9999px;
    font-weight: 600;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.clay-btn:active {
    transform: scale(0.97);
}
</style>

<div class="space-y-6 pb-12" x-data="{
    showNewTaskModal: false,
    showSubmitModal: false,
    showApproveModal: false,
    selectedTaskId: null,
    selectedTaskTitle: ''
}">

    {{-- ── Banner ───────────────────────────────────────────────────────────── --}}
    <div class="relative overflow-hidden rounded-3xl p-6 sm:p-8 text-white shadow-xl"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #0284c7 100%);">
        <!-- Soft clay 3D background shapes -->
        <div class="absolute -right-12 -top-12 w-64 h-64 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>
        <div class="absolute right-1/3 -bottom-16 w-56 h-56 rounded-full bg-sky-400/20 blur-xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md border border-white/20 text-xs font-semibold tracking-wide">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Digital Media Department • KPI Suite</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                    Welcome, {{ auth()->user()->name }}! 👋
                </h1>
                <p class="text-blue-100 text-sm max-w-2xl">
                    Real-time performance tracking, deliverables quality control, and monthly squad evaluations for Digital Media Squad 1 & Squad 2.
                </p>
                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <span class="text-xs bg-black/20 backdrop-blur-sm px-3 py-1 rounded-full border border-white/10">
                        Period: <strong>{{ $currentPeriod?->name ?? 'September 2026' }}</strong>
                    </span>
                    <span class="text-xs bg-black/20 backdrop-blur-sm px-3 py-1 rounded-full border border-white/10">
                        Role: <strong>{{ $isSupervisor ? 'Supervisor / Admin' : (auth()->user()->username === 'dara' ? 'Squad 1 Lead (QC & Video)' : 'Squad 2 Lead (Graphic Head)') }}</strong>
                    </span>
                    @if($isSupervisor)
                    <span class="text-xs bg-emerald-400/20 text-emerald-200 px-3 py-1 rounded-full border border-emerald-400/30 font-medium">
                        Full Department Access
                    </span>
                    @else
                    <span class="text-xs bg-amber-400/20 text-amber-200 px-3 py-1 rounded-full border border-amber-400/30 font-medium">
                        Squad-Scoped View
                    </span>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="button" @click="showNewTaskModal = true"
                        class="clay-btn bg-white text-blue-700 hover:bg-blue-50 px-4 py-2.5 text-sm flex items-center gap-2 shadow-md">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>New Deliverable</span>
                </button>
                <a href="{{ route('kpi.export.pdf') }}" target="_blank"
                   class="clay-btn bg-white/20 hover:bg-white/30 text-white backdrop-blur-md border border-white/30 px-4 py-2.5 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Export PDF</span>
                </a>
                <a href="{{ route('kpi.export.csv') }}"
                   class="clay-btn bg-white/20 hover:bg-white/30 text-white backdrop-blur-md border border-white/30 px-4 py-2.5 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M12 10.875v7.5" />
                    </svg>
                    <span>CSV</span>
                </a>
            </div>
        </div>
    </div>

    {{-- ── 4 3D Clay Stat Cards ─────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- 1. Deliverables Progress -->
        <div class="clay-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-400">Total Deliverables</span>
                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="text-2xl font-black text-slate-800 dark:text-white">{{ $totalDeliverablesDone }}</span>
                        <span class="text-xs text-slate-400 font-medium">/ {{ $totalDeliverablesTarget }} target</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl clay-pill-blue flex items-center justify-center text-white">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="w-full bg-slate-100 dark:bg-slate-700/60 rounded-full h-2.5 overflow-hidden">
                    <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-500" style="width: {{ $completionRate }}%"></div>
                </div>
                <div class="flex items-center justify-between text-xs mt-2 text-slate-500 dark:text-slate-400">
                    <span>Fulfillment Rate</span>
                    <span class="font-bold text-blue-600 dark:text-blue-400">{{ $completionRate }}%</span>
                </div>
            </div>
        </div>

        <!-- 2. Quality Score -->
        <div class="clay-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-400">Quality Pass Rate</span>
                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="text-2xl font-black text-slate-800 dark:text-white">{{ number_format($avgQualityScore, 1) }}%</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl clay-pill-purple flex items-center justify-center text-white">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-xs text-purple-600 dark:text-purple-400 font-medium">
                <span class="px-2 py-0.5 rounded-full bg-purple-50 dark:bg-purple-900/30">Benchmark 90%</span>
                <span class="text-slate-400">• QC Approved</span>
            </div>
        </div>

        <!-- 3. Turnaround Time -->
        <div class="clay-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-400">Avg Turnaround (TAT)</span>
                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="text-2xl font-black text-slate-800 dark:text-white">{{ $avgTatHours }}h</span>
                        <span class="text-xs text-emerald-600 dark:text-emerald-400 font-bold">Fast</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl clay-pill-mint flex items-center justify-center text-white">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                <span class="px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-900/30">Target < 4.0h</span>
                <span class="text-slate-400">• On-Time 98%</span>
            </div>
        </div>

        <!-- 4. Overall KPI Score -->
        <div class="clay-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-400">Overall KPI Rank</span>
                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="text-2xl font-black text-slate-800 dark:text-white">{{ number_format($overallKpiScore, 1) }}%</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-700 dark:text-amber-300 font-bold">
                            Outstanding
                        </span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl clay-pill-amber flex items-center justify-center text-white">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.004 0H8.624m5.004 0V9.75m-5.004 0V9.75m0 0a2.25 2.25 0 0 1 2.25-2.25h.504a2.25 2.25 0 0 1 2.25 2.25m-5.004 0h5.004" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400 font-medium">
                <span class="px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-900/30">Rank A+</span>
                <span class="text-slate-400">• Supervisor Commended</span>
            </div>
        </div>
    </div>

    {{-- ── Squad Breakdown Cards ─────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        @foreach($squadStats as $st)
        <div class="clay-card p-6">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center font-extrabold text-white text-base {{ $loop->first ? 'clay-pill-blue' : 'clay-pill-purple' }}">
                        {{ $st['squad']->code === 'SQUAD-1' ? 'SQ1' : 'SQ2' }}
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-800 dark:text-white">{{ $st['squad']->name }}</h2>
                        <p class="text-xs text-slate-400">
                            Squad Lead: <strong>{{ $st['lead']?->name ?? 'N/A' }}</strong> ({{ $st['lead']?->team_role ?? 'Lead' }})
                        </p>
                    </div>
                </div>
                <span class="text-xs font-bold px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                    {{ $st['rank'] }}
                </span>
            </div>

            <div class="mt-5 space-y-3">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500 dark:text-slate-400">Monthly Deliverables Progress:</span>
                    <span class="font-bold text-slate-800 dark:text-white">{{ $st['completed'] }} / {{ $st['target'] }} ({{ $st['progress'] }}%)</span>
                </div>
                <div class="w-full bg-slate-100 dark:bg-slate-700/60 rounded-full h-3 overflow-hidden">
                    <div class="h-3 rounded-full {{ $loop->first ? 'bg-blue-600' : 'bg-purple-600' }}" style="width: {{ $st['progress'] }}%"></div>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 grid grid-cols-3 gap-2 text-center text-xs">
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase">Overall Score</span>
                        <span class="font-bold text-sm text-slate-800 dark:text-white">{{ number_format($st['kpi_score'], 1) }}%</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase">Pillar Focus</span>
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ $loop->first ? 'Video & QC' : 'Design & Tokens' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase">Report Status</span>
                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">Approved</span>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Deliverables Activity Table ─────────────────────────────────────── --}}
    <div class="clay-card overflow-hidden">
        <div class="p-6 border-b border-slate-100 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-800 dark:text-white">Active Deliverables & Task Progress</h2>
                <p class="text-xs text-slate-400 mt-0.5">Track deliverables, submit Google Drive evidence links, and verify QC scores.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('kpi.tasks.index') }}" class="text-xs text-blue-600 hover:text-blue-700 font-semibold flex items-center gap-1">
                    <span>View All Tasks</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-400 font-semibold border-b border-slate-100 dark:border-slate-700/60">
                        <th class="py-3 px-6">Task Title</th>
                        <th class="py-3 px-4">Squad</th>
                        <th class="py-3 px-4">Assignee</th>
                        <th class="py-3 px-4">Priority</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-center">Quality Score</th>
                        <th class="py-3 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-slate-700 dark:text-slate-300">
                    @forelse($tasks->take(8) as $t)
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="py-3.5 px-6 font-medium text-slate-900 dark:text-white">
                            {{ $t->title }}
                            @if($t->submissions->isNotEmpty())
                            <span class="block text-[11px] text-blue-500 hover:underline">
                                <a href="{{ $t->submissions->first()->evidence_url }}" target="_blank" rel="noopener noreferrer">
                                    🔗 Evidence Link
                                </a>
                            </span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $t->squad?->code === 'SQUAD-1' ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' }}">
                                {{ $t->squad?->name ?? 'N/A' }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center font-bold text-[10px]">
                                    {{ substr($t->assignee?->name ?? 'U', 0, 1) }}
                                </div>
                                <span class="font-medium">{{ $t->assignee?->name ?? 'N/A' }}</span>
                            </div>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $t->priority === 'Urgent' ? 'bg-rose-100 text-rose-700' : ($t->priority === 'High' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700') }}">
                                {{ $t->priority }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $t->status === 'Approved' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : ($t->status === 'Submitted' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-700') }}">
                                {{ $t->status }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-center font-bold">
                            @if($t->quality_score)
                                <span class="text-emerald-600 dark:text-emerald-400">{{ $t->quality_score }}%</span>
                            @else
                                <span class="text-slate-300 dark:text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-6 text-right space-x-2">
                            @if($t->status !== 'Approved')
                                <button type="button"
                                        @click="selectedTaskId = {{ $t->id }}; selectedTaskTitle = '{{ addslashes($t->title) }}'; showSubmitModal = true;"
                                        class="px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold text-[11px]">
                                    Submit Work
                                </button>
                                @if($isSupervisor)
                                <button type="button"
                                        @click="selectedTaskId = {{ $t->id }}; selectedTaskTitle = '{{ addslashes($t->title) }}'; showApproveModal = true;"
                                        class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-semibold text-[11px]">
                                    Score / Approve
                                </button>
                                @endif
                            @else
                                <span class="text-emerald-600 font-bold text-[11px] flex items-center justify-end gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    Done
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-400">
                            No deliverables logged for this cycle yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Quick Navigation Cards ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <a href="{{ route('kpi.assignments.index') }}" class="clay-card p-5 hover:border-blue-300 transition-all flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl clay-pill-blue flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                </svg>
            </div>
            <div>
                <h3 class="font-bold text-slate-800 dark:text-white text-sm">Monthly Assignments</h3>
                <p class="text-xs text-slate-400 mt-0.5">Configure deliverable targets and skill weightings.</p>
            </div>
        </a>

        <a href="{{ route('kpi.reviews.index') }}" class="clay-card p-5 hover:border-purple-300 transition-all flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl clay-pill-purple flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                </svg>
            </div>
            <div>
                <h3 class="font-bold text-slate-800 dark:text-white text-sm">Performance Reviews</h3>
                <p class="text-xs text-slate-400 mt-0.5">Interactive scorecard evaluations and final sign-offs.</p>
            </div>
        </a>

        <a href="{{ route('kpi.supervisor-reports.index') }}" class="clay-card p-5 hover:border-mint-300 transition-all flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl clay-pill-mint flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </div>
            <div>
                <h3 class="font-bold text-slate-800 dark:text-white text-sm">Squad Monthly Reports</h3>
                <p class="text-xs text-slate-400 mt-0.5">Submit squad summary reports for supervisor review.</p>
            </div>
        </a>
    </div>

    {{-- ── Modal: Create New Deliverable Task ─────────────────────────────── --}}
    <div x-show="showNewTaskModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="clay-card w-full max-w-lg p-6 bg-white dark:bg-slate-800" @click.away="showNewTaskModal = false">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Create New Deliverable Task</h3>
                <button @click="showNewTaskModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            <form action="{{ route('kpi.tasks.store') }}" method="POST" class="mt-4 space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-semibold mb-1 text-slate-700 dark:text-slate-300">Deliverable Title</label>
                    <input type="text" name="title" required placeholder="e.g. Master Promo 4K Highlight Reel"
                           class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700 dark:border-slate-600">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700 dark:text-slate-300">Squad</label>
                        <select name="squad_id" required class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700 dark:border-slate-600">
                            @foreach($squads as $sq)
                            <option value="{{ $sq->id }}">{{ $sq->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700 dark:text-slate-300">Assignee</label>
                        <select name="assignee_id" required class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700 dark:border-slate-600">
                            <option value="12">Mr. Dara (QC & Video)</option>
                            <option value="13">Mr. KimOun (Graphic Head)</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700 dark:text-slate-300">Priority</label>
                        <select name="priority" class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700 dark:border-slate-600">
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                            <option value="Urgent">Urgent</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700 dark:text-slate-300">Due Date</label>
                        <input type="date" name="due_date" value="{{ date('Y-m-d') }}"
                               class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700 dark:border-slate-600">
                    </div>
                </div>
                <div>
                    <label class="block font-semibold mb-1 text-slate-700 dark:text-slate-300">Task Notes / Instructions</label>
                    <textarea name="description" rows="2" placeholder="Specifications, dimensions, or target platform details..."
                              class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700 dark:border-slate-600"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                    <button type="button" @click="showNewTaskModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700">Create Task</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Modal: Submit Evidence Link ───────────────────────────────────── --}}
    <div x-show="showSubmitModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="clay-card w-full max-w-md p-6 bg-white dark:bg-slate-800" @click.away="showSubmitModal = false">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Submit Work Evidence</h3>
                <button @click="showSubmitModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            <form :action="'{{ url('/kpi/tasks') }}/' + selectedTaskId + '/submit'" method="POST" class="mt-4 space-y-4 text-xs">
                @csrf
                <p class="text-slate-600 dark:text-slate-300 font-medium" x-text="selectedTaskTitle"></p>
                <div>
                    <label class="block font-semibold mb-1 text-slate-700 dark:text-slate-300">Google Drive / File URL</label>
                    <input type="url" name="evidence_url" required placeholder="https://drive.google.com/..."
                           class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700 dark:border-slate-600">
                </div>
                <div>
                    <label class="block font-semibold mb-1 text-slate-700 dark:text-slate-300">Submission Notes</label>
                    <textarea name="notes" rows="2" placeholder="Export details, QC check notes..."
                              class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700 dark:border-slate-600"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                    <button type="button" @click="showSubmitModal = false" class="px-4 py-2 rounded-xl bg-slate-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold">Submit for QC</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Modal: Score / Approve ────────────────────────────────────────── --}}
    <div x-show="showApproveModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="clay-card w-full max-w-md p-6 bg-white dark:bg-slate-800" @click.away="showApproveModal = false">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">QC Score & Approval</h3>
                <button @click="showApproveModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            <form :action="'{{ url('/kpi/tasks') }}/' + selectedTaskId + '/approve'" method="POST" class="mt-4 space-y-4 text-xs">
                @csrf
                <p class="text-slate-600 dark:text-slate-300 font-medium" x-text="selectedTaskTitle"></p>
                <div>
                    <label class="block font-semibold mb-1 text-slate-700 dark:text-slate-300">Quality Score (0 - 100%)</label>
                    <input type="number" step="0.5" min="0" max="100" name="quality_score" value="95.0" required
                           class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700 dark:border-slate-600">
                </div>
                <div>
                    <label class="block font-semibold mb-1 text-slate-700 dark:text-slate-300">Feedback / Review Notes</label>
                    <textarea name="feedback" rows="2" placeholder="Approved with commendation for color grade and typography..."
                              class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700 dark:border-slate-600"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                    <button type="button" @click="showApproveModal = false" class="px-4 py-2 rounded-xl bg-slate-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-semibold">Approve & Score</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
