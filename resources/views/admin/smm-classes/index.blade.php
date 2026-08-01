@extends('layouts.app')
@section('title', 'Class Label Management')
@section('page_title', 'Class Label Management')

@section('content')
<div x-data="smmClassManager()" class="animate-fade-in">

  {{-- Top Bar --}}
  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 sm:gap-0 mb-5">
    <div>
        <p class="text-sm text-slate-500">Manage SMM Class labels.</p>
    </div>
    <button @click="openCreateModal()" class="btn btn-primary py-2 px-4 shadow-md flex items-center justify-center gap-2 whitespace-nowrap shrink-0 w-full sm:w-auto">
      <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      Create Class Label
    </button>
  </div>

  {{-- Labels Table --}}
  <div class="card p-0 overflow-hidden shadow-sm border border-slate-200/60">
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-bold text-slate-500 uppercase tracking-wider">
            <th class="px-5 py-4">Class Label</th>
            <th class="px-5 py-4">External Link</th>
            <th class="px-4 py-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($classes as $class)
          <tr class="hover:bg-slate-50/70 transition-colors group">
            <td class="px-5 py-3">
              <div class="flex items-center gap-3">
                <span class="inline-flex items-center h-4 w-12 rounded-full shadow-sm" style="background:{{ $class->color }}"></span>
                <span class="font-bold text-slate-800">{{ $class->name }}</span>
              </div>
            </td>
            <td class="px-5 py-3">
              @if($class->external_link)
                <a href="{{ $class->external_link }}" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 underline flex items-center gap-1">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                  Drive Link
                </a>
              @else
                <span class="text-xs text-slate-400">None</span>
              @endif
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button @click="openEditModal({{ $class->id }}, '{{ addslashes($class->name) }}', '{{ $class->color }}', '{{ addslashes($class->external_link ?? '') }}')" 
                        class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="Edit">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                </button>
                <form method="POST" action="{{ route('admin.smm-classes.destroy', $class) }}"
                      data-confirm-title="Delete class label?"
                      data-confirm="Delete this class label permanently?"
                      data-confirm-text="Delete class"
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
            <td colspan="2" class="text-center py-16 text-slate-400 font-medium">
                No class labels found. Click "Create Class Label" to get started.
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
        <h3 class="font-black text-slate-800" x-text="editId ? 'Edit Class Label' : 'Create Class Label'"></h3>
        <button @click="showModal=false" class="text-slate-400 hover:text-slate-600 transition-colors">✕</button>
      </div>
      
      <form :action="editId ? `{{ url('admin/smm-classes') }}/${editId}` : '{{ route('admin.smm-classes.store') }}'" method="POST" class="p-6 space-y-4">
        @csrf
        <template x-if="editId">
            <input type="hidden" name="_method" value="PUT">
        </template>
        
        <div>
            <label class="form-label text-xs">Name</label>
            <input type="text" name="name" x-model="form.name" required class="form-input py-2 text-sm" placeholder="e.g. Health & Care">
        </div>

        <div>
            <label class="form-label text-xs">Color Hex</label>
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded border border-slate-200 shadow-inner"
                      :style="'background-color: ' + form.color"></span>
                <input type="text" name="color" x-model="form.color" required pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$" class="form-input py-2 text-sm flex-1 font-mono uppercase" placeholder="#ef4444">
            </div>
        </div>

        <div>
            <label class="form-label text-xs">External Link (Google Drive, etc.)</label>
            <input type="url" name="external_link" x-model="form.external_link" class="form-input py-2 text-sm" placeholder="https://drive.google.com/...">
        </div>

        <div class="pt-2 flex justify-end gap-2">
            <button type="button" @click="showModal=false" class="btn btn-secondary py-2 text-xs">Cancel</button>
            <button type="submit" class="btn btn-primary py-2 text-xs shadow-md" x-text="editId ? 'Save Changes' : 'Create Class Label'"></button>
        </div>
      </form>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
(function() {
    const initSmmClassApp = () => {
        Alpine.data('smmClassManager', () => ({
        showModal: false,
        editId: null,
        form: {
            name: '',
            color: '#ef4444',
            external_link: ''
        },

        openCreateModal() {
            this.editId = null;
            this.form = { name: '', color: '#ef4444', external_link: '' };
            this.showModal = true;
        },

        openEditModal(id, name, color, external_link) {
            this.editId = id;
            this.form = {
                name: name,
                color: color || '#ef4444',
                external_link: external_link || ''
            };
            this.showModal = true;
        }
    }));
    };

    if (window.Alpine) {
        initSmmClassApp();
    } else {
        document.addEventListener('alpine:init', initSmmClassApp);
    }
})();
</script>
@endpush
