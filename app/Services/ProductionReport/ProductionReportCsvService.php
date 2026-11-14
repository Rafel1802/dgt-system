<?php

namespace App\Services\ProductionReport;

use App\Models\ProductionReport\ProductionReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ProductionReportCsvService
{
    /**
     * Generate a UTF-8 BOM CSV file for a single ProductionReport and store it in private storage.
     */
    public function generateReportCsv(ProductionReport $report): string
    {
        $report->loadMissing(['user', 'items', 'media.creator']);

        $relativeDir = "{$report->storage_folder}/exports";
        Storage::disk('local')->makeDirectory($relativeDir);

        $fileName = "{$report->report_code}.csv";
        $relativePath = "{$relativeDir}/{$fileName}";
        $fullPath = Storage::disk('local')->path($relativePath);

        $fp = fopen($fullPath, 'wb');
        // Write UTF-8 BOM for Excel compatibility
        fwrite($fp, "\xEF\xBB\xBF");

        fputcsv($fp, ['KIUQ SYSTEM - PRODUCTION REPORT EXPORT']);
        fputcsv($fp, ['Report Code', $report->report_code]);
        fputcsv($fp, ['Report Type', strtoupper($report->report_type)]);
        fputcsv($fp, ['Title', $report->title]);
        fputcsv($fp, ['Team', $report->team_name ?: ('Team ' . ($report->team_code ?: 'N/A'))]);
        fputcsv($fp, ['Author / Prepared By', $report->user?->name ?? 'N/A']);
        fputcsv($fp, ['Reporting Period', $report->period_label]);
        fputcsv($fp, ['Status', $report->status_badge_label]);
        fputcsv($fp, ['Late Submission', $report->is_late ? 'Yes' : 'No']);
        fputcsv($fp, ['Submitted At (Cambodia)', $report->submitted_at ? $report->submitted_at->timezone('Asia/Phnom_Penh')->format('Y-m-d H:i:s') : 'Draft']);
        fputcsv($fp, ['Performance Rating', $report->rating_display]);
        fputcsv($fp, []);

        fputcsv($fp, ['SECTION', 'CONTENT']);
        fputcsv($fp, ['Executive Summary', (string) $report->executive_summary]);
        fputcsv($fp, ['Tasks Completed', (string) $report->tasks_completed]);
        fputcsv($fp, ['Work in Progress', (string) $report->work_in_progress]);
        fputcsv($fp, ['Pending Tasks', (string) $report->pending_tasks]);
        fputcsv($fp, ['Challenges / Blockers', (string) $report->blockers]);
        fputcsv($fp, ['Key Achievements', (string) $report->achievements]);
        fputcsv($fp, ['Next Priorities / Goals', (string) $report->next_priorities]);
        fputcsv($fp, ['Head Comments', (string) $report->head_comments]);
        fputcsv($fp, ['Supervisor Comments', (string) $report->supervisor_comments]);
        fputcsv($fp, ['Notes', (string) $report->notes]);
        fputcsv($fp, []);

        $summary = is_array($report->compiled_summary_json) ? $report->compiled_summary_json : [];
        if (!empty($summary['member_summary_table'])) {
            fputcsv($fp, ['INDIVIDUAL MEMBER SUMMARY TABLE']);
            fputcsv($fp, ['Member Name', 'Role', 'Submitted Days', 'Expected Days', 'Missing Days', 'Completed Tasks Count', 'Completed Summary', 'Work in Progress', 'Pending Tasks', 'Key Achievements']);
            foreach ($summary['member_summary_table'] as $row) {
                fputcsv($fp, [
                    $row['member_name'] ?? '',
                    $row['role_title'] ?? '',
                    $row['submitted_days'] ?? 0,
                    $row['expected_days'] ?? 0,
                    $row['missing_days'] ?? 0,
                    $row['completed_tasks_count'] ?? 0,
                    $row['completed_tasks_summary'] ?? '',
                    $row['work_in_progress'] ?? '',
                    $row['pending_tasks'] ?? '',
                    $row['key_achievements'] ?? '',
                ]);
            }
            fputcsv($fp, []);
        }

        if ($report->items->count() > 0) {
            fputcsv($fp, ['MEASURABLE TASK ITEMS']);
            fputcsv($fp, ['Task Title', 'Description', 'Category', 'Quantity', 'Unit', 'Status', 'Completion Date', 'External Link']);
            foreach ($report->items as $item) {
                fputcsv($fp, [
                    $item->task_title,
                    $item->task_description,
                    $item->task_category,
                    $item->quantity,
                    $item->unit,
                    $item->status,
                    $item->completion_date?->toDateString(),
                    $item->external_link,
                ]);
            }
            fputcsv($fp, []);
        }

        if ($report->media->count() > 0) {
            fputcsv($fp, ['MEDIA & DELIVERABLE ATTACHMENTS']);
            fputcsv($fp, ['Title', 'Media Type', 'Creator', 'Category', 'Platform', 'Production Date', 'Highlighted', 'File / URL']);
            foreach ($report->media as $media) {
                fputcsv($fp, [
                    $media->title,
                    $media->media_type,
                    $media->creator?->name ?? $report->user?->name ?? '',
                    $media->project_category,
                    $media->platform,
                    $media->production_date?->toDateString(),
                    $media->is_highlighted ? 'Yes' : 'No',
                    $media->external_url ?: $media->original_filename,
                ]);
            }
        }

        fclose($fp);

        $report->update([
            'csv_path' => $relativePath,
            'last_exported_at' => now('Asia/Phnom_Penh'),
        ]);

        return $fullPath;
    }

    /**
     * Generate a UTF-8 BOM CSV stream string for a collection of reports (e.g., Archive / Date Range export).
     */
    public function generateCollectionCsvContent(Collection $reports): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Report Code',
            'Report Type',
            'Title',
            'Team Code',
            'Team Name',
            'Prepared By',
            'Period Start',
            'Period End',
            'Status',
            'Late',
            'Performance Rating',
            'Submitted At (Cambodia)',
            'Tasks Completed',
            'Work in Progress',
            'Pending Tasks',
            'Blockers',
            'Achievements',
            'Media Count',
        ]);

        foreach ($reports as $r) {
            fputcsv($handle, [
                $r->report_code,
                strtoupper($r->report_type),
                $r->title,
                $r->team_code,
                $r->team_name,
                $r->user?->name ?? 'N/A',
                $r->reporting_start_date?->toDateString(),
                $r->reporting_end_date?->toDateString(),
                $r->status_badge_label,
                $r->is_late ? 'Yes' : 'No',
                $r->performance_rating ? "{$r->performance_rating}/5 ({$r->performance_rating_label})" : 'N/A',
                $r->submitted_at ? $r->submitted_at->timezone('Asia/Phnom_Penh')->format('Y-m-d H:i:s') : '',
                $r->tasks_completed,
                $r->work_in_progress,
                $r->pending_tasks,
                $r->blockers,
                $r->achievements,
                $r->media_count ?? $r->media()->count(),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /**
     * Write collection CSV content to a stored file on disk and return its full system path.
     */
    public function generateCollectionCsvFile(Collection $reports, string $fileName = 'production-reports-export.csv'): string
    {
        $relativeDir = 'production-reports/exports';
        Storage::disk('local')->makeDirectory($relativeDir);
        $relativePath = "{$relativeDir}/{$fileName}";
        $fullPath = Storage::disk('local')->path($relativePath);

        $content = $this->generateCollectionCsvContent($reports);
        file_put_contents($fullPath, $content);

        return $fullPath;
    }
}

