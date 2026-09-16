<?php

use App\Http\Middleware\CheckIpBan;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\LogActivity;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
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
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $e->getPrevious() instanceof ModelNotFoundException) {
                return null;
            }

            $model = class_basename($e->getPrevious()->getModel());
            $message = "This {$model} no longer exists — it may have been deleted by another user.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 404);
            }

            return redirect('/dashboard')->with('error', $message);
        });
    })->create();
