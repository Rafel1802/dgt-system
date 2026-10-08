@extends('layouts.app')
@section('title', 'Manage Squad Team Members')
@section('page_title', 'Squad Team Members')

@section('content')

@include('kpi.styles')

<div class="space-y-6 pb-12 text-slate-800 dark:text-slate-100" x-data="{
    showAddMemberModal: false,
    availableUsersList: {{ json_encode($availableUsers->map(fn($u) => [
        'id' => $u->id,
        'name' => $u->name,
        'username' => $u->username ?? '',
        'email' => $u->email,
        'avatar_url' => $u->avatar_url,
        'team_role' => $u->team_role ?? 'Staff Member',
    ])) }},
    selectedUser: null,
    userDropdownOpen: false,
    userSearch: '',
    filteredUsers() {
        if (!this.userSearch) return this.availableUsersList;
        let q = this.userSearch.toLowerCase();
        return this.availableUsersList.filter(u =>
            (u.name && u.name.toLowerCase().includes(q)) ||
            (u.username && u.username.toLowerCase().includes(q)) ||
            (u.email && u.email.toLowerCase().includes(q)) ||
            (u.team_role && u.team_role.toLowerCase().includes(q))
        );
    },
    selectUser(u) {
        this.selectedUser = u;
        this.userDropdownOpen = false;
        if (!this.newRoleTitle && u.team_role) {
            this.newRoleTitle = u.team_role;
        }
    },
    showDeleteModal: false,
    memberToDelete: null,
    confirmDelete(id, name) {
        this.memberToDelete = { id: id, name: name };
        this.showDeleteModal = true;
    },
    // Add Member Work Types
    newRoleTitle: '',
    newWorkTypes: [],
    customNewWorkType: '',
    toggleNewWorkType(type) {
        if (this.newWorkTypes.includes(type)) {
            this.newWorkTypes = this.newWorkTypes.filter(t => t !== type);
        } else {
            this.newWorkTypes.push(type);
        }
    },
    addNewCustomWorkType() {
        let t = (this.customNewWorkType || '').trim();
        if (t && !this.newWorkTypes.includes(t)) {
            this.newWorkTypes.push(t);
            this.customNewWorkType = '';
        }
    },
    // Edit Member Role & Work Types
    showEditRoleModal: false,
    editMemberId: null,
    editMemberName: '',
    editRoleTitle: '',
    editWorkTypes: [],
    customEditWorkType: '',
    openEditRoleModal(id, name, roleTitle, workTypes) {
        this.editMemberId = id;
        this.editMemberName = name;
        this.editRoleTitle = roleTitle || '';
        this.editWorkTypes = Array.isArray(workTypes) ? [...workTypes] : [];
        this.customEditWorkType = '';
        this.showEditRoleModal = true;
    },
    toggleEditWorkType(type) {
        if (this.editWorkTypes.includes(type)) {
            this.editWorkTypes = this.editWorkTypes.filter(t => t !== type);
        } else {
            this.editWorkTypes.push(type);
        }
    },
    addEditCustomWorkType() {
        let t = (this.customEditWorkType || '').trim();
        if (t && !this.editWorkTypes.includes(t)) {
            this.editWorkTypes.push(t);
            this.customEditWorkType = '';
        }
    }
}">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-700/60 shadow-xs dark:shadow-lg backdrop-blur-xl transition-colors">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-sky-400 animate-pulse"></span>
                <span>Squad Team Members</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Manage roles, work types (Video Editor, Graphic, Website, Social Media, etc.), and squad members.
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
            <a href="{{ route('kpi.index') }}" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold border border-slate-200 dark:border-slate-700 transition-all shadow-xs">
                ← KPI Dashboard
            </a>
        </div>
    </div>

    {{-- Feedback Messages --}}
    @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
        <span>{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-bold flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    {{-- Squad Selector (For Supervisor/Admin) --}}
    @if($isSupervisor && $squads->count() > 1)
    <div class="flex items-center gap-2 p-1.5 rounded-2xl bg-slate-900/90 border border-slate-700/60 w-fit">
        @foreach($squads as $squad)
        <a href="{{ route('kpi.team.index', ['squad_id' => $squad->id]) }}"
           class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ ($currentSquad?->id == $squad->id) ? 'bg-sky-500 text-white shadow-md' : 'text-slate-400 hover:text-white' }}">
            {{ $squad->name }} ({{ $squad->members->count() }})
        </a>
        @endforeach
    </div>
    @endif

    {{-- Squad Info Banner --}}
    <div class="p-5 rounded-2xl bg-gradient-to-r from-sky-50 via-blue-50 to-indigo-50 dark:from-slate-900 dark:via-slate-800 dark:to-slate-900 border border-sky-200 dark:border-slate-700/60 shadow-xs dark:shadow-lg transition-colors">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="text-[11px] font-bold tracking-wider text-sky-400 uppercase">Active Squad</span>
                <h2 class="text-lg font-black text-slate-900 dark:text-white mt-0.5">{{ $currentSquad?->name }}</h2>
                <p class="text-xs text-slate-400 mt-1">Lead: <strong class="text-white">{{ $currentSquad?->lead?->name ?? 'Lead' }}</strong> &bull; {{ $currentSquad?->description }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-400 font-semibold">Total Members:</span>
                <span class="text-2xl font-black text-slate-900 dark:text-white">{{ $currentSquad?->members->count() }}</span>
            </div>
        </div>
    </div>

    {{-- Members Grid with Real Avatars & Work Types --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($currentSquad?->members ?? [] as $member)
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/60 hover:border-sky-500/40 shadow-xs dark:shadow-md hover:shadow-md transition-all flex flex-col justify-between">
            <div>
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        {{-- Real Staff Avatar Image --}}
                        <img src="{{ $member->avatar_url }}" alt="{{ $member->name }}"
                             class="w-12 h-12 rounded-2xl object-cover border border-slate-700 shadow-md flex-shrink-0"
                             onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode($member->name) }}&background=0284c7&color=fff';">
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white text-sm hover:text-sky-600 dark:hover:text-sky-300 transition-colors">{{ $member->name }}</h3>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="px-2.5 py-0.5 rounded-lg bg-blue-600 text-white font-extrabold text-xs shadow-xs">
                                    {{ $member->pivot->role_title ?? 'Team Member' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-1">
                        {{-- Download Staff KPI PDF Certificate --}}
                        <a href="{{ route('kpi.staff.pdf', ['user_id' => $member->id]) }}" target="_blank"
                           class="text-slate-400 hover:text-sky-300 p-1.5 rounded-xl hover:bg-sky-500/10 transition-colors cursor-pointer"
                           title="Download {{ $member->name }} KPI PDF Certificate">
                            <svg class="w-4 h-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </a>

                        {{-- Edit Role & Work Types Button --}}
                        <button type="button" 
                                @click="openEditRoleModal({{ $member->id }}, '{{ addslashes($member->name) }}', '{{ addslashes($member->pivot->role_title ?? '') }}', {{ json_encode($member->pivot_work_types) }})"
                                class="text-slate-400 hover:text-sky-400 p-1.5 rounded-xl hover:bg-sky-500/10 transition-colors cursor-pointer"
                                title="Edit Role Title & Work Types">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                        </button>

                        {{-- Delete Button (Opens custom confirmation modal) --}}
                        <button type="button" @click="confirmDelete({{ $member->id }}, '{{ addslashes($member->name) }}')"
                                class="text-slate-400 hover:text-rose-400 p-1.5 rounded-xl hover:bg-rose-500/10 transition-colors cursor-pointer"
                                title="Remove member from squad">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Work Types Badges --}}
                <div class="mt-3.5">
                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Work Types / Parts:</span>
                    <div class="flex flex-wrap gap-1.5 mt-1.5">
                        @forelse($member->pivot_work_types as $type)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10.5px] font-semibold bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-200 border border-sky-200 dark:border-sky-800/40 shadow-xs">
                                {{ $type }}
                            </span>
                        @empty
                            <button type="button" 
                                    @click="openEditRoleModal({{ $member->id }}, '{{ addslashes($member->name) }}', '{{ addslashes($member->pivot->role_title ?? '') }}', [])"
                                    class="text-[11px] text-slate-500 hover:text-sky-400 italic cursor-pointer transition-colors flex items-center gap-1">
                                <span>+</span> Assign work types
                            </button>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-sky-900/50 text-xs text-slate-500 dark:text-slate-400 space-y-1.5">
                <div class="flex justify-between items-center">
                    <span>Username:</span>
                    <span class="font-mono text-sky-700 dark:text-sky-300 font-semibold bg-sky-50 dark:bg-sky-950/40 px-2 py-0.5 rounded-md border border-sky-200 dark:border-sky-800/40">{{ $member->username ?? $member->email ?? 'N/A' }}</span>
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

        {{-- ── Modal: Add Member from Users (Enhanced Height & Theme Adaptive) ───────────────────────────────────── --}}
    <div x-show="showAddMemberModal" style="display: none;"
         class="fixed inset-0 z-[100] overflow-y-auto kpi-modal-overlay flex items-center justify-center p-2.5 sm:p-4 md:p-6">
        <div class="w-full max-w-3xl min-h-0 md:min-h-[560px] flex flex-col justify-between bg-white dark:bg-[#162344] text-slate-800 dark:text-slate-100 rounded-2xl sm:rounded-3xl border border-slate-200 dark:border-sky-500/35 shadow-2xl dark:shadow-[0_25px_70px_rgba(0,0,0,0.85),0_0_40px_rgba(14,165,233,0.18)] overflow-hidden my-auto max-h-[92vh]"
             @click.away="showAddMemberModal = false">
            <div class="p-4 sm:p-6 border-b border-slate-200 dark:border-sky-900/50 bg-slate-100/90 dark:bg-[#1a2d56] flex items-center justify-between flex-shrink-0 gap-3">
                <div class="flex items-center gap-3 sm:gap-3.5 min-w-0">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-gradient-to-br from-sky-500/20 to-blue-600/30 border border-sky-400/40 flex items-center justify-center text-sky-500 dark:text-sky-400 shadow-sm flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white tracking-tight truncate">Add Team Member</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Assign user and parts of role to <strong class="text-sky-600 dark:text-sky-300">{{ $currentSquad?->name }}</strong></p>
                    </div>
                </div>
                <button type="button" @click="showAddMemberModal = false" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white border border-slate-200 dark:border-slate-700 flex items-center justify-center transition-all cursor-pointer font-bold flex-shrink-0">✕</button>
            </div>

            <form action="{{ route('kpi.team.add-member') }}" method="POST" class="p-4 sm:p-7 space-y-5 sm:space-y-6 text-xs flex-1 min-h-0 flex flex-col justify-between overflow-y-auto">
                @csrf
                <input type="hidden" name="squad_id" value="{{ $currentSquad?->id ?? 1 }}">

                <div class="space-y-6">
                    {{-- User & Role Row --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="relative" @click.outside="userDropdownOpen = false">
                            <label class="block font-bold mb-2 text-slate-700 dark:text-slate-300 text-xs sm:text-sm">
                                Select Member from Users <span class="text-rose-500">*</span>
                            </label>
                            <input type="hidden" name="user_id" :value="selectedUser ? selectedUser.id : ''" required>

                            {{-- Trigger Button (Showing Avatar + Name + Role Badge like in Card/Board) --}}
                            <button type="button" @click="userDropdownOpen = !userDropdownOpen; if(userDropdownOpen) $nextTick(() => $refs.userSearchInput?.focus())"
                                    class="kpi-member-btn group">
                                <template x-if="!selectedUser">
                                    <div class="flex items-center gap-3 text-slate-400 dark:text-slate-500">
                                        <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-400 flex-shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                            </svg>
                                        </div>
                                        <span class="font-medium text-xs sm:text-sm">-- Choose member with profile & role --</span>
                                    </div>
                                </template>
                                <template x-if="selectedUser">
                                    <div class="flex items-center gap-3 text-left min-w-0">
                                        <img :src="selectedUser.avatar_url" :alt="selectedUser.name"
                                             class="w-9 h-9 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shadow-xs flex-shrink-0"
                                             x-on:error="$event.target.src='https://ui-avatars.com/api/?name='+encodeURIComponent(selectedUser.name)+'&background=0284c7&color=fff'">
                                        <div class="min-w-0">
                                            <div class="font-bold text-slate-900 dark:text-white text-xs sm:text-sm truncate" x-text="selectedUser.name"></div>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span class="text-[11px] text-sky-600 dark:text-sky-400 font-mono" x-text="selectedUser.username ? '@' + selectedUser.username : selectedUser.email"></span>
                                                <span class="text-[10px] px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-semibold" x-text="selectedUser.team_role || 'Staff'"></span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <div class="flex items-center gap-1.5 text-slate-400 flex-shrink-0 ml-2">
                                    <svg class="w-4 h-4 transition-transform duration-200" :class="userDropdownOpen ? 'rotate-180 text-sky-500' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </div>
                            </button>

                            {{-- Dropdown Card Panel --}}
                            <div x-show="userDropdownOpen" style="display: none;"
                                 class="kpi-member-dropdown p-2"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                                 x-transition:enter-end="opacity-100 translate-y-0 scale-100">
                                
                                {{-- Search Input inside Dropdown --}}
                                <div class="p-1 mb-2">
                                    <div class="relative">
                                        <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                        </svg>
                                        <input type="text" x-model="userSearch" x-ref="userSearchInput"
                                               placeholder="Search staff by name or username..."
                                               class="w-full h-9 pl-9 pr-3 rounded-lg bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-sky-900/50 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-sky-500">
                                    </div>
                                </div>

                                {{-- Users List (Avatar, Name, Username, Role like in board card) --}}
                                <div class="max-h-56 overflow-y-auto space-y-1 p-0.5">
                                    <template x-for="u in filteredUsers()" :key="u.id">
                                        <div @click="selectUser(u)"
                                             class="flex items-center justify-between p-2 rounded-xl cursor-pointer transition-all hover:bg-sky-50 dark:hover:bg-sky-950/50"
                                             :class="selectedUser?.id === u.id ? 'bg-sky-100/80 dark:bg-sky-900/40 border border-sky-300 dark:border-sky-700/60' : 'border border-transparent'">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <img :src="u.avatar_url" :alt="u.name"
                                                     class="w-9 h-9 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shadow-xs flex-shrink-0"
                                                     x-on:error="$event.target.src='https://ui-avatars.com/api/?name='+encodeURIComponent(u.name)+'&background=0284c7&color=fff'">
                                                <div class="min-w-0 text-left">
                                                    <div class="font-bold text-slate-900 dark:text-white text-xs truncate" x-text="u.name"></div>
                                                    <div class="flex items-center gap-1.5 mt-0.5">
                                                        <span class="text-[10.5px] text-sky-600 dark:text-sky-400 font-mono" x-text="u.username ? '@' + u.username : u.email"></span>
                                                        <span class="text-[9.5px] px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400" x-text="u.team_role || 'Staff'"></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div x-show="selectedUser?.id === u.id" class="text-sky-600 dark:text-sky-400 font-black text-sm flex-shrink-0 mr-1.5">
                                                ✓
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="filteredUsers().length === 0" class="py-6 text-center text-xs text-slate-400">
                                        No matching users found
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold mb-2 text-slate-700 dark:text-slate-300 text-xs sm:text-sm">Role Title in Squad <span class="text-rose-500">*</span></label>
                            <input type="text" name="role_title" x-model="newRoleTitle" required placeholder="e.g. Video & Web Specialist, Motion Graphic Artist"
                                   class="w-full h-12 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:bg-white text-xs sm:text-sm shadow-xs">
                        </div>
                    </div>

                    {{-- Select Work Types --}}
                    <div class="pt-2">
                        <div class="flex items-center justify-between mb-2">
                            <label class="font-bold text-slate-700 dark:text-slate-300 text-xs sm:text-sm">Select Work Types (Parts of Role)</label>
                            <span class="text-xs text-sky-600 dark:text-sky-400 font-mono font-bold bg-sky-50 dark:bg-sky-950/60 px-2.5 py-1 rounded-lg border border-sky-200 dark:border-sky-800/40" x-text="newWorkTypes.length + ' selected'"></span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Click all parts of work that this team member will be responsible for in the squad:</p>

                        <div class="flex flex-wrap gap-2.5 mb-4">
                            <template x-for="item in [
                                { name: 'Video Editor', icon: '🎬' },
                                { name: 'Graphic', icon: '🎨' },
                                { name: 'Website', icon: '🌐' },
                                { name: 'Social Media', icon: '📱' },
                                { name: 'Motion Graphics', icon: '🎥' },
                                { name: 'Content Creation', icon: '✍️' },
                                { name: 'Quality Control (QC)', icon: '🔍' },
                                { name: 'Branding & Visuals', icon: '🏷️' },
                                { name: 'Technical Support', icon: '⚙️' }
                            ]" :key="item.name">
                                <button type="button" 
                                        @click="toggleNewWorkType(item.name)"
                                        :class="newWorkTypes.includes(item.name) ? 'kpi-work-pill active' : 'kpi-work-pill'"
                                        class="transition-all select-none">
                                    <span x-text="item.icon"></span>
                                    <span x-text="item.name"></span>
                                    <span x-show="newWorkTypes.includes(item.name)" class="text-white font-extrabold ml-1">✓</span>
                                </button>
                            </template>
                        </div>

                        {{-- Custom Types Display --}}
                        <div class="flex flex-wrap gap-2 mb-3" x-show="newWorkTypes.filter(t => !['Video Editor', 'Graphic', 'Website', 'Social Media', 'Motion Graphics', 'Content Creation', 'Quality Control (QC)', 'Branding & Visuals', 'Technical Support'].includes(t)).length > 0">
                            <template x-for="custom in newWorkTypes.filter(t => !['Video Editor', 'Graphic', 'Website', 'Social Media', 'Motion Graphics', 'Content Creation', 'Quality Control (QC)', 'Branding & Visuals', 'Technical Support'].includes(t))" :key="custom">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/40 shadow-xs">
                                    <span>🏷️</span>
                                    <span x-text="custom"></span>
                                    <button type="button" @click="toggleNewWorkType(custom)" class="text-rose-500 hover:text-rose-700 dark:text-rose-400 cursor-pointer text-sm leading-none ml-1 font-bold">×</button>
                                </span>
                            </template>
                        </div>

                        {{-- Custom Input --}}
                        <div class="flex gap-2.5">
                            <input type="text" x-model="customNewWorkType" @keydown.enter.prevent="addNewCustomWorkType()" 
                                   placeholder="+ Add other custom work type (e.g. Copywriting, Audio Engineer)..." 
                                   class="flex-1 h-11 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:bg-white shadow-xs">
                            <button type="button" @click="addNewCustomWorkType()" 
                                    class="h-11 px-5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-sky-600 dark:text-sky-400 text-xs font-bold border border-slate-200 dark:border-slate-700 cursor-pointer transition-colors shadow-xs">
                                + Add Type
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Hidden Inputs for Work Types --}}
                <template x-for="wt in newWorkTypes" :key="wt">
                    <input type="hidden" name="work_types[]" :value="wt">
                </template>

                <div class="flex justify-end gap-3 pt-5 border-t border-slate-200 dark:border-sky-900/50 mt-6 flex-shrink-0">
                    <button type="button" @click="showAddMemberModal = false" class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold cursor-pointer border border-slate-200 dark:border-slate-700">Cancel</button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white font-bold shadow-lg shadow-sky-500/25 cursor-pointer">Add to Squad</button>
                </div>
            </form>
        </div>
    </div>

        {{-- ── Modal: Edit Staff Role & Work Types (Enhanced & Theme Adaptive) ────────────────────────────── --}}
    <div x-show="showEditRoleModal" style="display: none;"
         class="fixed inset-0 z-[100] overflow-y-auto kpi-modal-overlay flex items-center justify-center p-2.5 sm:p-4 md:p-6">
        <div class="w-full max-w-3xl min-h-0 md:min-h-[500px] flex flex-col justify-between bg-white dark:bg-[#162344] text-slate-800 dark:text-slate-100 rounded-2xl sm:rounded-3xl border border-slate-200 dark:border-sky-500/35 shadow-2xl dark:shadow-[0_25px_70px_rgba(0,0,0,0.85),0_0_40px_rgba(14,165,233,0.18)] overflow-hidden my-auto max-h-[92vh]"
             @click.away="showEditRoleModal = false">
            <div class="p-4 sm:p-6 border-b border-slate-200 dark:border-sky-900/50 bg-slate-100/90 dark:bg-[#1a2d56] flex items-center justify-between flex-shrink-0 gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-sky-500/20 to-blue-600/30 border border-sky-400/40 flex items-center justify-center text-sky-500 dark:text-sky-400 shadow-sm flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white truncate">Edit Role & Work Types</h3>
                        <p class="text-xs text-sky-600 dark:text-sky-400 font-semibold truncate" x-text="editMemberName"></p>
                    </div>
                </div>
                <button type="button" @click="showEditRoleModal = false" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white border border-slate-200 dark:border-slate-700/80 flex items-center justify-center transition-all cursor-pointer font-bold flex-shrink-0">✕</button>
            </div>

            <form action="{{ route('kpi.team.update-member-role') }}" method="POST" class="p-4 sm:p-7 space-y-5 sm:space-y-6 text-xs flex-1 min-h-0 flex flex-col justify-between overflow-y-auto">
                @csrf
                <input type="hidden" name="squad_id" value="{{ $currentSquad?->id ?? 1 }}">
                <input type="hidden" name="user_id" :value="editMemberId">

                <div class="space-y-6">
                    <div>
                        <label class="block font-bold mb-2 text-slate-700 dark:text-slate-300 text-xs sm:text-sm">
                            Role Title in Squad <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="role_title" x-model="editRoleTitle" required
                               placeholder="e.g. Video & Web Specialist, Motion Graphic Artist"
                               class="w-full h-12 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-semibold text-xs sm:text-sm focus:outline-none focus:border-sky-500 focus:bg-white shadow-xs">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="font-bold text-slate-700 dark:text-slate-300 text-xs sm:text-sm">Select Work Types (Parts of Role)</label>
                            <span class="text-xs text-sky-600 dark:text-sky-400 font-mono font-bold bg-sky-50 dark:bg-sky-950/60 px-2.5 py-1 rounded-lg border border-sky-200 dark:border-sky-800/40" x-text="editWorkTypes.length + ' selected'"></span>
                        </div>

                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Click to select all parts of work that apply to this staff member:</p>

                        <div class="flex flex-wrap gap-2.5 mb-4">
                            <template x-for="item in [
                                { name: 'Video Editor', icon: '🎬' },
                                { name: 'Graphic', icon: '🎨' },
                                { name: 'Website', icon: '🌐' },
                                { name: 'Social Media', icon: '📱' },
                                { name: 'Motion Graphics', icon: '🎥' },
                                { name: 'Content Creation', icon: '✍️' },
                                { name: 'Quality Control (QC)', icon: '🔍' },
                                { name: 'Branding & Visuals', icon: '🏷️' },
                                { name: 'Technical Support', icon: '⚙️' }
                            ]" :key="item.name">
                                <button type="button" 
                                        @click="toggleEditWorkType(item.name)"
                                        :class="editWorkTypes.includes(item.name) ? 'kpi-work-pill active' : 'kpi-work-pill'"
                                        class="transition-all select-none">
                                    <span x-text="item.icon"></span>
                                    <span x-text="item.name"></span>
                                    <span x-show="editWorkTypes.includes(item.name)" class="text-white font-extrabold ml-1">✓</span>
                                </button>
                            </template>
                        </div>

                        <div class="flex flex-wrap gap-2 mb-3" x-show="editWorkTypes.filter(t => !['Video Editor', 'Graphic', 'Website', 'Social Media', 'Motion Graphics', 'Content Creation', 'Quality Control (QC)', 'Branding & Visuals', 'Technical Support'].includes(t)).length > 0">
                            <template x-for="custom in editWorkTypes.filter(t => !['Video Editor', 'Graphic', 'Website', 'Social Media', 'Motion Graphics', 'Content Creation', 'Quality Control (QC)', 'Branding & Visuals', 'Technical Support'].includes(t))" :key="custom">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/40 shadow-xs">
                                    <span>🏷️</span>
                                    <span x-text="custom"></span>
                                    <button type="button" @click="toggleEditWorkType(custom)" class="text-rose-500 hover:text-rose-700 dark:text-rose-400 cursor-pointer text-sm leading-none ml-1 font-bold">×</button>
                                </span>
                            </template>
                        </div>

                        <div class="flex gap-2.5">
                            <input type="text" x-model="customEditWorkType" @keydown.enter.prevent="addEditCustomWorkType()" 
                                   placeholder="+ Add custom work type..." 
                                   class="flex-1 h-11 px-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:bg-white shadow-xs">
                            <button type="button" @click="addEditCustomWorkType()" 
                                    class="h-11 px-5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-sky-600 dark:text-sky-400 text-xs font-bold border border-slate-200 dark:border-slate-700 cursor-pointer transition-colors shadow-xs">
                                + Add Type
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Hidden Inputs for Work Types --}}
                <template x-for="wt in editWorkTypes" :key="wt">
                    <input type="hidden" name="work_types[]" :value="wt">
                </template>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-sky-900/50 mt-4 flex-shrink-0">
                    <button type="button" @click="showEditRoleModal = false" class="px-4 sm:px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold cursor-pointer border border-slate-200 dark:border-slate-700 text-xs sm:text-sm">Cancel</button>
                    <button type="submit" class="px-5 sm:px-6 py-2.5 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white font-bold shadow-lg shadow-sky-500/25 cursor-pointer text-xs sm:text-sm">Save Role & Work Types</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Modal: Custom Delete Confirmation (Replaces raw browser alert) ─── --}}
    <div x-show="showDeleteModal" style="display: none;"
         class="fixed inset-0 z-[100] overflow-y-auto kpi-modal-overlay flex items-center justify-center p-2.5 sm:p-4 md:p-6">
        <div class="w-full max-w-md bg-[#0f172a] text-slate-100 rounded-2xl sm:rounded-3xl border border-rose-500/30 shadow-[0_25px_60px_rgba(0,0,0,0.9),0_0_35px_rgba(244,63,94,0.2)] p-5 sm:p-6 text-center my-auto"
             @click.away="showDeleteModal = false">
            
            <div class="w-12 h-12 rounded-2xl bg-rose-500/20 border border-rose-500/40 text-rose-400 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
            </div>

            <h3 class="text-base font-extrabold text-white">Remove Team Member?</h3>
            <p class="text-xs text-slate-400 mt-2">
                Are you sure you want to remove <strong class="text-rose-400 font-bold" x-text="memberToDelete ? memberToDelete.name : ''"></strong> from this squad?
            </p>

            <div class="mt-6 flex items-center justify-center gap-3">
                <button type="button" @click="showDeleteModal = false"
                        class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold cursor-pointer transition-colors">
                    Cancel
                </button>
                <form :action="'{{ url('/kpi/team/' . ($currentSquad?->id ?? 1) . '/members') }}/' + (memberToDelete ? memberToDelete.id : '')"
                      method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-rose-500 to-red-600 hover:from-rose-400 hover:to-red-500 text-white text-xs font-bold shadow-lg shadow-rose-500/30 cursor-pointer transition-all">
                        Yes, Remove Member
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
