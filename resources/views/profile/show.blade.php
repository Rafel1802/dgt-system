@extends('layouts.app')
@section('title', 'My Profile')
@section('page_title', 'My Profile')

@php
  $canEdit = $user->can_edit_profile || $user->hasAnyRole(['super-admin', 'admin']);
  $rawAvatar = trim((string) ($user->avatar ?? ''));
  $remoteAvatar = \Illuminate\Support\Str::startsWith($rawAvatar, ['http://', 'https://'])
      ? $rawAvatar
      : '';
  $initialsAvatar = \App\Models\User::initialsAvatarDataUri($user->name ?: $user->email ?: 'User', $user->avatar_color);
  $avatarUrlValue = old('avatar_url', $remoteAvatar);
  $removeAvatar = old('remove_avatar') === '1';
  $previewAvatar = $removeAvatar ? $initialsAvatar : ($avatarUrlValue ?: $user->avatar_url);

  $lockedSetting = \App\Models\Setting::where('key', 'lock_profile_images')->value('value');
  $isLocked = in_array(strtolower((string)$lockedSetting), ['1', 'true', 'yes']);
  $canChangeAvatar = !$isLocked || $user->hasAnyRole(['super-admin']);
@endphp

@section('content')
<div
  class="profile-container animate-fade-in"
  x-data="window.profileManager()"
