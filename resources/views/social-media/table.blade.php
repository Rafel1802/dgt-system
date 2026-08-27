@extends('layouts.app')

@section('title', 'Socials - ' . $class->name)
@section('back_url', route('social-media.dashboard'))

@section('content')
<div class="page-header flex flex-wrap gap-4 items-center justify-between mb-6">
    <div>
        <h1 class="page-title flex items-center gap-2">
            {{ $class->name }}
        </h1>
        <p class="page-subtitle">Social Media Links Directory</p>
    </div>
</div>

<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    @if($items->isEmpty())
        <div class="p-12 text-center">
            <span class="text-5xl block mb-4">🔗</span>
            <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2">No Socials Found</h3>
            <p class="text-slate-500">There are currently no active social media links for this class.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                        <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider w-1/4">Social Platform</th>
                        <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider">URL Link</th>
                        <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right w-1/4">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @foreach($items as $item)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-slate-100 dark:bg-slate-700 text-lg">
                                    {!! $item->icon_html !!}
                                </div>
                                <div class="font-bold text-slate-800 dark:text-white whitespace-nowrap">
                                    {{ $item->name }}
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            @if($item->url)
                                <a href="{{ $item->url }}" target="_blank" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 break-all hover:underline" title="{{ $item->url }}">
                                    {{ $item->url }}
                                </a>
                            @else
                                <span class="text-xs text-slate-400 italic">No URL provided</span>
                            @endif
                        </td>
                        <td class="py-4 px-4 text-right">
                            @if($item->url)
                                <div class="flex items-center justify-end gap-2">
                                    <button x-data="{ copied: false }" 
                                            @click="navigator.clipboard.writeText('{{ $item->url }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })" 
                                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-lg text-sm font-semibold transition-colors dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600 w-24">
                                        <svg x-show="!copied" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a2.25 2.25 0 0 1-2.25 2.25H10.5a2.25 2.25 0 0 1-2.25-2.25v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                                        <svg x-show="copied" x-cloak class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                        <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                                    </button>
                                    <a href="{{ $item->url }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white rounded-lg text-sm font-semibold transition-colors">
                                        Open Link
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                    </a>
                                </div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
