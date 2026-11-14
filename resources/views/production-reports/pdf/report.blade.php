<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 28px 32px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #0f172a;
            line-height: 1.45;
        }
        .header-banner {
            background-color: #0f172a;
            color: #ffffff;
            padding: 18px 22px;
            border-bottom: 4px solid #4f46e5;
            margin-bottom: 16px;
        }
        .header-kicker {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #93c5fd;
            font-weight: bold;
        }
        .header-title {
            font-size: 18px;
            font-weight: bold;
            margin: 4px 0;
            color: #ffffff;
        }
        .header-sub {
            font-size: 11px;
            color: #cbd5e1;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .meta-table td, .meta-table th {
            border: 1px solid #cbd5e1;
            padding: 7px 10px;
            text-align: left;
            font-size: 10.5px;
        }
        .meta-table th {
            background-color: #f1f5f9;
            color: #334155;
            width: 18%;
            font-weight: bold;
        }
        .section-box {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .section-title {
            background-color: #1e293b;
            color: #ffffff;
            padding: 6px 10px;
            font-size: 11.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .section-content {
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            padding: 10px 12px;
            white-space: pre-line;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.data-table th {
            background-color: #e2e8f0;
            color: #0f172a;
            font-weight: bold;
            font-size: 10px;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }
        table.data-table td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 10px;
            vertical-align: top;
        }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 9.5px;
            font-weight: bold;
            background-color: #e0e7ff;
            color: #3730a3;
        }
        .rating-box {
            background-color: #eef2ff;
            border: 2px solid #4f46e5;
            padding: 10px 14px;
            margin-bottom: 12px;
        }
        .poster-thumb {
            max-width: 130px;
            max-height: 95px;
            border: 1px solid #cbd5e1;
            margin-top: 4px;
        }
        .footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #cbd5e1;
            font-size: 9px;
            color: #64748b;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header-banner">
        <div class="header-kicker">KIUQ SYSTEM • PRODUCTION REPORT MANAGEMENT SYSTEM</div>
        <div class="header-title">{{ $report->title }}</div>
        <div class="header-sub">
            {{ strtoupper($report->report_type) }} REPORT &bull; {{ $report->team_name ?: ('Team ' . ($report->team_code ?: 'General')) }} &bull; Period: {{ $report->period_label }}
        </div>
    </div>

    <table class="meta-table">
        <tr>
            <th>Report Code</th>
            <td><strong>{{ $report->report_code }}</strong></td>
            <th>Prepared By</th>
            <td>{{ $report->user?->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Production Team</th>
            <td>{{ $report->team_name ?: ('Team ' . ($report->team_code ?: 'N/A')) }}</td>
            <th>Reporting Period</th>
            <td>{{ $report->period_label }}</td>
        </tr>
        <tr>
            <th>Status</th>
            <td>
                <span class="badge">{{ $report->status_badge_label }}</span>
                @if($report->is_late)
                    <strong style="color: #b45309; margin-left: 4px;">(Submitted After Deadline)</strong>
                @endif
            </td>
            <th>Submitted At</th>
            <td>{{ $report->submitted_at ? $report->submitted_at->timezone('Asia/Phnom_Penh')->format('M d, Y g:i A') . ' (Cambodia Time)' : 'Draft / Not Submitted' }}</td>
        </tr>
        <tr>
            <th>Deadline</th>
            <td>{{ $report->deadline_at ? $report->deadline_at->timezone('Asia/Phnom_Penh')->format('M d, Y g:i A') : 'N/A' }}</td>
            <th>Performance Rating</th>
            <td><strong>{{ $report->rating_display }}</strong></td>
        </tr>
    </table>

    @if($report->performance_rating)
    <div class="rating-box">
        <strong>Team Performance Rating: {{ $report->performance_rating }} / 5 — {{ $report->performance_rating_label }}</strong>
        @if(filled($report->head_comments))
            <div style="margin-top: 4px;"><strong>Head of Production Comments:</strong> {{ $report->head_comments }}</div>
        @endif
        @if(filled($report->supervisor_comments))
            <div style="margin-top: 4px;"><strong>Supervisor Comments:</strong> {{ $report->supervisor_comments }}</div>
        @endif
    </div>
    @endif

    @if(filled($report->executive_summary))
    <div class="section-box">
        <div class="section-title">1. Executive Summary</div>
        <div class="section-content">{{ $report->executive_summary }}</div>
    </div>
    @endif

    @if(!empty($summary['member_summary_table']))
    <div class="section-box">
        <div class="section-title">2. Individual Member Summary Table</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Daily Reports</th>
                    <th>Completed Tasks</th>
                    <th>Work in Progress</th>
                    <th>Pending Tasks</th>
                    <th>Key Achievements</th>
                </tr>
            </thead>
            <tbody>
                @foreach($summary['member_summary_table'] as $row)
                <tr>
                    <td><strong>{{ $row['member_name'] ?? 'Member' }}</strong><br><span style="color:#64748b;">{{ $row['role_title'] ?? '' }}</span></td>
                    <td>{{ $row['submitted_days'] ?? 0 }} / {{ $row['expected_days'] ?? 0 }} days</td>
                    <td><strong>{{ $row['completed_tasks_count'] ?? 0 }}</strong><br>{{ \Illuminate\Support\Str::limit($row['completed_tasks_summary'] ?? '', 140) }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($row['work_in_progress'] ?? '—', 100) }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($row['pending_tasks'] ?? '—', 100) }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($row['key_achievements'] ?? '—', 110) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(!empty($summary['missing_reports']) || !empty($summary['missing_dates']))
    <div class="section-box">
        <div class="section-title">Missing Daily Reports in Period</div>
        <div class="section-content">
            @if(!empty($summary['missing_reports']))
                @foreach($summary['missing_reports'] as $mr)
                    &bull; {{ $mr['member_name'] ?? 'Member' }} — {{ $mr['date_formatted'] ?? $mr['date'] }} ({{ $mr['state'] ?? 'Missing' }})<br>
                @endforeach
            @elseif(!empty($summary['missing_dates']))
                Missing Dates: {{ implode(', ', $summary['missing_dates']) }}
            @endif
        </div>
    </div>
    @endif

    <div class="section-box">
        <div class="section-title">Tasks Completed &amp; Production Output</div>
        <div class="section-content">{{ $report->tasks_completed ?: 'None recorded.' }}</div>
        @if($report->items->count() > 0)
        <table class="data-table" style="margin-top: 8px;">
            <thead>
                <tr>
                    <th>Task Title</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Completion Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report->items as $item)
                <tr>
                    <td><strong>{{ $item->task_title }}</strong>@if($item->task_description)<br><span style="color:#475569;">{{ $item->task_description }}</span>@endif</td>
                    <td>{{ $item->task_category ?: 'General' }}</td>
                    <td>{{ $item->quantity }} {{ $item->unit }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $item->status)) }}</td>
                    <td>{{ $item->completion_date ? $item->completion_date->format('M d, Y') : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <div class="section-box">
        <div class="section-title">Work in Progress &amp; Pending Tasks</div>
        <div class="section-content"><strong>Work in Progress:</strong><br>{{ $report->work_in_progress ?: 'None reported.' }}<br><br><strong>Pending Tasks:</strong><br>{{ $report->pending_tasks ?: 'None reported.' }}</div>
    </div>

    <div class="section-box">
        <div class="section-title">Challenges, Blockers &amp; Key Achievements</div>
        <div class="section-content"><strong>Blockers / Challenges:</strong><br>{{ $report->blockers ?: 'No blockers reported.' }}<br><br><strong>Important Achievements:</strong><br>{{ $report->achievements ?: 'None reported.' }}</div>
    </div>

    @if(filled($report->next_priorities) || filled($report->notes))
    <div class="section-box">
        <div class="section-title">Next Period Priorities &amp; Notes</div>
        <div class="section-content">
            @if(filled($report->next_priorities))<strong>Next Priorities / Goals:</strong><br>{{ $report->next_priorities }}<br><br>@endif
            @if(filled($report->notes))<strong>Additional Notes:</strong><br>{{ $report->notes }}@endif
        </div>
    </div>
    @endif

    @if($report->media->count() > 0)
    <div class="section-box">
        <div class="section-title">Posters, Graphics, Videos &amp; Media Deliverables ({{ $report->media->count() }})</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Preview / Type</th>
                    <th>Title &amp; Description</th>
                    <th>Creator</th>
                    <th>Category / Platform</th>
                    <th>Production Date</th>
                    <th>File / External Link</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report->media as $media)
                <tr>
                    <td>
                        <strong>{{ strtoupper($media->media_type) }}</strong>
                        @if($media->is_highlighted)
                            <br><span class="badge">★ Highlight</span>
                        @endif
                        @if(isset($posterDataUris[$media->id]))
                            <br><img src="{{ $posterDataUris[$media->id] }}" class="poster-thumb" alt="Poster">
                        @endif
                    </td>
                    <td><strong>{{ $media->title }}</strong>@if($media->description)<br>{{ $media->description }}@endif</td>
                    <td>{{ $media->creator?->name ?? $report->user?->name ?? 'N/A' }}</td>
                    <td>{{ $media->project_category ?: 'General' }} / {{ $media->platform ?: 'Local' }}</td>
                    <td>{{ $media->production_date ? $media->production_date->format('M d, Y') : '—' }}</td>
                    <td>{{ $media->external_url ?: ($media->original_filename ?: 'Attached File') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        Generated by KIUQ SYSTEM Production Report Engine &bull; {{ now('Asia/Phnom_Penh')->format('M d, Y g:i A') }} (Asia/Phnom_Penh)
    </div>
</body>
</html>

