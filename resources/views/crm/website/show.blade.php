@extends('layouts.app')
@section('title', $lead->client_name . ' — Lead Profile')
@section('page_title', 'Lead Profile')

@section('content')
<div x-data="leadProfile({{ $lead->id }}, {{ Js::from($catalogProducts->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'price' => $p->price])) }})" class="animate-fade-in">

  <div class="mb-5 flex items-center justify-between flex-wrap gap-3">
    <a href="{{ route('crm.website.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Website CRM</a>
    <div class="flex gap-2">
      @if(auth()->user()->canDeleteCrmRecords('website'))
      <a href="{{ route('crm.website.edit', $lead) }}" class="btn btn-secondary text-sm">Edit Lead</a>
      @endif
      @if(!$lead->status?->isTerminal())
      <button @click="showFollowUp = true" class="btn btn-primary text-sm" id="btn-log-followup">
        📝 Log Follow-Up
      </button>
      @endif
    </div>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- ── Left: Client Card + Pipeline ────────────────────────────────────── --}}
    <div class="xl:col-span-1 space-y-4">

      {{-- Client Summary Card --}}
      <div class="card">
        {{-- Temperature banner --}}
        <div class="h-2 -mx-5 -mt-5 mb-4 rounded-t-2xl" style="background:{{ $lead->temperature?->color() ?? '#94a3b8' }}"></div>

        <div class="text-center pb-2">
          <div class="w-16 h-16 rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-3"
               style="background: linear-gradient(135deg, {{ $lead->status?->color() }}, {{ $lead->temperature?->color() ?? '#94a3b8' }})">
            {{ strtoupper(substr($lead->client_name, 0, 1)) }}
          </div>
          <h2 class="font-display font-bold text-slate-800 text-lg">{{ $lead->client_name }}</h2>
          <div class="flex items-center justify-center gap-2 mt-1 flex-wrap">
            <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full"
                  style="background:{{ $lead->display_status_color }}22; color:{{ $lead->display_status_color }}">
              {{ $lead->display_status_label }}
            </span>
            @if($lead->techSupportCase?->occurrence_label)
              <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700" title="Repeat technical issue">
                🔁 {{ $lead->techSupportCase->occurrence_label }}
              </span>
            @endif
            @if($lead->temperature)
            <span class="text-sm" title="{{ $lead->temperature->label() }}">{{ $lead->temperature->icon() }} {{ $lead->temperature->label() }}</span>
            @endif
          </div>
        </div>

        <div class="mt-4 space-y-2.5 border-t border-slate-100 pt-4">
          @if($lead->client_phone)
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">📞</span>
            <a href="tel:{{ $lead->client_phone }}" class="text-slate-700 hover:text-indigo-600">{{ $lead->client_phone }}</a>
          </div>
          @endif
          @if($lead->client_whatsapp)
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">💬</span>
            <span class="text-slate-700">{{ $lead->client_whatsapp }}</span>
          </div>
          @endif
          @if($lead->client_email)
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">📧</span>
            <a href="mailto:{{ $lead->client_email }}" class="text-slate-700 hover:text-indigo-600 truncate">{{ $lead->client_email }}</a>
          </div>
          @endif
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">{{ $lead->source?->icon() }}</span>
            <span class="text-slate-600">{{ $lead->source?->label() }}</span>
          </div>
          @if($lead->product)
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">🏗️</span>
            <span class="text-slate-700">{{ $lead->product->name }}</span>
          </div>
          @elseif($lead->product_interested)
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">🏗️</span>
            <span class="text-slate-600">{{ $lead->product_interested }}</span>
          </div>
          @endif
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">📅</span>
            <span class="text-slate-600">{{ $lead->received_at?->format('d M Y, g:ia') }}</span>
          </div>
        </div>

        @if($lead->handler)
        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center gap-2">
          <img src="{{ $lead->handler->avatar_url }}" class="w-7 h-7 rounded-full">
          <div>
            <p class="text-xs text-slate-400">Handled by</p>
            <p class="text-sm font-semibold text-slate-700">{{ $lead->handler->name }}</p>
          </div>
        </div>
        @endif
      </div>

      {{-- Follow-Up Due --}}
      @if($lead->follow_up_date && !$lead->status?->isTerminal())
      <div class="card {{ $lead->is_overdue ? 'border border-red-300 bg-red-50' : 'border border-amber-200 bg-amber-50' }}">
        <div class="flex items-start gap-3">
          <span class="text-xl">{{ $lead->is_overdue ? '⚠️' : '📅' }}</span>
          <div>
            <p class="text-sm font-semibold {{ $lead->is_overdue ? 'text-red-700' : 'text-amber-700' }}">
              {{ $lead->is_overdue ? 'Follow-up OVERDUE' : 'Follow-up Due' }}
            </p>
            <p class="text-sm {{ $lead->is_overdue ? 'text-red-600' : 'text-amber-600' }}">{{ $lead->follow_up_date->format('D, d M Y') }}</p>
            @if($lead->next_action)
            <p class="text-xs text-slate-500 mt-1">→ {{ $lead->next_action }}</p>
            @endif
          </div>
        </div>
        <button @click="showFollowUp = true" class="btn btn-primary text-xs w-full mt-3">Log Follow-Up Now</button>
      </div>
      @endif

      {{-- Inquiry Notes --}}
      @if($lead->inquiry_details)
      <div class="card">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Inquiry Details</h4>
        <p class="text-sm text-slate-700 leading-relaxed">{{ $lead->inquiry_details }}</p>
      </div>
      @endif

    </div>

    {{-- ── Right: Pipeline + Activity Timeline ─────────────────────────────── --}}
    <div class="xl:col-span-2 space-y-5">

      {{-- Pipeline Status Bar --}}
      <div class="card">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-4">Pipeline Progress</h4>
        <div class="flex gap-1 overflow-x-auto pb-2">
          @foreach($statuses as $s)
          @php $isActive = $lead->status?->value === $s->value; @endphp
          <button
            @click="updateStatus('{{ $s->value }}')"
            :disabled="statusLoading"
            class="flex-1 min-w-[80px] py-2 px-2 rounded-xl text-xs font-semibold text-center transition-all cursor-pointer border-2 {{ $isActive ? 'text-white border-transparent shadow-md scale-105' : 'bg-white border-slate-200 text-slate-500 hover:border-slate-300' }}"
            style="{{ $isActive ? 'background:'.$s->color().'; border-color:'.$s->color() : '' }}"
            title="{{ $s->label() }}">
            {{ $s->label() }}
          </button>
          @endforeach
        </div>
        <p class="text-xs text-slate-400 mt-2">Click a stage to move this lead.</p>
      </div>

      {{-- Order History --}}
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-semibold text-slate-700">Order History</h4>
          @if(auth()->user()->canDeleteCrmRecords('website'))
          <button @click="openNewOrderModal()" class="btn btn-primary text-sm" id="btn-add-order">+ Add New Order</button>
          @endif
        </div>

        <div class="space-y-4">
          @forelse($lead->orders as $order)
          <div class="border border-slate-100 rounded-xl p-4">
            <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
              <span class="font-mono text-xs text-slate-400">Order #{{ $order->id }}</span>
              <span class="text-xs text-slate-400">{{ $order->order_date?->format('d M Y') }}</span>
            </div>
            <div class="divide-y divide-slate-50">
              @foreach($order->items as $item)
              <div class="flex items-center justify-between py-1.5 text-sm">
                <span class="text-slate-700">{{ $item->product_name }} <span class="text-xs text-slate-400">× {{ $item->quantity }}</span></span>
                <span class="text-slate-500">{{ $item->price !== null ? '$'.number_format($item->price, 2) : '—' }}</span>
              </div>
              @endforeach
            </div>
          </div>
          @empty
          <p class="text-slate-400 text-sm">No orders logged yet.</p>
          @endforelse
        </div>
      </div>

      {{-- Follow-Up History / Activity Timeline --}}
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-semibold text-slate-700">Activity Timeline</h4>
          <span class="badge badge-indigo text-xs">{{ $lead->followUps->count() }} entries</span>
        </div>

        {{-- Follow-up entries --}}
        <div class="space-y-4" id="followup-timeline">
          {{-- Initial inquiry entry --}}
          <div class="flex gap-3">
            <div class="flex flex-col items-center">
              <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 text-sm shrink-0">📋</div>
              @if($lead->followUps->count()) <div class="w-0.5 flex-1 bg-slate-100 mt-1"></div> @endif
            </div>
            <div class="flex-1 pb-4">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-sm font-semibold text-slate-800">Inquiry Received</span>
                <span class="badge badge-indigo text-xs">{{ $lead->source?->label() }}</span>
              </div>
              @if($lead->inquiry_details)
              <p class="text-sm text-slate-600 bg-slate-50 rounded-xl px-3 py-2">{{ $lead->inquiry_details }}</p>
              @endif
              <p class="text-xs text-slate-400 mt-1">{{ $lead->received_at?->format('d M Y, g:ia') }}</p>
            </div>
          </div>

          {{-- Follow-up history --}}
          @foreach($lead->followUps->sortBy('contacted_at') as $i => $fu)
          <div class="flex gap-3" id="followup-{{ $fu->id }}">
            <div class="flex flex-col items-center">
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm shrink-0"
                   style="background:{{ $fu->temperature?->color() ?? '#6366f1' }}">
                {{ $fu->temperature?->icon() ?? '💬' }}
              </div>
              @if(!$loop->last) <div class="w-0.5 flex-1 bg-slate-100 mt-1"></div> @endif
            </div>
            <div class="flex-1 pb-4">
              <div class="flex items-center gap-2 mb-1 flex-wrap">
                <span class="text-sm font-semibold text-slate-800">Follow-Up</span>
                @if($fu->status_changed_to)
                  <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                        style="background:{{ $fu->status_changed_to?->color() }}22; color:{{ $fu->status_changed_to?->color() }}">
                    → {{ $fu->status_changed_to?->label() }}
                  </span>
                @endif
                <span class="text-xs text-slate-400 ml-auto">{{ $fu->contacted_at?->format('d M Y, g:ia') }}</span>
              </div>
              <div class="bg-slate-50 rounded-xl px-3 py-2 text-sm text-slate-700">{{ $fu->notes }}</div>
              @if($fu->next_action)
              <p class="text-xs text-indigo-600 mt-1">→ Next: {{ $fu->next_action }}</p>
              @endif
              @if($fu->follow_up_date)
              <p class="text-xs text-amber-600 mt-0.5">📅 Follow-up: {{ $fu->follow_up_date->format('d M Y') }}</p>
              @endif
              <div class="flex items-center gap-1 mt-1">
                <img src="{{ $fu->user?->avatar_url }}" class="w-4 h-4 rounded-full">
                <span class="text-xs text-slate-400">{{ $fu->user?->name }}</span>
                @if($fu->user_id === auth()->id())
                <form method="POST" action="{{ route('crm.website.follow-up.destroy', [$lead, $fu]) }}" class="ml-auto"
                      data-confirm-title="Delete this follow-up?"
                      data-confirm="This will permanently remove this follow-up entry."
                      data-confirm-text="Delete"
                      data-confirm-tone="danger">
                  @csrf @method('DELETE')
                  <button type="submit" class="text-xs text-slate-300 hover:text-red-600" title="Delete">🗑</button>
                </form>
                @endif
              </div>
            </div>
          </div>
          @endforeach
        </div>

        {{-- Quick follow-up inline form at bottom --}}
        @if(!$lead->status?->isTerminal())
        <div x-show="!showFollowUp" class="mt-2">
          <button @click="showFollowUp = true"
                  class="w-full border-2 border-dashed border-slate-200 rounded-xl py-3 text-sm text-slate-400 hover:border-indigo-400 hover:text-indigo-600 transition-colors">
            + Log follow-up or note
          </button>
        </div>
        @endif
      </div>
    </div>
  </div>

  {{-- ── Follow-Up Modal ─────────────────────────────────────────────────── --}}
  <div x-show="showFollowUp" x-cloak class="modal-overlay" @keydown.escape.window="showFollowUp = false">
    <div class="modal-box max-w-lg" @click.stop>
      <div class="modal-header">
        <h3 class="font-display font-bold text-slate-800">Log Follow-Up</h3>
        <button @click="showFollowUp = false" class="btn btn-secondary btn-icon ml-auto">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="form-label">Contact Notes <span class="text-red-500">*</span></label>
          <textarea x-model="fuForm.notes" rows="3" class="form-input"
                    placeholder="What did you discuss? What was the outcome?"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="form-label text-xs">Update Status</label>
            <select x-model="fuForm.status" class="form-input text-sm">
              <option value="">No change</option>
              @foreach($statuses as $s)
              <option value="{{ $s->value }}">{{ $s->label() }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label text-xs">Temperature</label>
            <select x-model="fuForm.temperature" class="form-input text-sm">
              <option value="">No change</option>
              @foreach($temps as $t)
              <option value="{{ $t->value }}">{{ $t->icon() }} {{ $t->label() }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label text-xs">Next Follow-Up Date</label>
            <input type="date" x-model="fuForm.follow_up_date" class="form-input text-sm">
          </div>
          <div>
            <label class="form-label text-xs">Next Action</label>
            <input type="text" x-model="fuForm.next_action" class="form-input text-sm" placeholder="What to do next?">
          </div>
        </div>
        <div class="flex gap-3 pt-2">
          <button @click="showFollowUp = false" class="btn btn-secondary flex-1">Cancel</button>
          <button @click="submitFollowUp()" :disabled="fuLoading" class="btn btn-primary flex-1">
            <span x-show="!fuLoading">Save Follow-Up</span>
            <span x-show="fuLoading" x-cloak>Saving…</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- ── Order Modal — shared by "+ Add New Order" and "Mark Successful" ─── --}}
  <div x-show="showOrderModal" x-cloak class="modal-overlay" @keydown.escape.window="showOrderModal = false">
    <div class="modal-box max-w-lg" @click.stop>
      <div class="modal-header">
        <h3 class="font-display font-bold text-slate-800" x-text="orderModalPurpose === 'successful' ? 'Products Sold' : 'Log New Order'"></h3>
        <button @click="showOrderModal = false" class="btn btn-secondary btn-icon ml-auto">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <p class="text-xs text-slate-400" x-show="orderModalPurpose === 'successful'">At least one product is required to mark this lead as Successful.</p>
        <p class="text-xs text-slate-400" x-show="orderModalPurpose !== 'successful'">Search the catalog or type a product name manually — each order is kept in history, nothing gets overwritten.</p>
        <div>
          <label class="form-label">Purchase Date <span class="text-red-500">*</span></label>
          <input type="date" x-model="orderDate" class="form-input" required>
        </div>
        <div class="space-y-2">
          <template x-for="(line, i) in lines" :key="i">
            <div class="flex gap-2 items-start">
              <input type="text" list="catalog-products-{{ $lead->id }}" x-model="line.product_name" @input="matchCatalogProduct(line)"
                     placeholder="Search or type a product" class="form-input flex-1">
              <input type="number" step="0.01" min="0" x-model="line.price" placeholder="Price" class="form-input w-28">
              <input type="number" min="1" x-model.number="line.quantity" placeholder="Qty" class="form-input w-20">
              <button type="button" @click="removeLine(i)" x-show="lines.length > 1"
                      class="btn btn-secondary btn-icon text-red-400 hover:text-red-600 shrink-0" style="width:38px;height:38px;">
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </template>
        </div>
        <datalist id="catalog-products-{{ $lead->id }}">
          @foreach($catalogProducts as $p)
          <option value="{{ $p->name }}">{{ $p->sku ? '('.$p->sku.')' : '' }} — ${{ number_format($p->price, 2) }}</option>
          @endforeach
        </datalist>
        <button type="button" @click="addLine()" class="btn btn-secondary text-xs">+ Add Another Product</button>
        <div class="flex gap-3 pt-2">
          <button @click="showOrderModal = false" class="btn btn-secondary flex-1">Cancel</button>
          <button @click="confirmOrder()" :disabled="statusLoading || orderLoading" class="btn btn-primary flex-1">
            <span x-show="!statusLoading && !orderLoading" x-text="orderModalPurpose === 'successful' ? 'Save & Mark Successful' : 'Save Order'"></span>
            <span x-show="statusLoading || orderLoading" x-cloak>Saving…</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- ── Mark Technical Support Modal (note required) ─────────────────────── --}}
  <div x-show="showTechNoteModal" x-cloak class="modal-overlay" @keydown.escape.window="showTechNoteModal = false">
    <div class="modal-box max-w-lg" @click.stop>
      <div class="modal-header">
        <h3 class="font-display font-bold text-slate-800">Mark as Technical Support</h3>
        <button @click="showTechNoteModal = false" class="btn btn-secondary btn-icon ml-auto">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <p class="text-xs text-slate-400">A note explaining the technical issue is required to mark this lead as Technical Support.</p>
        <div>
          <label class="form-label">Note <span class="text-red-500">*</span></label>
          <textarea x-model="techNote" rows="4" class="form-input" placeholder="What's the technical issue?"></textarea>
        </div>
        <div class="flex gap-3 pt-2">
          <button @click="showTechNoteModal = false" class="btn btn-secondary flex-1">Cancel</button>
          <button @click="confirmTechSupport()" :disabled="statusLoading" class="btn btn-primary flex-1">
            <span x-show="!statusLoading">Save & Mark Technical Support</span>
            <span x-show="statusLoading" x-cloak>Saving…</span>
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
function leadProfile(leadId, catalog) {
  return {
    showFollowUp: {{ session('open_followup') ? 'true' : 'false' }},
    showOrderModal: false,
    orderModalPurpose: 'new', // 'new' (standalone order log) or 'successful' (tied to a status change)
    orderDate: '',
    showTechNoteModal: false,
    techNote: '',
    pendingStatus: null,
    fuLoading: false,
    statusLoading: false,
    orderLoading: false,
    fuForm: { notes: '', status: '', temperature: '', follow_up_date: '', next_action: '' },
    lines: [{ product_id: null, product_name: '', price: '', quantity: 1 }],
    catalog: catalog,
    addLine() { this.lines.push({ product_id: null, product_name: '', price: '', quantity: 1 }); },
    removeLine(i) { if (this.lines.length > 1) this.lines.splice(i, 1); },
    matchCatalogProduct(line) {
      const typed = (line.product_name || '').trim().toLowerCase();
      const match = this.catalog.find(p => p.name.toLowerCase() === typed);
      if (match) {
        line.product_id = match.id;
        if (!line.price) line.price = match.price;
      } else {
        line.product_id = null;
      }
    },

    async submitFollowUp() {
      if (!this.fuForm.notes.trim()) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Notes are required.', type: 'error' } }));
        return;
      }
      this.fuLoading = true;
      try {
        const data = await api(`/crm/website/${leadId}/follow-up`, {
          method: 'POST',
          body: JSON.stringify(this.fuForm),
        });
        this.showFollowUp = false;
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Follow-up saved!', type: 'success' } }));
        setTimeout(() => location.reload(), 900);
      } catch(err) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
      } finally { this.fuLoading = false; }
    },

    updateStatus(newStatus) {
      if (newStatus === 'successful') {
        this.pendingStatus = newStatus;
        this.orderModalPurpose = 'successful';
        this.lines = [{ product_id: null, product_name: '', price: '', quantity: 1 }];
        this.orderDate = '';
        this.showOrderModal = true;
        return;
      }
      if (newStatus === 'technical_support') {
        this.pendingStatus = newStatus;
        this.techNote = '';
        this.showTechNoteModal = true;
        return;
      }
      this._patchStatus(newStatus, null, null, null);
    },

    openNewOrderModal() {
      this.orderModalPurpose = 'new';
      this.lines = [{ product_id: null, product_name: '', price: '', quantity: 1 }];
      this.orderDate = '';
      this.showOrderModal = true;
    },

    async confirmOrder() {
      if (!this.lines.length || this.lines.some(l => !l.product_id && !String(l.product_name || '').trim())) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Enter a product for each line.', type: 'error' } }));
        return;
      }
      if (!this.orderDate) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Purchase date is required.', type: 'error' } }));
        return;
      }
      this.showOrderModal = false;
      if (this.orderModalPurpose === 'successful') {
        await this._patchStatus(this.pendingStatus, this.lines, null, this.orderDate);
      } else {
        await this._storeOrder(this.lines, this.orderDate);
      }
    },

    async confirmTechSupport() {
      if (!this.techNote.trim()) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'A note explaining the technical issue is required.', type: 'error' } }));
        return;
      }
      this.showTechNoteModal = false;
      await this._patchStatus(this.pendingStatus, null, this.techNote, null);
    },

    async _patchStatus(newStatus, lines, note, orderDate) {
      this.statusLoading = true;
      try {
        const body = { status: newStatus };
        if (lines) {
          body.products = lines.map(l => ({ product_id: l.product_id, product_name: l.product_name, price: l.price, quantity: l.quantity }));
          body.order_date = orderDate;
        }
        if (note) body.note = note;
        await api(`/crm/website/${leadId}/status`, {
          method: 'PATCH',
          body: JSON.stringify(body),
        });
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Status updated!', type: 'success' } }));
        setTimeout(() => location.reload(), 700);
      } catch(err) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
      } finally { this.statusLoading = false; }
    },

    async _storeOrder(lines, orderDate) {
      this.orderLoading = true;
      try {
        const body = {
          products: lines.map(l => ({ product_id: l.product_id, product_name: l.product_name, price: l.price, quantity: l.quantity })),
          order_date: orderDate,
        };
        await api(`/crm/website/${leadId}/orders`, {
          method: 'POST',
          body: JSON.stringify(body),
        });
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Order logged!', type: 'success' } }));
        setTimeout(() => location.reload(), 700);
      } catch(err) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
      } finally { this.orderLoading = false; }
    },
  };
}
</script>
@endpush
