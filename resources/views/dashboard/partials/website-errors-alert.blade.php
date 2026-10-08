@if(isset($websiteErrorsCount) && $websiteErrorsCount > 0 && isset($websiteErrorWebsites) && $websiteErrorWebsites->isNotEmpty())
<section class="space-y-4">
    {{-- Website Errors Banner / Header Card --}}
    <div class="relative overflow-hidden rounded-3xl border-2 border-red-300/80 dark:border-red-700/80 bg-gradient-to-r from-red-50 via-rose-50/60 to-red-50/40 dark:from-red-950/40 dark:via-slate-900/80 dark:to-red-950/30 p-5 sm:p-6 shadow-lg shadow-red-500/5 backdrop-blur-xl">
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-red-500 to-rose-600 text-white flex items-center justify-center shadow-md shadow-red-500/30 shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 animate-pulse">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">
                            Website Errors Requiring Action
                        </h3>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-black bg-red-600 text-white shadow-sm ring-2 ring-red-300 dark:ring-red-900">
                            <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                            {{ $websiteErrorsCount }} {{ Str::plural('Web Error', $websiteErrorsCount) }}
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 mt-0.5">
                        Websites flagged with QC or Supervisor errors. Click any website below to review comments, reference files, and error history.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2.5 w-full sm:w-auto">
                <a href="{{ route('websites.index', ['tab' => 'qc-error']) }}" 
                   class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-extrabold text-xs shadow-md shadow-red-500/20 transition-all hover:scale-[1.02] w-full sm:w-auto">
                    <span>Manage In Website Status</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                    </svg>
                </a>
            </div>
        </div>

        {{-- Grid of Flagged Website Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mt-5">
            @foreach($websiteErrorWebsites as $web)
            @php
                $isQcError = in_array($web->status, [\App\Models\Website::STATUS_QC_ERROR, \App\Models\Website::STATUS_MAINTENANCE_QC_ERROR]);
                $isMaint = in_array($web->status, [\App\Models\Website::STATUS_MAINTENANCE_QC_ERROR, \App\Models\Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR]);
                $targetTab = $isQcError ? 'qc-error' : 'supervisor-error';
                $fixPct = $web->error_progress_percent ?? 0;
                $historyUrl = route('websites.index', [
                    'tab' => $targetTab,
                    'open_history' => $web->id,
                    'website_name' => $web->name,
                    'history_type' => $isMaint ? 'maintenance' : 'build',
                ]);
                $cardUrl = route('websites.index', [
                    'tab' => $targetTab,
                    'highlight' => $web->id,
                ]);
            @endphp
            <div class="group relative flex flex-col justify-between rounded-2xl border-2 border-red-200 dark:border-red-900/50 bg-white/95 dark:bg-slate-900/95 p-4 shadow-sm hover:shadow-md hover:border-red-400 dark:hover:border-red-600 transition-all">
                
                <div>
                    {{-- Header: Logo, Name, Domain & Status Badge --}}
                    <div class="flex items-start gap-3">
                        <a href="{{ $historyUrl }}" class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/40 flex items-center justify-center shrink-0 hover:scale-105 transition-transform" title="Click to view error history">
                            @if($web->logo_path)
                                <img src="{{ $web->logo_src }}" alt="{{ $web->name }}" class="w-8 h-8 object-contain rounded">
                            @else
                                <span class="text-xl">🌐</span>
                            @endif
                        </a>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <a href="{{ $historyUrl }}" class="font-extrabold text-sm text-slate-900 dark:text-white hover:text-red-600 dark:hover:text-red-400 transition-colors truncate block" title="Click to open history & comments">
                                    {{ $web->name }}
                                </a>
                                @if($isMaint)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">
                                        Maint
                                    </span>
                                @endif
                            </div>

                            <a href="{{ $web->url }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] text-indigo-500 hover:text-indigo-700 truncate mt-0.5">
                                <span>{{ $web->clean_domain }}</span>
                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                </svg>
                            </a>
                        </div>

                        {{-- Status Pill --}}
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black shrink-0 {{ $isQcError ? 'bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $isQcError ? 'bg-red-500' : 'bg-rose-500' }} animate-pulse"></span>
                            {{ $isQcError ? 'QC Error' : 'Supervisor Error' }}
                        </span>
                    </div>

                    {{-- Error Note / Comment Preview --}}
                    @if($web->error_note)
                    <div class="mt-3 bg-red-50/70 dark:bg-red-950/30 rounded-xl p-3 border border-red-100 dark:border-red-900/40">
                        <div class="flex items-center justify-between text-[10px] font-bold text-red-600 dark:text-red-400 uppercase tracking-wider mb-1">
                            <span>Error Reason</span>
                            @if($web->error_flagged_at)
                                <span class="font-medium text-slate-400 lowercase">{{ $web->error_flagged_at->diffForHumans() }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-700 dark:text-slate-200 line-clamp-2 leading-relaxed">
                            {{ $web->error_note }}
                        </p>
                        @if($web->error_link)
                            <a href="{{ $web->error_link }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-500 hover:text-indigo-700 mt-1.5">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                <span>Reference link</span>
                            </a>
                        @endif
                    </div>
                    @endif

                    {{-- Handler & Flagger Details --}}
                    <div class="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="text-[11px] text-slate-400">Handled by:</span>
                            <span class="font-bold text-slate-700 dark:text-slate-200 truncate">
                                {{ $web->handler?->name ?? 'Unassigned' }}
                            </span>
                        </div>
                        @if($web->errorFlagger)
                        <div class="flex items-center gap-1 shrink-0 text-[11px] text-slate-400">
                            <span>By:</span>
                            <span class="font-bold text-slate-600 dark:text-slate-300">{{ $web->errorFlagger->name }}</span>
                        </div>
                        @endif
                    </div>

                    {{-- Fix Progress Bar --}}
                    <div class="mt-2.5">
                        <div class="flex items-center justify-between text-[11px] mb-1">
                            <span class="font-bold text-slate-600 dark:text-slate-400">Fix Progress</span>
                            <span class="font-black text-red-600 dark:text-red-400">{{ $fixPct }}%</span>
                        </div>
                        <div class="h-2 bg-red-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-red-500 to-orange-400 transition-all duration-300" style="width: {{ $fixPct }}%"></div>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center gap-2">
                    <a href="{{ $historyUrl }}" 
                       class="inline-flex items-center justify-center gap-1.5 flex-1 px-3 py-1.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-xs shadow-sm shadow-red-500/20 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>View Error & Comments</span>
                    </a>

                    <a href="{{ $cardUrl }}" 
                       class="inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs transition-colors"
                       title="Scroll to website card">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <span>Card</span>
                    </a>
                </div>

            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
