<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sitemaps & Indexation Report - {{ $website->name ?? 'Website' }}</title>
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
            border-bottom: 2px solid #a855f7;
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
        /* KPI Cards */
        .kpi-table {
            width: 100%;
            border-spacing: 6px;
            margin-bottom: 16px;
        }
        .kpi-card {
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
            margin-bottom: 4px;
        }
        .kpi-value {
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .kpi-sub {
            font-size: 8.5px;
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
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: bold;
            background: #e2e8f0;
            color: #334155;
        }
        .badge-success {
            background: #dcfce7;
            color: #15803d;
        }
        .badge-warning {
            background: #fef3c7;
            color: #b45309;
        }
        .badge-danger {
            background: #fee2e2;
            color: #b91c1c;
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
        DGT System &bull; Google Search Console Sitemaps & Page Indexation Report &bull; Generated on {{ now()->format('Y-m-d H:i:s') }}
    </div>

    {{-- Header --}}
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="header-title">🌐 Sitemaps & Page Indexation Coverage Report</div>
                <div class="header-subtitle">Submitted XML Sitemaps, Google Indexing Status, Coverage Diagnostics & Crawl Health</div>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <span class="badge" style="background: #f3e8ff; color: #7e22ce; font-size: 11px; padding: 4px 8px;">
                    {{ $website->name ?? 'Target Website' }}
                </span>
            </td>
        </tr>
    </table>

    {{-- Meta Info --}}
    <table class="meta-table" cellpadding="2" cellspacing="0">
        <tr>
            <td width="35%">
                <span class="meta-label">Website Domain:</span><br>
                <span class="meta-val">{{ $website->name }} ({{ $website->clean_domain }})</span><br>
                <span style="font-size: 9px; color: #a855f7; font-weight: bold;">Class: {{ $website->category ?: 'General' }} &bull; Responsible: {{ $website->handler->name ?? 'Unassigned' }}</span>
            </td>
            <td width="35%">
                <span class="meta-label">Google Search Console Snapshot:</span><br>
                <span class="meta-val">
                    {{ $summary['is_official_snapshot'] ? 'Verified Official GSC Snapshot' : 'Auto-Computed Local Coverage' }}
                </span><br>
                <span style="font-size: 9px; color: #64748b;">Last Updated: {{ $summary['gsc_last_updated'] ?? now()->format('M d, Y') }}</span>
            </td>
            <td width="30%">
                <span class="meta-label">Audit Scope:</span><br>
                <span class="meta-val">
                    <strong>Sitemaps:</strong> {{ count($sitemaps) }} &bull;
                    <strong>Discovered URLs:</strong> {{ number_format($summary['total_discovered'] ?? 0) }}
                </span>
            </td>
        </tr>
    </table>

    {{-- Indexation Coverage KPI Cards --}}
    <div class="section-heading">Indexation Coverage Scorecard</div>
    <table class="kpi-table" cellpadding="0" cellspacing="0">
        <tr>
            {{-- Indexed Pages --}}
            <td class="kpi-card" style="background: #f0fdf4; border-color: #bbf7d0;">
                <div class="kpi-title" style="color: #15803d;">Total Indexed Pages</div>
                <div class="kpi-value" style="color: #16a34a;">{{ number_format($summary['total_indexed'] ?? 0) }}</div>
                <div class="kpi-sub">
                    Healthy &amp; visible in Google search results
                </div>
            </td>

            {{-- Not Indexed Pages --}}
            <td class="kpi-card" style="background: #fffbeb; border-color: #fde68a;">
                <div class="kpi-title" style="color: #b45309;">Not Indexed Pages</div>
                <div class="kpi-value" style="color: #d97706;">{{ number_format($summary['total_not_indexed'] ?? 0) }}</div>
                <div class="kpi-sub">
                    Excluded, crawling issues or noindex
                </div>
            </td>

            {{-- Total Discovered --}}
            <td class="kpi-card" style="background: #eff6ff; border-color: #bfdbfe;">
                <div class="kpi-title" style="color: #1d4ed8;">Discovered URLs</div>
                <div class="kpi-value" style="color: #2563eb;">{{ number_format($summary['total_discovered'] ?? 0) }}</div>
                <div class="kpi-sub">
                    Total known URLs across sitemaps &amp; crawls
                </div>
            </td>

            {{-- Indexation Health Rate --}}
            <td class="kpi-card" style="background: #faf5ff; border-color: #e9d5ff;">
                <div class="kpi-title" style="color: #7e22ce;">Indexation Health Rate</div>
                <div class="kpi-value" style="color: #9333ea;">{{ number_format($summary['index_rate'] ?? 0, 1) }}%</div>
                <div class="kpi-sub">
                    @if(($summary['index_rate'] ?? 0) >= 80)
                        <span class="badge badge-success">Optimal (&ge; 80%)</span>
                    @elseif(($summary['index_rate'] ?? 0) >= 50)
                        <span class="badge badge-warning">Moderate (50-79%)</span>
                    @else
                        <span class="badge badge-danger">Needs Review (&lt; 50%)</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Submitted Sitemaps Table --}}
    <div class="section-heading">Submitted XML Sitemaps ({{ count($sitemaps) }})</div>
    <table class="data-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th>Sitemap URL / Path</th>
                <th width="12%">Type</th>
                <th width="18%">Last Submitted</th>
                <th width="14%" class="center">Status</th>
                <th width="14%" class="num">Submitted URLs</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sitemaps as $sm)
            <tr>
                <td style="font-family: monospace; font-size: 9px; color: #2563eb;">{{ $sm['path'] }}</td>
                <td>{{ $sm['type'] ?? 'sitemap' }}</td>
                <td>{{ !empty($sm['last_submitted']) ? date('Y-m-d H:i', strtotime($sm['last_submitted'])) : 'N/A' }}</td>
                <td class="center">
                    @if(strtolower($sm['status'] ?? '') === 'success' || strtolower($sm['status'] ?? '') === 'ok')
                        <span class="badge badge-success">Success</span>
                    @elseif(!empty($sm['is_pending']))
                        <span class="badge badge-warning">Pending</span>
                    @else
                        <span class="badge">{{ ucfirst($sm['status'] ?? 'Active') }}</span>
                    @endif
                </td>
                <td class="num"><strong>{{ number_format($sm['submitted_urls'] ?? $sm['urls_count'] ?? 0) }}</strong></td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 14px;">
                    No submitted sitemaps found for this website.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Page Indexing Issues / Reasons Breakdown --}}
    @if(!empty($summary['gsc_reasons']))
    <div class="section-heading">Page Indexing Diagnostics &amp; Exclusion Reasons</div>
    <table class="data-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th>Reason / Issue Description</th>
                <th width="20%" class="num">Affected Pages</th>
                <th width="25%" class="center">Classification</th>
            </tr>
        </thead>
        <tbody>
            @foreach($summary['gsc_reasons'] as $reason)
            <tr>
                <td><strong>{{ $reason['reason'] ?? $reason['name'] ?? 'Issue' }}</strong></td>
                <td class="num"><strong style="color: #d97706;">{{ number_format($reason['pages_count'] ?? $reason['count'] ?? 0) }}</strong></td>
                <td class="center">
                    <span class="badge badge-warning">Excluded from Google</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Sample Pages Indexation & Traffic --}}
    @if(!empty($pages))
    <div class="section-heading">Pages Coverage &amp; Search Visibility Sample ({{ count($pages) }} URLs)</div>
    <table class="data-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th>Page URL</th>
                <th width="14%" class="center">Status</th>
                <th width="12%" class="num">Clicks</th>
                <th width="14%" class="num">Impressions</th>
                <th width="10%" class="num">CTR</th>
                <th width="16%">Last Crawled</th>
            </tr>
        </thead>
        <tbody>
            @foreach(collect($pages ?? [])->take(30) as $p)
            <tr>
                <td style="font-family: monospace; font-size: 8.5px; color: #1e293b; word-break: break-all;">
                    {{ $p['url'] }}
                </td>
                <td class="center">
                    @if(!empty($p['is_indexed']))
                        <span class="badge badge-success">Indexed</span>
                    @else
                        <span class="badge badge-warning">Not Indexed</span>
                    @endif
                </td>
                <td class="num">{{ number_format($p['clicks'] ?? 0) }}</td>
                <td class="num">{{ number_format($p['impressions'] ?? 0) }}</td>
                <td class="num">{{ number_format($p['ctr'] ?? 0, 2) }}%</td>
                <td style="font-size: 8.5px; color: #64748b;">
                    {{ !empty($p['last_crawl_time']) ? date('Y-m-d', strtotime($p['last_crawl_time'])) : ($p['last_seen_date'] ?? 'N/A') }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</body>
</html>
