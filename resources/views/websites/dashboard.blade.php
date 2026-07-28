@extends('layouts.app')
@section('title', 'All Websites Dashboard')
@section('page_title', 'All Websites Dashboard')

@section('content')
<style>
/* ══════════════════════════════════════════════════════════════════
   ALL WEBSITES EXECUTIVE DASHBOARD — Custom Styles
══════════════════════════════════════════════════════════════════ */

/* ── Animated Counter ── */
@keyframes countUp { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
.metric-counter { animation: countUp 0.5s ease both; }

/* ── Glow Pulse ── */
@keyframes glowPulse { 0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,0.4)} 50%{box-shadow:0 0 0 8px rgba(99,102,241,0)} }
.glow-indigo { animation: glowPulse 2.5s ease-in-out infinite; }
@keyframes glowPulseGreen { 0%,100%{box-shadow:0 0 0 0 rgba(16,185,129,0.4)} 50%{box-shadow:0 0 0 8px rgba(16,185,129,0)} }
.glow-green { animation: glowPulseGreen 2.5s ease-in-out infinite; }
@keyframes glowPulseBlue { 0%,100%{box-shadow:0 0 0 0 rgba(59,130,246,0.4)} 50%{box-shadow:0 0 0 8px rgba(59,130,246,0)} }
.glow-blue { animation: glowPulseBlue 2.5s ease-in-out infinite; }
@keyframes glowPulseAmber { 0%,100%{box-shadow:0 0 0 0 rgba(245,158,11,0.4)} 50%{box-shadow:0 0 0 8px rgba(245,158,11,0)} }
.glow-amber { animation: glowPulseAmber 2.5s ease-in-out infinite; }
@keyframes glowPulseRed { 0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,0.4)} 50%{box-shadow:0 0 0 8px rgba(239,68,68,0)} }
.glow-red { animation: glowPulseRed 2s ease-in-out infinite; }

