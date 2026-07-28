@extends('layouts.app')
@section('title', 'Workspaces & Boards')
@section('page_title', 'Workspaces')

@section('content')
<div class="animate-fade-in space-y-8 pb-28 md:pb-8" x-data="workspacePage()" x-init="selectedWorkspaceId = {{ $workspaces->first()?->id ?? 'null' }}">

  {{-- ── Header ───────────────────────────────────────────────────────── --}}
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-display font-bold text-slate-800">My Workspaces</h1>
      <p class="text-sm text-slate-400 mt-0.5">All your workspaces and boards in one place.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2 sm:gap-3">


      @if(auth()->user()->hasAnyRole(['super-admin', 'admin', 'admin-digital']))
        <button @click="showCreateWorkspace = true" class="btn btn-secondary gap-2 border border-slate-200">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
          New Workspace
        </button>
      @endif

      @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital']))
        <button @click="showHiddenBoards = true" class="btn btn-secondary gap-2 border border-slate-200">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
          Hidden Boards
        </button>
        <button @click="showTrashWorkspaces = true" class="btn bg-white hover:bg-rose-50 text-rose-600 border border-slate-200 hover:border-rose-200 gap-2 shadow-sm transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
          Trash
        </button>
      @endif

      @if(auth()->user()->canCreateBoards())
      <button @click="showCreateBoard = true"
              class="btn btn-primary gap-2">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        New Board
      </button>
      @endif
    </div>
  </div>

  {{-- ── Workspaces ────────────────────────────────────────────────────── --}}
  @forelse($workspaces as $workspace)
    <section>
      {{-- Workspace header --}}
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-3 mb-4">
        <div class="flex items-center gap-2 sm:gap-3 flex-1 min-w-0">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
               style="background-color: {{ $workspace->color }}">
            {{ $workspace->icon_text ?? strtoupper(substr($workspace->name, 0, 1)) }}
          </div>
          <h2 class="font-display font-bold text-slate-700 text-base sm:text-lg break-words min-w-0">{{ $workspace->name }}</h2>
        </div>
        
        <div class="flex items-center gap-2 sm:gap-3 pl-10 sm:pl-0">
          <span class="badge badge-slate text-[10px] sm:text-xs whitespace-nowrap flex-shrink-0">{{ $workspace->boards->count() }} boards</span>
          
          <div class="flex -space-x-1.5 overflow-hidden">
            @foreach($workspace->members->take(5) as $member)
              <img class="inline-block h-6 w-6 rounded-full ring-2 ring-white object-cover" src="{{ $member->avatar_url }}" alt="{{ $member->name }}" title="{{ $member->name }}">
            @endforeach
            @if($workspace->members->count() > 5)
              <span class="inline-flex items-center justify-center h-6 w-6 rounded-full ring-2 ring-white bg-slate-100 text-[10px] font-medium text-slate-500 z-10 relative" title="{{ $workspace->members->count() - 5 }} more members">+{{ $workspace->members->count() - 5 }}</span>
            @endif
          </div>

          @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor', 'Graphic Head', 'Video head', 'QC', 'Listing head', 'Graphic Head', 'Video Head', 'Listing Head']))
            <div class="relative" x-data="{ openWsMembers: false, search: '' }">
              <button @click="openWsMembers = !openWsMembers; search = ''" class="flex-shrink-0 text-slate-500 hover:text-indigo-600 transition-colors p-1 rounded-md hover:bg-indigo-50" title="Manage Members">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
              </button>
              <div x-show="openWsMembers" @click.outside="openWsMembers = false" x-cloak
                   class="absolute right-0 mt-2 w-64 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 p-4"
                   x-transition:enter="transition ease-out duration-100"
                   x-transition:enter-start="opacity-0 scale-95"
                   x-transition:enter-end="opacity-100 scale-100">
                <p class="text-[10px] uppercase font-black text-slate-400 pb-2 border-b border-slate-100 mb-2">Manage Workspace Members</p>
                <input type="text" x-model="search" placeholder="Search members..." class="w-full text-xs bg-slate-50 border-slate-200 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-lg py-1.5 px-2.5 mb-3" @click.stop>
                <div class="max-h-48 overflow-y-auto space-y-2.5 mb-2 pr-1 scrollbar-thin">
                  @php
                    $possibleUsers = $possibleWorkspaceMembers ?? collect();
                    $workspaceMemberIds = $workspace->members->pluck('id');
                  @endphp
                  @foreach($possibleUsers as $u)
                    <div x-show="search === '' || '{{ strtolower(addslashes($u->name)) }}'.includes(search.toLowerCase())" class="flex items-center justify-between gap-2 text-xs">
                      <div class="flex items-center gap-1.5">
                        <img src="{{ $u->avatar_url }}" class="w-6.5 h-6.5 rounded-full object-cover border border-slate-200">
                        <span class="font-semibold text-slate-700 truncate max-w-28" title="{{ $u->name }}">{{ $u->name }}</span>
                      </div>
                      @if($workspaceMemberIds->contains($u->id))
                        <button @click="removeWorkspaceMember({{ $workspace->id }}, {{ $u->id }}, $el)" class="text-[10px] text-rose-500 font-bold px-2.5 py-1 rounded-md hover:bg-rose-500 hover:text-white transition-colors">Remove</button>
                      @else
                        <button @click="addWorkspaceMember({{ $workspace->id }}, {{ $u->id }}, $el)" class="text-[10px] text-indigo-600 font-bold px-2.5 py-1 rounded-md hover:bg-indigo-600 hover:text-white transition-colors">Add</button>
                      @endif
                    </div>
                  @endforeach
                </div>
              </div>
            </div>
          @endif

          @if(auth()->user()->hasAnyRole(['super-admin', 'admin', 'admin-digital']) || $workspace->owner_id === auth()->id())
            <button @click="openEditWorkspace({{ $workspace->id }}, '{{ addslashes($workspace->name) }}', '{{ $workspace->color }}', '{{ addslashes($workspace->icon_text ?? '') }}')" class="flex-shrink-0 text-slate-400 hover:text-indigo-600 transition-colors p-1 rounded-md hover:bg-indigo-50">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            </button>
          @endif
          @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital']))
            <div class="flex items-center gap-1">
              <form method="POST" action="{{ route('boards.workspaces.moveUp', $workspace->id) }}">
                @csrf
                <button type="submit" class="text-slate-300 hover:text-slate-600 transition-colors p-1" title="Move Workspace Up">
                  <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                </button>
              </form>
              <form method="POST" action="{{ route('boards.workspaces.moveDown', $workspace->id) }}">
                @csrf
                <button type="submit" class="text-slate-300 hover:text-slate-600 transition-colors p-1" title="Move Workspace Down">
                  <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
              </form>
            </div>
          @endif
        </div>
      </div>

      {{-- Board grid --}}
      <div class="board-sort-grid grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4"
           data-workspace-id="{{ $workspace->id }}">
        @foreach($workspace->boards as $board)
          <div data-board-id="{{ $board->id }}"
               x-data="{ openBoardMenu: false }"
               :class="{ 'z-50': openBoardMenu, 'z-10': !openBoardMenu }"
               @click="if(!window.isDraggingBoard && !openBoardMenu) window.location.href='{{ route('boards.show', $board->slug) }}'"
               title="Drag to move this board left or right"
               class="group relative h-28 cursor-grab active:cursor-grabbing rounded-xl shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5"
               style="{{ $board->coverStyle() }}">
            {{-- Overlay --}}
            <div class="absolute inset-0 rounded-xl bg-black/20 group-hover:bg-black/10 transition-colors pointer-events-none"></div>

            {{-- Star --}}
            @if($board->is_starred)
              <div class="absolute top-2 right-2 text-amber-300 z-10 pointer-events-none">
                <svg class="w-4 h-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
              </div>
            @endif

            {{-- Board name --}}
            <div class="absolute bottom-0 left-0 right-0 p-3 pointer-events-none flex justify-between items-end">
              <p class="text-white font-semibold text-sm drop-shadow leading-tight">{{ $board->name }}</p>
            </div>
            
            {{-- Edit Board Button --}}
            @if(auth()->user()->hasAnyRole(['super-admin', 'admin', 'admin-digital']) || $workspace->owner_id === auth()->id())
              <button type="button" 
                      @click.stop.prevent="openEditBoard({{ $board->id }}, '{{ addslashes($board->name) }}', '{{ $board->cover_type ?? $board->background_type }}', '{{ $board->cover_value ?? $board->background_value }}')"
                      class="absolute top-2 left-2 p-1.5 rounded-lg bg-black/30 text-white opacity-0 group-hover:opacity-100 transition-opacity hover:bg-black/50"
                      title="Edit Board Cover">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
              </button>
            @endif

            {{-- Three-dot menu (superadmin/admin-digital on hover) --}}
            @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital']))
              <div class="absolute top-2 right-2 z-20 opacity-0 group-hover:opacity-100 transition-opacity" :class="{ 'opacity-100': openBoardMenu }">
                <button @click.stop.prevent="openBoardMenu = !openBoardMenu"
                        class="p-1.5 rounded-lg bg-black/40 text-white hover:bg-black/60 transition-colors backdrop-blur-sm">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="10" cy="16" r="1.5"/></svg>
                </button>
                <div x-show="openBoardMenu" @click.outside="openBoardMenu = false" x-cloak
                     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-90" x-transition:enter-end="opacity-100 scale-100"
                     class="absolute right-0 top-full mt-1 w-44 bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden py-1 z-30">
                  <button @click.stop.prevent="openBoardMenu = false; boardQuickAction('hide', '{{ $board->slug }}', '{{ addslashes($board->name) }}')"
                          class="w-full flex items-center gap-2.5 px-3.5 py-2.5 text-sm text-slate-700 hover:bg-amber-50 hover:text-amber-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                    Hide Board
                  </button>
                  <button @click.stop.prevent="openBoardMenu = false; boardQuickAction('delete', '{{ $board->slug }}', '{{ addslashes($board->name) }}')"
                          class="w-full flex items-center gap-2.5 px-3.5 py-2.5 text-sm text-rose-600 hover:bg-rose-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                    Delete Board
                  </button>
                </div>
              </div>
            @endif
          </div>
        @endforeach

        {{-- Create new board tile --}}
        @if(auth()->user()->canCreateBoards())
        <button @click="openCreateBoard({{ $workspace->id }})"
                class="h-28 rounded-xl border-2 border-dashed border-slate-200 flex flex-col items-center justify-center gap-1.5 text-slate-400 hover:border-indigo-300 hover:text-indigo-500 hover:bg-indigo-50/50 transition-all duration-200">
          <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
          </svg>
          <span class="text-xs font-medium">Create board</span>
        </button>
        @endif
      </div>
    </section>
  @empty
    {{-- Empty state --}}
    <div class="flex flex-col items-center justify-center py-24 text-center">
      <div class="w-20 h-20 rounded-2xl bg-indigo-50 flex items-center justify-center mb-5">
        <svg class="w-10 h-10 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
        </svg>
      </div>
      <h3 class="font-display font-bold text-slate-700 text-xl mb-2">No workspaces yet</h3>
      <p class="text-slate-400 text-sm mb-6">Run the seeder to create your first workspace, or contact your admin.</p>
      <p class="text-xs font-mono bg-slate-100 px-3 py-2 rounded-lg text-slate-500">
        php artisan db:seed --class=WorkspaceBoardSeeder
      </p>
    </div>
  @endforelse

  {{-- ── Create Board Modal ───────────────────────────────────────────── --}}
  @if(auth()->user()->canCreateBoards())
  <div x-show="showCreateBoard" x-cloak
       class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
       @click.self="showCreateBoard = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6"
         @click.stop>
      <h3 class="font-display font-bold text-slate-800 text-lg mb-5">Create New Board</h3>

      <form method="POST" action="{{ route('boards.store') }}" enctype="multipart/form-data" class="space-y-4" x-data="{ template: 'normal', bgType: 'color', customColor: '#6366f1', customImage: '', month: '{{ date('F') }}', year: '{{ date('Y') }}' }" x-init="$watch('template', value => { if (value === 'workflow') customColor = '#ffffff'; else if (value === 'planning') customColor = '#ef4444'; else customColor = '#6366f1'; })">
        @csrf
        <div>
          <label class="form-label">Workspace</label>
          <select name="workspace_id" x-model="selectedWorkspaceId" class="form-input" required>
            @foreach($workspaces as $ws)
              <option value="{{ $ws->id }}">{{ $ws->name }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="form-label">Template</label>
          <select name="template" x-model="template" class="form-input">
            <option value="normal">Normal (Empty)</option>
            <option value="workflow">Workflow board</option>
            <option value="planning">Planning board</option>
          </select>
        </div>

        <div x-show="template === 'workflow' || template === 'planning'" x-cloak class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Month</label>
            <select name="template_month" x-model="month" class="form-input">
              @foreach(['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] as $m)
                <option value="{{ $m }}" {{ date('F') === $m ? 'selected' : '' }}>{{ $m }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Year</label>
            <select name="template_year" x-model="year" class="form-input">
              @for($y = date('Y') - 1; $y <= date('Y') + 2; $y++)
                <option value="{{ $y }}" {{ date('Y') == $y ? 'selected' : '' }}>{{ $y }}</option>
              @endfor
            </select>
          </div>
        </div>

        <div x-show="template === 'normal'" x-cloak>
          <label class="form-label">Board Name <span class="text-red-500">*</span></label>
          <input type="text" name="name" class="form-input" placeholder="e.g. Planning Board" :required="template === 'normal'" autofocus>
        </div>

        <div>
          <label class="form-label">Background Type</label>
          <div class="flex gap-4 mt-1 mb-3">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="background_type" value="color" x-model="bgType" class="text-indigo-600 focus:ring-indigo-500">
              <span class="text-sm text-slate-700">Color</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="background_type" value="image" x-model="bgType" class="text-indigo-600 focus:ring-indigo-500">
              <span class="text-sm text-slate-700">Image</span>
            </label>
          </div>

          <div x-show="bgType === 'color'" x-cloak>
            <label class="form-label">Background Color</label>
            <div class="flex items-center gap-3 mt-2">
              <label class="cursor-pointer relative group flex-shrink-0" title="Choose any color">
                <input type="color" x-model="customColor" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" @input="$refs.customRadio.checked = true">
                <input type="radio" x-bind:value="customColor" class="peer sr-only" x-ref="customRadio" :checked="bgType === 'color'">
                <span class="block w-10 h-10 rounded-xl border-2 border-white peer-checked:ring-4 peer-checked:ring-offset-1 peer-checked:scale-105 hover:scale-105 transition-all duration-200 flex items-center justify-center shadow-sm overflow-hidden"
                      x-bind:style="'background: conic-gradient(#ef4444, #f59e0b, #10b981, #06b6d4, #3b82f6, #8b5cf6, #d946ef, #ef4444); --tw-ring-color: ' + customColor">
                    <span class="block w-4 h-4 rounded-full border-2 border-white shadow-md" x-bind:style="'background-color: ' + customColor"></span>
                </span>
                <input type="hidden" name="background_value" x-model="customColor" :disabled="bgType !== 'color'">
              </label>
              
              <div class="relative">
                <input type="text" x-model="customColor" class="form-input w-28 uppercase font-mono text-sm" placeholder="#FFFFFF" maxlength="7" @input="if(!$el.value.startsWith('#')) $el.value = '#' + $el.value; customColor = $el.value; $refs.customRadio.checked = true">
              </div>
            </div>
          </div>

          <div x-show="bgType === 'image'" x-cloak class="space-y-3">
            <div>
              <label class="form-label">Upload Image (Auto-converts to WebP)</label>
              <input type="file" name="background_image_file" accept="image/*" class="form-input !p-1.5 text-sm file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100" :disabled="bgType !== 'image'">
            </div>
            
            <div class="relative flex items-center py-2">
              <div class="flex-grow border-t border-slate-200"></div>
              <span class="flex-shrink-0 mx-4 text-slate-400 text-xs font-medium uppercase">Or paste URL</span>
              <div class="flex-grow border-t border-slate-200"></div>
            </div>

            <div>
              <label class="form-label">Image URL</label>
              <input type="url" name="background_value" x-model="customImage" class="form-input" placeholder="https://images.unsplash.com/..." :disabled="bgType !== 'image'">
              <p class="text-[10px] text-slate-500 mt-1">Provide a valid image URL if not uploading a file.</p>
            </div>
          </div>
        </div>

        <div>
          <label class="form-label">Visibility</label>
          <select name="visibility" class="form-input">
            <option value="workspace">Workspace (all members)</option>
            <option value="private">Private (only you)</option>
            <option value="public">Public (anyone can view)</option>
          </select>
        </div>

        <div class="flex gap-3 pt-2">
          <button type="button" @click="showCreateBoard = false" class="btn btn-secondary flex-1">Cancel</button>
          <button type="submit" class="btn btn-primary flex-1">Create Board</button>
        </div>
      </form>
    </div>
  </div>
  @endif

  {{-- ── Create Workspace Modal ───────────────────────────────────────────── --}}
  <div x-show="showCreateWorkspace" x-cloak
       class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
       @click.self="showCreateWorkspace = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6"
         @click.stop>
      <h3 class="font-display font-bold text-slate-800 text-lg mb-5">Create New Workspace</h3>

      <form method="POST" action="{{ route('boards.workspaces.store') }}" class="space-y-4">
        @csrf

        <div>
          <label class="form-label">Workspace Name <span class="text-red-500">*</span></label>
          <input type="text" name="name" class="form-input" placeholder="e.g. Marketing Team" required autofocus>
        </div>

        <div>
          <label class="form-label">Color Theme <span class="text-red-500">*</span></label>
          <input type="text" name="color" class="form-input font-mono uppercase" value="#6366f1" pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$" required>
        </div>

        <div>
          <label class="form-label">Icon Text (Optional)</label>
          <input type="text" name="icon_text" class="form-input" placeholder="e.g. L" maxlength="5">
          <p class="text-xs text-slate-500 mt-1">Leave empty to auto-use the first letter.</p>
        </div>

        <div class="flex gap-3 pt-2">
          <button type="button" @click="showCreateWorkspace = false" class="btn btn-secondary flex-1">Cancel</button>
          <button type="submit" class="btn btn-primary flex-1">Create Workspace</button>
        </div>
      </form>
    </div>
  </div>

  {{-- ── Edit Workspace Modal ─────────────────────────────────────────────── --}}
  <div x-show="editWorkspaceModal.open" x-cloak
       class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
       @click.self="editWorkspaceModal.open = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6"
         @click.stop>
      <h3 class="font-display font-bold text-slate-800 text-lg mb-5">Rename Workspace</h3>

      <form method="POST" :action="'/boards/workspaces/' + editWorkspaceModal.id" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
          <label class="form-label">Workspace Name <span class="text-red-500">*</span></label>
          <input type="text" name="name" x-model="editWorkspaceModal.name" class="form-input" required autofocus>
        </div>

        <div>
          <label class="form-label">Color Theme <span class="text-red-500">*</span></label>
          <input type="text" name="color" x-model="editWorkspaceModal.color" class="form-input font-mono uppercase" pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$" required>
        </div>

        <div>
          <label class="form-label">Icon Text (Optional)</label>
          <input type="text" name="icon_text" x-model="editWorkspaceModal.icon_text" class="form-input" maxlength="5">
          <p class="text-xs text-slate-500 mt-1">Leave empty to auto-use the first letter.</p>
        </div>

        <div class="flex gap-3 pt-2">
          <button type="button" @click="editWorkspaceModal.open = false" class="btn btn-secondary flex-1">Cancel</button>
          <button type="submit" class="btn btn-primary flex-1">Save Changes</button>
        </div>
      </form>

      @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital']))
        <form method="POST" :action="`/boards/workspaces/${editWorkspaceModal.id}`" class="mt-4 pt-4 border-t border-rose-100 dark:border-rose-900/30" onsubmit="return confirm('Are you sure you want to move this workspace to trash? All its boards will be hidden until restored.')">
          @csrf
          @method('DELETE')
          <button type="submit" class="w-full flex items-center justify-center gap-2 py-2 px-4 rounded-xl border border-rose-200 text-rose-600 font-bold text-sm hover:bg-rose-50 hover:border-rose-300 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
            Move to Trash
          </button>
        </form>
      @endif
    </div>
  </div>

  {{-- ── Hidden Boards Modal ─────────────────────────────────────────────── --}}
  @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital']))
  <div x-show="showHiddenBoards" x-cloak
       class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 transition-opacity"
       x-transition:enter="ease-out duration-300"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="ease-in duration-200"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       @click.self="showHiddenBoards = false">
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-2xl flex flex-col max-h-[85vh] overflow-hidden transform transition-all"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
         @click.stop>
      
      {{-- Modal Header --}}
      <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 flex items-center justify-center shadow-sm border border-violet-200 dark:border-violet-800">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19.5c-4.638 0-8.573-3.007-9.963-7.178.709-2.056 2.008-3.685 3.65-4.851zm0 0l-3.414-3.414m3.414 3.414L15 15m0 0l-3.414-3.414M15 15l-3.414-3.414m0 0L8.172 8.172m0 0L3 3m5.172 5.172A10.04 10.04 0 0112 4.5c4.638 0 8.573 3.007 9.963 7.178-.316.916-.763 1.776-1.32 2.56m-5.46 2.093A3.001 3.001 0 019.586 9.586" /></svg>
          </div>
          <div>
            <h3 class="font-display font-bold text-slate-800 dark:text-slate-100 text-lg">Hidden Boards</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Manage boards that are currently hidden from workspaces.</p>
          </div>
        </div>
        <button @click="showHiddenBoards = false" class="w-8 h-8 flex items-center justify-center rounded-full text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 dark:hover:text-slate-300 transition-colors">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      {{-- Modal Body --}}
      <div class="overflow-y-auto flex-1 p-6 space-y-3 scrollbar-thin bg-white dark:bg-slate-900">
        @forelse($hiddenBoards ?? [] as $hb)
          <div class="hidden-board-item flex items-center justify-between bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-4 shadow-sm hover:shadow-md hover:border-violet-300 dark:hover:border-violet-600 transition-all duration-200">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-bold flex-shrink-0 shadow-inner"
                   style="{{ $hb->backgroundStyle() }}">
                <span class="drop-shadow-md">{{ strtoupper(substr($hb->name, 0, 1)) }}</span>
              </div>
              <div>
                <h4 class="font-bold text-slate-800 dark:text-slate-100 text-base mb-0.5">{{ $hb->name }}</h4>
                <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                  <span>{{ $hb->workspace->name ?? 'Unknown Workspace' }}</span>
                </div>
              </div>
            </div>
            <button onclick="unhideBoard('{{ $hb->slug }}', this)" 
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-bold text-violet-700 bg-violet-50 hover:bg-violet-600 hover:text-white hover:shadow-lg hover:shadow-violet-200 dark:bg-violet-900/30 dark:text-violet-300 dark:hover:bg-violet-600 dark:hover:text-white transition-all duration-200 active:scale-95">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.22.611.22 1.28 0 1.889-1.4 4.172-5.337 7.178-9.963 7.178-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
              Restore Board
            </button>
          </div>
        @empty
          <div class="flex flex-col items-center justify-center py-12 text-center">
            <div class="w-20 h-20 bg-slate-50 dark:bg-slate-800 rounded-full flex items-center justify-center mb-4">
              <svg class="w-10 h-10 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
            </div>
            <h4 class="text-lg font-bold text-slate-700 dark:text-slate-200">No Hidden Boards</h4>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">You don't have any hidden boards. When you hide a board, it will appear here so you can restore it later.</p>
          </div>
        @endforelse
      </div>
    </div>
  </div>
  @endif

  {{-- ── Trash Workspaces Modal (with checkboxes & bulk actions) ───────────── --}}
  @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital']))
  <div x-show="showTrashWorkspaces" x-cloak
       x-data="trashManager()"
       class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 transition-opacity"
       x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
       x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
       @click.self="showTrashWorkspaces = false">
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-2xl flex flex-col max-h-[85vh] overflow-hidden transform transition-all"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-8 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-8 sm:scale-95"
         @click.stop>
      
      {{-- Modal Header --}}
      <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shadow-sm border border-rose-200">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
          </div>
          <div>
            <h3 class="font-display font-bold text-slate-800 text-lg">Trash (Workspaces & Boards)</h3>
            <p class="text-xs text-slate-500">Select items to recover or permanently delete.</p>
          </div>
        </div>
        <button @click="showTrashWorkspaces = false" class="w-8 h-8 flex items-center justify-center rounded-full text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      {{-- Bulk Actions Bar --}}
      @php $totalTrashed = (isset($trashedWorkspaces) ? $trashedWorkspaces->count() : 0) + (isset($trashedBoards) ? $trashedBoards->count() : 0); @endphp
      @if($totalTrashed > 0)
      <div class="px-6 py-3 border-b border-slate-100 bg-white flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3">
          <label class="flex items-center gap-2 cursor-pointer select-none group">
            <input type="checkbox" @change="toggleSelectAll($event.target.checked, [{{ isset($trashedWorkspaces) ? $trashedWorkspaces->pluck('id')->implode(',') : '' }}], [{{ isset($trashedBoards) ? $trashedBoards->pluck('id')->implode(',') : '' }}])" :checked="selectedCount === {{ $totalTrashed }}" :indeterminate="selectedCount > 0 && selectedCount < {{ $totalTrashed }}"
                   class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0 transition-colors cursor-pointer">
            <span class="text-sm font-medium text-slate-600 group-hover:text-slate-800 transition-colors">Select All</span>
          </label>
          <span x-show="selectedCount > 0" x-cloak class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full" x-text="selectedCount + ' selected'"></span>
        </div>
        <div x-show="selectedCount > 0" x-cloak class="flex items-center gap-2" x-transition>
          <button @click="bulkAction('recover')" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white border border-emerald-200 hover:border-emerald-600 transition-all duration-200 shadow-sm">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
            Recover
          </button>
          <button @click="bulkAction('delete')" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-600 hover:text-white border border-rose-200 hover:border-rose-600 transition-all duration-200 shadow-sm">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg>
            Delete Forever
          </button>
        </div>
      </div>
      @endif

      {{-- Modal Body --}}
      <div class="overflow-y-auto flex-1 p-6 space-y-6 scrollbar-thin bg-white">
        
        {{-- Workspaces --}}
        @if(isset($trashedWorkspaces) && $trashedWorkspaces->count() > 0)
        <div>
          <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Workspaces</h4>
          <div class="space-y-2.5">
            @foreach($trashedWorkspaces as $tw)
              <div class="trash-item flex items-center gap-3 bg-white border border-slate-200 rounded-2xl p-4 shadow-sm hover:shadow-md hover:border-indigo-200 transition-all duration-200"
                   :class="selected.workspaces.includes({{ $tw->id }}) ? 'ring-2 ring-indigo-400 border-indigo-300 bg-indigo-50/30' : ''">
                <input type="checkbox" value="{{ $tw->id }}" @change="toggleItem('workspaces', {{ $tw->id }})"
                       :checked="selected.workspaces.includes({{ $tw->id }})"
                       class="w-4.5 h-4.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0 cursor-pointer flex-shrink-0">
                <div class="flex-1 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold flex-shrink-0 shadow-inner text-sm"
                         style="background-color: {{ $tw->color }}">{{ strtoupper(substr($tw->name, 0, 1)) }}</div>
                    <div>
                      <h4 class="font-bold text-slate-800 text-sm">{{ $tw->name }}</h4>
                      <p class="text-xs text-slate-400">Deleted {{ $tw->deleted_at->diffForHumans() }}</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <button @click="singleAction('recover', 'workspace', {{ $tw->id }}, '{{ addslashes($tw->name) }}')" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white transition-all duration-200">Recover</button>
                    <button @click="singleAction('delete', 'workspace', {{ $tw->id }}, '{{ addslashes($tw->name) }}')" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-600 hover:text-white transition-all duration-200">Delete Forever</button>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        </div>
        @endif

        {{-- Boards --}}
        @if(isset($trashedBoards) && $trashedBoards->count() > 0)
        <div>
          <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Boards</h4>
          <div class="space-y-2.5">
            @foreach($trashedBoards as $tb)
              <div class="trash-item flex items-center gap-3 bg-white border border-slate-200 rounded-2xl p-4 shadow-sm hover:shadow-md hover:border-indigo-200 transition-all duration-200"
                   :class="selected.boards.includes({{ $tb->id }}) ? 'ring-2 ring-indigo-400 border-indigo-300 bg-indigo-50/30' : ''">
                <input type="checkbox" value="{{ $tb->id }}" @change="toggleItem('boards', {{ $tb->id }})"
                       :checked="selected.boards.includes({{ $tb->id }})"
                       class="w-4.5 h-4.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0 cursor-pointer flex-shrink-0">
                <div class="flex-1 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex-shrink-0 shadow-inner" style="{{ $tb->coverStyle() }}"></div>
                    <div>
                      <h4 class="font-bold text-slate-800 text-sm">{{ $tb->name }}</h4>
                      <p class="text-xs text-slate-400">In: {{ $tb->workspace->name ?? 'Unknown' }} &bull; Deleted {{ $tb->deleted_at->diffForHumans() }}</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <button @click="singleAction('recover', 'board', {{ $tb->id }}, '{{ addslashes($tb->name) }}')" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white transition-all duration-200">Recover</button>
                    <button @click="singleAction('delete', 'board', {{ $tb->id }}, '{{ addslashes($tb->name) }}')" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-600 hover:text-white transition-all duration-200">Delete Forever</button>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        </div>
        @endif

        @if((!isset($trashedWorkspaces) || $trashedWorkspaces->count() === 0) && (!isset($trashedBoards) || $trashedBoards->count() === 0))
          <div class="text-center py-10">
            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-100">
              <svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
            </div>
            <h4 class="text-lg font-bold text-slate-700">Trash is empty</h4>
            <p class="text-sm text-slate-500 mt-1 max-w-sm mx-auto">Deleted workspaces and boards will appear here.</p>
          </div>
        @endif
      </div>
    </div>

    {{-- Pretty Confirmation Popup (nested inside trash modal) --}}
    <div x-show="confirmPopup.open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" @click.self="confirmPopup.open = false">
      <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"></div>
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 transform transition-all"
           x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-90" x-transition:enter-end="opacity-100 scale-100"
           x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-90">
        <div class="text-center">
          <div class="w-14 h-14 rounded-2xl mx-auto mb-4 flex items-center justify-center"
               :class="confirmPopup.type === 'delete' ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600'">
            <template x-if="confirmPopup.type === 'delete'">
              <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
            </template>
            <template x-if="confirmPopup.type === 'recover'">
              <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
            </template>
          </div>
          <h4 class="text-lg font-bold text-slate-800 mb-2" x-text="confirmPopup.title"></h4>
          <p class="text-sm text-slate-500 leading-relaxed mb-6" x-html="confirmPopup.message"></p>
          <div class="flex gap-3">
            <button @click="confirmPopup.open = false" class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors">Cancel</button>
            <button @click="confirmPopup.resolve(true); confirmPopup.open = false"
                    class="flex-1 px-4 py-2.5 text-sm font-bold rounded-xl text-white transition-all duration-200 shadow-md hover:shadow-lg"
                    :class="confirmPopup.type === 'delete' ? 'bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700' : 'bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700'"
                    x-text="confirmPopup.type === 'delete' ? 'Delete Forever' : 'Recover'"></button>
          </div>
        </div>
      </div>
    </div>
  </div>
  @endif

  {{-- ── Edit Board Modal ───────────────────────────────────────────── --}}
  <div x-show="editBoardModal.open" x-cloak
       class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
       @click.self="editBoardModal.open = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" @click.stop>
      <h3 class="font-display font-bold text-slate-800 text-lg mb-5">Edit Board</h3>

      <form method="POST" :action="`/boards/${editBoardModal.id}/basic-update`" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
          <label class="form-label">Board Name <span class="text-red-500">*</span></label>
          <input type="text" name="name" x-model="editBoardModal.name" class="form-input" required>
        </div>

        <div>
          <label class="form-label">Background Type</label>
          <div class="flex gap-4 mt-1 mb-3">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="background_type" value="color" x-model="editBoardModal.bgType" class="text-indigo-600 focus:ring-indigo-500">
              <span class="text-sm text-slate-700">Color</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="background_type" value="image" x-model="editBoardModal.bgType" class="text-indigo-600 focus:ring-indigo-500">
              <span class="text-sm text-slate-700">Image</span>
            </label>
          </div>

          <div x-show="editBoardModal.bgType === 'color'" x-cloak>
            <label class="form-label">Background Color</label>
            <div class="flex items-center gap-3 mt-2">
              <label class="cursor-pointer relative group flex-shrink-0" title="Choose any color">
                <input type="color" x-model="editBoardModal.customColor" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" @input="$refs.editCustomRadio.checked = true">
                <input type="radio" x-bind:value="editBoardModal.customColor" class="peer sr-only" x-ref="editCustomRadio" :checked="editBoardModal.bgType === 'color'">
                <span class="block w-10 h-10 rounded-xl border-2 border-white peer-checked:ring-4 peer-checked:ring-offset-1 peer-checked:scale-105 hover:scale-105 transition-all duration-200 flex items-center justify-center shadow-sm overflow-hidden"
                      x-bind:style="'background: conic-gradient(#ef4444, #f59e0b, #10b981, #06b6d4, #3b82f6, #8b5cf6, #d946ef, #ef4444); --tw-ring-color: ' + editBoardModal.customColor">
                    <span class="block w-4 h-4 rounded-full border-2 border-white shadow-md" x-bind:style="'background-color: ' + editBoardModal.customColor"></span>
                </span>
                <input type="hidden" name="background_value" x-model="editBoardModal.customColor" :disabled="editBoardModal.bgType !== 'color'">
              </label>

              <div class="relative">
                <input type="text" x-model="editBoardModal.customColor" class="form-input w-28 uppercase font-mono text-sm" placeholder="#FFFFFF" maxlength="7" @input="if(!$el.value.startsWith('#')) $el.value = '#' + $el.value; editBoardModal.customColor = $el.value; $refs.editCustomRadio.checked = true">
              </div>
            </div>
          </div>

          <div x-show="editBoardModal.bgType === 'image'" x-cloak class="space-y-3">
            <div>
              <label class="form-label">Upload Image (Auto-converts to WebP)</label>
              <input type="file" name="background_image_file" accept="image/*" class="form-input !p-1.5 text-sm file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100" :disabled="editBoardModal.bgType !== 'image'">
            </div>
            
            <div class="relative flex items-center py-2">
              <div class="flex-grow border-t border-slate-200"></div>
              <span class="flex-shrink-0 mx-4 text-slate-400 text-xs font-medium uppercase">Or paste URL</span>
              <div class="flex-grow border-t border-slate-200"></div>
            </div>

            <div>
              <label class="form-label">Image URL</label>
              <input type="url" name="background_value" x-model="editBoardModal.customImage" class="form-input" placeholder="https://images.unsplash.com/..." :disabled="editBoardModal.bgType !== 'image'">
              <p class="text-[10px] text-slate-500 mt-1">Provide a valid image URL if not uploading a file.</p>
            </div>
          </div>
        </div>

        <div class="flex gap-3 pt-2">
          <button type="button" @click="editBoardModal.open = false" class="btn btn-secondary flex-1">Cancel</button>
          <button type="submit" class="btn btn-primary flex-1">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

</div>
@endsection
