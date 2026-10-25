<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Head-to-Head Comparison Report - {{ $siteA->name ?? 'Site A' }} vs {{ $siteB->name ?? 'Site B' }}</title>
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
            border-bottom: 2px solid #6366f1;
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
        /* Side by Side Comparison Grid */
        .compare-kpi-table {
            width: 100%;
            border-spacing: 6px;
            margin-bottom: 16px;
        }
        .compare-kpi-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 8px;
            text-align: center;
            width: 25%;
            vertical-align: top;
        }
        .kpi-title {
            font-size: 9px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .site-split {
            width: 100%;
            margin-bottom: 4px;
        }
        .site-val {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }
        .site-label {
            font-size: 8.5px;
            color: #64748b;
            text-transform: uppercase;
        }
        .winner-tag {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            background: #dcfce7;
            color: #15803d;
            margin-top: 3px;
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
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: bold;
            background: #e2e8f0;
            color: #334155;
        }
        .badge-site-a {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        .badge-site-b {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
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
        DGT System &bull; Search Console Head-to-Head Comparison Report &bull; Generated on {{ now()->format('Y-m-d H:i:s') }}
    </div>

    {{-- Header --}}
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="header-title">⇄ Head-to-Head Website Performance Comparison</div>
                <div class="header-subtitle">Direct Metric Benchmarking, Keyword Differences & Traffic Trends</div>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <span class="badge" style="background: #e0e7ff; color: #4338ca; font-size: 11px; padding: 4px 8px;">
                    Comparison Audit
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
                <span style="font-size: 9px; color: #6366f1; font-weight: bold;">{{ $dateRange['label'] ?? 'Custom Range' }}</span>
            </td>
            <td width="35%">
                <span class="meta-label">Site A (Primary):</span><br>
                <span class="meta-val">
                    <span class="badge badge-site-a">{{ $siteA->name ?? 'Site A' }}</span><br>
                    <span style="font-size: 9px; color: #64748b; font-family: monospace;">{{ $siteA->clean_domain ?? $siteA->url ?? '' }}</span>
                    <span style="font-size: 9px; color: #64748b;">&bull; Class: {{ $siteA->category ?? 'General' }} &bull; Handler: {{ $siteA->handler->name ?? 'Unassigned' }}</span>
                </span>
            </td>
            <td width="35%">
                <span class="meta-label">Site B (Benchmark):</span><br>
                <span class="meta-val">
                    <span class="badge badge-site-b">{{ $siteB->name ?? 'Site B' }}</span><br>
                    <span style="font-size: 9px; color: #64748b; font-family: monospace;">{{ $siteB->clean_domain ?? $siteB->url ?? '' }}</span>
                    <span style="font-size: 9px; color: #64748b;">&bull; Class: {{ $siteB->category ?? 'General' }} &bull; Handler: {{ $siteB->handler->name ?? 'Unassigned' }}</span>
                </span>
            </td>
        </tr>
    </table>

    {{-- Head-to-Head Metric Comparison Cards --}}
    <div class="section-heading">Executive Head-to-Head Scorecard</div>
    <table class="compare-kpi-table" cellpadding="0" cellspacing="0">
        <tr>
            {{-- Clicks --}}
            <td class="compare-kpi-card">
                <div class="kpi-title">Total Clicks</div>
                <table class="site-split" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="50%" style="text-align: center; border-right: 1px solid #e2e8f0;">
                            <div class="site-label" style="color: #2563eb;">{{ $siteA->name ?? 'Site A' }}</div>
                            <div class="site-val" style="color: #2563eb;">{{ number_format($metricsA['clicks'] ?? 0) }}</div>
                        </td>
                        <td width="50%" style="text-align: center;">
                            <div class="site-label" style="color: #16a34a;">{{ $siteB->name ?? 'Site B' }}</div>
                            <div class="site-val" style="color: #16a34a;">{{ number_format($metricsB['clicks'] ?? 0) }}</div>
                        </td>
                    </tr>
                </table>
                @php
                    $clicksDiff = ($metricsA['clicks'] ?? 0) - ($metricsB['clicks'] ?? 0);
                    $clicksWinner = $clicksDiff > 0 ? ($siteA->name ?? 'Site A') : ($clicksDiff < 0 ? ($siteB->name ?? 'Site B') : 'Tied');
                @endphp
                <div class="winner-tag">
                    Leader: {{ $clicksWinner }} ({{ $clicksDiff > 0 ? '+' : '' }}{{ number_format($clicksDiff) }})
                </div>
            </td>

            {{-- Impressions --}}
            <td class="compare-kpi-card">
                <div class="kpi-title">Total Impressions</div>
                <table class="site-split" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="50%" style="text-align: center; border-right: 1px solid #e2e8f0;">
                            <div class="site-label" style="color: #2563eb;">{{ $siteA->name ?? 'Site A' }}</div>
                            <div class="site-val" style="color: #2563eb;">{{ number_format($metricsA['impressions'] ?? 0) }}</div>
                        </td>
                        <td width="50%" style="text-align: center;">
                            <div class="site-label" style="color: #16a34a;">{{ $siteB->name ?? 'Site B' }}</div>
                            <div class="site-val" style="color: #16a34a;">{{ number_format($metricsB['impressions'] ?? 0) }}</div>
                        </td>
                    </tr>
                </table>
                @php
                    $imprDiff = ($metricsA['impressions'] ?? 0) - ($metricsB['impressions'] ?? 0);
                    $imprWinner = $imprDiff > 0 ? ($siteA->name ?? 'Site A') : ($imprDiff < 0 ? ($siteB->name ?? 'Site B') : 'Tied');
                @endphp
                <div class="winner-tag">
                    Leader: {{ $imprWinner }} ({{ $imprDiff > 0 ? '+' : '' }}{{ number_format($imprDiff) }})
                </div>
            </td>

            {{-- CTR --}}
            <td class="compare-kpi-card">
                <div class="kpi-title">Average CTR</div>
                <table class="site-split" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="50%" style="text-align: center; border-right: 1px solid #e2e8f0;">
                            <div class="site-label" style="color: #2563eb;">{{ $siteA->name ?? 'Site A' }}</div>
                            <div class="site-val" style="color: #2563eb;">{{ number_format($metricsA['ctr'] ?? 0, 2) }}%</div>
                        </td>
                        <td width="50%" style="text-align: center;">
                            <div class="site-label" style="color: #16a34a;">{{ $siteB->name ?? 'Site B' }}</div>
                            <div class="site-val" style="color: #16a34a;">{{ number_format($metricsB['ctr'] ?? 0, 2) }}%</div>
                        </td>
                    </tr>
                </table>
                @php
                    $ctrDiff = round(($metricsA['ctr'] ?? 0) - ($metricsB['ctr'] ?? 0), 2);
                    $ctrWinner = $ctrDiff > 0 ? ($siteA->name ?? 'Site A') : ($ctrDiff < 0 ? ($siteB->name ?? 'Site B') : 'Tied');
                @endphp
                <div class="winner-tag">
                    Leader: {{ $ctrWinner }} ({{ $ctrDiff > 0 ? '+' : '' }}{{ $ctrDiff }}%)
                </div>
            </td>

            {{-- Avg Position --}}
            <td class="compare-kpi-card">
                <div class="kpi-title">Average Position</div>
                <table class="site-split" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="50%" style="text-align: center; border-right: 1px solid #e2e8f0;">
                            <div class="site-label" style="color: #2563eb;">{{ $siteA->name ?? 'Site A' }}</div>
                            <div class="site-val" style="color: #2563eb;">#{{ number_format($metricsA['position'] ?? 0, 1) }}</div>
                        </td>
                        <td width="50%" style="text-align: center;">
                            <div class="site-label" style="color: #16a34a;">{{ $siteB->name ?? 'Site B' }}</div>
                            <div class="site-val" style="color: #16a34a;">#{{ number_format($metricsB['position'] ?? 0, 1) }}</div>
                        </td>
                    </tr>
                </table>
                @php
                    $posA = $metricsA['position'] ?? 0;
                    $posB = $metricsB['position'] ?? 0;
                    // Lower position is better in SEO
                    $posWinner = ($posA > 0 && ($posA < $posB || $posB == 0)) ? ($siteA->name ?? 'Site A') : (($posB > 0 && ($posB < $posA || $posA == 0)) ? ($siteB->name ?? 'Site B') : 'Tied');
                @endphp
                <div class="winner-tag">
                    Leader: {{ $posWinner }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Top Search Queries Side-by-Side --}}
    <div class="section-heading">Top Search Keywords Benchmark</div>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            {{-- Site A Queries --}}
            <td width="48%" style="vertical-align: top;">
                <div style="font-weight: bold; color: #1d4ed8; margin-bottom: 4px;">
                    {{ $siteA->name ?? 'Site A' }} Top Queries
                </div>
                <table class="data-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Query</th>
                            <th class="num" width="22%">Clicks</th>
                            <th class="num" width="22%">Impr</th>
                            <th class="num" width="18%">Pos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(collect($queriesA ?? [])->take(10) as $q)
                        <tr>
                            <td>{{ $q['query'] ?? $q->query ?? '' }}</td>
                            <td class="num"><strong>{{ number_format($q['clicks'] ?? $q->clicks ?? 0) }}</strong></td>
                            <td class="num">{{ number_format($q['impressions'] ?? $q->impressions ?? 0) }}</td>
                            <td class="num">{{ number_format($q['position'] ?? $q->position ?? 0, 1) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align: center; color: #94a3b8;">No query data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>

            <td width="4%"></td>

            {{-- Site B Queries --}}
            <td width="48%" style="vertical-align: top;">
                <div style="font-weight: bold; color: #15803d; margin-bottom: 4px;">
                    {{ $siteB->name ?? 'Site B' }} Top Queries
                </div>
                <table class="data-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Query</th>
                            <th class="num" width="22%">Clicks</th>
                            <th class="num" width="22%">Impr</th>
                            <th class="num" width="18%">Pos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(collect($queriesB ?? [])->take(10) as $q)
                        <tr>
                            <td>{{ $q['query'] ?? $q->query ?? '' }}</td>
                            <td class="num"><strong>{{ number_format($q['clicks'] ?? $q->clicks ?? 0) }}</strong></td>
                            <td class="num">{{ number_format($q['impressions'] ?? $q->impressions ?? 0) }}</td>
                            <td class="num">{{ number_format($q['position'] ?? $q->position ?? 0, 1) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align: center; color: #94a3b8;">No query data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    {{-- Daily Comparison Table --}}
    @if(!empty($dailyComparison))
    <div class="section-heading">Daily Performance Head-to-Head</div>
    <table class="data-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th width="16%">Date</th>
                <th width="21%" class="num" style="color: #93c5fd;">{{ $siteA->name ?? 'Site A' }} Clicks</th>
                <th width="21%" class="num" style="color: #86efac;">{{ $siteB->name ?? 'Site B' }} Clicks</th>
                <th width="21%" class="num" style="color: #93c5fd;">{{ $siteA->name ?? 'Site A' }} Impr</th>
                <th width="21%" class="num" style="color: #86efac;">{{ $siteB->name ?? 'Site B' }} Impr</th>
            </tr>
        </thead>
        <tbody>
            @foreach(collect($dailyComparison ?? [])->take(20) as $day)
            <tr>
                <td><strong>{{ $day['date'] }}</strong></td>
                <td class="num">{{ number_format($day['clicks_a']) }}</td>
                <td class="num">{{ number_format($day['clicks_b']) }}</td>
                <td class="num">{{ number_format($day['impr_a']) }}</td>
                <td class="num">{{ number_format($day['impr_b']) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</body>
</html>
