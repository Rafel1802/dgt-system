@extends('layouts.app')
@section('title', 'User Management')
@section('page_title', 'User Management')

@section('content')
<div x-data="userManager()" class="animate-fade-in pb-28 md:pb-8">

<style>
/* ── Mobile Responsive Table ── */
@media (max-width: 768px) {
  .responsive-table thead { display: none; }
  .responsive-table tbody, .responsive-table tr, .responsive-table td { display: block; width: 100%; }
  .responsive-table tr {
    margin-bottom: 1rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    padding: 0.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
  }
  .responsive-table td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem !important;
    border-bottom: 1px solid #f8fafc;
    text-align: right;
  }
  .responsive-table td:last-child { border-bottom: none; }
  .responsive-table td::before {
    content: attr(data-label);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.65rem;
    letter-spacing: 0.05em;
    color: #94a3b8;
    margin-right: 1rem;
    text-align: left;
  }
  .responsive-table td > div { text-align: right; justify-content: flex-end; }
  /* User cell gets special treatment */
  .responsive-table td.user-cell {
    flex-direction: column;
    align-items: flex-start;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 1rem !important;
  }
  .responsive-table td.user-cell::before { display: none; }
  .responsive-table td.user-cell > div { text-align: left; justify-content: flex-start; width: 100%; }
  .responsive-table td.check-cell::before { display: none; }
  .responsive-table td.check-cell { border-bottom: none; padding-bottom: 0 !important; }
}
</style>


  {{-- Stats --}}
  <div class="mobile-scroll-x lg:grid lg:grid-cols-3 gap-4 mb-6">
    <div class="stat-card flex-shrink-0 w-[240px] lg:w-auto">
      <div class="stat-icon" style="background:linear-gradient(135deg,#e0e7ff,#c7d2fe)">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#4f46e5" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
      </div>
      <div><div class="stat-value">{{ $stats['total'] }}</div><div class="stat-label">Total Users</div></div>
    </div>
    <div class="stat-card flex-shrink-0 w-[240px] lg:w-auto">
      <div class="stat-icon" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0)">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#059669" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
      </div>
      <div><div class="stat-value">{{ $stats['active'] }}</div><div class="stat-label">Active</div></div>
    </div>
    <div class="stat-card flex-shrink-0 w-[240px] lg:w-auto">
      <div class="stat-icon" style="background:linear-gradient(135deg,#fee2e2,#fecaca)">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#dc2626" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/></svg>
      </div>
      <div><div class="stat-value">{{ $stats['inactive'] }}</div><div class="stat-label">Inactive</div></div>
    </div>
  </div>

  {{-- Search & Filters (client-side live filter) --}}
  <div class="card mb-5">
    <div class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[200px]">
        <label class="form-label text-xs">Search</label>
        <div class="relative">
          <input type="search" id="users-search" x-model="search" placeholder="Name or email…"
              class="form-input pl-9 py-2 text-sm"
              autocomplete="off"
              @input="applyFilters()">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
          <button type="button" x-show="search" @click="search=''; applyFilters()" x-cloak
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
          </button>
        </div>
      </div>
      <div>
        <label class="form-label text-xs">Role</label>
        <select id="filter-role" x-model="filterRole" @change="applyFilters()" class="form-input py-2 text-sm">
          <option value="">All Roles</option>
          @foreach($roles as $role)
            <option value="{{ $role->name }}">{{ ucwords(str_replace('-', ' ', $role->name)) }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="form-label text-xs">Status</label>
        <select id="filter-active" x-model="filterStatus" @change="applyFilters()" class="form-input py-2 text-sm">
          <option value="">All</option>
          <option value="active">Active</option>
          <option value="frozen">Frozen / Inactive</option>
        </select>
      </div>
      <div class="flex gap-2">
        <button type="button" @click="resetFilters()" class="btn btn-secondary py-2">Reset</button>
      </div>

      {{-- Bulk Actions Dropdown --}}
      <div class="relative ml-4" x-data="{ open: false }" @click.outside="open = false" x-show="selectedUsers.length > 0" x-cloak>
        <button type="button" @click="open = !open" class="btn btn-secondary py-2 border-slate-300">
          Bulk Actions (<span x-text="selectedUsers.length"></span>)
          <svg class="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
        </button>
        <div x-show="open" class="absolute left-0 mt-1 w-48 bg-white border border-slate-200 rounded-lg shadow-lg z-50 overflow-hidden py-1">
          <button type="button" @click="performBulkAction('allow_security'); open=false" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Allow Security Edit</button>
          <button type="button" @click="performBulkAction('disallow_security'); open=false" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Disallow Security Edit</button>
          <div class="border-t border-slate-100 my-1"></div>
          <button type="button" @click="performBulkAction('freeze'); open=false" class="w-full text-left px-4 py-2 text-sm text-amber-600 hover:bg-amber-50">Freeze Users</button>
          <button type="button" @click="performBulkAction('unfreeze'); open=false" class="w-full text-left px-4 py-2 text-sm text-emerald-600 hover:bg-emerald-50">Unfreeze Users</button>
        </div>
      </div>

      <button type="button" @click="toggleSelectMode()" class="btn py-2 ml-auto transition-colors" :class="selectMode ? 'btn-secondary bg-slate-200 border-slate-300' : 'btn-secondary'">
        <svg x-show="!selectMode" class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/></svg>
        <span x-text="selectMode ? 'Cancel Selection' : 'Select Users'"></span>
      </button>

      <a href="{{ route('admin.users.create') }}" class="btn btn-primary py-2 ml-2" id="btn-add-user">
        <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Add User
      </a>
    </div>

    {{-- Live result count --}}
    <p class="mt-3 text-xs font-semibold text-slate-400" x-show="search || filterRole || filterStatus" x-cloak>
      Showing <span class="font-black text-slate-700" x-text="visibleCount"></span> of {{ count($users) }} users
    </p>
  </div>

  {{-- Users Table --}}
  <div class="card p-0 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm responsive-table">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left w-10 transition-all duration-200" x-show="selectMode" x-cloak>
              <input type="checkbox" x-model="selectAll" @change="toggleSelectAll()" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
            </th>
            <th class="px-5 py-3 text-left">User</th>
            <th class="px-4 py-3 text-left">Role</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Last Login</th>
            <th class="px-4 py-3 text-left">Security Edit</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($users as $user)
          @php
            $userRole = ($user->roles->first(fn($r) => strpos($r->name, 'social_') !== 0) ?? $user->roles->first())?->name ?? '';
          @endphp
          <tr class="hover:bg-slate-50/70 transition-colors" id="user-row-{{ $user->id }}"
              x-show="matchesFilter(
                '{{ addslashes(strtolower($user->name)) }}',
                '{{ addslashes(strtolower($user->email)) }}',
                '{{ addslashes(strtolower($userRole)) }}',
                '{{ $user->is_active ? 'active' : 'frozen' }}'
              )"
              x-cloak>
            <td class="px-5 py-3 transition-all duration-200 check-cell" x-show="selectMode" x-cloak>
              <input type="checkbox" value="{{ $user->id }}" x-model="selectedUsers" class="user-checkbox rounded border-slate-300 text-indigo-600 focus:ring-indigo-600" {{ $user->id === auth()->id() ? 'disabled' : '' }}>
            </td>
            <td class="px-5 py-3 user-cell"
                :class="selectMode && {{ $user->id !== auth()->id() ? 'true' : 'false' }} ? 'cursor-pointer' : ''"
                @click="handleUserCellClick({{ $user->id !== auth()->id() ? 'true' : 'false' }}, {{ $user->id }})">
              <div class="flex items-center gap-3 cursor-zoom-in"
                   @dblclick.stop="previewAvatar(@js($user->avatar_url), @js($user->name))"
                   title="Double-click profile to preview">
                <img src="{{ $user->avatar_url }}"
                     alt="{{ $user->name }}"
                     @dblclick.stop="previewAvatar(@js($user->avatar_url), @js($user->name))"
                     title="Double-click to view full screen"
                     class="avatar avatar-sm cursor-zoom-in">
                <div>
                  <div class="font-semibold text-slate-800">{{ $user->name }}</div>
                  <div class="text-xs text-slate-400">{{ $user->email }}</div>
                </div>
              </div>
            </td>
            <td class="px-4 py-3" data-label="Role">
              @php $role = $user->roles->first(fn($r) => strpos($r->name, 'social_') !== 0) ?? $user->roles->first(); @endphp
              @if($role)
                <span class="badge badge-indigo text-xs">{{ ucwords(str_replace('-', ' ', $role->name)) }}</span>
              @else
                <span class="text-slate-300 text-xs">No role</span>
              @endif
            </td>
            <td class="px-4 py-3" data-label="Status">
              <button @click="toggleActive({{ $user->id }}, {{ $user->is_active ? 'true' : 'false' }})"
                      id="status-btn-{{ $user->id }}"
                      {{ $user->id === auth()->id() ? 'disabled' : '' }}
                      class="flex items-center gap-1.5 text-xs font-semibold {{ $user->is_active ? 'text-emerald-600' : 'text-slate-400' }} hover:opacity-70 transition-opacity disabled:opacity-40 disabled:cursor-not-allowed w-fit">
                <span id="status-dot-{{ $user->id }}" class="w-2 h-2 rounded-full {{ $user->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                <span id="status-text-{{ $user->id }}">{{ $user->is_active ? 'Active' : 'Frozen' }}</span>
              </button>
            </td>
            <td class="px-4 py-3 text-xs text-slate-400" data-label="Last Login">
              {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
            </td>
            <td class="px-4 py-3" data-label="Security">
              <span class="text-[10px] font-semibold px-2 py-0.5 rounded w-fit {{ $user->can_edit_profile ? 'bg-blue-50 text-blue-600' : 'bg-red-50 text-red-600' }}">
                {{ $user->can_edit_profile ? 'Allowed' : 'Not Allowed' }}
              </span>
            </td>
            <td class="px-4 py-3" data-label="Actions">
              <div class="flex items-center gap-1 justify-end">
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="Edit">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                </a>
                @if($user->id !== auth()->id() && ! $user->hasRole('super-admin'))
                <button @click="deleteUser({{ $user->id }}, '{{ addslashes($user->name) }}')"
                        class="btn btn-danger btn-icon" style="width:28px;height:28px;" title="Delete">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                </button>
                @endif
              </div>
            </td>
          </tr>
          @empty
          <tr x-show="visibleCount === 0" x-cloak>
            <td colspan="7" class="text-center py-12 text-slate-400">No users match your filters.</td>
          </tr>
          <tr x-show="visibleCount !== 0" style="display:none;"></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Reset Password Modal --}}
  <div x-show="showResetModal" x-cloak class="modal-overlay" @keydown.escape.window="showResetModal=false">
    <div class="modal-box max-w-sm" @click.stop>
      <div class="modal-header">
        <h3 class="font-display font-bold text-slate-800">Reset Password</h3>
        <button @click="showResetModal=false" class="btn btn-secondary btn-icon ml-auto">✕</button>
      </div>
      <div class="p-6 space-y-3">
        <div>
          <label class="form-label">New Password</label>
          <div class="relative">
            <input :type="showResetNew ? 'text' : 'password'" x-model="resetPassword" class="form-input pr-10" placeholder="Min 8 chars, mixed case + number">
            <button type="button" @click="showResetNew = !showResetNew" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-600">
              <svg x-show="!showResetNew" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <svg x-show="showResetNew" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
            </button>
          </div>
        </div>
        <div>
          <label class="form-label">Confirm Password</label>
          <div class="relative">
            <input :type="showResetConfirm ? 'text' : 'password'" x-model="resetPasswordConfirm" class="form-input pr-10" placeholder="Repeat password">
            <button type="button" @click="showResetConfirm = !showResetConfirm" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-600">
              <svg x-show="!showResetConfirm" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <svg x-show="showResetConfirm" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
            </button>
          </div>
        </div>
        <div class="flex gap-3 pt-2">
          <button @click="showResetModal=false" class="btn btn-secondary flex-1">Cancel</button>
          <button @click="submitResetPassword()" class="btn btn-primary flex-1">Reset</button>
        </div>
      </div>
    </div>
  </div>

  {{-- Avatar Fullscreen Preview --}}
  <div x-show="avatarPreview.open"
       x-cloak
       class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/80 p-4"
       @keydown.escape.window="closeAvatarPreview()"
       @click="closeAvatarPreview()">
    <div class="max-h-[88vh] w-full max-w-3xl overflow-hidden rounded-2xl border border-white/15 bg-slate-950 shadow-2xl" @click.stop>
      <div class="flex items-center justify-between border-b border-white/10 px-4 py-3 text-white">
        <p class="truncate text-sm font-black" x-text="avatarPreview.title"></p>
        <button type="button" @click="closeAvatarPreview()" class="rounded-lg p-2 text-white/70 transition hover:bg-white/10 hover:text-white" aria-label="Close preview">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div class="flex max-h-[78vh] items-center justify-center bg-slate-900">
        <img :src="avatarPreview.url" :alt="avatarPreview.title" class="max-h-[78vh] max-w-full object-contain">
      </div>
    </div>
  </div>

  @include('kanban.partials.toast')
