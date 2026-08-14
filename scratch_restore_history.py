import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# 1. Update History Modal HTML
html_old = """
        <div class="p-4 border-t border-slate-100 dark:border-slate-700 text-right">
            <button type="button" @click="showHistoryModal = false" class="btn btn-secondary text-sm">Close</button>
        </div>
"""

html_new = """
        <div class="p-4 border-t border-slate-100 dark:border-slate-700">
            <form @submit.prevent="submitHistoryComment($event, historyWebsiteId)" data-no-processing="true" enctype="multipart/form-data" class="flex flex-col gap-2">
                <textarea name="note" x-model="newHistoryComment" @paste="handlePasteRef($event, 'newHistoryFiles')" rows="2" class="form-textarea w-full resize-none rounded-xl border border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-800/50" placeholder="Type a comment or CMD+V to paste a screenshot..."></textarea>
                <div class="flex items-center justify-between gap-3">
                    <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" name="attachments[]" x-ref="newHistoryFiles" @change="updateNewHistoryFilesCount()" class="hidden">
                    <button type="button" @click="$refs.newHistoryFiles.click()" class="btn btn-secondary text-xs flex items-center justify-center gap-1.5 whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                        <span x-text="newHistoryFilesCount > 0 ? newHistoryFilesCount + ' file(s)' : 'Attach Files'"></span>
                    </button>
                    <div class="flex gap-2">
                        <button type="button" @click="showHistoryModal = false" class="btn btn-secondary text-sm">Close</button>
                        <button type="submit" class="btn text-sm bg-indigo-500 hover:bg-indigo-600 text-white font-bold px-4 py-1.5 rounded-xl shadow-md shadow-indigo-500/20 active:scale-95 transition-all">Comment</button>
                    </div>
                </div>
            </form>
        </div>
"""
if html_old.strip() in content:
    content = content.replace(html_old, html_new)

# 2. Update Alpine Data properties
alpine_data_old = """
        showHistoryEditModal: false,
        historyEditLog: null,
"""
alpine_data_new = """
        showHistoryEditModal: false,
        historyEditLog: null,
        newHistoryComment: '',
        newHistoryFilesCount: 0,
        updateNewHistoryFilesCount() {
            this.newHistoryFilesCount = this.$refs.newHistoryFiles?.files?.length || 0;
        },
"""
if alpine_data_old.strip() in content:
    content = content.replace(alpine_data_old, alpine_data_new)

# 3. Add submitHistoryComment function
js_new = """
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
                    const result = await response.json();
                    if (result.success) {
                        this.showHistoryModal = false;
                        window.location.reload();
                    } else {
                        alert("Error: " + result.message);
                    }
                } else {
                    const result = await response.json().catch(() => ({}));
                    alert("Error: " + (result.message || 'Failed to add comment'));
                    console.error('Failed to add comment');
                }
            } catch (error) {
                alert("Network Error: " + error.message);
                console.error(error);
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        },

        openHistoryEditModal(log) {
"""

content = content.replace("openHistoryEditModal(log) {", js_new.strip())

with open(file_path, 'w') as f:
    f.write(content)
