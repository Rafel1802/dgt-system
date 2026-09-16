@extends('layouts.app')

@section('title', 'Analytics Import')
@section('page_title', 'Analytics Import')
@section('back_url', route('social-media.dashboard'))

@section('content')
@php
  $canUpload = auth()->user()?->hasAnyRole(['super-admin', 'admin-digital', 'social_admin', 'social_qc', 'boss', 'digital-team', 'supervisor']);
  $canEdit   = $canUpload;
@endphp
<div class="animate-fade-in w-full space-y-8">

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  {{-- ── Hero Banner ─────────────────────────────────────────────────────── --}}
  <div class="overflow-hidden rounded-2xl relative" style="background: linear-gradient(135deg, #1e40af 0%, #2563eb 52%, #3b82f6 100%);">
    <div class="absolute inset-0 opacity-10" style="background-image: url(&quot;data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E&quot;);"></div>
    <div class="relative p-8 flex flex-col sm:flex-row sm:items-center gap-6">
      <div class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded-2xl bg-white/15 shadow-inner ring-1 ring-white/25">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-10 w-10 text-white">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
        </svg>
      </div>
      <div class="flex-1">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-100 mb-1">Social Media</p>
        <h1 class="font-display text-3xl font-black text-white sm:text-4xl">Analytics Import</h1>
        <p class="mt-2 text-sm font-medium text-blue-100 max-w-2xl">Upload weekly PDF analytics reports for one or more classes. Each file can be included in report exports.</p>
      </div>
    </div>
  </div>

  <div class="grid gap-8 {{ $canUpload ? 'xl:grid-cols-[400px_1fr]' : 'grid-cols-1' }}">

    {{-- ── Upload Card ─────────────────────────────────────────────────────── --}}
    @if($canUpload)
    <div>
      <div class="card border border-slate-200 dark:border-slate-700 overflow-hidden sticky top-4">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-violet-50/50 dark:bg-violet-900/10">
          <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-violet-500"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
            Upload Analytics Report
          </h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">PDF & Canva link required · Max 100 MB · Replaces existing files for the same classes and week</p>
        </div>
        <form action="{{ route('social-media.analytics.store') }}" method="POST" enctype="multipart/form-data"
          class="p-6 space-y-5" x-data="analyticsUploadForm()" @submit.prevent="submit($event)">
          @csrf

          <div x-show="error" x-cloak class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" x-text="error"></div>

          {{-- Class --}}
          <div x-data="{ open: false, selected: @js(array_map('strval', old('class_ids', []))) }" class="relative">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Classes <span class="text-red-400">*</span></label>
            <button type="button" @click="open = !open"
              class="form-input w-full min-h-11 rounded-xl text-left text-sm flex items-center justify-between gap-3">
              <span x-text="selected.length ? selected.length + ' class' + (selected.length === 1 ? '' : 'es') + ' selected' : '— Select Classes —'"
                :class="selected.length ? 'text-slate-800 dark:text-slate-100 font-semibold' : 'text-slate-500'"></span>
              <svg class="h-4 w-4 text-slate-400 transition-transform" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-cloak @click.outside="open = false"
              class="absolute z-30 mt-2 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-800">
              @foreach($classes as $class)
                <label class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-blue-50 dark:text-slate-200 dark:hover:bg-slate-700">
                  <input type="checkbox" name="class_ids[]" value="{{ $class->id }}" x-model="selected"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                  <span>{{ $class->name }}</span>
                </label>
              @endforeach
            </div>
            <p class="mt-1.5 text-xs text-slate-400">Select every class covered by this report.</p>
            @error('class_ids')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
            @error('class_ids.*')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          {{-- Date Range --}}
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Date From <span class="text-red-400">*</span></label>
              <input type="date" name="date_from" required
                value="{{ old('date_from', now()->startOfWeek()->toDateString()) }}"
                class="form-input w-full text-sm rounded-xl">
              @error('date_from')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
              @enderror
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Date To <span class="text-red-400">*</span></label>
              <input type="date" name="date_to" required
                value="{{ old('date_to', now()->endOfWeek()->toDateString()) }}"
                class="form-input w-full text-sm rounded-xl">
              @error('date_to')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
              @enderror
            </div>
          </div>

          {{-- File --}}
          <div x-data="{ fileName: '', isDragging: false }">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Analytics PDF <span class="text-red-400">*</span></label>
            <label 
              @dragover.prevent="isDragging = true"
              @dragleave.prevent="isDragging = false"
              @drop.prevent="isDragging = false; const files = $event.dataTransfer.files; if(files.length) { $refs.fileInput.files = files; fileName = files[0].name; }"
              :class="isDragging ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20' : 'border-slate-200 dark:border-slate-700 hover:border-violet-400 hover:bg-violet-50/40 dark:hover:border-violet-600 dark:hover:bg-violet-900/10'"
              class="flex flex-col items-center justify-center gap-3 w-full h-32 border-2 border-dashed rounded-xl cursor-pointer transition-all group relative">
              <input type="file" x-ref="fileInput" name="file" accept=".pdf" required class="hidden"
                @change="fileName = $event.target.files[0]?.name || ''">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                :class="isDragging ? 'text-violet-500' : 'text-slate-300 group-hover:text-violet-500'"
                class="w-7 h-7 transition-colors pointer-events-none">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
              </svg>
              <div class="text-center pointer-events-none">
                <p class="text-xs font-semibold transition-colors" :class="isDragging ? 'text-violet-600' : 'text-slate-600 dark:text-slate-300 group-hover:text-violet-600'" x-text="isDragging ? 'Drop PDF here' : (fileName || 'Click or drag PDF file here')"></p>
                <p class="text-[11px] text-slate-400 mt-0.5" x-show="!fileName && !isDragging">PDF only, max 100 MB</p>
              </div>
            </label>
            @error('file')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          {{-- Canva Link --}}
          <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5 text-cyan-500" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/>
              </svg>
              Canva Link <span class="text-red-400">*</span>
            </label>
            <div class="relative rounded-xl shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-cyan-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
              </div>
              <input type="text" name="canva_link" value="{{ old('canva_link') }}" required
                placeholder="https://www.canva.com/design/... or URL"
                class="form-input w-full pl-10 text-sm rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 focus:border-cyan-500 focus:ring-cyan-500/20">
            </div>
            <p class="mt-1 text-[11px] text-slate-400">Canva presentation link is required.</p>
            @error('canva_link')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          <button type="submit"
            :disabled="uploading"
            class="w-full btn btn-primary py-2.5 flex items-center justify-center gap-2 disabled:cursor-not-allowed disabled:opacity-70">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
            <span x-text="uploading ? 'Uploading ' + progress + '%' : 'Upload Analytics'"></span>
          </button>

          <div x-show="uploading || progress === 100" x-cloak class="space-y-2" aria-live="polite">
            <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-300">
              <span x-text="progress === 100 ? 'Save complete — refreshing…' : 'Uploading / Saving…'"></span>
              <span x-text="progress + '%'"></span>
            </div>
            <div class="h-3 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
              <div class="h-full rounded-full bg-blue-600 transition-[width] duration-200 ease-out"
                :style="'width: ' + progress + '%'" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
          </div>
        </form>
      </div>
    </div>
    @endif

    {{-- ── Uploaded Files Table ─────────────────────────────────────────────── --}}
    <turbo-frame id="analytics-manager" data-turbo-action="advance">
      <div class="space-y-5">
        {{-- Filters --}}
        <form method="GET" action="{{ route('social-media.analytics.index') }}" class="card p-4 border border-slate-200 dark:border-slate-700"
              x-data="{
                  class_id: '{{ $classId }}',
                  date_from: '{{ $dateFrom }}',
                  date_to: '{{ $dateTo }}'
              }">
          <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Filter by Class</label>
              <select name="class_id" x-model="class_id" class="form-select w-full text-sm rounded-xl">
                <option value="">All Classes</option>
                @foreach($classes as $class)
                  <option value="{{ $class->id }}">{{ $class->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="flex-1 min-w-[140px]">
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Date From</label>
              <input type="date" name="date_from" x-model="date_from" class="form-input w-full text-sm rounded-xl">
            </div>
            <div class="flex-1 min-w-[140px]">
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Date To</label>
              <input type="date" name="date_to" x-model="date_to" class="form-input w-full text-sm rounded-xl">
            </div>
            <div class="flex gap-2 w-full sm:w-auto">
              <button type="submit" class="btn btn-primary text-sm flex-1 sm:flex-none px-4 py-2 text-center">Search</button>
              <a href="{{ route('social-media.analytics.index') }}" data-turbo-action="advance" class="btn btn-secondary text-sm flex-1 sm:flex-none px-4 py-2 text-center">Clear</a>
            </div>
          </div>
        </form>

      {{-- ── File Manager Grid ─────────────────────────────────────────────── --}}
      <div class="card border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
          <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-violet-500">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
            </svg>
            Uploaded Analytics Files
          </h2>
          <span class="text-xs font-semibold text-slate-500 bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-full">
            {{ $analytics->total() }} file{{ $analytics->total() !== 1 ? 's' : '' }}
          </span>
        </div>

        @if($analytics->isEmpty())
          <div class="flex flex-col items-center justify-center py-20 text-slate-400">
            <div class="w-20 h-20 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-10 h-10 text-slate-300 dark:text-slate-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
              </svg>
            </div>
            <p class="font-semibold text-slate-600 dark:text-slate-300">No analytics files uploaded yet.</p>
            <p class="text-sm mt-1 text-slate-400">Use the upload form on the left to add your first file.</p>
          </div>
        @else
          <div class="p-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2 gap-3" id="analytics-file-grid">
            @foreach($analytics as $analytic)
            <div class="group relative flex items-start gap-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 p-4 hover:border-violet-300 dark:hover:border-violet-600 hover:shadow-lg hover:shadow-violet-100/50 dark:hover:shadow-violet-900/20 transition-all duration-200">

              {{-- Icon (PDF or Canva) --}}
              @if($analytic->fileExists())
              <div class="flex-shrink-0 w-12 h-14 rounded-xl flex flex-col items-center justify-center gap-0.5 shadow-sm relative"
                style="background: linear-gradient(145deg, #ef4444 0%, #dc2626 100%)">
                <span class="text-[9px] font-black text-white/80 tracking-widest mt-1">PDF</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="white" class="w-5 h-5 opacity-90">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
              </div>
              @elseif($analytic->canva_link)
              <div class="flex-shrink-0 w-12 h-14 rounded-xl flex flex-col items-center justify-center gap-0.5 shadow-sm relative"
                style="background: linear-gradient(145deg, #00c4cc 0%, #7d2ae8 100%)">
                <span class="text-[9px] font-black text-white/95 tracking-wider mt-1">CANVA</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="white" class="w-5 h-5 opacity-95">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>
              </div>
              @else
              <div class="flex-shrink-0 w-12 h-14 rounded-xl flex flex-col items-center justify-center gap-0.5 shadow-sm relative bg-slate-400">
                <span class="text-[9px] font-black text-white/80 tracking-widest mt-1">PDF</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="white" class="w-5 h-5 opacity-90">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                <div class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-amber-400 rounded-full flex items-center justify-center">
                  <span class="text-white text-[8px] font-black">!</span>
                </div>
              </div>
              @endif

              {{-- File Info --}}
              <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate" title="{{ $analytic->original_name }}">
                  {{ $analytic->original_name }}
                </p>

                {{-- Meta chips row --}}
                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                  {{-- Date range --}}
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-violet-50 dark:bg-violet-900/30 text-[11px] font-semibold text-violet-700 dark:text-violet-300">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5" />
                    </svg>
                    {{ $analytic->dateRangeLabel() }}
                  </span>

                  {{-- Classes --}}
                  @foreach($analytic->classes as $class)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-900/30 text-[11px] font-semibold text-blue-700 dark:text-blue-300">{{ $class->name }}</span>
                  @endforeach

                  {{-- Canva link chip --}}
                  @if($analytic->canva_link)
                    <a href="{{ $analytic->formattedCanvaLink() }}" target="_blank" rel="noopener noreferrer"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-cyan-50 dark:bg-cyan-900/30 text-[11px] font-semibold text-cyan-700 dark:text-cyan-300 hover:bg-cyan-100 dark:hover:bg-cyan-900/50 transition-colors"
                      title="Open Canva link in new tab">
                      <svg class="w-3 h-3 text-cyan-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                      </svg>
                      Canva Link
                    </a>
                  @endif

                  @if(!$analytic->fileExists() && !$analytic->canva_link)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-900/30 text-[11px] font-semibold text-amber-700 dark:text-amber-300">
                      ⚠ File missing
                    </span>
                  @endif
                </div>

                {{-- Uploader + date --}}
                <div class="flex items-center gap-2 mt-2">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 text-slate-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                  </svg>
                  <span class="text-xs text-slate-400">{{ $analytic->uploader?->name ?? '—' }}</span>
                  <span class="text-slate-200 dark:text-slate-600 text-xs">·</span>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 text-slate-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span class="text-xs text-slate-400 whitespace-nowrap">{{ $analytic->created_at->format('d M Y H:i') }}</span>
                </div>

                {{-- Action buttons --}}
                <div class="flex flex-wrap items-center gap-2 mt-3">
                  @if($analytic->fileExists())
                    <button type="button"
                      onclick="openPreviewModal('{{ route('social-media.analytics.preview', $analytic) }}', '{{ addslashes($analytic->original_name) }}')"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-violet-600 hover:text-white dark:hover:bg-violet-600 dark:hover:text-white text-xs font-semibold transition-all duration-150 shadow-sm">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      </svg>
                      Preview
                    </button>
                    
                    <button type="button"
                      onclick="openDownloadModal('{{ route('social-media.analytics.download', $analytic) }}', '{{ addslashes($analytic->original_name) }}')"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-violet-600 hover:text-white dark:hover:bg-violet-600 dark:hover:text-white text-xs font-semibold transition-all duration-150 shadow-sm">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                      </svg>
                      Download
                    </button>
                  @endif

                  @if($analytic->canva_link)
                    <a href="{{ $analytic->formattedCanvaLink() }}" target="_blank" rel="noopener noreferrer"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gradient-to-r from-cyan-500 to-teal-600 hover:from-cyan-600 hover:to-teal-700 text-white text-xs font-bold transition-all duration-150 shadow-sm shadow-cyan-500/20 hover:scale-[1.02] active:scale-[0.98]">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                      </svg>
                      Open Canva
                    </a>
                  @endif

                  @if($canEdit)
                  {{-- Edit Details button (QC, Admin Digital, Super Admin) --}}
                  <button type="button"
                    data-id="{{ $analytic->id }}"
                    data-update-url="{{ route('social-media.analytics.update', $analytic) }}"
                    data-file-name="{{ $analytic->original_name }}"
                    data-date-from="{{ $analytic->date_from->format('Y-m-d') }}"
                    data-date-to="{{ $analytic->date_to->format('Y-m-d') }}"
                    data-class-ids="{{ json_encode($analytic->classes->pluck('id')->values()->all()) }}"
                    data-uploaded-by="{{ $analytic->uploaded_by }}"
                    data-canva-link="{{ $analytic->canva_link ?? '' }}"
                    onclick="openEditAnalyticModalFromBtn(this)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-violet-600 hover:text-white dark:hover:bg-violet-600 dark:hover:text-white text-xs font-semibold transition-all duration-150 shadow-sm"
                    title="Edit date, classes & details">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                    Edit
                  </button>
                  @endif

                  @if($canUpload)
                  {{-- Delete button triggers custom modal --}}
                  <button type="button"
                    onclick="openDeleteModal('{{ route('social-media.analytics.destroy', $analytic) }}', '{{ addslashes($analytic->original_name) }}')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-400 hover:bg-red-500 hover:text-white dark:hover:bg-red-500 dark:hover:text-white text-xs font-semibold transition-all duration-150 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                    </svg>
                    Delete
                  </button>
                  @endif
                </div>
              </div>
            </div>
            @endforeach
          </div>

          @if($analytics->hasPages())
            <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700">
              {{ $analytics->links() }}
            </div>
          @endif
        @endif
      </div>
    </turbo-frame>
    </div>
  </div>

  {{-- ══════════════════════════════════════════════════════════════════════════
       Beautiful Download Confirmation Modal
  ══════════════════════════════════════════════════════════════════════════ --}}
  <div id="downloadModal"
    class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
    style="display:none!important"
    aria-modal="true" role="dialog" aria-labelledby="downloadModalTitle">

    {{-- Backdrop --}}
    <div id="downloadModalBackdrop"
      onclick="closeDownloadModal()"
      class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
      style="opacity:0;transition:opacity .2s ease"></div>

    {{-- Panel --}}
    <div id="downloadModalPanel"
      class="relative w-full max-w-md rounded-3xl bg-white dark:bg-slate-800 shadow-2xl ring-1 ring-slate-900/10 dark:ring-white/10 overflow-hidden"
      style="transform:scale(.93) translateY(16px);opacity:0;transition:transform .25s cubic-bezier(.34,1.56,.64,1),opacity .2s ease">

      {{-- Violet header strip --}}
      <div class="h-1.5 w-full" style="background:linear-gradient(90deg,#8b5cf6,#a855f7)"></div>

      <div class="px-7 pt-7 pb-6">

        {{-- Icon --}}
        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-violet-50 dark:bg-violet-900/20 ring-1 ring-violet-100 dark:ring-violet-800">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-8 w-8 text-violet-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
        </div>

        {{-- Title + body --}}
        <h3 id="downloadModalTitle" class="text-center text-xl font-black text-slate-800 dark:text-slate-100">
          Download Analytics File
        </h3>
        <p class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">
          You are about to download
        </p>
        <p id="downloadModalFileName"
          class="mt-1 text-center text-sm font-bold text-slate-700 dark:text-slate-200 truncate px-2"></p>
        
        {{-- Progress Bar (Hidden initially) --}}
        <div id="downloadModalProgressContainer" style="display:none;" class="mt-5 space-y-2">
           <div class="flex items-center justify-between text-xs font-bold text-violet-600 dark:text-violet-400">
             <span>Downloading...</span>
             <span id="downloadModalProgressText">0%</span>
           </div>
           <div class="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
             <div id="downloadModalProgressBar" class="h-full rounded-full bg-violet-500 transition-[width] duration-200 ease-out"
               style="width: 0%"></div>
           </div>
        </div>

        {{-- Buttons --}}
        <div id="downloadModalButtons" class="mt-7 flex flex-col-reverse sm:flex-row gap-3">
          <button type="button" onclick="closeDownloadModal()"
            class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-600 dark:hover:text-white hover:border-slate-300 dark:hover:border-slate-500 hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Cancel
          </button>
          <button type="button" onclick="performDownload()"
            class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-bold text-white shadow-md active:scale-[0.98]"
            style="background:linear-gradient(135deg,#8b5cf6 0%,#a855f7 100%);box-shadow:0 4px 14px rgba(139,92,246,.35);transition:all 0.2s cubic-bezier(0.4, 0, 0.2, 1);"
            onmouseover="this.style.transform='translateY(-2px) scale(1.02)';this.style.boxShadow='0 8px 20px rgba(139,92,246,.5)';this.style.filter='brightness(1.08)';"
            onmouseout="this.style.transform='';this.style.boxShadow='0 4px 14px rgba(139,92,246,.35)';this.style.filter='';"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Yes, Download
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- ══════════════════════════════════════════════════════════════════════════
       Beautiful Delete Confirmation Modal
  ══════════════════════════════════════════════════════════════════════════ --}}
  <div id="deleteModal"
    class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
    style="display:none!important"
    aria-modal="true" role="dialog" aria-labelledby="deleteModalTitle">

    {{-- Backdrop --}}
    <div id="deleteModalBackdrop"
      onclick="closeDeleteModal()"
      class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
      style="opacity:0;transition:opacity .2s ease"></div>

    {{-- Panel --}}
    <div id="deleteModalPanel"
      class="relative w-full max-w-md rounded-3xl bg-white dark:bg-slate-800 shadow-2xl ring-1 ring-slate-900/10 dark:ring-white/10 overflow-hidden"
      style="transform:scale(.93) translateY(16px);opacity:0;transition:transform .25s cubic-bezier(.34,1.56,.64,1),opacity .2s ease">

      {{-- Red danger header strip --}}
      <div class="h-1.5 w-full" style="background:linear-gradient(90deg,#ef4444,#f97316)"></div>

      <div class="px-7 pt-7 pb-6">

        {{-- Icon --}}
        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-red-50 dark:bg-red-900/20 ring-1 ring-red-100 dark:ring-red-800">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-8 w-8 text-red-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
          </svg>
        </div>

        {{-- Title + body --}}
        <h3 id="deleteModalTitle" class="text-center text-xl font-black text-slate-800 dark:text-slate-100">
          Delete Analytics File
        </h3>
        <p class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">
          You are about to permanently delete
        </p>
        <p id="deleteModalFileName"
          class="mt-1 text-center text-sm font-bold text-slate-700 dark:text-slate-200 truncate px-2"></p>
        <p class="mt-2 text-center text-xs text-slate-400 dark:text-slate-500">
          This action <span class="font-semibold text-red-500">cannot be undone</span>. The file will be permanently removed.
        </p>

        {{-- Buttons --}}
        <div class="mt-7 flex flex-col-reverse sm:flex-row gap-3">
          <button type="button" onclick="closeDeleteModal()"
            class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-600 dark:hover:text-white hover:border-slate-300 dark:hover:border-slate-500 hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Cancel
          </button>
          <form id="deleteModalForm" method="POST" action="" class="flex-1">
            @csrf
            @method('DELETE')
            <button type="submit"
              class="w-full inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-bold text-white shadow-md active:scale-[0.98]"
              style="background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);box-shadow:0 4px 14px rgba(239,68,68,.35);transition:all 0.2s cubic-bezier(0.4, 0, 0.2, 1);"
              onmouseover="this.style.transform='translateY(-2px) scale(1.02)';this.style.boxShadow='0 8px 20px rgba(239,68,68,.5)';this.style.filter='brightness(1.08)';"
              onmouseout="this.style.transform='';this.style.boxShadow='0 4px 14px rgba(239,68,68,.35)';this.style.filter='';"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
              </svg>
              Yes, Delete File
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- ══════════════════════════════════════════════════════════════════════════
       Beautiful Canva Link Edit/Add Modal
  ══════════════════════════════════════════════════════════════════════════ --}}
  <div id="canvaModal"
    class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
    style="display:none!important"
    aria-modal="true" role="dialog" aria-labelledby="canvaModalTitle">

    {{-- Backdrop --}}
    <div id="canvaModalBackdrop"
      onclick="closeCanvaModal()"
      class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
      style="opacity:0;transition:opacity .2s ease"></div>

    {{-- Panel --}}
    <div id="canvaModalPanel"
      class="relative w-full max-w-md rounded-3xl bg-white dark:bg-slate-800 shadow-2xl ring-1 ring-slate-900/10 dark:ring-white/10 overflow-hidden"
      style="transform:scale(.93) translateY(16px);opacity:0;transition:transform .25s cubic-bezier(.34,1.56,.64,1),opacity .2s ease">

      {{-- Cyan-teal header strip --}}
      <div class="h-1.5 w-full" style="background:linear-gradient(90deg,#06b6d4,#0d9488)"></div>

      <div class="px-7 pt-7 pb-6">

        {{-- Icon --}}
        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-cyan-50 dark:bg-cyan-900/20 ring-1 ring-cyan-100 dark:ring-cyan-800">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-8 w-8 text-cyan-600 dark:text-cyan-400">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
          </svg>
        </div>

        {{-- Title + body --}}
        <h3 id="canvaModalTitle" class="text-center text-xl font-black text-slate-800 dark:text-slate-100">
          Canva Link
        </h3>
        <p id="canvaModalFileName"
          class="mt-1 text-center text-xs font-semibold text-slate-500 dark:text-slate-400 truncate px-2"></p>

        <form id="canvaModalForm" method="POST" action="" class="mt-5 space-y-4" onsubmit="handleCanvaModalSubmit(event)">
          @csrf
          @method('PATCH')
          <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">
              Canva URL
            </label>
            <input type="text" id="canvaModalInput" name="canva_link"
              placeholder="https://www.canva.com/design/... or URL"
              class="form-input w-full text-sm rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 focus:border-cyan-500 focus:ring-cyan-500/20">
            <p class="mt-1 text-[11px] text-slate-400">Enter or clear the link to the Canva presentation.</p>
          </div>

          <div id="canvaModalError" style="display:none;" class="rounded-xl border border-red-200 bg-red-50 p-2.5 text-xs font-semibold text-red-600"></div>

          {{-- Buttons --}}
          <div class="mt-6 flex flex-col-reverse sm:flex-row gap-3">
            <button type="button" onclick="closeCanvaModal()"
              class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-600 dark:hover:text-white hover:border-slate-300 dark:hover:border-slate-500 active:scale-[0.98] transition-all duration-200 shadow-sm">
              Cancel
            </button>
            <button type="submit" id="canvaModalSubmitBtn"
              class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-bold text-white shadow-md active:scale-[0.98]"
              style="background:linear-gradient(135deg,#06b6d4 0%,#0d9488 100%);box-shadow:0 4px 14px rgba(6,182,212,.35);transition:all 0.2s cubic-bezier(0.4, 0, 0.2, 1);"
            >
              Save Link
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  @if($canEdit)
  {{-- ══════════════════════════════════════════════════════════════════════════
       Beautiful Edit Analytics Modal (QC, Admin Digital, Super Admin)
  ══════════════════════════════════════════════════════════════════════════ --}}
  <div id="editAnalyticModal"
    class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
    style="display:none!important"
    aria-modal="true" role="dialog" aria-labelledby="editAnalyticModalTitle">

    {{-- Backdrop --}}
    <div id="editAnalyticModalBackdrop"
      onclick="closeEditModal()"
      class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
      style="opacity:0;transition:opacity .2s ease"></div>

    {{-- Panel --}}
    <div id="editAnalyticModalPanel"
      class="relative w-full max-w-lg max-h-[90vh] flex flex-col rounded-3xl bg-white dark:bg-slate-800 shadow-2xl ring-1 ring-slate-900/10 dark:ring-white/10 overflow-hidden"
      style="transform:scale(.93) translateY(16px);opacity:0;transition:transform .25s cubic-bezier(.34,1.56,.64,1),opacity .2s ease">

      {{-- Violet-indigo header strip --}}
      <div class="h-1.5 w-full" style="background:linear-gradient(90deg,#6366f1,#8b5cf6,#a855f7)"></div>

      {{-- Modal Header --}}
      <div class="px-6 pt-5 pb-4 border-b border-slate-100 dark:border-slate-700/80 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
          <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-violet-50 dark:bg-violet-900/20 ring-1 ring-violet-200 dark:ring-violet-800 text-violet-600 dark:text-violet-400">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
            </svg>
          </div>
          <div class="min-w-0">
            <h3 id="editAnalyticModalTitle" class="text-base font-black text-slate-800 dark:text-slate-100 leading-tight">
              Edit Analytics Details
            </h3>
            <p id="editAnalyticModalFileName" class="text-xs text-slate-500 dark:text-slate-400 truncate max-w-[280px] sm:max-w-xs mt-0.5 font-medium"></p>
          </div>
        </div>
        <button type="button" onclick="closeEditModal()"
          class="flex items-center justify-center w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/60 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      {{-- Form --}}
      <form id="editAnalyticModalForm" method="POST" enctype="multipart/form-data" onsubmit="handleEditModalSubmit(event)"
        class="flex-1 overflow-y-auto p-6 space-y-4 text-left">
        @csrf
        @method('PUT')

        <div id="editModalError" style="display:none;" class="rounded-xl border border-red-200 bg-red-50 dark:border-red-800/60 dark:bg-red-900/20 p-3 text-xs font-semibold text-red-600 dark:text-red-400"></div>

        {{-- Classes Selection --}}
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Classes <span class="text-red-400">*</span>
            </label>
            <div class="flex items-center gap-2 text-[11px]">
              <button type="button" onclick="setAllEditClasses(true)" class="text-violet-600 dark:text-violet-400 hover:underline font-medium">Select all</button>
              <span class="text-slate-300 dark:text-slate-600">·</span>
              <button type="button" onclick="setAllEditClasses(false)" class="text-slate-500 hover:underline font-medium">Clear all</button>
            </div>
          </div>
          <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/40 p-2.5 max-h-36 overflow-y-auto space-y-1">
            @foreach($classes as $class)
              <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-violet-50/80 dark:text-slate-200 dark:hover:bg-slate-700/60 transition-colors">
                <input type="checkbox" name="class_ids[]" value="{{ $class->id }}"
                  class="edit-modal-class-checkbox h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500 dark:border-slate-600 dark:bg-slate-800">
                <span class="truncate">{{ $class->name }}</span>
              </label>
            @endforeach
          </div>
          <p class="mt-1 text-[11px] text-slate-400">Assign this report to one or multiple classes.</p>
        </div>

        {{-- Date Range --}}
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">
              Date From <span class="text-red-400">*</span>
            </label>
            <input type="date" id="editDateFrom" name="date_from" required
              class="form-input w-full text-xs sm:text-sm rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">
              Date To <span class="text-red-400">*</span>
            </label>
            <input type="date" id="editDateTo" name="date_to" required
              class="form-input w-full text-xs sm:text-sm rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900">
          </div>
        </div>

        {{-- Uploaded By / User --}}
        <div>
          <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">
            Uploaded By (User)
          </label>
          <select id="editUploadedBy" name="uploaded_by"
            class="form-input w-full text-xs sm:text-sm rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900">
            @foreach($users as $userOption)
              <option value="{{ $userOption->id }}">{{ $userOption->name }}{{ $userOption->team_role ? ' (' . $userOption->team_role . ')' : '' }}</option>
            @endforeach
          </select>
          <p class="mt-1 text-[11px] text-slate-400">The user credited for this analytics upload.</p>
        </div>

        {{-- Canva Link --}}
        <div>
          <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-cyan-500" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/>
            </svg>
            Canva Link (Optional)
          </label>
          <input type="text" id="editCanvaLink" name="canva_link"
            placeholder="https://www.canva.com/design/... or URL"
            class="form-input w-full text-xs sm:text-sm rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 focus:border-cyan-500 focus:ring-cyan-500/20">
        </div>

        {{-- Replace PDF File (Optional) --}}
        <div>
          <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-3.5 h-3.5 text-violet-500">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
            </svg>
            Replace PDF File (Optional)
          </label>
          <input type="file" id="editFileInput" name="file" accept=".pdf"
            class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100 dark:file:bg-violet-900/30 dark:file:text-violet-300 transition-colors">
          <p class="mt-1 text-[11px] text-slate-400">
            Leave empty to keep current file: <span id="editModalCurrentPdfName" class="font-bold text-slate-700 dark:text-slate-300"></span>
          </p>
        </div>

        {{-- Footer Buttons --}}
        <div class="pt-3 border-t border-slate-100 dark:border-slate-700/80 flex flex-col-reverse sm:flex-row gap-2.5">
          <button type="button" onclick="closeEditModal()"
            class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2.5 text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-600 transition-all shadow-sm">
            Cancel
          </button>
          <button type="submit" id="editModalSubmitBtn"
            class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-xs sm:text-sm font-bold text-white shadow-md active:scale-[0.98] transition-all"
            style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 50%,#a855f7 100%);box-shadow:0 4px 14px rgba(124,58,237,.35);">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
  @endif

  {{-- ══════════════════════════════════════════════════════════════════════════
       Beautiful Preview Modal
  ══════════════════════════════════════════════════════════════════════════ --}}
  <div id="previewModal"
    class="fixed inset-0 z-[9999] flex items-center justify-center p-4 sm:p-6"
    style="display:none!important"
    aria-modal="true" role="dialog" aria-labelledby="previewModalTitle">

    {{-- Backdrop --}}
    <div id="previewModalBackdrop"
      onclick="closePreviewModal()"
      class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm"
      style="opacity:0;transition:opacity .2s ease"></div>

    {{-- Panel --}}
    <div id="previewModalPanel"
      class="relative w-full max-w-5xl h-[85vh] flex flex-col rounded-2xl bg-white dark:bg-slate-900 shadow-2xl ring-1 ring-slate-900/10 dark:ring-white/10 overflow-hidden"
      style="transform:scale(.95) translateY(16px);opacity:0;transition:transform .25s cubic-bezier(.34,1.56,.64,1),opacity .2s ease">

      {{-- Header --}}
      <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
        <h3 id="previewModalTitle" class="text-sm font-black text-slate-800 dark:text-slate-100 truncate pr-4">
          Preview Document
        </h3>
        <button type="button" onclick="closePreviewModal()"
          class="flex items-center justify-center w-8 h-8 rounded-full bg-slate-200/50 dark:bg-slate-800 text-slate-500 hover:bg-slate-300 dark:hover:bg-slate-700 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      {{-- Iframe Container --}}
      <div class="flex-1 w-full bg-slate-100 dark:bg-slate-800 overflow-hidden relative">
        <div id="previewModalLoader" class="absolute inset-0 flex flex-col items-center justify-center bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm z-10">
          <div class="w-8 h-8 border-4 border-violet-200 border-t-violet-600 rounded-full animate-spin"></div>
          <p class="mt-3 text-xs font-bold text-slate-500">Loading preview... <span id="previewModalLoaderText">0%</span></p>
        </div>
        <iframe id="previewModalIframe" class="w-full h-full border-0"></iframe>
      </div>
    </div>
  </div>

