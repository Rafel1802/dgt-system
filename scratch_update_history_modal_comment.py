import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# Add the comment form to the History modal
comment_form_html = """        <div class="p-4 border-t border-slate-100 dark:border-slate-700">
            <form @submit.prevent="submitHistoryComment($event, historyWebsiteId)" enctype="multipart/form-data" class="flex flex-col gap-2">
                <textarea name="note" x-model="newHistoryComment" @paste="handlePasteRef($event, 'newHistoryFiles')" rows="2" class="form-textarea w-full resize-none rounded-xl border border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-800/50" placeholder="Type a comment or CMD+V to paste a screenshot..."></textarea>
                <div class="flex items-center justify-between gap-3">
                    <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" name="attachments[]" x-ref="newHistoryFiles" @change="updateNewHistoryFilesCount()" class="hidden">
                    <button type="button" @click="$refs.newHistoryFiles.click()" class="btn btn-secondary text-xs flex items-center justify-center gap-1.5 whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                        <span x-text="newHistoryFilesCount > 0 ? newHistoryFilesCount + ' file(s)' : 'Attach Files'"></span>
                    </button>
                    <div class="flex gap-2">
                        <button type="button" @click="showHistoryModal = false" class="btn btn-secondary text-sm">Close</button>
                        <button type="submit" class="btn text-sm bg-indigo-500 hover:bg-indigo-600 text-white font-bold px-4 py-1.5 rounded-xl shadow-md shadow-indigo-500/20 active:scale-95 transition-all">Post</button>
                    </div>
                </div>
            </form>
        </div>"""

content = content.replace(
"""        <div class="p-4 border-t border-slate-100 dark:border-slate-700 text-right">
            <button type="button" @click="showHistoryModal = false" class="btn btn-secondary text-sm">Close</button>
        </div>""",
    comment_form_html
)

js_state = """        showHistoryModal: false,
        historyLogs: [],
        historyWebsiteName: '',
        historyWebsiteId: null,
        newHistoryComment: '',
        newHistoryFilesCount: 0,
        
        updateNewHistoryFilesCount() {
            this.newHistoryFilesCount = this.$refs.newHistoryFiles.files.length;
        },

        async submitHistoryComment(e, websiteId) {
            if (!this.newHistoryComment.trim() && this.newHistoryFilesCount === 0) return;
            const form = e.target;
            const formData = new FormData(form);
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch(`/websites/${websiteId}/history-logs/comment`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'Accept': 'application/json' },
                    body: formData
                });
                if (response.ok) {
                    this.newHistoryComment = '';
                    this.$refs.newHistoryFiles.value = '';
                    this.newHistoryFilesCount = 0;
                    window.showToast('Comment added!', 'success');
                    
                    // Force refresh history logs
                    const logs = await this.prefetchHistory(websiteId, true);
                    this.historyLogs = [...logs];
                } else {
                    window.showToast('Failed to add comment.', 'error');
                }
            } catch (err) {
                console.error(err);
                window.showToast('Network error.', 'error');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        },"""

content = content.replace("        showHistoryModal: false,\n        historyLogs: [],\n        historyWebsiteName: '',\n        historyWebsiteId: null,", js_state)

# Also make sure prefetchHistory update wasn't replaced with backslashes
content = content.replace(r"\'newHistoryFiles\'", "'newHistoryFiles'")

with open(file_path, 'w') as f:
    f.write(content)
