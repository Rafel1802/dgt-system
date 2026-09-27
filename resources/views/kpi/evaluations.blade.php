@extends('layouts.app')
@section('title', 'Monthly Staff KPI Evaluations')
@section('page_title', 'Staff KPI Evaluations')

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
    showSupervisorModal: false,
    selectedReviewId: null,
    selectedUserId: null,
    selectedUserName: '',
    selectedSquadId: {{ $currentSquad?->id ?? 1 }},
    scoreProd: 95,
    scoreQual: 95,
    scoreTat: 92,
    scoreTeam: 95,
    reviewNotes: '',
    supervisorNotes: '',
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

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Monthly Staff KPI Evaluations</h1>
            <p class="text-xs text-slate-400 mt-1">
                Rate monthly performance scores, write review notes, and generate official staff KPI reports.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <!-- Period Selector -->
            <form method="GET" action="{{ route('kpi.evaluations.index') }}" class="flex items-center gap-2">
                @if($selectedSquadId)
                <input type="hidden" name="squad_id" value="{{ $selectedSquadId }}">
                @endif
                <select name="period_id" onchange="this.form.submit()" class="px-3 py-1.5 border rounded-xl text-xs font-semibold dark:bg-slate-800 dark:border-slate-700">
                    @foreach($periods as $p)
                    <option value="{{ $p->id }}" {{ $currentPeriod?->id === $p->id ? 'selected' : '' }}>
                        {{ $p->name }}
                    </option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('kpi.index') }}" class="clay-btn bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 text-xs">
                ← Dashboard
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-semibold">
        ✓ {{ session('success') }}
    </div>
    @endif

    {{-- Squad Selector (Supervisor) --}}
    @if($isSupervisor && $squads->count() > 1)
    <div class="flex items-center gap-2 p-1.5 rounded-2xl bg-slate-100 dark:bg-slate-800 w-fit">
        @foreach($squads as $sq)
        <a href="{{ route('kpi.evaluations.index', ['squad_id' => $sq->id, 'period_id' => $currentPeriod?->id]) }}"
           class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ ($currentSquad?->id === $sq->id) ? 'bg-white dark:bg-slate-700 text-blue-600 shadow-sm' : 'text-slate-600' }}">
            {{ $sq->name }} ({{ $sq->members->count() }} Staff)
        </a>
        @endforeach
    </div>
    @endif

    {{-- Evaluations Table --}}
    <div class="clay-card overflow-hidden">
        <div class="p-6 border-b border-slate-100 dark:border-slate-700/60">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">
                {{ $currentSquad?->name }} • {{ $currentPeriod?->name }} Evaluation Matrix
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">
                Evaluator: <strong>{{ $currentSquad?->lead?->name }}</strong> (Lead) • Supervisor: <strong>Ms. Somalika</strong>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-400 font-semibold border-b border-slate-100 dark:border-slate-700/60">
                        <th class="py-3 px-6">Staff Member</th>
                        <th class="py-3 px-4">Role Title</th>
                        <th class="py-3 px-4 text-center">Productivity</th>
                        <th class="py-3 px-4 text-center">Quality</th>
                        <th class="py-3 px-4 text-center">TAT Speed</th>
                        <th class="py-3 px-4 text-center">Teamwork</th>
                        <th class="py-3 px-4 text-center">Total KPI</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-slate-700 dark:text-slate-300">
                    @forelse($staffMembers as $staff)
                    @php $rev = $reviews->get($staff->id); @endphp
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-3.5 px-6 font-bold text-slate-900 dark:text-white">
                            {{ $staff->name }}
                        </td>
                        <td class="py-3.5 px-4 text-slate-500 font-medium">
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
                                <span class="text-slate-400 text-xs italic">Pending</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4">
                            @if($rev)
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold {{ $rev->status === 'Finalized' ? 'bg-purple-50 text-purple-700' : ($rev->status === 'Approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700') }}">
                                    {{ $rev->status }}
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-500">
                                    Not Rated
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
                                    class="px-2.5 py-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold text-[11px]">
                                {{ $rev ? 'Edit Score' : 'Rate Staff' }}
                            </button>
                            @if($rev && $isSupervisor && $rev->status !== 'Finalized')
                            <button type="button"
                                    @click="
                                        selectedReviewId = {{ $rev->id }};
                                        selectedUserName = '{{ addslashes($staff->name) }}';
                                        supervisorNotes = '{{ addslashes($rev->supervisor_notes ?? '') }}';
                                        showSupervisorModal = true;
                                    "
                                    class="px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-semibold text-[11px]">
                                Review / Approve
                            </button>
                            @endif
                            @if($rev)
                            <a href="{{ route('kpi.evaluations.pdf', $rev->id) }}" target="_blank"
                               class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-[11px]">
                                Export PDF
                            </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-8 text-center text-slate-400">
                            No staff in this squad. Add members from the Team page.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Modal: Rate Staff KPI ──────────────────────────────────────────── --}}
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

                <div class="grid grid-cols-2 gap-4">
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-600">
                        <div class="flex justify-between items-center mb-1">
                            <label class="font-bold text-slate-800 dark:text-white">Productivity (35%)</label>
                            <span class="font-black text-blue-600 text-sm" x-text="scoreProd + '%'"></span>
                        </div>
                        <input type="range" min="0" max="100" step="1" x-model.number="scoreProd" name="productivity_score" class="w-full accent-blue-600">
                    </div>

                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-600">
                        <div class="flex justify-between items-center mb-1">
                            <label class="font-bold text-slate-800 dark:text-white">Quality (35%)</label>
                            <span class="font-black text-purple-600 text-sm" x-text="scoreQual + '%'"></span>
                        </div>
                        <input type="range" min="0" max="100" step="1" x-model.number="scoreQual" name="quality_score" class="w-full accent-purple-600">
                    </div>

                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-600">
                        <div class="flex justify-between items-center mb-1">
                            <label class="font-bold text-slate-800 dark:text-white">TAT Speed (20%)</label>
                            <span class="font-black text-emerald-600 text-sm" x-text="scoreTat + '%'"></span>
                        </div>
                        <input type="range" min="0" max="100" step="1" x-model.number="scoreTat" name="deadline_score" class="w-full accent-emerald-600">
                    </div>

                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-600">
                        <div class="flex justify-between items-center mb-1">
                            <label class="font-bold text-slate-800 dark:text-white">Teamwork (10%)</label>
                            <span class="font-black text-amber-600 text-sm" x-text="scoreTeam + '%'"></span>
                        </div>
                        <input type="range" min="0" max="100" step="1" x-model.number="scoreTeam" name="teamwork_score" class="w-full accent-amber-600">
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-slate-700 dark:to-slate-700 border border-blue-200/60 dark:border-slate-600 flex items-center justify-between">
                    <div>
                        <span class="text-xs uppercase font-bold text-slate-500 dark:text-slate-300">Overall Weighted Score</span>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-2xl font-black text-blue-700 dark:text-blue-300" x-text="overallScore + '%'"></span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-white dark:bg-slate-800 text-slate-800 dark:text-white" x-text="rankBand"></span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Review Status</label>
                        <select name="status" x-model="reviewStatus" class="px-3 py-1.5 border rounded-xl dark:bg-slate-800 text-xs font-semibold">
                            <option value="Approved">Approved</option>
                            <option value="Submitted">Submitted</option>
                            <option value="Draft">Draft</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-bold mb-1 text-slate-700 dark:text-slate-300">Review Notes & Commendations</label>
                    <textarea name="manager_notes" rows="3" required x-model="reviewNotes"
                              placeholder="Write review notes on monthly deliverables, strengths, and areas to improve..."
                              class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700 dark:border-slate-600"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                    <button type="button" @click="showRateModal = false" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-semibold">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 text-white font-bold">Save Staff KPI</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Modal: Supervisor Review & Commendation ────────────────────────── --}}
    @if($isSupervisor)
    <div x-show="showSupervisorModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="clay-card w-full max-w-md p-6 bg-white dark:bg-slate-800" @click.away="showSupervisorModal = false">
            <h3 class="text-base font-bold mb-3 text-slate-900 dark:text-white">Supervisor Review Sign-off</h3>
            <p class="text-xs text-slate-500 mb-3">Reviewing KPI for: <strong class="text-blue-600" x-text="selectedUserName"></strong></p>
            <form :action="'{{ url('/kpi/evaluations') }}/' + selectedReviewId + '/approve'" method="POST" class="space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">Supervisor Commendation / Feedback</label>
                    <textarea name="supervisor_notes" rows="3" x-model="supervisorNotes" class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="showSupervisorModal = false" class="px-4 py-2 rounded-xl bg-slate-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold">Approve Review</button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
@endsection
