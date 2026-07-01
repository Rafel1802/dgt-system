@extends('layouts.app')

@section('title', 'Analytics Import')
@section('page_title', 'Analytics Import')
@section('back_url', route('social-media.dashboard'))

@section('content')
@php
  $canUpload = auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'social_qc']);
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
            Upload Analytics PDF
          </h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">PDF only · Max 100 MB · Replaces existing files for the same classes and week</p>
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
            <p class="mt-1.5 text-xs text-slate-400">Select every class covered by this PDF.</p>
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
              class="flex flex-col items-center justify-center gap-3 w-full h-36 border-2 border-dashed rounded-xl cursor-pointer transition-all group relative">
              <input type="file" x-ref="fileInput" name="file" accept=".pdf" required class="hidden"
                @change="fileName = $event.target.files[0]?.name || ''">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                :class="isDragging ? 'text-violet-500' : 'text-slate-300 group-hover:text-violet-500'"
                class="w-8 h-8 transition-colors pointer-events-none">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
              </svg>
              <div class="text-center pointer-events-none">
                <p class="text-sm font-semibold transition-colors" :class="isDragging ? 'text-violet-600' : 'text-slate-600 dark:text-slate-300 group-hover:text-violet-600'" x-text="isDragging ? 'Drop PDF here' : (fileName || 'Click or drag PDF file here')"></p>
                <p class="text-xs text-slate-400 mt-0.5" x-show="!fileName && !isDragging">PDF only, max 100 MB</p>
              </div>
            </label>
            @error('file')
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
              <span x-text="progress === 100 ? 'Upload complete — refreshing…' : 'Uploading PDF…'"></span>
              <span x-text="progress + '%'"></span>
            </div>
            <div class="h-3 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
              <div class="h-full rounded-full bg-blue-600 transition-[width] duration-200 ease-out"
                :style="'width: ' + progress + '%'" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
          </div>
      </div>
    </div>
    @endif

    {{-- ── Uploaded Files Table ─────────────────────────────────────────────── --}}
    <div class="space-y-5">
      {{-- Filters --}}
      <form method="GET" action="{{ route('social-media.analytics.index') }}" class="card p-4 border border-slate-200 dark:border-slate-700">
        <div class="flex flex-wrap gap-3 items-end">
          <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Filter by Class</label>
            <select name="class_id" class="form-select w-full text-sm rounded-xl">
              <option value="">All Classes</option>
              @foreach($classes as $class)
                <option value="{{ $class->id }}" {{ $classId == $class->id ? 'selected' : '' }}>{{ $class->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="flex-1 min-w-[140px]">
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Date From</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-input w-full text-sm rounded-xl">
          </div>
          <div class="flex-1 min-w-[140px]">
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Date To</label>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-input w-full text-sm rounded-xl">
          </div>
          <div class="flex gap-2 w-full sm:w-auto">
            <button type="submit" class="btn btn-primary text-sm flex-1 sm:flex-none px-4 py-2">Filter</button>
            <a href="{{ route('social-media.analytics.index') }}" class="btn btn-secondary text-sm flex-1 sm:flex-none px-4 py-2 text-center">Clear</a>
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

              {{-- PDF Icon --}}
              <div class="flex-shrink-0 w-12 h-14 rounded-xl flex flex-col items-center justify-center gap-0.5 shadow-sm relative"
                style="background: linear-gradient(145deg, #ef4444 0%, #dc2626 100%)">
                <span class="text-[9px] font-black text-white/80 tracking-widest mt-1">PDF</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="white" class="w-5 h-5 opacity-90">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                @if(!$analytic->fileExists())
                  <div class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-amber-400 rounded-full flex items-center justify-center">
                    <span class="text-white text-[8px] font-black">!</span>
                  </div>
                @endif
              </div>

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

                  @if(!$analytic->fileExists())
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
          <p class="mt-3 text-xs font-bold text-slate-500 animate-pulse">Loading preview...</p>
        </div>
        <iframe id="previewModalIframe" src="" class="w-full h-full border-0" onload="document.getElementById('previewModalLoader').style.display='none'"></iframe>
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
      const blob = xhr.response;
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.style.display = 'none';
      a.href = url;
      a.download = currentDownloadFileName;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
      
      closeDownloadModal();
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
function openPreviewModal(previewUrl, fileName) {
  const modal   = document.getElementById('previewModal');
  const backdrop = document.getElementById('previewModalBackdrop');
  const panel   = document.getElementById('previewModalPanel');
  const iframe  = document.getElementById('previewModalIframe');
  const loader  = document.getElementById('previewModalLoader');

  document.getElementById('previewModalTitle').textContent = fileName;
  
  // Show loader and set iframe src
  loader.style.display = 'flex';
  iframe.src = previewUrl;

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
}

function closePreviewModal() {
  const modal   = document.getElementById('previewModal');
  const backdrop = document.getElementById('previewModalBackdrop');
  const panel   = document.getElementById('previewModalPanel');
  const iframe  = document.getElementById('previewModalIframe');

  backdrop.style.opacity = '0';
  panel.style.transform  = 'scale(.95) translateY(16px)';
  panel.style.opacity    = '0';
  
  setTimeout(() => { 
    modal.style.display = 'none'; 
    iframe.src = ''; // Clear iframe to stop playback/loading
  }, 220);
  
  document.removeEventListener('keydown', handlePreviewModalEsc);
}

function handlePreviewModalEsc(e) {
  if (e.key === 'Escape') closePreviewModal();
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
          if (window.showToast) window.showToast(response.message || 'Analytics uploaded successfully.');
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
