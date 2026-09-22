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
  class="mx-auto max-w-6xl animate-fade-in"
  x-data="{
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
    }
  }"
>
  {{-- ── Connected Google Account (Top of Profile) ─────────────────────── --}}
  <div class="mb-6 rounded-[1.75rem] border border-slate-200/80 dark:border-slate-800 bg-white/90 dark:bg-slate-900/80 p-5 sm:p-6 shadow-sm backdrop-blur-md">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-3.5">
        <div class="w-12 h-12 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center flex-shrink-0 shadow-xs">
          <svg class="w-6 h-6" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17Z"/>
            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24Z"/>
            <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.98 0 12s.45 3.82 1.25 5.42l4.03-3.15Z"/>
            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98Z"/>
          </svg>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h3 class="font-bold text-sm sm:text-base text-slate-800 dark:text-slate-100">Google Account</h3>
            @if($user->isGoogleLinked())
              <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-emerald-500 text-white">Linked</span>
            @else
              <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30">Not Linked</span>
            @endif
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            @if($user->isGoogleLinked())
              Connected with <strong class="text-slate-700 dark:text-slate-200">{{ $user->google_email ?: $user->email }}</strong> for one-click login and seamless Google Drive access.
            @else
              Link your @kiuq.com Google account to sign in with one click and access Google Drive videos seamlessly.
            @endif
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2 flex-shrink-0">
        @if($user->isGoogleLinked())
          <button type="button"
                  onclick="event.preventDefault(); if(confirm('Are you sure you want to unlink your Google account?')) { document.getElementById('unlink-google-form').submit(); }"
                  class="px-4 py-2 rounded-xl border border-rose-200 dark:border-rose-900/60 bg-white dark:bg-slate-900 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-bold transition shadow-xs cursor-pointer">
            Unlink Account
          </button>
        @else
          <button type="button"
                  id="btn-link-google"
                  onclick="triggerGoogleProfileLink()"
                  class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs font-bold transition shadow-xs cursor-pointer active:scale-95">
            <svg class="w-4 h-4" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17Z"/>
              <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24Z"/>
              <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.98 0 12s.45 3.82 1.25 5.42l4.03-3.15Z"/>
              <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98Z"/>
            </svg>
            <span id="btn-link-google-text">Link Google Account</span>
          </button>
        @endif
      </div>
    </div>
  </div>

  <div class="mb-6 overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-[#2F68ED] via-[#2457cf] to-[#173a92] p-6 text-white shadow-xl shadow-blue-900/20 sm:p-8">
    <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
      <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
        <div class="relative h-24 w-24 flex-shrink-0 overflow-hidden rounded-3xl bg-white/15 p-1 shadow-2xl ring-1 ring-white/25">
          <img
            :src="previewUrl"
            src="{{ $previewAvatar }}"
            alt="{{ $user->name }}"
            data-avatar-name="{{ $user->name }}"
            data-avatar-color="{{ $user->avatar_color }}"
            class="h-full w-full rounded-[1.25rem] object-cover"
            x-on:error="previewUrl = fallbackUrl"
          >
        </div>
        <div>
          <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-100">Profile Center</p>
          <h2 class="mt-2 font-display text-3xl font-black leading-tight sm:text-4xl">{{ $user->name }}</h2>
          <p class="mt-2 text-sm font-semibold text-blue-100">{{ $user->role_display }}</p>
          <p class="mt-1 text-xs font-medium text-blue-100/80">Member since {{ $user->created_at->format('M Y') }}</p>
        </div>
      </div>
      <div class="grid gap-3 text-sm sm:grid-cols-2 md:w-80">
        <div class="rounded-2xl border border-white/15 bg-white/12 p-4 backdrop-blur">
          <p class="text-xs font-black uppercase tracking-wider text-blue-100">Email</p>
          <p class="mt-1 break-all font-bold">{{ $user->email }}</p>
        </div>
        <div class="rounded-2xl border border-white/15 bg-white/12 p-4 backdrop-blur">
          <p class="text-xs font-black uppercase tracking-wider text-blue-100">Status</p>
          <p class="mt-1 font-bold">{{ $user->is_active ? 'Active account' : 'Inactive account' }}</p>
        </div>

      </div>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success mb-5">{{ session('success') }}</div>
  @endif

  @if($errors->any())
    <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      <p class="font-black">Please fix the highlighted profile details.</p>
      <ul class="mt-2 list-disc space-y-1 pl-5">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
    @csrf
    @method('PUT')
    <input type="hidden" name="remove_avatar" :value="removeAvatar ? '1' : '0'">

    <aside class="card h-fit p-5">
      <div class="overflow-hidden rounded-3xl border border-slate-200 bg-slate-50 p-3">
        <img
          :src="previewUrl"
          src="{{ $previewAvatar }}"
          alt="{{ $user->name }}"
          data-avatar-name="{{ $user->name }}"
          data-avatar-color="{{ $user->avatar_color }}"
          class="aspect-square w-full rounded-2xl object-cover shadow-inner"
          x-on:error="previewUrl = fallbackUrl"
        >
      </div>
      <div class="mt-4 space-y-2">
        @if($canChangeAvatar)
          <label for="avatar" class="btn w-full justify-center bg-indigo-600 text-white border border-indigo-600 hover:bg-white hover:text-slate-900 hover:border-green-500 hover:ring-1 hover:ring-green-500 hover:shadow-lg transition-colors cursor-pointer">
            Choose image
          </label>
          <input
            x-ref="avatarFile"
            id="avatar"
            type="file"
            name="avatar"
            class="sr-only"
            accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
            @change="setFile"
          >
          <button type="button" class="btn w-full justify-center bg-white border border-slate-200 text-slate-700 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 hover:-translate-y-0.5 hover:shadow-sm transition-colors" @click="useInitials">
            Use initials avatar
          </button>
          <p class="min-h-5 truncate text-center text-xs font-semibold text-slate-400" x-text="fileName || 'JPEG, PNG, GIF, or WEBP up to 2 MB'"></p>
          @error('avatar')<p class="form-error justify-center">{{ $message }}</p>@enderror
        @else
          <div class="rounded-xl border border-rose-100 bg-rose-50 p-3 text-center">
            <p class="text-xs font-medium text-rose-600">Profile images are currently locked by the administrator.</p>
          </div>
        @endif
      </div>
    </aside>

    <section class="card p-5 sm:p-6">
      <div class="mb-6 flex flex-col gap-2 border-b border-slate-100 pb-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h3 class="font-display text-xl font-black text-slate-900">Edit Profile</h3>
          <p class="mt-1 text-sm font-medium text-slate-500">Update your identity, contact details, and avatar source.</p>
        </div>
        <span class="w-fit rounded-full bg-blue-50 px-3 py-1 text-xs font-black uppercase text-[#2F68ED]">Live preview</span>
      </div>

      <div class="grid gap-5 sm:grid-cols-2">
        @if($canChangeAvatar)
        <div class="sm:col-span-2">
          <label for="avatar_url" class="form-label">Image URL</label>
          <input
            id="avatar_url"
            type="url"
            name="avatar_url"
            x-model="avatarUrl"
            @input.debounce.300ms="setUrl"
            placeholder="https://example.com/avatar.png"
            class="form-input @error('avatar_url') error @enderror"
          >
          <p class="mt-1 text-xs font-medium text-slate-400">Paste a direct image URL, or choose a local file instead.</p>
          @error('avatar_url')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        @endif

        <div>
          <label for="profile-name" class="form-label flex gap-2">Full Name</label>
          <input
            id="profile-name"
            type="text"
            name="name"
            value="{{ old('name', $user->name) }}"
            class="form-input @error('name') error @enderror"
            required
          >
          @error('name')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
          <label for="profile-email" class="form-label flex gap-2">Email Address @if(!$canEdit)<span class="text-[10px] text-red-500 font-normal self-center">(Locked)</span>@endif</label>
          <input
            id="profile-email"
            type="email"
            name="email"
            x-model="currentEmail"
            class="form-input @error('email') error @enderror @if(!$canEdit) opacity-60 cursor-not-allowed bg-slate-50 @endif"
            @if(!$canEdit) disabled @endif
            required
          >
          @error('email')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div x-show="isChangingEmail" x-cloak class="sm:col-span-2 transition-all duration-300 ease-in-out">
          <label for="current_password" class="form-label text-amber-600 flex items-center gap-2">
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
            Verify Current Password
          </label>
          <div class="relative">
            <input
              id="current_password"
              :type="showCurrentPassword ? 'text' : 'password'"
              name="current_password"
              class="form-input pr-10 border-amber-300 focus:border-amber-500 focus:ring-amber-500 @error('current_password') error @enderror"
              :required="isChangingEmail"
              placeholder="Enter your password to confirm email change"
            >
            <button type="button" @click="showCurrentPassword = !showCurrentPassword" class="absolute inset-y-0 right-0 px-3 flex items-center text-amber-500 hover:text-amber-700">
              <svg x-show="!showCurrentPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <svg x-show="showCurrentPassword" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
            </button>
          </div>
          <p class="mt-1 text-xs text-amber-600/80 font-medium">Security requirement: You must provide your current password when changing your email address.</p>
          @error('current_password')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
          <label for="profile-phone" class="form-label">Phone Number</label>
          <input
            id="profile-phone"
            type="text"
            name="phone"
            value="{{ old('phone', $user->phone) }}"
            class="form-input @error('phone') error @enderror"
            placeholder="e.g. +855 12 345 678"
          >
          @error('phone')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
          <label for="profile-whatsapp" class="form-label">WhatsApp Number</label>
          <input
            id="profile-whatsapp"
            type="text"
            name="whatsapp"
            value="{{ old('whatsapp', $user->whatsapp) }}"
            class="form-input @error('whatsapp') error @enderror"
            placeholder="e.g. +855 12 345 678"
          >
          <p class="mt-1 text-xs font-medium text-slate-400">Leave blank to use the phone number above.</p>
          @error('whatsapp')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div class="sm:col-span-2 border-t border-slate-100 pt-5 mt-2" x-data="{ 
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
          <div class="flex items-start justify-between mb-3">
            <div>
              <label class="form-label flex gap-2 mb-1">Notification Sound</label>
              <p class="text-xs text-slate-500">Choose the sound that plays when you receive a notification.</p>
            </div>
            <div class="flex items-center gap-3">
              {{-- Mute Toggle --}}
              <button type="button"
                @click="toggleMute()"
                :class="soundMuted ? 'bg-slate-200 text-slate-500 border-slate-300' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                class="flex items-center gap-2 text-xs font-semibold px-3 py-1.5 rounded-lg border transition-all duration-200 select-none"
              >
                <svg x-show="!soundMuted" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" />
                </svg>
                <svg x-show="soundMuted" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 9.75 19.5 12m0 0 2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25m-10.5-6 4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                </svg>
                <span x-text="soundMuted ? 'Sounds Off' : 'Sounds On'"></span>
              </button>
              @hasrole('super-admin')
              <button type="button" @click="$refs.newSoundInput.click()" class="btn justify-center bg-white border border-slate-200 text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 hover:border-indigo-200 text-xs py-1.5 px-3 rounded-lg flex-shrink-0 transition-colors shadow-sm">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Sound
              </button>
              @endhasrole
            </div>
          </div>
          
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" :class="soundMuted ? 'opacity-50 pointer-events-none' : ''">
            @foreach($sounds as $sound)
            <label class="relative flex cursor-pointer rounded-xl border p-3 focus:outline-none group"
                   :class="currentSound === '{{ $sound }}' ? 'bg-indigo-50 border-indigo-200' : 'bg-white border-slate-200 hover:bg-slate-50'">
              <input type="radio" name="notification_sound" value="{{ $sound }}" class="sr-only" 
                     x-model="currentSound"
                     @change="
                        const audio = new Audio('{{ asset('notificationsound/' . $sound) }}');
                        audio.play();
                     ">
              <span class="flex flex-1 flex-col">
                <span class="block text-sm font-medium" :class="currentSound === '{{ $sound }}' ? 'text-indigo-900' : 'text-slate-900'">
                  {{ Str::beforeLast($sound, '.') }}
                </span>
                <span class="mt-1 flex items-center text-xs" :class="currentSound === '{{ $sound }}' ? 'text-indigo-700' : 'text-slate-500'">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 mr-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" />
                  </svg>
                  Preview
                </span>
              </span>
              <svg class="h-5 w-5 text-indigo-600" :class="currentSound === '{{ $sound }}' ? 'opacity-100' : 'opacity-0'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
              </svg>
              @hasrole('super-admin')
              <button type="button" 
                      class="absolute -top-2 -right-2 bg-white rounded-full p-1 shadow-sm border border-slate-200 text-slate-400 hover:text-red-500 hover:border-red-200 hover:bg-red-50 opacity-0 group-hover:opacity-100 transition-all z-10"
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
          @error('notification_sound')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        
        {{-- ── Shift Clock Alarms (12:00 PM Lunch, 4:00 PM Off Work & Saturday 11:00 AM) ─────────────── --}}
        <div class="sm:col-span-2 border-t border-slate-100 pt-5 mt-2" x-data="{
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

          isSoundSelected(sound) {
            return this.getCurrentSound() === sound;
          },

          selectSound(sound) {
            if (this.activeShiftTab === 'lunch') {
              this.lunchAlarmSound = sound;
            } else if (this.activeShiftTab === 'offwork') {
              this.offworkAlarmSound = sound;
            } else if (this.activeShiftTab === 'sat') {
              this.satAlarmSound = sound;
            }
            // Update live data attributes on modal audio tag for instant preview
            const audio = document.getElementById('lunch-alarm-sound');
            if (audio) {
              if (this.activeShiftTab === 'lunch') audio.dataset.lunchSrc = '{{ asset('clocksound') }}/' + sound;
              if (this.activeShiftTab === 'offwork') audio.dataset.offworkSrc = '{{ asset('clocksound') }}/' + sound;
              if (this.activeShiftTab === 'sat') audio.dataset.satSrc = '{{ asset('clocksound') }}/' + sound;
            }
            this.playPreview(sound);
          },

          playPreview(sound) {
            if (this.previewAudio) {
              this.previewAudio.pause();
              this.previewAudio.currentTime = 0;
            }
            if (this.previewPlaying && this.previewFile === sound) {
              this.previewPlaying = false;
              this.previewFile = '';
              return;
            }
            this.previewAudio = new Audio('{{ asset('clocksound') }}/' + sound);
            this.previewAudio.volume = 1.0;
            this.previewFile = sound;
            this.previewPlaying = true;
            this.previewAudio.play().catch(e => console.log('Preview error:', e));
            this.previewAudio.onended = () => {
              this.previewPlaying = false;
              this.previewFile = '';
            };
            setTimeout(() => {
              if (this.previewPlaying && this.previewFile === sound) {
                if (this.previewAudio) this.previewAudio.pause();
                this.previewPlaying = false;
                this.previewFile = '';
              }
            }, (this.alarmDuration || 15) * 1000);
          },
          saveDuration() {
            if (!this.alarmDuration || this.alarmDuration < 3 || this.alarmDuration > 60) {
              this.durationError = 'Must be 3-60s';
              return;
            }
            this.durationError = '';
            this.savingDuration = true;
            fetch('{{ route('profile.clock-sound.duration') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
              },
              body: JSON.stringify({ shift_alarm_duration: this.alarmDuration })
            })
            .then(res => res.json())
            .then(data => {
              this.savingDuration = false;
              if (data && data.success) {
                this.durationSaved = true;
                setTimeout(() => { this.durationSaved = false; }, 2500);
              } else {
                this.durationError = data.message || 'Failed';
              }
            })
            .catch(err => {
              this.savingDuration = false;
              this.durationError = 'Error';
            });
          },
          togglingAlarm: false,
          toggleLunchAlarm() {
            this.lunchAlarmEnabled = !this.lunchAlarmEnabled;
            this.togglingAlarm = true;

            // Notify alarm modal on page
            window.dispatchEvent(new CustomEvent('lunch-alarm-toggle', {
              detail: { enabled: this.lunchAlarmEnabled }
            }));

            // Auto-save setting immediately via AJAX
            fetch('{{ route('profile.clock-sound.toggle') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
              },
              body: JSON.stringify({ lunch_alarm_enabled: this.lunchAlarmEnabled })
            })
            .then(res => res.json())
            .then(data => {
              this.togglingAlarm = false;
            })
            .catch(() => {
              this.togglingAlarm = false;
            });
          }
        }">
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-3">
            <div>
              <div class="flex items-center gap-2 mb-2 flex-wrap">
                <label class="form-label mb-0 text-slate-800 font-bold flex items-center gap-2">
                  <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                  </span>
                  Shift Clock Alarms
                </label>

                {{-- Interactive Shift Time Tabs --}}
                <button type="button"
                        @click="activeShiftTab = 'lunch'"
                        :class="activeShiftTab === 'lunch'
                          ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-md ring-2 ring-amber-400/60 font-black scale-[1.02]'
                          : 'bg-amber-50/80 text-amber-800 border border-amber-200/80 hover:bg-amber-100 font-bold opacity-85 hover:opacity-100'"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs transition-all cursor-pointer shadow-sm"
                        title="Click to customize 12:00 PM Lunch sound">
                  <span>🍽️</span>
                  <span>Mon-Fri: 12:00 PM Lunch</span>
                  <span class="ml-0.5 text-[10px] px-1.5 py-0.5 rounded-full font-mono font-black"
                        :class="activeShiftTab === 'lunch' ? 'bg-white/25 text-white' : 'bg-amber-200/80 text-amber-950'"
                        x-text="lunchAlarmSound"></span>
                </button>

                <button type="button"
                        @click="activeShiftTab = 'offwork'"
                        :class="activeShiftTab === 'offwork'
                          ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-md ring-2 ring-emerald-400/60 font-black scale-[1.02]'
                          : 'bg-emerald-50/80 text-emerald-800 border border-emerald-200/80 hover:bg-emerald-100 font-bold opacity-85 hover:opacity-100'"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs transition-all cursor-pointer shadow-sm"
                        title="Click to customize 4:00 PM Off Work sound">
                  <span>🎉</span>
                  <span>Mon-Fri: 4:00 PM Off Work</span>
                  <span class="ml-0.5 text-[10px] px-1.5 py-0.5 rounded-full font-mono font-black"
                        :class="activeShiftTab === 'offwork' ? 'bg-white/25 text-white' : 'bg-emerald-200/80 text-emerald-950'"
                        x-text="offworkAlarmSound"></span>
                </button>

                <button type="button"
                        @click="activeShiftTab = 'sat'"
                        :class="activeShiftTab === 'sat'
                          ? 'bg-gradient-to-r from-teal-500 to-cyan-500 text-white shadow-md ring-2 ring-teal-400/60 font-black scale-[1.02]'
                          : 'bg-teal-50/80 text-teal-800 border border-teal-200/80 hover:bg-teal-100 font-bold opacity-85 hover:opacity-100'"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs transition-all cursor-pointer shadow-sm"
                        title="Click to customize Saturday 11:00 AM Half Day sound">
                  <span>☀️</span>
                  <span>Sat: 11:00 AM Half Day</span>
                  <span class="ml-0.5 text-[10px] px-1.5 py-0.5 rounded-full font-mono font-black"
                        :class="activeShiftTab === 'sat' ? 'bg-white/25 text-white' : 'bg-teal-200/80 text-teal-950'"
                        x-text="satAlarmSound"></span>
                </button>

                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-500 border border-slate-200" title="Sunday Off">
                  Sun: Off
                </span>
              </div>
              <p class="text-xs text-slate-500">Play a <span class="font-bold text-amber-600" x-text="alarmDuration"></span>-second ringtone and pop up reminder at 12:00 PM & 4:00 PM (Mon–Fri), 11:00 AM (Sat Half Day 7–11 AM), Sunday Off.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
              {{-- Test Preview 12 PM Button --}}
              <button type="button"
                      onclick="if (window.triggerLunchAlarm) window.triggerLunchAlarm(true, 'lunch')"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200/80 rounded-lg transition-colors cursor-pointer"
                      title="Test 12:00 PM Lunch Popup">
                <span class="text-xs">🍽️</span>
                <span>Preview 12 PM</span>
              </button>
              {{-- Test Preview 4 PM Button --}}
              <button type="button"
                      onclick="if (window.triggerOffWorkAlarm) window.triggerOffWorkAlarm(true)"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200/80 rounded-lg transition-colors cursor-pointer"
                      title="Test 4:00 PM Off Work Popup">
                <span class="text-xs">🎉</span>
                <span>Preview 4 PM</span>
              </button>
              {{-- Test Preview Saturday 11 AM Button --}}
              <button type="button"
                      onclick="if (window.triggerSaturdayAlarm) window.triggerSaturdayAlarm(true)"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-teal-700 bg-teal-50 hover:bg-teal-100 border border-teal-200/80 rounded-lg transition-colors cursor-pointer"
                      title="Test Saturday 11:00 AM Half Day Popup">
                <span class="text-xs">☀️</span>
                <span>Preview Sat 11 AM</span>
              </button>

              {{-- Add Clock Ringtone (QC, Super Admin, Supervisor) --}}
              @if($user->canManageClockSounds())
              <button type="button" 
                      @click="$refs.newClockSoundInput.click()" 
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 hover:border-indigo-300 text-xs font-bold transition-all shadow-sm cursor-pointer"
                      title="QC, Super Admin, and Supervisors can upload new clock ringtones">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Clock Sound</span>
              </button>
              @endif

              {{-- Duration Setter for Super Admin & QC --}}
              @if($user->canSetAlarmDuration())
              <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-purple-200 dark:border-purple-800/60 bg-purple-50 dark:bg-purple-950/40 text-purple-800 dark:text-purple-300 text-xs font-bold shadow-sm"
                   title="Super Admin & QC can set the alarm ring duration for all users">
                <svg class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Duration:</span>
                <input type="number" min="3" max="60" x-model.number="alarmDuration"
                       @change="saveDuration()"
                       @keydown.enter.prevent="saveDuration()"
                       class="w-12 h-6 px-1 text-center font-mono font-black text-xs rounded border border-purple-300 dark:border-purple-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-purple-500 focus:outline-none">
                <span>s</span>
                <button type="button" @click="saveDuration()"
                        :disabled="savingDuration"
                        class="px-1.5 py-0.5 rounded bg-purple-600 hover:bg-purple-700 text-white text-[10px] font-black uppercase tracking-wider transition-colors cursor-pointer disabled:opacity-50">
                  <span x-show="!savingDuration && !durationSaved">Set</span>
                  <span x-show="savingDuration">...</span>
                  <span x-show="durationSaved" class="text-emerald-300">✓</span>
                </button>
              </div>
              @endif

              {{-- On/Off Switch --}}
              <div class="flex items-center gap-2 pl-2 border-l border-slate-200 dark:border-slate-700">
                <input type="hidden" name="lunch_alarm_enabled" :value="lunchAlarmEnabled ? 1 : 0">
                <input type="hidden" name="lunch_alarm_sound" :value="lunchAlarmSound">
                <input type="hidden" name="offwork_alarm_sound" :value="offworkAlarmSound">
                <input type="hidden" name="sat_alarm_sound" :value="satAlarmSound">
                <button type="button"
                        @click="toggleLunchAlarm()"
                        :disabled="togglingAlarm"
                        :class="lunchAlarmEnabled ? 'bg-amber-500' : 'bg-slate-200 dark:bg-slate-700'"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 disabled:opacity-75"
                        role="switch"
                        :aria-checked="lunchAlarmEnabled.toString()"
                        title="Click to turn shift clock alarms on or off">
                  <span :class="lunchAlarmEnabled ? 'translate-x-5' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"></span>
                </button>
                <span class="text-xs font-bold" :class="lunchAlarmEnabled ? 'text-amber-700 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400'" x-text="lunchAlarmEnabled ? 'Alarm On' : 'Alarm Off'"></span>
              </div>
            </div>
          </div>

          {{-- Informational Banner showing which time slot is currently being customized --}}
          <div class="mb-3.5 p-3 rounded-2xl border flex flex-col sm:flex-row sm:items-center justify-between gap-2 transition-all shadow-sm"
               :class="{
                 'bg-gradient-to-r from-amber-50 to-orange-50 border-amber-200 text-amber-950': activeShiftTab === 'lunch',
                 'bg-gradient-to-r from-emerald-50 to-teal-50 border-emerald-200 text-emerald-950': activeShiftTab === 'offwork',
                 'bg-gradient-to-r from-teal-50 to-cyan-50 border-teal-200 text-teal-950': activeShiftTab === 'sat'
               }">
            <div class="flex items-center gap-2 text-xs font-medium">
              <template x-if="activeShiftTab === 'lunch'">
                <div class="flex items-center gap-2">
                  <span class="text-lg">🍽️</span>
                  <div>
                    <span class="font-bold text-amber-900">Customizing Sound for 12:00 PM Lunch:</span>
                    <span class="ml-1 text-slate-600">Currently set to <strong class="font-mono bg-white px-2 py-0.5 rounded-md border border-amber-300 text-amber-900 shadow-sm" x-text="lunchAlarmSound"></strong></span>
                  </div>
                </div>
              </template>
              <template x-if="activeShiftTab === 'offwork'">
                <div class="flex items-center gap-2">
                  <span class="text-lg">🎉</span>
                  <div>
                    <span class="font-bold text-emerald-900">Customizing Sound for 4:00 PM Off Work:</span>
                    <span class="ml-1 text-slate-600">Currently set to <strong class="font-mono bg-white px-2 py-0.5 rounded-md border border-emerald-300 text-emerald-900 shadow-sm" x-text="offworkAlarmSound"></strong></span>
                  </div>
                </div>
              </template>
              <template x-if="activeShiftTab === 'sat'">
                <div class="flex items-center gap-2">
                  <span class="text-lg">☀️</span>
                  <div>
                    <span class="font-bold text-teal-900">Customizing Sound for Saturday 11:00 AM Half Day:</span>
                    <span class="ml-1 text-slate-600">Currently set to <strong class="font-mono bg-white px-2 py-0.5 rounded-md border border-teal-300 text-teal-900 shadow-sm" x-text="satAlarmSound"></strong></span>
                  </div>
                </div>
              </template>
            </div>
            <div class="text-[11px] text-slate-500 font-semibold flex items-center gap-1.5">
              <span>👉 Click any ringtone below to choose it for this shift time</span>
            </div>
          </div>

          {{-- Sound Chooser Grid --}}
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 transition-opacity duration-200" :class="!lunchAlarmEnabled ? 'opacity-40 pointer-events-none' : ''">
            @foreach($clockSounds as $cSound)
            <label class="relative flex cursor-pointer rounded-xl border p-3 focus:outline-none group transition-all select-none"
                   @click.prevent="selectSound('{{ $cSound }}')"
                   :class="isSoundSelected('{{ $cSound }}')
                     ? (activeShiftTab === 'lunch'
                         ? 'bg-amber-50/90 border-amber-300 ring-2 ring-amber-400/50 shadow-sm'
                         : (activeShiftTab === 'offwork'
                             ? 'bg-emerald-50/90 border-emerald-300 ring-2 ring-emerald-400/50 shadow-sm'
                             : 'bg-teal-50/90 border-teal-300 ring-2 ring-teal-400/50 shadow-sm'))
                     : 'bg-white border-slate-200 hover:bg-slate-50 hover:border-slate-300'">

              <span class="flex flex-1 flex-col justify-between">
                <div>
                  <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full"
                          :class="isSoundSelected('{{ $cSound }}')
                            ? (activeShiftTab === 'lunch' ? 'bg-amber-500' : (activeShiftTab === 'offwork' ? 'bg-emerald-500' : 'bg-teal-500'))
                            : 'bg-slate-300'"></span>
                    <span class="block text-sm font-semibold truncate"
                          :class="isSoundSelected('{{ $cSound }}')
                            ? (activeShiftTab === 'lunch' ? 'text-amber-950 font-bold' : (activeShiftTab === 'offwork' ? 'text-emerald-950 font-bold' : 'text-teal-950 font-bold'))
                            : 'text-slate-800'">
                      {{ Str::title(str_replace(['-', '_'], ' ', Str::beforeLast($cSound, '.'))) }}
                    </span>
                  </div>
                  <span class="text-[10px] uppercase font-bold text-slate-400 mt-0.5 block tracking-wider">
                    {{ Str::afterLast($cSound, '.') }} ringtone
                  </span>

                  {{-- Active Shift Tags for this sound --}}
                  <div class="flex flex-wrap items-center gap-1 mt-1.5">
                    <span x-show="lunchAlarmSound === '{{ $cSound }}'"
                          class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-black bg-amber-100 text-amber-900 border border-amber-300/80 shadow-xs"
                          title="Assigned to 12:00 PM Lunch">
                      🍽️ 12 PM
                    </span>
                    <span x-show="offworkAlarmSound === '{{ $cSound }}'"
                          class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-black bg-emerald-100 text-emerald-900 border border-emerald-300/80 shadow-xs"
                          title="Assigned to 4:00 PM Off Work">
                      🎉 4 PM
                    </span>
                    <span x-show="satAlarmSound === '{{ $cSound }}'"
                          class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-black bg-teal-100 text-teal-900 border border-teal-300/80 shadow-xs"
                          title="Assigned to Saturday 11:00 AM">
                      ☀️ Sat 11 AM
                    </span>
                  </div>
                </div>

                {{-- Preview Button --}}
                <button type="button"
                        @click.stop="playPreview('{{ $cSound }}')"
                        class="mt-2.5 inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-md transition-colors w-fit cursor-pointer"
                        :class="previewPlaying && previewFile === '{{ $cSound }}'
                          ? (activeShiftTab === 'lunch' ? 'bg-amber-600 text-white' : (activeShiftTab === 'offwork' ? 'bg-emerald-600 text-white' : 'bg-teal-600 text-white'))
                          : 'bg-slate-100 hover:bg-slate-200 text-slate-700'">
                  <template x-if="previewPlaying && previewFile === '{{ $cSound }}'">
                    <svg class="w-3 h-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 9v6m4-6v6" />
                    </svg>
                  </template>
                  <template x-if="!(previewPlaying && previewFile === '{{ $cSound }}')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                    </svg>
                  </template>
                  <span x-text="previewPlaying && previewFile === '{{ $cSound }}' ? 'Playing...' : 'Preview'"></span>
                </button>
              </span>

              {{-- Active Selection Checkmark --}}
              <div class="absolute top-2.5 right-2.5">
                <svg class="h-5 w-5 transition-opacity"
                     :class="{
                       'opacity-100 scale-100 text-amber-600': isSoundSelected('{{ $cSound }}') && activeShiftTab === 'lunch',
                       'opacity-100 scale-100 text-emerald-600': isSoundSelected('{{ $cSound }}') && activeShiftTab === 'offwork',
                       'opacity-100 scale-100 text-teal-600': isSoundSelected('{{ $cSound }}') && activeShiftTab === 'sat',
                       'opacity-0 scale-75': !isSoundSelected('{{ $cSound }}')
                     }"
                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                </svg>
              </div>

              {{-- Delete Clock Sound (QC, Super Admin, Supervisor) --}}
              @if($user->canManageClockSounds())
              <button type="button" 
                      class="absolute -top-2 -right-2 bg-white rounded-full p-1 shadow-md border border-slate-200 text-slate-400 hover:text-red-600 hover:border-red-300 hover:bg-red-50 opacity-0 group-hover:opacity-100 transition-all z-10"
                      title="Remove Clock Ringtone"
                      onclick="event.preventDefault(); event.stopPropagation(); if(confirm('Are you sure you want to delete this clock ringtone?')) { const f = document.getElementById('delete-clock-sound-form'); f.action = '{{ route('profile.clock-sound.delete', $cSound) }}'; f.submit(); }">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
              </button>
              @endif
            </label>
            @endforeach

            @if($clockSounds->isEmpty())
              <div class="col-span-full text-sm text-slate-500 italic py-2">
                No clock sounds available in the public/clocksound directory.
              </div>
            @endif
          </div>
          @error('lunch_alarm_sound')<p class="form-error">{{ $message }}</p>@enderror
          @error('offwork_alarm_sound')<p class="form-error">{{ $message }}</p>@enderror
          @error('sat_alarm_sound')<p class="form-error">{{ $message }}</p>@enderror


      <div class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-between">
        <div class="flex flex-col gap-3 sm:flex-row">
          <a href="{{ route('settings') }}" class="btn justify-center bg-white border border-slate-200 text-slate-700 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 hover:-translate-y-0.5 hover:shadow-sm transition-colors">Change Password</a>
        </div>
        <button type="submit" 
                class="btn justify-center px-5 bg-indigo-600 text-white border border-indigo-600 hover:bg-white hover:text-slate-900 hover:border-green-500 hover:ring-1 hover:ring-green-500 hover:shadow-lg transition-colors relative" 
                id="btn-save-profile"
                x-data="{ isSubmitting: false }"
                @click="isSubmitting = true; setTimeout(() => isSubmitting = false, 8000)"
                :class="{ 'opacity-70 cursor-wait': isSubmitting }">
          <span :class="{ 'opacity-0': isSubmitting }">Save Changes</span>
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

  {{-- Clock Sound Management Forms (QC, Super Admin, Supervisor) --}}
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

  {{-- Google Unlink Form --}}
  <form id="unlink-google-form" method="POST" action="{{ route('profile.google.unlink') }}" class="hidden">
      @csrf
  </form>
