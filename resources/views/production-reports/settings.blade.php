@extends('layouts.app')

@section('title', 'Production Report Team & Deadline Settings')

@section('content')
<div class="pr-root pb-6">
    @include('production-reports.partials.nav')

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Left Column: Deadline & Timezone Rules --}}
        <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
            <h2 class="text-base font-bold text-white mb-1">Reporting Deadlines &amp; Schedule</h2>
            <p class="text-xs text-slate-400 mb-4">All deadlines operate in Cambodia Timezone (<code>Asia/Phnom_Penh</code>).</p>

            <form method="POST" action="{{ route('production-reports.settings.deadlines') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Daily Deadline Time</label>
                        <input type="text" name="daily_deadline_time" value="{{ $deadline->daily_deadline_time }}" {{ $role !== 'supervisor' ? 'readonly' : '' }}
                               class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Daily Reminder Time</label>
                        <input type="text" name="daily_reminder_time" value="{{ $deadline->daily_reminder_time }}" {{ $role !== 'supervisor' ? 'readonly' : '' }}
                               class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Weekly Deadline Day</label>
                        <input type="text" name="weekly_deadline_day" value="{{ $deadline->weekly_deadline_day }}" {{ $role !== 'supervisor' ? 'readonly' : '' }}
                               class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Weekly Deadline Time</label>
                        <input type="text" name="weekly_deadline_time" value="{{ $deadline->weekly_deadline_time }}" {{ $role !== 'supervisor' ? 'readonly' : '' }}
                               class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Weekly Reminder Time</label>
                        <input type="text" name="weekly_reminder_time" value="{{ $deadline->weekly_reminder_time }}" {{ $role !== 'supervisor' ? 'readonly' : '' }}
                               class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Monthly Deadline Mode</label>
                        <select name="monthly_deadline_mode" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white">
                            <option value="last_day_of_month" {{ $deadline->monthly_deadline_mode === 'last_day_of_month' ? 'selected' : '' }}>Last Day of Month</option>
                            <option value="specific_day" {{ $deadline->monthly_deadline_mode === 'specific_day' ? 'selected' : '' }}>Specific Day of Month</option>
                            <option value="day_of_next_month" {{ $deadline->monthly_deadline_mode === 'day_of_next_month' ? 'selected' : '' }}>Day of Following Month</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Weekly Start Day</label>
                        <input type="text" name="weekly_period_start_day" value="{{ $deadline->weekly_period_start_day }}"
                               class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Weekly End Day</label>
                        <input type="text" name="weekly_period_end_day" value="{{ $deadline->weekly_period_end_day }}"
                               class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Monthly Deadline Day</label>
                        <input type="number" name="monthly_deadline_day" value="{{ $deadline->monthly_deadline_day }}" min="1" max="31"
                               class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Monthly Deadline Time</label>
                        <input type="text" name="monthly_deadline_time" value="{{ $deadline->monthly_deadline_time }}"
                               class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                    </div>
                </div>

                @if($role === 'supervisor')
                    <button type="submit" class="w-full py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold">
                        Save Deadline Settings
                    </button>
                @endif
            </form>
        </div>

        {{-- Right Column: Explicit Team Assignment Mapping --}}
        <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
            <h2 class="text-base font-bold text-white mb-1">Production Team Assignment Mapping</h2>
            <p class="text-xs text-slate-400 mb-4">
                Explicitly map Production Members to Team A (Dara) or Team B (Kim). Cross-team access between Heads is strictly blocked.
            </p>

            <form method="POST" action="{{ route('production-reports.settings.team-mapping') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-2 mb-5 bg-slate-800/60 p-3 rounded-xl border border-slate-800">
                @csrf
                <div class="sm:col-span-2">
                    <select name="user_id" required class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-2">
                        <option value="">Select Existing User...</option>
                        @foreach($allActiveUsers as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select name="team_code" required class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-2">
                        @if($role === 'supervisor' || $permissionService->getAssignedTeamCode($user) === 'A')
                            <option value="A">Team A (Dara)</option>
                        @endif
                        @if($role === 'supervisor' || $permissionService->getAssignedTeamCode($user) === 'B')
                            <option value="B">Team B (Kim)</option>
                        @endif
                        @if($role === 'supervisor')
                            <option value="ALL">All Teams (Supervisor)</option>
                        @endif
                    </select>
                </div>
                <div>
                    <select name="role_in_reporting" required class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-2">
                        <option value="member">Production Member</option>
                        @if($role === 'supervisor')
                            <option value="head">Head of Production</option>
                            <option value="supervisor">Supervisor (View-Only)</option>
                        @endif
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold">
                        Save Mapping
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto border border-slate-800 rounded-xl">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-800 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-2 px-3">User</th>
                            <th class="py-2 px-3">Team</th>
                            <th class="py-2 px-3">Reporting Role</th>
                            <th class="py-2 px-3">Permissions</th>
                            <th class="py-2 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($mappings as $map)
                            <tr>
                                <td class="py-2 px-3 font-bold text-white">{{ $map->user?->name ?? 'User' }}</td>
                                <td class="py-2 px-3 font-semibold">{{ $map->team_code === 'ALL' ? 'All Teams' : 'Team ' . $map->team_code }}</td>
                                <td class="py-2 px-3 uppercase font-bold">{{ $map->role_in_reporting }}</td>
                                <td class="py-2 px-3 text-[11px] text-slate-400">
                                    @if($map->role_in_reporting === 'supervisor')
                                        View All Teams &amp; Exports (Never Submits)
                                    @elseif($map->role_in_reporting === 'head')
                                        Team {{ $map->team_code }} Daily Review + Weekly Report Creation
                                    @else
                                        Own Daily &amp; Monthly Reports Only
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-right">
                                    @if($permissionService->canRemoveTeamMember($user, $map))
                                        <form method="POST" action="{{ route('production-reports.settings.team-mapping.remove', $map->id) }}"
                                              onsubmit="return confirm('Remove {{ $map->user?->name }} from Team {{ $map->team_code }} production reporting?')"
                                              class="inline-block">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 hover:text-white text-[11px] font-bold border border-rose-500/30 transition shadow-sm">
                                                Remove Member
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-slate-600 text-[11px]">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