</div>

<script>
/* ── Download Modal ──────────────────────────────────────────────────── */
let currentDownloadUrl = '';
let currentDownloadFileName = '';

function openDownloadModal(url, fileName) {
  const modal   = document.getElementById('downloadModal');
  const backdrop = document.getElementById('downloadModalBackdrop');
  const panel   = document.getElementById('downloadModalPanel');

  currentDownloadUrl = url;
  currentDownloadFileName = fileName;

  document.getElementById('downloadModalFileName').textContent = '"' + fileName + '"';
  
  // reset state
  document.getElementById('downloadModalProgressContainer').style.display = 'none';
  document.getElementById('downloadModalButtons').style.display = 'flex';
  document.getElementById('downloadModalProgressBar').style.width = '0%';
  document.getElementById('downloadModalProgressText').textContent = '0%';

  modal.style.removeProperty('display');
  // Animate in
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      backdrop.style.opacity = '1';
      panel.style.transform  = 'scale(1) translateY(0)';
      panel.style.opacity    = '1';
    });
  });
  document.addEventListener('keydown', handleDownloadModalEsc);
}

function closeDownloadModal() {
  const modal   = document.getElementById('downloadModal');
  const backdrop = document.getElementById('downloadModalBackdrop');
  const panel   = document.getElementById('downloadModalPanel');

  backdrop.style.opacity = '0';
  panel.style.transform  = 'scale(.93) translateY(16px)';
  panel.style.opacity    = '0';
  setTimeout(() => { modal.style.display = 'none'; }, 220);
  document.removeEventListener('keydown', handleDownloadModalEsc);
}

