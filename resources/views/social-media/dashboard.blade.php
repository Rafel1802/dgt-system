@extends('layouts.app')

@section('title', 'Social Media Team Dashboard')

@section('content')
<style>
/* Dashboard Styles matching Websites module */
@keyframes countUp { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
.metric-counter { animation: countUp 0.5s ease both; }

.ws-dash-card {
    background: var(--card-bg, #fff);
    border: 1.5px solid var(--border-color, #e2e8f0);
    border-radius: 1.25rem;
    transition: all 0.25s ease;
    overflow: hidden;
    display: flex; flex-direction: column;
}
.ws-dash-card:hover { box-shadow: 0 12px 40px rgba(0,0,0,0.1); transform: translateY(-3px); }
[data-theme="dark"] .ws-dash-card { background: #0f172a; border-color: #1e293b; }
[data-theme="dark"] .ws-dash-card:hover { box-shadow: 0 12px 40px rgba(0,0,0,0.4); }

.status-bar-track { height: 8px; border-radius: 9999px; overflow: hidden; display: flex; gap: 1px; background: var(--bg-page, #f1f5f9); }
[data-theme="dark"] .status-bar-track { background: #1e293b; }

.pillar-badge {
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 0.5rem; padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 700;
}
.bg-indigo-soft { background: #e0e7ff; color: #4338ca; }
.bg-emerald-soft { background: #d1fae5; color: #047857; }
.bg-amber-soft { background: #fef3c7; color: #b45309; }
.bg-blue-soft { background: #dbeafe; color: #1d4ed8; }
.bg-slate-soft { background: #f1f5f9; color: #475569; }

[data-theme="dark"] .bg-indigo-soft { background: rgba(99,102,241,0.2); color: #818cf8; }
[data-theme="dark"] .bg-emerald-soft { background: rgba(16,185,129,0.2); color: #34d399; }
[data-theme="dark"] .bg-amber-soft { background: rgba(245,158,11,0.2); color: #fbbf24; }
[data-theme="dark"] .bg-blue-soft { background: rgba(59,130,246,0.2); color: #60a5fa; }
[data-theme="dark"] .bg-slate-soft { background: rgba(148,163,184,0.1); color: #94a3b8; }
</style>

<div x-data="{ searchQuery: '', filterClass: '' }">
<div class="page-header flex flex-wrap gap-4 items-end justify-between mb-8">
    <div>
        <h1 class="page-title text-3xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
            <div class="p-2 bg-indigo-500 text-white rounded-xl shadow-lg shadow-indigo-500/30 w-12 h-12 flex items-center justify-center flex-shrink-0">
                <img src="https://cdn-icons-png.flaticon.com/512/1468/1468269.png" alt="Social Media Team" class="w-8 h-8 object-contain">
            </div>
            Social Media Team
        </h1>
        <p class="page-subtitle text-slate-500 dark:text-slate-400 mt-2 font-medium">Manage social media tasks and tracking</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-1.5 shadow-sm">
            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="searchQuery" placeholder="Search class..." class="border-0 focus:ring-0 p-0 text-sm w-32 sm:w-40 placeholder-slate-400 dark:placeholder-slate-500 bg-transparent text-slate-800 dark:text-white">
        </div>
        
        <select x-model="filterClass" class="form-select py-1.5 text-sm w-32 sm:w-48 shadow-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white rounded-lg">
            <option value="">All Classes</option>
            @foreach($classesWithStats as $stat)
                <option value="{{ $stat['model']->id }}">{{ $stat['model']->name }}</option>
            @endforeach
        </select>

        @if($isAdmin)
        <a href="{{ route('social-media.manage') }}" class="btn btn-secondary flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
            Manage Classes
        </a>
        @endif
        <a href="{{ route('social-media.reports.index') }}" class="btn btn-primary flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
            Reports
        </a>
    </div>
</div>

{{-- Global KPIs --}}
@if(auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'social_qc']))
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    @php
    $kpiCards = [
        ['icon'=>'📁', 'label'=>'Classes', 'value'=>$globalStats['total_classes'], 'color'=>'text-slate-700', 'bg'=>'bg-white'],
        ['icon'=>'📱', 'label'=>'Socials', 'value'=>$globalStats['total_items'],   'color'=>'text-indigo-600', 'bg'=>'bg-indigo-50'],
        ['icon'=>'✅', 'label'=>'Completed','value'=>$globalStats['completed'],     'color'=>'text-emerald-600','bg'=>'bg-emerald-50'],
        ['icon'=>'⏳', 'label'=>'Pending',  'value'=>$globalStats['pending'],       'color'=>'text-amber-600',  'bg'=>'bg-amber-50'],
        ['icon'=>'🛡️', 'label'=>'QC Checked','value'=>$globalStats['qc_checked'],   'color'=>'text-blue-600',   'bg'=>'bg-blue-50'],
        ['icon'=>'👀', 'label'=>'QC Pending','value'=>$globalStats['qc_pending'],   'color'=>'text-orange-600', 'bg'=>'bg-orange-50'],
    ];
    @endphp
    @foreach($kpiCards as $kpi)
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 flex flex-col justify-center items-center gap-1 {{ str_replace('bg-', 'dark:bg-', $kpi['bg']) }}/10 bg-white dark:bg-slate-800 shadow-sm metric-counter" style="animation-delay: {{ $loop->index * 0.05 }}s">
        <div class="flex items-center gap-2">
            <span class="text-xl">{{ $kpi['icon'] }}</span>
            <span class="text-2xl font-black {{ str_replace('text-', 'dark:text-', $kpi['color']) }}">{{ $kpi['value'] }}</span>
        </div>
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $kpi['label'] }}</div>
    </div>
    @endforeach
</div>
@endif

{{-- Class Cards Grid --}}
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 pb-24 md:pb-6">
    @forelse($classesWithStats as $stat)
        @php
            $class = $stat['model'];
            $total = $stat['total_posts'];
            $pctComplete = $total > 0 ? round(($stat['completed'] / $total) * 100) : 0;
            $pctChecked  = $total > 0 ? round(($stat['qc_checked'] / $total) * 100) : 0;
        @endphp
        <div class="ws-dash-card" x-show="(filterClass === '' || filterClass === '{{ $class->id }}') && ('{{ strtolower($class->name) }}'.includes(searchQuery.toLowerCase()))">
            {{-- Card Header --}}
            <div class="p-5 border-b border-slate-100 dark:border-slate-700/50 flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-1">{{ $class->name }}</h3>
                    <div class="flex items-center gap-2">
                        <span class="pillar-badge {{ $class->status === 'active' ? 'bg-emerald-soft' : 'bg-slate-soft' }}">
                            {{ ucfirst($class->status) }}
                        </span>
                        <span class="text-xs font-semibold text-slate-500 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                            {{ $class->assignedUsers->count() }} Members
                        </span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 shadow-sm overflow-hidden {{ $class->icon ? 'bg-transparent' : 'bg-gradient-to-br from-indigo-500 to-purple-600 text-white font-bold text-lg' }}">
                    @if($class->icon)
                        <img src="{{ $class->icon }}" alt="{{ $class->name }}" class="w-full h-full object-cover">
                    @else
                        {{ strtoupper(substr($class->name, 0, 2)) }}
                    @endif
                </div>
            </div>



            {{-- Card Footer --}}
            <div class="p-4 border-t border-slate-100 dark:border-slate-700/50 bg-white dark:bg-slate-800 flex items-center justify-between gap-3">
                <a href="{{ route('social-media.class.show', $class->id) }}" class="btn btn-primary flex-1 py-2 justify-center shadow-sm">
                    View Table
                </a>
            </div>
        </div>
    @empty
        <div class="col-span-full">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-12 text-center shadow-sm">
                <span class="text-5xl block mb-4">📭</span>
                <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2">No Classes Found</h3>
                <p class="text-slate-500">You haven't been assigned to any Social Media classes yet.</p>
                @if($isAdmin)
                    <a href="{{ route('social-media.manage') }}" class="btn btn-primary mt-6">Create Your First Class</a>
                @endif
            </div>
        </div>
    @endforelse
</div>
</div>
@endsection
