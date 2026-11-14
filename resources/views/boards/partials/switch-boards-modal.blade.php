{{--
  Trello-style Switch Boards Modal
  State: switchBoardsModal.open, .search, .tab, .selectedWorkspace, create fields
  Methods: openSwitchBoardsModal(), closeSwitchBoardsModal(), sbmFilteredBoards(), switchToBoard()
--}}
<div x-show="switchBoardsModal.open" x-cloak
     class="fixed inset-0 flex items-start justify-center px-3 sm:px-5 sm:py-10"
     style="z-index: 70; padding-top: max(1.25rem, calc(var(--dgt-safe-top, env(safe-area-inset-top, 0px)) + 0.625rem)); padding-bottom: max(1rem, var(--dgt-safe-bottom, env(safe-area-inset-bottom, 0px)));"
     @keydown.escape.window="if(switchBoardsModal?.open) { $event.preventDefault(); $event.stopImmediatePropagation(); closeSwitchBoardsModal(); }">

  <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       @click="closeSwitchBoardsModal()"></div>

  <section class="relative flex max-h-[88vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10"
           role="dialog"
           aria-modal="true"
           aria-labelledby="switch-boards-title"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="opacity-0 translate-y-3 scale-95"
           x-transition:enter-end="opacity-100 translate-y-0 scale-100"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="opacity-100 translate-y-0 scale-100"
           x-transition:leave-end="opacity-0 translate-y-3 scale-95"
           @click.stop>

    <header class="border-b border-slate-200 bg-white">
      <div class="flex flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center">
        <div class="flex min-w-0 items-center gap-3">
          <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600 ring-1 ring-sky-100">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
            </svg>
          </span>
          <div class="min-w-0">
            <h2 id="switch-boards-title" class="font-display text-lg font-black leading-tight text-slate-900">Switch boards</h2>
            <p class="truncate text-xs font-medium text-slate-500" x-text="sbmCurrentTitle()"></p>
          </div>
        </div>

        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center lg:justify-end">
          <label class="relative flex-1 lg:max-w-sm">
            <span class="sr-only">Search boards</span>
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <input type="search"
                   x-model.debounce.120ms="switchBoardsModal.search"
                   placeholder="Search boards"
                   class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm font-medium text-slate-800 outline-none transition focus:border-sky-400 focus:bg-white focus:ring-4 focus:ring-sky-100">
          </label>

          <button type="button"
                  @click="closeSwitchBoardsModal()"
                  class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 active:scale-95 touch-manipulation cursor-pointer"
                  aria-label="Close switch boards modal">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <div class="flex gap-2 overflow-x-auto px-4 pb-4 sm:px-6 lg:hidden no-scrollbar touch-pan-x" style="-webkit-overflow-scrolling: touch;">
        <button type="button"
                @click="switchBoardsModal.tab = 'your'"
                class="whitespace-nowrap rounded-lg px-3 py-2 min-h-[36px] text-xs font-extrabold transition active:scale-95 touch-manipulation flex items-center"
                :class="switchBoardsModal.tab === 'your' ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
          Your boards
        </button>
        <button type="button"
                @click="switchBoardsModal.tab = 'starred'"
                class="whitespace-nowrap rounded-lg px-3 py-2 min-h-[36px] text-xs font-extrabold transition active:scale-95 touch-manipulation flex items-center"
                :class="switchBoardsModal.tab === 'starred' ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
          Starred
        </button>
        <button type="button"
                @click="switchBoardsModal.tab = 'hidden'"
                class="whitespace-nowrap rounded-lg px-3 py-2 min-h-[36px] text-xs font-extrabold transition active:scale-95 touch-manipulation flex items-center gap-1.5"
                :class="switchBoardsModal.tab === 'hidden' ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
          <span>Hidden boards</span>
          <span class="rounded-full bg-black/10 px-1.5 py-0.2 text-[10px] font-black" x-text="sbmBoardCount('hidden')"></span>
        </button>

        <template x-for="ws in allWorkspaces.filter(w => w.boards && w.boards.length > 0)" :key="'mobile-ws-' + ws.id">
          <button type="button"
                  @click="switchBoardsModal.tab = 'workspace'; switchBoardsModal.selectedWorkspace = ws.id"
                  class="whitespace-nowrap rounded-lg px-3 py-2 min-h-[36px] text-xs font-extrabold transition active:scale-95 touch-manipulation flex items-center"
                  :class="switchBoardsModal.tab === 'workspace' && switchBoardsModal.selectedWorkspace === ws.id ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                  x-text="ws.name">
          </button>
        </template>
      </div>
    </header>

    <div class="flex min-h-0 flex-1 bg-slate-50">
      <aside class="hidden w-72 flex-shrink-0 border-r border-slate-200 bg-white p-4 lg:block">
        <div class="space-y-1">
          <button type="button"
                  @click="switchBoardsModal.tab = 'your'"
                  class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-bold transition"
                  :class="switchBoardsModal.tab === 'your' ? 'bg-sky-50 text-sky-700 ring-1 ring-sky-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6Z" />
            </svg>
            <span class="min-w-0 flex-1 truncate">Your boards</span>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black text-slate-500" x-text="sbmBoardCount('your')"></span>
          </button>

          <button type="button"
                  @click="switchBoardsModal.tab = 'starred'"
                  class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-bold transition"
                  :class="switchBoardsModal.tab === 'starred' ? 'bg-sky-50 text-sky-700 ring-1 ring-sky-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
            </svg>
            <span class="min-w-0 flex-1 truncate">Starred boards</span>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black text-slate-500" x-text="sbmBoardCount('starred')"></span>
          </button>

          <button type="button"
                  @click="switchBoardsModal.tab = 'hidden'"
                  class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-bold transition"
                  :class="switchBoardsModal.tab === 'hidden' ? 'bg-sky-50 text-sky-700 ring-1 ring-sky-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
            </svg>
            <span class="min-w-0 flex-1 truncate">Hidden boards</span>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black text-slate-500" x-text="sbmBoardCount('hidden')"></span>
          </button>
        </div>

        <div class="mt-5 border-t border-slate-100 pt-4">
          <div class="mb-2 px-3 text-[10px] font-black uppercase tracking-wider text-slate-400">Workspaces</div>
          <div class="max-h-[42vh] space-y-1 overflow-y-auto pr-1 scrollbar-thin">
            <template x-for="ws in allWorkspaces.filter(w => w.boards && w.boards.length > 0)" :key="ws.id">
              <button type="button"
                      @click="switchBoardsModal.tab = 'workspace'; switchBoardsModal.selectedWorkspace = ws.id"
                      class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-semibold transition"
                      :class="switchBoardsModal.tab === 'workspace' && switchBoardsModal.selectedWorkspace === ws.id ? 'bg-slate-100 text-slate-950' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'">
                <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md bg-slate-200 text-[10px] font-black uppercase text-slate-600" x-text="sbmWorkspaceInitials(ws.name)"></span>
                <span class="min-w-0 flex-1 truncate" x-text="ws.name"></span>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black text-slate-500" x-text="sbmWorkspaceBoardCount(ws)"></span>
              </button>
            </template>
          </div>
        </div>
      </aside>

      <main class="min-w-0 flex-1 overflow-y-auto p-4 sm:p-6">
        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <h3 class="truncate text-xl font-black tracking-tight text-slate-900" x-text="sbmCurrentTitle()"></h3>
              <template x-if="switchBoardsModal.tab === 'hidden'">
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-amber-800 ring-1 ring-amber-300/60">
                  Hidden View
                </span>
              </template>
            </div>
            <p class="text-xs font-semibold text-slate-500">
              <span x-text="switchBoardsModal.filteredBoards ? switchBoardsModal.filteredBoards.length : 0"></span>
              <span x-text="(switchBoardsModal.filteredBoards && switchBoardsModal.filteredBoards.length === 1) ? 'board' : 'boards'"></span>
            </p>
          </div>

          {{-- Month Filter Select + Clear Filters --}}
          <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
              <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-sky-500" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
              </svg>
              <select x-model="switchBoardsModal.monthFilter"
                      aria-label="Filter boards by month"
                      class="sbm-filter-select cursor-pointer appearance-none rounded-lg border border-slate-200 bg-white py-1.5 pl-8 pr-7 text-xs font-extrabold text-slate-700 shadow-xs outline-none transition hover:border-sky-300 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                <option value="all">All Months</option>
                <template x-for="m in sbmMonthOptions()" :key="m.value">
                  <option :value="m.value" x-text="m.label"></option>
                </template>
              </select>
              <svg class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
              </svg>
            </div>

            <template x-if="sbmHasActiveFilters()">
              <button type="button"
                      @click="sbmResetFilters()"
                      class="sbm-filter-reset inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-extrabold text-rose-600 transition hover:bg-rose-100 cursor-pointer">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
                <span>Reset</span>
              </button>
            </template>
          </div>
        </div>

        {{-- Board Type Filter Bar: All | Planning Board | Workflow Board | SMM --}}
        <div class="sbm-type-filter-bar mb-4 flex flex-wrap items-center gap-1.5 rounded-xl border border-slate-200/80 bg-white p-1.5 shadow-2xs">
          <button type="button"
                  @click="switchBoardsModal.typeFilter = 'all'"
                  class="sbm-type-pill inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-extrabold transition cursor-pointer"
                  :class="switchBoardsModal.typeFilter === 'all' ? 'sbm-type-pill-active bg-sky-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'">
            <span>All Boards</span>
            <span class="rounded-full px-1.5 py-0.2 text-[10px] font-black"
                  :class="switchBoardsModal.typeFilter === 'all' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'"
                  x-text="sbmTypeCount('all')"></span>
          </button>

          <button type="button"
                  @click="switchBoardsModal.typeFilter = 'planning'"
                  class="sbm-type-pill inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-extrabold transition cursor-pointer"
                  :class="switchBoardsModal.typeFilter === 'planning' ? 'sbm-type-pill-active bg-sky-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'">
            <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
            </svg>
            <span>Planning Board</span>
            <span class="rounded-full px-1.5 py-0.2 text-[10px] font-black"
                  :class="switchBoardsModal.typeFilter === 'planning' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'"
                  x-text="sbmTypeCount('planning')"></span>
          </button>

          <button type="button"
                  @click="switchBoardsModal.typeFilter = 'workflow'"
                  class="sbm-type-pill inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-extrabold transition cursor-pointer"
                  :class="switchBoardsModal.typeFilter === 'workflow' ? 'sbm-type-pill-active bg-sky-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'">
            <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
            </svg>
            <span>Workflow Board</span>
            <span class="rounded-full px-1.5 py-0.2 text-[10px] font-black"
                  :class="switchBoardsModal.typeFilter === 'workflow' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'"
                  x-text="sbmTypeCount('workflow')"></span>
          </button>

          <button type="button"
                  @click="switchBoardsModal.typeFilter = 'smm'"
                  class="sbm-type-pill inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-extrabold transition cursor-pointer"
                  :class="switchBoardsModal.typeFilter === 'smm' ? 'sbm-type-pill-active bg-sky-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'">
            <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
            </svg>
            <span>SMM</span>
            <span class="rounded-full px-1.5 py-0.2 text-[10px] font-black"
                  :class="switchBoardsModal.typeFilter === 'smm' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'"
                  x-text="sbmTypeCount('smm')"></span>
          </button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <template x-for="b in switchBoardsModal.filteredBoards" :key="b.id">
            <article x-data="{ menuOpen: false }"
                     class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
              <a :href="b.id === boardId ? '#' : '/boards/' + b.slug"
                 @click="b.id === boardId ? (closeSwitchBoardsModal(), $event.preventDefault()) : null"
                 class="block h-28 w-full overflow-hidden bg-slate-900 text-left relative transition-transform"
                 :style="sbmCoverStyle(b)"
                 :aria-label="'Switch to ' + b.name">
                <div class="relative h-full w-full switch-board-cover-overlay bg-slate-950/15 transition group-hover:bg-transparent">
                  <div class="absolute left-3 top-3 flex max-w-[calc(100%-1.5rem)] flex-wrap items-center gap-1.5">
                    <span class="switch-board-ws-tag switch-board-month-tag rounded-md bg-white/90 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-slate-700 shadow-sm" x-text="sbmBoardMonth(b)"></span>
                    <template x-if="b.is_hidden">
                      <span class="rounded-md bg-amber-500/95 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-white shadow-sm">Hidden</span>
                    </template>
                  </div>
                </div>
              </a>

              <div class="space-y-3 p-3">
                <div class="flex items-start gap-2">
                  <a :href="b.id === boardId ? '#' : '/boards/' + b.slug"
                     @click="b.id === boardId ? (closeSwitchBoardsModal(), $event.preventDefault()) : null"
                     class="min-w-0 flex-1 text-left">
                    <span class="block text-sm font-black text-slate-900 transition hover:text-sky-700" x-text="b.name"></span>
                  </a>

                  <div class="flex items-center gap-1 flex-shrink-0">
                    <button type="button"
                            x-show="board && board.can_manage_board"
                            @click.stop="toggleBoardHiddenDirect(b)"
                            class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg transition cursor-pointer"
                            :class="b.is_hidden ? 'bg-amber-50 text-amber-500 hover:bg-amber-100' : 'text-slate-300 hover:bg-slate-100 hover:text-slate-600'"
                            :title="b.is_hidden ? 'Unhide board (Restore to active)' : 'Hide board'"
                            :aria-label="b.is_hidden ? 'Unhide board' : 'Hide board'">
                      <svg x-show="!b.is_hidden" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                      </svg>
                      <svg x-show="b.is_hidden" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                      </svg>
                    </button>

                    <button type="button"
                            @click.stop="toggleStarDirect(b.id)"
                            class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg transition cursor-pointer"
                            :class="b.is_starred ? 'bg-amber-50 text-amber-500' : 'text-slate-300 hover:bg-slate-100 hover:text-amber-500'"
                            :title="b.is_starred ? 'Unpin board' : 'Pin board'"
                            :aria-label="b.is_starred ? 'Unpin board' : 'Pin board'">
                      <svg class="h-4 w-4" :fill="b.is_starred ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </article>
          </template>


          <template x-if="!switchBoardsModal.filteredBoards || switchBoardsModal.filteredBoards.length === 0">
            <div class="col-span-full flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center">
              <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6Z" />
                </svg>
              </span>
              <h4 class="mt-4 text-sm font-black text-slate-800">No boards found</h4>
              <p class="mt-1 max-w-xs text-xs font-medium text-slate-500"
                 x-text="sbmHasActiveFilters() ? 'No boards match your selected month or filter.' : (switchBoardsModal.tab === 'hidden' ? 'There are no hidden boards right now.' : 'This view does not have any boards yet.')"></p>
              <template x-if="sbmHasActiveFilters()">
                <button type="button"
                        @click="sbmResetFilters()"
                        class="mt-3 rounded-lg bg-sky-600 px-3.5 py-1.5 text-xs font-extrabold text-white shadow-xs transition hover:bg-sky-500 cursor-pointer">
                  Clear Filters
                </button>
              </template>
            </div>
          </template>
        </div>
      </main>
    </div>
  </section>
</div>
