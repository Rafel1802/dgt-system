/**
 * trello-board.js
 * Premium Alpine.js component that powers the digital team Trello-style board view.
 * Handles: SortableJS drag-and-drop, dynamic board switcher, multi-filters,
 * card detail modal, checklists, comments, attachments upload, labels, members,
 * star favorite toggles, and sliding activity drawers.
 */

// Safe guard flag to prevent live production board from flickering/auto-refreshing
const ENABLE_BOARD_REALTIME_SYNC = true;

window.trelloBoard = function(config) {
  return {
    // ── State ────────────────────────────────────────────────────────────────
    board:      config.board || { id: config.boardId, name: '', slug: config.boardSlug, is_starred: false },
    boardId:    config.boardId,
    boardSlug:  config.boardSlug,
    baseRoute:  config.baseRoute || 'boards',
    csrfToken:  config.csrfToken,
    currentUserId: config.currentUserId,
    currentUser: config.currentUser || { id: config.currentUserId, can_move_any_card: false, can_manage_blocked_cards: false, is_digital_team: false },
    lists:      config.lists,
    labels:     config.labels || [],
    allBoardLabels: (config.labels && config.labels.length > 0) ? config.labels : [],
    smmClasses: config.smmClasses || [],
    smmTeams:   config.smmTeams   || ['Graphic Team', 'Video Team', 'Listing Team', 'Content Writing Team', 'QC Team'],
    smmContentTypes: config.smmContentTypes || [
      { name: 'Long Landscape', bg: '#dcfce7', text: '#166534', border: '#bbf7d0', dot: '#22c55e' },
      { name: 'Short Reel',     bg: '#ffd7d7', text: '#991b1b', border: '#fecaca', dot: '#ef4444' },
      { name: 'Poster Design',  bg: '#ebd9fc', text: '#6b21a8', border: '#e9d5ff', dot: '#a855f7' },
      { name: 'Share Blog',     bg: '#1e6f82', text: '#ffffff', border: '#155e75', dot: '#06b6d4' },
      { name: 'Urgent Task',    bg: '#6f3710', text: '#ffffff', border: '#572b0d', dot: '#ea580c' },
      { name: 'Press Release',  bg: '#136e43', text: '#ffffff', border: '#0e5333', dot: '#10b981' },
    ],

    // All members for filtering (set from PHP injected config)
    allBoardMembers:     config.boardMembers     || [],
    allWorkspaceMembers: config.workspaceMembers || [],
    allSystemMembers:    config.allSystemMembers || [],
    allWorkspaces:       (config.allWorkspaces || []).filter(ws => ws.boards && ws.boards.length > 0),

    get availableLabels() {
      const canonical = [
        { id: 'Graphic', name: 'Graphic', color: '#f43f5e' },
        { id: 'Video', name: 'Video', color: '#ef4444' },
        { id: 'SMM', name: 'SMM', color: '#50C878' },
        { id: 'Listing', name: 'Listing', color: '#f59e0b' },
        { id: 'Content', name: 'Content', color: '#0ea5e9' },
      ];
      const list = [...(this.labels || this.allBoardLabels || [])];
      canonical.forEach(c => {
        const found = list.find(l => (l.name || '').toLowerCase() === c.name.toLowerCase());
        if (!found) {
          list.push(c);
        } else if (!found.color) {
          found.color = c.color;
        }
      });
      return list;
    },

    get exportAvailableMembers() {
      const map = new Map();
      (this.allBoardMembers || []).forEach(m => { if (m && m.id) map.set(String(m.id), m); });
      (this.allWorkspaceMembers || []).forEach(m => { if (m && m.id && !map.has(String(m.id))) map.set(String(m.id), m); });
      (this.allSystemMembers || []).forEach(m => { if (m && m.id && !map.has(String(m.id))) map.set(String(m.id), m); });
      return Array.from(map.values());
    },

    get filterAvailableMembers() {
      const map = new Map();
      (this.allBoardMembers || []).forEach(m => { if (m && m.id) map.set(String(m.id), m); });
      (this.allWorkspaceMembers || []).forEach(m => { if (m && m.id && !map.has(String(m.id))) map.set(String(m.id), m); });
      (this.allSystemMembers || []).forEach(m => { if (m && m.id && !map.has(String(m.id))) map.set(String(m.id), m); });
      return Array.from(map.values());
    },

    get selectedFilterAssignee() {
      const id = this.filterAssignee;
      if (!id || id === 'all') return null;
      return (this.filterAvailableMembers || []).find(m => String(m.id) === String(id)) || null;
    },

    get selectedFilterAssignBy() {
      const id = this.filterAssignBy;
      if (!id || id === 'all') return null;
      return (this.filterAvailableMembers || []).find(m => String(m.id) === String(id)) || null;
    },

    get filteredFilterAssignees() {
      const q = (this.filterAssigneeSearch || '').toLowerCase().trim();
      const list = this.filterAvailableMembers || [];
      if (!q) return list;
      return list.filter(m => (m.name || '').toLowerCase().includes(q) || (m.email || '').toLowerCase().includes(q) || (m.username || '').toLowerCase().includes(q));
    },

    get filteredFilterAssignBy() {
      const q = (this.filterAssignBySearch || '').toLowerCase().trim();
      const list = this.filterAvailableMembers || [];
      if (!q) return list;
      return list.filter(m => (m.name || '').toLowerCase().includes(q) || (m.email || '').toLowerCase().includes(q) || (m.username || '').toLowerCase().includes(q));
    },

    get selectedExportMember() {
      const id = this.exportModal?.memberId;
      if (!id || id === 'all') return null;
      return (this.exportAvailableMembers || []).find(m => String(m.id) === String(id)) || null;
    },

    get selectedExportAssignBy() {
      const id = this.exportModal?.assignById;
      if (!id || id === 'all') return null;
      return (this.exportAvailableMembers || []).find(m => String(m.id) === String(id)) || null;
    },

    get filteredExportAssignees() {
      const q = (this.exportModal?.assigneeSearch || '').toLowerCase().trim();
      const list = this.exportAvailableMembers || [];
      if (!q) return list;
      return list.filter(m => (m.name || '').toLowerCase().includes(q) || (m.email || '').toLowerCase().includes(q) || (m.username || '').toLowerCase().includes(q));
    },

    get filteredExportAssignBy() {
      const q = (this.exportModal?.assignBySearch || '').toLowerCase().trim();
      const list = this.exportAvailableMembers || [];
      if (!q) return list;
      return list.filter(m => (m.name || '').toLowerCase().includes(q) || (m.email || '').toLowerCase().includes(q) || (m.username || '').toLowerCase().includes(q));
    },

    toggleExportLabel(label) {
      const em = this.exportModal;
      if (!Array.isArray(em.labelIds)) em.labelIds = [];
      const val = label.id || label.name;
      const idx = em.labelIds.findIndex(item => String(item).toLowerCase() === String(val).toLowerCase() || (label.name && String(item).toLowerCase() === String(label.name).toLowerCase()));
      if (idx > -1) {
        em.labelIds.splice(idx, 1);
      } else {
        em.labelIds.push(val);
      }
    },

    isExportLabelSelected(label) {
      const em = this.exportModal;
      if (!Array.isArray(em.labelIds) || em.labelIds.length === 0) return false;
      const val = label.id || label.name;
      return em.labelIds.some(item => String(item).toLowerCase() === String(val).toLowerCase() || (label.name && String(item).toLowerCase() === String(label.name).toLowerCase()));
    },

    selectAllExportLabels() {
      const em = this.exportModal;
      em.labelIds = (this.availableLabels || []).map(l => l.id || l.name);
    },

    clearExportLabels() {
      const em = this.exportModal;
      em.labelIds = [];
    },

    getMemberById(id) {
      if (!id || id === 'all') return null;
      return (this.exportAvailableMembers || []).find(m => String(m.id) === String(id)) || null;
    },

    // Filters & Zoom
    zoomLevel: parseInt(localStorage.getItem('boardZoomLevel')) || 100,
    searchQuery: '',
    filterPriority: '',
    filterAssignee: '',
    filterAssignBy: '',
    filterLabel: '',
    filterStatus: '',
    filterDateFrom: '',
    filterDateTo: '',
    filterTeamLabel: '',
    filterSmmClass: '',
    filterCluster: '',
    filterContentPublicDateFrom: '',
    filterContentPublicDateTo: '',
    filterPublicDate: '',
    filterTeam: '',
    filterCategory: '',
    filtersOpen: false,
    filterOpenAssignBy: false,
    filterAssignBySearch: '',
    filterOpenAssignee: false,
    filterAssigneeSearch: '',
    searchOpen: false,
    currentTheme: (typeof document !== 'undefined' ? (document.documentElement.getAttribute('data-theme') || (document.documentElement.classList.contains('dark') ? 'dark' : 'light')) : 'light'),
    boardMembers: [], // unique list for filter dropdown (populated by loadBoardMembers)

    // Activity drawer
    activityOpen: false,
    activities: [],

    // Checklist Item Modal
    checklistItemModal: {
      open: false,
      mode: 'add', // 'add' or 'edit'
      checklist: null,
      item: null,
      title: '',
      assignedUserIds: [],
      manuallySelected: false,
      detectedCategory: null,
      detectedMembers: [],
    },

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
      backgroundColors: ['#ffffff', '#2F68ED', '#0ea5e9', '#6366f1', '#14b8a6', '#22c55e', '#f59e0b', '#ef4444', '#0f172a'],
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
      trashLoading: false,
      trashItems: [],
      trashTab: 'cards',
      selectedTrashItems: [],
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
      assignById: 'all',
      labelId: 'all',
      labelIds: [],
      openAssigneeDropdown: false,
      assigneeSearch: '',
      openAssignByDropdown: false,
      assignBySearch: '',
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
      worksheetName: '',
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

    // Bulk selection state
    isSelectMode: false,
    selectedCards: [],
    openBulkComment: false,
    bulkCommentSubmitting: false,
    bulkDeleting: false,

    // Attachment modal
    attachmentModal: {
      open:           false,
      tab:            'file',    // 'file' | 'link'
      cardId:         null,
      dragOver:       false,
      uploading:      false,
      uploadProgress: 0,
      uploadCount:    0,
      uploadStatusText: '',
      folderName:     '',
      pendingFiles:   [],
      pendingRelativePaths: [],
      pendingFolderName: '',
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
    newCardTeam:      null,
    newCardAssignedTeam: null,

    // Card modal - pre-initialize synchronously if ?card= is in query string or autoOpenCardId is passed
    activeCard: (function() {
      try {
        const urlParams = new URLSearchParams(window.location.search);
        const targetCardId = urlParams.get('card') || config.autoOpenCardId;
        if (!targetCardId) return null;
        for (const l of (config.lists || [])) {
          const c = l.cards?.find(card => card.id == targetCardId);
          if (c) return JSON.parse(JSON.stringify(c));
        }
      } catch (_) {}
      return null;
    })(),
    cardLoading:       false,
    sendingScreenshot: false,
    pastedImage:       null,
    pastedImages:      [],
    newComment:        '',
    mentionState: {
      show: false,
      query: '',
      members: [],
      selectedIndex: 0,
      startPos: -1,
    },
    isEditingDesc:     false,
    imagePreview: {
      open: false,
      url: '',
      title: '',
      images: [],
      currentIndex: 0,
    },
    folderViewer: {
      open: false,
      folderName: '',
      files: [],
      filter: 'all',
    },
    groupFolderModal: {
      open: false,
      folderName: 'Photos',
      selectedFileIds: [],
      submitting: false,
      error: '',
    },
    videoPreview: {
      open: false,
      url: '',
      embedUrl: '',
      title: '',
    },
    canvaPreview: {
      open: false,
      loading: false,
      url: '',
      embedUrl: '',
      title: '',
      scale: 1,
      panX: 0,
      panY: 0,
      isDragging: false,
      startX: 0,
      startY: 0,
      dragMoved: false,
      currentPage: 1,
      totalPages: 16,
      isFullscreen: false,
    },
    googleDocsPreview: {
      open: false,
      url: '',
      embedUrl: '',
      title: '',
      type: 'doc', // 'doc' | 'sheet' | 'slide' | 'form'
      loading: false,
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
    isMacPlatform: false,

    checkIsMacApp() {
      return (typeof document !== 'undefined' && document.documentElement && document.documentElement.classList.contains('dgt-macos-app')) ||
             window.__dgtOfficialAppReady === true ||
             window.isDgtDesktopApp === true ||
             window.__dgtMacApp === true ||
             (typeof navigator !== 'undefined' && (navigator.userAgent.includes('DGTSystemMacOSApp') || navigator.userAgent.includes('dgt-macos')));
    },

    isMacApp() {
      return this.isMacPlatform || this.checkIsMacApp();
    },

    // ── Init ─────────────────────────────────────────────────────────────────
    init() {
      if (typeof document !== 'undefined') {
        document.documentElement.classList.add('is-board-page');
        document.body.classList.add('is-board-page');
        document.addEventListener('turbo:before-visit', () => {
          document.documentElement.classList.remove('is-board-page');
          document.body.classList.remove('is-board-page');
        }, { once: true });
      }

      this.currentTheme = (typeof document !== 'undefined' ? (document.documentElement.getAttribute('data-theme') || (document.documentElement.classList.contains('dark') ? 'dark' : 'light')) : 'light');
      window.addEventListener('theme-changed', (e) => {
        this.currentTheme = e.detail?.dataTheme || (e.detail?.theme === 'dark' ? (e.detail?.neon ? 'neon' : 'dark') : 'light');
      });
      if (typeof MutationObserver !== 'undefined' && document.documentElement) {
        const themeObserver = new MutationObserver(() => {
          this.currentTheme = document.documentElement.getAttribute('data-theme') || (document.documentElement.classList.contains('dark') ? 'dark' : 'light');
        });
        themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'class'] });
      }

      this.isMacPlatform = this.checkIsMacApp();
      window.addEventListener('dgt-macos-app-ready', () => {
        this.isMacPlatform = true;
      });
      if (typeof MutationObserver !== 'undefined' && document.documentElement) {
        const macObserver = new MutationObserver(() => {
          if (document.documentElement.classList.contains('dgt-macos-app')) {
            this.isMacPlatform = true;
          }
        });
        macObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
      }

      // Prompt desktop notifications permission on mount
      if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
      }

      // Auto-set team filter:
      // - Workflow boards: strictly locked to board's team (Team A or B)
      // - Normal Planning boards: Team A and Team B members/leads see ONLY their team (Team A or B) unless they can filter all teams
      // - Users who can filter all teams (dara, kim, somalika, admin-digital, supervisor, boss): see BOTH teams by default (filterTeam = '')
      const canFilterAll = this.canFilterAllTeams();
      if (this.isWorkflowBoard()) {
        const bTeam = this.getBoardTeam();
        if (bTeam) {
          this.filterTeam = bTeam;
        }
      } else if (this.isNormalPlanningBoard() && !canFilterAll && this.currentUser?.team) {
        this.filterTeam = this.currentUser.team;
      } else {
        this.filterTeam = '';
      }

      // Close context menu and preview modals on ESC, handle image gallery arrow keys
      window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          if (this.canvaPreview && this.canvaPreview.open) {
            e.preventDefault();
            e.stopPropagation();
            this.closeCanvaPreview();
            return;
          }
          if (this.videoPreview && this.videoPreview.open) {
            e.preventDefault();
            e.stopPropagation();
            this.closeVideoPreview();
            return;
          }
          if (this.imagePreview && this.imagePreview.open) {
            e.preventDefault();
            e.stopPropagation();
            this.closeImagePreview();
            return;
          }
          if (this.folderViewer && this.folderViewer.open) {
            e.preventDefault();
            e.stopPropagation();
            this.closeFolderViewer();
            return;
          }
          this.closeCtxMenu();
        } else if (this.imagePreview && this.imagePreview.open) {
          if (e.key === 'ArrowLeft') {
            this.previewPrevImage();
          } else if (e.key === 'ArrowRight') {
            this.previewNextImage();
          }
        }
      }, true);
      document.addEventListener('click', (e) => {
        const menu = document.getElementById('card-ctx-menu');
        if (menu && !menu.contains(e.target)) this.closeCtxMenu();
      });

      // Load unique assignees dynamically from cards
      this.loadBoardMembers();

      this.boardMenu.watched = this.board.is_watching ?? true;

      // Initialize SortableJS drag-and-drop
      this.initSortable();
      
      this.bindRealtimeBoardUpdates();

      // Listen for custom event to open a card without reloading the page
      window.addEventListener('kiuq:open-card', (e) => {
        if (e.detail && e.detail.cardId) {
          this.openCard(e.detail.cardId);
        }
      });

      this.$watch('switchBoardsModal.search', () => this.updateSbmFilteredBoards());
      this.$watch('switchBoardsModal.tab', () => this.updateSbmFilteredBoards());
      this.$watch('switchBoardsModal.selectedWorkspace', () => this.updateSbmFilteredBoards());

      // Reset horizontal scroll cleanly without layout thrashing
      const wrap = document.getElementById('board-wrap');
      if (wrap) wrap.scrollLeft = 0;

      // Auto-open card if passed in query param
      const urlParams = new URLSearchParams(window.location.search);
      const cardId = urlParams.get('card') || config.autoOpenCardId;
      if (cardId) {
        this.openCard(cardId);
      }
    },

    setZoom(level) {
      this.zoomLevel = level;
      localStorage.setItem('boardZoomLevel', level);
    },

    zoomIn() {
      const levels = [50, 67, 75, 85, 100, 115, 125, 150];
      const next = levels.find(l => l > this.zoomLevel);
      if (next) this.setZoom(next);
    },

    zoomOut() {
      const levels = [50, 67, 75, 85, 100, 115, 125, 150];
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

    getListLead(list) {
      if (list?.lead) return list.lead;
      const name = String(list?.name || '').toLowerCase();
      const boardName = String(this.board?.name || '').toLowerCase();
      const isWorkflow = boardName.includes('workflow') || this.board?.type === 'workflow';

      if (!isWorkflow && !name.includes('team a') && !name.includes('team b') && !name.includes('digital department')) {
        return null;
      }

      if ((name.includes('production team a') || name.includes('team a') || name.includes('dara') || name.includes('qc')) && !name.includes('team b')) {
        const u = this.allSystemMembers?.find(m => m.id === 12 || (m.name && m.name.toLowerCase().includes('dara')));
        return {
          id: u?.id || 12,
          name: 'Mr. Dara',
          display_name: 'Mr. Dara',
          full_name: u?.name || 'Mr. Dara (QC)',
          avatar: u?.avatar || this.avatarUrl(u),
          initials: 'MD',
          avatar_color: u?.avatar_color || '#334155',
          role: 'Team A QC / Lead'
        };
      }

      if ((name.includes('production team b') || name.includes('team b') || name.includes('kim') || name.includes('head review')) && !name.includes('team a')) {
        const u = this.allSystemMembers?.find(m => m.id === 13 || (m.name && m.name.toLowerCase().includes('kim')));
        return {
          id: u?.id || 13,
          name: 'Mr. Kim',
          display_name: 'Mr. Kim',
          full_name: u?.name || 'Mr. KimOun (Head)',
          avatar: u?.avatar || this.avatarUrl(u),
          initials: 'MK',
          avatar_color: u?.avatar_color || '#0369a1',
          role: 'Team B Head'
        };
      }

      if (name.includes('digital department') || name.includes('supervisor') || name.includes('somalika')) {
        const u = this.allSystemMembers?.find(m => m.id === 2 || (m.name && (m.name.toLowerCase().includes('somalika') || m.name.toLowerCase().includes('supervisor'))));
        return {
          id: u?.id || 2,
          name: 'Supervisor',
          display_name: 'Supervisor',
          full_name: u?.name || 'Ms. Somalika (Supervisor)',
          avatar: u?.avatar || this.avatarUrl(u),
          initials: 'MS',
          avatar_color: u?.avatar_color || '#0f766e',
          role: 'Digital Supervisor'
        };
      }

      return null;
    },

    isWorkflowBoard() {
      const name = String(this.board?.name || '').toLowerCase();
      return (name.includes('workflow') || this.board?.type === 'workflow') && !name.includes('planning');
    },

    getBoardTeam() {
      const name = String(this.board?.name || '').toLowerCase();
      if (name.includes('team a') || name.includes('teama') || name.includes('team-a')) return 'A';
      if (name.includes('team b') || name.includes('teamb') || name.includes('team-b')) return 'B';
      return null;
    },

    isCardBothTeams(card) {
      if (!card) return false;
      const t = (card.team || '').toUpperCase().trim();
      if (t === 'BOTH' || t === 'ALL' || t === 'A,B' || t === 'A, B' || t === 'A&B' || t === 'A & B' || t === 'A+B' || (t.includes('A') && t.includes('B'))) {
        return true;
      }
      if (card.labels && Array.isArray(card.labels)) {
        if (card.labels.some(l => /team\s*a\s*(&|\+|and)\s*(team\s*)?b\b/i.test(l.name || ''))) return true;
        const hasA = card.labels.some(l => /team\s*a\b/i.test(l.name || ''));
        const hasB = card.labels.some(l => /team\s*b\b/i.test(l.name || ''));
        if (hasA && hasB) return true;
      }
      const title = card.title || '';
      if (/team\s*a\b/i.test(title) && /team\s*b\b/i.test(title)) return true;
      return false;
    },

    cardBelongsToTeam(card, teamLetter) {
      if (!card || !teamLetter) return false;
      if (this.isCardBothTeams(card)) return true;
      const t = (card.team || '').toUpperCase().trim();
      if (t === teamLetter.toUpperCase()) return true;
      if (card.labels && Array.isArray(card.labels)) {
        const reg = new RegExp('team\\s*' + teamLetter + '\\b', 'i');
        if (card.labels.some(l => reg.test(l.name || ''))) return true;
      }
      return false;
    },

    isSmmPlanningBoard() {
      const name = String(this.board?.name || '').toLowerCase();
      const wsName = String(this.board?.workspace_name || '').toLowerCase();
      return name.includes('smm') || this.board?.type === 'smm' || wsName.includes('social media');
    },

    isPlanningBoard() {
      const name = String(this.board?.name || '').toLowerCase();
      return name.includes('planning') || this.board?.is_template || this.board?.type === 'smm';
    },

    isNormalPlanningBoard() {
      return this.isPlanningBoard() && !this.isSmmPlanningBoard() && !this.isWorkflowBoard();
    },

    canFilterAllTeams() {
      const user = this?.currentUser || this?.boardData?.currentUser || {};
      if (user.can_filter_all_teams) return true;
      if (user.is_special_manager) return true;
      const roles = user.roles || [];
      if (roles.some(r => ['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss'].includes(r))) return true;
      const username = String(user.username || '').toLowerCase().trim();
      const name = String(user.name || '').toLowerCase().trim();
      if (['dara', 'kim', 'somalika'].includes(username) || username.includes('dara') || username.includes('kim') || username.includes('somalika')) return true;
      if (name.includes('dara') || name.includes('kim') || name.includes('somalika')) return true;
      return false;
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
        reactions: c.reactions || [],
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
        const dateA = a.created_at ? new Date(a.created_at).getTime() : 0;
        const dateB = b.created_at ? new Date(b.created_at).getTime() : 0;
        const diff = dateB - dateA;
        if (diff !== 0) return diff; // Newest first

        // If timestamps are exactly the same, put activities ABOVE comments
        if (a._type === 'activity' && b._type === 'comment') return -1;
        if (a._type === 'comment' && b._type === 'activity') return 1;

        // Fallback to ID sorting
        return String(b.id).localeCompare(String(a.id));
      });
    },

    getReactionGroups(reactions) {
      if (!reactions) return [];
      const groups = {};
      reactions.forEach(r => {
        if (!groups[r.emoji]) groups[r.emoji] = { count: 0, users: [], emoji: r.emoji, hasReacted: false };
        groups[r.emoji].count++;
        groups[r.emoji].users.push(r.user?.name || 'User');
        if (r.user_id === this.currentUserId) groups[r.emoji].hasReacted = true;
      });
      return Object.values(groups).sort((a, b) => b.count - a.count);
    },

    async toggleReaction(commentId, emoji) {
      if (!this.activeCard || this.isUpdatingComment) return;
      
      const commentIdx = this.activeCard.comments?.findIndex(c => c.id === commentId);
      if (commentIdx === -1) return;
      
      const comment = this.activeCard.comments[commentIdx];
      const originalReactions = [...(comment.reactions || [])];
      let newReactions = [...originalReactions];
      
      const existingReactionIndex = newReactions.findIndex(r => r.emoji === emoji && r.user_id === this.currentUserId);
      if (existingReactionIndex !== -1) {
        newReactions.splice(existingReactionIndex, 1);
      } else {
        newReactions.push({ id: 'temp-'+Date.now(), emoji: emoji, user_id: this.currentUserId, user: { id: this.currentUserId, name: this.currentUser?.name, avatar_url: this.currentUser?.avatar_url } });
      }
      
      const optimisticComments = [...this.activeCard.comments];
      optimisticComments[commentIdx] = { ...comment, reactions: newReactions };
      this.activeCard.comments = optimisticComments;
      
      this.isUpdatingComment = true;
      try {
        const res = await this.api(`/boards/cards/comments/${commentId}/react`, 'POST', { emoji: emoji }, { silentErrors: true });
        
        if (res._ok) {
            const finalComments = [...this.activeCard.comments];
            finalComments[commentIdx] = { ...finalComments[commentIdx], reactions: res.reactions };
            this.activeCard.comments = finalComments;
        } else {
            console.error("Reaction failed:", res);
            const rollbackComments = [...this.activeCard.comments];
            rollbackComments[commentIdx] = { ...comment, reactions: originalReactions };
            this.activeCard.comments = rollbackComments;
        }
      } catch (err) {
        console.error('Failed to toggle reaction', err);
        const rollbackComments = [...this.activeCard.comments];
        rollbackComments[commentIdx] = { ...comment, reactions: originalReactions };
        this.activeCard.comments = rollbackComments;
      } finally {
        this.isUpdatingComment = false;
      }
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
        this.justOpenedCtx = true;
        setTimeout(() => { this.justOpenedCtx = false; }, 400);
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
            title: 'Move card to Trash?',
            message: `Move "<strong>${this.escapeHtml(card.title)}</strong>" to Trash?<br><span class="text-xs text-slate-500 mt-1 block">Items in Trash are kept for 7 days before being automatically removed.</span>`,
            confirmText: 'Move to Trash',
            tone: 'danger',
          })) break;
          
          this.lists.forEach(l => { l.cards = l.cards.filter(c => c.id !== card.id); });
          window.showToast('Card moved to Trash (auto-removes in 7 days).');
          
          this.api(`/boards/cards/${card.id}`, 'DELETE').catch(() => {});
          break;
      }
    },

    openCardTransferModal(mode, card, list) {
      const normalizedMode = mode === 'copy' ? 'copy' : 'move';
      const targetCard = card || this.activeCard;
      if (this.isCardChecklistIncomplete(targetCard)) {
        this.showChecklistIncompleteModal(normalizedMode, targetCard);
        return;
      }

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

    // ── Bulk Selection Actions ───────────────────────────────────────────────
    startSelectMode(listId = null) {
      this.isSelectMode = true;
    },

    toggleSelectMode() {
      this.isSelectMode = !this.isSelectMode;
      if (!this.isSelectMode) {
        this.selectedCards = [];
        this.openBulkComment = false;
      }
    },

    toggleCardSelection(cardId) {
      const id = parseInt(cardId, 10);
      const idx = this.selectedCards.indexOf(id);
      if (idx > -1) {
        this.selectedCards.splice(idx, 1);
        if (this.selectedCards.length === 0) {
          this.isSelectMode = false;
        }
      } else {
        this.selectedCards.push(id);
        this.isSelectMode = true;
      }
    },

    selectAllInList(listId) {
      const list = this.lists.find(l => l.id === listId);
      if (!list || !list.cards || list.cards.length === 0) return;
      const ids = list.cards.map(c => c.id);
      const allSelected = ids.every(id => this.selectedCards.includes(id));
      if (allSelected) {
        this.selectedCards = this.selectedCards.filter(id => !ids.includes(id));
        if (this.selectedCards.length === 0) {
          this.isSelectMode = false;
        }
      } else {
        ids.forEach(id => {
          if (!this.selectedCards.includes(id)) {
            this.selectedCards.push(id);
          }
        });
        this.isSelectMode = true;
      }
    },

    exitSelectMode() {
      this.isSelectMode = false;
      this.selectedCards = [];
      this.openBulkComment = false;
      this.bulkDeleting = false;
    },

    openBulkTransferModal(mode) {
      if (!this.selectedCards.length) return;
      const normalizedMode = mode === 'copy' ? 'copy' : 'move';
      this.cardTransferModal.mode = normalizedMode;
      this.cardTransferModal.cardId = 'bulk';
      this.cardTransferModal.sourceListId = null;
      this.cardTransferModal.boardSearch = '';
      this.cardTransferModal.selectedBoardId = this.boardId;
      this.cardTransferModal.selectedListId = this.lists[0]?.id || null;
      this.cardTransferModal.title = '';
      this.cardTransferModal.submitting = false;
      this.cardTransferModal.open = true;
      this.$nextTick(() => this.ensureCardTransferSelection());
    },

    async submitBulkComment(word) {
      if (!this.selectedCards.length || !word) return;
      this.bulkCommentSubmitting = true;
      try {
        const res = await this.api(`/${this.baseRoute || 'boards'}/${this.boardSlug}/cards/bulk`, 'POST', {
          card_ids: this.selectedCards,
          action: 'comment',
          comment: word,
        });
        if (res.message) {
          window.showToast(res.message);
        }
        this.openBulkComment = false;
        this.exitSelectMode();
        window.location.reload();
      } catch (err) {
        window.showToast('Failed to post bulk comment.', 'error');
      } finally {
        this.bulkCommentSubmitting = false;
      }
    },

    async bulkDeleteCards() {
      if (!this.selectedCards.length || this.bulkDeleting) return;
      const count = this.selectedCards.length;
      const confirmed = window.confirmModal 
        ? await window.confirmModal({
            title: 'Move selected cards to Trash?',
            message: `Are you sure you want to move <strong>${count}</strong> selected card${count > 1 ? 's' : ''} to Trash?<br><span class="text-xs text-slate-500 mt-1 block">Items in Trash are kept for 7 days before being automatically removed.</span>`,
            confirmText: 'Move to Trash',
            tone: 'danger'
          })
        : confirm(`Are you sure you want to move ${count} selected card(s) to Trash?`);

      if (!confirmed) return;

      this.bulkDeleting = true;
      try {
        const res = await this.api(`/${this.baseRoute || 'boards'}/${this.boardSlug}/cards/bulk`, 'POST', {
          card_ids: this.selectedCards,
          action: 'delete'
        });

        const deletedIds = new Set(this.selectedCards.map(id => Number(id)));
        this.lists.forEach(l => {
          if (l.cards) {
            l.cards = l.cards.filter(c => !deletedIds.has(Number(c.id)));
          }
        });

        window.showToast(res.message || `${count} cards moved to Trash (auto-removes in 7 days).`);
        this.exitSelectMode();
      } catch (err) {
        window.showToast('Failed to delete selected cards.', 'error');
      } finally {
        this.bulkDeleting = false;
      }
    },

    cardTransferBoards() {
      const search = this.cardTransferModal.boardSearch.trim().toLowerCase();
      const boards = this.allWorkspaces.flatMap(ws =>
        (ws.boards || []).map(b => ({
          ...b,
          workspace_name: ws.name,
        }))
      );

      // If viewing a hidden board, ensure the current board itself is also included in transfer list
      if (this.board && !boards.some(b => b.id === this.board.id)) {
        boards.unshift({
          id: this.board.id,
          name: this.board.name,
          workspace_id: this.board.workspace_id,
          workspace_name: this.board.workspace?.name || 'Current Board',
          lists: this.lists,
        });
      }

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

      if (this.cardTransferModal.mode === 'copy' && this.cardTransferModal.cardId !== 'bulk') {
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

      if (this.cardTransferModal.cardId === 'bulk') {
        const targetBoardId = parseInt(this.cardTransferModal.selectedBoardId, 10);
        const targetListId = parseInt(this.cardTransferModal.selectedListId, 10);
        if (!targetBoardId || !targetListId) {
          window.showToast('Choose a destination board and list first.', 'error');
          return;
        }
        this.cardTransferModal.submitting = true;
        try {
          const res = await this.api(`/${this.baseRoute || 'boards'}/${this.boardSlug}/cards/bulk`, 'POST', {
            card_ids: this.selectedCards,
            action: mode,
            target_board_id: targetBoardId,
            target_list_id: targetListId,
          });
          if (res.message) {
            window.showToast(res.message);
          }
          this.closeCardTransferModal();
          this.exitSelectMode();
          window.location.reload();
        } catch (err) {
          window.showToast('Failed to perform bulk transfer.', 'error');
        } finally {
          this.cardTransferModal.submitting = false;
        }
        return;
      }

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
      const targetCard = sourceCard || this.findCard(cardId) || this.activeCard;

      if (this.isCardChecklistIncomplete(targetCard)) {
        this.closeCardTransferModal();
        this.showChecklistIncompleteModal(mode === 'copy' ? 'copy' : 'move', targetCard);
        return;
      }

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

      // Optimistic update
      if (this.activeCard && this.activeCard.id === id) {
        Object.assign(this.activeCard, payload);
      }
      // Sync into board list card for badge
      this.lists.forEach(l => {
        const c = l.cards.find(x => x.id === id);
        if (c) Object.assign(c, payload);
      });
      window.showToast('Dates saved!');
      this.closeDatePicker();

      // Background API call
      this.api(`/boards/cards/${id}`, 'PATCH', payload).catch(() => {});
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
      const base = 'hover:bg-indigo-100 hover:text-indigo-700 text-slate-700 dark:text-slate-200';
      const today = new Date().toISOString().substring(0,10);

      if (iso === dp.dueDate && dp.useDue)   return 'dp-day-due bg-indigo-600 text-white font-bold rounded-xl shadow-sm';
      if (iso === dp.startDate && dp.useStart) return 'dp-day-start bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-100 font-bold rounded-xl';
      if (iso === today)   return base + ' dp-day-today ring-2 ring-indigo-400 dark:ring-cyan-400 rounded-xl font-bold';
      // Highlight range between start and due
      if (dp.startDate && dp.dueDate && dp.useStart && dp.useDue) {
        if (iso > dp.startDate && iso < dp.dueDate) {
          return 'dp-day-range bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-cyan-300 rounded-xl';
        }
      }
      return base + ' rounded-xl';
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
          const boardWrap = document.querySelector('.board-wrap');
          const containers = document.querySelectorAll('.list-cards');
          containers.forEach(el => {
            if (el.sortableInstance) {
              el.sortableInstance.destroy();
            }

            el.sortableInstance = new Sortable(el, {
              group: {
                name: 'cards',
                pull: true,
                put: true
              },
              draggable: '.kanban-card[data-can-drag="1"]',
              filter: 'button, input, select, textarea, a, .card-quick-btn, .block-fix-btn, [data-no-drag]',
              preventOnFilter: false,
              animation: 180,
              ghostClass: 'sortable-ghost',
              chosenClass: 'sortable-chosen',
              dragClass: 'sortable-drag',
              delay: window.innerWidth < 768 ? 220 : 120,
              delayOnTouchOnly: true,
              touchStartThreshold: window.innerWidth < 768 ? 4 : 6,
              emptyInsertThreshold: 60,
              scroll: true,
              scrollSensitivity: 100,
              scrollSpeed: 20,
              bubbleScroll: true,
              onStart: () => {
                this.realtimeDragging = true;
                if (typeof this.ctxTouchEnd === 'function') {
                  this.ctxTouchEnd();
                }
                document.body.classList.add('is-dragging-card');
              },
              onMove: (evt) => {
                const toListId = parseInt(evt.to?.dataset?.listId || evt.to?.getAttribute('data-list-id'));
                const toList = this.lists.find(l => l.id === toListId);

                // Only block if dropping into a block list without permission
                if (toList && this.isBlockList(toList)) {
                  if (!this.currentUser?.can_manage_blocked_cards) {
                    return false;
                  }
                }

                // Highlight target list
                containers.forEach(c => {
                  if (c === evt.to) {
                    c.classList.add('drag-over');
                  } else {
                    c.classList.remove('drag-over');
                  }
                });
                return true;
              },
              onEnd: async (evt) => {
                this.realtimeDragging = false;
                this.justDroppedCard = true;
                setTimeout(() => { this.justDroppedCard = false; }, 300);
                document.body.classList.remove('is-dragging-card');
                containers.forEach(c => c.classList.remove('drag-over'));

                const cardId = evt.item?.dataset?.id || evt.item?.getAttribute('data-id');
                const fromListId = evt.from?.dataset?.listId || evt.from?.getAttribute('data-list-id');
                const toListId = evt.to?.dataset?.listId || evt.to?.getAttribute('data-list-id');
                const newIndex = evt.newIndex;
                const oldIndex = evt.oldIndex;

                if (!cardId || !fromListId || !toListId) return;
                if (fromListId === toListId && oldIndex === newIndex) return;

                await this.persistCardOrder(cardId, fromListId, toListId, newIndex, evt);
              }
            });
          });

          // Initialize list reordering
          const sortableContainer = document.getElementById('sortable-lists-container');
          if (sortableContainer) {
            if (sortableContainer.sortableListInstance) {
              sortableContainer.sortableListInstance.destroy();
            }
            sortableContainer.sortableListInstance = new Sortable(sortableContainer, {
              group: 'board-lists',
              draggable: '.board-list',
              handle: '.list-header',
              filter: '.add-list-wrapper, .list-cards, .add-card-btn, button, input',
              preventOnFilter: false,
              animation: 200,
              easing: 'cubic-bezier(0.25, 1, 0.5, 1)',
              ghostClass: 'sortable-list-ghost',
              chosenClass: 'sortable-list-chosen',
              dragClass: 'sortable-list-drag',
              delay: window.innerWidth < 768 ? 400 : 80,
              delayOnTouchOnly: true,
              touchStartThreshold: 5,
              scroll: true,
              scrollSensitivity: 100,
              scrollSpeed: 20,
              bubbleScroll: true,
              onMove: (evt) => {
                // Ensure we don't drag over the "Add list" container which might not have an id
                if (evt.related && !evt.related.classList.contains('board-list')) {
                  return false;
                }
                return true;
              },
              onEnd: async (evt) => {
                if (evt.oldIndex === evt.newIndex) return;

                const wrap = boardWrap || document.getElementById('sortable-lists-container') || document;
                const listElements = Array.from(wrap.querySelectorAll('.board-list'));
                const order = listElements
                    .filter(el => el.id && el.id.startsWith('list-'))
                    .map(el => parseInt(el.id.replace('list-', '')));

                try {
                  const res = await this.api(`/boards/${this.boardSlug}/lists/reorder`, 'POST', { order });
                  if (res._ok === false) throw new Error('Failed to reorder lists');

                  // Reorder local alpine state array
                  const movedList = this.lists.splice(evt.oldIndex, 1)[0];
                  this.lists.splice(evt.newIndex, 0, movedList);
                } catch (e) {
                  console.error(e);
                  this.initSortable(); // Reset visual state if failed
                }
              }
            });
          }
        }, 100);
      });
    },

    async persistCardOrder(cardId, fromListId, toListId, newIndex, evt = null) {
      const id = parseInt(cardId);
      const fromId = parseInt(fromListId);
      const toId = parseInt(toListId);
      const cardBeforeMove = this.findCard(id);
      const sourceListBeforeMove = this.lists.find(l => l.id === fromId);
      const targetListBeforeMove = this.lists.find(l => l.id === toId);

      if (!cardBeforeMove) {
        this.initSortable();
        return;
      }

      if (!this.canDragCard(cardBeforeMove, sourceListBeforeMove)) {
        window.showToast('You do not have permission to move this card.', 'error');
        this.initSortable();
        return;
      }

      if (this.isBlockList(targetListBeforeMove) && !this.currentUser?.can_manage_blocked_cards) {
        window.showToast('Only supervisors can move cards to Blocked list.', 'error');
        this.initSortable();
        return;
      }

      if (fromId !== toId && this.isCardChecklistIncomplete(cardBeforeMove)) {
        this.showChecklistIncompleteModal('move', cardBeforeMove);
        this.initSortable();
        return;
      }

      // Revert SortableJS DOM move so Alpine can cleanly manage its reactive DOM without duplicating or missing nodes!
      if (evt && evt.item && evt.from) {
        if (evt.from !== evt.to) {
          if (evt.oldIndex !== undefined && evt.oldIndex < evt.from.children.length) {
            evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] || null);
          } else {
            evt.from.appendChild(evt.item);
          }
        }
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

      const targetList = this.lists.find(l => l.id === parseInt(toListId));
      const order = targetList ? targetList.cards.map(c => parseInt(c.id)).filter(id => !isNaN(id)) : [];

      try {
        // Save target list order
        const reorderRes = await this.api(`/boards/${this.boardSlug}/cards/reorder`, 'POST', {
          list_id: parseInt(toListId),
          source_list_id: parseInt(fromListId),
          moving_card_id: parseInt(cardId),
          order: order
        });
        if (reorderRes._ok === false) {
          this.initSortable();
          return;
        }

        // If moved to a different column, trigger move notifications/activities
        if (fromListId !== toListId) {
          const res = await this.api(`/boards/cards/${cardId}/move`, 'POST', {
            board_list_id: parseInt(toListId),
            source_list_id: parseInt(fromListId),
            position: newIndex
          });
          if (res._ok === false) {
            this.initSortable();
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
                 actualTargetList.cards.unshift(res.card);
               }
            }

            const automationNote = res.automation_triggered
              ? ` Automation then ${res.automation?.action_type === 'copy' ? 'copied' : 'moved'} the card (${res.automation?.reason || 'matching rule'}).`
              : '';

            if (automationNote) {
              window.showToast(`${currentUser.name || 'You'} moved "${res.card.title}" from ${fromList} to ${toList}.${automationNote}`);
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

    async editChecklistInline(cl, title, bulkAssignUserId = '__keep__') {
      const trimmedTitle = (title || '').trim();
      if (!trimmedTitle) {
        window.showToast('Checklist title cannot be empty', 'warning');
        return;
      }

      const titleChanged = trimmedTitle !== (cl.name || cl.title);
      const assignmentChanged = bulkAssignUserId !== '__keep__';

      if (!titleChanged && !assignmentChanged) {
        return;
      }

      try {
        const payload = { title: trimmedTitle };
        if (assignmentChanged) {
          if (bulkAssignUserId === '__clear__') {
            payload.assigned_user_id = null;
            payload.assigned_user_ids = [];
          } else {
            payload.assigned_user_id = Number(bulkAssignUserId);
            payload.assigned_user_ids = [Number(bulkAssignUserId)];
          }
        }

        const res = await this.api(`/boards/cards/${this.activeCard.id}/checklists/${cl.id}`, 'PATCH', payload);
        if (res.success && res.checklist) {
          cl.name = res.checklist.title;
          cl.title = res.checklist.title;
          if (Array.isArray(res.checklist.items)) {
            cl.items = res.checklist.items;
          }
          this.updateCardChecklistProgress();

          if (assignmentChanged) {
            if (bulkAssignUserId === '__clear__') {
              window.showToast('Checklist updated and all checkboxes unassigned', 'success');
            } else {
              const u = this.findMemberById(bulkAssignUserId);
              const name = u ? u.name : 'selected user';
              window.showToast(`Checklist updated and all checkboxes assigned to ${name}`, 'success');
            }
          } else {
            window.showToast('Checklist updated', 'success');
          }
        }
      } catch (e) {
        console.error(e);
        const msg = e?.response?.data?.message || e?.message || 'Failed to edit checklist';
        window.showToast(msg, 'error');
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
      if (this.filterAssignBy) count++;
      if (this.filterLabel) count++;
      if (this.filterStatus && this.filterStatus !== 'all') count++;
      if (this.filterDateFrom) count++;
      if (this.filterDateTo) count++;
      if (this.filterTeamLabel) count++;
      if (this.filterSmmClass) count++;
      if (this.filterCluster) count++;
      if (this.filterContentPublicDateFrom) count++;
      if (this.filterContentPublicDateTo) count++;
      if (this.filterPublicDate) count++;
      const isTeamLocked = this.isWorkflowBoard() || (this.isNormalPlanningBoard() && !this.canFilterAllTeams() && this.currentUser?.team);
      if (!isTeamLocked && this.filterTeam) count++;
      if (this.filterCategory) count++;
      return count;
    },

    clearFilters(keepOpen = false) {
      this.searchQuery = '';
      this.filterPriority = '';
      this.filterAssignee = '';
      this.filterAssignBy = '';
      this.filterLabel = '';
      this.filterStatus = '';
      this.filterDateFrom = '';
      this.filterDateTo = '';
      this.filterTeamLabel = '';
      this.filterSmmClass = '';
      this.filterCluster = '';
      this.filterContentPublicDateFrom = '';
      this.filterContentPublicDateTo = '';
      this.filterPublicDate = '';
      const canFilterAll = this.canFilterAllTeams();
      if (this.isWorkflowBoard()) {
        const bTeam = this.getBoardTeam();
        this.filterTeam = bTeam || '';
      } else if (this.isNormalPlanningBoard() && !canFilterAll && this.currentUser?.team) {
        this.filterTeam = this.currentUser.team;
      } else {
        this.filterTeam = '';
      }
      this.filterCategory = '';
      this.filterOpenAssignBy = false;
      this.filterAssignBySearch = '';
      this.filterOpenAssignee = false;
      this.filterAssigneeSearch = '';
      this.searchOpen = false;
      if (!keepOpen) {
        this.filtersOpen = false;
      }
    },

    cardMatchesDate(dateVal, query) {
      if (!dateVal || !query) return false;
      const raw = String(dateVal).trim().substring(0, 10);
      const q = query.trim().toLowerCase();

      const m = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
      if (!m) return false;
      const year = m[1];
      const month = m[2];
      const day = m[3];
      const monthNamesShort = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
      const monthNamesFull = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
      const mIdx = parseInt(month, 10) - 1;
      const shortMonth = monthNamesShort[mIdx] || '';
      const fullMonth = monthNamesFull[mIdx] || '';

      // If query is just 1 or 2 digits, match exact day of month only
      if (/^\d{1,2}$/.test(q)) {
        const num = parseInt(q, 10);
        return num === parseInt(day, 10);
      }

      // Direct raw check if q contains delimiters or has length >= 4
      if ((q.includes('-') || q.includes('/')) && raw.toLowerCase().includes(q)) return true;

      const variations = [
        `${day}/${month}/${year}`,
        `${parseInt(day, 10)}/${parseInt(month, 10)}/${year}`,
        `${day}/${month}`,
        `${parseInt(day, 10)}/${parseInt(month, 10)}`,
        `${day}-${month}-${year}`,
        `${day}-${month}`,
        `${year}-${month}-${day}`,
        `${year}/${month}/${day}`,
        `${day} ${shortMonth} ${year}`,
        `${day} ${shortMonth}`,
        `${day} ${fullMonth} ${year}`,
        `${day} ${fullMonth}`,
        `${shortMonth} ${day}`,
        `${fullMonth} ${day}`,
      ];

      if (variations.some(v => v === q || v.includes(q) || (q.length >= 5 && q.includes(v)))) return true;

      // Clean digits comparison (e.g. 25092026 or 2509)
      const cleanQ = q.replace(/[^0-9]/g, '');
      if (cleanQ.length >= 4) {
        const cleanDateDMY = `${day}${month}${year}`;
        const cleanDateYMD = `${year}${month}${day}`;
        const cleanDateDM = `${day}${month}`;
        if (cleanDateDMY.includes(cleanQ) || cleanDateYMD.includes(cleanQ) || cleanDateDM === cleanQ) {
          return true;
        }
      }

      return false;
    },

    hasActiveFilters() {
      const isTeamLocked = this.isWorkflowBoard() || (this.isNormalPlanningBoard() && !this.canFilterAllTeams() && this.currentUser?.team);
      const hasTeamFilter = isTeamLocked ? false : !!this.filterTeam;

      return !!(
        (this.searchQuery && this.searchQuery.trim()) ||
        this.filterPublicDate ||
        hasTeamFilter ||
        this.filterCategory ||
        this.filterPriority ||
        this.filterLabel ||
        this.filterAssignee ||
        this.filterAssignBy ||
        this.filterTeamLabel ||
        this.filterSmmClass ||
        this.filterCluster ||
        this.filterContentPublicDateFrom ||
        this.filterContentPublicDateTo ||
        (this.filterStatus && this.filterStatus !== 'all') ||
        this.filterDateFrom ||
        this.filterDateTo
      );
    },

    filteredCards(list) {
      if (!list || !Array.isArray(list.cards)) return [];

      const isBlock = this.isBlockList(list);
      const isNormalPlanning = this.isNormalPlanningBoard();
      const canFilterAll = this.canFilterAllTeams();
      const userTeam = this.currentUser?.team;

      // Fast path: No active filters
      if (!this.hasActiveFilters()) {
        let baseCards = list.cards;
        if (isNormalPlanning && !canFilterAll && userTeam) {
          baseCards = baseCards.filter(c => {
            if (this.isCardBothTeams(c)) return true;
            const isDirectAssignee = c.assignees && c.assignees.some(u => u.id === this.currentUser?.id);
            if (isDirectAssignee) return true;
            const cardTeam = (c.team || '').toUpperCase().trim();
            if (userTeam === 'A' && cardTeam === 'B') return false;
            if (userTeam === 'B' && cardTeam === 'A') return false;
            return true;
          });
        }
        if (!isBlock) return baseCards;
        return [...baseCards].sort((a, b) => {
          const aDone = a.block_completed_at ? 1 : 0;
          const bDone = b.block_completed_at ? 1 : 0;
          if (aDone !== bDone) return aDone - bDone;
          return (a.position ?? 0) - (b.position ?? 0);
        });
      }

      // Pre-compute filter values once per list evaluation
      const q = this.searchQuery ? this.searchQuery.toLowerCase().trim() : '';
      const filterAssigneeId = this.filterAssignee ? parseInt(this.filterAssignee, 10) : null;
      const filterAssignById = this.filterAssignBy ? parseInt(this.filterAssignBy, 10) : null;
      const filterLabelId = this.filterLabel ? this.filterLabel : null;
      const teamLower = this.filterTeamLabel ? this.filterTeamLabel.toLowerCase() : null;
      const classLower = this.filterSmmClass ? this.filterSmmClass.toLowerCase() : null;
      const clusterLower = this.filterCluster ? this.filterCluster.toLowerCase() : null;
      const statusLower = (this.filterStatus && this.filterStatus !== 'all') ? this.filterStatus.toLowerCase() : null;

      const cards = list.cards.filter(c => {
        // Team filter:
        // - Workflow boards: strictly auto-show only that board's team cards (or cards belonging to Both teams)!
        // - Normal planning boards: regular team members can ONLY see their own team's cards (or Both teams)!
        // - Users with all-teams access (dara, kim, somalika, admin-digital, supervisor, boss) or SMM planning boards: can see both teams, or filter optionally by Team A or Team B or ALL
        if (this.isWorkflowBoard()) {
          const bTeam = this.getBoardTeam();
          if (bTeam) {
            if (!this.cardBelongsToTeam(c, bTeam)) return false;
          }
        } else if (isNormalPlanning && !canFilterAll && (userTeam === 'A' || userTeam === 'B')) {
          if (!this.isCardBothTeams(c)) {
            const isDirectAssignee = c.assignees && c.assignees.some(u => u.id === this.currentUser?.id);
            if (!isDirectAssignee) {
              const cardTeam = (c.team || '').toUpperCase().trim();
              if (userTeam === 'A' && (cardTeam === 'B' || (c.labels && c.labels.some(l => /team\s*b\b/i.test(l.name || ''))))) return false;
              if (userTeam === 'B' && (cardTeam === 'A' || (c.labels && c.labels.some(l => /team\s*a\b/i.test(l.name || ''))))) return false;
            }
          }
        } else {
          // Users who can filter all teams or SMM planning board: filter optionally by Team A or Team B, or show ALL
          if (this.filterTeam) {
            if (!this.cardBelongsToTeam(c, this.filterTeam)) return false;
          }
        }

        // Category filter (Video, Graphic, Listing, Content, SMM)
        if (this.filterCategory) {
          const cat = this.filterCategory.toLowerCase();
          const matchLabel = c.labels && c.labels.some(l => (l.name || '').toLowerCase().includes(cat));
          const matchClass = (c.smm_class_label || '').toLowerCase().includes(cat);
          const matchTeam = (c.smm_team_label || '').toLowerCase().includes(cat);
          const matchCardLabel = (c.label || '').toLowerCase().includes(cat);
          const matchSubLabel = (c.sub_label || '').toLowerCase().includes(cat);
          const matchTitle = (c.title || '').toLowerCase().includes(cat);
          if (!matchLabel && !matchClass && !matchTeam && !matchCardLabel && !matchSubLabel && !matchTitle) return false;
        }

        // Search text (matches title, description, public date, due date, start date, assignees, labels)
        if (q) {
          const matchTitle = c.title && c.title.toLowerCase().includes(q);
          const matchDesc = c.description && c.description.toLowerCase().includes(q);
          const matchPubDate = this.cardMatchesDate(c.content_public_date, q);
          const matchDueDate = this.cardMatchesDate(c.due_at, q);
          const matchStartDate = this.cardMatchesDate(c.start_date, q);
          const matchAssignee = c.assignees && c.assignees.some(u => u.name && u.name.toLowerCase().includes(q));
          const matchLabels = c.labels && c.labels.some(l => l.name && l.name.toLowerCase().includes(q));
          const matchSmmTeam = c.smm_team_label && c.smm_team_label.toLowerCase().includes(q);
          const matchSmmClass = c.smm_class_label && c.smm_class_label.toLowerCase().includes(q);

          if (!matchTitle && !matchDesc && !matchPubDate && !matchDueDate && !matchStartDate && !matchAssignee && !matchLabels && !matchSmmTeam && !matchSmmClass) return false;
        }

        // Direct Public Date picker filter
        if (this.filterPublicDate) {
          const pubDate = (c.content_public_date || '').substring(0, 10);
          const dueDate = (c.due_at || '').substring(0, 10);
          const startDate = (c.start_date || '').substring(0, 10);
          if (pubDate) {
            if (pubDate !== this.filterPublicDate) return false;
          } else if (dueDate || startDate) {
            if (dueDate !== this.filterPublicDate && startDate !== this.filterPublicDate) return false;
          } else {
            return false;
          }
        }

        // Priority
        if (this.filterPriority && c.priority !== this.filterPriority) return false;

        // Label
        if (filterLabelId) {
          const lblObj = (this.labels || []).find(l => String(l.id) === String(filterLabelId));
          const lblName = lblObj ? (lblObj.name || '').toLowerCase() : '';
          const matchRel = c.labels && c.labels.some(lbl => String(lbl.id) === String(filterLabelId) || (lblName && (lbl.name || '').toLowerCase() === lblName));
          const matchText = lblName && ((c.label || '').toLowerCase() === lblName || (c.sub_label || '').toLowerCase() === lblName);
          if (!matchRel && !matchText) return false;
        }

        // Assignee
        if (filterAssigneeId && (!c.assignees || !c.assignees.some(u => String(u.id) === String(filterAssigneeId)))) return false;

        // Assign By
        if (filterAssignById && (!c.creator || String(c.creator.id) !== String(filterAssignById)) && String(c.created_by) !== String(filterAssignById)) return false;

        // Team Label
        if (teamLower && (!c.smm_team_label || !c.smm_team_label.toLowerCase().includes(teamLower))) return false;

        // SMM Class
        if (classLower && (!c.smm_class_label || !c.smm_class_label.toLowerCase().includes(classLower))) return false;

        // Cluster
        if (clusterLower && (!c.smm_cluster_label || !c.smm_cluster_label.toLowerCase().includes(clusterLower))) return false;

        // Content Public Date
        if (this.filterContentPublicDateFrom || this.filterContentPublicDateTo) {
          const pubDate = c.content_public_date || '';
          if (!pubDate) return false;
          if (this.filterContentPublicDateFrom && pubDate < this.filterContentPublicDateFrom) return false;
          if (this.filterContentPublicDateTo && pubDate > this.filterContentPublicDateTo) return false;
        }

        // Status
        if (statusLower) {
          const s = (c.status || '').toLowerCase();
          if (statusLower === 'approved') {
            if (s !== 'approved') return false;
          } else if (statusLower === 'unapproved') {
            if (s === 'approved') return false;
          } else {
            if (s !== statusLower) return false;
          }
        }

        if (this.filterDateFrom || this.filterDateTo) {
          const cardDate = c.due_at || c.start_date || '';
          if (!cardDate) return false;
          if (this.filterDateFrom && cardDate < this.filterDateFrom) return false;
          if (this.filterDateTo && cardDate > this.filterDateTo) return false;
        }

        return true;
      });

      if (isBlock) {
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
      const uname = (this.currentUser?.username || '').toLowerCase().trim();
      const name = (this.currentUser?.name || '').toLowerCase().trim();
      const email = (this.currentUser?.email || '').toLowerCase().trim();
      const isAllowedUser = uname.includes('dara') || uname.includes('kim') ||
                            name.includes('dara') || name.includes('kim') ||
                            email.includes('dara') || email.includes('kim');
      if (isAllowedUser) return true;
      if (this.currentUser?.can_move_any_card) return true;
      if (Array.isArray(this.currentUser?.roles) && (
          this.currentUser.roles.includes('super-admin') ||
          this.currentUser.roles.includes('admin-digital') ||
          this.currentUser.roles.includes('admin') ||
          this.currentUser.roles.includes('supervisor') ||
          this.currentUser.roles.includes('boss')
      )) return true;
      if (this.isBlockList(list)) return !!this.currentUser?.can_manage_blocked_cards;
      const myId = parseInt(this.currentUserId || this.currentUser?.id || 0);
      const isAssigned = (card.assignees || []).some(u => parseInt(u.id) === myId);
      const creatorId = parseInt(card.created_by || card.creator?.id || card.creator_id || 0);
      const isCreator = creatorId > 0 && creatorId === myId;
      return isAssigned || isCreator;
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

    async toggleSupervisorApprove(card, list) {
      const wasApproved = (card.status === 'Approved' || card.status === 'approved');
      const oldStatus = card.status;
      
      // Optimistic UI update
      card.status = wasApproved ? 'in_progress' : 'approved';
      
      try {
        const res = await this.api(`/boards/cards/${card.id}/toggle-approve`, 'POST', {});
        if (res.card) {
          Object.assign(card, res.card);
          window.showToast(res.message || 'Task approval toggled.');
        } else {
          card.status = oldStatus;
        }
      } catch (err) {
        card.status = oldStatus;
        window.showToast('Failed to toggle approval.', 'error');
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
      if (view === 'trash') this.fetchTrashItems();
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
        trash: 'Trash',
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

    async toggleBoardWatch() {
      if (this.boardMenu.busy) return;
      this.boardMenu.busy = true;
      try {
        const res = await this.api(`/${this.baseRoute}/${this.boardSlug}/watch`, 'POST');
        if (res && res._ok !== false) {
          this.boardMenu.watched = res.watching;
          window.showToast(res.message);
        }
      } catch (err) {
        window.showToast('Error updating watch status', 'error');
      } finally {
        this.boardMenu.busy = false;
      }
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
        // setTimeout(() => window.location.reload(), 500);
      }
    },

    async fetchTrashItems() {
      const bm = this.boardMenu;
      bm.trashLoading = true;
      try {
        const res = await this.api(`/${this.baseRoute}/${this.boardSlug}/trash`, 'GET');
        bm.trashItems = (res && res.items) ? res.items : [];
      } catch (err) {
        console.error('Error fetching trash items:', err);
        bm.trashItems = [];
      } finally {
        bm.trashLoading = false;
      }
    },

    filteredTrashItems() {
      const tab = this.boardMenu.trashTab;
      // tab is 'cards' or 'lists' (plural), but API returns type 'card'/'list' (singular)
      const typeMap = { 'cards': 'card', 'lists': 'list' };
      const typeFilter = typeMap[tab] || tab;
      return (this.boardMenu.trashItems || []).filter(item => item.type === typeFilter);
    },

    isTrashSelected(type, id) {
      return this.boardMenu.selectedTrashItems.some(s => s.type === type && s.id === id);
    },

    toggleTrashSelect(type, id, checked) {
      if (checked) {
        if (!this.isTrashSelected(type, id)) {
          this.boardMenu.selectedTrashItems.push({ type, id });
        }
      } else {
        this.boardMenu.selectedTrashItems = this.boardMenu.selectedTrashItems.filter(
          s => !(s.type === type && s.id === id)
        );
      }
    },

    toggleTrashSelectAll(checked) {
      if (checked) {
        this.boardMenu.selectedTrashItems = this.filteredTrashItems().map(item => ({ type: item.type, id: item.id }));
      } else {
        this.boardMenu.selectedTrashItems = [];
      }
    },

    async restoreSelectedTrashItems() {
      const items = [...this.boardMenu.selectedTrashItems];
      if (!items.length) return;
      if (!await window.confirmModal(`Restore ${items.length} item(s)?`)) return;
      const res = await this.api(`/${this.baseRoute}/${this.boardSlug}/trash/restore-bulk`, 'POST', { items });
      if (res && (res.message || res.success)) {
        window.showToast(res.message || 'Items restored.');
        this.boardMenu.selectedTrashItems = [];
        await this.fetchTrashItems();
        setTimeout(() => window.location.reload(), 500);
      }
    },

    async forceDeleteSelectedTrashItems() {
      const items = [...this.boardMenu.selectedTrashItems];
      if (!items.length) return;
      if (!await window.confirmModal(`Permanently delete ${items.length} item(s)? This cannot be undone.`, 'Delete Permanently', 'Cancel', 'bg-rose-600')) return;
      const res = await this.api(`/${this.baseRoute}/${this.boardSlug}/trash/force-bulk`, 'DELETE', { items });
      if (res && (res.message || res.success)) {
        window.showToast(res.message || 'Items permanently deleted.');
        this.boardMenu.selectedTrashItems = [];
        await this.fetchTrashItems();
      }
    },

    async restoreTrashItem(type, id) {
      if (!await window.confirmModal(`Restore this ${type}?`)) return;
      const res = await this.api(`/${this.baseRoute}/${this.boardSlug}/trash/restore`, 'POST', { type, id });
      if (res && (res.message || res.success)) {
        window.showToast(res.message || 'Item restored.');
        await this.fetchTrashItems();
        setTimeout(() => window.location.reload(), 500);
      }
    },

    async forceDeleteTrashItem(type, id) {
      if (!await window.confirmModal(`Permanently delete this ${type}? This cannot be undone.`, 'Delete Permanently', 'Cancel', 'bg-rose-600')) return;
      const res = await this.api(`/${this.baseRoute}/${this.boardSlug}/trash/force`, 'DELETE', { type, id });
      if (res && (res.message || res.success)) {
        window.showToast(res.message || 'Item permanently deleted.');
        await this.fetchTrashItems();
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

    formatInputDate(dateStr) {
      const d = this.parseCardDate(dateStr);
      if (!d) return '';
      const formatter = new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        timeZone: this.khTimeZone
      });
      const parts = formatter.formatToParts(d);
      const year = parts.find(p => p.type === 'year')?.value;
      const month = parts.find(p => p.type === 'month')?.value;
      const day = parts.find(p => p.type === 'day')?.value;
      if (year && month && day) {
        return `${year}-${month}-${day}`;
      }
      // Fallback
      return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
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
      return `${donePrefix}End: ${dateLabel}${timeLabel ? ` - ${timeLabel}` : ''}`;
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

    formatDateHuman(dateStr) {
      if (!dateStr) return '';
      return this.formatDate(dateStr);
    },

    getSmmClassColor(name) {
      if (!this.smmClasses) return '#e2e8f0';
      const cls = this.smmClasses.find(c => c.name === name);
      return cls && cls.color ? cls.color : '#e2e8f0';
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

      const isBossOrSupervisor = this.currentUser?.is_special_manager
        || this.currentUser?.can_filter_all_teams
        || (this.currentUser?.roles || []).some(r => ['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss'].includes(r))
        || ['dara', 'kim', 'kimoun'].some(name => (this.currentUser?.name || '').toLowerCase().includes(name));

      if (!isBossOrSupervisor && this.currentUserId) {
        em.memberId = this.currentUserId;
      } else {
        em.memberId = 'all';
      }

      em.assignById = 'all';
      em.labelId = 'all';
      em.labelIds = [];
      em.openAssigneeDropdown = false;
      em.assigneeSearch = '';
      em.openAssignByDropdown = false;
      em.assignBySearch = '';
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
      
      // Assign By
      params.append('assign_by_id', em.assignById);

      // Labels (single or multi-select)
      if (Array.isArray(em.labelIds) && em.labelIds.length > 0) {
        em.labelIds.forEach(id => {
          params.append('label_ids[]', id);
        });
      } else if (em.labelId && em.labelId !== 'all') {
        params.append('label_id', em.labelId);
      }

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
      
      // Cache buster for Mac App Webview
      params.append('_t', new Date().getTime());

      const route = em.format === 'pdf' ? 'export/pdf' : 'export/csv';
      const url = `/${this.baseRoute || 'boards'}/${this.boardSlug}/${route}?${params.toString()}`;

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
      im.worksheetName = '';
      im.targetListId = '';
      im.file = null;
      im.preview = null;
      im.error = null;
      im.result = null;
      im.busy = false;
      im.confirmCooldown = false;
      im.previewFilter = 'all';
    },

    closeImportModal() {
      if (this.importModal.step === 3) {
        window.location.reload();
        return;
      }
      this.importModal.open = false;
      this.importModal.busy = false;
      this.importModal.confirmCooldown = false;
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
      window.location.href = `/${this.baseRoute || 'boards'}/${this.boardSlug}/import/template`;
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
      
      if (im.worksheetName.trim()) {
        formData.append('worksheet_name', im.worksheetName.trim());
      }
      if (im.targetListId) {
        formData.append('target_list_id', im.targetListId);
      }

      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || this.csrfToken;
        const res = await fetch(`/${this.baseRoute || 'boards'}/${this.boardSlug}/import/preview`, {
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
        im.confirmCooldown = true;
        setTimeout(() => {
          im.confirmCooldown = false;
        }, 600);
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

        const res = await this.api(`/${this.baseRoute || 'boards'}/${this.boardSlug}/import/confirm`, 'POST', payload);
        
        if (res.cards && Array.isArray(res.cards)) {
          // Push new/updated cards directly into their respective lists without duplicating
          res.cards.forEach(newCard => {
            this.lists.forEach(l => {
              const idx = l.cards.findIndex(c => c.id === newCard.id);
              if (idx !== -1) {
                l.cards.splice(idx, 1);
              }
            });
            const list = this.lists.find(l => l.id === newCard.board_list_id);
            if (list) {
              list.cards.push(newCard);
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
          trigger_word: this.newAutomation.trigger_word || null,
          trigger_board_id: this.newAutomation.trigger_board_id || null,
          trigger_list_id: this.newAutomation.trigger_list_id || null,
          target_board_id: this.newAutomation.target_board_id,
          target_list_id: this.newAutomation.target_list_id,
          target_assignee_id: assigneeId || null,
          target_assignee_role: assigneeRole || null,
          action_type: this.newAutomation.action_type || 'move'
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
      if (!await window.confirmModal({
        title: 'Delete entire list?',
        message: 'Are you sure you want to delete this list and move all its cards to Trash?',
        confirmText: 'Delete List',
        tone: 'danger'
      })) return;
      
      const res = await this.api(`/boards/lists/${listId}`, 'DELETE');
      if (res.message) {
        this.lists = this.lists.filter(l => l.id !== listId);
        window.showToast(res.message);
      }
    },

    async clearList(listId) {
      if (!await window.confirmModal("Are you sure you want to clear ALL CARDS from this list permanently? This action cannot be undone.")) return;
      
      // Optimistic real-time UI clear
      const targetList = this.lists.find(l => l.id === listId);
      if (targetList) targetList.cards = [];
      window.showToast("List cleared successfully.");
      
      this.api(`/boards/lists/${listId}/clear`, 'DELETE', null, { silentErrors: true }).catch(() => {});
    },

    // ── Add card ─────────────────────────────────────────────────────────────
    startAddCard(listId) {
      this.addingCardListId = listId;
      this.newCardTitle     = '';
      this.newCardTeam      = null;

      const uname = (this.currentUser?.username || '').toLowerCase().trim();
      const name = (this.currentUser?.name || '').toLowerCase().trim();
      let defaultTeam = null;
      if (uname.includes('kim') || name.includes('kim') || this.currentUser?.id === 13) {
        defaultTeam = 'B';
      } else if (uname.includes('dara') || name.includes('dara') || this.currentUser?.id === 12) {
        defaultTeam = 'A';
      } else if (this.currentUser?.team) {
        defaultTeam = this.currentUser.team;
      }

      this.newCardAssignedTeam = (this.isWorkflowBoard() ? this.getBoardTeam() : null) || defaultTeam || this.filterTeam || 'A';
      this.$nextTick(() => {
        const el = document.querySelector(`#cards-${listId}`);
        if (el) el.scrollTop = el.scrollHeight;
      });
    },

    async saveCard(listId) {
      const title = this.newCardTitle.trim();
      if (!title) return;

      const list = this.lists.find(l => l.id === listId);
      if (!list) return;

      const teamLabel = this.newCardTeam || null;

      const uname = (this.currentUser?.username || '').toLowerCase().trim();
      const name = (this.currentUser?.name || '').toLowerCase().trim();
      let userDefaultTeam = null;
      if (uname.includes('kim') || name.includes('kim') || this.currentUser?.id === 13) {
        userDefaultTeam = 'B';
      } else if (uname.includes('dara') || name.includes('dara') || this.currentUser?.id === 12) {
        userDefaultTeam = 'A';
      } else if (this.currentUser?.team) {
        userDefaultTeam = this.currentUser.team;
      }

      const assignedTeam = (this.isWorkflowBoard() ? this.getBoardTeam() : null) || this.newCardAssignedTeam || userDefaultTeam || this.filterTeam || null;

      // Optimistic Create
      const tempId = 'temp-' + Date.now();
      const tempCard = {
        id: tempId,
        title: title,
        team: assignedTeam || null,
        smm_team_label: teamLabel,
        priority: 'medium',
        due_at: null,
        labels: [],
        assignees: [],
        creator: this.currentUser,
        created_by: this.currentUser?.id,
        checklist_total: 0,
        checklist_done: 0,
        has_files: false,
        comment_count: 0,
      };
      list.cards.push(tempCard);
      
      this.newCardTitle = '';
      this.newCardTeam = null;
      this.newCardAssignedTeam = null;
      this.addingCardListId = null;

      // Background Sync
      const payload = {
        board_list_id: listId,
        title,
      };
      if (teamLabel) {
        payload.smm_team_label = teamLabel;
      }
      if (assignedTeam) {
        payload.team = assignedTeam;
      }

      const res = await this.api(`/boards/${this.boardSlug}/cards`, 'POST', payload);

      if (res.card) {
        const idx = list.cards.findIndex(c => c.id === tempId);
        if (idx !== -1) {
          list.cards[idx] = res.card;
        }
        const syncedTeam = res.card.smm_team_label || teamLabel;
        window.showToast(syncedTeam ? `Card "${title}" added & synced to ${syncedTeam}!` : `Card "${title}" added!`);
      }
    },

    // ── Card detail modal ────────────────────────────────────────────────────
    async openCard(cardId) {
      if (this.realtimeDragging || this.justDroppedCard || this.justOpenedCtx) {
        return;
      }
      if (String(cardId).startsWith('temp-')) {
          window.showToast('Card is still saving, please wait a moment.', 'info');
          return;
      }

      this.newComment     = '';
      this.cardActivities = [];
      this.isEditingDesc  = false;

      // Optimistic Open: Instantly show whatever data we already have from the board
      let foundCard = null;
      for (const l of this.lists) {
        foundCard = l.cards.find(c => c.id == cardId);
        if (foundCard) break;
      }
      
      if (foundCard) {
        // Deep clone so we don't modify the list version directly until needed, but keep ALL properties
        this.activeCard = JSON.parse(JSON.stringify(foundCard));
        this.cardActivities = foundCard.activities || [];
        this.cardLoading = false; // Show instantly
      } else {
        this.activeCard = { id: cardId, title: 'Loading...', loading: true };
        this.cardLoading = true;
      }

      // Background Fetch for full details (comments, activities, etc.)
      const res = await this.api(`/boards/cards/${cardId}`, 'GET');
      if (res.card) {
        if (!this.activeCard || this.activeCard.loading) {
          this.activeCard = res.card;
        } else {
          // Mutate properties to avoid full DOM redraw / blink
          Object.assign(this.activeCard, res.card);
        }
        this.cardActivities = res.activities || [];
      } else {
        if (this.activeCard?.loading) {
          this.activeCard = null;
        }
      }
      this.cardLoading = false;
    },

    // Silently refresh only the activity log and comments — no flicker
    async refreshActiveCard() {
      if (!this.activeCard) return;
      try {
        const res = await this.api(`/boards/cards/${this.activeCard.id}`, 'GET', null, { silentErrors: true });
        if (res.card && JSON.stringify(this.activeCard) !== JSON.stringify(res.card)) {
          Object.assign(this.activeCard, res.card);
        }
        if (res.activities && JSON.stringify(this.cardActivities) !== JSON.stringify(res.activities)) {
          this.cardActivities = res.activities;
        }
      } catch (_) {}
    },

    async refreshCardActivities() {
      if (!this.activeCard) return;
      try {
        const res = await this.api(`/boards/cards/${this.activeCard.id}`, 'GET');
        if (res.activities) this.cardActivities = res.activities;
        if (res.card?.comments) {
          this.activeCard.comments = res.card.comments;
        }
      } catch (_) {}
    },

    closeCard() {
      if (this.videoPreview && this.videoPreview.open) {
        this.closeVideoPreview();
      }
      if (this.canvaPreview && this.canvaPreview.open) {
        this.closeCanvaPreview();
      }
      this.activeCard = null;
    },

    // ── Card modal navigation (Prev / Next) ──────────────────────────────────
    getActiveListCards() {
      if (!this.activeCard) return [];
      const listId = this.activeCard.board_list_id;
      let list = this.lists.find(l => l.id == listId);
      if (!list) {
        list = this.lists.find(l => (l.cards || []).some(c => c.id == this.activeCard.id));
      }
      return list ? (list.cards || []) : [];
    },

    getActiveCardIndex() {
      if (!this.activeCard) return -1;
      const cards = this.getActiveListCards();
      return cards.findIndex(c => c.id == this.activeCard.id);
    },

    totalCardsInActiveList() {
      return this.getActiveListCards().length;
    },

    hasPrevCard() {
      return this.totalCardsInActiveList() > 1;
    },

    hasNextCard() {
      return this.totalCardsInActiveList() > 1;
    },

    getPrevCard() {
      const cards = this.getActiveListCards();
      if (cards.length <= 1) return null;
      const currentIndex = this.getActiveCardIndex();
      if (currentIndex === -1) return null;
      const prevIndex = currentIndex === 0 ? cards.length - 1 : currentIndex - 1;
      return cards[prevIndex] || null;
    },

    getNextCard() {
      const cards = this.getActiveListCards();
      if (cards.length <= 1) return null;
      const currentIndex = this.getActiveCardIndex();
      if (currentIndex === -1) return null;
      const nextIndex = currentIndex === cards.length - 1 ? 0 : currentIndex + 1;
      return cards[nextIndex] || null;
    },

    async prevCard() {
      const prev = this.getPrevCard();
      if (prev) {
        await this.openCard(prev.id);
      }
    },

    async nextCard() {
      const next = this.getNextCard();
      if (next) {
        await this.openCard(next.id);
      }
    },

    handleCardModalKeydown(event) {
      if (!this.activeCard) return;
      const tag = (event.target?.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || tag === 'select' || event.target?.isContentEditable) {
        return;
      }
      if (this.imagePreview?.open || this.attachmentModal?.open || this.exportModal?.open || this.switchBoardsModal?.open || this.importModal?.open || this.cardTransferModal?.open) {
        return;
      }
      if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        event.preventDefault();
        this.prevCard();
      } else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        event.preventDefault();
        this.nextCard();
      }
    },

    formatActivityDescription(desc) {
      if (!desc) return '';
      let formatted = desc.replace(/\bbulk\s+copied\b/gi, 'copied').replace(/\bbulk\s+moved\b/gi, 'moved');
      formatted = formatted.replace(/^copied\s+from\s+card\b/i, 'copied this card from');
      formatted = formatted.replace(/^Copied\s+From\s+Card\b/, 'copied this card from');
      return formatted;
    },

    async updateCardField(fields) {
      if (!this.activeCard) return;
      const res = await this.api(`/boards/cards/${this.activeCard.id}`, 'PATCH', fields);
      if (res.card) {
        if (res.card_moved) {
          window.showToast('Card moved by automation!');
          const newCard = res.card;
          if (parseInt(newCard.board_id, 10) !== parseInt(this.boardId, 10)) {
            this.lists.forEach(l => l.cards = l.cards.filter(c => parseInt(c.id, 10) !== parseInt(this.activeCard.id, 10)));
            this.closeCard();
            return;
          } else if (parseInt(newCard.board_list_id, 10) !== parseInt(this.activeCard.board_list_id, 10)) {
            this.lists.forEach(l => l.cards = l.cards.filter(c => parseInt(c.id, 10) !== parseInt(this.activeCard.id, 10)));
            const targetList = this.lists.find(l => parseInt(l.id, 10) === parseInt(newCard.board_list_id, 10));
            if (targetList) targetList.cards.unshift(newCard);
            this.closeCard();
            return;
          }
        }

        this.lists.forEach(l => {
          const c = l.cards.find(x => x.id === this.activeCard.id);
          if (c) Object.assign(c, res.card);
          if (res.card.sync_group_id && res.card.team !== undefined) {
            l.cards.filter(x => x.sync_group_id === res.card.sync_group_id && x.id !== res.card.id).forEach(sib => {
              sib.team = res.card.team;
            });
          }
        });
        Object.assign(this.activeCard, res.card);
        window.showToast('Card updated successfully!');
        this.refreshCardActivities();
      }
    },

    async setCardTeam(teamVal) {
      if (!this.activeCard) return;
      let normalized = null;
      if (teamVal) {
        const t = String(teamVal).toUpperCase().trim();
        if (t === 'BOTH' || t === 'ALL' || t === 'A,B' || t === 'A, B' || t === 'A&B' || t === 'A & B' || t === 'A+B' || (t.includes('A') && t.includes('B'))) {
          normalized = 'Both';
        } else if (t === 'A' || t === 'B') {
          normalized = t;
        } else {
          normalized = teamVal;
        }
      }

      // Optimistic update in memory
      this.activeCard.team = normalized;
      const cardId = this.activeCard.id;
      const syncGroupId = this.activeCard.sync_group_id;

      this.lists.forEach(l => {
        l.cards.forEach(c => {
          if (c.id === cardId || (syncGroupId && c.sync_group_id === syncGroupId)) {
            c.team = normalized;
          }
        });
      });

      await this.updateCardField({ team: normalized });
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
    openMemberPicker(card, mode = 'assignee') {
      if (!card) return;
      const mp       = this.memberPicker;
      mp.cardId      = card.id;
      mp.mode        = mode;
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
      const mp = this.memberPicker;

      if (mp.mode === 'assignBy') {
        const creatorId = card.created_by || card.creator?.id;
        const assignedMembers = this.allSystemMembers.filter(u => u.id === creatorId);
        mp.cardMembers = assignedMembers.map(a => ({
          id:       a.id,
          name:     a.name,
          email:    a.email || '',
          avatar:   a.avatar || a.avatar_url || '',
          initials: a.avatar_initials || a.initials || this.avatarInitials(a),
          avatar_color: a.avatar_color || this.avatarColor(a),
        }));
        mp.boardMembers = this.allSystemMembers
          .filter(u => u.id !== creatorId)
          .map(u => ({ ...u }));
        mp.workspaceMembers = [];
        return;
      }

      const assigneeIds   = new Set((card.assignees || []).map(a => a.id));

      // Currently assigned to this card (show first, with check mark)
      mp.cardMembers = (card.assignees || []).map(a => ({
        id:       a.id,
        name:     a.name,
        email:    a.email || '',
        avatar:   a.avatar || a.avatar_url || '',
        initials: a.avatar_initials || a.initials || this.avatarInitials(a),
        avatar_color: a.avatar_color || this.avatarColor(a),
      }));

      // Determine available pool: On SMM boards or synced cards, allow assigning from all system members (Graphic, Video, etc.)
      const isSmmOrSynced = (this.board?.name || '').toLowerCase().includes('smm') ||
                            this.board?.type === 'smm' ||
                            Boolean(card.smm_team_label) ||
                            Boolean(card.sync_group_id);

      let pool = [...(this.allBoardMembers || [])];
      if (isSmmOrSynced && Array.isArray(this.allSystemMembers) && this.allSystemMembers.length) {
        const seenIds = new Set(pool.map(u => u.id));
        this.allSystemMembers.forEach(u => {
          if (!seenIds.has(u.id)) {
            seenIds.add(u.id);
            pool.push(u);
          }
        });
      }

      // Members not yet on the card
      mp.boardMembers = pool
        .filter(u => !assigneeIds.has(u.id))
        .map(u => ({ ...u }));

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
      const match = u => (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q) || (u.username || '').toLowerCase().includes(q);
      const assigneeIds = new Set((card.assignees || []).map(a => a.id));

      const isSmmOrSynced = (this.board?.name || '').toLowerCase().includes('smm') ||
                            this.board?.type === 'smm' ||
                            Boolean(card.smm_team_label) ||
                            Boolean(card.sync_group_id);

      let pool = [...(this.allBoardMembers || [])];
      if (isSmmOrSynced && Array.isArray(this.allSystemMembers) && this.allSystemMembers.length) {
        const seenIds = new Set(pool.map(u => u.id));
        this.allSystemMembers.forEach(u => {
          if (!seenIds.has(u.id)) {
            seenIds.add(u.id);
            pool.push(u);
          }
        });
      }

      mp.cardMembers      = (card.assignees || []).filter(a => match({ name: a.name, email: a.email || '', username: a.username || '' }))
        .map(a => ({ ...a, initials: a.avatar_initials || a.initials || this.avatarInitials(a), avatar_color: a.avatar_color || this.avatarColor(a) }));
      mp.boardMembers     = pool.filter(u => !assigneeIds.has(u.id) && match(u));
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
          const serverPool = [
            ...(data.board_members || []),
            ...(data.workspace_members || []),
            ...(data.other_members || [])
          ];
          const seen = new Set();
          mp.boardMembers = serverPool.filter(u => {
            if (assigneeIds.has(u.id) || seen.has(u.id)) return false;
            seen.add(u.id);
            return true;
          });
          mp.workspaceMembers = [];
        } finally {
          mp.loading = false;
        }
      }
    },

    async mpToggleMember(user) {
      const card = this.activeCard;
      if (!card) return;

      if (this.memberPicker.mode === 'assignBy') {
        // Optimistic UI for Assign By
        const isAlreadyCreator = (card.created_by === user.id) || (card.creator?.id === user.id);
        const newCreatorId = isAlreadyCreator ? null : user.id;
        const newCreator = isAlreadyCreator ? null : user;

        // Auto set team if kim or dara
        const uUname = (user?.username || '').toLowerCase().trim();
        const uName = (user?.name || '').toLowerCase().trim();
        let autoTeam = null;
        if (uUname.includes('kim') || uName.includes('kim') || user?.id === 13) {
          autoTeam = 'B';
        } else if (uUname.includes('dara') || uName.includes('dara') || user?.id === 12) {
          autoTeam = 'A';
        }
        if (autoTeam && !this.isWorkflowBoard() && !this.isCardBothTeams(card)) {
          card.team = autoTeam;
        }

        this.lists.forEach(l => {
          const c = l.cards.find(x => x.id === card.id);
          if (c) {
            c.created_by = newCreatorId;
            c.creator = newCreator;
            if (autoTeam && !this.isWorkflowBoard() && !this.isCardBothTeams(c)) c.team = autoTeam;
          }
        });
        this.mpSeparateMembers(card);
        this.closeMemberPicker();

        // Background update
        const patchPayload = { created_by: newCreatorId };
        if (autoTeam && !this.isWorkflowBoard() && !this.isCardBothTeams(card)) {
          patchPayload.team = autoTeam;
        }
        const res = await this.api(`/boards/cards/${card.id}`, 'PATCH', patchPayload);
        if (res.card) {
          window.showToast("Assign By updated");
          this.refreshCardActivities();
        }
        return;
      }

      const isAssigned = (card.assignees || []).some(a => a.id === user.id);
      
      // Optimistic UI Update (Instant)
      if (isAssigned) {
        card.assignees = card.assignees.filter(a => a.id !== user.id);
      } else {
        card.assignees.push(user);
      }
      this.lists.forEach(l => {
        const c = l.cards.find(x => x.id === card.id);
        if (c) c.assignees = card.assignees;
      });
      this.mpSeparateMembers(card); // Re-render picker instantly

      // Background API Request
      const res = await this.api(`/boards/cards/${card.id}/members`, 'POST', { user_id: user.id });

      if (res.assignees !== undefined) {
        // Update to confirm state from server
        card.assignees = res.assignees;
        this.lists.forEach(l => {
          const c = l.cards.find(x => x.id === card.id);
          if (c) c.assignees = res.assignees;
        });
        this.mpSeparateMembers(card);
        this.loadBoardMembers();
        window.showToast(res.message || (isAssigned ? 'Member removed.' : 'Member added.'));
        this.refreshCardActivities();
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

    async toggleSmmClass(className) {
      if (!this.activeCard) return;
      const card = this.activeCard;
      const val = card.smm_class_label === className ? null : className;
      card.smm_class_label = val;
      
      this.lists.forEach(l => {
        const c = l.cards.find(x => x.id === card.id);
        if (c) c.smm_class_label = val;
      });
      
      await this.api(`/boards/cards/${card.id}`, 'PATCH', { smm_class_label: val }).catch(() => {});
    },

    async setSmmTeam(teamName) {
      if (!this.activeCard) return;
      const card = this.activeCard;
      const val = card.smm_team_label === teamName ? null : teamName;
      card.smm_team_label = val;

      this.lists.forEach(l => {
        const c = l.cards.find(x => x.id === card.id);
        if (c) c.smm_team_label = val;
      });

      const res = await this.api(`/boards/cards/${card.id}`, 'PATCH', { smm_team_label: val }).catch(() => null);
      if (res && res.card) {
        if (res.card.labels) {
          card.labels = res.card.labels;
          this.lists.forEach(l => {
            const c = l.cards.find(x => x.id === card.id);
            if (c) c.labels = res.card.labels;
          });
        }
      }
      window.showToast(val ? `Team set to ${val} (Synced to team planning board)` : 'Team label cleared');
      this.refreshCardActivities();
    },

    isSmmCard(card) {
      if (!card) return false;
      const hasSmmLabel = Array.isArray(card.labels) && card.labels.some(l => (l.name || '').trim().toLowerCase() === 'smm');
      if (hasSmmLabel) return true;
      const isSmmBoard = (this.board?.name || '').toLowerCase().includes('smm') || this.board?.type === 'smm' || this.board?.is_active_smm;
      return Boolean(isSmmBoard);
    },

    getContentTypeStyle(name) {
      if (!name) return { bg: '#f1f5f9', text: '#475569', border: '#e2e8f0', dot: '#94a3b8' };
      const lower = name.toLowerCase().trim();
      const found = (this.smmContentTypes || []).find(t => t.name.toLowerCase() === lower);
      if (found) return found;
      return { bg: '#e0e7ff', text: '#3730a3', border: '#c7d2fe', dot: '#6366f1' };
    },

    getContentTypeBadgeStyle(name) {
      const s = this.getContentTypeStyle(name);
      return `background-color: ${s.bg}; color: ${s.text}; border: 1px solid ${s.border};`;
    },

    async setSmmContentType(typeName) {
      if (!this.activeCard) return;
      const card = this.activeCard;
      const val = card.smm_cluster_label === typeName ? null : (typeName ? typeName.trim() : null);
      card.smm_cluster_label = val;

      this.lists.forEach(l => {
        const c = l.cards.find(x => x.id === card.id);
        if (c) c.smm_cluster_label = val;
      });

      const res = await this.api(`/boards/cards/${card.id}`, 'PATCH', { smm_cluster_label: val }).catch(() => null);
      if (res && res.card) {
        card.smm_cluster_label = res.card.smm_cluster_label;
        this.lists.forEach(l => {
          const c = l.cards.find(x => x.id === card.id);
          if (c) c.smm_cluster_label = res.card.smm_cluster_label;
        });
      }
      window.showToast(val ? `Content Type set to "${val}"` : 'Content Type cleared');
      if (typeof this.refreshCardActivities === 'function') {
        this.refreshCardActivities();
      }
    },

    async createSmmClass(name) {
      if (!name.trim()) return;
      const res = await this.api('/smm/classes/quick-create', 'POST', { name: name.trim() }).catch(() => null);
      if (res && res.smm_class) {
        if (!this.smmClasses) this.smmClasses = [];
        this.smmClasses.push(res.smm_class);
        this.toggleSmmClass(res.smm_class.name);
        window.showToast('SMM Class created!');
      } else {
        window.showToast('Failed to create SMM Class');
      }
    },

    async updatePublicDate(card, dateStr) {
      if (!card) return;
      const val = dateStr || null;
      card.content_public_date = val;
      
      this.lists.forEach(l => {
        const c = l.cards.find(x => x.id === card.id);
        if (c) c.content_public_date = val;
      });
      
      await this.api(`/boards/cards/${card.id}`, 'PATCH', { content_public_date: val }).catch(() => {});
      window.showToast('Public date updated!');
    },

    async toggleLabel(labelId) {
      if (!this.activeCard) return;
      const card = this.activeCard;
      const lbl = this.labels.find(l => l.id === labelId);
      if (!lbl) return;

      const hasLabel = (card.labels || []).some(l => l.id === labelId);

      // Optimistic UI Update (Instant)
      if (hasLabel) {
        card.labels = card.labels.filter(l => l.id !== labelId);
      } else {
        card.labels.push(lbl);
      }
      this.lists.forEach(l => {
        const c = l.cards.find(x => x.id === card.id);
        if (c) c.labels = card.labels;
      });

      // Background Sync
      const res = await this.api(`/boards/cards/${card.id}/labels`, 'POST', { label_id: labelId });
      if (res && res.labels) {
        card.labels = res.labels;
        if (res.smm_team_label !== undefined) {
          card.smm_team_label = res.smm_team_label;
        }
        this.lists.forEach(l => {
          const c = l.cards.find(x => x.id === card.id);
          if (c) {
            c.labels = res.labels;
            if (res.smm_team_label !== undefined) {
              c.smm_team_label = res.smm_team_label;
            }
          }
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

      const tempId = 'temp-cl-' + Date.now();
      const fakeChecklist = { id: tempId, title: title, name: title, items: [] };
      if (!this.activeCard.checklists) this.activeCard.checklists = [];
      this.activeCard.checklists.push(fakeChecklist);
      window.showToast('Checklist added!');

      this.api(`/boards/cards/${this.activeCard.id}/checklists`, 'POST', { title }).then(res => {
        if (res.checklist) {
          const idx = this.activeCard.checklists.findIndex(c => c.id === tempId);
          if (idx !== -1) this.activeCard.checklists[idx] = res.checklist;
        }
      }).catch(() => {});
    },

    async deleteChecklist(cl) {
      if (!this.activeCard || !await window.confirmModal({
        title: 'Delete checklist?',
        message: `Delete checklist "<strong>${this.escapeHtml(cl.name || cl.title || 'Checklist')}</strong>"?`,
        confirmText: 'Delete checklist',
        tone: 'danger',
      })) return;
      
      const original = [...this.activeCard.checklists];
      this.activeCard.checklists = this.activeCard.checklists.filter(x => x.id !== cl.id);
      window.showToast('Checklist removed.');

      this.api(`/boards/cards/${this.activeCard.id}/checklists/${cl.id}`, 'DELETE')
        .catch(() => {
          this.activeCard.checklists = original;
          window.showToast?.('Failed to delete checklist', 'error');
        });
    },

    detectCategoryFromText(text) {
      if (!text) return null;
      const trimmed = text.trim();

      const videoPatterns = [
        /\bvideo\s+short\b/i,
        /\bshort\s+video\b/i,
        /\bvideo\s+landscape\b/i,
        /\blandscape\s+video\b/i,
        /\bvideo\s+content\b/i,
        /\bvideo\b/i,
        /\bshort\b/i,
        /\breels?\b/i
      ];

      const graphicPatterns = [
        /\bsocial\s+media\s+graphic\b/i,
        /\bgraphic\s+design\b/i,
        /\bposter\b/i,
        /\bgraphic\b/i,
        /\bdesign\b/i,
        /\bartwork\b/i,
        /\bbanner\b/i,
        /\bcreative\b/i
      ];

      for (const p of videoPatterns) {
        if (p.test(trimmed)) return 'video';
      }
      for (const p of graphicPatterns) {
        if (p.test(trimmed)) return 'graphic';
      }
      if (/\blistings?\b/i.test(trimmed)) return 'listing';
      if (/\bcontent\b/i.test(trimmed)) return 'content';

      return null;
    },

    // Fixed users for categories that are not tied to card members
    categoryUsernames: { listing: 'chhay', content: 'sreypich' },

    findMemberByUsername(username) {
      if (!username) return null;
      const target = String(username).toLowerCase();
      const pools = [
        this.activeCard?.assignees ?? [],
        this.allBoardMembers || [],
        this.allSystemMembers || [],
        this.allWorkspaceMembers || [],
      ];
      for (const pool of pools) {
        const exact = pool.find(m => String(m.username || '').toLowerCase() === target);
        if (exact) return exact;
      }
      for (const pool of pools) {
        const partial = pool.find(m => String(m.username || '').toLowerCase().includes(target));
        if (partial) return partial;
      }
      return null;
    },

    isVideoMember(user) {
      if (!user) return false;
      const name = String(user.name || '').toLowerCase();
      const username = String(user.username || '').toLowerCase();
      const videoNames = ['samnang', 'nalin', 'sarak'];
      return videoNames.some(vn => name.includes(vn) || username.includes(vn));
    },

    isGraphicMember(user) {
      if (!user) return false;
      const name = String(user.name || '').toLowerCase();
      const username = String(user.username || '').toLowerCase();
      const graphicNames = ['vouchky', 'pich', 'sor', 'kim'];
      return graphicNames.some(gn => {
        if (gn === 'sor') {
          return name.includes('sopor') || username.includes('sopor') || /\bsor\b/i.test(name) || /\bsor\b/i.test(username) || name.startsWith('sor');
        }
        if (gn === 'pich') {
          return name.includes('pich') || name.includes('sreypich') || username.includes('pich');
        }
        return name.includes(gn) || username.includes(gn);
      });
    },

    detectMemberForChecklist(text, card = this.activeCard) {
      const cat = this.detectCategoryFromText(text);
      if (!cat) return null;
      if (this.categoryUsernames[cat]) {
        return this.findMemberByUsername(this.categoryUsernames[cat]);
      }
      const cardMembers = card?.assignees ?? [];
      const matching = cardMembers.filter(m => cat === 'video' ? this.isVideoMember(m) : this.isGraphicMember(m));
      if (matching.length === 1) {
        return matching[0];
      }
      return null;
    },

    getChecklistItemUsers(item) {
      if (!item) return [];
      if (Array.isArray(item.assigned_users) && item.assigned_users.length > 0) {
        return item.assigned_users;
      }
      if (item.assigned_user) {
        return [item.assigned_user];
      }
      if (item.assigned_user_id) {
        const cardMembers = this.activeCard?.assignees ?? [];
        const found = cardMembers.find(m => m.id == item.assigned_user_id)
          || (this.allBoardMembers || []).find(m => m.id == item.assigned_user_id)
          || (this.allSystemMembers || []).find(m => m.id == item.assigned_user_id);
        if (found) return [found];
        if (item.assignedUser) return [item.assignedUser];
      }

      // Auto-detect fallback from keyword & card members
      const title = String(item.content || item.title || '').toLowerCase().trim();
      if (title) {
        const cardMembers = this.activeCard?.assignees ?? [];
        // Listing / Description -> Chhay
        if (/\b(listings?|descriptions?|desc)\b/i.test(title)) {
          const chhay = cardMembers.find(m => /chhay/i.test(m.name || '') || /chhay/i.test(m.username || ''))
            || (this.allBoardMembers || []).find(m => /chhay/i.test(m.name || '') || /chhay/i.test(m.username || ''))
            || (this.allSystemMembers || []).find(m => /chhay/i.test(m.name || '') || /chhay/i.test(m.username || ''));
          if (chhay) return [chhay];
        }
        // Content -> Sreypich
        if (/\bcontent\b/i.test(title) && !/\bvideo\s+content\b/i.test(title)) {
          const sreypich = cardMembers.find(m => /sreypich/i.test(m.name || '') || /sreypich/i.test(m.username || ''))
            || (this.allBoardMembers || []).find(m => /sreypich/i.test(m.name || '') || /sreypich/i.test(m.username || ''))
            || (this.allSystemMembers || []).find(m => /sreypich/i.test(m.name || '') || /sreypich/i.test(m.username || ''));
          if (sreypich) return [sreypich];
        }
        // Graphic -> Graphic member
        if (/\b(graphic|poster|design|artwork|banner|creative)\b/i.test(title)) {
          const graphicUsers = cardMembers.filter(m => /vouchky|pich|sor|sopor|kim/i.test(m.name || '') || /vouchky|pich|sor|sopor|kim/i.test(m.username || ''));
          if (graphicUsers.length === 1) return [graphicUsers[0]];
        }
        // Video -> Video member
        if (/\b(video|short|reel)\b/i.test(title)) {
          const videoUsers = cardMembers.filter(m => /samnang|nalin|sarak/i.test(m.name || '') || /samnang|nalin|sarak/i.test(m.username || ''));
          if (videoUsers.length === 1) return [videoUsers[0]];
        }
      }
      return [];
    },

    getChecklistItemAssigneeIds(item) {
      return this.getChecklistItemUsers(item).map(u => Number(u.id));
    },

    // An assigned item can only be ticked by its assignee(s), or by dara / kim / somalika.
    canTickChecklistItem(item) {
      const ids = this.getChecklistItemAssigneeIds(item);
      if (!ids.length) return true;
      if (this.currentUser?.can_override_tick) return true;
      return ids.includes(Number(this.currentUserId));
    },

    checklistTickTitle(item) {
      if (this.canTickChecklistItem(item)) return '';
      const names = this.getChecklistItemUsers(item).map(u => u.name).filter(Boolean).join(', ');
      return `Assigned to ${names || 'another member'} — only they, dara, kim or somalika can tick this`;
    },

    openAddChecklistItemModal(cl) {
      if (!this.activeCard) return;
      this.checklistItemModal = {
        open: true,
        mode: 'add',
        checklist: cl,
        item: null,
        title: '',
        assignedUserIds: [],
        manuallySelected: false,
        detectedCategory: null,
        detectedMembers: [],
      };
      this.$nextTick(() => {
        const input = document.getElementById('checklist-item-modal-input');
        if (input) input.focus();
      });
    },

    openEditChecklistItemModal(cl, item) {
      if (!this.activeCard) return;
      this.checklistItemModal = {
        open: true,
        mode: 'edit',
        checklist: cl,
        item: item,
        title: item.title || item.content || '',
        assignedUserIds: this.getChecklistItemAssigneeIds(item),
        manuallySelected: true,
        detectedCategory: null,
        detectedMembers: [],
      };
      this.$nextTick(() => {
        const input = document.getElementById('checklist-item-modal-input');
        if (input) {
          input.focus();
          input.select();
        }
      });
    },

    closeChecklistItemModal() {
      this.checklistItemModal.open = false;
    },

    onChecklistItemTitleInput() {
      if (this.checklistItemModal.manuallySelected) {
        return;
      }
      const cat = this.detectCategoryFromText(this.checklistItemModal.title);
      this.checklistItemModal.detectedCategory = cat;
      if (!cat) {
        this.checklistItemModal.detectedMembers = [];
        this.checklistItemModal.assignedUserIds = [];
        return;
      }
      if (this.categoryUsernames[cat]) {
        const special = this.findMemberByUsername(this.categoryUsernames[cat]);
        this.checklistItemModal.detectedMembers = special ? [special] : [];
        this.checklistItemModal.assignedUserIds = special ? [Number(special.id)] : [];
        return;
      }
      const cardMembers = this.activeCard?.assignees ?? [];
      const matching = cardMembers.filter(m => cat === 'video' ? this.isVideoMember(m) : this.isGraphicMember(m));
      this.checklistItemModal.detectedMembers = matching;
      this.checklistItemModal.assignedUserIds = matching.length === 1 ? [Number(matching[0].id)] : [];
    },

    // Toggle one member in the multi-select list. Passing null clears everyone.
    selectChecklistUser(userId) {
      this.checklistItemModal.manuallySelected = true;
      if (!userId) {
        this.checklistItemModal.assignedUserIds = [];
        return;
      }
      const id = Number(userId);
      const ids = this.checklistItemModal.assignedUserIds || [];
      this.checklistItemModal.assignedUserIds = ids.includes(id) ? ids.filter(x => x !== id) : [...ids, id];
    },

    isChecklistUserSelected(userId) {
      return (this.checklistItemModal.assignedUserIds || []).includes(Number(userId));
    },

    findMemberById(id) {
      const cardMembers = this.activeCard?.assignees ?? [];
      return cardMembers.find(m => m.id == id)
        || (this.allBoardMembers || []).find(m => m.id == id)
        || (this.allSystemMembers || []).find(m => m.id == id)
        || null;
    },

    getSelectedChecklistUsers() {
      return (this.checklistItemModal.assignedUserIds || [])
        .map(id => this.findMemberById(id))
        .filter(Boolean);
    },

    getChecklistCardMembers(query = '') {
      const cardMembers = this.activeCard?.assignees ?? [];
      const q = (query || '').toLowerCase().trim();
      if (!q) return cardMembers;
      return cardMembers.filter(m => (m.name || '').toLowerCase().includes(q) || (m.email || '').toLowerCase().includes(q));
    },

    getChecklistBoardMembers(query = '') {
      const cardMemberIds = new Set((this.activeCard?.assignees ?? []).map(m => m.id));
      const boardMembers = (this.allBoardMembers || []).filter(m => !cardMemberIds.has(m.id));
      const q = (query || '').toLowerCase().trim();
      if (!q) return boardMembers;
      return boardMembers.filter(m => (m.name || '').toLowerCase().includes(q) || (m.email || '').toLowerCase().includes(q));
    },

    getAllChecklistEligibleMembers(query = '') {
      const cardMembers = this.activeCard?.assignees ?? [];
      const cardMemberIds = new Set(cardMembers.map(m => m.id));
      const boardMembers = (this.allBoardMembers || []).filter(m => !cardMemberIds.has(m.id));
      const boardMemberIds = new Set([...cardMemberIds, ...boardMembers.map(m => m.id)]);
      const systemMembers = (this.allSystemMembers || []).filter(m => !boardMemberIds.has(m.id));

      const all = [
        ...cardMembers.map(m => ({ ...m, _memberType: 'card' })),
        ...boardMembers.map(m => ({ ...m, _memberType: 'board' })),
        ...systemMembers.map(m => ({ ...m, _memberType: 'system' })),
      ];

      const q = (query || '').toLowerCase().trim();
      if (!q) return all;
      return all.filter(m =>
        (m.name || '').toLowerCase().includes(q) ||
        (m.username || '').toLowerCase().includes(q) ||
        (m.email || '').toLowerCase().includes(q)
      );
    },

    async submitChecklistItemModal() {
      const title = (this.checklistItemModal.title || '').trim();
      if (!title || !this.activeCard) return;
      const cl = this.checklistItemModal.checklist;
      if (!cl) return;

      const assignedUserIds = (this.checklistItemModal.assignedUserIds || []).map(Number);
      const assignedUsers = this.getSelectedChecklistUsers();
      const assignedUserId = assignedUserIds[0] ?? null;
      const assignedUser = assignedUsers[0] ?? null;
      const payloadUsers = (serverItem) => (
        Array.isArray(serverItem?.assigned_users) && serverItem.assigned_users.length
          ? serverItem.assigned_users
          : assignedUsers
      );

      if (this.checklistItemModal.mode === 'add') {
        const tempId = 'temp-item-' + Date.now();
        const fakeItem = {
          id: tempId,
          title: title,
          content: title,
          is_completed: false,
          assigned_user_id: assignedUserId,
          assigned_user_ids: assignedUserIds,
          assigned_user: assignedUser,
          assigned_users: assignedUsers,
        };
        if (!cl.items) cl.items = [];
        cl.items.push(fakeItem);
        this.updateCardChecklistProgress();
        this.closeChecklistItemModal();

        try {
          const postData = { title };
          if (assignedUserIds && assignedUserIds.length > 0) {
            postData.assigned_user_id = assignedUserId;
            postData.assigned_user_ids = assignedUserIds;
          }
          const res = await this.api(`/boards/cards/${this.activeCard.id}/checklists/${cl.id}/items`, 'POST', postData);
          if (res && res.item) {
            const idx = cl.items.findIndex(i => i.id === tempId);
            if (idx !== -1) {
              cl.items[idx] = {
                ...res.item,
                assigned_user: res.item.assigned_user || assignedUser,
                assigned_users: payloadUsers(res.item),
              };
            }
          }
        } catch (e) {
          window.showToast?.('Failed to add checklist item', 'error');
        }
      } else if (this.checklistItemModal.mode === 'edit') {
        const item = this.checklistItemModal.item;
        if (!item) return;
        const oldTitle = item.title || item.content;
        const oldUserId = item.assigned_user_id;
        const oldUsers = item.assigned_users;
        const oldUser = item.assigned_user;

        item.title = title;
        item.content = title;
        item.assigned_user_id = assignedUserId;
        item.assigned_user_ids = assignedUserIds;
        item.assigned_user = assignedUser;
        item.assigned_users = assignedUsers;
        this.closeChecklistItemModal();

        try {
          const res = await this.api(`/boards/cards/${this.activeCard.id}/checklists/${cl.id}/items/${item.id}`, 'PATCH', {
            title,
            assigned_user_id: assignedUserId,
            assigned_user_ids: assignedUserIds,
          });
          if (res && res.item) {
            Object.assign(item, res.item);
            item.assigned_user = res.item.assigned_user || assignedUser;
            item.assigned_users = payloadUsers(res.item);
          }
          window.showToast?.('Item updated.');
        } catch (e) {
          item.title = oldTitle;
          item.content = oldTitle;
          item.assigned_user_id = oldUserId;
          item.assigned_users = oldUsers;
          item.assigned_user = oldUser;
          window.showToast?.('Failed to update checklist item', 'error');
        }
      }
    },

    addChecklistItem(cl) {
      this.openAddChecklistItemModal(cl);
    },

    async toggleChecklistItem(cl, item, event = null) {
      if (!this.canTickChecklistItem(item)) {
        if (event?.target) event.target.checked = !!item.is_completed;
        window.showToast?.('This task is assigned to another member. Only the assignee, dara, kim or somalika can tick it.', 'error');
        return;
      }
      const previous = !!item.is_completed;
      item.is_completed = !previous;
      const res = await this.api(`/boards/cards/${this.activeCard.id}/checklists/${cl.id}/items/${item.id}`, 'PATCH');
      if (res.success) {
        this.updateCardChecklistProgress();
        // Silently refresh activities in background
        this.refreshCardActivities();
      } else {
        // Server refused (e.g. not the assignee) — revert the optimistic tick
        item.is_completed = previous;
        if (event?.target) event.target.checked = previous;
      }
    },

    // ── Checklist review marks (green tick / red cross / approved) ────────────
    canReviewMark() {
      return !!this.currentUser?.can_review_mark;
    },

    isHeadUser() {
      if (this.currentUser?.is_head !== undefined) return !!this.currentUser.is_head;
      if (this.currentUser?.is_dara_or_kim !== undefined) return !!this.currentUser.is_dara_or_kim;
      if (this.currentUser?.can_review_mark !== undefined) return !!this.currentUser.can_review_mark;
      const name = String(this.currentUser?.name || '').toLowerCase();
      const uname = String(this.currentUser?.username || '').toLowerCase();
      return name.includes('dara') || name.includes('kim') || uname.includes('dara') || uname.includes('kim');
    },

    canApproveChecklist() {
      return !!this.currentUser?.can_approve_checklist;
    },

    checklistReviewState(item) {
      if (item?.is_approved) return 'approved';
      if (item?.has_issue) return 'issue';
      if (item?.is_marked) return 'marked';
      return 'none';
    },

    getReviewUser(userId, fallbackUserObj) {
      if (fallbackUserObj && fallbackUserObj.name) return fallbackUserObj;
      if (!userId) return null;
      return this.boardMembers?.find(m => m.id === userId)
          || this.activeCard?.assignees?.find(a => a.id === userId)
          || (this.currentUser?.id === userId ? this.currentUser : null)
          || null;
    },

    async reviewChecklistItem(cl, item, action) {
      if (!this.activeCard || !item || String(item.id).startsWith('temp-')) return;
      if (action === 'approve' ? !this.canApproveChecklist() : !this.canReviewMark()) {
        window.showToast?.(action === 'approve'
          ? 'Only admins can approve checklist items.'
          : 'Only Production Team A & B can mark checklist items.', 'error');
        return;
      }
      const res = await this.api(`/boards/cards/${this.activeCard.id}/checklists/${cl.id}/items/${item.id}/review`, 'PATCH', { action });
      if (res && res.success && res.item) {
        item.is_marked = !!res.item.is_marked;
        item.has_issue = !!res.item.has_issue;
        item.is_approved = !!res.item.is_approved;
        item.marked_by = res.item.marked_by;
        item.issue_by = res.item.issue_by;
        item.approved_by = res.item.approved_by;
        item.marked_user = res.item.marked_user || null;
        item.issue_user = res.item.issue_user || null;
        this.refreshCardActivities?.();
      }
    },

    async deleteChecklistItem(cl, item) {
      if (!this.activeCard || !await window.confirmModal("Delete this checklist item?")) return;
      
      const original = [...cl.items];
      cl.items = cl.items.filter(x => x.id !== item.id);
      this.updateCardChecklistProgress();
      window.showToast('Item deleted.');

      this.api(`/boards/cards/${this.activeCard.id}/checklists/${cl.id}/items/${item.id}`, 'DELETE')
        .catch(() => {
          cl.items = original;
          this.updateCardChecklistProgress();
          window.showToast?.('Failed to delete item', 'error');
        });
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

    getCardChecklistProgress(card = this.activeCard) {
      if (!card) return null;
      let total = 0;
      let done = 0;

      if (Array.isArray(card.checklists) && card.checklists.length > 0) {
        total = card.checklists.reduce((acc, curr) => acc + (curr.items?.length || 0), 0);
        done = card.checklists.reduce((acc, curr) => acc + (curr.items?.filter(i => i.is_completed).length || 0), 0);
      } else if (card.checklist_total !== undefined) {
        total = card.checklist_total || 0;
        done = card.checklist_done || 0;
      }

      const percent = total > 0 ? Math.round((done / total) * 100) : 0;
      return {
        total,
        done,
        percent,
        hasChecklist: (Array.isArray(card.checklists) && card.checklists.length > 0) || (card.checklist_total ?? 0) > 0,
        isIncomplete: total > 0 && done < total
      };
    },

    isCardChecklistIncomplete(card = this.activeCard) {
      const progress = this.getCardChecklistProgress(card);
      return Boolean(progress && progress.hasChecklist && progress.isIncomplete);
    },

    showChecklistIncompleteModal(action = 'proceed', card = this.activeCard) {
      const info = this.getCardChecklistProgress(card);
      const actionPhrases = {
        'ready': 'marking this card as ready or copying it to the workflow board',
        'move': 'moving this card to another list or board',
        'copy': 'copying or duplicating this card',
        'automation': 'using comment automations to move or copy this card',
      };
      const actionText = actionPhrases[action] || 'marking this card as ready, moving, or copying it';

      const progressHtml = info && info.total > 0
        ? `<div class="mt-3.5 p-3 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-xs font-semibold text-amber-900 dark:text-amber-200 flex items-center justify-between">
            <span class="flex items-center gap-1.5">
              <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Checklist Progress:
            </span>
            <span class="font-bold text-amber-600 dark:text-amber-400 font-mono">${info.done} / ${info.total} completed (${info.percent}%)</span>
           </div>`
        : '';

      const modalPayload = {
        title: 'Checklist Incomplete',
        message: `<p class="text-sm font-semibold text-slate-800 dark:text-slate-100">All checklist items must be 100% completed before ${actionText}.</p>
                  <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Please complete all remaining tasks in the checklist to proceed.</p>
                  ${progressHtml}`,
        confirmText: 'Understood',
        tone: 'warning'
      };

      if (typeof window.alertModal === 'function') {
        return window.alertModal(modalPayload);
      } else if (typeof window.confirmModal === 'function') {
        return window.confirmModal(modalPayload);
      } else {
        alert('All checklist items must be 100% completed before ' + actionText + '.');
        return Promise.resolve(true);
      }
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
      this.updateSbmFilteredBoards();
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

    updateSbmFilteredBoards() {
      const s = this.switchBoardsModal.search.trim().toLowerCase();
      let boards = s ? this.sbmAllBoards() : this.sbmBoardsForTab(this.switchBoardsModal.tab);
      if (s) {
        boards = boards.filter(b => {
          const workspaceName = this.sbmBoardWorkspaceName(b).toLowerCase();
          return (b.name || '').toLowerCase().includes(s) || workspaceName.includes(s);
        });
      }

      this.switchBoardsModal.filteredBoards = this.sbmUniqueBoards(boards);
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
      const value = board?.background_value || '#ffffff';
      if (board?.background_type === 'image') {
        let safeUrl = String(value);
        if (safeUrl.includes('images.unsplash.com')) {
          safeUrl = safeUrl.replace(/w=\d+/, 'w=1920').replace(/q=\d+/, 'q=90');
          if (!safeUrl.includes('w=')) safeUrl += (safeUrl.includes('?') ? '&' : '?') + 'w=1920&q=90';
        }
        safeUrl = safeUrl.replace(/"/g, '\\"');
        return `background-image: linear-gradient(rgba(15,23,42,.12), rgba(15,23,42,.32)), url("${safeUrl}"); background-color: #0f172a; background-size: cover; background-position: center; image-rendering: -webkit-optimize-contrast;`;
      }

      const isNeon = (this.currentTheme === 'neon' || document.documentElement.getAttribute('data-theme') === 'neon');
      const isDark = (this.currentTheme === 'dark' || document.documentElement.getAttribute('data-theme') === 'dark' || document.documentElement.classList.contains('dark'));

      if (isNeon) {
        return `background: radial-gradient(ellipse at 45% -10%, rgba(0, 150, 255, 0.42) 0%, rgba(0, 70, 210, 0.22) 42%, transparent 70%), radial-gradient(ellipse at 85% 90%, rgba(0, 100, 255, 0.2) 0%, transparent 50%), radial-gradient(ellipse at 10% 90%, rgba(0, 50, 180, 0.15) 0%, transparent 50%), #020819; background-size: cover;`;
      }
      if (isDark) {
        return `background: #0b1329; background-size: cover;`;
      }

      return `background: ${value};`;
    },

    sbmCoverStyle(board) {
      let type = board?.cover_type;
      let value = board?.cover_value;

      const name = String(board?.name || '').toLowerCase();
      const wsName = String(this.sbmBoardWorkspaceName(board) || '').toLowerCase();
      const isSmm = board?.type === 'smm' || board?.is_active_smm || name.includes('smm') || wsName.includes('social media');
      const isPlanning = !isSmm && (name.includes('planning') || board?.type === 'planning');
      const isTeamA = !isSmm && !isPlanning && (name.includes('team a') || name.includes('teama') || name.includes('team-a'));
      const isTeamB = !isSmm && !isPlanning && (name.includes('team b') || name.includes('teamb') || name.includes('team-b'));

      if (isSmm) {
        type = 'image';
        value = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/SMM.webp';
      } else if (isPlanning) {
        type = 'image';
        value = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/ChatGPT%20Image%20Oct%203%202026%2007_59_23%20AM.webp';
      } else if (isTeamA) {
        type = 'image';
        value = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/TeamA.webp';
      } else if (isTeamB) {
        type = 'image';
        value = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/B.webp';
      }

      type = type || board?.background_type || 'color';
      value = value || board?.background_value || '#0ea5e9';
      const isNeon = (this.currentTheme === 'neon' || document.documentElement.getAttribute('data-theme') === 'neon');

      if (type === 'image') {
        let safeUrl = String(value);
        if (safeUrl.includes('images.unsplash.com')) {
          safeUrl = safeUrl.replace(/w=\d+/g, 'w=1600').replace(/q=\d+/g, 'q=90').replace(/dpr=\d+/g, 'dpr=2');
          if (!safeUrl.includes('w=')) safeUrl += (safeUrl.includes('?') ? '&' : '?') + 'w=1600&q=90&dpr=2';
        }
        safeUrl = safeUrl.replace(/"/g, '\\"');
        return `background-image: url("${safeUrl}"); background-size: cover; background-position: center; background-repeat: no-repeat; image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges;`;
      }

      if (isNeon) {
        if (type === 'gradient' && value) {
          return `background: ${value}; background-size: cover;`;
        }
        if (type === 'color' && value) {
          return `background: radial-gradient(circle at 80% 20%, rgba(0, 242, 254, 0.45) 0%, transparent 60%), linear-gradient(135deg, ${value} 0%, #030e2e 100%); background-size: cover;`;
        }
        return `background: radial-gradient(circle at 80% 20%, rgba(0, 242, 254, 0.5) 0%, transparent 60%), linear-gradient(135deg, #0b1a4a 0%, #0044aa 50%, #00c3ff 100%); background-size: cover;`;
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
      
      const url = `/boards/${board.slug}`;
      
      if (window.Turbo) {
        window.Turbo.visit(url);
      } else {
        window.location.href = url;
      }
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
    
    handleCommentInput(e) {
      const val = this.newComment;
      const cursorPos = e.target.selectionStart;
      const textBeforeCursor = val.substring(0, cursorPos);
      const match = textBeforeCursor.match(/(?:^|\s)@([a-zA-Z0-9_.-]*)$/);
      
      if (match) {
        this.mentionState.show = true;
        this.mentionState.query = match[1].toLowerCase();
        this.mentionState.startPos = cursorPos - match[1].length - 1;
        
        const q = this.mentionState.query;
        this.mentionState.members = (this.allBoardMembers || []).filter(m => {
          if (!m) return false;
          return m.name?.toLowerCase().includes(q) || (m.username && m.username.toLowerCase().includes(q));
        }).slice(0, 5); // show max 5
        
        this.mentionState.selectedIndex = 0;
      } else {
        this.mentionState.show = false;
      }
    },
    
    handleCommentKeydown(e) {
      this.handleTextareaKeydown(e);
    },

    handleTextareaKeydown(e) {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'b') {
        e.preventDefault();
        this.insertMarkdownFormatting(e.target, '**');
        return;
      }
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'i') {
        e.preventDefault();
        this.insertMarkdownFormatting(e.target, '*');
        return;
      }
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'z') {
        e.preventDefault();
        if (e.shiftKey) {
          document.execCommand('redo');
        } else {
          document.execCommand('undo');
        }
        e.target.dispatchEvent(new Event('input', { bubbles: true }));
        return;
      }

      if (!this.mentionState.show) return;
      
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        this.mentionState.selectedIndex = (this.mentionState.selectedIndex + 1) % this.mentionState.members.length;
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        this.mentionState.selectedIndex = (this.mentionState.selectedIndex - 1 + this.mentionState.members.length) % this.mentionState.members.length;
      } else if (e.key === 'Enter' || e.key === 'Tab') {
        if (this.mentionState.members.length > 0) {
          e.preventDefault();
          this.insertMention(this.mentionState.members[this.mentionState.selectedIndex]);
        }
      } else if (e.key === 'Escape') {
        this.mentionState.show = false;
      }
    },

    insertMarkdownFormatting(textarea, syntax) {
      if (!textarea || typeof textarea.selectionStart !== 'number') return;
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const text = textarea.value;
      const selected = text.substring(start, end);
      
      const replacement = syntax + selected + syntax;
      textarea.focus();
      
      // Use execCommand so undo history is preserved
      if (!document.execCommand('insertText', false, replacement)) {
        // Fallback for browsers where execCommand is disabled
        textarea.setRangeText(replacement, start, end, 'select');
      }
      
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
      
      // Select the word inside formatting or place cursor inside
      if (selected.length > 0) {
        textarea.selectionStart = start + syntax.length;
        textarea.selectionEnd = end + syntax.length;
      } else {
        textarea.selectionStart = start + syntax.length;
        textarea.selectionEnd = start + syntax.length;
      }
    },
    
    insertMention(member) {
      if (!member) return;
      const val = this.newComment;
      const start = this.mentionState.startPos;
      const username = member.username || member.name.replace(/\s+/g, '');
      
      // We want to replace the typed query with @username + space
      this.newComment = val.substring(0, start) + '@' + username + ' ' + val.substring(start + this.mentionState.query.length + 1);
      this.mentionState.show = false;
      
      // Optionally focus back to textarea at correct position (requires $nextTick and refs, but good enough for now)
    },
    
    isCommentAutomation(body, card = this.activeCard) {
      if (!body) return false;
      const text = body.toLowerCase().trim();

      const workflowKeywords = [
        'ready',
        'caption ready',
        'production approved smm',
        'production approved',
        'qc approved smm',
        'qc approved',
        'approved smm',
        'team approved',
        'head approved',
        'supervisor approved',
        'approved',
        'blocked',
        'reject',
        'rejected',
        'block',
        'error',
      ];

      for (const kw of workflowKeywords) {
        if (kw === 'block' || kw === 'ready') {
          const re = new RegExp('\\b' + kw + '\\b', 'i');
          if (re.test(text)) return true;
        } else if (text.includes(kw)) {
          return true;
        }
      }

      if (Array.isArray(this.boardAutomations)) {
        for (const auto of this.boardAutomations) {
          if (['keyword', 'both'].includes(auto.trigger_type) && ['move', 'copy'].includes(auto.action_type) && auto.trigger_word) {
            const tw = auto.trigger_word.toLowerCase().trim();
            if (tw && text.includes(tw)) return true;
          }
        }
      }

      return false;
    },

    submitComment() {
      const body = this.newComment.trim();
      if (!body || !this.activeCard) return;

      if (this.isCardChecklistIncomplete(this.activeCard) && this.isCommentAutomation(body, this.activeCard)) {
        this.showChecklistIncompleteModal(/\bready\b/i.test(body) ? 'ready' : 'automation', this.activeCard);
        return;
      }

      const tempId = 'temp-' + Date.now();
      const newCommentVal = this.newComment;
      this.newComment = '';

      const fakeComment = {
        id: tempId,
        body: body,
        content: body,
        user_id: this.currentUser.id,
        created_at: new Date().toISOString(),
        user: {
          ...this.currentUser,
          name: this.currentUser.name || 'You',
          avatar: this.currentUser.avatar_url || this.currentUser.avatar || this.avatarUrl(this.currentUser) || '',
          avatar_initials: this.currentUser.initials || this.currentUser.avatar_initials || this.avatarInitials(this.currentUser) || '',
          avatar_color: this.currentUser.avatar_color || this.avatarColor(this.currentUser) || '#64748b'
        },
      };

      if (!this.activeCard.comments) this.activeCard.comments = [];
      this.activeCard.comments.push(fakeComment);

      this.lists.forEach(l => {
        const c = l.cards.find(x => x.id === this.activeCard.id);
        if (c) c.comment_count = (c.comment_count ?? 0) + 1;
      });

      this.api(`/boards/cards/${this.activeCard.id}/comments`, 'POST', { body: newCommentVal })
        .then(res => {
          if (res.comment) {
            const idx = this.activeCard.comments.findIndex(c => c.id === tempId);
            if (idx !== -1) this.activeCard.comments[idx] = res.comment;

            if (res.card_moved) {
              window.showToast('Card moved by automation!');
              const newCard = res.card;
              if (parseInt(newCard.board_id, 10) !== parseInt(this.boardId, 10)) {
                this.lists.forEach(l => l.cards = l.cards.filter(c => parseInt(c.id, 10) !== parseInt(this.activeCard.id, 10)));
                this.closeCard();
                return;
              } else if (parseInt(newCard.board_list_id, 10) !== parseInt(this.activeCard.board_list_id, 10)) {
                this.lists.forEach(l => l.cards = l.cards.filter(c => parseInt(c.id, 10) !== parseInt(this.activeCard.id, 10)));
                const targetList = this.lists.find(l => parseInt(l.id, 10) === parseInt(newCard.board_list_id, 10));
                if (targetList) targetList.cards.unshift(newCard);
                this.closeCard();
                return;
              }
            }
            // No need to refreshCardActivities() if we successfully swapped the comment optimistically
          } else {
            this.revertComment(tempId, newCommentVal);
          }
        }).catch((err) => {
          this.revertComment(tempId, newCommentVal);
          if (err && err.checklist_incomplete) {
            this.showChecklistIncompleteModal(/\bready\b/i.test(newCommentVal) ? 'ready' : 'automation', this.activeCard);
          } else {
            window.showToast?.(err?.error || err?.message || 'Failed to post comment', 'error');
          }
        });
    },

    revertComment(tempId, originalText) {
      if (!this.activeCard) return;
      this.activeCard.comments = this.activeCard.comments.filter(c => c.id !== tempId);
      this.lists.forEach(l => {
        const c = l.cards.find(x => x.id === this.activeCard.id);
        if (c) c.comment_count = Math.max(0, (c.comment_count ?? 1) - 1);
      });
      this.newComment = originalText;
      window.showToast?.('Failed to post comment', 'error');
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

    openAttachmentModal(card, defaultFolder = '') {
      if (!card) return;
      const am = this.attachmentModal;
      am.cardId         = card.id;
      am.tab            = 'file';
      am.dragOver       = false;
      am.uploading      = false;
      am.uploadProgress = 0;
      am.uploadCount    = 0;
      am.uploadStatusText = '';
      am.folderName     = defaultFolder || '';
      am.pendingFiles   = [];
      am.pendingRelativePaths = [];
      am.pendingFolderName   = defaultFolder || '';
      am.error          = '';
      am.linkUrl        = '';
      am.linkName       = '';
      am.open           = true;
    },

    closeAttachmentModal() {
      this.attachmentModal.open = false;
      this.attachmentModal.editingFileId = null;
      this.attachmentModal.folderName = '';
      this.attachmentModal.pendingFiles = [];
      this.attachmentModal.pendingRelativePaths = [];
      this.attachmentModal.pendingFolderName = '';
      this.attachmentModal.uploading = false;
      this.attachmentModal.error = '';
    },

    amClearPendingFiles() {
      this.attachmentModal.pendingFiles = [];
      this.attachmentModal.pendingRelativePaths = [];
      this.attachmentModal.pendingFolderName = '';
      this.attachmentModal.error = '';
    },

    amStageFiles(fileList, folderName = '', relativePaths = []) {
      const am = this.attachmentModal;
      const files = Array.from(fileList || []).filter(f => f && f.name && !f.name.startsWith('.') && f.name !== 'Thumbs.db');
      if (!files.length) {
        am.error = 'No valid files selected.';
        return;
      }

      // Pre-check size (25 MB per file)
      const MAX_SIZE = 25 * 1024 * 1024;
      for (const f of files) {
        if (f.size > MAX_SIZE) {
          am.error = `File "${f.name}" is too large: ${this.amFormatBytes(f.size)}. Maximum allowed is 25 MB.`;
          return;
        }
      }

      am.error = '';
      am.pendingFiles = files;
      am.pendingRelativePaths = (relativePaths && relativePaths.length) ? relativePaths : files.map(f => f.name);
      am.pendingFolderName = folderName ? folderName.trim() : '';

      if (folderName && !am.folderName) {
        am.folderName = folderName.trim();
      }
    },

    async amHandleDrop(event) {
      this.attachmentModal.dragOver = false;
      const items = event.dataTransfer?.items;
      const files = [];
      const relativePaths = [];
      let detectedFolderName = this.attachmentModal.folderName ? this.attachmentModal.folderName.trim() : '';

      if (items && items.length && items[0].webkitGetAsEntry) {
        const getFilesFromEntry = async (entry, path = '') => {
          if (entry.isFile) {
            return new Promise((resolve) => {
              entry.file((file) => {
                const rel = path ? `${path}/${file.name}` : file.name;
                files.push(file);
                relativePaths.push(rel);
                resolve();
              }, () => resolve());
            });
          } else if (entry.isDirectory) {
            if (!detectedFolderName && !path) {
              detectedFolderName = entry.name;
            }
            const currentPath = path ? `${path}/${entry.name}` : entry.name;
            const dirReader = entry.createReader();

            const readAllEntries = () => {
              return new Promise((resolve) => {
                const allEntries = [];
                const readBatch = () => {
                  dirReader.readEntries((entries) => {
                    if (!entries || !entries.length) {
                      resolve(allEntries);
                    } else {
                      allEntries.push(...entries);
                      readBatch();
                    }
                  }, () => resolve(allEntries));
                };
                readBatch();
              });
            };

            const childEntries = await readAllEntries();
            for (const child of childEntries) {
              await getFilesFromEntry(child, currentPath);
            }
          }
        };

        for (let i = 0; i < items.length; i++) {
          const entry = items[i].webkitGetAsEntry();
          if (entry) {
            await getFilesFromEntry(entry);
          }
        }
      }

      // Fallback if webkitGetAsEntry didn't yield files but dataTransfer.files exists
      if (!files.length && event.dataTransfer?.files?.length) {
        for (let i = 0; i < event.dataTransfer.files.length; i++) {
          const f = event.dataTransfer.files[i];
          files.push(f);
          relativePaths.push(f.name);
        }
      }

      if (files.length) {
        this.amStageFiles(files, detectedFolderName, relativePaths);
      }
    },

    amUploadFile(event) {
      this.amUploadFiles(event);
    },

    amUploadFiles(event) {
      const fileList = event.target.files;
      if (!fileList || !fileList.length) return;
      const files = Array.from(fileList);
      const relativePaths = files.map(f => f.name);
      this.amStageFiles(files, this.attachmentModal.folderName, relativePaths);
      event.target.value = '';
    },

    amUploadFolder(event) {
      const fileList = event.target.files;
      if (!fileList || !fileList.length) return;

      const files = Array.from(fileList);
      const relativePaths = [];
      let detectedFolderName = this.attachmentModal.folderName ? this.attachmentModal.folderName.trim() : '';

      files.forEach(f => {
        const rel = f.webkitRelativePath || f.name;
        relativePaths.push(rel);
        if (!detectedFolderName && rel.includes('/')) {
          detectedFolderName = rel.split('/')[0];
        }
      });

      if (!detectedFolderName) {
        detectedFolderName = 'Uploaded Folder';
      }

      this.amStageFiles(files, detectedFolderName, relativePaths);
      event.target.value = '';
    },

    handleDirectFolderUpload(event) {
      const fileList = event.target.files;
      if (!fileList || !fileList.length) return;

      const files = Array.from(fileList);
      const relativePaths = [];
      let detectedFolderName = '';

      files.forEach(f => {
        const rel = f.webkitRelativePath || f.name;
        relativePaths.push(rel);
        if (!detectedFolderName && rel.includes('/')) {
          detectedFolderName = rel.split('/')[0];
        }
      });

      if (!detectedFolderName) {
        detectedFolderName = 'Uploaded Folder';
      }

      this.openAttachmentModal(this.activeCard, detectedFolderName);
      this.amStageFiles(files, detectedFolderName, relativePaths);
      event.target.value = '';
    },

    amStartPendingUpload() {
      const am = this.attachmentModal;
      if (!am.pendingFiles || !am.pendingFiles.length) {
        am.error = 'Please select a folder or files above first.';
        return;
      }
      const folderName = (am.folderName || am.pendingFolderName || '').trim();
      this.amDoUploadFiles(am.pendingFiles, folderName, am.pendingRelativePaths);
    },

    openGroupFolderModal() {
      const card = this.activeCard;
      if (!card) return;

      const standalone = this.getStandaloneCardFiles(card.files);
      if (!standalone.length) {
        window.showToast?.('No loose files found to group into a folder.', 'info');
        return;
      }

      this.groupFolderModal.folderName = 'Photos';
      this.groupFolderModal.selectedFileIds = [];
      this.groupFolderModal.error = '';
      this.groupFolderModal.submitting = false;
      this.groupFolderModal.open = true;
    },

    closeGroupFolderModal() {
      this.groupFolderModal.open = false;
      this.groupFolderModal.selectedFileIds = [];
      this.groupFolderModal.error = '';
      this.groupFolderModal.submitting = false;
    },

    toggleGroupFileSelection(fileId) {
      const id = Number(fileId);
      const idx = this.groupFolderModal.selectedFileIds.indexOf(id);
      if (idx > -1) {
        this.groupFolderModal.selectedFileIds.splice(idx, 1);
      } else {
        this.groupFolderModal.selectedFileIds.push(id);
      }
      this.groupFolderModal.error = '';
    },

    isGroupFileSelected(fileId) {
      const id = Number(fileId);
      return this.groupFolderModal.selectedFileIds.includes(id);
    },

    toggleSelectAllGroupFiles() {
      const card = this.activeCard;
      if (!card) return;
      const standalone = this.getStandaloneCardFiles(card.files);
      if (this.groupFolderModal.selectedFileIds.length === standalone.length) {
        this.groupFolderModal.selectedFileIds = [];
      } else {
        this.groupFolderModal.selectedFileIds = standalone.map(f => Number(f.id));
      }
      this.groupFolderModal.error = '';
    },

    async submitGroupFilesToFolder() {
      const card = this.activeCard;
      if (!card) return;

      const trimmedName = (this.groupFolderModal.folderName || '').trim();
      if (!trimmedName) {
        this.groupFolderModal.error = 'Please enter a folder name.';
        return;
      }

      if (!this.groupFolderModal.selectedFileIds.length) {
        this.groupFolderModal.error = 'Please select at least one file below to group.';
        return;
      }

      this.groupFolderModal.submitting = true;
      this.groupFolderModal.error = '';

      try {
        const res = await fetch(`/boards/cards/${card.id}/folders/assign`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': this.csrfToken,
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            folder_name: trimmedName,
            file_ids: this.groupFolderModal.selectedFileIds,
          }),
        });

        const data = await res.json();
        if (res.ok && data.success) {
          if (data.files) {
            card.files = data.files;
          } else {
            const selectedSet = new Set(this.groupFolderModal.selectedFileIds);
            (card.files || []).forEach(f => {
              if (selectedSet.has(Number(f.id))) {
                f.folder_name = trimmedName;
                if (!f.original_name.startsWith(`${trimmedName}/`)) {
                  f.original_name = `${trimmedName}/${f.original_name}`;
                }
                f.display_name = f.original_name.split('/').pop();
              }
            });
          }
          const count = data.count || this.groupFolderModal.selectedFileIds.length;
          window.showToast?.(`Grouped ${count} file${count === 1 ? '' : 's'} into folder "${trimmedName}"! 📁`, 'success');
          this.closeGroupFolderModal();
          this.refreshCardActivities();
        } else {
          this.groupFolderModal.error = data.error || 'Failed to group files into folder.';
        }
      } catch (err) {
        console.error('Error grouping files into folder:', err);
        this.groupFolderModal.error = 'Network error grouping files. Please try again.';
      } finally {
        this.groupFolderModal.submitting = false;
      }
    },

    groupCardFilesIntoFolderPrompt() {
      this.openGroupFolderModal();
    },

    amDoUpload(file) {
      this.amDoUploadFiles([file], this.attachmentModal.folderName);
    },

    amDoUploadFiles(fileList, folderName = '', relativePaths = []) {
      const am   = this.attachmentModal;
      const card = this.activeCard;
      if (!card) return;

      const files = Array.from(fileList || []).filter(f => f && f.name && !f.name.startsWith('.') && f.name !== 'Thumbs.db');
      if (!files.length) {
        am.error = 'No valid files selected.';
        return;
      }

      // Client-side size check (25 MB per file)
      const MAX_SIZE = 25 * 1024 * 1024;
      for (const f of files) {
        if (f.size > MAX_SIZE) {
          am.error = `File "${f.name}" is too large: ${this.amFormatBytes(f.size)}. Maximum allowed is 25 MB.`;
          return;
        }
      }

      // Client-side MIME / extension pre-check
      const BLOCKED_EXTS = ['.html', '.htm', '.php', '.exe', '.sh', '.bat', '.cmd'];
      for (const f of files) {
        const lowerName = f.name.toLowerCase();
        if (BLOCKED_EXTS.some(ext => lowerName.endsWith(ext)) || f.type === 'text/html' || f.type === 'application/x-httpd-php') {
          am.error = `File "${f.name}" has a disallowed file type.`;
          return;
        }
      }

      am.error          = '';
      am.uploading      = true;
      am.uploadProgress = 0;
      am.uploadCount    = files.length;
      am.uploadStatusText = files.length > 1
        ? `Preparing ${files.length} files...`
        : `Preparing ${files[0].name}...`;

      const formData = new FormData();
      if (folderName) {
        formData.append('folder_name', folderName.trim());
      }

      files.forEach((file, index) => {
        formData.append('files[]', file);
        const rel = (relativePaths && relativePaths[index]) || file.relative_path || file.webkitRelativePath || file.name;
        if (rel) {
          formData.append('relative_paths[]', rel);
        }
      });

      const xhr = new XMLHttpRequest();
      xhr.open('POST', `/boards/cards/${card.id}/files`);
      xhr.setRequestHeader('X-CSRF-TOKEN', this.csrfToken);
      xhr.setRequestHeader('Accept', 'application/json');

      xhr.upload.addEventListener('progress', e => {
        if (e.lengthComputable) {
          am.uploadProgress = Math.min(100, Math.round((e.loaded / e.total) * 100));
          am.uploadStatusText = files.length > 1
            ? `Uploading ${files.length} items (${am.uploadProgress}%)...`
            : `Uploading (${am.uploadProgress}%)...`;
        }
      });

      xhr.addEventListener('load', () => {
        am.uploadProgress = 100;
        am.uploadStatusText = 'Complete! 100%';
        setTimeout(() => {
          am.uploading = false;
          try {
            const data = JSON.parse(xhr.responseText);
            if ((xhr.status === 200 || xhr.status === 201) && (data.files || data.file)) {
              if (!card.files) card.files = [];
              const newFiles = data.files || [data.file];
              newFiles.forEach(nf => {
                const idx = card.files.findIndex(x => x.id === nf.id);
                if (idx >= 0) card.files[idx] = nf;
                else card.files.push(nf);
              });

              this.lists.forEach(l => {
                const c = l.cards.find(x => x.id === card.id);
                if (c) c.has_files = true;
              });

              const successMsg = folderName
                ? `Uploaded folder "${folderName}" (${newFiles.length} items)! 📁`
                : (newFiles.length > 1 ? `Attached ${newFiles.length} files successfully! 📎` : 'File attached successfully! 📎');
              window.showToast?.(successMsg);
              this.refreshCardActivities();
              this.closeAttachmentModal();

              // Refresh Folder Viewer if open for this folder
              if (this.folderViewer.open && this.folderViewer.folderName === folderName) {
                this.folderViewer.files = (card.files || []).filter(f => {
                  const fFolder = this.getFolderOfFile(f);
                  return fFolder === folderName;
                });
              }
            } else {
              am.error = data.error || data.message || 'Upload failed. Please try again.';
            }
          } catch {
            am.error = 'Upload failed — invalid server response.';
          }
        }, 400);
      });

      xhr.addEventListener('error', () => {
        am.uploading = false;
        am.error = 'Network error during upload. Please try again.';
      });

      xhr.send(formData);
    },

    getFolderOfFile(f) {
      if (!f) return null;
      if (typeof f.folder_name === 'string' && f.folder_name.trim().length > 0) {
        return f.folder_name.trim();
      }
      if (typeof f.original_name === 'string' && /[\/\\]/.test(f.original_name)) {
        const parts = f.original_name.split(/[\/\\]/);
        if (parts[0] && parts[0].trim().length > 0) {
          return parts[0].trim();
        }
      }
      return null;
    },

    getFileBytes(f) {
      if (!f) return 0;
      let size = Number(f.size);
      if (size && !isNaN(size) && size > 0) return size;
      if (typeof f.formatted_size === 'string') {
        const match = f.formatted_size.trim().match(/^([\d.,]+)\s*([A-Za-z]+)?$/i);
        if (match) {
          const val = parseFloat(match[1].replace(/,/g, ''));
          const unit = (match[2] || 'B').toUpperCase();
          const multipliers = { B: 1, KB: 1024, MB: 1024 * 1024, GB: 1024 * 1024 * 1024, TB: 1024 * 1024 * 1024 * 1024 };
          return Math.round(val * (multipliers[unit] || 1));
        }
      }
      return 0;
    },

    getCardFolders(files) {
      if (!files || !files.length) return [];
      const foldersMap = {};

      files.forEach(f => {
        const folder = this.getFolderOfFile(f);
        if (!folder) return;

        if (!foldersMap[folder]) {
          foldersMap[folder] = {
            name: folder,
            files: [],
            totalBytes: 0,
            imageCount: 0,
          };
        }
        foldersMap[folder].files.push(f);
        foldersMap[folder].totalBytes += this.getFileBytes(f);
        if (f.is_image) foldersMap[folder].imageCount++;
      });

      return Object.values(foldersMap).map(f => {
        f.fileCount = f.files.length;
        f.formattedSize = this.amFormatBytes(f.totalBytes);
        f.coverImage = f.files.find(item => item.is_image) || null;
        return f;
      });
    },

    getStandaloneCardFiles(files) {
      if (!files || !files.length) return [];
      return files.filter(f => {
        const folder = this.getFolderOfFile(f);
        return !folder;
      });
    },

    openFolderViewer(folderName, files = null) {
      if (!folderName) return;
      const allFiles = this.activeCard?.files || [];
      const folderFiles = Array.isArray(files) && files.length
        ? files
        : allFiles.filter(f => {
            const fFolder = this.getFolderOfFile(f);
            return fFolder === folderName;
          });

      this.folderViewer.folderName = folderName;
      this.folderViewer.files = folderFiles;
      this.folderViewer.filter = 'all';
      this.folderViewer.open = true;
    },

    closeFolderViewer() {
      this.folderViewer.open = false;
      this.folderViewer.folderName = '';
      this.folderViewer.files = [];
    },

    downloadCardFolder(folderName) {
      if (!this.activeCard || !folderName) return;
      const url = `/boards/cards/${this.activeCard.id}/folders/${encodeURIComponent(folderName)}/download`;
      const a = document.createElement('a');
      a.href = url;
      a.download = `${folderName}.zip`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.showToast?.(`Downloading folder "${folderName}" as ZIP... 📦`);
    },

    async deleteCardFolder(folderName) {
      if (!this.activeCard || !folderName) return;
      const count = (this.activeCard.files || []).filter(f => {
        const fFolder = this.getFolderOfFile(f);
        return fFolder === folderName;
      }).length;

      const ok = await window.confirmModal({
        title: `Delete folder "${folderName}"?`,
        message: `This will permanently delete folder "<strong>${folderName}</strong>" and all <strong>${count} file(s)</strong> inside it.`,
        confirmText: 'Delete Folder',
        tone: 'danger',
      });
      if (!ok) return;

      const originalFiles = [...this.activeCard.files];
      this.activeCard.files = this.activeCard.files.filter(f => {
        const fFolder = this.getFolderOfFile(f);
        return fFolder !== folderName;
      });

      this.lists.forEach(l => {
        const c = l.cards.find(x => x.id === this.activeCard.id);
        if (c) c.has_files = this.activeCard.files.length > 0;
      });

      if (this.folderViewer.folderName === folderName) {
        this.closeFolderViewer();
      }

      window.showToast?.(`Folder "${folderName}" deleted.`);

      try {
        const resp = await fetch(`/boards/cards/${this.activeCard.id}/folders/${encodeURIComponent(folderName)}`, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': this.csrfToken,
            'Accept': 'application/json',
          },
        });
        const res = await resp.json();
        if (!res.success) {
          this.activeCard.files = originalFiles;
          window.showToast?.(res.error || 'Failed to delete folder', 'error');
        } else {
          this.refreshCardActivities();
        }
      } catch (err) {
        this.activeCard.files = originalFiles;
        window.showToast?.('Network error deleting folder', 'error');
      }
    },

    amSubmitLink() {
      const am   = this.attachmentModal;
      const card = this.activeCard;
      if (!am.linkUrl || !card) return;

      try { new URL(am.linkUrl); } catch {
        am.error = 'Please enter a valid URL (including https://).';
        return;
      }

      am.error = '';
      const tempId = 'temp-link-' + Date.now();
      const linkUrl = am.linkUrl;
      let linkName = am.linkName;
      if (!linkName) {
        if (this.isCanvaFile({ url: linkUrl })) {
          linkName = 'Canva Design';
        } else {
          linkName = linkUrl;
        }
      }

      const fakeFile = {
        id: tempId,
        original_name: linkName,
        path: linkUrl,
        url: linkUrl,
        disk: 'url',
        created_at: new Date().toISOString(),
        time_ago: 'Just now',
        is_link: true,
      };

      if (!card.files) card.files = [];
      card.files.push(fakeFile);

      this.lists.forEach(l => {
        const c = l.cards.find(x => x.id === card.id);
        if (c) c.has_files = true;
      });

      am.linkUrl  = '';
      am.linkName = '';
      this.closeAttachmentModal();
      window.showToast?.('Link attached!');

      this.api(`/boards/cards/${card.id}/files`, 'POST', {
        link_url:  linkUrl,
        link_name: linkName,
      }).then(res => {
        if (res.file) {
          const idx = card.files.findIndex(f => f.id === tempId);
          if (idx !== -1) card.files.splice(idx, 1, res.file);
        } else {
          card.files = card.files.filter(f => f.id !== tempId);
          window.showToast?.(res.error || 'Failed to attach link', 'error');
        }
      }).catch(() => {
        card.files = card.files.filter(f => f.id !== tempId);
        window.showToast?.('Network error attaching link', 'error');
      });
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
          if (idx !== -1) this.activeCard.files.splice(idx, 1, res.file);
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
      const fileName = file.display_name || file.original_name || 'attachment';
      if (!this.activeCard || !await window.confirmModal(`Remove "${fileName}"?`)) return;
      
      const original = [...this.activeCard.files];
      this.activeCard.files = this.activeCard.files.filter(x => x.id !== file.id);
      this.lists.forEach(l => {
        const c = l.cards.find(x => x.id === this.activeCard.id);
        if (c) c.has_files = this.activeCard.files.length > 0;
      });

      if (this.folderViewer && this.folderViewer.files) {
        this.folderViewer.files = this.folderViewer.files.filter(x => x.id !== file.id);
        if (!this.folderViewer.files.length) {
          this.closeFolderViewer();
        }
      }

      window.showToast('Attachment removed.');

      this.api(`/boards/cards/${this.activeCard.id}/files/${file.id}`, 'DELETE')
        .catch(() => {
          this.activeCard.files = original;
          this.lists.forEach(l => {
            const c = l.cards.find(x => x.id === this.activeCard.id);
            if (c) c.has_files = this.activeCard.files.length > 0;
          });
          if (this.folderViewer && this.folderViewer.open) {
            this.folderViewer.files = this.activeCard.files.filter(f => {
              const fFolder = this.getFolderOfFile(f);
              return fFolder === this.folderViewer.folderName;
            });
          }
          window.showToast?.('Failed to remove attachment', 'error');
        });
    },

    amAutoFillName() {
      const am = this.attachmentModal;
      // Auto-populate display name from URL hostname if the field is empty
      if (!am.linkName && am.linkUrl && am.linkUrl.length > 8) {
        if (this.isCanvaFile({ url: am.linkUrl })) {
          am.linkName = 'Canva Design';
          return;
        }
        try {
          const u = new URL(am.linkUrl);
          am.linkName = u.hostname.replace('www.', '');
        } catch { /* ignore invalid URL */ }
      }
    },

    previewAttachment(file, gallery = null) {
      if (!file?.is_image) return;

      let images = [];
      if (Array.isArray(gallery) && gallery.length) {
        images = gallery.filter(f => f && f.is_image);
      } else {
        const folder = this.getFolderOfFile(file);
        if (folder && this.activeCard?.files) {
          images = this.activeCard.files.filter(f => {
            const fFolder = this.getFolderOfFile(f);
            return f.is_image && fFolder === folder;
          });
        } else if (this.activeCard?.files) {
          images = this.activeCard.files.filter(f => f.is_image);
        } else {
          images = [file];
        }
      }

      this.imagePreview.images = images.length ? images : [file];
      const idx = this.imagePreview.images.findIndex(img => img.id === file.id);
      this.imagePreview.currentIndex = idx >= 0 ? idx : 0;
      this.imagePreview.url = file.preview_url || file.url;
      this.imagePreview.title = file.display_name || file.original_name || 'Image preview';
      this.imagePreview.open = true;
    },

    previewNextImage() {
      if (!this.imagePreview.images || this.imagePreview.images.length <= 1) return;
      let nextIdx = this.imagePreview.currentIndex + 1;
      if (nextIdx >= this.imagePreview.images.length) nextIdx = 0;
      this.imagePreview.currentIndex = nextIdx;
      const file = this.imagePreview.images[nextIdx];
      this.imagePreview.url = file.preview_url || file.url;
      this.imagePreview.title = file.display_name || file.original_name || 'Image preview';
    },

    previewPrevImage() {
      if (!this.imagePreview.images || this.imagePreview.images.length <= 1) return;
      let prevIdx = this.imagePreview.currentIndex - 1;
      if (prevIdx < 0) prevIdx = this.imagePreview.images.length - 1;
      this.imagePreview.currentIndex = prevIdx;
      const file = this.imagePreview.images[prevIdx];
      this.imagePreview.url = file.preview_url || file.url;
      this.imagePreview.title = file.display_name || file.original_name || 'Image preview';
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
      this.imagePreview.images = [{ preview_url: url, url: url, original_name: title, is_image: true }];
      this.imagePreview.currentIndex = 0;
      this.imagePreview.open = true;
    },

    closeImagePreview() {
      this.imagePreview.open = false;
      this.imagePreview.url = '';
      this.imagePreview.title = '';
      this.imagePreview.images = [];
      this.imagePreview.currentIndex = 0;
    },

    async downloadAttachment(file) {
      if (!file) return;
      const url = file.download_url || file.url;
      const filename = file.original_name || 'download';

      try {
        const response = await fetch(url);
        if (!response.ok) throw new Error('Network response was not ok');
        const blob = await response.blob();
        const blobUrl = URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        
        setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
      } catch (err) {
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
      }
    },

    isCanvaFile(file) {
      if (!file) return false;
      const url = (file.url || file.preview_url || file.path || file.stored_name || '').toLowerCase();
      const name = (file.original_name || '').toLowerCase();
      return url.includes('canva.com') ||
             url.includes('canva.link') ||
             url.includes('canva.me') ||
             url.includes('canva.site') ||
             name.includes('canva.com') ||
             name.includes('canva.link');
    },

    isVideoFile(file) {
      if (!file) return false;
      if (this.isCanvaFile(file)) return false;
      if (this.isGoogleDocsFile && this.isGoogleDocsFile(file)) return false;

      const url = (file.url || file.preview_url || file.download_url || file.path || file.stored_name || '').toLowerCase();
      const name = (file.original_name || '').toLowerCase();
      const raw = url + ' ' + name;

      // Google Drive folders are NEVER videos
      if (raw.includes('drive.google.com') && (raw.includes('/folders/') || raw.includes('/folderview') || raw.includes('folders%2f'))) {
        return false;
      }
      if (file.is_google_drive_folder === true) {
        return false;
      }

      if (file.is_video === false) return false;
      if (file.is_video === true) return true;
      const mime = (file.mime_type || '').toLowerCase();
      if (mime.startsWith('video/')) return true;

      if (url.includes('drive.google.com')) {
        const nonVideoExts = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx', '.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg', '.zip', '.rar', '.7z', '.txt', '.csv', '.tar', '.gz', '.json', '.xml', '.mp3', '.wav', '.ogg'];
        if (nonVideoExts.some(ext => name.endsWith(ext) || url.endsWith(ext))) {
          return false;
        }
        return url.includes('/file/d/') || url.includes('id=');
      }
      if (url.includes('youtube.com') || url.includes('youtu.be') || url.includes('loom.com') || url.includes('vimeo.com')) {
        return true;
      }
      const videoExts = ['.mp4', '.mov', '.webm', '.avi', '.mkv', '.wmv', '.flv', '.m4v', '.3gp'];
      return videoExts.some(ext => name.endsWith(ext) || url.endsWith(ext));
    },

    getVideoThumbnailUrl(file) {
      if (!file) return '';
      if (!this.isVideoFile(file)) return '';
      if (file.thumbnail_url) return file.thumbnail_url;
      const url = file.url || file.preview_url || file.download_url || file.path || file.stored_name || '';
      if (!url) return '';
      if (url.includes('/folders/') || url.includes('/folderview') || url.includes('folders%2f')) return '';

      // Google Drive thumbnail fallback
      if (url.includes('drive.google.com')) {
        const matchFile = url.match(/\/file\/d\/([a-zA-Z0-9_-]+)/i);
        if (matchFile && matchFile[1]) {
          return `https://drive.google.com/thumbnail?id=${matchFile[1]}&sz=w320`;
        }
        const matchId = url.match(/[?&]id=([a-zA-Z0-9_-]+)/i);
        if (matchId && matchId[1]) {
          return `https://drive.google.com/thumbnail?id=${matchId[1]}&sz=w320`;
        }
      }

      // YouTube video thumbnail
      if (url.includes('youtube.com') || url.includes('youtu.be')) {
        const ytMatch = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/i);
        if (ytMatch && ytMatch[1]) {
          return `https://img.youtube.com/vi/${ytMatch[1]}/mqdefault.jpg`;
        }
      }

      return '';
    },

    isDirectVideoFile(file) {
      if (!file) return false;
      const url = (file.url || file.preview_url || file.download_url || file.path || file.stored_name || '').toLowerCase();
      const name = (file.original_name || '').toLowerCase();
      const mime = (file.mime_type || '').toLowerCase();
      if (mime.startsWith('video/')) return true;
      const videoExts = ['.mp4', '.mov', '.webm', '.avi', '.mkv', '.wmv', '.flv', '.m4v', '.3gp'];
      return videoExts.some(ext => name.endsWith(ext) || url.endsWith(ext));
    },

    // ── Google Drive helpers ──────────────────────────────────────────────────
    isGoogleDriveFile(file) {
      if (!file) return false;
      if (file.is_google_drive === true) return true;
      const url = (file.url || file.preview_url || file.download_url || file.path || file.stored_name || '').toLowerCase();
      const name = (file.original_name || '').toLowerCase();
      return url.includes('drive.google.com') || name.includes('drive.google.com');
    },

    isGoogleDriveFolder(file) {
      if (!file) return false;
      if (file.is_google_drive_folder === true) return true;
      const url = (file.url || file.preview_url || file.download_url || file.path || file.stored_name || '').toLowerCase();
      const name = (file.original_name || '').toLowerCase();
      const raw = url + ' ' + name;
      return raw.includes('drive.google.com') && (
        raw.includes('/folders/') ||
        raw.includes('/folderview') ||
        raw.includes('folders%2f')
      );
    },

    openGoogleDriveDirect(url) {
      const target = url;
      if (!target) return;
      if (window.flutter_inappwebview && window.flutter_inappwebview.callHandler) {
        window.flutter_inappwebview.callHandler('DgtOpenExternal', target);
      } else {
        window.open(target, '_blank', 'noopener,noreferrer');
      }
    },

    // ── Google Docs / Sheets / Slides helpers ─────────────────────────────────
    isGoogleDocsFile(file) {
      if (!file) return false;
      const url = (file.url || file.preview_url || file.path || file.stored_name || '').toLowerCase();
      const name = (file.original_name || '').toLowerCase();
      return url.includes('docs.google.com') ||
             name.includes('docs.google.com') ||
             url.includes('/document/d/') ||
             url.includes('/spreadsheets/d/') ||
             url.includes('/presentation/d/') ||
             url.includes('/forms/d/') ||
             url.includes('drive.google.com/document') ||
             url.includes('drive.google.com/spreadsheets') ||
             url.includes('drive.google.com/presentation');
    },

    getGoogleDocsType(file) {
      if (!file) return 'doc';
      const url = (file.url || file.preview_url || file.path || file.stored_name || '').toLowerCase();
      if (url.includes('/spreadsheets')) return 'sheet';
      if (url.includes('/presentation')) return 'slide';
      if (url.includes('/forms')) return 'form';
      return 'doc';
    },

    getGoogleDocsEmbedUrl(file) {
      if (!file) return '';
      const rawUrl = file.url || file.preview_url || file.path || file.stored_name || '';
      if (!rawUrl) return '';
      const lower = rawUrl.toLowerCase();

      // Helper to extract doc ID
      const extractId = (pattern) => {
        const m = rawUrl.match(pattern);
        return m ? m[1] : null;
      };

      // Use /preview URLs — designed for embedding, no auth consent popup
      if (lower.includes('/document/d/')) {
        const id = extractId(/\/document\/d\/([a-zA-Z0-9_-]+)/i);
        if (id) return `https://docs.google.com/document/d/${id}/preview`;
      }
      if (lower.includes('/spreadsheets/d/')) {
        const id = extractId(/\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/i);
        // Sheets: use pub?output=html for best compatibility, or htmlview
        if (id) return `https://docs.google.com/spreadsheets/d/${id}/htmlview?usp=sharing`;
      }
      if (lower.includes('/presentation/d/')) {
        const id = extractId(/\/presentation\/d\/([a-zA-Z0-9_-]+)/i);
        if (id) return `https://docs.google.com/presentation/d/${id}/embed?start=false&loop=false&delayms=3000`;
      }
      if (lower.includes('/forms/d/')) {
        const id = extractId(/\/forms\/d\/([a-zA-Z0-9_-]+)/i);
        if (id) return `https://docs.google.com/forms/d/${id}/viewform?embedded=true`;
      }
      // fallback
      return rawUrl.replace(/\/(view|edit|pub)(\?.*)?$/i, '') + '/preview';
    },

    openGoogleDocsPreview(file) {
      if (!file) return;
      const rawUrl = file.url || file.preview_url || file.path || file.stored_name || '';
      if (!rawUrl) return;

      // ── macOS InAppWebView: Open in default external browser ───────────────
      // Google Docs requires active browser session cookies and cannot be edited in iframes
      if (this.isMacApp() && window.flutter_inappwebview && window.flutter_inappwebview.callHandler) {
        window.flutter_inappwebview.callHandler('DgtOpenExternal', rawUrl);
        return;
      }

      // ── Web browser: show in-system iframe modal ───────────────────────────
      const embedUrl = this.getGoogleDocsEmbedUrl(file);
      const title = file.original_name || 'Document';
      const type = this.getGoogleDocsType(file);
      this.googleDocsPreview.url = rawUrl;
      this.googleDocsPreview.embedUrl = embedUrl;
      this.googleDocsPreview.title = title;
      this.googleDocsPreview.type = type;
      this.googleDocsPreview.loading = true;
      this.googleDocsPreview.open = true;
    },

    closeGoogleDocsPreview() {
      this.googleDocsPreview.open = false;
      this.googleDocsPreview.loading = false;
      this.googleDocsPreview.embedUrl = '';
      this.googleDocsPreview.url = '';
      this.googleDocsPreview.title = '';
    },

    openGoogleDocsDirect(url) {
      const target = url || this.googleDocsPreview.url;
      if (!target) return;
      // In macOS app: open in external default browser (e.g. Chrome where user is logged in)
      if (window.flutter_inappwebview && window.flutter_inappwebview.callHandler) {
        window.flutter_inappwebview.callHandler('DgtOpenExternal', target);
      } else {
        window.open(target, '_blank', 'noopener,noreferrer');
      }
    },
    // ─────────────────────────────────────────────────────────────────────────

    getVideoEmbedUrl(file) {
      if (!file) return '';
      if (file.embed_url) {
        let embed = file.embed_url;
        if (embed.includes('youtube.com') && !embed.includes('autoplay=')) {
          embed += (embed.includes('?') ? '&' : '?') + 'autoplay=1&enablejsapi=1';
        }
        if (embed.includes('drive.google.com')) {
          embed = embed.replace(/[?&]autoplay=[^&]*/gi, '').replace(/\?$/, '');
        }
        if (embed.includes('loom.com') && !embed.includes('autoplay=')) {
          embed += (embed.includes('?') ? '&' : '?') + 'autoplay=1';
        }
        if (embed.includes('vimeo.com') && !embed.includes('autoplay=')) {
          embed += (embed.includes('?') ? '&' : '?') + 'autoplay=1';
        }
        return embed;
      }
      const url = file.url || file.preview_url || file.download_url || file.path || file.stored_name || '';
      if (!url) return '';

      // Google Drive: extract file ID
      if (url.includes('drive.google.com')) {
        if (url.includes('/folders/') || url.includes('/folderview') || url.includes('folders%2f')) {
          return '';
        }
        const matchFile = url.match(/\/file\/d\/([a-zA-Z0-9_-]+)/i);
        if (matchFile && matchFile[1]) {
          return `https://drive.google.com/file/d/${matchFile[1]}/preview?vq=hd1080`;
        }
        const matchId = url.match(/[?&]id=([a-zA-Z0-9_-]+)/i);
        if (matchId && matchId[1]) {
          return `https://drive.google.com/file/d/${matchId[1]}/preview?vq=hd1080`;
        }
        if (this.isVideoFile(file)) {
          const cleanUrl = url.split('?')[0];
          return cleanUrl.replace('/view', '/preview') + '?vq=hd1080';
        }
        return '';
      }

      // YouTube
      if (url.includes('youtube.com') || url.includes('youtu.be')) {
        const ytMatch = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/i);
        if (ytMatch && ytMatch[1]) {
          return `https://www.youtube.com/embed/${ytMatch[1]}?autoplay=1&enablejsapi=1&mute=0&vq=hd1080`;
        }
      }

      // Loom
      if (url.includes('loom.com/share/')) {
        const loomMatch = url.match(/loom\.com\/share\/([a-zA-Z0-9]+)/i);
        if (loomMatch && loomMatch[1]) {
          return `https://www.loom.com/embed/${loomMatch[1]}?autoplay=1`;
        }
      }

      // Vimeo
      if (url.includes('vimeo.com')) {
        const vimeoMatch = url.match(/vimeo\.com\/(\d+)/i);
        if (vimeoMatch && vimeoMatch[1]) {
          return `https://player.vimeo.com/video/${vimeoMatch[1]}?autoplay=1`;
        }
      }

      return file.preview_url || file.download_url || url;
    },

    openVideoPreview(file) {
      if (!file) return;
      const rawUrl = file.url || file.preview_url || file.download_url || file.path || file.stored_name || '';
      if (!rawUrl && !file.embed_url) return;

      const title = file.original_name || 'Video Preview';

      // ── macOS App InAppWebView: View video inside system (native theater) ──
      const isDriveVideo = (rawUrl && rawUrl.includes('drive.google.com')) || (file.embed_url && file.embed_url.includes('drive.google.com')) || (typeof this.isGoogleDriveFile === 'function' && this.isGoogleDriveFile(file));
      if (this.isMacApp() && window.flutter_inappwebview && window.flutter_inappwebview.callHandler && isDriveVideo) {
        let driveUrl = rawUrl || file.embed_url;
        if (!driveUrl.includes('vq=')) {
          driveUrl += (driveUrl.includes('?') ? '&' : '?') + 'vq=hd1080';
        }
        window.flutter_inappwebview.callHandler('DgtPlayInAppVideo', { url: driveUrl, title: title });
        return;
      }

      const embedUrl = this.getVideoEmbedUrl(file);

      this.videoPreview.embedUrl = embedUrl;
      this.videoPreview.url = rawUrl || embedUrl;
      this.videoPreview.title = title;
      this.videoPreview.open = true;

      // Automatically trigger play for native HTML5 video and iframe players immediately
      const triggerAutoplay = () => {
        const videoEl = document.querySelector('#systemVideoPlayer') || document.querySelector('[x-show="videoPreview.open"] video');
        if (videoEl) {
          const playPromise = videoEl.play();
          if (playPromise !== undefined) {
            playPromise.catch(() => {
              // If browser blocks unmuted autoplay, mute to start playback immediately without requiring user click
              videoEl.muted = true;
              videoEl.play().catch(() => {});
            });
          }
        }
        const iframeEl = document.querySelector('[x-show="videoPreview.open"] iframe');
        if (iframeEl) {
          this.onVideoIframeLoad({ target: iframeEl });
        }
      };

      this.$nextTick(() => {
        triggerAutoplay();
        setTimeout(triggerAutoplay, 60);
        setTimeout(triggerAutoplay, 180);
        setTimeout(triggerAutoplay, 400);
        setTimeout(triggerAutoplay, 800);
      });
    },

    onVideoIframeLoad(event) {
      try {
        const iframe = event?.target;
        if (!iframe) return;
        const sendPlay = () => {
          try {
            const win = iframe.contentWindow;
            if (!win) return;
            // YouTube & Google Drive embedded player handshake & play commands
            win.postMessage(JSON.stringify({ event: 'listening' }), '*');
            win.postMessage(JSON.stringify({ event: 'command', func: 'setPlaybackQualityRange', args: ['hd1080', 'highres'] }), '*');
            win.postMessage(JSON.stringify({ event: 'command', func: 'setPlaybackQuality', args: ['hd1080'] }), '*');
            win.postMessage(JSON.stringify({ event: 'command', func: 'playVideo', args: [] }), '*');
            win.postMessage('{"event":"command","func":"playVideo","args":""}', '*');
            // Vimeo / Loom play commands
            win.postMessage(JSON.stringify({ method: 'play' }), '*');
          } catch (err) {}
        };
        sendPlay();
        setTimeout(sendPlay, 100);
        setTimeout(sendPlay, 300);
        setTimeout(sendPlay, 700);
        setTimeout(sendPlay, 1500);
      } catch (e) {}
    },

    closeVideoPreview() {
      this.videoPreview.open = false;
      this.videoPreview.embedUrl = ''; // Clear iframe src to stop playback
      this.videoPreview.url = '';
      this.videoPreview.title = '';
    },

    openVideoDirect(url) {
      let targetUrl = url || this.videoPreview?.url;
      if (!targetUrl) return;

      // Normalize Google Drive preview / embed URLs to standard view URL for external browser
      if (targetUrl.includes('drive.google.com')) {
        const matchFile = targetUrl.match(/\/file\/d\/([a-zA-Z0-9_-]+)/i);
        if (matchFile && matchFile[1]) {
          targetUrl = `https://drive.google.com/file/d/${matchFile[1]}/view`;
        } else {
          targetUrl = targetUrl.replace(/\/preview(\?.*)?$/i, '/view');
        }
      }

      if (window.flutter_inappwebview && window.flutter_inappwebview.callHandler) {
        window.flutter_inappwebview.callHandler('DgtOpenExternal', targetUrl);
      } else {
        window.open(targetUrl, '_blank', 'noopener,noreferrer');
      }
    },

    getCanvaEmbedUrl(file) {
      if (!file) return '';
      // If embed_url is already a canonical design embed URL, return it directly
      if (file.embed_url && file.embed_url.includes('canva.com/design/')) {
        return file.embed_url;
      }
      const rawUrl = file.url || file.preview_url || file.path || file.stored_name || '';
      if (!rawUrl) return '';

      const match = rawUrl.match(/\/design\/([a-zA-Z0-9_-]+)(?:\/([a-zA-Z0-9_-]+))?(?:\/([a-zA-Z0-9_-]+))?/i);
      if (match) {
        const id = match[1];
        const p2 = match[2] || '';
        const p3 = match[3] || '';
        if (['view', 'edit', 'watch', 'present', ''].includes(p2)) {
          return `https://www.canva.com/design/${id}/view?embed`;
        } else if (['view', 'edit', 'watch', 'present', ''].includes(p3)) {
          return `https://www.canva.com/design/${id}/${p2}/view?embed`;
        } else {
          return `https://www.canva.com/design/${id}/view?embed`;
        }
      }

      if (file.embed_url && !file.embed_url.includes('canva.link') && !file.embed_url.includes('canva.me') && !file.embed_url.includes('canva.site')) {
        return file.embed_url;
      }
      return '';
    },

    openCanva(file) {
      if (!file) return;
      this.openCanvaPreview(file);
    },

    openCanvaDirect(file) {
      if (!file) return;
      const rawUrl = file.url || file.preview_url || file.path || file.stored_name || '';
      if (!rawUrl) return;

      if (window.flutter_inappwebview && window.flutter_inappwebview.callHandler) {
        window.flutter_inappwebview.callHandler('DgtOpenExternal', rawUrl);
      } else {
        window.open(rawUrl, '_blank', 'noopener,noreferrer');
      }
    },

    openCanvaPreview(file) {
      if (!file) return;
      const rawUrl = file.url || file.preview_url || file.path || file.stored_name || '';
      if (!rawUrl) return;

      const embedUrl = this.getCanvaEmbedUrl(file);
      const cleanEmbed = (embedUrl || '').split('#')[0];
      this.canvaPreview.embedUrl = cleanEmbed ? `${cleanEmbed}#1` : '';
      this.canvaPreview.url = rawUrl;
      this.canvaPreview.title = file.original_name || 'Canva Design';
      this.canvaPreview.scale = 1;
      this.canvaPreview.panX = 0;
      this.canvaPreview.panY = 0;
      this.canvaPreview.isDragging = false;
      this.canvaPreview.dragMoved = false;
      this.canvaPreview.loading = false;
      this.canvaPreview.currentPage = 1;
      this.canvaPreview.totalPages = (file.total_pages && file.total_pages > 1) ? file.total_pages : 16;
      this.canvaPreview.isFullscreen = false;
      this.canvaPreview.open = true;

      // Resolve embed URL and total page count via backend
      const needsResolve = !embedUrl || !embedUrl.includes('canva.com/design/') || !file.total_pages;
      if (needsResolve) {
        if (!this.canvaPreview.embedUrl) {
          this.canvaPreview.loading = true;
        }
        fetch(`/boards/canva/resolve?url=${encodeURIComponent(rawUrl)}`, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(res => res.json())
        .then(data => {
          if (data && data.embed_url && data.embed_url.includes('canva.com/design/')) {
            const clean = data.embed_url.split('#')[0];
            this.canvaPreview.embedUrl = `${clean}#${this.canvaPreview.currentPage}`;
            file.embed_url = data.embed_url;
          }
          if (data && data.total_pages && data.total_pages > 1) {
            this.canvaPreview.totalPages = data.total_pages;
            file.total_pages = data.total_pages;
          }
        })
        .catch(err => {
          console.warn('Canva embed resolve error:', err);
        })
        .finally(() => {
          this.canvaPreview.loading = false;
        });
      }
    },

    closeCanvaPreview() {
      if (document.fullscreenElement || document.webkitFullscreenElement) {
        try {
          if (document.exitFullscreen) document.exitFullscreen();
          else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
        } catch (_) {}
      }
      this.canvaPreview.open = false;
      this.canvaPreview.embedUrl = '';
      this.canvaPreview.url = '';
      this.canvaPreview.title = '';
      this.canvaPreview.scale = 1;
      this.canvaPreview.panX = 0;
      this.canvaPreview.panY = 0;
      this.canvaPreview.isDragging = false;
      this.canvaPreview.currentPage = 1;
      this.canvaPreview.totalPages = 16;
      this.canvaPreview.isFullscreen = false;
    },

    canvaZoomIn() {
      this.canvaPreview.scale = Math.min(4, +(this.canvaPreview.scale + 0.25).toFixed(2));
    },

    canvaZoomOut() {
      this.canvaPreview.scale = Math.max(0.5, +(this.canvaPreview.scale - 0.25).toFixed(2));
      if (this.canvaPreview.scale <= 1) {
        this.canvaPreview.panX = 0;
        this.canvaPreview.panY = 0;
      }
    },

    canvaResetZoom() {
      this.canvaPreview.scale = 1;
      this.canvaPreview.panX = 0;
      this.canvaPreview.panY = 0;
      this.canvaPreview.isDragging = false;
      this.canvaPreview.dragMoved = false;
    },

    canvaToggleFullScreen() {
      const elem = document.getElementById('canvaModalPanel') || document.documentElement;
      if (!document.fullscreenElement && !document.webkitFullscreenElement) {
        if (elem.requestFullscreen) {
          elem.requestFullscreen();
        } else if (elem.webkitRequestFullscreen) {
          elem.webkitRequestFullscreen();
        }
        this.canvaPreview.isFullscreen = true;
      } else {
        if (document.exitFullscreen) {
          document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
          document.webkitExitFullscreen();
        }
        this.canvaPreview.isFullscreen = false;
      }
    },

    canvaGetTotalPages() {
      const t = parseInt(this.canvaPreview.totalPages, 10);
      return (t && t > 1) ? t : 16;
    },

    canvaGoToPage(page) {
      const total = this.canvaGetTotalPages();
      let targetPage = parseInt(page, 10);
      if (isNaN(targetPage)) targetPage = 1;

      // Infinite loop wrap-around
      if (targetPage < 1) targetPage = total;
      if (targetPage > total) targetPage = 1;

      this.canvaPreview.currentPage = targetPage;

      const iframe = document.getElementById('canvaPreviewIframe');
      if (iframe && this.canvaPreview.embedUrl) {
        const baseUrl = this.canvaPreview.embedUrl.split('#')[0];
        const targetUrl = `${baseUrl}#${targetPage}`;
        iframe.src = targetUrl;

        try {
          const win = iframe.contentWindow;
          if (win) {
            win.postMessage({ type: 'canva:goto', page: targetPage }, '*');
            win.postMessage({ type: 'page', page: targetPage }, '*');
            win.postMessage({ type: 'presentation:page', page: targetPage }, '*');
          }
        } catch (_) {}
      }
    },

    canvaPrevPage() {
      const total = this.canvaGetTotalPages();
      let prevPage = this.canvaPreview.currentPage - 1;
      if (prevPage < 1) {
        prevPage = total; // Loop to last page (e.g. 16)
      }
      this.canvaGoToPage(prevPage);
    },

    canvaNextPage() {
      const total = this.canvaGetTotalPages();
      let nextPage = this.canvaPreview.currentPage + 1;
      if (nextPage > total) {
        nextPage = 1; // Loop to first page (1)
      }
      this.canvaGoToPage(nextPage);
    },

    canvaPan(dx, dy) {
      this.canvaPreview.panX += dx;
      this.canvaPreview.panY += dy;
    },

    canvaPanLeft() {
      // Pan viewport left (moves design right to show left side)
      this.canvaPan(150, 0);
    },

    canvaPanRight() {
      // Pan viewport right (moves design left to show right side)
      this.canvaPan(-150, 0);
    },

    canvaPanUp() {
      // Pan viewport up (moves design down to show top side)
      this.canvaPan(0, 150);
    },

    canvaPanDown() {
      // Pan viewport down (moves design up to show bottom side)
      this.canvaPan(0, -150);
    },

    canvaResetPan() {
      this.canvaPreview.panX = 0;
      this.canvaPreview.panY = 0;
    },

    canvaStartDrag(e) {
      if (e.button !== 0) return;
      if (this.canvaPreview.scale <= 1) return;
      e.preventDefault();
      this.canvaPreview.isDragging = true;
      this.canvaPreview.dragMoved = false;
      this.canvaPreview.startX = e.clientX - this.canvaPreview.panX;
      this.canvaPreview.startY = e.clientY - this.canvaPreview.panY;
    },

    canvaOnDrag(e) {
      if (!this.canvaPreview.isDragging) return;
      const newPanX = e.clientX - this.canvaPreview.startX;
      const newPanY = e.clientY - this.canvaPreview.startY;
      if (Math.abs(newPanX - this.canvaPreview.panX) > 2 || Math.abs(newPanY - this.canvaPreview.panY) > 2) {
        this.canvaPreview.dragMoved = true;
      }
      this.canvaPreview.panX = newPanX;
      this.canvaPreview.panY = newPanY;
    },

    canvaStopDrag() {
      this.canvaPreview.isDragging = false;
    },

    canvaHandleWheel(e) {
      e.preventDefault();
      // Trackpad pinch zoom (Ctrl + wheel) or normal mouse scroll wheel
      if (e.ctrlKey || Math.abs(e.deltaY) > 10) {
        const zoomDelta = -e.deltaY * (e.ctrlKey ? 0.015 : 0.002);
        const newScale = Math.min(Math.max(0.5, +(this.canvaPreview.scale + zoomDelta).toFixed(2)), 4);
        this.canvaPreview.scale = newScale;
        if (this.canvaPreview.scale <= 1) {
          this.canvaPreview.panX = 0;
          this.canvaPreview.panY = 0;
        }
      } else if (this.canvaPreview.scale > 1) {
        this.canvaPreview.panX -= e.deltaX;
        this.canvaPreview.panY -= e.deltaY;
      }
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
      const cardId = this.activeCard.id;
      this.lists.forEach(l => {
        l.cards = l.cards.filter(c => c.id !== cardId);
      });
      window.showToast("Card archived.");
      this.closeCard();

      this.api(`/boards/cards/${cardId}`, 'PATCH', { is_archived: true }).catch(err => {
        console.error("Failed to archive card", err);
      });
    },

    async deleteCard() {
      if (!this.activeCard) return;
      const ok = window.confirmModal
        ? await window.confirmModal({
            title: 'Move card to Trash?',
            message: `Move "<strong>${this.escapeHtml(this.activeCard.title)}</strong>" to Trash?<br><span class="text-xs text-slate-500 mt-1 block">Items in Trash are kept for 7 days before being automatically removed.</span>`,
            confirmText: 'Move to Trash',
            tone: 'danger',
          })
        : confirm(`Move "${this.activeCard.title}" to Trash?`);
      if (!ok) return;

      const cardId = this.activeCard.id;
      const origLists = JSON.parse(JSON.stringify(this.lists));
      this.lists.forEach(l => {
        l.cards = l.cards.filter(c => c.id !== cardId);
      });
      window.showToast("Card moved to Trash (auto-removes in 7 days).");
      this.closeCard();

      try {
        const res = await this.api(`/boards/cards/${cardId}`, 'DELETE');
        if (res && res._ok === false) {
          this.lists = origLists;
          window.showToast(res.error || res.message || 'Failed to move card to Trash.', 'error');
        }
      } catch (err) {
        this.lists = origLists;
        window.showToast('Failed to move card to Trash.', 'error');
      }
    },

    async moveCardDirect(listId) {
      if (!this.activeCard) return;
      
      if (this.activeCard.board_list_id !== listId && this.isCardChecklistIncomplete(this.activeCard)) {
        this.showChecklistIncompleteModal('move', this.activeCard);
        return;
      }

      const targetList = this.lists.find(l => l.id === listId);
      if (!targetList) return;
      
      try {
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
      } catch (err) {
        if (err && err.checklist_incomplete) {
          this.showChecklistIncompleteModal('move', this.activeCard);
        } else {
          window.showToast(err?.error || err?.message || 'Failed to move card.', 'error');
        }
      }
    },

    async deleteComment(commentId) {
      if (!this.activeCard || !await window.confirmModal("Delete this comment permanently?")) return;
      
      const originalComments = [...this.activeCard.comments];
      this.activeCard.comments = this.activeCard.comments.filter(x => x.id !== commentId);
      this.lists.forEach(l => {
        const c = l.cards.find(x => x.id === this.activeCard.id);
        if (c) c.comment_count = Math.max(0, (c.comment_count ?? 1) - 1);
      });
      
      this.api(`/boards/cards/${this.activeCard.id}/comments/${commentId}`, 'DELETE')
        .then(res => {
          if (res.success || res.message) {
            window.showToast('Comment deleted.');
            this.refreshCardActivities();
          } else {
            this.activeCard.comments = originalComments;
            this.lists.forEach(l => {
              const c = l.cards.find(x => x.id === this.activeCard.id);
              if (c) c.comment_count = (c.comment_count ?? 0) + 1;
            });
            window.showToast?.('Failed to delete comment', 'error');
          }
        }).catch(() => {
          this.activeCard.comments = originalComments;
          this.lists.forEach(l => {
            const c = l.cards.find(x => x.id === this.activeCard.id);
            if (c) c.comment_count = (c.comment_count ?? 0) + 1;
          });
          window.showToast?.('Network error', 'error');
        });
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

    enhanceDescriptionHtml(htmlString) {
      if (!htmlString || typeof htmlString !== 'string') return '';
      try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlString, 'text/html');

        const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT, {
          acceptNode(node) {
            let p = node.parentElement;
            while (p && p !== doc.body) {
              if (p.tagName === 'A' || p.tagName === 'SCRIPT' || p.tagName === 'STYLE') {
                return NodeFilter.FILTER_REJECT;
              }
              p = p.parentElement;
            }
            return NodeFilter.FILTER_ACCEPT;
          }
        });

        const textNodes = [];
        while (walker.nextNode()) {
          textNodes.push(walker.currentNode);
        }

        const urlRegex = /(https?:\/\/[^\s<]+[^<.,:;"')\]\s]|(?<![\w@])www\.[^\s<]+[^<.,:;"')\]\s])/gi;

        for (const node of textNodes) {
          const val = node.nodeValue;
          if (urlRegex.test(val)) {
            urlRegex.lastIndex = 0;
            const frag = doc.createDocumentFragment();
            let lastIdx = 0;
            let match;
            while ((match = urlRegex.exec(val)) !== null) {
              if (match.index > lastIdx) {
                frag.appendChild(doc.createTextNode(val.substring(lastIdx, match.index)));
              }
              const rawUrl = match[0];
              const href = rawUrl.startsWith('http') ? rawUrl : 'https://' + rawUrl;
              const a = doc.createElement('a');
              a.href = href;
              a.target = '_blank';
              a.rel = 'noopener noreferrer';
              a.className = 'card-desc-link';
              a.textContent = rawUrl;
              frag.appendChild(a);
              lastIdx = match.index + rawUrl.length;
            }
            if (lastIdx < val.length) {
              frag.appendChild(doc.createTextNode(val.substring(lastIdx)));
            }
            node.parentNode.replaceChild(frag, node);
          }
        }

        doc.body.querySelectorAll('a').forEach(a => {
          a.target = '_blank';
          a.rel = 'noopener noreferrer';
          a.classList.add('card-desc-link');
        });

        return doc.body.innerHTML;
      } catch (e) {
        return htmlString;
      }
    },

    parseMarkdown(text) {
      if (!text) return '<p class="text-slate-400 italic">No description provided. Click here to add one...</p>';

      const trimmed = text.trim();
      // If it's already HTML (from Quill WYSIWYG or tags), enhance it and ensure links are clickable
      if (/<[a-z][\s\S]*>/i.test(trimmed)) {
        return this.enhanceDescriptionHtml(text);
      }

      // If it looks like an activity log (short, no newlines, no URLs), do a lightweight parse
      if (!text.includes('\n') && text.length < 500 && !text.includes('http://') && !text.includes('https://') && !text.includes('www.')) {
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
      
      html = html.replace(/^### (.*$)/gim, '<h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mt-3 mb-1">$1</h3>');
      html = html.replace(/^## (.*$)/gim, '<h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 mt-4 mb-2">$1</h2>');
      html = html.replace(/^# (.*$)/gim, '<h1 class="text-base font-bold text-slate-900 dark:text-slate-50 mt-4 mb-2">$1</h1>');
      
      html = html.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-900 dark:text-slate-100">$1</strong>');
      html = html.replace(/\*(.*?)\*/g, '<em class="italic">$1</em>');
      html = html.replace(/`(.*?)`/g, '<code class="bg-slate-100 dark:bg-slate-700/60 text-rose-500 rounded px-1 py-0.5 text-sm font-mono">$1</code>');
      
      html = html.replace(/^\s*-\s+(.*$)/gim, '<li class="ml-4 list-disc text-slate-600 dark:text-slate-300 text-sm">$1</li>');

      // Markdown links: [label](url)
      html = html.replace(/\[([^\]]+?)\]\(((?:https?:\/\/|www\.)[^\s)]+)\)/gi, (match, label, url) => {
        const href = url.startsWith('http') ? url : 'https://' + url;
        return `<a href="${href}" target="_blank" rel="noopener noreferrer" class="card-desc-link">${label}</a>`;
      });

      // Raw URLs: https://... or http://... or www....
      const rawUrlRegex = /(^|[\s(>])((?:https?:\/\/|www\.)[^\s<]+[^<.,:;"')\]\s])/gi;
      html = html.replace(rawUrlRegex, (match, prefix, url) => {
        const href = url.startsWith('http') ? url : 'https://' + url;
        return `${prefix}<a href="${href}" target="_blank" rel="noopener noreferrer" class="card-desc-link">${url}</a>`;
      });

      html = html.replace(/\n/g, '<br>');
      
      return html;
    },

    // Render comment body: supports @mentions, markdown images, links, bold, italic
    parseCommentBody(text) {
      if (!text) return '';

      // Step 1: HTML-escape the raw text first to prevent XSS
      let html = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

      // Step 2: Parse mentions AFTER escaping — so the span tags are NOT escaped
      html = html.replace(/(?:^|(?<=\s))@([\w.\-]+)/g, (match, username) => {
        return match.replace('@' + username,
          `<span class="comment-mention inline-flex items-center gap-0.5 font-bold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded-full text-[11px]">@${username}</span>`);
      });

      // Step 3: Bold and italic markdown
      html = html.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-900">$1</strong>');
      html = html.replace(/\*(.*?)\*/g, '<em class="italic">$1</em>');

      // Step 4: Render markdown images ![alt](url) as clickable thumbnails
      html = html.replace(/!\[([^\]]*?)\]\(([^)]+?)\)/g, (_, alt, url) => {
        const safeUrl = url.replace(/"/g, '%22');
        return `<a href="${safeUrl}" target="_blank" rel="noopener" class="block mt-1">`
          + `<img src="${safeUrl}" alt="${alt || 'screenshot'}" class="max-w-full max-h-48 rounded-xl border border-slate-200 shadow-sm object-contain cursor-zoom-in hover:shadow-md transition-shadow">`
          + `</a>`;
      });

      // Step 5: Render [text](url) links
      html = html.replace(/\[([^\]]+?)\]\(([^)]+?)\)/g, (_, label, url) => {
        const safeUrl = url.replace(/"/g, '%22');
        return `<a href="${safeUrl}" target="_blank" rel="noopener" class="text-indigo-600 underline hover:text-indigo-800">${label}</a>`;
      });

      // Step 5.5: Render raw HTTP/HTTPS URLs (that aren't inside markdown links or html attributes)
      // By checking for (^|\s), we avoid matching URLs inside href="..." or src="..." or [text](url)
      html = html.replace(/(^|\s)(https?:\/\/[^\s<]+[^<.,:;"')\]\s])/g, (_, space, url) => {
        return `${space}<a href="${url}" target="_blank" rel="noopener" class="text-indigo-600 underline hover:text-indigo-800 break-all">${url}</a>`;
      });

      // Step 6: Newlines
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
      if (!this.pastedImages) this.pastedImages = [];
      let added = 0;
      for (let i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image') !== -1) {
          event.preventDefault();
          const blob = items[i].getAsFile();
          if (blob) {
            // Compress to max 1200×900 at 80% quality for inline storage
            const compressed = await this.compressImage(blob, 1200, 900, 0.80);
            if (compressed) {
              this.pastedImages.push(compressed);
              this.pastedImage = this.pastedImages[0];
              added++;
            }
          }
        }
      }
      if (added > 0) {
        const total = this.pastedImages.length;
        window.showToast(total > 1 ? `${total} screenshots ready! Press ⌘+V to paste more` : 'Screenshot attached! Press ⌘+V to paste more 📸', 'info');
      }
    },

    removePastedImage(index) {
      if (!this.pastedImages) return;
      this.pastedImages.splice(index, 1);
      this.pastedImage = this.pastedImages.length ? this.pastedImages[0] : null;
    },

    clearPastedImages() {
      this.pastedImages = [];
      this.pastedImage = null;
    },

    async sendScreenshot() {
      const imagesToSend = (this.pastedImages && this.pastedImages.length)
        ? [...this.pastedImages]
        : (this.pastedImage ? [this.pastedImage] : []);
      if (!imagesToSend.length || !this.activeCard) return;

      this.sendingScreenshot = true;
      try {
        // 1. Upload all images as files (with comment_only flag so they won't show in Attachments)
        const uploadPromises = imagesToSend.map(async (dataUrl, idx) => {
          const fetchRes = await fetch(dataUrl);
          const blob = await fetchRes.blob();
          const formData = new FormData();
          formData.append('file', blob, `screenshot-${Date.now()}-${idx + 1}.jpg`);
          formData.append('comment_only', '1');

          const fileRes = await fetch(`/boards/cards/${this.activeCard.id}/files`, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': this.csrfToken,
              'Accept': 'application/json'
            },
            body: formData
          });
          const fileData = await fileRes.json();
          if (!fileRes.ok) {
            throw new Error(fileData.error || `Failed to upload screenshot #${idx + 1}`);
          }
          return fileData.file.url;
        });

        const uploadedUrls = await Promise.all(uploadPromises);

        // 2. Post the comment referencing all URLs
        const textPrefix = this.newComment.trim() ? this.newComment.trim() + '\n\n' : '';
        const imgMarkdown = uploadedUrls.map(url => `![screenshot](${url})`).join('\n\n');
        const body = textPrefix + imgMarkdown;

        const res = await this.api(`/boards/cards/${this.activeCard.id}/comments`, 'POST', { body });
        if (res.comment) {
          if (res.card_moved) {
            window.showToast('Card moved by automation!');
            const newCard = res.card;
            if (parseInt(newCard.board_id, 10) !== parseInt(this.boardId, 10)) {
              this.lists.forEach(l => l.cards = l.cards.filter(c => parseInt(c.id, 10) !== parseInt(this.activeCard.id, 10)));
              this.closeCard();
              return;
            } else if (parseInt(newCard.board_list_id, 10) !== parseInt(this.activeCard.board_list_id, 10)) {
              this.lists.forEach(l => l.cards = l.cards.filter(c => parseInt(c.id, 10) !== parseInt(this.activeCard.id, 10)));
              const targetList = this.lists.find(l => parseInt(l.id, 10) === parseInt(newCard.board_list_id, 10));
              if (targetList) targetList.cards.unshift(newCard);
              this.closeCard();
              return;
            }
          }

          if (!this.activeCard.comments) this.activeCard.comments = [];
          this.activeCard.comments.push(res.comment);
          this.newComment = '';
          this.pastedImages = [];
          this.pastedImage = null;
          
          this.lists.forEach(l => {
            const c = l.cards.find(x => x.id === this.activeCard.id);
            if (c) c.comment_count = (c.comment_count ?? 0) + 1;
          });

          window.showToast(imagesToSend.length > 1 ? `${imagesToSend.length} screenshots shared in comments! 📸` : 'Screenshot shared in comments! 📸');
          this.refreshCardActivities();
        }
      } catch(e) {
        window.showToast(e.message || 'Failed to send screenshot', 'error');
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
        // setTimeout(() => window.location.reload(), 800);
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
        // setTimeout(() => window.location.reload(), 800);
      } else if (res.error) {
        window.showToast(res.error, 'error');
      }
    },

    bindRealtimeBoardUpdates() {
      this.connectBoardRealtimeChannel();
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
        if (!ENABLE_BOARD_REALTIME_SYNC) return;
        
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
      if (!ENABLE_BOARD_REALTIME_SYNC) return;
      if (this.realtimeDragging) return;

      clearTimeout(this.realtimeTimer);
      const delay = reason === 'push' ? 200 : 650;
      this.realtimeTimer = setTimeout(() => this.refreshBoardSnapshot(reason), delay);
    },

    async refreshBoardSnapshot(reason = 'push') {
      if (!ENABLE_BOARD_REALTIME_SYNC) return;
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
      this.labels = payload.labels || this.labels;
      this.allBoardMembers = payload.boardMembers || this.allBoardMembers;
      this.allWorkspaceMembers = payload.workspaceMembers || this.allWorkspaceMembers;
      this.allWorkspaces = (payload.allWorkspaces || this.allWorkspaces).filter(ws => ws.boards && ws.boards.length > 0);

      if (payload.lists) {
        let listStructureChanged = false;
        const newLists = payload.lists;
        const newListIds = new Set(newLists.map(l => l.id));

        // 1. Remove deleted lists
        for (let i = this.lists.length - 1; i >= 0; i--) {
            if (!newListIds.has(this.lists[i].id)) {
                this.lists.splice(i, 1);
                listStructureChanged = true;
            }
        }

        // 2. Add or update lists
        newLists.forEach((newList, lIndex) => {
            let existingList = this.lists.find(l => l.id === newList.id);
            if (!existingList) {
                this.lists.splice(lIndex, 0, newList);
                listStructureChanged = true;
            } else {
                // Update list properties in place
                Object.assign(existingList, {
                    name: newList.name,
                    position: newList.position,
                    color: newList.color
                });

                if (newList.cards) {
                    const newCardIds = new Set(newList.cards.map(c => parseInt(c.id, 10)));
                    if (!existingList.cards) existingList.cards = [];
                    
                    // Remove deleted cards
                    for (let c = existingList.cards.length - 1; c >= 0; c--) {
                        const existingCardId = parseInt(existingList.cards[c].id, 10);

                        // Safety protection: Never delete the active card that the user is currently viewing/editing,
                        // unless it is confirmed present in another list or is archived
                        if (activeCardId && existingCardId === activeCardId) {
                            const foundInAnyNewList = newLists.some(nl => (nl.cards || []).some(nc => parseInt(nc.id, 10) === activeCardId));
                            if (!foundInAnyNewList) {
                                continue;
                            }
                        }

                        if (!newCardIds.has(existingCardId)) {
                            existingList.cards.splice(c, 1);
                        }
                    }

                    // Add or update cards
                    newList.cards.forEach((newCard, cIndex) => {
                        let existingCard = existingList.cards.find(c => parseInt(c.id, 10) === parseInt(newCard.id, 10));
                        if (!existingCard) {
                            existingList.cards.splice(cIndex, 0, newCard);
                        } else {
                            // Update card properties in place
                            Object.assign(existingCard, newCard);
                            // Adjust position in array if needed
                            if (existingList.cards.indexOf(existingCard) !== cIndex) {
                                existingList.cards.splice(existingList.cards.indexOf(existingCard), 1);
                                existingList.cards.splice(cIndex, 0, existingCard);
                            }
                        }
                    });
                }
            }
        });

        // 3. Sort lists
        this.lists.sort((a, b) => (a.position || 0) - (b.position || 0));

        if (listStructureChanged) {
            this.$nextTick(() => {
                this.initSortable();
            });
        }
      }

      this.loadBoardMembers();

      if (activeCardId) {
        const stillExists = this.lists.some(list => (list.cards || []).some(card => parseInt(card.id, 10) === activeCardId));
        if (stillExists) {
          this.refreshActiveCard();
        } else {
          if (this.activeCard && !this.activeCard.is_archived) {
            // Keep card open if actively being interacted with
          } else {
            this.closeCard();
          }
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
