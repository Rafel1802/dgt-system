@extends('layouts.app')

@section('title', 'Social Media Reports')
@section('back_url', route('social-media.dashboard'))

@section('content')
<div class="flex flex-col items-center justify-center min-h-[80vh] py-10">
    <div class="page-header mb-10 text-center flex flex-col items-center">
        <span class="p-4 bg-indigo-500 text-white rounded-3xl shadow-xl shadow-indigo-500/30 mb-6">
            <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        </span>
        <h1 class="page-title text-4xl font-black text-slate-800 dark:text-white tracking-tight">
            Export Analytics Reports
        </h1>
        <p class="page-subtitle mt-3 text-lg text-slate-500 dark:text-slate-400 max-w-lg mx-auto">Export uploaded analytics PDFs across multiple classes by date or month.</p>
    </div>

    <div class="w-full max-w-4xl bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl shadow-slate-200/50 dark:shadow-none border border-slate-200 dark:border-slate-700 p-8 md:p-12">
        <form action="{{ route('social-media.reports.export.zip') }}" method="POST" class="space-y-8">
            @csrf
            
            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-2xl p-6 border border-slate-100 dark:border-slate-800">
                <label class="block text-base font-bold text-slate-800 dark:text-slate-200 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    Classes (Optional)
                </label>
                <select name="class_id[]" multiple class="form-select w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 shadow-sm min-h-[140px] focus:ring-indigo-500 focus:border-indigo-500">
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}" class="py-2 px-3 my-1 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/30">{{ $class->name }}</option>
                    @endforeach
                </select>
                <p class="text-sm text-slate-500 mt-3 flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Hold <kbd class="px-2 py-1 bg-slate-200 dark:bg-slate-700 rounded text-xs font-mono text-slate-800 dark:text-slate-300 mx-1">Ctrl</kbd> or <kbd class="px-2 py-1 bg-slate-200 dark:bg-slate-700 rounded text-xs font-mono text-slate-800 dark:text-slate-300 mx-1">Cmd</kbd> to select multiple classes.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-50 dark:bg-slate-900/50 rounded-2xl p-6 border border-slate-100 dark:border-slate-800">
                <div>
                    <label class="block text-base font-bold text-slate-800 dark:text-slate-200 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        Date From
                    </label>
                    <input type="date" name="date_from" value="{{ now()->startOfMonth()->toDateString() }}" class="form-input w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label class="block text-base font-bold text-slate-800 dark:text-slate-200 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        Date To
                    </label>
                    <input type="date" name="date_to" value="{{ now()->endOfMonth()->toDateString() }}" class="form-input w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
            </div>
            
            <input type="hidden" name="include_analytics" value="1">

            <div class="pt-6 flex flex-col sm:flex-row items-center justify-center gap-4 border-t border-slate-100 dark:border-slate-700/50 mt-8">
                <button type="submit" name="export_type" value="single" class="btn btn-secondary w-full sm:w-auto px-8 py-3 text-base rounded-xl font-bold flex items-center justify-center gap-2 transition-all hover:scale-105">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                    Export as Single PDF
                </button>
                <button type="submit" name="export_type" value="zip" class="btn btn-primary w-full sm:w-auto px-8 py-3 text-base rounded-xl font-bold flex items-center justify-center gap-2 transition-all hover:scale-105 shadow-lg shadow-indigo-500/30">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                    Export as ZIP
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
