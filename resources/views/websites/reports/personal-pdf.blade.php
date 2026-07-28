<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Website Personal Report</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 20px; background: #fff; }
        .header { border-bottom: 3px solid #4f46e5; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { font-size: 20px; margin: 0 0 5px 0; color: #1e293b; }
        .header .meta { font-size: 10px; color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #4f46e5; color: #fff; text-align: left; padding: 8px; font-size: 9px; text-transform: uppercase; }
        td { padding: 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; font-size: 10px; }
        tr:nth-child(even) td { background: #f8fafc; }
        .action { font-weight: bold; color: #4f46e5; }
        .footer { text-align: right; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Website Personal Report</h1>
        <div class="meta">
            Member: <strong>{{ $user ? $user->name : 'All Members' }}</strong> |
            Date Range: <strong>{{ $dateFrom }}</strong> to <strong>{{ $dateTo }}</strong> |
            Exported: <strong>{{ now()->format('d M Y, g:i a') }}</strong>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="15%">Date</th>
                <th width="15%">User</th>
                <th width="20%">Website</th>
                <th width="15%">Action</th>
                <th width="35%">Details</th>
            </tr>
        </thead>
        <tbody>
            @forelse($activities as $act)
            <tr>
                <td>{{ $act['date']->format('d M Y H:i') }}</td>
                <td>{{ $act['user'] }}</td>
                <td>{{ $act['website'] }}</td>
                <td class="action">{{ $act['action'] }}</td>
                <td>{{ $act['details'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;">No activities found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        KIUQ SYSTEM — Generated {{ now()->format('d M Y, g:i a') }}
    </div>
</body>
</html>
