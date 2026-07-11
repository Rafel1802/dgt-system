window.workspacePage = function() {
  return {
    showCreateBoard: false,
    showCreateWorkspace: false,
    editWorkspaceModal: {
      open: false, id: null, name: '', color: '#6366f1', icon_text: ''
    },
    editBoardModal: {
      open: false, id: null, name: '', bgType: 'color', customColor: '#6366f1', customImage: ''
    },
    showHiddenBoards: false,
    showTrashWorkspaces: false,
    selectedWorkspaceId: null,

    init() {
      this.$nextTick(() => this.initBoardSorting());
    },

    initBoardSorting() {
      if (typeof Sortable === 'undefined') return;

      document.querySelectorAll('.board-sort-grid').forEach((grid) => {
        Sortable.create(grid, {
          animation: 180,
          draggable: '[data-board-id]',
          ghostClass: 'opacity-40',
          chosenClass: 'ring-4',
          dragClass: 'shadow-2xl',
          onStart: () => { window.isDraggingBoard = true; },
          onEnd: (evt) => { 
            setTimeout(() => window.isDraggingBoard = false, 50);
            this.saveBoardOrder(grid); 
          },
        });
      });
    },

    async saveBoardOrder(grid) {
      const workspaceId = grid.dataset.workspaceId;
      const order = Array.from(grid.querySelectorAll('[data-board-id]'))
        .map((board) => Number(board.dataset.boardId));

      try {
        const response = await fetch(`/boards/workspaces/${workspaceId}/boards/reorder`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
          body: JSON.stringify({ order }),
        });

        if (!response.ok) throw new Error('Board order could not be saved.');
        if (window.showToast) window.showToast('Board order saved.');
      } catch (error) {
        if (window.showToast) window.showToast(error.message, 'error');
        typeof Turbo !== 'undefined' ? Turbo.visit(window.location.href, { action: 'replace' }) : window.location.reload();
      }
    },

    openCreateBoard(workspaceId) {
      this.selectedWorkspaceId = workspaceId;
      this.showCreateBoard = true;
    },

    openEditWorkspace(id, name, color, icon_text) {
      this.editWorkspaceModal.id = id;
      this.editWorkspaceModal.name = name;
      this.editWorkspaceModal.color = color || '#6366f1';
      this.editWorkspaceModal.icon_text = icon_text || '';
      this.editWorkspaceModal.open = true;
    },

    openEditBoard(id, name, type, value) {
      this.editBoardModal.id = id;
      this.editBoardModal.name = name;
      this.editBoardModal.bgType = type === 'image' ? 'image' : 'color';
      
      if (type === 'image') {
        this.editBoardModal.customImage = value;
        this.editBoardModal.customColor = '#6366f1';
      } else {
        this.editBoardModal.customColor = value || '#6366f1';
        this.editBoardModal.customImage = '';
      }
      this.editBoardModal.open = true;
    }
  }
}

// ── Board quick-action (hide/delete from card hover menu) ──────────────
window.boardQuickAction = async function(action, slug, name) {
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  if (action === 'hide') {
    const ok = await window.confirmModal?.(`Hide board "${name}"? It will be moved to the Hidden Boards list.`) ?? confirm(`Hide board "${name}"?`);
    if (!ok) return;
    try {
      const res = await fetch(`/boards/${slug}/toggle-hidden`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
      });
      if (!res.ok) throw new Error('Failed');
      Alpine.store?.('toast')?.show?.('Board hidden successfully');
      typeof Turbo !== 'undefined' ? Turbo.visit(window.location.href, { action: 'replace' }) : window.location.reload();
    } catch { Alpine.store?.('toast')?.show?.('Error hiding board', 'error'); }
  } else if (action === 'delete') {
    const ok = await window.confirmModal?.(`Delete board "${name}"? It will be moved to Trash.`) ?? confirm(`Delete board "${name}"?`);
    if (!ok) return;
    try {
      const res = await fetch(`/boards/${slug}`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
      });
      if (!res.ok) throw new Error('Failed');
      Alpine.store?.('toast')?.show?.('Board deleted (moved to trash)');
      typeof Turbo !== 'undefined' ? Turbo.visit(window.location.href, { action: 'replace' }) : window.location.reload();
    } catch { Alpine.store?.('toast')?.show?.('Error deleting board', 'error'); }
  }
}

