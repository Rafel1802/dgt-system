@extends('layouts.app')

@section('title', "Compile Team {$teamCode} Monthly Production Report")

@section('content')
<div class="pr-root pb-8">
    @include('production-reports.partials.nav')

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-4 mb-5 border-b border-slate-800">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                        HEAD COMPILATION &bull; TEAM {{ $teamCode }}
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300">
                        Submits to Supervisor Somalika
                    </span>
                </div>
                <h2 class="text-xl font-extrabold text-white">Team {{ $teamCode }} Monthly Production Report — {{ $compiled['month_label'] }}</h2>
                <p class="text-xs text-slate-400 mt-0.5">
                    Deadline: {{ $deadline->format('M d, Y g:i A') }} (Cambodia Time) &bull; Combine all {{ $compiled['members_count'] }} member reports into one consolidated team submission.
                </p>
            </div>

            <form method="GET" action="{{ route('production-reports.monthly.team.create') }}" class="flex items-end gap-2">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 mb-0.5">Month</label>
                    <select name="month" class="bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5 focus:border-indigo-500">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $month === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::createFromDate($year, $m, 1)->format('F') }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 mb-0.5">Year</label>
                    <input type="number" name="year" value="{{ $year }}" min="2024" max="2035" class="w-24 bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5 focus:border-indigo-500">
                </div>
                <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition">
                    Load Month
                </button>
            </form>
        </div>

        {{-- Team Monthly Overview KPIs --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div class="bg-slate-800/70 border border-slate-700/80 rounded-xl p-3.5">
                <div class="text-[11px] font-medium text-slate-400 uppercase tracking-wide">Member Submissions</div>
                <div class="text-xl font-extrabold text-emerald-400 mt-1">
                    {{ $compiled['submitted_members_count'] }} / {{ $compiled['members_count'] }}
                </div>
                <div class="text-[10px] text-slate-500 mt-0.5">Roster compliance</div>
            </div>
            <div class="bg-slate-800/70 border border-slate-700/80 rounded-xl p-3.5">
                <div class="text-[11px] font-medium text-slate-400 uppercase tracking-wide">Team Reporting Rate</div>
                <div class="text-xl font-extrabold text-indigo-400 mt-1">
                    {{ $compiled['submission_rate'] }}%
                </div>
                <div class="text-[10px] text-slate-500 mt-0.5">Submitted vs Expected</div>
            </div>
            <div class="bg-slate-800/70 border border-slate-700/80 rounded-xl p-3.5">
                <div class="text-[11px] font-medium text-slate-400 uppercase tracking-wide">Missing Member Reports</div>
                <div class="text-xl font-extrabold {{ count($compiled['missing_members']) > 0 ? 'text-rose-400' : 'text-slate-400' }} mt-1">
                    {{ count($compiled['missing_members']) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-0.5">{{ count($compiled['missing_members']) === 0 ? 'All submitted' : 'Pending submission' }}</div>
            </div>
            <div class="bg-slate-800/70 border border-slate-700/80 rounded-xl p-3.5">
                <div class="text-[11px] font-medium text-slate-400 uppercase tracking-wide">Team Tasks Recorded</div>
                <div class="text-xl font-extrabold text-sky-400 mt-1">
                    {{ $compiled['total_completed_tasks'] }}
                </div>
                <div class="text-[10px] text-slate-500 mt-0.5">From daily reports</div>
            </div>
        </div>

        {{-- Member Monthly Submissions Breakdown Table --}}
        <div class="mb-6">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-2 flex items-center justify-between">
                <span>Team {{ $teamCode }} Members Monthly Status &amp; Uploaded Documents</span>
                <span class="text-xs text-slate-400 lowercase font-normal">{{ $compiled['members_count'] }} active squad members</span>
            </h3>
            <div class="overflow-x-auto rounded-xl border border-slate-800">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-800/90 text-slate-400 uppercase text-[11px]">
                        <tr>
                            <th class="py-2.5 px-3">Team Member</th>
                            <th class="py-2.5 px-3">Squad Role</th>
                            <th class="py-2.5 px-3">Monthly Status</th>
                            <th class="py-2.5 px-3">Days Reported</th>
                            <th class="py-2.5 px-3">Tasks</th>
                            <th class="py-2.5 px-3">Submitted Attached Documents (PDF/PPT/Word)</th>
                            <th class="py-2.5 px-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 bg-slate-900/60">
                        @foreach($compiled['member_rows'] as $row)
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="py-2.5 px-3 font-semibold text-white">
                                    {{ $row['member_name'] }}
                                </td>
                                <td class="py-2.5 px-3 text-slate-400">
                                    {{ $row['role_title'] }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold pr-badge pr-badge-{{ $row['report_status_color'] }}">
                                        {{ $row['report_status'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="{{ $row['missing_days'] > 0 ? 'text-amber-400' : 'text-emerald-400' }} font-bold">
                                        {{ $row['submitted_days'] }} / {{ $row['expected_days'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 font-bold text-sky-400">
                                    {{ $row['completed_tasks'] }}
                                </td>
                                <td class="py-2.5 px-3">
                                    @if(!empty($row['documents']))
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($row['documents'] as $doc)
                                                <a href="{{ route('production-reports.media.download', $doc['id']) }}"
                                                   target="_blank"
                                                   class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-indigo-300 text-[10px] font-semibold border border-slate-700 transition"
                                                   title="{{ $doc['title'] }}">
                                                    <span>📄 {{ strtoupper($doc['extension']) }}</span>
                                                    <span class="max-w-[120px] truncate">{{ $doc['filename'] }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-slate-500 text-[11px] italic">No document attached</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    @if($row['report_id'])
                                        <a href="{{ route('production-reports.show', $row['report_id']) }}"
                                           target="_blank"
                                           class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 text-[11px] font-semibold transition">
                                            Inspect &rarr;
                                        </a>
                                    @else
                                        <span class="text-slate-600 text-[11px]">Not created</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Compilation Form to Submit to Somalika --}}
        <form method="POST" action="{{ route('production-reports.monthly.team.store') }}" enctype="multipart/form-data">
            @csrf
            @if($report)
                <input type="hidden" name="report_id" value="{{ $report->id }}">
            @endif
            <input type="hidden" name="reporting_year" value="{{ $year }}">
            <input type="hidden" name="reporting_month" value="{{ $month }}">

            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-300 mb-1">Team Monthly Report Title</label>
                <input type="text" name="title"
                       value="{{ old('title', $report?->title ?? "Team {$teamCode} Monthly Production Report ({$compiled['month_label']})") }}"
                       class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:border-indigo-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">
                        Executive Summary for Supervisor Somalika
                    </label>
                    <textarea name="executive_summary" rows="5"
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-indigo-500">{{ old('executive_summary', $report?->executive_summary ?? $compiled['compiled_texts']['executive_summary']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">
                        Consolidated Team Completed Deliverables
                    </label>
                    <textarea name="tasks_completed" rows="5"
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-indigo-500">{{ old('tasks_completed', $report?->tasks_completed ?? $compiled['compiled_texts']['tasks_completed']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Work in Progress &amp; Carryover Tasks</label>
                    <textarea name="work_in_progress" rows="3"
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-indigo-500">{{ old('work_in_progress', $report?->work_in_progress) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Challenges &amp; Blockers Across Team</label>
                    <textarea name="blockers" rows="3"
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-indigo-500">{{ old('blockers', $report?->blockers ?? $compiled['compiled_texts']['blockers']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Key Team Achievements &amp; Highlights</label>
                    <textarea name="achievements" rows="3"
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-indigo-500">{{ old('achievements', $report?->achievements ?? $compiled['compiled_texts']['achievements']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Next Month's Team Goals &amp; Priorities</label>
                    <textarea name="next_priorities" rows="3" placeholder="Target outputs for next month"
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-indigo-500">{{ old('next_priorities', $report?->next_priorities) }}</textarea>
                </div>
            </div>

            {{-- Performance Rating & Health Status --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-slate-800/60 border border-slate-700 rounded-xl p-4">
                    <label class="block text-xs font-bold text-white uppercase tracking-wider mb-2">
                        Team Monthly Performance Rating (1 to 5) <span class="text-rose-400">*</span>
                    </label>
                    @php $currRating = (int) old('performance_rating', $report?->performance_rating ?? 5); @endphp
                    <div class="grid grid-cols-5 gap-2">
                        @foreach([
                            1 => 'Needs Work',
                            2 => 'Below Target',
                            3 => 'Meets Goals',
                            4 => 'Exceeds',
                            5 => 'Outstanding'
                        ] as $score => $label)
                            <label class="flex flex-col items-center justify-center p-2 rounded-lg bg-slate-900 border border-slate-800 cursor-pointer hover:border-indigo-500 text-center transition">
                                <input type="radio" name="performance_rating" value="{{ $score }}"
                                       {{ $currRating === $score ? 'checked' : '' }} class="text-indigo-600 mb-1">
                                <span class="text-xs font-extrabold text-amber-400">★ {{ $score }}</span>
                                <span class="text-[9.5px] text-slate-400 leading-tight">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="bg-slate-800/60 border border-slate-700 rounded-xl p-4">
                    <label class="block text-xs font-bold text-white uppercase tracking-wider mb-2">
                        Overall Team Production Health
                    </label>
                    @php
                        $savedHealth = is_array($report?->compiled_summary_json) ? ($report->compiled_summary_json['health_status'] ?? 'on_track') : 'on_track';
                        $health = old('health_status', $savedHealth);
                    @endphp
                    <div class="grid grid-cols-3 gap-2">
                        <label class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-900 border border-slate-800 cursor-pointer hover:border-emerald-500">
                            <input type="radio" name="health_status" value="on_track" {{ $health === 'on_track' ? 'checked' : '' }}>
                            <span class="text-xs font-bold text-emerald-400">🟢 On Track</span>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-900 border border-slate-800 cursor-pointer hover:border-amber-500">
                            <input type="radio" name="health_status" value="at_risk" {{ $health === 'at_risk' ? 'checked' : '' }}>
                            <span class="text-xs font-bold text-amber-400">🟡 At Risk</span>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-900 border border-slate-800 cursor-pointer hover:border-rose-500">
                            <input type="radio" name="health_status" value="blocked" {{ $health === 'blocked' ? 'checked' : '' }}>
                            <span class="text-xs font-bold text-rose-400">🔴 Blocked</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Mandatory Monthly Presentation Document Upload (PDF, PPT/PPTX, DOC/DOCX) --}}
            <div class="mb-6 bg-slate-800/60 border-2 border-dashed border-indigo-500/40 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-rose-500/20 text-rose-300 border border-rose-500/30">
                        Mandatory Document
                    </span>
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">
                        Attach Team Monthly Presentation / Document (PDF, PowerPoint, Word)
                    </h4>
                </div>
                <p class="text-xs text-slate-300 mb-3">
                    Submitting the monthly production report requires uploading a presentation or document:
                    <span class="font-bold text-white">PDF (.pdf), PowerPoint (.ppt, .pptx), or Word (.doc, .docx)</span>.
                </p>

                @if($report && $report->media->count() > 0)
                    <div class="mb-3 p-3 rounded-lg bg-slate-900/80 border border-slate-700">
                        <div class="text-[11px] font-bold text-slate-400 uppercase mb-1.5">Currently Attached Files:</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($report->media as $m)
                                <a href="{{ route('production-reports.media.download', $m->id) }}"
                                   target="_blank"
                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-800 text-xs text-indigo-300 font-semibold border border-slate-700">
                                    <span>📎 {{ $m->original_filename ?: $m->title }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <input type="file" name="attachments[]" multiple
                       accept=".pdf,.ppt,.pptx,.doc,.docx"
                       class="block w-full text-xs text-slate-300 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500 cursor-pointer">
            </div>

            <div class="mb-6">
                <label class="block text-xs font-bold text-slate-300 mb-1">Head Remarks to Supervisor Somalika</label>
                <textarea name="head_comments" rows="2" placeholder="Notes, special considerations, or team commendations for the supervisor"
                          class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-indigo-500">{{ old('head_comments', $report?->head_comments) }}</textarea>
            </div>

            @if(!$report || $report->isEditableByAuthor())
                <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="submit" name="action" value="draft"
                            class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold border border-slate-700 transition">
                        Save Team Monthly Draft
                    </button>
                    <button type="submit" name="action" value="submit"
                            onclick="return confirm('Submit consolidated Team {{ $teamCode }} Monthly Report to Supervisor Somalika?')"
                            class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-lg transition">
                        Submit Team Monthly Report to Somalika &rarr;
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>
@endsection

