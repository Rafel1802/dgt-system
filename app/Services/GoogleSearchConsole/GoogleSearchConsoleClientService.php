<?php

namespace App\Services\GoogleSearchConsole;

use App\Models\GoogleSearchConsole\GoogleSearchConsoleAccount;
use Google\Client as GoogleClient;
use Google\Service\SearchConsole as GoogleSearchConsoleService;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Google\Service\Oauth2 as GoogleOauth2Service;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class GoogleSearchConsoleClientService
{
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected ?string $redirectUri;

    public function __construct()
    {
        $this->clientId     = config('services.google_search_console.client_id');
        $this->clientSecret = config('services.google_search_console.client_secret');
        $this->redirectUri  = config('services.google_search_console.redirect_uri') ?: route('google.search-console.callback');
    }

    /**
     * Check if OAuth client credentials are configured in environment
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /**
     * Build base Google Client instance
     */
    public function buildClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri($this->redirectUri);
        $client->setAccessType('offline');
        $client->setPrompt('consent'); // Force refresh token issuance
        $client->setIncludeGrantedScopes(true);
        $client->addScope([
            'https://www.googleapis.com/auth/webmasters',
            'https://www.googleapis.com/auth/webmasters.readonly',
            'openid',
            'email',
            'profile',
        ]);

        return $client;
    }

    /**
     * Generate Google OAuth authorization URL with CSRF state
     */
    public function createAuthUrl(string $state): string
    {
        $client = $this->buildClient();
        $client->setState($state);
        return $client->createAuthUrl();
    }

    /**
     * Authenticate and retrieve client with active account token (handling token refresh)
     */
    public function getAuthenticatedClient(?GoogleSearchConsoleAccount $account = null): GoogleClient
    {
        $account = $account ?: GoogleSearchConsoleAccount::where('is_active', true)->latest()->first();

        if (!$account) {
            throw new Exception("No active Google Search Console account found. Please connect your Google account.");
        }

        $client = $this->buildClient();

        $tokenData = [
            'access_token'  => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'expires_in'    => $account->expires_at ? max(0, $account->expires_at->diffInSeconds(now())) : 3600,
            'created'       => $account->updated_at ? $account->updated_at->timestamp : time(),
        ];

        $client->setAccessToken($tokenData);

        // Check if token has expired or is expiring in less than 60 seconds
        if ($client->isAccessTokenExpired() || ($account->expires_at && $account->expires_at->isPast())) {
            if (!$account->refresh_token) {
                $account->update([
                    'is_active' => false,
                    'last_error' => 'Access token expired and no refresh token is available. Please reconnect.',
                    'last_error_at' => now(),
                ]);
                throw new Exception("Google Search Console access token expired and no refresh token is present. Please reconnect.");
            }

            try {
                $newAccessToken = $client->fetchAccessTokenWithRefreshToken($account->refresh_token);

                if (isset($newAccessToken['error'])) {
                    $account->update([
                        'is_active' => false,
                        'last_error' => 'Token refresh failed: ' . ($newAccessToken['error_description'] ?? $newAccessToken['error']),
                        'last_error_at' => now(),
                    ]);
                    throw new Exception("Failed to refresh Google token: " . ($newAccessToken['error_description'] ?? $newAccessToken['error']));
                }

                $account->update([
                    'access_token' => $newAccessToken['access_token'],
                    'refresh_token' => $newAccessToken['refresh_token'] ?? $account->refresh_token,
                    'expires_at' => isset($newAccessToken['expires_in']) ? now()->addSeconds((int)$newAccessToken['expires_in']) : now()->addHour(),
                    'last_error' => null,
                    'last_error_at' => null,
                ]);

                $client->setAccessToken($newAccessToken);
            } catch (Exception $e) {
                Log::error('GSC Token Refresh Exception', ['error' => $e->getMessage()]);
                $account->update([
                    'last_error' => 'Exception during token refresh: ' . $e->getMessage(),
                    'last_error_at' => now(),
                ]);
                throw $e;
            }
        }

        return $client;
    }

    /**
     * Handle OAuth code exchange from Google callback
     */
    public function handleCallback(string $code, ?int $userId = null): GoogleSearchConsoleAccount
    {
        $client = $this->buildClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            $msg = $token['error_description'] ?? $token['error'];
            Log::error('Google Search Console OAuth Error', ['error' => $token]);
            throw new Exception("Google OAuth failed: " . $msg);
        }

        $client->setAccessToken($token);

        // Fetch user email & Google account ID
        $oauth2 = new GoogleOauth2Service($client);
        $userInfo = $oauth2->userinfo->get();
        $googleAccountId = $userInfo->getId();
        $email = $userInfo->getEmail();

        // Check if an account already exists for this Google Account or email
        $account = GoogleSearchConsoleAccount::where('google_account_id', $googleAccountId)
            ->orWhere('email', $email)
            ->first();

        $expiresIn = $token['expires_in'] ?? 3600;
        $expiresAt = now()->addSeconds((int)$expiresIn);

        $payload = [
            'google_account_id' => $googleAccountId,
            'email'             => $email,
            'access_token'      => $token['access_token'] ?? null,
            'token_type'        => $token['token_type'] ?? 'Bearer',
            'expires_at'        => $expiresAt,
            'scopes'            => is_array($token['scope'] ?? null) ? implode(' ', $token['scope']) : ($token['scope'] ?? ''),
            'is_active'         => true,
            'last_connected_by' => $userId,
            'last_error'        => null,
            'last_error_at'     => null,
        ];

        // Only overwrite refresh token if Google returned one
        if (!empty($token['refresh_token'])) {
            $payload['refresh_token'] = $token['refresh_token'];
        }

        if ($account) {
            $account->update($payload);
        } else {
            if (empty($token['refresh_token'])) {
                // If new account and no refresh token, warn in logs
                Log::warning('Google Search Console OAuth connected without refresh token. Re-authorization with prompt=consent may be required.');
            }
            $account = GoogleSearchConsoleAccount::create($payload);
        }

        // Test API access immediately to ensure Search Console API is enabled
        $this->verifySearchConsoleAccess($account);

        return $account;
    }

    /**
     * Verify that Search Console API is enabled and accessible
     */
    public function verifySearchConsoleAccess(GoogleSearchConsoleAccount $account): bool
    {
        try {
            $client = $this->getAuthenticatedClient($account);
            $service = new GoogleSearchConsoleService($client);
            $service->sites->listSites();
            return true;
        } catch (Exception $e) {
            $account->update([
                'last_error' => 'Search Console API verification failed: ' . $e->getMessage(),
                'last_error_at' => now(),
            ]);
            throw new Exception("Search Console API request failed: " . $e->getMessage() . ". Ensure the Google Search Console API is enabled in your Google Cloud Console.");
        }
    }

    /**
     * List all verified properties from Google Search Console
     */
    public function listProperties(?GoogleSearchConsoleAccount $account = null): array
    {
        $client = $this->getAuthenticatedClient($account);
        $service = new GoogleSearchConsoleService($client);

        try {
            $siteList = $service->sites->listSites();
            $sites = [];

            if ($siteList && $siteList->getSiteEntry()) {
                foreach ($siteList->getSiteEntry() as $site) {
                    $sites[] = [
                        'site_url'         => $site->getSiteUrl(),
                        'permission_level' => $site->getPermissionLevel(), // e.g. siteOwner, siteFullUser
                    ];
                }
            }

            return $sites;
        } catch (Exception $e) {
            Log::error('GSC listProperties error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Query Search Analytics API with dimensions and optional filters
     *
     * @param string $siteUrl Google site identifier (e.g. "sc-domain:example.com" or "https://example.com/")
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @param array $dimensions e.g. ['date'], ['query'], ['page'], ['country'], ['device']
     * @param int $rowLimit Max rows to fetch (up to 25,000 per Google API specs)
     * @param int $startRow 0-based start index
     * @param string $searchType 'web', 'image', 'video', 'news', 'discover', 'googleNews'
     */
    public function querySearchAnalytics(
        string $siteUrl,
        string $startDate,
        string $endDate,
        array $dimensions = ['date'],
        int $rowLimit = 1000,
        int $startRow = 0,
        string $searchType = 'web',
        ?GoogleSearchConsoleAccount $account = null
    ): array {
        $client = $this->getAuthenticatedClient($account);
        $service = new GoogleSearchConsoleService($client);

        $request = new SearchAnalyticsQueryRequest();
        $request->setStartDate($startDate);
        $request->setEndDate($endDate);
        $request->setDimensions($dimensions);
        $request->setRowLimit($rowLimit);
        $request->setStartRow($startRow);
        $request->setType($searchType);

        try {
            $response = $service->searchanalytics->query($siteUrl, $request);
            $rows = $response->getRows();

            $results = [];
            if ($rows) {
                foreach ($rows as $row) {
                    $results[] = [
                        'keys'        => $row->getKeys(), // dimension values corresponding to $dimensions
                        'clicks'      => (int) $row->getClicks(),
                        'impressions' => (int) $row->getImpressions(),
                        'ctr'         => (float) $row->getCtr(),
                        'position'    => (float) $row->getPosition(),
                    ];
                }
            }

            return $results;
        } catch (Exception $e) {
            Log::error('GSC querySearchAnalytics error', [
                'siteUrl' => $siteUrl,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => $dimensions,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * List all submitted sitemaps for a property from Google Search Console
     */
    public function listSitemaps(string $siteUrl, ?GoogleSearchConsoleAccount $account = null): array
    {
        $client = $this->getAuthenticatedClient($account);
        $service = new GoogleSearchConsoleService($client);

        try {
            $response = $service->sitemaps->listSitemaps($siteUrl);
            $sitemaps = [];

            if ($response && $response->getSitemap()) {
                foreach ($response->getSitemap() as $sm) {
                    $contents = [];
                    $totalSubmitted = 0;
                    $totalIndexed = 0;
                    $webSubmitted = 0;
                    $webIndexed = 0;

                    if ($sm->getContents()) {
                        foreach ($sm->getContents() as $c) {
                            $type = strtolower($c->getType() ?: 'web');
                            $sub = (int) $c->getSubmitted();
                            $idx = (int) $c->getIndexed();
                            $totalSubmitted += $sub;
                            $totalIndexed += $idx;

                            if ($type === 'web' || empty($type)) {
                                $webSubmitted += $sub;
                                $webIndexed += $idx;
                            }

                            $contents[] = [
                                'type'      => $c->getType(),
                                'submitted' => $sub,
                                'indexed'   => $idx,
                            ];
                        }
                    }

                    // Prefer web-specific submitted/indexed count for web page indexing
                    $submittedCount = $webSubmitted > 0 ? $webSubmitted : $totalSubmitted;
                    $indexedCount = $webSubmitted > 0 ? $webIndexed : $totalIndexed;

                    $errors = (int) $sm->getErrors();
                    $warnings = (int) $sm->getWarnings();
                    $isPending = (bool) $sm->getIsPending();

                    $status = 'success';
                    if ($errors > 0) {
                        $status = 'error';
                    } elseif ($isPending) {
                        $status = 'pending';
                    } elseif ($warnings > 0) {
                        $status = 'warning';
                    }

                    $sitemaps[] = [
                        'path'             => $sm->getPath(),
                        'type'             => $sm->getType() ?: 'SITEMAP',
                        'last_submitted'   => $sm->getLastSubmitted(),
                        'last_downloaded'  => $sm->getLastDownloaded(),
                        'is_pending'       => $isPending,
                        'is_sitemap_index' => (bool) $sm->getIsSitemapsIndex(),
                        'errors'           => $errors,
                        'warnings'         => $warnings,
                        'submitted_urls'   => $submittedCount,
                        'indexed_urls'     => $indexedCount,
                        'total_items'      => $totalSubmitted,
                        'status'           => $status,
                        'contents'         => $contents,
                    ];
                }
            }

            return $sitemaps;
        } catch (Exception $e) {
            Log::error('GSC listSitemaps error', [
                'siteUrl' => $siteUrl,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Submit a sitemap to Google Search Console
     */
    public function submitSitemap(string $siteUrl, string $feedpath, ?GoogleSearchConsoleAccount $account = null): bool
    {
        $client = $this->getAuthenticatedClient($account);
        $service = new GoogleSearchConsoleService($client);

        try {
            $service->sitemaps->submit($siteUrl, $feedpath);
            return true;
        } catch (Exception $e) {
            Log::error('GSC submitSitemap error', [
                'siteUrl'  => $siteUrl,
                'feedpath' => $feedpath,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete a sitemap from Google Search Console
     */
    public function deleteSitemap(string $siteUrl, string $feedpath, ?GoogleSearchConsoleAccount $account = null): bool
    {
        $client = $this->getAuthenticatedClient($account);
        $service = new GoogleSearchConsoleService($client);

        try {
            $service->sitemaps->delete($siteUrl, $feedpath);
            return true;
        } catch (Exception $e) {
            Log::error('GSC deleteSitemap error', [
                'siteUrl'  => $siteUrl,
                'feedpath' => $feedpath,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Inspect a specific URL via Google Search Console URL Inspection API
     */
    public function inspectUrl(string $siteUrl, string $inspectionUrl, ?GoogleSearchConsoleAccount $account = null): array
    {
        $client = $this->getAuthenticatedClient($account);
        $service = new GoogleSearchConsoleService($client);

        try {
            $request = new \Google\Service\SearchConsole\InspectUrlIndexRequest();
            $request->setSiteUrl($siteUrl);
            $request->setInspectionUrl($inspectionUrl);

            $response = $service->urlInspection_index->inspect($request);
            $result = $response->getInspectionResult();

            $indexStatus = $result ? $result->getIndexStatusResult() : null;
            $mobileResult = $result ? $result->getMobileUsabilityResult() : null;

            $verdict = $indexStatus ? ($indexStatus->getVerdict() ?: 'VERDICT_UNSPECIFIED') : 'VERDICT_UNSPECIFIED';
            $coverageState = $indexStatus ? ($indexStatus->getCoverageState() ?: 'Unknown') : 'Unknown';

            // Check if indexed: PASS or coverage mentions indexed (excluding 'not indexed')
            $isIndexed = false;
            $covLower = strtolower($coverageState);
            if ($verdict === 'PASS' || (str_contains($covLower, 'indexed') && !str_contains($covLower, 'not indexed') && !str_contains($covLower, 'noindex'))) {
                $isIndexed = true;
            }

            return [
                'url'              => $inspectionUrl,
                'site_url'         => $siteUrl,
                'verdict'          => $verdict,
                'is_indexed'       => $isIndexed,
                'coverage_state'   => $coverageState,
                'indexing_state'   => $indexStatus ? $indexStatus->getIndexingState() : null,
                'page_fetch_state' => $indexStatus ? $indexStatus->getPageFetchState() : null,
                'robots_txt_state' => $indexStatus ? $indexStatus->getRobotsTxtState() : null,
                'crawled_as'       => $indexStatus ? $indexStatus->getCrawledAs() : null,
                'last_crawl_time'  => $indexStatus ? $indexStatus->getLastCrawlTime() : null,
                'google_canonical' => $indexStatus ? $indexStatus->getGoogleCanonical() : null,
                'user_canonical'   => $indexStatus ? $indexStatus->getUserCanonical() : null,
                'sitemaps'         => $indexStatus ? ($indexStatus->getSitemap() ?: []) : [],
                'referring_urls'   => $indexStatus ? ($indexStatus->getReferringUrls() ?: []) : [],
                'mobile_verdict'   => $mobileResult ? $mobileResult->getVerdict() : null,
                'mobile_issues'    => $mobileResult && $mobileResult->getIssues() ? array_map(fn($i) => is_object($i) ? $i->getIssueType() : (string)$i, $mobileResult->getIssues()) : [],
                'inspection_result_link' => $result ? $result->getInspectionResultLink() : null,
            ];
        } catch (Exception $e) {
            Log::error('GSC inspectUrl error', [
                'siteUrl'       => $siteUrl,
                'inspectionUrl' => $inspectionUrl,
                'error'         => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Add a site property to Google Search Console
     */
    public function addSiteProperty(string $siteUrl, ?GoogleSearchConsoleAccount $account = null): bool
    {
        $client = $this->getAuthenticatedClient($account);
        $service = new GoogleSearchConsoleService($client);

        try {
            $service->sites->add($siteUrl);
            return true;
        } catch (Exception $e) {
            Log::error('GSC addSiteProperty error', [
                'siteUrl' => $siteUrl,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
