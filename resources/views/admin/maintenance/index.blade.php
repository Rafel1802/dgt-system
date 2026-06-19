@extends('layouts.app')
@section('title', 'Maintenance System')
@section('page_title', 'Maintenance System')

@section('content')
<div class="max-w-4xl animate-fade-in" x-data="{
    templateStyle: '{{ $templateStyle }}'
}">
  <div class="mb-6 overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-sky-500 via-blue-600 to-indigo-700 dark:from-slate-800 dark:via-slate-900 dark:to-slate-950 p-6 text-white shadow-xl sm:p-8">
    <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
      <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
        <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-white/20 p-4 shadow-inner ring-1 ring-white/30 backdrop-blur-sm">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-10 w-10 text-white">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.829M11.42 15.17l-3.976-3.976c-.845-.845-2.023-1.12-3.136-.788l-.513.153a.75.75 0 01-.933-.933l.153-.513c.332-1.113.057-2.291-.788-3.136l-3.976-3.976a2.652 2.652 0 013.75-3.75l3.976 3.976c.845.845 2.023 1.12 3.136.788l.513-.153a.75.75 0 01.933.933l-.153.513c-.332 1.113-.057 2.291.788 3.136l3.976 3.976A2.652 2.652 0 0111.42 15.17z" />
          </svg>
        </div>
        <div>
          <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-100 dark:text-slate-300">System Control</p>
          <h2 class="mt-1 font-display text-3xl font-black leading-tight sm:text-4xl">Maintenance System</h2>
          <p class="mt-2 text-sm font-medium text-blue-50 dark:text-slate-400">Toggle maintenance mode on specific modules to prevent user access while you work.</p>
        </div>
      </div>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success mb-6">{{ session('success') }}</div>
  @endif

  <form id="maintenance-form" method="POST" action="{{ route('admin.maintenance.store') }}">
    @csrf

    <div class="mb-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4 px-1">Module Access Control</h3>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @php
            $availableModules = [
                'boards' => ['name' => 'Boards & Workspaces', 'icon' => 'view-columns'],
                'notes' => ['name' => 'Notes & Drives', 'icon' => 'document-text'],
                'crm' => ['name' => 'CRM Systems', 'icon' => 'user-group'],
                'approvals' => ['name' => 'Approvals', 'icon' => 'check-badge'],
                'emails' => ['name' => 'Email Client', 'icon' => 'envelope'],
            ];
        @endphp

        @foreach($availableModules as $key => $module)
            @php $isActive = in_array($key, $maintenanceModules); @endphp
            <div class="card p-5 border-2 transition-colors {{ $isActive ? 'border-red-400 bg-red-50/50' : 'border-transparent' }}">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="h-10 w-10 rounded-xl bg-slate-100 flex items-center justify-center mb-3 {{ $isActive ? 'bg-red-100 text-red-600' : 'text-slate-500' }}">
                           {{-- Icon placeholder --}}
                           <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </div>
                        <h3 class="font-bold text-slate-800">{{ $module['name'] }}</h3>
                        <p class="text-xs text-slate-500 mt-1">Status: <span class="font-semibold {{ $isActive ? 'text-red-600' : 'text-emerald-600' }}">{{ $isActive ? 'Maintenance Mode' : 'Online' }}</span></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer mt-1">
                        <input type="checkbox" name="modules[{{ $key }}]" value="1" class="sr-only peer" {{ $isActive ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500"></div>
                    </label>
                </div>
            </div>
        @endforeach
        </div>
    </div>

    <div class="card p-6 mt-8 border border-slate-200">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Maintenance Page Settings</h3>
        
        <div class="mb-6">
            <label class="block text-sm font-semibold text-slate-700 mb-2">Select Template Style</label>
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="template_style" value="original" x-model="templateStyle" class="text-sky-600 focus:ring-sky-500 w-4 h-4">
                    <span class="text-sm font-medium text-slate-700">Original Theme</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="template_style" value="custom" x-model="templateStyle" class="text-sky-600 focus:ring-sky-500 w-4 h-4">
                    <span class="text-sm font-medium text-slate-700">New Custom Theme</span>
                </label>
            </div>
        </div>

        <div x-show="templateStyle === 'custom'" x-collapse>
            <div class="grid gap-4 sm:grid-cols-2 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Site Name</label>
                    <input type="text" name="site_name" value="{{ old('site_name', $siteName) }}" class="form-input w-full rounded-xl text-sm" placeholder="MyWebsite">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Contact Email</label>
                    <input type="email" name="email" value="{{ old('email', $email) }}" class="form-input w-full rounded-xl text-sm" placeholder="support@example.com">
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Maintenance Message</label>
                <input type="text" name="message" value="{{ old('message', $message) }}" class="form-input w-full rounded-xl text-sm" placeholder="We're currently performing scheduled maintenance.">
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Estimated Time / Subtitle</label>
                <input type="text" name="time" value="{{ old('time', $time) }}" class="form-input w-full rounded-xl text-sm" placeholder="We'll be back shortly!">
            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-slate-100 flex justify-end">
            <button type="submit" class="btn btn-primary px-8 py-2.5 text-sm">Save All Settings</button>
        </div>
    </div>

  </form>
</div>
@endsection
