<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Website Rankings & Leaderboard Report - {{ $scopeTitle }}</title>
    <style>
        @page {
            margin: 26px 26px 34px 26px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            background: #ffffff;
            font-size: 10.5px;
            line-height: 1.45;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #f59e0b;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .header-title {
            font-size: 19px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 4px 0;
        }
        .header-subtitle {
            font-size: 10.5px;
            color: #64748b;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 12px;
        }
        .meta-label {
            font-size: 9.5px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
        }
        .meta-val {
            font-size: 10.5px;
            font-weight: 600;
            color: #0f172a;
        }
        /* Top Performers Podium Grid */
        .podium-table {
            width: 100%;
            border-spacing: 6px;
            margin-bottom: 16px;
        }
        .podium-card {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 6px;
            padding: 10px 8px;
            text-align: center;
            width: 25%;
            vertical-align: top;
        }
        .podium-category {
            font-size: 9px;
            font-weight: bold;
            color: #b45309;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .podium-name {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .podium-metric {
            font-size: 16px;
            font-weight: bold;
            color: #d97706;
            margin: 2px 0;
        }
        .podium-sub {
            font-size: 9px;
            color: #64748b;
        }
        /* Section Headings */
        .section-heading {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 14px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        /* Data Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .data-table th {
            background: #1e293b;
            color: #ffffff;
            font-size: 9.5px;
            font-weight: bold;
            text-align: left;
            padding: 6px 7px;
            text-transform: uppercase;
            border: 1px solid #1e293b;
        }
        .data-table th.num, .data-table td.num {
            text-align: right;
        }
        .data-table th.center, .data-table td.center {
            text-align: center;
        }
        .data-table td {
            padding: 5px 7px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9.5px;
        }
        .data-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        .rank-badge {
            display: inline-block;
            width: 18px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            border-radius: 9px;
            font-size: 9px;
            font-weight: bold;
            color: #ffffff;
            background: #64748b;
        }
        .rank-gold { background: #d97706; }
        .rank-silver { background: #64748b; }
        .rank-bronze { background: #b45309; }
        .trend-up {
            color: #10b981;
            font-weight: bold;
        }
        .trend-down {
            color: #ef4444;
            font-weight: bold;
        }
        .trend-neutral {
            color: #64748b;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: bold;
            background: #e2e8f0;
            color: #334155;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            font-size: 8.5px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="footer">
        DGT System &bull; Search Console Website Rankings & Leaderboard &bull; Generated on {{ now()->format('Y-m-d H:i:s') }}
    </div>

    {{-- Header --}}
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="header-title">🏆 Website Rankings & Leaderboard Report</div>
                <div class="header-subtitle">Performance Leaderboard, Top Performers, Class Breakdown & Period-over-Period Growth</div>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <span class="badge" style="background: #fef3c7; color: #92400e; font-size: 11px; padding: 4px 8px;">
                    {{ $scopeTitle }}
                </span>
            </td>
        </tr>
    </table>

    {{-- Meta Info --}}
    <table class="meta-table" cellpadding="2" cellspacing="0">
        <tr>
            <td width="30%">
                <span class="meta-label">Reporting Period:</span><br>
                <span class="meta-val">{{ $dateRange['start'] }} to {{ $dateRange['end'] }} ({{ $dateRange['days'] }} days)</span><br>
                <span style="font-size: 9px; color: #d97706; font-weight: bold;">{{ $dateRange['label'] ?? 'Selected Period' }}</span>
            </td>
            <td width="30%">
                <span class="meta-label">Comparison Period:</span><br>
                <span class="meta-val">{{ $dateRange['prev_start'] }} to {{ $dateRange['prev_end'] }}</span><br>
                <span style="font-size: 9px; color: #64748b;">Previous matching period</span>
            </td>
            <td width="40%">
                <span class="meta-label">Scope & Filters:</span><br>
                <span class="meta-val">
                    <strong>Class:</strong> {{ !empty($selectedClass) && $selectedClass !== 'all' ? $selectedClass : 'All Classes' }} &bull;
                    <strong>Member:</strong> {{ !empty($members) && $members->isNotEmpty() ? $members->pluck('name')->implode(', ') : 'All Team Members' }} &bull;
                    <strong>Ranked Sites:</strong> {{ count($rankings) }}
                </span>
            </td>
        </tr>
    </table>

    {{-- Top Performers Podium --}}
    @if(!empty($topPerformers))
    <div class="section-heading">Top Performers Highlights</div>
    <table class="podium-table" cellpadding="0" cellspacing="0">
        <tr>
            {{-- Top Clicks --}}
            <td class="podium-card" style="background: #eff6ff; border-color: #bfdbfe;">
                <div class="podium-category" style="color: #1d4ed8;">🥇 Top Clicks</div>
                <div class="podium-name">{{ $topPerformers['clicks']['name'] ?? 'N/A' }}</div>
                <div class="podium-metric" style="color: #2563eb;">{{ number_format($topPerformers['clicks']['clicks'] ?? 0) }}</div>
                <div class="podium-sub">
                    Growth: <span class="{{ ($topPerformers['clicks']['clicks_growth_pct'] ?? 0) >= 0 ? 'trend-up' : 'trend-down' }}">
                        {{ ($topPerformers['clicks']['clicks_growth_pct'] ?? 0) >= 0 ? '+' : '' }}{{ $topPerformers['clicks']['clicks_growth_pct'] ?? 0 }}%
                    </span>
                    &bull; {{ $topPerformers['clicks']['handler_name'] ?? 'Unassigned' }}
                </div>
            </td>

            {{-- Top Impressions --}}
            <td class="podium-card" style="background: #f0fdf4; border-color: #bbf7d0;">
                <div class="podium-category" style="color: #15803d;">👁️ Top Impressions</div>
                <div class="podium-name">{{ $topPerformers['impressions']['name'] ?? 'N/A' }}</div>
                <div class="podium-metric" style="color: #16a34a;">{{ number_format($topPerformers['impressions']['impressions'] ?? 0) }}</div>
                <div class="podium-sub">
                    Growth: <span class="{{ ($topPerformers['impressions']['impressions_growth_pct'] ?? 0) >= 0 ? 'trend-up' : 'trend-down' }}">
                        {{ ($topPerformers['impressions']['impressions_growth_pct'] ?? 0) >= 0 ? '+' : '' }}{{ $topPerformers['impressions']['impressions_growth_pct'] ?? 0 }}%
                    </span>
                    &bull; {{ $topPerformers['impressions']['handler_name'] ?? 'Unassigned' }}
                </div>
            </td>

            {{-- Best CTR --}}
            <td class="podium-card" style="background: #fdf4ff; border-color: #f5d0fe;">
                <div class="podium-category" style="color: #a21caf;">🎯 Highest CTR</div>
                <div class="podium-name">{{ $topPerformers['ctr']['name'] ?? 'N/A' }}</div>
                <div class="podium-metric" style="color: #9333ea;">{{ number_format($topPerformers['ctr']['ctr'] ?? 0, 2) }}%</div>
                <div class="podium-sub">
                    {{ number_format($topPerformers['ctr']['clicks'] ?? 0) }} clicks &bull; {{ $topPerformers['ctr']['handler_name'] ?? 'Unassigned' }}
                </div>
            </td>

            {{-- Best Position --}}
            <td class="podium-card" style="background: #fff7ed; border-color: #fed7aa;">
                <div class="podium-category" style="color: #c2410c;">⭐ Top Search Rank</div>
                <div class="podium-name">{{ $topPerformers['position']['name'] ?? 'N/A' }}</div>
                <div class="podium-metric" style="color: #ea580c;">#{{ number_format($topPerformers['position']['position'] ?? 0, 1) }}</div>
                <div class="podium-sub">
                    Avg Position &bull; {{ $topPerformers['position']['handler_name'] ?? 'Unassigned' }}
                </div>
            </td>
        </tr>
    </table>
    @endif

    {{-- Class Breakdown Summary --}}
    @if(!empty($classSummaries))
    <div class="section-heading">Class Breakdown Summary</div>
    <table class="data-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th width="20%">Class</th>
                <th width="12%" class="center">Sites Count</th>
                <th width="16%" class="num">Total Clicks</th>
                <th width="14%" class="num">Clicks Growth</th>
                <th width="16%" class="num">Total Impressions</th>
                <th width="11%" class="num">Avg CTR</th>
                <th width="11%" class="num">Avg Position</th>
            </tr>
        </thead>
        <tbody>
            @foreach($classSummaries as $cls)
            <tr>
                <td><strong>{{ $cls['class'] }}</strong></td>
                <td class="center">{{ $cls['sites_count'] }}</td>
                <td class="num"><strong>{{ number_format($cls['clicks']) }}</strong></td>
                <td class="num">
                    <span class="{{ ($cls['clicks_growth_pct'] ?? 0) >= 0 ? 'trend-up' : 'trend-down' }}">
                        {{ ($cls['clicks_growth_pct'] ?? 0) >= 0 ? '+' : '' }}{{ $cls['clicks_growth_pct'] ?? 0 }}%
                    </span>
                </td>
                <td class="num">{{ number_format($cls['impressions']) }}</td>
                <td class="num">{{ number_format($cls['ctr'], 2) }}%</td>
                <td class="num">{{ number_format($cls['position'], 1) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Full Leaderboard Table --}}
    <div class="section-heading">Website Rankings Leaderboard ({{ count($rankings) }} Sites)</div>
    <table class="data-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th width="5%" class="center">#</th>
                <th width="22%">Website Name & Domain</th>
                <th width="11%">Class</th>
                <th width="14%">Responsible</th>
                <th width="12%" class="num">Clicks (Growth)</th>
                <th width="14%" class="num">Impressions</th>
                <th width="8%" class="num">CTR</th>
                <th width="7%" class="num">Avg Pos</th>
                <th width="7%" class="num">Share</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rankings as $idx => $r)
            @php
                $rankNum = $idx + 1;
                $rankClass = match($rankNum) {
                    1 => 'rank-gold',
                    2 => 'rank-silver',
                    3 => 'rank-bronze',
                    default => 'rank-badge'
                };
            @endphp
            <tr>
                <td class="center">
                    <span class="rank-badge {{ $rankClass }}">{{ $rankNum }}</span>
                </td>
                <td>
                    <div style="font-weight: bold; color: #0f172a;">{{ $r['name'] }}</div>
                    <div style="font-size: 8.5px; color: #64748b; font-family: monospace;">{{ $r['clean_domain'] ?? $r['domain'] }}</div>
                </td>
                <td>
                    <span class="badge">{{ $r['class'] ?? 'General' }}</span>
                </td>
                <td style="color: #334155;">
                    {{ $r['handler_name'] ?? 'Unassigned' }}
                </td>
                <td class="num">
                    <strong>{{ number_format($r['clicks']) }}</strong><br>
                    <span style="font-size: 8px;" class="{{ ($r['clicks_growth_pct'] ?? 0) >= 0 ? 'trend-up' : 'trend-down' }}">
                        {{ ($r['clicks_growth_pct'] ?? 0) >= 0 ? '+' : '' }}{{ $r['clicks_growth_pct'] ?? 0 }}%
                    </span>
                </td>
                <td class="num">
                    {{ number_format($r['impressions']) }}<br>
                    <span style="font-size: 8px;" class="{{ ($r['impressions_growth_pct'] ?? 0) >= 0 ? 'trend-up' : 'trend-down' }}">
                        {{ ($r['impressions_growth_pct'] ?? 0) >= 0 ? '+' : '' }}{{ $r['impressions_growth_pct'] ?? 0 }}%
                    </span>
                </td>
                <td class="num">{{ number_format($r['ctr'], 2) }}%</td>
                <td class="num">{{ number_format($r['position'], 1) }}</td>
                <td class="num" style="font-size: 8.5px; color: #64748b;">
                    {{ $r['clicks_share'] ?? 0 }}%
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" style="text-align: center; color: #94a3b8; padding: 18px;">
                    No ranking data found for the selected scope and period.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
