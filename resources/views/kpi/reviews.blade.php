@extends('layouts.app')
@section('title', 'KPI Reviews & Performance Evaluation')
@section('page_title', 'KPI Reviews & Evaluations')

@section('content')

@include('kpi.styles')

<div class="space-y-6 pb-12" x-data="{
    showEvalModal: false,
    selectedAssignmentId: null,
    selectedUserName: ''
}">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Performance Reviews & Evaluations</h1>
            <p class="text-xs text-slate-400 mt-1">Four-pillar evaluation scorecard: Productivity, Quality, TAT, and Teamwork.</p>
        </div>
        <a href="{{ route('kpi.index') }}" class="clay-btn bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 text-xs">
            ← Dashboard
        </a>
    </div>

    @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-semibold">
        ✓ {{ session('success') }}
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach($reviews as $rev)
        <div class="clay-card p-6">
            <div class="flex items-start justify-between">
                <div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $rev->assignment?->squad?->code === 'SQUAD-1' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                        {{ $rev->assignment?->squad?->name }}
                    </span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white mt-2">{{ $rev->user?->name }}</h2>
                    <p class="text-xs text-slate-400">Reviewed by: {{ $rev->reviewer?->name ?? 'Supervisor' }}</p>
                </div>
                <div class="text-right">
                    <span class="text-2xl font-black text-slate-800 dark:text-white">{{ number_format($rev->overall_kpi, 1) }}%</span>
                    <span class="block text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ $rev->performance_band }}</span>
                </div>
            </div>

            <!-- Scorecard Pillars -->
            <div class="grid grid-cols-4 gap-2 mt-6 text-center text-xs">
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40">
                    <span class="text-slate-400 text-[10px] block">Productivity</span>
                    <span class="font-bold text-slate-800 dark:text-white">{{ $rev->productivity_score }}%</span>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40">
                    <span class="text-slate-400 text-[10px] block">Quality</span>
                    <span class="font-bold text-slate-800 dark:text-white">{{ $rev->quality_score }}%</span>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40">
                    <span class="text-slate-400 text-[10px] block">TAT / Speed</span>
                    <span class="font-bold text-slate-800 dark:text-white">{{ $rev->deadline_score }}%</span>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40">
                    <span class="text-slate-400 text-[10px] block">Teamwork</span>
                    <span class="font-bold text-slate-800 dark:text-white">{{ $rev->teamwork_score }}%</span>
                </div>
            </div>

            @if($rev->manager_notes)
            <div class="mt-4 p-3 rounded-xl bg-blue-50/60 dark:bg-blue-900/20 text-xs text-blue-900 dark:text-blue-200">
                <strong>Supervisor Feedback:</strong> {{ $rev->manager_notes }}
            </div>
            @endif

            <div class="mt-5 pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs">
                <span class="text-slate-400">Status: <strong class="text-slate-700 dark:text-slate-200">{{ $rev->status }}</strong></span>
                @if($isSupervisor && $rev->status !== 'Finalized')
                <div class="flex gap-2">
                    <button type="button"
                            @click="selectedAssignmentId = {{ $rev->kpi_assignment_id }}; selectedUserName = '{{ addslashes($rev->user?->name) }}'; showEvalModal = true;"
                            class="px-3 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold text-xs">
                        Re-Evaluate
                    </button>
                    <form action="{{ route('kpi.reviews.finalize', $rev->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs">
                            Finalize KPI
                        </button>
                    </form>
                </div>
                @else
                <span class="text-emerald-600 font-bold text-xs">✓ Finalized</span>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Evaluate Modal --}}
    @if($isSupervisor)
    <div x-show="showEvalModal" style="display: none;" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="clay-card w-full max-w-md p-6 kpi-modal-card" @click.away="showEvalModal = false">
            <h3 class="text-base font-bold mb-3 text-slate-900 dark:text-white">Evaluate Performance</h3>
            <form :action="'{{ url('/kpi/assignments') }}/' + selectedAssignmentId + '/evaluate'" method="POST" class="space-y-4 text-xs">
                @csrf
                <p class="font-medium text-slate-700 dark:text-slate-300" x-text="selectedUserName"></p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold mb-1">Productivity Score (35%)</label>
                        <input type="number" step="0.5" min="0" max="100" name="productivity_score" value="95.0" required class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Quality Score (35%)</label>
                        <input type="number" step="0.5" min="0" max="100" name="quality_score" value="95.0" required class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold mb-1">TAT / Speed (20%)</label>
                        <input type="number" step="0.5" min="0" max="100" name="deadline_score" value="94.0" required class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Teamwork (10%)</label>
                        <input type="number" step="0.5" min="0" max="100" name="teamwork_score" value="95.0" required class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700">
                    </div>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Manager Notes & Comments</label>
                    <textarea name="manager_notes" rows="3" class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="showEvalModal = false" class="px-4 py-2 rounded-xl bg-slate-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold">Calculate & Submit</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
