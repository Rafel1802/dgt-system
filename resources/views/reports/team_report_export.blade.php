<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Team Report — {{ $periodLabel }}</title>
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
        .total-sales {
            background-color: #eef2ff;
            border: 1px solid #c7d2fe;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        .total-sales .label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #4338ca;
            letter-spacing: 0.5px;
        }
        .total-sales .value {
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

    <div class="header">
        <h1 class="header-title">Team Report — {{ $periodLabel }}</h1>
        <div class="header-meta">Generated on {{ now()->format('d M Y \a\t H:i:s') }} by {{ auth()->user()->name }}</div>
    </div>

    <div class="total-sales">
        <div class="label">Total Sales (eBay + Website)</div>
        <div class="value">${{ number_format($totalSales, 2) }}</div>
    </div>

    @foreach($domainReports as $domain)
    <div class="domain-heading">{{ $domain['label'] }}</div>
    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach($domain['metrics'] as $metricLabel => $value)
            <tr>
                <td>{{ $metricLabel }}</td>
                <td>{{ in_array($metricLabel, $domain['money_keys']) ? '$' . number_format($value, 2) : $value }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endforeach

    <div class="footer">
        Digital & CRM Management System · Confidential Reports
    </div>

</body>
</html>
