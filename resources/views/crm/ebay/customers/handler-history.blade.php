@extends('layouts.app')
@section('title', 'My Handler Assignment History')
@section('page_title', 'Handler Assignment History')

@section('content')
<div class="animate-fade-in">

  <div class="mb-5">
    <a href="{{ route('crm.ebay.customers.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to eBay Customers</a>
  </div>

  <p class="text-sm text-slate-500 mb-5">Every eBay customer record you've ever been assigned to handle, most recent first.</p>

  <div class="card p-0 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left">Customer</th>
            <th class="px-4 py-3 text-left">Assigned</th>
            <th class="px-4 py-3 text-left">Ended</th>
            <th class="px-4 py-3 text-left">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($entries as $entry)
          <tr class="hover:bg-slate-50/70 transition-colors">
            <td class="px-5 py-3 font-semibold text-slate-800">
              @if($entry->record)
                <a href="{{ route('crm.ebay.customers.show', $entry->record) }}" class="hover:text-indigo-600">
                  {{ $entry->record->buyer_name ?: $entry->record->username }}
                </a>
              @else
                <span class="text-slate-400">Deleted record</span>
              @endif
            </td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ $entry->started_at?->format('d M Y, g:ia') }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">
              {{ $entry->ended_at ? $entry->ended_at->format('d M Y, g:ia') : '—' }}
            </td>
            <td class="px-4 py-3">
              @if(! $entry->ended_at)
                <span class="badge text-xs px-2 py-0.5 rounded-full bg-sky-100 text-sky-700">Active</span>
              @endif
              @if($entry->confirmed_at)
                <span class="badge text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Confirmed</span>
              @else
                <span class="badge text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Pending Confirmation</span>
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="4" class="text-center py-14">
              <div class="text-4xl mb-3">🗂️</div>
              <p class="text-slate-500 font-medium">No handler assignments yet</p>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($entries->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">{{ $entries->links() }}</div>
    @endif
  </div>

</div>
@endsection
