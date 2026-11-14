@auth
<div id="dgt-global-webrtc-root"
     x-data="dgtGlobalWebRtcOverlay()"
     @dgt-webrtc-state.window="applyState($event.detail)"
     @pointermove.window="movePipDrag($event)"
     @pointerup.window="endPipDrag()"
     class="select-none">

    {{-- Persistent WebRTC Remote Audio Sink --}}
    <audio id="dgt-global-remote-audio" autoplay playsinline class="fixed -left-[9999px] w-px h-px opacity-0 pointer-events-none"></audio>

    {{-- 1. INCOMING VOICE / VIDEO CALL OVERLAY (Apple iOS 26 Liquid Glass) --}}
    <div x-show="active && !isCaller && status === 'ringing'"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-180"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         style="z-index: 100020; background-color: rgba(2, 6, 23, 0.86); backdrop-filter: blur(28px); -webkit-backdrop-filter: blur(28px); padding-top: max(1.25rem, calc(var(--dgt-safe-top, env(safe-area-inset-top, 0px)) + 0.75rem)); padding-bottom: max(1.5rem, calc(var(--dgt-safe-bottom, env(safe-area-inset-bottom, 0px)) + 1rem));"
         class="fixed inset-0 flex items-center justify-center px-4 bg-slate-950/80 backdrop-blur-2xl"
         x-cloak>

        <div style="background-color: rgba(15, 23, 42, 0.88); border-radius: 2rem;"
             class="relative w-full max-w-sm rounded-[2rem] border border-white/20 bg-slate-900/75 p-7 text-center text-white shadow-[0_32px_90px_rgba(0,0,0,0.65)] backdrop-blur-3xl overflow-hidden">
            <div class="pointer-events-none absolute -top-24 -left-20 h-52 w-52 rounded-full bg-emerald-500/25 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 -right-20 h-52 w-52 rounded-full bg-indigo-500/25 blur-3xl"></div>

            {{-- Top Quick Mute Ringtone Button --}}
            <div class="relative z-10 flex items-center justify-between mb-3">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[11px] font-semibold tracking-wide text-emerald-300">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span x-text="isVideo ? 'Incoming Video Call' : 'Incoming Voice Call'"></span>
                </span>
                <button type="button"
                        @click="muteRingtone()"
                        class="inline-flex items-center gap-1 rounded-full border border-white/15 bg-white/10 hover:bg-white/20 px-2.5 py-1 text-[11px] font-semibold text-white/85 transition cursor-pointer"
                        title="Silence Ringtone">
                    <span x-text="ringtoneMuted ? 'Silenced' : 'Mute Ring'"></span>
                </button>
            </div>

            {{-- Caller Avatar with Pulsing Aura --}}
            <div class="relative mx-auto my-5 h-24 w-24">
                <span class="animate-ping absolute inset-0 rounded-full bg-emerald-400/35"></span>
                <img :src="contactAvatar"
                     :alt="contactName"
                     class="relative h-24 w-24 rounded-full object-cover ring-4 ring-white/25 shadow-2xl">
            </div>

            <h3 class="text-xl font-bold tracking-tight text-white truncate" x-text="contactName"></h3>
            <p class="mt-1 text-xs font-medium text-slate-300" x-text="isVideo ? 'KIUQ HD Video Call' : 'KIUQ HD Audio Call'"></p>

            {{-- Decline / Accept Voice / Accept Video --}}
            <div class="relative z-10 mt-8 flex items-center justify-center gap-6">
                <div class="flex flex-col items-center gap-2">
                    <button type="button"
                            @click="rejectCall()"
                            class="h-14 w-14 rounded-full bg-rose-600 hover:bg-rose-500 text-white flex items-center justify-center shadow-lg shadow-rose-600/40 active:scale-95 transition cursor-pointer"
                            aria-label="Decline Call">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2.3" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <span class="text-[11px] font-semibold text-slate-300">Decline</span>
                </div>

                <div class="flex flex-col items-center gap-2">
                    <button type="button"
                            @click="acceptVoice()"
                            class="h-14 w-14 rounded-full bg-emerald-500 hover:bg-emerald-400 text-white flex items-center justify-center shadow-lg shadow-emerald-500/40 active:scale-95 transition cursor-pointer"
                            aria-label="Accept Voice Call">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                        </svg>
                    </button>
                    <span class="text-[11px] font-semibold text-emerald-300" x-text="isVideo ? 'Audio Only' : 'Accept'"></span>
                </div>

                <template x-if="isVideo">
                    <div class="flex flex-col items-center gap-2">
                        <button type="button"
                                @click="acceptVideo()"
                                class="h-14 w-14 rounded-full bg-indigo-500 hover:bg-indigo-400 text-white flex items-center justify-center shadow-lg shadow-indigo-500/40 active:scale-95 transition cursor-pointer"
                                aria-label="Accept Video Call">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </button>
                        <span class="text-[11px] font-semibold text-indigo-300">Video</span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- 2. OUTGOING / CONNECTING / ACTIVE CALL SCREEN --}}
    <div x-show="active && !minimized && (isCaller || status !== 'ringing')"
         @click="if (audioBlocked) unblockAudio()"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-180"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         style="z-index: 100020; background-color: rgba(2, 6, 23, 0.9); backdrop-filter: blur(28px); -webkit-backdrop-filter: blur(28px); padding-top: max(1rem, calc(var(--dgt-safe-top, env(safe-area-inset-top, 0px)) + 0.5rem)); padding-bottom: max(1.25rem, calc(var(--dgt-safe-bottom, env(safe-area-inset-bottom, 0px)) + 0.75rem));"
         class="fixed inset-0 flex items-center justify-center px-3 sm:px-6 bg-slate-950/90 backdrop-blur-2xl"
         x-cloak>

        <div :class="(remoteVideoActive || isCameraOn) ? 'max-w-4xl h-[82dvh] sm:h-[80dvh]' : 'max-w-sm py-8'"
             style="background-color: rgba(15, 23, 42, 0.88); border-radius: 2rem;"
             class="relative w-full rounded-[2rem] border border-white/20 bg-slate-900/80 px-6 py-5 text-white shadow-[0_32px_90px_rgba(0,0,0,0.7)] backdrop-blur-3xl flex flex-col justify-between overflow-hidden transition-all duration-300">

            {{-- Remote Full-Bleed Video --}}
            <video id="dgt-global-remote-video"
                   autoplay
                   playsinline
                   webkit-playsinline
                   x-show="remoteVideoActive"
                   class="absolute inset-0 h-full w-full object-cover bg-slate-950 z-0"></video>

            {{-- Local Full-Bleed Preview (while waiting for remote camera) --}}
            <video id="dgt-global-local-main-video"
                   autoplay
                   playsinline
                   webkit-playsinline
                   muted
                   x-show="!remoteVideoActive && isCameraOn"
                   :class="facingMode === 'user' ? '-scale-x-100' : ''"
                   class="absolute inset-0 h-full w-full object-cover bg-slate-950 z-0"></video>

            {{-- Readability Gradient Scrim over Video --}}
            <div x-show="remoteVideoActive || isCameraOn"
                 class="pointer-events-none absolute inset-0 bg-gradient-to-b from-slate-950/75 via-transparent to-slate-950/85 z-0"></div>

            {{-- Top Bar: Status Badge + Minimize Button --}}
            <div class="relative z-20 flex items-center justify-between gap-2">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/35 px-3 py-1 text-[11px] font-semibold backdrop-blur-md">
                    <span class="h-2 w-2 rounded-full"
                          :class="{
                              'bg-emerald-400': status === 'connected' && networkQuality === 'good',
                              'bg-amber-400 animate-pulse': status === 'calling' || status === 'ringing' || status === 'connecting' || status === 'reconnecting' || networkQuality === 'fair',
                              'bg-rose-500': status === 'permission_error' || networkQuality === 'poor'
                          }"></span>
                    <span x-text="{
                        'calling': isVideo ? 'Calling (Video)...' : 'Calling...',
                        'ringing': 'Ringing...',
                        'connecting': 'Connecting...',
                        'connected': (remoteVideoActive || isCameraOn) ? 'HD Video' : 'HD Voice',
                        'reconnecting': 'Reconnecting...',
                        'permission_error': 'Permission Needed',
                        'idle': 'Call'
                    }[status] || 'Calling...'"></span>
                </div>

                <div class="flex items-center gap-2">
                    <template x-if="remoteMuted && status === 'connected'">
                        <span class="rounded-full bg-amber-500/25 border border-amber-400/30 px-2.5 py-1 text-[10px] font-semibold text-amber-200">
                            Peer Muted
                        </span>
                    </template>
                    <button type="button"
                            x-show="status !== 'permission_error'"
                            @click="toggleMinimize()"
                            class="h-9 w-9 rounded-full border border-white/15 bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition cursor-pointer"
                            title="Minimize Call">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Browser Autoplay Audio Unblock Banner --}}
            <div x-show="audioBlocked"
                 class="relative z-20 mt-2 flex items-center justify-between gap-2 rounded-2xl bg-amber-500/95 px-3.5 py-2 text-xs font-semibold text-slate-950 shadow-lg">
                <span>Tap to enable call audio output</span>
                <button type="button" @click="unblockAudio()" class="rounded-xl bg-slate-950 px-3 py-1 text-white font-bold cursor-pointer">
                    Enable Audio
                </button>
            </div>

            {{-- Center Identity / Permission Card --}}
            <div class="relative z-10 my-auto flex flex-col items-center text-center py-4">
                <template x-if="status === 'permission_error'">
                    <div class="max-w-xs rounded-2xl border border-rose-400/30 bg-slate-950/80 p-5 backdrop-blur-xl">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-rose-500/20 text-rose-400">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <h4 class="text-sm font-bold text-white">Microphone / Camera Access</h4>
                        <p class="mt-1.5 text-xs text-slate-300 leading-relaxed" x-text="permissionErrorMessage"></p>
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                            <button type="button"
                                    @click="retryCall()"
                                    class="rounded-xl bg-indigo-500 hover:bg-indigo-400 px-3.5 py-2 text-xs font-bold text-white cursor-pointer">
                                Retry Call
                            </button>
                            <template x-if="isVideo">
                                <button type="button"
                                        @click="retryAudioOnly()"
                                        class="rounded-xl bg-emerald-500 hover:bg-emerald-400 px-3.5 py-2 text-xs font-bold text-white cursor-pointer">
                                    Audio Only
                                </button>
                            </template>
                            <button type="button"
                                    @click="endCall()"
                                    class="rounded-xl bg-white/15 hover:bg-white/25 px-3.5 py-2 text-xs font-semibold text-white cursor-pointer">
                                Close
                            </button>
                        </div>
                    </div>
                </template>

                <template x-if="status !== 'permission_error'">
                    <div class="flex flex-col items-center">
                        <div x-show="!remoteVideoActive && !isCameraOn" class="relative mb-4">
                            <span x-show="status === 'connected'" class="animate-pulse absolute -inset-1.5 rounded-full bg-emerald-500/30"></span>
                            <img :src="contactAvatar"
                                 :alt="contactName"
                                 class="relative h-24 w-24 rounded-full object-cover ring-4 ring-white/20 shadow-2xl">
                        </div>
                        <h3 class="text-xl font-bold tracking-tight text-white drop-shadow" x-text="contactName"></h3>
                        <p x-show="status === 'connected'"
                           class="mt-1 font-mono text-lg font-bold tracking-wider text-emerald-300 drop-shadow"
                           x-text="formatDuration(duration)">00:00</p>
                    </div>
                </template>
            </div>

            {{-- Draggable Local Camera PiP Window (when both local & remote video are active) --}}
            <div x-show="remoteVideoActive && isCameraOn"
                 @pointerdown="startPipDrag($event)"
                 :style="`transform: translate(${pipX}px, ${pipY}px); touch-action: none;`"
                 class="absolute bottom-24 right-4 z-20 w-28 sm:w-36 aspect-[3/4] sm:aspect-video rounded-2xl overflow-hidden border-2 border-white/35 bg-slate-950 shadow-2xl cursor-grab active:cursor-grabbing">
                <video id="dgt-global-local-pip-video"
                       muted
                       autoplay
                       playsinline
                       webkit-playsinline
                       :class="facingMode === 'user' ? '-scale-x-100' : ''"
                       class="h-full w-full object-cover"></video>
            </div>

            {{-- Bottom Liquid Glass Control Capsule --}}
            <div x-show="status !== 'permission_error'"
                 class="relative z-20 mx-auto flex items-center justify-center gap-3 sm:gap-4 rounded-full border border-white/20 bg-white/10 px-4 py-2.5 backdrop-blur-2xl shadow-2xl">

                {{-- Mute / Unmute Mic --}}
                <button type="button"
                        @click="toggleMute()"
                        :class="isMuted ? 'bg-amber-500 text-white' : 'bg-white/15 hover:bg-white/25 text-white'"
                        class="h-12 w-12 rounded-full flex items-center justify-center transition active:scale-95 cursor-pointer"
                        :title="isMuted ? 'Unmute Microphone' : 'Mute Microphone'">
                    <svg x-show="!isMuted" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                    </svg>
                    <svg x-show="isMuted" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 9.75 19.5 12m0 0 2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25m-10.5-6 4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H9Z" />
                    </svg>
                </button>

                {{-- Camera On / Off --}}
                <button type="button"
                        @click="toggleCamera()"
                        :class="isCameraOn ? 'bg-indigo-500 text-white' : 'bg-white/15 hover:bg-white/25 text-white'"
                        class="h-12 w-12 rounded-full flex items-center justify-center transition active:scale-95 cursor-pointer"
                        :title="isCameraOn ? 'Turn Camera Off' : 'Turn Camera On'">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </button>

                {{-- Switch Front / Back Camera --}}
                <button type="button"
                        x-show="isCameraOn && canSwitchCamera"
                        @click="switchCamera()"
                        class="h-12 w-12 rounded-full bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition active:scale-95 cursor-pointer"
                        title="Switch Camera">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </button>

                {{-- Speaker / Audio Output --}}
                <button type="button"
                        @click="toggleSpeaker()"
                        :class="speakerOn ? 'bg-white/25 text-white' : 'bg-white/10 text-white/70'"
                        class="h-12 w-12 rounded-full flex items-center justify-center transition active:scale-95 cursor-pointer"
                        title="Toggle Speaker Output">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                    </svg>
                </button>

                {{-- End / Hangup Call --}}
                <button type="button"
                        @click="endCall()"
                        class="h-12 w-14 rounded-full bg-rose-600 hover:bg-rose-500 text-white flex items-center justify-center shadow-lg shadow-rose-600/40 transition active:scale-95 cursor-pointer"
                        title="End Call">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2.3" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- 3. FLOATING MINIMIZED LIQUID GLASS CALL PILL --}}
    <div x-show="active && minimized"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         style="z-index: 100025; top: max(1rem, calc(var(--dgt-safe-top, env(safe-area-inset-top, 0px)) + 0.5rem));"
         class="fixed right-4 rounded-full border border-white/25 bg-slate-900/90 px-3 py-2 text-white shadow-2xl backdrop-blur-2xl flex items-center gap-2.5"
         x-cloak>
        <img :src="contactAvatar"
             :alt="contactName"
             @click="toggleMinimize()"
             class="h-9 w-9 rounded-full object-cover ring-2 ring-emerald-400 cursor-pointer shrink-0">
        <div class="min-w-0 pr-1 cursor-pointer" @click="toggleMinimize()">
            <p class="text-xs font-bold truncate max-w-[110px]" x-text="contactName"></p>
            <p class="text-[10px] font-mono font-semibold text-emerald-400 flex items-center gap-1">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span x-text="status === 'connected' ? formatDuration(duration) : 'Calling...'"></span>
            </p>
        </div>
        <button type="button"
                @click="toggleMute()"
                :class="isMuted ? 'bg-amber-500 text-white' : 'bg-white/15 text-white'"
                class="h-8 w-8 rounded-full flex items-center justify-center cursor-pointer"
                title="Mute/Unmute">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
            </svg>
        </button>
        <button type="button"
                @click="endCall()"
                class="h-8 w-8 rounded-full bg-rose-600 hover:bg-rose-500 text-white flex items-center justify-center cursor-pointer"
                title="End Call">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.3" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>
@endauth
