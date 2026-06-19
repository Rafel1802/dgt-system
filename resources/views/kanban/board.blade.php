@extends('layouts.app')
@section('title', 'Kanban Board')
@section('page_title', 'Kanban Board')

@push('head')
<style>
[x-cloak] { display: none !important; }
</style>
@endpush

@section('content')
<div x-data="kanbanBoard()" x-init="init()" class="relative">

  {{-- ── Toolbar ──────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center gap-3 mb-4">
    {{-- Search --}}
    <div class="relative flex-1 min-w-[200px] max-w-xs">
      <input type="search" x-model="searchQuery" placeholder="Search tasks…"
             class="form-input pl-9 py-2 text-sm" id="kanban-search">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
      </svg>
    </div>

    {{-- Label filter --}}
    <select x-model="filterLabel" class="form-input py-2 text-sm w-auto" id="filter-label">
      <option value="">All Labels</option>
      @foreach($labels as $lbl)
        <option value="{{ $lbl['value'] }}">{{ $lbl['label'] }}</option>
      @endforeach
    </select>

    {{-- Priority filter --}}
    <select x-model="filterPriority" class="form-input py-2 text-sm w-auto" id="filter-priority">
      <option value="">All Priority</option>
      @foreach($priorities as $p)
        <option value="{{ $p->value }}">{{ $p->label() }}</option>
      @endforeach
    </select>

    {{-- Assignee filter --}}
    <select x-model="filterAssignee" class="form-input py-2 text-sm w-auto" id="filter-assignee">
      <option value="">All Assignees</option>
      @foreach($users as $u)
        <option value="{{ $u->id }}">{{ $u->name }}</option>
      @endforeach
    </select>

    @can('kanban.create')
    <button @click="createModal = true" class="btn btn-primary ml-auto" id="btn-new-card">
      <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
      </svg>
      New Task
    </button>
    @endcan
  </div>

  {{-- ── Board Columns ────────────────────────────────────────────────── --}}
  <div class="kanban-board">
    @foreach($columns as $statusValue => $col)
    @php $status = $col['status']; $cards = $col['cards']; @endphp
    <div class="kanban-col" data-status="{{ $statusValue }}">

      {{-- Column Header --}}
      <div class="kanban-col-header">
        <div class="kanban-col-title">
          <span class="kanban-col-dot"></span>
          {{ $status->label() }}
        </div>
        <span class="kanban-col-count">{{ $cards->count() }}</span>
      </div>

      {{-- Cards List --}}
      <div class="kanban-cards" data-kanban-column data-status="{{ $statusValue }}" id="col-{{ $statusValue }}">
        @foreach($cards as $card)
        @include('kanban.partials.card', ['card' => $card])
        @endforeach
      </div>

    </div>
    @endforeach
  </div>

  {{-- ── Create Card Modal ────────────────────────────────────────────── --}}
  <div x-show="createModal" x-cloak class="modal-overlay" @keydown.escape.window="createModal = false">
    <div class="modal-box max-w-lg" @click.stop>
      <div class="modal-header">
        <div>
          <h3 class="font-display font-bold text-slate-800 text-lg">New Task</h3>
          <p class="text-slate-400 text-sm mt-0.5">Create a new Kanban task card</p>
        </div>
        <button @click="createModal = false" class="btn btn-secondary btn-icon ml-auto flex-shrink-0">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 overflow-y-auto space-y-4">
        {{-- Title --}}
        <div>
          <label class="form-label">Title <span class="text-red-500">*</span></label>
          <input type="text" x-model="form.title" class="form-input" placeholder="Task title…" id="card-title" maxlength="255">
          <p x-show="formErrors.title" x-text="formErrors.title?.[0]" class="form-error" x-cloak></p>
        </div>

        {{-- Label + Sub-label --}}
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="form-label">Label <span class="text-red-500">*</span></label>
            <select x-model="form.label" @change="onLabelChange(form.label)" class="form-input" id="card-label">
              <option value="">Select label…</option>
              @foreach($labels as $lbl)
                <option value="{{ $lbl['value'] }}">{{ $lbl['label'] }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Sub-label</label>
            <select x-model="form.sub_label" class="form-input" id="card-sub-label" :disabled="!subLabels.length">
              <option value="">None</option>
              <template x-for="sl in subLabels" :key="sl">
                <option :value="sl" x-text="sl"></option>
              </template>
            </select>
          </div>
        </div>

        {{-- Priority + Deadline --}}
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="form-label">Priority</label>
            <select x-model="form.priority" class="form-input" id="card-priority">
              @foreach($priorities as $p)
                <option value="{{ $p->value }}">{{ $p->label() }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Deadline</label>
            <input type="date" x-model="form.deadline" class="form-input" id="card-deadline"
                   min="{{ now()->toDateString() }}">
          </div>
        </div>

        {{-- Assignees --}}
        <div>
          <label class="form-label">Assignees</label>
          <select x-model="form.assignees" multiple class="form-input" style="height: 90px;" id="card-assignees">
            @foreach($users as $u)
              <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->roles->first()?->name ?? 'no role' }})</option>
            @endforeach
          </select>
          <p class="text-xs text-slate-400 mt-1">Hold Ctrl/Cmd to select multiple</p>
        </div>

        {{-- Description --}}
        <div>
          <label class="form-label">Description</label>
          <textarea x-model="form.description" rows="3" class="form-input" placeholder="Describe the task…" id="card-description"></textarea>
        </div>

        {{-- Actions --}}
        <div class="flex gap-3 pt-2">
          <button @click="createModal = false" class="btn btn-secondary flex-1">Cancel</button>
          <button @click="submitCreate()" :disabled="formLoading" class="btn btn-primary flex-1">
            <svg x-show="formLoading" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" x-cloak>
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span x-show="!formLoading">Create Task</span>
            <span x-show="formLoading" x-cloak>Creating…</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- ── Card Detail Modal ─────────────────────────────────────────────── --}}
  @include('kanban.partials.card-modal')

  {{-- ── Toast Notifications ──────────────────────────────────────────── --}}
  @include('kanban.partials.toast')

</div>
@endsection

@push('scripts')
<script>
// Pass users data to Alpine for the board
window.kanbanUsers = @json($users->map(fn($u) => ['id'=>$u->id,'name'=>$u->name,'avatar_url'=>$u->avatar_url]));
</script>
@endpush
