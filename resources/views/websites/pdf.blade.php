<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Websites Report</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            color: #1e293b;
            margin: 0;
            padding: 16px;
            background: #fff;
        }
        .report-header {
            border-bottom: 3px solid #4f46e5;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }
        .report-header h1 {
            color: #1e293b;
            font-size: 20px;
            margin: 0 0 4px 0;
        }
        .report-header .meta {
            color: #64748b;
            font-size: 9px;
        }
        .report-header .meta span {
            display: inline-block;
            margin-right: 16px;
        }

        /* Website block */
        .website-block {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            margin-bottom: 14px;
            page-break-inside: avoid;
            overflow: hidden;
        }

        /* Website title bar */
        .website-titlebar {
            background: #1e293b;
            color: #fff;
            padding: 7px 10px;
            display: table;
            width: 100%;
        }
        .website-titlebar .ws-name {
            font-size: 11px;
            font-weight: bold;
            display: table-cell;
            vertical-align: middle;
        }
        .website-titlebar .ws-badge {
            display: table-cell;
            text-align: right;
            vertical-align: middle;
        }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 20px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-build        { background:#dbeafe; color:#1d4ed8; }
        .badge-qc           { background:#fef9c3; color:#a16207; }
        .badge-supervisor   { background:#e0f2fe; color:#0369a1; }
        .badge-live         { background:#dcfce7; color:#15803d; }
        .badge-maintenance  { background:#ffedd5; color:#c2410c; }
        .badge-qc-error     { background:#fee2e2; color:#dc2626; }
        .badge-sup-error    { background:#fce7f3; color:#be185d; }
        .badge-archived     { background:#f1f5f9; color:#475569; }

        /* Info grid (website summary) */
        .info-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
            background: #f8fafc;
        }
        .info-grid td {
            padding: 5px 8px;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .info-grid .label {
            font-size: 7px;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: bold;
            letter-spacing: 0.3px;
        }
        .info-grid .value {
            font-size: 9px;
            color: #1e293b;
            font-weight: 600;
            margin-top: 1px;
        }

        /* Error notice box */
        .error-box {
            background: #fff1f2;
            border-left: 3px solid #ef4444;
            padding: 6px 10px;
            margin: 0;
        }
        .error-box .error-label {
            font-size: 7.5px;
            font-weight: bold;
            color: #dc2626;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .error-box .error-note {
            font-size: 9px;
            color: #7f1d1d;
        }
        .error-box .canva-link {
            font-size: 8px;
            color: #7c3aed;
            word-break: break-all;
            margin-top: 2px;
        }

        /* History section */
        .history-title {
            background: #f1f5f9;
            padding: 5px 10px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            color: #475569;
            letter-spacing: 0.5px;
            border-top: 1px solid #e2e8f0;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        .history-table th {
            background: #f8fafc;
            color: #64748b;
            font-size: 7.5px;
            text-transform: uppercase;
            font-weight: bold;
            padding: 5px 8px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .history-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
            font-size: 8.5px;
        }
        .history-table tr:last-child td {
            border-bottom: none;
        }
        .history-table tr.in-range td {
            background: #fffbeb;
        }
        .percent-pill {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
            min-width: 30px;
            text-align: center;
        }
        .percent-0   { background:#f1f5f9; color:#475569; }
        .percent-10  { background:#ede9fe; color:#6d28d9; }
        .percent-25  { background:#dbeafe; color:#1d4ed8; }
        .percent-50  { background:#e0f2fe; color:#0284c7; }
        .percent-75  { background:#d1fae5; color:#059669; }
        .percent-100 { background:#dcfce7; color:#15803d; }
        .percent-other { background:#fef9c3; color:#a16207; }

        .type-build { color:#4f46e5; font-weight:bold; font-size:7.5px; }
        .type-maintenance { color:#c2410c; font-weight:bold; font-size:7.5px; }

        /* Follow Ups table */
        .followups-table {
            width: 100%;
            border-collapse: collapse;
        }
        .followups-table th {
            background: #fef9c3;
            color: #78350f;
            font-size: 7.5px;
            text-transform: uppercase;
            font-weight: bold;
            padding: 4px 8px;
            text-align: left;
        }
        .followups-table td {
            padding: 4px 8px;
            border-bottom: 1px solid #fef9c3;
            font-size: 8.5px;
            vertical-align: top;
        }
        .followups-table tr:last-child td {
            border-bottom: none;
        }

        .footer {
            margin-top: 20px;
            text-align: right;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }

        a { color: #4f46e5; }
    </style>
</head>
<body>

    {{-- ── REPORT HEADER ──────────────────────────────────────────────── --}}
    <div class="report-header">
        <h1>📊 All Websites Report</h1>
        <div class="meta">
            <span>Generated: <strong>{{ now()->format('d M Y, g:i a') }}</strong></span>
            @if($startDate || $endDate)
            <span>Date Range: <strong>
                {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d M Y') : 'Beginning' }}
                → 
                {{ $endDate ? \Carbon\Carbon::parse($endDate)->format('d M Y') : 'Today' }}
            </strong></span>
            @endif
            <span>Total Websites: <strong>{{ $websites->count() }}</strong></span>
        </div>
    </div>

    {{-- ── WEBSITES ────────────────────────────────────────────────────── --}}
    @forelse($websites as $ws)
    @php
        // Determine badge class
        $statusBadgeClass = match(true) {
            str_contains($ws->status, 'QC Error') || str_contains($ws->status, 'qc_error') => 'badge-qc-error',
            str_contains($ws->status, 'Supervisor Error') || str_contains($ws->status, 'supervisor_error') => 'badge-sup-error',
            str_contains($ws->status, 'QC') => 'badge-qc',
            str_contains($ws->status, 'Supervisor') => 'badge-supervisor',
            str_contains($ws->status, 'Live') => 'badge-live',
            str_contains($ws->status, 'Maintenance') => 'badge-maintenance',
            str_contains($ws->status, 'Build') || str_contains($ws->status, 'build') => 'badge-build',
            default => 'badge-archived',
        };

        // Progress display
        $progressDisplay = $ws->status === 'Maintenance'
            ? 'Maint: ' . $ws->maintenance_percent . '%'
            : $ws->progress_percent . '%';

        // Filter progress logs by date range
        $allProgressLogs = $ws->progressLogs->sortBy('created_at');
        $filteredProgressLogs = $allProgressLogs;
        if (!empty($startDate) || !empty($endDate)) {
            $filteredProgressLogs = $allProgressLogs->filter(function($log) use ($startDate, $endDate) {
                $logDate = $log->created_at->startOfDay();
                if (!empty($startDate) && $logDate < \Carbon\Carbon::parse($startDate)->startOfDay()) return false;
                if (!empty($endDate) && $logDate > \Carbon\Carbon::parse($endDate)->endOfDay()) return false;
                return true;
            });
        }

        // Helper to get percent pill class
        $getPillClass = function($pct) {
            return match(true) {
                $pct >= 100 => 'percent-100',
                $pct >= 75  => 'percent-75',
                $pct >= 50  => 'percent-50',
                $pct >= 25  => 'percent-25',
                $pct >= 10  => 'percent-10',
                $pct == 0   => 'percent-0',
                default     => 'percent-other',
            };
        };
    @endphp

    <div class="website-block">

        {{-- Title Bar --}}
        <div class="website-titlebar">
            <span class="ws-name">{{ $ws->name }}</span>
            <span class="ws-badge"><span class="badge {{ $statusBadgeClass }}">{{ $ws->status }}</span></span>
        </div>

        {{-- Info Grid --}}
        <table class="info-grid">
            <tr>
                <td width="22%">
                    <div class="label">Website URL</div>
                    <div class="value"><a href="{{ $ws->url }}">{{ $ws->url }}</a></div>
                </td>
                <td width="13%">
                    <div class="label">Progress</div>
                    <div class="value">{{ $progressDisplay }}</div>
                </td>
                <td width="14%">
                    <div class="label">Handler / Member</div>
                    <div class="value">{{ $ws->handler?->name ?? '—' }}</div>
                </td>
                <td width="10%">
                    <div class="label">Class</div>
                    <div class="value">{{ $ws->category ?? 'Uncategorized' }}</div>
                </td>
                <td width="10%">
                    <div class="label">Start Date</div>
                    <div class="value">{{ $ws->start_date?->format('d/m/Y') ?? '—' }}</div>
                </td>
                <td width="10%">
                    <div class="label">Deadline</div>
                    <div class="value">{{ $ws->deadline?->format('d/m/Y') ?? '—' }}</div>
                </td>
                <td width="10%">
                    <div class="label">Live At</div>
                    <div class="value">{{ $ws->live_at?->format('d/m/Y') ?? '—' }}</div>
                </td>
                <td width="11%">
                    <div class="label">QC Approved By</div>
                    <div class="value">{{ $ws->qcApprover?->name ?? '—' }}</div>
                </td>
            </tr>
            @if($ws->notes)
            <tr>
                <td colspan="8">
                    <div class="label">General Notes</div>
                    <div class="value" style="font-weight:normal;">{{ strip_tags($ws->notes) }}</div>
                </td>
            </tr>
            @endif
        </table>

        {{-- Error / Canva Box (if has active error) --}}
        @if($ws->error_note || $ws->error_link)
        <div class="error-box">
            @if($ws->error_note)
            <div class="error-label">⚠ Current Error / Issue</div>
            <div class="error-note">{{ $ws->error_note }}</div>
            @endif
            @if($ws->error_link)
            <div class="canva-link">📎 Reference / Canva Link: <a href="{{ $ws->error_link }}">{{ $ws->error_link }}</a></div>
            @endif
        </div>
        @endif

        {{-- Update History Section --}}
        <div class="history-title">
            📋 Update History
            @if($startDate || $endDate)
                — <span style="color:#4f46e5;">{{ $filteredProgressLogs->count() }} update(s) in selected date range</span>
                ({{ $allProgressLogs->count() }} total all time)
            @else
                — {{ $allProgressLogs->count() }} update(s) total
            @endif
        </div>

        @if($allProgressLogs->count() > 0)
        <table class="history-table">
            <thead>
                <tr>
                    <th width="11%">Date &amp; Time</th>
                    <th width="11%">Updated By</th>
                    <th width="7%">Type</th>
                    <th width="7%">%</th>
                    <th width="64%">Update Reason / Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allProgressLogs as $log)
                @php
                    $inRange = true;
                    if (!empty($startDate) || !empty($endDate)) {
                        $logDate = $log->created_at->startOfDay();
                        if (!empty($startDate) && $logDate < \Carbon\Carbon::parse($startDate)->startOfDay()) $inRange = false;
                        if (!empty($endDate) && $logDate > \Carbon\Carbon::parse($endDate)->endOfDay()) $inRange = false;
                    }
                    $pillClass = $getPillClass((int)$log->percent);
                @endphp
                <tr class="{{ ($startDate || $endDate) && $inRange ? 'in-range' : '' }}">
                    <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $log->user?->name ?? 'System' }}</td>
                    <td>
                        <span class="{{ $log->type === 'maintenance' ? 'type-maintenance' : 'type-build' }}">
                            {{ strtoupper($log->type ?? 'BUILD') }}
                        </span>
                    </td>
                    <td>
                        <span class="percent-pill {{ $pillClass }}">{{ $log->percent }}%</span>
                    </td>
                    <td>{{ $log->note }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div style="padding:8px 10px; color:#94a3b8; font-style:italic; font-size:8.5px;">No update history recorded yet.</div>
        @endif

        {{-- Follow Ups Section --}}
        @if($ws->followUps->count() > 0)
        <div class="history-title" style="background:#fefce8; color:#78350f;">
            🔗 Follow Ups ({{ $ws->followUps->count() }})
        </div>
        <table class="followups-table">
            <thead>
                <tr>
                    <th width="11%">Date</th>
                    <th width="12%">Type</th>
                    <th width="22%">Page Title</th>
                    <th width="22%">Page URL</th>
                    <th width="11%">Assigned To</th>
                    <th width="10%">QC Status</th>
                    <th width="12%">Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ws->followUps as $fu)
                <tr>
                    <td>{{ $fu->created_at->format('d/m/Y') }}</td>
                    <td>{{ $fu->getTypeLabel() }}</td>
                    <td>{{ $fu->title ?? '—' }}</td>
                    <td style="word-break:break-all; font-size:7.5px;">{{ $fu->url ?? '—' }}</td>
                    <td>{{ $fu->assignee?->name ?? 'Unassigned' }}</td>
                    <td>{{ $fu->qc_status ?? '—' }}</td>
                    <td>{{ strip_tags($fu->note ?? '') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

    </div>{{-- /website-block --}}
    @empty
    <p style="text-align:center; color:#94a3b8; padding:30px;">No websites found for the selected criteria.</p>
    @endforelse

    <div class="footer">
        DGT System — All Websites Report &nbsp;|&nbsp; Generated {{ now()->format('d M Y, g:i a') }} &nbsp;|&nbsp; Total: {{ $websites->count() }} website(s)
    </div>

</body>
</html>
