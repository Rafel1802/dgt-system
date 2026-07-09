@extends('layouts.app')
@section('title', 'eBay CRM — General Report')
@section('page_title', 'eBay CRM — General Report')

@section('content')
<div class="animate-fade-in">

  <div class="mb-5">
    <a href="{{ route('crm.ebay.stores.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Stores</a>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="card p-4 text-center">
      <div class="text-2xl font-bold text-red-600">{{ $negTotalMonth }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Negative Feedback / Month</div>
    </div>
    <div class="card p-4 text-center">
      <div class="text-2xl font-bold text-emerald-600">{{ $negSolvedMonth }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Solved Negative Feedback / Month</div>
    </div>
    <div class="card p-4 text-center">
      <div class="text-2xl font-bold text-indigo-700">${{ number_format($salesMonth, 2) }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Total Sales (all stores)</div>
    </div>
  </div>

</div>
@endsection