>
  {{-- ── 1. Profile Center Header Banner (Hero at Top) ─────────────────────── --}}
  <div class="mb-6 relative overflow-hidden rounded-[2rem] bg-gradient-to-r from-blue-700 via-indigo-700 to-slate-900 p-6 sm:p-8 text-white shadow-2xl shadow-indigo-950/20">
    {{-- Ambient light orbs --}}
    <div class="absolute -right-20 -top-20 w-80 h-80 rounded-full bg-blue-500/20 blur-3xl pointer-events-none"></div>
    <div class="absolute -left-20 -bottom-20 w-80 h-80 rounded-full bg-indigo-500/20 blur-3xl pointer-events-none"></div>

    <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
      {{-- Identity info --}}
      <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
        <div class="profile-banner-avatar-wrap">
          <img
            :src="previewUrl"
            src="{{ $previewAvatar }}"
            alt="{{ $user->name }}"
            data-avatar-name="{{ $user->name }}"
            data-avatar-color="{{ $user->avatar_color }}"
            class="profile-banner-avatar-img"
            x-on:error="previewUrl = fallbackUrl"
          >
        </div>
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-[0.2em] bg-white/15 text-blue-100 backdrop-blur-md border border-white/20">
              <svg class="w-3 h-3 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
              </svg>
              Profile Center
            </span>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-500/30 text-indigo-100 border border-indigo-300/30">
              {{ $user->role_display }}
            </span>
          </div>
          <h2 class="mt-2.5 font-display text-2xl sm:text-3xl lg:text-4xl font-black leading-tight text-white tracking-tight">
            {{ $user->name }}
          </h2>
          <p class="mt-1 text-xs sm:text-sm font-medium text-blue-100/80 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-blue-200/80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Member since {{ $user->created_at->format('M Y') }}
          </p>
        </div>
      </div>

      {{-- Email & Status badges (Single-line email with Copy button) --}}
      <div class="flex flex-wrap sm:flex-nowrap items-stretch gap-3 shrink-0">
        {{-- Email Pill --}}
        <div class="flex-1 sm:flex-initial min-w-[240px] rounded-2xl border border-white/20 bg-white/15 px-5 py-3.5 backdrop-blur-md shadow-lg shadow-black/5 hover:bg-white/20 transition-all">
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-1.5">
              <svg class="h-3.5 w-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
              </svg>
              <span class="text-[11px] font-black uppercase tracking-wider text-blue-100">Email</span>
            </div>
            <button type="button"
                    onclick="navigator.clipboard.writeText('{{ $user->email }}'); if(window.toast){ toast.success('Email copied to clipboard!'); }"
                    class="text-[10px] font-bold text-blue-200 hover:text-white px-2 py-0.5 rounded-md bg-white/10 hover:bg-white/25 transition cursor-pointer select-none"
                    title="Copy email to clipboard">
              Copy
            </button>
          </div>
          <p class="mt-1 whitespace-nowrap font-bold text-white text-sm sm:text-base tracking-tight select-all">
            {{ $user->email }}
          </p>
        </div>

        {{-- Status Pill --}}
        <div class="flex-1 sm:flex-initial min-w-[150px] rounded-2xl border border-white/20 bg-white/15 px-5 py-3.5 backdrop-blur-md shadow-lg shadow-black/5 hover:bg-white/20 transition-all">
          <div class="flex items-center gap-1.5">
            <span class="relative flex h-2 w-2">
              @if($user->is_active)
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-300 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
              @else
                <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-400"></span>
              @endif
            </span>
            <span class="text-[11px] font-black uppercase tracking-wider text-blue-100">Status</span>
          </div>
          <p class="mt-1 whitespace-nowrap font-bold text-white text-sm sm:text-base flex items-center gap-1.5">
            {{ $user->is_active ? 'Active account' : 'Inactive account' }}
          </p>
        </div>
      </div>
    </div>
  </div>

  {{-- ── 2. Alerts (Success / Validation Errors) ─────────────────────────────── --}}
  @if(session('success'))
    <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-950/40 dark:border-emerald-800 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200 flex items-center gap-3">
      <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  @if($errors->any())
    <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 dark:bg-rose-950/40 dark:border-rose-800 p-4 text-sm text-rose-800 dark:text-rose-200">
      <div class="flex items-center gap-2 font-bold">
        <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <span>Please review the profile details below:</span>
      </div>
      <ul class="mt-2 list-disc space-y-1 pl-7 text-xs sm:text-sm">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- ── 3. Main Two-Column Layout (Left: Photo Studio | Right: Security & Settings) ── --}}
  <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="profile-grid-layout">
    @csrf
    @method('PUT')
    <input type="hidden" name="remove_avatar" :value="removeAvatar ? '1' : '0'">

    {{-- ── LEFT PANEL: Profile Photo Studio (320px strictly constrained) ─────── --}}
    <aside class="profile-avatar-card card p-0 rounded-3xl border border-slate-200/90 dark:border-slate-800 shadow-xl shadow-slate-900/5 bg-white dark:bg-slate-900 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto profile-panel-scroll flex flex-col">
      {{-- Sticky top header --}}
      <div class="sticky top-0 z-10 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-100 dark:border-slate-800 px-5 py-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
            </svg>
          </span>
          <h3 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base">Profile Photo</h3>
        </div>
        <span class="rounded-full bg-blue-50 dark:bg-blue-950/60 border border-blue-200/60 dark:border-blue-800 px-2.5 py-0.5 text-[10px] font-black uppercase text-[#2F68ED]">Live</span>
      </div>

      {{-- Scrollable content of left aside --}}
      <div class="p-5 sm:p-6 space-y-5 flex-1">
        {{-- Avatar Preview Box (Strictly locked 240px x 240px) --}}
        <div class="profile-avatar-preview-wrap group">
          <img
            :src="previewUrl"
            src="{{ $previewAvatar }}"
            alt="{{ $user->name }}"
            data-avatar-name="{{ $user->name }}"
            data-avatar-color="{{ $user->avatar_color }}"
            class="profile-avatar-preview-img"
            x-on:error="previewUrl = fallbackUrl"
          >
          <span class="absolute bottom-3 left-3 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-black/60 text-white backdrop-blur-md shadow-md"
                x-text="removeAvatar ? 'Initials' : (fileName ? 'File Ready' : (avatarUrl ? 'URL Image' : 'Active Avatar'))">
          </span>
        </div>

        {{-- Action Buttons --}}
        <div class="space-y-2.5">
          @if($canChangeAvatar)
            <label for="avatar" class="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:scale-[0.99] text-white font-bold text-xs sm:text-sm shadow-md shadow-indigo-600/20 transition-all cursor-pointer select-none">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
              </svg>
              Choose Image File
            </label>
            <input
              x-ref="avatarFile"
              id="avatar"
              type="file"
              name="avatar"
              class="sr-only"
              accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
              @change="setFile($event)"
            >

            <button
              type="button"
              class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-2xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs transition-all cursor-pointer select-none"
              @click="useInitials()"
            >
              <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
              </svg>
              Use Initials Avatar
            </button>

            <div class="pt-2">
              <label for="avatar_url" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Direct Image URL</label>
              <div class="flex gap-1.5">
                <input
                  id="avatar_url"
                  type="url"
                  name="avatar_url"
                  x-model="avatarUrl"
                  placeholder="https://example.com/avatar.jpg"
                  class="flex-1 min-w-0 text-xs py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-850 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition"
                >
                <button
                  type="button"
                  @click="setUrl()"
                  class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs transition cursor-pointer"
                >
                  Apply
                </button>
              </div>
            </div>
          @else
            <div class="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-xs text-center leading-relaxed">
              Profile images are currently locked by the administrator.
            </div>
          @endif
        </div>

        {{-- Account Quick Info Card --}}
        <div class="p-4 rounded-2xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400 space-y-2.5">
          <div class="flex items-center justify-between">
            <span class="font-bold text-slate-700 dark:text-slate-300">Account ID</span>
            <span class="font-mono text-slate-500 font-semibold">#{{ $user->id }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="font-bold text-slate-700 dark:text-slate-300">Role</span>
            <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ $user->role_display }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="font-bold text-slate-700 dark:text-slate-300">Status</span>
            <span class="inline-flex items-center gap-1 font-bold {{ $user->is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
              <span class="w-1.5 h-1.5 rounded-full {{ $user->is_active ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
              {{ $user->is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <div class="flex items-center justify-between pt-1 border-t border-slate-200/50 dark:border-slate-700/50">
            <span class="font-bold text-slate-700 dark:text-slate-300">Joined</span>
            <span class="text-slate-500">{{ $user->created_at->format('M d, Y') }}</span>
          </div>
        </div>
      </div>
    </aside>

    {{-- ── RIGHT PANEL: Security, Personal Info & Preferences ─────────────────── --}}
    <section class="card p-0 rounded-3xl border border-slate-200/90 dark:border-slate-800 shadow-xl shadow-slate-900/5 bg-white dark:bg-slate-900 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto profile-panel-scroll flex flex-col">
      {{-- Sticky top header --}}
      <div class="sticky top-0 z-20 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-100 dark:border-slate-800 px-6 py-5 flex items-center justify-between shadow-xs">
        <div>
          <div class="flex items-center gap-2">
            <h3 class="font-display text-xl sm:text-2xl font-black text-slate-900 dark:text-white">Profile & Security Settings</h3>
            <span class="rounded-full bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800 px-2.5 py-0.5 text-[10px] font-black uppercase text-indigo-600 dark:text-indigo-400">Settings</span>
          </div>
          <p class="mt-0.5 text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400">Manage your credentials, authentication protection, and preferences.</p>
        </div>
        <div class="hidden sm:flex items-center gap-2">
          <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-800/80 px-3 py-1 rounded-full">
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
            Scroll to navigate
          </span>
        </div>
      </div>

      {{-- Scrollable body container --}}
      <div class="p-6 sm:p-8 space-y-8 flex-1">
        
        {{-- ── SECTION 1: Security & Connected Accounts (SaaS Standard) ───────── --}}
        <div>
          <div class="flex items-center gap-2 pb-3 mb-5 border-b border-slate-100 dark:border-slate-800">
            <div class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
              </svg>
            </div>
            <div>
              <h4 class="font-bold text-base text-slate-900 dark:text-white">Security & Connected Accounts</h4>
              <p class="text-xs text-slate-500 dark:text-slate-400">OAuth single sign-on and two-factor authentication</p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            {{-- Google Account Card --}}
            <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex flex-col justify-between gap-4 transition hover:border-slate-300 dark:hover:border-slate-700">
              <div>
                <div class="flex items-center justify-between gap-2">
                  <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center flex-shrink-0 shadow-2xs">
                      <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17Z"/>
                        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24Z"/>
                        <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.98 0 12s.45 3.82 1.25 5.42l4.03-3.15Z"/>
                        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98Z"/>
                      </svg>
                    </div>
                    <div>
                      <h5 class="font-bold text-sm text-slate-900 dark:text-white">Google Account</h5>
                    </div>
                  </div>
                  @if($user->isGoogleLinked())
                    <span class="inline-flex items-center gap-1 text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                      <svg class="w-3 h-3 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                      Linked
                    </span>
                  @else
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-slate-200/80 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                      Not Linked
                    </span>
                  @endif
                </div>

                <p class="text-xs text-slate-600 dark:text-slate-400 mt-2.5 leading-relaxed">
                  @if($user->isGoogleLinked())
                    Connected with <strong class="text-slate-900 dark:text-white font-semibold">{{ $user->google_email ?: $user->email }}</strong> for 1-click login and Google Drive sync.
                  @else
                    Link your @kiuq.com Google account to sign in with one click and access Google Drive video features.
                  @endif
                </p>
              </div>

              <div class="pt-3 border-t border-slate-200/60 dark:border-slate-800/80 flex items-center justify-between gap-2">
                <span class="text-[11px] text-slate-400 font-medium">OAuth 2.0</span>
                @if($user->isGoogleLinked())
                  <button type="button"
                          @click="unlinkGoogleModalOpen = true"
                          class="px-3.5 py-1.5 rounded-xl border border-rose-200 dark:border-rose-900/60 bg-white dark:bg-slate-800 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-bold transition shadow-2xs cursor-pointer">
                    Unlink
                  </button>
                @else
                  <button type="button"
                          id="btn-link-google"
                          onclick="triggerGoogleProfileLink()"
                          class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 text-slate-800 dark:text-slate-200 text-xs font-bold transition shadow-2xs cursor-pointer">
                    <span id="btn-link-google-text">Connect Google</span>
                  </button>
                @endif
              </div>
            </div>

            {{-- Two-Factor Authentication Card --}}
            <div id="two-factor-section"
                 class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex flex-col justify-between gap-4 transition hover:border-slate-300 dark:hover:border-slate-700">
              <div>
                <div class="flex items-center justify-between gap-2">
                  <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800 flex items-center justify-center flex-shrink-0 shadow-2xs">
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                      </svg>
                    </div>
                    <div>
                      <h5 class="font-bold text-sm text-slate-900 dark:text-white">Two-Factor Auth</h5>
                    </div>
                  </div>
                  <span x-show="enabled"
                        style="{{ $user->hasEnabledTwoFactor() ? '' : 'display: none;' }}"
                        class="inline-flex items-center gap-1 text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Active
                  </span>
                  <span x-show="!enabled"
                        style="{{ !$user->hasEnabledTwoFactor() ? '' : 'display: none;' }}"
                        class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-slate-200/80 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                    Disabled
                  </span>
                </div>

                <p class="text-xs text-slate-600 dark:text-slate-400 mt-2.5 leading-relaxed">
                  <span x-show="enabled" style="{{ $user->hasEnabledTwoFactor() ? '' : 'display: none;' }}">
                    Protected with 6-digit authenticator codes (Google Authenticator, Apple, Authy).
                  </span>
                  <span x-show="!enabled" style="{{ !$user->hasEnabledTwoFactor() ? '' : 'display: none;' }}">
                    Add an extra security layer. Requires your password plus a 6-digit authenticator code to sign in.
                  </span>
                </p>
              </div>

              <div class="pt-3 border-t border-slate-200/60 dark:border-slate-800/80 flex items-center justify-between gap-2">
                <span class="text-[11px] text-slate-400 font-medium">TOTP / AES-256</span>
                <div x-show="!enabled" style="{{ !$user->hasEnabledTwoFactor() ? '' : 'display: none;' }}">
                  <button type="button"
                          id="btn-turn-on-2fa"
                          @click="startSetup()"
                          class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs cursor-pointer">
                    Enable 2FA
                  </button>
                </div>
                <div x-show="enabled" style="{{ $user->hasEnabledTwoFactor() ? '' : 'display: none;' }}" class="flex items-center gap-2">
                  <button type="button"
                          @click="openRecoveryModal()"
                          class="px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 text-slate-700 dark:text-slate-300 text-xs font-bold transition shadow-2xs cursor-pointer">
                    Codes
                  </button>
                  <button type="button"
                          id="btn-turn-off-2fa"
                          @click="openDisableModal()"
                          class="px-3 py-1.5 rounded-xl border border-rose-200 dark:border-rose-900/60 bg-white dark:bg-slate-800 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-bold transition shadow-2xs cursor-pointer">
                    Turn Off
                  </button>
                </div>
              </div>
            </div>

          </div>
        </div>

        {{-- ── SECTION 2: Personal & Contact Information ─────────────────────── --}}
        <div>
          <div class="flex items-center gap-2 pb-3 mb-5 border-b border-slate-100 dark:border-slate-800">
            <div class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div>
              <h4 class="font-bold text-base text-slate-900 dark:text-white">Personal Information</h4>
              <p class="text-xs text-slate-500 dark:text-slate-400">Your display identity and contact details</p>
            </div>
          </div>

          <div class="grid gap-5 sm:grid-cols-2">
            <div>
              <label for="profile-name" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">Full Name</label>
              <input
                id="profile-name"
                type="text"
                name="name"
                value="{{ old('name', $user->name) }}"
                class="w-full text-sm py-2.5 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/50 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-850 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition @error('name') border-rose-500 @enderror"
                required
              >
              @error('name')<p class="text-xs text-rose-600 mt-1 font-semibold">{{ $message }}</p>@enderror
            </div>

            <div>
              <label for="profile-email" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                Email Address @if(!$canEdit)<span class="text-[10px] text-rose-500 font-normal self-center lowercase">(locked)</span>@endif
              </label>
              <input
                id="profile-email"
                type="email"
                name="email"
                x-model="currentEmail"
                class="w-full text-sm py-2.5 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/50 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-850 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition @error('email') border-rose-500 @enderror @if(!$canEdit) opacity-60 cursor-not-allowed @endif"
                @if(!$canEdit) disabled @endif
                required
              >
              @error('email')<p class="text-xs text-rose-600 mt-1 font-semibold">{{ $message }}</p>@enderror
            </div>

            <div x-show="isChangingEmail" x-cloak class="sm:col-span-2 transition-all duration-300 ease-in-out">
              <label for="current_password" class="block text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 mb-1.5 flex items-center gap-1.5">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                Verify Current Password (Required to change email)
              </label>
              <div class="relative">
                <input
                  id="current_password"
                  :type="showCurrentPassword ? 'text' : 'password'"
                  name="current_password"
                  class="w-full text-sm py-2.5 pr-10 pl-3.5 rounded-xl border border-amber-300 dark:border-amber-700 bg-amber-50/50 dark:bg-amber-950/20 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-850 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition @error('current_password') border-rose-500 @enderror"
                  :required="isChangingEmail"
                  placeholder="Enter your current password to confirm email change"
                >
                <button type="button" @click="showCurrentPassword = !showCurrentPassword" class="absolute inset-y-0 right-0 px-3 flex items-center text-amber-500 hover:text-amber-700">
                  <svg x-show="!showCurrentPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  <svg x-show="showCurrentPassword" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                </button>
              </div>
              @error('current_password')<p class="text-xs text-rose-600 mt-1 font-semibold">{{ $message }}</p>@enderror
            </div>

            <div>
              <label for="profile-phone" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">Phone Number</label>
              <input
                id="profile-phone"
                type="text"
                name="phone"
                value="{{ old('phone', $user->phone) }}"
                class="w-full text-sm py-2.5 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/50 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-850 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition @error('phone') border-rose-500 @enderror"
                placeholder="e.g. +855 12 345 678"
              >
              @error('phone')<p class="text-xs text-rose-600 mt-1 font-semibold">{{ $message }}</p>@enderror
            </div>

            <div>
              <label for="profile-whatsapp" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">WhatsApp Number</label>
              <input
                id="profile-whatsapp"
                type="text"
                name="whatsapp"
                value="{{ old('whatsapp', $user->whatsapp) }}"
                class="w-full text-sm py-2.5 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/50 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-850 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition @error('whatsapp') border-rose-500 @enderror"
                placeholder="e.g. +855 12 345 678"
              >
              <p class="mt-1 text-[11px] font-medium text-slate-400">Leave blank to use the phone number above.</p>
              @error('whatsapp')<p class="text-xs text-rose-600 mt-1 font-semibold">{{ $message }}</p>@enderror
            </div>
          </div>
        </div>

        {{-- ── SECTION 3: Notification Sound Preferences ─────────────────────── --}}
        <div class="border-t border-slate-100 dark:border-slate-800 pt-6" x-data="{ 
          currentSound: '{{ old('notification_sound', $user->notification_sound ?? '01.mp3') }}',
          soundMuted: localStorage.getItem('dgt_notifications_muted') === 'true',
          toggleMute() {
            this.soundMuted = !this.soundMuted;
            localStorage.setItem('dgt_notifications_muted', this.soundMuted);
            if (!this.soundMuted) {
              const audio = document.getElementById('notif-sound');
              if (audio) { audio.currentTime = 0; audio.volume = 1.0; audio.play().catch(() => {}); }
            }
          }
        }">
          <div class="flex items-start justify-between mb-4">
            <div>
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                </div>
                <div>
                  <h4 class="font-bold text-base text-slate-900 dark:text-white">Notification Sounds</h4>
                  <p class="text-xs text-slate-500 dark:text-slate-400">Audio chime played when you receive an in-app notification</p>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2.5">
              {{-- Mute Toggle --}}
              <button type="button"
                @click="toggleMute()"
                :class="soundMuted ? 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600' : 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'"
                class="flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl border transition-all select-none cursor-pointer"
              >
                <svg x-show="!soundMuted" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" />
                </svg>
                <svg x-show="soundMuted" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 9.75 19.5 12m0 0 2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25m-10.5-6 4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                </svg>
                <span x-text="soundMuted ? 'Muted' : 'Sound On'"></span>
              </button>
              @hasrole('super-admin')
              <button type="button" @click="$refs.newSoundInput.click()" class="btn justify-center bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 hover:text-indigo-700 dark:hover:text-indigo-400 text-xs py-1.5 px-3 rounded-xl flex-shrink-0 transition-colors shadow-xs">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Sound
              </button>
              @endhasrole
            </div>
          </div>
          
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" :class="soundMuted ? 'opacity-50 pointer-events-none' : ''">
            @foreach($sounds as $sound)
            <label class="relative flex cursor-pointer rounded-2xl border p-3 focus:outline-none group transition-all"
                   :class="currentSound === '{{ $sound }}' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-300 dark:border-indigo-700 ring-2 ring-indigo-400/40 shadow-xs' : 'bg-white dark:bg-slate-850 border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'">
              <input type="radio" name="notification_sound" value="{{ $sound }}" class="sr-only" 
                     x-model="currentSound"
                     @change="
                        const audio = new Audio('{{ asset('notificationsound/' . $sound) }}');
                        audio.play();
                     ">
              <span class="flex flex-1 flex-col">
                <span class="block text-sm font-semibold truncate" :class="currentSound === '{{ $sound }}' ? 'text-indigo-900 dark:text-indigo-200 font-bold' : 'text-slate-900 dark:text-slate-100'">
                  {{ Str::beforeLast($sound, '.') }}
                </span>
                <span class="mt-1 flex items-center text-xs" :class="currentSound === '{{ $sound }}' ? 'text-indigo-700 dark:text-indigo-300 font-medium' : 'text-slate-500 dark:text-slate-400'">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 mr-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" />
                  </svg>
                  Preview
                </span>
              </span>
              <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400 shrink-0" :class="currentSound === '{{ $sound }}' ? 'opacity-100' : 'opacity-0'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
              </svg>
              @hasrole('super-admin')
              <button type="button" 
                      class="absolute -top-2 -right-2 bg-white dark:bg-slate-800 rounded-full p-1 shadow-sm border border-slate-200 dark:border-slate-700 text-slate-400 hover:text-red-500 hover:border-red-200 hover:bg-red-50 opacity-0 group-hover:opacity-100 transition-all z-10"
                      title="Remove Sound"
                      onclick="event.preventDefault(); event.stopPropagation(); if(confirm('Are you sure you want to completely remove this sound?')) { const f = document.getElementById('delete-sound-form'); f.action = '{{ route('profile.sound.delete', $sound) }}'; f.submit(); }">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
              </button>
              @endhasrole
            </label>
            @endforeach
            @if($sounds->isEmpty())
                <div class="col-span-full text-sm text-slate-500 italic py-2">
                    No sounds available in the public/notificationsound directory.
                </div>
            @endif
          </div>
          @error('notification_sound')<p class="text-xs text-rose-600 mt-1 font-semibold">{{ $message }}</p>@enderror
        </div>
        
        {{-- ── SECTION 4: Shift Clock Alarms ─────────────────────────────────── --}}
        <div class="border-t border-slate-100 dark:border-slate-800 pt-6" x-data="{
          activeShiftTab: 'lunch', // 'lunch' (12 PM), 'offwork' (4 PM), 'sat' (Sat 11 AM)
          lunchAlarmEnabled: {{ old('lunch_alarm_enabled', $user->isLunchAlarmEnabled()) ? 'true' : 'false' }},
          lunchAlarmSound: '{{ old('lunch_alarm_sound', $user->lunch_alarm_sound ?? 'lunch.wav') }}',
          offworkAlarmSound: '{{ old('offwork_alarm_sound', $user->offwork_alarm_sound ?? 'funny.wav') }}',
          satAlarmSound: '{{ old('sat_alarm_sound', $user->sat_alarm_sound ?? 'funny.wav') }}',
          alarmDuration: {{ (int) \App\Models\Setting::get('shift_alarm_duration', 15) }},
          savingDuration: false,
          durationSaved: false,
          durationError: '',
          previewAudio: null,
          previewPlaying: false,
          previewFile: '',

          getCurrentSound() {
            if (this.activeShiftTab === 'lunch') return this.lunchAlarmSound;
            if (this.activeShiftTab === 'offwork') return this.offworkAlarmSound;
            return this.satAlarmSound;
          },

          setCurrentSound(file) {
            if (this.activeShiftTab === 'lunch') this.lunchAlarmSound = file;
            else if (this.activeShiftTab === 'offwork') this.offworkAlarmSound = file;
            else this.satAlarmSound = file;
            this.playPreview(file);
          },

          isSoundSelected(file) {
            return this.getCurrentSound() === file;
          },

          playPreview(file) {
            if (this.previewAudio) {
              this.previewAudio.pause();
              this.previewAudio = null;
            }
            if (this.previewPlaying && this.previewFile === file) {
              this.previewPlaying = false;
              this.previewFile = '';
              return;
            }
            this.previewFile = file;
            this.previewPlaying = true;
            this.previewAudio = new Audio('{{ asset('clocksound') }}/' + file);
            this.previewAudio.onended = () => {
              this.previewPlaying = false;
              this.previewFile = '';
            };
            this.previewAudio.onerror = () => {
              this.previewPlaying = false;
              this.previewFile = '';
            };
            this.previewAudio.play().catch(() => {
              this.previewPlaying = false;
              this.previewFile = '';
            });
          },

          async saveAlarmDuration() {
            this.savingDuration = true;
            this.durationSaved = false;
            this.durationError = '';
            try {
              const res = await fetch('{{ route('profile.clock-sound.duration') }}', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': '{{ csrf_token() }}',
                  'Accept': 'application/json'
                },
                body: JSON.stringify({ shift_alarm_duration: this.alarmDuration })
              });
              const data = await res.json();
              if (res.ok && data.success) {
                this.durationSaved = true;
                setTimeout(() => { this.durationSaved = false; }, 2500);
              } else {
                this.durationError = data.message || 'Failed to save';
              }
            } catch (e) {
              this.durationError = 'Network error';
            } finally {
              this.savingDuration = false;
            }
          }
        }">
          <input type="hidden" name="lunch_alarm_enabled" :value="lunchAlarmEnabled ? '1' : '0'">
          <input type="hidden" name="lunch_alarm_sound" :value="lunchAlarmSound">
          <input type="hidden" name="offwork_alarm_sound" :value="offworkAlarmSound">
          <input type="hidden" name="sat_alarm_sound" :value="satAlarmSound">

          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <h4 class="font-bold text-base text-slate-900 dark:text-white">Shift Clock Alarms</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400">Scheduled workplace alerts for lunch, end of workday, and Saturday shifts</p>
              </div>
            </div>

            <div class="flex items-center gap-2">
              @hasrole('super-admin')
              <button type="button" @click="$refs.newClockSoundInput.click()" class="btn justify-center bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-amber-50 dark:hover:bg-amber-950/40 hover:text-amber-700 dark:hover:text-amber-400 text-xs py-1.5 px-3 rounded-xl flex-shrink-0 transition-colors shadow-xs">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Ringtone
              </button>
              @endhasrole
            </div>
          </div>

          {{-- Shift Tabs: Lunch (12:00 PM), Off Work (4:00 PM), Sat (11:00 AM) --}}
          <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-700 mb-4 pb-2">
            <button type="button"
                    @click="activeShiftTab = 'lunch'"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                    :class="activeShiftTab === 'lunch'
                      ? 'bg-amber-500 text-white shadow-xs'
                      : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'">
              <span>🍽️</span>
              <span>12:00 PM Lunch</span>
            </button>
            <button type="button"
                    @click="activeShiftTab = 'offwork'"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                    :class="activeShiftTab === 'offwork'
                      ? 'bg-emerald-600 text-white shadow-xs'
                      : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'">
              <span>🎉</span>
              <span>4:00 PM Off Work</span>
            </button>
            <button type="button"
                    @click="activeShiftTab = 'sat'"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5"
                    :class="activeShiftTab === 'sat'
                      ? 'bg-teal-600 text-white shadow-xs'
                      : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'">
              <span>☀️</span>
              <span>Sat 11:00 AM</span>
            </button>
          </div>

          {{-- Sound Selection Grid --}}
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            @foreach($clockSounds as $cSound)
            <div class="relative flex flex-col justify-between rounded-2xl border p-3 group transition-all cursor-pointer"
                 :class="isSoundSelected('{{ $cSound }}')
                   ? (activeShiftTab === 'lunch' ? 'bg-amber-50/80 dark:bg-amber-950/40 border-amber-300 dark:border-amber-700 ring-2 ring-amber-400/40 shadow-xs' : (activeShiftTab === 'offwork' ? 'bg-emerald-50/80 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-700 ring-2 ring-emerald-400/40 shadow-xs' : 'bg-teal-50/80 dark:bg-teal-950/40 border-teal-300 dark:border-teal-700 ring-2 ring-teal-400/40 shadow-xs'))
                   : 'bg-white dark:bg-slate-850 border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'"
                 @click="setCurrentSound('{{ $cSound }}')">
              <div>
                <span class="block text-sm font-semibold truncate"
                      :class="isSoundSelected('{{ $cSound }}') ? 'text-slate-900 dark:text-white font-bold' : 'text-slate-700 dark:text-slate-200'">
                  {{ Str::beforeLast($cSound, '.') }}
                </span>
                
                {{-- Badges for currently assigned shifts --}}
                <div class="flex flex-wrap gap-1 mt-1.5">
                  <span x-show="lunchAlarmSound === '{{ $cSound }}'"
                        class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-black bg-amber-100 dark:bg-amber-900/60 text-amber-900 dark:text-amber-200 border border-amber-300/80 dark:border-amber-700 shadow-2xs"
                        title="Assigned to 12:00 PM Lunch">
                    🍽️ 12 PM
                  </span>
                  <span x-show="offworkAlarmSound === '{{ $cSound }}'"
                        class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-black bg-emerald-100 dark:bg-emerald-900/60 text-emerald-900 dark:text-emerald-200 border border-emerald-300/80 dark:border-emerald-700 shadow-2xs"
                        title="Assigned to 4:00 PM Off Work">
                    🎉 4 PM
                  </span>
                  <span x-show="satAlarmSound === '{{ $cSound }}'"
                        class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-black bg-teal-100 dark:bg-teal-900/60 text-teal-900 dark:text-teal-200 border border-teal-300/80 dark:border-teal-700 shadow-2xs"
                        title="Assigned to Saturday 11:00 AM">
                    ☀️ Sat 11 AM
                  </span>
                </div>
              </div>

              {{-- Play Preview Button --}}
              <div class="mt-3 flex items-center justify-between">
                <button type="button"
                        @click.stop="playPreview('{{ $cSound }}')"
                        class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-lg transition-colors cursor-pointer"
                        :class="previewPlaying && previewFile === '{{ $cSound }}'
                          ? (activeShiftTab === 'lunch' ? 'bg-amber-600 text-white' : (activeShiftTab === 'offwork' ? 'bg-emerald-600 text-white' : 'bg-teal-600 text-white'))
                          : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200'">
                  <template x-if="previewPlaying && previewFile === '{{ $cSound }}'">
                    <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 9v6m4-6v6" />
                    </svg>
                  </template>
                  <template x-if="!(previewPlaying && previewFile === '{{ $cSound }}')">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                    </svg>
                  </template>
                  <span x-text="previewPlaying && previewFile === '{{ $cSound }}' ? 'Stop' : 'Play'"></span>
                </button>

                <div class="w-4 h-4 flex items-center justify-center">
                  <svg x-show="isSoundSelected('{{ $cSound }}')"
                       class="h-4 w-4"
                       :class="activeShiftTab === 'lunch' ? 'text-amber-600 dark:text-amber-400' : (activeShiftTab === 'offwork' ? 'text-emerald-600 dark:text-emerald-400' : 'text-teal-600 dark:text-teal-400')"
                       viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                  </svg>
                </div>
              </div>

              {{-- Delete Clock Sound (QC, Super Admin, Supervisor) --}}
              @if($user->canManageClockSounds())
              <button type="button" 
                      class="absolute -top-2 -right-2 bg-white dark:bg-slate-800 rounded-full p-1 shadow-md border border-slate-200 dark:border-slate-700 text-slate-400 hover:text-red-600 hover:border-red-300 hover:bg-red-50 opacity-0 group-hover:opacity-100 transition-all z-10"
                      title="Remove Clock Ringtone"
                      onclick="event.preventDefault(); event.stopPropagation(); if(confirm('Are you sure you want to delete this clock ringtone?')) { const f = document.getElementById('delete-clock-sound-form'); f.action = '{{ route('profile.clock-sound.delete', $cSound) }}'; f.submit(); }">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
              </button>
              @endif
            </div>
            @endforeach
          </div>

          {{-- Alarm Ring Duration Setting (QC, Super Admin, Supervisor) --}}
          @if($user->canManageClockSounds())
          <div class="mt-5 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <span class="block text-xs font-bold text-slate-900 dark:text-white">Global Alarm Ring Duration</span>
              <span class="block text-[11px] text-slate-500">How many seconds the alarm audio plays when ringing (3 – 60 seconds)</span>
            </div>
            <div class="flex items-center gap-3">
              <input type="range" min="3" max="60" step="1" x-model.number="alarmDuration" @change="saveAlarmDuration()" class="w-28 sm:w-36 accent-amber-500">
              <span class="font-mono font-bold text-xs text-slate-700 dark:text-slate-300 w-12 text-right" x-text="alarmDuration + 's'"></span>
              <span x-show="savingDuration" class="text-xs text-amber-500 font-semibold">Saving...</span>
              <span x-show="durationSaved" x-cloak class="text-xs text-emerald-500 font-bold">✓ Saved</span>
            </div>
          </div>
          @endif
        </div>

      </div>

      {{-- ── Sticky Action Footer with Save Button ─────────────────────────── --}}
      <div class="sticky bottom-0 z-20 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-100 dark:border-slate-800/80 px-6 py-4 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 shadow-lg shadow-black/5">
        <div class="flex items-center gap-3">
          <a href="{{ route('settings') }}" class="btn justify-center bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 hover:-translate-y-0.5 hover:shadow-sm transition-all text-xs font-bold py-2.5 px-4 rounded-xl">
            Change Password
          </a>
        </div>
        <button type="submit" 
                class="btn justify-center px-6 py-2.5 bg-indigo-600 text-white font-bold text-sm rounded-xl border border-indigo-600 hover:bg-white hover:text-slate-900 hover:border-green-500 hover:ring-1 hover:ring-green-500 hover:shadow-lg transition-all relative cursor-pointer" 
                id="btn-save-profile"
                x-data="{ isSubmitting: false }"
                @click="isSubmitting = true; setTimeout(() => isSubmitting = false, 8000)"
                :class="{ 'opacity-70 cursor-wait': isSubmitting }">
          <span :class="{ 'opacity-0': isSubmitting }">Save Profile Changes</span>
          <span x-show="isSubmitting" x-cloak class="absolute inset-0 flex items-center justify-center text-current">
            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </span>
        </button>
      </div>
    </section>
  </form>

  {{-- ── 4. ALL POPUP MODALS (Standard, Professional, Smooth SaaS Dialogs) ───── --}}

  {{-- ── MODAL 1: Disable 2FA Modal ────────────────────────────────────────── --}}
  <div x-show="disableModalOpen"
       x-cloak
       class="fixed inset-0 z-[100] flex items-center justify-center p-4 overflow-y-auto"
       style="display: none;"
       @keydown.escape.window="disableModalOpen = false">
    
    {{-- Smooth Backdrop --}}
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-md transition-opacity"
         x-show="disableModalOpen"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="disableModalOpen = false">
    </div>

    {{-- Dialog Box --}}
    <div class="relative w-full max-w-md rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-6 sm:p-7 z-10 my-6 transition-all"
         x-show="disableModalOpen"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-2"
         @click.stop>
      
      <button type="button"
              @click="disableModalOpen = false"
              class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <div class="flex items-start gap-4 pr-6">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center flex-shrink-0 border border-rose-100 dark:border-rose-900/30 shadow-xs">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
        </div>
        <div>
          <h3 class="font-bold text-lg text-slate-900 dark:text-white">Disable Two-Factor Authentication</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Turn off TOTP protection for your account</p>
        </div>
      </div>

      <div class="mt-5 space-y-4">
        <div class="p-3.5 rounded-2xl bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-900/40 text-xs text-amber-900 dark:text-amber-200 leading-relaxed">
          <strong>Security notice:</strong> Turning off Two-Factor Authentication removes the extra layer of security. Anyone with your password will be able to log into your account directly.
        </div>

        <div>
          <label for="2fa-disable-input" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            Account Password or Current 6-Digit Code
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
              </svg>
            </div>
            <input type="password"
                   id="2fa-disable-input"
                   x-model="disablePasswordOrCode"
                   @keyup.enter="confirmDisable()"
                   placeholder="Enter account password or 6-digit code"
                   class="w-full text-sm pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-850 focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 outline-none transition" />
          </div>
        </div>

        <div x-show="disableError" x-cloak class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 text-rose-600 dark:text-rose-400 text-xs font-medium" x-text="disableError"></div>
      </div>

      <div class="mt-6 flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
        <button type="button"
                @click="disableModalOpen = false"
                class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 transition cursor-pointer">
          Keep Protected
        </button>
        <button type="button"
                @click="confirmDisable()"
                :disabled="disabling || !disablePasswordOrCode"
                :class="{ 'opacity-60 cursor-not-allowed': disabling || !disablePasswordOrCode }"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 active:scale-95 text-white text-xs sm:text-sm font-bold transition shadow-md shadow-rose-600/20 cursor-pointer">
          <template x-if="disabling">
            <svg class="animate-spin -ml-1 mr-1.5 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </template>
          <span x-text="disabling ? 'Disabling...' : 'Confirm & Disable'"></span>
        </button>
      </div>
    </div>
  </div>

  {{-- ── MODAL 2: 2FA Setup Modal ─────────────────────────────────────────── --}}
  <div x-show="setupModalOpen"
       x-cloak
       class="fixed inset-0 z-[100] flex items-center justify-center p-4 overflow-y-auto"
       style="display: none;"
       @keydown.escape.window="if(setupStep !== 'recovery') setupModalOpen = false">
    
    {{-- Smooth Backdrop --}}
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-md transition-opacity"
         x-show="setupModalOpen"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="if(setupStep !== 'recovery') setupModalOpen = false">
    </div>

    {{-- Dialog Box --}}
    <div class="relative w-full max-w-2xl rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-6 sm:p-8 z-10 my-6 transition-all"
         x-show="setupModalOpen"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-2"
         @click.stop>
      
      <button type="button"
              @click="finishSetup()"
              class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <div class="flex items-start gap-4 pr-6">
        <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center flex-shrink-0 border border-indigo-200/60 dark:border-indigo-800 shadow-xs">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <div>
          <h3 class="font-bold text-lg sm:text-xl text-slate-900 dark:text-white" x-text="setupStep === 'recovery' ? '2FA Activated Successfully!' : 'Set Up Two-Factor Authentication'"></h3>
          <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5" x-text="setupStep === 'recovery' ? 'Your account is now protected with two-factor authentication.' : 'Connect Google Authenticator, Apple Keychain, or Authy to your account.'"></p>
        </div>
      </div>

      {{-- Setup Body --}}
      <div class="mt-6">
        {{-- STEP 1: Scan & Verify --}}
        <div x-show="setupStep === 'scan'">
          {{-- Loading spinner --}}
          <div x-show="loadingSetup" class="py-16 flex flex-col items-center justify-center gap-3">
            <svg class="animate-spin h-9 w-9 text-indigo-600" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-xs font-semibold text-slate-500">Generating secure 2FA QR code...</span>
          </div>

          <div x-show="!loadingSetup" class="grid grid-cols-1 md:grid-cols-12 gap-6 items-stretch">
            {{-- Left: QR Code & Key --}}
            <div class="md:col-span-5 flex flex-col items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80">
              <div class="text-center w-full flex flex-col items-center">
                <span class="inline-flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-2.5 py-0.5 rounded-full mb-3">
                  Scan QR
                </span>
                
                <div class="w-48 h-48 sm:w-52 sm:h-52 bg-white p-3 rounded-2xl border border-slate-200 dark:border-slate-600 shadow-sm flex items-center justify-center overflow-hidden [&_svg]:w-full [&_svg]:h-full [&_svg]:max-w-full [&_svg]:block"
                     x-html="qrCodeSvg">
                </div>
              </div>

              <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700 w-full text-center">
                <p class="text-[11px] text-slate-500 font-medium">Can't scan? Use setup key:</p>
                <div class="mt-1.5 flex items-center justify-center gap-1.5">
                  <code class="px-2 py-0.5 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400 select-all truncate max-w-[150px]"
                        x-text="secretKey"></code>
                  <button type="button"
                          @click="copySecret()"
                          class="text-xs font-bold px-2.5 py-0.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 text-slate-700 dark:text-slate-200 transition cursor-pointer">
                    <span x-text="copiedSecret ? '✓' : 'Copy'"></span>
                  </button>
                </div>
              </div>
            </div>

            {{-- Right: Verify Code Form --}}
            <div class="md:col-span-7 flex flex-col justify-between space-y-4">
              <div class="space-y-4">
                <div class="p-3.5 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/40 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                  Open your authenticator app (Google Authenticator, Apple Passwords, Authy) and scan the QR code to connect your account.
                </div>

                <div>
                  <label for="2fa-confirm-input" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                    Enter the 6-digit code shown in your app:
                  </label>
                  <input type="text"
                         id="2fa-confirm-input"
                         x-model="confirmCode"
                         maxlength="6"
                         inputmode="numeric"
                         pattern="[0-9]*"
                         autocomplete="one-time-code"
                         @keyup.enter="confirmSetup()"
                         placeholder="000000"
                         class="w-full text-center text-2xl tracking-[0.35em] font-mono font-black py-3 px-4 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-850 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition" />
                </div>

                <div x-show="setupError" x-cloak class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 text-rose-600 dark:text-rose-400 text-xs font-medium" x-text="setupError"></div>
              </div>

              <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2.5">
                <button type="button"
                        @click="finishSetup()"
                        class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 transition cursor-pointer">
                  Cancel
                </button>
                <button type="button"
                        @click="confirmSetup()"
                        :disabled="confirming || confirmCode.replace(/\D/g, '').length < 6"
                        :class="{ 'opacity-60 cursor-not-allowed': confirming || confirmCode.replace(/\D/g, '').length < 6 }"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white text-xs sm:text-sm font-bold transition shadow-md shadow-indigo-600/20 cursor-pointer">
                  <template x-if="confirming">
                    <svg class="animate-spin -ml-1 mr-1.5 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                  </template>
                  <span x-text="confirming ? 'Verifying...' : 'Verify & Activate'"></span>
                </button>
              </div>
            </div>
          </div>
        </div>

        {{-- STEP 2: Backup Recovery Codes --}}
        <div x-show="setupStep === 'recovery'" x-cloak class="space-y-5">
          <div class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-200 leading-relaxed">
            <strong>Important:</strong> Save these emergency backup recovery codes now. If you ever lose your phone, you can use these codes to regain access to your account.
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
            <template x-for="(code, idx) in recoveryCodes" :key="idx">
              <div class="p-2 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-700 text-center font-mono font-bold text-xs text-slate-800 dark:text-slate-100 select-all"
                   x-text="code">
              </div>
            </template>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <button type="button" @click="copyRecoveryCodes()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 text-xs font-bold text-slate-700 dark:text-slate-200 transition cursor-pointer">
                <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span x-text="copiedRecovery ? '✓ Copied' : 'Copy All'"></span>
              </button>
              <button type="button" @click="downloadRecoveryCodes()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 text-xs font-bold text-slate-700 dark:text-slate-200 transition cursor-pointer">
                <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Download .txt</span>
              </button>
            </div>
            <button type="button" @click="finishSetup()" class="px-5 py-2.5 rounded-xl bg-slate-900 text-white dark:bg-white dark:text-slate-900 text-xs sm:text-sm font-bold transition cursor-pointer shadow-xs">
              Done & Return
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ── MODAL 3: 2FA Recovery Codes Modal ──────────────────────────────────── --}}
  <div x-show="recoveryModalOpen"
       x-cloak
       class="fixed inset-0 z-[100] flex items-center justify-center p-4 overflow-y-auto"
       style="display: none;"
       @keydown.escape.window="recoveryModalOpen = false">
    
    {{-- Smooth Backdrop --}}
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-md transition-opacity"
         x-show="recoveryModalOpen"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="recoveryModalOpen = false">
    </div>

    {{-- Dialog Box --}}
    <div class="relative w-full max-w-lg rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-6 sm:p-7 z-10 my-6 transition-all"
         x-show="recoveryModalOpen"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-2"
         @click.stop>
      
      <button type="button"
              @click="recoveryModalOpen = false"
              class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <div class="flex items-start gap-4 pr-6">
        <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center flex-shrink-0 border border-amber-100 dark:border-amber-900/30 shadow-xs">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
          </svg>
        </div>
        <div>
          <h3 class="font-bold text-lg text-slate-900 dark:text-white">Emergency Recovery Codes</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Use one of these codes to sign in if you lose your phone</p>
        </div>
      </div>

      <div class="mt-5 space-y-4">
        {{-- If locked, prompt for password/code --}}
        <div x-show="!recoveryCodes || recoveryCodes.length === 0" class="space-y-4">
          <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
            For security, please confirm your account password or current 6-digit authenticator code to view your recovery codes:
          </p>
          <div>
            <input type="password"
                   id="2fa-unlock-input"
                   x-model="unlockPasswordOrCode"
                   @keyup.enter="unlockRecoveryCodes()"
                   placeholder="Enter account password or 6-digit code"
                   class="w-full text-sm py-2.5 px-3.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-850 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition" />
          </div>
          <div x-show="recoveryError" x-cloak class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 text-rose-600 dark:text-rose-400 text-xs font-medium" x-text="recoveryError"></div>
          <div class="flex items-center justify-end gap-2.5 pt-2">
            <button type="button" @click="recoveryModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 transition">
              Cancel
            </button>
            <button type="button"
                    @click="unlockRecoveryCodes()"
                    :disabled="unlockingRecovery || !unlockPasswordOrCode"
                    class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs">
              <span x-text="unlockingRecovery ? 'Verifying...' : 'Unlock Codes'"></span>
            </button>
          </div>
        </div>

        {{-- If unlocked, display codes --}}
        <div x-show="recoveryCodes && recoveryCodes.length > 0" x-cloak class="space-y-4">
          <div class="grid grid-cols-2 gap-2.5 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
            <template x-for="(code, idx) in recoveryCodes" :key="idx">
              <div class="p-2 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-700 text-center font-mono font-bold text-xs text-slate-800 dark:text-slate-100 select-all"
                   x-text="code">
              </div>
            </template>
          </div>

          {{-- In-modal regenerate codes form (No ugly browser prompt) --}}
          <div x-show="showRegenerateField" x-cloak class="p-3.5 rounded-2xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/40 space-y-2.5">
            <p class="text-xs font-semibold text-amber-900 dark:text-amber-200">Confirm password or code to regenerate:</p>
            <div class="flex gap-2">
              <input type="password"
                     x-model="regenPasswordOrCode"
                     @keyup.enter="submitRegenerateCodes()"
                     placeholder="Password or 6-digit code"
                     class="flex-1 text-xs py-1.5 px-3 rounded-lg border border-amber-300 dark:border-amber-700 bg-white dark:bg-slate-850 text-slate-900 dark:text-white" />
              <button type="button"
                      @click="submitRegenerateCodes()"
                      :disabled="regenerating || !regenPasswordOrCode"
                      class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition">
                <span x-text="regenerating ? '...' : 'Confirm'"></span>
              </button>
              <button type="button" @click="showRegenerateField = false" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                Cancel
              </button>
            </div>
            <div x-show="recoveryError" x-cloak class="text-xs text-rose-600 font-medium" x-text="recoveryError"></div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-2.5 pt-3 border-t border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <button type="button" @click="copyRecoveryCodes()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 text-xs font-bold text-slate-700 dark:text-slate-200 transition cursor-pointer">
                <span x-text="copiedRecovery ? '✓ Copied' : 'Copy All'"></span>
              </button>
              <button type="button" @click="downloadRecoveryCodes()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 text-xs font-bold text-slate-700 dark:text-slate-200 transition cursor-pointer">
                <span>Download .txt</span>
              </button>
            </div>
            <div class="flex items-center gap-2">
              <button type="button"
                      x-show="!showRegenerateField"
                      @click="showRegenerateField = true; regenPasswordOrCode = '';"
                      class="text-xs font-bold text-amber-600 hover:underline">
                Regenerate
              </button>
              <button type="button" @click="recoveryModalOpen = false" class="px-4 py-1.5 rounded-xl bg-slate-900 text-white dark:bg-white dark:text-slate-900 text-xs font-bold transition cursor-pointer">
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ── MODAL 4: Unlink Google In-App Modal (No browser confirm) ───────────── --}}
  <div x-show="unlinkGoogleModalOpen"
       x-cloak
       class="fixed inset-0 z-[100] flex items-center justify-center p-4 overflow-y-auto"
       style="display: none;"
       @keydown.escape.window="unlinkGoogleModalOpen = false">
    
    {{-- Smooth Backdrop --}}
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-md transition-opacity"
         x-show="unlinkGoogleModalOpen"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="unlinkGoogleModalOpen = false">
    </div>

    {{-- Dialog Box --}}
    <div class="relative w-full max-w-md rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-6 sm:p-7 z-10 my-6 transition-all"
         x-show="unlinkGoogleModalOpen"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-2"
         @click.stop>
      
      <button type="button"
              @click="unlinkGoogleModalOpen = false"
              class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <div class="flex items-start gap-4 pr-6">
        <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center flex-shrink-0 border border-amber-100 dark:border-amber-900/30 shadow-xs">
          <svg class="w-6 h-6" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17Z"/>
            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24Z"/>
            <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.98 0 12s.45 3.82 1.25 5.42l4.03-3.15Z"/>
            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98Z"/>
          </svg>
        </div>
        <div>
          <h3 class="font-bold text-lg text-slate-900 dark:text-white">Unlink Google Account?</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Disconnect your Google OAuth account</p>
        </div>
      </div>

      <div class="mt-5 space-y-4">
        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
          Are you sure you want to unlink <strong class="text-slate-900 dark:text-white">{{ $user->google_email ?: $user->email }}</strong>?
          You will no longer be able to log in with 1-click Google OAuth or automatically sync Google Drive videos until you reconnect.
        </p>
      </div>

      <div class="mt-6 flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
        <button type="button"
                @click="unlinkGoogleModalOpen = false"
                class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 transition cursor-pointer">
          Keep Connected
        </button>
        <button type="button"
                @click="confirmUnlinkGoogle()"
                :disabled="unlinkingGoogle"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 active:scale-95 text-white text-xs sm:text-sm font-bold transition shadow-md shadow-rose-600/20 cursor-pointer">
          <template x-if="unlinkingGoogle">
            <svg class="animate-spin -ml-1 mr-1.5 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </template>
          <span x-text="unlinkingGoogle ? 'Unlinking...' : 'Yes, Unlink Account'"></span>
        </button>
      </div>
    </div>
  </div>

  {{-- Hidden helper forms --}}
  @hasrole('super-admin')
  <form x-ref="soundUploadForm" method="POST" action="{{ route('profile.sound.upload') }}" enctype="multipart/form-data" class="hidden">
      @csrf
      <input type="file" name="new_sound" x-ref="newSoundInput" accept="audio/mpeg,audio/wav,audio/ogg" @change="$refs.soundUploadForm.submit()">
  </form>

  <form id="delete-sound-form" method="POST" action="" class="hidden">
      @csrf
      @method('DELETE')
  </form>
  @endhasrole

  @if($user->canManageClockSounds())
  <form x-ref="clockSoundUploadForm" method="POST" action="{{ route('profile.clock-sound.upload') }}" enctype="multipart/form-data" class="hidden">
      @csrf
      <input type="file" name="new_clock_sound" x-ref="newClockSoundInput" accept="audio/mpeg,audio/wav,audio/ogg" @change="$refs.clockSoundUploadForm.submit()">
  </form>

  <form id="delete-clock-sound-form" method="POST" action="" class="hidden">
      @csrf
      @method('DELETE')
  </form>
  @endif

  <form id="unlink-google-form" method="POST" action="{{ route('profile.google.unlink') }}" class="hidden">
      @csrf
  </form>
