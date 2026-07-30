@extends('layouts.app')

@section('title', 'SMM Planning Boards')

@section('content')
<style>
/* Dashboard Styles matching Websites module */
@keyframes countUp { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
.metric-counter { animation: countUp 0.5s ease both; }

.ws-dash-card {
    background: var(--card-bg, #fff);
    border: 1.5px solid var(--border-color, #e2e8f0);
    border-radius: 1.25rem;
    transition: all 0.25s ease;
    overflow: hidden;
    display: flex; flex-direction: column;
}
.ws-dash-card:hover { box-shadow: 0 12px 40px rgba(0,0,0,0.1); transform: translateY(-3px); }
[data-theme="dark"] .ws-dash-card { background: #0f172a; border-color: #1e293b; }
[data-theme="dark"] .ws-dash-card:hover { box-shadow: 0 12px 40px rgba(0,0,0,0.4); }
</style>

<div x-data="{ searchQuery: '', filterWorkspace: '', showCreateModal: false }">
<div class="page-header flex flex-wrap gap-4 items-end justify-between mb-8">
    <div>
        <h1 class="page-title text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
            <div class="p-2 bg-indigo-500 text-white rounded-xl shadow-lg shadow-indigo-500/30 w-12 h-12 flex items-center justify-center flex-shrink-0">
                <img src="https://cdn-icons-png.flaticon.com/512/1468/1468269.png" alt="SMM Planning" class="w-8 h-8 object-contain">
            </div>
            SMM Planning Boards
        </h1>
        <p class="page-subtitle text-slate-500 dark:text-slate-400 mt-2 font-medium">Manage monthly Social Media Planning boards.</p>
    </div>
    
    <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 w-full md:w-auto mt-4 md:mt-0">
        <div class="flex gap-2 w-full sm:w-auto">
            <div class="flex-1 flex items-center gap-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-1.5 shadow-sm min-w-0">
                <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="searchQuery" placeholder="Search boards..." class="border-0 focus:ring-0 p-0 text-sm w-full placeholder-slate-400 dark:placeholder-slate-500 bg-transparent text-slate-800 dark:text-white min-w-0">
            </div>
            <select x-model="filterWorkspace" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-sm rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 px-3 py-1.5 min-w-[120px]">
                <option value="">All Workspaces</option>
                @foreach($workspaces as $ws)
                    <option value="{{ $ws->id }}">{{ $ws->name }}</option>
                @endforeach
            </select>
        </div>

        @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'boss']))
        <button @click="showCreateModal = true" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-500/20 text-white text-sm font-semibold rounded-lg shadow-sm transition-all shadow-indigo-600/20 whitespace-nowrap">
            <svg class="w-5 h-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Create Monthly Board
        </button>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    @forelse($boards as $board)
    <div class="ws-dash-card relative" 
         x-show="(searchQuery === '' || '{{ strtolower($board->name) }}'.includes(searchQuery.toLowerCase())) && (filterWorkspace === '' || filterWorkspace == '{{ $board->workspace_id }}')"
         style="display: none;" x-transition>
        
        <div class="h-24 w-full relative" style="{{ $board->coverStyle() }}">
            <div class="absolute inset-0 bg-black/20"></div>
            @if($board->is_active_smm)
                <div class="absolute top-3 left-3 bg-emerald-500 text-white text-xs font-bold px-2 py-1 rounded shadow">
                    ACTIVE BOARD
                </div>
            @endif
            @if($board->is_hidden)
                <div class="absolute top-3 right-3 bg-slate-800/80 text-white text-xs font-bold px-2 py-1 rounded shadow">
                    HIDDEN
                </div>
            @endif
        </div>
        
        <div class="p-5 flex-1 flex flex-col">
            <div class="text-xs font-bold text-slate-400 dark:text-slate-500 mb-1 uppercase tracking-wider">
                {{ $board->workspace->name ?? 'No Workspace' }}
            </div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-white leading-tight mb-2">
                <a href="{{ route('boards.show', $board->slug) }}" class="hover:text-indigo-500 transition-colors">
                    {{ $board->name }}
                </a>
            </h3>
            
            <p class="text-sm text-slate-500 dark:text-slate-400 line-clamp-2 mb-4 flex-1">
                {{ $board->description ?: 'No description provided.' }}
            </p>

            <div class="pt-4 mt-auto border-t border-slate-100 dark:border-slate-800/50 flex items-center justify-between">
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">
                    Created by {{ $board->creator->name ?? 'Unknown' }}
                </div>
                
                @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'boss']))
                <div class="flex items-center gap-2">
                    <form action="{{ route('smm-boards.toggle-active', $board->id) }}" method="POST">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-xs font-semibold px-2 py-1 rounded border {{ $board->is_active_smm ? 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/30' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700 dark:hover:bg-slate-700' }}"
                                title="Toggle Active Status for Automation">
                            {{ $board->is_active_smm ? 'Deactivate' : 'Set Active' }}
                        </button>
                    </form>
                    
                    <div x-data="{ drop: false }" class="relative">
                        <button @click="drop = !drop" @click.away="drop = false" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                        </button>
                        <div x-show="drop" x-cloak class="absolute right-0 bottom-full mb-1 w-40 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-10 overflow-hidden">
                            <form action="{{ route('smm-boards.duplicate', $board->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50">Duplicate</button>
                            </form>
                            <form action="{{ route('smm-boards.toggle-hidden', $board->id) }}" method="POST">
                                @csrf @method('PATCH')
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50">{{ $board->is_hidden ? 'Unhide' : 'Hide' }}</button>
                            </form>
                            <form action="{{ route('smm-boards.destroy', $board->id) }}" method="POST" onsubmit="return confirm('Delete this board entirely?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="col-span-full py-12 text-center text-slate-500 dark:text-slate-400">
        <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        <p>No SMM Planning Boards found.</p>
    </div>
    @endforelse
