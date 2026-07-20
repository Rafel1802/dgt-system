<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Report — {{ $user->name }} — {{ $periodLabel }}</title>
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
        .total-handled {
            background-color: #eef2ff;
            border: 1px solid #c7d2fe;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        .total-handled .label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #4338ca;
            letter-spacing: 0.5px;
        }
        .total-handled .value {
            font-size: 20px;
            font-weight: 800;
            color: #312e81;
        }
        .domain-heading {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            margin: 18px 0 6px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
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
        }
        tr:nth-child(even) td {
            background-color: #f8fafc;
        }
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
        }
    </style>
</head>
<body>

    @php
        $domainMeta = [
            'website'      => ['label' => 'Website', 'rows' => ['Handled' => $summary['website']['crm_handled'] ?? 0, 'Successful Leads' => $summary['website']['crm_sales'] ?? 0, 'Calls Answered' => $summary['website']['calls_answered'] ?? 0]],
            'ebay'         => ['label' => 'eBay', 'rows' => ['Handled' => $summary['ebay']['ebay_handled'] ?? 0]],
            'tech_support' => ['label' => 'Technical Support', 'rows' => ['Cases Assigned' => $summary['tech_support']['assigned'] ?? 0, 'Cases Resolved' => $summary['tech_support']['resolved'] ?? 0]],
            'logistic'     => ['label' => 'Logistic', 'rows' => ['Number of Shipments' => $summary['logistic']['assigned'] ?? 0, 'Complete' => $summary['logistic']['complete'] ?? 0]],
        ];
        $headline = [
            'website'      => $summary['website']['crm_handled'] ?? 0,
            'ebay'         => $summary['ebay']['ebay_handled'] ?? 0,
            'tech_support' => $summary['tech_support']['assigned'] ?? 0,
            'logistic'     => $summary['logistic']['assigned'] ?? 0,
        ];
        $totalHandled = collect($activeDomains)->sum(fn ($d) => $headline[$d]);
    @endphp

    <div class="header">
        <h1 class="header-title">Staff Report — {{ $user->name }}</h1>
        <div class="header-meta">{{ $periodLabel }} · Generated on {{ now()->format('d M Y \a\t H:i:s') }}</div>
    </div>

    <div class="total-handled">
        <div class="label">Total Handled</div>
        <div class="value">{{ $totalHandled }}</div>
    </div>

    @forelse($activeDomains as $d)
    <div class="domain-heading">{{ $domainMeta[$d]['label'] }}</div>
    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach($domainMeta[$d]['rows'] as $metricLabel => $value)
            <tr>
                <td>{{ $metricLabel }}</td>
                <td>{{ $value }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @empty
    <p>No staff activity recorded for this period.</p>
    @endforelse

    <div class="footer">
        Digital & CRM Management System · Confidential Reports
    </div>

</body>
</html>
