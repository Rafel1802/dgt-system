@extends('layouts.app')

@section('title', 'Production Report Calendar — ' . $startOfMonth->format('F Y'))

@section('content')
<div class="pr-root pb-8" x-data="{
    modalOpen: false,
    selectedDay: null,
    openDay(dayData) {
        this.selectedDay = dayData;
        this.modalOpen = true;
    }
}">
    @include('production-reports.partials.nav')

    {{-- ── Calendar Top Hero & Month Switcher Toolbar ───────────────────── --}}
    <div class="bg-[var(--pr-card)] border border-[var(--pr-border)] rounded-2xl p-4 sm:p-5 shadow-sm mb-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            {{-- Title & Context --}}
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <span class="pr-chip pr-chip-indigo">MONTHLY CALENDAR</span>
                    @if($teamFilter === 'ALL')
                        <span class="pr-chip pr-chip-emerald">All Production Teams (A &amp; B)</span>
                    @else
                        <span class="pr-chip pr-chip-amber">Team {{ $teamFilter }}</span>
                    @endif
                    <span class="text-xs text-[var(--pr-text2)]">Daily cut-off: 5:00 PM • Weekly cut-off: Sat 8:00 AM</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-[var(--pr-text)] tracking-tight flex items-center gap-2">
                    <span>📅</span>
                    <span>Submission Calendar — {{ $startOfMonth->format('F Y') }}</span>
                </h1>
            </div>

            {{-- Controls: Previous Month / Quick Today / Next Month & Month Selector --}}
            <div class="flex flex-wrap items-center gap-2">
                {{-- Team Filter for Supervisor or Admin --}}
                @if($permissionService->isSupervisor($user))
                    <div class="flex items-center bg-[var(--pr-soft)] p-1 rounded-xl border border-[var(--pr-border)] mr-2">
                        <a href="{{ route('production-reports.calendar', ['month' => $month, 'year' => $year, 'team' => 'ALL']) }}"
                           class="px-2.5 py-1 text-xs font-bold rounded-lg transition-all {{ $teamFilter === 'ALL' ? 'bg-[var(--pr-card)] text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-[var(--pr-text2)] hover:text-[var(--pr-text)]' }}">
                            All Teams
                        </a>
                        <a href="{{ route('production-reports.calendar', ['month' => $month, 'year' => $year, 'team' => 'A']) }}"
                           class="px-2.5 py-1 text-xs font-bold rounded-lg transition-all {{ $teamFilter === 'A' ? 'bg-[var(--pr-card)] text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-[var(--pr-text2)] hover:text-[var(--pr-text)]' }}">
                            Team A (Dara)
                        </a>
                        <a href="{{ route('production-reports.calendar', ['month' => $month, 'year' => $year, 'team' => 'B']) }}"
                           class="px-2.5 py-1 text-xs font-bold rounded-lg transition-all {{ $teamFilter === 'B' ? 'bg-[var(--pr-card)] text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-[var(--pr-text2)] hover:text-[var(--pr-text)]' }}">
                            Team B (Kim)
                        </a>
                    </div>
                @endif

                {{-- Previous Month --}}
                <a href="{{ route('production-reports.calendar', ['month' => $prevDate->month, 'year' => $prevDate->year, 'team' => $teamFilter]) }}"
                   class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-[var(--pr-text2)] bg-[var(--pr-soft)] hover:bg-[var(--pr-card)] rounded-xl border border-[var(--pr-border)] transition-all shadow-xs"
                   title="Previous Month ({{ $prevDate->format('F Y') }})">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                    <span class="hidden sm:inline">{{ $prevDate->format('M') }}</span>
                </a>

                {{-- Today Quick Jump --}}
                <a href="{{ route('production-reports.calendar', ['month' => now()->month, 'year' => now()->year, 'team' => $teamFilter]) }}"
                   class="px-3 py-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 rounded-xl border border-indigo-200 dark:border-indigo-800 transition-all shadow-xs"
                   title="Jump to Current Month">
                    Today
                </a>

                {{-- Next Month --}}
                <a href="{{ route('production-reports.calendar', ['month' => $nextDate->month, 'year' => $nextDate->year, 'team' => $teamFilter]) }}"
                   class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-[var(--pr-text2)] bg-[var(--pr-soft)] hover:bg-[var(--pr-card)] rounded-xl border border-[var(--pr-border)] transition-all shadow-xs"
                   title="Next Month ({{ $nextDate->format('F Y') }})">
                    <span class="hidden sm:inline">{{ $nextDate->format('M') }}</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                </a>

                {{-- Month / Year Quick Dropdowns Form --}}
                <form method="GET" action="{{ route('production-reports.calendar') }}" class="flex items-center gap-1.5">
                    @if($teamFilter && $teamFilter !== 'ALL')
                        <input type="hidden" name="team" value="{{ $teamFilter }}">
                    @endif
                    <select name="month" onchange="this.form.submit()" class="text-xs font-bold bg-[var(--pr-soft)] border border-[var(--pr-border)] text-[var(--pr-text)] rounded-xl px-2.5 py-1.5 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $month === $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::createFromDate($year, $m, 1)->format('F') }}
                            </option>
                        @endfor
                    </select>
                    <select name="year" onchange="this.form.submit()" class="text-xs font-bold bg-[var(--pr-soft)] border border-[var(--pr-border)] text-[var(--pr-text)] rounded-xl px-2.5 py-1.5 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none">
                        @for($y = 2024; $y <= 2030; $y++)
                            <option value="{{ $y }}" {{ $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </form>
            </div>
        </div>

        {{-- Monthly KPI Quick Stats Strip --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 pt-4 border-t border-[var(--pr-border)]">
            <div class="p-3 bg-[var(--pr-soft)] rounded-xl border border-[var(--pr-border)]">
                <div class="text-[11px] font-bold text-[var(--pr-muted)] uppercase tracking-wider">Total Reports</div>
                <div class="text-lg font-black text-[var(--pr-text)] mt-0.5">{{ $totalMonthReports }}</div>
                <div class="text-[10px] text-[var(--pr-muted)] mt-0.5">{{ $totalMonthDaily }} Daily • {{ $totalMonthWeekly }} Weekly • {{ $totalMonthMonthly }} Monthly</div>
            </div>
            <div class="p-3 bg-[var(--pr-soft)] rounded-xl border border-[var(--pr-border)]">
                <div class="text-[11px] font-bold text-[var(--pr-muted)] uppercase tracking-wider">On-Time Compliance</div>
                <div class="text-lg font-black text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $complianceRate }}%</div>
                <div class="text-[10px] text-[var(--pr-muted)] mt-0.5">Based on workdays (Mon-Sat)</div>
            </div>
            <div class="p-3 bg-[var(--pr-soft)] rounded-xl border border-[var(--pr-border)]">
                <div class="text-[11px] font-bold text-[var(--pr-muted)] uppercase tracking-wider">Team Capacity</div>
                <div class="text-lg font-black text-indigo-600 dark:text-indigo-400 mt-0.5">{{ $expectedDailyCount }} Members</div>
                <div class="text-[10px] text-[var(--pr-muted)] mt-0.5">Expected daily submissions</div>
            </div>
            <div class="p-3 bg-[var(--pr-soft)] rounded-xl border border-[var(--pr-border)]">
                <div class="text-[11px] font-bold text-[var(--pr-muted)] uppercase tracking-wider">Weekly Reviews</div>
                <div class="text-lg font-black text-amber-600 dark:text-amber-400 mt-0.5">{{ $totalMonthWeekly }} Submitted</div>
                <div class="text-[10px] text-[var(--pr-muted)] mt-0.5">Every Saturday before 8:00 AM</div>
            </div>
        </div>
    </div>

    {{-- ── True 7-Column Calendar Grid Structure ────────────────────────── --}}
    <div class="pr-cal-wrapper">
        {{-- Weekday Header Row --}}
        <div class="pr-cal-grid-header">
            <div class="pr-cal-head-col is-sunday">
                <span class="block">Sun</span>
                <span class="text-[9px] font-normal opacity-70">Rest Day</span>
            </div>
            <div class="pr-cal-head-col">
                <span class="block">Mon</span>
                <span class="text-[9px] font-normal opacity-70">Daily Due</span>
            </div>
            <div class="pr-cal-head-col">
                <span class="block">Tue</span>
                <span class="text-[9px] font-normal opacity-70">Daily Due</span>
            </div>
            <div class="pr-cal-head-col">
                <span class="block">Wed</span>
                <span class="text-[9px] font-normal opacity-70">Daily Due</span>
            </div>
            <div class="pr-cal-head-col">
                <span class="block">Thu</span>
                <span class="text-[9px] font-normal opacity-70">Daily Due</span>
            </div>
            <div class="pr-cal-head-col">
                <span class="block">Fri</span>
                <span class="text-[9px] font-normal opacity-70">Daily Due</span>
            </div>
            <div class="pr-cal-head-col">
                <span class="block">Sat</span>
                <span class="text-[9px] font-bold text-amber-500">Weekly Due</span>
            </div>
        </div>

        {{-- Calendar Days Grid (5 to 6 Full Weeks) --}}
        <div class="pr-cal-grid-body">
            @foreach($calendarDays as $dayItem)
                @php
                    $isOutside = !$dayItem['is_current_month'];
                    $isToday = $dayItem['is_today'];
                    $isSunday = $dayItem['is_sunday'];
                    $isPast = $dayItem['is_past'];
                    $reports = $dayItem['reports'];
                    $dailyCount = $dayItem['daily_submitted_count'];
                    $expected = $dayItem['daily_expected_count'];
                    $missing = $dayItem['missing_daily'];
                @endphp

                <div class="pr-cal-day-cell {{ $isOutside ? 'is-outside' : '' }} {{ $isToday ? 'is-today' : '' }} {{ $isSunday ? 'is-sunday' : '' }}"
                     @click="openDay(@js([
                         'date' => $dayItem['date']->format('l, F d, Y'),
                         'date_str' => $dayItem['date_str'],
                         'is_today' => $isToday,
                         'is_sunday' => $isSunday,
                         'daily_count' => $dailyCount,
                         'expected' => $expected,
                         'missing' => $missing,
                         'reports' => $reports->map(fn($r) => [
                             'id' => $r->id,
                             'type' => $r->report_type,
                             'author' => $r->user?->name ?? 'Team Member',
                             'team' => $r->team_code,
                             'status' => $r->status_badge_label,
                             'color' => $r->status_badge_color,
                             'rating' => $r->rating_display,
                             'time' => $r->submitted_at ? $r->submitted_at->setTimezone('Asia/Phnom_Penh')->format('g:i A') : null,
                             'url' => route('production-reports.show', $r->id),
                         ])
                     ]))">

                    {{-- Day Cell Header: Number + Mini Status Pill --}}
                    <div class="flex items-center justify-between gap-1 mb-2">
                        <span class="pr-cal-day-num {{ $isToday ? 'is-today-badge' : '' }}">
                            {{ $dayItem['day'] }}
                        </span>

                        @if($isToday)
                            <span class="px-1.5 py-0.5 rounded-full bg-indigo-600 text-white text-[9px] font-black uppercase tracking-wider">
                                Today
                            </span>
                        @elseif($isSunday)
                            <span class="text-[9px] font-semibold text-[var(--pr-muted)] italic">
                                Off
                            </span>
                        @elseif($isPast && $dailyCount >= $expected && $expected > 0)
                            <span class="px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 text-[9px] font-bold">
                                ✓ {{ $dailyCount }}/{{ $expected }}
                            </span>
                        @elseif($isPast && $missing > 0)
                            <span class="px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 text-[9px] font-bold" title="{{ $missing }} reports missing">
                                ⚠️ {{ $missing }} Miss
                            </span>
                        @elseif($isToday)
                            <span class="px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 text-[9px] font-bold">
                                {{ $dailyCount }}/{{ $expected }}
                            </span>
                        @endif
                    </div>

                    {{-- Day Cell Reports Stack --}}
                    <div class="space-y-1 overflow-hidden">
                        {{-- Weekly Report Badge (Saturday priority) --}}
                        @foreach($dayItem['weekly_reports'] as $wRep)
                            <a href="{{ route('production-reports.show', $wRep->id) }}"
                               @click.stop
                               class="pr-cal-pill pr-cal-pill-amber"
                               title="Team {{ $wRep->team_code }} Weekly Report ({{ $wRep->status_badge_label }})">
                                <span>⭐</span>
                                <span class="truncate font-black">Team {{ $wRep->team_code }} Weekly</span>
                            </a>
                        @endforeach

                        {{-- Monthly Report Badge --}}
                        @foreach($dayItem['monthly_reports'] as $mRep)
                            <a href="{{ route('production-reports.show', $mRep->id) }}"
                               @click.stop
                               class="pr-cal-pill pr-cal-pill-indigo"
                               title="Monthly Report ({{ $mRep->status_badge_label }})">
                                <span>🏆</span>
                                <span class="truncate font-black">{{ $mRep->user?->name ?? 'Team' }} Monthly</span>
                            </a>
                        @endforeach

                        {{-- Daily Reports Summary / Individual Badges --}}
                        @if($dailyCount > 0)
                            @if($dailyCount <= 2)
                                @foreach($dayItem['daily_reports'] as $dRep)
                                    <a href="{{ route('production-reports.show', $dRep->id) }}"
                                       @click.stop
                                       class="pr-cal-pill pr-cal-pill-emerald"
                                       title="{{ $dRep->user?->name }}: Daily Report submitted">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                        <span class="truncate">{{ Str::before($dRep->user?->name ?? 'Staff', ' ') }}</span>
                                    </a>
                                @endforeach
                            @else
                                <div class="pr-cal-pill pr-cal-pill-emerald font-bold">
                                    <span>✓</span>
                                    <span class="truncate">{{ $dailyCount }} Daily Reports</span>
                                </div>
                            @endif
                        @elseif(!$isSunday && $isPast)
                            <div class="text-[10px] text-rose-500 dark:text-rose-400 italic py-0.5 truncate">
                                No reports
                            </div>
                        @elseif($isToday && $dailyCount === 0)
                            <div class="text-[10px] text-indigo-500 dark:text-indigo-400 italic py-0.5 truncate">
                                Awaiting submissions
                            </div>
                        @endif
                    </div>

                    {{-- Bottom Hint --}}
                    <div class="mt-auto pt-1 text-right">
                        @if($reports->count() > 0)
                            <span class="text-[9px] font-bold text-[var(--pr-muted)] group-hover:text-indigo-500">
                                {{ $reports->count() }} report{{ $reports->count() === 1 ? '' : 's' }} &rsaquo;
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── Interactive Day Inspection Modal ─────────────────────────────── --}}
    <div x-show="modalOpen"
         x-cloak
         x-transition.opacity.duration.150ms
         class="pr-modal-overlay"
         role="dialog" aria-modal="true" aria-label="Day details"
         @click.self="modalOpen = false"
         @keydown.escape.window="modalOpen = false"
         x-effect="document.body.style.overflow = modalOpen ? 'hidden' : ''">

        <div class="pr-modal" x-show="modalOpen" x-transition.opacity.duration.150ms>

            {{-- Header --}}
            <div class="pr-modal-head">
                <div class="min-w-0">
                    <span class="pr-chip pr-chip-indigo" x-text="selectedDay?.date_str"></span>
                    <h3 class="pr-modal-title" x-text="selectedDay?.date"></h3>
                </div>
                <button type="button" @click="modalOpen = false" class="pr-modal-close" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="pr-modal-body">
                <div class="pr-modal-stats">
                    <div class="pr-modal-stat">
                        <div class="pr-modal-stat-label">Daily Submitted</div>
                        <div class="pr-modal-stat-value">
                            <span x-text="selectedDay?.daily_count || 0"></span> / <span x-text="selectedDay?.expected ?? {{ $expectedDailyCount }}"></span>
                        </div>
                    </div>
                    <div class="pr-modal-stat">
                        <div class="pr-modal-stat-label">Compliance Status</div>
                        <div class="pr-modal-stat-value"
                             :class="(selectedDay?.missing || 0) > 0 ? 'text-amber-500' : 'text-emerald-500'"
                             x-text="selectedDay?.is_sunday ? 'Rest Day' : ((selectedDay?.missing || 0) > 0 ? (selectedDay.missing + ' Missing') : (selectedDay?.daily_count > 0 ? 'On Track' : 'No Reports'))"></div>
                    </div>
                </div>

                <template x-if="!selectedDay?.reports || selectedDay.reports.length === 0">
                    <div class="pr-modal-empty">
                        <span class="pr-modal-empty-icon">📭</span>
                        <span x-text="selectedDay?.is_sunday ? 'Sunday is a rest day — no reports expected.' : 'No reports recorded for this date.'"></span>
                    </div>
                </template>

                <template x-for="r in selectedDay?.reports || []" :key="r.id">
                    <div class="pr-modal-item">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-extrabold uppercase"
                                      :class="{
                                          'bg-indigo-100 text-indigo-800': r.type === 'DAILY',
                                          'bg-amber-100 text-amber-800': r.type === 'WEEKLY',
                                          'bg-purple-100 text-purple-800': r.type === 'MONTHLY'
                                      }"
                                      x-text="r.type"></span>
                                <span class="text-sm font-bold truncate" style="color: var(--pr-text)" x-text="r.author"></span>
                            </div>
                            <div class="text-xs mt-1" style="color: var(--pr-muted)">
                                <span x-text="'Team ' + (r.team || '—')"></span>
                                <span x-show="r.time" x-text="' • Submitted at ' + r.time"></span>
                                <span x-show="r.rating" x-text="' • Rating: ' + r.rating" class="font-bold text-amber-500"></span>
                            </div>
                        </div>
                        <a :href="r.url" class="pr-modal-btn pr-modal-btn-primary shrink-0">Open Report</a>
                    </div>
                </template>
            </div>

            {{-- Footer --}}
            <div class="pr-modal-foot">
                <button type="button" @click="modalOpen = false" class="pr-modal-btn pr-modal-btn-ghost">Close</button>
                @if($permissionService->canSubmitDaily($user))
                    <a href="{{ route('production-reports.daily.create') }}" class="pr-modal-btn pr-modal-btn-primary">+ Submit Daily Report</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
