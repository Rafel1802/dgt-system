<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Logistic;
use App\Models\Lead;
use App\Models\EbayOffer;
use App\Models\User;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class CrmReportController extends Controller
{
    /**
     * Export CRM Reports as PDF or CSV.
     */
    public function export(Request $request, string $type)
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date'],
            'member_id'  => ['nullable', 'string'], // 'All' or user ID
            'format'     => ['required', 'string', 'in:pdf,csv'],
        ]);

        $startDate = $validated['start_date'] ? Carbon::parse($validated['start_date'])->startOfDay() : null;
        $endDate   = $validated['end_date'] ? Carbon::parse($validated['end_date'])->endOfDay() : null;
        $memberId  = $validated['member_id'] ?? 'All';
        $format    = $validated['format'];

        $title = '';
        $data = [];
        $headers = [];

        // 1. Fetch filtered data based on export type
        switch ($type) {
            case 'customers':
                $query = Customer::with('assignee');
                if ($startDate) $query->where('created_at', '>=', $startDate);
                if ($endDate) $query->where('created_at', '<=', $endDate);
                if ($memberId !== 'All') $query->where('assigned_to', $memberId);
                $data = $query->latest()->get();
                $title = 'CRM Customer Report';
                $headers = ['#', 'Name', 'Email', 'Phone', 'Company', 'Status', 'Source', 'Pipeline Stage', 'Value (USD)', 'Assigned To', 'Date Added'];
                break;

            case 'logistics':
                $query = Logistic::with(['customer', 'product', 'assignee']);
                if ($startDate) $query->where('created_at', '>=', $startDate);
                if ($endDate) $query->where('created_at', '<=', $endDate);
                if ($memberId !== 'All') $query->where('assigned_to', $memberId);
                $data = $query->latest()->get();
                $title = 'Logistics CRM Shipment Report';
                $headers = ['#', 'Order ID', 'Customer', 'Product', 'Recipient', 'Address', 'Budget (USD)', 'Status', 'Pickup Time'];
                break;

            case 'website':
                $query = Lead::with(['customer', 'product', 'assignee']);
                if ($startDate) $query->where('received_at', '>=', $startDate);
                if ($endDate) $query->where('received_at', '<=', $endDate);
                if ($memberId !== 'All') $query->where('assigned_to', $memberId);
                $data = $query->latest('received_at')->get();
                $title = 'Website CRM Lead Inquiry Report';
                $headers = ['#', 'Client Name', 'Email', 'Phone', 'Source', 'Product Interested', 'Status', 'Temperature', 'Received At'];
                break;

            case 'ebay':
                $query = EbayOffer::with(['customer', 'product', 'handler', 'store']);
                if ($startDate) $query->where('received_at', '>=', $startDate);
                if ($endDate) $query->where('received_at', '<=', $endDate);
                if ($memberId !== 'All') $query->where('handled_by', $memberId);
                $data = $query->latest('received_at')->get();
                $title = 'eBay CRM Offers Report';
                $headers = ['#', 'Store', 'Customer Name', 'eBay Username', 'Product', 'Offer Amount (USD)', 'Final Amount (USD)', 'Status', 'Received At'];
                break;

            default:
                abort(404, 'Invalid report type.');
        }

        $formattedMember = 'All Members';
        if ($memberId !== 'All') {
            $user = User::find($memberId);
            if ($user) $formattedMember = $user->name;
        }

        $dateRangeStr = ($startDate && $endDate) 
            ? $startDate->format('d M Y') . ' to ' . $endDate->format('d M Y')
            : 'All Time';

        // 2. Export as CSV
        if ($format === 'csv') {
            $filename = strtolower(str_replace(' ', '_', $title)) . '_' . now()->format('Ymd_His') . '.csv';

            return response()->streamDownload(function () use ($type, $data, $headers) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $headers);

                foreach ($data as $index => $row) {
                    $rowData = [];
                    $num = $index + 1;

                    if ($type === 'customers') {
                        $rowData = [
                            $num,
                            $row->name,
                            $row->email ?? '—',
                            $row->phone ?? '—',
                            $row->company ?? '—',
                            $row->status ? $row->status->label() : '—',
                            $row->source ? $row->source : '—',
                            $row->pipeline_stage ? $row->pipeline_stage->label() : '—',
                            $row->lifetime_value,
                            $row->assignee ? $row->assignee->name : 'Unassigned',
                            $row->created_at->format('Y-m-d H:i')
                        ];
                    } elseif ($type === 'logistics') {
                        $rowData = [
                            $num,
                            $row->order_id ?? '—',
                            $row->customer ? $row->customer->name : '—',
                            $row->product ? $row->product->name : '—',
                            $row->recipient_name,
                            $row->shipping_address,
                            $row->shipping_budget,
                            $row->status ? $row->status->label() : '—',
                            $row->pickup_datetime ? $row->pickup_datetime->format('Y-m-d H:i') : '—'
                        ];
                    } elseif ($type === 'website') {
                        $rowData = [
                            $num,
                            $row->client_name,
                            $row->client_email ?? '—',
                            $row->client_phone ?? '—',
                            $row->source ? $row->source->label() : '—',
                            $row->product ? $row->product->name : ($row->product_interested ?? '—'),
                            $row->status ? $row->status->label() : '—',
                            $row->temperature ? $row->temperature->label() : '—',
                            $row->received_at ? $row->received_at->format('Y-m-d H:i') : '—'
                        ];
                    } elseif ($type === 'ebay') {
                        $rowData = [
                            $num,
                            $row->store ? $row->store->store_name : '—',
                            $row->customer ? $row->customer->name : ($row->client_name ?? '—'),
                            $row->ebay_username ?? '—',
                            $row->product ? $row->product->name : '—',
                            $row->offer_amount,
                            $row->final_amount,
                            $row->status ? $row->status->label() : '—',
                            $row->received_at ? $row->received_at->format('Y-m-d H:i') : '—'
                        ];
                    }

                    fputcsv($file, $rowData);
                }
                fclose($file);
            }, $filename, [
                'Content-Type' => 'text/csv',
                'Cache-Control' => 'no-cache, must-revalidate',
                'Expires' => '0',
            ]);
        }

        // 3. Export as PDF
        $filename = strtolower(str_replace(' ', '_', $title)) . '_' . now()->format('Ymd_His') . '.pdf';
        
        $pdf = Pdf::loadView('reports.crm_export', compact('title', 'type', 'data', 'headers', 'formattedMember', 'dateRangeStr'));
        return $pdf->download($filename);
    }
}
