<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKpiAccess
{
    /**
     * Handle an incoming request.
     * The KPI section is strictly restricted to:
     * - superadmin (username: superadmin or super-admin role)
     * - supervisor (username: somalika or supervisor team_role)
     * - dara (username: dara)
     * - kim (username: kim)
     *
     * Other staff cannot access this section.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $username = strtolower($user->username ?? '');
        $teamRole = strtolower($user->team_role ?? '');

        $isAuthorized = $user->hasRole('super-admin')
            || in_array($username, ['superadmin', 'somalika', 'dara', 'kim'])
            || str_contains($teamRole, 'supervisor');

        if (!$isAuthorized) {
            abort(403, 'Access denied. The Digital Media KPI system is strictly reserved for Department Supervisor, Team Leads (Dara & Kim), and Superadmin.');
        }

        return $next($request);
    }
}
