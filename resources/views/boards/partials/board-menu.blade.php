{{--
  Trello-style right-side board menu.
  State and actions live in public/js/trello-board.js under boardMenu.
--}}
<style>
/* Board menu panel custom styles */
.board-menu-panel {
    background: #ffffff;
    border-left: 1px solid #e2e8f0;
}
[data-theme="dark"] .board-menu-panel {
    background: #0f172a;
    border-color: #1e293b;
}
[data-theme="neon"] .board-menu-panel {
    background: rgba(4, 20, 56, 0.96);
    border-color: rgba(0, 160, 255, 0.35);
    box-shadow: -8px 0 32px rgba(0, 0, 0, 0.6);
}

.board-menu-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    transition: all 0.18s ease;
}
[data-theme="dark"] .board-menu-card {
    background: #131d33;
    border-color: #1e293b;
}
[data-theme="neon"] .board-menu-card {
    background: rgba(7, 28, 76, 0.7);
    border-color: rgba(0, 160, 255, 0.25);
}

.board-menu-subcard {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
}
[data-theme="dark"] .board-menu-subcard {
    background: #17223b;
    border-color: #1e293b;
}
[data-theme="neon"] .board-menu-subcard {
    background: rgba(4, 20, 56, 0.7);
    border-color: rgba(0, 160, 255, 0.18);
}
</style>

