import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import Sortable from 'sortablejs';
import Chart from 'chart.js/auto';

// Expose globally for inline scripts
window.Chart = Chart;
window.Sortable = Sortable;

// Register Alpine.js plugins
Alpine.plugin(collapse);
Alpine.plugin(focus);

// ── Helpers ───────────────────────────────────────────────────────────────

window.csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function api(url, options = {}) {
    const defaults = {
        headers: {
            'X-CSRF-TOKEN': window.csrf(),
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
    };
    const res = await fetch(url, { ...defaults, ...options, headers: { ...defaults.headers, ...(options.headers || {}) } });
    const data = await res.json().catch(() => ({}));
    if (! res.ok) throw { status: res.status, message: data.message || 'Request failed', errors: data.errors };
    return data;
}

window.api = api;

window.dgtGoBack = (fallback = '/boards') => {
    let hasSameAppReferrer = false;

    try {
        hasSameAppReferrer = document.referrer
            ? new URL(document.referrer).origin === window.location.origin
            : false;
    } catch (_) {
        hasSameAppReferrer = false;
    }

    if (window.history.length > 1 && hasSameAppReferrer) {
        window.history.back();
        return;
    }

    window.location.href = fallback;
};

const makeInitialsAvatar = function(name = 'User', color = '#4f46e5') {
    const cleanName = String(name || 'User').trim().replace(/\s+/g, ' ');
    const parts = cleanName.includes('@') ? [cleanName.split('@')[0]] : cleanName.split(' ').filter(Boolean);
    const initials = (parts.length > 1
        ? `${parts[0][0] || ''}${parts[parts.length - 1][0] || ''}`
        : (parts[0] || 'U').slice(0, 2)
    ).toUpperCase() || 'U';
    const safeColor = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(color) ? color : '#4f46e5';
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128"><rect width="128" height="128" rx="64" fill="${safeColor}"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="#fff" font-family="Inter, Arial, sans-serif" font-size="44" font-weight="800">${initials}</text></svg>`;

    return `data:image/svg+xml;base64,${btoa(unescape(encodeURIComponent(svg)))}`;
};

window.dgtInitialsAvatar = makeInitialsAvatar;

window.dgtAvatarFallback = function(img) {
    if (! img || img.dataset.avatarFallbackApplied === 'true') return;

    const className = String(img.className || '');
    const avatarLike = className.includes('avatar')
        || className.includes('k-card-avatar')
        || className.includes('kanban-card-avatar')
        || (className.includes('rounded-full') && (img.alt || img.title || img.dataset.avatarName));

    if (! avatarLike) return;

    img.dataset.avatarFallbackApplied = 'true';
    img.src = makeInitialsAvatar(
        img.dataset.avatarName || img.alt || img.title || 'User',
        img.dataset.avatarColor || '#4f46e5',
    );
};

document.addEventListener('error', (event) => {
    if (event.target instanceof HTMLImageElement) {
        window.dgtAvatarFallback(event.target);
    }
}, true);

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('img').forEach((img) => {
        if (img.complete && img.naturalWidth === 0) {
            window.dgtAvatarFallback(img);
        }
    });
});

// ── Global Alpine Components ──────────────────────────────────────────────

