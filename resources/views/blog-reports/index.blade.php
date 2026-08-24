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

    <div class="bg-white shadow rounded-xl overflow-hidden">
        <form action="{{ route('blog-reports.generate') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div>
                <label for="sheet_url" class="block text-sm font-medium text-gray-700">Google Sheet URL <span class="text-red-500">*</span></label>
                <div class="mt-1">
                    <input type="url" name="sheet_url" id="sheet_url" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="https://docs.google.com/spreadsheets/d/...">
                </div>
                <p class="mt-1 text-xs text-gray-500">Ensure the sheet is public ("Anyone with the link can view").</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div>
                    <label for="month_label" class="block text-sm font-medium text-gray-700">Report Month Label</label>
                    <div class="mt-1">
                        <input type="text" name="month_label" id="month_label" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="e.g. July">
                    </div>
                </div>

                <div>
                    <label for="date_filter" class="block text-sm font-medium text-gray-700">Date Filter (Optional)</label>
                    <div class="mt-1">
                        <input type="text" name="date_filter" id="date_filter" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="e.g. /07 or 24/07">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Filters rows where 'Dated' contains this.</p>
                </div>

                <div>
                    <label for="class_filter" class="block text-sm font-medium text-gray-700">Class Filter</label>
                    <div class="mt-1">
                        <select name="class_filter" id="class_filter" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="all">All Classes</option>
                            <option value="1">Class 1</option>
                            <option value="2">Class 2</option>
                            <option value="3">Class 3</option>
                            <option value="4">Class 4</option>
                            <option value="5">Class 5</option>
                            <option value="6">Class 6</option>
                            <option value="7">Class 7</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="pt-4 flex justify-end">
                <button type="submit" class="inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Generate Report
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