function handleDownloadModalEsc(e) {
  if (e.key === 'Escape') closeDownloadModal();
}

function performDownload() {
  document.getElementById('downloadModalProgressContainer').style.display = 'block';
  document.getElementById('downloadModalButtons').style.display = 'none';
  
  const xhr = new XMLHttpRequest();
  xhr.open('GET', currentDownloadUrl, true);
  xhr.responseType = 'blob';
  
  xhr.addEventListener('progress', (e) => {
    if (e.lengthComputable) {
      const percentComplete = Math.round((e.loaded / e.total) * 100);
      document.getElementById('downloadModalProgressBar').style.width = percentComplete + '%';
      document.getElementById('downloadModalProgressText').textContent = percentComplete + '%';
    }
  });
  
  xhr.addEventListener('load', () => {
    if (xhr.status === 200) {
      // Trigger native download. Since it was just fetched via XHR, it's cached.
      // This bypasses the "blob:" URL issue in macOS/Electron apps and correctly 
      // triggers the native Save As dialog.
      const a = document.createElement('a');
      a.style.display = 'none';
      a.href = currentDownloadUrl;
      a.download = currentDownloadFileName;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      
      setTimeout(() => {
        closeDownloadModal();
      }, 500);
    } else {
      alert("Download failed. Server returned " + xhr.status);
      closeDownloadModal();
    }
  });
  
  xhr.addEventListener('error', () => {
    alert("Network error during download.");
    closeDownloadModal();
  });
  
  xhr.send();
}

