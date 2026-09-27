@extends('layouts.app')
@section('title', 'Staff KPI Management')
@section('page_title', 'Staff KPI Dashboard')

@section('content')

<style>
/* 3D Claymorphic Tokens */
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
    showRateModal: false,
    selectedUserId: null,
    selectedUserName: '',
    selectedSquadId: {{ $currentSquad?->id ?? 1 }},
    scoreProd: 95,
    scoreQual: 95,
    scoreTat: 92,
    scoreTeam: 95,
    reviewNotes: '',
    reviewStatus: 'Approved',
    get overallScore() {
        return ((this.scoreProd * 0.35) + (this.scoreQual * 0.35) + (this.scoreTat * 0.20) + (this.scoreTeam * 0.10)).toFixed(1);
    },
    get rankBand() {
        let s = parseFloat(this.overallScore);
        if (s >= 95.0) return 'Outstanding';
        if (s >= 85.0) return 'Exceeds Expectations';
        if (s >= 70.0) return 'Meets Expectations';
        return 'Needs Improvement';
    }
}">

    {{-- ── Banner ───────────────────────────────────────────────────────────── --}}
    <div class="relative overflow-hidden rounded-3xl p-6 sm:p-8 text-white shadow-xl"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #0284c7 100%);">
        <div class="absolute -right-12 -top-12 w-64 h-64 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>
        <div class="absolute right-1/3 -bottom-16 w-56 h-56 rounded-full bg-sky-400/20 blur-xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md border border-white/20 text-xs font-semibold tracking-wide">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Staff KPI Management System</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                    Welcome, {{ auth()->user()->name }}! 👋
                </h1>
                <p class="text-blue-100 text-sm max-w-2xl">
                    @if(auth()->user()->username === 'dara')
                        You are viewing <strong>Digital Media Squad 1</strong>. Evaluate and give monthly KPI scores to the staff under your leadership.
                    @elseif(auth()->user()->username === 'kim')
                        You are viewing <strong>Digital Media Squad 2</strong>. Evaluate and give monthly KPI scores to the creative staff under your leadership.
                    @else
                        Supervisor Overview: Monitor and review monthly staff evaluations across Squad 1 (Dara) and Squad 2 (Kim).
                    @endif
                </p>
                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <span class="text-xs bg-black/20 backdrop-blur-sm px-3 py-1 rounded-full border border-white/10">
                        Current Month: <strong>{{ $currentPeriod?->name ?? 'September 2026' }}</strong>
                    </span>
                    <span class="text-xs bg-black/20 backdrop-blur-sm px-3 py-1 rounded-full border border-white/10">
                        Active Squad: <strong>{{ $currentSquad?->name ?? 'Squad' }}</strong> (Lead: {{ $currentSquad?->lead?->name ?? 'Lead' }})
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('kpi.evaluations.index') }}"
                   class="clay-btn bg-white text-blue-700 hover:bg-blue-50 px-4 py-2.5 text-sm flex items-center gap-2 shadow-md">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                    </svg>
                    <span>Give Staff KPI</span>
                </a>
                <a href="{{ route('kpi.team.index') }}"
                   class="clay-btn bg-white/20 hover:bg-white/30 text-white backdrop-blur-md border border-white/30 px-4 py-2.5 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                    <span>Manage Team Members</span>
                </a>
                @if($currentSquad)
                <a href="{{ route('kpi.squad.pdf', $currentSquad->id) }}" target="_blank"
                   class="clay-btn bg-white/20 hover:bg-white/30 text-white backdrop-blur-md border border-white/30 px-4 py-2.5 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Export Squad PDF</span>
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Squad Selector (if Supervisor or Superadmin) ────────────────────── --}}
    @if($isSupervisor && $squads->count() > 1)
    <div class="flex items-center gap-2 p-1.5 rounded-2xl bg-slate-100 dark:bg-slate-800 w-fit">
        @foreach($squads as $sq)
        <a href="{{ route('kpi.index', ['squad_id' => $sq->id]) }}"
           class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ ($currentSquad?->id === $sq->id) ? 'bg-white dark:bg-slate-700 text-blue-600 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
            {{ $sq->name }} ({{ $sq->members->count() }} Staff)
        </a>
        @endforeach
    </div>
    @endif

    {{-- ── 4 Stat Cards ─────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- 1. Staff Members in Squad -->
        <div class="clay-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Team Size</span>
                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="text-2xl font-black text-slate-800 dark:text-white">{{ $staffMembers->count() }}</span>
                        <span class="text-xs text-slate-400 font-medium">staff members</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl clay-pill-blue flex items-center justify-center text-white">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
                <span>Lead: <strong>{{ $currentSquad?->lead?->name ?? 'N/A' }}</strong></span>
                <a href="{{ route('kpi.team.index') }}" class="text-blue-600 hover:underline font-semibold">+ Add Staff</a>
            </div>
        </div>

        <!-- 2. Staff Evaluated this Month -->
        <div class="clay-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Monthly Evaluations</span>
                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="text-2xl font-black text-slate-800 dark:text-white">{{ $evaluatedCount }}</span>
                        <span class="text-xs text-slate-400 font-medium">/ {{ $staffMembers->count() }} rated</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl clay-pill-purple flex items-center justify-center text-white">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                @php $evalPct = $staffMembers->count() > 0 ? round(($evaluatedCount / $staffMembers->count()) * 100) : 0; @endphp
                <div class="w-full bg-slate-100 dark:bg-slate-700/60 rounded-full h-2 overflow-hidden">
                    <div class="bg-purple-600 h-2 rounded-full" style="width: {{ $evalPct }}%"></div>
                </div>
                <div class="flex justify-between text-[11px] text-slate-400 mt-1">
                    <span>Evaluation Progress</span>
                    <span class="font-bold text-purple-600">{{ $evalPct }}%</span>
                </div>
            </div>
        </div>

        <!-- 3. Average Staff KPI Score -->
        <div class="clay-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Average Squad KPI</span>
                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="text-2xl font-black text-slate-800 dark:text-white">{{ $avgKpiScore }}%</span>
                        <span class="text-xs text-emerald-600 font-bold">Good</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl clay-pill-mint flex items-center justify-center text-white">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-xs text-emerald-600 font-medium">
                <span class="px-2 py-0.5 rounded-full bg-emerald-50">Department Target ≥ 85%</span>
            </div>
        </div>

        <!-- 4. Outstanding Performers -->
        <div class="clay-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Rank A+ Performers</span>
                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="text-2xl font-black text-slate-800 dark:text-white">{{ $outstandingCount }}</span>
                        <span class="text-xs text-amber-600 font-bold">≥ 95% Score</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl clay-pill-amber flex items-center justify-center text-white">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.004 0H8.624m5.004 0V9.75m-5.004 0V9.75m0 0a2.25 2.25 0 0 1 2.25-2.25h.504a2.25 2.25 0 0 1 2.25 2.25m-5.004 0h5.004" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-xs text-amber-600 font-medium">
                <span class="px-2 py-0.5 rounded-full bg-amber-50">Award Eligible</span>
            </div>
        </div>
    </div>

    {{-- ── Staff Members & Monthly KPI Table ────────────────────────────────── --}}
    <div class="clay-card overflow-hidden">
        <div class="p-6 border-b border-slate-100 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-800 dark:text-white">
                    {{ $currentSquad?->name ?? 'Squad' }} • Staff Monthly KPI Evaluations
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">
                    Rate each staff member numerically (Productivity, Quality, Speed, Teamwork), write review notes, and export PDF.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('kpi.team.index') }}" class="clay-btn bg-slate-100 hover:bg-slate-200 text-slate-700 px-3.5 py-1.5 text-xs font-semibold flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Add Member to Squad</span>
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-400 font-semibold border-b border-slate-100 dark:border-slate-700/60">
                        <th class="py-3 px-6">Staff Member</th>
                        <th class="py-3 px-4">Role Title</th>
                        <th class="py-3 px-4 text-center">Productivity (35%)</th>
                        <th class="py-3 px-4 text-center">Quality (35%)</th>
                        <th class="py-3 px-4 text-center">TAT Speed (20%)</th>
                        <th class="py-3 px-4 text-center">Teamwork (10%)</th>
                        <th class="py-3 px-4 text-center">Total KPI</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-slate-700 dark:text-slate-300">
                    @forelse($staffMembers as $staff)
                    @php
                        $rev = $reviews->get($staff->id);
                    @endphp
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="py-3.5 px-6">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 dark:bg-slate-700 dark:text-blue-300 font-bold flex items-center justify-center text-xs">
                                    {{ substr($staff->name, 0, 1) }}
                                </div>
                                <div>
                                    <span class="font-bold text-slate-900 dark:text-white block">{{ $staff->name }}</span>
                                    <span class="text-[11px] text-slate-400 font-mono">@ {{ $staff->username }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="py-3.5 px-4 font-medium text-slate-600 dark:text-slate-300">
                            {{ $staff->pivot->role_title ?? 'Team Member' }}
                        </td>
                        <td class="py-3.5 px-4 text-center font-bold">
                            {{ $rev ? $rev->productivity_score . '%' : '—' }}
                        </td>
                        <td class="py-3.5 px-4 text-center font-bold">
                            {{ $rev ? $rev->quality_score . '%' : '—' }}
                        </td>
                        <td class="py-3.5 px-4 text-center font-bold">
                            {{ $rev ? $rev->deadline_score . '%' : '—' }}
                        </td>
                        <td class="py-3.5 px-4 text-center font-bold">
                            {{ $rev ? $rev->teamwork_score . '%' : '—' }}
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            @if($rev)
                                <span class="font-black text-sm text-blue-600 dark:text-blue-400 block">{{ number_format($rev->overall_kpi, 1) }}%</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $rev->overall_kpi >= 95 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                                    {{ $rev->performance_band }}
                                </span>
                            @else
                                <span class="text-slate-400 text-xs italic">Not rated</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4">
                            @if($rev)
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold {{ $rev->status === 'Finalized' ? 'bg-purple-50 text-purple-700' : ($rev->status === 'Approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700') }}">
                                    {{ $rev->status }}
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-500">
                                    Pending
                                </span>
                            @endif
                        </td>
                        <td class="py-3.5 px-6 text-right space-x-1.5 whitespace-nowrap">
                            <button type="button"
                                    @click="
                                        selectedUserId = {{ $staff->id }};
                                        selectedUserName = '{{ addslashes($staff->name) }}';
                                        selectedSquadId = {{ $currentSquad?->id ?? 1 }};
                                        scoreProd = {{ $rev?->productivity_score ?? 95 }};
                                        scoreQual = {{ $rev?->quality_score ?? 95 }};
                                        scoreTat = {{ $rev?->deadline_score ?? 92 }};
                                        scoreTeam = {{ $rev?->teamwork_score ?? 95 }};
                                        reviewNotes = '{{ addslashes($rev?->manager_notes ?? '') }}';
                                        reviewStatus = '{{ $rev?->status ?? 'Approved' }}';
                                        showRateModal = true;
                                    "
                                    class="px-2.5 py-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold text-[11px] shadow-sm">
                                {{ $rev ? 'Edit KPI' : 'Rate KPI' }}
                            </button>
                            @if($rev)
                            <a href="{{ route('kpi.evaluations.pdf', $rev->id) }}" target="_blank"
                               class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-[11px]">
                                PDF
                            </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-8 text-center text-slate-400">
                            No staff members currently in this squad. Click <strong>"Add Member to Squad"</strong> to add staff from existing users.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Modal: Rate Staff KPI (Scores, Notes, Live Calculation) ──────────── --}}
    <div x-show="showRateModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="clay-card w-full max-w-lg p-6 bg-white dark:bg-slate-800" @click.away="showRateModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Give Monthly Staff KPI</h3>
                    <p class="text-xs text-slate-400">Staff: <strong class="text-blue-600" x-text="selectedUserName"></strong> • {{ $currentPeriod?->name }}</p>
                </div>
                <button @click="showRateModal = false" class="text-slate-400 hover:text-slate-600 text-base">✕</button>
            </div>

            <form action="{{ route('kpi.evaluations.rate') }}" method="POST" class="mt-4 space-y-4 text-xs">
                @csrf
                <input type="hidden" name="user_id" :value="selectedUserId">
                <input type="hidden" name="squad_id" :value="selectedSquadId">
                <input type="hidden" name="kpi_period_id" value="{{ $currentPeriod?->id ?? 1 }}">

                <!-- Numerical KPI Ratings -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-600">
                        <div class="flex justify-between items-center mb-1">
                            <label class="font-bold text-slate-800 dark:text-white">Productivity (35%)</label>
                            <span class="font-black text-blue-600 text-sm" x-text="scoreProd + '%'"></span>
                        </div>
                        <input type="range" min="0" max="100" step="1" x-model.number="scoreProd" name="productivity_score" class="w-full accent-blue-600">
                        <span class="text-[10px] text-slate-400">Volume & output of deliverables</span>
                    </div>

                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-600">
                        <div class="flex justify-between items-center mb-1">
                            <label class="font-bold text-slate-800 dark:text-white">Quality (35%)</label>
                            <span class="font-black text-purple-600 text-sm" x-text="scoreQual + '%'"></span>
                        </div>
                        <input type="range" min="0" max="100" step="1" x-model.number="scoreQual" name="quality_score" class="w-full accent-purple-600">
                        <span class="text-[10px] text-slate-400">Accuracy & brand compliance</span>
                    </div>

                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-600">
                        <div class="flex justify-between items-center mb-1">
                            <label class="font-bold text-slate-800 dark:text-white">TAT Speed (20%)</label>
                            <span class="font-black text-emerald-600 text-sm" x-text="scoreTat + '%'"></span>
                        </div>
                        <input type="range" min="0" max="100" step="1" x-model.number="scoreTat" name="deadline_score" class="w-full accent-emerald-600">
                        <span class="text-[10px] text-slate-400">Turnaround hours & deadlines</span>
                    </div>

                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-600">
                        <div class="flex justify-between items-center mb-1">
                            <label class="font-bold text-slate-800 dark:text-white">Teamwork (10%)</label>
                            <span class="font-black text-amber-600 text-sm" x-text="scoreTeam + '%'"></span>
                        </div>
                        <input type="range" min="0" max="100" step="1" x-model.number="scoreTeam" name="teamwork_score" class="w-full accent-amber-600">
                        <span class="text-[10px] text-slate-400">Initiative, collaboration & attitude</span>
                    </div>
                </div>

                <!-- Live Overall Calculation Box -->
                <div class="p-4 rounded-2xl bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-slate-700 dark:to-slate-700 border border-blue-200/60 dark:border-slate-600 flex items-center justify-between">
                    <div>
                        <span class="text-xs uppercase font-bold text-slate-500 dark:text-slate-300">Computed Overall KPI Score</span>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-2xl font-black text-blue-700 dark:text-blue-300" x-text="overallScore + '%'"></span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-white dark:bg-slate-800 text-slate-800 dark:text-white shadow-sm" x-text="rankBand"></span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Review Status</label>
                        <select name="status" x-model="reviewStatus" class="px-3 py-1.5 border rounded-xl dark:bg-slate-800 text-xs font-semibold">
                            <option value="Approved">Approved & Completed</option>
                            <option value="Submitted">Submit for Supervisor Review</option>
                            <option value="Draft">Draft Only</option>
                        </select>
                    </div>
                </div>

                <!-- Notes / Comments -->
                <div>
                    <label class="block font-bold mb-1 text-slate-700 dark:text-slate-300">
                        Review Notes & Comments <span class="text-red-500">*</span>
                    </label>
                    <textarea name="manager_notes" rows="3" required x-model="reviewNotes"
                              placeholder="Write detailed notes on deliverables, strengths, speed, and areas to improve..."
                              class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700 dark:border-slate-600"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                    <button type="button" @click="showRateModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold shadow-md">
                        Save & Record KPI
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
