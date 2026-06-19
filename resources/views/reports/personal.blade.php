@extends('layouts.app')
@section('title', 'Personal Report')
@section('page_title', 'Personal Report')

@section('content')
<div class="animate-fade-in space-y-8" x-data="{ dateRange: 'all_time', selectAll(workspaceId, checked) {
    document.querySelectorAll(`.workspace-${workspaceId}-board`).forEach(cb => cb.checked = checked);
} }">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-display font-bold text-slate-800 dark:text-white">Personal Report</h1>
      <p class="text-sm text-slate-400 dark:text-slate-400 mt-0.5">Consolidated multi-board report compilation for QC and Supervisors.</p>
    </div>
  </div>

  <form action="{{ route('boards.reports.personal.export') }}" method="GET" target="_blank" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      {{-- Workspace & Boards Selection (Left) --}}
      <div class="lg:col-span-2 space-y-6">
          <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-700 p-6 shadow-sm">
              <h2 class="text-lg font-bold text-slate-700 dark:text-white mb-4 flex items-center gap-2">
                  <span>📋</span> Select Boards to Include
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
                          <label class="flex items-center justify-center gap-2 p-3 rounded-xl border border-slate-200 dark:border-gray-700 dark:hover:border-indigo-500 cursor-pointer hover:bg-slate-50 dark:hover:bg-gray-800 transition-all select-none">
                              <input type="radio" name="format" value="pdf" checked class="text-indigo-600 border-slate-300 dark:border-gray-600 focus:ring-indigo-500">
                              <span class="text-xs font-bold text-slate-700 dark:text-white">📄 PDF Report</span>
                          </label>
                          <label class="flex items-center justify-center gap-2 p-3 rounded-xl border border-slate-200 dark:border-gray-700 dark:hover:border-indigo-500 cursor-pointer hover:bg-slate-50 dark:hover:bg-gray-800 transition-all select-none">
                              <input type="radio" name="format" value="csv" class="text-indigo-600 border-slate-300 dark:border-gray-600 focus:ring-indigo-500">
                              <span class="text-xs font-bold text-slate-700 dark:text-white">📊 CSV Sheet</span>
                          </label>
                      </div>
                  </div>
                  
                  <hr class="border-slate-100 dark:border-gray-700">
                  
                  {{-- Display Options --}}
                  <div class="space-y-3">
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
