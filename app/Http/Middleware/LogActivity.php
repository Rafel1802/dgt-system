<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Only log for authenticated users on non-API, non-asset routes
        if (
            Auth::check() &&
            ! $request->is('api/*') &&
            ! $request->is('_debugbar/*') &&
            $request->isMethod('GET') &&
            $response->isSuccessful()
        ) {
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'page.visit',
                'module' => 'navigation',
                'description' => 'Visited: ' . $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        }

        return $response;
    }
}
