@extends('layouts.app')
@section('title', 'Security Management')
@section('page_title', 'Security Command Center')

@section('content')
<div class="space-y-6 animate-fade-in pb-28 md:pb-8" x-data="{ activeTab: new URLSearchParams(location.search).has('activity_page') ? 'activity' : (new URLSearchParams(location.search).has('attempt_page') ? 'attempts' : (new URLSearchParams(location.search).has('user_page') ? 'users' : 'ips')) }">

<style>
/* ── Mobile Responsive Table ── */
@media (max-width: 768px) {
  .responsive-table thead { display: none; }
  .responsive-table tbody, .responsive-table tr, .responsive-table td { display: block; width: 100%; }
  .responsive-table tr {
    margin-bottom: 1rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    padding: 0.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
  }
  .responsive-table td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem !important;
    border-bottom: 1px solid #f8fafc;
    text-align: right;
  }
  .responsive-table td:last-child { border-bottom: none; }
  .responsive-table td::before {
    content: attr(data-label);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.65rem;
    letter-spacing: 0.05em;
    color: #94a3b8;
    margin-right: 1rem;
    text-align: left;
  }
  .responsive-table td > div { text-align: right; justify-content: flex-end; }
  /* User cell gets special treatment */
  .responsive-table td.user-cell {
    flex-direction: column;
    align-items: flex-start;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 1rem !important;
  }
  .responsive-table td.user-cell::before { display: none; }
  .responsive-table td.user-cell > div { text-align: left; justify-content: flex-start; width: 100%; }
}
/* Ensure tabs can scroll horizontally without wrapping */
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

  {{-- Stats Grid --}}
  <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
    <!-- Stat 1 -->
    <div class="card p-5 flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600 font-semibold text-lg">
        🔒
      </div>
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Attempts (24h)</p>
        <h3 class="text-2xl font-bold text-slate-800 mt-1">{{ $stats['total_attempts_24h'] }}</h3>
      </div>
    </div>

    <!-- Stat 2 -->
    <div class="card p-5 flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600 font-semibold text-lg">
        ⚠️
      </div>
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Failed Attempts (24h)</p>
        <h3 class="text-2xl font-bold text-rose-600 mt-1">{{ $stats['failed_attempts_24h'] }}</h3>
      </div>
    </div>

    <!-- Stat 3 -->
    <div class="card p-5 flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600 font-semibold text-lg">
        👤
      </div>
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Blocked Users</p>
        <h3 class="text-2xl font-bold text-amber-600 mt-1">{{ $stats['blocked_users_count'] }}</h3>
      </div>
    </div>

    <!-- Stat 4 -->
    <div class="card p-5 flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center text-red-600 font-semibold text-lg">
        🚫
      </div>
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Banned IP Addresses</p>
        <h3 class="text-2xl font-bold text-red-600 mt-1">{{ $stats['banned_ips_count'] }}</h3>
      </div>
    </div>
  </div>

  {{-- Tab Navigation --}}
  <div class="flex border-b border-slate-200 gap-6 overflow-x-auto whitespace-nowrap hide-scrollbar">
    <button @click="activeTab = 'ips'"
            :class="activeTab === 'ips' ? 'border-indigo-600 text-indigo-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700'"
            class="py-3 px-1 border-b-2 text-sm transition-all focus:outline-none">
      🚫 Banned IPs ({{ $bannedIps->total() }})
    </button>
    <button @click="activeTab = 'users'"
            :class="activeTab === 'users' ? 'border-indigo-600 text-indigo-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700'"
            class="py-3 px-1 border-b-2 text-sm transition-all focus:outline-none">
      👤 Blocked Users ({{ $blockedUsers->total() }})
    </button>
    <button @click="activeTab = 'attempts'"
            :class="activeTab === 'attempts' ? 'border-indigo-600 text-indigo-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700'"
            class="py-3 px-1 border-b-2 text-sm transition-all focus:outline-none">
      🔍 Failed Attempts Log ({{ $failedAttempts->total() }})
    </button>
    <button @click="activeTab = 'activity'"
            :class="activeTab === 'activity' ? 'border-indigo-600 text-indigo-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700'"
            class="py-3 px-1 border-b-2 text-sm transition-all focus:outline-none">
      📋 Security Activity Log ({{ $activityLogs->total() }})
    </button>
    <button @click="activeTab = 'settings'"
            :class="activeTab === 'settings' ? 'border-indigo-600 text-indigo-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700'"
            class="py-3 px-1 border-b-2 text-sm transition-all focus:outline-none">
      ⚙️ Settings
    </button>
  </div>

  {{-- Tab Contents --}}
  <div>
    
    {{-- TAB 1: BANNED IPS --}}
    <div x-show="activeTab === 'ips'" class="space-y-6">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Ban Form -->
        <div class="card p-6 h-fit">
          <h3 class="font-display font-bold text-slate-800 text-base mb-4">Ban New IP Address</h3>
          <form method="POST" action="{{ route('admin.security.ban-ip') }}" class="space-y-4">
            @csrf
            <div>
              <label class="form-label text-xs">IP Address</label>
              <input type="text" name="ip_address" placeholder="e.g. 192.168.1.100" class="form-input text-sm" required>
            </div>
            <div>
              <label class="form-label text-xs">Reason for Ban</label>
              <input type="text" name="reason" placeholder="Suspicious API requests" class="form-input text-sm">
            </div>
            <div>
              <label class="form-label text-xs">Ban Duration</label>
              <select name="duration" class="form-input text-sm" required>
                <option value="permanent">Permanent Ban</option>
                <option value="1h">1 Hour</option>
                <option value="24h">24 Hours</option>
                <option value="7d">7 Days</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary w-full py-2 text-sm mt-2">🚫 Apply IP Ban</button>
          </form>
        </div>

        <!-- Banned IPs List -->
        <div class="card lg:col-span-2 overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
            <h3 class="font-display font-bold text-slate-800 text-sm">Active IP Restrictions</h3>
            <span class="text-xs text-slate-400">{{ $bannedIps->total() }} restricted IPs</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse responsive-table">
              <thead>
                <tr class="border-b border-slate-100 text-[10px] font-semibold text-slate-400 uppercase bg-slate-50/50">
                  <th class="px-6 py-3">IP Address</th>
                  <th class="px-6 py-3">Reason</th>
                  <th class="px-6 py-3">Banned By</th>
                  <th class="px-6 py-3">Expires At</th>
                  <th class="px-6 py-3 text-right">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50 text-slate-600 text-xs">
                @forelse($bannedIps as $ban)
                  <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-3 font-mono font-semibold text-slate-700" data-label="IP Address">{{ $ban->ip_address }}</td>
                    <td class="px-6 py-3" data-label="Reason">{{ $ban->reason ?? 'N/A' }}</td>
                    <td class="px-6 py-3" data-label="Banned By">{{ $ban->bannedBy?->name ?? 'System' }}</td>
                    <td class="px-6 py-3" data-label="Expires At">
                      @if($ban->expires_at)
                        <span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-600 font-medium">
                          {{ $ban->expires_at->diffForHumans() }}
                        </span>
                      @else
                        <span class="px-1.5 py-0.5 rounded bg-red-50 text-red-600 font-medium">Permanent</span>
                      @endif
                    </td>
                    <td class="px-6 py-3 text-right" data-label="Action">
                      <form method="POST" action="{{ route('admin.security.unban-ip', $ban) }}"
                            data-confirm-title="Lift IP ban?"
                            data-confirm="Lift ban for {{ $ban->ip_address }}?"
                            data-confirm-text="Unban"
                            data-confirm-tone="warning">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-indigo-600 hover:text-indigo-900 font-semibold">Unban</button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center py-8 text-slate-400">No banned IP addresses found.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          @if($bannedIps->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/30">
              {{ $bannedIps->links() }}
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- TAB 2: BLOCKED USERS --}}
    <div x-show="activeTab === 'users'" class="card overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
        <h3 class="font-display font-bold text-slate-800 text-sm">Suspended or Blocked Accounts</h3>
        <span class="text-xs text-slate-400">{{ $blockedUsers->total() }} users</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse responsive-table">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] font-semibold text-slate-400 uppercase bg-slate-50/50">
              <th class="px-6 py-3">User</th>
              <th class="px-6 py-3">Failed Attempts</th>
              <th class="px-6 py-3">Last Login IP</th>
              <th class="px-6 py-3">Blocked Until</th>
              <th class="px-6 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 text-slate-600 text-xs">
            @forelse($blockedUsers as $u)
              <tr class="hover:bg-slate-50/50 transition-colors">
                <td class="px-6 py-3 flex items-center gap-3 user-cell" data-label="User">
                  <img src="{{ $u->avatar_url }}" alt="{{ $u->name }}" class="w-7 h-7 rounded-full object-cover">
                  <div>
                    <p class="font-semibold text-slate-700">{{ $u->name }}</p>
                    <p class="text-[10px] text-slate-400">{{ $u->email }}</p>
                  </div>
                </td>
                <td class="px-6 py-3 font-semibold text-rose-600" data-label="Failed Attempts">{{ $u->failed_login_count }}</td>
                <td class="px-6 py-3 font-mono" data-label="Last Login IP">{{ $u->last_login_ip ?? 'N/A' }}</td>
                <td class="px-6 py-3" data-label="Blocked Until">
                  @if($u->isLockedOut())
                    <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-600 font-semibold text-[10px]">
                      Locked until {{ $u->locked_until->format('H:i') }} ({{ $u->locked_until->diffForHumans() }})
                    </span>
                  @else
                    <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-600 font-semibold text-[10px]">
                      Max Failed Logins Reach
                    </span>
                  @endif
                </td>
                <td class="px-6 py-3 text-right" data-label="Action">
                  <form method="POST" action="{{ route('admin.security.unblock-user', $u) }}"
                        data-confirm-title="Unblock user?"
                        data-confirm="Unblock user {{ $u->name }}?"
                        data-confirm-text="Unblock"
                        data-confirm-tone="warning">
                    @csrf
                    <button type="submit" class="btn btn-primary py-1 px-3 text-[11px]">🔑 Unblock Account</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center py-8 text-slate-400">No blocked user accounts found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($blockedUsers->hasPages())
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/30">
          {{ $blockedUsers->links() }}
        </div>
      @endif
    </div>

    {{-- TAB 3: FAILED ATTEMPTS --}}
    <div x-show="activeTab === 'attempts'" class="card overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
        <h3 class="font-display font-bold text-slate-800 text-sm">Failed Authentication Log</h3>
        <span class="text-xs text-slate-400">{{ $failedAttempts->total() }} raw logs</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse responsive-table">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] font-semibold text-slate-400 uppercase bg-slate-50/50">
              <th class="px-6 py-3">Email Attempted</th>
              <th class="px-6 py-3">IP Address</th>
              <th class="px-6 py-3">User Agent</th>
              <th class="px-6 py-3">Timestamp</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 text-slate-600 text-xs">
            @forelse($failedAttempts as $attempt)
              <tr class="hover:bg-slate-50/50 transition-colors">
                <td class="px-6 py-3 font-semibold text-slate-700" data-label="Email Attempted">{{ $attempt->email }}</td>
                <td class="px-6 py-3 font-mono" data-label="IP Address">{{ $attempt->ip_address }}</td>
                <td class="px-6 py-3 text-slate-400 max-w-xs truncate" title="{{ $attempt->user_agent }}" data-label="User Agent">
                  {{ $attempt->user_agent }}
                </td>
                <td class="px-6 py-3 text-slate-400 font-medium" data-label="Timestamp">{{ $attempt->attempted_at->format('M d, Y H:i:s') }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center py-8 text-slate-400">No failed login logs recorded.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($failedAttempts->hasPages())
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/30">
          {{ $failedAttempts->links() }}
        </div>
      @endif
    </div>

    {{-- TAB 4: ACTIVITY LOG --}}
    <div x-show="activeTab === 'activity'" class="card overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
        <div>
          <h3 class="font-display font-bold text-slate-800 text-sm">Recent Authentication Activity Log</h3>
          <span class="text-xs text-slate-400">{{ $activityLogs->total() }} events</span>
        </div>
        @if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'admin-crm']))
          <form method="POST" action="{{ route('admin.security.activity.clear') }}"
                data-confirm-title="Clear all activity history?"
                data-confirm="This will permanently delete all security activity logs. Proceed?"
                data-confirm-text="Clear History"
                data-confirm-tone="danger">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-secondary py-1 px-3 text-[11px] text-rose-600 hover:bg-rose-50 border-rose-200 hover:border-rose-300 transition-colors">
              🗑 Clear History
            </button>
          </form>
        @endif
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse responsive-table">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] font-semibold text-slate-400 uppercase bg-slate-50/50">
              <th class="px-6 py-3">User</th>
              <th class="px-6 py-3">Action</th>
              <th class="px-6 py-3">Description</th>
              <th class="px-6 py-3">IP Address</th>
              <th class="px-6 py-3">Time</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 text-slate-600 text-xs">
            @forelse($activityLogs as $log)
              <tr class="hover:bg-slate-50/50 transition-colors">
                <td class="px-6 py-3 font-semibold text-slate-700" data-label="User">{{ $log->user?->name ?? 'System' }}</td>
                <td class="px-6 py-3 font-mono text-[10px] uppercase font-bold text-indigo-600" data-label="Action">{{ $log->action }}</td>
                <td class="px-6 py-3" data-label="Description">{{ $log->description }}</td>
                <td class="px-6 py-3 font-mono" data-label="IP Address">{{ $log->ip_address ?? 'N/A' }}</td>
                <td class="px-6 py-3 text-slate-400" data-label="Time">{{ $log->created_at->diffForHumans() }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center py-8 text-slate-400">No activity logs found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($activityLogs->hasPages())
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/30">
          {{ $activityLogs->links() }}
        </div>
      @endif
    </div>

    {{-- TAB 5: SETTINGS --}}
    <div x-show="activeTab === 'settings'" class="space-y-6" x-cloak>
      <div class="card p-6 max-w-2xl">
        <h3 class="font-display font-bold text-slate-800 text-base mb-2">Login Ban Configuration</h3>
        <p class="text-xs text-slate-500 mb-6">Configure the auto-ban threshold and duration. This will ban both the user's IP address and their specific device (via a persistent tracking cookie) to prevent evasion.</p>
        
        <form method="POST" action="{{ route('admin.security.settings') }}" class="space-y-5">
          @csrf
          
          <div>
            <label class="form-label font-bold text-sm">Failed Login Attempts to Ban</label>
            <p class="text-[11px] text-slate-400 mb-1.5">How many consecutive failed logins before the IP and device are banned?</p>
            <input type="number" name="security_max_login_attempts" min="1" value="{{ old('security_max_login_attempts', $settings['max_attempts']) }}" class="form-input text-sm w-48" required>
          </div>
          
          <div>
            <label class="form-label font-bold text-sm">Ban Duration (Minutes)</label>
            <p class="text-[11px] text-slate-400 mb-1.5">Enter 0 for a permanent ban. Otherwise, enter the number of minutes the ban should last.</p>
            <input type="number" name="security_ban_duration_minutes" min="0" value="{{ old('security_ban_duration_minutes', $settings['ban_duration']) }}" class="form-input text-sm w-48" required>
          </div>
          
          <div class="pt-4 border-t border-slate-100">
            <button type="submit" class="btn btn-primary px-6 py-2.5 shadow-sm text-sm">Save Configuration</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>
@endsection
