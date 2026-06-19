@extends('layouts.app')
@section('title', 'All Mails')
@section('page_title', 'All Mails')

@section('content')
{{-- Dark mode overrides for the email index page --}}
<style>
  [data-theme="dark"] .email-card-dark { background-color: #0f172a !important; border-color: #1e293b !important; }
  [data-theme="dark"] .email-filter-text { color: #cbd5e1 !important; }
  [data-theme="dark"] .email-filter-input { background-color: #1e293b !important; color: #ffffff !important; border-color: #334155 !important; }
  [data-theme="dark"] .email-empty-title { color: #ffffff !important; }
  [data-theme="dark"] .email-empty-sub { color: #94a3b8 !important; }
</style>
<div class="animate-fade-in space-y-4" x-data="emailList()">

  {{-- Stats + Actions Row --}}
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="mobile-scroll-x md:flex md:flex-wrap items-center gap-4">
      <div class="stat-card !py-3 !px-4 flex-row gap-3 flex-shrink-0 w-[180px] md:w-auto">
        <span class="text-2xl">📬</span>
        <div>
          <div class="stat-value text-lg">{{ $stats['total'] }}</div>
          <div class="stat-label">Total</div>
        </div>
      </div>
      <div class="stat-card !py-3 !px-4 flex-row gap-3 flex-shrink-0 w-[180px] md:w-auto">
        <span class="text-2xl">🔴</span>
        <div>
          <div class="stat-value text-lg text-rose-600">{{ $stats['unread'] }}</div>
          <div class="stat-label">Unread</div>
        </div>
      </div>
    </div>
    <a href="{{ route('admin.emails.accounts') }}" class="btn btn-secondary text-sm">⚙️ Manage Accounts</a>
  </div>

  {{-- Filters --}}
  <div class="card email-card-dark p-3">
    <form method="GET" action="{{ route('admin.emails.index') }}" class="flex flex-wrap items-center gap-3">
      <div class="relative flex-1 min-w-[200px] max-w-xs">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search subject, sender…"
               class="form-input email-filter-input pl-9 py-2 text-sm" id="email-search">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
      </div>
      <select name="account" class="form-input email-filter-input py-2 text-sm w-auto">
        <option value="">All Accounts</option>
        @foreach($accounts as $acct)
          <option value="{{ $acct->id }}" @selected(request('account') == $acct->id)>{{ $acct->name }}</option>
        @endforeach
      </select>
      <label class="email-filter-text flex items-center gap-1.5 text-sm cursor-pointer" style="color: #475569;">
        <input type="checkbox" name="unread" value="1" @checked(request('unread')) class="rounded border-slate-300">
        Unread only
      </label>
      <button type="submit" class="btn btn-primary text-sm py-2">Filter</button>
      <a href="{{ route('admin.emails.index') }}" class="btn btn-secondary text-sm py-2">Clear</a>
    </form>
  </div>

  {{-- Emails List --}}
  <div class="card email-card-dark p-0 overflow-hidden">
    
    {{-- Normal Header (when select mode is OFF) --}}
    <div x-show="!showCheckboxes" class="h-12 border-b border-slate-100 flex items-center justify-end px-4" style="border-color: #e2e8f0;">
      <button @click.prevent="showCheckboxes = true" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition-colors">
        ☑️ Select Emails
      </button>
    </div>

    {{-- Select Mode Header (when select mode is ON but nothing selected) --}}
    <div x-cloak x-show="showCheckboxes && selected.length === 0" class="h-12 border-b border-slate-100 flex items-center justify-between px-4" style="border-color: #e2e8f0;">
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" x-model="selectAll" @change="toggleAll" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
        <span class="text-sm font-medium" style="color: #64748b;">Select All</span>
      </label>
      <button @click.prevent="cancelSelection" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</button>
    </div>

    {{-- Bulk Actions Toolbar --}}
    <div x-cloak x-show="showCheckboxes && selected.length > 0" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="h-12 bg-indigo-50 border-b border-indigo-100 flex items-center justify-between px-4" style="background-color: #eef2ff;">
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" x-model="selectAll" @change="toggleAll" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
          <span class="text-sm font-medium text-indigo-900"><span x-text="selected.length"></span> selected</span>
        </label>
      </div>
      <div class="flex items-center gap-4">
        <button @click="confirmDelete('bulk')" class="btn btn-danger text-xs py-1.5 px-3">
          🗑 Delete Selected
        </button>
        <button @click.prevent="cancelSelection" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</button>
      </div>
    </div>

    <div id="email-list-container">
      @forelse($messages as $msg)
        @include('admin.emails.partials.email-row', ['msg' => $msg])
      @empty
      <div class="text-center py-20">
        <span class="text-5xl mb-3 block">📭</span>
      <h3 class="email-empty-title font-display font-bold text-lg mb-2" style="color: #334155;">No emails yet</h3>
        <p class="email-empty-sub text-sm mb-4" style="color: #94a3b8;">Connect an email account to start syncing messages.</p>
        <a href="{{ route('admin.emails.accounts') }}" class="btn btn-primary">+ Add Email Account</a>
      </div>
      @endforelse
    </div>

  {{-- Pagination --}}
  @if($messages->hasPages())
    <div class="mt-4">{{ $messages->links() }}</div>
  @endif

</div>
    {{-- Delete Confirmation Modal --}}
    <div x-cloak x-show="showDeleteModal" class="relative z-[100]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div x-show="showDeleteModal" 
           x-transition:enter="ease-out duration-300" 
           x-transition:enter-start="opacity-0" 
           x-transition:enter-end="opacity-100" 
           x-transition:leave="ease-in duration-200" 
           x-transition:leave-start="opacity-100" 
           x-transition:leave-end="opacity-0" 
           class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>

      <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <div x-show="showDeleteModal" 
               @click.away="showDeleteModal = false"
               x-transition:enter="ease-out duration-300" 
               x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
               x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
               x-transition:leave="ease-in duration-200" 
               x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
               x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
               class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
              <div class="sm:flex sm:items-start">
                <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                  <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                </div>
                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                  <h3 class="text-lg font-semibold leading-6 text-slate-900" id="modal-title">Delete Email(s)</h3>
                  <div class="mt-2">
                    <p class="text-sm text-slate-500">
                      Are you sure you want to delete <span x-show="deleteType === 'bulk'" x-text="selected.length + ' emails'"></span><span x-show="deleteType !== 'bulk'">this email</span>? This action cannot be undone.
                    </p>
                  </div>
                </div>
              </div>
            </div>
            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
              <button type="button" @click="executeDelete" class="btn btn-danger w-full justify-center sm:ml-3 sm:w-auto">Remove</button>
              <button type="button" @click="showDeleteModal = false" class="btn btn-secondary mt-3 w-full justify-center sm:mt-0 sm:w-auto">Cancel</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    
</div>

{{-- Toast Notification Container --}}
<div id="live-toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-2"></div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('emailList', () => ({
        selected: [],
        selectAll: false,
        showCheckboxes: false,
        showDeleteModal: false,
        deleteType: null, // 'bulk' or email ID
        allIds: [
            @foreach($messages as $msg) {{ $msg->id }}, @endforeach
        ],
        toggleAll() {
            if (this.selectAll) {
                this.selected = [...this.allIds];
            } else {
                this.selected = [];
            }
        },
        cancelSelection() {
            this.showCheckboxes = false;
            this.selected = [];
            this.selectAll = false;
        },
        confirmDelete(type) {
            this.deleteType = type;
            this.showDeleteModal = true;
        },
        async executeDelete() {
            if (this.deleteType === 'bulk') {
                await fetch('{{ route("admin.emails.bulk-destroy") }}', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ids: this.selected })
                });
                this.selected.forEach(id => {
                    const el = document.getElementById('email-row-' + id);
                    if(el) el.remove();
                });
                this.selected = [];
                this.selectAll = false;
            } else {
                await fetch('/admin/emails/' + this.deleteType, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    }
                });
                const el = document.getElementById('email-row-' + this.deleteType);
                if(el) el.remove();
                this.selected = this.selected.filter(id => id !== this.deleteType);
            }
            this.showDeleteModal = false;
        }
    }));
});

