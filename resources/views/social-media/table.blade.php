@extends('layouts.app')

@section('title', 'Class Table - ' . $class->name)
@section('back_url', route('social-media.dashboard'))

@section('content')
<style>
/* Google Sheets-like styling */
.gs-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8125rem; }
.gs-table th, .gs-table td { 
    border-right: 1px solid var(--border-color, #e2e8f0); 
    border-bottom: 1px solid var(--border-color, #e2e8f0); 
    padding: 0.5rem 0.75rem; 
    vertical-align: middle; 
}
.gs-table th { 
    background: var(--bg-page, #f8fafc); 
    font-weight: 700; color: var(--text-secondary, #475569); 
    text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.7rem; 
    border-top: 1px solid var(--border-color, #e2e8f0); 
    position: sticky; top: 0; z-index: 10;
}
.gs-table tr td:first-child, .gs-table tr th:first-child { border-left: 1px solid var(--border-color, #e2e8f0); }
.gs-table tbody tr:hover { background: #f8fafc; }
[data-theme="dark"] .gs-table th { background: #1e293b; color: #cbd5e1; border-color: #334155; }
[data-theme="dark"] .gs-table td { border-color: #334155; }
[data-theme="dark"] .gs-table tbody tr:hover { background: #1e293b; }

.gs-input { 
    width: 100%; border: 1px solid transparent; background: transparent; 
    padding: 0.25rem 0.5rem; border-radius: 4px; outline: none; transition: all 0.2s;
    color: inherit;
}
.gs-input:hover { border-color: #cbd5e1; }
.gs-input:focus { border-color: #818cf8; background: #fff; box-shadow: 0 0 0 2px rgba(99,102,241,0.2); }
[data-theme="dark"] .gs-input:focus { background: #0f172a; border-color: #6366f1; }

.gs-checkbox { 
    width: 1.25rem; height: 1.25rem; border-radius: 4px; cursor: pointer;
    border: 2px solid #cbd5e1; display: inline-flex; align-items: center; justify-content: center;
    transition: all 0.2s; background: white;
}
.gs-checkbox:hover { border-color: #94a3b8; }
.gs-checkbox.checked { background: #10b981; border-color: #10b981; }
.gs-checkbox.checked::after { content: '✓'; color: white; font-weight: bold; font-size: 0.85rem; }
.gs-checkbox:disabled, .gs-checkbox.disabled { cursor: not-allowed; }
.gs-checkbox:disabled:not(.checked), .gs-checkbox.disabled:not(.checked) { opacity: 0.5; background: #f1f5f9; border-color: #e2e8f0; }

.gs-badge { display: inline-flex; align-items: center; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; }
.gs-badge-checked { background: #dbeafe; color: #1d4ed8; }
.gs-badge-pending { background: #ffedd5; color: #c2410c; }
.gs-badge-na { background: #f1f5f9; color: #64748b; }
</style>

<div x-data="spreadsheet()">
<div class="page-header flex flex-wrap gap-4 items-center justify-between mb-6">
    <div>
        <h1 class="page-title flex items-center gap-2">
            {{ $class->name }}
        </h1>
        <p class="page-subtitle">Social Media Posting Table</p>
    </div>
    
    {{-- Top Controls: Date and User filter --}}
    <div class="flex items-center gap-3 bg-white dark:bg-slate-800 p-2 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="flex items-center -space-x-2 mr-1 pl-2">
            @foreach($class->assignedUsers->take(5) as $u)
                <img src="{{ $u->avatar_url }}" alt="{{ $u->name }}" title="{{ $u->name }}" 
                     class="w-7 h-7 rounded-full border-2 border-white dark:border-slate-800 shadow-sm object-cover cursor-pointer hover:scale-110 transition-transform"
                     @click="openPhotoViewer('{{ $u->avatar_url }}')" 
                     @dblclick="openPhotoViewer('{{ $u->avatar_url }}')">
            @endforeach
            @if($class->assignedUsers->count() > 5)
                <div class="w-7 h-7 rounded-full border-2 border-white dark:border-slate-800 bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-[9px] font-bold text-slate-600 dark:text-slate-300 shadow-sm">
                    +{{ $class->assignedUsers->count() - 5 }}
                </div>
            @endif
        </div>
        @if($isQc)
            <div class="w-px h-6 bg-slate-200 dark:bg-slate-700"></div>
        @endif
        <form method="GET" action="{{ route('social-media.class.show', $class) }}" class="flex items-center gap-3">
            @if($isQc)
                <select name="user_id" class="form-select py-1.5 text-sm border-none bg-slate-50 dark:bg-slate-900 rounded-lg" onchange="this.form.submit()">
                    <option value="">All Users</option>
                    @foreach($assignedUsers as $u)
                        <option value="{{ $u->id }}" {{ $viewUserId == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
                <div class="w-px h-6 bg-slate-200 dark:bg-slate-700"></div>
            @endif
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wide px-2">Date</span>
                <input type="date" name="date" value="{{ $postDate }}" class="form-input py-1.5 text-sm border-none bg-slate-50 dark:bg-slate-900 rounded-lg font-bold" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm overflow-hidden border border-slate-200 dark:border-slate-700">
    <div class="overflow-x-auto max-h-[70vh] relative">
        <table class="gs-table">
            <thead>
                <tr>
                    <th class="w-48 text-left">Socials</th>
                    <th class="w-48 text-left">Optional</th>
                    <th class="min-w-[300px] text-left">Post Link</th>
                    <th class="w-24 text-center">Complete</th>
                    <th class="w-32 text-center">QC Status</th>
                    @if($isQc)
                        <th class="w-24 text-center">QC Tick</th>
                        <th class="w-32 text-left">Submitted By</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    @php
                        $postKey = $viewUserId ? ($item->id . '_' . $viewUserId) : $item->id;
                        $post = $posts->get($postKey);
                        
                        $isCompleted = $post ? $post->is_completed : false;
                        $isChecked   = $post ? $post->is_checked : false;
                        $postUrl     = $post ? $post->post_url : '';
                        
                        // Edit rules
                        // Normal user cannot edit if checked.
                        $canEdit = true;
                        if ($isChecked && !$isAdmin) $canEdit = false;
                        
                        // If viewing "All Users" (viewUserId is null), user cannot edit, it's just a view.
                        // Actually, to make it simple, if viewUserId is null, we shouldn't show input fields, 
                        // but the controller handles viewUserId=null by aggregating. Wait, the controller keyBy uses user_id.
                        // If viewUserId is null, multiple users might have posts for this item. 
                        // The table logic requires a specific user to edit.
                        $isViewOnly = is_null($viewUserId);
                    @endphp
                    <tr id="row-{{ $item->id }}" data-item-id="{{ $item->id }}">
                        {{-- 1. Socials --}}
                        <td class="font-bold text-slate-700 dark:text-slate-300">
                            <div class="flex items-center">
                                <span class="mr-2 inline-flex items-center justify-center">{!! $item->icon_html !!}</span>
                                <span>{{ $item->name }}</span>
                            </div>
                        </td>
                        
                        {{-- 2. Optional --}}
                        <td class="">
                            @if($isViewOnly)
                                <span class="text-slate-500 font-medium">{{ $post ? $post->optional_text : '' }}</span>
                            @else
                                <input type="text" id="optional-{{ $item->id }}" value="{{ $post ? $post->optional_text : '' }}" 
                                       class="gs-input text-xs font-medium text-slate-600 w-full" 
                                       placeholder="Optional text..."
                                       @change="upsertPost({{ $item->id }}, '{{ $postDate }}', document.getElementById('url-{{ $item->id }}').value, $el.value)"
                                       {{ !$canEdit ? 'disabled' : '' }}>
                            @endif
                        </td>
                        
                        {{-- 3. Links --}}
                        <td>
                            @if($isViewOnly)
                                @if($post)
                                    <a href="{{ $postUrl }}" target="_blank" class="text-indigo-600 hover:underline">{{ $postUrl }}</a>
                                @else
                                    <span class="text-slate-300 italic">No link</span>
                                @endif
                            @else
                                <div class="relative flex items-center">
                                    <input type="url" id="url-{{ $item->id }}" value="{{ $postUrl }}" 
                                           class="gs-input pr-12" placeholder="https://..."
                                           @change="upsertPost({{ $item->id }}, '{{ $postDate }}', $el.value, document.getElementById('optional-{{ $item->id }}').value)"
                                           {{ !$canEdit ? 'disabled' : '' }}>
                                    @if($postUrl)
                                        <div class="absolute right-1 flex items-center bg-white dark:bg-slate-800 px-1">
                                            <span class="text-slate-300 mr-1 select-none font-light">|</span>
                                            <a href="{{ $postUrl }}" target="_blank" class="text-slate-400 hover:text-indigo-600" title="Open Link">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </td>

                        {{-- 4. Complete Checkbox --}}
                        <td class="text-center">
                            @if($isViewOnly)
                                @if($isCompleted) <span class="text-emerald-500 font-bold">✓</span> @else <span class="text-slate-300">—</span> @endif
                            @else
                                <div class="gs-checkbox {{ $isCompleted ? 'checked' : '' }} {{ !$canEdit ? 'disabled' : '' }}"
                                     id="complete-{{ $item->id }}"
                                     @click="toggleComplete({{ $item->id }}, {{ $post ? $post->id : 'null' }})">
                                </div>
                            @endif
                        </td>

                        {{-- 5. QC Status Badge --}}
                        <td class="text-center" id="qc-status-{{ $item->id }}">
                            @if($isChecked)
                                <span class="gs-badge gs-badge-checked">Checked ✓</span>
                            @elseif($isCompleted)
                                <span class="gs-badge gs-badge-pending">QC Pending</span>
                            @else
                                <span class="gs-badge gs-badge-na">—</span>
                            @endif
                        </td>

                        {{-- Admin columns --}}
                        @if($isQc)
                            {{-- 6. QC Tick (Admin only) --}}
                            <td class="text-center relative">
                                @if($post && $isCompleted)
                                    <div class="flex flex-col items-center justify-center gap-1 py-1">
                                        <div class="flex items-center justify-center">
                                            @if($canQc ?? false)
                                                <div class="gs-checkbox {{ $isChecked ? 'checked' : '' }}"
                                                     id="qc-check-{{ $item->id }}"
                                                     @click="toggleQc({{ $item->id }}, {{ $post->id }})">
                                                </div>
                                            @else
                                                <div class="gs-checkbox disabled {{ $isChecked ? 'checked' : '' }}"
                                                     id="qc-check-{{ $item->id }}"
                                                     title="Only QC can check this">
                                                </div>
                                            @endif
                                        </div>
                                        @if($isChecked && $post->checker)
                                            <div class="flex items-center gap-1" title="QC: {{ $post->checker->name }}">
                                                <span class="text-[9px] font-bold text-indigo-600 truncate max-w-[80px]">{{ $post->checker->name }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            
                            {{-- 7. Submitted By --}}
                            <td class="text-xs text-slate-500">
                                @if($post && $post->user)
                                    <div class="font-bold text-slate-700 dark:text-slate-300">{{ $post->user->name }}</div>
                                    @if($post->completed_at) <div class="text-[9px]">{{ $post->completed_at->format('d M H:i') }}</div> @endif
                                @else
                                    —
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Notification Toast --}}
<div id="toast" class="fixed bottom-4 right-4 bg-slate-800 text-white px-4 py-2 rounded-lg shadow-xl opacity-0 transition-opacity duration-300 pointer-events-none z-50 text-sm font-medium">
    Saved
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('spreadsheet', () => ({
        classId: {{ $class->id }},
        viewerOpen: false,
        viewerUrl: '',
        
        openPhotoViewer(url) {
            this.viewerUrl = url;
            this.viewerOpen = true;
        },
        
        async upsertPost(itemId, postDate, postUrl, optionalText = '') {
            if(!postUrl && !optionalText) return; // Don't upsert empty fields automatically

            const targetUserId = {{ $viewUserId ?? 'null' }};

            try {
                const res = await fetch('{{ route('social-media.posts.upsert') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        social_media_class_id: this.classId,
                        social_media_item_id: itemId,
                        post_date: postDate,
                        post_url: postUrl,
                        optional_text: optionalText,
                        target_user_id: targetUserId
                    })
                });
                
                const data = await res.json();
                if(data.error) { window.showToast(data.error, 'error'); return; }
                
                // Update complete button binding
                const btn = document.getElementById(`complete-${itemId}`);
                if(btn) {
                    btn.setAttribute('@click', `toggleComplete(${itemId}, ${data.post_id})`);
                }
                this.showToast('Link saved');
            } catch(e) {
                console.error(e);
            }
        },

        async toggleComplete(itemId, postId) {
            const urlInput = document.getElementById(`url-${itemId}`);
            if(!urlInput.value) {
                window.showToast('Please enter a post link first.', 'error');
                return;
            }

            // Need to upsert first if no post ID exists yet
            if(!postId) {
                window.showToast('Saving link first...', 'info');
                return; // Let the change event trigger first
            }

            const btn = document.getElementById(`complete-${itemId}`);
            if(btn.classList.contains('disabled')) return;

            const isCurrentlyCompleted = btn.classList.contains('checked');
            const targetState = !isCurrentlyCompleted;

            try {
                const res = await fetch(`/social-media/posts/${postId}/complete`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ is_completed: targetState })
                });

                const data = await res.json();
                if(data.error) { window.showToast(data.error, 'error'); return; }

                if(data.is_completed) {
                    btn.classList.add('checked');
                    document.getElementById(`qc-status-${itemId}`).innerHTML = '<span class="gs-badge gs-badge-pending">QC Pending</span>';
                } else {
                    btn.classList.remove('checked');
                    document.getElementById(`qc-status-${itemId}`).innerHTML = '<span class="gs-badge gs-badge-na">—</span>';
                }
                
                // Reload to show admin QC columns if needed
                if(targetState) window.location.reload(); 
            } catch(e) {
                console.error(e);
            }
        },

        async toggleQc(itemId, postId) {
            const btn = document.getElementById(`qc-check-${itemId}`);
            const isCurrentlyChecked = btn.classList.contains('checked');
            const targetState = !isCurrentlyChecked;

            try {
                const res = await fetch(`/social-media/posts/${postId}/check`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ is_checked: targetState })
                });

                const data = await res.json();
                if(data.error) { window.showToast(data.error, 'error'); return; }

                if(data.is_checked) {
                    btn.classList.add('checked');
                    document.getElementById(`qc-status-${itemId}`).innerHTML = `<span class="gs-badge gs-badge-checked">Checked ✓</span>`;
                } else {
                    btn.classList.remove('checked');
                    document.getElementById(`qc-status-${itemId}`).innerHTML = `<span class="gs-badge gs-badge-pending">QC Pending</span>`;
                }
                
                // Reload page to lock inputs
                window.location.reload();
            } catch(e) {
                console.error(e);
            }
        },

        showToast(msg) {
            const toast = document.getElementById('toast');
            toast.innerText = msg;
            toast.style.opacity = '1';
            setTimeout(() => { toast.style.opacity = '0'; }, 2000);
        }
    }));
});
</script>

{{-- Simple Photo Viewer Modal --}}
<div class="fixed inset-0 z-[9999] bg-black/95 flex items-center justify-center backdrop-blur-sm"
     x-show="viewerOpen"
     x-transition.opacity
     @click="viewerOpen = false"
     @keydown.escape.window="viewerOpen = false"
     style="display: none;">
    <button type="button" class="absolute top-6 right-6 text-white hover:text-gray-300 bg-white/10 p-2 rounded-full border border-white/20 transition-transform hover:scale-110" @click.stop="viewerOpen = false">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <img :src="viewerUrl" class="w-auto h-[85vh] max-w-[95vw] rounded-2xl object-contain shadow-2xl" @click.stop>
</div>
</div>
@endsection
