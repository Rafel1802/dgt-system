@extends('layouts.auth')
@section('title', 'Shared Call Reports')

@section('content')
<div class="min-h-full bg-slate-50 py-8 px-4">
  <div class="max-w-5xl mx-auto">

    <div class="mb-6">
      <h1 class="font-display font-bold text-2xl text-slate-800">📞 Call Reports</h1>
      <p class="text-sm text-slate-500 mt-1">
        Shared read-only view · {{ $callReports->total() }} report(s)
        @if(!empty($share->filters['date_from']) || !empty($share->filters['date_to']))
          · {{ $share->filters['date_from'] ?? 'Start' }} — {{ $share->filters['date_to'] ?? 'Now' }}
        @endif
      </p>
    </div>

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
              <td class="px-4 py-3"><span class="badge text-xs px-2 py-0.5 rounded-full {{ \App\Models\CallReport::badgeClassForInquiryType($report->inquiry_type) }}">{{ $report->inquiry_type }}</span></td>
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

    <p class="text-xs text-slate-400 mt-6 text-center">Shared from KIUQ SYSTEM CRM · This link stays live and shows current data.</p>
  </div>
</div>
@endsection
