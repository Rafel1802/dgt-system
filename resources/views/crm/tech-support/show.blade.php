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
  showCompleteCall: null,
  completeCallLoading: false,

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
    this.requestCallLoading = true;
    try {
      await window.api('{{ route('crm.tech-support.request-call', $case) }}', { method: 'POST' });
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Call requested!', type: 'success' } }));
      setTimeout(() => location.reload(), 700);
    } catch (err) {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
    } finally { this.requestCallLoading = false; }
  },

  async completeCall(event, callRequestId) {
    this.completeCallLoading = true;
    try {
      const fd = new FormData(event.target);
      const url = '{{ route('crm.tech-support.complete-call', [$case, '__CALL_REQUEST_ID__']) }}'.replace('__CALL_REQUEST_ID__', callRequestId);
      await window.api(url, {
        method: 'POST',
        body: JSON.stringify({ summary: fd.get('summary'), status: fd.get('status') || null }),
      });
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: 'Call completed!', type: 'success' } }));
      setTimeout(() => location.reload(), 700);
    } catch (err) {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg: err.message || 'Failed.', type: 'error' } }));
    } finally { this.completeCallLoading = false; }
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
          <button @click="requestCall()" :disabled="requestCallLoading" class="btn btn-primary text-sm w-full">📞 Request Call</button>
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

      {{-- Pending Call Requests --}}
      @if($case->callRequests->where('fulfilled', false)->count())
      <div class="card">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Pending Call Requests</h4>
        @foreach($case->callRequests->where('fulfilled', false) as $cr)
        <div class="border border-amber-200 bg-amber-50 rounded-xl p-3 mb-2">
          <p class="text-sm text-slate-700">{{ $cr->note }}</p>
          <p class="text-xs text-slate-400 mt-1">Requested {{ $cr->created_at->format('d M Y, g:ia') }}</p>
          <button @click="showCompleteCall = {{ $cr->id }}" class="btn btn-primary text-xs mt-2 w-full">Complete Call</button>

          <div x-show="showCompleteCall === {{ $cr->id }}" x-cloak class="mt-3 pt-3 border-t border-amber-200 space-y-2">
            <form @submit.prevent="completeCall($event, {{ $cr->id }})" class="space-y-2">
              <div>
                <label class="form-label text-xs">Call Summary <span class="text-red-500">*</span></label>
                <textarea name="summary" rows="3" class="form-input text-sm" required></textarea>
              </div>
              <div>
                <label class="form-label text-xs">Update Status (optional)</label>
                <select name="status" class="form-input text-sm">
                  <option value="">No change</option>
                  @foreach($statuses as $key => $label)
                  <option value="{{ $key }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="flex gap-2">
                <button type="button" @click="showCompleteCall = null" class="btn btn-secondary text-xs flex-1">Cancel</button>
                <button type="submit" :disabled="completeCallLoading" class="btn btn-primary text-xs flex-1">Save</button>
              </div>
            </form>
          </div>
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
              <span class="badge text-xs px-2 py-0.5 rounded-full {{ $log->type === 'call_completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                {{ $log->type === 'call_completed' ? '📞 Call Completed' : '📝 Follow-Up' }}
              </span>
              <span class="text-xs text-slate-400 ml-auto">{{ $log->created_at->format('d M Y, g:ia') }}</span>
            </div>
            <p class="text-sm text-slate-700">{{ $log->note }}</p>
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
</div>
@endsection
