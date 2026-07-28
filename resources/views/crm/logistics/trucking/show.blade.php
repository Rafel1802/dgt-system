@extends('layouts.app')
@section('title', 'Trucking Profile — ' . $truckingCompany->company_name)
@section('page_title', 'Trucking Profile')

@section('content')
<div class="animate-fade-in">
  <div class="mb-5 flex justify-between">
    <a href="{{ route('crm.logistics.trucking.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Trucking Companies</a>
    <a href="{{ route('crm.logistics.trucking.edit', $truckingCompany) }}" class="btn btn-secondary text-sm">Edit Company</a>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Left Col: Details --}}
    <div class="lg:col-span-1 space-y-6">
      <div class="card p-6">
        <div class="flex justify-between items-start mb-4">
          <h2 class="font-display font-bold text-slate-800 text-xl">{{ $truckingCompany->company_name }}</h2>
          <span class="badge {{ $truckingCompany->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
            {{ $truckingCompany->is_active ? 'Active' : 'Inactive' }}
          </span>
        </div>

        <div class="space-y-4">
          @if($truckingCompany->pic_name)
          <div>
            <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Contact Person</span>
            <p class="text-sm text-slate-800">{{ $truckingCompany->pic_name }}</p>
          </div>
          @endif
          @if($truckingCompany->phone)
          <div>
            <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Phone</span>
            <p class="text-sm text-slate-800">{{ $truckingCompany->phone }}</p>
          </div>
          @endif
          @if($truckingCompany->email)
          <div>
            <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Email</span>
            <p class="text-sm text-slate-800"><a href="mailto:{{ $truckingCompany->email }}" class="text-indigo-600 hover:underline">{{ $truckingCompany->email }}</a></p>
          </div>
          @endif
          @if($truckingCompany->address)
          <div>
            <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Address</span>
            <p class="text-sm text-slate-800 whitespace-pre-wrap">{{ $truckingCompany->address }}</p>
          </div>
          @endif
          @if($truckingCompany->notes)
          <div>
            <span class="block text-xs uppercase text-slate-400 font-semibold mb-1">Notes</span>
            <div class="text-sm text-slate-600 whitespace-pre-wrap">{{ $truckingCompany->notes }}</div>
          </div>
          @endif
        </div>
      </div>

      {{-- Drivers --}}
      <div class="card p-6">
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Drivers</h4>

        <div class="mb-3">
          @forelse($truckingCompany->drivers as $driver)
          <div class="flex items-center justify-between py-1.5 {{ !$loop->last ? 'border-b border-slate-50' : '' }}">
            <div>
              <span class="text-sm text-slate-800">{{ $driver->name }}</span>
              <span class="text-xs text-slate-500 ml-2">{{ $driver->phone ?: '—' }}</span>
            </div>
            @if(auth()->user()->canDeleteCrmRecords('logistic'))
            <button type="submit" form="delete-driver-{{ $driver->id }}-form" class="text-slate-300 hover:text-red-500" title="Remove driver">
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
            <form id="delete-driver-{{ $driver->id }}-form" method="POST" action="{{ route('crm.logistics.trucking.drivers.destroy', [$truckingCompany, $driver]) }}"
                  onsubmit="return confirm('Remove this driver?')" class="hidden">
              @csrf @method('DELETE')
            </form>
            @endif
          </div>
          @empty
          <p class="text-slate-400 text-sm">No drivers on file.</p>
          @endforelse
        </div>

        <form method="POST" action="{{ route('crm.logistics.trucking.drivers.store', $truckingCompany) }}" class="pt-3 border-t border-slate-100 space-y-2">
          @csrf
          <div class="flex gap-2">
            <input type="text" name="name" placeholder="Full Name" class="form-input text-sm flex-1" required>
            <input type="tel" name="phone" placeholder="Phone Number" class="form-input text-sm w-32">
          </div>
          @error('name')<p class="form-error">{{ $message }}</p>@enderror
          <button type="submit" class="btn btn-secondary text-xs w-full">+ Add Driver</button>
        </form>
      </div>
    </div>

    {{-- Right Col: Shipments --}}
    <div class="lg:col-span-2">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h3 class="font-display font-bold text-slate-800 text-lg">Shipments</h3>
        <a href="{{ route('crm.logistics.shipments.create', ['truck_company_id' => $truckingCompany->id]) }}" class="btn btn-primary text-sm">
          + New Shipment
        </a>
      </div>

      <div class="card p-0 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                <th class="px-5 py-3 text-left">Shipment Code</th>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">Customers</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-left">Handled By</th>
                <th class="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              @forelse($shipments as $shipment)
              <tr class="hover:bg-slate-50/70 transition-colors">
                <td class="px-5 py-3">
                  <a href="{{ route('crm.logistics.shipments.show', $shipment) }}" class="font-semibold text-indigo-600 hover:underline">
                    {{ $shipment->shipment_code }}
                  </a>
                </td>
                <td class="px-4 py-3 text-xs text-slate-500">
                  {{ $shipment->estimated_arrival ? $shipment->estimated_arrival->format('d M Y') : '—' }}
                </td>
                <td class="px-4 py-3">
                  <span class="badge bg-slate-100 text-slate-600">{{ $shipment->shipment_customers_count }} customers</span>
                </td>
                <td class="px-4 py-3">
                  <span class="badge text-xs px-2 py-0.5 rounded-full" style="background:{{ $shipment->statusColor() }}22; color:{{ $shipment->statusColor() }}">
                    {{ $shipment->statusLabel() }}
                  </span>
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs">
                  {{ $shipment->assignee?->name ?? 'Unassigned' }}
                </td>
                <td class="px-4 py-3">
                  <div class="flex justify-end gap-1">
                    <a href="{{ route('crm.logistics.shipments.show', $shipment) }}" class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="View">
                      <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    </a>
                  </div>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="6" class="text-center py-10">
                  <p class="text-slate-500 font-medium">No shipments handled by this company</p>
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if($shipments->hasPages())
        <div class="px-6 py-4 border-t border-slate-100">{{ $shipments->links() }}</div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
