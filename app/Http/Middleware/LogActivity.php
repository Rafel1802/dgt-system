<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    /**
     * Log page/route access for authenticated users.
     *
     * Performance (Hostinger shared hosting):
     * - Speculative prefetch GETs never write logs.
     * - Real GET page.visits are throttled per user+path (5 min) so Turbo /
     *   back-forward does not INSERT on every navigation.
     * - Mutations (POST/PUT/PATCH/DELETE) always log.
     * Activity logs are kept; only duplicate/prefetch noise is reduced.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            ! Auth::check()
            || $request->is('api/*')
            || $request->is('_debugbar/*')
            || $request->is('storage/*')
            || $request->is('build/*')
            || $request->is('js/*')
            || $request->is('images/*')
            || $request->is('favicon*')
            || $request->is('notifications*')
            || $request->is('notificationsound/*')
            || $request->is('appcast/*')
            || $request->is('downloads/*')
            || ! ($response->isSuccessful() || $response->isRedirection())
            || $this->isPrefetchOrBackground($request)
        ) {
            return $response;
        }

        $method = strtoupper($request->method());
        $routeName = $request->route()?->getName();
        $path = trim($request->path(), '/');
        $module = $this->moduleFromPath($path);
        $action = $method === 'GET' ? 'page.visit' : 'user.action';
        $description = $method === 'GET'
            ? 'Visited ' . ($routeName ?: ($path ?: 'dashboard'))
            : $this->actionDescription($method, $routeName, $path);

        // Throttle GET visits only — mutations always record.
        if ($method === 'GET') {
            $throttleKey = 'actlog:' . Auth::id() . ':' . md5($path);
            if (! Cache::add($throttleKey, 1, now()->addMinutes(5))) {
                return $response;
            }
        }

        // Write the log AFTER the response is sent so menu navigation is not
        // blocked by a synchronous INSERT on every first visit to a path.
        $payload = [
            'user_id' => Auth::id(),
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'properties' => [
                'route' => $routeName,
                'path' => $path,
                'method' => $method,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ];

        if (app()->runningUnitTests()) {
            ActivityLog::create($payload);
        } else {
            dispatch(static function () use ($payload) {
                ActivityLog::create($payload);
            })->afterResponse();
        }

        return $response;
    }

    private function isPrefetchOrBackground(Request $request): bool
    {
        $purpose = strtolower((string) (
            $request->headers->get('Purpose')
            ?? $request->headers->get('Sec-Purpose')
            ?? ''
        ));

        if (str_contains($purpose, 'prefetch') || str_contains($purpose, 'preview')) {
            return true;
        }

        if ($request->headers->has('Turbo-Prefetch')
            || $request->headers->get('X-Turbo-Prefetch') === 'true') {
            return true;
        }

        // Browser / <link rel="prefetch"> often sends Sec-Fetch-Dest: empty
        $dest = strtolower((string) $request->headers->get('Sec-Fetch-Dest'));
        $mode = strtolower((string) $request->headers->get('Sec-Fetch-Mode'));
        if ($request->isMethod('GET') && $dest === 'empty' && in_array($mode, ['no-cors', 'cors'], true)) {
            return true;
        }

        return false;
    }

    private function moduleFromPath(string $path): string
    {
        return match (true) {
            Str::startsWith($path, 'boards') => 'boards',
            Str::startsWith($path, 'social-media') => 'social-media',
            Str::startsWith($path, 'websites') => 'websites',
            Str::startsWith($path, 'website-status') => 'websites',
            Str::startsWith($path, 'follow-up') => 'follow-up',
            Str::startsWith($path, 'notes') => 'notes',
            Str::startsWith($path, 'team-note') => 'team-notes',
            Str::startsWith($path, 'crm') => 'crm',
            Str::startsWith($path, 'approvals') => 'approvals',
            Str::startsWith($path, 'admin/users') => 'users',
            Str::startsWith($path, 'profile') => 'profile',
            default => 'navigation',
        };
    }

    private function actionDescription(string $method, ?string $routeName, string $path): string
    {
        $verb = match ($method) {
            'POST' => 'Created or submitted',
            'PUT', 'PATCH' => 'Updated',
            'DELETE' => 'Deleted',
            default => 'Changed',
        };

        return $verb . ' ' . ($routeName ?: ($path ?: 'system record'));
    }
}
