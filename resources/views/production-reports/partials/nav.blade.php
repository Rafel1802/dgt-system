@include('production-reports.partials.styles')

@php
    $canDaily = $permissionService->canSubmitDaily($user);
    $canWeekly = $permissionService->canSubmitWeekly($user);
    $canMonthly = $permissionService->canSubmitMonthly($user);
    $isSup = $permissionService->isSupervisor($user);
    $isHead = $permissionService->isProductionHead($user);
    $assignedTeam = $permissionService->getAssignedTeamCode($user);
    $fullMenu = $permissionService->canSeeFullMenu($user);
    $tab = fn (string ...$patterns) => collect($patterns)->contains(fn ($p) => request()->routeIs($p)) ? 'pr-tab is-active' : 'pr-tab';
@endphp

{{-- Fixed on top sticky navigation bar --}}
<div class="pr-sticky-nav">
    {{-- Top Hero Row: Identity & Sleek Action Buttons (Image 3 Overhaul) --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 pb-3 border-b border-slate-700/60 dark:border-slate-800/80">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="pr-chip pr-chip-indigo">KIUQ PRODUCTION</span>
                @if($isSup)
                    <span class="pr-chip pr-chip-emerald">Supervisor View-Only • All Teams</span>
                @elseif($isHead)
                    <span class="pr-chip pr-chip-amber">Head of Production · Team {{ $assignedTeam }}</span>
                @else
                    <span class="pr-chip pr-chip-sky">Production Member · Team {{ $assignedTeam ?: 'General' }}</span>
                @endif
                <span class="text-[11px] text-slate-400 font-medium">Daily 5:00 PM • Weekly Sat 8:00 AM</span>
            </div>
        </div>

        {{-- Action Buttons (Image 3 Refined UX/UI) --}}
        <div class="flex flex-wrap items-center gap-2">
            @if($canDaily)
                <a href="{{ route('production-reports.daily.create') }}" class="pr-action-btn-daily" title="Submit your daily production report">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    <span>Submit Daily</span>
                </a>
            @endif

            @if($canWeekly)
                <a href="{{ route('production-reports.weekly.create', ['team' => $assignedTeam]) }}" class="pr-action-btn-weekly" title="Compile and submit weekly team report">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>★ Compile Team {{ $assignedTeam }} Weekly</span>
                </a>
            @endif

            @if($isHead)
                <a href="{{ route('production-reports.monthly.team.create') }}" class="pr-action-btn-monthly" title="Compile and submit monthly team report to supervisor">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>★ Compile Team {{ $assignedTeam }} Monthly</span>
                </a>
            @elseif($canMonthly)
                <a href="{{ route('production-reports.monthly.create') }}" class="pr-action-btn-monthly" title="Submit your personal monthly production report">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Monthly Report</span>
                </a>
            @endif
        </div>
    </div>

    {{-- Bottom Menu Bar: Direct High-Contrast Navigation Tabs for All Pages (No Hidden Dropdown) --}}
    <nav class="pt-2.5 overflow-x-auto" aria-label="Production Report Menu">
        <div class="pr-tabs-bar">
            {{-- 1. Dashboard --}}
            <a href="{{ route('production-reports.index') }}" class="{{ $tab('production-reports.index') }}">
                <span class="text-sm">📊</span>
                <span>Dashboard</span>
            </a>

            {{-- 2. Daily Report / Daily Tracker --}}
            @if($canDaily)
                <a href="{{ route('production-reports.daily.create') }}" class="{{ $tab('production-reports.daily.*') }}">
                    <span class="text-sm">📝</span>
                    <span>Daily Report</span>
                </a>
            @else
                <a href="{{ route('production-reports.daily.tracker') }}" class="{{ $tab('production-reports.daily.*') }}">
                    <span class="text-sm">📝</span>
                    <span>Daily Tracker</span>
                </a>
            @endif

            {{-- 3. Weekly Team Reports (Leadership: Dara, Kim, Somalika) --}}
            @if($fullMenu)
                <a href="{{ route('production-reports.weekly.index') }}" class="{{ $tab('production-reports.weekly.*') }}">
                    <span class="text-sm">📈</span>
                    <span>Weekly Team Reports</span>
                </a>
            @endif

            {{-- 4. Monthly Reports --}}
            @if($isHead)
                <a href="{{ route('production-reports.monthly.team.create') }}" class="{{ $tab('production-reports.monthly.*') }}">
                    <span class="text-sm">🗓️</span>
                    <span>Monthly Reports</span>
                </a>
            @elseif($canMonthly)
                <a href="{{ route('production-reports.monthly.create') }}" class="{{ $tab('production-reports.monthly.*') }}">
                    <span class="text-sm">🗓️</span>
                    <span>Monthly Report</span>
                </a>
            @endif

            {{-- 5. My Reports (Members & Heads) --}}
            @if(!$isSup)
                <a href="{{ route('production-reports.my-reports') }}" class="{{ $tab('production-reports.my-reports') }}">
                    <span class="text-sm">🗂️</span>
                    <span>My Reports</span>
                </a>
            @endif

            {{-- Leadership Direct Navigation Pages (Image 3 Overhaul: Direct Tabs, No Hidden Dropdown) --}}
            @if($fullMenu)
                {{-- 6. Report Calendar --}}
                <a href="{{ route('production-reports.calendar') }}" class="{{ $tab('production-reports.calendar') }}">
                    <span class="text-sm">📅</span>
                    <span>Report Calendar</span>
                </a>

                {{-- 7. Media & Attachments --}}
                <a href="{{ route('production-reports.media') }}" class="{{ $tab('production-reports.media') }}">
                    <span class="text-sm">📎</span>
                    <span>Media &amp; Attachments</span>
                </a>

                {{-- 8. Report Archive --}}
                <a href="{{ route('production-reports.archive') }}" class="{{ $tab('production-reports.archive*', 'production-reports.export-range') }}">
                    <span class="text-sm">🗄️</span>
                    <span>Report Archive</span>
                </a>

                {{-- 9. Team Roster & Deadlines --}}
                @if($permissionService->canManageSettings($user))
                    <a href="{{ route('production-reports.settings') }}" class="{{ $tab('production-reports.settings*') }}">
                        <span class="text-sm">⚙️</span>
                        <span>Team Roster &amp; Deadlines</span>
                    </a>
                @endif
            @endif
        </div>
    </nav>
</div>

@if(session('success'))
    <div class="pr-alert pr-alert-success font-medium flex items-center justify-between">
        <span>{{ session('success') }}</span>
        <button type="button" onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
    </div>
@endif

@if(session('error'))
    <div class="pr-alert pr-alert-error font-medium flex items-center justify-between">
        <span>{{ session('error') }}</span>
        <button type="button" onclick="this.parentElement.remove()" class="text-rose-400 hover:text-white">&times;</button>
    </div>
@endif

@if($errors->any())
    <div class="pr-alert pr-alert-error">
        <div class="font-bold mb-1">Please fix the following errors:</div>
        <ul class="list-disc list-inside space-y-0.5 text-xs">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif
