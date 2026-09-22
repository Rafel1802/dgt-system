@php
    $lunchUser = auth()->user();

    $lunchSound = $lunchUser && $lunchUser->lunch_alarm_sound ? $lunchUser->lunch_alarm_sound : 'lunch.wav';
    $lunchPath = 'clocksound/' . $lunchSound;
    $lunchSoundUrl = file_exists(public_path($lunchPath)) ? asset($lunchPath) : asset('clocksound/lunch.wav');

    $offworkSound = $lunchUser && $lunchUser->offwork_alarm_sound ? $lunchUser->offwork_alarm_sound : 'funny.wav';
    $offworkPath = 'clocksound/' . $offworkSound;
    $offworkSoundUrl = file_exists(public_path($offworkPath)) ? asset($offworkPath) : asset('clocksound/funny.wav');

    $satSound = $lunchUser && $lunchUser->sat_alarm_sound ? $lunchUser->sat_alarm_sound : 'funny.wav';
    $satPath = 'clocksound/' . $satSound;
    $satSoundUrl = file_exists(public_path($satPath)) ? asset($satPath) : asset('clocksound/funny.wav');

    $lunchEnabled = $lunchUser ? $lunchUser->isLunchAlarmEnabled() : false;
    $shiftAlarmDuration = (int) \App\Models\Setting::get('shift_alarm_duration', 15);
    if ($shiftAlarmDuration < 3 || $shiftAlarmDuration > 60) {
        $shiftAlarmDuration = 15;
    }
@endphp

<!-- Global Clock & Meeting Alarm Audio Element -->
<audio id="lunch-alarm-sound"
       src="{{ $lunchSoundUrl }}"
       data-default-src="{{ $lunchSoundUrl }}"
       data-lunch-src="{{ $lunchSoundUrl }}"
       data-offwork-src="{{ $offworkSoundUrl }}"
       data-sat-src="{{ $satSoundUrl }}"
       preload="auto"></audio>

