@extends('layouts.app')
@section('title', 'Personal Report')
@section('page_title', 'Personal Report')

@section('content')
@php
    $currentUser = auth()->user();
    $isDara = $currentUser?->isDara() ?? false;
    $isKim = $currentUser?->isKim() ?? false;
    $isDaraOrKim = $currentUser?->isDaraOrKim() ?? ($isDara || $isKim);

    // Filter reviewer users: ONLY Mr. Dara (Head) and Mr. KimOun (Head)
    // Strictly exclude any admin variant (e.g. Mr. Dara Vuthy (admin)) and Somalika as requested
    $allUsers = isset($users) ? $users : \App\Models\User::where('is_active', true)->orderBy('name')->get();
    $filteredReviewers = $allUsers->filter(function($u) {
        $n = strtolower($u->name ?? '');
        $un = strtolower($u->username ?? '');
        if (str_contains($n, 'admin') || str_contains($un, 'admin')) {
            return false;
        }
        if (str_contains($n, 'somalika') || str_contains($un, 'somalika')) {
            return false;
        }
        return str_contains($n, 'dara') || str_contains($un, 'dara')
            || str_contains($n, 'kim') || str_contains($un, 'kim')
            || in_array($u->id, [12, 13, 24]);
    })->values();

    // Determine initial reviewer ID and Team
    $defaultReviewer = null;
    if ($isKim) {
        $defaultReviewer = $filteredReviewers->first(fn($u) => $u->isKim());
        $initialTeam = 'B';
    } else {
        $defaultReviewer = $filteredReviewers->first(fn($u) => $u->isDara());
        $initialTeam = 'A';
    }
    if (!$defaultReviewer && $filteredReviewers->isNotEmpty()) {
        $defaultReviewer = $filteredReviewers->first();
        $initialTeam = str_contains(strtolower($defaultReviewer->name . ' ' . $defaultReviewer->username), 'kim') ? 'B' : 'A';
    }
    $initialReviewerId = $defaultReviewer?->id ?? '';
@endphp