document.addEventListener('DOMContentLoaded', function() {
    let lastCheckedAt = "{{ now()->toIso8601String() }}";
    
    // Play a gentle notification sound
    const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
    
    function showToast(sender, subject) {
        const container = document.getElementById('live-toast-container');
        const toast = document.createElement('div');
        toast.className = 'bg-slate-900 text-white rounded-xl shadow-xl p-4 w-80 transform transition-all duration-500 translate-y-10 opacity-0 flex items-start gap-3';
        toast.innerHTML = `
            <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold truncate">New from ${sender}</p>
                <p class="text-xs text-slate-300 truncate">${subject}</p>
            </div>
        `;
        container.appendChild(toast);
        
        // Animate in
        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-10', 'opacity-0');
        });
        
        // Remove after 5 seconds
        setTimeout(() => {
            toast.classList.add('translate-x-full', 'opacity-0');
            setTimeout(() => toast.remove(), 500);
        }, 5000);
    }

    // Poll server every 10 seconds
    setInterval(async () => {
        try {
            const url = new URL("{{ route('admin.emails.poll') }}");
            url.searchParams.append('since', lastCheckedAt);
            
            // Pass current filters so we don't inject irrelevant emails
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('account')) urlParams.append('account', urlParams.get('account'));
            if (urlParams.has('folder')) urlParams.append('folder', urlParams.get('folder'));
            if (urlParams.has('unread')) urlParams.append('unread', urlParams.get('unread'));
            if (urlParams.has('search')) urlParams.append('search', urlParams.get('search'));

            const response = await fetch(url.toString());
            const data = await response.json();
            
            if (data.status === 'ok' && data.emails.length > 0) {
                const list = document.getElementById('email-list-container');
                
                // Remove the "No emails yet" empty state if it exists
                const emptyState = list.querySelector('.text-center.py-20');
                if (emptyState) emptyState.remove();

                // Inject new emails
                data.emails.forEach(email => {
                    list.insertAdjacentHTML('afterbegin', email.html);
                    
                    // Show notification
                    showToast(email.sender, email.subject);
                });
                
                // Play sound once per batch
                audio.play().catch(e => console.log('Audio autoplay blocked'));
            }
            
            lastCheckedAt = data.timestamp;
        } catch (error) {
            console.error('Error polling for new emails:', error);
        }
    }, 10000);
});
</script>
@endsection