</div>

<!-- Create Modal -->
<div x-show="showCreateModal" style="display:none;" class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto overflow-x-hidden bg-slate-900/50 dark:bg-slate-900/80 backdrop-blur-sm p-4">
    <div @click.away="showCreateModal = false" class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-2xl overflow-hidden border border-slate-200 dark:border-slate-700 transform transition-all">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/50 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white">Create SMM Planning Board</h3>
            <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form action="{{ route('smm-boards.store') }}" method="POST">
            @csrf
            <div class="p-6 space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Board Name</label>
                    <input type="text" name="name" required placeholder="e.g. Planning Board - August" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg px-4 py-2.5 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Workspace</label>
                    <select name="workspace_id" required class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg px-4 py-2.5 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                        @foreach($workspaces as $ws)
                            <option value="{{ $ws->id }}">{{ $ws->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Select the team workspace this board belongs to.</p>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Description (Optional)</label>
                    <textarea name="description" rows="2" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg px-4 py-2.5 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Background Color</label>
                    <div class="flex gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="background" value="#6366f1" checked class="peer sr-only">
                            <div class="w-8 h-8 rounded-full bg-indigo-500 ring-2 ring-transparent peer-checked:ring-slate-900 dark:peer-checked:ring-white ring-offset-2 dark:ring-offset-slate-800 transition-all"></div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="background" value="#10b981" class="peer sr-only">
                            <div class="w-8 h-8 rounded-full bg-emerald-500 ring-2 ring-transparent peer-checked:ring-slate-900 dark:peer-checked:ring-white ring-offset-2 dark:ring-offset-slate-800 transition-all"></div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="background" value="#f59e0b" class="peer sr-only">
                            <div class="w-8 h-8 rounded-full bg-amber-500 ring-2 ring-transparent peer-checked:ring-slate-900 dark:peer-checked:ring-white ring-offset-2 dark:ring-offset-slate-800 transition-all"></div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="background" value="#ec4899" class="peer sr-only">
                            <div class="w-8 h-8 rounded-full bg-pink-500 ring-2 ring-transparent peer-checked:ring-slate-900 dark:peer-checked:ring-white ring-offset-2 dark:ring-offset-slate-800 transition-all"></div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="background" value="#0ea5e9" class="peer sr-only">
                            <div class="w-8 h-8 rounded-full bg-sky-500 ring-2 ring-transparent peer-checked:ring-slate-900 dark:peer-checked:ring-white ring-offset-2 dark:ring-offset-slate-800 transition-all"></div>
                        </label>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-700/50 flex justify-end gap-3">
                <button type="button" @click="showCreateModal = false" class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors">Create Board</button>
            </div>
        </form>
    </div>
</div>
</div>
@endsection
