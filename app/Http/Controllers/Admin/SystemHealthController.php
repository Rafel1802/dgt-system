<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    /**
     * Display the System Health & Maintenance Diagnostics Dashboard.
     */
    public function index(SystemHealthService $service): View
    {
        abort_unless(
            auth()->user()->canAccessMaintenance(),
            403,
            'Unauthorized access to system maintenance diagnostics.'
        );

        $diagnostics = $service->runDiagnostics();
        $aiReport = $service->generateAiMarkdownReport();

        return view('admin.system-health.index', compact('diagnostics', 'aiReport'));
    }

    /**
     * Execute one-click auto-repair routine (supports full or module-scoped repairs).
     */
    public function repair(SystemHealthService $service, Request $request): JsonResponse|RedirectResponse
    {
        abort_unless(
            auth()->user()->canAccessMaintenance(),
            403,
            'Unauthorized access to system maintenance diagnostics.'
        );

        $scope = $request->input('scope', 'all');
        $result = $service->runAutoRepair($scope);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => $result['success'] ?? true,
                'scope' => $scope,
                'message' => $result['message'],
                'actions_taken' => $result['actions_taken'],
            ]);
        }

        return redirect()->to(route('system.health.index'), 303)
            ->with('repair_success', $result['message'])
            ->with('repair_actions', $result['actions_taken']);
    }

    /**
     * Clear caches and optimize system speed directly from the maintenance center.
     */
    public function optimize(SystemHealthService $service, Request $request): JsonResponse|RedirectResponse
    {
        abort_unless(
            auth()->user()->canAccessMaintenance(),
            403,
            'Unauthorized access to system maintenance diagnostics.'
        );

        $result = $service->optimizeSystemSpeed();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => $result['success'] ?? true,
                'message' => $result['message'],
                'actions_taken' => $result['actions_taken'],
                'duration' => $result['duration'] ?? 0,
                'performance' => $result['performance'] ?? null,
            ]);
        }

        return redirect()->to(route('system.health.index'), 303)
            ->with('optimize_success', $result['message'])
            ->with('optimize_actions', $result['actions_taken']);
    }

    /**
     * Export diagnostic report formatted for AI.
     */
    public function copyReport(SystemHealthService $service): JsonResponse
    {
        abort_unless(
            auth()->user()->canAccessMaintenance(),
            403,
            'Unauthorized access to system maintenance diagnostics.'
        );

        $markdown = $service->generateAiMarkdownReport();

        return response()->json([
            'success' => true,
            'markdown' => $markdown,
        ]);
    }

    /**
     * Clear or rotate the server log file.
     */
    public function clearLogs(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless(
            auth()->user()->canAccessMaintenance(),
            403,
            'Unauthorized access to system maintenance diagnostics.'
        );

        $logPath = storage_path('logs/laravel.log');
        if (File::exists($logPath)) {
            File::put($logPath, '');
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'System log file cleared successfully.',
            ]);
        }

        return redirect()->to(route('system.health.index'), 303)
            ->with('status', 'System log file cleared successfully.');
    }
}
