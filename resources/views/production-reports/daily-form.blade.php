@extends('layouts.app')

@section('title', 'Submit Daily Production Report')

@section('content')
<div class="pr-root pb-6"
     x-data="{
        autosaveStatus: 'Ready',
        externalMedia: [{ title: '', media_type: 'video', platform: 'YouTube', external_url: '' }],
        addExternalMedia() {
            this.externalMedia.push({ title: '', media_type: 'video', platform: 'YouTube', external_url: '' });
        },
        async triggerAutosave() {
            const form = document.getElementById('daily-report-form');
            if (!form) return;
            this.autosaveStatus = 'Saving draft...';
            const fd = new FormData(form);
            fd.set('action', 'draft');
            fd.set('is_autosave', '1');
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd
                });
                const json = await res.json();
                if (json.ok) {
                    this.autosaveStatus = 'Autosaved at ' + json.saved_at;
                }
            } catch (e) {
                this.autosaveStatus = 'Autosave paused';
            }
        }
     }">
    @include('production-reports.partials.nav')

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 mb-6 border-b border-slate-800">
            <div>
                <h2 class="text-lg font-extrabold text-white">Daily Production Report</h2>
                <p class="text-xs text-slate-400">
                    Daily Deadline: <strong class="text-amber-400">Before 5:00 PM Cambodia Time (Asia/Phnom_Penh)</strong>
                    &bull; Current Status:
                    <span class="font-bold text-indigo-300">{{ $report ? $report->status_badge_label : 'Not Started' }}</span>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-400" x-text="autosaveStatus"></span>
                <form method="GET" action="{{ route('production-reports.daily.create') }}" class="flex items-center gap-2">
                    <input type="date" name="date" value="{{ $selectedDate }}" onchange="this.form.submit()"
                           class="bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5">
                </form>
            </div>
        </div>

        @if($report && !$report->isEditableByAuthor())
            <div class="mb-6 p-4 rounded-xl bg-amber-500/15 border border-amber-500/40 text-amber-200 text-xs">
                <strong>Locked Report:</strong> This daily report was already submitted ({{ $report->submitted_at?->timezone('Asia/Phnom_Penh')->format('M d, Y g:i A') }}).
                To preserve audit integrity, submitted reports cannot be silently overwritten. Ask your Production Head or Supervisor to return it for correction if an update is required.
                <a href="{{ route('production-reports.show', $report->id) }}" class="underline font-bold ml-2">View Submitted Report &rarr;</a>
            </div>
        @endif

        @if($report && $report->status === 'returned_for_correction' && $report->returned_reason)
            <div class="mb-6 p-4 rounded-xl bg-rose-500/15 border border-rose-500/40 text-rose-200 text-xs">
                <strong>Returned for Correction:</strong> {{ $report->returned_reason }}
            </div>
        @endif

        <form id="daily-report-form" method="POST" action="{{ route('production-reports.daily.store') }}" enctype="multipart/form-data"
              @change.debounce.4000ms="triggerAutosave()">
            @csrf
            @if($report)
                <input type="hidden" name="report_id" value="{{ $report->id }}">
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">1. Reporting Date *</label>
                    <input type="date" name="reporting_date" value="{{ old('reporting_date', $selectedDate) }}" required
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Project / Production Category</label>
                    <select name="project_category" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
                        @foreach(['Video Production', 'Graphic Design & Posters', 'Motion Graphics', 'Social Media Content', 'Website & UI Assets', 'Quality Control (QC)', 'General Production'] as $cat)
                            <option value="{{ $cat }}" {{ old('project_category', $report?->project_category) === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Report Title (Optional)</label>
                    <input type="text" name="title" value="{{ old('title', $report?->title) }}"
                           placeholder="e.g. Daily Production Report - {{ $user->name }}"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">2. Tasks Completed Today *</label>
                    <textarea name="tasks_completed" rows="4"
                              placeholder="Example:&#10;- Designed 3 promotional posters for Product X&#10;- Edited 2 TikTok vertical reels (60s)"
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">{{ old('tasks_completed', $report?->tasks_completed) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">3. Work in Progress</label>
                    <textarea name="work_in_progress" rows="4"
                              placeholder="Example:&#10;- Color grading main product showcase video (70% done)"
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">{{ old('work_in_progress', $report?->work_in_progress) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">4. Pending Tasks</label>
                    <textarea name="pending_tasks" rows="3"
                              placeholder="Example:&#10;- Waiting for final product photos from studio"
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">{{ old('pending_tasks', $report?->pending_tasks) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">5. Blockers or Obstacles</label>
                    <textarea name="blockers" rows="3"
                              placeholder="Example:&#10;- None, or specify any technical/asset delays"
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">{{ old('blockers', $report?->blockers) }}</textarea>
                </div>
            </div>

            {{-- 6. Links to Published Work or Deliverables --}}
            <div class="mb-5">
                <label class="block text-xs font-bold text-slate-300 mb-1">6. Links to Published Work or Deliverables (One URL per line)</label>
                <textarea name="deliverable_links" rows="3"
                          placeholder="https://drive.google.com/...&#10;https://www.youtube.com/watch?v=..."
                          class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">{{ old('deliverable_links', is_array($report?->deliverable_links) ? implode("\n", array_column($report->deliverable_links, 'url')) : '') }}</textarea>
            </div>


            {{-- 7. Upload Posters, Graphics & Videos --}}
            <div class="mb-5 bg-slate-800/50 border border-dashed border-slate-600 rounded-2xl p-4"
                 x-data="{
                    over: false,
                    names: [],
                    sync() { this.names = Array.from(this.$refs.fileInput.files).map(f => f.name + ' (' + (f.size / 1048576).toFixed(1) + ' MB)'); },
                    drop(e) {
                        this.over = false;
                        const dt = new DataTransfer();
                        Array.from(this.$refs.fileInput.files).forEach(f => dt.items.add(f));
                        Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
                        this.$refs.fileInput.files = dt.files;
                        this.sync();
                    }
                 }">
                <label class="block text-xs font-bold text-white mb-1">
                    7. Upload Posters, Graphics, Videos &amp; Documents (Private Storage)
                </label>
                <p class="text-[11px] text-slate-400 mb-3">
                    Supported formats: JPG, JPEG, PNG, WEBP, PDF, MP4, MOV, WEBM, PPTX, DOCX, XLSX, CSV.
                </p>

                <div @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="drop($event)"
                     @click="$refs.fileInput.click()"
                     :class="over ? 'border-indigo-500 bg-indigo-500/10' : 'border-slate-700'"
                     class="cursor-pointer text-center rounded-xl border-2 border-dashed px-4 py-7 transition">
                    <div class="text-2xl mb-1">📎</div>
                    <div class="text-xs font-bold text-white">Drag &amp; drop files here, or <span class="text-indigo-400 underline">browse</span></div>
                    <div class="text-[11px] text-slate-400 mt-0.5">Up to 100 MB per file</div>
                </div>

                <input type="file" name="attachments[]" multiple x-ref="fileInput" @change="sync()" class="hidden"
                       accept=".jpg,.jpeg,.png,.webp,.pdf,.mp4,.mov,.webm,.pptx,.docx,.xlsx,.csv">

                <ul x-show="names.length" x-cloak class="mt-3 space-y-1">
                    <template x-for="n in names" :key="n">
                        <li class="text-[11px] text-slate-300 flex items-center gap-1.5"><span>✅</span><span x-text="n"></span></li>
                    </template>
                </ul>


                {{-- External Video / Deliverable Links with Metadata --}}
                <div class="mt-4 pt-3 border-t border-slate-700/80">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-slate-300">Add External Video / Media Link (YouTube, Facebook, TikTok, Google Drive)</span>
                        <button type="button" @click="addExternalMedia()" class="text-xs text-indigo-400 hover:text-indigo-300 font-bold">+ Add Media Link</button>
                    </div>
                    <template x-for="(ext, eIdx) in externalMedia" :key="eIdx">
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 mb-2">
                            <input type="text" :name="`external_media[${eIdx}][title]`" x-model="ext.title" placeholder="Media Title"
                                   class="bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white">
                            <select :name="`external_media[${eIdx}][media_type]`" x-model="ext.media_type"
                                    class="bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white">
                                <option value="video">Video</option>
                                <option value="poster">Poster / Graphic</option>
                                <option value="external_link">External Deliverable</option>
                            </select>
                            <select :name="`external_media[${eIdx}][platform]`" x-model="ext.platform"
                                    class="bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white">
                                <option value="YouTube">YouTube</option>
                                <option value="TikTok">TikTok</option>
                                <option value="Facebook">Facebook</option>
                                <option value="Google Drive">Google Drive</option>
                                <option value="Website">Website</option>
                            </select>
                            <input type="url" :name="`external_media[${eIdx}][external_url]`" x-model="ext.external_url" placeholder="https://..."
                                   class="bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white">
                        </div>
                    </template>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-xs font-bold text-slate-300 mb-1">8. Optional Notes</label>
                <textarea name="notes" rows="2" placeholder="Any extra remarks for your Production Head"
                          class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">{{ old('notes', $report?->notes) }}</textarea>
            </div>

            @if(!$report || $report->isEditableByAuthor())
                <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="submit" name="action" value="draft"
                            class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold border border-slate-700">
                        Save Draft
                    </button>
                    <button type="submit" name="action" value="submit"
                            onclick="return confirm('Confirm final submission of this Daily Production Report?')"
                            class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg">
                        Submit Daily Report
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>
@endsection

