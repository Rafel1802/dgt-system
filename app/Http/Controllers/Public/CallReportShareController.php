<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CallReport;
use App\Models\CallReportShare;
use Illuminate\View\View;

class CallReportShareController extends Controller
{
    /**
     * Public, unauthenticated view of a shared Call Reports filter set.
     * Access control is the token itself (unguessable, 40 chars) rather
     * than a login — the query re-runs live so the page always reflects
     * current data for whoever holds the link.
     */
    public function show(string $token): View
    {
        $share = CallReportShare::where('token', $token)->firstOrFail();

        $callReports = CallReport::with('answeredBy')
            ->filtered($share->filters ?? [])
            ->latest('occurred_at')
            ->paginate(20);

        return view('public.call-reports', compact('callReports', 'share'));
    }
}
