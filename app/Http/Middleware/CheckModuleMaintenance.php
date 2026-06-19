<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Setting;

class CheckModuleMaintenance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = auth()->user();
        
        // Admins bypass maintenance mode to test and fix the module
        if ($user && $user->hasAnyRole(['super-admin', 'admin', 'admin-digital', 'admin-crm'])) {
            return $next($request);
        }

        $maintenanceJson = Setting::where('key', 'maintenance_modules')->value('value') ?? '[]';
        $maintenanceModules = json_decode($maintenanceJson, true) ?: [];

        if (in_array($module, $maintenanceModules)) {
            return response()->view('errors.maintenance', ['module' => $module], 503);
        }

        return $next($request);
    }
}
