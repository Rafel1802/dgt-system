@extends('layouts.app')
@section('title', 'Manage Squad Team Members')
@section('page_title', 'Squad Team Members')

@section('content')

@include('kpi.styles')

<div class="space-y-6 pb-12" x-data="{ showAddMemberModal: false }">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold kpi-card-title">Squad Team Members</h1>
            <p class="text-xs kpi-card-desc mt-1">
                Add staff to your squad from users in the system (just like board members).
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button @click="showAddMemberModal = true" class="clay-btn bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 text-xs flex items-center gap-2 shadow-md">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                </svg>
                <span>Add Member from Users</span>
            </button>
            <a href="{{ route('kpi.index') }}" class="clay-btn bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 text-xs">
                ← KPI Dashboard
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-semibold">
        ✓ {{ session('success') }}
    </div>
    @endif

    {{-- Squad Selector (Supervisor only) --}}
    @if($isSupervisor && $squads->count() > 1)
    <div class="kpi-tab-container flex items-center gap-2 p-1.5 rounded-2xl w-fit">
        @foreach($squads as $sq)
        <a href="{{ route('kpi.team.index', ['squad_id' => $sq->id]) }}"
           class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ ($currentSquad?->id === $sq->id) ? 'kpi-tab-active shadow-sm' : 'kpi-tab-inactive' }}">
            {{ $sq->name }} ({{ $sq->members->count() }} Members)
        </a>
        @endforeach
    </div>
    @endif

    {{-- Squad Info Card --}}
    <div class="clay-card p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl {{ $currentSquad?->code === 'SQUAD-1' ? 'clay-pill-blue' : 'clay-pill-purple' }} flex items-center justify-center font-extrabold text-white text-lg">
                    {{ $currentSquad?->code === 'SQUAD-1' ? 'SQ1' : 'SQ2' }}
                </div>
                <div>
                    <h2 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ $currentSquad?->name }}</h2>
                    <p class="text-xs kpi-card-desc mt-0.5">
                        Team Lead: <strong>{{ $currentSquad?->lead?->name }}</strong> ({{ $currentSquad?->lead?->email }})
                    </p>
                </div>
            </div>
            <div class="text-right">
                <span class="text-xs text-slate-400 block uppercase">Active Team Members</span>
                <span class="text-2xl font-black text-slate-800 dark:text-white">{{ $currentSquad?->members->count() }}</span>
            </div>
        </div>
    </div>

    {{-- Members Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse($currentSquad?->members ?? [] as $member)
        <div class="clay-card p-5 relative">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-slate-700 text-blue-700 dark:text-blue-300 font-bold flex items-center justify-center text-sm">
                        {{ substr($member->name, 0, 1) }}
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white text-sm">{{ $member->name }}</h3>
                        <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">{{ $member->pivot->role_title ?? 'Team Member' }}</p>
                    </div>
                </div>

                <form action="{{ route('kpi.team.remove-member', [$currentSquad->id, $member->id]) }}" method="POST"
                      onsubmit="return confirm('Remove {{ addslashes($member->name) }} from this squad?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-slate-400 hover:text-red-500 p-1" title="Remove member">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                </form>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60 text-xs text-slate-500 space-y-1">
                <div class="flex justify-between">
                    <span class="text-slate-400">Username:</span>
                    <span class="font-mono text-slate-700 dark:text-slate-300">{{ $member->username }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Joined Squad:</span>
                    <span>{{ $member->pivot->joined_date ?? 'Active' }}</span>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full clay-card p-12 text-center text-slate-400">
            <p class="text-sm">No members added to this squad yet.</p>
            <button @click="showAddMemberModal = true" class="clay-btn bg-blue-600 text-white px-4 py-2 text-xs mt-3">
                + Add Member from Users
            </button>
        </div>
        @endforelse
    </div>

    {{-- ── Modal: Add Member from Users ───────────────────────────────────── --}}
    <div x-show="showAddMemberModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="clay-card kpi-modal-card w-full max-w-md p-6" @click.away="showAddMemberModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Add Team Member to {{ $currentSquad?->name }}</h3>
                <button @click="showAddMemberModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form action="{{ route('kpi.team.add-member') }}" method="POST" class="mt-4 space-y-4 text-xs">
                @csrf
                <input type="hidden" name="squad_id" value="{{ $currentSquad?->id ?? 1 }}">

                <div>
                    <label class="block font-semibold mb-1 text-slate-700 dark:text-slate-300">Select User</label>
                    <select name="user_id" required class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700 dark:border-slate-600 text-xs">
                        <option value="">-- Choose from existing users --</option>
                        @foreach($availableUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->username }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block font-semibold mb-1 text-slate-700 dark:text-slate-300">Role Title in Squad</label>
                    <input type="text" name="role_title" placeholder="e.g. Video Editor, Graphic Designer, Motion Artist"
                           class="w-full px-3 py-2 border rounded-xl dark:bg-slate-700 dark:border-slate-600 text-xs">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                    <button type="button" @click="showAddMemberModal = false" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-semibold">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 text-white font-bold">Add to Squad</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
