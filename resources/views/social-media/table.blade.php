@extends('layouts.app')

@section('title', 'Socials - ' . $class->name)
@section('back_url', route('social-media.dashboard'))

@section('content')
@php
    $classIcon = trim((string) ($class->getRawOriginal('icon') ?? ''));
    $classInitials = collect(preg_split('/\s+|[._-]+/', $class->name))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->join('') ?: mb_strtoupper(mb_substr($class->name, 0, 2));
    $classIconIsImage = $classIcon !== '' && (
        filter_var($classIcon, FILTER_VALIDATE_URL)
        || str_starts_with($classIcon, '/')
        || str_starts_with($classIcon, 'storage/')
        || preg_match('/\.(jpeg|jpg|png|gif|svg|webp)(\?.*)?$/i', $classIcon)
    );
    $classIconSrc = $classIconIsImage
        ? (filter_var($classIcon, FILTER_VALIDATE_URL) ? $classIcon : asset(ltrim($classIcon, '/')))
        : null;
@endphp

<div class="max-w-7xl mx-auto px-0 sm:px-4 lg:px-8 py-2 sm:py-4 pb-28 md:pb-12">
    {{-- Back breadcrumb & Header --}}
    <div class="mb-6">
        <a href="{{ route('social-media.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 transition-colors mb-3">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            <span>Back to Social Media Classes</span>
        </a>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm overflow-hidden bg-white ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700 p-2">
                    @if($classIconSrc)
                        <img
                            src="{{ $classIconSrc }}"
                            alt="{{ $class->name }}"
                            class="max-h-full max-w-full object-contain rounded-lg"
                            loading="lazy"
                            referrerpolicy="no-referrer"
                            onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');"
                        >
                        <span class="hidden h-full w-full items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-white font-black text-sm">{{ $classInitials }}</span>
                    @elseif($classIcon !== '')
                        <span class="flex h-full w-full items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-white font-bold text-lg">{{ $classIcon }}</span>
                    @else
                        <span class="flex h-full w-full items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-white font-black text-sm">{{ $classInitials }}</span>
                    @endif
                </div>
                <div>
                    <h1 class="page-title text-2xl sm:text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-2.5">
                        <span>{{ $class->name }}</span>
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/40">
                            {{ $items->count() }} {{ Str::plural('Link', $items->count()) }}
                        </span>
                    </h1>
                    <p class="page-subtitle text-slate-500 dark:text-slate-400 mt-0.5 text-xs sm:text-sm font-medium">Social Media Links Directory</p>
                </div>
            </div>

            @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'social_qc', 'boss', 'social_admin']))
            <div>
                <a href="{{ route('social-media.manage') }}" class="btn btn-secondary text-xs sm:text-sm py-2 px-3 active:scale-95 touch-manipulation font-semibold gap-1.5 flex items-center justify-center w-full sm:w-auto">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    <span>Manage Classes</span>
                </a>
            </div>
            @endif
        </div>
    </div>

    @if($items->isEmpty())
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-12 text-center shadow-xs">
            <span class="text-5xl block mb-4">🔗</span>
            <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2">No Socials Found</h3>
            <p class="text-slate-500 dark:text-slate-400">There are currently no active social media links configured for this class.</p>
            @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'social_qc', 'boss', 'social_admin']))
                <a href="{{ route('social-media.manage') }}" class="btn btn-primary mt-6 inline-flex">Configure Links</a>
            @endif
        </div>
    @else
        {{-- ── MOBILE VIEW: Touch-friendly modern cards (md:hidden) ── --}}
        <div class="block md:hidden space-y-3.5">
            @foreach($items as $item)
            <div class="rounded-2xl border border-slate-200/90 dark:border-slate-700/80 bg-white dark:bg-slate-800 p-4 shadow-xs" x-data="{ copied: false }">
                {{-- Platform Header --}}
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-slate-100 dark:bg-slate-700 text-lg shadow-xs">
                            {!! $item->icon_html !!}
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-extrabold text-base text-slate-800 dark:text-white truncate">{{ $item->name }}</h3>
                            <p class="text-[11px] font-semibold text-slate-400">Social Channel</p>
                        </div>
                    </div>
                    @if($item->url)
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex-shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Active
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide bg-slate-100 dark:bg-slate-700 text-slate-400 flex-shrink-0">
                            Empty
                        </span>
                    @endif
                </div>

                {{-- URL Display Box --}}
                @if($item->url)
                    <div class="mt-3.5 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-700/80 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                        <span class="truncate text-xs font-mono font-semibold text-indigo-600 dark:text-indigo-400 flex-1 min-w-0 select-all" title="{{ $item->url }}">
                            {{ $item->url }}
                        </span>
                    </div>

                    {{-- Actions Bar --}}
                    <div class="grid grid-cols-2 gap-2 mt-3 pt-1 border-t border-slate-100 dark:border-slate-700/50">
                        <button type="button" 
                                @click="navigator.clipboard.writeText('{{ $item->url }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })" 
                                class="btn btn-secondary flex items-center justify-center gap-1.5 py-2.5 px-3 text-xs font-bold active:scale-95 touch-manipulation w-full"
                                :class="{ 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800': copied }">
                            <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a2.25 2.25 0 0 1-2.25 2.25H10.5a2.25 2.25 0 0 1-2.25-2.25v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                            </svg>
                            <svg x-show="copied" x-cloak class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            <span x-text="copied ? 'Copied!' : 'Copy Link'"></span>
                        </button>

                        <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer" 
                           class="btn btn-primary flex items-center justify-center gap-1.5 py-2.5 px-3 text-xs font-bold active:scale-95 touch-manipulation w-full">
                            <span>Open Link</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                            </svg>
                        </a>
                    </div>
                @else
                    <div class="mt-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-dashed border-slate-200 dark:border-slate-700 text-center">
                        <span class="text-xs text-slate-400 font-medium italic">No URL configured for this platform</span>
                    </div>
                @endif
            </div>
            @endforeach
        </div>

        {{-- ── DESKTOP & TABLET VIEW: Crisp Data Table (hidden md:block) ── --}}
        <div class="hidden md:block bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[680px]">
                    <thead>
                        <tr class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                            <th class="py-3.5 px-5 text-xs font-bold text-slate-500 uppercase tracking-wider w-56">Social Platform</th>
                            <th class="py-3.5 px-5 text-xs font-bold text-slate-500 uppercase tracking-wider">URL Link</th>
                            <th class="py-3.5 px-5 text-xs font-bold text-slate-500 uppercase tracking-wider text-right w-64">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($items as $item)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/60 transition-colors">
                            <td class="py-4 px-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-slate-100 dark:bg-slate-700 text-lg shadow-xs">
                                        {!! $item->icon_html !!}
                                    </div>
                                    <div class="font-bold text-slate-800 dark:text-white whitespace-nowrap">
                                        {{ $item->name }}
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-5">
                                @if($item->url)
                                    <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer" 
                                       class="text-sm font-medium font-mono text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 hover:underline max-w-md truncate block" 
                                       title="{{ $item->url }}">
                                        {{ $item->url }}
                                    </a>
                                @else
                                    <span class="text-xs text-slate-400 italic">No URL provided</span>
                                @endif
                            </td>
                            <td class="py-4 px-5 text-right">
                                @if($item->url)
                                    <div class="flex items-center justify-end gap-2" x-data="{ copied: false }">
                                        <button type="button" 
                                                @click="navigator.clipboard.writeText('{{ $item->url }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })" 
                                                class="btn btn-secondary text-xs font-semibold py-2 px-3 inline-flex items-center gap-1.5 active:scale-95 touch-manipulation min-w-[90px] justify-center"
                                                :class="{ '!bg-emerald-50 !text-emerald-700 !border-emerald-200 dark:!bg-emerald-950/40 dark:!text-emerald-400 dark:!border-emerald-800': copied }">
                                            <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a2.25 2.25 0 0 1-2.25 2.25H10.5a2.25 2.25 0 0 1-2.25-2.25v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                            <svg x-show="copied" x-cloak class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                            <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                                        </button>
                                        <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer" 
                                           class="btn btn-primary text-xs font-semibold py-2 px-3 inline-flex items-center gap-1.5 active:scale-95 touch-manipulation">
                                            <span>Open Link</span>
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                            </svg>
                                        </a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