<div x-show="boardMenu.open" x-cloak class="fixed inset-0 overflow-hidden" style="z-index: 60;" @keydown.escape.window="closeBoardMenu()">
  {{-- Backdrop --}}
  <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-xs transition-opacity"
       x-show="boardMenu.open"
       x-transition.opacity
       @click="closeBoardMenu()"></div>

  <aside class="board-menu-panel fixed inset-y-0 right-0 flex w-screen max-w-md sm:max-w-lg flex-col shadow-2xl z-10"
         x-show="boardMenu.open"
         x-transition:enter="transform transition ease-out duration-250"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         @click.stop>

    {{-- ── Drawer Header ────────────────────────────────────────────────── --}}
    <header class="flex h-14 sm:h-16 flex-shrink-0 items-center justify-between gap-3 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/80 backdrop-blur-md px-4 sm:px-5">
      <div class="flex items-center gap-2 min-w-0">
        <button type="button"
                x-show="boardMenu.view !== 'menu'"
                @click="openBoardMenuView('menu')"
                class="flex h-8 w-8 items-center justify-center rounded-xl text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100 hover:bg-slate-200/70 dark:hover:bg-slate-800 transition active:scale-95 cursor-pointer flex-shrink-0"
                aria-label="Back to board menu">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
          </svg>
        </button>

        <h2 class="min-w-0 truncate text-sm sm:text-base font-extrabold text-slate-800 dark:text-white tracking-tight" x-text="boardMenuTitle()"></h2>
      </div>

      <button type="button"
              @click="closeBoardMenu()"
              class="flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 hover:text-slate-700 dark:text-slate-500 dark:hover:text-slate-200 hover:bg-slate-200/70 dark:hover:bg-slate-800 transition active:scale-95 cursor-pointer flex-shrink-0"
              aria-label="Close board menu">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
      </button>
    </header>

    {{-- ── Drawer Body ──────────────────────────────────────────────────── --}}
    <div class="flex-1 overflow-y-auto p-4 sm:p-5 pb-32 lg:pb-6 scrollbar-thin touch-pan-y" style="-webkit-overflow-scrolling: touch;">

      {{-- ═════════════════════════════════════════════════════════════════ --}}
      {{-- VIEW: MENU (Main Overview)                                        --}}
      {{-- ═════════════════════════════════════════════════════════════════ --}}
      <div x-show="boardMenu.view === 'menu'" class="space-y-4">
        {{-- Board Preview Card --}}
        <div class="board-menu-card rounded-2xl p-3.5 shadow-xs">
          <div class="h-28 rounded-xl shadow-inner ring-1 ring-slate-900/10 transition-all"
               :style="sbmBoardPreviewStyle(board)"></div>
          <div class="mt-3.5">
            <h3 class="truncate text-base font-extrabold text-slate-900 dark:text-white" x-text="board.name"></h3>
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mt-0.5" x-text="sbmBoardWorkspaceName(board)"></p>
          </div>
        </div>

        <nav class="space-y-1">
          <button type="button" @click="closeBoardMenu(); openSwitchBoardsModal();" class="board-menu-row text-indigo-600 dark:text-indigo-400 font-black">
            <span class="board-menu-icon bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
              <svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6Zm4.5 3h7.5m-7.5 6h7.5" /></svg>
            </span>
            <span>Switch Board</span>
          </button>
          <button type="button" @click="openBoardMenuView('about')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25h1.5v6h-1.5zM12 7.5h.008v.008H12z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span>
            <span>About this board</span>
          </button>
          <button type="button" @click="openBoardMenuView('visibility')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 0 0-9 0v3.75M6.75 10.5h10.5A2.25 2.25 0 0 1 19.5 12.75v6A2.25 2.25 0 0 1 17.25 21H6.75A2.25 2.25 0 0 1 4.5 18.75v-6a2.25 2.25 0 0 1 2.25-2.25Z"/></svg></span>
            <span>Visibility</span>
            <span class="ml-auto rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-[10px] font-black uppercase text-slate-500 dark:text-slate-400" x-text="board.visibility || 'workspace'"></span>
          </button>
          <button type="button" @click="openBoardMenuView('share')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314M16.783 5.593a2.25 2.25 0 1 0 0-2.186 2.25 2.25 0 0 0 0 2.186Zm0 12.814a2.25 2.25 0 1 0 0 2.186 2.25 2.25 0 0 0 0-2.186Z"/></svg></span>
            <span>Print/export/share</span>
          </button>
          <button type="button" @click="toggleStar()" class="board-menu-row">
            <span class="board-menu-icon" :class="board.is_starred ? 'text-amber-500' : ''"><svg fill="currentColor" viewBox="0 0 24 24"><path d="m12 2.25 2.89 5.86 6.47.94-4.68 4.56 1.1 6.44L12 17l-5.78 3.05 1.1-6.44-4.68-4.56 6.47-.94L12 2.25Z"/></svg></span>
            <span x-text="board.is_starred ? 'Unstar board' : 'Star board'"></span>
          </button>
          <button type="button" @click="openBoardMenuView('settings')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.685.654.846l1.153.535c.344.16.745.121 1.052-.103l1.05-.765c.445-.325 1.064-.276 1.454.114l1.834 1.834c.39.39.439 1.009.114 1.454l-.765 1.05c-.224.307-.263.708-.103 1.052l.535 1.153c.161.341.472.591.846.654l1.281.213c.542.09.94.56.94 1.11v2.593c0 .55-.398 1.02-.94 1.11l-1.281.213a1.125 1.125 0 0 0-.846.654l-.535 1.153c-.16.344-.121.745.103 1.052l.765 1.05c.325.445.276 1.064-.114 1.454l-1.834 1.834a1.125 1.125 0 0 1-1.454.114l-1.05-.765a1.125 1.125 0 0 0-1.052-.103l-1.153.535a1.125 1.125 0 0 0-.654.846l-.213 1.281c-.09.542-.56.94-1.11.94h-2.593c-.55 0-1.02-.398-1.11-.94l-.213-1.281a1.125 1.125 0 0 0-.654-.846l-1.153-.535a1.125 1.125 0 0 0-1.052.103l-1.05.765a1.125 1.125 0 0 1-1.454-.114L2.183 18.54a1.125 1.125 0 0 1-.114-1.454l.765-1.05c.224-.307.263-.708.103-1.052l-.535-1.153a1.125 1.125 0 0 0-.846-.654L.275 12.964a1.125 1.125 0 0 1-.94-1.11V9.262c0-.55.398-1.02.94-1.11l1.281-.213c.374-.063.685-.313.846-.654l.535-1.153a1.125 1.125 0 0 0-.103-1.052l-.765-1.05a1.125 1.125 0 0 1 .114-1.454L4.017.742a1.125 1.125 0 0 1 1.454-.114l1.05.765c.307.224.708.263 1.052.103l1.153-.535c.341-.161.591-.472.654-.846Z" transform="scale(.72) translate(5 4)"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg></span>
            <span>Settings</span>
          </button>
          @if(auth()->user()?->hasAnyRole(['super-admin', 'admin-digital', 'supervisor']))
          <button type="button" @click="openBoardMenuView('automation')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg></span>
            <span>Automations</span>
          </button>
          @endif
          <button type="button" @click="openBoardMenuView('background')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.16-5.16a2.25 2.25 0 0 1 3.18 0l5.16 5.16m-1.5-1.5 1.41-1.41a2.25 2.25 0 0 1 3.18 0l2.91 2.91M3.75 19.5h16.5A1.5 1.5 0 0 0 21.75 18V6A1.5 1.5 0 0 0 20.25 4.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z"/></svg></span>
            <span>Change background</span>
          </button>
          <button type="button" @click="openBoardMenuView('labels')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581a2.25 2.25 0 0 0 3.182 0l4.318-4.318a2.25 2.25 0 0 0 0-3.182L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6Z"/></svg></span>
            <span>Labels</span>
          </button>
          <button type="button" @click="openBoardMenuView('activity')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3.75 2.25M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span>
            <span>Activity</span>
          </button>
          <button type="button" @click="openBoardMenuView('archived')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.63A2.25 2.25 0 0 1 17.378 20.25H6.622a2.25 2.25 0 0 1-2.247-2.12L3.75 7.5M10 11.25h4M3.375 7.5h17.25a1.125 1.125 0 0 0 1.125-1.125v-1.5a1.125 1.125 0 0 0-1.125-1.125H3.375A1.125 1.125 0 0 0 2.25 4.875v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg></span>
            <span>Archived items</span>
          </button>
          <button type="button" @click="openBoardMenuView('trash')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg></span>
            <span>Trash</span>
          </button>
          <button type="button" @click="toggleBoardWatch()" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.43 0 .644C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg></span>
            <span>Watch board</span>
            <span x-show="boardMenu.watched" class="ml-auto rounded-full bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 text-[10px] font-black uppercase text-sky-700 dark:text-sky-300">On</span>
          </button>
          <button type="button" @click="openBoardMenuView('copy')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75m9 10.5h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876A9.06 9.06 0 0 0 11.25 2.25H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25"/></svg></span>
            <span>Copy board</span>
          </button>
          <button type="button" @click="openBoardMenuView('leave')" class="board-menu-row text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/></svg></span>
            <span>Leave board</span>
          </button>
        </nav>
      </div>

      {{-- ═════════════════════════════════════════════════════════════════ --}}
      {{-- VIEW: SETTINGS (Re-designed for Screenshot)                       --}}
      {{-- ═════════════════════════════════════════════════════════════════ --}}
      <section x-show="boardMenu.view === 'settings'" class="space-y-4">
        {{-- Read-only warning --}}
        <div x-show="!board.can_manage_board" class="rounded-2xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-950/40 p-3.5 text-xs font-semibold leading-5 text-amber-800 dark:text-amber-300 flex items-center gap-2.5">
          <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <span>You can view these settings, but only board admins can save changes.</span>
        </div>

        {{-- Board Identity Card --}}
        <div class="board-menu-card rounded-2xl p-4 space-y-3.5 shadow-xs">
          <div>
            <label class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider block mb-1.5 flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
              <span>Board Name</span>
            </label>
            <input x-model="boardMenu.settingsName" type="text" maxlength="100"
                   class="w-full px-3.5 py-2.5 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all font-semibold"
                   :disabled="!board.can_manage_board">
          </div>

          <div>
            <label class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider block mb-1.5 flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/></svg>
              <span>Board Description</span>
            </label>
            <textarea x-model="boardMenu.settingsDescription" rows="3" maxlength="5000"
                      class="w-full px-3.5 py-2.5 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all resize-none"
                      placeholder="Add board context, goals, or ownership notes."
                      :disabled="!board.can_manage_board"></textarea>
          </div>

          <div class="grid gap-3 sm:grid-cols-2 pt-1">
            <div>
              <label class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider block mb-1.5 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                <span>Workspace</span>
              </label>
              <div class="relative">
                <select x-model="boardMenu.settingsWorkspaceId"
                        class="w-full py-2.5 pl-3 pr-8 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white appearance-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500"
                        :disabled="!board.can_manage_board">
                  <template x-for="workspace in allWorkspaces" :key="workspace.id">
                    <option :value="workspace.id" x-text="workspace.name"></option>
                  </template>
                </select>
                <svg class="w-4 h-4 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
              </div>
            </div>

            <div>
              <label class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider block mb-1.5 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                <span>Visibility</span>
              </label>
              <div class="relative">
                <select x-model="boardMenu.settingsVisibility"
                        class="w-full py-2.5 pl-3 pr-8 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white appearance-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500"
                        :disabled="!board.can_manage_board">
                  <option value="private">Private (Only Members)</option>
                  <option value="workspace">Workspace</option>
                  <option value="public">Public</option>
                </select>
                <svg class="w-4 h-4 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
              </div>
            </div>
          </div>
        </div>

        {{-- ── Background Picker Card ─────────────────────────────────── --}}
        <div class="board-menu-card rounded-2xl p-4 space-y-3.5 shadow-xs">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="w-6 h-6 rounded-lg bg-indigo-500/10 text-indigo-500 flex items-center justify-center">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88"/></svg>
              </div>
              <span class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-200">Board Background</span>
            </div>

            {{-- Live swatch preview pill --}}
            <div class="flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-[11px] font-bold text-slate-600 dark:text-slate-300">
              <span class="w-3.5 h-3.5 rounded-full border border-white dark:border-slate-700 shadow-xs flex-shrink-0"
                    :style="'background:' + (boardMenu.backgroundType === 'color' ? boardMenu.backgroundColorDraft : (boardMenu.backgroundType === 'gradient' ? boardMenu.backgroundColorDraft : 'url(' + boardMenu.backgroundImageUrl + ') center/cover'))"></span>
              <span class="truncate max-w-[80px]" x-text="boardMenu.backgroundType === 'color' ? boardMenu.backgroundColorDraft : boardMenu.backgroundType"></span>
            </div>
          </div>

          {{-- Color Palette Swatches --}}
          <div>
            <div class="grid grid-cols-4 sm:grid-cols-5 gap-2 max-h-40 overflow-y-auto scrollbar-thin pr-1 py-1">
              <template x-for="color in boardMenu.backgroundColors" :key="color">
                <button type="button"
                        @click="boardMenu.backgroundType = 'color'; boardMenu.backgroundValue = color; boardMenu.backgroundColorDraft = color"
                        class="h-10 rounded-xl relative transition-all duration-150 hover:scale-105 active:scale-95 shadow-xs flex items-center justify-center cursor-pointer"
                        :class="boardMenu.backgroundType === 'color' && boardMenu.backgroundValue === color ? 'ring-2 ring-indigo-500 dark:ring-sky-400 ring-offset-2 dark:ring-offset-slate-900 shadow-md' : 'ring-1 ring-black/5 dark:ring-white/10 hover:ring-slate-300'"
                        :style="'background:' + color">
                  <svg x-show="boardMenu.backgroundType === 'color' && boardMenu.backgroundValue === color" class="w-4 h-4 text-white drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                  </svg>
                </button>
              </template>
            </div>
          </div>

          {{-- Custom Color Hex Input & Picker --}}
          <div class="flex items-center gap-2 pt-1">
            <label class="relative h-10 w-11 rounded-xl border border-slate-200 dark:border-slate-700 shadow-xs overflow-hidden cursor-pointer flex-shrink-0 flex items-center justify-center" title="Pick custom color">
              <input type="color" x-model="boardMenu.backgroundColorDraft"
                     @input="boardMenu.backgroundType = 'color'; boardMenu.backgroundValue = boardMenu.backgroundColorDraft"
                     class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
              <div class="w-full h-full" :style="'background-color: ' + boardMenu.backgroundColorDraft"></div>
            </label>

            <div class="relative flex-1">
              <input x-model="boardMenu.backgroundColorDraft"
                     @input="if(!$el.value.startsWith('#')) $el.value = '#' + $el.value; boardMenu.backgroundType = 'color'; boardMenu.backgroundValue = $el.value; boardMenu.backgroundColorDraft = $el.value;"
                     type="text"
                     maxlength="7"
                     class="w-full px-3 py-2 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white uppercase font-mono font-bold focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500"
                     placeholder="#6366F1">
            </div>

            <button type="button"
                    @click="boardMenu.backgroundType = 'color'; boardMenu.backgroundValue = boardMenu.backgroundColorDraft"
                    class="px-3 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-all flex-shrink-0">
              Apply Hex
            </button>
          </div>

          {{-- Custom Image URL or Upload --}}
          <div class="pt-2 border-t border-slate-100 dark:border-slate-800/60">
            <label class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Or Background Image:</label>
            <div class="flex gap-2 items-center">
              <input x-model="boardMenu.backgroundImageUrl"
                     @focus="boardMenu.backgroundType = 'image'; boardMenu.backgroundValue = boardMenu.backgroundImageUrl"
                     @input="boardMenu.backgroundType = 'image'; boardMenu.backgroundValue = boardMenu.backgroundImageUrl"
                     type="url"
                     maxlength="2048"
                     class="flex-1 min-w-0 px-3 py-2 text-xs bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500"
                     placeholder="https://example.com/image.jpg">
              <button type="button"
                      @click="$refs.boardSettingsBgUpload.click()"
                      class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 text-slate-700 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400 border border-slate-200 dark:border-slate-700 hover:border-indigo-300 transition-all flex items-center gap-1.5 shadow-xs flex-shrink-0"
                      :disabled="boardMenu.busy">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                <span>Upload</span>
              </button>
              <input x-ref="boardSettingsBgUpload" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="uploadBoardBackground($event)">
            </div>
          </div>
        </div>

        {{-- ── Permissions & Features Card ────────────────────────────── --}}
        <div class="board-menu-card rounded-2xl p-4 space-y-3.5 shadow-xs">
          <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-lg bg-sky-500/10 text-sky-500 flex items-center justify-center">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
            </div>
            <span class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-200">Permissions & Features</span>
          </div>

          {{-- Member permissions dropdown --}}
          <div>
            <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block mb-1.5">Who can edit content:</label>
            <div class="relative">
              <select x-model="boardMenu.settingsMemberPermissions"
                      class="w-full py-2.5 pl-3 pr-8 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white appearance-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500"
                      :disabled="!board.can_manage_board">
                <option value="admins">Only board admins can change content</option>
                <option value="members">Board members can change content</option>
                <option value="workspace">Workspace members can change content</option>
              </select>
              <svg class="w-4 h-4 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
            </div>
          </div>

          {{-- Feature Toggles --}}
          <div class="space-y-2 pt-1">
            {{-- Card Cover setting --}}
            <label class="board-menu-subcard flex items-center justify-between p-3 rounded-xl cursor-pointer hover:border-indigo-200 dark:hover:border-indigo-800 transition-all">
              <div class="flex items-center gap-3 pr-2">
                <div class="w-7 h-7 rounded-lg bg-indigo-500/10 text-indigo-500 flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z"/></svg>
                </div>
                <div>
                  <span class="block text-xs font-bold text-slate-800 dark:text-white">Card Covers</span>
                  <span class="block text-[11px] text-slate-500 dark:text-slate-400">Allow cards to display cover images</span>
                </div>
              </div>
              <input type="checkbox" x-model="boardMenu.settingsCardCoversEnabled" @change="saveBoardMenuSettings()"
                     class="rounded-md border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"
                     :disabled="!board.can_manage_board">
            </label>

            {{-- Board Notifications --}}
            <label class="board-menu-subcard flex items-center justify-between p-3 rounded-xl cursor-pointer hover:border-indigo-200 dark:hover:border-indigo-800 transition-all">
              <div class="flex items-center gap-3 pr-2">
                <div class="w-7 h-7 rounded-lg bg-sky-500/10 text-sky-500 flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                </div>
                <div>
                  <span class="block text-xs font-bold text-slate-800 dark:text-white">Activity Alerts</span>
                  <span class="block text-[11px] text-slate-500 dark:text-slate-400">Database and live broadcast notifications</span>
                </div>
              </div>
              <input type="checkbox" x-model="boardMenu.settingsNotificationsEnabled" @change="saveBoardMenuSettings()"
                     class="rounded-md border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"
                     :disabled="!board.can_manage_board">
            </label>

            {{-- Browser Desktop Notifications --}}
            <div class="board-menu-subcard p-3 rounded-xl space-y-2">
              <label class="flex items-center justify-between cursor-pointer">
                <div class="flex items-center gap-3 pr-2">
                  <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-500 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3"/></svg>
                  </div>
                  <div>
                    <span class="block text-xs font-bold text-slate-800 dark:text-white">Browser Push Prompts</span>
                    <span class="block text-[11px] text-slate-500 dark:text-slate-400">Desktop alerts on this browser</span>
                  </div>
                </div>
                <input type="checkbox" x-model="boardMenu.settingsBrowserNotificationsEnabled"
                       @change="if (boardMenu.settingsBrowserNotificationsEnabled) requestBrowserNotifications(); saveBoardMenuSettings();"
                       class="rounded-md border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"
                       :disabled="!board.can_manage_board">
              </label>

              <div class="pt-1.5 border-t border-slate-200/50 dark:border-slate-700/50 flex justify-end">
                <button type="button" @click="requestBrowserNotifications()"
                        class="text-[11px] font-bold text-sky-600 dark:text-sky-400 hover:underline inline-flex items-center gap-1"
                        :disabled="!board.can_manage_board">
                  <span>Request browser permission</span>
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        {{-- ── Save Settings Button ──────────────────────────────────── --}}
        <button type="button" @click="saveBoardMenuSettings()"
                :disabled="boardMenu.busy || !board.can_manage_board || !boardMenu.settingsName.trim()"
                class="w-full py-3 rounded-2xl text-xs sm:text-sm font-bold bg-gradient-to-r from-indigo-600 via-indigo-500 to-sky-500 hover:from-indigo-500 hover:to-sky-400 disabled:opacity-50 disabled:cursor-not-allowed text-white shadow-lg shadow-indigo-600/25 active:scale-95 transition-all flex items-center justify-center gap-2">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
          <span x-text="boardMenu.busy ? 'Saving Settings...' : 'Save Board Settings'"></span>
        </button>

        {{-- ── Danger Zone Card ──────────────────────────────────────── --}}
        <div class="rounded-2xl border border-rose-200/80 dark:border-rose-900/40 bg-rose-50/60 dark:bg-rose-950/20 p-4 space-y-3">
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <p class="text-xs font-black uppercase tracking-wider text-rose-600 dark:text-rose-400">Danger Zone</p>
          </div>

          <div class="space-y-2">
            <button type="button" x-show="board.can_delete_board" @click="deleteBoard()" :disabled="boardMenu.busy"
                    class="w-full py-2.5 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white shadow-md shadow-rose-600/25 active:scale-95 transition-all flex items-center justify-center gap-1.5">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
              <span>Delete Board</span>
            </button>

            @if(auth()->user()?->canManageBoards())
            <button type="button" @click="hideBoard()" :disabled="boardMenu.busy"
                    class="w-full py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition-all flex items-center justify-center gap-1.5">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
              <span>Hide Board</span>
            </button>
            @endif

            <p x-show="!board.can_delete_board" class="text-xs font-semibold leading-5 text-rose-600 dark:text-rose-400">
              Delete board is available to board admins only.
            </p>
          </div>
        </div>
      </section>

      {{-- ═════════════════════════════════════════════════════════════════ --}}
      {{-- VIEW: ABOUT                                                       --}}
      {{-- ═════════════════════════════════════════════════════════════════ --}}
      <section x-show="boardMenu.view === 'about'" class="space-y-4">
        <div>
          <h3 class="text-base font-extrabold text-slate-900 dark:text-white" x-text="board.name"></h3>
          <p class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400" x-text="sbmBoardWorkspaceName(board)"></p>
        </div>
        <div class="board-menu-card rounded-2xl p-4">
          <p class="text-[11px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">Description</p>
          <p class="mt-2 whitespace-pre-wrap text-xs sm:text-sm leading-6 text-slate-700 dark:text-slate-300" x-text="board.description || 'No description has been added yet.'"></p>
        </div>
      </section>

      {{-- ═════════════════════════════════════════════════════════════════ --}}
      {{-- VIEW: VISIBILITY                                                  --}}
      {{-- ═════════════════════════════════════════════════════════════════ --}}
      <section x-show="boardMenu.view === 'visibility'" class="space-y-3">
        <template x-for="option in ['private', 'workspace', 'public']" :key="option">
          <button type="button"
                  @click="saveBoardMenuVisibility(option)"
                  class="w-full rounded-2xl border p-4 text-left transition-all active:scale-98 cursor-pointer"
                  :class="boardMenu.settingsVisibility === option ? 'border-sky-400 bg-sky-50/80 dark:bg-sky-950/40 ring-2 ring-sky-400/30' : 'board-menu-card hover:border-slate-300 dark:hover:border-slate-700'">
            <span class="block text-sm font-extrabold capitalize text-slate-800 dark:text-white" x-text="option"></span>
            <span class="mt-1 block text-xs font-medium text-slate-500 dark:text-slate-400"
                  x-text="option === 'private' ? 'Only board members can access this board.' : (option === 'workspace' ? 'Workspace members can access this board.' : 'Anyone with access to boards can view this board.')"></span>
          </button>
        </template>
      </section>

      {{-- ═════════════════════════════════════════════════════════════════ --}}
      {{-- VIEW: SHARE / EXPORT                                              --}}
      {{-- ═════════════════════════════════════════════════════════════════ --}}
      <section x-show="boardMenu.view === 'share'" class="space-y-2">
        <button type="button" @click="openExportModal()" class="board-menu-row">
          <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg></span>
          <span>Export & Reports</span>
        </button>
        <button type="button" @click="copyCurrentBoardLink()" class="board-menu-row">
          <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg></span>
          <span>Copy board link</span>
        </button>
        <button type="button" @click="shareCurrentBoard()" class="board-menu-row">
          <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314M16.783 5.593a2.25 2.25 0 1 0 0-2.186 2.25 2.25 0 0 0 0 2.186Zm0 12.814a2.25 2.25 0 1 0 0 2.186 2.25 2.25 0 0 0 0-2.186Z"/></svg></span>
          <span>Share board</span>
        </button>
      </section>

      {{-- ═════════════════════════════════════════════════════════════════ --}}
      {{-- VIEW: CHANGE BACKGROUND                                           --}}
      {{-- ═════════════════════════════════════════════════════════════════ --}}
      <section x-show="boardMenu.view === 'background'" class="space-y-5">
        <div class="board-menu-card rounded-2xl p-4 shadow-xs">
          <p class="text-[11px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">Live board preview</p>
          <div class="mt-3 h-28 rounded-xl shadow-inner ring-1 ring-slate-900/10" :style="sbmBoardPreviewStyle(board)"></div>
        </div>

        <div>
          <p class="mb-2 text-[11px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">Colors</p>
          <div class="grid grid-cols-4 sm:grid-cols-5 gap-2 max-h-48 overflow-y-auto scrollbar-thin pr-1 py-1">
            <template x-for="color in boardMenu.backgroundColors" :key="color">
              <button type="button"
                      @click="boardMenu.backgroundColorDraft = color; saveBoardMenuBackground('color', color)"
                      class="h-12 rounded-xl transition hover:scale-105 active:scale-95 shadow-xs flex items-center justify-center cursor-pointer"
                      :class="board.background_type === 'color' && board.background_value === color ? 'ring-2 ring-indigo-500 dark:ring-sky-400 ring-offset-2 dark:ring-offset-slate-900 shadow-md' : 'ring-1 ring-black/5 dark:ring-white/10 hover:ring-slate-300'"
                      :style="'background:' + color"
                      :aria-label="'Set board background to ' + color">
                <svg x-show="board.background_type === 'color' && board.background_value === color" class="w-4 h-4 text-white drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                </svg>
              </button>
            </template>
          </div>
          <div class="mt-3 grid grid-cols-[auto_1fr_auto] gap-2 items-center">
            <label class="relative h-10 w-11 rounded-xl border border-slate-200 dark:border-slate-700 bg-white shadow-xs overflow-hidden cursor-pointer block" title="Choose custom color">
              <input type="color" x-model="boardMenu.backgroundColorDraft" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
              <div class="w-full h-full" :style="'background-color: ' + boardMenu.backgroundColorDraft"></div>
            </label>
            <input x-model="boardMenu.backgroundColorDraft" @input="if(!$el.value.startsWith('#')) $el.value = '#' + $el.value; boardMenu.backgroundColorDraft = $el.value;" type="text" maxlength="7"
                   class="w-full px-3 py-2 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white uppercase font-mono font-bold" placeholder="#2F68ED">
            <button type="button" @click="saveBoardMenuBackground('color', boardMenu.backgroundColorDraft)"
                    class="px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/20 active:scale-95 transition-all"
                    :disabled="boardMenu.busy">Apply</button>
          </div>
        </div>

        <div>
          <p class="mb-2 text-[11px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">Gradients</p>
          <div class="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto scrollbar-thin pr-1 py-1">
            <template x-for="gradient in boardMenu.backgroundGradients" :key="gradient">
              <button type="button"
                      @click="boardMenu.backgroundColorDraft = gradient; saveBoardMenuBackground('gradient', gradient)"
                      class="h-16 rounded-xl transition hover:scale-102 active:scale-98 shadow-xs flex items-center justify-center cursor-pointer"
                      :class="board.background_type === 'gradient' && board.background_value === gradient ? 'ring-2 ring-indigo-500 dark:ring-sky-400 ring-offset-2 dark:ring-offset-slate-900 shadow-md' : 'ring-1 ring-black/5 dark:ring-white/10 hover:ring-slate-300'"
                      :style="'background:' + gradient">
                <svg x-show="board.background_type === 'gradient' && board.background_value === gradient" class="w-4 h-4 text-white drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                </svg>
              </button>
            </template>
          </div>
        </div>

        <div class="rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 p-4 text-center">
          <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Upload Image</p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">JPG, PNG, GIF, or WebP up to 8 MB.</p>
          <button type="button" @click="$refs.boardBgUpload.click()"
                  class="mt-3 px-4 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition shadow-xs"
                  :disabled="boardMenu.busy">
            Choose background image
          </button>
          <input x-ref="boardBgUpload" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="uploadBoardBackground($event)">
        </div>

        <div>
          <label class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1">Image URL</label>
          <div class="flex gap-2">
            <input x-model="boardMenu.backgroundImageUrl" type="text" maxlength="2048"
                   class="flex-1 px-3 py-2 text-xs bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white"
                   placeholder="https://example.com/image.jpg">
            <button type="button" @click="saveBoardMenuBackground('image', boardMenu.backgroundImageUrl)"
                    class="px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/20 active:scale-95 transition-all"
                    :disabled="boardMenu.busy">Set</button>
          </div>
        </div>
      </section>

      {{-- ═════════════════════════════════════════════════════════════════ --}}
      {{-- VIEW: LABELS                                                      --}}
      {{-- ═════════════════════════════════════════════════════════════════ --}}
      <section x-show="boardMenu.view === 'labels'" class="space-y-3">
        <template x-for="lbl in labels" :key="lbl.id">
          <div class="flex items-center gap-3 rounded-2xl board-menu-card p-3 shadow-xs">
            <span class="h-8 w-12 rounded-xl shadow-xs ring-1 ring-black/5 dark:ring-white/10" :style="'background:' + lbl.color"></span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-bold text-slate-800 dark:text-white" x-text="lbl.name || 'Unnamed label'"></p>
              <p class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase font-mono" x-text="lbl.color"></p>
            </div>
          </div>
        </template>
        <div x-show="labels.length === 0" class="rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 p-6 text-center text-xs font-semibold text-slate-500">
          No labels have been created for this board.
        </div>
      </section>

      {{-- ═════════════════════════════════════════════════════════════════ --}}
      {{-- VIEW: ACTIVITY                                                    --}}
      {{-- ═════════════════════════════════════════════════════════════════ --}}
      <section x-show="boardMenu.view === 'activity'" class="space-y-3">
        <template x-for="act in activities" :key="act.id">
          <div class="flex gap-3 text-xs leading-5 p-3 rounded-2xl board-menu-card shadow-xs">
            <img :src="act.user_avatar || window.dgtInitialsAvatar(act.user_name || 'System', act.user_avatar_color || '#64748b')" class="mt-0.5 h-8 w-8 flex-shrink-0 rounded-full border border-slate-200 dark:border-slate-700 object-cover">
            <div class="min-w-0 flex-1">
              <p class="text-slate-700 dark:text-slate-300">
                <strong class="font-bold text-slate-900 dark:text-white" x-text="act.user_name"></strong>
                <span x-html="parseMarkdown(act.description || '')"></span>
              </p>
              <span class="mt-1 block text-[10px] font-bold text-slate-400 dark:text-slate-500" x-text="act.time_ago"></span>
            </div>
          </div>
        </template>
        <div x-show="activities.length === 0" class="rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 p-6 text-center text-xs font-semibold text-slate-500">
          No activity recorded yet.
        </div>
      </section>

      {{-- ═════════════════════════════════════════════════════════════════ --}}
      {{-- VIEW: ARCHIVED                                                    --}}
      {{-- ═════════════════════════════════════════════════════════════════ --}}
      <section x-show="boardMenu.view === 'archived'" class="space-y-4">
        <div class="grid grid-cols-2 rounded-xl bg-slate-100 dark:bg-slate-800 p-1">
          <button type="button" @click="boardMenu.archivedTab = 'cards'" class="rounded-lg px-3 py-2 text-xs font-bold transition" :class="boardMenu.archivedTab === 'cards' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 dark:text-slate-400'">Cards</button>
          <button type="button" @click="boardMenu.archivedTab = 'lists'" class="rounded-lg px-3 py-2 text-xs font-bold transition" :class="boardMenu.archivedTab === 'lists' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 dark:text-slate-400'">Lists</button>
        </div>
        <div x-show="boardMenu.archivedLoading" class="rounded-2xl board-menu-card p-5 text-center text-xs font-semibold text-slate-500">Loading archived items...</div>
        <div x-show="!boardMenu.archivedLoading && boardMenu.archivedTab === 'cards'" class="space-y-2">
          <template x-for="card in boardMenu.archivedCards" :key="card.id">
            <div class="rounded-2xl board-menu-card p-3.5 shadow-xs">
              <p class="text-sm font-bold text-slate-800 dark:text-white" x-text="card.title"></p>
              <p class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400"><span x-text="card.list_name"></span> &middot; <span x-text="card.archived_at || 'Archived'"></span></p>
              <button type="button" @click="restoreArchivedItem('card', card.id)" class="mt-3 text-xs font-bold text-sky-600 dark:text-sky-400 hover:underline">Restore card</button>
            </div>
          </template>
          <div x-show="boardMenu.archivedCards.length === 0" class="rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 p-6 text-center text-xs font-semibold text-slate-500">No archived cards.</div>
        </div>
        <div x-show="!boardMenu.archivedLoading && boardMenu.archivedTab === 'lists'" class="space-y-2">
          <template x-for="list in boardMenu.archivedLists" :key="list.id">
            <div class="rounded-2xl board-menu-card p-3.5 shadow-xs">
              <p class="text-sm font-bold text-slate-800 dark:text-white" x-text="list.name"></p>
              <p class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400"><span x-text="list.card_count"></span> cards &middot; <span x-text="list.archived_at || 'Archived'"></span></p>
              <button type="button" @click="restoreArchivedItem('list', list.id)" class="mt-3 text-xs font-bold text-sky-600 dark:text-sky-400 hover:underline">Restore list</button>
            </div>
          </template>
          <div x-show="boardMenu.archivedLists.length === 0" class="rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 p-6 text-center text-xs font-semibold text-slate-500">No archived lists.</div>
        </div>
      </section>

      {{-- ═════════════════════════════════════════════════════════════════ --}}
      {{-- VIEW: TRASH                                                       --}}
      {{-- ═════════════════════════════════════════════════════════════════ --}}
      <section x-show="boardMenu.view === 'trash'" class="space-y-4">
        <div class="rounded-2xl border border-sky-200 dark:border-sky-900/50 bg-sky-50/70 dark:bg-sky-950/40 p-3.5 flex items-start gap-2.5 text-xs text-sky-900 dark:text-sky-200">
          <span class="text-sm flex-shrink-0 mt-0.5">ℹ️</span>
          <div>
            <p class="font-bold text-sky-950 dark:text-sky-100">7-Day Trash Retention</p>
            <p class="text-slate-600 dark:text-slate-300 mt-0.5 leading-relaxed">Deleted cards and lists remain in Trash for <strong>7 days</strong> before being automatically removed. You can restore them at any time.</p>
          </div>
        </div>

        <div class="grid grid-cols-2 rounded-xl bg-slate-100 dark:bg-slate-800 p-1">
          <button type="button" @click="boardMenu.trashTab = 'cards'; boardMenu.selectedTrashItems = []" class="rounded-lg px-3 py-2 text-xs font-bold transition" :class="boardMenu.trashTab === 'cards' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 dark:text-slate-400'">Cards</button>
          <button type="button" @click="boardMenu.trashTab = 'lists'; boardMenu.selectedTrashItems = []" class="rounded-lg px-3 py-2 text-xs font-bold transition" :class="boardMenu.trashTab === 'lists' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 dark:text-slate-400'">Lists</button>
        </div>
        
        <div x-show="boardMenu.trashLoading" class="rounded-2xl board-menu-card p-5 text-center text-xs font-semibold text-slate-500">Loading trash items...</div>
        
        <div x-show="!boardMenu.trashLoading" class="space-y-2">
          <div x-show="filteredTrashItems().length > 0" class="flex flex-col gap-3 mb-4 p-3 board-menu-card rounded-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" class="rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-600 w-4 h-4 cursor-pointer"
                  :checked="filteredTrashItems().length > 0 && boardMenu.selectedTrashItems.length === filteredTrashItems().length"
                  :indeterminate="boardMenu.selectedTrashItems.length > 0 && boardMenu.selectedTrashItems.length < filteredTrashItems().length"
                  @change="toggleTrashSelectAll($event.target.checked)">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200">Select All (<span x-text="boardMenu.selectedTrashItems.length"></span>/<span x-text="filteredTrashItems().length"></span>)</span>
              </label>
            </div>
            <div class="flex items-center gap-2" x-show="boardMenu.selectedTrashItems.length > 0" x-cloak>
              <button type="button" @click="restoreSelectedTrashItems()" class="flex-1 py-1.5 px-3 bg-sky-50 dark:bg-sky-950/60 hover:bg-sky-100 dark:hover:bg-sky-900 text-sky-700 dark:text-sky-300 rounded-xl text-xs font-bold transition-colors">
                Restore Selected
              </button>
              <button type="button" @click="forceDeleteSelectedTrashItems()" class="flex-1 py-1.5 px-3 bg-rose-50 dark:bg-rose-950/60 hover:bg-rose-100 dark:hover:bg-rose-900 text-rose-600 dark:text-rose-400 rounded-xl text-xs font-bold transition-colors">
                Delete Selected
              </button>
            </div>
          </div>

          <template x-for="item in filteredTrashItems()" :key="item.id + item.type">
            <label class="block rounded-2xl board-menu-card p-3.5 cursor-pointer hover:border-indigo-300 dark:hover:border-indigo-700 hover:shadow-xs transition-all" :class="{'ring-1 ring-indigo-500 border-indigo-500 bg-indigo-50/20 dark:bg-indigo-950/30': isTrashSelected(item.type, item.id)}">
              <div class="flex items-start gap-3">
                <div class="pt-0.5">
                  <input type="checkbox" class="rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-600 w-4 h-4 cursor-pointer"
                    :checked="isTrashSelected(item.type, item.id)"
                    @change="toggleTrashSelect(item.type, item.id, $event.target.checked)">
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-bold text-slate-800 dark:text-white truncate" x-text="item.name || item.title"></p>
                  <p class="mt-0.5 text-xs font-medium text-slate-400 dark:text-slate-500 capitalize"><span x-text="item.type"></span> &middot; Deleted <span x-text="new Date(item.deleted_at).toLocaleDateString()"></span></p>
                  <div class="mt-2.5 flex items-center justify-between">
                    <button type="button" @click.prevent="restoreTrashItem(item.type, item.id)" class="text-xs font-bold text-sky-600 dark:text-sky-400 hover:underline">Restore</button>
                    <button type="button" @click.prevent="forceDeleteTrashItem(item.type, item.id)" class="text-xs font-bold text-rose-600 dark:text-rose-400 hover:underline">Delete forever</button>
                  </div>
                </div>
              </div>
            </label>
          </template>
          <div x-show="filteredTrashItems().length === 0" class="rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 p-6 text-center text-xs font-semibold text-slate-500" x-text="boardMenu.trashTab === 'cards' ? 'No deleted cards.' : 'No deleted lists.'"></div>
        </div>
      </section>

      {{-- ═════════════════════════════════════════════════════════════════ --}}
      {{-- VIEW: COPY BOARD                                                  --}}
      {{-- ═════════════════════════════════════════════════════════════════ --}}
      <section x-show="boardMenu.view === 'copy'" class="space-y-4">
        <div class="board-menu-card rounded-2xl p-4 space-y-3.5 shadow-xs">
          <div>
            <label class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider block mb-1.5">Copied board name</label>
            <input x-model="boardMenu.copyName" type="text" maxlength="100" class="w-full px-3.5 py-2.5 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white font-semibold">
          </div>
          <label class="flex cursor-pointer items-center gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-700 text-xs sm:text-sm font-bold text-slate-700 dark:text-slate-200">
            <input type="checkbox" x-model="boardMenu.copyIncludeCards" class="rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
            <span>Include cards from current board</span>
          </label>
          <button type="button" @click="copyBoard()" :disabled="boardMenu.busy || !boardMenu.copyName.trim()"
                  class="w-full py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/20 active:scale-95 transition-all">
            Copy Board
          </button>
          <a x-show="boardMenu.copiedBoardUrl" :href="boardMenu.copiedBoardUrl" class="block w-full py-2.5 text-center rounded-xl text-xs sm:text-sm font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-all">
            Open Copied Board
          </a>
        </div>
      </section>

      {{-- ═════════════════════════════════════════════════════════════════ --}}
      {{-- VIEW: AUTOMATIONS                                                 --}}
      {{-- ═════════════════════════════════════════════════════════════════ --}}
      <section x-show="boardMenu.view === 'automation'" class="space-y-4">
        <div class="board-menu-card rounded-2xl p-4 shadow-xs">
          <p class="text-sm font-bold text-slate-800 dark:text-white">Card Automation Rules</p>
          <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Automatically copy or move cards to another board when card titles or comments match conditions.</p>
        </div>

        <div class="space-y-3">
          <template x-for="rule in automations" :key="rule.id">
            <div class="board-menu-card rounded-2xl shadow-xs hover:shadow-md transition-shadow p-4 flex justify-between items-start relative group">
              <div class="space-y-3 w-full pr-8">
                <!-- Trigger Section -->
                <div class="flex items-start gap-2.5">
                   <div class="mt-0.5 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 rounded-lg p-1.5 border border-indigo-100 dark:border-indigo-900/60">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                   </div>
                   <div>
                     <p class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-wider">Trigger</p>
                     <p x-show="rule.trigger_word" class="text-xs font-semibold text-slate-800 dark:text-slate-200 mt-1">
                       Title/comment contains: <span class="font-black text-indigo-700 dark:text-indigo-400 bg-indigo-100/50 dark:bg-indigo-950/60 px-1.5 py-0.5 rounded ml-1" x-text="`&quot;${rule.trigger_word}&quot;`"></span>
                     </p>
                     <p x-show="rule.trigger_list_id" class="text-xs text-slate-600 dark:text-slate-400 mt-1">
                       In list: <span class="font-bold text-slate-800 dark:text-slate-200 ml-1" x-text="rule.trigger_list?.name"></span>
                       <span x-show="rule.trigger_board_id" class="text-slate-400"> (on <span x-text="rule.trigger_board?.name"></span>)</span>
                     </p>
                   </div>
                </div>

                <div class="w-px h-3 bg-slate-200 dark:bg-slate-700 ml-3.5"></div>

                <!-- Action Section -->
                <div class="flex items-start gap-2.5">
                   <div class="mt-0.5 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 rounded-lg p-1.5 border border-emerald-100 dark:border-emerald-900/60">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                   </div>
                   <div>
                     <p class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-wider">Action</p>
                     <p class="text-xs text-slate-800 dark:text-slate-200 mt-1">
                       <span class="font-bold" x-text="rule.action_type === 'copy' ? 'Copy to' : 'Move to'"></span>
                       <span class="font-bold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded ml-1 border border-slate-200 dark:border-slate-700" x-text="rule.target_list?.name"></span>
                       <span class="text-xs text-slate-500"> on <span x-text="rule.target_board?.name"></span></span>
                     </p>
                     
                     <template x-if="rule.target_assignee_id || rule.target_assignee_role">
                       <div class="flex items-center gap-1.5 mt-2">
                         <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                         <p x-show="rule.target_assignee_id" class="text-xs font-medium text-slate-600 dark:text-slate-400">
                           Assign: <span class="font-bold text-indigo-600 dark:text-indigo-400" x-text="rule.target_assignee?.name"></span>
                         </p>
                         <p x-show="rule.target_assignee_role" class="text-xs font-medium text-slate-600 dark:text-slate-400">
                           Assign Role: <span class="font-bold text-indigo-600 dark:text-indigo-400" x-text="rule.target_assignee_role"></span>
                         </p>
                       </div>
                     </template>
                   </div>
                </div>
              </div>
              
              <div class="absolute top-3 right-3 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button @click="editAutomation(rule)" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-slate-800 rounded-lg transition-colors" title="Edit Rule">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                </button>
                <button @click="deleteAutomation(rule.id)" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition-colors" title="Delete Rule">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </button>
              </div>
            </div>
          </template>
          <div x-show="automations.length === 0" class="rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 p-6 text-center text-xs font-semibold text-slate-500">
            No automations configured.
          </div>
        </div>

        <div id="automation-form" class="board-menu-card rounded-2xl p-4 space-y-3 shadow-xs">
          <p class="text-xs font-black uppercase tracking-wider text-slate-400 dark:text-slate-500" x-text="newAutomation.id ? 'Edit Rule' : 'Add New Rule'"></p>
          
          <div>
            <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block mb-1">Filter word (optional)</label>
            <input x-model="newAutomation.trigger_word" type="text" class="w-full px-3 py-2 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white" placeholder="e.g. DONE">
          </div>

          <div>
            <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block mb-1">From Board (optional)</label>
            <select x-model="newAutomation.trigger_board_id" @change="fetchTriggerLists()" class="w-full px-3 py-2 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white">
              <option value="">Current board</option>
              <template x-for="ws in allWorkspaces" :key="ws.id">
                <optgroup :label="ws.name">
                  <template x-for="b in ws.boards" :key="b.id">
                    <option :value="b.id" x-text="b.name"></option>
                  </template>
                </optgroup>
              </template>
            </select>
          </div>

          <div>
            <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block mb-1">From list (optional)</label>
            <select x-model="newAutomation.trigger_list_id" class="w-full px-3 py-2 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white">
              <option value="">Any list</option>
              <template x-for="list in triggerBoardLists" :key="list.id">
                <option :value="list.id" x-text="list.name"></option>
              </template>
            </select>
          </div>

          <div>
            <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block mb-1">Action Type</label>
            <select x-model="newAutomation.action_type" class="w-full px-3 py-2 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white">
              <option value="move">Move Card</option>
              <option value="copy">Copy Card</option>
            </select>
          </div>

          <div>
            <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block mb-1">Target Board</label>
            <select x-model="newAutomation.target_board_id" @change="fetchTargetLists()" class="w-full px-3 py-2 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white">
              <option value="">Select board...</option>
              <template x-for="ws in allWorkspaces" :key="ws.id">
                <optgroup :label="ws.name">
                  <template x-for="b in ws.boards" :key="b.id">
                    <option :value="b.id" x-text="b.name"></option>
                  </template>
                </optgroup>
              </template>
            </select>
          </div>
          <div x-show="newAutomation.target_board_id" class="space-y-3">
            <div>
              <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block mb-1">Target List</label>
              <select x-model="newAutomation.target_list_id" class="w-full px-3 py-2 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white">
                <option value="">Select list...</option>
                <template x-for="list in targetBoardLists" :key="list.id">
                  <option :value="list.id" x-text="list.name"></option>
                </template>
              </select>
            </div>
            
            <div>
              <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block mb-1">Assign Member (optional)</label>
              <select x-model="newAutomation.combined_assignee" class="w-full px-3 py-2 text-xs sm:text-sm bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white">
                <option value="">Do not assign</option>
                <optgroup label="Auto-Assign Role">
                  <option value="role_Standard Member">Role: Digital Team (Standard Members)</option>
                  <option value="role_Graphic Head">Role: Graphic Head</option>
                  <option value="role_Listing Head">Role: Listing Head</option>
                  <option value="role_Video Head">Role: Video Head</option>
                  <option value="role_QC">Role: QC</option>
                  <option value="role_Supervisor">Role: Supervisor</option>
                </optgroup>
                <optgroup label="Specific Member">
                  <template x-for="member in targetBoardMembers" :key="member.id">
                    <option :value="'user_' + member.id" x-text="member.name"></option>
                  </template>
                </optgroup>
              </select>
            </div>
          </div>
          <button type="button" @click="saveAutomation()" 
            :disabled="(!newAutomation.trigger_word && !newAutomation.trigger_list_id) || !newAutomation.target_board_id || !newAutomation.target_list_id || boardMenu.busy" 
            class="w-full py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/20 active:scale-95 transition-all"
            x-text="newAutomation.id ? 'Update Automation' : 'Add Automation'">
          </button>
          <button type="button" x-show="newAutomation.id" @click="resetAutomationForm()"
            class="w-full py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-all">
            Cancel Edit
          </button>
        </div>
      </section>

      {{-- ═════════════════════════════════════════════════════════════════ --}}
      {{-- VIEW: LEAVE BOARD                                                 --}}
      {{-- ═════════════════════════════════════════════════════════════════ --}}
      <section x-show="boardMenu.view === 'leave'" class="space-y-4">
        <div class="rounded-2xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/60 dark:bg-rose-950/20 p-4">
          <h3 class="text-sm font-bold text-rose-700 dark:text-rose-400">Leave this board?</h3>
          <p class="mt-1 text-xs leading-5 text-rose-600 dark:text-rose-300">You will lose direct board access unless your workspace role still grants it.</p>
        </div>
        <button type="button" @click="leaveBoard()" class="w-full py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-rose-600 hover:bg-rose-500 text-white shadow-md shadow-rose-600/20 active:scale-95 transition-all">Leave board</button>
      </section>
    </div>
  </aside>
</div>
