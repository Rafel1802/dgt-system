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
          <p class="mt-1 truncate font-bold">{{ $user->email }}</p>
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
      </div>

      <div class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-between">
        <div class="flex flex-col gap-3 sm:flex-row">
          <a href="{{ route('settings') }}" class="btn justify-center bg-white border border-slate-200 text-slate-700 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 hover:-translate-y-0.5 hover:shadow-sm transition-colors">Change Password</a>
          <a href="{{ route('dashboard') }}" class="btn justify-center bg-white border border-slate-200 text-slate-700 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 hover:-translate-y-0.5 hover:shadow-sm transition-colors">Back to Dashboard</a>
        </div>
        <button type="submit" class="btn justify-center px-5 bg-indigo-600 text-white border border-indigo-600 hover:bg-white hover:text-slate-900 hover:border-green-500 hover:ring-1 hover:ring-green-500 hover:shadow-lg transition-colors" id="btn-save-profile">
          Save Changes
        </button>
      </div>
    </section>
  </form>
</div>
@endsection
