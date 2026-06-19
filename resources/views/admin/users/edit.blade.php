@extends('layouts.app')
@section('title', 'Edit ' . $user->name)
@section('page_title', 'Edit User')

@section('content')
<div class="max-w-lg animate-fade-in">
  <div class="mb-5">
    <a href="{{ session('users_index_url', route('admin.users.index')) }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Users</a>
  </div>
  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100 flex items-center gap-3">
      <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="avatar avatar-sm">
      <div>
        <h2 class="font-display font-bold text-slate-800 text-lg">{{ $user->name }}</h2>
        <p class="text-slate-400 text-xs">{{ $user->email }}</p>
      </div>
    </div>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="p-6 space-y-4" enctype="multipart/form-data">
      @csrf @method('PUT')

      <div x-data="{ fileName: 'No file chosen' }">
        <label class="form-label">Profile Image / Avatar</label>
        <div class="flex flex-col gap-3 mt-1">
          <div class="flex items-center gap-3">
            <label for="avatar-upload" class="group cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-700 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all shadow-sm">
              <svg class="w-5 h-5 text-slate-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
              Choose File
            </label>
            <span x-text="fileName" class="text-sm text-slate-500"></span>
            <input type="file" name="avatar" id="avatar-upload" class="hidden" accept="image/*" @change="fileName = $event.target.files[0] ? $event.target.files[0].name : 'No file chosen'">
          </div>
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-slate-400 uppercase">OR URL:</span>
            <input type="url" name="avatar_url_input" class="form-input flex-1 text-sm" placeholder="https://example.com/avatar.jpg">
          </div>
        </div>
        @error('avatar')<p class="form-error mt-1">{{ $message }}</p>@enderror
        @error('avatar_url_input')<p class="form-error mt-1">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="form-label">Full Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-input" required>
        @error('name')<p class="form-error">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="form-label">Username (for imports) <span class="text-red-500">*</span></label>
        <input type="text" name="username" value="{{ old('username', $user->username) }}" class="form-input" required>
        @error('username')<p class="form-error">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="form-label">Email Address <span class="text-red-500">*</span></label>
        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-input" required>
        @error('email')<p class="form-error">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="form-label">Role <span class="text-red-500">*</span></label>
        <select name="role" class="form-input" required>
          @foreach($roles as $role)
            @if($role->name !== 'super-admin' || auth()->user()->hasRole('super-admin'))
            <option value="{{ $role->name }}" {{ old('role', $user->roles->first(fn($r) => strpos($r->name, 'social_') !== 0)?->name ?? $user->roles->first()?->name) === $role->name ? 'selected' : '' }}>
              {{ ucwords(str_replace(['-', '_'], ' ', $role->name)) }}
            </option>
            @endif
          @endforeach
        </select>
      </div>

      <div>
        <label class="form-label">Team Role (optional)</label>
        <select name="team_role" class="form-input" id="user-team-role">
          <option value="">None (Standard Member)</option>
          <option value="Graphic Head" {{ old('team_role', $user->team_role) === 'Graphic Head' ? 'selected' : '' }}>Graphic Head</option>
          <option value="Listing Head" {{ old('team_role', $user->team_role) === 'Listing Head' ? 'selected' : '' }}>Listing Head</option>
          <option value="Video Head" {{ old('team_role', $user->team_role) === 'Video Head' ? 'selected' : '' }}>Video Head</option>
          <option value="QC" {{ old('team_role', $user->team_role) === 'QC' ? 'selected' : '' }}>QC</option>
          <option value="Supervisor" {{ old('team_role', $user->team_role) === 'Supervisor' ? 'selected' : '' }}>Supervisor</option>
        </select>
        <p class="text-xs text-slate-500 mt-1">Used for automatic assignment in Board Automations.</p>
      </div>

      <div class="flex items-center gap-2">
        <input type="checkbox" name="is_active" value="1" id="edit-active"
               {{ old('is_active', $user->is_active) ? 'checked' : '' }}
               {{ $user->id === auth()->id() ? 'disabled' : '' }}
               class="accent-indigo-600">
        <label for="edit-active" class="text-sm text-slate-600 cursor-pointer">Account active</label>
        @if($user->id === auth()->id())
          <span class="text-xs text-slate-400">(cannot deactivate yourself)</span>
        @endif
      </div>

      <div x-data="{ showPasswordReset: false, showNew: false, showConfirm: false }" class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-500">Reset Password</p>
            <label class="relative inline-flex items-center cursor-pointer" title="Enable password reset">
              <input type="checkbox" x-model="showPasswordReset" class="sr-only peer">
              <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
            </label>
        </div>
        <div x-show="showPasswordReset" x-collapse class="space-y-3 pt-2">
          <div>
            <label class="form-label text-xs">New Password</label>
            <div class="relative">
              <input :type="showNew ? 'text' : 'password'" name="password" class="form-input text-sm pr-10" placeholder="New password…" autocomplete="new-password">
              <button type="button" @click="showNew = !showNew" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-600">
                <svg x-show="!showNew" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <svg x-show="showNew" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
              </button>
            </div>
            @error('password')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label text-xs">Confirm New Password</label>
            <div class="relative">
              <input :type="showConfirm ? 'text' : 'password'" name="password_confirmation" class="form-input text-sm pr-10" placeholder="Repeat password…" autocomplete="new-password">
              <button type="button" @click="showConfirm = !showConfirm" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-600">
                <svg x-show="!showConfirm" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <svg x-show="showConfirm" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="flex gap-3 justify-between pt-2">
        @if($user->id !== auth()->id() && ! $user->hasRole('super-admin'))
          <button type="button" onclick="confirmDelete()" class="btn bg-red-600 text-white border border-red-600 hover:bg-white hover:text-red-600 hover:border-red-600 hover:ring-1 hover:ring-red-600 transition-colors text-sm">Delete User</button>
        @else
          <div></div>
        @endif
        <div class="flex gap-3">
          <a href="{{ session('users_index_url', route('admin.users.index')) }}" class="btn justify-center bg-white border border-slate-200 text-slate-700 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-colors">Cancel</a>
          <button type="submit" class="btn justify-center bg-indigo-600 text-white border border-indigo-600 hover:bg-white hover:text-slate-900 hover:border-green-500 hover:ring-1 hover:ring-green-500 transition-colors">Save Changes</button>
        </div>
      </div>
    </form>
  </div>
</div>

@if($user->id !== auth()->id() && ! $user->hasRole('super-admin'))
  <form id="delete-user-form" method="POST" action="{{ route('admin.users.destroy', $user) }}" class="hidden">
    @csrf @method('DELETE')
  </form>

  <script>
    async function confirmDelete() {
      if (await window.confirmModal('Permanently delete {{ addslashes($user->name) }}?')) {
        document.getElementById('delete-user-form').submit();
      }
    }
  </script>
@endif
@endsection
