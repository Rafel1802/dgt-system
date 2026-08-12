import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

old_prefetch = '''        async prefetchHistory(websiteId) {
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

new_prefetch = '''        prefetchHistory(websiteId) {
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
        },'''

content = content.replace(old_prefetch, new_prefetch)

with open(file_path, 'w') as f:
    f.write(content)

print("Fixed prefetch promise race condition.")
