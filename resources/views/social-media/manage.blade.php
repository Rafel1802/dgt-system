@extends('layouts.app')

@section('title', 'Manage Classes - Social Media')

@section('content')
<div x-data="{ showCreateModal: false, showRolesModal: false }">
    <div class="page-header flex flex-wrap gap-4 items-end justify-between mb-6">
        <div>
            <h1 class="page-title">Manage Classes</h1>
            <p class="page-subtitle">Create classes and assign members</p>
        </div>
        <div class="flex gap-3">
            <button @click="showRolesModal = true" class="btn btn-secondary text-sm">👥 Manage Team Roles</button>
            <button @click="showCreateModal = true" class="btn btn-primary text-sm">➕ Create Class</button>
            <a href="{{ route('social-media.dashboard') }}" class="btn btn-secondary text-sm">← Back</a>
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
        <div class="card" x-data="{ view: 'main', showSingleForm: false }">
            {{-- Header --}}
            <div class="card-header flex items-start justify-between gap-4">
                <div>
                    <h3 class="font-bold text-lg text-slate-800 flex items-center gap-2">
                        {{ $class->name }}
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $class->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ $class->status }}
                        </span>
                    </h3>
                    @if($class->description)
                        <p class="text-xs text-slate-500 mt-1">{{ $class->description }}</p>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="showSingleForm = !showSingleForm" class="btn btn-secondary text-xs px-2 py-1">
                        <span x-text="showSingleForm ? 'Cancel Single' : 'Create Single Social'"></span>
                    </button>

                    <div class="w-px h-4 bg-slate-200 mx-1 hidden sm:block"></div>

                    <button @click="view = (view === 'members' ? 'main' : 'members')" class="btn btn-secondary text-xs px-2 py-1 flex items-center gap-1">
                        👥 Members ({{ $class->assignedUsers->count() }})
                    </button>
                    <button @click="view = (view === 'edit' ? 'main' : 'edit')" class="btn btn-secondary text-xs px-2 py-1">
                        ✏️ Edit
                    </button>
                    <form action="{{ route('social-media.classes.destroy', $class) }}" method="POST" onsubmit="return confirm('Delete this class?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger text-xs px-2 py-1">🗑</button>
                    </form>
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
                            <div class="flex gap-1">
                                <input type="url" name="icon_url" placeholder="https://..." class="form-input py-1 text-xs flex-1">
                                <input type="file" name="icon_file" accept="image/*" class="form-input py-1 text-xs w-24">
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
                        <button type="submit" class="btn btn-primary py-1 px-3 text-sm">Save</button>
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
                            <form action="{{ route('social-media.classes.remove-user', [$class, $u]) }}" method="POST" onsubmit="return confirm('Remove user from class?')">
                                @csrf @method('DELETE')
                                <button class="text-slate-400 hover:text-rose-500 rounded-full ml-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </form>
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
                    <form action="{{ route('social-media.items.store', $class) }}" method="POST" class="flex flex-col sm:flex-row gap-2">
                        @csrf
                        <input type="text" name="name" placeholder="Platform (e.g. Facebook)" required class="form-input py-1.5 px-3 text-sm rounded-lg flex-1">
                        <input type="text" name="icon" placeholder="Icon URL or Emoji" class="form-input py-1.5 px-3 text-sm rounded-lg flex-1">
                        <input type="hidden" name="status" value="active">
                        <button type="submit" class="btn btn-primary py-1.5 px-4 text-sm w-full sm:w-auto">Add</button>
                    </form>
                </div>

                @if($class->items->isEmpty())
                    <p class="text-xs text-slate-400 text-center py-4 bg-slate-50 rounded-xl border border-dashed border-slate-200">No social media items added.</p>
                @else
                    <div class="space-y-2">
                        @foreach($class->items as $item)
                        <div x-data="{ editing: false }" class="p-3 border border-slate-100 rounded-xl bg-white shadow-sm hover:border-indigo-200 transition-colors">
                            {{-- Normal View --}}
                            <div x-show="!editing" class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="text-lg flex items-center justify-center min-w-[24px]">{!! $item->icon_html !!}</span>
                                    <div>
                                        <div class="font-bold text-sm text-slate-700">{{ $item->name }}</div>
                                        <span class="text-[10px] uppercase font-bold {{ $item->status === 'active' ? 'text-emerald-600' : 'text-slate-400' }}">{{ $item->status }}</span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="editing = true" type="button" class="btn btn-secondary text-xs py-1 px-2 flex items-center gap-1">✏️ Edit</button>
                                    <form action="{{ route('social-media.items.toggle', $item) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-secondary text-xs py-1 px-2">{{ $item->status === 'active' ? 'Disable' : 'Enable' }}</button>
                                    </form>
                                    <form action="{{ route('social-media.items.destroy', $item) }}" method="POST" onsubmit="return confirm('Delete this item?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger text-xs py-1 px-2">Delete</button>
                                    </form>
                                </div>
                            </div>

                            {{-- Edit Form --}}
                            <form x-show="editing" x-cloak action="{{ route('social-media.items.update', $item) }}" method="POST" class="flex flex-col sm:flex-row items-start sm:items-end gap-2 mt-2" style="display: none;">
                                @csrf @method('PUT')
                                <div class="flex-1 w-full">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase">Platform Name</label>
                                    <input type="text" name="name" value="{{ $item->name }}" required class="form-input py-1 px-2 text-sm w-full">
                                </div>
                                <div class="flex-1 w-full">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase">Icon URL or Emoji</label>
                                    <input type="text" name="icon" value="{{ $item->icon }}" class="form-input py-1 px-2 text-sm w-full" placeholder="Icon URL or Emoji">
                                </div>
                                <div class="w-full sm:w-24">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase">Status</label>
                                    <select name="status" class="form-select py-1 px-2 text-sm w-full">
                                        <option value="active" {{ $item->status === 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ $item->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>
                                <div class="flex gap-2 w-full sm:w-auto mt-2 sm:mt-0 justify-end">
                                    <button type="button" @click="editing = false" class="btn btn-secondary text-xs py-1.5 px-3">Cancel</button>
                                    <button type="submit" class="btn btn-primary text-xs py-1.5 px-3">Save</button>
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
                        <div class="flex gap-4">
                            <div class="flex-1">
                                <label class="form-label">Class Name <span class="text-rose-500">*</span></label>
                                <input type="text" name="name" required class="form-input" placeholder="e.g. Acme Corporation">
                            </div>
                            <div class="flex-1">
                                <label class="form-label">Icon (URL or Upload)</label>
                                <div class="flex gap-2">
                                    <input type="url" name="icon_url" placeholder="https://..." class="form-input">
                                    <input type="file" name="icon_file" accept="image/*" class="form-input">
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-input resize-none" rows="3"></textarea>
                        </div>
                        <div class="flex gap-4">
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
                                    <option value="1" selected>Template (6 default platforms)</option>
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