</div>

{{-- ── 5. Scoped Styles for Profile & Modals ───────────────────────────────── --}}
<style>
  .profile-container {
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    box-sizing: border-box;
    padding: 0 0.5rem 3rem 0.5rem;
  }
  .profile-grid-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    align-items: start;
  }
  @media (min-width: 1024px) {
    .profile-grid-layout {
      grid-template-columns: 320px minmax(0, 1fr) !important;
    }
  }
  .profile-avatar-card {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
  }
  @media (min-width: 1024px) {
    .profile-avatar-card {
      max-width: 320px;
    }
  }
  .profile-avatar-preview-wrap {
    width: 100%;
    max-width: 240px;
    height: 240px;
    margin: 0 auto;
    aspect-ratio: 1 / 1;
    position: relative;
    border-radius: 1.25rem;
    overflow: hidden;
    border: 2px solid var(--border-color, #e2e8f0);
    background: #f8fafc;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.04);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .dark .profile-avatar-preview-wrap {
    background: #0f172a;
    border-color: #334155;
  }
  .profile-avatar-preview-img {
    width: 100% !important;
    height: 100% !important;
    max-width: 240px !important;
    max-height: 240px !important;
    aspect-ratio: 1 / 1 !important;
    object-fit: cover !important;
    border-radius: 1.1rem;
    display: block;
    margin: 0 auto;
  }
  .profile-banner-avatar-wrap {
    width: 88px;
    height: 88px;
    min-width: 88px;
    min-height: 88px;
    max-width: 88px;
    max-height: 88px;
    border-radius: 1.25rem;
    overflow: hidden;
    position: relative;
    flex-shrink: 0;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
  }
  .profile-banner-avatar-img {
    width: 100% !important;
    height: 100% !important;
    max-width: 88px !important;
    max-height: 88px !important;
    aspect-ratio: 1 / 1 !important;
    object-fit: cover !important;
    border-radius: 1.1rem;
    display: block;
  }
  .profile-panel-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.4) transparent;
    -webkit-overflow-scrolling: touch;
  }
  .profile-panel-scroll::-webkit-scrollbar {
    width: 6px;
    height: 6px;
  }
  .profile-panel-scroll::-webkit-scrollbar-track {
    background: transparent;
  }
  .profile-panel-scroll::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.35);
    border-radius: 9999px;
  }
  .profile-panel-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(100, 116, 139, 0.6);
  }
  .dark .profile-panel-scroll::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.25);
  }
  .dark .profile-panel-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(148, 163, 184, 0.45);
  }
