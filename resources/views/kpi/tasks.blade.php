@extends('layouts.app')
@section('title', 'KPI Deliverables & Tasks')
@section('page_title', 'KPI Deliverables Management')

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
    showSubmitModal: false,
    showApproveModal: false,
    selectedTaskId: null,
    selectedTaskTitle: ''
}">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">KPI Deliverables</h1>
            <p class="text-xs text-slate-400 mt-1">Review task submissions, evidence files, and approve deliverable quality scores.</p>
        </div>
        <a href="{{ route('kpi.index') }}" class="clay-btn bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 text-xs flex items-center gap-2">
            ← Back to KPI Dashboard
        </a>
    </div>

    @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-semibold">
        ✓ {{ session('success') }}
    </div>
    @endif

    <div class="clay-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-400 font-semibold border-b border-slate-100 dark:border-slate-700/60">
                        <th class="py-3 px-6">ID</th>
                        <th class="py-3 px-4">Title & Evidence</th>
                        <th class="py-3 px-4">Squad</th>
                        <th class="py-3 px-4">Assignee</th>
                        <th class="py-3 px-4">Priority</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-center">Score</th>
                        <th class="py-3 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-slate-700 dark:text-slate-300">
                    @forelse($tasks as $t)
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="py-3.5 px-6 font-mono text-slate-400">#{{ $t->id }}</td>
                        <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white">
                            <div>{{ $t->title }}</div>
                            @if($t->submissions->isNotEmpty())
                            <a href="{{ $t->submissions->first()->evidence_url }}" target="_blank" class="text-[11px] text-blue-600 hover:underline">
                                🔗 View Google Drive Evidence
                            </a>
                            @endif
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $t->squad?->code === 'SQUAD-1' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                                {{ $t->squad?->name }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 font-medium">{{ $t->assignee?->name }}</td>
                        <td class="py-3.5 px-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $t->priority === 'Urgent' ? 'bg-rose-100 text-rose-700' : ($t->priority === 'High' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700') }}">
                                {{ $t->priority }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $t->status === 'Approved' ? 'bg-emerald-50 text-emerald-700' : ($t->status === 'Submitted' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-700') }}">
                                {{ $t->status }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-center font-bold">
                            {{ $t->quality_score ? $t->quality_score . '%' : '—' }}
                        </td>
                        <td class="py-3.5 px-6 text-right space-x-2">
                            @if($t->status !== 'Approved')
                                <button type="button"
                                        @click="selectedTaskId = {{ $t->id }}; selectedTaskTitle = '{{ addslashes($t->title) }}'; showSubmitModal = true;"
                                        class="px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold text-[11px]">
                                    Submit
                                </button>
                                @if($isSupervisor)
                                <button type="button"
                                        @click="selectedTaskId = {{ $t->id }}; selectedTaskTitle = '{{ addslashes($t->title) }}'; showApproveModal = true;"
                                        class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-semibold text-[11px]">
                                    Approve
                                </button>
                                @endif
                            @else
                                <span class="text-emerald-600 font-bold text-[11px]">✓ Scored</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-400">No deliverables recorded yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-700/60">
            {{ $tasks->links() }}
        </div>
    </div>

    {{-- Modals --}}
    <div x-show="showSubmitModal" style="display: none;" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="clay-card w-full max-w-md p-6 bg-white dark:bg-slate-800" @click.away="showSubmitModal = false">
            <h3 class="text-base font-bold mb-3 text-slate-900 dark:text-white">Submit Deliverable Evidence</h3>
            <form :action="'{{ url('/kpi/tasks') }}/' + selectedTaskId + '/submit'" method="POST" class="space-y-4 text-xs">
                @csrf
                <p class="font-medium text-slate-700 dark:text-slate-300" x-text="selectedTaskTitle"></p>
                <div>
                    <label class="block font-semibold mb-1">Evidence URL (Google Drive, Cloud Storage)</label>
                    <input type="url" name="evidence_url" required class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Notes</label>
                    <textarea name="notes" rows="2" class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="showSubmitModal = false" class="px-4 py-2 rounded-xl bg-slate-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="showApproveModal" style="display: none;" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="clay-card w-full max-w-md p-6 bg-white dark:bg-slate-800" @click.away="showApproveModal = false">
            <h3 class="text-base font-bold mb-3 text-slate-900 dark:text-white">QC Score & Approval</h3>
            <form :action="'{{ url('/kpi/tasks') }}/' + selectedTaskId + '/approve'" method="POST" class="space-y-4 text-xs">
                @csrf
                <p class="font-medium text-slate-700 dark:text-slate-300" x-text="selectedTaskTitle"></p>
                <div>
                    <label class="block font-semibold mb-1">Quality Score (0 - 100%)</label>
                    <input type="number" step="0.5" min="0" max="100" name="quality_score" value="95.0" required class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Feedback</label>
                    <textarea name="feedback" rows="2" class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="showApproveModal = false" class="px-4 py-2 rounded-xl bg-slate-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-semibold">Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
