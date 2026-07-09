@extends('layouts.app')
@section('title', 'CRM Reports')
@section('page_title', 'Staff Performance & Team Reports')

@section('content')
<div class="animate-fade-in">

  <div class="flex items-center justify-between mb-3">
    <h3 class="font-display font-bold text-slate-800 text-lg">Staff Performance <span class="text-slate-400 text-sm font-normal">(this month)</span></h3>
  </div>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    @foreach($staff as $row)
    <div class="card p-4">
      <div class="flex items-center gap-2 mb-3">
        <img src="{{ $row['user']->avatar_url }}" class="w-6 h-6 rounded-full">
        <h4 class="font-semibold text-slate-800 text-sm">{{ $row['user']->name }}</h4>
      </div>
      <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">CRM Website</p>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Customers handled / mo</span><b class="text-slate-800">{{ $row['crm_handled'] }}</b></div>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Total sales</span><b class="text-slate-800">{{ $row['crm_sales'] }}</b></div>
      <div class="flex justify-between text-sm mb-3"><span class="text-slate-500">Calls answered / mo</span><b class="text-slate-800">{{ $row['calls_answered'] }}</b></div>
      <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">eBay</p>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Customers handled</span><b class="text-slate-800">{{ $row['ebay_handled'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Neg. feedback solved</span><b class="text-slate-800">{{ $row['neg_solved'] }}</b></div>
    </div>
    @endforeach
  </div>

  <h3 class="font-display font-bold text-slate-800 text-lg mb-3">Team Reports</h3>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <div class="card p-4 text-center">
      <div class="text-2xl font-bold text-red-600">{{ $negTotalMonth }}</div>
      <div class="text-xs text-slate-500 mt-0.5">eBay — Negative Feedback / Month</div>
    </div>
    <div class="card p-4 text-center">
      <div class="text-2xl font-bold text-emerald-600">{{ $negSolvedMonth }}</div>
      <div class="text-xs text-slate-500 mt-0.5">eBay — Solved / Month</div>
    </div>
    <div class="card p-4 text-center">
      <div class="text-2xl font-bold text-indigo-700">${{ number_format($salesMonth, 2) }}</div>
      <div class="text-xs text-slate-500 mt-0.5">eBay — Sales / Month</div>
    </div>
    <div class="card p-4 text-center">
      <div class="text-2xl font-bold text-emerald-600">{{ $completeWeek }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Logistics — Complete / Week</div>
    </div>
    <div class="card p-4 text-center">
      <div class="text-2xl font-bold text-emerald-600">{{ $completeMonth }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Logistics — Complete / Month</div>
    </div>
    <div class="card p-4 text-center">
      <div class="text-2xl font-bold text-red-600">{{ $techWeek }} / {{ $techMonth }}</div>
      <div class="text-xs text-slate-500 mt-0.5">Tech Support — Issues / Week / Month</div>
    </div>
  </div>

</div>
@endsection