<!-- Workplace & Meeting Alarm Pop-up Modal -->
<div x-data="lunchAlarmModal()"
     x-init="init()"
     x-show="isOpen"
     x-cloak
     @keydown.window.escape="close()"
     @lunch-alarm-toggle.window="userEnabled = $event.detail.enabled"
     class="fixed inset-0 z-[99999] flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
     style="display: none;">

    {{-- Backdrop with blur --}}
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-md transition-opacity duration-300 ease-out"
         x-show="isOpen"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="close()">
    </div>

    {{-- Modal Card (White Theme) --}}
    <div class="relative w-full max-w-lg rounded-3xl overflow-hidden shadow-2xl border transition-all transform duration-300 ease-out z-10 p-6 sm:p-8 bg-white text-slate-800"
         :class="{
             'border-amber-200 shadow-amber-500/10 ring-1 ring-amber-300/30': mode === 'lunch',
             'border-emerald-200 shadow-emerald-500/10 ring-1 ring-emerald-300/30': mode === 'offwork' || mode === 'sat_offwork',
             'border-indigo-200 shadow-indigo-500/10 ring-1 ring-indigo-300/30': mode === 'meeting'
         }"
         x-show="isOpen"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-90 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-90 translate-y-4"
         @click.stop="if (autoplayBlocked) { playRingtone(); }">

        {{-- Background Ambient Radial Glows --}}
        <div class="absolute -top-24 -left-24 w-64 h-64 rounded-full blur-3xl pointer-events-none opacity-60"
             :class="{
                 'bg-amber-100': mode === 'lunch',
                 'bg-emerald-100': mode === 'offwork' || mode === 'sat_offwork',
                 'bg-indigo-100': mode === 'meeting'
             }"></div>
        <div class="absolute -bottom-24 -right-24 w-64 h-64 rounded-full blur-3xl pointer-events-none opacity-60"
             :class="{
                 'bg-rose-100': mode === 'lunch',
                 'bg-teal-100': mode === 'offwork' || mode === 'sat_offwork',
                 'bg-purple-100': mode === 'meeting'
             }"></div>

        {{-- Top Right Close Button --}}
        <button type="button"
                @click="close()"
                class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 p-2 rounded-full hover:bg-slate-100 transition-colors focus:outline-none focus:ring-2 focus:ring-slate-300"
                title="Close and stop sound (Esc)">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        {{-- Autoplay Blocked Tap to Play Prompt --}}
        <div x-show="autoplayBlocked" 
             @click.stop="playRingtone()"
             class="mb-4 px-4 py-2.5 rounded-2xl bg-amber-50 border border-amber-300 text-amber-900 text-xs font-bold flex items-center justify-between gap-2 cursor-pointer hover:bg-amber-100 transition-all shadow-sm animate-pulse">
            <span class="flex items-center gap-2">
                <span class="text-base">🔔</span>
                <span>Click anywhere to start the alarm sound!</span>
            </span>
            <span class="bg-amber-500 text-white px-2.5 py-1 rounded-lg text-[11px] font-black uppercase shadow-sm">Unmute</span>
        </div>

        {{-- Header Badge & Dynamic Icon --}}
        <div class="flex flex-col items-center text-center">
            <div class="relative mb-2">
                <div class="w-14 h-14 rounded-2xl p-0.5 shadow-md flex items-center justify-center animate-bounce duration-1000"
                     :class="{
                         'bg-gradient-to-tr from-amber-400 via-orange-400 to-rose-400 shadow-orange-400/20': mode === 'lunch',
                         'bg-gradient-to-tr from-emerald-400 via-teal-400 to-sky-400 shadow-emerald-400/20': mode === 'offwork' || mode === 'sat_offwork',
                         'bg-gradient-to-tr from-indigo-400 via-purple-400 to-pink-400 shadow-indigo-400/20': mode === 'meeting'
                     }">
                    <div class="w-full h-full bg-white rounded-[14px] flex items-center justify-center text-2xl shadow-inner">
                        <template x-if="mode === 'lunch'"><span>🍽️</span></template>
                        <template x-if="mode === 'offwork' || mode === 'sat_offwork'"><span>🎉</span></template>
                        <template x-if="mode === 'meeting'"><span>🔔</span></template>
                    </div>
                </div>
                {{-- Ringing animation pulse around icon --}}
                <div x-show="isRinging" 
                     class="absolute -inset-2 rounded-2xl border-2 animate-ping pointer-events-none"
                     :class="{
                         'border-amber-400/50': mode === 'lunch',
                         'border-emerald-400/50': mode === 'offwork' || mode === 'sat_offwork',
                         'border-indigo-400/50': mode === 'meeting'
                     }"></div>
            </div>

            {{-- Dynamic Title --}}
            <template x-if="mode === 'lunch'">
                <div>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-amber-600 via-orange-600 to-rose-600">
                        It's Lunch Time!
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1 max-w-sm">
                        Time to take a well-deserved break, step away from your screen, and enjoy your meal!
                    </p>
                </div>
            </template>

            <template x-if="mode === 'offwork'">
                <div>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-emerald-600 via-teal-600 to-sky-600">
                        Getting Off Work!
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1 max-w-sm">
                        Great job today! The workday is officially complete — time to wrap up, relax, and head home!
                    </p>
                </div>
            </template>

            <template x-if="mode === 'sat_offwork'">
                <div>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-teal-600 via-emerald-600 to-cyan-600">
                        Saturday Half Day Off!
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1 max-w-sm">
                        Great job today! The Saturday half-day shift (7:00 AM – 11:00 AM) is complete — time to get off work and enjoy your weekend!
                    </p>
                </div>
            </template>

            <template x-if="mode === 'meeting'">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200 mb-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
                        Meeting Alert
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600">
                        Meeting Time!
                    </h2>
                    <h3 class="text-lg sm:text-xl font-extrabold text-slate-900 mt-1 px-4 leading-tight" x-text="meetingData.title">
                        Team Meeting
                    </h3>
                </div>
            </template>
        </div>

        {{-- Meeting Specific Details Box (if mode === 'meeting') --}}
        <template x-if="mode === 'meeting' && (meetingData.description || meetingData.link)">
            <div class="mt-4 p-4 rounded-2xl bg-indigo-50/80 border border-indigo-200/70 text-slate-800">
                <template x-if="meetingData.description">
                    <div class="mb-3">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-700 mb-1">Agenda / Note:</p>
                        <p class="text-xs text-slate-600 font-medium leading-relaxed whitespace-pre-line" x-text="meetingData.description"></p>
                    </div>
                </template>

                <template x-if="meetingData.link">
                    <div class="flex items-center justify-between pt-2 border-t border-indigo-200/50">
                        <span class="text-xs text-slate-600 font-semibold">Join URL ready</span>
                        <a :href="meetingData.link" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-black bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/30 transition-all hover:scale-105">
                            <span>Open Link</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    </div>
                </template>
            </div>
        </template>

        {{-- Cartoon Smile Image Section (Replaces Old Analog Clock Dial) --}}
        <div class="my-5 p-4 sm:p-5 rounded-3xl bg-slate-50 border border-slate-100 flex flex-col items-center justify-center relative overflow-hidden shadow-inner">
            <div class="relative flex items-center justify-center my-1">
                {{-- Dynamic Cartoon Smile Card --}}
                <div class="relative w-36 h-36 sm:w-44 sm:h-44 rounded-3xl overflow-hidden shadow-xl border-4 bg-white transition-all transform hover:scale-105 duration-300"
                     :class="{
                         'border-amber-300 shadow-amber-500/15 ring-4 ring-amber-100': mode === 'lunch',
                         'border-emerald-300 shadow-emerald-500/15 ring-4 ring-emerald-100': mode === 'offwork' || mode === 'sat_offwork',
                         'border-indigo-300 shadow-indigo-500/15 ring-4 ring-indigo-100': mode === 'meeting'
                     }">
                    {{-- Lunch Mode: Cartoon Smile Enjoying Meal with Chef Hat & Cutlery --}}
                    <img x-show="mode === 'lunch'"
                         src="{{ asset('images/alarms/smile-lunch.jpg') }}" 
                         alt="Lunch Smile" 
                         loading="eager"
                         class="w-full h-full object-cover select-none pointer-events-none transition-transform duration-500 ease-out hover:scale-110">
                    
                    {{-- Off Work & Saturday Half Day Mode: Cartoon Smile Celebrating with Party Hat & Confetti --}}
                    <img x-show="mode === 'offwork' || mode === 'sat_offwork'"
                         src="{{ asset('images/alarms/smile-offwork.jpg') }}" 
                         alt="Off Work Celebration Smile" 
                         loading="eager"
                         class="w-full h-full object-cover select-none pointer-events-none transition-transform duration-500 ease-out hover:scale-110">
                    
                    {{-- Meeting Mode: Smart Cartoon Smile with Glasses and Calendar --}}
                    <img x-show="mode === 'meeting'"
                         src="{{ asset('images/alarms/smile-meeting.jpg') }}" 
                         alt="Meeting Alert Smile" 
                         loading="eager"
                         class="w-full h-full object-cover select-none pointer-events-none transition-transform duration-500 ease-out hover:scale-110">
                </div>

                {{-- Floating Ringing Badge --}}
                <div x-show="isRinging"
                     class="absolute -bottom-2.5 px-3.5 py-1 rounded-full text-xs font-black text-white shadow-lg flex items-center gap-1.5 animate-bounce"
                     :class="{
                         'bg-gradient-to-r from-amber-500 to-orange-500 shadow-amber-500/30': mode === 'lunch',
                         'bg-gradient-to-r from-emerald-500 to-teal-500 shadow-emerald-500/30': mode === 'offwork' || mode === 'sat_offwork',
                         'bg-gradient-to-r from-indigo-500 to-purple-500 shadow-indigo-500/30': mode === 'meeting'
                     }">
                    <span class="text-[11px]">🔔</span>
                    <span>Ringing!</span>
                </div>
            </div>

            {{-- Digital Time Display --}}
            <div class="mt-3 text-center">
                <div class="text-2xl sm:text-3xl font-black font-mono tracking-wider"
                     :class="{
                         'text-amber-600': mode === 'lunch',
                         'text-emerald-600': mode === 'offwork' || mode === 'sat_offwork',
                         'text-indigo-600': mode === 'meeting'
                     }"
                     x-text="timeStr"
                     style="font-variant-numeric: tabular-nums;">
                    12:00:00 PM
                </div>
                <div class="flex items-center justify-center gap-1.5 mt-1 text-[11px] font-bold text-slate-500 uppercase tracking-widest">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span x-text="dateStr">Phnom Penh Time</span>
                </div>
            </div>
        </div>

        {{-- Ringtone Status / Visualizer --}}
        <div class="mb-5 flex items-center justify-center gap-3">
            <template x-if="isRinging">
                <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-full border text-xs font-bold shadow-sm"
                     :class="{
                         'bg-amber-50 border-amber-200 text-amber-900': mode === 'lunch',
                         'bg-emerald-50 border-emerald-200 text-emerald-900': mode === 'offwork' || mode === 'sat_offwork',
                         'bg-indigo-50 border-indigo-200 text-indigo-900': mode === 'meeting'
                     }">
                    {{-- Waveform bars --}}
                    <div class="flex items-center gap-0.5 h-3">
                        <span class="w-1 rounded-full animate-[pulse_0.4s_infinite_alternate] bg-current" style="height: 60%"></span>
                        <span class="w-1 rounded-full animate-[pulse_0.6s_infinite_alternate] bg-current" style="height: 100%"></span>
                        <span class="w-1 rounded-full animate-[pulse_0.3s_infinite_alternate] bg-current" style="height: 40%"></span>
                        <span class="w-1 rounded-full animate-[pulse_0.5s_infinite_alternate] bg-current" style="height: 80%"></span>
                    </div>
                    <span>Ringing (<span x-text="ringCountdown"></span>s left)...</span>
                    <button type="button" @click.stop="stopRingtone()" 
                            class="ml-1 text-[10px] uppercase font-black px-2 py-0.5 rounded transition-colors bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 shadow-sm cursor-pointer">
                        Mute Sound
                    </button>
                </div>
            </template>
            <template x-if="!isRinging">
                <div class="flex items-center gap-1.5 text-xs text-slate-500 font-semibold">
                    <template x-if="mode === 'lunch'"><span>✨ Have a wonderful lunch break!</span></template>
                    <template x-if="mode === 'offwork'"><span>🎉 Great work today! See you tomorrow!</span></template>
                    <template x-if="mode === 'sat_offwork'"><span>☀️ Happy weekend! See you on Monday!</span></template>
                    <template x-if="mode === 'meeting'"><span>👥 Please attend the meeting on time!</span></template>
                </div>
            </template>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <template x-if="mode === 'meeting' && meetingData.link">
                <a :href="meetingData.link" target="_blank" rel="noopener noreferrer"
                   @click="close()"
                   class="w-full flex-1 inline-flex items-center justify-center gap-2 py-3.5 px-6 rounded-2xl font-bold text-sm text-white shadow-xl transition-all transform hover:scale-[1.02] active:scale-[0.98] bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 hover:from-indigo-500 hover:to-pink-500 shadow-indigo-500/30">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    <span>Join Meeting Now</span>
                </a>
            </template>

            <button type="button"
                    @click="close()"
                    class="w-full flex-1 inline-flex items-center justify-center gap-2 py-3.5 px-6 rounded-2xl font-bold text-sm text-white shadow-xl transition-all transform hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2"
                    :class="{
                        'bg-gradient-to-r from-amber-500 via-orange-500 to-rose-500 hover:from-amber-600 hover:to-rose-600 shadow-orange-500/25 focus:ring-amber-500/50': mode === 'lunch',
                        'bg-gradient-to-r from-emerald-500 via-teal-500 to-sky-500 hover:from-emerald-600 hover:to-sky-600 shadow-emerald-500/25 focus:ring-emerald-500/50': mode === 'offwork' || mode === 'sat_offwork',
                        'bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 focus:ring-slate-300': mode === 'meeting' && meetingData.link,
                        'bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 shadow-indigo-500/30 focus:ring-indigo-500/50': mode === 'meeting' && !meetingData.link
                    }">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span x-text="mode === 'lunch' ? 'Close & Enjoy Lunch' : (mode === 'offwork' ? 'Close & Clock Out' : 'Close / Got It')"></span>
            </button>
        </div>

    </div>
