{{-- Export & Reporting Modal --}}
<div x-show="exportModal.open" x-cloak
     class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
     style="z-index: 110;"
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
          <template x-for="ws in allWorkspaces.filter(w => w.boards && w.boards.length > 0)" :key="ws.id">
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

        {{-- Assigned Members & Assign By --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {{-- Assigned Member --}}
          <div class="relative" @click.outside="exportModal.openAssigneeDropdown = false">
            <label class="block font-semibold text-slate-600 mb-1">Assigned Member</label>
            <button type="button"
                    @click="exportModal.openAssigneeDropdown = !exportModal.openAssigneeDropdown; exportModal.openAssignByDropdown = false"
                    class="form-input w-full text-xs rounded-lg py-1.5 px-2.5 border-slate-200 bg-white flex items-center justify-between text-left hover:border-slate-300 focus:ring-1 focus:ring-indigo-500 transition shadow-2xs">
              <div class="flex items-center gap-2 min-w-0">
                <template x-if="!selectedExportMember">
                  <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-500 border border-slate-200 flex items-center justify-center text-[10px] flex-shrink-0">👥</span>
                </template>
                <template x-if="selectedExportMember">
                  <div class="flex items-center gap-1.5 flex-shrink-0">
                    <template x-if="avatarUrl(selectedExportMember)">
                      <img :src="avatarUrl(selectedExportMember)" :alt="selectedExportMember.name" class="w-5 h-5 rounded-full object-cover border border-slate-200 flex-shrink-0">
                    </template>
                    <template x-if="!avatarUrl(selectedExportMember)">
                      <span class="w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-bold text-white border border-white flex-shrink-0"
                            :style="avatarStyle(selectedExportMember)"
                            x-text="avatarInitials(selectedExportMember)"></span>
                    </template>
                  </div>
                </template>
                <span class="truncate font-medium text-slate-700" x-text="selectedExportMember ? selectedExportMember.name : 'All Members'"></span>
              </div>
              <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0 ml-1.5 transition-transform" :class="exportModal.openAssigneeDropdown ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>

            {{-- Assignee Dropdown Popover --}}
            <div x-show="exportModal.openAssigneeDropdown" x-cloak
                 class="absolute left-0 right-0 top-full mt-1 bg-white rounded-xl shadow-xl border border-slate-200 z-30 overflow-hidden"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
              <div class="p-2 border-b border-slate-100 bg-slate-50/70">
                <div class="relative">
                  <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2 top-2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                  </svg>
                  <input type="text"
                         x-model="exportModal.assigneeSearch"
                         @click.stop
                         placeholder="Search members..."
                         class="w-full pl-7 pr-2.5 py-1 text-xs rounded-md border border-slate-200 focus:outline-none focus:border-indigo-500 bg-white">
                </div>
              </div>
              <div class="max-h-48 overflow-y-auto scrollbar-thin py-1 text-xs">
                <button type="button"
                        @click="exportModal.memberId = 'all'; exportModal.openAssigneeDropdown = false"
                        class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-slate-50 transition text-left"
                        :class="exportModal.memberId === 'all' ? 'bg-indigo-50/50 text-indigo-700 font-bold' : 'text-slate-700'">
                  <div class="flex items-center gap-2">
                    <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-500 border border-slate-200 flex items-center justify-center text-[10px]">👥</span>
                    <span>All Members</span>
                  </div>
                  <span x-show="exportModal.memberId === 'all'" class="text-indigo-600 font-bold">✓</span>
                </button>
                <template x-for="m in filteredExportAssignees" :key="'em-assignee-' + m.id">
                  <button type="button"
                          @click="exportModal.memberId = m.id; exportModal.openAssigneeDropdown = false"
                          class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-slate-50 transition text-left"
                          :class="exportModal.memberId == m.id ? 'bg-indigo-50/50 text-indigo-700 font-bold' : 'text-slate-700'">
                    <div class="flex items-center gap-2 min-w-0">
                      <template x-if="avatarUrl(m)">
                        <img :src="avatarUrl(m)" :alt="m.name" class="w-5 h-5 rounded-full object-cover border border-slate-200 flex-shrink-0">
                      </template>
                      <template x-if="!avatarUrl(m)">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-bold text-white border border-white flex-shrink-0"
                              :style="avatarStyle(m)"
                              x-text="avatarInitials(m)"></span>
                      </template>
                      <span class="truncate" x-text="m.name"></span>
                    </div>
                    <span x-show="exportModal.memberId == m.id" class="text-indigo-600 font-bold flex-shrink-0">✓</span>
                  </button>
                </template>
                <div x-show="filteredExportAssignees.length === 0" class="px-3 py-2 text-center text-slate-400 italic text-[11px]">
                  No members found
                </div>
              </div>
            </div>
          </div>

          {{-- Assign By --}}
          <div class="relative" @click.outside="exportModal.openAssignByDropdown = false">
            <label class="block font-semibold text-slate-600 mb-1">Assign By</label>
            <button type="button"
                    @click="exportModal.openAssignByDropdown = !exportModal.openAssignByDropdown; exportModal.openAssigneeDropdown = false"
                    class="form-input w-full text-xs rounded-lg py-1.5 px-2.5 border-slate-200 bg-white flex items-center justify-between text-left hover:border-slate-300 focus:ring-1 focus:ring-indigo-500 transition shadow-2xs">
              <div class="flex items-center gap-2 min-w-0">
                <template x-if="!selectedExportAssignBy">
                  <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-500 border border-slate-200 flex items-center justify-center text-[10px] flex-shrink-0">👥</span>
                </template>
                <template x-if="selectedExportAssignBy">
                  <div class="flex items-center gap-1.5 flex-shrink-0">
                    <template x-if="avatarUrl(selectedExportAssignBy)">
                      <img :src="avatarUrl(selectedExportAssignBy)" :alt="selectedExportAssignBy.name" class="w-5 h-5 rounded-full object-cover border border-slate-200 flex-shrink-0">
                    </template>
                    <template x-if="!avatarUrl(selectedExportAssignBy)">
                      <span class="w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-bold text-white border border-white flex-shrink-0"
                            :style="avatarStyle(selectedExportAssignBy)"
                            x-text="avatarInitials(selectedExportAssignBy)"></span>
                    </template>
                  </div>
                </template>
                <span class="truncate font-medium text-slate-700" x-text="selectedExportAssignBy ? selectedExportAssignBy.name : 'All Members'"></span>
              </div>
              <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0 ml-1.5 transition-transform" :class="exportModal.openAssignByDropdown ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>

            {{-- Assign By Dropdown Popover --}}
            <div x-show="exportModal.openAssignByDropdown" x-cloak
                 class="absolute left-0 right-0 top-full mt-1 bg-white rounded-xl shadow-xl border border-slate-200 z-30 overflow-hidden"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
              <div class="p-2 border-b border-slate-100 bg-slate-50/70">
                <div class="relative">
                  <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2 top-2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                  </svg>
                  <input type="text"
                         x-model="exportModal.assignBySearch"
                         @click.stop
                         placeholder="Search assigner..."
                         class="w-full pl-7 pr-2.5 py-1 text-xs rounded-md border border-slate-200 focus:outline-none focus:border-indigo-500 bg-white">
                </div>
              </div>
              <div class="max-h-48 overflow-y-auto scrollbar-thin py-1 text-xs">
                <button type="button"
                        @click="exportModal.assignById = 'all'; exportModal.openAssignByDropdown = false"
                        class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-slate-50 transition text-left"
                        :class="exportModal.assignById === 'all' ? 'bg-indigo-50/50 text-indigo-700 font-bold' : 'text-slate-700'">
                  <div class="flex items-center gap-2">
                    <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-500 border border-slate-200 flex items-center justify-center text-[10px]">👥</span>
                    <span>All Members</span>
                  </div>
                  <span x-show="exportModal.assignById === 'all'" class="text-indigo-600 font-bold">✓</span>
                </button>
                <template x-for="m in filteredExportAssignBy" :key="'em-assignby-' + m.id">
                  <button type="button"
                          @click="exportModal.assignById = m.id; exportModal.openAssignByDropdown = false"
                          class="w-full flex items-center justify-between px-3 py-1.5 hover:bg-slate-50 transition text-left"
                          :class="exportModal.assignById == m.id ? 'bg-indigo-50/50 text-indigo-700 font-bold' : 'text-slate-700'">
                    <div class="flex items-center gap-2 min-w-0">
                      <template x-if="avatarUrl(m)">
                        <img :src="avatarUrl(m)" :alt="m.name" class="w-5 h-5 rounded-full object-cover border border-slate-200 flex-shrink-0">
                      </template>
                      <template x-if="!avatarUrl(m)">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-bold text-white border border-white flex-shrink-0"
                              :style="avatarStyle(m)"
                              x-text="avatarInitials(m)"></span>
                      </template>
                      <span class="truncate" x-text="m.name"></span>
                    </div>
                    <span x-show="exportModal.assignById == m.id" class="text-indigo-600 font-bold flex-shrink-0">✓</span>
                  </button>
                </template>
                <div x-show="filteredExportAssignBy.length === 0" class="px-3 py-2 text-center text-slate-400 italic text-[11px]">
                  No members found
                </div>
              </div>
            </div>
          </div>

          {{-- Labels Multi-select --}}
          <div class="col-span-1 sm:col-span-2 mt-1 border-t border-slate-100 pt-3">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-1.5">
                <label class="block font-semibold text-slate-700">🏷️ Labels</label>
                <span x-show="exportModal.labelIds && exportModal.labelIds.length > 0"
                      class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-indigo-100 text-indigo-700"
                      x-text="exportModal.labelIds.length + ' selected'"></span>
                <span x-show="!exportModal.labelIds || exportModal.labelIds.length === 0"
                      class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">
                  All Labels
                </span>
              </div>
              <div class="flex items-center gap-2 text-[11px]">
                <button type="button" @click="selectAllExportLabels()" class="text-indigo-600 hover:text-indigo-800 font-semibold transition cursor-pointer">
                  Select All
                </button>
                <span class="text-slate-300">•</span>
                <button type="button" @click="clearExportLabels()" class="text-slate-500 hover:text-slate-700 font-semibold transition cursor-pointer">
                  Clear
                </button>
              </div>
            </div>

            {{-- Labels Pill Buttons --}}
            <div class="flex flex-wrap gap-1.5 max-h-36 overflow-y-auto scrollbar-thin p-1.5 bg-slate-50/70 border border-slate-200/80 rounded-xl">
              {{-- All Labels Pill --}}
              <button type="button"
                      @click="clearExportLabels()"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all cursor-pointer select-none"
                      :class="(!exportModal.labelIds || exportModal.labelIds.length === 0)
                        ? 'border-indigo-600 bg-white text-indigo-700 font-bold shadow-xs ring-1 ring-indigo-500/20'
                        : 'border-slate-200 bg-white/70 text-slate-600 hover:bg-white hover:border-slate-300'">
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                <span>All Labels</span>
                <span x-show="!exportModal.labelIds || exportModal.labelIds.length === 0" class="text-indigo-600 text-xs font-bold">✓</span>
              </button>

              {{-- Dynamic Available Labels (Including Graphic, Video, SMM, Listing, Content, etc.) --}}
              <template x-for="l in availableLabels" :key="l.id || l.name">
                <button type="button"
                        @click="toggleExportLabel(l)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all cursor-pointer select-none"
                        :class="isExportLabelSelected(l)
                          ? 'border-indigo-600 bg-indigo-50/90 text-indigo-900 font-bold shadow-xs ring-1 ring-indigo-500/20'
                          : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300'">
                  <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 shadow-2xs" :style="'background-color:' + (l.color || '#6366f1')"></span>
                  <span x-text="l.name"></span>
                  <svg x-show="isExportLabelSelected(l)" class="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                  </svg>
                </button>
              </template>
            </div>
            <p class="text-[10.5px] text-slate-400 mt-1 pl-0.5">
              <span x-show="!exportModal.labelIds || exportModal.labelIds.length === 0">Exporting all labels. Multi-select any combination (e.g. Video + Graphic) above.</span>
              <span x-show="exportModal.labelIds && exportModal.labelIds.length > 0">Tasks matching <strong class="text-slate-600">any</strong> of the selected labels will be exported.</span>
            </p>
          </div>
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
      <button @click="exportModal.open = false" class="btn btn-cancel btn-secondary py-2 px-4 text-xs font-semibold rounded-xl">
        Cancel
      </button>
      <button @click="triggerExport()" class="btn btn-primary py-2 px-4 text-xs font-bold rounded-xl flex items-center gap-1.5 shadow-md">
        <span>⚡</span> Generate Export
      </button>
    </div>

  </div>
</div>
