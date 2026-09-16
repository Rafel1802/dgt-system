@extends('layouts.app')

@section('title', 'Meeting Alarms Management')
@section('page_title', 'Meeting Alarms')

@section('content')
<div class="max-w-7xl mx-auto pb-12" x-data="meetingAlarmsManager()">
    {{-- Top Alert Messages --}}
    @if(session('success'))
    <div class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 font-bold flex items-center justify-between shadow-sm animate-fade-in">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('success') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    @endif

    @if($errors->any())
    <div class="mb-6 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 font-bold shadow-sm">
        <p class="mb-1 font-extrabold flex items-center gap-2">
            <svg class="w-5 h-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            Please check the errors below:
        </p>
        <ul class="list-disc list-inside text-xs space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Page Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-500 via-purple-500 to-pink-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">Meeting Alarms</h1>
                    <p class="text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400 mt-0.5">
                        Schedule automatic pop-up alarms with sound alerts for all team members at the exact meeting time.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            {{-- Phnom Penh Clock Badge --}}
            <div class="hidden sm:flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 text-xs font-bold text-slate-700 dark:text-slate-300 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                <span>Phnom Penh:</span>
                <span class="font-mono text-indigo-600 dark:text-indigo-400" x-text="livePhnomPenhTime">--:--:--</span>
            </div>

            {{-- Schedule Meeting Button --}}
            <button type="button"
                    @click="openCreateModal()"
                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-bold text-white transition-all bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-xl hover:from-indigo-500 hover:to-pink-500 focus:ring-4 focus:ring-indigo-300 dark:focus:ring-indigo-900 shadow-lg shadow-indigo-500/25 hover:-translate-y-0.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Schedule Meeting Alarm</span>
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    @php
        $nowPP = \Carbon\Carbon::now('Asia/Phnom_Penh');
        $totalCount = \App\Models\MeetingAlarm::count();
        $upcomingTodayCount = \App\Models\MeetingAlarm::where('is_active', true)
            ->whereBetween('meeting_time', [$nowPP->copy()->startOfDay(), $nowPP->copy()->endOfDay()])
            ->count();
        $activeCount = \App\Models\MeetingAlarm::where('is_active', true)->count();
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bento-card p-5 flex items-center justify-between border-l-4 border-indigo-500">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Scheduled</p>
                <p class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ $totalCount }}</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        </div>

        <div class="bento-card p-5 flex items-center justify-between border-l-4 border-amber-500">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Scheduled For Today</p>
                <p class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ $upcomingTodayCount }}</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center text-amber-600 dark:text-amber-400">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        <div class="bento-card p-5 flex items-center justify-between border-l-4 border-emerald-500">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Active Alarms</p>
                <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ $activeCount }}</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- Main Table Card --}}
    <div class="bento-card overflow-hidden shadow-xl">
        <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-extrabold text-slate-900 dark:text-white">Scheduled Meeting Alarms</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">When time arrives, all connected team members will hear the chosen sound and see the pop-up modal.</p>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400 font-semibold">Sound default: <strong class="text-indigo-600 dark:text-indigo-400">melodic-chime.wav</strong></span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-700/80">
                        <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">Meeting Info</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">Scheduled Time (Phnom Penh)</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">Ringtone Sound</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">Meeting Link</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider text-center">Status</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($meetingAlarms as $alarm)
                    @php
                        $isUpcoming = $alarm->meeting_time->isFuture();
                        $isPast = $alarm->meeting_time->isPast();
                        $isToday = $alarm->meeting_time->isToday();
                    @endphp
                    <tr class="group hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                        {{-- Meeting Info --}}
                        <td class="px-6 py-4">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-xl flex-shrink-0 flex items-center justify-center text-lg {{ $isUpcoming ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-400' }}">
                                    📅
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="font-bold text-slate-900 dark:text-white text-sm group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                            {{ $alarm->title }}
                                        </p>
                                        @if($isToday)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">
                                                Today
                                            </span>
                                        @endif
                                    </div>
                                    @if($alarm->description)
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-xs line-clamp-2">
                                            {{ $alarm->description }}
                                        </p>
                                    @endif
                                    <p class="text-[11px] text-slate-400 mt-1 flex items-center gap-1.5">
                                        <span>Created by:</span>
                                        <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $alarm->creator?->name ?? 'System' }}</span>
                                    </p>
                                </div>
                            </div>
                        </td>

                        {{-- Scheduled Time --}}
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="text-sm font-black text-slate-800 dark:text-slate-200">
                                    {{ $alarm->meeting_time->format('h:i A') }}
                                </span>
                                <span class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ $alarm->meeting_time->format('l, M d, Y') }}
                                </span>
                                <span class="text-[11px] font-semibold mt-1 {{ $isUpcoming ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400' }}">
                                    {{ $alarm->meeting_time->diffForHumans() }}
                                </span>
                            </div>
                        </td>

                        {{-- Sound --}}
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                    <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                                    </svg>
                                    <span>{{ $alarm->sound }}</span>
                                </span>

                                {{-- Preview Sound Button --}}
                                <button type="button"
                                        @click="toggleSoundPreview('{{ $alarm->sound }}', '{{ $alarm->sound_url }}')"
                                        class="p-1.5 rounded-lg text-xs font-bold transition-all"
                                        :class="playingSound === '{{ $alarm->sound }}' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/30' : 'bg-slate-100 hover:bg-indigo-100 text-slate-600 hover:text-indigo-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300'"
                                        title="Preview sound">
                                    <template x-if="playingSound === '{{ $alarm->sound }}'">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6"/>
                                        </svg>
                                    </template>
                                    <template x-if="playingSound !== '{{ $alarm->sound }}'">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                        </svg>
                                    </template>
                                </button>
                            </div>
                        </td>

                        {{-- Meeting Link --}}
                        <td class="px-6 py-4">
                            @if($alarm->meeting_link)
                                <a href="{{ $alarm->meeting_link }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-indigo-50 hover:bg-indigo-100 text-indigo-600 dark:bg-indigo-500/10 dark:hover:bg-indigo-500/20 dark:text-indigo-400 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    <span>Join Link</span>
                                </a>
                            @else
                                <span class="text-xs text-slate-400 italic">No link provided</span>
                            @endif
                        </td>

                        {{-- Status Switch --}}
                        <td class="px-6 py-4 text-center">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox"
                                       class="sr-only peer"
                                       {{ $alarm->is_active ? 'checked' : '' }}
                                       @change="toggleActiveStatus({{ $alarm->id }}, $event.target)">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-emerald-500"></div>
                                <span class="ml-2 text-xs font-bold uppercase tracking-wider {{ $alarm->is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}" id="status-label-{{ $alarm->id }}">
                                    {{ $alarm->is_active ? 'Active' : 'Paused' }}
                                </span>
                            </label>
                        </td>

                        {{-- Actions --}}
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                {{-- Test Alarm Trigger Button --}}
                                <button type="button"
                                        @click="testAlarmTrigger({
                                            id: {{ $alarm->id }},
                                            title: @js($alarm->title),
                                            description: @js($alarm->description),
                                            meeting_time: @js($alarm->meeting_time->format('h:i A')),
                                            meeting_link: @js($alarm->meeting_link),
                                            sound: @js($alarm->sound),
                                            sound_url: @js($alarm->sound_url),
                                            ring_duration: {{ $alarm->ring_duration ?? 10 }}
                                        })"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-amber-50 hover:bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:hover:bg-amber-500/20 dark:text-amber-400 transition-colors"
                                        title="Preview pop-up & sound">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <span>Test</span>
                                </button>

                                {{-- Edit Button --}}
                                <button type="button"
                                        @click="openEditModal({
                                            id: {{ $alarm->id }},
                                            title: @js($alarm->title),
                                            description: @js($alarm->description),
                                            meeting_time: @js($alarm->meeting_time->format('Y-m-d\TH:i')),
                                            meeting_link: @js($alarm->meeting_link),
                                            sound: @js($alarm->sound),
                                            ring_duration: {{ $alarm->ring_duration ?? 10 }},
                                            is_active: {{ $alarm->is_active ? 'true' : 'false' }}
                                        })"
                                        class="p-1.5 rounded-lg text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10 transition-colors"
                                        title="Edit meeting">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                    </svg>
                                </button>

                                {{-- Delete Form --}}
                                <form action="{{ route('admin.meeting-alarms.destroy', $alarm) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this meeting alarm?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10 transition-colors" title="Delete">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="max-w-sm mx-auto">
                                <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center mx-auto mb-4 text-3xl">
                                    🔔
                                </div>
                                <h3 class="text-base font-black text-slate-900 dark:text-white">No Meeting Alarms Scheduled</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 mb-5">
                                    Create a meeting alarm to alert all employees with a pop-up and sound when the meeting begins.
                                </p>
                                <button type="button"
                                        @click="openCreateModal()"
                                        class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-all shadow-md">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <span>Schedule First Meeting</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($meetingAlarms->hasPages())
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            {{ $meetingAlarms->links() }}
        </div>
        @endif
    </div>

    {{-- Audio Player for In-Page Sound Previewing --}}
    <audio id="admin-meeting-sound-preview" preload="none"></audio>

    {{-- ========================================================================= --}}
    {{-- MODAL: Schedule New Meeting Alarm                                         --}}
    {{-- ========================================================================= --}}
    <div x-show="showCreateModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="showCreateModal = false"
         style="display: none;">
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity" @click="showCreateModal = false"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-xl rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 sm:p-8 shadow-2xl transition-all transform z-10"
                 @click.stop>
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center text-white shadow-md">
                            🔔
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-slate-900 dark:text-white">Schedule Meeting Alarm</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">All users will receive a pop-up and ringtone alert.</p>
                        </div>
                    </div>
                    <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form action="{{ route('admin.meeting-alarms.store') }}" method="POST" class="mt-6 space-y-4">
                    @csrf

                    {{-- Title --}}
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                            Meeting Title <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="title" required placeholder="e.g. Weekly QC Sync Meeting / Project Review"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>

                    {{-- Meeting Date & Time (Cambodia Time) --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                Date & Time (Phnom Penh Time) <span class="text-rose-500">*</span>
                            </label>
                            <span class="text-[11px] text-indigo-600 dark:text-indigo-400 font-bold">Timezone: Asia/Phnom_Penh (GMT+7)</span>
                        </div>
                        <input type="datetime-local" name="meeting_time" required x-model="defaultMeetingTime"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>

                    {{-- Meeting Join Link --}}
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                            Meeting Join Link <span class="text-slate-400 font-normal">(Google Meet, Zoom, Teams, etc.)</span>
                        </label>
                        <input type="url" name="meeting_link" placeholder="https://meet.google.com/... or https://zoom.us/..."
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>

                    {{-- Description / Agenda --}}
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                            Meeting Notes / Agenda <span class="text-slate-400 font-normal">(optional)</span>
                        </label>
                        <textarea name="description" rows="2" placeholder="Brief note or agenda topic shown on the pop-up modal..."
                                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                    </div>

                    {{-- Sound Chooser (Auto-picks alarm1.mp3 with option to change & preview) --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                Alarm Ringtone Sound <span class="text-indigo-600 dark:text-indigo-400 font-bold">(Auto-selected: melodic-chime.wav)</span>
                            </label>
                            <button type="button"
                                    @click="toggleSoundPreview(selectedCreateSound, '/clocksound/' + selectedCreateSound)"
                                    class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                </svg>
                                <span x-text="playingSound === selectedCreateSound ? 'Stop Preview' : 'Listen Now'"></span>
                            </button>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-48 overflow-y-auto p-2 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30">
                            @foreach($clockSounds as $sound)
                            <label class="relative flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all"
                                   :class="selectedCreateSound === '{{ $sound }}' ? 'border-indigo-500 bg-indigo-50/80 dark:bg-indigo-900/30 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-slate-300'">
                                <input type="radio" name="sound" value="{{ $sound }}" class="sr-only" x-model="selectedCreateSound">
                                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :class="selectedCreateSound === '{{ $sound }}' ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-600'"></span>
                                <div class="overflow-hidden flex-1">
                                    <p class="text-xs font-bold truncate" :class="selectedCreateSound === '{{ $sound }}' ? 'text-indigo-950 dark:text-indigo-200' : 'text-slate-800 dark:text-slate-200'">
                                        {{ Str::title(str_replace(['-', '_'], ' ', Str::beforeLast($sound, '.'))) }}
                                    </p>
                                    @if($sound === 'melodic-chime.wav')
                                        <span class="text-[9px] font-black uppercase text-indigo-600 dark:text-indigo-400">Default</span>
                                    @endif
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Ring Duration & Active --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                                Ring Duration (Seconds)
                            </label>
                            <input type="number" name="ring_duration" min="3" max="60" value="10"
                                   class="w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <span class="text-[11px] text-slate-400 mt-1 block">Default: 10 seconds</span>
                        </div>

                        <div class="flex items-center sm:justify-center pt-4">
                            <label class="flex items-center cursor-pointer gap-2">
                                <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Activate Immediately</span>
                            </label>
                        </div>
                    </div>

                    {{-- Submit Buttons --}}
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showCreateModal = false"
                                class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-bold shadow-lg shadow-indigo-500/25">
                            Save & Schedule Alarm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL: Edit Meeting Alarm                                                 --}}
    {{-- ========================================================================= --}}
    <div x-show="showEditModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="showEditModal = false"
         style="display: none;">
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity" @click="showEditModal = false"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-xl rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 sm:p-8 shadow-2xl transition-all transform z-10"
                 @click.stop>
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-xl font-bold">
                            ✏️
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-slate-900 dark:text-white">Edit Meeting Alarm</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Update scheduled time, join link, or sound alert.</p>
                        </div>
                    </div>
                    <button type="button" @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form :action="'{{ url('admin/meeting-alarms') }}/' + editData.id" method="POST" class="mt-6 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                            Meeting Title <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="title" required x-model="editData.title"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                Date & Time (Phnom Penh Time) <span class="text-rose-500">*</span>
                            </label>
                            <span class="text-[11px] text-indigo-600 dark:text-indigo-400 font-bold">Asia/Phnom_Penh (GMT+7)</span>
                        </div>
                        <input type="datetime-local" name="meeting_time" required x-model="editData.meeting_time"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                            Meeting Join Link
                        </label>
                        <input type="url" name="meeting_link" x-model="editData.meeting_link" placeholder="https://meet.google.com/..."
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                            Meeting Notes / Agenda
                        </label>
                        <textarea name="description" rows="2" x-model="editData.description"
                                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                Alarm Ringtone Sound
                            </label>
                            <button type="button"
                                    @click="toggleSoundPreview(editData.sound, '/clocksound/' + editData.sound)"
                                    class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                                <span x-text="playingSound === editData.sound ? 'Stop Preview' : 'Listen Now'"></span>
                            </button>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-48 overflow-y-auto p-2 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30">
                            @foreach($clockSounds as $sound)
                            <label class="relative flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all"
                                   :class="editData.sound === '{{ $sound }}' ? 'border-indigo-500 bg-indigo-50/80 dark:bg-indigo-900/30 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-slate-300'">
                                <input type="radio" name="sound" value="{{ $sound }}" class="sr-only" x-model="editData.sound">
                                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :class="editData.sound === '{{ $sound }}' ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-600'"></span>
                                <div class="overflow-hidden flex-1">
                                    <p class="text-xs font-bold truncate" :class="editData.sound === '{{ $sound }}' ? 'text-indigo-950 dark:text-indigo-200' : 'text-slate-800 dark:text-slate-200'">
                                        {{ Str::title(str_replace(['-', '_'], ' ', Str::beforeLast($sound, '.'))) }}
                                    </p>
                                    @if($sound === 'melodic-chime.wav')
                                        <span class="text-[9px] font-black uppercase text-indigo-600 dark:text-indigo-400">Default</span>
                                    @endif
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                                Ring Duration (Seconds)
                            </label>
                            <input type="number" name="ring_duration" min="3" max="60" x-model="editData.ring_duration"
                                   class="w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>

                        <div class="flex items-center sm:justify-center pt-4">
                            <label class="flex items-center cursor-pointer gap-2">
                                <input type="checkbox" name="is_active" value="1" :checked="editData.is_active" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Active</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showEditModal = false"
                                class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md">
                            Update Alarm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function meetingAlarmsManager() {
    return {
        showCreateModal: false,
        showEditModal: false,
        selectedCreateSound: 'melodic-chime.wav',
        defaultMeetingTime: '',
        playingSound: null,
        livePhnomPenhTime: '',
        editData: {
            id: null,
            title: '',
            description: '',
            meeting_time: '',
            meeting_link: '',
            sound: 'melodic-chime.wav',
            ring_duration: 10,
            is_active: true
        },

        init() {
            this.updateLiveTime();
            setInterval(() => this.updateLiveTime(), 1000);

            // Compute a clean default upcoming meeting time for the picker (15 min from now)
            const now = new Date();
            now.setMinutes(now.getMinutes() + 15);
            now.setSeconds(0);
            now.setMilliseconds(0);
            const tzOffset = now.getTimezoneOffset() * 60000;
            this.defaultMeetingTime = (new Date(now - tzOffset)).toISOString().slice(0, 16);
        },

        updateLiveTime() {
            const fmt = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Phnom_Penh',
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
            this.livePhnomPenhTime = fmt.format(new Date());
        },

        openCreateModal() {
            this.selectedCreateSound = 'melodic-chime.wav';
            this.showCreateModal = true;
        },

        openEditModal(data) {
            this.editData = Object.assign({}, data);
            this.showEditModal = true;
        },

        toggleSoundPreview(soundName, soundUrl) {
            const audio = document.getElementById('admin-meeting-sound-preview');
            if (!audio) return;

            if (this.playingSound === soundName) {
                audio.pause();
                audio.currentTime = 0;
                this.playingSound = null;
            } else {
                audio.src = soundUrl;
                audio.currentTime = 0;
                audio.play().then(() => {
                    this.playingSound = soundName;
                }).catch(err => {
                    console.error('Audio playback failed:', err);
                });

                audio.onended = () => {
                    this.playingSound = null;
                };
            }
        },

        async toggleActiveStatus(id, checkbox) {
            const label = document.getElementById('status-label-' + id);
            try {
                const res = await fetch(`{{ url('admin/meeting-alarms') }}/${id}/toggle-active`, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    checkbox.checked = data.is_active;
                    if (label) {
                        label.textContent = data.is_active ? 'Active' : 'Paused';
                        label.className = 'ml-2 text-xs font-bold uppercase tracking-wider ' + (data.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400');
                    }
                }
            } catch (err) {
                console.error('Failed to toggle status:', err);
            }
        },

        testAlarmTrigger(alarm) {
            if (window.triggerMeetingAlarm) {
                window.triggerMeetingAlarm(alarm);
            } else if (window.triggerLunchAlarm) {
                window.triggerLunchAlarm(true);
            } else {
                alert('Pop-up alarm component is loading...');
            }
        }
    };
}
</script>
@endsection
