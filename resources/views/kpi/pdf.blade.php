<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Digital Media KPI Summary Report - {{ $currentPeriod?->name }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.5;
            margin: 0;
            padding: 24px;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #1e3a8a;
            font-size: 20px;
            margin: 0 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header p {
            color: #64748b;
            margin: 0;
            font-size: 10px;
        }
        .meta-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 20px;
        }
        .meta-box table {
            width: 100%;
        }
        .meta-box td {
            font-size: 11px;
        }
        h2 {
            font-size: 13px;
            color: #1e3a8a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 20px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        table.data-table th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 10px;
            text-transform: uppercase;
        }
        table.data-table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            font-size: 10px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 9px;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .signatures {
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .signatures table {
            width: 100%;
        }
        .sig-line {
            border-top: 1px solid #94a3b8;
            margin-top: 50px;
            padding-top: 5px;
            text-align: center;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Digital Media KPI Performance Summary</h1>
        <p>Digital System KiuQ.com • Departmental Official Monthly Evaluation</p>
    </div>

    <div class="meta-box">
        <table>
            <tr>
                <td><strong>Evaluation Period:</strong> {{ $currentPeriod?->name ?? 'September 2026' }}</td>
                <td><strong>Generated At:</strong> {{ date('d M Y, H:i') }}</td>
                <td><strong>Status:</strong> Approved & Certified</td>
            </tr>
        </table>
    </div>

    <h2>1. Squad Monthly Evaluation Scorecards</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Squad</th>
                <th>Lead</th>
                <th>Target</th>
                <th>Completed</th>
                <th>Productivity</th>
                <th>Quality</th>
                <th>Overall KPI</th>
                <th>Rank</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reviews as $rev)
            <tr>
                <td><strong>{{ $rev->assignment?->squad?->name }}</strong></td>
                <td>{{ $rev->user?->name }}</td>
                <td>{{ $rev->assignment?->target_deliverables }}</td>
                <td>{{ $rev->assignment?->target_deliverables }}</td>
                <td>{{ $rev->productivity_score }}%</td>
                <td>{{ $rev->quality_score }}%</td>
                <td><strong>{{ number_format($rev->overall_kpi, 1) }}%</strong></td>
                <td><span class="badge badge-success">{{ $rev->performance_band }}</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>2. Key Deliverables Sample</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Task Title</th>
                <th>Squad</th>
                <th>Assignee</th>
                <th>Priority</th>
                <th>Status</th>
                <th>QC Score</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tasks->take(12) as $t)
            <tr>
                <td>#{{ $t->id }}</td>
                <td>{{ $t->title }}</td>
                <td>{{ $t->squad?->code }}</td>
                <td>{{ $t->assignee?->name }}</td>
                <td>{{ $t->priority }}</td>
                <td>{{ $t->status }}</td>
                <td><strong>{{ $t->quality_score ? $t->quality_score . '%' : 'N/A' }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>3. Squad Summaries & Comments</h2>
    @foreach($reports as $rep)
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; margin-bottom: 10px;">
        <strong style="color: #1e3a8a;">{{ $rep->squad?->name }} Summary:</strong>
        <p style="margin: 4px 0 0 0; font-size: 10px;">{{ $rep->summary }}</p>
    </div>
    @endforeach

    <div class="signatures">
        <table>
            <tr>
                <td style="width: 30%;">
                    <div class="sig-line">
                        <strong>Mr. Dara</strong><br/>
                        QC & Video Squad Lead
                    </div>
                </td>
                <td style="width: 5%;"></td>
                <td style="width: 30%;">
                    <div class="sig-line">
                        <strong>Mr. KimOun</strong><br/>
                        Graphic Design Squad Lead
                    </div>
                </td>
                <td style="width: 5%;"></td>
                <td style="width: 30%;">
                    <div class="sig-line">
                        <strong>Ms. Somalika</strong><br/>
                        Department Supervisor
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
