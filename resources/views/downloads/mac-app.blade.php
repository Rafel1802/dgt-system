@extends('layouts.app')

@section('title', 'macOS App')

@section('content')
@php
    $version = '1.0.1';
    $downloadUrl = asset('downloads/KIUQ-SYSTEM-1.0.1.dmg');
    $appcastUrl = asset('appcast/latest-mac.json');
@endphp

<style>
    .mac-app-page {
        color: #0f172a;
    }

    .mac-app-shell {
        background: #ffffff;
        border-color: #dbe4f0;
        box-shadow: 0 26px 70px rgba(15, 23, 42, 0.12);
    }

    .mac-app-hero {
        background:
            radial-gradient(circle at 17% 12%, rgba(79, 70, 229, 0.16), transparent 28%),
            radial-gradient(circle at 84% 10%, rgba(37, 99, 235, 0.12), transparent 30%),
            linear-gradient(135deg, #f8fbff 0%, #ffffff 43%, #eef4ff 100%);
        color: #0f172a !important;
    }

    .mac-app-hero * {
        color: inherit;
    }

    .mac-app-badge {
        background: #eef4ff;
        color: #1d4ed8 !important;
        border: 1px solid #bfdbfe;
    }

    .mac-app-title,
    .mac-app-section-title,
    .mac-app-strong {
        color: #0f172a !important;
    }

    .mac-app-copy,
    .mac-app-muted {
        color: #475569 !important;
    }

    .mac-app-download-box {
        background: #ffffff;
        border: 1px solid #dbe4f0;
        box-shadow: 0 18px 48px rgba(37, 99, 235, 0.14);
    }

    .mac-app-download-button {
        background: linear-gradient(135deg, #2563eb, #4f46e5);
        color: #ffffff !important;
        box-shadow: 0 14px 32px rgba(37, 99, 235, 0.28);
    }

    .mac-app-download-button:hover {
        transform: translateY(-1px);
        background: linear-gradient(135deg, #1d4ed8, #4338ca);
    }

    .mac-app-meta {
        color: #334155 !important;
    }

    .mac-app-stats {
        background: #f8fafc;
    }

    .mac-app-card,
    .mac-app-step {
        background: #ffffff;
        border-color: #dbe4f0;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .mac-app-update {
        background: #eef2ff;
        border-color: #c7d2fe;
    }

    .mac-app-icon-tile {
        background: #ffffff;
        color: #4f46e5 !important;
    }

    [data-theme="dark"] .mac-app-page {
        color: #f8fafc;
    }

    [data-theme="dark"] .mac-app-shell {
        background: #1a1d24 !important;
        border-color: rgba(148, 163, 184, 0.18) !important;
        box-shadow: 0 26px 70px rgba(0, 0, 0, 0.34);
    }

    [data-theme="dark"] .mac-app-hero {
        background:
            radial-gradient(circle at 18% 12%, rgba(99, 102, 241, 0.26), transparent 28%),
            radial-gradient(circle at 84% 12%, rgba(14, 165, 233, 0.14), transparent 30%),
            linear-gradient(135deg, #171a21 0%, #1d2028 54%, #151821 100%);
        color: #ffffff !important;
    }

    [data-theme="dark"] .mac-app-badge {
        background: rgba(15, 23, 42, 0.5);
        color: #f8fafc !important;
        border-color: rgba(255, 255, 255, 0.72);
    }

    [data-theme="dark"] .mac-app-title,
    [data-theme="dark"] .mac-app-section-title,
    [data-theme="dark"] .mac-app-strong {
        color: #f8fafc !important;
    }

    [data-theme="dark"] .mac-app-copy,
    [data-theme="dark"] .mac-app-muted {
        color: #d5dae5 !important;
    }

    [data-theme="dark"] .mac-app-download-box {
        background: rgba(15, 18, 25, 0.72);
        border-color: rgba(255, 255, 255, 0.74);
        box-shadow: 0 22px 58px rgba(0, 0, 0, 0.28);
    }

    [data-theme="dark"] .mac-app-download-button {
        background: #2b303b;
        color: #f8fafc !important;
        box-shadow: none;
    }

    [data-theme="dark"] .mac-app-download-button:hover {
        background: #343a46;
    }

    [data-theme="dark"] .mac-app-meta {
        color: #f8fafc !important;
    }

    [data-theme="dark"] .mac-app-stats {
        background: #242832 !important;
    }

    [data-theme="dark"] .mac-app-card,
    [data-theme="dark"] .mac-app-step {
        background: #1a1d24 !important;
        border-color: rgba(148, 163, 184, 0.2) !important;
        box-shadow: none;
    }

    [data-theme="dark"] .mac-app-update {
        background: #1a1d24;
        border-color: rgba(148, 163, 184, 0.2);
    }

    [data-theme="dark"] .mac-app-icon-tile {
        background: rgba(99, 102, 241, 0.14);
        color: #a5b4fc !important;
    }
</style>

<div class="mac-app-page mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mac-app-shell overflow-hidden rounded-[1.75rem] border">
        <section class="mac-app-hero relative overflow-hidden px-7 py-8 sm:px-10 lg:px-12">
            <div class="relative grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                    <img src="{{ asset('favicon.svg') }}" alt="KIUQ SYSTEM" class="h-20 w-20 rounded-3xl object-contain shadow-2xl shadow-blue-950/30 ring-1 ring-white/20">
                    <div>
                        <div class="mac-app-badge inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-black uppercase tracking-[0.18em]">
                            <span class="h-2 w-2 rounded-full bg-emerald-300"></span>
                            Private staff app
                        </div>
                        <h1 class="mac-app-title mt-4 text-3xl font-black tracking-normal sm:text-5xl">KIUQ SYSTEM for macOS</h1>
                        <p class="mac-app-copy mt-3 max-w-2xl text-base font-semibold leading-7">
                            Faster access to the hosted workspace, native macOS notifications, and a cleaner desktop experience for staff.
                        </p>
                    </div>
                </div>

                <div class="mac-app-download-box rounded-3xl p-4 backdrop-blur">
                    <a href="{{ route('downloads.mac-app.file') }}"
                       class="mac-app-download-button inline-flex w-full items-center justify-center gap-3 rounded-2xl px-7 py-4 text-base font-black transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                        </svg>
                        Download .dmg
                    </a>
                    <p class="mac-app-meta mt-3 text-center text-xs font-bold">Version {{ $version }} · macOS</p>
                </div>
            </div>
        </section>

        <section class="mac-app-stats grid gap-4 px-7 py-6 sm:grid-cols-3 sm:px-10 lg:px-12">
            <div class="mac-app-card rounded-2xl border p-5">
                <p class="mac-app-muted text-xs font-black uppercase tracking-[0.16em]">Version</p>
                <p class="mac-app-strong mt-2 text-3xl font-black">{{ $version }}</p>
            </div>
            <div class="mac-app-card rounded-2xl border p-5">
                <p class="mac-app-muted text-xs font-black uppercase tracking-[0.16em]">Connects to</p>
                <p class="mac-app-strong mt-2 truncate text-2xl font-black">Hostinger</p>
            </div>
            <div class="mac-app-card rounded-2xl border p-5">
                <p class="mac-app-muted text-xs font-black uppercase tracking-[0.16em]">Notifications</p>
                <p class="mac-app-strong mt-2 text-2xl font-black">Native macOS</p>
            </div>
        </section>

        <section class="grid gap-6 px-7 py-8 sm:px-10 lg:grid-cols-[1.1fr_0.9fr] lg:px-12">
            <div>
                <h2 class="mac-app-section-title text-xl font-black">Install steps</h2>
                <div class="mt-4 grid gap-3">
                    @foreach([
                        ['1', 'Download the DMG file from this page.'],
                        ['2', 'Open the DMG and drag KIUQ SYSTEM into Applications.'],
                        ['3', 'Open the app, sign in, and allow macOS notifications.'],
                    ] as [$number, $text])
                        <div class="mac-app-step flex items-center gap-4 rounded-2xl border p-4">
                            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-sm font-black text-blue-700">{{ $number }}</span>
                            <p class="mac-app-copy text-sm font-bold leading-6">{{ $text }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mac-app-update rounded-3xl border p-6">
                <div class="flex items-start gap-4">
                    <div class="mac-app-icon-tile flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7v6h-6M4 17v-6h6M6.5 9A6.5 6.5 0 0 1 18 6.1M17.5 15A6.5 6.5 0 0 1 6 17.9"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="mac-app-section-title text-lg font-black">Updates</h2>
                        <p class="mac-app-copy mt-2 text-sm font-semibold leading-6">
                            When a new app version is ready, upload the new DMG and update the appcast file. Staff can return here to download the latest version.
                        </p>
                        <a href="{{ $appcastUrl }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-black text-indigo-700 shadow-sm ring-1 ring-indigo-100">
                            View appcast
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M9 7h8v8"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
