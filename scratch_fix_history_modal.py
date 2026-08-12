import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# Add hover prefetch to all history buttons
content = re.sub(
    r'@click="openHistoryModal\(\{\{ \$website->id \}\}, \'\{\{ addslashes\(\$website->name\) \}\}\', ([^\)]+)\)"',
    r'@mouseenter.once="prefetchHistory({{ $website->id }})" @click="openHistoryModal({{ $website->id }}, \'{{ addslashes($website->name) }}\', \1)"',
    content
)

# Add prefetch state and methods to Alpine data object
replacement_data = '''        showHistoryModal:     false,
        historyLoading:       false,
        prefetchedHistories: {},
        prefetchingHistories: {},

        async prefetchHistory(websiteId) {
            if (this.prefetchedHistories[websiteId] || this.prefetchingHistories[websiteId]) return;
            this.prefetchingHistories[websiteId] = true;
            try {
                const response = await fetch(`/websites/${websiteId}/history`);
                if (response.ok) {
                    let parsedLogs = await response.json();
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
                }
            } catch (err) {
                console.error(err);
            }
        },'''

content = content.replace(
    '''        showHistoryModal:     false,
        historyLoading:       false,''',
    replacement_data
)

# Refactor openHistoryModal
old_function = '''        async openHistoryModal(websiteId, websiteName, type) {
            this.historyWebsiteName = websiteName;
            this.historyType = type;
            this.historyLogs = [];
            this.historyLoading = true;
            this.showHistoryModal = true;
            
            try {
                const response = await fetch(`/websites/${websiteId}/history`);
                if (!response.ok) throw new Error('Failed to load history');
                let parsedLogs = await response.json();
                
                // Retrofit old logs that stored files as text "| File: filename.ext"
                parsedLogs = parsedLogs.map(log => {
                    if (!log.attachment_path && log.note && log.note.includes(' | File: ')) {
                        const parts = log.note.split(' | File: ');
                        log.note = parts[0];
                        log.attachment_name = parts[1];
                        log.attachment_path = 'website-error-references/' + parts[1];
                    }
                    if (!Array.isArray(log.attachments)) {
                        log.attachments = [];
                    }
                    if (!log.attachments.length && log.attachment_path) {
                        log.attachments = [{
                            id: 'legacy',
                            path: log.attachment_path,
                            name: log.attachment_name || 'Attached File'
                        }];
                    }
                    return log;
                });

                // Sort logs by created_at descending just to be safe
                parsedLogs.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                this.historyLogs = parsedLogs;
            } catch (err) {
                console.error(err);
                window.showToast('Failed to load website history.', 'error');
            } finally {
                this.historyLoading = false;
            }
        },'''

new_function = '''        async openHistoryModal(websiteId, websiteName, type) {
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
        },'''

content = content.replace(old_function, new_function)

with open(file_path, 'w') as f:
    f.write(content)

print("Updated history modals to use prefetching.")
