<?php

namespace App\Services\ProductionReport;

use App\Models\ProductionReport\ProductionReport;
use App\Models\ProductionReport\ProductionReportMedia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ProductionReportPdfService
{
    public function generatePdf(ProductionReport $report): string
    {
        $report->loadMissing(['user', 'items', 'media.creator', 'revisions.user']);

        $relativeDir = "{$report->storage_folder}/exports";
        Storage::disk('local')->makeDirectory($relativeDir);

        $fileName = "{$report->report_code}.pdf";
        $relativePath = "{$relativeDir}/{$fileName}";
        $fullPath = Storage::disk('local')->path($relativePath);

        // Prepare base64 previews for local image attachments so DomPDF renders them reliably offline
        $posterDataUris = [];
        foreach ($report->media as $mediaItem) {
            if ($mediaItem->isImage() && $mediaItem->file_path && Storage::disk($mediaItem->disk ?: 'local')->exists($mediaItem->file_path)) {
                try {
                    $bytes = Storage::disk($mediaItem->disk ?: 'local')->get($mediaItem->file_path);
                    if (strlen($bytes) <= 4 * 1024 * 1024) {
                        $mime = $mediaItem->mime_type ?: 'image/png';
                        $posterDataUris[$mediaItem->id] = 'data:' . $mime . ';base64,' . base64_encode($bytes);
                    }
                } catch (\Throwable) {
                    // Skip if unreadable
                }
            }
        }

        $pdf = Pdf::loadView('production-reports.pdf.report', [
            'report' => $report,
            'summary' => is_array($report->compiled_summary_json) ? $report->compiled_summary_json : [],
            'posterDataUris' => $posterDataUris,
        ])->setPaper('a4', 'portrait');

        file_put_contents($fullPath, $pdf->output());

        $report->update([
            'pdf_path' => $relativePath,
            'last_exported_at' => now('Asia/Phnom_Penh'),
        ]);

        return $fullPath;
    }

    /**
     * Compile multiple production reports into a single consolidated PDF document.
     */
    public function generateCombinedPdf(
        \Illuminate\Support\Collection $reports,
        string $title,
        ?string $subtitle = null,
        array $meta = []
    ): string {
        $reports->loadMissing(['user', 'items', 'media.creator']);

        $relativeDir = 'production-reports/exports';
        Storage::disk('local')->makeDirectory($relativeDir);

        $cleanTitle = \Illuminate\Support\Str::slug($title, '-');
        $timestamp = now('Asia/Phnom_Penh')->format('Ymd-His');
        $fileName = "{$cleanTitle}-{$timestamp}.pdf";
        $relativePath = "{$relativeDir}/{$fileName}";
        $fullPath = Storage::disk('local')->path($relativePath);

        $pdf = Pdf::loadView('production-reports.pdf.combined', [
            'reports' => $reports,
            'title' => $title,
            'subtitle' => $subtitle,
            'meta' => $meta,
        ])->setPaper('a4', 'portrait');

        file_put_contents($fullPath, $pdf->output());

        return $fullPath;
    }
}

