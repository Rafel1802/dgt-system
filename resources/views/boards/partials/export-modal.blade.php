{{-- Export & Reporting Modal --}}
<div x-show="exportModal.open" x-cloak
     class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 overflow-y-auto"
     @click.self="exportModal.open = false">
     
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-slate-100 flex flex-col"
       x-show="exportModal.open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100">
       
    {{-- Modal Header --}}
    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
      <h3 class="font-display font-black text-slate-800 text-base flex items-center gap-2">
        <span>📊</span> Export & Reports
      </h3>
      <button @click="exportModal.open = false" class="text-slate-400 hover:text-slate-600 p-1 hover:bg-slate-200/50 rounded-full transition">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    {{-- Modal Body --}}
    <div class="p-6 space-y-4 max-h-[calc(100vh-200px)] overflow-y-auto scrollbar-thin text-xs text-slate-700">
      
      {{-- Format Selection --}}
      <div>
        <label class="block font-bold text-slate-500 uppercase tracking-wider mb-2">Export Format</label>
        <div class="grid grid-cols-2 gap-3">
          <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition"
                 :class="exportModal.format === 'pdf' ? 'border-indigo-600 bg-indigo-50/35 font-bold text-indigo-700' : ''">
            <input type="radio" value="pdf" x-model="exportModal.format" class="accent-indigo-600 hidden">
            <span class="text-base">📄</span>
            <div>
              <p class="text-xs">PDF Report</p>
              <p class="text-[10px] text-slate-400 font-normal mt-0.5">Management summary</p>
            </div>
          </label>
          <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition"
                 :class="exportModal.format === 'csv' ? 'border-indigo-600 bg-indigo-50/35 font-bold text-indigo-700' : ''">
            <input type="radio" value="csv" x-model="exportModal.format" class="accent-indigo-600 hidden">
            <span class="text-base">📊</span>
            <div>
              <p class="text-xs">CSV Spreadsheet</p>
              <p class="text-[10px] text-slate-400 font-normal mt-0.5">Excel spreadsheet</p>
            </div>
          </label>
        </div>
      </div>

      {{-- Scope Selection --}}
      <div>
        <label class="block font-bold text-slate-500 uppercase tracking-wider mb-2">Export Scope</label>
        <div class="flex items-center gap-6 mb-2">
          <label class="flex items-center gap-2 cursor-pointer font-semibold">
            <input type="radio" value="board" x-model="exportModal.scope" class="accent-indigo-600 rounded">
            <span>Just this board</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer font-semibold">
            <input type="radio" value="boards" x-model="exportModal.scope" class="accent-indigo-600 rounded">
            <span>Multiple boards</span>
          </label>
        </div>

        {{-- Boards Checklist (Visible if scope is 'boards') --}}
        <div x-show="exportModal.scope === 'boards'" x-cloak class="mt-3 p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2 max-h-36 overflow-y-auto scrollbar-thin">
          <template x-for="ws in allWorkspaces" :key="ws.id">
            <div class="space-y-1">
              <p class="font-extrabold text-slate-500 text-[10px] uppercase tracking-wide" x-text="ws.name"></p>
              <div class="pl-2 space-y-1">
                <template x-for="b in (ws.boards || [])" :key="b.id">
                  <label class="flex items-center gap-2 cursor-pointer py-0.5 hover:text-indigo-600">
                    <input type="checkbox" :value="b.id" x-model="exportModal.selectedBoards" class="rounded border-slate-300 accent-indigo-600">
                    <span x-text="b.name"></span>
                  </label>
                </template>
              </div>
            </div>
          </template>
        </div>
      </div>

      {{-- Filtering Options --}}
      <div class="border-t border-slate-100 pt-4 space-y-4">
        <p class="font-bold text-slate-800 text-sm mb-1.5 flex items-center gap-1.5">
          <span>🔍</span> Filtering Options
        </p>

        {{-- Date Range --}}
        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2">
            <label class="block font-semibold text-slate-600 mb-1">Date Range</label>
            <select x-model="exportModal.dateRange" class="form-input w-full text-xs rounded-lg py-1.5 border-slate-200">
              <option value="all_time">All Time</option>
              <option value="this_week">This Week</option>
              <option value="this_month">This Month</option>
              <option value="last_month">Last Month</option>
              <option value="custom_period">Custom Period</option>
            </select>
          </div>
          
          <div x-show="exportModal.dateRange === 'custom_period'" class="col-span-1" x-cloak>
            <label class="block font-semibold text-slate-500 mb-1">Start Date</label>
            <input type="date" x-model="exportModal.startDate" class="form-input w-full text-xs rounded-lg py-1.5 border-slate-200">
          </div>
          <div x-show="exportModal.dateRange === 'custom_period'" class="col-span-1" x-cloak>
            <label class="block font-semibold text-slate-500 mb-1">End Date</label>
            <input type="date" x-model="exportModal.endDate" class="form-input w-full text-xs rounded-lg py-1.5 border-slate-200">
          </div>
        </div>

        {{-- Assigned Members --}}
        <div>
          <label class="block font-semibold text-slate-600 mb-1">Assigned Member</label>
          <select x-model="exportModal.memberId" class="form-input w-full text-xs rounded-lg py-1.5 border-slate-200">
            <option value="all">All Members</option>
            <template x-for="m in allBoardMembers" :key="m.id">
              <option :value="m.id" x-text="m.name"></option>
            </template>
          </select>
        </div>

        {{-- Task Status Checkboxes --}}
        <div>
          <label class="block font-semibold text-slate-600 mb-1.5">Task Status</label>
          <div class="grid grid-cols-2 gap-x-4 gap-y-2">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" value="draft" x-model="exportModal.statuses" class="rounded border-slate-300 accent-indigo-600">
              <span>Draft (To Do / Rejected)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" value="in_progress" x-model="exportModal.statuses" class="rounded border-slate-300 accent-indigo-600">
              <span>In Progress</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" value="review" x-model="exportModal.statuses" class="rounded border-slate-300 accent-indigo-600">
              <span>Review (Under Review / Approved)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" value="completed" x-model="exportModal.statuses" class="rounded border-slate-300 accent-indigo-600">
              <span>Completed (Done)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" value="archived" x-model="exportModal.statuses" class="rounded border-slate-300 accent-indigo-600">
              <span>Archived</span>
            </label>
          </div>
        </div>

        {{-- Display Options --}}
        <div class="p-3 bg-slate-50 border border-slate-100 rounded-xl space-y-2">
          <label class="flex items-center gap-2.5 cursor-pointer font-semibold">
            <input type="checkbox" x-model="exportModal.includeDesc" class="rounded border-slate-300 accent-indigo-600">
            <span>Include task description in report</span>
          </label>
          <label class="flex items-center gap-2.5 cursor-pointer font-semibold">
            <input type="checkbox" x-model="exportModal.includeComments" class="rounded border-slate-300 accent-indigo-600">
            <span>Include card comments in report</span>
          </label>
        </div>
      </div>

    </div>

    {{-- Modal Footer --}}
    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
      <button @click="exportModal.open = false" class="btn btn-secondary py-2 px-4 text-xs font-semibold rounded-xl">
        Cancel
      </button>
      <button @click="triggerExport()" class="btn btn-primary py-2 px-4 text-xs font-bold rounded-xl flex items-center gap-1.5 shadow-md">
        <span>⚡</span> Generate Export
      </button>
    </div>

  </div>
</div>
