@extends('layouts.app')
@section('title', 'All Websites')
@section('page_title', 'All Websites')

@section('content')
<style>
  /* ── Dark mode ── */
  [data-theme="dark"] .ws-card { background-color: #0f172a !important; border-color: #1e293b !important; }
  [data-theme="dark"] .ws-card:hover { background-color: #1e293b !important; }
  [data-theme="dark"] .ws-section-title { color: #f1f5f9 !important; }
  [data-theme="dark"] .ws-tab-bar { background-color: #0f172a !important; border-color: #1e293b !important; }
  [data-theme="dark"] .ws-tab-btn { color: #94a3b8 !important; }
  [data-theme="dark"] .ws-tab-btn.active { color: #818cf8 !important; border-color: #818cf8 !important; }
  [data-theme="dark"] .ws-stat-card { background-color: #1e293b !important; border-color: #334155 !important; }
  [data-theme="dark"] .ws-stat-label { color: #94a3b8 !important; }
  [data-theme="dark"] .ws-stat-value { color: #f1f5f9 !important; }
  [data-theme="dark"] .ws-card-footer { background-color: #1e293b !important; border-color: #334155 !important; }
  [data-theme="dark"] .ws-domain-text { color: #a5b4fc !important; }
  [data-theme="dark"] .ws-modal { background-color: #1e293b !important; }
  [data-theme="dark"] .ws-modal-header { background-color: #0f172a !important; border-color: #334155 !important; }
  [data-theme="dark"] .ws-modal-label { color: #cbd5e1 !important; }
  [data-theme="dark"] .ws-modal-input { background-color: #0f172a !important; color: #f1f5f9 !important; border-color: #334155 !important; }
  [data-theme="dark"] .ws-progress-card { background-color: #1e293b !important; border-color: #334155 !important; }
  [data-theme="dark"] .ws-progress-name { color: #f1f5f9 !important; }
  [data-theme="dark"] .ws-progress-meta { color: #94a3b8 !important; }
  [data-theme="dark"] .ws-live-card { background-color: #0f172a !important; border-color: #1e293b !important; }
  [data-theme="dark"] .ws-live-name { color: #f1f5f9 !important; }
  [data-theme="dark"] .ws-live-meta { color: #94a3b8 !important; }
  [data-theme="dark"] .ws-empty-box { background-color: #1e293b !important; border-color: #334155 !important; }

  /* ── Status badges ── */
  .badge-draft        { background:#f1f5f9;color:#475569; }
  .badge-in-progress  { background:#dbeafe;color:#1d4ed8; }
  .badge-qc-review    { background:#fef3c7;color:#b45309; }
  .badge-completed    { background:#d1fae5;color:#065f46; }
  .badge-live         { background:#dcfce7;color:#15803d; }
  .badge-needs-update { background:#ffedd5;color:#c2410c; }
  .badge-error        { background:#fee2e2;color:#b91c1c; }
  [data-theme="dark"] .badge-draft        { background:#1e293b;color:#94a3b8; }
  [data-theme="dark"] .badge-in-progress  { background:#1e3a5f;color:#93c5fd; }
  [data-theme="dark"] .badge-qc-review    { background:#422006;color:#fcd34d; }
  [data-theme="dark"] .badge-completed    { background:#064e3b;color:#6ee7b7; }
  [data-theme="dark"] .badge-live         { background:#14532d;color:#86efac; }
  [data-theme="dark"] .badge-needs-update { background:#431407;color:#fb923c; }
  [data-theme="dark"] .badge-error        { background:#450a0a;color:#fca5a5; }

  .progress-bar-bg { background:#e2e8f0; border-radius:99px; height:6px; overflow:hidden; }
  .progress-bar-fill { height:100%; border-radius:99px; transition:width .4s ease; }
  [data-theme="dark"] .progress-bar-bg { background:#334155; }

  /* Deadline warning pulse */
  @keyframes deadlinePulse { 0%,100%{opacity:1}50%{opacity:.5} }
  .deadline-warning { animation: deadlinePulse 2s infinite; }
</style>

<div x-data="websitesPage()" x-cloak>

{{-- ════════════════════════════════════════════════════════════
     KPI STATS
════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
  @php
    $kpiCards = [
      ['icon'=>'🌐','label'=>'Total Sites','value'=>$stats['total'],'color'=>'indigo'],
      ['icon'=>'🔨','label'=>'In Progress','value'=>$stats['in_progress'],'color'=>'blue'],
      ['icon'=>'🔍','label'=>'QC Review','value'=>$stats['qc_review'],'color'=>'amber'],
      ['icon'=>'✅','label'=>'Live','value'=>$stats['live'],'color'=>'green'],
      ['icon'=>'⚠️','label'=>'Needs Fix','value'=>$stats['needs_fix'],'color'=>'rose'],
    ];
  @endphp
  @foreach($kpiCards as $kpi)
  <div class="ws-stat-card rounded-2xl border p-4 flex items-center gap-3 bg-white border-slate-200 shadow-sm">
    <span class="text-2xl flex-shrink-0">{{ $kpi['icon'] }}</span>
    <div>
      <div class="ws-stat-value text-xl font-black text-slate-800">{{ $kpi['value'] }}</div>
      <div class="ws-stat-label text-xs text-slate-500">{{ $kpi['label'] }}</div>
    </div>
  </div>
  @endforeach
</div>

{{-- ════════════════════════════════════════════════════════════
     TAB BAR + ACTION BUTTONS
════════════════════════════════════════════════════════════ --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
  {{-- Tabs --}}
  <div class="ws-tab-bar bg-white border border-slate-200 rounded-xl flex overflow-hidden shadow-sm">
    <button @click="activeTab='built'"
            :class="activeTab==='built' ? 'ws-tab-btn active border-b-2 border-indigo-500 text-indigo-600 bg-indigo-50' : 'ws-tab-btn text-slate-500 hover:bg-slate-50'"
            class="px-5 py-2.5 text-sm font-semibold transition-all">
      🏗 Built Websites
    </button>
    <button @click="activeTab='progress'"
            :class="activeTab==='progress' ? 'ws-tab-btn active border-b-2 border-blue-500 text-blue-600 bg-blue-50' : 'ws-tab-btn text-slate-500 hover:bg-slate-50'"
            class="px-5 py-2.5 text-sm font-semibold transition-all">
      📊 Website Progress
      @if($stats['in_progress'] > 0)
        <span class="ml-1 px-1.5 py-0.5 text-xs bg-blue-500 text-white rounded-full">{{ $stats['in_progress'] }}</span>
      @endif
    </button>
    <button @click="activeTab='live'"
            :class="activeTab==='live' ? 'ws-tab-btn active border-b-2 border-green-500 text-green-600 bg-green-50' : 'ws-tab-btn text-slate-500 hover:bg-slate-50'"
            class="px-5 py-2.5 text-sm font-semibold transition-all">
      🚀 Live Websites
      @if($stats['live'] > 0)
        <span class="ml-1 px-1.5 py-0.5 text-xs bg-green-500 text-white rounded-full">{{ $stats['live'] }}</span>
      @endif
    </button>
  </div>

  {{-- Action buttons — only shown on Built tab --}}
  <div class="flex items-center gap-2" x-show="activeTab === 'built'">
    <button @click="createCategoryModalOpen=true" class="btn btn-secondary flex items-center gap-2 text-sm">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Create Class
    </button>
    <button @click="openAddModal" class="btn btn-primary flex items-center gap-2 text-sm">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Add Website
    </button>
  </div>
</div>

{{-- Flash Messages --}}
@if(session('success'))
  <div class="mb-4 bg-emerald-50 text-emerald-700 p-4 rounded-xl border border-emerald-200 shadow-sm text-sm font-medium flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
    {{ session('success') }}
  </div>
@endif
@if($errors->any())
  <div class="mb-4 bg-rose-50 text-rose-700 p-4 rounded-xl border border-rose-200 shadow-sm text-sm font-medium">
    <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
@endif


{{-- ════════════════════════════════════════════════════════════
     TAB 1: BUILT WEBSITES
════════════════════════════════════════════════════════════ --}}
<div x-show="activeTab==='built'" x-transition.opacity>

  {{-- Search/Filter bar --}}
  <div class="flex flex-wrap items-center gap-3 mb-5">
    <div class="relative flex-1 min-w-[200px] max-w-xs">
      <input type="text" x-model="search" placeholder="Search name or domain…"
             class="w-full form-input pl-9 py-2 text-sm rounded-xl">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
    </div>
    <select x-model="filterStatus" class="form-select text-sm rounded-xl py-2">
      <option value="">All Statuses</option>
      @foreach(\App\Models\Website::STATUSES as $s)
        <option value="{{ $s }}">{{ $s }}</option>
      @endforeach
    </select>
    <select x-model="filterHandler" class="form-select text-sm rounded-xl py-2">
      <option value="">All Handlers</option>
      @foreach($users as $u)
        <option value="{{ $u->id }}">{{ $u->name }}</option>
      @endforeach
    </select>
  </div>

  @if($groupedWebsites->count() > 0)
  <div class="space-y-10">
    @foreach($groupedWebsites as $category => $websites)
    <div>
      {{-- Group Header --}}
      <div class="flex items-center gap-3 mb-4">
        <h2 class="ws-section-title text-lg font-black text-slate-800">{{ $category }}</h2>
        @if($category !== 'Uncategorized')
          <button @click="openRenameModal('{{ addslashes($category) }}')" class="text-slate-400 hover:text-indigo-600 transition-colors" title="Rename">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>
          </button>
          <form action="{{ route('websites.reorderCategory') }}" method="POST" class="inline">@csrf @method('PUT')<input type="hidden" name="category" value="{{ $category }}"><input type="hidden" name="direction" value="up"><button type="submit" class="text-slate-400 hover:text-indigo-600" {{ $loop->first?'disabled style="opacity:0.3"':'' }}><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg></button></form>
          <form action="{{ route('websites.reorderCategory') }}" method="POST" class="inline">@csrf @method('PUT')<input type="hidden" name="category" value="{{ $category }}"><input type="hidden" name="direction" value="down"><button type="submit" class="text-slate-400 hover:text-indigo-600" {{ $loop->last?'disabled style="opacity:0.3"':'' }}><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></button></form>
          <form action="{{ route('websites.destroyCategory') }}" method="POST" class="inline ml-1" @submit.prevent="confirmDeleteCategory('{{ addslashes($category) }}', $el)">@csrf @method('DELETE')<input type="hidden" name="category" value="{{ $category }}"><button type="submit" class="text-slate-400 hover:text-rose-600 transition-colors"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
        @endif
        <span class="px-2.5 py-1 text-xs font-bold bg-indigo-100 text-indigo-700 rounded-lg">{{ $websites->count() }} Sites</span>
        <div class="flex-1 h-px bg-slate-200"></div>
      </div>

      {{-- Card Grid --}}
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        @foreach($websites as $website)
        @php
          $statusSlug = strtolower(str_replace(['/', ' '], ['-', '-'], $website->status));
          $isOverdue  = $website->isOverdue();
        @endphp
        <div class="ws-card bg-white border border-slate-200 rounded-2xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden group flex flex-col"
             x-show="matchesFilter({{ json_encode(['name'=>$website->name,'url'=>$website->url,'status'=>$website->status,'handled_by'=>$website->handled_by]) }})"
             style="display:flex">
          {{-- Card Top --}}
          <div class="p-5 flex-1 flex flex-col items-center text-center">
            {{-- Logo --}}
            <div class="w-16 h-16 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center p-2 mb-3 shadow-sm group-hover:scale-105 transition-transform">
              @if($website->logo_path)
                <img src="{{ $website->logo_src }}" alt="{{ $website->name }}" class="max-w-full max-h-full object-contain">
              @else
                <span class="text-3xl">🌐</span>
              @endif
            </div>

            {{-- Status badge --}}
            <span class="badge-{{ $statusSlug }} text-xs font-bold px-2.5 py-1 rounded-full mb-2">{{ $website->status }}</span>

            <h3 class="text-sm font-bold text-slate-800 mb-1 leading-tight">{{ $website->name }}</h3>
            <a href="{{ $website->url }}" target="_blank" rel="noopener noreferrer"
               class="ws-domain-text text-xs text-indigo-600 hover:text-indigo-800 break-all px-2">
              {{ $website->clean_domain }}
            </a>

            {{-- Progress bar --}}
            @if($website->progress_percent > 0)
            <div class="w-full mt-3 px-1">
              <div class="progress-bar-bg">
                <div class="progress-bar-fill bg-indigo-500" style="width:{{ $website->progress_percent }}%"></div>
              </div>
              <div class="text-[10px] text-slate-400 text-right mt-0.5">{{ $website->progress_percent }}%</div>
            </div>
            @endif

            {{-- Handler + Deadline --}}
            <div class="flex items-center justify-between w-full mt-3 px-1">
              @if($website->handler)
                <span class="text-[10px] text-slate-500 flex items-center gap-1">
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                  {{ $website->handler->name }}
                </span>
              @else
                <span></span>
              @endif
              @if($website->deadline)
                <span class="text-[10px] {{ $isOverdue ? 'text-rose-500 font-bold deadline-warning' : 'text-slate-400' }} flex items-center gap-1">
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  {{ $website->deadline->format('d M Y') }}{{ $isOverdue ? ' ⚠' : '' }}
                </span>
              @endif
            </div>
          </div>

          {{-- Card Footer Actions --}}
          <div class="ws-card-footer bg-slate-50 border-t border-slate-100 px-4 py-2.5 flex justify-between items-center opacity-0 group-hover:opacity-100 transition-opacity">
            <a href="{{ $website->url }}" target="_blank" rel="noopener noreferrer"
               class="text-xs font-semibold text-slate-600 hover:text-indigo-600 flex items-center gap-1">
              Visit
              <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
            </a>
            <div class="flex items-center gap-3">
              <button @click="openEditModal({{ $website->toJson() }})" class="text-slate-400 hover:text-indigo-600 transition-colors" title="Edit">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>
              </button>
              <form action="{{ route('websites.destroy', $website->id) }}" method="POST" class="inline"
                    @submit.prevent="confirmDeleteWebsite('{{ addslashes($website->name) }}', $el)">
                @csrf @method('DELETE')
                <button type="submit" class="text-slate-400 hover:text-rose-600 transition-colors" title="Delete">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916"/></svg>
                </button>
              </form>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
    @endforeach
  </div>
  @else
  <div class="ws-empty-box bg-white rounded-2xl shadow-sm border border-slate-200 p-12 text-center">
    <span class="text-5xl block mb-3">🌐</span>
    <h3 class="text-lg font-bold text-slate-800 mb-2">No websites added yet</h3>
    <p class="text-slate-500 mb-6">Start by adding the websites built by the Digital Team.</p>
    <button @click="openAddModal" class="btn btn-primary">Add Your First Website</button>
  </div>
  @endif

</div>{{-- /tab built --}}


{{-- ════════════════════════════════════════════════════════════
     TAB 2: WEBSITE PROGRESS
════════════════════════════════════════════════════════════ --}}
<div x-show="activeTab==='progress'" x-transition.opacity>

  @if($progressWebsites->count() > 0)
  <div class="space-y-4">
    @foreach($progressWebsites as $website)
    @php
      $statusSlug  = strtolower(str_replace(['/', ' '], ['-', '-'], $website->status));
      $isOverdue   = $website->isOverdue();
      $pct         = $website->progress_percent;
      $pctColor    = match(true) { $pct >= 100=>'bg-emerald-500', $pct>=75=>'bg-amber-500', $pct>=50=>'bg-blue-500', default=>'bg-indigo-500' };
    @endphp
    <div class="ws-progress-card bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
      <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        {{-- Logo --}}
        <div class="w-12 h-12 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center p-1.5 flex-shrink-0">
          @if($website->logo_path)
            <img src="{{ $website->logo_src }}" alt="" class="max-w-full max-h-full object-contain">
          @else
            <span class="text-2xl">🌐</span>
          @endif
        </div>

        {{-- Info --}}
        <div class="flex-1 min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <h3 class="ws-progress-name font-bold text-slate-800">{{ $website->name }}</h3>
            <span class="badge-{{ $statusSlug }} text-xs font-bold px-2.5 py-1 rounded-full">{{ $website->status }}</span>
            @if($website->category)
              <span class="text-xs bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full">{{ $website->category }}</span>
            @endif
          </div>
          <div class="flex flex-wrap items-center gap-3 text-xs ws-progress-meta text-slate-500">
            <a href="{{ $website->url }}" target="_blank" class="text-indigo-600 hover:underline">{{ $website->clean_domain }}</a>
            @if($website->handler)
              <span>👤 {{ $website->handler->name }}</span>
            @endif
            @if($website->deadline)
              <span class="{{ $isOverdue ? 'text-rose-500 font-bold' : '' }}">
                📅 Deadline: {{ $website->deadline->format('d M Y') }}{{ $isOverdue ? ' ⚠️ Overdue' : '' }}
              </span>
            @endif
          </div>
        </div>

        {{-- Progress percent display --}}
        <div class="text-center flex-shrink-0">
          <div class="text-2xl font-black {{ $pct >= 75 ? 'text-amber-500' : 'text-indigo-600' }}">{{ $pct }}%</div>
          <div class="text-xs text-slate-500">Progress</div>
        </div>
      </div>

      {{-- Progress Bar + Steps --}}
      <div class="mt-4">
        <div class="progress-bar-bg mb-3">
          <div class="progress-bar-fill {{ $pctColor }}" style="width:{{ $pct }}%"></div>
        </div>
        {{-- Step markers --}}
        <div class="flex gap-2">
          @foreach([10,25,50,75,100] as $step)
          @php
            $stepLabel = match($step) { 75=>'75% QC', 100=>'100% Done', default=>"$step%" };
            $isDone    = $pct >= $step;
          @endphp
          <div class="flex-1 text-center">
            <div class="w-full h-1.5 rounded-full {{ $isDone ? ($step>=75 ? 'bg-amber-400' : 'bg-indigo-500') : 'bg-slate-200' }} mb-1"></div>
            <span class="text-[10px] {{ $isDone ? 'text-indigo-600 font-bold' : 'text-slate-400' }}">{{ $stepLabel }}</span>
          </div>
          @endforeach
        </div>
      </div>

      {{-- Notes preview --}}
      @if($website->notes)
        <p class="mt-3 text-xs text-slate-500 bg-slate-50 rounded-lg px-3 py-2 border border-slate-100 line-clamp-2">{{ $website->notes }}</p>
      @endif

      {{-- Quick Actions --}}
      <div class="mt-3 pt-3 border-t border-slate-100 flex items-center gap-2">
        <button @click="openProgressModal({{ $website->toJson() }})"
                class="text-xs btn btn-primary py-1.5 px-3">📊 Update Progress</button>
        <button @click="openEditModal({{ $website->toJson() }})"
                class="text-xs btn btn-secondary py-1.5 px-3">✏️ Full Edit</button>
        <a href="{{ $website->url }}" target="_blank" rel="noopener noreferrer"
           class="text-xs btn btn-secondary py-1.5 px-3">🔗 Visit</a>
      </div>
    </div>
    @endforeach
  </div>
  @else
  <div class="ws-empty-box bg-white rounded-2xl border border-slate-200 shadow-sm p-12 text-center">
    <span class="text-5xl block mb-3">📊</span>
    <h3 class="text-lg font-bold text-slate-800 mb-2">No websites in progress</h3>
    <p class="text-slate-500">Set a website status to "In Progress" or "QC Review" to see it here.</p>
  </div>
  @endif

</div>{{-- /tab progress --}}


{{-- ════════════════════════════════════════════════════════════
     TAB 3: LIVE WEBSITES
════════════════════════════════════════════════════════════ --}}
<div x-show="activeTab==='live'" x-transition.opacity>

  @php
    // Group live websites by category (same logic as Built tab)
    $liveGrouped = collect();
    foreach ($orderArray as $catName) {
      $catSites = $liveWebsites->where('category', $catName)->values();
      if ($catSites->count() > 0) {
        $liveGrouped->put($catName, $catSites);
      }
    }
    // Add uncategorized live sites
    $liveUncategorized = $liveWebsites->where('category', null)->values();
    if ($liveUncategorized->count() > 0) {
      $liveGrouped->put('Uncategorized', $liveUncategorized);
    }
    // Any categories not in orderArray (safety net)
    $liveWebsites->whereNotIn('category', $orderArray)->whereNotNull('category')
      ->groupBy('category')->each(function($sites, $cat) use (&$liveGrouped) {
        if (!$liveGrouped->has($cat)) $liveGrouped->put($cat, $sites->values());
      });
  @endphp

  {{-- Search + Filter bar --}}
  <div class="flex flex-wrap items-center gap-3 mb-5">
    <div class="relative flex-1 min-w-[200px] max-w-xs">
      <input type="text" x-model="liveSearch" placeholder="Search name or domain…"
             class="w-full form-input pl-9 py-2 text-sm rounded-xl">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
    </div>
    <select x-model="liveFilterClass" class="form-select text-sm rounded-xl py-2">
      <option value="">All Classes</option>
      @foreach($orderArray as $catName)
        <option value="{{ $catName }}">{{ $catName }}</option>
      @endforeach
    </select>
    <select x-model="liveFilterHandler" class="form-select text-sm rounded-xl py-2">
      <option value="">All Handlers</option>
      @foreach($users as $u)
        <option value="{{ $u->id }}">{{ $u->name }}</option>
      @endforeach
    </select>
  </div>

  @if($liveWebsites->count() > 0)
  <div class="space-y-10">
    @foreach($liveGrouped as $category => $websites)
    <div>
      {{-- Group Header (read-only, no management buttons) --}}
      <div class="flex items-center gap-3 mb-4">
        <h2 class="ws-section-title text-lg font-black text-slate-800">{{ $category }}</h2>
        <span class="px-2.5 py-1 text-xs font-bold bg-emerald-100 text-emerald-700 rounded-lg">{{ $websites->count() }} Live</span>
        <div class="flex-1 h-px bg-slate-200"></div>
      </div>

      {{-- Cards Grid --}}
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        @foreach($websites as $website)
        <div class="ws-live-card bg-white border border-slate-200 rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden group flex flex-col"
             x-show="liveMatchesFilter({{ json_encode(['name'=>$website->name,'url'=>$website->url,'category'=>$website->category,'handled_by'=>$website->handled_by]) }})"
             style="display:flex">

          {{-- Green top bar --}}
          <div class="h-2 bg-gradient-to-r from-emerald-400 to-green-500"></div>

          <div class="p-5 flex-1 flex flex-col items-center text-center">
            {{-- Live badge --}}
            <span class="badge-live text-xs font-bold px-3 py-1 rounded-full mb-3 flex items-center gap-1">
              <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse inline-block"></span>
              LIVE
            </span>

            {{-- Logo --}}
            <div class="w-16 h-16 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center p-2 mb-3 shadow-sm group-hover:scale-105 transition-transform">
              @if($website->logo_path)
                <img src="{{ $website->logo_src }}" alt="{{ $website->name }}" class="max-w-full max-h-full object-contain">
              @else
                <span class="text-3xl">🌐</span>
              @endif
            </div>

            <h3 class="ws-live-name font-bold text-slate-800 mb-1 leading-tight">{{ $website->name }}</h3>
            <a href="{{ $website->url }}" target="_blank" rel="noopener noreferrer"
               class="ws-domain-text text-xs text-indigo-600 hover:text-indigo-800 break-all mb-2">
              {{ $website->clean_domain }}
            </a>

            {{-- Handler --}}
            @if($website->handler)
              <div class="ws-live-meta flex items-center gap-1.5 text-xs text-slate-500 mt-1">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                {{ $website->handler->name }}
              </div>
            @endif

            {{-- Dates --}}
            <div class="ws-live-meta mt-2 space-y-0.5 text-[10px] text-slate-400">
              @if($website->live_at)
                <div>🚀 Live: {{ $website->live_at->format('d M Y') }}</div>
              @endif
              @if($website->updated_at)
                <div>🔄 Updated: {{ $website->updated_at->format('d M Y') }}</div>
              @endif
            </div>

            {{-- 100% progress bar --}}
            <div class="w-full mt-3 px-1">
              <div class="progress-bar-bg">
                <div class="progress-bar-fill bg-emerald-500" style="width:100%"></div>
              </div>
              <div class="text-[10px] text-emerald-600 font-bold text-right mt-0.5">100% Complete</div>
            </div>
          </div>

          {{-- Card Footer — Visit only, no edit --}}
          <div class="ws-card-footer bg-slate-50 border-t border-slate-100 px-4 py-2.5 flex justify-center items-center">
            <a href="{{ $website->url }}" target="_blank" rel="noopener noreferrer"
               class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
              🔗 Visit Site
              <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
            </a>
          </div>
        </div>
        @endforeach
      </div>
    </div>
    @endforeach
  </div>
  @else
  <div class="ws-empty-box bg-white rounded-2xl border border-slate-200 shadow-sm p-12 text-center">
    <span class="text-5xl block mb-3">🚀</span>
    <h3 class="text-lg font-bold text-slate-800 mb-2">No live websites yet</h3>
    <p class="text-slate-500">Websites marked as "Live" will appear here automatically.</p>
  </div>
  @endif

</div>{{-- /tab live --}}


{{-- ════════════════════════════════════════════════════════════
     MODALS
════════════════════════════════════════════════════════════ --}}

{{-- Add / Edit Website Modal --}}
<div x-show="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center" x-cloak>
  <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="closeModal"></div>
  <div class="ws-modal bg-white rounded-2xl shadow-2xl w-full max-w-xl mx-4 relative z-10 overflow-hidden max-h-[90vh] overflow-y-auto"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100 scale-100"
       x-transition:leave-end="opacity-0 scale-95">

    <div class="ws-modal-header px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
      <h3 class="font-bold text-slate-800" x-text="isEdit ? '✏️ Edit Website' : '➕ Add Website'"></h3>
      <button @click="closeModal" class="text-slate-400 hover:text-slate-600 transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <form :action="formAction" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
      @csrf
      <template x-if="isEdit"><input type="hidden" name="_method" value="PUT"></template>

      {{-- Row 1: Name + Class --}}
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase mb-1">Website Name *</label>
          <input type="text" name="name" x-model="formData.name" required placeholder="e.g. Acme Corp"
                 class="ws-modal-input w-full form-input text-sm rounded-xl">
        </div>
        <div>
          <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase mb-1">Group / Class</label>
          <select name="category" x-model="formData.category" class="ws-modal-input w-full form-select text-sm rounded-xl">
            <option value="">Select a Class…</option>
            @foreach($orderArray as $catName)
              <option value="{{ $catName }}">{{ $catName }}</option>
            @endforeach
          </select>
        </div>
      </div>

      {{-- Row 2: Domain --}}
      <div>
        <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase mb-1">Domain (URL) *</label>
        <input type="url" name="url" x-model="formData.url" required placeholder="https://example.com"
               class="ws-modal-input w-full form-input text-sm rounded-xl">
      </div>

      {{-- Row 3: Handler + Status --}}
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase mb-1">Handled By</label>
          <select name="handled_by" x-model="formData.handled_by" class="ws-modal-input w-full form-select text-sm rounded-xl">
            <option value="">Not assigned</option>
            @foreach($users as $u)
              <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase mb-1">Status</label>
          <select name="status" x-model="formData.status" class="ws-modal-input w-full form-select text-sm rounded-xl">
            @foreach(\App\Models\Website::STATUSES as $s)
              <option value="{{ $s }}">{{ $s }}</option>
            @endforeach
          </select>
        </div>
      </div>

      {{-- Row 4: Start Date + Deadline --}}
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase mb-1">Start Date</label>
          <input type="date" name="start_date" x-model="formData.start_date"
                 class="ws-modal-input w-full form-input text-sm rounded-xl">
        </div>
        <div>
          <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase mb-1">Deadline</label>
          <input type="date" name="deadline" x-model="formData.deadline"
                 class="ws-modal-input w-full form-input text-sm rounded-xl">
        </div>
      </div>

      {{-- Row 5: Progress --}}
      <div>
        <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase mb-1">Progress</label>
        <select name="progress_percent" x-model="formData.progress_percent" class="ws-modal-input w-full form-select text-sm rounded-xl">
          <option value="0">0% — Not Started</option>
          <option value="10">10% — Started / Basic Setup</option>
          <option value="25">25% — Structure / Content Started</option>
          <option value="50">50% — Main Pages Added</option>
          <option value="75">75% — QC Review</option>
          <option value="100">100% — Completed</option>
        </select>
      </div>

      {{-- Row 6: Logo --}}
      <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3" x-data="{ uploadType: 'file' }">
        <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase">Website Logo</label>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="radio" x-model="uploadType" value="file" class="text-indigo-600 focus:ring-indigo-500"> Upload File
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="radio" x-model="uploadType" value="url" class="text-indigo-600 focus:ring-indigo-500"> Image URL
          </label>
        </div>
        <div x-show="uploadType === 'file'">
          <input type="file" name="logo" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
          <p class="mt-1 text-[10px] text-slate-400">Max 2MB. PNG, JPG, GIF, WebP, SVG.</p>
        </div>
        <div x-show="uploadType === 'url'">
          <input type="url" name="logo_url" x-model="formData.logo_url" placeholder="https://example.com/logo.png"
                 class="ws-modal-input w-full form-input text-sm rounded-xl">
        </div>
      </div>

      {{-- Notes --}}
      <div>
        <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase mb-1">Notes</label>
        <textarea name="notes" x-model="formData.notes" rows="2" placeholder="Any notes about this website…"
                  class="ws-modal-input w-full form-input text-sm rounded-xl resize-none"></textarea>
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <button type="button" @click="closeModal" class="btn btn-secondary px-4 py-2">Cancel</button>
        <button type="submit" class="btn btn-primary px-5 py-2.5" x-text="isEdit ? 'Save Changes' : 'Add Website'"></button>
      </div>
    </form>
  </div>
</div>

{{-- Rename Category Modal --}}
<div x-show="renameModalOpen" class="fixed inset-0 z-50 flex items-center justify-center" x-cloak>
  <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="renameModalOpen=false"></div>
  <div class="ws-modal bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 relative z-10 overflow-hidden"
       x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
    <div class="ws-modal-header px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
      <h3 class="font-bold text-slate-800">Rename Group</h3>
      <button @click="renameModalOpen=false" class="text-slate-400 hover:text-slate-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <form action="{{ route('websites.renameCategory') }}" method="POST" class="p-6">
      @csrf @method('PUT')
      <input type="hidden" name="old_category" x-model="renameData.old_category">
      <div class="space-y-4">
        <div>
          <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase mb-1">New Group Name</label>
          <input type="text" name="new_category" x-model="renameData.new_category" required class="ws-modal-input w-full form-input text-sm rounded-xl">
        </div>
      </div>
      <div class="mt-6 flex justify-end gap-3">
        <button type="button" @click="renameModalOpen=false" class="btn btn-secondary px-4 py-2">Cancel</button>
        <button type="submit" class="btn btn-primary px-5 py-2.5">Rename</button>
      </div>
    </form>
  </div>
</div>

{{-- Create Class Modal --}}
<div x-show="createCategoryModalOpen" class="fixed inset-0 z-50 flex items-center justify-center" x-cloak>
  <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="createCategoryModalOpen=false"></div>
  <div class="ws-modal bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 relative z-10 overflow-hidden"
       x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
    <div class="ws-modal-header px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
      <h3 class="font-bold text-slate-800">Create New Class</h3>
      <button @click="createCategoryModalOpen=false" class="text-slate-400 hover:text-slate-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <form action="{{ route('websites.storeCategory') }}" method="POST" class="p-6">
      @csrf
      <div class="space-y-4">
        <div>
          <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase mb-1">Class Name</label>
          <input type="text" name="name" required placeholder="e.g. 1st Class" class="ws-modal-input w-full form-input text-sm rounded-xl">
        </div>
      </div>
      <div class="mt-6 flex justify-end gap-3">
        <button type="button" @click="createCategoryModalOpen=false" class="btn btn-secondary px-4 py-2">Cancel</button>
        <button type="submit" class="btn btn-primary px-5 py-2.5">Create Class</button>
      </div>
    </form>
  </div>
</div>

{{-- Delete Confirmation Modal --}}
<div x-show="deleteModal.open" class="fixed inset-0 z-[60] flex items-center justify-center" x-cloak>
  <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="deleteModal.open=false"></div>
  <div class="ws-modal bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 relative z-10"
       x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
    <div class="p-6">
      <div class="flex items-start gap-4">
        <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <div>
          <h3 class="font-bold text-slate-800 text-lg" x-text="deleteModal.title"></h3>
          <p class="text-sm text-slate-500 mt-1" x-text="deleteModal.message"></p>
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button @click="deleteModal.open=false" class="btn btn-secondary px-4 py-2">Cancel</button>
        <button @click="deleteModal.confirm()" class="btn btn-danger px-5 py-2">Delete</button>
      </div>
    </div>
  </div>
</div>
{{-- Update Progress Modal (simple: status + percent + note only) --}}
<div x-show="progressModal.open" class="fixed inset-0 z-50 flex items-center justify-center" x-cloak>
  <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="progressModal.open=false"></div>
  <div class="ws-modal bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 relative z-10 overflow-hidden"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100 scale-100"
       x-transition:leave-end="opacity-0 scale-95">

    {{-- Header --}}
    <div class="ws-modal-header px-6 py-4 border-b border-slate-100 flex items-center gap-3 bg-slate-50">
      <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
        <span class="text-lg">📊</span>
      </div>
      <div class="flex-1 min-w-0">
        <h3 class="font-bold text-slate-800 text-sm" x-text="progressModal.websiteName"></h3>
        <p class="text-xs text-slate-500">Update Progress</p>
      </div>
      <button @click="progressModal.open=false" class="text-slate-400 hover:text-slate-600">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <form :action="progressModal.action" method="POST" class="p-6 space-y-5">
      @csrf
      <input type="hidden" name="_method" value="PUT">
      {{-- Pass all required fields hidden so the update() validation doesn't fail --}}
      <input type="hidden" name="name" :value="progressModal.name">
      <input type="hidden" name="url" :value="progressModal.url">
      <input type="hidden" name="category" :value="progressModal.category">
      <input type="hidden" name="handled_by" :value="progressModal.handled_by">
      <input type="hidden" name="start_date" :value="progressModal.start_date">
      <input type="hidden" name="deadline" :value="progressModal.deadline">

      {{-- STATUS --}}
      <div>
        <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase mb-2">Status</label>
        <div class="grid grid-cols-2 gap-2">
          @foreach(\App\Models\Website::STATUSES as $s)
          @php $sSlug = strtolower(str_replace(['/', ' '], '-', $s)); @endphp
          <label class="flex items-center gap-2 text-sm cursor-pointer border rounded-xl px-3 py-2 transition-all"
                 :class="progressModal.status === '{{ $s }}' ? 'border-indigo-500 bg-indigo-50 font-semibold text-indigo-700' : 'border-slate-200 text-slate-600 hover:border-slate-300'">
            <input type="radio" name="status" value="{{ $s }}" x-model="progressModal.status" class="sr-only">
            <span class="w-2 h-2 rounded-full badge-{{ $sSlug }} inline-block flex-shrink-0" style="background: currentColor"></span>
            {{ $s }}
          </label>
          @endforeach
        </div>
      </div>

      {{-- PROGRESS % --}}
      <div>
        <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase mb-2">
          Progress — <span class="text-indigo-600" x-text="progressModal.percent + '%'"></span>
        </label>
        <div class="flex gap-2">
          @foreach([0, 10, 25, 50, 75, 100] as $step)
          <button type="button"
                  @click="progressModal.percent = '{{ $step }}';
                          @if($step === 100)
                            progressModal.status = 'Completed';
                          @elseif($step === 75)
                            progressModal.status = 'QC Review';
                          @elseif($step > 0)
                            if (progressModal.status === 'Completed' || progressModal.status === 'Draft') progressModal.status = 'In Progress';
                          @endif"
                  :class="progressModal.percent == '{{ $step }}' ? 'bg-indigo-600 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                  class="flex-1 text-xs font-bold py-2 rounded-xl transition-all">
            {{ $step == 75 ? '75 QC' : ($step == 100 ? '100 ✓' : $step . '%') }}
          </button>
          @endforeach
        </div>
        <input type="hidden" name="progress_percent" :value="progressModal.percent">
        {{-- Live progress bar --}}
        <div class="mt-3 progress-bar-bg">
          <div class="progress-bar-fill bg-indigo-500" :style="'width:' + progressModal.percent + '%'"></div>
        </div>
      </div>

      {{-- NOTE --}}
      <div>
        <label class="ws-modal-label block text-xs font-bold text-slate-700 uppercase mb-2">Note / Update</label>
        <textarea name="notes" x-model="progressModal.notes" rows="3"
                  placeholder="What was completed? What's next?"
                  class="ws-modal-input w-full form-input text-sm rounded-xl resize-none"></textarea>
      </div>

      <div class="flex justify-end gap-3">
        <button type="button" @click="progressModal.open=false" class="btn btn-secondary px-4 py-2">Cancel</button>
        <button type="submit" class="btn btn-primary px-5 py-2.5">💾 Save Progress</button>
      </div>
    </form>
  </div>
</div>

</div>{{-- /x-data --}}

<script>
function websitesPage() {
  return {
    activeTab: '{{ $tab }}',
    search: '',
    filterStatus: '',
    filterHandler: '',
    liveSearch: '',
    liveFilterClass: '',
    liveFilterHandler: '',
    modalOpen: false,
    renameModalOpen: false,
    createCategoryModalOpen: false,
    isEdit: false,
    formAction: '{{ route('websites.store') }}',
    formData: {
      id: null, name: '', url: '', category: '', logo_url: '',
      handled_by: '', status: 'Draft', progress_percent: '0',
      start_date: '', deadline: '', notes: ''
    },
    progressModal: {
      open: false, websiteName: '', name: '', url: '', category: '',
      handled_by: '', start_date: '', deadline: '', status: 'In Progress',
      percent: '0', notes: '', action: ''
    },
    renameData: { old_category: '', new_category: '' },
    deleteModal: {
      open: false, title: '', message: '', confirm: () => {}
    },

    matchesFilter(w) {
      const s = this.search.toLowerCase();
      if (s && !w.name.toLowerCase().includes(s) && !w.url.toLowerCase().includes(s)) return false;
      if (this.filterStatus && w.status !== this.filterStatus) return false;
      if (this.filterHandler && String(w.handled_by) !== String(this.filterHandler)) return false;
      return true;
    },

    liveMatchesFilter(w) {
      const s = this.liveSearch.toLowerCase();
      if (s && !w.name.toLowerCase().includes(s) && !w.url.toLowerCase().includes(s)) return false;
      if (this.liveFilterClass && w.category !== this.liveFilterClass) return false;
      if (this.liveFilterHandler && String(w.handled_by) !== String(this.liveFilterHandler)) return false;
      return true;
    },

    openAddModal() {
      this.isEdit = false;
      this.formAction = '{{ route('websites.store') }}';
      this.formData = { id:null, name:'', url:'', category:'', logo_url:'', handled_by:'', status:'Draft', progress_percent:'0', start_date:'', deadline:'', notes:'' };
      this.modalOpen = true;
    },
    openEditModal(website) {
      this.isEdit = true;
      this.formAction = `/websites/${website.id}`;
      this.formData = {
        ...website,
        logo_url: website.logo_path && website.logo_path.startsWith('http') ? website.logo_path : '',
        handled_by: website.handled_by ? String(website.handled_by) : '',
        progress_percent: website.progress_percent !== undefined ? String(website.progress_percent) : '0',
        start_date: website.start_date ? website.start_date.substring(0, 10) : '',
        deadline: website.deadline ? website.deadline.substring(0, 10) : '',
      };
      this.modalOpen = true;
    },
    openProgressModal(website) {
      this.progressModal = {
        open: true,
        websiteName: website.name,
        name: website.name,
        url: website.url,
        category: website.category || '',
        handled_by: website.handled_by ? String(website.handled_by) : '',
        start_date: website.start_date ? website.start_date.substring(0, 10) : '',
        deadline: website.deadline ? website.deadline.substring(0, 10) : '',
        status: website.status || 'In Progress',
        percent: website.progress_percent !== undefined ? String(website.progress_percent) : '0',
        notes: website.notes || '',
        action: `/websites/${website.id}`,
      };
    },
    openRenameModal(oldCat) {
      this.renameData.old_category = oldCat;
      this.renameData.new_category = oldCat === 'Uncategorized' ? '' : oldCat;
      this.renameModalOpen = true;
    },
    closeModal() {
      this.modalOpen = false;
      this.renameModalOpen = false;
      this.createCategoryModalOpen = false;
    },

    confirmDeleteWebsite(name, form) {
      this.deleteModal.title = 'Delete Website?';
      this.deleteModal.message = `Are you sure you want to delete "${name}"? This action cannot be undone.`;
      this.deleteModal.confirm = () => { form.submit(); };
      this.deleteModal.open = true;
    },

    confirmDeleteCategory(name, form) {
      this.deleteModal.title = 'Delete Class?';
      this.deleteModal.message = `Delete class "${name}"? All websites inside will become Uncategorized.`;
      this.deleteModal.confirm = () => { form.submit(); };
      this.deleteModal.open = true;
    },
  }
}
</script>
@endsection
