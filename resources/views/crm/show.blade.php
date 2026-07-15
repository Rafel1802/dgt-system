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

        <div class="flex justify-center gap-2 mt-3 flex-wrap">
          <span class="badge {{ $customer->status?->badgeClass() }}">{{ $customer->status?->label() }}</span>
          @if($customer->has_purchased)
            <span class="badge badge-emerald">✓ Purchased</span>
          @endif
          @if($customer->shipment_delay)
            <span class="badge text-xs px-2 py-0.5 rounded-full"
                  style="background:{{ \App\Models\EbayCustomerRecord::LOGISTIC_ISSUES_COLOR }}22; color:{{ \App\Models\EbayCustomerRecord::LOGISTIC_ISSUES_COLOR }}">
              ⚠ Logistic Issues
            </span>
          @elseif($customer->shipment_delivered)
            <span class="badge text-xs px-2 py-0.5 rounded-full"
                  style="background:{{ \App\Models\EbayCustomerRecord::DELIVERED_COLOR }}22; color:{{ \App\Models\EbayCustomerRecord::DELIVERED_COLOR }}">
              ✅ Delivered
            </span>
          @endif
          @if($customer->latestTechSupportCase)
            @php $techColor = \App\Models\TechSupportCase::statusColor($customer->latestTechSupportCase->status); @endphp
            <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $techColor }}22; color:{{ $techColor }}">
              🛠 {{ \App\Models\TechSupportCase::statuses()[$customer->latestTechSupportCase->status] ?? $customer->latestTechSupportCase->status }}
            </span>
            @if($customer->latestTechSupportCase->occurrence_label)
              <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700" title="Repeat technical issue">
                🔁 {{ $customer->latestTechSupportCase->occurrence_label }}
              </span>
            @endif
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
            <input type="number" x-model="purchaseValue" step="0.01" class="form-input text-sm" placeholder="Amount (USD)">
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

      {{-- Attachments Section --}}
      <div class="card">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
          <h4 class="font-semibold text-slate-700 text-sm">📎 Customer Attachments</h4>
          <span class="text-xs text-slate-400">PDFs and Images only (Max 50MB)</span>
        </div>

        @if($customer->attachments->isNotEmpty())
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            @foreach($customer->attachments as $attach)
              <div class="flex items-center justify-between p-3 bg-slate-50 border border-slate-100 rounded-xl hover:border-slate-200 transition-colors">
                <div class="flex items-center gap-3 min-w-0">
                  <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-lg flex-shrink-0 overflow-hidden">
                    @if($attach->isImage())
                      <img src="{{ $attach->view_url }}" class="w-full h-full object-cover" alt="">
                    @else
                      📄
                    @endif
                  </div>
                  <div class="min-w-0">
                    <a href="#" 
                       @click.prevent="openFileViewer('{{ $attach->view_url }}', {{ $attach->isImage() ? 'true' : 'false' }}, '{{ addslashes($attach->original_name) }}')"
                       class="text-sm font-medium text-slate-700 hover:text-indigo-600 truncate block cursor-pointer" 
                       title="View {{ $attach->original_name }}">
                      {{ $attach->original_name }}
                    </a>
                    <div class="text-xs text-slate-400 mt-0.5">
                      {{ $attach->formatted_size }} · {{ $attach->created_at->diffForHumans() }}
                    </div>
                  </div>
                </div>
                <div class="flex items-center gap-1 ml-2 flex-shrink-0">
                  <a href="#" @click.prevent="confirmDownload('{{ $attach->url }}', '{{ addslashes($attach->original_name) }}')" class="text-slate-400 hover:text-indigo-600 p-1" title="Download File">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                  </a>
                  <form action="{{ route('attachments.destroy', $attach->id) }}" method="POST" class="inline"
                        data-confirm="Are you sure you want to delete this attachment permanently? This cannot be undone."
                        data-confirm-title="Delete Attachment"
                        data-confirm-text="Delete"
                        data-confirm-tone="danger">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-slate-400 hover:text-rose-500 p-1" title="Delete Attachment">
                      <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                    </button>
                  </form>
                </div>
              </div>
            @endforeach
          </div>
        @else
          <p class="text-slate-400 text-sm text-center py-4 italic mb-4">No attachments uploaded yet.</p>
        @endif

        {{-- Upload Form --}}
        <form action="{{ route('crm.customers.attachments.upload', $customer->id) }}" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3 items-end pt-3 border-t border-slate-100">
          @csrf
          <div class="flex-1 w-full">
            <label class="form-label text-xs">Upload File (PDF/Image)</label>
            <input type="file" name="attachment" required class="form-input text-sm py-1.5" accept=".pdf,image/*">
            @error('attachment')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div class="w-full sm:w-auto">
            <button type="submit" class="btn btn-secondary text-sm w-full py-2">
              📤 Upload File
            </button>
          </div>
        </form>
      </div>

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

    </div>
  </div>

  @include('kanban.partials.toast')

  {{-- Attachment Inline Viewer Modal --}}
  <div x-show="showFileViewer" x-cloak class="fixed inset-0 z-[150] flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)" @click="showFileViewer = false" @keydown.escape.window="showFileViewer = false">
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col" @click.stop>
          {{-- Header --}}
          <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
              <h3 class="font-bold text-slate-800 dark:text-slate-100 truncate pr-4 text-base" x-text="viewerTitle"></h3>
              <div class="flex items-center gap-3">
                  <button @click="showFileViewer = false" class="text-slate-400 hover:text-slate-600 p-1">
                      <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                  </button>
              </div>
          </div>
          {{-- Content --}}
          <div class="p-6 bg-slate-50 dark:bg-slate-900/30 flex-1 overflow-y-auto flex items-center justify-center min-h-[50vh]">
              <template x-if="viewerType === 'image'">
                  <img :src="viewerUrl" class="max-w-full max-h-[70vh] object-contain rounded-lg shadow-md">
              </template>
              <template x-if="viewerType === 'pdf'">
                  <iframe :src="viewerUrl" class="w-full h-[70vh] rounded-lg border border-slate-200 dark:border-slate-700 bg-white" type="application/pdf"></iframe>
              </template>
          </div>
      </div>
  </div>
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
    showFileViewer: false,
    viewerUrl: '',
    viewerType: '',
    viewerTitle: '',
    openFileViewer(url, isImage, title) {
      this.viewerUrl = url;
      this.viewerType = isImage ? 'image' : 'pdf';
      this.viewerTitle = title;
      this.showFileViewer = true;
    },
    async confirmDownload(url, filename) {
      const ok = await window.confirmModal({
        title: 'Download Attachment',
        message: `Are you sure you want to download <strong>${filename}</strong>?`,
        confirmText: 'Download',
        tone: 'warning'
      });
      if (ok) {
        window.location.href = url;
      }
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
