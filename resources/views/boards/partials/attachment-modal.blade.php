{{--
  Trello-style Attachment Modal
  State: attachmentModal.open, .tab ('file'|'link'), .cardId,
         .dragOver, .uploading, .uploadProgress, .error,
         .linkUrl, .linkName
  Methods: openAttachmentModal(), closeAttachmentModal(),
           amHandleDrop(), amBrowseFile(), amUploadFile(),
           amSubmitLink(), amDeleteAttachment(), amFileIcon(),
           amFormatBytes()
--}}
<div x-show="attachmentModal.open" x-cloak
     class="fixed inset-0 z-[80] flex items-center justify-center p-4"
     @keydown.escape.window="closeAttachmentModal()">

  {{-- Backdrop --}}
  <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
       @click="closeAttachmentModal()"></div>

  {{-- Panel --}}
  <div class="attachment-modal-panel relative bg-white rounded-2xl shadow-2xl border border-slate-100 w-full max-w-md overflow-hidden"
       x-transition:enter="transition ease-out duration-150"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       @click.stop>

    {{-- Header --}}
    <div class="attachment-modal-header flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/60">
      <h3 class="text-xs font-black text-slate-700 tracking-wide uppercase">📎 Attach</h3>
      <button @click="closeAttachmentModal()"
              class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    {{-- Tab strip --}}
    <div class="flex border-b border-slate-100 bg-white">
      <button @click="attachmentModal.tab = 'file'"
              class="flex-1 text-xs font-semibold py-2.5 transition-colors border-b-2 -mb-px"
              :class="attachmentModal.tab === 'file'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-700'">
        📁 File Upload
      </button>
      <button @click="attachmentModal.tab = 'link'"
              class="flex-1 text-xs font-semibold py-2.5 transition-colors border-b-2 -mb-px"
              :class="attachmentModal.tab === 'link'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-700'">
        🔗 Link / URL
      </button>
    </div>

    {{-- ── FILE TAB ─────────────────────────────────────────────────────────── --}}
    <div x-show="attachmentModal.tab === 'file'" class="p-5 space-y-4">

      {{-- Drag / Drop Zone --}}
      <div class="attachment-dropzone relative border-2 border-dashed rounded-xl transition-all duration-200 cursor-pointer"
           :class="attachmentModal.dragOver
             ? 'border-indigo-400 bg-indigo-50 scale-[1.01]'
             : 'border-slate-200 bg-slate-50/40 hover:border-indigo-300 hover:bg-indigo-50/30'"
           @dragover.prevent="attachmentModal.dragOver = true"
           @dragleave.prevent="attachmentModal.dragOver = false"
           @drop.prevent="amHandleDrop($event)"
           @click="$refs.amFileInput.click()">

        <div class="flex flex-col items-center justify-center py-8 px-4 text-center select-none">
          <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-3 transition-colors"
               :class="attachmentModal.dragOver ? 'bg-indigo-100' : 'bg-slate-100'">
            <svg class="w-6 h-6 transition-colors" :class="attachmentModal.dragOver ? 'text-indigo-600' : 'text-slate-400'"
                 fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/>
            </svg>
          </div>
          <p class="text-xs font-semibold text-slate-700 mb-0.5">
            <span x-text="attachmentModal.dragOver ? 'Drop it!' : 'Drag & drop or click to browse'"></span>
          </p>
          <p class="text-[10px] text-slate-400">
            Images, PDFs, Office docs, archives — max 20 MB
          </p>
        </div>
        <input type="file" x-ref="amFileInput"
               @change="amUploadFile($event)"
               class="hidden"
               accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.bmp,.tiff,
                       .pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,
                       .zip,.rar,.7z,.gz,
                       .txt,.csv,.md,
                       .mp4,.mov,.webm,.mp3,.wav,.ogg">
      </div>

      {{-- Upload progress bar --}}
      <template x-if="attachmentModal.uploading">
        <div class="space-y-1.5">
          <div class="flex items-center justify-between text-[10px] font-semibold text-slate-500">
            <span>Uploading…</span>
            <span x-text="attachmentModal.uploadProgress + '%'"></span>
          </div>
          <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-indigo-500 rounded-full transition-all duration-200"
                 :style="'width:' + attachmentModal.uploadProgress + '%'"></div>
          </div>
        </div>
      </template>

      {{-- Error message --}}
      <template x-if="attachmentModal.error">
        <div class="flex items-start gap-2 bg-rose-50 border border-rose-200 rounded-xl p-3">
          <svg class="w-4 h-4 text-rose-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
          </svg>
          <p class="text-[11px] text-rose-700 font-medium" x-text="attachmentModal.error"></p>
        </div>
      </template>

      {{-- Allowed types hint --}}
      <div class="attachment-types-hint bg-slate-50 rounded-xl p-3 text-[10px] text-slate-400 leading-relaxed">
        <span class="font-semibold text-slate-500 block mb-1">Allowed file types:</span>
        Images (JPG, PNG, GIF, WebP, SVG) · PDF · Word, Excel, PowerPoint ·
        ZIP, RAR, 7z · TXT, CSV · MP4, MOV, MP3
        <span class="block mt-1 text-rose-400 font-semibold">
          🚫 Executables, scripts and HTML files are blocked.
        </span>
      </div>

      {{-- Action buttons --}}
      <div class="flex gap-2 pt-1">
        <button @click="$refs.amFileInput.click()"
                :disabled="attachmentModal.uploading"
                class="btn btn-primary flex-1 py-2.5">
          Choose File
        </button>
        <button @click="closeAttachmentModal()"
                class="btn btn-danger attach-cancel-btn flex-1 py-2.5">
          Cancel
        </button>
      </div>
    </div>

    {{-- ── LINK TAB ─────────────────────────────────────────────────────────── --}}
    <div x-show="attachmentModal.tab === 'link'" class="p-5 space-y-4">

      <div>
        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1.5">
          URL
        </label>
        <input type="url"
               x-model="attachmentModal.linkUrl"
               @input="amAutoFillName()"
               placeholder="https://example.com/document.pdf"
               class="w-full text-xs bg-white border border-slate-200 rounded-xl px-3 py-2.5
                      focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:outline-none
                      transition-all placeholder-slate-300">
      </div>

      <div>
        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1.5">
          Display Text <span class="text-slate-300 font-normal">(optional)</span>
        </label>
        <input type="text"
               x-model="attachmentModal.linkName"
               placeholder="e.g. Design Brief"
               class="w-full text-xs bg-white border border-slate-200 rounded-xl px-3 py-2.5
                      focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:outline-none
                      transition-all placeholder-slate-300">
      </div>

      {{-- Link preview badge --}}
      <template x-if="attachmentModal.linkUrl.length > 8">
        <div class="flex items-center gap-2 bg-slate-50 rounded-xl px-3 py-2 border border-slate-100">
          <svg class="w-4 h-4 text-indigo-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
          </svg>
          <p class="text-xs font-medium text-slate-600 truncate"
             x-text="attachmentModal.linkName || attachmentModal.linkUrl"></p>
        </div>
      </template>

      {{-- Error message --}}
      <template x-if="attachmentModal.error">
        <div class="flex items-start gap-2 bg-rose-50 border border-rose-200 rounded-xl p-3">
          <svg class="w-4 h-4 text-rose-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
          </svg>
          <p class="text-[11px] text-rose-700 font-medium" x-text="attachmentModal.error"></p>
        </div>
      </template>

      {{-- Action buttons --}}
      <div class="flex gap-2 pt-1">
        <button @click="amSubmitLink()"
                :disabled="!attachmentModal.linkUrl || attachmentModal.uploading"
                class="btn btn-primary flex-1 py-2.5">
          Insert
        </button>
        <button @click="closeAttachmentModal()"
                class="btn btn-danger attach-cancel-btn flex-1 py-2.5">
          Cancel
        </button>
      </div>
    </div>

    {{-- ── Current Attachments list (shown in both tabs) ───────────────────── --}}
    <template x-if="activeCard?.files?.length">
      <div class="border-t border-slate-100 px-5 pb-4 pt-3">
        <p class="text-[10px] uppercase font-extrabold text-slate-400 tracking-wider mb-2 select-none">
          Current Attachments
        </p>
        <div class="space-y-1.5 max-h-64 overflow-y-auto scrollbar-thin pr-1">
          <template x-for="file in activeCard.files" :key="file.id">
            <div class="rounded-xl transition-all"
                 :class="attachmentModal.editingFileId === file.id ? 'bg-amber-50 border border-amber-200 p-3' : 'bg-slate-50 hover:bg-slate-100 px-3 py-2'">

              {{-- ═══ NORMAL VIEW MODE ═══ --}}
              <div x-show="attachmentModal.editingFileId !== file.id"
                   class="flex items-center gap-2.5 group">

                {{-- File icon / thumbnail --}}
                <div class="flex-shrink-0 w-8 h-8 rounded-lg overflow-hidden flex items-center justify-center"
                     :class="file.is_image ? 'bg-transparent' : 'bg-white border border-slate-200'">
                  <template x-if="file.is_image">
                    <button type="button" @click="previewAttachment(file)" class="h-full w-full">
                      <img :src="file.preview_url || file.url" :alt="file.original_name"
                          class="w-8 h-8 object-cover rounded-lg">
                    </button>
                  </template>
                  <template x-if="file.is_video && !file.is_image">
                    <button type="button" @click="openVideoPreview(file)" class="h-full w-full flex items-center justify-center bg-indigo-50 hover:bg-indigo-100 transition">
                      <span class="text-sm">🎥</span>
                    </button>
                  </template>
                  <template x-if="!file.is_image && !file.is_video">
                    <span class="text-base select-none" x-text="amFileIcon(file)"></span>
                  </template>
                </div>

                {{-- Name & size --}}
                <div class="flex-1 min-w-0">
                  <template x-if="file.is_video">
                    <button type="button" @click="openVideoPreview(file)"
                       class="text-xs font-semibold text-slate-700 hover:text-indigo-600 truncate block text-left w-full
                              transition-colors font-sans font-semibold" x-text="file.original_name"></button>
                  </template>
                  <template x-if="!file.is_video">
                    <a :href="file.disk === 'url' ? file.url : (file.preview_url || file.url)" target="_blank" rel="noopener"
                       class="text-xs font-semibold text-slate-700 hover:text-indigo-600 truncate block
                              transition-colors" x-text="file.original_name"></a>
                  </template>
                  <p class="text-[10px] text-slate-400"
                     x-text="file.disk === 'url' ? 'External link' : (file.formatted_size || '')"></p>
                </div>

                {{-- Action buttons --}}
                <a x-show="file.disk !== 'url'"
                   :href="file.download_url || file.url"
                   :download="file.original_name"
                   title="Download"
                   class="opacity-0 group-hover:opacity-100 p-1 rounded-lg
                          hover:bg-indigo-100 text-slate-300 hover:text-indigo-600
                          transition-all flex-shrink-0">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 12 12 16.5m0 0 4.5-4.5M12 16.5V3"/>
                  </svg>
                </a>
                <button @click="amEditAttachment(file)"
                        title="Edit"
                        class="opacity-0 group-hover:opacity-100 p-1 rounded-lg
                               hover:bg-amber-100 text-slate-300 hover:text-amber-500
                               transition-all flex-shrink-0">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
                  </svg>
                </button>
                <button @click="amDeleteAttachment(file)"
                        title="Remove"
                        class="opacity-0 group-hover:opacity-100 p-1 rounded-lg
                               hover:bg-rose-100 text-slate-300 hover:text-rose-500
                               transition-all flex-shrink-0">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>

              {{-- ═══ EDIT MODE (Trello-style inline) ═══ --}}
              <div x-show="attachmentModal.editingFileId === file.id" x-cloak class="space-y-2.5">

                {{-- Edit name --}}
                <div>
                  <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1">Name</label>
                  <input type="text"
                         x-model="attachmentModal.editName"
                         @keydown.enter="amSaveEdit(file)"
                         class="w-full text-xs bg-white border border-slate-200 rounded-lg px-2.5 py-2
                                focus:ring-2 focus:ring-amber-400/30 focus:border-amber-400 focus:outline-none transition-all">
                </div>

                {{-- Edit URL (only for links) --}}
                <div x-show="file.disk === 'url'">
                  <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1">URL</label>
                  <input type="url"
                         x-model="attachmentModal.editUrl"
                         @keydown.enter="amSaveEdit(file)"
                         class="w-full text-xs bg-white border border-slate-200 rounded-lg px-2.5 py-2
                                focus:ring-2 focus:ring-amber-400/30 focus:border-amber-400 focus:outline-none transition-all">
                </div>

                {{-- Replace file (only for uploaded files, not links) --}}
                <div x-show="file.disk !== 'url'">
                  <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1">Replace File</label>
                  <label class="flex items-center gap-2 px-2.5 py-2 bg-white border border-dashed border-slate-300 rounded-lg cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/30 transition-all">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/>
                    </svg>
                    <span class="text-xs text-slate-500">Choose replacement file…</span>
                    <input type="file" class="hidden"
                           @change="amReplaceFile(file, $event)"
                           accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.bmp,.tiff,
                                   .pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,
                                   .zip,.rar,.7z,.gz,
                                   .txt,.csv,.md,
                                   .mp4,.mov,.webm,.mp3,.wav,.ogg">
                  </label>
                </div>

                {{-- Save / Cancel --}}
                <div class="flex gap-2 pt-1">
                  <button @click="amSaveEdit(file)"
                          :disabled="attachmentModal.editSaving"
                          class="flex-1 text-xs font-bold py-1.5 rounded-lg bg-amber-500 text-white
                                 hover:bg-amber-600 disabled:opacity-50 transition-colors">
                    <span x-show="!attachmentModal.editSaving">Save</span>
                    <span x-show="attachmentModal.editSaving">Saving…</span>
                  </button>
                  <button @click="attachmentModal.editingFileId = null"
                          class="flex-1 text-xs font-bold py-1.5 rounded-lg bg-slate-200 text-slate-600
                                 hover:bg-slate-300 transition-colors">
                    Cancel
                  </button>
                </div>
              </div>

            </div>
          </template>
        </div>
      </div>
    </template>

  </div>
