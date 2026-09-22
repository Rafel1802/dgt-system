<?php
$appIconPath = file_exists(public_path('storage/favicon.svg')) ? 'storage/favicon.svg' : 'favicon.svg';
$appIconVersion = file_exists(public_path($appIconPath)) ? filemtime(public_path($appIconPath)) : time();
$appIconAsset = asset($appIconPath);
$appIcon = $appIconAsset . '?v=' . $appIconVersion;
$faviconIco = asset('favicon.ico') . '?v=' . (file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : $appIconVersion);
$faviconPng = asset('favicon-32x32.png') . '?v=' . (file_exists(public_path('favicon-32x32.png')) ? filemtime(public_path('favicon-32x32.png')) : $appIconVersion);
$appleTouchIcon = $appIcon;
$isMacDesktopApp = str_contains((string) request()->userAgent(), 'DGTSystemMacOSApp');
?>
    @php
        $userAgent = (string) request()->userAgent();
        $isIosApp = str_contains($userAgent, 'DGTSystemiOSApp') || preg_match('/iPhone|iPad|iPod/i', $userAgent);
        $isAndroidApp = str_contains($userAgent, 'DGTSystemAndroidApp') || str_contains($userAgent, 'wv') || preg_match('/Android/i', $userAgent);
        $isMobile = $isIosApp || $isAndroidApp || preg_match('/Mobile/i', $userAgent);
    @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full overflow-x-hidden {{ $isMacDesktopApp ? 'dgt-macos-app' : '' }} {{ $isMobile ? 'dgt-mobile-app' : '' }}">
