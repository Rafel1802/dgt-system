@extends('layouts.app')
@section('title', 'Logistics — Logistic Issues')
@section('page_title', 'Logistic Issues')

@section('content')
<div class="animate-fade-in">

  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <p class="text-sm text-slate-500">Every customer currently flagged with a logistics/shipment problem, across Website, eBay, and Logistics.</p>
    <form method="GET" action="{{ route('crm.logistics.issues.index') }}" class="flex gap-2">
      <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name/email/phone…" class="form-input text-sm py-2 w-64">
      <button type="submit" class="btn btn-secondary text-sm">Search</button>
    </form>
  </div>

  <div class="card p-0 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <p class="text-sm text-slate-500">Showing <strong>{{ $customers->count() }}</strong> customer(s) with a logistic issue</p>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left">Name</th>
            <th class="px-4 py-3 text-left">Contact</th>
            <th class="px-4 py-3 text-left">Source</th>
            <th class="px-4 py-3 text-left">Status</th>
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
            <td class="px-4 py-3 text-xs text-slate-500">{{ $customer['handler'] ?: '—' }}</td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-1">
                @if($customer['link'])
                <a href="{{ $customer['link'] }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="View">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                </a>
                @endif
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center py-16 text-slate-400">
              <div class="text-4xl mb-3">🚚</div>
              No customers currently flagged with a logistic issue.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