</div>
@endsection

@push('scripts')
<script>
function userManager() {
  return {
    selectMode: false,
    selectedUsers: [],
    selectAll: false,
    showResetModal: false,
    activeUserId: null,
    resetPassword: '',
    resetPasswordConfirm: '',
    showResetNew: false,
    showResetConfirm: false,
    avatarPreview: {
      open: false,
      url: '',
      title: '',
    },
    // Filter states
    search: '',
    filterRole: '',
    filterStatus: '',
    visibleCount: {{ count($users) }},

    applyFilters() {
      // Allow Alpine to update the DOM first (x-show applies style="display:none")
      this.$nextTick(() => {
        let count = 0;
        document.querySelectorAll('tr[id^="user-row-"]').forEach(row => {
          if (row.style.display !== 'none') count++;
        });
        this.visibleCount = count;
      });
    },

    resetFilters() {
      this.search = '';
      this.filterRole = '';
      this.filterStatus = '';
      this.applyFilters();
    },

    matchesFilter(name, email, role, status) {
      const q = this.search.toLowerCase().trim();
      const matchSearch = !q || name.includes(q) || email.includes(q);
      const matchRole = !this.filterRole || role === this.filterRole.toLowerCase();
      const matchStatus = !this.filterStatus || status === this.filterStatus.toLowerCase();
      return matchSearch && matchRole && matchStatus;
    },

    toggleSelectMode() {
      this.selectMode = !this.selectMode;
      if (!this.selectMode) {
        this.selectedUsers = [];
        this.selectAll = false;
      }
    },

    toggleSelectAll() {
      if (this.selectAll) {
        this.selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:not(:disabled)')).map(cb => cb.value);
      } else {
        this.selectedUsers = [];
      }
    },

    toggleUserSelection(userId) {
      const idStr = String(userId);
      if (this.selectedUsers.includes(idStr)) {
        this.selectedUsers = this.selectedUsers.filter(id => id !== idStr);
      } else {
        this.selectedUsers.push(idStr);
      }
    },

    handleUserCellClick(canToggle, userId) {
      if (this.selectMode && canToggle) {
        this.toggleUserSelection(userId);
      }
    },

    previewAvatar(url, name = 'User profile') {
      if (!url) return;
      this.avatarPreview.url = url;
      this.avatarPreview.title = name || 'User profile';
      this.avatarPreview.open = true;
    },

    closeAvatarPreview() {
      this.avatarPreview.open = false;
      this.avatarPreview.url = '';
      this.avatarPreview.title = '';
    },

    async performBulkAction(action) {
      if (!this.selectedUsers.length) return;
      if (!await window.confirmModal('Are you sure you want to perform this bulk action?')) return;

      try {
        const res = await fetch(`/admin/users/bulk-action`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': window.csrf(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: action, users: this.selectedUsers }),
        });
        const data = await res.json();
        if (!res.ok) throw data;

        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: data.message, type: 'success' } }));
        setTimeout(() => window.location.reload(), 800);
      } catch(err) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
      }
    },

    async toggleActive(userId, currentState) {
      try {
        const res = await fetch(`/admin/users/${userId}/toggle-active`, {
          method: 'PATCH',
          headers: { 'X-CSRF-TOKEN': window.csrf(), 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (! res.ok) throw data;

        const dot  = document.getElementById(`status-dot-${userId}`);
        const text = document.getElementById(`status-text-${userId}`);
        const btn  = document.getElementById(`status-btn-${userId}`);

        if (data.is_active) {
          dot.className  = 'w-2 h-2 rounded-full bg-emerald-500';
          text.textContent = 'Active';
          btn.className  = btn.className.replace('text-slate-400', 'text-emerald-600');
        } else {
          dot.className  = 'w-2 h-2 rounded-full bg-slate-300';
          text.textContent = 'Frozen';
          btn.className  = btn.className.replace('text-emerald-600', 'text-slate-400');
        }

        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: data.message, type: 'success' } }));
      } catch(err) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
      }
    },

    openResetPassword(userId) {
      this.activeUserId = userId;
      this.resetPassword = '';
      this.resetPasswordConfirm = '';
      this.showResetModal = true;
    },

    async submitResetPassword() {
      if (this.resetPassword !== this.resetPasswordConfirm) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Passwords do not match.', type: 'error' } }));
        return;
      }
      try {
        const res = await fetch(`/admin/users/${this.activeUserId}/reset-password`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': window.csrf(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ password: this.resetPassword, password_confirmation: this.resetPasswordConfirm }),
        });
        const data = await res.json();
        if (! res.ok) throw data;
        this.showResetModal = false;
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: data.message, type: 'success' } }));
      } catch(err) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
      }
    },

    async deleteUser(userId, name) {
      if (! await window.confirmModal(`Delete user "${name}"? This cannot be undone.`)) return;
      try {
        const res = await fetch(`/admin/users/${userId}`, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': window.csrf(), 'Accept': 'application/json' },
        });
        if (! res.ok) throw await res.json();
        document.getElementById(`user-row-${userId}`)?.remove();
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: `"${name}" deleted.`, type: 'success' } }));
      } catch(err) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
      }
    },
  };
}
</script>
@endpush
