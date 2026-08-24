@extends('layouts.app')
@section('title', 'Follow-Up Report Preview')

@section('content')
<div class="w-full h-[calc(100vh-100px)] flex flex-col p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Follow-Up Report Preview</h1>
            <p class="text-sm text-slate-500 mt-1">
                Member: <strong>{{ $user ? $user->name : 'All Members' }}</strong> &nbsp;|&nbsp;
                Date: <strong>{{ $dateFrom }}</strong> to <strong>{{ $dateTo }}</strong> &nbsp;|&nbsp;
                Total Records: <strong>{{ $followUps->count() }}</strong>
            </p>
        </div>
        <div class="flex items-center gap-3">
            @php
                $downloadUrl = route('boards.reports.personal.follow_up.export') . '?' . http_build_query([
                    'format' => 'pdf',
                    'download' => 1,
                ]);
            @endphp
            <a href="{{ $downloadUrl }}" class="btn btn-primary flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Download PDF
            </a>
            <a href="{{ route('boards.reports.personal.follow_up.export') . '?format=csv' }}" class="btn btn-secondary flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M3 16.5l6-6 4 4 8-8"/></svg>
                Download CSV
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-auto flex-1">
        <table class="w-full text-left border-collapse text-sm">
            <thead class="sticky top-0 bg-indigo-600 text-white z-10">
                <tr>
                    <th class="px-4 py-3 font-semibold text-xs uppercase">Date</th>
                    <th class="px-4 py-3 font-semibold text-xs uppercase">Website</th>
                    <th class="px-4 py-3 font-semibold text-xs uppercase">Type</th>
                    <th class="px-4 py-3 font-semibold text-xs uppercase">URL</th>
                    <th class="px-4 py-3 font-semibold text-xs uppercase">Handled By</th>
                    <th class="px-4 py-3 font-semibold text-xs uppercase">QC Status</th>
                    <th class="px-4 py-3 font-semibold text-xs uppercase">QC Checker</th>
                    <th class="px-4 py-3 font-semibold text-xs uppercase">Note</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                @forelse($followUps as $fu)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ $fu->created_at->format('d M Y') }}</td>
                    <td class="px-4 py-3">
                        <div class="font-semibold text-slate-800 dark:text-white">{{ $fu->website->name ?? 'Unknown' }}</div>
                        <div class="text-xs text-slate-400">{{ $fu->website->category ?? 'Uncategorized' }}</div>
                    </td>
                    <td class="px-4 py-3 text-xs font-medium text-slate-700 dark:text-slate-300">{{ $fu->getTypeLabel() }}</td>
                    <td class="px-4 py-3 text-xs max-w-[160px] truncate">
                        @if($fu->url)
                            <a href="{{ $fu->url }}" target="_blank" class="text-indigo-600 hover:underline">{{ $fu->url }}</a>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-300">{{ $fu->assignee?->name ?? 'Unassigned' }}</td>
                    <td class="px-4 py-3">
                        @php
                            $statusColor = match($fu->qc_status) {
                                'approved' => 'text-emerald-600 bg-emerald-50',
                                'checked'  => 'text-blue-600 bg-blue-50',
                                default    => 'text-amber-600 bg-amber-50',
                            };
                        @endphp
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold {{ $statusColor }}">
                            {{ ucfirst($fu->qc_status ?? 'pending') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                        {{ $fu->qcChecker?->name ?? '—' }}
                        @if($fu->qc_checked_at)
                            <div class="text-slate-400">{{ $fu->qc_checked_at->format('d M Y') }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300 max-w-[200px]">{{ strip_tags($fu->note ?? '') ?: '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-12 text-slate-400">No follow-up records found for this period.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