<div class="animate-fade-in space-y-8 pb-32" x-data="{ 
    dateRange: 'today',
    reportType: 'kanban',
    showHiddenBoards: false,
    restoringSlug: null,
    currentReviewerTeam: '{{ $initialTeam }}',
    selectedReviewerId: '{{ $initialReviewerId }}',
    autoTickTeamBoards(team) {
        this.currentReviewerTeam = team;
        document.querySelectorAll('input[name=\'board_ids[]\']').forEach(cb => {
            const boardTeam = cb.getAttribute('data-team');
            const isHidden = cb.getAttribute('data-hidden') === '1';
            if (!isHidden) {
                if (team === 'A') {
                    cb.checked = (boardTeam === 'A');
                } else if (team === 'B') {
                    cb.checked = (boardTeam === 'B');
                }
            }
        });
    },
    onReviewerChange(event) {
        const select = event.target;
        const opt = select.selectedOptions ? select.selectedOptions[0] : null;
        const team = opt ? opt.getAttribute('data-team') : (this.selectedReviewerId == {{ $filteredReviewers->first(fn($u) => $u->isKim())?->id ?? 999999 }} ? 'B' : 'A');
        this.autoTickTeamBoards(team || 'A');
    },
    selectAll(workspaceId, checked) {
        document.querySelectorAll(`.workspace-${workspaceId}-board`).forEach(cb => {
            // Only toggle checkboxes that are currently visible
            const parentLabel = cb.closest('label');
            if (!parentLabel || parentLabel.offsetParent !== null || window.getComputedStyle(parentLabel).display !== 'none') {
                cb.checked = checked;
            }
        });
    },
    async restoreBoard(slug, btn) {
        if (!confirm('Are you sure you want to restore this board back to active boards?')) return;
        this.restoringSlug = slug;
        try {
            const res = await fetch(`/boards/${slug}/toggle-hidden`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data && data.success) {
                if (window.Notyf) {
                    new Notyf().success('Board restored back successfully!');
                }
                setTimeout(() => window.location.reload(), 500);
            } else {
                alert('Could not restore board.');
            }
        } catch (e) {
            console.error(e);
            alert('Failed to restore board.');
        } finally {
            this.restoringSlug = null;
        }
    },
    getExportUrl() {
        if (this.reportType === 'social_media') return '{{ route('boards.reports.personal.social_media.export') }}';
        if (this.reportType === 'website') return '{{ route('boards.reports.personal.website.export') }}';
        if (this.reportType === 'follow_up') return '{{ route('boards.reports.personal.follow_up.export') }}';
        return '{{ route('boards.reports.personal.export') }}';
    }
}">

  <div class="flex items-center justify-between mb-3">
    <div class="flex items-start gap-4">
      <div>
        <h1 class="text-3xl font-display font-black text-slate-800 dark:text-white tracking-tight">Personal Report</h1>
        <p class="text-base text-slate-500 dark:text-slate-400 mt-1">Consolidated multi-department report compilation for QC and Supervisors.</p>
      </div>
    </div>
  </div>

  <form :action="getExportUrl()" method="GET" target="_blank" data-turbo="false" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <input type="hidden" name="is_personal_report" value="1">
      
      {{-- Main Content Area (Left) --}}
      <div class="lg:col-span-2 space-y-8">
          <div class="bg-white dark:bg-gray-900 rounded-3xl border border-slate-200 dark:border-gray-800 p-7 md:p-8 shadow-sm">
              <h2 class="text-xl font-black text-slate-800 dark:text-white mb-5 flex items-center gap-3">
                  <span class="text-2xl">📋</span> Select Report Type
              </h2>

              @if($isDaraOrKim)
                  {{-- For Mr. Dara & Mr. Kim: ONLY Board Report (Website, Follow-up, and Social Media are removed) --}}
                  <input type="hidden" name="report_type" value="kanban">
                  <div class="relative overflow-hidden p-6 md:p-7 rounded-2xl border-2 border-indigo-500 bg-gradient-to-br from-indigo-50/90 via-white to-blue-50/60 dark:from-indigo-950/40 dark:via-gray-900 dark:to-blue-950/20 shadow-md mb-8 transition-all">
                      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-5">
                          <div class="flex items-center gap-5">
                              <div class="w-16 h-16 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-600/30 flex-shrink-0">
                                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-9 h-9">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
                                  </svg>
                              </div>
                              <div>
                                  <div class="flex items-center gap-3 flex-wrap">
                                      <h3 class="text-xl md:text-2xl font-black text-slate-800 dark:text-white tracking-tight">Board Report</h3>
                                      <span class="inline-flex items-center px-3.5 py-1 rounded-full text-xs font-black bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-700 shadow-xs">
                                          <span x-text="currentReviewerTeam === 'B' ? 'Team B Report' : 'Team A Report'">{{ $initialTeam === 'B' ? 'Team B Report' : 'Team A Report' }}</span>
                                      </span>
                                  </div>
                                  <p class="text-sm md:text-base text-slate-600 dark:text-slate-300 mt-1 font-medium">
                                      Consolidated workflow cards review & checklist task compilation.
                                  </p>
                              </div>
                          </div>
                          <div class="flex items-center gap-2 self-start sm:self-center px-3.5 py-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 text-xs font-black shadow-xs">
                              <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                              <span>Selected</span>
                          </div>
                      </div>
                  </div>
              @else
                  {{-- Other Users: Multiple Report Types --}}
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                      {{-- Board / Kanban --}}
                      <label class="flex flex-col items-center justify-center gap-2.5 p-4 md:p-5 rounded-2xl border-2 cursor-pointer transition-all select-none group" 
                             :class="reportType === 'kanban' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 shadow-md transform scale-[1.02]' : 'border-slate-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-500 hover:bg-slate-50 dark:hover:bg-gray-800'">
                          <input type="radio" name="report_type" value="kanban" x-model="reportType" class="hidden">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-9 h-9 group-hover:scale-110 transition-transform" :class="reportType === 'kanban' ? 'text-indigo-600' : 'text-slate-500'">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
                          </svg>
                          <span class="text-sm font-bold text-slate-800 dark:text-white text-center">Board<br>
                              @if(auth()->user()?->isQc())
                                  <span class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold">
                                      Checking Report
                                  </span>
                              @else
                                  <span class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold">Supervisor Report</span>
                              @endif
                          </span>
                      </label>

                      {{-- Website Status --}}
                      <label class="flex flex-col items-center justify-center gap-2.5 p-4 md:p-5 rounded-2xl border-2 cursor-pointer transition-all select-none group" 
                             :class="reportType === 'website' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 shadow-md transform scale-[1.02]' : 'border-slate-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-500 hover:bg-slate-50 dark:hover:bg-gray-800'">
                          <input type="radio" name="report_type" value="website" x-model="reportType" class="hidden">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-9 h-9 group-hover:scale-110 transition-transform" :class="reportType === 'website' ? 'text-indigo-600' : 'text-slate-500'">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253M3 12a8.959 8.959 0 0 0 .284 2.253" />
                          </svg>
                          <span class="text-sm font-bold text-slate-800 dark:text-white text-center">Website<br><span class="text-xs text-indigo-600 dark:text-indigo-400">Report</span></span>
                      </label>

                      {{-- Follow-Up --}}
                      @unless(auth()->user()?->isSupervisorRole() && !auth()->user()?->isQc())
                      <label class="flex flex-col items-center justify-center gap-2.5 p-4 md:p-5 rounded-2xl border-2 cursor-pointer transition-all select-none group" 
                             :class="reportType === 'follow_up' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 shadow-md transform scale-[1.02]' : 'border-slate-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-500 hover:bg-slate-50 dark:hover:bg-gray-800'">
                          <input type="radio" name="report_type" value="follow_up" x-model="reportType" class="hidden">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-9 h-9 group-hover:scale-110 transition-transform" :class="reportType === 'follow_up' ? 'text-indigo-600' : 'text-slate-500'">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                          </svg>
                          <span class="text-sm font-bold text-slate-800 dark:text-white text-center">Follow-Up<br><span class="text-xs text-indigo-600 dark:text-indigo-400">Report</span></span>
                      </label>
                      @endunless

                      {{-- Social Media Analytics (QC / Supervisor / Super-Admin only) --}}
                      @if(auth()->user()?->isQc() || auth()->user()?->hasAnyRole(['super-admin','admin-digital','supervisor']))
                      <label class="flex flex-col items-center justify-center gap-2.5 p-4 md:p-5 rounded-2xl border-2 cursor-pointer transition-all select-none group" 
                             :class="reportType === 'social_media' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 shadow-md transform scale-[1.02]' : 'border-slate-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-500 hover:bg-slate-50 dark:hover:bg-gray-800'">
                          <input type="radio" name="report_type" value="social_media" x-model="reportType" class="hidden">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-9 h-9 group-hover:scale-110 transition-transform" :class="reportType === 'social_media' ? 'text-indigo-600' : 'text-slate-500'">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                          </svg>
                          <span class="text-sm font-bold text-slate-800 dark:text-white text-center">Social Media<br><span class="text-xs text-indigo-600 dark:text-indigo-400">Analytics</span></span>
                      </label>
                      @endif
                  </div>

                  {{-- Social Media Class Selector (only when social_media is selected) --}}
                  <div x-show="reportType === 'social_media'" x-transition class="mb-6 space-y-3">
                      <h2 class="text-lg font-bold text-slate-700 dark:text-white mb-2 flex items-center gap-2">
                          <span>📊</span> Select Social Media Class
                      </h2>
                      <select name="class_id" class="form-select w-full rounded-xl py-3 px-4 text-sm font-semibold">
                          <option value="">— All Classes —</option>
                          @foreach(\App\Models\SocialMediaClass::active()->orderBy('position')->orderBy('name')->get() as $class)
                              <option value="{{ $class->id }}">{{ $class->name }}</option>
                          @endforeach
                      </select>
                  </div>
              @endif

              {{-- Workspace & Boards Selection (Kanban only) --}}
              <div x-show="reportType === 'kanban'" x-transition>
                  <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
                      <div>
                          <h2 class="text-xl font-black text-slate-800 dark:text-white flex items-center gap-2.5">
                              <span class="text-2xl">📁</span> Select Boards to Include
                          </h2>
                          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Showing latest month workflow boards by default.</p>
                      </div>

                      {{-- Old Hidden Board Button --}}
                      <button type="button" 
                              @click="showHiddenBoards = !showHiddenBoards"
                              class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border text-sm font-bold transition-all shadow-xs cursor-pointer select-none"
                              :class="showHiddenBoards 
                                  ? 'bg-amber-500 text-white border-amber-600 shadow-amber-500/20 ring-2 ring-amber-400/40' 
                                  : 'bg-white dark:bg-gray-800 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-700/60 hover:bg-amber-50 dark:hover:bg-amber-900/20'">
                          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                          </svg>
                          <span>Old Hidden Board</span>
                          @if(isset($hiddenBoardsCount) && $hiddenBoardsCount > 0)
                              <span class="px-2 py-0.5 text-xs font-black rounded-full transition-colors"
                                    :class="showHiddenBoards ? 'bg-amber-700 text-white' : 'bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-300'">
                                  {{ $hiddenBoardsCount }}
                              </span>
                          @endif
                      </button>
                  </div>

                  @if($workspaces->isEmpty())
                      <div class="text-center py-12 text-slate-400 dark:text-gray-500">
                          <p class="text-base font-semibold">No active workspaces or workflow boards found.</p>
                      </div>
                  @else
                      <div class="space-y-6" x-init="if(typeof Sortable !== 'undefined') { 
                          new Sortable($el, { 
                              animation: 150, 
                              handle: '.drag-handle',
                              onEnd: function (evt) {
                                  let order = [];
                                  evt.to.querySelectorAll('[data-workspace-id]').forEach((el) => {
                                      order.push(el.getAttribute('data-workspace-id'));
                                  });
                                  fetch('{{ route('boards.workspaces.reorder', [], false) }}', {
                                      method: 'POST',
                                      headers: {
                                          'Content-Type': 'application/json',
                                          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                                          'Accept': 'application/json'
                                      },
                                      body: JSON.stringify({ order: order })
                                  });
                              }
                          }) 
                      }">
                          @foreach($workspaces as $workspace)
                              @if($workspace->boards->isNotEmpty())
                                  <div class="border border-slate-200 dark:border-gray-800 rounded-2xl p-5 md:p-6 bg-slate-50/70 dark:bg-gray-800/70 transition-all shadow-xs" 
                                       data-workspace-id="{{ $workspace->id }}"
                                       x-show="showHiddenBoards || {{ $workspace->has_active_workflow_boards ? 'true' : 'false' }}">
                                      <div class="flex items-center justify-between pb-3.5 border-b border-slate-200/80 dark:border-gray-700 mb-4">
                                          <div class="flex items-center gap-3">
                                              <span class="drag-handle cursor-grab active:cursor-grabbing text-slate-300 hover:text-slate-500 p-1">
                                                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-12a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"/></svg>
                                              </span>
                                              <div class="w-8 h-8 rounded-xl flex items-center justify-center text-white text-xs font-black shadow-xs"
                                                   style="background-color: {{ $workspace->color }}">
                                                  {{ $workspace->icon_text ?? strtoupper(substr($workspace->name, 0, 1)) }}
                                              </div>
                                              <h3 class="font-black text-slate-800 dark:text-white text-base md:text-lg">{{ $workspace->name }}</h3>
                                          </div>
                                          
                                          {{-- Select All / None Toggle --}}
                                          <div class="flex items-center gap-2">
                                              <button type="button" @click="selectAll({{ $workspace->id }}, true)" class="text-xs font-bold px-2.5 py-1 rounded-lg bg-white dark:bg-gray-700 border border-slate-200 dark:border-gray-600 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-gray-600 transition-colors shadow-xs">Select All</button>
                                              <span class="text-slate-300 dark:text-gray-600 text-xs">|</span>
                                              <button type="button" @click="selectAll({{ $workspace->id }}, false)" class="text-xs font-bold px-2.5 py-1 rounded-lg bg-white dark:bg-gray-700 border border-slate-200 dark:border-gray-600 text-slate-500 dark:text-gray-300 hover:bg-slate-100 dark:hover:bg-gray-600 transition-colors shadow-xs">Clear</button>
                                          </div>
                                      </div>
                                      
                                      <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                                          @foreach($workspace->boards as $board)
                                              <label class="flex items-start justify-between gap-3 p-3.5 md:p-4 rounded-xl cursor-pointer transition-all border-2
                                                            {{ $board->is_hidden
                                                                ? 'bg-amber-50/70 dark:bg-amber-900/10 border-amber-200 dark:border-amber-700/40 hover:border-amber-400 dark:hover:border-amber-500'
                                                                : 'bg-white dark:bg-gray-900 border-slate-200 dark:border-gray-700 hover:border-indigo-400 dark:hover:border-indigo-500 hover:bg-indigo-50/20 dark:hover:bg-indigo-900/20 shadow-xs' }}"
                                                     @if($board->is_hidden) x-show="showHiddenBoards" x-cloak @endif>
                                                  <div class="flex items-start gap-3.5 flex-1 min-w-0">
                                                      @php
                                                   $bName = strtolower($board->name ?? '');
                                                   $isTeamA = str_contains($bName, 'team a') || str_contains($bName, 'teama') || str_contains($bName, 'team-a');
                                                   $isTeamB = str_contains($bName, 'team b') || str_contains($bName, 'teamb') || str_contains($bName, 'team-b');
                                                   $boardTeam = $isTeamA ? 'A' : ($isTeamB ? 'B' : 'other');
                                                   $shouldAutoCheck = (!$board->is_hidden) && (($initialTeam === 'A' && $isTeamA) || ($initialTeam === 'B' && $isTeamB));
                                               @endphp
                                               <input type="checkbox" name="board_ids[]" value="{{ $board->id }}" 
                                                      data-team="{{ $boardTeam }}"
                                                      data-hidden="{{ $board->is_hidden ? '1' : '0' }}"
                                                      class="workspace-{{ $workspace->id }}-board board-team-{{ $boardTeam }} w-5 h-5 mt-0.5 rounded border-slate-300 dark:border-gray-600 focus:ring-indigo-500 dark:bg-gray-800
                                                             {{ $board->is_hidden ? 'text-amber-500' : 'text-indigo-600' }}"
                                                      {{ $shouldAutoCheck ? 'checked' : '' }}>
                                                      <div class="text-sm flex-1 min-w-0">
                                                          <div class="font-bold text-sm md:text-base leading-snug {{ $board->is_hidden ? 'text-amber-800 dark:text-amber-300' : 'text-slate-800 dark:text-white' }} truncate">
                                                              {{ $board->name }}
                                                          </div>
                                                          <div class="mt-1 flex items-center gap-2 flex-wrap">
                                                              <span class="text-xs text-slate-400 dark:text-gray-500 font-medium">{{ $board->visibilityDisplay ?? ucfirst($board->visibility) }}</span>
                                                              @if($board->is_hidden)
                                                                  <span class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 rounded-full">
                                                                      📦 Hidden / Past board
                                                                  </span>
                                                              @endif
                                                          </div>
                                                      </div>
                                                  </div>

                                                  {{-- Quick Restore Button for QC on Hidden Boards --}}
                                                  @if($board->is_hidden && auth()->user()->canManageBoards())
                                                  <button type="button" 
                                                          @click.stop.prevent="restoreBoard('{{ $board->slug }}', $el)"
                                                          :disabled="restoringSlug === '{{ $board->slug }}'"
                                                          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-amber-800 hover:text-white bg-amber-100 hover:bg-emerald-600 dark:bg-amber-900/40 dark:text-amber-300 dark:hover:bg-emerald-600 dark:hover:text-white border border-amber-300 dark:border-amber-700/60 transition-all cursor-pointer shadow-xs active:scale-95 flex-shrink-0"
                                                          title="Restore this board back to active boards">
                                                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                      </svg>
                                                      <span x-text="restoringSlug === '{{ $board->slug }}' ? '...' : 'Restore'"></span>
                                                  </button>
                                                  @endif
                                              </label>
                                          @endforeach
                                      </div>
                                  </div>
                              @endif
                          @endforeach
                      </div>
                  @endif
              </div>
          </div>
      </div>
      
      {{-- Report Options Panel (Right) --}}
      <div class="space-y-6">
          <div class="bg-white dark:bg-gray-900 rounded-3xl border border-slate-200 dark:border-gray-800 p-7 md:p-8 shadow-sm sticky top-6">
              <h2 class="text-xl font-black text-slate-800 dark:text-white mb-6 flex items-center gap-2.5">
                  <span class="text-2xl">⚙️</span> Report Settings
              </h2>
              
              <div class="space-y-6">
                  @if(isset($users) && (auth()->user()?->isSupervisorRole() || auth()->user()?->hasAnyRole(['super-admin', 'admin-digital', 'admin']) || auth()->user()?->isDaraOrKim() || auth()->user()?->isQc()))
                  {{-- Reviewer Selector for Supervisor/Admin/QC --}}
                  <div>
                      <label class="block text-xs font-black text-slate-500 dark:text-gray-400 uppercase tracking-wider mb-2">Reviewer / Lead</label>
                      <select name="reviewer_id" 
                              x-model="selectedReviewerId"
                              @change="onReviewerChange($event)"
                              class="w-full bg-slate-50 dark:bg-gray-800 border border-slate-200 dark:border-gray-700 dark:text-white focus:border-indigo-400 focus:ring focus:ring-indigo-200 rounded-xl py-3 px-4 text-sm md:text-base font-semibold text-slate-700 cursor-pointer">
                          @foreach($filteredReviewers as $reviewer)
                              @php
                                  $rIsDara = str_contains(strtolower($reviewer->username . ' ' . $reviewer->name), 'dara');
                                  $rIsKim = str_contains(strtolower($reviewer->username . ' ' . $reviewer->name), 'kim');
                                  $rTeam = $rIsKim ? 'B' : 'A';
                                  $rLabel = $rIsDara ? ' (Team A Lead)' : ($rIsKim ? ' (Team B Lead)' : '');
                              @endphp
                              <option value="{{ $reviewer->id }}" data-team="{{ $rTeam }}" {{ (string)$reviewer->id === (string)$initialReviewerId ? 'selected' : '' }}>
                                  {{ $reviewer->name }}{{ $rLabel }}
                              </option>
                          @endforeach
                      </select>
                  </div>
                  @endif

                  {{-- Date Range --}}
                  <div>
                      <label class="block text-xs font-black text-slate-500 dark:text-gray-400 uppercase tracking-wider mb-2">Date Range</label>
                      <select name="date_range" x-model="dateRange" class="w-full bg-slate-50 dark:bg-gray-800 border border-slate-200 dark:border-gray-700 dark:text-white focus:border-indigo-400 focus:ring focus:ring-indigo-200 rounded-xl py-3 px-4 text-sm md:text-base font-semibold text-slate-700">
                          <option value="today">Today</option>
                          <option value="custom">Custom Period</option>
                      </select>
                  </div>
                  
                  {{-- Custom Date Picker Panel --}}
                  <div x-show="dateRange === 'custom'" class="grid grid-cols-2 gap-3.5" x-transition x-cloak>
                      <div>
                          <label class="block text-xs font-bold text-slate-400 dark:text-gray-400 uppercase mb-1.5">Start Date</label>
                          <input type="date" name="start_date" class="w-full bg-slate-50 dark:bg-gray-800 dark:text-white border border-slate-200 dark:border-gray-700 focus:border-indigo-400 focus:ring focus:ring-indigo-200 rounded-xl py-2.5 px-3.5 text-sm font-semibold text-slate-700">
                      </div>
                      <div>
                          <label class="block text-xs font-bold text-slate-400 dark:text-gray-400 uppercase mb-1.5">End Date</label>
                          <input type="date" name="end_date" class="w-full bg-slate-50 dark:bg-gray-800 dark:text-white border border-slate-200 dark:border-gray-700 focus:border-indigo-400 focus:ring focus:ring-indigo-200 rounded-xl py-2.5 px-3.5 text-sm font-semibold text-slate-700">
                      </div>
                  </div>
                  
                  {{-- Format --}}
                  <div>
                      <label class="block text-xs font-black text-slate-500 dark:text-gray-400 uppercase tracking-wider mb-2.5">Export Format</label>
                      <div class="grid grid-cols-2 gap-3.5">
                          <label class="flex items-center justify-center gap-2.5 p-3.5 md:p-4 rounded-xl border-2 border-slate-200 dark:border-gray-700 dark:hover:border-indigo-500 cursor-pointer hover:bg-slate-50 dark:hover:bg-gray-800 transition-all select-none has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-900/20 shadow-xs">
                              <input type="radio" name="format" value="pdf" class="w-4 h-4 text-indigo-600 border-slate-300 dark:border-gray-600 focus:ring-indigo-500" checked>
                              <span class="text-sm font-bold text-slate-700 dark:text-white">📄 PDF Report</span>
                          </label>
                          <label class="flex items-center justify-center gap-2.5 p-3.5 md:p-4 rounded-xl border-2 border-slate-200 dark:border-gray-700 dark:hover:border-indigo-500 cursor-pointer hover:bg-slate-50 dark:hover:bg-gray-800 transition-all select-none has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-900/20 shadow-xs">
                              <input type="radio" name="format" value="csv" class="w-4 h-4 text-indigo-600 border-slate-300 dark:border-gray-600 focus:ring-indigo-500">
                              <span class="text-sm font-bold text-slate-700 dark:text-white">📊 CSV Sheet</span>
                          </label>
                      </div>
                  </div>
                  
                  <hr class="border-slate-100 dark:border-gray-800">
                  
                  {{-- Display Options (Kanban only) --}}
                  <div class="space-y-3.5" x-show="reportType === 'kanban'">
                      <label class="block text-xs font-black text-slate-500 dark:text-gray-400 uppercase tracking-wider">Include Options</label>
                      
                      <label class="flex items-center gap-3.5 cursor-pointer select-none">
                          <input type="checkbox" name="include_desc" value="1" class="w-5 h-5 rounded text-indigo-600 border-slate-300 dark:border-gray-600 focus:ring-indigo-500 dark:bg-gray-800">
                          <span class="text-sm text-slate-700 dark:text-gray-200 font-semibold">Include Task Descriptions</span>
                      </label>
                      
                      <label class="flex items-center gap-3.5 cursor-pointer select-none">
                          <input type="checkbox" name="include_comments" value="1" class="w-5 h-5 rounded text-indigo-600 border-slate-300 dark:border-gray-600 focus:ring-indigo-500 dark:bg-gray-800">
                          <span class="text-sm text-slate-700 dark:text-gray-200 font-semibold">Include Task Comments</span>
                      </label>
                  </div>
                  
                  <button type="submit" class="w-full py-4 px-6 rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:scale-[0.99] text-white font-black text-base md:text-lg flex items-center justify-center gap-3 shadow-xl shadow-indigo-600/20 hover:shadow-indigo-600/30 transition-all cursor-pointer">
                      <span class="text-xl">⚡</span>
                      <span>Compile & Export</span>
                  </button>
              </div>
          </div>
      </div>
      
  </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
@endsection