/* ── Delete Modal ────────────────────────────────────────────────────── */
function openDeleteModal(actionUrl, fileName) {
  const modal   = document.getElementById('deleteModal');
  const backdrop = document.getElementById('deleteModalBackdrop');
  const panel   = document.getElementById('deleteModalPanel');

  document.getElementById('deleteModalForm').action = actionUrl;
  document.getElementById('deleteModalFileName').textContent = '"' + fileName + '"';

  modal.style.removeProperty('display');
  // Animate in
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      backdrop.style.opacity = '1';
      panel.style.transform  = 'scale(1) translateY(0)';
      panel.style.opacity    = '1';
    });
  });
  document.addEventListener('keydown', handleModalEsc);
}

function closeDeleteModal() {
  const modal   = document.getElementById('deleteModal');
  const backdrop = document.getElementById('deleteModalBackdrop');
  const panel   = document.getElementById('deleteModalPanel');

  backdrop.style.opacity = '0';
  panel.style.transform  = 'scale(.93) translateY(16px)';
  panel.style.opacity    = '0';
  setTimeout(() => { modal.style.display = 'none'; }, 220);
  document.removeEventListener('keydown', handleModalEsc);
}

function handleModalEsc(e) {
  if (e.key === 'Escape') closeDeleteModal();
}

