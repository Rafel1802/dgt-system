<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsitePost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class UniversalTrackController extends Controller
{
    /**
     * Universal Direct Tracking Endpoint.
     * Records blog views directly into Central DGT regardless of external framework changes.
     * 
     * POST /api/v1/track
     */
    public function track(Request $request): JsonResponse
    {
        $domain = $this->cleanDomain($request->input('domain') ?: $request->header('Origin') ?: $request->header('Referer'));
        $slug = trim((string) ($request->input('slug') ?: $request->input('post_id') ?: ''));
        $url = trim((string) ($request->input('url') ?: $request->header('Referer') ?: ''));

        if (empty($domain) && !empty($url)) {
            $parsedHost = parse_url($url, PHP_URL_HOST);
            if ($parsedHost) {
                $domain = $this->cleanDomain($parsedHost);
            }
        }

        if (empty($slug) && !empty($url)) {
            $path = parse_url($url, PHP_URL_PATH);
            if ($path) {
                $segments = array_values(array_filter(explode('/', trim($path, '/'))));
                if (!empty($segments)) {
                    $slug = end($segments);
                }
            }
        }

        if (empty($domain) || empty($slug)) {
            return response()->json([
                'success' => false,
                'error'   => 'missing_required_parameters',
                'message' => 'Both domain and slug (or valid page url) are required.',
            ], 422);
        }

        // Bot Detection Filter
        $userAgent = (string) $request->header('User-Agent', '');
        if ($this->isBot($userAgent)) {
            return response()->json([
                'success'  => true,
                'recorded' => false,
                'reason'   => 'bot_ignored',
            ]);
        }

        // Find Website in Central DGT
        $website = Website::where('url', 'like', "%{$domain}%")
            ->orWhere('name', 'like', "%{$domain}%")
            ->first();

        if (!$website) {
            return response()->json([
                'success' => false,
                'error'   => 'website_not_found',
                'domain'  => $domain,
            ], 404);
        }

        // Find Post in Central DGT by slug, canonical url, or external_id
        $post = WebsitePost::where('website_id', $website->id)
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug)
                  ->orWhere('external_id', $slug)
                  ->orWhere('canonical_url', 'like', "%/{$slug}")
                  ->orWhere('canonical_url', 'like', "%/{$slug}/");
            })
            ->first();

        if (!$post) {
            return response()->json([
                'success' => false,
                'error'   => 'post_not_found',
                'slug'    => $slug,
                'domain'  => $domain,
            ], 404);
        }

        // IP Deduplication (1 view per IP per article every 30 minutes)
        $clientIp = $request->ip() ?: 'unknown';
        $dedupKey = 'dgt_view_' . md5("{$clientIp}_{$website->id}_{$post->id}");

        if (Cache::has($dedupKey)) {
            return response()->json([
                'success'  => true,
                'recorded' => false,
                'reason'   => 'deduplicated',
                'views'    => (int) $post->cumulative_views,
            ]);
        }

        // Lock deduplication window for 30 minutes
        Cache::put($dedupKey, true, now()->addMinutes(30));

        // Atomic Increment on Central DGT
        $post->increment('cumulative_views');
        $post->increment('page_views');

        // Ensure analytics source is set to a tracked source if none existed
        if (empty($post->analytics_source) || $post->analytics_source === WebsitePost::SOURCE_NONE || $post->analytics_source === 'none') {
            $post->updateQuietly([
                'analytics_source' => WebsitePost::SOURCE_DGT_TRACKER,
                'last_synced_at'   => now(),
            ]);
        } else {
            $post->updateQuietly([
                'last_synced_at'   => now(),
            ]);
        }

        $post->refresh();

        return response()->json([
            'success'  => true,
            'recorded' => true,
            'post_id'  => $post->id,
            'slug'     => $post->slug,
            'views'    => (int) $post->cumulative_views,
        ]);
    }

    /**
     * 1x1 Transparent GIF Beacon for Zero-JavaScript environments.
     * GET /api/v1/track.gif
     */
    public function pixel(Request $request): Response
    {
        // Re-use track logic internally
        try {
            $this->track($request);
        } catch (\Throwable $e) {
            // Silently handle to ensure pixel always renders
        }

        // 1x1 transparent GIF binary
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200, [
            'Content-Type'   => 'image/gif',
            'Content-Length' => strlen($gif),
            'Cache-Control'  => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'         => 'no-cache',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Clean domain input (strip protocol, www, trailing slashes, and ports).
     */
    private function cleanDomain(?string $url): string
    {
        if (empty($url)) {
            return '';
        }
        $host = parse_url($url, PHP_URL_HOST);
        $domain = $host ?: $url;
        $domain = preg_replace('/^https?:\/\//i', '', $domain);
        $domain = preg_replace('/^www\./i', '', $domain);
        $domain = preg_replace('/:[0-9]+$/', '', $domain);
        return strtolower(trim(explode('/', $domain)[0]));
    }

    /**
     * Comprehensive bot/spider crawler detection.
     */
    private function isBot(string $userAgent): bool
    {
        if (empty($userAgent)) {
            return true;
        }

        $botPatterns = [
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'whatsapp', 'facebookexternalhit',
            'slackbot', 'twitterbot', 'telegrambot', 'discordbot', 'pinterest', 'googlebot',
            'bingbot', 'yandex', 'baidu', 'duckduckbot', 'lighthouse', 'chrome-lighthouse',
            'headlesschrome', 'phantomjs', 'puppeteer', 'selenium', 'semrush', 'ahrefs',
            'screaming frog', 'siteaudit', 'dotbot', 'rogerbot', 'uptimerobot',
        ];

        $lowerUa = strtolower($userAgent);
        foreach ($botPatterns as $pattern) {
            if (str_contains($lowerUa, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
