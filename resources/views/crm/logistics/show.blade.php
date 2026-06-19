@extends('layouts.app')
@section('title', ($logistic->order_id ?? 'Shipment #'.$logistic->id) . ' — Logistic')
@section('page_title', 'Shipment Detail')

@section('content')
<div x-data="logisticDetail({{ $logistic->id }})" class="animate-fade-in">

  {{-- Breadcrumb + actions --}}
  <div class="mb-5 flex items-center justify-between flex-wrap gap-3">
    <a href="{{ route('crm.logistics.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Logistic CRM</a>
    <div class="flex gap-2 flex-wrap">
      <a href="{{ route('crm.logistics.edit', $logistic) }}" class="btn btn-secondary text-sm">Edit Shipment</a>
      @if(!$logistic->status?->isTerminal())
        <button @click="showStatusModal = true" class="btn btn-primary text-sm" id="btn-push-status">
          📍 Update Status
        </button>
      @endif
      @if(!$logistic->delivery_proof && !$logistic->status?->isTerminal())
        <button @click="showProofUpload = true" class="btn text-sm bg-emerald-600 hover:bg-emerald-700 text-white" id="btn-upload-proof">
          📸 Upload Delivery Proof
        </button>
      @endif
    </div>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- ── LEFT: Shipment Info Cards ────────────────────────────────────────── --}}
    <div class="xl:col-span-1 space-y-4">

      {{-- Status Header --}}
      <div class="card">
        <div class="h-2 -mx-5 -mt-5 mb-4 rounded-t-2xl"
             style="background:{{ $logistic->status?->color() ?? '#94a3b8' }}"></div>

        <div class="text-center pb-2">
          <div class="w-16 h-16 rounded-full flex items-center justify-center text-4xl mx-auto mb-3"
               style="background:{{ $logistic->status?->color() }}18">
            {{ $logistic->status?->icon() ?? '📦' }}
          </div>
          <h2 class="font-display font-bold text-slate-800 text-lg">
            {{ $logistic->order_id ?? 'Shipment #'.$logistic->id }}
          </h2>
          <div class="mt-2">
            <span class="text-sm font-semibold" style="color:{{ $logistic->status?->color() }}">
              {{ $logistic->status?->label() }}
            </span>
          </div>
        </div>

        <div class="mt-4 space-y-2.5 border-t border-slate-100 pt-4 text-sm">
          <div class="flex items-start gap-2">
            <span class="w-5 text-slate-400 shrink-0">👤</span>
            <div>
              <p class="font-semibold text-slate-800">{{ $logistic->recipient_name }}</p>
              <a href="tel:{{ $logistic->recipient_phone }}" class="text-slate-500 hover:text-indigo-600">{{ $logistic->recipient_phone }}</a>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <span class="w-5 text-slate-400 shrink-0">📍</span>
            <p class="text-slate-600 leading-snug">{{ $logistic->shipping_address }}</p>
          </div>
          @if($logistic->product)
          <div class="flex items-center gap-2">
            <span class="w-5">{{ $logistic->product->category?->icon() }}</span>
            <span class="text-slate-700">{{ $logistic->product->name }}</span>
          </div>
          @elseif($logistic->product_description)
          <div class="flex items-center gap-2">
            <span class="w-5 text-slate-400">📦</span>
            <span class="text-slate-600">{{ $logistic->product_description }}</span>
          </div>
          @endif
          @if($logistic->customer)
          <div class="flex items-center gap-2">
            <span class="w-5 text-slate-400">🏢</span>
            <a href="{{ route('crm.customers.show', $logistic->customer) }}" class="text-indigo-600 hover:underline">
              {{ $logistic->customer->name }}
            </a>
          </div>
          @endif
          @if($logistic->ebayOrder)
          <div class="flex items-center gap-2">
            <span class="w-5 text-slate-400">🛒</span>
            <a href="{{ route('crm.ebay.show', $logistic->ebayOrder->offer) }}" class="text-amber-600 hover:underline text-xs font-mono">
              eBay: {{ $logistic->ebayOrder->ebay_order_id ?? '#'.$logistic->ebayOrder->id }}
            </a>
          </div>
          @endif
        </div>
      </div>

      {{-- Tracking Card --}}
      <div class="card">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-4">Tracking</h4>
        @if($logistic->tracking_number)
        <div class="bg-slate-900 rounded-xl px-4 py-3 flex items-center gap-3 mb-3">
          <span class="text-lg">🔢</span>
          <div>
            <p class="text-xs text-slate-400">Tracking Number</p>
            <p class="font-mono font-bold text-white tracking-wider text-sm">{{ $logistic->tracking_number }}</p>
          </div>
        </div>
        @else
        <div class="bg-slate-50 rounded-xl px-4 py-3 text-center text-sm text-slate-400 mb-3">
          No tracking number yet
        </div>
        @endif
        <div class="space-y-2 text-sm">
          @if($logistic->pickup_datetime)
          <div class="flex justify-between">
            <span class="text-slate-500">Pickup</span>
            <span class="font-semibold text-slate-700">{{ $logistic->pickup_datetime->format('d M Y, g:ia') }}</span>
          </div>
          @endif
          @if($logistic->estimated_arrival)
          @php $overdue = $logistic->estimated_arrival->isPast() && !$logistic->status?->isTerminal(); @endphp
          <div class="flex justify-between">
            <span class="text-slate-500">ETA</span>
            <span class="font-semibold {{ $overdue ? 'text-red-600' : 'text-slate-700' }}">
              {{ $overdue ? '⚠️ ' : '' }}{{ $logistic->estimated_arrival->format('d M Y') }}
            </span>
          </div>
          @endif
          @if($logistic->actual_arrival)
          <div class="flex justify-between">
            <span class="text-slate-500">Arrived</span>
            <span class="font-semibold text-emerald-600">{{ $logistic->actual_arrival->format('d M Y') }}</span>
          </div>
          @endif
        </div>
      </div>

      {{-- Truck & Driver --}}
      @if($logistic->truck_company || $logistic->driver_name)
      <div class="card">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-4">🚛 Truck & Driver</h4>
        <div class="space-y-2.5 text-sm">
          @if($logistic->truck_company)
          <div class="flex items-center gap-2">
            <span class="w-5 text-slate-400">🏢</span>
            <span class="text-slate-700">{{ $logistic->truck_company }}</span>
          </div>
          @endif
          @if($logistic->driver_name)
          <div class="flex items-center gap-2">
            <span class="w-5 text-slate-400">👨‍✈️</span>
            <span class="text-slate-700">{{ $logistic->driver_name }}</span>
          </div>
          @endif
          @if($logistic->driver_phone)
          <div class="flex items-center gap-2">
            <span class="w-5 text-slate-400">📞</span>
            <a href="tel:{{ $logistic->driver_phone }}" class="text-indigo-600 hover:underline">{{ $logistic->driver_phone }}</a>
          </div>
          @endif
        </div>
      </div>
      @endif

      {{-- Shipping Cost --}}
      @if($logistic->shipping_budget || $logistic->final_shipping_cost)
      <div class="card">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-4">💰 Shipping Cost</h4>
        <div class="space-y-2 text-sm">
          @if($logistic->shipping_budget)
          <div class="flex justify-between">
            <span class="text-slate-500">Budget</span>
            <span class="font-semibold text-slate-700">${{ number_format($logistic->shipping_budget, 2) }}</span>
          </div>
          @endif
          @if($logistic->final_shipping_cost)
          <div class="flex justify-between border-t border-slate-100 pt-2">
            <span class="text-slate-500">Final Cost</span>
            <span class="font-bold text-emerald-700">${{ number_format($logistic->final_shipping_cost, 2) }}</span>
          </div>
          @php $saved = ($logistic->shipping_budget ?? 0) - ($logistic->final_shipping_cost ?? 0); @endphp
          @if($logistic->shipping_budget && $saved != 0)
          <div class="flex justify-between">
            <span class="text-xs text-slate-400">{{ $saved > 0 ? 'Under budget' : 'Over budget' }}</span>
            <span class="text-xs font-semibold {{ $saved > 0 ? 'text-emerald-600' : 'text-red-500' }}">
              {{ $saved > 0 ? '−' : '+' }}${{ number_format(abs($saved), 2) }}
            </span>
          </div>
          @endif
          @endif
        </div>
      </div>
      @endif

      {{-- Delivery Proof --}}
      @if($logistic->delivery_proof)
      <div class="card border-2 border-emerald-200 bg-emerald-50">
        <h4 class="font-bold text-emerald-800 mb-3">✅ Delivery Confirmed</h4>
        @if(Str::endsWith($logistic->delivery_proof, ['.jpg','.jpeg','.png','.webp']))
        <a href="{{ $logistic->delivery_proof_url }}" target="_blank">
          <img src="{{ $logistic->delivery_proof_url }}" alt="Delivery Proof"
               class="w-full rounded-xl object-cover max-h-40 border border-emerald-200">
        </a>
        @else
        <a href="{{ $logistic->delivery_proof_url }}" target="_blank"
           class="btn btn-secondary w-full text-sm">📄 View Delivery Proof</a>
        @endif
        @if($logistic->actual_arrival)
        <p class="text-xs text-emerald-700 mt-2 text-center">Delivered on {{ $logistic->actual_arrival->format('d M Y') }}</p>
        @endif
      </div>
      @endif

      @if($logistic->assignee)
      <div class="card flex items-center gap-3">
        <img src="{{ $logistic->assignee->avatar_url }}" class="w-9 h-9 rounded-full">
        <div>
          <p class="text-xs text-slate-400">Assigned to</p>
          <p class="text-sm font-semibold text-slate-700">{{ $logistic->assignee->name }}</p>
        </div>
      </div>
      @endif
    </div>

    {{-- ── RIGHT: Status Pipeline + Timeline ───────────────────────────────── --}}
    <div class="xl:col-span-2 space-y-5">

      {{-- Visual Pipeline Steps --}}
      <div class="card">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-5">Delivery Progress</h4>
        <div class="relative">
          {{-- Line connector --}}
          <div class="absolute left-4 top-4 bottom-4 w-0.5 bg-slate-100 z-0"></div>
          <div class="space-y-3 relative z-10">
            @foreach($statuses as $s)
            @php
              $isDone   = false;
              $isCurrent = $logistic->status?->value === $s->value;
              // Check if this step was completed (find in updates)
              $stepDone = $logistic->updates->contains(fn($u) => $u->status?->value === $s->value);
            @endphp
            <div class="flex items-start gap-4">
              {{-- Step indicator --}}
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm shrink-0 border-2 transition-all
                          {{ $isCurrent ? 'text-white shadow-md border-transparent scale-110' : ($stepDone ? 'text-white border-transparent' : 'bg-white border-slate-200 text-slate-300') }}"
                   style="{{ $isCurrent || $stepDone ? 'background:'.$s->color().'; border-color:'.$s->color() : '' }}">
                @if($stepDone && !$isCurrent)✓@else{{ $s->icon() }}@endif
              </div>
              <div class="flex-1 pb-1">
                <div class="flex items-center justify-between">
                  <p class="text-sm font-semibold {{ $isCurrent ? 'text-slate-900' : ($stepDone ? 'text-slate-600' : 'text-slate-300') }}">
                    {{ $s->label() }}
                  </p>
                  @if($isCurrent)
                  <span class="badge text-xs animate-pulse" style="background:{{ $s->color() }}22; color:{{ $s->color() }}">Current</span>
                  @endif
                </div>
                {{-- Show update note if available --}}
                @php $update = $logistic->updates->where('status.value', $s->value)->first(); @endphp
                @if($update)
                <p class="text-xs text-slate-400 mt-0.5">{{ $update->occurred_at?->format('d M Y, g:ia') }} · {{ $update->user?->name }}</p>
                @if($update->notes)
                <p class="text-xs text-slate-500 mt-0.5 italic">{{ $update->notes }}</p>
                @endif
                @endif
              </div>
            </div>
            @endforeach
          </div>
        </div>
        @if(!$logistic->status?->isTerminal())
        <div class="mt-5 pt-4 border-t border-slate-100">
          <button @click="showStatusModal = true" class="btn btn-primary text-sm w-full" id="btn-update-status-bottom">
            📍 Push Next Status Update
          </button>
        </div>
        @endif
      </div>

      {{-- Activity Log / Update Timeline --}}
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-semibold text-slate-700">Update History</h4>
          <span class="badge badge-indigo text-xs">{{ $logistic->updates->count() }} entries</span>
        </div>
        <div class="space-y-4">
          @forelse($logistic->updates->sortByDesc('occurred_at') as $update)
          <div class="flex gap-3">
            <div class="flex flex-col items-center shrink-0">
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm"
                   style="background:{{ $update->status?->color() ?? '#94a3b8' }}">
                {{ $update->status?->icon() ?? '📍' }}
              </div>
            </div>
            <div class="flex-1">
              <div class="flex items-center gap-2 flex-wrap mb-1">
                <span class="text-sm font-semibold" style="color:{{ $update->status?->color() }}">
                  {{ $update->status?->label() }}
                </span>
                <span class="text-xs text-slate-400">{{ $update->occurred_at?->format('d M Y, g:ia') }}</span>
              </div>
              @if($update->notes)
              <div class="bg-slate-50 rounded-xl px-3 py-2 text-sm text-slate-700">{{ $update->notes }}</div>
              @endif
              @if($update->attachment)
              <a href="{{ asset('storage/'.$update->attachment) }}" target="_blank"
                 class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline mt-1">
                📎 View Attachment
              </a>
              @endif
              <div class="flex items-center gap-1 mt-1">
                <img src="{{ $update->user?->avatar_url }}" class="w-4 h-4 rounded-full">
                <span class="text-xs text-slate-400">{{ $update->user?->name }}</span>
              </div>
            </div>
          </div>
          @empty
          <p class="text-slate-400 text-sm text-center py-4">No updates logged yet.</p>
          @endforelse
        </div>
      </div>

      {{-- Notes --}}
      @if($logistic->notes)
      <div class="card">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Notes</h4>
        <p class="text-sm text-slate-700">{{ $logistic->notes }}</p>
      </div>
      @endif
    </div>
  </div>

  {{-- ── Status Update Modal ─────────────────────────────────────────────── --}}
  <div x-show="showStatusModal" x-cloak class="modal-overlay" @keydown.escape.window="showStatusModal = false">
    <div class="modal-box max-w-lg" @click.stop>
      <div class="modal-header">
        <h3 class="font-display font-bold text-slate-800">📍 Push Status Update</h3>
        <button @click="showStatusModal = false" class="btn btn-secondary btn-icon ml-auto">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="form-label">New Status <span class="text-red-500">*</span></label>
          <select x-model="statusForm.status" class="form-input" id="modal-new-status">
            <option value="">— Select new status —</option>
            @foreach($statuses as $s)
            <option value="{{ $s->value }}"
                    {{ $logistic->status?->value === $s->value ? 'disabled' : '' }}>
              {{ $s->icon() }} {{ $s->label() }}
              {{ $logistic->status?->value === $s->value ? '(Current)' : '' }}
            </option>
            @endforeach
          </select>
        </div>

        {{-- Tracking number field (shows when relevant) --}}
        <div x-show="statusForm.status === 'tracking_received' || statusForm.status === 'shipping_started'">
          <label class="form-label">Tracking Number</label>
          <input type="text" x-model="statusForm.tracking_number" class="form-input font-mono"
                 placeholder="TRK-XXXXXXXXX">
          <p class="text-xs text-slate-400 mt-1">Will be saved to the shipment record.</p>
        </div>

        <div>
          <label class="form-label">Update Notes</label>
          <textarea x-model="statusForm.notes" rows="3" class="form-input"
                    placeholder="What happened? Any updates from driver/client?"></textarea>
        </div>

        <div class="flex gap-3 pt-2">
          <button @click="showStatusModal = false" class="btn btn-secondary flex-1">Cancel</button>
          <button @click="pushStatus()" :disabled="loading || !statusForm.status"
                  class="btn btn-primary flex-1" id="btn-confirm-status">
            <span x-show="!loading">Update Status</span>
            <span x-show="loading" x-cloak>Saving…</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- ── Delivery Proof Upload Modal ──────────────────────────────────────── --}}
  <div x-show="showProofUpload" x-cloak class="modal-overlay" @keydown.escape.window="showProofUpload = false">
    <div class="modal-box max-w-md" @click.stop>
      <div class="modal-header">
        <h3 class="font-display font-bold text-slate-800">📸 Upload Delivery Proof</h3>
        <button @click="showProofUpload = false" class="btn btn-secondary btn-icon ml-auto">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <p class="text-sm text-slate-600">Upload a photo or PDF confirming the delivery. This will automatically mark the shipment as <strong>Delivered</strong>.</p>

        {{-- Drop zone --}}
        <label for="proof-file-input"
               class="block border-2 border-dashed border-slate-300 rounded-xl p-8 text-center cursor-pointer hover:border-emerald-400 hover:bg-emerald-50 transition-colors"
               x-bind:class="proofFile ? 'border-emerald-400 bg-emerald-50' : ''">
          <div class="text-3xl mb-2" x-show="!proofFile">📸</div>
          <div class="text-3xl mb-2" x-show="proofFile" x-cloak>✅</div>
          <p class="text-sm text-slate-600" x-show="!proofFile">Click to select or drag & drop</p>
          <p class="text-sm font-semibold text-emerald-700" x-show="proofFile" x-cloak x-text="proofFile?.name"></p>
          <p class="text-xs text-slate-400 mt-1">JPG, PNG or PDF — max 10MB</p>
          <input id="proof-file-input" type="file" accept=".jpg,.jpeg,.png,.pdf"
                 class="sr-only" @change="proofFile = $event.target.files[0]">
        </label>

        <div class="flex gap-3">
          <button @click="showProofUpload = false" class="btn btn-secondary flex-1">Cancel</button>
          <button @click="uploadProof()" :disabled="!proofFile || loading"
                  class="btn flex-1 bg-emerald-600 hover:bg-emerald-700 text-white disabled:opacity-50" id="btn-upload-confirm">
            <span x-show="!loading">Confirm Delivery</span>
            <span x-show="loading" x-cloak>Uploading…</span>
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
function logisticDetail(logisticId) {
  return {
    showStatusModal:  false,
    showProofUpload:  false,
    loading:          false,
    proofFile:        null,
    statusForm: {
      status:          '',
      notes:           '',
      tracking_number: '',
    },

    async pushStatus() {
      if (!this.statusForm.status) return;
      this.loading = true;
      try {
        await api(`/crm/logistics/${logisticId}/status`, {
          method: 'POST',
          body: JSON.stringify(this.statusForm),
        });
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Status updated!', type: 'success' } }));
        this.showStatusModal = false;
        setTimeout(() => location.reload(), 800);
      } catch(e) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: e.message || 'Failed to update.', type: 'error' } }));
      } finally { this.loading = false; }
    },

    async uploadProof() {
      if (!this.proofFile) return;
      this.loading = true;
      try {
        const formData = new FormData();
        formData.append('proof', this.proofFile);
        formData.append('_token', document.querySelector('meta[name=csrf-token]').content);

        const res = await fetch(`/crm/logistics/${logisticId}/proof`, {
          method: 'POST',
          body: formData,
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Upload failed.');

        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: '✅ Delivered! Proof saved.', type: 'success' } }));
        this.showProofUpload = false;
        setTimeout(() => location.reload(), 1000);
      } catch(e) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: e.message || 'Upload failed.', type: 'error' } }));
      } finally { this.loading = false; }
    },
  };
}
</script>
@endpush
