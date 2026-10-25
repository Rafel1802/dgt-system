<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DGT Central Platform — Blog Performance Report</title>
    <style>
        @page {
            margin: 20mm 15mm 20mm 15mm;
            size: a4 landscape;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
        }
        .subtitle {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }
        .meta-text {
            font-size: 10px;
            color: #64748b;
            text-align: right;
        }
        .stats-summary {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
        }
        .stats-summary td {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            text-align: center;
        }
        .stats-label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
        }
        .stats-val {
            font-size: 14px;
            font-weight: bold;
            color: #4f46e5;
            margin-top: 2px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }
        table.data-table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        table.data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
            background: #e2e8f0;
            color: #475569;
        }
        .rank-medal {
            display: inline-block;
            width: 18px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            border-radius: 50%;
            font-size: 8px;
            font-weight: bold;
        }
        .medal-gold { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .medal-silver { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .medal-bronze { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
        .badge-class { background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; }
        .handler-text { font-size: 8px; color: #64748b; margin-top: 2px; }
        .text-right { text-align: right; }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            text-align: center;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td>
                <div class="title">DGT Central Platform — Blog Performance Report</div>
                <div class="subtitle">
                    {{ !empty($limit) ? 'Top ' . $limit . ' Ranking Blog Posts by Cumulative Views' : 'Multi-Site Blog View Metrics across Hostinger Accounts' }}
                </div>
            </td>
            <td class="meta-text">
                <div><strong>Generated:</strong> {{ $generatedAt }} by {{ $generatedBy }}</div>
                <div>
                    <strong>Date Range:</strong> {{ !empty($dateFrom) ? $dateFrom : 'All Time' }} to {{ !empty($dateTo) ? $dateTo : 'Present' }}
                    @if(!empty($limit))
                        &bull; <strong>Ranking Scope:</strong> Top {{ $limit }} Articles
                    @endif
                </div>
                <div>
                    <strong>Website:</strong> {{ $website ? $website->name : 'All Websites' }}
                    @if(!empty($category)) &bull; <strong>Class:</strong> {{ $category }} @endif
                    @if(!empty($member)) &bull; <strong>Handler:</strong> {{ $member->name }} @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="stats-summary">
        <tr>
            <td>
                <div class="stats-label">Total Posts Reported</div>
                <div class="stats-val">{{ number_format(count($posts)) }}</div>
            </td>
            <td>
                <div class="stats-label">Total Cumulative Views</div>
                <div class="stats-val">{{ number_format($totalViews) }}</div>
            </td>
            <td>
                <div class="stats-label">Avg Views Per Post</div>
                <div class="stats-val">{{ count($posts) > 0 ? number_format((int)($totalViews / count($posts))) : 0 }}</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;">#</th>
                <th style="width: 140px;">Website / Class / Handler</th>
                <th>Post Title & Canonical URL</th>
                <th style="width: 80px;">Author</th>
                <th style="width: 70px;">Published</th>
                <th style="width: 65px;" class="text-right">Cumulative Views</th>
                <th style="width: 65px;" class="text-right">Period (30d)</th>
                <th style="width: 70px;">Source</th>
            </tr>
        </thead>
        <tbody>
            @forelse($posts as $index => $post)
                <tr>
                    <td class="text-right">
                        @if($index === 0)
                            <span class="rank-medal medal-gold">1</span>
                        @elseif($index === 1)
                            <span class="rank-medal medal-silver">2</span>
                        @elseif($index === 2)
                            <span class="rank-medal medal-bronze">3</span>
                        @else
                            {{ $index + 1 }}
                        @endif
                    </td>
                    <td>
                        <strong>{{ $post->website?->name }}</strong><br>
                        <span class="badge badge-class">{{ $post->website?->category ?: '-' }}</span>
                        @if($post->website?->handler)
                            <div class="handler-text">👤 {{ $post->website->handler->name }}</div>
                        @endif
                    </td>
                    <td>
                        <strong style="color: #0f172a;">{{ $post->title }}</strong><br>
                        <span style="color: #64748b; font-size: 8px;">{{ $post->canonical_url }}</span>
                    </td>
                    <td>{{ $post->author_name ?: '-' }}</td>
                    <td>{{ $post->published_at ? $post->published_at->format('Y-m-d') : '-' }}</td>
                    <td class="text-right"><strong>{{ number_format($post->cumulative_views) }}</strong></td>
                    <td class="text-right">{{ number_format($post->period_views) }}</td>
                    <td><span class="badge">{{ strtoupper($post->analytics_source) }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px;">No blog posts found for this criteria.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        DGT Central System &copy; {{ date('Y') }} — Confidential Internal Performance Report
    </div>

</body>
</html>