</style>

{{-- ── 6. Profile JavaScript & State Controller ───────────────────────────── --}}
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
window.profileManager = function() {
  return {
    showCurrentPassword: false,
    originalEmail: @js($user->email),
    currentEmail: @js(old('email', $user->email)),
    get isChangingEmail() {
        return this.originalEmail !== this.currentEmail;
    },
    previewUrl: @js($previewAvatar),
    fallbackUrl: @js($initialsAvatar),
    avatarUrl: @js($avatarUrlValue),
    removeAvatar: @js($removeAvatar),
    fileName: '',

    // Google Unlink In-App Modal State
    unlinkGoogleModalOpen: false,
    unlinkingGoogle: false,

    // 2FA State
    enabled: @js($user->hasEnabledTwoFactor()),
    setupModalOpen: false,
    recoveryModalOpen: false,
    disableModalOpen: false,

    loadingSetup: false,
    qrCodeSvg: '',
    secretKey: '',
    confirmCode: '',
    confirming: false,
    setupError: '',
    setupStep: 'scan',
    copiedSecret: false,

    recoveryCodes: [],
    loadingRecovery: false,
    recoveryError: '',
    copiedRecovery: false,
    unlockPasswordOrCode: '',
    unlockingRecovery: false,
    showRegenerateField: false,
    regenPasswordOrCode: '',
    regenerating: false,

    disablePasswordOrCode: '',
    disabling: false,
    disableError: '',

    setFile(event) {
      const file = event.target.files?.[0];
      if (!file) return;
      this.fileName = file.name;
      this.avatarUrl = '';
      this.removeAvatar = false;
      this.previewUrl = URL.createObjectURL(file);
    },
    setUrl() {
      this.fileName = '';
      this.removeAvatar = false;
      if (this.$refs.avatarFile) this.$refs.avatarFile.value = '';
      const value = String(this.avatarUrl || '').trim();
      this.previewUrl = value.length ? value : @js($user->avatar_url);
    },
    useInitials() {
      this.fileName = '';
      this.avatarUrl = '';
      this.removeAvatar = true;
      this.previewUrl = this.fallbackUrl;
      if (this.$refs.avatarFile) this.$refs.avatarFile.value = '';
    },

    // 2FA Methods
    startSetup() {
      this.setupModalOpen = true;
      this.loadingSetup = true;
      this.setupStep = 'scan';
      this.confirmCode = '';
      this.setupError = '';
      this.copiedSecret = false;

      fetch("{{ route('profile.two-factor.setup') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json'
        }
      })
      .then(res => res.json())
      .then(data => {
        this.loadingSetup = false;
        if (data && data.success) {
          this.qrCodeSvg = data.qr_code_svg || '';
          this.secretKey = data.secret || '';
          this.$nextTick(() => {
            const input = document.getElementById('2fa-confirm-input');
            if (input) input.focus();
          });
        } else {
          this.setupError = (data && data.message) || 'Failed to initialize 2FA setup.';
        }
      })
      .catch(err => {
        this.loadingSetup = false;
        this.setupError = 'Network error while generating 2FA credentials.';
        console.error(err);
      });
    },

    confirmSetup() {
      const cleanCode = this.confirmCode.replace(/\D/g, '');
      if (cleanCode.length < 6) {
        this.setupError = 'Please enter the complete 6-digit code from your authenticator app.';
        return;
      }

      this.confirming = true;
      this.setupError = '';

      fetch("{{ route('profile.two-factor.confirm') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ code: cleanCode })
      })
      .then(res => res.json().then(data => ({ status: res.status, data })))
      .then(({ status, data }) => {
        this.confirming = false;
        if (data && data.success) {
          this.enabled = true;
          this.recoveryCodes = data.recovery_codes || [];
          this.setupStep = 'recovery';
          if (window.toast) {
            toast.success(data.message || 'Two-Factor Authentication is now enabled!');
          }
        } else {
          this.setupError = (data && data.message) || 'Invalid confirmation code. Please check your app and try again.';
        }
      })
      .catch(err => {
        this.confirming = false;
        this.setupError = 'Network error verifying confirmation code.';
        console.error(err);
      });
    },

    finishSetup() {
      this.setupModalOpen = false;
      this.setupStep = 'scan';
      this.confirmCode = '';
      this.qrCodeSvg = '';
      this.secretKey = '';
    },

    openRecoveryModal() {
      this.recoveryModalOpen = true;
      this.recoveryError = '';
      this.copiedRecovery = false;
      this.unlockPasswordOrCode = '';
      this.showRegenerateField = false;
      if (!this.recoveryCodes || this.recoveryCodes.length === 0) {
        this.$nextTick(() => {
          const input = document.getElementById('2fa-unlock-input');
          if (input) input.focus();
        });
      }
    },

    unlockRecoveryCodes() {
      if (!this.unlockPasswordOrCode) {
        this.recoveryError = 'Please enter your password or current 6-digit code.';
        return;
      }

      this.unlockingRecovery = true;
      this.recoveryError = '';

      fetch("{{ route('profile.two-factor.recovery-codes') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          password: this.unlockPasswordOrCode,
          code: this.unlockPasswordOrCode
        })
      })
      .then(res => res.json().then(data => ({ status: res.status, data })))
      .then(({ status, data }) => {
        this.unlockingRecovery = false;
        if (data && data.success) {
          this.recoveryCodes = data.recovery_codes || [];
          this.unlockPasswordOrCode = '';
        } else {
          this.recoveryError = (data && data.message) || 'Incorrect password or authentication code.';
        }
      })
      .catch(err => {
        this.unlockingRecovery = false;
        this.recoveryError = 'Network error unlocking recovery codes.';
        console.error(err);
      });
    },

    submitRegenerateCodes() {
      if (!this.regenPasswordOrCode) return;

      this.regenerating = true;
      this.recoveryError = '';

      fetch("{{ route('profile.two-factor.regenerate-recovery-codes') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          password: this.regenPasswordOrCode,
          code: this.regenPasswordOrCode
        })
      })
      .then(res => res.json().then(data => ({ status: res.status, data })))
      .then(({ status, data }) => {
        this.regenerating = false;
        if (data && data.success) {
          this.recoveryCodes = data.recovery_codes || [];
          this.showRegenerateField = false;
          this.regenPasswordOrCode = '';
          if (window.toast) toast.success(data.message || 'New recovery codes generated.');
        } else {
          this.recoveryError = (data && data.message) || 'Failed to regenerate recovery codes.';
        }
      })
      .catch(err => {
        this.regenerating = false;
        this.recoveryError = 'Network error regenerating codes.';
        console.error(err);
      });
    },

    openDisableModal() {
      this.disableModalOpen = true;
      this.disablePasswordOrCode = '';
      this.disableError = '';
      this.$nextTick(() => {
        const input = document.getElementById('2fa-disable-input');
        if (input) input.focus();
      });
    },

    confirmDisable() {
      if (!this.disablePasswordOrCode) {
        this.disableError = 'Please enter your password or current 6-digit code.';
        return;
      }

      this.disabling = true;
      this.disableError = '';

      fetch("{{ route('profile.two-factor.disable') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          password: this.disablePasswordOrCode,
          code: this.disablePasswordOrCode
        })
      })
      .then(res => res.json().then(data => ({ status: res.status, data })))
      .then(({ status, data }) => {
        this.disabling = false;
        if (data && data.success) {
          this.enabled = false;
          this.disableModalOpen = false;
          this.disablePasswordOrCode = '';
          this.recoveryCodes = [];
          if (window.toast) {
            toast.success(data.message || 'Two-Factor Authentication has been disabled.');
          }
        } else {
          this.disableError = (data && data.message) || 'Failed to disable 2FA. Incorrect password or code.';
        }
      })
      .catch(err => {
        this.disabling = false;
        this.disableError = 'Network error disabling 2FA.';
        console.error(err);
      });
    },

    copySecret() {
      if (!this.secretKey) return;
      navigator.clipboard.writeText(this.secretKey);
      this.copiedSecret = true;
      setTimeout(() => { this.copiedSecret = false; }, 2500);
    },

    copyRecoveryCodes() {
      if (!this.recoveryCodes || !this.recoveryCodes.length) return;
      const text = this.recoveryCodes.join('\n');
      navigator.clipboard.writeText(text);
      this.copiedRecovery = true;
      setTimeout(() => { this.copiedRecovery = false; }, 2500);
    },

    downloadRecoveryCodes() {
      if (!this.recoveryCodes || !this.recoveryCodes.length) return;
      const date = new Date().toISOString().slice(0, 10);
      let content = "========================================\n";
      content += "DGT SYSTEM - TWO-FACTOR RECOVERY CODES\n";
      content += "Account: " + @js($user->email) + "\n";
      content += "Date: " + date + "\n";
      content += "========================================\n\n";
      content += "Store these recovery codes in a secure password manager or safe.\n";
      content += "Each code can only be used once if you lose access to your authenticator app.\n\n";
      this.recoveryCodes.forEach((code, index) => {
        content += (index + 1) + ". " + code + "\n";
      });
      content += "\n========================================\n";

      const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = "dgt-system-recovery-codes-" + date + ".txt";
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    },

    // In-App Google Unlink Confirmation
    confirmUnlinkGoogle() {
      this.unlinkingGoogle = true;

      fetch("{{ route('profile.google.unlink') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json'
        }
      })
      .then(res => res.json().then(data => ({ status: res.status, data })))
      .then(({ status, data }) => {
        this.unlinkingGoogle = false;
        if (data && data.success) {
          if (window.toast) {
            toast.success(data.message || 'Google account unlinked successfully.');
          }
          this.unlinkGoogleModalOpen = false;
          setTimeout(() => { window.location.reload(); }, 600);
        } else {
          if (window.toast) {
            toast.error(data.message || 'Failed to unlink Google account.');
          } else {
            alert(data.message || 'Failed to unlink Google account.');
          }
        }
      })
      .catch(err => {
        this.unlinkingGoogle = false;
        console.error('Unlink error:', err);
        if (window.toast) {
          toast.error('Network error unlinking Google account.');
        } else {
          alert('Network error unlinking Google account.');
        }
      });
    }
  };
};

