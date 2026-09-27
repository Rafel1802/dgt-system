@extends('layouts.app')
@section('title', 'Staff KPI Management')
@section('page_title', 'Staff KPI Dashboard')

@section('content')

@include('kpi.styles')

<div class="space-y-6 pb-12" x-data="{
    showRateModal: false,
    showHistoryModal: false,
    showUploadModal: false,
    selectedUserId: null,
    selectedUserName: '',
    selectedSquadId: {{ $currentSquad?->id ?? 1 }},
    selectedReviewId: null,
    scoreProd: 95,
    scoreQual: 95,
    scoreTat: 92,
    scoreTeam: 95,
    reviewMonth: {{ $selectedMonth ?? date('n') }},
    reviewYear: {{ $selectedYear ?? date('Y') }},
    evaluationDate: '{{ date('Y-m-d') }}',
    reviewNotes: '',
    reviewStatus: 'Approved',
    historyStaffName: '',
    historyRecords: [],
    get overallScore() {
        return ((this.scoreProd * 0.35) + (this.scoreQual * 0.35) + (this.scoreTat * 0.20) + (this.scoreTeam * 0.10)).toFixed(1);
    },
    get rankBand() {
        let s = parseFloat(this.overallScore);
        if (s >= 95.0) return 'Outstanding';
        if (s >= 85.0) return 'Exceeds Expectations';
        if (s >= 70.0) return 'Meets Expectations';
        return 'Needs Improvement';
    },
    openHistory(staffName, records) {
        this.historyStaffName = staffName;
        this.historyRecords = records;
        this.showHistoryModal = true;
    },
    openUpload(userId, userName, reviewId) {
        this.selectedUserId = userId;
        this.selectedUserName = userName;
        this.selectedReviewId = reviewId;
        this.showUploadModal = true;
    }
}">

    {{-- ── Hero Banner ──────────────────────────────────────────────────────── --}}
    <div class="kpi-hero-banner relative overflow-hidden rounded-3xl p-6 sm:p-8 text-white shadow-xl">
        <div class="absolute -right-12 -top-12 w-64 h-64 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>
        <div class="absolute right-1/3 -bottom-16 w-56 h-56 rounded-full bg-sky-400/20 blur-xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md border border-white/20 text-xs font-semibold tracking-wide">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Staff KPI Management System</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                    Welcome, {{ auth()->user()->name }}! 👋
                </h1>
                <p class="text-blue-100 text-sm max-w-2xl">
                    @if(auth()->user()->username === 'dara')
                        You are viewing <strong>Digital Media Squad 1</strong> (Lead: Mr. Dara). Evaluate, review history, and export official KPI certificates for your staff.
                    @elseif(auth()->user()->username === 'kim')
                        You are viewing <strong>Digital Media Squad 2</strong> (Lead: Mr. Kim). Evaluate, review history, and export official KPI certificates for your creative staff.
                    @else
                        Supervisor Overview: Monitor and review staff evaluations across Squad 1 (Dara) and Squad 2 (Kim).
                    @endif
                </p>
                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <span class="text-xs bg-black/25 backdrop-blur-sm px-3.5 py-1.5 rounded-full border border-white/15 font-medium">
                        Current Month: <strong class="text-sky-300">{{ $currentPeriod?->name ?? 'September 2026' }}</strong>
                    </span>
                    <span class="text-xs bg-black/25 backdrop-blur-sm px-3.5 py-1.5 rounded-full border border-white/15 font-medium">
                        Active Squad: <strong class="text-sky-300">{{ $currentSquad?->name ?? 'Squad' }}</strong> (Lead: {{ $currentSquad?->lead?->name ?? 'Lead' }})
                    </span>
                </div>
            </div>

            {{-- Clean Quick Action Strip --}}
            <div class="flex flex-wrap items-center gap-2.5 pt-2 lg:pt-0">
                <a href="{{ route('kpi.evaluations.index', ['squad_id' => $currentSquad?->id]) }}"
                   class="clay-btn bg-white text-blue-700 hover:bg-blue-50 px-4 py-2.5 text-xs sm:text-sm font-bold flex items-center gap-2 shadow-lg">
                    <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                    </svg>
                    <span>Give Staff KPI</span>
                </a>

                <a href="{{ route('kpi.evaluations.export-all-pdf', ['squad_id' => $currentSquad?->id, 'period_id' => $currentPeriod?->id]) }}" target="_blank"
                   class="clay-btn bg-white/15 hover:bg-white/25 text-white backdrop-blur-md border border-white/25 px-3.5 py-2.5 text-xs font-semibold flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-sky-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Export All Staff PDFs</span>
                </a>

                @if($currentSquad)
                <a href="{{ route('kpi.squad.pdf', $currentSquad->id) }}" target="_blank"
                   class="clay-btn bg-white/15 hover:bg-white/25 text-white backdrop-blur-md border border-white/25 px-3.5 py-2.5 text-xs font-semibold flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <span>Squad Summary</span>
                </a>
                @endif

                <a href="{{ route('kpi.team.index', ['squad_id' => $currentSquad?->id]) }}"
                   class="clay-btn bg-white/15 hover:bg-white/25 text-white backdrop-blur-md border border-white/25 px-3.5 py-2.5 text-xs font-semibold flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-purple-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                    <span>Team ({{ $rawMembers->count() }})</span>
                </a>
            </div>
        </div>
    </div>

    {{-- ── Squad Selector (Supervisor) ─────────────────────────────────────── --}}
    @if($isSupervisor && $squads->count() > 1)
    <div class="kpi-tab-container flex items-center gap-2 p-1.5 rounded-2xl w-fit">
        @foreach($squads as $sq)
        <a href="{{ route('kpi.index', ['squad_id' => $sq->id, 'month' => $selectedMonth, 'year' => $selectedYear, 'search' => $search]) }}"
           class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ ($currentSquad?->id === $sq->id) ? 'kpi-tab-active shadow-sm' : 'kpi-tab-inactive' }}">
            {{ $sq->name }} ({{ $sq->members->count() }} Staff)
        </a>
        @endforeach
    </div>
    @endif

    {{-- ── 4 Stat Cards ─────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- 1. Staff Members in Squad -->
        <div class="clay-card p-5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="kpi-stat-title">Squad Team Size</span>
                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="kpi-stat-value">{{ $rawMembers->count() }}</span>
                        <span class="kpi-stat-sub">active staff</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl clay-pill-blue flex items-center justify-center text-white flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/50 flex items-center justify-between text-xs">
                <span class="kpi-stat-sub">Lead: <strong class="text-slate-800 dark:text-sky-300">{{ $currentSquad?->lead?->name ?? 'N/A' }}</strong></span>
                <a href="{{ route('kpi.team.index') }}" class="text-blue-500 hover:text-blue-400 font-bold">+ Manage Team</a>
            </div>
        </div>

        <!-- 2. Staff Evaluated this Month -->
        <div class="clay-card p-5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="kpi-stat-title">Monthly Evaluations</span>
                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="kpi-stat-value">{{ $evaluatedCount }}</span>
                        <span class="kpi-stat-sub">/ {{ $rawMembers->count() }} rated</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl clay-pill-purple flex items-center justify-center text-white flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/50">
                @php $evalPct = $rawMembers->count() > 0 ? round(($evaluatedCount / $rawMembers->count()) * 100) : 0; @endphp
                <div class="w-full bg-slate-100 dark:bg-slate-700/60 rounded-full h-2 overflow-hidden">
                    <div class="bg-purple-500 h-2 rounded-full" style="width: {{ $evalPct }}%"></div>
                </div>
                <div class="flex justify-between text-[11px] mt-1.5">
                    <span class="kpi-stat-sub">Cycle Progress</span>
                    <span class="font-bold text-purple-400">{{ $evalPct }}%</span>
                </div>
            </div>
        </div>

        <!-- 3. Average Staff KPI Score -->
        <div class="clay-card p-5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="kpi-stat-title">Average Squad KPI</span>
                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="kpi-stat-value">{{ $avgKpiScore }}%</span>
                        <span class="text-xs font-bold text-emerald-400">Target Met</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl clay-pill-mint flex items-center justify-center text-white flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/50 flex items-center gap-1.5 text-xs">
                <span class="px-2 py-0.5 rounded-full kpi-badge-success font-semibold text-[11px]">Benchmark ≥ 85%</span>
            </div>
        </div>

        <!-- 4. Outstanding Performers -->
        <div class="clay-card p-5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="kpi-stat-title">Rank A+ Performers</span>
                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="kpi-stat-value">{{ $outstandingCount }}</span>
                        <span class="text-xs font-bold text-amber-400">≥ 95% Score</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl clay-pill-amber flex items-center justify-center text-white flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.004 0H8.624m5.004 0V9.75m-5.004 0V9.75m0 0a2.25 2.25 0 0 1 2.25-2.25h.504a2.25 2.25 0 0 1 2.25 2.25m-5.004 0h5.004" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/50 flex items-center gap-1.5 text-xs">
                <span class="px-2 py-0.5 rounded-full kpi-badge-amber font-semibold text-[11px]">Award Eligible</span>
            </div>
        </div>
    </div>

    {{-- ── Advanced Filter & Search Bar ────────────────────────────────────── --}}
    <div class="clay-card p-4">
        <form method="GET" action="{{ route('kpi.index') }}" class="flex flex-wrap items-center gap-3">
            @if($selectedSquadId)
            <input type="hidden" name="squad_id" value="{{ $selectedSquadId }}">
            @endif

            {{-- 1. Search --}}
            <div class="flex-1 min-w-[200px]">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                    </span>
                    <input type="text" name="search" value="{{ $search ?? '' }}"
                           placeholder="Search staff name, role, username..."
                           class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            {{-- 2. Staff Member Filter --}}
            <div class="w-48">
                <select name="member_id" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                    <option value="">-- All Staff Members --</option>
                    @foreach($rawMembers as $rm)
                    <option value="{{ $rm->id }}" {{ ($memberId == $rm->id) ? 'selected' : '' }}>
                        {{ $rm->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- 3. Month Filter --}}
            <div class="w-36">
                <select name="month" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-white font-medium">
                    <option value="">-- Month --</option>
                    @for($m = 1; $m <= 12; $m++)
                    @php $mName = date('F', mktime(0, 0, 0, $m, 1)); @endphp
                    <option value="{{ $m }}" {{ ($selectedMonth == $m || (!$selectedMonth && $m == 9)) ? 'selected' : '' }}>
                        {{ $mName }}
                    </option>
                    @endfor
                </select>
            </div>

            {{-- 4. Year Filter --}}
            <div class="w-28">
                <select name="year" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-white font-medium">
                    @for($y = 2025; $y <= 2028; $y++)
                    <option value="{{ $y }}" {{ ($selectedYear == $y || (!$selectedYear && $y == 2026)) ? 'selected' : '' }}>
                        {{ $y }}
                    </option>
                    @endfor
                </select>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-2">
                <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs shadow-sm">
                    Filter
                </button>
                @if(!empty($search) || !empty($memberId) || !empty($selectedMonth))
                <a href="{{ route('kpi.index', ['squad_id' => $selectedSquadId]) }}" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-xs font-semibold">
                    Reset
                </a>
                @endif
            </div>
        </form>
    </div>

    {{-- ── Staff Members & Monthly KPI Table ────────────────────────────────── --}}
    <div class="clay-card overflow-hidden">
        <div class="p-6 kpi-card-header flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold kpi-card-title">
                    {{ $currentSquad?->name ?? 'Squad' }} • Staff Monthly Evaluations ({{ $currentPeriod?->name }})
                </h2>
                <p class="text-xs kpi-card-desc mt-0.5">
                    Official 5-Star Performance Evaluations, Signed PDF Uploads, and Full Historical Records.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('kpi.evaluations.export-all-pdf', ['squad_id' => $currentSquad?->id, 'period_id' => $currentPeriod?->id]) }}" target="_blank"
                   class="clay-btn bg-blue-50 hover:bg-blue-100 text-blue-700 dark:bg-sky-950 dark:text-sky-300 dark:hover:bg-sky-900 border border-blue-200 dark:border-sky-800 px-3.5 py-1.5 text-xs font-bold flex items-center gap-1.5 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    <span>Export All Staff PDFs</span>
                </a>
                <a href="{{ route('kpi.team.index') }}" class="clay-btn bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 px-3.5 py-1.5 text-xs font-semibold flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Manage Squad Members</span>
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="kpi-table-head text-xs font-semibold">
                        <th class="py-3 px-6">Staff Member</th>
                        <th class="py-3 px-4">Role Title</th>
                        <th class="py-3 px-4 text-center">Quality (35%)</th>
                        <th class="py-3 px-4 text-center">Productivity (35%)</th>
                        <th class="py-3 px-4 text-center">TAT Speed (20%)</th>
                        <th class="py-3 px-4 text-center">Teamwork (10%)</th>
                        <th class="py-3 px-4 text-center">Overall KPI</th>
                        <th class="py-3 px-4">Status &amp; PDF</th>
                        <th class="py-3 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($staffMembers as $staff)
                    @php
                        $rev = $reviews->get($staff->id);
                        $history = $allUserReviews->get($staff->id, collect());
                    @endphp
                    <tr class="kpi-table-row">
                        <td class="py-3.5 px-6">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 dark:bg-slate-700 dark:text-blue-300 font-extrabold flex items-center justify-center text-xs flex-shrink-0">
                                    {{ substr($staff->name, 0, 1) }}
                                </div>
                                <div>
                                    <span class="font-bold kpi-card-title block text-[13px]">{{ $staff->name }}</span>
                                    <span class="text-[11px] text-slate-400 font-mono">@ {{ $staff->username }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="py-3.5 px-4 font-medium kpi-card-desc">
                            {{ $staff->pivot->role_title ?? 'Team Member' }}
                        </td>
                        <td class="py-3.5 px-4 text-center font-bold">
                            {{ $rev ? $rev->quality_score . '%' : '—' }}
                        </td>
                        <td class="py-3.5 px-4 text-center font-bold">
                            {{ $rev ? $rev->productivity_score . '%' : '—' }}
                        </td>
                        <td class="py-3.5 px-4 text-center font-bold">
                            {{ $rev ? $rev->deadline_score . '%' : '—' }}
                        </td>
                        <td class="py-3.5 px-4 text-center font-bold">
                            {{ $rev ? $rev->teamwork_score . '%' : '—' }}
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            @if($rev)
                                <span class="font-black text-sm text-blue-500 dark:text-sky-300 block">{{ number_format($rev->overall_kpi, 1) }}%</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $rev->overall_kpi >= 95 ? 'kpi-badge-amber' : 'kpi-badge-success' }}">
                                    {{ $rev->performance_band }}
                                </span>
                            @else
                                <span class="text-slate-400 text-xs italic">Not rated</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4">
                            <div class="flex flex-col gap-1">
                                @if($rev)
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold w-fit {{ $rev->status === 'Finalized' ? 'kpi-badge-purple' : ($rev->status === 'Approved' ? 'kpi-badge-success' : 'kpi-badge-blue') }}">
                                        {{ $rev->status }}
                                    </span>
                                    @if($rev->uploaded_pdf_path)
                                    <a href="{{ asset($rev->uploaded_pdf_path) }}" target="_blank"
                                       class="text-[10px] text-emerald-500 hover:text-emerald-400 font-bold flex items-center gap-1">
                                        ✓ Signed PDF
                                    </a>
                                    @endif
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold kpi-badge-pending w-fit">
                                        Pending
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="py-3.5 px-6 text-right whitespace-nowrap space-x-1">
                            {{-- Rate / Edit --}}
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
                                        evaluationDate = '{{ $rev?->evaluation_date ? \Carbon\Carbon::parse($rev->evaluation_date)->format('Y-m-d') : date('Y-m-d') }}';
                                        showRateModal = true;
                                    "
                                    class="kpi-btn-rate px-2.5 py-1.5 rounded-lg font-bold text-xs shadow-sm">
                                {{ $rev ? 'Edit KPI' : 'Rate KPI' }}
                            </button>

                            {{-- History Button --}}
                            <button type="button"
                                    @click="openHistory('{{ addslashes($staff->name) }}', {{ json_encode($history) }})"
                                    class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold text-xs">
                                History ({{ $history->count() }})
                            </button>

                            {{-- Upload PDF --}}
                            <button type="button"
                                    @click="openUpload({{ $staff->id }}, '{{ addslashes($staff->name) }}', {{ $rev?->id ?? 'null' }})"
                                    class="px-2.5 py-1.5 rounded-lg bg-sky-50 hover:bg-sky-100 dark:bg-sky-950 dark:hover:bg-sky-900 text-sky-700 dark:text-sky-300 font-semibold text-xs">
                                Upload PDF
                            </button>

                            {{-- Export PDF --}}
                            @if($rev)
                            <a href="{{ route('kpi.evaluations.pdf', $rev->id) }}" target="_blank"
                               class="kpi-btn-pdf px-2.5 py-1.5 rounded-lg font-semibold text-xs inline-block">
                                PDF
                            </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-8 text-center text-slate-400">
                            No staff members match the selected filter. Try clearing your search or filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Modal: Rate Staff KPI (Scores, Notes, Month/Year & Date Picker, PDF upload) ──── --}}
    <div x-show="showRateModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="clay-card kpi-modal-card w-full max-w-lg p-6" @click.away="showRateModal = false">
            <div class="flex items-center justify-between pb-3 border-b kpi-card-header">
                <div>
                    <h3 class="text-base font-bold kpi-card-title">Give Monthly Staff KPI</h3>
                    <p class="text-xs kpi-card-desc">Staff: <strong class="text-sky-400 font-bold" x-text="selectedUserName"></strong></p>
                </div>
                <button @click="showRateModal = false" class="text-slate-400 hover:text-slate-200 text-lg font-bold">✕</button>
            </div>

            <form action="{{ route('kpi.evaluations.rate') }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-4 text-xs">
                @csrf
                <input type="hidden" name="user_id" :value="selectedUserId">
                <input type="hidden" name="squad_id" :value="selectedSquadId">

                <!-- Date & Month / Year Selector -->
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block font-bold mb-1 kpi-card-title">Evaluation Month</label>
                        <select name="month" x-model.number="reviewMonth" class="kpi-modal-select w-full px-3 py-2 border rounded-xl text-xs font-semibold">
                            @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 kpi-card-title">Evaluation Year</label>
                        <select name="year" x-model.number="reviewYear" class="kpi-modal-select w-full px-3 py-2 border rounded-xl text-xs font-semibold">
                            @for($y = 2025; $y <= 2028; $y++)
                            <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 kpi-card-title">Rate Date</label>
                        <input type="date" name="evaluation_date" x-model="evaluationDate"
                               class="kpi-modal-input w-full px-3 py-2 border rounded-xl text-xs">
                    </div>
                </div>

                <!-- Numerical KPI Ratings (4 Pillars) -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="kpi-modal-box p-3 rounded-2xl">
                        <div class="flex justify-between items-center mb-1">
                            <label class="font-bold kpi-card-title">Work Quality (35%)</label>
                            <span class="font-black text-purple-400 text-sm" x-text="scoreQual + '%'"></span>
                        </div>
                        <input type="range" min="0" max="100" step="1" x-model.number="scoreQual" name="quality_score" class="w-full accent-purple-500">
                        <span class="text-[10px] kpi-stat-sub">Accuracy &amp; brand compliance</span>
                    </div>

                    <div class="kpi-modal-box p-3 rounded-2xl">
                        <div class="flex justify-between items-center mb-1">
                            <label class="font-bold kpi-card-title">Productivity (35%)</label>
                            <span class="font-black text-sky-400 text-sm" x-text="scoreProd + '%'"></span>
                        </div>
                        <input type="range" min="0" max="100" step="1" x-model.number="scoreProd" name="productivity_score" class="w-full accent-blue-500">
                        <span class="text-[10px] kpi-stat-sub">Deliverables volume &amp; output</span>
                    </div>

                    <div class="kpi-modal-box p-3 rounded-2xl">
                        <div class="flex justify-between items-center mb-1">
                            <label class="font-bold kpi-card-title">TAT Speed (20%)</label>
                            <span class="font-black text-emerald-400 text-sm" x-text="scoreTat + '%'"></span>
                        </div>
                        <input type="range" min="0" max="100" step="1" x-model.number="scoreTat" name="deadline_score" class="w-full accent-emerald-500">
                        <span class="text-[10px] kpi-stat-sub">Turnaround hours &amp; deadlines</span>
                    </div>

                    <div class="kpi-modal-box p-3 rounded-2xl">
                        <div class="flex justify-between items-center mb-1">
                            <label class="font-bold kpi-card-title">Teamwork (10%)</label>
                            <span class="font-black text-amber-400 text-sm" x-text="scoreTeam + '%'"></span>
                        </div>
                        <input type="range" min="0" max="100" step="1" x-model.number="scoreTeam" name="teamwork_score" class="w-full accent-amber-500">
                        <span class="text-[10px] kpi-stat-sub">Initiative, collaboration &amp; attitude</span>
                    </div>
                </div>

                <!-- Live Overall Calculation Box -->
                <div class="kpi-modal-calc p-4 rounded-2xl flex items-center justify-between">
                    <div>
                        <span class="text-xs uppercase font-bold kpi-stat-title">Computed Overall KPI Score</span>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-2xl font-black text-sky-400" x-text="overallScore + '%'"></span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold kpi-badge-blue" x-text="rankBand"></span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase kpi-stat-title mb-1">Review Status</label>
                        <select name="status" x-model="reviewStatus" class="kpi-modal-select px-3 py-1.5 border rounded-xl text-xs font-semibold">
                            <option value="Approved">Approved &amp; Completed</option>
                            <option value="Submitted">Submit for Supervisor Review</option>
                            <option value="Draft">Draft Only</option>
                        </select>
                    </div>
                </div>

                <!-- Notes / Comments -->
                <div>
                    <label class="block font-bold mb-1 kpi-card-title">
                        Review Notes &amp; Comments <span class="text-red-400">*</span>
                    </label>
                    <textarea name="manager_notes" rows="3" required x-model="reviewNotes"
                              placeholder="Write detailed notes on deliverables, strengths, speed, and areas to improve..."
                              class="kpi-modal-textarea w-full px-3 py-2 border rounded-xl"></textarea>
                </div>

                <!-- Upload Scanned/Signed PDF (Optional) -->
                <div>
                    <label class="block font-bold mb-1 kpi-card-title">
                        Upload Signed/Scanned PDF (Optional)
                    </label>
                    <input type="file" name="kpi_pdf" accept="application/pdf"
                           class="kpi-modal-input w-full px-3 py-1.5 border rounded-xl text-xs">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t kpi-card-header">
                    <button type="button" @click="showRateModal = false" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold shadow-md">
                        Save &amp; Record KPI
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Modal: Staff KPI History ────────────────────────────────────────── --}}
    <div x-show="showHistoryModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="clay-card kpi-modal-card w-full max-w-2xl p-6" @click.away="showHistoryModal = false">
            <div class="flex items-center justify-between pb-3 border-b kpi-card-header">
                <div>
                    <h3 class="text-base font-bold kpi-card-title">Evaluation History</h3>
                    <p class="text-xs kpi-card-desc">Staff: <strong class="text-sky-400 font-bold" x-text="historyStaffName"></strong></p>
                </div>
                <button @click="showHistoryModal = false" class="text-slate-400 hover:text-slate-200 text-lg font-bold">✕</button>
            </div>

            <div class="mt-4 max-h-96 overflow-y-auto">
                <template x-if="historyRecords.length === 0">
                    <div class="py-8 text-center text-slate-400 text-xs">
                        No previous KPI evaluation records found for this staff member.
                    </div>
                </template>

                <template x-if="historyRecords.length > 0">
                    <div class="space-y-3">
                        <template x-for="rec in historyRecords" :key="rec.id">
                            <div class="kpi-modal-box p-4 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-sky-400" x-text="rec.period ? rec.period.name : 'Cycle'"></span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold kpi-badge-success" x-text="rec.performance_band"></span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold kpi-badge-blue" x-text="rec.status"></span>
                                    </div>
                                    <div class="text-[11px] kpi-stat-sub mt-1">
                                        Score: <strong class="text-white" x-text="rec.overall_kpi + '%'"></strong> &bull;
                                        Quality: <span x-text="rec.quality_score + '%'"></span> &bull;
                                        Speed: <span x-text="rec.deadline_score + '%'"></span> &bull;
                                        Output: <span x-text="rec.productivity_score + '%'"></span>
                                    </div>
                                    <div class="text-[11px] text-slate-300 mt-1 italic" x-text="rec.manager_notes"></div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <a :href="'/kpi/evaluations/' + rec.id + '/pdf'" target="_blank"
                                       class="kpi-btn-pdf px-3 py-1.5 rounded-lg text-xs font-semibold">
                                        Download PDF
                                    </a>
                                    <template x-if="rec.uploaded_pdf_path">
                                        <a :href="'/' + rec.uploaded_pdf_path" target="_blank"
                                           class="px-2.5 py-1.5 rounded-lg bg-emerald-500/20 border border-emerald-500/50 text-emerald-300 text-xs font-semibold">
                                            Signed PDF
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="flex justify-end pt-4 border-t kpi-card-header mt-4">
                <button type="button" @click="showHistoryModal = false" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold text-xs">
                    Close
                </button>
            </div>
        </div>
    </div>

    {{-- ── Modal: Upload Signed PDF ────────────────────────────────────────── --}}
    <div x-show="showUploadModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="clay-card kpi-modal-card w-full max-w-md p-6" @click.away="showUploadModal = false">
            <div class="flex items-center justify-between pb-3 border-b kpi-card-header">
                <div>
                    <h3 class="text-base font-bold kpi-card-title">Upload Signed Staff KPI</h3>
                    <p class="text-xs kpi-card-desc">Staff: <strong class="text-sky-400 font-bold" x-text="selectedUserName"></strong></p>
                </div>
                <button @click="showUploadModal = false" class="text-slate-400 hover:text-slate-200 text-lg font-bold">✕</button>
            </div>

            <form action="{{ route('kpi.evaluations.upload-direct') }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-4 text-xs">
                @csrf
                <input type="hidden" name="user_id" :value="selectedUserId">
                <input type="hidden" name="squad_id" :value="selectedSquadId">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold mb-1 kpi-card-title">Month</label>
                        <select name="month" class="kpi-modal-select w-full px-3 py-2 border rounded-xl text-xs font-semibold">
                            @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ ($selectedMonth == $m || (!$selectedMonth && $m == 9)) ? 'selected' : '' }}>
                                {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                            </option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 kpi-card-title">Year</label>
                        <select name="year" class="kpi-modal-select w-full px-3 py-2 border rounded-xl text-xs font-semibold">
                            @for($y = 2025; $y <= 2028; $y++)
                            <option value="{{ $y }}" {{ ($selectedYear == $y || (!$selectedYear && $y == 2026)) ? 'selected' : '' }}>
                                {{ $y }}
                            </option>
                            @endfor
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-bold mb-1 kpi-card-title">
                        Select PDF Document <span class="text-red-400">*</span>
                    </label>
                    <input type="file" name="kpi_pdf" required accept="application/pdf"
                           class="kpi-modal-input w-full px-3 py-2 border rounded-xl text-xs">
                    <p class="text-[10px] kpi-stat-sub mt-1">Accepts signed/scanned PDF files up to 15MB.</p>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t kpi-card-header">
                    <button type="button" @click="showUploadModal = false" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold shadow-md">
                        Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
