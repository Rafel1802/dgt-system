<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends blog follow-up data to Google Sheets via Google Apps Script Web App.
 *
 * Security model:
 *  - Apps Script URL stored in GOOGLE_BLOGS_APPS_SCRIPT_URL env var (never exposed to JS/frontend)
 *  - Shared secret stored in GOOGLE_BLOGS_API_SECRET env var
 *  - Domain/website extracted server-side via parse_url() — browser input is ignored
 *  - Doc Link is NEVER touched by this service
 */
class GoogleBlogsSheetService
{
    /** Column mapping per class number. Configured here; Apps Script uses the same map. */
    public const CLASS_BLOCKS = [
        '1' => ['class' => 'O', 'doc' => 'P', 'public' => 'Q', 'date' => 'R', 'website' => 'S'],
        '2' => ['class' => 'H', 'doc' => 'I', 'public' => 'J', 'date' => 'K', 'website' => 'L'],
        '3' => ['class' => 'A', 'doc' => 'B', 'public' => 'C', 'date' => 'D', 'website' => 'E'],
        '4' => ['class' => 'A', 'doc' => 'B', 'public' => 'C', 'date' => 'D', 'website' => 'E'],
        '5' => ['class' => 'V', 'doc' => 'W', 'public' => 'X', 'date' => 'Y', 'website' => 'Z'],
        '6' => ['class' => 'AC', 'doc' => 'AD', 'public' => 'AE', 'date' => 'AF', 'website' => 'AG'],
        '7' => ['class' => 'AJ', 'doc' => 'AK', 'public' => 'AL', 'date' => 'AM', 'website' => 'AN'],
    ];

    /**
     * Check if a class number is configured.
     */
    public function isClassSupported(string $classNumber): bool
    {
        return array_key_exists($classNumber, self::CLASS_BLOCKS);
    }

    /**
     * Extract domain from a public blog URL (server-side only).
     * Example: https://wheelloaders.org/some-article/ → wheelloaders.org
     */
    public function extractDomain(string $url): string
    {
        $host = parse_url(trim($url), PHP_URL_HOST) ?? '';
        // Strip leading "www."
        return preg_replace('/^www\./i', '', $host);
    }

