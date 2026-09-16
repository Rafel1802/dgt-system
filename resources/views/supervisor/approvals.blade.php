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

  @php
    $totalGraphic = $stats['drafting']['graphic'] + $stats['head_review']['graphic'] + $stats['qc_review']['graphic'] + $stats['supervisor_review']['graphic'];
    $totalVideo = $stats['drafting']['video'] + $stats['head_review']['video'] + $stats['qc_review']['video'] + $stats['supervisor_review']['video'];
    $totalListing = $stats['drafting']['listing'] + $stats['head_review']['listing'] + $stats['qc_review']['listing'] + $stats['supervisor_review']['listing'];
    $totalContent = $stats['drafting']['content'] + $stats['head_review']['content'] + $stats['qc_review']['content'] + $stats['supervisor_review']['content'];
    $totalQc = $stats['drafting']['qc'] + $stats['head_review']['qc'] + $stats['qc_review']['qc'] + $stats['supervisor_review']['qc'];
  @endphp

  {{-- ── Hero Banner ──────────────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden rounded-2xl mb-6 sticky top-[80px] z-30 shadow-lg shadow-indigo-200/50" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 40%, #818cf8 100%)">
    {{-- Pattern overlay --}}
    <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle, #fff 1px, transparent 1px); background-size: 28px 28px;"></div>
    <div class="relative flex flex-col xl:flex-row items-start xl:items-center justify-between gap-5 px-7 py-6">
      
      <div class="flex items-center gap-5">
        <div class="flex-shrink-0 w-16 h-16 rounded-2xl flex items-center justify-center" style="background:rgba(255,255,255,0.15); backdrop-filter:blur(8px)">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="white" class="w-8 h-8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
          </svg>
        </div>
        <div>
          <p class="text-[11px] font-bold uppercase tracking-widest text-indigo-200 mb-0.5">Pipeline Overview</p>
          <h2 class="text-3xl font-black text-white leading-tight">All Card Status</h2>
          <p class="text-sm text-indigo-100 mt-1 max-w-lg">Live snapshot of every task across all workflow boards — from drafting through to supervisor approval.</p>
        </div>
      </div>

      {{-- Total Badges --}}
      <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">
        <div class="bg-white rounded-xl p-3 flex items-center justify-between gap-4 min-w-[7.5rem] shadow-sm">
          <div class="flex flex-col">
            <svg class="w-4 h-4 text-sky-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
            <span class="text-[9px] font-bold uppercase tracking-wider text-sky-700">Graphic</span>
          </div>
          <span class="text-2xl font-black text-sky-700">{{ $totalGraphic }}</span>
        </div>
        
        <div class="bg-white rounded-xl p-3 flex items-center justify-between gap-4 min-w-[7.5rem] shadow-sm">
          <div class="flex flex-col">
            <svg class="w-4 h-4 text-violet-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" /></svg>
            <span class="text-[9px] font-bold uppercase tracking-wider text-violet-700">Video</span>
          </div>
          <span class="text-2xl font-black text-violet-700">{{ $totalVideo }}</span>
        </div>

        <div class="bg-white rounded-xl p-3 flex items-center justify-between gap-4 min-w-[7.5rem] shadow-sm">
          <div class="flex flex-col">
            <svg class="w-4 h-4 text-amber-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
            <span class="text-[9px] font-bold uppercase tracking-wider text-amber-700">Listing</span>
          </div>
          <span class="text-2xl font-black text-amber-700">{{ $totalListing }}</span>
        </div>

        <div class="bg-white rounded-xl p-3 flex items-center justify-between gap-4 min-w-[7.5rem] shadow-sm">
          <div class="flex flex-col">
            <svg class="w-4 h-4 text-fuchsia-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Zm3.75 11.625a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
            <span class="text-[9px] font-bold uppercase tracking-wider text-fuchsia-700">Content</span>
          </div>
          <span class="text-2xl font-black text-fuchsia-700">{{ $totalContent }}</span>
        </div>

        <div class="bg-white rounded-xl p-3 flex items-center justify-between gap-4 min-w-[7.5rem] shadow-sm">
          <div class="flex flex-col">
            <svg class="w-4 h-4 text-emerald-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            <span class="text-[9px] font-bold uppercase tracking-wider text-emerald-700">QC</span>
          </div>
          <span class="text-2xl font-black text-emerald-700">{{ $totalQc }}</span>
        </div>
      </div>
      
    </div>
  </div>

  {{-- ── Pipeline Stats (Masonry Grid) ──────────────────────────────────────── --}}
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 mb-8" id="pipeline-stats-container">
    
    {{-- Left Side: Drafting, Head Review, Supervisor Review --}}
    <div class="lg:col-span-7 xl:col-span-8 flex flex-col gap-5">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        {{-- Drafting --}}
        <div class="bg-white rounded-3xl p-6 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] border border-slate-100/60 flex flex-col gap-5 justify-center">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-slate-100/80">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#64748b" class="w-7 h-7"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
            </div>
            <div class="flex flex-col">
              <span class="text-[2.25rem] font-black text-slate-800 leading-none">{{ $stats['drafting']['total'] }}</span>
              <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mt-1">Drafting</span>
            </div>
          </div>
          <div class="w-20 border-b-2 border-slate-100 mx-auto mt-2"></div>
          <div class="flex items-center gap-2 flex-wrap justify-center mt-2">
            <span class="bg-sky-50/80 text-sky-600 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">G: {{ $stats['drafting']['graphic'] }}</span>
            <span class="bg-violet-50/80 text-violet-600 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">V: {{ $stats['drafting']['video'] }}</span>
            <span class="bg-amber-50/80 text-amber-600 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">L: {{ $stats['drafting']['listing'] }}</span>
            <span class="bg-fuchsia-50/80 text-fuchsia-600 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">C: {{ $stats['drafting']['content'] }}</span>
            <span class="bg-emerald-50/80 text-emerald-600 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">Q: {{ $stats['drafting']['qc'] }}</span>
          </div>
        </div>

        {{-- Head Review --}}
        <div class="bg-white rounded-3xl p-6 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] border border-slate-100/60 flex flex-col gap-5 justify-center">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-indigo-100/60">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#4f46e5" class="w-7 h-7"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
            </div>
            <div class="flex flex-col">
              <span class="text-[2.25rem] font-black text-slate-800 leading-none">{{ $stats['head_review']['total'] }}</span>
              <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mt-1">Head Review</span>
            </div>
          </div>
          <div class="w-20 border-b-2 border-slate-100 mx-auto mt-2"></div>
          <div class="flex items-center gap-2 flex-wrap justify-center mt-2">
            <span class="bg-sky-50/80 text-sky-600 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">G: {{ $stats['head_review']['graphic'] }}</span>
            <span class="bg-violet-50/80 text-violet-600 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">V: {{ $stats['head_review']['video'] }}</span>
            <span class="bg-amber-50/80 text-amber-600 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">L: {{ $stats['head_review']['listing'] }}</span>
            <span class="bg-fuchsia-50/80 text-fuchsia-600 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">C: {{ $stats['head_review']['content'] }}</span>
            <span class="bg-emerald-50/80 text-emerald-600 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">Q: {{ $stats['head_review']['qc'] }}</span>
          </div>
        </div>
      </div>
      
      @if(auth()->user() && auth()->user()->isSupervisorRole() && !auth()->user()->isQc())
      {{-- QC Review (Wide) --}}
      <div class="bg-white rounded-[2rem] p-6 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] border border-indigo-100/60 flex flex-col sm:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-5">
          <div class="w-16 h-16 rounded-[1.25rem] flex items-center justify-center bg-indigo-100/70">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#4f46e5" class="w-8 h-8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
          </div>
          <div class="flex flex-col">
            <span class="text-[3rem] font-black text-slate-800 leading-none">{{ $stats['qc_review']['total'] }}</span>
            <span class="text-[12px] font-bold text-slate-500 uppercase tracking-widest mt-1">QC Review</span>
          </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap justify-end">
          <span class="bg-sky-50 text-sky-600 px-4 py-2 rounded-xl text-sm font-bold shadow-sm">G: {{ $stats['qc_review']['graphic'] }}</span>
          <span class="bg-violet-50 text-violet-600 px-4 py-2 rounded-xl text-sm font-bold shadow-sm">V: {{ $stats['qc_review']['video'] }}</span>
          <span class="bg-amber-50 text-amber-600 px-4 py-2 rounded-xl text-sm font-bold shadow-sm">L: {{ $stats['qc_review']['listing'] }}</span>
          <span class="bg-fuchsia-50 text-fuchsia-600 px-4 py-2 rounded-xl text-sm font-bold shadow-sm">C: {{ $stats['qc_review']['content'] }}</span>
          <span class="bg-emerald-50 text-emerald-600 px-4 py-2 rounded-xl text-sm font-bold shadow-sm">Q: {{ $stats['qc_review']['qc'] }}</span>
        </div>
      </div>
      @else
      {{-- Supervisor Review --}}
      <div class="bg-white rounded-[2rem] p-6 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] border border-amber-100/60 flex flex-col sm:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-5">
          <div class="w-16 h-16 rounded-[1.25rem] flex items-center justify-center bg-amber-100/50">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#d97706" class="w-8 h-8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
          </div>
          <div class="flex flex-col">
            <span class="text-[3rem] font-black text-slate-800 leading-none">{{ $stats['supervisor_review']['total'] }}</span>
            <span class="text-[12px] font-bold text-slate-500 uppercase tracking-widest mt-1">Supervisor Review</span>
          </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap justify-end">
          <span class="bg-sky-50 text-sky-600 px-4 py-2 rounded-xl text-sm font-bold shadow-sm">G: {{ $stats['supervisor_review']['graphic'] }}</span>
          <span class="bg-violet-50 text-violet-600 px-4 py-2 rounded-xl text-sm font-bold shadow-sm">V: {{ $stats['supervisor_review']['video'] }}</span>
          <span class="bg-amber-50 text-amber-600 px-4 py-2 rounded-xl text-sm font-bold shadow-sm">L: {{ $stats['supervisor_review']['listing'] }}</span>
          <span class="bg-fuchsia-50 text-fuchsia-600 px-4 py-2 rounded-xl text-sm font-bold shadow-sm">C: {{ $stats['supervisor_review']['content'] }}</span>
          <span class="bg-emerald-50 text-emerald-600 px-4 py-2 rounded-xl text-sm font-bold shadow-sm">Q: {{ $stats['supervisor_review']['qc'] }}</span>
        </div>
      </div>
      @endif
    
{{-- ── Planning Boards Stats ────────────────────────────────────────── --}}
  @if(!empty($smmPlanningStats))
  <div class="mb-8 mt-2">
    <div class="flex items-center gap-2 mb-4">
      <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
      <h3 class="text-lg font-black text-slate-800 tracking-tight">Team Planning Boards</h3>
    </div>
    
    <div class="flex flex-col gap-4">
      @foreach($smmPlanningStats as $key => $teamStat)
        <div class="stat-card !p-6 flex items-start justify-between gap-6 flex-shrink-0 w-full hover:shadow-md transition-shadow bg-white border border-slate-200">
          <div class="flex items-start gap-4">
            @php
              $iconBg = match($key) {
                'graphic' => 'bg-sky-100 text-sky-600',
                'video' => 'bg-violet-100 text-violet-600',
                'listing' => 'bg-amber-100 text-amber-600',
                'content' => 'bg-fuchsia-100 text-fuchsia-600',
                'qc' => 'bg-emerald-100 text-emerald-600',
                default => 'bg-slate-100 text-slate-600'
              };
            @endphp
            <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 {{ $iconBg }}">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M3 4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8l-7.5 4L2 8V6a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2z"/></svg>
            </div>
            <div class="flex flex-col pt-0.5">
              <span class="text-lg font-black text-slate-800 leading-tight uppercase">{{ $teamStat['name'] }}</span>
              <span class="text-xs font-bold text-slate-400 mt-1 uppercase tracking-wider">{{ $teamStat['board_name'] }}</span>
            </div>
          </div>
          
          <div class="flex flex-col gap-3 pl-6 border-l-2 border-slate-100 flex-1">
            @foreach($teamStat['weeks'] as $weekName => $weekData)
              <div class="flex items-center gap-3 text-sm">
                <span class="font-bold text-slate-500 uppercase w-20 shrink-0">{{ $weekName }}:</span>
                <span class="font-bold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-lg border border-emerald-100 shadow-sm" title="Approved">Approved: {{ $weekData['approved'] }}</span>
                <span class="font-bold text-slate-600 bg-slate-50 px-3 py-1 rounded-lg border border-slate-200 shadow-sm" title="Unapproved">Unapproved: {{ $weekData['unapproved'] }}</span>
              </div>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>
  </div>
  @endif

  
    </div>

    @if(auth()->user() && auth()->user()->isSupervisorRole() && !auth()->user()->isQc())
    {{-- Right Side: Supervisor Review (Giant Box) --}}
    @php
      $isSupervisor = auth()->user() && auth()->user()->isSupervisorRole();
      $supClass = $isSupervisor ? 'ring-2 ring-amber-500 shadow-xl scale-[1.02] transform z-10 bg-white border-none' : 'bg-white shadow-sm border border-slate-100/50';
    @endphp
    <div class="lg:col-span-5 xl:col-span-4 sticky top-[240px] z-20 self-start">
      <div class="rounded-[2.5rem] !p-10 flex flex-col justify-center items-center text-center hover:shadow-[0_8px_30px_-4px_rgba(0,0,0,0.1)] transition-all {{ $supClass }}">
        
        <div class="w-24 h-24 mb-8 flex items-center justify-center rounded-[2rem] bg-amber-100/70">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#d97706" class="w-12 h-12"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
        </div>
        
        <div class="text-[6.5rem] font-black text-amber-600 leading-none mb-3">{{ $stats['supervisor_review']['total'] }}</div>
        <div class="text-xl font-black text-slate-800 uppercase tracking-widest mb-4">Supervisor Review</div>
        @if($isSupervisor)
          <div class="mb-10"><span class="bg-amber-500 text-white px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-widest shadow-md">Your Queue</span></div>
        @else
          <div class="mb-10"><span class="text-slate-400 text-sm font-medium">Tasks awaiting supervisor approval</span></div>
        @endif

        <div class="w-24 border-b-2 border-slate-100 mx-auto mb-6"></div>

        <div class="flex items-center gap-3 flex-wrap justify-center w-full">
          <a href="{{ $boardLinks['graphic'] ? route('boards.show', $boardLinks['graphic']) : '#' }}" class="flex flex-col items-center gap-1 group cursor-pointer" title="Open Graphic Board">
            <span class="text-sky-600 font-bold text-sm bg-sky-50 px-3 py-1.5 rounded-lg shadow-sm group-hover:bg-sky-100 group-hover:scale-105 transition-all">Graphic</span>
            <span class="text-lg font-black text-slate-800">{{ $stats['supervisor_review']['graphic'] }}</span>
          </a>
          <a href="{{ $boardLinks['video'] ? route('boards.show', $boardLinks['video']) : '#' }}" class="flex flex-col items-center gap-1 group cursor-pointer" title="Open Video Board">
            <span class="text-violet-600 font-bold text-sm bg-violet-50 px-3 py-1.5 rounded-lg shadow-sm group-hover:bg-violet-100 group-hover:scale-105 transition-all">Video</span>
            <span class="text-lg font-black text-slate-800">{{ $stats['supervisor_review']['video'] }}</span>
          </a>
          <a href="{{ $boardLinks['listing'] ? route('boards.show', $boardLinks['listing']) : '#' }}" class="flex flex-col items-center gap-1 group cursor-pointer" title="Open Listing Board">
            <span class="text-amber-600 font-bold text-sm bg-amber-50 px-3 py-1.5 rounded-lg shadow-sm group-hover:bg-amber-100 group-hover:scale-105 transition-all">Listing</span>
            <span class="text-lg font-black text-slate-800">{{ $stats['supervisor_review']['listing'] }}</span>
          </a>
          <a href="{{ $boardLinks['content'] ? route('boards.show', $boardLinks['content']) : '#' }}" class="flex flex-col items-center gap-1 group cursor-pointer" title="Open Content Board">
            <span class="text-fuchsia-600 font-bold text-sm bg-fuchsia-50 px-3 py-1.5 rounded-lg shadow-sm group-hover:bg-fuchsia-100 group-hover:scale-105 transition-all">Content</span>
            <span class="text-lg font-black text-slate-800">{{ $stats['supervisor_review']['content'] }}</span>
          </a>
          <a href="{{ $boardLinks['qc'] ? route('boards.show', $boardLinks['qc']) : '#' }}" class="flex flex-col items-center gap-1 group cursor-pointer" title="Open QC Board">
            <span class="text-emerald-600 font-bold text-sm bg-emerald-50 px-3 py-1.5 rounded-lg shadow-sm group-hover:bg-emerald-100 group-hover:scale-105 transition-all">QC</span>
            <span class="text-lg font-black text-slate-800">{{ $stats['supervisor_review']['qc'] }}</span>
          </a>
        </div>
      </div>
    </div>
    @else
    {{-- Right Side: QC Review (Giant Box) --}}
    @php
      $isQc = auth()->user() && auth()->user()->isQc();
      $qcClass = $isQc ? 'ring-2 ring-indigo-500 shadow-xl scale-[1.02] transform z-10 bg-white border-none' : 'bg-white shadow-sm border border-slate-100/50';
    @endphp
    <div class="lg:col-span-5 xl:col-span-4 sticky top-[240px] z-20 self-start">
      <div class="rounded-[2.5rem] !p-10 flex flex-col justify-center items-center text-center hover:shadow-[0_8px_30px_-4px_rgba(0,0,0,0.1)] transition-all {{ $qcClass }}">
        
        <div class="w-24 h-24 mb-8 flex items-center justify-center rounded-[2rem] bg-indigo-100/70">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#4f46e5" class="w-12 h-12"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
        </div>
        
        <div class="text-[6.5rem] font-black text-indigo-900 leading-none mb-3">{{ $stats['qc_review']['total'] }}</div>
        <div class="text-xl font-black text-slate-800 uppercase tracking-widest mb-4">QC Review</div>
        @if($isQc)
          <div class="mb-10"><span class="bg-indigo-600 text-white px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-widest shadow-md">Your Queue</span></div>
        @else
          <div class="mb-10"><span class="text-slate-400 text-sm font-medium">Tasks awaiting quality control review</span></div>
        @endif

        <div class="w-24 border-b-2 border-slate-100 mx-auto mb-6"></div>

        <div class="flex items-center gap-3 flex-wrap justify-center w-full">
          <a href="{{ $boardLinks['graphic'] ? route('boards.show', $boardLinks['graphic']) : '#' }}" class="flex flex-col items-center gap-1 group cursor-pointer" title="Open Graphic Board">
            <span class="text-sky-600 font-bold text-sm bg-sky-50 px-3 py-1.5 rounded-lg shadow-sm group-hover:bg-sky-100 group-hover:scale-105 transition-all">Graphic</span>
            <span class="text-lg font-black text-slate-800">{{ $stats['qc_review']['graphic'] }}</span>
          </a>
          <a href="{{ $boardLinks['video'] ? route('boards.show', $boardLinks['video']) : '#' }}" class="flex flex-col items-center gap-1 group cursor-pointer" title="Open Video Board">
            <span class="text-violet-600 font-bold text-sm bg-violet-50 px-3 py-1.5 rounded-lg shadow-sm group-hover:bg-violet-100 group-hover:scale-105 transition-all">Video</span>
            <span class="text-lg font-black text-slate-800">{{ $stats['qc_review']['video'] }}</span>
          </a>
          <a href="{{ $boardLinks['listing'] ? route('boards.show', $boardLinks['listing']) : '#' }}" class="flex flex-col items-center gap-1 group cursor-pointer" title="Open Listing Board">
            <span class="text-amber-600 font-bold text-sm bg-amber-50 px-3 py-1.5 rounded-lg shadow-sm group-hover:bg-amber-100 group-hover:scale-105 transition-all">Listing</span>
            <span class="text-lg font-black text-slate-800">{{ $stats['qc_review']['listing'] }}</span>
          </a>
          <a href="{{ $boardLinks['content'] ? route('boards.show', $boardLinks['content']) : '#' }}" class="flex flex-col items-center gap-1 group cursor-pointer" title="Open Content Board">
            <span class="text-fuchsia-600 font-bold text-sm bg-fuchsia-50 px-3 py-1.5 rounded-lg shadow-sm group-hover:bg-fuchsia-100 group-hover:scale-105 transition-all">Content</span>
            <span class="text-lg font-black text-slate-800">{{ $stats['qc_review']['content'] }}</span>
          </a>
          <a href="{{ $boardLinks['qc'] ? route('boards.show', $boardLinks['qc']) : '#' }}" class="flex flex-col items-center gap-1 group cursor-pointer" title="Open QC Board">
            <span class="text-emerald-600 font-bold text-sm bg-emerald-50 px-3 py-1.5 rounded-lg shadow-sm group-hover:bg-emerald-100 group-hover:scale-105 transition-all">QC</span>
            <span class="text-lg font-black text-slate-800">{{ $stats['qc_review']['qc'] }}</span>
          </a>
        </div>
      </div>
    </div>
    @endif
  {{-- ── Overdue Banner ───────────────────────────────────────────────────── --}}
  @if($stats['overdue'] > 0)
  <div class="flex items-center gap-3 bg-rose-50 border border-rose-200 rounded-xl px-5 py-3 mb-6">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#e11d48" class="w-5 h-5 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
    <span class="text-sm font-semibold text-rose-700">{{ $stats['overdue'] }} task{{ $stats['overdue'] !== 1 ? 's are' : ' is' }} overdue across selected boards — please action immediately.</span>
  </div>
  @endif



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

    refreshTimer: null,
    
    init() {
      // Connect to Pusher for real-time dashboard updates when cards move on selected boards
      if (typeof window.kiuqGetPusherClient === 'function') {
        const pusher = window.kiuqGetPusherClient();
        if (pusher && Array.isArray(_selectedBoardIds)) {
          _selectedBoardIds.forEach(boardId => {
            const channel = pusher.subscribe(`private-boards.${boardId}`);
            channel.bind('board.updated', (data) => {
              this.scheduleRefresh();
            });
          });
        }
      }
    },

    scheduleRefresh() {
      if (this.refreshTimer) clearTimeout(this.refreshTimer);
      // Wait a short time to batch rapid rapid board updates
      this.refreshTimer = setTimeout(() => {
        this.fetchLatestStats();
      }, 1000);
    },

    async fetchLatestStats() {
      try {
        const url = new URL(window.location.href);
        // Add a parameter to bypass full page cache if any
        url.searchParams.set('t', Date.now());
        const res = await fetch(url.toString(), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const text = await res.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(text, 'text/html');
        
        const currentContainer = document.getElementById('pipeline-stats-container');
        const newContainer = doc.getElementById('pipeline-stats-container');
        
        if (currentContainer && newContainer) {
          currentContainer.innerHTML = newContainer.innerHTML;
        }
      } catch (err) {
        console.error('Failed to auto-refresh stats:', err);
      }
    },

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


@endpush
