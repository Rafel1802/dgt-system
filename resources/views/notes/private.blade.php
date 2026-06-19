@extends('layouts.app')
@section('title', 'Private Notes')

@push('styles')
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
    .notes-shell {
        height: calc(100vh - 8rem);
        min-height: 560px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        box-shadow: 0 20px 60px rgba(15, 23, 42, .08);
    }
    .notes-list-scroll,
    .notes-editor-scroll { min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
    .notes-list-scroll::-webkit-scrollbar,
    .notes-editor-scroll::-webkit-scrollbar { width: 8px; }
    .notes-list-scroll::-webkit-scrollbar-thumb,
    .notes-editor-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; border: 2px solid transparent; background-clip: padding-box; }
    .notes-editor-card .ql-toolbar.ql-snow {
        flex-shrink: 0;
        z-index: 20;
        border: none;
        border-bottom: 1px solid #e2e8f0;
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        padding: 10px 24px;
        min-height: 48px;
    }
    .notes-editor-card .ql-toolbar.ql-snow .ql-formats { margin-right: 10px; }
    .notes-editor-card .ql-toolbar.ql-snow button,
    .notes-editor-card .ql-toolbar.ql-snow .ql-picker-label {
        border-radius: 8px;
        color: #475569;
        transition: background .15s ease, color .15s ease;
    }
    .notes-editor-card .ql-toolbar.ql-snow button:hover,
    .notes-editor-card .ql-toolbar.ql-snow button.ql-active,
    .notes-editor-card .ql-toolbar.ql-snow .ql-picker-label:hover,
    .notes-editor-card .ql-toolbar.ql-snow .ql-picker.ql-expanded .ql-picker-label {
        background: #fef3c7;
        color: #854d0e;
    }
    .notes-editor-card .ql-container.ql-snow {
        border: none;
        display: flex;
        flex: 1 1 auto;
        min-height: 0;
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Inter", system-ui, sans-serif;
        font-size: 1rem;
    }
    .notes-editor-card .ql-editor {
        min-height: clamp(420px, calc(100vh - 250px), 980px);
        padding: 22px 40px 56px;
        color: #1e293b;
        line-height: 1.65;
        caret-color: #d97706;
        overflow-y: visible;
    }
    .notes-editor-card .ql-editor.ql-blank::before {
        color: #94a3b8;
        font-style: normal;
        left: 40px;
        right: 40px;
    }
    .notes-editor-card .ql-editor p { margin-bottom: 0.75em; }
    .notes-editor-card .ql-editor h1,
    .notes-editor-card .ql-editor h2,
    .notes-editor-card .ql-editor h3 { color: #0f172a; margin: 1.1em 0 .45em; font-weight: 800; }
    .notes-editor-card #editor-container { display: flex; flex: 1 1 auto; min-height: 0; flex-direction: column; outline: none; }
    .notes-editor-card .ql-picker-options { z-index: 50; border-color: #e2e8f0 !important; border-radius: 10px; box-shadow: 0 18px 40px rgba(15, 23, 42, .14); }
    .notes-editor-card .ql-tooltip { z-index: 60; border-radius: 12px; box-shadow: 0 18px 40px rgba(15, 23, 42, .14); }
    .notes-editor-card .ql-toolbar svg,
    .notes-editor-card .ql-container svg { width: 18px !important; height: 18px !important; display: inline-block; }
    .notes-title-input:focus { outline: none; box-shadow: none; }
    [x-cloak] { display: none !important; }

    /* ── Mobile Notes Layout ── */
    @media (max-width: 1023px) {
        .notes-shell {
            height: calc(100dvh - 56px - 60px - env(safe-area-inset-bottom, 0px) - 1rem);
            min-height: 0;
            border-radius: 12px;
            overflow: hidden;
            flex-direction: column;
        }
        .notes-shell .notes-col-folder,
        .notes-shell .notes-col-list,
        .notes-shell .notes-col-editor {
            position: absolute;
            inset: 0;
            width: 100% !important;
            height: 100% !important;
            flex-shrink: 0;
            transition: transform 0.32s cubic-bezier(0.4,0,0.2,1), opacity 0.28s ease;
            will-change: transform;
        }
        .notes-shell { position: relative; }
        .notes-shell[data-mobile-panel="0"] .notes-col-folder { transform: translateX(0); opacity: 1; pointer-events: auto; z-index: 3; }
        .notes-shell[data-mobile-panel="0"] .notes-col-list   { transform: translateX(100%); opacity: 0; pointer-events: none; z-index: 2; }
        .notes-shell[data-mobile-panel="0"] .notes-col-editor { transform: translateX(100%); opacity: 0; pointer-events: none; z-index: 1; }
        .notes-shell[data-mobile-panel="1"] .notes-col-folder { transform: translateX(-100%); opacity: 0; pointer-events: none; z-index: 1; }
        .notes-shell[data-mobile-panel="1"] .notes-col-list   { transform: translateX(0); opacity: 1; pointer-events: auto; z-index: 3; }
        .notes-shell[data-mobile-panel="1"] .notes-col-editor { transform: translateX(100%); opacity: 0; pointer-events: none; z-index: 2; }
        .notes-shell[data-mobile-panel="2"] .notes-col-folder { transform: translateX(-100%); opacity: 0; pointer-events: none; z-index: 1; }
        .notes-shell[data-mobile-panel="2"] .notes-col-list   { transform: translateX(-100%); opacity: 0; pointer-events: none; z-index: 2; }
        .notes-shell[data-mobile-panel="2"] .notes-col-editor { transform: translateX(0); opacity: 1; pointer-events: auto; z-index: 3; }
        .notes-editor-card .ql-editor { padding: 16px 20px 80px; min-height: 300px; }
        .notes-editor-card .ql-editor.ql-blank::before { left: 20px; right: 20px; }
    }
</style>
@endpush

@section('content')
<div class="bg-slate-50 flex notes-shell font-sans"
     x-data="notesApp('private', null)"
     x-init="initApp()"
     :class="{ 'full-note-mode': fullNoteMode }"
     :data-mobile-panel="mobilePanel">

    <!-- ── Column 1: Folders Panel ── -->
    <div class="notes-col-folder w-64 bg-slate-100 border-r border-slate-200 flex flex-col flex-shrink-0"
         x-show="!fullNoteMode"
         x-transition:leave="transition-none">

        <!-- Mobile folder panel topbar -->
        <div class="lg:hidden flex items-center justify-between px-4 py-3 border-b border-slate-200 bg-white">
            <span class="text-sm font-black text-slate-700">Private Notes</span>
            <button @click="mobileGotoList()" class="flex items-center gap-1 text-xs font-bold text-yellow-600 hover:text-yellow-700">
                Notes
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
            </button>
        </div>

        <div class="p-4 pt-6">
            <h2 class="hidden lg:block text-xs font-black uppercase tracking-wider text-slate-400 mb-3 px-2">Private</h2>
            <nav class="space-y-1">
                <button @click="selectFolder(null); mobileGotoList()" :class="{'bg-yellow-400 text-yellow-900 font-semibold shadow-sm': !activeFolder && viewMode === 'notes', 'text-slate-600 hover:bg-slate-200/50': activeFolder || viewMode !== 'notes'}" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors text-left">
                    <svg class="w-5 h-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    All Notes
                </button>
                <button @click="openBin(); mobileGotoList()" :class="{'bg-slate-800 text-white font-semibold shadow-sm': viewMode === 'bin', 'text-slate-600 hover:bg-slate-200/50': viewMode !== 'bin'}" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors text-left">
                    <svg class="w-5 h-5 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m20.25 7.5-.625 10.632A2.25 2.25 0 0 1 17.38 20.25H6.62a2.25 2.25 0 0 1-2.245-2.118L3.75 7.5M9.75 11.25v6m4.5-6v6M8.25 7.5V5.25A2.25 2.25 0 0 1 10.5 3h3a2.25 2.25 0 0 1 2.25 2.25V7.5M3 7.5h18" /></svg>
                    Note Bin
                </button>
            </nav>
            
            <h2 class="text-xs font-black uppercase tracking-wider text-slate-400 mt-8 mb-3 px-2 flex justify-between items-center">
                Folders
                <button @click="openFolderModal()" class="hover:text-slate-600" title="New Folder">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                </button>
            </h2>
            <nav class="space-y-1" x-show="folders.length > 0">
                <template x-for="folder in folders" :key="folder.id">
                    <div :class="{'bg-yellow-400 text-yellow-900 font-semibold shadow-sm': activeFolder == folder.id && viewMode === 'notes', 'text-slate-600 hover:bg-slate-200/50': activeFolder != folder.id || viewMode !== 'notes'}" class="group w-full flex items-center gap-1 px-3 py-2 rounded-lg text-sm transition-colors">
                        <button @click="selectFolder(folder.id); mobileGotoList()" class="min-w-0 flex flex-1 items-center gap-3 text-left">
                            <svg class="w-5 h-5 text-yellow-500 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" /></svg>
                            <span x-text="folder.name" class="truncate"></span>
                        </button>
                        <button @click.stop="openFolderModal(folder)" class="rounded-md p-1 opacity-0 transition group-hover:opacity-100 hover:bg-white/60" title="Rename folder">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L8.25 18.402 3.75 19.5l1.098-4.5L16.862 4.487Z"/></svg>
                        </button>
                        <button @click.stop="downloadFolder(folder)" class="rounded-md p-1 opacity-0 transition group-hover:opacity-100 hover:bg-white/60" title="Download folder ZIP">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 10.5 12 15m0 0 4.5-4.5M12 15V3"/></svg>
                        </button>
                        <button @click.stop="deleteFolder(folder)" class="rounded-md p-1 opacity-0 transition group-hover:opacity-100 hover:bg-red-100 hover:text-red-600" title="Delete folder">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </div>
                </template>
            </nav>
        </div>
    </div>

    <!-- ── Column 2: Note List Panel ── -->
    <div class="notes-col-list w-80 bg-white border-r border-slate-200 flex flex-col flex-shrink-0"
         x-show="!fullNoteMode"
         x-transition:leave="transition-none">

        <!-- Mobile note list topbar -->
        <div class="lg:hidden flex items-center justify-between px-3 py-2.5 border-b border-slate-200 bg-slate-50">
            <button @click="mobileGotoFolders()" class="flex items-center gap-1 text-xs font-bold text-slate-500 hover:text-slate-700">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                Folders
            </button>
            <span class="text-sm font-black text-slate-700" x-text="viewMode === 'bin' ? 'Note Bin' : 'Notes'"></span>
            <button @click="createNewNote(); mobileGotoEditor()" x-show="viewMode !== 'bin'" class="p-1 rounded-lg text-slate-500 hover:text-yellow-600 hover:bg-yellow-50 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
            </button>
        </div>

        <div class="hidden lg:flex p-4 border-b border-slate-100 items-center justify-between">
            <h1 class="text-xl font-bold text-slate-800" x-text="viewMode === 'bin' ? 'Note Bin' : 'Notes'"></h1>
            <button @click="createNewNote()" x-show="viewMode !== 'bin'" class="p-1.5 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" title="Compose New Note">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
            </button>
        </div>
        
        <div class="p-3">
            <div class="relative">
                <svg class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                <input type="text" x-model="searchQuery" @input.debounce.300ms="fetchNotes()" placeholder="Search notes" class="w-full pl-9 pr-3 py-2 bg-slate-100 border-transparent rounded-lg text-sm focus:bg-white focus:border-yellow-400 focus:ring-2 focus:ring-yellow-400/20 transition-all">
            </div>
            <div class="mt-2 flex items-center justify-between gap-2">
                <button @click="toggleSelectMode()" class="text-xs font-bold text-slate-500 hover:text-slate-900" x-text="isSelectMode ? 'Cancel' : 'Select'"></button>
                <div x-show="isSelectMode" x-cloak class="flex items-center gap-2 text-xs">
                    <button @click="selectAllVisible()" class="font-bold text-slate-500 hover:text-slate-900">All</button>
                    <button @click="clearSelection()" class="font-bold text-slate-400 hover:text-slate-700">Clear</button>
                </div>
            </div>
            <div x-show="selectedNoteIds.length > 0" x-cloak class="mt-3 rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
                <div class="mb-2 text-xs font-bold text-slate-500" x-text="`${selectedNoteIds.length} selected`"></div>
                <div class="flex flex-wrap gap-2">
                    <button @click="downloadSelected()" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-700">Download ZIP</button>
                    <button x-show="viewMode !== 'bin'" @click="bulkDelete()" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-bold text-red-600 hover:bg-red-100">Move to Bin</button>
                    <button x-show="viewMode === 'bin'" @click="restoreSelected()" class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100">Restore</button>
                    <button x-show="viewMode === 'bin'" @click="forceDeleteSelected()" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-red-700">Delete Forever</button>
                </div>
            </div>
        </div>

        <div class="flex-1 notes-list-scroll px-3 pb-4 space-y-1">
            <template x-if="loadingNotes">
                <div class="py-10 text-center text-sm text-slate-400">Loading...</div>
            </template>
            <template x-if="!loadingNotes && notes.length === 0">
                <div class="py-10 text-center text-sm text-slate-400" x-text="viewMode === 'bin' ? 'Note Bin is empty.' : 'No notes found.'"></div>
            </template>
            
            <template x-for="note in notes" :key="note.id">
                <div class="flex items-start gap-2">
                    <input x-show="isSelectMode" x-cloak type="checkbox" class="mt-4 rounded border-slate-300 text-yellow-500 focus:ring-yellow-400" :checked="isSelected(note.id)" @change="toggleNoteSelection(note.id)">
                    <button @click="isSelectMode ? toggleNoteSelection(note.id) : (selectNote(note), mobileGotoEditor())"
                            :class="{'bg-yellow-400 text-yellow-950 shadow-sm': activeNote && activeNote.id === note.id, 'hover:bg-slate-100 text-slate-800': !activeNote || activeNote.id !== note.id}"
                            class="min-w-0 flex-1 p-3 rounded-xl text-left transition-colors flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <div class="font-bold truncate" x-text="note.title || 'New Note'"></div>
                            <span x-show="viewMode === 'bin'" class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-black uppercase text-slate-500">Bin</span>
                        </div>
                        <div class="flex gap-2 items-baseline text-xs">
                            <span class="font-semibold whitespace-nowrap" :class="{'text-yellow-800': activeNote && activeNote.id === note.id, 'text-slate-500': !activeNote || activeNote.id !== note.id}" x-text="formatDate(note.updated_at)"></span>
                            <span class="truncate opacity-80" x-text="note.plain_text ? note.plain_text.substring(0, 40) : 'No additional text'"></span>
                        </div>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- ── Column 3: Editor Panel ── -->
    <div class="notes-col-editor notes-editor-card flex-1 bg-white flex flex-col min-w-0 relative">

        <!-- Editor Content (Always rendered so Quill initializes correctly) -->
        <div class="flex-1 flex flex-col relative h-full">
            <div class="h-14 flex items-center justify-between px-4 lg:px-6 border-b border-slate-100 shrink-0">
                <div class="flex items-center gap-3 text-xs font-semibold text-slate-400">
                    <!-- Mobile back to note list -->
                    <button class="lg:hidden flex items-center gap-1 text-slate-600 hover:text-slate-900 font-bold text-xs"
                            @click="mobileGotoList()">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                        Notes
                    </button>
                    <!-- Back button in full note mode -->
                    <button x-show="fullNoteMode" @click="exitFullNote()" x-cloak
                            class="hidden lg:flex items-center gap-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 px-3 py-1.5 rounded-lg transition-colors text-xs font-bold">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                        Back
                    </button>
                    <span x-show="!fullNoteMode" class="hidden lg:inline" x-text="formatFullDate(activeNote?.updated_at)"></span>
                    <span id="save-status" class="text-slate-400 select-none"></span>
                </div>
                <div class="flex items-center gap-2">
                    <!-- View Full Note button (desktop only) -->
                    <button x-show="activeNote && !fullNoteMode && viewMode !== 'bin'" @click="enterFullNote()" x-cloak
                            class="hidden lg:flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors" title="View Full Note">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/></svg>
                        Full Note
                    </button>
                    <button x-show="viewMode === 'bin' && activeNote" @click="restoreNote(activeNote)" class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" title="Restore Note">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14.25 4.5 9.75 9 5.25m-4.5 4.5h11.25a4.5 4.5 0 0 1 0 9H12" /></svg>
                    </button>
                    <button @click="deleteNote()" class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" :title="viewMode === 'bin' ? 'Delete Forever' : 'Delete Note'">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                </div>
            </div>

            <!-- Toolbar removed as per user request for plain text view -->

            <div class="flex-1 notes-editor-scroll relative flex flex-col" x-ref="editorScroll">
                <div class="px-5 lg:px-8 pt-6 lg:pt-8 shrink-0">
                    <input type="text" x-ref="titleInput" x-model="activeNoteTitle" @input="triggerSave()" :disabled="viewMode === 'bin'" placeholder="Title" class="notes-title-input w-full text-2xl lg:text-3xl font-bold border-none p-0 focus:ring-0 text-slate-800 placeholder-slate-300 bg-transparent disabled:text-slate-500" spellcheck="false" autocorrect="off" autocapitalize="off">
                </div>
                <div id="editor-container" class="flex-1" x-ignore data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false" spellcheck="false" autocorrect="off" autocapitalize="off"></div>
            </div>
        </div>

        <!-- Overlay when no note is selected (covers the editor) -->
        <div x-show="!activeNote" class="absolute inset-0 bg-white z-10 flex flex-col items-center justify-center text-slate-400 gap-3" x-transition.opacity>
            <div class="lg:hidden flex flex-col items-center gap-4">
                <svg class="w-14 h-14 opacity-15" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                <button @click="mobileGotoList()" class="flex items-center gap-2 px-4 py-2 bg-yellow-400 text-yellow-900 font-black text-sm rounded-xl shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                    Go to Notes
                </button>
            </div>
            <div class="hidden lg:flex flex-col items-center gap-3">
                <svg class="w-16 h-16 opacity-20" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                <p>Select a note or create a new one</p>
            </div>
        </div>
    </div>

    <!-- Beautiful Folder Modal -->
    <div x-show="showFolderModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm" x-cloak x-transition.opacity>
        <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm" @click.away="showFolderModal = false" x-transition>
            <h3 class="text-lg font-bold text-slate-800 mb-2" x-text="editingFolder ? 'Rename Folder' : 'New Folder'"></h3>
            <p class="text-sm text-slate-500 mb-4" x-text="editingFolder ? 'Update the folder name.' : 'Enter a name for this folder.'"></p>
            <input type="text" x-model="newFolderName" @keyup.enter="submitFolder()" placeholder="Folder name" class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 mb-5">
            <div class="flex justify-end gap-3">
                <button @click="showFolderModal = false" class="px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button @click="submitFolder()" :disabled="!newFolderName || isSavingFolder" class="px-4 py-2 text-sm font-semibold text-yellow-900 bg-yellow-400 hover:bg-yellow-500 rounded-lg disabled:opacity-50" x-text="editingFolder ? 'Save' : 'Create'"></button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
    document.addEventListener('alpine:init', () => {
        let quillInstance = null;
        let saveTimeoutTimer = null;

        Alpine.data('notesApp', (type, team) => ({
            type: type,
            team: team,
            folders: @json($folders ?? []),
            notes: [],
            activeFolder: null,
            activeNote: null,
            activeNoteTitle: '',
            searchQuery: '',
            viewMode: 'notes',
            isSelectMode: false,
            selectedNoteIds: [],
            loadingNotes: false,
            fullNoteMode: false,
            mobilePanel: 1, // 0=folders, 1=noteList, 2=editor
            showFolderModal: false,
            editingFolder: null,
            newFolderName: '',
            isSavingFolder: false,

            // Mobile panel navigation helpers
            mobileGotoFolders() { this.mobilePanel = 0; },
            mobileGotoList()    { this.mobilePanel = 1; },
            mobileGotoEditor()  { this.mobilePanel = 2; },

            initApp() {
                this.fetchNotes();

                this.$nextTick(() => this.initEditor());
            },

            initEditor() {
                if (quillInstance) return;

                if (typeof Quill === 'undefined') {
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: { msg: 'Note editor failed to load. Please refresh this page.', type: 'error' }
                    }));
                    return;
                }

                quillInstance = new Quill('#editor-container', {
                    theme: 'snow',
                    bounds: '.notes-editor-card',
                    placeholder: 'Start writing your note...',
                    modules: {
                        toolbar: false,
                        clipboard: {
                            matchVisual: false
                        }
                    }
                });

                // Apply spelling and autocorrect disables programmatically on Quill's root
                if (quillInstance.root) {
                    quillInstance.root.setAttribute('spellcheck', 'false');
                    quillInstance.root.setAttribute('autocorrect', 'off');
                    quillInstance.root.setAttribute('autocapitalize', 'off');
                    quillInstance.root.setAttribute('autocomplete', 'off');
                    quillInstance.root.setAttribute('smartquotes', 'off');
                    quillInstance.root.setAttribute('smartdashes', 'off');
                }

                // ─── HIGH-PERFORMANCE SELECTION TRACKING ───
                let savedRange = null;

                // Track selection ONLY via Quill's optimized native event
                quillInstance.on('selection-change', (range) => {
                    if (range) savedRange = range;
                });

                // ─── Cmd+A / Ctrl+A: Select only editor content, not whole page ───
                quillInstance.root.addEventListener('keydown', (e) => {
                    if ((e.metaKey || e.ctrlKey) && e.key === 'a') {
                        e.preventDefault();
                        const length = quillInstance.getLength();
                        const targetLength = length > 0 ? length - 1 : 0;
                        quillInstance.setSelection(0, targetLength);
                        savedRange = { index: 0, length: targetLength };
                    }
                });

                // ─── Paste support ───
                quillInstance.root.addEventListener('paste', () => {
                    this.triggerSave();
                }, { passive: true });

                // ─── Auto-save on text change ───
                quillInstance.on('text-change', (delta, oldDelta, source) => {
                    if (source === 'user') {
                        this.triggerSave();
                    }
                });
            },

            setEditorContent(content = '') {
                this.initEditor();
                if (!quillInstance) return;

                quillInstance.setContents(quillInstance.clipboard.convert(content || ''), 'silent');
                quillInstance.enable(this.viewMode !== 'bin');
                this.resetEditorScroll();
            },

            resetEditorScroll() {
                this.$nextTick(() => {
                    if (this.$refs.editorScroll) {
                        this.$refs.editorScroll.scrollTop = 0;
                    }
                });
            },

            focusEditorWithoutJump() {
                const windowX = window.scrollX;
                const windowY = window.scrollY;

                this.$nextTick(() => {
                    if (this.$refs.editorScroll) {
                        this.$refs.editorScroll.scrollTop = 0;
                    }

                    if (quillInstance?.root) {
                        quillInstance.root.focus({ preventScroll: true });
                    }

                    window.scrollTo(windowX, windowY);
                });
            },

            fetchNotes() {
                this.loadingNotes = true;
                let url = `/notes/api/fetch?type=${this.type}`;
                if (this.team) url += `&team=${this.team}`;
                if (this.activeFolder && this.viewMode !== 'bin') url += `&folder_id=${this.activeFolder}`;
                if (this.searchQuery) url += `&q=${encodeURIComponent(this.searchQuery)}`;
                if (this.viewMode === 'bin') url += '&trash=1';

                fetch(url)
                    .then(res => res.json())
                    .then(data => {
                        this.notes = data;
                        this.loadingNotes = false;
                        this.selectedNoteIds = this.selectedNoteIds.filter((id) => this.notes.some((note) => note.id === id));
                    });
            },

            enterFullNote() {
                this.fullNoteMode = true;
                this.$nextTick(() => {
                    if (quillInstance?.root) {
                        quillInstance.root.focus({ preventScroll: true });
                    }
                });
            },

            exitFullNote() {
                this.fullNoteMode = false;
            },

            selectFolder(folderId) {
                this.viewMode = 'notes';
                this.activeFolder = folderId;
                this.activeNote = null;
                this.clearSelection();
                if (quillInstance) {
                    quillInstance.setContents([], 'silent');
                    quillInstance.enable(true);
                }
                this.fetchNotes();
            },

            openBin() {
                this.viewMode = 'bin';
                this.activeFolder = null;
                this.activeNote = null;
                this.activeNoteTitle = '';
                this.clearSelection();
                if (quillInstance) {
                    quillInstance.setContents([], 'silent');
                    quillInstance.enable(false);
                }
                this.fetchNotes();
            },

            selectNote(note) {
                this.activeNote = note;
                this.activeNoteTitle = note.title;
                this.setEditorContent(note.content || '');

                this.$nextTick(() => {
                    const statusEl = document.getElementById('save-status');
                    if (statusEl) statusEl.innerHTML = '<span class="text-slate-400">Saved</span>';
                });
            },

            createNewNote() {
                if (this.viewMode === 'bin') return;

                this.activeNote = { id: 'temp', title: 'New Note', content: '' };
                this.activeNoteTitle = 'New Note';
                this.initEditor();
                if (quillInstance) {
                    quillInstance.setContents([], 'silent');
                }
                this.resetEditorScroll();

                this.$nextTick(() => {
                    const statusEl = document.getElementById('save-status');
                    if (statusEl) {
                        statusEl.innerHTML = '<span class="text-yellow-600 flex items-center gap-1"><svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Saving...</span>';
                    }
                });

                fetch('/notes/api/store', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        type: this.type,
                        team: this.team,
                        folder_id: this.activeFolder,
                        title: 'New Note',
                        content: ''
                    })
                })
                .then(res => res.json())
                .then(note => {
                    this.notes.unshift(note);
                    this.activeNote = note;
                    
                    const statusEl = document.getElementById('save-status');
                    if (statusEl) statusEl.innerHTML = '<span class="text-slate-400">Saved</span>';

                    this.focusEditorWithoutJump();
                });
            },

            openFolderModal(folder = null) {
                this.editingFolder = folder;
                this.newFolderName = folder ? folder.name : '';
                this.showFolderModal = true;
                setTimeout(() => document.querySelector('[x-model="newFolderName"]').focus(), 100);
            },

            submitFolder() {
                if (!this.newFolderName) return;
                this.isSavingFolder = true;

                const url = this.editingFolder ? `/notes/api/folders/${this.editingFolder.id}` : '/notes/api/folders';
                const method = this.editingFolder ? 'PUT' : 'POST';

                fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        type: this.type,
                        team: this.team,
                        name: this.newFolderName
                    })
                })
                .then(res => res.json())
                .then(folder => {
                    this.isSavingFolder = false;
                    if (folder.id) {
                        if (this.editingFolder) {
                            const idx = this.folders.findIndex((item) => item.id === folder.id);
                            if (idx !== -1) this.folders[idx] = folder;
                        } else {
                            this.folders.push(folder);
                        }
                        this.showFolderModal = false;
                        this.editingFolder = null;
                        this.selectFolder(folder.id);
                    }
                });
            },

            async deleteFolder(folder) {
                if (! await window.confirmModal({
                    title: 'Delete folder?',
                    message: `Delete "${folder.name}"? Notes inside will stay in All Notes.`,
                    confirmText: 'Delete folder',
                    tone: 'danger',
                })) return;

                await fetch(`/notes/api/folders/${folder.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                });

                this.folders = this.folders.filter((item) => item.id !== folder.id);
                if (this.activeFolder === folder.id) this.selectFolder(null);
            },

            downloadFolder(folder) {
                window.location.href = `/notes/api/folders/${folder.id}/download`;
            },

            triggerSave() {
                if (!this.activeNote || this.activeNote.id === 'temp' || this.viewMode === 'bin') return;

                const statusEl = document.getElementById('save-status');
                if (statusEl) statusEl.innerHTML = '<span class="text-slate-400/70">Unsaved changes</span>';
                
                // Update local list title instantly for snappy UI
                if (this.activeNote) {
                    let idx = this.notes.findIndex(n => n.id === this.activeNote.id);
                    if (idx !== -1 && this.notes[idx].title !== this.activeNoteTitle) {
                        this.notes[idx].title = this.activeNoteTitle;
                    }
                }

                clearTimeout(saveTimeoutTimer);
                saveTimeoutTimer = setTimeout(() => {
                    this.saveNote();
                }, 1500);
            },

            saveNote() {
                if (!this.activeNote || this.activeNote.id === 'temp' || this.viewMode === 'bin' || !quillInstance) return;

                const content = quillInstance.root.innerHTML;
                const plainText = quillInstance.getText();

                const statusEl = document.getElementById('save-status');
                if (statusEl) {
                    statusEl.innerHTML = '<span class="text-yellow-600 flex items-center gap-1"><svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Saving...</span>';
                }

                fetch(`/notes/api/${this.activeNote.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        title: this.activeNoteTitle || 'Untitled',
                        content: content,
                        plain_text: plainText
                    })
                })
                .then(res => res.json())
                .then(updatedNote => {
                    if (statusEl) statusEl.innerHTML = '<span class="text-slate-400">Saved</span>';
                    
                    // Update list with fresh data
                    let idx = this.notes.findIndex(n => n.id === updatedNote.id);
                    if (idx !== -1) {
                        // Only replace if it actually differs to avoid excessive DOM re-renders in the list
                        if (this.notes[idx].updated_at !== updatedNote.updated_at || this.notes[idx].title !== updatedNote.title || this.notes[idx].plain_text !== updatedNote.plain_text) {
                            this.notes[idx] = updatedNote;
                        }
                    }
                })
                .catch(() => {
                    if (statusEl) statusEl.innerHTML = '<span class="text-rose-500 font-bold">Save failed</span>';
                });
            },

            async deleteNote() {
                if (!this.activeNote || this.activeNote.id === 'temp') return;

                if (this.viewMode === 'bin') {
                    if (! await window.confirmModal({
                        title: 'Delete forever?',
                        message: 'This note will be permanently deleted.',
                        confirmText: 'Delete forever',
                        tone: 'danger',
                    })) return;

                    await this.forceDeleteNote(this.activeNote);
                    return;
                }

                if (! await window.confirmModal({
                    title: 'Move note to bin?',
                    message: 'You can restore it later from Note Bin.',
                    confirmText: 'Move to Bin',
                    tone: 'danger',
                })) return;

                fetch(`/notes/api/${this.activeNote.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                }).then(() => {
                    this.notes = this.notes.filter(n => n.id !== this.activeNote.id);
                    this.selectedNoteIds = this.selectedNoteIds.filter((id) => id !== this.activeNote.id);
                    this.activeNote = null;
                });
            },

            toggleSelectMode() {
                this.isSelectMode = !this.isSelectMode;
                if (!this.isSelectMode) this.clearSelection();
            },

            isSelected(id) {
                return this.selectedNoteIds.includes(id);
            },

            toggleNoteSelection(id) {
                if (this.isSelected(id)) {
                    this.selectedNoteIds = this.selectedNoteIds.filter((item) => item !== id);
                    return;
                }

                this.selectedNoteIds.push(id);
            },

            selectAllVisible() {
                this.selectedNoteIds = this.notes.map((note) => note.id);
            },

            clearSelection() {
                this.selectedNoteIds = [];
            },

            async downloadSelected() {
                if (!this.selectedNoteIds.length) return;

                const res = await fetch('/notes/api/download', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ note_ids: this.selectedNoteIds })
                });

                if (!res.ok) return;

                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'Selected notes.zip';
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
            },

            async bulkDelete() {
                if (!this.selectedNoteIds.length) return;
                if (! await window.confirmModal({
                    title: 'Move selected notes to bin?',
                    message: `${this.selectedNoteIds.length} note(s) will move to Note Bin.`,
                    confirmText: 'Move to Bin',
                    tone: 'danger',
                })) return;

                const res = await fetch('/notes/api/bulk-delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ note_ids: this.selectedNoteIds })
                });
                const data = await res.json();
                const deleted = data.deleted_ids || [];
                this.notes = this.notes.filter((note) => !deleted.includes(note.id));
                if (this.activeNote && deleted.includes(this.activeNote.id)) this.activeNote = null;
                this.clearSelection();
            },

            async restoreNote(note) {
                const res = await fetch(`/notes/api/${note.id}/restore`, {
                    method: 'PUT',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                });

                if (res.ok) {
                    this.notes = this.notes.filter((item) => item.id !== note.id);
                    this.selectedNoteIds = this.selectedNoteIds.filter((id) => id !== note.id);
                    if (this.activeNote?.id === note.id) this.activeNote = null;
                }
            },

            async restoreSelected() {
                await Promise.all(this.selectedNoteIds.map((id) => this.restoreNote({ id })));
                this.clearSelection();
            },

            async forceDeleteNote(note) {
                const res = await fetch(`/notes/api/${note.id}/force`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                });

                if (res.ok) {
                    this.notes = this.notes.filter((item) => item.id !== note.id);
                    this.selectedNoteIds = this.selectedNoteIds.filter((id) => id !== note.id);
                    if (this.activeNote?.id === note.id) this.activeNote = null;
                }
            },

            async forceDeleteSelected() {
                if (!this.selectedNoteIds.length) return;
                if (! await window.confirmModal({
                    title: 'Delete selected forever?',
                    message: `${this.selectedNoteIds.length} note(s) will be permanently deleted.`,
                    confirmText: 'Delete forever',
                    tone: 'danger',
                })) return;

                await Promise.all(this.selectedNoteIds.map((id) => this.forceDeleteNote({ id })));
                this.clearSelection();
            },

            formatDate(dateStr) {
                if (!dateStr) return '';
                const date = new Date(dateStr);
                const today = new Date();
                if (date.toDateString() === today.toDateString()) {
                    return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                }
                return date.toLocaleDateString([], {month: 'short', day: 'numeric'});
            },

            formatFullDate(dateStr) {
                if (!dateStr) return '';
                const date = new Date(dateStr);
                return date.toLocaleString([], {month: 'long', day: 'numeric', year: 'numeric', hour: '2-digit', minute:'2-digit'});
            }
        }));
    });
</script>
@endpush
