@extends('layouts.app')
@section('title', 'Logistics — Logistic Issues')
@section('page_title', 'Logistic Issues')

@section('content')
<div class="animate-fade-in" x-data="{ showResolveModal: false, resolveSource: '', resolveId: '', resolveName: '' }">

  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <p class="text-sm text-slate-500">Every customer currently flagged with a logistics/shipment problem, across Website, eBay, and Logistics.</p>
    <form method="GET" action="{{ route('crm.logistics.issues.index') }}" class="flex gap-2">
      <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name/email/phone…" class="form-input text-sm py-2 w-64">
      <button type="submit" class="btn btn-secondary text-sm">Search</button>
    </form>
  </div>

  <div class="card p-0 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <p class="text-sm text-slate-500">
        Showing <strong>{{ $customers->count() }}</strong>
        of <strong>{{ $customers->total() }}</strong> customer(s) with a logistic issue
      </p>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left">Name</th>
            <th class="px-4 py-3 text-left">Contact</th>
            <th class="px-4 py-3 text-left">Source</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Issue Date</th>
            <th class="px-4 py-3 text-left">Handler</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($customers as $customer)
          <tr class="hover:bg-slate-50/70 transition-colors">
            <td class="px-5 py-3">
              @if($customer['link'])
                <a href="{{ $customer['link'] }}" class="font-semibold text-slate-800 hover:text-indigo-600 transition-colors">{{ $customer['name'] }}</a>
              @else
                <span class="font-semibold text-slate-800">{{ $customer['name'] }}</span>
              @endif
            </td>
            <td class="px-4 py-3 text-xs text-slate-500">
              {{ $customer['email'] ?: '—' }}<br>{{ $customer['phone'] ?: '' }}
            </td>
            <td class="px-4 py-3">
              <span class="badge text-xs px-2 py-0.5 rounded-full"
                    style="background:{{ $customer['source_color'] }}22; color:{{ $customer['source_color'] }}">
                {{ $customer['source_icon'] }} {{ $customer['source'] }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $customer['status_color'] ?? '#94a3b8' }}22; color:{{ $customer['status_color'] ?? '#94a3b8' }}">
                {{ $customer['status_label'] }}
              </span>
            </td>
            <td class="px-4 py-3 text-xs text-slate-500">
              @if(!empty($customer['issue_date']))
                <span class="font-medium text-slate-700">{{ $customer['issue_date'] }}</span>
              @else
                —
              @endif
            </td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ $customer['handler'] ?: '—' }}</td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-1">
                @if($customer['link'])
                <a href="{{ $customer['link'] }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="View">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                </a>
                @endif
                <button type="button" 
                        class="btn btn-secondary btn-icon text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50"
                        style="width:28px;height:28px;" 
                        title="Resolve Issue"
                        @click="resolveSource = '{{ $customer['source'] }}'; resolveId = '{{ $customer['id'] }}'; resolveName = '{{ addslashes($customer['name']) }}'; showResolveModal = true;">
                  <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                </button>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center py-16 text-slate-400">
              <div class="text-4xl mb-3">🚚</div>
              No customers currently flagged with a logistic issue.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($customers->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">
      {{ $customers->links() }}
    </div>
    @endif
  </div>

  {{-- Resolve Issue Modal --}}
  <div x-show="showResolveModal" x-cloak class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showResolveModal = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" @click.stop x-transition>
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-display font-bold text-slate-800 text-lg">Resolve Logistic Issue</h3>
        <button @click="showResolveModal = false" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form method="POST" :action="`/crm/logistics/issues/${resolveSource}/${resolveId}/resolve`" class="space-y-4">
        @csrf
        <p class="text-sm text-slate-500">
          You are resolving the active logistic issue flag for <strong class="text-slate-700" x-text="resolveName"></strong>. 
          This will update all associated shipments, leads, and eBay records, restoring their status.
        </p>

        <div>
          <label class="form-label font-semibold text-slate-700 block mb-1">Resolution Note <span class="text-red-500">*</span></label>
          <textarea name="notes" required placeholder="Explain how the issue was resolved (e.g. carrier updated, package arrived, tracking corrected)…" class="form-input w-full" rows="4"></textarea>
        </div>

        <div class="flex gap-3 justify-end pt-2">
          <button type="button" @click="showResolveModal = false" class="px-4 py-2.5 text-sm font-semibold rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors">Cancel</button>
          <button type="submit" class="px-4 py-2.5 text-sm font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-md">
            Resolve Issue
          </button>
        </div>
      </form>
    </div>
  </div>

</div>
@endsection
