@extends('layouts.app')
@section('title', 'Return #' . $return->id)
@section('page_title', 'Machine Return #' . $return->id)
@section('back_url', route('crm.logistics.returns.index'))

@section('content')
@php
  $color = $return->statusColor();
  $statusColors = collect(array_keys($statuses))
      ->mapWithKeys(fn ($k) => [$k => \App\Models\MachineReturn::statuses()[$k] === 'Pending' ? '#f59e0b' : (\App\Models\MachineReturn::statuses()[$k] === 'Pickup Arranged' ? '#3b82f6' : (\App\Models\MachineReturn::statuses()[$k] === 'In Transit' ? '#0ea5e9' : '#10b981'))])
      ->all();
  
  // Actually, better to just use color logic from model
  foreach($statuses as $k => $v) {
      $dummy = new \App\Models\MachineReturn(['status' => $k]);
      $statusColors[$k] = $dummy->statusColor();
  }
@endphp

<div id="crm-logistics-return-detail" class="animate-fade-in live-swap-target" x-data="machineReturn">
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
    
    {{-- ── Left: Customer & Case Info ───────────────────────────────────────── --}}
    <div class="xl:col-span-1 space-y-4">
      <div class="card">
        <div class="h-2 -mx-5 -mt-5 mb-4 rounded-t-2xl"
             style="background:{{ $color }}"
             :style="'background:' + colorFor(currentStatus)"></div>

        <div class="text-center pb-2">
          <div class="w-16 h-16 rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-3"
               style="background:{{ $color }}"
               :style="'background:' + colorFor(currentStatus)">
            {{ strtoupper(substr($return->customer?->name ?? '?', 0, 1)) }}
          </div>
          <h2 class="font-display font-bold text-slate-800 text-lg">{{ $return->customer?->name ?? 'Unknown Customer' }}</h2>
          <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full inline-block mt-1"
                style="background:{{ $color }}22; color:{{ $color }}"
                :style="'background:' + colorFor(currentStatus) + '22; color:' + colorFor(currentStatus)"
                x-text="labelFor(currentStatus)">
            {{ $statuses[$return->status] ?? $return->status }}
          </span>
        </div>

        <div class="mt-4 space-y-2.5 border-t border-slate-100 pt-4">
          @if($return->customer?->email)
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">📧</span>
            <a href="mailto:{{ $return->customer->email }}" class="text-slate-700 hover:text-indigo-600 truncate">{{ $return->customer->email }}</a>
          </div>
          @endif
          @if($return->customer?->phone)
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">📞</span>
            <a href="tel:{{ $return->customer->phone }}" class="text-slate-700 hover:text-indigo-600">{{ $return->customer->phone }}</a>
          </div>
          @endif
          @if($return->customer?->address)
          <div class="flex gap-2 text-sm">
            <span class="text-slate-400 w-5 shrink-0">📍</span>
            <span class="text-slate-600">{{ $return->customer->address }}</span>
          </div>
          @endif
          <div class="flex items-center gap-2 text-sm mt-3 pt-3 border-t border-slate-50">
            <span class="text-slate-400 w-5">🔗</span>
            <a href="{{ route('crm.tech-support.show', $return->tech_support_case_id) }}" class="text-indigo-600 hover:underline">View Original Tech Support Case</a>
          </div>
        </div>

        {{-- Status buttons --}}
        <div class="mt-4 pt-4 border-t border-slate-100">
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Update Return Status</p>
          <div class="grid grid-cols-2 gap-2">
            @foreach($statuses as $key => $label)
            @php $btnColor = $statusColors[$key]; @endphp
            <button type="button"
                    @click="promptStatus('{{ $key }}')"
                    :disabled="statusLoading"
                    class="py-2 px-2 rounded-xl text-xs font-semibold text-center transition-all border-2"
                    :class="currentStatus === '{{ $key }}'
                      ? 'text-white border-transparent shadow-sm'
                      : 'bg-white border-slate-200 text-slate-600 hover:border-slate-300'"
                    :style="currentStatus === '{{ $key }}'
                      ? { backgroundColor: '{{ $btnColor }}', borderColor: '{{ $btnColor }}', color: '#ffffff' }
                      : { backgroundColor: '#ffffff', borderColor: '#e2e8f0', color: '#475569' }">
              {{ $label }}
            </button>
            @endforeach
          </div>
          <p x-show="statusLoading" x-cloak class="text-xs text-slate-400 mt-2">Updating…</p>
        </div>
      </div>
    </div>

    {{-- ── Right: Timeline & Logs ───────────────────────────────────────────── --}}
    <div class="xl:col-span-2 space-y-5">
      
      {{-- Initial Tech Support Reason --}}
      <div class="card bg-amber-50 border-amber-100">
        <h3 class="text-sm font-semibold text-amber-800 flex items-center gap-2 mb-2">
          <span>⚠️</span> Reason for Return (from Tech Support)
        </h3>
        <p class="text-sm text-amber-900 whitespace-pre-wrap">{{ $return->notes ?: 'No specific reason provided by Tech Support.' }}</p>
      </div>

      {{-- Timeline --}}
      <div class="card">
        <div class="flex items-center justify-between mb-6">
          <h4 class="font-semibold text-slate-700">Return Timeline</h4>
          <span class="badge badge-indigo text-xs">{{ $return->logs->count() }} updates</span>
        </div>

        <div class="space-y-0 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-300 before:to-transparent mb-8">
          @forelse($return->logs as $log)
          <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active py-4">
            <!-- Icon -->
            <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 shadow-sm z-10 {{ match($log->status_changed_to) { 'pending' => 'bg-amber-500', 'pickup_arranged' => 'bg-blue-500', 'in_transit' => 'bg-sky-500', 'received' => 'bg-emerald-500', default => 'bg-slate-500' } }} text-white">
              {{ match($log->status_changed_to) { 'pending' => '⏳', 'pickup_arranged' => '📅', 'in_transit' => '🚚', 'received' => '✅', default => '📝' } }}
            </div>
            <!-- Card -->
            <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] bg-white p-4 rounded-xl border border-slate-100 shadow-sm transition-all hover:shadow-md">
              <div class="flex items-center justify-between mb-1">
                <span class="font-semibold text-slate-800 text-sm">
                  {{ $statuses[$log->status_changed_to] ?? 'Note Added' }}
                </span>
                <time class="text-xs text-slate-400">{{ $log->created_at->format('d M, g:ia') }}</time>
              </div>
              <p class="text-slate-600 text-sm whitespace-pre-wrap mt-2">{{ $log->note }}</p>
              
              <div class="flex items-center gap-1.5 mt-3 pt-3 border-t border-slate-50">
                <img src="{{ $log->user?->avatar_url }}" class="w-5 h-5 rounded-full">
                <span class="text-xs font-medium text-slate-500">{{ $log->user?->name ?? 'System' }}</span>
              </div>
            </div>
          </div>
          @empty
          <div class="text-center py-6 text-slate-400 text-sm">No timeline events recorded.</div>
          @endforelse
        </div>
        
        <div x-show="!showAddNote">
          <button @click="showAddNote = true"
                  class="w-full border-2 border-dashed border-slate-200 rounded-xl py-3 text-sm text-slate-400 hover:border-indigo-400 hover:text-indigo-600 transition-colors">
            + Add internal note
          </button>
        </div>

        <form x-show="showAddNote" x-cloak @submit.prevent="submitNote($event)"
              action="{{ route('crm.logistics.returns.status', $return) }}" class="space-y-3">
          <input type="hidden" name="_method" value="PATCH">
          <input type="hidden" name="status" :value="currentStatus">
          <div>
            <label class="form-label text-xs">Note <span class="text-red-500">*</span></label>
            <textarea name="note" rows="3" class="form-input text-sm" placeholder="Add an update without changing the status..." required></textarea>
          </div>
          <div class="flex gap-2">
            <button type="button" @click="showAddNote = false" class="btn btn-cancel btn-secondary text-sm flex-1">Cancel</button>
            <button type="submit" :disabled="statusLoading" class="btn btn-primary text-sm flex-1">
              <span x-show="!statusLoading">Save Note</span>
              <span x-show="statusLoading" x-cloak>Saving…</span>
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

  {{-- Status Update Modal --}}
  <div x-show="showStatusModal" x-cloak class="modal-overlay" @keydown.escape.window="showStatusModal = false">
    <div class="modal-box max-w-lg" @click.stop>
      <div class="modal-header">
        <h3 class="font-display font-bold text-slate-800">Update Return Status</h3>
        <button @click="showStatusModal = false" class="btn btn-secondary btn-icon ml-auto">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <form @submit.prevent="confirmStatusUpdate($event)" action="{{ route('crm.logistics.returns.status', $return) }}">
        <input type="hidden" name="_method" value="PATCH">
        <input type="hidden" name="status" :value="pendingStatus">
        <div class="p-6 space-y-4">
          <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-lg border border-slate-100">
            <span class="text-slate-500 text-sm">Changing status to:</span>
            <span class="badge text-xs px-2 py-0.5 rounded-full text-white font-semibold"
                  :style="'background-color: ' + colorFor(pendingStatus)"
                  x-text="labelFor(pendingStatus)">
            </span>
          </div>
          <div>
            <label class="form-label">Update Note <span class="text-red-500">*</span></label>
            <textarea name="note" x-model="statusNote" rows="3" class="form-input" placeholder="E.g., Pickup scheduled for tomorrow at 2 PM, tracking #123456" required></textarea>
            <p class="text-xs text-slate-400 mt-1">A required note is needed for every step of the return process.</p>
          </div>
          <div class="flex gap-3 pt-2">
            <button type="button" @click="showStatusModal = false" class="btn btn-cancel btn-secondary flex-1">Cancel</button>
            <button type="submit" :disabled="statusLoading" class="btn btn-primary flex-1" :style="'background-color: ' + colorFor(pendingStatus) + '; border-color: ' + colorFor(pendingStatus) + ';'">
              <span x-show="!statusLoading">Confirm Update</span>
              <span x-show="statusLoading" x-cloak>Updating…</span>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  (function() {
    const init = () => {
      Alpine.data('machineReturn', () => ({
        statusLoading: false,
        showStatusModal: false,
        showAddNote: false,
        pendingStatus: '',
        statusNote: '',
        currentStatus: @js($return->status),
        statusLabels: @js($statuses),
        statusColors: @js($statusColors),

        colorFor(status) {
          return (this.statusColors && this.statusColors[status]) ? this.statusColors[status] : '#94a3b8';
        },
        labelFor(status) {
          return (this.statusLabels && this.statusLabels[status]) ? this.statusLabels[status] : status;
        },

        promptStatus(newStatus) {
          if (newStatus === this.currentStatus) return;
          this.pendingStatus = newStatus;
          this.statusNote = '';
          this.showStatusModal = true;
        },

        async confirmStatusUpdate(event) {
          if (!this.statusNote.trim()) {
            window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'An update note is required.', type: 'error' } }));
            return;
          }
          this.statusLoading = true;
          try {
            const fd = new FormData(event.target);
            const res = await fetch(event.target.action, {
              method: 'POST',
              headers: { 'X-CSRF-TOKEN': window.csrf(), 'Accept': 'application/json' },
              body: fd,
            });
            if (!res.ok) {
              const data = await res.json().catch(() => ({}));
              throw new Error(data.message || 'Failed to update status.');
            }
            this.showStatusModal = false;
            window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Status updated!', type: 'success' } }));
            document.dispatchEvent(new CustomEvent('ajax-success'));
          } catch (err) {
            window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
          } finally {
            this.statusLoading = false;
          }
        },

        async submitNote(event) {
          this.statusLoading = true;
          try {
            const fd = new FormData(event.target);
            const res = await fetch(event.target.action, {
              method: 'POST',
              headers: { 'X-CSRF-TOKEN': window.csrf(), 'Accept': 'application/json' },
              body: fd,
            });
            if (!res.ok) {
              const data = await res.json().catch(() => ({}));
              throw new Error(data.message || 'Failed to add note.');
            }
            this.showAddNote = false;
            window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Note added!', type: 'success' } }));
            document.dispatchEvent(new CustomEvent('ajax-success'));
          } catch (err) {
            window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
          } finally {
            this.statusLoading = false;
          }
        }
      }));
    };

    if (window.Alpine) {
      init();
    } else {
      document.addEventListener('alpine:init', init);
    }
  })();
</script>
@endpush
