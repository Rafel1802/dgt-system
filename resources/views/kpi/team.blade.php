@extends('layouts.app')
@section('title', 'Manage Squad Team Members')
@section('page_title', 'Squad Team Members')

@section('content')

@include('kpi.styles')

<div class="space-y-6 pb-12 text-slate-100" x-data="{
    showAddMemberModal: false,
    showDeleteModal: false,
    memberToDelete: null,
    confirmDelete(id, name) {
        this.memberToDelete = { id: id, name: name };
        this.showDeleteModal = true;
    }
}">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-slate-900/90 border border-slate-700/60 shadow-lg backdrop-blur-xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-sky-400 animate-pulse"></span>
                <span>Squad Team Members</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Add staff to your squad from users in the system (just like board members).
            </p>
        </div>
        <div class="flex items-center gap-2.5">
            <button type="button" @click="showAddMemberModal = true"
                    class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white text-xs font-bold flex items-center gap-2 shadow-lg shadow-sky-500/20 transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                </svg>
                <span>Add Member from Users</span>
            </button>
            <a href="{{ route('kpi.index') }}" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold border border-slate-700 transition-all">
                ← KPI Dashboard
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 text-xs font-semibold flex items-center gap-2">
        <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    {{-- Squad Selector (Supervisor only) --}}
    @if($isSupervisor && $squads->count() > 1)
    <div class="flex items-center gap-2 p-1.5 rounded-2xl bg-slate-900/90 border border-slate-700/60 w-fit">
        @foreach($squads as $sq)
        <a href="{{ route('kpi.team.index', ['squad_id' => $sq->id]) }}"
           class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ ($currentSquad?->id === $sq->id) ? 'bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white' }}">
            {{ $sq->name }} ({{ $sq->members->count() }} Members)
        </a>
        @endforeach
    </div>
    @endif

    {{-- Squad Info Banner --}}
    <div class="p-5 sm:p-6 rounded-2xl bg-slate-900/80 border border-slate-700/60 shadow-md backdrop-blur-xl">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl {{ $currentSquad?->code === 'SQUAD-1' ? 'bg-gradient-to-br from-blue-600 to-indigo-700' : 'bg-gradient-to-br from-purple-600 to-pink-700' }} flex items-center justify-center font-extrabold text-white text-base shadow-md">
                    {{ $currentSquad?->code === 'SQUAD-1' ? 'SQ1' : 'SQ2' }}
                </div>
                <div>
                    <h2 class="text-lg font-extrabold text-white">{{ $currentSquad?->name }}</h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Team Lead: <strong class="text-sky-300">{{ $currentSquad?->lead?->name }}</strong> ({{ $currentSquad?->lead?->email }})
                    </p>
                </div>
            </div>
            <div class="text-right">
                <span class="text-[11px] text-slate-400 block uppercase font-bold tracking-wider">Active Team Members</span>
                <span class="text-2xl font-black text-white">{{ $currentSquad?->members->count() }}</span>
            </div>
        </div>
    </div>

    {{-- Members Grid with Real Avatars --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($currentSquad?->members ?? [] as $member)
        <div class="p-5 rounded-2xl bg-slate-900/80 border border-slate-700/60 hover:border-sky-500/40 shadow-md transition-all">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    {{-- Real Staff Avatar Image --}}
                    <img src="{{ $member->avatar_url }}" alt="{{ $member->name }}"
                         class="w-11 h-11 rounded-2xl object-cover border border-slate-700 shadow-md flex-shrink-0"
                         onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode($member->name) }}&background=0284c7&color=fff';">
                    <div>
                        <h3 class="font-bold text-white text-sm hover:text-sky-300 transition-colors">{{ $member->name }}</h3>
                        <p class="text-xs text-sky-400 font-semibold">{{ $member->pivot->role_title ?? 'Team Member' }}</p>
                    </div>
                </div>

                {{-- Delete Button (Opens custom confirmation modal) --}}
                <button type="button" @click="confirmDelete({{ $member->id }}, '{{ addslashes($member->name) }}')"
                        class="text-slate-400 hover:text-rose-400 p-1.5 rounded-xl hover:bg-rose-500/10 transition-colors cursor-pointer"
                        title="Remove member from squad">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-800 text-xs text-slate-400 space-y-1.5">
                <div class="flex justify-between">
                    <span>Username:</span>
                    <span class="font-mono text-slate-300">@{{ $member->username }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Joined Squad:</span>
                    <span class="text-slate-300">{{ $member->pivot->joined_date ?? 'Active' }}</span>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full p-12 rounded-2xl bg-slate-900/80 border border-slate-700/60 text-center text-slate-400">
            <p class="text-sm">No members added to this squad yet.</p>
            <button @click="showAddMemberModal = true" class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs mt-3 cursor-pointer">
                + Add Member from Users
            </button>
        </div>
        @endforelse
    </div>

    {{-- ── Modal: Add Member from Users ───────────────────────────────────── --}}
    <div x-show="showAddMemberModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-[#0f172a] text-slate-100 rounded-3xl border border-sky-500/30 shadow-[0_25px_60px_rgba(0,0,0,0.9),0_0_35px_rgba(14,165,233,0.2)] overflow-hidden"
             @click.away="showAddMemberModal = false">
            <div class="p-5 border-b border-slate-800 bg-slate-900/80 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-extrabold text-white">Add Team Member</h3>
                    <p class="text-xs text-slate-400">To {{ $currentSquad?->name }}</p>
                </div>
                <button type="button" @click="showAddMemberModal = false" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700/80 flex items-center justify-center transition-all cursor-pointer">✕</button>
            </div>

            <form action="{{ route('kpi.team.add-member') }}" method="POST" class="p-5 space-y-4 text-xs">
                @csrf
                <input type="hidden" name="squad_id" value="{{ $currentSquad?->id ?? 1 }}">

                <div>
                    <label class="block font-bold mb-1 text-slate-300">Select User</label>
                    <select name="user_id" required class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-semibold focus:outline-none focus:border-sky-500">
                        <option value="">-- Choose from existing users --</option>
                        @foreach($availableUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} (@{{ $u->username }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block font-bold mb-1 text-slate-300">Role Title in Squad</label>
                    <input type="text" name="role_title" placeholder="e.g. Video Editor, Graphic Designer, Motion Artist"
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-sky-500">
                </div>

                <div class="flex justify-end gap-2.5 pt-3 border-t border-slate-800">
                    <button type="button" @click="showAddMemberModal = false" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold cursor-pointer">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white font-bold shadow-lg shadow-sky-500/30 cursor-pointer">Add to Squad</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Modal: Custom Delete Confirmation (Replaces raw browser alert) ─── --}}
    <div x-show="showDeleteModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
        <div class="w-full max-w-sm bg-[#0f172a] text-slate-100 rounded-3xl border border-rose-500/30 shadow-[0_25px_60px_rgba(0,0,0,0.9),0_0_35px_rgba(244,63,94,0.2)] p-6 text-center"
             @click.away="showDeleteModal = false">
            
            <div class="w-12 h-12 rounded-2xl bg-rose-500/20 border border-rose-500/40 text-rose-400 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
            </div>

            <h3 class="text-base font-bold text-white mb-1.5">Remove Squad Member</h3>
            <p class="text-xs text-slate-400 mb-6 leading-relaxed">
                Remove <strong class="text-rose-300 font-bold" x-text="memberToDelete?.name"></strong> from {{ $currentSquad?->name }}?<br/>
                They will be excluded from future monthly KPI evaluations for this squad.
            </p>

            <form :action="'{{ url('/kpi/team/' . ($currentSquad?->id ?? 1) . '/members') }}/' + (memberToDelete?.id || '')" method="POST" class="flex items-center justify-center gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="showDeleteModal = false"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold border border-slate-700 transition-all cursor-pointer">
                    Cancel
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-xl bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white text-xs font-bold shadow-lg shadow-rose-600/30 transition-all cursor-pointer">
                    Yes, Remove Member
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
