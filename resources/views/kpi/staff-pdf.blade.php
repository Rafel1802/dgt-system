<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Employee Performance Evaluation - {{ $review->user?->name }}</title>
    <style>
        @page {
            margin: 28px 36px 32px 36px;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .title-main {
            font-size: 15px;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .title-sub {
            font-size: 9px;
            color: #64748b;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            border: 1px solid #cbd5e1;
        }
        .info-table td {
            padding: 5px 8px;
            border: 1px solid #cbd5e1;
            font-size: 9.5px;
        }
        .info-label {
            background-color: #ffffff;
            font-weight: bold;
            color: #0f172a;
            width: 16%;
        }
        .info-val {
            width: 34%;
            color: #1e293b;
        }
        .section-header {
            font-size: 10.5px;
            font-weight: 800;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 10px;
            margin-bottom: 5px;
            letter-spacing: 0.3px;
        }
        .score-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border-top: 1.5px solid #1e3a8a;
            border-bottom: 1.5px solid #1e3a8a;
        }
        .score-table th {
            padding: 5px 8px;
            font-size: 9.5px;
            font-weight: 800;
            color: #1e3a8a;
            border-bottom: 1px solid #cbd5e1;
            text-align: left;
        }
        .score-table th.center {
            text-align: center;
        }
        .score-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
            border-top: 1px solid #e2e8f0;
            font-size: 9.5px;
            color: #334155;
        }
        .score-table td.center {
            text-align: center;
            font-weight: bold;
        }
        .total-row td {
            border-top: 1.5px solid #cbd5e1;
            border-bottom: none;
            padding: 6px 8px;
            font-weight: 800;
        }
        .outcome-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border: 1px solid #cbd5e1;
            border-top: 1.5px solid #1e3a8a;
        }
        .outcome-table td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 9.5px;
            vertical-align: top;
        }
        .outcome-label {
            font-weight: bold;
            color: #0f172a;
            width: 22%;
        }
        .note-text {
            font-size: 9px;
            color: #1e293b;
            margin-top: 8px;
            margin-bottom: 35px;
            line-height: 1.4;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        .sig-block {
            text-align: left;
            vertical-align: bottom;
        }
        .sig-title {
            font-size: 8.5px;
            color: #475569;
            margin-bottom: 35px;
        }
        .sig-name {
            font-size: 10px;
            font-weight: bold;
            color: #000000;
        }
        .sig-role {
            font-size: 8.5px;
            color: #475569;
            margin-top: 1px;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 55px;
            font-size: 8.5px;
            color: #64748b;
        }
    </style>
</head>
<body>
    @php
        $logoBase64 = null;
        if (file_exists(public_path('images/kiuqlogo.png'))) {
            $logoBase64 = base64_encode(file_get_contents(public_path('images/kiuqlogo.png')));
        }
        $roleTitle = $review->user?->kpiSquads->firstWhere('id', $review->squad_id)?->pivot->role_title ?? 'Content Creator / Editor';
        $evalDate = $review->evaluation_date ? \Carbon\Carbon::parse($review->evaluation_date)->format('Y-m-d') : date('Y-m-d');
        $joinedDate = $review->user?->kpiSquads->firstWhere('id', $review->squad_id)?->pivot->joined_date ? \Carbon\Carbon::parse($review->user->kpiSquads->firstWhere('id', $review->squad_id)->pivot->joined_date)->format('Y-m-d') : '2026-01-01';
        $leadName = ($review->squad?->lead?->username === 'kim' || $review->squad_id == 2) ? 'Mr. KimOun (Lead)' : 'Mr. Dara (Lead)';
        $leadSigName = ($review->squad?->lead?->username === 'kim' || $review->squad_id == 2) ? 'Mr. KimOun' : 'Mr. Dara Vuthy';
        $leadSigRole = ($review->squad?->lead?->username === 'kim' || $review->squad_id == 2) ? 'Human Resource / Squad 2 Lead' : 'Human Resource Department';
    @endphp

    {{-- Top Header --}}
    <table class="header-table">
        <tr>
            <td style="width: 35%;">
                @if($logoBase64)
                    <img src="data:image/png;base64,{{ $logoBase64 }}" style="height: 38px;" alt="kiuQ">
                @else
                    <span style="font-size: 24px; font-weight: 900; color: #00a8cc;">kiu<span style="color: #1e3a8a;">Q</span></span>
                @endif
            </td>
            <td style="width: 65%; text-align: right;">
                <div class="title-main">EMPLOYEE PERFORMANCE EVALUATION</div>
                <div class="title-sub">
                    Review Cycle: {{ $review->period?->name ?? 'Monthly KPI Cycle' }} | Ref: PRF-{{ str_pad($review->id ?? 1, 4, '0', STR_PAD_LEFT) }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Employee Details --}}
    <table class="info-table">
        <tr>
            <td class="info-label">Employee Name:</td>
            <td class="info-val"><strong>{{ $review->user?->name }}</strong></td>
            <td class="info-label">Employee ID:</td>
            <td class="info-val">EMP{{ str_pad($review->user?->id, 3, '0', STR_PAD_LEFT) }}</td>
        </tr>
        <tr>
            <td class="info-label">Department / Role:</td>
            <td class="info-val">Digital Media — {{ $roleTitle }}</td>
            <td class="info-label">Evaluation Date:</td>
            <td class="info-val">{{ $evalDate }}</td>
        </tr>
        <tr>
            <td class="info-label">Reviewer / Lead:</td>
            <td class="info-val">{{ $leadName }}</td>
            <td class="info-label">Date Joined:</td>
            <td class="info-val">{{ $joinedDate }}</td>
        </tr>
    </table>

    {{-- 1. Core Performance Criteria --}}
    <div class="section-header">1. CORE PERFORMANCE CRITERIA (1–5 STARS)</div>
    <table class="score-table">
        <thead>
            <tr>
                <th style="width: 44%;">Evaluation Category</th>
                <th class="center" style="width: 18%;">Score (1–5)</th>
                <th style="width: 38%;">Standard Benchmark</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1. Work Quality &amp; Accuracy</td>
                <td class="center">{{ number_format($review->quality_score / 20, 1) }}</td>
                <td>Output standard &amp; detail accuracy</td>
            </tr>
            <tr>
                <td>2. Productivity &amp; Timeliness</td>
                <td class="center">{{ number_format($review->productivity_score / 20, 1) }}</td>
                <td>Task speed &amp; meeting deadlines</td>
            </tr>
            <tr>
                <td>3. Communication &amp; Teamwork</td>
                <td class="center">{{ number_format($review->teamwork_score / 20, 1) }}</td>
                <td>Collaboration, responsiveness &amp; attitude</td>
            </tr>
            <tr>
                <td>4. Initiative &amp; Problem Solving</td>
                <td class="center">{{ number_format((($review->productivity_score + $review->quality_score) / 2) / 20, 1) }}</td>
                <td>Proactivity &amp; handling challenges</td>
            </tr>
            <tr>
                <td>5. Discipline &amp; Responsibility</td>
                <td class="center">{{ number_format($review->deadline_score / 20, 1) }}</td>
                <td>Attendance, consistency &amp; ownership</td>
            </tr>
            <tr class="total-row">
                <td>FINAL TOTAL SCORE:</td>
                <td class="center" style="color: #1e3a8a; font-size: 11px;">
                    {{ number_format(($review->overall_kpi / 100) * 25, 1) }} / 25
                </td>
                <td style="color: #15803d; font-weight: 800;">
                    OVERALL: {{ $review->performance_band ?? 'Exceeds Expectations' }}
                </td>
            </tr>
        </tbody>
    </table>

    {{-- 2. Performance Outcome & Decision --}}
    <div class="section-header">2. PROBATION OUTCOME &amp; DECISION</div>
    <table class="outcome-table">
        <tr>
            <td class="outcome-label">Probation Decision:</td>
            <td style="color: #15803d; font-weight: 800;">
                {{ $review->status === 'Finalized' || $review->status === 'Approved' ? 'Passed' : 'In Review' }}
            </td>
        </tr>
        <tr>
            <td class="outcome-label">Supervisor Remarks:</td>
            <td>
                {{ $review->manager_notes ?: 'Has performed well during the monthly evaluation cycle, handling assignments, video deliverables, and graphic tasks with strong consistency and attention to brand standards. Recommendation: Confirm full performance status.' }}
                @if($review->supervisor_notes)
                    <br/><br/>
                    <strong>Supervisor Comments:</strong> {{ $review->supervisor_notes }}
                @endif
            </td>
        </tr>
    </table>

    {{-- Note --}}
    <div class="note-text">
        <strong>Note:</strong><br/>
        This structured scoring ensures transparency, consistency, and clear justification for probation confirmation decisions.
    </div>

    {{-- Signatures (Mr. Dennis Tan, Mr. Dara Vuthy / Kim, Ms. Somalika In) --}}
    <table class="signatures-table">
        <tr>
            <td class="sig-block" style="width: 32%;">
                <div class="sig-title">Approved by:</div>
                <div class="sig-name">Mr. Dennis Tan</div>
                <div class="sig-role">Chief Executive Officer</div>
            </td>
            <td class="sig-block" style="width: 36%; text-align: center;">
                <div class="sig-title">Seen by:</div>
                <div class="sig-name">{{ $leadSigName }}</div>
                <div class="sig-role">{{ $leadSigRole }}</div>
            </td>
            <td class="sig-block" style="width: 32%; text-align: right;">
                <div class="sig-title">Prepared by:</div>
                <div class="sig-name">Ms. Somalika In</div>
                <div class="sig-role">Head of Digital Media</div>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <table class="footer-table">
        <tr>
            <td style="text-align: left;">
                {{ $review->evaluation_date ? \Carbon\Carbon::parse($review->evaluation_date)->format('d/F/Y') : date('d/F/Y') }}
            </td>
            <td style="text-align: right;">
                Copyright &copy;KiuQ.Com {{ date('Y') }} All Rights Reserved<br/>
                Digital Media Department
            </td>
        </tr>
    </table>
</body>
</html>
