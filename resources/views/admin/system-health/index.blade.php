@extends('layouts.app')
@section('title', 'System Health & Maintenance')
@section('page_title', 'System Health & Maintenance')

@section('content')
<div class="animate-fade-in space-y-8 pb-32" x-data="systemHealthManager()">

  {{-- Hidden Pre-compiled AI Report for 0ms Instant Clipboard Copy --}}
  <textarea id="ai-markdown-report" class="hidden" readonly>{{ $aiReport ?? '' }}</textarea>

  {{-- Top Header & Action Buttons --}}
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white dark:bg-gray-900 p-6 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm">
    <div class="flex items-center gap-3.5">
      <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-500 to-sky-500 text-white flex items-center justify-center text-2xl font-bold shadow-md shadow-indigo-500/20">
        🩺
      </div>
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-2xl font-display font-bold text-slate-800 dark:text-white">System Health &amp; Maintenance</h1>
          <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
            Multi-Module
          </span>
        </div>
        <p class="text-sm text-slate-400 dark:text-slate-400 mt-0.5">Comprehensive diagnostics, cross-module self-repair, and system speed optimization.</p>
      </div>
    </div>

    {{-- Main Actions Bar --}}
    <div class="flex flex-wrap items-center gap-2.5">
      {{-- 1. Instant Copy for AI Button --}}
      <button 
        type="button"
        @click="copyForAi()" 
        :disabled="copying"
        class="btn inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-sm transition-all shadow-sm cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
        :class="copied ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-600/20'"
        title="Copy complete diagnostics formatted for AI Assistant"
      >
        <span x-show="!copying && !copied">📋</span>
        <span x-show="copying" class="animate-spin text-xs">⏳</span>
        <span x-show="copied">✓</span>
        <span x-text="copied ? 'Copied Diagnostics for AI!' : (copying ? 'Copying...' : 'Copy for AI Assistant')"></span>
      </button>

      {{-- 2. Clear Cache & Optimize Speed Button --}}
      <button 
        type="button" 
        @click="optimizeSystem()"
        :disabled="optimizing"
        class="btn inline-flex items-center gap-2 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white px-4 py-2.5 rounded-xl font-semibold text-sm shadow-sm shadow-blue-500/20 transition-all cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
        title="Clear all compiled caches and prime system for maximum speed"
      >
        <span x-show="!optimizing">🚀</span>
        <span x-show="optimizing" class="animate-spin text-xs">⏳</span>
        <span x-text="optimizing ? 'Optimizing Speed...' : 'Clear Cache & Optimize'"></span>
      </button>

      {{-- 3. Run Auto-Repair Routine --}}
      <button 
        type="button" 
        @click="runRepair('all')"
        :disabled="repairing"
        class="btn inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white px-4 py-2.5 rounded-xl font-semibold text-sm shadow-sm shadow-orange-500/20 transition-all cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
        title="Self-heal all detected issues across modules"
      >
        <span x-show="!repairing">⚡</span>
        <span x-show="repairing" class="animate-spin text-xs">⏳</span>
        <span x-text="repairing ? 'Repairing System...' : 'Run Auto-Repair'"></span>
      </button>

      {{-- 4. Re-scan Refresh --}}
      <button 
        type="button" 
        @click="rescan()"
        :disabled="rescanning"
        class="btn inline-flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-gray-200 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
        title="Re-scan all diagnostics"
      >
        <span :class="rescanning ? 'animate-spin' : ''">🔄</span>
        <span x-text="rescanning ? 'Scanning...' : 'Re-scan'">Re-scan</span>
      </button>
    </div>
  </div>

  {{-- Live Dynamic Alpine Notification Banner --}}
  <div x-show="notification" x-cloak class="animate-fade-in transition-all">
    <div 
      class="p-5 rounded-2xl border shadow-sm flex items-start justify-between gap-4"
      :class="{
        'bg-cyan-50 dark:bg-cyan-950/40 border-cyan-200 dark:border-cyan-800 text-cyan-900 dark:text-cyan-200': notification?.type === 'success' && notification?.icon === '🚀',
        'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200': notification?.type === 'success' && notification?.icon !== '🚀',
        'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-200': notification?.type === 'warning',
        'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-900 dark:text-rose-200': notification?.type === 'error'
      }"
    >
      <div class="flex items-start gap-3.5 flex-1">
        <span class="text-2xl" x-text="notification?.icon || 'ℹ️'"></span>
        <div class="flex-1 text-sm">
          <p class="font-bold text-base mb-1" x-text="notification?.title"></p>
          <p class="text-xs opacity-90 mb-2" x-text="notification?.message"></p>
          <template x-if="notification?.actions && notification.actions.length > 0">
            <ul class="list-disc list-inside space-y-1 text-xs opacity-90 font-mono">
              <template x-for="(act, idx) in notification.actions" :key="idx">
                <li x-text="act"></li>
              </template>
            </ul>
          </template>
        </div>
      </div>
      <button 
        type="button" 
        @click="dismissNotification()" 
        class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-lg text-lg leading-none cursor-pointer"
        title="Dismiss notice"
      >
        ✕
      </button>
    </div>
  </div>

  {{-- Flash Notifications --}}
  @if(session('optimize_success'))
  <div class="p-4 rounded-2xl bg-cyan-50 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800 text-cyan-900 dark:text-cyan-300 animate-fade-in shadow-sm">
    <div class="flex items-start gap-3">
      <span class="text-2xl">🚀</span>
      <div class="flex-1 text-sm">
        <p class="font-bold text-base mb-1">{{ session('optimize_success') }}</p>
        @if(session('optimize_actions') && count(session('optimize_actions')) > 0)
        <ul class="list-disc list-inside space-y-1 mt-2 text-xs opacity-90 font-mono">
          @foreach(session('optimize_actions') as $act)
            <li>{{ $act }}</li>
          @endforeach
        </ul>
        @endif
      </div>
    </div>
  </div>
  @endif

  @if(session('repair_success'))
  <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-300 animate-fade-in shadow-sm">
    <div class="flex items-start gap-3">
      <span class="text-2xl">✅</span>
      <div class="flex-1 text-sm">
        <p class="font-bold text-base mb-1">{{ session('repair_success') }}</p>
        @if(session('repair_actions') && count(session('repair_actions')) > 0)
        <ul class="list-disc list-inside space-y-1 mt-2 text-xs opacity-90 font-mono">
          @foreach(session('repair_actions') as $act)
            <li>{{ $act }}</li>
          @endforeach
        </ul>
        @endif
      </div>
    </div>
  </div>
  @endif

  @if(session('status'))
  <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300 text-sm flex items-center gap-2">
    <span>ℹ️</span>
    <span>{{ session('status') }}</span>
  </div>
  @endif

  {{-- KPI Overview Grid --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
    {{-- Card 1: Overall Health --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl p-5 border border-slate-200 dark:border-gray-800 shadow-sm flex flex-col justify-between">
      <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-gray-400">Overall Health</p>
      <div class="mt-3 flex items-center gap-2">
        @if($diagnostics['overall_status'] === 'healthy')
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Operational
          </span>
        @elseif($diagnostics['overall_status'] === 'warning')
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
            <span class="w-2 h-2 rounded-full bg-amber-500"></span> Attention Needed
          </span>
        @else
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
            <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span> Critical Error
          </span>
        @endif
      </div>
      <p class="text-xs text-slate-400 mt-2">{{ $diagnostics['total_issues'] }} issue(s) detected</p>
    </div>

    {{-- Card 2: Board Automations --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl p-5 border border-slate-200 dark:border-gray-800 shadow-sm flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-gray-400">Board Automations</p>
        <span class="text-lg">🤖</span>
      </div>
      <div class="mt-2">
        <span class="text-2xl font-bold text-slate-800 dark:text-white">{{ $diagnostics['automations']['total_checked'] }}</span>
        <span class="text-xs text-slate-400 ml-1">rules active</span>
      </div>
      <div class="mt-2 text-xs {{ $diagnostics['automations']['issues_count'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400 font-semibold' }}">
        {{ $diagnostics['automations']['issues_count'] === 0 ? '✓ All rules correctly linked' : $diagnostics['automations']['issues_count'] . ' rule issue(s)' }}
      </div>
    </div>

    {{-- Card 3: Social Media & SMM --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl p-5 border border-slate-200 dark:border-gray-800 shadow-sm flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-gray-400">Social Media</p>
        <span class="text-lg">📱</span>
      </div>
      <div class="mt-2">
        <span class="text-2xl font-bold text-slate-800 dark:text-white">{{ $diagnostics['social_media']['total_items'] }}</span>
        <span class="text-xs text-slate-400 ml-1">profiles in {{ $diagnostics['social_media']['total_classes'] }} classes</span>
      </div>
      <div class="mt-2 text-xs {{ $diagnostics['social_media']['issues_count'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400 font-semibold' }}">
        {{ $diagnostics['social_media']['issues_count'] === 0 ? '✓ ' . $diagnostics['social_media']['week_cards_count'] . ' cards synced' : $diagnostics['social_media']['issues_count'] . ' item issue(s)' }}
      </div>
    </div>

    {{-- Card 4: All Websites & Follow-Ups --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl p-5 border border-slate-200 dark:border-gray-800 shadow-sm flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-gray-400">All Websites</p>
        <span class="text-lg">🌐</span>
      </div>
      <div class="mt-2">
        <span class="text-2xl font-bold text-slate-800 dark:text-white">{{ $diagnostics['websites']['total_websites'] }}</span>
        <span class="text-xs text-slate-400 ml-1">sites ({{ $diagnostics['websites']['total_follow_ups'] }} follow-ups)</span>
      </div>
      <div class="mt-2 text-xs {{ $diagnostics['websites']['issues_count'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400 font-semibold' }}">
        {{ $diagnostics['websites']['issues_count'] === 0 ? '✓ Follow-ups & sync healthy' : $diagnostics['websites']['issues_count'] . ' website issue(s)' }}
      </div>
    </div>

    {{-- Card 5: Speed & Caches --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl p-5 border border-slate-200 dark:border-gray-800 shadow-sm flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-gray-400">System Speed</p>
        <span class="text-lg">⚡</span>
      </div>
      <div class="mt-2 flex items-center gap-1.5">
        <span class="px-2 py-0.5 rounded text-[0.7rem] font-bold transition-colors"
              :class="perfRoutesCached ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'"
              x-text="perfRoutesCached ? 'Routes Cached' : 'Routes Uncached'">
          {{ $diagnostics['performance']['routes_cached'] ? 'Routes Cached' : 'Routes Uncached' }}
        </span>
        <span class="px-2 py-0.5 rounded text-[0.7rem] font-bold transition-colors"
              :class="perfConfigCached ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'"
              x-text="perfConfigCached ? 'Config Cached' : 'Config Uncached'">
          {{ $diagnostics['performance']['config_cached'] ? 'Config Cached' : 'Config Uncached' }}
        </span>
      </div>
      <div class="mt-2 text-xs text-slate-400">
        <span x-text="perfViewsCount"></span> views • Log: <span x-text="perfLogSize"></span>
      </div>
    </div>
  </div>

  {{-- ──────────────── SECTION 1: BOARD & CARD COMMENT AUTOMATIONS ──────────────── --}}
  <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-100 dark:border-gray-800 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="text-2xl">🤖</span>
        <div>
          <h2 class="text-lg font-bold text-slate-800 dark:text-white">Board &amp; Card Comment Automation Diagnostics</h2>
          <p class="text-xs text-slate-400">Scans trigger words, target boards, destination lists, and month-end rotation consistency.</p>
        </div>
      </div>
      <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $diagnostics['automations']['issues_count'] === 0 ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' }}">
        {{ $diagnostics['automations']['issues_count'] }} issue(s)
      </span>
    </div>

    @if($diagnostics['automations']['issues_count'] === 0)
    <div class="p-6">
      <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 text-emerald-800 dark:text-emerald-300 flex items-center gap-3">
        <span class="text-xl">✅</span>
        <div class="text-sm">
          <p class="font-bold">All Board &amp; Card Automations are healthy!</p>
          <p class="text-xs opacity-90 mt-0.5">Every trigger word ('ready', 'qc approved', 'supervisor', etc.), target board, and destination list is properly connected with no broken references.</p>
        </div>
      </div>
    </div>
    @else
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 dark:bg-gray-800/50 text-xs uppercase font-bold text-slate-400 dark:text-slate-400 border-b border-slate-100 dark:border-gray-800">
          <tr>
            <th class="py-3.5 px-6">Severity</th>
            <th class="py-3.5 px-6">Board</th>
            <th class="py-3.5 px-6">Error Reason</th>
            <th class="py-3.5 px-6">Suggested Resolution</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-gray-800">
          @foreach($diagnostics['automations']['issues'] as $issue)
          <tr class="hover:bg-slate-50/50 dark:hover:bg-gray-800/30 transition-colors">
            <td class="py-4 px-6 whitespace-nowrap">
              @if($issue['severity'] === 'error')
                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400">ERROR</span>
              @else
                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">WARNING</span>
              @endif
            </td>
            <td class="py-4 px-6 font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap">{{ $issue['board'] }}</td>
            <td class="py-4 px-6 text-slate-600 dark:text-slate-300 font-mono text-xs">{{ $issue['reason'] }}</td>
            <td class="py-4 px-6 text-slate-500 dark:text-slate-400 text-xs">{{ $issue['suggested_fix'] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  {{-- ──────────────── SECTION 2: BOARD STRUCTURES & TWIN-CARD INTEGRITY ──────────────── --}}
  <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-100 dark:border-gray-800 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="text-2xl">📋</span>
        <div>
          <h2 class="text-lg font-bold text-slate-800 dark:text-white">Board Structures, Lists &amp; Twin Card Sync Integrity</h2>
          <p class="text-xs text-slate-400">Monitors list hierarchy, required workflow stages, SMM Block/Waiting list, and card sync groups.</p>
        </div>
      </div>
      @php $boardAndSyncCount = $diagnostics['boards']['issues_count'] + $diagnostics['sync']['issues_count']; @endphp
      <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $boardAndSyncCount === 0 ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' }}">
        {{ $boardAndSyncCount }} issue(s)
      </span>
    </div>

    @if($boardAndSyncCount === 0)
    <div class="p-6">
      <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 text-emerald-800 dark:text-emerald-300 flex items-center gap-3">
        <span class="text-xl">✅</span>
        <div class="text-sm">
          <p class="font-bold">Board structures and twin cards are fully synchronized!</p>
          <p class="text-xs opacity-90 mt-0.5">Active workflow boards have all required stage lists, SMM planning boards have the Block/Waiting list, and card sync groups are consistent.</p>
        </div>
      </div>
    </div>
    @else
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 dark:bg-gray-800/50 text-xs uppercase font-bold text-slate-400 dark:text-slate-400 border-b border-slate-100 dark:border-gray-800">
          <tr>
            <th class="py-3.5 px-6">Severity</th>
            <th class="py-3.5 px-6">Title</th>
            <th class="py-3.5 px-6">Error Reason</th>
            <th class="py-3.5 px-6">Suggested Resolution</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-gray-800">
          @foreach(array_merge($diagnostics['boards']['issues'], $diagnostics['sync']['issues']) as $issue)
          <tr class="hover:bg-slate-50/50 dark:hover:bg-gray-800/30 transition-colors">
            <td class="py-4 px-6 whitespace-nowrap">
              <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $issue['severity'] === 'error' ? 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' }}">
                {{ strtoupper($issue['severity']) }}
              </span>
            </td>
            <td class="py-4 px-6 font-semibold text-slate-800 dark:text-slate-200">{{ $issue['title'] }}</td>
            <td class="py-4 px-6 text-slate-600 dark:text-slate-300 font-mono text-xs">{{ $issue['reason'] }}</td>
            <td class="py-4 px-6 text-slate-500 dark:text-slate-400 text-xs">{{ $issue['suggested_fix'] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  {{-- ──────────────── SECTION 3: SOCIAL MEDIA MANAGEMENT ──────────────── --}}
  <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <span class="text-2xl">📱</span>
        <div>
          <h2 class="text-lg font-bold text-slate-800 dark:text-white">Social Media Management Diagnostics</h2>
          <p class="text-xs text-slate-400">Profiles, active platforms, URL integrity, and SMM planning week distribution.</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $diagnostics['social_media']['issues_count'] === 0 ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' }}">
          {{ $diagnostics['social_media']['issues_count'] }} issue(s)
        </span>
        <button 
          type="button" 
          @click="runRepair('social_media')"
          :disabled="repairing"
          class="btn text-xs font-semibold px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-gray-200 transition-all inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
          title="Repair Social Media platform links"
        >
          <span x-show="!(repairing && repairScope === 'social_media')">⚡</span>
          <span x-show="repairing && repairScope === 'social_media'" class="animate-spin text-xs">⏳</span>
          <span x-text="repairing && repairScope === 'social_media' ? 'Repairing...' : 'Repair Social Media'"></span>
        </button>
      </div>
    </div>

    @if($diagnostics['social_media']['issues_count'] === 0)
    <div class="p-6">
      <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 text-emerald-800 dark:text-emerald-300 flex items-center gap-3">
        <span class="text-xl">✅</span>
        <div class="text-sm">
          <p class="font-bold">Social media profiles, classes, and planning weeks are healthy!</p>
          <p class="text-xs opacity-90 mt-0.5">{{ $diagnostics['social_media']['total_items'] }} platform items across {{ $diagnostics['social_media']['total_classes'] }} classes are active. {{ $diagnostics['social_media']['week_cards_count'] }} card(s) distributed in '{{ $diagnostics['social_media']['active_smm_board'] }}'.</p>
        </div>
      </div>
    </div>
    @else
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 dark:bg-gray-800/50 text-xs uppercase font-bold text-slate-400 dark:text-slate-400 border-b border-slate-100 dark:border-gray-800">
          <tr>
            <th class="py-3.5 px-6">Severity</th>
            <th class="py-3.5 px-6">Title</th>
            <th class="py-3.5 px-6">Error Reason</th>
            <th class="py-3.5 px-6">Suggested Resolution</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-gray-800">
          @foreach($diagnostics['social_media']['issues'] as $issue)
          <tr class="hover:bg-slate-50/50 dark:hover:bg-gray-800/30 transition-colors">
            <td class="py-4 px-6 whitespace-nowrap">
              <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $issue['severity'] === 'error' ? 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' }}">
                {{ strtoupper($issue['severity']) }}
              </span>
            </td>
            <td class="py-4 px-6 font-semibold text-slate-800 dark:text-slate-200">{{ $issue['title'] }}</td>
            <td class="py-4 px-6 text-slate-600 dark:text-slate-300 font-mono text-xs">{{ $issue['reason'] }}</td>
            <td class="py-4 px-6 text-slate-500 dark:text-slate-400 text-xs">{{ $issue['suggested_fix'] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  {{-- ──────────────── SECTION 4: ALL WEBSITES & FOLLOW-UPS ──────────────── --}}
  <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <span class="text-2xl">🌐</span>
        <div>
          <h2 class="text-lg font-bold text-slate-800 dark:text-white">All Websites &amp; Follow-Up Diagnostics</h2>
          <p class="text-xs text-slate-400">Website records, category associations, follow-up linkages, and Google Sheet sync queue.</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $diagnostics['websites']['issues_count'] === 0 ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' }}">
          {{ $diagnostics['websites']['issues_count'] }} issue(s)
        </span>
        <button 
          type="button" 
          @click="runRepair('websites')"
          :disabled="repairing"
          class="btn text-xs font-semibold px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-gray-200 transition-all inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
          title="Repair Websites & queued follow-up syncs"
        >
          <span x-show="!(repairing && repairScope === 'websites')">⚡</span>
          <span x-show="repairing && repairScope === 'websites'" class="animate-spin text-xs">⏳</span>
          <span x-text="repairing && repairScope === 'websites' ? 'Repairing...' : 'Repair Websites'"></span>
        </button>
      </div>
    </div>

    @if($diagnostics['websites']['issues_count'] === 0)
    <div class="p-6">
      <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 text-emerald-800 dark:text-emerald-300 flex items-center gap-3">
        <span class="text-xl">✅</span>
        <div class="text-sm">
          <p class="font-bold">All Websites and Follow-Up records are consistent!</p>
          <p class="text-xs opacity-90 mt-0.5">{{ $diagnostics['websites']['total_websites'] }} websites and {{ $diagnostics['websites']['total_follow_ups'] }} follow-up entries are properly indexed with no orphan references.</p>
        </div>
      </div>
    </div>
    @else
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 dark:bg-gray-800/50 text-xs uppercase font-bold text-slate-400 dark:text-slate-400 border-b border-slate-100 dark:border-gray-800">
          <tr>
            <th class="py-3.5 px-6">Severity</th>
            <th class="py-3.5 px-6">Title</th>
            <th class="py-3.5 px-6">Error Reason</th>
            <th class="py-3.5 px-6">Suggested Resolution</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-gray-800">
          @foreach($diagnostics['websites']['issues'] as $issue)
          <tr class="hover:bg-slate-50/50 dark:hover:bg-gray-800/30 transition-colors">
            <td class="py-4 px-6 whitespace-nowrap">
              <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $issue['severity'] === 'error' ? 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' }}">
                {{ strtoupper($issue['severity']) }}
              </span>
            </td>
            <td class="py-4 px-6 font-semibold text-slate-800 dark:text-slate-200">{{ $issue['title'] }}</td>
            <td class="py-4 px-6 text-slate-600 dark:text-slate-300 font-mono text-xs">{{ $issue['reason'] }}</td>
            <td class="py-4 px-6 text-slate-500 dark:text-slate-400 text-xs">{{ $issue['suggested_fix'] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  {{-- ──────────────── SECTION 5: NOTES & COLLABORATION ──────────────── --}}
  <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <span class="text-2xl">📝</span>
        <div>
          <h2 class="text-lg font-bold text-slate-800 dark:text-white">Notes &amp; Folder Hierarchy Diagnostics</h2>
          <p class="text-xs text-slate-400">Team and Private notes folder structures and orphan document associations.</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $diagnostics['notes']['issues_count'] === 0 ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' }}">
          {{ $diagnostics['notes']['issues_count'] }} issue(s)
        </span>
        <button 
          type="button" 
          @click="runRepair('notes')"
          :disabled="repairing"
          class="btn text-xs font-semibold px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-gray-200 transition-all inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
          title="Repair orphan notes and link to root folder"
        >
          <span x-show="!(repairing && repairScope === 'notes')">⚡</span>
          <span x-show="repairing && repairScope === 'notes'" class="animate-spin text-xs">⏳</span>
          <span x-text="repairing && repairScope === 'notes' ? 'Repairing...' : 'Repair Notes'"></span>
        </button>
      </div>
    </div>

    @if($diagnostics['notes']['issues_count'] === 0)
    <div class="p-6">
      <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 text-emerald-800 dark:text-emerald-300 flex items-center gap-3">
        <span class="text-xl">✅</span>
        <div class="text-sm">
          <p class="font-bold">Notes and folders are healthy!</p>
          <p class="text-xs opacity-90 mt-0.5">{{ $diagnostics['notes']['total_notes'] }} note(s) across {{ $diagnostics['notes']['total_folders'] }} folder(s) have valid parent linkages.</p>
        </div>
      </div>
    </div>
    @else
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 dark:bg-gray-800/50 text-xs uppercase font-bold text-slate-400 dark:text-slate-400 border-b border-slate-100 dark:border-gray-800">
          <tr>
            <th class="py-3.5 px-6">Severity</th>
            <th class="py-3.5 px-6">Title</th>
            <th class="py-3.5 px-6">Error Reason</th>
            <th class="py-3.5 px-6">Suggested Resolution</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-gray-800">
          @foreach($diagnostics['notes']['issues'] as $issue)
          <tr class="hover:bg-slate-50/50 dark:hover:bg-gray-800/30 transition-colors">
            <td class="py-4 px-6 whitespace-nowrap">
              <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">WARNING</span>
            </td>
            <td class="py-4 px-6 font-semibold text-slate-800 dark:text-slate-200">{{ $issue['title'] }}</td>
            <td class="py-4 px-6 text-slate-600 dark:text-slate-300 font-mono text-xs">{{ $issue['reason'] }}</td>
            <td class="py-4 px-6 text-slate-500 dark:text-slate-400 text-xs">{{ $issue['suggested_fix'] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  {{-- ──────────────── SECTION 6: SYSTEM MODULES & MAINTENANCE STATUS ──────────────── --}}
  <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm p-6">
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <span class="text-2xl">🔒</span>
        <div>
          <h2 class="text-lg font-bold text-slate-800 dark:text-white">System Modules &amp; Maintenance Lock Status</h2>
          <p class="text-xs text-slate-400">Live operational status of core application modules (configured in Maintenance System).</p>
        </div>
      </div>
      @if($diagnostics['maintenance']['maintenance_count'] > 0)
        <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
          {{ $diagnostics['maintenance']['maintenance_count'] }} Module(s) Locked
        </span>
      @else
        <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">
          All Modules Open
        </span>
      @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5 mt-4">
      @foreach($diagnostics['maintenance']['modules'] as $modKey => $modInfo)
      <div class="p-3.5 rounded-xl border border-slate-200 dark:border-gray-800 bg-slate-50/50 dark:bg-gray-800/20 flex items-center justify-between">
        <span class="text-sm font-semibold text-slate-700 dark:text-gray-200">{{ $modInfo['name'] }}</span>
        @if($modInfo['status'] === 'maintenance')
          <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">
            ⚠️ Maintenance
          </span>
        @else
          <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">
            ✓ Operational
          </span>
        @endif
      </div>
      @endforeach
    </div>
  </div>

  {{-- ──────────────── SECTION 7: SERVER ENVIRONMENT & ERROR LOGS ──────────────── --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Server Info Card --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm p-6">
      <div class="flex items-center gap-3 mb-4">
        <span class="text-2xl">🖥️</span>
        <h3 class="text-base font-bold text-slate-800 dark:text-white">Server &amp; Platform</h3>
      </div>
      <div class="space-y-3 text-sm">
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-gray-800">
          <span class="text-slate-400">PHP Version</span>
          <span class="font-mono font-semibold text-slate-700 dark:text-slate-200">{{ $diagnostics['server']['php_version'] }}</span>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-gray-800">
          <span class="text-slate-400">Application Timezone</span>
          <span class="font-mono font-semibold text-slate-700 dark:text-slate-200">{{ $diagnostics['server']['app_timezone'] }}</span>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-gray-800">
          <span class="text-slate-400">Database Time</span>
          <span class="font-mono font-semibold text-slate-700 dark:text-slate-200">{{ $diagnostics['server']['database_time'] }}</span>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-gray-800">
          <span class="text-slate-400">Storage Symlink</span>
          <span class="font-semibold {{ $diagnostics['server']['storage_symlink_ok'] ? 'text-emerald-600' : 'text-rose-600' }}">
            {{ $diagnostics['server']['storage_symlink_ok'] ? '✓ Connected' : '✗ Missing' }}
          </span>
        </div>
        <div class="flex items-center justify-between py-1.5">
          <span class="text-slate-400">Storage Writable</span>
          <span class="font-semibold {{ $diagnostics['server']['storage_writable'] ? 'text-emerald-600' : 'text-rose-600' }}">
            {{ $diagnostics['server']['storage_writable'] ? '✓ Yes' : '✗ No' }}
          </span>
        </div>
      </div>
    </div>

    {{-- Error Logs Viewer --}}
    <div class="lg:col-span-2 bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm p-6">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
          <span class="text-2xl">⚠️</span>
          <div>
            <h3 class="text-base font-bold text-slate-800 dark:text-white">Recent Server &amp; Application Error Logs</h3>
            <p class="text-xs text-slate-400">Last 15 exceptions recorded in storage/logs/laravel.log (Log file size: {{ $diagnostics['performance']['log_size'] }})</p>
          </div>
        </div>
        <button 
          type="button" 
          @click="clearLogFile()"
          :disabled="clearingLog"
          class="btn text-xs font-semibold px-3 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/30 dark:hover:bg-rose-900/40 text-rose-600 dark:text-rose-400 transition-all inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
          title="Clear the server log file"
        >
          <span x-show="!clearingLog">🗑️</span>
          <span x-show="clearingLog" class="animate-spin text-xs">⏳</span>
          <span x-text="clearingLog ? 'Clearing...' : 'Clear Log'"></span>
        </button>
      </div>

      <div id="recent-error-logs-container">
        @if(count($diagnostics['logs']['recent_errors']) === 0)
        <div class="p-6 rounded-xl bg-slate-50 dark:bg-gray-800/30 text-center">
          <span class="text-3xl">✨</span>
          <p class="font-bold text-slate-700 dark:text-slate-300 mt-2 text-sm">No recent application errors found!</p>
          <p class="text-xs text-slate-400 mt-1">laravel.log is clean with no recent exceptions.</p>
        </div>
        @else
        <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
          @foreach($diagnostics['logs']['recent_errors'] as $err)
          <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-gray-800/50 border border-slate-200 dark:border-gray-700/60 font-mono text-xs">
            <div class="flex items-center justify-between text-slate-400 text-[0.7rem] mb-1">
              <span>{{ $err['timestamp'] }}</span>
              <span class="px-2 py-0.5 rounded font-bold bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400">{{ $err['level'] }}</span>
            </div>
            <p class="font-bold text-rose-700 dark:text-rose-400 mb-0.5">{{ $err['exception'] }}</p>
            <p class="text-slate-600 dark:text-slate-300 truncate">{{ $err['message'] }}</p>
          </div>
          @endforeach
        </div>
        @endif
      </div>
    </div>
  </div>

  {{-- ──────────────── SECTION 8: AI REPORT PREVIEW TOGGLE ──────────────── --}}
  <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm p-6">
    <div class="flex items-center justify-between cursor-pointer select-none" @click="showAiPreview = !showAiPreview">
      <div class="flex items-center gap-3">
        <span class="text-2xl">🤖</span>
        <div>
          <h3 class="text-base font-bold text-slate-800 dark:text-white">AI Diagnostics Report Preview</h3>
          <p class="text-xs text-slate-400">Click to preview the complete Markdown report that gets copied to your clipboard.</p>
        </div>
      </div>
      <button type="button" class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
        <span x-show="!showAiPreview">Show Preview ▾</span>
        <span x-show="showAiPreview">Hide Preview ▴</span>
      </button>
    </div>

    <div x-show="showAiPreview" x-cloak class="mt-4 pt-4 border-t border-slate-100 dark:border-gray-800">
      <pre class="p-4 rounded-xl bg-slate-900 text-slate-100 text-xs font-mono overflow-x-auto max-h-96 leading-relaxed whitespace-pre-wrap">{{ $aiReport ?? '' }}</pre>
    </div>
  </div>

</div>

<script>
function systemHealthManager() {
    return {
        copying: false,
        copied: false,
        showAiPreview: false,
        repairing: false,
        repairScope: 'all',
        optimizing: false,
        rescanning: false,
        clearingLog: false,
        
        // Live performance indicators (initialized from server render)
        perfRoutesCached: {{ $diagnostics['performance']['routes_cached'] ? 'true' : 'false' }},
        perfConfigCached: {{ $diagnostics['performance']['config_cached'] ? 'true' : 'false' }},
        perfViewsCount: {{ (int)$diagnostics['performance']['compiled_views_count'] }},
        perfLogSize: '{{ $diagnostics['performance']['log_size'] }}',
        
        // Live notification banner state
        notification: null,
        dismissNotification() {
            this.notification = null;
        },

        // 1. Copy for AI Assistant
        async copyForAi() {
            if (this.copying) return;
            this.copying = true;
            try {
                let text = '';
                const ta = document.getElementById('ai-markdown-report');
                if (ta && ta.value && ta.value.trim().length > 0) {
                    text = ta.value;
                } else {
                    const res = await fetch('{{ route('system.health.copy-report') }}', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();
                    text = data.markdown || '';
                }

                if (!text) {
                    alert('Diagnostic report is empty.');
                    return;
                }

                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                    this.onCopied();
                } else {
                    this.fallbackCopy(text);
                }
            } catch (err) {
                console.error(err);
                alert('Could not copy report to clipboard.');
            } finally {
                this.copying = false;
            }
        },

        fallbackCopy(text) {
            const temp = document.createElement('textarea');
            temp.value = text;
            temp.style.position = 'fixed';
            temp.style.left = '-9999px';
            temp.style.top = '-9999px';
            document.body.appendChild(temp);
            temp.focus();
            temp.select();
            try {
                document.execCommand('copy');
                this.onCopied();
            } catch (e) {
                alert('Please copy report manually from the AI Report preview below.');
            } finally {
                document.body.removeChild(temp);
            }
        },

        onCopied() {
            this.copied = true;
            setTimeout(() => this.copied = false, 4000);
        },

        // 2. Clear Cache & Optimize Speed
        async optimizeSystem() {
            if (this.optimizing) return;
            this.optimizing = true;
            this.notification = null;
            
            // Safety timeout so it can never stay stuck
            const timer = setTimeout(() => {
                if (this.optimizing) {
                    this.optimizing = false;
                    this.notification = {
                        type: 'warning',
                        icon: '⏱️',
                        title: 'Optimization Request Sent',
                        message: 'The optimization task was submitted. Click Re-scan to verify current cache state.',
                        actions: []
                    };
                }
            }, 15000);

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || document.querySelector('input[name="_token"]')?.value
                    || '{{ csrf_token() }}';

                const res = await fetch('{{ route('system.health.optimize') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                clearTimeout(timer);

                if (res.ok) {
                    const data = await res.json();
                    if (data.performance) {
                        this.perfRoutesCached = !!data.performance.routes_cached;
                        this.perfConfigCached = !!data.performance.config_cached;
                        this.perfViewsCount = data.performance.compiled_views_count;
                        this.perfLogSize = data.performance.log_size;
                    }
                    this.notification = {
                        type: 'success',
                        icon: '🚀',
                        title: data.message || 'System Speed Optimized Successfully!',
                        message: 'Compiled caches are primed and operational for maximum speed.',
                        actions: data.actions_taken || []
                    };
                } else {
                    throw new Error('Server returned HTTP ' + res.status);
                }
            } catch (err) {
                clearTimeout(timer);
                console.error('Optimization error:', err);
                this.notification = {
                    type: 'error',
                    icon: '⚠️',
                    title: 'Speed Optimization Note',
                    message: err.message || 'Optimization request encountered an issue. Click Re-scan to check status.',
                    actions: []
                };
            } finally {
                this.optimizing = false;
            }
        },

        // 3. Run Auto-Repair (all or specific module)
        async runRepair(scope = 'all') {
            if (this.repairing) return;

            const confirmMsg = scope === 'all' 
                ? 'Run auto-repair now? This will align automations, restore missing lists, sync card statuses across all modules, and clear stale caches.'
                : 'Run auto-repair for ' + scope + ' now?';
            
            if (!confirm(confirmMsg)) return;

            this.repairing = true;
            this.repairScope = scope;
            this.notification = null;

            const timer = setTimeout(() => {
                if (this.repairing) {
                    this.repairing = false;
                    this.notification = {
                        type: 'warning',
                        icon: '⏱️',
                        title: 'Auto-Repair Operation In Progress',
                        message: 'The repair was queued. Click Re-scan in a moment to review updated health.',
                        actions: []
                    };
                }
            }, 20000);

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || document.querySelector('input[name="_token"]')?.value
                    || '{{ csrf_token() }}';

                const res = await fetch('{{ route('system.health.repair') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ scope: scope })
                });

                clearTimeout(timer);

                if (res.ok) {
                    const data = await res.json();
                    this.notification = {
                        type: 'success',
                        icon: '✅',
                        title: data.message || 'Auto-repair routine executed successfully.',
                        message: 'Repairs have been applied across the selected modules.',
                        actions: data.actions_taken || []
                    };
                    // Automatically re-scan in background after 1.5s to refresh all diagnostic tables
                    setTimeout(() => {
                        this.rescan();
                    }, 1500);
                } else {
                    throw new Error('Server returned HTTP ' + res.status);
                }
            } catch (err) {
                clearTimeout(timer);
                console.error('Repair error:', err);
                this.notification = {
                    type: 'error',
                    icon: '⚠️',
                    title: 'Auto-Repair Could Not Complete',
                    message: err.message || 'An error occurred during auto-repair.',
                    actions: []
                };
            } finally {
                this.repairing = false;
            }
        },

        // 4. Re-scan Refresh
        rescan() {
            if (this.rescanning) return;
            this.rescanning = true;
            const targetUrl = window.location.pathname + '?refresh=' + Date.now();
            if (window.Turbo) {
                window.Turbo.visit(targetUrl, { action: 'replace' });
            } else {
                window.location.href = targetUrl;
            }
            setTimeout(() => { this.rescanning = false; }, 4000);
        },

        // 5. Clear Log File
        async clearLogFile() {
            if (this.clearingLog) return;
            if (!confirm('Clear the server log file now?')) return;

            this.clearingLog = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || document.querySelector('input[name="_token"]')?.value
                    || '{{ csrf_token() }}';

                const res = await fetch('{{ route('system.health.clear-logs') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (res.ok) {
                    this.perfLogSize = '0.0 KB';
                    const logContainer = document.getElementById('recent-error-logs-container');
                    if (logContainer) {
                        logContainer.innerHTML = '<div class="p-6 rounded-xl bg-slate-50 dark:bg-gray-800/30 text-center"><span class="text-3xl">✨</span><p class="font-bold text-slate-700 dark:text-slate-300 mt-2 text-sm">No recent application errors found!</p><p class="text-xs text-slate-400 mt-1">laravel.log has been cleared.</p></div>';
                    }
                    this.notification = {
                        type: 'success',
                        icon: '🗑️',
                        title: 'System Log Cleared',
                        message: 'storage/logs/laravel.log has been emptied successfully.',
                        actions: []
                    };
                }
            } catch (err) {
                console.error('Clear log error:', err);
                alert('Could not clear log file.');
            } finally {
                this.clearingLog = false;
            }
        }
    };
}
</script>
@endsection
