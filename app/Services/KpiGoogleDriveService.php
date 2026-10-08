<?php

namespace App\Services;

use App\Models\Kpi\KpiReview;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KpiGoogleDriveService
{
    /**
     * Target Parent Folder ID on Google Drive:
     * https://drive.google.com/drive/folders/1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi
     */
    public const DEFAULT_PARENT_FOLDER_ID = '1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi';

    /**
     * Synchronize a Staff KPI Review PDF to Google Drive via Google Apps Script Web App.
     *
     * Creates / finds the Year folder (e.g. 2026, 2027, 2028),
     * creates / finds the Month folder (e.g. September),
     * and saves/updates the PDF with the format: "[Staff Name] - KPI [Month] [Year].pdf"
     *
     * @param KpiReview $review
     * @param string|null $pdfContent Binary content of the PDF if already rendered
     * @return array{success: bool, configured: bool, message: string, file_url?: string, file_id?: string, file_name?: string}
     */
    public function syncReviewPdf(KpiReview $review, ?string $pdfContent = null): array
    {
        $review->loadMissing(['user.kpiSquads', 'reviewer', 'squad.lead', 'period']);

        // 1. Generate PDF if not provided
        if (empty($pdfContent)) {
            try {
                $pdf = Pdf::loadView('kpi.staff-pdf', compact('review'));
                $pdfContent = $pdf->output();
            } catch (\Throwable $e) {
                Log::error('KpiGoogleDriveService: Failed to generate PDF for review ' . $review->id . ': ' . $e->getMessage());
                return [
                    'success' => false,
                    'configured' => true,
                    'message' => 'Failed to render PDF: ' . $e->getMessage(),
                ];
            }
        }

        // 2. Save local storage backup copy
        try {
            $localFilename = 'kpi_' . $review->id . '_' . time() . '.pdf';
            Storage::disk('public')->put('kpi-uploads/' . $localFilename, $pdfContent);
            $review->update(['uploaded_pdf_path' => 'storage/kpi-uploads/' . $localFilename]);
        } catch (\Throwable $e) {
            Log::warning('KpiGoogleDriveService: Could not save local backup: ' . $e->getMessage());
        }

        // 3. Determine Year, Month, Staff Name and File Name
        $date = $review->evaluation_date ? Carbon::parse($review->evaluation_date) : ($review->created_at ?: Carbon::now());

        $year = (string)$date->format('Y');
        $monthName = $date->format('F');

        if ($review->period && $review->period->name) {
            if (preg_match('/\b(20\d\d)\b/', $review->period->name, $matches)) {
                $year = (string)$matches[1];
            }
            if (preg_match('/^(January|February|March|April|May|June|July|August|September|October|November|December)/i', $review->period->name, $matches)) {
                $monthName = ucfirst(strtolower($matches[1]));
            }
        }

        $staffName = $review->user ? $review->user->name : 'Staff';
        // Clean special characters for file system safety
        $safeStaffName = trim(preg_replace('/[\\/\\\\]+/', ' ', $staffName));
        $fileName = "{$safeStaffName} - KPI {$monthName} {$year}.pdf";

        // 4. Check Google Apps Script configuration
        $scriptUrl = config('services.google_kpi.apps_script_url') ?: env('GOOGLE_KPI_APPS_SCRIPT_URL');
        $folderId = config('services.google_kpi.drive_folder_id') ?: env('GOOGLE_KPI_DRIVE_FOLDER_ID', self::DEFAULT_PARENT_FOLDER_ID);
        $secret = config('services.google_kpi.api_secret') ?: env('GOOGLE_KPI_API_SECRET', 'kpi-drive-sync-secret-2026');

        if (empty($scriptUrl)) {
            Log::info("KpiGoogleDriveService: GOOGLE_KPI_APPS_SCRIPT_URL is not set. Saved locally as {$fileName}.");
            return [
                'success' => false,
                'configured' => false,
                'file_name' => $fileName,
                'message' => "PDF saved locally. Google Apps Script Web App URL is not configured yet in .env (GOOGLE_KPI_APPS_SCRIPT_URL).",
            ];
        }

        // 5. Send to Google Apps Script Webhook
        $payload = [
            'secret' => $secret,
            'folder_id' => $folderId,
            'year' => (string)$year,
            'month' => $monthName,
            'file_name' => $fileName,
            'file_data' => base64_encode($pdfContent),
            'mime_type' => 'application/pdf',
            'staff_name' => $staffName,
            'overall_kpi' => $review->overall_kpi,
            'performance_band' => $review->performance_band,
        ];

        try {
            $client = Http::timeout(30)
                ->connectTimeout(8)
                ->withHeaders(['Accept' => 'application/json']);

            if (app()->environment('local', 'testing')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($scriptUrl, $payload);

            if ($response->successful()) {
                $json = $response->json();
                $fileUrl = $json['file_url'] ?? $json['fileUrl'] ?? null;
                $fileId = $json['file_id'] ?? $json['fileId'] ?? null;

                if ($fileUrl || $fileId) {
                    $review->update([
                        'google_drive_url' => $fileUrl,
                        'google_drive_file_id' => $fileId,
                        'google_drive_synced_at' => now(),
                    ]);

                    Log::info("KpiGoogleDriveService: Successfully uploaded {$fileName} to Google Drive folder {$year}/{$monthName}.", [
                        'file_url' => $fileUrl,
                        'file_id' => $fileId,
                    ]);

                    return [
                        'success' => true,
                        'configured' => true,
                        'file_url' => $fileUrl,
                        'file_id' => $fileId,
                        'file_name' => $fileName,
                        'message' => "Successfully synced to Google Drive in folder {$year}/{$monthName}!",
                    ];
                }

                $errMsg = $json['message'] ?? 'Unexpected response format from Google Apps Script.';
                Log::warning("KpiGoogleDriveService: Google script returned: {$errMsg}");
                return [
                    'success' => false,
                    'configured' => true,
                    'file_name' => $fileName,
                    'message' => "Google Drive response: {$errMsg}",
                ];
            }

            $status = $response->status();
            $body = $response->body();
            Log::error("KpiGoogleDriveService: HTTP error {$status} from Google Apps Script.");

            if ($status === 403 || str_contains($body, 'accounts.google.com') || str_contains($body, 'request-access-icon')) {
                return [
                    'success' => false,
                    'configured' => true,
                    'file_name' => $fileName,
                    'message' => "Google Apps Script 403 Forbidden: In script.google.com, click Deploy > Manage deployments > Edit > set 'Who has access' to 'Anyone' and click Deploy.",
                ];
            }

            return [
                'success' => false,
                'configured' => true,
                'file_name' => $fileName,
                'message' => "Google Apps Script returned HTTP {$status}.",
            ];

        } catch (\Throwable $e) {
            Log::error('KpiGoogleDriveService: Connection exception: ' . $e->getMessage());
            return [
                'success' => false,
                'configured' => true,
                'file_name' => $fileName,
                'message' => 'Could not connect to Google Drive service: ' . $e->getMessage(),
            ];
        }
    }
}
