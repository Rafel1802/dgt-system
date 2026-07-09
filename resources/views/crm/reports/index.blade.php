@extends('layouts.app')
@section('title', 'CRM Reports')
@section('page_title', 'Staff Performance & Team Reports')

@section('content')
<div class="animate-fade-in">

  @foreach($teams as $key => $team)
  <div class="flex items-center justify-between mb-3 {{ !$loop->first ? 'mt-8' : '' }}">
    <h3 class="font-display font-bold text-slate-800 text-lg">{{ $team['label'] }} <span class="text-slate-400 text-sm font-normal">(this month)</span></h3>
  </div>

  @if($team['members']->isEmpty())
  <div class="card p-6 text-center text-slate-400 text-sm">No staff activity recorded for this team yet.</div>
  @else
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    @foreach($team['members'] as $row)
    <a href="{{ route('crm.reports.show', $row['user']) }}" class="card p-4 block hover:shadow-md hover:border-indigo-200 transition-all">
      <div class="flex items-center gap-2 mb-3">
        <img src="{{ $row['user']->avatar_url }}" class="w-6 h-6 rounded-full">
        <h4 class="font-semibold text-slate-800 text-sm">{{ $row['user']->name }}</h4>
        <svg class="w-3.5 h-3.5 text-slate-300 ml-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
      </div>

      @if($key === 'website')
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Customers handled / mo</span><b class="text-slate-800">{{ $row['crm_handled'] }}</b></div>
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Successful leads</span><b class="text-slate-800">{{ $row['crm_sales'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Calls answered / mo</span><b class="text-slate-800">{{ $row['calls_answered'] }}</b></div>
      @elseif($key === 'ebay')
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Customers handled</span><b class="text-slate-800">{{ $row['ebay_handled'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Neg. feedback solved</span><b class="text-slate-800">{{ $row['neg_solved'] }}</b></div>
      @elseif($key === 'tech_support')
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Cases assigned</span><b class="text-slate-800">{{ $row['assigned'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Cases resolved</span><b class="text-slate-800">{{ $row['resolved'] }}</b></div>
      @elseif($key === 'logistic')
      <div class="flex justify-between text-sm mb-0.5"><span class="text-slate-500">Shipments assigned</span><b class="text-slate-800">{{ $row['assigned'] }}</b></div>
      <div class="flex justify-between text-sm"><span class="text-slate-500">Complete</span><b class="text-slate-800">{{ $row['complete'] }}</b></div>
      @endif
    </a>
    @endforeach
  </div>
  @endif
  @endforeach

  <h3 class="font-display font-bold text-slate-800 text-lg mb-3 mt-8">Team Reports</h3>
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
