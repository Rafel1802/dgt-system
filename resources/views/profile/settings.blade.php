@extends('layouts.app')
@section('title', 'Settings')
@section('page_title', 'Settings')

@section('content')
<div class="max-w-2xl mx-auto space-y-6 animate-fade-in">

  {{-- Change Password --}}
  <div class="card">
    <h3 class="font-semibold text-slate-700 mb-1">Change Password</h3>

    @php $canEdit = $user->can_edit_profile || $user->hasAnyRole(['super-admin', 'admin']); @endphp

    @if(!$canEdit)
      <div class="mt-3 p-4 bg-amber-50 border border-amber-200 rounded-xl">
        <p class="text-sm font-semibold text-amber-800 flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          Password changes are disabled by the administrator.
        </p>
      </div>
    @else
      <p class="text-sm text-slate-400 mb-5">Use at least 8 characters with mixed case and a number.</p>

      @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger mb-4 text-rose-600 bg-rose-50 p-3 rounded-lg text-sm border border-rose-200">{{ session('error') }}</div>
      @endif

      <form method="POST" action="{{ route('settings.password') }}" class="space-y-4" x-data="{ showCurrent: false, showNew: false, showConfirm: false }">
        @csrf @method('PUT')

      <div>
        <label class="form-label">Current Password</label>
        <div class="relative">
          <input :type="showCurrent ? 'text' : 'password'" name="current_password" class="form-input pr-10" id="current-password" required>
          <button type="button" @click="showCurrent = !showCurrent" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-600">
            <svg x-show="!showCurrent" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <svg x-show="showCurrent" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
          </button>
        </div>
        @error('current_password')<p class="form-error">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="form-label">New Password</label>
        <div class="relative">
          <input :type="showNew ? 'text' : 'password'" name="password" class="form-input pr-10" id="new-password" required>
          <button type="button" @click="showNew = !showNew" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-600">
            <svg x-show="!showNew" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <svg x-show="showNew" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
          </button>
        </div>
        @error('password')<p class="form-error">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="form-label">Confirm New Password</label>
        <div class="relative">
          <input :type="showConfirm ? 'text' : 'password'" name="password_confirmation" class="form-input pr-10" id="confirm-password" required>
          <button type="button" @click="showConfirm = !showConfirm" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-600">
            <svg x-show="!showConfirm" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <svg x-show="showConfirm" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
          </button>
        </div>
      </div>

        <div class="flex justify-end pt-2">
          <button type="submit" class="btn btn-primary" id="btn-change-password">Update Password</button>
        </div>
      </form>
    @endif
  </div>

  {{-- Account Info (read-only) --}}
  <div class="card">
    <h3 class="font-semibold text-slate-700 mb-4">Account Information</h3>
    <div class="space-y-3 text-sm">
      <div class="flex justify-between">
        <span class="text-slate-500">Name</span>
        <span class="font-medium text-slate-700">{{ $user->name }}</span>
      </div>
      <div class="flex justify-between">
        <span class="text-slate-500">Email</span>
        <span class="font-medium text-slate-700">{{ $user->email }}</span>
      </div>
      <div class="flex justify-between">
        <span class="text-slate-500">Role</span>
        <span class="font-medium text-slate-700">{{ $user->role_display }}</span>
      </div>
      <div class="flex justify-between">
        <span class="text-slate-500">Last Login</span>
        <span class="font-medium text-slate-700">{{ $user->last_login_at?->diffForHumans() ?? 'N/A' }}</span>
      </div>
    </div>
    <div class="mt-4">
      <a href="{{ route('profile.show') }}" class="btn btn-secondary text-sm">✏️ Edit Profile</a>
    </div>
  </div>

</div>
@endsection
