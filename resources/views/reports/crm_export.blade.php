<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #334155;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 25px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 15px;
        }
        .header-title {
            font-size: 20px;
            font-weight: 800;
            color: #1e3a8a;
            margin: 0 0 5px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-meta {
            font-size: 10px;
            color: #64748b;
        }
        .filter-summary {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        .filter-grid {
            width: 100%;
        }
        .filter-grid td {
            padding: 2px 0;
        }
        .filter-label {
            font-weight: 600;
            color: #475569;
            width: 120px;
        }
        .filter-value {
            color: #0f172a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
            border-bottom: 2px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            word-wrap: break-word;
        }
        tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            font-weight: 600;
            border-radius: 9999px;
            text-transform: uppercase;
        }
        .badge-slate { background-color: #f1f5f9; color: #475569; }
        .badge-indigo { background-color: #e0e7ff; color: #4338ca; }
        .badge-emerald { background-color: #d1fae5; color: #065f46; }
        .badge-rose { background-color: #ffe4e6; color: #9f1239; }
        .badge-amber { background-color: #fef3c7; color: #92400e; }
        .badge-cyan { background-color: #ecfeff; color: #155e75; }

        .text-right { text-align: right; }
        .font-semibold { font-weight: 600; }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        .kpi-cards {
            width: 100%;
            margin-bottom: 15px;
            border-spacing: 8px;
            border-collapse: separate;
        }
        .kpi-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            vertical-align: top;
        }
        .kpi-title {
            font-size: 9px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .kpi-value {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
        }
        .kpi-sub {
            font-size: 9px;
            color: #64748b;
            margin-top: 3px;
        }
        .chart-bar-container {
            background-color: #e2e8f0;
            border-radius: 4px;
            height: 8px;
            width: 100%;
            overflow: hidden;
            margin-top: 5px;
        }
        .chart-bar-fill {
            height: 100%;
            float: left;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1 class="header-title">{{ $title }}</h1>
        <div class="header-meta">Generated on {{ now()->format('d M Y \a\t H:i:s') }} by {{ auth()->user()?->name ?? 'System' }}</div>
    </div>

    @if(isset($summaryStats))
    {{-- Executive Summary KPI Header Cards --}}
    <table class="kpi-cards" style="margin-left:-8px; margin-right:-8px;">
        <tr>
            <td class="kpi-card" style="width: 30%;">
                <div class="kpi-title">👥 Total Customers</div>
                <div class="kpi-value" style="color: #4338ca;">{{ number_format($summaryStats['total_customers'] ?? 0) }}</div>
                <div class="kpi-sub">Deduplicated across all CRM channels</div>
            </td>
            <td class="kpi-card" style="width: 35%;">
                <div class="kpi-title">💰 Total Sales / Revenue</div>
                <div class="kpi-value" style="color: #059669;">${{ number_format($summaryStats['total_sales'] ?? 0, 2) }}</div>
                <div class="kpi-sub">Lifetime customer order revenue</div>
            </td>
            <td class="kpi-card" style="width: 35%;">
                <div class="kpi-title">🌐 Channel Distribution</div>
                <div class="kpi-sub" style="margin-top: 2px;">
                    <span style="color:#0ea5e9; font-weight:bold;">eBay:</span> {{ $summaryStats['ebay_count'] ?? 0 }} &nbsp;|&nbsp;
                    <span style="color:#8b5cf6; font-weight:bold;">Website:</span> {{ $summaryStats['website_count'] ?? 0 }} &nbsp;|&nbsp;
                    <span style="color:#f59e0b; font-weight:bold;">Logistics:</span> {{ $summaryStats['logistics_count'] ?? 0 }}
                </div>
                @php
                    $totCh = max(1, ($summaryStats['ebay_count'] ?? 0) + ($summaryStats['website_count'] ?? 0) + ($summaryStats['logistics_count'] ?? 0));
                    $ebayPct = round((($summaryStats['ebay_count'] ?? 0) / $totCh) * 100);
                    $webPct  = round((($summaryStats['website_count'] ?? 0) / $totCh) * 100);
                    $logPct  = max(0, 100 - $ebayPct - $webPct);
                @endphp
                <div class="chart-bar-container">
                    <div class="chart-bar-fill" style="width: {{ $ebayPct }}%; background-color: #0ea5e9;"></div>
                    <div class="chart-bar-fill" style="width: {{ $webPct }}%; background-color: #8b5cf6;"></div>
                    <div class="chart-bar-fill" style="width: {{ $logPct }}%; background-color: #f59e0b;"></div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Delivery Status & Issues Health KPI Grid --}}
    <table class="kpi-cards" style="margin-left:-8px; margin-right:-8px; margin-top:-8px;">
        <tr>
            <td class="kpi-card" style="width: 45%; background-color: #f0f9ff; border-color: #bae6fd;">
                <div class="kpi-title" style="color: #0369a1;">🚚 Delivery & Shipping Status</div>
                <div class="kpi-sub" style="color: #0f172a; font-size: 10px; margin-top:4px;">
                    <b>In Delivery:</b> <span class="badge badge-indigo">{{ $summaryStats['in_delivery_count'] ?? 0 }}</span> &nbsp;
                    <b>Delivered:</b> <span class="badge badge-emerald">{{ $summaryStats['delivered_count'] ?? 0 }}</span> &nbsp;
                    <b>Waiting Pick Up:</b> <span class="badge badge-amber">{{ $summaryStats['waiting_pickup_count'] ?? 0 }}</span>
                </div>
            </td>
            <td class="kpi-card" style="width: 55%; background-color: #fff1f2; border-color: #fecdd3;">
                <div class="kpi-title" style="color: #9f1239;">⚠️ Issues & Feedback Health</div>
                <div class="kpi-sub" style="color: #0f172a; font-size: 10px; margin-top:4px;">
                    <b>Logistic Issues:</b> <span class="badge badge-amber" style="background-color: #ffedd5; color: #c2410c;">{{ $summaryStats['logistic_issues_count'] ?? 0 }}</span> &nbsp;
                    <b>Negative Feedback:</b> <span class="badge badge-rose">{{ $summaryStats['negative_feedback_count'] ?? 0 }}</span> &nbsp;
                    <b>Technical Issues:</b> <span class="badge badge-indigo" style="background-color: #f3e8ff; color: #6b21a8;">{{ $summaryStats['technical_issues_count'] ?? 0 }}</span>
                </div>
            </td>
        </tr>
    </table>
    @endif

    <div class="filter-summary">
        <table class="filter-grid">
            <tr>
                <td class="filter-label">Filtered Member:</td>
                <td class="filter-value">{{ $formattedMember }}</td>
                <td class="filter-label">Date Range:</td>
                <td class="filter-value">{{ $dateRangeStr }}</td>
            </tr>
            <tr>
                <td class="filter-label">Total Records:</td>
                <td class="filter-value font-semibold">{{ $data->count() }}</td>
                <td></td>
                <td></td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th class="{{ $header === 'Value (USD)' || $header === 'Budget (USD)' || $header === 'Offer Amount (USD)' || $header === 'Final Amount (USD)' ? 'text-right' : '' }}">
                        {{ $header }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($data as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    
                    @if($type === 'customers')
                        @if(is_array($row))
                            <td>{{ $row['id'] ?? '—' }}</td>
                            <td>
                                <span class="font-semibold">{{ $row['name'] ?? '—' }}</span>
                            </td>
                            <td>{{ $row['email'] ?? '—' }}</td>
                            <td>{{ $row['phone'] ?? '—' }}</td>
                            <td>{{ $row['address'] ?? '—' }}</td>
                            <td>{{ $row['company'] ?? '—' }}</td>
                            <td>
                                <span class="badge badge-slate">
                                    {{ $row['status_label'] ?? ($row['status'] ?? '—') }}
                                </span>
                            </td>
                            <td>{{ $row['source'] ?? '—' }}</td>
                            <td>—</td>
                            <td>—</td>
                            <td>{{ !empty($row['purchase_date']) ? (is_string($row['purchase_date']) ? $row['purchase_date'] : $row['purchase_date']->format('d M Y')) : '—' }}</td>
                            <td>—</td>
                            <td class="text-right font-semibold">${{ number_format((float)($row['lifetime_value'] ?? 0), 2) }}</td>
                            <td>{{ $row['handler'] ?? ($row['assigned_to_name'] ?? 'Unassigned') }}</td>
                            <td>—</td>
                            <td>{{ !empty($row['created_date']) ? (is_string($row['created_date']) ? $row['created_date'] : $row['created_date']->format('d M Y')) : '—' }}</td>
                            <td>—</td>
                        @else
                            <td>{{ $row->id }}</td>
                            <td>
                                <span class="font-semibold">{{ $row->name }}</span>
                            </td>
                            <td>{{ $row->email ?? '—' }}</td>
                            <td>{{ $row->phone ?? '—' }}</td>
                            <td>{{ $row->address ?? '—' }}</td>
                            <td>{{ $row->company ?? '—' }}</td>
                            <td>
                                <span class="badge {{ is_object($row->status) ? $row->status->badgeClass() : 'badge-slate' }}">
                                    {{ is_object($row->status) ? $row->status->label() : ($row->status ?? '—') }}
                                </span>
                            </td>
                            <td>{{ is_object($row->source) ? ($row->source->label ?? (string)$row->source) : (is_array($row->source) ? implode(', ', $row->source) : ($row->source ?? '—')) }}</td>
                            <td>
                                @if(is_object($row->pipeline_stage))
                                    <span class="badge" style="background-color: {{ $row->pipeline_stage->color() }}22; color: {{ $row->pipeline_stage->color() }}">
                                        {{ $row->pipeline_stage->label() }}
                                    </span>
                                @else
                                    {{ $row->pipeline_stage ?? '—' }}
                                @endif
                            </td>
                            <td>{{ is_object($row->current_queue) ? $row->current_queue->label() : ($row->current_queue ?? '—') }}</td>
                            <td>{{ $row->first_purchase_date ? $row->first_purchase_date->format('d M Y') : '—' }}</td>
                            <td>{{ is_array($row->product_interests) ? implode('; ', $row->product_interests) : ($row->product_interests ?? '—') }}</td>
                            <td class="text-right font-semibold">${{ number_format((float)($row->lifetime_value ?? 0), 2) }}</td>
                            <td>{{ $row->assignee ? $row->assignee->name : 'Unassigned' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($row->interactions->first()?->content ?? '—', 60) }}</td>
                            <td>{{ $row->created_at ? $row->created_at->format('d M Y') : '—' }}</td>
                            <td>{{ $row->updated_at ? $row->updated_at->format('d M Y') : '—' }}</td>
                        @endif

                    @elseif($type === 'logistics')
                        @if($row instanceof \App\Models\ShipmentCustomer)
                            <td class="font-semibold">{{ $row->shipment ? $row->shipment->shipment_code : '—' }}</td>
                            <td>{{ $row->customer ? $row->customer->name : '—' }}</td>
                            <td>{{ $row->product_description ?? '—' }}</td>
                            <td>{{ $row->recipient_name }}</td>
                            <td style="max-width: 150px;">{{ $row->shipping_address ?? '—' }}</td>
                            <td class="text-right font-semibold">—</td>
                            <td>
                                <span class="badge badge-indigo">
                                    {{ ucfirst($row->status ?? 'pending') }}
                                </span>
                            </td>
                            <td>{{ $row->created_at ? $row->created_at->format('d M Y H:i') : '—' }}</td>
                        @else
                            <td class="font-semibold">{{ $row->order_id ?? '—' }}</td>
                            <td>{{ $row->customer ? $row->customer->name : '—' }}</td>
                            <td>{{ $row->product ? $row->product->name : '—' }}</td>
                            <td>{{ $row->recipient_name ?? '—' }}</td>
                            <td style="max-width: 150px;">{{ $row->shipping_address ?? '—' }}</td>
                            <td class="text-right font-semibold">${{ number_format((float)($row->shipping_budget ?? 0), 2) }}</td>
                            <td>
                                <span class="badge {{ is_object($row->status) ? $row->status->badgeClass() : 'badge-slate' }}">
                                    {{ is_object($row->status) ? $row->status->label() : ($row->status ?? '—') }}
                                </span>
                            </td>
                            <td>{{ $row->pickup_datetime ? $row->pickup_datetime->format('d M Y H:i') : '—' }}</td>
                        @endif

                    @elseif($type === 'website')
                        <td class="font-semibold">{{ $row->client_name ?? '—' }}</td>
                        <td>{{ $row->client_email ?? '—' }}</td>
                        <td>{{ $row->client_phone ?? '—' }}</td>
                        <td>{{ is_object($row->source) ? $row->source->label() : ($row->source ?? '—') }}</td>
                        <td>{{ $row->product ? $row->product->name : ($row->product_interested ?? '—') }}</td>
                        <td>
                            <span class="badge {{ is_object($row->status) ? $row->status->badgeClass() : 'badge-slate' }}">
                                {{ is_object($row->status) ? $row->status->label() : ($row->status ?? '—') }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ is_object($row->temperature) ? $row->temperature->badgeClass() : 'badge-slate' }}">
                                {{ is_object($row->temperature) ? $row->temperature->label() : ($row->temperature ?? '—') }}
                            </span>
                        </td>
                        <td>{{ $row->received_at ? $row->received_at->format('d M Y H:i') : ($row->created_at ? $row->created_at->format('d M Y H:i') : '—') }}</td>

                    @elseif($type === 'ebay')
                        @if($row instanceof \App\Models\EbayCustomerRecord)
                            <td>{{ $row->store ? $row->store->store_name : '—' }}</td>
                            <td class="font-semibold">{{ $row->buyer_name ?: ($row->username ?: '—') }}</td>
                            <td>{{ $row->username ?? '—' }}</td>
                            <td>{{ $row->order_id ?? '—' }}</td>
                            <td>{{ $row->summary ?? '—' }}</td>
                            <td>
                                <span class="badge badge-indigo">
                                    {{ ucfirst($row->tab_type ?? 'Record') }}
                                </span>
                            </td>
                            <td>{{ ($row->date ?? $row->created_at)?->format('d M Y H:i') ?? '—' }}</td>
                        @else
                            <td>{{ $row->store ? $row->store->store_name : '—' }}</td>
                            <td class="font-semibold">{{ $row->customer ? $row->customer->name : ($row->client_name ?? '—') }}</td>
                            <td>{{ $row->ebay_username ?? '—' }}</td>
                            <td>{{ $row->product ? $row->product->name : '—' }}</td>
                            <td class="text-right">${{ number_format((float)($row->offer_amount ?? 0), 2) }}</td>
                            <td class="text-right font-semibold">${{ number_format((float)($row->final_amount ?? 0), 2) }}</td>
                            <td>
                                <span class="badge {{ is_object($row->status) ? $row->status->badgeClass() : 'badge-slate' }}">
                                    {{ is_object($row->status) ? $row->status->label() : ($row->status ?? '—') }}
                                </span>
                            </td>
                            <td>{{ $row->received_at ? $row->received_at->format('d M Y H:i') : '—' }}</td>
                        @endif
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="15" style="text-align: center; color: #94a3b8; padding: 30px;">
                        No records match the selected filters.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Digital & CRM Management System · Confidential Reports
    </div>

</body>
</html>
