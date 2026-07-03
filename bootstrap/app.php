<?php

use App\Http\Middleware\CheckIpBan;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\LogActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // ── Global web middleware additions ──────────────────────────────
        $middleware->web(append: [
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // ── Exclude webhooks from CSRF ────────────────────────────────────
        $middleware->validateCsrfTokens(except: [
            'webhook/*',
        ]);

        // ── Named middleware aliases ──────────────────────────────────────
        $middleware->alias([
            'check.ip.ban'  => CheckIpBan::class,
            'ensure.active' => EnsureUserIsActive::class,
            'log.activity'  => LogActivity::class,
            'role'          => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'    => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'maintenance'   => \App\Http\Middleware\CheckModuleMaintenance::class,
        ]);

        // ── Trust all proxies (for XAMPP / reverse proxy setups) ─────────
        $middleware->trustProxies(at: '*');

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Custom exception handling can be added here in future phases
    })->create();
