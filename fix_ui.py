import re

with open('resources/views/websites/index.blade.php', 'r') as f:
    content = f.read()

# 1. Move Manage Classes button
manage_class_btn = """            @if(auth()->user()->canUpdateWebsiteProgress())
            <button type="button" @click="showManageClassesModal = true" class="btn btn-secondary flex items-center gap-2 px-3 py-1.5 text-sm flex-shrink-0">
                <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
                <span class="hidden sm:inline">Manage Classes</span>
                <span class="sm:hidden">Classes</span>
            </button>
            @endif"""

content = re.sub(
    r'(\{\{-- Export button --\}\})',
    manage_class_btn + r'\n        \1',
    content
)

content = re.sub(
    r'            <button type="button" @click="showManageClassesModal = true" class="btn btn-secondary flex items-center gap-2 text-sm">\s*<svg[^>]+>.*?</svg>\s*Manage Classes\s*</button>',
    '',
    content,
    flags=re.DOTALL
)

# 2. Remove "Class" filter from Follow up
content = re.sub(
    r'            <div>\s*<label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Class</label>\s*<select name="fu_class".*?</select>\s*</div>',
    '',
    content,
    flags=re.DOTALL
)
content = re.sub(
    r'            @if\(!empty\(\$followUpFilter\[\'fu_class\'\]\)\)\s*<div class="ml-2 flex items-center gap-1\.5 text-xs text-slate-500">\s*<span class="w-1\.5 h-1\.5 rounded-full bg-indigo-500"></span>\s*Showing: <strong class="text-indigo-600 dark:text-indigo-400">\{\{ \$followUpFilter\[\'fu_class\'\] === \'__none__\' \? \'Uncategorized\' : \$followUpFilter\[\'fu_class\'\] \}\}</strong>\s*<span class="text-slate-400">\(\{\{ \$fuWebsitesList->count\(\) \}\} sites\)</span>\s*</div>\s*@endif',
    '',
    content,
    flags=re.DOTALL
)

# 3. Handle by -> Upload by, Page URL -> Blog URL in the Follow Up table and modals
content = re.sub(r'>Handle by<', '>Upload by<', content)
content = re.sub(r'>HANDLE BY<', '>UPLOAD BY<', content)
content = re.sub(r'Page URL', 'Blog URL', content)

# 4. Make QC approval a large checkbox UI
old_qc_btn = """                                    @if(auth()->user()->hasAnyRole(['super-admin','admin-digital']) && $fu->qc_status !== 'approved')
                                    <form action="{{ route('websites.followups.qc', $fu) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="qc_status" value="approved">
                                        <button type="submit" class="btn btn-secondary text-xs py-1 px-1.5 relative group"  aria-label="Approve QC">✓
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
        Approve QC
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
    </div>
</button>
                                    </form>
                                    @endif"""

new_qc_btn = """                                    @if(auth()->user()->hasAnyRole(['super-admin','admin-digital']) && $fu->qc_status !== 'approved')
                                    <form action="{{ route('websites.followups.qc', $fu) }}" method="POST" class="inline-block">
                                        @csrf
                                        <input type="hidden" name="qc_status" value="approved">
                                        <button type="submit" class="group relative flex items-center justify-center w-6 h-6 rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 hover:border-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all cursor-pointer" aria-label="Approve QC">
                                            <svg class="w-4 h-4 text-emerald-500 opacity-0 group-hover:opacity-50 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
                                                Approve QC
                                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
                                            </div>
                                        </button>
                                    </form>
                                    @elseif($fu->qc_status === 'approved')
                                        <div class="flex items-center justify-center w-6 h-6 rounded bg-emerald-500 text-white cursor-default" title="QC Approved by {{ $fu->qcChecker?->name ?? 'System' }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                        </div>
                                    @endif"""

content = content.replace(old_qc_btn, new_qc_btn)

# 5. Flatpickr for Follow up Date Filter
content = re.sub(
    r'<input type="date" name="fu_date" value="\{\{ \$followUpFilter\[\'fu_date\'\] \?\? \'\' \}\}" class="form-input text-base font-bold rounded-lg py-1\.5 min-w-\[150px\] border border-slate-300 dark:border-slate-600 dark:bg-slate-800 \[\&::-webkit-calendar-picker-indicator\]:scale-150 \[\&::-webkit-calendar-picker-indicator\]:mr-1" onchange="this\.form\.submit\(\)">',
    """<input type="text" name="fu_date" value="{{ $followUpFilter['fu_date'] ?? date('Y-m-d') }}" x-init="flatpickr($el, { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M, Y', allowInput: true, onChange: function(selectedDates, dateStr, instance) { instance.element.closest('form').submit(); } })" class="form-input text-sm font-bold rounded-lg py-1.5 min-w-[150px] border border-slate-300 dark:border-slate-600 dark:bg-slate-800 cursor-pointer text-slate-700 dark:text-slate-200">""",
    content
)

with open('resources/views/websites/index.blade.php', 'w') as f:
    f.write(content)
