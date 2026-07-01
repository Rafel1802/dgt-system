@extends('layouts.app')
@section('title', 'New eBay Customer Record')
@section('page_title', 'eBay Manage Customer — Add Record')

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5 flex items-center gap-2">
    <a href="{{ route('crm.ebay.customers.index', $tab) }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to {{ $tabs[$tab] }}</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <h2 class="font-display font-bold text-slate-800 text-lg">New Record — {{ $tabs[$tab] }}</h2>
    </div>

    <form method="POST" action="{{ route('crm.ebay.customers.store', $tab) }}" class="divide-y divide-slate-100">
      @csrf

      @if($errors->any())
      <div class="px-6 py-3 bg-red-50 border-b border-red-100">
        <ul class="text-sm text-red-600 space-y-1">
          @foreach($errors->all() as $err)<li>• {{ $err }}</li>@endforeach
        </ul>
      </div>
      @endif

      <div class="px-6 py-5 space-y-4">

        @if(in_array('n', $columns))
        <div>
          <label class="form-label">N (Sequence #)</label>
          <input type="number" name="n" value="{{ old('n') }}" class="form-input w-28" min="1">
        </div>
        @endif

        @if(in_array('date', $columns))
        <div>
          <label class="form-label">Date</label>
          <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" class="form-input w-auto">
        </div>
        @endif

        @if(in_array('order_date', $columns))
        <div>
          <label class="form-label">Order Date</label>
          <input type="date" name="order_date" value="{{ old('order_date') }}" class="form-input w-auto">
        </div>
        @endif

        @if(in_array('username', $columns))
        <div>
          <label class="form-label">Username</label>
          <input type="text" name="username" value="{{ old('username') }}" class="form-input">
        </div>
        @endif

        @if(in_array('buyer_name', $columns))
        <div>
          <label class="form-label">Buyer Name</label>
          <input type="text" name="buyer_name" value="{{ old('buyer_name') }}" class="form-input">
        </div>
        @endif

        @if(in_array('email', $columns))
        <div>
          <label class="form-label">Email</label>
          <input type="email" name="email" value="{{ old('email') }}" class="form-input">
        </div>
        @endif

        @if(in_array('informations', $columns))
        <div>
          <label class="form-label">Informations</label>
          <textarea name="informations" rows="3" class="form-input">{{ old('informations') }}</textarea>
        </div>
        @endif

        @if(in_array('ebay_store_id', $columns))
        <div>
          <label class="form-label">eBay Store</label>
          @include('crm.partials.store-searchable-select', [
            'name'     => 'ebay_store_id',
            'selected' => old('ebay_store_id'),
            'stores'   => $stores,
          ])
        </div>
        @endif

        @if(in_array('order_id', $columns))
        <div>
          <label class="form-label">Order ID</label>
          <input type="text" name="order_id" value="{{ old('order_id') }}" class="form-input font-mono">
        </div>
        @endif

        @if(in_array('sku_number', $columns))
        <div>
          <label class="form-label">SKU Number</label>
          <input type="text" name="sku_number" value="{{ old('sku_number') }}" class="form-input font-mono">
        </div>
        @endif

        @if(in_array('summary', $columns))
        <div>
          <label class="form-label">Summary</label>
          <textarea name="summary" rows="3" class="form-input">{{ old('summary') }}</textarea>
        </div>
        @endif

        @if(in_array('attention_required', $columns))
        <div>
          <label class="form-label">Attention Required</label>
          <textarea name="attention_required" rows="2" class="form-input">{{ old('attention_required') }}</textarea>
        </div>
        @endif

        @if(in_array('required_attentions', $columns))
        <div>
          <label class="form-label">Required Attentions</label>
          <textarea name="required_attentions" rows="2" class="form-input">{{ old('required_attentions') }}</textarea>
        </div>
        @endif

        @if(in_array('updates', $columns))
        <div>
          <label class="form-label">Updates</label>
          <textarea name="updates" rows="2" class="form-input">{{ old('updates') }}</textarea>
        </div>
        @endif

        @if(in_array('status', $columns))
        <div>
          <label class="form-label">Status</label>
          <select name="status" class="form-input">
            <option value="open" {{ old('status') === 'open' ? 'selected' : '' }}>Open</option>
            <option value="resolved" {{ old('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
            <option value="pending" {{ old('status') === 'pending' ? 'selected' : '' }}>Pending</option>
          </select>
        </div>
        @endif

      </div>

      <div class="px-6 py-4 flex gap-3 justify-end bg-slate-50">
        <a href="{{ route('crm.ebay.customers.index', $tab) }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Record</button>
      </div>
    </form>
  </div>
</div>
@endsection
