{{--
  Trello-style Date Picker Modal
  Triggered by: datePickerOpen = true  (set in trello-board.js datePicker* methods)
  Requires Alpine state: datePicker { open, cardId, calYear, calMonth, startDate,
                         dueDate, dueTime, reminder, recurring, useStart, useDue }
--}}
<div x-show="datePicker.open" x-cloak
     class="fixed inset-0 flex items-center justify-center p-4 z-[110]"
     style="z-index: 110;"
     @click.self="closeDatePicker()"
     @keydown.escape.window="closeDatePicker()">

  {{-- Backdrop --}}
  <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
       @click="closeDatePicker()"></div>

  {{-- Panel: Enlarged from w-80 (320px) to w-[420px] for spacious, clear touch targets --}}
  <div class="date-picker-modal-panel relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-100 dark:border-slate-700 w-[420px] max-w-[95vw] overflow-hidden"
       x-transition:enter="transition ease-out duration-150"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       @click.stop>

    {{-- Header --}}
    <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/90">
      <h3 class="text-sm font-black text-slate-700 dark:text-slate-100 tracking-wider uppercase flex items-center gap-2">
        <span>📅</span> <span>Dates</span>
      </h3>
      <button @click="closeDatePicker()"
              class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    {{-- Month Navigator --}}
    <div class="px-5 pt-4">
      <div class="flex items-center justify-between mb-3">
        <button @click="dpPrevMonth()"
                class="p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
          </svg>
        </button>
        <span class="text-sm font-black text-slate-800 dark:text-slate-100 tracking-wide"
              x-text="dpMonthLabel()"></span>
        <button @click="dpNextMonth()"
                class="p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
          </svg>
        </button>
      </div>

      {{-- Day-of-week headers --}}
      <div class="grid grid-cols-7 mb-1.5">
        <template x-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="d">
          <div class="text-center text-xs font-bold text-slate-400 dark:text-slate-400 py-1" x-text="d"></div>
        </template>
      </div>

      {{-- Calendar grid --}}
      <div class="grid grid-cols-7 gap-1 mb-3">
        <template x-for="cell in dpCalCells()" :key="cell.key">
          <button
            :disabled="!cell.day"
            @click="cell.day && dpSelectDay(cell)"
            class="h-10 w-full rounded-xl text-sm font-semibold transition-all flex items-center justify-center"
            :class="dpDayClass(cell)">
            <span x-text="cell.day || ''"></span>
          </button>
        </template>
      </div>
    </div>

    {{-- Form Controls Section --}}
    <div class="px-5 pb-5 space-y-3.5 border-t border-slate-100 dark:border-slate-700 pt-4">

      {{-- Start date checkbox + input --}}
      <label class="flex items-center gap-3 cursor-pointer group">
        <input type="checkbox" x-model="datePicker.useStart"
               class="rounded accent-indigo-600 w-4 h-4 cursor-pointer">
        <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Start date</span>
        <input x-show="datePicker.useStart" x-cloak
               type="date" x-model="datePicker.startDate"
               class="ml-auto text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:outline-none transition-all">
      </label>

      {{-- Due date checkbox + input --}}
      <label class="flex items-center gap-3 cursor-pointer group">
        <input type="checkbox" x-model="datePicker.useDue"
               class="rounded accent-indigo-600 w-4 h-4 cursor-pointer">
        <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Due date</span>
        <input x-show="datePicker.useDue" x-cloak
               type="date" x-model="datePicker.dueDate"
               class="ml-auto text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:outline-none transition-all">
      </label>

      {{-- Due time --}}
      <div x-show="datePicker.useDue" x-cloak class="flex items-center gap-3">
        <span class="text-sm font-bold text-slate-700 dark:text-slate-200 w-24 flex-shrink-0">Due time</span>
        <input type="time" x-model="datePicker.dueTime"
               class="flex-1 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:outline-none transition-all">
      </div>

      {{-- Reminder --}}
      <div x-show="datePicker.useDue" x-cloak class="flex items-center gap-3">
        <span class="text-sm font-bold text-slate-700 dark:text-slate-200 w-24 flex-shrink-0">Reminder</span>
        <select x-model="datePicker.reminder"
                class="flex-1 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:outline-none transition-all">
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
      <div class="flex items-center gap-3">
        <span class="text-sm font-bold text-slate-700 dark:text-slate-200 w-24 flex-shrink-0">Repeat</span>
        <select x-model="datePicker.recurring"
                class="flex-1 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:outline-none transition-all">
          <option value="none">Does not repeat</option>
          <option value="daily">Daily</option>
          <option value="weekly">Weekly</option>
          <option value="monthly">Monthly</option>
          <option value="yearly">Yearly</option>
        </select>
      </div>

      {{-- Actions --}}
      <div class="flex gap-3 pt-2">
        <button @click="removeDates()"
                class="flex-1 date-picker-btn-remove bg-rose-600 text-white font-bold py-2.5 rounded-xl border border-rose-600 hover:bg-rose-700 shadow-sm transition-all text-sm">
          Remove
        </button>
        <button @click="saveDatePicker()"
                class="flex-1 date-picker-btn-save btn btn-primary py-2.5 font-bold rounded-xl text-sm transition-all">
          Save
        </button>
      </div>

    </div>
  </div>
</div>
