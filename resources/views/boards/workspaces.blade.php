@extends('layouts.app')
@section('title', 'Workspaces & Boards')
@section('page_title', 'Workspaces')

@section('content')
<div class="animate-fade-in space-y-8" x-data="workspacePage()">

  {{-- ── Header ───────────────────────────────────────────────────────── --}}
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-display font-bold text-slate-800">My Workspaces</h1>
      <p class="text-sm text-slate-400 mt-0.5">All your workspaces and boards in one place.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
      @php
        $digitalTeamWs = $workspaces->firstWhere('name', 'Infographic@KiuQ') ?? $workspaces->firstWhere('name', 'Digital Team');
      @endphp
      @if($digitalTeamWs && auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor']))
        <div class="relative" x-data="{ openWsMembers: false, search: '' }">
          <button @click="openWsMembers = !openWsMembers; search = ''" class="btn btn-primary gap-2">
            👥 Members
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
                $possibleUsers = \App\Models\User::active()->get()->filter(fn($u) => $u->hasAnyRole(['digital-team', 'admin-digital', 'admin', 'boss', 'supervisor', 'staff']));
              @endphp
              @foreach($possibleUsers as $u)
                <div x-show="search === '' || '{{ strtolower(addslashes($u->name)) }}'.includes(search.toLowerCase())" class="flex items-center justify-between gap-2 text-xs">
                  <div class="flex items-center gap-1.5">
                    <img src="{{ $u->avatar_url }}" class="w-6.5 h-6.5 rounded-full object-cover border border-slate-200">
                    <span class="font-semibold text-slate-700 truncate max-w-28" title="{{ $u->name }}">{{ $u->name }}</span>
                  </div>
                  @if($digitalTeamWs->hasMember($u->id))
                    <button @click="removeWorkspaceMember({{ $digitalTeamWs->id }}, {{ $u->id }}, $el)" class="text-[10px] text-rose-500 font-bold px-2.5 py-1 rounded-md hover:bg-rose-500 hover:text-white transition-colors">Remove</button>
                  @else
                    <button @click="addWorkspaceMember({{ $digitalTeamWs->id }}, {{ $u->id }}, $el)" class="text-[10px] text-indigo-600 font-bold px-2.5 py-1 rounded-md hover:bg-indigo-600 hover:text-white transition-colors">Add</button>
                  @endif
                </div>
              @endforeach
            </div>
          </div>
        </div>
      @endif

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
      <div class="flex items-center gap-3 mb-4">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
             style="background-color: {{ $workspace->color }}">
          {{ $workspace->icon_text ?? strtoupper(substr($workspace->name, 0, 1)) }}
        </div>
        <h2 class="font-display font-bold text-slate-700 text-lg">{{ $workspace->name }}</h2>
        <span class="badge badge-slate text-xs">{{ $workspace->boards->count() }} boards</span>
        @if(auth()->user()->hasAnyRole(['super-admin', 'admin', 'admin-digital']) || $workspace->owner_id === auth()->id())
          <button @click="openEditWorkspace({{ $workspace->id }}, '{{ addslashes($workspace->name) }}', '{{ $workspace->color }}', '{{ addslashes($workspace->icon_text ?? '') }}')" class="text-slate-400 hover:text-indigo-600 transition-colors p-1 rounded-md hover:bg-indigo-50">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
          </button>
        @endif
        <div class="ml-auto flex gap-2 items-center">



        </div>
      </div>

      {{-- Board grid --}}
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4">
        @foreach($workspace->boards as $board)
          <a href="{{ route('boards.show', $board->slug) }}"
             class="group relative h-28 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5"
             style="{{ $board->backgroundStyle() }}">
            {{-- Overlay --}}
            <div class="absolute inset-0 bg-black/20 group-hover:bg-black/10 transition-colors"></div>

            {{-- Star --}}
            @if($board->is_starred)
              <div class="absolute top-2 right-2 text-amber-300 z-10">
                <svg class="w-4 h-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
              </div>
            @endif

            {{-- Board name --}}
            <div class="absolute bottom-0 left-0 right-0 p-3">
              <p class="text-white font-semibold text-sm drop-shadow leading-tight">{{ $board->name }}</p>
            </div>
          </a>
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

      <form method="POST" action="{{ route('boards.store') }}" class="space-y-4" x-data="{ template: 'normal', customColor: '#6366f1' }" x-init="$watch('template', value => { if (value === 'workflow') customColor = '#ffffff'; else if (value === 'planning') customColor = '#ef4444'; else customColor = '#6366f1'; })">
        @csrf
        <input type="hidden" name="workspace_id" :value="selectedWorkspaceId">

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
            <select name="template_month" class="form-input">
              @foreach(['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] as $m)
                <option value="{{ $m }}" {{ date('F') === $m ? 'selected' : '' }}>{{ $m }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Year</label>
            <select name="template_year" class="form-input">
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
          <label class="form-label">Background Color</label>
          <div class="flex flex-wrap gap-2 mt-2">
            {{-- Custom Color Picker (Multi-color wheel) --}}
            <label class="cursor-pointer relative group flex-shrink-0" title="Choose any color">
              <input type="radio" name="background_value" x-bind:value="customColor" class="peer sr-only" x-ref="customRadio" checked>
              <span class="block w-10 h-10 rounded-xl border-2 border-white peer-checked:ring-4 peer-checked:ring-offset-1 peer-checked:scale-105 hover:scale-105 transition-all duration-200 flex items-center justify-center shadow-sm overflow-hidden"
                    x-bind:style="'background: conic-gradient(#ef4444, #f59e0b, #10b981, #06b6d4, #3b82f6, #8b5cf6, #d946ef, #ef4444); --tw-ring-color: ' + customColor">
                  {{-- Inner circle showing the actively selected color --}}
                  <span class="block w-4 h-4 rounded-full border-2 border-white shadow-md" x-bind:style="'background-color: ' + customColor"></span>
              </span>
              <input type="color" x-model="customColor" @input="$refs.customRadio.checked = true" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" title="Pick a color">
            </label>
          </div>
          <input type="hidden" name="background_type" value="color">
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
          <input type="color" name="color" class="w-14 h-10 p-1 border border-slate-200 rounded-lg cursor-pointer" value="#6366f1" required>
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
          <input type="color" name="color" x-model="editWorkspaceModal.color" class="w-14 h-10 p-1 border border-slate-200 rounded-lg cursor-pointer" required>
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
    </div>
  </div>

  {{-- ── Hidden Boards Modal ─────────────────────────────────────────────── --}}
  @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital']))
  <div x-show="showHiddenBoards" x-cloak
       class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
       @click.self="showHiddenBoards = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 flex flex-col max-h-[80vh]"
         @click.stop>
      <div class="flex items-center justify-between mb-5">
        <h3 class="font-display font-bold text-slate-800 text-lg">Hidden Boards</h3>
        <button @click="showHiddenBoards = false" class="text-slate-400 hover:text-slate-600 transition-colors">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="overflow-y-auto flex-1 pr-2 space-y-3 scrollbar-thin">
        @forelse($hiddenBoards ?? [] as $hb)
          <div class="hidden-board-item flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl p-3">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold flex-shrink-0 shadow-sm"
                   style="background-color: {{ $hb->background_type === 'color' ? $hb->background_value : '#6366f1' }}">
                {{ strtoupper(substr($hb->name, 0, 1)) }}
              </div>
              <div>
                <h4 class="font-bold text-slate-700 text-sm">{{ $hb->name }}</h4>
                <p class="text-xs text-slate-500">{{ $hb->workspace->name }}</p>
              </div>
            </div>
            <button onclick="unhideBoard('{{ $hb->slug }}', this)" class="btn btn-secondary text-xs px-3 py-1.5 h-auto rounded-lg font-bold border-slate-200 hover:bg-slate-100 hover:text-indigo-600">
              Unhide
            </button>
          </div>
        @empty
          <div class="text-center py-8">
            <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
            <p class="text-slate-500 font-medium">No hidden boards found.</p>
          </div>
        @endforelse
      </div>
    </div>
  </div>
  @endif

</div>
@endsection

@push('scripts')
<script>
function workspacePage() {
  return {
    showCreateBoard: false,
    showCreateWorkspace: false,
    editWorkspaceModal: {
      open: false, id: null, name: '', color: '#6366f1', icon_text: ''
    },
    showHiddenBoards: false,
    selectedWorkspaceId: {{ $workspaces->first()?->id ?? 'null' }},

    openCreateBoard(workspaceId) {
      this.selectedWorkspaceId = workspaceId;
      this.showCreateBoard = true;
    },

    openEditWorkspace(id, name, color, icon_text) {
      this.editWorkspaceModal.id = id;
      this.editWorkspaceModal.name = name;
      this.editWorkspaceModal.color = color || '#6366f1';
      this.editWorkspaceModal.icon_text = icon_text || '';
      this.editWorkspaceModal.open = true;
    }
  }
}

async function addWorkspaceMember(workspaceId, userId, btn) {
  try {
    btn.disabled = true;
    btn.innerHTML = '<span class="opacity-50">...</span>';
    const res = await fetch(`/boards/workspaces/${workspaceId}/members`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      },
      body: JSON.stringify({ user_id: userId })
    });
    if (!res.ok) throw new Error('Failed to add member');
    
    // Switch to Remove button visually
    btn.outerHTML = `<button onclick="removeWorkspaceMember(${workspaceId}, ${userId}, this)" class="text-[10px] text-rose-500 font-bold px-2.5 py-1 rounded-md hover:bg-rose-500 hover:text-white transition-colors">Remove</button>`;
    Alpine.store('toast').show('Member added to workspace');
  } catch (err) {
    console.error(err);
    Alpine.store('toast').show('Error adding member', 'error');
    btn.disabled = false;
    btn.textContent = 'Add';
  }
}

async function removeWorkspaceMember(workspaceId, userId, btn) {
  if (!await window.confirmModal('Are you sure you want to remove this member?')) return;
  try {
    btn.disabled = true;
    btn.innerHTML = '<span class="opacity-50">...</span>';
    const res = await fetch(`/boards/workspaces/${workspaceId}/members/${userId}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      }
    });
    if (!res.ok) throw new Error('Failed to remove member');
    
    // Switch to Add button visually
    btn.outerHTML = `<button onclick="addWorkspaceMember(${workspaceId}, ${userId}, this)" class="text-[10px] text-indigo-600 font-bold px-2.5 py-1 rounded-md hover:bg-indigo-600 hover:text-white transition-colors">Add</button>`;
    Alpine.store('toast').show('Member removed from workspace');
  } catch (err) {
    console.error(err);
    Alpine.store('toast').show('Error removing member', 'error');
    btn.disabled = false;
    btn.textContent = 'Add';
  }
}

async function unhideBoard(boardSlug, btn) {
  try {
    btn.disabled = true;
    btn.textContent = '...';
    const res = await fetch(`/boards/${boardSlug}/toggle-hidden`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      }
    });
    if (!res.ok) throw new Error('Failed to unhide board');
    
    Alpine.store('toast').show('Board unhidden successfully!');
    btn.closest('.hidden-board-item').remove();
    setTimeout(() => {
      window.location.reload();
    }, 1200);
  } catch (err) {
    console.error(err);
    Alpine.store('toast').show('Error unhiding board', 'error');
    btn.disabled = false;
    btn.textContent = 'Unhide';
  }
}
</script>
@endpush
