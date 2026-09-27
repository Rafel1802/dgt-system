@extends('layouts.app')
@section('title', 'Staff KPI Management')
@section('page_title', 'Staff KPI Dashboard')

@section('content')

@include('kpi.styles')

<div class="space-y-5 pb-12 text-slate-100" x-data="{
    showRateModal: false,
    showHistoryModal: false,
    showUploadModal: false,
    showExportModal: false,
    exportTarget: 'all',
    exportUserId: '',
    exportMonth: {{ $selectedMonth ?? date('n') }},
    exportYear: {{ $selectedYear ?? date('Y') }},
    exportDate: '{{ date('Y-m-d') }}',
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
    openRateFor(userId, userName) {
        this.selectedUserId = userId;
        this.selectedUserName = userName;
        this.showRateModal = true;
    },
    openHistory(staffName, records) {
        this.historyStaffName = staffName;
        this.historyRecords = records || [];
        this.showHistoryModal = true;
    },
    openUpload(userId, userName, reviewId) {
        this.selectedUserId = userId;
        this.selectedUserName = userName;
        this.selectedReviewId = reviewId;
        this.showUploadModal = true;
    },
    openExportModal(target, userId) {
        this.exportTarget = target || 'all';
        this.exportUserId = userId || '';
        this.showExportModal = true;
    }
}">

    {{-- ── 1. Compact Header Bar (Clean, small, no buttons inside) ──────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 rounded-2xl bg-slate-900/90 border border-slate-700/60 shadow-lg backdrop-blur-xl">
        <div class="flex items-center gap-2.5">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse shadow-sm shadow-emerald-400/50 flex-shrink-0"></span>
            <h1 class="text-sm sm:text-base font-extrabold tracking-tight text-white">Staff KPI Management System</h1>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-sky-500/10 border border-sky-500/25 text-xs">
                <span class="text-slate-400 font-medium">Active Squad:</span>
                <strong class="text-sky-300 font-bold">{{ $currentSquad?->name ?? 'Digital Media Squad 1' }}</strong>
                <span class="text-slate-400 text-[11px]">(Lead: <strong class="text-slate-200">{{ $currentSquad?->lead?->name ?? 'Mr. Dara (QC)' }}</strong>)</span>
            </div>
        </div>
    </div>

    {{-- ── 2. Action Toolbar (Dedicated action bar) ─────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2.5">
            {{-- Give Staff KPI --}}
            <button type="button" @click="showRateModal = true"
                    class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white text-xs sm:text-sm font-bold shadow-lg shadow-sky-500/20 hover:shadow-sky-500/40 flex items-center gap-2 transition-all active:scale-95 cursor-pointer">
                <svg class="w-4 h-4 text-sky-200" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                </svg>
                <span>Give Staff KPI</span>
            </button>

            {{-- Export KPI PDF Report (Opens modal popup to pick target/month/date) --}}
            <button type="button" @click="openExportModal('all', '')"
                    class="px-3.5 py-2.5 rounded-xl bg-slate-800/90 hover:bg-slate-700/90 text-sky-300 hover:text-white border border-slate-700/80 hover:border-sky-500/50 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-sm cursor-pointer">
                <svg class="w-4 h-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                <span>Export PDF Report</span>
            </button>

            {{-- Upload Signed PDF --}}
            <button type="button" @click="openUpload(null, 'Select Staff Member', null)"
                    class="px-3.5 py-2.5 rounded-xl bg-slate-800/90 hover:bg-slate-700/90 text-emerald-300 hover:text-white border border-slate-700/80 hover:border-emerald-500/50 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-sm cursor-pointer">
                <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                <span>Upload Signed PDF</span>
            </button>
        </div>

        <div class="flex items-center gap-2">
            @if($currentSquad)
            <a href="{{ route('kpi.squad.pdf', $currentSquad->id) }}" target="_blank"
               class="px-3 py-2 rounded-xl bg-slate-800/60 hover:bg-slate-700/80 text-slate-300 hover:text-white border border-slate-700/60 text-xs font-semibold flex items-center gap-1.5 transition-all">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <span>Squad Summary</span>
            </a>
            @endif

            <a href="{{ route('kpi.team.index', ['squad_id' => $currentSquad?->id]) }}"
               class="px-3.5 py-2 rounded-xl bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 hover:text-indigo-200 border border-indigo-500/30 text-xs font-semibold flex items-center gap-1.5 transition-all">
                <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                </svg>
                <span>Team Members ({{ $rawMembers->count() }})</span>
            </a>
        </div>
    </div>

    {{-- ── 3. Filters & Squad Tabs ───────────────────────────────────────────── --}}
    <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-700/60 shadow-md backdrop-blur-xl">
        <form method="GET" action="{{ url()->current() }}" class="flex flex-col gap-3">
            @if($isSupervisor && $squads->count() > 1)
            {{-- Squad Selector Pills for Supervisor --}}
            <div class="flex items-center gap-2 pb-3 border-b border-slate-800">
                <span class="text-xs font-semibold text-slate-400 mr-2">Select Squad:</span>
                @foreach($squads as $sq)
                <a href="{{ url()->current() . '?' . http_build_query(array_merge(request()->except('squad_id'), ['squad_id' => $sq->id])) }}"
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all {{ ($selectedSquadId == $sq->id) ? 'bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-md shadow-sky-500/20' : 'bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700/60' }}">
                    {{ $sq->name }} <span class="text-[11px] opacity-75 font-normal">({{ $sq->lead?->name }})</span>
                </a>
                @endforeach
            </div>
            @endif

            <input type="hidden" name="squad_id" value="{{ $selectedSquadId }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                {{-- Search --}}
                <div class="md:col-span-2 relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </span>
                    <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Search staff name, role..."
                           class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-800/90 border border-slate-700/80 text-white text-xs placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>

                {{-- Member Filter --}}
                <div>
                    <select name="member_id" class="w-full px-3 py-2 rounded-xl bg-slate-800/90 border border-slate-700/80 text-white text-xs focus:outline-none focus:border-sky-500">
                        <option value="">All Staff Members</option>
                        @foreach($rawMembers as $m)
                        <option value="{{ $m->id }}" {{ ($memberId == $m->id) ? 'selected' : '' }}>{{ $m->name }} ({{ $m->pivot->role_title ?? 'Staff' }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Month Filter --}}
                <div>
                    <select name="month" class="w-full px-3 py-2 rounded-xl bg-slate-800/90 border border-slate-700/80 text-white text-xs focus:outline-none focus:border-sky-500">
                        <option value="">All Months</option>
                        @php
                            $months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
                        @endphp
                        @foreach($months as $num => $mName)
                        <option value="{{ $num }}" {{ ($selectedMonth == $num) ? 'selected' : '' }}>{{ $mName }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Year Filter & Action --}}
                <div class="flex items-center gap-2">
                    <select name="year" class="w-full px-3 py-2 rounded-xl bg-slate-800/90 border border-slate-700/80 text-white text-xs focus:outline-none focus:border-sky-500">
                        @for($y = 2025; $y <= 2028; $y++)
                        <option value="{{ $y }}" {{ (($selectedYear ?? date('Y')) == $y) ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>

                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold transition-all shadow-sm cursor-pointer">
                        Filter
                    </button>
                    @if(!empty($search) || !empty($memberId) || !empty($selectedMonth))
                    <a href="{{ url()->current() . '?squad_id=' . $selectedSquadId }}" class="px-2.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white text-xs" title="Clear Filters">✕</a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- ── 4. Key Metrics Cards ────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Team Size -->
        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-700/60 shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Squad Team Size</span>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-2xl font-black text-white">{{ $rawMembers->count() }}</span>
                        <span class="text-xs text-slate-400">active staff</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-500/20 border border-blue-500/30 flex items-center justify-center text-blue-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-400">Lead: <strong class="text-sky-300">{{ $currentSquad?->lead?->name ?? 'N/A' }}</strong></span>
                <a href="{{ route('kpi.team.index') }}" class="text-sky-400 hover:text-sky-300 font-semibold">+ Manage</a>
            </div>
        </div>

        <!-- Monthly Evaluations -->
        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-700/60 shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Monthly Evaluations</span>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-2xl font-black text-white">{{ $evaluatedCount }}</span>
                        <span class="text-xs text-slate-400">/ {{ $rawMembers->count() }} rated</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-purple-500/20 border border-purple-500/30 flex items-center justify-center text-purple-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-800">
                @php $evalPct = $rawMembers->count() > 0 ? round(($evaluatedCount / $rawMembers->count()) * 100) : 0; @endphp
                <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-purple-500 h-1.5 rounded-full" style="width: {{ $evalPct }}%"></div>
                </div>
                <div class="flex justify-between text-[11px] text-slate-400 mt-1">
                    <span>Progress</span>
                    <span class="font-bold text-slate-300">{{ $evalPct }}%</span>
                </div>
            </div>
        </div>

        <!-- Average Score -->
        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-700/60 shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Avg Squad KPI</span>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-2xl font-black text-white">{{ $avgKpiScore }}%</span>
                        <span class="text-xs text-emerald-400 font-semibold">Exceeds</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-400">Target Benchmark</span>
                <span class="font-bold text-emerald-400">90.0% Minimum</span>
            </div>
        </div>

        <!-- Top Performers -->
        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-700/60 shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Top Performers</span>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-2xl font-black text-white">{{ $outstandingCount }}</span>
                        <span class="text-xs text-amber-400 font-semibold">Outstanding</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-amber-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-400">Score &ge; 90%</span>
                <span class="font-bold text-amber-400">High Tier</span>
            </div>
        </div>
    </div>

    {{-- ── 5. Staff KPI Evaluations Table ────────────────────────────────────── --}}
    <div class="rounded-2xl bg-slate-900/80 border border-slate-700/60 shadow-lg overflow-hidden backdrop-blur-xl">
        <div class="p-4 sm:p-5 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>Staff Evaluation Records</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-500/20 text-sky-300 border border-sky-500/30">
                        {{ $staffMembers->count() }} Staff
                    </span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">
                    Monthly KPI reviews for {{ $currentPeriod?->name ?? 'Current Period' }}. Rate scores, inspect history, or export PDF certificates.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="openExportModal('all', '')"
                        class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-sky-300 text-xs font-semibold flex items-center gap-1.5 transition-all cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Export Squad PDF</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-200">
                <thead class="bg-slate-950/70 text-slate-400 uppercase tracking-wider text-[11px] border-b border-slate-800 font-bold">
                    <tr>
                        <th class="py-3 px-4">Staff Member</th>
                        <th class="py-3 px-4">Role in Squad</th>
                        <th class="py-3 px-4 text-center">Score (KPI)</th>
                        <th class="py-3 px-4 text-center">Rating</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4">Evaluator & Notes</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/80">
                    @forelse($staffMembers as $staff)
                    @php
                        $rev = $reviews->get($staff->id);
                        $history = $allUserReviews->get($staff->id, collect());
                    @endphp
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        {{-- Staff Name & REAL AVATAR PROFILE PHOTO --}}
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-3">
                                <img src="{{ $staff->avatar_url }}" alt="{{ $staff->name }}"
                                     class="w-10 h-10 rounded-xl object-cover border border-slate-700 shadow-md flex-shrink-0"
                                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode($staff->name) }}&background=0284c7&color=fff';">
                                <div>
                                    <div class="font-bold text-white text-sm hover:text-sky-300 transition-colors">
                                        {{ $staff->name }}
                                    </div>
                                    <div class="text-[11px] text-slate-400">
                                        {{ $staff->email ?? '@' . $staff->username }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Role Title --}}
                        <td class="py-3.5 px-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 font-medium text-xs border border-slate-700/60">
                                {{ $staff->pivot->role_title ?? 'Digital Specialist' }}
                            </span>
                        </td>

                        {{-- Score --}}
                        <td class="py-3.5 px-4 text-center">
                            @if($rev && $rev->overall_kpi)
                                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-slate-800/90 border border-slate-700 font-black text-sm text-white">
                                    <span>{{ $rev->overall_kpi }}%</span>
                                </div>
                            @else
                                <span class="text-slate-500 font-semibold italic text-xs">Not Rated</span>
                            @endif
                        </td>

                        {{-- Rating / Performance Band --}}
                        <td class="py-3.5 px-4 text-center">
                            @if($rev && $rev->performance_band)
                                @php
                                    $bandClass = match($rev->performance_band) {
                                        'Outstanding' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40',
                                        'Exceeds Expectations' => 'bg-sky-500/20 text-sky-300 border-sky-500/40',
                                        'Meets Expectations' => 'bg-blue-500/20 text-blue-300 border-blue-500/40',
                                        default => 'bg-amber-500/20 text-amber-300 border-amber-500/40'
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold border {{ $bandClass }}">
                                    ★ {{ $rev->performance_band }}
                                </span>
                            @else
                                <span class="text-slate-500 text-xs">—</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="py-3.5 px-4 text-center">
                            @if($rev)
                                @php
                                    $statusClass = match($rev->status) {
                                        'Finalized' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                                        'Approved' => 'bg-sky-500/20 text-sky-300 border-sky-500/30',
                                        'Submitted' => 'bg-purple-500/20 text-purple-300 border-purple-500/30',
                                        default => 'bg-slate-800 text-slate-400 border-slate-700'
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $statusClass }}">
                                    {{ $rev->status }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-800 text-slate-400 border border-slate-700">
                                    Pending
                                </span>
                            @endif
                        </td>

                        {{-- Evaluator & Notes --}}
                        <td class="py-3.5 px-4 max-w-xs">
                            @if($rev)
                                <div class="text-xs text-slate-300 truncate" title="{{ $rev->review_notes ?? 'No notes recorded.' }}">
                                    "{{ $rev->review_notes ?? 'No notes recorded.' }}"
                                </div>
                                <div class="text-[11px] text-slate-400 mt-0.5">
                                    By: {{ $rev->reviewer?->name ?? 'Lead' }} &bull; {{ $rev->evaluation_date ? date('d M Y', strtotime($rev->evaluation_date)) : $rev->created_at->format('d M Y') }}
                                </div>
                            @else
                                <span class="text-slate-500 text-xs italic">Awaiting monthly score</span>
                            @endif
                        </td>

                        {{-- Action Buttons (Rate, History, PDF Modal, Upload) --}}
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                {{-- Rate Button --}}
                                <button type="button" @click="openRateFor({{ $staff->id }}, '{{ addslashes($staff->name) }}')"
                                        class="px-2.5 py-1.5 rounded-lg bg-sky-500/15 hover:bg-sky-500/25 border border-sky-500/30 text-sky-300 hover:text-white text-xs font-semibold transition-all cursor-pointer">
                                    {{ $rev ? 'Update' : 'Rate' }}
                                </button>

                                {{-- History Button --}}
                                <button type="button" @click="openHistory('{{ addslashes($staff->name) }}', {{ $history->toJson() }})"
                                        class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 hover:text-white text-xs font-semibold transition-all cursor-pointer">
                                    History ({{ $history->count() }})
                                </button>

                                {{-- Single Staff Export Modal Trigger --}}
                                <button type="button" @click="openExportModal('single', '{{ $staff->id }}')"
                                        class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-sky-400 hover:text-white transition-all cursor-pointer"
                                        title="Export Official KPI PDF for {{ $staff->name }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                </button>

                                {{-- Upload Signed PDF --}}
                                <button type="button" @click="openUpload({{ $staff->id }}, '{{ addslashes($staff->name) }}', {{ $rev?->id ?? 'null' }})"
                                        class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-emerald-400 hover:text-white transition-all cursor-pointer"
                                        title="Upload Signed Scanned PDF">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400 text-xs">
                            No team members found matching your search and filter criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── 6. Modal: Export KPI PDF Report (Select Month, Date, All/Each Staff) ── --}}
    <div x-show="showExportModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-[#0f172a] text-slate-100 rounded-3xl border border-sky-500/30 shadow-[0_25px_60px_rgba(0,0,0,0.9),0_0_35px_rgba(14,165,233,0.2)] overflow-hidden"
             @click.away="showExportModal = false">
            
            <div class="p-5 border-b border-slate-800 bg-slate-900/80 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-sky-500/20 to-blue-600/30 border border-sky-400/40 flex items-center justify-center text-sky-400 shadow-sm flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-white">Export KPI PDF Report</h3>
                        <p class="text-xs text-slate-400">Generate certificates matching examplekpi.pdf</p>
                    </div>
                </div>
                <button type="button" @click="showExportModal = false" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700/80 flex items-center justify-center transition-all cursor-pointer">✕</button>
            </div>

            <form action="{{ route('kpi.evaluations.export-all-pdf') }}" method="GET" target="_blank" class="p-5 space-y-4 text-xs" @submit="setTimeout(() => showExportModal = false, 1000)">
                <input type="hidden" name="squad_id" value="{{ $selectedSquadId }}">

                {{-- Target: All Users vs Each Specific User --}}
                <div>
                    <label class="block font-bold mb-2 text-slate-300">Export Scope</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="exportTarget === 'all' ? 'bg-sky-500/20 border-sky-500/50 text-white font-bold' : 'bg-slate-900 border-slate-700 text-slate-400'">
                            <input type="radio" name="export_scope" value="all" x-model="exportTarget" @change="exportUserId = ''" class="accent-sky-500">
                            <span>All Staff (All-in-One)</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="exportTarget === 'single' ? 'bg-sky-500/20 border-sky-500/50 text-white font-bold' : 'bg-slate-900 border-slate-700 text-slate-400'">
                            <input type="radio" name="export_scope" value="single" x-model="exportTarget" class="accent-sky-500">
                            <span>Specific Staff</span>
                        </label>
                    </div>
                </div>

                {{-- If single user is chosen --}}
                <div x-show="exportTarget === 'single'">
                    <label class="block font-bold mb-1 text-slate-300">Select Staff Member</label>
                    <select name="user_id" x-model="exportUserId" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-semibold focus:outline-none focus:border-sky-500">
                        <option value="">-- Choose Member --</option>
                        @foreach($rawMembers as $m)
                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->pivot->role_title ?? 'Staff' }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Month & Year Selection --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold mb-1 text-slate-300">Select Month</label>
                        <select name="month" x-model="exportMonth" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-semibold focus:outline-none focus:border-sky-500">
                            @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-slate-300">Select Year</label>
                        <select name="year" x-model="exportYear" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-semibold focus:outline-none focus:border-sky-500">
                            @for($y = 2025; $y <= 2028; $y++)
                            <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                </div>

                {{-- Report / Evaluation Date --}}
                <div>
                    <label class="block font-bold mb-1 text-slate-300">Official Report Date (Printed on Certificate)</label>
                    <input type="date" name="evaluation_date" x-model="exportDate"
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white text-xs font-semibold focus:outline-none focus:border-sky-500">
                </div>

                <div class="p-3 rounded-xl bg-sky-500/10 border border-sky-500/20 text-[11px] text-slate-300 flex items-start gap-2">
                    <svg class="w-4 h-4 text-sky-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                    </svg>
                    <span>Generates PDF formatted exactly according to the company 5-star criteria template with official signatures (CEO, HR/Admin, Supervisor, and Team Lead).</span>
                </div>

                <div class="flex justify-end gap-2.5 pt-3 border-t border-slate-800">
                    <button type="button" @click="showExportModal = false" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white font-bold shadow-lg shadow-sky-500/30 cursor-pointer">
                        Generate & Download PDF
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── 7. Modal: Evaluation History (Dark, sleek, glassmorphic) ───────────── --}}
    <div x-show="showHistoryModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
        <div class="w-full max-w-2xl bg-[#0f172a] text-slate-100 rounded-3xl border border-sky-500/30 shadow-[0_25px_60px_rgba(0,0,0,0.9),0_0_35px_rgba(14,165,233,0.2)] overflow-hidden"
             @click.away="showHistoryModal = false">
            
            <div class="p-5 border-b border-slate-800 bg-slate-900/80 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-sky-500/20 to-blue-600/30 border border-sky-400/40 flex items-center justify-center text-sky-400 shadow-sm flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-white tracking-wide">Evaluation History</h3>
                        <p class="text-xs text-slate-400">Staff Member: <strong class="text-sky-300 font-bold" x-text="historyStaffName"></strong></p>
                    </div>
                </div>
                <button type="button" @click="showHistoryModal = false"
                        class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700/80 flex items-center justify-center transition-all cursor-pointer">
                    ✕
                </button>
            </div>

            <div class="p-5 max-h-[28rem] overflow-y-auto space-y-3.5">
                <template x-if="historyRecords.length === 0">
                    <div class="py-12 text-center text-slate-400">
                        <div class="w-12 h-12 rounded-2xl bg-slate-800/80 border border-slate-700/80 mx-auto flex items-center justify-center text-slate-500 mb-3">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                        <p class="text-xs font-medium">No previous KPI evaluation records found for this staff member.</p>
                    </div>
                </template>

                <template x-if="historyRecords.length > 0">
                    <div class="space-y-3">
                        <template x-for="rec in historyRecords" :key="rec.id">
                            <div class="p-4 rounded-2xl bg-slate-900/90 border border-slate-700/80 hover:border-sky-500/50 transition-all shadow-md">
                                <div class="flex flex-wrap items-center justify-between gap-2 pb-2.5 border-b border-slate-800">
                                    <div class="flex items-center gap-2">
                                        <span class="px-3 py-1 rounded-xl text-xs font-extrabold bg-gradient-to-r from-blue-600/40 to-sky-600/40 border border-sky-400/40 text-sky-200"
                                              x-text="rec.period ? rec.period.name : 'Evaluation Cycle'"></span>
                                        <span class="px-2.5 py-0.5 rounded-lg text-[11px] font-bold bg-emerald-500/20 border border-emerald-500/40 text-emerald-300"
                                              x-text="'★ ' + (rec.performance_band || 'Standard')"></span>
                                        <span class="px-2 py-0.5 rounded-lg text-[10px] font-semibold bg-sky-500/15 border border-sky-500/30 text-sky-300"
                                              x-text="rec.status"></span>
                                    </div>
                                    <div class="text-[11px] text-slate-400">
                                        <span x-text="'Evaluated on: ' + (rec.evaluation_date || (rec.created_at ? rec.created_at.substring(0, 10) : 'N/A'))"></span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 py-3">
                                    <div class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 text-center">
                                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Overall KPI</span>
                                        <span class="text-lg font-black text-sky-300" x-text="rec.overall_kpi + '%'"></span>
                                    </div>
                                    <div class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 text-center">
                                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Quality</span>
                                        <span class="text-sm font-bold text-emerald-300" x-text="(rec.quality_score || rec.score || 90) + '%'"></span>
                                    </div>
                                    <div class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 text-center">
                                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Speed</span>
                                        <span class="text-sm font-bold text-amber-300" x-text="(rec.deadline_score || 90) + '%'"></span>
                                    </div>
                                    <div class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 text-center">
                                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Output</span>
                                        <span class="text-sm font-bold text-purple-300" x-text="(rec.productivity_score || 90) + '%'"></span>
                                    </div>
                                </div>

                                <template x-if="rec.manager_notes || rec.review_notes">
                                    <div class="bg-slate-950/80 border-l-2 border-sky-400 rounded-r-xl p-3 text-xs text-slate-300 italic mb-3">
                                        <p x-text="'"' + (rec.manager_notes || rec.review_notes) + '"'"></p>
                                        <div class="text-[10px] text-slate-500 not-italic mt-1" x-text="'Reviewer: ' + (rec.reviewer ? rec.reviewer.name : 'Team Lead')"></div>
                                    </div>
                                </template>

                                <div class="flex items-center justify-between pt-2 border-t border-slate-800/80">
                                    <span class="text-[11px] text-slate-500">Official KPI Documentation</span>
                                    <div class="flex items-center gap-2">
                                        <a :href="'/kpi/evaluations/' + rec.id + '/pdf'" target="_blank"
                                           class="px-3 py-1.5 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white text-xs font-semibold flex items-center gap-1.5 shadow-sm transition-all">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                            </svg>
                                            <span>Download PDF</span>
                                        </a>

                                        <template x-if="rec.uploaded_pdf_path">
                                            <a :href="'/' + rec.uploaded_pdf_path" target="_blank"
                                               class="px-3 py-1.5 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-300 text-xs font-semibold flex items-center gap-1.5 transition-all">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                                <span>Signed PDF</span>
                                            </a>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="px-5 py-3.5 bg-slate-900/80 border-t border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-400"><strong class="text-sky-400 font-bold" x-text="historyRecords.length"></strong> record(s) on file</span>
                <button type="button" @click="showHistoryModal = false"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold border border-slate-700 transition-all cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>

    {{-- ── 8. Modal: Rate Staff KPI (Dark Glassmorphic) ───────────────────────── --}}
    <div x-show="showRateModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
        <div class="w-full max-w-lg bg-[#0f172a] text-slate-100 rounded-3xl border border-sky-500/30 shadow-[0_25px_60px_rgba(0,0,0,0.9),0_0_35px_rgba(14,165,233,0.2)] overflow-hidden"
             @click.away="showRateModal = false">
            
            <div class="p-5 border-b border-slate-800 bg-slate-900/80 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-sky-500/20 to-blue-600/30 border border-sky-400/40 flex items-center justify-center text-sky-400 shadow-sm flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-white">Monthly Staff KPI Rating</h3>
                        <p class="text-xs text-slate-400">Squad: <span class="text-sky-300 font-semibold">{{ $currentSquad?->name }}</span></p>
                    </div>
                </div>
                <button type="button" @click="showRateModal = false" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700/80 flex items-center justify-center transition-all cursor-pointer">✕</button>
            </div>

            <form action="{{ route('kpi.evaluations.rate') }}" method="POST" class="p-5 space-y-4 text-xs">
                @csrf
                <input type="hidden" name="squad_id" :value="selectedSquadId">

                {{-- Select Staff Member --}}
                <div>
                    <label class="block font-bold mb-1 text-slate-300">Select Staff Member <span class="text-rose-400">*</span></label>
                    <select name="user_id" x-model="selectedUserId" required class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-semibold focus:outline-none focus:border-sky-500">
                        <option value="">-- Choose Member from Squad --</option>
                        @foreach($rawMembers as $m)
                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->pivot->role_title ?? 'Staff' }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Month, Year & Date Picker --}}
                <div class="grid grid-cols-3 gap-2.5">
                    <div>
                        <label class="block font-bold mb-1 text-slate-300">Month</label>
                        <select name="month" x-model="reviewMonth" class="w-full px-2.5 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-semibold focus:outline-none focus:border-sky-500">
                            @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-slate-300">Year</label>
                        <select name="year" x-model="reviewYear" class="w-full px-2.5 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-semibold focus:outline-none focus:border-sky-500">
                            @for($y = 2025; $y <= 2028; $y++)
                            <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-slate-300">Date Rated</label>
                        <input type="date" name="evaluation_date" x-model="evaluationDate"
                               class="w-full px-2.5 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white text-xs font-semibold focus:outline-none focus:border-sky-500">
                    </div>
                </div>

                {{-- 4 Core KPI Criteria --}}
                <div class="space-y-3 pt-1">
                    <div>
                        <div class="flex justify-between font-bold mb-1">
                            <span class="text-slate-300">1. Quality & Accuracy (35%)</span>
                            <span class="text-sky-400 font-bold" x-text="scoreQual + '%'"></span>
                        </div>
                        <input type="range" min="50" max="100" step="1" x-model="scoreQual" name="quality_score" class="w-full accent-sky-500 cursor-pointer">
                    </div>
                    <div>
                        <div class="flex justify-between font-bold mb-1">
                            <span class="text-slate-300">2. Productivity & Output (35%)</span>
                            <span class="text-sky-400 font-bold" x-text="scoreProd + '%'"></span>
                        </div>
                        <input type="range" min="50" max="100" step="1" x-model="scoreProd" name="productivity_score" class="w-full accent-sky-500 cursor-pointer">
                    </div>
                    <div>
                        <div class="flex justify-between font-bold mb-1">
                            <span class="text-slate-300">3. Timeliness & TAT (20%)</span>
                            <span class="text-sky-400 font-bold" x-text="scoreTat + '%'"></span>
                        </div>
                        <input type="range" min="50" max="100" step="1" x-model="scoreTat" name="deadline_score" class="w-full accent-sky-500 cursor-pointer">
                    </div>
                    <div>
                        <div class="flex justify-between font-bold mb-1">
                            <span class="text-slate-300">4. Collaboration & Discipline (10%)</span>
                            <span class="text-sky-400 font-bold" x-text="scoreTeam + '%'"></span>
                        </div>
                        <input type="range" min="50" max="100" step="1" x-model="scoreTeam" name="collaboration_score" class="w-full accent-sky-500 cursor-pointer">
                    </div>
                </div>

                {{-- Live Calculated Score Banner --}}
                <div class="p-3.5 rounded-2xl bg-gradient-to-r from-blue-950/60 to-slate-900 border border-sky-500/40 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Calculated Monthly Score</span>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-black text-white" x-text="overallScore + '%'"></span>
                            <span class="text-xs font-bold text-sky-400" x-text="rankBand"></span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[11px] text-slate-400 block">5-Star Scale</span>
                        <span class="text-sm font-black text-amber-400" x-text="(overallScore / 20).toFixed(1) + ' ★ / 5.0'"></span>
                    </div>
                </div>

                {{-- Notes / Justification --}}
                <div>
                    <label class="block font-bold mb-1 text-slate-300">Reviewer Notes & Feedback</label>
                    <textarea name="notes" rows="2" placeholder="Write feedback, accomplishments, or areas for improvement..."
                              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-sky-500"></textarea>
                </div>

                <div class="flex justify-end gap-2.5 pt-3 border-t border-slate-800">
                    <button type="button" @click="showRateModal = false" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white font-bold shadow-lg shadow-sky-500/30 cursor-pointer">
                        Save & Issue KPI
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── 9. Modal: Upload Signed PDF (Dark Glassmorphic) ─────────────────────── --}}
    <div x-show="showUploadModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-[#0f172a] text-slate-100 rounded-3xl border border-emerald-500/30 shadow-[0_25px_60px_rgba(0,0,0,0.9),0_0_35px_rgba(16,185,129,0.2)] overflow-hidden"
             @click.away="showUploadModal = false">
            
            <div class="p-5 border-b border-slate-800 bg-slate-900/80 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-500/20 to-teal-600/30 border border-emerald-400/40 flex items-center justify-center text-emerald-400 shadow-sm flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-white">Upload Signed KPI Document</h3>
                        <p class="text-xs text-slate-400">Target: <strong class="text-emerald-400 font-bold" x-text="selectedUserName"></strong></p>
                    </div>
                </div>
                <button type="button" @click="showUploadModal = false" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700/80 flex items-center justify-center transition-all cursor-pointer">✕</button>
            </div>

            <form action="{{ route('kpi.evaluations.upload-direct') }}" method="POST" enctype="multipart/form-data" class="p-5 space-y-4 text-xs">
                @csrf
                <input type="hidden" name="user_id" :value="selectedUserId">
                <input type="hidden" name="squad_id" :value="selectedSquadId">

                {{-- If staff not selected yet --}}
                <div x-show="!selectedUserId">
                    <label class="block font-bold mb-1 text-slate-300">Select Staff Member</label>
                    <select name="user_id_select" @change="selectedUserId = $event.target.value" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-semibold">
                        <option value="">-- Choose Member --</option>
                        @foreach($rawMembers as $m)
                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->pivot->role_title ?? 'Staff' }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold mb-1 text-slate-300">Month</label>
                        <select name="month" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-semibold">
                            @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ (($selectedMonth ?? date('n')) == $m) ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-slate-300">Year</label>
                        <select name="year" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-semibold">
                            @for($y = 2025; $y <= 2028; $y++)
                            <option value="{{ $y }}" {{ (($selectedYear ?? date('Y')) == $y) ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-bold mb-1 text-slate-300">Evaluation Date</label>
                    <input type="date" name="evaluation_date" value="{{ date('Y-m-d') }}"
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white text-xs font-semibold">
                </div>

                <div>
                    <label class="block font-bold mb-1 text-slate-300">Select Scanned KPI PDF <span class="text-rose-400">*</span></label>
                    <input type="file" name="pdf_file" accept=".pdf" required
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-slate-300 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-600 file:text-white hover:file:bg-emerald-500">
                    <p class="text-[10px] text-slate-400 mt-1">Upload signed or scanned official PDF document (PDF, Max 15MB).</p>
                </div>

                <div class="flex justify-end gap-2.5 pt-3 border-t border-slate-800">
                    <button type="button" @click="showUploadModal = false" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-bold shadow-lg shadow-emerald-500/30 cursor-pointer">
                        Upload & Attach
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

@endsection
