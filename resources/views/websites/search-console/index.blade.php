@extends('layouts.app')

@section('title', 'Website SEO Analytics - Google Search Console')

@push('head')
    <script id="chartjs-cdn" src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
@endpush

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto" x-data="gscDashboard()">

    {{-- ── Flash Notifications ────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 rounded-xl shadow-sm">
            <svg class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <span class="text-sm font-medium">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 flex items-center gap-3 p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 rounded-xl shadow-sm">
            <svg class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 6.75h.008v.008H12v-.008Z" />
            </svg>
            <span class="text-sm font-medium">{{ session('error') }}</span>
        </div>
    @endif

    {{-- ── Connection Notice Banner ──────────────────────────────────────── --}}
    @if(!$isConfigured)
        <div class="mb-6 p-5 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="p-2.5 bg-amber-100 dark:bg-amber-900/50 rounded-xl text-amber-700 dark:text-amber-300">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-amber-900 dark:text-amber-200">Google Search Console OAuth Not Configured</h3>
                    <p class="text-sm text-amber-700 dark:text-amber-400 mt-0.5">Configure your Google Cloud OAuth Client ID and Secret in your deployment environment to activate live synchronization.</p>
                </div>
            </div>
            <button type="button" @click="showSetupGuide = true" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-amber-900 dark:text-amber-100 bg-amber-200/80 hover:bg-amber-300 dark:bg-amber-800 dark:hover:bg-amber-700 rounded-xl transition-colors shrink-0">
                <span>View Setup Instructions</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </button>
        </div>
    @elseif(!$account)
        <div class="mb-6 p-5 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800/60 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="p-2.5 bg-blue-100 dark:bg-blue-900/50 rounded-xl text-blue-700 dark:text-blue-300">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-blue-900 dark:text-blue-200">Google Account Not Connected</h3>
                    <p class="text-sm text-blue-700 dark:text-blue-400 mt-0.5">Authorize your Google account to grant DGT System read-only access to your Search Console properties.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" @click="showSetupGuide = true" class="px-3.5 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 transition-colors">
                    Setup Guide
                </button>
                <a href="{{ route('google.search-console.connect') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-sm transition-colors">
                    <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12.24 10.285V14.4h6.806c-.275 1.765-2.056 5.174-6.806 5.174-4.095 0-7.439-3.389-7.439-7.574s3.345-7.574 7.439-7.574c2.33 0 3.891.989 4.785 1.849l3.254-3.138C18.189 1.186 15.479 0 12.24 0c-6.635 0-12 5.365-12 12s5.365 12 12 12c6.926 0 11.52-4.869 11.52-11.726 0-.788-.085-1.39-.189-1.989H12.24z"/></svg>
                    <span>Connect Google Account</span>
                </a>
            </div>
        </div>
    @elseif($mappedSites->isEmpty())
        <div class="mb-6 p-5 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="p-2.5 bg-amber-100 dark:bg-amber-900/50 rounded-xl text-amber-700 dark:text-amber-300">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 6.75h.008v.008H12v-.008Z" /></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-amber-900 dark:text-amber-200">Google Account Connected, But No Websites Mapped</h3>
                    <p class="text-sm text-amber-700 dark:text-amber-400 mt-0.5">Map your DGT website records to Search Console properties to begin synchronizing keywords and analytics.</p>
                </div>
            </div>
            <button type="button" @click="openManageModal($event)" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-amber-900 dark:text-amber-100 bg-amber-200/80 hover:bg-amber-300 dark:bg-amber-800 dark:hover:bg-amber-700 rounded-xl transition-colors shrink-0">
                <span>Map Properties Now</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </button>
        </div>
    @endif

    {{-- ── Top Navigation & Header ───────────────────────────────────────── --}}
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-blue-500/20">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-900 dark:text-slate-100 tracking-tight">Website SEO Analytics</h1>
                    <div class="flex flex-wrap items-center gap-2 mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        <span>Google Search Console Integration</span>
                        <span>&bull;</span>
                        <span class="inline-flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            {{ count($resolvedWebsiteIds) }} Website{{ count($resolvedWebsiteIds) === 1 ? '' : 's' }} in Scope
                        </span>
                        @if($latestGoogleDataDate)
                            <span>&bull;</span>
                            <span>Latest Google data: <strong class="text-slate-700 dark:text-slate-300">{{ \Carbon\Carbon::parse($latestGoogleDataDate)->format('M d, Y') }}</strong></span>
                        @endif
                        @if($lastSuccessfulSync)
                            <span>&bull;</span>
                            <span>Last synced: <strong class="text-slate-700 dark:text-slate-300">{{ \Carbon\Carbon::parse($lastSuccessfulSync)->diffForHumans() }}</strong></span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap items-center gap-2.5">
            @if(auth()->user()->isSpecialManagerOrAdmin() || auth()->user()->hasAnyRole(['super-admin', 'admin-digital']))
                <button type="button" @click="openManageModal($event)" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm transition-all">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <span>Manage Properties</span>
                </button>
            @endif

            {{-- Export Dropdown --}}
            <div class="relative" x-data="{ openExport: false }">
                <button type="button" @click="openExport = !openExport" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white bg-slate-900 hover:bg-slate-800 dark:bg-blue-600 dark:hover:bg-blue-700 rounded-xl shadow-sm transition-all">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Export Report</span>
                    <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': openExport }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </button>

                <div x-show="openExport" @click.outside="openExport = false" x-cloak class="absolute right-0 mt-2 w-64 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xl py-1.5 z-30 divide-y divide-slate-100 dark:divide-slate-700/60">
                    {{-- Active Sub-Page Report Context Header --}}
                    <div class="px-3.5 py-2 bg-slate-50/80 dark:bg-slate-750/70 flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Current Report</span>
                        <span class="px-2 py-0.5 text-[10.5px] font-bold rounded-lg truncate max-w-[140px]"
                              :class="{
                                  'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300': activeView === 'overview',
                                  'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300': activeView === 'rankings',
                                  'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300': activeView === 'compare',
                                  'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300': activeView === 'sitemaps',
                                  'bg-slate-100 text-slate-700': activeView === 'properties'
                              }"
                              x-text="{
                                  'overview': 'Overview & Keywords',
                                  'rankings': 'Website Rankings',
                                  'compare': 'Compare Sites',
                                  'sitemaps': 'Indexed & Sitemaps',
                                  'properties': 'Properties'
                              }[activeView] || 'SEO Report'">
                        </span>
                    </div>

                    <div class="py-1">
                        <a :href="buildExportUrl('pdf')" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">
                            <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                            <div class="min-w-0">
                                <div class="font-semibold text-slate-800 dark:text-slate-100">Export as PDF (.pdf)</div>
                                <div class="text-[10px] text-slate-400">Printable executive report</div>
                            </div>
                        </a>
                        <a :href="buildExportUrl('excel')" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">
                            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75a1.125 1.125 0 0 0 1.125 1.125h9.75m-9.75 0v-1.125M20.625 19.5a1.125 1.125 0 0 0 1.125-1.125M20.625 19.5h-7.5a1.125 1.125 0 0 1-1.125-1.125m8.625 0V5.625m0 12.75a1.125 1.125 0 0 1-1.125 1.125h-9.75m9.75 0v-1.125" /></svg>
                            <div class="min-w-0">
                                <div class="font-semibold text-slate-800 dark:text-slate-100">Export as Excel (.xlsx)</div>
                                <div class="text-[10px] text-slate-400">Multi-sheet workbook</div>
                            </div>
                        </a>
                        <a :href="buildExportUrl('csv')" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">
                            <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                            <div class="min-w-0">
                                <div class="font-semibold text-slate-800 dark:text-slate-100">Export Table as CSV (.csv)</div>
                                <div class="text-[10px] text-slate-400">Fast raw data export</div>
                            </div>
                        </a>
                    </div>
                    <div class="py-1">
                        <button type="button" @click="openExport = false; openExportModal()" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40 transition-colors w-full text-left">
                            <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                            <span>Advanced Filter &amp; Export...</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Setup Guide Button --}}
            <button type="button" @click="showSetupGuide = true" class="p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="Google Cloud Setup Guide">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
            </button>
        </div>
    </div>

    {{-- ── Main View Navigation Tabs ────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-3">
        <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-800/80 rounded-2xl border border-slate-200/80 dark:border-slate-700/60 shadow-inner overflow-x-auto max-w-full">
            <button type="button" @click="setView('overview')"
                    class="flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition-all whitespace-nowrap"
                    :class="activeView === 'overview' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100'">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.143 2.143L15 7.5" />
                </svg>
                <span>Overview & Analytics</span>
            </button>

            <button type="button" @click="setView('rankings')"
                    class="flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition-all whitespace-nowrap"
                    :class="activeView === 'rankings' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100'">
                <span class="text-amber-500 font-black">🏆</span>
                <span>Website Rankings</span>
                <span class="px-1.5 py-0.5 text-[10px] font-extrabold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                    Leaderboard
                </span>
            </button>

            <button type="button" @click="setView('compare')"
                    class="flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition-all whitespace-nowrap"
                    :class="activeView === 'compare' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100'">
                <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                </svg>
                <span>Compare Websites</span>
            </button>

            <button type="button" @click="setView('sitemaps')"
                    class="flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition-all whitespace-nowrap"
                    :class="activeView === 'sitemaps' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100'">
                <svg class="w-4 h-4 text-purple-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                </svg>
                <span>Indexed & Sitemaps</span>
                <span class="px-1.5 py-0.5 text-[10px] font-extrabold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300">
                    Live GSC
                </span>
            </button>

            @if(auth()->user()->isSpecialManagerOrAdmin() || auth()->user()->hasAnyRole(['super-admin', 'admin-digital']))
            <button type="button" @click="setView('properties')"
                    class="flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition-all whitespace-nowrap"
                    :class="activeView === 'properties' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100'">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <span>Property Manager & Batch Add</span>
            </button>
            @endif
        </div>

        {{-- Active View Helper Label --}}
        <div class="hidden sm:flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span class="w-2 h-2 rounded-full" :class="{
                'bg-blue-500': activeView === 'overview',
                'bg-amber-500': activeView === 'rankings',
                'bg-indigo-500': activeView === 'compare',
                'bg-purple-500': activeView === 'sitemaps',
                'bg-emerald-500': activeView === 'properties'
            }"></span>
            <span class="font-semibold text-slate-700 dark:text-slate-300" x-text="{
                'overview': 'Dashboard & Keyword Analytics',
                'rankings': 'Leaderboard: Ranked by Most Impressions',
                'compare': 'Web-to-Web Performance Comparison',
                'sitemaps': 'Indexed vs Not Indexed Pages & Live Sitemap Submissions',
                'properties': 'Bulk Property Auto-Mapper & Synchronizer'
            }[activeView]"></span>
        </div>
    </div>

    {{-- ── VIEW 1: Overview & Keyword Analytics ───────────────────────────── --}}
    <div x-show="activeView === 'overview'" style="{{ ($currentView ?? 'overview') === 'overview' ? '' : 'display: none;' }}">

    {{-- ── Filter Control Bar ─────────────────────────────────────────────── --}}
    <div class="mb-6 p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3.5">

            {{-- 1. Date Range Preset --}}
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Date Range</label>
                <div class="relative">
                    <select x-model="filters.range" @change="onFilterChange()" class="w-full text-xs font-medium bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                        <option value="this_week">This Week (vs Last Week)</option>
                        <option value="last_week">Last Week (vs Prior Week)</option>
                        <option value="7d">Last 7 Days (vs Prior 7 Days)</option>
                        <option value="28d">Last 28 Days (Default)</option>
                        <option value="this_month">This Month (vs Prior Month)</option>
                        <option value="prev_month">Previous Month (vs 2 Months Ago)</option>
                        <option value="quarter">Last 90 Days / Quarter</option>
                        <option value="custom">Custom Date Range...</option>
                    </select>
                </div>
            </div>

            {{-- 2. Class / Category Filter --}}
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Class / Category</label>
                <select x-model="filters.class" @change="onClassChange()" class="w-full text-xs font-medium bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                    <option value="all">All Classes</option>
                    <template x-for="cls in availableClasses" :key="cls">
                        <option :value="cls" x-text="cls"></option>
                    </template>
                </select>
            </div>

            {{-- 3. Multi-Select Website Scope Filter with Class Separation & User Avatars --}}
            <div class="relative" x-data="{ openWebsiteDropdown: false, websiteSearch: '' }">
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        Website Scope
                    </label>
                    <template x-if="selectedWebsites.length > 0">
                        <button type="button" @click="clearWebsites()" class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                            Reset to All
                        </button>
                    </template>
                </div>

                {{-- Trigger Button --}}
                <button type="button" @click="openWebsiteDropdown = !openWebsiteDropdown" class="w-full text-left text-xs font-medium bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none flex items-center justify-between gap-2 transition-all">
                    <div class="flex items-center gap-1.5 truncate">
                        <template x-if="selectedWebsites.length === 0">
                            <span class="text-slate-700 dark:text-slate-300 font-semibold flex items-center gap-1.5 truncate">
                                <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0"></span>
                                <span x-text="filters.class !== 'all' ? (filters.class + ' (All Sites)') : 'All Authorized Websites'"></span>
                            </span>
                        </template>
                        <template x-if="selectedWebsites.length === 1">
                            <span class="text-slate-900 dark:text-white font-semibold truncate flex items-center gap-1.5" x-text="getSingleWebsiteName()"></span>
                        </template>
                        <template x-if="selectedWebsites.length > 1">
                            <span class="inline-flex items-center gap-1.5 text-blue-700 dark:text-blue-300 font-semibold truncate">
                                <span class="px-1.5 py-0.5 rounded-md bg-blue-100 dark:bg-blue-900/60 text-blue-800 dark:text-blue-200 text-[10px] font-bold" x-text="selectedWebsites.length + ' Selected'"></span>
                                Combined Portfolio
                            </span>
                        </template>
                    </div>
                    <svg class="w-4 h-4 text-slate-400 transition-transform shrink-0" :class="{ 'rotate-180': openWebsiteDropdown }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                {{-- Multi-Select Dropdown Panel --}}
                <div x-show="openWebsiteDropdown" @click.outside="openWebsiteDropdown = false" x-cloak class="absolute left-0 mt-2 w-80 sm:w-96 bg-white dark:bg-slate-850 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-2xl z-40 p-3.5 space-y-3">
                    
                    {{-- Search Input --}}
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <input type="text" x-model="websiteSearch" placeholder="Search website, domain, or handler..." class="w-full text-xs pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 outline-none focus:border-blue-500">
                    </div>

                    {{-- Class Quick Filter Pills --}}
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-[11px] scrollbar-none">
                        <button type="button" @click="classFilter = 'all'; filters.class = 'all'; onFilterChange()" class="px-2.5 py-1 rounded-lg font-medium transition-colors shrink-0" :class="filters.class === 'all' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'">
                            All Classes
                        </button>
                        <template x-for="cls in availableClasses" :key="cls">
                            <button type="button" @click="classFilter = cls; filters.class = cls; onFilterChange()" class="px-2.5 py-1 rounded-lg font-medium transition-colors shrink-0" :class="filters.class === cls ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'" x-text="cls">
                            </button>
                        </template>
                    </div>

                    {{-- Quick Action Buttons --}}
                    <div class="flex items-center justify-between text-[11px] pt-1.5 border-t border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="selectAllWebsites()" class="text-blue-600 dark:text-blue-400 font-semibold hover:underline">
                                Select All (<span x-text="allWebsites.length"></span>)
                            </button>
                            <span class="text-slate-300 dark:text-slate-700">&bull;</span>
                            <button type="button" @click="selectMappedWebsites()" class="text-emerald-600 dark:text-emerald-400 font-semibold hover:underline">
                                Mapped Only
                            </button>
                        </div>
                        <button type="button" @click="clearWebsites()" class="text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:underline">
                            Clear
                        </button>
                    </div>

                    {{-- Websites List Grouped by Class --}}
                    <div class="max-h-64 overflow-y-auto space-y-3 pr-1 divide-y divide-slate-100 dark:divide-slate-800/60">
                        <template x-for="(group, className) in groupedWebsites" :key="className">
                            <div class="pt-2 first:pt-0" x-show="group.length > 0">
                                {{-- Class Header --}}
                                <div class="flex items-center justify-between mb-1.5 px-1">
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400" x-text="className + ' (' + group.length + ')'"></span>
                                    <button type="button" @click="toggleSelectClass(group)" class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold hover:underline">
                                        Toggle Class
                                    </button>
                                </div>

                                {{-- Websites in Class --}}
                                <div class="space-y-1">
                                    <template x-for="w in group" :key="w.id">
                                        <label class="flex items-center justify-between gap-2.5 p-2 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer transition-colors" :class="{ 'bg-blue-50/70 dark:bg-blue-950/40 border border-blue-200/60 dark:border-blue-800/60': selectedWebsites.includes(w.id) }">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <input type="checkbox" :value="w.id" :checked="selectedWebsites.includes(w.id)" @change="toggleWebsite(w.id)" class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-slate-300 dark:border-slate-700 dark:bg-slate-900 cursor-pointer shrink-0">
                                                <div class="min-w-0">
                                                    <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate" x-text="w.name"></div>
                                                    <div class="text-[11px] text-slate-400 dark:text-slate-500 truncate" x-text="w.domain"></div>
                                                </div>
                                            </div>

                                            {{-- Handler Profile Avatar --}}
                                            <div class="flex items-center gap-1.5 shrink-0">
                                                <template x-if="w.is_mapped">
                                                    <span class="w-2 h-2 rounded-full bg-emerald-500" title="Connected to Google Search Console"></span>
                                                </template>
                                                <template x-if="w.handler">
                                                    <div class="flex items-center gap-1.5" :title="'Assigned to: ' + w.handler.name">
                                                        <img :src="w.handler.avatar_url" :alt="w.handler.name" class="w-5 h-5 rounded-full object-cover border border-slate-200 dark:border-slate-700">
                                                        <span class="text-[10px] text-slate-600 dark:text-slate-300 font-medium hidden sm:inline" x-text="w.handler.name.split(' ')[0]"></span>
                                                    </div>
                                                </template>
                                                <template x-if="!w.handler">
                                                    <span class="text-[10px] text-slate-400 italic">Unassigned</span>
                                                </template>
                                            </div>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Bottom Apply / Close Button --}}
                    <div class="pt-2.5 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <span class="text-[11px] font-medium text-slate-600 dark:text-slate-300" x-text="selectedWebsites.length + ' website(s) selected'"></span>
                        <button type="button" @click="applyWebsitesFilter(); openWebsiteDropdown = false" class="px-4 py-1.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-sm transition-all">
                            Apply Filter
                        </button>
                    </div>
                </div>
            </div>

            {{-- 4. Team Member Filter --}}
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Responsible Member</label>
                <select x-model="filters.members" @change="onFilterChange()" class="w-full text-xs font-medium bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                    <option value="">All Team Members</option>
                    @foreach($teamMembers as $m)
                        <option value="{{ $m->id }}">{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 5. Active Period Display / Quick Badge with Comparison Dates --}}
            <div class="flex flex-col justify-end">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Active Period</span>
                    <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold truncate max-w-[120px]" x-text="dateRange.label || ''"></span>
                </div>
                <div class="flex items-center gap-2 px-3 py-1.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300">
                    <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" /></svg>
                    <div class="truncate">
                        <div class="truncate" x-text="dateRange.start + ' to ' + dateRange.end">{{ $dateRange['start'] }} to {{ $dateRange['end'] }}</div>
                        <div class="text-[10px] text-slate-400 dark:text-slate-500 font-normal truncate" x-text="'vs ' + dateRange.prev_start + ' to ' + dateRange.prev_end"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Custom Date Range Pickers (shown when range === 'custom') --}}
        <div x-show="filters.range === 'custom'" x-cloak class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/60 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Custom Start Date</label>
                <input type="date" x-model="filters.start_date" @change="onFilterChange()" class="w-full text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Custom End Date</label>
                <input type="date" x-model="filters.end_date" @change="onFilterChange()" class="w-full text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 outline-none">
            </div>
            <div class="sm:col-span-2 text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                <span>Google Search Console growth comparisons are calculated against the immediately preceding period of equal length.</span>
            </div>
        </div>
    </div>

    {{-- ── 4 Primary KPI Cards ────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        {{-- KPI 1: Total Clicks --}}
        <div class="p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Clicks</span>
                <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672 13.684 16.6m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672ZM12 2.25V4.5m5.834.166-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243-1.59-1.59" /></svg>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-3xl font-black text-slate-900 dark:text-slate-100 tracking-tight" x-text="formatNumber(kpi.clicks.current)">
                    {{ number_format($kpiSummary['clicks']['current']) }}
                </div>
                <div class="flex items-center gap-1.5 mt-2 text-xs">
                    <span :class="kpi.clicks.trend === 'up' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-rose-600 dark:text-rose-400 font-bold'" class="inline-flex items-center gap-0.5">
                        <template x-if="kpi.clicks.trend === 'up'">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" /></svg>
                        </template>
                        <template x-if="kpi.clicks.trend === 'down'">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" /></svg>
                        </template>
                        <span x-text="(kpi.clicks.diff >= 0 ? '+' : '') + kpi.clicks.percent + '%'">{{ ($kpiSummary['clicks']['diff'] >= 0 ? '+' : '') . $kpiSummary['clicks']['percent'] }}%</span>
                    </span>
                    <span class="text-slate-400 dark:text-slate-500">vs prev <span x-text="formatNumber(kpi.clicks.previous)">{{ number_format($kpiSummary['clicks']['previous']) }}</span></span>
                </div>
            </div>
        </div>

        {{-- KPI 2: Total Impressions --}}
        <div class="p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Impressions</span>
                <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-3xl font-black text-slate-900 dark:text-slate-100 tracking-tight" x-text="formatNumber(kpi.impressions.current)">
                    {{ number_format($kpiSummary['impressions']['current']) }}
                </div>
                <div class="flex items-center gap-1.5 mt-2 text-xs">
                    <span :class="kpi.impressions.trend === 'up' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-rose-600 dark:text-rose-400 font-bold'" class="inline-flex items-center gap-0.5">
                        <template x-if="kpi.impressions.trend === 'up'">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" /></svg>
                        </template>
                        <template x-if="kpi.impressions.trend === 'down'">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" /></svg>
                        </template>
                        <span x-text="(kpi.impressions.diff >= 0 ? '+' : '') + kpi.impressions.percent + '%'">{{ ($kpiSummary['impressions']['diff'] >= 0 ? '+' : '') . $kpiSummary['impressions']['percent'] }}%</span>
                    </span>
                    <span class="text-slate-400 dark:text-slate-500">vs prev <span x-text="formatNumber(kpi.impressions.previous)">{{ number_format($kpiSummary['impressions']['previous']) }}</span></span>
                </div>
            </div>
        </div>

        {{-- KPI 3: Average CTR --}}
        <div class="p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Average CTR</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605" /></svg>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-3xl font-black text-slate-900 dark:text-slate-100 tracking-tight">
                    <span x-text="kpi.ctr.current.toFixed(2)">{{ number_format($kpiSummary['ctr']['current'], 2) }}</span>%
                </div>
                <div class="flex items-center gap-1.5 mt-2 text-xs">
                    <span :class="kpi.ctr.trend === 'up' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-rose-600 dark:text-rose-400 font-bold'" class="inline-flex items-center gap-0.5">
                        <template x-if="kpi.ctr.trend === 'up'">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" /></svg>
                        </template>
                        <template x-if="kpi.ctr.trend === 'down'">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" /></svg>
                        </template>
                        <span x-text="(kpi.ctr.diff >= 0 ? '+' : '') + kpi.ctr.diff + '%'">{{ ($kpiSummary['ctr']['diff'] >= 0 ? '+' : '') . $kpiSummary['ctr']['diff'] }}%</span>
                    </span>
                    <span class="text-slate-400 dark:text-slate-500">vs prev <span x-text="kpi.ctr.previous.toFixed(2)">{{ number_format($kpiSummary['ctr']['previous'], 2) }}</span>%</span>
                </div>
            </div>
        </div>

        {{-- KPI 4: Average Position --}}
        <div class="p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Average Position</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871a2.25 2.25 0 0 0-1.848.972l-.707 1.06c-.347.52-.947.838-1.58.838H8.25m6.75-9.75v-1.5a2.25 2.25 0 0 0-2.25-2.25h-1.5A2.25 2.25 0 0 0 9 5.25v1.5m6.75 0h-7.5" /></svg>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-3xl font-black text-slate-900 dark:text-slate-100 tracking-tight" x-text="kpi.position.current.toFixed(1)">
                    {{ number_format($kpiSummary['position']['current'], 1) }}
                </div>
                <div class="flex items-center gap-1.5 mt-2 text-xs">
                    {{-- Lower position is better in Google! --}}
                    <span :class="kpi.position.trend === 'up' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-rose-600 dark:text-rose-400 font-bold'" class="inline-flex items-center gap-0.5">
                        <template x-if="kpi.position.trend === 'up'">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" /></svg>
                        </template>
                        <template x-if="kpi.position.trend === 'down'">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" /></svg>
                        </template>
                        <span x-text="(kpi.position.diff > 0 ? '+' : '') + kpi.position.diff + ' pos'">{{ ($kpiSummary['position']['diff'] > 0 ? '+' : '') . $kpiSummary['position']['diff'] }} pos</span>
                    </span>
                    <span class="text-slate-400 dark:text-slate-500">vs prev <span x-text="kpi.position.previous.toFixed(1)">{{ number_format($kpiSummary['position']['previous'], 1) }}</span></span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Interactive Performance Graph ─────────────────────────────────── --}}
    <div class="mb-6 p-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">Performance Over Time</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Daily search performance trends from Google Search Console</p>
            </div>

            {{-- Metric Switcher Pills --}}
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="setChartMetric('clicks_impressions')" :class="activeMetric === 'clicks_impressions' ? 'bg-blue-600 text-white font-bold' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-medium hover:bg-slate-200'" class="px-3 py-1.5 rounded-xl text-xs transition-all">
                    Clicks & Impressions
                </button>
                <button type="button" @click="setChartMetric('ctr')" :class="activeMetric === 'ctr' ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-medium hover:bg-slate-200'" class="px-3 py-1.5 rounded-xl text-xs transition-all">
                    Click-Through Rate (CTR)
                </button>
                <button type="button" @click="setChartMetric('position')" :class="activeMetric === 'position' ? 'bg-amber-600 text-white font-bold' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-medium hover:bg-slate-200'" class="px-3 py-1.5 rounded-xl text-xs transition-all">
                    Average Position
                </button>
            </div>
        </div>

        {{-- Chart Canvas --}}
        <div class="relative w-full h-[320px]" style="min-height: 320px; height: 320px; position: relative;">
            <canvas id="gscPerformanceChart"></canvas>
            <div x-show="loading" class="absolute inset-0 bg-white/70 dark:bg-slate-800/70 backdrop-blur-xs flex items-center justify-center rounded-xl z-10" x-cloak>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                    <svg class="w-5 h-5 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span>Updating data...</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Detailed Data Tabs ─────────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden">
        {{-- Tab Headers --}}
        <div class="border-b border-slate-200 dark:border-slate-700 px-6 pt-4 flex flex-wrap gap-2">
            <button type="button" @click="activeTab = 'keywords'" :class="activeTab === 'keywords' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium'" class="pb-3 px-3 text-xs border-b-2 flex items-center gap-2 transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                <span>Search Keywords</span>
                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300" x-text="keywordsTotal">{{ number_format($keywords['total']) }}</span>
            </button>

            <button type="button" @click="activeTab = 'pages'" :class="activeTab === 'pages' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium'" class="pb-3 px-3 text-xs border-b-2 flex items-center gap-2 transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                <span>Top Pages</span>
                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300" x-text="topPagesTotal">{{ number_format($topPages['total']) }}</span>
            </button>

            <button type="button" @click="activeTab = 'countries'" :class="activeTab === 'countries' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium'" class="pb-3 px-3 text-xs border-b-2 flex items-center gap-2 transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253M3 12a8.959 8.959 0 0 0 .284 2.253" /></svg>
                <span>Countries</span>
            </button>

            <button type="button" @click="activeTab = 'devices'" :class="activeTab === 'devices' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium'" class="pb-3 px-3 text-xs border-b-2 flex items-center gap-2 transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
                <span>Devices</span>
            </button>

            <button type="button" @click="activeTab = 'daily'" :class="activeTab === 'daily' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium'" class="pb-3 px-3 text-xs border-b-2 flex items-center gap-2 transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" /></svg>
                <span>Daily Performance</span>
            </button>

            <template x-if="websiteBreakdown.length > 0">
                <button type="button" @click="activeTab = 'breakdown'" :class="activeTab === 'breakdown' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium'" class="pb-3 px-3 text-xs border-b-2 flex items-center gap-2 transition-all">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" /></svg>
                    <span>Website Comparison</span>
                    <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300" x-text="websiteBreakdown.length"></span>
                </button>
            </template>
        </div>

        {{-- ── TAB 1: Search Keywords ────────────────────────────────────── --}}
        <div x-show="activeTab === 'keywords'" class="p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                <div class="relative w-full sm:w-72">
                    <input type="text" x-model="keywordSearch" @input.debounce.400ms="fetchKeywords()" placeholder="Filter search queries..." class="w-full text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl pl-9 pr-4 py-2 text-slate-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                    <span>Click any query to view daily trend & landing page details</span>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-3 px-4">Search Query / Keyword</th>
                            <th class="py-3 px-4 text-right cursor-pointer hover:text-blue-600" @click="sortKeywords('clicks')">
                                Clicks <span x-show="keywordSort === 'clicks'" x-text="keywordDir === 'asc' ? '▲' : '▼'"></span>
                            </th>
                            <th class="py-3 px-4 text-right cursor-pointer hover:text-blue-600" @click="sortKeywords('impressions')">
                                Impressions <span x-show="keywordSort === 'impressions'" x-text="keywordDir === 'asc' ? '▲' : '▼'"></span>
                            </th>
                            <th class="py-3 px-4 text-right cursor-pointer hover:text-blue-600" @click="sortKeywords('ctr')">
                                CTR <span x-show="keywordSort === 'ctr'" x-text="keywordDir === 'asc' ? '▲' : '▼'"></span>
                            </th>
                            <th class="py-3 px-4 text-right cursor-pointer hover:text-blue-600" @click="sortKeywords('position')">
                                Position <span x-show="keywordSort === 'position'" x-text="keywordDir === 'asc' ? '▲' : '▼'"></span>
                            </th>
                            <th class="py-3 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <template x-for="kw in keywordsList" :key="kw.query">
                            <tr @click="inspectKeyword(kw.query)" class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 cursor-pointer transition-colors">
                                <td class="py-3 px-4 font-semibold text-slate-800 dark:text-slate-100" x-text="kw.query"></td>
                                <td class="py-3 px-4 text-right font-medium text-slate-700 dark:text-slate-300" x-text="formatNumber(kw.clicks)"></td>
                                <td class="py-3 px-4 text-right font-medium text-slate-700 dark:text-slate-300" x-text="formatNumber(kw.impressions)"></td>
                                <td class="py-3 px-4 text-right font-medium text-emerald-600 dark:text-emerald-400" x-text="kw.ctr.toFixed(2) + '%'"></td>
                                <td class="py-3 px-4 text-right font-bold text-amber-600 dark:text-amber-400" x-text="kw.position.toFixed(1)"></td>
                                <td class="py-3 px-4 text-center" @click.stop>
                                    <button type="button" @click="inspectKeyword(kw.query)" class="p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-50 dark:hover:bg-blue-900/40 rounded-lg transition-colors" title="Inspect Keyword Trend">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="keywordsList.length === 0">
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 dark:text-slate-500">
                                    No keyword data found for the selected period and scope.
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Keyword Pagination --}}
            <div class="mt-4 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <div>
                    Showing page <span class="font-bold text-slate-700 dark:text-slate-300" x-text="keywordsCurrentPage"></span> of <span class="font-bold text-slate-700 dark:text-slate-300" x-text="keywordsPages"></span> (<span x-text="keywordsTotal"></span> total queries)
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="changeKeywordPage(keywordsCurrentPage - 1)" :disabled="keywordsCurrentPage <= 1" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-40 transition-colors">Previous</button>
                    <button type="button" @click="changeKeywordPage(keywordsCurrentPage + 1)" :disabled="keywordsCurrentPage >= keywordsPages" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-40 transition-colors">Next</button>
                </div>
            </div>

            <div class="mt-3 text-[11px] text-slate-400 dark:text-slate-500">
                Note: In accordance with Google Search Console policies, search queries are anonymized by Google when search volumes are low to protect user privacy.
            </div>
        </div>

        {{-- ── TAB 2: Top Pages ──────────────────────────────────────────── --}}
        <div x-show="activeTab === 'pages'" x-cloak class="p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                <div class="relative w-full sm:w-72">
                    <input type="text" x-model="pageSearch" @input.debounce.400ms="fetchPages()" placeholder="Filter landing pages..." class="w-full text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl pl-9 pr-4 py-2 text-slate-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-3 px-4">Landing Page URL</th>
                            <th class="py-3 px-4 text-right">Clicks</th>
                            <th class="py-3 px-4 text-right">Impressions</th>
                            <th class="py-3 px-4 text-right">CTR</th>
                            <th class="py-3 px-4 text-right">Position</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <template x-for="pg in topPagesList" :key="pg.page">
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition-colors">
                                <td class="py-3 px-4">
                                    <a :href="pg.page" target="_blank" rel="noopener noreferrer" class="font-mono text-blue-600 hover:underline inline-flex items-center gap-1.5 truncate max-w-xl">
                                        <span x-text="pg.page"></span>
                                        <svg class="w-3.5 h-3.5 shrink-0 opacity-60" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                    </a>
                                </td>
                                <td class="py-3 px-4 text-right font-medium text-slate-700 dark:text-slate-300" x-text="formatNumber(pg.clicks)"></td>
                                <td class="py-3 px-4 text-right font-medium text-slate-700 dark:text-slate-300" x-text="formatNumber(pg.impressions)"></td>
                                <td class="py-3 px-4 text-right font-medium text-emerald-600 dark:text-emerald-400" x-text="pg.ctr.toFixed(2) + '%'"></td>
                                <td class="py-3 px-4 text-right font-bold text-amber-600 dark:text-amber-400" x-text="pg.position.toFixed(1)"></td>
                            </tr>
                        </template>
                        <template x-if="topPagesList.length === 0">
                            <tr><td colspan="5" class="py-8 text-center text-slate-400">No page performance data recorded.</td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── TAB 3: Countries ──────────────────────────────────────────── --}}
        <div x-show="activeTab === 'countries'" x-cloak class="p-6">
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-3 px-4">Country</th>
                            <th class="py-3 px-4 text-right">Clicks</th>
                            <th class="py-3 px-4 text-right">Impressions</th>
                            <th class="py-3 px-4 text-right">CTR</th>
                            <th class="py-3 px-4 text-right">Position</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <template x-for="c in countriesList" :key="c.country_code">
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition-colors">
                                <td class="py-3 px-4 font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                    <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-[10px] font-mono text-slate-600 dark:text-slate-300" x-text="c.country_code"></span>
                                    <span x-text="c.country_name"></span>
                                </td>
                                <td class="py-3 px-4 text-right font-medium text-slate-700 dark:text-slate-300" x-text="formatNumber(c.clicks)"></td>
                                <td class="py-3 px-4 text-right font-medium text-slate-700 dark:text-slate-300" x-text="formatNumber(c.impressions)"></td>
                                <td class="py-3 px-4 text-right font-medium text-emerald-600 dark:text-emerald-400" x-text="c.ctr.toFixed(2) + '%'"></td>
                                <td class="py-3 px-4 text-right font-bold text-amber-600 dark:text-amber-400" x-text="c.position.toFixed(1)"></td>
                            </tr>
                        </template>
                        <template x-if="countriesList.length === 0">
                            <tr><td colspan="5" class="py-8 text-center text-slate-400">No country performance data recorded.</td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── TAB 4: Devices ────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'devices'" x-cloak class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <template x-for="d in devicesList" :key="d.device">
                    <div class="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase text-slate-500" x-text="d.device"></span>
                            <span class="text-xs font-bold text-blue-600 dark:text-blue-400" x-text="d.click_share + '% clicks'"></span>
                        </div>
                        <div class="text-2xl font-black text-slate-900 dark:text-slate-100" x-text="formatNumber(d.clicks) + ' Clicks'"></div>
                        <div class="text-xs text-slate-500 mt-1 flex items-center justify-between">
                            <span x-text="formatNumber(d.impressions) + ' Impressions'"></span>
                            <span class="text-emerald-600 font-bold" x-text="d.ctr.toFixed(2) + '% CTR'"></span>
                        </div>
                    </div>
                </template>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-3 px-4">Device Category</th>
                            <th class="py-3 px-4 text-right">Clicks</th>
                            <th class="py-3 px-4 text-right">Impressions</th>
                            <th class="py-3 px-4 text-right">CTR</th>
                            <th class="py-3 px-4 text-right">Position</th>
                            <th class="py-3 px-4 text-right">Click Share</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <template x-for="d in devicesList" :key="d.device">
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition-colors">
                                <td class="py-3 px-4 font-semibold text-slate-800 dark:text-slate-100" x-text="d.device"></td>
                                <td class="py-3 px-4 text-right font-medium text-slate-700 dark:text-slate-300" x-text="formatNumber(d.clicks)"></td>
                                <td class="py-3 px-4 text-right font-medium text-slate-700 dark:text-slate-300" x-text="formatNumber(d.impressions)"></td>
                                <td class="py-3 px-4 text-right font-medium text-emerald-600 dark:text-emerald-400" x-text="d.ctr.toFixed(2) + '%'"></td>
                                <td class="py-3 px-4 text-right font-bold text-amber-600 dark:text-amber-400" x-text="d.position.toFixed(1)"></td>
                                <td class="py-3 px-4 text-right font-bold text-blue-600 dark:text-blue-400" x-text="d.click_share + '%'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── TAB 5: Daily Performance ──────────────────────────────────── --}}
        <div x-show="activeTab === 'daily'" x-cloak class="p-6">
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4 text-right">Clicks</th>
                            <th class="py-3 px-4 text-right">Impressions</th>
                            <th class="py-3 px-4 text-right">CTR</th>
                            <th class="py-3 px-4 text-right">Position</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <template x-for="(label, idx) in trendData.labels" :key="label">
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition-colors">
                                <td class="py-2.5 px-4 font-semibold text-slate-800 dark:text-slate-200" x-text="label"></td>
                                <td class="py-2.5 px-4 text-right font-medium text-slate-700 dark:text-slate-300" x-text="formatNumber(trendData.clicks[idx])"></td>
                                <td class="py-2.5 px-4 text-right font-medium text-slate-700 dark:text-slate-300" x-text="formatNumber(trendData.impressions[idx])"></td>
                                <td class="py-2.5 px-4 text-right font-medium text-emerald-600 dark:text-emerald-400" x-text="(trendData.ctr[idx] || 0).toFixed(2) + '%'"></td>
                                <td class="py-2.5 px-4 text-right font-bold text-amber-600 dark:text-amber-400" x-text="(trendData.position[idx] || 0).toFixed(1)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── TAB 6: Website Comparison ─────────────────────────────────── --}}
        <div x-show="activeTab === 'breakdown'" x-cloak class="p-6">
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-3 px-4">Website</th>
                            <th class="py-3 px-4">Class</th>
                            <th class="py-3 px-4">Assigned Member</th>
                            <th class="py-3 px-4 text-right">Clicks</th>
                            <th class="py-3 px-4 text-right">Impressions</th>
                            <th class="py-3 px-4 text-right">CTR</th>
                            <th class="py-3 px-4 text-right">Avg Position</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <template x-for="wb in websiteBreakdown" :key="wb.website_id">
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900 dark:text-slate-100" x-text="wb.name"></div>
                                    <div class="font-mono text-[10px] text-slate-400 truncate max-w-xs" x-text="wb.url"></div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800" x-text="wb.class || 'Unclassified'"></span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2">
                                        <template x-if="wb.handler_avatar">
                                            <img :src="wb.handler_avatar" :alt="wb.handler_name" class="w-6 h-6 rounded-full object-cover border border-slate-200 dark:border-slate-700">
                                        </template>
                                        <template x-if="!wb.handler_avatar">
                                            <div class="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-600 dark:text-slate-300" x-text="(wb.handler_name || 'U').charAt(0).toUpperCase()"></div>
                                        </template>
                                        <span class="text-xs font-medium text-slate-700 dark:text-slate-200" x-text="wb.handler_name || 'Unassigned'"></span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right font-medium text-slate-700 dark:text-slate-300" x-text="formatNumber(wb.clicks)"></td>
                                <td class="py-3 px-4 text-right font-medium text-slate-700 dark:text-slate-300" x-text="formatNumber(wb.impressions)"></td>
                                <td class="py-3 px-4 text-right font-medium text-emerald-600 dark:text-emerald-400" x-text="wb.ctr.toFixed(2) + '%'"></td>
                                <td class="py-3 px-4 text-right font-bold text-amber-600 dark:text-amber-400" x-text="wb.position.toFixed(1)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Keyword Detail Inspector ────────────────────────────────── --}}
    <div x-show="showKeywordModal" @click.self="showKeywordModal = false" class="fixed inset-0 z-[99999] overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-3xl shadow-2xl max-w-3xl w-full p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-50 dark:bg-blue-900/40 rounded-xl text-blue-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100" x-text="inspectedKeyword"></h3>
                        <p class="text-xs text-slate-500">Keyword Performance Details & Daily Trend</p>
                    </div>
                </div>
                <button type="button" @click="showKeywordModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- Keyword Summary Cards --}}
            <div class="grid grid-cols-4 gap-3 my-5">
                <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl text-center">
                    <div class="text-[10px] uppercase font-bold text-slate-400">Clicks</div>
                    <div class="text-lg font-black text-slate-800 dark:text-slate-100" x-text="formatNumber(keywordDetailSummary.clicks)"></div>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl text-center">
                    <div class="text-[10px] uppercase font-bold text-slate-400">Impressions</div>
                    <div class="text-lg font-black text-slate-800 dark:text-slate-100" x-text="formatNumber(keywordDetailSummary.impressions)"></div>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl text-center">
                    <div class="text-[10px] uppercase font-bold text-slate-400">CTR</div>
                    <div class="text-lg font-black text-emerald-600" x-text="(keywordDetailSummary.ctr || 0).toFixed(2) + '%'"></div>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl text-center">
                    <div class="text-[10px] uppercase font-bold text-slate-400">Position</div>
                    <div class="text-lg font-black text-amber-600" x-text="(keywordDetailSummary.position || 0).toFixed(1)"></div>
                </div>
            </div>

            {{-- Daily Table --}}
            <div class="max-h-72 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 font-semibold sticky top-0">
                        <tr>
                            <th class="py-2 px-3">Date</th>
                            <th class="py-2 px-3 text-right">Clicks</th>
                            <th class="py-2 px-3 text-right">Impressions</th>
                            <th class="py-2 px-3 text-right">CTR</th>
                            <th class="py-2 px-3 text-right">Position</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <template x-for="row in keywordDetailDaily" :key="row.date">
                            <tr>
                                <td class="py-2 px-3 font-semibold text-slate-800 dark:text-slate-200" x-text="row.date"></td>
                                <td class="py-2 px-3 text-right" x-text="formatNumber(row.clicks)"></td>
                                <td class="py-2 px-3 text-right" x-text="formatNumber(row.impressions)"></td>
                                <td class="py-2 px-3 text-right font-medium text-emerald-600" x-text="row.ctr.toFixed(2) + '%'"></td>
                                <td class="py-2 px-3 text-right font-bold text-amber-600" x-text="row.position.toFixed(1)"></td>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="mt-5 flex justify-end">
                <button type="button" @click="showKeywordModal = false" class="px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 rounded-xl transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    </div> {{-- ── End VIEW 1: Overview & Keyword Analytics ── --}}

    {{-- ── VIEW 2: Website SEO Rankings & Leaderboard ──────────────────────── --}}
    <div x-show="activeView === 'rankings'" style="{{ ($currentView ?? 'overview') === 'rankings' ? '' : 'display: none;' }}" class="space-y-6">

        {{-- Rankings Header & Quick Export --}}
        <div class="p-6 bg-gradient-to-r from-slate-900 via-indigo-950 to-blue-900 rounded-3xl text-white shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-amber-500/20 text-amber-300 rounded-full text-xs font-bold mb-2 border border-amber-500/30">
                    <span>🏆 Search Console Leaderboard</span>
                    <span>&bull;</span>
                    <span x-text="sortedRankings.length + ' Websites Ranked'"></span>
                </div>
                <h2 class="text-2xl font-black tracking-tight">Website Performance Rankings</h2>
                <p class="text-sm text-slate-300 mt-1 max-w-2xl">
                    Real-time ranking of your authorized websites by search impressions, clicks, click-through rate, and average position from Google Search Console.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a :href="buildExportUrl('csv')" class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-slate-900 bg-white hover:bg-slate-100 rounded-xl shadow transition-all">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Export Rankings CSV</span>
                </a>
            </div>
        </div>

        {{-- 4 Trophy / Podium Highlight Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- 1. Most Impressions Champion --}}
            <div class="p-5 bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent border border-amber-400/40 dark:border-amber-700/50 rounded-2xl bg-white dark:bg-slate-800 shadow-sm relative overflow-hidden">
                <div class="absolute -right-3 -bottom-3 text-amber-500/10 dark:text-amber-400/10 pointer-events-none text-7xl font-black">
                    #1
                </div>
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-black bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-300/60 dark:border-amber-700/60">
                        <span>🥇 #1 Most Impressions</span>
                    </span>
                    <span class="text-xs font-bold text-amber-600 dark:text-amber-400" x-text="(topRankedByImpressions?.impressions_share || 0) + '% Share'"></span>
                </div>
                <template x-if="topRankedByImpressions">
                    <div>
                        <div class="text-base font-extrabold text-slate-900 dark:text-slate-100 truncate" x-text="topRankedByImpressions.name"></div>
                        <div class="text-xs font-mono text-blue-600 dark:text-blue-400 truncate mt-0.5" x-text="cleanDomain(topRankedByImpressions.clean_domain)"></div>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-2xl font-black text-amber-600 dark:text-amber-400" x-text="formatNumber(topRankedByImpressions.impressions)"></span>
                            <span class="text-xs text-slate-500">impressions</span>
                        </div>
                    </div>
                </template>
                <template x-if="!topRankedByImpressions">
                    <div class="text-xs text-slate-400 py-4">No data available</div>
                </template>
            </div>

            {{-- 2. Most Clicks Champion --}}
            <div class="p-5 bg-gradient-to-br from-blue-500/10 via-blue-500/5 to-transparent border border-blue-400/40 dark:border-blue-700/50 rounded-2xl bg-white dark:bg-slate-800 shadow-sm relative overflow-hidden">
                <div class="absolute -right-3 -bottom-3 text-blue-500/10 dark:text-blue-400/10 pointer-events-none text-7xl font-black">
                    #1
                </div>
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-black bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-300/60 dark:border-blue-700/60">
                        <span>🎯 #1 Top Clicks</span>
                    </span>
                    <span class="text-xs font-bold text-blue-600 dark:text-blue-400" x-text="(topRankedByClicks?.clicks_share || 0) + '% Share'"></span>
                </div>
                <template x-if="topRankedByClicks">
                    <div>
                        <div class="text-base font-extrabold text-slate-900 dark:text-slate-100 truncate" x-text="topRankedByClicks.name"></div>
                        <div class="text-xs font-mono text-blue-600 dark:text-blue-400 truncate mt-0.5" x-text="cleanDomain(topRankedByClicks.clean_domain)"></div>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-2xl font-black text-blue-600 dark:text-blue-400" x-text="formatNumber(topRankedByClicks.clicks)"></span>
                            <span class="text-xs text-slate-500">clicks</span>
                        </div>
                    </div>
                </template>
                <template x-if="!topRankedByClicks">
                    <div class="text-xs text-slate-400 py-4">No data available</div>
                </template>
            </div>

            {{-- 3. Highest CTR Performer --}}
            <div class="p-5 bg-gradient-to-br from-emerald-500/10 via-emerald-500/5 to-transparent border border-emerald-400/40 dark:border-emerald-700/50 rounded-2xl bg-white dark:bg-slate-800 shadow-sm relative overflow-hidden">
                <div class="absolute -right-3 -bottom-3 text-emerald-500/10 dark:text-emerald-400/10 pointer-events-none text-7xl font-black">
                    %
                </div>
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-black bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-300/60 dark:border-emerald-700/60">
                        <span>⚡ Highest CTR</span>
                    </span>
                </div>
                <template x-if="topRankedByCtr">
                    <div>
                        <div class="text-base font-extrabold text-slate-900 dark:text-slate-100 truncate" x-text="topRankedByCtr.name"></div>
                        <div class="text-xs font-mono text-blue-600 dark:text-blue-400 truncate mt-0.5" x-text="cleanDomain(topRankedByCtr.clean_domain)"></div>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400" x-text="(topRankedByCtr.ctr || 0) + '%'"></span>
                            <span class="text-xs text-slate-500">click rate</span>
                        </div>
                    </div>
                </template>
                <template x-if="!topRankedByCtr">
                    <div class="text-xs text-slate-400 py-4">No data available</div>
                </template>
            </div>

            {{-- 4. Best Search Position --}}
            <div class="p-5 bg-gradient-to-br from-purple-500/10 via-purple-500/5 to-transparent border border-purple-400/40 dark:border-purple-700/50 rounded-2xl bg-white dark:bg-slate-800 shadow-sm relative overflow-hidden">
                <div class="absolute -right-3 -bottom-3 text-purple-500/10 dark:text-purple-400/10 pointer-events-none text-7xl font-black">
                    #
                </div>
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-black bg-purple-100 dark:bg-purple-950/60 text-purple-800 dark:text-purple-300 border border-purple-300/60 dark:border-purple-700/60">
                        <span>📍 Top Avg Position</span>
                    </span>
                </div>
                <template x-if="topRankedByPosition">
                    <div>
                        <div class="text-base font-extrabold text-slate-900 dark:text-slate-100 truncate" x-text="topRankedByPosition.name"></div>
                        <div class="text-xs font-mono text-blue-600 dark:text-blue-400 truncate mt-0.5" x-text="cleanDomain(topRankedByPosition.clean_domain)"></div>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-2xl font-black text-purple-600 dark:text-purple-400" x-text="'#' + (topRankedByPosition.position || 0)"></span>
                            <span class="text-xs text-slate-500">Google Rank</span>
                        </div>
                    </div>
                </template>
                <template x-if="!topRankedByPosition">
                    <div class="text-xs text-slate-400 py-4">No data available</div>
                </template>
            </div>
        </div>

        {{-- Filter & Sorting Control Bar --}}
        <div class="p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider mr-1">Sort Leaderboard By:</span>
                <button type="button" @click="rankingSort = 'impressions'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                        :class="rankingSort === 'impressions' ? 'bg-amber-500 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200'">
                    <span>👁️ Most Impressions</span>
                </button>
                <button type="button" @click="rankingSort = 'clicks'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                        :class="rankingSort === 'clicks' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200'">
                    <span>🖱️ Most Clicks</span>
                </button>
                <button type="button" @click="rankingSort = 'ctr'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                        :class="rankingSort === 'ctr' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200'">
                    <span>📈 Highest CTR</span>
                </button>
                <button type="button" @click="rankingSort = 'position'"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                        :class="rankingSort === 'position' ? 'bg-purple-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200'">
                    <span>🎯 Best Position</span>
                </button>
            </div>

            <div class="flex items-center gap-3">
                <div class="relative w-full sm:w-64">
                    <input type="text" x-model="rankingSearch" placeholder="Search ranked website..." class="w-full text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl pl-9 pr-4 py-2 text-slate-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                </div>
            </div>
        </div>

        {{-- Leaderboard Table --}}
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-3.5 px-4 w-16 text-center">Rank</th>
                            <th class="py-3.5 px-4">DGT Website Name</th>
                            <th class="py-3.5 px-4">Clean Domain</th>
                            <th class="py-3.5 px-4">Team Handler</th>
                            <th class="py-3.5 px-4 text-right cursor-pointer hover:text-amber-600" @click="rankingSort = 'impressions'">
                                <div class="flex items-center justify-end gap-1">
                                    <span>Impressions</span>
                                    <span x-show="rankingSort === 'impressions'" class="text-amber-500 font-black">▼</span>
                                </div>
                            </th>
                            <th class="py-3.5 px-4 text-right cursor-pointer hover:text-blue-600" @click="rankingSort = 'clicks'">
                                <div class="flex items-center justify-end gap-1">
                                    <span>Clicks</span>
                                    <span x-show="rankingSort === 'clicks'" class="text-blue-500 font-black">▼</span>
                                </div>
                            </th>
                            <th class="py-3.5 px-4 text-right cursor-pointer hover:text-emerald-600" @click="rankingSort = 'ctr'">
                                <div class="flex items-center justify-end gap-1">
                                    <span>CTR (%)</span>
                                    <span x-show="rankingSort === 'ctr'" class="text-emerald-500 font-black">▼</span>
                                </div>
                            </th>
                            <th class="py-3.5 px-4 text-right cursor-pointer hover:text-purple-600" @click="rankingSort = 'position'">
                                <div class="flex items-center justify-end gap-1">
                                    <span>Avg Position</span>
                                    <span x-show="rankingSort === 'position'" class="text-purple-500 font-black">▲</span>
                                </div>
                            </th>
                            <th class="py-3.5 px-4 text-right">Quick Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium">
                        <template x-for="(ws, idx) in sortedRankings" :key="ws.website_id">
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-750 transition-colors">
                                {{-- Rank Badge --}}
                                <td class="py-3.5 px-4 text-center">
                                    <template x-if="idx === 0">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300 font-black text-sm border border-amber-300 shadow-xs" title="1st Place Champion">🥇</span>
                                    </template>
                                    <template x-if="idx === 1">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200 font-black text-sm border border-slate-300 shadow-xs" title="2nd Place">🥈</span>
                                    </template>
                                    <template x-if="idx === 2">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-900/20 text-amber-700 dark:text-amber-400 font-black text-sm border border-amber-800/30 shadow-xs" title="3rd Place">🥉</span>
                                    </template>
                                    <template x-if="idx > 2">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs font-bold" x-text="'#' + (idx + 1)"></span>
                                    </template>
                                </td>

                                {{-- Website Name & Category --}}
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-extrabold text-sm text-slate-900 dark:text-slate-100" x-text="ws.name"></span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300 border border-purple-200/80 dark:border-purple-800" x-text="ws.class || 'General'"></span>
                                    </div>
                                </td>

                                {{-- Clean Domain (No sc-domain:) --}}
                                <td class="py-3.5 px-4">
                                    <a :href="'https://' + cleanDomain(ws.clean_domain)" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-mono text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                        <span x-text="cleanDomain(ws.clean_domain)"></span>
                                        <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                    </a>
                                </td>

                                {{-- Handler --}}
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2">
                                        <template x-if="ws.handler_avatar">
                                            <img :src="ws.handler_avatar" class="w-5 h-5 rounded-full object-cover">
                                        </template>
                                        <span class="text-xs text-slate-700 dark:text-slate-300" x-text="ws.handler_name || 'Unassigned'"></span>
                                    </div>
                                </td>

                                {{-- Impressions & Progress Bar --}}
                                <td class="py-3.5 px-4 text-right">
                                    <div class="font-extrabold text-sm text-slate-900 dark:text-slate-100" x-text="formatNumber(ws.impressions)"></div>
                                    <div class="flex items-center justify-end gap-1.5 mt-1">
                                        <div class="w-16 h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                            <div class="h-full bg-gradient-to-r from-amber-400 to-amber-500 rounded-full" :style="'width: ' + (ws.impressions_share || 0) + '%'"></div>
                                        </div>
                                        <span class="text-[10px] font-semibold text-amber-600 dark:text-amber-400" x-text="(ws.impressions_share || 0) + '%'"></span>
                                    </div>
                                </td>

                                {{-- Clicks & Progress Bar --}}
                                <td class="py-3.5 px-4 text-right">
                                    <div class="font-extrabold text-sm text-blue-600 dark:text-blue-400" x-text="formatNumber(ws.clicks)"></div>
                                    <div class="flex items-center justify-end gap-1.5 mt-1">
                                        <div class="w-16 h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                            <div class="h-full bg-gradient-to-r from-blue-400 to-blue-600 rounded-full" :style="'width: ' + (ws.clicks_share || 0) + '%'"></div>
                                        </div>
                                        <span class="text-[10px] font-semibold text-blue-600 dark:text-blue-400" x-text="(ws.clicks_share || 0) + '%'"></span>
                                    </div>
                                </td>

                                {{-- CTR --}}
                                <td class="py-3.5 px-4 text-right">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold"
                                          :class="ws.ctr >= 4.0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : (ws.ctr >= 1.5 ? 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300')"
                                          x-text="(ws.ctr || 0) + '%'"></span>
                                </td>

                                {{-- Position --}}
                                <td class="py-3.5 px-4 text-right">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-extrabold"
                                          :class="ws.position <= 5.0 && ws.position > 0 ? 'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300' : (ws.position <= 15.0 && ws.position > 0 ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300')"
                                          x-text="'#' + (ws.position || 0)"></span>
                                </td>

                                {{-- Quick Actions --}}
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" @click="startComparisonWith(ws.website_id)" class="px-2.5 py-1 text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/50 hover:bg-indigo-100 dark:hover:bg-indigo-900 rounded-lg transition-colors flex items-center gap-1" title="Compare this website with another">
                                            <span>⚖️ Compare</span>
                                        </button>
                                        <button type="button" @click="filterToSingleWebsite(ws.website_id)" class="px-2.5 py-1 text-xs font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/50 hover:bg-blue-100 dark:hover:bg-blue-900 rounded-lg transition-colors" title="View in Overview Dashboard">
                                            <span>📊 Analytics</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <template x-if="sortedRankings.length === 0">
                            <tr>
                                <td colspan="9" class="py-12 text-center text-slate-400">
                                    <p class="font-medium text-sm">No websites found matching your search.</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

    </div> {{-- ── End VIEW 2: Website Rankings ── --}}

    {{-- ── VIEW 3: Website Comparison Tool ────────────────────────────────── --}}
    <div x-show="activeView === 'compare'" style="{{ ($currentView ?? 'overview') === 'compare' ? '' : 'display: none;' }}" class="space-y-6">

        {{-- Comparison Top Selector Bar --}}
        <div class="p-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm relative z-20">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-700">
                <div>
                    <h3 class="text-base font-black text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                        </span>
                        <span>Side-by-Side Website Performance Comparison</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Select two websites to contrast clicks, impressions, CTR, average ranking position, and daily trends.</p>
                </div>

                {{-- Metric Switch for Chart --}}
                <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-900 rounded-xl">
                    <button type="button" @click="compareMetric = 'impressions'; renderCompareChart()"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                            :class="compareMetric === 'impressions' ? 'bg-white dark:bg-slate-800 text-blue-600 dark:text-blue-400 shadow-xs' : 'text-slate-600 dark:text-slate-400'">
                        Compare Impressions
                    </button>
                    <button type="button" @click="compareMetric = 'clicks'; renderCompareChart()"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                            :class="compareMetric === 'clicks' ? 'bg-white dark:bg-slate-800 text-blue-600 dark:text-blue-400 shadow-xs' : 'text-slate-600 dark:text-slate-400'">
                        Compare Clicks
                    </button>
                </div>
            </div>

            {{-- Selectors Row with Rich Searchable Dropdowns --}}
            <div class="grid grid-cols-1 md:grid-cols-11 gap-4 items-center pt-4">
                {{-- Website A (Primary) --}}
                <div class="md:col-span-5 relative" @click.outside="openCompareDropdownA = false">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                            <span>Website A (Primary)</span>
                        </label>
                        <span class="text-[10px] text-blue-600 dark:text-blue-400 font-bold" x-text="filteredCompareWebsitesA.length + ' available'"></span>
                    </div>

                    {{-- Trigger Button Website A --}}
                    <button type="button"
                            @click="openCompareDropdownA = !openCompareDropdownA; if (openCompareDropdownA) openCompareDropdownB = false;"
                            class="w-full text-left bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-2.5 sm:px-3.5 sm:py-2.5 transition-all shadow-xs hover:border-blue-300 dark:hover:border-blue-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500/40 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-600 shrink-0"></span>

                            <div class="min-w-0 flex-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="text-xs sm:text-sm font-black text-slate-900 dark:text-slate-100 truncate" x-text="selectedCompareSiteA?.name || 'Select Website A'"></span>
                                <span class="font-mono text-[11px] text-slate-400 dark:text-slate-500 truncate" x-text="'(' + cleanDomain(selectedCompareSiteA?.domain || '') + ')'"></span>

                                {{-- Class Badge --}}
                                <template x-if="selectedCompareSiteA?.class">
                                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wide bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200/80 dark:border-blue-800/80" x-text="selectedCompareSiteA.class"></span>
                                </template>

                                {{-- Handler Badge --}}
                                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800/80 text-[11px] font-semibold text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700">
                                    <template x-if="selectedCompareSiteA?.handler">
                                        <div class="inline-flex items-center gap-1.5">
                                            <img :src="selectedCompareSiteA.handler.avatar_url" class="w-3.5 h-3.5 rounded-full object-cover">
                                            <span class="truncate max-w-[100px]" x-text="selectedCompareSiteA.handler.name"></span>
                                        </div>
                                    </template>
                                    <template x-if="!selectedCompareSiteA?.handler">
                                        <span class="italic text-slate-400 text-[10px]">Unassigned</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0 text-slate-400">
                            <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 hidden sm:inline">Change</span>
                            <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': openCompareDropdownA }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </button>

                    {{-- Searchable Dropdown Floating Panel A --}}
                    <div x-show="openCompareDropdownA"
                         x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1 scale-98"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-1 scale-98"
                         class="absolute left-0 right-0 top-full mt-2 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-2xl z-50 p-3 space-y-2.5">

                        {{-- Search Input --}}
                        <div class="relative">
                            <svg class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            <input type="text"
                                   x-model="compareSearchA"
                                   placeholder="Search website A by name, domain, class, handler..."
                                   class="w-full text-xs pl-9 pr-8 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-100 placeholder-slate-400 outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500">
                            <button type="button"
                                    x-show="compareSearchA"
                                    @click="compareSearchA = ''"
                                    class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        {{-- Quick Class Filters --}}
                        <div class="flex items-center gap-1.5 flex-wrap pt-0.5 text-[11px]">
                            <button type="button"
                                    @click="compareClassFilterA = 'all'"
                                    class="px-2 py-0.5 rounded-lg font-bold transition-colors"
                                    :class="compareClassFilterA === 'all' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'">
                                All
                            </button>
                            <template x-for="c in availableClasses" :key="'cf-a-' + c">
                                <button type="button"
                                        @click="compareClassFilterA = c"
                                        class="px-2 py-0.5 rounded-lg font-bold transition-colors"
                                        :class="compareClassFilterA === c ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                                        x-text="c">
                                </button>
                            </template>
                        </div>

                        {{-- Website List --}}
                        <div class="max-h-60 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/80 rounded-xl border border-slate-100 dark:border-slate-800">
                            <template x-for="w in filteredCompareWebsitesA" :key="'list-a-' + w.id">
                                <div @click="selectCompareSiteA(w.id)"
                                     class="p-2.5 hover:bg-blue-50/70 dark:hover:bg-slate-800/80 cursor-pointer transition-colors flex items-center justify-between gap-3"
                                     :class="String(compareSiteA) === String(w.id) ? 'bg-blue-50/90 dark:bg-blue-950/40' : ''">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full" :class="String(compareSiteA) === String(w.id) ? 'bg-blue-600' : 'bg-slate-300 dark:bg-slate-600'"></span>
                                            <span class="text-xs font-black text-slate-800 dark:text-slate-200 truncate" x-text="w.name"></span>
                                            <template x-if="w.class">
                                                <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300" x-text="w.class"></span>
                                            </template>
                                        </div>
                                        <div class="text-[11px] font-mono text-slate-400 dark:text-slate-500 pl-4 truncate" x-text="cleanDomain(w.domain)"></div>
                                    </div>
                                    <template x-if="w.handler">
                                        <div class="flex items-center gap-1.5 shrink-0 px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10.5px] text-slate-600 dark:text-slate-300">
                                            <img :src="w.handler.avatar_url" class="w-3.5 h-3.5 rounded-full object-cover">
                                            <span class="truncate max-w-[80px]" x-text="w.handler.name"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="filteredCompareWebsitesA.length === 0">
                                <div class="p-4 text-center text-xs text-slate-400">No websites match your search</div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Interactive VS / Swap Button --}}
                <div class="md:col-span-1 flex items-center justify-center pt-2 md:pt-4">
                    <button type="button"
                            @click="swapCompareSites()"
                            title="Click to swap Website A and Website B"
                            class="group relative w-10 h-10 rounded-full bg-gradient-to-tr from-blue-600 via-indigo-600 to-emerald-500 text-white font-black text-xs flex items-center justify-center shadow-md shadow-indigo-500/25 hover:scale-110 hover:shadow-lg hover:shadow-indigo-500/40 active:scale-95 transition-all">
                        <span class="group-hover:hidden font-black">VS</span>
                        <svg class="w-4 h-4 hidden group-hover:block transition-transform duration-300 group-hover:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                    </button>
                </div>

                {{-- Website B (Comparison) --}}
                <div class="md:col-span-5 relative" @click.outside="openCompareDropdownB = false">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                            <span>Website B (Comparison)</span>
                        </label>
                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold" x-text="filteredCompareWebsitesB.length + ' available'"></span>
                    </div>

                    {{-- Trigger Button Website B --}}
                    <button type="button"
                            @click="openCompareDropdownB = !openCompareDropdownB; if (openCompareDropdownB) openCompareDropdownA = false;"
                            class="w-full text-left bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-2.5 sm:px-3.5 sm:py-2.5 transition-all shadow-xs hover:border-emerald-300 dark:hover:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/40 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>

                            <div class="min-w-0 flex-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="text-xs sm:text-sm font-black text-slate-900 dark:text-slate-100 truncate" x-text="selectedCompareSiteB?.name || 'Select Website B'"></span>
                                <span class="font-mono text-[11px] text-slate-400 dark:text-slate-500 truncate" x-text="'(' + cleanDomain(selectedCompareSiteB?.domain || '') + ')'"></span>

                                {{-- Class Badge --}}
                                <template x-if="selectedCompareSiteB?.class">
                                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wide bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/80" x-text="selectedCompareSiteB.class"></span>
                                </template>

                                {{-- Handler Badge --}}
                                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800/80 text-[11px] font-semibold text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700">
                                    <template x-if="selectedCompareSiteB?.handler">
                                        <div class="inline-flex items-center gap-1.5">
                                            <img :src="selectedCompareSiteB.handler.avatar_url" class="w-3.5 h-3.5 rounded-full object-cover">
                                            <span class="truncate max-w-[100px]" x-text="selectedCompareSiteB.handler.name"></span>
                                        </div>
                                    </template>
                                    <template x-if="!selectedCompareSiteB?.handler">
                                        <span class="italic text-slate-400 text-[10px]">Unassigned</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0 text-slate-400">
                            <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 hidden sm:inline">Change</span>
                            <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': openCompareDropdownB }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </button>

                    {{-- Searchable Dropdown Floating Panel B --}}
                    <div x-show="openCompareDropdownB"
                         x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1 scale-98"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-1 scale-98"
                         class="absolute left-0 right-0 top-full mt-2 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-2xl z-50 p-3 space-y-2.5">

                        {{-- Search Input --}}
                        <div class="relative">
                            <svg class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            <input type="text"
                                   x-model="compareSearchB"
                                   placeholder="Search website B by name, domain, class, handler..."
                                   class="w-full text-xs pl-9 pr-8 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-100 placeholder-slate-400 outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500">
                            <button type="button"
                                    x-show="compareSearchB"
                                    @click="compareSearchB = ''"
                                    class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        {{-- Quick Class Filters --}}
                        <div class="flex items-center gap-1.5 flex-wrap pt-0.5 text-[11px]">
                            <button type="button"
                                    @click="compareClassFilterB = 'all'"
                                    class="px-2 py-0.5 rounded-lg font-bold transition-colors"
                                    :class="compareClassFilterB === 'all' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'">
                                All
                            </button>
                            <template x-for="c in availableClasses" :key="'cf-b-' + c">
                                <button type="button"
                                        @click="compareClassFilterB = c"
                                        class="px-2 py-0.5 rounded-lg font-bold transition-colors"
                                        :class="compareClassFilterB === c ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                                        x-text="c">
                                </button>
                            </template>
                        </div>

                        {{-- Website List --}}
                        <div class="max-h-60 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/80 rounded-xl border border-slate-100 dark:border-slate-800">
                            <template x-for="w in filteredCompareWebsitesB" :key="'list-b-' + w.id">
                                <div @click="selectCompareSiteB(w.id)"
                                     class="p-2.5 hover:bg-emerald-50/70 dark:hover:bg-slate-800/80 cursor-pointer transition-colors flex items-center justify-between gap-3"
                                     :class="String(compareSiteB) === String(w.id) ? 'bg-emerald-50/90 dark:bg-emerald-950/40' : ''">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full" :class="String(compareSiteB) === String(w.id) ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'"></span>
                                            <span class="text-xs font-black text-slate-800 dark:text-slate-200 truncate" x-text="w.name"></span>
                                            <template x-if="w.class">
                                                <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300" x-text="w.class"></span>
                                            </template>
                                        </div>
                                        <div class="text-[11px] font-mono text-slate-400 dark:text-slate-500 pl-4 truncate" x-text="cleanDomain(w.domain)"></div>
                                    </div>
                                    <template x-if="w.handler">
                                        <div class="flex items-center gap-1.5 shrink-0 px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10.5px] text-slate-600 dark:text-slate-300">
                                            <img :src="w.handler.avatar_url" class="w-3.5 h-3.5 rounded-full object-cover">
                                            <span class="truncate max-w-[80px]" x-text="w.handler.name"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="filteredCompareWebsitesB.length === 0">
                                <div class="p-4 text-center text-xs text-slate-400">No websites match your search</div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Head-to-Head Comparison KPI Cards --}}
        <template x-if="comparisonData.websites && comparisonData.websites.length >= 2">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Impressions Comparison --}}
                <div class="p-4 sm:p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-xs hover:shadow-md transition-all flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 dark:border-slate-700/60">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </div>
                                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Search Impressions</span>
                            </div>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">VS</span>
                        </div>

                        {{-- Site A Row --}}
                        <div class="mt-3 p-2.5 rounded-xl bg-blue-50/60 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/40 flex items-center justify-between gap-2">
                            <div class="min-w-0 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-600 shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate" :title="comparisonData.websites[0]?.name" x-text="comparisonData.websites[0]?.name"></span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-base sm:text-lg font-black text-blue-600 dark:text-blue-400" x-text="formatNumber(comparisonData.websites[0]?.impressions)"></span>
                            </div>
                        </div>

                        {{-- Site B Row --}}
                        <div class="mt-2 p-2.5 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/40 flex items-center justify-between gap-2">
                            <div class="min-w-0 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate" :title="comparisonData.websites[1]?.name" x-text="comparisonData.websites[1]?.name"></span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-base sm:text-lg font-black text-emerald-600 dark:text-emerald-400" x-text="formatNumber(comparisonData.websites[1]?.impressions)"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Visual Ratio Mini Bar & Delta --}}
                    <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-700/60 space-y-1.5">
                        <div class="w-full h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden flex">
                            <div class="bg-blue-500 transition-all duration-300" :style="'width: ' + (((comparisonData.websites[0]?.impressions || 0) + (comparisonData.websites[1]?.impressions || 0)) > 0 ? (((comparisonData.websites[0]?.impressions || 0) / ((comparisonData.websites[0]?.impressions || 0) + (comparisonData.websites[1]?.impressions || 0))) * 100) : 50) + '%'"></div>
                            <div class="bg-emerald-500 transition-all duration-300" :style="'width: ' + (((comparisonData.websites[0]?.impressions || 0) + (comparisonData.websites[1]?.impressions || 0)) > 0 ? (((comparisonData.websites[1]?.impressions || 0) / ((comparisonData.websites[0]?.impressions || 0) + (comparisonData.websites[1]?.impressions || 0))) * 100) : 50) + '%'"></div>
                        </div>
                        <div class="flex items-center justify-between text-[10.5px]">
                            <span class="text-slate-400 font-medium">Difference</span>
                            <span class="font-extrabold"
                                  :class="(comparisonData.websites[0]?.impressions || 0) >= (comparisonData.websites[1]?.impressions || 0) ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'"
                                  x-text="formatNumber(Math.abs((comparisonData.websites[0]?.impressions || 0) - (comparisonData.websites[1]?.impressions || 0))) + ' ' + ((comparisonData.websites[0]?.impressions || 0) >= (comparisonData.websites[1]?.impressions || 0) ? '(Site A leads)' : '(Site B leads)')">
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Clicks Comparison --}}
                <div class="p-4 sm:p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-xs hover:shadow-md transition-all flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 dark:border-slate-700/60">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672 13.684 16.6m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672ZM12 2.25V4.5m5.834.166-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243-1.59-1.59" />
                                    </svg>
                                </div>
                                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Organic Clicks</span>
                            </div>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">VS</span>
                        </div>

                        {{-- Site A Row --}}
                        <div class="mt-3 p-2.5 rounded-xl bg-blue-50/60 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/40 flex items-center justify-between gap-2">
                            <div class="min-w-0 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-600 shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate" :title="comparisonData.websites[0]?.name" x-text="comparisonData.websites[0]?.name"></span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-base sm:text-lg font-black text-blue-600 dark:text-blue-400" x-text="formatNumber(comparisonData.websites[0]?.clicks)"></span>
                            </div>
                        </div>

                        {{-- Site B Row --}}
                        <div class="mt-2 p-2.5 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/40 flex items-center justify-between gap-2">
                            <div class="min-w-0 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate" :title="comparisonData.websites[1]?.name" x-text="comparisonData.websites[1]?.name"></span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-base sm:text-lg font-black text-emerald-600 dark:text-emerald-400" x-text="formatNumber(comparisonData.websites[1]?.clicks)"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Visual Ratio Mini Bar & Delta --}}
                    <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-700/60 space-y-1.5">
                        <div class="w-full h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden flex">
                            <div class="bg-blue-500 transition-all duration-300" :style="'width: ' + (((comparisonData.websites[0]?.clicks || 0) + (comparisonData.websites[1]?.clicks || 0)) > 0 ? (((comparisonData.websites[0]?.clicks || 0) / ((comparisonData.websites[0]?.clicks || 0) + (comparisonData.websites[1]?.clicks || 0))) * 100) : 50) + '%'"></div>
                            <div class="bg-emerald-500 transition-all duration-300" :style="'width: ' + (((comparisonData.websites[0]?.clicks || 0) + (comparisonData.websites[1]?.clicks || 0)) > 0 ? (((comparisonData.websites[1]?.clicks || 0) / ((comparisonData.websites[0]?.clicks || 0) + (comparisonData.websites[1]?.clicks || 0))) * 100) : 50) + '%'"></div>
                        </div>
                        <div class="flex items-center justify-between text-[10.5px]">
                            <span class="text-slate-400 font-medium">Difference</span>
                            <span class="font-extrabold"
                                  :class="(comparisonData.websites[0]?.clicks || 0) >= (comparisonData.websites[1]?.clicks || 0) ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'"
                                  x-text="formatNumber(Math.abs((comparisonData.websites[0]?.clicks || 0) - (comparisonData.websites[1]?.clicks || 0))) + ' ' + ((comparisonData.websites[0]?.clicks || 0) >= (comparisonData.websites[1]?.clicks || 0) ? '(Site A leads)' : '(Site B leads)')">
                            </span>
                        </div>
                    </div>
                </div>

                {{-- CTR Comparison --}}
                <div class="p-4 sm:p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-xs hover:shadow-md transition-all flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 dark:border-slate-700/60">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 14.25 6-6m4.5-3.75h-15a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 4.5 21.75h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5Z" />
                                    </svg>
                                </div>
                                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Click-Through Rate (CTR)</span>
                            </div>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">VS</span>
                        </div>

                        {{-- Site A Row --}}
                        <div class="mt-3 p-2.5 rounded-xl bg-blue-50/60 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/40 flex items-center justify-between gap-2">
                            <div class="min-w-0 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-600 shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate" :title="comparisonData.websites[0]?.name" x-text="comparisonData.websites[0]?.name"></span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-base sm:text-lg font-black text-blue-600 dark:text-blue-400" x-text="(comparisonData.websites[0]?.ctr || 0) + '%'"></span>
                            </div>
                        </div>

                        {{-- Site B Row --}}
                        <div class="mt-2 p-2.5 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/40 flex items-center justify-between gap-2">
                            <div class="min-w-0 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate" :title="comparisonData.websites[1]?.name" x-text="comparisonData.websites[1]?.name"></span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-base sm:text-lg font-black text-emerald-600 dark:text-emerald-400" x-text="(comparisonData.websites[1]?.ctr || 0) + '%'"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Visual Ratio Mini Bar & Delta --}}
                    <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-700/60 space-y-1.5">
                        <div class="w-full h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden flex">
                            <div class="bg-blue-500 transition-all duration-300" :style="'width: ' + (((parseFloat(comparisonData.websites[0]?.ctr) || 0) + (parseFloat(comparisonData.websites[1]?.ctr) || 0)) > 0 ? (((parseFloat(comparisonData.websites[0]?.ctr) || 0) / ((parseFloat(comparisonData.websites[0]?.ctr) || 0) + (parseFloat(comparisonData.websites[1]?.ctr) || 0))) * 100) : 50) + '%'"></div>
                            <div class="bg-emerald-500 transition-all duration-300" :style="'width: ' + (((parseFloat(comparisonData.websites[0]?.ctr) || 0) + (parseFloat(comparisonData.websites[1]?.ctr) || 0)) > 0 ? (((parseFloat(comparisonData.websites[1]?.ctr) || 0) / ((parseFloat(comparisonData.websites[0]?.ctr) || 0) + (parseFloat(comparisonData.websites[1]?.ctr) || 0))) * 100) : 50) + '%'"></div>
                        </div>
                        <div class="flex items-center justify-between text-[10.5px]">
                            <span class="text-slate-400 font-medium">Difference</span>
                            <span class="font-extrabold"
                                  :class="(parseFloat(comparisonData.websites[0]?.ctr) || 0) >= (parseFloat(comparisonData.websites[1]?.ctr) || 0) ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'"
                                  x-text="Math.abs((parseFloat(comparisonData.websites[0]?.ctr) || 0) - (parseFloat(comparisonData.websites[1]?.ctr) || 0)).toFixed(2) + '% ' + ((parseFloat(comparisonData.websites[0]?.ctr) || 0) >= (parseFloat(comparisonData.websites[1]?.ctr) || 0) ? '(Site A leads)' : '(Site B leads)')">
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Avg Position Comparison --}}
                <div class="p-4 sm:p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-xs hover:shadow-md transition-all flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 dark:border-slate-700/60">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.504-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.004 0V5.625c0-.621-.504-1.125-1.125-1.125H9.625c-.621 0-1.125.504-1.125 1.125v8.625m5.004 0h-5.004" />
                                    </svg>
                                </div>
                                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Average Position</span>
                            </div>
                            <span class="text-[9px] font-bold text-slate-400">Lower is better</span>
                        </div>

                        {{-- Site A Row --}}
                        <div class="mt-3 p-2.5 rounded-xl bg-blue-50/60 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/40 flex items-center justify-between gap-2">
                            <div class="min-w-0 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-600 shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate" :title="comparisonData.websites[0]?.name" x-text="comparisonData.websites[0]?.name"></span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-base sm:text-lg font-black text-blue-600 dark:text-blue-400" x-text="'#' + (comparisonData.websites[0]?.position ? Number(comparisonData.websites[0]?.position).toFixed(1) : 0)"></span>
                            </div>
                        </div>

                        {{-- Site B Row --}}
                        <div class="mt-2 p-2.5 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/40 flex items-center justify-between gap-2">
                            <div class="min-w-0 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate" :title="comparisonData.websites[1]?.name" x-text="comparisonData.websites[1]?.name"></span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-base sm:text-lg font-black text-emerald-600 dark:text-emerald-400" x-text="'#' + (comparisonData.websites[1]?.position ? Number(comparisonData.websites[1]?.position).toFixed(1) : 0)"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Position Delta --}}
                    <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-[10.5px]">
                        <span class="text-slate-400 font-medium">Rank Advantage</span>
                        <span class="font-extrabold"
                              :class="(parseFloat(comparisonData.websites[0]?.position) || 999) <= (parseFloat(comparisonData.websites[1]?.position) || 999) ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'"
                              x-text="(parseFloat(comparisonData.websites[0]?.position) > 0 && parseFloat(comparisonData.websites[1]?.position) > 0) ? (Math.abs((parseFloat(comparisonData.websites[0]?.position) || 0) - (parseFloat(comparisonData.websites[1]?.position) || 0)).toFixed(1) + ' spots ' + ((parseFloat(comparisonData.websites[0]?.position) || 999) <= (parseFloat(comparisonData.websites[1]?.position) || 999) ? '(Site A higher)' : '(Site B higher)')) : 'N/A'">
                        </span>
                    </div>
                </div>
            </div>
        </template>

        {{-- Dynamic Comparison Trend Chart --}}
        <div class="p-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100" x-text="compareMetric === 'impressions' ? 'Comparative Daily Impressions Trend' : 'Comparative Daily Clicks Trend'"></h4>
                    <p class="text-xs text-slate-500">Overlay of daily performance between the compared websites</p>
                </div>
            </div>
            <div class="relative h-80 w-full" style="min-height: 320px; height: 320px; position: relative;">
                <canvas id="gscComparisonChartCanvas"></canvas>
            </div>
        </div>

        {{-- Top Keywords Head-to-Head Side-by-Side --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <template x-for="(ws, idx) in (comparisonData.websites || [])" :key="'kw-box-' + ws.website_id">
                <div class="p-5 sm:p-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-sm flex flex-col justify-between">
                    <div>
                        {{-- Card Header with Site Indicator & Top Queries Pill --}}
                        <div class="flex items-center justify-between gap-3 mb-4 pb-3.5 border-b border-slate-100 dark:border-slate-700/80">
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                <span class="w-3 h-3 rounded-full shrink-0 shadow-sm" :class="idx === 0 ? 'bg-blue-600 shadow-blue-500/50' : 'bg-emerald-500 shadow-emerald-500/50'"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm font-black text-slate-900 dark:text-slate-100 truncate" x-text="ws.name"></h4>
                                        <template x-if="idx === 0">
                                            <span class="px-1.5 py-0.5 rounded text-[9.5px] font-black uppercase tracking-wider bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60 shrink-0">Site A</span>
                                        </template>
                                        <template x-if="idx === 1">
                                            <span class="px-1.5 py-0.5 rounded text-[9.5px] font-black uppercase tracking-wider bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60 shrink-0">Site B</span>
                                        </template>
                                    </div>
                                    <div class="text-xs font-mono text-slate-400 dark:text-slate-500 mt-0.5 truncate" x-text="cleanDomain(ws.domain)"></div>
                                </div>
                            </div>

                            {{-- Protected Pill Badge --}}
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[10.5px] font-extrabold uppercase tracking-wider bg-slate-100 dark:bg-slate-700/70 text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-slate-600/60">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                                    <span>Top Queries</span>
                                </span>
                            </div>
                        </div>

                        {{-- Table with Generous Padding & Badges --}}
                        <div class="overflow-x-auto rounded-xl border border-slate-100 dark:border-slate-700/60 bg-slate-50/20 dark:bg-slate-900/30">
                            <table class="w-full text-left text-xs">
                                <thead class="text-[11px] uppercase tracking-wider font-bold text-slate-500 dark:text-slate-400 bg-slate-100/70 dark:bg-slate-800/80 border-b border-slate-200/80 dark:border-slate-700/80">
                                    <tr>
                                        <th class="py-2.5 pl-4 pr-3 min-w-[150px]">Search Query</th>
                                        <th class="py-2.5 px-3 text-right whitespace-nowrap">Clicks</th>
                                        <th class="py-2.5 px-3 text-right whitespace-nowrap">Impr</th>
                                        <th class="py-2.5 px-3 text-right whitespace-nowrap">CTR</th>
                                        <th class="py-2.5 pl-3 pr-4 text-right whitespace-nowrap">Avg Pos</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium">
                                    <template x-for="q in (ws.top_queries || [])" :key="q.query">
                                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                                            <td class="py-2.5 pl-4 pr-3 text-slate-800 dark:text-slate-200">
                                                <div class="font-semibold truncate max-w-[170px]" :title="q.query" x-text="q.query"></div>
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-black text-blue-600 dark:text-blue-400 whitespace-nowrap" x-text="formatNumber(q.clicks)"></td>
                                            <td class="py-2.5 px-3 text-right font-bold text-slate-600 dark:text-slate-300 whitespace-nowrap" x-text="formatNumber(q.impressions)"></td>
                                            <td class="py-2.5 px-3 text-right font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap" x-text="(q.ctr ? Number(q.ctr).toFixed(1) : 0) + '%'"></td>
                                            <td class="py-2.5 pl-3 pr-4 text-right whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-black bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200/80 dark:border-purple-800/80 shadow-2xs"
                                                      x-text="'#' + (q.position ? Number(q.position).toFixed(1) : 0)">
                                                </span>
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-if="!ws.top_queries || ws.top_queries.length === 0">
                                        <tr>
                                            <td colspan="5" class="py-8 text-center text-slate-400 text-xs">
                                                No queries recorded in this date range
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </div>

    </div> {{-- ── End VIEW 3: Website Comparison Tool ── --}}

    {{-- ── VIEW 4: Sitemaps & Page Indexation Hub ──────────────────────────────── --}}
    <div x-show="activeView === 'sitemaps'" style="{{ ($currentView ?? 'overview') === 'sitemaps' ? '' : 'display: none;' }}" class="space-y-6">

        {{-- Top Control Card: Website Selector & Property Info --}}
        <div class="p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="p-3 bg-purple-50 dark:bg-purple-900/40 rounded-xl text-purple-600 dark:text-purple-400">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-black text-slate-900 dark:text-slate-100">
                                Sitemaps & Page Indexation Coverage Hub
                            </h3>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300">
                                Google Search Console API
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Submit XML sitemaps directly to Google, check crawl status, and track Indexed vs Not-Indexed pages in real-time.
                        </p>
                    </div>
                </div>

                {{-- Action buttons --}}
                <div class="flex flex-wrap items-center gap-2.5">
                    <button type="button" @click="onSitemapWebsiteChange(sitemapWebsiteId, true)" :disabled="sitemapsLoading || indexationLoading" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700/60 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-xl transition-all">
                        <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': sitemapsLoading || indexationLoading }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span>Refresh Google Data</span>
                    </button>

                    <a :href="'{{ route('websites.search-console.export.sitemaps-csv') }}?website_id=' + sitemapWebsiteId" target="_blank" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 border border-emerald-200 dark:border-emerald-800 rounded-xl transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <span>Export Audit CSV</span>
                    </a>

                    <button type="button" @click="openManageModal($event)" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/50 hover:bg-blue-100 rounded-xl transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                        <span>Property Manager</span>
                    </button>
                </div>
            </div>

            {{-- Website Selector Bar with Linked Status indicator --}}
            <div class="pt-4 border-t border-slate-100 dark:border-slate-700/80 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex-1 max-w-2xl relative" @click.outside="openSitemapWebsiteDropdown = false">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Select Target DGT Website:
                        </label>
                        <span class="text-[10px] text-purple-600 dark:text-purple-400 font-bold" x-text="filteredSitemapWebsites.length + ' websites available'"></span>
                    </div>

                    {{-- Custom Searchable Trigger Button --}}
                    <button type="button"
                            @click="openSitemapWebsiteDropdown = !openSitemapWebsiteDropdown"
                            class="w-full text-left bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700/90 rounded-2xl p-2.5 sm:px-3.5 sm:py-2.5 transition-all shadow-xs hover:border-purple-300 dark:hover:border-purple-600 focus:outline-hidden focus:ring-2 focus:ring-purple-500/40 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                            <span class="relative flex h-2.5 w-2.5 shrink-0">
                                <template x-if="isSelectedWebsiteMapped">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                </template>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5" :class="isSelectedWebsiteMapped ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                            </span>

                            <div class="min-w-0 flex-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="text-xs sm:text-sm font-black text-slate-900 dark:text-slate-100 truncate" x-text="selectedSitemapWebsite?.name || 'Select website'"></span>
                                <span class="font-mono text-[11px] text-slate-400 dark:text-slate-500 truncate" x-text="'(' + (selectedSitemapWebsite?.domain || '') + ')'"></span>

                                {{-- Class Badge --}}
                                <template x-if="selectedSitemapWebsite?.class">
                                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wide bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200/80 dark:border-purple-800/80" x-text="selectedSitemapWebsite.class"></span>
                                </template>

                                {{-- Handler Badge --}}
                                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800/80 text-[11px] font-semibold text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700">
                                    <template x-if="selectedSitemapWebsite?.handler">
                                        <div class="inline-flex items-center gap-1.5">
                                            <img :src="selectedSitemapWebsite.handler.avatar_url" class="w-3.5 h-3.5 rounded-full object-cover">
                                            <span class="truncate max-w-[120px]" x-text="selectedSitemapWebsite.handler.name"></span>
                                        </div>
                                    </template>
                                    <template x-if="!selectedSitemapWebsite?.handler">
                                        <span class="italic text-slate-400 text-[10px]">Unassigned</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0 text-slate-400">
                            <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 hidden sm:inline">Change</span>
                            <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': openSitemapWebsiteDropdown }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </button>

                    {{-- Custom Searchable Dropdown Floating Panel --}}
                    <div x-show="openSitemapWebsiteDropdown"
                         x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1 scale-98"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-1 scale-98"
                         class="absolute left-0 right-0 top-full mt-2 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-2xl z-50 p-3 space-y-2.5">

                        {{-- Search Input with Clear Button --}}
                        <div class="relative">
                            <svg class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            <input type="text"
                                   x-model="sitemapWebsiteSearch"
                                   placeholder="Search website, domain, class, handler..."
                                   class="w-full text-xs pl-9 pr-8 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-100 placeholder-slate-400 outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-500">
                            <button type="button"
                                    x-show="sitemapWebsiteSearch"
                                    @click="sitemapWebsiteSearch = ''"
                                    class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        {{-- Quick Filter Chips (Linked Status & Class) --}}
                        <div class="flex items-center gap-1.5 flex-wrap pt-0.5 text-[11px]">
                            {{-- Mapping filters --}}
                            <button type="button"
                                    @click="sitemapMappingFilter = 'all'"
                                    class="px-2 py-0.5 rounded-lg font-bold transition-colors"
                                    :class="sitemapMappingFilter === 'all' ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'">
                                All Websites (<span x-text="allWebsites.length"></span>)
                            </button>
                            <button type="button"
                                    @click="sitemapMappingFilter = 'linked'"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg font-bold transition-colors"
                                    :class="sitemapMappingFilter === 'linked' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                <span>Linked in GSC (<span x-text="allWebsites.filter(w => w.is_mapped || w.site_url).length"></span>)</span>
                            </button>
                            <button type="button"
                                    @click="sitemapMappingFilter = 'unlinked'"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg font-bold transition-colors"
                                    :class="sitemapMappingFilter === 'unlinked' ? 'bg-amber-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                <span>Unlinked (<span x-text="allWebsites.filter(w => !w.is_mapped && !w.site_url).length"></span>)</span>
                            </button>

                            {{-- Class Filter dropdown or selector --}}
                            <div class="ml-auto flex items-center gap-1">
                                <span class="text-[10px] text-slate-400 uppercase font-semibold">Class:</span>
                                <select x-model="sitemapClassFilter" class="text-[10.5px] font-semibold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-0.5 text-slate-700 dark:text-slate-300 outline-none">
                                    <option value="all">All Classes</option>
                                    <template x-for="c in availableClasses" :key="c">
                                        <option :value="c" x-text="c"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        {{-- Website List with Virtual Scroll / Max Height --}}
                        <div class="max-h-72 overflow-y-auto space-y-1 pr-1 divide-y divide-slate-100/70 dark:divide-slate-800/50">
                            <template x-for="w in filteredSitemapWebsites" :key="w.id">
                                <div @click="selectSitemapWebsite(w.id)"
                                     class="pt-1.5 first:pt-0 flex items-center justify-between gap-3 p-2.5 rounded-xl cursor-pointer transition-all"
                                     :class="String(sitemapWebsiteId) === String(w.id) ? 'bg-purple-50 dark:bg-purple-950/60 border border-purple-200 dark:border-purple-800/80 shadow-xs' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60 border border-transparent'">
                                    
                                    {{-- Left: Status, Name, Domain, Class, Handler --}}
                                    <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                        <span class="w-2 h-2 rounded-full shrink-0"
                                              :class="w.is_mapped ? 'bg-emerald-500 ring-2 ring-emerald-400/30' : 'bg-slate-300 dark:bg-slate-600'"
                                              :title="w.is_mapped ? 'Linked with GSC' : 'Unlinked'"></span>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate" x-text="w.name"></span>

                                                {{-- Class Badge --}}
                                                <span class="px-2 py-0.5 rounded-md text-[9.5px] font-black uppercase tracking-tight bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200/80 dark:border-slate-700"
                                                      x-text="w.class || 'General'"></span>

                                                {{-- Status indicator if unlinked --}}
                                                <template x-if="!w.is_mapped">
                                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 border border-amber-200/70 dark:border-amber-800/60">
                                                        Unlinked
                                                    </span>
                                                </template>
                                            </div>

                                            <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-400 dark:text-slate-500 truncate">
                                                <span class="font-mono text-slate-500 dark:text-slate-400 truncate" x-text="w.domain"></span>
                                                <span>&bull;</span>

                                                {{-- Responsible Member / Handler Badge with Avatar --}}
                                                <div class="flex items-center gap-1.5 truncate text-slate-700 dark:text-slate-300">
                                                    <template x-if="w.handler">
                                                        <div class="flex items-center gap-1 truncate">
                                                            <img :src="w.handler.avatar_url" class="w-3.5 h-3.5 rounded-full object-cover shrink-0">
                                                            <span class="font-medium truncate" x-text="w.handler.name"></span>
                                                        </div>
                                                    </template>
                                                    <template x-if="!w.handler">
                                                        <span class="italic text-slate-400 text-[10px]">Unassigned</span>
                                                    </template>
                                                </div>

                                                {{-- Extra Assignees if any --}}
                                                <template x-if="w.assignees && w.assignees.length > 0">
                                                    <div class="hidden sm:inline-flex items-center gap-1 text-[10px] text-slate-400">
                                                        <span>+</span>
                                                        <span x-text="w.assignees.length + ' member' + (w.assignees.length > 1 ? 's' : '')"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Right: Checkmark if active --}}
                                    <div class="shrink-0">
                                        <template x-if="String(sitemapWebsiteId) === String(w.id)">
                                            <div class="w-5 h-5 rounded-full bg-purple-600 dark:bg-purple-500 text-white flex items-center justify-center shadow-xs">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Empty State --}}
                            <template x-if="filteredSitemapWebsites.length === 0">
                                <div class="py-8 text-center text-xs text-slate-400 dark:text-slate-500">
                                    <svg class="w-7 h-7 mx-auto mb-1.5 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                    </svg>
                                    <p class="font-bold text-slate-700 dark:text-slate-200">No matching websites found</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Try searching for a different domain, class, or handler</p>
                                    <button type="button" @click="sitemapWebsiteSearch = ''; sitemapClassFilter = 'all'; sitemapMappingFilter = 'all'" class="mt-2 px-3 py-1 text-[11px] font-bold text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-950/50 hover:bg-purple-100 rounded-lg transition-colors">
                                        Reset Filters
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Status Pills --}}
                <div class="flex flex-wrap items-center gap-2">
                    <template x-if="isSelectedWebsiteMapped">
                        <a :href="'https://search.google.com/search-console/index?resource_id=' + encodeURIComponent(selectedSitemapProperty || selectedSitemapWebsite?.site_url || '')" target="_blank" class="flex items-center gap-2 px-3 py-2 bg-emerald-50 dark:bg-emerald-950/50 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-emerald-800 dark:text-emerald-200 text-xs font-bold transition-all shadow-xs group" title="Click to open this property directly in Google Search Console">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Linked in GSC:</span>
                            <code class="px-1.5 py-0.5 bg-emerald-100 dark:bg-emerald-900/60 rounded text-[11px] group-hover:underline" x-text="selectedSitemapWebsite?.site_url"></code>
                            <svg class="w-3.5 h-3.5 opacity-70 group-hover:opacity-100 ml-0.5 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                        </a>
                    </template>
                    <template x-if="!isSelectedWebsiteMapped">
                        <div class="flex items-center gap-2 px-3 py-2 bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800 rounded-xl text-amber-800 dark:text-amber-200 text-xs font-bold">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            <span>Not Linked in GSC</span>
                            <button type="button" @click="openPropertyModalFor(sitemapWebsiteId)" class="underline hover:text-amber-950 dark:hover:text-amber-100 ml-1">
                                Link Property Now →
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Unlinked Website Warning Callout (If unlinked website is selected) --}}
        <div x-show="!isSelectedWebsiteMapped" x-cloak class="p-6 bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border border-amber-300 dark:border-amber-700/60 rounded-3xl space-y-3">
            <div class="flex items-start gap-4">
                <div class="p-3 bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 rounded-2xl">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h4 class="text-sm font-bold text-amber-900 dark:text-amber-200">
                        This Website is Not Yet Linked to Google Search Console
                    </h4>
                    <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                        To view indexed/not indexed pages, submit XML sitemaps, inspect live URLs, and test crawl status, please link this website record to your verified Google Search Console property.
                    </p>
                    <div class="mt-3 flex items-center gap-3">
                        <button type="button" @click="openPropertyModalFor(sitemapWebsiteId)" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl transition-all shadow-xs">
                            <span>Link Property Now</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                        </button>
                        <button type="button" @click="setView('properties')" class="px-3.5 py-2 text-xs font-semibold text-amber-800 dark:text-amber-300 hover:underline">
                            Open Property Manager
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Sitemaps & Indexation Content (When Website Is Linked) --}}
        <div x-show="isSelectedWebsiteMapped" class="space-y-6">

            {{-- Official Google Search Console Indexing Snapshot Banner --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 bg-gradient-to-r from-emerald-500/10 via-slate-50 dark:via-slate-800/80 to-purple-500/10 border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-xs">
                <div class="flex items-center gap-2.5 flex-wrap">
                    <span class="flex h-2.5 w-2.5 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                    </span>
                    <span class="text-xs font-black text-slate-800 dark:text-slate-100">
                        Google Search Console Page Indexing Report
                    </span>
                    <template x-if="indexationSummary.gsc_last_updated">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300" x-text="'Auto-synced: ' + indexationSummary.gsc_last_updated"></span>
                    </template>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">
                        • Auto-synced live from Google Search Console (sitemaps, indexing & inspections)
                    </span>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <a :href="'https://search.google.com/search-console/index?resource_id=' + encodeURIComponent(selectedSitemapProperty || selectedSitemapWebsite?.site_url || '')" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/60 rounded-xl border border-blue-200 dark:border-blue-800 transition-colors shadow-xs" title="Open this exact property directly in Google Search Console">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                        <span>Open GSC ↗</span>
                    </a>
                    <button type="button" @click="startBackgroundInspection()" :disabled="scanningBackground" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 rounded-xl border border-indigo-200 dark:border-indigo-800 transition-colors shadow-xs" title="Automatically inspect and verify all pages via Google's official URL Inspection API in the background">
                        <svg class="w-3.5 h-3.5" :class="scanningBackground ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <span x-text="scanningBackground ? scanProgressText : 'Auto-Scan All Pages'"></span>
                    </button>
                    <button type="button" @click="openCoverageModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-emerald-700 dark:text-emerald-300 bg-white dark:bg-slate-800 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 rounded-xl border border-emerald-300 dark:border-emerald-700 transition-colors shadow-xs" title="Edit official Google Search Console snapshot numbers">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                        <span>Edit GSC Snapshot</span>
                    </button>
                    <button type="button" @click="autoSyncGscIndexing()" :disabled="indexationLoading" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 rounded-xl shadow-xs transition-all disabled:opacity-50">
                        <svg class="w-3.5 h-3.5" :class="indexationLoading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span x-text="indexationLoading ? 'Auto-Syncing GSC...' : 'Auto-Sync from GSC'"></span>
                    </button>
                    <button type="button" @click="loadIndexStatus(1, true); loadSitemaps()" :disabled="indexationLoading" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl border border-slate-200 dark:border-slate-700 transition-colors" title="Reload from GSC">
                        <svg class="w-3.5 h-3.5" :class="indexationLoading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>

            {{-- 4 Metric Summary Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Indexed Pages --}}
                <div class="p-5 bg-gradient-to-br from-emerald-500/10 via-white dark:via-slate-800 to-white dark:to-slate-800 border border-emerald-200 dark:border-emerald-800/60 rounded-2xl shadow-xs space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Indexed Pages</span>
                            <button type="button" @click="openCoverageModal()" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-100/70 dark:bg-emerald-900/60 hover:bg-emerald-200 transition-colors" title="Edit official GSC numbers">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                <span>Edit</span>
                            </button>
                        </div>
                        <div class="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-300 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-black text-slate-900 dark:text-slate-100" x-text="indexationSummary.total_indexed.toLocaleString()"></span>
                        <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400" x-text="'(' + indexationSummary.indexation_rate + '%)'"></span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-emerald-500 h-1.5 rounded-full" :style="'width: ' + Math.min(100, indexationSummary.indexation_rate) + '%'"></div>
                    </div>
                    <p class="text-[11px] text-slate-500">Confirmed indexed by Google and appearing in search results.</p>
                </div>

                {{-- Not Indexed Pages --}}
                <div class="p-5 bg-gradient-to-br from-amber-500/10 via-white dark:via-slate-800 to-white dark:to-slate-800 border border-amber-200 dark:border-amber-800/60 rounded-2xl shadow-xs space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Not Indexed / Excluded</span>
                            <button type="button" @click="openCoverageModal()" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-bold text-amber-700 dark:text-amber-300 bg-amber-100/70 dark:bg-amber-900/60 hover:bg-amber-200 transition-colors" title="Edit official GSC numbers">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                <span>Edit</span>
                            </button>
                        </div>
                        <div class="w-7 h-7 rounded-lg bg-amber-100 dark:bg-amber-900/60 text-amber-600 dark:text-amber-300 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-black text-slate-900 dark:text-slate-100" x-text="indexationSummary.total_not_indexed.toLocaleString()"></span>
                        <template x-if="indexationSummary.gsc_reasons_count">
                            <span class="text-xs font-bold text-amber-600 dark:text-amber-400" x-text="'(' + indexationSummary.gsc_reasons_count + ' reasons)'"></span>
                        </template>
                    </div>
                    <p class="text-[11px] text-slate-500">Crawled but not indexed, discovered, noindex, or 404.</p>
                    <template x-if="Array.isArray(indexationSummary.gsc_reasons) && indexationSummary.gsc_reasons.length > 0">
                        <div class="pt-1 flex flex-wrap gap-1">
                            <template x-for="r in indexationSummary.gsc_reasons.slice(0, 3)" :key="r.reason">
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100/70 dark:bg-amber-950/60 text-amber-900 dark:text-amber-200 border border-amber-200/60 dark:border-amber-800/60">
                                    <strong x-text="r.count"></strong>
                                    <span class="truncate max-w-[130px]" x-text="r.reason"></span>
                                </span>
                            </template>
                        </div>
                    </template>
                    <button type="button" @click="indexationFilter = 'not_indexed'; loadIndexStatus(1)" class="text-[11px] font-bold text-amber-600 dark:text-amber-400 hover:underline block pt-0.5">
                        Filter Not Indexed Pages →
                    </button>
                </div>

                {{-- Submitted Sitemaps --}}
                <div class="p-5 bg-gradient-to-br from-blue-500/10 via-white dark:via-slate-800 to-white dark:to-slate-800 border border-blue-200 dark:border-blue-800/60 rounded-2xl shadow-xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400">Google Sitemaps</span>
                        <div class="w-7 h-7 rounded-lg bg-blue-100 dark:bg-blue-900/60 text-blue-600 dark:text-blue-300 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-black text-slate-900 dark:text-slate-100" x-text="sitemapsList.length || indexationSummary.sitemaps_count || 0"></span>
                        <span class="text-xs text-slate-400">Active</span>
                    </div>
                    <p class="text-[11px] text-slate-500">Live sitemaps processed by Google Search Console.</p>
                </div>

                {{-- URLs in Sitemaps --}}
                <div class="p-5 bg-gradient-to-br from-purple-500/10 via-white dark:via-slate-800 to-white dark:to-slate-800 border border-purple-200 dark:border-purple-800/60 rounded-2xl shadow-xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-purple-700 dark:text-purple-400">Sitemap URLs</span>
                        <div class="w-7 h-7 rounded-lg bg-purple-100 dark:bg-purple-900/60 text-purple-600 dark:text-purple-300 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" /></svg>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-black text-slate-900 dark:text-slate-100" x-text="(indexationSummary.sitemaps_submitted_urls || 0).toLocaleString()"></span>
                        <span class="text-xs text-slate-400">Web Pages</span>
                    </div>
                    <p class="text-[11px] text-slate-500">Indexed from sitemaps: <strong class="text-slate-700 dark:text-slate-300" x-text="(indexationSummary.sitemaps_indexed_urls || 0).toLocaleString()"></strong></p>
                </div>
            </div>

            {{-- SECTION 1: XML Sitemap Submission & Google API Hub --}}
            <div class="p-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-3xl shadow-sm space-y-6">
                <div>
                    <div class="flex items-center gap-2">
                        <h4 class="text-base font-black text-slate-900 dark:text-slate-100">
                            Submit & Manage Sitemaps (Google Search Console)
                        </h4>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">
                            API v3
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Submit a new sitemap URL to Google Search Console or monitor previously submitted sitemaps.
                    </p>
                </div>

                {{-- Submission Form --}}
                <div class="p-4 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-2xl space-y-3">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        Submit New Sitemap URL to Google:
                    </label>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                            </span>
                            <input type="url" x-model="sitemapUrlInput" placeholder="https://example.com/sitemap.xml" class="w-full pl-9 pr-3 py-2 text-xs font-medium bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-hidden focus:ring-2 focus:ring-purple-500">
                        </div>

                        {{-- Test Reachability button --}}
                        <button type="button" @click="testSitemapReachability()" :disabled="sitemapTesting" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xs transition-colors whitespace-nowrap">
                            <svg class="w-3.5 h-3.5 text-amber-500" :class="{ 'animate-spin': sitemapTesting }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                            </svg>
                            <span x-text="sitemapTesting ? 'Testing URL...' : 'Test Reachability'"></span>
                        </button>

                        {{-- Submit to Google button --}}
                        <button type="button" @click="submitSitemap()" :disabled="sitemapSubmitting || !sitemapUrlInput" class="inline-flex items-center justify-center gap-2 px-5 py-2 text-xs font-bold text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 rounded-xl shadow-md shadow-purple-500/20 disabled:opacity-50 transition-all whitespace-nowrap">
                            <svg class="w-4 h-4" :class="{ 'animate-spin': sitemapSubmitting }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                            </svg>
                            <span x-text="sitemapSubmitting ? 'Submitting to Google...' : 'Submit to Google Search Console'"></span>
                        </button>
                    </div>

                    {{-- Quick preset suggestion tags --}}
                    <div class="flex flex-wrap items-center gap-1.5 pt-1 text-[11px] text-slate-500">
                        <span>Quick Presets:</span>
                        <button type="button" @click="sitemapUrlInput = 'https://' + (selectedSitemapWebsite?.domain || '') + '/sitemap.xml'" class="px-2 py-0.5 bg-slate-200/80 dark:bg-slate-800 rounded-lg hover:bg-slate-300 font-mono text-[10px] text-slate-700 dark:text-slate-300">/sitemap.xml</button>
                        <button type="button" @click="sitemapUrlInput = 'https://' + (selectedSitemapWebsite?.domain || '') + '/sitemap_index.xml'" class="px-2 py-0.5 bg-slate-200/80 dark:bg-slate-800 rounded-lg hover:bg-slate-300 font-mono text-[10px] text-slate-700 dark:text-slate-300">/sitemap_index.xml</button>
                        <button type="button" @click="sitemapUrlInput = 'https://' + (selectedSitemapWebsite?.domain || '') + '/wp-sitemap.xml'" class="px-2 py-0.5 bg-slate-200/80 dark:bg-slate-800 rounded-lg hover:bg-slate-300 font-mono text-[10px] text-slate-700 dark:text-slate-300">/wp-sitemap.xml</button>
                    </div>

                    {{-- Live Reachability Test Result Box --}}
                    <template x-if="sitemapTestResult">
                        <div class="p-3 rounded-xl border text-xs font-semibold flex items-center justify-between" :class="sitemapTestResult.reachable ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200' : 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200'">
                            <div class="flex items-center gap-2">
                                <span x-text="sitemapTestResult.reachable ? '✓ Reachability Verified:' : '✗ Reachability Test Failed:'"></span>
                                <span class="font-normal" x-text="sitemapTestResult.message"></span>
                            </div>
                            <span class="text-[10px] font-mono opacity-80" x-text="sitemapTestResult.response_time || ''"></span>
                        </div>
                    </template>

                    {{-- Submission Feedback Alert --}}
                    <template x-if="sitemapFeedback">
                        <div class="p-3.5 rounded-xl border text-xs font-semibold flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs" :class="sitemapFeedback.type === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200' : 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200'">
                            <div class="flex items-center gap-2">
                                <span x-text="sitemapFeedback.text"></span>
                            </div>
                            <template x-if="sitemapFeedback.requiresReconnect">
                                <a href="{{ route('google.search-console.connect') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shrink-0 shadow-xs transition-colors">
                                    <span>Reconnect Google Account ↗</span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Submitted Sitemaps List Table --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h5 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            Submitted Sitemaps in Google Search Console (<span x-text="sitemapsList.length"></span>)
                        </h5>
                        <span class="text-[11px] text-slate-400">Live data from Google API</span>
                    </div>

                    <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-2xl">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-50 dark:bg-slate-900/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="py-3 px-4">Sitemap URL</th>
                                    <th class="py-3 px-4">Type</th>
                                    <th class="py-3 px-4">Submitted</th>
                                    <th class="py-3 px-4">Last Downloaded</th>
                                    <th class="py-3 px-4">Status</th>
                                    <th class="py-3 px-4 text-center">URLs Discovered</th>
                                    <th class="py-3 px-4 text-center">Issues</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                                <template x-if="sitemapsLoading">
                                    <tr>
                                        <td colspan="8" class="py-8 text-center text-slate-400">
                                            <div class="flex items-center justify-center gap-2">
                                                <svg class="w-4 h-4 animate-spin text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                                <span>Fetching submitted sitemaps from Google...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <template x-if="!sitemapsLoading && sitemapsList.length === 0">
                                    <tr>
                                        <td colspan="8" class="py-8 text-center text-slate-400">
                                            No sitemaps have been submitted to Google Search Console for this website yet. Use the form above to submit your first XML sitemap.
                                        </td>
                                    </tr>
                                </template>

                                <template x-for="sm in sitemapsList" :key="sm.path">
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-850/50 transition-colors">
                                        <td class="py-3 px-4 font-mono font-medium text-slate-900 dark:text-slate-100 max-w-xs truncate">
                                            <a :href="sm.path" target="_blank" class="hover:text-purple-600 dark:hover:text-purple-400 flex items-center gap-1.5" :title="sm.path">
                                                <span x-text="sm.path"></span>
                                                <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                            </a>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300" x-text="sm.type"></span>
                                        </td>
                                        <td class="py-3 px-4 text-slate-500 whitespace-nowrap text-[11px]" x-text="sm.last_submitted ? new Date(sm.last_submitted).toLocaleDateString() : 'N/A'"></td>
                                        <td class="py-3 px-4 text-slate-500 whitespace-nowrap text-[11px]" x-text="sm.last_downloaded ? new Date(sm.last_downloaded).toLocaleDateString() : 'N/A'"></td>
                                        <td class="py-3 px-4 whitespace-nowrap">
                                            <template x-if="sm.status === 'success'">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                    ✓ Success
                                                </span>
                                            </template>
                                            <template x-if="sm.status === 'pending'">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300">
                                                    ⏳ Processing
                                                </span>
                                            </template>
                                            <template x-if="sm.status === 'warning'">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                    ⚠️ Warnings
                                                </span>
                                            </template>
                                            <template x-if="sm.status === 'error'">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                                    ✗ Errors
                                                </span>
                                            </template>
                                            <template x-if="sm.status === 'detected'">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300">
                                                    🌐 Live on Site
                                                </span>
                                            </template>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="font-bold text-slate-900 dark:text-slate-100" x-text="(sm.submitted_urls || 0).toLocaleString()"></span>
                                            <template x-if="sm.indexed_urls > 0">
                                                <span class="text-[10px] text-emerald-600 block" x-text="'(' + sm.indexed_urls + ' indexed)'"></span>
                                            </template>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="text-xs" :class="sm.errors > 0 ? 'text-rose-600 font-bold' : 'text-slate-400'" x-text="sm.errors > 0 ? sm.errors + ' errors' : 'None'"></span>
                                        </td>
                                        <td class="py-3 px-4 text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <template x-if="sm.status === 'detected' || sm.is_detected_on_site">
                                                    <button type="button" @click="sitemapUrlInput = sm.path; submitSitemap()" title="Submit to Google Search Console" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold text-white bg-purple-600 hover:bg-purple-700 rounded-lg shadow-xs transition-colors">
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg>
                                                        <span>Submit to Google</span>
                                                    </button>
                                                </template>
                                                <template x-if="sm.status !== 'detected' && !sm.is_detected_on_site">
                                                    <div class="flex items-center gap-1.5">
                                                        <button type="button" @click="sitemapUrlInput = sm.path; submitSitemap()" title="Resubmit to Google" class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/40 rounded-lg transition-colors">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                                        </button>
                                                        <button type="button" @click="deleteSitemap(sm.path)" title="Delete from Google Search Console" class="p-1.5 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition-colors">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- SECTION 2: Page Indexation Coverage & Live Google URL Inspector --}}
            <div class="p-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-3xl shadow-sm space-y-6">
                <div>
                    <div class="flex items-center gap-2">
                        <h4 class="text-base font-black text-slate-900 dark:text-slate-100">
                            Page Indexation Coverage & Live Google URL Inspector
                        </h4>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                            URL Inspection API
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Test any URL on <span class="font-bold text-slate-700 dark:text-slate-300" x-text="selectedSitemapWebsite?.name"></span> in real-time to inspect crawl state, indexing permissions, canonicalization, and mobile usability.
                    </p>
                </div>

                {{-- Live URL Inspector Tool Box --}}
                <div class="p-4 bg-gradient-to-r from-purple-500/5 via-indigo-500/5 to-transparent border border-purple-200 dark:border-purple-800/60 rounded-2xl space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-purple-900 dark:text-purple-200">
                            Inspect URL, Test Live Reachability & Request Indexing:
                        </label>
                        <span class="text-[11px] text-slate-400">Like Google Search Console URL Inspector</span>
                    </div>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                            </span>
                            <input type="url" x-model="inspectUrlInput" placeholder="https://example.com/page-to-inspect" class="w-full pl-9 pr-3 py-2 text-xs font-medium bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-hidden focus:ring-2 focus:ring-purple-500">
                        </div>

                        {{-- 1. Inspect on Google API button --}}
                        <button type="button" @click="inspectSingleUrl(inspectUrlInput)" :disabled="inspectingUrl || !inspectUrlInput" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 rounded-xl shadow-md shadow-indigo-500/20 disabled:opacity-50 transition-all whitespace-nowrap" title="Fetch Google Search Console indexed status and crawl history">
                            <svg class="w-4 h-4" :class="{ 'animate-spin': inspectingUrl }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            <span x-text="inspectingUrl ? 'Inspecting GSC...' : 'Inspect GSC API'"></span>
                        </button>

                        {{-- 2. Test Live URL button --}}
                        <button type="button" @click="testLiveUrl(inspectUrlInput)" :disabled="testingLiveUrl || !inspectUrlInput" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 border border-emerald-300 dark:border-emerald-800 rounded-xl shadow-xs disabled:opacity-50 transition-all whitespace-nowrap" title="Test live webpage reachability, HTTP status, and robots meta tags right now">
                            <svg class="w-4 h-4 text-emerald-600" :class="{ 'animate-spin': testingLiveUrl }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                            </svg>
                            <span x-text="testingLiveUrl ? 'Testing Live...' : 'Test Live URL'"></span>
                        </button>

                        {{-- 3. Open in GSC to Request Indexing --}}
                        <a :href="getGscInspectUrl(inspectUrlInput)" target="_blank" @click="openGscForUrl(inspectUrlInput, $event)" :class="!inspectUrlInput ? 'pointer-events-none opacity-50' : ''" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-md shadow-blue-500/20 transition-all whitespace-nowrap" title="Open directly in Google Search Console (copies URL to clipboard)">
                            <span>Request Indexing in GSC ↗</span>
                        </a>
                    </div>

                    {{-- Quick preset inspection tags --}}
                    <div class="flex flex-wrap items-center gap-1.5 pt-1 text-[11px] text-slate-500">
                        <span>Quick Presets:</span>
                        <button type="button" @click="inspectUrlInput = 'https://' + (selectedSitemapWebsite?.domain || '') + '/'" class="px-2 py-0.5 bg-slate-200/80 dark:bg-slate-800 rounded-lg hover:bg-slate-300 font-mono text-[10px] text-slate-700 dark:text-slate-300">Homepage (/)</button>
                        <button type="button" @click="inspectUrlInput = 'https://' + (selectedSitemapWebsite?.domain || '') + '/sitemap.xml'" class="px-2 py-0.5 bg-slate-200/80 dark:bg-slate-800 rounded-lg hover:bg-slate-300 font-mono text-[10px] text-slate-700 dark:text-slate-300">/sitemap.xml</button>
                        <template x-if="indexationPages.find(p => !p.is_indexed)">
                            <button type="button" @click="inspectUrlInput = indexationPages.find(p => !p.is_indexed)?.url || inspectUrlInput" class="px-2 py-0.5 bg-amber-100 dark:bg-amber-950/60 rounded-lg hover:bg-amber-200 font-mono text-[10px] text-amber-800 dark:text-amber-300 font-bold">Sample Not Indexed Page</button>
                        </template>
                    </div>

                    {{-- Live URL Inspection Result Display (From Google Search Console API) --}}
                    <template x-if="inspectionResult">
                        <div class="mt-4 p-4 bg-white dark:bg-slate-850 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-black uppercase tracking-wider" :class="{
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300': inspectionResult.is_indexed,
                                            'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300': !inspectionResult.is_indexed && inspectionResult.verdict === 'NEUTRAL',
                                            'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300': !inspectionResult.is_indexed && inspectionResult.verdict === 'FAIL'
                                        }" x-text="inspectionResult.is_indexed ? '✓ Page is Indexed by Google' : '✗ Page is Not Indexed by Google'"></span>
                                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300" x-text="'Verdict: ' + inspectionResult.verdict"></span>
                                    </div>
                                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 font-semibold" x-text="'Coverage State: ' + inspectionResult.coverage_state"></p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="testLiveUrl(inspectionResult.url || inspectUrlInput)" :disabled="testingLiveUrl" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 rounded-xl border border-emerald-200 dark:border-emerald-800 transition-colors">
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" /></svg>
                                        <span>Test Live</span>
                                    </button>
                                    <a :href="getGscInspectUrl(inspectionResult.url || inspectUrlInput)" target="_blank" @click="openGscForUrl(inspectionResult.url || inspectUrlInput, $event)" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-xs transition-colors" title="Open in Google Search Console (copies URL to clipboard for Request Indexing)">
                                        <span>Request Indexing in GSC ↗</span>
                                    </a>
                                </div>
                            </div>

                            {{-- 4 Detailed Inspection Indicator Tiles --}}
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                                <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200/60 dark:border-slate-800">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Indexing Allowed?</div>
                                    <div class="text-xs font-bold mt-0.5" :class="inspectionResult.indexing_state === 'INDEXING_ALLOWED' ? 'text-emerald-600' : 'text-rose-600'" x-text="inspectionResult.indexing_state || 'Allowed'"></div>
                                </div>
                                <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200/60 dark:border-slate-800">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Page Fetch</div>
                                    <div class="text-xs font-bold mt-0.5 text-slate-800 dark:text-slate-200" x-text="inspectionResult.page_fetch_state || 'SUCCESSFUL'"></div>
                                </div>
                                <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200/60 dark:border-slate-800">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Robots.txt</div>
                                    <div class="text-xs font-bold mt-0.5" :class="inspectionResult.robots_txt_state === 'DISALLOWED' ? 'text-rose-600' : 'text-emerald-600'" x-text="inspectionResult.robots_txt_state || 'ALLOWED'"></div>
                                </div>
                                <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200/60 dark:border-slate-800">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Primary Crawler</div>
                                    <div class="text-xs font-bold mt-0.5 text-slate-800 dark:text-slate-200" x-text="inspectionResult.crawled_as || 'Googlebot Smartphone'"></div>
                                </div>
                            </div>

                            {{-- Crawl & Canonical Details --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs bg-slate-50 dark:bg-slate-900/60 p-3 rounded-xl border border-slate-200/60 dark:border-slate-800">
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Google-Selected Canonical:</span>
                                    <span class="font-mono text-[11px] text-slate-700 dark:text-slate-300 break-all" x-text="inspectionResult.google_canonical || 'Same as inspected URL'"></span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Last Crawled by Google:</span>
                                    <span class="font-semibold text-slate-700 dark:text-slate-300" x-text="inspectionResult.last_crawl_time ? new Date(inspectionResult.last_crawl_time).toLocaleString() : 'N/A'"></span>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Live Crawl Test Result Box (Real-Time Server Fetch) --}}
                    <template x-if="liveTestResult">
                        <div class="mt-4 p-4 bg-gradient-to-br from-emerald-500/5 via-white dark:via-slate-850 to-indigo-500/5 border border-emerald-300 dark:border-emerald-800 rounded-2xl shadow-sm space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-black uppercase tracking-wider" :class="liveTestResult.is_indexable ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300'" x-text="liveTestResult.is_indexable ? '✓ Page is Live & Indexable' : '⚠️ Page Cannot Be Indexed'"></span>
                                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300" x-text="'HTTP ' + liveTestResult.status_code + ' (' + liveTestResult.response_time + ')'"></span>
                                    </div>
                                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 font-semibold" x-text="liveTestResult.message"></p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a :href="getGscInspectUrl(liveTestResult.url)" target="_blank" @click="openGscForUrl(liveTestResult.url, $event)" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 rounded-xl shadow-xs transition-colors" title="Open in Google Search Console (copies URL to clipboard for Request Indexing)">
                                        <span>Request Indexing in GSC ↗</span>
                                    </a>
                                </div>
                            </div>

                            {{-- 4 Live Indicator Tiles --}}
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                                <div class="p-2.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/60 dark:border-slate-800">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Live Reachability</div>
                                    <div class="text-xs font-bold mt-0.5" :class="liveTestResult.reachable ? 'text-emerald-600' : 'text-rose-600'" x-text="liveTestResult.reachable ? '200 OK (' + liveTestResult.response_time + ')' : 'Failed'"></div>
                                </div>
                                <div class="p-2.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/60 dark:border-slate-800">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Robots.txt</div>
                                    <div class="text-xs font-bold mt-0.5" :class="liveTestResult.robots_txt_state === 'DISALLOWED' ? 'text-rose-600' : 'text-emerald-600'" x-text="liveTestResult.robots_txt_state"></div>
                                </div>
                                <div class="p-2.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/60 dark:border-slate-800">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Meta Robots</div>
                                    <div class="text-xs font-bold mt-0.5" :class="liveTestResult.has_noindex ? 'text-rose-600' : 'text-emerald-600'" x-text="liveTestResult.meta_robots || 'index, follow'"></div>
                                </div>
                                <div class="p-2.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/60 dark:border-slate-800">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Indexation Permission</div>
                                    <div class="text-xs font-bold mt-0.5" :class="liveTestResult.is_indexable ? 'text-emerald-600' : 'text-rose-600'" x-text="liveTestResult.indexing_state"></div>
                                </div>
                            </div>

                            {{-- Live Details & Canonical --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs bg-white dark:bg-slate-900/60 p-3 rounded-xl border border-slate-200/60 dark:border-slate-800">
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Detected Canonical Link:</span>
                                    <span class="font-mono text-[11px] text-slate-700 dark:text-slate-300 break-all" x-text="liveTestResult.canonical_url"></span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Live Page Title:</span>
                                    <span class="font-semibold text-slate-700 dark:text-slate-300 truncate block" x-text="liveTestResult.title || 'N/A'"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Pages Indexation Table with Filters --}}
                <div class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        {{-- Filter Tabs --}}
                        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700">
                            <button type="button" @click="indexationFilter = 'all'; loadIndexStatus(1)" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-colors" :class="indexationFilter === 'all' ? 'bg-white dark:bg-slate-800 text-purple-600 dark:text-purple-400 shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-200'">
                                All Pages (<span x-text="indexationSummary.total_discovered || 0"></span>)
                            </button>
                            <button type="button" @click="indexationFilter = 'indexed'; loadIndexStatus(1)" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-colors" :class="indexationFilter === 'indexed' ? 'bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-200'">
                                ✓ Indexed Only (<span x-text="indexationSummary.total_indexed || 0"></span>)
                            </button>
                            <button type="button" @click="indexationFilter = 'not_indexed'; loadIndexStatus(1)" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-colors" :class="indexationFilter === 'not_indexed' ? 'bg-white dark:bg-slate-800 text-amber-600 dark:text-amber-400 shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-200'">
                                ⚠️ Not Indexed (<span x-text="indexationSummary.total_not_indexed || 0"></span>)
                            </button>
                            <button type="button" @click="indexationFilter = 'inspected'; loadIndexStatus(1)" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-colors" :class="indexationFilter === 'inspected' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-200'">
                                🔍 Inspected via GSC
                            </button>
                        </div>

                        {{-- Search Filter --}}
                        <div class="relative w-full sm:w-64">
                            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                            </span>
                            <input type="text" x-model="indexationSearch" @input.debounce.350ms="loadIndexStatus(1)" placeholder="Filter page URL..." class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 focus:outline-hidden focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>

                    {{-- Table --}}
                    <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-2xl">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-50 dark:bg-slate-900/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="py-3 px-4">Page URL</th>
                                    <th class="py-3 px-4">Index Status</th>
                                    <th class="py-3 px-4">Coverage State / Reason</th>
                                    <th class="py-3 px-4 text-center">Clicks</th>
                                    <th class="py-3 px-4 text-center">Impressions</th>
                                    <th class="py-3 px-4 text-center">CTR</th>
                                    <th class="py-3 px-4 text-center">Position</th>
                                    <th class="py-3 px-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                                <template x-if="indexationLoading">
                                    <tr>
                                        <td colspan="8" class="py-8 text-center text-slate-400">
                                            <div class="flex items-center justify-center gap-2">
                                                <svg class="w-4 h-4 animate-spin text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                                <span>Loading page indexation audit...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <template x-if="!indexationLoading && indexationPages.length === 0">
                                    <tr>
                                        <td colspan="8" class="py-8 text-center text-slate-400">
                                            No pages match the selected indexation filter.
                                        </td>
                                    </tr>
                                </template>

                                <template x-for="p in indexationPages" :key="p.url">
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-850/50 transition-colors">
                                        <td class="py-3 px-4 font-mono font-medium text-slate-900 dark:text-slate-100 max-w-sm truncate">
                                            <a :href="p.url" target="_blank" class="hover:text-purple-600 dark:hover:text-purple-400 flex items-center gap-1.5" :title="p.url">
                                                <span x-text="p.url"></span>
                                                <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                            </a>
                                        </td>
                                        <td class="py-3 px-4 whitespace-nowrap">
                                            <template x-if="p.is_indexed">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    Indexed
                                                </span>
                                            </template>
                                            <template x-if="!p.is_indexed">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                    Not Indexed
                                                </span>
                                            </template>
                                        </td>
                                        <td class="py-3 px-4 text-slate-600 dark:text-slate-300 font-medium">
                                            <span x-text="p.coverage_state"></span>
                                            <template x-if="p.is_inspected">
                                                <span class="ml-1 text-[9px] px-1 py-0.2 rounded bg-purple-100 text-purple-700 font-bold">API Verified</span>
                                            </template>
                                        </td>
                                        <td class="py-3 px-4 text-center font-bold text-slate-900 dark:text-slate-100" x-text="(p.clicks || 0).toLocaleString()"></td>
                                        <td class="py-3 px-4 text-center text-slate-600 dark:text-slate-400" x-text="(p.impressions || 0).toLocaleString()"></td>
                                        <td class="py-3 px-4 text-center text-slate-600 dark:text-slate-400" x-text="(p.ctr || 0) + '%'"></td>
                                        <td class="py-3 px-4 text-center text-slate-600 dark:text-slate-400" x-text="p.position > 0 ? p.position : '-'"></td>
                                        <td class="py-3 px-4 text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1.5 justify-end">
                                                <button type="button" @click="inspectSingleUrl(p.url)" :disabled="inspectingUrl" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-950/50 hover:bg-purple-100 rounded-lg transition-colors" title="Inspect Google Search Console API">
                                                    <svg class="w-3 h-3 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                                                    <span>Inspect</span>
                                                </button>
                                                <button type="button" @click="testLiveUrl(p.url)" :disabled="testingLiveUrl" class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 hover:bg-emerald-100 rounded-lg transition-colors" title="Test Live Reachability & Indexability">
                                                    <svg class="w-3 h-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" /></svg>
                                                    <span>Live</span>
                                                </button>
                                                <a :href="getGscInspectUrl(p.url)" target="_blank" @click="openGscForUrl(p.url, $event)" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-xs transition-colors whitespace-nowrap" title="Open in Google Search Console (copies URL to clipboard for Request Indexing)">
                                                    <span>Request Indexing in GSC ↗</span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination Controls --}}
                    <div class="flex items-center justify-between text-xs text-slate-500 pt-2" x-show="indexationTotal > indexationPerPage">
                        <span x-text="'Showing ' + indexationPages.length + ' of ' + indexationTotal + ' pages'"></span>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="loadIndexStatus(indexationPage - 1)" :disabled="indexationPage <= 1" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 rounded-lg disabled:opacity-40 font-bold hover:bg-slate-200">
                                Previous
                            </button>
                            <span class="font-bold text-slate-800 dark:text-slate-200" x-text="'Page ' + indexationPage"></span>
                            <button type="button" @click="loadIndexStatus(indexationPage + 1)" :disabled="(indexationPage * indexationPerPage) >= indexationTotal" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 rounded-lg disabled:opacity-40 font-bold hover:bg-slate-200">
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- End When Website Is Linked --}}

    </div> {{-- ── End VIEW: Sitemaps & Page Indexation Hub ── --}}

    {{-- ── VIEW 5: Property Manager & Batch Mapping ────────────────────────── --}}
    <div x-show="activeView === 'properties'" style="{{ ($currentView ?? 'overview') === 'properties' ? '' : 'display: none;' }}" class="space-y-6">

        {{-- Google OAuth Connection Status Card --}}
        <div class="p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="p-3 bg-blue-50 dark:bg-blue-900/40 rounded-xl text-blue-600">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" /></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full {{ $account ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                            {{ $account ? 'Connected Account: ' . $account->email : 'Google Account Not Connected' }}
                        </h3>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $account ? 'Token auto-refreshed via OAuth refresh tokens. Batch map your websites to synchronized properties below.' : 'Connect your account to discover and map properties.' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('google.search-console.connect') }}" class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition-colors shadow-sm">
                    {{ $account ? 'Switch / Reconnect' : 'Connect Account' }}
                </a>
                @if($account)
                    @if(!empty($canDisconnect))
                        <form method="POST" action="{{ route('google.search-console.disconnect') }}" onsubmit="return confirm('Disconnect this Google Search Console account?')">
                            @csrf
                            <button type="submit" class="px-4 py-2 text-xs font-bold text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl transition-colors border border-rose-200 dark:border-rose-900/60">
                                Disconnect
                            </button>
                        </form>
                    @else
                        <div class="inline-flex items-center gap-1.5 px-3 py-2 text-[11px] font-semibold text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-850 rounded-xl cursor-not-allowed select-none border border-slate-200 dark:border-slate-700" title="Security: Only Mr. Dara (soporadara@kiuq.com) is authorized to disconnect this account.">
                            <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Disconnect (Dara Only)</span>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Batch Auto-Mapper & Manager Action Bar --}}
        <div class="p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-black text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <span>⚡ Smart Bulk Property Auto-Mapper</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Click "1-Click Auto-Match All" to automatically match all DGT websites with their verified Google Search Console properties, then save everything at once.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    {{-- 1-Click Auto Match All --}}
                    <button type="button" @click="loadAutoMatchData()" :disabled="batchLoading" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/60 hover:bg-blue-100 dark:hover:bg-blue-900/60 border border-blue-200 dark:border-blue-800 rounded-xl transition-all shadow-xs">
                        <svg class="w-4 h-4 text-blue-600" :class="{ 'animate-spin': batchLoading }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                        </svg>
                        <span x-text="batchLoading ? 'Scanning Google Properties...' : '⚡ 1-Click Auto-Match All'"></span>
                    </button>

                    {{-- Save All Mappings --}}
                    <button type="button" @click="saveBatchMappings()" :disabled="batchSaving || selectedBatchWebsites.length === 0" class="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 rounded-xl shadow-md shadow-emerald-500/20 disabled:opacity-50 transition-all">
                        <svg class="w-4 h-4" :class="{ 'animate-spin': batchSaving }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span x-text="batchSaving ? 'Saving & Syncing...' : '💾 Save All Selected (' + selectedBatchWebsites.length + ')'"></span>
                    </button>
                </div>
            </div>

            {{-- Flash Message if Saved --}}
            <div x-show="batchSuccessMessage" x-cloak class="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 rounded-xl text-xs font-bold">
                ✓ <span x-text="batchSuccessMessage"></span>
            </div>

            {{-- Filter & Search Row --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                <div class="flex items-center gap-1.5">
                    <button type="button" @click="batchFilter = 'all'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all" :class="batchFilter === 'all' ? 'bg-slate-900 text-white dark:bg-blue-600' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'">
                        All (<span x-text="batchMatches.length"></span>)
                    </button>
                    <button type="button" @click="batchFilter = 'unmapped'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all" :class="batchFilter === 'unmapped' ? 'bg-amber-500 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'">
                        Unmapped Only (<span x-text="batchMatches.filter(r => !r.is_already_mapped).length"></span>)
                    </button>
                    <button type="button" @click="batchFilter = 'mapped'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all" :class="batchFilter === 'mapped' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'">
                        Mapped (<span x-text="batchMatches.filter(r => r.is_already_mapped).length"></span>)
                    </button>
                </div>

                <div class="relative w-full sm:w-72">
                    <input type="text" x-model="batchSearch" placeholder="Filter website or domain..." class="w-full text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl pl-9 pr-4 py-2 text-slate-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                </div>
            </div>
        </div>

        {{-- Batch Mapping Table --}}
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-3 px-4 w-12 text-center">
                                <input type="checkbox" @click="toggleSelectAllBatch()" :checked="filteredBatchRows.length > 0 && filteredBatchRows.every(r => selectedBatchWebsites.includes(r.website_id))" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            </th>
                            <th class="py-3 px-4">DGT Website Name</th>
                            <th class="py-3 px-4">Clean Website Domain</th>
                            <th class="py-3 px-4">Google Search Console Property (Clean Domain)</th>
                            <th class="py-3 px-4">Team Handler</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <template x-for="row in filteredBatchRows" :key="row.website_id">
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-750 transition-colors" :class="selectedBatchWebsites.includes(row.website_id) ? 'bg-blue-50/40 dark:bg-blue-950/20' : ''">
                                {{-- Checkbox --}}
                                <td class="py-3 px-4 text-center">
                                    <input type="checkbox" :value="row.website_id" x-model="selectedBatchWebsites" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                </td>

                                {{-- Website Name --}}
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-extrabold text-sm text-slate-900 dark:text-slate-100" x-text="row.website_name"></span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300 border border-purple-200/80" x-text="row.category"></span>
                                    </div>
                                </td>

                                {{-- Clean Website Domain --}}
                                <td class="py-3 px-4">
                                    <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300" x-text="cleanDomain(row.website_domain)"></span>
                                </td>

                                {{-- Property Selector with Clean Domains and Auto-Matched badges --}}
                                <td class="py-3 px-4">
                                    <div class="space-y-1">
                                        <select x-model="batchRowProperties[row.website_id]" @change="if(!selectedBatchWebsites.includes(row.website_id)) selectedBatchWebsites.push(row.website_id)" class="w-full text-xs font-semibold bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-1.5 text-slate-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">-- Select Property in Google Account --</option>
                                            @foreach($availableProperties as $p)
                                                <option value="{{ $p['site_url'] }}">{{ $p['clean_domain'] }} ({{ ($p['type'] ?? '') === 'Domain' ? 'Domain Property' : 'URL Prefix' }})</option>
                                            @endforeach
                                        </select>

                                        {{-- Match confidence badge --}}
                                        <div class="flex items-center gap-1.5">
                                            <template x-if="row.confidence === 'exact'">
                                                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-emerald-600 dark:text-emerald-400">
                                                    <span>✓ Auto-Matched (Exact Domain)</span>
                                                </span>
                                            </template>
                                            <template x-if="row.confidence === 'partial'">
                                                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-amber-600 dark:text-amber-400">
                                                    <span>⚡ Auto-Matched (Partial Match)</span>
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </td>

                                {{-- Handler --}}
                                <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-400" x-text="row.handler_name"></td>

                                {{-- Status --}}
                                <td class="py-3 px-4 text-center">
                                    <template x-if="row.is_already_mapped">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300/60">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>Mapped</span>
                                        </span>
                                    </template>
                                    <template x-if="!row.is_already_mapped">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                            <span>Unmapped</span>
                                        </span>
                                    </template>
                                </td>

                                {{-- Row Action --}}
                                <td class="py-3 px-4 text-right">
                                    <button type="button" @click="selectedBatchWebsites = [row.website_id]; saveBatchMappings()" class="px-2.5 py-1 text-xs font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/50 hover:bg-blue-100 rounded-lg transition-colors">
                                        Save
                                    </button>
                                </td>
                            </tr>
                        </template>

                        <template x-if="filteredBatchRows.length === 0">
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    <p class="font-medium text-xs">No websites found. Click "⚡ 1-Click Auto-Match All" to scan properties.</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

    </div> {{-- ── End VIEW 4: Property Manager & Batch Mapping ── --}}

    {{-- ── MODAL: Property Mapping & Management ──────────────────────────── --}}
    <div x-show="showManageModal" 
         @keydown.escape.window="showManageModal = false"
         @click.self="showManageModal = false" 
         class="fixed inset-0 z-[99999] overflow-y-auto bg-slate-950/75 backdrop-blur-sm flex items-start justify-center p-2 sm:p-4 md:py-6" 
         style="display: none;"
         x-cloak>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl sm:rounded-3xl shadow-2xl max-w-5xl w-full my-auto max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            {{-- Modal Header (Fixed / Sticky) --}}
            <div class="flex items-center justify-between p-4 sm:p-5 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="p-2 sm:p-2.5 bg-blue-50 dark:bg-blue-900/40 rounded-xl text-blue-600 shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                    </div>
                    <div class="truncate">
                        <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100 truncate">
                            Google Search Console Property Management
                        </h3>
                        <p class="text-[11px] sm:text-xs text-slate-500 truncate hidden sm:block">
                            Map existing DGT website records to verified Google Search Console properties
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                    <button type="button" @click="showManageModal = false; setView('properties')" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/50 rounded-xl transition-colors border border-blue-200/80 dark:border-blue-900/60" title="Switch to full page property management table">
                        <span class="hidden sm:inline">Full Page View</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                    </button>
                    <button type="button" @click="showManageModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>

            {{-- Scrollable Modal Body --}}
            <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 sm:space-y-5">
                {{-- OAuth Connection State --}}
                <div class="p-3.5 sm:p-4 bg-slate-50 dark:bg-slate-850 border border-slate-200 dark:border-slate-800 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-2.5 h-2.5 rounded-full shrink-0 {{ $account ? 'bg-emerald-500' : 'bg-slate-400' }}"></div>
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">
                                {{ $account ? 'Connected to Google: ' . $account->email : 'Google Account Not Connected' }}
                            </div>
                            @if($account && $account->expires_at)
                                <div class="text-[11px] text-slate-500 truncate">Token valid until {{ $account->expires_at->format('M d, Y H:i') }} (auto-refreshed via refresh token)</div>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('google.search-console.connect') }}" class="px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition-colors">
                            {{ $account ? 'Reconnect / Switch' : 'Connect Google' }}
                        </a>
                        @if($account)
                            @if(!empty($canDisconnect))
                                <form method="POST" action="{{ route('google.search-console.disconnect') }}" onsubmit="return confirm('Disconnect this Google Search Console account?')">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl transition-colors">
                                        Disconnect
                                    </button>
                                </form>
                            @else
                                <div class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-800 rounded-xl cursor-not-allowed select-none border border-slate-200 dark:border-slate-700" title="Security: Only Mr. Dara (soporadara@kiuq.com) is authorized to disconnect this account.">
                                    <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span>Dara Only</span>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Multi-Property Mapping Center --}}
                <div class="p-4 sm:p-5 border border-slate-200 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-850 space-y-3.5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="text-xs font-bold uppercase text-slate-700 dark:text-slate-300 tracking-wider">
                                    Discovered Google Properties (<span x-text="fetchedProperties.length"></span>)
                                </h4>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                    Multi-Select Batch Matching
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                Select DGT Website Records for each property, or click 1-Click Auto-Match to pair them all.
                            </p>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                            <button type="button" @click="fetchGscProperties()" class="px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 bg-slate-100 dark:bg-slate-800 rounded-xl transition-colors">
                                ↻ Refresh
                            </button>
                            <button type="button" @click="autoMatchModalAll()" class="px-3 py-1.5 text-xs font-bold text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-950/50 hover:bg-purple-100 rounded-xl border border-purple-200 dark:border-purple-800 transition-colors flex items-center gap-1.5 shadow-xs">
                                <span>✨</span>
                                <span>1-Click Auto-Match</span>
                            </button>
                        </div>
                    </div>

                    {{-- Search & Quick Filter Tabs --}}
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button type="button" @click="modalFilter = 'all'" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all" :class="modalFilter === 'all' ? 'bg-slate-900 text-white dark:bg-blue-600' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'">
                                All (<span x-text="fetchedProperties.length"></span>)
                            </button>
                            <button type="button" @click="modalFilter = 'matched'" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all" :class="modalFilter === 'matched' ? 'bg-purple-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'">
                                Matched (<span x-text="fetchedProperties.filter(p => !!modalRowWebsites[p.site_url]).length"></span>)
                            </button>
                            <button type="button" @click="modalFilter = 'unmapped'" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all" :class="modalFilter === 'unmapped' ? 'bg-amber-500 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'">
                                Unmapped (<span x-text="fetchedProperties.filter(p => !p.is_already_mapped && !modalRowWebsites[p.site_url]).length"></span>)
                            </button>
                        </div>
                        <div class="relative w-full sm:w-64">
                            <input type="text" x-model="modalSearch" placeholder="Filter property domain..." class="w-full text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl pl-8 pr-7 py-1.5 text-slate-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                            <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                            <button type="button" x-show="modalSearch" @click="modalSearch = ''" class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Properties Table with Internal Sticky Header & Scroll --}}
                    <div class="overflow-x-auto max-h-[46vh] rounded-xl border border-slate-200 dark:border-slate-800 shadow-inner">
                        <table class="w-full text-left text-xs min-w-[700px]">
                            <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800 sticky top-0 z-10 shadow-xs">
                                <tr>
                                    <th class="py-2.5 px-3 w-8 text-center bg-slate-50 dark:bg-slate-900">
                                        <input type="checkbox" :checked="isAllModalSelected" @change="toggleModalSelectAll($event.target.checked)" class="rounded text-blue-600 focus:ring-blue-500 cursor-pointer">
                                    </th>
                                    <th class="py-2.5 px-2 w-10 text-center text-slate-400 bg-slate-50 dark:bg-slate-900">#</th>
                                    <th class="py-2.5 px-3 bg-slate-50 dark:bg-slate-900">Google Search Console Property</th>
                                    <th class="py-2.5 px-3 bg-slate-50 dark:bg-slate-900">Select DGT Website Record</th>
                                    <th class="py-2.5 px-3 text-center bg-slate-50 dark:bg-slate-900">Status</th>
                                    <th class="py-2.5 px-3 text-right bg-slate-50 dark:bg-slate-900">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <template x-for="(p, index) in filteredModalProperties" :key="p.site_url">
                                    <tr class="hover:bg-blue-50/40 dark:hover:bg-blue-950/30 transition-colors" :class="modalRowSelections[p.site_url] ? 'bg-blue-50/20 dark:bg-blue-950/20' : ''">
                                        <td class="py-2.5 px-3 text-center">
                                            <input type="checkbox" :value="p.site_url" x-model="modalRowSelections[p.site_url]" class="rounded text-blue-600 focus:ring-blue-500 cursor-pointer">
                                        </td>
                                        <td class="py-2.5 px-2 text-center font-mono text-[11px] font-bold text-slate-400" x-text="index + 1"></td>
                                        <td class="py-2.5 px-3">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-mono text-xs font-bold text-slate-900 dark:text-slate-100" x-text="cleanDomain(p.site_url)"></span>
                                                <span class="text-[9px] px-1.5 py-0.5 rounded font-bold uppercase" :class="p.site_url.startsWith('sc-domain:') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'" x-text="p.site_url.startsWith('sc-domain:') ? 'Domain Property' : 'URL Prefix'"></span>
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-3 min-w-[260px]">
                                            <select x-model="modalRowWebsites[p.site_url]" class="w-full text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-1.5 text-slate-800 dark:text-slate-200 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all font-medium">
                                                <option value="">-- Choose DGT Website Record --</option>
                                                @foreach($websitesList as $w)
                                                    <option value="{{ $w['id'] }}">{{ $w['name'] }} ({{ $w['clean_domain'] ?? $w['domain'] }}) • Class: {{ $w['class'] ?? 'General' }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                            <template x-if="p.is_already_mapped">
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                                                    ✓ Mapped
                                                </span>
                                            </template>
                                            <template x-if="!p.is_already_mapped && modalRowWebsites[p.site_url]">
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300">
                                                    ✨ Matched
                                                </span>
                                            </template>
                                            <template x-if="!p.is_already_mapped && !modalRowWebsites[p.site_url]">
                                                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                                    Unmapped
                                                </span>
                                            </template>
                                        </td>
                                        <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                            <button type="button" @click="saveSingleModalRow(p.site_url)" :disabled="!modalRowWebsites[p.site_url] || modalSaving" class="px-2.5 py-1 text-xs font-bold text-blue-600 dark:text-blue-400 hover:text-white hover:bg-blue-600 rounded-lg transition-colors disabled:opacity-30">
                                                Save
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="filteredModalProperties.length === 0">
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-slate-400 text-xs">
                                            No properties found matching your filter.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Existing Mappings Collapsible / Section --}}
                <div x-data="{ openExisting: false }" class="border border-slate-200 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-850 p-4">
                    <div class="flex items-center justify-between cursor-pointer" @click="openExisting = !openExisting">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase text-slate-700 dark:text-slate-300 tracking-wider">Existing Mappings ({{ $mappedSites->count() }})</span>
                            <span class="text-[11px] text-slate-400 font-medium">Click to toggle list</span>
                        </div>
                        <button type="button" class="text-slate-400 hover:text-slate-600 p-1">
                            <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': openExisting }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                    </div>
                    <div x-show="openExisting" class="mt-3 overflow-x-auto max-h-48 rounded-xl border border-slate-200 dark:border-slate-800">
                        <table class="w-full text-left text-xs min-w-[500px]">
                            <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 font-semibold sticky top-0">
                                <tr>
                                    <th class="py-2 px-3">DGT Website</th>
                                    <th class="py-2 px-3">GSC Property</th>
                                    <th class="py-2 px-3">Status</th>
                                    <th class="py-2 px-3">Last Data Date</th>
                                    <th class="py-2 px-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse($mappedSites as $site)
                                    <tr>
                                        <td class="py-2 px-3 font-bold text-slate-800 dark:text-slate-200">{{ $site->website?->name ?? 'Unmapped #' . $site->website_id }}</td>
                                        <td class="py-2 px-3 font-mono text-blue-600 dark:text-blue-400">{{ preg_replace('#^(sc-domain:|https?://(www\.)?)#i', '', rtrim($site->site_url, '/')) }}</td>
                                        <td class="py-2 px-3">
                                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $site->sync_status === 'success' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-slate-100 text-slate-600' }}">{{ ucfirst($site->sync_status) }}</span>
                                        </td>
                                        <td class="py-2 px-3 text-slate-500">{{ $site->last_data_date ? $site->last_data_date->format('M d, Y') : 'Pending' }}</td>
                                        <td class="py-2 px-3 text-right">
                                            <button type="button" @click="syncNow({{ $site->website_id }})" class="text-blue-600 hover:underline font-semibold text-[11px] mr-2">Sync</button>
                                            <button type="button" @click="deleteMapping({{ $site->id }})" class="text-rose-600 hover:underline font-semibold text-[11px]">Remove</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="py-4 text-center text-slate-400">No properties mapped yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Sticky Modal Footer (Fixed at Bottom of Dialog) --}}
            <div class="p-3.5 sm:p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50/90 dark:bg-slate-900/90 backdrop-blur-xs flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
                <div class="text-xs text-slate-500 text-center sm:text-left">
                    <span class="font-bold text-slate-800 dark:text-slate-200" x-text="selectedModalPropertiesCount"></span> property(s) selected for batch saving
                </div>
                <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end">
                    <button type="button" @click="showManageModal = false" class="flex-1 sm:flex-initial px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-750 border border-slate-200 dark:border-slate-700 rounded-xl transition-colors">
                        Close
                    </button>
                    <button type="button" @click="saveModalBatchMappings()" :disabled="modalSaving || selectedModalPropertiesCount === 0" class="flex-1 sm:flex-initial px-5 py-2 text-xs font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 rounded-xl shadow-md shadow-blue-500/20 transition-all disabled:opacity-50 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" :class="{ 'animate-spin': modalSaving }" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span x-text="modalSaving ? 'Saving & Auto-Syncing...' : 'Save All Selected & Auto-Sync'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>



    {{-- ── MODAL: Advanced Multi-Report Export Modal ──────────────── --}}
    <div x-show="showExportModal" @click.self="showExportModal = false" class="fixed inset-0 z-[99999] overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div class="bg-white dark:bg-slate-850 border border-slate-200 dark:border-slate-700 rounded-3xl shadow-2xl max-w-2xl w-full p-6 space-y-5">
            {{-- Header --}}
            <div class="flex items-center justify-between pb-3.5 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center shadow-md shadow-blue-500/20">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                            <span>Export Search Console Report</span>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full"
                                  :class="{
                                      'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300': exportModalView === 'overview',
                                      'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300': exportModalView === 'rankings',
                                      'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300': exportModalView === 'compare',
                                      'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300': exportModalView === 'sitemaps'
                                  }"
                                  x-text="{
                                      'overview': 'Overview & Analytics',
                                      'rankings': 'Website Rankings',
                                      'compare': 'Compare Websites',
                                      'sitemaps': 'Indexed & Sitemaps'
                                  }[exportModalView]">
                            </span>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Choose report type, customize date period, filter scope, and download in PDF, Excel, or CSV</p>
                    </div>
                </div>
                <button type="button" @click="showExportModal = false" class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-750 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- 1. Select Report Type (4 Sub-Pages) --}}
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">1. Select Sub-Page Report Type</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <button type="button" @click="setExportModalView('overview')" class="p-2.5 rounded-2xl border-2 text-left transition-all flex flex-col justify-between"
                            :class="exportModalView === 'overview' ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-950/30 shadow-xs' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'">
                        <div class="flex items-center justify-between mb-1">
                            <span class="p-1 rounded-lg bg-blue-100 dark:bg-blue-900/40 text-blue-600">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.143 2.143L15 7.5" /></svg>
                            </span>
                            <template x-if="exportModalView === 'overview'">
                                <svg class="w-3.5 h-3.5 text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                            </template>
                        </div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Overview</div>
                        <div class="text-[10px] text-slate-500">Executive &amp; Queries</div>
                    </button>

                    <button type="button" @click="setExportModalView('rankings')" class="p-2.5 rounded-2xl border-2 text-left transition-all flex flex-col justify-between"
                            :class="exportModalView === 'rankings' ? 'border-amber-500 bg-amber-50/50 dark:bg-amber-950/30 shadow-xs' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'">
                        <div class="flex items-center justify-between mb-1">
                            <span class="p-1 rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-600">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871a2.25 2.25 0 0 0-2.25 2.25m-3.75 0v3.375" /></svg>
                            </span>
                            <template x-if="exportModalView === 'rankings'">
                                <svg class="w-3.5 h-3.5 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                            </template>
                        </div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Rankings</div>
                        <div class="text-[10px] text-slate-500">Leaderboard &amp; Classes</div>
                    </button>

                    <button type="button" @click="setExportModalView('compare')" class="p-2.5 rounded-2xl border-2 text-left transition-all flex flex-col justify-between"
                            :class="exportModalView === 'compare' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 shadow-xs' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'">
                        <div class="flex items-center justify-between mb-1">
                            <span class="p-1 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                            </span>
                            <template x-if="exportModalView === 'compare'">
                                <svg class="w-3.5 h-3.5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                            </template>
                        </div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Compare</div>
                        <div class="text-[10px] text-slate-500">Site A vs Site B</div>
                    </button>

                    <button type="button" @click="setExportModalView('sitemaps')" class="p-2.5 rounded-2xl border-2 text-left transition-all flex flex-col justify-between"
                            :class="exportModalView === 'sitemaps' ? 'border-purple-500 bg-purple-50/50 dark:bg-purple-950/30 shadow-xs' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'">
                        <div class="flex items-center justify-between mb-1">
                            <span class="p-1 rounded-lg bg-purple-100 dark:bg-purple-900/40 text-purple-600">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                            </span>
                            <template x-if="exportModalView === 'sitemaps'">
                                <svg class="w-3.5 h-3.5 text-purple-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                            </template>
                        </div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Sitemaps</div>
                        <div class="text-[10px] text-slate-500">Coverage &amp; Indexing</div>
                    </button>
                </div>
            </div>

            {{-- 2. Format Selection (PDF / Excel / CSV) --}}
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">2. Choose File Format</label>
                <div class="grid grid-cols-3 gap-3">
                    <button type="button" @click="exportModalFormat = 'pdf'" class="p-3 rounded-2xl border-2 text-left transition-all flex flex-col justify-between"
                            :class="exportModalFormat === 'pdf' ? 'border-rose-500 bg-rose-50/50 dark:bg-rose-950/20 shadow-xs' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="w-7 h-7 rounded-xl bg-rose-100 dark:bg-rose-900/40 text-rose-600 flex items-center justify-center font-black text-xs">PDF</span>
                            <template x-if="exportModalFormat === 'pdf'">
                                <svg class="w-4 h-4 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                            </template>
                        </div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">PDF Document (.pdf)</div>
                        <div class="text-[10px] text-slate-500 truncate"
                             x-text="{
                                 'overview': 'Executive summary with KPI cards & growth tables',
                                 'rankings': 'Printable leaderboard with podium & class breakdown',
                                 'compare': 'Head-to-head scorecard & top queries benchmark',
                                 'sitemaps': 'Coverage scorecard, sitemaps table & diagnostic reasons'
                             }[exportModalView]">
                        </div>
                    </button>

                    <button type="button" @click="exportModalFormat = 'excel'" class="p-3 rounded-2xl border-2 text-left transition-all flex flex-col justify-between"
                            :class="exportModalFormat === 'excel' ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20 shadow-xs' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="w-7 h-7 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 flex items-center justify-center font-black text-xs">XLS</span>
                            <template x-if="exportModalFormat === 'excel'">
                                <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                            </template>
                        </div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Excel (.xlsx)</div>
                        <div class="text-[10px] text-slate-500 truncate"
                             x-text="{
                                 'overview': '7 sheets: Overview, breakdown, queries, pages, daily',
                                 'rankings': '3 sheets: Leaderboard rankings, performers, classes',
                                 'compare': '4 sheets: Comparison matrix, Site A/B queries & daily',
                                 'sitemaps': '4 sheets: Indexation overview, sitemaps, reasons, pages'
                             }[exportModalView]">
                        </div>
                    </button>

                    <button type="button" @click="exportModalFormat = 'csv'" class="p-3 rounded-2xl border-2 text-left transition-all flex flex-col justify-between"
                            :class="exportModalFormat === 'csv' ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-950/20 shadow-xs' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="w-7 h-7 rounded-xl bg-blue-100 dark:bg-blue-900/40 text-blue-600 flex items-center justify-center font-black text-xs">CSV</span>
                            <template x-if="exportModalFormat === 'csv'">
                                <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                            </template>
                        </div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Table CSV (.csv)</div>
                        <div class="text-[10px] text-slate-500 truncate">Fast raw data stream compatible with Excel &amp; Sheets</div>
                    </button>
                </div>
            </div>

            {{-- 3. Date Range & Period Growth Comparison --}}
            <div class="p-4 bg-slate-50 dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-750 space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">3. Date Filter &amp; Growth Period</label>
                    <span class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" /></svg>
                        <span>Compares with previous period</span>
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] text-slate-500 mb-1">Preset Period</label>
                        <select x-model="exportModalFilters.range" class="w-full text-xs font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 outline-none">
                            <option value="this_week">This Week (vs Last Week)</option>
                            <option value="last_week">Last Week (vs Prior Week)</option>
                            <option value="7d">Last 7 Days (vs Prior 7 Days)</option>
                            <option value="28d">Last 28 Days (Default)</option>
                            <option value="this_month">This Month (vs Prior Month)</option>
                            <option value="prev_month">Previous Month (vs 2 Months Ago)</option>
                            <option value="quarter">Last 90 Days / Quarter</option>
                            <option value="custom">Custom Date Range...</option>
                        </select>
                    </div>

                    {{-- Dynamic info indicator --}}
                    <div class="flex flex-col justify-center">
                        <label class="block text-[11px] text-slate-500 mb-1">Comparison Methodology</label>
                        <div class="text-[11px] text-slate-600 dark:text-slate-400 bg-white dark:bg-slate-800 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 truncate">
                            <template x-if="exportModalFilters.range === 'this_week'">
                                <span>Mon-to-Date vs Mon-to-Date of last week</span>
                            </template>
                            <template x-if="exportModalFilters.range === 'last_week'">
                                <span>Full Mon-Sun vs Mon-Sun prior week</span>
                            </template>
                            <template x-if="exportModalFilters.range === 'this_month'">
                                <span>Month-to-Date vs same span last month</span>
                            </template>
                            <template x-if="exportModalFilters.range === 'prev_month'">
                                <span>Entire last month vs month prior</span>
                            </template>
                            <template x-if="exportModalFilters.range === '7d' || exportModalFilters.range === '28d' || exportModalFilters.range === 'quarter'">
                                <span>Equal preceding duration</span>
                            </template>
                            <template x-if="exportModalFilters.range === 'custom'">
                                <span>Preceding duration of identical day count</span>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Custom Date Inputs (when range === 'custom') --}}
                <div x-show="exportModalFilters.range === 'custom'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-200/60 dark:border-slate-800">
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-300 mb-1">Custom Start Date</label>
                        <input type="date" x-model="exportModalFilters.start_date" class="w-full text-xs bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-300 mb-1">Custom End Date</label>
                        <input type="date" x-model="exportModalFilters.end_date" class="w-full text-xs bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 outline-none">
                    </div>
                </div>
            </div>

            {{-- 4. Scope & Targets Selection (Dynamically adjusted per report type) --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">4. Filter Scope &amp; Target Websites</label>
                </div>

                {{-- Scope A: For Overview & Rankings (Class, Member, Specific Website) --}}
                <div x-show="exportModalView === 'overview' || exportModalView === 'rankings'" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    {{-- Class Filter --}}
                    <div>
                        <label class="block text-[11px] text-slate-500 mb-1">Website Class</label>
                        <select x-model="exportModalFilters.class" @change="onExportModalScopeChange()" class="w-full text-xs font-medium bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="all">All Classes</option>
                            <template x-for="cls in availableClasses" :key="cls">
                                <option :value="cls" x-text="cls"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Member Filter --}}
                    <div>
                        <label class="block text-[11px] text-slate-500 mb-1">Responsible Member</label>
                        <select x-model="exportModalFilters.members" @change="onExportModalScopeChange()" class="w-full text-xs font-medium bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Team Members</option>
                            @foreach($teamMembers as $m)
                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Specific Website Scope --}}
                    <div class="relative" @click.outside="openExportWebsiteDropdown = false">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[11px] text-slate-500">Specific Website (Optional)</label>
                            <span class="text-[10px] text-blue-600 dark:text-blue-400 font-medium" x-text="filteredExportModalWebsites.length + ' available'"></span>
                        </div>
                        
                        <button type="button"
                                @click="openExportWebsiteDropdown = !openExportWebsiteDropdown"
                                class="w-full text-xs font-medium bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 flex items-center justify-between gap-1.5 focus:ring-2 focus:ring-blue-500 hover:border-slate-300 dark:hover:border-slate-600 transition-all text-left">
                            <div class="flex items-center gap-1.5 min-w-0 truncate">
                                <template x-if="!exportModalFilters.websites">
                                    <div class="flex items-center gap-1.5 min-w-0 truncate text-slate-600 dark:text-slate-300">
                                        <svg class="w-3.5 h-3.5 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                        </svg>
                                        <span class="truncate">All in Scope / Class</span>
                                    </div>
                                </template>
                                <template x-if="exportModalFilters.websites">
                                    <div class="flex items-center gap-1.5 min-w-0 truncate" x-data="{ ws: getSelectedExportWebsite() }">
                                        <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="ws?.is_mapped ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                        <span class="truncate font-semibold text-slate-800 dark:text-slate-100" x-text="ws?.name || 'Website'"></span>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold shrink-0 bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300" x-text="ws?.class || 'General'"></span>
                                    </div>
                                </template>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <template x-if="exportModalFilters.websites">
                                    <span @click.stop="exportModalFilters.websites = ''" class="p-0.5 text-slate-400 hover:text-rose-500 transition-colors cursor-pointer" title="Reset to All in Scope">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                    </span>
                                </template>
                                <svg class="w-3.5 h-3.5 text-slate-400 transition-transform" :class="{ 'rotate-180': openExportWebsiteDropdown }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </button>

                        {{-- Rich Dropdown Menu Panel --}}
                        <div x-show="openExportWebsiteDropdown"
                             x-cloak
                             class="absolute left-0 right-0 sm:right-0 sm:left-auto top-full mt-1.5 w-full sm:w-96 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-2xl z-50 p-2.5 space-y-2">
                            <div class="relative">
                                <svg class="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                                <input type="text"
                                       x-model="exportModalWebsiteSearch"
                                       placeholder="Search site, domain, class, handler..."
                                       class="w-full text-xs pl-8 pr-7 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 outline-none focus:border-blue-500">
                                <button type="button"
                                        x-show="exportModalWebsiteSearch"
                                        @click="exportModalWebsiteSearch = ''"
                                        class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                </button>
                            </div>

                            <div @click="selectExportWebsite('')"
                                 class="flex items-center justify-between p-2 rounded-xl cursor-pointer transition-colors"
                                 :class="!exportModalFilters.websites ? 'bg-blue-50 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-800/60' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-6 h-6 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                                            <span>All in Scope / Class</span>
                                            <span class="text-[10px] text-blue-600 dark:text-blue-400 font-bold" x-text="'(' + filteredExportModalWebsites.length + ')'"></span>
                                        </div>
                                        <div class="text-[10.5px] text-slate-400 dark:text-slate-500 truncate" x-text="'Export all ' + filteredExportModalWebsites.length + ' matching websites'"></div>
                                    </div>
                                </div>
                                <template x-if="!exportModalFilters.websites">
                                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                </template>
                            </div>

                            <div class="border-t border-slate-100 dark:border-slate-800"></div>

                            <div class="max-h-60 overflow-y-auto space-y-1 pr-1 divide-y divide-slate-100/60 dark:divide-slate-800/40">
                                <template x-for="w in filteredExportModalWebsites" :key="w.id">
                                    <div @click="selectExportWebsite(w.id)"
                                         class="pt-1.5 first:pt-0 flex items-center justify-between gap-2 p-2 rounded-xl cursor-pointer transition-colors"
                                         :class="String(exportModalFilters.websites) === String(w.id) ? 'bg-blue-50 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-800/60' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="w-2 h-2 rounded-full shrink-0" :class="w.is_mapped ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate" x-text="w.name"></span>
                                                    <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold tracking-tight bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700" x-text="w.class || 'General'"></span>
                                                </div>
                                                <div class="flex items-center gap-2 mt-0.5 text-[10.5px] text-slate-400 dark:text-slate-500 truncate">
                                                    <span class="font-mono truncate" x-text="w.domain"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <template x-if="String(exportModalFilters.websites) === String(w.id)">
                                            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Scope B: For Compare Websites (Site A and Site B) --}}
                <div x-show="exportModalView === 'compare'" class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-200/60 dark:border-indigo-900/50 rounded-2xl">
                    {{-- Site A --}}
                    <div>
                        <label class="block text-[11px] font-bold text-indigo-900 dark:text-indigo-300 mb-1">Primary Website (Site A)</label>
                        <select x-model="exportModalSiteA" class="w-full text-xs font-semibold bg-white dark:bg-slate-800 border border-indigo-200 dark:border-indigo-800 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 outline-none">
                            <template x-for="w in allWebsites" :key="'m-a-' + w.id">
                                <option :value="w.id" x-text="w.name + ' (' + (w.domain || w.clean_domain) + ') - ' + (w.class || 'General')"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Site B --}}
                    <div>
                        <label class="block text-[11px] font-bold text-emerald-900 dark:text-emerald-300 mb-1">Benchmark Website (Site B)</label>
                        <select x-model="exportModalSiteB" class="w-full text-xs font-semibold bg-white dark:bg-slate-800 border border-emerald-200 dark:border-emerald-800 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 outline-none">
                            <template x-for="w in allWebsites" :key="'m-b-' + w.id">
                                <option :value="w.id" x-text="w.name + ' (' + (w.domain || w.clean_domain) + ') - ' + (w.class || 'General')"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Scope C: For Sitemaps (Single Target Website) --}}
                <div x-show="exportModalView === 'sitemaps'" class="p-3 bg-purple-50/40 dark:bg-purple-950/20 border border-purple-200/60 dark:border-purple-900/50 rounded-2xl">
                    <label class="block text-[11px] font-bold text-purple-900 dark:text-purple-300 mb-1">Target Website for Sitemaps &amp; Indexation</label>
                    <select x-model="exportModalSitemapSite" class="w-full text-xs font-semibold bg-white dark:bg-slate-800 border border-purple-200 dark:border-purple-800 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 outline-none">
                        <template x-for="w in allWebsites" :key="'m-sm-' + w.id">
                            <option :value="w.id" x-text="w.name + ' (' + (w.domain || w.clean_domain) + ') &bull; ' + (w.class || 'General') + (w.is_mapped ? ' [GSC Connected]' : '')"></option>
                        </template>
                    </select>
                </div>
            </div>

            {{-- 5. If CSV selected: Content selection or format info --}}
            <div x-show="exportModalFormat === 'csv'" x-cloak class="p-3 bg-blue-50/40 dark:bg-blue-950/20 border border-blue-200/50 dark:border-blue-900/50 rounded-2xl">
                <template x-if="exportModalView === 'overview'">
                    <div>
                        <label class="block text-[11px] font-bold text-blue-900 dark:text-blue-300 uppercase tracking-wider mb-1.5">Overview CSV Table Content</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                            <label class="flex items-center gap-2 p-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer">
                                <input type="radio" value="growth" x-model="exportModalFilters.report" class="text-blue-600">
                                <span class="font-medium text-slate-700 dark:text-slate-200 text-xs">Websites Growth</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer">
                                <input type="radio" value="overview" x-model="exportModalFilters.report" class="text-blue-600">
                                <span class="font-medium text-slate-700 dark:text-slate-200 text-xs">Executive KPIs</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer">
                                <input type="radio" value="keywords" x-model="exportModalFilters.report" class="text-blue-600">
                                <span class="font-medium text-slate-700 dark:text-slate-200 text-xs">Search Queries</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer">
                                <input type="radio" value="pages" x-model="exportModalFilters.report" class="text-blue-600">
                                <span class="font-medium text-slate-700 dark:text-slate-200 text-xs">Top Pages</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer">
                                <input type="radio" value="daily" x-model="exportModalFilters.report" class="text-blue-600">
                                <span class="font-medium text-slate-700 dark:text-slate-200 text-xs">Daily Series</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer">
                                <input type="radio" value="countries" x-model="exportModalFilters.report" class="text-blue-600">
                                <span class="font-medium text-slate-700 dark:text-slate-200 text-xs">Countries &amp; Devices</span>
                            </label>
                        </div>
                    </div>
                </template>
                <template x-if="exportModalView === 'rankings'">
                    <div class="text-xs text-amber-800 dark:text-amber-300 font-medium flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                        <span>Outputs full Leaderboard ranking CSV with Clicks, Impressions, CTR, Position, Growth %, and share distribution.</span>
                    </div>
                </template>
                <template x-if="exportModalView === 'compare'">
                    <div class="text-xs text-indigo-800 dark:text-indigo-300 font-medium flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                        <span>Outputs Head-to-Head summary matrix, Site A top keywords, Site B top keywords, and daily comparative series.</span>
                    </div>
                </template>
                <template x-if="exportModalView === 'sitemaps'">
                    <div class="text-xs text-purple-800 dark:text-purple-300 font-medium flex items-center gap-2">
                        <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                        <span>Outputs indexation scorecard, submitted XML sitemaps, exclusion reasons, and discovered landing pages.</span>
                    </div>
                </template>
            </div>

            {{-- Footer Action Buttons --}}
            <div class="flex items-center justify-between pt-3 border-t border-slate-200 dark:border-slate-700">
                <button type="button" @click="showExportModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white transition-colors">
                    Cancel
                </button>

                <button type="button" @click="triggerModalDownload()" class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white rounded-xl shadow-md transition-all"
                        :class="exportModalFormat === 'pdf' ? 'bg-rose-600 hover:bg-rose-700 shadow-rose-500/20' : (exportModalFormat === 'excel' ? 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-500/20' : 'bg-blue-600 hover:bg-blue-700 shadow-blue-500/20')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span x-text="'Download ' + ({ 'overview':'Overview', 'rankings':'Rankings', 'compare':'Comparison', 'sitemaps':'Sitemaps' }[exportModalView] || 'Report') + ' Report'"></span>
                    <span class="uppercase text-[10px] px-1.5 py-0.5 rounded-md bg-white/20 font-black" x-text="'.' + exportModalFormat"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Beginner Setup Guide ────────────────────────────────────── --}}
    <div x-show="showSetupGuide" @click.self="showSetupGuide = false" class="fixed inset-0 z-[99999] overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-3xl shadow-2xl max-w-3xl w-full p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-blue-50 dark:bg-blue-900/40 rounded-xl text-blue-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">Google Cloud Console & OAuth Setup Guide</h3>
                        <p class="text-xs text-slate-500">Step-by-step instructions to create your credentials and connect Search Console</p>
                    </div>
                </div>
                <button type="button" @click="showSetupGuide = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="my-5 max-h-[60vh] overflow-y-auto space-y-4 text-xs text-slate-600 dark:text-slate-300 pr-2">
                <div class="p-4 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700">
                    <h4 class="font-bold text-slate-900 dark:text-slate-100 text-sm mb-1.5">Step 1: Open Google Cloud Console & Create a Project</h4>
                    <ol class="list-decimal list-inside space-y-1 text-slate-600 dark:text-slate-400">
                        <li>Go to <a href="https://console.cloud.google.com/" target="_blank" class="text-blue-600 font-bold underline">Google Cloud Console</a>.</li>
                        <li>Select your organization or click <strong>Select a project</strong> &rarr; <strong>New Project</strong>.</li>
                        <li>Name it <strong>DGT System - Search Console</strong> and click <strong>Create</strong>.</li>
                    </ol>
                </div>

                <div class="p-4 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700">
                    <h4 class="font-bold text-slate-900 dark:text-slate-100 text-sm mb-1.5">Step 2: Enable Google Search Console API</h4>
                    <ol class="list-decimal list-inside space-y-1 text-slate-600 dark:text-slate-400">
                        <li>Navigate to <strong>APIs & Services</strong> &rarr; <strong>Library</strong>.</li>
                        <li>Search for <strong>Google Search Console API</strong>.</li>
                        <li>Click <strong>Enable</strong>.</li>
                    </ol>
                </div>

                <div class="p-4 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700">
                    <h4 class="font-bold text-slate-900 dark:text-slate-100 text-sm mb-1.5">Step 3: Configure OAuth Consent Screen</h4>
                    <ol class="list-decimal list-inside space-y-1 text-slate-600 dark:text-slate-400">
                        <li>Go to <strong>APIs & Services</strong> &rarr; <strong>OAuth consent screen</strong>.</li>
                        <li>Select <strong>Internal</strong> (if Google Workspace organization) or <strong>External</strong> with Test Users.</li>
                        <li>Fill in App Name: <code>DGT System</code>, Support Email, and Developer Contact Email.</li>
                        <li>Under Scopes, add: <code>https://www.googleapis.com/auth/webmasters.readonly</code>.</li>
                        <li>If External, add your team Google email under <strong>Test Users</strong>.</li>
                    </ol>
                </div>

                <div class="p-4 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700">
                    <h4 class="font-bold text-slate-900 dark:text-slate-100 text-sm mb-1.5">Step 4: Create OAuth 2.0 Credentials</h4>
                    <ol class="list-decimal list-inside space-y-1 text-slate-600 dark:text-slate-400">
                        <li>Go to <strong>APIs & Services</strong> &rarr; <strong>Credentials</strong> &rarr; <strong>Create Credentials</strong> &rarr; <strong>OAuth Client ID</strong>.</li>
                        <li>Application Type: <strong>Web application</strong>.</li>
                        <li>Name: <code>DGT Search Console OAuth</code>.</li>
                        <li>In <strong>Authorized redirect URIs</strong>, add this exact URL:</li>
                    </ol>
                    <div class="mt-2 p-2.5 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 rounded-lg font-mono text-[11px] text-blue-700 dark:text-blue-300 select-all">
                        {{ route('google.search-console.callback') }}
                    </div>
                    <div class="mt-1.5 text-[10px] text-slate-400">
                        (For Hostinger Production: <code>https://lightcyan-weasel-711536.hostingersite.com/google/search-console/callback</code>)
                    </div>
                </div>

                <div class="p-4 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700">
                    <h4 class="font-bold text-slate-900 dark:text-slate-100 text-sm mb-1.5">Step 5: Add Credentials to .env</h4>
                    <p class="mb-2">Copy the Client ID and Client Secret generated by Google, and paste them into your <code>.env</code> file:</p>
                    <pre class="p-3 bg-slate-900 text-slate-100 rounded-xl font-mono text-[11px] overflow-x-auto">GOOGLE_SEARCH_CONSOLE_CLIENT_ID=your_client_id_here.apps.googleusercontent.com
GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET=GOCSPX-your_client_secret_here
GOOGLE_SEARCH_CONSOLE_REDIRECT_URI={{ route('google.search-console.callback') }}</pre>
                </div>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-slate-200 dark:border-slate-700">
                <span class="text-xs text-slate-400">Never share your Client Secret with unauthorized parties.</span>
                <button type="button" @click="showSetupGuide = false" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition-colors">
                    Understood
                </button>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Update Official Google Search Console Indexing Snapshot ── --}}
    <div x-show="showCoverageModal" @click.self="showCoverageModal = false" class="fixed inset-0 z-[99999] overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-3xl shadow-2xl max-w-lg w-full p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 bg-emerald-50 dark:bg-emerald-950/50 rounded-xl text-emerald-600 dark:text-emerald-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900 dark:text-slate-100">Update GSC Page Indexing Snapshot</h3>
                        <p class="text-[11px] text-slate-500" x-text="'Target: ' + (selectedSitemapWebsite?.name || 'Website')"></p>
                    </div>
                </div>
                <button type="button" @click="showCoverageModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="space-y-3.5">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-emerald-700 dark:text-emerald-400 mb-1">Indexed Pages (in GSC)</label>
                        <input type="number" min="0" x-model="coverageForm.indexed_pages" class="w-full text-sm font-bold bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-amber-700 dark:text-amber-400 mb-1">Not Indexed / Excluded</label>
                        <input type="number" min="0" x-model="coverageForm.not_indexed_pages" class="w-full text-sm font-bold bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">Snapshot Date (Date shown in GSC)</label>
                    <input type="date" x-model="coverageForm.updated_date" class="w-full text-xs font-semibold bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-900 dark:text-slate-100 outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">Exclusion Reasons Breakdown (Optional - one per line: [count] [reason])</label>
                    <textarea x-model="coverageForm.reasons_text" rows="4" placeholder="38120 Alternative page with proper canonical tag&#10;1240 Crawled - currently not indexed&#10;640 Discovered - currently not indexed" class="w-full text-xs font-mono bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-slate-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
                    <p class="text-[10px] text-slate-400 mt-1">Copy and paste or type the numbers directly as displayed in your Google Search Console Page Indexing report.</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-700">
                <button type="button" @click="showCoverageModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition-colors">
                    Cancel
                </button>
                <button type="button" @click="saveCoverageSnapshot()" :disabled="coverageSaving" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 rounded-xl shadow-xs transition-all disabled:opacity-50">
                    <svg class="w-3.5 h-3.5" :class="coverageSaving ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    <span x-text="coverageSaving ? 'Saving...' : 'Save &amp; Sync Snapshot'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

