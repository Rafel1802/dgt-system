@extends('layouts.app')
@section('title', 'Website CRM — Call Reports')
@section('page_title', 'Call Reports')

@section('content')
<div class="animate-fade-in">

  <div class="mb-5">
    <a href="{{ route('crm.website.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Leads</a>
  </div>

  {{-- ── Toolbar ─────────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <form method="GET" action="{{ route('crm.website.call-reports.index') }}" class="flex gap-2">
      <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name/phone/email…" class="form-input text-sm py-2 w-64">
      <button type="submit" class="btn btn-secondary text-sm">Search</button>
    </form>
    <button type="button" onclick="document.getElementById('logCallModal').classList.remove('hidden')" class="btn btn-primary text-sm" id="btn-log-call">
      <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      Log Call
    </button>
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
            <th class="px-4 py-3 text-left">Answered By</th>
            <th class="px-4 py-3 text-left">Date</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($callReports as $report)
          <tr class="hover:bg-slate-50/70 transition-colors">
            <td class="px-5 py-3 font-semibold text-slate-800">{{ $report->name }}</td>
            <td class="px-4 py-3 text-slate-500">{{ $report->phone ?: '—' }}</td>
            <td class="px-4 py-3 text-slate-500">{{ $report->email ?: '—' }}</td>
            <td class="px-4 py-3"><span class="badge text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $report->inquiry_type }}</span></td>
            <td class="px-4 py-3 text-slate-500">{{ $report->answeredBy?->name }}</td>
            <td class="px-4 py-3 text-xs text-slate-400">{{ $report->occurred_at?->format('d M Y') }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center py-14">
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
<div id="logCallModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 flex items-center justify-center">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 text-left">
    <form method="POST" action="{{ route('crm.website.call-reports.store') }}">
      @csrf
      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
        <h3 class="font-display font-bold text-lg text-slate-800">Log Call Report</h3>
        <button type="button" onclick="document.getElementById('logCallModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" required class="form-input">
          </div>
          <div>
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-input">
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
        </div>
      </div>
      <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 bg-slate-50 rounded-b-xl">
        <button type="button" onclick="document.getElementById('logCallModal').classList.add('hidden')" class="btn btn-secondary text-sm">Cancel</button>
        <button type="submit" class="btn btn-primary text-sm">Save</button>
      </div>
    </form>
  </div>
</div>
@endsection
