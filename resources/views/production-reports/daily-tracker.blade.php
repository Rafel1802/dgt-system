@extends('layouts.app')

@section('title', 'Daily Report Tracker')

@section('content')
<div class="pr-root pb-6">
    @include('production-reports.partials.nav')

    {{-- Date & Team Filter Bar --}}
    <div class="mb-6 bg-slate-900 border border-slate-800 rounded-2xl p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-base font-extrabold text-white flex items-center gap-2">
                <span>📝 Daily Production Report Tracker</span>
                @if($selectedDateStr === $todayStr)
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">Today</span>
                @endif
            </h1>
            <p class="text-xs text-slate-400 mt-0.5">
                Tracking date: <span class="font-bold text-white">{{ $selectedDate->format('l, F d, Y') }}</span>
                &bull; Daily Deadline: Before {{ $dailyDeadline->format('g:i A') }} Cambodia Time
            </p>
        </div>

        <form method="GET" action="{{ route('production-reports.daily.tracker') }}" class="flex flex-wrap items-center gap-2">
            @if($role === 'supervisor')
                <select name="team" onchange="this.form.submit()" class="bg-slate-800 border border-slate-700 text-white text-xs font-semibold rounded-xl px-3 py-2">
                    <option value="ALL" {{ $filterTeam === 'ALL' ? 'selected' : '' }}>All Teams (Team A &amp; B)</option>
                    <option value="A" {{ $filterTeam === 'A' ? 'selected' : '' }}>Team A (Dara)</option>
                    <option value="B" {{ $filterTeam === 'B' ? 'selected' : '' }}>Team B (Kim)</option>
                </select>
            @endif

            <div class="inline-flex items-center gap-1.5">
                <a href="{{ route('production-reports.daily.tracker', array_filter(['date' => $prevDateStr, 'team' => $role === 'supervisor' ? $filterTeam : null])) }}"
                   class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold border border-slate-700 transition"
                   title="Previous Day">
                    &larr; Prev
                </a>

                <input type="date"
                       name="date"
                       value="{{ $selectedDateStr }}"
                       onchange="this.form.submit()"
                       class="bg-slate-800 border border-slate-700 text-white text-xs font-semibold rounded-xl px-3 py-1.5">

                @if($selectedDateStr !== $todayStr)
                    <a href="{{ route('production-reports.daily.tracker', array_filter(['date' => $todayStr, 'team' => $role === 'supervisor' ? $filterTeam : null])) }}"
                       class="px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition">
                        Today
                    </a>
                @endif

                <a href="{{ route('production-reports.daily.tracker', array_filter(['date' => $nextDateStr, 'team' => $role === 'supervisor' ? $filterTeam : null])) }}"
                   class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold border border-slate-700 transition"
                   title="Next Day">
                    Next &rarr;
                </a>
            </div>
        </form>
    </div>

    {{-- Daily Tracker Summary KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <div class="text-xs font-semibold text-slate-400">Tracked Staff</div>
            <div class="text-2xl font-extrabold text-white mt-1">{{ $totalMembers }}</div>
            <div class="text-xs text-indigo-400 mt-1">
                {{ $filterTeam === 'ALL' ? 'Across Team A & Team B' : 'Team ' . $filterTeam . ' Roster' }}
            </div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <div class="text-xs font-semibold text-slate-400">Submitted Reports</div>
            <div class="text-2xl font-extrabold text-emerald-400 mt-1">
                {{ $totalSubmitted }} / {{ $totalMembers }}
            </div>
            <div class="text-xs text-slate-400 mt-1">
                {{ $totalMembers > 0 ? round(($totalSubmitted / $totalMembers) * 100, 1) : 0 }}% Daily Compliance
            </div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <div class="text-xs font-semibold text-slate-400">Waiting / Not Submitted</div>
            <div class="text-2xl font-extrabold {{ $totalMissing > 0 ? 'text-rose-400' : 'text-emerald-400' }} mt-1">
                {{ $totalMissing }}
            </div>
            <div class="text-xs text-slate-400 mt-1">
                {{ $totalLate > 0 ? $totalLate . ' Submitted Late' : '0 Late Submissions' }}
            </div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <div class="text-xs font-semibold text-slate-400">Completed Tasks Logged</div>
            <div class="text-2xl font-extrabold text-sky-400 mt-1">
                {{ $totalCompletedTasks }}
            </div>
            <div class="text-xs text-slate-400 mt-1">For {{ $selectedDate->format('M d, Y') }}</div>
        </div>
    </div>

    {{-- Team Tracker Tables --}}
    @foreach($teamTrackers as $tracker)
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-base font-bold text-white">
                        Team {{ $tracker['code'] }} — Daily Report Tracker ({{ $selectedDate->format('D, M d, Y') }})
                    </h2>
                    <p class="text-xs text-slate-400">{{ $tracker['name'] }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                        Submitted: {{ $tracker['submitted_count'] }} / {{ $tracker['members']->count() }}
                    </span>
                    @if($tracker['missing_count'] > 0)
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-500/15 text-rose-300 border border-rose-500/30">
                            Pending: {{ $tracker['missing_count'] }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-800/80 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-2.5 px-3">Team Member</th>
                            <th class="py-2.5 px-3">Role</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3">Completed Tasks</th>
                            <th class="py-2.5 px-3">Submitted At</th>
                            <th class="py-2.5 px-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($tracker['members'] as $member)
                            @php
                                $rep = $tracker['reports']->get($member->id);
                                $tasksCount = ($rep && is_array($rep->completed_tasks)) ? count($rep->completed_tasks) : 0;
                            @endphp
                            <tr>
                                <td class="py-2.5 px-3 font-semibold text-white">{{ $member->name }}</td>
                                <td class="py-2.5 px-3">{{ $member->team_role ?: 'Production Member' }}</td>
                                <td class="py-2.5 px-3">
                                    @if($rep)
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold pr-badge pr-badge-{{ $rep->status_badge_color }}">
                                            {{ $rep->status_badge_label }}
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                            Not Submitted
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 font-semibold {{ $tasksCount > 0 ? 'text-sky-400' : 'text-slate-500' }}">
                                    {{ $rep ? $tasksCount . ' Tasks' : '—' }}
                                </td>
                                <td class="py-2.5 px-3">
                                    {{ $rep?->submitted_at ? $rep->submitted_at->timezone('Asia/Phnom_Penh')->format('g:i A') : '—' }}
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    @if($rep)
                                        <a href="{{ route('production-reports.show', $rep->id) }}" class="pr-action-pill pr-action-pill-view">
                                            Review Report &rarr;
                                        </a>
                                    @else
                                        <span class="text-slate-500">Waiting</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-center text-slate-500">
                                    No members currently mapped to Team {{ $tracker['code'] }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection

