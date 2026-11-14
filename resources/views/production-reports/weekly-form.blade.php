@extends('layouts.app')

@section('title', 'Compile Weekly Team Report')

@section('content')
@php
    $savedSummary = is_array($report?->compiled_summary_json) ? $report->compiled_summary_json : [];
    $selectedPosters = old('selected_poster_ids', $savedSummary['selected_poster_media_ids'] ?? []);
    $selectedVideos = old('selected_video_ids', $savedSummary['selected_video_media_ids'] ?? []);
    $currentRating = (int) old('performance_rating', $report?->performance_rating ?? 0);
    $currentHealth = old('health_status', $savedSummary['health_status'] ?? 'on_track');
@endphp

<div class="pr-root pb-6"
     x-data="{ rating: {{ $currentRating }}, health: '{{ $currentHealth }}' }">
    @include('production-reports.partials.nav')

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-4 mb-5 border-b border-slate-800">
            <div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                    WEEKLY TEAM REPORT COMPILER &bull; TEAM {{ $teamCode }}
                </span>
                <h2 class="text-xl font-extrabold text-white mt-1">{{ $compiled['team_name'] }}</h2>
                <p class="text-xs text-slate-400">
                    Weekly Deadline: <strong class="text-amber-400">Every Saturday before 8:00 AM Cambodia Time</strong>
                    ({{ $deadline->format('D, M d, Y g:i A') }})
                </p>
            </div>

            <form method="GET" action="{{ route('production-reports.weekly.create') }}" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="team" value="{{ $teamCode }}">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 mb-0.5">Period Start</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 mb-0.5">Period End</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5">
                </div>
                <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold">
                    Re-Compile Period
                </button>
            </form>
        </div>

        {{-- Section 5: Daily Submission Overview & Missing Reports Alert --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
            <div class="bg-slate-800/70 border border-slate-700 rounded-xl p-3">
                <div class="text-[11px] text-slate-400">Team Members</div>
                <div class="text-lg font-extrabold text-white">{{ $compiled['team_members_count'] }}</div>
            </div>
            <div class="bg-slate-800/70 border border-slate-700 rounded-xl p-3">
                <div class="text-[11px] text-slate-400">Submitted Daily Reports</div>
                <div class="text-lg font-extrabold text-emerald-400">{{ $compiled['actual_submissions'] }} / {{ $compiled['expected_submissions'] }}</div>
            </div>
            <div class="bg-slate-800/70 border border-slate-700 rounded-xl p-3">
                <div class="text-[11px] text-slate-400">Submission Rate</div>
                <div class="text-lg font-extrabold text-indigo-400">{{ $compiled['submission_rate'] }}%</div>
            </div>
            <div class="bg-slate-800/70 border border-slate-700 rounded-xl p-3">
                <div class="text-[11px] text-slate-400">Missing Daily Reports</div>
                <div class="text-lg font-extrabold text-rose-400">{{ $compiled['missing_submissions_count'] }}</div>
            </div>
            <div class="bg-slate-800/70 border border-slate-700 rounded-xl p-3">
                <div class="text-[11px] text-slate-400">Completed Tasks</div>
                <div class="text-lg font-extrabold text-sky-400">{{ $compiled['total_completed_tasks'] }}</div>
            </div>
        </div>

        @if(!empty($compiled['missing_reports']))
            <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-xs text-rose-200">
                <div class="font-bold mb-1">Missing Daily Reports Highlighted ({{ count($compiled['missing_reports']) }}):</div>
                <div class="flex flex-wrap gap-2">
                    @foreach($compiled['missing_reports'] as $mr)
                        <span class="px-2 py-0.5 rounded bg-rose-500/20 border border-rose-500/40">
                            {{ $mr['member_name'] }} — {{ $mr['date_formatted'] }} ({{ $mr['state'] }})
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Individual Member Summary Table --}}
        <div class="mb-6">
            <h3 class="text-sm font-bold text-white mb-2">Individual Member Summary Table (Actual Submitted Data)</h3>
            <div class="overflow-x-auto border border-slate-800 rounded-xl">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-800 text-slate-300 uppercase text-[11px]">
                        <tr>
                            <th class="py-2.5 px-3">Member</th>
                            <th class="py-2.5 px-3 text-right">Completed Tasks</th>
                            <th class="py-2.5 px-3">Work in Progress</th>
                            <th class="py-2.5 px-3">Pending</th>
                            <th class="py-2.5 px-3">Key Achievements</th>
                            <th class="py-2.5 px-3">Daily Reports</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 bg-slate-900/60">
                        @foreach($compiled['member_summary_table'] as $row)
                            <tr>
                                <td class="py-2.5 px-3 font-bold text-white">
                                    {{ $row['member_name'] }}
                                    <div class="text-[11px] text-slate-500 font-normal">{{ $row['role_title'] }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-right font-bold text-emerald-400">
                                    {{ $row['completed_tasks_count'] }}
                                </td>
                                <td class="py-2.5 px-3">{{ \Illuminate\Support\Str::limit($row['work_in_progress'], 90) }}</td>
                                <td class="py-2.5 px-3">{{ \Illuminate\Support\Str::limit($row['pending_tasks'], 90) }}</td>
                                <td class="py-2.5 px-3">{{ \Illuminate\Support\Str::limit($row['key_achievements'], 100) }}</td>
                                <td class="py-2.5 px-3">
                                    {{ $row['submitted_days'] }}/{{ $row['expected_days'] }} days
                                    @if($row['missing_days'] > 0)
                                        <span class="text-rose-400 font-bold">({{ $row['missing_days'] }} missing)</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <form method="POST" action="{{ route('production-reports.weekly.store') }}" enctype="multipart/form-data">
            @csrf
            @if($report)
                <input type="hidden" name="report_id" value="{{ $report->id }}">
            @endif
            <input type="hidden" name="team_code" value="{{ $teamCode }}">
            <input type="hidden" name="reporting_start_date" value="{{ $startDate }}">
            <input type="hidden" name="reporting_end_date" value="{{ $endDate }}">

            <div class="mb-5 p-4 rounded-2xl bg-indigo-500/10 border border-indigo-500/30 text-xs text-slate-300">
                <div class="font-extrabold text-white mb-1">📝 Weekly report guidelines</div>
                <ul class="list-disc list-inside space-y-0.5">
                    <li>Write <strong>results, not activities</strong> — use numbers wherever possible (e.g. “Delivered 12 posters + 4 reels”, “SMM reach 18.4K”).</li>
                    <li>Keep every box to <strong>1–3 short lines</strong>. The system already compiled the daily reports below — edit, don’t retype.</li>
                    <li>Pick the <strong>best posters, graphics and videos</strong> of the week, then give the team a <strong>1–5 rating</strong> before submitting.</li>
                    <li>Only list a <strong>blocker</strong> if it is at risk or needs a decision; leave it empty if there is none.</li>
                </ul>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-300 mb-1">Weekly Report Title</label>
                <input type="text" name="title"
                       value="{{ old('title', $report?->title ?? "Team {$teamCode} Weekly Production Report ({$compiled['period_formatted']})") }}"
                       class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">1. Executive Summary</label>
                    <textarea name="executive_summary" rows="4" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">{{ old('executive_summary', $report?->executive_summary ?? $compiled['compiled_texts']['executive_summary']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">6 &amp; 7. Done This Week — Results &amp; Numbers (not activities)</label>
                    <textarea name="tasks_completed" rows="4" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">{{ old('tasks_completed', $report?->tasks_completed ?? $compiled['compiled_texts']['tasks_completed']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">8. Work in Progress</label>
                    <textarea name="work_in_progress" rows="3" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">{{ old('work_in_progress', $report?->work_in_progress ?? $compiled['compiled_texts']['work_in_progress']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">9. Pending Tasks</label>
                    <textarea name="pending_tasks" rows="3" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">{{ old('pending_tasks', $report?->pending_tasks ?? $compiled['compiled_texts']['pending_tasks']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">10. Blockers / Help Needed <span class="font-normal text-slate-400">(leave blank if none)</span></label>
                    <textarea name="blockers" rows="3" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">{{ old('blockers', $report?->blockers ?? $compiled['compiled_texts']['blockers']) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">13. Top Production Achievements</label>
                    <textarea name="achievements" rows="3" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">{{ old('achievements', $report?->achievements ?? $compiled['compiled_texts']['achievements']) }}</textarea>
                </div>
            </div>

            {{-- Section 11 & 12: Select Best Posters/Graphics & Best Videos from Team Daily Reports --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <h4 class="text-xs font-bold text-white uppercase mb-2">11. Select Best Posters &amp; Graphics ({{ $compiled['candidate_posters']->count() }} Available)</h4>
                    <div class="space-y-2 max-h-56 overflow-y-auto">
                        @forelse($compiled['candidate_posters'] as $poster)
                            <label class="flex items-center gap-2.5 p-2 rounded-lg bg-slate-900/80 border border-slate-800 cursor-pointer">
                                <input type="checkbox" name="selected_poster_ids[]" value="{{ $poster->id }}"
                                       {{ in_array($poster->id, (array) $selectedPosters) ? 'checked' : '' }}
                                       class="rounded border-slate-600 text-indigo-600">
                                <div class="text-xs">
                                    <div class="font-bold text-white">{{ $poster->title }}</div>
                                    <div class="text-[11px] text-slate-400">By {{ $poster->creator?->name ?? 'Member' }} &bull; {{ $poster->project_category }}</div>
                                </div>
                            </label>
                        @empty
                            <p class="text-xs text-slate-500">No posters/graphics uploaded in daily reports for this period.</p>
                        @endforelse
                    </div>
                </div>

                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <h4 class="text-xs font-bold text-white uppercase mb-2">12. Select Best Videos ({{ $compiled['candidate_videos']->count() }} Available)</h4>
                    <div class="space-y-2 max-h-56 overflow-y-auto">
                        @forelse($compiled['candidate_videos'] as $vid)
                            <label class="flex items-center gap-2.5 p-2 rounded-lg bg-slate-900/80 border border-slate-800 cursor-pointer">
                                <input type="checkbox" name="selected_video_ids[]" value="{{ $vid->id }}"
                                       {{ in_array($vid->id, (array) $selectedVideos) ? 'checked' : '' }}
                                       class="rounded border-slate-600 text-indigo-600">
                                <div class="text-xs">
                                    <div class="font-bold text-white">{{ $vid->title }}</div>
                                    <div class="text-[11px] text-slate-400">By {{ $vid->creator?->name ?? 'Member' }} &bull; {{ $vid->platform ?: 'Video' }}</div>
                                </div>
                            </label>
                        @empty
                            <p class="text-xs text-slate-500">No videos uploaded in daily reports for this period.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Weekly health status (On track / At risk / Blocked) --}}
            <div class="mb-6">
                <label class="block text-xs font-extrabold text-slate-300 mb-2 uppercase tracking-wider">Team Health Status *</label>
                <input type="hidden" name="health_status" :value="health">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <button type="button" @click="health = 'on_track'" :class="health === 'on_track' ? 'is-selected' : ''" class="pr-health is-on-track text-left">
                        <div class="text-sm font-extrabold text-white">🟢 On track</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">One-line update only</div>
                    </button>
                    <button type="button" @click="health = 'at_risk'" :class="health === 'at_risk' ? 'is-selected' : ''" class="pr-health is-at-risk text-left">
                        <div class="text-sm font-extrabold text-white">🟡 At risk</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Will be discussed in the meeting</div>
                    </button>
                    <button type="button" @click="health = 'blocked'" :class="health === 'blocked' ? 'is-selected' : ''" class="pr-health is-blocked text-left">
                        <div class="text-sm font-extrabold text-white">🔴 Blocked</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Needs a decision</div>
                    </button>
                </div>
            </div>

            {{-- Section 15: Required Team Performance Rating (1 to 5) --}}
            <div class="mb-6 bg-indigo-500/10 border-2 border-indigo-500/40 rounded-2xl p-5">
                <label class="block text-sm font-extrabold text-white mb-2">
                    15. Team Performance Rating (1 to 5) — Required Before Final Submission *
                </label>
                <input type="hidden" name="performance_rating" :value="rating || ''">
                <div class="grid grid-cols-1 sm:grid-cols-5 gap-2.5">
                    @foreach($ratingLabels as $score => $label)
                        <button type="button" @click="rating = {{ $score }}"
                                :class="rating === {{ $score }} ? 'bg-indigo-600 border-indigo-400 text-white shadow-lg' : 'bg-slate-900 border-slate-700 text-slate-300 hover:bg-slate-800'"
                                class="p-3 rounded-xl border text-left transition">
                            <div class="text-lg font-extrabold">{{ $score }} / 5 ★</div>
                            <div class="text-xs font-semibold mt-0.5">{{ $label }}</div>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">14. Next Week — Top 3 Priorities</label>
                    <textarea name="next_priorities" rows="3" placeholder="1. Finish product video cuts&#10;2. Complete website banner pack&#10;3. SMM schedule setup"
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">{{ old('next_priorities', $report?->next_priorities) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">16. Head of Production's Comments</label>
                    <textarea name="head_comments" rows="3" placeholder="Head assessment of team velocity, quality, and collaboration"
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">{{ old('head_comments', $report?->head_comments) }}</textarea>
                </div>
            </div>

            <div class="mb-6 bg-slate-800/40 border border-dashed border-slate-700 rounded-xl p-4">
                <label class="block text-xs font-bold text-slate-300 mb-1">Upload Additional Weekly Highlight Posters / Videos (Optional)</label>
                <input type="file" name="attachments[]" multiple
                       accept=".jpg,.jpeg,.png,.webp,.pdf,.mp4,.mov,.webm,.pptx,.docx,.xlsx,.csv"
                       class="block w-full text-xs text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white">
            </div>

            @if(!$report || $report->isEditableByAuthor())
                <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="submit" name="action" value="draft"
                            class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold border border-slate-700">
                        Save Weekly Draft
                    </button>
                    <button type="submit" name="action" value="submit"
                            onclick="return confirm('Submit this Weekly Team Production Report? Make sure you have selected a 1–5 Team Performance Rating.')"
                            class="px-6 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold shadow-lg">
                        Submit Weekly Team Report
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>
@endsection

