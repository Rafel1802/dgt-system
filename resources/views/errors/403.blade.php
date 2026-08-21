@extends('layouts.app')
@section('title', 'Unauthorized')
@section('page_title', 'Access Denied')
@section('hide_back', true)

@section('content')
<div class="flex flex-col items-center justify-center min-h-[50vh] text-center animate-fade-in px-4">
    <div class="w-20 h-20 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mb-6 shadow-sm border border-rose-200">
        <svg class="w-10 h-10" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
        </svg>
    </div>
    <h1 class="text-3xl font-display font-bold text-slate-800 mb-2">403 | Unauthorized</h1>
    <p class="text-slate-500 mb-8 max-w-md mx-auto">
        {{ $exception->getMessage() ?: 'You do not have permission to view this page or perform this action.' }}
    </p>
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('crm.dashboard') }}" class="btn btn-primary px-6 py-2.5">
        Go Back
    </a>
</div>
@endsection
