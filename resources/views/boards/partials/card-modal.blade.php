{{-- Upgraded Card Detail Modal (Phase 2 Trello features) --}}
<div x-show="activeCard !== null" x-cloak
     class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-start justify-center p-4 pt-16 pb-32 lg:pb-16 overflow-y-auto"
     style="z-index: 70;"
     @click.self="closeCard()"
     @keydown.escape.window="if(activeCard !== null && !imagePreview.open && !attachmentModal?.open && !exportModal?.open && !switchBoardsModal?.open && !importModal?.open && !cardTransferModal?.open) { $event.preventDefault(); closeCard(); }">

  <div class="trello-card-modal bg-white rounded-2xl shadow-2xl w-full max-w-4xl mb-8 overflow-hidden border border-slate-100 flex flex-col"
       x-show="activeCard"
       x-transition:enter="transition ease-out duration-75"
       x-transition:enter-start=" scale-95"
       x-transition:enter-end=" scale-100">

    {{-- Loading spinner --}}
    <div x-show="cardLoading" class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
    </div>

    <div x-show="!cardLoading && activeCard" class="flex flex-col h-full">

      {{-- Header section --}}
      <div class="p-6 pb-4 border-b border-slate-100 bg-slate-50/50">
        <div class="flex items-start gap-4">
          <span class="text-2xl mt-1 select-none">💳</span>
          <div class="flex-1 min-w-0">
            <input type="text" :value="activeCard?.title"
                   @change="updateCardField({ title: $event.target.value })"
                   @keydown.enter="$event.target.blur()"
                   class="card-detail-title font-display font-bold text-slate-800 text-xl w-full bg-transparent border-0 rounded px-2 -ml-2 py-0.5 transition-all truncate focus:ring-2 focus:ring-indigo-500/20 focus:bg-white">
            
            {{-- Stage/List selector dropdown trigger --}}
            <div class="flex items-center gap-1.5 text-xs text-slate-400 mt-1.5" x-data="{ openListSelect: false }">
              <span>in list</span>
              <div class="relative">
                <button @click="openListSelect = !openListSelect"
                        data-ctx-panel="move"
                        class="font-bold text-indigo-600 hover:text-indigo-800 underline focus:outline-none transition-colors"
                        x-text="activeCard?.board_list_name">
                </button>
                
                <div x-show="openListSelect" @click.outside="openListSelect = false" x-cloak
                     class="absolute left-0 mt-1 w-52 bg-white border border-slate-200 rounded-xl shadow-xl z-50 py-1.5"
                     x-transition:enter="transition ease-out duration-75"
                     x-transition:enter-start=" scale-95"
                     x-transition:enter-end=" scale-100">
                  <p class="text-[10px] uppercase font-bold text-slate-400 px-3 py-1.5 border-b border-slate-100">Move Column</p>
                  <div class="max-h-48 overflow-y-auto">
                    <template x-for="l in lists" :key="l.id">
                      <button @click="openListSelect = false; moveCardDirect(l.id)"
                              class="w-full text-left px-3 py-2 text-xs hover:bg-indigo-50 hover:text-indigo-700 flex items-center justify-between transition-colors"
                              :class="l.id === activeCard?.board_list_id ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-slate-600'">
                        <span x-text="l.name"></span>
                        <template x-if="l.id === activeCard?.board_list_id">
                          <span class="text-indigo-600 font-bold">✓</span>
                        </template>
                      </button>
                    </template>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <button @click="closeCard()" class="text-slate-400 hover:text-slate-600 flex-shrink-0 mt-1 p-1 hover:bg-slate-200/50 rounded-full transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        {{-- Meta Badges Row --}}
        <div class="flex flex-wrap gap-6 mt-5 text-xs" style="padding-left: 40px;">
          {{-- SMM Specific: Team & Class Labels --}}
          <template x-if="board?.name?.toLowerCase().includes('smm') || activeCard?.smm_class_label || activeCard?.smm_cluster_label || activeCard?.content_public_date || activeCard?.labels?.some(l => l.name.toLowerCase() === 'smm')">
            <div class="flex flex-col gap-6 text-xs w-full">
              {{-- CLASSIFICATION --}}
              <div>
                <p class="text-[11px] font-bold text-slate-800 uppercase tracking-widest mb-3 border-b border-slate-200 pb-1">Classification</p>
                <div class="flex flex-wrap gap-6">
                  {{-- Class (formerly Cluster) --}}
                  <div class="min-w-[100px]">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1.5">Class</p>
                    <div x-show="activeCard?.smm_cluster_label">
                      <a x-show="activeCard?.smm_cluster_link" :href="activeCard?.smm_cluster_link" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-emerald-700 bg-emerald-50 font-bold text-[10px] shadow-sm border border-emerald-100 hover:bg-emerald-100 transition-colors cursor-pointer" title="Open Drive Link">
                        <span x-text="activeCard?.smm_cluster_label"></span>
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                      </a>
                      <span x-show="!activeCard?.smm_cluster_link" class="px-2.5 py-1 rounded-md text-emerald-700 bg-emerald-50 font-bold text-[10px] shadow-sm border border-emerald-100" x-text="activeCard?.smm_cluster_label"></span>
                    </div>
                    <div x-show="!activeCard?.smm_cluster_label">
                      <span class="text-xs text-slate-400 italic">None</span>
                    </div>
                  </div>
                  {{-- Content Public Date --}}
                  <div class="min-w-[100px]">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1.5">Content Public</p>
                    <div class="relative flex items-center">
                      <input type="date" 
                             :value="formatInputDate(activeCard?.content_public_date)" 
                             @change="updateCardField({ content_public_date: $event.target.value })" 
                             class="px-2.5 py-1 rounded-md text-amber-700 bg-amber-50 font-bold text-[10px] shadow-sm border border-amber-100 focus:ring-1 focus:ring-amber-300 focus:border-amber-300 w-[110px] cursor-pointer"
                             title="Click to edit public date" />
                    </div>
                  </div>
                  {{-- Assign By --}}
                  <div class="min-w-[100px]">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1.5">Assign By</p>
                    <template x-if="activeCard?.creator">
                      <div class="flex items-center gap-1.5">
                        <template x-if="avatarUrl(activeCard.creator)">
                          <img :src="avatarUrl(activeCard.creator)" :title="activeCard.creator.name" class="w-6 h-6 rounded-full object-cover shadow-sm ring-1 ring-slate-100">
                        </template>
                        <template x-if="!avatarUrl(activeCard.creator)">
                          <span class="w-6 h-6 rounded-full bg-slate-200 text-[9px] flex items-center justify-center font-black text-slate-600 shadow-sm ring-1 ring-slate-100" x-text="avatarInitials(activeCard.creator)" :title="activeCard.creator.name"></span>
                        </template>
                        <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-200" x-text="activeCard.creator.name"></span>
                      </div>
                    </template>
                    <template x-if="!activeCard?.creator">
                      <span class="text-xs text-slate-400 italic">None</span>
                    </template>
                  </div>
                </div>
              </div>
            </div>
          </template>

          {{-- Standard Meta Badges --}}
          <div class="flex flex-wrap gap-6 items-start text-xs mt-6">
            {{-- Members --}}
            <div class="min-w-[120px]">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1.5">Members</p>
              <template x-if="activeCard?.assignees?.length">
                <div class="flex flex-wrap gap-1">
                  <template x-for="m in (activeCard?.assignees ?? [])" :key="m.id">
                    <span class="flex items-center gap-1.5 bg-slate-100/50 hover:bg-slate-100 transition-colors border border-slate-200/60 rounded-full pr-2.5 pb-0.5 pt-0.5 pl-0.5">
                      <template x-if="avatarUrl(m)">
                        <img :src="avatarUrl(m)" :alt="m.name" :title="m.name"
                             @dblclick.stop="openAvatarPreview(m)"
                             class="w-6 h-6 cursor-zoom-in rounded-full object-cover shadow-sm hover:scale-105 transition-transform">
                      </template>
                      <template x-if="!avatarUrl(m)">
                        <span class="w-6 h-6 rounded-full shadow-sm flex items-center justify-center text-[9px] font-black text-white"
                              :style="avatarStyle(m)"
                              x-text="avatarInitials(m)"
                              :title="m.name"></span>
                      </template>
                      <span class="text-[11px] font-medium text-slate-700" x-text="m.name"></span>
                    </span>
                  </template>
                </div>
              </template>
              <template x-if="!activeCard?.assignees?.length">
                <span class="text-xs text-slate-400 italic">Unassigned</span>
              </template>
            </div>

            {{-- Active Labels --}}
            <div x-show="activeCard?.labels?.length" class="min-w-[120px]">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1.5">Labels</p>
              <div class="flex flex-wrap gap-1">
                <template x-for="lbl in (activeCard?.labels ?? [])" :key="lbl.id">
                  <span class="px-2.5 py-1 rounded-md text-white font-semibold text-[10px] shadow-sm"
                        :style="'background:'+lbl.color" x-text="lbl.name"></span>
                </template>
              </div>
            </div>

            {{-- Due Date → opens Trello-style date picker --}}
            <div class="min-w-[180px]">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1.5">📅 Dates</p>
              <div class="flex items-center gap-2 flex-wrap">

                {{-- Start date badge --}}
                <template x-if="activeCard?.start_date">
                  <button @click="openDatePicker(activeCard)"
                          class="text-[10px] font-bold px-2 py-1 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors flex items-center gap-1">
                    <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25"/></svg>
                    Start: <span x-text="formatDateShort(activeCard.start_date)"></span>
                  </button>
                </template>

                {{-- Due date badge with status colour --}}
                <button @click="openDatePicker(activeCard)"
                        class="text-[10px] font-bold px-2 py-1 rounded-lg flex items-center gap-1 transition-colors"
                        :class="activeCard?.due_at
                          ? (activeCard?.status === 'done'
                              ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'
                              : (new Date(activeCard.due_at) < new Date()
                                  ? 'bg-rose-100 text-rose-700 hover:bg-rose-200'
                                  : ((new Date(activeCard.due_at) - new Date()) < 86400000
                                      ? 'bg-amber-100 text-amber-700 hover:bg-amber-200'
                                      : 'bg-slate-100 text-slate-600 hover:bg-slate-200')))
                          : 'bg-slate-100 text-slate-500 hover:bg-slate-200'">
                  <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                  <span x-text="formatDueBadge(activeCard?.due_at, activeCard?.due_time, activeCard?.status)"></span>
                </button>

                {{-- Recurring badge --}}
                <template x-if="activeCard?.recurring && activeCard.recurring !== 'none'">
                  <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-600 flex items-center gap-0.5">
                    🔄 <span x-text="activeCard.recurring"></span>
                  </span>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Grid Body --}}
      <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-6 bg-slate-50/20">

        {{-- Left Column: Main features --}}
        <div class="md:col-span-3 space-y-6">

          {{-- Premium Markdown Description Editor --}}
          <div class="flex gap-4">
            <span class="text-xl mt-0.5 select-none">📝</span>
            <div class="flex-1">
              <div class="flex items-center justify-between mb-2">
                <h3 class="font-bold text-slate-800 text-sm">Description</h3>
                <button x-show="!isEditingDesc" @click="isEditingDesc = true"
                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 transition-colors">
                  ✏️ Edit
                </button>
              </div>

              {{-- Non-editing preview mode --}}
              <div x-show="!isEditingDesc"
                   @click="isEditingDesc = true"
                   class="bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100/70 dark:hover:bg-slate-800 border border-slate-100/50 dark:border-slate-700 rounded-xl p-4 text-xs text-slate-700 dark:text-slate-200 cursor-pointer min-h-16 prose prose-slate dark:prose-invert max-w-none transition-all leading-relaxed"
                   x-html="parseMarkdown(activeCard?.description)">
              </div>

              {{-- Rich Text editor mode --}}
              <div x-show="isEditingDesc" x-cloak class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden bg-white dark:bg-slate-800 shadow-sm transition-all"
                   x-init="$watch('isEditingDesc', val => {
                     if (val) {
                       if (!window.cardDescQuill) {
                         window.cardDescQuill = new Quill('#card-desc-editor', {
                           theme: 'snow',
                           placeholder: 'Add a more detailed description...',
                           modules: {
                             toolbar: [
                               ['bold', 'italic', 'underline', 'strike'],
                               [{ 'header': [1, 2, 3, false] }],
                               [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                               ['link', 'clean']
                             ]
                           }
                         });
                         window.cardDescQuill.on('text-change', () => {
                           if (activeCard) activeCard.description = window.cardDescQuill.root.innerHTML;
                         });
                       }
                       // Load content
                       if (activeCard && activeCard.description) {
                         const trimmed = activeCard.description.trim();
                         if (trimmed.startsWith('<') && trimmed.endsWith('>')) {
                           window.cardDescQuill.root.innerHTML = activeCard.description;
                         } else {
                           window.cardDescQuill.root.innerHTML = parseMarkdown(activeCard.description);
                         }
                       } else {
                         window.cardDescQuill.root.innerHTML = '';
                       }
                     }
                   })">
                <style>
                  .ql-toolbar.ql-snow { border-top: none; border-left: none; border-right: none; background: #f8fafc; border-bottom: 1px solid #f1f5f9; padding: 6px 12px; }
                  .ql-container.ql-snow { border: none; font-family: inherit; font-size: 13px; color: #475569; }
                  .ql-editor { min-height: 120px; }
                  
                  html[data-theme="dark"] .ql-toolbar.ql-snow { background: #1e293b; border-bottom-color: #334155; }
                  html[data-theme="dark"] .ql-toolbar.ql-snow .ql-stroke { stroke: #cbd5e1; }
                  html[data-theme="dark"] .ql-toolbar.ql-snow .ql-fill { fill: #cbd5e1; }
                  html[data-theme="dark"] .ql-toolbar.ql-snow .ql-picker { color: #cbd5e1; }
                  html[data-theme="dark"] .ql-container.ql-snow { color: #e2e8f0; }
                  html[data-theme="dark"] .ql-editor.ql-blank::before { color: #64748b; }
                </style>
                <div id="card-desc-editor" class="w-full"></div>
                
                <div class="px-3 py-2 bg-slate-50/50 dark:bg-slate-800 dark:border-slate-700 border-t border-slate-100 flex items-center gap-2">
                  <button @click="isEditingDesc = false"
                          class="btn px-3 py-1.5 bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 btn-cancel-hover transition-all">
                    Cancel
                  </button>
                  <button @click="updateCardField({ description: activeCard.description }); isEditingDesc = false;"
                          class="btn btn-primary px-3 py-1.5">
                    Save Description
                  </button>
                </div>
              </div>
            </div>
          </div>

          {{-- Checklists Section --}}
          <template x-for="cl in (activeCard?.checklists ?? [])" :key="cl.id">
            <div class="flex gap-4" x-data="{ isEditingClTitle: false, editClTitle: cl.name || cl.title }">
              <span class="text-xl mt-0.5 select-none">☑</span>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-2">
                  <h3 class="font-bold text-slate-800 text-sm flex items-center gap-1.5 flex-1 mr-4">
                    <span x-show="!isEditingClTitle" x-text="cl.name || cl.title" class="truncate"></span>
                    <input x-show="isEditingClTitle" x-ref="clTitleInput" x-model="editClTitle"
                           @keydown.enter="editChecklistInline(cl, editClTitle); isEditingClTitle = false"
                           @keydown.escape="$event.preventDefault(); isEditingClTitle = false"
                           @blur="editChecklistInline(cl, editClTitle); isEditingClTitle = false"
                           class="w-full text-sm font-bold border-slate-300 rounded px-1.5 py-0.5 focus:ring-indigo-500 focus:border-indigo-500">
                  </h3>
                  <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="isEditingClTitle = true; editClTitle = cl.name || cl.title; $nextTick(() => $refs.clTitleInput.focus())" class="text-xs text-slate-400 hover:text-indigo-600 hover:underline">Edit</button>
                    <button @click="deleteChecklist(cl)" class="text-xs text-rose-500 hover:underline">Delete</button>
                  </div>
                </div>
                
                {{-- Progress Bar --}}
                <div class="flex items-center gap-3 mb-4 bg-slate-50 p-2 rounded-lg border border-slate-100/50">
                  <span class="text-[10px] font-extrabold text-slate-500 w-8 text-right"
                        x-text="(cl.items?.length ? Math.round(cl.items.filter(i=>i.is_completed).length/cl.items.length*100) : 0) + '%'"></span>
                  <div class="flex-1 bg-slate-200/60 rounded-full h-2 overflow-hidden shadow-inner">
                    <div class="bg-indigo-600 h-full rounded-full transition-all duration-75"
                         :style="'width:' + (cl.items?.length ? Math.round(cl.items.filter(i=>i.is_completed).length/cl.items.length*100) : 0) + '%'">
                    </div>
                  </div>
                </div>

                {{-- Checklist Items --}}
                <div class="space-y-1.5 mb-3">
                  <template x-for="item in (cl.items ?? [])" :key="item.id">
                    <div class="flex items-center justify-between py-1.5 group hover:bg-slate-100/80 rounded-lg px-3 transition-colors"
                         x-data="{ isEditingItemTitle: false, editItemTitle: item.title || item.content }">
                      <div class="flex items-center gap-3 flex-1 min-w-0 select-none">
                        <input type="checkbox" :checked="item.is_completed" class="rounded accent-indigo-600 w-4 h-4 border-slate-300 focus:ring-0 cursor-pointer"
                               @change="toggleChecklistItem(cl, item)">
                        <label class="flex-1 min-w-0 cursor-pointer" @dblclick="isEditingItemTitle = true; editItemTitle = item.title || item.content; $nextTick(() => $refs.itemTitleInput.focus())">
                          <span x-show="!isEditingItemTitle" class="text-xs text-slate-600 truncate block" :class="item.is_completed ? 'line-through text-slate-400 font-medium' : 'text-slate-700'"
                                x-text="item.title || item.content"></span>
                          <input x-show="isEditingItemTitle" x-ref="itemTitleInput" x-model="editItemTitle"
                                 @keydown.enter="editChecklistItemInline(cl, item, editItemTitle); isEditingItemTitle = false"
                                 @keydown.escape="$event.preventDefault(); isEditingItemTitle = false"
                                 @blur="editChecklistItemInline(cl, item, editItemTitle); isEditingItemTitle = false"
                                 class="w-full text-xs border-slate-300 rounded px-1.5 py-0.5 focus:ring-indigo-500 focus:border-indigo-500">
                        </label>
                      </div>
                      <div class="flex items-center gap-1  group-hover: transition-opacity">
                        <button @click="isEditingItemTitle = true; editItemTitle = item.title || item.content; $nextTick(() => $refs.itemTitleInput.focus())"
                                class="text-[10px] text-slate-400 hover:text-indigo-600 p-1">
                          ✏️
                        </button>
                        <button @click="deleteChecklistItem(cl, item)"
                                class="text-[10px] text-slate-400 hover:text-rose-600 p-1">
                          🗑
                        </button>
                      </div>
                    </div>
                  </template>
                </div>

                {{-- Add Checklist Item Form --}}
                <button @click="addChecklistItem(cl)"
                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1.5 px-3 py-1.5 hover:bg-indigo-50/50 rounded-lg transition-colors">
                  <span>+</span> Add checklist item
                </button>
              </div>
            </div>
          </template>

          {{-- Attachments Uploads & Web Links Grid --}}
          <div class="flex gap-4">
            <span class="text-xl mt-0.5 select-none">📎</span>
            <div class="flex-1">
              <h3 class="font-bold text-slate-800 text-sm mb-3">Attachments</h3>
              
              {{-- Files Card Grid --}}
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                <template x-for="f in (activeCard?.files ?? [])" :key="f.id">
                  <div class="flex items-center gap-3 p-3 bg-white border border-slate-200 rounded-xl hover:border-[#2F68ED]/40 hover:shadow-lg hover:shadow-blue-500/10 transition-all group">
                    <button type="button"
                            x-show="f.is_image"
                            @click.stop="previewAttachment(f)"
                            class="h-12 w-12 overflow-hidden rounded-xl border border-slate-200 bg-slate-50 shadow-sm flex-shrink-0">
                      <img :src="f.preview_url || f.url" :alt="f.original_name" class="h-full w-full object-cover">
                    </button>
                    <button type="button"
                            x-show="f.is_video && !f.is_image"
                            @click.stop="openVideoPreview(f)"
                            class="w-12 h-12 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 flex-shrink-0 select-none hover:bg-indigo-100 transition">
                      <span class="text-xl">🎥</span>
                    </button>
                    <a x-show="!f.is_image && !f.is_video"
                       :href="f.disk === 'url' ? f.url : (f.preview_url || f.url)"
                       target="_blank"
                       rel="noopener"
                       class="w-12 h-12 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 flex-shrink-0 select-none hover:bg-indigo-100 transition">
                      <span x-show="f.disk === 'url'">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                      </span>
                      <span x-show="f.disk !== 'url'" x-text="amFileIcon(f)"></span>
                    </a>
                    <div class="flex-1 min-w-0">
                      <button type="button"
                              x-show="f.is_video"
                              @click.stop="openVideoPreview(f)"
                              class="text-xs font-bold text-slate-700 truncate block hover:text-[#2F68ED] text-left w-full"
                              x-text="f.original_name"></button>
                      <a x-show="!f.is_video"
                         :href="f.disk === 'url' ? f.url : (f.preview_url || f.url)"
                         target="_blank"
                         rel="noopener"
                         class="text-xs font-bold text-slate-700 truncate block hover:text-[#2F68ED]"
                         x-text="f.original_name"></a>
                      <p class="text-[10px] text-slate-400 mt-0.5" x-text="f.disk === 'url' ? 'External link' : f.formatted_size"></p>
                      
                      {{-- Video Actions --}}
                      <template x-if="f.is_video">
                        <div class="flex items-center gap-2 mt-1.5">
                          <button type="button"
                                  @click.stop="openVideoPreview(f)"
                                  class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-white bg-[#2F68ED] hover:bg-[#1a51d4] rounded-lg transition shadow-sm">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M6.3 2.841A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.269l9.33-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                            </svg>
                            Play Video
                          </button>
                          <a :href="f.url"
                             target="_blank"
                             rel="noopener"
                             class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition border border-slate-200">
                            <span x-text="f.url.includes('drive.google.com') ? 'View in Google Drive' : 'View Original'"></span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                            </svg>
                          </a>
                        </div>
                      </template>
                    </div>
                    <div class="flex items-center gap-2 pr-1  group-hover: transition-opacity">
                      <button type="button"
                              x-show="f.is_image"
                              @click.stop="previewAttachment(f)"
                              title="View Full Screen"
                              class="p-2 text-[#2F68ED] hover:bg-blue-50 rounded-lg transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" /></svg>
                      </button>
                      <button type="button"
                              x-show="f.disk !== 'url'"
                              @click.stop="downloadAttachment(f)"
                              title="Download"
                              class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 12 12 16.5m0 0 4.5-4.5M12 16.5V3"/></svg>
                      </button>
                      <button @click="deleteAttachment(f)" title="Remove" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916"/></svg>
                      </button>
                    </div>
                  </div>
                </template>
                <template x-if="!activeCard?.files?.length">
                  <div class="sm:col-span-2 py-4 bg-slate-50 border border-slate-100 border-dashed rounded-xl flex items-center justify-center">
                    <p class="text-xs text-slate-400 italic">No attachments or links attached yet.</p>
                  </div>
                </template>
              </div>
            </div>
          </div>

          {{-- Comments Section --}}
          <div class="flex gap-4">
            <span class="text-xl mt-0.5 select-none">💬</span>
            <div class="flex-1">
              <h3 class="font-bold text-slate-800 text-sm mb-3">Comments</h3>
              
              {{-- New Comment Box --}}
              <div class="flex gap-3 mb-6">
                <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}"
                     class="w-8 h-8 rounded-full object-cover border border-slate-200 shadow-sm flex-shrink-0 select-none mt-1">
                <div class="flex-1" x-data="{ commentFocused: false }">
                  {{-- Paste image preview --}}
                  <template x-if="pastedImage">
                    <div class="relative mb-2 rounded-xl overflow-hidden border border-slate-200 bg-slate-50 inline-block max-w-full">
                      <img :src="pastedImage" class="max-h-36 max-w-sm rounded-xl object-contain block">
                      <button @click.stop="pastedImage = null"
                              class="absolute top-1.5 right-1.5 bg-slate-800/70 text-white rounded-full w-5 h-5 flex items-center justify-center text-[10px] hover:bg-rose-600 transition-colors leading-none">✕</button>
                      <span class="absolute bottom-1.5 left-1.5 bg-slate-800/60 text-white text-[9px] px-2 py-0.5 rounded-full font-semibold">📷 Ready to share</span>
                    </div>
                  </template>

                  <div class="relative">
                    <textarea x-ref="commentInput" x-model="newComment" rows="2" placeholder="Write a comment… or paste a screenshot (⌘+V)"
                              class="w-full p-3 pr-10 text-xs bg-white border border-slate-200 focus:bg-white rounded-2xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 focus:outline-none transition-all shadow-sm resize-none kanban-comment-bubble kanban-comment-text"
                              @keydown.ctrl.enter="submitComment()"
                              @keydown.meta.enter="pastedImage ? sendScreenshot() : submitComment()"
                              @focus="commentFocused = true"
                              @blur="commentFocused = false; setTimeout(() => { if(typeof mentionState !== 'undefined') mentionState.show = false }, 200)"
                              @paste="handlePaste($event)"
                              @input="if(typeof handleCommentInput === 'function') handleCommentInput($event)"
                              @keydown="if(typeof handleCommentKeydown === 'function') handleCommentKeydown($event)"></textarea>
                    
                    {{-- Mention Autocomplete Dropdown --}}
                    <div x-show="typeof mentionState !== 'undefined' && mentionState.show" 
                         class="absolute z-50 bottom-full left-0 mb-1 w-64 bg-white border border-slate-200 shadow-xl rounded-xl overflow-hidden py-1"
                         x-cloak>
                      <template x-if="typeof mentionState !== 'undefined'">
                        <template x-for="(member, index) in mentionState.members" :key="member.id">
                          <button type="button" @click.prevent="insertMention(member)"
                                  class="w-full text-left px-3 py-2 text-xs flex items-center gap-2 hover:bg-slate-50 transition-colors"
                                  :class="{'bg-indigo-50': index === mentionState.selectedIndex}">
                            <img :src="member.avatar || member.avatar_url" class="w-5 h-5 rounded-full object-cover">
                            <span class="font-bold text-slate-700" x-text="member.name"></span>
                            <span class="text-slate-400" x-text="'@' + (member.username || member.name.replace(/\s+/g, ''))"></span>
                          </button>
                        </template>
                      </template>
                      <div x-show="typeof mentionState !== 'undefined' && mentionState.members.length === 0" class="px-3 py-2 text-xs text-slate-400 italic">
                        No members found.
                      </div>
                    </div>

                    <div class="absolute right-2.5 bottom-2.5 text-slate-300 text-[10px] select-none" x-show="!newComment.trim() && !pastedImage">
                      ⌘↵
                    </div>
                  </div>

                  <div x-show="newComment.trim() || pastedImage" class="flex items-center gap-2 mt-2">
                    <template x-if="pastedImage">
                      <button @click="sendScreenshot()" :disabled="sendingScreenshot"
                              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white rounded-xl text-xs font-semibold shadow-sm transition-all hover:shadow-md">
                        <span x-show="!sendingScreenshot">📷 Share Screenshot</span>
                        <span x-show="sendingScreenshot" class="flex items-center gap-1.5">
                          <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                          Sending…
                        </span>
                      </button>
                    </template>
                    <template x-if="!pastedImage && newComment.trim()">
                      <button @click="submitComment()"
                              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all hover:shadow-md">
                        Post Comment
                      </button>
                    </template>
                    <span class="text-[10px] text-slate-400" x-show="!pastedImage">or press ⌘↵</span>
                  </div>
                </div>
              </div>


              {{-- Unified Comments and Activity List --}}
              <div class="space-y-4" x-data="{ editingCommentId: null, editBody: '' }">
                <template x-for="item in unifiedActivities()" :key="item.id">
                  <div>
                    {{-- Render Comment --}}
                    <template x-if="item._type === 'comment'">
                      <div class="flex gap-3 group">
                        <span class="flex-shrink-0 mt-0.5">
                          <template x-if="item.user_avatar">
                            <img :src="item.user_avatar" :alt="item.user_name"
                                 @dblclick.stop="openAvatarPreview(item.original.user)"
                                 class="w-7 h-7 cursor-zoom-in rounded-full object-cover flex-shrink-0 border border-slate-200 shadow-sm">
                          </template>
                          <template x-if="!item.user_avatar">
                            <span class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-black text-white border border-slate-200 shadow-sm"
                                  :style="avatarStyle(item.original.user || { name: 'User' })"
                                  x-text="item.user_initials"></span>
                          </template>
                        </span>
                        <div class="flex-1 min-w-0">
                          <div class="flex items-center gap-2 mb-1">
                            <p class="text-[11px] font-bold text-slate-700" x-text="item.user_name"></p>
                            <span class="text-[10px] text-slate-400" x-text="item.time_ago"></span>
                          </div>
                          
                          <template x-if="editingCommentId !== item.original.id">
                            <div>
                              <div class="bg-white border border-slate-200 rounded-2xl px-3.5 py-2.5 shadow-sm block w-full kanban-comment-bubble">
                                <div class="text-xs text-slate-700 leading-relaxed space-y-2 kanban-comment-text" x-html="typeof parseCommentBody === 'function' ? parseCommentBody(item.content) : item.content" @click="handleCommentClick($event)"></div>
                              </div>
                              
                              <div class="mt-1 flex flex-wrap items-center gap-2">
                                <div class="flex flex-wrap items-center gap-1">
                                  <template x-for="reaction in getReactionGroups(item.reactions)" :key="reaction.emoji">
                                    <button @click="toggleReaction(item.original.id, reaction.emoji)"
                                            :class="{'bg-indigo-50 border-indigo-200': reaction.hasReacted, 'bg-slate-50 border-slate-200': !reaction.hasReacted}"
                                            class="flex items-center gap-1 px-1.5 py-0.5 rounded-full border text-[11px] hover:bg-slate-100 transition-transform duration-200 hover:scale-105 active:scale-95"
                                            :title="reaction.users.join(', ')">
                                      <span x-text="reaction.emoji"></span>
                                      <span class="font-medium" :class="{'text-indigo-600': reaction.hasReacted, 'text-slate-500': !reaction.hasReacted}" x-text="reaction.count"></span>
                                    </button>
                                  </template>
                                </div>
                                
                                <div class="flex items-center gap-2 text-slate-500">
                                  <div class="relative" x-data="{ openReact: false }">
                                    <button @click="openReact = !openReact" @click.away="openReact = false" class="flex items-center justify-center w-5 h-5 rounded-full hover:bg-slate-200 text-slate-400 hover:text-slate-600 transition-colors">
                                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm3.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Z" /></svg>
                                    </button>
                                    <div x-show="openReact" x-transition class="absolute left-0 top-full mt-1 bg-white border border-slate-200 shadow-xl rounded-lg p-2 z-50 flex items-center gap-1">
                                      <template x-for="emoji in ['👍', '❤️', '😂', '😮', '😢', '🔥', '👀', '✍️']" :key="emoji">
                                        <button @click="toggleReaction(item.original.id, emoji); openReact = false" class="w-7 h-7 rounded hover:bg-slate-100 flex items-center justify-center text-base transition-transform duration-200 hover:scale-125 active:scale-90">
                                          <span x-text="emoji"></span>
                                        </button>
                                      </template>
                                    </div>
                                  </div>
                                  <span class="text-slate-300 text-[10px]">•</span>
                                  <button @click="newComment = (newComment ? newComment.trim() + ' ' : '') + '@' + (item.original?.user?.username || item.user_name.replace(/\s+/g, '')) + ' '; $refs.commentInput.focus()" class="text-[11px] font-medium underline-offset-2 hover:underline">Reply</button>
                                  
                                  <template x-if="item.user_id === {{ auth()->id() }}">
                                    <span class="text-slate-300 text-[10px]">•</span>
                                  </template>
                                  <template x-if="item.user_id === {{ auth()->id() }}">
                                    <button @click="editingCommentId = item.original.id; editBody = item.content" class="text-[11px] font-medium underline-offset-2 hover:underline">Edit</button>
                                  </template>
                                  
                                  <template x-if="item.user_id === {{ auth()->id() }} || '{{ auth()->user()->hasAnyRole(['super-admin', 'admin-digital']) }}' === '1'">
                                    <span class="text-slate-300 text-[10px]">•</span>
                                  </template>
                                  <template x-if="item.user_id === {{ auth()->id() }} || '{{ auth()->user()->hasAnyRole(['super-admin', 'admin-digital']) }}' === '1'">
                                    <button @click="deleteComment(item.original.id)" class="text-[11px] font-medium underline-offset-2 hover:underline">Delete</button>
                                  </template>
                                </div>
                              </div>
                            </div>
                          </template>

                          <template x-if="editingCommentId === item.original.id">
                            <div class="mt-1">
                              <textarea x-model="editBody" rows="2" class="w-full p-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 focus:outline-none" @keydown="if(typeof handleCommentKeydown === 'function') handleCommentKeydown($event)"></textarea>
                              <div class="flex gap-2 mt-2">
                                <button @click="updateComment(item.original.id, editBody); editingCommentId = null" class="px-3 py-1 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700">Save</button>
                                <button @click="editingCommentId = null" class="px-3 py-1 bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 rounded-lg text-xs font-semibold btn-cancel-hover transition-all">Cancel</button>
                              </div>
                            </div>
                          </template>
                        </div>
                      </div>
                    </template>

                    {{-- Render Activity Log --}}
                    <template x-if="item._type === 'activity'">
                      <div class="flex items-start gap-3 text-xs text-slate-500 mb-2">
                        <img :src="item.user_avatar || window.dgtInitialsAvatar(item.user_name || 'System', item.user_avatar_color || '#64748b')"
                             @dblclick.stop="openAvatarPreview(item.user_avatar || window.dgtInitialsAvatar(item.user_name || 'System', item.user_avatar_color || '#64748b'), item.user_name || 'User')"
                             class="w-5 h-5 cursor-zoom-in rounded-full object-cover border border-slate-100/50 flex-shrink-0 mt-0.5">
                        <div class="flex-1">
                          <span class="font-bold text-slate-700" x-text="item.user_name"></span>
                          <span x-html="parseMarkdown(item.description)" class="text-slate-600 text-[11px] [&_strong]:text-slate-800 [&_strong]:font-semibold [&_strong]:not-italic"></span>
                          <span class="text-[10px] text-slate-400 font-medium ml-1" x-text="item.time_ago"></span>
                        </div>
                      </div>
                    </template>
                  </div>
                </template>
              </div>
            </div>
          </div>



        </div>

        {{-- Right Column: Actions sidebar --}}
        <div class="space-y-4">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider select-none">Add to Card</p>
          
          {{-- SMM Specific Actions --}}
          <template x-if="board?.name?.toLowerCase().includes('smm') || activeCard?.smm_class_label || activeCard?.smm_cluster_label || activeCard?.content_public_date || activeCard?.labels?.some(l => l.name.toLowerCase() === 'smm')">
            <div>
              {{-- Assign By Dropdown --}}
              <div class="relative mb-2">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1 ml-1 select-none">Assign By</p>
                <button @click="openMemberPicker(activeCard, 'assignBy')"
                        class="btn btn-secondary group w-full text-xs text-left justify-start gap-2 py-2 px-3 flex items-center shadow-sm transition-all rounded-xl border border-slate-200 bg-white">
                  <span class="text-sm">👤</span>
                  <span class="font-semibold text-slate-700 group-hover:text-white transition-colors" x-text="activeCard?.creator?.name || 'Select Assign By...'"></span>
                </button>
              </div>
            </div>
          </template>

          {{-- Standard Actions --}}
          <div class="flex flex-col gap-3">
              {{-- Members Action --}}
              <button @click="openMemberPicker(activeCard)" data-ctx-panel="members"
                      class="btn btn-secondary group w-full text-xs text-left justify-start gap-2 py-2.5 px-3 flex items-center shadow-sm transition-all rounded-xl relative">
                <span class="text-sm text-slate-500 group-hover:text-white/70 transition-colors">👤</span>
                <span class="font-semibold group-hover:text-white transition-colors">Members</span>
                <template x-if="activeCard?.assignees?.length">
                  <span class="ml-auto bg-indigo-100 text-indigo-700 group-hover:bg-white/20 group-hover:text-white text-[10px] font-extrabold px-1.5 py-0.5 rounded-md transition-colors" x-text="activeCard.assignees.length"></span>
                </template>
              </button>
              {{-- Labels Dropdown Action --}}
              <div class="relative" x-data="{ open: false, search: '' }">
                <button @click="open = !open; search = ''; $nextTick(() => { if(open) $refs.labelSearch.focus() })" data-ctx-panel="labels"
                      class="btn btn-secondary w-full text-xs text-left justify-start gap-2 py-2.5 px-3 flex items-center shadow-sm transition-all rounded-xl">
                  <span class="text-sm">🏷</span>
                  <span class="font-semibold">Labels</span>
                </button>
                <div x-show="open" @click.outside="open = false" x-cloak
                     class="absolute right-0 top-11 w-56 bg-white border border-slate-200 rounded-2xl shadow-xl z-50 py-1.5"
                     x-transition:enter="transition ease-out duration-75"
                     x-transition:enter-start=" scale-95"
                     x-transition:enter-end=" scale-100">
                  <p class="text-[10px] uppercase font-bold text-slate-400 px-3 py-1.5 border-b border-slate-100 select-none">Card Labels</p>
                  
                  <div class="px-2 py-1.5 border-b border-slate-100">
                      <input type="text" x-model="search" x-ref="labelSearch"
                             placeholder="Search labels..." 
                             class="form-input w-full text-xs py-1.5 px-2 rounded-lg border-slate-200 shadow-inner">
                  </div>

                  <div class="max-h-48 overflow-y-auto">
                    <template x-for="lbl in labels.filter(l => l.name.toLowerCase().includes(search.toLowerCase()))" :key="lbl.id">
                      <button @click="toggleLabel(lbl.id)"
                              class="w-full flex items-center justify-between px-3 py-2.5 text-xs text-left hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                        <div class="flex items-center gap-2">
                          <span class="w-3.5 h-3.5 rounded-full border border-slate-200 shadow-sm" :style="'background:'+lbl.color"></span>
                          <span class="font-medium text-slate-700" x-text="lbl.name"></span>
                        </div>
                        <template x-if="activeCard?.labels?.find(l => l.id === lbl.id)">
                          <span class="text-indigo-600 font-extrabold text-xs">✓</span>
                        </template>
                      </button>
                    </template>
                  </div>

                  {{-- Create Label --}}
                  <div x-show="labels.filter(l => l.name.toLowerCase().includes(search.toLowerCase())).length === 0 && search.trim() !== ''" class="px-3 py-2 text-center border-t border-slate-100 mt-1">
                      <p class="text-[10px] text-slate-500 mb-1.5">No matching labels found.</p>
                      <button @click="createNewBoardLabel(search)" class="btn btn-primary w-full text-[10px] py-1.5 font-bold rounded shadow-sm">
                          Create "<span x-text="search"></span>"
                      </button>
                  </div>
                  </div>
                </div>
              </div>

              {{-- SMM Class Dropdown Action --}}
              <template x-if="board?.name?.toLowerCase().includes('smm') || activeCard?.smm_class_label || activeCard?.smm_cluster_label || activeCard?.content_public_date || activeCard?.labels?.some(l => l.name.toLowerCase() === 'smm')">
                <div class="relative" x-data="{ open: false, search: '' }">
                  <button @click="open = !open; search = ''; $nextTick(() => { if(open) $refs.smmClassSearch.focus() })"
                        class="btn btn-secondary w-full text-xs text-left justify-start gap-2 py-2.5 px-3 flex items-center shadow-sm transition-all rounded-xl">
                    <span class="text-sm">🏷</span>
                    <span class="font-semibold">SMM Class</span>
                  </button>
                  <div x-show="open" @click.outside="open = false" x-cloak
                       class="absolute right-0 top-11 w-56 bg-white border border-slate-200 rounded-2xl shadow-xl z-50 py-1.5"
                       x-transition:enter="transition ease-out duration-75"
                       x-transition:enter-start=" scale-95"
                       x-transition:enter-end=" scale-100">
                    <p class="text-[10px] uppercase font-bold text-slate-400 px-3 py-1.5 border-b border-slate-100 select-none">SMM Class</p>
                    
                    <div class="px-2 py-1.5 border-b border-slate-100">
                        <input type="text" x-model="search" x-ref="smmClassSearch"
                               placeholder="Search classes..." 
                               class="form-input w-full text-xs py-1.5 px-2 rounded-lg border-slate-200 shadow-inner">
                    </div>

                    <div class="max-h-48 overflow-y-auto">
                      <template x-for="cls in (smmClasses || []).filter(c => c.name.toLowerCase().includes(search.toLowerCase()))" :key="cls.id">
                        <button @click="toggleSmmClass(cls.name); open = false"
                                class="w-full flex items-center justify-between px-3 py-2.5 text-xs text-left hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                          <div class="flex items-center gap-2">
                            <span class="w-3.5 h-3.5 rounded-full border border-slate-200 shadow-sm" :style="'background:' + (cls.color || '#e2e8f0')"></span>
                            <span class="font-medium text-slate-700" x-text="cls.name"></span>
                          </div>
                          <template x-if="activeCard?.smm_class_label === cls.name">
                            <span class="text-indigo-600 font-extrabold text-xs">✓</span>
                          </template>
                        </button>
                      </template>
                    </div>

                    {{-- Create SMM Class --}}
                    <div x-show="(smmClasses || []).filter(c => c.name.toLowerCase().includes(search.toLowerCase())).length === 0 && search.trim() !== ''" class="px-3 py-2 text-center border-t border-slate-100 mt-1">
                        <p class="text-[10px] text-slate-500 mb-1.5">No matching classes found.</p>
                        <button @click="createSmmClass(search); open = false" class="btn btn-primary w-full text-[10px] py-1.5 font-bold rounded shadow-sm">
                            Create "<span x-text="search"></span>"
                        </button>
                    </div>
                  </div>
                </div>
              </template>

          {{-- Dates Action --}}
          <button type="button" @click="openDatePicker(activeCard)" data-ctx-panel="dates"
                  class="btn btn-secondary w-full text-xs text-left justify-start gap-2 py-2.5 px-3 flex items-center shadow-sm transition-all rounded-xl relative">
            <span class="text-sm">📅</span>
            <span class="font-semibold">Dates</span>
            <template x-if="activeCard?.due_at || activeCard?.start_date">
              <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-amber-400"></span>
            </template>
          </button>

          {{-- Attachment Action --}}
          <button type="button" @click="openAttachmentModal(activeCard)"
                  class="btn btn-secondary group w-full text-xs text-left justify-start gap-2 py-2.5 px-3 flex items-center shadow-sm transition-all rounded-xl relative">
            <span class="text-sm text-slate-500 group-hover:text-white/70 transition-colors">📎</span>
            <span class="font-semibold group-hover:text-white transition-colors">Attachment</span>
            <template x-if="activeCard?.files?.length">
              <span class="ml-auto bg-indigo-100 text-indigo-700 group-hover:bg-white/20 group-hover:text-white text-[10px] font-extrabold px-1.5 py-0.5 rounded-md transition-colors" x-text="activeCard.files.length"></span>
            </template>
          </button>

          {{-- Public Date Action --}}
          <template x-if="board?.name?.toLowerCase().includes('smm') || activeCard?.smm_class_label || activeCard?.smm_cluster_label || activeCard?.content_public_date || activeCard?.labels?.some(l => l.name.toLowerCase() === 'smm')">
            <div class="relative w-full">
              <input type="date"
                     x-ref="publicDateInput"
                     class="absolute w-0 h-0 opacity-0 pointer-events-none"
                     :value="formatInputDate(activeCard?.content_public_date)"
                     @change="updatePublicDate(activeCard, $event.target.value)">
              <button type="button" @click="$refs.publicDateInput.showPicker ? $refs.publicDateInput.showPicker() : $refs.publicDateInput.click()"
                      class="btn btn-secondary w-full text-xs text-left justify-start gap-2 py-2.5 px-3 flex items-center shadow-sm transition-all rounded-xl relative">
                <span class="text-sm">📅</span>
                <span class="font-semibold" x-text="activeCard?.content_public_date ? 'Public: ' + formatDateShort(activeCard.content_public_date) : 'Public Date'"></span>
              </button>
            </div>
          </template>

          {{-- Checklist Action --}}
          <button type="button" @click="addChecklist()" class="btn btn-secondary w-full text-xs text-left justify-start gap-2 py-2.5 px-3 flex items-center shadow-sm transition-all rounded-xl">
            <span class="text-sm">☑</span>
            <span class="font-semibold">Checklist</span>
          </button>

          <hr class="border-slate-200/80">

          {{-- Card Options (Bottom as row) --}}
          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider select-none mb-2">Card Options</p>
            <button @click="openCardTransferModal('copy', activeCard, lists.find(l => l.id === activeCard.board_list_id))" type="button" class="w-full bg-white text-indigo-600 border border-indigo-200/60 rounded-xl text-xs justify-center gap-2 py-2.5 px-3 flex items-center hover:bg-indigo-50 hover:border-indigo-300 dark:bg-slate-800 dark:border-slate-700 dark:hover:bg-indigo-600 dark:hover:text-white dark:hover:border-indigo-600 transition-all mb-2 shadow-sm">
              <span class="text-[11px]">📄</span>
              <span class="font-bold">Copy / duplicate</span>
            </button>
            <div class="flex flex-row gap-2 w-full">
              <button @click="archiveCard()" type="button" class="bg-white text-amber-600 border border-amber-200/60 rounded-xl text-xs flex-1 flex items-center justify-center gap-1.5 py-2 shadow-sm hover:bg-amber-500 hover:text-white hover:border-amber-500 dark:bg-slate-800 dark:border-slate-700 dark:hover:bg-indigo-600 dark:hover:text-white dark:hover:border-indigo-600 transition-all">
                <span class="text-[11px]">📦</span>
                <span class="font-bold">Archive</span>
              </button>
              <button @click="deleteCard()" type="button" class="bg-white hover:bg-rose-600 hover:text-white text-rose-500 border border-rose-200/60 hover:border-rose-600 rounded-xl text-xs flex-1 flex items-center justify-center gap-1.5 py-2 shadow-sm hover:shadow dark:bg-slate-800 dark:border-slate-700 btn-cancel-hover transition-all">
                <span class="text-[11px]">🗑</span>
                <span class="font-bold">Delete</span>
              </button>
            </div>
            <button @click="closeCard()" type="button" class="w-full bg-white text-slate-600 border border-slate-200 rounded-xl text-xs justify-center gap-2 py-2 px-3 flex items-center hover:bg-slate-100 transition-all mt-2 shadow-sm dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 btn-cancel-hover">
              <span class="text-sm">✕</span>
              <span class="font-semibold">Close Card</span>
            </button>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
