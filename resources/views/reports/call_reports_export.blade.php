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
            background-color: #e0e7ff;
            color: #4338ca;
        }
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
        }
    </style>
</head>
<body>

    <div class="header">
        <h1 class="header-title">{{ $title }}</h1>
        <div class="header-meta">Generated on {{ now()->format('d M Y \a\t H:i:s') }} by {{ auth()->user()->name }}</div>
    </div>

    <div class="filter-summary">
        <table class="filter-grid">
            <tr>
                <td class="filter-label">Date Range:</td>
                <td class="filter-value">{{ $dateRangeStr }}</td>
                <td class="filter-label">Total Records:</td>
                <td class="filter-value font-semibold">{{ $reports->count() }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Type</th>
                <th>Details / Note</th>
                <th>Answered By</th>
                <th>Date & Time</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reports as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="font-semibold">{{ $row->name }}</td>
                    <td>{{ $row->phone ?? '—' }}</td>
                    <td>{{ $row->email ?? '—' }}</td>
                    <td><span class="badge">{{ $row->inquiry_type }}</span></td>
                    <td style="max-width: 180px;">{{ $row->details ?? '—' }}</td>
                    <td>{{ $row->answeredBy?->name ?? '—' }}</td>
                    <td>{{ $row->occurred_at?->format('d M Y, g:ia') ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; color: #94a3b8; padding: 30px;">
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
