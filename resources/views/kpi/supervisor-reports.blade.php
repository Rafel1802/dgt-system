@extends('layouts.app')
@section('title', 'Squad Monthly Reports')
@section('page_title', 'Squad Monthly Reports')

@section('content')

@include('kpi.styles')

<div class="space-y-6 pb-12" x-data="{ showSubmitReportModal: false }">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Squad Monthly Reports</h1>
            <p class="text-xs text-slate-400 mt-1">Squad Leads submit monthly deliverables summary, highlights, and operational feedback.</p>
        </div>
        <div class="flex gap-2">
            <button @click="showSubmitReportModal = true" class="clay-btn bg-blue-600 text-white px-4 py-2 text-xs flex items-center gap-2">
                + Submit Squad Report
            </button>
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

    <div class="space-y-5">
        @foreach($reports as $rep)
        <div class="clay-card p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $rep->squad?->code === 'SQUAD-1' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                        {{ $rep->squad?->name }}
                    </span>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white mt-2">
                        Monthly Summary • Submitted by {{ $rep->submittedBy?->name }}
                    </h2>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold px-3 py-1 rounded-full {{ $rep->status === 'Finalized' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700' }}">
                        Status: {{ $rep->status }}
                    </span>
                    @if($isSupervisor && $rep->status !== 'Finalized')
                    <form action="{{ route('kpi.supervisor-reports.approve', $rep->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold">
                            Approve
                        </button>
                    </form>
                    <form action="{{ route('kpi.supervisor-reports.finalize', $rep->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-semibold">
                            Finalize
                        </button>
                    </form>
                    @endif
                </div>
            </div>

            <div class="mt-4 space-y-3 text-xs text-slate-700 dark:text-slate-300">
                <div>
                    <strong class="text-slate-900 dark:text-white block mb-1">Executive Summary:</strong>
                    <p class="leading-relaxed bg-slate-50 dark:bg-slate-800/40 p-3 rounded-xl">{{ $rep->summary }}</p>
                </div>
                @if($rep->strengths)
                <div>
                    <strong class="text-emerald-700 dark:text-emerald-400 block mb-1">Key Strengths & Wins:</strong>
                    <p class="leading-relaxed bg-emerald-50/50 dark:bg-emerald-900/10 p-3 rounded-xl">{{ $rep->strengths }}</p>
                </div>
                @endif
                @if($rep->improvements)
                <div>
                    <strong class="text-amber-700 dark:text-amber-400 block mb-1">Challenges & Recommendations:</strong>
                    <p class="leading-relaxed bg-amber-50/50 dark:bg-amber-900/10 p-3 rounded-xl">{{ $rep->improvements }}</p>
                </div>
                @endif
                @if($rep->supervisor_notes)
                <div>
                    <strong class="text-blue-700 dark:text-blue-400 block mb-1">Supervisor Review Notes:</strong>
                    <p class="leading-relaxed bg-blue-50/50 dark:bg-blue-900/10 p-3 rounded-xl">{{ $rep->supervisor_notes }}</p>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Submit Report Modal --}}
    <div x-show="showSubmitReportModal" style="display: none;" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="clay-card w-full max-w-lg p-6 kpi-modal-card" @click.away="showSubmitReportModal = false">
            <h3 class="text-base font-bold mb-3 text-slate-900 dark:text-white">Submit Monthly Squad Report</h3>
            <form action="{{ route('kpi.supervisor-reports.store') }}" method="POST" class="space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">Squad</label>
                    <select name="squad_id" required class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700">
                        @foreach($squads as $sq)
                        <option value="{{ $sq->id }}">{{ $sq->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Deliverables & Workflow Summary</label>
                    <textarea name="summary" rows="3" required placeholder="Completed projects, volume, QC metrics..." class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700"></textarea>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Strengths & Achievements</label>
                    <textarea name="strengths" rows="2" placeholder="Exceptional TAT, rapid design revisions..." class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700"></textarea>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Challenges & Recommendations</label>
                    <textarea name="improvements" rows="2" placeholder="Rendering queues, template additions..." class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="showSubmitReportModal = false" class="px-4 py-2 rounded-xl bg-slate-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
