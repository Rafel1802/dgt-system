@extends('layouts.app')

@section('title', 'Popup Ad Analytics')
@section('page_title', 'Analytics: ' . $popupAd->title)

@section('content')
<div class="max-w-7xl mx-auto pb-12">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-900 dark:text-white">Popup Analytics</h1>
            <p class="text-sm font-medium text-slate-500 mt-1">Viewing stats for "{{ $popupAd->title }}"</p>
        </div>
        <a href="{{ route('admin.popup-ads.index') }}" class="btn-secondary flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Ads
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bento-card p-6 flex flex-col justify-center">
            <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-2">Total Users</h3>
            <p class="text-4xl font-black text-slate-900 dark:text-white">{{ $totalUsers }}</p>
        </div>
        <div class="bento-card p-6 flex flex-col justify-center">
            <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-2">Total Views</h3>
            <p class="text-4xl font-black text-indigo-600 dark:text-indigo-400">{{ $seenUsersCount }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ $totalUsers > 0 ? round(($seenUsersCount / $totalUsers) * 100) : 0 }}% of total users</p>
        </div>
        <div class="bento-card p-6 flex flex-col justify-center">
            <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-2">Total Clicks</h3>
            <p class="text-4xl font-black text-emerald-600 dark:text-emerald-400">{{ $clickedUsersCount }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ $seenUsersCount > 0 ? round(($clickedUsersCount / $seenUsersCount) * 100) : 0 }}% click-through rate</p>
        </div>
    </div>

    <div class="bento-card overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">User Interactions</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                        <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">First Seen</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">Clicked?</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($interactions as $interaction)
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
                            <td class="px-6 py-4">
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
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center">
                                <p class="text-slate-500">No users have seen this ad yet.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