// Sidebar
Alpine.data('sidebar', () => ({
    mobileOpen: false,
    collapsed: false,
    isDesktop: window.innerWidth >= 1024,
    storageKey: 'dgt-sidebar-collapsed',
    scrollStorageKey: 'dgt-sidebar-scroll-top',

    init() {
        this.collapsed = localStorage.getItem(this.storageKey) === 'true';
        this.isDesktop = window.innerWidth >= 1024;

        window.addEventListener('resize', () => {
            this.isDesktop = window.innerWidth >= 1024;
            if (this.isDesktop) this.mobileOpen = false;
        });

        // Listen for the bottom nav "More" button to open sidebar on mobile
        window.addEventListener('open-mobile-sidebar', () => {
            if (! this.isDesktop) this.mobileOpen = true;
        });

        this.$nextTick(() => this.initScrollMemory());
    },

    toggle() {
        if (this.isDesktop) {
            this.toggleCollapse();
            return;
        }

        this.mobileOpen = !this.mobileOpen;
    },

    toggleMobile() {
        this.mobileOpen = !this.mobileOpen;
    },

    close() {
        this.mobileOpen = false;
    },

    toggleCollapse() {
        this.collapsed = !this.collapsed;
        localStorage.setItem(this.storageKey, String(this.collapsed));
        this.mobileOpen = false;
    },

    expandSidebar() {
        this.collapsed = false;
        localStorage.setItem(this.storageKey, 'false');
    },

    initScrollMemory() {
        const sidebar = document.getElementById('sidebar');
        if (! sidebar) return;

        const savedScrollTop = Number(localStorage.getItem(this.scrollStorageKey) || 0);

        if (sidebar.dataset.scrollRestored !== 'true' && savedScrollTop > 0) {
            // Temporarily disable smooth scroll to prevent animated jump
            const originalBehavior = sidebar.style.scrollBehavior;
            sidebar.style.scrollBehavior = 'auto';
            
            sidebar.scrollTop = savedScrollTop;
            sidebar.dataset.scrollRestored = 'true';

            // Restore after the frame paints
            requestAnimationFrame(() => {
                sidebar.style.scrollBehavior = originalBehavior;
            });
        }

        const saveScrollTop = () => {
            localStorage.setItem(this.scrollStorageKey, String(sidebar.scrollTop));
        };

        sidebar.addEventListener('scroll', saveScrollTop, { passive: true });

        window.addEventListener('pagehide', saveScrollTop);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') saveScrollTop();
        });

        sidebar.addEventListener('click', (event) => {
            // Save scroll position for any sidebar link click (including submenu tools)
            if (event.target.closest('a[href]')) {
                saveScrollTop();
            }
        }, true);
    },
}));

// Theme System
Alpine.data('themeSystem', () => ({
    theme: 'light',
    initTheme() {
        if (localStorage.getItem('theme')) {
            this.theme = localStorage.getItem('theme');
        } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            this.theme = 'dark';
        }
        this.applyTheme();
    },
    toggleTheme() {
        this.theme = this.theme === 'light' ? 'dark' : 'light';
        localStorage.setItem('theme', this.theme);
        this.applyTheme();
    },
    applyTheme() {
        if (this.theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
    }
}));

// Toast
Alpine.data('toast', () => ({
    messages: [],
    show(msg, type = 'success', duration = 4000) {
        const id = Date.now();
        this.messages.push({ id, msg, type });
        setTimeout(() => this.remove(id), duration);
    },
    remove(id) { this.messages = this.messages.filter(m => m.id !== id); },
}));

// Dropdown
Alpine.data('dropdown', () => ({
    open: false,
    toggle() { this.open = !this.open; },
    close() { this.open = false; },
}));

// ── Kanban Board ──────────────────────────────────────────────────────────

