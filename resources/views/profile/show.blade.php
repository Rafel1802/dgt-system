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
        
        {{-- ── Shift Clock Alarms (12:00 PM Lunch & 4:00 PM Off Work) ─────────────── --}}
        <div class="sm:col-span-2 border-t border-slate-100 pt-5 mt-2" x-data="{
          lunchAlarmEnabled: {{ old('lunch_alarm_enabled', $user->isLunchAlarmEnabled()) ? 'true' : 'false' }},
          currentClockSound: '{{ old('lunch_alarm_sound', $user->lunch_alarm_sound ?? 'melodic-chime.wav') }}',
          previewAudio: null,
          previewPlaying: false,
          previewFile: '',
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
            }, 10000);
          }
        }">
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-3">
            <div>
              <div class="flex items-center gap-2 mb-1 flex-wrap">
                <label class="form-label mb-0 text-slate-800 font-bold flex items-center gap-2">
                  <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                  </span>
                  Shift Clock Alarms
                </label>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-black bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-sm">
                  12:00 PM Lunch
                </span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-black bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-sm">
                  4:00 PM Off Work
                </span>
              </div>
              <p class="text-xs text-slate-500">Play a 10-second ringtone and pop up reminder at 12:00 PM (Lunch Time) & 4:00 PM (Getting Off Work).</p>
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

              {{-- On/Off Switch --}}
              <div class="flex items-center gap-2 pl-2 border-l border-slate-200">
                <input type="hidden" name="lunch_alarm_enabled" :value="lunchAlarmEnabled ? 1 : 0">
                <button type="button"
                        @click="lunchAlarmEnabled = !lunchAlarmEnabled"
                        :class="lunchAlarmEnabled ? 'bg-amber-500' : 'bg-slate-200'"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
                        role="switch"
                        :aria-checked="lunchAlarmEnabled.toString()">
                  <span :class="lunchAlarmEnabled ? 'translate-x-5' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"></span>
                </button>
                <span class="text-xs font-bold" :class="lunchAlarmEnabled ? 'text-amber-700' : 'text-slate-500'" x-text="lunchAlarmEnabled ? 'Alarm On' : 'Alarm Off'"></span>
              </div>
            </div>
          </div>

          {{-- Sound Chooser Grid --}}
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 transition-opacity duration-200" :class="!lunchAlarmEnabled ? 'opacity-40 pointer-events-none' : ''">
            @foreach($clockSounds as $cSound)
            <label class="relative flex cursor-pointer rounded-xl border p-3 focus:outline-none group transition-all"
                   :class="currentClockSound === '{{ $cSound }}' ? 'bg-amber-50/80 border-amber-300 ring-2 ring-amber-400/40 shadow-sm' : 'bg-white border-slate-200 hover:bg-slate-50 hover:border-slate-300'">
              <input type="radio" name="lunch_alarm_sound" value="{{ $cSound }}" class="sr-only"
                     x-model="currentClockSound"
                     @change="playPreview('{{ $cSound }}')">

              <span class="flex flex-1 flex-col justify-between">
                <div>
                  <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full" :class="currentClockSound === '{{ $cSound }}' ? 'bg-amber-500' : 'bg-slate-300'"></span>
                    <span class="block text-sm font-semibold truncate" :class="currentClockSound === '{{ $cSound }}' ? 'text-amber-950 font-bold' : 'text-slate-800'">
                      {{ Str::title(str_replace(['-', '_'], ' ', Str::beforeLast($cSound, '.'))) }}
                    </span>
                  </div>
                  <span class="text-[10px] uppercase font-bold text-slate-400 mt-0.5 block tracking-wider">
                    {{ Str::afterLast($cSound, '.') }} ringtone
                  </span>
                </div>

                {{-- Preview Button --}}
                <button type="button"
                        @click.stop="playPreview('{{ $cSound }}')"
                        class="mt-2.5 inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-md transition-colors w-fit"
                        :class="previewPlaying && previewFile === '{{ $cSound }}' ? 'bg-amber-600 text-white' : 'bg-slate-100 hover:bg-amber-100 text-slate-700 hover:text-amber-900'">
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
                  <span x-text="previewPlaying && previewFile === '{{ $cSound }}' ? 'Playing (8s)...' : 'Preview'"></span>
                </button>
              </span>

              {{-- Active Selection Checkmark --}}
              <div class="absolute top-2.5 right-2.5">
                <svg class="h-5 w-5 text-amber-600 transition-opacity" :class="currentClockSound === '{{ $cSound }}' ? 'opacity-100 scale-100' : 'opacity-0 scale-75'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
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
        </div>



      </div>



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
</div>
@endsection
