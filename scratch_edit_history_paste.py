import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# 1. Add handlePasteRef method to JS
handle_paste_ref_js = """
        handlePasteRef(e, refName) {
            const items = (e.clipboardData || e.originalEvent.clipboardData).items;
            let found = false;
            for (let index in items) {
                const item = items[index];
                if (item.kind === 'file') {
                    const blob = item.getAsFile();
                    if (!blob) continue;
                    
                    found = true;
                    const fileInput = this.$refs[refName];
                    if (fileInput) {
                        const dataTransfer = new DataTransfer();
                        // Add existing files
                        if (fileInput.files) {
                            for (let i = 0; i < fileInput.files.length; i++) {
                                dataTransfer.items.add(fileInput.files[i]);
                            }
                        }
                        // Add the new pasted file
                        const ext = blob.type.split('/')[1] || 'png';
                        const newFile = new File([blob], `pasted_screenshot_${Date.now()}.${ext}`, { type: blob.type });
                        dataTransfer.items.add(newFile);
                        
                        fileInput.files = dataTransfer.files;
                        
                        // Fire change event so we can update UI if needed
                        const event = new Event('change', { bubbles: true });
                        fileInput.dispatchEvent(event);
                    }
                }
            }
            if (found) {
                window.showToast('Screenshot pasted and attached!', 'success');
            }
        },"""

content = content.replace("        saveHistoryLogEdit() {", handle_paste_ref_js + "\n\n        saveHistoryLogEdit() {")

# 2. Add @paste to historyEditNote textarea
content = re.sub(
    r'<textarea x-model="historyEditNote" rows="4" class="form-textarea w-full resize-none rounded-xl border border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-800/50"></textarea>',
    r'<textarea x-model="historyEditNote" @paste="handlePasteRef($event, \'historyEditFiles\')" rows="4" class="form-textarea w-full resize-none rounded-xl border border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-800/50"></textarea>',
    content
)

with open(file_path, 'w') as f:
    f.write(content)
