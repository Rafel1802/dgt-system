<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\EbayOffer;
use App\Models\Lead;
use App\Models\Logistic;
use App\Models\Shipment;
use App\Models\ShipmentCustomer;
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

        $startDate = !empty($validated['start_date']) ? Carbon::parse($validated['start_date'])->startOfDay() : null;
        $endDate   = !empty($validated['end_date']) ? Carbon::parse($validated['end_date'])->endOfDay() : null;
        $memberId  = $validated['member_id'] ?? 'All';
        $format    = $validated['format'];

        $title = '';
        $data = collect();
        $headers = [];

        // 1. Fetch filtered data based on export type
        switch ($type) {
            case 'customers':
                $matcher = app(\App\Services\CrmCustomerMatchService::class);
                $unified = $matcher->buildUnifiedDirectory([
                    'search' => $request->get('search'),
                ]);

                // Filter unified rows by status, source, member, dates
                $statusFilter = $request->get('status_filter', 'All');
                $sourceFilter = $request->get('source_filter', 'All');
                $category = match ($statusFilter) {
                    'Technical issues'  => 'technical',
                    'Logistic issues'   => 'shipment_delay',
                    'Negative feedback' => 'negative_feedback',
                    default => null,
                };

                $filtered = $unified->filter(function ($c) use ($category, $sourceFilter, $memberId, $startDate, $endDate) {
                    if (is_array($c)) {
                        if ($category !== null) {
                            $cats = $c['categories'] ?? (isset($c['category']) ? [$c['category']] : []);
                            if (!in_array($category, $cats, true)) return false;
                        }
                        if ($sourceFilter === 'eBay' && ($c['source'] ?? null) !== 'eBay') return false;
                        if ($sourceFilter === 'Logistics' && ($c['source'] ?? null) !== 'Logistics') return false;
                        if ($sourceFilter === 'Website' && in_array($c['source'] ?? null, ['eBay', 'Logistics'], true)) return false;
                        if ($memberId !== 'All' && ($c['handler_id'] ?? null) != $memberId) return false;
                    }
                    return true;
                });

                $data = $filtered->values();
                if ($data->isEmpty()) {
                    $query = Customer::with(['assignee', 'interactions' => fn ($q) => $q->limit(1)]);
                    if ($startDate) $query->where('created_at', '>=', $startDate);
                    if ($endDate) $query->where('created_at', '<=', $endDate);
                    if ($memberId !== 'All') $query->where('assigned_to', $memberId);
                    $data = $query->latest()->get();
                }
                $title = 'CRM Unified Customer Report';
                $headers = ['#', 'Customer Name', 'Email', 'Phone', 'Status', 'Source', 'Value (USD)', 'Assigned To', 'Created Date'];
                break;

            case 'logistics':
                $query = ShipmentCustomer::with(['customer', 'shipment', 'handler']);
                if ($startDate) $query->where('created_at', '>=', $startDate);
                if ($endDate) $query->where('created_at', '<=', $endDate);
                if ($memberId !== 'All') $query->where('handled_by', $memberId);
                $data = $query->latest()->get();
                if ($data->isEmpty()) {
                    $legQuery = Logistic::with(['customer', 'product', 'assignee']);
                    if ($startDate) $legQuery->where('created_at', '>=', $startDate);
                    if ($endDate) $legQuery->where('created_at', '<=', $endDate);
                    if ($memberId !== 'All') $legQuery->where('assigned_to', $memberId);
                    $data = $legQuery->latest()->get();
                }
                $title = 'Logistics CRM Shipment Report';
                $headers = ['#', 'Order/Shipment', 'Customer', 'Product', 'Recipient', 'Address', 'Budget/Status', 'Status', 'Date'];
                break;

            case 'website':
                $query = Lead::with(['customer', 'product', 'handler', 'assignee']);
                if ($startDate) $query->where('received_at', '>=', $startDate);
                if ($endDate) $query->where('received_at', '<=', $endDate);
                if ($memberId !== 'All') $query->where('assigned_to', $memberId);
                $data = $query->latest('received_at')->get();
                $title = 'Website CRM Lead Inquiry Report';
                $headers = ['#', 'Client Name', 'Email', 'Phone', 'Source', 'Product Interested', 'Status', 'Temperature', 'Received At'];
                break;

            case 'ebay':
                $query = EbayCustomerRecord::with(['customer', 'store', 'creator']);
                if ($startDate) $query->where(fn ($q) => $q->where('date', '>=', $startDate)->orWhere('created_at', '>=', $startDate));
                if ($endDate) $query->where(fn ($q) => $q->where('date', '<=', $endDate)->orWhere('created_at', '<=', $endDate));
                $data = $query->latest()->get();
                if ($data->isEmpty()) {
                    $offQuery = EbayOffer::with(['customer', 'product', 'handler', 'store']);
                    if ($startDate) $offQuery->where('received_at', '>=', $startDate);
                    if ($endDate) $offQuery->where('received_at', '<=', $endDate);
                    if ($memberId !== 'All') $offQuery->where('handled_by', $memberId);
                    $data = $offQuery->latest('received_at')->get();
                }
                $title = 'eBay CRM Customer Report';
                $headers = ['#', 'Store', 'Customer Name', 'eBay Username', 'Order ID', 'Summary', 'Category/Tab', 'Date'];
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

        $customerQuery = ShipmentCustomer::query();
        $shipmentQuery = Shipment::query();

        if ($startDate) {
            $customerQuery->where('created_at', '>=', $startDate);
            $shipmentQuery->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $customerQuery->where('created_at', '<=', $endDate);
            $shipmentQuery->where('created_at', '<=', $endDate);
        }
        if ($memberId !== 'All') {
            $customerQuery->where('handled_by', $memberId);
            $shipmentQuery->where('assigned_to', $memberId);
        }

        $summaryStats = [
            'total_customers'         => $data->count(),
            'total_sales'             => 0.0,
            'ebay_count'              => 0,
            'website_count'           => 0,
            'logistics_count'         => 0,
            'ebay_sales'              => 0.0,
            'website_sales'           => 0.0,
            'delivered_count'         => (clone $customerQuery)->where('status', ShipmentCustomer::STATUS_DELIVERED)->count(),
            'waiting_pickup_count'    => (clone $customerQuery)->where('status', ShipmentCustomer::STATUS_PENDING)->count(),
            'logistic_issues_count'   => 0,
            'negative_feedback_count' => 0,
            'technical_issues_count'  => 0,
        ];

        foreach ($data as $row) {
            if (is_array($row)) {
                $val = (float)($row['lifetime_value'] ?? 0);
                $summaryStats['total_sales'] += $val;

                $src = strtolower($row['source'] ?? '');
                if (str_contains($src, 'ebay')) {
                    $summaryStats['ebay_count']++;
                    $summaryStats['ebay_sales'] += $val;
                } elseif (str_contains($src, 'website')) {
                    $summaryStats['website_count']++;
                    $summaryStats['website_sales'] += $val;
                } else {
                    $summaryStats['logistics_count']++;
                }

                $cats = $row['categories'] ?? [];
                $statusLabel = strtolower($row['status_label'] ?? '');
                if (in_array('shipment_delay', $cats, true) || str_contains($statusLabel, 'logistic')) {
                    $summaryStats['logistic_issues_count']++;
                }
                if (in_array('negative_feedback', $cats, true) || str_contains($statusLabel, 'negative')) {
                    $summaryStats['negative_feedback_count']++;
                }
                if (in_array('technical', $cats, true) || str_contains($statusLabel, 'technical')) {
                    $summaryStats['technical_issues_count']++;
                }
            } elseif ($row instanceof \App\Models\Customer) {
                $val = (float)$row->lifetime_value;
                $summaryStats['total_sales'] += $val;
                $src = strtolower($row->source ? (is_object($row->source) ? $row->source->label() : (string)$row->source) : '');
                if (str_contains($src, 'ebay')) {
                    $summaryStats['ebay_count']++;
                    $summaryStats['ebay_sales'] += $val;
                } elseif (str_contains($src, 'website')) {
                    $summaryStats['website_count']++;
                    $summaryStats['website_sales'] += $val;
                } else {
                    $summaryStats['logistics_count']++;
                }
                if ($row->shipment_delay) $summaryStats['logistic_issues_count']++;
            }
        }

        if ($summaryStats['logistic_issues_count'] == 0) {
            $summaryStats['logistic_issues_count'] = ShipmentCustomer::where('status', 'problem')->count()
                + EbayCustomerRecord::where(function ($q) {
                    $q->where('shipment_delay', true)
                      ->orWhere('negative_feedback_causes', 'like', '%Logistic issues%');
                })->count();
        }
        if ($summaryStats['negative_feedback_count'] == 0) {
            $summaryStats['negative_feedback_count'] = EbayCustomerRecord::whereIn('tab_type', ['potential_negatives', 'negatives_feedbacks'])->count();
        }
        if ($summaryStats['technical_issues_count'] == 0) {
            $summaryStats['technical_issues_count'] = \App\Models\TechSupportCase::where('status', '!=', 'resolved')->count();
        }

        if ($summaryStats['ebay_count'] == 0 && $summaryStats['website_count'] == 0 && $summaryStats['logistics_count'] == 0) {
            $summaryStats['ebay_count'] = EbayCustomerRecord::count();
            $summaryStats['website_count'] = Lead::count();
            $summaryStats['logistics_count'] = ShipmentCustomer::count();
        }

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
                        if (is_array($row)) {
                            $creatDate = !empty($row['created_date']) ? (is_string($row['created_date']) ? $row['created_date'] : $row['created_date']->format('Y-m-d H:i')) : '—';
                            $rowData = [
                                $num,
                                $row['id'] ?? '—',
                                $row['name'] ?? '—',
                                $row['email'] ?? '—',
                                $row['phone'] ?? '—',
                                $row['status_label'] ?? ($row['status'] ?? '—'),
                                $row['source'] ?? '—',
                                number_format((float)($row['lifetime_value'] ?? 0), 2),
                                $row['handler'] ?? ($row['assigned_to_name'] ?? 'Unassigned'),
                                $creatDate,
                            ];
                        } else {
                            $src = is_object($row->source) ? ($row->source->label ?? (string)$row->source) : (is_array($row->source) ? implode(', ', $row->source) : ($row->source ?? '—'));
                            $rowData = [
                                $num,
                                $row->id,
                                $row->name,
                                $row->email ?? '—',
                                $row->phone ?? '—',
                                $row->status ? (is_object($row->status) ? $row->status->label() : $row->status) : '—',
                                $src,
                                number_format((float)($row->lifetime_value ?? 0), 2),
                                $row->assignee ? $row->assignee->name : 'Unassigned',
                                $row->created_at ? $row->created_at->format('Y-m-d H:i') : '—',
                            ];
                        }
                    } elseif ($type === 'logistics') {
                        if ($row instanceof ShipmentCustomer) {
                            $rowData = [
                                $num,
                                $row->shipment ? $row->shipment->shipment_code : '—',
                                $row->customer ? $row->customer->name : '—',
                                $row->product_description ?? '—',
                                $row->recipient_name,
                                $row->shipping_address ?? '—',
                                '—',
                                ucfirst($row->status ?? 'pending'),
                                $row->created_at ? $row->created_at->format('Y-m-d H:i') : '—'
                            ];
                        } else {
                            $rowData = [
                                $num,
                                $row->order_id ?? '—',
                                $row->customer ? $row->customer->name : '—',
                                $row->product ? $row->product->name : '—',
                                $row->recipient_name ?? '—',
                                $row->shipping_address ?? '—',
                                $row->shipping_budget ?? '0.00',
                                $row->status ? (is_object($row->status) ? $row->status->label() : $row->status) : '—',
                                $row->pickup_datetime ? $row->pickup_datetime->format('Y-m-d H:i') : '—'
                            ];
                        }
                    } elseif ($type === 'website') {
                        $rowData = [
                            $num,
                            $row->client_name ?? '—',
                            $row->client_email ?? '—',
                            $row->client_phone ?? '—',
                            $row->source ? (is_object($row->source) ? $row->source->label() : $row->source) : '—',
                            $row->product ? $row->product->name : ($row->product_interested ?? '—'),
                            $row->status ? (is_object($row->status) ? $row->status->label() : $row->status) : '—',
                            $row->temperature ? (is_object($row->temperature) ? $row->temperature->label() : $row->temperature) : '—',
                            $row->received_at ? $row->received_at->format('Y-m-d H:i') : ($row->created_at ? $row->created_at->format('Y-m-d H:i') : '—')
                        ];
                    } elseif ($type === 'ebay') {
                        if ($row instanceof EbayCustomerRecord) {
                            $rowData = [
                                $num,
                                $row->store ? $row->store->store_name : '—',
                                $row->buyer_name ?: ($row->username ?: '—'),
                                $row->username ?? '—',
                                $row->order_id ?? '—',
                                $row->summary ?? '—',
                                $row->tab_type ?? '—',
                                ($row->date ?? $row->created_at)?->format('Y-m-d H:i') ?? '—'
                            ];
                        } else {
                            $rowData = [
                                $num,
                                $row->store ? $row->store->store_name : '—',
                                $row->customer ? $row->customer->name : ($row->client_name ?? '—'),
                                $row->ebay_username ?? '—',
                                $row->product ? $row->product->name : '—',
                                $row->offer_amount,
                                $row->final_amount,
                                $row->status ? (is_object($row->status) ? $row->status->label() : $row->status) : '—',
                                $row->received_at ? $row->received_at->format('Y-m-d H:i') : '—'
                            ];
                        }
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
        
        $pdf = Pdf::loadView('reports.crm_export', compact('title', 'type', 'data', 'headers', 'formattedMember', 'dateRangeStr', 'summaryStats'));
        return $pdf->download($filename);
    }
}
