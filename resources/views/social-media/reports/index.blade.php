@extends('layouts.app')

@section('title', 'Social Media Report')
@section('back_url', route('social-media.dashboard'))

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Social Media Report</h1>
        <p class="page-subtitle">Filter, analyze and export social media posting data</p>
    </div>
    <div class="flex gap-3">
        <a href="{{ route('social-media.dashboard') }}" class="btn btn-secondary text-sm">← Back to Dashboard</a>
    </div>
</div>

{{-- Filters Form --}}
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" action="{{ route('social-media.reports.index') }}" id="report-filter-form">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                <div>
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-input" id="rep-date-from">
                </div>
                <div>
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="form-input" id="rep-date-to">
                </div>
                <div>
                    <label class="form-label">Class</label>
                    <select name="class_id" class="form-select" id="rep-class">
                        <option value="">All Classes</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" {{ $classId == $class->id ? 'selected' : '' }}>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Social Media</label>
                    <select name="item_id" class="form-select" id="rep-item">
                        <option value="">All Platforms</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" {{ $itemId == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">QC Status</label>
                    <select name="qc_status" class="form-select" id="rep-qc">
                        <option value="">All</option>
                        <option value="checked" {{ $qcStatus === 'checked' ? 'selected' : '' }}>Checked</option>
                        <option value="pending" {{ $qcStatus === 'pending' ? 'selected' : '' }}>Pending</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Post Status</label>
                    <select name="post_status" class="form-select" id="rep-post-status">
                        <option value="">All</option>
                        <option value="posted" {{ $postStatus === 'posted' ? 'selected' : '' }}>Posted</option>
                        <option value="pending" {{ $postStatus === 'pending' ? 'selected' : '' }}>Pending</option>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-3 mt-4">
                <button type="submit" class="btn btn-primary text-sm" id="btn-apply-report">Apply</button>
                <a href="{{ route('social-media.reports.index') }}" class="btn btn-secondary text-sm">Reset</a>
                <a href="{{ route('social-media.reports.export.csv', request()->all()) }}" class="btn btn-secondary text-sm ml-auto" id="btn-export-csv">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Export CSV
                </a>
                <a href="{{ route('social-media.reports.export.pdf', request()->all()) }}" class="btn btn-primary text-sm" id="btn-export-pdf">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                    Export PDF
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
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
@endsection
