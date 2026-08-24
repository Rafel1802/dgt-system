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
    <style type="text/css">
        .s0 { border: 1px solid #000000; background-color: #425d90; text-align: center; font-weight: bold; color: #ffffff; font-family: Arial; font-size: 14pt; padding: 4px; }
        .s1 { border: 1px solid #000000; background-color: #ffffff; text-align: left; font-weight: bold; color: #64748b; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        .s2 { border: 1px solid #000000; background-color: #ffffff; text-align: left; color: #0f172a; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        .s3 { border: 1px solid #000000; background-color: #435e91; text-align: left; font-weight: bold; color: #ffffff; font-family: Arial; font-size: 12pt; padding: 2px 4px; }
        .s4 { border: 1px solid #000000; background-color: #435e91; text-align: center; font-weight: bold; color: #ffffff; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        .s5 { border: 1px solid #000000; background-color: #435e91; text-align: left; font-weight: bold; color: #ffffff; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        
        .s6 { border: 1px solid #000000; background-color: #f8f8ff; text-align: center; font-weight: bold; color: #0f172a; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        .s7 { border: 1px solid #000000; background-color: #f8f8ff; text-align: center; font-weight: bold; color: #10b981; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        .s8 { border: 1px solid #000000; background-color: #f8f8ff; text-align: center; font-weight: bold; color: #f59e0b; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        .s9 { border: 1px solid #000000; background-color: #f8f8ff; text-align: center; font-weight: bold; color: #ef4444; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        .s10 { border: 1px solid #000000; background-color: #f8f8ff; text-align: left; color: #0f172a; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        
        .s11 { border: 1px solid #000000; background-color: #ffffff; text-align: center; font-weight: bold; color: #10b981; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        .s12 { border: 1px solid #000000; background-color: #ffffff; text-align: center; font-weight: bold; color: #f59e0b; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        .s13 { border: 1px solid #000000; background-color: #ffffff; text-align: center; font-weight: bold; color: #0f172a; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        
        .s14 { border: 1px solid #000000; background-color: #f8f8ff; text-align: left; font-weight: bold; color: #0f172a; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        .s15 { border: 1px solid #000000; background-color: #f8f8ff; text-align: right; color: #0f172a; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        
        .s17 { border: 1px solid #000000; background-color: #ffffff; text-align: left; font-weight: bold; color: #0f172a; font-family: Arial; font-size: 10pt; padding: 2px 4px; }
        .s18 { border: 1px solid #000000; background-color: #ffffff; text-align: right; color: #0f172a; font-family: Arial; font-size: 10pt; padding: 2px 4px; }

        table { border-collapse: collapse; }
    </style>
</head>
<body>
    <table cellspacing="0" cellpadding="0">
        <!-- Section 1: Header / Title -->
        <tr>
            <td class="s0" colspan="8" style="height: 40px; vertical-align: middle;">
                <img src="{{ url('images/kiuqlogo.png') }}" alt="Logo" style="height: 25px; vertical-align: middle; margin-right: 10px;">
                <span style="vertical-align: middle;">{{ $board ? $board->name : 'Personal Consolidated Report' }}</span>
            </td>
        </tr>
        <tr>
            <td class="s1">Workspace:</td>
            <td class="s2" colspan="7">{{ $board ? ($board->workspace->name ?? 'N/A') : 'Consolidated' }}</td>
        </tr>
        <tr>
            <td class="s1">Report Period:</td>
            <td class="s2" colspan="7">{{ $period }}</td>
        </tr>
        <tr>
            <td class="s1">Export Date:</td>
            <td class="s2" colspan="7">{{ $exportDate }}</td>
        </tr>
        <tr>
            <td colspan="8"></td>
        </tr>

        <!-- Section 2: KPI Summary -->
        <tr>
            <td class="s3" colspan="8">📊 KPI SUMMARY</td>
        </tr>
        <tr>
            <td class="s4">Total Tasks</td>
            <td class="s4">Completed</td>
            <td class="s4">Pending</td>
            <td class="s4">Overdue</td>
            <td class="s4">Errors</td>
            <td class="s5" colspan="3"></td>
        </tr>
        <tr>
            <td class="s6">{{ $totalTasks }}</td>
            <td class="s7">{{ $completedTasks }}</td>
            <td class="s8">{{ $pendingTasks }}</td>
            <td class="s9">{{ $overdueTasks }}</td>
            <td class="s9">{{ $errorTasks ?? 0 }}</td>
            <td class="s10" colspan="3"></td>
        </tr>
        <tr>
            <td colspan="8"></td>
        </tr>

        <!-- Section 3: Team Productivity Summary -->
        <tr>
            <td class="s3" colspan="8">📊 Team Productivity Summary</td>
        </tr>
        <tr>
            <td class="s5" colspan="3">Member Name</td>
            <td class="s4">Completed Tasks</td>
            <td class="s4">Pending Tasks</td>
            <td class="s4" colspan="3">Total Tasks</td>
        </tr>
        @php $rowNum = 0; @endphp
        @foreach($memberStats as $name => $stats)
        @php $rowNum++; @endphp
        <tr>
            <td class="{{ $rowNum % 2 == 0 ? 's2' : 's10' }}" colspan="3">{{ $name }}</td>
            <td class="{{ $rowNum % 2 == 0 ? 's11' : 's7' }}">{{ $stats['completed'] }}</td>
            <td class="{{ $rowNum % 2 == 0 ? 's12' : 's8' }}">{{ $stats['pending'] }}</td>
            <td class="{{ $rowNum % 2 == 0 ? 's13' : 's6' }}" colspan="3">{{ $stats['total'] }}</td>
        </tr>
        @endforeach
        @if(empty($memberStats))
        <tr>
            <td class="s10" colspan="8" style="text-align: center;">No member productivity details.</td>
        </tr>
        @endif
        <tr>
            <td colspan="8"></td>
        </tr>

        <!-- Section 4: Task Details -->
        <tr>
            <td class="s3" colspan="8">📋 Task Details</td>
        </tr>
        <tr>
            <td class="s5" x:autofilter="all">Class</td>
            <td class="s5" x:autofilter="all">Task / Title</td>
            <td class="s4" x:autofilter="all">Status</td>
            <td class="s5" x:autofilter="all">Assigned Members</td>
            <td class="s5" x:autofilter="all">Activity Date</td>
            <td class="s5" x:autofilter="all">Due Date</td>
            <td class="s5" x:autofilter="all">Completed Date</td>
            <td class="s5" x:autofilter="all">Labels</td>
        </tr>

        @php $taskRowNum = 0; @endphp
        @if(!empty($groupedCards))
            @foreach($groupedCards as $weekName => $cardsInWeek)
                @if($weekName !== 'Other')
                <tr>
                    <td class="s14" colspan="8" style="background-color: #f1f5f9; color: #475569; font-weight: bold; text-align: center;">
                        {{ strtoupper($weekName) }}
                    </td>
                </tr>
                @endif
                
                @foreach($cardsInWeek as $c)
                @php 
                    $taskRowNum++; 
                    $rowClassPrefix = $taskRowNum % 2 == 0 ? 's17' : 's14'; // Bold
                    $rowClassText = $taskRowNum % 2 == 0 ? 's2' : 's10'; // Normal
                    $rowClassDate = $taskRowNum % 2 == 0 ? 's18' : 's15'; // Right
                    $rowClassCenter = $taskRowNum % 2 == 0 ? 's13' : 's6'; // Center
                @endphp
                <tr>
                    <td class="{{ $rowClassText }}" style="background-color: {{ $c->smm_class_color }}; color: #fff; font-weight: bold; text-align: center;">
                        {{ $c->smm_class_label ?? '-' }}
                    </td>
                    <td class="{{ $rowClassPrefix }}">
                        {{ $c->title }}
                    </td>
                    <td class="{{ $rowClassCenter }}">
                        @if($c->is_archived)
                            Archived
                        @else
                            {{ $c->status ? $c->status->label() : 'To Do' }}
                        @endif
                    </td>
                    <td class="{{ $rowClassText }}">
                        {{ $c->assignees->pluck('name')->join(', ') ?: 'Unassigned' }}
                    </td>
                    <td class="{{ $rowClassDate }}" style="mso-number-format: '\@';">
                        {{ $c->computed_activity_date ? $c->computed_activity_date->format('Y-m-d H:i') : ($c->created_at ? $c->created_at->format('Y-m-d H:i') : 'N/A') }}
                    </td>
                    <td class="{{ $rowClassDate }}" style="mso-number-format: '\@';">
                        {{ $c->due_at ? $c->due_at->format('Y-m-d') : 'None' }}
                    </td>
                    <td class="{{ $rowClassDate }}" style="mso-number-format: '\@';">
                        {{ $c->exact_completed_date ?? '-' }}
                    </td>
                    <td class="{{ $rowClassText }}">
                        @foreach($c->labels as $lbl)
                            <span style="background-color: {{ $lbl->color }}; color: #000; padding: 2px 4px; font-weight: bold;">
                                {{ $lbl->name }}
                            </span>
                        @endforeach
                    </td>
                </tr>
                @endforeach
            @endforeach
        @else
            <tr>
                <td class="s10" colspan="8" style="text-align: center;">No tasks found matching the selected filters.</td>
            </tr>
        @endif
    </table>
</body>
</html>
