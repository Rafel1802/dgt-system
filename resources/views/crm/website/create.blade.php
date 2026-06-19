@extends('layouts.app')
@section('title', 'Log New Inquiry')
@section('page_title', 'New Client Inquiry')

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5">
    <a href="{{ route('crm.website.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Website CRM</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <h2 class="font-display font-bold text-slate-800 text-lg">Log Client Inquiry</h2>
      <p class="text-slate-400 text-sm mt-1">Record all details as soon as the inquiry comes in.</p>
    </div>

    <form method="POST" action="{{ route('crm.website.store') }}" class="divide-y divide-slate-100">
      @csrf

      {{-- Client Information --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Client Information</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Client Name <span class="text-red-500">*</span></label>
            <input type="text" name="client_name" value="{{ old('client_name') }}"
                   class="form-input @error('client_name') error @enderror"
                   placeholder="Full name" id="field-client-name" required>
            @error('client_name')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">Phone</label>
            <input type="tel" name="client_phone" value="{{ old('client_phone') }}"
                   class="form-input" placeholder="+61 4xx xxx xxx" id="field-client-phone">
          </div>
          <div>
            <label class="form-label">Email</label>
            <input type="email" name="client_email" value="{{ old('client_email') }}"
                   class="form-input" placeholder="client@email.com" id="field-client-email">
          </div>
          <div>
            <label class="form-label">WhatsApp</label>
            <input type="tel" name="client_whatsapp" value="{{ old('client_whatsapp') }}"
                   class="form-input" placeholder="+61 4xx xxx xxx" id="field-client-whatsapp">
          </div>
        </div>
      </div>

      {{-- Inquiry Details --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Inquiry Details</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Inquiry Source <span class="text-red-500">*</span></label>
            <select name="source" class="form-input @error('source') error @enderror" id="field-source" required>
              <option value="">— Select source —</option>
              @foreach($sources as $s)
                <option value="{{ $s->value }}" {{ old('source') === $s->value ? 'selected' : '' }}>
                  {{ $s->icon() }} {{ $s->label() }}
                </option>
              @endforeach
            </select>
            @error('source')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">Date & Time Received <span class="text-red-500">*</span></label>
            <input type="datetime-local" name="received_at"
                   value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"
                   class="form-input" id="field-received-at" required>
          </div>
          <div>
            <label class="form-label">Product Interested In</label>
            <select name="product_id" class="form-input" id="field-product">
              <option value="">— Select product —</option>
              @foreach($products as $p)
                <option value="{{ $p->id }}" {{ old('product_id') == $p->id ? 'selected' : '' }}>
                  {{ $p->category?->icon() }} {{ $p->name }}
                </option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Or type product interest</label>
            <input type="text" name="product_interested" value="{{ old('product_interested') }}"
                   class="form-input" placeholder="e.g. Excavator, Forklift…" id="field-product-text">
          </div>
        </div>
        <div>
          <label class="form-label">Inquiry Details</label>
          <textarea name="inquiry_details" rows="3" class="form-input"
                    placeholder="What exactly did the client ask? What are they looking for?">{{ old('inquiry_details') }}</textarea>
        </div>
      </div>

      {{-- Lead Classification --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Lead Classification</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Lead Temperature <span class="text-red-500">*</span></label>
            <div class="flex gap-2">
              @foreach($temps as $t)
              <label class="flex-1 cursor-pointer">
                <input type="radio" name="temperature" value="{{ $t->value }}"
                       {{ old('temperature', 'cold') === $t->value ? 'checked' : '' }}
                       class="sr-only peer">
                <div class="text-center py-2 rounded-xl border-2 text-sm font-semibold transition-all
                            border-slate-200 peer-checked:border-transparent peer-checked:text-white"
                     style="peer-checked:background:{{ $t->color() }}"
                     x-bind:style="'border-color: transparent; background: {{ $t->color() }}22; color: {{ $t->color() }}'">
                  {{ $t->icon() }} {{ $t->label() }}
                </div>
              </label>
              @endforeach
            </div>
          </div>
          <div>
            <label class="form-label">Assign To</label>
            <select name="assigned_to" class="form-input" id="field-assigned">
              <option value="">Unassigned</option>
              @foreach($users as $u)
                <option value="{{ $u->id }}" {{ old('assigned_to') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      {{-- Follow-Up --}}
      <div class="px-6 py-5 space-y-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Follow-Up Plan</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Follow-Up Date</label>
            <input type="date" name="follow_up_date" value="{{ old('follow_up_date') }}"
                   class="form-input" id="field-follow-up-date">
          </div>
          <div>
            <label class="form-label">Next Action</label>
            <input type="text" name="next_action" value="{{ old('next_action') }}"
                   class="form-input" placeholder="e.g. Call to discuss pricing"
                   id="field-next-action">
          </div>
        </div>
      </div>

      <div class="px-6 py-4 flex gap-3 justify-end bg-slate-50">
        <a href="{{ route('crm.website.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" id="btn-save-lead">Save Inquiry</button>
      </div>
    </form>
  </div>
</div>
@endsection