// ── Trash Manager Alpine component ─────────────────────────────────────
window.trashManager = function() {
  return {
    selected: { workspaces: [], boards: [] },
    confirmPopup: { open: false, title: '', message: '', type: 'delete', resolve: () => {} },
    get selectedCount() { return this.selected.workspaces.length + this.selected.boards.length; },

    toggleItem(type, id) {
      const idx = this.selected[type].indexOf(id);
      if (idx > -1) this.selected[type].splice(idx, 1);
      else this.selected[type].push(id);
    },
    toggleSelectAll(checked, allWorkspaces = [], allBoards = []) {
      if (checked) {
        this.selected.workspaces = [...allWorkspaces];
        this.selected.boards = [...allBoards];
      } else {
        this.selected.workspaces = []; this.selected.boards = [];
      }
    },

    showConfirm(title, message, type) {
      return new Promise(resolve => {
        this.confirmPopup = { open: true, title, message, type, resolve };
      });
    },

    async singleAction(action, itemType, id, name) {
      const isDelete = action === 'delete';
      const title = isDelete ? 'Delete Forever?' : 'Recover Item?';
      const msg = isDelete
        ? `<strong>${name}</strong> and all its data will be <span class="text-rose-600 font-semibold">permanently deleted</span>. This cannot be undone.`
        : `<strong>${name}</strong> will be restored to its original workspace.`;
      const ok = await this.showConfirm(title, msg, isDelete ? 'delete' : 'recover');
      if (!ok) return;
      await this._submitForm(action, itemType, id);
      typeof Turbo !== 'undefined' ? Turbo.visit(window.location.href, { action: 'replace' }) : window.location.reload();
    },

    async bulkAction(action) {
      const count = this.selectedCount;
      const isDelete = action === 'delete';
      const title = isDelete ? `Delete ${count} item(s) forever?` : `Recover ${count} item(s)?`;
      const msg = isDelete
        ? `All <strong>${count} selected item(s)</strong> and their data will be <span class="text-rose-600 font-semibold">permanently deleted</span>. This cannot be undone.`
        : `All <strong>${count} selected item(s)</strong> will be restored to their original workspaces.`;
      const ok = await this.showConfirm(title, msg, isDelete ? 'delete' : 'recover');
      if (!ok) return;

      const promises = [];
      const act = isDelete ? 'delete' : 'recover';
      for (const id of this.selected.workspaces) promises.push(this._submitForm(act, 'workspace', id));
      for (const id of this.selected.boards) promises.push(this._submitForm(act, 'board', id));
      
      await Promise.all(promises);
      typeof Turbo !== 'undefined' ? Turbo.visit(window.location.href, { action: 'replace' }) : window.location.reload();
    },

    async _submitForm(action, itemType, id) {
      const csrf = document.querySelector('meta[name="csrf-token"]').content;
      const url = action === 'delete'
        ? (itemType === 'workspace' ? `/boards/workspaces/${id}/force` : `/boards/${id}/force`)
        : (itemType === 'workspace' ? `/boards/workspaces/${id}/restore` : `/boards/${id}/restore`);
      
      try {
        await fetch(url, {
          method: action === 'delete' ? 'DELETE' : 'POST',
          headers: {
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json'
          }
        });
      } catch (e) {
        console.error('Action failed:', e);
      }
    }
  };
}

window.addWorkspaceMember = async function(workspaceId, userId, btn) {
  try {
    btn.disabled = true;
    btn.innerHTML = '<span class="opacity-50">...</span>';
    const res = await fetch(`/boards/workspaces/${workspaceId}/members`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      },
      body: JSON.stringify({ user_id: userId })
    });
    if (!res.ok) throw new Error('Failed to add member');
    
    // Switch to Remove button visually
    btn.outerHTML = `<button onclick="removeWorkspaceMember(${workspaceId}, ${userId}, this)" class="text-[10px] text-rose-500 font-bold px-2.5 py-1 rounded-md hover:bg-rose-500 hover:text-white transition-colors">Remove</button>`;
    Alpine.store('toast').show('Member added to workspace');
  } catch (err) {
    console.error(err);
    Alpine.store('toast').show('Error adding member', 'error');
    btn.disabled = false;
    btn.textContent = 'Add';
  }
}

window.removeWorkspaceMember = async function(workspaceId, userId, btn) {
  if (!await window.confirmModal('Are you sure you want to remove this member?')) return;
  try {
    btn.disabled = true;
    btn.innerHTML = '<span class="opacity-50">...</span>';
    const res = await fetch(`/boards/workspaces/${workspaceId}/members/${userId}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      }
    });
    if (!res.ok) throw new Error('Failed to remove member');
    
    // Switch to Add button visually
    btn.outerHTML = `<button onclick="addWorkspaceMember(${workspaceId}, ${userId}, this)" class="text-[10px] text-indigo-600 font-bold px-2.5 py-1 rounded-md hover:bg-indigo-600 hover:text-white transition-colors">Add</button>`;
    Alpine.store('toast').show('Member removed from workspace');
  } catch (err) {
    console.error(err);
    Alpine.store('toast').show('Error removing member', 'error');
    btn.disabled = false;
    btn.textContent = 'Add';
  }
}

window.unhideBoard = async function(boardSlug, btn) {
  try {
    btn.disabled = true;
    btn.textContent = '...';
    const res = await fetch(`/boards/${boardSlug}/toggle-hidden`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      }
    });
    if (!res.ok) throw new Error('Failed to unhide board');
    
    Alpine.store('toast').show('Board unhidden successfully!');
    btn.closest('.hidden-board-item').remove();
    typeof Turbo !== 'undefined' ? Turbo.visit(window.location.href, { action: 'replace' }) : window.location.reload();
  } catch (err) {
    console.error(err);
    Alpine.store('toast').show('Error unhiding board', 'error');
    btn.disabled = false;
    btn.textContent = 'Unhide';
  }
}
