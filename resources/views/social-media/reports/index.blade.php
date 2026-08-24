@extends('layouts.app')

@section('title', 'Social Media Reports')
@section('back_url', route('social-media.dashboard'))

@section('content')
<div class="page-header mb-8">
    <h1 class="page-title text-3xl font-black text-slate-800 dark:text-white flex items-center gap-3">
        <span class="p-2 bg-indigo-500 text-white rounded-xl shadow-lg w-12 h-12 flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        </span>
        Export Analytics Reports
    </h1>
    <p class="page-subtitle mt-2">Export uploaded analytics PDFs by date or month.</p>
</div>

<div class="max-w-2xl bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 md:p-8">
    <form action="{{ route('social-media.reports.export.zip') }}" method="POST" class="space-y-6">
        @csrf
        
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Class (Optional)</label>
            <select name="class_id" class="form-select w-full rounded-xl">
                <option value="">All Classes</option>
                @foreach($classes as $class)
                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Date From</label>
                <input type="date" name="date_from" value="{{ now()->startOfMonth()->toDateString() }}" class="form-input w-full rounded-xl" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Date To</label>
                <input type="date" name="date_to" value="{{ now()->endOfMonth()->toDateString() }}" class="form-input w-full rounded-xl" required>
            </div>
        </div>
        
        <input type="hidden" name="include_analytics" value="1">

        <div class="pt-4 flex items-center justify-end gap-3">
            <button type="submit" name="export_type" value="single" class="btn btn-secondary px-6">Export as Single PDF</button>
            <button type="submit" name="export_type" value="zip" class="btn btn-primary px-6">Export as ZIP</button>
        </div>
    </form>
</div>
@endsection
