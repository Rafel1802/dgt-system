@extends('layouts.app')
@section('title', 'Monthly KPI Assignments')
@section('page_title', 'Monthly KPI Targets & Weights')

@section('content')

@include('kpi.styles')

<div class="space-y-6 pb-12" x-data="{ showAssignModal: false }">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Monthly KPI Assignments</h1>
            <p class="text-xs text-slate-400 mt-1">Configure monthly deliverable targets and criteria weights for Squad Leads.</p>
        </div>
        <div class="flex gap-2">
            @if($isSupervisor)
            <button @click="showAssignModal = true" class="clay-btn bg-blue-600 text-white px-4 py-2 text-xs flex items-center gap-2">
                + Configure Monthly Target
            </button>
            @endif
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

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach($assignments as $assign)
        <div class="clay-card p-6">
            <div class="flex items-start justify-between">
                <div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $assign->squad?->code === 'SQUAD-1' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                        {{ $assign->squad?->name }}
                    </span>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white mt-2">{{ $assign->user?->name }}</h2>
                    <p class="text-xs text-slate-400">Assigned by: {{ $assign->assignedBy?->name ?? 'Supervisor' }}</p>
                </div>
                <div class="text-right">
                    <span class="text-xs text-slate-400 block">Deliverables Target</span>
                    <span class="text-2xl font-black text-blue-600 dark:text-blue-400">{{ $assign->target_deliverables }}</span>
                </div>
            </div>

            <div class="mt-6 border-t border-slate-100 dark:border-slate-700/60 pt-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Evaluation Criteria & Weights</h3>
                <div class="space-y-2.5 text-xs">
                    @forelse($assign->items as $it)
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40">
                        <div>
                            <span class="font-semibold text-slate-800 dark:text-white">{{ $it->name }}</span>
                            <span class="text-[10px] text-slate-400 block uppercase font-mono">{{ $it->pillar }}</span>
                        </div>
                        <div class="text-right">
                            <span class="font-bold text-slate-900 dark:text-white">{{ $it->weight }}%</span>
                            <span class="text-[10px] text-slate-400 block">Target: {{ $it->target_value }}{{ $it->unit }}</span>
                        </div>
                    </div>
                    @empty
                    <p class="text-slate-400 text-xs">Default criteria apply (35% Deliverables, 35% Quality, 20% TAT, 10% Teamwork).</p>
                    @endforelse
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Assign Modal --}}
    @if($isSupervisor)
    <div x-show="showAssignModal" style="display: none;" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="clay-card w-full max-w-md p-6 kpi-modal-card" @click.away="showAssignModal = false">
            <h3 class="text-base font-bold mb-3 text-slate-900 dark:text-white">Configure Monthly KPI Target</h3>
            <form action="{{ route('kpi.assignments.store') }}" method="POST" class="space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">Staff Member</label>
                    <select name="user_id" required class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700">
                        @foreach($targetUsers as $tu)
                        <option value="{{ $tu->id }}">{{ $tu->name }} ({{ $tu->username }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Squad</label>
                    <select name="squad_id" required class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700">
                        @foreach($squads as $sq)
                        <option value="{{ $sq->id }}">{{ $sq->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Target Deliverables (Count)</label>
                    <input type="number" name="target_deliverables" value="20" min="1" max="200" required class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Template Criteria</label>
                    <select name="template_id" class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700">
                        <option value="">-- Standard Digital Media Template --</option>
                        @foreach($templates as $tpl)
                        <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="showAssignModal = false" class="px-4 py-2 rounded-xl bg-slate-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