/* ── Preview Modal ───────────────────────────────────────────────────── */
let currentPreviewXHR = null;

function openPreviewModal(previewUrl, fileName) {
  const modal   = document.getElementById('previewModal');
  const backdrop = document.getElementById('previewModalBackdrop');
  const panel   = document.getElementById('previewModalPanel');
  const iframe  = document.getElementById('previewModalIframe');
  const loader  = document.getElementById('previewModalLoader');
  const loaderText = document.getElementById('previewModalLoaderText');

  document.getElementById('previewModalTitle').textContent = fileName;
  
  // Show loader and clear iframe
  loader.style.display = 'flex';
  iframe.removeAttribute('src');
  iframe.onload = null;
  if (loaderText) loaderText.textContent = '0%';

  modal.style.removeProperty('display');
  // Animate in
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      backdrop.style.opacity = '1';
      panel.style.transform  = 'scale(1) translateY(0)';
      panel.style.opacity    = '1';
    });
  });
  document.addEventListener('keydown', handlePreviewModalEsc);

  if (currentPreviewXHR) currentPreviewXHR.abort();
  currentPreviewXHR = new XMLHttpRequest();
  currentPreviewXHR.open('GET', previewUrl, true);
  currentPreviewXHR.responseType = 'blob';
  
  currentPreviewXHR.addEventListener('progress', (e) => {
    if (e.lengthComputable) {
      const percentComplete = Math.round((e.loaded / e.total) * 100);
      if (loaderText) loaderText.textContent = percentComplete + '%';
    }
  });
  
  currentPreviewXHR.addEventListener('load', () => {
    if (currentPreviewXHR.status === 200) {
      if (loaderText) loaderText.textContent = '100%';
      const blob = currentPreviewXHR.response;
      window.currentPreviewObjectURL = window.URL.createObjectURL(blob);
      iframe.onload = () => {
        loader.style.display = 'none';
      };
      iframe.src = window.currentPreviewObjectURL;
    } else {
      if (loaderText) loaderText.textContent = 'Failed';
    }
  });
  
  currentPreviewXHR.addEventListener('error', () => {
    if (loaderText) loaderText.textContent = 'Error';
  });
  
  currentPreviewXHR.send();
}

