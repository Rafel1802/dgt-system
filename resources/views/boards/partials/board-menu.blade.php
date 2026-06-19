{{--
  Trello-style right-side board menu.
  State and actions live in public/js/trello-board.js under boardMenu.
--}}
<div x-show="boardMenu.open" x-cloak class="fixed inset-0 z-[60] overflow-hidden" @keydown.escape.window="closeBoardMenu()">
  <div class="absolute inset-0 bg-slate-950/30 backdrop-blur-[2px]"
       x-show="boardMenu.open"
       x-transition.opacity
       @click="closeBoardMenu()"></div>

  <aside class="fixed inset-y-0 right-0 flex w-screen max-w-sm flex-col border-l border-slate-200 bg-white shadow-2xl"
         x-show="boardMenu.open"
         x-transition:enter="transform transition ease-out duration-200"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in duration-150"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         @click.stop>
    <header class="flex h-14 flex-shrink-0 items-center gap-2 border-b border-slate-200 px-4">
      <button type="button"
              x-show="boardMenu.view !== 'menu'"
              @click="openBoardMenuView('menu')"
              class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
              aria-label="Back to board menu">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
      </button>

      <h2 class="min-w-0 flex-1 truncate text-center text-sm font-black text-slate-800" x-text="boardMenuTitle()"></h2>

      <button type="button"
              @click="closeBoardMenu()"
              class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
              aria-label="Close board menu">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
      </button>
    </header>

    <div class="flex-1 overflow-y-auto p-4 scrollbar-thin">
      <div x-show="boardMenu.view === 'menu'" class="space-y-4">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
          <div class="h-24 rounded-lg shadow-inner ring-1 ring-slate-900/5"
               :style="sbmBoardPreviewStyle(board)"></div>
          <div class="mt-3">
            <h3 class="truncate text-sm font-black text-slate-900" x-text="board.name"></h3>
            <p class="text-xs font-semibold text-slate-500" x-text="sbmBoardWorkspaceName(board)"></p>
          </div>
        </div>

        <nav class="space-y-1">
          <button type="button" @click="openBoardMenuView('about')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25h1.5v6h-1.5zM12 7.5h.008v.008H12z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span>
            <span>About this board</span>
          </button>
          <button type="button" @click="openBoardMenuView('visibility')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 0 0-9 0v3.75M6.75 10.5h10.5A2.25 2.25 0 0 1 19.5 12.75v6A2.25 2.25 0 0 1 17.25 21H6.75A2.25 2.25 0 0 1 4.5 18.75v-6a2.25 2.25 0 0 1 2.25-2.25Z"/></svg></span>
            <span>Visibility</span>
            <span class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase text-slate-500" x-text="board.visibility || 'workspace'"></span>
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
          @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'supervisor']))
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
          <button type="button" @click="openBoardMenuView('watch')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.43 0 .644C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg></span>
            <span>Watch board</span>
            <span x-show="boardMenu.watched" class="ml-auto rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-black uppercase text-sky-700">On</span>
          </button>
          <button type="button" @click="openBoardMenuView('copy')" class="board-menu-row">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75m9 10.5h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876A9.06 9.06 0 0 0 11.25 2.25H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25"/></svg></span>
            <span>Copy board</span>
          </button>
          <button type="button" @click="openBoardMenuView('leave')" class="board-menu-row text-rose-600 hover:bg-rose-50">
            <span class="board-menu-icon"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/></svg></span>
            <span>Leave board</span>
          </button>
        </nav>
      </div>

      <section x-show="boardMenu.view === 'about'" class="space-y-4">
        <div>
          <h3 class="text-sm font-black text-slate-900" x-text="board.name"></h3>
          <p class="mt-1 text-xs font-semibold text-slate-500" x-text="sbmBoardWorkspaceName(board)"></p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-xs font-black uppercase tracking-wider text-slate-400">Description</p>
          <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700" x-text="board.description || 'No description has been added yet.'"></p>
        </div>
      </section>

      <section x-show="boardMenu.view === 'visibility'" class="space-y-3">
        <template x-for="option in ['private', 'workspace', 'public']" :key="option">
          <button type="button"
                  @click="saveBoardMenuVisibility(option)"
                  class="w-full rounded-xl border p-4 text-left transition"
                  :class="boardMenu.settingsVisibility === option ? 'border-sky-300 bg-sky-50 ring-2 ring-sky-100' : 'border-slate-200 bg-white hover:bg-slate-50'">
            <span class="block text-sm font-black capitalize text-slate-800" x-text="option"></span>
            <span class="mt-1 block text-xs font-medium text-slate-500"
                  x-text="option === 'private' ? 'Only board members can access this board.' : (option === 'workspace' ? 'Workspace members can access this board.' : 'Anyone with access to boards can view this board.')"></span>
          </button>
        </template>
      </section>

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

      <section x-show="boardMenu.view === 'settings'" class="space-y-5">
        <div x-show="!board.can_manage_board" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-semibold leading-5 text-amber-700">
          You can view these settings, but only board admins can save changes.
        </div>

        <div class="space-y-4">
          <div>
            <label class="form-label text-xs">Board name</label>
            <input x-model="boardMenu.settingsName" type="text" maxlength="100" class="form-input text-sm" :disabled="!board.can_manage_board">
          </div>

          <div>
            <label class="form-label text-xs">Board description</label>
            <textarea x-model="boardMenu.settingsDescription" rows="4" maxlength="5000" class="form-input resize-none text-sm" placeholder="Add board context, goals, or ownership notes." :disabled="!board.can_manage_board"></textarea>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="form-label text-xs">Workspace</label>
              <select x-model="boardMenu.settingsWorkspaceId" class="form-input text-sm" :disabled="!board.can_manage_board">
                <template x-for="workspace in allWorkspaces" :key="workspace.id">
                  <option :value="workspace.id" x-text="workspace.name"></option>
                </template>
              </select>
            </div>

            <div>
              <label class="form-label text-xs">Visibility</label>
              <select x-model="boardMenu.settingsVisibility" class="form-input text-sm" :disabled="!board.can_manage_board">
                <option value="private">Private</option>
                <option value="workspace">Workspace</option>
                <option value="public">Public</option>
              </select>
            </div>
          </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <p class="mb-3 text-xs font-black uppercase tracking-wider text-slate-400">Background</p>
          <div class="grid grid-cols-4 gap-2">
            <template x-for="color in boardMenu.backgroundColors" :key="color">
              <button type="button"
                      @click="boardMenu.backgroundType = 'color'; boardMenu.backgroundValue = color; boardMenu.backgroundColorDraft = color"
                      class="h-11 rounded-lg ring-offset-2 transition hover:scale-[1.02]"
                      :class="boardMenu.backgroundType === 'color' && boardMenu.backgroundValue === color ? 'ring-2 ring-sky-500' : 'ring-1 ring-slate-200'"
                      :style="'background:' + color"
                      :disabled="!board.can_manage_board"></button>
            </template>
          </div>
          <div class="mt-4 grid grid-cols-[auto_1fr] gap-2">
            <input x-model="boardMenu.backgroundColorDraft"
                   @input="boardMenu.backgroundType = 'color'; boardMenu.backgroundValue = boardMenu.backgroundColorDraft"
                   type="color"
                   class="h-10 w-12 cursor-pointer rounded-lg border border-slate-200 bg-white p-1"
                   :disabled="!board.can_manage_board">
            <input x-model="boardMenu.backgroundColorDraft"
                   @input="boardMenu.backgroundType = 'color'; boardMenu.backgroundValue = boardMenu.backgroundColorDraft"
                   type="text"
                   maxlength="7"
                   class="form-input text-sm"
                   placeholder="#2F68ED"
                   :disabled="!board.can_manage_board">
          </div>
          <div class="mt-3 flex gap-2">
            <input x-model="boardMenu.backgroundImageUrl"
                   @focus="boardMenu.backgroundType = 'image'; boardMenu.backgroundValue = boardMenu.backgroundImageUrl"
                   @input="boardMenu.backgroundType = 'image'; boardMenu.backgroundValue = boardMenu.backgroundImageUrl"
                   type="url"
                   maxlength="2048"
                   class="form-input text-sm"
                   placeholder="https://example.com/board-background.jpg"
                   :disabled="!board.can_manage_board">
            <button type="button"
                    @click="$refs.boardSettingsBgUpload.click()"
                    class="btn btn-secondary flex-shrink-0"
                    :disabled="boardMenu.busy || !board.can_manage_board">
              Upload
            </button>
            <input x-ref="boardSettingsBgUpload" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="uploadBoardBackground($event)">
          </div>
        </div>

        <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
          <div>
            <label class="form-label text-xs">Member permissions</label>
            <select x-model="boardMenu.settingsMemberPermissions" class="form-input text-sm" :disabled="!board.can_manage_board">
              <option value="admins">Only board admins can change board content</option>
              <option value="members">Board members can change board content</option>
              <option value="workspace">Workspace members can change board content</option>
            </select>
          </div>

          <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
            <input type="checkbox" x-model="boardMenu.settingsCardCoversEnabled" @change="saveBoardMenuSettings()" class="mt-1 rounded border-slate-300 text-sky-600 focus:ring-sky-500" :disabled="!board.can_manage_board">
            <span>
              <span class="block text-sm font-black text-slate-800">Card cover setting</span>
              <span class="block text-xs leading-5 text-slate-500">Allow cards on this board to use cover images.</span>
            </span>
          </label>

          <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
            <input type="checkbox" x-model="boardMenu.settingsNotificationsEnabled" @change="saveBoardMenuSettings()" class="mt-1 rounded border-slate-300 text-sky-600 focus:ring-sky-500" :disabled="!board.can_manage_board">
            <span>
              <span class="block text-sm font-black text-slate-800">Enable notifications</span>
              <span class="block text-xs leading-5 text-slate-500">Send database and broadcast notifications for board activity.</span>
            </span>
          </label>

          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <label class="flex items-start gap-3">
              <input type="checkbox" x-model="boardMenu.settingsBrowserNotificationsEnabled" @change="if (boardMenu.settingsBrowserNotificationsEnabled) requestBrowserNotifications(); saveBoardMenuSettings();" class="mt-1 rounded border-slate-300 text-sky-600 focus:ring-sky-500" :disabled="!board.can_manage_board">
              <span>
                <span class="block text-sm font-black text-slate-800">Enable browser notifications</span>
                <span class="block text-xs leading-5 text-slate-500">Keep this board marked for desktop notification prompts on this browser.</span>
              </span>
            </label>
            <button type="button" @click="requestBrowserNotifications()" class="mt-3 text-xs font-black text-sky-700 hover:underline" :disabled="!board.can_manage_board">
              Request browser permission
            </button>
          </div>


        </div>

        <button type="button" @click="saveBoardMenuSettings()" :disabled="boardMenu.busy || !board.can_manage_board || !boardMenu.settingsName.trim()" class="btn btn-primary w-full justify-center">
          Save board settings
        </button>

        <div class="space-y-3 rounded-xl border border-rose-200 bg-rose-50 p-4">
          <p class="text-xs font-black uppercase tracking-wider text-rose-400">Danger zone</p>
          <button type="button" @click="archiveBoard()" :disabled="boardMenu.busy || !board.can_manage_board" class="btn btn-secondary w-full justify-center border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100">
            Archive board
          </button>
          <button type="button" x-show="board.can_delete_board" @click="deleteBoard()" :disabled="boardMenu.busy" class="btn btn-danger w-full justify-center">
            Delete board
          </button>
          @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital']))
          <button type="button" @click="hideBoard()" :disabled="boardMenu.busy" class="btn btn-secondary w-full justify-center border-slate-300 bg-slate-100 text-slate-700 hover:bg-slate-200">
            Hide board
          </button>
          @endif
          <p x-show="!board.can_delete_board" class="text-xs font-semibold leading-5 text-rose-600">
            Delete board is available to board admins only.
          </p>
        </div>
      </section>

      <section x-show="boardMenu.view === 'background'" class="space-y-5">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-xs font-black uppercase tracking-wider text-slate-400">Live board preview</p>
          <div class="mt-3 h-28 rounded-xl shadow-inner ring-1 ring-slate-900/5" :style="sbmBoardPreviewStyle(board)"></div>
        </div>

        <div>
          <p class="mb-2 text-xs font-black uppercase tracking-wider text-slate-400">Colors</p>
          <div class="grid grid-cols-4 gap-2">
            <template x-for="color in boardMenu.backgroundColors" :key="color">
              <button type="button"
                      @click="saveBoardMenuBackground('color', color)"
                      class="h-14 rounded-xl ring-offset-2 transition hover:scale-[1.02]"
                      :class="board.background_type === 'color' && board.background_value === color ? 'ring-2 ring-sky-500' : 'ring-1 ring-slate-200'"
                      :style="'background:' + color"
                      :aria-label="'Set board background to ' + color"></button>
            </template>
          </div>
          <div class="mt-3 grid grid-cols-[auto_1fr_auto] gap-2">
            <input x-model="boardMenu.backgroundColorDraft" type="color" class="h-11 w-12 cursor-pointer rounded-xl border border-slate-200 bg-white p-1">
            <input x-model="boardMenu.backgroundColorDraft" type="text" maxlength="7" class="form-input text-sm" placeholder="#2F68ED">
            <button type="button" @click="saveBoardMenuBackground('color', boardMenu.backgroundColorDraft)" class="btn btn-primary flex-shrink-0" :disabled="boardMenu.busy">Apply</button>
          </div>
        </div>

        <div>
          <p class="mb-2 text-xs font-black uppercase tracking-wider text-slate-400">Gradients</p>
          <div class="grid grid-cols-2 gap-2">
            <template x-for="gradient in boardMenu.backgroundGradients" :key="gradient">
              <button type="button"
                      @click="saveBoardMenuBackground('gradient', gradient)"
                      class="h-16 rounded-xl ring-offset-2 transition hover:scale-[1.02]"
                      :class="board.background_type === 'gradient' && board.background_value === gradient ? 'ring-2 ring-sky-500' : 'ring-1 ring-slate-200'"
                      :style="'background:' + gradient"></button>
            </template>
          </div>
        </div>

        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
          <p class="text-xs font-black uppercase tracking-wider text-slate-400">Upload image</p>
          <p class="mt-1 text-xs font-semibold leading-5 text-slate-500">JPG, PNG, GIF, or WebP up to 8 MB.</p>
          <button type="button" @click="$refs.boardBgUpload.click()" class="btn btn-secondary mt-3 w-full justify-center" :disabled="boardMenu.busy">
            Choose background image
          </button>
          <input x-ref="boardBgUpload" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="uploadBoardBackground($event)">
        </div>

        <div>
          <label class="form-label text-xs">Image URL</label>
          <div class="flex gap-2">
            <input x-model="boardMenu.backgroundImageUrl" type="url" maxlength="2048" class="form-input text-sm" placeholder="https://example.com/image.jpg">
            <button type="button" @click="saveBoardMenuBackground('image', boardMenu.backgroundImageUrl)" class="btn btn-primary flex-shrink-0" :disabled="boardMenu.busy">Set</button>
          </div>
        </div>
      </section>

      <section x-show="boardMenu.view === 'labels'" class="space-y-3">
        <template x-for="lbl in labels" :key="lbl.id">
          <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3">
            <span class="h-8 w-12 rounded-lg shadow-sm ring-1 ring-slate-900/5" :style="'background:' + lbl.color"></span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-black text-slate-800" x-text="lbl.name || 'Unnamed label'"></p>
              <p class="text-xs font-semibold text-slate-500" x-text="lbl.color"></p>
            </div>
          </div>
        </template>
        <div x-show="labels.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-center text-sm font-semibold text-slate-500">
          No labels have been created for this board.
        </div>
      </section>

      <section x-show="boardMenu.view === 'activity'" class="space-y-4">
        <template x-for="act in activities" :key="act.id">
          <div class="flex gap-3 text-xs leading-5">
            <img :src="act.user_avatar || window.dgtInitialsAvatar(act.user_name || 'System', act.user_avatar_color || '#64748b')" class="mt-0.5 h-8 w-8 flex-shrink-0 rounded-full border border-slate-200 object-cover">
            <div class="min-w-0 flex-1">
              <p class="text-slate-700">
                <strong class="font-bold text-slate-900" x-text="act.user_name"></strong>
                <span x-html="parseMarkdown(act.description || '')"></span>
              </p>
              <span class="mt-1 block text-[10px] font-bold text-slate-400" x-text="act.time_ago"></span>
            </div>
          </div>
        </template>
        <div x-show="activities.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-center text-sm font-semibold text-slate-500">
          No activity recorded yet.
        </div>
      </section>

      <section x-show="boardMenu.view === 'archived'" class="space-y-4">
        <div class="grid grid-cols-2 rounded-xl bg-slate-100 p-1">
          <button type="button" @click="boardMenu.archivedTab = 'cards'" class="rounded-lg px-3 py-2 text-xs font-black transition" :class="boardMenu.archivedTab === 'cards' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">Cards</button>
          <button type="button" @click="boardMenu.archivedTab = 'lists'" class="rounded-lg px-3 py-2 text-xs font-black transition" :class="boardMenu.archivedTab === 'lists' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">Lists</button>
        </div>
        <div x-show="boardMenu.archivedLoading" class="rounded-xl border border-slate-200 bg-white p-5 text-center text-sm font-semibold text-slate-500">Loading archived items...</div>
        <div x-show="!boardMenu.archivedLoading && boardMenu.archivedTab === 'cards'" class="space-y-2">
          <template x-for="card in boardMenu.archivedCards" :key="card.id">
            <div class="rounded-xl border border-slate-200 bg-white p-3">
              <p class="text-sm font-black text-slate-800" x-text="card.title"></p>
              <p class="mt-1 text-xs font-semibold text-slate-500"><span x-text="card.list_name"></span> &middot; <span x-text="card.archived_at || 'Archived'"></span></p>
              <button type="button" @click="restoreArchivedItem('card', card.id)" class="mt-3 text-xs font-black text-sky-700 hover:underline">Restore card</button>
            </div>
          </template>
          <div x-show="boardMenu.archivedCards.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-center text-sm font-semibold text-slate-500">No archived cards.</div>
        </div>
        <div x-show="!boardMenu.archivedLoading && boardMenu.archivedTab === 'lists'" class="space-y-2">
          <template x-for="list in boardMenu.archivedLists" :key="list.id">
            <div class="rounded-xl border border-slate-200 bg-white p-3">
              <p class="text-sm font-black text-slate-800" x-text="list.name"></p>
              <p class="mt-1 text-xs font-semibold text-slate-500"><span x-text="list.card_count"></span> cards &middot; <span x-text="list.archived_at || 'Archived'"></span></p>
              <button type="button" @click="restoreArchivedItem('list', list.id)" class="mt-3 text-xs font-black text-sky-700 hover:underline">Restore list</button>
            </div>
          </template>
          <div x-show="boardMenu.archivedLists.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-center text-sm font-semibold text-slate-500">No archived lists.</div>
        </div>
      </section>

      <section x-show="boardMenu.view === 'watch'" class="space-y-4">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-sm font-black text-slate-800" x-text="boardMenu.watched ? 'You are watching this board.' : 'You are not watching this board.'"></p>
          <p class="mt-2 text-xs leading-5 text-slate-500">Watching is saved on this device and keeps the board marked for follow-up in this workspace.</p>
        </div>
        <button type="button" @click="toggleBoardWatch()" class="btn btn-primary w-full justify-center" x-text="boardMenu.watched ? 'Stop watching' : 'Watch board'"></button>
      </section>

      <section x-show="boardMenu.view === 'copy'" class="space-y-4">
        <div>
          <label class="form-label text-xs">Copied board name</label>
          <input x-model="boardMenu.copyName" type="text" maxlength="100" class="form-input text-sm">
        </div>
        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 text-sm font-bold text-slate-700">
          <input type="checkbox" x-model="boardMenu.copyIncludeCards" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
          Include cards
        </label>
        <button type="button" @click="copyBoard()" :disabled="boardMenu.busy || !boardMenu.copyName.trim()" class="btn btn-primary w-full justify-center">Copy board</button>
        <a x-show="boardMenu.copiedBoardUrl" :href="boardMenu.copiedBoardUrl" class="btn btn-secondary w-full justify-center">Open copied board</a>
      </section>

      <section x-show="boardMenu.view === 'automation'" class="space-y-4">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-sm font-black text-slate-800">Automations</p>
          <p class="mt-2 text-xs leading-5 text-slate-500">Automatically copy or move cards to another board when you comment or their title contains a specific word.</p>
        </div>

        <div class="space-y-2">
          <template x-for="rule in automations" :key="rule.id">
            <div class="rounded-xl border border-slate-200 bg-white p-3 flex justify-between items-start">
              <div>
                <p x-show="rule.trigger_word" class="text-xs font-black text-slate-800">When title/comment contains: "<span class="text-indigo-600" x-text="rule.trigger_word"></span>"</p>
                <p x-show="rule.trigger_list_id" class="text-xs font-black text-slate-800">From list: "<span class="text-indigo-600" x-text="rule.trigger_list?.name"></span>" <span x-show="rule.trigger_board_id" class="text-slate-500 font-normal">on <span x-text="rule.trigger_board?.name"></span></span></p>
                <p class="mt-1 text-xs font-semibold text-slate-500">
                  <span x-text="rule.action_type === 'copy' ? 'Copy to:' : 'Move to:'"></span>
                  <span x-text="rule.target_board?.name"></span> &rarr; <span x-text="rule.target_list?.name"></span>
                </p>
                <p x-show="rule.target_assignee_id" class="text-xs font-semibold text-indigo-600 mt-1">Assign: <span x-text="rule.target_assignee?.name"></span></p>
                <p x-show="rule.target_assignee_role" class="text-xs font-semibold text-indigo-600 mt-1">Assign Role: <span x-text="rule.target_assignee_role"></span></p>
              </div>
              <div class="flex items-center space-x-2">
                <button @click="editAutomation(rule)" class="text-blue-500 hover:text-blue-700">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                </button>
                <button @click="deleteAutomation(rule.id)" class="text-rose-500 hover:text-rose-700">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>
            </div>
          </template>
          <div x-show="automations.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-center text-sm font-semibold text-slate-500">
            No automations configured.
          </div>
        </div>

        <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
          <p class="text-xs font-black uppercase tracking-wider text-slate-400" x-text="newAutomation.id ? 'Edit Rule' : 'Add New Rule'"></p>
          
          <div>
            <label class="form-label text-xs">Filter word (optional)</label>
            <input x-model="newAutomation.trigger_word" type="text" class="form-input text-sm" placeholder="e.g. DONE">
          </div>

          <div>
            <label class="form-label text-xs">From Board (optional)</label>
            <select x-model="newAutomation.trigger_board_id" @change="fetchTriggerLists()" class="form-input text-sm">
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
            <label class="form-label text-xs">From list (optional)</label>
            <select x-model="newAutomation.trigger_list_id" class="form-input text-sm">
              <option value="">Any list</option>
              <template x-for="list in triggerBoardLists" :key="list.id">
                <option :value="list.id" x-text="list.name"></option>
              </template>
            </select>
          </div>

          <div>
            <label class="form-label text-xs">Action Type</label>
            <select x-model="newAutomation.action_type" class="form-input text-sm">
              <option value="move">Move Card</option>
              <option value="copy">Copy Card</option>
            </select>
          </div>

          <div>
            <label class="form-label text-xs">Target Board</label>
            <select x-model="newAutomation.target_board_id" @change="fetchTargetLists()" class="form-input text-sm">
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
              <label class="form-label text-xs">Target List</label>
              <select x-model="newAutomation.target_list_id" class="form-input text-sm">
                <option value="">Select list...</option>
                <template x-for="list in targetBoardLists" :key="list.id">
                  <option :value="list.id" x-text="list.name"></option>
                </template>
              </select>
            </div>
            
            <div>
              <label class="form-label text-xs">Assign Member (optional)</label>
              <select x-model="newAutomation.combined_assignee" class="form-input text-sm">
                <option value="">Do not assign</option>
                <optgroup label="Auto-Assign Role">
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
            class="btn btn-primary w-full justify-center" x-text="newAutomation.id ? 'Update Automation' : 'Add Automation'">
          </button>
          <button type="button" x-show="newAutomation.id" @click="resetAutomationForm()" class="btn btn-secondary w-full justify-center">
            Cancel Edit
          </button>
        </div>
      </section>

      <section x-show="boardMenu.view === 'leave'" class="space-y-4">
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
          <h3 class="text-sm font-black text-rose-700">Leave this board?</h3>
          <p class="mt-2 text-xs leading-5 text-rose-600">You will lose direct board access unless your workspace role still grants it.</p>
        </div>
        <button type="button" @click="leaveBoard()" class="btn btn-danger w-full justify-center">Leave board</button>
      </section>
    </div>
  </aside>
</div>
