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
        '8' => ['class' => 'AQ', 'doc' => 'AR', 'public' => 'AS', 'date' => 'AT', 'website' => 'AU'],
        '9' => ['class' => 'AX', 'doc' => 'AY', 'public' => 'AZ', 'date' => 'BA', 'website' => 'BB'],
    ];

    /**
     * Check if a class number is configured.
     */
    public function isClassSupported(string $classNumber): bool
    {
        return array_key_exists($classNumber, self::CLASS_BLOCKS);
    }

    /**
     * Auto-detect the class number (e.g. "5", "9") from a website category string (e.g. "9th Class", "Class 9", "Ninth Class").
     */
    public static function extractClassFromCategory(?string $category): ?string
    {
        if (empty($category)) {
            return null;
        }

        $cat = strtolower(trim($category));

        $wordMap = [
            'first' => '1', '1st' => '1',
            'second' => '2', '2nd' => '2',
            'third' => '3', '3rd' => '3',
            'fourth' => '4', '4th' => '4',
            'fifth' => '5', '5th' => '5',
            'sixth' => '6', '6th' => '6',
            'seventh' => '7', '7th' => '7',
            'eighth' => '8', '8th' => '8',
            'ninth' => '9', '9th' => '9',
        ];

        foreach ($wordMap as $key => $num) {
            if (str_contains($cat, $key)) {
                return $num;
            }
        }

        if (preg_match('/\b([1-9])\b/', $cat, $matches)) {
            return $matches[1];
        }

        if (preg_match('/([1-9])/', $cat, $matches)) {
            return $matches[1];
        }

        return null;
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
     * Canonical Google Sheet website name mapping.
     * Normalizes website names/domains in DGT System to the exact option in the Google Sheet dropdown.
     */
    public static function getCanonicalSheetWebsite(?string $websiteOrUrl): string
    {
        if (empty($websiteOrUrl)) {
            return '';
        }

        $host = parse_url(trim($websiteOrUrl), PHP_URL_HOST);
        $domain = strtolower(trim(preg_replace('/^www\./i', '', $host ?: $websiteOrUrl)));
        $domain = preg_replace('/:\d+$/', '', $domain);
        $domain = trim($domain, '/');

        // Specific domain-to-sheet-dropdown mappings
        // IMPORTANT: Keep asiamachinery.net (for asianmachinery.net) and asiamachinery.org distinct!
        $map = [
            'asianmachinery.net'       => 'asiamachinery.net',
            'asiamachinery.net'        => 'asiamachinery.net',
            'asianmachinery.org'       => 'asiamachinery.org',
            'asiamachinery.org'        => 'asiamachinery.org',
            'asiamachinery'            => 'asiamachinery.org',
            'asianmachinery'           => 'asiamachinery.net',
            'machineryasia.online'     => 'asiamachinery.org',
            'americanexcavators.com'   => 'americanexcavators.org',
            'americanexcavator.org'    => 'americanexcavators.org',
            'americanexcavator.com'    => 'americanexcavators.org',
            'americanskidsteer.com'    => 'americanskidsteers.com',
            'americanskidsteers.org'   => 'americanskidsteers.com',
            'americanskidsteer.org'    => 'americanskidsteers.com',
            'stonekrusher.org'         => 'stonekrusher.com',
            'usaforklifts.com'         => 'usaforklifts.org',
            'newdiggersforsale.com'    => 'newdiggerforsale.com',
            'heavyequipment.com'       => 'heavyequipment.wiki',
            'happymachinery.org'       => 'happymachinery.com',
            'thunderdump.org'          => 'thunderdump.com',
            'stunningmachinery.org'    => 'stunningmachinery.com',
        ];

        if (isset($map[$domain])) {
            return $map[$domain];
        }

        return $domain;
    }

    /**
     * Canonical fleet domain aliases and common variations for matching.
     */
    public static function getDomainAliases(?string $domainOrUrl): array
    {
        if (empty($domainOrUrl)) {
            return [];
        }

        $host = parse_url(trim($domainOrUrl), PHP_URL_HOST);
        $domain = strtolower(trim(preg_replace('/^www\./i', '', $host ?: $domainOrUrl)));
        $domain = preg_replace('/:\d+$/', '', $domain);
        $domain = trim($domain, '/');

        $groups = [
            ['asianmachinery.net', 'asiamachinery.net'],
            ['asiamachinery.org', 'asianmachinery.org', 'machineryasia.online'],
            ['americanexcavators.org', 'americanexcavators.com', 'americanexcavator.org', 'americanexcavator.com'],
            ['americanskidsteers.com', 'americanskidsteer.com', 'americanskidsteers.org', 'americanskidsteer.org'],
            ['stonekrusher.com', 'stonekrusher.org'],
            ['usaforklifts.org', 'usaforklifts.com'],
            ['usaforklift.org', 'usaforklift.com'],
            ['newdiggerforsale.com', 'newdiggersforsale.com'],
            ['heavyequipment.wiki', 'heavyequipment.com'],
            ['happymachinery.com', 'happymachinery.org'],
            ['thunderdump.com', 'thunderdump.org'],
            ['stunningmachinery.com', 'stunningmachinery.org'],
        ];

        $result = [$domain];
        $canonical = self::getCanonicalSheetWebsite($domain);
        if ($canonical && !in_array($canonical, $result, true)) {
            $result[] = $canonical;
        }

        foreach ($groups as $grp) {
            if (in_array($domain, $grp, true) || in_array($canonical, $grp, true)) {
                foreach ($grp as $member) {
                    if (!in_array($member, $result, true)) {
                        $result[] = $member;
                    }
                }
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * Strictly check whether a Google Sheet "Website link" cell matches the target website.
     */
    public static function isStrictWebsiteMatch(string $cellWebsite, string $targetWebsite): bool
    {
        $cWeb = strtolower(trim($cellWebsite));
        $tWeb = strtolower(trim($targetWebsite));

        if ($cWeb === '' || $cWeb === '-' || $cWeb === 'n/a' || $tWeb === '') {
            return false;
        }

        $cHost = parse_url(str_contains($cWeb, '://') ? $cWeb : 'https://' . $cWeb, PHP_URL_HOST) ?: $cWeb;
        $tHost = parse_url(str_contains($tWeb, '://') ? $tWeb : 'https://' . $tWeb, PHP_URL_HOST) ?: $tWeb;

        $cDomain = trim(preg_replace('/^www\./i', '', strtolower($cHost)), '/');
        $tDomain = trim(preg_replace('/^www\./i', '', strtolower($tHost)), '/');

        if ($cDomain === $tDomain) {
            return true;
        }

        $cCanonical = self::getCanonicalSheetWebsite($cDomain);
        $tCanonical = self::getCanonicalSheetWebsite($tDomain);

        if ($cCanonical !== '' && $cCanonical === $tCanonical) {
            return true;
        }

        $aliases = self::getDomainAliases($tDomain);
        if (in_array($cDomain, $aliases, true) || in_array($cCanonical, $aliases, true)) {
            return true;
        }

        return false;
    }

    /**
     * Normalize URL for comparison.
     */
    public static function normaliseUrl(string $url): string
    {
        $u = strtolower(trim($url));
        $u = preg_replace('/#+[^#]*$/', '', $u);
        return rtrim($u, '/');
    }

    /**
     * Check whether two date strings represent the same month and day.
     */
    public static function datesMatch(string $cellDateVal, string $targetDateStr): bool
    {
        $s1 = trim($cellDateVal);
        $s2 = trim($targetDateStr);
        if ($s1 === '' || $s2 === '') {
            return false;
        }
        if (strcasecmp($s1, $s2) === 0) {
            return true;
        }

        $parseMonthDay = static function (string $str): ?array {
            // Handle JS Date string from Google Sheets export_csv like "Sat Nov 07 2026 00:00:00 GMT+0700..."
            if (preg_match('/\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d{1,2})\s+\d{4}\b/i', $str, $m)) {
                $months = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];
                $mon = $months[strtolower($m[1])] ?? null;
                if ($mon) {
                    return [$mon, (int) $m[2]];
                }
            }
            // Handle YYYY-MM-DD
            if (preg_match('/^(\d{4})[\-\/](\d{1,2})[\-\/](\d{1,2})/', $str, $m)) {
                return [(int) $m[2], (int) $m[3]];
            }
            // Handle MM/DD or MM/DD/YYYY
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})(?:[\/\-]\d{2,4})?$/', $str, $m)) {
                $p1 = (int) $m[1];
                $p2 = (int) $m[2];
                // In Google Sheets blog tabs, dates are MM/DD (e.g. 11/07)
                if ($p1 >= 1 && $p1 <= 12) {
                    return [$p1, $p2];
                }
                if ($p2 >= 1 && $p2 <= 12) {
                    return [$p2, $p1];
                }
            }
            try {
                $ts = strtotime($str);
                if ($ts !== false) {
                    return [(int) date('n', $ts), (int) date('j', $ts)];
                }
            } catch (\Throwable $e) {
            }
            return null;
        };

        $md1 = $parseMonthDay($s1);
        $md2 = $parseMonthDay($s2);

        if ($md1 && $md2) {
            return $md1[0] === $md2[0] && $md1[1] === $md2[1];
        }

        return false;
    }

    /**
     * Fetch the current Google Sheet CSV and resolve the exact 1-based row number for the website & class.
     */
    protected function resolveTargetSheetRow(
        string $scriptUrl,
        ?string $secret,
        string $classNumber,
        string $publicLink,
        string $date,
        string $websiteName,
        bool $forceOverwrite,
        ?string $sheetTab
    ): array {
        try {
            $client = Http::timeout(12)
                ->connectTimeout(5)
                ->withHeaders(['Accept' => 'application/json']);

            if (app()->environment('local', 'testing')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($scriptUrl, [
                'action'    => 'export_csv',
                'secret'    => $secret,
                'sheet_tab' => $sheetTab,
                'date'      => $date,
            ]);

            if (!$response->successful()) {
                return ['resolved' => false];
            }

            $json = $response->json();
            if (empty($json['success']) || empty($json['csv'])) {
                return ['resolved' => false];
            }

            $resolvedSheetName = $json['sheet'] ?? $sheetTab ?? 'Blogs';
            $csvText = (string) $json['csv'];

            // Parse CSV lines safely
            $rows = [];
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $csvText);
            rewind($stream);
            while (($data = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $rows[] = $data;
            }
            fclose($stream);

            if (count($rows) < 3) {
                return ['resolved' => false];
            }

            // Standard 0-based start column per class number
            $standardOffsets = [
                '3' => 0,
                '4' => 0,
                '2' => 7,
                '1' => 14,
                '5' => 21,
                '6' => 28,
                '7' => 35,
                '8' => 42,
                '9' => 49,
            ];

            if (!isset($standardOffsets[$classNumber])) {
                return ['resolved' => false];
            }

            $startCol = $standardOffsets[$classNumber];

            // Find subheader row ("Class", "Doc Link", "Public Link", "Dated", "Website link")
            $subRowIndex = 1;
            $numRows = count($rows);
            for ($r = 0; $r < min(15, $numRows); $r++) {
                $row = $rows[$r] ?? [];
                $subMatches = 0;
                foreach ($row as $cell) {
                    $h = strtolower(trim((string) $cell));
                    if (str_contains($h, 'link') || str_contains($h, 'public') || str_contains($h, 'doc') || str_contains($h, 'date') || str_contains($h, 'writer')) {
                        $subMatches++;
                    }
                }
                if ($subMatches >= 3) {
                    $subRowIndex = $r;
                    break;
                }
            }

            $dataStartRow = $subRowIndex + 1;
            $colClass   = $startCol + 0;
            $colDoc     = $startCol + 1;
            $colPublic  = $startCol + 2;
            $colDated   = $startCol + 3;
            $colWebsite = $startCol + 4;
            $colWriter  = $startCol + 5;

            $targetWebsite = self::getCanonicalSheetWebsite($websiteName ?: $publicLink);
            $urlDomain     = self::getCanonicalSheetWebsite($publicLink);
            $normLink      = self::normaliseUrl($publicLink);

            $websiteRows = [];
            $mismatchedRowsWithSameUrl = [];

            for ($r = $dataStartRow; $r < $numRows; $r++) {
                $row = $rows[$r] ?? [];
                if ($colWebsite >= count($row)) {
                    continue;
                }

                $cellClass   = trim((string) ($row[$colClass] ?? ''));
                $cellDoc     = trim((string) ($row[$colDoc] ?? ''));
                $cellPublic  = trim((string) ($row[$colPublic] ?? ''));
                $cellDated   = trim((string) ($row[$colDated] ?? ''));
                $cellWebsite = trim((string) ($row[$colWebsite] ?? ''));
                $cellWriter  = trim((string) ($row[$colWriter] ?? ''));

                $isEmptyPublic = ($cellPublic === '' || $cellPublic === '-' || strtolower($cellPublic) === 'n/a' || !preg_match('/^https?:\/\//i', $cellPublic));
                $isUrlMatch    = (!$isEmptyPublic) && (self::normaliseUrl($cellPublic) === $normLink);

                $webMatches = self::isStrictWebsiteMatch($cellWebsite, $targetWebsite)
                    || ($urlDomain !== '' && self::isStrictWebsiteMatch($cellWebsite, $urlDomain));

                if (!$webMatches) {
                    if ($isUrlMatch) {
                        $mismatchedRowsWithSameUrl[] = $r + 1;
                    }
                    continue;
                }

                // Class matching (for shared Class 3 & 4 column block, verify class number if present)
                $classMatches = ($cellClass === $classNumber
                    || $cellClass === 'Class ' . $classNumber
                    || ($classNumber !== '3' && $classNumber !== '4' && $cellClass === ''));

                if (!$classMatches) {
                    continue;
                }

                $hasDoc    = ($cellDoc !== '' && $cellDoc !== '-' && strtolower($cellDoc) !== 'n/a');
                $hasWriter = ($cellWriter !== '' && $cellWriter !== '-' && strtolower($cellWriter) !== 'n/a');

                $websiteRows[] = [
                    'rowIndex'      => $r,
                    'sheetRow'      => $r + 1,
                    'docLink'       => $cellDoc,
                    'hasDoc'        => $hasDoc,
                    'publicLink'    => $isEmptyPublic ? '' : $cellPublic,
                    'dated'         => $cellDated,
                    'writer'        => $cellWriter,
                    'hasWriter'     => $hasWriter,
                    'isDateMatch'   => self::datesMatch($cellDated, $date),
                    'isUrlMatch'    => $isUrlMatch,
                    'isEmptyPublic' => $isEmptyPublic,
                ];
            }

            if (empty($websiteRows)) {
                return ['resolved' => false];
            }

            $target = null;

            // 1. Check rows matching the requested date
            $dateMatchingRows = array_values(array_filter($websiteRows, static fn($w) => $w['isDateMatch']));

            if (!empty($dateMatchingRows)) {
                $urlMatchOnDate = array_values(array_filter($dateMatchingRows, static fn($w) => $w['isUrlMatch']));
                if (!empty($urlMatchOnDate)) {
                    $target = $urlMatchOnDate[0];
                } else {
                    $emptyOnDate = array_values(array_filter($dateMatchingRows, static fn($w) => $w['isEmptyPublic']));
                    if (!empty($emptyOnDate)) {
                        $target = $emptyOnDate[0];
                    } elseif ($forceOverwrite) {
                        $target = $dateMatchingRows[0];
                    } else {
                        return [
                            'resolved'           => true,
                            'conflict'           => true,
                            'sheet_row'          => $dateMatchingRows[0]['sheetRow'],
                            'existing_link'      => $dateMatchingRows[0]['publicLink'],
                            'sheet'              => $resolvedSheetName,
                            'message'            => 'Row ' . $dateMatchingRows[0]['sheetRow'] . ' for "' . $targetWebsite . '" on ' . $date . ' in Class ' . $classNumber . ' (tab "' . $resolvedSheetName . '") already has a Public Link (' . $dateMatchingRows[0]['publicLink'] . '). Confirm overwrite to replace it.',
                        ];
                    }
                }
            } else {
                // 2. No exact date match found in the sheet for this website
                $urlMatches = array_values(array_filter($websiteRows, static fn($w) => $w['isUrlMatch']));
                if (!empty($urlMatches)) {
                    $target = $urlMatches[0];
                } else {
                    $emptyWithDoc    = array_values(array_filter($websiteRows, static fn($w) => $w['isEmptyPublic'] && $w['hasDoc']));
                    $emptyWithWriter = array_values(array_filter($websiteRows, static fn($w) => $w['isEmptyPublic'] && $w['hasWriter']));
                    $emptyAny        = array_values(array_filter($websiteRows, static fn($w) => $w['isEmptyPublic']));

                    if (!empty($emptyWithDoc)) {
                        $target = $emptyWithDoc[0];
                    } elseif (!empty($emptyWithWriter)) {
                        $target = $emptyWithWriter[0];
                    } elseif (!empty($emptyAny)) {
                        $target = $emptyAny[0];
                    } elseif ($forceOverwrite) {
                        $target = $websiteRows[0];
                    } else {
                        return [
                            'resolved'           => true,
                            'conflict'           => true,
                            'sheet_row'          => $websiteRows[0]['sheetRow'],
                            'existing_link'      => $websiteRows[0]['publicLink'],
                            'sheet'              => $resolvedSheetName,
                            'message'            => 'All scheduled rows for "' . $targetWebsite . '" in Class ' . $classNumber . ' (tab "' . $resolvedSheetName . '") already have a Public Link (' . $websiteRows[0]['publicLink'] . '). Confirm overwrite to replace it.',
                        ];
                    }
                }
            }

            if (!$target) {
                return ['resolved' => false];
            }

            // If this same URL was previously placed in a wrong website's row in this class block, clean it up
            foreach ($mismatchedRowsWithSameUrl as $wrongRow) {
                if ($wrongRow !== $target['sheetRow']) {
                    $this->deleteBlog($classNumber, (int) $wrongRow, $publicLink, $resolvedSheetName);
                }
            }

            return [
                'resolved'  => true,
                'conflict'  => false,
                'sheet_row' => $target['sheetRow'],
                'sheet'     => $resolvedSheetName,
            ];
        } catch (\Throwable $e) {
            Log::warning('GoogleBlogsSheetService: resolveTargetSheetRow fallback.', ['error' => $e->getMessage()]);
            return ['resolved' => false];
        }
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
        string $idempotencyKey = '',
        ?string $sheetTab = null
    ): array {
        return $this->syncBlogFollowUp(
            $classNumber,
            $publicLink,
            $date,
            $websiteName,
            false,
            $sheetTab
        );
    }

    /**
     * Delete a blog entry from the Google Sheet via Apps Script.
     *
     * @param string $classNumber
     * @param int $sheetRow
     * @param string|null $publicLink
     * @return array{success: bool, message: string, error?: string}
     */
    public function deleteBlog(string $classNumber, int $sheetRow, ?string $publicLink = null, ?string $sheetTab = null): array
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
            'sheet_tab'   => $sheetTab,
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

        $canonicalWebsite = self::getCanonicalSheetWebsite($websiteName ?: $publicLink);

        // Resolve the exact 1-based target row in PHP from the live sheet CSV
        // so that websites in the same class (or similar domains like asiamachinery.net vs asiamachinery.org)
        // never get placed into another website's row.
        $resolved = $this->resolveTargetSheetRow(
            $scriptUrl,
            $secret,
            $classNumber,
            $publicLink,
            $date,
            $websiteName,
            $forceOverwrite,
            $sheetTab
        );

        if (!empty($resolved['resolved']) && !empty($resolved['conflict'])) {
            return [
                'success'            => false,
                'error'              => $resolved['message'] ?? 'Row already has a Public Link. Confirm overwrite to replace it.',
                'needs_confirmation' => true,
                'existing_link'      => $resolved['existing_link'] ?? null,
                'sheet_row'          => $resolved['sheet_row'] ?? null,
            ];
        }

        $payload = [
            'action'          => 'syncBlogFollowUp',
            'secret'          => $secret,
            'class'           => $classNumber,
            'public_link'     => $publicLink,
            'date'            => $date,
            'website'         => $canonicalWebsite,
            'aliases'         => [], // Always pass empty array to avoid remote Apps Script alias loop bug
            'sheet_tab'       => $sheetTab,
            'force_overwrite' => $forceOverwrite,
        ];

        if (!empty($resolved['resolved']) && !empty($resolved['sheet_row'])) {
            $payload['sheet_row'] = (int) $resolved['sheet_row'];
        }

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