</div>

<div x-show="imagePreview.open" x-cloak
     class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/75 p-4"
     @keydown.escape.window="closeImagePreview()"
     @click="closeImagePreview()">
  <div class="max-h-[88vh] w-full max-w-4xl overflow-hidden rounded-2xl border border-white/15 bg-slate-950 shadow-2xl" @click.stop>
    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3 text-white">
      <p class="truncate text-sm font-black" x-text="imagePreview.title"></p>
      <button type="button" @click="closeImagePreview()" class="rounded-lg p-2 text-white/70 transition hover:bg-white/10 hover:text-white" aria-label="Close preview">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    <div class="flex max-h-[78vh] items-center justify-center bg-slate-900">
      <img :src="imagePreview.url" :alt="imagePreview.title" class="max-h-[78vh] max-w-full object-contain">
    </div>
  </div>
</div>

<!-- Video Preview Modal -->
<div x-show="videoPreview.open" x-cloak
     class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/80 p-4"
     @keydown.escape.window="closeVideoPreview()"
     @click="closeVideoPreview()">
  <div class="max-h-[88vh] w-full max-w-4xl overflow-hidden rounded-2xl border border-white/15 bg-slate-950 shadow-2xl flex flex-col" @click.stop>
    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3 text-white bg-slate-900/50 backdrop-blur-md">
      <p class="truncate text-sm font-black flex items-center gap-2" x-text="videoPreview.title"></p>
      <div class="flex items-center gap-3">
        <a :href="videoPreview.url"
           target="_blank"
           rel="noopener"
           class="text-xs font-semibold text-white/70 hover:text-white px-2.5 py-1 rounded-lg hover:bg-white/10 transition flex items-center gap-1.5"
           title="Open in new tab">
          <span x-text="videoPreview.url.includes('drive.google.com') ? 'Open in Drive' : 'Open Original'"></span>
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
          </svg>
        </a>
        <button type="button" @click="closeVideoPreview()" class="rounded-lg p-2 text-white/70 transition hover:bg-white/10 hover:text-white" aria-label="Close preview">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
    <div class="flex-1 flex items-center justify-center bg-slate-900 relative aspect-video min-h-[50vh]">
      <template x-if="videoPreview.embedUrl && videoPreview.embedUrl.includes('drive.google.com')">
        <iframe :src="videoPreview.embedUrl"
                class="w-full h-full border-0 absolute inset-0"
                allow="autoplay; fullscreen"
                allowfullscreen></iframe>
      </template>
      <template x-if="videoPreview.url && !videoPreview.url.includes('drive.google.com')">
        <video :src="videoPreview.url"
               controls
               autoplay
               class="w-full h-full max-h-[78vh] object-contain absolute inset-0 bg-black"
               style="outline: none;"></video>
      </template>
    </div>
  </div>
</div>
