{{--
  Card transfer modal (Trello-style Move / Copy)
  State: cardTransferModal.*
  Methods: openCardTransferModal(), closeCardTransferModal(), submitCardTransfer(),
           cardTransferBoards(), cardTransferAvailableLists(), ensureCardTransferSelection()
--}}
<div x-show="cardTransferModal.open" x-cloak
     class="fixed inset-0 z-[75] flex items-start justify-center px-4 py-8 sm:py-12"
     @keydown.escape.window="closeCardTransferModal()">

  <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-sm"
       @click="closeCardTransferModal()"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"></div>

  <section class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
           role="dialog"
           aria-modal="true"
           aria-labelledby="card-transfer-modal-title"
           @click.stop
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="opacity-0 translate-y-3 scale-95"
           x-transition:enter-end="opacity-100 translate-y-0 scale-100"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="opacity-100 translate-y-0 scale-100"
           x-transition:leave-end="opacity-0 translate-y-3 scale-95">

    <header class="flex items-start justify-between border-b border-slate-200 bg-slate-50 px-5 py-4">
      <div>
        <h3 id="card-transfer-modal-title" class="text-base font-black text-slate-900">
          <span x-text="cardTransferModal.mode === 'copy' ? 'Copy / duplicate card' : 'Move card'"></span>
        </h3>
        <p class="mt-0.5 text-xs font-medium text-slate-500">
          Pick destination board and list.
        </p>
      </div>

      <button type="button"
              @click="closeCardTransferModal()"
              class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-200 hover:text-slate-700"
              aria-label="Close move/copy dialog">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
      </button>
    </header>

    <div class="space-y-4 px-5 py-5">
      <template x-if="cardTransferModal.mode === 'copy'">
        <label class="block">
          <span class="mb-1 block text-[11px] font-extrabold uppercase tracking-wide text-slate-500">Title</span>
          <input type="text"
                 x-model="cardTransferModal.title"
                 maxlength="255"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
        </label>
      </template>

      <label class="block">
        <span class="mb-1 block text-[11px] font-extrabold uppercase tracking-wide text-slate-500">Search board</span>
        <input type="search"
               x-model.debounce.100ms="cardTransferModal.boardSearch"
               @input="ensureCardTransferSelection()"
               placeholder="Type board or workspace name"
               class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-800 outline-none transition focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-100">
      </label>

      <label class="block">
        <span class="mb-1 block text-[11px] font-extrabold uppercase tracking-wide text-slate-500">Board</span>
        <select x-model.number="cardTransferModal.selectedBoardId"
                @change="ensureCardTransferSelection()"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
          <template x-for="boardOption in cardTransferBoards()" :key="'transfer-board-' + boardOption.id">
            <option :value="boardOption.id"
                    x-text="`${boardOption.name} (${boardOption.workspace_name})`"></option>
          </template>
        </select>
      </label>

      <label class="block">
        <span class="mb-1 block text-[11px] font-extrabold uppercase tracking-wide text-slate-500">List</span>
        <select x-model.number="cardTransferModal.selectedListId"
                :disabled="cardTransferAvailableLists().length === 0"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
          <template x-if="cardTransferAvailableLists().length === 0">
            <option value="">No active lists found</option>
          </template>
          <template x-for="listOption in cardTransferAvailableLists()" :key="'transfer-list-' + listOption.id">
            <option :value="listOption.id" x-text="listOption.name"></option>
          </template>
        </select>
      </label>
    </div>

    <footer class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
      <button type="button"
              @click="closeCardTransferModal()"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-extrabold text-slate-600 transition hover:bg-slate-100">
        Cancel
      </button>
      <button type="button"
              @click="submitCardTransfer()"
              :disabled="cardTransferModal.submitting || cardTransferBoards().length === 0 || cardTransferAvailableLists().length === 0"
              class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-extrabold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-indigo-300">
        <svg x-show="cardTransferModal.submitting" class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle cx="12" cy="12" r="10" class="opacity-25" stroke="currentColor" stroke-width="4"></circle>
          <path d="M22 12a10 10 0 0 1-10 10" class="opacity-90" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
        </svg>
        <span x-text="cardTransferModal.mode === 'copy' ? 'Copy card' : 'Move card'"></span>
      </button>
    </footer>
  </section>
</div>
