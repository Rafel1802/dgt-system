@php
    $appIconPath = file_exists(public_path('images/kiuqlogo.png')) ? 'images/kiuqlogo.png' : 'favicon.svg';
    $appLogo = asset($appIconPath);
    $faviconIco = asset('favicon.ico');
@endphp
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'KIUQ SYSTEM — Intelligent Digital Workflow & Team Management')</title>
    <meta name="description" content="@yield('meta_description', 'KIUQ SYSTEM by KIUQ.COM is a next-generation enterprise workflow, Kanban management, and team collaboration platform.')">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">

    <!-- Open Graph / Meta -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@yield('title', 'KIUQ SYSTEM — Intelligent Digital Workflow')">
    <meta property="og:description" content="@yield('meta_description', 'KIUQ SYSTEM by KIUQ.COM — Enterprise Digital Operations, Workflow Boards & Analytics.')">
    <meta property="og:image" content="{{ $appLogo }}">

    <link rel="icon" href="{{ $faviconIco }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" href="{{ $appLogo }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --bg-main: #060b18;
            --bg-card: rgba(14, 23, 47, 0.75);
            --bg-card-hover: rgba(20, 33, 68, 0.85);
            --border-glass: rgba(56, 189, 248, 0.18);
            --border-glass-hover: rgba(56, 189, 248, 0.45);
            --accent-cyan: #38bdf8;
            --accent-blue: #3b82f6;
            --accent-indigo: #6366f1;
            --text-heading: #f8fafc;
            --text-body: #cbd5e1;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-body);
            overflow-x: hidden;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        .ambient-bg {
            position: fixed;
            inset: 0;
            z-index: -10;
            pointer-events: none;
            overflow: hidden;
        }

        .ambient-glow-1 {
            position: absolute;
            top: -10%;
            left: 20%;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.14) 0%, rgba(99, 102, 241, 0.08) 40%, transparent 70%);
            filter: blur(80px);
            border-radius: 50%;
        }

        .ambient-glow-2 {
            position: absolute;
            top: 45%;
            right: -10%;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.12) 0%, rgba(14, 165, 233, 0.06) 45%, transparent 70%);
            filter: blur(90px);
            border-radius: 50%;
        }

        .ambient-glow-3 {
            position: absolute;
            bottom: -5%;
            left: -5%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.10) 0%, transparent 65%);
            filter: blur(80px);
            border-radius: 50%;
        }

        .glass-panel {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.5);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .glass-panel:hover {
            border-color: var(--border-glass-hover);
            transform: translateY(-2px);
            box-shadow: 0 25px 50px -10px rgba(14, 165, 233, 0.15);
        }

        .glass-nav {
            background: rgba(6, 11, 24, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .gradient-text {
            background: linear-gradient(135deg, #ffffff 10%, #7dd3fc 60%, #818cf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-primary-gradient {
            background: linear-gradient(135deg, #0284c7 0%, #2563eb 50%, #4f46e5 100%);
            color: #ffffff;
            font-weight: 700;
            border-radius: 12px;
            padding: 10px 22px;
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.35);
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .btn-primary-gradient:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 25px rgba(37, 99, 235, 0.55);
            filter: brightness(1.08);
            color: #ffffff;
        }

        .btn-secondary-glass {
            background: rgba(255, 255, 255, 0.06);
            color: #f1f5f9;
            font-weight: 600;
            border-radius: 12px;
            padding: 10px 20px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-secondary-glass:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(56, 189, 248, 0.4);
            color: #38bdf8;
            transform: translateY(-1px);
        }

        .legal-content h1, .legal-content h2, .legal-content h3, .legal-content h4 {
            color: #f8fafc;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .legal-content h2 {
            margin-top: 2rem;
            margin-bottom: 0.75rem;
            font-size: 1.35rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 0.5rem;
            color: #38bdf8;
        }

        .legal-content h3 {
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            font-size: 1.15rem;
        }

        .legal-content p, .legal-content li {
            color: #cbd5e1;
            font-size: 0.95rem;
            line-height: 1.75;
        }

        .legal-content ul {
            list-style-type: disc;
            padding-left: 1.5rem;
            margin-top: 0.5rem;
            margin-bottom: 1rem;
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            background: rgba(56, 189, 248, 0.1);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.25);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between selection:bg-cyan-500/30 selection:text-cyan-200">

    <!-- Ambient Glowing Lights -->
    <div class="ambient-bg">
        <div class="ambient-glow-1"></div>
        <div class="ambient-glow-2"></div>
        <div class="ambient-glow-3"></div>
    </div>

    <!-- Navigation Header -->
    <header class="glass-nav sticky top-0 z-50 transition-all duration-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <!-- Brand Logo & Name -->
            <a href="{{ route('home') }}" class="flex items-center gap-3.5 group text-decoration-none">
                <div class="relative flex items-center justify-center w-11 h-11 rounded-2xl bg-gradient-to-br from-blue-600/30 to-indigo-600/30 border border-cyan-400/30 p-1.5 shadow-md shadow-cyan-500/10 group-hover:border-cyan-300 transition-all">
                    <img src="{{ $appLogo }}" alt="KIUQ SYSTEM" class="w-full h-full object-contain">
                </div>
                <div class="flex flex-col">
                    <div class="flex items-center gap-2">
                        <span class="text-xl font-extrabold tracking-tight text-white group-hover:text-cyan-300 transition-colors">KIUQ SYSTEM</span>
                        <span class="badge-pill !text-[10px] !py-0.5 !px-2">Enterprise</span>
                    </div>
                    <span class="text-xs font-semibold tracking-wider uppercase text-cyan-200/70">by KIUQ.COM</span>
                </div>
            </a>

            <!-- Navigation Links -->
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium">
                <a href="{{ route('home') }}" class="text-slate-200 hover:text-cyan-300 transition-colors">Home</a>
                <a href="{{ route('home') }}#features" class="text-slate-300 hover:text-cyan-300 transition-colors">Features</a>
                <a href="{{ route('home') }}#security" class="text-slate-300 hover:text-cyan-300 transition-colors">Security & OAuth</a>
                <a href="{{ route('privacy-policy') }}" class="text-slate-300 hover:text-cyan-300 transition-colors">Privacy Policy</a>
                <a href="{{ route('terms-of-service') }}" class="text-slate-300 hover:text-cyan-300 transition-colors">Terms of Service</a>
            </nav>

            <!-- Auth Action -->
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-primary-gradient !py-2 !px-4 text-sm">
                        <span>Dashboard</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn-primary-gradient !py-2 !px-5 text-sm">
                        <span>Sign In</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Main Page Content -->
    <main class="flex-grow">
        @yield('content')
    </main>

    <!-- Global Footer -->
    <footer class="mt-24 border-t border-slate-800/80 bg-[#040813]/90 backdrop-blur-xl relative z-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-10">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-12 border-b border-slate-800/60">
                <!-- Brand Info -->
                <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center gap-3">
                        <img src="{{ $appLogo }}" alt="KIUQ.COM" class="w-9 h-9 object-contain rounded-xl bg-white/5 p-1 border border-white/10">
                        <span class="text-xl font-black tracking-tight text-white">KIUQ SYSTEM</span>
                    </div>
                    <p class="text-sm text-slate-400 max-w-md leading-relaxed">
                        An intelligent digital workflow, team collaboration, and task management command center designed for modern enterprises. Powered by <span class="text-cyan-300 font-semibold">KIUQ.COM</span>.
                    </p>
                    <div class="flex items-center gap-3 pt-2">
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-md bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            All Systems Operational
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-md bg-sky-500/10 text-sky-400 border border-sky-500/20">
                            TLS 1.3 256-bit Encrypted
                        </span>
                    </div>
                </div>

                <!-- Legal & Compliance Links -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-4">Legal & Compliance</h3>
                    <ul class="space-y-2.5 text-sm">
                        <li>
                            <a href="{{ route('privacy-policy') }}" class="text-slate-400 hover:text-cyan-300 transition-colors">Privacy Policy</a>
                        </li>
                        <li>
                            <a href="{{ route('terms-of-service') }}" class="text-slate-400 hover:text-cyan-300 transition-colors">Terms of Service</a>
                        </li>
                        <li>
                            <a href="{{ route('home') }}#security" class="text-slate-400 hover:text-cyan-300 transition-colors">Google OAuth Compliance</a>
                        </li>
                        <li>
                            <a href="{{ route('home') }}#security" class="text-slate-400 hover:text-cyan-300 transition-colors">Data Protection Standards</a>
                        </li>
                    </ul>
                </div>

                <!-- Navigation & Support -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-4">Platform & Support</h3>
                    <ul class="space-y-2.5 text-sm">
                        <li>
                            <a href="{{ route('home') }}" class="text-slate-400 hover:text-cyan-300 transition-colors">Home Page</a>
                        </li>
                        <li>
                            <a href="{{ route('login') }}" class="text-slate-400 hover:text-cyan-300 transition-colors">Sign In Portal</a>
                        </li>
                        <li>
                            <a href="{{ route('home') }}#features" class="text-slate-400 hover:text-cyan-300 transition-colors">Features & Modules</a>
                        </li>
                        <li>
                            <span class="text-slate-400">Support: <a href="mailto:support@kiuq.com" class="text-cyan-400 hover:underline">support@kiuq.com</a></span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Copyright Bar -->
            <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
                <p>
                    &copy; {{ date('Y') }} <a href="https://kiuq.com" class="text-slate-200 font-bold hover:text-cyan-300 transition-colors">KIUQ.COM</a>. All rights reserved.
                </p>
                <div class="flex items-center gap-6">
                    <a href="{{ route('home') }}" class="hover:text-slate-200 transition-colors">Home</a>
                    <a href="{{ route('privacy-policy') }}" class="hover:text-slate-200 transition-colors">Privacy Policy</a>
                    <a href="{{ route('terms-of-service') }}" class="hover:text-slate-200 transition-colors">Terms of Service</a>
                    <span class="text-slate-500">|</span>
                    <span class="text-slate-400">Official Brand: <strong>KIUQ.COM</strong></span>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
