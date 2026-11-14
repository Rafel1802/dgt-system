@extends('layouts.app')

@section('title', 'My Production Reports')

@section('content')
<div class="pr-root pb-8">
    @include('production-reports.partials.nav')

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
        {{-- Header & Quick Stats --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 pb-4 mb-4 border-b border-slate-800">
            <div>
                <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                    <span>Authorized Production Reports Archive</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                        {{ $reports->total() }} total
                    </span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">
                    Filter by date range, search past submissions, download combined reports (PDF/CSV), or copy a text summary in 1 click.
                </p>
            </div>

            {{-- 1-Click Combined Actions --}}
            <div class="flex flex-wrap items-center gap-2">
                <button type="button"
                        id="copyTextBtn"
                        onclick="copyCombinedReportText()"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-bold border border-slate-700 transition shadow-sm">
                    <span id="copyIcon">📋</span>
                    <span id="copyLabel">Copy Combined Text</span>
                </button>

                <a href="{{ route('production-reports.export-range', array_merge(request()->query(), ['format' => 'pdf'])) }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 text-xs font-bold border border-rose-500/30 transition shadow-sm">
                    <span>📥</span>
                    <span>Download Combined PDF</span>
                </a>

                <a href="{{ route('production-reports.export-range', array_merge(request()->query(), ['format' => 'csv'])) }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 text-xs font-bold border border-emerald-500/30 transition shadow-sm">
                    <span>📊</span>
                    <span>Download Combined CSV</span>
                </a>
            </div>
        </div>

        {{-- Filter & Date Range Bar --}}
        <form method="GET" action="{{ route('production-reports.my-reports') }}" class="mb-5 bg-slate-800/50 p-4 rounded-xl border border-slate-800/80">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                <div class="lg:col-span-2">
                    <label class="block text-[11px] font-semibold text-slate-400 mb-1">Search Keywords / Report Code</label>
                    <input type="text"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Search title, code, tasks, notes..."
                           class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-3 py-2 focus:border-indigo-500 placeholder-slate-500">
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 mb-1">From Date</label>
                    <input type="date"
                           id="filterFromDate"
                           name="from"
                           value="{{ request('from') }}"
                           class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-2 focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 mb-1">To Date</label>
                    <input type="date"
                           id="filterToDate"
                           name="to"
                           value="{{ request('to') }}"
                           class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-2 focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 mb-1">Report Type</label>
                    <select name="type" class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-2 focus:border-indigo-500">
                        <option value="">All Types</option>
                        <option value="daily" {{ request('type') === 'daily' ? 'selected' : '' }}>Daily</option>
                        <option value="weekly" {{ request('type') === 'weekly' ? 'selected' : '' }}>Weekly</option>
                        <option value="monthly" {{ request('type') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 mb-1">Status</label>
                    <select name="status" class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-2 focus:border-indigo-500">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Submitted</option>
                        <option value="submitted_late" {{ request('status') === 'submitted_late' ? 'selected' : '' }}>Submitted Late</option>
                        <option value="returned_for_correction" {{ request('status') === 'returned_for_correction' ? 'selected' : '' }}>Returned</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    </select>
                </div>
            </div>

            {{-- Quick Date Range Presets & Submit Buttons --}}
            <div class="flex flex-wrap items-center justify-between gap-2 mt-3 pt-3 border-t border-slate-700/60">
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="text-[11px] text-slate-400 mr-1">Presets:</span>
                    <button type="button" onclick="setDateRange('today')" class="px-2 py-1 rounded bg-slate-900 hover:bg-slate-700 text-slate-300 text-[11px]">Today</button>
                    <button type="button" onclick="setDateRange('this_week')" class="px-2 py-1 rounded bg-slate-900 hover:bg-slate-700 text-slate-300 text-[11px]">This Week</button>
                    <button type="button" onclick="setDateRange('this_month')" class="px-2 py-1 rounded bg-slate-900 hover:bg-slate-700 text-slate-300 text-[11px]">This Month</button>
                    <button type="button" onclick="setDateRange('last_30_days')" class="px-2 py-1 rounded bg-slate-900 hover:bg-slate-700 text-slate-300 text-[11px]">Last 30 Days</button>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('production-reports.my-reports') }}" class="px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-700 text-slate-300 text-xs font-semibold">
                        Reset
                    </a>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition">
                        Apply Filter
                    </button>
                </div>
            </div>
        </form>

        {{-- Toast notification banner --}}
        <div id="copyToast" class="hidden mb-4 p-3 rounded-xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-xs font-bold flex items-center justify-between">
            <span id="copyToastMsg">Copied combined text to clipboard!</span>
            <button type="button" onclick="document.getElementById('copyToast').classList.add('hidden')" class="text-emerald-400 hover:text-white">&times;</button>
        </div>

        {{-- Reports Table --}}
        <div class="overflow-x-auto rounded-xl border border-slate-800">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-800/80 text-slate-400 uppercase text-[11px]">
                    <tr>
                        <th class="py-2.5 px-3">Report Title / Code</th>
                        <th class="py-2.5 px-3">Type</th>
                        <th class="py-2.5 px-3">Team</th>
                        <th class="py-2.5 px-3">Author</th>
                        <th class="py-2.5 px-3">Period</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 px-3 text-right">Actions &amp; Exports</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($reports as $rep)
                        <tr class="transition">
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
                            <td class="py-2.5 px-3">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold pr-badge pr-badge-{{ $rep->status_badge_color }}">
                                    {{ $rep->status_badge_label }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right space-x-1 whitespace-nowrap">
                                <a href="{{ route('production-reports.show', $rep->id) }}"
                                   class="pr-action-pill pr-action-pill-view">
                                    Open
                                </a>
                                <a href="{{ route('production-reports.export', [$rep->id, 'pptx']) }}"
                                   class="pr-action-pill pr-action-pill-pptx"
                                   title="Export PPTX">
                                    PPTX
                                </a>
                                <a href="{{ route('production-reports.export', [$rep->id, 'pdf']) }}"
                                   class="pr-action-pill pr-action-pill-pdf"
                                   title="Export PDF">
                                    PDF
                                </a>
                                <a href="{{ route('production-reports.export', [$rep->id, 'csv']) }}"
                                   class="pr-action-pill pr-action-pill-csv"
                                   title="Export CSV">
                                    CSV
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                <p class="text-sm font-semibold">No matching production reports found.</p>
                                <p class="text-xs text-slate-600 mt-1">Try expanding your date range or clearing the search filter.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $reports->links() }}
        </div>
    </div>
</div>

<script>
function setDateRange(range) {
    const today = new Date();
    const fromInput = document.getElementById('filterFromDate');
    const toInput = document.getElementById('filterToDate');

    const fmt = d => d.toISOString().split('T')[0];

    if (range === 'today') {
        fromInput.value = fmt(today);
        toInput.value = fmt(today);
    } else if (range === 'this_week') {
        const d = new Date(today);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Monday
        const monday = new Date(d.setDate(diff));
        fromInput.value = fmt(monday);
        toInput.value = fmt(today);
    } else if (range === 'this_month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        fromInput.value = fmt(firstDay);
        toInput.value = fmt(today);
    } else if (range === 'last_30_days') {
        const past = new Date(today);
        past.setDate(past.getDate() - 30);
        fromInput.value = fmt(past);
        toInput.value = fmt(today);
    }
}

async function copyCombinedReportText() {
    const btn = document.getElementById('copyTextBtn');
    const icon = document.getElementById('copyIcon');
    const label = document.getElementById('copyLabel');
    const toast = document.getElementById('copyToast');
    const toastMsg = document.getElementById('copyToastMsg');

    icon.textContent = '⏳';
    label.textContent = 'Preparing text...';
    btn.disabled = true;

    try {
        const params = new URLSearchParams(window.location.search);
        const response = await fetch("{{ route('production-reports.summary-text') }}?" + params.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (data.success && data.text) {
            await navigator.clipboard.writeText(data.text);
            icon.textContent = '✅';
            label.textContent = 'Copied (' + data.count + ' reports)!';
            toastMsg.textContent = '✅ Copied ' + data.count + ' production reports (' + data.period + ') to clipboard!';
            toast.classList.remove('hidden');

            setTimeout(() => {
                icon.textContent = '📋';
                label.textContent = 'Copy Combined Text';
                btn.disabled = false;
            }, 3000);
        } else {
            alert('No reports found to compile into text.');
            icon.textContent = '📋';
            label.textContent = 'Copy Combined Text';
            btn.disabled = false;
        }
    } catch (err) {
        console.error(err);
        alert('Could not copy report text: ' + (err.message || err));
        icon.textContent = '📋';
        label.textContent = 'Copy Combined Text';
        btn.disabled = false;
    }
}
</script>
@endsection