// Check for google_linked query parameter on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('google_linked') === '1') {
        if (window.toast) {
            toast.success('Google account linked successfully!');
        }
        urlParams.delete('google_linked');
        const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        window.history.replaceState({}, document.title, newUrl);
    }
});

// Multi-channel OAuth listeners for profile linking
function handleIncomingProfileGoogleToken(token, state) {
    if (!token) return;
    submitProfileGoogleData({ access_token: token });
}

if (typeof BroadcastChannel !== 'undefined') {
    try {
        const authChannel = new BroadcastChannel('kiuq_google_auth');
        authChannel.onmessage = function(event) {
            if (event.data && event.data.type === 'GOOGLE_AUTH_TOKEN' && event.data.accessToken) {
                handleIncomingProfileGoogleToken(event.data.accessToken, event.data.state);
            }
        };
    } catch (e) {
        console.warn('BroadcastChannel init error:', e);
    }
}

window.addEventListener('storage', function(event) {
    if (event.key === 'kiuq_google_auth_token' && event.newValue) {
        try {
            const data = JSON.parse(event.newValue);
            if (data && data.accessToken && (Date.now() - (data.ts || 0) < 60000)) {
                localStorage.removeItem('kiuq_google_auth_token');
                handleIncomingProfileGoogleToken(data.accessToken, data.state);
            }
        } catch (e) {}
    }
});

