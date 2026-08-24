@extends('layouts.app')
@section('title', 'Website Status Report Preview')

@section('content')
<div class="w-full h-[calc(100vh-100px)] flex flex-col p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Website Status Report Preview</h1>
            <p class="text-sm text-slate-500 mt-1">
                Member: <strong>{{ $user ? $user->name : 'All Members' }}</strong> &nbsp;|&nbsp;
                Date: <strong>{{ $dateFrom }}</strong> to <strong>{{ $dateTo }}</strong> &nbsp;|&nbsp;
                Total Records: <strong>{{ count($activities) }}</strong>
            </p>
        </div>
        <div class="flex items-center gap-3">
            @php
                $dlUrl = route('boards.reports.personal.website.export') . '?' . http_build_query([
                    'format'   => 'pdf',
                    'download' => 1,
                ]);
                $csvUrl = route('boards.reports.personal.website.export') . '?format=csv';
            @endphp
            <a href="{{ $dlUrl }}" class="btn btn-primary flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Download PDF
            </a>
            <a href="{{ $csvUrl }}" class="btn btn-secondary flex items-center gap-2">
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
                    <th class="px-4 py-3 font-semibold text-xs uppercase">User</th>
                    <th class="px-4 py-3 font-semibold text-xs uppercase">Website</th>
                    <th class="px-4 py-3 font-semibold text-xs uppercase">Action</th>
                    <th class="px-4 py-3 font-semibold text-xs uppercase">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                @forelse($activities as $act)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">
                        {{ $act['date'] instanceof \DateTimeInterface ? $act['date']->format('d M Y H:i') : $act['date'] }}
                    </td>
                    <td class="px-4 py-3 text-xs font-medium text-slate-700 dark:text-slate-300">{{ $act['user'] ?: '—' }}</td>
                    <td class="px-4 py-3 font-semibold text-slate-800 dark:text-white">{{ $act['website'] }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700">{{ $act['action'] }}</span>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">{{ $act['details'] ?: '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-12 text-slate-400">No website activities found for this period.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
