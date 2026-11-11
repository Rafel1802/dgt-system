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

  {{-- Two-Factor Authentication (2FA) --}}
  <div class="card">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white flex items-center justify-center flex-shrink-0 shadow-xs">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h3 class="font-semibold text-slate-700 dark:text-slate-200">Two-Factor Authentication (2FA)</h3>
            @if($user->hasEnabledTwoFactor())
              <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-emerald-500 text-white">Active</span>
            @else
              <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">Disabled</span>
            @endif
          </div>
          <p class="text-xs text-slate-500 mt-0.5">
            @if($user->hasEnabledTwoFactor())
              Secured with authenticator PIN. Prompted at each sign in.
            @else
              Require a 6-digit TOTP code from Google Authenticator or Apple Passwords at login.
            @endif
          </p>
        </div>
      </div>
      <div>
        <a href="{{ route('profile.show') }}#two-factor-section" class="btn btn-primary text-xs inline-flex items-center gap-1.5 whitespace-nowrap">
          <span>{{ $user->hasEnabledTwoFactor() ? 'Manage 2FA' : 'Enable 2FA' }}</span>
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        </a>
      </div>
    </div>
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
  {{-- Chat Access Control (Dara & Super Admin) --}}
  @if($user->canManageChatPermissions())
  <div class="card space-y-4" x-data="{
      users: [],
      search: '',
      loading: true,
      convertingWebp: false,
      toast: '',
      init() {
          this.fetchUsers();
      },
      async fetchUsers() {
          this.loading = true;
          try {
              const res = await fetch('{{ route('chat.admin.users') }}');
              const data = await res.json();
              if (data.success) {
                  this.users = data.users;
              }
          } catch (e) {
              console.error(e);
          } finally {
              this.loading = false;
          }
      },
      get filteredUsers() {
          if (!this.search) return this.users;
          const q = this.search.toLowerCase();
          return this.users.filter(u =>
              (u.name && u.name.toLowerCase().includes(q)) ||
              (u.username && u.username.toLowerCase().includes(q)) ||
              (u.team_role && u.team_role.toLowerCase().includes(q))
          );
      },
      get enabledCount() {
          return this.users.filter(u => u.can_use_chat).length;
      },
      async toggleUser(u) {
          if (u.is_manager) return;
          const original = u.can_use_chat;
          u.can_use_chat = !original;
          try {
              const res = await fetch('{{ route('chat.admin.toggle') }}', {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/json',
                      'X-CSRF-TOKEN': '{{ csrf_token() }}'
                  },
                  body: JSON.stringify({ user_id: u.id, can_use_chat: u.can_use_chat })
              });
              const data = await res.json();
              if (!data.success) {
                  u.can_use_chat = original;
                  this.showToast(data.error || 'Failed to update', true);
              } else {
                  this.showToast(data.message || 'Updated successfully');
              }
          } catch (e) {
              u.can_use_chat = original;
              this.showToast('Network error', true);
          }
      },
      async setAll(enable) {
          if (!confirm(`Are you sure you want to turn Chat ${enable ? 'ON' : 'OFF'} for all users?`)) return;
          try {
              const res = await fetch('{{ route('chat.admin.batch-toggle') }}', {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/json',
                      'X-CSRF-TOKEN': '{{ csrf_token() }}'
                  },
                  body: JSON.stringify({ enable })
              });
              const data = await res.json();
              if (data.success) {
                  this.users.forEach(u => { if (!u.is_manager) u.can_use_chat = enable; });
                  this.showToast(data.message);
              }
          } catch (e) {
              this.showToast('Failed to update all users', true);
          }
      },
      async convertWebp() {
          this.convertingWebp = true;
          try {
              const res = await fetch('{{ route('chat.admin.convert-webp') }}', {
                  method: 'POST',
                  headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
              });
              const data = await res.json();
              if (data.success) {
                  this.showToast(data.output || 'WebP conversion completed successfully!');
              }
          } catch (e) {
              this.showToast('Conversion failed', true);
          } finally {
              this.convertingWebp = false;
          }
      },
      showToast(msg, isError = false) {
          this.toast = msg;
          setTimeout(() => this.toast = '', 4500);
      }
  }">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-cyan-500 via-blue-500 to-indigo-600 text-white flex items-center justify-center shrink-0 shadow-sm">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-.974-.948l.783-2.35A7.47 7.47 0 0 1 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
          </svg>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h3 class="font-semibold text-slate-800 dark:text-slate-100">Chat System Access Control</h3>
            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
              Dara & Super Admin
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-0.5">Control which users have access to DGT Chat menu and notifications.</p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button type="button" @click="setAll(true)" class="btn btn-secondary text-xs px-2.5 py-1">Turn All ON</button>
        <button type="button" @click="setAll(false)" class="btn btn-secondary text-xs px-2.5 py-1 text-rose-600 hover:text-rose-700">Turn All OFF</button>
      </div>
    </div>

    <!-- Storage Optimization Banner -->
    <div class="p-3 bg-gradient-to-r from-emerald-500/10 via-teal-500/10 to-indigo-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-between gap-3 text-xs">
      <div class="flex items-center gap-2">
        <span class="text-base">⚡</span>
        <div>
          <span class="font-semibold text-slate-800 dark:text-slate-100">Auto WebP Compression Active:</span>
          <span class="text-slate-500">All chat images auto-convert to WebP after 5 mins to save server disk storage.</span>
        </div>
      </div>
      <button type="button" @click="convertWebp()" :disabled="convertingWebp"
              class="btn btn-secondary text-xs py-1 px-3 whitespace-nowrap inline-flex items-center gap-1.5 font-medium">
        <span x-show="!convertingWebp">Run WebP Optimization Now</span>
        <span x-show="convertingWebp" x-cloak>Converting...</span>
      </button>
    </div>

    <!-- User Search & Stats -->
    <div class="flex items-center justify-between gap-3 pt-1">
      <div class="relative flex-1">
        <input type="text" x-model="search" placeholder="Search members by name, username, or role..."
               class="form-input text-xs pl-8 py-1.5 w-full">
        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
      <div class="text-xs text-slate-500 shrink-0">
        Enabled: <strong class="text-emerald-600 font-semibold" x-text="enabledCount"></strong> / <span x-text="users.length"></span>
      </div>
    </div>

    <!-- Users List -->
    <div class="divide-y divide-slate-100 dark:divide-slate-800 max-h-80 overflow-y-auto pr-1">
      <template x-if="loading">
        <div class="py-8 text-center text-xs text-slate-400">Loading team members...</div>
      </template>

      <template x-for="u in filteredUsers" :key="u.id">
        <div class="py-2.5 flex items-center justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <img :src="u.avatar_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(u.name)"
                 class="w-8 h-8 rounded-full object-cover shrink-0 border border-slate-200 dark:border-slate-700">
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate" x-text="u.name"></p>
                <template x-if="u.is_manager">
                  <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300">Protected</span>
                </template>
              </div>
              <p class="text-[11px] text-slate-400 truncate">
                <span x-text="'@' + u.username"></span> · <span x-text="u.team_role"></span>
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <span class="text-[11px] font-medium"
                  :class="u.can_use_chat ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'"
                  x-text="u.can_use_chat ? 'Chat ON' : 'Chat OFF'"></span>
            <button type="button"
                    @click="toggleUser(u)"
                    :disabled="u.is_manager"
                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="u.can_use_chat ? 'bg-indigo-600' : 'bg-slate-200 dark:bg-slate-700'">
              <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    :class="u.can_use_chat ? 'translate-x-4' : 'translate-x-0'"></span>
            </button>
          </div>
        </div>
      </template>

      <template x-if="!loading && filteredUsers.length === 0">
        <div class="py-6 text-center text-xs text-slate-400">No matching members found.</div>
      </template>
    </div>

    <!-- Dynamic Toast Notification -->
    <div x-show="toast" x-cloak x-transition
         class="p-2.5 rounded-xl bg-slate-900 text-white text-xs text-center font-medium shadow-lg"
         x-text="toast"></div>
  </div>
  @endif

</div>
@endsection