window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'GOOGLE_AUTH_TOKEN' && event.data.accessToken) {
        handleIncomingProfileGoogleToken(event.data.accessToken, event.data.state);
    }
});

function triggerGoogleProfileLink() {
    const btn = document.getElementById('btn-link-google');
    const btnText = document.getElementById('btn-link-google-text');
    if (btnText) btnText.textContent = 'Connecting...';
    if (btn) btn.disabled = true;

    const clientId = "{{ config('services.google_oauth.client_id') }}";
    const redirectUri = window.location.origin;
    const authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' + new URLSearchParams({
        client_id: clientId,
        redirect_uri: redirectUri,
        response_type: 'token',
        scope: 'email profile openid',
        state: 'link_profile',
        prompt: 'select_account'
    }).toString();

    const isMacApp = window.__dgtMacApp === true ||
                     (typeof navigator !== 'undefined' && (navigator.userAgent.includes('DGTSystem') || navigator.userAgent.includes('dgt-macos'))) ||
                     (window.flutter_inappwebview && window.flutter_inappwebview.callHandler);

    if (isMacApp) {
        window.location.href = authUrl;
        return;
    }

    const w = 520;
    const h = 650;
    const left = window.screenX + (window.outerWidth - w) / 2;
    const top = window.screenY + (window.outerHeight - h) / 2;
    const popup = window.open(authUrl, 'googleLinkPopup', `width=${w},height=${h},top=${top},left=${left},scrollbars=yes`);

    if (!popup || popup.closed || typeof popup.closed === 'undefined') {
        window.location.href = authUrl;
    } else {
        popup.focus();
        const checkClosed = setInterval(() => {
            if (popup.closed) {
                clearInterval(checkClosed);
                if (btn && btn.disabled && btnText && btnText.textContent === 'Connecting...') {
                    btn.disabled = false;
                    btnText.textContent = 'Link Google Account';
                }
            }
        }, 1000);
    }
}

