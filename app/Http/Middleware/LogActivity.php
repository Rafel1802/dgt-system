<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    /**
     * Log page/route access for authenticated users.
     * This middleware records navigation activity in the activity_logs table.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log for authenticated users on non-API, non-asset routes.
        if (
            Auth::check() &&
            ! $request->is('api/*') &&
            ! $request->is('_debugbar/*') &&
            ! $request->is('storage/*') &&
            ! $request->is('build/*') &&
            ! $request->is('js/*') &&
            ! $request->is('images/*') &&
            ! $request->is('favicon*') &&
            ! $request->is('notifications*') &&
            ! $request->is('notificationsound/*') &&
            ! $request->is('appcast/*') &&
            ! $request->is('downloads/*') &&
            ($response->isSuccessful() || $response->isRedirection())
        ) {
            $method = strtoupper($request->method());
            $routeName = $request->route()?->getName();
            $path = trim($request->path(), '/');
            $module = $this->moduleFromPath($path);
            $action = $method === 'GET' ? 'page.visit' : 'user.action';
            $description = $method === 'GET'
                ? 'Visited ' . ($routeName ?: ($path ?: 'dashboard'))
                : $this->actionDescription($method, $routeName, $path);

            ActivityLog::create([
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
            ]);
        }

        return $response;
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
