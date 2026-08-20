<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictTechSupportToViewOnly
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only restrict users who have 'tech-support' but lack other CRM editing roles
        if ($user && $user->hasRole('tech-support') && !$user->canModifyCrmData()) {
            $route = $request->route();
            $routeName = $route ? $route->getName() : '';

            // Allow full access to Tech Support pages/actions
            $isTechSupportRoute = str_starts_with($routeName, 'crm.tech-support.') || 
                                  $request->is('crm/tech-support*');

            if (!$isTechSupportRoute) {
                // Block all non-GET/HEAD modify requests
                if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
                    if ($request->wantsJson() || $request->is('api/*')) {
                        return response()->json(['error' => 'Technical Support users are restricted to view-only access on this page.'], 403);
                    }
                    abort(403, 'Technical Support users are restricted to view-only access on this page.');
                }

                // Block access to creation and editing GET views
                $isCreateOrEdit = str_ends_with($routeName, '.create') || 
                                  str_ends_with($routeName, '.edit') || 
                                  str_contains($routeName, '.edit.') || 
                                  $request->is('*/create*') || 
                                  $request->is('*/edit*');

                if ($isCreateOrEdit) {
                    if ($request->wantsJson() || $request->is('api/*')) {
                        return response()->json(['error' => 'Technical Support users are restricted to view-only access on this page.'], 403);
                    }
                    abort(403, 'Technical Support users are restricted to view-only access on this page.');
                }
            }
        }

        return $next($request);
    }
}
