@extends('layouts.app')

@section('title', 'Weekly Team Production Reports')

@section('content')
<div class="pr-root pb-6">
    @include('production-reports.partials.nav')

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h2 class="text-base font-bold text-white">Weekly Team Production Reports</h2>
                <p class="text-xs text-slate-400">Weekly Deadline: Every Saturday before 8:00 AM Cambodia Time</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                {{ $weeklyReports->total() }} Total Weekly Reports
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-800/80 text-slate-400 uppercase text-[11px]">
                    <tr>
                        <th class="py-2.5 px-3">Weekly Report</th>
                        <th class="py-2.5 px-3">Team</th>
                        <th class="py-2.5 px-3">Head of Production</th>
                        <th class="py-2.5 px-3">Period</th>
                        <th class="py-2.5 px-3">Rating (1–5)</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 px-3 text-right">Exports &amp; View</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($weeklyReports as $rep)
                        <tr>
                            <td class="py-2.5 px-3">
                                <a href="{{ route('production-reports.show', $rep->id) }}" class="font-bold text-white hover:text-indigo-400">{{ $rep->title }}</a>
                                <div class="text-[11px] text-slate-500">{{ $rep->report_code }}</div>
                            </td>
                            <td class="py-2.5 px-3 font-bold">Team {{ $rep->team_code }}</td>
                            <td class="py-2.5 px-3">{{ $rep->user?->name ?? 'N/A' }}</td>
                            <td class="py-2.5 px-3">{{ $rep->period_label }}</td>
                            <td class="py-2.5 px-3 font-bold text-amber-300">{{ $rep->rating_display }}</td>
                            <td class="py-2.5 px-3">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold pr-badge pr-badge-{{ $rep->status_badge_color }}">
                                    {{ $rep->status_badge_label }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right space-x-1 whitespace-nowrap">
                                <a href="{{ route('production-reports.show', $rep->id) }}" class="pr-action-pill pr-action-pill-view">Open</a>
                                <a href="{{ route('production-reports.export', [$rep->id, 'pptx']) }}" class="pr-action-pill pr-action-pill-pptx" title="Export PPTX">PPTX</a>
                                <a href="{{ route('production-reports.export', [$rep->id, 'pdf']) }}" class="pr-action-pill pr-action-pill-pdf" title="Export PDF">PDF</a>
                                <a href="{{ route('production-reports.export', [$rep->id, 'csv']) }}" class="pr-action-pill pr-action-pill-csv" title="Export CSV">CSV</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-500">No weekly team reports found yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $weeklyReports->links() }}
        </div>
    </div>
</div>
@endsection