Alpine.data('kanbanBoard', (config = {}) => ({
    // State
    cardModal: null,
    createModal: false,
    loading: false,
    activeCard: null,
    toast: null,

    // Filters
    filterLabel: '',
    filterPriority: '',
    filterAssignee: '',
    searchQuery: '',

    // Create form
    form: {
        title: '', description: '', label: '', sub_label: '',
        priority: 'medium', deadline: '', assignees: [],
    },
    subLabels: [],
    formErrors: {},
    formLoading: false,

    // Card detail modal state
    detailCard: null,
    detailTab: 'details',
    newComment: '',
    commentLoading: false,
    newChecklistTitle: '',
    newItemContent: {},
    uploadLoading: false,
    rejectReason: '',
    showRejectModal: false,

    init() {
        this.$nextTick(() => this.initDragDrop());
    },

    // ── Drag & Drop ────────────────────────────────────────────────────

    initDragDrop() {
        document.querySelectorAll('[data-kanban-column]').forEach(col => {
            Sortable.create(col, {
                group: 'kanban',
                animation: 200,
                ghostClass: 'kanban-card-ghost',
                dragClass: 'kanban-card-dragging',
                handle: '.card-drag-handle',
                onEnd: (evt) => this.onCardDrop(evt),
            });
        });
    },

    async onCardDrop(evt) {
        const cardId    = evt.item.dataset.cardId;
        const newStatus = evt.to.dataset.status;
        const position  = evt.newIndex;

        try {
            await api(`/kanban/cards/${cardId}/move`, {
                method: 'PATCH',
                body: JSON.stringify({ status: newStatus, position }),
            });
            this.showToast('Card moved!', 'success');
        } catch (err) {
            this.showToast(err.message || 'Could not move card.', 'error');
            // Revert DOM move
            evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
        }
    },

    // ── Label / Sub-label ──────────────────────────────────────────────

    async onLabelChange(label) {
        this.form.sub_label = '';
        this.subLabels = [];
        if (! label) return;
        try {
            const data = await api(`/kanban/sub-labels?label=${encodeURIComponent(label)}`);
            this.subLabels = data.sub_labels;
        } catch (_) {}
    },

    // ── Create Card ───────────────────────────────────────────────────

    async submitCreate() {
        this.formLoading = true;
        this.formErrors = {};
        try {
            const data = await api('/kanban/cards', {
                method: 'POST',
                body: JSON.stringify(this.form),
            });
            this.showToast(data.message, 'success');
            this.createModal = false;
            this.form = { title: '', description: '', label: '', sub_label: '', priority: 'medium', deadline: '', assignees: [] };
            this.appendCard(data.card);
        } catch (err) {
            if (err.errors) this.formErrors = err.errors;
            this.showToast(err.message, 'error');
        } finally {
            this.formLoading = false;
        }
    },

    appendCard(card) {
        const col = document.querySelector(`[data-kanban-column][data-status="${card.status}"]`);
        if (col) {
            const el = document.getElementById(`card-tpl-${card.id}`);
            // if (! el) window.location.reload(); // fallback
        } else {
            // window.location.reload();
        }
    },

    // ── Card Detail Modal ─────────────────────────────────────────────

    async openCard(cardId) {
        this.detailCard = null;
        this.detailTab  = 'details';
        this.cardModal  = true;
        try {
            const data = await api(`/kanban/cards/${cardId}`);
            this.detailCard = data;
        } catch (err) {
            this.showToast('Could not load card.', 'error');
            this.cardModal = false;
        }
    },

    closeCard() {
        this.cardModal  = false;
        this.detailCard = null;
    },

    // ── Comments ──────────────────────────────────────────────────────

    async submitComment(cardId) {
        if (! this.newComment.trim()) return;
        this.commentLoading = true;
        try {
            const data = await api(`/kanban/cards/${cardId}/comments`, {
                method: 'POST',
                body: JSON.stringify({ content: this.newComment }),
            });
            this.detailCard.card.comments.push(data.comment);
            this.newComment = '';
        } catch (err) {
            this.showToast(err.message, 'error');
        } finally {
            this.commentLoading = false;
        }
    },

    // ── Checklist ─────────────────────────────────────────────────────

    async addChecklist(cardId) {
        if (! this.newChecklistTitle.trim()) return;
        try {
            const data = await api(`/kanban/cards/${cardId}/checklists`, {
                method: 'POST',
                body: JSON.stringify({ title: this.newChecklistTitle }),
            });
            this.detailCard.card.checklists.push(data.checklist);
            this.newChecklistTitle = '';
        } catch (err) {
            this.showToast(err.message, 'error');
        }
    },

    async addChecklistItem(cardId, checklistId, idx) {
        const content = this.newItemContent[checklistId];
        if (! content?.trim()) return;
        try {
            const data = await api(`/kanban/cards/${cardId}/checklists/${checklistId}/items`, {
                method: 'POST',
                body: JSON.stringify({ content }),
            });
            this.detailCard.card.checklists[idx].items.push(data.item);
            this.newItemContent[checklistId] = '';
        } catch (err) {
            this.showToast(err.message, 'error');
        }
    },

    async toggleItem(cardId, checklistId, checklistIdx, itemIdx) {
        const item = this.detailCard.card.checklists[checklistIdx].items[itemIdx];
        try {
            const data = await api(`/kanban/cards/${cardId}/checklists/${checklistId}/items/${item.id}/toggle`, {
                method: 'PATCH',
            });
            this.detailCard.card.checklists[checklistIdx].items[itemIdx] = data.item;
        } catch (err) {
            this.showToast(err.message, 'error');
        }
    },

    // ── File Upload ───────────────────────────────────────────────────

    async uploadFiles(cardId, event) {
        const files = event.target.files;
        if (! files.length) return;
        this.uploadLoading = true;

        const formData = new FormData();
        for (const file of files) formData.append('files[]', file);

        try {
            const res = await fetch(`/kanban/cards/${cardId}/files`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': window.csrf(), 'Accept': 'application/json' },
                body: formData,
            });
            const data = await res.json();
            if (! res.ok) throw data;
            this.detailCard.card.files.push(...data.files);
            this.showToast(`${data.files.length} file(s) uploaded!`, 'success');
        } catch (err) {
            this.showToast(err.message || 'Upload failed.', 'error');
        } finally {
            this.uploadLoading = false;
            event.target.value = '';
        }
    },

    async deleteFile(cardId, fileId, fileIdx) {
        if (! await window.confirmModal({
            title: 'Delete file?',
            message: 'Delete this file from the card?',
            confirmText: 'Delete file',
            tone: 'danger',
        })) return;
        try {
            await api(`/kanban/cards/${cardId}/files/${fileId}`, { method: 'DELETE' });
            this.detailCard.card.files.splice(fileIdx, 1);
            this.showToast('File deleted.', 'success');
        } catch (err) {
            this.showToast(err.message, 'error');
        }
    },

    // ── Approval Workflow ─────────────────────────────────────────────

    async approveCard(cardId) {
        if (! await window.confirmModal({
            title: 'Approve task?',
            message: 'Approve this task? Boss will be notified by email.',
            confirmText: 'Approve task',
            tone: 'warning',
        })) return;
        try {
            const data = await api(`/kanban/cards/${cardId}/approve`, { method: 'POST' });
            this.detailCard.card.status = 'approved';
            this.showToast(data.message, 'success');
            // setTimeout(() => window.location.reload(), 1500);
        } catch (err) {
            this.showToast(err.message, 'error');
        }
    },

    async rejectCard(cardId) {
        if (! this.rejectReason.trim()) {
            this.showToast('Please enter a rejection reason.', 'error');
            return;
        }
        try {
            const data = await api(`/kanban/cards/${cardId}/reject`, {
                method: 'POST',
                body: JSON.stringify({ reason: this.rejectReason }),
            });
            this.detailCard.card.status = 'rejected';
            this.showRejectModal = false;
            this.rejectReason = '';
            this.showToast(data.message, 'success');
            // setTimeout(() => window.location.reload(), 1500);
        } catch (err) {
            this.showToast(err.message, 'error');
        }
    },

    // ── Filters ───────────────────────────────────────────────────────

    isCardVisible(card) {
        if (this.searchQuery && ! card.title.toLowerCase().includes(this.searchQuery.toLowerCase())) return false;
        if (this.filterLabel && card.label !== this.filterLabel) return false;
        if (this.filterPriority && card.priority !== this.filterPriority) return false;
        if (this.filterAssignee && ! card.assignees?.some(a => a.id == this.filterAssignee)) return false;
        return true;
    },

    // ── Toast helper ──────────────────────────────────────────────────

    showToast(msg, type = 'success') {
        // Dispatches to the global toast component via custom event
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg, type } }));
    },
}));

// ── Start Livewire + Alpine ────────────────────────────────────────────────
// Livewire's bundle owns window.Alpine/window.Livewire and starts both itself
// (once, on DOMContentLoaded) since we don't set window.livewireScriptConfig.
// Do not call Alpine.start()/Livewire.start() here — that would double-start.

// ── Turbo Sidebar Scroll Persistence ──────────────────────────────────────
document.addEventListener('turbo:before-render', (event) => {
    const currentSidebar = document.getElementById('sidebar');
    if (currentSidebar) {
        event.detail.newBody.setAttribute('data-sidebar-scroll', currentSidebar.scrollTop);
    }
});

document.addEventListener('turbo:render', () => {
    const sidebar = document.getElementById('sidebar');
    const scrollPos = document.body.getAttribute('data-sidebar-scroll');
    
    if (sidebar && scrollPos) {
        const originalBehavior = sidebar.style.scrollBehavior;
        sidebar.style.scrollBehavior = 'auto'; // Disable smooth scrolling temporarily
        sidebar.scrollTop = Number(scrollPos);
        
        requestAnimationFrame(() => {
            sidebar.style.scrollBehavior = originalBehavior;
        });
    }
});
