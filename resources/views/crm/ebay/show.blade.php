@extends('layouts.app')
@section('title', ($offer->client_name ?? $offer->ebay_username ?? 'Offer') . ' — eBay Offer')
@section('page_title', 'eBay Offer Detail')

@section('content')
<div x-data="ebayOffer({{ $offer->id }})" class="animate-fade-in">

  {{-- Breadcrumb + actions --}}
  <div class="mb-5 flex items-center justify-between flex-wrap gap-3">
    <a href="{{ route('crm.ebay.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to eBay CRM</a>
    <div class="flex gap-2 flex-wrap">
      <a href="{{ route('crm.ebay.edit', $offer) }}" class="btn btn-secondary text-sm">Edit Offer</a>

      {{-- Submit for authorization (non-admin staff) --}}
      @if($offer->status?->value === 'offer_received' || $offer->status?->value === 'inquiry')
        @cannot('authorize-ebay-offers')
        <button @click="submitForAuth()" :disabled="loading" class="btn btn-primary text-sm" id="btn-submit-auth">
          📤 Submit for Authorization
        </button>
        @endcannot
      @endif

      {{-- Authorization buttons: Admin / Boss / Supervisor only --}}
      @if(in_array($offer->authorization_status?->value, ['pending','negotiation']))
        @can('authorize-ebay-offers')
        <button @click="showAuthPanel = true" class="btn btn-primary text-sm bg-amber-500 hover:bg-amber-600" id="btn-open-auth">
          🔐 Review & Authorize
        </button>
        @endcan
      @endif

      {{-- Convert to order (only when authorized) --}}
      @if($offer->authorization_status?->value === 'approved' && !$offer->order)
        <button @click="showConvert = true" class="btn btn-primary text-sm bg-emerald-600 hover:bg-emerald-700" id="btn-convert-order">
          🎯 Convert to Order
        </button>
      @endif
    </div>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- ── LEFT: Offer Details ──────────────────────────────────────────────── --}}
    <div class="xl:col-span-1 space-y-4">

      {{-- Status header card --}}
      <div class="card">
        {{-- Color bar based on status --}}
        <div class="h-2 -mx-5 -mt-5 mb-4 rounded-t-2xl"
             style="background:{{ $offer->status?->color() ?? '#94a3b8' }}"></div>

        <div class="text-center pb-2">
          <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center text-3xl mx-auto mb-3">🛒</div>
          <h2 class="font-display font-bold text-slate-800 text-lg">
            {{ $offer->client_name ?? $offer->ebay_username ?? 'Unknown Buyer' }}
          </h2>
          <div class="flex items-center justify-center gap-2 mt-2 flex-wrap">
            <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full"
                  style="background:{{ $offer->status?->color() }}22; color:{{ $offer->status?->color() }}">
              {{ $offer->status?->label() }}
            </span>
            <span class="badge text-xs {{ $offer->authorization_status?->badgeClass() }}">
              {{ $offer->authorization_status?->label() }}
            </span>
          </div>
        </div>

        <div class="mt-4 space-y-2.5 border-t border-slate-100 pt-4">
          @if($offer->client_email)
          <div class="flex items-center gap-2 text-sm">
            <span class="w-5 text-slate-400">📧</span>
            <a href="mailto:{{ $offer->client_email }}" class="text-slate-700 hover:text-indigo-600 truncate">{{ $offer->client_email }}</a>
          </div>
          @endif
          @if($offer->ebay_username)
          <div class="flex items-center gap-2 text-sm">
            <span class="w-5 text-slate-400">👤</span>
            <span class="text-slate-700 font-mono">{{ '@' . $offer->ebay_username }}</span>
          </div>
          @endif
          @if($offer->ebay_item_id)
          <div class="flex items-center gap-2 text-sm">
            <span class="w-5 text-slate-400">🏷️</span>
            <span class="text-slate-600 font-mono text-xs">Item: {{ $offer->ebay_item_id }}</span>
          </div>
          @endif
          @if($offer->ebay_message_id)
          <div class="flex items-center gap-2 text-sm">
            <span class="w-5 text-slate-400">💬</span>
            <span class="text-slate-600 font-mono text-xs">MSG: {{ $offer->ebay_message_id }}</span>
          </div>
          @endif
          @if($offer->product)
          <div class="flex items-center gap-2 text-sm">
            <span class="w-5">{{ $offer->product->category?->icon() }}</span>
            <span class="text-slate-700">{{ $offer->product->name }}</span>
          </div>
          @endif
          <div class="flex items-center gap-2 text-sm">
            <span class="w-5 text-slate-400">📅</span>
            <span class="text-slate-600">{{ $offer->received_at?->format('d M Y, g:ia') }}</span>
          </div>
        </div>

        @if($offer->handler)
        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center gap-2">
          <img src="{{ $offer->handler->avatar_url }}" class="w-7 h-7 rounded-full">
          <div>
            <p class="text-xs text-slate-400">Logged by</p>
            <p class="text-sm font-semibold text-slate-700">{{ $offer->handler->name }}</p>
          </div>
        </div>
        @endif
      </div>

      {{-- Financials card --}}
      <div class="card">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-4">Financials</h4>
        <div class="space-y-3">
          <div class="flex justify-between items-center">
            <span class="text-sm text-slate-500">Offer Amount</span>
            <span class="font-bold text-slate-800 text-lg">
              {{ $offer->offer_amount ? '$'.number_format($offer->offer_amount, 2) : '—' }}
            </span>
          </div>
          @if($offer->final_amount)
          <div class="flex justify-between items-center border-t border-slate-100 pt-3">
            <span class="text-sm text-slate-500">Final Amount</span>
            <span class="font-bold text-emerald-600 text-lg">${{ number_format($offer->final_amount, 2) }}</span>
          </div>
          @endif
          @if($offer->offer_amount && $offer->final_amount && $offer->final_amount != $offer->offer_amount)
          <div class="flex justify-between items-center">
            <span class="text-xs text-slate-400">Negotiation</span>
            @php $diff = $offer->final_amount - $offer->offer_amount; @endphp
            <span class="text-xs font-semibold {{ $diff > 0 ? 'text-emerald-600' : 'text-red-500' }}">
              {{ $diff > 0 ? '+' : '' }}${{ number_format(abs($diff), 2) }}
            </span>
          </div>
          @endif
          <div class="flex justify-between items-center border-t border-slate-100 pt-3">
            <span class="text-sm text-slate-500">Payment</span>
            @php $payColors = ['paid'=>'text-emerald-600','unpaid'=>'text-red-500','partial'=>'text-amber-600','refunded'=>'text-slate-400']; @endphp
            <span class="text-sm font-semibold {{ $payColors[$offer->payment_status] ?? 'text-slate-600' }}">
              {{ ucfirst($offer->payment_status) }}
            </span>
          </div>
        </div>
      </div>

      {{-- Confirmed Order (if exists) --}}
      @if($offer->order)
      <div class="card border-2 border-emerald-200 bg-emerald-50">
        <div class="flex items-center gap-2 mb-3">
          <span class="text-xl">📦</span>
          <h4 class="font-bold text-emerald-800">Order Confirmed</h4>
        </div>
        <div class="space-y-2 text-sm">
          @if($offer->order->ebay_order_id)
          <div class="flex justify-between">
            <span class="text-slate-500">eBay Order ID</span>
            <span class="font-mono font-semibold text-emerald-700">{{ $offer->order->ebay_order_id }}</span>
          </div>
          @endif
          <div class="flex justify-between">
            <span class="text-slate-500">Sale Amount</span>
            <span class="font-bold text-emerald-700">${{ number_format($offer->order->sale_amount, 2) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Payment</span>
            <span class="font-semibold {{ $payColors[$offer->order->payment_status] ?? '' }}">
              {{ ucfirst($offer->order->payment_status) }}
            </span>
          </div>
        </div>
        <a href="{{ route('crm.logistics.shipments.create') }}"
           class="btn btn-secondary text-xs w-full mt-3">🚛 Create Shipment</a>
      </div>
      @endif
    </div>

    {{-- ── RIGHT: Pipeline + Authorization + Notes ──────────────────────────── --}}
    <div class="xl:col-span-2 space-y-5">

      {{-- Pipeline Status Bar --}}
      <div class="card">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-4">eBay Pipeline Progress</h4>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          @foreach($statuses as $s)
          @php $isActive = $offer->status?->value === $s->value; @endphp
          <div class="relative py-2.5 px-3 rounded-xl text-center text-xs font-semibold transition-all border-2
                      {{ $isActive ? 'text-white shadow-md scale-105 border-transparent' : 'bg-white border-slate-200 text-slate-400' }}"
               style="{{ $isActive ? 'background:'.$s->color().'; border-color:'.$s->color() : '' }}">
            {{ $s->label() }}
            @if($isActive)
            <div class="absolute -top-1.5 left-1/2 -translate-x-1/2 w-3 h-3 rounded-full bg-white border-2 border-current shadow-sm"></div>
            @endif
          </div>
          @endforeach
        </div>
      </div>

      {{-- ⚡ Authorization Panel --}}
      <div class="card @if($offer->authorization_status?->value === 'pending') border-2 border-amber-300 @endif">
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-semibold text-slate-800 flex items-center gap-2">
            🔐 Authorization
            <span class="badge text-xs {{ $offer->authorization_status?->badgeClass() }}">
              {{ $offer->authorization_status?->label() }}
            </span>
          </h4>
          @if($offer->authorized_at)
          <span class="text-xs text-slate-400">{{ $offer->authorized_at->format('d M Y, g:ia') }}</span>
          @endif
        </div>

        {{-- Pending state alert --}}
        @if($offer->authorization_status?->value === 'pending')
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
          <div class="flex items-start gap-3">
            <span class="text-2xl">⏳</span>
            <div>
              <p class="font-semibold text-amber-800 text-sm">Awaiting Authorization</p>
              <p class="text-amber-700 text-xs mt-1">This offer is waiting for review by Hongling or Dennis.</p>
            </div>
          </div>
        </div>
        @endif

        {{-- Approval result --}}
        @if($offer->authorization_status?->value === 'approved')
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 mb-4">
          <div class="flex items-start gap-3">
            <span class="text-2xl">✅</span>
            <div>
              <p class="font-semibold text-emerald-800 text-sm">Offer Approved</p>
              @if($offer->authorizer)
              <p class="text-emerald-700 text-xs mt-0.5">By {{ $offer->authorizer->name }} on {{ $offer->authorized_at?->format('d M Y') }}</p>
              @endif
              @if($offer->final_amount)
              <p class="text-emerald-700 text-xs mt-0.5">Final agreed amount: <strong>${{ number_format($offer->final_amount, 2) }}</strong></p>
              @endif
            </div>
          </div>
        </div>
        @endif

        @if($offer->authorization_status?->value === 'rejected')
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
          <div class="flex items-start gap-3">
            <span class="text-2xl">❌</span>
            <div>
              <p class="font-semibold text-red-800 text-sm">Offer Rejected</p>
              @if($offer->authorizer)
              <p class="text-red-700 text-xs mt-0.5">By {{ $offer->authorizer->name }}</p>
              @endif
            </div>
          </div>
        </div>
        @endif

        @if($offer->authorization_status?->value === 'negotiation')
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-4">
          <div class="flex items-start gap-3">
            <span class="text-2xl">🤝</span>
            <div>
              <p class="font-semibold text-indigo-800 text-sm">Needs Negotiation</p>
              @if($offer->authorizer)
              <p class="text-indigo-700 text-xs mt-0.5">Reviewed by {{ $offer->authorizer->name }}</p>
              @endif
            </div>
          </div>
        </div>
        @endif

        {{-- Authorization notes --}}
        @if($offer->authorization_notes)
        <div class="bg-slate-50 rounded-xl p-3 text-sm text-slate-700">
          <p class="text-xs font-semibold text-slate-400 mb-1">Authorization Notes</p>
          {{ $offer->authorization_notes }}
        </div>
        @endif

        {{-- Hongling / Dennis action buttons (admin role only) --}}
        @if(in_array($offer->authorization_status?->value, ['pending','negotiation']))
        @can('authorize-ebay-offers')
        <div class="mt-4 flex gap-2 flex-wrap">
          <button @click="authDecision = 'approved'; showAuthPanel = true"
                  class="btn text-sm flex-1 bg-emerald-600 hover:bg-emerald-700 text-white" id="btn-approve">
            ✅ Approve
          </button>
          <button @click="authDecision = 'negotiation'; showAuthPanel = true"
                  class="btn text-sm flex-1 bg-indigo-600 hover:bg-indigo-700 text-white" id="btn-negotiate">
            🤝 Need Negotiation
          </button>
          <button @click="authDecision = 'rejected'; showAuthPanel = true"
                  class="btn text-sm flex-1 btn-danger" id="btn-reject">
            ❌ Reject
          </button>
        </div>
        @endcan
        @endif
      </div>

      {{-- Inquiry + Offer Notes --}}
      @if($offer->inquiry_notes || $offer->offer_details)
      <div class="card space-y-4">
        <h4 class="font-semibold text-slate-700">Offer Notes</h4>
        @if($offer->inquiry_notes)
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Inquiry / Message</p>
          <div class="bg-slate-50 rounded-xl px-4 py-3 text-sm text-slate-700">{{ $offer->inquiry_notes }}</div>
        </div>
        @endif
        @if($offer->offer_details)
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Offer Details</p>
          <div class="bg-slate-50 rounded-xl px-4 py-3 text-sm text-slate-700">{{ $offer->offer_details }}</div>
        </div>
        @endif
      </div>
      @endif

    </div>
  </div>

  {{-- ── Authorization Modal (Hongling / Dennis) ────────────────────────────── --}}
  @can('authorize-ebay-offers')
  <div x-show="showAuthPanel" x-cloak class="modal-overlay" @keydown.escape.window="showAuthPanel = false">
    <div class="modal-box max-w-lg" @click.stop>
      <div class="modal-header">
        <h3 class="font-display font-bold text-slate-800">
          <span x-text="authDecision === 'approved' ? '✅ Approve Offer' : authDecision === 'rejected' ? '❌ Reject Offer' : '🤝 Need Negotiation'"></span>
        </h3>
        <button @click="showAuthPanel = false" class="btn btn-secondary btn-icon ml-auto">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="p-6 space-y-4">
        {{-- Offer summary --}}
        <div class="bg-slate-50 rounded-xl p-4 text-sm">
          <div class="flex justify-between mb-1">
            <span class="text-slate-500">Buyer</span>
            <span class="font-semibold">{{ $offer->client_name ?? $offer->ebay_username }}</span>
          </div>
          <div class="flex justify-between mb-1">
            <span class="text-slate-500">Product</span>
            <span class="font-semibold">{{ $offer->product?->name ?? '—' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Offer Amount</span>
            <span class="font-bold text-indigo-700">{{ $offer->offer_amount ? '$'.number_format($offer->offer_amount, 2) : 'No amount' }}</span>
          </div>
        </div>

        {{-- Final amount (for approve / negotiate) --}}
        <div x-show="authDecision !== 'rejected'">
          <label class="form-label">Final Agreed Amount (USD)</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
            <input type="number" x-model="authForm.final_amount" class="form-input pl-7"
                   placeholder="{{ $offer->offer_amount ?? '0.00' }}" step="0.01">
          </div>
          <p class="text-xs text-slate-400 mt-1">Leave blank to use original offer amount.</p>
        </div>

        <div>
          <label class="form-label">Notes <span x-show="authDecision === 'rejected'" class="text-red-500">*</span></label>
          <textarea x-model="authForm.notes" rows="3" class="form-input"
                    :placeholder="authDecision === 'rejected' ? 'Reason for rejection (required)…' : 'Notes for the team…'"></textarea>
        </div>

        <div class="flex gap-3 pt-2">
          <button @click="showAuthPanel = false" class="btn btn-secondary flex-1">Cancel</button>
          <button @click="submitAuth()" :disabled="loading"
                  :class="authDecision === 'rejected' ? 'bg-red-600 hover:bg-red-700 text-white' : authDecision === 'approved' ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white'"
                  class="btn flex-1" id="btn-confirm-auth">
            <span x-show="!loading" x-text="authDecision === 'approved' ? 'Approve' : authDecision === 'rejected' ? 'Reject' : 'Submit'"></span>
            <span x-show="loading" x-cloak>Processing…</span>
          </button>
        </div>
      </div>
    </div>
  </div>
  @endcan

  {{-- ── Convert to Order Modal ─────────────────────────────────────────────── --}}
  @if($offer->authorization_status?->value === 'approved' && !$offer->order)
  <div x-show="showConvert" x-cloak class="modal-overlay" @keydown.escape.window="showConvert = false">
    <div class="modal-box max-w-lg" @click.stop>
      <div class="modal-header">
        <h3 class="font-display font-bold text-slate-800">🎯 Convert to Confirmed Order</h3>
        <button @click="showConvert = false" class="btn btn-secondary btn-icon ml-auto">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <form method="POST" action="{{ route('crm.ebay.convert', $offer) }}" class="p-6 space-y-4">
        @csrf
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm">
          <p class="font-semibold text-emerald-800 mb-1">Authorized by {{ $offer->authorizer?->name }}</p>
          @if($offer->final_amount)
          <p class="text-emerald-700">Agreed price: <strong>${{ number_format($offer->final_amount, 2) }}</strong></p>
          @endif
        </div>
        <div>
          <label class="form-label">eBay Order ID</label>
          <input type="text" name="ebay_order_id" class="form-input" placeholder="e.g. 18-12345-67890" id="field-ebay-order-id">
        </div>
        <div>
          <label class="form-label">Sale Amount (USD) <span class="text-red-500">*</span></label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
            <input type="number" name="sale_amount" value="{{ $offer->final_amount ?? $offer->offer_amount }}"
                   class="form-input pl-7" step="0.01" required>
          </div>
        </div>
        <div>
          <label class="form-label">Payment Status <span class="text-red-500">*</span></label>
          <select name="payment_status" class="form-input" required>
            <option value="unpaid">Unpaid — awaiting payment</option>
            <option value="paid">Paid — payment received</option>
            <option value="partial">Partial — deposit received</option>
          </select>
        </div>
        <div class="flex gap-3 pt-2">
          <button type="button" @click="showConvert = false" class="btn btn-secondary flex-1">Cancel</button>
          <button type="submit" class="btn flex-1 bg-emerald-600 hover:bg-emerald-700 text-white" id="btn-confirm-order">
            Confirm Order
          </button>
        </div>
      </form>
    </div>
  </div>
  @endif

  @include('kanban.partials.toast')
</div>
@endsection

@push('scripts')
<script>
function ebayOffer(offerId) {
  return {
    showAuthPanel: false,
    showConvert:   false,
    loading:       false,
    authDecision:  'approved',
    authForm:      { authorization_status: 'approved', notes: '', final_amount: '' },

    async submitForAuth() {
      this.loading = true;
      try {
        await api(`/crm/ebay/${offerId}/authorize`, {
          method: 'POST',
          body: JSON.stringify({ authorization_status: 'pending', notes: 'Submitted for review.' }),
        });
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Submitted for authorization!', type: 'success' } }));
        setTimeout(() => location.reload(), 900);
      } catch(e) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: e.message || 'Failed.', type: 'error' } }));
      } finally { this.loading = false; }
    },

    async submitAuth() {
      if (this.authDecision === 'rejected' && !this.authForm.notes.trim()) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Rejection reason is required.', type: 'error' } }));
        return;
      }
      this.loading = true;
      try {
        const payload = {
          authorization_status: this.authDecision,
          notes:                this.authForm.notes,
          final_amount:         this.authForm.final_amount || null,
        };
        await api(`/crm/ebay/${offerId}/authorize`, { method: 'POST', body: JSON.stringify(payload) });
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Authorization saved!', type: 'success' } }));
        this.showAuthPanel = false;
        setTimeout(() => location.reload(), 900);
      } catch(e) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: e.message || 'Failed.', type: 'error' } }));
      } finally { this.loading = false; }
    },
  };
}
</script>
@endpush
