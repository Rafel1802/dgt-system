@extends('layouts.app')
@section('title', 'Edit — ' . $lead->client_name)
@section('page_title', 'Edit Lead')

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5 flex items-center justify-between">
    <a href="{{ route('crm.website.show', $lead) }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Profile</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100 flex items-center gap-3">
      <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold"
           style="background:{{ $lead->status?->color() ?? '#94a3b8' }}">
        {{ strtoupper(substr($lead->client_name, 0, 1)) }}
      </div>
      <div>
        <h2 class="font-display font-bold text-slate-800 text-lg">{{ $lead->client_name }}</h2>
        <span class="text-xs px-2 py-0.5 rounded-full font-semibold"
              style="background:{{ $lead->status?->color() }}22; color:{{ $lead->status?->color() }}">
          {{ $lead->status?->label() }}
        </span>
      </div>
    </div>

    <form method="POST" action="{{ route('crm.website.update', $lead) }}" class="divide-y divide-slate-100" x-data="{
      status: '{{ old('status', $lead->status?->value) }}',
    }">
      @csrf @method('PUT')

      {{-- Client Information --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Client Information</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Client Name <span class="text-red-500">*</span></label>
            <input type="text" name="client_name" value="{{ old('client_name', $lead->client_name) }}" class="form-input" required>
            @error('client_name')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">Phone</label>
            <input type="tel" name="client_phone" value="{{ old('client_phone', $lead->client_phone) }}" class="form-input">
          </div>
          <div>
            <label class="form-label">Email</label>
            <input type="email" name="client_email" value="{{ old('client_email', $lead->client_email) }}" class="form-input">
          </div>
          <div>
            <label class="form-label">WhatsApp</label>
            <input type="tel" name="client_whatsapp" value="{{ old('client_whatsapp', $lead->client_whatsapp) }}" class="form-input">
          </div>
        </div>
      </div>

      {{-- Inquiry Details --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Inquiry Details</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Source <span class="text-red-500">*</span></label>
            <select name="source" class="form-input" required>
              @foreach($sources as $s)
              <option value="{{ $s->value }}" {{ old('source', $lead->source?->value) === $s->value ? 'selected' : '' }}>
                {{ $s->icon() }} {{ $s->label() }}
              </option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Product (catalogue)</label>
            <select name="product_id" class="form-input">
              <option value="">— None —</option>
              @foreach($products as $p)
              <option value="{{ $p->id }}" {{ old('product_id', $lead->product_id) == $p->id ? 'selected' : '' }}>
                {{ $p->category?->icon() }} {{ $p->name }}
              </option>
              @endforeach
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="form-label">Free-text product interest</label>
            <input type="text" name="product_interested" value="{{ old('product_interested', $lead->product_interested) }}" class="form-input" placeholder="e.g. Excavator, Forklift…">
          </div>
        </div>
        <div>
          <label class="form-label">Inquiry Details</label>
          <textarea name="inquiry_details" rows="3" class="form-input">{{ old('inquiry_details', $lead->inquiry_details) }}</textarea>
        </div>
      </div>

      {{-- CRM Status --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">CRM Status</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Pipeline Status</label>
            <select name="status" class="form-input" x-model="status">
              @foreach($statuses as $s)
              <option value="{{ $s->value }}" {{ old('status', $lead->status?->value) === $s->value ? 'selected' : '' }}>
                {{ $s->label() }}
              </option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Temperature</label>
            <select name="temperature" class="form-input">
              @foreach($temps as $t)
              <option value="{{ $t->value }}" {{ old('temperature', $lead->temperature?->value) === $t->value ? 'selected' : '' }}>
                {{ $t->icon() }} {{ $t->label() }}
              </option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Handled By (CRM Member)</label>
            @if(auth()->user()->canDeleteCrmRecords('website'))
            @include('crm.partials.member-searchable-select', [
              'name'     => 'handled_by',
              'selected' => old('handled_by', $lead->handled_by),
              'members'  => $crmUsers
            ])
            @else
            <input type="text" class="form-input bg-slate-50 text-slate-500" value="{{ $lead->handler?->name ?? '— Unassigned —' }}" disabled>
            <p class="text-xs text-slate-400 mt-1">Only a Supervisor or Boss can reassign the handler.</p>
            @endif
          </div>
          <div>
            <label class="form-label">Follow-Up Date</label>
            <input type="date" name="follow_up_date" value="{{ old('follow_up_date', $lead->follow_up_date?->format('Y-m-d')) }}" class="form-input">
          </div>
        </div>
        <div>
          <label class="form-label">Follow-Up Notes</label>
          <textarea name="follow_up_notes" rows="2" class="form-input">{{ old('follow_up_notes', $lead->follow_up_notes) }}</textarea>
        </div>
        <div>
          <label class="form-label">Next Action</label>
          <input type="text" name="next_action" value="{{ old('next_action', $lead->next_action) }}" class="form-input" placeholder="e.g. Call to discuss finance options">
        </div>
      </div>

      {{-- Products are logged separately via "Mark Successful" or "+ Add New Order" on the Lead Profile page, not here — editing lead details shouldn't silently create a new order. --}}

      {{-- Lost Reason --}}
      @if($lead->status?->value === 'lost' || request('show_lost'))
      <div class="px-6 py-5">
        <label class="form-label">Lost / Not Interested Reason</label>
        <textarea name="lost_reason" rows="2" class="form-input" placeholder="Why was this lead lost?">{{ old('lost_reason', $lead->lost_reason) }}</textarea>
      </div>
      @endif

      <div class="px-6 py-4 flex gap-3 justify-between bg-slate-50">
        @if(auth()->user()->canDeleteCrmRecords('website'))
        <button type="submit" form="delete-website-lead-form" class="btn btn-danger text-sm">Delete Lead</button>
        @else
        <div></div>
        @endif
        <div class="flex gap-3">
          <a href="{{ route('crm.website.show', $lead) }}" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </div>
    </form>

    @if(auth()->user()->canDeleteCrmRecords('website'))
    <form id="delete-website-lead-form"
          method="POST"
          action="{{ route('crm.website.destroy', $lead) }}"
          data-confirm-title="{{ $lead->customer ? 'Delete lead and customer?' : 'Delete lead?' }}"
          data-confirm="{{ $lead->customer
              ? 'This lead is linked to "' . $lead->customer->name . '" — deleting it will PERMANENTLY delete that customer and everything tied to them across every CRM domain (other leads, eBay records, shipments, tech support cases). This cannot be undone.'
              : 'Delete this lead? All follow-ups will be removed.' }}"
          data-confirm-text="{{ $lead->customer ? 'Delete lead & customer' : 'Delete lead' }}"
          data-confirm-tone="danger"
          class="hidden">
      @csrf @method('DELETE')
    </form>
    @endif
  </div>
</div>
@endsection
