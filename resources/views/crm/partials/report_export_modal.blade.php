{{--
  Report Export Modal — reusable across all CRM index views.
  Usage:
    @include('crm.partials.report_export_modal', [
        'type' => 'customers', // 'customers', 'logistics', 'website', 'ebay'
        'btnClass' => 'btn btn-secondary', // Optional
        'btnText' => 'Export Report', // Optional
    ])
--}}
@php
    $btnClass = $btnClass ?? 'btn btn-secondary';
    $btnText  = $btnText ?? '📊 Export Report';
    $crmUsers = \App\Models\User::crmMembers()->orderBy('name')->get(['id', 'name']);
@endphp

<div x-data="{ open: false }" class="inline-block">
    {{-- Trigger Button --}}
    <button type="button" @click="open = true" class="{{ $btnClass }}">
        {{ $btnText }}
    </button>

    {{-- Modal --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-[200] flex items-center justify-center" role="dialog" aria-modal="true">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="open = false"></div>

        {{-- Content --}}
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6 space-y-4 text-left">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-display font-bold text-slate-800 text-base">Export CRM Report</h3>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
            </div>

            <form action="{{ route('crm.export', $type) }}" method="GET" target="_blank" @submit="setTimeout(() => { open = false; }, 500)">
                <div class="space-y-4">
                    {{-- Date Range --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label text-xs font-semibold text-slate-500">Start Date</label>
                            <input type="date" name="start_date" class="form-input text-sm py-1.5" value="{{ now()->startOfMonth()->format('Y-m-d') }}">
                        </div>
                        <div>
                            <label class="form-label text-xs font-semibold text-slate-500">End Date</label>
                            <input type="date" name="end_date" class="form-input text-sm py-1.5" value="{{ now()->format('Y-m-d') }}">
                        </div>
                    </div>

                    {{-- Member Filter --}}
                    <div>
                        <label class="form-label text-xs font-semibold text-slate-500">Filter CRM Member</label>
                        <select name="member_id" class="form-input text-sm py-1.5">
                            <option value="All">All CRM Members</option>
                            @foreach($crmUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Format Selection --}}
                    <div>
                        <label class="form-label text-xs font-semibold text-slate-500">Format</label>
                        <div class="grid grid-cols-2 gap-3 mt-1">
                            <label class="flex items-center gap-2 p-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-100 transition-colors">
                                <input type="radio" name="format" value="pdf" checked class="text-indigo-600">
                                <div class="text-sm font-medium text-slate-700">📄 PDF Document</div>
                            </label>
                            <label class="flex items-center gap-2 p-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-100 transition-colors">
                                <input type="radio" name="format" value="csv" class="text-indigo-600">
                                <div class="text-sm font-medium text-slate-700">📊 CSV Spreadsheet</div>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex gap-3 justify-end pt-4 border-t border-slate-100 mt-5">
                    <button type="button" @click="open = false" class="btn btn-secondary py-2">Cancel</button>
                    <button type="submit" class="btn btn-primary py-2 px-4">Generate Report</button>
                </div>
            </form>
        </div>
    </div>
</div>
