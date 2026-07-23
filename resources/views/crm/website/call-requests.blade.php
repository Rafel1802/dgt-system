@extends('layouts.app')
@section('title', 'Website CRM — Call Requests')
@section('page_title', 'Call Requests')

@section('content')
<div class="animate-fade-in">

  <div class="mb-5">
    <a href="{{ route('crm.website.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Leads</a>
  </div>

  @if(session('success'))
  <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm font-medium">
    {{ session('success') }}
  </div>
  @endif

  {{-- ── Toolbar ─────────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex gap-2 flex-wrap">
      @foreach(['pending' => 'Pending', 'fulfilled' => 'Fulfilled', 'all' => 'All'] as $val => $lbl)
      <a href="{{ route('crm.website.call-requests.index', array_merge(request()->query(), ['status' => $val])) }}"
         class="btn text-xs py-1.5 px-3 {{ $tab === $val ? 'btn-primary' : 'btn-secondary' }}">
        {{ $lbl }}
      </a>
      @endforeach
    </div>
    <form method="GET" action="{{ route('crm.website.call-requests.index') }}" class="flex gap-2">
      <input type="hidden" name="status" value="{{ $tab }}">
      <input type="search" name="search" value="{{ request('search') }}" placeholder="Name or phone…" class="form-input text-sm py-2 w-56">
      <button type="submit" class="btn btn-secondary text-sm">Search</button>
    </form>
  </div>

  {{-- ── Call Requests Table ──────────────────────────────────────────────── --}}
  <div class="card p-0 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left">Requester</th>
            <th class="px-4 py-3 text-left">Phone</th>
            <th class="px-4 py-3 text-left">Note</th>
            <th class="px-4 py-3 text-left">Source</th>
            <th class="px-4 py-3 text-left">Requested</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($callRequests as $request)
          <tr class="hover:bg-slate-50/70 transition-colors">
            <td class="px-5 py-3 font-semibold text-slate-800">{{ $request->name }}</td>
            <td class="px-4 py-3 text-slate-500">{{ $request->phone ?: '—' }}</td>
            <td class="px-4 py-3 text-slate-600 max-w-xs truncate" title="{{ $request->note }}">{{ $request->note }}</td>
            <td class="px-4 py-3 text-xs">
              @if($request->source_type === \App\Models\TechSupportCase::class && $request->source)
                <a href="{{ route('crm.tech-support.show', $request->source) }}" class="text-indigo-600 hover:underline">
                  Tech Support #{{ $request->source_id }}
                </a>
              @else
                <span class="text-slate-300">—</span>
              @endif
            </td>
            <td class="px-4 py-3 text-xs text-slate-400">
              {{ $request->created_at->diffForHumans() }}
              @if($request->requestedBy)<p class="text-slate-500">by {{ $request->requestedBy->name }}</p>@endif
            </td>
            <td class="px-4 py-3">
              @if($request->fulfilled)
                <span class="badge text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Fulfilled</span>
                @if($request->fulfilled_at)
                  <p class="text-xs text-slate-400 mt-1">{{ $request->fulfilled_at->format('d M Y, g:ia') }}@if($request->fulfilledBy) by {{ $request->fulfilledBy->name }}@endif</p>
                @endif
                @if($request->fulfillment_note)
                  <p class="text-xs text-slate-600 mt-1 max-w-xs truncate" title="{{ $request->fulfillment_note }}">{{ $request->fulfillment_note }}</p>
                @endif
              @else
                <span class="badge text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Pending</span>
              @endif
            </td>
            <td class="px-4 py-3 text-right">
              @unless($request->fulfilled)
              <button type="button" onclick="document.getElementById('fulfillModal{{ $request->id }}').classList.remove('hidden')" class="btn btn-primary text-xs py-1 px-2.5">Mark Called</button>

              {{-- Mark Called Modal --}}
              <div id="fulfillModal{{ $request->id }}" class="fixed inset-0 z-50 hidden bg-slate-900/50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col text-left">
                  <form method="POST" action="{{ route('crm.website.call-requests.fulfill', $request) }}" class="flex flex-col min-h-0"
                        onsubmit="const btn=this.querySelector('[data-save-btn]'); if(btn){ btn.disabled=true; btn.textContent='Saving…'; }">
                    @csrf
                    <input type="hidden" name="return_status" value="{{ $tab }}">
                    <input type="hidden" name="return_search" value="{{ request('search') }}">
                    <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center shrink-0">
                      <h3 class="font-display font-bold text-lg text-slate-800">Mark Called — {{ $request->name }}</h3>
                      <button type="button" onclick="document.getElementById('fulfillModal{{ $request->id }}').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                      </button>
                    </div>
                    <div class="p-6 space-y-4 overflow-y-auto min-h-0">
                      <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Reason for call</p>
                        <p class="text-sm text-slate-600 bg-slate-50 rounded-lg px-3 py-2">{{ $request->note }}</p>
                      </div>
                      <div>
                        <label class="form-label">Outcome Note <span class="text-red-500">*</span></label>
                        <textarea name="note" rows="3" class="form-input" required placeholder="What happened on the call?"></textarea>
                        <p class="text-xs text-slate-400 mt-1">Required — this is shown back to Tech Support on the case.</p>
                      </div>
                    </div>
                    <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 bg-slate-50 rounded-b-xl shrink-0">
                      <button type="button" onclick="document.getElementById('fulfillModal{{ $request->id }}').classList.add('hidden')" class="btn btn-secondary text-sm">Cancel</button>
                      <button type="submit" data-save-btn class="btn btn-primary text-sm">Save</button>
                    </div>
                  </form>
                </div>
              </div>
              @endunless
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center py-14">
              <div class="text-4xl mb-3">📲</div>
              <p class="text-slate-500 font-medium">No call requests found</p>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($callRequests->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">{{ $callRequests->links() }}</div>
    @endif
  </div>

</div>
@endsection
