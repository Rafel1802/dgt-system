import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# 1. Replace Revert QC button (QC Error tab and Maintenance tab)
qc_revert_btn_replacement = """                            <button type="button" @click="openRevertQcModal({{ $website->id }}, '{{ addslashes($website->name) }}')" class="btn text-xs py-1.5 px-2.5 w-full bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg shadow-md transition-all active:scale-95 flex items-center justify-center gap-1">
                                ✓ Fix Approved
                            </button>"""
content = re.sub(
    r'<form action="\{\{ route\(\'websites\.qc\.revert\', \$website\) \}\}" method="POST" class="flex-1" data-confirm="Revert QC approval.*?">[\s\n]*@csrf[\s\n]*<button type="submit"[^>]*>[\s\n]*✓ Fix Approved[\s\n]*</button>[\s\n]*</form>',
    qc_revert_btn_replacement,
    content,
    flags=re.DOTALL
)

# 2. Replace Revert Supervisor button
supervisor_revert_btn_replacement = """                            <button type="button" @click="openRevertSupervisorModal({{ $website->id }}, '{{ addslashes($website->name) }}')" class="btn text-xs py-1.5 px-2.5 w-full bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg shadow-md transition-all active:scale-95 flex items-center justify-center gap-1">
                                ✓ Fix Approved
                            </button>"""
content = re.sub(
    r'<form action="\{\{ route\(\'websites\.supervisor\.revert\', \$website\) \}\}" method="POST" class="flex-1" data-confirm="Revert Supervisor approval.*?">[\s\n]*@csrf[\s\n]*<button type="submit"[^>]*>[\s\n]*✓ Fix Approved[\s\n]*</button>[\s\n]*</form>',
    supervisor_revert_btn_replacement,
    content,
    flags=re.DOTALL
)

# 3. Add JS state and methods
js_state = """        // Revert Modals
        showRevertQcModal: false,
        revertQcModalName: '',
        revertQcModalWebsiteId: null,
        revertQcModalAction: '',
        openRevertQcModal(websiteId, websiteName) {
            this.revertQcModalWebsiteId = websiteId;
            this.revertQcModalName   = websiteName;
            this.revertQcModalAction = `/websites/${websiteId}/revert-qc`;
            this.showRevertQcModal   = true;
        },

        showRevertSupervisorModal: false,
        revertSupervisorModalName: '',
        revertSupervisorModalWebsiteId: null,
        revertSupervisorModalAction: '',
        openRevertSupervisorModal(websiteId, websiteName) {
            this.revertSupervisorModalWebsiteId = websiteId;
            this.revertSupervisorModalName   = websiteName;
            this.revertSupervisorModalAction = `/websites/${websiteId}/revert-supervisor`;
            this.showRevertSupervisorModal   = true;
        },"""

content = content.replace("        // Error Modals", js_state + "\n\n        // Error Modals")

# 4. Add the HTML modals
modals_html = """    <!-- Revert QC Modal -->
    <div id="show-revert-qc-modal" data-turbo-permanent x-show="showRevertQcModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
        <div class="card border border-amber-300 dark:border-amber-700 w-full max-w-md" @click.stop>
            <div class="p-5 border-b border-amber-100 dark:border-amber-800 flex items-center justify-between bg-amber-50/50 dark:bg-amber-900/20">
                <div>
                    <h3 class="text-lg font-bold text-amber-900 dark:text-amber-100">Fix Approved (QC)</h3>
                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5" x-text="'Website: ' + revertQcModalName"></p>
                </div>
                <button @click="showRevertQcModal = false" class="text-amber-400 hover:text-amber-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form :action="revertQcModalAction" method="POST" enctype="multipart/form-data" class="p-5 space-y-4" data-no-processing="true" @submit.prevent="optimisticSubmit($event, 'revertQcModalWebsiteId', 'showRevertQcModal')">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Note <span class="font-normal text-slate-400 dark:text-slate-500 normal-case ml-1">(optional)</span></label>
                    <textarea name="revert_note" rows="3" @paste="handlePaste($event, 'revert_files')" 
                              class="form-textarea w-full rounded-xl text-sm resize-none border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 focus:border-amber-500 focus:ring focus:ring-amber-500/20 placeholder-slate-400 dark:placeholder-slate-500 transition-all duration-150 shadow-sm" 
                              placeholder="e.g. Issues fixed, ready for re-check."></textarea>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Reference Files <span class="font-normal text-slate-400 dark:text-slate-500 normal-case ml-1">(optional PDFs or images)</span></label>
                    <input type="file" name="revert_files[]" accept=".pdf,image/png,image/jpeg,image/webp" multiple
                           class="form-input w-full rounded-xl text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-amber-600">
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="showRevertQcModal = false" class="btn btn-secondary text-sm">Cancel</button>
                    <button type="submit" class="btn text-sm bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2 rounded-xl shadow-md shadow-amber-500/20 active:scale-95 transition-all">Submit Fix</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Revert Supervisor Modal -->
    <div id="show-revert-supervisor-modal" data-turbo-permanent x-show="showRevertSupervisorModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)">
        <div class="card border border-amber-300 dark:border-amber-700 w-full max-w-md" @click.stop>
            <div class="p-5 border-b border-amber-100 dark:border-amber-800 flex items-center justify-between bg-amber-50/50 dark:bg-amber-900/20">
                <div>
                    <h3 class="text-lg font-bold text-amber-900 dark:text-amber-100">Fix Approved (Supervisor)</h3>
                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5" x-text="'Website: ' + revertSupervisorModalName"></p>
                </div>
                <button @click="showRevertSupervisorModal = false" class="text-amber-400 hover:text-amber-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form :action="revertSupervisorModalAction" method="POST" enctype="multipart/form-data" class="p-5 space-y-4" data-no-processing="true" @submit.prevent="optimisticSubmit($event, 'revertSupervisorModalWebsiteId', 'showRevertSupervisorModal')">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Note <span class="font-normal text-slate-400 dark:text-slate-500 normal-case ml-1">(optional)</span></label>
                    <textarea name="revert_note" rows="3" @paste="handlePaste($event, 'revert_files')" 
                              class="form-textarea w-full rounded-xl text-sm resize-none border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 focus:border-amber-500 focus:ring focus:ring-amber-500/20 placeholder-slate-400 dark:placeholder-slate-500 transition-all duration-150 shadow-sm" 
                              placeholder="e.g. Issues fixed, ready for re-check."></textarea>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Reference Files <span class="font-normal text-slate-400 dark:text-slate-500 normal-case ml-1">(optional PDFs or images)</span></label>
                    <input type="file" name="revert_files[]" accept=".pdf,image/png,image/jpeg,image/webp" multiple
                           class="form-input w-full rounded-xl text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800/50 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-amber-600">
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="showRevertSupervisorModal = false" class="btn btn-secondary text-sm">Cancel</button>
                    <button type="submit" class="btn text-sm bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2 rounded-xl shadow-md shadow-amber-500/20 active:scale-95 transition-all">Submit Fix</button>
                </div>
            </form>
        </div>
    </div>
"""

content = content.replace("    <!-- QC Error Modal -->", modals_html + "\n    <!-- QC Error Modal -->")

with open(file_path, 'w') as f:
    f.write(content)