function closePreviewModal() {
  const modal   = document.getElementById('previewModal');
  const backdrop = document.getElementById('previewModalBackdrop');
  const panel   = document.getElementById('previewModalPanel');
  const iframe  = document.getElementById('previewModalIframe');

  if (currentPreviewXHR) {
    currentPreviewXHR.abort();
    currentPreviewXHR = null;
  }

  backdrop.style.opacity = '0';
  panel.style.transform  = 'scale(.95) translateY(16px)';
  panel.style.opacity    = '0';
  
  setTimeout(() => { 
    modal.style.display = 'none'; 
    iframe.src = ''; // Clear iframe to stop playback/loading
    if (window.currentPreviewObjectURL) {
      window.URL.revokeObjectURL(window.currentPreviewObjectURL);
      window.currentPreviewObjectURL = null;
    }
  }, 220);
  
  document.removeEventListener('keydown', handlePreviewModalEsc);
}

function handlePreviewModalEsc(e) {
  if (e.key === 'Escape') closePreviewModal();
}

/* ── Canva Modal ─────────────────────────────────────────────────────── */
function openCanvaModal(actionUrl, fileName, currentLink) {
  const modal    = document.getElementById('canvaModal');
  const backdrop = document.getElementById('canvaModalBackdrop');
  const panel    = document.getElementById('canvaModalPanel');
  const form     = document.getElementById('canvaModalForm');
  const input    = document.getElementById('canvaModalInput');
  const errorDiv = document.getElementById('canvaModalError');
  const submitBtn = document.getElementById('canvaModalSubmitBtn');

  form.action = actionUrl;
  document.getElementById('canvaModalFileName').textContent = fileName ? '"' + fileName + '"' : '';
  input.value = currentLink || '';
  errorDiv.style.display = 'none';
  errorDiv.textContent = '';
  submitBtn.disabled = false;
  submitBtn.textContent = 'Save Link';

  modal.style.removeProperty('display');
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      backdrop.style.opacity = '1';
      panel.style.transform  = 'scale(1) translateY(0)';
      panel.style.opacity    = '1';
      input.focus();
    });
  });
  document.addEventListener('keydown', handleCanvaModalEsc);
}

