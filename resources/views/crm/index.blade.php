@extends('layouts.app')
@section('title', 'CRM — Customers')
@section('page_title', 'Customer Database')
@section('meta_description', 'All customers across CRM Website, eBay, and Logistics, deduplicated and searchable.')
@section('hide_back', true)

@section('content')
<style>
  @keyframes loading-bar-animation {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
  }
  .animate-loading-bar {
    animation: loading-bar-animation 1.5s ease infinite;
    background-size: 200% 100%;
  }
</style>

<div id="customer-directory-page" class="animate-fade-in">

  {{-- ── Stats Row ─────────────────────────────────────────────────────────── --}}
  <div class="mobile-scroll-x lg:grid lg:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
    <div class="relative overflow-hidden bg-white border border-slate-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-300 flex items-center gap-4 flex-shrink-0 w-[280px] lg:w-auto group">
      <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-indigo-50/50 rounded-full group-hover:scale-110 transition-transform duration-300"></div>
      <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-indigo-50 text-indigo-600 transition-colors duration-300 group-hover:bg-indigo-600 group-hover:text-white shadow-sm shadow-indigo-100 z-10">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
      </div>
      <div class="z-10">
        <div class="text-2xl font-bold text-slate-800 tracking-tight" id="total-unique-stats">{{ $totalUnique }}</div>
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Customers</div>
      </div>
    </div>

    <div class="relative overflow-hidden bg-white border border-slate-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-300 flex items-center gap-4 flex-shrink-0 w-[280px] lg:w-auto group">
      <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-amber-50/50 rounded-full group-hover:scale-110 transition-transform duration-300"></div>
      <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-amber-50 text-amber-600 transition-colors duration-300 group-hover:bg-amber-600 group-hover:text-white shadow-sm shadow-amber-100 z-10">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/></svg>
      </div>
      <div class="z-10">
        <div class="text-2xl font-bold text-slate-800 tracking-tight" id="total-revenue-stats">${{ number_format($stats['total_value'], 0) }}</div>
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Revenue</div>
      </div>
    </div>
  </div>

  {{-- ── Status Filter + Actions ───────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="flex gap-2 flex-wrap">
      @foreach(['All', 'Technical issues', 'Logistic issues', 'Negative feedback'] as $val)
      @php
        $pillUrl = $val === 'All'
          ? route('crm.customers.index', ['clear_filters' => 1])
          : route('crm.customers.index', array_merge(request()->query(), ['status_filter' => $val]));
        
        $isActive = ($statusFilter === $val);
        $tabClass = 'status-filter-tab px-3.5 py-2 text-xs font-semibold rounded-xl border transition-all duration-150 ';
        if ($val === 'All') {
            $tabClass .= $isActive ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm shadow-indigo-100' : 'bg-white text-slate-600 border-slate-200/60 hover:bg-slate-50';
        } elseif ($val === 'Technical issues') {
            $tabClass .= $isActive ? 'bg-violet-600 text-white border-violet-600 shadow-sm shadow-violet-100' : 'bg-white text-violet-600 border-violet-100 hover:bg-violet-50/50';
        } elseif ($val === 'Logistic issues') {
            $tabClass .= $isActive ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-100' : 'bg-white text-emerald-600 border-emerald-100 hover:bg-emerald-50/50';
        } elseif ($val === 'Negative feedback') {
            $tabClass .= $isActive ? 'bg-rose-600 text-white border-rose-600 shadow-sm shadow-rose-100' : 'bg-white text-rose-600 border-rose-100 hover:bg-rose-50/50';
        }
      @endphp
      <a href="{{ $pillUrl }}"
         class="{{ $tabClass }}"
         data-value="{{ $val }}">
        {{ $val }}
      </a>
      @endforeach
    </div>
    <div class="flex gap-2 items-center flex-wrap">
      @include('crm.partials.report_export_modal', ['type' => 'customers', 'btnClass' => 'btn btn-secondary py-2'])
    </div>
  </div>

  {{-- ── Search + Date Filters ─────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden bg-white border border-slate-100 rounded-2xl p-6 shadow-sm mb-5">
    <form id="customer-filter-form" method="GET" action="{{ route('crm.customers.index') }}" autocomplete="off" class="flex flex-wrap items-end gap-x-6 gap-y-4">
      <input type="hidden" name="status_filter" value="{{ $statusFilter }}">
      <input type="hidden" name="source_filter" value="{{ $sourceFilter }}">

      <div class="min-w-[220px] relative">
        <label class="form-label text-xs font-semibold text-slate-500 block mb-1">Search</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          </span>
          <input type="search" name="search" value="{{ request('search') }}" placeholder="Name, email, phone…" autocomplete="off" class="form-input text-sm py-2 pl-9 pr-3 w-full bg-slate-50 border border-slate-200/80 text-slate-700 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all duration-150">
        </div>
      </div>

      <div class="min-w-[160px] relative">
        <label class="form-label text-xs font-semibold text-slate-500 block mb-1">Sort by</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 12h18M3 17h18"/></svg>
          </span>
          <select name="sort_by" autocomplete="off" class="form-input text-sm py-2 pl-9 pr-8 w-full bg-slate-50 border border-slate-200/80 text-slate-700 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all duration-150 appearance-none">
            <option value="created" {{ $sortBy === 'created' ? 'selected' : '' }}>Newest Created</option>
            <option value="purchase" {{ $sortBy === 'purchase' ? 'selected' : '' }}>Newest Purchase</option>
          </select>
        </div>
      </div>

      <div class="min-w-[180px] relative">
        <label class="form-label text-xs font-semibold text-slate-500 block mb-1">Assigned Staff</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          </span>
          <select name="assigned_to_filter" autocomplete="off" class="form-input text-sm py-2 pl-9 pr-8 w-full bg-slate-50 border border-slate-200/80 text-slate-700 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all duration-150 appearance-none">
            <option value="">Anyone</option>
            @foreach($assignableStaff as $staff)
            <option value="{{ $staff->id }}" {{ (string) $assignedToFilter === (string) $staff->id ? 'selected' : '' }}>{{ $staff->name }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="flex items-center gap-2 pb-2.5">
        <input type="checkbox" name="new_only" id="new_only" value="1" {{ $newOnly ? 'checked' : '' }} autocomplete="off" class="form-checkbox h-4 w-4 text-indigo-600 border-slate-300 rounded-lg focus:ring-indigo-500/20 transition-all duration-150">
        <label for="new_only" class="text-sm font-medium text-slate-600 select-none cursor-pointer">New Customers (last 7 days)</label>
      </div>

      <div class="flex items-end gap-2 pl-4 border-l border-slate-100">
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5 flex items-center gap-1">
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Created Date
          </p>
          <div class="flex items-center gap-2">
            <input type="date" name="created_from" value="{{ $createdFrom }}" autocomplete="off" class="form-input text-sm py-2 px-2.5 w-36 bg-slate-50 border border-slate-200/80 text-slate-700 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all duration-150" title="From">
            <span class="text-xs text-slate-400">to</span>
            <input type="date" name="created_to" value="{{ $createdTo }}" autocomplete="off" class="form-input text-sm py-2 px-2.5 w-36 bg-slate-50 border border-slate-200/80 text-slate-700 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all duration-150" title="To">
          </div>
        </div>
      </div>

      <div class="flex items-end gap-2 pl-4 border-l border-slate-100">
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5 flex items-center gap-1">
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Purchase Date
          </p>
          <div class="flex items-center gap-2">
            <input type="date" name="date_from" value="{{ $dateFrom }}" autocomplete="off" class="form-input text-sm py-2 px-2.5 w-36 bg-slate-50 border border-slate-200/80 text-slate-700 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all duration-150" title="From">
            <span class="text-xs text-slate-400">to</span>
            <input type="date" name="date_to" value="{{ $dateTo }}" autocomplete="off" class="form-input text-sm py-2 px-2.5 w-36 bg-slate-50 border border-slate-200/80 text-slate-700 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all duration-150" title="To">
          </div>
        </div>
      </div>

      <div class="flex gap-2">
        @if($dateFrom || $dateTo || $createdFrom || $createdTo || request('search') || $newOnly || $assignedToFilter || $sortBy !== 'created')
        <a href="{{ route('crm.customers.index', ['clear_filters' => 1]) }}" class="btn btn-secondary text-sm">Clear Filters</a>
        @endif
      </div>
    </form>
  </div>

  {{-- ── Source Tabs ── --}}
  <div class="flex items-center gap-3 mb-6 bg-slate-100/60 p-1 rounded-2xl w-fit border border-slate-200/30 backdrop-blur-sm shadow-sm shadow-slate-100/50">
    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider pl-3">Source:</span>
    <div class="flex gap-1">
      @foreach(['All', 'eBay', 'Logistics', 'Website'] as $val)
      @php
        $isActive = ($sourceFilter === $val);
      @endphp
      <a href="{{ route('crm.customers.index', array_merge(request()->query(), ['source_filter' => $val])) }}"
         class="source-filter-tab px-4 py-1.5 text-xs font-semibold rounded-xl transition-all duration-200 {{ $isActive ? 'bg-white text-slate-800 shadow-sm border border-slate-200/40' : 'text-slate-500 hover:text-slate-800 hover:bg-white/40 border border-transparent' }}"
         data-value="{{ $val }}">
        {{ $val }}
      </a>
      @endforeach
    </div>
  </div>

  <p class="text-xs text-slate-400 mb-3">{{ $totalUnique }} unique customer(s) across CRM, eBay, and Logistics (deduplicated by email/phone).</p>

  {{-- ── Customer Table ────────────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden bg-white border border-slate-100 rounded-2xl shadow-sm">
    {{-- Loading progress bar --}}
    <div id="table-loading-bar" class="absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-500 bg-[length:200%_auto] animate-loading-bar hidden z-20"></div>

    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/20">
      <p class="text-sm text-slate-500" id="filter-stats">
        Showing <strong>{{ $customers->count() }}</strong>
        of <strong>{{ $customers->total() }}</strong> filtered customer(s)
      </p>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm" id="customer-table">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left border-r border-slate-100">Name</th>
            <th class="px-4 py-3 text-left border-r border-slate-100">Contact</th>
            <th class="px-4 py-3 text-left border-r border-slate-100">Source</th>
            <th class="px-4 py-3 text-left border-r border-slate-100">Status</th>
            <th class="px-4 py-3 text-left border-r border-slate-100">Created</th>
            <th class="px-4 py-3 text-left border-r border-slate-100">Purchase Date</th>
            <th class="px-4 py-3 text-left border-r border-slate-100">Handler</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody id="customer-table-body" class="divide-y divide-slate-100 transition-opacity duration-200">
          @include('crm.partials.customer_rows')
        </tbody>
      </table>
    </div>
    <div id="pagination-container">
      @if($customers->hasPages())
      <div class="px-6 py-4 border-t border-slate-100">
        {{ $customers->links() }}
      </div>
      @endif
    </div>
  </div>

</div>
@push('scripts')
<script>
document.addEventListener('turbo:load', function () {
    const pageContainer = document.querySelector('#customer-directory-page');
    if (!pageContainer) return;

    const form = pageContainer.querySelector('#customer-filter-form');
    if (!form) return;

    const tableBody = pageContainer.querySelector('#customer-table-body');
    const paginationContainer = pageContainer.querySelector('#pagination-container');
    const filterStats = pageContainer.querySelector('#filter-stats');
    const totalUniqueStats = pageContainer.querySelector('#total-unique-stats');
    const totalRevenueStats = pageContainer.querySelector('#total-revenue-stats');
    const loadingBar = pageContainer.querySelector('#table-loading-bar');

    // Debounce function to prevent hammering the server on search input keyup
    let debounceTimer;
    function debounce(func, delay) {
        return function () {
            const context = this;
            const args = arguments;
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => func.apply(context, args), delay);
        };
    }

    // Function to reload data via AJAX
    function loadData(page = 1) {
        // Show loading state
        tableBody.classList.add('opacity-40');
        if (loadingBar) {
            loadingBar.classList.remove('hidden');
        }

        const formData = new FormData(form);
        formData.set('page', page);
        
        // Build query string
        const params = new URLSearchParams(formData).toString();
        const url = `${form.action}?${params}`;

        // Update browser URL history state silently
        window.history.pushState({}, '', url);

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            // Update table body and pagination
            tableBody.innerHTML = data.rows;
            if (paginationContainer) {
                paginationContainer.innerHTML = data.pagination;
            }
            if (filterStats) {
                filterStats.innerHTML = data.stats;
            }
            if (totalUniqueStats) {
                totalUniqueStats.textContent = data.totalUnique;
            }
            if (totalRevenueStats) {
                totalRevenueStats.textContent = '$' + data.totalRevenue;
            }
            // Update active styles on tabs
            updateTabActiveStyles();
        })
        .catch(error => {
            console.error('Error reloading customer data:', error);
        })
        .finally(() => {
            tableBody.classList.remove('opacity-40');
            if (loadingBar) {
                loadingBar.classList.add('hidden');
            }
        });
    }

    // Helper to update active styles on custom tabs/buttons
    function updateTabActiveStyles() {
        const activeStatus = form.querySelector('input[name="status_filter"]').value;
        pageContainer.querySelectorAll('.status-filter-tab').forEach(tab => {
            const val = tab.getAttribute('data-value');
            const isActive = (val === activeStatus);
            if (val === 'All') {
                tab.className = `status-filter-tab px-3.5 py-2 text-xs font-semibold rounded-xl border transition-all duration-150 ${isActive ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm shadow-indigo-100' : 'bg-white text-slate-600 border-slate-200/60 hover:bg-slate-50'}`;
            } else if (val === 'Technical issues') {
                tab.className = `status-filter-tab px-3.5 py-2 text-xs font-semibold rounded-xl border transition-all duration-150 ${isActive ? 'bg-violet-600 text-white border-violet-600 shadow-sm shadow-violet-100' : 'bg-white text-violet-600 border-violet-100 hover:bg-violet-50/50'}`;
            } else if (val === 'Logistic issues') {
                tab.className = `status-filter-tab px-3.5 py-2 text-xs font-semibold rounded-xl border transition-all duration-150 ${isActive ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-100' : 'bg-white text-emerald-600 border-emerald-100 hover:bg-emerald-50/50'}`;
            } else if (val === 'Negative feedback') {
                tab.className = `status-filter-tab px-3.5 py-2 text-xs font-semibold rounded-xl border transition-all duration-150 ${isActive ? 'bg-rose-600 text-white border-rose-600 shadow-sm shadow-rose-100' : 'bg-white text-rose-600 border-rose-100 hover:bg-rose-50/50'}`;
            }
        });

        const activeSource = form.querySelector('input[name="source_filter"]').value;
        pageContainer.querySelectorAll('.source-filter-tab').forEach(tab => {
            const val = tab.getAttribute('data-value');
            if (val === activeSource) {
                tab.className = 'source-filter-tab px-4 py-1.5 text-xs font-semibold rounded-xl transition-all duration-200 bg-white text-slate-800 shadow-sm border border-slate-200/40';
            } else {
                tab.className = 'source-filter-tab px-4 py-1.5 text-xs font-semibold rounded-xl transition-all duration-200 text-slate-500 hover:text-slate-800 hover:bg-white/40 border border-transparent';
            }
        });
    }

    // Event listeners
    // 1. Search text input (key up with debounce)
    const searchInput = form.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function () {
            loadData(1);
        }, 300));
    }

    // 2. Select changes, checkboxes and dates
    form.querySelectorAll('select, input[type="checkbox"], input[type="date"]').forEach(input => {
        input.addEventListener('change', function () {
            loadData(1);
        });
    });

    // 3. Prevent form submit default behavior
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadData(1);
    });

    // 4. Status tab clicks
    pageContainer.addEventListener('click', function (e) {
        const statusTab = e.target.closest('.status-filter-tab');
        if (statusTab) {
            e.preventDefault();
            form.querySelector('input[name="status_filter"]').value = statusTab.getAttribute('data-value');
            loadData(1);
            return;
        }

        const sourceTab = e.target.closest('.source-filter-tab');
        if (sourceTab) {
            e.preventDefault();
            form.querySelector('input[name="source_filter"]').value = sourceTab.getAttribute('data-value');
            loadData(1);
            return;
        }

        // Pagination links
        const pagLink = e.target.closest('#pagination-container a');
        if (pagLink) {
            e.preventDefault();
            const urlObj = new URL(pagLink.href);
            const page = urlObj.searchParams.get('page') || 1;
            loadData(page);
            return;
        }
    });

    // 5. Pusher Real-Time Sync
    if (window.kiuqGetPusherClient) {
        const pusher = window.kiuqGetPusherClient();
        if (pusher) {
            const channel = pusher.subscribe('private-tech-support');
            channel.bind('TechSupportCaseStatusUpdated', (data) => {
                // If another user updated it, silently reload the current page data
                const currentUserId = document.querySelector('meta[name="kiuq-user-id"]')?.content;
                if (parseInt(data.updaterId) !== parseInt(currentUserId)) {
                    // Extract current page from pagination or default to 1
                    const activePageLink = paginationContainer?.querySelector('.pagination .active span, .pagination .active a');
                    const currentPage = activePageLink ? parseInt(activePageLink.textContent) : 1;
                    loadData(currentPage || 1);
                }
            });
        }
    }
});
</script>
@endpush

@endsection
