<?php

namespace App\Services\Ai;

use App\Models\Ai\AiReport;
use App\Models\Card;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsitePost;
use App\Models\WebsiteQcCheck;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KiuqAiReportService
{
    public function __construct(
        private readonly GeminiClientService $geminiClient
    ) {}

    /**
     * Compute real database metrics for a given date range and scope.
     */
    public function computeMetrics(string $category, Carbon $startDate, Carbon $endDate, array $filters = [], ?User $user = null): array
    {
        $startStr = $startDate->copy()->startOfDay()->toDateTimeString();
        $endStr   = $endDate->copy()->endOfDay()->toDateTimeString();

        // 1. Blog Metrics (WebsitePost)
        $blogsQuery = WebsitePost::whereBetween('created_at', [$startStr, $endStr]);
        if (!empty($filters['website_id'])) {
            $blogsQuery->where('website_id', $filters['website_id']);
        }
        $blogsTotal     = (clone $blogsQuery)->count();
        $blogsPublished = (clone $blogsQuery)->where('status', WebsitePost::STATUS_PUBLISHED)->count();
        $blogsDraft     = (clone $blogsQuery)->where('status', WebsitePost::STATUS_DRAFT)->count();
        $blogsScheduled = (clone $blogsQuery)->where('status', WebsitePost::STATUS_SCHEDULED)->count();

        // 2. Task Cards Metrics (Card)
        $cardsQuery = Card::whereBetween('created_at', [$startStr, $endStr]);
        if (!empty($filters['team'])) {
            $cardsQuery->where('team', $filters['team']);
        }
        if (!empty($filters['board_id'])) {
            $cardsQuery->whereHas('boardList', function ($l) use ($filters) {
                $l->where('board_id', $filters['board_id']);
            });
        } elseif (!empty($filters['board_name'])) {
            $cardsQuery->whereHas('boardList.board', function ($b) use ($filters) {
                $b->where('name', 'like', '%' . $filters['board_name'] . '%');
            });
        }
        $cardsCreated = (clone $cardsQuery)->count();

        // Completed cards: cards that have 'completed' or 'done' in their list name or are archived
        $cardsCompleted = (clone $cardsQuery)->where(function ($q) {
            $q->whereHas('boardList', function ($l) {
                $l->where('name', 'like', '%Done%')
                  ->orWhere('name', 'like', '%Complete%')
                  ->orWhere('name', 'like', '%Approved%');
            })->orWhere('is_archived', true);
        })->count();

        $cardsPending = max(0, $cardsCreated - $cardsCompleted);

        // 3. Website QC Metrics
        $qcQuery = WebsiteQcCheck::whereBetween('updated_at', [$startStr, $endStr]);
        $qcTotal  = (clone $qcQuery)->count();
        $qcPassed = (clone $qcQuery)->where('is_checked', true)->count();
        $qcErrors = (clone $qcQuery)->where('is_checked', false)->count();

        $activeWebsites = Website::where(function ($q) {
            $q->where('is_archived', false)->orWhereNull('is_archived');
        })->count();

        $websitesLive = Website::where('status', Website::STATUS_LIVE)->count();

        // Details breakdown table
        $details = [
            ['label' => 'Total Blog Posts Published', 'count' => $blogsPublished, 'note' => 'Live across connected CMS domains'],
            ['label' => 'Blog Drafts in Progress', 'count' => $blogsDraft, 'note' => 'Pending review or SEO polish'],
            ['label' => 'Scheduled Blog Posts', 'count' => $blogsScheduled, 'note' => 'Queued for future release'],
            ['label' => 'New Task Cards Created', 'count' => $cardsCreated, 'note' => 'Workflow tracking cards'],
            ['label' => 'Tasks Completed & Verified', 'count' => $cardsCompleted, 'note' => 'Successfully finished in target lists'],
            ['label' => 'Tasks In-Progress / Pending', 'count' => $cardsPending, 'note' => 'Active team workloads'],
            ['label' => 'Website Quality Checks Passed', 'count' => $qcPassed, 'note' => 'Checklist items verified'],
            ['label' => 'Total Active Websites', 'count' => $activeWebsites, 'note' => "{$websitesLive} currently in Live status"],
        ];

        // Assignee / Member breakdown
        try {
            $cardsWithAssignees = (clone $cardsQuery)->with('assignees')->get();
            $memberCounts = [];
            foreach ($cardsWithAssignees as $card) {
                foreach ($card->assignees as $member) {
                    $memberName = $member->name ?: 'Member #' . $member->id;
                    $memberCounts[$memberName] = ($memberCounts[$memberName] ?? 0) + 1;
                }
            }
            arsort($memberCounts);
            foreach (array_slice($memberCounts, 0, 10, true) as $mName => $mCount) {
                $details[] = [
                    'label' => "Member: {$mName}",
                    'count' => $mCount,
                    'note'  => "Active tasks assigned",
                ];
            }
        } catch (\Throwable) {}

        return [
            'period_from'      => $startDate->format('Y-m-d'),
            'period_to'        => $endDate->format('Y-m-d'),
            'blogs_total'      => $blogsTotal,
            'blogs_published'  => $blogsPublished,
            'blogs_draft'      => $blogsDraft,
            'blogs_scheduled'  => $blogsScheduled,
            'cards_created'    => $cardsCreated,
            'cards_completed'  => $cardsCompleted,
            'cards_pending'    => $cardsPending,
            'qc_checks_total'  => $qcTotal,
            'qc_checks_passed' => $qcPassed,
            'qc_checks_errors' => $qcErrors,
            'active_websites'  => $activeWebsites,
            'websites_live'    => $websitesLive,
            'details'          => $details,
        ];
    }

    /**
     * Request Gemini to synthesize verified numbers into executive insights.
     */
    public function synthesizeAnalysis(array $stats, string $category, Carbon $startDate, Carbon $endDate, ?string $instructions = null): string
    {
        $prompt = <<<EOT
You are an executive operational analyst for the DGT System.
Analyze the following VERIFIED database statistics for the period {$startDate->format('M d, Y')} to {$endDate->format('M d, Y')}.

CRITICAL: DO NOT INVENT NUMBERS. Use only the provided verified metrics:
- Blogs Total: {$stats['blogs_total']} (Published: {$stats['blogs_published']}, Draft: {$stats['blogs_draft']}, Scheduled: {$stats['blogs_scheduled']})
- Task Cards Created: {$stats['cards_created']}, Completed: {$stats['cards_completed']}, In-Progress: {$stats['cards_pending']}
- Website QC Checks Passed: {$stats['qc_checks_passed']}, Pending/Errors: {$stats['qc_checks_errors']}
- Active Websites: {$stats['active_websites']} (Live: {$stats['websites_live']})

User Additional Instructions: {$instructions}

Please provide:
1. Executive Summary: High-level overview of overall production volume and efficiency.
2. Key Operational Highlights: Standout accomplishments across content, task completion, and website readiness.
3. Bottlenecks & Quality Risks: Analysis of pending tasks, drafts, or QC discrepancies.
4. Strategic Recommendations: 3-4 specific, actionable steps for the upcoming cycle.

Tone: Professional, direct, authoritative corporate report.
EOT;

        if (!$this->geminiClient->isConfigured()) {
            return "Executive Analysis Summary:\nDuring the period from {$startDate->format('M d, Y')} to {$endDate->format('M d, Y')}, the department completed {$stats['cards_completed']} tasks and published {$stats['blogs_published']} blog articles. A total of {$stats['qc_checks_passed']} website quality checks were passed across {$stats['active_websites']} active sites.\n\nRecommendations:\n- Maintain current publishing schedule.\n- Address the remaining {$stats['cards_pending']} in-progress tasks.";
        }

        try {
            $response = $this->geminiClient->generateContent([
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ]);
            return $response['text'] ?? 'Analysis generation completed.';
        } catch (Exception $e) {
            return "Executive Summary:\nProduction analysis for {$startDate->format('M d, Y')} to {$endDate->format('M d, Y')}: Total {$stats['blogs_published']} blogs published, {$stats['cards_completed']} tasks completed, and {$stats['qc_checks_passed']} QC items approved.";
        }
    }

    /**
     * Create an official report record and compile PDF & CSV files.
     */
    public function generateReport(string $title, string $category, Carbon $startDate, Carbon $endDate, array $filters = [], ?string $instructions = null, ?User $user = null): AiReport
    {
        $stats = $this->computeMetrics($category, $startDate, $endDate, $filters, $user);
        $analysis = $this->synthesizeAnalysis($stats, $category, $startDate, $endDate, $instructions);

        $report = AiReport::create([
            'user_id'       => $user?->id ?? 1,
            'title'         => $title ?: ('Production Report - ' . $startDate->format('M Y')),
            'category'      => $category,
            'date_from'     => $startDate->toDateString(),
            'date_to'       => $endDate->toDateString(),
            'filters'       => $filters,
            'stats_data'    => $stats,
            'analysis_text' => $analysis,
        ]);

        // Generate PDF and CSV files
        $this->compilePdf($report);
        $this->compileCsv($report);

        return $report->fresh();
    }

    /**
     * Compile DomPDF document and store in public storage.
     */
    public function compilePdf(AiReport $report): string
    {
        Storage::disk('public')->makeDirectory('ai_reports');
        $filename = "ai_reports/report_{$report->id}_" . Str::slug($report->title) . ".pdf";

        $pdf = Pdf::loadView('reports.ai.pdf', compact('report'));
        $pdf->setPaper('a4', 'portrait');

        Storage::disk('public')->put($filename, $pdf->output());

        $report->update(['file_pdf_path' => $filename]);
        return $filename;
    }

    /**
     * Compile CSV file and store in public storage.
     */
    public function compileCsv(AiReport $report): string
    {
        Storage::disk('public')->makeDirectory('ai_reports');
        $filename = "ai_reports/report_{$report->id}_" . Str::slug($report->title) . ".csv";

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['KIUQ AI Executive Production Report', $report->title]);
        fputcsv($handle, ['Period', $report->date_from->format('Y-m-d') . ' to ' . $report->date_to->format('Y-m-d')]);
        fputcsv($handle, ['Category', $report->category]);
        fputcsv($handle, ['Generated At', $report->created_at->toDateTimeString()]);
        fputcsv($handle, []);

        fputcsv($handle, ['Metric Label', 'Count', 'Notes']);
        foreach ($report->stats_data['details'] ?? [] as $detail) {
            fputcsv($handle, [$detail['label'] ?? '', $detail['count'] ?? 0, $detail['note'] ?? '']);
        }

        fputcsv($handle, []);
        fputcsv($handle, ['Executive Analysis & Insights']);
        fputcsv($handle, [$report->analysis_text]);

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('public')->put($filename, $csvContent);

        $report->update(['file_csv_path' => $filename]);
        return $filename;
    }
}
