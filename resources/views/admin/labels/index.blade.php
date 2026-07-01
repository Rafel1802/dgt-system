@extends('layouts.app')
@section('title', 'Label Management')
@section('page_title', 'Label Management')

@section('content')
<div x-data="labelManager()" class="animate-fade-in">

  {{-- Top Bar --}}
  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 sm:gap-0 mb-5">
    <div>
        <p class="text-sm text-slate-500">Manage Global, Workspace, and Board labels.</p>
    </div>
    <button @click="openCreateModal()" class="btn btn-primary py-2 px-4 shadow-md flex items-center justify-center gap-2 whitespace-nowrap shrink-0 w-full sm:w-auto">
      <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      Create Label
    </button>
  </div>

  {{-- Labels Table --}}
  <div class="card p-0 overflow-hidden shadow-sm border border-slate-200/60">
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-bold text-slate-500 uppercase tracking-wider">
            <th class="px-5 py-4">Label</th>
            <th class="px-4 py-4">Scope</th>
            <th class="px-4 py-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($labels as $label)
          <tr class="hover:bg-slate-50/70 transition-colors group">
            <td class="px-5 py-3">
              <div class="flex items-center gap-3">
                <span class="inline-flex items-center h-4 w-12 rounded-full shadow-sm" style="background:{{ $label->color }}"></span>
                <span class="font-bold text-slate-800">{{ $label->name }}</span>
              </div>
            </td>
            <td class="px-4 py-3">
                @if(!$label->workspace_id && !$label->board_id)
                    <span class="badge badge-emerald text-[10px] font-black uppercase tracking-wider">Global</span>
                @elseif($label->workspace_id)
                    <span class="badge badge-indigo text-[10px] font-black uppercase tracking-wider">Workspace: {{ $label->workspace?->name ?? '(deleted)' }}</span>
                @elseif($label->board_id)
                    <span class="badge badge-slate text-[10px] font-black uppercase tracking-wider">Board: {{ $label->board?->name ?? '(deleted)' }}</span>
                @endif
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button @click="openEditModal({{ $label->id }}, '{{ addslashes($label->name) }}', '{{ $label->color }}', {{ $label->workspace_id ?? 'null' }}, {{ $label->board_id ?? 'null' }})" 
                        class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="Edit">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                </button>
                <form method="POST" action="{{ route('admin.labels.destroy', $label) }}"
                      data-confirm-title="Delete label?"
                      data-confirm="Delete this label permanently?"
                      data-confirm-text="Delete label"
                      data-confirm-tone="danger"
                      class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-icon" style="width:28px;height:28px;" title="Delete">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                    </button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="3" class="text-center py-16 text-slate-400 font-medium">
                No labels found. Click "Create Label" to get started.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Modal (Create/Edit) --}}
  <div x-show="showModal" x-cloak class="modal-overlay flex items-center justify-center" @keydown.escape.window="showModal=false">
    <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-sm overflow-hidden" @click.outside="showModal=false">
      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
        <h3 class="font-black text-slate-800" x-text="editId ? 'Edit Label' : 'Create Label'"></h3>
        <button @click="showModal=false" class="text-slate-400 hover:text-slate-600 transition-colors">✕</button>
      </div>
      
      <form :action="editId ? `{{ url('admin/labels') }}/${editId}` : '{{ route('admin.labels.store') }}'" method="POST" class="p-6 space-y-4">
        @csrf
        <template x-if="editId">
            <input type="hidden" name="_method" value="PUT">
        </template>
        
        <div>
            <label class="form-label text-xs">Name</label>
            <input type="text" name="name" x-model="form.name" required class="form-input py-2 text-sm" placeholder="e.g. Urgent">
        </div>

        <div>
            <label class="form-label text-xs">Color Hex</label>
            <div class="flex items-center gap-3">
                <input type="color" x-model="form.color" class="w-10 h-10 rounded cursor-pointer border-0 p-0">
                <input type="text" name="color" x-model="form.color" required pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$" class="form-input py-2 text-sm flex-1 font-mono uppercase" placeholder="#ef4444">
            </div>
        </div>

        <div>
            <label class="form-label text-xs">Workspace Scope (Optional)</label>
            <select name="workspace_id" x-model="form.workspace_id" class="form-input py-2 text-sm">
                <option value="">-- None (Global) --</option>
                @foreach($workspaces as $workspace)
                    <option value="{{ $workspace->id }}">{{ $workspace->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="form-label text-xs">Board Scope (Optional)</label>
            <select name="board_id" x-model="form.board_id" class="form-input py-2 text-sm">
                <option value="">-- None (Global/Workspace) --</option>
                @foreach($boards as $board)
                    <option value="{{ $board->id }}">{{ $board->name }}</option>
                @endforeach
            </select>
            <p class="text-[10px] text-slate-400 mt-1 leading-tight">If both Workspace and Board are blank, the label is Global and applies everywhere.</p>
        </div>

        <div class="pt-2 flex justify-end gap-2">
            <button type="button" @click="showModal=false" class="btn btn-secondary py-2 text-xs">Cancel</button>
            <button type="submit" class="btn btn-primary py-2 text-xs shadow-md" x-text="editId ? 'Save Changes' : 'Create Label'"></button>
        </div>
      </form>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('labelManager', () => ({
        showModal: false,
        editId: null,
        form: {
            name: '',
            color: '#ef4444',
            workspace_id: '',
            board_id: ''
        },

        openCreateModal() {
            this.editId = null;
            this.form = { name: '', color: '#ef4444', workspace_id: '', board_id: '' };
            this.showModal = true;
        },

        openEditModal(id, name, color, workspaceId, boardId) {
            this.editId = id;
            this.form = {
                name: name,
                color: color,
                workspace_id: workspaceId || '',
                board_id: boardId || ''
            };
            this.showModal = true;
        }
    }));
});
</script>
@endpush
