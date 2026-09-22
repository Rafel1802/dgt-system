@php
    $stats = $data['stats'] ?? [];
    $boardLinks = $data['boardLinks'] ?? [];
    $smmPlanningStats = $data['smmPlanningStats'] ?? [];
    $pendingCards = $data['pendingCards'] ?? collect();
    $totalGraphic = $data['totalGraphic'] ?? 0;
    $totalVideo = $data['totalVideo'] ?? 0;
    $totalListing = $data['totalListing'] ?? 0;
    $totalContent = $data['totalContent'] ?? 0;
    $totalQc = $data['totalQc'] ?? 0;
    $isQc = $data['isQc'] ?? false;
    $isSupervisor = $data['isSupervisor'] ?? false;
    if (!$isSupervisor && (auth()->user()?->isSupervisorOrAdminDigital() || auth()->user()?->isSupervisorRole()) && !$isQc) {
        $isSupervisor = true;
    }
    $overdue = $stats['overdue'] ?? 0;
@endphp

<section id="approval-queue" class="bento-card p-6 sm:p-8 space-y-6">
    {{-- ── Header & Team Counts ─────────────────────────────────────────── --}}
    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-5 pb-6 border-b border-slate-200/70 dark:border-slate-800/80">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider {{ $isSupervisor ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/70 dark:text-amber-300' : ($isQc ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300') }}">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $isSupervisor ? 'bg-amber-400' : ($isQc ? 'bg-indigo-400' : 'bg-slate-400') }} opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 {{ $isSupervisor ? 'bg-amber-600' : ($isQc ? 'bg-indigo-600' : 'bg-slate-600') }}"></span>
                    </span>
                    {{ $isSupervisor ? 'Supervisor Review Station' : ($isQc ? 'QC Review Station' : 'Approval Queue') }}
                </span>
                <span class="text-xs font-bold text-slate-400 dark:text-slate-500">• Live Pipeline</span>
            </div>
            <h3 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                {{ $isSupervisor ? 'Supervisor Approval Queue' : ($isQc ? 'Quality Control & Approval Queue' : 'Workflow Pipeline & Approvals') }}
            </h3>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                {{ $isSupervisor ? 'Live tasks on supervisor list across workflow boards awaiting your final review & approval.' : 'Live task status across workflow boards — drafting, reviews, and approvals.' }}
            </p>
        </div>

        {{-- Team Totals Pills --}}
        <div class="flex flex-wrap items-center gap-2.5 w-full lg:w-auto">
            <div class="px-3.5 py-2 rounded-xl bg-sky-50 dark:bg-sky-950/40 border border-sky-200/60 dark:border-sky-800/50 flex items-center gap-2.5 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                <span class="text-xs font-bold uppercase tracking-wider text-sky-700 dark:text-sky-300">Graphic</span>
                <span class="text-base font-black text-sky-800 dark:text-sky-200 ml-1">{{ $totalGraphic }}</span>
            </div>
            <div class="px-3.5 py-2 rounded-xl bg-violet-50 dark:bg-violet-950/40 border border-violet-200/60 dark:border-violet-800/50 flex items-center gap-2.5 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                <span class="text-xs font-bold uppercase tracking-wider text-violet-700 dark:text-violet-300">Video</span>
                <span class="text-base font-black text-violet-800 dark:text-violet-200 ml-1">{{ $totalVideo }}</span>
            </div>
            <div class="px-3.5 py-2 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/60 dark:border-amber-800/50 flex items-center gap-2.5 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">Listing</span>
                <span class="text-base font-black text-amber-800 dark:text-amber-200 ml-1">{{ $totalListing }}</span>
            </div>
            <div class="px-3.5 py-2 rounded-xl bg-fuchsia-50 dark:bg-fuchsia-950/40 border border-fuchsia-200/60 dark:border-fuchsia-800/50 flex items-center gap-2.5 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-fuchsia-500"></span>
                <span class="text-xs font-bold uppercase tracking-wider text-fuchsia-700 dark:text-fuchsia-300">Content</span>
                <span class="text-base font-black text-fuchsia-800 dark:text-fuchsia-200 ml-1">{{ $totalContent }}</span>
            </div>
            <div class="px-3.5 py-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/60 dark:border-emerald-800/50 flex items-center gap-2.5 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">QC</span>
                <span class="text-base font-black text-emerald-800 dark:text-emerald-200 ml-1">{{ $totalQc }}</span>
            </div>
        </div>
    </div>

    {{-- ── Overdue Banner ───────────────────────────────────────────────── --}}
    @if($overdue > 0)
    <div class="flex items-center gap-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 rounded-2xl px-5 py-3.5 text-rose-700 dark:text-rose-300 text-sm font-semibold shadow-sm">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 flex-shrink-0 text-rose-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
        </svg>
        <span><strong>{{ $overdue }} task{{ $overdue !== 1 ? 's' : '' }}</strong> are overdue across workflow boards — please review and action immediately.</span>
    </div>
    @endif

    {{-- ── Pipeline Stages Grid ─────────────────────────────────────────── --}}
    @if($isSupervisor)
    {{-- Supervisor Exclusive: 2-Column Summary View --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        {{-- Column 1: Supervisor List (Your Queue) --}}
        <div class="rounded-2xl p-6 bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent dark:from-amber-950/40 dark:via-amber-900/20 dark:to-transparent border-2 border-amber-500/80 shadow-md shadow-amber-500/10 flex flex-col justify-between hover:shadow-lg transition-all">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-amber-500 text-white shadow-sm">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-amber-500 text-white">Your Queue</span>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Action Required</span>
                        </div>
                        <h4 class="text-lg font-black text-slate-900 dark:text-white mt-0.5">Supervisor List</h4>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-4xl font-black text-amber-600 dark:text-amber-400">{{ $stats['supervisor_review']['total'] ?? 0 }}</span>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Cards</span>
                </div>
            </div>

            <div class="pt-4 border-t border-amber-200/50 dark:border-amber-900/50">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/50 border border-sky-200/60 dark:border-sky-800/40 text-xs font-bold text-sky-700 dark:text-sky-300">
                        <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                        <span>Graphic:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['supervisor_review']['graphic'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-violet-50 dark:bg-violet-950/50 border border-violet-200/60 dark:border-violet-800/40 text-xs font-bold text-violet-700 dark:text-violet-300">
                        <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                        <span>Video:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['supervisor_review']['video'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/50 border border-amber-200/60 dark:border-amber-800/40 text-xs font-bold text-amber-700 dark:text-amber-300">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span>Listing:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['supervisor_review']['listing'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-fuchsia-50 dark:bg-fuchsia-950/50 border border-fuchsia-200/60 dark:border-fuchsia-800/40 text-xs font-bold text-fuchsia-700 dark:text-fuchsia-300">
                        <span class="w-2 h-2 rounded-full bg-fuchsia-500"></span>
                        <span>Content:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['supervisor_review']['content'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200/60 dark:border-emerald-800/40 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>QC:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['supervisor_review']['qc'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Column 2: QC Review / Approved Pipeline --}}
        <div x-data="{ viewMode: 'qc' }" class="rounded-2xl p-6 bg-white/70 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 flex flex-col justify-between hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="viewMode === 'qc' ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400'">
                        <template x-if="viewMode === 'qc'">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </template>
                        <template x-if="viewMode === 'approved'">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </template>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="viewMode = 'qc'" class="px-2.5 py-1 rounded text-[10px] font-black uppercase tracking-wider transition-colors cursor-pointer" :class="viewMode === 'qc' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 hover:bg-slate-200'">QC Review</button>
                            <button type="button" @click="viewMode = 'approved'" class="px-2.5 py-1 rounded text-[10px] font-black uppercase tracking-wider transition-colors cursor-pointer" :class="viewMode === 'approved' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 hover:bg-slate-200'">Approved</button>
                        </div>
                        <h4 class="text-lg font-black text-slate-900 dark:text-white mt-0.5" x-text="viewMode === 'qc' ? 'QC Review Queue' : 'Completed / Approved'">QC Review Queue</h4>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-4xl font-black text-slate-800 dark:text-white" x-text="viewMode === 'qc' ? '{{ $stats['qc_review']['total'] ?? 0 }}' : '{{ $stats['approved']['total'] ?? 0 }}'">{{ $stats['qc_review']['total'] ?? 0 }}</span>
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Cards</span>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60">
                <div x-show="viewMode === 'qc'" class="flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/50 border border-sky-200/60 dark:border-sky-800/40 text-xs font-bold text-sky-700 dark:text-sky-300">
                        <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                        <span>Graphic:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['qc_review']['graphic'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-violet-50 dark:bg-violet-950/50 border border-violet-200/60 dark:border-violet-800/40 text-xs font-bold text-violet-700 dark:text-violet-300">
                        <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                        <span>Video:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['qc_review']['video'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/50 border border-amber-200/60 dark:border-amber-800/40 text-xs font-bold text-amber-700 dark:text-amber-300">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span>Listing:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['qc_review']['listing'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-fuchsia-50 dark:bg-fuchsia-950/50 border border-fuchsia-200/60 dark:border-fuchsia-800/40 text-xs font-bold text-fuchsia-700 dark:text-fuchsia-300">
                        <span class="w-2 h-2 rounded-full bg-fuchsia-500"></span>
                        <span>Content:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['qc_review']['content'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200/60 dark:border-emerald-800/40 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>QC:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['qc_review']['qc'] ?? 0 }}</span>
                    </div>
                </div>
                <div x-show="viewMode === 'approved'" x-cloak class="flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/50 border border-sky-200/60 dark:border-sky-800/40 text-xs font-bold text-sky-700 dark:text-sky-300">
                        <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                        <span>Graphic:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['approved']['graphic'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-violet-50 dark:bg-violet-950/50 border border-violet-200/60 dark:border-violet-800/40 text-xs font-bold text-violet-700 dark:text-violet-300">
                        <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                        <span>Video:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['approved']['video'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/50 border border-amber-200/60 dark:border-amber-800/40 text-xs font-bold text-amber-700 dark:text-amber-300">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span>Listing:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['approved']['listing'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-fuchsia-50 dark:bg-fuchsia-950/50 border border-fuchsia-200/60 dark:border-fuchsia-800/40 text-xs font-bold text-fuchsia-700 dark:text-fuchsia-300">
                        <span class="w-2 h-2 rounded-full bg-fuchsia-500"></span>
                        <span>Content:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['approved']['content'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200/60 dark:border-emerald-800/40 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>QC:</span>
                        <span class="font-black text-slate-900 dark:text-white ml-0.5">{{ $stats['approved']['qc'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @else
    {{-- ── 4-Stage Pipeline for Other Approvers & QC ─────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        {{-- Drafting --}}
        <div class="rounded-2xl p-5 bg-white/70 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 flex flex-col justify-between hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-slate-100 dark:bg-slate-700/80 text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Stage 1</span>
                        <h4 class="text-base font-black text-slate-800 dark:text-slate-100">Drafting</h4>
                    </div>
                </div>
                <span class="text-3xl font-black text-slate-800 dark:text-white">{{ $stats['drafting']['total'] ?? 0 }}</span>
            </div>
            <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs font-bold text-slate-500 dark:text-slate-400">
                <span title="Graphic">G: {{ $stats['drafting']['graphic'] ?? 0 }}</span>
                <span title="Video">V: {{ $stats['drafting']['video'] ?? 0 }}</span>
                <span title="Listing">L: {{ $stats['drafting']['listing'] ?? 0 }}</span>
                <span title="Content">C: {{ $stats['drafting']['content'] ?? 0 }}</span>
                <span title="QC">Q: {{ $stats['drafting']['qc'] ?? 0 }}</span>
            </div>
        </div>

        {{-- Head Review --}}
        <div class="rounded-2xl p-5 bg-white/70 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 flex flex-col justify-between hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Stage 2</span>
                        <h4 class="text-base font-black text-slate-800 dark:text-slate-100">Head Review</h4>
                    </div>
                </div>
                <span class="text-3xl font-black text-indigo-600 dark:text-indigo-400">{{ $stats['head_review']['total'] ?? 0 }}</span>
            </div>
            <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs font-bold text-slate-500 dark:text-slate-400">
                <span title="Graphic">G: {{ $stats['head_review']['graphic'] ?? 0 }}</span>
                <span title="Video">V: {{ $stats['head_review']['video'] ?? 0 }}</span>
                <span title="Listing">L: {{ $stats['head_review']['listing'] ?? 0 }}</span>
                <span title="Content">C: {{ $stats['head_review']['content'] ?? 0 }}</span>
                <span title="QC">Q: {{ $stats['head_review']['qc'] ?? 0 }}</span>
            </div>
        </div>

        {{-- QC Review (Spotlight for QC User) --}}
        <div class="rounded-2xl p-5 {{ $isQc ? 'bg-gradient-to-br from-indigo-50/90 to-blue-50/70 dark:from-indigo-950/40 dark:to-blue-950/30 border-2 border-indigo-500/80 shadow-md shadow-indigo-500/10' : 'bg-white/70 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60' }} flex flex-col justify-between hover:shadow-lg transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center {{ $isQc ? 'bg-indigo-600 text-white shadow-sm' : 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400' }}">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                    <div>
                        @if($isQc)
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-indigo-600 text-white">Your Queue</span>
                        @else
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Stage 3</span>
                        @endif
                        <h4 class="text-base font-black text-slate-800 dark:text-slate-100">QC Review</h4>
                    </div>
                </div>
                <span class="text-3xl font-black {{ $isQc ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-800 dark:text-white' }}">{{ $stats['qc_review']['total'] ?? 0 }}</span>
            </div>
            <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs font-bold text-slate-500 dark:text-slate-400">
                <span title="Graphic">G: {{ $stats['qc_review']['graphic'] ?? 0 }}</span>
                <span title="Video">V: {{ $stats['qc_review']['video'] ?? 0 }}</span>
                <span title="Listing">L: {{ $stats['qc_review']['listing'] ?? 0 }}</span>
                <span title="Content">C: {{ $stats['qc_review']['content'] ?? 0 }}</span>
                <span title="QC">Q: {{ $stats['qc_review']['qc'] ?? 0 }}</span>
            </div>
        </div>

        {{-- Supervisor Review --}}
        <div class="rounded-2xl p-5 bg-white/70 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 flex flex-col justify-between hover:shadow-lg transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Stage 4</span>
                        <h4 class="text-base font-black text-slate-800 dark:text-slate-100">Supervisor Review</h4>
                    </div>
                </div>
                <span class="text-3xl font-black text-slate-800 dark:text-white">{{ $stats['supervisor_review']['total'] ?? 0 }}</span>
            </div>
            <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs font-bold text-slate-500 dark:text-slate-400">
                <span title="Graphic">G: {{ $stats['supervisor_review']['graphic'] ?? 0 }}</span>
                <span title="Video">V: {{ $stats['supervisor_review']['video'] ?? 0 }}</span>
                <span title="Listing">L: {{ $stats['supervisor_review']['listing'] ?? 0 }}</span>
                <span title="Content">C: {{ $stats['supervisor_review']['content'] ?? 0 }}</span>
                <span title="QC">Q: {{ $stats['supervisor_review']['qc'] ?? 0 }}</span>
            </div>
        </div>
    </div>
    @endif

    {{-- ── 1-Click Board Launchers ─────────────────── --}}
    <div class="p-5 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-700/50">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3.5">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 {{ $isSupervisor ? 'text-amber-500' : 'text-indigo-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>
                <h4 class="text-sm font-black uppercase tracking-wider text-slate-700 dark:text-slate-200">
                    {{ $isSupervisor ? 'Jump Direct to Supervisor Boards' : ($isQc ? 'Jump Direct to QC Boards' : 'Quick Open Workflow Boards') }}
                </h4>
            </div>
            <span class="text-xs font-medium text-slate-400 dark:text-slate-500">
                Click any team board to open cards awaiting your review
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            @php
                $reviewKey = $isSupervisor ? 'supervisor_review' : ($isQc ? 'qc_review' : 'supervisor_review');
            @endphp

            {{-- Graphic --}}
            <a href="{{ !empty($boardLinks['graphic']) ? route('boards.show', $boardLinks['graphic']) : '#' }}"
               target="_blank"
               class="group flex items-center justify-between p-3 rounded-xl bg-white dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700 hover:border-sky-400 dark:hover:border-sky-500 hover:shadow-sm transition-all">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-2.5 h-2.5 rounded-full bg-sky-500 flex-shrink-0"></span>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200 group-hover:text-sky-600 dark:group-hover:text-sky-400 truncate">Graphic</span>
                </div>
                <span class="px-2 py-0.5 rounded-lg text-xs font-black bg-sky-50 text-sky-600 dark:bg-sky-950/60 dark:text-sky-300">
                    {{ $stats[$reviewKey]['graphic'] ?? 0 }}
                </span>
            </a>

            {{-- Video --}}
            <a href="{{ !empty($boardLinks['video']) ? route('boards.show', $boardLinks['video']) : '#' }}"
               target="_blank"
               class="group flex items-center justify-between p-3 rounded-xl bg-white dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700 hover:border-violet-400 dark:hover:border-violet-500 hover:shadow-sm transition-all">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-2.5 h-2.5 rounded-full bg-violet-500 flex-shrink-0"></span>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200 group-hover:text-violet-600 dark:group-hover:text-violet-400 truncate">Video</span>
                </div>
                <span class="px-2 py-0.5 rounded-lg text-xs font-black bg-violet-50 text-violet-600 dark:bg-violet-950/60 dark:text-violet-300">
                    {{ $stats[$reviewKey]['video'] ?? 0 }}
                </span>
            </a>

            {{-- Listing --}}
            <a href="{{ !empty($boardLinks['listing']) ? route('boards.show', $boardLinks['listing']) : '#' }}"
               target="_blank"
               class="group flex items-center justify-between p-3 rounded-xl bg-white dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700 hover:border-amber-400 dark:hover:border-amber-500 hover:shadow-sm transition-all">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 flex-shrink-0"></span>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400 truncate">Listing</span>
                </div>
                <span class="px-2 py-0.5 rounded-lg text-xs font-black bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-300">
                    {{ $stats[$reviewKey]['listing'] ?? 0 }}
                </span>
            </a>

            {{-- Content --}}
            <a href="{{ !empty($boardLinks['content']) ? route('boards.show', $boardLinks['content']) : '#' }}"
               target="_blank"
               class="group flex items-center justify-between p-3 rounded-xl bg-white dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700 hover:border-fuchsia-400 dark:hover:border-fuchsia-500 hover:shadow-sm transition-all">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-2.5 h-2.5 rounded-full bg-fuchsia-500 flex-shrink-0"></span>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200 group-hover:text-fuchsia-600 dark:group-hover:text-fuchsia-400 truncate">Content</span>
                </div>
                <span class="px-2 py-0.5 rounded-lg text-xs font-black bg-fuchsia-50 text-fuchsia-600 dark:bg-fuchsia-950/60 dark:text-fuchsia-300">
                    {{ $stats[$reviewKey]['content'] ?? 0 }}
                </span>
            </a>

            {{-- QC --}}
            <a href="{{ !empty($boardLinks['qc']) ? route('boards.show', $boardLinks['qc']) : '#' }}"
               target="_blank"
               class="group flex items-center justify-between p-3 rounded-xl bg-white dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700 hover:border-emerald-400 dark:hover:border-emerald-500 hover:shadow-sm transition-all">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 flex-shrink-0"></span>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 truncate">QC</span>
                </div>
                <span class="px-2 py-0.5 rounded-lg text-xs font-black bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-300">
                    {{ $stats[$reviewKey]['qc'] ?? 0 }}
                </span>
            </a>
        </div>
    </div>

    {{-- ── Active Pending Review Cards ──────────────────────────────────── --}}
    @if(isset($pendingCards) && $pendingCards->isNotEmpty())
    <div class="space-y-3 pt-2">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 {{ $isSupervisor ? 'text-amber-500' : 'text-slate-500 dark:text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
                <h4 class="text-sm font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                    {{ $isSupervisor ? 'Tasks on Supervisor List' : ($isQc ? 'Tasks Awaiting QC Action' : 'Tasks Awaiting Action') }} ({{ $pendingCards->count() }})
                </h4>
            </div>
            <span class="text-xs font-bold text-slate-400">Click task or Review to auto-open in board</span>
        </div>

        <div class="space-y-2.5 max-h-[550px] overflow-y-auto pr-1 scrollbar-thin">
            @foreach($pendingCards as $card)
            @php
                $cardUrl = $card->board ? route('boards.show', [$card->board->slug, 'card' => $card->id]) : '#';
                
                // Determine Team Label
                $teamName = $card->smm_team_label ?? $card->label;
                if (empty($teamName)) {
                    if (stripos($card->board?->name ?? '', 'Graphic') !== false || stripos($card->board?->workspace?->name ?? '', 'Graphic') !== false) {
                        $teamName = 'Graphic';
                    } elseif (stripos($card->board?->name ?? '', 'Video') !== false || stripos($card->board?->workspace?->name ?? '', 'Video') !== false) {
                        $teamName = 'Video';
                    } elseif (stripos($card->board?->name ?? '', 'Listing') !== false || stripos($card->board?->workspace?->name ?? '', 'Listing') !== false) {
                        $teamName = 'Listing';
                    } elseif (stripos($card->board?->name ?? '', 'Content') !== false || stripos($card->board?->workspace?->name ?? '', 'Content') !== false) {
                        $teamName = 'Content';
                    } elseif (stripos($card->board?->name ?? '', 'QC') !== false || stripos($card->board?->workspace?->name ?? '', 'QC') !== false) {
                        $teamName = 'QC';
                    }
                }

                $teamColor = match(strtolower($teamName ?? '')) {
                    'graphic'    => 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800',
                    'video'      => 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-950/60 dark:text-violet-300 dark:border-violet-800',
                    'listing'    => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
                    'content'    => 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200 dark:bg-fuchsia-950/60 dark:text-fuchsia-300 dark:border-fuchsia-800',
                    'qc', 'text' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
                    default      => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-800',
                };
            @endphp
            <div class="p-3.5 rounded-xl bg-white dark:bg-slate-800/70 border border-slate-200/80 dark:border-slate-700 hover:border-indigo-400 dark:hover:border-indigo-500 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
                <div class="flex items-start gap-2.5 min-w-0 flex-1">
                    {{-- Primary Team Badge --}}
                    @if(!empty($teamName))
                    <span class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider border {{ $teamColor }} shrink-0 mt-0.5 shadow-2xs">
                        {{ $teamName }}
                    </span>
                    @endif

                    {{-- Attached Card Labels / Tags --}}
                    @if($card->relationLoaded('labels') && $card->labels->isNotEmpty())
                        @foreach($card->labels as $lbl)
                            @if(strtolower($lbl->name) !== strtolower($teamName ?? ''))
                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider border shrink-0 mt-0.5"
                                  style="{{ !empty($lbl->color) ? 'background-color: ' . $lbl->color . '20; color: ' . $lbl->color . '; border-color: ' . $lbl->color . '50;' : 'background-color: #f1f5f9; color: #475569; border-color: #cbd5e1;' }}">
                                {{ $lbl->name }}
                            </span>
                            @endif
                        @endforeach
                    @endif

                    <div class="min-w-0 flex-1">
                        <a href="{{ $cardUrl }}"
                           target="_blank"
                           class="text-sm font-bold text-slate-800 dark:text-slate-100 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors line-clamp-1">
                            {{ $card->title }}
                        </a>
                        <div class="flex items-center gap-2 mt-1 text-[11px] font-medium text-slate-400 flex-wrap">
                            <span>{{ $card->board?->name ?? 'Board' }}</span>
                            <span>•</span>
                            <span class="text-indigo-500 dark:text-indigo-400 font-semibold">{{ $card->boardList?->name ?? 'Review' }}</span>
                            @if($card->deadline)
                            <span>•</span>
                            <span class="{{ $card->isOverdue() ? 'text-rose-500 font-bold' : '' }}">
                                Due: {{ \Carbon\Carbon::parse($card->deadline)->format('M d') }}
                            </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 shrink-0 self-end sm:self-center">
                    {{-- Assignees avatars --}}
                    @if($card->assignees->isNotEmpty())
                    <div class="flex -space-x-1.5 overflow-hidden">
                        @foreach($card->assignees->take(3) as $assignee)
                        <img src="{{ $assignee->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($assignee->name).'&size=32' }}"
                             alt="{{ $assignee->name }}"
                             title="{{ $assignee->name }}"
                             class="inline-block h-6 w-6 rounded-full ring-2 ring-white dark:ring-slate-800 object-cover">
                        @endforeach
                    </div>
                    @endif

                    <a href="{{ $cardUrl }}"
                       target="_blank"
                       class="btn btn-secondary text-xs py-1.5 px-3 rounded-lg flex items-center gap-1.5 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-slate-700">
                        <span>Review</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Team Planning Boards (Weekly Breakdown) - AUTO SHOW BY DEFAULT ───────────────────────── --}}
    @if(!empty($smmPlanningStats))
    <div x-data="{ expanded: true }" class="pt-2 border-t border-slate-200/70 dark:border-slate-800/80">
        <button type="button" @click="expanded = !expanded" class="w-full flex items-center justify-between py-2 text-left group cursor-pointer">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-500 group-hover:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                <span class="text-sm font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider">Team Planning Boards (Weekly Progress)</span>
            </div>
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300">
                <span x-text="expanded ? 'Hide weekly details' : 'Show weekly details'"></span>
                <svg class="w-4 h-4 transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </div>
        </button>

        <div x-show="expanded" x-transition class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pt-3">
            @foreach($smmPlanningStats as $key => $teamStat)
            <div class="p-4 rounded-xl bg-slate-50/80 dark:bg-slate-800/60 border border-slate-200/70 dark:border-slate-700/60 space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-200">{{ $teamStat['name'] }}</span>
                    @if(!empty($teamStat['board_slug']))
                    <a href="{{ route('boards.show', $teamStat['board_slug']) }}" target="_blank" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                        Open Board →
                    </a>
                    @endif
                </div>
                <div class="space-y-1.5 text-xs">
                    @foreach($teamStat['weeks'] as $weekName => $weekData)
                    <div class="flex items-center justify-between p-1.5 rounded-lg bg-white/60 dark:bg-slate-900/40">
                        <span class="font-bold text-slate-500 dark:text-slate-400">{{ $weekName }}</span>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-emerald-600 dark:text-emerald-400" title="Approved">✓ {{ $weekData['approved'] }}</span>
                            <span class="font-medium text-slate-400" title="Unapproved">/ {{ $weekData['unapproved'] }} pend</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</section>
