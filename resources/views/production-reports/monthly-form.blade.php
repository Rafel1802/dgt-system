@extends('layouts.app')

@section('title', 'Submit Monthly Production Report')

@section('content')
@php
    $savedSummary = is_array($report?->compiled_summary_json) ? $report->compiled_summary_json : [];
    $selectedPosters = old('selected_poster_ids', $savedSummary['selected_poster_media_ids'] ?? []);
    $selectedVideos = old('selected_video_ids', $savedSummary['selected_video_media_ids'] ?? []);
@endphp

<div class="pr-root pb-6">
    @include('production-reports.partials.nav')

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
        @if($permissionService->isProductionHead($user))
            <div class="mb-5 p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="text-xs font-bold text-amber-300">★ Head of Production Tools</div>
                    <div class="text-[11px] text-slate-300">You can compile all member reports in your squad and submit the consolidated Team Monthly report to Supervisor Somalika.</div>
                </div>
                <a href="{{ route('production-reports.monthly.team.create') }}" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold whitespace-nowrap transition">
                    ★ Open Team Monthly Compilation &rarr;
                </a>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 mb-5 border-b border-slate-800">
            <div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    AUTO-COMPILED FROM DAILY REPORTS
                </span>
                <h2 class="text-xl font-extrabold text-white mt-1">Monthly Production Report — {{ $compiled['month_label'] }}</h2>
                <p class="text-xs text-slate-400">
                    Monthly Deadline: {{ $deadline->format('M d, Y g:i A') }} (Cambodia Time)
                </p>
            </div>

            <form method="GET" action="{{ route('production-reports.monthly.create') }}" class="flex items-end gap-2">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 mb-0.5">Month</label>
                    <select name="month" class="bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $month === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::createFromDate($year, $m, 1)->format('F') }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 mb-0.5">Year</label>
                    <input type="number" name="year" value="{{ $year }}" min="2024" max="2035" class="w-24 bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5">
                </div>
                <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold">
                    Load Month
                </button>
            </form>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <div class="bg-slate-800/70 border border-slate-700 rounded-xl p-3">
                <div class="text-[11px] text-slate-400">Daily Reports Submitted</div>
                <div class="text-lg font-extrabold text-emerald-400">{{ $compiled['submitted_days_count'] }} / {{ $compiled['expected_days_count'] }}</div>
            </div>
            <div class="bg-slate-800/70 border border-slate-700 rounded-xl p-3">
                <div class="text-[11px] text-slate-400">Monthly Reporting Rate</div>
                <div class="text-lg font-extrabold text-indigo-400">{{ $compiled['submission_rate'] }}%</div>
            </div>
            <div class="bg-slate-800/70 border border-slate-700 rounded-xl p-3">
                <div class="text-[11px] text-slate-400">Missing Daily Dates</div>
                <div class="text-lg font-extrabold text-rose-400">{{ $compiled['missing_days_count'] }}</div>
            </div>
            <div class="bg-slate-800/70 border border-slate-700 rounded-xl p-3">
                <div class="text-[11px] text-slate-400">Completed Tasks Recorded</div>
                <div class="text-lg font-extrabold text-sky-400">{{ $compiled['total_completed_tasks'] }}</div>
            </div>
        </div>

        @if(!empty($compiled['missing_dates']))
            <div class="mb-5 p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-200">
                <strong>Missing Daily Reporting Dates ({{ count($compiled['missing_dates']) }}):</strong>
                {{ implode(', ', array_slice($compiled['missing_dates'], 0, 15)) }}
                @if(count($compiled['missing_dates']) > 15) ... @endif
            </div>
        @endif

        <form method="POST" action="{{ route('production-reports.monthly.store') }}" enctype="multipart/form-data">
            @csrf
            @if($report)
                <input type="hidden" name="report_id" value="{{ $report->id }}">
            @endif
            <input type="hidden" name="reporting_year" value="{{ $year }}">
            <input type="hidden" name="reporting_month" value="{{ $month }}">

            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-300 mb-1">Monthly Report Title</label>
                <input type="text" name="title"
                       value="{{ old('title', $report?->title ?? "{$user->name} — Monthly Production Report ({$compiled['month_label']})") }}"
                       class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Monthly Executive Summary</label>
                    <textarea name="executive_summary" rows="4" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">{{ old('executive_summary', $report?->executive_summary ?? $compiled['compiled_texts']['executive_summary']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">All Tasks Completed in {{ $compiled['month_label'] }}</label>
                    <textarea name="tasks_completed" rows="4" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">{{ old('tasks_completed', $report?->tasks_completed ?? $compiled['compiled_texts']['tasks_completed']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Ongoing / Pending Projects</label>
                    <textarea name="work_in_progress" rows="3" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">{{ old('work_in_progress', $report?->work_in_progress ?? $compiled['compiled_texts']['work_in_progress']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Challenges Faced</label>
                    <textarea name="blockers" rows="3" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">{{ old('blockers', $report?->blockers ?? $compiled['compiled_texts']['blockers']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Top Monthly Achievements</label>
                    <textarea name="achievements" rows="3" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">{{ old('achievements', $report?->achievements ?? $compiled['compiled_texts']['achievements']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Next Month's Goals &amp; Priorities</label>
                    <textarea name="next_priorities" rows="3" placeholder="Outline your production goals for next month"
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">{{ old('next_priorities', $report?->next_priorities) }}</textarea>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <h4 class="text-xs font-bold text-white uppercase mb-2">Select Your Best Posters &amp; Graphics ({{ $compiled['candidate_posters']->count() }})</h4>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        @forelse($compiled['candidate_posters'] as $poster)
                            <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white">
                                <input type="checkbox" name="selected_poster_ids[]" value="{{ $poster->id }}"
                                       {{ in_array($poster->id, (array) $selectedPosters) ? 'checked' : '' }}>
                                <span>{{ $poster->title }} ({{ $poster->production_date?->format('M d') }})</span>
                            </label>
                        @empty
                            <p class="text-xs text-slate-500">No posters uploaded in daily reports this month.</p>
                        @endforelse
                    </div>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <h4 class="text-xs font-bold text-white uppercase mb-2">Select Your Best Videos ({{ $compiled['candidate_videos']->count() }})</h4>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        @forelse($compiled['candidate_videos'] as $vid)
                            <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white">
                                <input type="checkbox" name="selected_video_ids[]" value="{{ $vid->id }}"
                                       {{ in_array($vid->id, (array) $selectedVideos) ? 'checked' : '' }}>
                                <span>{{ $vid->title }} ({{ $vid->platform ?: 'Video' }})</span>
                            </label>
                        @empty
                            <p class="text-xs text-slate-500">No videos uploaded in daily reports this month.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="mb-6 bg-slate-800/40 border-2 border-dashed border-indigo-500/40 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-rose-500/20 text-rose-300 border border-rose-500/30">
                        Mandatory Document
                    </span>
                    <label class="block text-xs font-bold text-white uppercase tracking-wider">
                        Attach Monthly Report Presentation / Document (PDF, PowerPoint, Word) <span class="text-rose-400">*</span>
                    </label>
                </div>
                <p class="text-xs text-slate-300 mb-2">
                    Submitting your monthly report requires uploading at least one document:
                    <span class="font-bold text-white">PDF (.pdf), PowerPoint (.ppt, .pptx), or Word (.doc, .docx)</span>.
                </p>

                @if($report && $report->media->count() > 0)
                    <div class="mb-3 p-2.5 rounded-lg bg-slate-900 border border-slate-700">
                        <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Currently Attached Files:</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($report->media as $m)
                                <a href="{{ route('production-reports.media.download', $m->id) }}" target="_blank"
                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-slate-800 text-xs text-indigo-300 font-semibold border border-slate-700">
                                    <span>📎 {{ $m->original_filename ?: $m->title }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <input type="file" name="attachments[]" multiple
                       accept=".pdf,.ppt,.pptx,.doc,.docx,.jpg,.jpeg,.png,.webp,.mp4"
                       class="block w-full text-xs text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white cursor-pointer">
            </div>

            @if(!$report || $report->isEditableByAuthor())
                <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="submit" name="action" value="draft"
                            class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold border border-slate-700">
                        Save Monthly Draft
                    </button>
                    <button type="submit" name="action" value="submit"
                            onclick="return confirm('Submit your Monthly Production Report?')"
                            class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-lg">
                        Submit Monthly Report
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>
@endsection

