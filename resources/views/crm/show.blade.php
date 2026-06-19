@extends('layouts.app')
@section('title', $customer->name . ' — Customer Profile')
@section('page_title', $customer->name)

@section('content')
<div x-data="customerProfile({{ $customer->id }})" class="animate-fade-in">

  {{-- Back --}}
  <a href="{{ route('crm.customers.index') }}" class="text-sm text-slate-400 hover:text-indigo-600 flex items-center gap-1 mb-5">
    ← Back to Customers
  </a>

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- ── Left: Profile Card ──────────────────────────────────────────────── --}}
    <div class="xl:col-span-1 space-y-4">

      {{-- Identity --}}
      <div class="card text-center">
        <img src="{{ $customer->avatar_url }}" alt="{{ $customer->name }}" class="avatar mx-auto mb-3" style="width:72px;height:72px;">
        <h2 class="font-display font-bold text-slate-800 text-lg">{{ $customer->name }}</h2>
        @if($customer->job_title || $customer->company)
          <p class="text-slate-400 text-sm">{{ collect([$customer->job_title, $customer->company])->filter()->join(' @ ') }}</p>
        @endif

        <div class="flex justify-center gap-2 mt-3">
          <span class="badge {{ $customer->status?->badgeClass() }}">{{ $customer->status?->label() }}</span>
          @if($customer->has_purchased)
            <span class="badge badge-emerald">✓ Purchased</span>
          @endif
        </div>

        {{-- Quick contact links --}}
        <div class="flex justify-center gap-2 mt-4">
          @if($customer->email)
            <a href="mailto:{{ $customer->email }}" class="btn btn-secondary btn-icon" title="{{ $customer->email }}">
              <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
            </a>
          @endif
          @if($customer->phone)
            <a href="tel:{{ $customer->phone }}" class="btn btn-secondary btn-icon" title="{{ $customer->phone }}">
              <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
            </a>
          @endif
          @can('update', $customer)
            <a href="{{ route('crm.customers.edit', $customer) }}" class="btn btn-secondary btn-icon" title="Edit">
              <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
            </a>
          @endcan
        </div>
      </div>

      {{-- Details --}}
      <div class="card space-y-3">
        <h4 class="font-semibold text-slate-700 text-sm border-b border-slate-100 pb-2">Details</h4>
        @if($customer->email)
        <div class="flex gap-2 text-sm"><span class="text-slate-400 w-20 flex-shrink-0">Email</span><span class="text-slate-700 break-all">{{ $customer->email }}</span></div>
        @endif
        @if($customer->phone)
        <div class="flex gap-2 text-sm"><span class="text-slate-400 w-20 flex-shrink-0">Phone</span><span class="text-slate-700">{{ $customer->phone }}</span></div>
        @endif
        @if($customer->website)
        <div class="flex gap-2 text-sm"><span class="text-slate-400 w-20 flex-shrink-0">Website</span><a href="{{ $customer->website }}" class="text-indigo-600 hover:underline truncate" target="_blank">{{ $customer->website }}</a></div>
        @endif
        @if($customer->city || $customer->country)
        <div class="flex gap-2 text-sm"><span class="text-slate-400 w-20 flex-shrink-0">Location</span><span class="text-slate-700">{{ collect([$customer->city, $customer->state, $customer->country])->filter()->join(', ') }}</span></div>
        @endif

        @php $src = \App\Enums\CustomerSource::tryFrom($customer->source ?? ''); @endphp
        @if($src)
        <div class="flex gap-2 text-sm"><span class="text-slate-400 w-20 flex-shrink-0">Source</span><span class="text-slate-700">{{ $src->icon() }} {{ $src->label() }}</span></div>
        @endif
        @if($customer->assignee)
        <div class="flex gap-2 text-sm items-center"><span class="text-slate-400 w-20 flex-shrink-0">Assigned</span>
          <img src="{{ $customer->assignee->avatar_url }}" class="avatar" style="width:20px;height:20px;">
          <span class="text-slate-700">{{ $customer->assignee->name }}</span>
        </div>
        @endif
        <div class="flex gap-2 text-sm"><span class="text-slate-400 w-20 flex-shrink-0">Added</span><span class="text-slate-700">{{ $customer->created_at->format('d M Y') }}</span></div>
      </div>

      {{-- Purchase Stats --}}
      <div class="card space-y-3">
        <h4 class="font-semibold text-slate-700 text-sm border-b border-slate-100 pb-2">Purchase History</h4>
        <div class="grid grid-cols-2 gap-3 text-center">
          <div class="bg-emerald-50 rounded-xl p-3">
            <div class="text-lg font-bold text-emerald-700">${{ number_format($customer->lifetime_value, 2) }}</div>
            <div class="text-xs text-emerald-500">Lifetime Value</div>
          </div>
          <div class="bg-indigo-50 rounded-xl p-3">
            <div class="text-lg font-bold text-indigo-700">{{ $customer->total_orders }}</div>
            <div class="text-xs text-indigo-500">Total Orders</div>
          </div>
        </div>
        @if($customer->last_purchase_date)
          <p class="text-xs text-slate-400">Last purchase: <strong>{{ $customer->last_purchase_date->format('d M Y') }}</strong></p>
        @endif

        {{-- Record Purchase --}}
        @can('update', $customer)
        <div x-data="{open: false}">
          <button @click="open = !open" class="btn btn-secondary text-xs py-1.5 w-full">+ Record Purchase</button>
          <div x-show="open" x-cloak class="mt-3 space-y-2">
            <input type="number" x-model="purchaseValue" step="0.01" class="form-input text-sm" placeholder="Amount (AUD)">
            <button @click="recordPurchase()" class="btn btn-primary text-xs py-1.5 w-full">Save Purchase</button>
          </div>
        </div>
        @endcan
      </div>

      {{-- Product Interests --}}
      @if($customer->product_interests)
      <div class="card">
        <h4 class="font-semibold text-slate-700 text-sm border-b border-slate-100 pb-2 mb-3">Product Interests</h4>
        <div class="flex flex-wrap gap-2">
          @foreach($customer->product_interests as $interest)
            <span class="badge badge-indigo">{{ $interest }}</span>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Tags --}}
      @if($customer->tags)
      <div class="card">
        <h4 class="font-semibold text-slate-700 text-sm border-b border-slate-100 pb-2 mb-3">Tags</h4>
        <div class="flex flex-wrap gap-2">
          @foreach($customer->tags as $tag)
            <span class="badge badge-slate">{{ $tag }}</span>
          @endforeach
        </div>
      </div>
      @endif
    </div>

    {{-- ── Right: Activity / Interactions ─────────────────────────────────── --}}
    <div class="xl:col-span-2 space-y-5">

      {{-- Notes --}}
      @if($customer->notes)
      <div class="card">
        <h4 class="font-semibold text-slate-700 text-sm mb-2">📋 Background Notes</h4>
        <p class="text-sm text-slate-600 leading-relaxed whitespace-pre-wrap">{{ $customer->notes }}</p>
      </div>
      @endif

      {{-- Log Interaction Form --}}
      @can('addInteraction', $customer)
      <div class="card">
        <h4 class="font-semibold text-slate-700 text-sm mb-3">Log Interaction</h4>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-3">
          <div>
            <label class="form-label text-xs">Type</label>
            <select x-model="newInteraction.type" class="form-input text-sm py-1.5">
              <option value="call">📞 Call</option>
              <option value="email">📧 Email</option>
              <option value="meeting">🤝 Meeting</option>
              <option value="note">📝 Note</option>
              <option value="whatsapp">💬 WhatsApp</option>
              <option value="demo">🖥️ Demo</option>
            </select>
          </div>
          <div>
            <label class="form-label text-xs">Outcome</label>
            <select x-model="newInteraction.outcome" class="form-input text-sm py-1.5">
              <option value="positive">✅ Positive</option>
              <option value="neutral">➖ Neutral</option>
              <option value="negative">❌ Negative</option>
            </select>
          </div>
          <div>
            <label class="form-label text-xs">Duration (mins)</label>
            <input type="number" x-model="newInteraction.duration_minutes" class="form-input text-sm py-1.5" placeholder="Optional">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label text-xs">Subject</label>
          <input type="text" x-model="newInteraction.subject" class="form-input text-sm py-1.5" placeholder="Brief subject…">
        </div>
        <div class="mb-3">
          <label class="form-label text-xs">Notes <span class="text-red-500">*</span></label>
          <textarea x-model="newInteraction.content" rows="3" class="form-input text-sm" placeholder="What was discussed?"></textarea>
        </div>
        <button @click="submitInteraction()" :disabled="interactionLoading" class="btn btn-primary text-sm">
          <span x-show="!interactionLoading">Save Interaction</span>
          <span x-show="interactionLoading" x-cloak>Saving…</span>
        </button>
      </div>
      @endcan

      {{-- Interaction Timeline --}}
      <div class="card">
        <h4 class="font-semibold text-slate-700 text-sm mb-4">Interaction History</h4>
        <div class="space-y-3" id="interactions-list">
          @forelse($customer->interactions as $interaction)
          <div class="flex gap-3">
            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center bg-slate-100 text-base">
              {{ $interaction->type_icon }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-slate-700 text-sm">{{ $interaction->user?->name }}</span>
                @if($interaction->subject)
                  <span class="text-slate-400 text-xs">· {{ $interaction->subject }}</span>
                @endif
                @if($interaction->outcome)
                  <span class="text-xs {{ $interaction->outcome_color }}">{{ ucfirst($interaction->outcome) }}</span>
                @endif
                <span class="text-xs text-slate-300 ml-auto">{{ $interaction->interacted_at->diffForHumans() }}</span>
              </div>
              <p class="text-sm text-slate-600 mt-0.5 leading-relaxed">{{ $interaction->content }}</p>
              @if($interaction->duration_minutes)
                <p class="text-xs text-slate-400 mt-0.5">Duration: {{ $interaction->duration_minutes }} min</p>
              @endif
            </div>
          </div>
          @empty
          <p class="text-slate-400 text-sm text-center py-4">No interactions logged yet.</p>
          @endforelse
        </div>
      </div>

      {{-- Active Deals --}}
      @if($customer->deals->isNotEmpty())
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-semibold text-slate-700 text-sm">Deals</h4>
          <a href="{{ route('crm.pipeline.index') }}" class="text-xs text-indigo-600 hover:underline">View Pipeline →</a>
        </div>
        <div class="space-y-2">
          @foreach($customer->deals->take(5) as $deal)
          <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
            <span class="text-xs font-semibold px-2 py-1 rounded-full flex-shrink-0"
                  style="background:{{ $deal->stage?->color() }}22; color:{{ $deal->stage?->color() }}">
              {{ $deal->stage?->label() }}
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-slate-700 truncate">{{ $deal->title }}</p>
              <p class="text-xs text-slate-400">{{ $deal->probability }}% · {{ $deal->expected_close_date?->format('d M Y') ?? 'No close date' }}</p>
            </div>
            <div class="text-sm font-bold text-slate-700">${{ number_format($deal->value, 0) }}</div>
          </div>
          @endforeach
        </div>
      </div>
      @endif

    </div>
  </div>

  @include('kanban.partials.toast')
</div>
@endsection

@push('scripts')
<script>
function customerProfile(customerId) {
  return {
    customerId,
    purchaseValue: '',
    interactionLoading: false,
    newInteraction: {
      type: 'call', outcome: 'positive', subject: '', content: '', duration_minutes: '',
    },

    async recordPurchase() {
      if (! this.purchaseValue) return;
      const res = await fetch(`/crm/customers/${this.customerId}/purchase`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': window.csrf(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ value: this.purchaseValue }),
      });
      const data = await res.json();
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: data.message, type: res.ok ? 'success' : 'error' } }));
      if (res.ok) setTimeout(() => location.reload(), 1200);
    },

    async submitInteraction() {
      if (! this.newInteraction.content.trim()) return;
      this.interactionLoading = true;
      try {
        const res = await fetch(`/crm/customers/${this.customerId}/interactions`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': window.csrf(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify(this.newInteraction),
        });
        const data = await res.json();
        if (! res.ok) throw data;
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Interaction saved!', type: 'success' } }));
        this.newInteraction = { type: 'call', outcome: 'positive', subject: '', content: '', duration_minutes: '' };
        setTimeout(() => location.reload(), 800);
      } catch(e) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: e.message || 'Failed.', type: 'error' } }));
      } finally {
        this.interactionLoading = false;
      }
    },
  };
}
</script>
@endpush
