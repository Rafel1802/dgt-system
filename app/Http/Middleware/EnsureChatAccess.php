<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChatAccess
{
    /**
     * Handle an incoming request.
     * Ensure the authenticated user has chat permissions enabled.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'error' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        }

        if (!$user->isChatEnabled()) {
            if ($request->is('chat/unread-count')) {
                return response()->json(['success' => true, 'unread_count' => 0]);
            }

            if ($request->expectsJson() || ($request->is('chat/*') && $request->method() !== 'GET')) {
                return response()->json(['success' => false, 'error' => 'Chat access is disabled for your account.'], 403);
            }
            return redirect()->route('dashboard')->with('error', 'Chat access is currently disabled for your account. Please contact Dara or Super Admin.');
        }

        return $next($request);
    }
}
