import re

with open('resources/views/websites/index.blade.php', 'r') as f:
    content = f.read()

# Let's find the realtime listener
realtime_script = """window.addEventListener('kiuq:realtime-notification', function(e) {
        if (e.detail && e.detail.data && e.detail.data.module === 'digital') {
            if (window.Turbo) {
                // Do not morph if any modal is open to prevent Alpine state loss and Idiomorph bugs
                const alpineEl = document.querySelector('[x-data="websitesApp()"]');
                if (alpineEl && typeof Alpine !== 'undefined') {
                    const data = Alpine.$data(alpineEl);
                    const isAnyModalOpen = data.showCreateModal || data.showEditModal || data.showProgressModal || data.showQcModal || data.showSupervisorModal || data.showMaintenanceModal || data.showQcErrorModal || data.showSupervisorErrorModal || data.showErrorProgressModal || data.showFollowUpModal || data.showEditFollowUpModal || data.showExportModal || data.showHistoryModal || data.showManageClassesModal || data.showDeleteClassModal || data.showManageMembersModal || data.showAttachmentPreview || data.showHistoryEditModal;
                    
                    if (isAnyModalOpen) {
                        console.log('Realtime update skipped because a modal is open.');
                        return; // Abort refresh
                    }
                }

                if (typeof window.Turbo.refresh === 'function') {
                    window.Turbo.refresh();
                } else {
                    window.Turbo.visit(window.location.href, { action: "replace" });
                }
            }
        }
    });"""

# We replace the old listener
content = re.sub(
    r"window\.addEventListener\('kiuq:realtime-notification', function\(e\) \{.*?\}\);",
    realtime_script,
    content,
    flags=re.DOTALL
)

with open('resources/views/websites/index.blade.php', 'w') as f:
    f.write(content)

print("Fixed realtime listener")
