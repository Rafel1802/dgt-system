<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Follow-Up Personal Report</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 20px; background: #fff; }
        .header { border-bottom: 3px solid #4f46e5; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { font-size: 20px; margin: 0 0 5px 0; color: #1e293b; }
        .header .meta { font-size: 10px; color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #4f46e5; color: #fff; text-align: left; padding: 8px; font-size: 9px; text-transform: uppercase; }
        td { padding: 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; font-size: 10px; word-wrap: break-word; }
        tr:nth-child(even) td { background: #f8fafc; }
        .status { font-weight: bold; }
        .footer { text-align: right; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Follow-Up Personal Report</h1>
        <div class="meta">
            Member: <strong>{{ $user ? $user->name : 'All Members' }}</strong> |
            Date Range: <strong>{{ $dateFrom }}</strong> to <strong>{{ $dateTo }}</strong> |
            Exported: <strong>{{ now()->format('d M Y, g:i a') }}</strong>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="12%">Date</th>
                <th width="15%">Website</th>
                <th width="15%">Type</th>
                <th width="15%">Handle By</th>
                <th width="10%">QC Status</th>
                <th width="13%">QC Checker</th>
                <th width="20%">Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse($followUps as $fu)
            <tr>
                <td>{{ $fu->created_at->format('d M Y H:i') }}</td>
                <td>
                    <strong>{{ $fu->website->name ?? 'Unknown' }}</strong><br>
                    <span style="font-size: 8px; color: #64748b;">{{ $fu->website->category ?? 'Uncategorized' }}</span>
                </td>
                <td>
                    {{ $fu->getTypeLabel() }}
                    @if($fu->url)
                    <br><a href="{{ $fu->url }}" style="font-size: 8px; color: #4f46e5;">Link</a>
                    @endif
                </td>
                <td>{{ $fu->assignee?->name ?? 'Unassigned' }}</td>
                <td class="status" style="color: {{ $fu->qc_status === 'approved' ? '#15803d' : ($fu->qc_status === 'checked' ? '#1d4ed8' : '#a16207') }};">
                    {{ ucfirst($fu->qc_status) }}
                </td>
                <td>
                    {{ $fu->qcChecker?->name ?? '—' }}<br>
                    <span style="font-size: 8px; color: #64748b;">{{ $fu->qc_checked_at?->format('d M Y') ?? '' }}</span>
                </td>
                <td>{{ strip_tags($fu->note ?? '') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align: center; color: #94a3b8; padding: 20px;">No follow-up activities found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        KIUQ SYSTEM — Generated {{ now()->format('d M Y, g:i a') }}
    </div>
</body>
</html>
