@extends('layouts.app')

@section('title', 'Production Media & Attachments')

@section('content')
<div class="pr-root pb-6"
     x-data="{ lightboxUrl: null, lightboxTitle: '' }">
    @include('production-reports.partials.nav')

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-5">
            <div>
                <h2 class="text-lg font-extrabold text-white">Posters, Graphics, Videos &amp; Attachments Gallery</h2>
                <p class="text-xs text-slate-400">All media files are stored in private storage and verified against your team permissions.</p>
            </div>

            <form method="GET" action="{{ route('production-reports.media') }}" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search title or project..."
                       class="bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-3 py-1.5">
                <select name="media_type" class="bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5">
                    <option value="">All Media Types</option>
                    <option value="poster" {{ request('media_type') === 'poster' ? 'selected' : '' }}>Posters</option>
                    <option value="graphic" {{ request('media_type') === 'graphic' ? 'selected' : '' }}>Graphics</option>
                    <option value="video" {{ request('media_type') === 'video' ? 'selected' : '' }}>Videos</option>
                    <option value="document" {{ request('media_type') === 'document' ? 'selected' : '' }}>Documents / PDFs</option>
                </select>
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-300">
                    <input type="checkbox" name="highlighted_only" value="1" {{ request()->boolean('highlighted_only') ? 'checked' : '' }}>
                    <span>★ Highlights Only</span>
                </label>
                <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-bold">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto border border-slate-800 rounded-xl">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-800 text-slate-400 uppercase text-[11px]">
                    <tr>
                        <th class="py-2.5 px-3">Preview</th>
                        <th class="py-2.5 px-3">Title</th>
                        <th class="py-2.5 px-3">Type</th>
                        <th class="py-2.5 px-3">Creator</th>
                        <th class="py-2.5 px-3">Date</th>
                        <th class="py-2.5 px-3">Project</th>
                        <th class="py-2.5 px-3 text-right">File</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($mediaItems as $media)
                        <tr>
                            <td class="py-2.5 px-3">
                                @if($media->isImage() && $media->file_path)
                                    <button type="button"
                                            @click="lightboxUrl = '{{ route('production-reports.media.view', $media->id) }}'; lightboxTitle = '{{ addslashes($media->title) }}'"
                                            class="block w-16 h-11 rounded overflow-hidden border border-slate-700">
                                        <img src="{{ route('production-reports.media.view', $media->id) }}" class="w-full h-full object-cover" alt="{{ $media->title }}">
                                    </button>
                                @elseif($media->isVideo() && $media->file_path)
                                    <video src="{{ route('production-reports.media.view', $media->id) }}" class="w-24 h-14 rounded bg-black" controls preload="metadata"></video>
                                @else
                                    <span class="px-2 py-1 rounded bg-slate-800 text-[10px] font-bold uppercase">{{ $media->platform ?: $media->media_type }}</span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="font-bold text-white">
                                    {{ $media->title }}
                                    @if($media->is_highlighted)
                                        <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 text-[10px] font-bold">★ Highlight</span>
                                    @endif
                                </div>
                                @if($media->report)
                                    <a href="{{ route('production-reports.show', $media->report->id) }}" class="text-[11px] text-indigo-400 hover:underline">
                                        {{ $media->report->report_code }}
                                    </a>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 uppercase font-semibold">{{ $media->media_type }}</td>
                            <td class="py-2.5 px-3">{{ $media->creator?->name ?? $media->uploader?->name ?? 'N/A' }}</td>
                            <td class="py-2.5 px-3">{{ $media->production_date?->format('M d, Y') ?? '—' }}</td>
                            <td class="py-2.5 px-3">{{ $media->project_category ?: 'General' }}</td>
                            <td class="py-2.5 px-3 text-right space-x-2 whitespace-nowrap">
                                @if($media->external_url)
                                    <a href="{{ $media->external_url }}" target="_blank" class="text-indigo-400 font-bold">Open Link &nearr;</a>
                                @endif
                                @if($media->file_path)
                                    <a href="{{ route('production-reports.media.view', $media->id) }}" target="_blank" class="text-sky-400 font-bold">Preview</a>
                                    <a href="{{ route('production-reports.media.download', $media->id) }}" class="text-emerald-400 font-bold">Download ({{ $media->formatted_size }})</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">No media items found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $mediaItems->links() }}
        </div>
    </div>

    {{-- Image Lightbox --}}
    <div x-show="lightboxUrl" x-cloak @click.self="lightboxUrl = null"
         class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl max-w-4xl w-full p-4">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-bold text-white" x-text="lightboxTitle"></h4>
                <button type="button" @click="lightboxUrl = null" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
            </div>
            <img :src="lightboxUrl" class="max-h-[75vh] mx-auto rounded-lg object-contain" alt="Preview">
        </div>
    </div>
</div>
@endsection