{{-- ── Frontend Alpine.js Controller & Chart.js Integration ──────────────── --}}
<script>
function gscDashboard() {
    return {
        loading: false,
        syncing: false,
        savingMapping: false,
        showKeywordModal: false,
        showManageModal: false,
        showSetupGuide: false,
        activeTab: 'keywords',
        activeMetric: 'clicks_impressions',
        activeView: '{{ $currentView ?? 'overview' }}',

        // Rankings Leaderboard State
        websiteRankings: @json($websiteRankings ?? []),
        rankingSort: 'impressions',
        rankingCategory: 'all',
        rankingSearch: '',

        get sortedRankings() {
            let list = [...this.websiteRankings];
            if (this.rankingCategory !== 'all') {
                list = list.filter(w => (w.class || 'General') === this.rankingCategory);
            }
            if (this.rankingSearch && this.rankingSearch.trim()) {
                const q = this.rankingSearch.toLowerCase().trim();
                list = list.filter(w => (w.name || '').toLowerCase().includes(q) || (w.clean_domain || '').toLowerCase().includes(q));
            }
            list.sort((a, b) => {
                if (this.rankingSort === 'impressions') return (b.impressions || 0) - (a.impressions || 0);
                if (this.rankingSort === 'clicks') return (b.clicks || 0) - (a.clicks || 0);
                if (this.rankingSort === 'ctr') return (b.ctr || 0) - (a.ctr || 0);
                if (this.rankingSort === 'position') {
                    const posA = a.position > 0 ? a.position : 999;
                    const posB = b.position > 0 ? b.position : 999;
                    return posA - posB;
                }
                return 0;
            });
            return list;
        },

        get topRankedByImpressions() {
            if (!this.websiteRankings || !this.websiteRankings.length) return null;
            return [...this.websiteRankings].sort((a, b) => (b.impressions || 0) - (a.impressions || 0))[0] || null;
        },

        get topRankedByClicks() {
            if (!this.websiteRankings || !this.websiteRankings.length) return null;
            return [...this.websiteRankings].sort((a, b) => (b.clicks || 0) - (a.clicks || 0))[0] || null;
        },

        get topRankedByCtr() {
            if (!this.websiteRankings || !this.websiteRankings.length) return null;
            return [...this.websiteRankings].sort((a, b) => (b.ctr || 0) - (a.ctr || 0))[0] || null;
        },

        get topRankedByPosition() {
            if (!this.websiteRankings || !this.websiteRankings.length) return null;
            return [...this.websiteRankings].filter(w => w.position > 0).sort((a, b) => a.position - b.position)[0] || null;
        },

        // Comparison State
        compareSiteA: {{ count($resolvedWebsiteIds) > 0 ? $resolvedWebsiteIds[0] : 'null' }},
        compareSiteB: {{ count($resolvedWebsiteIds) > 1 ? $resolvedWebsiteIds[1] : (count($resolvedWebsiteIds) > 0 ? $resolvedWebsiteIds[0] : 'null') }},
        comparisonData: {!! json_encode($initialComparison ?? ['websites' => [], 'chart_dates' => [], 'datasets_clicks' => [], 'datasets_impressions' => []]) !!},
        compareChartInstance: null,
        compareMetric: 'impressions',
        loadingCompare: false,

        // Comparison Dropdown & Filtering States
        openCompareDropdownA: false,
        openCompareDropdownB: false,
        compareSearchA: '',
        compareSearchB: '',
        compareClassFilterA: 'all',
        compareClassFilterB: 'all',

        get selectedCompareSiteA() {
            if (!this.compareSiteA) return null;
            return (this.allWebsites || []).find(w => String(w.id) === String(this.compareSiteA)) || null;
        },

        get selectedCompareSiteB() {
            if (!this.compareSiteB) return null;
            return (this.allWebsites || []).find(w => String(w.id) === String(this.compareSiteB)) || null;
        },

        get filteredCompareWebsitesA() {
            let list = this.allWebsites || [];
            if (this.compareClassFilterA !== 'all') {
                list = list.filter(w => w.class === this.compareClassFilterA);
            }
            if (this.compareSearchA && this.compareSearchA.trim() !== '') {
                const s = this.compareSearchA.toLowerCase().trim();
                list = list.filter(w =>
                    (w.name && w.name.toLowerCase().includes(s)) ||
                    (w.domain && w.domain.toLowerCase().includes(s)) ||
                    (w.class && w.class.toLowerCase().includes(s)) ||
                    (w.handler && w.handler.name && w.handler.name.toLowerCase().includes(s)) ||
                    (w.handle_assigned && w.handle_assigned.toLowerCase().includes(s))
                );
            }
            return list;
        },

        get filteredCompareWebsitesB() {
            let list = this.allWebsites || [];
            if (this.compareClassFilterB !== 'all') {
                list = list.filter(w => w.class === this.compareClassFilterB);
            }
            if (this.compareSearchB && this.compareSearchB.trim() !== '') {
                const s = this.compareSearchB.toLowerCase().trim();
                list = list.filter(w =>
                    (w.name && w.name.toLowerCase().includes(s)) ||
                    (w.domain && w.domain.toLowerCase().includes(s)) ||
                    (w.class && w.class.toLowerCase().includes(s)) ||
                    (w.handler && w.handler.name && w.handler.name.toLowerCase().includes(s)) ||
                    (w.handle_assigned && w.handle_assigned.toLowerCase().includes(s))
                );
            }
            return list;
        },

        selectCompareSiteA(websiteId) {
            this.compareSiteA = websiteId;
            this.openCompareDropdownA = false;
            this.compareSearchA = '';
            this.fetchComparison();
        },

        selectCompareSiteB(websiteId) {
            this.compareSiteB = websiteId;
            this.openCompareDropdownB = false;
            this.compareSearchB = '';
            this.fetchComparison();
        },

        swapCompareSites() {
            const temp = this.compareSiteA;
            this.compareSiteA = this.compareSiteB;
            this.compareSiteB = temp;
            this.fetchComparison();
        },

        // Batch Property Mapping State
        batchMatches: @json($initialBatchMatches ?? []),
        batchRowProperties: (() => {
            const map = {};
            const initial = @json($initialBatchMatches ?? []);
            if (Array.isArray(initial)) {
                initial.forEach(m => {
                    if (m && m.website_id && m.suggested_site_url) {
                        map[m.website_id] = m.suggested_site_url;
                    }
                });
            }
            return map;
        })(),
        selectedBatchWebsites: (() => {
            const selected = [];
            const initial = @json($initialBatchMatches ?? []);
            if (Array.isArray(initial)) {
                initial.forEach(m => {
                    if (m && m.website_id && !m.is_already_mapped && m.suggested_site_url) {
                        selected.push(m.website_id);
                    }
                });
            }
            return selected;
        })(),
        batchFilter: 'all',
        batchSearch: '',
        batchLoading: false,
        batchSaving: false,
        batchSuccessMessage: '',

        // Modal Multi-Property Mapping State
        modalRowSelections: {},
        modalRowWebsites: {},
        modalSaving: false,
        modalSearch: '',
        modalFilter: 'all',

        // Sitemaps & Indexation Hub State
        sitemapWebsiteId: {{ count($resolvedWebsiteIds) > 0 ? $resolvedWebsiteIds[0] : (!empty($websitesList) ? $websitesList[0]['id'] : 'null') }},
        sitemapsLoading: false,
        sitemapsList: [],
        sitemapUrlInput: '',
        sitemapSubmitting: false,
        sitemapTesting: false,
        sitemapTestResult: null,
        sitemapFeedback: null,

        // Indexation Explorer State (Auto-synced with Google Search Console)
        indexationLoading: false,
        indexationSummary: { total_discovered: 0, total_indexed: 0, total_not_indexed: 0, index_rate: 0, sitemaps_count: 0, sitemaps_submitted_urls: 0, sitemaps_indexed_urls: 0, is_official_snapshot: false, gsc_last_updated: null, gsc_reasons_count: 0, gsc_reasons: [] },
        indexationPages: [],
        indexationTotal: 0,
        indexationPage: 1,
        indexationPerPage: 20,
        indexationFilter: 'all',
        indexationSearch: '',

        // Official GSC Indexing Snapshot Modal State
        showCoverageModal: false,
        coverageSaving: false,
        coverageForm: {
            indexed_pages: 0,
            not_indexed_pages: 0,
            updated_date: '',
            reasons_text: '',
        },

        // Automated Background URL Inspection Scan State
        scanningBackground: false,
        scanProgressText: 'Auto-Scan All Pages',
        scanPollTimer: null,

        // Live URL Inspector State
        inspectUrlInput: '',
        inspectingUrl: false,
        inspectionResult: null,
        testingLiveUrl: false,
        liveTestResult: null,

        get selectedSitemapWebsite() {
            if (!this.sitemapWebsiteId) return null;
            return (this.allWebsites || []).find(w => String(w.id) === String(this.sitemapWebsiteId)) || null;
        },

        get isSelectedWebsiteMapped() {
            const ws = this.selectedSitemapWebsite;
            return !!(ws && (ws.is_mapped || ws.site_url));
        },

        openSitemapWebsiteDropdown: false,
        sitemapWebsiteSearch: '',
        sitemapClassFilter: 'all',
        sitemapMappingFilter: 'all',

        get filteredSitemapWebsites() {
            let list = this.allWebsites || [];
            if (this.sitemapMappingFilter === 'linked') {
                list = list.filter(w => w.is_mapped || w.site_url);
            } else if (this.sitemapMappingFilter === 'unlinked') {
                list = list.filter(w => !w.is_mapped && !w.site_url);
            }
            if (this.sitemapClassFilter !== 'all') {
                list = list.filter(w => w.class === this.sitemapClassFilter);
            }
            if (this.sitemapWebsiteSearch && this.sitemapWebsiteSearch.trim() !== '') {
                const s = this.sitemapWebsiteSearch.toLowerCase().trim();
                list = list.filter(w =>
                    (w.name && w.name.toLowerCase().includes(s)) ||
                    (w.domain && w.domain.toLowerCase().includes(s)) ||
                    (w.class && w.class.toLowerCase().includes(s)) ||
                    (w.handler && w.handler.name && w.handler.name.toLowerCase().includes(s)) ||
                    (w.handle_assigned && w.handle_assigned.toLowerCase().includes(s)) ||
                    (w.assignees && w.assignees.some(a => a.name && a.name.toLowerCase().includes(s)))
                );
            }
            return list;
        },

        selectSitemapWebsite(websiteId) {
            this.sitemapWebsiteId = websiteId;
            this.openSitemapWebsiteDropdown = false;
            this.sitemapWebsiteSearch = '';
            this.onSitemapWebsiteChange(websiteId);
        },

        get filteredModalProperties() {
            let list = this.fetchedProperties || [];
            if (this.modalFilter === 'unmapped') {
                list = list.filter(p => !p.is_already_mapped && !this.modalRowWebsites[p.site_url]);
            } else if (this.modalFilter === 'matched') {
                list = list.filter(p => !!this.modalRowWebsites[p.site_url]);
            } else if (this.modalFilter === 'mapped') {
                list = list.filter(p => p.is_already_mapped);
            }
            if (this.modalSearch && this.modalSearch.trim()) {
                const q = this.modalSearch.toLowerCase().trim();
                list = list.filter(p => (p.site_url || '').toLowerCase().includes(q) || this.cleanDomain(p.site_url).toLowerCase().includes(q));
            }
            return list;
        },

        get selectedModalPropertiesCount() {
            return Object.values(this.modalRowSelections || {}).filter(Boolean).length;
        },

        get isAllModalSelected() {
            const list = this.filteredModalProperties;
            if (!list || list.length === 0) return false;
            return list.every(p => !!this.modalRowSelections[p.site_url]);
        },

        toggleModalSelectAll(checked) {
            (this.filteredModalProperties || []).forEach(p => {
                this.modalRowSelections[p.site_url] = checked;
            });
        },

        initModalBatch() {
            if (!this.fetchedProperties || !this.allWebsites) return;
            this.fetchedProperties.forEach(p => {
                if (!p || !p.site_url) return;
                const pClean = this.cleanDomain(p.site_url).toLowerCase();
                
                // If backend provided suggested website
                if (p.suggested_ws_id) {
                    this.modalRowWebsites[p.site_url] = p.suggested_ws_id;
                    this.modalRowSelections[p.site_url] = true;
                    return;
                }

                // Match with allWebsites by clean domain or domain
                const match = this.allWebsites.find(w => {
                    const wClean = (w.clean_domain || w.domain || '').toLowerCase().replace(/^(https?:\/\/)?(www\.)?/, '').replace(/\/$/, '');
                    if (!wClean || !pClean) return false;
                    return wClean === pClean || pClean.includes(wClean) || wClean.includes(pClean);
                });

                if (match) {
                    this.modalRowWebsites[p.site_url] = match.id;
                    this.modalRowSelections[p.site_url] = true;
                } else if (!this.modalRowWebsites[p.site_url]) {
                    this.modalRowSelections[p.site_url] = false;
                }
            });
        },

        autoMatchModalAll() {
            this.initModalBatch();
        },

        async saveModalBatchMappings() {
            const mappingsToSave = [];
            for (const p of (this.fetchedProperties || [])) {
                if (this.modalRowSelections[p.site_url]) {
                    const wsId = this.modalRowWebsites[p.site_url];
                    if (wsId) {
                        mappingsToSave.push({
                            website_id: parseInt(wsId),
                            site_url: p.site_url,
                            permission_level: p.permission_level || 'siteFullUser'
                        });
                    }
                }
            }

            if (mappingsToSave.length === 0) {
                alert('Please select at least one property and assign a DGT Website record.');
                return;
            }

            this.modalSaving = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('{{ route('websites.search-console.mappings.batch') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ mappings: mappingsToSave }),
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to save mappings');
                }
            } catch (err) {
                alert('Error saving mappings: ' + err.message);
            } finally {
                this.modalSaving = false;
            }
        },

        async saveSingleModalRow(siteUrl) {
            const wsId = this.modalRowWebsites[siteUrl];
            if (!wsId) return;

            this.modalSaving = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('{{ route('websites.search-console.mappings.batch') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        mappings: [{ website_id: parseInt(wsId), site_url: siteUrl, permission_level: 'siteFullUser' }]
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to save mapping');
                }
            } catch (err) {
                alert('Error saving mapping: ' + err.message);
            } finally {
                this.modalSaving = false;
            }
        },

        get filteredBatchRows() {
            let list = this.batchMatches;
            if (this.batchFilter === 'unmapped') {
                list = list.filter(r => !r.is_already_mapped);
            } else if (this.batchFilter === 'mapped') {
                list = list.filter(r => r.is_already_mapped);
            }
            if (this.batchSearch && this.batchSearch.trim()) {
                const q = this.batchSearch.toLowerCase().trim();
                list = list.filter(r => (r.website_name || '').toLowerCase().includes(q) || (r.website_domain || '').toLowerCase().includes(q));
            }
            return list;
        },

        // Advanced Export Modal State
        showExportModal: false,
        exportModalFormat: 'pdf',
        exportModalView: 'overview',
        exportModalSiteA: {{ count($resolvedWebsiteIds) > 0 ? $resolvedWebsiteIds[0] : 'null' }},
        exportModalSiteB: {{ count($resolvedWebsiteIds) > 1 ? $resolvedWebsiteIds[1] : (count($resolvedWebsiteIds) > 0 ? $resolvedWebsiteIds[0] : 'null') }},
        exportModalSitemapSite: {{ count($resolvedWebsiteIds) > 0 ? $resolvedWebsiteIds[0] : (!empty($websitesList) ? $websitesList[0]['id'] : 'null') }},
        exportModalFilters: {
            range: '{{ $dateRange['preset'] ?? '28d' }}',
            start_date: '{{ $dateRange['start'] }}',
            end_date: '{{ $dateRange['end'] }}',
            class: '{{ $selectedClass ?? 'all' }}',
            members: '{{ implode(',', $selectedMembers) }}',
            websites: '',
            report: 'growth',
        },
        exportModalWebsiteSearch: '',
        openExportWebsiteDropdown: false,

        // Filters
        filters: {
            range: '{{ $dateRange['preset'] ?? '28d' }}',
            start_date: '{{ $dateRange['start'] }}',
            end_date: '{{ $dateRange['end'] }}',
            websites: '{{ implode(',', $selectedWebsites) }}',
            members: '{{ implode(',', $selectedMembers) }}',
            class: '{{ $selectedClass ?? 'all' }}',
        },

        dateRange: @json($dateRange),
        kpi: @json($kpiSummary),
        trendData: @json($dailyTrend),

        // Keywords
        keywordsList: @json($keywords['data']),
        keywordsTotal: {{ $keywords['total'] }},
        keywordsPages: {{ $keywords['pages'] }},
        keywordsCurrentPage: {{ $keywords['current_page'] }},
        keywordSearch: '',
        keywordSort: 'clicks',
        keywordDir: 'desc',

        // Keyword details modal state
        inspectedKeyword: '',
        keywordDetailSummary: {},
        keywordDetailDaily: [],

        // Top pages
        topPagesList: @json($topPages['data']),
        topPagesTotal: {{ $topPages['total'] }},
        pageSearch: '',

        // Countries & Devices
        countriesList: @json($countries),
        devicesList: @json($devices),
        websiteBreakdown: @json($websiteBreakdown),

        // Multi-select websites & class filtering
        allWebsites: @json($websitesList ?? []),
        availableClasses: @json($availableClasses ?? []),
        selectedWebsites: @json(array_values(array_map('intval', $selectedWebsites ?? []))),
        websiteSearch: '',
        classFilter: '{{ $selectedClass ?? 'all' }}',
        openWebsiteDropdown: false,

        get filteredWebsitesList() {
            let list = this.allWebsites;
            if (this.classFilter !== 'all') {
                list = list.filter(w => w.class === this.classFilter);
            }
            if (this.websiteSearch && this.websiteSearch.trim() !== '') {
                const s = this.websiteSearch.toLowerCase();
                list = list.filter(w =>
                    (w.name && w.name.toLowerCase().includes(s)) ||
                    (w.domain && w.domain.toLowerCase().includes(s)) ||
                    (w.handler && w.handler.name && w.handler.name.toLowerCase().includes(s))
                );
            }
            return list;
        },

        get groupedWebsites() {
            const groups = {};
            const list = this.filteredWebsitesList;
            list.forEach(w => {
                const cls = w.class || 'General';
                if (!groups[cls]) {
                    groups[cls] = [];
                }
                groups[cls].push(w);
            });
            return groups;
        },

        toggleWebsite(id) {
            const numId = parseInt(id, 10);
            const idx = this.selectedWebsites.indexOf(numId);
            if (idx > -1) {
                this.selectedWebsites.splice(idx, 1);
            } else {
                this.selectedWebsites.push(numId);
            }
            this.filters.websites = this.selectedWebsites.join(',');
        },

        toggleSelectClass(group) {
            const groupIds = group.map(w => w.id);
            const allSelected = groupIds.every(id => this.selectedWebsites.includes(id));
            if (allSelected) {
                this.selectedWebsites = this.selectedWebsites.filter(id => !groupIds.includes(id));
            } else {
                groupIds.forEach(id => {
                    if (!this.selectedWebsites.includes(id)) {
                        this.selectedWebsites.push(id);
                    }
                });
            }
            this.filters.websites = this.selectedWebsites.join(',');
            this.onFilterChange();
        },

        selectAllWebsites() {
            this.selectedWebsites = this.allWebsites.map(w => w.id);
            this.filters.websites = this.selectedWebsites.join(',');
            this.onFilterChange();
        },

        selectMappedWebsites() {
            this.selectedWebsites = this.allWebsites.filter(w => w.is_mapped).map(w => w.id);
            this.filters.websites = this.selectedWebsites.join(',');
            this.onFilterChange();
        },

        clearWebsites() {
            this.selectedWebsites = [];
            this.filters.websites = '';
            this.onFilterChange();
        },

        applyWebsitesFilter() {
            this.filters.websites = this.selectedWebsites.join(',');
            this.onFilterChange();
        },

        onClassChange() {
            this.classFilter = this.filters.class;
            this.selectedWebsites = [];
            this.filters.websites = '';
            this.onFilterChange();
        },

        getSingleWebsiteName() {
            if (this.selectedWebsites.length !== 1) return '';
            const ws = this.allWebsites.find(w => w.id === this.selectedWebsites[0]);
            return ws ? ws.name + ' (' + ws.domain + ')' : '';
        },

        // Mapping form
        mappingForm: {
            website_id: '',
            site_url: '',
        },
        fetchedProperties: @json($availableProperties ?? []),
        mappingWsDropdownOpen: false,
        mappingWsSearch: '',
        mappingWsClassFilter: 'all',

        get filteredMappingWebsites() {
            let list = this.allWebsites || [];
            if (this.mappingWsClassFilter !== 'all') {
                list = list.filter(w => (w.class || 'General') === this.mappingWsClassFilter);
            }
            if (this.mappingWsSearch && this.mappingWsSearch.trim() !== '') {
                const s = this.mappingWsSearch.toLowerCase().trim();
                list = list.filter(w => {
                    const name = (w.name || '').toLowerCase();
                    const domain = (w.domain || '').toLowerCase();
                    const cls = (w.class || '').toLowerCase();
                    const handler = (w.handler_name || (w.handler && w.handler.name) || '').toLowerCase();
                    const handleAssigned = (w.handle_assigned || '').toLowerCase();
                    const assignees = (w.assignee_names || []).join(' ').toLowerCase();

                    return name.includes(s) ||
                           domain.includes(s) ||
                           cls.includes(s) ||
                           handler.includes(s) ||
                           handleAssigned.includes(s) ||
                           assignees.includes(s);
                });
            }
            return list;
        },

        get selectedMappingWebsite() {
            if (!this.mappingForm.website_id) return null;
            return (this.allWebsites || []).find(w => w.id === parseInt(this.mappingForm.website_id, 10)) || null;
        },

        selectMappingWebsite(website) {
            this.mappingForm.website_id = website.id;
            this.mappingWsDropdownOpen = false;
            this.mappingWsSearch = '';

            // If a Google property was already discovered that matches this website domain, auto-fill it!
            if (!this.mappingForm.site_url && this.fetchedProperties && this.fetchedProperties.length > 0) {
                const cleanWsDomain = (website.domain || '').toLowerCase().replace(/^(https?:\/\/)/, '').replace(/\/$/, '');
                const matchingProp = this.fetchedProperties.find(p => {
                    const cleanP = (p.site_url || '').toLowerCase().replace('sc-domain:', '').replace(/^(https?:\/\/)/, '').replace(/\/$/, '');
                    return cleanP === cleanWsDomain || cleanP.includes(cleanWsDomain) || cleanWsDomain.includes(cleanP);
                });
                if (matchingProp) {
                    this.mappingForm.site_url = matchingProp.site_url;
                }
            }
        },

        clearSelectedMappingWebsite() {
            this.mappingForm.website_id = '';
            this.mappingWsSearch = '';
        },

        chartInstance: null,

        init() {
            window.currentGscDashboard = this;
            this.$nextTick(async () => {
                try {
                    if (this.activeView === 'overview') {
                        await this.ensureChartJs();
                        this.renderChart();
                        setTimeout(() => {
                            if (!this.chartInstance && this.activeView === 'overview') {
                                this.renderChart();
                            }
                        }, 120);
                    } else if (this.activeView === 'compare') {
                        await this.ensureChartJs();
                        this.renderCompareChart();
                        setTimeout(() => {
                            if (!this.compareChartInstance && this.activeView === 'compare') {
                                this.renderCompareChart();
                            }
                        }, 120);
                    } else if (this.activeView === 'properties') {
                        if (!this.batchMatches || this.batchMatches.length === 0) {
                            this.loadAutoMatchData();
                        }
                    } else if (this.activeView === 'sitemaps') {
                        this.onSitemapWebsiteChange(this.sitemapWebsiteId);
                    }
                    this.initModalBatch();
                    this.checkAutoSync();
                } catch (err) {
                    console.warn('GSC Dashboard init error caught:', err);
                }
            });

            if (!window._gscTurboHooksRegistered) {
                window._gscTurboHooksRegistered = true;

                document.addEventListener('turbo:load', () => {
                    setTimeout(() => {
                        if (window.currentGscDashboard) {
                            if (window.currentGscDashboard.activeView === 'overview') {
                                window.currentGscDashboard.renderChart();
                            } else if (window.currentGscDashboard.activeView === 'compare') {
                                window.currentGscDashboard.renderCompareChart();
                            }
                        }
                    }, 50);
                });

                document.addEventListener('turbo:before-cache', () => {
                    if (window.Chart && typeof window.Chart.getChart === 'function') {
                        const perf = window.Chart.getChart('gscPerformanceChart');
                        if (perf) try { perf.destroy(); } catch (e) {}
                        const comp = window.Chart.getChart('gscComparisonChartCanvas');
                        if (comp) try { comp.destroy(); } catch (e) {}
                    }
                    if (window.currentGscDashboard) {
                        if (window.currentGscDashboard.chartInstance) {
                            try { window.currentGscDashboard.chartInstance.destroy(); } catch (e) {}
                            window.currentGscDashboard.chartInstance = null;
                        }
                        if (window.currentGscDashboard.compareChartInstance) {
                            try { window.currentGscDashboard.compareChartInstance.destroy(); } catch (e) {}
                            window.currentGscDashboard.compareChartInstance = null;
                        }
                    }
                });
            }
        },

        async checkAutoSync() {
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('{{ route('websites.search-console.auto-sync-check') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    }
                });
                const data = await res.json();
                if (data.synced) {
                    await this.onFilterChange();
                }
            } catch (err) {
                // Auto-sync completed in background
            }
        },

        async ensureChartJs() {
            if (window.Chart) return window.Chart;
            if (window.loadChart) {
                try {
                    await window.loadChart();
                    if (window.Chart) return window.Chart;
                } catch (e) {}
            }
            // Poll for window.Chart in case Vite bundle or script is executing
            for (let i = 0; i < 40; i++) {
                if (window.Chart) return window.Chart;
                await new Promise(r => setTimeout(r, 50));
            }
            // Fallback load CDN script if still not found
            if (!window.Chart) {
                await new Promise((resolve) => {
                    const existing = document.getElementById('chartjs-cdn-fallback');
                    if (existing) {
                        existing.onload = () => resolve(window.Chart);
                        existing.onerror = () => resolve(null);
                        return;
                    }
                    const script = document.createElement('script');
                    script.id = 'chartjs-cdn-fallback';
                    script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';
                    script.onload = () => resolve(window.Chart);
                    script.onerror = () => resolve(null);
                    document.head.appendChild(script);
                });
            }
            return window.Chart;
        },

        renderChart() {
            if (this.activeView !== 'overview') return;
            const canvas = document.getElementById('gscPerformanceChart');
            if (!canvas) {
                setTimeout(() => this.renderChart(), 80);
                return;
            }

            const rect = canvas.getBoundingClientRect();
            if (rect.width === 0 && canvas.offsetParent === null) {
                setTimeout(() => this.renderChart(), 60);
                return;
            }

            if (!window.Chart) {
                this.ensureChartJs().then(() => {
                    if (window.Chart) {
                        requestAnimationFrame(() => this.renderChart());
                    } else {
                        setTimeout(() => this.renderChart(), 150);
                    }
                });
                return;
            }

            // Always destroy any pre-existing instance attached to this canvas to prevent "Canvas is already in use"
            try {
                const existing = window.Chart.getChart ? window.Chart.getChart(canvas) : null;
                if (existing) {
                    existing.destroy();
                }
            } catch (e) {
                console.warn('Error destroying existing Chart.js canvas instance', e);
            }

            if (this.chartInstance) {
                try {
                    this.chartInstance.destroy();
                } catch (e) {}
                this.chartInstance = null;
            }

            const ctx = canvas.getContext('2d');
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#94a3b8' : '#64748b';
            const gridColor = isDark ? 'rgba(51, 65, 85, 0.4)' : 'rgba(226, 232, 240, 0.8)';

            const labels = (this.trendData && this.trendData.labels) ? this.trendData.labels : [];
            const clicks = (this.trendData && this.trendData.clicks) ? this.trendData.clicks : [];
            const impressions = (this.trendData && this.trendData.impressions) ? this.trendData.impressions : [];
            const ctr = (this.trendData && this.trendData.ctr) ? this.trendData.ctr : [];
            const position = (this.trendData && this.trendData.position) ? this.trendData.position : [];

            let datasets = [];
            let scales = {
                x: {
                    grid: { color: gridColor },
                    ticks: { color: textColor, font: { size: 10 } },
                }
            };

            if (this.activeMetric === 'clicks_impressions') {
                // Dual Axis: Clicks & Impressions
                datasets = [
                    {
                        label: 'Clicks',
                        data: clicks,
                        borderColor: '#2563eb', // Blue-600
                        backgroundColor: 'rgba(37, 99, 235, 0.12)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        yAxisID: 'y_clicks',
                        pointRadius: 2,
                    },
                    {
                        label: 'Impressions',
                        data: impressions,
                        borderColor: '#818cf8', // Indigo-400
                        backgroundColor: 'rgba(129, 140, 248, 0.05)',
                        borderWidth: 1.8,
                        borderDash: [4, 4],
                        fill: false,
                        tension: 0.35,
                        yAxisID: 'y_imp',
                        pointRadius: 1.5,
                    }
                ];

                scales.y_clicks = {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: { color: '#2563eb', font: { size: 10 } },
                    title: { display: true, text: 'Clicks', color: '#2563eb', font: { size: 11, weight: 'bold' } }
                };

                scales.y_imp = {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                    ticks: { color: '#818cf8', font: { size: 10 } },
                    title: { display: true, text: 'Impressions', color: '#818cf8', font: { size: 11, weight: 'bold' } }
                };

            } else if (this.activeMetric === 'ctr') {
                // CTR Trend
                datasets = [
                    {
                        label: 'Click-Through Rate (CTR %)',
                        data: ctr,
                        borderColor: '#059669', // Emerald-600
                        backgroundColor: 'rgba(5, 150, 105, 0.15)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2.5,
                    }
                ];

                scales.y = {
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: {
                        color: textColor,
                        callback: (v) => v + '%',
                        font: { size: 10 }
                    },
                    title: { display: true, text: 'CTR (%)', color: '#059669', font: { size: 11, weight: 'bold' } }
                };

            } else if (this.activeMetric === 'position') {
                // Average Position Trend (Inverted axis where 1 is top/best)
                datasets = [
                    {
                        label: 'Average Position',
                        data: position,
                        borderColor: '#d97706', // Amber-600
                        backgroundColor: 'rgba(217, 119, 6, 0.12)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2.5,
                    }
                ];

                scales.y = {
                    reverse: true, // Lower position is better
                    grid: { color: gridColor },
                    ticks: { color: textColor, font: { size: 10 } },
                    title: { display: true, text: 'Position (Lower is Better)', color: '#d97706', font: { size: 11, weight: 'bold' } }
                };
            }

            try {
                this.chartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: datasets,
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'top', labels: { color: textColor, font: { size: 11 } } },
                            tooltip: {
                                backgroundColor: isDark ? '#1e293b' : '#ffffff',
                                titleColor: isDark ? '#f8fafc' : '#0f172a',
                                bodyColor: isDark ? '#cbd5e1' : '#334155',
                                borderColor: isDark ? '#334155' : '#e2e8f0',
                                borderWidth: 1,
                                padding: 10,
                                boxPadding: 4,
                            }
                        },
                        scales: scales,
                    }
                });
            } catch (err) {
                console.error('Error instantiating Chart.js', err);
            }
        },

        setChartMetric(metric) {
            this.activeMetric = metric;
            this.renderChart();
        },

        async onFilterChange() {
            this.loading = true;
            try {
                const isCustom = (this.filters.range === 'custom');
                const params = new URLSearchParams({
                    range: this.filters.range,
                    start_date: isCustom ? (this.filters.start_date || '') : '',
                    end_date: isCustom ? (this.filters.end_date || '') : '',
                    websites: this.filters.websites || '',
                    members: this.filters.members || '',
                    class: (this.filters.class && this.filters.class !== 'all') ? this.filters.class : '',
                    keyword_search: this.keywordSearch || '',
                    sort_by: this.keywordSort,
                    sort_dir: this.keywordDir,
                });

                const res = await fetch(`{{ route('websites.search-console.data') }}?${params.toString()}`);
                const data = await res.json();

                if (data.success) {
                    this.dateRange = data.date_range;
                    if (!isCustom && data.date_range) {
                        this.filters.start_date = data.date_range.start;
                        this.filters.end_date = data.date_range.end;
                    }
                    this.kpi = data.kpi_summary;
                    this.trendData = data.daily_trend;
                    this.keywordsList = data.keywords.data;
                    this.keywordsTotal = data.keywords.total;
                    this.keywordsPages = data.keywords.pages;
                    this.keywordsCurrentPage = data.keywords.current_page;
                    this.topPagesList = data.top_pages.data;
                    this.topPagesTotal = data.top_pages.total;
                    this.countriesList = data.countries;
                    this.devicesList = data.devices;
                    this.websiteBreakdown = data.website_breakdown;
                    this.websiteRankings = data.website_breakdown;

                    this.renderChart();
                }
            } catch (err) {
                console.error('Filter update error', err);
            } finally {
                this.loading = false;
            }
        },

        async fetchKeywords() {
            const isCustom = (this.filters.range === 'custom');
            const params = new URLSearchParams({
                range: this.filters.range,
                start_date: isCustom ? (this.filters.start_date || '') : '',
                end_date: isCustom ? (this.filters.end_date || '') : '',
                websites: this.filters.websites || '',
                members: this.filters.members || '',
                class: (this.filters.class && this.filters.class !== 'all') ? this.filters.class : '',
                keyword_search: this.keywordSearch,
                sort_by: this.keywordSort,
                sort_dir: this.keywordDir,
                page: this.keywordsCurrentPage,
            });

            const res = await fetch(`{{ route('websites.search-console.data') }}?${params.toString()}`);
            const data = await res.json();
            if (data.success) {
                this.keywordsList = data.keywords.data;
                this.keywordsTotal = data.keywords.total;
                this.keywordsPages = data.keywords.pages;
                this.keywordsCurrentPage = data.keywords.current_page;
            }
        },

        sortKeywords(col) {
            if (this.keywordSort === col) {
                this.keywordDir = (this.keywordDir === 'asc') ? 'desc' : 'asc';
            } else {
                this.keywordSort = col;
                this.keywordDir = 'desc';
            }
            this.fetchKeywords();
        },

        changeKeywordPage(page) {
            if (page < 1 || page > this.keywordsPages) return;
            this.keywordsCurrentPage = page;
            this.fetchKeywords();
        },

        async inspectKeyword(keyword) {
            this.inspectedKeyword = keyword;
            this.showKeywordModal = true;
            try {
                const isCustom = (this.filters.range === 'custom');
                const params = new URLSearchParams({
                    keyword: keyword,
                    range: this.filters.range,
                    start_date: isCustom ? (this.filters.start_date || '') : '',
                    end_date: isCustom ? (this.filters.end_date || '') : '',
                    websites: this.filters.websites || '',
                    members: this.filters.members || '',
                });

                const res = await fetch(`{{ route('websites.search-console.keyword-details') }}?${params.toString()}`);
                const data = await res.json();
                this.keywordDetailSummary = data.summary || {};
                this.keywordDetailDaily = data.daily || [];
            } catch (err) {
                console.error('Inspect keyword error', err);
            }
        },

        openManageModal(event = null) {
            if (event) {
                try { event.preventDefault(); } catch (e) {}
                try { event.stopPropagation(); } catch (e) {}
            }
            this.setView('properties');
            this.showManageModal = true;
            try {
                this.initModalBatch();
            } catch (err) {
                console.warn('initModalBatch warning:', err);
            }
            try {
                this.fetchGscProperties();
            } catch (err) {
                console.warn('fetchGscProperties warning:', err);
            }
        },

        async fetchGscProperties() {
            try {
                const res = await fetch('{{ route('websites.search-console.properties') }}');
                const data = await res.json();
                if (data.success) {
                    this.fetchedProperties = data.properties || [];
                    this.initModalBatch();
                }
            } catch (err) {
                console.error('Fetch properties error', err);
            }
        },

        async saveMappingForm() {
            this.savingMapping = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('{{ route('websites.search-console.mappings.save') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.mappingForm),
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to save mapping');
                }
            } catch (err) {
                alert('Save mapping error: ' + err.message);
            } finally {
                this.savingMapping = false;
            }
        },

        async deleteMapping(id) {
            if (!confirm('Remove this property mapping?')) return;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch(`{{ url('/websites/google-search-console/mappings') }}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                }
            } catch (err) {
                alert('Delete error: ' + err.message);
            }
        },

        async syncNow(websiteId = null) {
            this.syncing = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('{{ route('websites.search-console.sync') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ website_id: websiteId, days: 30 }),
                });
                const data = await res.json();
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('Sync failed: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Sync request error: ' + err.message);
            } finally {
                this.syncing = false;
            }
        },

        setView(view) {
            this.activeView = view;
            const url = new URL(window.location);
            url.searchParams.set('view', view);
            window.history.pushState({}, '', url);

            if (view === 'compare') {
                this.$nextTick(async () => {
                    await this.ensureChartJs();
                    requestAnimationFrame(() => this.renderCompareChart());
                });
            } else if (view === 'properties') {
                if (this.batchMatches.length === 0) {
                    this.loadAutoMatchData();
                }
            } else if (view === 'sitemaps') {
                this.onSitemapWebsiteChange(this.sitemapWebsiteId);
            } else if (view === 'overview') {
                this.$nextTick(async () => {
                    await this.ensureChartJs();
                    requestAnimationFrame(() => this.renderChart());
                });
            }
        },

        startComparisonWith(websiteId) {
            this.compareSiteA = websiteId;
            if (this.compareSiteB === websiteId) {
                const other = this.allWebsites.find(w => w.id !== websiteId);
                this.compareSiteB = other ? other.id : websiteId;
            }
            this.setView('compare');
            this.fetchComparison();
        },

        filterToSingleWebsite(websiteId) {
            this.selectedWebsites = [parseInt(websiteId, 10)];
            this.filters.websites = String(websiteId);
            this.setView('overview');
            this.onFilterChange();
        },

        async fetchComparison() {
            if (!this.compareSiteA && !this.compareSiteB) return;
            this.loadingCompare = true;
            try {
                const sites = [this.compareSiteA, this.compareSiteB].filter(Boolean);
                const startDate = (this.filters.range === 'custom') ? (this.filters.start_date || '') : '';
                const endDate = (this.filters.range === 'custom') ? (this.filters.end_date || '') : '';

                const params = new URLSearchParams({
                    websites: sites.join(','),
                    range: this.filters.range,
                    start_date: startDate,
                    end_date: endDate,
                });

                const res = await fetch(`{{ route('websites.search-console.compare-data') }}?${params.toString()}`);
                const data = await res.json();
                if (data.success) {
                    this.comparisonData = data.comparison;
                    this.$nextTick(async () => {
                        await this.ensureChartJs();
                        this.renderCompareChart();
                    });
                }
            } catch (err) {
                console.error('Fetch comparison error', err);
            } finally {
                this.loadingCompare = false;
            }
        },

        renderCompareChart() {
            if (this.activeView !== 'compare') return;
            const canvas = document.getElementById('gscComparisonChartCanvas');
            if (!canvas) {
                setTimeout(() => this.renderCompareChart(), 80);
                return;
            }

            const rect = canvas.getBoundingClientRect();
            if (rect.width === 0 && canvas.offsetParent === null) {
                setTimeout(() => this.renderCompareChart(), 60);
                return;
            }

            if (!window.Chart) {
                this.ensureChartJs().then(() => {
                    if (window.Chart) {
                        requestAnimationFrame(() => this.renderCompareChart());
                    } else {
                        setTimeout(() => this.renderCompareChart(), 150);
                    }
                });
                return;
            }

            try {
                const existing = window.Chart.getChart ? window.Chart.getChart(canvas) : null;
                if (existing) {
                    existing.destroy();
                }
            } catch (e) {
                console.warn('Error destroying existing Compare Chart.js canvas instance', e);
            }

            if (this.compareChartInstance) {
                try {
                    this.compareChartInstance.destroy();
                } catch (e) {}
                this.compareChartInstance = null;
            }

            const ctx = canvas.getContext('2d');
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#94a3b8' : '#64748b';
            const gridColor = isDark ? 'rgba(51, 65, 85, 0.4)' : 'rgba(226, 232, 240, 0.8)';

            const datasets = (this.compareMetric === 'impressions')
                ? (this.comparisonData.datasets_impressions || [])
                : (this.comparisonData.datasets_clicks || []);

            try {
                this.compareChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: this.comparisonData.chart_dates || [],
                        datasets: datasets,
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'top', labels: { color: textColor, font: { size: 11, weight: 'bold' } } },
                            tooltip: {
                                backgroundColor: isDark ? '#1e293b' : '#ffffff',
                                titleColor: isDark ? '#f8fafc' : '#0f172a',
                                bodyColor: isDark ? '#cbd5e1' : '#334155',
                                borderColor: isDark ? '#334155' : '#e2e8f0',
                                borderWidth: 1,
                                padding: 10,
                            }
                        },
                        scales: {
                            x: {
                                grid: { color: gridColor },
                                ticks: { color: textColor, font: { size: 10 } }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: gridColor },
                                ticks: { color: textColor, font: { size: 10 } },
                                title: {
                                    display: true,
                                    text: this.compareMetric === 'impressions' ? 'Daily Impressions' : 'Daily Clicks',
                                    color: textColor,
                                    font: { size: 11, weight: 'bold' }
                                }
                            }
                        }
                    }
                });
            } catch (err) {
                console.error('Error instantiating Comparison Chart.js', err);
            }
        },

        async loadAutoMatchData() {
            this.batchLoading = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('{{ route('websites.search-console.mappings.auto-match') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token || ''
                    }
                });
                const data = await res.json();
                if (data.success) {
                    this.batchMatches = data.matches || [];
                    this.fetchedProperties = data.properties || [];

                    this.selectedBatchWebsites = [];
                    this.batchMatches.forEach(m => {
                        if (m.suggested_site_url) {
                            this.batchRowProperties[m.website_id] = m.suggested_site_url;
                        }
                        if (!m.is_already_mapped && m.suggested_site_url) {
                            this.selectedBatchWebsites.push(m.website_id);
                        }
                    });
                }
            } catch (err) {
                console.error('Auto match fetch error', err);
            } finally {
                this.batchLoading = false;
            }
        },

        toggleSelectAllBatch() {
            const visibleIds = this.filteredBatchRows.map(r => r.website_id);
            const allSelected = visibleIds.every(id => this.selectedBatchWebsites.includes(id));
            if (allSelected) {
                this.selectedBatchWebsites = this.selectedBatchWebsites.filter(id => !visibleIds.includes(id));
            } else {
                visibleIds.forEach(id => {
                    if (!this.selectedBatchWebsites.includes(id)) {
                        this.selectedBatchWebsites.push(id);
                    }
                });
            }
        },

        async saveBatchMappings() {
            if (this.selectedBatchWebsites.length === 0) {
                alert('Please select at least one website to save.');
                return;
            }

            const mappingsToSave = [];
            for (const wsId of this.selectedBatchWebsites) {
                const propUrl = this.batchRowProperties[wsId];
                if (propUrl) {
                    mappingsToSave.push({
                        website_id: wsId,
                        site_url: propUrl,
                        permission_level: 'siteFullUser'
                    });
                }
            }

            if (mappingsToSave.length === 0) {
                alert('None of the selected websites have a Google property selected.');
                return;
            }

            this.batchSaving = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('{{ route('websites.search-console.mappings.batch') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ mappings: mappingsToSave }),
                });
                const data = await res.json();
                if (data.success) {
                    this.batchSuccessMessage = data.message || `Saved and synced ${data.count} websites!`;
                    setTimeout(() => {
                        window.location.reload();
                    }, 1200);
                } else {
                    alert(data.error || 'Failed to save batch mappings');
                }
            } catch (err) {
                alert('Batch save error: ' + err.message);
            } finally {
                this.batchSaving = false;
            }
        },

        openPropertyModalFor(websiteId) {
            if (websiteId) {
                const ws = (this.allWebsites || []).find(w => String(w.id) === String(websiteId));
                if (ws) {
                    this.selectMappingWebsite(ws);
                } else {
                    this.mappingForm.website_id = websiteId;
                }
            }
            this.showManageModal = true;
        },

        onSitemapWebsiteChange(websiteId, forceRefresh = false) {
            if (websiteId) {
                this.sitemapWebsiteId = websiteId;
            }
            this.sitemapFeedback = null;
            this.sitemapTestResult = null;
            this.inspectionResult = null;

            // Auto fill default sitemap url and inspection url if not yet set
            const ws = this.selectedSitemapWebsite;
            if (ws && ws.domain) {
                if (!this.sitemapUrlInput) {
                    this.sitemapUrlInput = 'https://' + ws.domain + '/sitemap.xml';
                }
                if (!this.inspectUrlInput) {
                    this.inspectUrlInput = 'https://' + ws.domain + '/';
                }
            }

            if (this.isSelectedWebsiteMapped) {
                this.loadSitemaps(this.sitemapWebsiteId);
                this.loadIndexStatus(1, forceRefresh);
            }
        },

        async loadSitemaps(websiteId) {
            const id = websiteId || this.sitemapWebsiteId;
            if (!id || !this.isSelectedWebsiteMapped) return;
            this.sitemapsLoading = true;
            try {
                const res = await fetch(`{{ route('websites.search-console.sitemaps') }}?website_id=${id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.sitemapsList = data.sitemaps || [];
                    if (data.summary) {
                        this.indexationSummary = {
                            ...this.indexationSummary,
                            sitemaps_count: data.summary.sitemaps_count || this.sitemapsList.length,
                            sitemaps_submitted_urls: data.summary.submitted_urls || 0,
                            sitemaps_indexed_urls: data.summary.indexed_urls || 0,
                        };
                    }
                } else {
                    this.sitemapsList = [];
                }
            } catch (err) {
                console.error('Error fetching sitemaps:', err);
            } finally {
                this.sitemapsLoading = false;
            }
        },

        async testSitemapReachability() {
            if (!this.sitemapUrlInput || !this.sitemapUrlInput.trim()) {
                alert('Please enter or select a sitemap URL first');
                return;
            }
            this.sitemapTesting = true;
            this.sitemapTestResult = null;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('{{ route('websites.search-console.sitemaps.test') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ sitemap_url: this.sitemapUrlInput.trim() }),
                });
                const data = await res.json();
                this.sitemapTestResult = data;
            } catch (err) {
                this.sitemapTestResult = {
                    reachable: false,
                    message: 'Failed to test URL: ' + err.message,
                };
            } finally {
                this.sitemapTesting = false;
            }
        },

        async submitSitemap() {
            if (!this.sitemapWebsiteId) {
                alert('Please select a website first');
                return;
            }
            if (!this.isSelectedWebsiteMapped) {
                alert('This website is not mapped with Google Search Console yet. Please map it first.');
                this.openPropertyModalFor(this.sitemapWebsiteId);
                return;
            }
            if (!this.sitemapUrlInput || !this.sitemapUrlInput.trim()) {
                alert('Please enter a valid sitemap URL');
                return;
            }

            this.sitemapSubmitting = true;
            this.sitemapFeedback = null;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('{{ route('websites.search-console.sitemaps.submit') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        website_id: this.sitemapWebsiteId,
                        sitemap_url: this.sitemapUrlInput.trim(),
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    this.sitemapFeedback = {
                        type: 'success',
                        text: '✓ ' + (data.message || 'Sitemap successfully submitted to Google Search Console!'),
                    };
                    await this.loadSitemaps(this.sitemapWebsiteId);
                    await this.loadIndexStatus(1);
                } else {
                    this.sitemapFeedback = {
                        type: 'error',
                        text: '✕ ' + (data.message || data.error || 'Submission could not be completed.'),
                        requiresReconnect: !!data.requires_reconnect,
                    };
                }
            } catch (err) {
                this.sitemapFeedback = {
                    type: 'error',
                    text: '✕ Error: ' + err.message,
                    requiresReconnect: false,
                };
            } finally {
                this.sitemapSubmitting = false;
            }
        },

        async deleteSitemap(sitemapPath) {
            if (!confirm(`Are you sure you want to remove this sitemap from Google Search Console?\n\n${sitemapPath}`)) {
                return;
            }
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('{{ route('websites.search-console.sitemaps.delete') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        website_id: this.sitemapWebsiteId,
                        sitemap_url: sitemapPath,
                        sitemap_path: sitemapPath,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    this.sitemapFeedback = {
                        type: 'success',
                        text: data.message || 'Sitemap deleted from Google Search Console.',
                    };
                    await this.loadSitemaps(this.sitemapWebsiteId);
                } else {
                    alert('Failed to delete sitemap: ' + (data.message || data.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Delete error: ' + err.message);
            }
        },

        async loadIndexStatus(page = 1, forceRefresh = false) {
            if (!this.sitemapWebsiteId || !this.isSelectedWebsiteMapped) return;
            this.indexationLoading = true;
            this.indexationPage = page;
            try {
                const params = new URLSearchParams({
                    website_id: this.sitemapWebsiteId,
                    filter: this.indexationFilter,
                    search: this.indexationSearch || '',
                    page: page,
                    per_page: this.indexationPerPage,
                });
                if (forceRefresh) {
                    params.set('refresh', '1');
                }
                const res = await fetch(`{{ route('websites.search-console.index-status') }}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.indexationPages = Array.isArray(data.pages) 
                        ? data.pages 
                        : (Array.isArray(data.pages?.data) ? data.pages.data : []);
                    this.indexationTotal = data.pages?.total 
                        ?? data.pagination?.total 
                        ?? (Array.isArray(data.pages) ? data.pages.length : (this.indexationPages || []).length);
                    this.indexationPerPage = data.pages?.per_page || this.indexationPerPage || 25;
                    if (data.summary) {
                        this.indexationSummary = {
                            ...this.indexationSummary,
                            ...data.summary,
                        };
                    }
                }
            } catch (err) {
                console.error('Error loading index status:', err);
            } finally {
                this.indexationLoading = false;
            }
        },

        async autoSyncGscIndexing() {
            if (!this.sitemapWebsiteId) return;
            this.indexationLoading = true;
            try {
                await Promise.all([
                    this.loadIndexStatus(1, true),
                    this.loadSitemaps()
                ]);
            } catch (err) {
                console.error('Error auto-syncing GSC indexation:', err);
            } finally {
                this.indexationLoading = false;
            }
        },

        openCoverageModal() {
            this.coverageForm = {
                indexed_pages: this.indexationSummary.total_indexed || 0,
                not_indexed_pages: this.indexationSummary.total_not_indexed || 0,
                updated_date: this.indexationSummary.gsc_last_updated || new Date().toISOString().split('T')[0],
                reasons_text: Array.isArray(this.indexationSummary.gsc_reasons)
                    ? this.indexationSummary.gsc_reasons.map(r => `${r.count || ''} ${r.reason || ''}`.trim()).join('\n')
                    : ''
            };
            this.showCoverageModal = true;
        },

        async saveCoverageSnapshot() {
            if (!this.sitemapWebsiteId) return;
            this.coverageSaving = true;
            try {
                const res = await fetch('{{ route('websites.search-console.sitemaps.update-coverage') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        website_id: this.sitemapWebsiteId,
                        indexed_pages: parseInt(this.coverageForm.indexed_pages, 10) || 0,
                        not_indexed_pages: parseInt(this.coverageForm.not_indexed_pages, 10) || 0,
                        updated_date: this.coverageForm.updated_date,
                        reasons_text: this.coverageForm.reasons_text,
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.showCoverageModal = false;
                    await this.loadIndexStatus(1, true);
                } else {
                    alert('Failed to save snapshot: ' + (data.message || 'Unknown error'));
                }
            } catch (err) {
                alert('Save error: ' + err.message);
            } finally {
                this.coverageSaving = false;
            }
        },

        async startBackgroundInspection(force = false) {
            if (!this.sitemapWebsiteId) return;
            this.scanningBackground = true;
            this.scanProgressText = 'Starting scan...';
            try {
                const res = await fetch('{{ route('websites.search-console.sitemaps.start-auto-inspection') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        website_id: this.sitemapWebsiteId,
                        force: force
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.pollInspectionStatus();
                } else {
                    alert(data.message || 'Could not start background scan.');
                    this.scanningBackground = false;
                    this.scanProgressText = 'Auto-Scan All Pages';
                }
            } catch (err) {
                console.error('Scan start error:', err);
                this.scanningBackground = false;
                this.scanProgressText = 'Auto-Scan All Pages';
            }
        },

        pollInspectionStatus() {
            if (this.scanPollTimer) clearInterval(this.scanPollTimer);
            this.scanPollTimer = setInterval(async () => {
                if (!this.sitemapWebsiteId) {
                    clearInterval(this.scanPollTimer);
                    this.scanPollTimer = null;
                    return;
                }
                try {
                    const res = await fetch(`{{ route('websites.search-console.sitemaps.inspection-status') }}?website_id=${this.sitemapWebsiteId}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    if (data.running) {
                        this.scanningBackground = true;
                        if (data.total > 0) {
                            this.scanProgressText = `Scanning ${data.processed}/${data.total} (${data.indexed || 0} indexed)`;
                        } else {
                            this.scanProgressText = data.message || 'Scanning...';
                        }
                    } else {
                        clearInterval(this.scanPollTimer);
                        this.scanPollTimer = null;
                        this.scanningBackground = false;
                        this.scanProgressText = 'Auto-Scan All Pages';
                        await this.loadIndexStatus(1, true);
                    }
                } catch (err) {
                    console.error('Poll status error:', err);
                }
            }, 3000);
        },

        async inspectSingleUrl(targetUrl) {
            const url = targetUrl || this.inspectUrlInput;
            if (!url || !url.trim()) {
                alert('Please enter a full URL to inspect');
                return;
            }
            if (!this.isSelectedWebsiteMapped) {
                alert('Website must be mapped with Google Search Console first.');
                return;
            }
            this.inspectingUrl = true;
            this.inspectUrlInput = url.trim();
            this.inspectionResult = null;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('{{ route('websites.search-console.inspect-url') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        website_id: this.sitemapWebsiteId,
                        url: url.trim(),
                        inspection_url: url.trim(),
                    }),
                });
                const data = await res.json();
                const inspResult = data.inspection || data.data;
                if (data.success && inspResult) {
                    this.inspectionResult = inspResult;
                    // Update matching row in indexationPages table if present
                    const existingIdx = this.indexationPages.findIndex(p => p.url === url.trim());
                    if (existingIdx !== -1) {
                        this.indexationPages[existingIdx].is_indexed = (inspResult.is_indexed !== undefined) ? inspResult.is_indexed : (inspResult.verdict === 'PASS');
                        this.indexationPages[existingIdx].coverage_state = inspResult.coverage_state || this.indexationPages[existingIdx].coverage_state;
                        this.indexationPages[existingIdx].verdict = inspResult.verdict || this.indexationPages[existingIdx].verdict;
                        this.indexationPages[existingIdx].is_inspected = true;
                    }
                } else {
                    alert('URL Inspection error: ' + (data.message || data.error || 'Inspection failed'));
                }
            } catch (err) {
                alert('Inspection request error: ' + err.message);
            } finally {
                this.inspectingUrl = false;
            }
        },

        async testLiveUrl(targetUrl) {
            const url = targetUrl || this.inspectUrlInput;
            if (!url || !url.trim()) {
                alert('Please enter a full URL to test');
                return;
            }
            this.testingLiveUrl = true;
            this.inspectUrlInput = url.trim();
            this.liveTestResult = null;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('{{ route('websites.search-console.test-live-url') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        website_id: this.sitemapWebsiteId,
                        url: url.trim(),
                    }),
                });
                const data = await res.json();
                if (data.success && data.live_test) {
                    this.liveTestResult = data.live_test;
                } else {
                    alert('Live URL test error: ' + (data.message || 'Test failed'));
                }
            } catch (err) {
                alert('Test request error: ' + err.message);
            } finally {
                this.testingLiveUrl = false;
            }
        },

        getGscInspectUrl(targetUrl) {
            const siteUrl = this.selectedSitemapWebsite?.site_url || '';
            const pageUrl = targetUrl || this.inspectUrlInput;
            if (!siteUrl) return 'https://search.google.com/search-console';
            // If official API inspection link exists from Google, use it directly
            if (this.inspectionResult && (this.inspectionResult.url === pageUrl || !targetUrl) && this.inspectionResult.inspection_result_link) {
                return this.inspectionResult.inspection_result_link;
            }
            // Otherwise, open the verified Search Console property dashboard
            return `https://search.google.com/search-console?resource_id=${encodeURIComponent(siteUrl)}`;
        },

        openGscForUrl(targetUrl, event = null) {
            const siteUrl = this.selectedSitemapWebsite?.site_url || '';
            const pageUrl = targetUrl || this.inspectUrlInput;

            if (!siteUrl) {
                if (event) event.preventDefault();
                alert('This website is not yet linked to Google Search Console. Please link it using "Link Property Now" first.');
                return;
            }

            // Copy URL to clipboard so the user can easily paste it into GSC inspection bar
            if (pageUrl && navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(pageUrl).catch(() => {});
            }

            // Also populate the top inspector bar in the DGT system
            if (pageUrl) {
                this.inspectUrlInput = pageUrl;
            }
        },

        cleanDomain(url) {
            if (!url) return '';
            return String(url).replace(/^(sc-domain:|https?:\/\/(www\.)?)/i, '').replace(/\/$/, '');
        },

        get filteredExportModalWebsites() {
            let list = this.allWebsites || [];

            // Filter by Website Class
            if (this.exportModalFilters.class && this.exportModalFilters.class !== 'all') {
                const targetCls = this.exportModalFilters.class.toLowerCase().trim();
                list = list.filter(w => {
                    const wCls = (w.class || 'General').toLowerCase().trim();
                    return wCls === targetCls;
                });
            }

            // Filter by Responsible Member
            if (this.exportModalFilters.members) {
                const memberId = parseInt(this.exportModalFilters.members, 10);
                list = list.filter(w => {
                    const handlerId = w.handler ? parseInt(w.handler.id, 10) : null;
                    const assigneeMatch = Array.isArray(w.assignees) && w.assignees.some(a => parseInt(a.id, 10) === memberId);
                    return handlerId === memberId || assigneeMatch;
                });
            }

            // Filter by Search Query
            if (this.exportModalWebsiteSearch && this.exportModalWebsiteSearch.trim() !== '') {
                const s = this.exportModalWebsiteSearch.toLowerCase().trim();
                list = list.filter(w => {
                    const name = (w.name || '').toLowerCase();
                    const domain = (w.domain || '').toLowerCase();
                    const cls = (w.class || '').toLowerCase();
                    const handler = (w.handler_name || (w.handler ? w.handler.name : '') || '').toLowerCase();
                    const assignees = (w.assignee_names || '').toLowerCase();
                    return name.includes(s) || domain.includes(s) || cls.includes(s) || handler.includes(s) || assignees.includes(s);
                });
            }

            return list;
        },

        getSelectedExportWebsite() {
            if (!this.exportModalFilters.websites) return null;
            return (this.allWebsites || []).find(w => String(w.id) === String(this.exportModalFilters.websites)) || null;
        },

        selectExportWebsite(id) {
            this.exportModalFilters.websites = id ? String(id) : '';
            this.openExportWebsiteDropdown = false;
        },

        onExportModalScopeChange() {
            this.exportModalWebsiteSearch = '';
            if (this.exportModalFilters.websites) {
                const stillValid = this.filteredExportModalWebsites.some(w => String(w.id) === String(this.exportModalFilters.websites));
                if (!stillValid) {
                    this.exportModalFilters.websites = '';
                }
            }
        },

        setExportModalView(view) {
            this.exportModalView = view;
            if (view === 'rankings') {
                this.exportModalFilters.report = 'rankings';
            } else if (view === 'compare') {
                this.exportModalFilters.report = 'compare';
            } else if (view === 'sitemaps') {
                this.exportModalFilters.report = 'sitemaps';
            } else {
                if (!['growth', 'overview', 'keywords', 'pages', 'daily', 'countries'].includes(this.exportModalFilters.report)) {
                    this.exportModalFilters.report = 'growth';
                }
            }
        },

        openExportModal() {
            this.exportModalView = (this.activeView === 'properties') ? 'overview' : (this.activeView || 'overview');
            this.exportModalFilters.range = this.filters.range;
            this.exportModalFilters.start_date = (this.filters.range === 'custom') ? this.filters.start_date : (this.dateRange.start || '');
            this.exportModalFilters.end_date = (this.filters.range === 'custom') ? this.filters.end_date : (this.dateRange.end || '');
            this.exportModalFilters.class = this.filters.class || 'all';
            this.exportModalFilters.members = this.filters.members || '';

            // If a single website is currently filtered on the dashboard, check if it matches the scope
            if (this.filters.websites && !this.filters.websites.includes(',')) {
                this.exportModalFilters.websites = String(this.filters.websites);
            } else {
                this.exportModalFilters.websites = '';
            }

            // Prefill compare sites from active compare selections if available
            if (this.compareSiteA) {
                this.exportModalSiteA = this.compareSiteA;
            } else if (this.allWebsites && this.allWebsites.length > 0) {
                this.exportModalSiteA = this.allWebsites[0].id;
            }
            if (this.compareSiteB) {
                this.exportModalSiteB = this.compareSiteB;
            } else if (this.allWebsites && this.allWebsites.length > 1) {
                this.exportModalSiteB = this.allWebsites[1].id;
            } else if (this.allWebsites && this.allWebsites.length > 0) {
                this.exportModalSiteB = this.allWebsites[0].id;
            }

            // Prefill sitemap site from active sitemap website selection if available
            if (this.sitemapWebsiteId) {
                this.exportModalSitemapSite = this.sitemapWebsiteId;
            } else if (this.filters.websites && !this.filters.websites.includes(',')) {
                this.exportModalSitemapSite = this.filters.websites;
            } else if (this.allWebsites && this.allWebsites.length > 0) {
                this.exportModalSitemapSite = this.allWebsites[0].id;
            }

            this.exportModalWebsiteSearch = '';
            this.openExportWebsiteDropdown = false;
            this.onExportModalScopeChange();

            this.setExportModalView(this.exportModalView);
            this.showExportModal = true;
        },

        buildExportUrl(type, customFilters = null) {
            const isModal = Boolean(customFilters);
            const f = customFilters || this.filters;
            const viewMode = isModal 
                ? (f.view || this.exportModalView || 'overview') 
                : (this.activeView === 'properties' ? 'overview' : (this.activeView || 'overview'));

            const startDate = (f.range === 'custom' && f.start_date)
                ? f.start_date
                : (this.dateRange.start || '');
            const endDate = (f.range === 'custom' && f.end_date)
                ? f.end_date
                : (this.dateRange.end || '');

            const siteA = isModal ? (f.site_a || this.exportModalSiteA || this.compareSiteA) : this.compareSiteA;
            const siteB = isModal ? (f.site_b || this.exportModalSiteB || this.compareSiteB) : this.compareSiteB;
            const wsId = isModal ? (f.website_id || this.exportModalSitemapSite || this.sitemapWebsiteId) : this.sitemapWebsiteId;

            const params = new URLSearchParams({
                view: viewMode,
                range: f.range || '28d',
                start_date: startDate,
                end_date: endDate,
                websites: f.websites || '',
                members: f.members || '',
                class: (f.class && f.class !== 'all') ? f.class : '',
                report: f.report || (viewMode === 'rankings' ? 'rankings' : (viewMode === 'compare' ? 'compare' : (viewMode === 'sitemaps' ? 'sitemaps' : (this.activeTab || 'growth')))),
            });

            if (viewMode === 'compare') {
                if (siteA) params.set('site_a', siteA);
                if (siteB) params.set('site_b', siteB);
            } else if (viewMode === 'sitemaps') {
                if (wsId) params.set('website_id', wsId);
            }

            if (type === 'pdf') {
                return `{{ route('websites.search-console.export.pdf') }}?${params.toString()}`;
            } else if (type === 'excel') {
                return `{{ route('websites.search-console.export.excel') }}?${params.toString()}`;
            } else {
                return `{{ route('websites.search-console.export.csv') }}?${params.toString()}`;
            }
        },

        triggerModalDownload() {
            const filters = {
                ...this.exportModalFilters,
                view: this.exportModalView,
                site_a: this.exportModalSiteA,
                site_b: this.exportModalSiteB,
                website_id: this.exportModalSitemapSite,
            };
            const url = this.buildExportUrl(this.exportModalFormat, filters);
            window.location.href = url;
            this.showExportModal = false;
        },

        formatNumber(num) {
            if (!num && num !== 0) return '0';
            return num.toLocaleString();
        }
    };
}
</script>
@endsection
