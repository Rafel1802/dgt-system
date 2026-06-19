@extends('layouts.app')
@section('title', 'Sales Pipeline')
@section('page_title', 'Sales Pipeline')

@section('content')
<div x-data="salesPipeline()" x-init="init()" class="animate-fade-in">

  {{-- Toolbar --}}
  <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
    <div>
      <p class="text-slate-400 text-sm">Active pipeline value: <strong class="text-slate-700">${{ number_format($totalActive, 0) }} AUD</strong></p>
    </div>
    <div class="flex gap-2">
      <a href="{{ route('crm.customers.index') }}" class="btn btn-secondary text-sm">📋 Customer List</a>
      @can('create', \App\Models\Customer::class)
      <button @click="showAddDeal = true" class="btn btn-primary text-sm" id="btn-add-deal">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Add Deal
      </button>
      @endcan
    </div>
  </div>

  {{-- ── Pipeline Columns ──────────────────────────────────────────────────── --}}
  <div class="kanban-board">
    @foreach($pipeline as $stageValue => $col)
    @php $stage = $col['stage']; @endphp
    <div class="kanban-col" data-stage="{{ $stageValue }}"
         style="border-top: 3px solid {{ $stage->color() }}">

      <div class="kanban-col-header">
        <div class="kanban-col-title" style="color: {{ $stage->color() }}">
          <span class="kanban-col-dot" style="background: {{ $stage->color() }}"></span>
          {{ $stage->label() }}
        </div>
        <div class="flex items-center gap-1.5">
          <span class="kanban-col-count">{{ $col['count'] }}</span>
          @if($col['total_value'] > 0)
            <span class="text-xs text-slate-400">${{ number_format($col['total_value'], 0) }}</span>
          @endif
        </div>
      </div>

      <div class="kanban-cards" data-pipeline-column data-stage="{{ $stageValue }}" id="pipeline-col-{{ $stageValue }}">
        @foreach($col['deals'] as $deal)
        @include('crm.partials.deal-card', ['deal' => $deal])
        @endforeach
      </div>
    </div>
    @endforeach
  </div>

  {{-- ── Add Deal Modal ────────────────────────────────────────────────────── --}}
  <div x-show="showAddDeal" x-cloak class="modal-overlay" @keydown.escape.window="showAddDeal = false">
    <div class="modal-box max-w-md" @click.stop>
      <div class="modal-header">
        <h3 class="font-display font-bold text-slate-800">New Deal</h3>
        <button @click="showAddDeal = false" class="btn btn-secondary btn-icon ml-auto">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="form-label">Customer <span class="text-red-500">*</span></label>
          <select x-model="dealForm.customer_id" class="form-input" id="deal-customer">
            <option value="">Select customer…</option>
            @foreach($customers as $c)
              <option value="{{ $c->id }}">{{ $c->name }}{{ $c->company ? ' — '.$c->company : '' }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="form-label">Deal Title <span class="text-red-500">*</span></label>
          <input type="text" x-model="dealForm.title" class="form-input" placeholder="e.g. Website Package for Acme" id="deal-title">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="form-label">Value (AUD)</label>
            <input type="number" x-model="dealForm.value" class="form-input" placeholder="0.00" step="0.01">
          </div>
          <div>
            <label class="form-label">Probability %</label>
            <input type="number" x-model="dealForm.probability" class="form-input" placeholder="10" min="0" max="100">
          </div>
        </div>
        <div>
          <label class="form-label">Expected Close Date</label>
          <input type="date" x-model="dealForm.expected_close_date" class="form-input">
        </div>
        <div>
          <label class="form-label">Assigned To</label>
          <select x-model="dealForm.assigned_to" class="form-input">
            <option value="">Unassigned</option>
            @foreach($users as $u)
              <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="flex gap-3 pt-2">
          <button @click="showAddDeal = false" class="btn btn-secondary flex-1">Cancel</button>
          <button @click="submitDeal()" :disabled="dealLoading" class="btn btn-primary flex-1">
            <span x-show="!dealLoading">Create Deal</span>
            <span x-show="dealLoading" x-cloak>Saving…</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  @include('kanban.partials.toast')
</div>
@endsection

@push('scripts')
<script>
function salesPipeline() {
  return {
    showAddDeal: false,
    dealLoading: false,
    dealForm: { customer_id: '', title: '', value: '', probability: 10, expected_close_date: '', assigned_to: '' },

    init() {
      this.$nextTick(() => this.initDragDrop());
    },

    initDragDrop() {
      document.querySelectorAll('[data-pipeline-column]').forEach(col => {
        Sortable.create(col, {
          group: 'pipeline',
          animation: 200,
          ghostClass: 'kanban-card-ghost',
          handle: '.card-drag-handle',
          onEnd: (evt) => this.onDealDrop(evt),
        });
      });
    },

    async onDealDrop(evt) {
      const dealId   = evt.item.dataset.dealId;
      const newStage = evt.to.dataset.stage;
      const position = evt.newIndex;
      try {
        await api(`/crm/pipeline/deals/${dealId}/move`, {
          method: 'PATCH',
          body: JSON.stringify({ stage: newStage, position }),
        });
        this.showToast('Deal moved!', 'success');
      } catch(err) {
        this.showToast(err.message || 'Move failed.', 'error');
        evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
      }
    },

    async submitDeal() {
      if (! this.dealForm.customer_id || ! this.dealForm.title) {
        this.showToast('Customer and title are required.', 'error'); return;
      }
      this.dealLoading = true;
      try {
        const data = await api('/crm/pipeline/deals', {
          method: 'POST',
          body: JSON.stringify(this.dealForm),
        });
        this.showToast('Deal created!', 'success');
        this.showAddDeal = false;
        this.dealForm = { customer_id: '', title: '', value: '', probability: 10, expected_close_date: '', assigned_to: '' };
        setTimeout(() => location.reload(), 800);
      } catch(err) {
        this.showToast(err.message || 'Failed.', 'error');
      } finally {
        this.dealLoading = false;
      }
    },

    showToast(msg, type) {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg, type } }));
    },
  };
}
</script>
@endpush
