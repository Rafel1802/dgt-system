<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 25px 25px 35px 25px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #334155;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        
        /* Executive Header Banner */
        .brand-header {
            background-color: #1e293b;
            color: #ffffff;
            padding: 14px 18px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .brand-title {
            font-size: 18px;
            font-weight: 800;
            margin: 0;
            letter-spacing: 0.5px;
            color: #ffffff;
            text-transform: uppercase;
        }
        .brand-meta {
            font-size: 9px;
            color: #94a3b8;
            margin-top: 3px;
        }

        /* KPI Cards Grid */
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 12px;
        }
        .kpi-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            vertical-align: top;
        }
        .kpi-card-title {
            font-size: 8px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 2px;
        }
        .kpi-card-value {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
        }
        .kpi-card-sub {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Horizontal Stacked Bar Graph */
        .chart-bar-bg {
            background-color: #cbd5e1;
            border-radius: 3px;
            height: 6px;
            width: 100%;
            overflow: hidden;
            margin-top: 4px;
        }
        .chart-bar-seg {
            height: 100%;
            float: left;
        }

        /* Filter Summary Strip */
        .filter-summary {
            background-color: #f1f5f9;
            border-left: 3px solid #3b82f6;
            padding: 6px 12px;
            margin-bottom: 15px;
            font-size: 9px;
        }
        .filter-summary table {
            width: 100%;
            border-collapse: collapse;
        }
        .filter-summary td {
            padding: 1px 0;
            border: none;
        }

        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .data-table th {
            background-color: #1e293b;
            color: #f8fafc;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8.5px;
            padding: 6px 8px;
            text-align: left;
            letter-spacing: 0.3px;
            border: none;
        }
        .data-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 9.5px;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            font-weight: 700;
            border-radius: 4px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .badge-slate   { background-color: #f1f5f9; color: #475569; }
        .badge-indigo  { background-color: #e0e7ff; color: #3730a3; }
        .badge-emerald { background-color: #d1fae5; color: #065f46; }
        .badge-rose    { background-color: #ffe4e6; color: #9f1239; }
        .badge-amber   { background-color: #fef3c7; color: #92400e; }
        .badge-cyan    { background-color: #cffafe; color: #155e75; }

        .text-right { text-align: right; }
        .font-bold { font-weight: 700; }
        .nowrap { white-space: nowrap; }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>

    {{-- Executive Header Banner --}}
    <div class="brand-header">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="border:none; padding:0;">
                    <h1 class="brand-title">{{ $title }}</h1>
                    <div class="brand-meta">Generated on {{ now()->format('d M Y \a\t H:i:s') }} by {{ auth()->user()?->name ?? 'System' }}</div>
                </td>
                <td style="border:none; padding:0; text-align:right; vertical-align:middle;">
                    <span style="background-color:#3b82f6; color:#ffffff; font-size:9px; font-weight:800; padding:4px 10px; border-radius:4px; text-transform:uppercase;">
                        EXECUTIVE REPORT
                    </span>
                </td>
            </tr>
        </table>
    </div>

    @if(isset($summaryStats))
    {{-- Executive KPI Metrics Cards --}}
    <table class="kpi-table" style="margin-left:-6px; margin-right:-6px;">
        <tr>
            <td class="kpi-card" style="width: 28%;">
                <div class="kpi-card-title">TOTAL CUSTOMERS</div>
                <div class="kpi-card-value" style="color: #3b82f6;">{{ number_format($summaryStats['total_customers'] ?? 0) }}</div>
                <div class="kpi-card-sub">Deduplicated across channels</div>
            </td>
            <td class="kpi-card" style="width: 34%;">
                <div class="kpi-card-title">TOTAL SALES / REVENUE</div>
                <div class="kpi-card-value" style="color: #10b981;">${{ number_format($summaryStats['total_sales'] ?? 0, 2) }}</div>
                <div class="kpi-card-sub">Lifetime customer order revenue</div>
            </td>
            <td class="kpi-card" style="width: 38%;">
                <div class="kpi-card-title">CHANNEL SALES BREAKDOWN</div>
                <div class="kpi-card-sub" style="margin-top:2px;">
                    <span style="color:#0284c7; font-weight:bold;">eBay:</span> {{ $summaryStats['ebay_count'] ?? 0 }} (${{ number_format($summaryStats['ebay_sales'] ?? 0, 2) }}) &nbsp;|&nbsp;
                    <span style="color:#8b5cf6; font-weight:bold;">Website:</span> {{ $summaryStats['website_count'] ?? 0 }} (${{ number_format($summaryStats['website_sales'] ?? 0, 2) }}) &nbsp;|&nbsp;
                    <span style="color:#d97706; font-weight:bold;">Logistics:</span> {{ $summaryStats['logistics_count'] ?? 0 }}
                </div>
                @php
                    $totSales = max(1, ($summaryStats['ebay_sales'] ?? 0) + ($summaryStats['website_sales'] ?? 0));
                    $ebayPct = round((($summaryStats['ebay_sales'] ?? 0) / $totSales) * 100);
                    $webPct  = max(0, 100 - $ebayPct);
                @endphp
                <div class="chart-bar-bg">
                    <div class="chart-bar-seg" style="width: {{ $ebayPct }}%; background-color: #0284c7;"></div>
                    <div class="chart-bar-seg" style="width: {{ $webPct }}%; background-color: #8b5cf6;"></div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Delivery Status & Health Indicators Grid --}}
    <table class="kpi-table" style="margin-left:-6px; margin-right:-6px; margin-top:-6px;">
        <tr>
            <td class="kpi-card" style="width: 48%; background-color: #f0f9ff; border-color: #bae6fd;">
                <div class="kpi-card-title" style="color: #0369a1;">DELIVERY & SHIPPING STATUS</div>
                <div class="kpi-card-sub" style="color: #0f172a; font-size: 9px; margin-top:3px;">
                    In Delivery: <span class="badge badge-indigo">{{ $summaryStats['in_delivery_count'] ?? 0 }}</span> &nbsp;
                    Delivered: <span class="badge badge-emerald">{{ $summaryStats['delivered_count'] ?? 0 }}</span> &nbsp;
                    Waiting Pickup: <span class="badge badge-amber">{{ $summaryStats['waiting_pickup_count'] ?? 0 }}</span>
                </div>
            </td>
            <td class="kpi-card" style="width: 52%; background-color: #fff1f2; border-color: #fecdd3;">
                <div class="kpi-card-title" style="color: #9f1239;">ISSUES & FEEDBACK HEALTH</div>
                <div class="kpi-card-sub" style="color: #0f172a; font-size: 9px; margin-top:3px;">
                    Logistic Issues: <span class="badge badge-amber" style="background-color: #ffedd5; color: #c2410c;">{{ $summaryStats['logistic_issues_count'] ?? 0 }}</span> &nbsp;
                    Negative Feedback: <span class="badge badge-rose">{{ $summaryStats['negative_feedback_count'] ?? 0 }}</span> &nbsp;
                    Tech Support: <span class="badge badge-indigo" style="background-color: #f3e8ff; color: #6b21a8;">{{ $summaryStats['technical_issues_count'] ?? 0 }}</span>
                </div>
            </td>
        </tr>
    </table>
    @endif

    {{-- Filter Summary Strip --}}
    <div class="filter-summary">
        <table>
            <tr>
                <td style="font-weight:700; width:100px; color:#475569;">Filtered Staff:</td>
                <td style="color:#0f172a;">{{ $formattedMember }}</td>
                <td style="font-weight:700; width:80px; color:#475569;">Date Range:</td>
                <td style="color:#0f172a;">{{ $dateRangeStr }}</td>
                <td style="font-weight:700; width:80px; color:#475569; text-align:right;">Total Rows:</td>
                <td style="color:#0f172a; font-weight:800; text-align:right; width:40px;">{{ $data->count() }}</td>
            </tr>
        </table>
    </div>

    {{-- Streamlined Data Table --}}
    <table class="data-table">
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
                    <td class="nowrap" style="width: 24px;">{{ $index + 1 }}</td>
                    
                    @if($type === 'customers')
                        @if(is_array($row))
                            <td class="nowrap" style="color:#64748b;">#{{ $row['id'] ?? '—' }}</td>
                            <td class="nowrap font-bold" style="color:#0f172a;">{{ $row['name'] ?? '—' }}</td>
                            <td>{{ $row['email'] ?? '—' }}</td>
                            <td class="nowrap">{{ $row['phone'] ?? '—' }}</td>
                            <td class="nowrap">
                                @php
                                    $badgesList = !empty($row['status_badges']) && is_array($row['status_badges'])
                                        ? $row['status_badges']
                                        : [['label' => $row['status_label'] ?? ($row['status'] ?? '—')]];
                                @endphp
                                @foreach($badgesList as $b)
                                    @php
                                        $bLabel = is_array($b) ? ($b['label'] ?? '') : (string)$b;
                                        $sStr = strtolower($bLabel);
                                        $badgeStyle = match(true) {
                                            str_contains($sStr, 'success') || str_contains($sStr, 'delivered') || str_contains($sStr, 'resolved') || str_contains($sStr, 'approved')
                                                => 'background-color: #d1fae5; color: #047857; border: 1px solid #a7f3d0;',
                                            str_contains($sStr, 'logistic') || str_contains($sStr, 'delay') || str_contains($sStr, 'problem')
                                                => 'background-color: #ffedd5; color: #c2410c; border: 1px solid #fed7aa;',
                                            str_contains($sStr, 'negative') || str_contains($sStr, 'urgent') || str_contains($sStr, 'cancel') || str_contains($sStr, 'lost')
                                                => 'background-color: #ffe4e6; color: #be123c; border: 1px solid #fecdd3;',
                                            str_contains($sStr, 'tech')
                                                => 'background-color: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff;',
                                            str_contains($sStr, 'new') || str_contains($sStr, 'contact')
                                                => 'background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;',
                                            str_contains($sStr, 'nurtur') || str_contains($sStr, 'warm') || str_contains($sStr, 'pending')
                                                => 'background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a;',
                                            default
                                                => 'background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;',
                                        };
                                    @endphp
                                    <div style="margin-bottom: 2px;">
                                        <span class="badge" style="{{ $badgeStyle }}">
                                            {{ $bLabel }}
                                        </span>
                                    </div>
                                @endforeach
                            </td>
                            <td class="nowrap">
                                <span class="badge {{ str_contains(strtolower($row['source'] ?? ''), 'ebay') ? 'badge-cyan' : (str_contains(strtolower($row['source'] ?? ''), 'website') ? 'badge-indigo' : 'badge-amber') }}">
                                    {{ $row['source'] ?? '—' }}
                                </span>
                            </td>
                            <td class="text-right font-bold nowrap" style="color:#059669;">
                                ${{ number_format((float)($row['lifetime_value'] ?? 0), 2) }}
                            </td>
                            <td class="nowrap">{{ $row['handler'] ?? ($row['assigned_to_name'] ?? 'Unassigned') }}</td>
                            <td class="nowrap" style="color:#64748b;">
                                {{ !empty($row['created_date']) ? (is_string($row['created_date']) ? $row['created_date'] : $row['created_date']->format('d M Y')) : '—' }}
                            </td>
                        @else
                            <td class="nowrap" style="color:#64748b;">#{{ $row->id }}</td>
                            <td class="nowrap font-bold" style="color:#0f172a;">{{ $row->name }}</td>
                            <td>{{ $row->email ?? '—' }}</td>
                            <td class="nowrap">{{ $row->phone ?? '—' }}</td>
                            <td class="nowrap">
                                @php
                                    $statusText = is_object($row->status) ? $row->status->label() : ($row->status ?? '—');
                                    $sStr = strtolower((string)$statusText);
                                    $badgeStyle = match(true) {
                                        str_contains($sStr, 'success') || str_contains($sStr, 'delivered') || str_contains($sStr, 'resolved') || str_contains($sStr, 'approved')
                                            => 'background-color: #d1fae5; color: #047857; border: 1px solid #a7f3d0;',
                                        str_contains($sStr, 'logistic') || str_contains($sStr, 'delay') || str_contains($sStr, 'problem')
                                            => 'background-color: #ffedd5; color: #c2410c; border: 1px solid #fed7aa;',
                                        str_contains($sStr, 'negative') || str_contains($sStr, 'urgent') || str_contains($sStr, 'cancel') || str_contains($sStr, 'lost')
                                            => 'background-color: #ffe4e6; color: #be123c; border: 1px solid #fecdd3;',
                                        str_contains($sStr, 'tech')
                                            => 'background-color: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff;',
                                        str_contains($sStr, 'new') || str_contains($sStr, 'contact')
                                            => 'background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;',
                                        str_contains($sStr, 'nurtur') || str_contains($sStr, 'warm') || str_contains($sStr, 'pending')
                                            => 'background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a;',
                                        default
                                            => 'background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;',
                                    };
                                @endphp
                                <span class="badge" style="{{ $badgeStyle }}">
                                    {{ $statusText }}
                                </span>
                            </td>
                            <td class="nowrap">
                                <span class="badge badge-indigo">
                                    {{ is_object($row->source) ? ($row->source->label ?? (string)$row->source) : (is_array($row->source) ? implode(', ', $row->source) : ($row->source ?? '—')) }}
                                </span>
                            </td>
                            <td class="text-right font-bold nowrap" style="color:#059669;">
                                ${{ number_format((float)($row->lifetime_value ?? 0), 2) }}
                            </td>
                            <td class="nowrap">{{ $row->assignee ? $row->assignee->name : 'Unassigned' }}</td>
                            <td class="nowrap" style="color:#64748b;">
                                {{ $row->created_at ? $row->created_at->format('d M Y') : '—' }}
                            </td>
                        @endif

                    @elseif($type === 'logistics')
                        @if($row instanceof \App\Models\ShipmentCustomer)
                            <td class="nowrap font-bold">{{ $row->shipment ? $row->shipment->shipment_code : '—' }}</td>
                            <td class="nowrap font-bold">{{ $row->customer ? $row->customer->name : '—' }}</td>
                            <td>{{ $row->product_description ?? '—' }}</td>
                            <td>{{ $row->recipient_name }}</td>
                            <td>{{ $row->shipping_address ?? '—' }}</td>
                            <td class="text-right font-bold">${{ number_format((float)($row->shipment?->cost ?? 0), 2) }}</td>
                            <td class="nowrap">
                                <span class="badge {{ $row->status === 'delivered' ? 'badge-emerald' : ($row->status === 'problem' ? 'badge-rose' : 'badge-amber') }}">
                                    {{ ucfirst($row->status ?? 'pending') }}
                                </span>
                            </td>
                            <td class="nowrap" style="color:#64748b;">{{ $row->created_at ? $row->created_at->format('d M Y') : '—' }}</td>
                        @else
                            <td class="nowrap font-bold">{{ $row->order_id ?? '—' }}</td>
                            <td class="nowrap font-bold">{{ $row->customer ? $row->customer->name : '—' }}</td>
                            <td>{{ $row->product ? $row->product->name : '—' }}</td>
                            <td>{{ $row->recipient_name ?? '—' }}</td>
                            <td>{{ $row->shipping_address ?? '—' }}</td>
                            <td class="text-right font-bold">${{ number_format((float)($row->shipping_budget ?? 0), 2) }}</td>
                            <td class="nowrap">
                                <span class="badge {{ is_object($row->status) ? $row->status->badgeClass() : 'badge-slate' }}">
                                    {{ is_object($row->status) ? $row->status->label() : ($row->status ?? '—') }}
                                </span>
                            </td>
                            <td class="nowrap" style="color:#64748b;">{{ $row->pickup_datetime ? $row->pickup_datetime->format('d M Y') : '—' }}</td>
                        @endif

                    @elseif($type === 'website')
                        <td class="nowrap font-bold">{{ $row->client_name }}</td>
                        <td>{{ $row->client_email ?? '—' }}</td>
                        <td class="nowrap">{{ $row->client_phone ?? '—' }}</td>
                        <td class="nowrap">
                            <span class="badge badge-indigo">
                                {{ is_object($row->source) ? $row->source->label() : ($row->source ?? '—') }}
                            </span>
                        </td>
                        <td>{{ $row->product ? $row->product->name : ($row->product_interest ?? '—') }}</td>
                        <td class="nowrap">
                            <span class="badge {{ is_object($row->status) ? $row->status->badgeClass() : 'badge-slate' }}">
                                {{ is_object($row->status) ? $row->status->label() : ($row->status ?? '—') }}
                            </span>
                        </td>
                        <td class="nowrap">
                            <span class="badge {{ is_object($row->temperature) ? $row->temperature->badgeClass() : 'badge-slate' }}">
                                {{ is_object($row->temperature) ? $row->temperature->label() : ($row->temperature ?? '—') }}
                            </span>
                        </td>
                        <td class="nowrap" style="color:#64748b;">{{ $row->received_at ? $row->received_at->format('d M Y') : '—' }}</td>

                    @elseif($type === 'ebay')
                        @if($row instanceof \App\Models\EbayCustomerRecord)
                            <td class="nowrap font-bold">{{ $row->tab_type ? (EbayCustomerRecord::tabs()[$row->tab_type] ?? $row->tab_type) : '—' }}</td>
                            <td class="nowrap font-bold">{{ $row->buyer_name }}</td>
                            <td class="nowrap" style="color:#64748b;">{{ $row->username ?? '—' }}</td>
                            <td>{{ $row->email ?? '—' }}</td>
                            <td class="nowrap">{{ $row->phone ?? '—' }}</td>
                            <td class="nowrap">
                                <span class="badge {{ $row->tab_type === 'resolved' ? 'badge-emerald' : ($row->shipment_delay ? 'badge-amber' : 'badge-rose') }}">
                                    {{ $row->tab_type ? (EbayCustomerRecord::tabs()[$row->tab_type] ?? '—') : '—' }}
                                </span>
                            </td>
                            <td class="nowrap" style="color:#64748b;">{{ $row->created_at ? $row->created_at->format('d M Y') : '—' }}</td>
                        @else
                            <td class="nowrap font-bold">{{ $row->store ? $row->store->store_name : '—' }}</td>
                            <td class="nowrap font-bold">{{ $row->customer ? $row->customer->name : ($row->client_name ?? '—') }}</td>
                            <td class="nowrap" style="color:#64748b;">{{ $row->ebay_username ?? '—' }}</td>
                            <td>{{ $row->product ? $row->product->name : '—' }}</td>
                            <td class="text-right font-bold">${{ number_format((float)($row->offer_amount ?? 0), 2) }}</td>
                            <td class="text-right font-bold" style="color:#059669;">${{ number_format((float)($row->final_amount ?? 0), 2) }}</td>
                            <td class="nowrap">
                                <span class="badge {{ is_object($row->status) ? $row->status->badgeClass() : 'badge-slate' }}">
                                    {{ is_object($row->status) ? $row->status->label() : ($row->status ?? '—') }}
                                </span>
                            </td>
                            <td class="nowrap" style="color:#64748b;">{{ $row->received_at ? $row->received_at->format('d M Y') : '—' }}</td>
                        @endif
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}" style="text-align:center; padding:20px; color:#94a3b8;">
                        No records found matching the specified report criteria.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Confidential Executive CRM Report &bull; Page 1 of 1 &bull; Generated by {{ config('app.name', 'KIUQ System') }}
    </div>

</body>
</html>