</div>

<script>
// Document-level audio unlocker to bypass browser autoplay restrictions
(function() {
    window.__dgtAudioContext = null;
    function tryUnlockAudio() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext && !window.__dgtAudioContext) {
                window.__dgtAudioContext = new AudioContext();
            }
            if (window.__dgtAudioContext && window.__dgtAudioContext.state === 'suspended') {
                window.__dgtAudioContext.resume();
            }
            const audio = document.getElementById('lunch-alarm-sound');
            if (audio && !audio.__dgtUnlocked) {
                const origMuted = audio.muted;
                audio.muted = true;
                const p = audio.play();
                if (p !== undefined) {
                    p.then(() => {
                        audio.pause();
                        audio.currentTime = 0;
                        audio.muted = origMuted;
                        audio.__dgtUnlocked = true;
                    }).catch(() => {});
                }
            }
            // Ask for Notification permission on first user interaction if not already granted/denied
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission().catch(() => {});
            }
        } catch (e) {}
    }

    ['click', 'touchstart', 'keydown', 'mousedown', 'pointerdown'].forEach(evt => {
        document.addEventListener(evt, tryUnlockAudio, { once: false, passive: true });
    });
})();

document.addEventListener('alpine:init', () => {
    registerLunchAlarmModal();
});

