@extends('layouts.app')
@section('title', 'Not Found')
@section('page_title', 'Page Not Found')
@section('hide_back', true)

@section('content')
<div class="flex flex-col items-center justify-center min-h-[50vh] text-center animate-fade-in px-4">
    <div class="w-20 h-20 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mb-6 shadow-sm border border-slate-200">
        <svg class="w-10 h-10" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
    </div>
    <h1 class="text-3xl font-display font-bold text-slate-800 mb-2">404 | Not Found</h1>
    <p class="text-slate-500 mb-8 max-w-md mx-auto">
        The page or record you are looking for does not exist or has been deleted.
    </p>
    <a href="{{ route('crm.dashboard') }}" class="btn btn-primary px-6 py-2.5">
        Back to Dashboard
    </a>
</div>
@endsection
