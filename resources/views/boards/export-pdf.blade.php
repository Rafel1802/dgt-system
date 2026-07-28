<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Board Report - {{ $board ? $board->name : 'Personal Consolidated Report' }}</title>
    <!-- Premium Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #e0e7ff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
            background-color: #fff;
            margin: 0;
            padding: 30px;
            line-height: 1.5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        h1, h2, h3, .font-display {
            font-family: 'Outfit', sans-serif;
            margin: 0;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header-left h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
        }

        .header-left .meta-info {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 5px;
        }

        .header-right {
            text-align: right;
        }

        .header-right .badge {
            display: inline-block;
            background-color: var(--primary-light);
            color: var(--primary);
            font-weight: 700;
            font-size: 11px;
            padding: 6px 12px;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .header-right .date-info {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 8px;
        }

        /* KPI Cards Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 35px;
        }

        .stat-card {
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }

        .stat-card .label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.75px;
            margin-bottom: 6px;
        }

        .stat-card .value {
            font-size: 26px;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
        }

        .stat-card.completed {
            border-color: var(--success);
            background-color: #f6fdf9;
        }
        .stat-card.completed .value { color: var(--success); }

        .stat-card.pending {
            border-color: var(--warning);
            background-color: #fffdf5;
        }
        .stat-card.pending .value { color: var(--warning); }

        .stat-card.overdue {
            border-color: var(--danger);
            background-color: #fffcfc;
        }
        .stat-card.overdue .value { color: var(--danger); }

        /* Sections */
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-container {
            margin-bottom: 35px;
            page-break-inside: avoid;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 20px;
        }

        th {
            background-color: var(--bg-light);
            color: var(--text-muted);
            font-weight: 700;
            text-align: left;
            padding: 10px 12px;
            border-bottom: 2px solid var(--border-color);
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-dark);
            vertical-align: top;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .task-title {
            font-weight: 600;
            font-size: 13px;
        }

        .task-desc {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 5px;
            background-color: var(--bg-light);
            padding: 6px 10px;
            border-radius: 6px;
            border-left: 3px solid var(--border-color);
            white-space: pre-line;
        }

        /* Badges */
        .status-badge {
            display: inline-block;
            font-weight: 700;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        
        .status-todo { background-color: #f1f5f9; color: #475569; }
        .status-in_progress { background-color: #dbeafe; color: #1d4ed8; }
        .status-review { background-color: #fef3c7; color: #b45309; }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-rejected { background-color: #fee2e2; color: #b91c1c; }
        .status-done { background-color: #ede9fe; color: #5b21b6; }
        .status-archived { background-color: #e2e8f0; color: #1e293b; border: 1px dashed #94a3b8; }

        .priority-badge {
            display: inline-block;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .priority-urgent { background-color: #fee2e2; color: #ef4444; }
        .priority-high { background-color: #ffedd5; color: #f97316; }
        .priority-medium { background-color: #e0e7ff; color: #4338ca; }
        .priority-low { background-color: #f1f5f9; color: #64748b; }

        .tag-label {
            display: inline-block;
            background-color: var(--primary-light);
            color: var(--primary);
            font-size: 9px;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: 4px;
            margin-right: 3px;
            margin-bottom: 3px;
        }

        /* Print Specifics */
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
            .section-container {
                page-break-inside: avoid;
            }
        }

        .print-btn-container {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .btn-print {
            background-color: var(--primary);
            color: white;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
            transition: transform 0.15s, opacity 0.15s;
        }

        .btn-print:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        .btn-print:active {
            transform: translateY(0);
        }

        /* Card comments rendering */
        .task-comments {
            margin-top: 8px;
            background-color: #f1f5f9;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 11px;
            border-left: 3px solid #6366f1;
            text-align: left;
        }
        .comments-header {
            font-weight: 700;
            color: #475569;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.5px;
        }
        .comments-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .comment-item {
            padding-bottom: 4px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .comment-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .comment-author {
            font-weight: 600;
            color: #1e293b;
        }
        .comment-date {
            color: var(--text-muted);
            font-size: 9px;
            margin-left: 6px;
        }
        .comment-body {
            color: #334155;
            margin-top: 2px;
            white-space: pre-line;
        }

        /* Task images rendering */
        .task-images {
            margin-top: 10px;
            background-color: #f8fafc;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        .images-header {
            font-weight: 700;
            color: #475569;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.5px;
        }
        .images-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .image-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100px;
            background-color: #fff;
            padding: 4px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .task-image-thumbnail {
            width: 92px;
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
        }
        .image-name {
            font-size: 8px;
            color: var(--text-muted);
            margin-top: 4px;
            text-align: center;
            width: 90px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>

    <!-- Print Button overlay (hidden when printing) -->
    <div class="print-btn-container no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
    </div>

    <!-- Header -->
    <div class="header-container">
        <div class="header-left">
            <h1>{{ $board ? $board->name : 'Personal Consolidated Report' }}</h1>
            <div class="meta-info">
                @if($board)
                    Workspace: <strong>{{ $board->workspace->name ?? 'N/A' }}</strong> &nbsp;|&nbsp; 
                @else
                    Workspaces/Boards: <strong>Consolidated</strong> &nbsp;|&nbsp; 
                @endif
                Report Period: <strong>{{ $period }}</strong>
            </div>
        </div>
        <div class="header-right">
            <span class="badge">{{ $board ? 'Board Report' : 'Personal Report' }}</span>
            <div class="date-info">Exported: {{ $exportDate }}</div>
        </div>
    </div>

    <!-- KPI Summary Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="label">Total Tasks</div>
            <div class="value">{{ $totalTasks }}</div>
        </div>
        <div class="stat-card completed">
            <div class="label">Completed</div>
            <div class="value">{{ $completedTasks }}</div>
        </div>
        <div class="stat-card pending">
            <div class="label">Pending</div>
            <div class="value">{{ $pendingTasks }}</div>
        </div>
        <div class="stat-card overdue">
            <div class="label">Errors / Overdue</div>
            <div class="value">{{ $errorTasks ?? 0 }} / {{ $overdueTasks }}</div>
        </div>
    </div>

    <!-- Team Productivity Summary Section -->
    <div class="section-container">
        <h2 class="section-title">📊 Team Productivity Summary</h2>
        <table style="max-width: 600px;">
            <thead>
                <tr>
                    <th>Member Name</th>
                    <th style="width: 120px; text-align: center;">Completed Tasks</th>
                    <th style="width: 120px; text-align: center;">Pending Tasks</th>
                    <th style="width: 120px; text-align: center;">Total Tasks</th>
                </tr>
            </thead>
            <tbody>
                @foreach($memberStats as $name => $stats)
                <tr>
                    <td><strong>{{ $name }}</strong></td>
                    <td style="text-align: center; color: var(--success); font-weight: 600;">{{ $stats['completed'] }}</td>
                    <td style="text-align: center; color: var(--warning); font-weight: 600;">{{ $stats['pending'] }}</td>
                    <td style="text-align: center; font-weight: 600;">{{ $stats['total'] }}</td>
                </tr>
                @endforeach
                @if(empty($memberStats))
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--text-muted); font-style: italic;">No member productivity details.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <!-- Task Details Section -->
    <div class="section-container">
        <h2 class="section-title">📋 Task Details</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: {{ ($isQcReport ?? false) ? '26%' : '30%' }};">Task / Title</th>
                    <th style="width: 12%; text-align: center;">Status</th>
                    <th style="width: 16%;">Assigned Members</th>
                    <th style="width: 9%;">Created Date</th>
                    <th style="width: 9%;">Due Date</th>
                    <th style="width: 9%;">Completed Date</th>
                    @if($isQcReport ?? false)
                    <th style="width: 9%; text-align: center; background:#eff6ff; color:#1d4ed8;">QC Reviews</th>
                    @endif
                    <th style="width: 10%;">Labels</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cards as $c)
                <tr>
                    <td>
                        <div class="task-title">{{ $c->title }}</div>
                        @if($includeDesc && $c->description)
                            <div class="task-desc">{!! strip_tags($c->description) !!}</div>
                        @endif
                        @if(!empty($c->rejection_reason))
                            <div style="margin-top: 6px; padding: 6px 10px; background-color: #fef2f2; border-radius: 6px; border-left: 3px solid #ef4444; font-size: 11px; color: #991b1b;">
                                <strong>Error Reason:</strong> {{ $c->rejection_reason }}
                            </div>
                        @endif
                        @if(($includeComments ?? false) && $c->comments->isNotEmpty())
                            <div class="task-comments">
                                <div class="comments-header">💬 Comments ({{ $c->comments->count() }})</div>
                                <div class="comments-list">
                                    @foreach($c->comments as $cmt)
                                        @php
                                            $parsedComment = \App\Http\Controllers\Board\BoardExportController::extractScreenshotsAndClean($cmt->body);
                                        @endphp
                                        <div class="comment-item">
                                            <span class="comment-author">{{ $cmt->user->name ?? 'System' }}</span>
                                            <span class="comment-date">{{ $cmt->created_at->format('M d, Y g:i A') }}</span>
                                            <div class="comment-body">{{ $parsedComment['text'] }}</div>
                                            
                                            @if(!empty($parsedComment['screenshots']))
                                                <div class="comment-screenshots" style="margin-top: 6px; display: flex; flex-wrap: wrap; gap: 8px;">
                                                    @foreach($parsedComment['screenshots'] as $scrIdx => $scrSrc)
                                                        <div class="image-item" style="display: flex; flex-direction: column; align-items: center; width: 120px; background-color: #fff; padding: 4px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                                            <img src="{{ $scrSrc }}" style="width: 112px; max-height: 90px; object-fit: contain; border-radius: 4px;" class="task-image-thumbnail">
                                                            <div class="image-name" style="font-size: 8px; color: var(--text-muted); margin-top: 4px; text-align: center; width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Screenshot {{ $scrIdx + 1 }}</div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if(($includeComments ?? false) && $c->files->isNotEmpty())
                            @php
                                $images = $c->files->filter(fn($f) => $f->is_image);
                            @endphp
                            @if($images->isNotEmpty())
                                <div class="task-images">
                                    <div class="images-header">🖼️ Screenshots / Images</div>
                                    <div class="images-gallery">
                                        @foreach($images as $img)
                                            @php
                                                $imgSrc = $img->preview_url;
                                                if ($img->disk === 'local') {
                                                    $filePath = storage_path('app/' . $img->path);
                                                    if (file_exists($filePath)) {
                                                        $fileData = file_get_contents($filePath);
                                                        $imgSrc = 'data:' . ($img->mime_type ?? 'image/png') . ';base64,' . base64_encode($fileData);
                                                    }
                                                }
                                            @endphp
                                            <div class="image-item">
                                                <img src="{{ $imgSrc }}" class="task-image-thumbnail">
                                                <div class="image-name">{{ $img->original_name }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    </td>
                    <td style="text-align: center;">
                        @if($c->is_archived)
                            <span class="status-badge status-archived">Archived</span>
                        @elseif(stripos($c->boardList?->name ?? '', 'Approved') !== false)
                            <span class="status-badge status-approved">Approved ✓</span>
                        @elseif(stripos($c->boardList?->name ?? '', 'Urgent') !== false)
                            <span class="status-badge" style="background:#fee2e2;color:#b91c1c;">Urgent</span>
                        @elseif(stripos($c->boardList?->name ?? '', 'Block') !== false)
                            <span class="status-badge" style="background:#e2e8f0;color:#475569;">Blocked</span>
                        @elseif(stripos($c->boardList?->name ?? '', 'Supervisor') !== false)
                            <span class="status-badge status-review">Supervisor Review</span>
                        @elseif(stripos($c->boardList?->name ?? '', 'QC') !== false || stripos($c->boardList?->name ?? '', 'Text') !== false)
                            <span class="status-badge status-in_progress">QC Review</span>
                        @elseif(stripos($c->boardList?->name ?? '', 'Head Review') !== false)
                            <span class="status-badge status-in_progress">Head Review</span>
                        @elseif(stripos($c->boardList?->name ?? '', 'Drafting') !== false || stripos($c->boardList?->name ?? '', 'Draft') !== false)
                            <span class="status-badge status-todo">Drafting</span>
                        @elseif($c->boardList)
                            <span class="status-badge status-todo">{{ $c->boardList->name }}</span>
                        @else
                            <span class="status-badge status-todo">To Do</span>
                        @endif
                    </td>
                    <td>
                        {{ $c->assignees->pluck('name')->join(', ') ?: 'Unassigned' }}
                    </td>
                    <td>
                        {{ $c->created_at ? $c->created_at->format('Y-m-d') : 'N/A' }}
                    </td>
                    <td>
                        @php
                            // Use deadline field (date-only) — due_at is often null
                            $dueDate = $c->deadline ?? $c->due_at;
                        @endphp
                        {{ $dueDate ? \Carbon\Carbon::parse($dueDate)->format('Y-m-d') : 'None' }}
                    </td>
                    <td>
                        @php
                            $inApproved = stripos($c->boardList?->name ?? '', 'Approved') !== false;
                        @endphp
                        {{ $inApproved ? ($c->approved_at?->format('Y-m-d') ?? $c->updated_at?->format('Y-m-d') ?? 'Completed') : '-' }}
                    </td>
                    @if($isQcReport ?? false)
                    <td style="text-align: center; vertical-align: top;">
                        @php
                            // qcApprovalComments is eager-loaded when user is QC.
                            // Each comment = one review cycle (may repeat after rejection).
                            $qcComments = $c->relationLoaded('qcApprovalComments')
                                ? $c->qcApprovalComments
                                : collect();
                            $qcCount = $qcComments->count();
                        @endphp
                        @if($qcCount > 0)
                            <span style="display:inline-block; background:#dbeafe; color:#1d4ed8; font-weight:800; font-size:14px; border-radius:50%; width:26px; height:26px; line-height:26px; text-align:center;">{{ $qcCount }}</span>
                            @if($qcCount > 1)
                            <div style="font-size:9px; color:#dc2626; font-weight:700; margin-top:3px;">⚠ Checked {{ $qcCount }}× ({{ $qcCount - 1 }} re-review{{ $qcCount > 2 ? 's' : '' }})</div>
                            @endif
                            <div style="font-size:8px; color:#64748b; margin-top:4px;">
                                @foreach($qcComments as $qi => $qcmt)
                                <div style="margin-bottom:2px; padding:2px 4px; background:{{ $qi === 0 ? '#f0fdf4' : '#fef2f2' }}; border-radius:3px;">
                                    <span style="font-weight:600;">Review {{ $qi + 1 }}:</span> {{ $qcmt->created_at->format('d M Y') }}
                                </div>
                                @endforeach
                            </div>
                        @else
                            <span style="color:#94a3b8; font-size:11px;">—</span>
                        @endif
                    </td>
                    @endif
                    <td>
                        @foreach($c->labels as $lbl)
                            <span class="tag-label" style="background-color: {{ $lbl->color }}20; color: {{ $lbl->color }}; border: 1px solid {{ $lbl->color }}40;">{{ $lbl->name }}</span>
                        @endforeach
                    </td>
                </tr>
                @endforeach
                @if($cards->isEmpty())
                <tr>
                    <td colspan="{{ ($isQcReport ?? false) ? 8 : 7 }}" style="text-align: center; color: var(--text-muted); font-style: italic; padding: 20px 0;">No tasks found matching the selected filters.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <!-- Auto-trigger Browser Printing -->
    <script>
        window.addEventListener('load', function() {
            // Auto open print dialog
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
