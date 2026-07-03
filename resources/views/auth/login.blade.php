@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
@php($appIcon = file_exists(public_path('storage/favicon.svg')) ? asset('storage/favicon.svg') : asset('favicon.svg'))
<style>
    .login-shell {
        --login-bg: url("{{ asset('bg-light.png') }}");
        --login-text: #f8fbff;
        --login-muted: rgba(226, 238, 255, 0.78);
        --login-soft: rgba(187, 222, 255, 0.68);
        --glass-bg: rgba(9, 27, 72, 0.52);
        --glass-border: rgba(117, 205, 255, 0.64);
        --glass-highlight: rgba(255, 255, 255, 0.24);
        --field-bg: rgba(12, 32, 82, 0.38);
        --field-border: rgba(165, 218, 255, 0.28);
        --field-focus: rgba(56, 189, 248, 0.62);
        position: relative;
        min-height: 100vh;
        overflow: hidden;
        background: #07142e;
        color: var(--login-text);
        isolation: isolate;
    }

    [data-theme="dark"] .login-shell {
        --login-text: #ffffff;
        --login-muted: rgba(226, 238, 255, 0.82);
        --login-soft: rgba(148, 211, 255, 0.72);
        --glass-bg: rgba(2, 4, 8, 0.85);
        --glass-border: rgba(56, 189, 248, 0.45);
        --glass-highlight: rgba(255, 255, 255, 0.08);
        --field-bg: rgba(0, 0, 0, 0.55);
        --field-border: rgba(255, 255, 255, 0.08);
        --field-focus: rgba(56, 189, 248, 0.8);
        background: #02040a !important;
    }

    .login-backdrop {
        position: absolute;
        inset: -18px;
        z-index: -3;
        background-image: var(--login-bg);
        background-position: center;
        background-size: cover;
        background-repeat: no-repeat;
        filter: blur(5px) brightness(0.78) saturate(1.22);
        transform: scale(1.035);
        transition: filter 0.45s ease, background-image 0.45s ease;
    }

    [data-theme="dark"] .login-backdrop {
        filter: blur(3px) brightness(0.58) saturate(1.1) !important;
    }

    .login-shell::before {
        content: "";
        position: absolute;
        inset: 0;
        z-index: -2;
        background:
            radial-gradient(circle at 17% 18%, rgba(59, 130, 246, 0.26), transparent 28%),
            radial-gradient(circle at 82% 22%, rgba(34, 211, 238, 0.22), transparent 26%),
            radial-gradient(circle at 63% 84%, rgba(99, 102, 241, 0.28), transparent 36%),
            linear-gradient(90deg, rgba(2, 6, 23, 0.24), rgba(2, 10, 34, 0.1) 45%, rgba(4, 12, 38, 0.28));
    }

    [data-theme="dark"] .login-shell::before {
        background:
            radial-gradient(circle at 17% 18%, rgba(99, 102, 241, 0.15), transparent 28%),
            radial-gradient(circle at 82% 22%, rgba(14, 165, 233, 0.12), transparent 26%),
            radial-gradient(circle at 63% 84%, rgba(99, 102, 241, 0.15), transparent 36%),
            linear-gradient(180deg, rgba(2, 4, 10, 0.32), rgba(4, 8, 20, 0.38)) !important;
    }

    [data-theme="dark"] .login-card {
        box-shadow:
            0 28px 90px rgba(0, 0, 0, 0.8),
            0 0 0 1px rgba(255, 255, 255, 0.05) inset,
            0 0 30px rgba(56, 189, 248, 0.12),
            0 0 60px rgba(99, 102, 241, 0.08) !important;
        animation: border-glow 6s ease-in-out infinite alternate;
    }

    [data-theme="dark"] .ambient-orbit {
        border-color: rgba(56, 189, 248, 0.15) !important;
        box-shadow:
            0 0 100px rgba(14, 165, 233, 0.12),
            inset 0 0 100px rgba(99, 102, 241, 0.08) !important;
    }

    @keyframes border-glow {
        0% {
            border-color: rgba(56, 189, 248, 0.35);
            box-shadow: 0 28px 90px rgba(0, 0, 0, 0.8), 0 0 30px rgba(56, 189, 248, 0.12);
        }
        100% {
            border-color: rgba(99, 102, 241, 0.45);
            box-shadow: 0 28px 90px rgba(0, 0, 0, 0.8), 0 0 45px rgba(99, 102, 241, 0.18);
        }
    }

    .login-shell::after {
        content: "";
        position: absolute;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        background-image:
            linear-gradient(rgba(147, 197, 253, 0.055) 1px, transparent 1px),
            linear-gradient(90deg, rgba(147, 197, 253, 0.055) 1px, transparent 1px);
        background-size: 82px 82px;
        mask-image: linear-gradient(to bottom, transparent, black 12%, black 84%, transparent);
    }

    .ambient-orbit {
        position: absolute;
        inset: auto -12rem -16rem auto;
        width: min(42vw, 34rem);
        aspect-ratio: 1;
        border: 1px solid rgba(125, 211, 252, 0.28);
        border-radius: 999px;
        box-shadow:
            0 0 80px rgba(14, 165, 233, 0.26),
            inset 0 0 80px rgba(99, 102, 241, 0.14);
        animation: login-orbit 18s linear infinite;
        opacity: 0.72;
        pointer-events: none;
    }

    .login-theme-toggle {
        color: var(--login-text);
        background:
            linear-gradient(135deg, rgba(255, 255, 255, 0.22), rgba(125, 211, 252, 0.1)),
            rgba(4, 18, 49, 0.34);
        border: 1px solid rgba(172, 225, 255, 0.46);
        box-shadow:
            0 16px 40px rgba(3, 7, 18, 0.28),
            inset 0 1px 0 rgba(255, 255, 255, 0.32);
        backdrop-filter: blur(18px) saturate(150%);
        -webkit-backdrop-filter: blur(18px) saturate(150%);
    }

    /* ── Login Pill Toggle ─────────────────────────────────────────── */
    .login-pill-toggle {
        display: inline-flex;
        align-items: center;
        padding: 3px;
        width: 80px;
        height: 36px;
        border-radius: 999px;
        background: linear-gradient(135deg, rgba(255,255,255,0.18), rgba(125,211,252,0.12)), rgba(4,18,49,0.42);
        border: 1.5px solid rgba(172,225,255,0.5);
        box-shadow: 0 8px 24px rgba(3,7,18,0.28), inset 0 1px 0 rgba(255,255,255,0.24);
        backdrop-filter: blur(18px) saturate(150%);
        -webkit-backdrop-filter: blur(18px) saturate(150%);
        cursor: pointer;
        position: absolute !important;
        top: 1.5rem !important;
        right: 1.5rem !important;
        z-index: 50 !important;
        transition: border-color 0.35s ease, box-shadow 0.35s ease;
        user-select: none;
    }

    .login-pill-toggle:hover {
        border-color: rgba(172,225,255,0.75);
        box-shadow: 0 12px 32px rgba(3,7,18,0.36), inset 0 1px 0 rgba(255,255,255,0.32), 0 0 18px rgba(56,189,248,0.22);
    }

    .login-pill-icon {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: rgba(186,230,253,0.55);
        transition: color 0.3s ease;
        z-index: 1;
        position: relative;
    }

    .login-pill-icon.active {
        color: #fbbf24;
    }

    [data-theme="dark"] .login-pill-icon.active {
        color: #1e293b;
    }

    .login-pill-knob {
        position: absolute;
        top: 3px;
        left: 3px;
        width: calc(50% - 4px);
        height: calc(100% - 6px);
        border-radius: 999px;
        background: linear-gradient(135deg, #e0f2fe, #bfdbfe);
        box-shadow: 0 3px 8px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.9);
        transition: left 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.35s ease, box-shadow 0.35s ease;
        z-index: 0;
    }

    [data-theme="dark"] .login-pill-knob {
        left: calc(50% + 1px);
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        box-shadow: 0 3px 10px rgba(251,191,36,0.55), inset 0 1px 0 rgba(255,255,255,0.3);
    }

    .login-hero-pill,
    .login-metric,
    .login-card {
        backdrop-filter: blur(28px) saturate(165%);
        -webkit-backdrop-filter: blur(28px) saturate(165%);
    }

    .login-hero-pill {
        border: 1px solid rgba(188, 232, 255, 0.42);
        background: rgba(7, 22, 58, 0.4);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
    }

    .login-metric {
        border: 1px solid rgba(180, 225, 255, 0.28);
        background: linear-gradient(145deg, rgba(9, 31, 79, 0.4), rgba(78, 139, 255, 0.14));
        box-shadow:
            0 22px 60px rgba(2, 6, 23, 0.24),
            inset 0 1px 0 rgba(255, 255, 255, 0.18);
    }

    .login-card {
        position: relative;
        overflow: hidden;
        border: 1px solid var(--glass-border);
        background:
            linear-gradient(135deg, var(--glass-highlight), transparent 33%),
            linear-gradient(180deg, var(--glass-bg), rgba(12, 31, 78, 0.38));
        box-shadow:
            0 28px 90px rgba(1, 8, 29, 0.48),
            0 0 0 1px rgba(255, 255, 255, 0.06) inset,
            0 0 42px rgba(56, 189, 248, 0.26);
    }

    .login-card::before {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        border-radius: inherit;
        background:
            linear-gradient(90deg, transparent, rgba(125, 211, 252, 0.28), transparent) top / 68% 1px no-repeat,
            linear-gradient(180deg, rgba(125, 211, 252, 0.34), transparent 46%) right / 1px 64% no-repeat;
    }

    .login-card::after {
        content: "";
        position: absolute;
        right: -7rem;
        bottom: -7rem;
        width: 17rem;
        aspect-ratio: 1;
        border-radius: 999px;
        background: radial-gradient(circle, rgba(14, 165, 233, 0.38), transparent 68%);
        filter: blur(14px);
        pointer-events: none;
    }

    .brand-mark {
        background: linear-gradient(135deg, #2563eb, #22d3ee);
        box-shadow:
            0 18px 48px rgba(14, 165, 233, 0.34),
            inset 0 1px 0 rgba(255, 255, 255, 0.36);
    }

    .glass-field {
        border: 1px solid var(--field-border);
        background: var(--field-bg);
        color: var(--login-text);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.08),
            0 14px 30px rgba(2, 6, 23, 0.12);
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }

    .glass-field::placeholder {
        color: rgba(226, 238, 255, 0.54);
    }

    .glass-field:focus {
        border-color: var(--field-focus);
        background: rgba(8, 27, 72, 0.56);
        box-shadow:
            0 0 0 4px rgba(56, 189, 248, 0.14),
            0 20px 44px rgba(14, 165, 233, 0.16),
            inset 0 1px 0 rgba(255, 255, 255, 0.12);
    }

    .glass-field.is-invalid {
        border-color: rgba(251, 113, 133, 0.9);
        box-shadow: 0 0 0 4px rgba(244, 63, 94, 0.16);
    }

    .glass-field:-webkit-autofill,
    .glass-field:-webkit-autofill:hover,
    .glass-field:-webkit-autofill:focus,
    .glass-field:-webkit-autofill:active {
        transition: background-color 5000s ease-in-out 0s;
        -webkit-text-fill-color: var(--login-text) !important;
        caret-color: var(--login-text);
    }

    .login-check {
        background: rgba(10, 30, 76, 0.5);
        border-color: rgba(191, 219, 254, 0.4);
    }

    .liquid-button {
        position: relative;
        overflow: hidden;
        background:
            radial-gradient(circle at 18% 0%, rgba(255, 255, 255, 0.35), transparent 28%),
            linear-gradient(135deg, #2563eb 0%, #14b8ff 55%, #22d3ee 100%);
        box-shadow:
            0 18px 46px rgba(14, 165, 233, 0.32),
            inset 0 1px 0 rgba(255, 255, 255, 0.36);
    }

    .liquid-button::before {
        content: "";
        position: absolute;
        inset: -80% -20%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.44), transparent);
        transform: translateX(-76%) rotate(13deg);
        transition: transform 0.7s ease;
    }

    .liquid-button:hover::before {
        transform: translateX(76%) rotate(13deg);
    }

    .login-alert-success {
        border: 1px solid rgba(52, 211, 153, 0.38);
        background: rgba(6, 78, 59, 0.42);
        color: #c7f9e5;
    }

    .login-alert-error {
        border: 1px solid rgba(251, 113, 133, 0.42);
        background: rgba(127, 29, 29, 0.42);
        color: #ffe4e6;
    }

    @keyframes login-orbit {
        to { transform: rotate(360deg); }
    }

    @media (max-width: 1023px) {
        .login-backdrop {
            background-position: 31% center;
            filter: blur(6px) brightness(0.65) saturate(1.24);
        }

        .login-card {
            box-shadow:
                0 22px 64px rgba(1, 8, 29, 0.42),
                0 0 30px rgba(56, 189, 248, 0.2);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .ambient-orbit,
        .liquid-button::before {
            animation: none;
            transition: none;
        }
    }
</style>

<main class="login-shell flex min-h-screen items-center justify-center px-4 py-8 sm:px-6 lg:px-10"
      x-data="themeSystem()"
      x-init="initTheme()"
      :style="{ '--login-bg': theme === 'dark' ? 'url({{ asset('bg-dark-custom.jpg') }})' : 'url({{ asset('bg-light.png') }})' }">
    <div class="login-backdrop" aria-hidden="true"></div>
    <div class="ambient-orbit" aria-hidden="true"></div>

    <!-- Pill Toggle (Login Page) -->
    <div class="login-pill-toggle absolute right-5 top-5 z-50 sm:right-8 sm:top-8"
         @click="toggleTheme()"
         role="button"
         tabindex="0"
         @keydown.enter="toggleTheme()"
         @keydown.space.prevent="toggleTheme()"
         aria-label="Toggle login theme">
        <!-- Sun icon -->
        <span class="login-pill-icon" :class="{ 'active': theme !== 'dark' }">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
            </svg>
        </span>
        <!-- Knob -->
        <span class="login-pill-knob"></span>
        <!-- Moon icon -->
        <span class="login-pill-icon" :class="{ 'active': theme === 'dark' }">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998z" />
            </svg>
        </span>
    </div>

    <section class="relative z-10 grid w-full max-w-7xl items-center gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(390px,520px)] xl:gap-14">
        <div class="hidden min-h-[640px] flex-col justify-end pb-14 lg:flex">
            <div class="max-w-2xl">
                <div class="login-hero-pill inline-flex items-center gap-3 rounded-full px-5 py-3 text-xs font-black uppercase tracking-[0.18em] text-cyan-100">
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-300 shadow-[0_0_18px_rgba(52,211,153,0.85)]"></span>
                    Secure operations dashboard
                </div>

                <h1 class="mt-8 max-w-3xl font-display text-5xl font-black leading-[1.04] tracking-normal text-white xl:text-6xl">
                    Digital & CRM Management, refined.
                </h1>
                <p class="mt-5 max-w-xl text-lg font-semibold leading-8 text-sky-100/82">
                    A focused command center for boards, approvals, sales activity, customer pipelines, and team operations.
                </p>

                <div class="mt-8 grid max-w-xl grid-cols-3 gap-4">
                    <div class="login-metric rounded-2xl p-5">
                        <p class="text-3xl font-black text-white">24/7</p>
                        <p class="mt-1 text-sm font-bold text-sky-100/75">Secure access</p>
                    </div>
                    <div class="login-metric rounded-2xl p-5">
                        <p class="text-3xl font-black text-white">CRM</p>
                        <p class="mt-1 text-sm font-bold text-sky-100/75">Pipeline ready</p>
                    </div>
                    <div class="login-metric rounded-2xl p-5">
                        <p class="text-3xl font-black text-white">KQ</p>
                        <p class="mt-1 text-sm font-bold text-sky-100/75">Team boards</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mx-auto w-full max-w-[520px]">
            <div class="mb-6 text-center lg:hidden">
                <div class="brand-mark mx-auto flex h-16 w-16 items-center justify-center rounded-3xl">
                    <img src="{{ $appIcon }}" alt="KIUQ SYSTEM logo" class="h-12 w-12 object-contain">
                </div>
                <h1 class="mt-4 font-display text-3xl font-black text-white">KIUQ SYSTEM</h1>
                <p class="mt-1 text-sm font-semibold text-sky-100/80">Digital & CRM Management</p>
            </div>

            <div class="login-card rounded-[2.25rem] p-6 sm:p-8 lg:p-10">
                <div class="relative z-10">
                    <div class="hidden items-center gap-4 lg:flex">
                        <div class="brand-mark flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-3xl">
                            <img src="{{ $appIcon }}" alt="KIUQ SYSTEM logo" class="h-12 w-12 object-contain">
                        </div>
                        <div>
                            <p class="text-sm font-black uppercase tracking-[0.22em] text-cyan-200/80">KIUQ SYSTEM</p>
                            <h2 class="font-display text-4xl font-black tracking-normal text-white">
                                Welcome <span class="text-cyan-300">Back</span>
                            </h2>
                            <p class="mt-1 text-base font-semibold text-sky-100/76">Sign in to continue to your dashboard.</p>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="login-alert-error mt-6 rounded-2xl p-4 text-sm font-semibold shadow-sm" role="alert">
                            <div class="flex gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-5 w-5 flex-shrink-0">
                                    <path fill-rule="evenodd" d="M18 10A8 8 0 1 1 2 10a8 8 0 0 1 16 0ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd"/>
                                </svg>
                                <div>
                                    <p class="font-black">Please check your sign-in details.</p>
                                    <ul class="mt-1 space-y-1">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (session('success'))
                        <div class="login-alert-success mt-6 rounded-2xl p-4 text-sm font-semibold shadow-sm" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.submit') }}" id="login-form" class="mt-8 space-y-5" novalidate>
                        @csrf

                        <div>
                            <label for="email" class="mb-2 block text-sm font-black text-white">Email address</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                placeholder="you@company.com"
                                autocomplete="email"
                                required
                                class="glass-field h-14 w-full rounded-2xl px-5 text-base font-semibold outline-none {{ $errors->has('email') ? 'is-invalid' : '' }}"
                            >
                            @error('email')
                                <p class="mt-2 text-xs font-bold text-rose-200" id="email-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-data="{ show: false }">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label for="password" class="block text-sm font-black text-white">Password</label>
                                <a href="{{ Route::has('password.request') ? route('password.request') : '#' }}"
                                   class="text-xs font-black text-cyan-200 transition hover:text-white hover:underline"
                                   id="forgot-password-link">
                                    Forgot password?
                                </a>
                            </div>
                            <div class="relative">
                                <input
                                    :type="show ? 'text' : 'password'"
                                    id="password"
                                    name="password"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                                    class="glass-field h-14 w-full rounded-2xl px-5 pr-14 text-base font-semibold outline-none {{ $errors->has('password') ? 'is-invalid' : '' }}"
                                >
                                <button type="button"
                                        @click="show = !show"
                                        class="absolute inset-y-0 right-0 flex w-14 items-center justify-center text-sky-100/70 transition hover:text-white"
                                        id="toggle-password"
                                        :aria-label="show ? 'Hide password' : 'Show password'">
                                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" class="h-5 w-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                    </svg>
                                    <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" class="h-5 w-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                    </svg>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-2 text-xs font-bold text-rose-200" id="password-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <label for="remember" class="flex cursor-pointer items-center gap-3 text-sm font-bold text-sky-100/82">
                                <input type="hidden" name="remember" value="1">
                                <input type="checkbox" id="remember" name="remember" value="1" checked class="login-check h-5 w-5 rounded-md text-cyan-400 focus:ring-cyan-300">
                                Keep me signed in
                            </label>
                            <span class="hidden text-xs font-bold text-sky-100/52 sm:inline">Protected access</span>
                        </div>

                        <button type="submit"
                                id="btn-login"
                                class="liquid-button flex h-14 w-full items-center justify-center gap-3 rounded-2xl px-5 text-base font-black text-white transition hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-cyan-300/20">
                            <span class="relative">Sign in</span>
                            <svg class="relative h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>

            <p class="mt-6 text-center text-xs font-semibold leading-6 text-sky-100/68">
                &copy; {{ date('Y') }} KIUQ SYSTEM. Secure sign-in with rate limiting and IP monitoring.
            </p>
        </div>
    </section>
</main>
@endsection
