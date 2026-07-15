<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Follow Ups Report</title>
    <style>
        @page {
            margin: 30px 40px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #334155;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
            line-height: 1.5;
        }
        .header-container {
            margin-bottom: 25px;
            border-bottom: 3px solid #6366f1;
            padding-bottom: 12px;
        }
        .header-title {
            color: #1e1b4b;
            font-size: 22px;
            font-weight: 800;
            margin: 0 0 4px 0;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }
        .header-subtitle {
            color: #64748b;
            font-size: 11px;
            margin: 0;
            font-weight: 500;
        }
        
        /* Filter Information Panel */
        .filter-panel {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            width: 100%;
            box-sizing: border-box;
        }
        .filter-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }
        .filter-table td {
            border: none;
            padding: 2px 0;
            font-size: 10px;
            color: #475569;
        }
        .filter-label {
            font-weight: bold;
            color: #1e293b;
            width: 120px;
        }

        /* Stats Panel */
        .stats-badge {
            background-color: #e0e7ff;
            color: #3730a3;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
            font-size: 10px;
        }

        /* Main Data Table */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8px;
            padding: 10px 8px;
            text-align: left;
            border-bottom: 2px solid #cbd5e1;
            letter-spacing: 0.5px;
        }
        table.data-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            font-size: 9px;
            color: #334155;
            word-wrap: break-word;
        }
        table.data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
        }
        .badge-blog_post { background-color: #dbeafe; color: #1e40af; }
        .badge-indexed_page { background-color: #f3e8ff; color: #6b21a8; }
        .badge-website_page { background-color: #f1f5f9; color: #334155; }
        .badge-other { background-color: #fafaf9; color: #44403c; }

        .badge-yes { background-color: #dcfce7; color: #166534; }
        .badge-no { background-color: #fee2e2; color: #991b1b; }
        .badge-pending { background-color: #fef9c3; color: #854d0e; }

        .badge-approved { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-checked { background-color: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-qc-pending { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        .text-link {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 500;
        }
        
        .footer {
            margin-top: 35px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            text-align: space-between;
            font-size: 8px;
            color: #94a3b8;
        }
        .footer-left {
            float: left;
        }
        .footer-right {
            float: right;
        }
    </style>
</head>
<body>

    <div class="header-container">
        <h1 class="header-title">Follow Ups Report</h1>
        <p class="header-subtitle">Website Build & SEO Tracking System</p>
    </div>

    <div class="filter-panel">
        <table class="filter-table">
            <tr>
                <td class="filter-label">Generated Date:</td>
                <td>{{ now()->format('F j, Y, g:i a') }}</td>
                <td class="filter-label" style="text-align:right;">Total Records:</td>
                <td style="text-align:right;"><span class="stats-badge">{{ $followUps->count() }} Entries</span></td>
            </tr>
            <tr>
                <td class="filter-label">Date Filter:</td>
                <td>
                    @if($startDate || $endDate)
                        {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('M j, Y') : 'Beginning' }} 
                        to 
                        {{ $endDate ? \Carbon\Carbon::parse($endDate)->format('M j, Y') : 'Now' }}
                    @else
                        All Dates
                    @endif
                </td>
                <td class="filter-label" style="text-align:right;">Filtered Member:</td>
                <td style="text-align:right;">{{ $memberName ?? 'All Members' }}</td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="18%">Website</th>
                <th width="12%">Type</th>
                <th width="28%">URL</th>
                <th width="12%">Handle by</th>
                <th width="10%">QC Status</th>
                <th width="10%">Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($followUps as $fu)
            <tr>
                <td>
                    <div style="font-weight: bold; color: #1e293b;">{{ $fu->website->name ?? '-' }}</div>
                    @if($fu->website && $fu->website->category)
                        <div style="font-size: 7px; color: #64748b; margin-top: 2px;">Class: {{ $fu->website->category }}</div>
                    @endif
                </td>
                <td>
                    <span class="badge badge-{{ $fu->type }}">
                        {{ $fu->getTypeLabel() }}
                    </span>
                </td>
                <td>
                    @if($fu->url)
                        <div style="margin-bottom: 4px;"><a href="{{ $fu->url }}" class="text-link" target="_blank">{{ Str::limit($fu->url, 50) }}</a></div>
                    @endif
                    @if($fu->note)
                        <div style="font-size: 8px; color: #64748b; font-style: italic; background-color: #fafafa; padding: 4px; border-radius: 4px; border-left: 2px solid #cbd5e1;">
                            {{ $fu->note }}
                        </div>
                    @endif
                </td>
                <td>
                    <div style="font-weight: 500;">{{ $fu->assignee?->name ?? 'Unassigned' }}</div>
                    @if($fu->creator)
                        <div style="font-size: 7px; color: #94a3b8; margin-top: 1px;">Created by: {{ $fu->creator->name }}</div>
                    @endif
                </td>
                <td>
                    @php
                        $qcBadge = $fu->qc_status === 'approved' ? 'badge-approved' : ($fu->qc_status === 'checked' ? 'badge-checked' : 'badge-qc-pending');
                    @endphp
                    <span class="badge {{ $qcBadge }}">
                        {{ ucfirst($fu->qc_status ?? 'Pending') }}
                    </span>
                    @if($fu->qc_checked_at && $fu->qcChecker)
                        <div style="font-size: 7px; color: #94a3b8; margin-top: 2px;">By: {{ $fu->qcChecker->name }}</div>
                    @endif
                </td>
                <td>
                    <div>{{ $fu->created_at->format('d M Y') }}</div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align: center; padding: 25px; color: #64748b; font-style: italic;">No follow-up entries found matching the filter criteria.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            KIUQ SYSTEM &copy; {{ date('Y') }} - Website Follow Ups Report
        </div>
        <div class="footer-right">
            Confidential Document
        </div>
    </div>

</body>
</html>
