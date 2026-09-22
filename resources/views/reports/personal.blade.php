@extends('layouts.app')
@section('title', 'Personal Report')
@section('page_title', 'Personal Report')

@section('content')
<div class="animate-fade-in space-y-8 pb-32" x-data="{ 
    dateRange: 'today',
    reportType: 'kanban',
    showHiddenBoards: false,
    restoringSlug: null,
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

  <div class="flex items-center justify-between mb-2">
    <div class="flex items-start gap-4">

      <div>
        <h1 class="text-2xl font-display font-bold text-slate-800 dark:text-white">Personal Report</h1>
        <p class="text-sm text-slate-400 dark:text-slate-400 mt-0.5">Consolidated multi-department report compilation for QC and Supervisors.</p>
      </div>
    </div>
  </div>

  <form :action="getExportUrl()" method="GET" target="_blank" data-turbo="false" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <input type="hidden" name="is_personal_report" value="1">
      
      {{-- Main Content Area (Left) --}}
      <div class="lg:col-span-2 space-y-6">
          <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-700 p-6 shadow-sm">
              <h2 class="text-lg font-bold text-slate-700 dark:text-white mb-4 flex items-center gap-2">
                  <span>📋</span> Select Report Type
              </h2>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                  {{-- Board / Kanban --}}
                  <label class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all select-none group" 
                         :class="reportType === 'kanban' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 shadow-md transform scale-[1.02]' : 'border-slate-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-500 hover:bg-slate-50 dark:hover:bg-gray-800'">
                      <input type="radio" name="report_type" value="kanban" x-model="reportType" class="hidden">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-8 h-8 group-hover:scale-110 transition-transform" :class="reportType === 'kanban' ? 'text-indigo-600' : 'text-slate-500'">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
                      </svg>
                      <span class="text-xs font-bold text-slate-700 dark:text-white text-center">Board<br>
                          @if(auth()->user()?->isQc())
                              <span class="text-[10px] text-indigo-600 dark:text-indigo-400">QC Report</span>
                          @else
                              <span class="text-[10px] text-indigo-600 dark:text-indigo-400">Supervisor Report</span>
                          @endif
                      </span>
                  </label>

                  {{-- Website Status --}}
                  <label class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all select-none group" 
                         :class="reportType === 'website' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 shadow-md transform scale-[1.02]' : 'border-slate-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-500 hover:bg-slate-50 dark:hover:bg-gray-800'">
                      <input type="radio" name="report_type" value="website" x-model="reportType" class="hidden">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-8 h-8 group-hover:scale-110 transition-transform" :class="reportType === 'website' ? 'text-indigo-600' : 'text-slate-500'">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253M3 12a8.959 8.959 0 0 0 .284 2.253" />
                      </svg>
                      <span class="text-xs font-bold text-slate-700 dark:text-white text-center">Website<br><span class="text-[10px] text-indigo-600 dark:text-indigo-400">Report</span></span>
                  </label>

                  {{-- Follow-Up --}}
                  @unless(auth()->user()?->isSupervisorRole() && !auth()->user()?->isQc())
                  <label class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all select-none group" 
                         :class="reportType === 'follow_up' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 shadow-md transform scale-[1.02]' : 'border-slate-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-500 hover:bg-slate-50 dark:hover:bg-gray-800'">
                      <input type="radio" name="report_type" value="follow_up" x-model="reportType" class="hidden">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-8 h-8 group-hover:scale-110 transition-transform" :class="reportType === 'follow_up' ? 'text-indigo-600' : 'text-slate-500'">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                      </svg>
                      <span class="text-xs font-bold text-slate-700 dark:text-white text-center">Follow-Up<br><span class="text-[10px] text-indigo-600 dark:text-indigo-400">Report</span></span>
                  </label>
                  @endunless

                  {{-- Social Media Analytics (QC / Supervisor / Super-Admin only) --}}
                  @if(auth()->user()?->isQc() || auth()->user()?->hasAnyRole(['super-admin','admin-digital','supervisor']))
                  <label class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all select-none group" 
                         :class="reportType === 'social_media' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 shadow-md transform scale-[1.02]' : 'border-slate-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-500 hover:bg-slate-50 dark:hover:bg-gray-800'">
                      <input type="radio" name="report_type" value="social_media" x-model="reportType" class="hidden">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-8 h-8 group-hover:scale-110 transition-transform" :class="reportType === 'social_media' ? 'text-indigo-600' : 'text-slate-500'">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                      </svg>
                      <span class="text-xs font-bold text-slate-700 dark:text-white text-center">Social Media<br><span class="text-[10px] text-indigo-600 dark:text-indigo-400">Analytics</span></span>
                  </label>
                  @endif
              </div>

              {{-- Social Media Class Selector (only when social_media is selected) --}}
              <div x-show="reportType === 'social_media'" x-transition class="mb-4 space-y-3">
                  <h2 class="text-lg font-bold text-slate-700 dark:text-white mb-3 flex items-center gap-2">
                      <span>📊</span> Select Social Media Class
                  </h2>
                  <select name="class_id" class="form-select w-full rounded-xl">
                      <option value="">— All Classes —</option>
                      @foreach(\App\Models\SocialMediaClass::active()->orderBy('position')->orderBy('name')->get() as $class)
                          <option value="{{ $class->id }}">{{ $class->name }}</option>
                      @endforeach
                  </select>
                          {{-- Workspace & Boards Selection (Kanban only) --}}
              <div x-show="reportType === 'kanban'" x-transition>
                  <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                      <div>
                          <h2 class="text-lg font-bold text-slate-700 dark:text-white flex items-center gap-2">
                              <span>📁</span> Select Boards to Include
                          </h2>
                          <p class="text-xs text-slate-400 dark:text-slate-400 mt-0.5">Showing latest month workflow boards by default.</p>
                      </div>

                      {{-- Old Hidden Board Button --}}
                      <button type="button" 
                              @click="showHiddenBoards = !showHiddenBoards"
                              class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl border text-xs font-bold transition-all shadow-xs cursor-pointer select-none"
                              :class="showHiddenBoards 
                                  ? 'bg-amber-500 text-white border-amber-600 shadow-amber-500/20 ring-2 ring-amber-400/40' 
                                  : 'bg-white dark:bg-gray-800 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-700/60 hover:bg-amber-50 dark:hover:bg-amber-900/20'">
                          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                          </svg>
                          <span>Old Hidden Board</span>
                          @if(isset($hiddenBoardsCount) && $hiddenBoardsCount > 0)
                              <span class="px-1.5 py-0.5 text-[10px] font-black rounded-full transition-colors"
                                    :class="showHiddenBoards ? 'bg-amber-700 text-white' : 'bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-300'">
                                  {{ $hiddenBoardsCount }}
                              </span>
                          @endif
                      </button>
                  </div>

                  @if($workspaces->isEmpty())
                      <div class="text-center py-8 text-slate-400 dark:text-gray-500">
                          <p>No active workspaces or workflow boards found.</p>
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
                                  <div class="border border-slate-200 dark:border-gray-700 rounded-xl p-4 bg-slate-50/50 dark:bg-gray-800 transition-all" 
                                       data-workspace-id="{{ $workspace->id }}"
                                       x-show="showHiddenBoards || {{ $workspace->has_active_workflow_boards ? 'true' : 'false' }}">
                                      <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-gray-700 mb-3">
                                          <div class="flex items-center gap-2.5">
                                              <span class="drag-handle cursor-grab active:cursor-grabbing text-slate-300 hover:text-slate-500">
                                                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-12a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"/></svg>
                                              </span>
                                              <div class="w-6 h-6 rounded-md flex items-center justify-center text-white text-xs font-bold"
                                                   style="background-color: {{ $workspace->color }}">
                                                  {{ $workspace->icon_text ?? strtoupper(substr($workspace->name, 0, 1)) }}
                                              </div>
                                              <h3 class="font-bold text-slate-700 dark:text-white text-sm">{{ $workspace->name }}</h3>
                                          </div>
                                          
                                          {{-- Select All / None Toggle --}}
                                          <div class="flex gap-2">
                                              <button type="button" @click="selectAll({{ $workspace->id }}, true)" class="text-[10px] text-indigo-500 dark:text-indigo-400 font-bold hover:underline">Select All</button>
                                              <span class="text-slate-300 dark:text-gray-600 text-[10px]">|</span>
                                              <button type="button" @click="selectAll({{ $workspace->id }}, false)" class="text-[10px] text-slate-500 dark:text-gray-400 font-bold hover:underline">Clear</button>
                                          </div>
                                      </div>
                                      
                                      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                          @foreach($workspace->boards as $board)
                                              <label class="flex items-start justify-between gap-2.5 p-2.5 rounded-lg cursor-pointer transition-all
                                                            {{ $board->is_hidden
                                                                ? 'bg-amber-50/60 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-700/40 hover:border-amber-400 dark:hover:border-amber-500'
                                                                : 'bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-700 hover:border-indigo-400 dark:hover:border-indigo-500 hover:bg-indigo-50/10 dark:hover:bg-indigo-900/20' }}"
                                                     @if($board->is_hidden) x-show="showHiddenBoards" x-cloak @endif>
                                                  <div class="flex items-start gap-3 flex-1 min-w-0">
                                                      <input type="checkbox" name="board_ids[]" value="{{ $board->id }}" 
                                                             class="workspace-{{ $workspace->id }}-board mt-0.5 rounded border-slate-300 dark:border-gray-600 focus:ring-indigo-500 dark:bg-gray-800
                                                                    {{ $board->is_hidden ? 'text-amber-500' : 'text-indigo-600' }}">
                                                      <div class="text-xs flex-1 min-w-0">
                                                          <div class="font-semibold {{ $board->is_hidden ? 'text-amber-700 dark:text-amber-400' : 'text-slate-700 dark:text-white' }} truncate">
                                                              {{ $board->name }}
                                                          </div>
                                                          <div class="mt-0.5 flex items-center gap-1.5 flex-wrap">
                                                              <span class="text-slate-400 dark:text-gray-500">{{ $board->visibilityDisplay ?? ucfirst($board->visibility) }}</span>
                                                              @if($board->is_hidden)
                                                                  <span class="inline-flex items-center gap-0.5 text-[10px] font-semibold text-amber-600 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/30 px-1.5 py-0.5 rounded-full">
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
                                                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold text-amber-800 hover:text-white bg-amber-100 hover:bg-emerald-600 dark:bg-amber-900/40 dark:text-amber-300 dark:hover:bg-emerald-600 dark:hover:text-white border border-amber-300 dark:border-amber-700/60 transition-all cursor-pointer shadow-xs active:scale-95 flex-shrink-0"
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
          <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-700 p-6 shadow-sm sticky top-6">
              <h2 class="text-lg font-bold text-slate-700 dark:text-white mb-4 flex items-center gap-2">
                  <span>⚙️</span> Report Settings
              </h2>
              
              <div class="space-y-5">
                  {{-- Date Range --}}
                  <div>
                      <label class="block text-xs font-bold text-slate-500 dark:text-gray-400 uppercase tracking-wider mb-2">Date Range</label>
                      <select name="date_range" x-model="dateRange" class="w-full bg-slate-50 dark:bg-gray-800 border-slate-200 dark:border-gray-600 dark:text-white focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-xl py-2 px-3 text-sm text-slate-700">
                          <option value="today">Today</option>
                          <option value="custom">Custom Period</option>
                      </select>
                  </div>
                  
                  {{-- Custom Date Picker Panel --}}
                  <div x-show="dateRange === 'custom'" class="grid grid-cols-2 gap-3" x-transition x-cloak>
                      <div>
                          <label class="block text-[10px] font-bold text-slate-400 dark:text-gray-400 uppercase mb-1">Start Date</label>
                          <input type="date" name="start_date" class="w-full bg-slate-50 dark:bg-gray-800 dark:text-white border-slate-200 dark:border-gray-600 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-lg py-1.5 px-2 text-xs text-slate-700">
                      </div>
                      <div>
                          <label class="block text-[10px] font-bold text-slate-400 dark:text-gray-400 uppercase mb-1">End Date</label>
                          <input type="date" name="end_date" class="w-full bg-slate-50 dark:bg-gray-800 dark:text-white border-slate-200 dark:border-gray-600 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-lg py-1.5 px-2 text-xs text-slate-700">
                      </div>
                  </div>
                  
                  {{-- Format --}}
                  <div>
                      <label class="block text-xs font-bold text-slate-500 dark:text-gray-400 uppercase tracking-wider mb-2">Export Format</label>
                      <div class="grid grid-cols-2 gap-3">
                          <label class="flex items-center justify-center gap-2 p-3 rounded-xl border-2 border-slate-200 dark:border-gray-700 dark:hover:border-indigo-500 cursor-pointer hover:bg-slate-50 dark:hover:bg-gray-800 transition-all select-none has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-900/20">
                              <input type="radio" name="format" value="pdf" class="text-indigo-600 border-slate-300 dark:border-gray-600 focus:ring-indigo-500" checked>
                              <span class="text-xs font-bold text-slate-700 dark:text-white">📄 PDF Report</span>
                          </label>
                          <label class="flex items-center justify-center gap-2 p-3 rounded-xl border-2 border-slate-200 dark:border-gray-700 dark:hover:border-indigo-500 cursor-pointer hover:bg-slate-50 dark:hover:bg-gray-800 transition-all select-none has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-900/20">
                              <input type="radio" name="format" value="csv" class="text-indigo-600 border-slate-300 dark:border-gray-600 focus:ring-indigo-500">
                              <span class="text-xs font-bold text-slate-700 dark:text-white">📊 CSV Sheet</span>
                          </label>
                      </div>
                  </div>
                  
                  <hr class="border-slate-100 dark:border-gray-700">
                  
                  {{-- Display Options (Kanban only) --}}
                  <div class="space-y-3" x-show="reportType === 'kanban'">
                      <label class="block text-xs font-bold text-slate-500 dark:text-gray-400 uppercase tracking-wider">Include Options</label>
                      
                      <label class="flex items-center gap-3 cursor-pointer">
                          <input type="checkbox" name="include_desc" value="1" class="rounded text-indigo-600 border-slate-300 dark:border-gray-600 focus:ring-indigo-500 dark:bg-gray-800">
                          <span class="text-xs text-slate-600 dark:text-gray-300 font-medium">Include Task Descriptions</span>
                      </label>
                      
                      <label class="flex items-center gap-3 cursor-pointer">
                          <input type="checkbox" name="include_comments" value="1" class="rounded text-indigo-600 border-slate-300 dark:border-gray-600 focus:ring-indigo-500 dark:bg-gray-800">
                          <span class="text-xs text-slate-600 dark:text-gray-300 font-medium">Include Task Comments</span>
                      </label>
                  </div>
                  
                  <button type="submit" class="w-full btn btn-primary flex items-center justify-center gap-2 py-3 rounded-xl shadow-lg shadow-indigo-600/10">
                      <span>⚡</span> Compile & Export
                  </button>
              </div>
          </div>
      </div>
      
  </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
@endsection
