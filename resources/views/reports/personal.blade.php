@extends('layouts.app')
@section('title', 'Personal Report')
@section('page_title', 'Personal Report')

@section('content')
<div class="animate-fade-in space-y-8" x-data="{ 
    dateRange: 'all_time',
    reportType: 'kanban',
    selectAll(workspaceId, checked) {
        document.querySelectorAll(`.workspace-${workspaceId}-board`).forEach(cb => cb.checked = checked);
    },
    getExportUrl() {
        if (this.reportType === 'social_media') return '{{ route('boards.reports.personal.social_media.export') }}';
        if (this.reportType === 'website') return '{{ route('boards.reports.personal.website.export') }}';
        if (this.reportType === 'follow_up') return '{{ route('boards.reports.personal.follow_up.export') }}';
        return '{{ route('boards.reports.personal.export') }}';
    }
}">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-display font-bold text-slate-800 dark:text-white">Personal Report</h1>
      <p class="text-sm text-slate-400 dark:text-slate-400 mt-0.5">Consolidated multi-department report compilation for QC and Supervisors.</p>
    </div>
  </div>

  <form :action="getExportUrl()" method="GET" target="_blank" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      {{-- Main Content Area (Left) --}}
      <div class="lg:col-span-2 space-y-6">
          <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-700 p-6 shadow-sm">
              <h2 class="text-lg font-bold text-slate-700 dark:text-white mb-4 flex items-center gap-2">
                  <span>📋</span> Select Report Type
              </h2>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
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
                  @unless(auth()->user()?->isSupervisorRole() && !auth()->user()?->isQc())
                  <label class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all select-none group" 
                         :class="reportType === 'social_media' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 shadow-md transform scale-[1.02]' : 'border-slate-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-500 hover:bg-slate-50 dark:hover:bg-gray-800'">
                      <input type="radio" name="report_type" value="social_media" x-model="reportType" class="hidden">
                      <img src="https://cdn-icons-png.flaticon.com/512/1468/1468269.png" alt="Social Media" class="w-8 h-8 object-contain group-hover:scale-110 transition-transform" :class="reportType === 'social_media' ? 'opacity-100' : 'opacity-80 grayscale'">
                      <span class="text-xs font-bold text-slate-700 dark:text-white text-center">Social Media<br><span class="text-[10px] text-indigo-600 dark:text-indigo-400">Report</span></span>
                  </label>
                  @endunless

                  <label class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all select-none group" 
                         :class="reportType === 'website' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 shadow-md transform scale-[1.02]' : 'border-slate-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-500 hover:bg-slate-50 dark:hover:bg-gray-800'">
                      <input type="radio" name="report_type" value="website" x-model="reportType" class="hidden">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-8 h-8 group-hover:scale-110 transition-transform" :class="reportType === 'website' ? 'text-indigo-600' : 'text-slate-500'">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253M3 12a8.959 8.959 0 0 0 .284 2.253" />
                      </svg>
                      <span class="text-xs font-bold text-slate-700 dark:text-white text-center">Website<br><span class="text-[10px] text-indigo-600 dark:text-indigo-400">Report</span></span>
                  </label>

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
              </div>

              {{-- Workspace & Boards Selection --}}
              <div x-show="reportType === 'kanban'" x-transition>
                  <h2 class="text-lg font-bold text-slate-700 dark:text-white mb-4 flex items-center gap-2">
                      <span>📁</span> Select Boards to Include
                  </h2>
                  @if($workspaces->isEmpty())
                      <div class="text-center py-8 text-slate-400 dark:text-gray-500">
                          <p>No active workspaces or boards found.</p>
                      </div>
                  @else
                      <div class="space-y-6">
                          @foreach($workspaces as $workspace)
                              @if($workspace->boards->isNotEmpty())
                                  <div class="border border-slate-200 dark:border-gray-700 rounded-xl p-4 bg-slate-50/50 dark:bg-gray-800">
                                      <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-gray-700 mb-3">
                                          <div class="flex items-center gap-2.5">
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
                                              <label class="flex items-start gap-3 p-2.5 rounded-lg bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-700 hover:border-indigo-400 dark:hover:border-indigo-500 hover:bg-indigo-50/10 dark:hover:bg-indigo-900/20 cursor-pointer transition-all">
                                                  <input type="checkbox" name="board_ids[]" value="{{ $board->id }}" 
                                                         class="workspace-{{ $workspace->id }}-board mt-0.5 rounded text-indigo-600 border-slate-300 dark:border-gray-600 focus:ring-indigo-500 dark:bg-gray-800">
                                                  <div class="text-xs">
                                                      <div class="font-semibold text-slate-700 dark:text-white">{{ $board->name }}</div>
                                                      <div class="text-slate-400 dark:text-gray-500 mt-0.5">{{ $board->visibilityDisplay ?? ucfirst($board->visibility) }}</div>
                                                  </div>
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
                          <option value="all_time">All Time</option>
                          <option value="this_week">This Week</option>
                          <option value="this_month">This Month</option>
                          <option value="last_month">Last Month</option>
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
                  
                  {{-- Display Options --}}
                  <div class="space-y-3" x-show="reportType === 'kanban'">
                      <label class="block text-xs font-bold text-slate-500 dark:text-gray-400 uppercase tracking-wider">Include Options</label>
                      
                      <label class="flex items-center gap-3 cursor-pointer">
                          <input type="checkbox" name="include_desc" value="1" checked class="rounded text-indigo-600 border-slate-300 dark:border-gray-600 focus:ring-indigo-500 dark:bg-gray-800">
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
@endsection
