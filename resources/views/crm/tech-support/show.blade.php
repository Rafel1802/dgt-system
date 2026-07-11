@extends('layouts.app')
@section('title', 'Technical Support Case')
@section('page_title', 'Tech Support — Case Detail')

@section('content')
<div class="animate-fade-in" x-data="{
  statusLoading: false,
  assignLoading: false,
  followUpLoading: false,
  requestCallLoading: false,
  showFollowUp: false,
  showRequestCall: false,
  requestCallNote: '',

  async changeStatus(newStatus) {
    this.statusLoading = true;
    try {
      await window.api('{{ route('crm.tech-support.status', $case) }}', { method: 'PATCH', body: JSON.stringify({ status: newStatus }) });
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Status updated!', type: 'success' } }));
      setTimeout(() => location.reload(), 700);
    } catch (err) {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
    } finally { this.statusLoading = false; }
  },

  async assignTechnician(event) {
    this.assignLoading = true;
    try {
      const userId = new FormData(event.target).get('user_id');
      await window.api(event.target.action, { method: 'POST', body: JSON.stringify({ user_id: userId }) });
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Case assigned!', type: 'success' } }));
      setTimeout(() => location.reload(), 700);
    } catch (err) {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
    } finally { this.assignLoading = false; }
  },

  async submitFollowUp(event) {
    this.followUpLoading = true;
    try {
      const fd = new FormData(event.target);
      const res = await fetch(event.target.action, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': window.csrf(), 'Accept': 'application/json' },
        body: fd,
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.message || 'Failed.');
      }
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Follow-up added!', type: 'success' } }));
      setTimeout(() => location.reload(), 700);
    } catch (err) {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
    } finally { this.followUpLoading = false; }
  },

  async requestCall() {
    if (!this.requestCallNote.trim()) {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'A note is required so CRM Website knows why to call.', type: 'error' } }));
      return;
    }
    this.requestCallLoading = true;
    try {
      await window.api('{{ route('crm.tech-support.request-call', $case) }}', { method: 'POST', body: JSON.stringify({ note: this.requestCallNote }) });
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Call requested!', type: 'success' } }));
      setTimeout(() => location.reload(), 700);
    } catch (err) {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
    } finally { this.requestCallLoading = false; }
  },
}">

  <div class="mb-5 flex items-center justify-between">
    <a href="{{ route('crm.tech-support.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Tech Support</a>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- ── Left: Customer/Order info + Notes ────────────────────────────────── --}}
    <div class="xl:col-span-1 space-y-4">

      <div class="card">
        @php $color = \App\Models\TechSupportCase::statusColor($case->status); @endphp
        <div class="h-2 -mx-5 -mt-5 mb-4 rounded-t-2xl" style="background:{{ $color }}"></div>

        <div class="text-center pb-2">
          <div class="w-16 h-16 rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-3" style="background:{{ $color }}">
            {{ strtoupper(substr($case->customer?->name ?? '?', 0, 1)) }}
          </div>
          <h2 class="font-display font-bold text-slate-800 text-lg">{{ $case->customer?->name ?? 'Unknown Customer' }}</h2>
          <span class="badge text-xs font-semibold px-2 py-0.5 rounded-full inline-block mt-1" style="background:{{ $color }}22; color:{{ $color }}">
            {{ $statuses[$case->status] ?? $case->status }}
          </span>
          @if($case->occurrence_label)
          <p class="text-xs text-amber-600 font-semibold mt-2">🔁 {{ \App\Models\TechSupportCase::ordinal($case->occurrence_count) }} technical issue reported for this customer</p>
          @endif
        </div>

        <div class="mt-4 space-y-2.5 border-t border-slate-100 pt-4">
          @if($case->customer?->email)
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">📧</span>
            <a href="mailto:{{ $case->customer->email }}" class="text-slate-700 hover:text-indigo-600 truncate">{{ $case->customer->email }}</a>
          </div>
          @endif
          @if($case->customer?->phone)
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">📞</span>
            <a href="tel:{{ $case->customer->phone }}" class="text-slate-700 hover:text-indigo-600">{{ $case->customer->phone }}</a>
          </div>
          @endif
          @if($case->order_id)
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">🧾</span>
            <span class="font-mono text-slate-700">{{ $case->order_id }}</span>
          </div>
          @endif
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">{{ $case->source_type === \App\Models\Lead::class ? '🌐' : '🛒' }}</span>
            <span class="text-slate-600">{{ $case->source_type === \App\Models\Lead::class ? 'CRM Website' : 'eBay' }}</span>
          </div>
          <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 w-5">📅</span>
            <span class="text-slate-600">{{ $case->created_at->format('d M Y, g:ia') }}</span>
          </div>
        </div>

        {{-- Status buttons --}}
        <div class="mt-4 pt-4 border-t border-slate-100">
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Technical Status</p>
          <div class="grid grid-cols-2 gap-2">
            @foreach($statuses as $key => $label)
            <button @click="changeStatus('{{ $key }}')" :disabled="statusLoading"
                    class="py-2 px-2 rounded-xl text-xs font-semibold text-center transition-all border-2 {{ $case->status === $key ? 'text-white border-transparent' : 'bg-white border-slate-200 text-slate-500 hover:border-slate-300' }}"
                    style="{{ $case->status === $key ? 'background:'.\App\Models\TechSupportCase::statusColor($key).'; border-color:'.\App\Models\TechSupportCase::statusColor($key) : '' }}">
              {{ $label }}
            </button>
            @endforeach
          </div>
        </div>

        {{-- Assign technician --}}
        <div class="mt-4 pt-4 border-t border-slate-100">
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Assigned Technician</p>
          <form @submit.prevent="assignTechnician($event)" action="{{ route('crm.tech-support.assign', $case) }}" class="flex gap-2">
            <select name="user_id" class="form-input py-2 text-sm flex-1">
              <option value="">Unassigned</option>
              @foreach($technicians as $tech)
              <option value="{{ $tech->id }}" {{ $case->assigned_to === $tech->id ? 'selected' : '' }}>{{ $tech->name }}</option>
              @endforeach
            </select>
            <button type="submit" class="btn btn-secondary text-sm" :disabled="assignLoading">Assign</button>
          </form>
        </div>

        {{-- Request Call --}}
        <div class="mt-4 pt-4 border-t border-slate-100">
          <button @click="showRequestCall = true" class="btn btn-primary text-sm w-full">📞 Request Call</button>
        </div>
      </div>

      {{-- Previous Sales/eBay Notes --}}
      <div class="card">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">
          Previous {{ $case->source_type === \App\Models\Lead::class ? 'Sales' : 'eBay' }} Notes
        </h4>
        @php $source = $case->source; @endphp
        @if($source instanceof \App\Models\Lead)
          @if($source->inquiry_details)<p class="text-sm text-slate-700 mb-2">{{ $source->inquiry_details }}</p>@endif
          @if($source->follow_up_notes)<p class="text-sm text-slate-600">{{ $source->follow_up_notes }}</p>@endif
          @if(!$source->inquiry_details && !$source->follow_up_notes)<p class="text-slate-400 text-sm">No sales notes recorded.</p>@endif
        @elseif($source instanceof \App\Models\EbayCustomerRecord)
          @if($source->informations)<p class="text-sm text-slate-700 mb-2">{{ $source->informations }}</p>@endif
          @if($source->summary)<p class="text-sm text-slate-600">{{ $source->summary }}</p>@endif
          @if(!$source->informations && !$source->summary)<p class="text-slate-400 text-sm">No eBay notes recorded.</p>@endif
        @else
          <p class="text-slate-400 text-sm">Source record no longer available.</p>
        @endif

        @if($source && $source->followUps->count())
        <div class="mt-3 pt-3 border-t border-slate-100 space-y-2">
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Existing Follow-Up History</p>
          @foreach($source->followUps->take(5) as $fu)
          <div class="text-xs text-slate-600 bg-slate-50 rounded-lg px-2.5 py-1.5">
            {{ $fu->notes }}
            <span class="text-slate-400 block mt-0.5">{{ $fu->contacted_at?->format('d M Y, g:ia') }}</span>
          </div>
          @endforeach
        </div>
        @endif
      </div>

      {{-- Call Requests — only the still-pending ones; once called, the outcome is logged on the Follow-Up Log instead (see logCallCompletedOnCase()) so it isn't shown twice. --}}
      @php $pendingCallRequests = $case->callRequests->where('fulfilled', false); @endphp
      @if($pendingCallRequests->count())
      <div class="card">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Call Requests</h4>
        @foreach($pendingCallRequests as $cr)
        <div class="border border-amber-200 bg-amber-50 rounded-xl p-3 mb-2">
          <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1">Awaiting CRM Website Callback</p>
          <p class="text-sm text-slate-700">{{ $cr->note }}</p>
          <p class="text-xs text-slate-400 mt-1">Requested {{ $cr->created_at->format('d M Y, g:ia') }}</p>
        </div>
        @endforeach
      </div>
      @endif
    </div>

    {{-- ── Right: Follow-Up Logs ─────────────────────────────────────────────── --}}
    <div class="xl:col-span-2 space-y-5">

      {{-- Follow-Up Logs --}}
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-semibold text-slate-700">Follow-Up Logs</h4>
          <span class="badge badge-indigo text-xs">{{ $case->logs->count() }} entries</span>
        </div>

        <div class="space-y-3 mb-4">
          @forelse($case->logs as $log)
          <div class="border border-slate-100 rounded-xl p-3">
            <div class="flex items-center gap-2 mb-1 flex-wrap">
              <span class="badge text-xs px-2 py-0.5 rounded-full {{ match($log->type) { 'call_completed' => 'bg-emerald-50 text-emerald-700', 'reopened' => 'bg-amber-50 text-amber-700', default => 'bg-slate-100 text-slate-600' } }}">
                {{ match($log->type) { 'call_completed' => '📞 Call Completed', 'reopened' => '🔁 New Issue Reported', default => '📝 Follow-Up' } }}
              </span>
              <span class="text-xs text-slate-400 ml-auto">{{ $log->created_at->format('d M Y, g:ia') }}</span>
            </div>
            <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ $log->note }}</p>
            @if($log->attachments->count())
            <div class="flex flex-wrap gap-2 mt-2">
              @foreach($log->attachments as $att)
              <a href="{{ $att->url }}" target="_blank" class="text-xs text-indigo-600 hover:underline bg-indigo-50 rounded-full px-2 py-1">
                📎 {{ $att->original_name }} ({{ $att->formatted_size }})
              </a>
              @endforeach
            </div>
            @endif
            <div class="flex items-center gap-1 mt-2">
              <img src="{{ $log->user?->avatar_url }}" class="w-4 h-4 rounded-full">
              <span class="text-xs text-slate-400">{{ $log->user?->name }}</span>
              @if($log->user_id === auth()->id())
              <form method="POST" action="{{ route('crm.tech-support.follow-up.destroy', [$case, $log]) }}" class="ml-auto"
                    data-confirm-title="Delete this log entry?"
                    data-confirm="This will permanently remove this entry from the Follow-Up Logs."
                    data-confirm-text="Delete"
                    data-confirm-tone="danger">
                @csrf @method('DELETE')
                <button type="submit" class="text-xs text-slate-300 hover:text-red-600" title="Delete">🗑</button>
              </form>
              @endif
            </div>
          </div>
          @empty
          <p class="text-slate-400 text-sm">No follow-up logs yet.</p>
          @endforelse
        </div>

        <div x-show="!showFollowUp">
          <button @click="showFollowUp = true"
                  class="w-full border-2 border-dashed border-slate-200 rounded-xl py-3 text-sm text-slate-400 hover:border-indigo-400 hover:text-indigo-600 transition-colors">
            + Add follow-up log
          </button>
        </div>

        <form x-show="showFollowUp" x-cloak @submit.prevent="submitFollowUp($event)"
              action="{{ route('crm.tech-support.follow-up', $case) }}" enctype="multipart/form-data" class="space-y-3">
          <div>
            <label class="form-label text-xs">Note <span class="text-red-500">*</span></label>
            <textarea name="note" rows="3" class="form-input text-sm" required></textarea>
          </div>
          <div>
            <label class="form-label text-xs">Attachment (optional)</label>
            <input type="file" name="attachment" class="form-input text-sm" accept=".pdf,.jpg,.jpeg,.png,.gif">
          </div>
          <div class="flex gap-2">
            <button type="button" @click="showFollowUp = false" class="btn btn-secondary text-sm flex-1">Cancel</button>
            <button type="submit" :disabled="followUpLoading" class="btn btn-primary text-sm flex-1">
              <span x-show="!followUpLoading">Save Log</span>
              <span x-show="followUpLoading" x-cloak>Saving…</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- Request Call Modal --}}
  <div x-show="showRequestCall" x-cloak class="modal-overlay" @keydown.escape.window="showRequestCall = false">
    <div class="modal-box max-w-lg" @click.stop>
      <div class="modal-header">
        <h3 class="font-display font-bold text-slate-800">Request Call</h3>
        <button @click="showRequestCall = false" class="btn btn-secondary btn-icon ml-auto">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="form-label">Note <span class="text-red-500">*</span></label>
          <textarea x-model="requestCallNote" rows="3" class="form-input" placeholder="Why does CRM Website need to call this customer?"></textarea>
          <p class="text-xs text-slate-400 mt-1">Required — this is what the CRM Website team will see when they make the call.</p>
        </div>
        <div class="flex gap-3 pt-2">
          <button @click="showRequestCall = false" class="btn btn-secondary flex-1">Cancel</button>
          <button @click="requestCall()" :disabled="requestCallLoading" class="btn btn-primary flex-1">
            <span x-show="!requestCallLoading">Send Request</span>
            <span x-show="requestCallLoading" x-cloak>Sending…</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
