@extends('layouts.app')

@section('title', 'Production Reports Dashboard')

@section('content')
<div class="pr-root pb-6">
    @include('production-reports.partials.nav')

    {{-- ====================================================================
         1. SUPERVISOR DASHBOARD (Somalika — View All Teams, View-Only)
         ==================================================================== --}}
    @if($role === 'supervisor')
        <div class="mb-6 bg-slate-900/80 border border-slate-800 rounded-2xl p-4">
            <form method="GET" action="{{ route('production-reports.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Production Team</label>
                    <select name="team" class="bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-3 py-2">
                        <option value="ALL" {{ $filterTeam === 'ALL' ? 'selected' : '' }}>All Production Teams (Team A &amp; Team B)</option>
                        <option value="A" {{ $filterTeam === 'A' ? 'selected' : '' }}>Team A (Dara)</option>
                        <option value="B" {{ $filterTeam === 'B' ? 'selected' : '' }}>Team B (Kim)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">From Date</label>
                    <input type="date" name="from" value="{{ $dateFrom }}" class="bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">To Date</label>
                    <input type="date" name="to" value="{{ $dateTo }}" class="bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-3 py-2">
                </div>
                <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold">
                    Apply Filter
                </button>
                <a href="{{ route('production-reports.archive.export-csv', ['team' => $filterTeam !== 'ALL' ? $filterTeam : null, 'from' => $dateFrom, 'to' => $dateTo]) }}"
                   class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold">
                    Export Filtered CSV
                </a>
            </form>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="text-xs font-semibold text-slate-400">Total Production Staff</div>
                <div class="text-2xl font-extrabold text-white mt-1">{{ $totalMembersCount }}</div>
                <div class="text-xs text-slate-400 mt-1">Team A: {{ $teamAMembers->count() }} &bull; Team B: {{ $teamBMembers->count() }}</div>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="text-xs font-semibold text-slate-400">Today's Daily Rate</div>
                <div class="text-2xl font-extrabold text-indigo-400 mt-1">{{ $todayDailyRate }}%</div>
                <div class="text-xs text-slate-400 mt-1">Due: {{ $dailyDeadline->format('g:i A') }} (Cambodia)</div>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="text-xs font-semibold text-slate-400">Weekly Team Rate</div>
                <div class="text-2xl font-extrabold text-emerald-400 mt-1">{{ $weeklyRate }}%</div>
                <div class="text-xs text-slate-400 mt-1">Due: Sat 8:00 AM</div>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="text-xs font-semibold text-slate-400">Monthly Submission Rate</div>
                <div class="text-2xl font-extrabold text-sky-400 mt-1">{{ $monthlyRate }}%</div>
                <div class="text-xs text-slate-400 mt-1">{{ $now->format('F Y') }}</div>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="text-xs font-semibold text-slate-400">Late &amp; Missing (This Week)</div>
                <div class="text-2xl font-extrabold text-amber-400 mt-1">
                    {{ $lateSubmissionsCount }} Late / {{ ($teamAWeekly['missing_submissions_count'] ?? 0) + ($teamBWeekly['missing_submissions_count'] ?? 0) }} Missing
                </div>
                <div class="text-xs text-slate-400 mt-1">Across Team A &amp; Team B</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            @foreach([['code' => 'A', 'head' => 'Dara', 'data' => $teamAWeekly, 'ratingRep' => $latestTeamARating], ['code' => 'B', 'head' => 'Kim', 'data' => $teamBWeekly, 'ratingRep' => $latestTeamBRating]] as $tm)
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h2 class="text-base font-bold text-white">{{ $tm['data']['team_name'] }}</h2>
                            <p class="text-xs text-slate-400">Head of Production: {{ $tm['head'] }} &bull; Week: {{ $tm['data']['period_formatted'] }}</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                            {{ $tm['ratingRep'] ? $tm['ratingRep']->rating_display : 'Pending Weekly Rating' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-4 gap-2 text-center bg-slate-800/60 rounded-xl p-3 mb-3">
                        <div>
                            <div class="text-[11px] text-slate-400">Members</div>
                            <div class="text-sm font-bold text-white">{{ $tm['data']['team_members_count'] }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] text-slate-400">Daily Submitted</div>
                            <div class="text-sm font-bold text-emerald-400">{{ $tm['data']['actual_submissions'] }}/{{ $tm['data']['expected_submissions'] }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] text-slate-400">Missing Daily</div>
                            <div class="text-sm font-bold text-rose-400">{{ $tm['data']['missing_submissions_count'] }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] text-slate-400">Completed Tasks</div>
                            <div class="text-sm font-bold text-sky-400">{{ $tm['data']['total_completed_tasks'] }}</div>
                        </div>
                    </div>

                    @if(!empty($tm['data']['missing_reports']))
                        <div class="text-xs text-rose-300 bg-rose-500/10 border border-rose-500/30 rounded-xl p-2.5">
                            <span class="font-bold">Missing Daily Reports ({{ count($tm['data']['missing_reports']) }}):</span>
                            @foreach(array_slice($tm['data']['missing_reports'], 0, 5) as $mr)
                                <span class="inline-block mr-2">{{ $mr['member_name'] }} ({{ $mr['date_formatted'] }})</span>
                            @endforeach
                        </div>
                    @else
                        <div class="text-xs text-emerald-300 bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-2.5">
                            All expected daily reports submitted for this period.
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- ====================================================================
         2. PRODUCTION HEAD DASHBOARD (Dara -> Team A ONLY; Kim -> Team B ONLY)
         ==================================================================== --}}
    @if($role === 'head')
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="text-xs font-semibold text-slate-400">Assigned Team</div>
                <div class="text-lg font-extrabold text-white mt-1">{{ $teamName }}</div>
                <div class="text-xs text-indigo-400 mt-1">{{ $teamMembers->count() }} Active Team Members</div>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="text-xs font-semibold text-slate-400">Weekly Submission Progress</div>
                <div class="text-2xl font-extrabold text-emerald-400 mt-1">
                    {{ $compiledWeek['actual_submissions'] }} / {{ $compiledWeek['expected_submissions'] }}
                </div>
                <div class="text-xs text-slate-400 mt-1">{{ $compiledWeek['submission_rate'] }}% Daily Compliance ({{ $compiledWeek['missing_submissions_count'] }} Missing)</div>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="text-xs font-semibold text-slate-400">Weekly Report Status</div>
                <div class="text-lg font-extrabold text-amber-400 mt-1">
                    {{ $currentWeeklyReport ? $currentWeeklyReport->status_badge_label : 'Not Started' }}
                </div>
                <div class="text-xs text-slate-400 mt-1">Deadline: {{ $weeklyPeriod['deadline']->format('D, M d g:i A') }}</div>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="text-xs font-semibold text-slate-400">Team Output &amp; Rating</div>
                <div class="text-lg font-extrabold text-sky-400 mt-1">
                    {{ $compiledWeek['total_completed_tasks'] }} Completed Tasks
                </div>
                <div class="text-xs text-slate-400 mt-1">
                    Rating: {{ $currentWeeklyReport ? $currentWeeklyReport->rating_display : 'Rate 1–5 in Weekly Report' }}
                </div>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-base font-bold text-white">Team {{ $teamCode }} — Today's Daily Report Tracker ({{ $now->format('D, M d, Y') }})</h2>
                    <p class="text-xs text-slate-400">Daily Deadline: Before 5:00 PM Cambodia Time</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                        Submitted Today: {{ $todayTeamDailyReports->count() }} / {{ $teamMembers->count() }}
                    </span>
                    <a href="{{ route('production-reports.daily.tracker') }}"
                       class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-indigo-400 hover:text-indigo-300 text-xs font-bold border border-slate-700 transition">
                        Open Daily Tracker &rarr;
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-800/80 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-2.5 px-3">Team Member</th>
                            <th class="py-2.5 px-3">Role</th>
                            <th class="py-2.5 px-3">Today's Status</th>
                            <th class="py-2.5 px-3">Submitted At</th>
                            <th class="py-2.5 px-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($teamMembers as $member)
                            @php $rep = $todayTeamDailyReports->get($member->id); @endphp
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
                                <td class="py-2.5 px-3">{{ $rep?->submitted_at ? $rep->submitted_at->timezone('Asia/Phnom_Penh')->format('g:i A') : '—' }}</td>
                                <td class="py-2.5 px-3 text-right">
                                    @if($rep)
                                        <a href="{{ route('production-reports.show', $rep->id) }}" class="text-indigo-400 hover:text-indigo-300 font-bold">Review Report &rarr;</a>
                                    @else
                                        <span class="text-slate-500">Waiting</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-slate-500">No members currently mapped to Team {{ $teamCode }}.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ====================================================================
         3. PRODUCTION MEMBER DASHBOARD (Own Reports Only)
         ==================================================================== --}}
    @if($role === 'member')
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="text-xs font-semibold text-slate-400">Today's Daily Report</div>
                <div class="text-lg font-extrabold text-white mt-1">
                    {{ $todayReport ? $todayReport->status_badge_label : 'Not Started' }}
                </div>
                <div class="text-xs text-slate-400 mt-1">Deadline: Today 5:00 PM (Cambodia)</div>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="text-xs font-semibold text-slate-400">{{ $now->format('F Y') }} Progress</div>
                <div class="text-2xl font-extrabold text-indigo-400 mt-1">
                    {{ $monthlyCompiled['submitted_days_count'] }} / {{ $monthlyCompiled['expected_days_count'] }} Days
                </div>
                <div class="text-xs text-slate-400 mt-1">{{ $monthlyCompiled['submission_rate'] }}% Completion Rate</div>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="text-xs font-semibold text-slate-400">Missing Daily Reports</div>
                <div class="text-2xl font-extrabold {{ $monthlyCompiled['missing_days_count'] > 0 ? 'text-rose-400' : 'text-emerald-400' }} mt-1">
                    {{ $monthlyCompiled['missing_days_count'] }}
                </div>
                <div class="text-xs text-slate-400 mt-1">In {{ $now->format('F Y') }}</div>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="text-xs font-semibold text-slate-400">Monthly Report Status</div>
                <div class="text-lg font-extrabold text-emerald-400 mt-1">
                    {{ $currentMonthlyReport ? $currentMonthlyReport->status_badge_label : 'Not Started' }}
                </div>
                <div class="text-xs text-slate-400 mt-1">Due: {{ $monthlyDeadline->format('M d, g:i A') }}</div>
            </div>
        </div>
    @endif

    {{-- ====================================================================
         RECENT REPORTS TABLE (Scoped strictly to logged-in user's permissions)
         ==================================================================== --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-bold text-white">Recent Production Reports</h2>
            @if($permissionService->canSeeFullMenu($user))
                <a href="{{ route('production-reports.archive') }}" class="text-xs font-bold text-indigo-400 hover:text-indigo-300">
                    Open Full Archive &rarr;
                </a>
            @else
                <a href="{{ route('production-reports.my-reports') }}" class="text-xs font-bold text-indigo-400 hover:text-indigo-300">
                    Open My Reports &rarr;
                </a>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-800/80 text-slate-400 uppercase text-[11px]">
                    <tr>
                        <th class="py-2.5 px-3">Code / Title</th>
                        <th class="py-2.5 px-3">Type</th>
                        <th class="py-2.5 px-3">Team</th>
                        <th class="py-2.5 px-3">Author</th>
                        <th class="py-2.5 px-3">Period</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 px-3">Rating</th>
                        <th class="py-2.5 px-3 text-right">Exports &amp; View</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($recentReports as $rep)
                        <tr>
                            <td class="py-2.5 px-3">
                                <a href="{{ route('production-reports.show', $rep->id) }}" class="font-bold text-white hover:text-indigo-400">
                                    {{ $rep->title }}
                                </a>
                                <div class="text-[11px] text-slate-500">{{ $rep->report_code }}</div>
                            </td>
                            <td class="py-2.5 px-3 uppercase font-bold">{{ $rep->report_type }}</td>
                            <td class="py-2.5 px-3">Team {{ $rep->team_code ?: '—' }}</td>
                            <td class="py-2.5 px-3">{{ $rep->user?->name ?? 'N/A' }}</td>
                            <td class="py-2.5 px-3">{{ $rep->period_label }}</td>
                            <td class="py-2.5 px-3">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold pr-badge pr-badge-{{ $rep->status_badge_color }}">
                                    {{ $rep->status_badge_label }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3">{{ $rep->performance_rating ? $rep->performance_rating . '/5' : '—' }}</td>
                            <td class="py-2.5 px-3 text-right space-x-1 whitespace-nowrap">
                                <a href="{{ route('production-reports.show', $rep->id) }}" class="pr-action-pill pr-action-pill-view">View</a>
                                <a href="{{ route('production-reports.export', [$rep->id, 'pptx']) }}" class="pr-action-pill pr-action-pill-pptx" title="Export PPTX">PPTX</a>
                                <a href="{{ route('production-reports.export', [$rep->id, 'pdf']) }}" class="pr-action-pill pr-action-pill-pdf" title="Export PDF">PDF</a>
                                <a href="{{ route('production-reports.export', [$rep->id, 'csv']) }}" class="pr-action-pill pr-action-pill-csv" title="Export CSV">CSV</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-slate-500">No production reports found yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

