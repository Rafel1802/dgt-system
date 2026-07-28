{{--
  Trello-style Switch Boards Modal
  State: switchBoardsModal.open, .search, .tab, .selectedWorkspace, create fields
  Methods: openSwitchBoardsModal(), closeSwitchBoardsModal(), sbmFilteredBoards(), switchToBoard()
--}}
<div x-show="switchBoardsModal.open" x-cloak
     class="fixed inset-0 z-[70] flex items-start justify-center px-3 py-6 sm:px-5 sm:py-10"
     @keydown.escape.window="closeSwitchBoardsModal()">

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
                  class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                  aria-label="Close switch boards modal">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <div class="flex gap-2 overflow-x-auto px-4 pb-4 sm:px-6 lg:hidden">
        <button type="button"
                @click="switchBoardsModal.tab = 'your'"
                class="whitespace-nowrap rounded-lg px-3 py-2 text-xs font-extrabold transition"
                :class="switchBoardsModal.tab === 'your' ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
          Your boards
        </button>
        <button type="button"
                @click="switchBoardsModal.tab = 'starred'"
                class="whitespace-nowrap rounded-lg px-3 py-2 text-xs font-extrabold transition"
                :class="switchBoardsModal.tab === 'starred' ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
          Starred
        </button>

        <template x-for="ws in allWorkspaces" :key="'mobile-ws-' + ws.id">
          <button type="button"
                  @click="switchBoardsModal.tab = 'workspace'; switchBoardsModal.selectedWorkspace = ws.id"
                  class="whitespace-nowrap rounded-lg px-3 py-2 text-xs font-extrabold transition"
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


        </div>

        <div class="mt-5 border-t border-slate-100 pt-4">
          <div class="mb-2 px-3 text-[10px] font-black uppercase tracking-wider text-slate-400">Workspaces</div>
          <div class="max-h-[42vh] space-y-1 overflow-y-auto pr-1 scrollbar-thin">
            <template x-for="ws in allWorkspaces" :key="ws.id">
              <button type="button"
                      @click="switchBoardsModal.tab = 'workspace'; switchBoardsModal.selectedWorkspace = ws.id"
                      class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-semibold transition"
                      :class="switchBoardsModal.tab === 'workspace' && switchBoardsModal.selectedWorkspace === ws.id ? 'bg-slate-100 text-slate-950' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'">
                <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md bg-slate-200 text-[10px] font-black uppercase text-slate-600" x-text="sbmWorkspaceInitials(ws.name)"></span>
                <span class="min-w-0 flex-1 truncate" x-text="ws.name"></span>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black text-slate-500" x-text="ws.boards.length"></span>
              </button>
            </template>
          </div>
        </div>
      </aside>

      <main class="min-w-0 flex-1 overflow-y-auto p-4 sm:p-6">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="min-w-0">
            <h3 class="truncate text-xl font-black tracking-tight text-slate-900" x-text="sbmCurrentTitle()"></h3>
            <p class="text-xs font-semibold text-slate-500">
              <span x-text="sbmFilteredBoards().length"></span>
              <span x-text="sbmFilteredBoards().length === 1 ? 'board' : 'boards'"></span>
            </p>
          </div>

        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <template x-for="b in sbmFilteredBoards()" :key="b.id">
            <article x-data="{ menuOpen: false }"
                     class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
              <button type="button"
                      @click="switchToBoard(b)"
                      class="block h-28 w-full overflow-hidden bg-slate-200 text-left"
                      :style="sbmCoverStyle(b)"
                      :aria-label="'Switch to ' + b.name">
                <div class="relative h-full w-full bg-slate-950/15 transition group-hover:bg-slate-950/5">
                  <div class="absolute left-3 top-3 flex max-w-[calc(100%-1.5rem)] items-center gap-2">
                    <span class="rounded-md bg-white/90 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-slate-700 shadow-sm" x-text="sbmBoardWorkspaceName(b)"></span>
                  </div>

                </div>
              </button>

              <div class="space-y-3 p-3">
                <div class="flex items-start gap-3">
                  <button type="button"
                          @click="switchToBoard(b)"
                          class="min-w-0 flex-1 text-left">
                    <span class="block text-sm font-black text-slate-900 transition hover:text-sky-700" x-text="b.name"></span>
                  </button>

                  <button type="button"
                          @click.stop="toggleStarDirect(b.id)"
                          class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg transition"
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


          <template x-if="sbmFilteredBoards().length === 0">
            <div class="col-span-full flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center">
              <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6Z" />
                </svg>
              </span>
              <h4 class="mt-4 text-sm font-black text-slate-800">No boards found</h4>
              <p class="mt-1 max-w-xs text-xs font-medium text-slate-500" x-text="switchBoardsModal.search ? 'Try a different search term.' : 'This view does not have any boards yet.'"></p>
            </div>
          </template>
        </div>
      </main>
    </div>
  </section>
</div>
