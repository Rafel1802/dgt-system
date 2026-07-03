@extends('layouts.app')
@section('title', $board->name)

@push('head')
<!-- Quill Theme -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<style>
[x-cloak]{display:none!important}
.board-wrap{display:flex;gap:1rem;overflow-x:auto;padding:1rem;align-items:flex-start;min-height:calc(100vh - 180px);border-radius:1.25rem;box-shadow:inset 0 1px 0 rgba(255,255,255,.42),0 18px 50px rgba(15,23,42,.08);transition:background .25s ease,box-shadow .25s ease}
.board-list{flex-shrink:0;width:272px;background:rgba(241,245,249,.9);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.54);border-radius:12px;display:flex;flex-direction:column;max-height:calc(100vh - 180px)}
.list-header{padding:.75rem 1rem;font-weight:600;font-size:.875rem;color:#334155;display:flex;align-items:center;justify-content:space-between;cursor:pointer}
.list-cards{padding:.5rem;flex:1;overflow-y:auto;min-height:40px}
.list-cards.drag-over{background:rgba(99,102,241,.08);border-radius:8px}
.kanban-card{background:#fff;border:1px solid rgba(226,232,240,.92);border-radius:12px;padding:1rem;margin-bottom:.65rem;box-shadow:0 1px 3px rgba(15,23,42,.08);cursor:grab;transition:box-shadow .2s,transform .2s,border-color .2s;position:relative}
.kanban-card:hover{box-shadow:0 8px 22px rgba(15,23,42,.13);border-color:#10b981;transform:translateY(-1px)}
.kanban-card:hover .card-quick-btn{opacity:1}
.kanban-card.dragging{opacity:.5;transform:rotate(2deg)}
.kanban-card-title{font-size:1.05rem;line-height:1.38;font-weight:800;color:#1e293b;letter-spacing:0;margin-bottom:.8rem;padding-right:1.5rem}
.kanban-card-meta{font-size:.875rem;line-height:1.25}
.kanban-card-label{height:.6rem;width:3.25rem;border-radius:999px}
.kanban-card-avatar{width:2rem;height:2rem}
.trello-card-modal{font-size:.95rem;line-height:1.5}
.trello-card-modal .card-detail-title{font-size:1.45rem!important;line-height:1.28!important;padding-top:.35rem!important;padding-bottom:.35rem!important}
.trello-card-modal .text-xs{font-size:.875rem!important;line-height:1.45!important}
.trello-card-modal .text-\[10px\]{font-size:.75rem!important;line-height:1.35!important}
.trello-card-modal .text-\[11px\]{font-size:.8125rem!important;line-height:1.4!important}
.trello-card-modal textarea,.trello-card-modal input,.trello-card-modal select{font-size:.9375rem!important}
.trello-card-modal button{font-size:.875rem}
.trello-card-modal .prose{font-size:.9375rem;line-height:1.6}
.trello-card-modal .modal-action-btn{padding:.75rem .85rem!important;font-weight:750!important}
.add-list-btn{flex-shrink:0;width:272px;background:rgba(255,255,255,.25);border-radius:12px;padding:1rem;cursor:pointer;color:#64748b;font-weight:500;font-size:.875rem;display:flex;align-items:center;gap:.5rem;transition:background .2s;align-self:flex-start}
.add-list-btn:hover{background:rgba(255,255,255,.45);color:#334155}
.priority-urgent{border-left:3px solid #ef4444}
.priority-high{border-left:3px solid #f97316}
.priority-medium{border-left:3px solid #6366f1}
.priority-low{border-left:3px solid #94a3b8}
/* Card quick-action button */
.card-quick-btn{position:absolute;top:6px;right:6px;opacity:0;transition:opacity .15s;background:rgba(255,255,255,.9);border:1px solid #e2e8f0;border-radius:6px;width:24px;height:24px;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:10;box-shadow:0 1px 4px rgba(0,0,0,.12)}
.card-quick-btn:hover{background:#f8fafc;border-color:#c7d2dd}
/* Context menu */
#card-ctx-menu{position:fixed;z-index:9999;min-width:188px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.18),0 2px 8px rgba(0,0,0,.08);padding:6px;user-select:none;transition:opacity .1s,transform .1s}
#card-ctx-menu.hidden{display:none}
.ctx-item{display:flex;align-items:center;gap:9px;padding:7px 10px;border-radius:8px;font-size:.75rem;font-weight:500;color:#334155;cursor:pointer;transition:background .12s,color .12s;white-space:nowrap}
	.ctx-item:hover{background:#f1f5f9;color:#1e293b}
	.ctx-item.ctx-danger{color:#dc2626}
	.ctx-item.ctx-danger:hover{background:#fef2f2;color:#b91c1c}
	.ctx-sep{height:1px;background:#f1f5f9;margin:4px 0}
	.board-menu-row{display:flex;width:100%;align-items:center;gap:.75rem;border-radius:.85rem;padding:.7rem .75rem;text-align:left;font-size:.875rem;font-weight:800;color:#334155;transition:background .16s ease,color .16s ease,transform .16s ease,box-shadow .16s ease}
	.board-menu-row:hover{background:linear-gradient(135deg,#eff6ff,#f8fafc);color:#1d4ed8;transform:translateX(2px);box-shadow:0 10px 24px rgba(47,104,237,.08)}
	.board-menu-row.text-rose-600{color:#dc2626}
	.board-menu-icon{display:flex;height:1.9rem;width:1.9rem;flex-shrink:0;align-items:center;justify-content:center;border-radius:.65rem;background:#f1f5f9;color:#64748b;font-size:.7rem;font-weight:900;transition:background .16s ease,color .16s ease,transform .16s ease}
	.board-menu-icon svg{height:1rem;width:1rem}
	.board-menu-row:hover .board-menu-icon{background:#dbeafe;color:#2F68ED;transform:scale(1.04)}

	[data-theme="dark"] .board-menu-row {
		color: #f1f5f9 !important;
	}
	[data-theme="dark"] .board-menu-row:hover {
		background: rgba(255, 255, 255, 0.07) !important;
		color: #38bdf8 !important;
	}
	[data-theme="dark"] .board-menu-row.text-rose-600 {
		color: #fca5a5 !important;
	}
	[data-theme="dark"] .board-menu-row.text-rose-600:hover {
		background: rgba(244, 63, 94, 0.15) !important;
		color: #fda4af !important;
	}
	[data-theme="dark"] .board-menu-icon {
		background: #272a34 !important;
		color: #94a3b8 !important;
	}
	[data-theme="dark"] .board-menu-row:hover .board-menu-icon {
		background: rgba(56, 189, 248, 0.2) !important;
		color: #38bdf8 !important;
	}

	/* ── Mobile Board Fixes ── */
	@media (max-width: 1023px) {
		.board-wrap {
			min-height: calc(100dvh - 56px - 60px - env(safe-area-inset-bottom,0px) - 8rem);
			padding: .75rem .5rem;
			gap: .625rem;
		}
		.board-list {
			width: 240px;
			max-height: calc(100dvh - 56px - 60px - env(safe-area-inset-bottom,0px) - 10rem);
		}
		/* Board header: wrap on mobile */
		.board-header-mobile { flex-wrap: wrap; gap: .5rem; }
		/* Member avatars: fewer overlap */
		.board-member-stack img { width: 1.75rem; height: 1.75rem; }
		/* kanban cards: slightly smaller text */
		.kanban-card-title { font-size: .95rem; }
	}
	@media (max-width: 480px) {
		.board-list { width: 220px; }
		.board-wrap { padding: .5rem .375rem; gap: .5rem; }
	}

	/* Zoom Control Styling */
	.zoom-container {
		background-color: #ffffff;
		border-color: #e2e8f0;
	}
	.zoom-label {
		color: #64748b;
	}
	.zoom-pill {
		background-color: #ffffff;
		border-color: #e2e8f0;
		color: #334155;
	}
	.zoom-btn {
		color: #334155;
	}
	.zoom-btn:hover {
		background-color: #f1f5f9;
	}
	.zoom-reset {
		color: #4f46e5;
	}
	.zoom-reset:hover {
		color: #4338ca;
	}

	/* Dark mode overrides using [data-theme="dark"] */
	[data-theme="dark"] .zoom-container {
		background-color: #1e293b;
		border-color: #334155;
	}
	[data-theme="dark"] .zoom-label {
		color: #94a3b8;
	}
	[data-theme="dark"] .zoom-pill {
		background-color: #000000;
		border-color: #1e293b;
		color: #ffffff;
	}
	[data-theme="dark"] .zoom-btn {
		color: #ffffff;
	}
	[data-theme="dark"] .zoom-btn:hover {
		background-color: rgba(255, 255, 255, 0.15);
	}
	[data-theme="dark"] .zoom-reset {
		color: #818cf8;
	}
	[data-theme="dark"] .zoom-reset:hover {
		color: #a5b4fc;
	}
	</style>
	@endpush

@section('content')
{{-- Board takes full width – no max-width constraint --}}
<div x-data='trelloBoard(@json($boardData))' x-init="init()" x-cloak>

{{-- ── Board header ────────────── --}}
<div class="relative z-30 flex items-center justify-between gap-2 sm:gap-3 mb-4 flex-nowrap lg:flex-nowrap bg-white/65 backdrop-blur-md p-2.5 sm:p-3.5 rounded-xl sm:rounded-2xl border border-slate-200/60 shadow-sm board-header-mobile">
  <div class="relative flex items-center gap-2">
    <div>
      <nav class="hidden sm:block text-xs text-slate-400 mb-0.5">
        <a href="{{ route('boards.workspaces') }}" class="hover:text-indigo-600 font-medium">Workspaces</a>
        <span class="mx-1 text-slate-300">›</span>
        <span class="font-medium text-slate-500" x-text="sbmBoardWorkspaceName(board)">{{ $board->workspace->name }}</span>
      </nav>
      <div class="relative flex items-center gap-2">
        <h1 class="font-display font-black text-slate-800 text-base sm:text-lg cursor-pointer hover:text-indigo-600 flex items-center gap-1 sm:gap-1.5 transition-colors select-none" @click="openSwitchBoardsModal()">
          <span x-text="board.name">{{ $board->name }}</span>
          <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-slate-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
          </svg>
        </h1>
        
        <!-- Star Toggle -->
        <button @click="toggleStar()" class="p-1 rounded-lg hover:bg-slate-100 transition-colors flex items-center justify-center text-base sm:text-lg select-none"
                :class="board.is_starred ? 'text-amber-500' : 'text-slate-300 hover:text-slate-400'">
            <span x-text="board.is_starred ? '★' : '☆'"></span>
        </button>
      </div>
    </div>
  </div>

  <div class="hidden lg:flex flex-1 justify-start pl-4 lg:pl-6">
    <button type="button"
            @click="openSwitchBoardsModal()"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-extrabold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-indigo-700">
      <svg class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6Zm4.5 3h7.5m-7.5 6h7.5" />
      </svg>
      Switch board
    </button>
  </div>

  <div class="ml-auto flex flex-shrink-0 items-center gap-1.5 sm:gap-3">
    {{-- Zoom Control --}}
    <div class="zoom-container hidden md:flex items-center gap-2 mr-1 rounded-full px-3 py-1.5 border shadow-sm">
        <span class="zoom-label text-[10px] font-extrabold uppercase tracking-widest pl-1 mr-1">Zoom</span>
        <div class="zoom-pill flex items-center gap-1.5 text-xs font-bold rounded-full px-2 py-1 shadow-sm border">
            <button @click="zoomOut()" class="zoom-btn w-5 h-5 flex items-center justify-center rounded-full transition-colors" :class="{'opacity-40 cursor-not-allowed': zoomLevel <= 33}">−</button>
            <span class="w-10 text-center" x-text="zoomLevel + '%'"></span>
            <button @click="zoomIn()" class="zoom-btn w-5 h-5 flex items-center justify-center rounded-full transition-colors" :class="{'opacity-40 cursor-not-allowed': zoomLevel >= 150}">+</button>
        </div>
        <button x-show="zoomLevel !== 100" @click="setZoom(100)" x-cloak class="zoom-reset text-[11px] font-bold px-1 transition-colors">Reset</button>
    </div>

    {{-- Board Members Stack --}}
    <div class="flex items-center -space-x-1.5 sm:-space-x-2 mr-0.5 sm:mr-1">
      @foreach($board->members as $bm)
        <img src="{{ $bm->avatar_url }}" alt="{{ $bm->name }}" title="{{ $bm->name }}"
             class="w-6 h-6 sm:w-7 sm:h-7 rounded-full object-cover border-2 border-white shadow-sm ring-1 ring-slate-100">
      @endforeach
    </div>

    {{-- Manage Board Members Dropdown (Only for Board Admins/Managers) --}}
    @if(auth()->user()->hasRole('super-admin') || $board->created_by === auth()->id() || auth()->user()->isSupervisorRole() || (auth()->user()->hasRole('sales-crm') && $board->workspace->name === 'CRM Team'))
      <div class="relative" x-data="{ openMembers: false, search: '' }">
        <button @click="openMembers = !openMembers; search = ''" class="btn btn-secondary py-1 sm:py-1.5 px-2 sm:px-3 text-[10px] sm:text-xs flex items-center gap-1 sm:gap-1.5 font-semibold">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
          </svg>
          <span class="hidden sm:inline">Members</span>
        </button>
        <div x-show="openMembers" @click.outside="openMembers = false" x-cloak
             class="absolute right-0 mt-2 w-64 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 p-4"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
          <p class="text-[10px] uppercase font-black text-slate-400 pb-2 border-b border-slate-100 mb-2">Manage Board Members</p>
          
          <input type="text" x-model="search" placeholder="Search members..." class="w-full text-xs bg-slate-50 border-slate-200 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-lg py-1.5 px-2.5 mb-3" @click.stop>
          
          <div class="max-h-48 overflow-y-auto space-y-2.5 mb-2 pr-1 scrollbar-thin">
            @php
              // Fetch workspace members to display in toggle list
              $possibleUsers = $board->workspace->members;
              
              // Always add dynamic digital team members (and boss/supervisor) so they are available to add to any board
              $digitalMembers = \App\Models\User::active()->get()->filter(fn($u) => $u->hasAnyRole(['digital-team', 'admin-digital', 'admin', 'boss', 'supervisor', 'staff']) && !$possibleUsers->contains($u->id));
              
              $possibleUsers = $possibleUsers->concat($digitalMembers)->sortBy(function($u) use ($board) {
                  return ($board->hasMember($u->id) ? '0_' : '1_') . strtolower($u->name);
              })->values();
            @endphp
            @foreach($possibleUsers as $u)
              <div x-show="search === '' || '{{ strtolower(addslashes($u->name)) }}'.includes(search.toLowerCase())" class="flex items-center justify-between gap-2 text-xs">
                <div class="flex items-center gap-1.5">
                  <img src="{{ $u->avatar_url }}" class="w-6.5 h-6.5 rounded-full object-cover border border-slate-200">
                  <span class="font-semibold text-slate-700 truncate max-w-28" title="{{ $u->name }}">{{ $u->name }}</span>
                </div>
                @if($board->hasMember($u->id))
                  @if($board->created_by !== $u->id)
                    <button @click="removeBoardMember({{ $u->id }}, $el)" class="text-[10px] text-rose-500 font-bold hover:underline">Remove</button>
                  @else
                    <span class="text-[9px] text-slate-400 font-bold italic">Owner</span>
                  @endif
                @else
                  <button @click="addBoardMember({{ $u->id }}, $el)" class="text-[10px] text-indigo-600 font-bold hover:underline">Add</button>
                @endif
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @endif

    {{-- Import Button --}}
    <button @click="openImportModal()"
            class="btn btn-secondary py-1 sm:py-1.5 px-2 sm:px-3 text-[10px] sm:text-xs flex items-center gap-1 sm:gap-1.5 font-semibold hover:text-white transition-colors"
            title="Import cards from CSV or Google Sheets">
      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
      </svg>
      <span class="hidden sm:inline">Import</span>
    </button>

    {{-- Board menu --}}
    <button @click="openBoardMenu('menu')" class="board-menu-btn btn btn-secondary btn-icon w-8 h-8 sm:w-9 sm:h-9" title="Board menu" aria-label="Open board menu">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
      </svg>
    </button>
  </div>
</div>

{{-- ── Lists row ─────────────────────────────────────────────────────── --}}
<div class="board-wrap" id="board-wrap" :style="sbmBoardPreviewStyle(board)">

  <template x-for="(list, li) in lists" :key="list.id">
    <div class="board-list" :id="'list-'+list.id" :style="'zoom: ' + (zoomLevel / 100)">

      {{-- List header --}}
      <div class="list-header flex items-center justify-between px-3.5 py-2.5 border-b border-slate-200/50 bg-slate-50/50 rounded-t-xl" :style="list.color ? 'border-top:3px solid '+list.color : ''">
        <div class="flex-1 min-w-0 pr-2">
          <!-- Normal view -->
          <div x-show="editingListId !== list.id" @click="startEditList(list.id, list.name)" class="cursor-pointer group flex items-center gap-1">
            <span class="font-extrabold text-slate-700 text-sm truncate" x-text="list.name"></span>
            <span class="opacity-0 group-hover:opacity-100 text-[10px] text-indigo-500 font-bold transition-opacity">edit</span>
          </div>
          <!-- Edit input -->
          <div x-show="editingListId === list.id" x-cloak>
            <input type="text" x-model="editingListName"
                   @blur="saveListName(list.id)"
                   @keydown.enter="saveListName(list.id)"
                   @keydown.escape="editingListId = null"
                   class="form-input py-0.5 px-1.5 text-xs font-semibold text-slate-700 w-full rounded-lg"
                   :id="'list-input-'+list.id">
          </div>
        </div>
        
        <div class="flex items-center gap-1.5">
          <span class="text-[10px] text-slate-400 font-bold" x-text="filteredCards(list).length"></span>
          
          <!-- Dropdown menu -->
          <div x-data="{ openMenu: false }" class="relative">
            <button @click="openMenu = !openMenu" class="text-slate-400 hover:text-slate-600 focus:outline-none p-1 rounded hover:bg-slate-200/50 flex items-center">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
              </svg>
            </button>
            <div x-show="openMenu" @click.outside="openMenu = false" x-cloak
                 class="absolute right-0 mt-1 w-36 bg-white border border-slate-200 rounded-xl shadow-2xl z-50 py-1.5"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
              <button @click="openMenu = false; startEditList(list.id, list.name)" class="w-full text-left px-3.5 py-2 text-xs text-slate-600 hover:bg-slate-50 flex items-center gap-1.5 font-medium">
                ✏️ Rename
              </button>
              <button @click="openMenu = false; archiveList(list.id)" class="w-full text-left px-3.5 py-2 text-xs text-amber-600 hover:bg-amber-50 flex items-center gap-1.5 font-medium">
                📦 Archive
              </button>
              <button @click="openMenu = false; deleteList(list.id)" class="w-full text-left px-3.5 py-2 text-xs text-rose-600 hover:bg-rose-50 flex items-center gap-1.5 font-medium">
                🗑️ Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      {{-- Cards Container with SortableJS hook --}}
      <div class="list-cards flex-1 overflow-y-auto min-h-12 pb-8 scrollbar-thin transition-colors" :id="'cards-'+list.id" :data-list-id="list.id">
        <template x-for="card in filteredCards(list)" :key="card.id">
          <div class="kanban-card select-none cursor-grab active:cursor-grabbing transform transition-all duration-150"
               :class="'priority-' + card.priority"
               :data-id="card.id"
               @click="openCard(card.id)"
               @contextmenu.prevent="openCtxMenu($event, card, list)"
               @touchstart="ctxTouchStart($event, card, list)"
               @touchend="ctxTouchEnd()"
               @touchmove="ctxTouchEnd()">

            {{-- Quick-action ⋮ button (hover-visible) --}}
            <button class="card-quick-btn"
                    @click.stop="openCtxMenu($event, card, list)"
                    title="Quick actions">
              <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor" class="text-slate-500">
                <circle cx="10" cy="4" r="1.8"/><circle cx="10" cy="10" r="1.8"/><circle cx="10" cy="16" r="1.8"/>
              </svg>
            </button>

            {{-- Labels --}}
            <div x-show="card.labels && card.labels.length" class="flex flex-wrap gap-1 mb-2.5">
              <template x-for="lbl in card.labels" :key="lbl.id">
                <span class="kanban-card-label inline-flex items-center"
                      :style="'background:'+lbl.color" :title="lbl.name"></span>
              </template>
            </div>

            {{-- Title --}}
            <div class="flex items-start justify-between gap-2 mb-2">
              <p class="kanban-card-title !mb-0 !pr-0"
                 x-text="card.title"></p>
              <svg x-show="list.name.toLowerCase().includes('approved') || card.status === 'Approved'" 
                   class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5 drop-shadow-sm" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
              </svg>
            </div>

            {{-- Meta row --}}
            <div class="flex items-center gap-2 flex-wrap">
              {{-- Due date --}}
              <span x-show="card.due_at"
                    :class="isOverdue(card) ? 'bg-red-100 text-red-600' : 'bg-slate-100 text-slate-500'"
                    class="kanban-card-meta font-bold px-2 py-1 rounded-lg"
                    x-text="formatDate(card.due_at)"></span>

              {{-- Checklist --}}
              <span x-show="card.checklist_total > 0"
                    class="kanban-card-meta text-slate-500 font-bold flex items-center gap-1">
                ✓ <span x-text="card.checklist_done+'/'+card.checklist_total"></span>
              </span>

              {{-- Files --}}
              <span x-show="card.has_files" class="kanban-card-meta text-slate-500">📎</span>

              {{-- Comments --}}
              <span x-show="card.comment_count > 0"
                    class="kanban-card-meta text-slate-500 font-bold flex items-center gap-1">
                💬 <span x-text="card.comment_count"></span>
              </span>

              {{-- Assignees --}}
              <div class="ml-auto flex -space-x-1.5">
                <template x-for="u in card.assignees.slice(0,3)" :key="u.id">
                  <span>
                    <template x-if="avatarUrl(u)">
                      <img :src="avatarUrl(u)" :alt="u.name" :title="u.name"
                           class="kanban-card-avatar rounded-full border-2 border-white ring-1 ring-slate-100 object-cover">
                    </template>
                    <template x-if="!avatarUrl(u)">
                      <span class="kanban-card-avatar rounded-full border-2 border-white ring-1 ring-slate-100 flex items-center justify-center text-[11px] font-black text-white"
                            :style="avatarStyle(u)"
                            x-text="avatarInitials(u)"
                            :title="u.name"></span>
                    </template>
                  </span>
                </template>
              </div>
            </div>
          </div>
        </template>
      </div>

      {{-- Add card button --}}
      <div class="px-2 pb-2">
        <div x-show="addingCardListId !== list.id">
          <button @click="startAddCard(list.id)"
                  class="w-full text-left text-xs text-slate-500 hover:text-indigo-600 hover:bg-white/60 px-3 py-2 rounded-lg transition-colors flex items-center gap-1.5 font-medium">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Add a card
          </button>
        </div>

        {{-- Inline add card form --}}
        <div x-show="addingCardListId === list.id" x-cloak>
          <textarea x-model="newCardTitle" @keydown.enter.prevent="saveCard(list.id)"
                    @keydown.escape="addingCardListId = null"
                    rows="2" placeholder="Card title…"
                    class="form-input text-xs resize-none w-full mb-2 rounded-xl"
                    x-ref="'newcard_'+list.id"
                    :x-ref="'newcard_'+list.id"></textarea>
          <div class="flex gap-2">
            <button @click="saveCard(list.id)" class="btn btn-primary text-xs py-1.5 px-3">Add</button>
            <button @click="addingCardListId = null" class="text-xs text-slate-400 hover:text-slate-600">✕</button>
          </div>
        </div>
      </div>
    </div>
  </template>

  {{-- Add list button --}}
  <div :style="'zoom: ' + (zoomLevel / 100)">
    <div x-show="!addingList" class="add-list-btn border border-dashed border-slate-300 rounded-xl hover:border-slate-400 transition-colors" @click="addingList=true">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      Add another list
    </div>
    <div x-show="addingList" x-cloak class="board-list p-3 border border-slate-200 shadow-sm">
      <input x-model="newListName" type="text" placeholder="List name…"
             @keydown.enter="saveList" @keydown.escape="addingList=false"
             class="form-input text-xs mb-2 rounded-xl" autofocus>
      <div class="flex gap-2">
        <button @click="saveList" class="btn btn-primary text-xs py-1.5 px-3">Add List</button>
        <button @click="addingList=false" class="text-xs text-slate-400 hover:text-slate-600 font-semibold">✕</button>
      </div>
    </div>
</div>

</div>



{{-- ── Board Activity Feed Side Drawer ────────────────────────────────── --}}
<div x-show="activityOpen" class="fixed inset-0 z-50 overflow-hidden" x-cloak>
  <!-- Backdrop overlay -->
  <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" 
       @click="activityOpen = false"
       x-show="activityOpen"
       x-transition:enter="ease-out duration-300"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="ease-in duration-200"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"></div>

  <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
    <div class="w-screen max-w-md"
         x-show="activityOpen"
         x-transition:enter="transform transition ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">
      <div class="h-full flex flex-col bg-white shadow-2xl border-l border-slate-200">
        <!-- Drawer Header -->
        <div class="p-6 bg-slate-50 border-b border-slate-200/60 flex items-center justify-between">
          <h2 class="text-sm font-black text-slate-800 flex items-center gap-2">
            <span>📜 Board Activity Log</span>
          </h2>
          <button @click="activityOpen = false" class="text-slate-400 hover:text-slate-600 transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <!-- Drawer Feed List -->
        <div class="flex-1 overflow-y-auto p-6 space-y-4.5 scrollbar-thin select-none">
          <template x-for="act in activities" :key="act.id">
            <div class="flex gap-3 text-xs leading-normal">
              <img :src="act.user_avatar || window.dgtInitialsAvatar(act.user_name || 'System', act.user_avatar_color || '#64748b')" class="w-8 h-8 rounded-full object-cover border border-slate-200 flex-shrink-0 mt-0.5">
              <div class="flex-1">
                <p class="text-slate-700">
                  <strong class="font-bold text-slate-800" x-text="act.user_name"></strong> 
                  <span x-html="parseMarkdown(act.description || '')"></span>
                </p>
                <span class="text-[9px] text-slate-400 font-bold block mt-1" x-text="act.time_ago"></span>
              </div>
            </div>
          </template>
          <template x-if="activities.length === 0">
            <div class="py-12 text-center text-slate-400 font-semibold">
              🌱 No activities recorded on this board yet.
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</div>

@include('boards.partials.board-menu')

{{-- ── Card Context Menu ─────────────────────────────────────────────── --}}
{{-- Rendered once; positioned via JS --}}
<div id="card-ctx-menu" class="hidden" @click.outside="closeCtxMenu()">

  {{-- Open card --}}
  <div class="ctx-item" @click="ctxAction('open')">
    <svg class="w-3.5 h-3.5 text-indigo-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
    Open card
  </div>

  <div class="ctx-sep"></div>

  {{-- Edit labels --}}
  <div class="ctx-item" @click="ctxAction('labels')">
    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
    Edit labels
  </div>

  {{-- Change members --}}
  <div class="ctx-item" @click="ctxAction('members')">
    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
    Change members
  </div>

  {{-- Change cover --}}
  <div class="ctx-item" x-show="board.card_covers_enabled !== false" @click="ctxAction('cover')">
    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
    Change cover
  </div>

  {{-- Edit dates --}}
  <div class="ctx-item" @click="ctxAction('dates')">
    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
    Edit dates
  </div>

  <div class="ctx-sep"></div>

  {{-- Move --}}
  <div class="ctx-item" @click="ctxAction('move')">
    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
    Move
  </div>

  {{-- Copy card --}}
  <div class="ctx-item" @click="ctxAction('copy')">
    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/></svg>
    Copy card
  </div>

  {{-- Copy link --}}
  <div class="ctx-item" @click="ctxAction('link')">
    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
    Copy link
  </div>

  <div class="ctx-sep"></div>

  {{-- Archive --}}
  <div class="ctx-item" @click="ctxAction('archive')">
    <svg class="w-3.5 h-3.5 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
    Archive
  </div>

  {{-- Delete: admin only --}}
  @if(auth()->user()->hasAnyRole(['super-admin', 'admin']))
  <div class="ctx-item ctx-danger" @click="ctxAction('delete')">
    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
    Delete card
  </div>
  @endif

</div>

{{-- ── Card Detail Modal ─────────────────────────────────────────────── --}}
@include('boards.partials.card-modal')

{{-- ── Trello-style Date Picker Modal ───────────────────────────────── --}}
@include('boards.partials.date-picker-modal')

{{-- ── Trello-style Switch Boards Modal ─────────────────────────────── --}}
@include('boards.partials.switch-boards-modal')

{{-- ── Move / Copy Card Destination Modal ───────────────────────────── --}}
@include('boards.partials.card-transfer-modal')

{{-- ── Trello-style Member Picker Modal ─────────────────────────────── --}}
@include('boards.partials.member-picker-modal')

{{-- ── Trello-style Attachment Modal ────────────────────────────────── --}}
@include('boards.partials.attachment-modal')
@include('boards.partials.export-modal')
@include('boards.partials.import-modal')




</div>
@endsection

@push('scripts')
<!-- Quill JS -->
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
@php
    $trelloBoardScript = public_path('js/trello-board.js');
    $trelloBoardVersion = file_exists($trelloBoardScript) ? filemtime($trelloBoardScript) : time();
@endphp
<script src="{{ asset('js/trello-board.js') }}?v={{ $trelloBoardVersion }}"></script>
@endpush
