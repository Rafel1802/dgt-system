<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Google Search Console SEO Report - {{ $scopeTitle }}</title>
    <style>
        @page {
            margin: 28px 28px 36px 28px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            background: #ffffff;
            font-size: 11px;
            line-height: 1.45;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header-title {
            font-size: 20px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 4px 0;
        }
        .header-subtitle {
            font-size: 11px;
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
            font-size: 10px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
        }
        .meta-val {
            font-size: 11px;
            font-weight: 600;
            color: #0f172a;
        }
        /* KPI Cards */
        .kpi-table {
            width: 100%;
            border-spacing: 8px;
            margin-bottom: 18px;
        }
        .kpi-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 10px;
            text-align: center;
            width: 25%;
        }
        .kpi-title {
            font-size: 10px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .kpi-value {
            font-size: 20px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .kpi-sub {
            font-size: 9.5px;
            color: #64748b;
        }
        .trend-up {
            color: #10b981;
            font-weight: bold;
        }
        .trend-down {
            color: #ef4444;
            font-weight: bold;
        }
        /* Section Headings */
        .section-heading {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 18px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        /* Data Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .data-table th {
            background: #1e293b;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            text-transform: uppercase;
            border: 1px solid #1e293b;
        }
        .data-table th.num, .data-table td.num {
            text-align: right;
        }
        .data-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
            word-break: break-all;
        }
        .data-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        .page-break {
            page-break-after: always;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            background: #e2e8f0;
            color: #334155;
        }
    </style>
</head>
<body>
    <div class="footer">
        DGT System &bull; Google Search Console Performance Report &bull; Generated on {{ now()->format('Y-m-d H:i:s') }}
    </div>

    {{-- Header --}}
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="header-title">Google Search Console SEO Report</div>
                <div class="header-subtitle">TYPHON Machinery &bull; Digital Team Management System</div>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <span class="badge" style="background: #dbeafe; color: #1e40af; font-size: 11px; padding: 4px 8px;">
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
                <span style="font-size: 9px; color: #3b82f6; font-weight: bold;">{{ $dateRange['label'] ?? 'Custom Range' }}</span>
            </td>
            <td width="30%">
                <span class="meta-label">Comparison Period:</span><br>
                <span class="meta-val">{{ $dateRange['prev_start'] }} to {{ $dateRange['prev_end'] }}</span><br>
                <span style="font-size: 9px; color: #64748b;">Equal duration preceding period</span>
            </td>
            <td width="40%">
                <span class="meta-label">Filter Scope & Breakdown:</span><br>
                <span class="meta-val">
                    <strong>Class:</strong> {{ !empty($selectedClass) && $selectedClass !== 'all' ? $selectedClass : 'All Classes' }} &bull;
                    <strong>Member:</strong> {{ !empty($members) && $members->isNotEmpty() ? $members->pluck('name')->implode(', ') : 'All Members' }} &bull;
                    <strong>Websites:</strong> {{ count($websites) }} Sites
                </span>
            </td>
        </tr>
    </table>

    {{-- KPI Cards --}}
    <table class="kpi-table" cellpadding="0" cellspacing="6">
        <tr>
            <td class="kpi-card">
                <div class="kpi-title">Total Clicks</div>
                <div class="kpi-value">{{ number_format($kpiSummary['clicks']['current']) }}</div>
                <div class="kpi-sub">
                    Prev: {{ number_format($kpiSummary['clicks']['previous']) }}
                    <span class="{{ $kpiSummary['clicks']['trend'] === 'up' ? 'trend-up' : 'trend-down' }}">
                        ({{ ($kpiSummary['clicks']['diff'] >= 0 ? '+' : '') . $kpiSummary['clicks']['percent'] }}%)
                    </span>
                </div>
            </td>
            <td class="kpi-card">
                <div class="kpi-title">Total Impressions</div>
                <div class="kpi-value">{{ number_format($kpiSummary['impressions']['current']) }}</div>
                <div class="kpi-sub">
                    Prev: {{ number_format($kpiSummary['impressions']['previous']) }}
                    <span class="{{ $kpiSummary['impressions']['trend'] === 'up' ? 'trend-up' : 'trend-down' }}">
                        ({{ ($kpiSummary['impressions']['diff'] >= 0 ? '+' : '') . $kpiSummary['impressions']['percent'] }}%)
                    </span>
                </div>
            </td>
            <td class="kpi-card">
                <div class="kpi-title">Average CTR</div>
                <div class="kpi-value">{{ number_format($kpiSummary['ctr']['current'], 2) }}%</div>
                <div class="kpi-sub">
                    Prev: {{ number_format($kpiSummary['ctr']['previous'], 2) }}%
                    <span class="{{ $kpiSummary['ctr']['trend'] === 'up' ? 'trend-up' : 'trend-down' }}">
                        ({{ ($kpiSummary['ctr']['diff'] >= 0 ? '+' : '') . $kpiSummary['ctr']['diff'] }}%)
                    </span>
                </div>
            </td>
            <td class="kpi-card">
                <div class="kpi-title">Average Position</div>
                <div class="kpi-value">{{ number_format($kpiSummary['position']['current'], 1) }}</div>
                <div class="kpi-sub">
                    Prev: {{ number_format($kpiSummary['position']['previous'], 1) }}
                    <span class="{{ $kpiSummary['position']['trend'] === 'up' ? 'trend-up' : 'trend-down' }}">
                        ({{ ($kpiSummary['position']['diff'] > 0 ? '+' : '') . $kpiSummary['position']['diff'] }})
                    </span>
                </div>
            </td>
        </tr>
    </table>

    {{-- Websites & Class Performance Breakdown --}}
    @if(!empty($websiteBreakdown) && count($websiteBreakdown) > 0)
    <div class="section-heading">Websites & Class Performance Breakdown (with Growth Comparison)</div>
    <table class="data-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th width="24%">Website</th>
                <th width="12%">Class</th>
                <th width="15%">Assigned Member</th>
                <th width="16%" class="num">Clicks (Growth)</th>
                <th width="17%" class="num">Impressions (Growth)</th>
                <th width="8%" class="num">CTR (%)</th>
                <th width="8%" class="num">Position</th>
            </tr>
        </thead>
        <tbody>
            @foreach($websiteBreakdown as $wb)
            <tr>
                <td>
                    <strong>{{ $wb['name'] }}</strong><br>
                    <span style="font-family: monospace; font-size: 8px; color: #64748b;">{{ $wb['clean_domain'] ?? $wb['url'] }}</span>
                </td>
                <td>
                    <span class="badge" style="background: #e0e7ff; color: #3730a3; font-size: 8.5px;">
                        {{ $wb['class'] ?? 'General' }}
                    </span>
                </td>
                <td>
                    {{ $wb['handler_name'] ?? 'Unassigned' }}
                </td>
                <td class="num">
                    <strong>{{ number_format($wb['clicks']) }}</strong>
                    <div style="font-size: 8px; color: #64748b;">
                        Prev: {{ number_format($wb['prev_clicks'] ?? 0) }}
                        <span class="{{ ($wb['clicks_growth_pct'] ?? 0) >= 0 ? 'trend-up' : 'trend-down' }}">
                            ({{ ($wb['clicks_growth_pct'] ?? 0) >= 0 ? '+' : '' }}{{ $wb['clicks_growth_pct'] ?? 0 }}%)
                        </span>
                    </div>
                </td>
                <td class="num">
                    <strong>{{ number_format($wb['impressions']) }}</strong>
                    <div style="font-size: 8px; color: #64748b;">
                        Prev: {{ number_format($wb['prev_impressions'] ?? 0) }}
                        <span class="{{ ($wb['impressions_growth_pct'] ?? 0) >= 0 ? 'trend-up' : 'trend-down' }}">
                            ({{ ($wb['impressions_growth_pct'] ?? 0) >= 0 ? '+' : '' }}{{ $wb['impressions_growth_pct'] ?? 0 }}%)
                        </span>
                    </div>
                </td>
                <td class="num">{{ number_format($wb['ctr'], 2) }}%</td>
                <td class="num">
                    {{ number_format($wb['position'], 1) }}
                    @if(isset($wb['position_diff']) && $wb['position_diff'] != 0)
                        <div style="font-size: 7.5px; color: {{ ($wb['position_trend'] ?? '') === 'up' ? '#10b981' : '#ef4444' }};">
                            {{ $wb['position_diff'] > 0 ? '+' : '' }}{{ $wb['position_diff'] }}
                        </div>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Top Search Keywords --}}
    <div class="section-heading">Top Search Keywords / Queries</div>
    <table class="data-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th width="45%">Search Query</th>
                <th width="13%" class="num">Clicks</th>
                <th width="15%" class="num">Impressions</th>
                <th width="13%" class="num">CTR (%)</th>
                <th width="14%" class="num">Avg Position</th>
            </tr>
        </thead>
        <tbody>
            @forelse(array_slice($keywords['data'], 0, 30) as $kw)
            <tr>
                <td><strong>{{ $kw['query'] }}</strong></td>
                <td class="num">{{ number_format($kw['clicks']) }}</td>
                <td class="num">{{ number_format($kw['impressions']) }}</td>
                <td class="num">{{ number_format($kw['ctr'], 2) }}%</td>
                <td class="num">{{ number_format($kw['position'], 1) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 16px;">
                    No search query data recorded for this period.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-break"></div>

    {{-- Top Landing Pages --}}
    <div class="section-heading">Top Performing Landing Pages</div>
    <table class="data-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th width="50%">Page URL</th>
                <th width="12%" class="num">Clicks</th>
                <th width="14%" class="num">Impressions</th>
                <th width="12%" class="num">CTR (%)</th>
                <th width="12%" class="num">Avg Position</th>
            </tr>
        </thead>
        <tbody>
            @forelse(array_slice($topPages['data'], 0, 25) as $pg)
            <tr>
                <td style="font-family: monospace; font-size: 9px; color: #2563eb;">{{ $pg['page'] }}</td>
                <td class="num">{{ number_format($pg['clicks']) }}</td>
                <td class="num">{{ number_format($pg['impressions']) }}</td>
                <td class="num">{{ number_format($pg['ctr'], 2) }}%</td>
                <td class="num">{{ number_format($pg['position'], 1) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 16px;">
                    No page data recorded for this period.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Device & Country Summaries --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 10px;">
        <tr>
            <td width="48%" style="vertical-align: top;">
                <div class="section-heading">Device Breakdown</div>
                <table class="data-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Device</th>
                            <th class="num">Clicks</th>
                            <th class="num">Impressions</th>
                            <th class="num">CTR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($devices as $dev)
                        <tr>
                            <td><strong>{{ $dev['device'] }}</strong> ({{ $dev['click_share'] }}%)</td>
                            <td class="num">{{ number_format($dev['clicks']) }}</td>
                            <td class="num">{{ number_format($dev['impressions']) }}</td>
                            <td class="num">{{ number_format($dev['ctr'], 2) }}%</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align: center; color: #94a3b8;">No device data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td width="4%"></td>
            <td width="48%" style="vertical-align: top;">
                <div class="section-heading">Top Countries</div>
                <table class="data-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th class="num">Clicks</th>
                            <th class="num">Impressions</th>
                            <th class="num">CTR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(array_slice($countries, 0, 8) as $c)
                        <tr>
                            <td><strong>{{ $c['country_name'] }}</strong> ({{ $c['country_code'] }})</td>
                            <td class="num">{{ number_format($c['clicks']) }}</td>
                            <td class="num">{{ number_format($c['impressions']) }}</td>
                            <td class="num">{{ number_format($c['ctr'], 2) }}%</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align: center; color: #94a3b8;">No country data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
