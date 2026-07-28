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
        // A record referenced by a route (customer, lead, eBay record,
        // shipment, ...) can vanish out from under a request that's already
        // in flight — most commonly another user deleting it while this one
        // still has it open and clicks Edit/Save/Record Purchase/etc. Laravel's
        // default here is either a bare framework 404 page (HTML requests) or
        // a raw "No query results for model [...] 5" message (JSON/AJAX) —
        // neither tells the user what actually happened or where to go next.
        //
        // Registered against NotFoundHttpException, not ModelNotFoundException
        // directly — Handler::prepareException() already converts the latter
        // into the former before any render() callback is ever consulted, so
        // a callback type-hinted on ModelNotFoundException itself silently
        // never matches. Only handles the ones that actually originated from
        // a missing model (getPrevious()) — returning null for everything
        // else (a truly undefined route, an explicit abort(404), ...) lets
        // Laravel's normal handling take over unchanged.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $e->getPrevious() instanceof ModelNotFoundException) {
                return null;
            }

            $model = class_basename($e->getPrevious()->getModel());

            $labels = [
                'Customer'           => 'customer',
                'Lead'               => 'lead',
                'EbayCustomerRecord' => 'eBay customer record',
                'EbayOffer'          => 'eBay offer',
                'Shipment'           => 'shipment',
                'ShipmentCustomer'   => 'shipment customer',
                'TruckingCompany'    => 'trucking company',
                'Product'            => 'product',
                'TechSupportCase'    => 'technical support case',
            ];
            $label = $labels[$model] ?? 'record';
            $message = "This {$label} no longer exists — it may have been deleted by another user.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 404);
            }

            $fallbackRoutes = [
                'Customer'           => 'crm.customers.index',
                'Lead'               => 'crm.website.index',
                'EbayCustomerRecord' => 'crm.ebay.customers.index',
                'EbayOffer'          => 'crm.ebay.index',
                'Shipment'           => 'crm.logistics.shipments.index',
                'ShipmentCustomer'   => 'crm.logistics.shipments.index',
                'TruckingCompany'    => 'crm.logistics.trucking.index',
                'Product'            => 'crm.products.index',
                'TechSupportCase'    => 'crm.tech-support.index',
            ];
            $route = $fallbackRoutes[$model] ?? null;

            if ($route && Route::has($route)) {
                return redirect()->route($route)->with('error', $message);
            }

            return redirect('/dashboard')->with('error', $message);
        });
    })->create();