function closeCanvaModal() {
  const modal    = document.getElementById('canvaModal');
  const backdrop = document.getElementById('canvaModalBackdrop');
  const panel    = document.getElementById('canvaModalPanel');

  backdrop.style.opacity = '0';
  panel.style.transform  = 'scale(.93) translateY(16px)';
  panel.style.opacity    = '0';
  setTimeout(() => { modal.style.display = 'none'; }, 220);
  document.removeEventListener('keydown', handleCanvaModalEsc);
}

function handleCanvaModalEsc(e) {
  if (e.key === 'Escape') closeCanvaModal();
}

function handleCanvaModalSubmit(event) {
  event.preventDefault();
  const form = event.target;
  const submitBtn = document.getElementById('canvaModalSubmitBtn');
  const errorDiv = document.getElementById('canvaModalError');

  submitBtn.disabled = true;
  submitBtn.textContent = 'Saving...';
  errorDiv.style.display = 'none';

  fetch(form.action, {
    method: 'POST',
    body: new FormData(form),
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    }
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      if (window.showToast) window.showToast(data.message);
      closeCanvaModal();
      setTimeout(() => window.location.reload(), 400);
    } else {
      errorDiv.textContent = data.message || 'Failed to update Canva link.';
      errorDiv.style.display = 'block';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Save Link';
    }
  })
  .catch(err => {
    errorDiv.textContent = 'Network error. Please try again.';
    errorDiv.style.display = 'block';
    submitBtn.disabled = false;
    submitBtn.textContent = 'Save Link';
  });
}

/* ── Edit Analytics Modal ────────────────────────────────────────────── */
function openEditAnalyticModalFromBtn(btn) {
  let classIds = [];
  try {
    classIds = JSON.parse(btn.dataset.classIds || '[]');
  } catch (e) {
    classIds = [];
  }

  openEditAnalyticModal({
    id: btn.dataset.id,
    updateUrl: btn.dataset.updateUrl,
    fileName: btn.dataset.fileName,
    dateFrom: btn.dataset.dateFrom,
    dateTo: btn.dataset.dateTo,
    classIds: classIds,
    uploadedBy: btn.dataset.uploadedBy,
    canvaLink: btn.dataset.canvaLink || '',
  });
}

