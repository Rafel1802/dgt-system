<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 26px 30px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #0f172a;
            line-height: 1.45;
        }
        .header-banner {
            background-color: #0f172a;
            color: #ffffff;
            padding: 16px 20px;
            border-bottom: 4px solid #4f46e5;
            margin-bottom: 14px;
        }
        .header-kicker {
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #93c5fd;
            font-weight: bold;
        }
        .header-title {
            font-size: 16px;
            font-weight: bold;
            margin: 4px 0;
            color: #ffffff;
        }
        .header-sub {
            font-size: 10px;
            color: #cbd5e1;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .meta-table td, .meta-table th {
            border: 1px solid #cbd5e1;
            padding: 6px 9px;
            text-align: left;
            font-size: 10px;
        }
        .meta-table th {
            background-color: #f1f5f9;
            color: #334155;
            width: 20%;
            font-weight: bold;
        }
        .summary-card {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-left: 4px solid #4f46e5;
            padding: 8px 12px;
            margin-bottom: 14px;
            font-size: 10px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        table.data-table th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: bold;
            font-size: 9.5px;
            padding: 6px 8px;
            border: 1px solid #334155;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table.data-table td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 9.5px;
            vertical-align: top;
        }
        .report-card {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            margin-bottom: 14px;
            page-break-inside: avoid;
            background-color: #ffffff;
        }
        .report-card-header {
            background-color: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            padding: 7px 10px;
            font-weight: bold;
        }
        .report-card-body {
            padding: 9px 11px;
        }
        .field-label {
            font-weight: bold;
            color: #334155;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 6px;
            margin-bottom: 2px;
        }
        .field-content {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            font-size: 9.5px;
            white-space: pre-line;
            border-radius: 3px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            background-color: #e0e7ff;
            color: #3730a3;
        }
        .badge-late {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #cbd5e1;
            font-size: 8.5px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header-banner">
        <div class="header-kicker">KIUQ DIGITAL MEDIA &bull; PRODUCTION MANAGEMENT</div>
        <div class="header-title">{{ $title }}</div>
        @if($subtitle)
            <div class="header-sub">{{ $subtitle }}</div>
        @endif
    </div>

    <table class="meta-table">
        <tr>
            <th>Date Range</th>
            <td>{{ $meta['period_range'] ?? 'All Records' }}</td>
            <th>Total Reports</th>
            <td><strong>{{ $reports->count() }}</strong> reports compiled</td>
        </tr>
        <tr>
            <th>Author(s) Included</th>
            <td>{{ !empty($meta['authors']) ? implode(', ', $meta['authors']) : 'All Filtered Members' }}</td>
            <th>Team(s)</th>
            <td>{{ !empty($meta['teams']) ? 'Team ' . implode(', Team ', $meta['teams']) : 'All Teams' }}</td>
        </tr>
        <tr>
            <th>Generated On</th>
            <td>{{ now('Asia/Phnom_Penh')->format('F d, Y \a\t g:i A') }} (Cambodia Time)</td>
            <th>Total Tasks Recorded</th>
            <td><strong>{{ $meta['total_tasks'] ?? 0 }}</strong> completed item(s)</td>
        </tr>
    </table>

    <h3 style="font-size: 11px; margin-bottom: 6px; text-transform: uppercase; color: #1e293b;">
        Summary Table ({{ $reports->count() }} Reports)
    </h3>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 18%;">Date / Period</th>
                <th style="width: 17%;">Author</th>
                <th style="width: 10%;">Team</th>
                <th style="width: 12%;">Type</th>
                <th style="width: 14%;">Status</th>
                <th style="width: 10%;">Rating</th>
                <th style="width: 14%;">Code</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reports as $idx => $r)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td><strong>{{ $r->period_label }}</strong></td>
                    <td>{{ $r->user?->name ?? 'N/A' }}</td>
                    <td>Team {{ $r->team_code ?: '—' }}</td>
                    <td style="text-transform: uppercase;">{{ $r->report_type }}</td>
                    <td>
                        <span class="badge {{ $r->is_late ? 'badge-late' : '' }}">
                            {{ $r->status_badge_label }}
                        </span>
                    </td>
                    <td>{{ $r->performance_rating ? $r->performance_rating . '/5' : '—' }}</td>
                    <td style="font-family: monospace; font-size: 8.5px;">{{ $r->report_code }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; color: #64748b; padding: 12px;">No production reports found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h3 style="font-size: 11px; margin-top: 18px; margin-bottom: 8px; text-transform: uppercase; color: #1e293b;">
        Detailed Report Records
    </h3>

    @foreach($reports as $idx => $r)
        <div class="report-card">
            <div class="report-card-header">
                <table style="width: 100%; border: none;">
                    <tr>
                        <td style="border: none; padding: 0;">
                            <span style="color: #4f46e5;">#{{ $idx + 1 }}</span>
                            &bull; <strong>{{ $r->title }}</strong>
                            ({{ $r->period_label }})
                        </td>
                        <td style="border: none; padding: 0; text-align: right;">
                            <span class="badge {{ $r->is_late ? 'badge-late' : '' }}">{{ $r->status_badge_label }}</span>
                            @if($r->performance_rating)
                                <span class="badge" style="background-color: #fef3c7; color: #92400e;">★ {{ $r->performance_rating }}/5</span>
                            @endif
                        </td>
                    </tr>
                </table>
                <div style="font-size: 9px; color: #64748b; font-weight: normal; margin-top: 3px;">
                    Author: <strong>{{ $r->user?->name ?? 'N/A' }}</strong> &bull; Team {{ $r->team_code ?: 'N/A' }} &bull; Code: <code>{{ $r->report_code }}</code>
                    @if($r->submitted_at)
                        &bull; Submitted: {{ $r->submitted_at->timezone('Asia/Phnom_Penh')->format('M d, Y g:i A') }}
                    @endif
                </div>
            </div>
            <div class="report-card-body">
                @if(filled($r->executive_summary))
                    <div class="field-label">Executive Summary</div>
                    <div class="field-content">{{ trim($r->executive_summary) }}</div>
                @endif

                @if(filled($r->tasks_completed))
                    <div class="field-label">Completed Tasks</div>
                    <div class="field-content">{{ trim($r->tasks_completed) }}</div>
                @endif

                @if(filled($r->work_in_progress))
                    <div class="field-label">Work in Progress</div>
                    <div class="field-content">{{ trim($r->work_in_progress) }}</div>
                @endif

                @if(filled($r->pending_tasks))
                    <div class="field-label">Pending / Upcoming Tasks</div>
                    <div class="field-content">{{ trim($r->pending_tasks) }}</div>
                @endif

                @if(filled($r->blockers) && !str_starts_with(trim($r->blockers), 'No blockers'))
                    <div class="field-label" style="color: #991b1b;">Challenges &amp; Blockers</div>
                    <div class="field-content" style="background-color: #fef2f2; border-color: #fecaca;">{{ trim($r->blockers) }}</div>
                @endif

                @if(filled($r->achievements) && trim($r->achievements) !== 'None reported.')
                    <div class="field-label" style="color: #065f46;">Key Achievements</div>
                    <div class="field-content" style="background-color: #f0fdf4; border-color: #bbf7d0;">{{ trim($r->achievements) }}</div>
                @endif

                @if(is_array($r->deliverable_links) && !empty($r->deliverable_links))
                    <div class="field-label">Deliverable Links</div>
                    <div class="field-content">
                        @foreach($r->deliverable_links as $link)
                            @php
                                $u = is_array($link) ? ($link['url'] ?? '') : (string) $link;
                                $t = is_array($link) ? ($link['title'] ?? $u) : $u;
                            @endphp
                            @if($u)
                                <div>&bull; <strong>{{ $t }}</strong>: {{ $u }}</div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    <div class="footer">
        KIUQ Production Management System &bull; Confidential &bull; Generated for Authorized Production Leadership &bull; Page printed on {{ now('Asia/Phnom_Penh')->format('Y-m-d H:i') }}
    </div>
</body>
</html>

