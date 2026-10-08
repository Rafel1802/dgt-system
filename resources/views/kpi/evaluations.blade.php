@extends('layouts.app')
@section('title', 'Staff KPI Evaluations')
@section('page_title', 'Monthly KPI Evaluations')

@section('content')

@include('kpi.styles')

<script>
    window.__KPI_HISTORY = {!! json_encode($allUserReviews) !!};
    window.__KPI_MEMBERS = {!! json_encode($rawMembers->map(fn($m) => [
        'id' => $m->id,
        'name' => $m->name,
        'username' => $m->username ?? '',
        'email' => $m->email,
        'avatar_url' => $m->avatar_url,
        'role_title' => $m->pivot->role_title ?? 'Staff Member',
    ])) !!};
</script>

<div class="space-y-5 pb-12 text-slate-800 dark:text-slate-100" x-data="{
    showRateModal: false,
    showHistoryModal: false,
    showUploadModal: false,
    showExportModal: false,
    showDriveModal: false,
    copyScriptSuccess: false,
    allHistoryByUser: (typeof window !== 'undefined' && window.__KPI_HISTORY) ? window.__KPI_HISTORY : {},
    squadMembersList: (typeof window !== 'undefined' && window.__KPI_MEMBERS) ? window.__KPI_MEMBERS : [],
    rateMemberDropdownOpen: false,
    rateMemberSearch: '',
    filteredRateMembers() {
        if (!this.rateMemberSearch) return this.squadMembersList;
        let q = this.rateMemberSearch.toLowerCase();
        return this.squadMembersList.filter(m =>
            (m.name && m.name.toLowerCase().includes(q)) ||
            (m.username && m.username.toLowerCase().includes(q)) ||
            (m.role_title && m.role_title.toLowerCase().includes(q))
        );
    },
    getSelectedRateMember() {
        return this.squadMembersList.find(m => m.id == this.selectedUserId) || null;
    },
    selectRateMember(m) {
        this.selectedUserId = m.id;
        this.selectedStaffName = m.name;
        this.selectedUserName = m.name;
        this.rateMemberDropdownOpen = false;
    },
    exportTarget: 'all',
    exportUserId: '',
    exportMemberDropdownOpen: false,
    exportMemberSearch: '',
    filteredExportMembers() {
        if (!this.exportMemberSearch) return this.squadMembersList;
        let q = this.exportMemberSearch.toLowerCase();
        return this.squadMembersList.filter(m =>
            (m.name && m.name.toLowerCase().includes(q)) ||
            (m.username && m.username.toLowerCase().includes(q)) ||
            (m.role_title && m.role_title.toLowerCase().includes(q))
        );
    },
    getSelectedExportMember() {
        return this.squadMembersList.find(m => m.id == this.exportUserId) || null;
    },
    selectExportMember(m) {
        this.exportUserId = m.id;
        this.exportMemberDropdownOpen = false;
    },
    exportMonth: {{ $selectedMonth ?? date('n') }},
    exportYear: {{ $selectedYear ?? date('Y') }},
    exportDate: '{{ date('Y-m-d') }}',
    selectedUserId: null,
    selectedUserName: '',
    selectedStaffName: '',
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
    formatKpiDate(val) {
        if (!val) return 'N/A';
        try {
            let str = String(val);
            let datePart = str.split('T')[0];
            let p = datePart.split('-');
            if (p.length === 3) {
                let y = parseInt(p[0], 10);
                let m = parseInt(p[1], 10) - 1;
                let d = parseInt(p[2], 10);
                let months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                return ('0' + d).slice(-2) + ' ' + months[m] + ' ' + y;
            }
            let d = new Date(val);
            if (!isNaN(d.getTime())) {
                let months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                return ('0' + d.getDate()).slice(-2) + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
            }
        } catch(e) {}
        return String(val).substring(0, 10);
    },
    openNewRateModal() {
        this.selectedUserId = null;
        this.selectedUserName = '';
        this.selectedStaffName = '';
        this.selectedReviewId = null;
        this.scoreProd = 95;
        this.scoreQual = 95;
        this.scoreTat = 92;
        this.scoreTeam = 95;
        this.reviewNotes = '';
        this.reviewStatus = 'Approved';
        this.reviewMonth = {{ $selectedMonth ?? date('n') }};
        this.reviewYear = {{ $selectedYear ?? date('Y') }};
        this.evaluationDate = '{{ date('Y-m-d') }}';
        this.rateMemberDropdownOpen = false;
        this.showRateModal = true;
    },
    openRateFor(userId, userName, existingReview) {
        this.selectedUserId = userId;
        this.selectedUserName = userName;
        this.selectedStaffName = userName;
        this.rateMemberDropdownOpen = false;
        if (existingReview) {
            this.selectedReviewId = existingReview.id;
            this.scoreQual = parseFloat(existingReview.quality_score || existingReview.score || 95);
            this.scoreProd = parseFloat(existingReview.productivity_score || 95);
            this.scoreTat = parseFloat(existingReview.deadline_score || 92);
            this.scoreTeam = parseFloat(existingReview.teamwork_score || 95);
            this.reviewStatus = existingReview.status || 'Approved';
            this.reviewNotes = existingReview.manager_notes || existingReview.review_notes || '';
            if (existingReview.evaluation_date) {
                this.evaluationDate = String(existingReview.evaluation_date).substring(0, 10);
            }
            if (existingReview.period && existingReview.period.name) {
                let match = existingReview.period.name.match(/\b(20\d\d)\b/);
                if (match) this.reviewYear = parseInt(match[1]);
                let monthMatch = existingReview.period.name.match(/^(January|February|March|April|May|June|July|August|September|October|November|December)/i);
                if (monthMatch) {
                    let monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                    let mIdx = monthNames.findIndex(m => m.toLowerCase() === monthMatch[1].toLowerCase());
                    if (mIdx !== -1) this.reviewMonth = mIdx + 1;
                }
            }
        } else {
            this.selectedReviewId = null;
            this.scoreProd = 95;
            this.scoreQual = 95;
            this.scoreTat = 92;
            this.scoreTeam = 95;
            this.reviewNotes = '';
            this.reviewStatus = 'Approved';
            this.reviewMonth = {{ $selectedMonth ?? date('n') }};
            this.reviewYear = {{ $selectedYear ?? date('Y') }};
            this.evaluationDate = '{{ date('Y-m-d') }}';
        }
        this.showRateModal = true;
    },
    openHistory(staffName, userIdOrRecords) {
        this.historyStaffName = staffName;
        if (Array.isArray(userIdOrRecords)) {
            this.historyRecords = userIdOrRecords;
        } else if (this.allHistoryByUser && this.allHistoryByUser[userIdOrRecords]) {
            this.historyRecords = this.allHistoryByUser[userIdOrRecords];
        } else {
            this.historyRecords = [];
        }
        this.showHistoryModal = true;
    },
    editHistoryRecord(rec, staffName) {
        this.selectedUserId = rec.user_id;
        this.selectedUserName = staffName;
        this.selectedStaffName = staffName;
        this.selectedReviewId = rec.id;
        this.scoreQual = parseFloat(rec.quality_score || rec.score || 95);
        this.scoreProd = parseFloat(rec.productivity_score || 95);
        this.scoreTat = parseFloat(rec.deadline_score || 92);
        this.scoreTeam = parseFloat(rec.teamwork_score || 95);
        this.reviewStatus = rec.status || 'Approved';
        this.reviewNotes = rec.manager_notes || rec.review_notes || '';
        if (rec.evaluation_date) {
            this.evaluationDate = String(rec.evaluation_date).substring(0, 10);
        }
        if (rec.period && rec.period.name) {
            let match = rec.period.name.match(/\b(20\d\d)\b/);
            if (match) this.reviewYear = parseInt(match[1]);
            let monthMatch = rec.period.name.match(/^(January|February|March|April|May|June|July|August|September|October|November|December)/i);
            if (monthMatch) {
                let monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                let mIdx = monthNames.findIndex(m => m.toLowerCase() === monthMatch[1].toLowerCase());
                if (mIdx !== -1) this.reviewMonth = mIdx + 1;
            }
        } else if (rec.created_at) {
            let d = new Date(rec.created_at);
            if (!isNaN(d.getTime())) {
                this.reviewMonth = d.getMonth() + 1;
                this.reviewYear = d.getFullYear();
            }
        }
        this.showHistoryModal = false;
        this.showRateModal = true;
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
        this.exportMemberDropdownOpen = false;
        this.exportMemberSearch = '';
        this.showExportModal = true;
    },
    copyAppsScriptCode() {
        let code = document.getElementById('kpi-apps-script-raw-code')?.innerText || '';
        if (navigator.clipboard && code) {
            navigator.clipboard.writeText(code).then(() => {
                this.copyScriptSuccess = true;
                setTimeout(() => this.copyScriptSuccess = false, 3000);
            });
        }
    }
}">

    {{-- ── 1. Compact Header Bar (Responsive & Theme Adaptive with Google Drive Link) ────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-700/60 shadow-xs dark:shadow-lg backdrop-blur-xl transition-colors">
        <div class="flex items-center gap-3">
            <span class="w-3 h-3 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse shadow-md shadow-emerald-500/50 flex-shrink-0"></span>
            <div>
                <h1 class="text-base sm:text-xl font-black tracking-tight text-slate-900 dark:text-white">Staff KPI Management System</h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">Performance tracking, automatic PDF generation & Google Drive integration</p>
            </div>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/25 text-xs">
                <span class="text-slate-500 dark:text-slate-400 font-medium">Active Squad:</span>
                <strong class="text-sky-600 dark:text-sky-300 font-bold text-xs">{{ $currentSquad?->name ?? 'Digital Media Production Team A' }}</strong>
                <span class="text-slate-500 dark:text-slate-400 text-xs">(Lead: <strong class="text-slate-700 dark:text-slate-200">{{ $currentSquad?->lead?->name ?? 'Mr. Dara (QC)' }}</strong>)</span>
            </div>

            <a href="https://drive.google.com/drive/folders/1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi" target="_blank"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:hover:bg-emerald-500/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 font-bold text-xs sm:text-sm transition-all shadow-xs"
               title="Open Google Drive KPI Storage Folder">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM19 18H6c-2.21 0-4-1.79-4-4 0-2.05 1.53-3.76 3.56-3.97l1.07-.11.5-.95C8.08 7.14 9.94 6 12 6c2.62 0 4.88 1.86 5.39 4.43l.3 1.5 1.53.11c1.56.1 2.78 1.41 2.78 2.96 0 1.65-1.35 3-3 3z"/>
                </svg>
                <span>Google Drive Folder</span>
            </a>
        </div>
    </div>

    {{-- ── 2. Key Metrics Cards (Standardized Text & Card Sizes) ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <!-- Team Size -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/60 shadow-xs dark:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Squad Team Size</span>
                    <div class="flex items-baseline gap-1.5 mt-1">
                        <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ $rawMembers->count() }}</span>
                        <span class="text-xs text-slate-400 font-medium">active staff</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-500/20 border border-blue-500/30 flex items-center justify-center text-blue-400 flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-500 dark:text-slate-400">Lead: <strong class="text-sky-600 dark:text-sky-300 font-semibold">{{ $currentSquad?->lead?->name ?? 'N/A' }}</strong></span>
                <a href="{{ route('kpi.team.index') }}" class="text-sky-600 hover:text-sky-500 dark:text-sky-400 font-semibold">+ Manage</a>
            </div>
        </div>

        <!-- Monthly Evaluations -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/60 shadow-xs dark:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Monthly Evaluations</span>
                    <div class="flex items-baseline gap-1.5 mt-1">
                        <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ $evaluatedCount }}</span>
                        <span class="text-xs text-slate-400 font-medium">/ {{ $rawMembers->count() }} rated</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-purple-500/20 border border-purple-500/30 flex items-center justify-center text-purple-400 flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                @php $evalPct = $rawMembers->count() > 0 ? round(($evaluatedCount / $rawMembers->count()) * 100) : 0; @endphp
                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-500 to-indigo-500 h-1.5 rounded-full transition-all duration-500" style="width: {{ $evalPct }}%"></div>
                </div>
                <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">
                    <span>Evaluation Progress</span>
                    <span class="font-bold text-slate-700 dark:text-slate-200">{{ $evalPct }}%</span>
                </div>
            </div>
        </div>

        <!-- Average Score -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/60 shadow-xs dark:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Avg Squad KPI</span>
                    <div class="flex items-baseline gap-1.5 mt-1">
                        <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ $avgKpiScore }}%</span>
                        <span class="text-[11px] text-emerald-500 dark:text-emerald-400 font-semibold px-1.5 py-0.5 rounded-md bg-emerald-500/10">Exceeds</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-500 dark:text-slate-400 font-medium">Target Benchmark</span>
                <span class="font-bold text-emerald-600 dark:text-emerald-400">90.0% Minimum</span>
            </div>
        </div>

        <!-- Top Performers -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/60 shadow-xs dark:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Top Performers</span>
                    <div class="flex items-baseline gap-1.5 mt-1">
                        <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ $outstandingCount }}</span>
                        <span class="text-[11px] text-amber-500 dark:text-amber-400 font-semibold px-1.5 py-0.5 rounded-md bg-amber-500/10">Outstanding</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-amber-400 flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-500 dark:text-slate-400 font-medium">Score &ge; 90%</span>
                <span class="font-bold text-amber-600 dark:text-amber-400">High Tier</span>
            </div>
        </div>
    </div>

    {{-- ── 3. Action Toolbar (Image 2 + Image 4 Unified Clean Toolbar - No Gap) ── --}}
    <div class="flex flex-wrap items-center gap-2 sm:gap-2.5 p-3 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 shadow-xs">
        {{-- Button 1: Give Staff KPI --}}
        <button type="button" @click="openNewRateModal()"
                class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white text-xs font-bold shadow-sm shadow-sky-500/20 flex items-center gap-2 transition-all cursor-pointer">
            <svg class="w-4 h-4 text-sky-100" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
            </svg>
            <span>Give Staff KPI</span>
        </button>

        {{-- Button 2: Export Squad PDF --}}
        <button type="button" @click="openExportModal('all', '')"
                class="px-3.5 py-2 rounded-xl bg-sky-500 hover:bg-sky-400 text-white text-xs font-bold flex items-center gap-1.5 transition-all shadow-xs cursor-pointer">
            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            <span>Export Squad PDF</span>
        </button>

        {{-- Button 3: Drive Setup --}}
        <button type="button" @click="showDriveModal = true"
                class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 text-xs font-bold flex items-center gap-1.5 transition-all shadow-xs cursor-pointer"
                title="Google Drive Webhook Setup">
            <svg class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/>
            </svg>
            <span>Drive Setup</span>
        </button>

        {{-- Button 4: Google Drive Folder (Image 4) --}}
        <a href="https://drive.google.com/drive/folders/1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi" target="_blank"
           class="px-3.5 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30 text-xs font-bold flex items-center gap-1.5 transition-all shadow-xs"
           title="Open Google Drive KPI Storage Folder">
            <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 .75-7.425A5.25 5.25 0 0 0 8.25 9.75a4.5 4.5 0 0 0-6 5.25Z" />
            </svg>
            <span>Google Drive Folder</span>
        </a>

        {{-- Button 5: Team Members --}}
        <a href="{{ route('kpi.team.index', ['squad_id' => $currentSquad?->id]) }}"
           class="px-3.5 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-600/20 dark:hover:bg-indigo-600/30 text-indigo-700 dark:text-indigo-300 hover:text-indigo-900 dark:hover:text-indigo-200 border border-indigo-200 dark:border-indigo-500/30 text-xs font-bold flex items-center gap-1.5 transition-all shadow-xs">
            <svg class="w-4 h-4 text-indigo-500 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
            </svg>
            <span>Team Members ({{ $rawMembers->count() }})</span>
        </a>
    </div>

    {{-- ── 4. Filters & Squad Tabs (Larger Text & Extended Years 2025-2035) ───────────── --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/60 shadow-xs dark:shadow-md backdrop-blur-xl transition-colors">
        <form method="GET" action="{{ url()->current() }}" class="flex flex-col gap-3.5">
            <input type="hidden" name="filter_applied" value="1">
            @if($isSupervisor && $squads->count() > 1)
            {{-- Squad Selector Pills for Supervisor --}}
            <div class="flex items-center gap-2 pb-3.5 border-b border-slate-200 dark:border-slate-800 overflow-x-auto">
                <span class="text-xs sm:text-sm font-bold text-slate-500 dark:text-slate-400 mr-2 flex-shrink-0">Select Squad:</span>
                @foreach($squads as $sq)
                <a href="{{ url()->current() . '?' . http_build_query(array_merge(request()->except('squad_id'), ['squad_id' => $sq->id])) }}"
                   class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all whitespace-nowrap {{ ($selectedSquadId == $sq->id) ? 'bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-md shadow-sky-500/20' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700/60' }}">
                    {{ $sq->name }} <span class="text-xs opacity-80 font-normal">({{ $sq->lead?->name }})</span>
                </a>
                @endforeach
            </div>
            @endif

            <input type="hidden" name="squad_id" value="{{ $selectedSquadId }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3.5">
                {{-- Search --}}
                <div class="md:col-span-2 relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </span>
                    <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Search staff name, role..."
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700/80 text-slate-800 dark:text-white text-sm placeholder-slate-400 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 shadow-xs">
                </div>

                {{-- Member Filter --}}
                <div>
                    <select name="member_id" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700/80 text-slate-800 dark:text-white text-sm focus:bg-white focus:outline-none focus:border-sky-500 shadow-xs">
                        <option value="">All Staff Members</option>
                        @foreach($rawMembers as $m)
                        <option value="{{ $m->id }}" {{ ($memberId == $m->id) ? 'selected' : '' }}>{{ $m->name }} ({{ $m->pivot->role_title ?? 'Staff' }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Month Filter --}}
                <div>
                    <select name="month" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700/80 text-slate-800 dark:text-white text-sm focus:bg-white focus:outline-none focus:border-sky-500 shadow-xs">
                        <option value="">All Months</option>
                        @php
                            $months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
                        @endphp
                        @foreach($months as $num => $mName)
                        <option value="{{ $num }}" {{ ($selectedMonth == $num) ? 'selected' : '' }}>{{ $mName }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Year Filter & Action (Supports up to 2035) --}}
                <div class="flex items-center gap-2">
                    <select name="year" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700/80 text-slate-800 dark:text-white text-sm font-semibold focus:bg-white focus:outline-none focus:border-sky-500 shadow-xs">
                        @for($y = 2025; $y <= 2035; $y++)
                        <option value="{{ $y }}" {{ (($selectedYear ?? date('Y')) == $y) ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>

                    <button type="submit" class="px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-sm font-bold transition-all shadow-sm cursor-pointer flex-shrink-0">
                        Filter
                    </button>
                    @if(!empty($search) || !empty($memberId) || !empty($selectedMonth) || request()->has('filter_applied'))
                    <a href="{{ url()->current() . ($selectedSquadId ? '?squad_id=' . $selectedSquadId : '') }}" class="px-3 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-sm border border-slate-200 dark:border-slate-700 transition-colors flex-shrink-0" title="Clear Filters">✕</a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- ── 5. Staff KPI Evaluations Table (Clean Header & Standardized UI Fonts) ── --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/60 shadow-xs dark:shadow-md overflow-hidden backdrop-blur-xl transition-colors">
        <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>Staff Evaluation Records</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-50 dark:bg-sky-500/20 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-500/30">
                        {{ $staffMembers->count() }} Staff
                    </span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Monthly KPI reviews for {{ $currentPeriod?->name ?? 'Current Period' }}. Rate scores, inspect history, or export PDF certificates.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs min-w-[850px] text-slate-700 dark:text-slate-200">
                <thead class="kpi-table-head bg-slate-900/90 text-white uppercase tracking-wider text-[11px] font-semibold border-b border-slate-700/60">
                    <tr>
                        <th class="py-3 px-3.5 text-white font-semibold tracking-wider">Staff Member</th>
                        <th class="py-3 px-3.5 text-white font-semibold tracking-wider">Role in Squad</th>
                        <th class="py-3 px-3.5 text-center text-white font-semibold tracking-wider">Score (KPI)</th>
                        <th class="py-3 px-3.5 text-center text-white font-semibold tracking-wider">Rating</th>
                        <th class="py-3 px-3.5 text-center text-white font-semibold tracking-wider">Status</th>
                        <th class="py-3 px-3.5 text-white font-semibold tracking-wider">Evaluator & Notes</th>
                        <th class="py-3 px-3.5 text-right text-white font-semibold tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @forelse($staffMembers as $staff)
                    @php
                        $rev = $reviews->get($staff->id);
                        $history = $allUserReviews->get($staff->id, collect());
                    @endphp
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                        {{-- Staff Name & Avatar --}}
                        <td class="py-3 px-3.5">
                            <div class="flex items-center gap-2.5">
                                <img src="{{ $staff->avatar_url }}" alt="{{ $staff->name }}"
                                     class="w-9 h-9 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shadow-xs flex-shrink-0"
                                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode($staff->name) }}&background=0284c7&color=fff';">
                                <div>
                                    <div class="font-semibold text-slate-900 dark:text-white text-xs sm:text-sm hover:text-sky-600 dark:hover:text-sky-300 transition-colors">
                                        {{ $staff->name }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono">
                                        {{ '@' . ($staff->username ?? 'user') }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Role in Squad & Work Types --}}
                        <td class="py-3 px-3.5">
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-blue-600 text-white font-bold text-[11px]">
                                        {{ $staff->pivot->role_title ?? 'Digital Specialist' }}
                                    </span>
                                </div>

                                {{-- Work Types Pills --}}
                                <div class="flex flex-wrap gap-1 max-w-xs">
                                    @forelse($staff->pivot_work_types as $type)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                            {{ $type }}
                                        </span>
                                    @empty
                                        <span class="text-[11px] text-slate-400 italic">Standard Assignment</span>
                                    @endforelse
                                </div>
                            </div>
                        </td>

                        {{-- Score --}}
                        <td class="py-3 px-3.5 text-center">
                            @if($rev && $rev->overall_kpi)
                                <div class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 font-bold text-xs sm:text-sm font-mono text-slate-900 dark:text-white shadow-xs">
                                    <span>{{ $rev->overall_kpi }}%</span>
                                </div>
                            @else
                                <span class="text-slate-400 italic text-xs">Not Rated</span>
                            @endif
                        </td>

                        {{-- Rating / Performance Band --}}
                        <td class="py-3 px-3.5 text-center">
                            @if($rev && $rev->performance_band)
                                @php
                                    $bandClass = match($rev->performance_band) {
                                        'Outstanding' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 border-emerald-500/30',
                                        'Exceeds Expectations' => 'bg-sky-500/15 text-sky-600 dark:text-sky-300 border-sky-500/30',
                                        'Meets Expectations' => 'bg-blue-500/15 text-blue-600 dark:text-blue-300 border-blue-500/30',
                                        default => 'bg-amber-500/15 text-amber-600 dark:text-amber-300 border-amber-500/30'
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[11px] font-semibold border {{ $bandClass }}">
                                    ★ {{ $rev->performance_band }}
                                </span>
                            @else
                                <span class="text-slate-400 text-xs">—</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="py-3 px-3.5 text-center">
                            @if($rev)
                                @php
                                    $statusClass = match($rev->status) {
                                        'Finalized' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 border-emerald-500/30',
                                        'Approved' => 'bg-sky-500/15 text-sky-600 dark:text-sky-300 border-sky-500/30',
                                        'Submitted' => 'bg-purple-500/15 text-purple-600 dark:text-purple-300 border-purple-500/30',
                                        default => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700'
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium border {{ $statusClass }}">
                                    {{ $rev->status }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 shadow-xs">
                                    Pending
                                </span>
                            @endif
                        </td>

                        {{-- Evaluator & Notes --}}
                        <td class="py-3 px-3.5 max-w-xs">
                            @if($rev)
                                <div class="text-xs text-slate-700 dark:text-slate-300 truncate" title="{{ $rev->manager_notes ?? $rev->review_notes ?? 'No notes recorded.' }}">
                                    "{{ $rev->manager_notes ?? $rev->review_notes ?? 'No notes recorded.' }}"
                                </div>
                                <div class="text-[11px] text-slate-400 mt-0.5">
                                    By: <strong class="text-slate-600 dark:text-slate-300">{{ $rev->reviewer?->name ?? 'Lead' }}</strong> &bull; {{ $rev->evaluation_date ? date('d M Y', strtotime($rev->evaluation_date)) : $rev->created_at->format('d M Y') }}
                                </div>
                                @if($rev->google_drive_url)
                                    <div class="mt-0.5">
                                        <a href="{{ $rev->google_drive_url }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] text-emerald-600 dark:text-emerald-400 hover:underline font-semibold">
                                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/></svg>
                                            <span>Saved on Google Drive</span>
                                        </a>
                                    </div>
                                @endif
                            @else
                                <span class="text-slate-400 text-xs italic">Awaiting monthly score</span>
                            @endif
                        </td>

                        {{-- Action Buttons (Rate, History, PDF) --}}
                        <td class="py-3 px-3.5 text-right">
                            <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                {{-- Rate / Update Button --}}
                                <button type="button" @click.stop="openRateFor({{ $staff->id }}, '{{ addslashes($staff->name) }}', {{ $rev ? $rev->toJson() : 'null' }})"
                                        class="px-2.5 py-1 rounded-lg bg-sky-50 hover:bg-sky-100 dark:bg-sky-500/20 dark:hover:bg-sky-500/30 border border-sky-200 dark:border-sky-500/30 text-sky-700 dark:text-sky-300 font-semibold text-xs transition-all cursor-pointer shadow-xs">
                                    {{ $rev ? 'Update' : 'Rate' }}
                                </button>

                                {{-- History Button --}}
                                <button type="button" @click.stop="openHistory('{{ addslashes($staff->name) }}', {{ $staff->id }})"
                                        class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition-all cursor-pointer shadow-xs">
                                    History ({{ $history->count() }})
                                </button>

                                {{-- Direct Download Staff KPI PDF Certificate --}}
                                <a href="{{ route('kpi.staff.pdf', ['user_id' => $staff->id, 'month' => request('month', date('n')), 'year' => request('year', date('Y'))]) }}"
                                   target="_blank"
                                   class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-500/20 dark:hover:bg-emerald-500/30 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 font-semibold text-xs flex items-center gap-1 transition-all cursor-pointer shadow-xs"
                                   title="Download Official KPI Certificate PDF for {{ $staff->name }}">
                                    <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    <span>Download PDF</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 px-4 text-center">
                            <div class="max-w-md mx-auto py-6">
                                <div class="w-14 h-14 rounded-2xl bg-sky-50 dark:bg-sky-500/20 border border-sky-200 dark:border-sky-500/30 text-sky-600 dark:text-sky-300 flex items-center justify-center mx-auto mb-3.5 shadow-xs">
                                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                    </svg>
                                </div>
                                <p class="font-bold text-slate-800 dark:text-slate-100 text-base">No evaluated staff members found for this date.</p>
                                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Staff members without evaluation records for the selected period are hidden. Try choosing a different month or year.</p>
                                <a href="{{ url()->current() . ($selectedSquadId ? '?squad_id=' . $selectedSquadId : '') }}"
                                   class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-xl bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700 font-bold text-sm hover:bg-sky-100 dark:hover:bg-sky-900/60 transition-colors">
                                    Reset Filters
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── 6. Modal: Export KPI PDF Report (Luminous & Profile Icon Member Selector) ── --}}
    <div x-show="showExportModal" style="display: none;" @click.self="showExportModal = false"
         class="fixed inset-0 z-[100] overflow-y-auto kpi-modal-overlay flex items-center justify-center p-2.5 sm:p-4 md:p-6">
        <div class="w-full max-w-3xl kpi-modal-card text-slate-800 dark:text-slate-100 rounded-2xl sm:rounded-3xl border border-slate-200 dark:border-sky-500/40 shadow-2xl overflow-hidden my-auto max-h-[92vh] flex flex-col">
            <div class="p-4 sm:p-6 border-b border-slate-200 dark:border-sky-900/50 bg-slate-100/90 dark:bg-[#1e3464] flex items-center justify-between flex-shrink-0 gap-3">
                <div class="flex items-center gap-3 sm:gap-3.5 min-w-0">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-gradient-to-br from-sky-500/20 to-blue-600/30 border border-sky-400/40 flex items-center justify-center text-sky-500 dark:text-sky-400 shadow-sm flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base sm:text-xl font-black text-slate-900 dark:text-white truncate">Export KPI PDF Report</h3>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 truncate">Generate executive certificates matching company criteria</p>
                    </div>
                </div>
                <button type="button" @click="showExportModal = false" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white border border-slate-200 dark:border-slate-700/80 flex items-center justify-center transition-all cursor-pointer font-bold flex-shrink-0">✕</button>
            </div>

            <form action="{{ route('kpi.evaluations.export-all-pdf') }}" method="GET" target="_blank" class="p-4 sm:p-6 space-y-4 sm:space-y-5 text-sm flex-1 min-h-0 overflow-y-auto" @submit="setTimeout(() => showExportModal = false, 1000)">
                <input type="hidden" name="squad_id" value="{{ $selectedSquadId }}">

                {{-- Target: All Users vs Each Specific User --}}
                <div>
                    <label class="block font-bold mb-2 text-slate-800 dark:text-slate-200 text-sm sm:text-base">Export Scope</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="kpi-scope-radio p-3 rounded-xl border flex items-center gap-2.5 cursor-pointer font-bold"
                               :class="exportTarget === 'all' ? 'active bg-sky-50 dark:bg-sky-950/60 border-sky-500' : 'bg-slate-50 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700'">
                            <input type="radio" name="export_scope" value="all" x-model="exportTarget" @change="exportUserId = ''" class="accent-sky-500">
                            <span>All Staff (All-in-One)</span>
                        </label>
                        <label class="kpi-scope-radio p-3 rounded-xl border flex items-center gap-2.5 cursor-pointer font-bold"
                               :class="exportTarget === 'single' ? 'active bg-sky-50 dark:bg-sky-950/60 border-sky-500' : 'bg-slate-50 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700'">
                            <input type="radio" name="export_scope" value="single" x-model="exportTarget" class="accent-sky-500">
                            <span>Specific Staff Member</span>
                        </label>
                    </div>
                </div>

                {{-- Profile Member Selector --}}
                <div x-show="exportTarget === 'single'" class="relative" @click.outside="exportMemberDropdownOpen = false">
                    <label class="block font-bold mb-2 text-slate-800 dark:text-slate-200 text-sm">
                        Select Staff Member <span class="text-rose-500">*</span>
                    </label>
                    <input type="hidden" name="user_id" :value="exportUserId" :required="exportTarget === 'single'">

                    <button type="button" @click="exportMemberDropdownOpen = !exportMemberDropdownOpen; if(exportMemberDropdownOpen) $nextTick(() => $refs.exportMemberSearchInput?.focus())"
                            class="kpi-member-btn group w-full p-3 rounded-xl border bg-slate-50 dark:bg-[#14244a] border-slate-300 dark:border-sky-800/60 flex items-center justify-between">
                        <template x-if="!getSelectedExportMember()">
                            <div class="flex items-center gap-2.5 text-slate-400">
                                <span>-- Choose Member from Squad --</span>
                            </div>
                        </template>
                        <template x-if="getSelectedExportMember()">
                            <div class="flex items-center gap-3 text-left min-w-0">
                                <img :src="getSelectedExportMember().avatar_url" :alt="getSelectedExportMember().name"
                                     class="w-9 h-9 rounded-xl object-cover border border-slate-200 dark:border-sky-500/40 shadow-xs flex-shrink-0"
                                     x-on:error="$event.target.src='https://ui-avatars.com/api/?name='+encodeURIComponent(getSelectedExportMember().name)+'&background=0284c7&color=fff'">
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-900 dark:text-white text-sm truncate" x-text="getSelectedExportMember().name"></div>
                                    <div class="text-xs text-sky-600 dark:text-sky-300 font-mono" x-text="getSelectedExportMember().username ? '@' + getSelectedExportMember().username : getSelectedExportMember().email"></div>
                                </div>
                            </div>
                        </template>
                        <div class="text-slate-400">▼</div>
                    </button>

                    <div x-show="exportMemberDropdownOpen" style="display: none;"
                         class="kpi-member-dropdown p-2 shadow-2xl z-50 absolute inset-x-0 mt-1 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                        <div class="p-1 mb-2">
                            <input type="text" x-model="exportMemberSearch" x-ref="exportMemberSearchInput"
                                   placeholder="Search member..."
                                   class="w-full h-10 px-3 rounded-lg bg-slate-100 dark:bg-[#132349] border border-slate-200 dark:border-sky-800/60 text-sm">
                        </div>
                        <div class="max-h-56 overflow-y-auto space-y-1">
                            <template x-for="m in filteredExportMembers()" :key="m.id">
                                <div @click="selectExportMember(m)"
                                     class="flex items-center justify-between p-2.5 rounded-xl cursor-pointer hover:bg-sky-50 dark:hover:bg-sky-950/60">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <img :src="m.avatar_url" class="w-8 h-8 rounded-lg object-cover">
                                        <div>
                                            <div class="font-bold text-sm text-slate-900 dark:text-white" x-text="m.name"></div>
                                            <div class="text-xs text-slate-400" x-text="m.role_title || 'Staff'"></div>
                                        </div>
                                    </div>
                                    <div x-show="exportUserId == m.id" class="text-sky-600 font-bold">✓</div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Month & Year Selection (Extended 2025-2035) --}}
                <div class="grid grid-cols-2 gap-3.5">
                    <div>
                        <label class="block font-bold mb-1.5 text-slate-700 dark:text-slate-200">Select Month</label>
                        <select name="month" x-model="exportMonth" class="w-full h-11 px-3.5 rounded-xl bg-slate-50 dark:bg-[#14244a] border border-slate-300 dark:border-sky-800/60 text-slate-900 dark:text-white font-bold text-sm">
                            @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1.5 text-slate-700 dark:text-slate-200">Select Year</label>
                        <select name="year" x-model="exportYear" class="w-full h-11 px-3.5 rounded-xl bg-slate-50 dark:bg-[#14244a] border border-slate-300 dark:border-sky-800/60 text-slate-900 dark:text-white font-bold text-sm">
                            @for($y = 2025; $y <= 2035; $y++)
                            <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                </div>

                {{-- Report / Evaluation Date --}}
                <div>
                    <label class="block font-bold mb-1.5 text-slate-700 dark:text-slate-200">Official Report Date (Printed on Certificate)</label>
                    <input type="date" name="evaluation_date" x-model="exportDate"
                           class="w-full h-11 px-3.5 rounded-xl bg-slate-50 dark:bg-[#14244a] border border-slate-300 dark:border-sky-800/60 text-slate-900 dark:text-white text-sm font-semibold">
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-slate-200 dark:border-sky-900/50">
                    <button type="button" @click="showExportModal = false" class="px-5 py-2.5 rounded-xl bg-slate-200 hover:bg-slate-300 dark:bg-[#203668] text-slate-800 dark:text-slate-100 font-bold cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white font-black shadow-lg shadow-sky-500/30 cursor-pointer">
                        Generate & Download PDF
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── 7. Modal: Evaluation History (Larger Text, Clean Date Formatting & Allow to Edit History Rate) ── --}}
    <div x-show="showHistoryModal" style="display: none;" @click.self="showHistoryModal = false"
         class="fixed inset-0 z-[100] overflow-y-auto kpi-modal-overlay flex items-center justify-center p-2.5 sm:p-4 md:p-6">
        <div class="w-full max-w-5xl bg-white dark:bg-[#162344] text-slate-800 dark:text-slate-100 rounded-2xl sm:rounded-3xl border border-slate-200 dark:border-sky-500/40 shadow-2xl dark:shadow-[0_20px_60px_rgba(0,0,0,0.6),0_0_35px_rgba(14,165,233,0.2)] overflow-hidden my-auto max-h-[92vh] flex flex-col">
            
            <div class="p-4 sm:p-6 border-b border-slate-200 dark:border-sky-900/50 bg-slate-100/90 dark:bg-[#1a2d56] flex items-center justify-between flex-shrink-0 gap-3">
                <div class="flex items-center gap-3 sm:gap-3.5 min-w-0">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-gradient-to-br from-sky-500/20 to-blue-600/30 border border-sky-400/40 flex items-center justify-center text-sky-500 dark:text-sky-400 shadow-sm flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base sm:text-2xl font-black text-slate-900 dark:text-white tracking-wide truncate">Evaluation History</h3>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 truncate">Staff Member: <strong class="text-sky-600 dark:text-sky-300 font-extrabold text-sm sm:text-base" x-text="historyStaffName"></strong></p>
                    </div>
                </div>
                <button type="button" @click="showHistoryModal = false"
                        class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white border border-slate-200 dark:border-slate-700/80 flex items-center justify-center transition-all cursor-pointer font-bold flex-shrink-0">
                    ✕
                </button>
            </div>

            <div class="p-3.5 sm:p-6 flex-1 min-h-0 overflow-y-auto space-y-4">
                <template x-if="historyRecords.length === 0">
                    <div class="py-16 text-center text-slate-400">
                        <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 mx-auto flex items-center justify-center text-slate-500 mb-3.5">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                        <p class="text-sm sm:text-base font-semibold">No previous KPI evaluation records found for this staff member.</p>
                    </div>
                </template>

                <template x-if="historyRecords.length > 0">
                    <div class="space-y-4">
                        <template x-for="rec in historyRecords" :key="rec.id">
                            <div class="p-4 sm:p-6 rounded-2xl bg-slate-50 dark:bg-[#1b2d56] border border-slate-200 dark:border-sky-500/30 hover:border-sky-400/60 transition-all shadow-xs">
                                <div class="flex flex-wrap items-center justify-between gap-2.5 pb-3 border-b border-slate-200 dark:border-sky-900/40">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="px-3 py-1 rounded-xl text-xs sm:text-sm font-black bg-blue-100 dark:bg-sky-950/80 border border-sky-300 dark:border-sky-500/50 text-sky-800 dark:text-sky-200"
                                              x-text="rec.period ? rec.period.name : 'Evaluation Cycle'"></span>
                                        <span class="px-2.5 py-1 rounded-xl text-xs sm:text-sm font-bold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-300 dark:border-emerald-500/40 text-emerald-700 dark:text-emerald-300"
                                              x-text="'★ ' + (rec.performance_band || 'Standard')"></span>
                                        <span class="px-2 py-0.5 rounded-lg text-xs font-semibold bg-sky-50 dark:bg-sky-900/60 border border-sky-200 dark:border-sky-600/40 text-sky-700 dark:text-sky-300"
                                              x-text="rec.status"></span>
                                    </div>
                                    <div class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-mono font-medium">
                                        <span x-text="'Evaluated on: ' + formatKpiDate(rec.evaluation_date || rec.created_at)"></span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 py-3 sm:py-4">
                                    <div class="p-2.5 sm:p-3.5 rounded-2xl bg-white dark:bg-[#132244] border border-slate-200 dark:border-sky-900/50 text-center shadow-xs">
                                        <span class="text-[10px] sm:text-xs uppercase font-extrabold text-slate-400 block tracking-wider">Overall KPI</span>
                                        <span class="text-xl sm:text-3xl font-black text-sky-600 dark:text-sky-300 mt-1 block" x-text="rec.overall_kpi + '%'"></span>
                                    </div>
                                    <div class="p-2.5 sm:p-3.5 rounded-2xl bg-white dark:bg-[#132244] border border-slate-200 dark:border-sky-900/50 text-center shadow-xs">
                                        <span class="text-[10px] sm:text-xs uppercase font-extrabold text-slate-400 block tracking-wider">Quality</span>
                                        <span class="text-base sm:text-xl font-black text-emerald-600 dark:text-emerald-300 mt-1 block" x-text="(rec.quality_score || rec.score || 90) + '%'"></span>
                                    </div>
                                    <div class="p-2.5 sm:p-3.5 rounded-2xl bg-white dark:bg-[#132244] border border-slate-200 dark:border-sky-900/50 text-center shadow-xs">
                                        <span class="text-[10px] sm:text-xs uppercase font-extrabold text-slate-400 block tracking-wider">Speed</span>
                                        <span class="text-base sm:text-xl font-black text-amber-600 dark:text-amber-300 mt-1 block" x-text="(rec.deadline_score || 90) + '%'"></span>
                                    </div>
                                    <div class="p-2.5 sm:p-3.5 rounded-2xl bg-white dark:bg-[#132244] border border-slate-200 dark:border-sky-900/50 text-center shadow-xs">
                                        <span class="text-[10px] sm:text-xs uppercase font-extrabold text-slate-400 block tracking-wider">Output</span>
                                        <span class="text-base sm:text-xl font-black text-purple-600 dark:text-purple-300 mt-1 block" x-text="(rec.productivity_score || 90) + '%'"></span>
                                    </div>
                                </div>

                                {{-- Reviewer Feedback & Notes Box --}}
                                <div class="rounded-2xl p-3.5 sm:p-5 text-sm mb-3.5 sm:mb-4 border bg-sky-50/90 dark:bg-[#132244] border-sky-200/80 dark:border-sky-500/40 text-slate-800 dark:text-slate-100 shadow-xs">
                                    <div class="flex items-center justify-between mb-2.5 pb-2.5 border-b border-sky-200/60 dark:border-sky-500/20 font-bold text-sky-700 dark:text-sky-300">
                                        <span class="flex items-center gap-2 text-xs sm:text-base font-bold">
                                            <svg class="w-4 h-4 text-sky-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.502 49.188 49.188 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                            </svg>
                                            Reviewer Feedback & Notes
                                        </span>
                                        <span class="text-xs font-mono text-sky-700 dark:text-sky-200 bg-sky-100 dark:bg-sky-950/80 px-2 py-0.5 rounded-md border border-sky-200 dark:border-sky-700/50" x-text="rec.status || 'Approved'"></span>
                                    </div>
                                    <p class="font-medium text-xs sm:text-base leading-relaxed italic text-slate-800 dark:text-slate-100 py-1"
                                       x-text="'“' + (rec.manager_notes || rec.review_notes || rec.supervisor_notes || 'All monthly performance criteria met standard requirements.') + '”'"></p>
                                    <div class="mt-2.5 pt-2 border-t border-sky-200/50 dark:border-sky-500/20 flex flex-wrap items-center justify-between text-xs sm:text-sm text-slate-500 dark:text-slate-300 gap-2">
                                        <span class="font-semibold text-slate-700 dark:text-slate-200" x-text="'Evaluated by: ' + (rec.reviewer ? rec.reviewer.name : 'Team Lead')"></span>
                                        <span x-text="'Evaluation Date: ' + formatKpiDate(rec.evaluation_date || rec.created_at)"></span>
                                    </div>
                                </div>

                                {{-- History Record Actions: Edit Rate, Download PDF & Google Drive Sync --}}
                                <div class="flex flex-wrap items-center justify-between gap-2.5 pt-3 border-t border-slate-200 dark:border-sky-900/40">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        {{-- ALLOW TO EDIT HISTORY RATE BUTTON --}}
                                        <button type="button" @click="editHistoryRecord(rec, historyStaffName)"
                                                class="px-3.5 py-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-700 dark:text-amber-300 border border-amber-500/40 text-xs sm:text-sm font-bold flex items-center gap-1.5 transition-all shadow-xs cursor-pointer"
                                                title="Edit and recalculate this historical evaluation">
                                            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                            <span>Edit Rate</span>
                                        </button>

                                        {{-- Google Drive Link if Synced --}}
                                        <template x-if="rec.google_drive_url">
                                            <a :href="rec.google_drive_url" target="_blank"
                                               class="px-3 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-700 dark:text-emerald-300 border border-emerald-500/40 text-xs sm:text-sm font-bold flex items-center gap-1.5 transition-all shadow-xs"
                                               title="View PDF directly in Google Drive">
                                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-emerald-500 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/>
                                                </svg>
                                                <span>Google Drive</span>
                                            </a>
                                        </template>
                                    </div>

                                    <div class="flex items-center gap-2 flex-wrap">
                                        {{-- Direct Download PDF --}}
                                        <a :href="'/kpi/evaluations/' + rec.id + '/pdf'" target="_blank"
                                           class="px-3.5 sm:px-4 py-2 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white text-xs sm:text-sm font-bold flex items-center gap-1.5 shadow-xs transition-all">
                                            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                            </svg>
                                            <span>Download PDF</span>
                                        </a>

                                        <template x-if="rec.uploaded_pdf_path">
                                            <a :href="'/' + rec.uploaded_pdf_path" target="_blank"
                                               class="px-3 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-700 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-1.5 transition-all">
                                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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

            <div class="px-4 sm:px-6 py-3.5 sm:py-4 bg-slate-100/90 dark:bg-[#1a2d56] border-t border-slate-200 dark:border-sky-900/50 flex items-center justify-between text-xs sm:text-sm flex-shrink-0">
                <span class="text-slate-500 dark:text-slate-400 font-medium"><strong class="text-sky-600 dark:text-sky-300 font-extrabold text-sm sm:text-base" x-text="historyRecords.length"></strong> record(s) on file</span>
                <button type="button" @click="showHistoryModal = false"
                        class="px-4 sm:px-5 py-2 rounded-xl bg-slate-200/80 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold border border-slate-300 dark:border-slate-700 transition-all cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>

    {{-- ── 8. Modal: Rate / Edit Staff KPI (Supports History Edit & Auto Google Drive Upload) ──── --}}
    <div x-show="showRateModal" style="display: none;" @click.self="showRateModal = false"
         class="fixed inset-0 z-[100] overflow-y-auto kpi-modal-overlay flex items-center justify-center p-2.5 sm:p-4 md:p-6">
        <div class="w-full max-w-4xl bg-white dark:bg-[#162344] text-slate-800 dark:text-slate-100 rounded-2xl sm:rounded-3xl border border-slate-200 dark:border-sky-500/40 shadow-2xl dark:shadow-[0_20px_60px_rgba(0,0,0,0.6),0_0_35px_rgba(14,165,233,0.2)] overflow-hidden my-auto max-h-[92vh] flex flex-col">
            
            <div class="p-4 sm:p-6 border-b border-slate-200 dark:border-slate-800 bg-slate-50/90 dark:bg-slate-900/80 flex items-center justify-between flex-shrink-0 gap-3">
                <div class="flex items-center gap-3 sm:gap-3.5 min-w-0">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-gradient-to-br from-sky-500/20 to-blue-600/30 border border-sky-400/40 flex items-center justify-center text-sky-500 dark:text-sky-400 shadow-sm flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base sm:text-xl font-black text-slate-900 dark:text-white truncate" x-text="selectedReviewId ? ('Edit KPI Evaluation: ' + (selectedStaffName || 'Staff')) : 'Monthly Staff KPI Rating'"></h3>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                            Squad: <span class="text-sky-600 dark:text-sky-300 font-bold">{{ $currentSquad?->name }}</span>
                            <span x-show="selectedReviewId" class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300 border border-amber-300">Editing History Rate #<span x-text="selectedReviewId"></span></span>
                        </p>
                    </div>
                </div>
                <button type="button" @click="showRateModal = false" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white border border-slate-200 dark:border-slate-700/80 flex items-center justify-center transition-all cursor-pointer font-bold flex-shrink-0">✕</button>
            </div>

            <form action="{{ route('kpi.evaluations.rate') }}" method="POST" class="p-4 sm:p-8 space-y-5 sm:space-y-6 text-sm flex-1 min-h-0 overflow-y-auto">
                @csrf
                <input type="hidden" name="review_id" :value="selectedReviewId">
                <input type="hidden" name="squad_id" :value="selectedSquadId">
                <input type="hidden" name="status" :value="reviewStatus || 'Approved'">
                <input type="hidden" name="teamwork_score" :value="scoreTeam">

                {{-- Top Row: Staff Member & Date Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="relative" @click.outside="rateMemberDropdownOpen = false">
                        <label class="block font-bold mb-2 text-slate-800 dark:text-slate-200 text-sm sm:text-base">
                            Staff Member <span class="text-rose-500">*</span>
                        </label>
                        <input type="hidden" name="user_id" :value="selectedUserId" required>

                        <button type="button" @click="rateMemberDropdownOpen = !rateMemberDropdownOpen; if(rateMemberDropdownOpen) $nextTick(() => $refs.rateMemberSearchInput?.focus())"
                                class="kpi-member-btn group w-full p-3.5 rounded-2xl border bg-slate-50 dark:bg-slate-900 border-slate-300 dark:border-slate-700 flex items-center justify-between text-left">
                            <template x-if="!getSelectedRateMember()">
                                <div class="flex items-center gap-2.5 text-slate-400">
                                    <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-400 flex-shrink-0">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                        </svg>
                                    </div>
                                    <span class="font-medium text-sm">-- Choose Member from Squad --</span>
                                </div>
                            </template>
                            <template x-if="getSelectedRateMember()">
                                <div class="flex items-center gap-3.5 text-left min-w-0">
                                    <img :src="getSelectedRateMember().avatar_url" :alt="getSelectedRateMember().name"
                                         class="w-10 h-10 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shadow-xs flex-shrink-0"
                                         x-on:error="$event.target.src='https://ui-avatars.com/api/?name='+encodeURIComponent(getSelectedRateMember().name)+'&background=0284c7&color=fff'">
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-900 dark:text-white text-sm sm:text-base truncate" x-text="getSelectedRateMember().name"></div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs text-sky-600 dark:text-sky-400 font-mono" x-text="getSelectedRateMember().username ? '@' + getSelectedRateMember().username : getSelectedRateMember().email"></span>
                                            <span class="text-xs px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-semibold" x-text="getSelectedRateMember().role_title || 'Staff'"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div class="text-slate-400">▼</div>
                        </button>

                        {{-- Dropdown Card Panel --}}
                        <div x-show="rateMemberDropdownOpen" style="display: none;"
                             class="kpi-member-dropdown p-2 shadow-2xl z-50 absolute inset-x-0 mt-1 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                            <div class="p-1 mb-2">
                                <input type="text" x-model="rateMemberSearch" x-ref="rateMemberSearchInput"
                                       placeholder="Search squad member..."
                                       class="w-full h-10 px-3 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm">
                            </div>
                            <div class="max-h-56 overflow-y-auto space-y-1">
                                <template x-for="m in filteredRateMembers()" :key="m.id">
                                    <div @click="selectRateMember(m)"
                                         class="flex items-center justify-between p-2.5 rounded-xl cursor-pointer hover:bg-sky-50 dark:hover:bg-sky-950/50">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <img :src="m.avatar_url" class="w-9 h-9 rounded-xl object-cover">
                                            <div>
                                                <div class="font-bold text-sm text-slate-900 dark:text-white" x-text="m.name"></div>
                                                <div class="text-xs text-slate-400" x-text="m.role_title || 'Staff'"></div>
                                            </div>
                                        </div>
                                        <div x-show="selectedUserId == m.id" class="text-sky-600 font-bold">✓</div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Month, Year (2025-2035) & Evaluation Date --}}
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold mb-2 text-slate-800 dark:text-slate-200 text-xs sm:text-sm">Month</label>
                            <select name="month" x-model="reviewMonth" class="w-full px-3 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-bold text-sm">
                                @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold mb-2 text-slate-800 dark:text-slate-200 text-xs sm:text-sm">Year</label>
                            <select name="year" x-model="reviewYear" class="w-full px-3 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-bold text-sm">
                                @for($y = 2025; $y <= 2035; $y++)
                                <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold mb-2 text-slate-800 dark:text-slate-200 text-xs sm:text-sm">Date Rated</label>
                            <input type="date" name="evaluation_date" x-model="evaluationDate"
                                   class="w-full px-3 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs sm:text-sm font-semibold">
                        </div>
                    </div>
                </div>

                {{-- Middle Row: Criteria Sliders & Calculated Score Banner + Notes --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch">
                    {{-- Left Column: 4 Criteria Sliders --}}
                    <div class="space-y-4 p-6 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between font-bold mb-2 text-sm sm:text-base">
                                <span class="text-slate-800 dark:text-slate-200">1. Quality & Accuracy (35%)</span>
                                <span class="text-sky-600 dark:text-sky-400 font-black px-3 py-1 rounded-xl bg-sky-100 dark:bg-sky-950/60 border border-sky-300/60 dark:border-sky-800/40 text-sm sm:text-base" x-text="scoreQual + '%'"></span>
                            </div>
                            <input type="range" min="50" max="100" step="1" x-model="scoreQual" name="quality_score" class="w-full h-2.5 rounded-lg accent-sky-500 cursor-pointer">
                        </div>
                        <div>
                            <div class="flex justify-between font-bold mb-2 text-sm sm:text-base">
                                <span class="text-slate-800 dark:text-slate-200">2. Productivity & Output (35%)</span>
                                <span class="text-sky-600 dark:text-sky-400 font-black px-3 py-1 rounded-xl bg-sky-100 dark:bg-sky-950/60 border border-sky-300/60 dark:border-sky-800/40 text-sm sm:text-base" x-text="scoreProd + '%'"></span>
                            </div>
                            <input type="range" min="50" max="100" step="1" x-model="scoreProd" name="productivity_score" class="w-full h-2.5 rounded-lg accent-sky-500 cursor-pointer">
                        </div>
                        <div>
                            <div class="flex justify-between font-bold mb-2 text-sm sm:text-base">
                                <span class="text-slate-800 dark:text-slate-200">3. Timeliness & TAT (20%)</span>
                                <span class="text-sky-600 dark:text-sky-400 font-black px-3 py-1 rounded-xl bg-sky-100 dark:bg-sky-950/60 border border-sky-300/60 dark:border-sky-800/40 text-sm sm:text-base" x-text="scoreTat + '%'"></span>
                            </div>
                            <input type="range" min="50" max="100" step="1" x-model="scoreTat" name="deadline_score" class="w-full h-2.5 rounded-lg accent-sky-500 cursor-pointer">
                        </div>
                        <div>
                            <div class="flex justify-between font-bold mb-2 text-sm sm:text-base">
                                <span class="text-slate-800 dark:text-slate-200">4. Collaboration & Discipline (10%)</span>
                                <span class="text-sky-600 dark:text-sky-400 font-black px-3 py-1 rounded-xl bg-sky-100 dark:bg-sky-950/60 border border-sky-300/60 dark:border-sky-800/40 text-sm sm:text-base" x-text="scoreTeam + '%'"></span>
                            </div>
                            <input type="range" min="50" max="100" step="1" x-model="scoreTeam" name="collaboration_score" class="w-full h-2.5 rounded-lg accent-sky-500 cursor-pointer">
                        </div>
                    </div>

                    {{-- Right Column: Calculated Score Banner & Notes --}}
                    <div class="flex flex-col justify-between space-y-4">
                        <div class="p-6 rounded-2xl bg-gradient-to-br from-sky-50 via-blue-50 to-indigo-50 dark:from-blue-950/70 dark:via-slate-900 dark:to-indigo-950/60 border border-sky-200 dark:border-sky-500/40 flex items-center justify-between shadow-sm">
                            <div>
                                <span class="text-xs uppercase font-extrabold text-slate-500 dark:text-slate-400 block tracking-wider">Calculated Monthly Score</span>
                                <div class="flex items-baseline gap-2.5 mt-1">
                                    <span class="text-4xl sm:text-5xl font-black text-slate-900 dark:text-white" x-text="overallScore + '%'"></span>
                                    <span class="text-xs sm:text-sm font-bold text-sky-700 dark:text-sky-300 px-3 py-1 rounded-lg bg-sky-100 dark:bg-sky-500/20 border border-sky-300 dark:border-sky-400/30" x-text="rankBand"></span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-slate-500 dark:text-slate-400 block font-bold">5-Star Scale</span>
                                <span class="text-lg sm:text-xl font-black text-amber-500 dark:text-amber-400" x-text="(overallScore / 20).toFixed(1) + ' ★ / 5.0'"></span>
                            </div>
                        </div>

                        {{-- Reviewer Notes & Feedback Box (Populated via x-model="reviewNotes" for history edit) --}}
                        <div class="flex-1 flex flex-col pt-1">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block font-bold text-slate-800 dark:text-slate-200 text-sm sm:text-base">
                                    Reviewer Notes & Feedback
                                </label>
                                <span class="text-xs text-sky-600 dark:text-sky-400 font-bold">Includes in PDF & Drive</span>
                            </div>
                            <textarea name="manager_notes" x-model="reviewNotes" rows="6" placeholder="Write feedback, key achievements, strengths, and areas for improvement..."
                                      class="w-full min-h-[170px] p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 text-sm sm:text-base leading-relaxed shadow-inner transition-all resize-y"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Google Drive Auto-Upload Notice --}}
                <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-500/40 text-xs sm:text-sm text-emerald-800 dark:text-emerald-200 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/>
                        </svg>
                        <span><strong>Automatic Google Drive Integration:</strong> Upon saving, this KPI evaluation PDF is automatically created and uploaded into the correct Year and Month folders in Google Drive.</span>
                    </div>
                    <a href="https://drive.google.com/drive/folders/1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi" target="_blank" class="underline font-bold whitespace-nowrap">View Folder</a>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800 flex-shrink-0">
                    <button type="button" @click="showRateModal = false" class="px-4 sm:px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold cursor-pointer border border-slate-200 dark:border-slate-700 text-xs sm:text-sm">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 sm:px-7 py-2.5 sm:py-3 rounded-2xl bg-gradient-to-r from-sky-500 via-blue-600 to-indigo-600 hover:from-sky-400 hover:to-blue-500 text-white font-extrabold text-xs sm:text-base shadow-lg shadow-sky-500/30 cursor-pointer">
                        <span x-text="selectedReviewId ? 'Update & Sync to Google Drive' : 'Save & Upload to Google Drive'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── 9. Modal: Google Drive Integration & Setup Guide ────────────────────────── --}}
    <div x-show="showDriveModal" style="display: none;" @click.self="showDriveModal = false"
         class="fixed inset-0 z-[100] overflow-y-auto kpi-modal-overlay flex items-center justify-center p-2.5 sm:p-4 md:p-6">
        <div class="w-full max-w-3xl bg-white dark:bg-[#162344] text-slate-800 dark:text-slate-100 rounded-2xl sm:rounded-3xl border border-slate-200 dark:border-emerald-500/40 shadow-2xl dark:shadow-[0_20px_60px_rgba(0,0,0,0.6),0_0_35px_rgba(16,185,129,0.2)] overflow-hidden my-auto max-h-[92vh] flex flex-col">
            <div class="p-4 sm:p-6 border-b border-slate-200 dark:border-emerald-900/50 bg-slate-100/90 dark:bg-[#153434] flex items-center justify-between flex-shrink-0 gap-3">
                <div class="flex items-center gap-3 sm:gap-3.5 min-w-0">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-emerald-500/20 border border-emerald-400/40 flex items-center justify-center text-emerald-500 shadow-sm flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base sm:text-xl font-black text-slate-900 dark:text-white truncate">Google Drive Automatic Sync</h3>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 truncate">Target Folder: <strong class="text-emerald-600 dark:text-emerald-300">1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi</strong></p>
                    </div>
                </div>
                <button type="button" @click="showDriveModal = false" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white border border-slate-200 dark:border-slate-700/80 flex items-center justify-center transition-all cursor-pointer font-bold flex-shrink-0">✕</button>
            </div>

            <div class="p-4 sm:p-6 space-y-4 text-sm flex-1 min-h-0 overflow-y-auto">
                <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-500/40 space-y-2">
                    <h4 class="font-bold text-emerald-800 dark:text-emerald-300 text-sm sm:text-base">Direct Folder Link</h4>
                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300">Official KPI PDFs are organized by Year and Month folders inside:</p>
                    <a href="https://drive.google.com/drive/folders/1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi" target="_blank" class="inline-flex items-center gap-2 text-sky-600 dark:text-sky-400 font-bold hover:underline break-all text-xs sm:text-sm">
                        <span>https://drive.google.com/drive/folders/1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi</span>
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                    </a>
                </div>

                <div class="space-y-2">
                    <h4 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base">How it Works:</h4>
                    <ol class="list-decimal list-inside space-y-1.5 text-xs sm:text-sm text-slate-600 dark:text-slate-300">
                        <li>When you <strong>rate or update</strong> a staff member, the system automatically builds the official company KPI PDF.</li>
                        <li>It connects to your Google Drive folder <code class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-sky-600 font-mono text-xs">1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi</code>.</li>
                        <li>It verifies whether the <strong>Year folder (2026, 2027, 2028, etc.)</strong> exists. If not, it creates it automatically!</li>
                        <li>Inside the Year folder, it checks whether the <strong>Month folder (e.g. September)</strong> exists. If not, it creates it automatically!</li>
                        <li>It uploads the PDF formatted as: <code class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-emerald-600 font-mono text-xs">[Staff Name] - KPI [Month] [Year].pdf</code>. If an older copy exists, it updates it seamlessly.</li>
                    </ol>
                </div>

                <div class="space-y-3 p-4 rounded-2xl bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-500/40">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <h4 class="font-bold text-sky-900 dark:text-sky-300 text-xs sm:text-base">Configured Google Apps Script Endpoint</h4>
                        </div>
                        <span class="px-2 py-0.5 text-[11px] sm:text-xs font-semibold rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">Configured in .env</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="text" readonly value="https://script.google.com/macros/s/AKfycbxXXOumYYCzercvaTZwu8maugr8FDkHDudUQ5kTf4JWIUY3GqRzqJJgPw27zFEFPvnG/exec"
                               class="w-full text-[11px] sm:text-xs font-mono p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-sky-300 select-all" />
                        <a href="https://script.google.com/macros/s/AKfycbxXXOumYYCzercvaTZwu8maugr8FDkHDudUQ5kTf4JWIUY3GqRzqJJgPw27zFEFPvnG/exec" target="_blank"
                           class="px-3 py-2 rounded-lg bg-sky-500 hover:bg-sky-600 text-white font-bold text-xs whitespace-nowrap flex items-center gap-1 transition-all">
                            Test URL
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                        </a>
                    </div>
                    <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-600/50 text-xs text-amber-800 dark:text-amber-200 space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                            Important Setup Check: "Who has access" must be "Anyone"
                        </div>
                        <p class="leading-relaxed">
                            In <a href="https://script.google.com/" target="_blank" class="underline font-semibold">script.google.com</a>, click <strong>Deploy</strong> &rarr; <strong>Manage deployments</strong> &rarr; click <strong>Edit (Pencil)</strong>. Ensure <strong>"Execute as" = Me</strong> and <strong>"Who has access" = Anyone (អ្នកណាក៏បាន)</strong>. If set to "Only myself", Google blocks automated server uploads with HTTP 403.
                        </p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <h4 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base">Google Apps Script Source Code</h4>
                        <button type="button" @click="copyAppsScriptCode()" class="px-3 py-1.5 rounded-xl bg-sky-500 hover:bg-sky-400 text-white font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer">
                            <span x-text="copyScriptSuccess ? '✓ Copied!' : 'Copy Script Code'"></span>
                        </button>
                    </div>

                    <pre id="kpi-apps-script-raw-code" class="p-3.5 rounded-xl bg-slate-900 text-sky-300 text-xs font-mono max-h-48 overflow-y-auto leading-relaxed border border-slate-700">/** Google Apps Script for KPI Upload */
