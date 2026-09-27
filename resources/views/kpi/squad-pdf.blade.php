<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $squad->name }} - KPI Summary - {{ $period?->name }}</title>
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
            border-bottom: 2.5px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #1e3a8a;
            font-size: 20px;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }
        .header p { color: #64748b; margin: 0; font-size: 10px; }
        .meta-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 20px;
        }
        .meta-card table { width: 100%; }
        table.score-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.score-table th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 700;
            text-align: left;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            font-size: 10px;
            text-transform: uppercase;
        }
        table.score-table td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 9px;
            background: #dcfce7;
            color: #166534;
        }
        .signatures { margin-top: 50px; page-break-inside: avoid; }
        .signatures table { width: 100%; }
        .sig-line {
            border-top: 1px solid #94a3b8;
            margin-top: 45px;
            padding-top: 5px;
            text-align: center;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $squad->name }} • Monthly Staff KPI Summary</h1>
        <p>Digital System KiuQ.com • Departmental Evaluation Report</p>
    </div>

    <div class="meta-card">
        <table>
            <tr>
                <td><strong>Squad:</strong> {{ $squad->name }}</td>
                <td><strong>Lead:</strong> {{ $squad->lead?->name }}</td>
                <td><strong>Period:</strong> {{ $period?->name }}</td>
            </tr>
        </table>
    </div>

    <table class="score-table">
        <thead>
            <tr>
                <th>Staff Name</th>
                <th>Role Title</th>
                <th style="text-align: center;">Productivity</th>
                <th style="text-align: center;">Quality</th>
                <th style="text-align: center;">TAT Speed</th>
                <th style="text-align: center;">Teamwork</th>
                <th style="text-align: center;">Overall KPI</th>
                <th style="text-align: center;">Rank</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reviews as $rev)
            <tr>
                <td><strong>{{ $rev->user?->name }}</strong></td>
                <td>{{ $rev->user?->kpiSquads->firstWhere('id', $squad->id)?->pivot->role_title ?? 'Team Member' }}</td>
                <td style="text-align: center;">{{ $rev->productivity_score }}%</td>
                <td style="text-align: center;">{{ $rev->quality_score }}%</td>
                <td style="text-align: center;">{{ $rev->deadline_score }}%</td>
                <td style="text-align: center;">{{ $rev->teamwork_score }}%</td>
                <td style="text-align: center;"><strong>{{ number_format($rev->overall_kpi, 1) }}%</strong></td>
                <td style="text-align: center;"><span class="badge">{{ $rev->performance_band }}</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signatures">
        <table>
            <tr>
                <td style="width: 45%;">
                    <div class="sig-line">
                        <strong>{{ $squad->lead?->name }}</strong><br/>
                        Squad Lead ({{ $squad->name }})
                    </div>
                </td>
                <td style="width: 10%;"></td>
                <td style="width: 45%;">
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
