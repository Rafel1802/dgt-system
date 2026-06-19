@php($appIcon = file_exists(public_path('storage/favicon.svg')) ? asset('storage/favicon.svg') : asset('favicon.svg'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Login') | DGT System</title>
    <meta name="description" content="Sign in to DGT System — Digital Team & CRM Management">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="{{ $appIcon }}">
    <link rel="shortcut icon" href="{{ $appIcon }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

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
