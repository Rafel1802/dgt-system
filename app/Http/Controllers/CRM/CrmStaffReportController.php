<?php

namespace App\Http\Controllers\CRM;

use App\Enums\WebsiteLeadStatus;
use App\Http\Controllers\Controller;
use App\Models\CallReport;
use App\Models\EbayCustomerHandlerHistory;
use App\Models\EbayCustomerRecord;
use App\Models\EbayStore;
use App\Models\Lead;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\View\View;

class CrmStaffReportController extends Controller
{
    /** Staff performance cards + team KPI tiles, mirroring the demo's Reports tab */
    public function index(): View
    {
        $monthStart = now()->startOfMonth();

        $staff = User::crmMembers()->orderBy('name')->get()->map(function (User $user) use ($monthStart) {
            return [
                'user'           => $user,
                'crm_handled'    => Lead::where('handled_by', $user->id)->where('created_at', '>=', $monthStart)->count(),
                'crm_sales'      => Lead::where('handled_by', $user->id)->where('status', WebsiteLeadStatus::Successful)->count(),
                'calls_answered' => CallReport::where('answered_by', $user->id)->where('occurred_at', '>=', $monthStart)->count(),
                'ebay_handled'   => EbayCustomerHandlerHistory::where('user_id', $user->id)->count(),
                'neg_solved'     => EbayCustomerHandlerHistory::where('user_id', $user->id)
                    ->whereHas('record', fn ($q) => $q->where('negative_feedback_resolved', true))
                    ->count(),
            ];
        });

        $negTotalMonth  = EbayCustomerRecord::where('tab_type', EbayCustomerRecord::TAB_NEGATIVES)->where('created_at', '>=', $monthStart)->count();
        $negSolvedMonth = EbayCustomerRecord::where('negative_feedback_resolved', true)->where('negative_feedback_resolved_at', '>=', $monthStart)->count();
        $salesMonth     = EbayStore::sum('total_sales');

        $completeWeek  = Shipment::where('status', Shipment::STATUS_DELIVERED)->where('updated_at', '>=', now()->subDays(7))->count();
        $completeMonth = Shipment::where('status', Shipment::STATUS_DELIVERED)->where('updated_at', '>=', $monthStart)->count();

        $techWeek  = Lead::where('status', WebsiteLeadStatus::TechnicalSupport)->where('created_at', '>=', now()->subDays(7))->count()
            + EbayCustomerRecord::where('tab_type', EbayCustomerRecord::TAB_TECHNICAL)->where('created_at', '>=', now()->subDays(7))->count();
        $techMonth = Lead::where('status', WebsiteLeadStatus::TechnicalSupport)->where('created_at', '>=', $monthStart)->count()
            + EbayCustomerRecord::where('tab_type', EbayCustomerRecord::TAB_TECHNICAL)->where('created_at', '>=', $monthStart)->count();

        return view('crm.reports.index', compact(
            'staff', 'negTotalMonth', 'negSolvedMonth', 'salesMonth',
            'completeWeek', 'completeMonth', 'techWeek', 'techMonth'
        ));
    }
}
