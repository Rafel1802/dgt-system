import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# 1. Update QC Approve Modal
qc_approve_files_html = """            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Reference Files <span class="font-normal text-slate-400 dark:text-slate-500 normal-case ml-1">(optional PDFs or images)</span></label>
                <input type="file" name="qc_files[]" accept=".pdf,image/png,image/jpeg,image/webp" multiple
                       class="form-input w-full rounded-xl text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-amber-600">
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">"""

content = re.sub(
    r'<form :action="qcModalAction" method="POST" class="p-5 space-y-4" data-no-processing="true" @submit\.prevent="optimisticSubmit\(\$event, \'qcModalWebsiteId\', \'showQcModal\'\)">',
    r'<form :action="qcModalAction" method="POST" enctype="multipart/form-data" class="p-5 space-y-4" data-no-processing="true" @submit.prevent="optimisticSubmit($event, \'qcModalWebsiteId\', \'showQcModal\')">',
    content
)

content = re.sub(
    r'name="qc_note" rows="3"([\s\n]+)class="form-textarea w-full',
    r'name="qc_note" rows="3" @paste="handlePaste($event, \'qc_files\')"\1class="form-textarea w-full',
    content
)

content = re.sub(
    r'            <div class="flex items-center justify-end gap-3 pt-2">\n                <button type="button" @click="showQcModal = false" class="btn btn-secondary text-sm">Cancel</button>\n                <button type="submit" class="btn text-sm bg-amber-500',
    qc_approve_files_html + '\n                <button type="button" @click="showQcModal = false" class="btn btn-secondary text-sm">Cancel</button>\n                <button type="submit" class="btn text-sm bg-amber-500',
    content
)


# 2. Update Supervisor Approve Modal
supervisor_approve_files_html = """            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Reference Files <span class="font-normal text-slate-400 dark:text-slate-500 normal-case ml-1">(optional PDFs or images)</span></label>
                <input type="file" name="supervisor_files[]" accept=".pdf,image/png,image/jpeg,image/webp" multiple
                       class="form-input w-full rounded-xl text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 file:mr-3 file:rounded-lg file:border-0 file:bg-cyan-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-cyan-600">
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">"""

content = re.sub(
    r'<form :action="supervisorModalAction" method="POST" class="p-5 space-y-4" data-no-processing="true" @submit\.prevent="optimisticSubmit\(\$event, \'supervisorModalWebsiteId\', \'showSupervisorModal\'\)">',
    r'<form :action="supervisorModalAction" method="POST" enctype="multipart/form-data" class="p-5 space-y-4" data-no-processing="true" @submit.prevent="optimisticSubmit($event, \'supervisorModalWebsiteId\', \'showSupervisorModal\')">',
    content
)

content = re.sub(
    r'name="supervisor_note" rows="3"([\s\n]+)class="form-textarea w-full',
    r'name="supervisor_note" rows="3" @paste="handlePaste($event, \'supervisor_files\')"\1class="form-textarea w-full',
    content
)

content = re.sub(
    r'            <div class="flex items-center justify-end gap-3 pt-2">\n                <button type="button" @click="showSupervisorModal = false" class="btn btn-secondary text-sm">Cancel</button>\n                <button type="submit" class="btn text-sm bg-cyan-500',
    supervisor_approve_files_html + '\n                <button type="button" @click="showSupervisorModal = false" class="btn btn-secondary text-sm">Cancel</button>\n                <button type="submit" class="btn text-sm bg-cyan-500',
    content
)

# 3. Add Paste to QC Error Modal
content = re.sub(
    r'name="error_note" rows="3" required minlength="5"([\s\n]+)class="form-textarea w-full',
    r'name="error_note" rows="3" required minlength="5" @paste="handlePaste($event, \'error_files\')"\1class="form-textarea w-full',
    content,
    count=1 # Make sure to apply it carefully, let's just do it directly on both error modals
)

# 4. Add handlePaste method to Alpine JS
handle_paste_js = """
        handlePaste(e, inputName) {
            const items = (e.clipboardData || e.originalEvent.clipboardData).items;
            let found = false;
            for (let index in items) {
                const item = items[index];
                if (item.kind === 'file') {
                    const blob = item.getAsFile();
                    if (!blob) continue;
                    
                    found = true;
                    // Find the input element by name within the same form
                    const form = e.target.closest('form');
                    const fileInput = form.querySelector(`input[name="${inputName}[]"]`);
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

content = content.replace('saveHistoryLogEdit() {', handle_paste_js + '\n\n        saveHistoryLogEdit() {')

with open(file_path, 'w') as f:
    f.write(content)

print("Updated modals to support file uploads and CMD+V pasting.")
