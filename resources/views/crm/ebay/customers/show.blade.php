@extends('layouts.app')
@section('title', ($record->buyer_name ?: $record->username) . ' — eBay Customer')
@section('page_title', 'eBay Customer — Detail')

@section('content')
<div class="animate-fade-in" x-data="{
  showFollowUp: false,
  fuLoading: false,
  fuNotes: '',
  handlerLoading: false,
  showAddOrder: false,
  orderLoading: false,
  products: [{ name: '', price: '' }],
  addProduct() { this.products.push({ name: '', price: '' }); },
  removeProduct(i) { if (this.products.length > 1) this.products.splice(i, 1); },
  async submitFollowUp() {
    if (!this.fuNotes.trim()) {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Notes are required.', type: 'error' } }));
      return;
    }
    this.fuLoading = true;
    try {
      await window.api('{{ route('crm.ebay.customers.follow-up', $record) }}', { method: 'POST', body: JSON.stringify({ notes: this.fuNotes }) });
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Follow-up saved!', type: 'success' } }));
      setTimeout(() => location.reload(), 700);
    } catch (err) {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
    } finally { this.fuLoading = false; }
  },
  async switchHandler(event) {
    this.handlerLoading = true;
    try {
      const userId = new FormData(event.target).get('user_id');
      await window.api(event.target.action, { method: 'POST', body: JSON.stringify({ user_id: userId }) });
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Handler updated!', type: 'success' } }));
      setTimeout(() => location.reload(), 700);
    } catch (err) {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
    } finally { this.handlerLoading = false; }
  },
  async submitOrder(event) {
    const fd = new FormData(event.target);
    const payload = {
      order_id: fd.get('order_id'),
      order_date: fd.get('order_date'),
      order_store_id: fd.get('order_store_id'),
      products: this.products.filter(p => p.name.trim() && String(p.price).trim() !== ''),
    };
    if (!payload.order_id || payload.products.length === 0) {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Order ID and at least one product with a price are required.', type: 'error' } }));
      return;
    }
    this.orderLoading = true;
    try {
      await window.api(event.target.action, { method: 'POST', body: JSON.stringify(payload) });
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Order added!', type: 'success' } }));
      setTimeout(() => location.reload(), 700);
    } catch (err) {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
    } finally { this.orderLoading = false; }
  },
}">

  <div class="mb-5 flex items-center justify-between flex-wrap gap-3">
    <a href="{{ route('crm.ebay.customers.index', ['tab_type' => $record->tab_type]) }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Records</a>
    <a href="{{ route('crm.ebay.customers.edit', $record) }}" class="btn btn-secondary text-sm">Edit Record</a>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- ── Left: Customer Summary ──────────────────────────────────────────── --}}
    <div class="xl:col-span-1 space-y-4">
      <div class="card">
        @php $tabColor = \App\Models\EbayCustomerRecord::tabColor($record->tab_type); @endphp
        <div class="h-2 -mx-5 -mt-5 mb-4 rounded-t-2xl" style="background:{{ $tabColor }}"></div>

        <div class="text-center pb-2">
          <div class="w-16 h-16 rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-3"
               style="background:{{ $tabColor }}">
            {{ strtoupper(substr($record->buyer_name ?: $record->username, 0, 1)) }}
          </div>
          <h2 class="font-display font-bold text-slate-800 text-lg">{{ $record->buyer_name ?: $record->username }}</h2>
          <div class="flex items-center justify-center gap-2 mt-1 flex-wrap">
            <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full" style="background:{{ $tabColor }}22; color:{{ $tabColor }}">
              {{ $tabs[$record->tab_type] ?? $record->tab_type }}
            </span>
            @if($record->techSupportCase?->occurrence_label)
              <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700" title="Repeat technical issue">
                🔁 {{ $record->techSupportCase->occurrence_label }}
              </span>
            @endif
            @if($record->shipment_delay)
            <span class="badge text-xs px-2 py-0.5 rounded-full"
                  style="background:{{ \App\Models\EbayCustomerRecord::LOGISTIC_ISSUES_COLOR }}22; color:{{ \App\Models\EbayCustomerRecord::LOGISTIC_ISSUES_COLOR }}">
              ⚠ Logistic Issues
            </span>
            @endif
          </div>
        </div>

        <div class="mt-4 space-y-2.5 border-t border-slate-100 pt-4">
          @if($record->username)
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">🛒</span>
            <span class="text-slate-700">@{{ $record->username }}</span>
          </div>
          @endif
          @if($record->email)
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">📧</span>
            <a href="mailto:{{ $record->email }}" class="text-slate-700 hover:text-indigo-600 truncate">{{ $record->email }}</a>
          </div>
          @endif
          @if($record->phone)
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">📞</span>
            <a href="tel:{{ $record->phone }}" class="text-slate-700 hover:text-indigo-600">{{ $record->phone }}</a>
          </div>
          @endif
          @if($record->store)
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">🏬</span>
            <span class="text-slate-600">{{ $record->store->store_name }}</span>
          </div>
          @endif
        </div>

        @if($record->summary)
        <div class="mt-4 pt-4 border-t border-slate-100">
          <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Reason</h4>
          <p class="text-sm text-slate-700 leading-relaxed">{{ $record->summary }}</p>
        </div>
        @endif

        @if($record->negative_feedback_causes)
        <div class="mt-4 pt-4 border-t border-slate-100">
          <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Negative Feedback Cause(s)</h4>
          <div class="flex flex-wrap gap-1.5">
            @foreach($record->negative_feedback_causes as $cause)
            <span class="badge text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $cause }}</span>
            @endforeach
          </div>
          <p class="text-xs mt-2 {{ $record->negative_feedback_resolved ? 'text-emerald-600' : 'text-amber-600' }}">
            {{ $record->negative_feedback_resolved ? '✓ Resolved' . ($record->negative_feedback_resolved_at ? ' on '.$record->negative_feedback_resolved_at->format('d M Y') : '') : '⏳ Unresolved' }}
          </p>
        </div>
        @endif

        @if($record->informations)
        <div class="mt-4 pt-4 border-t border-slate-100">
          <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Note</h4>
          <p class="text-sm text-slate-700 leading-relaxed">{{ $record->informations }}</p>
        </div>
        @endif
      </div>

      {{-- Handled-By History --}}
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-semibold text-slate-700">Handled-by History</h4>
          <span class="badge badge-indigo text-xs">{{ $record->current_handler?->name ?? 'Unassigned' }}</span>
        </div>
        <div class="space-y-3 mb-4">
          @forelse($record->handlerHistory as $entry)
          <div class="flex items-center gap-2 text-sm">
            <img src="{{ $entry->user?->avatar_url }}" class="w-5 h-5 rounded-full">
            <span class="font-medium text-slate-700">{{ $entry->user?->name }}</span>
            <span class="text-xs text-slate-400">
              {{ $entry->started_at?->format('d M Y, g:ia') }}
              {{ $entry->ended_at ? ' until '.$entry->ended_at->format('d M Y, g:ia') : '' }}
            </span>
            @unless($entry->ended_at)
            <span class="badge badge-emerald text-xs">current</span>
            @endunless
          </div>
          @empty
          <p class="text-slate-400 text-sm">No handler history yet.</p>
          @endforelse
        </div>
        <form @submit.prevent="switchHandler($event)" action="{{ route('crm.ebay.customers.switch-handler', $record) }}" class="flex gap-2">
          <select name="user_id" class="form-input py-2 text-sm flex-1">
            @foreach($crmUsers as $user)
            <option value="{{ $user->id }}">{{ $user->name }}</option>
            @endforeach
          </select>
          <button type="submit" class="btn btn-secondary text-sm" :disabled="handlerLoading">Switch Handler</button>
        </form>
      </div>

      {{-- Status History --}}
      <div class="card">
        <h4 class="font-semibold text-slate-700 mb-4">Status History</h4>
        <div class="space-y-3">
          @forelse($record->statusHistory as $entry)
          @php $historyColor = \App\Models\EbayCustomerRecord::tabColor($entry->status); @endphp
          <div class="flex items-center gap-2 text-sm">
            <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $historyColor }}22; color:{{ $historyColor }}">{{ $tabs[$entry->status] ?? $entry->status }}</span>
            <span class="text-xs text-slate-400">{{ $entry->changed_at?->format('d M Y, g:ia') }}</span>
            <span class="text-xs text-slate-400">— {{ $entry->changedBy?->name }}</span>
          </div>
          @empty
          <p class="text-slate-400 text-sm">No status changes recorded yet.</p>
          @endforelse
        </div>
      </div>
    </div>

    {{-- ── Right: Purchase History + Follow-Up Notes ───────────────────────── --}}
    <div class="xl:col-span-2 space-y-5">

      {{-- Purchase History --}}
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-semibold text-slate-700">Purchase History</h4>
          <button @click="showAddOrder = true" class="btn btn-primary text-sm">+ Add New Order</button>
        </div>

        <div class="space-y-4">
          @forelse($record->orders as $order)
          <div class="border border-slate-100 rounded-xl p-4">
            <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
              <span class="font-mono text-sm font-semibold text-slate-800">{{ $order->order_id }}</span>
              <div class="flex items-center gap-2 text-xs text-slate-400">
                @if($order->store)<span class="text-indigo-600">{{ $order->store->store_name }}</span>@endif
                <span>{{ $order->ordered_at?->format('d M Y') }}</span>
              </div>
            </div>
            <div class="divide-y divide-slate-50">
              @forelse($order->items as $item)
              <div class="flex items-center justify-between py-1.5 text-sm">
                <span class="text-slate-700">{{ $item->product_name }}</span>
                <span class="text-slate-500">{{ $item->price !== null ? '$'.number_format($item->price, 2) : '—' }}</span>
              </div>
              @empty
              <p class="text-slate-400 text-sm py-1.5">No products logged for this order.</p>
              @endforelse
            </div>
          </div>
          @empty
          <p class="text-slate-400 text-sm">No orders logged yet.</p>
          @endforelse
        </div>
      </div>

      {{-- Follow-Up Notes --}}
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-semibold text-slate-700">Follow-Up Notes</h4>
          <span class="badge badge-indigo text-xs">{{ $record->followUps->count() }} entries</span>
        </div>

        <div class="space-y-4" id="followup-timeline">
          @forelse($record->followUps as $i => $fu)
          <div class="flex gap-3">
            <div class="flex flex-col items-center">
              <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 text-sm shrink-0">💬</div>
              @if(!$loop->last) <div class="w-0.5 flex-1 bg-slate-100 mt-1"></div> @endif
            </div>
            <div class="flex-1 pb-4">
              <div class="bg-slate-50 rounded-xl px-3 py-2 text-sm text-slate-700">{{ $fu->notes }}</div>
              <div class="flex items-center gap-2 mt-1">
                <img src="{{ $fu->user?->avatar_url }}" class="w-4 h-4 rounded-full">
                <span class="text-xs text-slate-400">{{ $fu->user?->name }}</span>
                <span class="text-xs text-slate-400 ml-auto">{{ $fu->contacted_at?->format('d M Y, g:ia') }}</span>
                @if($fu->user_id === auth()->id())
                <form method="POST" action="{{ route('crm.ebay.customers.follow-up.destroy', [$record, $fu]) }}"
                      data-confirm-title="Delete this follow-up?"
                      data-confirm="This will permanently remove this follow-up note."
                      data-confirm-text="Delete"
                      data-confirm-tone="danger">
                  @csrf @method('DELETE')
                  <button type="submit" class="text-xs text-slate-300 hover:text-red-600" title="Delete">🗑</button>
                </form>
                @endif
              </div>
            </div>
          </div>
          @empty
          <p class="text-slate-400 text-sm">No follow-up notes yet.</p>
          @endforelse
        </div>

        <div x-show="!showFollowUp" class="mt-2">
          <button @click="showFollowUp = true"
                  class="w-full border-2 border-dashed border-slate-200 rounded-xl py-3 text-sm text-slate-400 hover:border-indigo-400 hover:text-indigo-600 transition-colors">
            + Log follow-up note
          </button>
        </div>

        <div x-show="showFollowUp" x-cloak class="mt-3 space-y-3">
          <textarea x-model="fuNotes" rows="3" class="form-input" placeholder="What did you discuss? What was the outcome?"></textarea>
          <div class="flex gap-3">
            <button @click="showFollowUp = false" class="btn btn-secondary flex-1">Cancel</button>
            <button @click="submitFollowUp()" :disabled="fuLoading" class="btn btn-primary flex-1">
              <span x-show="!fuLoading">Save Note</span>
              <span x-show="fuLoading" x-cloak>Saving…</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ── Add New Order Modal ─────────────────────────────────────────────── --}}
  <div x-show="showAddOrder" x-cloak class="modal-overlay" @keydown.escape.window="showAddOrder = false">
    <div class="modal-box max-w-lg" @click.stop>
      <div class="modal-header">
        <h3 class="font-display font-bold text-slate-800">Add New Order</h3>
        <button @click="showAddOrder = false" class="btn btn-secondary btn-icon ml-auto">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <form @submit.prevent="submitOrder($event)" action="{{ route('crm.ebay.customers.orders.store', $record) }}" class="p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Order ID <span class="text-red-500">*</span></label>
            <input type="text" name="order_id" class="form-input font-mono">
          </div>
          <div>
            <label class="form-label">Order Date</label>
            <input type="date" name="order_date" class="form-input">
          </div>
        </div>
        <div>
          <label class="form-label">Purchased From (Store)</label>
          @include('crm.partials.store-searchable-select', [
            'name'     => 'order_store_id',
            'selected' => null,
            'stores'   => $stores,
          ])
        </div>
        <div>
          <label class="form-label">Product(s) <span class="text-red-500">*</span> <span class="text-slate-400 normal-case font-normal">(manual entry — pricing varies per sale)</span></label>
          <div class="space-y-2">
            <template x-for="(product, i) in products" :key="i">
              <div class="flex gap-2 items-start">
                <input type="text" x-model="product.name" list="ebay-catalog-products" placeholder="Search or type a product" class="form-input flex-1" required>
                <input type="number" step="0.01" min="0" x-model="product.price" placeholder="Price" class="form-input w-28" required>
                <button type="button" @click="removeProduct(i)" x-show="products.length > 1"
                        class="btn btn-secondary btn-icon text-red-400 hover:text-red-600 shrink-0" style="width:38px;height:38px;">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
              </div>
            </template>
          </div>
          <button type="button" @click="addProduct()" class="btn btn-secondary text-xs mt-2">+ Add Another Product</button>
        </div>
        <div class="flex gap-3 pt-2">
          <button type="button" @click="showAddOrder = false" class="btn btn-secondary flex-1">Cancel</button>
          <button type="submit" :disabled="orderLoading" class="btn btn-primary flex-1">
            <span x-show="!orderLoading">Save Order</span>
            <span x-show="orderLoading" x-cloak>Saving…</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <datalist id="ebay-catalog-products">
    @foreach($catalogProducts as $p)
    <option value="{{ $p->name }}">{{ $p->sku }}</option>
    @endforeach
  </datalist>
</div>
@endsection