/* ── Filter Pill ── */
.filter-pill {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.375rem 0.875rem; border-radius: 9999px;
    font-size: 0.8125rem; font-weight: 600; cursor: pointer;
    border: 1.5px solid transparent; transition: all 0.18s ease;
    background: var(--card-bg, #fff); color: var(--text-secondary, #64748b);
    border-color: var(--border-color, #e2e8f0);
}
.filter-pill:hover { border-color: #818cf8; color: #4f46e5; background: #eef2ff; }
.filter-pill.active { background: #4f46e5; color: #fff; border-color: #4f46e5; box-shadow: 0 2px 12px rgba(79,70,229,0.3); }
[data-theme="dark"] .filter-pill { background: #1e293b; color: #94a3b8; border-color: #334155; }
[data-theme="dark"] .filter-pill:hover { border-color: #818cf8; color: #a5b4fc; background: #1e2a4a; }
[data-theme="dark"] .filter-pill.active { background: #4f46e5; color: #fff; border-color: #4f46e5; }

/* ── Metric Card ── */
.metric-card {
    border-radius: 1rem; padding: 1rem;
    display: flex; flex-direction: column; gap: 0.5rem;
    border: 1.5px solid transparent;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    position: relative; overflow: hidden;
}
@media (min-width: 640px) {
    .metric-card {
        border-radius: 1.25rem; padding: 1.5rem 1.25rem;
    }
}
.metric-card:hover { transform: translateY(-2px); }
.metric-card::before {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
    pointer-events: none;
}

/* ── Website Card ── */
.ws-dash-card {
    background: var(--card-bg, #fff);
    border: 1.5px solid var(--border-color, #e2e8f0);
    border-radius: 1.25rem;
    transition: all 0.25s ease;
    overflow: hidden;
    display: flex; flex-direction: column;
}
.ws-dash-card:hover { box-shadow: 0 12px 40px rgba(0,0,0,0.1); transform: translateY(-3px); }
[data-theme="dark"] .ws-dash-card { background: #0f172a; border-color: #1e293b; }
[data-theme="dark"] .ws-dash-card:hover { box-shadow: 0 12px 40px rgba(0,0,0,0.4); }

/* ── Status Matrix Bar ── */
.status-segment { height: 8px; transition: width 0.5s ease; }
.status-bar-track { height: 8px; border-radius: 9999px; overflow: hidden; display: flex; gap: 1px; background: var(--bg-page, #f1f5f9); }

/* ── Pillar Badge ── */
.pillar-badge {
    display: inline-flex; align-items: center; gap: 0.25rem;
    padding: 0.1875rem 0.5rem; border-radius: 9999px;
    font-size: 0.6875rem; font-weight: 700; letter-spacing: 0.01em;
}
.pillar-on  { background: #d1fae5; color: #065f46; }
.pillar-off { background: #f1f5f9; color: #94a3b8; }
[data-theme="dark"] .pillar-on  { background: #064e3b; color: #6ee7b7; }
[data-theme="dark"] .pillar-off { background: #1e293b; color: #475569; }

/* ── QC Bubble ── */
.qc-bubble {
    display: inline-flex; flex-direction: column; align-items: center;
    padding: 0.375rem 0.75rem; border-radius: 0.75rem; min-width: 52px;
    font-weight: 800;
}
.qc-bubble-count { font-size: 1.125rem; line-height: 1; }
.qc-bubble-label { font-size: 0.625rem; font-weight: 600; letter-spacing: 0.04em; margin-top: 2px; opacity: 0.85; }
.qc-new   { background: #fee2e2; color: #b91c1c; }
.qc-fixed { background: #fef3c7; color: #b45309; }
.qc-ok    { background: #d1fae5; color: #065f46; }
[data-theme="dark"] .qc-new   { background: #450a0a; color: #fca5a5; }
[data-theme="dark"] .qc-fixed { background: #422006; color: #fcd34d; }
[data-theme="dark"] .qc-ok    { background: #064e3b; color: #6ee7b7; }

/* ── Empty State ── */
.ws-empty-state { background: var(--card-bg, #fff); border: 1.5px dashed var(--border-color, #e2e8f0); border-radius: 1.25rem; }
[data-theme="dark"] .ws-empty-state { background: #0f172a; border-color: #334155; }

/* ── Custom Range Inputs ── */
.date-range-inputs input[type="date"] {
    padding: 0.375rem 0.75rem; border-radius: 0.625rem; font-size: 0.8125rem;
    border: 1.5px solid var(--border-color, #e2e8f0);
    background: var(--card-bg, #fff); color: var(--text-primary, #0f172a);
}
[data-theme="dark"] .date-range-inputs input[type="date"] {
    background: #1e293b; border-color: #334155; color: #f1f5f9;
    color-scheme: dark;
}

/* ── Filter bar ── */
.filter-section { background: var(--card-bg, #fff); border: 1.5px solid var(--border-color, #e2e8f0); border-radius: 1rem; padding: 1rem; }
@media (min-width: 640px) {
    .filter-section { border-radius: 1.25rem; padding: 1rem 1.25rem; }
}
[data-theme="dark"] .filter-section { background: #0f172a; border-color: #1e293b; }

/* ── Website card header accent ── */
.ws-card-accent { height: 4px; width: 100%; }

/* Completion ring */
.ring-track { stroke: var(--ring-track, #e2e8f0); }
.ring-fill { stroke-dasharray: 125.66; stroke-linecap: round; transform: rotate(-90deg); transform-origin: center; transition: stroke-dashoffset 0.8s ease; }
[data-theme="dark"] .ring-track { stroke: #334155; }
</style>

{{-- Alpine.js data component --}}
<div x-data="websitesDashboard()" x-init="init()" x-cloak class="pb-28 md:pb-8">

{{-- ════════════════════════════════════════════════════════════════
     SECTION A: GLOBAL HEADER METRICS
════════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">

    {{-- Total Open Issues --}}
    <div class="metric-card" style="background:linear-gradient(135deg,#fef2f2,#fee2e2);border-color:#fca5a5;">
        <div class="flex items-center justify-between">
            <span class="text-[10px] sm:text-xs font-bold text-rose-700 uppercase tracking-wide leading-tight">Open Issues</span>
            <div class="w-7 h-7 sm:w-9 sm:h-9 rounded-lg sm:rounded-xl flex items-center justify-center glow-red flex-shrink-0 ml-1" style="background:#ef4444;">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
            </div>
        </div>
        <div class="metric-counter text-2xl sm:text-4xl font-black text-rose-700 mt-1">{{ number_format($globalMetrics['total_open_issues']) }}</div>
        <div class="text-[10px] sm:text-xs text-rose-600 font-medium leading-snug mt-auto">Tasks still in "To Do"</div>
    </div>

    {{-- Overall Completion Rate --}}
    <div class="metric-card" style="background:linear-gradient(135deg,#f0fdf4,#d1fae5);border-color:#6ee7b7;">
        <div class="flex items-center justify-between">
            <span class="text-[10px] sm:text-xs font-bold text-emerald-700 uppercase tracking-wide leading-tight">Completion Rate</span>
            <div class="w-7 h-7 sm:w-9 sm:h-9 rounded-lg sm:rounded-xl flex items-center justify-center glow-green flex-shrink-0 ml-1" style="background:#10b981;">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </div>
        </div>
        <div class="metric-counter text-2xl sm:text-4xl font-black text-emerald-700 mt-1">{{ $globalMetrics['overall_completion_rate'] }}<span class="text-lg sm:text-2xl">%</span></div>
        <div class="text-[10px] sm:text-xs text-emerald-600 font-medium leading-snug mt-auto">Approved &amp; Done across all sites</div>
    </div>

    {{-- Active Blog Schedules --}}
    <div class="metric-card" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border-color:#93c5fd;">
        <div class="flex items-center justify-between">
            <span class="text-[10px] sm:text-xs font-bold text-blue-700 uppercase tracking-wide leading-tight">Blog Schedules</span>
            <div class="w-7 h-7 sm:w-9 sm:h-9 rounded-lg sm:rounded-xl flex items-center justify-center glow-blue flex-shrink-0 ml-1" style="background:#3b82f6;">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z"/></svg>
            </div>
        </div>
        <div class="metric-counter text-2xl sm:text-4xl font-black text-blue-700 mt-1">{{ number_format($globalMetrics['active_blog_schedules']) }}</div>
        <div class="text-[10px] sm:text-xs text-blue-600 font-medium leading-snug mt-auto">Active blog tasks</div>
    </div>

    {{-- Pending Plugin Updates --}}
    <div class="metric-card" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border-color:#fcd34d;">
        <div class="flex items-center justify-between">
            <span class="text-[10px] sm:text-xs font-bold text-amber-700 uppercase tracking-wide leading-tight">Plugin Tasks</span>
            <div class="w-7 h-7 sm:w-9 sm:h-9 rounded-lg sm:rounded-xl flex items-center justify-center glow-amber flex-shrink-0 ml-1" style="background:#f59e0b;">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.39 48.39 0 0 1-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 0 1-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 0 0-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 0 1-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 0 0 .657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 0 1-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.401.604-.401.959v0c0 .333.277.599.61.58a48.1 48.1 0 0 0 5.427-.63 48.05 48.05 0 0 0 .582-4.717.532.532 0 0 0-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.959.401v0a.656.656 0 0 0 .658-.663 48.422 48.422 0 0 0-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 0 1-.61-.58v0Z"/></svg>
            </div>
        </div>
        <div class="metric-counter text-2xl sm:text-4xl font-black text-amber-700 mt-1">{{ number_format($globalMetrics['pending_plugin_updates']) }}</div>
        <div class="text-[10px] sm:text-xs text-amber-600 font-medium leading-snug mt-auto">Bug Fix &amp; Plugin cards in scope</div>
    </div>

</div>

{{-- ════════════════════════════════════════════════════════════════
     SECTION B: LIVE FILTER BAR
════════════════════════════════════════════════════════════════ --}}




<div class="filter-section mb-6">
    <!-- Mobile Filter Toggle Button -->
    <button type="button" @click="mobileFiltersOpen = !mobileFiltersOpen" class="sm:hidden w-full flex items-center justify-between bg-indigo-50 text-indigo-600 px-4 py-3 rounded-xl font-bold mb-4">
        <span class="flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/></svg>
            Toggle Filters
        </span>
        <svg class="w-5 h-5 transition-transform" :class="mobileFiltersOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </button>

    <form id="dashFilterForm" method="GET" action="{{ route('websites.dashboard') }}"
          class="hidden sm:block" :class="mobileFiltersOpen ? '!block' : ''">

        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">

            {{-- Date Scope Pills --}}
            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wide mr-1 w-full sm:w-auto mb-1 sm:mb-0">Period</span>
                <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                    @foreach(['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'custom' => '📅 Custom'] as $scope => $label)
                    <button type="button"
                            id="scope-pill-{{ $scope }}"
                            @click="setScope('{{ $scope }}')"
                            :class="activeScope === '{{ $scope }}' ? 'filter-pill active' : 'filter-pill'"
                            class="filter-pill flex-1 justify-center sm:flex-none {{ $filters['date_scope'] === $scope ? 'active' : '' }}">
                        {{ $label }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Custom Date Range --}}
            <div class="date-range-inputs flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto" x-show="activeScope === 'custom'" x-cloak>
                <input type="date" name="date_from" id="date_from"
                       value="{{ $dateFrom }}"
                       x-model="dateFrom"
                       class="form-input text-sm rounded-xl py-2 w-full sm:w-auto">
                <span class="text-slate-400 text-sm font-medium hidden sm:inline">→</span>
                <input type="date" name="date_to" id="date_to"
                       value="{{ $dateTo }}"
                       x-model="dateTo"
                       class="form-input text-sm rounded-xl py-2 w-full sm:w-auto mt-2 sm:mt-0">
            </div>

            {{-- Hidden scope input --}}
            <input type="hidden" name="date_scope" :value="activeScope">

        </div>

        <div class="flex flex-col sm:flex-row sm:flex-wrap items-start sm:items-center gap-4 mt-4 pt-4 border-t border-[var(--border-color)]">

            {{-- Team Member Dropdown --}}
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Member</span>
                <select name="member_id" id="member_id_filter" class="form-select text-sm rounded-xl py-2 w-full sm:min-w-[160px]">
                    <option value="">All Members</option>
                    @foreach($members as $member)
                    <option value="{{ $member->id }}" {{ ($filters['member_id'] ?? '') == $member->id ? 'selected' : '' }}>
                        {{ $member->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Site Filter --}}
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto flex-1">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wide whitespace-nowrap">Sites</span>
                <div class="flex flex-wrap gap-1.5 max-h-[120px] sm:max-h-none overflow-y-auto w-full">
                    @foreach($allWebsites as $ws)
                    <label class="inline-flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" name="site_ids[]"
                               value="{{ $ws->id }}"
                               {{ in_array($ws->id, $filters['site_ids'] ?? []) ? 'checked' : '' }}
                               class="rounded text-indigo-600 w-3.5 h-3.5 focus:ring-indigo-500">
                        <span class="text-xs font-medium text-slate-600">{{ $ws->name }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Apply + Export Buttons --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto sm:ml-auto mt-2 sm:mt-0">
                <button type="submit" id="btn-apply-filters"
                        class="btn btn-primary flex justify-center items-center gap-2 text-sm py-2 px-4 w-full sm:w-auto">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/></svg>
                    Apply Filters
                </button>
                <a id="btn-export-csv"
                   href="{{ route('websites.dashboard.export', array_filter(array_merge($filters, ['date_from' => $dateFrom, 'date_to' => $dateTo]))) }}"
                   class="btn btn-secondary flex justify-center items-center gap-2 text-sm py-2 px-4 w-full sm:w-auto">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Export CSV
                </a>
            </div>

        </div>

    </form>
</div>

{{-- ════════════════════════════════════════════════════════════════
     SECTION C: DATE RANGE SUMMARY BAR
════════════════════════════════════════════════════════════════ --}}
<div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-5">
    <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
        <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
        Showing data from
        <span class="font-bold text-indigo-600">{{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }}</span>
        to
        <span class="font-bold text-indigo-600">{{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}</span>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        @if($filters['member_id'])
        <span class="px-2.5 py-1 bg-violet-100 text-violet-700 rounded-full text-xs font-bold">
            👤 {{ $members->firstWhere('id', $filters['member_id'])?->name ?? 'Member Filter' }}
        </span>
        @endif
        <span class="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-bold">
            {{ $totalWebsites }} {{ Str::plural('site', $totalWebsites) }}
        </span>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════
     SECTION D: WEBSITE CARDS GRID
════════════════════════════════════════════════════════════════ --}}
@if($websiteCards->isEmpty())
    <div class="ws-empty-state p-16 text-center">
        <div class="text-6xl mb-4">🌐</div>
        <h3 class="text-xl font-black text-slate-800 mb-2">No websites found</h3>
        <p class="text-slate-500">Add websites in the Website List first, or adjust your filters.</p>
        <a href="{{ route('websites.index') }}" class="btn btn-primary mt-6 inline-flex items-center gap-2">
            Go to Website List
        </a>
    </div>
@else
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
    @foreach($websiteCards as $idx => $wd)
    @php
        /** @var \App\Models\Website $website */
        $website = $wd['website'];
        $hasBoards = $wd['has_boards'];
        $sm = $wd['status_matrix'];
        $pillars = $wd['pillars'];
        $qc = $wd['qc_badges'];
        $totalCards = $wd['total_cards'];
        $completion = $wd['completion_rate'];

        // Status bar widths
        $smTotal = max(1, array_sum($sm));
        $smWidths = [
            'todo'        => round(($sm['todo'] / $smTotal) * 100),
            'in_progress' => round(($sm['in_progress'] / $smTotal) * 100),
            'review'      => round(($sm['review'] / $smTotal) * 100),
            'approved'    => round(($sm['approved'] / $smTotal) * 100),
            'done'        => round(($sm['done'] / $smTotal) * 100),
            'rejected'    => round(($sm['rejected'] / $smTotal) * 100),
        ];

        // Completion ring dashoffset (circumference = 125.66)
        $ringOffset = 125.66 - (125.66 * $completion / 100);
        $ringColor = match(true) {
            $completion >= 80 => '#10b981',
            $completion >= 50 => '#3b82f6',
            $completion >= 25 => '#f59e0b',
            default           => '#ef4444',
        };

        $accentColor = match(true) {
            $completion >= 80 => '#10b981',
            $completion >= 50 => '#6366f1',
            $completion >= 25 => '#f59e0b',
            default           => '#ef4444',
        };
    @endphp

    <div class="ws-dash-card" style="animation-delay: {{ $idx * 0.05 }}s">

        {{-- Accent Top Bar --}}
        <div class="ws-card-accent" style="background: linear-gradient(90deg, {{ $accentColor }}, {{ $accentColor }}88);"></div>

        {{-- Card Header --}}
        <div class="p-4 sm:p-5 flex items-start gap-3 sm:gap-4">
            {{-- Logo --}}
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center p-1.5 flex-shrink-0 shadow-sm">
                @if($website->logo_path)
                    <img src="{{ $website->logo_src }}" alt="{{ $website->name }}" class="max-w-full max-h-full object-contain">
                @else
                    <span class="text-xl sm:text-2xl">🌐</span>
                @endif
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-bold text-slate-800 text-sm leading-tight truncate">{{ $website->name }}</h3>
                    @if($website->status === \App\Models\Website::STATUS_LIVE)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
                            LIVE
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">{{ $website->status }}</span>
                    @endif
                </div>
                <a href="{{ $website->url }}" target="_blank" rel="noopener noreferrer"
                   class="text-[11px] sm:text-xs text-indigo-500 hover:text-indigo-700 truncate block mt-0.5">
                    {{ $website->clean_domain }}
                </a>
                @if($website->handler)
                    <span class="text-[10px] text-slate-400 flex items-center gap-1 mt-1">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                        {{ $website->handler->name }}
                    </span>
                @endif
            </div>

            {{-- Completion Ring --}}
            <div class="flex flex-col items-center flex-shrink-0">
                <svg width="40" height="40" class="sm:w-[44px] sm:h-[44px]" viewBox="0 0 44 44">
                    <circle class="ring-track" cx="22" cy="22" r="20" fill="none" stroke-width="4"/>
                    <circle class="ring-fill" cx="22" cy="22" r="20" fill="none"
                            stroke="{{ $ringColor }}" stroke-width="4"
                            stroke-dashoffset="{{ $ringOffset }}"/>
                </svg>
                <span class="text-[10px] font-bold text-slate-500 -mt-1">{{ $completion }}%</span>
            </div>
        </div>

        @if(!$hasBoards)
        {{-- No boards found --}}
        <div class="px-4 sm:px-5 pb-4 sm:pb-5">
            <div class="bg-slate-50 rounded-xl p-3 text-center border border-slate-100">
                <p class="text-[11px] sm:text-xs text-slate-400">No boards linked to this website yet.</p>
                <p class="text-[9px] sm:text-[10px] text-slate-300 mt-1">Board name must contain "{{ $website->name }}"</p>
            </div>
        </div>
        @else

        {{-- Status Matrix Bar --}}
        <div class="px-4 sm:px-5 pb-3">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Status Matrix</span>
                <span class="text-[10px] text-slate-400">{{ $totalCards }} task{{ $totalCards !== 1 ? 's' : '' }}</span>
            </div>
            <div class="status-bar-track">
                @if($smWidths['todo'] > 0)
                    <div class="status-segment bg-slate-300 rounded-l-full" style="width:{{ $smWidths['todo'] }}%" title="To Do: {{ $sm['todo'] }}"></div>
                @endif
                @if($smWidths['in_progress'] > 0)
                    <div class="status-segment bg-blue-400" style="width:{{ $smWidths['in_progress'] }}%" title="In Progress: {{ $sm['in_progress'] }}"></div>
                @endif
                @if($smWidths['review'] > 0)
                    <div class="status-segment bg-amber-400" style="width:{{ $smWidths['review'] }}%" title="Review: {{ $sm['review'] }}"></div>
                @endif
                @if($smWidths['approved'] > 0)
                    <div class="status-segment bg-emerald-400" style="width:{{ $smWidths['approved'] }}%" title="Approved: {{ $sm['approved'] }}"></div>
                @endif
                @if($smWidths['done'] > 0)
                    <div class="status-segment bg-violet-400 {{ ($smWidths['rejected'] == 0) ? 'rounded-r-full' : '' }}" style="width:{{ $smWidths['done'] }}%" title="Done: {{ $sm['done'] }}"></div>
                @endif
                @if($smWidths['rejected'] > 0)
                    <div class="status-segment bg-rose-400 rounded-r-full" style="width:{{ $smWidths['rejected'] }}%" title="Rejected: {{ $sm['rejected'] }}"></div>
                @endif
            </div>
            {{-- Legend --}}
            <div class="flex flex-wrap gap-2 mt-2">
                @foreach([
                    ['color'=>'bg-slate-300','label'=>'To Do','count'=>$sm['todo']],
                    ['color'=>'bg-blue-400','label'=>'In Prog','count'=>$sm['in_progress']],
                    ['color'=>'bg-amber-400','label'=>'Review','count'=>$sm['review']],
                    ['color'=>'bg-emerald-400','label'=>'Approved','count'=>$sm['approved']],
                    ['color'=>'bg-violet-400','label'=>'Done','count'=>$sm['done']],
                    ['color'=>'bg-rose-400','label'=>'Rejected','count'=>$sm['rejected']],
                ] as $seg)
                @if($seg['count'] > 0)
                <span class="inline-flex items-center gap-1 text-[10px] text-slate-500">
                    <span class="w-2 h-2 rounded-full {{ $seg['color'] }} inline-block"></span>
                    {{ $seg['label'] }}: <strong>{{ $seg['count'] }}</strong>
                </span>
                @endif
                @endforeach
            </div>
        </div>

        {{-- Divider --}}
        <div class="mx-4 sm:mx-5 border-t border-[var(--border-color)] mb-3"></div>

        {{-- 4 Pillars --}}
        <div class="px-4 sm:px-5 pb-3">
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-2">Follow-up Pillars</div>
            <div class="flex flex-wrap gap-1.5">
                <span class="pillar-badge {{ $pillars['ebay_click'] ? 'pillar-on' : 'pillar-off' }}">
                    {{ $pillars['ebay_click'] ? '✓' : '○' }} eBay Click
                </span>
                <span class="pillar-badge {{ $pillars['optimization'] ? 'pillar-on' : 'pillar-off' }}">
                    {{ $pillars['optimization'] ? '✓' : '○' }} Optimization
                </span>
                <span class="pillar-badge {{ $pillars['blogs'] ? 'pillar-on' : 'pillar-off' }}">
                    {{ $pillars['blogs'] ? '✓' : '○' }} Blogs
                </span>
                <span class="pillar-badge {{ $pillars['plugins'] ? 'pillar-on' : 'pillar-off' }}">
                    {{ $pillars['plugins'] ? '✓' : '○' }} Plugins
                </span>
            </div>
        </div>

        {{-- Divider --}}
        <div class="mx-4 sm:mx-5 border-t border-[var(--border-color)] mb-3"></div>

        {{-- QC Badges --}}
        <div class="px-4 sm:px-5 pb-4 sm:pb-5">
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-2">Quality Check Badges</div>
            <div class="flex gap-2">
                <div class="qc-bubble qc-new flex-1">
                    <span class="qc-bubble-count">{{ $qc['new_issue'] }}</span>
                    <span class="qc-bubble-label">NEW ISSUE</span>
                </div>
                <div class="qc-bubble qc-fixed flex-1">
                    <span class="qc-bubble-count">{{ $qc['fixed'] }}</span>
                    <span class="qc-bubble-label">FIXED</span>
                </div>
                <div class="qc-bubble qc-ok flex-1">
                    <span class="qc-bubble-count">{{ $qc['approved'] }}</span>
                    <span class="qc-bubble-label">APPROVED</span>
                </div>
            </div>
        </div>

        @endif {{-- /hasBoards --}}

        {{-- Card Footer --}}
        <div class="mt-auto border-t border-[var(--border-color)] px-4 sm:px-5 py-3 flex flex-wrap items-center justify-between gap-2 bg-slate-50/50">
            <a href="{{ $website->url }}" target="_blank" rel="noopener noreferrer"
               class="text-[11px] sm:text-xs font-semibold text-indigo-500 hover:text-indigo-700 flex items-center gap-1">
                Visit Site
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
            </a>
            <div class="flex flex-wrap items-center gap-2 text-right justify-end">
                @if($wd['has_remote'])
                    @php
                        $schedule = $wd['followup_schedule'];
                        $scheduleLabel = '';
                        if ($schedule) {
                            $scheduleLabel = ucfirst($schedule['type'] ?? '');
                            if (!empty($schedule['weekdays'])) {
                                $days = array_map(fn($d) => ['','Mon','Tue','Wed','Thu','Fri','Sat','Sun'][$d] ?? $d, $schedule['weekdays']);
                                $scheduleLabel .= ' (' . implode(', ', $days) . ')';
                            }
                        }
                    @endphp
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] sm:text-[10px] font-bold bg-indigo-100 text-indigo-700" title="Source: kiuq.kiuq.net">
                        🔗 Remote DB
                    </span>
                    @if($scheduleLabel)
                    <span class="text-[9px] sm:text-[10px] text-slate-400 hidden sm:inline">{{ $scheduleLabel }}</span>
                    @endif
                @endif
                <span class="text-[9px] sm:text-[10px] text-slate-400">{{ $wd['boards_count'] }} {{ Str::plural('board', $wd['boards_count']) }}</span>
            </div>
        </div>

    </div>
    @endforeach
</div>
@endif

</div>{{-- /x-data --}}

@push('scripts')
<script>
function websitesDashboard() {
    return {
        activeScope: '{{ $filters['date_scope'] }}',
        dateFrom: '{{ $dateFrom }}',
        dateTo: '{{ $dateTo }}',
        mobileFiltersOpen: false,

        init() {
            // Sync scope pills on load
            this.setScope(this.activeScope);
        },

        setScope(scope) {
            this.activeScope = scope;
            // Update export link to reflect current filter state
            this.$nextTick(() => this.syncExportLink());
        },

        syncExportLink() {
            const form = document.getElementById('dashFilterForm');
            if (!form) return;
            const data = new FormData(form);
            const params = new URLSearchParams(data).toString();
            const exportBtn = document.getElementById('btn-export-csv');
            if (exportBtn) {
                const baseUrl = '{{ route('websites.dashboard.export') }}';
                exportBtn.href = baseUrl + '?' + params;
            }
        },
    };
}
</script>
@endpush

@endsection
