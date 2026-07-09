@php
    $appIconPath = file_exists(public_path('storage/favicon.svg')) ? 'storage/favicon.svg' : 'favicon.svg';
    $appIconVersion = file_exists(public_path($appIconPath)) ? filemtime(public_path($appIconPath)) : time();
    $appIcon = asset($appIconPath) . '?v=' . $appIconVersion;
    $faviconIco = asset('favicon.ico') . '?v=' . (file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : $appIconVersion);
    $faviconPng = asset('favicon-32x32.png') . '?v=' . (file_exists(public_path('favicon-32x32.png')) ? filemtime(public_path('favicon-32x32.png')) : $appIconVersion);
    $appleTouchIcon = $appIcon;
    $isMacDesktopApp = str_contains((string) request()->userAgent(), 'DGTSystemMacOSApp');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full {{ $isMacDesktopApp ? 'dgt-macos-app' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="KIUQ SYSTEM">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Login') | KIUQ SYSTEM</title>
    <meta name="description" content="Sign in to KIUQ SYSTEM — Digital & CRM Management">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ $faviconIco }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ $appIcon }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $faviconPng }}">
    <link rel="apple-touch-icon" href="{{ $appleTouchIcon }}">
    <link rel="shortcut icon" href="{{ $faviconIco }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if($isMacDesktopApp)
        <style>
            html.dgt-macos-app,
            html.dgt-macos-app body {
                background: #f4f7fb;
            }
        </style>
    @endif

    <!-- Prevent dark mode flash (FOUC) -->
    <script>
        (function() {
            try {
                if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.removeAttribute('data-theme');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body class="h-full">

    @yield('content')

</body>
</html>
