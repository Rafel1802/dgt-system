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

        // User "dara" is strictly exempt from maintenance mode and sees everything normally
        $isDara = false;
        if ($user) {
            $username = strtolower(trim($user->username ?? ''));
            $name = strtolower(trim($user->name ?? ''));
            if ($username === 'dara' 
                || str_contains($username, 'dara') 
                || str_contains($name, 'dara') 
                || (method_exists($user, 'isDara') && $user->isDara())
                || in_array($user->id ?? 0, [12, 24], true)) {
                $isDara = true;
            }
        }

        if ($isDara) {
            return $next($request);
        }

        $maintenanceJson = Setting::where('key', 'maintenance_modules')->value('value') ?? '[]';
        $maintenanceModules = json_decode($maintenanceJson, true) ?: [];

        $isMaintained = false;
        $displayModule = $module;

        // Specific module routing logic
        if ($module === 'all_websites') {
            if ($request->is('websites/google-search-console*') || $request->is('google/search-console*') || $request->routeIs('websites.search-console.*') || $request->routeIs('google.search-console.*')) {
                if (in_array('google_search_console', $maintenanceModules) || in_array('all_websites', $maintenanceModules)) {
                    $isMaintained = true;
                    $displayModule = 'Google Search Console';
                }
            } else {
                $tab = $request->query('tab', 'build');
                if ($tab === 'follow-up' || $request->is('websites/follow-ups*') || $request->routeIs('websites.followups.*')) {
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
            }
        } elseif ($module === 'google_search_console') {
            if (in_array('google_search_console', $maintenanceModules) || in_array('all_websites', $maintenanceModules)) {
                $isMaintained = true;
                $displayModule = 'Google Search Console';
            }
        } elseif ($module === 'tasks_count') {
            if (in_array('tasks_count', $maintenanceModules)) {
                $isMaintained = true;
                $displayModule = 'Tasks Count';
            }
        } elseif ($module === 'boards') {
            if ($request->is('boards/personal-report*') || $request->routeIs('boards.reports.personal*')) {
                if (in_array('personal_report', $maintenanceModules) || in_array('boards', $maintenanceModules)) {
                    $isMaintained = true;
                    $displayModule = 'Personal Report';
                }
            } else {
                if (in_array('boards', $maintenanceModules)) {
                    $isMaintained = true;
                    $displayModule = 'Boards & Workspaces';
                }
            }
        } elseif ($module === 'personal_report') {
            if (in_array('personal_report', $maintenanceModules) || in_array('boards', $maintenanceModules)) {
                $isMaintained = true;
                $displayModule = 'Personal Report';
            }
        } elseif ($module === 'social_media') {
            if ($request->is('smm-boards*') || $request->routeIs('smm-boards.*')) {
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
        } elseif ($module === 'social_media_planning') {
            if (in_array('social_media_planning', $maintenanceModules) || in_array('social_media', $maintenanceModules)) {
                $isMaintained = true;
                $displayModule = 'SMM Planning Board';
            }
        } elseif ($module === 'social_media_analytics') {
            if (in_array('social_media_analytics', $maintenanceModules) || in_array('social_media', $maintenanceModules)) {
                $isMaintained = true;
                $displayModule = 'Social & Analytics';
            }
        } elseif ($module === 'kpi') {
            if (in_array('kpi', $maintenanceModules)) {
                $isMaintained = true;
                $displayModule = 'Staff KPI System';
            }
        } elseif ($module === 'members') {
            if (in_array('members', $maintenanceModules)) {
                $isMaintained = true;
                $displayModule = 'All Members Directory';
            }
        } elseif ($module === 'dashboard') {
            if (in_array('dashboard', $maintenanceModules)) {
                $isMaintained = true;
                $displayModule = 'Dashboard';
            }
        } elseif ($module === 'reports') {
            if (in_array('reports', $maintenanceModules)) {
                $isMaintained = true;
                $displayModule = 'Reports Portals';
            }
        } else {
            if (in_array($module, $maintenanceModules)) {
                $isMaintained = true;
                $displayModule = ucwords(str_replace('_', ' ', $module));
            }
        }

        if ($isMaintained) {
            if ($request->expectsJson() || $request->ajax() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'maintenance',
                    'maintenance' => true,
                    'module' => $displayModule,
                    'message' => "The {$displayModule} module is currently undergoing scheduled maintenance. Please check back shortly.",
                ], 503);
            }

            return response()->view('errors.maintenance', ['module' => $displayModule], 503);
        }

        return $next($request);
    }
}
