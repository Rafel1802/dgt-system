@extends('layouts.app')

@section('title', 'Production Report Archive')

@section('content')
<div class="pr-root pb-8">
    @include('production-reports.partials.nav')

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-5 pb-4 border-b border-slate-800">
            <div>
                <h2 class="text-lg font-extrabold text-white flex items-center gap-2">
                    <span>Production Report Archive &amp; Export Center</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                        {{ $reports->total() }} reports
                    </span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">
                    Select individual reports or the entire team to export as a consolidated production package (PDF, CSV).
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('production-reports.archive.export-csv', request()->query()) }}"
                   class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold border border-slate-700 transition">
                    Export Filtered CSV
                </a>
            </div>
        </div>

        {{-- Filter Form --}}
        <form method="GET" action="{{ route('production-reports.archive') }}" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-2.5 mb-5 bg-slate-800/50 p-3.5 rounded-xl border border-slate-800">
            <div>
                <label class="block text-[11px] text-slate-400 mb-0.5">Year</label>
                <input type="number" name="year" value="{{ request('year') }}" placeholder="All" class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[11px] text-slate-400 mb-0.5">Month</label>
                <select name="month" class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5 focus:border-indigo-500">
                    <option value="">All Months</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ (int) request('month') === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::createFromDate(2026, $m, 1)->format('F') }}</option>
                    @endfor
                </select>
            </div>
            @if($role === 'supervisor')
                <div>
                    <label class="block text-[11px] text-slate-400 mb-0.5">Team</label>
                    <select name="team" class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5 focus:border-indigo-500">
                        <option value="">All Teams</option>
                        <option value="A" {{ request('team') === 'A' ? 'selected' : '' }}>Team A (Dara)</option>
                        <option value="B" {{ request('team') === 'B' ? 'selected' : '' }}>Team B (Kim)</option>
                    </select>
                </div>
            @endif
            <div>
                <label class="block text-[11px] text-slate-400 mb-0.5">Member</label>
                <select name="member_id" class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5 focus:border-indigo-500">
                    <option value="">All Members</option>
                    @foreach($availableMembers as $mem)
                        <option value="{{ $mem->id }}" {{ (int) request('member_id') === (int) $mem->id ? 'selected' : '' }}>{{ $mem->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-slate-400 mb-0.5">Report Type</label>
                <select name="report_type" class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5 focus:border-indigo-500">
                    <option value="">All Types</option>
                    <option value="daily" {{ request('report_type') === 'daily' ? 'selected' : '' }}>Daily</option>
                    <option value="weekly" {{ request('report_type') === 'weekly' ? 'selected' : '' }}>Weekly</option>
                    <option value="monthly" {{ request('report_type') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-slate-400 mb-0.5">Status</label>
                <select name="status" class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5 focus:border-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Submitted</option>
                    <option value="submitted_late" {{ request('status') === 'submitted_late' ? 'selected' : '' }}>Submitted Late</option>
                    <option value="returned_for_correction" {{ request('status') === 'returned_for_correction' ? 'selected' : '' }}>Returned</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                </select>
            </div>
            <div class="flex items-end gap-1.5">
                <button type="submit" class="w-full px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition">Search</button>
                <a href="{{ route('production-reports.archive') }}" class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs">Reset</a>
            </div>
        </form>

        {{-- Whole Team / Selected Reports Batch Form --}}
        <form method="POST" id="batchExportForm" action="{{ route('production-reports.team.export-combined') }}">
            @csrf
            {{-- Mirror filter inputs for whole-team export fallback --}}
            <input type="hidden" name="year" value="{{ request('year') }}">
            <input type="hidden" name="month" value="{{ request('month') }}">
            <input type="hidden" name="team" value="{{ request('team') }}">
            <input type="hidden" name="report_type" value="{{ request('report_type') }}">

            {{-- Floating / Top Action Toolbar for Combined Export --}}
            <div class="mb-3 p-3 rounded-xl bg-slate-800/80 border border-slate-700/80 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 text-xs font-bold text-white cursor-pointer">
                        <input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll(this)" class="rounded bg-slate-900 border-slate-700 text-indigo-600">
                        <span>Select All On Page</span>
                    </label>
                    <span id="selectedCountBadge" class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-500/20 text-indigo-300">
                        0 selected
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" name="format" value="pdf"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 text-xs font-bold border border-rose-500/30 transition">
                        <span>📥</span>
                        <span>Export Combined PDF</span>
                    </button>
                    <button type="submit" name="format" value="csv"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 text-xs font-bold border border-emerald-500/30 transition">
                        <span>📊</span>
                        <span>Export Combined CSV</span>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-800">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-800/80 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-2.5 px-3 w-8"></th>
                            <th class="py-2.5 px-3">Report Code &amp; Title</th>
                            <th class="py-2.5 px-3">Type</th>
                            <th class="py-2.5 px-3">Team</th>
                            <th class="py-2.5 px-3">Author</th>
                            <th class="py-2.5 px-3">Period</th>
                            <th class="py-2.5 px-3">Media</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3 text-right">Exports</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($reports as $rep)
                            <tr class="transition">
                                <td class="py-2.5 px-3">
                                    <input type="checkbox" name="report_ids[]" value="{{ $rep->id }}"
                                           class="report-checkbox rounded bg-slate-900 border-slate-700 text-indigo-600"
                                           onchange="updateSelectedCount()">
                                </td>
                                <td class="py-2.5 px-3">
                                    <a href="{{ route('production-reports.show', $rep->id) }}" class="font-bold text-white hover:text-indigo-400 transition">
                                        {{ $rep->title }}
                                    </a>
                                    <div class="text-[11px] text-slate-500 font-mono">{{ $rep->report_code }}</div>
                                </td>
                                <td class="py-2.5 px-3 uppercase font-bold text-slate-300">{{ $rep->report_type }}</td>
                                <td class="py-2.5 px-3">Team {{ $rep->team_code ?: '—' }}</td>
                                <td class="py-2.5 px-3">{{ $rep->user?->name ?? 'N/A' }}</td>
                                <td class="py-2.5 px-3">{{ $rep->period_label }}</td>
                                <td class="py-2.5 px-3">{{ $rep->media_count }} file(s)</td>
                                <td class="py-2.5 px-3">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold pr-badge pr-badge-{{ $rep->status_badge_color }}">
                                        {{ $rep->status_badge_label }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right space-x-1 whitespace-nowrap">
                                    <a href="{{ route('production-reports.show', $rep->id) }}" class="pr-action-pill pr-action-pill-view">View</a>
                                    <a href="{{ route('production-reports.export', [$rep->id, 'pptx']) }}" class="pr-action-pill pr-action-pill-pptx" title="Export PPTX">PPTX</a>
                                    <a href="{{ route('production-reports.export', [$rep->id, 'pdf']) }}" class="pr-action-pill pr-action-pill-pdf" title="Export PDF">PDF</a>
                                    <a href="{{ route('production-reports.export', [$rep->id, 'csv']) }}" class="pr-action-pill pr-action-pill-csv" title="Export CSV">CSV</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-8 text-center text-slate-500">No archived production reports match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <div class="mt-4">
            {{ $reports->links() }}
        </div>
    </div>
</div>

<script>
function toggleSelectAll(master) {
    const checkboxes = document.querySelectorAll('.report-checkbox');
    checkboxes.forEach(cb => cb.checked = master.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.report-checkbox:checked').length;
    const badge = document.getElementById('selectedCountBadge');
    if (badge) {
        badge.textContent = checked > 0 ? (checked + ' selected') : '0 selected (exports whole filtered team)';
    }
}
updateSelectedCount();
</script>
@endsection
