@extends('layouts.app')

@section('title', 'Website Report Preview')

@section('content')
<div class="w-full h-[calc(100vh-100px)] flex flex-col p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Report Preview</h1>
            <p class="text-sm text-slate-500">
                @if($startDate || $endDate)
                    Date Range: {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d M Y') : 'Beginning' }} to {{ $endDate ? \Carbon\Carbon::parse($endDate)->format('d M Y') : 'Today' }}
                @else
                    All Time
                @endif
                &nbsp;|&nbsp; Total Records: {{ isset($followUps) ? $followUps->count() : (isset($websites) ? $websites->count() : 0) }}
            </p>
            @if(isset($qcStats))
            <div class="flex gap-4 mt-3">
                <div class="bg-indigo-50 border border-indigo-100 rounded px-3 py-1 text-sm"><span class="font-bold text-indigo-700">{{ $qcStats['checked'] }}</span> Checked</div>
                <div class="bg-emerald-50 border border-emerald-100 rounded px-3 py-1 text-sm"><span class="font-bold text-emerald-700">{{ $qcStats['approved'] }}</span> Approved</div>
                <div class="bg-rose-50 border border-rose-100 rounded px-3 py-1 text-sm"><span class="font-bold text-rose-700">{{ $qcStats['error'] }}</span> Errors</div>
                <div class="bg-amber-50 border border-amber-100 rounded px-3 py-1 text-sm"><span class="font-bold text-amber-700">{{ $qcStats['comment'] }}</span> Comments</div>
            </div>
            @endif
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="copyReportText()" class="btn btn-primary flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                <span id="copyBtnText">Copy Report</span>
            </button>

            @php
                // Build the base download URL
                $downloadUrl = route('websites.export') . '?' . http_build_query([
                    'tab' => $tab,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'member_id' => $memberId,
                    'download' => 1
                ]);
            @endphp

            <button type="button" onclick="document.getElementById('reportIframe').contentWindow.print()" class="btn btn-secondary flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                Print / Save PDF
            </button>
            
            <a href="{{ $downloadUrl }}&format=csv" class="btn btn-secondary flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M3 16.5l6-6 4 4 8-8"/></svg>
                Download CSV
            </a>
        </div>
    </div>

    @php
        $reportText = "";
        $count = 1;

        if ($tab === 'follow-up' && isset($followUps)) {
            foreach($followUps as $item) {
                $websiteName = $item->website->name;
                $className = $item->website->category ?? 'Unknown';
                $url = $item->url;
                $handleBy = $item->assignee ? $item->assignee->name : 'Unassigned';
                $date = $item->created_at->format('d M Y');
                $reportText .= "{$count}. {$websiteName} ({$className}) : {$url} , Handle by: {$handleBy} , Date: {$date}\n";
                $count++;
            }
        } elseif (isset($websites)) {
            foreach($websites as $ws) {
                $reportText .= $count . ". " . $ws->name . " (" . $ws->status . ")\n";

                // Collect logs for this date range
                $allProgressLogs = collect();
                if ($filterStart || $filterEnd) {
                     $buildLogs = $ws->progressLogs->filter(fn($l) => $l->created_at >= $filterStart && $l->created_at <= $filterEnd);
                     $maintLogs = $ws->maintenanceLogs->filter(fn($l) => $l->created_at >= $filterStart && $l->created_at <= $filterEnd);
                     $allProgressLogs = $buildLogs->concat($maintLogs);
                } else {
                     $allProgressLogs = $ws->progressLogs->concat($ws->maintenanceLogs);
                }

                if ($allProgressLogs->count() > 0) {
                    foreach($allProgressLogs->sortBy('created_at') as $log) {
                        $type = $log->type === 'maintenance' ? 'Maintenance Progress' : 'Build Progress';
                        $note = strip_tags($log->note);
                        $reportText .= "   - " . $type . ": " . $log->percent . "%" . ($note ? " (" . $note . ")" : "") . "\n";
                    }
                }

                // Check if there are active errors in the date range
                if (str_contains(strtolower($ws->status), 'error')) {
                     if ($ws->error_note) {
                         $reportText .= "   - Error/Issue: " . strip_tags($ws->error_note) . "\n";
                     }
                }

                $reportText .= "\n";
                $count++;
            }
        }
    @endphp

    <div class="card border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm mt-6 w-full flex-1 flex">
        <iframe id="reportIframe" src="{{ route('websites.export') . '?' . http_build_query([
            'tab' => $tab,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'member_id' => $memberId,
            'format' => 'html',
            'download' => 1
        ]) }}" class="w-full h-full bg-slate-100 flex-1" style="border:none;"></iframe>
    </div>
    <textarea id="reportTextContent" class="hidden" readonly>{{ trim($reportText) }}</textarea>
</div>

<script>
    function copyReportText() {
        const textarea = document.getElementById('reportTextContent');
        
        navigator.clipboard.writeText(textarea.value).then(() => {
            const btnText = document.getElementById('copyBtnText');
            const originalText = btnText.innerText;
            btnText.innerText = "Copied!";
            setTimeout(() => {
                btnText.innerText = originalText;
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy text: ', err);
            alert("Failed to copy text. Your browser may not support clipboard operations.");
        });
    }
</script>
@endsection
