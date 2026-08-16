function websitesApp() {
    return {
        // Search state
        searchQuery: '',
        filterMember: '',
        filterClass: '',
        filterApprovalStatus: '',
        matchesSearch(name, url, category, handlerId, status, isFollowUpTab = false) {
            let matchText = true;
            let matchMember = true;
            let matchClass = true;
            let matchStatus = true;
            if (this.searchQuery) {
                let q = this.searchQuery.toLowerCase();
                matchText = (name && name.toLowerCase().includes(q)) || (url && url.toLowerCase().includes(q));
            }
            if (!isFollowUpTab) {
                if (this.filterMember) {
                    matchMember = (handlerId == this.filterMember);
                }
                if (this.filterClass) {
                    matchClass = (category == this.filterClass);
                }
                if (this.filterApprovalStatus) {
                    if (this.filterApprovalStatus === 'qc-approved') {
                        matchStatus = (status === 'Maintenance QC Checking' || status === 'QC Checking');
                    } else if (this.filterApprovalStatus === 'supervisor-approved') {
                        matchStatus = (status === 'Maintenance Supervisor Checking' || status === 'Supervisor Checking');
                    }
                }
            }
            return matchText && matchMember && matchClass && matchStatus;
        },
        hasMatchingWebsites(websites, isFollowUpTab = false) {
            if (isFollowUpTab) {
                if (!this.searchQuery) return true;
            } else {
                if (!this.searchQuery && !this.filterMember && !this.filterClass && !this.filterApprovalStatus) return true;
            }
            return websites.some(w => {
                let matchText = true;
                let matchMember = true;
                let matchClass = true;
                let matchStatus = true;
                
                if (this.searchQuery) {
                    const q = this.searchQuery.toLowerCase();
                    matchText = (w.name && w.name.toLowerCase().includes(q)) || 
                                  (w.url && w.url.toLowerCase().includes(q));
                }
                if (!isFollowUpTab) {
                    if (this.filterMember) {
                        matchMember = (w.handled_by == this.filterMember);
                    }
                    if (this.filterClass && w.category !== undefined) {
                        matchClass = (w.category == this.filterClass);
                    }
                    if (this.filterApprovalStatus && w.status !== undefined) {
                        if (this.filterApprovalStatus === 'qc-approved') {
                            matchStatus = (w.status === 'Maintenance QC Checking' || w.status === 'QC Checking');
                        } else if (this.filterApprovalStatus === 'supervisor-approved') {
                            matchStatus = (w.status === 'Maintenance Supervisor Checking' || w.status === 'Supervisor Checking');
                        }
                    }
                }
                return matchText && matchMember && matchClass && matchStatus;
            });
        },

        // Member form state
        memberForm: {
            role: 'Developer'
        },
        selectedUserIds: [],
        memberUserSearch: '',
        isEditing: false,
        editMember(userId, role) {
            this.isEditing = true;
            this.selectedUserIds = [userId];
            this.memberForm.role = role;
        },

        // Modal state
        showCreateModal:      false,
        showManageClassesModal: localStorage.getItem('showManageClassesModal') === 'true',
        showManageMembersModal: localStorage.getItem('showManageMembersModal') === 'true',
        showProgressModal:    false,
        showQcModal:          false,
        showSupervisorModal:  false,
        showMaintenanceModal: false,
        showFollowUpModal:    false,
        showEditFollowUpModal:false,
        showExportModal:      false,
        editFollowUpAction:   '',
        editFollowUpForm: {
        website_id: '', type: '', title: '', url: '', google_indexed: '', assigned_to: '', note: '', created_at: ''
        },
        showHistoryModal:     false,
        historyLoading:       false,
        prefetchedHistories: {},
        prefetchingHistories: {},
        currentUserId: null,


        prefetchHistory(websiteId) {
            if (this.prefetchedHistories[websiteId]) {
                return Promise.resolve(this.prefetchedHistories[websiteId]);
            }
            if (!this.prefetchingHistories[websiteId]) {
                this.prefetchingHistories[websiteId] = fetch(`/websites/${websiteId}/history`)
                    .then(response => {
                        if (!response.ok) throw new Error('Failed to load history');
                        return response.json();
                    })
                    .then(parsedLogs => {
                        parsedLogs = parsedLogs.map(log => {
                            if (!log.attachment_path && log.note && log.note.includes(' | File: ')) {
                                const parts = log.note.split(' | File: ');
                                log.note = parts[0];
                                log.attachment_name = parts[1];
                                log.attachment_path = 'website-error-references/' + parts[1];
                            }
                            if (!Array.isArray(log.attachments)) log.attachments = [];
                            if (!log.attachments.length && log.attachment_path) {
                                log.attachments = [{
                                    id: 'legacy', path: log.attachment_path, name: log.attachment_name || 'Attached File'
                                }];
                            }
                            return log;
                        });
                        parsedLogs.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                        this.prefetchedHistories[websiteId] = parsedLogs;
                        return parsedLogs;
                    })
                    .catch(err => {
                        console.error(err);
                        this.prefetchingHistories[websiteId] = null; // allow retry
                    });
            }
            return this.prefetchingHistories[websiteId];
        },

        // Collapsible groups state
        collapsedGroups: {},
        toggleGroup(groupKey) {
            this.collapsedGroups = {
                ...this.collapsedGroups,
                [groupKey]: !this.collapsedGroups[groupKey]
            };
            localStorage.setItem('collapsedGroups', JSON.stringify(this.collapsedGroups));
        },
        isGroupCollapsed(groupKey) {
            return !!this.collapsedGroups[groupKey];
        },

        // History modal
        historyLogs: [],
        historyWebsiteId: null,
        historyWebsiteName: '',
        historyType: '',
        canManageErrorHistory: null,
        showAttachmentPreview: false,
        previewFile: null,
        previewUrl: '',
        previewDownloadUrl: '',
        previewLoading: false,
        previewIsImage: false,
        previewZoom: 100,
        previewFitNonce: 0,
        previewImageNaturalWidth: 0,
        previewImageNaturalHeight: 0,
        previewTouchStartDistance: 0,
        previewTouchStartZoom: 100,
        previewPanX: 0,
        previewPanY: 0,
        previewPanStartX: 0,
        previewPanStartY: 0,
        previewPanOriginX: 0,
        previewPanOriginY: 0,
        previewIsPanning: false,
        showHistoryEditModal: false,
        historyEditLog: null,
        newHistoryComment: '',
        newHistoryFilesCount: 0,
        newHistoryFilesPreviews: [],
        updateNewHistoryFilesCount() {
            const files = this.$refs.newHistoryFiles?.files || [];
            this.newHistoryFilesCount = files.length;
            
            // Clean up old previews to avoid memory leaks
            this.newHistoryFilesPreviews.forEach(p => URL.revokeObjectURL(p.url));
            this.newHistoryFilesPreviews = [];
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (file.type.startsWith('image/')) {
                    this.newHistoryFilesPreviews.push({
                        url: URL.createObjectURL(file),
                        name: file.name
                    });
                }
            }
        },
        removeNewHistoryFile(index) {
            const inputEl = this.$refs.newHistoryFiles;
            if (!inputEl || !inputEl.files) return;
            const dt = new DataTransfer();
            const files = inputEl.files;
            for (let i = 0; i < files.length; i++) {
                if (i !== index) {
                    dt.items.add(files[i]);
                }
            }
            inputEl.files = dt.files;
            this.updateNewHistoryFilesCount();
        },
        handlePasteImage(e, inputRefName) {
            const files = e.clipboardData?.files;
            if (!files || !files.length) return;
            
            let hasImage = false;
            const dataTransfer = new DataTransfer();
            
            const inputEl = this.$refs[inputRefName];
            if (!inputEl) return;
            
            if (inputEl.files) {
                for (let i = 0; i < inputEl.files.length; i++) {
                    dataTransfer.items.add(inputEl.files[i]);
                }
            }

            for (let i = 0; i < files.length; i++) {
                if (files[i].type.startsWith('image/')) {
                    const ext = files[i].type.split('/')[1] || 'png';
                    const file = new File([files[i]], `pasted-image-${Date.now()}-${i}.${ext}`, { type: files[i].type });
                    dataTransfer.items.add(file);
                    hasImage = true;
                }
            }

            if (hasImage) {
                inputEl.files = dataTransfer.files;
                inputEl.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
        historyEditNote: '',
        historyEditRemoveIds: [],
        historyEditSelectedFileNames: [],

        // Delete class modal
        showDeleteClassModal: false,
        classToDelete: '',
        classToDeleteId: '',
        
        // Edit class state
        editingClass: null,
        editingClassName: '',
        classSearchQuery: '',

        // Progress modal
        progressModalTitle:   '',
        progressModalCurrent: 0,
        progressModalAction:  '',
        progressModalType:    'build',

        // QC modal
        qcModalName:   '',
        qcModalAction: '',
        qcErrorFilesCount: 0,
        qcErrorFileNames: [],
        qcApproveFilesPreviews: [],
        updateQcApproveFilesCount() {
            const files = this.$refs.qcApproveFiles?.files || [];
            this.qcApproveFilesPreviews.forEach(p => URL.revokeObjectURL(p.url));
            this.qcApproveFilesPreviews = [];
            for (let i = 0; i < files.length; i++) {
                if (files[i].type.startsWith('image/')) {
                    this.qcApproveFilesPreviews.push({ url: URL.createObjectURL(files[i]), name: files[i].name });
                }
            }
        },
        removeQcApproveFile(index) {
            const inputEl = this.$refs.qcApproveFiles;
            if (!inputEl || !inputEl.files) return;
            const dt = new DataTransfer();
            for (let i = 0; i < inputEl.files.length; i++) {
                if (i !== index) dt.items.add(inputEl.files[i]);
            }
            inputEl.files = dt.files;
            this.updateQcApproveFilesCount();
        },
        updateQcErrorFilesCount() {
            const files = this.$refs.qcErrorFiles?.files || [];
            this.qcErrorFilesCount = files.length;
            this.qcErrorFileNames = Array.from(files).map(f => f.name);
        },

        // Supervisor modal
        supervisorModalName:   '',
        supervisorModalAction: '',
        supervisorApproveFilesPreviews: [],
        updateSupervisorApproveFilesCount() {
            const files = this.$refs.supervisorApproveFiles?.files || [];
            this.supervisorApproveFilesPreviews.forEach(p => URL.revokeObjectURL(p.url));
            this.supervisorApproveFilesPreviews = [];
            for (let i = 0; i < files.length; i++) {
                if (files[i].type.startsWith('image/')) {
                    this.supervisorApproveFilesPreviews.push({ url: URL.createObjectURL(files[i]), name: files[i].name });
                }
            }
        },
        removeSupervisorApproveFile(index) {
            const inputEl = this.$refs.supervisorApproveFiles;
            if (!inputEl || !inputEl.files) return;
            const dt = new DataTransfer();
            for (let i = 0; i < inputEl.files.length; i++) {
                if (i !== index) dt.items.add(inputEl.files[i]);
            }
            inputEl.files = dt.files;
            this.updateSupervisorApproveFilesCount();
        },

        // Maintenance modal
        maintenanceModalName:   '',
        maintenanceModalAction: '',

        init() {
            // Initialize collapsedGroups from localStorage
            try {
                this.collapsedGroups = JSON.parse(localStorage.getItem('collapsedGroups') || '{}');
            } catch (e) {
                this.collapsedGroups = {};
            }

            // Watch visibility states to save to localStorage
            this.$watch('showManageClassesModal', value => localStorage.setItem('showManageClassesModal', value));
            this.$watch('showManageMembersModal', value => {
                localStorage.setItem('showManageMembersModal', value);
                if (!value) {
                    this.selectedUserIds = [];
                    this.memberUserSearch = '';
                    this.isEditing = false;
                }
            });

            // Close modals on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    // Close attachment preview first if open
                    if (this.showAttachmentPreview) {
                        this.closeAttachmentPreview();
                        e.preventDefault();
                        e.stopPropagation();
                        return;
                    }
                    // Close history log edit modal if open (keeping main history modal open)
                    if (this.showHistoryEditModal) {
                        this.closeHistoryEditModal();
                        e.preventDefault();
                        e.stopPropagation();
                        return;
                    }
                    // Close delete class modal
                    if (this.showDeleteClassModal) {
                        this.showDeleteClassModal = false;
                        this.classToDelete = '';
                        this.classToDeleteId = '';
                        return;
                    }
                    // Close other standard modals
                    this.showCreateModal = false;
                    this.showProgressModal = false;
                    this.showQcModal = false;
                    this.showSupervisorModal = false;
                    this.showMaintenanceModal = false;
                    this.showFollowUpModal = false;
                    this.showEditFollowUpModal = false;
                    this.showHistoryModal = false;
                    this.showExportModal = false;
                    if (this.showManageMembersModal) {
                        this.showManageMembersModal = false;
                        this.selectedUserIds = [];
                        this.memberUserSearch = '';
                        this.isEditing = false;
                    }
                }
            });

            // Restore scroll position after a reload (e.g. form submission)
            const scrollPos = sessionStorage.getItem('websitesScrollPos');
            if (scrollPos) {
                setTimeout(() => {
                    window.scrollTo({ top: parseInt(scrollPos), behavior: 'instant' });
                }, 50);
                sessionStorage.removeItem('websitesScrollPos');
            }

            // Save scroll position on any form submit to preserve it across the reload
            document.addEventListener('submit', () => {
                sessionStorage.setItem('websitesScrollPos', window.scrollY);
            });
        },

        openProgressModal(websiteId, websiteName, currentPct, type) {
            this.progressModalTitle   = (type === 'maintenance' ? '🔧 Maintenance' : '📊 Build') + ' Progress: ' + websiteName;
            this.progressModalCurrent = currentPct;
            this.progressModalType    = type;
            if (type === 'maintenance') {
                this.progressModalAction = `/websites/${websiteId}/maintenance-progress`;
            } else {
                this.progressModalAction = `/websites/${websiteId}/progress`;
            }
            this.showProgressModal = true;
        },

        qcModalTriggerEvent: null,
        openQcModal(websiteId, websiteName, event = null) {
            this.qcModalName   = websiteName;
            this.qcModalAction = `/websites/${websiteId}/approve-qc`;
            this.qcModalTriggerEvent = event;
            this.showQcModal   = true;
            // Reset button text just in case it was used before
            setTimeout(() => {
                let btn = document.getElementById('qcApproveSubmitBtn');
                if (btn) {
                    btn.innerHTML = '✓ Approve QC';
                    btn.disabled = false;
                    btn.classList.remove('bg-emerald-500', 'hover:bg-emerald-600');
                    btn.classList.add('bg-amber-500', 'hover:bg-amber-600');
                }
            }, 50);
        },

        async submitProgress(e) {
            let form = e.target;
            let btn = form.querySelector('button[type=submit]');
            let originalText = btn.innerHTML;
            btn.innerHTML = '...';
            btn.disabled = true;
            
            let formData = new FormData(form);
            try {
                let res = await fetch(this.progressModalAction, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                
                let data = await res.json();
                if (data.success) {
                    btn.innerHTML = '✓ Complete';
                    btn.classList.remove('btn-primary');
                    btn.classList.add('bg-emerald-500', 'hover:bg-emerald-600', 'text-white', 'border-emerald-500');
                    setTimeout(() => {
                        this.showProgressModal = false;
                        window.location.reload();
                    }, 300);
                } else {
                    alert(data.message || 'Error occurred');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        },

        async submitAjaxForm(e, modalProp) {
            let form = e.target;
            let btn = form.querySelector('button[type=submit]');
            let originalText = btn.innerHTML;
            btn.innerHTML = '...';
            btn.disabled = true;
            
            let formData = new FormData(form);
            try {
                let res = await fetch(form.action, {
                    method: form.method || 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                let data = await res.json();
                if (data.success || res.ok) {
                    btn.innerHTML = '✓ Complete';
                    btn.classList.remove('btn-primary');
                    btn.classList.add('bg-emerald-500', 'hover:bg-emerald-600', 'text-white', 'border-emerald-500');
                    setTimeout(() => {
                        this[modalProp] = false;
                        window.location.reload();
                    }, 300);
                } else {
                    let errs = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Error occurred');
                    alert(errs);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                alert('An error occurred while submitting.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        },

        async submitQcApprove(e) {
            let form = e.target;
            let btn = form.querySelector('button[type=submit]');
            let originalText = btn.innerHTML;
            btn.innerHTML = `<span class='flex items-center justify-center gap-2'><svg class='animate-spin h-4 w-4' xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24'><circle class='opacity-25' cx='12' cy='12' r='10' stroke='currentColor' stroke-width='4'></circle><path class='opacity-75' fill='currentColor' d='M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z'></path></svg> Progressing...</span>`;
            btn.disabled = true;
            
            let formData = new FormData(form);
            try {
                let res = await fetch(this.qcModalAction, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                
                let data = await res.json();
                if (data.success) {
                    btn.innerHTML = '✓ Complete';
                    btn.classList.remove('bg-amber-500', 'hover:bg-amber-600');
                    btn.classList.add('bg-emerald-500', 'hover:bg-emerald-600');
                    
                    if (this.qcModalTriggerEvent) {
                        let actionsDiv = this.qcModalTriggerEvent.target.closest('div.mt-auto.pt-3') || this.qcModalTriggerEvent.target.closest('.flex');
                        if (actionsDiv) {
                            actionsDiv.innerHTML = `<span class="text-xs font-bold text-amber-600 px-3 py-1.5 flex-1 text-center bg-amber-50 dark:bg-amber-900/20 rounded border border-amber-200 dark:border-amber-800 w-full block">Awaiting Supervisor Approval</span>`;
                        }
                    }
                    setTimeout(() => {
                        this.showQcModal = false;
                        if (window.Turbo) {
                            Turbo.visit(window.location.pathname + '?tab=' + (new URLSearchParams(window.location.search).get('tab') || 'build-progress'));
                        }
                    }, 500);
                } else {
                    alert(data.message || 'Error occurred');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        },

        async submitSupervisorApprove(e) {
            let form = e.target;
            let btn = form.querySelector('button[type=submit]');
            let originalText = btn.innerHTML;
            btn.innerHTML = `<span class='flex items-center justify-center gap-2'><svg class='animate-spin h-4 w-4' xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24'><circle class='opacity-25' cx='12' cy='12' r='10' stroke='currentColor' stroke-width='4'></circle><path class='opacity-75' fill='currentColor' d='M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z'></path></svg> Progressing...</span>`;
            btn.disabled = true;
            
            let formData = new FormData(form);
            try {
                let res = await fetch(this.supervisorModalAction, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                
                let data = await res.json();
                if (data.success) {
                    btn.innerHTML = '✓ Live!';
                    btn.classList.add('bg-emerald-500', 'hover:bg-emerald-600');
                    setTimeout(() => {
                        this.showSupervisorModal = false;
                        if (window.Turbo) {
                            Turbo.visit(window.location.pathname + '?tab=live');
                        } else {
                            window.location.href = window.location.pathname + '?tab=live';
                        }
                    }, 500);
                } else {
                    alert(data.message || 'Error occurred');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        },

        async submitErrorForm(e, tabName) {
            let form = e.target;
            let btn = form.querySelector('button[type=submit]');
            let originalText = btn.innerHTML;
            btn.innerHTML = '...';
            btn.disabled = true;
            
            let formData = new FormData(form);
            try {
                let res = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                
                let data = await res.json();
                if (data.success) {
                    btn.innerHTML = '✓ Flagged';
                    btn.classList.add('bg-emerald-500', 'text-white');
                    setTimeout(() => {
                        window.location.href = '?tab=' + tabName;
                    }, 500);
                } else {
                    alert(data.message || 'Error occurred');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        },

        openSupervisorModal(websiteId, websiteName) {
            this.supervisorModalName   = websiteName;
            this.supervisorModalAction = `/websites/${websiteId}/approve-supervisor`;
            this.showSupervisorModal   = true;
        },

        openMaintenanceModal(websiteId, websiteName) {
            this.maintenanceModalName   = websiteName;
            this.maintenanceModalAction = `/websites/${websiteId}/start-maintenance`;
            this.showMaintenanceModal   = true;
        },

        // Error Modals
        showQcErrorModal: false,
        qcErrorModalName: '',
        qcErrorModalAction: '',
        openQcErrorModal(websiteId, websiteName) {
            this.qcErrorModalName   = websiteName;
            this.qcErrorModalAction = `/websites/${websiteId}/qc-error`;
            this.showQcErrorModal   = true;
        },

        showSupervisorErrorModal: false,
        supervisorErrorModalName: '',
        supervisorErrorModalAction: '',
        openSupervisorErrorModal(websiteId, websiteName) {
            this.supervisorErrorModalName   = websiteName;
            this.supervisorErrorModalAction = `/websites/${websiteId}/supervisor-error`;
            this.showSupervisorErrorModal   = true;
        },

        showErrorProgressModal: false,
        errorProgressModalName: '',
        errorProgressModalAction: '',
        errorProgressModalCurrent: 0,
        openErrorProgressModal(websiteId, websiteName, currentPct) {
            this.errorProgressModalName    = websiteName;
            this.errorProgressModalAction  = `/websites/${websiteId}/error-progress`;
            this.errorProgressModalCurrent = currentPct;
            this.showErrorProgressModal    = true;
        },

        showEditModal: false,
        editModalAction: '',
        editForm: {
            name: '', url: '', category: '', logo_url: '', handled_by: '', start_date: '', deadline: '', notes: ''
        },
        openEditModal(id, data) {
            this.editModalAction = `/websites/${id}`;
            this.editForm = {
                name: data.name || '',
                url: data.url || '',
                category: data.category || '',
                logo_url: data.logo_url || '',
                handled_by: data.handled_by || '',
                start_date: data.start_date ? data.start_date.substring(0, 10) : '',
                deadline: data.deadline ? data.deadline.substring(0, 10) : '',
                notes: data.notes || ''
            };
            this.showEditModal = true;
        },

        openEditFollowUpModal(id, data) {
            const standardTypes = ['blog_post', 'indexed_page', 'website_page', 'other'];
            let formType = data.type || '';
            let customType = '';
            
            if (formType && !standardTypes.includes(formType)) {
                customType = formType;
                formType = 'other';
            }

            this.editFollowUpAction = `/websites/follow-ups/${id}`;
            this.editFollowUpForm = {
                website_id: data.website_id || '',
                type: formType,
                custom_type: customType,
                url: data.url || '',
                assigned_to: data.assigned_to || '',
                note: data.note || '',
                created_at: data.created_at || ''
            };
            this.showEditFollowUpModal = true;
        },

        async openHistoryModal(websiteId, websiteName, type) {
            this.historyWebsiteId = websiteId;
            this.historyWebsiteName = websiteName;
            this.historyType = type;
            this.historyLogs = [];
            this.showHistoryModal = true;
            
            if (this.prefetchedHistories[websiteId]) {
                this.historyLoading = false;
                this.historyLogs = [...this.prefetchedHistories[websiteId]];
                return;
            }

            this.historyLoading = true;
            try {
                await this.prefetchHistory(websiteId);
                this.historyLogs = [...(this.prefetchedHistories[websiteId] || [])];
            } catch (err) {
                console.error(err);
                window.showToast('Failed to load website history.', 'error');
            } finally {
                this.historyLoading = false;
            }
        },

        // Returns storage URL - works with both relative paths and full URLs
        getStorageUrl(path) {
            if (!path) return '';
            if (path.startsWith('http://') || path.startsWith('https://')) return path;
            return '/storage/' + path;
        },

        getHistoryAttachmentUrl(log, action = 'view', file = null) {
            const target = file || (log?.attachments?.[0] ?? null);
            if (!log || !target?.path) return '';
            const fileQuery = target.id ? `?file=${encodeURIComponent(target.id)}` : '';
            if (log.id) return `null/${log.id}/attachment/${action}${fileQuery}`;
            return this.getStorageUrl(target.path);
        },

        isImageAttachment(file) {
            return !!((file?.name || file?.path || '').match(/\.(jpeg|jpg|gif|png|webp)$/i));
        },

        isPdfAttachment(file) {
            return !!((file?.name || file?.path || '').match(/\.pdf$/i));
        },

        getAttachmentExtension(file) {
            const name = file?.name || file?.path || '';
            return name.includes('.') ? name.split('.').pop().toUpperCase() : 'FILE';
        },

        canManageLog(log) {
            if (log?.action === 'comment') {
                return this.canManageErrorHistory || log?.user_id === this.currentUserId;
            }
            return this.canManageErrorHistory && ['qc_error', 'supervisor_error'].includes(log?.action);
        },

        openAttachmentPreview(log, file) {
            this.previewFile = file;
            this.previewUrl = this.getHistoryAttachmentUrl(log, 'view', file);
            this.previewDownloadUrl = this.getHistoryAttachmentUrl(log, 'download', file);
            this.previewIsImage = this.isImageAttachment(file);
            this.previewZoom = 100;
            this.previewImageNaturalWidth = 0;
            this.previewImageNaturalHeight = 0;
            this.resetPreviewPan();
            this.previewLoading = true;
            this.showAttachmentPreview = true;
            this.$nextTick(() => this.refreshPreviewFit());
        },

        openGenericAttachmentPreview(name, viewUrl, downloadUrl) {
            this.previewFile = { name };
            this.previewUrl = viewUrl;
            this.previewDownloadUrl = downloadUrl;
            this.previewIsImage = !!((name || '').match(/\.(jpeg|jpg|gif|png|webp)$/i));
            this.previewZoom = 100;
            this.previewImageNaturalWidth = 0;
            this.previewImageNaturalHeight = 0;
            this.resetPreviewPan();
            this.previewLoading = true;
            this.showAttachmentPreview = true;
            this.$nextTick(() => this.refreshPreviewFit());
        },

        closeAttachmentPreview() {
            this.showAttachmentPreview = false;
            this.previewFile = null;
            this.previewUrl = '';
            this.previewDownloadUrl = '';
            this.previewLoading = false;
            this.previewIsImage = false;
            this.previewZoom = 100;
            this.previewImageNaturalWidth = 0;
            this.previewImageNaturalHeight = 0;
            this.previewTouchStartDistance = 0;
            this.resetPreviewPan();
        },

        setPreviewZoom(value) {
            this.previewZoom = Math.min(400, Math.max(60, Math.round(value)));
            if (this.previewZoom <= 100) {
                this.resetPreviewPan();
            }
            this.refreshPreviewFit();
        },

        resetPreviewZoom() {
            this.setPreviewZoom(100);
            this.resetPreviewPan();
        },

        handlePreviewWheel(event) {
            if (!this.previewIsImage) return;
            this.setPreviewZoom(this.previewZoom + (event.deltaY < 0 ? 4 : -4));
        },

        handlePreviewImageLoad(event) {
            this.previewImageNaturalWidth = event.target.naturalWidth || 0;
            this.previewImageNaturalHeight = event.target.naturalHeight || 0;
            this.previewLoading = false;
            this.refreshPreviewFit();
        },

        previewTouchDistance(event) {
            if (!event.touches || event.touches.length < 2) return 0;
            const [first, second] = event.touches;
            return Math.hypot(first.clientX - second.clientX, first.clientY - second.clientY);
        },

        handlePreviewTouchStart(event) {
            if (!this.previewIsImage) return;
            if (event.touches.length >= 2) {
                event.preventDefault();
                this.previewTouchStartDistance = this.previewTouchDistance(event);
                this.previewTouchStartZoom = this.previewZoom;
                this.previewIsPanning = false;
                return;
            }

            if (event.touches.length === 1 && this.previewZoom > 100) {
                const touch = event.touches[0];
                this.startPreviewPan(touch);
            }
        },

        handlePreviewTouchMove(event) {
            if (!this.previewIsImage) return;
            if (event.touches.length >= 2 && this.previewTouchStartDistance) {
                event.preventDefault();
                const distance = this.previewTouchDistance(event);
                this.setPreviewZoom(this.previewTouchStartZoom * (distance / this.previewTouchStartDistance));
                return;
            }

            if (event.touches.length === 1 && this.previewIsPanning) {
                event.preventDefault();
                this.movePreviewPan(event.touches[0]);
            }
        },

        resetPreviewPan() {
            this.previewPanX = 0;
            this.previewPanY = 0;
            this.previewPanStartX = 0;
            this.previewPanStartY = 0;
            this.previewPanOriginX = 0;
            this.previewPanOriginY = 0;
            this.previewIsPanning = false;
        },

        startPreviewPan(event) {
            if (!this.previewIsImage || this.previewZoom <= 100) return;
            this.previewIsPanning = true;
            this.previewPanStartX = event.clientX;
            this.previewPanStartY = event.clientY;
            this.previewPanOriginX = this.previewPanX;
            this.previewPanOriginY = this.previewPanY;
        },

        movePreviewPan(event) {
            if (!this.previewIsPanning || this.previewZoom <= 100) return;
            this.previewPanX = this.previewPanOriginX + (event.clientX - this.previewPanStartX);
            this.previewPanY = this.previewPanOriginY + (event.clientY - this.previewPanStartY);
        },

        endPreviewPan() {
            this.previewIsPanning = false;
        },

        refreshPreviewFit() {
            this.previewFitNonce += 1;
        },

        previewImageStyle() {
            this.previewFitNonce;
            const zoomScale = this.previewZoom / 100;

            return [
                `transform:translate3d(${this.previewPanX}px, ${this.previewPanY}px, 0) scale(${zoomScale})`,
            ].join(';') + ';';
        },

        // Handle CMD+V paste of screenshots into file input refs
        handlePasteRef(event, refName) {
            const items = event.clipboardData?.items;
            if (!items) return;
            for (let i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') !== -1) {
                    event.preventDefault();
                    const blob = items[i].getAsFile();
                    if (!blob) continue;
                    const ext = blob.type === 'image/png' ? 'png' : blob.type === 'image/webp' ? 'webp' : 'jpg';
                    const file = new File([blob], `screenshot-${Date.now()}.${ext}`, { type: blob.type });
                    const dt = new DataTransfer();
                    // Keep existing files
                    const existing = this.$refs[refName]?.files || [];
                    [...existing].forEach(f => dt.items.add(f));
                    dt.items.add(file);
                    if (this.$refs[refName]) {
                        this.$refs[refName].files = dt.files;
                        // Update count state
                        if (refName === 'newHistoryFiles') {
                            this.newHistoryFilesCount = dt.files.length;
                        }
                        if (refName === 'historyEditFiles') {
                            this.historyEditSelectedFileNames = [...dt.files].map(f => f.name);
                        }
                    }
                    break;
                }
            }
        },

        async submitHistoryComment(websiteId) {
            const note = (this.newHistoryComment || '').trim();
            const files = this.$refs.newHistoryFiles?.files || [];
            if (!note && files.length === 0) return;

            const submitBtn = this.$refs.historyCommentBtn;
            const originalText = submitBtn ? submitBtn.innerHTML : 'Comment';
            if (submitBtn) { submitBtn.innerHTML = '...'; submitBtn.disabled = true; }

            const formData = new FormData();
            formData.append('note', note || ' ');
            [...files].forEach(file => formData.append('attachments[]', file));

            try {
                const response = await fetch(`/websites/${websiteId}/history-logs/comment`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        this.newHistoryComment = '';
                        this.newHistoryFilesCount = 0;
                        if (this.$refs.newHistoryFiles) this.$refs.newHistoryFiles.value = '';
                        
                        delete this.prefetchedHistories[websiteId];
                        delete this.prefetchingHistories[websiteId];
                        await this.prefetchHistory(websiteId);
                        this.historyLogs = [...(this.prefetchedHistories[websiteId] || [])];
                        
                        setTimeout(() => {
                            let container = document.querySelector('#show-history-modal .overflow-y-auto');
                            if (container) container.scrollTop = container.scrollHeight;
                        }, 100);
                        
                        if (submitBtn) { submitBtn.innerHTML = originalText; submitBtn.disabled = false; }
                    } else {
                        alert('Error: ' + result.message);
                        if (submitBtn) { submitBtn.innerHTML = originalText; submitBtn.disabled = false; }
                    }
                } else {
                    const result = await response.json().catch(() => ({}));
                    alert('Error: ' + (result.message || 'Server error. Please try again.'));
                    if (submitBtn) { submitBtn.innerHTML = originalText; submitBtn.disabled = false; }
                }
            } catch (error) {
                console.error(error);
                alert('An error occurred while submitting.');
                if (submitBtn) { submitBtn.innerHTML = originalText; submitBtn.disabled = false; }
            }
        },

        openHistoryEditModal(log) {
            if (!this.canManageLog(log)) return;
            this.historyEditLog = log;
            this.historyEditNote = (log.note || '').replace(/\s*\|\s*Link:\s*https?:\/\/[^\s]*/gi, '').trim();
            this.historyEditRemoveIds = [];
            this.historyEditSelectedFileNames = [];
            this.showHistoryEditModal = true;
            this.$nextTick(() => {
                if (this.$refs.historyEditFiles) {
                    this.$refs.historyEditFiles.value = '';
                }
                this.$refs.historyEditPanel?.focus();
            });
        },

        closeHistoryEditModal() {
            this.showHistoryEditModal = false;
            this.historyEditLog = null;
            this.historyEditNote = '';
            this.historyEditRemoveIds = [];
            this.historyEditSelectedFileNames = [];
            if (this.$refs.historyEditFiles) {
                this.$refs.historyEditFiles.value = '';
            }
        },

        historyEditFileKey(file) {
            return file?.id || file?.path || 'legacy';
        },

        visibleHistoryEditAttachments() {
            const files = this.historyEditLog?.attachments || [];
            return files.filter((file) => !this.historyEditRemoveIds.includes(this.historyEditFileKey(file)));
        },

        removeHistoryEditFile(file) {
            const key = this.historyEditFileKey(file);
            if (!this.historyEditRemoveIds.includes(key)) {
                this.historyEditRemoveIds = [...this.historyEditRemoveIds, key];
            }
        },

        updateHistoryEditSelectedFiles() {
            this.historyEditSelectedFileNames = [...(this.$refs.historyEditFiles?.files || [])].map((file) => file.name);
        },

        // Strip "| Link: URL" from note for clean display, return just the text
        formatNoteText(note) {
            if (!note) return '';
            const cleaned = note.replace(/\s*\|\s*Link:\s*https?:\/\/[^\s]*/gi, '').trim();
            return cleaned.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        },

        // Extract URL from "| Link: URL" pattern in note
        extractLink(note) {
            if (!note) return null;
            const match = note.match(/\|\s*Link:\s*(https?:\/\/[^\s]*)/i);
            return match ? match[1] : null;
        },
        submitDynamicForm(action, method = 'POST', fields = {}, fileFields = {}) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = action;
            form.enctype = 'multipart/form-data';

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = 'null';
            form.appendChild(csrf);

            if (method !== 'POST') {
                const methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                methodInput.value = method;
                form.appendChild(methodInput);
            }

            Object.entries({ ...fields, redirect_to: window.location.href }).forEach(([name, value]) => {
                const values = Array.isArray(value) ? value : [value];
                values.forEach((item) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = item ?? '';
                    form.appendChild(input);
                });
            });

            Object.entries(fileFields).forEach(([name, files]) => {
                [...files].forEach((file) => {
                    const fileInput = document.createElement('input');
                    fileInput.type = 'file';
                    fileInput.name = name;
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    fileInput.files = transfer.files;
                    form.appendChild(fileInput);
                });
            });

            document.body.appendChild(form);
            form.submit();
        },

        async submitAjaxStatus(event) {
            event.preventDefault();
            const form = event.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            
            if (submitBtn) {
                submitBtn.innerHTML = '...';
                submitBtn.disabled = true;
            }

            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: form.method || 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (response.ok) {
                    // Close any open modals
                    this.showProgressModal = false;
                    this.showErrorProgressModal = false;
                    this.showQCErrorModal = false;
                    this.showSupervisorErrorModal = false;
                    this.showMaintenanceModal = false;
                    
                    // The server just broadcasted a Pusher event (WebsiteUpdated).
                    // We don't need to manually morph the DOM here.
                    // The global Pusher listener will catch it and call Turbo.visit for everyone (including us)!
                } else {
                    alert('Error updating status.');
                    if (submitBtn) {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                }
            } catch (err) {
                alert('Network error.');
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            }
        },

        saveHistoryLogEdit() {
            const log = this.historyEditLog;
            if (!this.canManageLog(log)) return;
            const note = (this.historyEditNote || '').trim();
            if (note.length < 5) {
                alert('Please enter at least 5 characters.');
                return;
            }
            this.submitDynamicForm(
                `/websites/history-logs/${log.id}`,
                'PUT',
                {
                    note,
                    'remove_file_ids[]': this.historyEditRemoveIds,
                },
                { 'attachments[]': this.$refs.historyEditFiles?.files || [] }
            );
        },

        async deleteHistoryLog(log) {
            if (!this.canManageLog(log)) return;
            const ok = await window.confirmModal({
                title: 'Delete History Log',
                message: 'Are you sure you want to completely delete this log/comment? This action cannot be undone.',
                confirmText: 'Delete Log',
                tone: 'danger'
            });
            if (ok) {
                this.submitDynamicForm(`/websites/history-logs/${log.id}`, 'DELETE');
            }
        },

        async deleteHistoryAttachment(log, file) {
            if (!this.canManageLog(log)) return;
            const ok = await window.confirmModal({
                title: 'Delete Attachment',
                message: 'Are you sure you want to delete this attached file? The history text will remain untouched.',
                confirmText: 'Delete Attachment',
                tone: 'danger'
            });
            if (ok) {
                this.submitDynamicForm(`/websites/history-logs/${log.id}/attachments/${encodeURIComponent(file?.id || 'legacy')}`, 'DELETE');
            }
        },
        formatStatusLabel(status) {
            const map = {
                'comment': '💬 Comment',
                'build': 'Build Progress',
                'qc_checking': 'QC Checking',
                'qc_error': 'QC Error',
                'supervisor_checking': 'Supervisor Checking',
                'supervisor_error': 'Supervisor Error',
                'live': 'Live',
                'maintenance': 'Maintenance',
                'maintenance_qc_checking': 'Maint. QC Check',
                'maintenance_qc_error': 'Maint. QC Error',
                'maintenance_supervisor_checking': 'Maint. Sup. Check',
                'maintenance_supervisor_error': 'Maint. Sup. Error'
            };
            return map[status] || status;
        },
        formatStatusColor(status) {
            if (!status) return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400';
            
            if (status.includes('error')) return 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800';
            if (status.includes('maintenance')) return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800';
            if (status === 'live') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800';
            if (status.includes('qc') || status.includes('supervisor')) return 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400 border border-purple-200 dark:border-purple-800';
            
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400 border border-blue-200 dark:border-blue-800';
        }
    };
}

// Global script to prevent double form submissions
document.addEventListener('submit', function(e) {
    if (e.target && e.target.tagName === 'FORM') {
        const submitBtn = e.target.querySelector('button[type="submit"]');
        if (submitBtn) {
            // If already submitting, prevent duplicate submission
            if (e.target.dataset.submitting) {
                e.preventDefault();
                return;
            }
            e.target.dataset.submitting = 'true';
            
            // Disable button visually and functionally
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
            
            // Change button content to indicate processing
            if (!submitBtn.dataset.originalText) {
                submitBtn.dataset.originalText = submitBtn.innerHTML;
            }
            submitBtn.innerHTML = '<span class="inline-flex items-center gap-1.5"><svg class="animate-spin h-4 w-4 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...</span>';
            
            // Fallback: reset the button state after 8 seconds in case validation fails without a page reload
            setTimeout(() => {
                e.target.dataset.submitting = '';
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                submitBtn.innerHTML = submitBtn.dataset.originalText;
            }, 8000);
        }
    }
});

// Real-time updates via Pusher
document.addEventListener('DOMContentLoaded', () => {
    if (window.kiuqGetPusherClient) {
        const pusher = window.kiuqGetPusherClient();
        if (pusher) {
            const channel = pusher.subscribe('private-websites');
            channel.bind('WebsiteUpdated', function(data) {
                // Ignore if we triggered it (optional, but Turbo morph is fast enough)
                if (window.Turbo) {
                    window.Turbo.visit(window.location.href, { action: 'replace' });
                }
            });
        }
    }
});
