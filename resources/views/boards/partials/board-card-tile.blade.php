@php
  $isWhiteCover = ($board->cover_type === 'color' && in_array(strtolower($board->cover_value ?? ''), ['#ffffff', '#fff', 'white']));
  $isTeamA = str_contains($board->name, 'Team A') || str_contains($board->name, 'TEAM A');
  $isTeamB = str_contains($board->name, 'Team B') || str_contains($board->name, 'TEAM B');
  $isVideo = str_contains(strtolower($board->name), 'video');
  $isGraphic = str_contains(strtolower($board->name), 'graphic');
  $isListing = str_contains(strtolower($board->name), 'listing');
  $isContent = str_contains(strtolower($board->name), 'content');
  $isPlanning = str_contains(strtolower($board->name), 'planning');
@endphp

<div data-board-id="{{ $board->id }}"
     data-board-name="{{ $board->name }}"
     data-cover-type="{{ $board->cover_type ?? $board->background_type }}"
     data-cover-value="{{ $board->cover_value ?? $board->background_value }}"
     x-data="{ openBoardMenu: false }"
     :class="{ 'z-50': openBoardMenu, 'z-10': !openBoardMenu }"
     @mouseenter="if ('{{ $board->background_type === 'image' && $board->background_value ? 1 : 0 }}' === '1' && !window['_preloaded_bg_' + {{ $board->id }}]) { const img = new Image(); img.src = '{{ str_replace('\'', '\\\'', $board->background_value) }}'; window['_preloaded_bg_' + {{ $board->id }}] = true; }"
     title="Drag to move this board left or right"
     class="group block relative h-28 cursor-grab active:cursor-grabbing rounded-xl shadow-xs hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 overflow-hidden {{ $isWhiteCover ? 'border-2 border-slate-200/90 dark:border-slate-700 bg-white dark:bg-slate-800' : '' }}"
     style="{{ $board->coverStyle() }}">
     
  <a href="{{ route('boards.show', $board->slug) }}" 
     data-turbo="false"
     draggable="false"
     @click="if(window.isDraggingBoard || openBoardMenu) { $event.preventDefault(); }"
     class="absolute inset-0 z-0 rounded-xl"></a>

  {{-- Overlay --}}
  @if($isWhiteCover)
    <div class="absolute inset-0 rounded-xl bg-slate-50/50 dark:bg-slate-800/80 group-hover:bg-slate-100/50 dark:group-hover:bg-slate-700/60 transition-colors pointer-events-none"></div>
  @else
    <div class="absolute inset-0 rounded-xl bg-black/20 group-hover:bg-black/10 transition-colors pointer-events-none"></div>
  @endif

  {{-- Badges --}}
  @if($isTeamA)
    <span class="absolute top-2.5 left-2.5 z-10 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-blue-600 text-white shadow-xs pointer-events-none group-hover:opacity-0 transition-opacity">TEAM A</span>
  @elseif($isTeamB)
    <span class="absolute top-2.5 left-2.5 z-10 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-emerald-600 text-white shadow-xs pointer-events-none group-hover:opacity-0 transition-opacity">TEAM B</span>
  @elseif($isVideo)
    <span class="absolute top-2.5 left-2.5 z-10 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-red-800/80 text-white shadow-xs pointer-events-none group-hover:opacity-0 transition-opacity">VIDEO</span>
  @elseif($isGraphic)
    <span class="absolute top-2.5 left-2.5 z-10 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-pink-800/80 text-white shadow-xs pointer-events-none group-hover:opacity-0 transition-opacity">GRAPHIC</span>
  @elseif($isListing)
    <span class="absolute top-2.5 left-2.5 z-10 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-amber-800/80 text-white shadow-xs pointer-events-none group-hover:opacity-0 transition-opacity">LISTING</span>
  @elseif($isContent)
    <span class="absolute top-2.5 left-2.5 z-10 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-sky-800/80 text-white shadow-xs pointer-events-none group-hover:opacity-0 transition-opacity">CONTENT</span>
  @elseif($isPlanning)
    <span class="absolute top-2.5 left-2.5 z-10 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-indigo-600 text-white shadow-xs pointer-events-none group-hover:opacity-0 transition-opacity">PLANNING</span>
  @endif

  {{-- Star --}}
  @if($board->is_starred)
    <div class="absolute top-2 right-2 text-amber-300 z-10 pointer-events-none">
      <svg class="w-4 h-4 fill-current drop-shadow-xs" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
      </svg>
    </div>
  @endif

  {{-- Board name --}}
  <div class="absolute bottom-0 left-0 right-0 p-3 pointer-events-none flex justify-between items-end">
    @if($isWhiteCover)
      <p class="text-slate-800 dark:text-slate-100 font-bold text-sm leading-snug">{{ $board->name }}</p>
    @else
      <p class="text-white font-bold text-sm drop-shadow leading-snug">{{ $board->name }}</p>
    @endif
  </div>
  
  {{-- Edit Board Button --}}
  <button type="button" 
          @click.stop.prevent="openEditBoard({{ $board->id }}, '{{ addslashes($board->name) }}', '{{ $board->cover_type ?? $board->background_type }}', '{{ $board->cover_value ?? $board->background_value }}')"
          class="absolute top-2 left-2 p-1.5 rounded-lg {{ $isWhiteCover ? 'bg-slate-200/80 text-slate-700 dark:bg-slate-700 dark:text-slate-200 hover:bg-slate-300' : 'bg-black/30 text-white hover:bg-black/50' }} opacity-0 group-hover:opacity-100 transition-opacity z-20 shadow-xs"
          title="Change cover color">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
  </button>

  {{-- Three-dot menu (superadmin/admin-digital on hover) --}}
  @if(auth()->user()->canManageBoards())
    <div class="absolute top-2 right-2 z-20 opacity-0 group-hover:opacity-100 transition-opacity" :class="{ 'opacity-100': openBoardMenu }">
      <button @click.stop.prevent="openBoardMenu = !openBoardMenu"
              class="p-1.5 rounded-lg {{ $isWhiteCover ? 'bg-slate-200/80 text-slate-700 dark:bg-slate-700 dark:text-slate-200 hover:bg-slate-300' : 'bg-black/40 text-white hover:bg-black/60' }} transition-colors backdrop-blur-sm shadow-xs">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="10" cy="16" r="1.5"/></svg>
      </button>
      <div x-show="openBoardMenu" @click.outside="openBoardMenu = false" x-cloak
           x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-90" x-transition:enter-end="opacity-100 scale-100"
           class="absolute right-0 top-full mt-1 w-44 bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden py-1 z-30">
        <button @click.stop.prevent="openBoardMenu = false; boardQuickAction('hide', '{{ $board->slug }}', '{{ addslashes($board->name) }}', '{{ isset($isSmmModule) && $isSmmModule ? '/smm-boards' : '/boards' }}')"
                class="w-full flex items-center gap-2.5 px-3.5 py-2.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-amber-50 dark:hover:bg-slate-700 hover:text-amber-700 transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
          Hide Board
        </button>
        <button @click.stop.prevent="openBoardMenu = false; boardQuickAction('delete', '{{ $board->slug }}', '{{ addslashes($board->name) }}', '{{ isset($isSmmModule) && $isSmmModule ? '/smm-boards' : '/boards' }}')"
                class="w-full flex items-center gap-2.5 px-3.5 py-2.5 text-sm text-rose-600 hover:bg-rose-50 dark:hover:bg-slate-700 transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
          Delete Board
        </button>
      </div>
    </div>
  @endif
</div>
