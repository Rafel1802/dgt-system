@extends('layouts.app')
@section('title', 'Social Media Analytics Report')

@section('content')
<div class="animate-fade-in space-y-6 pb-10">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-white">Social Media Analytics Report</h1>
            <p class="text-sm text-slate-500 mt-1">
                Showing analytics files from <strong>{{ $dateFrom }}</strong> to <strong>{{ $dateTo }}</strong>
                @if($classId)
                    &nbsp;|&nbsp; Class: <strong>{{ $classes->firstWhere('id', $classId)?->name ?? '—' }}</strong>
                @else
                    &nbsp;|&nbsp; All Classes
                @endif
                &nbsp;|&nbsp; <strong>{{ $analytics->count() }}</strong> file(s) found
            </p>
        </div>
        <a href="{{ route('boards.reports.personal') }}" class="btn btn-secondary flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
            Back to Personal Report
        </a>
    </div>

    @if($analytics->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-16 text-center">
            <svg class="w-14 h-14 text-slate-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625z"/>
            </svg>
            <p class="text-lg font-bold text-slate-500 dark:text-slate-400">No analytics files found</p>
            <p class="text-sm text-slate-400 mt-1">No analytics were uploaded for the selected date range and class.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($analytics as $analytic)
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                {{-- Header stripe --}}
                <div class="h-1.5 w-full" style="background: linear-gradient(90deg, #4f46e5, #818cf8);"></div>
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 mb-1">Analytics PDF</p>
                            <h3 class="font-bold text-slate-800 dark:text-white text-sm leading-snug truncate" title="{{ $analytic->original_name }}">{{ $analytic->original_name }}</h3>
                        </div>
                        <div class="flex-shrink-0 w-9 h-9 bg-red-50 dark:bg-red-900/20 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Classes --}}
                    <div class="flex flex-wrap gap-1.5 mb-3">
                        @foreach($analytic->classes as $class)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">
                                {{ $class->name }}
                            </span>
                        @endforeach
                    </div>

                    {{-- Date Range --}}
                    <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 mb-4">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/></svg>
                        {{ $analytic->date_from->format('d M Y') }} — {{ $analytic->date_to->format('d M Y') }}
                    </div>

                    {{-- Actions --}}
                    <div class="flex gap-2">
                        <a href="{{ route('social-media.analytics.preview', $analytic) }}" target="_blank"
                           class="flex-1 text-center btn btn-secondary text-xs py-1.5 rounded-lg">
                            👁 Preview
                        </a>
                        <a href="{{ route('social-media.analytics.download', $analytic) }}"
                           class="flex-1 text-center btn btn-primary text-xs py-1.5 rounded-lg">
                            ⬇ Download
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
