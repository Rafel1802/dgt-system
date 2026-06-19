{{--
  Trello-style Date Picker Modal
  Triggered by: datePickerOpen = true  (set in trello-board.js datePicker* methods)
  Requires Alpine state: datePicker { open, cardId, calYear, calMonth, startDate,
                         dueDate, dueTime, reminder, recurring, useStart, useDue }
--}}
<div x-show="datePicker.open" x-cloak
     class="fixed inset-0 z-[60] flex items-center justify-center p-4"
     @click.self="closeDatePicker()"
     @keydown.escape.window="closeDatePicker()">

  {{-- Backdrop --}}
  <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
       @click="closeDatePicker()"></div>

  {{-- Panel --}}
  <div class="relative bg-white rounded-2xl shadow-2xl border border-slate-100 w-80 overflow-hidden"
       x-transition:enter="transition ease-out duration-150"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       @click.stop>

    {{-- Header --}}
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-slate-50/60">
      <h3 class="text-xs font-black text-slate-700 tracking-wide uppercase">📅 Dates</h3>
      <button @click="closeDatePicker()"
              class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    {{-- Month Navigator --}}
    <div class="px-4 pt-4">
      <div class="flex items-center justify-between mb-3">
        <button @click="dpPrevMonth()"
                class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
          </svg>
        </button>
        <span class="text-xs font-bold text-slate-700"
              x-text="dpMonthLabel()"></span>
        <button @click="dpNextMonth()"
                class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
          </svg>
        </button>
      </div>

      {{-- Day-of-week headers --}}
      <div class="grid grid-cols-7 mb-1">
        <template x-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="d">
          <div class="text-center text-[10px] font-bold text-slate-400 py-0.5" x-text="d"></div>
        </template>
      </div>

      {{-- Calendar grid --}}
      <div class="grid grid-cols-7 gap-y-0.5 mb-2">
        <template x-for="cell in dpCalCells()" :key="cell.key">
          <button
            :disabled="!cell.day"
            @click="cell.day && dpSelectDay(cell)"
            class="h-8 w-full rounded-lg text-xs font-medium transition-all"
            :class="dpDayClass(cell)">
            <span x-text="cell.day || ''"></span>
          </button>
        </template>
      </div>
    </div>

    <div class="px-4 pb-4 space-y-3 border-t border-slate-100 pt-3">

      {{-- Start date checkbox --}}
      <label class="flex items-center gap-2.5 cursor-pointer group">
        <input type="checkbox" x-model="datePicker.useStart"
               class="rounded accent-indigo-600 w-3.5 h-3.5">
        <span class="text-xs font-semibold text-slate-600">Start date</span>
        <input x-show="datePicker.useStart" x-cloak
               type="date" x-model="datePicker.startDate"
               class="ml-auto text-xs bg-white border border-slate-200 rounded-lg px-2 py-0.5 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:outline-none">
      </label>

      {{-- Due date checkbox + input --}}
      <label class="flex items-center gap-2.5 cursor-pointer group">
        <input type="checkbox" x-model="datePicker.useDue"
               class="rounded accent-indigo-600 w-3.5 h-3.5">
        <span class="text-xs font-semibold text-slate-600">Due date</span>
        <input x-show="datePicker.useDue" x-cloak
               type="date" x-model="datePicker.dueDate"
               class="ml-auto text-xs bg-white border border-slate-200 rounded-lg px-2 py-0.5 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:outline-none">
      </label>

      {{-- Due time --}}
      <div x-show="datePicker.useDue" x-cloak class="flex items-center gap-2.5">
        <span class="text-xs font-semibold text-slate-600 w-20 flex-shrink-0">Due time</span>
        <input type="time" x-model="datePicker.dueTime"
               class="flex-1 text-xs bg-white border border-slate-200 rounded-lg px-2 py-0.5 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:outline-none">
      </div>

      {{-- Reminder --}}
      <div x-show="datePicker.useDue" x-cloak class="flex items-center gap-2.5">
        <span class="text-xs font-semibold text-slate-600 w-20 flex-shrink-0">Reminder</span>
        <select x-model="datePicker.reminder"
                class="flex-1 text-xs bg-white border border-slate-200 rounded-lg px-2 py-0.5 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:outline-none">
          <option value="">None</option>
          <option value="0">At due time</option>
          <option value="5">5 min before</option>
          <option value="10">10 min before</option>
          <option value="15">15 min before</option>
          <option value="30">30 min before</option>
          <option value="60">1 hour before</option>
          <option value="1440">1 day before</option>
          <option value="2880">2 days before</option>
        </select>
      </div>

      {{-- Recurring --}}
      <div class="flex items-center gap-2.5">
        <span class="text-xs font-semibold text-slate-600 w-20 flex-shrink-0">Repeat</span>
        <select x-model="datePicker.recurring"
                class="flex-1 text-xs bg-white border border-slate-200 rounded-lg px-2 py-0.5 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:outline-none">
          <option value="none">Does not repeat</option>
          <option value="daily">Daily</option>
          <option value="weekly">Weekly</option>
          <option value="monthly">Monthly</option>
          <option value="yearly">Yearly</option>
        </select>
      </div>

      {{-- Actions --}}
      <div class="flex gap-2 pt-1">
        <button @click="saveDatePicker()"
                class="btn btn-primary flex-1 py-2">
          Save
        </button>
        <button @click="removeDates()"
                class="flex-1 bg-rose-600 text-white font-bold py-2 rounded-xl border border-rose-600 hover:bg-white hover:text-rose-600 shadow-sm transition-colors text-xs">
          Remove
        </button>
      </div>

    </div>
  </div>
</div>
