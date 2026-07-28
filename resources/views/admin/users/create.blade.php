@extends('layouts.app')
@section('title', 'Add User')
@section('page_title', 'Add User')

@section('content')
<div class="max-w-lg animate-fade-in">
  <div class="mb-5">
    <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Users</a>
  </div>
  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <h2 class="font-display font-bold text-slate-800 text-lg">New User</h2>
      <p class="text-slate-400 text-sm mt-1">Create a system account and assign a role.</p>
    </div>
    <form method="POST" action="{{ route('admin.users.store') }}" class="p-6 space-y-4" enctype="multipart/form-data" x-data="{ showNew: false, showConfirm: false }">
      @csrf

      <div x-data="{ fileName: 'No file chosen' }">
        <label class="form-label">Profile Image / Avatar</label>
        <div class="flex items-center gap-3 mt-1">
          <label for="avatar-upload" class="group cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-700 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all shadow-sm">
            <svg class="w-5 h-5 text-slate-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
            Choose File
          </label>
          <span x-text="fileName" class="text-sm text-slate-500"></span>
          <input type="file" name="avatar" id="avatar-upload" class="hidden" accept="image/*" @change="fileName = $event.target.files[0] ? $event.target.files[0].name : 'No file chosen'">
        </div>
        @error('avatar')<p class="form-error mt-1">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="form-label">Full Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name') }}" class="form-input @error('name') border-red-300 @enderror" placeholder="Jane Smith" id="user-name" required>
        @error('name')<p class="form-error">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="form-label">Username (for imports) <span class="text-red-500">*</span></label>
        <input type="text" name="username" value="{{ old('username') }}" class="form-input @error('username') border-red-300 @enderror" placeholder="janesmith" id="user-username" required>
        @error('username')<p class="form-error">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="form-label">Email Address <span class="text-red-500">*</span></label>
        <input type="email" name="email" value="{{ old('email') }}" class="form-input @error('email') border-red-300 @enderror" placeholder="jane@company.com" id="user-email" required>
        @error('email')<p class="form-error">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="form-label">Role <span class="text-red-500">*</span></label>
        <select name="role" class="form-input @error('role') border-red-300 @enderror" id="user-role" required>
          <option value="">Select role…</option>
          @foreach($roles as $role)
            @if($role->name !== 'super-admin' || auth()->user()->hasRole('super-admin'))
            <option value="{{ $role->name }}" {{ old('role') === $role->name ? 'selected' : '' }}>
              {{ ucwords(str_replace(['-', '_'], ' ', $role->name)) }}
            </option>
            @endif
          @endforeach
        </select>
        @error('role')<p class="form-error">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="form-label">Team Role (optional)</label>
        <select name="team_role" class="form-input @error('team_role') border-red-300 @enderror" id="user-team-role">
          <option value="">None (Standard Member)</option>
          <option value="Graphic Head" {{ old('team_role') === 'Graphic Head' ? 'selected' : '' }}>Graphic Head</option>
          <option value="Listing Head" {{ old('team_role') === 'Listing Head' ? 'selected' : '' }}>Listing Head</option>
          <option value="Video Head" {{ old('team_role') === 'Video Head' ? 'selected' : '' }}>Video Head</option>
          <option value="QC" {{ old('team_role') === 'QC' ? 'selected' : '' }}>QC</option>
          <option value="Supervisor" {{ old('team_role') === 'Supervisor' ? 'selected' : '' }}>Supervisor</option>
        </select>
        <p class="text-xs text-slate-500 mt-1">Used for automatic assignment in Board Automations.</p>
        @error('team_role')<p class="form-error">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="form-label">CRM Role (optional)</label>
        <select name="crm_role" class="form-input @error('crm_role') border-red-300 @enderror">
          <option value="">Member (default)</option>
          <option value="supervisor" {{ old('crm_role') === 'supervisor' ? 'selected' : '' }}>Supervisor</option>
        </select>
        <p class="text-xs text-slate-500 mt-1">Only applies to the Sales/CRM role. A CRM Supervisor can edit lead/customer details and purchase history — a Member can only change status and log follow-ups.</p>
        @error('crm_role')<p class="form-error">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="form-label">Password <span class="text-red-500">*</span></label>
        <div class="relative">
          <input :type="showNew ? 'text' : 'password'" name="password" class="form-input pr-10 @error('password') border-red-300 @enderror" placeholder="Min 8 chars, mixed case + number" id="user-password" autocomplete="new-password" required>
          <button type="button" @click="showNew = !showNew" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-600">
            <svg x-show="!showNew" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <svg x-show="showNew" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
          </button>
        </div>
        @error('password')<p class="form-error">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="form-label">Confirm Password <span class="text-red-500">*</span></label>
        <div class="relative">
          <input :type="showConfirm ? 'text' : 'password'" name="password_confirmation" class="form-input pr-10" placeholder="Repeat password" id="user-password-confirm" autocomplete="new-password" required>
          <button type="button" @click="showConfirm = !showConfirm" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-600">
            <svg x-show="!showConfirm" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <svg x-show="showConfirm" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
          </button>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <input type="checkbox" name="is_active" value="1" id="user-active"
               {{ old('is_active', true) ? 'checked' : '' }} class="accent-indigo-600">
        <label for="user-active" class="text-sm text-slate-600 cursor-pointer">Account active (user can login immediately)</label>
      </div>

      <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-700">
        <strong>Password Requirements:</strong> Minimum 8 characters with at least one uppercase letter, one lowercase letter, and one number.
      </div>

      <div class="flex gap-3 pt-2">
        <a href="{{ route('admin.users.index') }}" class="btn flex-1 justify-center bg-white border border-slate-200 text-slate-700 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-colors">Cancel</a>
        <button type="submit" class="btn flex-1 justify-center bg-indigo-600 border border-indigo-600 text-white hover:bg-white hover:text-slate-900 hover:border-green-500 hover:ring-1 hover:ring-green-500 transition-colors" id="btn-create-user">Create User</button>
      </div>
    </form>
  </div>
</div>
@endsection
