@extends('layouts.app')
@section('title', 'Approval Queue')
@section('page_title', 'Approval Queue')
@section('meta_description', 'Review and approve or reject tasks submitted by staff and digital team members.')

@section('content')
<div x-data="approvalQueue()" class="animate-fade-in pb-28 md:pb-8">

  {{-- ── Board Sync Selector (Admin / Super-Admin only) ─────────────────────── --}}
  @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital']))
  <div class="card p-4 mb-5" x-data="{ open: false }">
    <div class="flex items-center justify-between cursor-pointer" @click="open = !open">
      <div class="flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4 text-indigo-500">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
        </svg>
        <span class="font-semibold text-slate-700 text-sm">Board Data Filters — Select Which Boards to Include in Stats</span>
        <span class="badge badge-indigo text-[10px]">{{ count($selectedBoardIds) }} / {{ $availableBoards->count() }} selected</span>
      </div>
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
           class="w-4 h-4 text-slate-400 transition-transform" :class="open ? 'rotate-180' : ''">
        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
      </svg>
    </div>

    <div x-show="open" x-transition x-cloak class="mt-4 pt-4 border-t border-slate-100">
      <form method="GET" action="{{ route('approvals.index') }}" id="board-sync-form">
        <input type="hidden" name="period" value="{{ $period }}">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 mb-4">
          @foreach($availableBoards as $board)
          <label class="flex items-center gap-2.5 p-2.5 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer transition-colors {{ in_array($board->id, $selectedBoardIds) ? 'border-indigo-300 bg-indigo-50' : '' }}">
            <input type="checkbox" name="board_ids[]" value="{{ $board->id }}"
                   {{ in_array($board->id, $selectedBoardIds) ? 'checked' : '' }}
                   class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                   onchange="document.getElementById('board-sync-form').submit()">
            <div class="min-w-0">
              <p class="text-xs font-semibold text-slate-700 truncate">{{ $board->name }}</p>
              <p class="text-[10px] text-slate-400 truncate">{{ $board->workspace?->name ?? 'No workspace' }}</p>
            </div>
          </label>
          @endforeach
        </div>
        <div class="flex items-center gap-2">
          <button type="submit" class="btn btn-primary text-xs py-1.5 px-3">Apply Selection</button>
          <a href="{{ route('approvals.index', ['period' => $period]) }}" class="btn btn-secondary text-xs py-1.5 px-3">Select All</a>
        </div>
      </form>
    </div>
  </div>
  @endif

  {{-- ── Pipeline Stats Row ──────────────────────────────────────────────── --}}
  <div class="mobile-scroll-x lg:grid lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
    {{-- Drafting --}}
    <div class="stat-card !p-4 flex items-center justify-between flex-shrink-0 w-[280px] lg:w-auto">
      <div class="flex items-center gap-3">
        <div class="stat-icon" style="background:linear-gradient(135deg,#f3f4f6,#e5e7eb)">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#6b7280" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
        </div>
        <div><div class="stat-value">{{ $stats['drafting']['total'] }}</div><div class="stat-label">Drafting</div></div>
      </div>
      <div class="flex flex-col gap-1 pl-3 border-l border-slate-100 text-[10px] font-bold min-w-[3.5rem]">
        <span class="bg-sky-50 text-sky-600 px-2 py-0.5 rounded text-center">G: {{ $stats['drafting']['graphic'] }}</span>
        <span class="bg-violet-50 text-violet-600 px-2 py-0.5 rounded text-center">V: {{ $stats['drafting']['video'] }}</span>
        <span class="bg-amber-50 text-amber-600 px-2 py-0.5 rounded text-center">L: {{ $stats['drafting']['listing'] }}</span>
        <span class="bg-fuchsia-50 text-fuchsia-600 px-2 py-0.5 rounded text-center">C: {{ $stats['drafting']['content'] }}</span>
        <span class="bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded text-center">Q: {{ $stats['drafting']['qc'] }}</span>
      </div>
    </div>
    {{-- Head Review --}}
    <div class="stat-card !p-4 flex items-center justify-between flex-shrink-0 w-[280px] lg:w-auto">
      <div class="flex items-center gap-3">
        <div class="stat-icon" style="background:linear-gradient(135deg,#e0e7ff,#c7d2fe)">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#4f46e5" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
        </div>
        <div><div class="stat-value">{{ $stats['head_review']['total'] }}</div><div class="stat-label">Head Review</div></div>
      </div>
      <div class="flex flex-col gap-1 pl-3 border-l border-slate-100 text-[10px] font-bold min-w-[3.5rem]">
        <span class="bg-sky-50 text-sky-600 px-2 py-0.5 rounded text-center">G: {{ $stats['head_review']['graphic'] }}</span>
        <span class="bg-violet-50 text-violet-600 px-2 py-0.5 rounded text-center">V: {{ $stats['head_review']['video'] }}</span>
        <span class="bg-amber-50 text-amber-600 px-2 py-0.5 rounded text-center">L: {{ $stats['head_review']['listing'] }}</span>
        <span class="bg-fuchsia-50 text-fuchsia-600 px-2 py-0.5 rounded text-center">C: {{ $stats['head_review']['content'] }}</span>
        <span class="bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded text-center">Q: {{ $stats['head_review']['qc'] }}</span>
      </div>
    </div>
    {{-- QC Review --}}
    <div class="stat-card !p-4 flex items-center justify-between flex-shrink-0 w-[280px] lg:w-auto">
      <div class="flex items-center gap-3">
        <div class="stat-icon" style="background:linear-gradient(135deg,#e0f2fe,#bae6fd)">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#0284c7" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
        </div>
        <div><div class="stat-value">{{ $stats['qc_review']['total'] }}</div><div class="stat-label">QC Review</div></div>
      </div>
      <div class="flex flex-col gap-1 pl-3 border-l border-slate-100 text-[10px] font-bold min-w-[3.5rem]">
        <span class="bg-sky-50 text-sky-600 px-2 py-0.5 rounded text-center">G: {{ $stats['qc_review']['graphic'] }}</span>
        <span class="bg-violet-50 text-violet-600 px-2 py-0.5 rounded text-center">V: {{ $stats['qc_review']['video'] }}</span>
        <span class="bg-amber-50 text-amber-600 px-2 py-0.5 rounded text-center">L: {{ $stats['qc_review']['listing'] }}</span>
        <span class="bg-fuchsia-50 text-fuchsia-600 px-2 py-0.5 rounded text-center">C: {{ $stats['qc_review']['content'] }}</span>
        <span class="bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded text-center">Q: {{ $stats['qc_review']['qc'] }}</span>
      </div>
    </div>
    {{-- Supervisor Review --}}
    <div class="stat-card !p-4 flex items-center justify-between flex-shrink-0 w-[280px] lg:w-auto">
      <div class="flex items-center gap-3">
        <div class="stat-icon" style="background:linear-gradient(135deg,#fef3c7,#fde68a)">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#d97706" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
        </div>
        <div><div class="stat-value">{{ $stats['supervisor_review']['total'] }}</div><div class="stat-label">Supervisor</div></div>
      </div>
      <div class="flex flex-col gap-1 pl-3 border-l border-slate-100 text-[10px] font-bold min-w-[3.5rem]">
        <span class="bg-sky-50 text-sky-600 px-2 py-0.5 rounded text-center">G: {{ $stats['supervisor_review']['graphic'] }}</span>
        <span class="bg-violet-50 text-violet-600 px-2 py-0.5 rounded text-center">V: {{ $stats['supervisor_review']['video'] }}</span>
        <span class="bg-amber-50 text-amber-600 px-2 py-0.5 rounded text-center">L: {{ $stats['supervisor_review']['listing'] }}</span>
        <span class="bg-fuchsia-50 text-fuchsia-600 px-2 py-0.5 rounded text-center">C: {{ $stats['supervisor_review']['content'] }}</span>
        <span class="bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded text-center">Q: {{ $stats['supervisor_review']['qc'] }}</span>
      </div>
    </div>
    {{-- Urgent Priority --}}
    <div class="stat-card !p-4 flex items-center justify-between flex-shrink-0 w-[280px] lg:w-auto">
      <div class="flex items-center gap-3">
        <div class="stat-icon" style="background:linear-gradient(135deg,#fee2e2,#fecaca)">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#dc2626" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
        </div>
        <div>
          <div class="stat-value text-red-600">{{ $stats['urgent']['total'] }}</div>
          <div class="stat-label">Urgent</div>
          <div class="text-[9px] text-slate-400 mt-0.5 leading-tight">Requires action</div>
        </div>
      </div>
      <div class="flex flex-col gap-1 pl-3 border-l border-slate-100 text-[10px] font-bold min-w-[3.5rem]">
        <span class="bg-sky-50 text-sky-600 px-2 py-0.5 rounded text-center">G: {{ $stats['urgent']['graphic'] }}</span>
        <span class="bg-violet-50 text-violet-600 px-2 py-0.5 rounded text-center">V: {{ $stats['urgent']['video'] }}</span>
        <span class="bg-amber-50 text-amber-600 px-2 py-0.5 rounded text-center">L: {{ $stats['urgent']['listing'] }}</span>
        <span class="bg-fuchsia-50 text-fuchsia-600 px-2 py-0.5 rounded text-center">C: {{ $stats['urgent']['content'] }}</span>
        <span class="bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded text-center">Q: {{ $stats['urgent']['qc'] }}</span>
      </div>
    </div>
  </div>

  {{-- ── Completed Tasks Section ──────────────────────────────────────────── --}}
  <div class="card p-5 mb-6">
    {{-- Header + Period Tabs --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
      <div>
        <h3 class="font-display font-black text-slate-800 text-base flex items-center gap-2">
          <span>✅ Completed Tasks</span>
          <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2 py-0.5 rounded-md">
            All Time: {{ $stats['approved_all']['total'] }}
          </span>
        </h3>
        <p class="text-xs text-slate-400 mt-0.5">
          Tasks in the <strong>"Approved"</strong> list — recognised as Supervisor-approved completions.
          {{ count($selectedBoardIds) < $availableBoards->count() ? count($selectedBoardIds).' board(s) selected.' : 'All boards.' }}
        </p>
      </div>
      {{-- Period tabs --}}
      <div class="flex gap-1 bg-slate-100 rounded-xl p-1 flex-shrink-0">
        <a href="{{ route('approvals.index', array_merge(request()->except('period'), ['period' => 'today', 'board_ids' => $selectedBoardIds])) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $period === 'today' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
          Today
        </a>
        <a href="{{ route('approvals.index', array_merge(request()->except('period'), ['period' => 'week', 'board_ids' => $selectedBoardIds])) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $period === 'week' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
          This Week
        </a>
        <a href="{{ route('approvals.index', array_merge(request()->except('period'), ['period' => 'month', 'board_ids' => $selectedBoardIds])) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $period === 'month' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
          This Month
        </a>
      </div>
    </div>

    {{-- Period Label --}}
    @php
      $periodLabel = match($period) {
        'week'  => 'This Week (' . now()->startOfWeek()->format('d M') . ' – ' . now()->endOfWeek()->format('d M Y') . ')',
        'month' => 'This Month (' . now()->format('F Y') . ')',
        default => 'Today — ' . now()->timezone('Asia/Phnom_Penh')->format('d M Y') . ' (Cambodia Time)',
      };
      $breakdown = $stats['approved'];
    @endphp
    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-4">{{ $periodLabel }}</p>

    {{-- Stat Tiles --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      {{-- Total --}}
      <div class="rounded-xl p-4 text-center border-2 border-indigo-200 bg-indigo-50 col-span-2 sm:col-span-1">
        <p class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-500 mb-1">Total</p>
        <p class="text-3xl font-black text-indigo-700">{{ $breakdown['total'] }}</p>
        <p class="text-[10px] text-indigo-400 mt-0.5">Completed</p>
      </div>
      {{-- Graphic --}}
      <div class="rounded-xl p-4 text-center border border-sky-100 bg-sky-50">
        <p class="text-[11px] font-extrabold uppercase tracking-wider text-sky-500 mb-1">Graphic</p>
        <p class="text-2xl font-black text-sky-700">{{ $breakdown['graphic'] }}</p>
      </div>
      {{-- Video --}}
      <div class="rounded-xl p-4 text-center border border-violet-100 bg-violet-50">
        <p class="text-[11px] font-extrabold uppercase tracking-wider text-violet-500 mb-1">Video</p>
        <p class="text-2xl font-black text-violet-700">{{ $breakdown['video'] }}</p>
      </div>
      {{-- Listing --}}
      <div class="rounded-xl p-4 text-center border border-amber-100 bg-amber-50">
        <p class="text-[11px] font-extrabold uppercase tracking-wider text-amber-500 mb-1">Listing</p>
        <p class="text-2xl font-black text-amber-700">{{ $breakdown['listing'] }}</p>
      </div>
      {{-- Content --}}
      <div class="rounded-xl p-4 text-center border border-fuchsia-100 bg-fuchsia-50">
        <p class="text-[11px] font-extrabold uppercase tracking-wider text-fuchsia-500 mb-1">Content</p>
        <p class="text-2xl font-black text-fuchsia-700">{{ $breakdown['content'] }}</p>
      </div>
      {{-- QC --}}
      <div class="rounded-xl p-4 text-center border border-emerald-100 bg-emerald-50">
        <p class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-500 mb-1">QC</p>
        <p class="text-2xl font-black text-emerald-700">{{ $breakdown['qc'] }}</p>
      </div>
    </div>

    {{-- Mini comparison row --}}
    <div class="mt-5 pt-4 border-t border-slate-100">
      <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3">Quick Comparison</p>
      <div class="grid grid-cols-3 gap-3">
        <div class="text-center">
          <p class="text-[10px] text-slate-400 mb-0.5">Today</p>
          <p class="text-xl font-black {{ $period === 'today' ? 'text-indigo-700' : 'text-slate-700' }}">{{ $stats['approved_today']['total'] }}</p>
          <div class="flex justify-center gap-1 mt-1 flex-wrap text-[9px] font-bold">
            <span class="text-sky-600">G:{{ $stats['approved_today']['graphic'] }}</span>
            <span class="text-violet-600">V:{{ $stats['approved_today']['video'] }}</span>
            <span class="text-amber-600">L:{{ $stats['approved_today']['listing'] }}</span>
            <span class="text-fuchsia-600">C:{{ $stats['approved_today']['content'] }}</span>
            <span class="text-emerald-600">Q:{{ $stats['approved_today']['qc'] }}</span>
          </div>
        </div>
        <div class="text-center border-x border-slate-100">
          <p class="text-[10px] text-slate-400 mb-0.5">This Week</p>
          <p class="text-xl font-black {{ $period === 'week' ? 'text-indigo-700' : 'text-slate-700' }}">{{ $stats['approved_week']['total'] }}</p>
          <div class="flex justify-center gap-1 mt-1 flex-wrap text-[9px] font-bold">
            <span class="text-sky-600">G:{{ $stats['approved_week']['graphic'] }}</span>
            <span class="text-violet-600">V:{{ $stats['approved_week']['video'] }}</span>
            <span class="text-amber-600">L:{{ $stats['approved_week']['listing'] }}</span>
            <span class="text-fuchsia-600">C:{{ $stats['approved_week']['content'] }}</span>
            <span class="text-emerald-600">Q:{{ $stats['approved_week']['qc'] }}</span>
          </div>
        </div>
        <div class="text-center">
          <p class="text-[10px] text-slate-400 mb-0.5">This Month</p>
          <p class="text-xl font-black {{ $period === 'month' ? 'text-indigo-700' : 'text-slate-700' }}">{{ $stats['approved_month']['total'] }}</p>
          <div class="flex justify-center gap-1 mt-1 flex-wrap text-[9px] font-bold">
            <span class="text-sky-600">G:{{ $stats['approved_month']['graphic'] }}</span>
            <span class="text-violet-600">V:{{ $stats['approved_month']['video'] }}</span>
            <span class="text-amber-600">L:{{ $stats['approved_month']['listing'] }}</span>
            <span class="text-fuchsia-600">C:{{ $stats['approved_month']['content'] }}</span>
            <span class="text-emerald-600">Q:{{ $stats['approved_month']['qc'] }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ── Overdue Banner ───────────────────────────────────────────────────── --}}
  @if($stats['overdue'] > 0)
  <div class="flex items-center gap-3 bg-rose-50 border border-rose-200 rounded-xl px-5 py-3 mb-6">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#e11d48" class="w-5 h-5 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
    <span class="text-sm font-semibold text-rose-700">{{ $stats['overdue'] }} task{{ $stats['overdue'] !== 1 ? 's are' : ' is' }} overdue across selected boards — please action immediately.</span>
  </div>
  @endif

  {{-- ── Custom Range Filter ──────────────────────────────────────────────── --}}
  <div class="card p-5 mb-8" x-data="customRangeFilter()">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4 mb-4">
      <div>
        <h3 class="font-display font-semibold text-slate-800">Custom Range — Completed Tasks</h3>
        <p class="text-xs text-slate-400 mt-0.5">Select a date range to see how many tasks were moved to the "Approved" list.</p>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <input type="date" x-model="startDate" class="form-input text-xs py-1.5 px-3 rounded-lg border-slate-200 w-32">
        <span class="text-slate-400 font-bold px-1">to</span>
        <input type="date" x-model="endDate" class="form-input text-xs py-1.5 px-3 rounded-lg border-slate-200 w-32">
        <button @click="fetchStats()" class="btn btn-primary text-xs py-1.5 px-3 shadow-sm flex items-center gap-1" :disabled="loading">
          <svg x-show="loading" class="animate-spin -ml-1 mr-1 h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          Check
        </button>
      </div>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3" x-show="hasSearched" x-transition x-cloak>
      <div class="bg-indigo-50 rounded-xl p-4 text-center border-2 border-indigo-200 col-span-2 sm:col-span-1">
        <p class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-500 mb-1">Total</p>
        <p class="text-2xl font-black text-indigo-700" x-text="results.total">0</p>
      </div>
      <div class="bg-sky-50 rounded-xl p-4 text-center border border-sky-100">
        <p class="text-[11px] font-extrabold uppercase tracking-wider text-sky-500 mb-1">Graphic</p>
        <p class="text-2xl font-black text-sky-700" x-text="results.graphic">0</p>
      </div>
      <div class="bg-violet-50 rounded-xl p-4 text-center border border-violet-100">
        <p class="text-[11px] font-extrabold uppercase tracking-wider text-violet-500 mb-1">Video</p>
        <p class="text-2xl font-black text-violet-700" x-text="results.video">0</p>
      </div>
      <div class="bg-amber-50 rounded-xl p-4 text-center border border-amber-100">
        <p class="text-[11px] font-extrabold uppercase tracking-wider text-amber-500 mb-1">Listing</p>
        <p class="text-2xl font-black text-amber-700" x-text="results.listing">0</p>
      </div>
      <div class="bg-fuchsia-50 rounded-xl p-4 text-center border border-fuchsia-100">
        <p class="text-[11px] font-extrabold uppercase tracking-wider text-fuchsia-500 mb-1">Content</p>
        <p class="text-2xl font-black text-fuchsia-700" x-text="results.content">0</p>
      </div>
      <div class="bg-emerald-50 rounded-xl p-4 text-center border border-emerald-100">
        <p class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-500 mb-1">QC</p>
        <p class="text-2xl font-black text-emerald-700" x-text="results.qc">0</p>
      </div>
    </div>
  </div>



  {{-- Toast --}}
  @include('kanban.partials.toast')

</div>
@endsection

@push('scripts')
<script>
const _selectedBoardIds = @json($selectedBoardIds);

function approvalQueue() {
  return {
    showApprove: false,
    showReject:  false,
    activeCardId: null,
    activeTitle:  '',
    rejectReason: '',
    loading: false,

    openApprove(id, title) {
      this.activeCardId = id;
      this.activeTitle  = title;
      this.showApprove  = true;
    },

    openReject(id, title) {
      this.activeCardId = id;
      this.activeTitle  = title;
      this.showReject   = true;
    },

    async submitApprove() {
      this.loading = true;
      try {
        const res = await fetch(`/kanban/cards/${this.activeCardId}/approve`, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
        });
        const data = await res.json();
        if (!res.ok) throw data;
        this.showApprove = false;
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: data.message, type: 'success' } }));
        document.getElementById(`approval-row-${this.activeCardId}`)?.remove();
      } catch(err) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed to approve.', type: 'error' } }));
      } finally {
        this.loading = false;
      }
    },

    async submitReject() {
      if (!this.rejectReason.trim()) return;
      this.loading = true;
      try {
        const res = await fetch(`/kanban/cards/${this.activeCardId}/reject`, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ reason: this.rejectReason }),
        });
        const data = await res.json();
        if (!res.ok) throw data;
        this.showReject   = false;
        this.rejectReason = '';
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: data.message, type: 'success' } }));
        document.getElementById(`approval-row-${this.activeCardId}`)?.remove();
      } catch(err) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed to reject.', type: 'error' } }));
      } finally {
        this.loading = false;
      }
    },
  };
}

function customRangeFilter() {
  return {
    startDate: '',
    endDate: '',
    loading: false,
    hasSearched: false,
    results: { total: 0, graphic: 0, video: 0, listing: 0, content: 0, qc: 0 },

    async fetchStats() {
      if (!this.startDate || !this.endDate) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Please select both start and end dates', type: 'error' } }));
        return;
      }
      if (this.startDate > this.endDate) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'End date must be after start date', type: 'error' } }));
        return;
      }
      this.loading = true;
      try {
        const res = await fetch('{{ route("approvals.custom-range") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({
            start_date: this.startDate,
            end_date: this.endDate,
            board_ids: _selectedBoardIds,
          })
        });
        const data = await res.json();
        if (!res.ok) throw data;
        this.results = data;
        this.hasSearched = true;
      } catch(err) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed to fetch data', type: 'error' } }));
      } finally {
        this.loading = false;
      }
    }
  };
}
</script>
@endpush
