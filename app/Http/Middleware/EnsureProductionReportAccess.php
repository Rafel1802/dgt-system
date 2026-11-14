<?php

namespace App\Http\Middleware;

use App\Services\ProductionReport\ProductionReportPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProductionReportAccess
{
    public function __construct(
        private readonly ProductionReportPermissionService $permissionService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('production_reports')) {
            try {
                \Illuminate\Support\Facades\Artisan::call('migrate', [
                    '--path' => 'database/migrations/2026_11_11_000001_create_production_reports_tables.php',
                    '--force' => true,
                ]);
            } catch (\Throwable) {
                // Ignore if migration cannot run automatically
            }
        }

        $this->permissionService->syncDefaultMappingsIfNeeded();

        if (!$this->permissionService->canAccessModule($user)) {
            abort(403, 'Access denied. You are not authorized to access the Production Reports module.');
        }

        return $next($request);
    }
}
