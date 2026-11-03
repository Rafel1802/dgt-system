@extends('layouts.auth')

@section('title', 'Two-Factor Authentication')

@section('content')
@php
    $appIcon = file_exists(public_path('storage/favicon.svg')) ? asset('storage/favicon.svg') : asset('favicon.svg');
@endphp
<style>
    .challenge-shell {
        position: relative;
        min-height: 100vh;
        overflow: hidden;
        background: #07142e;
        color: #f8fbff;
        isolation: isolate;
    }

    [data-theme="dark"] .challenge-shell {
        background: #02040a !important;
    }

    .challenge-backdrop {
        position: absolute;
        inset: 0;
        z-index: -3;
        background-image: url("{{ asset('bg-light.webp') }}");
        background-position: center;
        background-size: cover;
        background-repeat: no-repeat;
        filter: blur(5px) brightness(0.78) saturate(1.22);
        transform: scale(1.035);
    }

    [data-theme="dark"] .challenge-backdrop {
        filter: blur(3px) brightness(0.55) saturate(1.1) !important;
    }

    .challenge-shell::before {
        content: "";
        position: absolute;
        inset: 0;
        z-index: -2;
        background:
            radial-gradient(circle at 17% 18%, rgba(59, 130, 246, 0.28), transparent 30%),
            radial-gradient(circle at 82% 22%, rgba(34, 211, 238, 0.24), transparent 28%),
            radial-gradient(circle at 63% 84%, rgba(99, 102, 241, 0.3), transparent 38%),
            linear-gradient(90deg, rgba(2, 6, 23, 0.4), rgba(2, 10, 34, 0.2) 45%, rgba(4, 12, 38, 0.4));
    }

    .challenge-card {
        background: rgba(9, 27, 72, 0.72);
        border: 1px solid rgba(117, 205, 255, 0.4);
        box-shadow: 0 28px 90px rgba(0, 0, 0, 0.65), 0 0 40px rgba(56, 189, 248, 0.15);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
    }

    [data-theme="dark"] .challenge-card {
        background: rgba(2, 4, 8, 0.88);
        border: 1px solid rgba(56, 189, 248, 0.35);
        box-shadow: 0 28px 90px rgba(0, 0, 0, 0.85), 0 0 35px rgba(56, 189, 248, 0.12);
    }

    .pin-input {
        background: rgba(12, 32, 82, 0.55);
        border: 1px solid rgba(165, 218, 255, 0.35);
        color: #ffffff;
        transition: all 0.2s ease;
    }

    [data-theme="dark"] .pin-input {
        background: rgba(0, 0, 0, 0.65);
        border-color: rgba(255, 255, 255, 0.15);
    }

    .pin-input:focus {
        border-color: rgba(56, 189, 248, 0.85);
        box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.2);
    }

    .btn-submit {
        background: linear-gradient(135deg, #0284c7 0%, #2563eb 50%, #4f46e5 100%);
        box-shadow: 0 10px 30px rgba(37, 99, 235, 0.4);
        transition: all 0.25s ease;
    }

    .btn-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 36px rgba(37, 99, 235, 0.55);
        filter: brightness(1.08);
    }
</style>