<head>
    <script>localStorage.removeItem('dgt_font_scale');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="DIGITAL SYSTEM">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        @if(config('broadcasting.default') === 'pusher' && config('broadcasting.connections.pusher.key'))
            <meta name="kiuq-user-id" content="{{ auth()->id() }}">
            <meta name="kiuq-pusher-key" content="{{ config('broadcasting.connections.pusher.key') }}">
            <meta name="kiuq-pusher-cluster" content="{{ config('broadcasting.connections.pusher.options.cluster') }}">
            <script defer src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
        @endif
    @endauth

    <title>@yield('title', 'DIGITAL SYSTEM') | Digital Workspace</title>
    <meta name="description" content="@yield('meta_description', 'Digital Team Management System — Manage tasks, boards, and operations efficiently.')">
    <meta name="robots" content="noindex, nofollow">

    <!-- Favicon -->
    <link rel="icon" href="{{ $faviconIco }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ $appIcon }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $faviconPng }}">
    <link rel="apple-touch-icon" href="{{ $appleTouchIcon }}">
    <link rel="shortcut icon" href="{{ $faviconIco }}">

    <!-- Fonts preconnect and async load -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    </noscript>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>


    <!-- Turbo 8: Drive navigation with speculative prefetch for instant loading. -->
    <meta name="turbo-prefetch" content="true">
    <style>
        [x-cloak] { display: none !important; }

        /* We previously hid the progress bars, but re-enabling them provides crucial visual feedback on slow networks */
        .turbo-progress-bar {
            background-color: #4f46e5;
            height: 3px;
        }
        
        /* Disable manual fade-in to prevent SPA blinking/flashing during transitions */
        .animate-fade-in {
            animation: none !important;
            opacity: 1 !important;
        }

        /* Global Dark & Neon Mode Contrast Overrides */
        [data-theme="dark"] .bg-slate-50\/50 {
            background-color: rgba(30, 41, 59, 0.4) !important;
        }
        [data-theme="dark"] .bg-slate-50\/40 {
            background-color: rgba(30, 41, 59, 0.3) !important;
        }
        [data-theme="dark"] .border-slate-200 {
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        [data-theme="dark"] .text-slate-700 {
            color: #cbd5e1 !important;
        }

        [data-theme="neon"] .bg-slate-50\/50,
        [data-theme="neon"] .bg-slate-50\/40 {
            background-color: rgba(4, 22, 64, 0.6) !important;
        }
        [data-theme="neon"] .border-slate-200 {
            border-color: rgba(0, 160, 255, 0.35) !important;
        }
        [data-theme="neon"] .text-slate-700 {
            color: #bae6fd !important;
        }

        /* ─── Notification Toast Styles for Dark & Neon Modes ─── */
        [data-theme="dark"] .dgt-notification-toast {
            background-color: rgba(15, 23, 42, 0.95) !important;
            border-color: rgba(51, 65, 85, 0.8) !important;
            color: #f8fafc !important;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.7) !important;
        }
        [data-theme="dark"] .dgt-notification-toast .toast-actor {
            color: #f8fafc !important;
        }
        [data-theme="dark"] .dgt-notification-toast .toast-time {
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .dgt-notification-toast .toast-body {
            color: #cbd5e1 !important;
        }
        [data-theme="dark"] .dgt-notification-toast .toast-badge {
            background-color: rgba(49, 46, 129, 0.5) !important;
            border-color: rgba(99, 102, 241, 0.4) !important;
            color: #a5b4fc !important;
        }
        [data-theme="dark"] .dgt-notification-toast .toast-close-btn {
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .dgt-notification-toast .toast-close-btn:hover {
            color: #ffffff !important;
            background-color: rgba(51, 65, 85, 0.5) !important;
        }
        [data-theme="dark"] .dgt-notification-toast .toast-avatar {
            border-color: rgba(51, 65, 85, 0.8) !important;
        }

        [data-theme="neon"] .dgt-notification-toast {
            background: linear-gradient(135deg, rgba(4, 24, 68, 0.96) 0%, rgba(2, 14, 44, 0.98) 100%) !important;
            border: 1px solid rgba(0, 162, 255, 0.45) !important;
            color: #f0f9ff !important;
            box-shadow: 0 20px 45px -10px rgba(0, 5, 20, 0.85), 0 0 25px rgba(0, 162, 255, 0.25), inset 0 1px 0 rgba(56, 189, 248, 0.35) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
        }
        [data-theme="neon"] .dgt-notification-toast:hover {
            border-color: rgba(0, 210, 255, 0.75) !important;
            box-shadow: 0 25px 50px -10px rgba(0, 5, 20, 0.95), 0 0 35px rgba(0, 195, 255, 0.35), inset 0 1px 0 rgba(56, 189, 248, 0.5) !important;
        }
        [data-theme="neon"] .dgt-notification-toast .toast-actor {
            color: #ffffff !important;
            text-shadow: 0 0 12px rgba(0, 162, 255, 0.5);
        }
        [data-theme="neon"] .dgt-notification-toast .toast-time {
            color: #7dd3fc !important;
        }
        [data-theme="neon"] .dgt-notification-toast .toast-body {
            color: #bae6fd !important;
        }
        [data-theme="neon"] .dgt-notification-toast .toast-badge {
            background-color: rgba(0, 140, 255, 0.2) !important;
            border-color: rgba(0, 195, 255, 0.55) !important;
            color: #38bdf8 !important;
            box-shadow: 0 0 12px rgba(0, 160, 255, 0.25) !important;
        }
        [data-theme="neon"] .dgt-notification-toast .toast-close-btn {
            color: #7dd3fc !important;
        }
        [data-theme="neon"] .dgt-notification-toast .toast-close-btn:hover {
            color: #ffffff !important;
            background-color: rgba(0, 162, 255, 0.25) !important;
        }
        [data-theme="neon"] .dgt-notification-toast .toast-avatar {
            border-color: rgba(0, 162, 255, 0.5) !important;
            box-shadow: 0 0 12px rgba(0, 162, 255, 0.35) !important;
        }

        /* ─── Notification Dropdown in Neon Mode ─── */
        [data-theme="neon"] .notif-panel {
            background: linear-gradient(180deg, rgba(4, 20, 56, 0.98) 0%, rgba(2, 12, 36, 0.98) 100%) !important;
            border-color: rgba(0, 160, 255, 0.35) !important;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.8), 0 0 30px rgba(0, 162, 255, 0.2) !important;
        }
        [data-theme="neon"] .notif-panel .border-b {
            background: rgba(2, 10, 30, 0.95) !important;
            border-color: rgba(0, 160, 255, 0.25) !important;
        }
    </style>
    <script type="module">
        // Self-hosted Turbo — avoid unpkg RTT on every cold load (Hostinger users often far from CDN).
        import * as Turbo from "{{ asset('js/turbo.es2017-esm.js') }}?v={{ file_exists(public_path('js/turbo.es2017-esm.js')) ? filemtime(public_path('js/turbo.es2017-esm.js')) : '8.0.4' }}";
        window.Turbo = Turbo;
        Turbo.setProgressBarDelay(400);
        
        // Trigger Turbo prefetch on touch devices (Turbo 8 relies on hover/focus which doesn't trigger fast enough on mobile taps)
        document.addEventListener('touchstart', (e) => {
            const link = e.target.closest('a[href]');
            if (link && link.href && link.origin === window.location.origin && link.getAttribute('data-turbo') !== 'false') {
                const event = new MouseEvent('mouseenter', {
                    view: window,
                    bubbles: true,
                    cancelable: true
                });
                link.dispatchEvent(event);
            }
        }, { passive: true });
    </script>
    <script>
        (function() {
            // Revert submit buttons when Turbo completes the request
            document.addEventListener('turbo:submit-end', function(e) {
                const form = e.target;
                const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                submitButtons.forEach(button => {
                    if (button.dataset.originalHtml) {
                        button.disabled = false;
                        button.classList.remove('opacity-75', 'cursor-not-allowed');
                        button.innerHTML = button.dataset.originalHtml;
                        delete button.dataset.originalHtml;
                    }
                });
            });

            // Keep scroll position on form submissions & show processing spinner
            document.addEventListener('submit', (e) => {
                if (e.defaultPrevented) return;
                
                const form = e.target;
                if (form.checkValidity && !form.checkValidity()) {
                    return;
                }

                // Store current path and scroll position
                sessionStorage.setItem('scroll_position_path', window.location.pathname);
                sessionStorage.setItem('scroll_position_y', window.scrollY);

                // Show spinner and disable buttons to prevent double clicks
                setTimeout(() => {
                    if (e.defaultPrevented) return;
                    
                    // Skip forms that explicitly opt-out of the processing spinner
                    if (form.hasAttribute('data-no-processing')) return;
                    
                    const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                    submitButtons.forEach(button => {
                        if (!button.disabled) {
                            button.dataset.originalHtml = button.innerHTML;
                            button.disabled = true;
                            button.classList.add('opacity-75', 'cursor-not-allowed');
                            
                            const isSmall = button.classList.contains('py-1') || button.classList.contains('px-1.5') || button.classList.contains('px-2.5');
                            
                            button.innerHTML = `
                                <svg class="animate-spin -ml-1 mr-1.5 h-3.5 w-3.5 text-current inline-block align-middle" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="align-middle">${isSmall ? '...' : 'Processing...'}</span>
                            `;

                            // If form has data-turbo="false", it is likely a file download.
                            // Re-enable button after 3 seconds so it doesn't get stuck disabled.
                            if (form.getAttribute('data-turbo') === 'false') {
                                setTimeout(() => {
                                    if (button.disabled && button.dataset.originalHtml) {
                                        button.disabled = false;
                                        button.classList.remove('opacity-75', 'cursor-not-allowed');
                                        button.innerHTML = button.dataset.originalHtml;
                                    }
                                }, 3000);
                            }
                        }
                    });
                }, 0);
            });

            // Restore scroll position on turbo:load
            document.addEventListener('turbo:load', () => {
                const savedPath = sessionStorage.getItem('scroll_position_path');
                const savedY = sessionStorage.getItem('scroll_position_y');
                if (savedPath === window.location.pathname && savedY !== null) {
                    requestAnimationFrame(() => {
                        window.scrollTo(0, parseInt(savedY, 10));
                    });
                    sessionStorage.removeItem('scroll_position_path');
                    sessionStorage.removeItem('scroll_position_y');
                }
            });

            // Re-enable buttons and clean up charts before Turbo cache (back/forward navigation)
            document.addEventListener('turbo:before-cache', () => {
                document.querySelectorAll('form button[type="submit"], form input[type="submit"]').forEach(button => {
                    if (button.disabled && button.dataset.originalHtml) {
                        button.disabled = false;
                        button.classList.remove('opacity-75', 'cursor-not-allowed');
                        button.innerHTML = button.dataset.originalHtml;
                    }
                });

                // Destroy any Chart.js instances to revert canvases back to pristine state,
                // otherwise Turbo restores them with fixed inline sizes and breaks them.
                if (window.Chart) {
                    document.querySelectorAll('canvas').forEach(canvas => {
                        const chart = Chart.getChart(canvas);
                        if (chart) chart.destroy();
                    });
                }
            });

            // Keep sidebar scroll + active item in sync when the sidebar is
            // data-turbo-permanent (not re-rendered on every menu click).
            function updateSidebarActive() {
                const sidebar = document.getElementById('sidebar');
                if (!sidebar) return;
                const path = window.location.pathname.replace(/\/+$/, '') || '/';
                const links = [...sidebar.querySelectorAll('a[href]')].filter(a => {
                    try { return a.origin === window.location.origin; } catch (_) { return false; }
                });

                links.forEach(a => a.classList.remove('active'));

                let best = null;
                let bestLen = -1;
                links.forEach(a => {
                    let href;
                    try { href = new URL(a.getAttribute('href'), window.location.origin).pathname.replace(/\/+$/, '') || '/'; }
                    catch (_) { return; }
                    if (href === path || (href !== '/' && path.startsWith(href + '/'))) {
                        if (href.length > bestLen) {
                            best = a;
                            bestLen = href.length;
                        }
                    }
                });
                if (best) {
                    best.classList.add('active');
                    // Open parent accordion if the active link is nested — via
                    // Alpine's own public $data API, not by reaching into
                    // _x_dataStack (undocumented, not guaranteed to trigger
                    // reactivity) or writing style.display directly on an
                    // x-show/x-transition element. Alpine already owns that
                    // style property for its own animated show/hide; a second,
                    // uncoordinated writer fighting over the same property is
                    // exactly what caused the open-close-open flash on every
                    // click — Alpine's own transition replaying once it
                    // "corrects" the DOM back to what its reactive state says.
                    const group = best.closest('[x-data]');
                    if (group && window.Alpine && typeof window.Alpine.$data === 'function') {
                        try {
                            const data = window.Alpine.$data(group);
                            if (data && typeof data.open !== 'undefined') {
                                data.open = true;
                            }
                        } catch (_) {}
                    }
                }
            }

            document.addEventListener('turbo:load', updateSidebarActive);
            document.addEventListener('DOMContentLoaded', updateSidebarActive);

            // Optimistic UI update: instantly change active state on click
            document.addEventListener('click', (e) => {
                const link = e.target.closest('#sidebar a[href]');
                if (link && !link.closest('[x-data="{ open: false }"]')) { // Only update for actual navigation links
                    const sidebar = document.getElementById('sidebar');
                    if (sidebar) {
                        sidebar.querySelectorAll('a[href]').forEach(a => a.classList.remove('active'));
                        link.classList.add('active');
                    }
                }
            });

            document.addEventListener('turbo:before-render', (event) => {
                const currentSidebar = document.getElementById('sidebar');
                const newSidebar = event.detail.newBody.querySelector('#sidebar');
                if (currentSidebar && newSidebar) {
                    // Preserve scroll; permanent element keeps DOM, but copy scroll if swapped.
                    try { newSidebar.scrollTop = currentSidebar.scrollTop; } catch (_) {}
                }
            });
        })();
    </script>
    <script defer src="{{ asset('js/workspace-alpine.js') }}?v={{ file_exists(public_path('js/workspace-alpine.js')) ? filemtime(public_path('js/workspace-alpine.js')) : '1.0.0' }}"></script>
    <script defer src="{{ asset('js/trello-board.js') }}?v={{ file_exists(public_path('js/trello-board.js')) ? filemtime(public_path('js/trello-board.js')) : '1.0.0' }}"></script>
    <script defer src="{{ asset('js/drag-scroll.js') }}?v={{ file_exists(public_path('js/drag-scroll.js')) ? filemtime(public_path('js/drag-scroll.js')) : '1.0.0' }}"></script>
    <!-- Vite assets (Tailwind CSS + Alpine.js + Livewire, bundled manually so Livewire's JS
         is not injected into <body> where Turbo would re-execute it on every navigation) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    @stack('styles')
    @stack('head')

    @php
        $isDesktopOrMobileApp = $isMacDesktopApp || $isMobile;
    @endphp

    @if($isDesktopOrMobileApp)
        <style>
            [x-cloak] { display: none !important; }
            [x-cloak=""] { display: none !important; }

            html, body {
                background-color: var(--bg-page) !important;
            }

            .turbo-progress-bar {
                display: none !important;
                visibility: hidden !important;
            }


            /* Smooth scrolling and momentum touch scroll globally */
            html, body, .page-content, .board-wrap, .sidebar {
                -webkit-overflow-scrolling: touch;
            }

            html.dgt-mobile-app,
            html.dgt-mobile-app body {
                width: 100%;
                height: 100%;
                min-height: 100%;
                overflow: hidden;
                overscroll-behavior: none;
                background: var(--bg-page, #f4f7fb);
                -webkit-tap-highlight-color: transparent;
            }

            html.dgt-mobile-app #dgt-app-wrapper {
                width: 100%;
                height: 100vh;
                height: 100dvh;
                min-height: 100vh;
                min-height: 100dvh;
                overflow: hidden;
                background: var(--bg-page, #f4f7fb);
            }

            html.dgt-macos-app .sidebar-logo, html.dgt-mobile-app .sidebar-logo {
                padding-top: 3.15rem;
            }

            html.dgt-macos-app .sidebar-logo-icon, html.dgt-mobile-app .sidebar-logo-icon {
                width: 36px;
                height: 36px;
            }

            html.dgt-mobile-app .sidebar-logo-icon img {
                width: 100%;
                height: 100%;
                image-rendering: auto;
                filter: none;
            }

            html.dgt-mobile-app .sidebar-logo-text {
                white-space: nowrap;
            }

            html.dgt-mobile-app .sidebar-logo-sub {
                white-space: nowrap;
            }

            html.dgt-mobile-app .sidebar {
                top: 0;
                height: 100vh;
                height: 100dvh;
                overscroll-behavior: contain;
            }

            html.dgt-mobile-app .main-wrapper {
                height: 100vh;
                height: 100dvh;
                min-height: 0;
                overflow: hidden;
                background: var(--bg-page, #f4f7fb);
            }

            html.dgt-mobile-app .topbar {
                flex: 0 0 64px;
            }

            html.dgt-mobile-app .page-content {
                flex: 1 1 auto;
                min-height: 0;
                overflow-x: hidden;
                overflow-y: auto;
                overscroll-behavior: contain;
            }
        </style>
    @endif

    <!-- Service Worker Registration for Instant Offline-First Cache Loads -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('Service Worker registered!'))
                    .catch(err => console.error('Service Worker registration failed:', err));
            });
        }
    </script>

    <!-- Prevent theme flash (FOUC) -->
    <script>
        (function() {
            try {
                const storedTheme = localStorage.getItem('theme');
                const storedNeon = localStorage.getItem('dgt_neon_mode');
                let theme = storedTheme;
                let neon = storedNeon === 'true';

                if (storedTheme === 'neon') {
                    theme = 'dark';
                    neon = true;
                } else if (!storedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    theme = 'dark';
                }

                if (theme === 'dark') {
                    if (neon) {
                        document.documentElement.setAttribute('data-theme', 'neon');
                    } else {
                        document.documentElement.setAttribute('data-theme', 'dark');
                    }
                } else {
                    document.documentElement.removeAttribute('data-theme');
                }
            } catch (e) {}
        })();
    </script>
    <!-- Custom Flatpickr UI -->
    <style>
        .flatpickr-calendar {
            font-family: inherit !important;
            border-radius: 12px !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
            border: 1px solid var(--border-color, #e2e8f0) !important;
            background: #ffffff !important;
            width: 320px !important;
            padding: 10px !important;
        }
        .flatpickr-months .flatpickr-month {
            background: transparent !important;
            color: #1e293b !important;
            fill: #1e293b !important;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months {
            background: #ffffff !important;
            font-weight: 600 !important;
        }
        .flatpickr-current-month input.cur-year {
            font-weight: 600 !important;
        }
        .flatpickr-day {
            color: #1e293b !important;
            border-radius: 8px !important;
            font-size: 14px !important;
            line-height: 38px !important;
            height: 38px !important;
            flex-basis: 14.2857% !important;
            max-width: 14.2857% !important;
        }
        .flatpickr-day.selected {
            background: #4f46e5 !important;
            border-color: #4f46e5 !important;
            color: #ffffff !important;
        }
        .flatpickr-day:hover {
            background: #e0e7ff !important;
            border-color: #e0e7ff !important;
            color: #4f46e5 !important;
        }
        html[data-theme="dark"] .flatpickr-calendar, .dark .flatpickr-calendar {
            background: #1e293b !important;
            border-color: #334155 !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5) !important;
        }
        html[data-theme="dark"] .flatpickr-months .flatpickr-month,
        html[data-theme="dark"] .flatpickr-current-month .flatpickr-monthDropdown-months,
        html[data-theme="dark"] .flatpickr-day,
        .dark .flatpickr-months .flatpickr-month,
        .dark .flatpickr-current-month .flatpickr-monthDropdown-months,
        .dark .flatpickr-day {
            color: #f8fafc !important;
            fill: #f8fafc !important;
            background: transparent !important;
        }
        html[data-theme="dark"] .flatpickr-current-month .flatpickr-monthDropdown-months option,
        .dark .flatpickr-current-month .flatpickr-monthDropdown-months option {
            background: #1e293b !important;
        }
        html[data-theme="dark"] .flatpickr-day:hover,
        .dark .flatpickr-day:hover {
            background: #334155 !important;
            border-color: #334155 !important;
            color: #f8fafc !important;
        }
        html[data-theme="dark"] .flatpickr-day.selected,
        .dark .flatpickr-day.selected {
            background: #6366f1 !important;
            border-color: #6366f1 !important;
            color: #ffffff !important;
        }
        .flatpickr-weekday {
            color: #64748b !important;
            font-weight: 600 !important;
        }
        html[data-theme="dark"] .flatpickr-weekday,
        .dark .flatpickr-weekday {
            color: #94a3b8 !important;
        }
    </style>
</head>
<body class="h-full bg-[var(--bg-page)] overscroll-none touch-manipulation overflow-x-hidden" x-data="themeSystem()" x-init="initTheme()">

    <!-- ── Sidebar Overlay (mobile) ───────────────────────────────────── -->
    <style>
        [x-cloak] { display: none !important; }

        #dgt-app-wrapper.not-ready .sidebar,
        #dgt-app-wrapper.not-ready .main-wrapper {
            transition: none !important;
        }
    </style>
    <div
        id="dgt-app-wrapper"
        x-init="$nextTick(() => { $el.classList.remove('not-ready') })"
        x-on:keydown.escape.window="$store.sidebar.close()"
        :class="{ 'sidebar-is-collapsed': $store.sidebar.collapsed }"
        class="relative h-full not-ready"
    >
        <script>
            if (localStorage.getItem('dgt-sidebar-collapsed') === 'true') {
                document.getElementById('dgt-app-wrapper').classList.add('sidebar-is-collapsed');
            }
        </script>
        <!-- Mobile overlay backdrop -->
        <div
            x-show="$store.sidebar.mobileOpen && !$store.sidebar.isDesktop"
            x-transition:enter="transition ease-out duration-75"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm z-30 lg:hidden"
            @click="$store.sidebar.close()"
            x-cloak
        ></div>

        <!-- ── Sidebar ────────────────────────────────────────────────── -->
        {{-- data-turbo-permanent: keep sidebar DOM across menu clicks so Turbo
             does not re-paint the full nav on every navigation. Active item
             is updated in JS on turbo:load. Its open/collapsed/mobileOpen
             state lives in the global $store.sidebar (resources/js/app.js),
             not a local x-data — #dgt-app-wrapper itself is NOT permanent and
             gets fully recreated by Turbo on every navigation, so a local
             component here would reinitialize (and its state briefly flash
             back to defaults, and its listeners re-stack on the permanent
             aside below) on every single click. --}}
        <aside
            :class="{ 'open': $store.sidebar.mobileOpen }"
            class="sidebar"
            id="sidebar"
            data-turbo-permanent
            aria-label="Main navigation"
        >
            <!-- Logo -->
            <div class="sidebar-logo">
                <div class="sidebar-logo-icon">
                    <img src="{{ $appIconAsset }}" alt="DIGITAL SYSTEM logo" class="h-full w-full object-contain">
                </div>
                <div>
                    <div class="sidebar-logo-text">DIGITAL SYSTEM</div>
                    <div class="sidebar-logo-sub">KiuQ.com</div>
                </div>
                <!-- Desktop collapse btn -->
                <button type="button"
                        class="sidebar-collapse-btn hidden lg:inline-flex"
                        @click="$store.sidebar.toggleCollapse()"
                        aria-label="Collapse sidebar"
                        title="Collapse sidebar">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                    </svg>
                </button>
                <!-- Mobile close btn -->
                <button type="button"
                        class="sidebar-mobile-close-btn lg:hidden"
                        @click="$store.sidebar.close()"
                        aria-label="Close menu">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="sidebar-nav" role="navigation">
                @php
                    $currentUser = auth()->user();
                    $isQcUser = $currentUser && ($currentUser->isQc() || $currentUser->hasRole('qc') || str_contains(strtolower($currentUser->name ?? ''), 'dara') || str_contains(strtolower($currentUser->team_role ?? ''), 'qc'));
                    $canSeeApprovalQueue = ($currentUser?->can('kanban.approve') ?? false) && !$isQcUser;
                    $userPlanningBoards = $currentUser ? $currentUser->getPlanningBoardsWithTaskCounts() : collect();
                @endphp

                <!-- Main -->
                @can('dashboard.view')
                @unless(auth()->user()?->hasRole('boss'))
                <span class="sidebar-section-label">Main</span>

                <a href="{{ route('dashboard') }}"
                   class="sidebar-item {{ request()->routeIs('dashboard*') ? 'active' : '' }}"
                   id="nav-dashboard" data-tooltip="Dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <rect width="7" height="9" x="3" y="3" rx="1" />
                        <rect width="7" height="5" x="14" y="3" rx="1" />
                        <rect width="7" height="9" x="14" y="12" rx="1" />
                        <rect width="7" height="5" x="3" y="16" rx="1" />
                    </svg>
                    <span>Dashboard</span>
                </a>
                @endunless
                @endcan

                <!-- All Members Directory -->
                <a href="{{ route('members.index') }}"
                   class="sidebar-item {{ request()->routeIs('members.*') ? 'active' : '' }}"
                   id="nav-all-members" data-tooltip="All Members">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                    </svg>
                    <span>All Members</span>
                </a>

                <!-- Notes -->
                @unless(auth()->user()?->hasRole('boss'))
                <span class="sidebar-section-label">Notes</span>
                <a href="{{ route('notes.team') }}"
                   class="sidebar-item {{ request()->routeIs('notes.team*') ? 'active' : '' }}"
                   id="nav-notes-team" data-tooltip="Team Note">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <span>Team Note</span>
                </a>
                <a href="{{ route('notes.private') }}"
                   class="sidebar-item {{ request()->routeIs('notes.private*') ? 'active' : '' }}"
                   id="nav-notes-private" data-tooltip="Private Note">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <span>Private Note</span>
                </a>
                @endunless

                <!-- Digital Team -->
                @can('kanban.view')
                <span class="sidebar-section-label">Digital Team</span>
                <?php
                    $sidebarWebTools = \App\Models\Setting::externalToolsForGroup('board', true);
                    $sidebarSystemTools = \App\Models\Setting::externalToolsForGroup('generator', true);
                    $sidebarWorkspaceTools = \App\Models\Setting::externalToolsForGroup('workspace', true);
                    $sidebarAiTools = \App\Models\Setting::externalToolsForGroup('ai', true);
                ?>

                <a href="{{ route('boards.workspaces') }}"
                   class="sidebar-item {{ (request()->routeIs('boards.*') && !request()->routeIs('boards.reports.*')) ? 'active' : '' }}"
                   id="nav-boards" data-tooltip="Boards">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
                    </svg>
                    <span>Boards</span>
                </a>

                {{-- Social Media --}}
                @if(auth()->user()?->hasAnyRole(['super-admin', 'admin-digital', 'social_admin', 'social_qc', 'boss', 'digital-team']))
                <div x-data="{ smOpen: localStorage.getItem('dgt-sm-menu-open') === 'true' || {{ request()->routeIs('social-media.*') || request()->routeIs('smm-boards.*') ? 'true' : 'false' }} }" class="sidebar-accordion-group">
                    <div class="sidebar-item w-full flex items-center justify-between text-left {{ request()->routeIs('social-media.*') || request()->routeIs('smm-boards.*') ? 'active' : '' }}" data-tooltip="Social Media Management">
                        <a href="{{ route('social-media.dashboard') }}" @click="smOpen = true; localStorage.setItem('dgt-sm-menu-open', 'true')" class="flex items-center gap-[0.625rem] flex-1">
                            <img src="https://cdn-icons-png.flaticon.com/512/1468/1468269.png" alt="Social Media Management" class="w-[18px] h-[18px] flex-shrink-0 object-contain">
                            <span>Social Media Management</span>
                        </a>
                        <button type="button" @click.stop="smOpen = !smOpen; localStorage.setItem('dgt-sm-menu-open', smOpen)" class="p-1 -mr-1 rounded hover:bg-slate-700/50 transition-colors" aria-label="Toggle SMM menu">
                            <svg class="w-3.5 h-3.5 transition-transform duration-100" :class="{'rotate-180': smOpen}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                    </div>
                    <div x-show="smOpen" x-collapse x-cloak>
                        <div class="sidebar-submenu-list mt-1 space-y-1 relative">
                            
                            <a href="{{ route('social-media.dashboard') }}"
                               class="sidebar-submenu-item {{ request()->routeIs('social-media.*') ? 'active' : '' }}" data-tooltip="Social & Analytics">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /><path stroke-linecap="round" stroke-linejoin="round" d="m9 10 2 2 4-4" /></svg>
                                <span>Social & Analytics</span>
                            </a>

                            <a href="{{ route('smm-boards.index') }}"
                               class="sidebar-submenu-item {{ request()->routeIs('smm-boards.*') ? 'active' : '' }}" data-tooltip="SMM Planning Board">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                </svg>
                                <span>SMM Planning Board</span>
                            </a>
                            

                        </div>
                    </div>
                </div>
                @endif

                @if(auth()->user()->hasWebsiteAccess())
                {{-- All Websites accordion sub-menu --}}
                <div x-data="{ wsOpen: localStorage.getItem('dgt-websites-menu-open') === 'true' || {{ request()->routeIs('websites.*') ? 'true' : 'false' }} }" class="sidebar-accordion-group">
                    <div
                        class="sidebar-item w-full flex items-center justify-between text-left {{ request()->routeIs('websites.*') ? 'active' : '' }}"
                        id="nav-websites-toggle"
                        data-tooltip="All Websites"
                    >
                        <a href="{{ route('websites.index', ['tab' => 'build']) }}" class="flex items-center gap-[0.625rem] flex-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-[18px] h-[18px]">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253M3 12a8.959 8.959 0 0 0 .284 2.253" />
                            </svg>
                            <span>All Websites</span>
                        </a>
                        <button type="button" @click.stop="wsOpen = !wsOpen; localStorage.setItem('dgt-websites-menu-open', wsOpen)" class="p-1 -mr-1 rounded hover:bg-slate-700/50 transition-colors" aria-label="Toggle Websites menu">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2.5"
                                stroke="currentColor"
                                class="w-3.5 h-3.5 transition-transform duration-100"
                                :class="{ 'rotate-180': wsOpen }"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                    </div>

                    <div
                        id="submenu-websites"
                        x-show="wsOpen"
                        x-transition:enter="transition ease-out duration-75"
                        x-transition:enter-start="opacity-0 transform -translate-y-2"
                        x-transition:enter-end="opacity-100 transform translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 transform translate-y-0"
                        x-transition:leave-end="opacity-0 transform -translate-y-2"
                        x-cloak
                        class="sidebar-submenu-list mt-1 space-y-1 relative"
                    >
                        {{-- 1. Website Status --}}
                        @php
                            $wsStatusTabs = ['build','build-progress','live','maintenance','qc-error','supervisor-error'];
                            $isOnStatusTab = request()->routeIs('websites.index') && in_array(request()->get('tab','build'), $wsStatusTabs);
                        @endphp
                        <a href="{{ route('websites.index', ['tab' => 'build']) }}"
                           class="sidebar-submenu-item {{ $isOnStatusTab ? 'active' : '' }}"
                           id="nav-websites-status" data-tooltip="Website Status">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 10 2 2 4-4" />
                                <rect width="20" height="14" x="2" y="3" rx="2" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 17v4" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8" />
                            </svg>
                            <span>Website Status</span>
                        </a>
                        {{-- 2. Follow Up --}}
                        <a href="{{ route('websites.index', ['tab' => 'follow-up']) }}"
                           class="sidebar-submenu-item {{ request()->routeIs('websites.index') && request()->get('tab') === 'follow-up' ? 'active' : '' }}"
                           id="nav-websites-followup" data-tooltip="Follow Up">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4"><rect width="8" height="4" x="8" y="2" rx="1" ry="1" /><path stroke-linecap="round" stroke-linejoin="round" d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" /><path stroke-linecap="round" stroke-linejoin="round" d="m9 14 2 2 4-4" /></svg>
                            <span>Follow Up</span>
                        </a>
                    </div>
                </div>
                @endif

                @if($canSeeApprovalQueue)
                <a href="{{ route('approvals.index') }}"
                   class="sidebar-item {{ request()->routeIs('approvals.*') ? 'active' : '' }}"
                   id="nav-approvals" data-tooltip="Approval Queue">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375"/>
                    </svg>
                    <span>Approval Queue</span>
                </a>
                @elseif(isset($userPlanningBoards) && $userPlanningBoards->isNotEmpty())
                    @php
                        $totalUserTasks = $userPlanningBoards->sum('user_tasks_count');
                        $hasDueTomorrow = $userPlanningBoards->sum('due_tomorrow_count') > 0;
                    @endphp
                    <a href="{{ route('tasks.count') }}"
                       class="sidebar-item {{ request()->routeIs('tasks.count') ? 'active' : '' }}"
                       id="nav-tasks-count" data-tooltip="Tasks Count">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                        </svg>
                        <span>Tasks Count</span>
                        @if($totalUserTasks > 0)
                        <span class="ml-auto flex items-center gap-1.5 shrink-0">
                            @if($hasDueTomorrow)
                                <span class="relative flex h-2 w-2" title="Tasks due in 1 day (tomorrow)">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                </span>
                            @endif
                            <span class="bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                {{ $totalUserTasks }}
                            </span>
                        </span>
                        @endif
                    </a>
                @endif

                @if(auth()->user()->isQcOrSupervisor())
                <a href="{{ route('boards.reports.personal') }}"
                   class="sidebar-item {{ request()->routeIs('boards.reports.personal') ? 'active' : '' }}"
                   id="nav-personal-report" data-tooltip="Personal Report">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                        <polyline stroke-linecap="round" stroke-linejoin="round" points="14 2 14 8 20 8" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-6" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 18v-1" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 18v-3" />
                    </svg>
                    <span>Personal Report</span>
                </a>
                @endif


                @can('view-blog-reports')
                <a href="{{ route('blog-reports.index') }}"
                   class="sidebar-item {{ request()->routeIs('blog-reports.*') ? 'active' : '' }}"
                   id="nav-blog-report" data-tooltip="Blog Report">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <span>Blog Report</span>
                </a>
                @endcan

                <?php
                    $weeklyReport = collect(\App\Models\Setting::externalToolsForGroup('board', true))->firstWhere('key', 'weekly_report_url');
                ?>
                @unless(auth()->user()?->hasRole('boss'))
                @if($weeklyReport)
                    <a href="{{ $weeklyReport['url'] }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="sidebar-item" data-tooltip="{{ $weeklyReport['short_label'] ?? $weeklyReport['label'] ?? $weeklyReport['name'] ?? 'Weekly Report' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <rect width="20" height="5" x="2" y="3" rx="1" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 12h4" />
                        </svg>
                        <span>{{ $weeklyReport['short_label'] ?? $weeklyReport['label'] ?? $weeklyReport['name'] ?? 'Weekly Report' }}</span>
                    </a>
                @endif
                @endunless

                @unless(auth()->user()?->hasRole('boss'))
                @if(count($sidebarWorkspaceTools))
                    @php
                        $userEmail = auth()->user()->email ?? '';
                        // The shared Digital Drive — all @kiuq.com members have access
                        $digitalDriveUrl = 'https://drive.google.com/drive/shared-drives?authuser=' . urlencode($userEmail);
                    @endphp
                    <div class="sidebar-tool-group">
                        <span class="sidebar-tool-heading">Google Workspace</span>
                        @foreach($sidebarWorkspaceTools as $tool)
                            @php
                                $toolUrl = $tool['url'] ?? '#';
                                // For Google Drive: open digital@typhonmachinery.com's drive directly
                                // All @kiuq.com accounts already have access granted by typhonmachinery
                                if (str_contains($toolUrl, 'drive.google.com')) {
                                    $toolUrl = 'https://drive.google.com/drive/my-drive?authuser=digital@typhonmachinery.com';
                                } elseif (str_contains($toolUrl, 'docs.google.com') || str_contains($toolUrl, 'sheets.google.com') || str_contains($toolUrl, 'slides.google.com') || str_contains($toolUrl, 'mail.google.com') || str_contains($toolUrl, 'calendar.google.com')) {
                                    // Append authuser to other Google services
                                    $userEmail = auth()->user()->email ?? '';
                                    $separator = str_contains($toolUrl, '?') ? '&' : '?';
                                    $toolUrl = $toolUrl . $separator . 'authuser=' . urlencode($userEmail);
                                }
                            @endphp
                            <a href="{{ $toolUrl }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="sidebar-item sidebar-tool-item"
                               data-tooltip="{{ $tool['label'] ?? $tool['name'] }}">
                                @if(isset($tool['icon_url']) && $tool['icon_url'])
                                    <img src="{{ $tool['icon_url'] }}" class="h-4 w-4 object-contain" alt="">
                                @else
                                    <x-external-tool-icon :name="$tool['icon']" />
                                @endif
                                <span>{{ $tool['short_label'] ?? $tool['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <?php
                    $sidebarWebTools = collect(\App\Models\Setting::externalToolsForGroup('board', true))
                        ->reject(function($t) {
                            return ($t['key'] ?? null) === 'weekly_report_url';
                        })
                        ->all();
                ?>

                @if(count($sidebarWebTools))
                    <div class="sidebar-tool-group">
                        <span class="sidebar-tool-heading">eBay &amp; Web Supporter</span>
                        @foreach($sidebarWebTools as $tool)
                            <a href="{{ $tool['url'] }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="sidebar-item sidebar-tool-item sidebar-tool-item-web" data-tooltip="{{ $tool['label'] }}">
                                @if(isset($tool['icon_url']) && $tool['icon_url'])
                                    <img src="{{ $tool['icon_url'] }}" class="h-4 w-4 object-contain" alt="">
                                @else
                                    <x-external-tool-icon :name="$tool['icon']" />
                                @endif
                                <span>{{ $tool['short_label'] ?? $tool['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if(count($sidebarSystemTools))
                    <div class="sidebar-tool-group sidebar-tool-group-system">
                        <span class="sidebar-tool-heading">System Supporter</span>
                        @foreach($sidebarSystemTools as $tool)
                            <a href="{{ $tool['url'] }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="sidebar-item sidebar-tool-item sidebar-tool-item-system"
                               data-tooltip="{{ $tool['label'] }}">
                                @if(isset($tool['icon_url']) && $tool['icon_url'])
                                    <img src="{{ $tool['icon_url'] }}" class="h-4 w-4 object-contain" alt="">
                                @else
                                    <x-external-tool-icon :name="$tool['icon']" />
                                @endif
                                <span>{{ $tool['short_label'] ?? $tool['label'] }}</span>
                            </a>
                         @endforeach
                    </div>
                @endif

                @if(count($sidebarAiTools))
                    <div x-data="{ open: localStorage.getItem('dgt-ai-menu-open') === 'true' }" class="sidebar-accordion-group mt-3">
                        <button 
                            @click="open = !open; localStorage.setItem('dgt-ai-menu-open', open)"
                            type="button"
                            class="sidebar-item w-full flex items-center justify-between text-left"
                            aria-label="Toggle AI Tools"
                            data-tooltip="AI Tools"
                        >
                            <div class="flex items-center gap-[0.625rem]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-[18px] h-[18px]">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.25l-.25-1.5-1.5-.25 1.5-.25.25-1.5.25 1.5 1.5.25-1.5.25-.25 1.5ZM18.259 18.75l-.25-1.5-1.5-.25 1.5-.25.25-1.5.25 1.5 1.5.25-1.5.25-.25 1.5Z" />
                                </svg>
                                <span>AI Tools</span>
                            </div>
                            <svg 
                                xmlns="http://www.w3.org/2000/svg" 
                                fill="none" 
                                viewBox="0 0 24 24" 
                                stroke-width="2.5" 
                                stroke="currentColor" 
                                class="w-3.5 h-3.5 transition-transform duration-100"
                                :class="{ 'rotate-180': open }"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div 
                            id="submenu-ai"
                            x-show="open"
                            x-transition:enter="transition ease-out duration-75"
                            x-transition:enter-start="opacity-0 transform -translate-y-2"
                            x-transition:enter-end="opacity-100 transform translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 transform translate-y-0"
                            x-transition:leave-end="opacity-0 transform -translate-y-2"
                            x-cloak
                            class="sidebar-submenu-list mt-1 space-y-1 relative"
                        >
                             @foreach($sidebarAiTools as $tool)
                                <a href="{{ $tool['url'] }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="sidebar-submenu-item" data-tooltip="{{ $tool['label'] }}">
                                    @if(isset($tool['icon_url']) && $tool['icon_url'])
                                        <img src="{{ $tool['icon_url'] }}" class="h-4.5 w-4.5 object-contain" alt="">
                                    @else
                                        <x-external-tool-icon :name="$tool['icon'] ?? 'sparkles'" />
                                    @endif
                                    <span>{{ $tool['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @endunless
                @endcan




                <!-- Reports -->
                @can('reports.view')
                {{--
                <span class="sidebar-section-label">Analytics</span>

                <a href="{{ route('reports.index') }}"
                   class="sidebar-item {{ request()->routeIs('reports.*') ? 'active' : '' }}"
                   id="nav-reports" data-tooltip="System Reports">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z"/>
                    </svg>
                    <span>Reports</span>
                </a>
                --}}
                @endcan

                <!-- Admin -->
                @if(auth()->check() && (auth()->user()->canany(['users.view', 'roles.view', 'security.view', 'backup.view']) || auth()->user()->hasRole('super-admin') || auth()->user()->canAccessMaintenance() || auth()->user()->canManageClockSounds()))
                <span class="sidebar-section-label">Administration</span>


                @can('users.view')
                <a href="{{ route('admin.users.index') }}"
                   class="sidebar-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                   id="nav-users" data-tooltip="User Management">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                    </svg>
                    <span>Users</span>
                </a>
                @endcan


                @hasanyrole('super-admin|admin-digital')
                <div x-data="{ open: {{ request()->routeIs('admin.labels.*') || request()->routeIs('admin.smm-classes.*') ? 'true' : 'false' }} }" class="sidebar-accordion-group">
                    <button type="button"
                        @click="open = !open"
                        class="sidebar-item w-full justify-between {{ request()->routeIs('admin.labels.*') || request()->routeIs('admin.smm-classes.*') ? 'active' : '' }}"
                        id="nav-labels-toggle"
                        aria-expanded="open"
                        data-tooltip="Labels">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-[18px] h-[18px]">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/>
                            </svg>
                            <span>Labels</span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform flex-shrink-0"
                             :class="{'rotate-180': open}"
                             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </button>
                    <div x-show="open" x-collapse class="sidebar-submenu-list mt-1 space-y-1 relative">
                        <a href="{{ route('admin.labels.index') }}"
                           class="sidebar-submenu-item {{ request()->routeIs('admin.labels.*') ? 'active' : '' }}">
                            <span>Team Labels</span>
                        </a>
                        <a href="{{ route('admin.smm-classes.index') }}"
                           class="sidebar-submenu-item {{ request()->routeIs('admin.smm-classes.*') ? 'active' : '' }}">
                            <span>Class Labels</span>
                        </a>
                    </div>
                </div>
                @endhasanyrole
                
                @hasanyrole('super-admin|admin-digital')
                <a href="{{ route('admin.popup-ads.index') }}"
                   class="sidebar-item {{ request()->routeIs('admin.popup-ads.*') ? 'active' : '' }}"
                   id="nav-popup-ads" data-tooltip="Popup Ads">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>Popup Ads</span>
                </a>
                @endhasanyrole

                @if(auth()->check() && auth()->user()->canManageClockSounds())
                <a href="{{ route('admin.meeting-alarms.index') }}"
                   class="sidebar-item {{ request()->routeIs('admin.meeting-alarms.*') ? 'active' : '' }}"
                   id="nav-meeting-alarms" data-tooltip="Meeting Alarms">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                    <span>Meeting Alarms</span>
                </a>
                @endif

                @can('security.view')
                <a href="{{ route('admin.security.index') }}"
                   class="sidebar-item {{ request()->routeIs('admin.security.*') ? 'active' : '' }}"
                   id="nav-security" data-tooltip="Security Activity">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                    </svg>
                    <span>Security</span>
                </a>
                @endcan



                @hasanyrole('super-admin|admin-digital')
                <a href="{{ route('admin.settings.index') }}"
                   class="sidebar-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"
                   aria-label="External Systems" data-tooltip="System Settings">
                    <x-external-tool-icon name="link" class="w-5 h-5 flex-shrink-0" />
                    <span>External Systems</span>
                </a>
                <a href="{{ route('admin.maintenance.index') }}"
                   class="sidebar-item {{ request()->routeIs('admin.maintenance.*') ? 'active' : '' }}"
                   aria-label="Maintenance System" title="Maintenance System">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.829M11.42 15.17l-3.976-3.976c-.845-.845-2.023-1.12-3.136-.788l-.513.153a.75.75 0 01-.933-.933l.153-.513c.332-1.113.057-2.291-.788-3.136l-3.976-3.976a2.652 2.652 0 013.75-3.75l3.976 3.976c.845.845 2.023 1.12 3.136.788l.513-.153a.75.75 0 01.933.933l-.153.513c-.332 1.113-.057 2.291.788 3.136l3.976 3.976A2.652 2.652 0 0111.42 15.17z" />
                    </svg>
                    <span>Maintenance System</span>
                </a>
                @endhasanyrole

                @if(auth()->user()->canAccessMaintenance())
                <a href="{{ route('system.health.index') }}"
                   class="sidebar-item {{ request()->routeIs('system.health.*') ? 'active' : '' }}"
                   id="nav-system-health" data-tooltip="System Health">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                    <span>System Health</span>
                </a>
                @endif
                @endif

            </nav>

            <!-- User profile at bottom -->
            <div class="sidebar-footer">
                <div x-data="dropdown" class="relative">
                    <button
                        @click="toggle"
                        class="sidebar-item w-full text-left"
                        id="sidebar-user-menu"
                        aria-haspopup="true"
                        :aria-expanded="open"
                    >
                        <img
                            src="{{ auth()->user()?->avatar_url }}"
                            alt="{{ auth()->user()?->name }}"
                            class="avatar avatar-sm"
                        >
                        <div class="flex-1 min-w-0">
                            <div class="text-[0.8125rem] font-semibold text-slate-200 truncate">{{ auth()->user()?->name }}</div>
                            <div class="text-[0.7rem] text-slate-400 truncate">{{ auth()->user()?->role_display }}</div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-slate-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </button>

                    <!-- User dropdown menu -->
                    <div
                        x-show="open"
                        @click.outside="close"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="dropdown-menu absolute bottom-full left-0 right-0 mb-2"
                        x-cloak
                        style="display: none;"
                        role="menu"
                    >
                        <a href="{{ route('profile.show') }}" class="dropdown-item hover:!bg-indigo-600 hover:!text-white" role="menuitem" id="menu-profile">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                            </svg>
                            My Profile
                        </a>
                        <!-- Neon Theme Switcher Item -->
                        <button type="button" @click="toggleNeonTheme()" class="dropdown-item w-full text-left hover:!bg-sky-600 hover:!text-white group flex items-center justify-between" role="menuitem" id="menu-neon-toggle">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-cyan-400 group-hover:text-white transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/>
                                </svg>
                                <span x-text="isNeon ? 'Switch to Normal Theme' : 'Switch to Neon Theme'">Switch to Neon Theme</span>
                            </span>
                            <span x-show="isNeon" class="text-[9px] px-1.5 py-0.2 rounded-full font-black bg-cyan-400/20 text-cyan-300 border border-cyan-400/40">ACTIVE</span>
                            <span x-show="!isNeon" class="text-[9px] px-1.5 py-0.2 rounded-full font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">NEW</span>
                        </button>
                        <a href="{{ route('settings') }}" class="dropdown-item hover:!bg-indigo-600 hover:!text-white" role="menuitem" id="menu-settings">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            </svg>
                            Settings
                        </a>
                        <a href="{{ route('downloads.mac-app') }}" class="dropdown-item hover:!bg-indigo-600 hover:!text-white" role="menuitem" id="menu-macos-app">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                            </svg>
                            Download App
                        </a>
                        <hr class="border-[var(--border-color)] my-1">
                        <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                            @csrf
                            <button type="submit" class="dropdown-item danger w-full hover:!bg-rose-600 hover:!text-white" role="menuitem" id="menu-logout">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25"/>
                                </svg>
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <script>
                (() => {
                    // Pre-expand submenus based on localStorage so scroll height is correct immediately
                    const websitesOpen = localStorage.getItem('dgt-websites-menu-open') === 'true' || {{ request()->routeIs('websites.*') ? 'true' : 'false' }};
                    if (websitesOpen) {
                        const el = document.getElementById('submenu-websites');
                        if (el) {
                            el.style.display = 'block';
                            el.removeAttribute('x-cloak');
                        }
                    }
                    const aiOpen = localStorage.getItem('dgt-ai-menu-open') === 'true';
                    if (aiOpen) {
                        const el = document.getElementById('submenu-ai');
                        if (el) {
                            el.style.display = 'block';
                            el.removeAttribute('x-cloak');
                        }
                    }

                    const sidebar = document.getElementById('sidebar');
                    if (! sidebar) return;

                    const savedScrollTop = Number(localStorage.getItem('dgt-sidebar-scroll-top') || 0);
                    if (savedScrollTop > 0) {
                        sidebar.scrollTop = savedScrollTop;
                        if (sidebar.scrollTop >= savedScrollTop - 5) {
                            sidebar.dataset.scrollRestored = 'true';
                        }
                    }
                })();
            </script>
        </aside>

        <!-- ── Main Content ─────────────────────────────────────────────── -->
        <div class="main-wrapper">

            <!-- Top Navigation Bar -->
            <header class="topbar" role="banner">
                <div class="flex items-center gap-3">
                    <!-- Toggle/Expand Sidebar Button (Desktop) -->
                    <button type="button"
                            x-show="$store.sidebar.collapsed"
                            @click="$store.sidebar.expandSidebar()"
                            class="sidebar-expand-btn btn btn-secondary btn-icon hidden lg:inline-flex active:scale-95 transition-all duration-75"
                            title="Show sidebar"
                            aria-label="Show sidebar"
                            x-cloak>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>

                    <!-- Hamburger Button (Mobile) -->
                    <button type="button"
                            @click="$store.sidebar.toggleMobile()"
                            class="mobile-menu-btn lg:hidden"
                            title="Toggle menu"
                            aria-label="Toggle menu">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>

                    @if(!View::hasSection('hide_back'))
                    <button type="button"
                            @if(View::hasSection('back_url'))
                                onclick="window.Turbo ? Turbo.visit('@yield('back_url')') : window.location.href='@yield('back_url')'"
                            @else
                                onclick="window.history.length > 1 ? window.history.back() : (window.Turbo ? Turbo.visit('{{ route('boards.workspaces') }}') : window.location.href='{{ route('boards.workspaces') }}')"
                            @endif
                            class="mobile-back-btn"
                            title="Back"
                            aria-label="Back to previous page">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        <span class="hidden sm:inline text-xs font-bold">Back</span>
                    </button>
                    @endif
                    <!-- Page Title (visible on all screens) -->
                    <div class="mobile-topbar-title">
                        <p class="mobile-topbar-title-text">@yield('title', 'DIGITAL SYSTEM')</p>
                    </div>
                </div>

                <div class="topbar-actions ml-auto">

                    <!-- Cambodia Clock -->
                    <div x-data="cambodiaClock()" x-init="start()" class="hidden md:flex items-center gap-1.5 text-indigo-700 dark:text-indigo-400 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span x-text="dateStr" class="text-sm font-bold uppercase tracking-wide"></span>
                        <div class="w-[1px] h-5 bg-indigo-300 dark:bg-indigo-700/80 mx-1.5"></div>
                        <span x-text="timeStr" class="text-xl font-black tracking-tight" style="font-variant-numeric: tabular-nums;"></span>
                    </div>

                    <!-- Dark Mode Pill Toggle (desktop) -->
                    <div class="theme-pill-toggle cursor-pointer select-none" @click="toggleTheme()" :title="theme === 'dark' ? 'Switch to Light' : 'Switch to Dark'" role="button" tabindex="0" @keydown.enter="toggleTheme()" @keydown.space.prevent="toggleTheme()" aria-label="Toggle dark mode" id="topbar-theme-toggle">
                        <!-- Sun icon -->
                        <span class="theme-pill-icon" :class="{ 'active': theme !== 'dark' }" @click.stop="setTheme('light')" title="Switch to Light mode">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                            </svg>
                        </span>
                        <!-- Sliding knob -->
                        <span class="theme-pill-knob" :class="{ 'dark-knob': theme === 'dark' }"></span>
                        <!-- Moon icon -->
                        <span class="theme-pill-icon" :class="{ 'active': theme === 'dark' }" @click.stop="setTheme('dark')" title="Switch to Dark mode">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                            </svg>
                        </span>
                    </div>

                    <!-- Dark Mode Icon-only Button (mobile only) -->
                    <button type="button"
                            @click="toggleTheme()"
                            class="mobile-theme-icon-btn hidden items-center justify-center w-9 h-9 rounded-xl border border-[var(--border-color)] bg-[var(--bg-card)] text-[var(--text-secondary)] transition-all active:scale-90"
                            :title="theme === 'dark' ? 'Switch to Light' : 'Switch to Dark'"
                            aria-label="Toggle dark mode">
                        <!-- Sun icon (light mode) -->
                        <svg x-show="theme === 'dark'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                        </svg>
                        <!-- Moon icon (dark mode) -->
                        <svg x-show="theme !== 'dark'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                        </svg>
                    </button>

                    <div class="relative" x-data="notificationSystem()" x-init="initNotifications()" @kiuq:realtime-notification.window="open = false">
                        <button class="btn btn-secondary btn-icon relative hover:!bg-blue-600 hover:!text-white hover:!border-blue-600 transition-all duration-75"
                                @click="toggleOpen()"
                                aria-label="Open notifications"
                                :aria-expanded="open">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                            </svg>
                            <template x-if="unreadCount > 0">
                                <span class="absolute -top-1.5 -right-1.5 min-w-4 h-4 px-1 rounded-full bg-rose-500 text-white text-[9px] font-black leading-4 text-center shadow shadow-rose-500/30 border border-white" x-text="badgeCount()"></span>
                            </template>
                        </button>

                        <div x-show="open" @click.outside="open = false" x-cloak style="display: none;"
                             class="notif-panel absolute right-0 mt-2 w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl z-50 sm:w-96"
                             x-transition:enter="transition ease-out duration-75"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             role="menu">
                            <div class="border-b border-slate-200/70 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-black text-slate-900 dark:text-slate-100">Recent activity</h3>
                                        <p class="mt-0.5 text-xs font-semibold text-slate-500 dark:text-slate-400" x-text="unreadCount > 0 ? unreadCount + ' unread notification' + (unreadCount === 1 ? '' : 's') : 'All notifications are read'"></p>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <button type="button"
                                                x-show="canPin"
                                                @click="openAnnouncementModal()"
                                                class="inline-flex items-center gap-1 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 dark:text-amber-400 px-2 py-1 text-[11px] font-black transition border border-amber-500/20 shadow-xs"
                                                title="Pin an announcement to top for all users">
                                            <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                                            Pin
                                        </button>
                                        <button type="button"
                                                x-show="unreadCount > 0"
                                                @click="markAllAsRead()"
                                                class="rounded-lg px-2 py-1 text-[11px] font-black text-indigo-700 dark:text-indigo-400 transition hover:bg-indigo-50 dark:hover:bg-indigo-950/40">
                                            Mark all as read
                                        </button>
                                    </div>
                                </div>
                                <button type="button"
                                        @click="requestBrowserPermission()"
                                        class="mt-3 flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800/80 px-3 py-2 text-left text-xs font-bold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-800"
                                        :disabled="permissionBusy">
                                    <span class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31"/>
                                        </svg>
                                        Browser notifications
                                    </span>
                                    <span class="rounded-full bg-slate-100 dark:bg-slate-700 px-2 py-0.5 text-[10px] font-black uppercase text-slate-500 dark:text-slate-300" x-text="browserPermissionLabel()"></span>
                                </button>
                                
                                <button type="button"
                                        @click="toggleMute()"
                                        class="mt-2 flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800/80 px-3 py-2 text-left text-xs font-bold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-800">
                                    <span class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                                        </svg>
                                        Mute in-app popups
                                    </span>
                                    <div class="relative inline-flex h-4 w-7 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-100 ease-in-out"
                                         :class="notificationsMuted ? 'bg-indigo-600' : 'bg-slate-200 dark:bg-slate-700'"
                                         role="switch" :aria-checked="notificationsMuted.toString()">
                                        <span aria-hidden="true" class="pointer-events-none inline-block h-3 w-3 transform rounded-full bg-white shadow ring-0 transition duration-100 ease-in-out"
                                              :class="notificationsMuted ? 'translate-x-3' : 'translate-x-0'"></span>
                                    </div>
                                </button>
                            </div>

                            <div class="max-h-96 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 scrollbar-thin">
                                <template x-for="notif in notifications" :key="notif.id">
                                    <div class="group relative flex w-full items-start gap-3 p-3.5 text-left transition cursor-pointer select-none"
                                         :class="notif.is_pinned 
                                             ? 'bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border-l-4 border-amber-500 hover:bg-amber-500/15' 
                                             : (isUnread(notif) ? 'bg-indigo-50/40 hover:bg-slate-50 dark:bg-indigo-950/20 dark:hover:bg-slate-800/40' : 'bg-white hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/40')"
                                         @click="clickNotification(notif)">
                                        <img :src="actorAvatar(notif)"
                                             class="mt-0.5 h-9 w-9 flex-shrink-0 rounded-full border border-slate-200 dark:border-slate-700 object-cover shadow-sm"
                                             :alt="actorName(notif)">
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center justify-between gap-1">
                                                <span class="flex items-center gap-1.5 min-w-0">
                                                    <span class="truncate text-xs font-black text-slate-900 dark:text-slate-100" x-text="actorName(notif)"></span>
                                                    <template x-if="notif.is_pinned">
                                                        <span class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.2 bg-amber-500/15 text-amber-700 dark:text-amber-400 text-[9px] font-black tracking-wide border border-amber-500/30 flex-shrink-0">
                                                            <svg class="w-2.5 h-2.5 fill-current" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                                                            PINNED
                                                        </span>
                                                    </template>
                                                </span>
                                                
                                                <span class="flex items-center gap-1.5 flex-shrink-0">
                                                    <span class="text-[10px] font-bold text-slate-400" x-text="notificationTime(notif)"></span>
                                                    
                                                    <!-- Unpin Button (Visible if canPin and notif is pinned) -->
                                                    <template x-if="canPin && notif.is_pinned">
                                                        <button type="button"
                                                                @click.stop="unpinNotification(notif)"
                                                                class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-400 border border-slate-200 dark:border-slate-700 transition shadow-xs"
                                                                title="Unpin notification">
                                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            Unpin
                                                        </button>
                                                    </template>
                                                    
                                                    <!-- Pin Button (Visible on hover if canPin and not pinned) -->
                                                    <template x-if="canPin && !notif.is_pinned">
                                                        <button type="button"
                                                                @click.stop="openPinModal(notif)"
                                                                class="opacity-0 group-hover:opacity-100 focus:opacity-100 inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 hover:bg-amber-100 border border-amber-200 dark:border-amber-800 transition shadow-xs"
                                                                title="Pin notification to top for all users">
                                                            <svg class="w-2.5 h-2.5 fill-current" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                                                            Pin
                                                        </button>
                                                    </template>
                                                </span>
                                            </span>
                                            
                                            <span class="mt-0.5 block text-xs font-semibold leading-5 text-slate-600 dark:text-slate-300 line-clamp-2" x-text="notificationAction(notif)" :title="(notif?.data?.message || notif?.data?.description || '')"></span>
                                            
                                            <span class="mt-1 flex flex-wrap items-center gap-1.5">
                                                <span x-show="boardName(notif)" class="rounded-md bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[10px] font-black text-slate-600 dark:text-slate-300" x-text="boardName(notif)"></span>
                                                <span x-show="cardName(notif)" class="rounded-md bg-indigo-50 dark:bg-indigo-950/40 px-1.5 py-0.5 text-[10px] font-black text-indigo-700 dark:text-indigo-400" x-text="cardName(notif)"></span>
                                                
                                                <template x-if="notif.is_pinned && notif.expires_at_human">
                                                    <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 dark:bg-rose-950/30 px-1.5 py-0.5 text-[10px] font-bold text-rose-600 dark:text-rose-400 border border-rose-200/60 dark:border-rose-900/40">
                                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        <span x-text="'Expires ' + notif.expires_at_human"></span>
                                                    </span>
                                                </template>
                                                <template x-if="notif.is_pinned && !notif.expires_at_human">
                                                    <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                                                        No expiry
                                                    </span>
                                                </template>
                                            </span>
                                        </span>
                                        <span class="mt-2 h-2 w-2 flex-shrink-0 rounded-full"
                                              :class="isUnread(notif) ? 'bg-indigo-600 shadow-sm shadow-indigo-600/40' : (notif.is_pinned ? 'bg-amber-400' : 'bg-slate-200 dark:bg-slate-700')"
                                              :title="isUnread(notif) ? 'Unread' : 'Read'"></span>
                                    </div>
                                </template>
                                <template x-if="notifications.length === 0">
                                    <div class="px-4 py-12 text-center">
                                        <p class="text-sm font-black text-slate-700 dark:text-slate-200">No recent activity</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-400">New board and card updates will appear here.</p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Teleported Modals for Pinning and Announcement -->
                        <template x-teleport="body">
                            <!-- Pin Existing Notification Modal -->
                            <div x-show="pinModalOpen" 
                                 x-transition.opacity.duration.200ms
                                 class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-[999999]"
                                 style="display: none;"
                                 @keydown.escape.window="pinModalOpen = false">
                                <div x-show="pinModalOpen"
                                     @click.outside="pinModalOpen = false"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl overflow-hidden w-full max-w-md border border-slate-200 dark:border-slate-800 flex flex-col">
                                    
                                    <!-- Header -->
                                    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/30">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                                            </div>
                                            <div>
                                                <h3 class="text-sm font-black text-slate-900 dark:text-slate-100">Pin Notification to Top</h3>
                                                <p class="text-[11px] font-semibold text-slate-500">All users will see this notification pinned on top</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pinModalOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-lg">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>

                                    <!-- Body -->
                                    <div class="p-5 space-y-4">
                                        <!-- Selected Notification Preview -->
                                        <template x-if="pinningNotif">
                                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 flex items-start gap-3">
                                                <img :src="actorAvatar(pinningNotif)" class="w-8 h-8 rounded-full border border-slate-200 dark:border-slate-700 object-cover flex-shrink-0">
                                                <div class="min-w-0 flex-1">
                                                    <div class="text-xs font-black text-slate-900 dark:text-slate-100" x-text="actorName(pinningNotif)"></div>
                                                    <div class="text-xs text-slate-600 dark:text-slate-300 font-medium line-clamp-2 mt-0.5" x-text="notificationAction(pinningNotif)"></div>
                                                    <div class="mt-1 flex gap-1">
                                                        <span x-show="boardName(pinningNotif)" class="rounded bg-slate-200/60 dark:bg-slate-700 px-1 py-0.5 text-[9px] font-bold text-slate-600 dark:text-slate-300" x-text="boardName(pinningNotif)"></span>
                                                        <span x-show="cardName(pinningNotif)" class="rounded bg-indigo-100/60 dark:bg-indigo-950 px-1 py-0.5 text-[9px] font-bold text-indigo-700 dark:text-indigo-300" x-text="cardName(pinningNotif)"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Schedule Expiry Selector -->
                                        <div>
                                            <label class="block text-xs font-black text-slate-800 dark:text-slate-200 mb-2">
                                                Schedule Time to Disappear (Auto-Expire)
                                            </label>
                                            <div class="grid grid-cols-3 gap-2">
                                                <button type="button" @click="pinDuration = '1h'" 
                                                        :class="pinDuration === '1h' ? 'bg-amber-500 text-white font-black border-amber-600 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                                        class="py-2 px-3 text-xs rounded-xl border font-bold transition text-center">
                                                    1 Hour
                                                </button>
                                                <button type="button" @click="pinDuration = '12h'" 
                                                        :class="pinDuration === '12h' ? 'bg-amber-500 text-white font-black border-amber-600 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                                        class="py-2 px-3 text-xs rounded-xl border font-bold transition text-center">
                                                    12 Hours
                                                </button>
                                                <button type="button" @click="pinDuration = '24h'" 
                                                        :class="pinDuration === '24h' ? 'bg-amber-500 text-white font-black border-amber-600 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                                        class="py-2 px-3 text-xs rounded-xl border font-bold transition text-center">
                                                    24 Hours
                                                </button>
                                                <button type="button" @click="pinDuration = '3d'" 
                                                        :class="pinDuration === '3d' ? 'bg-amber-500 text-white font-black border-amber-600 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                                        class="py-2 px-3 text-xs rounded-xl border font-bold transition text-center">
                                                    3 Days
                                                </button>
                                                <button type="button" @click="pinDuration = '7d'" 
                                                        :class="pinDuration === '7d' ? 'bg-amber-500 text-white font-black border-amber-600 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                                        class="py-2 px-3 text-xs rounded-xl border font-bold transition text-center">
                                                    7 Days
                                                </button>
                                                <button type="button" @click="pinDuration = 'never'" 
                                                        :class="pinDuration === 'never' ? 'bg-amber-500 text-white font-black border-amber-600 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                                        class="py-2 px-3 text-xs rounded-xl border font-bold transition text-center">
                                                    Never
                                                </button>
                                            </div>

                                            <div class="mt-2">
                                                <button type="button" @click="pinDuration = 'custom'" 
                                                        :class="pinDuration === 'custom' ? 'bg-amber-500/15 text-amber-800 dark:text-amber-300 border-amber-500 font-black' : 'bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 border-dashed border-slate-300 dark:border-slate-700'"
                                                        class="w-full py-2 px-3 text-xs rounded-xl border font-bold transition text-center flex items-center justify-center gap-1.5">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                    Pick Custom Date & Time
                                                </button>

                                                <div x-show="pinDuration === 'custom'" class="mt-2">
                                                    <input type="datetime-local" 
                                                           x-model="customPinExpiresAt"
                                                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 font-semibold">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                                            ℹ️ When the scheduled time arrives, the notification will automatically unpin and return to regular order. You can also manually unpin it at any time.
                                        </p>
                                    </div>

                                    <!-- Footer -->
                                    <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-800/40 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2">
                                        <button type="button" 
                                                @click="pinModalOpen = false"
                                                class="px-3 py-2 text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition">
                                            Cancel
                                        </button>
                                        <button type="button" 
                                                @click="confirmPin()"
                                                :disabled="isPinning"
                                                class="px-4 py-2 text-xs font-black text-white bg-amber-500 hover:bg-amber-600 disabled:opacity-50 rounded-xl transition flex items-center gap-1.5 shadow-sm shadow-amber-500/30">
                                            <template x-if="isPinning">
                                                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                            </template>
                                            <template x-if="!isPinning">
                                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                                            </template>
                                            <span x-text="isPinning ? 'Pinning...' : 'Pin to Top'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Create & Pin Announcement Modal -->
                            <div x-show="announcementModalOpen" 
                                 x-transition.opacity.duration.200ms
                                 class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-[999999]"
                                 style="display: none;"
                                 @keydown.escape.window="announcementModalOpen = false">
                                <div x-show="announcementModalOpen"
                                     @click.outside="announcementModalOpen = false"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl overflow-hidden w-full max-w-lg border border-slate-200 dark:border-slate-800 flex flex-col">
                                    
                                    <!-- Header -->
                                    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/30">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                                            </div>
                                            <div>
                                                <h3 class="text-sm font-black text-slate-900 dark:text-slate-100">Pin Announcement</h3>
                                                <p class="text-[11px] font-semibold text-slate-500">Broadcast a pinned message on top for all team members</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="announcementModalOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-lg">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>

                                    <!-- Body -->
                                    <div class="p-5 space-y-4">
                                        <div>
                                            <label class="block text-xs font-black text-slate-800 dark:text-slate-200 mb-1">
                                                Announcement Title <span class="text-rose-500">*</span>
                                            </label>
                                            <input type="text" 
                                                   x-model="announcementTitle"
                                                   placeholder="e.g. System Maintenance tonight at 10 PM"
                                                   class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 font-semibold">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-black text-slate-800 dark:text-slate-200 mb-1">
                                                Message / Details <span class="text-rose-500">*</span>
                                            </label>
                                            <textarea rows="3"
                                                      x-model="announcementMessage"
                                                      placeholder="Write the announcement details here..."
                                                      class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 font-semibold"></textarea>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-black text-slate-800 dark:text-slate-200 mb-1">
                                                Action Link (Optional)
                                            </label>
                                            <input type="text" 
                                                   x-model="announcementLink"
                                                   placeholder="https://... or /social-media"
                                                   class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 font-semibold">
                                        </div>

                                        <!-- Schedule Expiry Selector -->
                                        <div>
                                            <label class="block text-xs font-black text-slate-800 dark:text-slate-200 mb-2">
                                                Schedule Time to Disappear (Auto-Expire)
                                            </label>
                                            <div class="grid grid-cols-3 gap-2">
                                                <button type="button" @click="announcementDuration = '1h'" 
                                                        :class="announcementDuration === '1h' ? 'bg-amber-500 text-white font-black border-amber-600 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                                        class="py-2 px-3 text-xs rounded-xl border font-bold transition text-center">
                                                    1 Hour
                                                </button>
                                                <button type="button" @click="announcementDuration = '12h'" 
                                                        :class="announcementDuration === '12h' ? 'bg-amber-500 text-white font-black border-amber-600 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                                        class="py-2 px-3 text-xs rounded-xl border font-bold transition text-center">
                                                    12 Hours
                                                </button>
                                                <button type="button" @click="announcementDuration = '24h'" 
                                                        :class="announcementDuration === '24h' ? 'bg-amber-500 text-white font-black border-amber-600 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                                        class="py-2 px-3 text-xs rounded-xl border font-bold transition text-center">
                                                    24 Hours
                                                </button>
                                                <button type="button" @click="announcementDuration = '3d'" 
                                                        :class="announcementDuration === '3d' ? 'bg-amber-500 text-white font-black border-amber-600 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                                        class="py-2 px-3 text-xs rounded-xl border font-bold transition text-center">
                                                    3 Days
                                                </button>
                                                <button type="button" @click="announcementDuration = '7d'" 
                                                        :class="announcementDuration === '7d' ? 'bg-amber-500 text-white font-black border-amber-600 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                                        class="py-2 px-3 text-xs rounded-xl border font-bold transition text-center">
                                                    7 Days
                                                </button>
                                                <button type="button" @click="announcementDuration = 'never'" 
                                                        :class="announcementDuration === 'never' ? 'bg-amber-500 text-white font-black border-amber-600 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                                        class="py-2 px-3 text-xs rounded-xl border font-bold transition text-center">
                                                    Never
                                                </button>
                                            </div>

                                            <div class="mt-2">
                                                <button type="button" @click="announcementDuration = 'custom'" 
                                                        :class="announcementDuration === 'custom' ? 'bg-amber-500/15 text-amber-800 dark:text-amber-300 border-amber-500 font-black' : 'bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 border-dashed border-slate-300 dark:border-slate-700'"
                                                        class="w-full py-2 px-3 text-xs rounded-xl border font-bold transition text-center flex items-center justify-center gap-1.5">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                    Pick Custom Date & Time
                                                </button>

                                                <div x-show="announcementDuration === 'custom'" class="mt-2">
                                                    <input type="datetime-local" 
                                                           x-model="customAnnouncementExpiresAt"
                                                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 font-semibold">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Footer -->
                                    <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-800/40 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2">
                                        <button type="button" 
                                                @click="announcementModalOpen = false"
                                                class="px-3 py-2 text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition">
                                            Cancel
                                        </button>
                                        <button type="button" 
                                                @click="submitAnnouncement()"
                                                :disabled="isSubmittingAnnouncement"
                                                class="px-4 py-2 text-xs font-black text-white bg-amber-500 hover:bg-amber-600 disabled:opacity-50 rounded-xl transition flex items-center gap-1.5 shadow-sm shadow-amber-500/30">
                                            <template x-if="isSubmittingAnnouncement">
                                                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                            </template>
                                            <template x-if="!isSubmittingAnnouncement">
                                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                                            </template>
                                            <span x-text="isSubmittingAnnouncement ? 'Posting...' : 'Post & Pin to Top'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                    </div>



                    @php
                        // CRM removed — no pending handler confirmations needed.
                        $pendingHandlerConfirmations = collect();
                    @endphp
                    <div class="relative" x-data="dropdown" @kiuq:realtime-notification.window="open = false">
                        <button type="button"
                                @click="toggle"
                                class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-2 py-1.5 text-left shadow-sm transition hover:border-indigo-600 hover:bg-indigo-50 dark-user-btn"
                                id="topbar-user-menu"
                                aria-haspopup="true"
                                :aria-expanded="open">
                            <span class="relative inline-block">
                                <img src="{{ auth()->user()?->avatar_url }}"
                                     alt="{{ auth()->user()?->name }}"
                                     onerror="this.onerror=null; this.src='{{ \App\Models\User::initialsAvatarDataUri(auth()->user()?->name ?? 'User', auth()->user()?->avatar_color ?? '#64748b') }}';"
                                     class="avatar avatar-sm ring-2 ring-white">
                                @if($pendingHandlerConfirmations->isNotEmpty())
                                <span class="absolute -top-1 -right-1 min-w-4 h-4 px-1 rounded-full bg-amber-500 text-white text-[9px] font-black leading-4 text-center shadow border border-white">{{ $pendingHandlerConfirmations->count() }}</span>
                                @endif
                            </span>
                            <span class="hidden sm:block min-w-0">
                                <span class="block max-w-36 truncate text-sm font-black leading-none text-slate-800">{{ auth()->user()?->name }}</span>
                                <span class="mt-0.5 block max-w-36 truncate text-[11px] font-semibold text-slate-400">{{ auth()->user()?->role_display }}</span>
                            </span>
                            <svg class="hidden h-4 w-4 text-slate-400 sm:block" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </button>

                        <div x-show="open"
                             @click.outside="close"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="dropdown-menu absolute right-0 mt-2 w-64"
                             x-cloak
                             style="display: none;"
                             role="menu">
                            <div class="flex items-center gap-3 border-b border-slate-100 px-2 py-2.5">
                                <img src="{{ auth()->user()?->avatar_url }}" alt="{{ auth()->user()?->name }}" onerror="this.onerror=null; this.src='{{ \App\Models\User::initialsAvatarDataUri(auth()->user()?->name ?? 'User', auth()->user()?->avatar_color ?? '#64748b') }}';" class="avatar avatar-md">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-slate-900">{{ auth()->user()?->name }}</p>
                                    <p class="truncate text-xs font-semibold text-slate-500">{{ auth()->user()?->email }}</p>
                                </div>
                            </div>

                            <a href="{{ route('profile.show') }}" class="dropdown-item hover:!bg-indigo-600 hover:!text-white" role="menuitem" id="topbar-menu-profile">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0"/>
                                </svg>
                                My Profile
                            </a>
                            <!-- Neon Theme Switcher Item -->
                            <button type="button" @click="toggleNeonTheme()" class="dropdown-item w-full text-left hover:!bg-sky-600 hover:!text-white group flex items-center justify-between" role="menuitem" id="topbar-menu-neon-toggle">
                                <span class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-cyan-400 group-hover:text-white transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/>
                                    </svg>
                                    <span x-text="isNeon ? 'Switch to Normal Theme' : 'Switch to Neon Theme'">Switch to Neon Theme</span>
                                </span>
                                <span x-show="isNeon" class="text-[9px] px-1.5 py-0.2 rounded-full font-black bg-cyan-400/20 text-cyan-400 border border-cyan-400/40">ACTIVE</span>
                                <span x-show="!isNeon" class="text-[9px] px-1.5 py-0.2 rounded-full font-bold bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">NEW</span>
                            </button>
                            <a href="{{ route('settings') }}" class="dropdown-item hover:!bg-indigo-600 hover:!text-white" role="menuitem" id="topbar-menu-settings">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87l.22.127c.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992v.255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124l-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87l-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991v-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124l.22-.128c.332-.183.582-.495.644-.869l.214-1.28Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                </svg>
                                Settings
                            </a>
                            <a href="{{ route('downloads.mac-app') }}" class="dropdown-item hover:!bg-indigo-600 hover:!text-white" role="menuitem" id="topbar-menu-macos-app">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                                </svg>
                                Download App
                            </a>
                            <hr class="border-[var(--border-color)] my-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item danger w-full hover:!bg-rose-600 hover:!text-white" role="menuitem" id="topbar-menu-logout">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25"/>
                                    </svg>
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Flash Messages -->
            @if (session('success'))
                <div class="mx-6 mt-4">
                    <div class="alert alert-success animate-fade-in" role="alert">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/>
                        </svg>
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="mx-6 mt-4">
                    <div class="alert alert-error animate-fade-in" role="alert">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm-1.5-9.5a1.5 1.5 0 1 1 3 0v4a1.5 1.5 0 0 1-3 0v-4Zm1.5 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                        </svg>
                        {{ session('error') }}
                    </div>
                </div>
            @endif

            <!-- Main Content Slot -->
            <main class="page-content mobile-page-content" id="main-content" role="main">
                @yield('content')
            </main>

        </div>
    </div>

    <!-- ── Mobile Bottom Navigation Bar ──────────────────────────────────── -->
    <nav class="mobile-bottom-nav lg:hidden" id="mobile-bottom-nav" aria-label="Mobile navigation">
        <div class="mobile-bottom-nav-inner">
            <div id="nav-active-bubble"></div>

            <!-- Home (Everyone) -->
            @can('dashboard.view')
            <a href="{{ route('dashboard') }}"
               class="mobile-nav-item {{ request()->routeIs('dashboard*') ? 'active' : '' }}"
               aria-label="Dashboard">
                <span class="mobile-nav-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
                    </svg>
                </span>
                <span class="mobile-nav-label">Home</span>
            </a>
            @endcan

            <!-- Boards (Everyone) -->
            @can('kanban.view')
            <a href="{{ route('boards.workspaces') }}"
               class="mobile-nav-item {{ (request()->routeIs('boards.*') && !request()->routeIs('boards.reports.*')) ? 'active' : '' }}"
               aria-label="Boards">
                <span class="mobile-nav-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125Z"/>
                    </svg>
                </span>
                <span class="mobile-nav-label">Boards</span>
            </a>
            @endcan

            @if($canSeeApprovalQueue)
                <!-- Approval Queue -->
                <a href="{{ route('approvals.index') }}"
                   class="mobile-nav-item {{ request()->routeIs('approvals.*') ? 'active' : '' }}"
                   aria-label="Approval">
                    <span class="mobile-nav-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375"/>
                        </svg>
                    </span>
                    <span class="mobile-nav-label">Approval</span>
                </a>
            @elseif(isset($userPlanningBoards) && $userPlanningBoards->isNotEmpty())
                @php
                    $mobileTasksCount = $userPlanningBoards->sum('user_tasks_count');
                    $mobileHasDueTomorrow = $userPlanningBoards->sum('due_tomorrow_count') > 0;
                @endphp
                <!-- Tasks Count -->
                <a href="{{ route('tasks.count') }}"
                   class="mobile-nav-item {{ request()->routeIs('tasks.count') ? 'active' : '' }}"
                   aria-label="Tasks Count">
                    <span class="mobile-nav-icon relative">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                        </svg>
                        @if($mobileTasksCount > 0)
                            <span class="absolute -top-1 -right-2 min-w-4 h-4 px-1 rounded-full {{ $mobileHasDueTomorrow ? 'bg-amber-500' : 'bg-indigo-600' }} text-white text-[9px] font-bold flex items-center justify-center">{{ $mobileTasksCount }}</span>
                        @endif
                    </span>
                    <span class="mobile-nav-label">Tasks Count</span>
                </a>
            @endif

            <!-- Social Media (Boss, super-admin, Digital Team) -->
            @if(auth()->user()?->hasAnyRole(['boss', 'super-admin', 'admin-digital', 'digital-team', 'social_qc', 'social_admin']))
            <a href="{{ route('social-media.dashboard') }}"
               class="mobile-nav-item {{ request()->routeIs('social-media.*') ? 'active' : '' }}"
               aria-label="Social">
                <span class="mobile-nav-icon">
                    <img src="https://cdn-icons-png.flaticon.com/512/1468/1468269.png" alt="Social" class="w-5 h-5 flex-shrink-0 object-contain">
                </span>
                <span class="mobile-nav-label">Social</span>
            </a>
            @endif

            <!-- Websites (Boss + super-admin) -->
            @if(auth()->user()?->hasAnyRole(['boss', 'super-admin']))
            <a href="{{ route('websites.dashboard') }}"
               class="mobile-nav-item {{ request()->routeIs('websites.*') ? 'active' : '' }}"
               aria-label="Websites">
                <span class="mobile-nav-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253M3 12a8.959 8.959 0 0 0 .284 2.253" />
                    </svg>
                </span>
                <span class="mobile-nav-label">Websites</span>
            </a>
            @endif
            <!-- Notes -->
            <a href="{{ route('notes.private') }}"
               class="mobile-nav-item {{ request()->routeIs('notes.*') ? 'active' : '' }}"
               aria-label="Note">
                <span class="mobile-nav-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </span>
                <span class="mobile-nav-label">Note</span>
            </a>

            <!-- More / Menu trigger -->
            <button type="button"
                    class="mobile-nav-item {{ request()->routeIs('admin.*') || request()->routeIs('reports.*') || request()->routeIs('profile.*') ? 'active' : '' }}"
                    x-data
                    @click="$dispatch('open-mobile-sidebar')"
                    aria-label="More">
                <span class="mobile-nav-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </span>
                <span class="mobile-nav-label">More</span>
            </button>

        </div>
    </nav>

    {{-- Global Toast Container --}}
    <div id="toast-container" class="fixed top-5 right-5 z-[9999] space-y-3 pointer-events-none max-w-[calc(100vw-2rem)] sm:max-w-sm"></div>

    <script>
    window.dgtInitialsAvatar = function(name = 'User', color = '#4f46e5') {
        const cleanName = String(name || 'User').trim().replace(/\s+/g, ' ');
        const parts = cleanName.includes('@') ? [cleanName.split('@')[0]] : cleanName.split(' ').filter(Boolean);
        const initials = (parts.length > 1
            ? (parts[0][0] || '') + (parts[parts.length - 1][0] || '')
            : (parts[0] || 'U').slice(0, 2)
        ).toUpperCase() || 'U';
        const safeColor = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(color) ? color : '#4f46e5';
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128"><rect width="128" height="128" rx="64" fill="${safeColor}"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="#fff" font-family="Inter, Arial, sans-serif" font-size="44" font-weight="800">${initials}</text></svg>`;
        return `data:image/svg+xml;base64,${btoa(svg)}`;
    };

    window.dgtEscapeHtml = function(value = '') {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    };

    // Custom Confirmation Modal — singleton (only one at a time)
    let _confirmModalOpen = false;
    window.confirmModal = function(input) {
        // If a modal is already open, reject immediately so we don't stack
        if (_confirmModalOpen) return Promise.resolve(false);
        _confirmModalOpen = true;

        return new Promise((resolve) => {
            const options = typeof input === 'object' && input !== null ? input : { message: input };
            const title = options.title || 'Confirm action';
            const message = options.message || '';
            const confirmText = options.confirmText || 'Confirm';
            const cancelText = options.cancelText || 'Cancel';
            const tone = options.tone || 'danger';
            const toneMap = {
                danger: {
                    icon: 'bg-rose-100 text-rose-600 ring-rose-200',
                    button: 'bg-rose-600 hover:bg-rose-700 shadow-rose-500/20',
                    glow: 'from-rose-500/12',
                },
                warning: {
                    icon: 'bg-amber-100 text-amber-600 ring-amber-200',
                    button: 'bg-amber-500 hover:bg-amber-600 shadow-amber-500/20',
                    glow: 'from-amber-400/14',
                },
            };
            const theme = toneMap[tone] || toneMap.danger;
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 bg-slate-950/60 backdrop-blur-md z-[9999] flex items-center justify-center p-4 opacity-0 transition-opacity duration-100';
            
            const modal = document.createElement('div');
            modal.className = 'relative overflow-hidden bg-white/85 backdrop-blur-2xl border border-white/70 rounded-3xl shadow-2xl max-w-md w-full p-6 transform scale-95 opacity-0 transition-all duration-100 ring-1 ring-slate-900/5';
            
            modal.innerHTML = `
                <div class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b ${theme.glow} to-transparent"></div>
                <div class="relative flex items-start gap-3 mb-4">
                    <div class="w-11 h-11 rounded-2xl ${theme.icon} ring-1 flex items-center justify-center flex-shrink-0 shadow-sm">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base font-black text-slate-900 leading-tight"></h3>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Please confirm before continuing.</p>
                    </div>
                </div>
                <p class="relative text-sm text-slate-600 font-semibold mb-6 leading-relaxed" id="confirm-modal-msg"></p>
                <div class="relative flex gap-3">
                    <button type="button" id="btn-cancel" class="flex-1 py-2.5 px-4 rounded-xl text-xs font-black text-slate-700 bg-white/80 border border-slate-200 hover:bg-slate-100 transition-colors">${cancelText}</button>
                    <button type="button" id="btn-confirm" class="flex-1 py-2.5 px-4 rounded-xl text-xs font-black text-white ${theme.button} shadow-lg transition-all">${confirmText}</button>
                </div>
            `;
            
            modal.querySelector('h3').textContent = title;
            modal.querySelector('#confirm-modal-msg').innerHTML = message;
            
            overlay.appendChild(modal);
            document.body.appendChild(overlay);
            modal.querySelector('#btn-cancel').focus();
            
            requestAnimationFrame(() => {
                overlay.classList.remove('opacity-0');
                modal.classList.remove('scale-95', 'opacity-0');
            });
            
            let closed = false;
            const close = (result) => {
                if (closed) return;
                closed = true;
                _confirmModalOpen = false;
                document.removeEventListener('keydown', escapeHandler);
                // Animate out
                overlay.classList.add('opacity-0');
                modal.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    if (overlay.parentNode) document.body.removeChild(overlay);
                    resolve(result);
                }, 150);
            };
            
            const escapeHandler = (event) => {
                if (event.key === 'Escape') close(false);
            };

            document.addEventListener('keydown', escapeHandler);
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) close(false);
            });
            modal.querySelector('#btn-cancel').addEventListener('click', () => close(false));
            modal.querySelector('#btn-confirm').addEventListener('click', () => close(true));
        });
    };

    window.promptModal = function(input = {}) {
        return new Promise((resolve) => {
            const options = typeof input === 'object' && input !== null ? input : { value: String(input || '') };
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 bg-slate-950/60 backdrop-blur-md z-[9999] flex items-center justify-center p-4 opacity-0 transition-opacity duration-100';
            const modal = document.createElement('div');
            modal.className = 'relative overflow-hidden bg-white/90 backdrop-blur-2xl border border-white/70 rounded-3xl shadow-2xl max-w-md w-full p-6 transform scale-95 opacity-0 transition-all duration-100 ring-1 ring-slate-900/5';
            modal.innerHTML = `
                <div class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-indigo-500/10 to-transparent"></div>
                <div class="relative mb-4">
                    <h3 class="text-base font-black text-slate-900"></h3>
                    <p class="mt-1 text-sm font-semibold text-slate-500"></p>
                </div>
                <label class="relative block">
                    <span class="mb-1.5 block text-xs font-black uppercase tracking-wide text-slate-400"></span>
                    <input id="prompt-modal-input" type="text" class="form-input w-full text-sm" autocomplete="off">
                    <span id="prompt-modal-error" class="mt-2 hidden text-xs font-bold text-rose-600"></span>
                </label>
                <div class="relative mt-6 flex gap-3">
                    <button type="button" id="prompt-cancel" class="btn btn-secondary flex-1 py-2.5"></button>
                    <button type="button" id="prompt-confirm" class="btn btn-primary flex-1 py-2.5"></button>
                </div>
            `;

            const title = modal.querySelector('h3');
            const message = modal.querySelector('p');
            const label = modal.querySelector('label span');
            const field = modal.querySelector('#prompt-modal-input');
            const error = modal.querySelector('#prompt-modal-error');
            const cancel = modal.querySelector('#prompt-cancel');
            const confirm = modal.querySelector('#prompt-confirm');

            title.textContent = options.title || 'Enter details';
            message.textContent = options.message || '';
            label.textContent = options.inputLabel || 'Value';
            field.value = options.value || '';
            field.placeholder = options.placeholder || '';
            field.readOnly = Boolean(options.readonly);
            cancel.textContent = options.cancelText || 'Cancel';
            confirm.textContent = options.confirmText || 'Save';

            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            let closed = false;
            const close = (value) => {
                if (closed) return;
                closed = true;
                document.removeEventListener('keydown', keyHandler);
                if (overlay.parentNode) overlay.remove();
                resolve(value);
            };

            const submit = () => {
                const value = field.value.trim();
                if (options.required !== false && !value) {
                    error.textContent = options.errorText || 'Please enter a value.';
                    error.classList.remove('hidden');
                    field.focus();
                    return;
                }
                close(value);
            };

            const keyHandler = (event) => {
                if (event.key === 'Escape') close(null);
                if (event.key === 'Enter' && document.activeElement === field) submit();
            };

            requestAnimationFrame(() => {
                overlay.classList.remove('opacity-0');
                modal.classList.remove('scale-95', 'opacity-0');
                field.focus();
                field.select();
            });

            document.addEventListener('keydown', keyHandler);
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) close(null);
            });
            cancel.addEventListener('click', () => close(null));
            confirm.addEventListener('click', submit);
        });
    };

    // Singleton guard so the confirm modal never opens twice for one form
    let _formConfirmInProgress = false;
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!form || !form.matches('form[data-confirm]')) return;

        // Block immediately — before any async work
        if (_formConfirmInProgress || form.dataset.confirmSubmitting === 'true') {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        _formConfirmInProgress = true;

        const ok = await window.confirmModal({
            title: form.dataset.confirmTitle || 'Confirm action',
            message: form.dataset.confirm || 'Are you sure?',
            confirmText: form.dataset.confirmText || 'Confirm',
            tone: form.dataset.confirmTone || 'danger',
        });

        _formConfirmInProgress = false;

        if (ok) {
            form.dataset.confirmSubmitting = 'true';
            form.submit();
        }
    });

    window.playNotificationSound = function() {
        if (localStorage.getItem('dgt_notifications_muted') === 'true') return;
        const audio = document.getElementById('notif-sound');
        if (audio) {
            audio.currentTime = 0;
            audio.volume = 1.0;
            audio.play().catch(e => console.log('Audio play blocked:', e));
        }
    };

    // Global Toast Notification Helper
    window.showToast = function(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `dgt-notification-toast flex items-center gap-3 px-4 py-3 rounded-2xl shadow-2xl text-sm font-bold text-white pointer-events-auto transform translate-x-8 opacity-0 transition-all duration-75 border border-white/10 backdrop-blur-xl ${
            type === 'success' ? 'bg-slate-950/90' : type === 'error' ? 'bg-rose-950/90' : 'bg-slate-900/90'
        }`;
        toast.innerHTML = `
            <span>${type === 'success' ? '✨' : type === 'error' ? '⚠️' : 'ℹ️'}</span>
            <span>${message}</span>
        `;

        container.prepend(toast);

        // Slide in
        setTimeout(() => {
            toast.classList.remove('translate-x-8', 'opacity-0');
        }, 50);

        // Slide out & remove
        setTimeout(() => {
            toast.classList.add('translate-x-8', 'opacity-0');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 3500);
    };

    // Global Premium Clickable Trello-style Rich Notification Toast Helper
    window.showRichNotificationToast = function(data) {
        if (localStorage.getItem('dgt_notifications_muted') === 'true') return;
        if (window.dgtShouldSuppressDuplicateContent?.(data)) return;

        const container = document.getElementById('toast-container');
        if (!container) return;

        const appLogo = data.app_logo || '/favicon.svg';
        const toast = document.createElement('div');
        toast.className = 'dgt-notification-toast flex items-start gap-3 p-4 rounded-3xl shadow-2xl bg-white/95 text-slate-900 border border-slate-200/60 dark:bg-slate-900/95 dark:text-slate-100 dark:border-slate-700/60 pointer-events-auto transform translate-x-8 opacity-0 transition-all duration-75 max-w-sm cursor-pointer hover:border-slate-300/80 dark:hover:border-slate-600 select-none backdrop-blur-2xl ring-1 ring-slate-900/5 dark:ring-white/10';
        
        const actorName = data.actor_name || 'System';
        const avatar = data.actor_avatar || window.dgtInitialsAvatar(actorName);
        const subject = data.card_title || data.board_name || data.customer_name || data.lead_name || data.offer_name || data.logistic_name || '';
        const time = data.created_at ? new Date(data.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'now';
        const actionRaw = (data.description || data.message || 'New activity').replace(/\*/g, '');
        const actionText = window.dgtShortenNotificationText
            ? window.dgtShortenNotificationText(actionRaw, 100)
            : actionRaw;
        const cardTitleMarkup = data.card_title 
            ? `<span class="toast-badge mt-2 inline-flex max-w-full rounded-lg border border-indigo-200 bg-indigo-50 px-2 py-1 text-[11px] font-black text-indigo-700">${window.dgtEscapeHtml(data.card_title)}</span>`
            : '';
        
        toast.innerHTML = `
            <div class="flex-shrink-0 flex items-center gap-2">
                <img src="${appLogo}" alt="App" class="h-6 w-6 rounded" />
                <img src="${avatar}" class="toast-avatar h-6 w-6 rounded-full object-cover ring-1 ring-slate-200" alt="" />
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <p class="toast-actor truncate text-sm font-black text-slate-900">${window.dgtEscapeHtml(actorName)}</p>
                    <span class="toast-time ml-auto text-[10px] font-bold text-slate-500">${window.dgtEscapeHtml(time)}</span>
                </div>
                <p class="toast-body mt-2 text-sm font-semibold leading-snug text-slate-600 line-clamp-2" title="${window.dgtEscapeHtml(actionRaw)}">${window.dgtEscapeHtml(actionText)}</p>
                ${subject && !data.card_title ? `<span class="toast-badge mt-2 inline-flex max-w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-black text-slate-700">${window.dgtEscapeHtml(subject)}</span>` : cardTitleMarkup}
            </div>
            <button class="toast-close-btn flex-shrink-0 ml-1 -mt-1 -mr-1 w-6 h-6 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all" aria-label="Close">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        `;
        
        const closeBtn = toast.querySelector('.toast-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toast.classList.add('translate-x-8', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            });
        }
        
        if (data.link) {
            toast.addEventListener('click', (e) => {
                if (!e.target.closest('.toast-close-btn')) {
                    const targetUrl = new URL(data.link, window.location.origin);
                    if (data.card_id && window.location.pathname === targetUrl.pathname) {
                        window.dispatchEvent(new CustomEvent('kiuq:open-card', { detail: { cardId: data.card_id } }));
                        toast.classList.add('translate-x-8', 'opacity-0');
                        setTimeout(() => toast.remove(), 300);
                    } else {
                        window.location.href = data.link;
                    }
                }
            });
        }
        
        container.prepend(toast);
        
        setTimeout(() => {
            toast.classList.remove('translate-x-8', 'opacity-0');
            window.playNotificationSound?.();
        }, 50);

        setTimeout(() => {
            toast.classList.add('translate-x-8', 'opacity-0');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 6000);
    };

    // Global CRM Notification Card — same pop-up-and-stack idea as the rich
    // Board toast above, but for CRM payloads (tech support cases, call
    // requests, lead reassignment, negative-feedback routing — no
    // actor_name, just a message + link). Deliberately does NOT auto-dismiss
    // like the other toasts do: CRM asked for these to stay stacked in place
    // until manually closed, since a case notification is easy to miss if it
    // vanishes before they've finished reading it. Multiple cards stack
    // naturally in #toast-container's own vertical gap — no extra layout
    // code needed here for that part.
    // Truncate notification body for cards/bell — keeps layout tight.
    window.dgtShortenNotificationText = function(text, maxLen = 96) {
        const cleaned = String(text || '').replace(/\s+/g, ' ').replace(/\*/g, '').trim();
        if (cleaned.length <= maxLen) return cleaned;
        return cleaned.slice(0, Math.max(0, maxLen - 1)).trimEnd() + '…';
    };

    window.showCrmNotificationCard = function(data, id = null) {
        if (localStorage.getItem('dgt_notifications_muted') === 'true') return;

        const container = document.getElementById('toast-container');
        if (!container) return;

        // Hard backstop against duplicate cards for the same notification —
        // whatever upstream path called this twice (fetchData()/handleIncoming()
        // racing, an orphaned poll interval left behind by SPA-style page
        // navigation, ...), the DOM itself is the one source of truth this
        // checks against, so it can't be fooled by any timing issue in the
        // callers' own id-tracking.
        const normId = window.dgtNormalizeNotificationId?.(id) || id;
        if (normId && (
            container.querySelector(`[data-notification-id="${normId}"]`)
            || container.querySelector(`[data-notification-id="notif_${normId}"]`)
            || container.querySelector(`[data-notification-id="${id}"]`)
        )) return;

        if (window.dgtShouldSuppressDuplicateContent?.(data)) return;

        const icons = {
            tech_case_new: '🛠️',
            tech_case_call_request: '📞',
            tech_case_call_completed: '✅',
            call_request_new: '📞',
            lead_reassigned: '👤',
            tech_case_status_changed: '🔄',
            ebay_negative_feedback: '⚠️',
            logistic_problem: '🚚',
            machine_return: '🔄',
        };
        const icon = icons[data.type] || '🔔';
        const shortMessage = window.dgtShortenNotificationText(data.message || 'New update', 90);

        const actorName = data.actor_name || 'DIGITAL SYSTEM';
        const avatarHtml = data.actor_avatar 
            ? `<img src="${data.actor_avatar}" class="toast-avatar h-9 w-9 rounded-full object-cover ring-1 ring-slate-200 shadow-sm" alt="" />`
            : `<div class="toast-avatar flex h-9 w-9 items-center justify-center rounded-full bg-indigo-50 text-lg shadow-sm ring-1 ring-slate-200">${icon}</div>`;

        const card = document.createElement('div');
        card.className = 'dgt-notification-toast flex items-start gap-3 p-4 rounded-3xl shadow-2xl bg-white/95 text-slate-900 border border-slate-200/60 dark:bg-slate-900/95 dark:text-slate-100 dark:border-slate-700/60 pointer-events-auto transform translate-x-8 opacity-0 transition-all duration-75 max-w-sm cursor-pointer hover:border-slate-300/80 dark:hover:border-slate-600 select-none backdrop-blur-2xl ring-1 ring-slate-900/5 dark:ring-white/10';
        if (normId) card.dataset.notificationId = normId;

        card.innerHTML = `
            <div class="flex-shrink-0 flex items-center gap-2">
                ${avatarHtml}
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <p class="toast-actor truncate text-sm font-black text-slate-900">${window.dgtEscapeHtml(actorName)}</p>
                    <span class="toast-time ml-auto text-[10px] font-bold text-slate-500">now</span>
                </div>
                <p class="toast-body mt-1.5 text-sm font-semibold leading-snug text-slate-600 line-clamp-2" title="${window.dgtEscapeHtml(data.message || 'New update')}">${window.dgtEscapeHtml(shortMessage)}</p>
            </div>
            <button class="toast-close-btn flex-shrink-0 ml-1 -mt-1 -mr-1 w-6 h-6 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all" aria-label="Close">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        `;

        const closeBtn = card.querySelector('.toast-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                card.classList.add('translate-x-8', 'opacity-0');
                setTimeout(() => card.remove(), 300);
            });
        }

        if (data.link) {
            card.addEventListener('click', (e) => {
                if (!e.target.closest('.toast-close-btn')) {
                    window.location.href = data.link;
                }
            });
        }

        container.prepend(card);

        setTimeout(() => {
            card.classList.remove('translate-x-8', 'opacity-0');
            window.playNotificationSound?.();
        }, 50);

        // Deliberately no auto-dismiss timer — stays until the user closes it.
    };

    window.sendBrowserNotification = function(title, body, iconUrl = null, linkUrl = null, cardId = null) {
        if (!("Notification" in window)) return;
        const audioSrc = document.getElementById('notif-sound')?.src;
        
        const options = {
            body: body,
            icon: iconUrl || window.dgtInitialsAvatar('KQ', '#4f46e5'),
            silent: true, // Prevent OS default sound so we only hear our custom sound
            sound: audioSrc, // For custom app wrappers (e.g. macOS WKWebView) that might support this
            data: { sound: audioSrc, link: linkUrl, card_id: cardId }
        };

        if (Notification.permission === "granted") {
            const notif = new Notification(title, options);
            if (linkUrl) {
                notif.onclick = function(e) {
                    e.preventDefault();
                    window.focus(); // Focus the browser window
                    
                    try {
                        const targetUrl = new URL(linkUrl, window.location.origin);
                        if (cardId && window.location.pathname === targetUrl.pathname) {
                            window.dispatchEvent(new CustomEvent('kiuq:open-card', { detail: { cardId: cardId } }));
                        } else {
                            window.location.href = linkUrl;
                        }
                    } catch (e) {
                        window.location.href = linkUrl;
                    }
                    
                    notif.close();
                };
            }
        }
    };

    window.kiuqGetPusherClient = function() {
        if (!window.Pusher) return null;

        if (window.__kiuqPusher) return window.__kiuqPusher;

        const key = document.querySelector('meta[name="kiuq-pusher-key"]')?.content;
        const cluster = document.querySelector('meta[name="kiuq-pusher-cluster"]')?.content || 'ap1';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        if (!key || !csrf) return null;

        window.__kiuqPusher = new Pusher(key, {
            cluster,
            forceTLS: true,
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        });

        return window.__kiuqPusher;
    };

    window.kiuqConnectPusherNotifications = function(onNotification) {
        if (window.__kiuqNotificationsSubscribed) return true;

        const userId = document.querySelector('meta[name="kiuq-user-id"]')?.content;
        const pusher = window.kiuqGetPusherClient?.();

        if (!userId || !pusher) return false;

        window.__kiuqNotificationsSubscribed = true;
        const channel = window.__kiuqPusher.subscribe(`private-App.Models.User.${userId}`);
        const handle = payload => {
            if (!payload) return;
            onNotification(payload);
        };

        // A single bind_global handler, not multiple exact-name channel.bind()
        // calls — Laravel's broadcastAs() name can arrive with different
        // backslash-escaping depending on how it's transported, and binding
        // several literal variants "just in case" means more than one of
        // them matches the same incoming event, delivering (and popping up)
        // the same notification multiple times. bind_global's substring
        // check is escaping-agnostic and fires exactly once per event.
        channel.bind_global((eventName, payload) => {
            if (String(eventName).includes('BroadcastNotificationCreated')) {
                handle(payload);
            }
        });

        return true;
    };

    // Persists which notification IDs have already popped a card, across
    // page loads — this is a traditional multi-page app (full reload on
    // every navigation, not an SPA), so in-memory Alpine state alone can't
    // tell "already shown to the user" apart from "just not present in this
    // particular page's first fetch yet". Without this, a notification that
    // arrives while the user is on one page and is only first fetched after
    // they've navigated to another page never pops up at all, since that
    // next page's initial fetch treats it as pre-existing backlog.
    const DGT_SHOWN_IDS_KEY = 'dgt_shown_notification_ids';
    const DGT_SHOWN_IDS_MAX = 300;
    // Normalize ids so "uuid", "notif_uuid", and legacy shapes all match.
    window.dgtNormalizeNotificationId = function(id) {
        if (id == null || id === '') return '';
        let s = String(id);
        if (s.startsWith('notif_')) s = s.slice(6);
        return s;
    };
    window.dgtWasNotificationShown = function(id) {
        try {
            const key = window.dgtNormalizeNotificationId(id);
            if (!key) return false;
            const ids = JSON.parse(localStorage.getItem(DGT_SHOWN_IDS_KEY) || '[]');
            return ids.includes(key) || ids.includes('notif_' + key) || ids.includes(id);
        } catch (e) { return false; }
    };
    window.dgtMarkNotificationShown = function(id) {
        try {
            const key = window.dgtNormalizeNotificationId(id);
            if (!key) return;
            const ids = JSON.parse(localStorage.getItem(DGT_SHOWN_IDS_KEY) || '[]');
            if (ids.includes(key)) return;
            ids.push(key);
            while (ids.length > DGT_SHOWN_IDS_MAX) ids.shift();
            localStorage.setItem(DGT_SHOWN_IDS_KEY, JSON.stringify(ids));
        } catch (e) { /* localStorage unavailable — popups just won't dedupe across reloads */ }
    };
    // Short-window content fingerprint: if the same message pops twice within
    // a few seconds (e.g. Pusher + poll race before id lists sync), suppress.
    window.__dgtRecentNotifFingerprints = window.__dgtRecentNotifFingerprints || new Map();
    window.dgtShouldSuppressDuplicateContent = function(data) {
        try {
            const msg = String(data?.message || data?.description || '').trim();
            if (!msg) return false;
            const fp = (data?.type || '') + '|' + msg;
            const now = Date.now();
            const prev = window.__dgtRecentNotifFingerprints.get(fp);
            window.__dgtRecentNotifFingerprints.set(fp, now);
            // Prune old entries
            for (const [k, t] of window.__dgtRecentNotifFingerprints) {
                if (now - t > 15000) window.__dgtRecentNotifFingerprints.delete(k);
            }
            return prev != null && (now - prev) < 8000;
        } catch (e) { return false; }
    };

    // AlpineJS Notification Dropdown Component
    function notificationSystem() {
        return {
            open: false,
            notifications: [],
            unreadCount: 0,
            canPin: {{ auth()->check() && auth()->user()->canPinNotifications() ? 'true' : 'false' }},
            pinModalOpen: false,
            pinningNotif: null,
            pinDuration: '24h',
            customPinExpiresAt: '',
            isPinning: false,

            announcementModalOpen: false,
            announcementTitle: '',
            announcementMessage: '',
            announcementLink: '',
            announcementDuration: '24h',
            customAnnouncementExpiresAt: '',
            isSubmittingAnnouncement: false,

            // Guards against overlapping fetchData() calls — the interval poll
            // (every 10-30s) and toggleOpen() (clicking the bell) both call
            // fetchData() independently. If a click lands while the interval's
            // call is still awaiting the network response, both calls would
            // otherwise independently see the same notification as "not yet
            // shown" and each pop a card for it, since neither has called
            // dgtMarkNotificationShown() yet when the other checks.
            fetchInFlight: false,
            browserPermission: 'unsupported',
            permissionBusy: false,
            notificationsMuted: localStorage.getItem('dgt_notifications_muted') === 'true',

            initNotifications() {
                this.refreshBrowserPermission();
                this.fetchData(true);

                // Always points at the fetchData() of whichever component instance
                // most recently initialized — kept up to date below rather than
                // captured once, since this page uses Turbo navigation (no full
                // reload), which recreates this element and re-runs x-init on every
                // navigation without ever destroying the previous instance's
                // setInterval. Guarding interval creation globally (like the Pusher
                // subscription below already does) means exactly one polling loop
                // ever exists per browser tab, instead of accumulating one more
                // per navigation — each of which independently hitting the network
                // and racing the others to render the same notification.
                window.__kiuqNotificationsPoll = () => this.fetchData();

                const pusherConnected = window.kiuqConnectPusherNotifications?.(n => this.handleIncoming(n));

                if (! window.__kiuqNotificationsPollingStarted) {
                    window.__kiuqNotificationsPollingStarted = true;
                    // Polling failover — Pusher is instant when it connects, but this
                    // deployment has no queue worker and depends on third-party
                    // WebSocket delivery, so a silent connection failure (misconfigured
                    // keys, blocked domain, etc.) would otherwise mean live updates and
                    // popups just never arrive until the page is manually refreshed.
                    // fetchData() already dedupes by notification id, so this is safe
                    // to run even when Pusher is also connected.
                    setInterval(() => window.__kiuqNotificationsPoll?.(), pusherConnected ? 120000 : 15000);
                }
            },

            refreshBrowserPermission() {
                this.browserPermission = ("Notification" in window) ? Notification.permission : 'unsupported';
            },

            browserPermissionLabel() {
                if (this.permissionBusy) return 'Checking';
                return {
                    granted: 'Enabled',
                    denied: 'Blocked',
                    default: 'Ask',
                    unsupported: 'Unavailable',
                }[this.browserPermission] || 'Ask';
            },

            async requestBrowserPermission() {
                if (!("Notification" in window)) {
                    this.browserPermission = 'unsupported';
                    window.showToast('This browser does not support notifications.', 'error');
                    return;
                }

                if (Notification.permission === 'granted') {
                    this.refreshBrowserPermission();
                    window.showToast('Browser notifications are already enabled.');
                    return;
                }

                if (Notification.permission === 'denied') {
                    this.refreshBrowserPermission();
                    window.showToast('Browser notifications are blocked in your browser settings.', 'error');
                    return;
                }

                this.permissionBusy = true;
                const permission = await Notification.requestPermission();
                this.permissionBusy = false;
                this.browserPermission = permission;
                window.showToast(permission === 'granted' ? 'Browser notifications enabled.' : 'Browser notifications were not enabled.', permission === 'granted' ? 'success' : 'error');
            },

            toggleMute() {
                this.notificationsMuted = !this.notificationsMuted;
                localStorage.setItem('dgt_notifications_muted', this.notificationsMuted);
                window.showToast(this.notificationsMuted ? "In-app popups muted" : "In-app popups enabled");
            },

            async fetchData(isInitialLoad = false) {
                if (this.fetchInFlight) return;
                this.fetchInFlight = true;
                try {
                    const res = await fetch('{{ route('notifications.index') }}', {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const data = await res.json();
                    const unread = (data.notifications || []).filter(n => !n.read_at);

                    // If it's the first load of the page, NEVER blast the user with
                    // popups for notifications that were already sitting in the database.
                    // Just silently mark them all as "shown" so they only appear in the dropdown.
                    if (isInitialLoad) {
                        unread.forEach(n => window.dgtMarkNotificationShown(n.id));
                    } else {
                        const newNotifs = unread.filter(n => !window.dgtWasNotificationShown(n.id));
                        newNotifs.forEach((newNotif, index) => {
                            window.dgtMarkNotificationShown(newNotif.id);
                            
                            // Prevent browser freeze from a flood of toasts on wake-up
                            if (index < 3) {
                                if (newNotif.data && newNotif.data.module === 'announcement') {
                                    if (window.showRichNotificationToast) {
                                        window.showRichNotificationToast({
                                            actor_name: newNotif.data.title || 'Announcement',
                                            description: newNotif.data.body || 'A new announcement has been posted.',
                                            actor_avatar: newNotif.data.icon || '📢'
                                        });
                                    }
                                    window.sendBrowserNotification(
                                        newNotif.data.title || "Announcement", 
                                        newNotif.data.body || "A new announcement has been posted.",
                                        newNotif.data.icon || null,
                                        newNotif.data.link || null
                                    );
                                } else if (newNotif.data && newNotif.data.module !== 'crm' && newNotif.data.actor_name) {
                                    window.showRichNotificationToast(newNotif.data);
                                    if (newNotif.data.browser_notifications_enabled !== false) {
                                        window.sendBrowserNotification(
                                            "KIUQ Board Update",
                                            `${newNotif.data.actor_name} ${newNotif.data.description?.replace(/\*\*/g, '') || ''}`,
                                            newNotif.data.actor_avatar,
                                            newNotif.data.link || null,
                                            newNotif.data.card_id || null
                                        );
                                    }
                                } else {
                                    if (window.dgtShouldSuppressDuplicateContent?.(newNotif.data)) return;
                                    window.showCrmNotificationCard(newNotif.data, newNotif.id);
                                    window.sendBrowserNotification(
                                        "DIGITAL SYSTEM Update", 
                                        newNotif.data.message || "New update", 
                                        null, 
                                        newNotif.data.link || null
                                    );
                                }
                            }
                        });
                        
                        if (newNotifs.length > 3) {
                            window.sendBrowserNotification("DIGITAL SYSTEM", `You have ${newNotifs.length} new notifications.`);
                            if (window.showRichNotificationToast) {
                                window.showRichNotificationToast({
                                    actor_name: 'DIGITAL SYSTEM',
                                    description: `You have ${newNotifs.length} new updates.`
                                });
                            }
                        }
                    }

                    if (typeof data.can_pin !== 'undefined') {
                        this.canPin = !!data.can_pin;
                    }
                    this.notifications = data.notifications || [];
                    this.unreadCount = data.unread_count || 0;
                } catch (e) {
                    console.error('Error fetching notifications:', e);
                } finally {
                    this.fetchInFlight = false;
                }
            },

            handleIncoming(n) {
                // Prepend incoming websocket notification
                const rawId = n.id || n.data?.id || null;
                const notifItem = {
                    id: window.dgtNormalizeNotificationId?.(rawId) || rawId,
                    data: n.data || n,
                    read_at: null,
                    created_at: n.created_at || new Date().toISOString()
                };

                // Prevent duplicates — both against this page's own in-memory list
                // and against the persisted shown-ids (in case the polling
                // failover already popped this same notification moments ago).
                const nid = notifItem.id;
                if (nid && this.notifications.some(x => window.dgtNormalizeNotificationId?.(x.id) === nid || x.id === nid)) return;
                if (window.dgtWasNotificationShown(nid)) return;
                window.dgtMarkNotificationShown(nid);

                this.notifications.unshift(notifItem);
                this.unreadCount++;
                window.dispatchEvent(new CustomEvent('kiuq:realtime-notification', { detail: notifItem }));

                // Trigger animations and toasts. Board/Kanban payloads use the rich card toast;
                // CRM payloads get the persistent CRM notification card instead (stacks,
                // stays until manually closed) rather than the Board-style
                // toast, which auto-dismisses.
                if (notifItem.data && notifItem.data.module === 'announcement') {
                    if (window.showRichNotificationToast) {
                        window.showRichNotificationToast({
                            actor_name: notifItem.data.title || 'Announcement',
                            description: notifItem.data.body || 'A new announcement has been posted.',
                            actor_avatar: notifItem.data.icon || '📢'
                        });
                    }
                    window.sendBrowserNotification(
                        notifItem.data.title || "Announcement", 
                        notifItem.data.body || "A new announcement has been posted.",
                        notifItem.data.icon || null,
                        notifItem.data.link || null
                    );
                } else if (notifItem.data && notifItem.data.module !== 'crm' && notifItem.data.actor_name) {
                    window.showRichNotificationToast(notifItem.data);
                } else {
                    window.showCrmNotificationCard(notifItem.data, notifItem.id);
                    window.sendBrowserNotification(
                        "DIGITAL SYSTEM Update", 
                        notifItem.data.message || "New update", 
                        null, 
                        notifItem.data.link || null
                    );
                }
            },

            timeAgo(dateStr) {
                if (!dateStr) return 'just now';
                const date = new Date(dateStr);
                const now = new Date();
                const seconds = Math.floor((now - date) / 1000);
                
                if (seconds < 5) return 'just now';
                if (seconds < 60) return `${seconds}s ago`;
                const minutes = Math.floor(seconds / 60);
                if (minutes < 60) return `${minutes}m ago`;
                const hours = Math.floor(minutes / 60);
                if (hours < 24) return `${hours}h ago`;
                const days = Math.floor(hours / 24);
                if (days < 30) return `${days}d ago`;
                
                return date.toLocaleDateString();
            },

            toggleOpen() {
                this.open = !this.open;
                if (this.open) {
                    this.refreshBrowserPermission();
                    this.fetchData();
                }
            },

            badgeCount() {
                return this.unreadCount > 99 ? '99+' : this.unreadCount;
            },

            isUnread(notif) {
                return !notif.read_at;
            },

            actorAvatar(notif) {
                return notif?.data?.actor_avatar || window.dgtInitialsAvatar(notif?.data?.actor_name || 'System', '#64748b');
            },

            actorName(notif) {
                return notif?.data?.actor_name || 'DIGITAL SYSTEM';
            },

            stripMarkdown(value) {
                return String(value || '')
                    .replace(/\*/g, '')
                    .replace(/<[^>]*>/g, '')
                    .trim();
            },

            notificationAction(notif) {
                const data = notif?.data || {};
                let text = '';
                if (data.title && data.message) {
                    text = data.title + ' — ' + this.stripMarkdown(data.message);
                } else if (data.description) {
                    text = this.stripMarkdown(data.description);
                } else if (data.message) {
                    text = this.stripMarkdown(data.message);
                } else {
                    text = data.action ? this.stripMarkdown(String(data.action).replace(/_/g, ' ')) : 'sent a notification';
                }
                return window.dgtShortenNotificationText ? window.dgtShortenNotificationText(text, 90) : text;
            },

            boardName(notif) {
                return notif?.data?.board_name || '';
            },

            cardName(notif) {
                return notif?.data?.card_title || '';
            },

            notificationTime(notif) {
                return notif?.time_ago || this.timeAgo(notif?.created_at);
            },

            async markAllAsRead() {
                try {
                    await fetch('{{ route('notifications.read-all') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    this.unreadCount = 0;
                    this.notifications.forEach(n => n.read_at = new Date().toISOString());
                    window.showToast("All notifications marked as read!");
                } catch(e) {
                    console.error(e);
                }
            },


            async clickNotification(notif) {
                try {
                    if (!notif.is_pinned && !notif.read_at) {
                        fetch(`/notifications/${notif.id}/read`, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        }).catch(() => {});
                        notif.read_at = new Date().toISOString();
                        if (this.unreadCount > 0) this.unreadCount--;
                    }

                    const link = notif.link || notif.data?.link;
                    if (link) {
                        const targetUrl = new URL(link, window.location.origin);
                        const cardId = notif.card_id || notif.data?.card_id;
                        if (cardId && window.location.pathname === targetUrl.pathname) {
                            window.dispatchEvent(new CustomEvent('kiuq:open-card', { detail: { cardId: cardId } }));
                            this.open = false; // close the dropdown
                        } else {
                            window.location.href = link;
                        }
                    }
                } catch(e) {
                    console.error(e);
                }
            },

            openPinModal(notif) {
                this.pinningNotif = notif;
                this.pinDuration = '24h';
                this.customPinExpiresAt = '';
                this.pinModalOpen = true;
            },

            async confirmPin() {
                if (!this.pinningNotif) return;
                this.isPinning = true;
                try {
                    const notif = this.pinningNotif;
                    const res = await fetch('{{ route('notifications.pin') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            notification_id: notif.id,
                            title: notif.data?.title || notif.data?.card_title || 'Pinned Notification',
                            message: notif.data?.message || notif.data?.description || '',
                            duration: this.pinDuration,
                            custom_expires_at: this.customPinExpiresAt,
                            link: notif.data?.link || '',
                            actor_name: this.actorName(notif),
                            actor_avatar: notif.data?.actor_avatar || '',
                            board_name: this.boardName(notif),
                            card_title: this.cardName(notif),
                            card_id: notif.data?.card_id || null,
                        })
                    });

                    const data = await res.json();
                    if (data.success) {
                        this.pinModalOpen = false;
                        this.pinningNotif = null;
                        window.showToast(data.message || 'Notification pinned to top!', 'success');
                        await this.fetchData();
                    } else {
                        window.showToast(data.message || 'Failed to pin notification.', 'error');
                    }
                } catch (e) {
                    console.error(e);
                    window.showToast('Error pinning notification.', 'error');
                } finally {
                    this.isPinning = false;
                }
            },

            async unpinNotification(notif) {
                if (!confirm('Are you sure you want to unpin this notification? It will return to regular order.')) {
                    return;
                }
                const pinId = notif.pinned_id || (String(notif.id).startsWith('pinned_') ? String(notif.id).replace('pinned_', '') : null);
                if (!pinId) return;

                try {
                    const res = await fetch(`/notifications/${pinId}/unpin`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });

                    const data = await res.json();
                    if (data.success) {
                        window.showToast(data.message || 'Notification unpinned.', 'success');
                        await this.fetchData();
                    } else {
                        window.showToast(data.message || 'Failed to unpin.', 'error');
                    }
                } catch (e) {
                    console.error(e);
                    window.showToast('Error unpinning notification.', 'error');
                }
            },

            openAnnouncementModal() {
                this.announcementTitle = '';
                this.announcementMessage = '';
                this.announcementLink = '';
                this.announcementDuration = '24h';
                this.customAnnouncementExpiresAt = '';
                this.announcementModalOpen = true;
            },

            async submitAnnouncement() {
                if (!this.announcementTitle.trim()) {
                    window.showToast('Please enter an announcement title.', 'error');
                    return;
                }
                if (!this.announcementMessage.trim()) {
                    window.showToast('Please enter an announcement message.', 'error');
                    return;
                }

                this.isSubmittingAnnouncement = true;
                try {
                    const res = await fetch('{{ route('notifications.pin') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            title: this.announcementTitle.trim(),
                            message: this.announcementMessage.trim(),
                            link: this.announcementLink.trim(),
                            duration: this.announcementDuration,
                            custom_expires_at: this.customAnnouncementExpiresAt,
                        })
                    });

                    const data = await res.json();
                    if (data.success) {
                        this.announcementModalOpen = false;
                        this.announcementTitle = '';
                        this.announcementMessage = '';
                        this.announcementLink = '';
                        window.showToast(data.message || 'Announcement pinned to top!', 'success');
                        await this.fetchData();
                    } else {
                        window.showToast(data.message || 'Failed to pin announcement.', 'error');
                    }
                } catch (e) {
                    console.error(e);
                    window.showToast('Error posting announcement.', 'error');
                } finally {
                    this.isSubmittingAnnouncement = false;
                }
            }
        };
    }
    </script>

    @include('components.ajax-form-script')

    @stack('scripts')
    <!-- Notification Sound Effect -->
    @php
        $defaultNotifSound = '01.mp3';
        $userNotifSound = auth()->check() && auth()->user()->notification_sound ? auth()->user()->notification_sound : $defaultNotifSound;
        $notifSoundPath = 'notificationsound/' . $userNotifSound;
        $notifSoundUrl = file_exists(public_path($notifSoundPath)) ? asset($notifSoundPath) : 'https://assets.mixkit.co/active_storage/sfx/2354/2354-preview.mp3';
    @endphp
    <audio id="notif-sound" src="{{ $notifSoundUrl }}" preload="auto"></audio>

    {{-- Lunch Time Alarm Pop-up & Audio --}}
    @include('partials.lunch-alarm-modal')

    {{-- iOS Style Drag & Slide Navigation Logic --}}
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const navContainer = document.getElementById('mobile-bottom-nav');
        if (!navContainer) return;
        
        const innerContainer = navContainer.querySelector('.mobile-bottom-nav-inner');
        const items = Array.from(innerContainer.querySelectorAll('.mobile-nav-item'));
        const bubble = document.getElementById('nav-active-bubble');
        if (!bubble || items.length === 0) return;
        
        let activeIndex = items.findIndex(item => item.classList.contains('active'));
        if (activeIndex === -1) activeIndex = 0;
        
        let currentX = 0;
        let itemWidth = 0;
        const PADDING = 12; // 6px padding on each side of the bubble
        
        const updateLayout = () => {
            if (!items[activeIndex]) return;
            itemWidth = items[activeIndex].offsetWidth;
            bubble.style.width = `${itemWidth - PADDING}px`;
            // relative to inner container
            currentX = items[activeIndex].offsetLeft + (PADDING / 2);
            bubble.style.transform = `translateX(${currentX}px)`;
        };
        
        // Apply instantly on load to avoid sliding-in animation glitch
        bubble.style.transition = 'none';
        updateLayout();
        
        // Restore transition for dragging and resizing
        setTimeout(() => {
            bubble.style.transition = '';
        }, 100);
        window.addEventListener('resize', updateLayout);
        
        let isDragging = false;
        let startX = 0;
        let initialBubbleX = 0;
        let touchTargetItem = null;
        
        innerContainer.addEventListener('touchstart', (e) => {
            const touch = e.touches[0];
            touchTargetItem = e.target.closest('.mobile-nav-item');
            
            if (touchTargetItem) {
                const index = items.indexOf(touchTargetItem);
                if (index === activeIndex) {
                    isDragging = true;
                    startX = touch.clientX;
                    initialBubbleX = currentX;
                    bubble.style.transition = 'none';
                    bubble.classList.add('is-dragging');
                    innerContainer.classList.add('is-dragging-active');
                    // Allow normal touch but prep for drag
                }
            }
        }, { passive: true });
        
        innerContainer.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            const touch = e.touches[0];
            const deltaX = touch.clientX - startX;
            
            // If dragging started, prevent default scroll
            if (Math.abs(deltaX) > 5) {
                e.preventDefault();
            }
            
            let newX = initialBubbleX + deltaX;
            
            const minX = items[0].offsetLeft + (PADDING / 2);
            const maxX = items[items.length - 1].offsetLeft + (PADDING / 2);
            newX = Math.max(minX, Math.min(newX, maxX));
            
            bubble.style.transform = `translateX(${newX}px)`;
            
            let closestIndex = 0;
            let minDiff = Infinity;
            items.forEach((item, index) => {
                const itemCenter = item.offsetLeft + (item.offsetWidth / 2);
                const bubbleCenter = newX + ((itemWidth - PADDING) / 2);
                const diff = Math.abs(bubbleCenter - itemCenter);
                if (diff < minDiff) {
                    minDiff = diff;
                    closestIndex = index;
                }
            });
            
            items.forEach((item, index) => {
                if (index === closestIndex) item.classList.add('active');
                else item.classList.remove('active');
            });
            
        }, { passive: false });
        
        innerContainer.addEventListener('touchend', (e) => {
            bubble.classList.remove('is-dragging');
            innerContainer.classList.remove('is-dragging-active');
            
            if (!isDragging) return;
            isDragging = false;
            bubble.style.transition = 'transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1)';
            
            const currentBubbleRect = bubble.getBoundingClientRect();
            const bubbleCenterX = currentBubbleRect.left + currentBubbleRect.width / 2;
            
            let closestIndex = 0;
            let minDiff = Infinity;
            
            items.forEach((item, index) => {
                const itemRect = item.getBoundingClientRect();
                const itemCenterX = itemRect.left + itemRect.width / 2;
                const diff = Math.abs(bubbleCenterX - itemCenterX);
                if (diff < minDiff) {
                    minDiff = diff;
                    closestIndex = index;
                }
            });
            
            const selectedItem = items[closestIndex];
            currentX = selectedItem.offsetLeft + (PADDING / 2);
            bubble.style.transform = `translateX(${currentX}px)`;
            
            items.forEach((item, index) => {
                if (index === closestIndex) item.classList.add('active');
                else item.classList.remove('active');
            });
            
            if (closestIndex !== activeIndex) {
                activeIndex = closestIndex;
                handleNavAction(selectedItem);
            }
        });
        
        const handleNavAction = (item) => {
            const href = item.getAttribute('href');
            if (href && href !== '#' && !item.hasAttribute('x-data')) {
                if (window.Turbo) {
                    window.Turbo.visit(href);
                } else {
                    window.location.href = href;
                }
            } else if (item.hasAttribute('x-data')) {
                // Use a proper MouseEvent so AlpineJS catches it natively
                const clickEvent = new MouseEvent('click', {
                    view: window,
                    bubbles: true,
                    cancelable: true
                });
                item.dispatchEvent(clickEvent);
            }
        };
        
        items.forEach((item, index) => {
            item.addEventListener('click', (e) => {
                bubble.classList.remove('is-dragging');
                innerContainer.classList.remove('is-dragging-active');
                
                // Ignore if we were just dragging
                if (isDragging) {
                    e.preventDefault();
                    return;
                }
                
                if (e.ctrlKey || e.metaKey || e.shiftKey || (e.button !== undefined && e.button !== 0)) return;
                
                // If it's not a trusted event (i.e. we dispatched it programmatically), let it pass to Alpine
                if (!e.isTrusted) return;
                
                if (index === activeIndex) {
                    if (item.hasAttribute('x-data')) {
                        return; // Let standard click work for things like More button if already active
                    }
                    return; 
                }
                
                e.preventDefault();
                
                activeIndex = index;
                currentX = item.offsetLeft + (PADDING / 2);
                
                items.forEach((it, i) => {
                    if (i === activeIndex) it.classList.add('active');
                    else it.classList.remove('active');
                });
                
                bubble.style.transition = 'transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1)';
                bubble.style.transform = `translateX(${currentX}px)`;
                
                handleNavAction(item);
            });
        });
        
        let scrollTimeout;
        window.addEventListener('scroll', () => {
            if (!isDragging) {
                bubble.classList.add('is-scrolling');
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    bubble.classList.remove('is-scrolling');
                }, 150);
            }
        }, { passive: true, capture: true });
    });
        // Fix for "not real time fast" menu feeling:
        // Provide immediate visual feedback when clicking sidebar links.
        document.addEventListener('click', function(e) {
            const sidebarLink = e.target.closest('.sidebar-item');
            if (sidebarLink && sidebarLink.getAttribute('href') && sidebarLink.getAttribute('href') !== '#') {
                // Instantly highlight the clicked link
                document.querySelectorAll('.sidebar-item').forEach(el => el.classList.remove('active'));
                sidebarLink.classList.add('active');
                
                // If on mobile, instantly close the sidebar to feel faster
                if (window.innerWidth < 1024) {
                    // Alpine.js usually handles this, but forcing it instantly makes it feel better
                    document.dispatchEvent(new CustomEvent('close-mobile-menu'));
                }
            }
        });

        // Aggressive background prefetch for sidebar links to ensure instantaneous 0ms navigation
        window.addEventListener('load', () => {
            setTimeout(() => {
                document.querySelectorAll('a.sidebar-item').forEach(link => {
                    const href = link.getAttribute('href');
                    if (href && href !== '#' && !href.includes('javascript:')) {
                        const prefetch = document.createElement('link');
                        prefetch.rel = 'prefetch';
                        prefetch.as = 'document';
                        prefetch.href = href;
                        document.head.appendChild(prefetch);
                    }
                });
            }, 1000); // Delay to prioritize main page load
        });


    </script>
    <script>
        // Consistent Date Pickers across OS (Fixes macOS native calendar being too small)
        const initDatePickers = () => {
            if (typeof flatpickr !== 'undefined') {
                flatpickr('input[type="date"]:not(.flatpickr-input):not(.no-flatpickr)', {
                    disableMobile: true,
                    altInput: true,
                    altFormat: "m/d/Y",
                    dateFormat: "Y-m-d"
                });
            }
        };
        document.addEventListener('turbo:load', initDatePickers);
        document.addEventListener('DOMContentLoaded', initDatePickers);
        
        const fpObserver = new MutationObserver(mutations => {
            let hasNewInputs = false;
            for (let m of mutations) {
                if (m.addedNodes.length > 0) {
                    m.addedNodes.forEach(node => {
                        if (node.nodeType === 1 && ((node.matches && node.matches('input[type="date"]:not(.no-flatpickr)')) || (node.querySelector && node.querySelector('input[type="date"]:not(.no-flatpickr)')))) {
                            hasNewInputs = true;
                        }
                    });
                }
            }
            if (hasNewInputs) initDatePickers();
        });
        fpObserver.observe(document.body, { childList: true, subtree: true });
    </script>
    <div x-data="{ tooltipVisible: false, tooltipText: '', tooltipX: 0, tooltipY: 0 }" 
         @mouseover.document="
             let el = $event.target.closest('[data-tooltip]'); 
             if(el && $store.sidebar.collapsed && window.innerWidth >= 1024) { 
                 tooltipText = el.getAttribute('data-tooltip'); 
                 let rect = el.getBoundingClientRect(); 
                 tooltipX = rect.right + 12; 
                 tooltipY = rect.top + (rect.height / 2); 
                 tooltipVisible = true; 
             } else { 
                 tooltipVisible = false; 
             } 
         " 
         x-show="tooltipVisible" 
         x-transition.opacity.duration.200ms 
         class="fixed z-[99999] bg-slate-800 text-white text-[13px] font-medium px-3 py-1.5 rounded-md shadow-lg pointer-events-none whitespace-nowrap" 
         :style="`left: ${tooltipX}px; top: ${tooltipY}px; transform: translateY(-50%);`" 
         x-cloak> 
         <span x-text="tooltipText"></span> 
    </div>

    <!-- Popup Ads Manager -->
    <div x-data="popupAdManager" x-init="init()" class="relative z-[999999]">
        <!-- Modal -->
        <div x-show="showModal" 
             x-transition.opacity.duration.300ms
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4 z-[999999]"
             style="display: none;">
            <div x-show="showModal"
                 @click.outside="closeModal()"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-8"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-8"
                 class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl overflow-hidden w-full max-w-2xl relative border border-slate-200 dark:border-slate-800 flex flex-col">
                
                <button @click="closeModal()" class="absolute top-4 right-4 p-2 bg-black/40 backdrop-blur-md rounded-full text-white hover:bg-black/70 transition-colors z-20 shadow-sm">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                <template x-if="ad?.image_url">
                    <div class="w-full bg-slate-100 dark:bg-slate-800/50 flex items-center justify-center overflow-hidden">
                        <img :src="ad.image_url" class="w-full h-auto object-contain max-h-[65vh]">
                    </div>
                </template>

                <div class="p-8 sm:p-10 text-center flex-1 bg-white dark:bg-slate-900 flex flex-col items-center justify-center relative">
                    <div class="absolute inset-0 bg-indigo-50/50 dark:bg-indigo-500/5 rounded-[2rem] -z-10 blur-3xl"></div>
                    
                    <h3 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white mb-4 tracking-tight leading-tight" x-text="ad?.title"></h3>
                    <template x-if="ad?.body_text">
                        <p class="text-slate-500 dark:text-slate-400 text-lg mb-8 max-w-xl mx-auto leading-relaxed" x-text="ad.body_text"></p>
                    </template>
                    
                    <button @click="clickAd()" class="inline-flex items-center justify-center gap-2 px-8 py-3.5 text-base font-bold text-white transition-all bg-indigo-600 rounded-xl hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 dark:focus:ring-indigo-800 shadow-xl shadow-indigo-200 dark:shadow-none hover:-translate-y-1 w-full max-w-sm">
                        <span x-text="ad?.button_text || 'Click Here'"></span>
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Scripts -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('popupAdManager', () => ({
                ad: null,
                showModal: false,
                checkInterval: null,
                
                init() {
                    // Only check if user is logged in
                    if (!document.querySelector('meta[name="csrf-token"]')) return;

                    // Clean up any existing timer from previous SPA visit
                    if (this.checkInterval) {
                        clearTimeout(this.checkInterval);
                        this.checkInterval = null;
                    }

                    // Quick client-side check against localStorage before hitting API
                    const lastActiveId = localStorage.getItem('dgt_popup_ad_last_active_id');
                    if (lastActiveId) {
                        // If user has already clicked this ad, never show it again
                        if (localStorage.getItem('dgt_popup_ad_clicked_' + lastActiveId) === 'true') {
                            return;
                        }

                        const lastShown = parseInt(localStorage.getItem('dgt_popup_ad_last_shown_' + lastActiveId) || '0', 10);
                        const intervalMins = Math.max(1, parseInt(localStorage.getItem('dgt_popup_ad_interval_' + lastActiveId) || '5', 10));
                        const elapsedMs = Date.now() - lastShown;
                        const intervalMs = intervalMins * 60 * 1000;

                        // If interval has not passed yet, set timer for remaining time and avoid popping up prematurely
                        if (lastShown > 0 && elapsedMs < intervalMs) {
                            const remainingMs = intervalMs - elapsedMs;
                            this.startTimerMs(remainingMs);
                            return;
                        }
                    }

                    this.checkAd();
                },
                
                async checkAd() {
                    try {
                        const response = await fetch('/api/popup-ads/check', {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();
                        
                        if (data && data.ad) {
                            const ad = data.ad;
                            const adId = ad.id;
                            localStorage.setItem('dgt_popup_ad_last_active_id', adId.toString());

                            // If user clicked it in localStorage, do not show
                            if (localStorage.getItem('dgt_popup_ad_clicked_' + adId) === 'true') {
                                return;
                            }

                            // Check localStorage interval to prevent 5s re-popups on SPA navigation
                            const lastShown = parseInt(localStorage.getItem('dgt_popup_ad_last_shown_' + adId) || '0', 10);
                            const intervalMins = Math.max(1, parseInt(ad.interval_minutes, 10) || 5);
                            localStorage.setItem('dgt_popup_ad_interval_' + adId, intervalMins.toString());
                            const elapsedMs = Date.now() - lastShown;
                            const intervalMs = intervalMins * 60 * 1000;

                            if (lastShown > 0 && elapsedMs < intervalMs) {
                                const remainingMs = intervalMs - elapsedMs;
                                this.startTimerMs(remainingMs);
                                return;
                            }

                            this.showAd(ad);
                        } else {
                            // No ad available right now. Check again in 5 mins
                            this.startTimer(5);
                        }
                    } catch (error) {
                        console.error('Error checking popup ads:', error);
                        this.startTimer(5);
                    }
                },
                
                showAd(ad) {
                    this.ad = ad;
                    const adId = ad.id;
                    const intervalMins = Math.max(1, parseInt(ad.interval_minutes, 10) || 5);

                    // Record last shown timestamp in localStorage
                    localStorage.setItem('dgt_popup_ad_last_shown_' + adId, Date.now().toString());
                    localStorage.setItem('dgt_popup_ad_interval_' + adId, intervalMins.toString());

                    this.showModal = true;
                    
                    // Show notification toast if it has text
                    if (ad.notification_text && window.Notyf) {
                        const notyf = new Notyf({
                            duration: 5000,
                            position: { x: 'right', y: 'top' },
                        });
                        notyf.success({
                            message: `<b>${ad.notification_icon || '🔔'} ${ad.notification_text}</b>`,
                            background: '#4f46e5'
                        });
                    }
                    
                    // Mark as shown in DB
                    const token = document.querySelector('meta[name="csrf-token"]')?.content;
                    fetch('/api/popup-ads/mark-shown', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ ad_id: adId })
                    }).catch(err => console.error('Error marking ad shown:', err));
                },
                
                closeModal() {
                    this.showModal = false;
                    const intervalMins = this.ad ? (Math.max(1, parseInt(this.ad.interval_minutes, 10) || 5)) : 5;
                    if (this.ad) {
                        localStorage.setItem('dgt_popup_ad_last_shown_' + this.ad.id, Date.now().toString());
                    }
                    // Start timer to check again based on interval
                    this.startTimer(intervalMins);
                },
                
                clickAd() {
                    if (!this.ad) return;
                    const adId = this.ad.id;
                    const link = this.ad.button_link;

                    // 1. Permanently record clicked state in localStorage so it NEVER shows again in this browser
                    localStorage.setItem('dgt_popup_ad_clicked_' + adId, 'true');

                    // 2. Hide modal and clear interval immediately
                    this.showModal = false;
                    if (this.checkInterval) {
                        clearTimeout(this.checkInterval);
                        this.checkInterval = null;
                    }

                    // 3. Open link synchronously in click handler to prevent browser popup blockers
                    if (link) {
                        window.open(link, '_blank', 'noopener,noreferrer');
                    }

                    // 4. Mark as clicked in DB
                    const token = document.querySelector('meta[name="csrf-token"]')?.content;
                    fetch('/api/popup-ads/mark-clicked', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ ad_id: adId })
                    }).catch(err => console.error('Error marking ad clicked:', err));

                    this.ad = null;
                },
                
                startTimerMs(ms) {
                    if (this.checkInterval) clearTimeout(this.checkInterval);
                    this.checkInterval = setTimeout(() => {
                        this.checkAd();
                    }, Math.max(1000, ms));
                },

                startTimer(minutes) {
                    const mins = Math.max(1, parseInt(minutes, 10) || 5);
                    this.startTimerMs(mins * 60 * 1000);
                }
            }));

            Alpine.data('cambodiaClock', () => ({
                timeStr: '',
                dateStr: '',
                timer: null,
                start() {
                    this.updateClock();
                    this.timer = setInterval(() => this.updateClock(), 1000);
                },
                updateClock() {
                    const now = new Date();
                    const optionsTime = { timeZone: 'Asia/Phnom_Penh', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
                    const optionsDate = { timeZone: 'Asia/Phnom_Penh', weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
                    
                    // Intl.DateTimeFormat output isn't exactly what we might want, so let's format it nicely
                    const formatter = new Intl.DateTimeFormat('en-US', {
                        timeZone: 'Asia/Phnom_Penh',
                        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
                        hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true
                    });
                    
                    const parts = formatter.formatToParts(now);
                    const p = {};
                    parts.forEach(part => p[part.type] = part.value);
                    
                    this.dateStr = `${p.weekday}, ${p.month} ${p.day}, ${p.year}`;
                    this.timeStr = `${p.hour}:${p.minute}:${p.second} ${p.dayPeriod}`;
                },
                destroy() {
                    if (this.timer) clearInterval(this.timer);
                }
            }));
        });
    </script>

</body>
</html>
