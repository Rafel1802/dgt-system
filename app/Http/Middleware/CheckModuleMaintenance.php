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

        $isMaintained = false;
        $displayModule = $module;

        if ($module === 'all_websites') {
            $tab = $request->query('tab', 'build');
            if ($tab === 'follow-up') {
                if (in_array('websites_followup', $maintenanceModules) || in_array('all_websites', $maintenanceModules)) {
                    $isMaintained = true;
                    $displayModule = 'Website Follow Up';
                }
            } else {
                if (in_array('websites_status', $maintenanceModules) || in_array('all_websites', $maintenanceModules)) {
                    $isMaintained = true;
                    $displayModule = 'Website Status';
                }
            }
        } elseif ($module === 'social_media') {
            if ($request->is('smm-boards*')) {
                if (in_array('social_media_planning', $maintenanceModules) || in_array('social_media', $maintenanceModules)) {
                    $isMaintained = true;
                    $displayModule = 'SMM Planning Board';
                }
            } else {
                if (in_array('social_media_analytics', $maintenanceModules) || in_array('social_media', $maintenanceModules)) {
                    $isMaintained = true;
                    $displayModule = 'Social & Analytics';
                }
            }
        } else {
            if (in_array($module, $maintenanceModules)) {
                $isMaintained = true;
            }
        }

        if ($isMaintained) {
            return response()->view('errors.maintenance', ['module' => $displayModule], 503);
        }

        return $next($request);
    }
}
