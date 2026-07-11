<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>eBay Report — {{ $periodLabel }}</title>
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
        .summary {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        .summary-grid {
            width: 100%;
        }
        .summary-grid td {
            padding: 2px 0;
        }
        .summary-label {
            font-weight: 600;
            color: #92400e;
            width: 160px;
        }
        .summary-value {
            color: #0f172a;
            font-weight: 700;
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
        }
        tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .total-row td {
            border-top: 2px solid #cbd5e1;
            font-weight: 700;
            background-color: #f1f5f9 !important;
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
        <h1 class="header-title">eBay Report — {{ $periodLabel }}</h1>
        <div class="header-meta">Generated on {{ now()->format('d M Y \a\t H:i:s') }} by {{ auth()->user()->name }}</div>
    </div>

    <div class="summary">
        <table class="summary-grid">
            <tr>
                <td class="summary-label">Total Orders (all stores):</td>
                <td class="summary-value">{{ $totalOrders }}</td>
                <td class="summary-label">Total Sales (all stores):</td>
                <td class="summary-value">${{ number_format($totalSales, 2) }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Store</th>
                <th>Orders</th>
                <th>Sales</th>
            </tr>
        </thead>
        <tbody>
            @forelse($storeReports as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row['store']->store_name }}</td>
                    <td>{{ $row['orders'] }}</td>
                    <td>${{ number_format($row['sales'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #94a3b8; padding: 30px;">
                        No stores found.
                    </td>
                </tr>
            @endforelse
            @if($storeReports->isNotEmpty())
                <tr class="total-row">
                    <td colspan="2">Total</td>
                    <td>{{ $totalOrders }}</td>
                    <td>${{ number_format($totalSales, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        Digital & CRM Management System · Confidential Reports
    </div>

</body>
</html>
