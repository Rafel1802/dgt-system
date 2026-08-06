<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Prevent deactivated users from accessing the system.
     * This runs after authentication is confirmed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact admin.']);
        }

        // Keep last_login_at updated to accurately track online/active users
        if ($request->user()) {
            $user = $request->user();
            if (! $user->last_login_at || $user->last_login_at->diffInMinutes(now()) >= 5) {
                $user->timestamps = false;
                $user->last_login_at = now();
                $user->save();
            }
        }

        return $next($request);
    }
}
