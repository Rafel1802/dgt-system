<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Kpi\KpiAuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpiAuditController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        if (!$user->isKpiSupervisor()) {
            abort(403, 'Audit logs are reserved for Supervisor and Superadmin.');
        }

        $logs = KpiAuditLog::with('user')->latest('id')->paginate(25);

        return view('kpi.audit-logs', compact('logs'));
    }
}