function openEditAnalyticModal(data) {
  const modal      = document.getElementById('editAnalyticModal');
  const backdrop   = document.getElementById('editAnalyticModalBackdrop');
  const panel      = document.getElementById('editAnalyticModalPanel');
  const form       = document.getElementById('editAnalyticModalForm');
  const fileNameEl = document.getElementById('editAnalyticModalFileName');
  const curPdfEl   = document.getElementById('editModalCurrentPdfName');
  const dateFromEl = document.getElementById('editDateFrom');
  const dateToEl   = document.getElementById('editDateTo');
  const uploaderEl = document.getElementById('editUploadedBy');
  const canvaEl    = document.getElementById('editCanvaLink');
  const fileInput  = document.getElementById('editFileInput');
  const errorDiv   = document.getElementById('editModalError');
  const submitBtn  = document.getElementById('editModalSubmitBtn');

  if (!modal) return;

  form.action = data.updateUrl;
  if (fileNameEl) fileNameEl.textContent = data.fileName || '';
  if (curPdfEl) curPdfEl.textContent = data.fileName || '';
  if (dateFromEl) dateFromEl.value = data.dateFrom || '';
  if (dateToEl) dateToEl.value = data.dateTo || '';
  if (uploaderEl && data.uploadedBy) uploaderEl.value = data.uploadedBy;
  if (canvaEl) canvaEl.value = data.canvaLink || '';
  if (fileInput) fileInput.value = '';

  // Set class checkboxes
  const classIds = (data.classIds || []).map(id => Number(id));
  document.querySelectorAll('.edit-modal-class-checkbox').forEach(cb => {
    cb.checked = classIds.includes(Number(cb.value));
  });

  if (errorDiv) {
    errorDiv.style.display = 'none';
    errorDiv.textContent = '';
  }
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg> Save Changes`;
  }

  modal.style.removeProperty('display');
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      backdrop.style.opacity = '1';
      panel.style.transform  = 'scale(1) translateY(0)';
      panel.style.opacity    = '1';
    });
  });
  document.addEventListener('keydown', handleEditModalEsc);
}

function closeEditModal() {
  const modal    = document.getElementById('editAnalyticModal');
  const backdrop = document.getElementById('editAnalyticModalBackdrop');
  const panel    = document.getElementById('editAnalyticModalPanel');

  if (!modal) return;

  backdrop.style.opacity = '0';
  panel.style.transform  = 'scale(.93) translateY(16px)';
  panel.style.opacity    = '0';
  setTimeout(() => { modal.style.display = 'none'; }, 220);
  document.removeEventListener('keydown', handleEditModalEsc);
}

function handleEditModalEsc(e) {
  if (e.key === 'Escape') closeEditModal();
}

function setAllEditClasses(checked) {
  document.querySelectorAll('.edit-modal-class-checkbox').forEach(cb => {
    cb.checked = checked;
  });
}

function handleEditModalSubmit(event) {
  event.preventDefault();
  const form = event.target;
  const submitBtn = document.getElementById('editModalSubmitBtn');
  const errorDiv = document.getElementById('editModalError');

  // Client-side validation: at least 1 class selected
  const checkedClasses = form.querySelectorAll('input[name="class_ids[]"]:checked');
  if (checkedClasses.length === 0) {
    errorDiv.textContent = 'Please select at least one class.';
    errorDiv.style.display = 'block';
    return;
  }

  const dateFrom = form.querySelector('input[name="date_from"]')?.value;
  const dateTo = form.querySelector('input[name="date_to"]')?.value;
  if (!dateFrom || !dateTo) {
    errorDiv.textContent = 'Please select both Date From and Date To.';
    errorDiv.style.display = 'block';
    return;
  }
  if (dateFrom > dateTo) {
    errorDiv.textContent = 'Date To must be equal to or after Date From.';
    errorDiv.style.display = 'block';
    return;
  }

  submitBtn.disabled = true;
  submitBtn.innerHTML = '<span class="inline-block animate-spin mr-2">⟳</span> Saving...';
  errorDiv.style.display = 'none';

  const formData = new FormData(form);

  fetch(form.action, {
    method: 'POST',
    body: formData,
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    }
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      if (window.showToast) window.showToast(data.message);
      closeEditModal();
      setTimeout(() => window.location.reload(), 400);
    } else {
      let errorMsg = data.message || 'Failed to update analytics.';
      if (data.errors) {
        errorMsg = Object.values(data.errors).flat().join(' ');
      }
      errorDiv.textContent = errorMsg;
      errorDiv.style.display = 'block';
      submitBtn.disabled = false;
      submitBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg> Save Changes`;
    }
  })
  .catch(err => {
    errorDiv.textContent = 'Network error. Please try again.';
    errorDiv.style.display = 'block';
    submitBtn.disabled = false;
    submitBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg> Save Changes`;
  });
}

function analyticsUploadForm() {
  return {
    uploading: false,
    progress: 0,
    error: '',

    submit(event) {
      if (this.uploading) return;

      const form = event.currentTarget;
      const file = form.querySelector('input[type="file"]')?.files?.[0];
      const canvaLink = form.querySelector('input[name="canva_link"]')?.value?.trim();

      if (!canvaLink) {
        this.error = 'Please enter the Canva link. The Canva link is required.';
        return;
      }
      if (!file) {
        this.error = 'Please choose an analytics PDF.';
        return;
      }
      if (file.size > 100 * 1024 * 1024) {
        this.error = 'The PDF is larger than the 100 MB limit.';
        return;
      }

      this.uploading = true;
      this.progress = 0;
      this.error = '';

      const xhr = new XMLHttpRequest();
      xhr.open('POST', form.action, true);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

      xhr.upload.addEventListener('progress', (uploadEvent) => {
        if (uploadEvent.lengthComputable) {
          this.progress = Math.min(99, Math.round((uploadEvent.loaded / uploadEvent.total) * 100));
        }
      });

      xhr.addEventListener('load', () => {
        let response = {};
        try { response = JSON.parse(xhr.responseText || '{}'); } catch (_) {}

        if (xhr.status >= 200 && xhr.status < 300) {
          this.progress = 100;
          if (window.showToast) window.showToast(response.message || 'Analytics saved successfully.');
          setTimeout(() => window.location.reload(), 700);
          return;
        }

        const validationMessage = response.errors
          ? Object.values(response.errors).flat().join(' ')
          : '';
        this.error = validationMessage || response.message ||
          (xhr.status === 413 ? 'The upload is too large for the server. Maximum size is 100 MB.' : 'Upload failed. Please try again.');
        this.uploading = false;
        this.progress = 0;
      });

      xhr.addEventListener('error', () => {
        this.error = 'The upload connection failed. Please check the server and try again.';
        this.uploading = false;
        this.progress = 0;
      });

      xhr.send(new FormData(form));
    }
  };
}
</script>
@endsection
