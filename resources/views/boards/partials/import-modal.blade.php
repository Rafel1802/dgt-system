{{-- ── Board Import Modal ─────────────────────────────────────────────── --}}
{{-- Multi-step: Step 1 = source selection, Step 2 = validation preview, Step 3 = done --}}
<div x-show="importModal.open" x-cloak
     class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[70] flex items-center justify-center p-4 overflow-y-auto"
     @click.self="closeImportModal()"
     @keydown.escape.window="closeImportModal()">

  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border border-slate-100 flex flex-col"
       x-show="importModal.open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100 scale-100"
       x-transition:leave-end="opacity-0 scale-95"
       style="max-height: calc(100vh - 2rem);">

    {{-- ── Modal Header ──────────────────────────────────────────────── --}}
    <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-indigo-600 to-violet-600 flex items-center justify-between flex-shrink-0">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
          </svg>
        </div>
        <div>
          <h3 class="font-black text-white text-sm">Import Cards</h3>
          <p class="text-white/70 text-[10px] font-semibold" x-text="importModal.step === 1 ? 'Step 1 of 3 — Choose source' : (importModal.step === 2 ? 'Step 2 of 3 — Validate & Preview' : 'Step 3 of 3 — Import complete')"></p>
        </div>
      </div>
      <button @click="closeImportModal()" class="text-white/60 hover:text-white transition p-1 hover:bg-white/10 rounded-lg">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    {{-- ── Step indicator ─────────────────────────────────────────────── --}}
    <div class="flex items-center px-6 pt-4 pb-2 gap-2 flex-shrink-0">
      <template x-for="s in [1, 2, 3]" :key="s">
        <div class="flex items-center gap-2 flex-1">
          <div class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-black flex-shrink-0 transition-all duration-300"
               :class="s < importModal.step ? 'bg-emerald-500 text-white' : (s === importModal.step ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-400')">
            <template x-if="s < importModal.step">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            </template>
            <template x-if="s >= importModal.step">
              <span x-text="s"></span>
            </template>
          </div>
          <div x-show="s < 3" class="flex-1 h-0.5 rounded-full transition-colors duration-300" :class="s < importModal.step ? 'bg-emerald-400' : 'bg-slate-200'"></div>
        </div>
      </template>
    </div>

    {{-- ── Modal Body ────────────────────────────────────────────────── --}}
    <div class="flex-1 overflow-y-auto p-6 scrollbar-thin">

      {{-- ── STEP 1: Source Selection ─────────────────────────────────── --}}
      <div x-show="importModal.step === 1" class="space-y-5">
        <p class="text-sm font-semibold text-slate-500">Choose how you want to import cards into <strong x-text="board.name" class="text-slate-800"></strong>.</p>

        {{-- Source tabs --}}
        <div class="grid grid-cols-2 gap-3">
          <button @click="importModal.source = 'csv'"
                  class="relative flex flex-col items-center gap-3 p-5 rounded-2xl border-2 transition-all"
                  :class="importModal.source === 'csv' ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl"
                 :class="importModal.source === 'csv' ? 'bg-indigo-100' : 'bg-slate-100'">📄</div>
            <div class="text-center">
              <p class="text-sm font-black text-slate-800">CSV File</p>
              <p class="text-[10px] font-semibold text-slate-500 mt-0.5">Upload a .csv file</p>
            </div>
            <div x-show="importModal.source === 'csv'" class="absolute top-3 right-3 w-5 h-5 bg-indigo-500 rounded-full flex items-center justify-center">
              <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            </div>
          </button>

          <button @click="importModal.source = 'sheets'"
                  class="relative flex flex-col items-center gap-3 p-5 rounded-2xl border-2 transition-all"
                  :class="importModal.source === 'sheets' ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl"
                 :class="importModal.source === 'sheets' ? 'bg-emerald-100' : 'bg-slate-100'">🔗</div>
            <div class="text-center">
              <p class="text-sm font-black text-slate-800">Google Sheets</p>
              <p class="text-[10px] font-semibold text-slate-500 mt-0.5">Paste a Sheets URL</p>
            </div>
            <div x-show="importModal.source === 'sheets'" class="absolute top-3 right-3 w-5 h-5 bg-emerald-500 rounded-full flex items-center justify-center">
              <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            </div>
          </button>
        </div>

        {{-- CSV upload zone --}}
        <div x-show="importModal.source === 'csv'" class="space-y-3">
          <div class="relative border-2 border-dashed rounded-2xl p-8 text-center transition-all cursor-pointer"
               :class="importModal.dragOver ? 'border-indigo-400 bg-indigo-50' : 'border-slate-300 hover:border-indigo-300 hover:bg-slate-50'"
               @dragover.prevent="importModal.dragOver = true"
               @dragleave="importModal.dragOver = false"
               @drop.prevent="importHandleDrop($event)"
               @click="$refs.importFileInput.click()">
            <div x-show="!importModal.file">
              <div class="w-12 h-12 bg-indigo-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
              </div>
              <p class="text-sm font-black text-slate-700">Drag & drop your CSV here</p>
              <p class="text-xs font-semibold text-slate-400 mt-1">or <span class="text-indigo-600 underline">browse files</span> — max 20 MB</p>
            </div>
            <div x-show="importModal.file" class="flex items-center justify-center gap-3">
              <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
              </div>
              <div class="text-left">
                <p class="text-sm font-black text-slate-800" x-text="importModal.file?.name"></p>
                <p class="text-[10px] font-semibold text-slate-400" x-text="importModal.file ? (importModal.file.size / 1024).toFixed(1) + ' KB' : ''"></p>
              </div>
              <button @click.stop="importModal.file = null" class="text-xs text-rose-500 hover:text-rose-700 font-bold ml-2">Remove</button>
            </div>
            <input x-ref="importFileInput" type="file" accept=".csv,text/csv" class="hidden" @change="importHandleFileSelect($event)">
          </div>
        </div>

        {{-- Google Sheets URL input --}}
        <div x-show="importModal.source === 'sheets'" class="space-y-3">
          <div>
            <label class="text-xs font-black text-slate-600 uppercase tracking-wider block mb-1.5">Google Sheets URL</label>
            <input type="url" x-model="importModal.sheetsUrl"
                   placeholder="https://docs.google.com/spreadsheets/d/…"
                   class="form-input text-sm w-full rounded-xl border-slate-200 focus:border-emerald-400 focus:ring-emerald-300">
            <p class="text-[10px] text-slate-400 font-semibold mt-1.5 leading-relaxed">
              ⚠️ The sheet must be shared as <strong>"Anyone with the link — Viewer"</strong> for the import to work.
            </p>
          </div>
        </div>

        {{-- Template download --}}
        <div class="bg-gradient-to-r from-indigo-50 to-violet-50 border border-indigo-100 rounded-2xl p-4">
          <div class="flex items-start gap-3">
            <div class="w-9 h-9 bg-white rounded-xl flex items-center justify-center flex-shrink-0 shadow-sm">
              <span class="text-base">📋</span>
            </div>
            <div class="flex-1">
              <p class="text-sm font-black text-slate-800">Not sure of the format?</p>
              <p class="text-xs font-semibold text-slate-500 mt-0.5 mb-2">Download the standard template — it includes all required columns and two example rows.</p>
              <button @click="downloadImportTemplate()"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-indigo-200 rounded-lg text-xs font-black text-indigo-700 hover:bg-indigo-50 transition shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Download Template
              </button>
            </div>
          </div>
        </div>

        {{-- Column reference --}}
        <details class="rounded-xl border border-slate-200 bg-slate-50">
          <summary class="px-4 py-3 text-xs font-black text-slate-600 cursor-pointer select-none flex items-center gap-2">
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/></svg>
            Column Reference
          </summary>
          <div class="px-4 pb-4">
            <table class="w-full text-[10px] mt-2">
              <thead>
                <tr class="border-b border-slate-200">
                  <th class="text-left py-1.5 font-black text-slate-500 pr-3">Column</th>
                  <th class="text-left py-1.5 font-black text-slate-500">Description</th>
                </tr>
              </thead>
              <tbody class="text-slate-600">
                <tr class="border-b border-slate-100"><td class="py-1.5 pr-3 font-bold">Title *</td><td>Card title (required)</td></tr>
                <tr class="border-b border-slate-100"><td class="py-1.5 pr-3 font-bold">Label</td><td>Must exactly match an existing label on this board</td></tr>
                <tr class="border-b border-slate-100"><td class="py-1.5 pr-3 font-bold">Description</td><td>Full task description</td></tr>
                <tr class="border-b border-slate-100"><td class="py-1.5 pr-3 font-bold">Start Date</td><td>YYYY-MM-DD format</td></tr>
                <tr class="border-b border-slate-100"><td class="py-1.5 pr-3 font-bold">Due Date</td><td>YYYY-MM-DD format</td></tr>
                <tr class="border-b border-slate-100"><td class="py-1.5 pr-3 font-bold">Assigned To</td><td>Member username</td></tr>
                <tr class="border-b border-slate-100"><td class="py-1.5 pr-3 font-bold">Attachment Link</td><td>Any URL (Google Drive, etc.)</td></tr>
                <tr class="border-b border-slate-100"><td class="py-1.5 pr-3 font-bold">Checklist</td><td>Items separated by semicolons</td></tr>
                <tr><td class="py-1.5 pr-3 font-bold">Week</td><td>Column/list name (defaults to first list)</td></tr>
              </tbody>
            </table>
          </div>
        </details>

        {{-- Error display --}}
        <div x-show="importModal.error" class="bg-rose-50 border border-rose-200 rounded-xl p-3 text-xs font-semibold text-rose-700 flex items-start gap-2">
          <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
          <span x-text="importModal.error"></span>
        </div>
      </div>

      {{-- ── STEP 2: Validation Preview ──────────────────────────────────── --}}
      <div x-show="importModal.step === 2" class="space-y-4">
        {{-- Summary cards --}}
        <div class="grid grid-cols-3 gap-3" x-show="importModal.preview">
          <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-center">
            <p class="text-2xl font-black text-slate-800" x-text="importModal.preview?.total || 0"></p>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-0.5">Total Rows</p>
          </div>
          <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-center">
            <p class="text-2xl font-black text-emerald-600" x-text="importModal.preview?.valid || 0"></p>
            <p class="text-[10px] font-black text-emerald-500 uppercase tracking-widest mt-0.5">✅ Valid</p>
          </div>
          <div class="bg-rose-50 border border-rose-200 rounded-xl p-3 text-center">
            <p class="text-2xl font-black text-rose-600" x-text="importModal.preview?.invalid || 0"></p>
            <p class="text-[10px] font-black text-rose-400 uppercase tracking-widest mt-0.5">❌ Invalid</p>
          </div>
        </div>

        {{-- Duplicate warning --}}
        <div x-show="importModal.preview?.rows?.filter(r => r.is_duplicate).length > 0"
             class="bg-amber-50 border border-amber-200 rounded-xl p-3 flex items-start gap-2 text-xs font-semibold text-amber-700">
          <span class="text-base flex-shrink-0">⚠️</span>
          <span>
            <strong x-text="importModal.preview?.rows?.filter(r => r.is_duplicate).length"></strong>
            row(s) have titles that already exist on this board. These are marked as possible duplicates.
          </span>
        </div>

        {{-- Rows preview table --}}
        <div class="border border-slate-200 rounded-xl overflow-hidden">
          <div class="bg-slate-50 px-4 py-2.5 border-b border-slate-200 flex items-center justify-between">
            <p class="text-xs font-black text-slate-700">Import Preview</p>
            <div class="flex items-center gap-2">
              <button @click="importModal.previewFilter = 'all'"
                      class="text-[10px] font-bold px-2 py-1 rounded-lg transition"
                      :class="importModal.previewFilter === 'all' ? 'bg-slate-800 text-white' : 'text-slate-500 hover:bg-slate-200'">
                All
              </button>
              <button @click="importModal.previewFilter = 'invalid'"
                      class="text-[10px] font-bold px-2 py-1 rounded-lg transition"
                      :class="importModal.previewFilter === 'invalid' ? 'bg-rose-600 text-white' : 'text-slate-500 hover:bg-slate-200'">
                Errors only
              </button>
            </div>
          </div>
          <div class="max-h-64 overflow-y-auto scrollbar-thin divide-y divide-slate-100">
            <template x-for="row in importFilteredPreviewRows()" :key="row.row">
              <div class="px-4 py-3 hover:bg-slate-50 transition-colors">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex items-start gap-2.5 flex-1 min-w-0">
                    <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
                         :class="row.valid ? (row.is_duplicate ? 'bg-amber-100' : 'bg-emerald-100') : 'bg-rose-100'">
                      <svg x-show="row.valid && !row.is_duplicate" class="w-3 h-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                      <svg x-show="row.valid && row.is_duplicate" class="w-3 h-3 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                      <svg x-show="!row.valid" class="w-3 h-3 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                      <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-xs font-black text-slate-800 truncate" x-text="row.title || '(No title)'"></p>
                        <span class="text-[9px] font-bold text-slate-400">Row <span x-text="row.row"></span></span>
                        <span x-show="row.is_duplicate" class="text-[9px] font-black text-amber-600 bg-amber-100 px-1.5 py-0.5 rounded-full">DUPLICATE</span>
                      </div>
                      <div class="flex items-center gap-3 mt-1 text-[10px] text-slate-400 font-semibold flex-wrap">
                        <span x-show="row.list_name">→ <span x-text="row.list_name"></span></span>
                        <span x-show="row.assigned_name">👤 <span x-text="row.assigned_name"></span></span>
                        <span x-show="row.start_date">▶ <span x-text="row.start_date"></span></span>
                        <span x-show="row.due_date">📅 <span x-text="row.due_date"></span></span>
                      </div>
                      {{-- Errors --}}
                      <div x-show="row.errors && row.errors.length" class="mt-1.5 space-y-0.5">
                        <template x-for="err in row.errors" :key="err">
                          <p class="text-[10px] font-semibold text-rose-600">⚠ <span x-text="err"></span></p>
                        </template>
                      </div>
                      {{-- Warnings --}}
                      <div x-show="row.warnings && row.warnings.length" class="mt-1 space-y-0.5">
                        <template x-for="w in row.warnings" :key="w">
                          <p class="text-[10px] font-semibold text-amber-600">ℹ <span x-text="w"></span></p>
                        </template>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
            <div x-show="importFilteredPreviewRows().length === 0" class="py-8 text-center text-sm font-semibold text-slate-400">
              No rows to display.
            </div>
          </div>
        </div>

        {{-- Only invalid warning --}}
        <div x-show="importModal.preview?.valid === 0" class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-center">
          <p class="text-sm font-black text-rose-700">No valid rows to import.</p>
          <p class="text-xs font-semibold text-rose-500 mt-1">Fix the errors in your file and try again.</p>
        </div>

        {{-- Partial import notice --}}
        <div x-show="importModal.preview?.invalid > 0 && importModal.preview?.valid > 0"
             class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs font-semibold text-amber-700">
          ℹ️ Only valid rows will be imported. Invalid rows will be skipped.
        </div>

        {{-- Error display --}}
        <div x-show="importModal.error" class="bg-rose-50 border border-rose-200 rounded-xl p-3 text-xs font-semibold text-rose-700 flex items-start gap-2">
          <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
          <span x-text="importModal.error"></span>
        </div>
      </div>

      {{-- ── STEP 3: Done ────────────────────────────────────────────────── --}}
      <div x-show="importModal.step === 3" class="space-y-5 text-center py-4">
        <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto">
          <svg class="w-10 h-10 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
          </svg>
        </div>
        <div>
          <h4 class="text-xl font-black text-slate-800">Import Complete!</h4>
          <p class="text-sm font-semibold text-slate-500 mt-1" x-text="importModal.result ? importModal.result.created + ' card(s) created successfully.' : 'Done!'"></p>
        </div>

        {{-- Import summary --}}
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 text-left space-y-2.5 max-w-xs mx-auto">
          <div class="flex items-center justify-between text-xs">
            <span class="font-semibold text-slate-500">Cards created</span>
            <span class="font-black text-emerald-600" x-text="importModal.result?.created ?? 0"></span>
          </div>
          <div class="flex items-center justify-between text-xs">
            <span class="font-semibold text-slate-500">Rows skipped</span>
            <span class="font-black text-rose-500" x-text="importModal.result?.skipped ?? 0"></span>
          </div>
        </div>

        <p class="text-xs font-semibold text-slate-400">The new cards have been added to the board. Activity logs show they were created via Import.</p>
      </div>

    </div>

    {{-- ── Modal Footer ──────────────────────────────────────────────────── --}}
    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-3 flex-shrink-0">

      {{-- Left side --}}
      <div>
        <button x-show="importModal.step === 2" @click="importModal.step = 1; importModal.preview = null; importModal.error = null"
                class="btn btn-secondary text-xs py-2 px-4 rounded-xl font-semibold flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
          Back
        </button>
      </div>

      {{-- Right side --}}
      <div class="flex items-center gap-2.5">
        <button x-show="importModal.step !== 3" @click="closeImportModal()"
                class="btn btn-secondary text-xs py-2 px-4 rounded-xl font-semibold">
          Cancel
        </button>

        {{-- Step 1: Next --}}
        <button x-show="importModal.step === 1"
                @click="importPreview()"
                :disabled="importModal.busy || (importModal.source === 'csv' && !importModal.file) || (importModal.source === 'sheets' && !importModal.sheetsUrl.trim())"
                class="btn btn-primary text-xs py-2 px-5 rounded-xl font-bold flex items-center gap-1.5 shadow-md disabled:opacity-50 disabled:cursor-not-allowed">
          <span x-show="importModal.busy" class="w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
          <span x-text="importModal.busy ? 'Validating…' : 'Validate & Preview'"></span>
          <svg x-show="!importModal.busy" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
        </button>

        {{-- Step 2: Confirm import --}}
        <button x-show="importModal.step === 2"
                @click="importConfirm()"
                :disabled="importModal.busy || !importModal.preview?.valid"
                class="btn btn-primary text-xs py-2 px-5 rounded-xl font-bold flex items-center gap-1.5 shadow-md disabled:opacity-50 disabled:cursor-not-allowed">
          <span x-show="importModal.busy" class="w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
          <span x-text="importModal.busy ? 'Importing…' : 'Confirm Import (' + (importModal.preview?.valid ?? 0) + ' cards)'"></span>
          <svg x-show="!importModal.busy" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
        </button>

        {{-- Step 3: Done --}}
        <button x-show="importModal.step === 3"
                @click="closeImportModal()"
                class="btn btn-primary text-xs py-2 px-5 rounded-xl font-bold flex items-center gap-1.5 shadow-md">
          Done ✓
        </button>
      </div>
    </div>

  </div>
</div>
