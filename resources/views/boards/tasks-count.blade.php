@extends('layouts.app')
@section('title', 'Tasks Count')
@section('page_title', 'Tasks Count')
@section('meta_description', 'Track and review all assigned tasks across your team planning boards, with 1-day deadline warnings and alerts.')

@section('content')
<div class="animate-fade-in pb-28 md:pb-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-2">

  {{-- ── Hero Banner ──────────────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden rounded-3xl mb-8 shadow-xl shadow-indigo-500/10 border border-indigo-400/20"
       style="background: linear-gradient(135deg, #3730a3 0%, #4f46e5 45%, #6366f1 100%)">
    {{-- Dot matrix overlay --}}
    <div class="absolute inset-0 opacity-15" style="background-image: radial-gradient(circle, #fff 1.25px, transparent 1.25px); background-size: 24px 24px;"></div>
    <div class="absolute -right-16 -bottom-16 w-64 h-64 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>

    <div class="relative flex flex-col xl:flex-row items-start xl:items-center justify-between gap-6 px-6 sm:px-8 py-7">
      
      {{-- Title and icon --}}
      <div class="flex items-center gap-5">
        <div class="flex-shrink-0 w-16 h-16 rounded-2xl flex items-center justify-center bg-white/15 backdrop-blur-md border border-white/20 shadow-inner">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="white" class="w-8 h-8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
          </svg>
        </div>
        <div>
          <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-widest bg-white/20 text-indigo-100 backdrop-blur-sm mb-1.5">
            Personal Dashboard
          </div>
          <h1 class="text-2xl sm:text-3xl font-black text-white leading-tight">Tasks Count</h1>
          <p class="text-xs sm:text-sm text-indigo-100 mt-1 max-w-xl leading-relaxed">
            Live overview of all tasks assigned to you across your team planning boards.
          </p>
        </div>
      </div>

      {{-- Total Summary Cards --}}
      <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">
        {{-- Total Tasks Card --}}
        <div class="bg-white/95 dark:bg-slate-900/90 backdrop-blur-md rounded-2xl px-5 py-3.5 flex items-center gap-4 shadow-md min-w-[8.5rem] border border-white/30 dark:border-slate-800">
          <div class="flex flex-col">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Total Tasks</span>
            <span class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ $totalTasksCount }}</span>
          </div>
          <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
            </svg>
          </div>
        </div>

        {{-- Planning Boards Card --}}
        <div class="bg-white/95 dark:bg-slate-900/90 backdrop-blur-md rounded-2xl px-5 py-3.5 flex items-center gap-4 shadow-md min-w-[8.5rem] border border-white/30 dark:border-slate-800">
          <div class="flex flex-col">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-violet-600 dark:text-violet-400">Planning Boards</span>
            <span class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ count($boardBoxes) }}</span>
          </div>
          <div class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-950/60 flex items-center justify-center text-violet-600 dark:text-violet-400 shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
            </svg>
          </div>
        </div>

        {{-- Due Tomorrow (1 Day Before) Warning Card --}}
        @if($dueTomorrowCount > 0)
        <div class="bg-amber-500 text-white backdrop-blur-md rounded-2xl px-5 py-3.5 flex items-center gap-4 shadow-md min-w-[8.5rem] border border-amber-400/40 animate-pulse">
          <div class="flex flex-col">
            <span class="text-[10px] font-black uppercase tracking-wider text-amber-100">Due in 1 Day</span>
            <span class="text-2xl font-black text-white">{{ $dueTomorrowCount }}</span>
          </div>
          <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
          </div>
        </div>
        @endif

        {{-- Overdue Tasks Card --}}
        @if($overdueCount > 0)
        <div class="bg-rose-500/90 text-white backdrop-blur-md rounded-2xl px-5 py-3.5 flex items-center gap-4 shadow-md min-w-[8.5rem] border border-rose-400/30">
          <div class="flex flex-col">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-100">Overdue</span>
            <span class="text-2xl font-black text-white">{{ $overdueCount }}</span>
          </div>
          <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
          </div>
        </div>
        @endif
      </div>

    </div>
  </div>

  {{-- ── Deadline Warning Alert Banner (Due in 1 Day / Today / Overdue) ── --}}
  @include('partials.deadline-warning-alert')

  {{-- ── Board Boxes Section (Board Name, Month & Amount of Tasks) ───────── --}}
  <div class="mb-10">
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
          </svg>
        </div>
        <h2 class="text-xl font-black text-slate-800 dark:text-slate-100 tracking-tight">Your Planning Boards</h2>
      </div>
      <span class="text-xs text-slate-500 font-medium">Click any board box to filter tasks or open board</span>
    </div>

    @if(empty($boardBoxes))
      <div class="bg-white dark:bg-slate-800 rounded-3xl p-10 text-center border border-slate-200/80 dark:border-slate-700 shadow-sm">
        <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700 mx-auto flex items-center justify-center text-slate-400 mb-4">
          <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
          </svg>
        </div>
        <h3 class="text-base font-bold text-slate-700 dark:text-slate-200">No planning boards found</h3>
        <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">You do not currently belong to any planning boards or have not been assigned any cards yet.</p>
      </div>
    @else
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @foreach($boardBoxes as $box)
          @php
            $isTarget = $selectedBoardId && $selectedBoardId == $box['board']->id;
            
            // Team icon and accents
            $themeClasses = match($box['team_key']) {
                'video'   => ['icon_bg' => 'bg-violet-100 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400', 'badge' => 'bg-violet-50 dark:bg-violet-950/40 text-violet-700 dark:text-violet-300 border-violet-200 dark:border-violet-800'],
                'graphic' => ['icon_bg' => 'bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400', 'badge' => 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800'],
                'listing' => ['icon_bg' => 'bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400', 'badge' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'],
                'content' => ['icon_bg' => 'bg-fuchsia-100 dark:bg-fuchsia-950/60 text-fuchsia-600 dark:text-fuchsia-400', 'badge' => 'bg-fuchsia-50 dark:bg-fuchsia-950/40 text-fuchsia-700 dark:text-fuchsia-300 border-fuchsia-200 dark:border-fuchsia-800'],
                'qc'      => ['icon_bg' => 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400', 'badge' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'],
                'smm'     => ['icon_bg' => 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400', 'badge' => 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800'],
                default   => ['icon_bg' => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400', 'badge' => 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'],
            };
          @endphp

          {{-- ── SINGLE BOARD BOX ── --}}
          <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border {{ $isTarget ? 'ring-2 ring-indigo-500 border-indigo-500' : 'border-slate-200/80 dark:border-slate-700/70' }} shadow-sm hover:shadow-lg hover:border-indigo-300 dark:hover:border-indigo-500/50 transition-all flex flex-col justify-between group">
            
            <div>
              {{-- Top Row: Team Badge + Month --}}
              <div class="flex items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-2 min-w-0">
                  <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $themeClasses['icon_bg'] }}">
                    @if($box['team_key'] === 'video')
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                    @elseif($box['team_key'] === 'graphic')
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
                    @else
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/></svg>
                    @endif
                  </div>
                  <span class="text-[11px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-lg border {{ $themeClasses['badge'] }} truncate">
                    {{ $box['workspace_name'] }}
                  </span>
                </div>

                {{-- Month and Year Badge --}}
                <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 shrink-0">
                  <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  {{ $box['month_year'] }}
                </span>
              </div>

              {{-- Board Name Header --}}
              <h3 class="text-lg font-black text-slate-800 dark:text-slate-100 tracking-tight leading-snug group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors truncate" title="{{ $box['board_name'] }}">
                {{ $box['clean_name'] }}
              </h3>
              <p class="text-xs text-slate-400 dark:text-slate-500 truncate mt-0.5">{{ $box['board_name'] }}</p>

              {{-- ── GIANT TASK COUNT BOX ── --}}
              <div class="my-5 p-4 rounded-2xl {{ $box['task_count'] > 0 ? 'bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/50' : 'bg-slate-50 dark:bg-slate-800/80 border border-slate-100 dark:border-slate-700/50' }} flex items-center justify-between">
                <div>
                  <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-400 block mb-0.5">Tasks Assigned</span>
                  <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black {{ $box['task_count'] > 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                      {{ $box['task_count'] }}
                    </span>
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">task{{ $box['task_count'] == 1 ? '' : 's' }}</span>
                  </div>
                </div>

                @if($box['task_count'] > 0)
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-600 text-white shadow-sm shadow-indigo-500/20">
                    Active
                  </span>
                @else
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                    Up to date
                  </span>
                @endif
              </div>

              {{-- ── Weeks / Lists Breakdown (similar to Approval Queue) ── --}}
              @if(!empty($box['weeks']))
                <div class="space-y-1.5 mb-5 pt-1">
                  <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500 block mb-1">List Distribution</span>
                  @foreach($box['weeks'] as $weekName => $count)
                    <div class="flex items-center justify-between text-xs py-1 px-2.5 rounded-lg bg-slate-50/80 dark:bg-slate-900/40 hover:bg-slate-100 dark:hover:bg-slate-900/70 transition-colors">
                      <span class="font-semibold text-slate-600 dark:text-slate-300 truncate max-w-[150px]">{{ $weekName }}</span>
                      <span class="font-black {{ $count > 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400' }}">
                        {{ $count }}
                      </span>
                    </div>
                  @endforeach
                </div>
              @endif
            </div>

            {{-- ── Footer Action Buttons ── --}}
            <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center gap-2 mt-2">
              <a href="{{ route('tasks.count', ['board_id' => $box['board']->id]) }}"
                 class="flex-1 inline-flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl border {{ $isTarget ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-200' }} text-xs font-bold transition-all">
                <span>{{ $isTarget ? 'Filtering' : 'View Tasks' }}</span>
              </a>

              <a href="{{ route('boards.show', $box['board']) }}"
                 class="inline-flex items-center justify-center gap-1.5 py-2 px-3.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900 text-indigo-700 dark:text-indigo-300 text-xs font-bold transition-colors"
                 title="Open Board">
                <span>Open Board</span>
                <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
              </a>
            </div>

          </div>
        @endforeach
      </div>
    @endif
  </div>

  {{-- ── Detailed Assigned Tasks Section ─────────────────────────────────── --}}
  <div class="mt-12">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h3 class="text-xl font-black text-slate-800 dark:text-slate-100 tracking-tight">Your Assigned Tasks</h3>
        <p class="text-xs text-slate-500 mt-0.5">Click any task to open it directly on the board.</p>
      </div>

      {{-- Board Filter Pills --}}
      <div class="flex items-center gap-2 overflow-x-auto pb-1 max-w-full">
        <a href="{{ route('tasks.count') }}"
           class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 {{ empty($selectedBoardId) ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-500/30' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50' }}">
          All Boards ({{ $totalTasksCount }})
        </a>
        @foreach($boardBoxes as $b)
          <a href="{{ route('tasks.count', ['board_id' => $b['board']->id]) }}"
             class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 {{ $selectedBoardId == $b['board']->id ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-500/30' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50' }}">
            {{ $b['clean_name'] }} ({{ $b['task_count'] }})
          </a>
        @endforeach
      </div>
    </div>

    @if($filteredCards->isEmpty())
      <div class="bg-white dark:bg-slate-800 rounded-3xl p-8 text-center border border-slate-200/80 dark:border-slate-700 shadow-sm">
        <svg class="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m9 12.75 11.25-11.25M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <p class="text-sm font-bold text-slate-700 dark:text-slate-300">No assigned tasks found for this view</p>
        <p class="text-xs text-slate-400 mt-1">All caught up! Check back when new cards are assigned to you.</p>
      </div>
    @else
      <div class="space-y-3">
        @foreach($filteredCards as $card)
          @php
            $isDoneOrApproved = $card->isCompletedOrApproved($approvedSyncGroupIds ?? null);
            $cardDue = $card->due_at ?? ($card->deadline ? \Carbon\Carbon::parse($card->deadline) : null);
            $isOverdue = $cardDue && $cardDue->isPast() && !$cardDue->isToday() && !$isDoneOrApproved;
            $isDueTomorrow = $cardDue && !$isDoneOrApproved && ($cardDue->toDateString() === \Carbon\Carbon::tomorrow()->toDateString() || (!$cardDue->isPast() && !$cardDue->isToday() && \Carbon\Carbon::now()->diffInHours($cardDue, false) <= 36));
            $isDueToday = $cardDue && !$isDoneOrApproved && $cardDue->isToday();
            $totalChecklist = $card->checklists->flatMap->items->count();
            $doneChecklist  = $card->checklists->flatMap->items->where('is_completed', true)->count();
          @endphp
          <a href="{{ route('boards.show', $card->board) }}?card={{ $card->id }}"
             class="block bg-white dark:bg-slate-800 rounded-2xl p-4 sm:p-5 border {{ $isDoneOrApproved ? 'border-slate-200/80 dark:border-slate-700/70 opacity-80 hover:opacity-100' : ($isDueTomorrow ? 'border-amber-300 dark:border-amber-700/60 ring-1 ring-amber-400/30' : ($isOverdue ? 'border-rose-300 dark:border-rose-800' : 'border-slate-200/80 dark:border-slate-700/70')) }} shadow-sm hover:shadow-md hover:border-indigo-400 dark:hover:border-indigo-500/50 transition-all group">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              
              {{-- Left: Task Details --}}
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1.5">
                  {{-- Workspace/Board badge --}}
                  <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-900/50">
                    {{ $card->board?->workspace?->name ?? 'Board' }}
                  </span>
                  {{-- List badge --}}
                  <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                    {{ $card->boardList?->name ?? 'Planning' }}
                  </span>

                  {{-- Completed / Approved Badge --}}
                  @if($isDoneOrApproved)
                    <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700 flex items-center gap-1">
                      <svg class="w-3 h-3 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                      Completed
                    </span>
                  {{-- 1-Day Before Warning Badge --}}
                  @elseif($isDueTomorrow)
                    <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700 flex items-center gap-1 animate-pulse">
                      <svg class="w-3 h-3 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                      Due in 1 Day
                    </span>
                  @elseif($isDueToday)
                    <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-orange-100 dark:bg-orange-950/60 text-orange-800 dark:text-orange-200 border border-orange-300 dark:border-orange-700 flex items-center gap-1">
                      Due Today
                    </span>
                  @elseif($isOverdue)
                    <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-200 border border-rose-300 dark:border-rose-700 flex items-center gap-1">
                      Overdue
                    </span>
                  @endif

                  {{-- Priority --}}
                  @if($card->priority)
                    @php
                      $priorityVal = is_object($card->priority) ? ($card->priority->value ?? '') : (string)$card->priority;
                    @endphp
                    @if($priorityVal)
                      <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded
                        {{ $priorityVal === 'urgent' ? 'bg-rose-100 text-rose-700' : '' }}
                        {{ $priorityVal === 'high' ? 'bg-amber-100 text-amber-700' : '' }}
                        {{ $priorityVal === 'medium' ? 'bg-blue-100 text-blue-700' : '' }}
                        {{ $priorityVal === 'low' ? 'bg-slate-100 text-slate-600' : '' }}">
                        {{ ucfirst($priorityVal) }}
                      </span>
                    @endif
                  @endif
                </div>

                <h4 class="text-sm sm:text-base font-bold text-slate-800 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors truncate">
                  {{ $card->title }}
                </h4>
              </div>

              {{-- Right: Metadata (Checklist, Deadline, Action Arrow) --}}
              <div class="flex items-center gap-4 shrink-0 justify-between sm:justify-end">
                @if($totalChecklist > 0)
                  <div class="flex items-center gap-1.5 text-xs text-slate-500" title="Checklist progress">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span class="font-semibold">{{ $doneChecklist }}/{{ $totalChecklist }}</span>
                  </div>
                @endif

                @if($cardDue)
                  <div class="flex items-center gap-1.5 text-xs {{ $isOverdue ? 'text-rose-600 font-bold' : ($isDueTomorrow ? 'text-amber-600 dark:text-amber-400 font-black' : ($isDueToday ? 'text-orange-600 font-bold' : 'text-slate-500 font-medium')) }}" title="Deadline">
                    <svg class="w-3.5 h-3.5 {{ $isOverdue ? 'text-rose-500' : ($isDueTomorrow ? 'text-amber-500' : ($isDueToday ? 'text-orange-500' : 'text-slate-400')) }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span>
                      @if($isDueTomorrow)
                        Tomorrow, {{ $cardDue->format('M d') }}
                      @elseif($isDueToday)
                        Today
                      @else
                        {{ $cardDue->format('M d, Y') }}
                      @endif
                    </span>
                  </div>
                @endif

                <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-700/60 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-950/60 group-hover:text-indigo-600 flex items-center justify-center text-slate-400 transition-colors">
                  <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                  </svg>
                </div>
              </div>

            </div>
          </a>
        @endforeach
      </div>
    @endif
  </div>

</div>
@endsection