function handleGoogleProfileToken(tokenResponse) {
    const btn = document.getElementById('btn-link-google');
    const btnText = document.getElementById('btn-link-google-text');
    if (btn) btn.disabled = false;
    if (btnText) btnText.textContent = 'Link Google Account';

    if (!tokenResponse || !tokenResponse.access_token) return;
    submitProfileGoogleData({ access_token: tokenResponse.access_token });
}

function handleGoogleProfileCredential(credentialResponse) {
    if (!credentialResponse || !credentialResponse.credential) return;
    submitProfileGoogleData({ credential: credentialResponse.credential });
}

function submitProfileGoogleData(payload) {
    const btn = document.getElementById('btn-link-google');
    const btnText = document.getElementById('btn-link-google-text');
    if (btn) btn.disabled = true;
    if (btnText) btnText.textContent = 'Linking...';

    fetch("{{ route('profile.google.link') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(res => res.json().then(data => ({ status: res.status, data })))
    .then(({ status, data }) => {
        if (data && data.success) {
            if (window.toast) {
                toast.success(data.message || 'Google account linked successfully!');
            }
            setTimeout(() => { window.location.reload(); }, 600);
        } else {
            alert(data.message || 'Failed to link Google account.');
            if (btn) btn.disabled = false;
            if (btnText) btnText.textContent = 'Link Google Account';
        }
    })
    .catch(err => {
        console.error('Error linking Google account:', err);
        alert('Network error while linking Google account.');
        if (btn) btn.disabled = false;
        if (btnText) btnText.textContent = 'Link Google Account';
    });
}
</script>
@endsection
