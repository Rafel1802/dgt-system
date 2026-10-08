@extends('layouts.app')

@section('title', 'Popup Ad Analytics')
@section('page_title', 'Analytics: ' . $popupAd->title)
@section('back_url', route('admin.popup-ads.index'))

@section('content')
<div class="max-w-7xl mx-auto px-0 sm:px-4 lg:px-8 py-2 sm:py-4 pb-28 md:pb-12">
    <div class="mb-6">
        <a href="{{ route('admin.popup-ads.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 transition-colors mb-3">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            <span>Back to Popup Ads</span>
        </a>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">Popup Analytics</h1>
                <p class="text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">Viewing stats for "{{ $popupAd->title }}"</p>
            </div>
            <a href="{{ route('admin.popup-ads.edit', $popupAd) }}" class="btn btn-secondary inline-flex items-center justify-center gap-2 text-xs sm:text-sm font-bold py-2 px-3 active:scale-95 touch-manipulation w-full sm:w-auto">
                <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                <span>Edit Ad</span>
            </a>
        </div>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-8">
        <div class="bento-card p-5 sm:p-6 flex flex-col justify-center rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xs">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Total Users</h3>
            <p class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white">{{ $totalUsers }}</p>
        </div>
        <div class="bento-card p-5 sm:p-6 flex flex-col justify-center rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xs">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Total Views</h3>
            <p class="text-3xl sm:text-4xl font-black text-indigo-600 dark:text-indigo-400">{{ $seenUsersCount }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ $totalUsers > 0 ? round(($seenUsersCount / $totalUsers) * 100) : 0 }}% of total users</p>
        </div>
        <div class="bento-card p-5 sm:p-6 flex flex-col justify-center rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xs">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Total Clicks</h3>
            <p class="text-3xl sm:text-4xl font-black text-emerald-600 dark:text-emerald-400">{{ $clickedUsersCount }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ $seenUsersCount > 0 ? round(($clickedUsersCount / $seenUsersCount) * 100) : 0 }}% click-through rate</p>
        </div>
    </div>

    {{-- Interactions Section --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">User Interactions</h2>
            <span class="text-xs font-bold text-slate-400">{{ $interactions->count() }} records</span>
        </div>

        @if($interactions->isEmpty())
            <div class="bento-card p-12 text-center rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xs">
                <p class="text-slate-500 text-sm">No users have seen this ad yet.</p>
            </div>
        @else
            {{-- Mobile Cards --}}
            <div class="block md:hidden space-y-3">
                @foreach($interactions as $interaction)
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xs space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-700 dark:text-slate-200 flex-shrink-0">
                                    {{ substr($interaction->name, 0, 1) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-slate-900 dark:text-white text-sm truncate">{{ $interaction->name }}</p>
                                    <p class="text-xs text-slate-500 truncate">{{ $interaction->email }}</p>
                                </div>
                            </div>
                            @if($interaction->is_clicked)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400 flex-shrink-0">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    Clicked
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 flex-shrink-0">
                                    Seen Only
                                </span>
                            @endif
                        </div>
                        <div class="text-[11px] text-slate-400 font-medium pt-1 border-t border-slate-100 dark:border-slate-700/60">
                            First seen: {{ \Carbon\Carbon::parse($interaction->last_shown_at)->format('M d, Y h:i A') }}
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Desktop Table --}}
            <div class="hidden md:block bento-card rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead>
                            <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                                <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">First Seen</th>
                                <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider text-right">Clicked?</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            @foreach($interactions as $interaction)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-600 dark:text-slate-300">
                                                {{ substr($interaction->name, 0, 1) }}
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-900 dark:text-white text-sm">{{ $interaction->name }}</p>
                                                <p class="text-xs text-slate-500">{{ $interaction->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                                        {{ \Carbon\Carbon::parse($interaction->last_shown_at)->format('M d, Y h:i A') }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        @if($interaction->is_clicked)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                Clicked
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                                Seen Only
                                            </span>
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
</div>
@endsection
