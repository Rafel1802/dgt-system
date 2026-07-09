<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\EbayCustomerRecord;
use App\Models\EbayStore;
use Illuminate\View\View;

class EbayReportController extends Controller
{
    /** eBay Team "General Report" tiles: negative feedback/month, solved/month, sales/month */
    public function index(): View
    {
        $negTotalMonth = EbayCustomerRecord::where('tab_type', EbayCustomerRecord::TAB_NEGATIVES)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $negSolvedMonth = EbayCustomerRecord::where('negative_feedback_resolved', true)
            ->where('negative_feedback_resolved_at', '>=', now()->startOfMonth())
            ->count();

        $salesMonth = EbayStore::sum('total_sales');

        return view('crm.ebay.report', compact('negTotalMonth', 'negSolvedMonth', 'salesMonth'));
    }
}