</div>

<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
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

// Listen for popup message
window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'GOOGLE_AUTH_TOKEN' && event.data.accessToken) {
        submitProfileGoogleData({ access_token: event.data.accessToken });
    }
});

function triggerGoogleProfileLink() {
    const btn = document.getElementById('btn-link-google');
    const btnText = document.getElementById('btn-link-google-text');
    if (btnText) btnText.textContent = 'Connecting...';
    if (btn) btn.disabled = true;

    const clientId = "{{ config('services.google_oauth.client_id') }}";
    const redirectUri = window.location.origin; // e.g. https://lightcyan-weasel-711536.hostingersite.com
    const authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' + new URLSearchParams({
        client_id: clientId,
        redirect_uri: redirectUri,
        response_type: 'token',
        scope: 'email profile openid',
        state: 'link_profile',
        prompt: 'select_account'
    }).toString();

    // In macOS desktop app (flutter_inappwebview), navigate directly to OAuth
    if (window.flutter_inappwebview && window.flutter_inappwebview.callHandler) {
        window.location.href = authUrl;
        return;
    }

    // Try popup for desktop web browsers
    const w = 520;
    const h = 650;
    const left = window.screenX + (window.outerWidth - w) / 2;
    const top = window.screenY + (window.outerHeight - h) / 2;
    const popup = window.open(authUrl, 'googleLinkPopup', `width=${w},height=${h},top=${top},left=${left},scrollbars=yes`);

    if (!popup || popup.closed || typeof popup.closed === 'undefined') {
        // Popup was blocked -> use direct navigation
        window.location.href = authUrl;
    } else {
        popup.focus();
        const checkClosed = setInterval(() => {
            if (popup.closed) {
                clearInterval(checkClosed);
                if (btn) btn.disabled = false;
                if (btnText) btnText.textContent = 'Link Google Account';
            }
        }, 1000);
    }
}

function handleGoogleProfileToken(tokenResponse) {
    const btn = document.getElementById('btn-link-google');
    const btnText = document.getElementById('btn-link-google-text');
    if (btn) btn.disabled = false;
    if (btnText) btnText.textContent = 'Link Google Account';

    if (!tokenResponse || !tokenResponse.access_token) {
        return;
    }
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

function confirmAndUnlinkGoogle() {
    if (!confirm('Are you sure you want to unlink your Google account?')) {
        return;
    }

    const btn = document.getElementById('btn-unlink-google');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Unlinking...';
    }

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
        if (data && data.success) {
            if (window.toast) {
                toast.success(data.message || 'Google account unlinked successfully.');
            }
            setTimeout(() => { window.location.reload(); }, 600);
        } else {
            alert(data.message || 'Failed to unlink Google account.');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Unlink Account';
            }
        }
    })
    .catch(err => {
        console.error('Unlink error:', err);
        alert('Network error unlinking Google account.');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Unlink Account';
        }
    });
}
@endsection

