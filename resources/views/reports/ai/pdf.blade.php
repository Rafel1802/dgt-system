<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->title }} - KIUQ AI Executive Report</title>
    <style>
        @page {
            margin: 25mm 20mm 25mm 20mm;
            size: A4 portrait;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            line-height: 1.5;
            font-size: 11pt;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #6366f1;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .brand {
            font-size: 22pt;
            font-weight: 900;
            color: #4338ca;
            letter-spacing: -0.5px;
        }
        .brand-sub {
            font-size: 9pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
        }
        .report-title {
            font-size: 16pt;
            font-weight: 800;
            color: #0f172a;
            margin-top: 15px;
            margin-bottom: 5px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 25px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 10px 15px;
        }
        .meta-table td {
            font-size: 9pt;
            color: #475569;
            padding: 3px 0;
        }
        .meta-label {
            font-weight: 700;
            color: #1e293b;
            width: 120px;
        }
        .stats-grid {
            width: 100%;
            margin-bottom: 25px;
            border-collapse: collapse;
        }
        .stat-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        .stat-val {
            font-size: 20pt;
            font-weight: 900;
            color: #4338ca;
            margin-bottom: 2px;
        }
        .stat-lbl {
            font-size: 8.5pt;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .section-heading {
            font-size: 12pt;
            font-weight: 800;
            color: #1e293b;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 5px;
            margin-top: 25px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .content-box {
            font-size: 10pt;
            color: #334155;
            background: #ffffff;
            line-height: 1.6;
        }
        .content-box p {
            margin-bottom: 10px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .data-table th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 800;
            font-size: 9pt;
            text-align: left;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td {
            font-size: 9.5pt;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }
        .data-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        .footer {
            position: fixed;
            bottom: -15mm;
            left: 0;
            right: 0;
            height: 10mm;
            border-top: 1px solid #e2e8f0;
            font-size: 8pt;
            color: #94a3b8;
            padding-top: 5px;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="brand">KIUQ AI</div>
                    <div class="brand-sub">Enterprise Intelligence System &bull; DGT</div>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <div style="font-size: 9pt; color: #64748b;">Report ID: #REP-{{ str_pad($report->id, 5, '0', STR_PAD_LEFT) }}</div>
                    <div style="font-size: 9pt; color: #64748b;">Generated: {{ $report->created_at->format('M d, Y H:i') }}</div>
                </td>
            </tr>
        </table>
        <div class="report-title">{{ $report->title }}</div>
    </div>

    {{-- Meta Parameters Table --}}
    <table class="meta-table">
        <tr>
            <td class="meta-label">Category:</td>
            <td>{{ ucwords(str_replace('_', ' ', $report->category)) }}</td>
            <td class="meta-label">Prepared By:</td>
            <td>{{ $report->user->name ?? 'System' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Reporting Period:</td>
            <td>{{ $report->date_from->format('F d, Y') }} &mdash; {{ $report->date_to->format('F d, Y') }}</td>
            <td class="meta-label">Status:</td>
            <td><strong style="color: #059669;">Verified DB Records</strong></td>
        </tr>
    </table>

    {{-- Key Verified Statistics --}}
    <div class="section-heading">Verified Production Metrics</div>
    <table style="width: 100%; border-spacing: 8px; margin-left: -8px; margin-right: -8px;">
        <tr>
            <td style="width: 25%;">
                <div class="stat-card">
                    <div class="stat-val">{{ $report->stats_data['blogs_published'] ?? 0 }}</div>
                    <div class="stat-lbl">Blogs Published</div>
                </div>
            </td>
            <td style="width: 25%;">
                <div class="stat-card">
                    <div class="stat-val">{{ $report->stats_data['cards_completed'] ?? 0 }}</div>
                    <div class="stat-lbl">Tasks Completed</div>
                </div>
            </td>
            <td style="width: 25%;">
                <div class="stat-card">
                    <div class="stat-val">{{ $report->stats_data['qc_checks_passed'] ?? 0 }}</div>
                    <div class="stat-lbl">QC Checks Passed</div>
                </div>
            </td>
            <td style="width: 25%;">
                <div class="stat-card">
                    <div class="stat-val">{{ $report->stats_data['active_websites'] ?? 0 }}</div>
                    <div class="stat-lbl">Active Websites</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Detailed Category Metrics Table --}}
    @if(!empty($report->stats_data['details']))
    <div class="section-heading">Detailed Breakdown</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Area / Component</th>
                <th style="text-align: right;">Volume / Total</th>
                <th>Status / Key Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report->stats_data['details'] as $item)
            <tr>
                <td><strong>{{ $item['label'] ?? '' }}</strong></td>
                <td style="text-align: right; font-weight: bold; color: #4338ca;">{{ $item['count'] ?? 0 }}</td>
                <td>{{ $item['note'] ?? 'Active' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Executive Analysis & Strategic Insights --}}
    <div class="section-heading">Executive Analysis & Strategic Insights</div>
    <div class="content-box">
        {!! nl2br(e($report->analysis_text)) !!}
    </div>

    <div class="footer">
        <span>KIUQ AI &bull; Confidential Internal Executive Report</span>
        <span style="float: right;">Page 1 of 1</span>
    </div>
</body>
</html>
