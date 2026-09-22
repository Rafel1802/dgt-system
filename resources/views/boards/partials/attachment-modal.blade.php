<style>
  .attachment-modal-panel .attach-cancel-btn {
    background: linear-gradient(135deg, #b91c1c, #991b1b) !important;
    color: #ffffff !important;
    border: 1px solid rgba(153, 27, 27, 0.4) !important;
    box-shadow: 0 2px 8px rgba(185, 28, 28, 0.28) !important;
    transition: all 0.2s ease !important;
  }
  .attachment-modal-panel .attach-cancel-btn:hover,
  .attachment-modal-panel .attach-cancel-btn:active {
    background: linear-gradient(135deg, #991b1b, #7f1d1d) !important;
    color: #ffffff !important;
    border-color: #7f1d1d !important;
    box-shadow: 0 4px 14px rgba(153, 27, 27, 0.4) !important;
    transform: translateY(-1px);
  }

  .attachment-modal-panel .attach-submit-btn {
    background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
    color: #ffffff !important;
    border: 1px solid rgba(29, 78, 216, 0.4) !important;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.28) !important;
    transition: all 0.2s ease !important;
  }
  .attachment-modal-panel .attach-submit-btn:hover,
  .attachment-modal-panel .attach-submit-btn:active {
    background: linear-gradient(135deg, #1d4ed8, #1e3a8a) !important;
    color: #ffffff !important;
    border-color: #1e3a8a !important;
    box-shadow: 0 4px 14px rgba(29, 78, 216, 0.4) !important;
    transform: translateY(-1px);
  }
</style>

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
     class="fixed inset-0 flex items-center justify-center p-4"
     style="z-index: 110;"
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
        <button @click="closeAttachmentModal()"
                class="btn btn-danger attach-cancel-btn flex-1 py-2.5">
          Cancel
        </button>
        <button @click="$refs.amFileInput.click()"
                :disabled="attachmentModal.uploading"
                class="btn btn-primary attach-submit-btn flex-1 py-2.5 font-bold">
          Choose File
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
        <button @click="closeAttachmentModal()"
                class="btn btn-danger attach-cancel-btn flex-1 py-2.5">
          Cancel
        </button>
        <button @click="amSubmitLink()"
                :disabled="!attachmentModal.linkUrl || attachmentModal.uploading"
                class="btn btn-primary attach-submit-btn flex-1 py-2.5 font-bold">
          Insert
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
                  <template x-if="isVideoFile(file) && !file.is_image">
                    <button type="button" @click="openVideoPreview(file)"
                            x-data="{ thumbLoaded: false }"
                            class="h-full w-full flex items-center justify-center bg-blue-50/80 dark:bg-slate-900 border border-blue-200/80 dark:border-blue-500/40 rounded-lg overflow-hidden relative group/thumb" title="Play video">
                      <template x-if="getVideoThumbnailUrl(file)">
                        <img :src="getVideoThumbnailUrl(file)" :alt="file.original_name" referrerpolicy="no-referrer"
                             class="w-full h-full object-cover absolute inset-0"
                             x-show="thumbLoaded"
                             x-on:load="thumbLoaded = true"
                             x-on:error="thumbLoaded = false; $event.target.style.display='none'">
                      </template>
                      <template x-if="!getVideoThumbnailUrl(file) && isDirectVideoFile(file)">
                        <video :src="(file.preview_url || file.url) + '#t=0.5'" preload="metadata" muted playsinline
                               x-on:loadeddata="thumbLoaded = true"
                               x-show="thumbLoaded"
                               class="w-full h-full object-cover absolute inset-0 pointer-events-none"></video>
                      </template>
                      <div x-show="!thumbLoaded" class="flex items-center justify-center">
                        <svg class="w-4 h-4 text-[#2F68ED] dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                      </div>
                      <div x-show="thumbLoaded" class="absolute inset-0 bg-black/30 flex items-center justify-center">
                        <span class="text-[10px] text-white font-bold pl-0.5">▶</span>
                      </div>
                    </button>
                  </template>
                  <template x-if="!file.is_image && !isVideoFile(file)">
                    <span class="flex items-center justify-center">
                      <template x-if="isCanvaFile(file)">
                        <button type="button" @click="openCanvaPreview(file)" class="w-full h-full flex items-center justify-center cursor-pointer" title="View Canva Design">
                          <img src="{{ asset('images/canva-icon.png') }}"
                               alt="Canva"
                               class="w-5 h-5 object-contain rounded-full shadow-xs"
                               x-on:error="$event.target.src='https://brandlogovector.com/wp-content/uploads/2022/02/Canva-Icon-Logo.png'">
                        </button>
                      </template>
                      <template x-if="!isCanvaFile(file) && (file.disk === 'url' || file.is_link)">
                        <svg class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                      </template>
                      <template x-if="!isCanvaFile(file) && file.disk !== 'url' && !file.is_link">
                        <span class="text-base select-none" x-text="amFileIcon(file)"></span>
                      </template>
                    </span>
                  </template>
                </div>

                {{-- Name & size --}}
                <div class="flex-1 min-w-0">
                  <template x-if="isVideoFile(file)">
                    <button type="button" @click="openVideoPreview(file)"
                       class="text-xs font-semibold text-slate-700 hover:text-indigo-600 truncate block text-left w-full
                              transition-colors font-sans font-semibold" x-text="file.original_name"></button>
                  </template>
                  <template x-if="!isVideoFile(file) && isCanvaFile(file)">
                    <button type="button" @click="openCanvaPreview(file)"
                       class="text-xs font-semibold text-slate-700 hover:text-[#00c4cc] truncate block text-left w-full
                              transition-colors cursor-pointer" :title="file.original_name || 'Canva Design'" x-text="file.original_name || 'Canva Design'"></button>
                  </template>
                  <template x-if="!isVideoFile(file) && !isCanvaFile(file)">
                    <a :href="file.disk === 'url' ? file.url : (file.preview_url || file.url)" target="_blank" rel="noopener"
                       class="text-xs font-semibold text-slate-700 hover:text-indigo-600 truncate block
                              transition-colors" x-text="file.original_name || 'Attachment'"></a>
                  </template>
                  <p class="text-[10px] text-slate-400"
                     x-text="isCanvaFile(file) ? 'Canva link' : ((file.disk === 'url' || file.is_link) ? 'External link' : (file.formatted_size || ''))"></p>
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
     x-data="imageZoomHandler()"
     x-init="$watch('imagePreview.open', value => { if(value) reset(); })"
     class="fixed inset-0 bg-slate-950/95 flex flex-col w-screen h-screen overflow-hidden select-none"
     style="z-index: 99999;"
     @keydown.escape.window="if(imagePreview.open) { $event.preventDefault(); closeImagePreview(); }"
     @click="closeImagePreview()">
  <div class="flex items-center justify-between border-b border-white/10 px-4 sm:px-6 pb-3 text-white bg-slate-900/90 backdrop-blur-md flex-shrink-0 w-full"
       style="padding-top: calc(14px + env(safe-area-inset-top, 0px));"
       @click.stop>
    <div class="flex items-center gap-3 min-w-0">
      <p class="truncate text-sm sm:text-base font-extrabold tracking-wide text-white" x-text="imagePreview.title"></p>
      <span class="text-xs font-semibold text-cyan-400 bg-cyan-950/60 border border-cyan-500/30 px-2.5 py-0.5 rounded-full shrink-0" x-text="Math.round(scale * 100) + '%'"></span>
    </div>
    <div class="flex items-center gap-2 sm:gap-3">
        {{-- Zoom In / Out controls --}}
        <div class="inline-flex items-center bg-white/10 rounded-xl p-0.5 border border-white/15">
          <button type="button" @click="zoomOut()" class="p-1.5 hover:bg-white/20 rounded-lg text-white/90 hover:text-white transition" title="Zoom Out (-)">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
          </button>
          <button type="button" @click="zoomIn()" class="p-1.5 hover:bg-white/20 rounded-lg text-white/90 hover:text-white transition" title="Zoom In (+)">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
          </button>
        </div>
        <button type="button" @click="reset()" x-show="scale !== 1 || panX !== 0 || panY !== 0" class="text-xs font-bold bg-white/10 hover:bg-white/20 text-cyan-300 border border-cyan-400/30 px-3 py-1.5 rounded-xl transition" aria-label="Reset zoom">Reset Zoom</button>
        <button type="button" @click="closeImagePreview()" class="rounded-xl p-2 text-white/80 transition hover:bg-rose-500/30 hover:text-white bg-white/10 border border-white/10" aria-label="Close preview">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
          </svg>
        </button>
    </div>
  </div>
  <div class="flex-1 w-full h-full flex items-center justify-center p-2 sm:p-6 min-h-0 overflow-hidden relative"
       :class="isDragging ? '!cursor-grabbing' : (scale > 1 ? 'cursor-grab' : 'cursor-zoom-in')"
       @click.stop
       @wheel="handleWheel"
       @mousedown="startDrag($event)"
       @mousemove.window="onDrag($event)"
       @mouseup.window="stopDrag()"
       @touchstart="handleTouchStart"
       @touchmove="handleTouchMove"
       @touchend="handleTouchEnd"
       @touchcancel="handleTouchEnd">
    <img :src="imagePreview.url" :alt="imagePreview.title" 
         draggable="false"
         @click="toggleClickZoom($event)"
         class="max-h-full max-w-full w-auto h-auto object-contain select-none shadow-2xl rounded-xl"
         :class="isDragging ? '' : 'transition-transform duration-100 ease-out'"
         :style="`transform: translate(${panX}px, ${panY}px) scale(${scale}); touch-action: none;`">
  </div>
</div>

<script>
function imageZoomHandler() {
    return {
        scale: 1,
        panX: 0,
        panY: 0,
        isDragging: false,
        startX: 0,
        startY: 0,
        initialDistance: 0,
        initialScale: 1,
        dragMoved: false,

        reset() {
            this.scale = 1;
            this.panX = 0;
            this.panY = 0;
            this.isDragging = false;
            this.dragMoved = false;
        },

        zoomIn() {
            this.scale = Math.min(8, +(this.scale + 0.5).toFixed(2));
        },

        zoomOut() {
            this.scale = Math.max(0.5, +(this.scale - 0.5).toFixed(2));
            if (this.scale <= 1) {
                this.panX = 0;
                this.panY = 0;
            }
        },

        toggleClickZoom(e) {
            // If the user was dragging the image, do not toggle zoom
            if (this.dragMoved) {
                this.dragMoved = false;
                return;
            }
            if (this.scale === 1) {
                this.scale = 2;
            } else {
                this.reset();
            }
        },

        startDrag(e) {
            if (e.button !== 0) return; // Left click only
            e.preventDefault(); // Prevent native image ghost drag
            this.isDragging = true;
            this.dragMoved = false;
            this.startX = e.clientX - this.panX;
            this.startY = e.clientY - this.panY;
        },

        onDrag(e) {
            if (!this.isDragging) return;
            const newPanX = e.clientX - this.startX;
            const newPanY = e.clientY - this.startY;
            if (Math.abs(newPanX - this.panX) > 3 || Math.abs(newPanY - this.panY) > 3) {
                this.dragMoved = true;
            }
            this.panX = newPanX;
            this.panY = newPanY;
        },

        stopDrag() {
            this.isDragging = false;
        },

        handleWheel(e) {
            e.preventDefault();

            // Trackpad pinch zoom (Ctrl + wheel)
            if (e.ctrlKey) {
                const zoomDelta = -e.deltaY * 0.015;
                this.scale = Math.min(Math.max(0.5, this.scale + zoomDelta), 8);
                if (this.scale <= 1) {
                    this.panX = 0;
                    this.panY = 0;
                }
                return;
            }

            // Normal scroll or two-finger swipe on mousepad
            if (this.scale > 1) {
                // Pan image horizontally and vertically
                this.panX -= e.deltaX;
                this.panY -= e.deltaY;
            } else {
                // If at 1x and scrolling up, zoom in
                if (e.deltaY < -20) {
                    this.scale = 1.5;
                }
            }
        },

        handleTouchStart(e) {
            if (e.touches.length === 2) {
                this.initialDistance = Math.hypot(
                    e.touches[0].pageX - e.touches[1].pageX,
                    e.touches[0].pageY - e.touches[1].pageY
                );
                this.initialScale = this.scale;
            } else if (e.touches.length === 1) {
                this.isDragging = true;
                this.dragMoved = false;
                this.startX = e.touches[0].pageX - this.panX;
                this.startY = e.touches[0].pageY - this.panY;
            }
        },

        handleTouchMove(e) {
            if (e.touches.length === 2 && this.initialDistance > 0) {
                e.preventDefault();
                const currentDistance = Math.hypot(
                    e.touches[0].pageX - e.touches[1].pageX,
                    e.touches[0].pageY - e.touches[1].pageY
                );
                const ratio = currentDistance / this.initialDistance;
                this.scale = Math.min(Math.max(0.5, this.initialScale * ratio), 8);
            } else if (e.touches.length === 1 && this.isDragging) {
                e.preventDefault();
                const newPanX = e.touches[0].pageX - this.startX;
                const newPanY = e.touches[0].pageY - this.startY;
                if (Math.abs(newPanX - this.panX) > 3 || Math.abs(newPanY - this.panY) > 3) {
                    this.dragMoved = true;
                }
                this.panX = newPanX;
                this.panY = newPanY;
            }
        },

        handleTouchEnd(e) {
            if (e.touches.length < 2) {
                this.initialDistance = 0;
            }
            if (e.touches.length === 0) {
                this.isDragging = false;
            }
        }
    };
}
</script>

<!-- Video Preview Modal (Popup Theater) -->
<div x-show="videoPreview.open" x-cloak
     class="fixed inset-0 flex items-center justify-center bg-slate-950/85 backdrop-blur-sm p-3 sm:p-6"
     style="z-index: 110;"
     @keydown.escape.window="if(videoPreview.open) { $event.preventDefault(); closeVideoPreview(); }"
     @click="closeVideoPreview()">
  <div class="max-h-[92vh] w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-700/80 bg-slate-950 shadow-2xl flex flex-col" @click.stop>
    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3 text-white bg-slate-900/80 backdrop-blur-md gap-3">
      <div class="flex items-center gap-2.5 truncate min-w-0 flex-1">
        <span class="text-base select-none">🎥</span>
        <p class="truncate text-sm font-black text-white" x-text="videoPreview.title"></p>
        <span x-show="videoPreview.url && videoPreview.url.includes('drive.google.com')" class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 whitespace-nowrap">Google Drive</span>
        <span x-show="videoPreview.url && (videoPreview.url.includes('youtube.com') || videoPreview.url.includes('youtu.be'))" class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30 whitespace-nowrap">YouTube</span>
        <span x-show="videoPreview.url && videoPreview.url.includes('loom.com')" class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 whitespace-nowrap">Loom</span>
      </div>
      <div class="flex items-center gap-2 flex-shrink-0">
        <button type="button"
                @click="openVideoDirect(videoPreview.url)"
                class="text-xs font-bold px-3 py-1.5 rounded-lg transition flex items-center gap-1.5 cursor-pointer shadow-xs active:scale-95"
                :class="videoPreview.url && videoPreview.url.includes('drive.google.com') ? 'bg-emerald-600 hover:bg-emerald-500 text-white' : 'bg-white/10 hover:bg-white/20 text-white/90'"
                title="Open original link in browser">
          <span x-text="videoPreview.url && videoPreview.url.includes('drive.google.com') ? 'Open in Google Drive' : 'Open Link'"></span>
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
          </svg>
        </button>
        <button type="button" @click="closeVideoPreview()" class="rounded-xl p-1.5 sm:px-2.5 sm:py-1.5 text-white/80 transition hover:bg-rose-500/30 hover:text-white bg-white/10 border border-white/10 flex items-center gap-1 text-xs font-bold" aria-label="Close preview" title="Close (Esc)">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
          </svg>
          <span class="hidden sm:inline font-mono text-[10px] text-white/60">Esc</span>
        </button>
      </div>
    </div>
    <div class="flex-1 flex items-center justify-center bg-black relative aspect-video min-h-[50vh] max-h-[80vh]">
      <template x-if="videoPreview.embedUrl && (videoPreview.embedUrl.includes('drive.google.com') || videoPreview.embedUrl.includes('youtube.com') || videoPreview.embedUrl.includes('loom.com') || videoPreview.embedUrl.includes('vimeo.com'))">
        <iframe :src="videoPreview.embedUrl"
                class="w-full h-full border-0 absolute inset-0"
                @load="onVideoIframeLoad($event)"
                allow="autoplay *; fullscreen *; picture-in-picture *; encrypted-media *; camera; microphone; display-capture; clipboard-write"
                allowfullscreen="true"
                webkitallowfullscreen="true"
                mozallowfullscreen="true"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"></iframe>
      </template>
      <template x-if="videoPreview.url && (!videoPreview.embedUrl || (!videoPreview.embedUrl.includes('drive.google.com') && !videoPreview.embedUrl.includes('youtube.com') && !videoPreview.embedUrl.includes('loom.com') && !videoPreview.embedUrl.includes('vimeo.com')))">
        <video id="systemVideoPlayer"
               :src="videoPreview.url"
               controls
               autoplay
               playsinline
               webkit-playsinline
               @loadeddata="$event.target.play().catch(e => { $event.target.muted = true; $event.target.play(); })"
               @canplay="$event.target.play().catch(e => { $event.target.muted = true; $event.target.play(); })"
               class="w-full h-full max-h-[78vh] object-contain absolute inset-0 bg-black"
               style="outline: none;"></video>
      </template>
    </div>
  </div>
</div>

<!-- Canva Preview Modal (Interactive Theater with Zoom & Page Navigation) -->
<div x-show="canvaPreview.open" x-cloak
     class="fixed inset-0 flex items-center justify-center bg-slate-950/90 backdrop-blur-md p-2 sm:p-5"
     style="z-index: 110;"
     @keydown.escape.window="if(canvaPreview.open) { $event.preventDefault(); closeCanvaPreview(); }"
     @keydown.left.window="if(canvaPreview.open) { if(canvaPreview.scale > 1) { canvaPanLeft(); } else { canvaPrevPage(); } }"
     @keydown.right.window="if(canvaPreview.open) { if(canvaPreview.scale > 1) { canvaPanRight(); } else { canvaNextPage(); } }"
     @keydown.up.window="if(canvaPreview.open && canvaPreview.scale > 1) { $event.preventDefault(); canvaPanUp(); }"
     @keydown.down.window="if(canvaPreview.open && canvaPreview.scale > 1) { $event.preventDefault(); canvaPanDown(); }"
     @fullscreenchange.window="canvaPreview.isFullscreen = !!(document.fullscreenElement || document.webkitFullscreenElement)"
     @webkitfullscreenchange.window="canvaPreview.isFullscreen = !!(document.fullscreenElement || document.webkitFullscreenElement)"
     @click="closeCanvaPreview()">

  <div id="canvaModalPanel"
       :class="canvaPreview.isFullscreen ? 'h-full w-full max-w-none rounded-none' : 'h-[94vh] w-full max-w-6xl rounded-2xl'"
       class="overflow-hidden border border-slate-700/80 bg-slate-950 shadow-2xl flex flex-col relative select-none transition-all duration-150"
       @click.stop>
    
    {{-- Header --}}
    <div class="flex items-center justify-between border-b border-white/10 px-4 py-2.5 text-white bg-slate-900/90 backdrop-blur-md gap-3 flex-shrink-0 z-20">
      
      {{-- Left: Logo & Title --}}
      <div class="flex items-center gap-2.5 truncate min-w-0 flex-1">
        <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-gradient-to-tr from-[#00c4cc] to-[#7d2ae8] p-1 flex-shrink-0 shadow-md">
          <img src="{{ asset('images/canva-icon.png') }}" alt="Canva" class="w-full h-full object-contain"
               x-on:error="$event.target.src='https://brandlogovector.com/wp-content/uploads/2022/02/Canva-Icon-Logo.png'">
        </span>
        <p class="truncate text-sm font-black text-white" x-text="canvaPreview.title || 'Canva Design'"></p>
        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-gradient-to-r from-[#00c4cc]/20 to-[#7d2ae8]/20 text-cyan-300 border border-cyan-500/30 whitespace-nowrap hidden sm:inline-block">
          Interactive Design
        </span>
      </div>

      {{-- Center: Controls (Prev, Next, Zoom In, Zoom Out, Scale %, Reset) --}}
      <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
        
        {{-- Prev / Next Slide Navigation Buttons (With Infinite Looping) --}}
        <div class="inline-flex items-center bg-white/10 rounded-xl p-0.5 border border-white/15">
          <button type="button"
                  @mousedown.stop
                  @click="canvaPrevPage()"
                  class="px-2.5 py-1.5 hover:bg-white/20 active:scale-95 text-white/90 hover:text-white rounded-lg transition flex items-center gap-1 text-xs font-bold cursor-pointer"
                  title="Previous Page (Loops to last)">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
            </svg>
            <span class="hidden md:inline">Prev</span>
          </button>

          <span class="text-xs font-mono font-bold text-white/90 px-2 select-none"
                x-text="`${canvaPreview.currentPage} / ${canvaGetTotalPages()}`"></span>

          <div class="w-px h-4 bg-white/20 my-auto"></div>

          <button type="button"
                  @mousedown.stop
                  @click="canvaNextPage()"
                  class="px-2.5 py-1.5 hover:bg-white/20 active:scale-95 text-white/90 hover:text-white rounded-lg transition flex items-center gap-1 text-xs font-bold cursor-pointer"
                  title="Next Page (Loops to first)">
            <span class="hidden md:inline">Next</span>
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
          </button>
        </div>

        {{-- Reset Zoom Button (shown when scale != 1 or panned) - Placed to the left of minus (-) --}}
        <button type="button"
                x-show="canvaPreview.scale !== 1 || canvaPreview.panX !== 0 || canvaPreview.panY !== 0"
                @click="canvaResetZoom()"
                class="text-xs font-bold bg-white/10 hover:bg-white/20 text-cyan-300 border border-cyan-400/30 px-2.5 py-1.5 rounded-xl transition select-none cursor-pointer">
          Reset
        </button>

        {{-- Zoom Controls --}}
        <div class="inline-flex items-center bg-white/10 rounded-xl p-0.5 border border-white/15">
          <button type="button"
                  @click="canvaZoomOut()"
                  class="p-1.5 hover:bg-white/20 rounded-lg text-white/90 hover:text-white transition cursor-pointer"
                  title="Zoom Out (-)">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
            </svg>
          </button>
          <span class="text-xs font-mono font-bold text-cyan-300 px-2 select-none"
                x-text="Math.round(canvaPreview.scale * 100) + '%'"></span>
          <button type="button"
                  @click="canvaZoomIn()"
                  class="p-1.5 hover:bg-white/20 rounded-lg text-white/90 hover:text-white transition cursor-pointer"
                  title="Zoom In (+)">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
          </button>
        </div>

      </div>

      {{-- Right: Open External, Fullscreen & Close --}}
      <div class="flex items-center gap-2 flex-shrink-0">
        <button type="button"
                @click="openCanvaDirect({ url: canvaPreview.url })"
                class="text-xs font-bold text-white bg-gradient-to-r from-[#00c4cc]/80 to-[#7d2ae8]/80 hover:from-[#00c4cc] hover:to-[#7d2ae8] px-3 py-1.5 rounded-xl transition flex items-center gap-1.5 shadow-sm cursor-pointer"
                title="Open in Canva (browser)">
          <span>Open in Canva</span>
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
          </svg>
        </button>

        {{-- Full Screen Toggle Button --}}
        <button type="button"
                @click="canvaToggleFullScreen()"
                class="rounded-xl p-1.5 sm:px-2.5 sm:py-1.5 text-white/80 transition hover:bg-white/20 hover:text-white bg-white/10 border border-white/10 flex items-center gap-1.5 text-xs font-bold cursor-pointer"
                :title="canvaPreview.isFullscreen ? 'Exit Full Screen' : 'Full Screen'">
          <template x-if="!canvaPreview.isFullscreen">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
            </svg>
          </template>
          <template x-if="canvaPreview.isFullscreen">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25" />
            </svg>
          </template>
          <span class="hidden sm:inline" x-text="canvaPreview.isFullscreen ? 'Exit' : 'Full Screen'"></span>
        </button>

        <button type="button"
                @click="closeCanvaPreview()"
                class="rounded-xl p-1.5 sm:px-2.5 sm:py-1.5 text-white/80 transition hover:bg-rose-500/30 hover:text-white bg-white/10 border border-white/10 flex items-center gap-1 text-xs font-bold cursor-pointer"
                aria-label="Close preview"
                title="Close (Esc)">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
          </svg>
          <span class="hidden sm:inline font-mono text-[10px] text-white/60">Esc</span>
        </button>
      </div>

    </div>

    {{-- Viewer Canvas Container with Zoom & Pan --}}
    <div class="flex-1 w-full h-full flex items-center justify-center p-2 sm:p-4 min-h-0 overflow-hidden relative bg-black/90 select-none"
         :class="canvaPreview.isDragging ? '!cursor-grabbing' : (canvaPreview.scale > 1 ? 'cursor-grab' : '')"
         @wheel="canvaHandleWheel($event)"
         @mousedown="canvaStartDrag($event)"
         @mousemove.window="canvaOnDrag($event)"
         @mouseup.window="canvaStopDrag()">

      {{-- Floating On-Screen Quick Navigation Arrows (Left / Right) - visible when not zoomed in --}}
      <button type="button"
              x-show="canvaPreview.scale <= 1"
              @mousedown.stop
              @click.stop="canvaPrevPage()"
              class="absolute left-4 top-1/2 -translate-y-1/2 z-50 w-12 h-12 rounded-full bg-slate-900/85 hover:bg-slate-900 text-white border border-white/25 shadow-2xl flex items-center justify-center transition-all duration-150 active:scale-90 group/prev opacity-85 hover:opacity-100 cursor-pointer backdrop-blur-md"
              title="Previous Page (Loops to last)">
        <svg class="w-6 h-6 transition-transform group-hover/prev:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
        </svg>
      </button>

      <button type="button"
              x-show="canvaPreview.scale <= 1"
              @mousedown.stop
              @click.stop="canvaNextPage()"
              class="absolute right-4 top-1/2 -translate-y-1/2 z-50 w-12 h-12 rounded-full bg-slate-900/85 hover:bg-slate-900 text-white border border-white/25 shadow-2xl flex items-center justify-center transition-all duration-150 active:scale-90 group/next opacity-85 hover:opacity-100 cursor-pointer backdrop-blur-md"
              title="Next Page (Loops to first)">
        <svg class="w-6 h-6 transition-transform group-hover/next:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
        </svg>
      </button>

      {{-- Loading Indicator --}}
      <div x-show="canvaPreview.loading"
           class="absolute inset-0 z-50 flex flex-col items-center justify-center bg-slate-950/80 backdrop-blur-xs">
        <div class="w-10 h-10 border-3 border-[#00c4cc] border-t-transparent rounded-full animate-spin"></div>
        <p class="mt-3 text-xs font-semibold text-slate-300">Loading Canva design...</p>
      </div>

      {{-- Zoomable and Pannable Iframe Container --}}
      <div class="w-full h-full flex items-center justify-center relative select-none"
           :class="canvaPreview.isDragging ? '' : 'transition-transform duration-100 ease-out'"
           :style="`transform: translate(${canvaPreview.panX}px, ${canvaPreview.panY}px) scale(${canvaPreview.scale}); transform-origin: center center;`">

        {{-- Canva Iframe --}}
        <template x-if="canvaPreview.embedUrl">
          <iframe id="canvaPreviewIframe"
                  :src="canvaPreview.embedUrl"
                  class="w-full h-full rounded-xl border border-white/10 shadow-2xl bg-slate-900"
                  style="min-height: 500px; height: 100%; width: 100%; border: none;"
                  loading="lazy"
                  allow="fullscreen; picture-in-picture; clipboard-write"
                  allowfullscreen="true"
                  webkitallowfullscreen="true"
                  mozallowfullscreen="true"></iframe>
        </template>

        {{-- Transparent overlay active whenever zoomed in: intercepts mousedown so the cross-origin iframe NEVER swallows dragging! --}}
        <div x-show="canvaPreview.scale > 1"
             @mousedown.stop.prevent="canvaStartDrag($event)"
             class="absolute inset-0 z-40 bg-transparent select-none"
             :class="canvaPreview.isDragging ? '!cursor-grabbing' : 'cursor-grab'"
             title="Click and drag to pan"></div>

      </div>



    </div>

  </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════
     Google Docs / Sheets / Slides In-System Preview Modal
     ══════════════════════════════════════════════════════════════════ --}}
<div x-show="googleDocsPreview.open" x-cloak
     class="fixed inset-0 flex flex-col bg-slate-950/95 backdrop-blur-sm"
     style="z-index: 120;"
     @keydown.escape.window="if(googleDocsPreview.open) { $event.preventDefault(); closeGoogleDocsPreview(); }">

  {{-- Header bar --}}
  <div class="flex items-center gap-3 px-4 py-3 bg-[#1a1f2e]/90 border-b border-white/10 flex-shrink-0 shadow-xl">

    {{-- Google colours icon --}}
    <div class="flex items-center gap-1.5 flex-shrink-0">
      <div class="w-5 h-5 rounded-sm flex items-center justify-center"
           :class="{
             'bg-[#1a73e8]': googleDocsPreview.type === 'doc',
             'bg-[#0f9d58]': googleDocsPreview.type === 'sheet',
             'bg-[#f4b400]': googleDocsPreview.type === 'slide',
             'bg-[#9c27b0]': googleDocsPreview.type === 'form',
           }">
        <svg class="w-3 h-3 text-white fill-current" viewBox="0 0 24 24">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM6 4h5v7h8v9H6V4zm2 9h8v1H8v-1zm0 2h8v1H8v-1zm0 2h5v1H8v-1z"/>
        </svg>
      </div>
    </div>

    {{-- Title --}}
    <div class="min-w-0 flex-1">
      <p class="text-sm font-semibold text-white truncate" x-text="googleDocsPreview.title"></p>
      <p class="text-[10px] text-slate-400 capitalize" x-text="googleDocsPreview.type === 'sheet' ? 'Google Sheets' : (googleDocsPreview.type === 'slide' ? 'Google Slides' : (googleDocsPreview.type === 'form' ? 'Google Forms' : 'Google Docs'))"></p>
    </div>

    {{-- Action buttons --}}
    <div class="flex items-center gap-2 flex-shrink-0">
      {{-- Open in browser --}}
      <button type="button"
              @click.stop="openGoogleDocsDirect(googleDocsPreview.url)"
              title="Open in browser"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-[#1a73e8] bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg transition-all active:scale-95">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
        </svg>
        <span>Open in browser</span>
      </button>

      {{-- Close --}}
      <button type="button"
              @click.stop="closeGoogleDocsPreview()"
              title="Close"
              class="w-8 h-8 flex items-center justify-center rounded-full text-slate-300 hover:text-white hover:bg-white/10 transition-all active:scale-95">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
  </div>

  {{-- Iframe viewer --}}
  <div class="flex-1 relative overflow-hidden bg-white">
    <iframe
      :src="googleDocsPreview.embedUrl"
      class="absolute inset-0 w-full h-full border-0"
      allow="autoplay; clipboard-read; clipboard-write; fullscreen"
      allowfullscreen
      sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads">
    </iframe>
  </div>

</div>
