import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# Modify prefetchHistory
prefetch_history_new = """        prefetchHistory(websiteId, force = false) {
            if (!force && this.prefetchedHistories[websiteId]) {
                return Promise.resolve(this.prefetchedHistories[websiteId]);
            }
            if (force) {
                delete this.prefetchingHistories[websiteId];
            }
            if (!this.prefetchingHistories[websiteId]) {
                this.prefetchingHistories[websiteId] = fetch(`/websites/${websiteId}/history`)"""

content = content.replace("""        prefetchHistory(websiteId) {
            if (this.prefetchedHistories[websiteId]) {
                return Promise.resolve(this.prefetchedHistories[websiteId]);
            }
            if (!this.prefetchingHistories[websiteId]) {
                this.prefetchingHistories[websiteId] = fetch(`/websites/${websiteId}/history`)""", prefetch_history_new)

with open(file_path, 'w') as f:
    f.write(content)
