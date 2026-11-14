@extends('layouts.app')

@section('title', $report->title)

@section('content')
<div class="pr-root pb-6"
     x-data="{ lightboxUrl: null, lightboxTitle: '' }">
    @include('production-reports.partials.nav')

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-5 mb-6 border-b border-slate-800">
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                        {{ $report->report_type }} Report
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold pr-badge pr-badge-{{ $report->status_badge_color }}">
                        {{ $report->status_badge_label }}
                    </span>
                    @if($report->is_late)
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            Submitted After Deadline
                        </span>
                    @endif
                    @if($report->performance_rating)
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            ★ {{ $report->rating_display }}
                        </span>
                    @endif
                    @php
                        $healthKey = is_array($report->compiled_summary_json) ? ($report->compiled_summary_json['health_status'] ?? null) : null;
                        $healthMap = [
                            'on_track' => ['🟢 On track', 'emerald'],
                            'at_risk'  => ['🟡 At risk', 'amber'],
                            'blocked'  => ['🔴 Blocked', 'rose'],
                        ];
                    @endphp
                    @if($report->report_type === 'weekly' && $healthKey && isset($healthMap[$healthKey]))
                        <span class="pr-badge pr-badge-{{ $healthMap[$healthKey][1] }}">{{ $healthMap[$healthKey][0] }}</span>
                    @endif
                </div>
                <h2 class="text-xl sm:text-2xl font-extrabold text-white">{{ $report->title }}</h2>
                <p class="text-xs text-slate-400 mt-1">
                    Code: <strong class="text-slate-200">{{ $report->report_code }}</strong>
                    &bull; Prepared By: <strong class="text-slate-200">{{ $report->user?->name ?? 'N/A' }}</strong>
                    &bull; Team: <strong class="text-slate-200">{{ $report->team_name ?: ('Team ' . ($report->team_code ?: 'General')) }}</strong>
                    &bull; Period: <strong class="text-slate-200">{{ $report->period_label }}</strong>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($canEdit)
                    @if($report->report_type === 'daily')
                        <a href="{{ route('production-reports.daily.create', ['report_id' => $report->id]) }}"
                           class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold">
                            Edit Draft / Resubmit
                        </a>
                    @elseif($report->report_type === 'weekly')
                        <a href="{{ route('production-reports.weekly.create', ['report_id' => $report->id, 'team' => $report->team_code]) }}"
                           class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold">
                            Edit Weekly Report
                        </a>
                    @elseif($report->report_type === 'monthly')
                        <a href="{{ route('production-reports.monthly.create', ['year' => $report->reporting_year, 'month' => $report->reporting_month]) }}"
                           class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold">
                            Edit Monthly Report
                        </a>
                    @endif
                @endif

                <a href="{{ route('production-reports.export', [$report->id, 'pptx']) }}"
                   class="px-3.5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold">
                    Download .PPTX (12 Slides)
                </a>
                <a href="{{ route('production-reports.export', [$report->id, 'pdf']) }}"
                   class="px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold">
                    Download .PDF
                </a>
                <a href="{{ route('production-reports.export', [$report->id, 'csv']) }}"
                   class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold">
                    Download .CSV
                </a>
            </div>
        </div>

        @if($report->returned_reason && $report->status === 'returned_for_correction')
            <div class="mb-6 p-4 rounded-xl bg-rose-500/15 border border-rose-500/40 text-rose-200 text-xs">
                <strong>Returned for Correction:</strong> {{ $report->returned_reason }}
            </div>
        @endif

        @if(filled($report->executive_summary))
            <div class="mb-5 bg-slate-800/60 border border-slate-700/80 rounded-xl p-4">
                <h3 class="text-xs font-bold text-indigo-300 uppercase tracking-wider mb-1.5">Executive Summary</h3>
                <div class="text-sm text-slate-200 whitespace-pre-line">{{ $report->executive_summary }}</div>
            </div>
        @endif

        {{-- Individual Member Summary Table (for Weekly Team Reports) --}}
        @if(!empty($summary['member_summary_table']))
            <div class="mb-6">
                <h3 class="text-sm font-bold text-white mb-2">Individual Member Summary Table</h3>
                <div class="overflow-x-auto border border-slate-800 rounded-xl">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-800 text-slate-300 uppercase text-[11px]">
                            <tr>
                                <th class="py-2.5 px-3">Member</th>
                                <th class="py-2.5 px-3 text-right">Completed Tasks</th>
                                <th class="py-2.5 px-3">Work in Progress</th>
                                <th class="py-2.5 px-3">Pending</th>
                                <th class="py-2.5 px-3">Key Achievements</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($summary['member_summary_table'] as $row)
                                <tr>
                                    <td class="py-2.5 px-3 font-bold text-white">
                                        {{ $row['member_name'] ?? 'Member' }}
                                        <div class="text-[11px] text-slate-500 font-normal">{{ $row['submitted_days'] ?? 0 }}/{{ $row['expected_days'] ?? 0 }} daily reports</div>
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-bold text-emerald-400">{{ $row['completed_tasks_count'] ?? 0 }}</td>
                                    <td class="py-2.5 px-3 whitespace-pre-line">{{ $row['work_in_progress'] ?? '—' }}</td>
                                    <td class="py-2.5 px-3 whitespace-pre-line">{{ $row['pending_tasks'] ?? '—' }}</td>
                                    <td class="py-2.5 px-3 whitespace-pre-line">{{ $row['key_achievements'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-slate-800/50 border border-slate-700/80 rounded-xl p-4">
                <h3 class="text-xs font-bold text-emerald-300 uppercase tracking-wider mb-1.5">Tasks Completed &amp; Production Output</h3>
                <div class="text-xs text-slate-200 whitespace-pre-line">{{ $report->tasks_completed ?: 'None recorded.' }}</div>
            </div>
            <div class="bg-slate-800/50 border border-slate-700/80 rounded-xl p-4">
                <h3 class="text-xs font-bold text-sky-300 uppercase tracking-wider mb-1.5">Work in Progress</h3>
                <div class="text-xs text-slate-200 whitespace-pre-line">{{ $report->work_in_progress ?: 'None reported.' }}</div>
            </div>
            <div class="bg-slate-800/50 border border-slate-700/80 rounded-xl p-4">
                <h3 class="text-xs font-bold text-amber-300 uppercase tracking-wider mb-1.5">Pending Tasks</h3>
                <div class="text-xs text-slate-200 whitespace-pre-line">{{ $report->pending_tasks ?: 'None reported.' }}</div>
            </div>
            <div class="bg-slate-800/50 border border-slate-700/80 rounded-xl p-4">
                <h3 class="text-xs font-bold text-rose-300 uppercase tracking-wider mb-1.5">Challenges &amp; Blockers</h3>
                <div class="text-xs text-slate-200 whitespace-pre-line">{{ $report->blockers ?: 'No blockers reported.' }}</div>
            </div>
            <div class="bg-slate-800/50 border border-slate-700/80 rounded-xl p-4">
                <h3 class="text-xs font-bold text-indigo-300 uppercase tracking-wider mb-1.5">Important Achievements</h3>
                <div class="text-xs text-slate-200 whitespace-pre-line">{{ $report->achievements ?: 'None reported.' }}</div>
            </div>
            <div class="bg-slate-800/50 border border-slate-700/80 rounded-xl p-4">
                <h3 class="text-xs font-bold text-purple-300 uppercase tracking-wider mb-1.5">Next Priorities / Comments</h3>
                <div class="text-xs text-slate-200 whitespace-pre-line">
                    @if(filled($report->next_priorities))<strong>Next Priorities:</strong> {{ $report->next_priorities }}&#10;@endif
                    @if(filled($report->head_comments))<strong>Head Comments:</strong> {{ $report->head_comments }}&#10;@endif
                    @if(filled($report->supervisor_comments))<strong>Supervisor Comments:</strong> {{ $report->supervisor_comments }}@endif
                </div>
            </div>
        </div>

        @if($report->items->count() > 0)
            <div class="mb-6">
                <h3 class="text-sm font-bold text-white mb-2">Structured Measurable Task Items ({{ $report->items->count() }})</h3>
                <div class="overflow-x-auto border border-slate-800 rounded-xl">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-800 text-slate-400 uppercase text-[11px]">
                            <tr>
                                <th class="py-2 px-3">Task Title</th>
                                <th class="py-2 px-3">Category</th>
                                <th class="py-2 px-3">Quantity</th>
                                <th class="py-2 px-3">Status</th>
                                <th class="py-2 px-3">Link</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($report->items as $item)
                                <tr>
                                    <td class="py-2 px-3 font-semibold text-white">{{ $item->task_title }}</td>
                                    <td class="py-2 px-3">{{ $item->task_category }}</td>
                                    <td class="py-2 px-3">{{ $item->quantity }} {{ $item->unit }}</td>
                                    <td class="py-2 px-3 uppercase">{{ $item->status }}</td>
                                    <td class="py-2 px-3">
                                        @if($item->external_link)
                                            <a href="{{ $item->external_link }}" target="_blank" class="text-indigo-400 underline">Open Link</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Media & Attachments Table + Lightbox --}}
        <div class="mb-6">
            <h3 class="text-sm font-bold text-white mb-2">Posters, Graphics, Videos &amp; Attachments ({{ $report->media->count() }})</h3>
            <div class="overflow-x-auto border border-slate-800 rounded-xl">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-800 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-2.5 px-3">Preview</th>
                            <th class="py-2.5 px-3">Title</th>
                            <th class="py-2.5 px-3">Type</th>
                            <th class="py-2.5 px-3">Creator</th>
                            <th class="py-2.5 px-3">Date</th>
                            <th class="py-2.5 px-3">Project</th>
                            <th class="py-2.5 px-3 text-right">File</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($report->media as $media)
                            <tr>
                                <td class="py-2.5 px-3">
                                    @if($media->isImage() && $media->file_path)
                                        <button type="button"
                                                @click="lightboxUrl = '{{ route('production-reports.media.view', $media->id) }}'; lightboxTitle = '{{ addslashes($media->title) }}'"
                                                class="block w-14 h-10 rounded overflow-hidden border border-slate-700">
                                            <img src="{{ route('production-reports.media.view', $media->id) }}" class="w-full h-full object-cover" alt="{{ $media->title }}">
                                        </button>
                                    @elseif($media->isVideo() && $media->file_path)
                                        <video src="{{ route('production-reports.media.view', $media->id) }}" class="w-20 h-12 rounded bg-black" controls preload="metadata"></video>
                                    @else
                                        <span class="px-2 py-1 rounded bg-slate-800 text-[10px] font-bold uppercase">{{ $media->platform ?: $media->media_type }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 font-semibold text-white">
                                    {{ $media->title }}
                                    @if($media->is_highlighted)
                                        <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 text-[10px] font-bold">★ Highlight</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 uppercase">{{ $media->media_type }}</td>
                                <td class="py-2.5 px-3">{{ $media->creator?->name ?? $report->user?->name ?? 'N/A' }}</td>
                                <td class="py-2.5 px-3">{{ $media->production_date?->format('M d, Y') ?? '—' }}</td>
                                <td class="py-2.5 px-3">{{ $media->project_category ?: 'General' }}</td>
                                <td class="py-2.5 px-3 text-right space-x-2">
                                    @if($media->external_url)
                                        <a href="{{ $media->external_url }}" target="_blank" class="text-indigo-400 hover:text-indigo-300 font-bold">Open URL &nearr;</a>
                                    @endif
                                    @if($media->file_path)
                                        <a href="{{ route('production-reports.media.view', $media->id) }}" target="_blank" class="text-sky-400 hover:text-sky-300 font-bold">Preview</a>
                                        <a href="{{ route('production-reports.media.download', $media->id) }}" class="text-emerald-400 hover:text-emerald-300 font-bold">Download</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4 text-center text-slate-500">No media files or external links attached.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Auditable Revision History --}}
        <div>
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Auditable Revision History ({{ $report->revisions->count() }})</h3>
            <div class="space-y-1.5">
                @foreach($report->revisions as $rev)
                    <div class="flex items-center justify-between text-xs bg-slate-800/40 border border-slate-800 rounded-lg px-3 py-2 text-slate-300">
                        <div>
                            <strong class="text-white">Rev #{{ $rev->revision_number }}</strong> &bull;
                            <span class="uppercase font-semibold text-indigo-300">{{ str_replace('_', ' ', $rev->action) }}</span>
                            by {{ $rev->user?->name ?? 'System' }}
                            @if($rev->notes)
                                <span class="text-slate-400">— "{{ $rev->notes }}"</span>
                            @endif
                        </div>
                        <div class="text-[11px] text-slate-500">
                            {{ $rev->created_at?->timezone('Asia/Phnom_Penh')->format('M d, Y g:i:s A') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Image Lightbox Modal --}}
    <div x-show="lightboxUrl" x-cloak @click.self="lightboxUrl = null"
         class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl max-w-4xl w-full p-4">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-bold text-white" x-text="lightboxTitle"></h4>
                <button type="button" @click="lightboxUrl = null" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
            </div>
            <img :src="lightboxUrl" class="max-h-[75vh] mx-auto rounded-lg object-contain" alt="Preview">
        </div>
    </div>
</div>
@endsection