// Also register immediately if Alpine is already loaded
if (window.Alpine) {
    registerLunchAlarmModal();
}

function registerLunchAlarmModal() {
    if (window.__dgtLunchAlarmModalRegistered) return;
    if (!window.Alpine) return;
    window.__dgtLunchAlarmModalRegistered = true;

    Alpine.data('lunchAlarmModal', () => ({
        isOpen: false,
        isRinging: false,
        autoplayBlocked: false,
        shiftAlarmDuration: {{ $shiftAlarmDuration }},
        ringCountdown: {{ $shiftAlarmDuration }},
        countdownTimer: null,
        stopAudioTimeout: null,
        checkTicker: null,
        worker: null,
        mode: 'lunch', // 'lunch' (12:00 PM), 'offwork' (4:00 PM), or 'meeting'
        userEnabled: {{ $lunchEnabled ? 'true' : 'false' }},
        isTestModal: false,
        testAutoCloseTimeout: null,
        hourDeg: 0,
        minuteDeg: 0,
        secondDeg: 0,
        timeStr: '12:00:00 PM',
        dateStr: '',
        upcomingMeetings: [],
        lastMeetingsFetch: 0,
        titleFlashInterval: null,
        originalDocumentTitle: '',
        broadcastChannel: null,
        meetingData: {
            id: null,
            title: '',
            description: '',
            link: '',
            sound: 'funny.wav',
            sound_url: '{{ $lunchSoundUrl }}',
            ring_duration: 15
        },

        setAudioSource(targetMode = null) {
            const currentTarget = targetMode || this.mode || 'lunch';
            const audio = document.getElementById('lunch-alarm-sound');
            if (!audio) return;
            let targetSrc = audio.dataset.lunchSrc || audio.dataset.defaultSrc;
            if (currentTarget === 'offwork') {
                targetSrc = audio.dataset.offworkSrc || audio.dataset.defaultSrc;
            } else if (currentTarget === 'sat_offwork') {
                targetSrc = audio.dataset.satSrc || audio.dataset.defaultSrc;
            } else if (currentTarget === 'meeting') {
                targetSrc = (this.meetingData && this.meetingData.sound_url) ? this.meetingData.sound_url : audio.dataset.defaultSrc;
            }
            if (targetSrc && audio.src !== targetSrc) {
                audio.src = targetSrc;
                audio.load();
            }
        },

        init() {
            this.originalDocumentTitle = document.title;

            // BroadcastChannel for syncing alarm state across all open browser tabs
            try {
                if ('BroadcastChannel' in window) {
                    this.broadcastChannel = new BroadcastChannel('dgt_workplace_alarm_bus');
                    this.broadcastChannel.onmessage = (e) => {
                        if (!e || !e.data) return;
                        if (e.data.type === 'trigger') {
                            this.mode = e.data.mode || 'lunch';
                            this.isOpen = true;
                            this.updateClock();
                        } else if (e.data.type === 'close') {
                            this.close(false, e.data.mode); // Close without re-broadcasting
                        }
                    };
                }
            } catch (e) {}

            // Restore / Show active alarm pop-up across any page in the system
            if (this.userEnabled) {
                const pp = this.getPhnomPenhParts();
                const isWeekday = pp.dayOfWeek >= 1 && pp.dayOfWeek <= 5;
                const isSaturday = pp.dayOfWeek === 6;

                const dismissedLunch = localStorage.getItem('dgt_alarm_dismissed_lunch_' + pp.dateKey);
                const dismissedOffwork = localStorage.getItem('dgt_alarm_dismissed_offwork_' + pp.dateKey);
                const dismissedSaturday = localStorage.getItem('dgt_alarm_dismissed_sat11_' + pp.dateKey);

                // 1. Monday to Friday Schedule
                if (isWeekday) {
                    // 12:00 PM - 12:09:59 PM Lunch Time (auto-closes at 12:10 PM)
                    if (pp.hour === 12 && pp.minute < 10 && !dismissedLunch) {
                        this.mode = 'lunch';
                        this.isOpen = true;
                        const rungLunch = localStorage.getItem('dgt_alarm_rung_lunch_' + pp.dateKey);
                        if (!rungLunch) {
                            localStorage.setItem('dgt_alarm_rung_lunch_' + pp.dateKey, 'true');
                            this.playRingtone(this.shiftAlarmDuration);
                            this.startTitleFlashing('🍽️ It\'s Lunch Time!');
                            this.sendDesktopNotification('🍽️ It\'s Lunch Time!', 'Time for lunch break!');
                        }
                    }
                    // 4:00 PM - 4:09:59 PM Off Work Time (auto-closes at 4:10 PM)
                    else if (pp.hour === 16 && pp.minute < 10 && !dismissedOffwork) {
                        this.mode = 'offwork';
                        this.isOpen = true;
                        const rungOffwork = localStorage.getItem('dgt_alarm_rung_offwork_' + pp.dateKey);
                        if (!rungOffwork) {
                            localStorage.setItem('dgt_alarm_rung_offwork_' + pp.dateKey, 'true');
                            this.playRingtone(this.shiftAlarmDuration);
                            this.startTitleFlashing('🎉 Getting Off Work!');
                            this.sendDesktopNotification('🎉 Getting Off Work!', 'Workday is complete. Great job today!');
                        }
                    }
                }
                // 2. Saturday Half Day Schedule (7:00 AM - 11:00 AM, alarm at 11:00 AM, auto-closes at 11:10 AM)
                else if (isSaturday) {
                    if ((pp.hour === 11 || pp.hour === 23) && pp.minute < 10 && !dismissedSaturday) {
                        this.mode = 'sat_offwork';
                        this.isOpen = true;
                        const rungSat = localStorage.getItem('dgt_alarm_rung_sat11_' + pp.dateKey);
                        if (!rungSat) {
                            localStorage.setItem('dgt_alarm_rung_sat11_' + pp.dateKey, 'true');
                            this.playRingtone(this.shiftAlarmDuration);
                            this.startTitleFlashing('🎉 Saturday Half Day Off!');
                            this.sendDesktopNotification('🎉 Saturday Half Day Complete!', 'Time to get off work! Enjoy your weekend!');
                        }
                    }
                }
                // Sunday: Off, no shift alarms
                else {
                    try {
                        const saved = sessionStorage.getItem('dgt_active_alarm_modal');
                        if (saved) {
                            const parsed = JSON.parse(saved);
                            if (parsed && parsed.dateKey === pp.dateKey && parsed.mode) {
                                if (parsed.mode === 'lunch' && (pp.hour !== 12 || pp.minute >= 10 || !isWeekday)) {
                                    sessionStorage.removeItem('dgt_active_alarm_modal');
                                } else if (parsed.mode === 'offwork' && (pp.hour !== 16 || pp.minute >= 10 || !isWeekday)) {
                                    sessionStorage.removeItem('dgt_active_alarm_modal');
                                } else if (parsed.mode === 'sat_offwork' && ((pp.hour !== 11 && pp.hour !== 23) || pp.minute >= 10 || !isSaturday)) {
                                    sessionStorage.removeItem('dgt_active_alarm_modal');
                                } else if (parsed.mode === 'meeting') {
                                    this.mode = 'meeting';
                                    if (parsed.meetingData) {
                                        this.meetingData = parsed.meetingData;
                                    }
                                    this.isOpen = true;
                                    this.isRinging = false;
                                }
                            }
                        }
                    } catch (e) {}
                }
            }

            // Expose globally so users/admins can test anytime
            window.triggerLunchAlarm = (isTest = false, mode = 'lunch') => {
                this.triggerAlarm(isTest, mode);
            };

            window.triggerOffWorkAlarm = (isTest = false) => {
                this.triggerAlarm(isTest, 'offwork');
            };

            window.triggerSaturdayAlarm = (isTest = false) => {
                this.triggerAlarm(isTest, 'sat_offwork');
            };

            window.triggerMeetingAlarm = (meeting) => {
                this.triggerMeetingAlert(meeting, true);
            };

            window.closeLunchAlarm = () => {
                this.close();
            };

            // Start live clock updates
            this.updateClock();

            // Fetch upcoming meetings right away
            this.fetchUpcomingMeetings();

            // Background Tab Fix: Web Worker Ticker
            // Browsers aggressively throttle setInterval in background tabs to 1 min or freeze it completely.
            // Web Workers run in an independent background thread and are NOT throttled by Chrome/Safari/Edge/Firefox!
            try {
                if (window.__dgtLunchWorker) {
                    try { window.__dgtLunchWorker.terminate(); } catch (e) {}
                }
                const workerBlob = new Blob([`
                    let timer = null;
                    self.onmessage = function(e) {
                        if (e.data === 'start') {
                            if (!timer) {
                                timer = setInterval(function() {
                                    self.postMessage('tick');
                                }, 1000);
                            }
                        } else if (e.data === 'stop') {
                            if (timer) {
                                clearInterval(timer);
                                timer = null;
                            }
                        }
                    };
                `], { type: 'application/javascript' });
                const workerUrl = URL.createObjectURL(workerBlob);
                this.worker = new Worker(workerUrl);
                window.__dgtLunchWorker = this.worker;
                this.worker.onmessage = (e) => {
                    if (e.data === 'tick') {
                        this.onTick();
                    }
                };
                this.worker.postMessage('start');
            } catch (err) {
                console.warn('Web Worker ticker failed, falling back to standard interval:', err);
                if (this.checkTicker) clearInterval(this.checkTicker);
                this.checkTicker = setInterval(() => {
                    this.onTick();
                }, 1000);
            }
        },

        onTick() {
            this.updateClock();
            this.checkTimeTrigger();
            this.checkMeetingTriggers();

            // Poll upcoming meetings every 30 seconds
            const nowMs = Date.now();
            if (nowMs - this.lastMeetingsFetch > 30000) {
                this.fetchUpcomingMeetings();
            }
        },

        async fetchUpcomingMeetings() {
            this.lastMeetingsFetch = Date.now();
            try {
                const res = await fetch('{{ route('meeting-alarms.upcoming') }}', {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data && Array.isArray(data.alarms)) {
                        this.upcomingMeetings = data.alarms;
                    }
                }
            } catch (e) {
                // Silently ignore network hiccup
            }
        },

        getPhnomPenhParts() {
            const now = new Date();
            const formatter = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Phnom_Penh',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: 'numeric',
                minute: 'numeric',
                second: 'numeric',
                weekday: 'short',
                hourCycle: 'h23'
            });
            const parts = formatter.formatToParts(now);
            const p = {};
            parts.forEach(part => p[part.type] = part.value);

            // Wall-clock day of week in Phnom Penh (0 = Sunday, 1 = Monday, ..., 6 = Saturday)
            const ppDate = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Phnom_Penh' }));
            const dayOfWeek = ppDate.getDay();

            return {
                year: p.year,
                month: p.month,
                day: p.day,
                hour: parseInt(p.hour, 10),
                minute: parseInt(p.minute, 10),
                second: parseInt(p.second, 10),
                dayOfWeek: dayOfWeek,
                weekday: p.weekday,
                dateKey: `${p.year}-${p.month}-${p.day}`
            };
        },

        updateClock() {
            const pp = this.getPhnomPenhParts();
            const now = new Date();

            // Calculate SVG rotation angles
            const hours = pp.hour % 12;
            const minutes = pp.minute;
            const seconds = pp.second;

            this.hourDeg = (hours * 30) + (minutes * 0.5);
            this.minuteDeg = (minutes * 6) + (seconds * 0.1);
            this.secondDeg = seconds * 6;

            // Formatted 12-hour digital string
            const h12 = hours === 0 ? 12 : hours;
            const ampm = pp.hour >= 12 ? 'PM' : 'AM';
            const pad = (n) => String(n).padStart(2, '0');
            this.timeStr = `${h12}:${pad(minutes)}:${pad(seconds)} ${ampm}`;

            const dateFmt = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Phnom_Penh',
                weekday: 'short', month: 'short', day: 'numeric'
            });
            this.dateStr = dateFmt.format(now);
        },

        checkTimeTrigger() {
            const pp = this.getPhnomPenhParts();
            const isWeekday = pp.dayOfWeek >= 1 && pp.dayOfWeek <= 5;
            const isSaturday = pp.dayOfWeek === 6;

            // Auto-close check:
            // 1. Lunch (Mon-Fri): When past 12:10 PM (or not weekday), auto-close!
            if (this.isOpen && this.mode === 'lunch' && !this.isTestModal) {
                if (pp.hour !== 12 || pp.minute >= 10 || !isWeekday) {
                    this.close(true, 'lunch');
                    try { sessionStorage.removeItem('dgt_active_alarm_modal'); } catch (e) {}
                    return;
                }
            }

            // 2. Off Work (Mon-Fri): When past 4:10 PM (or not weekday), auto-close!
            if (this.isOpen && this.mode === 'offwork' && !this.isTestModal) {
                if (pp.hour !== 16 || pp.minute >= 10 || !isWeekday) {
                    this.close(true, 'offwork');
                    try { sessionStorage.removeItem('dgt_active_alarm_modal'); } catch (e) {}
                    return;
                }
            }

            // 3. Saturday Half Day: When past 11:10 AM (or not Saturday), auto-close!
            if (this.isOpen && this.mode === 'sat_offwork' && !this.isTestModal) {
                if (((pp.hour !== 11 && pp.hour !== 23) || pp.minute >= 10) || !isSaturday) {
                    this.close(true, 'sat_offwork');
                    try { sessionStorage.removeItem('dgt_active_alarm_modal'); } catch (e) {}
                    return;
                }
            }

            if (!this.userEnabled) return;

            // Monday to Friday:
            if (isWeekday) {
                // 1. 12:00 PM (12:00 - 12:09 window)
                if (pp.hour === 12 && pp.minute < 10) {
                    const dismissed = localStorage.getItem('dgt_alarm_dismissed_lunch_' + pp.dateKey);
                    if (!dismissed) {
                        this.mode = 'lunch';
                        this.isOpen = true;
                        const rung = localStorage.getItem('dgt_alarm_rung_lunch_' + pp.dateKey);
                        if (!rung) {
                            localStorage.setItem('dgt_alarm_rung_lunch_' + pp.dateKey, 'true');
                            this.triggerAlarm(false, 'lunch');
                        }
                    }
                }

                // 2. 4:00 PM (16:00 - 16:09 window)
                if (pp.hour === 16 && pp.minute < 10) {
                    const dismissed = localStorage.getItem('dgt_alarm_dismissed_offwork_' + pp.dateKey);
                    if (!dismissed) {
                        this.mode = 'offwork';
                        this.isOpen = true;
                        const rung = localStorage.getItem('dgt_alarm_rung_offwork_' + pp.dateKey);
                        if (!rung) {
                            localStorage.setItem('dgt_alarm_rung_offwork_' + pp.dateKey, 'true');
                            this.triggerAlarm(false, 'offwork');
                        }
                    }
                }
            }
            // Saturday Half Day (11:00 AM):
            else if (isSaturday) {
                if ((pp.hour === 11 || pp.hour === 23) && pp.minute < 10) {
                    const dismissed = localStorage.getItem('dgt_alarm_dismissed_sat11_' + pp.dateKey);
                    if (!dismissed) {
                        this.mode = 'sat_offwork';
                        this.isOpen = true;
                        const rung = localStorage.getItem('dgt_alarm_rung_sat11_' + pp.dateKey);
                        if (!rung) {
                            localStorage.setItem('dgt_alarm_rung_sat11_' + pp.dateKey, 'true');
                            this.triggerAlarm(false, 'sat_offwork');
                        }
                    }
                }
            }
            // Sunday: off
        },

        checkMeetingTriggers() {
            if (!this.upcomingMeetings || this.upcomingMeetings.length === 0) return;

            // Calculate current Phnom Penh wall-clock Date object
            const nowPP = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Phnom_Penh' }));

            this.upcomingMeetings.forEach(meeting => {
                const storageKey = 'dgt_meeting_alarm_' + meeting.id;
                if (localStorage.getItem(storageKey)) return;

                // meeting_time is formatted as "YYYY-MM-DD HH:mm:ss" in Phnom Penh timezone
                const meetingDate = new Date(meeting.meeting_time.replace(' ', 'T'));
                const diffSeconds = (nowPP.getTime() - meetingDate.getTime()) / 1000;

                // Trigger if within 0 to 180 seconds (up to 3 minutes after scheduled start)
                if (diffSeconds >= 0 && diffSeconds < 180) {
                    localStorage.setItem(storageKey, 'triggered');
                    this.triggerMeetingAlert(meeting, false);
                }
            });
        },

        triggerMeetingAlert(meeting, isTest = false) {
            this.mode = 'meeting';
            this.meetingData = {
                id: meeting.id || 0,
                title: meeting.title || 'Team Meeting',
                description: meeting.description || '',
                link: meeting.meeting_link || '',
                sound: meeting.sound || 'funny.wav',
                sound_url: meeting.sound_url || '{{ asset('clocksound/funny.wav') }}',
                ring_duration: meeting.ring_duration || 15
            };

            this.isOpen = true;
            this.updateClock();

            // Set sound src to meeting's designated sound
            const audio = document.getElementById('lunch-alarm-sound');
            if (audio) {
                audio.src = this.meetingData.sound_url;
                audio.load();
            }

            const duration = this.meetingData.ring_duration || 15;
            this.playRingtone(duration);

            // Notify in background tab:
            // 1. Flash document title
            this.startTitleFlashing('🔔 Meeting Time: ' + this.meetingData.title);

            // 2. Desktop Notification if tab is hidden/in background
            this.sendDesktopNotification('🔔 Meeting Time!', this.meetingData.title + (this.meetingData.description ? ' - ' + this.meetingData.description : ''));

            // 3. Vibrate device
            try {
                if ('vibrate' in navigator) {
                    navigator.vibrate([400, 200, 400, 200, 400]);
                }
            } catch (e) {}
        },

        triggerAlarm(isTest = false, targetMode = 'lunch') {
            this.mode = targetMode;
            this.isTestModal = isTest;
            this.isOpen = true;
            this.updateClock();

            // Persist in sessionStorage only during real alarm window so it stays visible across page navigation
            if (!isTest) {
                const pp = this.getPhnomPenhParts();
                try {
                    sessionStorage.setItem('dgt_active_alarm_modal', JSON.stringify({
                        mode: targetMode,
                        dateKey: pp.dateKey
                    }));
                } catch (e) {}
            } else {
                // For preview / test mode, auto-close after 15 seconds so test modal doesn't persist forever
                if (this.testAutoCloseTimeout) clearTimeout(this.testAutoCloseTimeout);
                this.testAutoCloseTimeout = setTimeout(() => {
                    if (this.isOpen && this.isTestModal) {
                        this.close(false);
                    }
                }, 15000);
            }

            // Broadcast to other open tabs in the system
            if (this.broadcastChannel) {
                try {
                    this.broadcastChannel.postMessage({
                        type: 'trigger',
                        mode: targetMode
                    });
                } catch (e) {}
            }

            // Set sound src to matching alarm mode
            this.setAudioSource(targetMode);

            // Play ringtone for configured duration (15s default), then sound automatically stops while pop-up remains open
            const duration = this.shiftAlarmDuration || 15;
            this.playRingtone(duration);

            const title = targetMode === 'lunch' ? '🍽️ It\'s Lunch Time!' : '🎉 Getting Off Work!';
            const body = targetMode === 'lunch' ? 'Time for lunch break!' : 'Workday is complete. Great job today!';

            this.startTitleFlashing(title);
            this.sendDesktopNotification(title, body);

            try {
                if ('vibrate' in navigator) {
                    navigator.vibrate([300, 150, 300, 150, 300]);
                }
            } catch (e) {}
        },

        playRingtone(durationSeconds = null) {
            const finalDuration = durationSeconds || this.shiftAlarmDuration || 15;
            this.isRinging = true;
            this.autoplayBlocked = false;
            this.ringCountdown = finalDuration;

            // Ensure correct sound is loaded for current mode
            this.setAudioSource(this.mode);

            const audio = document.getElementById('lunch-alarm-sound');
            if (audio) {
                audio.currentTime = 0;
                audio.volume = 1.0;
                audio.muted = false;
                audio.loop = true; // Ensure continuous ring for full duration
                const playPromise = audio.play();
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        this.autoplayBlocked = false;
                    }).catch(e => {
                        console.log('Autoplay blocked audio element, using Web Audio chime fallback:', e);
                        this.autoplayBlocked = true;
                        this.playWebAudioBeep(finalDuration * 1000);
                    });
                }
            } else {
                this.playWebAudioBeep(finalDuration * 1000);
            }

            // Countdown timer: ticks down every second
            if (this.countdownTimer) clearInterval(this.countdownTimer);
            this.countdownTimer = setInterval(() => {
                if (this.ringCountdown > 1) {
                    this.ringCountdown--;
                } else {
                    this.stopRingtone();
                }
            }, 1000);

            // Stop audio timeout after finalDuration
            // Note: stopRingtone() mutes and stops sound, while this.isOpen remains true so pop-up still shows!
            if (this.stopAudioTimeout) clearTimeout(this.stopAudioTimeout);
            this.stopAudioTimeout = setTimeout(() => {
                this.stopRingtone();
            }, finalDuration * 1000);
        },

        playWebAudioBeep(durationMs = null) {
            const finalMs = durationMs || (this.shiftAlarmDuration || 15) * 1000;
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                const ctx = window.__dgtAudioContext || new AudioContext();
                if (ctx.state === 'suspended') {
                    ctx.resume();
                }
                window.__dgtAudioContext = ctx;
                const now = ctx.currentTime;
                const bursts = Math.max(3, Math.round(finalMs / 1000));
                
                for (let b = 0; b < bursts; b++) {
                    const burstStart = now + b * 1.0;
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(1760, burstStart);
                    gain.gain.setValueAtTime(0.3, burstStart);
                    gain.gain.exponentialRampToValueAtTime(0.001, burstStart + 0.6);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(burstStart);
                    osc.stop(burstStart + 0.6);
                }
            } catch (err) {
                console.log('Web Audio chime error:', err);
            }
        },

        startTitleFlashing(alertText) {
            this.stopTitleFlashing();
            let toggle = false;
            this.titleFlashInterval = setInterval(() => {
                document.title = toggle ? alertText : this.originalDocumentTitle;
                toggle = !toggle;
            }, 800);
        },

        stopTitleFlashing() {
            if (this.titleFlashInterval) {
                clearInterval(this.titleFlashInterval);
                this.titleFlashInterval = null;
            }
            if (this.originalDocumentTitle) {
                document.title = this.originalDocumentTitle;
            }
        },

        sendDesktopNotification(title, body) {
            try {
                if ('Notification' in window && Notification.permission === 'granted') {
                    const n = new Notification(title, {
                        body: body,
                        icon: '/favicon.ico',
                        tag: 'dgt-workplace-alarm',
                        requireInteraction: true
                    });
                    n.onclick = () => {
                        window.focus();
                        n.close();
                    };
                }
            } catch (e) {}
        },

        stopRingtone() {
            this.isRinging = false;
            this.autoplayBlocked = false;
            if (this.countdownTimer) {
                clearInterval(this.countdownTimer);
                this.countdownTimer = null;
            }
            if (this.stopAudioTimeout) {
                clearTimeout(this.stopAudioTimeout);
                this.stopAudioTimeout = null;
            }
            const audio = document.getElementById('lunch-alarm-sound');
            if (audio) {
                audio.loop = false;
                audio.pause();
                audio.currentTime = 0;
            }
            // CRITICAL: this.isOpen remains true!
            // The pop up still shows on screen until the user manually clicks Close / Got It.
        },

        close(shouldBroadcast = true, forceMode = null) {
            const currentMode = forceMode || this.mode;
            this.stopRingtone();
            this.stopTitleFlashing();
            this.isOpen = false;
            this.isTestModal = false;
            if (this.testAutoCloseTimeout) {
                clearTimeout(this.testAutoCloseTimeout);
                this.testAutoCloseTimeout = null;
            }
            try {
                sessionStorage.removeItem('dgt_active_alarm_modal');
            } catch (e) {}

            const pp = this.getPhnomPenhParts();

            // Record dismissal for today so it doesn't pop up again once dismissed
            if (currentMode === 'lunch') {
                localStorage.setItem('dgt_alarm_dismissed_lunch_' + pp.dateKey, 'true');
            } else if (currentMode === 'offwork') {
                localStorage.setItem('dgt_alarm_dismissed_offwork_' + pp.dateKey, 'true');
            } else if (currentMode === 'sat_offwork') {
                localStorage.setItem('dgt_alarm_dismissed_sat11_' + pp.dateKey, 'true');
            } else if (currentMode === 'meeting' && this.meetingData && this.meetingData.id) {
                localStorage.setItem('dgt_meeting_alarm_' + this.meetingData.id, 'dismissed');
            }

            try {
                sessionStorage.removeItem('dgt_active_alarm_modal');
            } catch (e) {}

            if (shouldBroadcast && this.broadcastChannel) {
                try {
                    this.broadcastChannel.postMessage({ type: 'close', mode: currentMode });
                } catch (e) {}
            }

            // Restore audio default source if meeting ended
            const audio = document.getElementById('lunch-alarm-sound');
            if (audio && audio.dataset.defaultSrc) {
                audio.src = audio.dataset.defaultSrc;
            }
        }
    }));
}
</script>
