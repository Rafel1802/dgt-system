@extends('layouts.app')

@section('title', 'Social Media Report')
@section('back_url', route('social-media.dashboard'))

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Social Media Report</h1>
        <p class="page-subtitle">Filter, analyze and export social media posting data</p>
    </div>
</div>

{{-- Filters Form --}}
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" action="{{ route('social-media.reports.index') }}" id="report-filter-form">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label class="form-label text-slate-500 font-semibold text-xs uppercase tracking-wider">Date From</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-input rounded-xl" id="rep-date-from">
                </div>
                <div>
                    <label class="form-label text-slate-500 font-semibold text-xs uppercase tracking-wider">Date To</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="form-input rounded-xl" id="rep-date-to">
                </div>
                <div>
                    <label class="form-label text-slate-500 font-semibold text-xs uppercase tracking-wider">Class</label>
                    <select name="class_id" class="form-select rounded-xl" id="rep-class">
                        <option value="">All Classes</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" {{ $classId == $class->id ? 'selected' : '' }}>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label text-slate-500 font-semibold text-xs uppercase tracking-wider">QC Status</label>
                    <select name="qc_status" class="form-select rounded-xl" id="rep-qc">
                        <option value="">All Statuses</option>
                        <option value="checked" {{ $qcStatus === 'checked' ? 'selected' : '' }}>Checked</option>
                        <option value="pending" {{ $qcStatus === 'pending' ? 'selected' : '' }}>Pending</option>
                    </select>
                </div>
                <div>
                    <label class="form-label text-slate-500 font-semibold text-xs uppercase tracking-wider">Post Status</label>
                    <select name="post_status" class="form-select rounded-xl" id="rep-post-status">
                        <option value="">All Statuses</option>
                        <option value="posted" {{ $postStatus === 'posted' ? 'selected' : '' }}>Posted</option>
                        <option value="pending" {{ $postStatus === 'pending' ? 'selected' : '' }}>Pending</option>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 sm:gap-3 mt-4 w-full">
                <button type="submit" class="btn btn-primary text-sm flex-1 sm:flex-none justify-center" id="btn-apply-report">Apply</button>
                <a href="{{ route('social-media.reports.index') }}" class="btn btn-secondary text-sm flex-1 sm:flex-none justify-center">Reset</a>

                {{-- ── Export Button ── opens modal --}}
                <button type="button" id="btn-open-export"
                    class="btn btn-secondary text-sm flex-1 sm:flex-none justify-center flex items-center gap-2"
                    onclick="document.getElementById('export-modal').classList.remove('hidden')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                    Export
                    @if($hasAnalytics)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-violet-500 text-white">+Analytics</span>
                    @endif
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Export Modal ──────────────────────────────────────────────────────── --}}
<div id="export-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" x-data>
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="document.getElementById('export-modal').classList.add('hidden')"></div>

    {{-- Modal Panel --}}
    <div class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-2xl overflow-hidden">
        {{-- Header --}}
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between"
             style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Export Report</h3>
                    <p class="text-xs text-purple-200">Choose what to include in your download</p>
                </div>
            </div>
            <button onclick="document.getElementById('export-modal').classList.add('hidden')"
                class="w-7 h-7 rounded-lg bg-white/20 hover:bg-white/30 flex items-center justify-center transition-colors">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Export Form --}}
        <form method="POST" action="{{ route('social-media.reports.export.zip') }}" id="export-form" data-turbo="false">
            @csrf
            {{-- Carry current filters --}}
            <input type="hidden" name="date_from"   value="{{ $dateFrom }}">
            <input type="hidden" name="date_to"     value="{{ $dateTo }}">
            <input type="hidden" name="class_id"    value="{{ $classId }}">
            <input type="hidden" name="user_id"     value="{{ $userId }}">
            <input type="hidden" name="qc_status"   value="{{ $qcStatus }}">
            <input type="hidden" name="post_status" value="{{ $postStatus }}">

            <div class="p-6 space-y-3">
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                    Tick the formats you want. Multiple selections are packaged into a single ZIP file.
                </p>

                {{-- CSV --}}
                <label class="flex items-start gap-4 p-4 rounded-xl border-2 border-slate-100 dark:border-slate-700 hover:border-violet-300 dark:hover:border-violet-600 hover:bg-violet-50/40 dark:hover:bg-violet-900/10 cursor-pointer transition-all group">
                    <input type="checkbox" name="include_csv" value="1" id="chk-csv"
                        class="mt-0.5 w-5 h-5 rounded text-violet-600 border-slate-300 focus:ring-violet-500 cursor-pointer flex-shrink-0">
                    <div>
                        <div class="font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-[11px] font-bold uppercase">CSV</span>
                            Spreadsheet Report
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">All posts with summary — opens in Excel / Google Sheets</div>
                    </div>
                </label>

                {{-- PDF --}}
                <label class="flex items-start gap-4 p-4 rounded-xl border-2 border-slate-100 dark:border-slate-700 hover:border-violet-300 dark:hover:border-violet-600 hover:bg-violet-50/40 dark:hover:bg-violet-900/10 cursor-pointer transition-all group">
                    <input type="checkbox" name="include_pdf" value="1" id="chk-pdf"
                        class="mt-0.5 w-5 h-5 rounded text-violet-600 border-slate-300 focus:ring-violet-500 cursor-pointer flex-shrink-0">
                    <div>
                        <div class="font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-[11px] font-bold uppercase">PDF</span>
                            Formatted Report
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Printable A4 landscape report with header and summary</div>
                    </div>
                </label>

                {{-- Analytics --}}
                <label class="flex items-start gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all group
                    {{ $hasAnalytics ? 'border-slate-100 dark:border-slate-700 hover:border-violet-300 dark:hover:border-violet-600 hover:bg-violet-50/40 dark:hover:bg-violet-900/10' : 'border-slate-100 dark:border-slate-700 opacity-50 cursor-not-allowed' }}">
                    <input type="checkbox" name="include_analytics" value="1" id="chk-analytics"
                        {{ !$hasAnalytics ? 'disabled' : '' }}
                        class="mt-0.5 w-5 h-5 rounded text-violet-600 border-slate-300 focus:ring-violet-500 flex-shrink-0
                        {{ !$hasAnalytics ? 'cursor-not-allowed' : 'cursor-pointer' }}">
                    <div class="flex-1">
                        <div class="font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 text-[11px] font-bold uppercase">PDF</span>
                            Analytics File
                            @if(!$hasAnalytics)
                                <span class="text-xs text-slate-400 font-normal">(none uploaded yet)</span>
                            @else
                                <span class="text-xs font-normal text-violet-600 dark:text-violet-400">{{ $availableAnalytics->count() }} file{{ $availableAnalytics->count() !== 1 ? 's' : '' }} available</span>
                            @endif
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            @if($hasAnalytics)
                                Weekly analytics PDFs imported by admin — latest per class included
                            @else
                                No analytics files have been imported for this filter yet
                            @endif
                        </div>

                    </div>
                </label>
            </div>

            <div class="px-6 pb-6 flex gap-3">
                <button type="button"
                    onclick="document.getElementById('export-modal').classList.add('hidden')"
                    class="flex-1 btn btn-secondary text-sm py-2.5">
                    Cancel
                </button>
                <button type="submit" id="btn-export-submit"
                    class="flex-1 btn btn-primary text-sm py-2.5 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                    Download
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
    @php
    $statCards = [
        ['label' => 'Total Tasks',  'value' => $summary['total'],     'color' => 'text-slate-700',   'bg' => 'bg-white'],
        ['label' => 'Posted',       'value' => $summary['completed'], 'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50'],
        ['label' => 'Pending Post', 'value' => $summary['pending'],   'color' => 'text-amber-600',   'bg' => 'bg-amber-50'],
        ['label' => 'QC Checked',   'value' => $summary['checked'],   'color' => 'text-blue-600',    'bg' => 'bg-blue-50'],
        ['label' => 'QC Pending',   'value' => $summary['qcPending'], 'color' => 'text-orange-600',  'bg' => 'bg-orange-50'],
    ];
    @endphp
    @foreach($statCards as $card)
    <div class="card {{ $card['bg'] }} border p-4 text-center">
        <div class="text-2xl font-bold {{ $card['color'] }}">{{ $card['value'] }}</div>
        <div class="text-xs text-slate-500 mt-1">{{ $card['label'] }}</div>
    </div>
    @endforeach
