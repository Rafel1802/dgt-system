<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-type" content="text/html;charset=UTF-8" />
    <!--[if gte mso 9]>
    <xml>
        {!! '<' . 'x:ExcelWorkbook>' !!}
            {!! '<' . 'x:ExcelWorksheets>' !!}
                {!! '<' . 'x:ExcelWorksheet>' !!}
                    {!! '<' . 'x:Name>Board Report</' . 'x:Name>' !!}
                    {!! '<' . 'x:WorksheetOptions>' !!}
                        {!! '<' . 'x:DisplayGridlines/>' !!}
                    {!! '</' . 'x:WorksheetOptions>' !!}
                {!! '</' . 'x:ExcelWorksheet>' !!}
            {!! '</' . 'x:ExcelWorksheets>' !!}
        {!! '</' . 'x:ExcelWorkbook>' !!}
    </xml>
    <![endif]-->
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #0f172a;
        }
        table {
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            font-size: 11px;
            vertical-align: top;
        }
        th {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #475569;
            text-align: left;
        }
        .text-format {
            mso-number-format: "\@";
        }
        .header-title {
            font-size: 16px;
            font-weight: bold;
            color: #4f46e5;
            border: none;
        }
        .meta-label {
            font-weight: bold;
            color: #64748b;
            border: none;
        }
        .meta-value {
            color: #0f172a;
            border: none;
        }
        .section-header {
            font-size: 12px;
            font-weight: bold;
            color: #1e293b;
            background-color: #e2e8f0;
            border: none;
        }
        .stat-label {
            font-size: 10px;
            font-weight: bold;
            color: #475569;
            background-color: #f8fafc;
            text-align: center;
        }
        .stat-value {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
        }
        .status-badge {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            text-align: center;
        }
        .task-title {
            font-weight: bold;
            font-size: 12px;
            color: #0f172a;
        }
        .task-desc {
            font-size: 10px;
            color: #475569;
            background-color: #f8fafc;
            padding: 6px;
            border-left: 2px solid #cbd5e1;
            white-space: pre-wrap;
        }
        .task-comments {
            margin-top: 8px;
            background-color: #f1f5f9;
            padding: 6px 8px;
            font-size: 10px;
            border-left: 2px solid #6366f1;
        }
        .comments-header {
            font-weight: bold;
            color: #475569;
            margin-bottom: 4px;
        }
        .comment-item {
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 3px;
            margin-bottom: 3px;
        }
        .comment-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .comment-author {
            font-weight: bold;
            color: #1e293b;
        }
        .comment-date {
            color: #64748b;
            font-size: 9px;
        }
        .comment-body {
            color: #334155;
            white-space: pre-wrap;
        }
        .tag-label {
            display: inline-block;
            background-color: #e0e7ff;
            color: #4338ca;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 4px;
            border-radius: 3px;
            margin-right: 2px;
            margin-bottom: 2px;
        }
        .task-images {
            margin-top: 10px;
            background-color: #f8fafc;
            padding: 8px;
            border: 1px solid #e2e8f0;
        }
        .images-header {
            font-weight: bold;
            color: #475569;
            margin-bottom: 6px;
            font-size: 9px;
        }
        .images-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .image-item {
            display: inline-block;
            background-color: #fff;
            padding: 4px;
            border: 1px solid #e2e8f0;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .task-image-thumbnail {
            width: 100px;
            height: 75px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <table>
        <!-- Section 1: Header / Title -->
        <tr>
            <td colspan="7" class="header-title">{{ $board ? $board->name : 'Personal Consolidated Report' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Workspace:</td>
            <td colspan="6" class="meta-value">{{ $board ? ($board->workspace->name ?? 'N/A') : 'Consolidated' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Report Period:</td>
            <td colspan="6" class="meta-value">{{ $period }}</td>
        </tr>
        <tr>
            <td class="meta-label">Export Date:</td>
            <td colspan="6" class="meta-value">{{ $exportDate }}</td>
        </tr>
        <tr>
            <td colspan="7" style="border: none;">&nbsp;</td>
        </tr>

        <!-- Section 2: KPI Summary -->
        <tr>
            <td colspan="7" class="section-header">📊 KPI SUMMARY</td>
        </tr>
        <tr>
            <td class="stat-label">Total Tasks</td>
            <td class="stat-label">Completed</td>
            <td class="stat-label">Pending</td>
            <td class="stat-label">Overdue</td>
            <td colspan="3" style="border: none;">&nbsp;</td>
        </tr>
        <tr>
            <td class="stat-value">{{ $totalTasks }}</td>
            <td class="stat-value" style="color: #10b981;">{{ $completedTasks }}</td>
            <td class="stat-value" style="color: #f59e0b;">{{ $pendingTasks }}</td>
            <td class="stat-value" style="color: #ef4444;">{{ $overdueTasks }}</td>
            <td colspan="3" style="border: none;">&nbsp;</td>
        </tr>
        <tr>
            <td colspan="7" style="border: none;">&nbsp;</td>
        </tr>

        <!-- Section 3: Team Productivity Summary -->
        <tr>
            <td colspan="7" class="section-header">📊 Team Productivity Summary</td>
        </tr>
        <tr>
            <th colspan="3">Member Name</th>
            <th style="text-align: center;">Completed Tasks</th>
            <th style="text-align: center;">Pending Tasks</th>
            <th style="text-align: center;" colspan="2">Total Tasks</th>
        </tr>
        @foreach($memberStats as $name => $stats)
        <tr>
            <td colspan="3"><strong>{{ $name }}</strong></td>
            <td style="text-align: center; color: #10b981; font-weight: bold;">{{ $stats['completed'] }}</td>
            <td style="text-align: center; color: #f59e0b; font-weight: bold;">{{ $stats['pending'] }}</td>
            <td style="text-align: center; font-weight: bold;" colspan="2">{{ $stats['total'] }}</td>
        </tr>
        @endforeach
        @if(empty($memberStats))
        <tr>
            <td colspan="7" style="text-align: center; color: #64748b; font-style: italic;">No member productivity details.</td>
        </tr>
        @endif
        <tr>
            <td colspan="7" style="border: none;">&nbsp;</td>
        </tr>

        <!-- Section 4: Task Details -->
        <tr>
            <td colspan="7" class="section-header">📋 Task Details</td>
        </tr>
        <tr>
            <th style="width: 350px;">Task / Title</th>
            <th style="width: 100px; text-align: center;">Status</th>
            <th style="width: 150px;">Assigned Members</th>
            <th style="width: 90px;">Created Date</th>
            <th style="width: 90px;">Due Date</th>
            <th style="width: 90px;">Completed Date</th>
            <th style="width: 120px;">Labels</th>
        </tr>
        @foreach($cards as $c)
        <tr>
            <td>
                <div class="task-title">{{ $c->title }}</div>
                @if($includeDesc && $c->description)
                    <div class="task-desc">{!! strip_tags($c->description) !!}</div>
                @endif
                
                @if(($includeComments ?? false) && $c->comments->isNotEmpty())
                    <div class="task-comments">
                        <div class="comments-header">💬 Comments ({{ $c->comments->count() }})</div>
                        @foreach($c->comments as $cmt)
                            @php
                                $parsedComment = \App\Http\Controllers\Board\BoardExportController::extractScreenshotsAndClean($cmt->body);
                            @endphp
                            <div class="comment-item">
                                <span class="comment-author">{{ $cmt->user->name ?? 'System' }}</span>
                                <span class="comment-date">[{{ $cmt->created_at->format('Y-m-d H:i') }}]</span>:
                                <span class="comment-body">{{ $parsedComment['text'] }}</span>
                                
                                @if(!empty($parsedComment['screenshots']))
                                    <div class="comment-screenshots" style="margin-top: 5px;">
                                        @foreach($parsedComment['screenshots'] as $scrIdx => $scrSrc)
                                            <div class="image-item">
                                                <img src="{{ $scrSrc }}" class="task-image-thumbnail">
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
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
                                        <div style="font-size: 8px; color: #64748b; margin-top: 2px;">{{ $img->original_name }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </td>
            <td style="text-align: center; font-weight: bold;">
                @if($c->is_archived)
                    Archived
                @else
                    {{ $c->status ? $c->status->label() : 'To Do' }}
                @endif
            </td>
            <td>
                {{ $c->assignees->pluck('name')->join(', ') ?: 'Unassigned' }}
            </td>
            <td class="text-format">
                {{ $c->created_at ? $c->created_at->format('Y-m-d') : 'N/A' }}
            </td>
            <td class="text-format">
                {{ $c->due_at ? $c->due_at->format('Y-m-d') : 'None' }}
            </td>
            <td class="text-format">
                {{ $c->status === \App\Enums\CardStatus::Done || $c->status === \App\Enums\CardStatus::Approved ? ($c->updated_at ? $c->updated_at->format('Y-m-d') : 'Completed') : '-' }}
            </td>
            <td>
                @foreach($c->labels as $lbl)
                    <span class="tag-label" style="background-color: {{ $lbl->color }}20; color: {{ $lbl->color }}; border: 1px solid {{ $lbl->color }}40;">{{ $lbl->name }}</span>
                @endforeach
            </td>
        </tr>
        @endforeach
        @if($cards->isEmpty())
        <tr>
            <td colspan="7" style="text-align: center; color: #64748b; font-style: italic; padding: 20px 0;">No tasks found matching the selected filters.</td>
        </tr>
        @endif
    </table>
</body>
</html>
