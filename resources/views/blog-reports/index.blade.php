@extends('layouts.app')

@section('title', 'Blog Reports Generator')

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Blog Report Generator</h1>
        <p class="mt-2 text-sm text-gray-600">Import and filter blog records from a Google Sheet to generate a formatted report.</p>
    </div>

    @if (session('error'))
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg" role="alert">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-8 py-6">
            <h2 class="text-xl font-bold text-white">Report Configuration</h2>
            <p class="text-blue-100 mt-1 text-sm">Fill in the details below to generate your blog report.</p>
        </div>

        <form action="{{ route('blog-reports.preview') }}" method="POST" target="_blank" data-turbo="false" class="p-8 space-y-8">
            @csrf

            <!-- URL Input -->
            <div>
                <label for="sheet_url" class="block text-base font-semibold text-gray-800 mb-2">Google Sheet URL <span class="text-red-500">*</span></label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </div>
                    <input type="text" name="sheet_url" id="sheet_url" required 
                           class="block w-full pl-12 pr-4 py-4 text-lg border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow bg-gray-50 hover:bg-white" 
                           placeholder="https://docs.google.com/spreadsheets/d/...">
                </div>
                <p class="mt-2 text-sm text-gray-500 flex items-center">
                    <svg class="h-4 w-4 mr-1 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    Ensure the sheet is public ("Anyone with the link can view").
                </p>
            </div>

            <!-- Date Range and Month -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Date From -->
                <div>
                    <label for="date_from" class="block text-base font-semibold text-gray-800 mb-2">From Date (Optional)</label>
                    <div class="relative">
                        <input type="date" name="date_from" id="date_from" 
                               class="block w-full px-4 py-4 text-lg border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow bg-gray-50 hover:bg-white">
                    </div>
                </div>

                <!-- Date To -->
                <div>
                    <label for="date_to" class="block text-base font-semibold text-gray-800 mb-2">To Date (Optional)</label>
                    <div class="relative">
                        <input type="date" name="date_to" id="date_to" 
                               class="block w-full px-4 py-4 text-lg border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow bg-gray-50 hover:bg-white">
                    </div>
                </div>

                <!-- Report Month Label -->
                <div>
                    <label for="month_label" class="block text-base font-semibold text-gray-800 mb-2">Report Month Title</label>
                    <div class="relative">
                        <select name="month_label" id="month_label" 
                               class="block w-full px-4 py-4 text-lg border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow bg-gray-50 hover:bg-white">
                            @foreach(['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] as $month)
                                <option value="Blog Reports - {{ $month }}" {{ date('F') == $month ? 'selected' : '' }}>
                                    Blog Reports - {{ $month }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">The title displayed on the final report.</p>
                </div>
            </div>

            <div class="pt-6 mt-6 border-t border-gray-100 flex justify-end">
                <button type="submit" class="inline-flex items-center justify-center py-4 px-8 border border-transparent shadow-lg text-lg font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-500 focus:ring-offset-2 transition-all transform hover:scale-105">
                    <svg class="h-6 w-6 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    Preview Report
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