</div>

{{-- Report Table --}}
<div class="card">
    <div class="card-body p-0">
        @if($posts->isEmpty())
            <div class="text-center py-16 text-slate-400">
                <p>No data found for the selected filters.</p>
            </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-slate-600 dark:text-slate-400">
                <thead class="text-xs text-slate-700 uppercase bg-slate-50 border-b border-slate-200 dark:bg-slate-700 dark:text-slate-400 dark:border-slate-600">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Class</th>
                        <th class="px-4 py-3">Social Media</th>
                        <th class="px-4 py-3 text-center">Post Status</th>
                        <th class="px-4 py-3">Post Link</th>
                        <th class="px-4 py-3">Submitted By</th>
                        <th class="px-4 py-3">Submitted At</th>
                        <th class="px-4 py-3 text-center">QC Status</th>
                        <th class="px-4 py-3">Checked By</th>
                        <th class="px-4 py-3">Checked At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($posts as $post)
                    <tr class="bg-white border-b hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:hover:bg-slate-600 transition-colors">
                        <td class="px-4 py-3 whitespace-nowrap font-medium text-slate-900 dark:text-white">{{ $post->post_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $post->socialMediaClass->name ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $post->socialMediaItem->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                {{ $post->is_completed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $post->is_completed ? 'Posted' : 'Pending' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 max-w-[160px]">
                            @if($post->post_url)
                                <a href="{{ $post->post_url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline text-xs truncate block">{{ $post->post_url }}</a>
                            @else
                                <span class="text-slate-400 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400 whitespace-nowrap">{{ $post->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ $post->completed_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                @if($post->is_checked) bg-blue-100 text-blue-700
                                @elseif($post->is_completed) bg-orange-100 text-orange-700
                                @else bg-slate-100 text-slate-500
                                @endif">
                                {{ $post->qc_status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400 whitespace-nowrap">{{ $post->checker?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ $post->checked_at?->format('d M Y H:i') ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

<script>
// Validate at least one checkbox is ticked before submitting
document.getElementById('export-form').addEventListener('submit', function(e) {
    const csv       = document.getElementById('chk-csv').checked;
    const pdf       = document.getElementById('chk-pdf').checked;
    const analytics = document.getElementById('chk-analytics').checked;
    if (!csv && !pdf && !analytics) {
        e.preventDefault();
        alert('Please select at least one export format.');
    }
});

// Close modal on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('export-modal').classList.add('hidden');
    }
});
</script>
@endsection
