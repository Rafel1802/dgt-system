/**
 * trello-board.js
 * Premium Alpine.js component that powers the digital team Trello-style board view.
 * Handles: SortableJS drag-and-drop, dynamic board switcher, multi-filters,
 * card detail modal, checklists, comments, attachments upload, labels, members,
 * star favorite toggles, and sliding activity drawers.
 */
function trelloBoard(config) {
  return {
    // ── State ────────────────────────────────────────────────────────────────
    board:      config.board || { id: config.boardId, name: '', slug: config.boardSlug, is_starred: false },
    boardId:    config.boardId,
    boardSlug:  config.boardSlug,
    csrfToken:  config.csrfToken,
    currentUserId: config.currentUserId,
    currentUser: config.currentUser || { id: config.currentUserId, can_move_any_card: false, can_manage_blocked_cards: false, is_digital_team: false },
    lists:      config.lists,
    labels:     config.labels,

    // All members for filtering (set from PHP injected config)
    allBoardMembers:     config.boardMembers     || [],
    allWorkspaceMembers: config.workspaceMembers || [],
    allWorkspaces:       config.allWorkspaces    || [],

    // Filters & Zoom
    zoomLevel: parseInt(localStorage.getItem('boardZoomLevel')) || 100,
    searchQuery: '',
    filterPriority: '',
    filterAssignee: '',
    filterDateFrom: '',
    filterDateTo: '',
    filtersOpen: false,
    searchOpen: false,
    boardMembers: [], // unique list for filter dropdown (populated by loadBoardMembers)

    // Activity drawer
    activityOpen: false,
    activities: [],

    // Board menu drawer
    boardMenu: {
      open: false,
      view: 'menu',
      busy: false,
      settingsName: '',
      settingsDescription: '',
      settingsWorkspaceId: '',
      settingsVisibility: 'workspace',
      settingsMemberPermissions: 'members',
      settingsCardCoversEnabled: true,
      settingsNotificationsEnabled: true,
      settingsBrowserNotificationsEnabled: false,
      settingsAttachEditActivity: false,
      backgroundType: 'color',
      backgroundValue: '',
      backgroundColorDraft: '#2F68ED',
      backgroundImageUrl: '',
      backgroundColors: ['#2F68ED', '#0ea5e9', '#6366f1', '#14b8a6', '#22c55e', '#f59e0b', '#ef4444', '#0f172a'],
      backgroundGradients: [
        'linear-gradient(135deg,#0ea5e9,#22c55e)',
        'linear-gradient(135deg,#6366f1,#ec4899)',
        'linear-gradient(135deg,#f59e0b,#ef4444)',
        'linear-gradient(135deg,#0f172a,#334155)',
      ],
      archivedLoading: false,
      archivedTab: 'cards',
      archivedCards: [],
      archivedLists: [],
      watched: false,
      copyName: '',
      copyIncludeCards: true,
      copiedBoardUrl: '',
    },

    // Automations
    automations: [],
    newAutomation: {
      id: null,
      trigger_word: '',
      trigger_board_id: '',
      trigger_list_id: '',
      target_board_id: '',
      target_list_id: '',
      target_assignee_id: '',
      target_assignee_role: '',
      combined_assignee: '',
      action_type: 'move'
    },
    targetBoardLists: [],
    targetBoardMembers: [],
    triggerBoardLists: [],
    cardAutomation: {
      open: false,
      filterWord: '',
      triggerListId: '',
      targetBoardId: '',
      targetListId: '',
      targetLists: [],
      targetMembers: [],
      combined_assignee: '',
      action_type: 'move'
    },
    exportModal: {
      open: false,
      format: 'pdf',
      scope: 'board',
      selectedBoards: [],
      dateRange: 'all_time',
      startDate: '',
      endDate: '',
      memberId: 'all',
      statuses: ['draft', 'in_progress', 'review', 'completed', 'archived'],
      includeDesc: false,
      includeComments: false
    },

    // Import modal
    importModal: {
      open: false,
      step: 1,           // 1=source, 2=preview, 3=done
      source: 'csv',     // 'csv' | 'sheets'
      sheetsUrl: '',
      file: null,
      dragOver: false,
      preview: null,     // JSON from /import/preview
      busy: false,
      error: null,
      result: null,      // JSON from /import/confirm
      previewFilter: 'all', // 'all' | 'invalid'
    },

    // Context menu
    ctxCard:      null,
    ctxList:      null,
    ctxVisible:   false,
    ctxTouchTimer: null,

    // Date picker modal
    datePicker: {
      open:       false,
      cardId:     null,
      calYear:    new Date().getFullYear(),
      calMonth:   new Date().getMonth(),   // 0-based
      useStart:   false,
      useDue:     false,
      startDate:  '',
      dueDate:    '',
      dueTime:    '',
      reminder:   '',
      recurring:  'none',
    },

    // Member picker modal
    memberPicker: {
      open:             false,
      cardId:           null,
      search:           '',
      loading:          false,
      cardMembers:      [],   // currently assigned to this card
      boardMembers:     [],   // board members NOT on card
      workspaceMembers: [],   // workspace members NOT on board
    },

    // Switch Boards modal
    switchBoardsModal: {
      open: false,
      search: '',
      tab: 'your', // 'your', 'starred', 'recent', 'workspace'
      selectedWorkspace: null,
      creating: false,
      createBoardName: '',
      createVisibility: 'workspace',
      createColorType: 'gradient',
      createColor: 'linear-gradient(135deg,#2F68ED,#0ea5e9,#14b8a6)',
      createColors: [
        {
          type: 'gradient',
          value: 'linear-gradient(135deg,#2F68ED,#0ea5e9,#14b8a6)',
          preview: 'linear-gradient(135deg,#2F68ED,#0ea5e9,#14b8a6)',
        },
        {
          type: 'gradient',
          value: 'linear-gradient(135deg,#6366f1,#8b5cf6,#ec4899)',
          preview: 'linear-gradient(135deg,#6366f1,#8b5cf6,#ec4899)',
        },
        {
          type: 'gradient',
          value: 'linear-gradient(135deg,#14b8a6,#22c55e,#84cc16)',
          preview: 'linear-gradient(135deg,#14b8a6,#22c55e,#84cc16)',
        },
        {
          type: 'gradient',
          value: 'linear-gradient(135deg,#f59e0b,#f97316,#ef4444)',
          preview: 'linear-gradient(135deg,#f59e0b,#f97316,#ef4444)',
        },
        {
          type: 'gradient',
          value: 'linear-gradient(135deg,#0ea5e9,#3b82f6,#6366f1)',
          preview: 'linear-gradient(135deg,#0ea5e9,#3b82f6,#6366f1)',
        },
        {
          type: 'color',
          value: '#2F68ED',
          preview: 'linear-gradient(135deg,#2F68ED,#3b82f6)',
        },
        {
          type: 'color',
          value: '#0f172a',
          preview: 'linear-gradient(135deg,#0f172a,#1e293b)',
        },
      ],
    },

    // Move / Copy destination modal
    cardTransferModal: {
      open: false,
      mode: 'move', // 'move' | 'copy'
      cardId: null,
      sourceListId: null,
      boardSearch: '',
      selectedBoardId: null,
      selectedListId: null,
      title: '',
      submitting: false,
    },

    // Attachment modal
    attachmentModal: {
      open:           false,
      tab:            'file',    // 'file' | 'link'
      cardId:         null,
      dragOver:       false,
      uploading:      false,
      uploadProgress: 0,
      error:          '',
      linkUrl:        '',
      linkName:       '',
      // Inline edit state (Trello-style)
      editingFileId:  null,
      editName:       '',
      editUrl:        '',
      editSaving:     false,
    },

    // Add list
    addingList:  false,
    newListName: '',
    editingListId: null,
    editingListName: '',

    // Add card
    addingCardListId: null,
    newCardTitle:     '',

    // Card modal
    activeCard:        null,
    cardLoading:       false,
    sendingScreenshot: false,
    pastedImage:       null,
    newComment:        '',
    isEditingDesc:     false,
    imagePreview: {
      open: false,
      url: '',
      title: '',
    },
    videoPreview: {
      open: false,
      url: '',
      embedUrl: '',
      title: '',
    },
    khTimeZone: 'Asia/Phnom_Penh',
    realtimeBound: false,
    realtimeTimer: null,
    realtimePollTimer: null,
    realtimeChannel: null,
    realtimeConnectAttempts: 0,
    realtimeInFlight: false,
    realtimeDragging: false,
    lastSnapshotAt: 0,

    // ── Init ─────────────────────────────────────────────────────────────────
    init() {
      // Prompt desktop notifications permission on mount
      if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
      }

      // Close context menu on ESC or outside click
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') this.closeCtxMenu();
      });
      document.addEventListener('click', (e) => {
        const menu = document.getElementById('card-ctx-menu');
        if (menu && !menu.contains(e.target)) this.closeCtxMenu();
      });

      // Load unique assignees dynamically from cards
      this.loadBoardMembers();

      this.boardMenu.watched = localStorage.getItem(this.boardWatchStorageKey()) === 'true';

      // Initialize SortableJS drag-and-drop
      this.initSortable();
      this.bindRealtimeBoardUpdates();

      // Auto-open card if passed in query param
      const urlParams = new URLSearchParams(window.location.search);
      const cardId = urlParams.get('card');
      if (cardId) {
        this.openCard(cardId);
      }
    },

    setZoom(level) {
      this.zoomLevel = level;
      localStorage.setItem('boardZoomLevel', level);
    },

    zoomIn() {
      const levels = [33, 50, 67, 75, 80, 90, 100, 110, 125, 150];
      const next = levels.find(l => l > this.zoomLevel);
      if (next) this.setZoom(next);
    },

    zoomOut() {
      const levels = [33, 50, 67, 75, 80, 90, 100, 110, 125, 150];
      const prev = [...levels].reverse().find(l => l < this.zoomLevel);
      if (prev) this.setZoom(prev);
    },

    avatarUrl(user) {
      return user?.avatar || user?.avatar_url || user?.user_avatar || '';
    },

    avatarInitials(user) {
      if (user?.avatar_initials) return user.avatar_initials;
      if (user?.initials) return user.initials;

      const name = String(user?.name || user?.user_name || user?.email || 'User').trim().replace(/\s+/g, ' ');
      if (!name) return 'U';
      const cleanName = name.includes('@') ? name.split('@')[0] : name;
      const parts = cleanName.split(' ').filter(Boolean);

      if (parts.length > 1) {
        return `${parts[0][0] || ''}${parts[parts.length - 1][0] || ''}`.toUpperCase() || 'U';
      }

      return (parts[0] || 'U').slice(0, 2).toUpperCase();
    },

    avatarColor(user) {
      if (user?.avatar_color || user?.user_avatar_color) return user.avatar_color || user.user_avatar_color;

      const palette = ['#4f46e5', '#0f766e', '#be123c', '#b45309', '#0369a1', '#7c3aed', '#15803d', '#334155'];
      const seed = String(user?.email || user?.name || user?.user_name || 'user').toLowerCase();
      let hash = 0;
      for (let i = 0; i < seed.length; i++) hash = ((hash << 5) - hash + seed.charCodeAt(i)) | 0;
      return palette[Math.abs(hash) % palette.length];
    },

    avatarStyle(user) {
      return `background:${this.avatarColor(user)}`;
    },

    escapeHtml(value) {
      const div = document.createElement('div');
      div.textContent = String(value ?? '');
      return div.innerHTML;
    },

    unifiedActivities() {
      if (!this.activeCard) return [];
      
      const comments = (this.activeCard.comments || []).map(c => ({
        _type: 'comment',
        id: 'comment_' + c.id,
        user_id: c.user_id,
        user_name: c.user?.name || 'User',
        user_avatar: c.user?.avatar || '',
        user_initials: c.user?.avatar_initials || 'U',
        user_avatar_color: c.user?.avatar_color || '#64748b',
        content: c.body || c.content,
        created_at: c.created_at,
        time_ago: this.timeAgo(c.created_at),
        original: c
      }));

      const acts = (this.cardActivities || []).filter(a => {
        // Filter out activity log entries for comment creation, since we show the comment itself
        return a.action !== 'card.comment_added';
      }).map(a => ({
        _type: 'activity',
        id: 'act_' + a.id,
        user_name: a.user_name || 'System',
        user_avatar: a.user_avatar || '',
        user_initials: a.user_initials || 'SY',
        user_avatar_color: a.user_avatar_color || '#64748b',
        description: a.description,
        created_at: a.created_at,
        time_ago: a.time_ago,
        original: a
      }));

      return [...comments, ...acts].sort((a, b) => {
        const dateA = a.created_at ? new Date(a.created_at) : new Date(0);
        const dateB = b.created_at ? new Date(b.created_at) : new Date(0);
        return dateB - dateA; // Newest first
      });
    },

    // ── Context menu ─────────────────────────────────────────────────────────
    openCtxMenu(event, card, list) {
      this.ctxCard = card;
      this.ctxList = list;

      const menu = document.getElementById('card-ctx-menu');
      if (!menu) return;

      // Show briefly off-screen so we can measure its size
      menu.classList.remove('hidden');
      menu.style.left = '-9999px';
      menu.style.top  = '-9999px';

      this.$nextTick(() => {
        const mw = menu.offsetWidth  || 200;
        const mh = menu.offsetHeight || 300;
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        // Determine raw cursor position (touch or mouse)
        let cx = event.clientX ?? (event.touches?.[0]?.clientX ?? 0);
        let cy = event.clientY ?? (event.touches?.[0]?.clientY ?? 0);

        // Offset slightly so the cursor doesn't land on the first item
        cx += 4;
        cy += 4;

        // Flip horizontally if too close to right edge
        if (cx + mw > vw - 8) cx = cx - mw - 8;
        // Flip vertically if too close to bottom edge
        if (cy + mh > vh - 8) cy = cy - mh;

        menu.style.left = Math.max(8, cx) + 'px';
        menu.style.top  = Math.max(8, cy) + 'px';
        this.ctxVisible = true;
      });
    },

    closeCtxMenu() {
      const menu = document.getElementById('card-ctx-menu');
      if (menu) menu.classList.add('hidden');
      this.ctxVisible = false;
      this.ctxCard = null;
      this.ctxList = null;
    },

    // Long-press support (500 ms) for mobile
    ctxTouchStart(event, card, list) {
      this.ctxTouchTimer = setTimeout(() => {
        // Synthesise a fake event from the touch position
        const touch = event.touches[0];
        this.openCtxMenu({ clientX: touch.clientX, clientY: touch.clientY }, card, list);
      }, 500);
    },

    ctxTouchEnd() {
      clearTimeout(this.ctxTouchTimer);
      this.ctxTouchTimer = null;
    },

    async ctxAction(action) {
      const card = this.ctxCard;
      const list = this.ctxList;
      this.closeCtxMenu();
      if (!card) return;

      switch (action) {

        case 'open':
          await this.openCard(card.id);
          break;

        // Open modal then activate the Labels panel
        case 'labels':
          await this.openCard(card.id);
          this.$nextTick(() => {
            const btn = document.querySelector('[data-ctx-panel="labels"]');
            if (btn) btn.click();
          });
          break;

        // Open member picker directly from context menu
        case 'members':
          // If card modal isn't open yet, open it so activeCard is set, then show picker
          if (!this.activeCard || this.activeCard.id !== card.id) {
            await this.openCard(card.id);
          }
          this.openMemberPicker(this.activeCard || card);
          break;


        // Cover: open modal; user picks cover via existing description / PATCH
        case 'cover': {
          const url = await window.promptModal({
            title: 'Change cover image',
            message: 'Paste a cover image URL, or leave it blank to remove the cover.',
            inputLabel: 'Image URL',
            value: card.cover_image ?? '',
            placeholder: 'https://example.com/image.jpg',
            confirmText: 'Save cover',
            required: false,
          });
          if (url === null) break; // cancelled
          const res = await this.api(`/boards/cards/${card.id}`, 'PATCH', {
            cover_image: url.trim() || null
          });
          if (res.card) {
            this.syncCardToList(res.card);
            window.showToast('Cover updated!');
          }
          break;
        }

        // Dates: open modal then focus the due-date picker
        case 'dates':
          await this.openCard(card.id);
          this.$nextTick(() => {
            const btn = document.querySelector('[data-ctx-panel="dates"]');
            if (btn) btn.click();
          });
          break;

        // Move: open board/list destination picker
        case 'move':
          this.openCardTransferModal('move', card, list);
          break;

        // Copy / duplicate: open board/list destination picker
        case 'copy':
          this.openCardTransferModal('copy', card, list);
          break;

        // Copy a shareable link to the clipboard
        case 'link': {
          const url = `${window.location.origin}/boards/${this.boardSlug}?card=${card.id}`;
          try {
            await navigator.clipboard.writeText(url);
            window.showToast('Card link copied to clipboard!');
          } catch {
            await window.promptModal({
              title: 'Copy card link',
              message: 'Copy this URL manually.',
              inputLabel: 'Card link',
              value: url,
              readonly: true,
              required: false,
              confirmText: 'Done',
              cancelText: 'Close',
            });
          }
          break;
        }

        case 'archive':
          if (!await window.confirmModal({
            title: 'Archive card?',
            message: `Archive "<strong>${this.escapeHtml(card.title)}</strong>"? You can restore it later from Archived items.`,
            confirmText: 'Archive card',
            tone: 'warning',
          })) break;
          await this.api(`/boards/cards/${card.id}`, 'PATCH', { is_archived: true });
          this.lists.forEach(l => { l.cards = l.cards.filter(c => c.id !== card.id); });
          window.showToast('Card archived.');
          break;

        case 'delete':
          if (!await window.confirmModal({
            title: 'Delete card permanently?',
            message: `Delete "<strong>${this.escapeHtml(card.title)}</strong>"? This cannot be undone.`,
            confirmText: 'Delete card',
            tone: 'danger',
          })) break;
          await this.api(`/boards/cards/${card.id}`, 'DELETE');
          this.lists.forEach(l => { l.cards = l.cards.filter(c => c.id !== card.id); });
          window.showToast('Card deleted.');
          break;
      }
    },

    openCardTransferModal(mode, card, list) {
      const normalizedMode = mode === 'copy' ? 'copy' : 'move';
      const fallbackListId = parseInt(list?.id ?? card?.board_list_id ?? 0, 10) || null;

      this.cardTransferModal.mode = normalizedMode;
      this.cardTransferModal.cardId = card?.id ?? null;
      this.cardTransferModal.sourceListId = fallbackListId;
      this.cardTransferModal.boardSearch = '';
      this.cardTransferModal.selectedBoardId = this.boardId;
      this.cardTransferModal.selectedListId = fallbackListId;
      this.cardTransferModal.title = normalizedMode === 'copy'
        ? `${card?.title || 'Untitled card'} (copy)`
        : (card?.title || '');
      this.cardTransferModal.submitting = false;
      this.cardTransferModal.open = true;

      this.$nextTick(() => this.ensureCardTransferSelection());
    },

    closeCardTransferModal() {
      this.cardTransferModal.open = false;
      this.cardTransferModal.submitting = false;
    },

    cardTransferBoards() {
      const search = this.cardTransferModal.boardSearch.trim().toLowerCase();
      const boards = this.allWorkspaces.flatMap(ws =>
        (ws.boards || []).map(b => ({
          ...b,
          workspace_name: ws.name,
        }))
      );

      const seen = new Set();
      return boards.filter(board => {
        if (!board || seen.has(board.id)) return false;
        seen.add(board.id);

        if (!search) return true;
        const boardName = String(board.name || '').toLowerCase();
        const workspaceName = String(board.workspace_name || '').toLowerCase();
        return boardName.includes(search) || workspaceName.includes(search);
      });
    },

    selectedTransferBoard() {
      const selectedBoardId = parseInt(this.cardTransferModal.selectedBoardId, 10);
      if (!selectedBoardId) return null;
      return this.cardTransferBoards().find(board => board.id === selectedBoardId) || null;
    },

    cardTransferAvailableLists() {
      const board = this.selectedTransferBoard();
      if (!board) return [];
      return (board.lists || []).slice().sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
    },

    ensureCardTransferSelection() {
      const selectedBoardId = parseInt(this.cardTransferModal.selectedBoardId, 10);
      const currentBoardStillVisible = this.cardTransferBoards().some(b => b.id === selectedBoardId);

      if (!currentBoardStillVisible) {
        const firstBoard = this.cardTransferBoards()[0] || null;
        this.cardTransferModal.selectedBoardId = firstBoard ? firstBoard.id : null;
      }

      const availableLists = this.cardTransferAvailableLists();
      const selectedListId = parseInt(this.cardTransferModal.selectedListId, 10);
      const listIsValid = availableLists.some(list => list.id === selectedListId);

      if (!listIsValid) {
        const preferredList = availableLists.find(list => list.id === this.cardTransferModal.sourceListId);
        this.cardTransferModal.selectedListId = preferredList
          ? preferredList.id
          : (availableLists[0]?.id ?? null);
      }

      if (this.cardTransferModal.mode === 'copy') {
        const isSameBoard = parseInt(this.cardTransferModal.selectedBoardId, 10) === parseInt(this.boardId, 10);
        if (isSameBoard) {
            if (!this.cardTransferModal.title.endsWith(' (copy)')) {
                this.cardTransferModal.title = this.cardTransferModal.title + ' (copy)';
            }
        } else {
            if (this.cardTransferModal.title.endsWith(' (copy)')) {
                this.cardTransferModal.title = this.cardTransferModal.title.replace(/ \(copy\)$/, '');
            }
        }
      }
    },

    async submitCardTransfer() {
      if (this.cardTransferModal.submitting) return;

      const mode = this.cardTransferModal.mode === 'copy' ? 'copy' : 'move';
      const cardId = parseInt(this.cardTransferModal.cardId, 10);
      const targetBoardId = parseInt(this.cardTransferModal.selectedBoardId, 10);
      const targetListId = parseInt(this.cardTransferModal.selectedListId, 10);
      const targetBoard = this.selectedTransferBoard();
      const targetList = this.cardTransferAvailableLists().find(list => list.id === targetListId);

      if (!cardId || !targetBoardId || !targetListId || !targetBoard || !targetList) {
        window.showToast('Choose a destination board and list first.', 'error');
        return;
      }

      const sourceListId = parseInt(this.cardTransferModal.sourceListId, 10) || null;
      const sourceList = this.lists.find(l => l.id === sourceListId) || null;
      const sourceCard = sourceList?.cards?.find(c => c.id === cardId) || null;

      this.cardTransferModal.submitting = true;

      try {
        if (mode === 'move') {
          if (targetBoardId === this.boardId && sourceListId === targetListId) {
            this.closeCardTransferModal();
            window.showToast('Card is already in this list.');
            return;
          }

          const res = await this.api(`/boards/cards/${cardId}/move`, 'POST', {
            board_list_id: targetListId,
          });

          if (!res.card) return;

          const movedWithinCurrentBoard = targetBoardId === this.boardId;

          // Always remove from visible source list first.
          this.lists.forEach(l => {
            l.cards = l.cards.filter(c => c.id !== cardId);
          });

          if (movedWithinCurrentBoard) {
            const localTargetList = this.lists.find(l => l.id === targetListId);
            if (localTargetList && sourceCard) {
              sourceCard.board_list_id = targetListId;
              localTargetList.cards.push(sourceCard);
            } else if (localTargetList && res.card) {
              localTargetList.cards.push({
                id: res.card.id,
                title: res.card.title,
                priority: res.card.priority ?? 'medium',
                due_at: res.card.due_at ?? null,
                start_date: res.card.start_date ?? null,
                due_time: res.card.due_time ?? null,
                labels: res.card.labels ?? [],
                assignees: res.card.assignees ?? [],
                checklist_total: res.card.checklist_total ?? 0,
                checklist_done: res.card.checklist_done ?? 0,
                has_files: res.card.has_files ?? false,
                comment_count: res.card.comment_count ?? 0,
              });
            }

            if (this.activeCard && this.activeCard.id === cardId) {
              this.activeCard.board_list_id = targetListId;
              this.activeCard.board_list_name = targetList.name;
            }
          } else if (this.activeCard && this.activeCard.id === cardId) {
            this.closeCard();
          }

          this.closeCardTransferModal();
          window.showToast(`Moved card to "${targetBoard.name} / ${targetList.name}".`);
          return;
        }

        const copyTitle = (this.cardTransferModal.title || '').trim();
        if (!copyTitle) {
          window.showToast('Please enter a title for the copied card.', 'error');
          return;
        }

        const res = await this.api(`/boards/cards/${cardId}/copy`, 'POST', {
          title: copyTitle,
          target_board_id: targetBoardId,
          board_list_id: targetListId,
        });

        if (!res.card) return;

        const copiedIntoCurrentBoard = targetBoardId === this.boardId;
        if (copiedIntoCurrentBoard) {
          const localTargetList = this.lists.find(l => l.id === targetListId);
          if (localTargetList) {
            localTargetList.cards.push({
              id: res.card.id,
              title: res.card.title,
              priority: res.card.priority ?? 'medium',
              due_at: res.card.due_at ?? null,
              start_date: res.card.start_date ?? null,
              due_time: res.card.due_time ?? null,
              labels: res.card.labels ?? [],
              assignees: res.card.assignees ?? [],
              checklist_total: res.card.checklist_total ?? 0,
              checklist_done: res.card.checklist_done ?? 0,
              has_files: res.card.has_files ?? false,
              comment_count: res.card.comment_count ?? 0,
            });
          }
        }

        this.closeCardTransferModal();
        window.showToast(`Copied card to "${targetBoard.name} / ${targetList.name}".`);
      } finally {
        this.cardTransferModal.submitting = false;
      }
    },

    // Push an updated card object back into the reactive lists array
    syncCardToList(updated) {
      this.lists.forEach(l => {
        const idx = l.cards.findIndex(c => c.id === updated.id);
        if (idx !== -1) Object.assign(l.cards[idx], updated);
      });
    },

    // ── Date picker ──────────────────────────────────────────────────────────────
    openDatePicker(card) {
      if (!card) return;
      const dp = this.datePicker;
      const now = new Date();
      dp.cardId    = card.id;
      dp.useStart  = !!card.start_date;
      dp.useDue    = !!card.due_at;
      dp.startDate = card.start_date  ? String(card.start_date).substring(0,10) : '';
      dp.dueDate   = card.due_at      ? String(card.due_at).substring(0,10)     : '';
      dp.dueTime   = card.due_time    ? String(card.due_time).substring(0,5)    : '';
      dp.reminder  = card.reminder    != null ? String(card.reminder)            : '';
      dp.recurring = card.recurring   || 'none';
      // Start calendar at the due month, or today
      const ref = dp.dueDate ? new Date(dp.dueDate + 'T00:00:00') : now;
      dp.calYear  = ref.getFullYear();
      dp.calMonth = ref.getMonth();
      dp.open = true;
    },

    closeDatePicker() {
      this.datePicker.open = false;
    },

    async saveDatePicker() {
      const dp   = this.datePicker;
      const id   = dp.cardId;
      if (!id) return;

      const payload = {
        due_at:     dp.useDue   && dp.dueDate   ? dp.dueDate   : null,
        start_date: dp.useStart && dp.startDate ? dp.startDate : null,
        due_time:   dp.useDue   && dp.dueTime   ? dp.dueTime   : null,
        reminder:   dp.reminder !== '' ? parseInt(dp.reminder, 10) : null,
        recurring:  dp.recurring || 'none',
      };

      const res = await this.api(`/boards/cards/${id}`, 'PATCH', payload);
      if (res.card || res.message) {
        // Sync into active card modal if open
        if (this.activeCard && this.activeCard.id === id) {
          Object.assign(this.activeCard, {
            due_at:     payload.due_at,
            start_date: payload.start_date,
            due_time:   payload.due_time,
            reminder:   payload.reminder,
            recurring:  payload.recurring,
          });
        }
        // Sync into board list card for badge
        this.lists.forEach(l => {
          const c = l.cards.find(c => c.id === id);
          if (c) {
            c.due_at     = payload.due_at;
            c.start_date = payload.start_date;
            c.due_time   = payload.due_time;
            c.reminder   = payload.reminder;
            c.recurring  = payload.recurring;
          }
        });
        window.showToast('Dates saved!');
        this.closeDatePicker();
      }
    },

    async removeDates() {
      const id = this.datePicker.cardId;
      if (!id) return;
      if (!await window.confirmModal('Remove all dates from this card?')) return;

      await this.api(`/boards/cards/${id}`, 'PATCH', {
        due_at: null, start_date: null, due_time: null,
        reminder: null, recurring: 'none',
      });

      if (this.activeCard && this.activeCard.id === id) {
        Object.assign(this.activeCard, {
          due_at: null, start_date: null, due_time: null,
          reminder: null, recurring: 'none',
        });
      }
      this.lists.forEach(l => {
        const c = l.cards.find(c => c.id === id);
        if (c) {
          c.due_at = null; c.start_date = null;
          c.due_time = null; c.reminder = null; c.recurring = 'none';
        }
      });
      window.showToast('Dates removed.');
      this.closeDatePicker();
    },

    dpPrevMonth() {
      if (this.datePicker.calMonth === 0) {
        this.datePicker.calMonth = 11;
        this.datePicker.calYear--;
      } else {
        this.datePicker.calMonth--;
      }
    },

    dpNextMonth() {
      if (this.datePicker.calMonth === 11) {
        this.datePicker.calMonth = 0;
        this.datePicker.calYear++;
      } else {
        this.datePicker.calMonth++;
      }
    },

    dpMonthLabel() {
      const dp = this.datePicker;
      return new Date(dp.calYear, dp.calMonth, 1)
        .toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    },

    // Build a flat array of calendar cells (some empty for padding)
    dpCalCells() {
      const dp = this.datePicker;
      const y  = dp.calYear;
      const m  = dp.calMonth;
      const firstDow = new Date(y, m, 1).getDay();   // 0=Sun
      const daysInMonth = new Date(y, m + 1, 0).getDate();
      const cells = [];
      // Leading empty cells
      for (let i = 0; i < firstDow; i++) {
        cells.push({ key: 'e' + i, day: 0, date: null });
      }
      for (let d = 1; d <= daysInMonth; d++) {
        const iso = `${y}-${String(m + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        cells.push({ key: iso, day: d, date: iso });
      }
      return cells;
    },

    dpSelectDay(cell) {
      const dp = this.datePicker;
      // If useDue is active, clicking sets the due date; else sets start date
      if (dp.useDue) {
        dp.dueDate = cell.date;
      } else if (dp.useStart) {
        dp.startDate = cell.date;
      } else {
        // Activate due date with the clicked day
        dp.useDue  = true;
        dp.dueDate = cell.date;
      }
    },

    dpDayClass(cell) {
      if (!cell.day) return 'cursor-default';
      const dp   = this.datePicker;
      const iso  = cell.date;
      const base = 'hover:bg-indigo-100 hover:text-indigo-700 text-slate-700';
      const today = new Date().toISOString().substring(0,10);

      if (iso === dp.dueDate && dp.useDue)   return 'bg-indigo-600 text-white font-bold rounded-lg';
      if (iso === dp.startDate && dp.useStart) return 'bg-slate-200 text-slate-800 font-bold rounded-lg';
      if (iso === today)   return base + ' ring-1 ring-indigo-400 rounded-lg font-bold';
      // Highlight range between start and due
      if (dp.startDate && dp.dueDate && dp.useStart && dp.useDue) {
        if (iso > dp.startDate && iso < dp.dueDate) {
          return 'bg-indigo-50 text-indigo-600 rounded-lg';
        }
      }
      return base + ' rounded-lg';
    },

    loadBoardMembers() {
      const membersMap = new Map();
      this.lists.forEach(l => {
        l.cards.forEach(c => {
          if (c.assignees) {
            c.assignees.forEach(u => {
              membersMap.set(u.id, { id: u.id, name: u.name });
            });
          }
        });
      });
      this.boardMembers = Array.from(membersMap.values());
    },

    initSortable() {
      this.$nextTick(() => {
        setTimeout(() => {
          const containers = document.querySelectorAll('.list-cards');
          containers.forEach(el => {
            if (el.sortableInstance) {
              el.sortableInstance.destroy();
            }

            el.sortableInstance = new Sortable(el, {
              group: 'cards',
              draggable: '.kanban-card[data-can-drag="1"]',
              animation: 180,
              ghostClass: 'bg-indigo-50/70',
              dragClass: 'opacity-50',
              delay: 150,
              delayOnTouchOnly: true,
              touchStartThreshold: 5,
              onStart: () => {
                this.realtimeDragging = true;
              },
              onMove: (evt) => {
                const card = this.findCard(parseInt(evt.dragged.dataset.id));
                const fromList = this.lists.find(l => l.id === parseInt(evt.from.dataset.listId));
                const toList = this.lists.find(l => l.id === parseInt(evt.to.dataset.listId));
                if (!this.canDragCard(card, fromList) || !this.canDragCard(card, toList)) {
                  return false;
                }
                return true;
              },
              onEnd: async (evt) => {
                this.realtimeDragging = false;
                const cardId = evt.item.dataset.id;
                const fromListId = evt.from.dataset.listId;
                const toListId = evt.to.dataset.listId;
                const newIndex = evt.newIndex;

                if (fromListId === toListId && evt.oldIndex === newIndex) return;

                await this.persistCardOrder(cardId, fromListId, toListId, newIndex);
              }
            });
          });
        }, 100);
      });
    },

    async persistCardOrder(cardId, fromListId, toListId, newIndex) {
      const cardBeforeMove = this.findCard(parseInt(cardId));
      const sourceListBeforeMove = this.lists.find(l => l.id === parseInt(fromListId));
      const targetListBeforeMove = this.lists.find(l => l.id === parseInt(toListId));
      if (!this.canDragCard(cardBeforeMove, sourceListBeforeMove) || !this.canDragCard(cardBeforeMove, targetListBeforeMove)) {
        this.initSortable();
        return;
      }

      // Find the card element in local state and move it
      let movedCard = null;
      this.lists.forEach(l => {
        const idx = l.cards.findIndex(c => c.id === parseInt(cardId));
        if (idx !== -1) {
          movedCard = l.cards.splice(idx, 1)[0];
        }
      });

      if (movedCard) {
        const targetList = this.lists.find(l => l.id === parseInt(toListId));
        if (targetList) {
          movedCard.board_list_id = parseInt(toListId);
          targetList.cards.splice(newIndex, 0, movedCard);

          // Update position index values for all cards in target list
          targetList.cards.forEach((c, idx) => {
            c.position = idx;
          });
        }
      }

      // Retrieve new order IDs from visual list container
      const container = document.getElementById(`cards-${toListId}`);
      if (!container) return;
      const cardEls = container.querySelectorAll('.kanban-card');
      const order = Array.from(cardEls).map(el => parseInt(el.dataset.id));

      try {
        // Save target list order
        const reorderRes = await this.api(`/boards/${this.boardSlug}/cards/reorder`, 'POST', {
          list_id: parseInt(toListId),
          source_list_id: parseInt(fromListId),
          moving_card_id: parseInt(cardId),
          order: order
        }, { silentErrors: true });
        if (reorderRes._ok === false) {
          setTimeout(() => window.location.reload(), 800);
          return;
        }

        // If moved to a different column, trigger move notifications/activities
        if (fromListId !== toListId) {
          const res = await this.api(`/boards/cards/${cardId}/move`, 'POST', {
            board_list_id: parseInt(toListId),
            source_list_id: parseInt(fromListId),
            position: newIndex
          }, { silentErrors: true });
          if (res._ok === false) {
            setTimeout(() => window.location.reload(), 800);
            return;
          }
          
          if (res.card) {
            const currentUser = this.allWorkspaceMembers.find(m => m.id === this.currentUserId) || {};
            const fromList = this.lists.find(l => l.id == fromListId)?.name || 'another list';
            const toList = this.lists.find(l => l.id == toListId)?.name || 'another list';

            // Only label a move as automated when the server confirms a rule ran.
            if (res.automation_triggered && res.card.board_id !== this.boardId) {
               // Remove it from targetList
               const currentList = this.lists.find(l => l.id == toListId);
               if (currentList) {
                 currentList.cards = currentList.cards.filter(c => c.id !== res.card.id);
               }
            } else if (res.automation_triggered && res.card.board_list_id !== parseInt(toListId)) {
               // Remove it from targetList
               const currentList = this.lists.find(l => l.id == toListId);
               if (currentList) {
                 currentList.cards = currentList.cards.filter(c => c.id !== res.card.id);
               }
               // Add it to actual target list
               const actualTargetList = this.lists.find(l => l.id == res.card.board_list_id);
               if (actualTargetList) {
                 actualTargetList.cards.push(res.card);
               }
            }

            if (window.showRichNotificationToast) {
              const automationNote = res.automation_triggered
                ? ` Automation then ${res.automation?.action_type === 'copy' ? 'copied' : 'moved'} the card (${res.automation?.reason || 'matching rule'}).`
                : '';

              window.showRichNotificationToast({
                actor_name: currentUser.name || 'You',
                actor_avatar: currentUser.avatar_url || currentUser.avatar || this.avatarUrl(currentUser) || '',
                actor_initials: currentUser.initials || currentUser.avatar_initials || '',
                actor_avatar_color: currentUser.avatar_color || '#64748b',
                card_title: res.card.title,
                description: `moved this card from **${fromList}** to **${toList}**.${automationNote}`,
                created_at: new Date().toISOString()
              });
            } else {
              window.showToast(`${currentUser.name || 'You'} moved "${res.card.title}" from ${fromList} to ${toList}.`);
            }

            if (this.activityOpen || (this.boardMenu.open && this.boardMenu.view === 'activity')) {
              await this.fetchBoardActivities();
            }
          }
        } else {
          window.showToast("Card position saved.");
        }
      } catch (err) {
        console.error("Failed to reorder:", err);
        window.showToast("Failed to save card positions", "error");
        this.initSortable();
      }
    },

    // ── Star / Star favorite toggle ──────────────────────────────────────────
    async toggleStar() {
      this.board.is_starred = !this.board.is_starred;
      try {
        await this.api(`/boards/${this.boardSlug}`, 'PATCH', {
          is_starred: this.board.is_starred
        });
        window.showToast(this.board.is_starred ? "Starred board!" : "Unstarred board!");
      } catch (e) {
        console.error(e);
        this.board.is_starred = !this.board.is_starred;
      }
    },

    async editChecklistInline(cl, title) {
      if (!title || title === (cl.name || cl.title)) return;
      try {
        const res = await this.api(`/boards/cards/${this.activeCard.id}/checklists/${cl.id}`, 'PATCH', { title });
        if (res.success && res.checklist) {
          cl.name = res.checklist.title;
          cl.title = res.checklist.title;
          this.refreshCardData();
        }
      } catch (e) {
        console.error(e);
        window.showToast('Failed to edit checklist', 'error');
      }
    },

    async editChecklistItemInline(cl, item, title) {
      if (!title || title === item.title || title === item.content) return;
      try {
        const res = await this.api(`/boards/cards/${this.activeCard.id}/checklists/${cl.id}/items/${item.id}`, 'PATCH', { title });
        if (res.success && res.item) {
          item.title = res.item.content;
          item.content = res.item.content;
          this.refreshCardData();
        }
      } catch (e) {
        console.error(e);
        window.showToast('Failed to edit item', 'error');
      }
    },

    async toggleStarDirect(targetBoardId) {
      let b = null;
      for (const ws of this.allWorkspaces) {
        b = ws.boards.find(x => x.id === targetBoardId);
        if (b) break;
      }
      if (!b) return;

      b.is_starred = !b.is_starred;
      // also update current board if it matches
      if (b.id === this.boardId) {
        this.board.is_starred = b.is_starred;
      }

      try {
        await this.api(`/boards/${b.slug}`, 'PATCH', {
          is_starred: b.is_starred
        });
        window.showToast(b.is_starred ? "Starred board!" : "Unstarred board!");
      } catch (e) {
        console.error(e);
        b.is_starred = !b.is_starred;
        if (b.id === this.boardId) this.board.is_starred = b.is_starred;
      }
    },


    // ── Multiple Filters ─────────────────────────────────────────────────────
    activeFiltersCount() {
      let count = 0;
      if (this.searchQuery.trim()) count++;
      if (this.filterPriority) count++;
      if (this.filterAssignee) count++;
      if (this.filterDateFrom) count++;
      if (this.filterDateTo) count++;
      return count;
    },

    clearFilters() {
      this.searchQuery = '';
      this.filterPriority = '';
      this.filterAssignee = '';
      this.filterDateFrom = '';
      this.filterDateTo = '';
      this.searchOpen = false;
      this.filtersOpen = false;
    },

    filteredCards(list) {
      const cards = list.cards.filter(c => {
        // Search text
        if (this.searchQuery.trim()) {
          const q = this.searchQuery.toLowerCase();
          const matchTitle = c.title && c.title.toLowerCase().includes(q);
          const matchDesc = c.description && c.description.toLowerCase().includes(q);
          if (!matchTitle && !matchDesc) return false;
        }

        // Priority
        if (this.filterPriority) {
          if (c.priority !== this.filterPriority) return false;
        }

        // Assignee
        if (this.filterAssignee) {
          const hasAssignee = c.assignees && c.assignees.some(u => u.id === parseInt(this.filterAssignee));
          if (!hasAssignee) return false;
        }

        if (this.filterDateFrom || this.filterDateTo) {
          const cardDate = c.due_at || c.start_date || '';
          if (!cardDate) return false;
          if (this.filterDateFrom && cardDate < this.filterDateFrom) return false;
          if (this.filterDateTo && cardDate > this.filterDateTo) return false;
        }

        return true;
      });

      if (this.isBlockList(list)) {
        return [...cards].sort((a, b) => {
          const aDone = a.block_completed_at ? 1 : 0;
          const bDone = b.block_completed_at ? 1 : 0;
          if (aDone !== bDone) return aDone - bDone;
          return (a.position ?? 0) - (b.position ?? 0);
        });
      }

      return cards;
    },

    findCard(cardId) {
      for (const list of this.lists) {
        const card = list.cards.find(c => c.id === cardId);
        if (card) return card;
      }
      return null;
    },

    isBlockList(list) {
      const name = typeof list === 'string' ? list : (list?.name || '');
      return name.toLowerCase().includes('block');
    },

    canDragCard(card, list) {
      if (!card) return false;
      if (this.isBlockList(list)) return !!this.currentUser.can_manage_blocked_cards;
      if (this.currentUser.can_move_any_card) return true;
      return (card.assignees || []).some(u => parseInt(u.id) === parseInt(this.currentUserId));
    },

    async completeBlockedCard(card, list) {
      if (!this.isBlockList(list) || !this.currentUser.can_manage_blocked_cards) {
        window.showToast('Only supervisors can complete blocked cards.', 'error');
        return;
      }

      const res = await this.api(`/boards/cards/${card.id}/block-complete`, 'POST', {});
      if (res.card) {
        Object.assign(card, res.card);
        window.showToast(res.message || 'Blocked card updated.');
      }
    },

    // ── Board Activity Drawer ────────────────────────────────────────────────
    async toggleActivityDrawer() {
      this.activityOpen = !this.activityOpen;
      if (this.activityOpen) {
        await this.fetchBoardActivities();
      }
    },

    async fetchBoardActivities() {
      const res = await this.api(`/boards/${this.boardSlug}/activities`, 'GET');
      if (res.activities) {
        this.activities = res.activities;
      }
    },

    // ── Board Menu Drawer ─────────────────────────────────────────────────────
    openBoardMenu(view = 'menu') {
      this.boardMenu.open = true;
      this.boardMenu.settingsName = this.board.name || '';
      this.boardMenu.settingsDescription = this.board.description || '';
      this.boardMenu.settingsWorkspaceId = this.board.workspace_id || '';
      this.boardMenu.settingsVisibility = this.board.visibility || 'workspace';
      this.boardMenu.settingsMemberPermissions = this.board.member_permissions || 'members';
      this.boardMenu.settingsCardCoversEnabled = this.board.card_covers_enabled !== false;
      this.boardMenu.settingsNotificationsEnabled = this.board.notifications_enabled !== false;
      this.boardMenu.settingsBrowserNotificationsEnabled = this.board.browser_notifications_enabled === true;
      this.boardMenu.backgroundType = this.board.background_type || 'color';
      this.boardMenu.backgroundValue = this.board.background_value || '#0ea5e9';
      this.boardMenu.backgroundColorDraft = this.boardMenu.backgroundType === 'color'
        ? this.boardMenu.backgroundValue
        : '#2F68ED';
      this.boardMenu.backgroundImageUrl = this.boardMenu.backgroundType === 'image'
        ? this.boardMenu.backgroundValue
        : '';
      this.boardMenu.copyName = `${this.board.name || 'Board'} copy`;
      this.boardMenu.copiedBoardUrl = '';
      this.openBoardMenuView(view);
    },

    closeBoardMenu() {
      this.boardMenu.open = false;
      this.boardMenu.view = 'menu';
    },

    openBoardMenuView(view) {
      this.boardMenu.view = view;
      if (view === 'activity') this.fetchBoardActivities();
      if (view === 'archived') this.fetchArchivedItems();
      if (view === 'automation') {
        this.fetchAutomations();
        this.resetAutomationForm();
      }
    },

    boardMenuTitle() {
      const titles = {
        menu: 'Board menu',
        about: 'About this board',
        visibility: 'Visibility',
        share: 'Print, export, and share',
        settings: 'Settings',
        background: 'Change background',
        labels: 'Labels',
        activity: 'Activity',
        archived: 'Archived items',
        watch: 'Watch board',
        copy: 'Copy board',
        leave: 'Leave board',
      };
      return titles[this.boardMenu.view] || 'Board menu';
    },

    async saveBoardMenuSettings() {
      const bm = this.boardMenu;
      bm.busy = true;
      const res = await this.api(`/boards/${this.boardSlug}`, 'PATCH', {
        name: bm.settingsName.trim(),
        description: bm.settingsDescription,
        workspace_id: bm.settingsWorkspaceId || this.board.workspace_id,
        visibility: bm.settingsVisibility,
        background_type: bm.backgroundType,
        background_value: bm.backgroundValue,
        member_permissions: bm.settingsMemberPermissions,
        card_covers_enabled: bm.settingsCardCoversEnabled,
        notifications_enabled: bm.settingsNotificationsEnabled,
        browser_notifications_enabled: bm.settingsBrowserNotificationsEnabled,
      });
      bm.busy = false;

      if (res.board) {
        this.syncBoardData(res.board);
        window.showToast('Board settings saved.');
      } else if (res.message) {
        window.showToast(res.message);
      }
    },

    async saveBoardMenuVisibility(visibility) {
      this.boardMenu.settingsVisibility = visibility;
      await this.saveBoardMenuSettings();
    },

    async saveBoardMenuBackground(type, value) {
      const bm = this.boardMenu;
      let nextValue = String(value || '').trim();

      if (!nextValue) {
        window.showToast('Choose a background value first.', 'error');
        return;
      }

      if (type === 'color') {
        if (/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/.test(nextValue)) {
          nextValue = `#${nextValue}`;
        }

        if (!/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(nextValue)) {
          window.showToast('Choose a valid hex background color.', 'error');
          return;
        }
      }

      if (type === 'image') {
        if (nextValue.startsWith('#')) {
          await this.saveBoardMenuBackground('color', nextValue);
          return;
        }

        const isLocalStorageImage = nextValue.startsWith('/storage/') || nextValue.startsWith('storage/');
        try {
          if (!isLocalStorageImage) new URL(nextValue);
        } catch {
          window.showToast('Enter a valid image URL, or use the color picker for hex colors.', 'error');
          return;
        }
      }

      bm.backgroundType = type;
      bm.backgroundValue = nextValue;
      if (type === 'color') bm.backgroundColorDraft = nextValue;
      if (type === 'image') bm.backgroundImageUrl = nextValue;
      bm.busy = true;
      const res = await this.api(`/boards/${this.boardSlug}`, 'PATCH', {
        background_type: type,
        background_value: nextValue,
      });
      bm.busy = false;

      if (res.board) {
        this.syncBoardData(res.board);
        window.showToast('Board background updated.');
      }
    },

    async uploadBoardBackground(event) {
      const file = event.target.files?.[0];
      if (!file) return;

      if (!file.type.startsWith('image/')) {
        window.showToast('Choose an image file for the board background.', 'error');
        event.target.value = '';
        return;
      }

      if (file.size > 8 * 1024 * 1024) {
        window.showToast('Board background image must be 8 MB or smaller.', 'error');
        event.target.value = '';
        return;
      }

      const bm = this.boardMenu;
      bm.busy = true;

      const formData = new FormData();
      formData.append('background_image', file);

      try {
        const response = await fetch(`/boards/${this.boardSlug}/background`, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': this.csrfToken,
          },
          body: formData,
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
          const firstError = payload.errors
            ? Object.values(payload.errors).flat()[0]
            : (payload.error || payload.message || 'Upload failed.');
          window.showToast(firstError, 'error');
          return;
        }

        if (payload.board) {
          this.syncBoardData(payload.board);
          bm.backgroundType = 'image';
          bm.backgroundValue = payload.board.background_value;
          bm.backgroundImageUrl = payload.board.background_value;
          window.showToast(payload.message || 'Board background image uploaded.');
        }
      } catch (error) {
        console.error('Board background upload failed:', error);
        window.showToast('Board background upload failed.', 'error');
      } finally {
        bm.busy = false;
        event.target.value = '';
      }
    },

    syncBoardData(updated) {
      const previousWorkspaceId = this.board.workspace_id;
      this.board = {
        ...this.board,
        id: updated.id ?? this.board.id,
        name: updated.name ?? this.board.name,
        slug: updated.slug ?? this.board.slug,
        description: updated.description ?? '',
        workspace_id: updated.workspace_id ?? this.board.workspace_id,
        visibility: updated.visibility ?? this.board.visibility,
        background_type: updated.background_type ?? this.board.background_type,
        background_value: updated.background_value ?? this.board.background_value,
        member_permissions: updated.member_permissions ?? this.board.member_permissions ?? 'members',
        card_covers_enabled: updated.card_covers_enabled ?? this.board.card_covers_enabled ?? true,
        notifications_enabled: updated.notifications_enabled ?? this.board.notifications_enabled ?? true,
        browser_notifications_enabled: updated.browser_notifications_enabled ?? this.board.browser_notifications_enabled ?? false,
        is_starred: Boolean(updated.is_starred),
        is_archived: Boolean(updated.is_archived),
        can_manage_board: updated.can_manage_board ?? this.board.can_manage_board,
        can_delete_board: updated.can_delete_board ?? this.board.can_delete_board,
      };

      for (const ws of this.allWorkspaces) {
        if (this.board.is_archived) {
          ws.boards = (ws.boards || []).filter(b => b.id !== this.boardId);
          continue;
        }

        const board = (ws.boards || []).find(b => b.id === this.boardId);
        if (board) {
          Object.assign(board, {
            name: this.board.name,
            slug: this.board.slug,
            workspace_id: this.board.workspace_id,
            is_starred: this.board.is_starred,
            background_type: this.board.background_type,
            background_value: this.board.background_value,
          });
        }
      }

      if (previousWorkspaceId !== this.board.workspace_id) {
        for (const ws of this.allWorkspaces) {
          if (ws.id === previousWorkspaceId) {
            ws.boards = (ws.boards || []).filter(b => b.id !== this.boardId);
          }
        }

        const newWorkspace = this.allWorkspaces.find(ws => ws.id === this.board.workspace_id);
        if (newWorkspace && !(newWorkspace.boards || []).some(b => b.id === this.boardId)) {
          if (!newWorkspace.boards) newWorkspace.boards = [];
          newWorkspace.boards.push({
            id: this.board.id,
            name: this.board.name,
            slug: this.board.slug,
            is_starred: this.board.is_starred,
            background_type: this.board.background_type,
            background_value: this.board.background_value,
          });
        }
      }

      document.title = this.board.name;
    },

    async requestBrowserNotifications() {
      if (!('Notification' in window)) {
        this.boardMenu.settingsBrowserNotificationsEnabled = false;
        window.showToast('This browser does not support desktop notifications.', 'error');
        return;
      }

      if (Notification.permission === 'granted') {
        this.boardMenu.settingsBrowserNotificationsEnabled = true;
        window.showToast('Browser notifications are enabled.');
        return;
      }

      const permission = await Notification.requestPermission();
      this.boardMenu.settingsBrowserNotificationsEnabled = permission === 'granted';
      window.showToast(permission === 'granted' ? 'Browser notifications are enabled.' : 'Browser notifications were not enabled.');
    },

    async archiveBoard() {
      if (!await window.confirmModal(`Archive "${this.board.name}"?`)) return;

      this.boardMenu.busy = true;
      const res = await this.api(`/boards/${this.boardSlug}`, 'PATCH', { is_archived: true });
      this.boardMenu.busy = false;

      if (res.board) {
        this.syncBoardData(res.board);
        window.showToast('Board archived.');
        setTimeout(() => { window.location.href = '/boards'; }, 700);
      }
    },

    async deleteBoard() {
      if (!this.board.can_delete_board) {
        window.showToast('Only board admins can delete this board.', 'error');
        return;
      }

      if (!await window.confirmModal(`Permanently delete board "<strong>${this.board.name}</strong>"?<br><span class="text-rose-600 font-bold text-xs">⚠️ This cannot be undone. All lists and cards will be lost.</span>`)) return;

      this.boardMenu.busy = true;
      const res = await this.api(`/boards/${this.boardSlug}`, 'DELETE');
      this.boardMenu.busy = false;

      if (res.message) {
        window.showToast(res.message);
        setTimeout(() => { window.location.href = '/boards'; }, 700);
      }
    },

    async hideBoard() {
      if (!await window.confirmModal(`Hide board "<strong>${this.board.name}</strong>"?<br><span class="text-slate-600 font-bold text-xs">It will be removed from the workspaces view, but super-admins can unhide it later.</span>`)) return;

      this.boardMenu.busy = true;
      try {
        const res = await fetch(`/boards/${this.boardSlug}/toggle-hidden`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
          }
        });
        if (!res.ok) throw new Error('Failed to hide board');
        
        window.showToast('Board hidden successfully.');
        setTimeout(() => { window.location.href = '/boards'; }, 700);
      } catch (err) {
        console.error(err);
        window.showToast('Error hiding board', 'error');
        this.boardMenu.busy = false;
      }
    },

    boardWatchStorageKey() {
      return `dgt-board-watch-${this.boardId}`;
    },

    toggleBoardWatch() {
      this.boardMenu.watched = !this.boardMenu.watched;
      localStorage.setItem(this.boardWatchStorageKey(), String(this.boardMenu.watched));
      window.showToast(this.boardMenu.watched ? 'Watching board.' : 'Stopped watching board.');
    },

    async copyCurrentBoardLink() {
      const url = `${window.location.origin}/boards/${this.boardSlug}`;
      try {
        await navigator.clipboard.writeText(url);
        window.showToast('Board link copied.');
      } catch {
        await window.promptModal({
          title: 'Copy board link',
          message: 'Copy this URL manually.',
          inputLabel: 'Board link',
          value: url,
          readonly: true,
          required: false,
          confirmText: 'Done',
          cancelText: 'Close',
        });
      }
    },

    async shareCurrentBoard() {
      const url = `${window.location.origin}/boards/${this.boardSlug}`;
      if (navigator.share) {
        try {
          await navigator.share({ title: this.board.name, url });
          return;
        } catch {
          return;
        }
      }
      await this.copyCurrentBoardLink();
    },

    printBoard() {
      window.print();
    },

    async copyBoard() {
      const bm = this.boardMenu;
      if (!bm.copyName.trim()) return;

      bm.busy = true;
      bm.copiedBoardUrl = '';
      const res = await this.api(`/boards/${this.boardSlug}/copy`, 'POST', {
        name: bm.copyName.trim(),
        include_cards: bm.copyIncludeCards,
      });
      bm.busy = false;

      if (res.board) {
        bm.copiedBoardUrl = res.board.url || `/boards/${res.board.slug}`;
        window.showToast(res.message || 'Board copied.');
      }
    },

    async fetchArchivedItems() {
      const bm = this.boardMenu;
      bm.archivedLoading = true;
      const res = await this.api(`/boards/${this.boardSlug}/archived`, 'GET');
      bm.archivedLoading = false;
      bm.archivedCards = res.cards || [];
      bm.archivedLists = res.lists || [];
    },

    async restoreArchivedItem(type, id) {
      const url = type === 'list' ? `/boards/lists/${id}` : `/boards/cards/${id}`;
      const res = await this.api(url, 'PATCH', { is_archived: false });
      if (res.message || res.card || res.list) {
        window.showToast(type === 'list' ? 'List restored.' : 'Card restored.');
        await this.fetchArchivedItems();
        setTimeout(() => window.location.reload(), 500);
      }
    },

    async leaveBoard() {
      if (!this.currentUserId) return;
      if (!await window.confirmModal('Leave this board?')) return;

      const res = await this.api(`/boards/${this.boardSlug}/members/${this.currentUserId}`, 'DELETE');
      if (res.message) {
        window.showToast(res.message);
        setTimeout(() => { window.location.href = '/boards'; }, 700);
      } else if (res.error) {
        window.showToast(res.error, 'error');
      }
    },

    // ── Date Formatting & helpers ────────────────────────────────────────────
    parseCardDate(dateStr, dueTime = '') {
      if (!dateStr) return null;

      const rawDate = String(dateStr).trim();
      const dateMatch = rawDate.match(/^(\d{4})-(\d{2})-(\d{2})/);
      const timeMatch = String(dueTime || '').trim().match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);

      if (dateMatch && timeMatch) {
        const year = parseInt(dateMatch[1], 10);
        const month = parseInt(dateMatch[2], 10);
        const day = parseInt(dateMatch[3], 10);
        const hour = parseInt(timeMatch[1], 10);
        const minute = parseInt(timeMatch[2], 10);
        const second = parseInt(timeMatch[3] || '0', 10);
        return new Date(Date.UTC(year, month - 1, day, hour - 7, minute, second));
      }

      if (rawDate.includes('T')) {
        const parsed = new Date(rawDate);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
      }

      if (dateMatch) {
        const year = parseInt(dateMatch[1], 10);
        const month = parseInt(dateMatch[2], 10);
        const day = parseInt(dateMatch[3], 10);
        return new Date(Date.UTC(year, month - 1, day, -7, 0, 0));
      }

      const parsed = new Date(rawDate);
      return Number.isNaN(parsed.getTime()) ? null : parsed;
    },

    formatDateShort(dateStr) {
      const d = this.parseCardDate(dateStr);
      if (!d) return '';
      return d.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: this.khTimeZone,
      });
    },

    formatDueBadge(dateStr, dueTime = '', status = '') {
      const d = this.parseCardDate(dateStr, dueTime);
      if (!d) return 'Set due date';

      const dateLabel = d.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: this.khTimeZone,
      });

      const rawDate = String(dateStr || '').trim();
      const inlineTimeMatch = rawDate.match(/T(\d{2}):(\d{2})(?::(\d{2}))?/);
      const hasInlineTime = !!(
        inlineTimeMatch &&
        (inlineTimeMatch[1] !== '00' || inlineTimeMatch[2] !== '00' || (inlineTimeMatch[3] || '00') !== '00')
      );

      let timeLabel = '';
      if (String(dueTime || '').trim() || hasInlineTime) {
        timeLabel = d.toLocaleTimeString('en-US', {
          hour: '2-digit',
          minute: '2-digit',
          hour12: true,
          timeZone: this.khTimeZone,
        }).toLowerCase();
      }

      const donePrefix = status === 'done' ? '✓ ' : '';
      return `${donePrefix}${dateLabel}${timeLabel ? ` - ${timeLabel}` : ''}`;
    },

    isOverdue(card) {
      if (!card?.due_at) return false;
      const due = this.parseCardDate(card.due_at, card?.due_time);
      if (!due) return false;
      return due.getTime() < Date.now();
    },

    formatDate(dateStr) {
      const d = this.parseCardDate(dateStr);
      if (!d) return '';
      return d.toLocaleDateString('en-AU', {
        day: 'numeric',
        month: 'short',
        timeZone: this.khTimeZone,
      });
    },

    // ── Automations ────────────────────────────────────────────────────────
    async fetchAutomations() {
      this.boardMenu.busy = true;
      try {
        const res = await this.api(`/boards/${this.boardSlug}/automations`, 'GET');
        this.automations = res.automations || [];
      } catch (e) {
        console.error(e);
      } finally {
        this.boardMenu.busy = false;
      }
    },

    fetchTargetLists() {
      this.targetBoardLists = [];
      this.targetBoardMembers = [];
      this.newAutomation.target_list_id = '';
      this.newAutomation.combined_assignee = '';
      
      const targetBoardId = parseInt(this.newAutomation.target_board_id);
      if (!targetBoardId) return;

      for (const ws of this.allWorkspaces) {
        for (const b of ws.boards) {
          if (b.id === targetBoardId) {
            this.targetBoardLists = b.lists || [];
            this.targetBoardMembers = b.members || [];
            return;
          }
        }
      }
    },

    fetchTriggerLists() {
      this.triggerBoardLists = [];
      this.newAutomation.trigger_list_id = '';
      
      const triggerBoardId = parseInt(this.newAutomation.trigger_board_id);
      if (!triggerBoardId) {
        // If empty, use current board lists
        this.triggerBoardLists = this.lists;
        return;
      }

      for (const ws of this.allWorkspaces) {
        for (const b of ws.boards) {
          if (b.id === triggerBoardId) {
            this.triggerBoardLists = b.lists || [];
            return;
          }
        }
      }
    },

    editAutomation(rule) {
      let combinedAssignee = '';
      if (rule.target_assignee_role) {
        combinedAssignee = 'role_' + rule.target_assignee_role;
      } else if (rule.target_assignee_id) {
        combinedAssignee = 'user_' + rule.target_assignee_id;
      }

      this.newAutomation = {
        id: rule.id,
        trigger_word: rule.trigger_word || '',
        trigger_board_id: rule.trigger_board_id || '',
        trigger_list_id: rule.trigger_list_id || '',
        target_board_id: rule.target_board_id || '',
        target_list_id: rule.target_list_id || '',
        combined_assignee: combinedAssignee,
        action_type: rule.action_type || 'move'
      };
      this.fetchTriggerLists();
      this.newAutomation.trigger_list_id = rule.trigger_list_id || '';
      this.fetchTargetLists();
      this.newAutomation.target_list_id = rule.target_list_id || '';
      this.newAutomation.combined_assignee = combinedAssignee;
    },

    openCardAutomation() {
      this.cardAutomation.open = !this.cardAutomation.open;
      this.cardAutomation.filterWord = '';
      this.cardAutomation.triggerListId = this.activeCard ? this.activeCard.board_list_id : '';
      this.cardAutomation.targetBoardId = '';
      this.cardAutomation.targetListId = '';
      this.cardAutomation.targetLists = [];
      this.cardAutomation.targetMembers = [];
      this.cardAutomation.combined_assignee = '';
      this.cardAutomation.action_type = 'move';
    },

    async fetchCardAutomationTargetLists() {
      const targetBoardId = parseInt(this.cardAutomation.targetBoardId);
      if (!targetBoardId) {
        this.cardAutomation.targetLists = [];
        return;
      }
      let targetSlug = null;
      for (const ws of this.allWorkspaces) {
        const found = (ws.boards || []).find(b => b.id === targetBoardId);
        if (found) {
          targetSlug = found.slug;
          break;
        }
      }
      if (!targetSlug) return;
      // Use allWorkspaces to find lists and members locally instead of API call if possible, to get members too
      for (const ws of this.allWorkspaces) {
        for (const b of ws.boards) {
          if (b.id === targetBoardId) {
            this.cardAutomation.targetLists = b.lists || [];
            this.cardAutomation.targetMembers = b.members || [];
            return;
          }
        }
      }
      
      // Fallback if not found locally
      try {
        const res = await this.api(`/boards/${targetSlug}/lists`, 'GET');
        if (res.lists) {
          this.cardAutomation.targetLists = res.lists;
        }
      } catch (e) {
        console.error(e);
      }
    },

    async saveCardAutomation() {
      const ca = this.cardAutomation;
      if ((!ca.filterWord && !ca.triggerListId) || !ca.targetBoardId || !ca.targetListId) {
        window.showToast('Please fill trigger keyword/list and select target board/list.', 'error');
        return;
      }

      let assigneeId = null;
      let assigneeRole = null;
      if (ca.combined_assignee) {
        if (ca.combined_assignee.startsWith('role_')) {
          assigneeRole = ca.combined_assignee.substring(5);
        } else if (ca.combined_assignee.startsWith('user_')) {
          assigneeId = ca.combined_assignee.substring(5);
        }
      }

      try {
        const payload = {
          trigger_word: ca.filterWord,
          trigger_board_id: this.boardId,
          trigger_list_id: ca.triggerListId || null,
          target_board_id: parseInt(ca.targetBoardId),
          target_list_id: parseInt(ca.targetListId),
          target_assignee_id: assigneeId,
          target_assignee_role: assigneeRole,
          action_type: ca.action_type || 'move'
        };

        const res = await this.api(`/boards/${this.boardSlug}/automations`, 'POST', payload);
        if (res.automation) {
          this.automations.push(res.automation);
          window.showToast('Automation created!');
          ca.open = false;
        }
      } catch (e) {
        window.showToast(e.message || 'Error creating automation', 'error');
      }
    },

    openExportModal() {
      this.closeBoardMenu();
      const em = this.exportModal;
      em.open = true;
      em.format = 'pdf';
      em.scope = 'board';
      em.selectedBoards = [this.boardId];
      em.dateRange = 'all_time';
      em.startDate = '';
      em.endDate = '';
      em.memberId = 'all';
      em.statuses = ['draft', 'in_progress', 'review', 'completed', 'archived'];
      em.includeDesc = false;
      em.includeComments = false;
    },

    triggerExport() {
      const em = this.exportModal;
      const params = new URLSearchParams();
      
      // Format
      params.append('format', em.format);

      // Scope: check if we are exporting just this board or multiple selected boards
      if (em.scope === 'board') {
        params.append('board_ids[]', this.boardId);
      } else {
        if (em.selectedBoards.length === 0) {
          window.showToast('Please select at least one board to export.', 'error');
          return;
        }
        em.selectedBoards.forEach(id => {
          params.append('board_ids[]', id);
        });
      }

      // Date Range
      params.append('date_range', em.dateRange);
      if (em.dateRange === 'custom_period') {
        if (em.startDate) params.append('start_date', em.startDate);
        if (em.endDate) params.append('end_date', em.endDate);
      }

      // Member
      params.append('member_id', em.memberId);

      // Statuses
      if (em.statuses.length === 0) {
        window.showToast('Please select at least one status to export.', 'error');
        return;
      }
      em.statuses.forEach(s => {
        params.append('statuses[]', s);
      });

      // Display options
      params.append('include_comments', em.includeComments ? '1' : '0');
      params.append('include_desc', em.includeDesc ? '1' : '0');

      const route = em.format === 'pdf' ? 'export/pdf' : 'export/csv';
      const url = `/boards/${this.boardSlug}/${route}?${params.toString()}`;

      if (em.format === 'pdf') {
        // Open PDF report in a new tab for printing
        window.open(url, '_blank');
      } else {
        // Download CSV file directly
        window.location.href = url;
      }

      em.open = false;
    },

    // ── Import Methods ──────────────────────────────────────────────────────────

    openImportModal() {
      this.closeBoardMenu();
      const im = this.importModal;
      im.open = true;
      im.step = 1;
      im.source = 'csv';
      im.sheetsUrl = '';
      im.file = null;
      im.preview = null;
      im.error = null;
      im.result = null;
      im.busy = false;
      im.previewFilter = 'all';
    },

    closeImportModal() {
      this.importModal.open = false;
      this.importModal.busy = false;
    },

    importHandleDrop(event) {
      this.importModal.dragOver = false;
      const file = event.dataTransfer?.files?.[0];
      if (file && (file.type.includes('csv') || file.name.endsWith('.csv'))) {
        this.importModal.file = file;
      } else {
        window.showToast('Please upload a valid CSV file.', 'error');
      }
    },

    importHandleFileSelect(event) {
      const file = event.target.files?.[0];
      if (file) {
        this.importModal.file = file;
      }
    },

    downloadImportTemplate() {
      window.location.href = `/boards/${this.boardSlug}/import/template`;
    },

    async importPreview() {
      const im = this.importModal;
      im.error = null;
      im.busy = true;

      const formData = new FormData();
      if (im.source === 'csv') {
        if (!im.file) {
          im.error = 'Please select a file first.';
          im.busy = false;
          return;
        }
        formData.append('file', im.file);
      } else {
        if (!im.sheetsUrl.trim()) {
          im.error = 'Please provide a Sheets URL.';
          im.busy = false;
          return;
        }
        formData.append('sheets_url', im.sheetsUrl.trim());
      }

      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || this.csrfToken;
        const res = await fetch(`/boards/${this.boardSlug}/import/preview`, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
          },
          body: formData
        });

        const data = await res.json();
        if (!res.ok) {
          throw new Error(data.error || data.message || 'Failed to preview import.');
        }

        im.preview = data;
        im.step = 2;
      } catch (err) {
        im.error = err.message;
      } finally {
        im.busy = false;
      }
    },

    importFilteredPreviewRows() {
      const rows = this.importModal.preview?.rows || [];
      if (this.importModal.previewFilter === 'invalid') {
        return rows.filter(r => !r.valid);
      }
      return rows;
    },

    async importConfirm() {
      const im = this.importModal;
      if (!im.preview || im.preview.valid === 0) return;

      im.busy = true;
      im.error = null;

      try {
        const payload = {
          rows: im.preview.rows
        };

        const res = await this.api(`/boards/${this.boardSlug}/import/confirm`, 'POST', payload);
        
        if (res.cards && Array.isArray(res.cards)) {
          // Push new cards directly into their respective lists
          res.cards.forEach(newCard => {
            const list = this.lists.find(l => l.id === newCard.board_list_id);
            if (list) {
              list.cards.push(newCard);
              // Re-sort list cards by position just in case
              list.cards.sort((a, b) => a.position - b.position);
            }
          });
        }

        im.result = res;
        im.step = 3;
      } catch (err) {
        im.error = err.message || 'Failed to import cards.';
      } finally {
        im.busy = false;
      }
    },

    resetAutomationForm() {
      this.newAutomation = { id: null, trigger_word: '', trigger_board_id: '', trigger_list_id: '', target_board_id: '', target_list_id: '', combined_assignee: '', action_type: 'move' };
      this.targetBoardLists = [];
      this.targetBoardMembers = [];
      this.triggerBoardLists = this.lists;
    },

    async saveAutomation() {
      if ((!this.newAutomation.trigger_word && !this.newAutomation.trigger_list_id) || 
          !this.newAutomation.target_board_id || !this.newAutomation.target_list_id) return;

      this.boardMenu.busy = true;
      try {
        let assigneeId = '';
        let assigneeRole = '';
        if (this.newAutomation.combined_assignee) {
          if (this.newAutomation.combined_assignee.startsWith('role_')) {
            assigneeRole = this.newAutomation.combined_assignee.substring(5);
          } else if (this.newAutomation.combined_assignee.startsWith('user_')) {
            assigneeId = this.newAutomation.combined_assignee.substring(5);
          }
        }
        
        const payload = {
          ...this.newAutomation,
          target_assignee_id: assigneeId,
          target_assignee_role: assigneeRole
        };

        if (this.newAutomation.id) {
          // Update
          const res = await this.api(`/boards/${this.boardSlug}/automations/${this.newAutomation.id}`, 'PUT', payload);
          if (res.automation) {
            const idx = this.automations.findIndex(a => a.id === this.newAutomation.id);
            if (idx !== -1) this.automations.splice(idx, 1, res.automation);
            this.resetAutomationForm();
            window.showToast('Automation updated!');
          }
        } else {
          // Create
          const res = await this.api(`/boards/${this.boardSlug}/automations`, 'POST', payload);
          if (res.automation) {
            this.automations.push(res.automation);
            this.resetAutomationForm();
            window.showToast('Automation created!');
          }
        }
      } catch (e) {
        window.showToast(e.message || 'Error creating automation', 'error');
      } finally {
        this.boardMenu.busy = false;
      }
    },

    async deleteAutomation(id) {
      if (!confirm('Are you sure you want to delete this automation rule?')) return;
      try {
        await this.api(`/boards/${this.boardSlug}/automations/${id}`, 'DELETE');
        this.automations = this.automations.filter(a => a.id !== id);
        window.showToast('Automation deleted');
      } catch (e) {
        window.showToast(e.message || 'Error deleting automation', 'error');
      }
    },

    // ── Add list ─────────────────────────────────────────────────────────────
    async saveList() {
      const name = this.newListName.trim();
      if (!name) return;

      const res = await this.api(`/boards/${this.boardSlug}/lists`, 'POST', { name });
      if (res.list) {
        this.lists.push({ ...res.list, cards: [] });
        this.newListName = '';
        this.addingList  = false;
        this.initSortable(); // Bind SortableJS to new list
        window.showToast(`List "${name}" created!`);
      }
    },

    startEditList(listId, currentName) {
      this.editingListId   = listId;
      this.editingListName = currentName;
      this.$nextTick(() => {
        const input = document.getElementById('list-input-' + listId);
        if (input) {
          input.focus();
          input.select();
        }
      });
    },

    async saveListName(listId) {
      if (this.editingListId !== listId) return;
      const name = this.editingListName.trim();
      if (!name) {
        this.editingListId = null;
        return;
      }

      const list = this.lists.find(l => l.id === listId);
      if (list) list.name = name;
      this.editingListId = null;

      const res = await this.api(`/boards/lists/${listId}`, 'PATCH', { name });
      if (res.message) {
        window.showToast("List renamed successfully!");
      }
    },

    async archiveList(listId) {
      if (!await window.confirmModal("Are you sure you want to archive this list?")) return;
      
      const res = await this.api(`/boards/lists/${listId}`, 'PATCH', { is_archived: true });
      if (res.list) {
        this.lists = this.lists.filter(l => l.id !== listId);
        window.showToast("List archived successfully!");
      }
    },

    async deleteList(listId) {
      if (!await window.confirmModal("Are you sure you want to delete this list and all its cards permanently?")) return;
      
      const res = await this.api(`/boards/lists/${listId}`, 'DELETE');
      if (res.message) {
        this.lists = this.lists.filter(l => l.id !== listId);
        window.showToast(res.message);
      }
    },

    // ── Add card ─────────────────────────────────────────────────────────────
    startAddCard(listId) {
      this.addingCardListId = listId;
      this.newCardTitle     = '';
      this.$nextTick(() => {
        const el = document.querySelector(`#cards-${listId}`);
        if (el) el.scrollTop = el.scrollHeight;
      });
    },

    async saveCard(listId) {
      const title = this.newCardTitle.trim();
      if (!title) return;

      const res = await this.api(`/boards/${this.boardSlug}/cards`, 'POST', {
        board_list_id: listId,
        title,
      });

      if (res.card) {
        const list = this.lists.find(l => l.id === listId);
        if (list) {
          list.cards.push({
            id:              res.card.id,
            title:           res.card.title,
            priority:        res.card.priority ?? 'medium',
            due_at:          res.card.due_at ?? null,
            labels:          [],
            assignees:       [],
            checklist_total: 0,
            checklist_done:  0,
            has_files:       false,
            comment_count:   0,
          });
        }
        this.newCardTitle     = '';
        this.addingCardListId = null;
        window.showToast(`Card "${title}" added!`);
      }
    },

    // ── Card detail modal ────────────────────────────────────────────────────
    async openCard(cardId) {
      this.activeCard     = null;
      this.cardLoading    = true;
      this.newComment     = '';
      this.cardActivities = [];
      this.isEditingDesc  = false;

      const res = await this.api(`/boards/cards/${cardId}`, 'GET');
      if (res.card) {
        this.activeCard = res.card;
        this.cardActivities = res.activities || [];
      }
      this.cardLoading = false;
    },

    // Silently refresh only the activity log and comments — no flicker
    async refreshActiveCard() {
      if (!this.activeCard) return;
      try {
        const res = await this.api(`/boards/cards/${this.activeCard.id}`, 'GET', null, { silentErrors: true });
        if (res.card) this.activeCard = res.card;
        if (res.activities) this.cardActivities = res.activities;
      } catch (_) {}
    },

    async refreshCardActivities() {
      if (!this.activeCard) return;
      try {
        const res = await this.api(`/boards/cards/${this.activeCard.id}`, 'GET');
        if (res.activities) this.cardActivities = res.activities;
        if (res.card?.comments) this.activeCard.comments = res.card.comments;
      } catch (_) {}
    },

    closeCard() {
      this.activeCard = null;
    },

    async updateCardField(fields) {
      if (!this.activeCard) return;
      const res = await this.api(`/boards/cards/${this.activeCard.id}`, 'PATCH', fields);
      if (res.card) {
        if (res.card_moved) {
          window.showToast('Card moved by automation!');
          const newCard = res.card;
          if (newCard.board_id !== this.boardId) {
            this.lists.forEach(l => l.cards = l.cards.filter(c => c.id !== this.activeCard.id));
            this.closeCard();
            return;
          } else if (newCard.board_list_id !== this.activeCard.board_list_id) {
            this.lists.forEach(l => l.cards = l.cards.filter(c => c.id !== this.activeCard.id));
            const targetList = this.lists.find(l => l.id === newCard.board_list_id);
            if (targetList) targetList.cards.push(newCard);
            this.closeCard();
            return;
          }
        }

        this.lists.forEach(l => {
          const c = l.cards.find(x => x.id === this.activeCard.id);
          if (c) Object.assign(c, res.card);
        });
        Object.assign(this.activeCard, res.card);
        window.showToast('Card updated successfully!');
        this.refreshCardActivities();
      }
    },

    // ── Members & Labels ──────────────────────────────────────────────────────
    async toggleMember(userId) {
      if (!this.activeCard) return;
      const res = await this.api(`/boards/cards/${this.activeCard.id}/members`, 'POST', { user_id: userId });
      if (res.assignees) {
        this.activeCard.assignees = res.assignees;
        this.lists.forEach(l => {
          const c = l.cards.find(x => x.id === this.activeCard.id);
          if (c) c.assignees = res.assignees;
        });
        this.loadBoardMembers();
        window.showToast(res.message);
        if (this.memberPicker.open && this.memberPicker.cardId === this.activeCard.id) {
          this.mpSeparateMembers(this.activeCard);
        }
        this.refreshCardActivities();
      }
    },

    // ── Member Picker ──────────────────────────────────────────────────────────────
    openMemberPicker(card) {
      if (!card) return;
      const mp       = this.memberPicker;
      mp.cardId      = card.id;
      mp.search      = '';
      mp.loading     = false;
      this.mpSeparateMembers(card);
      mp.open = true;
    },

    closeMemberPicker() {
      this.memberPicker.open = false;
    },

    // Split allBoardMembers / allWorkspaceMembers into three buckets for the picker
    mpSeparateMembers(card) {
      const mp            = this.memberPicker;
      const assigneeIds   = new Set((card.assignees || []).map(a => a.id));
      const boardMemberIds = new Set(this.allBoardMembers.map(u => u.id));

      // Currently assigned to this card (show first, with check mark)
      mp.cardMembers = (card.assignees || []).map(a => ({
        id:       a.id,
        name:     a.name,
        email:    a.email || '',
        avatar:   a.avatar || a.avatar_url || '',
        initials: a.avatar_initials || a.initials || this.avatarInitials(a),
        avatar_color: a.avatar_color || this.avatarColor(a),
      }));

      // Board members not yet on the card
      mp.boardMembers = this.allBoardMembers
        .filter(u => !assigneeIds.has(u.id))
        .map(u => ({ ...u }));

      // Workspace members intentionally excluded from card assignment
      mp.workspaceMembers = [];
    },

    // Live search: filter existing data first; fall back to server if needed
    async mpSearch() {
      const q  = this.memberPicker.search.toLowerCase().trim();
      const mp = this.memberPicker;
      const card = this.activeCard;
      if (!card) return;

      if (!q) {
        this.mpSeparateMembers(card);
        return;
      }

      // Client-side filter first (instant, no network)
      const match = u => u.name.toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q);
      const assigneeIds = new Set((card.assignees || []).map(a => a.id));
      const boardMemberIds = new Set(this.allBoardMembers.map(u => u.id));

      mp.cardMembers      = (card.assignees || []).filter(a => match({ name: a.name, email: a.email || '' }))
        .map(a => ({ ...a, initials: a.avatar_initials || a.initials || this.avatarInitials(a), avatar_color: a.avatar_color || this.avatarColor(a) }));
      mp.boardMembers     = this.allBoardMembers.filter(u => !assigneeIds.has(u.id) && match(u));
      mp.workspaceMembers = [];

      // If nothing found locally, ask the server
      if (!mp.cardMembers.length && !mp.boardMembers.length) {
        mp.loading = true;
        try {
          const res = await fetch(`/boards/${this.boardSlug}/members/search?q=${encodeURIComponent(q)}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
          });
          const data = await res.json();
          mp.boardMembers     = (data.board_members || []).filter(u => !assigneeIds.has(u.id));
          mp.workspaceMembers = [];
        } finally {
          mp.loading = false;
        }
      }
    },

    async mpToggleMember(user) {
      const card = this.activeCard;
      if (!card) return;

      const isAssigned = (card.assignees || []).some(a => a.id === user.id);
      const res = await this.api(`/boards/cards/${card.id}/members`, 'POST', { user_id: user.id });

      if (res.assignees !== undefined) {
        // Update active card
        card.assignees = res.assignees;
        // Update board list card
        this.lists.forEach(l => {
          const c = l.cards.find(x => x.id === card.id);
          if (c) c.assignees = res.assignees;
        });
        // Re-separate so the picker buckets update instantly
        this.mpSeparateMembers(card);
        this.loadBoardMembers();
        window.showToast(res.message || (isAssigned ? 'Member removed.' : 'Member added.'));
      }
    },

    // Deterministic pastel colour from a name string
    mpAvatarBg(name) {
      const palette = [
        '#6366f1','#8b5cf6','#ec4899','#f97316',
        '#10b981','#3b82f6','#14b8a6','#f59e0b',
        '#ef4444','#84cc16',
      ];
      let hash = 0;
      for (const ch of (name || '')) hash = (hash * 31 + ch.charCodeAt(0)) >>> 0;
      return palette[hash % palette.length];
    },


    async createNewBoardLabel(name) {
      if (!name.trim()) return;
      const color = this.mpAvatarBg(name + Date.now().toString());
      const res = await this.api(`/boards/${this.boardSlug}/labels`, 'POST', { name, color });
      if (res.label) {
        this.labels.push(res.label);
        this.search = '';
        this.toggleLabel(res.label.id);
        window.showToast(res.message);
      }
    },

    async toggleLabel(labelId) {
      if (!this.activeCard) return;
      const res = await this.api(`/boards/cards/${this.activeCard.id}/labels`, 'POST', { label_id: labelId });
      if (res.labels) {
        this.activeCard.labels = res.labels;
        this.lists.forEach(l => {
          const c = l.cards.find(x => x.id === this.activeCard.id);
          if (c) c.labels = res.labels;
        });
        window.showToast(res.message);
        this.refreshCardActivities();
      }
    },

    // ── Checklists ────────────────────────────────────────────────────────────
    async addChecklist() {
      if (!this.activeCard) return;
      const title = await window.promptModal({
        title: 'Add checklist',
        message: 'Create a Trello-style checklist for this card.',
        inputLabel: 'Checklist title',
        value: 'Tasks',
        placeholder: 'Tasks',
        confirmText: 'Add checklist',
      });
      if (!title) return;

      const res = await this.api(`/boards/cards/${this.activeCard.id}/checklists`, 'POST', { title });
      if (res.checklist) {
        if (!this.activeCard.checklists) this.activeCard.checklists = [];
        this.activeCard.checklists.push(res.checklist);
        window.showToast('Checklist added successfully!');
        this.refreshCardActivities();
      }
    },

    async deleteChecklist(cl) {
      if (!this.activeCard || !await window.confirmModal({
        title: 'Delete checklist?',
        message: `Delete checklist "<strong>${this.escapeHtml(cl.name || cl.title || 'Checklist')}</strong>"?`,
        confirmText: 'Delete checklist',
        tone: 'danger',
      })) return;
      const res = await this.api(`/boards/cards/${this.activeCard.id}/checklists/${cl.id}`, 'DELETE');
      if (res.success) {
        this.activeCard.checklists = this.activeCard.checklists.filter(x => x.id !== cl.id);
        window.showToast('Checklist removed.');
        this.refreshCardActivities();
      }
    },

    async addChecklistItem(cl) {
      if (!this.activeCard) return;
      const title = await window.promptModal({
        title: 'Add checklist item',
        message: 'Add a new item to this checklist.',
        inputLabel: 'Item name',
        placeholder: 'Add item',
        confirmText: 'Add item',
      });
      if (!title) return;

      const res = await this.api(`/boards/cards/${this.activeCard.id}/checklists/${cl.id}/items`, 'POST', { title });
      if (res.item) {
        if (!cl.items) cl.items = [];
        cl.items.push(res.item);
        this.updateCardChecklistProgress();
        window.showToast('Item added!');
        this.refreshCardActivities();
      }
    },

    async toggleChecklistItem(cl, item) {
      item.is_completed = !item.is_completed;
      const res = await this.api(`/boards/cards/${this.activeCard.id}/checklists/${cl.id}/items/${item.id}`, 'PATCH');
      if (res.success) {
        this.updateCardChecklistProgress();
        // Silently refresh activities in background
        this.refreshCardActivities();
      }
    },

    async deleteChecklistItem(cl, item) {
      if (!this.activeCard || !await window.confirmModal("Delete this checklist item?")) return;
      const res = await this.api(`/boards/cards/${this.activeCard.id}/checklists/${cl.id}/items/${item.id}`, 'DELETE');
      if (res.success) {
        cl.items = cl.items.filter(x => x.id !== item.id);
        this.updateCardChecklistProgress();
        window.showToast('Item deleted.');
        this.refreshCardActivities();
      }
    },

    updateCardChecklistProgress() {
      if (!this.activeCard) return;
      const total = this.activeCard.checklists.reduce((acc, curr) => acc + (curr.items?.length || 0), 0);
      const done = this.activeCard.checklists.reduce((acc, curr) => acc + (curr.items?.filter(i => i.is_completed).length || 0), 0);

      this.lists.forEach(l => {
        const c = l.cards.find(x => x.id === this.activeCard.id);
        if (c) {
          c.checklist_total = total;
          c.checklist_done = done;
        }
      });
    },

    // ── Switch Boards Modal ──────────────────────────────────────────────────
    openSwitchBoardsModal() {
      if (!this.switchBoardsModal.selectedWorkspace && this.allWorkspaces.length > 0) {
        const currentWs = this.sbmCurrentWorkspace();
        this.switchBoardsModal.selectedWorkspace = currentWs ? currentWs.id : this.allWorkspaces[0].id;
      }
      this.switchBoardsModal.search = '';
      this.switchBoardsModal.creating = false;
      this.switchBoardsModal.createBoardName = '';
      this.switchBoardsModal.open = true;
    },

    closeSwitchBoardsModal() {
      this.switchBoardsModal.open = false;
    },

    sbmCurrentTitle() {
      if (this.switchBoardsModal.search) return 'Search boards';
      if (this.switchBoardsModal.tab === 'your') return 'Your boards';
      if (this.switchBoardsModal.tab === 'starred') return 'Starred boards';
      if (this.switchBoardsModal.tab === 'recent') return 'Recent boards';
      
      const ws = this.allWorkspaces.find(w => w.id === this.switchBoardsModal.selectedWorkspace);
      return ws ? ws.name : 'Workspace boards';
    },

    sbmFilteredBoards() {
      const s = this.switchBoardsModal.search.trim().toLowerCase();
      let boards = s ? this.sbmAllBoards() : this.sbmBoardsForTab(this.switchBoardsModal.tab);
      if (s) {
        boards = boards.filter(b => {
          const workspaceName = this.sbmBoardWorkspaceName(b).toLowerCase();
          return (b.name || '').toLowerCase().includes(s) || workspaceName.includes(s);
        });
      }

      return this.sbmUniqueBoards(boards);
    },

    sbmAllBoards() {
      return this.allWorkspaces.flatMap(ws => ws.boards || []);
    },

    sbmBoardsForTab(tab) {
      if (tab === 'starred') {
        return this.sbmAllBoards().filter(b => b.is_starred);
      }

      if (tab === 'recent') {
        const current = this.sbmAllBoards().find(b => b.id === this.boardId);
        const others = this.sbmAllBoards().filter(b => b.id !== this.boardId);
        return (current ? [current, ...others] : others).slice(0, 8);
      }

      if (tab === 'workspace') {
        const ws = this.sbmSelectedWorkspace();
        return ws ? (ws.boards || []) : [];
      }

      return this.sbmAllBoards();
    },

    sbmBoardCount(tab) {
      return this.sbmUniqueBoards(this.sbmBoardsForTab(tab)).length;
    },

    sbmUniqueBoards(boards) {
      const seen = new Set();
      return boards.filter(board => {
        if (!board || seen.has(board.id)) return false;
        seen.add(board.id);
        return true;
      });
    },

    sbmCurrentWorkspace() {
      return this.allWorkspaces.find(ws => (ws.boards || []).some(b => b.id === this.boardId)) || null;
    },

    sbmSelectedWorkspace() {
      return this.allWorkspaces.find(ws => ws.id === this.switchBoardsModal.selectedWorkspace)
        || this.sbmCurrentWorkspace()
        || this.allWorkspaces[0]
        || null;
    },

    sbmWorkspaceForBoard(board) {
      if (!board) return null;
      return this.allWorkspaces.find(ws => (ws.boards || []).some(b => b.id === board.id)) || null;
    },

    sbmBoardWorkspaceName(board) {
      const ws = this.sbmWorkspaceForBoard(board);
      return ws ? ws.name : 'Workspace';
    },

    sbmWorkspaceInitials(name) {
      return String(name || 'WS')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map(part => part.charAt(0))
        .join('')
        .toUpperCase();
    },

    sbmBoardInitials(name) {
      return this.sbmWorkspaceInitials(name || 'BD');
    },

    sbmBoardPreviewStyle(board) {
      const value = board?.background_value || '#0f172a';
      if (board?.background_type === 'image') {
        const safeUrl = String(value).replace(/"/g, '\\"');
        return `background-image: linear-gradient(rgba(15,23,42,.12), rgba(15,23,42,.32)), url("${safeUrl}"); background-size: cover; background-position: center;`;
      }

      return `background: ${value};`;
    },

    sbmCoverStyle(board) {
      const type = board?.cover_type || board?.background_type || 'color';
      const value = board?.cover_value || board?.background_value || '#6366f1';
      if (type === 'image') {
        const safeUrl = String(value).replace(/"/g, '\\"');
        return `background-image: url("${safeUrl}"); background-size: cover; background-position: center;`;
      }
      return `background: ${value};`;
    },

    sbmCreateWorkspaceId() {
      const ws = this.sbmSelectedWorkspace();
      return ws ? ws.id : '';
    },

    sbmCreateWorkspaceName() {
      const ws = this.sbmSelectedWorkspace();
      return ws ? ws.name : 'this workspace';
    },

    switchToBoard(board) {
      if (!board?.slug) return;
      if (board.id === this.boardId) {
        this.closeSwitchBoardsModal();
        return;
      }
      window.location.href = `/boards/${board.slug}`;
    },

    sbmOpenBoardMembers(board) {
      if (board?.id === this.boardId) {
        this.closeSwitchBoardsModal();
        window.showToast?.('Use the Members button in the board header.');
        return;
      }
      this.switchToBoard(board);
    },

    sbmOpenBoardSettings(board) {
      if (board?.id === this.boardId) {
        this.closeSwitchBoardsModal();
        window.showToast?.('Open board settings from this board header.');
        return;
      }
      this.switchToBoard(board);
    },

    sbmOpenWorkspaceMembers() {
      this.closeSwitchBoardsModal();
      window.showToast?.('Workspace members are managed from the current board header.');
    },

    sbmOpenWorkspaceSettings() {
      this.closeSwitchBoardsModal();
      window.showToast?.('Board settings are available after opening a board.');
    },

    async sbmCopyBoardLink(board) {
      if (!board?.slug) return;
      const url = `${window.location.origin}/boards/${board.slug}`;
      try {
        await navigator.clipboard.writeText(url);
        window.showToast?.('Board link copied.');
      } catch (e) {
        window.showToast?.(url);
      }
    },

    // ── Comments ──────────────────────────────────────────────────────────────
    async submitComment() {
      const body = this.newComment.trim();
      if (!body || !this.activeCard) return;

      const res = await this.api(`/boards/cards/${this.activeCard.id}/comments`, 'POST', { body });
      if (res.comment) {
        if (res.card_moved) {
          window.showToast('Card moved by automation!');
          const newCard = res.card;
          if (newCard.board_id !== this.boardId) {
            this.lists.forEach(l => l.cards = l.cards.filter(c => c.id !== this.activeCard.id));
            this.closeCard();
            return;
          } else if (newCard.board_list_id !== this.activeCard.board_list_id) {
            this.lists.forEach(l => l.cards = l.cards.filter(c => c.id !== this.activeCard.id));
            const targetList = this.lists.find(l => l.id === newCard.board_list_id);
            if (targetList) targetList.cards.push(newCard);
            this.closeCard();
            return;
          }
        }

        if (!this.activeCard.comments) this.activeCard.comments = [];
        this.activeCard.comments.push(res.comment);
        this.newComment = '';

        this.lists.forEach(l => {
          const c = l.cards.find(x => x.id === this.activeCard.id);
          if (c) c.comment_count = (c.comment_count ?? 0) + 1;
        });

        window.showToast('Comment posted!');
        this.refreshCardActivities();
      }
    },

    async updateComment(commentId, body) {
      const content = String(body || '').trim();
      if (!this.activeCard || !content) return;

      const res = await this.api(`/boards/cards/${this.activeCard.id}/comments/${commentId}`, 'PATCH', { body: content });
      if (res.comment) {
        if (!this.activeCard.comments) this.activeCard.comments = [];
        this.activeCard.comments = this.activeCard.comments.map(comment =>
          comment.id === commentId ? { ...comment, ...res.comment } : comment
        );
        window.showToast('Comment updated.');
        this.refreshCardActivities();
      }
    },

    // ── Attachment Modal ──────────────────────────────────────────────────────

    openAttachmentModal(card) {
      if (!card) return;
      const am = this.attachmentModal;
      am.cardId         = card.id;
      am.tab            = 'file';
      am.dragOver       = false;
      am.uploading      = false;
      am.uploadProgress = 0;
      am.error          = '';
      am.linkUrl        = '';
      am.linkName       = '';
      am.open           = true;
    },

    closeAttachmentModal() {
      this.attachmentModal.open = false;
      this.attachmentModal.editingFileId = null;
    },

    amHandleDrop(event) {
      this.attachmentModal.dragOver = false;
      const file = event.dataTransfer.files[0];
      if (file) this.amDoUpload(file);
    },

    amUploadFile(event) {
      const file = event.target.files[0];
      if (file) this.amDoUpload(file);
      // Reset so same file can be re-selected
      event.target.value = '';
    },

    amDoUpload(file) {
      const am   = this.attachmentModal;
      const card = this.activeCard;
      if (!card) return;

      // Client-side size check (20 MB)
      if (file.size > 20 * 1024 * 1024) {
        am.error = `File too large: ${this.amFormatBytes(file.size)}. Maximum allowed is 20 MB.`;
        return;
      }

      // Client-side MIME pre-check
      const ALLOWED_PREFIXES = ['image/', 'video/', 'audio/', 'text/'];
      const ALLOWED_EXACT = [
        'application/pdf', 'application/msword', 'application/zip',
        'application/x-zip-compressed', 'application/x-rar-compressed',
        'application/x-7z-compressed', 'application/gzip',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel', 'application/vnd.ms-powerpoint',
      ];
      const BLOCKED = ['text/html', 'application/javascript', 'application/x-httpd-php'];
      if (BLOCKED.includes(file.type)) {
        am.error = `File type "${file.type}" is not allowed.`;
        return;
      }
      const allowed = ALLOWED_PREFIXES.some(p => file.type.startsWith(p))
                   || ALLOWED_EXACT.includes(file.type);
      if (!allowed) {
        am.error = `File type "${file.type || 'unknown'}" is not permitted.`;
        return;
      }

      am.error          = '';
      am.uploading      = true;
      am.uploadProgress = 0;

      const formData = new FormData();
      formData.append('file', file);

      const xhr = new XMLHttpRequest();
      xhr.open('POST', `/boards/cards/${card.id}/files`);
      xhr.setRequestHeader('X-CSRF-TOKEN', this.csrfToken);
      xhr.setRequestHeader('Accept', 'application/json');

      xhr.upload.addEventListener('progress', e => {
        if (e.lengthComputable) {
          am.uploadProgress = Math.round((e.loaded / e.total) * 100);
        }
      });

      xhr.addEventListener('load', () => {
        am.uploading = false;
        try {
          const data = JSON.parse(xhr.responseText);
          if (xhr.status === 201 && data.file) {
            if (!card.files) card.files = [];
            card.files.push(data.file);
            this.lists.forEach(l => {
              const c = l.cards.find(x => x.id === card.id);
              if (c) c.has_files = true;
            });
            window.showToast('File attached successfully! 📎');
            this.refreshCardActivities();
            this.closeAttachmentModal();
          } else {
            am.error = data.error || data.message || 'Upload failed. Please try again.';
          }
        } catch {
          am.error = 'Upload failed — invalid server response.';
        }
      });

      xhr.addEventListener('error', () => {
        am.uploading = false;
        am.error = 'Network error during upload. Please try again.';
      });

      xhr.send(formData);
    },

    async amSubmitLink() {
      const am   = this.attachmentModal;
      const card = this.activeCard;
      if (!am.linkUrl || !card) return;

      // Basic URL validation
      try { new URL(am.linkUrl); } catch {
        am.error = 'Please enter a valid URL (including https://).';
        return;
      }

      am.error     = '';
      am.uploading = true;
      const res = await this.api(`/boards/cards/${card.id}/files`, 'POST', {
        link_url:  am.linkUrl,
        link_name: am.linkName || am.linkUrl,
      });
      am.uploading = false;

      if (res.file) {
        if (!card.files) card.files = [];
        card.files.push(res.file);
        this.lists.forEach(l => {
          const c = l.cards.find(x => x.id === card.id);
          if (c) c.has_files = true;
        });
        window.showToast('Link attached successfully!');
        am.linkUrl  = '';
        am.linkName = '';
        this.openCard(card.id);
        this.closeAttachmentModal();
      } else {
        am.error = res.error || res.message || 'Failed to attach link.';
      }
    },

    async amEditAttachment(file) {
      if (!this.activeCard) return;
      const am = this.attachmentModal;
      // Toggle: if already editing this file, cancel
      if (am.editingFileId === file.id) {
        am.editingFileId = null;
        return;
      }
      am.editingFileId = file.id;
      am.editName = file.original_name;
      am.editUrl = file.path || file.url || '';
      am.editSaving = false;
    },

    async amSaveEdit(file) {
      if (!this.activeCard) return;
      const am = this.attachmentModal;
      am.editSaving = true;

      const payload = { original_name: am.editName.trim() };
      if (file.disk === 'url') {
        payload.link_url = am.editUrl.trim();
      }

      const fd = new FormData();
      fd.append('original_name', payload.original_name);
      if (payload.link_url) fd.append('link_url', payload.link_url);

      try {
        const resp = await fetch(`/boards/cards/${this.activeCard.id}/files/${file.id}/update`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
          body: fd,
        });
        const res = await resp.json();
        if (res.success) {
          const idx = this.activeCard.files.findIndex(x => x.id === file.id);
          if (idx !== -1) this.activeCard.files[idx] = res.file;
          am.editingFileId = null;
          window.showToast('Attachment updated successfully.');
        }
      } finally {
        am.editSaving = false;
      }
    },

    async amReplaceFile(file, event) {
      if (!this.activeCard) return;
      const newFile = event.target.files?.[0];
      if (!newFile) return;

      const am = this.attachmentModal;
      am.editSaving = true;

      const fd = new FormData();
      fd.append('file', newFile);

      try {
        const resp = await fetch(`/boards/cards/${this.activeCard.id}/files/${file.id}/update`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
          body: fd,
        });
        const res = await resp.json();
        if (res.success) {
          // Replace in array
          const idx = this.activeCard.files.findIndex(x => x.id === file.id);
          if (idx !== -1) {
            this.activeCard.files.splice(idx, 1, res.file);
          } else {
            this.activeCard.files.push(res.file);
          }
          am.editingFileId = null;
          window.showToast('File replaced.');
        }
      } finally {
        am.editSaving = false;
        event.target.value = '';
      }
    },

    async amDeleteAttachment(file) {
      if (!this.activeCard || !await window.confirmModal(`Remove "${file.original_name}"?`)) return;
      const res = await this.api(`/boards/cards/${this.activeCard.id}/files/${file.id}`, 'DELETE');
      if (res.success) {
        this.activeCard.files = this.activeCard.files.filter(x => x.id !== file.id);
        this.lists.forEach(l => {
          const c = l.cards.find(x => x.id === this.activeCard.id);
          if (c) c.has_files = this.activeCard.files.length > 0;
        });
        window.showToast('Attachment removed.');
        this.refreshCardActivities();
      }
    },

    amAutoFillName() {
      const am = this.attachmentModal;
      // Auto-populate display name from URL hostname if the field is empty
      if (!am.linkName && am.linkUrl.length > 8) {
        try {
          const u = new URL(am.linkUrl);
          am.linkName = u.hostname.replace('www.', '');
        } catch { /* ignore invalid URL */ }
      }
    },

    previewAttachment(file) {
      if (!file?.is_image) return;
      this.imagePreview.url = file.preview_url || file.url;
      this.imagePreview.title = file.original_name || 'Image preview';
      this.imagePreview.open = true;
    },

    openAvatarPreview(userOrUrl, fallbackTitle = 'Profile image') {
      let url = '';
      let title = fallbackTitle;

      if (typeof userOrUrl === 'string') {
        url = userOrUrl;
      } else if (userOrUrl && typeof userOrUrl === 'object') {
        url = this.avatarUrl(userOrUrl);
        title = userOrUrl.name || userOrUrl.user_name || userOrUrl.email || fallbackTitle;
      }

      if (!url) return;

      this.imagePreview.url = url;
      this.imagePreview.title = title;
      this.imagePreview.open = true;
    },

    closeImagePreview() {
      this.imagePreview.open = false;
      this.imagePreview.url = '';
      this.imagePreview.title = '';
    },

    openVideoPreview(file) {
      if (!file || !file.url) return;
      let embedUrl = file.url;
      // Convert standard drive view links to embedded preview links
      if (file.url.includes('drive.google.com')) {
        // Strip query params and replace view with preview
        const cleanUrl = file.url.split('?')[0];
        embedUrl = cleanUrl.replace('/view', '/preview');
      }
      this.videoPreview.embedUrl = embedUrl;
      this.videoPreview.url = file.url;
      this.videoPreview.title = file.original_name || 'Video Preview';
      this.videoPreview.open = true;
    },

    closeVideoPreview() {
      this.videoPreview.open = false;
      this.videoPreview.embedUrl = ''; // Clear iframe src to stop playback
      this.videoPreview.url = '';
      this.videoPreview.title = '';
    },

    // Return an emoji icon for a file based on its MIME or name
    amFileIcon(file) {
      const mime = (file.mime_type || '').toLowerCase();
      const name = (file.original_name || '').toLowerCase();
      if (mime === 'link') return '🔗';
      if (mime.startsWith('image/')) return '🖼️';
      if (mime === 'application/pdf') return '📄';
      if (mime.includes('word')  || name.endsWith('.doc') || name.endsWith('.docx')) return '📝';
      if (mime.includes('excel') || name.endsWith('.xls') || name.endsWith('.xlsx')) return '📊';
      if (mime.includes('power') || name.endsWith('.ppt') || name.endsWith('.pptx')) return '📰';
      if (mime.includes('zip')   || mime.includes('rar')  || mime.includes('7z')) return '🗄️';
      if (mime.startsWith('video/')) return '🎥';
      if (mime.startsWith('audio/')) return '🎵';
      if (mime.startsWith('text/'))  return '📝';
      return '📂';
    },

    amFormatBytes(bytes) {
      if (bytes < 1024)        return bytes + ' B';
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
      return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    },

    // ── Legacy stubs — kept for backward-compat (old markup may call these) ──
    async uploadAttachment(event) { this.amUploadFile(event); },
    async attachLink()            { this.openAttachmentModal(this.activeCard); },
    async editAttachment(file)    { this.amEditAttachment(file); },
    async deleteAttachment(file)  { this.amDeleteAttachment(file); },


    // ── Deletions ─────────────────────────────────────────────────────────────
    async archiveCard() {
      if (!this.activeCard || !await window.confirmModal({
        title: 'Archive card?',
        message: `Archive "<strong>${this.escapeHtml(this.activeCard.title)}</strong>"? You can restore it later from Archived items.`,
        confirmText: 'Archive card',
        tone: 'warning',
      })) return;
      await this.api(`/boards/cards/${this.activeCard.id}`, 'PATCH', { is_archived: true });

      this.lists.forEach(l => {
        l.cards = l.cards.filter(c => c.id !== this.activeCard.id);
      });
      window.showToast("Card archived.");
      this.closeCard();
    },

    async deleteCard() {
      if (!this.activeCard || !await window.confirmModal({
        title: 'Delete card permanently?',
        message: `Delete "<strong>${this.escapeHtml(this.activeCard.title)}</strong>"? This cannot be undone.`,
        confirmText: 'Delete card',
        tone: 'danger',
      })) return;
      await this.api(`/boards/cards/${this.activeCard.id}`, 'DELETE');

      this.lists.forEach(l => {
        l.cards = l.cards.filter(c => c.id !== this.activeCard.id);
      });
      window.showToast("Card permanently deleted.");
      this.closeCard();
    },

    async moveCardDirect(listId) {
      if (!this.activeCard) return;
      
      const targetList = this.lists.find(l => l.id === listId);
      if (!targetList) return;
      
      const res = await this.api(`/boards/cards/${this.activeCard.id}/move`, 'POST', {
        board_list_id: listId
      });
      
      if (res.card) {
        this.lists.forEach(l => {
          l.cards = l.cards.filter(c => c.id !== this.activeCard.id);
        });
        targetList.cards.push(this.activeCard);
        this.activeCard.board_list_id = listId;
        this.activeCard.board_list_name = targetList.name;
        window.showToast(`Moved card to "${targetList.name}"`);
        this.refreshCardActivities();
      }
    },

    async deleteComment(commentId) {
      if (!this.activeCard || !await window.confirmModal("Delete this comment permanently?")) return;
      const res = await this.api(`/boards/cards/${this.activeCard.id}/comments/${commentId}`, 'DELETE');
      if (res.success) {
        this.activeCard.comments = this.activeCard.comments.filter(x => x.id !== commentId);
        this.lists.forEach(l => {
          const c = l.cards.find(x => x.id === this.activeCard.id);
          if (c) c.comment_count = Math.max(0, (c.comment_count ?? 1) - 1);
        });
        window.showToast('Comment deleted.');
        this.refreshCardActivities();
      }
    },

    insertMarkdown(tag) {
      const textarea = document.getElementById('card-desc-editor');
      if (!textarea) return;
      
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const text = textarea.value;
      const selected = text.substring(start, end);
      
      let replacement = '';
      switch (tag) {
        case 'bold':
          replacement = `**${selected || 'bold text'}**`;
          break;
        case 'italic':
          replacement = `*${selected || 'italic text'}*`;
          break;
        case 'heading':
          replacement = `\n### ${selected || 'Heading'}\n`;
          break;
        case 'code':
          replacement = `\`${selected || 'code'}\``;
          break;
        case 'list':
          replacement = `\n- ${selected || 'List item'}\n`;
          break;
      }
      
      textarea.value = text.substring(0, start) + replacement + text.substring(end);
      this.activeCard.description = textarea.value;
      
      textarea.focus();
      textarea.setSelectionRange(start + replacement.length, start + replacement.length);
    },

    parseMarkdown(text) {
      if (!text) return '<p class="text-slate-400 italic">No description provided. Click here to add one...</p>';

      // If it's already HTML (from Quill WYSIWYG), return it safely
      const trimmed = text.trim();
      if (/<[a-z][\s\S]*>/i.test(trimmed)) {
        return text;
      }

      // If it looks like an activity log (short, no newlines), do a lightweight parse
      if (!text.includes('\n') && text.length < 500) {
        return ' ' + text
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/\*\*(.*?)\*\*/g, '<strong class="font-semibold text-slate-800">$1</strong>')
          .replace(/`(.*?)`/g, '<code class="bg-slate-100 text-rose-500 rounded px-1 text-[10px] font-mono">$1</code>');
      }
      
      let html = text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
      
      html = html.replace(/^### (.*$)/gim, '<h3 class="text-sm font-bold text-slate-800 mt-3 mb-1">$1</h3>');
      html = html.replace(/^## (.*$)/gim, '<h2 class="text-sm font-bold text-slate-800 mt-4 mb-2">$1</h2>');
      html = html.replace(/^# (.*$)/gim, '<h1 class="text-base font-bold text-slate-900 mt-4 mb-2">$1</h1>');
      
      html = html.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-900">$1</strong>');
      html = html.replace(/\*(.*?)\*/g, '<em class="italic">$1</em>');
      html = html.replace(/`(.*?)`/g, '<code class="bg-slate-100 text-rose-500 rounded px-1 py-0.5 text-sm font-mono">$1</code>');
      
      html = html.replace(/^\s*-\s+(.*$)/gim, '<li class="ml-4 list-disc text-slate-600 text-sm">$1</li>');
      html = html.replace(/\n/g, '<br>');
      
      return html;
    },

    // Render comment body: supports ![alt](url) images and regular text
    parseCommentBody(text) {
      if (!text) return '';
      // Escape HTML first
      let html = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
        
      // Bold rendering (for system comments like **file.png**)
      html = html.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-900">$1</strong>');
      html = html.replace(/\*(.*?)\*/g, '<em class="italic">$1</em>');
      
      // Render markdown images ![alt](url) as clickable thumbnails
      html = html.replace(/!\[([^\]]*?)\]\(([^)]+?)\)/g, (_, alt, url) => {
        const safeUrl = url.replace(/"/g, '%22');
        return `<a href="${safeUrl}" target="_blank" rel="noopener" class="block mt-1">`
          + `<img src="${safeUrl}" alt="${alt || 'screenshot'}" class="max-w-full max-h-48 rounded-xl border border-slate-200 shadow-sm object-contain cursor-zoom-in hover:shadow-md transition-shadow">`
          + `</a>`;
      });
      // Render [text](url) links
      html = html.replace(/\[([^\]]+?)\]\(([^)]+?)\)/g, (_, label, url) => {
        const safeUrl = url.replace(/"/g, '%22');
        return `<a href="${safeUrl}" target="_blank" rel="noopener" class="text-indigo-600 underline hover:text-indigo-800">${label}</a>`;
      });
      // Newlines
      html = html.replace(/\n/g, '<br>');
      return html;
    },
    // ── Paste Screenshot ──────────────────────────────────────────────────────
    compressImage(blob, maxW, maxH, quality) {
      return new Promise(resolve => {
        const img = new Image();
        const url = URL.createObjectURL(blob);
        img.onload = () => {
          let w = img.width, h = img.height;
          if (w > maxW) { h = Math.round(h * maxW / w); w = maxW; }
          if (h > maxH) { w = Math.round(w * maxH / h); h = maxH; }
          const canvas = document.createElement('canvas');
          canvas.width = w; canvas.height = h;
          canvas.getContext('2d').drawImage(img, 0, 0, w, h);
          URL.revokeObjectURL(url);
          resolve(canvas.toDataURL('image/jpeg', quality));
        };
        img.src = url;
      });
    },

    handleCommentClick(e) {
      if (e.target.tagName === 'IMG' && e.target.closest('a')) {
        e.preventDefault();
        this.imagePreview.url = e.target.src;
        this.imagePreview.title = e.target.alt || 'Attached Image';
        this.imagePreview.open = true;
      }
    },

    async handlePaste(event) {
      const items = event.clipboardData?.items;
      if (!items) return;
      for (let i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image') !== -1) {
          event.preventDefault();
          const blob = items[i].getAsFile();
          // Compress to max 1200×900 at 80% quality for inline storage
          this.pastedImage = await this.compressImage(blob, 1200, 900, 0.80);
          break;
        }
      }
    },

    async sendScreenshot() {
      if (!this.pastedImage || !this.activeCard) return;
      this.sendingScreenshot = true;
      try {
        const textPrefix = this.newComment.trim() ? this.newComment.trim() + '\n\n' : '';
        const body = textPrefix + '![screenshot](' + this.pastedImage + ')';
        const res = await this.api(`/boards/cards/${this.activeCard.id}/comments`, 'POST', { body });
        if (res.comment) {
          if (res.card_moved) {
            window.showToast('Card moved by automation!');
            const newCard = res.card;
            if (newCard.board_id !== this.boardId) {
              this.lists.forEach(l => l.cards = l.cards.filter(c => c.id !== this.activeCard.id));
              this.closeCard();
              return;
            } else if (newCard.board_list_id !== this.activeCard.board_list_id) {
              this.lists.forEach(l => l.cards = l.cards.filter(c => c.id !== this.activeCard.id));
              const targetList = this.lists.find(l => l.id === newCard.board_list_id);
              if (targetList) targetList.cards.push(newCard);
              this.closeCard();
              return;
            }
          }

          if (!this.activeCard.comments) this.activeCard.comments = [];
          this.activeCard.comments.push(res.comment);
          this.newComment = '';
          this.pastedImage = null;
          
          this.lists.forEach(l => {
            const c = l.cards.find(x => x.id === this.activeCard.id);
            if (c) c.comment_count = (c.comment_count ?? 0) + 1;
          });

          window.showToast('Screenshot shared in comments! 📸');
          this.refreshCardActivities();
        }
      } catch(e) {
        window.showToast('Failed to send screenshot', 'error');
      } finally {
        this.sendingScreenshot = false;
      }
    },

    timeAgo(dateStr) {
      if (!dateStr) return 'just now';
      const date = new Date(dateStr);
      return date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true });
    },

    // ── Board members toggles ────────────────────────────────────────────────
    async addBoardMember(userId, element) {
      if (element) element.disabled = true;
      const res = await this.api(`/boards/${this.boardSlug}/members`, 'POST', { user_id: userId });
      if (element) element.disabled = false;
      if (res.message) {
        window.showToast(res.message);
        setTimeout(() => window.location.reload(), 800);
      } else if (res.error) {
        window.showToast(res.error, 'error');
      }
    },

    async removeBoardMember(userId, element) {
      if (!await window.confirmModal('Are you sure you want to remove this member from the board?')) return;
      if (element) element.disabled = true;
      const res = await this.api(`/boards/${this.boardSlug}/members/${userId}`, 'DELETE');
      if (element) element.disabled = false;
      if (res.message) {
        window.showToast(res.message);
        setTimeout(() => window.location.reload(), 800);
      } else if (res.error) {
        window.showToast(res.error, 'error');
      }
    },

    bindRealtimeBoardUpdates() {
      if (this.realtimeBound) return;
      this.realtimeBound = true;

      const triggerRefresh = (reason = 'push') => this.scheduleBoardSnapshot(reason);
      this.connectBoardRealtimeChannel();

      window.addEventListener('kiuq:realtime-notification', (event) => {
        const payload = event.detail?.data || event.detail || {};
        if (String(payload.board_slug || '') !== String(this.boardSlug)) return;
        triggerRefresh('push');
      });

      window.addEventListener('focus', () => triggerRefresh('focus'));
      document.addEventListener('visibilitychange', () => {
        if (!document.hidden) triggerRefresh('visible');
      });

      this.realtimePollTimer = setInterval(() => {
        if (!document.hidden) triggerRefresh('poll');
      }, 5000);
    },

    connectBoardRealtimeChannel() {
      if (this.realtimeChannel || !this.boardId) return;

      const pusher = window.kiuqGetPusherClient?.();
      if (!pusher) {
        if (this.realtimeConnectAttempts < 20) {
          this.realtimeConnectAttempts++;
          setTimeout(() => this.connectBoardRealtimeChannel(), 500);
        }
        return;
      }

      this.realtimeChannel = pusher.subscribe(`private-boards.${this.boardId}`);
      const handleBoardUpdate = (payload = {}) => {
        if (payload.board_id && parseInt(payload.board_id, 10) !== parseInt(this.boardId, 10)) return;
        this.scheduleBoardSnapshot('push');
      };

      this.realtimeChannel.bind('board.updated', handleBoardUpdate);
      this.realtimeChannel.bind('.board.updated', handleBoardUpdate);
      this.realtimeChannel.bind('App\\Events\\BoardUpdated', handleBoardUpdate);
      this.realtimeChannel.bind_global((eventName, payload) => {
        if (String(eventName).includes('board.updated') || String(eventName).includes('BoardUpdated')) {
          handleBoardUpdate(payload);
        }
      });
    },

    scheduleBoardSnapshot(reason = 'push') {
      if (this.realtimeDragging) return;

      clearTimeout(this.realtimeTimer);
      const delay = reason === 'push' ? 200 : 650;
      this.realtimeTimer = setTimeout(() => this.refreshBoardSnapshot(reason), delay);
    },

    async refreshBoardSnapshot(reason = 'push') {
      if (this.realtimeInFlight || this.realtimeDragging) return;

      const now = Date.now();
      if (reason !== 'push' && now - this.lastSnapshotAt < 2500) return;

      this.realtimeInFlight = true;

      try {
        const payload = await this.api(`/boards/${this.boardSlug}/snapshot`, 'GET', null, { silentErrors: true });
        if (!payload || payload._ok === false || !Array.isArray(payload.lists)) return;

        this.applyBoardSnapshot(payload);
        this.lastSnapshotAt = Date.now();
      } finally {
        this.realtimeInFlight = false;
      }
    },

    applyBoardSnapshot(payload) {
      const activeCardId = this.activeCard?.id ? parseInt(this.activeCard.id, 10) : null;

      this.board = payload.board || this.board;
      this.boardId = payload.boardId || this.boardId;
      this.boardSlug = payload.boardSlug || this.boardSlug;
      this.currentUser = payload.currentUser || this.currentUser;
      this.lists = payload.lists || this.lists;
      this.labels = payload.labels || this.labels;
      this.allBoardMembers = payload.boardMembers || this.allBoardMembers;
      this.allWorkspaceMembers = payload.workspaceMembers || this.allWorkspaceMembers;
      this.allWorkspaces = payload.allWorkspaces || this.allWorkspaces;

      this.loadBoardMembers();

      this.$nextTick(() => {
        this.initSortable();
      });

      if (activeCardId) {
        const stillExists = this.lists.some(list => (list.cards || []).some(card => parseInt(card.id, 10) === activeCardId));
        if (stillExists) {
          this.refreshActiveCard();
        } else {
          this.closeCard();
        }
      }
    },

    // ── API request helper ───────────────────────────────────────────────────
    async api(url, method = 'GET', data = null, options = {}) {
      const opts = {
        method,
        headers: {
          'Content-Type': 'application/json',
          'Accept':       'application/json',
          'X-CSRF-TOKEN': this.csrfToken,
        },
      };
      if (data && method !== 'GET') opts.body = JSON.stringify(data);

      try {
        const res = await fetch(url, opts);
        const payload = await res.json().catch(() => ({}));
        payload._ok = res.ok;
        payload._status = res.status;
        if (!res.ok && !options.silentErrors) {
          const firstError = payload.errors
            ? Object.values(payload.errors).flat()[0]
            : (payload.error || payload.message || 'Request failed.');
          window.showToast(firstError, 'error');
        }
        return payload;
      } catch (err) {
        console.error('Board API error:', err);
        return {};
      }
    },
  };
}
