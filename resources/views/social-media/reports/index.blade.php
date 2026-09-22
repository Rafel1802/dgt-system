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

    <div class="w-full max-w-4xl bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl shadow-slate-200/50 dark:shadow-none border border-slate-200 dark:border-slate-700 p-8 md:p-12"
         x-data="{ 
            selected: [],
            allIds: [{{ $classes->pluck('id')->implode(',') }}],
            dateFrom: '{{ now()->startOfMonth()->toDateString() }}',
            dateTo: '{{ now()->endOfMonth()->toDateString() }}',
            toggle(id) {
                if (this.selected.includes(id)) {
                    this.selected = this.selected.filter(i => i !== id);
                } else {
                    this.selected.push(id);
                }
            },
            selectAll() {
                this.selected = [...this.allIds];
            },
            deselectAll() {
                this.selected = [];
            },
            setDates(from, to) {
                this.dateFrom = from;
                this.dateTo = to;
                const fromEl = document.getElementById('report_date_from');
                const toEl = document.getElementById('report_date_to');
                if (fromEl) {
                    fromEl.value = from;
                    if (fromEl._flatpickr) fromEl._flatpickr.setDate(from);
                }
                if (toEl) {
                    toEl.value = to;
                    if (toEl._flatpickr) toEl._flatpickr.setDate(to);
                }
            }
         }">

        {{-- Alerts for immediate error/success feedback --}}
        @if(session('error'))
            <div class="p-4 mb-6 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 flex items-center gap-3 text-sm font-bold animate-fade-in shadow-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if(session('success'))
            <div class="p-4 mb-6 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 flex items-center gap-3 text-sm font-bold animate-fade-in shadow-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(isset($latestAnalytic) && $latestAnalytic)
            <div class="mb-6 p-4 rounded-2xl bg-indigo-50/70 dark:bg-sky-950/40 border border-indigo-200/80 dark:border-sky-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2.5 text-slate-700 dark:text-sky-200">
                    <span class="flex h-7 w-7 items-center justify-center rounded-xl bg-indigo-500/15 dark:bg-sky-500/20 text-indigo-600 dark:text-sky-400 font-black">📅</span>
                    <div>
                        <span class="font-bold">Latest Uploaded Report:</span>
                        <span class="font-semibold text-slate-600 dark:text-sky-300 ml-1">{{ $latestAnalytic->dateRangeLabel() }}</span>
                        @if($latestAnalytic->classes->isNotEmpty())
                            <span class="text-[11px] opacity-75 ml-1">({{ $latestAnalytic->classes->pluck('name')->join(', ') }})</span>
                        @endif
                    </div>
                </div>
                <button type="button" 
                        @click="setDates('{{ $latestAnalytic->date_from->format('Y-m-d') }}', '{{ $latestAnalytic->date_to->format('Y-m-d') }}')"
                        class="btn btn-secondary px-3 py-1.5 text-xs font-bold rounded-xl flex items-center justify-center gap-1.5 whitespace-nowrap hover:scale-102 transition-transform">
                    <span>Use These Dates</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </div>
        @endif

        <form action="{{ route('social-media.reports.export.zip') }}" method="POST" data-turbo="false" class="space-y-8" id="social-reports-form">
            @csrf
            
            <input type="hidden" name="export_type" id="export_type_input" value="zip">
            <input type="hidden" name="include_analytics" value="1">

            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-2xl p-6 border border-slate-100 dark:border-slate-800">
                <div class="flex items-center justify-between mb-4">
                    <label class="text-base font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                        Classes (Optional)
                    </label>
                    
                    <div class="flex items-center gap-2">
                        <button type="button" @click="selectAll()" class="text-xs font-semibold text-indigo-600 dark:text-sky-400 hover:text-indigo-800 dark:hover:text-sky-300 bg-indigo-50 dark:bg-sky-950/60 border border-indigo-100 dark:border-sky-500/30 px-3 py-1.5 rounded-lg transition-colors">Select All</button>
                        <button type="button" @click="deselectAll()" class="text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-300 bg-slate-200 dark:bg-slate-700 px-3 py-1.5 rounded-lg transition-colors">Clear</button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    @foreach($classes as $class)
                        <button type="button" 
                            @click="toggle({{ $class->id }})"
                            :class="selected.includes({{ $class->id }}) 
                                ? 'bg-indigo-500 text-white border-indigo-600 shadow-md shadow-indigo-500/20 dark:bg-sky-600 dark:border-sky-400' 
                                : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:border-indigo-300 hover:bg-indigo-50 dark:hover:bg-slate-700'"
                            class="px-4 py-2.5 rounded-xl border font-medium text-sm transition-all duration-200 flex items-center gap-2">
                            
                            <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-colors"
                                 :class="selected.includes({{ $class->id }}) ? 'border-white bg-white' : 'border-slate-300 dark:border-slate-600'">
                                <svg x-show="selected.includes({{ $class->id }})" class="w-2.5 h-2.5 text-indigo-500 dark:text-sky-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </div>
                            {{ $class->name }}
                        </button>
                    @endforeach
                </div>
                
                <!-- Hidden inputs for form submission -->
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="class_id[]" :value="id">
                </template>

                <p class="text-sm text-slate-500 dark:text-slate-400 mt-4 flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Click to toggle classes. Leave empty to export all classes.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-50 dark:bg-slate-900/50 rounded-2xl p-6 border border-slate-100 dark:border-slate-800">
                <div>
                    <label class="block text-base font-bold text-slate-800 dark:text-slate-200 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        Date From
                    </label>
                    <input type="date" id="report_date_from" name="date_from" x-model="dateFrom" class="form-input w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                <div>
                    <label class="block text-base font-bold text-slate-800 dark:text-slate-200 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        Date To
                    </label>
                    <input type="date" id="report_date_to" name="date_to" x-model="dateTo" class="form-input w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
            </div>

            <div class="pt-6 flex flex-col sm:flex-row items-center justify-center gap-4 border-t border-slate-100 dark:border-slate-700/50 mt-8">
                <button type="submit" onclick="document.getElementById('export_type_input').value = 'single'" class="btn btn-secondary w-full sm:w-auto px-8 py-3 text-base rounded-xl font-bold flex items-center justify-center gap-2 transition-all hover:scale-105 shadow-sm">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                    Export as Single PDF
                </button>
                <button type="submit" onclick="document.getElementById('export_type_input').value = 'zip'" class="btn btn-primary w-full sm:w-auto px-8 py-3 text-base rounded-xl font-bold flex items-center justify-center gap-2 transition-all hover:scale-105 shadow-lg shadow-indigo-500/30 dark:shadow-sky-500/20">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                    Export as ZIP
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
