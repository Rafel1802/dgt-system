{{-- Global Toast Notification Stack --}}
<div class="fixed bottom-6 right-6 z-[100] space-y-2"
     x-on:show-toast.window="$dispatch('internal-toast', $event.detail)">

  <div x-data="{messages:[]}"
       @internal-toast.window="messages.push({id:Date.now(),...$event.detail}); setTimeout(()=>messages.shift(), 4000)">
    <template x-for="m in messages" :key="m.id">
      <div x-transition:enter="transition ease-out duration-300"
           x-transition:enter-start="opacity-0 translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0"
           x-transition:leave="transition ease-in duration-200"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0"
           :class="m.type === 'error' ? 'bg-rose-600' : m.type === 'warning' ? 'bg-amber-500' : 'bg-emerald-600'"
           class="flex items-center gap-3 text-white text-sm font-medium px-4 py-3 rounded-xl shadow-xl min-w-[260px] max-w-sm">
        <svg x-show="m.type === 'success'" class="w-5 h-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/>
        </svg>
        <svg x-show="m.type === 'error'" class="w-5 h-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm-1.5-9.5a1.5 1.5 0 1 1 3 0v4a1.5 1.5 0 0 1-3 0v-4Zm1.5 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
        </svg>
        <span x-text="m.msg" class="flex-1"></span>
      </div>
    </template>
  </div>
</div>
