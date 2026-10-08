@extends('layouts.app')

@section('title', 'Popup Ads Management')
@section('page_title', 'Popup Ads')

@section('content')
<div class="max-w-7xl mx-auto px-0 sm:px-4 lg:px-8 py-2 sm:py-4 pb-28 md:pb-12">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 sm:mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">Popup Ads</h1>
            <p class="text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">Manage notification popup ads shown to users.</p>
        </div>
        <a href="{{ route('admin.popup-ads.create') }}" class="btn btn-primary inline-flex items-center justify-center gap-2 text-sm font-bold py-2.5 px-4 rounded-xl active:scale-95 touch-manipulation w-full sm:w-auto shadow-md shadow-indigo-500/20">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            <span>Create New Ad</span>
        </a>
    </div>

    @if($ads->isEmpty())
        <div class="bento-card p-10 sm:p-14 text-center rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xs">
            <div class="w-16 h-16 bg-slate-100 dark:bg-slate-700/60 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-200 mb-1">No Popup Ads Yet</h3>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-sm mx-auto mb-6">Create your first popup ad to start broadcasting alerts and reminders to users.</p>
            <a href="{{ route('admin.popup-ads.create') }}" class="btn btn-primary inline-flex">
                Create New Ad
            </a>
        </div>
    @else
        {{-- ── MOBILE VIEW: Touch-Friendly Cards (md:hidden) ── --}}
        <div class="block md:hidden space-y-3.5">
            @foreach($ads as $ad)
                <div class="rounded-2xl border border-slate-200/90 dark:border-slate-700/80 bg-white dark:bg-slate-800 p-4 shadow-xs space-y-3.5">
                    <div class="flex items-start gap-3">
                        <div class="w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-500 dark:text-indigo-400 flex-shrink-0 overflow-hidden shadow-xs ring-1 ring-slate-100 dark:ring-slate-700/50">
                            @if($ad->image_path)
                                <img src="{{ Storage::url($ad->image_path) }}" alt="{{ $ad->title }}" class="w-full h-full object-cover">
                            @else
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white leading-snug break-words">
                                {{ $ad->title }}
                            </h3>
                            <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1.5 flex-wrap">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                @if($ad->start_time || $ad->end_time)
                                    <span>{{ $ad->start_time ? $ad->start_time->format('M d, Y') : 'Anytime' }} – {{ $ad->end_time ? $ad->end_time->format('M d, Y') : 'Forever' }}</span>
                                @else
                                    <span>Runs continuously</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Interval & Status Toggle Row --}}
                    <div class="flex items-center justify-between gap-2 pt-2.5 border-t border-slate-100 dark:border-slate-700/60">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Every {{ $ad->interval_minutes }} min
                        </span>

                        <form action="{{ route('admin.popup-ads.toggle-active', $ad) }}" method="POST" x-data x-ref="mobileForm" class="inline-flex items-center">
                            @csrf
                            @method('PATCH')
                            <label class="flex items-center cursor-pointer group select-none">
                                <input type="checkbox" name="is_active" class="sr-only peer" {{ $ad->is_active ? 'checked' : '' }} @change="$refs.mobileForm.submit()">
                                <div class="w-10 h-5 rounded-full transition-colors relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all after:shadow-sm peer-checked:after:translate-x-5 peer-checked:bg-emerald-500 bg-slate-200 dark:bg-slate-700"></div>
                                <span class="ml-2 text-xs font-extrabold uppercase tracking-wider {{ $ad->is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    {{ $ad->is_active ? 'Active' : 'Off' }}
                                </span>
                            </label>
                        </form>
                    </div>

                    {{-- Action Buttons Row --}}
                    <div class="grid grid-cols-3 gap-2 pt-2.5 border-t border-slate-100 dark:border-slate-700/60">
                        <a href="{{ route('admin.popup-ads.show', $ad) }}" class="btn btn-secondary flex items-center justify-center gap-1.5 py-2 px-2 text-xs font-bold active:scale-95 touch-manipulation">
                            <svg class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                            <span>Stats</span>
                        </a>
                        <a href="{{ route('admin.popup-ads.edit', $ad) }}" class="btn btn-secondary flex items-center justify-center gap-1.5 py-2 px-2 text-xs font-bold active:scale-95 touch-manipulation">
                            <svg class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            <span>Edit</span>
                        </a>
                        <form action="{{ route('admin.popup-ads.destroy', $ad) }}" method="POST" onsubmit="return confirm('Delete this popup ad?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200/60 dark:border-rose-900/40 flex items-center justify-center gap-1.5 py-2 px-2 text-xs font-bold active:scale-95 touch-manipulation w-full">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                <span>Delete</span>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ── DESKTOP VIEW: Data Table (hidden md:block) ── --}}
        <div class="hidden md:block bento-card rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[720px]">
                    <thead>
                        <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                            <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">Ad Title</th>
                            <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider w-40">Interval</th>
                            <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider w-36">Status</th>
                            <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider text-right w-44">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($ads as $ad)
                            <tr class="group hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-all duration-200">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-4">
                                        <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-500 dark:text-indigo-400 overflow-hidden ring-1 ring-slate-100 dark:ring-slate-700/50">
                                            @if($ad->image_path)
                                                <img src="{{ Storage::url($ad->image_path) }}" alt="{{ $ad->title }}" class="w-12 h-12 rounded-xl object-cover">
                                            @else
                                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors line-clamp-2">{{ $ad->title }}</p>
                                            <p class="text-xs text-slate-500 mt-1 flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                @if($ad->start_time || $ad->end_time)
                                                    <span>{{ $ad->start_time ? $ad->start_time->format('M d, Y') : 'Anytime' }} - {{ $ad->end_time ? $ad->end_time->format('M d, Y') : 'Forever' }}</span>
                                                @else
                                                    <span>Runs continuously</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 whitespace-nowrap">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Every {{ $ad->interval_minutes }} min
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <form action="{{ route('admin.popup-ads.toggle-active', $ad) }}" method="POST" x-data x-ref="form" class="inline-block relative top-0.5">
                                        @csrf
                                        @method('PATCH')
                                        <label class="flex items-center cursor-pointer group select-none">
                                            <input type="checkbox" name="is_active" class="sr-only peer" {{ $ad->is_active ? 'checked' : '' }} @change="$refs.form.submit()">
                                            <div class="w-11 h-6 rounded-full transition-colors relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all after:shadow-sm peer-checked:after:translate-x-full peer-checked:bg-emerald-500 bg-slate-200 dark:bg-slate-700 peer-focus:ring-2 peer-focus:ring-offset-2 peer-focus:ring-emerald-400"></div>
                                            <span class="ml-3 text-xs font-bold uppercase tracking-wider {{ $ad->is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500' }}">
                                                {{ $ad->is_active ? 'Active' : 'Off' }}
                                            </span>
                                        </label>
                                    </form>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.popup-ads.show', $ad) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-teal-50 text-teal-600 hover:bg-teal-100 dark:bg-teal-500/10 dark:text-teal-400 dark:hover:bg-teal-500/20 transition-colors" title="Stats & Analytics">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                        </a>
                                        <a href="{{ route('admin.popup-ads.edit', $ad) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-400 dark:hover:bg-indigo-500/20 transition-colors" title="Edit">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </a>
                                        <form action="{{ route('admin.popup-ads.destroy', $ad) }}" method="POST" class="inline-block" onsubmit="return confirm('Delete this popup ad?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500/20 transition-colors" title="Delete">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($ads->hasPages())
            <div class="mt-4">
                {{ $ads->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
