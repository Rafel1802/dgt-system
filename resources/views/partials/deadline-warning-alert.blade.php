{{-- ── Deadline Warning Alert Banner (Due in 1 Day / Today / Overdue) ── --}}
@if(!empty($totalWarningCount) && $totalWarningCount > 0)
<div class="relative overflow-hidden rounded-3xl mb-8 border border-amber-300 dark:border-amber-500/40 bg-gradient-to-r from-amber-50 via-orange-50/70 to-amber-50 dark:from-amber-950/40 dark:via-slate-900 dark:to-amber-950/30 p-6 sm:p-7 shadow-lg shadow-amber-500/5">
  
  {{-- Decorative ambient glow --}}
  <div class="absolute top-0 right-0 -mt-8 -mr-8 w-48 h-48 bg-amber-400/20 dark:bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>

  {{-- Banner Header --}}
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-amber-200/70 dark:border-amber-800/40">
    <div class="flex items-center gap-3.5">
      {{-- Pulsing Warning Bell --}}
      <div class="relative flex items-center justify-center shrink-0">
        <span class="animate-ping absolute inline-flex h-11 w-11 rounded-2xl bg-amber-400/60 dark:bg-amber-500/40"></span>
        <div class="relative w-11 h-11 rounded-2xl bg-amber-500 text-white flex items-center justify-center shadow-md shadow-amber-500/30">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
          </svg>
        </div>
      </div>
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h2 class="text-lg sm:text-xl font-black text-amber-950 dark:text-amber-200 tracking-tight">
            Deadline Warning Alert
          </h2>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-black bg-amber-500 text-white shadow-sm">
            {{ $totalWarningCount }} task{{ $totalWarningCount == 1 ? '' : 's' }} alert
          </span>
        </div>
        <p class="text-xs sm:text-sm text-amber-800/90 dark:text-amber-300/80 mt-0.5">
          The following tasks are due within 1 day (tomorrow) or require urgent attention:
        </p>
      </div>
    </div>

    {{-- Quick counts breakdown pills + Link to Tasks Count if on Dashboard --}}
    <div class="flex items-center gap-2 flex-wrap">
      @if(!empty($dueTomorrowCount) && $dueTomorrowCount > 0)
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-100 dark:bg-amber-900/60 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700 shadow-sm">
          <svg class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
          <span>Due Tomorrow: {{ $dueTomorrowCount }}</span>
        </span>
      @endif
      @if(!empty($dueTodayCount) && $dueTodayCount > 0)
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-orange-100 dark:bg-orange-900/60 text-orange-900 dark:text-orange-200 border border-orange-300 dark:border-orange-700 shadow-sm">
          <span>Due Today: {{ $dueTodayCount }}</span>
        </span>
      @endif
      @if(!empty($overdueCount) && $overdueCount > 0)
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-rose-100 dark:bg-rose-900/60 text-rose-900 dark:text-rose-200 border border-rose-300 dark:border-rose-700 shadow-sm">
          <span>Overdue: {{ $overdueCount }}</span>
        </span>
      @endif

      @if(!request()->routeIs('tasks.count') && Route::has('tasks.count'))
        <a href="{{ route('tasks.count') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-black bg-amber-500 hover:bg-amber-600 text-white shadow-sm transition-colors ml-auto">
          <span>Tasks Count</span>
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
        </a>
      @endif
    </div>
  </div>

  {{-- Alert Tasks Cards Grid (Telling Task Name, Board Name & Due Date) --}}
  <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-3.5">
    @foreach($warningTasks as $warn)
      <div class="bg-white/95 dark:bg-slate-800/95 rounded-2xl p-4 sm:p-4.5 border border-amber-200/80 dark:border-slate-700 shadow-sm hover:shadow-md transition-all flex flex-col justify-between group">
        <div>
          {{-- Top meta: Board, List and Due Warning Badge --}}
          <div class="flex items-center justify-between gap-2 mb-2 flex-wrap">
            <div class="flex items-center gap-1.5 truncate text-[11px] font-bold text-slate-500 dark:text-slate-400">
              <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">{{ $warn['workspace_name'] }}</span>
              <span>•</span>
              <span class="truncate">{{ $warn['list_name'] }}</span>
            </div>
            {{-- Warning pill --}}
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider
              {{ $warn['badge_color'] === 'rose' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800' : '' }}
              {{ $warn['badge_color'] === 'orange' ? 'bg-orange-100 text-orange-700 dark:bg-orange-950/60 dark:text-orange-300 border border-orange-200 dark:border-orange-800' : '' }}
              {{ $warn['badge_color'] === 'amber' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800' : '' }}">
              <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
              <span>{{ $warn['badge_text'] }}</span>
            </span>
          </div>

          {{-- Task Title / Name --}}
          <h4 class="text-sm font-black text-slate-800 dark:text-slate-100 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors line-clamp-2">
            <a href="{{ $warn['open_url'] }}" title="Open {{ $warn['title'] }}">
              {{ $warn['title'] }}
            </a>
          </h4>
        </div>

        {{-- Footer with exact due date & action button --}}
        <div class="mt-3.5 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-3 text-xs">
          <div class="flex items-center gap-1.5 text-amber-700 dark:text-amber-400 font-bold">
            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
            <span>Due: {{ $warn['due_formatted'] }}</span>
          </div>

          <a href="{{ $warn['open_url'] }}"
             class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-black text-xs shadow-sm transition-colors shrink-0">
            <span>Open Task</span>
            <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
          </a>
        </div>
      </div>
    @endforeach
  </div>

</div>
@endif
