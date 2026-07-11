@extends('layouts.app')
@section('title', 'Website CRM — Call Reports')
@section('page_title', 'Call Reports')

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
  @if(session('share_url'))
  <div class="mb-4 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-700 px-4 py-3 text-sm font-medium flex items-center justify-between gap-3 flex-wrap">
    <span>🔗 Share link ready — anyone with this link can view these call reports (no login required):</span>
    <div class="flex items-center gap-2">
      <input id="share-url-input" type="text" readonly value="{{ session('share_url') }}" class="form-input text-xs py-1.5 w-72" onclick="this.select()">
      <button type="button" class="btn btn-secondary text-xs py-1.5 px-3" onclick="navigator.clipboard.writeText(document.getElementById('share-url-input').value)">Copy</button>
    </div>
  </div>
  @endif
  @if($errors->any())
  <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 text-sm font-medium">
    <ul class="space-y-1">
      @foreach($errors->all() as $error)<li>• {{ $error }}</li>@endforeach
    </ul>
  </div>
  @endif

  <div class="mb-5">
    <span class="text-sm text-slate-500">Showing <strong class="text-slate-800">{{ $filteredTotal }}</strong> call report(s) matching the current filters.</span>
  </div>

  {{-- ── Toolbar ─────────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <form method="GET" action="{{ route('crm.website.call-reports.index') }}" class="flex flex-wrap gap-2 items-end">
      <div>
        <label class="form-label text-xs">Search</label>
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Name, phone, email…" class="form-input text-sm py-2 w-56">
      </div>
      <div>
        <label class="form-label text-xs">From</label>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input text-sm py-2">
      </div>
      <div>
        <label class="form-label text-xs">To</label>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input text-sm py-2">
      </div>
      <div>
        <label class="form-label text-xs">Answered By</label>
        <select name="answered_by" class="form-input text-sm py-2">
          <option value="">All Staff</option>
          @foreach($crmUsers as $user)
            <option value="{{ $user->id }}" {{ (string) request('answered_by') === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
          @endforeach
        </select>
      </div>
      <button type="submit" class="btn btn-secondary text-sm">Filter</button>
      @php
        $today = now()->toDateString();
        $presets = [
          'Today' => [$today, $today],
          'This Week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
          'This Month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        ];
      @endphp
      @foreach($presets as $label => [$from, $to])
      <a href="{{ route('crm.website.call-reports.index', ['search' => request('search'), 'answered_by' => request('answered_by'), 'date_from' => $from, 'date_to' => $to]) }}"
         class="btn btn-secondary text-xs py-1.5 px-3">{{ $label }}</a>
      @endforeach
      @if(request('date_from') || request('date_to'))
      <a href="{{ route('crm.website.call-reports.index', ['search' => request('search'), 'answered_by' => request('answered_by')]) }}" class="btn btn-secondary text-xs py-1.5 px-3">All Time</a>
      @endif
    </form>
    <div class="flex gap-2">
      <form method="POST" action="{{ route('crm.website.call-reports.share') }}">
        @csrf
        <input type="hidden" name="search" value="{{ request('search') }}">
        <input type="hidden" name="date_from" value="{{ request('date_from') }}">
        <input type="hidden" name="date_to" value="{{ request('date_to') }}">
        <input type="hidden" name="answered_by" value="{{ request('answered_by') }}">
        <button type="submit" class="btn btn-secondary text-sm">🔗 Share Link</button>
      </form>
      <button type="button" onclick="document.getElementById('exportCallReportsModal').classList.remove('hidden')" class="btn btn-secondary text-sm">
        📊 Export
      </button>
      <button type="button" onclick="document.getElementById('logCallModal').classList.remove('hidden')" class="btn btn-primary text-sm" id="btn-log-call">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Log Call
      </button>
    </div>
  </div>

  {{-- ── Call Reports Table ────────────────────────────────────────────────── --}}
  <div class="card p-0 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left">Name</th>
            <th class="px-4 py-3 text-left">Phone</th>
            <th class="px-4 py-3 text-left">Email</th>
            <th class="px-4 py-3 text-left">Type</th>
            <th class="px-4 py-3 text-left">Details / Note</th>
            <th class="px-4 py-3 text-left">Answered By</th>
            <th class="px-4 py-3 text-left">Date & Time</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($callReports as $report)
          <tr class="hover:bg-slate-50/70 transition-colors">
            <td class="px-5 py-3 font-semibold text-slate-800">{{ $report->name ?: '—' }}</td>
            <td class="px-4 py-3 text-slate-500">{{ $report->phone ?: '—' }}</td>
            <td class="px-4 py-3 text-slate-500">{{ $report->email ?: '—' }}</td>
            <td class="px-4 py-3"><span class="badge text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $report->inquiry_type }}</span></td>
            <td class="px-4 py-3 text-xs text-slate-500 max-w-xs truncate" title="{{ $report->details }}">{{ $report->details ? \Illuminate\Support\Str::limit($report->details, 40) : '—' }}</td>
            <td class="px-4 py-3 text-slate-500">{{ $report->answeredBy?->name }}</td>
            <td class="px-4 py-3 text-xs text-slate-400">{{ $report->occurred_at?->format('d M Y, g:ia') }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center py-14">
              <div class="text-4xl mb-3">📞</div>
              <p class="text-slate-500 font-medium">No call reports found</p>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($callReports->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">{{ $callReports->links() }}</div>
    @endif
  </div>

</div>

{{-- Log Call Modal --}}
<div id="logCallModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col text-left">
    <form method="POST" action="{{ route('crm.website.call-reports.store') }}" class="flex flex-col min-h-0">
      @csrf
      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center shrink-0">
        <h3 class="font-display font-bold text-lg text-slate-800">Log Call Report</h3>
        <button type="button" onclick="document.getElementById('logCallModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 space-y-4 overflow-y-auto min-h-0">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-input">
          </div>
          <div>
            <label class="form-label">Phone <span class="text-red-500">*</span></label>
            <input type="text" name="phone" required class="form-input">
          </div>
          <div>
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-input">
          </div>
          <div>
            <label class="form-label">Inquiry Type</label>
            <select name="inquiry_type" class="form-input">
              @foreach($inquiryTypes as $type)
                <option value="{{ $type }}">{{ $type }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Answered By</label>
            <select name="answered_by" class="form-input">
              @foreach($crmUsers as $user)
                <option value="{{ $user->id }}" {{ auth()->id() === $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Date</label>
            <input type="date" name="occurred_at" value="{{ now()->toDateString() }}" class="form-input">
          </div>
          <div>
            <label class="form-label">Time</label>
            <input type="time" name="occurred_at_time" value="{{ now()->format('H:i') }}" class="form-input">
          </div>
        </div>
        <div>
          <label class="form-label">Details / Note <span class="text-slate-400 normal-case font-normal">(optional)</span></label>
          <textarea name="details" rows="3" class="form-input" placeholder="What was discussed / outcome…"></textarea>
        </div>
      </div>
      <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 bg-slate-50 rounded-b-xl shrink-0">
        <button type="button" onclick="document.getElementById('logCallModal').classList.add('hidden')" class="btn btn-secondary text-sm">Cancel</button>
        <button type="submit" class="btn btn-primary text-sm">Save</button>
      </div>
    </form>
  </div>
</div>

{{-- Export Call Reports Modal --}}
<div id="exportCallReportsModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[90vh] overflow-hidden flex flex-col text-left">
    <form method="POST" action="{{ route('crm.website.call-reports.export') }}" target="_blank" class="flex flex-col min-h-0"
          onsubmit="setTimeout(() => document.getElementById('exportCallReportsModal').classList.add('hidden'), 400)">
      @csrf
      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center shrink-0">
        <h3 class="font-display font-bold text-lg text-slate-800">Export Call Reports</h3>
        <button type="button" onclick="document.getElementById('exportCallReportsModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 space-y-4 overflow-y-auto min-h-0">
        <p class="text-xs text-slate-400">Exports the reports matching your current search/date filters.</p>
        <input type="hidden" name="search" value="{{ request('search') }}">
        <input type="hidden" name="date_from" value="{{ request('date_from') }}">
        <input type="hidden" name="date_to" value="{{ request('date_to') }}">
        <input type="hidden" name="answered_by" value="{{ request('answered_by') }}">
        <div>
          <label class="form-label text-xs font-semibold text-slate-500">Format</label>
          <div class="grid grid-cols-2 gap-3 mt-1">
            <label class="flex items-center gap-2 p-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-100 transition-colors">
              <input type="radio" name="format" value="pdf" checked class="text-indigo-600">
              <div class="text-sm font-medium text-slate-700">📄 PDF Document</div>
            </label>
            <label class="flex items-center gap-2 p-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-100 transition-colors">
              <input type="radio" name="format" value="google_sheet" class="text-indigo-600">
              <div class="text-sm font-medium text-slate-700">🟢 Google Sheet</div>
            </label>
          </div>
        </div>
      </div>
      <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 bg-slate-50 rounded-b-xl shrink-0">
        <button type="button" onclick="document.getElementById('exportCallReportsModal').classList.add('hidden')" class="btn btn-secondary text-sm">Cancel</button>
        <button type="submit" class="btn btn-primary text-sm">Generate Export</button>
      </div>
    </form>
  </div>
</div>
@endsection
