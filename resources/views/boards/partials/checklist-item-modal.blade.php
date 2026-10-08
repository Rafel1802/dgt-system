{{--
  Checklist Item Modal (Add / Edit checklist item with card member assignment)
  State: checklistItemModal.*
--}}
<div x-show="checklistItemModal.open" x-cloak
     class="fixed inset-0 flex items-center justify-center p-3 sm:p-4 overflow-y-auto"
     style="z-index: 120;"
     @keydown.escape.window="if (checklistItemModal.open) { $event.stopPropagation(); closeChecklistItemModal(); }">

  {{-- Backdrop --}}
  <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs"
       @click="closeChecklistItemModal()"
       x-transition:enter="transition ease-out duration-150"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-100"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"></div>

  {{-- Modal Panel --}}
  <div class="relative w-full max-w-md my-auto flex flex-col rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-2xl max-h-[90vh] overflow-hidden"
       role="dialog"
       aria-modal="true"
       @click.stop
       x-transition:enter="transition ease-out duration-150"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       x-transition:leave="transition ease-in duration-100"
       x-transition:leave-start="opacity-100 scale-100"
       x-transition:leave-end="opacity-0 scale-95">

    {{-- Header --}}
    <div class="flex-shrink-0 flex items-center justify-between border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-800/90 px-4 sm:px-5 py-3.5">
      <h3 class="text-sm font-black text-slate-800 dark:text-slate-100 tracking-wide flex items-center gap-2">
        <span class="text-base select-none">☑️</span>
        <span x-text="checklistItemModal.mode === 'edit' ? 'Edit checklist item' : 'Add checklist item'"></span>
      </h3>
      <button type="button"
              @click="closeChecklistItemModal()"
              class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    {{-- Form Body (Scrollable & Responsive) --}}
    <form @submit.prevent="submitChecklistItemModal()"
          class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4 overscroll-contain scrollbar-thin">
      {{-- ITEM NAME --}}
      <div>
        <label class="block text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">
          Item Name
        </label>
        <input type="text"
               id="checklist-item-modal-input"
               x-model="checklistItemModal.title"
               @input="onChecklistItemTitleInput()"
               placeholder="Add item…"
               class="w-full text-xs font-semibold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 focus:outline-none transition-all shadow-2xs">
      </div>

      {{-- Detected choices pill container (if multiple matching users exist on card) --}}
      <template x-if="checklistItemModal.detectedMembers && checklistItemModal.detectedMembers.length > 1">
        <div class="p-2.5 bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50 rounded-xl">
          <div class="text-[10px] font-bold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <span>✨ Detected <span x-text="({ video: 'Video', graphic: 'Graphic', listing: 'Listing', content: 'Content' })[checklistItemModal.detectedCategory] || ''"></span> Members:</span>
          </div>
          <div class="flex flex-wrap gap-1.5">
            <template x-for="dm in checklistItemModal.detectedMembers" :key="'dm-' + dm.id">
              <button type="button"
                      @click="selectChecklistUser(dm.id)"
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border transition-all cursor-pointer"
                      :class="isChecklistUserSelected(dm.id)
                        ? 'bg-indigo-600 text-white border-indigo-600 shadow-2xs ring-2 ring-indigo-300'
                        : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-indigo-50'">
                <template x-if="avatarUrl(dm)">
                  <img :src="avatarUrl(dm)" :alt="dm.name" class="w-4 h-4 rounded-full object-cover">
                </template>
                <template x-if="!avatarUrl(dm)">
                  <span class="w-4 h-4 rounded-full flex items-center justify-center text-[8px] font-black text-white"
                        :style="avatarStyle(dm)" x-text="avatarInitials(dm)"></span>
                </template>
                <span x-text="dm.name"></span>
              </button>
            </template>
          </div>
        </div>
      </template>

      {{-- ASSIGN USER --}}
      {{-- Dropdown is CLOSED by default and re-closed every time the modal opens/closes --}}
      <div x-data="{ userDropdownOpen: false, memberSearch: '' }"
           x-effect="if (!checklistItemModal.open) { userDropdownOpen = false; memberSearch = ''; }"
           class="space-y-1.5">
        <label class="block text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
          Assign Users <span class="normal-case font-medium text-slate-400">(optional — pick one or more)</span>
        </label>

        {{-- Dropdown Trigger Button --}}
        <button type="button"
                @click="userDropdownOpen = !userDropdownOpen; if (userDropdownOpen) { $nextTick(() => $refs.memberSearchInput?.focus()); }"
                class="w-full text-xs font-semibold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl px-3.5 py-2.5 flex items-center justify-between shadow-2xs hover:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all cursor-pointer">
          <div class="flex items-center gap-2 min-w-0">
            <template x-if="getSelectedChecklistUsers().length">
              <div class="flex items-center gap-2 min-w-0">
                <div class="flex items-center -space-x-1.5 shrink-0">
                  <template x-for="su in getSelectedChecklistUsers().slice(0, 4)" :key="'sel-' + su.id">
                    <span class="inline-flex">
                      <template x-if="avatarUrl(su)">
                        <img :src="avatarUrl(su)" :alt="su.name"
                             class="w-5 h-5 rounded-full object-cover shadow-2xs ring-2 ring-white dark:ring-slate-900">
                      </template>
                      <template x-if="!avatarUrl(su)">
                        <span class="w-5 h-5 rounded-full shadow-2xs flex items-center justify-center text-[8px] font-black text-white ring-2 ring-white dark:ring-slate-900"
                              :style="avatarStyle(su)" x-text="avatarInitials(su)"></span>
                      </template>
                    </span>
                  </template>
                </div>
                <span class="truncate font-bold"
                      x-text="getSelectedChecklistUsers().length === 1
                        ? getSelectedChecklistUsers()[0].name
                        : getSelectedChecklistUsers().length + ' members assigned'"></span>
              </div>
            </template>
            <template x-if="!getSelectedChecklistUsers().length">
              <div class="flex items-center gap-2 text-slate-400">
                <span class="w-5 h-5 rounded-full border border-dashed border-slate-300 dark:border-slate-600 flex items-center justify-center text-[10px] text-slate-400">👤</span>
                <span class="italic font-normal">No user assigned</span>
              </div>
            </template>
          </div>
          <svg class="h-4 w-4 text-slate-400 shrink-0 ml-2 transition-transform duration-150"
               :class="userDropdownOpen ? 'rotate-180 text-indigo-500' : ''"
               fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
          </svg>
        </button>

        {{-- Dropdown Container (In-flow, never clipped by modal, fully scrollable) --}}
        <div x-show="userDropdownOpen"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/90 shadow-2xs p-2 space-y-2">
          
          {{-- Search Filter Input --}}
          <div class="relative">
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none"
                 fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input type="text"
                   x-ref="memberSearchInput"
                   x-model="memberSearch"
                   placeholder="Search members…"
                   autocomplete="off"
                   class="w-full pl-8 pr-3 py-1.5 text-xs bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition shadow-2xs">
          </div>

          {{-- Scrollable List of Members --}}
          <div class="max-h-48 sm:max-h-56 overflow-y-auto space-y-0.5 pr-1 scrollbar-thin overscroll-contain">
            
            {{-- Option: No user assigned --}}
            <button type="button"
                    @click="selectChecklistUser(null)"
                    class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs hover:bg-white dark:hover:bg-slate-800 transition text-left cursor-pointer"
                    :class="!(checklistItemModal.assignedUserIds || []).length ? 'bg-white dark:bg-slate-800 font-bold text-indigo-600 dark:text-indigo-400 shadow-2xs' : 'text-slate-600 dark:text-slate-300'">
              <div class="flex items-center gap-2">
                <span class="w-5 h-5 rounded-full border border-dashed border-slate-300 dark:border-slate-600 flex items-center justify-center text-[10px] text-slate-400">✕</span>
                <span>No user assigned</span>
              </div>
              <span x-show="!(checklistItemModal.assignedUserIds || []).length" class="text-indigo-600 dark:text-indigo-400 font-bold">✓</span>
            </button>

            {{-- ── Card Members Group ─────────────────────────────── --}}
            <template x-if="getChecklistCardMembers(memberSearch).length">
              <div class="pt-1.5">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 px-2 py-1">
                  Card Members
                </div>
                <template x-for="m in getChecklistCardMembers(memberSearch)" :key="'card-m-' + m.id">
                  <button type="button"
                          @click="selectChecklistUser(m.id)"
                          class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs hover:bg-white dark:hover:bg-slate-800 transition text-left cursor-pointer"
                          :class="isChecklistUserSelected(m.id) ? 'bg-white dark:bg-slate-800 font-bold text-indigo-600 dark:text-indigo-400 shadow-2xs' : 'text-slate-700 dark:text-slate-200'">
                    <div class="flex items-center gap-2 min-w-0">
                      <template x-if="avatarUrl(m)">
                        <img :src="avatarUrl(m)" :alt="m.name"
                             class="w-5 h-5 rounded-full object-cover shadow-2xs ring-1 ring-slate-200 shrink-0">
                      </template>
                      <template x-if="!avatarUrl(m)">
                        <span class="w-5 h-5 rounded-full shadow-2xs flex items-center justify-center text-[8px] font-black text-white shrink-0"
                              :style="avatarStyle(m)"
                              x-text="avatarInitials(m)"></span>
                      </template>
                      <span class="truncate" x-text="m.name"></span>
                    </div>
                    <span x-show="isChecklistUserSelected(m.id)" class="text-indigo-600 dark:text-indigo-400 font-bold">✓</span>
                  </button>
                </template>
              </div>
            </template>

            {{-- ── Other Board Members Group ─────────────────────────────── --}}
            <template x-if="getChecklistBoardMembers(memberSearch).length">
              <div class="pt-1.5 border-t border-slate-200/60 dark:border-slate-800 mt-1">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 px-2 py-1">
                  Other Board Members
                </div>
                <template x-for="m in getChecklistBoardMembers(memberSearch)" :key="'board-m-' + m.id">
                  <button type="button"
                          @click="selectChecklistUser(m.id)"
                          class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs hover:bg-white dark:hover:bg-slate-800 transition text-left cursor-pointer"
                          :class="isChecklistUserSelected(m.id) ? 'bg-white dark:bg-slate-800 font-bold text-indigo-600 dark:text-indigo-400 shadow-2xs' : 'text-slate-700 dark:text-slate-200'">
                    <div class="flex items-center gap-2 min-w-0">
                      <template x-if="avatarUrl(m)">
                        <img :src="avatarUrl(m)" :alt="m.name"
                             class="w-5 h-5 rounded-full object-cover shadow-2xs ring-1 ring-slate-200 shrink-0">
                      </template>
                      <template x-if="!avatarUrl(m)">
                        <span class="w-5 h-5 rounded-full shadow-2xs flex items-center justify-center text-[8px] font-black text-white shrink-0"
                              :style="avatarStyle(m)"
                              x-text="avatarInitials(m)"></span>
                      </template>
                      <span class="truncate" x-text="m.name"></span>
                    </div>
                    <span x-show="isChecklistUserSelected(m.id)" class="text-indigo-600 dark:text-indigo-400 font-bold">✓</span>
                  </button>
                </template>
              </div>
            </template>

            {{-- Empty search state --}}
            <template x-if="memberSearch && !getChecklistCardMembers(memberSearch).length && !getChecklistBoardMembers(memberSearch).length">
              <div class="px-3 py-3 text-xs text-slate-400 italic text-center">
                No members found matching "<span x-text="memberSearch"></span>"
              </div>
            </template>

          </div>
        </div>
      </div>

      {{-- Actions Footer --}}
      <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-700">
        <button type="button"
                @click="closeChecklistItemModal()"
                class="px-4 py-2 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition cursor-pointer">
          Cancel
        </button>
        <button type="submit"
                :disabled="!((checklistItemModal.title || '').trim())"
                class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl transition shadow-xs cursor-pointer">
          <span x-text="checklistItemModal.mode === 'edit' ? 'Save' : 'Add item'"></span>
        </button>
      </div>
    </form>
  </div>
</div>
