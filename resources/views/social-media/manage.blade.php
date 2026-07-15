@extends('layouts.app')

@section('title', 'Manage Classes - Social Media')
@section('back_url', route('social-media.dashboard'))

@section('content')
<div x-data="{
    showCreateModal: false,
    showRolesModal: false,
    searchQuery: '',
    filterClass: '',

    /* ── Delete confirm modal ── */
    deleteModal: false,
    deleteAction: '',
    deleteLabel: '',
    confirmDelete(action, label) {
        this.deleteAction = action;
        this.deleteLabel  = label;
        this.deleteModal  = true;
    },
    submitDelete() {
        this.$refs.deleteForm.action = this.deleteAction;
        this.$refs.deleteForm.submit();
    }
}">

    {{-- Hidden delete form (shared by all delete buttons) --}}
    <form x-ref="deleteForm" method="POST" style="display:none;">
        @csrf
        @method('DELETE')
    </form>

    {{-- ── Pretty Delete Confirm Modal ──────────────────────────────────── --}}
    <div x-show="deleteModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[60] flex items-center justify-center px-4"
         x-cloak style="display:none;">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="deleteModal = false"></div>
        {{-- Dialog card --}}
        <div x-show="deleteModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-90 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-90 translate-y-4"
             class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden z-10">
            {{-- Red gradient top bar --}}
            <div class="h-1.5 w-full bg-gradient-to-r from-rose-400 via-red-500 to-rose-600"></div>
            <div class="p-7">
                {{-- Trash icon in circle --}}
                <div class="flex items-center justify-center w-16 h-16 rounded-full bg-rose-50 dark:bg-rose-900/30 ring-8 ring-rose-50/50 dark:ring-rose-900/10 mx-auto mb-5">
                    <svg class="w-8 h-8 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                </div>
                {{-- Title & text --}}
                <h3 class="text-center font-bold text-slate-800 dark:text-slate-100 text-xl mb-1">Delete this item?</h3>
                <p class="text-center text-sm text-slate-500 dark:text-slate-400 mb-3">You are about to permanently delete:</p>
                <div class="text-center font-semibold text-slate-700 dark:text-slate-200 text-sm bg-slate-50 dark:bg-slate-700/50 border border-slate-100 dark:border-slate-600 rounded-xl px-4 py-2.5 mb-4" x-text="deleteLabel"></div>
                <p class="text-center text-xs text-rose-500 font-medium mb-6">⚠️ This cannot be undone.</p>
                {{-- Buttons --}}
                <div class="flex gap-3">
                    <button type="button" @click="deleteModal = false"
                        class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-300 hover:text-slate-900 dark:bg-slate-700 dark:hover:bg-slate-500 dark:text-slate-200 transition-all duration-200 active:scale-95">
                        Cancel
                    </button>
                    <button type="button" @click="submitDelete()"
                        class="flex-1 px-4 py-2.5 rounded-xl text-sm font-bold bg-gradient-to-br from-rose-500 to-red-600 hover:from-rose-400 hover:to-red-500 hover:shadow-xl hover:shadow-rose-300 hover:scale-[1.03] text-white shadow-lg shadow-rose-200 dark:shadow-rose-900/40 transition-all duration-200 active:scale-95">
                        Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Page header --}}
    <div class="page-header flex flex-col md:flex-row gap-4 items-start md:items-end justify-between mb-6">
        <div>
            <h1 class="page-title">Manage Classes</h1>
            <p class="page-subtitle">Create classes and assign members</p>
        </div>
        <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 w-full md:w-auto">
            <div class="flex gap-2 w-full sm:w-auto">
                <div class="flex-1 flex items-center gap-2 bg-white border border-slate-200 rounded-lg px-3 py-1.5 shadow-sm min-w-0">
                    <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="searchQuery" placeholder="Search class..." class="border-0 focus:ring-0 p-0 text-sm w-full placeholder-slate-400 bg-transparent min-w-0">
                </div>
                <select x-model="filterClass" class="form-select py-1.5 text-sm flex-1 sm:w-48 shadow-sm min-w-0">
                    <option value="">All Classes</option>
                    @foreach($classes as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <button @click="showRolesModal = true" class="btn btn-secondary text-sm flex-1 sm:flex-none justify-center">👥 Team Roles</button>
                <button @click="showCreateModal = true" class="btn btn-primary text-sm flex-1 sm:flex-none justify-center">➕ Create</button>
                <a href="{{ route('social-media.dashboard') }}" class="btn btn-secondary text-sm flex-none justify-center">← Back</a>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error mb-4">{{ session('error') }}</div>
    @endif

    <div class="space-y-4">
        @forelse($classes as $class)
        <div class="card" x-data="{ view: 'main', showSingleForm: false }"
             x-show="(filterClass === '' || filterClass === '{{ $class->id }}') && ('{{ strtolower($class->name) }}'.includes(searchQuery.toLowerCase()))">
            {{-- Header --}}
            <div class="card-header flex flex-col md:flex-row md:items-start justify-between gap-3 md:gap-4">
                <div>
                    <h3 class="font-bold text-lg text-slate-800 flex items-center gap-2">
                        {{ $class->name }}
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $class->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ $class->status }}
                        </span>
                    </h3>
                </div>
                <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                    <button type="button" @click="showSingleForm = !showSingleForm" class="btn btn-secondary text-xs px-2 py-1 flex-1 sm:flex-none justify-center">
                        <span x-text="showSingleForm ? 'Cancel Single' : 'Create Single'"></span>
                    </button>
                    <div class="w-px h-4 bg-slate-200 mx-1 hidden sm:block"></div>
                    <button @click="view = (view === 'members' ? 'main' : 'members')" class="btn btn-secondary text-xs px-2 py-1 flex items-center justify-center gap-1 flex-1 sm:flex-none">
                        👥 Members ({{ $class->assignedUsers->count() }})
                    </button>
                    <button @click="view = (view === 'edit' ? 'main' : 'edit')" class="btn btn-secondary text-xs px-2 py-1 flex-1 sm:flex-none justify-center">
                        ✏️ Edit
                    </button>
                    {{-- Class delete button --}}
                    <button type="button"
                        @click="confirmDelete('{{ route('social-media.classes.destroy', $class) }}', '{{ addslashes($class->name) }} (class + all items)')"
                        class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-white bg-rose-500 hover:bg-rose-400 active:bg-rose-700 shadow-sm hover:shadow-rose-200 hover:scale-110 transition-all duration-200 active:scale-95 flex-none"
                        title="Delete class">
                        🗑
                    </button>
                </div>
            </div>

            <div class="p-4 border-b border-slate-100 bg-slate-50" x-show="view !== 'main'" x-transition x-cloak>
                {{-- Edit Class View --}}
                <div x-show="view === 'edit'">
                    <h4 class="text-sm font-bold mb-3">Edit Class Details</h4>
                    <form action="{{ route('social-media.classes.update', $class) }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-2">
                        @csrf @method('PUT')
                        <div class="flex-1">
                            <label class="form-label text-[10px]">Name</label>
                            <input type="text" name="name" value="{{ $class->name }}" required class="form-input py-1 text-sm">
                        </div>
                        <div class="flex-1">
                            <label class="form-label text-[10px]">Icon (URL or Upload)</label>
                            <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                                <input type="text" name="icon_url" value="{{ $class->icon }}" placeholder="https://..." class="form-input py-1 text-xs flex-1">
                                @include('social-media._file-picker', ['name' => 'icon_file', 'accept' => 'image/*', 'placeholder' => 'Choose icon…'])
                            </div>
                        </div>
                        <div class="flex-1">
                            <label class="form-label text-[10px]">Description</label>
                            <input type="text" name="description" value="{{ $class->description }}" class="form-input py-1 text-sm">
                        </div>
                        <div class="w-24">
                            <label class="form-label text-[10px]">Status</label>
                            <select name="status" class="form-select py-1 text-sm">
                                <option value="active" {{ $class->status === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $class->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary py-1 px-6 text-sm self-start mt-2">Save</button>
                    </form>
                </div>

                {{-- Members View --}}
                <div x-show="view === 'members'">
                    <h4 class="text-sm font-bold mb-3">Assigned Members</h4>
                    <form action="{{ route('social-media.classes.assign', $class) }}" method="POST" class="flex gap-2 mb-4">
                        @csrf
                        <select name="user_ids[]" class="form-select py-1.5 text-sm flex-1" required>
                            <option value="">-- Select user to assign --</option>
                            @foreach($allUsers as $u)
                                @if(!$class->assignedUsers->contains($u->id))
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary py-1.5 px-3 text-sm">Assign User</button>
                    </form>
                    <div class="flex flex-wrap gap-2">
                        @foreach($class->assignedUsers as $u)
                        <div class="inline-flex items-center gap-1 bg-white border border-slate-200 rounded-full px-3 py-1 shadow-sm">
                            <span class="text-xs font-medium text-slate-700">{{ $u->name }}</span>
                            <button type="button"
                                @click="confirmDelete('{{ route('social-media.classes.remove-user', [$class, $u]) }}', 'Remove {{ addslashes($u->name) }} from this class?')"
                                class="text-slate-300 hover:text-rose-500 hover:bg-rose-50 rounded-full p-0.5 ml-1 transition-all duration-200"
                                title="Remove member">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        @endforeach
                        @if($class->assignedUsers->isEmpty())
                            <span class="text-xs text-slate-400 italic">No members assigned yet.</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Main View: Social Media Items --}}
            <div class="card-body">
                <div class="mb-3">
                    <h4 class="text-sm font-bold text-slate-700">Social Media Items</h4>
                </div>

                {{-- Single Form --}}
                <div x-show="showSingleForm" x-transition x-cloak class="mb-4 bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <form action="{{ route('social-media.items.store', $class) }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-3">
                        @csrf
                        <div class="flex flex-wrap gap-2 items-end">
                            <div class="flex-1 min-w-[180px]">
                                <label class="form-label text-[10px] font-bold text-slate-500 uppercase mb-1">Platform Name</label>
                                <input type="text" name="name" placeholder="Platform (e.g. Facebook)" required class="form-input py-1.5 px-3 text-sm rounded-lg w-full">
                            </div>
                            <div class="flex-[2] min-w-[280px]">
                                <label class="form-label text-[10px] font-bold text-slate-500 uppercase mb-1">Icon (URL or Upload)</label>
                                <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                                    <input type="text" name="icon_url" placeholder="https://... or Emoji" class="form-input py-1.5 px-3 text-sm rounded-lg flex-1">
                                    @include('social-media._file-picker', ['name' => 'icon_file', 'accept' => 'image/*', 'placeholder' => 'Choose icon…'])
                                </div>
                            </div>
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="btn btn-primary py-1.5 px-4 text-sm">Add</button>
                        </div>
                    </form>
                </div>

                @if($class->items->isEmpty())
                    <p class="text-xs text-slate-400 text-center py-4 bg-slate-50 rounded-xl border border-dashed border-slate-200">No social media items added.</p>
                @else
                    <div class="space-y-2">
                        @foreach($class->items as $item)
                        <div x-data="{ editing: false }" class="p-3 border border-slate-100 rounded-xl bg-white shadow-sm hover:border-indigo-200 transition-colors">
                            {{-- Normal View --}}
                            <div x-show="!editing" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="text-lg flex items-center justify-center min-w-[24px] flex-shrink-0">{!! $item->icon_html !!}</span>
                                    <div>
                                        <div class="font-bold text-sm text-slate-700">{{ $item->name }}</div>
                                        <span class="text-[10px] uppercase font-bold {{ $item->status === 'active' ? 'text-emerald-600' : 'text-slate-400' }}">{{ $item->status }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2 items-center">
                                    <button @click="editing = true" type="button" class="btn btn-secondary text-xs py-1 px-2 flex-1 sm:flex-none justify-center flex items-center gap-1">✏️ Edit</button>
                                    <form action="{{ route('social-media.items.toggle', $item) }}" method="POST" class="flex-1 sm:flex-none flex">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-secondary text-xs py-1 px-2 w-full justify-center">{{ $item->status === 'active' ? 'Disable' : 'Enable' }}</button>
                                    </form>
                                    {{-- Item delete button: ghost rose → solid red on hover --}}
                                    <button type="button"
                                        @click="confirmDelete('{{ route('social-media.items.destroy', $item) }}', '{{ addslashes($item->name) }}')"
                                        class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold flex-1 sm:flex-none
                                               text-rose-600 bg-rose-50 border border-rose-100
                                               hover:bg-rose-500 hover:text-white hover:border-rose-500 hover:shadow-md hover:shadow-rose-200
                                               active:scale-95 transition-all duration-200">
                                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </div>

                            {{-- Edit Form --}}
                            <form x-show="editing" x-cloak action="{{ route('social-media.items.update', $item) }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-2 mt-2 bg-slate-50 p-3 rounded-xl border border-slate-200" style="display: none;">
                                @csrf @method('PUT')
                                <div class="flex flex-wrap gap-2 items-end">
                                    <div class="flex-1 min-w-[150px]">
                                        <label class="text-[10px] font-bold text-slate-500 uppercase mb-1">Platform Name</label>
                                        <input type="text" name="name" value="{{ $item->name }}" required class="form-input py-1.5 px-2 text-sm w-full">
                                    </div>
                                    <div class="flex-[2] min-w-[250px]">
                                        <label class="text-[10px] font-bold text-slate-500 uppercase mb-1">Icon (URL or Upload)</label>
                                        <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                                            <input type="text" name="icon_url" value="{{ $item->icon }}" class="form-input py-1.5 px-2 text-sm flex-1" placeholder="https://... or Emoji">
                                            @include('social-media._file-picker', ['name' => 'icon_file', 'accept' => 'image/*', 'placeholder' => 'Choose icon…'])
                                        </div>
                                    </div>
                                    <div class="w-24">
                                        <label class="text-[10px] font-bold text-slate-500 uppercase mb-1">Status</label>
                                        <select name="status" class="form-select py-1.5 px-2 text-sm w-full">
                                            <option value="active" {{ $item->status === 'active' ? 'selected' : '' }}>Active</option>
                                            <option value="inactive" {{ $item->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="flex gap-2 justify-end mt-2 sm:mt-0">
                                        <button type="button" @click="editing = false" class="btn btn-secondary text-xs py-1.5 px-3">Cancel</button>
                                        <button type="submit" class="btn btn-primary text-xs py-1.5 px-3">Save</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        @empty
            <div class="text-center py-12 text-slate-400 bg-white rounded-2xl border border-dashed border-slate-300">
                <span class="text-4xl block mb-2">📋</span>
                <p>No classes created yet. Use the form to create one.</p>
            </div>
        @endforelse
    </div>

    {{-- Create Class Modal --}}
    <div x-show="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-auto bg-black/50 backdrop-blur-sm" x-transition x-cloak style="display: none;">
        <div @click.away="showCreateModal = false" class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800 text-lg">Create New Class</h3>
                <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('social-media.classes.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="space-y-4">
                        <div class="flex flex-col sm:flex-row gap-4">
                            <div class="flex-1">
                                <label class="form-label">Class Name <span class="text-rose-500">*</span></label>
                                <input type="text" name="name" required class="form-input" placeholder="e.g. Acme Corporation">
                            </div>
                            <div class="flex-1">
                                <label class="form-label">Icon (URL or Upload)</label>
                                <div class="flex flex-col gap-2">
                                    <input type="url" name="icon_url" placeholder="https://..." class="form-input">
                                    @include('social-media._file-picker', ['name' => 'icon_file', 'accept' => 'image/*', 'placeholder' => 'Upload an image…'])
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-input resize-none" rows="3"></textarea>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <div class="flex-1">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select w-full">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label class="form-label">Initial Setup</label>
                                <select name="use_template" class="form-select w-full mt-1 text-sm">
                                    <option value="0">None (Empty)</option>
                                    <option value="1" selected>Template (7 default platforms)</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Assign Members (Optional)</label>
                            <div class="max-h-40 overflow-y-auto border border-slate-200 rounded-lg p-2 bg-slate-50 space-y-1">
                                @foreach($allUsers as $u)
                                    <label class="flex items-center gap-3 p-2 hover:bg-white rounded-md cursor-pointer transition-colors shadow-sm bg-slate-50">
                                        <input type="checkbox" name="user_ids[]" value="{{ $u->id }}" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                        <div class="flex items-center gap-2">
                                            <img src="{{ $u->avatar_url }}" alt="" class="w-6 h-6 rounded-full border border-slate-200">
                                            <span class="text-sm text-slate-700 font-medium">{{ $u->name }}</span>
                                        </div>
                                    </label>
                                @endforeach
                                @if($allUsers->isEmpty())
                                    <div class="p-2 text-xs text-slate-400 italic">No digital team members available.</div>
                                @endif
                            </div>
                        </div>
                        <div class="pt-2 flex justify-end gap-3">
                            <button type="button" @click="showCreateModal = false" class="btn btn-secondary">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create Class</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Manage Roles Modal --}}
    <div x-show="showRolesModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-auto bg-black/50 backdrop-blur-sm py-10" x-transition x-cloak style="display: none;">
        <div @click.away="showRolesModal = false" class="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 shrink-0">
                <div>
                    <h3 class="font-bold text-slate-800 text-lg">Digital Team Roles</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Assign module roles to Digital Team members</p>
                </div>
                <button @click="showRolesModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form action="{{ route('social-media.users.roles.bulk') }}" method="POST" class="flex flex-col flex-1 overflow-hidden">
                @csrf
                <div class="overflow-y-auto flex-1 p-0">
                    <div class="divide-y divide-slate-100">
                        @foreach($allUsers as $user)
                            @php
                                $currentSocialRole = $user->roles->first(fn($r) => strpos($r->name, 'social_') === 0)?->name ?? 'none';
                            @endphp
                            <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-10 h-10 rounded-full border border-slate-200">
                                    <div>
                                        <div class="font-bold text-sm text-slate-800">{{ $user->name }}</div>
                                        <div class="text-xs text-slate-400">
                                            @if($currentSocialRole === 'social_admin')
                                                <span class="text-indigo-600 font-semibold">Social Admin</span>
                                            @elseif($currentSocialRole === 'social_qc')
                                                <span class="text-blue-600 font-semibold">Social QC</span>
                                            @else
                                                <span class="text-slate-400">None</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <select name="roles[{{ $user->id }}]" class="form-select py-1.5 px-3 text-sm rounded-lg min-w-[140px] bg-white border-slate-200 focus:bg-white transition-colors">
                                    <option value="none" {{ $currentSocialRole === 'none' ? 'selected' : '' }}>None</option>
                                    <option value="social_admin" {{ $currentSocialRole === 'social_admin' ? 'selected' : '' }}>Social Admin</option>
                                    <option value="social_qc" {{ $currentSocialRole === 'social_qc' ? 'selected' : '' }}>Social QC</option>
                                </select>
                            </div>
                        @endforeach
                        @if($allUsers->isEmpty())
                            <div class="p-8 text-center text-slate-400 italic text-sm">
                                No active digital team members found.
                            </div>
                        @endif
                    </div>
                </div>
                <div class="p-4 border-t border-slate-100 bg-slate-50 shrink-0 flex justify-end gap-3">
                    <button type="button" @click="showRolesModal = false" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary px-6">Save All Roles</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