function doPost(e) {
  var data = JSON.parse(e.postData.contents);
  var rootFolder = DriveApp.getFolderById(data.folder_id || "1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi");
  var yearFolder = getOrCreateFolder(rootFolder, String(data.year));
  var monthFolder = getOrCreateFolder(yearFolder, String(data.month));
  var blob = Utilities.newBlob(Utilities.base64Decode(data.file_data), data.mime_type || "application/pdf", data.file_name);
  var existing = monthFolder.getFilesByName(data.file_name);
  while (existing.hasNext()) { existing.next().setTrashed(true); }
  var file = monthFolder.createFile(blob);
  file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
  return ContentService.createTextOutput(JSON.stringify({ status: "success", file_id: file.getId(), file_url: file.getUrl() })).setMimeType(ContentService.MimeType.JSON);
}
function getOrCreateFolder(p, n) {
  var it = p.getFoldersByName(n);
  return it.hasNext() ? it.next() : p.createFolder(n);
}</pre>
                </div>
            </div>

            <div class="px-4 sm:px-6 py-3.5 sm:py-4 bg-slate-100/90 dark:bg-[#1a2d56] border-t border-slate-200 dark:border-emerald-900/50 flex items-center justify-between gap-3 text-xs sm:text-sm flex-shrink-0">
                <span class="text-slate-500 dark:text-slate-400 truncate">A full script is also saved in <code class="font-mono text-sky-400">google-apps-script/kpi_drive_uploader.js</code></span>
                <button type="button" @click="showDriveModal = false" class="px-4 sm:px-5 py-2 rounded-xl bg-slate-200/80 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold border border-slate-300 dark:border-slate-700 cursor-pointer flex-shrink-0">
                    Close
                </button>
            </div>
        </div>
    </div>

</div>

@endsection