    /**
     * Push a blog entry to the Google Sheet via Apps Script.
     *
     * @param string $classNumber  e.g. "2"
     * @param string $publicLink   The full public URL of the blog post
     * @param string $date         Date in d/m format (e.g. "27/08")
     * @param string $idempotencyKey Unique key to prevent double-writes on retry
     *
     * @return array{success: bool, message: string, row?: int, duplicate?: bool, error?: string}
     */
    public function pushBlog(
        string $classNumber,
        string $publicLink,
        string $date,
        string $websiteName,
        string $idempotencyKey = ''
    ): array {
        $scriptUrl = config('services.google_blogs.apps_script_url');
        $secret    = config('services.google_blogs.api_secret');

        if (empty($scriptUrl)) {
            Log::warning('GoogleBlogsSheetService: GOOGLE_BLOGS_APPS_SCRIPT_URL is not configured.');
            return ['success' => false, 'error' => 'Google Blogs Sheet is not configured on this server.'];
        }

        $payload = [
            'secret'           => $secret,
            'class'            => $classNumber,
            'public_link'      => $publicLink,
            'date'             => $date,
            'website'          => $websiteName,
            'idempotency_key'  => $idempotencyKey,
        ];

        Log::info('GoogleBlogsSheetService: Sending blog to sheet.', [
            'class'   => $classNumber,
            'website' => $websiteName,
            'date'    => $date,
        ]);

        try {
            $client = Http::timeout(25)
                ->connectTimeout(5)
                ->withHeaders(['Accept' => 'application/json']);

            if (app()->environment('local', 'testing')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($scriptUrl, $payload);

            $json = $response->json();

            if ($response->successful() && isset($json['success']) && $json['success'] === true) {
                Log::info('GoogleBlogsSheetService: Blog synced successfully.', [
                    'class' => $classNumber,
                    'row'   => $json['row'] ?? null,
                ]);
                return [
                    'success' => true,
                    'message' => $json['message'] ?? 'Blog added to Google Sheet.',
                    'row'     => $json['row'] ?? null,
                ];
            }

            // Duplicate detection from Apps Script
            if (!empty($json['duplicate'])) {
                Log::info('GoogleBlogsSheetService: Duplicate blog detected in sheet.', [
                    'class'       => $classNumber,
                    'public_link' => $publicLink,
                ]);
                return [
                    'success'   => true,  // treat as success — it's already there
                    'duplicate' => true,
                    'message'   => $json['message'] ?? 'This blog link already exists in the schedule.',
                    'row'       => $json['row'] ?? null,
                ];
            }

            $errorMessage = $json['message'] ?? ('HTTP ' . $response->status() . ' from Apps Script.');
            Log::warning('GoogleBlogsSheetService: Apps Script returned failure.', [
                'status'  => $response->status(),
                'message' => $errorMessage,
            ]);
            return ['success' => false, 'error' => $errorMessage];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('GoogleBlogsSheetService: Connection failed.', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Could not connect to Google Apps Script (Connection timed out). Google server may be busy; please try again in a few seconds.'];
        } catch (\Throwable $e) {
            Log::error('GoogleBlogsSheetService: Unexpected error.', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Unexpected error syncing to Google Sheet.'];
        }
    }

    /**
     * Delete a blog entry from the Google Sheet via Apps Script.
     *
     * @param string $classNumber
     * @param int $sheetRow
     * @param string|null $publicLink
     * @return array{success: bool, message: string, error?: string}
     */
    public function deleteBlog(string $classNumber, int $sheetRow, ?string $publicLink = null): array
    {
        $url    = config('services.google_blogs.apps_script_url');
        $secret = config('services.google_blogs.api_secret');

        if (empty($url) || empty($secret)) {
            return ['success' => false, 'error' => 'Apps Script URL or API Secret is not configured.'];
        }

        if (!$this->isClassSupported($classNumber)) {
            return ['success' => false, 'error' => "Class {$classNumber} is not mapped in GoogleBlogsSheetService."];
        }

        $payload = [
            'action'      => 'delete',
            'secret'      => $secret,
            'class'       => $classNumber,
            'sheet_row'   => $sheetRow,
            'public_link' => $publicLink,
        ];

        try {
            $client = Http::timeout(20)
                ->connectTimeout(5);

            if (app()->environment('local', 'testing')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($url, $payload);
            
            if (!$response->successful()) {
                return ['success' => false, 'error' => 'HTTP ' . $response->status() . ' from Apps Script.'];
            }

            $json = $response->json();
            
            if (isset($json['success']) && $json['success'] === true) {
                return ['success' => true, 'message' => $json['message'] ?? 'Deleted successfully.'];
            }
            
            return ['success' => false, 'error' => $json['message'] ?? 'Failed to delete from sheet.'];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('GoogleBlogsSheetService: Delete connection failed.', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Could not connect to Google Apps Script (Connection timed out). Please try again in a few seconds.'];
        } catch (\Throwable $e) {
            Log::error('GoogleBlogsSheetService: Delete failed.', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Unexpected error deleting from Google Sheet.'];
        }
    }

    /**
     * Synchronize a specific Follow Up Blog Post directly into the matching existing row.
     *
     * @param string $classNumber
     * @param string $publicLink
     * @param string $date
     * @param string $websiteName
     * @param bool   $forceOverwrite
     * @return array{success: bool, message: string, sheet?: string, row?: int, error?: string}
     */
    public function syncBlogFollowUp(
        string $classNumber,
        string $publicLink,
        string $date,
        string $websiteName,
        bool $forceOverwrite = false,
        ?string $sheetTab = null
    ): array {
        $scriptUrl = config('services.google_blogs.apps_script_url');
        $secret    = config('services.google_blogs.api_secret');

        if (empty($scriptUrl)) {
            Log::warning('GoogleBlogsSheetService: GOOGLE_BLOGS_APPS_SCRIPT_URL is not configured.');
            return ['success' => false, 'error' => 'Google Blogs Sheet is not configured on this server.'];
        }

        if (!$this->isClassSupported($classNumber)) {
            return ['success' => false, 'error' => "Class {$classNumber} is not mapped in GoogleBlogsSheetService."];
        }

        $payload = [
            'action'          => 'syncBlogFollowUp',
            'secret'          => $secret,
            'class'           => $classNumber,
            'public_link'     => $publicLink,
            'date'            => $date,
            'website'         => $websiteName,
            'sheet_tab'       => $sheetTab,
            'force_overwrite' => $forceOverwrite,
        ];

        try {
            $client = Http::timeout(12)
                ->connectTimeout(5)
                ->withHeaders(['Accept' => 'application/json']);

            if (app()->environment('local', 'testing')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($scriptUrl, $payload);

            $json = $response->json();

            if ($response->successful() && isset($json['success']) && $json['success'] === true) {
                Log::info('GoogleBlogsSheetService: syncBlogFollowUp succeeded.', [
                    'class' => $classNumber,
                    'sheet' => $json['sheet'] ?? null,
                    'row'   => $json['row'] ?? null,
                ]);
                return [
                    'success' => true,
                    'message' => $json['message'] ?? 'Blog successfully synchronized.',
                    'sheet'   => $json['sheet'] ?? null,
                    'row'     => $json['row'] ?? null,
                ];
            }

            $errorMessage = $json['message'] ?? ('HTTP ' . $response->status() . ' from Apps Script.');
            $isConflict = !empty($json['needs_confirmation'])
                || str_contains($errorMessage, 'already has a Public Link')
                || str_contains($errorMessage, 'replace it');

            Log::warning('GoogleBlogsSheetService: syncBlogFollowUp failed.', [
                'status'  => $response->status(),
                'message' => $errorMessage,
            ]);

            return [
                'success'            => false,
                'error'              => $errorMessage,
                'needs_confirmation' => $isConflict,
                'existing_link'      => $json['existing_public_link'] ?? null,
                'sheet_row'          => $json['sheet_row'] ?? null,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('GoogleBlogsSheetService: syncBlogFollowUp connection failed.', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Could not connect to Google Apps Script (Connection timed out). Google server may be busy or cooling down; please try again in a moment.'];
        } catch (\Throwable $e) {
            Log::error('GoogleBlogsSheetService: syncBlogFollowUp unexpected error.', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Unexpected error syncing to Google Sheet.'];
        }
    }
}
