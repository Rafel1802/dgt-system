@extends('layouts.app')
@section('title', 'Maintenance System')
@section('page_title', 'Maintenance System')

@section('content')
<div class="animate-fade-in w-full" x-data="{
    templateStyle: '{{ $templateStyle }}'
}">

  {{-- ── Hero Banner ─────────────────────────────────────────────────────── --}}
  <div class="mb-8 overflow-hidden rounded-2xl relative" style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 40%, #6366f1 100%);">
    <div class="absolute inset-0 opacity-10" style="background-image: url(\"data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E\");"></div>
    <div class="relative p-8 flex flex-col sm:flex-row sm:items-center gap-6">
      <div class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded-2xl bg-white/20 shadow-inner ring-1 ring-white/30 backdrop-blur-sm">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-10 w-10 text-white">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.829M11.42 15.17l-3.976-3.976c-.845-.845-2.023-1.12-3.136-.788l-.513.153a.75.75 0 01-.933-.933l.153-.513c.332-1.113.057-2.291-.788-3.136l-3.976-3.976a2.652 2.652 0 013.75-3.75l3.976 3.976c.845.845 2.023 1.12 3.136.788l.513-.153a.75.75 0 01.933.933l-.153.513c-.332 1.113-.057 2.291.788 3.136l3.976 3.976A2.652 2.652 0 0111.42 15.17z" />
        </svg>
      </div>
      <div class="flex-1">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-100 mb-1">System Control</p>
        <h1 class="font-display text-3xl font-black text-white sm:text-4xl">Maintenance System</h1>
        <p class="mt-2 text-sm font-medium text-blue-100 max-w-2xl">Toggle maintenance mode on specific modules to prevent user access while you work. Changes take effect immediately.</p>
      </div>
      <div class="flex-shrink-0 hidden lg:flex flex-col items-end gap-2">
        @php $totalActive = count($maintenanceModules); @endphp
        <div class="text-white/80 text-xs font-semibold uppercase tracking-widest">Active Restrictions</div>
        <div class="text-5xl font-black text-white">{{ $totalActive }}</div>
        <div class="text-blue-200 text-xs">module{{ $totalActive !== 1 ? 's' : '' }} in maintenance</div>
      </div>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success mb-6">{{ session('success') }}</div>
  @endif

  <form id="maintenance-form" method="POST" action="{{ route('admin.maintenance.store') }}">
    @csrf

    {{-- ── Module Access Control ─────────────────────────────────────────── --}}
    <div class="mb-8">
      <div class="flex items-center justify-between mb-5 px-1">
        <div>
          <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Module Access Control</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Toggle individual modules on or off for maintenance mode.</p>
        </div>
        <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          {{ 7 - count($maintenanceModules) }} Online
          <span class="w-px h-3 bg-slate-300 dark:bg-slate-600"></span>
          <span class="w-2 h-2 rounded-full bg-red-500"></span>
          {{ count($maintenanceModules) }} In Maintenance
        </span>
      </div>

      @php
        $availableModules = [
          'boards'       => [
            'name'  => 'Boards & Workspaces',
            'desc'  => 'Kanban boards, task cards, and team workspaces',
            'color' => 'indigo',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>',
          ],
          'notes'        => [
            'name'  => 'Notes & Drives',
            'desc'  => 'Team notes, private notes, and shared drives',
            'color' => 'violet',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>',
          ],
          'crm'          => [
            'name'  => 'CRM Systems',
            'desc'  => 'Customer management, eBay & logistics CRM',
            'color' => 'sky',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>',
          ],
          'approvals'    => [
            'name'  => 'Approvals',
            'desc'  => 'Content approval queue and review workflows',
            'color' => 'amber',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0118 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375"/>',
          ],
          'social_media' => [
            'name'  => 'Social Media Team',
            'desc'  => 'Social media dashboards, scheduling & analytics',
            'color' => 'pink',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z"/>',
          ],
          'all_websites' => [
            'name'  => 'All Websites',
            'desc'  => 'Website list, dashboards, and maintenance logs',
            'color' => 'emerald',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253M3 12a8.959 8.959 0 00.284 2.253"/>',
          ],
        ];

        $colorMap = [
          'indigo'  => ['ring' => 'ring-indigo-400',  'bg' => 'bg-indigo-500/10 dark:bg-indigo-500/20',  'icon' => 'text-indigo-500',  'badge' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300'],
          'violet'  => ['ring' => 'ring-violet-400',  'bg' => 'bg-violet-500/10 dark:bg-violet-500/20',  'icon' => 'text-violet-500',  'badge' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/50 dark:text-violet-300'],
          'sky'     => ['ring' => 'ring-sky-400',     'bg' => 'bg-sky-500/10 dark:bg-sky-500/20',        'icon' => 'text-sky-500',     'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300'],
          'amber'   => ['ring' => 'ring-amber-400',   'bg' => 'bg-amber-500/10 dark:bg-amber-500/20',    'icon' => 'text-amber-500',   'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300'],
          'teal'    => ['ring' => 'ring-teal-400',    'bg' => 'bg-teal-500/10 dark:bg-teal-500/20',      'icon' => 'text-teal-500',    'badge' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/50 dark:text-teal-300'],
          'pink'    => ['ring' => 'ring-pink-400',    'bg' => 'bg-pink-500/10 dark:bg-pink-500/20',      'icon' => 'text-pink-500',    'badge' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/50 dark:text-pink-300'],
          'emerald' => ['ring' => 'ring-emerald-400', 'bg' => 'bg-emerald-500/10 dark:bg-emerald-500/20','icon' => 'text-emerald-500', 'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300'],
        ];
      @endphp

      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        @foreach($availableModules as $key => $module)
          @php
            $isActive = in_array($key, $maintenanceModules);
            $c = $colorMap[$module['color']];
          @endphp
          <label for="module-{{ $key }}" class="relative flex flex-col cursor-pointer group" x-data="{ active: {{ $isActive ? 'true' : 'false' }} }">
            <div class="card p-5 h-full border-2 transition-all duration-200 cursor-pointer"
                 :class="active ? 'border-red-400 bg-red-50/60 dark:bg-red-900/20 shadow-md shadow-red-200/40 dark:shadow-red-900/30' : 'border-transparent hover:border-slate-200 dark:hover:border-slate-600 hover:shadow-md'">

              {{-- Top row: icon + toggle --}}
              <div class="flex items-start justify-between mb-4">
                <div class="h-12 w-12 rounded-xl flex items-center justify-center flex-shrink-0 transition-colors"
                     :class="active ? 'bg-red-100 dark:bg-red-900/40' : '{{ $c['bg'] }}'">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                       class="w-6 h-6 transition-colors"
                       :class="active ? 'text-red-500' : '{{ $c['icon'] }}'">
                    {!! $module['icon'] !!}
                  </svg>
                </div>

                {{-- Toggle Switch --}}
                <div class="relative flex-shrink-0 mt-0.5">
                  <input
                    type="checkbox"
                    id="module-{{ $key }}"
                    name="modules[{{ $key }}]"
                    value="1"
                    class="sr-only peer"
                    x-model="active"
                  >
                  <div class="w-12 h-6 rounded-full transition-colors peer-focus:ring-2 peer-focus:ring-offset-2 peer-focus:ring-red-400
                    relative after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:rounded-full after:h-[18px] after:w-[18px] after:transition-all after:shadow-sm
                    peer-checked:after:translate-x-6"
                    :class="active ? 'bg-red-500' : 'bg-slate-200 dark:bg-slate-700'">
                  </div>
                </div>
              </div>

              {{-- Info --}}
              <div>
                <h3 class="font-bold text-slate-800 dark:text-slate-100 text-[0.9375rem] leading-snug">{{ $module['name'] }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">{{ $module['desc'] }}</p>
              </div>

              {{-- Status Badge --}}
              <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                <template x-if="active">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                    Maintenance Mode
                  </span>
                </template>
                <template x-if="!active">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {{ $c['badge'] }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Online
                  </span>
                </template>
                <span class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ $key }}</span>
              </div>
            </div>
          </label>
        @endforeach
      </div>
    </div>

    {{-- ── Maintenance Page Settings ─────────────────────────────────────── --}}
    <div class="card border border-slate-200 dark:border-slate-700 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">
        <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Maintenance Page Settings</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Customize what users see when a module is under maintenance.</p>
      </div>
      <div class="p-6">
        {{-- Template Style --}}
        <div class="mb-6">
          <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Select Template Style</label>
          <div class="flex items-center gap-6">
            <label class="flex items-center gap-2.5 cursor-pointer group">
              <input type="radio" name="template_style" value="original" x-model="templateStyle"
                     class="text-sky-600 focus:ring-sky-500 w-4 h-4">
              <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Original Theme</span>
            </label>
            <label class="flex items-center gap-2.5 cursor-pointer group">
              <input type="radio" name="template_style" value="custom" x-model="templateStyle"
                     class="text-sky-600 focus:ring-sky-500 w-4 h-4">
              <span class="text-sm font-medium text-slate-700 dark:text-slate-300">New Custom Theme</span>
            </label>
          </div>
        </div>

        <div x-show="templateStyle === 'custom'" x-collapse>
          <div class="grid gap-4 sm:grid-cols-2 mb-4">
            <div>
              <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Site Name</label>
              <input type="text" name="site_name" value="{{ old('site_name', $siteName) }}"
                     class="form-input w-full rounded-xl text-sm" placeholder="MyWebsite">
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Contact Email</label>
              <input type="email" name="email" value="{{ old('email', $email) }}"
                     class="form-input w-full rounded-xl text-sm" placeholder="support@example.com">
            </div>
          </div>
          <div class="mb-4">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Maintenance Message</label>
            <input type="text" name="message" value="{{ old('message', $message) }}"
                   class="form-input w-full rounded-xl text-sm" placeholder="We're currently performing scheduled maintenance.">
          </div>
          <div class="mb-4">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Estimated Time / Subtitle</label>
            <input type="text" name="time" value="{{ old('time', $time) }}"
                   class="form-input w-full rounded-xl text-sm" placeholder="We'll be back shortly!">
          </div>
        </div>

        <div class="mt-6 pt-6 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between gap-4">
          <p class="text-xs text-slate-400 dark:text-slate-500">Changes to module toggles take effect immediately after saving.</p>
          <button type="submit" class="btn btn-primary px-8 py-2.5 text-sm flex-shrink-0">
            Save All Settings
          </button>
        </div>
      </div>
    </div>

  </form>
</div>
@endsection
