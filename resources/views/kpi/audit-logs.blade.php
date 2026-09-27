@extends('layouts.app')
@section('title', 'KPI Audit Logs')
@section('page_title', 'KPI Audit Trail')

@section('content')

@include('kpi.styles')

<div class="space-y-6 pb-12">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">KPI Audit Trail</h1>
            <p class="text-xs text-slate-400 mt-1">Immutable record of all KPI allocations, evidence submissions, QC approvals, and reports.</p>
        </div>
        <a href="{{ route('kpi.index') }}" class="clay-btn bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 text-xs">
            ← Dashboard
        </a>
    </div>

    <div class="clay-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-400 font-semibold border-b border-slate-100 dark:border-slate-700/60">
                        <th class="py-3 px-6">Timestamp</th>
                        <th class="py-3 px-4">User</th>
                        <th class="py-3 px-4">Action</th>
                        <th class="py-3 px-4">Entity</th>
                        <th class="py-3 px-4">IP Address</th>
                        <th class="py-3 px-6">Payload</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-slate-700 dark:text-slate-300">
                    @forelse($logs as $log)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-3.5 px-6 font-mono text-slate-400 whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="py-3.5 px-4">
                            <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-blue-50 text-blue-700">
                                {{ $log->action }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 font-mono text-[11px] text-slate-500">{{ class_basename($log->entity_type) }} #{{ $log->entity_id }}</td>
                        <td class="py-3.5 px-4 font-mono text-slate-400">{{ $log->ip_address }}</td>
                        <td class="py-3.5 px-6 font-mono text-[11px] text-slate-500">
                            {{ json_encode($log->new_values) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-400">No audit records found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
