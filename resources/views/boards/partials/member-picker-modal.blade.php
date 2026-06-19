{{--
  Trello-style Member Picker Modal
  State lives in Alpine: memberPicker.open, .cardId, .search, .cardMembers,
                         .boardMembers, .workspaceMembers, .loading
  Methods in trello-board.js: openMemberPicker(), closeMemberPicker(),
                               mpToggleMember(), mpSearch(), mpAvatarBg()
--}}
<div x-show="memberPicker.open" x-cloak
     class="fixed inset-0 z-[65] flex items-start justify-center pt-24 px-4"
     @keydown.escape.window="closeMemberPicker()">

  {{-- Backdrop --}}
  <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
       @click="closeMemberPicker()"></div>

  {{-- Panel --}}
  <div class="relative bg-white rounded-2xl shadow-2xl border border-slate-100 w-80 overflow-hidden"
       x-transition:enter="transition ease-out duration-150"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       @click.stop>

    {{-- Header --}}
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-slate-50/60">
      <h3 class="text-xs font-black text-slate-700 tracking-wide uppercase">👥 Members</h3>
      <button @click="closeMemberPicker()"
              class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    {{-- Search --}}
    <div class="px-3 pt-3 pb-2">
      <div class="relative">
        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none"
             fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
        <input type="text"
               x-model="memberPicker.search"
               @input.debounce.300ms="mpSearch()"
               placeholder="Search members…"
               autocomplete="off"
               class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg
                      focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:outline-none
                      focus:bg-white transition-all">
      </div>
    </div>

    {{-- Loading spinner --}}
    <div x-show="memberPicker.loading" class="flex justify-center py-6">
      <div class="w-5 h-5 border-2 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
    </div>

    <div x-show="!memberPicker.loading" class="max-h-80 overflow-y-auto pb-3 scrollbar-thin">

      {{-- ── Card Members ─────────────────────────────── --}}
      <template x-if="memberPicker.cardMembers.length">
        <div>
          <p class="text-[10px] uppercase font-extrabold text-slate-400 px-4 pt-3 pb-1.5 select-none tracking-wider">
            Card Members
          </p>
          <template x-for="u in memberPicker.cardMembers" :key="'cm-'+u.id">
            <button @click="mpToggleMember(u)"
                    class="w-full flex items-center gap-3 px-4 py-2 hover:bg-indigo-50/50 transition-colors group">
              {{-- Avatar --}}
              <div class="relative flex-shrink-0">
                <template x-if="u.avatar && !u.avatar.includes('ui-avatars')">
                  <img :src="u.avatar" :alt="u.name"
                       class="w-8 h-8 rounded-full object-cover border-2 border-white shadow-sm ring-1 ring-slate-100">
                </template>
                <template x-if="!u.avatar || u.avatar.includes('ui-avatars')">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shadow-sm"
                       :style="'background:' + mpAvatarBg(u.name)">
                    <span x-text="u.initials || u.name.charAt(0).toUpperCase()"></span>
                  </div>
                </template>
                {{-- Online indicator dot --}}
                <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-emerald-400 border-2 border-white rounded-full"></span>
              </div>
              {{-- Name & email --}}
              <div class="flex-1 min-w-0 text-left">
                <p class="text-xs font-semibold text-slate-700 truncate" x-text="u.name"></p>
                <p class="text-[10px] text-slate-400 truncate" x-text="u.email"></p>
              </div>
              {{-- Check mark --}}
              <svg class="w-4 h-4 text-indigo-600 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                   stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
              </svg>
            </button>
          </template>
        </div>
      </template>

      {{-- ── Board Members ─────────────────────────────── --}}
      <template x-if="memberPicker.boardMembers.length">
        <div>
          <p class="text-[10px] uppercase font-extrabold text-slate-400 px-4 pt-3 pb-1.5 select-none tracking-wider">
            Board Members
          </p>
          <template x-for="u in memberPicker.boardMembers" :key="'bm-'+u.id">
            <button @click="mpToggleMember(u)"
                    class="w-full flex items-center gap-3 px-4 py-2 hover:bg-indigo-50/50 transition-colors group">
              <div class="flex-shrink-0">
                <template x-if="u.avatar && !u.avatar.includes('ui-avatars')">
                  <img :src="u.avatar" :alt="u.name"
                       class="w-8 h-8 rounded-full object-cover border-2 border-white shadow-sm ring-1 ring-slate-100">
                </template>
                <template x-if="!u.avatar || u.avatar.includes('ui-avatars')">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shadow-sm"
                       :style="'background:' + mpAvatarBg(u.name)">
                    <span x-text="u.initials || u.name.charAt(0).toUpperCase()"></span>
                  </div>
                </template>
              </div>
              <div class="flex-1 min-w-0 text-left">
                <p class="text-xs font-semibold text-slate-700 truncate" x-text="u.name"></p>
                <p class="text-[10px] text-slate-400 truncate" x-text="u.email"></p>
              </div>
              {{-- Plus icon to add --}}
              <svg class="w-4 h-4 text-slate-300 group-hover:text-indigo-500 flex-shrink-0 transition-colors"
                   fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
              </svg>
            </button>
          </template>
        </div>
      </template>

      {{-- ── Workspace Members ─────────────────────────── --}}
      <template x-if="memberPicker.workspaceMembers.length">
        <div>
          <p class="text-[10px] uppercase font-extrabold text-slate-400 px-4 pt-3 pb-1.5 select-none tracking-wider">
            Workspace Members
          </p>
          <template x-for="u in memberPicker.workspaceMembers" :key="'wm-'+u.id">
            <button @click="mpToggleMember(u)"
                    class="w-full flex items-center gap-3 px-4 py-2 hover:bg-indigo-50/50 transition-colors group">
              <div class="flex-shrink-0">
                <template x-if="u.avatar && !u.avatar.includes('ui-avatars')">
                  <img :src="u.avatar" :alt="u.name"
                       class="w-8 h-8 rounded-full object-cover border-2 border-white shadow-sm ring-1 ring-slate-100">
                </template>
                <template x-if="!u.avatar || u.avatar.includes('ui-avatars')">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shadow-sm"
                       :style="'background:' + mpAvatarBg(u.name)">
                    <span x-text="u.initials || u.name.charAt(0).toUpperCase()"></span>
                  </div>
                </template>
              </div>
              <div class="flex-1 min-w-0 text-left">
                <p class="text-xs font-semibold text-slate-700 truncate" x-text="u.name"></p>
                <p class="text-[10px] text-slate-400 truncate" x-text="u.email"></p>
              </div>
              <svg class="w-4 h-4 text-slate-300 group-hover:text-indigo-500 flex-shrink-0 transition-colors"
                   fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
              </svg>
            </button>
          </template>
        </div>
      </template>

      {{-- Empty state --}}
      <template x-if="!memberPicker.cardMembers.length && !memberPicker.boardMembers.length && !memberPicker.workspaceMembers.length">
        <div class="py-10 text-center">
          <p class="text-xs text-slate-400 font-medium">No members found</p>
          <p class="text-[10px] text-slate-300 mt-0.5">Try a different search term</p>
        </div>
      </template>

    </div>
  </div>
</div>
