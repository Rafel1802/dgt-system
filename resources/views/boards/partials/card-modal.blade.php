{{-- Upgraded Card Detail Modal (Phase 2 Trello features) --}}
<div x-show="activeCard !== null" x-cloak
     class="fixed inset-0"
     style="z-index: 70;"
     @keydown.escape.window="if(activeCard !== null && !imagePreview.open && !attachmentModal?.open && !exportModal?.open && !switchBoardsModal?.open && !importModal?.open && !cardTransferModal?.open && !videoPreview?.open && !canvaPreview?.open) { $event.preventDefault(); closeCard(); }"
     @keydown.window="handleCardModalKeydown($event)">

  {{-- Fixed Fullscreen Backdrop with Blur --}}
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"
       @click="closeCard()"
       aria-hidden="true"></div>

  {{-- Floating Prev Button (Fixed on screen - Card Above) --}}
  <button type="button"
          @click.stop="prevCard()"
          :disabled="!hasPrevCard()"
          x-show="activeCard"
          :class="hasPrevCard() 
            ? 'bg-white/95 hover:bg-white text-slate-700 hover:text-indigo-600 shadow-2xl border-slate-200/80 hover:border-indigo-300 active:scale-95 cursor-pointer opacity-95 hover:opacity-100 dark:bg-slate-800/95 dark:hover:bg-slate-800 dark:text-slate-200 dark:hover:text-indigo-400 dark:border-slate-700' 
            : 'bg-white/30 text-slate-400/50 border-white/20 opacity-20 cursor-not-allowed pointer-events-none dark:bg-slate-800/30 dark:text-slate-600 dark:border-slate-800'"
          class="fixed left-3 sm:left-6 lg:left-8 xl:left-14 top-1/2 -translate-y-1/2 z-[85] transition-colors duration-150 flex flex-col items-center justify-center w-12 h-12 lg:w-14 lg:h-14 rounded-2xl border backdrop-blur-md shadow-2xl group focus:outline-none select-none card-modal-prev-btn"
          :aria-label="getPrevCard() ? (getActiveCardIndex() === 0 ? 'Previous (Wrap to last card): ' + getPrevCard().title : 'Previous: ' + getPrevCard().title) : 'Previous card'">
    <svg class="w-6 h-6 lg:w-7 lg:h-7 transition-transform duration-150 group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
    </svg>
    <span class="text-[9px] font-black uppercase tracking-wider -mt-1 hidden lg:block text-slate-500 group-hover:text-indigo-600 dark:text-slate-400 dark:group-hover:text-indigo-400">Prev</span>

    {{-- Hover Tooltip Preview (Persistent node, single smooth fade) --}}
    <div x-show="hasPrevCard() && getPrevCard()"
         class="absolute left-full ml-3 top-1/2 -translate-y-1/2 bg-slate-900/95 text-white p-2.5 rounded-xl shadow-2xl pointer-events-none whitespace-normal opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-[80] hidden md:block w-52 text-left border border-slate-700/60">
      <div class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-indigo-400 mb-0.5">
        <span x-text="getActiveCardIndex() === 0 ? '⤾ Previous (Last Card in List)' : '↑ Previous Card (Above)'"></span>
      </div>
      <div class="text-xs font-semibold text-slate-100 line-clamp-2 leading-snug" x-text="getPrevCard() ? getPrevCard().title : ''"></div>
      <div class="mt-1 text-[10px] text-slate-400 flex items-center justify-between">
        <span x-text="'Card ' + (getActiveCardIndex() === 0 ? totalCardsInActiveList() : getActiveCardIndex()) + ' of ' + totalCardsInActiveList()"></span>
        <span class="font-mono text-[9px] bg-slate-800 px-1 py-0.5 rounded border border-slate-700">← / ↑</span>
      </div>
    </div>
  </button>

  {{-- Floating Next Button (Right Side - Card Below) --}}
  <button type="button"
          @click.stop="nextCard()"
          :disabled="!hasNextCard()"
          x-show="activeCard"
          :class="hasNextCard() 
            ? 'bg-white/95 hover:bg-white text-slate-700 hover:text-indigo-600 shadow-2xl border-slate-200/80 hover:border-indigo-300 active:scale-95 cursor-pointer opacity-95 hover:opacity-100 dark:bg-slate-800/95 dark:hover:bg-slate-800 dark:text-slate-200 dark:hover:text-indigo-400 dark:border-slate-700' 
            : 'bg-white/30 text-slate-400/50 border-white/20 opacity-20 cursor-not-allowed pointer-events-none dark:bg-slate-800/30 dark:text-slate-600 dark:border-slate-800'"
          class="fixed right-3 sm:right-6 lg:right-8 xl:right-14 top-1/2 -translate-y-1/2 z-[85] transition-colors duration-150 flex flex-col items-center justify-center w-12 h-12 lg:w-14 lg:h-14 rounded-2xl border backdrop-blur-md shadow-2xl group focus:outline-none select-none card-modal-next-btn"
          :aria-label="getNextCard() ? (getActiveCardIndex() === totalCardsInActiveList() - 1 ? 'Next (Wrap to first card): ' + getNextCard().title : 'Next: ' + getNextCard().title) : 'Next card'">
    <svg class="w-6 h-6 lg:w-7 lg:h-7 transition-transform duration-150 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
    </svg>
    <span class="text-[9px] font-black uppercase tracking-wider -mt-1 hidden lg:block text-slate-500 group-hover:text-indigo-600 dark:text-slate-400 dark:group-hover:text-indigo-400">Next</span>

    {{-- Hover Tooltip Preview (Persistent node, single smooth fade) --}}
    <div x-show="hasNextCard() && getNextCard()"
         class="absolute right-full mr-3 top-1/2 -translate-y-1/2 bg-slate-900/95 text-white p-2.5 rounded-xl shadow-2xl pointer-events-none whitespace-normal opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-[90] hidden md:block w-52 text-left border border-slate-700/60">
      <div class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-indigo-400 mb-0.5">
        <span x-text="getActiveCardIndex() === totalCardsInActiveList() - 1 ? '⤿ Next (First Card in List)' : '↓ Next Card (Below)'"></span>
      </div>
      <div class="text-xs font-semibold text-slate-100 line-clamp-2 leading-snug" x-text="getNextCard() ? getNextCard().title : ''"></div>
      <div class="mt-1 text-[10px] text-slate-400 flex items-center justify-between">
        <span x-text="'Card ' + (getActiveCardIndex() === totalCardsInActiveList() - 1 ? 1 : getActiveCardIndex() + 2) + ' of ' + totalCardsInActiveList()"></span>
        <span class="font-mono text-[9px] bg-slate-800 px-1 py-0.5 rounded border border-slate-700">→ / ↓</span>
      </div>
    </div>
  </button>

  {{-- Scrollable Container for Card Modal Box --}}
  <div id="card-modal-scroll-container"
       class="fixed inset-0 flex items-start justify-center p-4 pt-16 pb-32 lg:pb-16 overflow-y-auto z-[75]"
       @click.self="closeCard()">

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
          <div class="flex items-center gap-1.5 flex-shrink-0 mt-1">
            {{-- Order Counter: e.g. 5 / 40 --}}
            <div x-show="totalCardsInActiveList() > 0" class="flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 text-[11px] font-bold text-slate-600 dark:text-slate-300 select-none mr-0.5">
              <span x-text="(getActiveCardIndex() + 1)"></span>
              <span class="text-slate-400 font-normal">/</span>
              <span x-text="totalCardsInActiveList()"></span>
            </div>

            {{-- Prev Button (Above) --}}
            <button type="button"
                    @click="prevCard()"
                    :disabled="!hasPrevCard()"
                    :class="hasPrevCard() ? 'text-slate-600 hover:text-indigo-600 hover:bg-slate-200/70 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-700 cursor-pointer shadow-sm bg-white dark:bg-slate-800 border border-slate-200/70 dark:border-slate-700' : 'text-slate-300 dark:text-slate-600 opacity-40 cursor-not-allowed pointer-events-none bg-slate-50 dark:bg-slate-800/40 border border-transparent'"
                    class="p-1.5 rounded-lg transition-all flex items-center justify-center"
                    :title="getPrevCard() ? (getActiveCardIndex() === 0 ? 'Previous card (wrap to last): ' + getPrevCard().title + ' [← / ↑]' : 'Previous card (above): ' + getPrevCard().title + ' [← / ↑]') : 'Only 1 card in this list'">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
              </svg>
            </button>

            {{-- Next Button (Below) --}}
            <button type="button"
                    @click="nextCard()"
                    :disabled="!hasNextCard()"
                    :class="hasNextCard() ? 'text-slate-600 hover:text-indigo-600 hover:bg-slate-200/70 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-700 cursor-pointer shadow-sm bg-white dark:bg-slate-800 border border-slate-200/70 dark:border-slate-700' : 'text-slate-300 dark:text-slate-600 opacity-40 cursor-not-allowed pointer-events-none bg-slate-50 dark:bg-slate-800/40 border border-transparent'"
                    class="p-1.5 rounded-lg transition-all flex items-center justify-center"
                    :title="getNextCard() ? (getActiveCardIndex() === totalCardsInActiveList() - 1 ? 'Next card (wrap to first): ' + getNextCard().title + ' [→ / ↓]' : 'Next card (below): ' + getNextCard().title + ' [→ / ↓]') : 'Only 1 card in this list'">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
              </svg>
            </button>

            <div class="h-4 w-px bg-slate-200 dark:bg-slate-700 mx-1"></div>

            {{-- Close Button --}}
            <button @click="closeCard()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 hover:bg-slate-200/50 dark:hover:bg-slate-700/50 rounded-full transition-colors" title="Close (Esc)">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>

        {{-- Meta Badges Row --}}
        <div class="flex flex-wrap gap-6 mt-5 text-xs" style="padding-left: 40px;">
          {{-- SMM Specific: Class & Content Type & Public Date --}}
          <template x-if="isSmmCard(activeCard)">
            <div class="flex flex-col gap-6 text-xs w-full">
              {{-- CLASSIFICATION --}}
              <div>
                <p class="text-[11px] font-bold text-slate-800 uppercase tracking-widest mb-3 border-b border-slate-200 pb-1">Classification</p>
                <div class="flex flex-wrap gap-6">
                  {{-- Class (SMM Class Label) --}}
                  <div class="min-w-[100px]">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1.5">Class</p>
                    <div x-show="activeCard?.smm_class_label">
                      <a x-show="activeCard?.smm_class_link" :href="activeCard?.smm_class_link" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-emerald-700 bg-emerald-50 font-bold text-[10px] shadow-sm border border-emerald-100 hover:bg-emerald-100 transition-colors cursor-pointer" title="Open External Link">
                        <span x-text="activeCard?.smm_class_label"></span>
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                      </a>
                      <span x-show="!activeCard?.smm_class_link" class="px-2.5 py-1 rounded-md text-emerald-700 bg-emerald-50 font-bold text-[10px] shadow-sm border border-emerald-100" x-text="activeCard?.smm_class_label"></span>
                    </div>
                    <div x-show="!activeCard?.smm_class_label">
                      <span class="text-xs text-slate-400 italic">None</span>
                    </div>
                  </div>
                  {{-- Content Type (SMM Cluster Label) --}}
                  <div class="min-w-[100px]">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1.5">Content Type</p>
                    <div x-show="activeCard?.smm_cluster_label">
                      <button type="button" @click="$dispatch('open-content-type-picker')"
                              class="px-2.5 py-1 rounded-md font-bold text-[10px] shadow-sm inline-flex items-center gap-1.5 hover:opacity-90 transition-all cursor-pointer"
                              :style="getContentTypeBadgeStyle(activeCard?.smm_cluster_label)"
                              title="Click to change Content Type">
                        <span x-text="activeCard?.smm_cluster_label"></span>
                        <svg class="w-2.5 h-2.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                      </button>
                    </div>
                    <div x-show="!activeCard?.smm_cluster_label">
                      <button type="button" @click="$dispatch('open-content-type-picker')"
                              class="text-xs text-slate-400 italic hover:text-indigo-600 transition-colors cursor-pointer"
                              title="Click to select Content Type">
                        + Select
                      </button>
                    </div>
                  </div>
                  {{-- Content Public Date --}}
                  <div class="min-w-[125px]">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1.5">Content Public</p>
                    <div class="relative flex items-center">
                      <input type="date" 
                             :value="formatInputDate(activeCard?.content_public_date)" 
                             @change="updatePublicDate(activeCard, $event.target.value)" 
                             class="no-flatpickr px-2.5 py-1 rounded-md text-amber-700 bg-amber-50 font-bold text-[10px] shadow-sm border border-amber-200 focus:ring-1 focus:ring-amber-300 focus:border-amber-300 w-[125px] cursor-pointer transition-colors"
                             title="Click to edit public date" />
                      <template x-if="activeCard?.content_public_date">
                        <button type="button"
                                @click="updatePublicDate(activeCard, null)"
                                class="ml-1 text-slate-300 hover:text-rose-500 p-0.5 rounded transition-colors"
                                title="Remove public date">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                      </template>
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
                    <span class="card-member-pill flex items-center gap-1.5 bg-slate-100/50 hover:bg-slate-100 transition-colors border border-slate-200/60 rounded-full pr-2.5 pb-0.5 pt-0.5 pl-0.5">
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

            {{-- Assign By (shown here for non-SMM cards) --}}
            <template x-if="!isSmmCard(activeCard)">
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
            </template>

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
                       // If Quill was initialized but the DOM element was replaced/removed, discard the stale instance
                       if (window.cardDescQuill && (!document.body.contains(window.cardDescQuill.container) || !document.querySelector('#card-desc-editor .ql-editor'))) {
                         window.cardDescQuill = null;
                       }
                       if (!window.cardDescQuill) {
                         window.cardDescQuill = new Quill('#card-desc-editor', {
                           theme: 'snow',
                           placeholder: 'Add a more detailed description...',
                           modules: {
                             toolbar: [
                               ['bold', 'italic', 'underline', 'strike'],
                               [{ 'color': [] }, { 'background': [] }],
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

                  /* ─── Neon Blue Theme for Card Description Quill Editor ─── */
                  html[data-theme="neon"] .ql-toolbar.ql-snow { 
                    background: rgba(4, 20, 56, 0.96) !important; 
                    border-bottom: 1px solid rgba(0, 160, 255, 0.35) !important; 
                  }
                  html[data-theme="neon"] .ql-toolbar.ql-snow .ql-stroke { stroke: #7dd3fc !important; }
                  html[data-theme="neon"] .ql-toolbar.ql-snow .ql-fill { fill: #7dd3fc !important; }
                  html[data-theme="neon"] .ql-toolbar.ql-snow .ql-picker { color: #bae6fd !important; }
                  html[data-theme="neon"] .ql-toolbar.ql-snow .ql-picker-options { 
                    background-color: #030e2e !important; 
                    border: 1px solid rgba(0, 160, 255, 0.45) !important; 
                    color: #ffffff !important; 
                  }
                  html[data-theme="neon"] .ql-container.ql-snow,
                  html[data-theme="neon"] .ql-editor,
                  html[data-theme="neon"] .ql-editor p,
                  html[data-theme="neon"] .ql-editor span,
                  html[data-theme="neon"] .ql-editor div,
                  html[data-theme="neon"] .ql-editor h1,
                  html[data-theme="neon"] .ql-editor h2,
                  html[data-theme="neon"] .ql-editor h3,
                  html[data-theme="neon"] .ql-editor li { 
                    color: #ffffff !important; 
                  }
                  html[data-theme="neon"] .ql-editor.ql-blank::before { color: #7dd3fc !important; }
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

              {{-- Files Card Grid (All attachments including videos as square icons) --}}
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                <template x-for="f in (activeCard?.files ?? [])" :key="f.id">
                  <div class="col-span-1 relative w-full h-full flex flex-col">
                    {{-- Normal View --}}
                    <div x-show="attachmentModal.editingFileId !== f.id"
                         class="flex items-center gap-2.5 sm:gap-3 p-2.5 sm:p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl hover:border-[#2F68ED]/40 hover:shadow-md transition-all group h-full">
                      
                      {{-- 1. Image Thumbnail --}}
                      <button type="button"
                              x-show="f.is_image"
                              @click.stop="previewAttachment(f)"
                              class="h-12 w-12 sm:h-14 sm:w-14 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 shadow-sm flex-shrink-0">
                        <img :src="f.preview_url || f.url" :alt="f.original_name" class="h-full w-full object-cover">
                      </button>

                      {{-- 2. Video Thumbnail / Clean Video Icon --}}
                      <button type="button"
                              x-show="isVideoFile(f) && !f.is_image"
                              @click.stop="openVideoPreview(f)"
                              x-data="{ thumbLoaded: false }"
                              class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl bg-blue-50/80 dark:bg-slate-900 border border-blue-200/80 dark:border-blue-500/40 flex items-center justify-center text-[#2F68ED] dark:text-blue-400 flex-shrink-0 select-none shadow-sm group/vid-thumb transition-all relative overflow-hidden active:scale-95 cursor-pointer hover:border-[#2F68ED] hover:shadow-md"
                              title="Play video">
                        
                        {{-- Detected Video Image Thumbnail (Google Drive / YouTube / Backend) --}}
                        <template x-if="getVideoThumbnailUrl(f)">
                          <img :src="getVideoThumbnailUrl(f)"
                               :alt="f.original_name"
                               referrerpolicy="no-referrer"
                               class="w-full h-full object-cover absolute inset-0 transition-transform duration-300 group-hover/vid-thumb:scale-105"
                               loading="lazy"
                               x-show="thumbLoaded"
                               x-on:load="thumbLoaded = true"
                               x-on:error="thumbLoaded = false; $event.target.style.display='none'">
                        </template>

                        {{-- Direct Video Frame Detection (HTML5 media fragment #t=0.5) --}}
                        <template x-if="!getVideoThumbnailUrl(f) && isDirectVideoFile(f)">
                          <video :src="(f.preview_url || f.url) + '#t=0.5'"
                                 preload="metadata"
                                 muted
                                 playsinline
                                 x-on:loadeddata="thumbLoaded = true"
                                 x-show="thumbLoaded"
                                 class="w-full h-full object-cover absolute inset-0 pointer-events-none transition-transform duration-300 group-hover/vid-thumb:scale-105"></video>
                        </template>

                        {{-- Clean Video Camera Icon (Shown when no image thumbnail is loaded) --}}
                        <div x-show="!thumbLoaded" class="flex items-center justify-center w-full h-full">
                          <svg class="w-6 h-6 sm:w-7 sm:h-7 text-[#2F68ED] dark:text-blue-400 transition-transform duration-200 group-hover/vid-thumb:scale-110" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                          </svg>
                        </div>

                        {{-- Frosted Glass Play Overlay (ONLY when real image thumbnail is loaded) --}}
                        <div x-show="thumbLoaded" class="absolute inset-0 bg-black/25 group-hover/vid-thumb:bg-black/45 backdrop-blur-[0.5px] flex items-center justify-center transition-all">
                          <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-white/95 group-hover/vid-thumb:bg-[#2F68ED] text-[#2F68ED] group-hover/vid-thumb:text-white flex items-center justify-center shadow-md transform group-hover/vid-thumb:scale-110 transition-all pl-0.5">
                            <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 fill-current" viewBox="0 0 24 24">
                              <path d="M8 5v14l11-7z"/>
                            </svg>
                          </div>
                        </div>
                      </button>

                      {{-- 3. Document / Link Square Icon --}}
                      <div x-show="!f.is_image && !isVideoFile(f)" class="flex-shrink-0">
                        <template x-if="isCanvaFile(f)">
                          <button type="button"
                                  @click.stop="openCanvaPreview(f)"
                                  class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl border border-[#00c4cc]/30 bg-[#00c4cc]/10 hover:bg-[#00c4cc]/20 flex items-center justify-center text-indigo-600 dark:text-indigo-400 flex-shrink-0 select-none transition group/canva-thumb cursor-pointer shadow-xs active:scale-95"
                                  title="View Canva Design">
                            <img src="{{ asset('images/canva-icon.png') }}"
                                 alt="Canva"
                                 class="w-7 h-7 sm:w-8 sm:h-8 object-contain rounded-full shadow-xs transition-transform group-hover/canva-thumb:scale-110"
                                 x-on:error="$event.target.src='https://brandlogovector.com/wp-content/uploads/2022/02/Canva-Icon-Logo.png'">
                          </button>
                        </template>
                        <template x-if="!isCanvaFile(f)">
                          <a :href="f.disk === 'url' ? f.url : (f.preview_url || f.url)"
                             target="_blank"
                             rel="noopener"
                             class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl border border-indigo-100 dark:border-slate-700 bg-indigo-50 dark:bg-slate-800 hover:bg-indigo-100 dark:hover:bg-slate-700 flex items-center justify-center text-indigo-600 dark:text-indigo-400 flex-shrink-0 select-none transition">
                            <span x-show="f.disk === 'url' || f.is_link">
                              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                            </span>
                            <span x-show="f.disk !== 'url' && !f.is_link" x-text="amFileIcon(f)"></span>
                          </a>
                        </template>
                      </div>

                      {{-- Right Side: Title on Top, Actions on Bottom --}}
                      <div class="flex-1 min-w-0 flex flex-col justify-center gap-1.5 py-0.5">
                        {{-- Top Row: Title Name --}}
                        <div class="min-w-0">
                          <button type="button"
                                  x-show="isVideoFile(f)"
                                  @click.stop="openVideoPreview(f)"
                                  class="text-left font-bold text-xs sm:text-sm text-slate-800 dark:text-slate-100 hover:text-[#2F68ED] dark:hover:text-[#4C8BF5] transition-colors truncate block w-full"
                                  :title="f.original_name || f.url"
                                  x-text="f.original_name || 'Video attachment'">
                          </button>
                          <button type="button"
                                  x-show="!isVideoFile(f) && isCanvaFile(f)"
                                  @click.stop="openCanvaPreview(f)"
                                  class="text-left font-bold text-xs sm:text-sm text-slate-800 dark:text-slate-100 hover:text-[#00c4cc] dark:hover:text-[#00c4cc] transition-colors truncate block w-full cursor-pointer"
                                  :title="f.original_name || f.url"
                                  x-text="f.original_name || 'Canva Design'">
                          </button>
                          <a x-show="!isVideoFile(f) && !isCanvaFile(f)"
                             :href="f.disk === 'url' ? f.url : (f.preview_url || f.url)"
                             target="_blank"
                             rel="noopener"
                             class="block text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100 hover:text-[#2F68ED] dark:hover:text-[#4C8BF5] transition-colors truncate w-full"
                             :title="f.original_name || f.url"
                             x-text="f.original_name || 'Attachment'">
                          </a>
                        </div>

                        {{-- Bottom Row: Action Buttons (Strictly same row, no wrapping) --}}
                        <div class="flex items-center gap-1 sm:gap-1.5 flex-nowrap shrink-0 overflow-x-auto no-scrollbar">
                          {{-- Video: Play Button --}}
                          <button type="button"
                                  x-show="isVideoFile(f)"
                                  @click.stop="openVideoPreview(f)"
                                  title="Play video"
                                  class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold text-white bg-[#2F68ED] hover:bg-[#1a51d4] rounded-lg shadow-xs hover:shadow active:scale-95 transition-all flex-shrink-0 whitespace-nowrap cursor-pointer">
                            <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24">
                              <path d="M8 5v14l11-7z"/>
                            </svg>
                            <span>Play</span>
                          </button>

                          {{-- Video: Open Link in Browser (Google Drive / External Video) --}}
                          <button type="button"
                                  x-show="isVideoFile(f) && (f.url || f.preview_url || f.path || f.stored_name)"
                                  @click.stop="openVideoDirect(f.url || f.preview_url || f.path || f.stored_name)"
                                  :title="((f.url || f.path || '') + '').includes('drive.google.com') ? 'Open in Google Drive (browser)' : 'Open Video in browser'"
                                  class="inline-flex items-center gap-1 px-2 py-1 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-xs hover:shadow active:scale-95 transition-all flex-shrink-0 whitespace-nowrap cursor-pointer">
                            <span>Open</span>
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                            </svg>
                          </button>

                          {{-- Canva: View Button (in-system preview for web and macOS app) --}}
                          <div class="relative group/tip inline-flex items-center shrink-0" x-show="isCanvaFile(f)">
                            <button type="button"
                                    @click.stop="openCanvaPreview(f)"
                                    title="View Canva Design"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold text-white bg-gradient-to-r from-[#00c4cc] to-[#7d2ae8] hover:from-[#00b2b9] hover:to-[#6c24cb] rounded-lg shadow-xs hover:shadow active:scale-95 transition-all flex-shrink-0 whitespace-nowrap cursor-pointer">
                              <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                              </svg>
                              <span>View</span>
                            </button>
                            <div class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover/tip:flex items-center px-2 py-0.5 text-[10px] font-bold text-white bg-slate-900/95 border border-white/10 rounded-md shadow-xl whitespace-nowrap z-50">
                              View in system
                            </div>
                          </div>

                          {{-- Canva: Open Link in Browser Button --}}
                          <button type="button"
                                  x-show="isCanvaFile(f)"
                                  @click.stop="openCanvaDirect(f)"
                                  title="Open in Canva (browser)"
                                  class="inline-flex items-center gap-1 px-2 py-1 text-xs font-bold text-white bg-[#00c4cc] hover:bg-[#009da3] rounded-lg shadow-xs hover:shadow active:scale-95 transition-all flex-shrink-0 whitespace-nowrap cursor-pointer">
                            <span>Open</span>
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                            </svg>
                          </button>

                          {{-- Google Docs / Sheets / Slides: View In-System Button --}}
                          <button type="button"
                                  x-show="isGoogleDocsFile(f)"
                                  @click.stop="openGoogleDocsPreview(f)"
                                  title="View inside system"
                                  class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold text-white bg-gradient-to-r from-[#1a73e8] to-[#4285f4] hover:from-[#1557b0] hover:to-[#3367d6] rounded-lg shadow-xs hover:shadow active:scale-95 transition-all flex-shrink-0 whitespace-nowrap cursor-pointer">
                            {{-- Google Docs icon --}}
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM6 4h5v7h8v9H6V4zm2 9h8v1H8v-1zm0 2h8v1H8v-1zm0 2h5v1H8v-1z"/>
                            </svg>
                            <span x-text="getGoogleDocsType(f) === 'sheet' ? 'View Sheet' : (getGoogleDocsType(f) === 'slide' ? 'View Slides' : 'View Doc')"></span>
                          </button>

                          {{-- Google Docs: Open in Browser Button --}}
                          <button type="button"
                                  x-show="isGoogleDocsFile(f)"
                                  @click.stop="openGoogleDocsDirect(f.url || f.preview_url || f.path || f.stored_name)"
                                  title="Open in browser (full edit)"
                                  class="inline-flex items-center gap-1 px-2 py-1 text-xs font-bold text-[#1a73e8] dark:text-blue-300 bg-blue-50 dark:bg-blue-950/60 hover:bg-blue-100 dark:hover:bg-blue-900/60 border border-blue-200 dark:border-blue-700 rounded-lg shadow-xs hover:shadow active:scale-95 transition-all flex-shrink-0 whitespace-nowrap cursor-pointer">
                            <span>Open</span>
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                            </svg>
                          </button>

                          {{-- Image Full Screen Button --}}
                          <button type="button"
                                  x-show="f.is_image"
                                  @click.stop="previewAttachment(f)"
                                  title="View Full Screen"
                                  class="p-1 text-[#2F68ED] hover:bg-blue-50 dark:hover:bg-slate-800 rounded-lg transition flex-shrink-0">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" /></svg>
                          </button>

                          {{-- Download Button --}}
                          <button type="button"
                                  x-show="f.disk !== 'url'"
                                  @click.stop="downloadAttachment(f)"
                                  title="Download"
                                  class="p-1 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-slate-800 rounded-lg transition flex-shrink-0">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 12 12 16.5m0 0 4.5-4.5M12 16.5V3"/></svg>
                          </button>

                          {{-- External Link Icon (for non-Canva, non-video generic URLs) --}}
                          <div class="relative group/tip inline-flex items-center shrink-0" x-show="f.url && f.disk === 'url' && !isCanvaFile(f) && !isVideoFile(f) && !isGoogleDocsFile(f)">
                            <a :href="f.url"
                               target="_blank"
                               rel="noopener"
                               title="Open link in new tab"
                               class="p-1 text-slate-400 hover:text-cyan-500 dark:hover:text-cyan-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition flex-shrink-0 cursor-pointer">
                              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                              </svg>
                            </a>
                            <div class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover/tip:flex items-center px-2 py-0.5 text-[10px] font-bold text-white bg-slate-900/95 border border-white/10 rounded-md shadow-xl whitespace-nowrap z-50">
                              Open link in new tab
                            </div>
                          </div>

                          {{-- Edit Button --}}
                          <div class="relative group/tip inline-flex items-center shrink-0" x-show="f.disk === 'url'">
                            <button type="button"
                                    @click.stop="editAttachment(f)"
                                    title="Edit link (name or URL)"
                                    class="p-1 text-slate-400 hover:text-[#2F68ED] hover:bg-blue-50 dark:hover:bg-slate-800 rounded-lg transition cursor-pointer flex-shrink-0">
                              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                            </button>
                            <div class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover/tip:flex items-center px-2 py-0.5 text-[10px] font-bold text-white bg-slate-900/95 border border-white/10 rounded-md shadow-xl whitespace-nowrap z-50">
                              Edit link
                            </div>
                          </div>

                          {{-- Remove Button --}}
                          <div class="relative group/tip inline-flex items-center shrink-0">
                            <button @click="deleteAttachment(f)" title="Delete attachment" class="p-1 text-rose-500 hover:bg-rose-50 dark:hover:bg-slate-800 rounded-lg transition cursor-pointer flex-shrink-0">
                              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916"/></svg>
                            </button>
                            <div class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover/tip:flex items-center px-2 py-0.5 text-[10px] font-bold text-white bg-slate-900/95 border border-white/10 rounded-md shadow-xl whitespace-nowrap z-50">
                              Delete
                            </div>
                          </div>

                          {{-- Non-video file size label --}}
                          <span x-show="!isVideoFile(f) && f.disk !== 'url'" class="text-[10px] text-slate-400 ml-1 shrink-0 whitespace-nowrap" x-text="f.formatted_size || ''"></span>
                        </div>
                      </div>
                    </div>

                    {{-- Edit Mode --}}
                    <div x-show="attachmentModal.editingFileId === f.id" x-cloak class="p-3 bg-amber-50/50 dark:bg-slate-800 border border-amber-200 dark:border-amber-700/50 rounded-xl h-full flex flex-col justify-center space-y-2.5">
                      <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Name</label>
                        <input type="text" x-model="attachmentModal.editName" @keydown.enter="amSaveEdit(f)" class="w-full text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-lg px-2.5 py-2 focus:ring-2 focus:ring-amber-400/30 focus:border-amber-400 focus:outline-none transition-all">
                      </div>
                      <div x-show="f.disk === 'url'">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">URL</label>
                        <input type="url" x-model="attachmentModal.editUrl" @keydown.enter="amSaveEdit(f)" class="w-full text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-lg px-2.5 py-2 focus:ring-2 focus:ring-amber-400/30 focus:border-amber-400 focus:outline-none transition-all">
                      </div>
                      <div class="flex gap-2 pt-1">
                        <button @click="amSaveEdit(f)" :disabled="attachmentModal.editSaving" class="flex-1 text-xs font-bold py-1.5 rounded-lg bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50 transition-colors">
                          <span x-show="!attachmentModal.editSaving">Save</span>
                          <span x-show="attachmentModal.editSaving">Saving…</span>
                        </button>
                        <button @click="attachmentModal.editingFileId = null" class="flex-1 text-xs font-bold py-1.5 rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-200 hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">Cancel</button>
                      </div>
                    </div>
                  </div>
                </template>

                {{-- Empty State (No attachments or links attached yet) --}}
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
                <div class="flex-1" x-data="{ commentFocused: false, quickReplies: ['Ready', 'Team approved', 'Head approved', 'QC approved', 'QC approved SMM', 'Approved', 'Blocked'] }">
                  {{-- Paste multi-image preview --}}
                  <template x-if="(pastedImages && pastedImages.length > 0) || pastedImage">
                    <div class="mb-3 p-2.5 rounded-2xl border border-slate-200 dark:border-slate-700/60 bg-slate-50/70 dark:bg-slate-800/60">
                      <div class="flex items-center justify-between gap-2 mb-2 px-1">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                          📷 <span x-text="(pastedImages && pastedImages.length > 1) ? pastedImages.length + ' Screenshots ready' : '1 Screenshot ready'"></span>
                        </span>
                        <div class="flex items-center gap-2">
                          <span class="text-[10px] text-slate-400 font-medium">⌘+V to paste more</span>
                          <button type="button" @click.stop="clearPastedImages()" class="text-[10px] text-rose-500 hover:text-rose-700 font-bold hover:underline">Clear all</button>
                        </div>
                      </div>
                      <div class="flex flex-wrap gap-2.5 items-center">
                        <template x-for="(imgSrc, idx) in (pastedImages && pastedImages.length ? pastedImages : [pastedImage])" :key="idx">
                          <div class="relative group/shot rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 shadow-sm bg-black/5 flex-shrink-0">
                            <img :src="imgSrc" class="h-20 w-28 object-cover block">
                            <button type="button" @click.stop="removePastedImage(idx)"
                                    class="absolute top-1 right-1 bg-slate-900/80 hover:bg-rose-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-[10px] font-black transition-colors shadow-md leading-none"
                                    title="Remove this screenshot">✕</button>
                            <span class="absolute bottom-1 left-1 bg-slate-900/70 text-white text-[9px] px-1.5 py-0.5 rounded font-mono" x-text="'#' + (idx + 1)"></span>
                          </div>
                        </template>
                      </div>
                    </div>
                  </template>

                  <div class="relative">
                    <textarea x-ref="commentInput" x-model="newComment" rows="4" placeholder="Write a comment… or paste a screenshot (⌘+V)"
                              class="w-full p-3 pr-10 text-xs bg-white border border-slate-200 focus:bg-white rounded-2xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 focus:outline-none transition-all shadow-sm resize-none kanban-comment-bubble kanban-comment-text"
                              @keydown.ctrl.enter="submitComment()"
                              @keydown.meta.enter="((pastedImages && pastedImages.length > 0) || pastedImage) ? sendScreenshot() : submitComment()"
                              @focus="commentFocused = true"
                              @blur="commentFocused = false; setTimeout(() => { if(typeof mentionState !== 'undefined') mentionState.show = false }, 200)"
                              @paste="handlePaste($event)"
                              @input="if(typeof handleCommentInput === 'function') handleCommentInput($event)"
                              @keydown="if(typeof handleCommentKeydown === 'function') handleCommentKeydown($event)"></textarea>
                    
                    {{-- Mention Autocomplete Dropdown --}}
                    <div x-show="typeof mentionState !== 'undefined' && mentionState.show" 
                         class="mention-autocomplete-dropdown absolute z-50 bottom-full left-0 mb-1 w-64 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl rounded-xl overflow-hidden py-1"
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

                    <div class="absolute right-2.5 bottom-2.5 text-slate-300 text-[10px] select-none" x-show="!newComment.trim() && !((pastedImages && pastedImages.length > 0) || pastedImage)">
                      ⌘↵
                    </div>

                    {{-- Quick Replies Dropdown --}}
                    <div x-show="commentFocused && !newComment.trim() && !((pastedImages && pastedImages.length > 0) || pastedImage)"
                         x-transition
                         class="absolute z-40 bottom-full left-0 mb-1 w-56 bg-white border border-slate-200 shadow-xl rounded-xl py-1 overflow-hidden"
                         x-cloak>
                        <template x-for="reply in quickReplies" :key="reply">
                            <button type="button" @mousedown.prevent="newComment = reply; setTimeout(() => $refs.commentInput.focus(), 10)"
                                    class="w-full text-left px-3 py-1.5 text-xs text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition-colors block font-medium">
                                <span x-text="reply"></span>
                            </button>
                        </template>
                    </div>
                  </div>

                  <div x-show="newComment.trim() || ((pastedImages && pastedImages.length > 0) || pastedImage)" class="flex items-center gap-2 mt-2">
                    <template x-if="(pastedImages && pastedImages.length > 0) || pastedImage">
                      <button @click="sendScreenshot()" :disabled="sendingScreenshot"
                              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white rounded-xl text-xs font-semibold shadow-sm transition-all hover:shadow-md">
                        <span x-show="!sendingScreenshot" x-text="(pastedImages && pastedImages.length > 1) ? '📷 Share ' + pastedImages.length + ' Screenshots' : '📷 Share Screenshot'"></span>
                        <span x-show="sendingScreenshot" class="flex items-center gap-1.5">
                          <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                          Sending…
                        </span>
                      </button>
                    </template>
                    <template x-if="!((pastedImages && pastedImages.length > 0) || pastedImage) && newComment.trim()">
                      <button @click="submitComment()"
                              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all hover:shadow-md">
                        Post Comment
                      </button>
                    </template>
                    <span class="text-[10px] text-slate-400" x-show="!((pastedImages && pastedImages.length > 0) || pastedImage)">or press ⌘↵</span>
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
                                      <template x-for="emoji in ['🙏', '👍', '❤️', '😂', '😮', '😢', '🔥', '👀', '✍️']" :key="emoji">
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
                          <span x-html="parseMarkdown(typeof formatActivityDescription === 'function' ? formatActivityDescription(item.description) : (item.description?.replace(/\bbulk\s+copied\b/gi, 'copied') || item.description))" class="text-slate-600 text-[11px] [&_strong]:text-slate-800 [&_strong]:font-semibold [&_strong]:not-italic"></span>
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
          <template x-if="isSmmCard(activeCard)">
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
              <template x-if="isSmmCard(activeCard)">
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

              {{-- Content Type Dropdown Action (Work Task / Content Type) --}}
              <template x-if="isSmmCard(activeCard)">
                <div class="relative" x-data="{ open: false, search: '' }" @open-content-type-picker.window="open = true; search = ''; $nextTick(() => { if($refs.contentTypeSearch) $refs.contentTypeSearch.focus() })">
                  <button type="button" @click="open = !open; search = ''; $nextTick(() => { if(open && $refs.contentTypeSearch) $refs.contentTypeSearch.focus() })"
                        class="btn btn-secondary w-full text-xs text-left justify-start gap-2 py-2.5 px-3 flex items-center shadow-sm transition-all rounded-xl relative group">
                    <span class="text-sm">🎬</span>
                    <span class="font-semibold truncate" x-text="activeCard?.smm_cluster_label ? 'Content: ' + activeCard.smm_cluster_label : 'Content Type'"></span>
                    <template x-if="activeCard?.smm_cluster_label">
                      <span class="ml-auto w-2.5 h-2.5 rounded-full shadow-sm border border-white shrink-0" :style="'background-color:' + (getContentTypeStyle(activeCard.smm_cluster_label)?.dot || '#6366f1')"></span>
                    </template>
                  </button>
                  <div x-show="open" @click.outside="open = false" x-cloak
                       class="absolute right-0 top-11 w-64 bg-white border border-slate-200 rounded-2xl shadow-xl z-50 py-1.5"
                       x-transition:enter="transition ease-out duration-75"
                       x-transition:enter-start="scale-95 opacity-0"
                       x-transition:enter-end="scale-100 opacity-100"
                       x-transition:leave="transition ease-in duration-75"
                       x-transition:leave-start="scale-100 opacity-100"
                       x-transition:leave-end="scale-95 opacity-0">
                    <div class="flex items-center justify-between px-3 py-1.5 border-b border-slate-100">
                      <p class="text-[10px] uppercase font-bold text-slate-400 select-none">Work Task / Content Type</p>
                      <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600 text-xs leading-none p-0.5">✕</button>
                    </div>
                    
                    <div class="px-2 py-1.5 border-b border-slate-100">
                        <input type="text" x-model="search" x-ref="contentTypeSearch"
                               placeholder="Search content type..." 
                               class="form-input w-full text-xs py-1.5 px-2 rounded-lg border-slate-200 shadow-inner">
                    </div>

                    <div class="max-h-52 overflow-y-auto py-1">
                      {{-- Clear / None option if currently selected --}}
                      <template x-if="activeCard?.smm_cluster_label">
                        <button type="button" @click="setSmmContentType(null); open = false"
                                class="w-full flex items-center justify-between px-3 py-2 text-xs text-left text-rose-600 hover:bg-rose-50 transition-colors">
                          <span class="italic font-medium">✕ Clear Content Type</span>
                        </button>
                      </template>

                      {{-- Presets matching Google Sheets --}}
                      <template x-for="item in (smmContentTypes || []).filter(t => t.name.toLowerCase().includes(search.toLowerCase()))" :key="item.name">
                        <button type="button" @click="setSmmContentType(item.name); open = false"
                                class="w-full flex items-center justify-between px-3 py-2 text-xs text-left hover:bg-slate-50 transition-colors">
                          <div class="flex items-center gap-2">
                            <span class="inline-block px-2.5 py-1 rounded-md text-[11px] font-bold shadow-xs border"
                                  :style="'background-color:' + item.bg + '; color:' + item.text + '; border-color:' + item.border"
                                  x-text="item.name"></span>
                          </div>
                          <template x-if="activeCard?.smm_cluster_label?.toLowerCase() === item.name.toLowerCase()">
                            <span class="text-indigo-600 font-extrabold text-xs ml-2">✓</span>
                          </template>
                        </button>
                      </template>
                    </div>

                    {{-- Custom option if search doesn't match --}}
                    <div x-show="(smmContentTypes || []).filter(t => t.name.toLowerCase().includes(search.toLowerCase())).length === 0 && search.trim() !== ''" class="px-3 py-2 text-center border-t border-slate-100 mt-1">
                        <p class="text-[10px] text-slate-500 mb-1.5">No matching presets found.</p>
                        <button type="button" @click="setSmmContentType(search.trim()); open = false" class="btn btn-primary w-full text-[10px] py-1.5 font-bold rounded shadow-sm">
                            Use "<span x-text="search.trim()"></span>"
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
          <template x-if="isSmmCard(activeCard)">
            <div class="relative w-full">
              <input type="date"
                     class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10 no-flatpickr"
                     :value="formatInputDate(activeCard?.content_public_date)"
                     @change="updatePublicDate(activeCard, $event.target.value)">
              <button type="button"
                      class="btn btn-secondary w-full text-xs text-left justify-start gap-2 py-2.5 px-3 flex items-center shadow-sm transition-all rounded-xl relative pointer-events-none">
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
              <span class="font-bold">Copy card to</span>
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
</div>
