{{--
  Report Export Dropdown & Modal — reusable across all CRM index views.
  Clean UX: 1-click dropdown to instantly download active filtered list as PDF or CSV,
  with an optional modal for custom date/member filtering.
--}}
@php
    $btnClass = $btnClass ?? 'btn btn-secondary text-sm py-1.5';
    $btnText  = $btnText ?? '📊 Export Report';
    $crmUsers = \App\Models\User::crmMembers()->orderBy('name')->get(['id', 'name']);
    $queryParams = request()->query();
@endphp

<div x-data="{ open: false, showCustom: false, loading: false }" class="relative inline-block text-left">
    {{-- Trigger Button --}}
    <button type="button" @click="open = !open" class="{{ $btnClass }} inline-flex items-center gap-1.5 shadow-sm">
        <span>{{ $btnText }}</span>
        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    {{-- Dropdown Menu --}}
    <div x-show="open" 
         @click.away="open = false" 
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95 transform -translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         x-cloak 
         class="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-xl border border-slate-100 z-[200] overflow-hidden p-2 space-y-1 text-slate-700 text-sm">
        
        <div class="px-3 py-1.5 text-[11px] font-semibold tracking-wider text-slate-400 uppercase border-b border-slate-100 mb-1">
            Quick Export (Active List)
        </div>

        {{-- Direct PDF Export --}}
        <a href="{{ route('crm.export', array_merge($queryParams, ['type' => $type, 'format' => 'pdf'])) }}"
           @click="loading = 'pdf'; setTimeout(() => { open = false; loading = false; }, 1500)"
           class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-slate-50 transition-colors group">
            <span class="text-base group-hover:scale-110 transition-transform">📄</span>
            <div class="flex-1 text-left">
                <div class="font-medium text-slate-800 text-xs">PDF Document</div>
                <div class="text-[10px] text-slate-400">Instant PDF download of current view</div>
            </div>
            <template x-if="loading === 'pdf'">
                <svg class="animate-spin w-4 h-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            </template>
        </a>

        {{-- Direct CSV Export --}}
        <a href="{{ route('crm.export', array_merge($queryParams, ['type' => $type, 'format' => 'csv'])) }}"
           @click="loading = 'csv'; setTimeout(() => { open = false; loading = false; }, 1500)"
           class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-slate-50 transition-colors group">
            <span class="text-base group-hover:scale-110 transition-transform">📊</span>
            <div class="flex-1 text-left">
                <div class="font-medium text-slate-800 text-xs">CSV Spreadsheet</div>
                <div class="text-[10px] text-slate-400">Excel / Google Sheets spreadsheet</div>
            </div>
            <template x-if="loading === 'csv'">
                <svg class="animate-spin w-4 h-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            </template>
        </a>

        <div class="border-t border-slate-100 pt-1 mt-1">
            <button type="button" 
                    @click="showCustom = true; open = false"
                    class="w-full flex items-center justify-between px-3 py-1.5 text-xs text-indigo-600 font-medium hover:bg-indigo-50/50 rounded-lg transition-colors">
                <span>Custom Date & Member Range…</span>
                <span>⚙️</span>
            </button>
        </div>
    </div>

    {{-- Advanced Custom Filter Modal --}}
    <div x-show="showCustom" x-cloak class="fixed inset-0 z-[300] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showCustom = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-left space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-bold text-slate-800 text-sm">Export Custom Date & Member Range</h3>
                <button type="button" @click="showCustom = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
            </div>
            <form action="{{ route('crm.export', $type) }}" method="GET" @submit="setTimeout(() => { showCustom = false; }, 1000)">
                <input type="hidden" name="status_filter" value="{{ request('status_filter') }}">
                <input type="hidden" name="source_filter" value="{{ request('source_filter') }}">
                <input type="hidden" name="search" value="{{ request('search') }}">

                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label text-[11px] font-semibold text-slate-500">Start Date</label>
                            <input type="date" name="start_date" class="form-input text-xs py-1.5" value="{{ request('created_from', request('date_from')) }}">
                        </div>
                        <div>
                            <label class="form-label text-[11px] font-semibold text-slate-500">End Date</label>
                            <input type="date" name="end_date" class="form-input text-xs py-1.5" value="{{ request('created_to', request('date_to')) }}">
                        </div>
                    </div>
                    <div>
                        <label class="form-label text-[11px] font-semibold text-slate-500">Filter Staff Member</label>
                        <select name="member_id" class="form-input text-xs py-1.5">
                            <option value="All">All CRM Members</option>
                            @foreach($crmUsers as $user)
                                <option value="{{ $user->id }}" {{ request('assigned_to_filter') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label text-[11px] font-semibold text-slate-500">Format</label>
                        <div class="grid grid-cols-2 gap-2 mt-1">
                            <label class="flex items-center gap-2 p-2 bg-slate-50 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-100 text-xs">
                                <input type="radio" name="format" value="pdf" checked class="text-indigo-600">
                                <span>📄 PDF</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 bg-slate-50 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-100 text-xs">
                                <input type="radio" name="format" value="csv" class="text-indigo-600">
                                <span>📊 CSV</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 justify-end pt-3 border-t border-slate-100 mt-4">
                    <button type="button" @click="showCustom = false" class="btn btn-secondary text-xs py-1.5">Cancel</button>
                    <button type="submit" class="btn btn-primary text-xs py-1.5 px-4">Download Custom Export</button>
                </div>
            </form>
        </div>
    </div>
</div>