<div class="challenge-shell flex min-h-screen items-center justify-center p-4 sm:p-6"
     x-data="{
         recoveryMode: false,
         code: '',
         recoveryCode: '',
         submitting: false,
         init() {
             this.$nextTick(() => {
                 this.$refs.codeInput?.focus();
             });
         },
         toggleRecovery() {
             this.recoveryMode = !this.recoveryMode;
             this.$nextTick(() => {
                 if (this.recoveryMode) {
                     this.$refs.recoveryInput?.focus();
                 } else {
                     this.$refs.codeInput?.focus();
                 }
             });
         },
         handleCodeInput(e) {
             let val = e.target.value.replace(/[^0-9]/g, '');
             if (val.length > 6) val = val.substring(0, 6);
             this.code = val;
             if (val.length === 6) {
                 this.$refs.form.submit();
             }
         }
     }">
    <div class="challenge-backdrop"></div>

    <div class="relative w-full max-w-[460px]">
        <div class="challenge-card rounded-[2.25rem] p-7 sm:p-10">

            {{-- Brand & Header --}}
            <div class="text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 p-2 shadow-inner ring-1 ring-white/20">
                    <img src="{{ $appIcon }}" alt="Logo" class="h-10 w-10 object-contain">
                </div>

                <div class="mt-5 inline-flex items-center gap-1.5 rounded-full bg-cyan-500/15 border border-cyan-400/30 px-3 py-1 text-xs font-bold text-cyan-300">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                    Two-Factor Authentication
                </div>

                <h1 class="mt-3 text-2xl font-black text-white sm:text-3xl">
                    Security Check
                </h1>

                <p class="mt-2 text-xs sm:text-sm text-sky-100/75 leading-relaxed" x-show="!recoveryMode">
                    Open your <strong class="text-white">authenticator app</strong> (Google Authenticator, Microsoft Authenticator, etc.) and enter the 6-digit code for <strong class="text-cyan-300">{{ $maskedEmail }}</strong>.
                </p>

                <p class="mt-2 text-xs sm:text-sm text-sky-100/75 leading-relaxed" x-show="recoveryMode" x-cloak>
                    Enter one of your emergency recovery codes to access your account.
                </p>
            </div>

            {{-- Error Alerts --}}
            @if ($errors->any())
                <div class="mt-6 rounded-2xl bg-rose-500/20 border border-rose-500/40 p-4 text-xs font-bold text-rose-200" role="alert">
                    <div class="flex items-center gap-2.5">
                        <svg class="h-4 w-4 flex-shrink-0 text-rose-300" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('login.2fa.verify') }}" x-ref="form" @submit="submitting = true" class="mt-6 space-y-5">
                @csrf

                {{-- Authenticator Code Input --}}
                <div x-show="!recoveryMode">
                    <label for="code" class="block text-xs font-bold uppercase tracking-wider text-sky-100/90 mb-2 text-center">
                        6-Digit Security PIN
                    </label>
                    <input
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        id="code"
                        name="code"
                        x-ref="codeInput"
                        :value="code"
                        @input="handleCodeInput"
                        placeholder="000 000"
                        maxlength="6"
                        autocomplete="one-time-code"
                        class="pin-input h-14 w-full rounded-2xl text-center font-mono text-2xl sm:text-3xl font-black tracking-[0.35em] placeholder-slate-500/60 outline-none"
                    >
                </div>

                {{-- Recovery Code Input --}}
                <div x-show="recoveryMode" x-cloak>
                    <label for="recovery_code" class="block text-xs font-bold uppercase tracking-wider text-sky-100/90 mb-2">
                        Emergency Recovery Code
                    </label>
                    <input
                        type="text"
                        id="recovery_code"
                        name="recovery_code"
                        x-ref="recoveryInput"
                        x-model="recoveryCode"
                        placeholder="XXXXXXXXXX-XXXXXXXXXX"
                        autocomplete="off"
                        class="pin-input h-14 w-full rounded-2xl px-4 text-center font-mono text-base font-bold tracking-wider placeholder-slate-500/60 outline-none"
                    >
                </div>

                {{-- Submit Button --}}
                <button
                    type="submit"
                    class="btn-submit flex h-13 w-full items-center justify-center rounded-2xl text-base font-black text-white cursor-pointer active:scale-[0.99]"
                    :disabled="submitting"
                >
                    <span x-show="!submitting" class="flex items-center gap-2">
                        <span>Verify & Sign In</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </span>
                    <span x-show="submitting" x-cloak class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Verifying...</span>
                    </span>
                </button>

                {{-- Recovery code switcher --}}
                <div class="pt-2 text-center">
                    <button
                        type="button"
                        @click="toggleRecovery()"
                        class="text-xs font-bold text-cyan-300 hover:text-white transition underline-offset-4 hover:underline cursor-pointer"
                    >
                        <span x-show="!recoveryMode">Don't have your phone? Use a recovery code</span>
                        <span x-show="recoveryMode" x-cloak>Use authenticator app PIN instead</span>
                    </button>
                </div>
            </form>

            {{-- Back to login --}}
            <div class="mt-6 pt-5 border-t border-white/10 text-center">
                <a href="{{ route('login') }}" class="text-xs font-bold text-sky-200/70 hover:text-white transition inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Cancel and return to sign in
                </a>
            </div>

        </div>
    </div>
</div>
@endsection
