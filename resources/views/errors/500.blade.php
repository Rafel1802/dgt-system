@extends('layouts.app')
@section('title', 'Server Error')
@section('page_title', 'Server Error')
@section('hide_back', true)

@section('content')
<div class="flex flex-col items-center justify-center min-h-[50vh] text-center animate-fade-in px-4">
    <div class="w-20 h-20 bg-amber-100 text-amber-500 rounded-full flex items-center justify-center mb-6 shadow-sm border border-amber-200">
        <svg class="w-10 h-10" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
    </div>
    <h1 class="text-3xl font-display font-bold text-slate-800 mb-2">500 | Server Error</h1>
    <p class="text-slate-500 mb-8 max-w-md mx-auto">
        Something went wrong on our end. Please try again later or contact support if the issue persists.
    </p>
    <a href="{{ route('dashboard') }}" class="btn btn-primary px-6 py-2.5">
        Back to Dashboard
    </a>
</div>
@endsection
