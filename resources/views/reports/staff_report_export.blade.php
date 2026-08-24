<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Performance Report — {{ $user->name }} — {{ $periodLabel }}</title>
    <style>
        @page {
            margin: 25px 30px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10.5px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
        }
        
        /* Executive Header */
        .brand-header {
            background-color: #0f172a;
            color: #ffffff;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .brand-logo-badge {
            display: inline-block;
            background-color: #312e81;
            color: #818cf8;
            font-size: 8.5px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 4px;
            margin-bottom: 6px;
            border: 1px solid #4338ca;
        }
        .header-title {
            font-size: 18px;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 4px 0;
            letter-spacing: 0.3px;
        }
        .header-subtitle {
            font-size: 9.5px;
            color: #94a3b8;
            margin: 0;
        }

        /* Summary Banner */
        .summary-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 5px solid #4f46e5;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 22px;
        }
        .summary-title {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #4f46e5;
            margin-bottom: 4px;
        }
        .summary-value {
            font-size: 22px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 8px;
        }

        /* Domain Section Cards */
        .domain-section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .domain-header {
            padding: 8px 12px;
            border-radius: 6px 6px 0 0;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: #ffffff;
        }
        
        /* Domain Colors */
        .dh-logistic { background-color: #059669; }
        .dh-ebay { background-color: #d97706; }
        .dh-website { background-color: #7c3aed; }
        .dh-tech_support { background-color: #e11d48; }
        .dh-default { background-color: #334155; }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-top: none;
            border-radius: 0 0 6px 6px;
        }
        th {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 7px 12px;
            border-bottom: 1px solid #cbd5e1;
            text-align: left;
        }
        th.text-right {
            text-align: right;
        }
        td {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 10px;
        }
        td.text-right {
            text-align: right;
            font-weight: 700;
        }
        tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    @php
        $domainMeta = [
            'website'      => ['label' => 'Website Activity', 'tag' => 'WEBSITE', 'rows' => ['Handled' => $summary['website']['crm_handled'] ?? 0, 'Total Order' => $summary['website']['crm_sales'] ?? 0, 'Calls Answered' => $summary['website']['calls_answered'] ?? 0]],
            'ebay'         => ['label' => 'eBay Activity', 'tag' => 'EBAY', 'rows' => ['Handled' => $summary['ebay']['ebay_handled'] ?? 0]],
            'tech_support' => ['label' => 'Technical Support Activity', 'tag' => 'TECH SUPPORT', 'rows' => ['Cases Assigned' => $summary['tech_support']['assigned'] ?? 0, 'Cases Resolved' => $summary['tech_support']['resolved'] ?? 0, 'Total Issues' => $summary['tech_support']['Total Issues'] ?? 0, 'Negative Feedback' => $summary['tech_support']['Negative Feedback'] ?? 0]],
            'logistic'     => ['label' => 'Logistic Activity', 'tag' => 'LOGISTIC', 'rows' => ['Number of Shipments' => $summary['logistic']['assigned'] ?? 0, 'Complete' => $summary['logistic']['complete'] ?? 0, 'Total Issues' => $summary['logistic']['Total Issues'] ?? 0, 'Negative Feedback' => $summary['logistic']['Negative Feedback'] ?? 0]],
        ];
        $headline = [
            'website'      => $summary['website']['crm_handled'] ?? 0,
            'ebay'         => $summary['ebay']['ebay_handled'] ?? 0,
            'tech_support' => $summary['tech_support']['assigned'] ?? 0,
            'logistic'     => $summary['logistic']['assigned'] ?? 0,
        ];
        $totalHandled = collect($activeDomains)->sum(fn ($d) => $headline[$d]);
    @endphp

    <!-- Executive Header -->
    <div class="brand-header">
        <div class="brand-logo-badge">DIGITAL SYSTEM · STAFF PERFORMANCE</div>
        <h1 class="header-title">Staff Performance Report — {{ $user->name }}</h1>
        <div class="header-subtitle">
            Period: <strong>{{ $periodLabel }}</strong> &nbsp;|&nbsp; 
            Generated: <strong>{{ now()->format('d M Y \a\t H:i:s') }}</strong> &nbsp;|&nbsp; 
            Exported By: <strong>{{ auth()->user()->name ?? 'System Administrator' }}</strong>
        </div>
    </div>

    <!-- Summary Card -->
    <div class="summary-card">
        <div class="summary-title">Total Activity Items Handled</div>
        <div class="summary-value">{{ number_format($totalHandled) }} <span style="font-size:12px; font-weight:600; color:#64748b;">Items</span></div>
    </div>

    <!-- Domain Activity Tables -->
    @forelse($activeDomains as $d)
    @php
        $dhClass = match($d) {
            'logistic' => 'dh-logistic',
            'ebay' => 'dh-ebay',
            'website' => 'dh-website',
            'tech_support' => 'dh-tech_support',
            default => 'dh-default'
        };
    @endphp
    <div class="domain-section">
        <div class="domain-header {{ $dhClass }}">
            {{ $domainMeta[$d]['label'] }}
        </div>
        <table>
            <thead>
                <tr>
                    <th>Performance Metric</th>
                    <th class="text-right">Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach($domainMeta[$d]['rows'] as $metricLabel => $value)
                <tr>
                    <td>{{ $metricLabel }}</td>
                    <td class="text-right">{{ number_format($value) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @empty
    <p style="color:#64748b; font-style:italic;">No staff activity recorded for this period.</p>
    @endforelse

    <div class="footer">
        Digital & CRM System Staff Activity Report &nbsp;·&nbsp; Confidential & Proprietary
    </div>

</body>
</html>
