@extends('layouts.app')
@section('title', 'Trucking Companies')
@section('page_title', 'Trucking Companies')

@section('content')
<div class="animate-fade-in">
  {{-- ── Toolbar ──────────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex gap-2 flex-wrap">
      @foreach(['' => 'Active', 'all' => 'All', 'inactive' => 'Inactive'] as $val => $lbl)
      <a href="{{ route('crm.logistics.trucking.index', array_merge(request()->query(), ['status' => $val])) }}"
         class="btn text-xs py-1.5 px-3 {{ request('status', '') === $val ? 'btn-primary' : 'btn-secondary' }}">
        {{ $lbl }}
      </a>
      @endforeach
    </div>
    <div class="flex gap-2">
      <a href="{{ route('crm.logistics.index') }}" class="btn btn-secondary text-sm">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
        All Shipments
      </a>
      <a href="{{ route('crm.logistics.trucking.create') }}" class="btn btn-primary text-sm">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        New Trucking Company
      </a>
    </div>
  </div>

  {{-- ── Search ────────────────────────────────────────────────────────────── --}}
  <form method="GET" action="{{ route('crm.logistics.trucking.index') }}" class="card p-4 mb-5" x-data>
    <div class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[200px]">
        <label class="form-label text-xs">Search</label>
        <div class="relative">
          <input type="search" name="search" value="{{ request('search') }}"
                 @input.debounce.500ms="$el.closest('form').submit()"
                 placeholder="Company name, contact name, email…" class="form-input pl-9 py-2 text-sm">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        </div>
      </div>
      <input type="hidden" name="status" value="{{ request('status') }}">
    </div>
  </form>

  {{-- ── Trucking Grid ────────────────────────────────────────────────────── --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($companies as $company)
    <div class="card p-5 flex flex-col gap-3 hover:shadow-md transition-shadow">
      <div class="flex items-start justify-between gap-2">
        <h3 class="font-semibold text-slate-800 text-base">{{ $company->company_name }}</h3>
        <span class="badge text-xs px-2 py-0.5 rounded-full {{ $company->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
          {{ $company->is_active ? 'Active' : 'Inactive' }}
        </span>
      </div>

      <div class="space-y-1 mt-1">
        @if($company->pic_name)
          <p class="text-sm flex items-center gap-2 text-slate-600">
            <svg class="w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
            {{ $company->pic_name }}
          </p>
        @endif
        @if($company->phone)
          <p class="text-sm flex items-center gap-2 text-slate-600">
            <svg class="w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-2.896-1.596-5.48-4.08-7.074-6.996l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
            {{ $company->phone }}
          </p>
        @endif
        @if($company->email)
          <p class="text-sm flex items-center gap-2 text-slate-600">
            <svg class="w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
            <a href="mailto:{{ $company->email }}" class="hover:text-indigo-600 hover:underline">{{ $company->email }}</a>
          </p>
        @endif
      </div>

      <div class="mt-auto flex gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('crm.logistics.trucking.show', $company) }}" class="btn btn-secondary btn-sm flex-1 text-center text-xs">
          View Shipments
        </a>
        <a href="{{ route('crm.logistics.trucking.edit', $company) }}" class="btn btn-secondary btn-icon" style="width:32px;height:32px;" title="Edit">
          <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
        </a>
      </div>
    </div>
    @empty
    <div class="col-span-full">
      <div class="card p-14 text-center">
        <div class="text-5xl mb-3">🚛</div>
        <p class="text-slate-500 font-medium">No trucking companies found</p>
        <p class="text-slate-400 text-xs mt-1">Create your first trucking company profile to assign shipments</p>
        <a href="{{ route('crm.logistics.trucking.create') }}" class="btn btn-primary text-sm mt-4 inline-flex">+ New Trucking Company</a>
      </div>
    </div>
    @endforelse
  </div>

  @if($companies->hasPages())
  <div class="mt-5">{{ $companies->links() }}</div>
  @endif

</div>
@endsection
