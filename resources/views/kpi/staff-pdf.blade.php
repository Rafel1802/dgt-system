<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Staff Monthly KPI Report - {{ $review->user?->name }} - {{ $review->period?->name }}</title>
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
            letter-spacing: 0.5px;
        }
        .header p {
            color: #64748b;
            margin: 0;
            font-size: 10px;
        }
        .meta-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        .meta-card table {
            width: 100%;
        }
        .meta-card td {
            font-size: 11px;
            padding: 3px 0;
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
        .total-row {
            background: #eff6ff;
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 10px;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .notes-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 14px;
            font-size: 11px;
        }
        .signatures {
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .signatures table {
            width: 100%;
        }
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
        <h1>Official Staff Monthly KPI Report</h1>
        <p>Digital System KiuQ.com • Digital Media Department</p>
    </div>

    <div class="meta-card">
        <table>
            <tr>
                <td style="width: 50%;"><strong>Staff Member:</strong> {{ $review->user?->name }} (@ {{ $review->user?->username }})</td>
                <td style="width: 50%;"><strong>Evaluation Month:</strong> {{ $review->period?->name }}</td>
            </tr>
            <tr>
                <td><strong>Squad:</strong> {{ $review->squad?->name ?? 'Digital Media Squad' }}</td>
                <td><strong>Evaluated By:</strong> {{ $review->reviewer?->name }} (Squad Lead)</td>
            </tr>
            <tr>
                <td><strong>Role Title:</strong> {{ $review->user?->kpiSquads->firstWhere('id', $review->squad_id)?->pivot->role_title ?? 'Team Member' }}</td>
                <td><strong>Certified Date:</strong> {{ date('d M Y') }}</td>
            </tr>
        </table>
    </div>

    <h2>1. Four-Pillar Numerical Performance Evaluation</h2>
    <table class="score-table">
        <thead>
            <tr>
                <th>Evaluation Pillar</th>
                <th>Description</th>
                <th style="text-align: center;">Weight</th>
                <th style="text-align: center;">Numeric Score</th>
                <th style="text-align: center;">Weighted Score</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Productivity & Output</strong></td>
                <td>Deliverables volume, rendering completion, and quota fulfillment</td>
                <td style="text-align: center;">35%</td>
                <td style="text-align: center;">{{ $review->productivity_score }}%</td>
                <td style="text-align: center;">{{ number_format($review->productivity_score * 0.35, 2) }}%</td>
            </tr>
            <tr>
                <td><strong>Quality & Accuracy</strong></td>
                <td>Brand consistency, first-time QC pass rate, and zero defect standard</td>
                <td style="text-align: center;">35%</td>
                <td style="text-align: center;">{{ $review->quality_score }}%</td>
                <td style="text-align: center;">{{ number_format($review->quality_score * 0.35, 2) }}%</td>
            </tr>
            <tr>
                <td><strong>Delivery Speed & Turnaround</strong></td>
                <td>Turnaround time (TAT hours), deadline adherence, and responsiveness</td>
                <td style="text-align: center;">20%</td>
                <td style="text-align: center;">{{ $review->deadline_score }}%</td>
                <td style="text-align: center;">{{ number_format($review->deadline_score * 0.20, 2) }}%</td>
            </tr>
            <tr>
                <td><strong>Teamwork & Initiative</strong></td>
                <td>Collaboration, positive attitude, communication, and squad support</td>
                <td style="text-align: center;">10%</td>
                <td style="text-align: center;">{{ $review->teamwork_score }}%</td>
                <td style="text-align: center;">{{ number_format($review->teamwork_score * 0.10, 2) }}%</td>
            </tr>
            <tr class="total-row">
                <td colspan="2"><strong>TOTAL MONTHLY KPI SCORE</strong></td>
                <td style="text-align: center;"><strong>100%</strong></td>
                <td style="text-align: center; color: #1e40af; font-size: 13px;"><strong>{{ number_format($review->overall_kpi, 1) }}%</strong></td>
                <td style="text-align: center;">
                    <span class="badge {{ $review->overall_kpi >= 95 ? 'badge-amber' : 'badge-success' }}">
                        {{ $review->performance_band }}
                    </span>
                </td>
            </tr>
        </tbody>
    </table>

    <h2>2. Team Lead Evaluation Notes</h2>
    <div class="notes-box">
        <strong>Review Notes by {{ $review->reviewer?->name }}:</strong><br/>
        <p style="margin: 4px 0 0 0;">{{ $review->manager_notes }}</p>
    </div>

    @if($review->supervisor_notes)
    <h2>3. Supervisor Review & Commendations</h2>
    <div class="notes-box" style="background: #eff6ff; border-color: #bfdbfe;">
        <strong style="color: #1e3a8a;">Ms. Somalika (Department Supervisor):</strong><br/>
        <p style="margin: 4px 0 0 0;">{{ $review->supervisor_notes }}</p>
    </div>
    @endif

    <div class="signatures">
        <table>
            <tr>
                <td style="width: 30%;">
                    <div class="sig-line">
                        <strong>{{ $review->user?->name }}</strong><br/>
                        Staff Member Signature
                    </div>
                </td>
                <td style="width: 5%;"></td>
                <td style="width: 30%;">
                    <div class="sig-line">
                        <strong>{{ $review->reviewer?->name }}</strong><br/>
                        Squad Lead (Dara / Kim)
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
