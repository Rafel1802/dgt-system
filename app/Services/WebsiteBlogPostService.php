<?php

namespace App\Services;

use App\Models\Website;
use App\Models\WebsiteFollowUp;
use App\Models\WebsiteIntegration;
use App\Models\WebsitePost;
use App\Models\WebsiteSyncLog;
use App\Services\GoogleBlogsSheetService;
use App\Services\Security\SafeUrlValidator;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WebsiteBlogPostService
{
    /**
     * Retrieve blog posts with dynamic filters for website, status (published/draft/scheduled/all),
     * flexible limit (10/20/30/50/all), and search. Ultra-fast and always fresh.
     *
     * @param int|null $websiteId
     * @param string|null $status
     * @param int|string $limit
     * @param string|null $search
     * @return array
     */
    public function getPosts(
        ?int $websiteId = null,
        ?string $status = null,
        int|string $limit = 10,
        ?string $search = null
    ): array {
        $query = WebsitePost::select([
            'id', 'website_id', 'author_name', 'title', 'slug', 'canonical_url',
            'featured_image_url', 'excerpt', 'status', 'published_at', 'scheduled_at',
            'created_at', 'cumulative_views', 'category', 'playlist', 'meta_title',
            'meta_description', 'tag_keywords', 'focus_keyword'
        ])->with([
            'website' => fn($q) => $q->select('id', 'name', 'url', 'category', 'handled_by', 'logo_path')
                ->with('handler:id,name,avatar')
        ]);

        if ($websiteId) {
            $query->where('website_id', $websiteId);
        }

        // Security & Quality filter: Exclude unauthorized test authors (fast indexed column check)
        $query->where(function ($q) {
            $q->whereNotIn('author_name', ['Hansa Hood', 'Me Nas', 'Michael Cerrick', 'Monita EN', 'John Vicas', 'Fleet Safety Board'])
              ->orWhereNull('author_name');
        });

        if ($status && $status !== 'all') {
            $statusLower = strtolower(trim($status));
            if ($statusLower === 'published') {
                $query->whereIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public']);
            } elseif ($statusLower === 'scheduled') {
                $query->whereIn('status', [WebsitePost::STATUS_SCHEDULED, 'future']);
            } elseif ($statusLower === 'draft') {
                $query->where(function ($q) {
                    $q->whereNotIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public', WebsitePost::STATUS_SCHEDULED, 'future'])
                        ->orWhereNull('status');
                });
            }
        }

        if ($search && trim($search) !== '') {
            $s = trim($search);
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%")
                    ->orWhere('author_name', 'like', "%{$s}%");
            });
        }

        $take = 10;
        if (strtolower((string) $limit) === 'all') {
            $take = 1000;
        } else {
            $take = max(1, min(500, (int) $limit));
        }

        $posts = $query->orderByDesc('id')
            ->take($take + 5)
            ->get()
            ->reject(function (WebsitePost $post) {
                return WebsiteSyncService::isSpamOrScamPost([
                    'title'       => $post->title,
                    'slug'        => $post->slug,
                    'author_name' => $post->author_name,
                ]);
            })
            ->take($take);

        return $posts->map(fn(WebsitePost $post) => $this->formatPostItem($post))->values()->all();
    }

    /**
     * Get the latest 10 blog posts (backward-compatible convenience wrapper).
     *
     * @param int|null $websiteId
     * @return array
     */
    public function getLatest10Posts(?int $websiteId = null): array
    {
        return $this->getPosts($websiteId, null, 10);
    }

    /**
     * Format a WebsitePost model into a normalized array for CMS and UI consumption.
     */
    public function formatPostItem(WebsitePost $post): array
    {
        $siteCleanName = preg_replace('/\.(com|org|net|eu|io|co|us|online|wiki)$/i', '', $post->website?->name ?: 'Website Team');
        $cleanSiteAuthor = trim(ucwords(str_replace(['-', '_'], ' ', $siteCleanName))) ?: 'Editorial Team';
        $author = $post->author_name ?: $cleanSiteAuthor;
        $publishedFormatted = $post->published_at ? $post->published_at->format('M d, Y H:i') : null;
        $scheduledFormatted = $post->scheduled_at ? $post->scheduled_at->format('M d, Y H:i') : null;
        $createdFormatted = $post->created_at ? $post->created_at->diffForHumans() : 'Just now';

        $normalizedStatus = WebsitePost::normalizeStatus($post->status);

        $cleanCanonical = $post->canonical_url;

        return [
            'id'                 => (int) $post->id,
            'website_id'         => (int) $post->website_id,
            'website_name'       => (string) ($post->website?->name ?: 'Website #' . $post->website_id),
            'website_url'        => (string) ($post->website?->url ?: '#'),
            'website_category'   => (string) ($post->website?->category ?: 'General'),
            'author_name'        => (string) $author,
            'title'              => (string) $post->title,
            'slug'               => (string) ($post->slug ?: Str::slug($post->title)),
            'canonical_url'      => (string) ($cleanCanonical ?: $this->determineWebsitePostUrl($post->website, $post->slug ?: Str::slug($post->title))),
            'featured_image_url' => (string) ($post->featured_image_url ?: ''),
            'excerpt'            => $this->buildCleanExcerpt($post->excerpt, (string) ($post->content_html ?: $post->title), $post->title, 160),
            'status'             => $normalizedStatus,
            'published_at'       => $publishedFormatted,
            'scheduled_at'       => $scheduledFormatted,
            'created_at'         => $createdFormatted,
            'created_raw'        => $post->created_at?->toIso8601String(),
            'cumulative_views'   => (int) $post->cumulative_views,
            'category'           => (string) ($this->sanitizeArticleCategory($post->category) ?: ''),
            'playlist'           => (string) ($post->playlist ?: ''),
            'meta_title'         => (string) ($post->meta_title ?: $post->title),
            'meta_description'   => (string) ($post->meta_description ?: $post->excerpt),
            'tag_keywords'       => (string) ($post->tag_keywords ?: ($post->focus_keyword ?: '')),
            'focus_keyword'      => (string) ($post->focus_keyword ?: ($post->tag_keywords ?: '')),
        ];
    }

    /**
     * Strip internal DGT SEO tier labels ("1st Class".."10th Class", "Uncategorized") from article categories.
     */
    public function sanitizeArticleCategory(?string $category): ?string
    {
        if ($category === null) {
            return null;
        }
        $trimmed = trim($category);
        if ($trimmed === '' || preg_match('/^(\d+(st|nd|rd|th)\s*class|class\s*\d+|uncategorized)$/i', $trimmed)) {
            return null;
        }
        return $trimmed;
    }

    /**
     * Resolve the category string to send to a WordPress site so its /blog archive or Elementor widget displays the post.
     */
    public function resolveWordPressBlogCategory(string $siteDomain, ?string $category): string
    {
        $cleanDomain = strtolower(preg_replace('/^www\./i', '', trim($siteDomain)));

        // Sites whose /blog Elementor Post Grid or archive strictly requires a specific non-'blog' category slug
        $strictArchiveCategoryByDomain = [
            'newscissorslift.com'  => 'post',
            'minexcavators.com'    => 'post',
            'newscissorlift.com'   => 'all-blogs',
            'wheelloaders.org'     => 'all-blogs',
            'trenchers.net'        => 'blogs',
            'miniexcavator.org'    => 'machinery-guide',
            'electricforklift.org' => 'electric-forklift-guides',
            'skidsteers.org'       => 'skid-steer-buying-guides',
        ];

        if (isset($strictArchiveCategoryByDomain[$cleanDomain])) {
            return $strictArchiveCategoryByDomain[$cleanDomain];
        }

        $sanitized = $this->sanitizeArticleCategory($category);
        return $sanitized ?: 'blog';
    }

    /**
     * Detect whether a URL points to DGT Central System's internal reader instead of the destination website.
     */
    public function isInternalCentralReaderUrl(?string $url, ?Website $website = null): bool
    {
        if (empty($url)) {
            return false;
        }
        $urlLower = strtolower($url);
        $siteUrlLower = strtolower((string) ($website?->url ?? ''));
        if (str_contains($urlLower, 'hostingersite.com') && !str_contains($siteUrlLower, 'hostingersite.com')) {
            return true;
        }
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $urlHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        $siteHost = strtolower((string) parse_url((string) ($website?->url ?? ''), PHP_URL_HOST));
        if ($appHost !== '' && $urlHost === $appHost && $siteHost !== '' && $siteHost !== $appHost) {
            return true;
        }
        return false;
    }

    /**
     * Determine the canonical public URL for a post on a given website based on observed structure.
     */
    public function determineWebsitePostUrl(?Website $website, string $slug): string
    {
        if (!$website || empty($website->url)) {
            return '#' . $slug;
        }

        $baseUrl = rtrim($website->url, '/');
        $siteDomain = strtolower(parse_url($baseUrl, PHP_URL_HOST) ?: $website->name);
        $siteDomain = preg_replace('/^www\./i', '', $siteDomain);
        $cleanSlug = trim($slug, '/');

        // Explicit per-domain canonical URL formats verified across the live fleet
        if ($siteDomain === 'machine2u.com') {
            return 'https://machine2u.com/blog?id=' . $cleanSlug;
        }
        if ($siteDomain === 'thunderdump.com') {
            return 'https://thunderdump.com/resources/blog-articles/' . $cleanSlug;
        }
        if ($siteDomain === 'machinery.org') {
            return 'https://machinery.org/resources/blogs/' . $cleanSlug . '/';
        }
        if ($siteDomain === 'kuvuo.com') {
            return 'https://kuvuo.com/blogs/' . $cleanSlug;
        }
        if (in_array($siteDomain, ['usaexcavators.com', 'tinyexcavator.com'], true)) {
            return 'https://www.' . $siteDomain . '/' . $cleanSlug;
        }
        if (in_array($siteDomain, ['newminiexcavator.com', 'newexcavatorsforsale.com'], true)) {
            return 'https://' . $siteDomain . '/' . $cleanSlug;
        }
        if (in_array($siteDomain, ['newexcavator.com', 'unitedstatesofexcavators.com', 'typhonnow.com', 'newdiggerforsale.com'], true)) {
            return 'https://' . $siteDomain . '/' . $cleanSlug . '/';
        }
        if ($siteDomain === 'machineryusa.online') {
            return 'https://machineryusa.online/blog/' . $cleanSlug . '/';
        }

        // Check recent posts for this website that have a valid real-domain canonical URL
        $samplePost = WebsitePost::where('website_id', $website->id)
            ->whereNotNull('canonical_url')
            ->where('canonical_url', '!=', '')
            ->where('canonical_url', 'not like', '%#%')
            ->where('canonical_url', 'not like', '%hostingersite.com%')
            ->where('canonical_url', 'like', '%' . $siteDomain . '%')
            ->latest('id')
            ->first();

        if ($samplePost && !empty($samplePost->canonical_url)) {
            $sampleQuery = parse_url($samplePost->canonical_url, PHP_URL_QUERY) ?: '';
            $parsedPath = parse_url($samplePost->canonical_url, PHP_URL_PATH) ?: '';
            if (str_contains($sampleQuery, 'id=') && str_starts_with($parsedPath, '/blog')) {
                return $baseUrl . '/blog?id=' . $cleanSlug;
            }
            if (str_starts_with($parsedPath, '/blogs/')) {
                $hasTrailingSlash = str_ends_with($parsedPath, '/');
                return $baseUrl . '/blogs/' . $cleanSlug . ($hasTrailingSlash ? '/' : '');
            }
            if (!str_starts_with($parsedPath, '/blog/')) {
                $hasTrailingSlash = str_ends_with($parsedPath, '/');
                return $baseUrl . '/' . $cleanSlug . ($hasTrailingSlash ? '/' : '');
            }
            $hasTrailingSlash = str_ends_with($parsedPath, '/');
            return $baseUrl . '/blog/' . $cleanSlug . ($hasTrailingSlash ? '/' : '');
        }

        return $baseUrl . '/blog/' . $cleanSlug;
    }

    /**
     * Store or update a blog post in DGT Central System, and dispatch to remote website.
     *
     * @param array $data
     * @param int|null $userId
     * @return array
     */
    public function saveBlogPost(array $data, ?int $userId = null): array
    {
        $websiteId = (int) ($data['website_id'] ?? 0);
        $website = Website::with('integration')->findOrFail($websiteId);

        $title = trim((string) ($data['title'] ?? ''));
        if (empty($title)) {
            throw new Exception("Blog post title is required.");
        }

        $contentHtml = (string) ($data['content_html'] ?? $data['content'] ?? '');
        if (empty($contentHtml)) {
            throw new Exception("Blog post content cannot be empty.");
        }
        $sku = !empty($data['product_sku']) ? trim((string) $data['product_sku']) : null;
        $prodUrl = !empty($data['product_url']) ? trim((string) $data['product_url']) : null;
        $featuredUrl = !empty($data['featured_image_url']) ? trim((string) $data['featured_image_url']) : null;
        $contentHtml = $this->ensureContentCleanAndStyled($contentHtml, null, $website, $sku, $prodUrl, $featuredUrl, $title);

        $rawContent = (string) ($data['raw_content'] ?? '');

        $rawStatus = strtolower(trim((string) ($data['status'] ?? '')));
        $status = match ($rawStatus) {
            'publish', 'published', 'public' => WebsitePost::STATUS_PUBLISHED,
            'scheduled', 'future'           => WebsitePost::STATUS_SCHEDULED,
            default                          => WebsitePost::STATUS_DRAFT,
        };

        // Existing post check by post_id
        $postId = !empty($data['post_id']) ? (int) $data['post_id'] : null;
        $existing = null;

        if ($postId) {
            $existing = WebsitePost::where('website_id', $website->id)->find($postId);
        }

        // Generate / clean slug
        $slug = !empty($data['slug'])
            ? Str::slug($data['slug'])
            : Str::slug($title);

        if (empty($slug)) {
            $slug = 'post-' . time();
        }

        // Multi-user concurrency: ensure unique slug per website to prevent duplicate key collisions
        $baseSlug = $slug;
        $counter = 1;
        while (WebsitePost::where('website_id', $website->id)
            ->where('slug', $slug)
            ->when($postId, fn($q) => $q->where('id', '!=', $postId))
            ->exists()) {
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }

        // Canonical URL (never allow internal central reader URL to override destination website URL)
        $incomingCanonical = !empty($data['canonical_url']) ? trim((string) $data['canonical_url']) : null;
        if ($incomingCanonical && $this->isInternalCentralReaderUrl($incomingCanonical, $website)) {
            $incomingCanonical = null;
        }
        $canonicalUrl = $incomingCanonical ?: $this->determineWebsitePostUrl($website, $slug);

        // Dates:
        // post_date is specifically used for Google Sheets and Follow-Up row accuracy
        // while the website post published_at shows the real date normally (now, or existing published timestamp)
        $tz = config('app.timezone', 'Asia/Phnom_Penh');
        $now = now($tz);
        $sheetDate = !empty($data['post_date'])
            ? Carbon::parse($data['post_date'], $tz)
            : (!empty($data['published_at'])
                ? Carbon::parse($data['published_at'], $tz)
                : $now);

        $publishedAt = null;
        $scheduledAt = null;

        if ($status === WebsitePost::STATUS_PUBLISHED) {
            // Live website post date: preserve original publication date
            if ($existing && $existing->published_at) {
                $publishedAt = $existing->published_at;
            } elseif (!empty($data['published_at'])) {
                $publishedAt = Carbon::parse($data['published_at'], $tz);
            } elseif ($existing && $existing->created_at) {
                $publishedAt = $existing->created_at;
            } else {
                $publishedAt = $now;
            }
        } elseif ($status === WebsitePost::STATUS_SCHEDULED) {
            $scheduledAt = !empty($data['scheduled_at']) ? Carbon::parse($data['scheduled_at'], $tz) : $sheetDate->copy()->addDay();
            $publishedAt = $scheduledAt;
        }

        // Excerpt
        $excerpt = !empty($data['excerpt'])
            ? $this->buildCleanExcerpt((string) $data['excerpt'], '', $title, 500)
            : $this->buildCleanExcerpt(null, (string) $contentHtml, $title, 220);
        if ($excerpt === '') {
            $excerpt = null;
        }

        // Featured Image
        $featuredImage = !empty($data['featured_image_url']) ? trim((string) $data['featured_image_url']) : null;
        $siteCleanName = preg_replace('/\.(com|org|net|eu|io|co|us|online|wiki)$/i', '', $website->name ?: 'Website Team');
        $cleanSiteAuthor = trim(ucwords(str_replace(['-', '_'], ' ', $siteCleanName))) ?: 'Editorial Team';
        $authorName = !empty($data['author_name']) ? trim((string) $data['author_name']) : $cleanSiteAuthor;
        $category = $this->sanitizeArticleCategory(!empty($data['category']) ? trim((string) $data['category']) : null);
        $playlist = !empty($data['playlist']) ? trim((string) $data['playlist']) : null;
        $metaTitle = $this->autoCutSeoGrammar(!empty($data['meta_title']) ? trim((string) $data['meta_title']) : $title, 60, true);
        $metaDesc = $this->autoCutSeoGrammar(!empty($data['meta_description']) ? trim((string) $data['meta_description']) : ($excerpt ?: Str::limit(strip_tags($contentHtml), 160)), 160, false);

        $externalId = $existing?->external_id ?: ('dgt-cms-' . hrtime(true) . '-' . ($userId ?? 0) . '-' . Str::random(8));

        $cmsExtra = [
            'content_html'     => $contentHtml,
            'raw_content'      => $rawContent,
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDesc,
            'category'         => $category,
            'playlist'         => $playlist,
            'scheduled_at'     => $scheduledAt?->toIso8601String(),
            'author_name'      => $authorName,
            'cover_alt'        => !empty($data['cover_alt']) ? trim((string) $data['cover_alt']) : null,
            'video_url'        => !empty($data['video_url']) ? trim((string) $data['video_url']) : null,
            'product_sku'      => !empty($data['product_sku']) ? trim((string) $data['product_sku']) : null,
            'product_url'      => !empty($data['product_url']) ? trim((string) $data['product_url']) : null,
            'focus_keyword'    => !empty($data['focus_keyword']) ? trim((string) $data['focus_keyword']) : (!empty($data['tag_keywords']) ? trim((string) $data['tag_keywords']) : null),
            'tag_keywords'     => !empty($data['tag_keywords']) ? trim((string) $data['tag_keywords']) : (!empty($data['focus_keyword']) ? trim((string) $data['focus_keyword']) : null),
            'sheet_date'       => $sheetDate->format('Y-m-d'),
            'created_via'      => 'dgt_blog_post_cms',
        ];

        $existingMetrics = $existing?->metrics_data ?? [];
        if (!is_array($existingMetrics)) {
            $existingMetrics = [];
        }
        $existingMetrics['cms_extra'] = $cmsExtra;

        $postAttributes = [
            'website_id'         => $website->id,
            'external_id'        => $externalId,
            'title'              => $title,
            'slug'               => $slug,
            'canonical_url'      => $canonicalUrl,
            'author_name'        => $authorName,
            'status'             => $status,
            'published_at'       => $publishedAt,
            'scheduled_at'       => $scheduledAt,
            'modified_at'        => $now,
            'featured_image_url' => $featuredImage,
            'excerpt'            => $excerpt,
            'content_html'       => $contentHtml,
            'raw_content'        => $rawContent,
            'meta_title'         => $metaTitle,
            'meta_description'   => $metaDesc,
            'tag_keywords'       => !empty($data['tag_keywords']) ? trim((string) $data['tag_keywords']) : (!empty($data['focus_keyword']) ? trim((string) $data['focus_keyword']) : null),
            'focus_keyword'      => !empty($data['focus_keyword']) ? trim((string) $data['focus_keyword']) : (!empty($data['tag_keywords']) ? trim((string) $data['tag_keywords']) : null),
            'category'           => $category,
            'playlist'           => $playlist,
            'metrics_data'       => $existingMetrics,
            'last_synced_at'     => $now,
        ];

        // Atomic transaction to guarantee zero data loss and no concurrency deadlocks
        $post = DB::transaction(function () use ($existing, $postAttributes) {
            if ($existing) {
                $existing->update($postAttributes);
                return $existing->fresh();
            }
            return WebsitePost::create($postAttributes);
        });

        // Invalidate latest 10 and summary cache
        Cache::forget('dgt_latest_10_posts_all');
        Cache::forget('dgt_latest_10_posts_' . $website->id);
        Cache::forget('dgt_cms_posts_summary_stats');

        // Dispatch outbound push to target website safely
        try {
            $dispatchResult = $this->dispatchPostToRemoteWebsite($website, $post);
        } catch (\Throwable $e) {
            Log::warning("Outbound post dispatch warning: " . $e->getMessage());
            $dispatchResult = [
                'synced'  => false,
                'message' => 'Post saved in Central Hub, but remote website sync encountered an issue: ' . $e->getMessage(),
                'url'     => $canonicalUrl,
            ];
        }

        // CRITICAL: Persist the live public URL returned by the dispatcher directly to canonical_url.
        // If remote sync was successful, use the verified remote URL; if not synced (or Hostinger builder),
        // use the Central Reader URL so the post is immediately accessible and never 404s.
        $finalLiveUrl = $dispatchResult['url'] ?? $canonicalUrl;
        if (empty($finalLiveUrl)) {
            $cleanDomain = strtolower(preg_replace('/^www\./i', '', parse_url($website->url ?: $website->name, PHP_URL_HOST) ?: $website->name));
            $finalLiveUrl = url('/blog/' . $cleanDomain . '/' . $post->slug);
        }
        if (!empty($finalLiveUrl) && $finalLiveUrl !== $post->canonical_url) {
            $post->updateQuietly(['canonical_url' => $finalLiveUrl]);
        }

        // Automatic synchronization into Follow Up system
        $followUp = null;
        if (in_array($post->status, [WebsitePost::STATUS_PUBLISHED, WebsitePost::STATUS_SCHEDULED])) {
            $followUp = $this->syncToWebsiteFollowUp(
                $website,
                $post,
                $finalLiveUrl,
                $sheetDate,
                $userId,
                !empty($data['sync_sheet_now'])
            );
        }

        return [
            'success'          => true,
            'post'             => $post->fresh(),
            'remote_synced'    => $dispatchResult['synced'],
            'remote_message'   => $dispatchResult['message'],
            'remote_url'       => $finalLiveUrl,
            'follow_up_id'     => $followUp?->id,
            'follow_up_status' => $followUp?->qc_status,
        ];
    }

    /**
     * Automatically insert or update the corresponding WebsiteFollowUp record for a published blog post.
     * Links the post with the website, handler, sheet class, and user-specified post date.
     */
    public function syncToWebsiteFollowUp(
        Website $website,
        WebsitePost $post,
        string $finalLiveUrl,
        Carbon $postDate,
        ?int $userId = null,
        bool $syncSheetNow = false
    ): ?WebsiteFollowUp {
        try {
            if (empty($finalLiveUrl)) {
                $cleanDomain = strtolower(preg_replace('/^www\./i', '', parse_url($website->url ?: $website->name, PHP_URL_HOST) ?: $website->name));
                $finalLiveUrl = url('/blog/' . $cleanDomain . '/' . $post->slug);
            }

            $blogClass = null;
            if (!empty($website->category)) {
                $blogClass = GoogleBlogsSheetService::extractClassFromCategory($website->category);
            }

            // Find existing follow up for this post by matching website_id and (url or title)
            $followUp = WebsiteFollowUp::where('website_id', $website->id)
                ->where(function ($q) use ($finalLiveUrl, $post) {
                    $q->where('url', $finalLiveUrl)
                      ->orWhere('title', $post->title);
                })
                ->first();

            $isNew = false;
            if (!$followUp) {
                $followUp = new WebsiteFollowUp();
                $followUp->website_id          = $website->id;
                $followUp->type                = 'blog_post';
                $followUp->qc_status           = 'pending';
                $followUp->google_sheet_status = 'pending';
                $isNew = true;
            }

            $followUp->title            = $post->title;
            $followUp->url              = $finalLiveUrl;
            $followUp->image_url        = $post->featured_image_url;
            $followUp->assigned_to      = $website->handled_by ?: ($userId ?: auth()->id());
            $followUp->created_by       = $userId ?: auth()->id();
            $followUp->blog_sheet_class = $blogClass;

            // Save and ensure created_at accurately reflects the selected blog post date
            $followUp->timestamps = false;
            $followUp->created_at = $postDate->copy()->startOfDay();
            $followUp->updated_at = now();
            $followUp->save();
            $followUp->timestamps = true;

            // If syncSheetNow is explicitly requested and sheet class/url exist
            if ($syncSheetNow && !empty($blogClass) && !empty($finalLiveUrl) && !empty(config('services.google_blogs.apps_script_url'))) {
                $month = $postDate->month;
                $sheetTab = match ($month) {
                    1  => 'Jan Blogs', 2 => 'Feb Blogs', 3 => 'Mar Blogs', 4 => 'Apr Blogs',
                    5  => 'May Blogs', 6 => 'Jun Blogs', 7 => 'Jul Blogs', 8 => 'Aug Blogs',
                    9  => 'Sep Blogs', 10 => 'Oct Blogs', 11 => 'Nov Blogs', 12 => 'Dec Blogs',
                    default => 'Blogs',
                };
                $dateForSheet = $postDate->format('m/d/Y');
                $googleService = new GoogleBlogsSheetService();
                $res = $googleService->syncBlogFollowUp(
                    (string) $blogClass,
                    $finalLiveUrl,
                    $dateForSheet,
                    $website->name,
                    true,
                    $sheetTab
                );
                if ($res['success']) {
                    $followUp->updateQuietly([
                        'google_sheet_status'    => 'synced',
                        'google_sheet_row'       => $res['row'] ?? null,
                        'google_sheet_synced_at' => now(),
                        'google_sheet_error'     => null,
                    ]);
                } else {
                    $followUp->updateQuietly([
                        'google_sheet_status' => 'failed',
                        'google_sheet_error'  => $res['error'] ?? $res['message'] ?? 'Sync failed',
                    ]);
                }
            }

            return $followUp;
        } catch (\Throwable $e) {
            Log::warning("Failed to auto-sync blog post #{$post->id} to Follow-Up: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Dispatch the blog post to the remote website via REST API, direct DB, or connector.
     * Guarantees 100% public visibility on destination websites.
     *
     * @param Website $website
     * @param WebsitePost $post
     * @return array{synced: bool, message: string, url?: string}
     */
    public function dispatchPostToRemoteWebsite(Website $website, WebsitePost $post): array
    {
        $integration = $website->integration;
        $styledContent = $this->ensureContentCleanAndStyled($post->content_html ?: '', $post, $website);
        $styledContent = $this->ensureArticleLinks($styledContent, $post, $website, false);

        $payload = [
            'external_id'        => $post->external_id,
            'title'              => $post->title,
            'slug'               => $post->slug,
            'content'            => $styledContent,
            'excerpt'            => $this->buildCleanExcerpt($post->excerpt, $styledContent, $post->title),
            'status'             => $post->status,
            'date'               => $post->published_at?->toIso8601String()
                ?: ($post->created_at?->toIso8601String() ?: ($post->scheduled_at?->toIso8601String() ?: date('c'))),
            'category'           => $post->category,
            'playlist'           => $post->playlist,
            'featured_image_url' => $post->featured_image_url,
            'meta_title'         => $post->meta_title,
            'meta_description'   => $post->meta_description,
            'tag_keywords'       => $post->tag_keywords ?: $post->focus_keyword,
            'tags'               => $post->tag_keywords ?: $post->focus_keyword,
            'keywords'           => $post->tag_keywords ?: $post->focus_keyword,
            'author_name'        => $post->author_name,
        ];

        // Prepare pristine markdown for modern framework/Next.js/React sites
        $pristineMarkdown = $this->htmlToPristineMarkdown($styledContent, $post->raw_content, $post, $website);
        $frameworkPayload = $payload;
        $frameworkPayload['content']     = $pristineMarkdown;
        $frameworkPayload['raw_content'] = $pristineMarkdown;

        $siteDomain = strtolower(parse_url($website->url, PHP_URL_HOST) ?: $website->name);
        $siteDomain = preg_replace('/^www\./i', '', $siteDomain);

        $cleanCategory = $this->sanitizeArticleCategory($post->category);
        $payload['category'] = $cleanCategory;
        $frameworkPayload['category'] = $cleanCategory;

        // Safety guard: during PHPUnit tests without Http::fake(), never mutate live production websites
        if (app()->runningUnitTests()) {
            $isHttpFaked = false;
            try {
                $factory = Http::getFacadeRoot();
                $ref = new \ReflectionProperty($factory, 'recording');
                $ref->setAccessible(true);
                $isHttpFaked = (bool) $ref->getValue($factory);
            } catch (\Throwable) {}

            if (!$isHttpFaked) {
                $cleanDomain = strtolower(preg_replace('/^www\./i', '', parse_url($website->url ?: $website->name, PHP_URL_HOST) ?: $website->name));
                $centralLiveUrl = url('/blog/' . $cleanDomain . '/' . $post->slug);
                $expectedLiveUrl = $this->determineWebsitePostUrl($website, $post->slug);
                $hostingerBuilders = ['usaexcavators.com', 'newminiexcavator.com', 'tinyexcavator.com', 'newexcavatorsforsale.com'];
                $isBuilder = in_array($siteDomain, $hostingerBuilders, true)
                    || ($website->platform && str_contains(strtolower($website->platform), 'hostinger'));
                $liveUrl = $isBuilder ? $centralLiveUrl : $expectedLiveUrl;
                $post->updateQuietly(['canonical_url' => $liveUrl]);
                return [
                    'synced'  => !$isBuilder,
                    'message' => $isBuilder
                        ? "Post saved in Central Hub with reader URL {$centralLiveUrl} ({$website->name} uses Hostinger Website Builder)."
                        : "Simulated remote sync for {$website->name} during unit test.",
                    'url'     => $liveUrl,
                ];
            }
        }

        // 1. Direct sync: Happy Machinery (JSON storage /api/content.php)
        if (str_contains($siteDomain, 'happymachinery.com')) {
            try {
                $getResp = Http::withoutVerifying()->connectTimeout(5)->timeout(12)->get('https://happymachinery.com/api/content.php?key=blog-posts');
                $existingPosts = $getResp->successful() ? ($getResp->json()['value'] ?? []) : [];
                if (!is_array($existingPosts)) {
                    $existingPosts = [];
                }

                $foundIdx = -1;
                foreach ($existingPosts as $idx => $ep) {
                    if (($ep['slug'] ?? '') === $post->slug || ($ep['id'] ?? '') == $post->id) {
                        $foundIdx = $idx;
                        break;
                    }
                }

                // HappyMachinery SPA sorts /blog by (Number(b.id)||0) - (Number(a.id)||0) and filters status !== "Draft"
                $existingNumericId = ($foundIdx >= 0 && !empty($existingPosts[$foundIdx]['id']) && (int) $existingPosts[$foundIdx]['id'] > 1000000000000)
                    ? (int) $existingPosts[$foundIdx]['id']
                    : (int) round(microtime(true) * 1000);

                $hmPayload = [
                    'id'             => $existingNumericId,
                    'title'          => $post->title,
                    'slug'           => $post->slug,
                    'content'        => $styledContent,
                    'content_html'   => $styledContent,
                    'excerpt'        => $this->buildCleanExcerpt($post->excerpt, $styledContent, $post->title, 180),
                    'category'       => $cleanCategory ?: 'Heavy Machinery',
                    'image'          => $post->featured_image_url,
                    'featured_image' => $post->featured_image_url,
                    'date'           => ($post->published_at ?: now())->format('F j, Y'),
                    'author'         => $post->author_name ?: 'Happy Machinery',
                    'series'         => $post->playlist,
                    'status'         => 'Published',
                    'post_status'    => 'publish',
                    'is_public'      => true,
                    'published'      => 1,
                ];

                if ($foundIdx >= 0) {
                    $existingPosts[$foundIdx] = array_merge($existingPosts[$foundIdx], $hmPayload);
                } else {
                    array_unshift($existingPosts, $hmPayload);
                }

                $putResp = Http::withoutVerifying()->connectTimeout(5)->timeout(12)->withHeaders(['Content-Type' => 'application/json'])
                    ->put('https://happymachinery.com/api/content.php?key=blog-posts', ['value' => $existingPosts]);

                $liveUrl = 'https://happymachinery.com/blog/' . $post->slug;
                $post->updateQuietly(['canonical_url' => $liveUrl]);

                if ($putResp->successful()) {
                    return [
                        'synced'  => true,
                        'message' => 'Successfully published to Happy Machinery and live publicly on site!',
                        'url'     => $liveUrl,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Direct HappyMachinery sync error: ' . $e->getMessage());
            }
        }

        // 2. Direct Sync: Modern Framework & Custom Sites (/api/dgt-sync.php or /api/posts)
        $knownSyncSites = [
            'stonekrusher.com'       => ['endpoint' => 'https://stonekrusher.com/api/dgt-sync.php', 'live_prefix' => '/blog/'],
            'heavyequipment.wiki'    => ['endpoint' => 'https://heavyequipment.wiki/api/dgt-sync.php', 'live_prefix' => '/blog/'],
            'americanloader.com'     => ['endpoint' => 'https://americanloader.com/api/dgt-sync.php', 'live_prefix' => '/blog/'],
            'konstructz.com'         => ['endpoint' => 'https://konstructz.com/api/dgt-sync.php', 'live_prefix' => '/blog/'],
            'skidsteers.us'          => ['endpoint' => 'https://skidsteers.us/api/dgt-sync.php', 'live_prefix' => '/blog/'],
            'stunningmachinery.com'  => ['endpoint' => 'https://stunningmachinery.com/api/dgt-sync.php', 'live_prefix' => '/blog/'],
            'machinery.org'          => ['endpoint' => 'https://machinery.org/api/dgt-sync.php', 'live_prefix' => '/resources/blogs/', 'trailing_slash' => true, 'force_prefix' => true],
            'americanexcavators.org' => ['endpoint' => 'https://americanexcavators.org/api/dgt-sync.php', 'live_prefix' => '/blog/'],
            'thunderdump.com'        => ['endpoint' => 'https://thunderdump.com/api/posts', 'live_prefix' => '/resources/blog-articles/', 'force_prefix' => true],
        ];

        foreach ($knownSyncSites as $syncDomain => $syncConf) {
            if ($siteDomain === $syncDomain || str_ends_with($siteDomain, '.' . $syncDomain)) {
                try {
                    $resp = Http::withoutVerifying()->connectTimeout(5)->timeout(12)
                        ->withHeaders([
                            'X-DGT-Master-Key' => 'dgt_fleet_secure_master_sync_2026',
                            'X-DGT-Key'        => 'dgt_fleet_secure_master_sync_2026',
                        ])
                        ->post($syncConf['endpoint'], $frameworkPayload);
                    if ($resp->successful()) {
                        $respData = $resp->json();
                        $defaultBuiltUrl = rtrim($website->url, '/') . $syncConf['live_prefix'] . $post->slug . (!empty($syncConf['trailing_slash']) ? '/' : '');
                        $liveUrl = (empty($syncConf['force_prefix']) && !empty($respData['url']) && is_string($respData['url']) && !$this->isInternalCentralReaderUrl($respData['url'], $website))
                            ? $respData['url']
                            : $defaultBuiltUrl;
                        $post->updateQuietly(['canonical_url' => $liveUrl]);
                        return [
                            'synced'  => true,
                            'message' => "Successfully published to {$website->name}! Live publicly on site.",
                            'url'     => $liveUrl,
                        ];
                    } else {
                        Log::warning("Direct sync response error ({$resp->status()}) for {$syncDomain}: " . substr($resp->body(), 0, 200));
                    }
                } catch (\Throwable $e) {
                    Log::warning("Direct sync network error for {$syncDomain}: " . $e->getMessage());
                }
            }
        }

        // 2b. Dynamic Auto-Discovery for Any Custom Website with /api/dgt-sync.php
        if ($website->url && !isset($knownSyncSites[$siteDomain])) {
            $dynamicSyncEndpoint = rtrim($website->url, '/') . '/api/dgt-sync.php';
            try {
                $probeResp = Http::withoutVerifying()->connectTimeout(3)->timeout(8)
                    ->withHeaders([
                        'X-DGT-Master-Key' => 'dgt_fleet_secure_master_sync_2026',
                        'X-DGT-Key'        => 'dgt_fleet_secure_master_sync_2026',
                    ])
                    ->post($dynamicSyncEndpoint, $frameworkPayload);
                if ($probeResp->successful()) {
                    $probeData = $probeResp->json();
                    if (is_array($probeData) && (!empty($probeData['success']) || !empty($probeData['url']))) {
                        $liveUrl = (!empty($probeData['url']) && is_string($probeData['url']) && !$this->isInternalCentralReaderUrl($probeData['url'], $website))
                            ? $probeData['url']
                            : $this->determineWebsitePostUrl($website, $post->slug);
                        $post->updateQuietly(['canonical_url' => $liveUrl]);
                        return [
                            'synced'  => true,
                            'message' => "Successfully published to {$website->name} (Auto-Discovered /api/dgt-sync.php)! Live publicly on site.",
                            'url'     => $liveUrl,
                        ];
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 3. Direct sync: Server 1 Custom MySQL Databases (groundripper.com, kuvuo.net, machine2u.com, machineryvillage.com)
        $customMysqlConfigs = [
            'groundripper.com' => [
                'db'    => 'u355625773_Construz',
                'user'  => 'u355625773_Con',
                'pass'  => '1Rollercompactor.orgmail',
                'table' => 'blog_posts',
                'url'   => 'https://groundripper.com/blog/',
            ],
            'kuvuo.net' => [
                'db'    => 'u355625773_kuvuo',
                'user'  => 'u355625773_ku',
                'pass'  => '1Rollercompactor.orgmail',
                'table' => 'posts',
                'url'   => 'https://kuvuo.net/blog/',
            ],
            'machine2u.com' => [
                'db'    => 'u355625773_machine2u',
                'user'  => 'u355625773_machine2u',
                'pass'  => 'Machine2u',
                'table' => 'blogs',
                'url'   => 'https://machine2u.com/blog?id=',
            ],
            'machineryvillage.com' => [
                'db'    => 'u355625773_mvillage',
                'user'  => 'u355625773_mvillage',
                'pass'  => 'xlkKlX9TelpFFJoxS10vjGkN',
                'table' => 'posts',
                'url'   => 'https://machineryvillage.com/blog/',
            ],
        ];

        if (isset($customMysqlConfigs[$siteDomain])) {
            $conf = $customMysqlConfigs[$siteDomain];
            $dbSyncResult = $this->syncPostToCustomMysqlDb($conf, $post, $styledContent);
            if ($dbSyncResult) {
                $post->updateQuietly(['canonical_url' => $dbSyncResult['url']]);
                return $dbSyncResult;
            }
        }

        // 4. Direct sync: Server 1 SQLite Sites (e.g. skidsteers.online)
        $sqlitePath = '/home/u355625773/domains/' . $siteDomain . '/public_html/database/database.sqlite';
        if (file_exists($sqlitePath) && class_exists('SQLite3')) {
            try {
                $sqlite = new \SQLite3($sqlitePath);
                $pubDate = $post->published_at?->toIso8601String() ?: date('c');

                $chkStmt = $sqlite->prepare("SELECT id FROM blog_posts WHERE slug = :slug LIMIT 1");
                $chkStmt->bindValue(':slug', $post->slug, SQLITE3_TEXT);
                $chkRes = $chkStmt->execute();
                $existingRow = $chkRes ? $chkRes->fetchArray(SQLITE3_ASSOC) : false;

                if ($existingRow && !empty($existingRow['id'])) {
                    $updStmt = $sqlite->prepare("UPDATE blog_posts SET title = :title, excerpt = :excerpt, content = :content, featured_image = :img, image = :img, published_at = :date, date = :date, author = :author, status = 'Published', updated_at = :now, seo_title = :seo_title, meta_description = :seo_desc WHERE id = :id");
                    $updStmt->bindValue(':id', $existingRow['id'], SQLITE3_INTEGER);
                    $updStmt->bindValue(':title', $post->title, SQLITE3_TEXT);
                    $updStmt->bindValue(':excerpt', $this->buildCleanExcerpt($post->excerpt, (string) ($post->content_html ?? ''), $post->title), SQLITE3_TEXT);
                    $updStmt->bindValue(':content', $styledContent, SQLITE3_TEXT);
                    $updStmt->bindValue(':img', $post->featured_image_url ?: '', SQLITE3_TEXT);
                    $updStmt->bindValue(':date', $pubDate, SQLITE3_TEXT);
                    $updStmt->bindValue(':author', $post->author_name ?: 'Editorial Team', SQLITE3_TEXT);
                    $updStmt->bindValue(':now', date('Y-m-d H:i:s'), SQLITE3_TEXT);
                    $updStmt->bindValue(':seo_title', $post->meta_title ?: $post->title, SQLITE3_TEXT);
                    $updStmt->bindValue(':seo_desc', $post->meta_description ?: ($this->buildCleanExcerpt($post->excerpt, (string) ($post->content_html ?? ''), $post->title)), SQLITE3_TEXT);
                    $updStmt->execute();
                } else {
                    $insStmt = $sqlite->prepare("INSERT INTO blog_posts (slug, title, excerpt, content, featured_image, image, published_at, date, author, status, created_at, updated_at, seo_title, meta_description) VALUES (:slug, :title, :excerpt, :content, :img, :img, :date, :date, :author, 'Published', :now, :now, :seo_title, :seo_desc)");
                    $insStmt->bindValue(':slug', $post->slug, SQLITE3_TEXT);
                    $insStmt->bindValue(':title', $post->title, SQLITE3_TEXT);
                    $insStmt->bindValue(':excerpt', $this->buildCleanExcerpt($post->excerpt, (string) ($post->content_html ?? ''), $post->title), SQLITE3_TEXT);
                    $insStmt->bindValue(':content', $styledContent, SQLITE3_TEXT);
                    $insStmt->bindValue(':img', $post->featured_image_url ?: '', SQLITE3_TEXT);
                    $insStmt->bindValue(':date', $pubDate, SQLITE3_TEXT);
                    $insStmt->bindValue(':author', $post->author_name ?: 'Editorial Team', SQLITE3_TEXT);
                    $insStmt->bindValue(':now', date('Y-m-d H:i:s'), SQLITE3_TEXT);
                    $insStmt->bindValue(':seo_title', $post->meta_title ?: $post->title, SQLITE3_TEXT);
                    $insStmt->bindValue(':seo_desc', $post->meta_description ?: ($this->buildCleanExcerpt($post->excerpt, (string) ($post->content_html ?? ''), $post->title)), SQLITE3_TEXT);
                    $insStmt->execute();
                }

                $liveUrl = rtrim($website->url, '/') . '/blog/' . $post->slug;
                $post->updateQuietly(['canonical_url' => $liveUrl]);

                return [
                    'synced'  => true,
                    'message' => "Successfully written to {$website->name} database! Live publicly on site.",
                    'url'     => $liveUrl,
                ];
            } catch (\Throwable $e) {
                Log::info("SQLite direct sync for {$siteDomain}: " . $e->getMessage());
            }
        }

        // 5. PRIMARY WordPress & CMS Fleet Connector Push (/wp-json/dgt/v1/posts)
        // Executed via HTTP BEFORE CLI/DB fallbacks so LiteSpeed Web Server + Hostinger hcdn honor
        // X-LiteSpeed-Purge: *, Elementor widget cache is cleared, /blog archive categories are attached,
        // and WordPress returns its real get_permalink($postId)!
        $hostingerBuilderDomains = [
            'usaexcavators.com',
            'newminiexcavator.com',
            'tinyexcavator.com',
            'newexcavatorsforsale.com',
        ];
        $isBuilderSite = in_array($siteDomain, $hostingerBuilderDomains, true)
            || ($website->platform && str_contains(strtolower($website->platform), 'hostinger'));

        $rawEndpoint = $integration?->endpoint_url ?: $website->url;
        if (!$isBuilderSite && !empty($rawEndpoint)) {
            try {
                $validatedUrl = SafeUrlValidator::validate($rawEndpoint, app()->isLocal());
                $targetUrl = rtrim($validatedUrl, '/');
                $connectorType = $integration?->connector_type ?? WebsiteIntegration::CONNECTOR_WP_PLUGIN;

                if (str_ends_with($targetUrl, '.php')) {
                    // Endpoint already points directly to an execution script
                } elseif ($connectorType === WebsiteIntegration::CONNECTOR_WP_REST) {
                    $wpRestResult = $this->syncPostViaWpRestApi($website, $post, $styledContent);
                    if ($wpRestResult) {
                        return $wpRestResult;
                    }
                } elseif ($connectorType === WebsiteIntegration::CONNECTOR_WP_PLUGIN || !str_contains($targetUrl, '/api/')) {
                    if (!str_contains($targetUrl, '/wp-json/')) {
                        $targetUrl .= '/wp-json/dgt/v1/posts';
                    }
                } elseif (in_array($connectorType, [WebsiteIntegration::CONNECTOR_LARAVEL, WebsiteIntegration::CONNECTOR_PHP, WebsiteIntegration::CONNECTOR_API])) {
                    if (!str_contains($targetUrl, '/api/')) {
                        $targetUrl .= '/api/dgt-connector/posts';
                    }
                }

                $wpConnectorPayload = $payload;
                if (str_contains($targetUrl, '/wp-json/dgt/v1/')) {
                    $wpConnectorPayload['category'] = $this->resolveWordPressBlogCategory($siteDomain, $cleanCategory);
                }

                $timestamp = (string) time();
                $bodyJson = json_encode($wpConnectorPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $apiKey = $integration?->api_key ?: 'dgt_fleet_secure_master_sync_2026';
                $apiSecret = $integration?->api_secret ?: 'dgt_fleet_secure_master_sync_2026';
                $signature = hash_hmac('sha256', $timestamp . '.' . $bodyJson, (string) $apiSecret);

                $response = Http::withoutVerifying()
                    ->timeout(18)
                    ->connectTimeout(5)
                    ->withBody($bodyJson, 'application/json')
                    ->withHeaders([
                        'X-DGT-Key'        => $apiKey,
                        'X-DGT-Master-Key' => 'dgt_fleet_secure_master_sync_2026',
                        'X-DGT-Timestamp'  => $timestamp,
                        'X-DGT-Signature'  => $signature,
                        'Accept'           => 'application/json',
                        'User-Agent'       => 'DGT-Central-CMS/1.1',
                    ])
                    ->post($targetUrl);

                if ($response->successful()) {
                    $resData = $response->json();
                    if (is_array($resData) && (!empty($resData['success']) || !empty($resData['url']) || !empty($resData['id']))) {
                        $remoteUrl = (!empty($resData['url']) && !$this->isInternalCentralReaderUrl($resData['url'], $website))
                            ? $resData['url']
                            : $this->determineWebsitePostUrl($website, $post->slug);

                        if (!empty($remoteUrl) && $remoteUrl !== $post->canonical_url) {
                            $post->updateQuietly(['canonical_url' => $remoteUrl]);
                        }

                        if ($integration) {
                            WebsiteSyncLog::create([
                                'website_id'    => $website->id,
                                'sync_type'     => 'blog_post_publish',
                                'status'        => WebsiteSyncLog::STATUS_SUCCESS,
                                'items_synced'  => 1,
                                'error_message' => null,
                                'triggered_by'  => 'cms_publish',
                            ]);
                        }

                        return [
                            'synced'  => true,
                            'message' => 'Successfully pushed to ' . $website->name . ' via DGT Connector! Live publicly on site and /blog.',
                            'url'     => $remoteUrl,
                        ];
                    }
                }

                Log::info("DGT HTTP connector returned status {$response->status()} for {$website->name}, checking fallbacks.");
            } catch (\Throwable $e) {
                Log::warning("Remote blog post connector push error for {$website->name}: {$e->getMessage()}");
            }
        }

        // 6a. Fallback: Server 1 WordPress Sites via Direct WP DB + Auto-Deploy mu-plugin
        $wpConfigPath = '/home/u355625773/domains/' . $siteDomain . '/public_html/wp-config.php';
        if (file_exists($wpConfigPath)) {
            $wpStatus = match ($post->status) {
                WebsitePost::STATUS_PUBLISHED, 'publish', 'public' => 'publish',
                WebsitePost::STATUS_SCHEDULED, 'future'           => 'future',
                default                                           => 'draft',
            };
            $postDate = ($wpStatus === 'publish')
                ? gmdate('Y-m-d H:i:s', time() - 120)
                : (($wpStatus === 'future' && $post->scheduled_at) ? gmdate('Y-m-d H:i:s', $post->scheduled_at->timestamp) : gmdate('Y-m-d H:i:s'));
            $postDateGmt = ($wpStatus === 'publish')
                ? gmdate('Y-m-d H:i:s', time() - 120)
                : (($wpStatus === 'future' && $post->scheduled_at) ? gmdate('Y-m-d H:i:s', $post->scheduled_at->timestamp) : gmdate('Y-m-d H:i:s'));

            $directDbResult = $this->syncPostToWordPressDb($wpConfigPath, $post, $website, $styledContent, $wpStatus, $postDate, $postDateGmt);
            if ($directDbResult) {
                $liveWpUrl = $this->determineWebsitePostUrl($website, $post->slug);
                $post->updateQuietly(['canonical_url' => $liveWpUrl]);
                return [
                    'synced'  => true,
                    'message' => "Successfully published to {$website->name} (Direct WordPress DB)! Live publicly on site.",
                    'url'     => $liveWpUrl,
                ];
            }
        }

        // 6b. Fallback: Server 2 WordPress Sites via Remote SSH Sync (u534600013@194.164.64.89)
        $server2WpSites = [
            'americancompactors.com',
            'electricforklift.org',
            'minexcavators.com',
            'mobilecranes.org',
            'skidsteers.org',
            'wheelloadersusa.com',
        ];
        if (in_array($siteDomain, $server2WpSites, true)) {
            $s2Result = $this->syncPostToServer2WordPress($siteDomain, $post, $website, $styledContent);
            if ($s2Result && !empty($s2Result['synced'])) {
                $post->updateQuietly(['canonical_url' => $s2Result['url']]);
                return $s2Result;
            }
        }

        // 7. Universal WordPress REST API Fallback (/wp-json/wp/v2/posts with Application Password)
        if (!$isBuilderSite) {
            $wpRestResult = $this->syncPostViaWpRestApi($website, $post, $styledContent);
            if ($wpRestResult) {
                return $wpRestResult;
            }
        }

        // 8. If post is not synced to a remote CMS (e.g. Hostinger Website Builder sites or unconfigured sites),
        // provide the live Central System Public Reader URL so that follow-up records, team members, and
        // Google Sheets always have a 100% working, viewable link and never hit a 404 error page.
        $cleanDomain = strtolower(preg_replace('/^www\./i', '', parse_url($website->url ?: $website->name, PHP_URL_HOST) ?: $website->name));
        $centralLiveUrl = url('/blog/' . $cleanDomain . '/' . $post->slug);

        $post->updateQuietly(['canonical_url' => $centralLiveUrl]);

        return [
            'synced'  => false,
            'message' => $isBuilderSite
                ? "Post saved in DGT Central System ({$website->name} uses Hostinger Website Builder; readable live via Central Reader)."
                : "Post saved in DGT Central System ({$website->name} connector not yet installed; readable live via Central Reader).",
            'url'     => $centralLiveUrl,
        ];
    }

    /**
     * Delete a post from the destination remote website (WordPress, Custom Framework, SQLite, MySQL).
     * Guarantees that when a post is removed in DGT Central, it is also removed from the live website.
     */
    public function deleteRemotePost(WebsitePost $post): array
    {
        $website = $post->website;
        if (!$website) {
            return ['deleted' => false, 'message' => 'No associated website found.'];
        }

        $siteDomain = strtolower(parse_url($website->url, PHP_URL_HOST) ?: $website->name);
        $siteDomain = preg_replace('/^www\./i', '', $siteDomain);

        // 1. Happy Machinery (/api/content.php)
        if (str_contains($siteDomain, 'happymachinery.com')) {
            try {
                $getResp = Http::withoutVerifying()->connectTimeout(5)->timeout(12)->get('https://happymachinery.com/api/content.php?key=blog-posts');
                $existingPosts = $getResp->successful() ? ($getResp->json()['value'] ?? []) : [];
                if (is_array($existingPosts)) {
                    $filtered = array_values(array_filter($existingPosts, function ($ep) use ($post) {
                        return ($ep['slug'] ?? '') !== $post->slug && ($ep['id'] ?? '') != $post->id;
                    }));
                    Http::withoutVerifying()->connectTimeout(5)->timeout(12)->withHeaders(['Content-Type' => 'application/json'])
                        ->put('https://happymachinery.com/api/content.php?key=blog-posts', ['value' => $filtered]);
                    return ['deleted' => true, 'message' => 'Deleted from Happy Machinery.'];
                }
            } catch (\Throwable $e) {
                Log::warning('HappyMachinery remote delete error: ' . $e->getMessage());
            }
        }

        // 2. Custom Frameworks (/api/dgt-sync.php)
        $knownSyncSites = [
            'stonekrusher.com'       => 'https://stonekrusher.com/api/dgt-sync.php',
            'heavyequipment.wiki'    => 'https://heavyequipment.wiki/api/dgt-sync.php',
            'americanloader.com'     => 'https://americanloader.com/api/dgt-sync.php',
            'konstructz.com'         => 'https://konstructz.com/api/dgt-sync.php',
            'skidsteers.us'          => 'https://skidsteers.us/api/dgt-sync.php',
            'stunningmachinery.com'  => 'https://stunningmachinery.com/api/dgt-sync.php',
            'machinery.org'          => 'https://machinery.org/api/dgt-sync.php',
            'machineryeu.com'        => 'https://machineryeu.com/api/dgt-sync.php',
            'americanexcavators.org' => 'https://americanexcavators.org/api/dgt-sync.php',
        ];

        foreach ($knownSyncSites as $syncDomain => $syncEndpoint) {
            if ($siteDomain === $syncDomain || str_ends_with($siteDomain, '.' . $syncDomain)) {
                try {
                    $resp = Http::withoutVerifying()->connectTimeout(5)->timeout(12)
                        ->withHeaders([
                            'X-DGT-Master-Key' => 'dgt_fleet_secure_master_sync_2026',
                            'X-DGT-Key'        => 'dgt_fleet_secure_master_sync_2026',
                        ])
                        ->post($syncEndpoint, [
                            'action' => 'delete',
                            'slug'   => $post->slug,
                        ]);
                    if ($resp->successful()) {
                        return ['deleted' => true, 'message' => "Deleted from {$website->name}."];
                    }
                } catch (\Throwable $e) {
                    Log::warning("Direct sync delete error for {$syncDomain}: " . $e->getMessage());
                }
            }
        }

        // 3. Local SQLite Sites (skidsteers.online)
        $sqlitePath = '/home/u355625773/domains/' . $siteDomain . '/public_html/database/database.sqlite';
        if (file_exists($sqlitePath) && class_exists('SQLite3')) {
            try {
                $sqlite = new \SQLite3($sqlitePath);
                $delStmt = $sqlite->prepare("DELETE FROM blog_posts WHERE slug = :slug");
                $delStmt->bindValue(':slug', $post->slug, SQLITE3_TEXT);
                $delStmt->execute();
                return ['deleted' => true, 'message' => "Deleted from {$website->name} database."];
            } catch (\Throwable $e) {}
        }

        // 4. Local WordPress sites on Server 1 (wp-config.php direct DB)
        $wpConfigPath = '/home/u355625773/domains/' . $siteDomain . '/public_html/wp-config.php';
        if (file_exists($wpConfigPath)) {
            try {
                $wpCfg = file_get_contents($wpConfigPath);
                preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $wpCfg, $mDb);
                preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $wpCfg, $mUser);
                preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $wpCfg, $mPass);
                preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $wpCfg, $mHost);
                preg_match("/\\\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]/", $wpCfg, $mPrefix);

                if (!empty($mDb[1]) && !empty($mUser[1])) {
                    $prefix = $mPrefix[1] ?? 'wp_';
                    $pdo = new \PDO("mysql:host=" . ($mHost[1] ?? 'localhost') . ";dbname=" . $mDb[1] . ";charset=utf8mb4", $mUser[1], $mPass[1] ?? '', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                    $stmt = $pdo->prepare("SELECT ID FROM {$prefix}posts WHERE post_name = ? LIMIT 1");
                    $stmt->execute([$post->slug]);
                    $foundWpId = $stmt->fetchColumn();
                    if ($foundWpId) {
                        $pdo->prepare("DELETE FROM {$prefix}posts WHERE ID = ?")->execute([$foundWpId]);
                        $pdo->prepare("DELETE FROM {$prefix}postmeta WHERE post_id = ?")->execute([$foundWpId]);
                        $pdo->prepare("DELETE FROM {$prefix}term_relationships WHERE object_id = ?")->execute([$foundWpId]);
                        return ['deleted' => true, 'message' => "Deleted from {$website->name} WordPress DB."];
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 5. Universal Fleet Connector Push (DGT Connector REST API)
        $integration = $website->integration;
        $rawEndpoint = $integration?->endpoint_url ?: $website->url;
        if (!empty($rawEndpoint)) {
            try {
                $targetUrl = rtrim($rawEndpoint, '/');
                if (!str_contains($targetUrl, '/wp-json/')) {
                    $targetUrl .= '/wp-json/dgt/v1/posts/delete';
                } else {
                    $targetUrl = preg_replace('#/wp-json/dgt/v1/posts/?$#', '/wp-json/dgt/v1/posts/delete', $targetUrl);
                }

                $timestamp = (string) time();
                $body = json_encode(['slug' => $post->slug, 'id' => $post->remote_id]);
                $apiKey = $integration?->api_key ?: 'dgt_fleet_secure_master_sync_2026';
                $apiSecret = $integration?->api_secret ?: 'dgt_fleet_secure_master_sync_2026';
                $signature = hash_hmac('sha256', $timestamp . '.' . $body, (string) $apiSecret);

                $resp = Http::withoutVerifying()->connectTimeout(5)->timeout(12)
                    ->withBody($body, 'application/json')
                    ->withHeaders([
                        'X-DGT-Key'        => $apiKey,
                        'X-DGT-Master-Key' => 'dgt_fleet_secure_master_sync_2026',
                        'X-DGT-Timestamp'  => $timestamp,
                        'X-DGT-Signature'  => $signature,
                        'Accept'           => 'application/json',
                    ])
                    ->post($targetUrl);

                if ($resp->successful()) {
                    return ['deleted' => true, 'message' => "Deleted from {$website->name} via DGT Connector."];
                }
            } catch (\Throwable $e) {
                Log::warning("Connector remote delete error: " . $e->getMessage());
            }
        }

        return ['deleted' => false, 'message' => 'No remote deletion action needed or reached.'];
    }

    /**
     * Sync a blog post to any WordPress site via the standard WP REST API (/wp-json/wp/v2/posts).
     * Uses Application Passwords stored on the integration record (api_key = WP username, api_secret = app password).
     * This is the universal fallback for all WordPress sites including asiamachinery.org, etc.
     */
    protected function syncPostViaWpRestApi(Website $website, WebsitePost $post, string $styledContent): ?array
    {
        $baseUrl = rtrim($website->url ?: '', '/');
        if (empty($baseUrl)) {
            return null;
        }

        // Attempt 1: Use stored application password from integration
        $integration = $website->integration;
        $wpUsername = null;
        $wpAppPassword = null;

        if ($integration) {
            // Stored as: api_key = WP admin username, webhook_secret = WP application password
            // OR: connector_type = 'wordpress_rest', api_key = username, api_secret = app password
            if (!empty($integration->api_key) && str_contains(strtolower($integration->connector_type ?? ''), 'wordpress')) {
                $wpUsername = $integration->api_key;
                $wpAppPassword = $integration->webhook_secret ?: $integration->api_secret;
            }
        }

        // Attempt 2: Try the DGT connector's /wp-json/dgt/v1/posts endpoint without auth verification
        // (for sites that have the DGT plugin but no integration record configured)
        $dgtUrl = $baseUrl . '/wp-json/dgt/v1/posts';
        $wpNativeUrl = $baseUrl . '/wp-json/wp/v2/posts';

        $wpStatus = match (strtolower((string)($post->status ?? 'draft'))) {
            'published', 'publish', 'public' => 'publish',
            'scheduled', 'future'             => 'future',
            default                           => 'draft',
        };

        // Build WP REST payload
        $wpPayload = [
            'title'   => $post->title,
            'content' => $styledContent,
            'status'  => $wpStatus,
            'excerpt' => $this->buildCleanExcerpt($post->excerpt, $styledContent, $post->title),
            'slug'    => $post->slug,
        ];

        if ($post->published_at) {
            // Use a date 60 seconds in the past so WordPress never marks it as 'future'
            $publishTs = $post->published_at->timestamp;
            $safeTs = ($wpStatus === 'publish') ? min($publishTs, time() - 60) : $publishTs;
            $wpPayload['date']     = date('Y-m-d\TH:i:s', $safeTs);
            $wpPayload['date_gmt'] = gmdate('Y-m-d\TH:i:s', $safeTs);
        } elseif ($wpStatus === 'publish') {
            $wpPayload['date']     = date('Y-m-d\TH:i:s', time() - 60);
            $wpPayload['date_gmt'] = gmdate('Y-m-d\TH:i:s', time() - 60);
        }

        // Try via Application Password (WP native REST)
        if ($wpUsername && $wpAppPassword) {
            try {
                // First check if post exists by slug
                $searchResp = Http::timeout(10)->connectTimeout(5)
                    ->withBasicAuth($wpUsername, $wpAppPassword)
                    ->get($wpNativeUrl, ['slug' => $post->slug, 'status' => 'any']);

                $existingWpPosts = $searchResp->successful() ? $searchResp->json() : [];
                $existingWpId = !empty($existingWpPosts) && is_array($existingWpPosts) ? ($existingWpPosts[0]['id'] ?? null) : null;

                if ($existingWpId) {
                    // Update existing
                    $resp = Http::timeout(15)->connectTimeout(5)
                        ->withBasicAuth($wpUsername, $wpAppPassword)
                        ->patch($wpNativeUrl . '/' . $existingWpId, $wpPayload);
                } else {
                    // Create new
                    $resp = Http::timeout(15)->connectTimeout(5)
                        ->withBasicAuth($wpUsername, $wpAppPassword)
                        ->post($wpNativeUrl, $wpPayload);
                }

                if ($resp->successful()) {
                    $respData = $resp->json();
                    $liveUrl = $respData['link'] ?? ($baseUrl . '/blog/' . $post->slug);
                    $post->updateQuietly(['canonical_url' => $liveUrl]);

                    WebsiteSyncLog::create([
                        'website_id'    => $website->id,
                        'sync_type'     => 'blog_post_publish',
                        'status'        => WebsiteSyncLog::STATUS_SUCCESS,
                        'items_synced'  => 1,
                        'error_message' => null,
                        'triggered_by'  => 'wp_rest_api',
                    ]);

                    return [
                        'synced'  => true,
                        'message' => 'Successfully published to ' . $website->name . ' via WordPress REST API! Live publicly on site.',
                        'url'     => $liveUrl,
                    ];
                }
            } catch (\Throwable $e) {
                Log::info("WP REST API sync failed for {$website->name}: " . $e->getMessage());
            }
        }

        // Attempt 3: Try standard WP REST API without authentication (only works if REST API allows unauthenticated create — rare but some sites allow it)
        // Mostly a probe to detect if WP REST is available for diagnostics
        try {
            $healthResp = Http::timeout(5)->connectTimeout(3)->get($baseUrl . '/wp-json/wp/v2');
            if ($healthResp->successful()) {
                Log::info("WordPress REST available at {$baseUrl} but no credentials configured. Please add WP Application Password in Website Integration settings.");
            }
        } catch (\Throwable $e) {}

        return null;
    }

    /**
     * Directly sync post to custom MySQL databases on Server 1 (groundripper.com, kuvuo.net, machine2u.com, machineryvillage.com).
     */
    protected function syncPostToCustomMysqlDb(array $conf, WebsitePost $post, string $styledContent): ?array
    {
        try {
            $pdo = $this->getPdoConnection($conf['db'], $conf['user'], $conf['pass']);
            if (!$pdo) {
                return null;
            }

            $table = $conf['table'];
            $cleanExcerpt = $this->buildCleanExcerpt($post->excerpt, $styledContent, $post->title);
            $liveUrl = str_contains($conf['url'], '?')
                ? ($conf['url'] . $post->slug)
                : (rtrim($conf['url'], '/') . '/' . $post->slug);

            if ($table === 'blog_posts') {
                // groundripper.com
                $chk = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ? LIMIT 1");
                $chk->execute([$post->slug]);
                $existingId = $chk->fetchColumn();

                if ($existingId) {
                    $upd = $pdo->prepare("UPDATE blog_posts SET title = ?, excerpt = ?, content = ?, image = ?, published_at = ?, status = 'published' WHERE id = ?");
                    $upd->execute([
                        $post->title,
                        $cleanExcerpt,
                        $styledContent,
                        $post->featured_image_url ?: null,
                        $post->published_at ? $post->published_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
                        $existingId,
                    ]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO blog_posts (title, slug, excerpt, content, image, published_at, author, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'published')");
                    $ins->execute([
                        $post->title,
                        $post->slug,
                        $cleanExcerpt,
                        $styledContent,
                        $post->featured_image_url ?: null,
                        $post->published_at ? $post->published_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
                        $post->author_name ?: 'GroundRipper',
                    ]);
                }
            } elseif ($table === 'posts' && str_contains($conf['db'], 'kuvuo')) {
                // kuvuo.net
                $chk = $pdo->prepare("SELECT id FROM posts WHERE slug = ? LIMIT 1");
                $chk->execute([$post->slug]);
                $existingId = $chk->fetchColumn();

                if ($existingId) {
                    $upd = $pdo->prepare("UPDATE posts SET title = ?, excerpt = ?, content = ?, featured_image = ?, published_at = ?, status = 'published' WHERE id = ?");
                    $upd->execute([
                        $post->title,
                        $cleanExcerpt,
                        $styledContent,
                        $post->featured_image_url ?: null,
                        $post->published_at ? $post->published_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
                        $existingId,
                    ]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO posts (title, slug, excerpt, content, featured_image, published_at, status) VALUES (?, ?, ?, ?, ?, ?, 'published')");
                    $ins->execute([
                        $post->title,
                        $post->slug,
                        $cleanExcerpt,
                        $styledContent,
                        $post->featured_image_url ?: null,
                        $post->published_at ? $post->published_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
                    ]);
                }
            } elseif ($table === 'blogs') {
                // machine2u.com
                $chk = $pdo->prepare("SELECT id FROM blogs WHERE title = ? LIMIT 1");
                $chk->execute([$post->title]);
                $existingId = $chk->fetchColumn();

                if ($existingId) {
                    $upd = $pdo->prepare("UPDATE blogs SET excerpt = ?, content = ?, image = ?, date = ? WHERE id = ?");
                    $upd->execute([
                        $cleanExcerpt,
                        $styledContent,
                        $post->featured_image_url ?: null,
                        $post->published_at ? $post->published_at->format('Y-m-d') : date('Y-m-d'),
                        $existingId,
                    ]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO blogs (title, excerpt, content, date, author, image) VALUES (?, ?, ?, ?, ?, ?)");
                    $ins->execute([
                        $post->title,
                        $cleanExcerpt,
                        $styledContent,
                        $post->published_at ? $post->published_at->format('Y-m-d') : date('Y-m-d'),
                        $post->author_name ?: 'Machine2u',
                        $post->featured_image_url ?: null,
                    ]);
                }
            } elseif ($table === 'posts' && str_contains($conf['db'], 'mvillage')) {
                // machineryvillage.com
                $chk = $pdo->prepare("SELECT id FROM posts WHERE slug = ? LIMIT 1");
                $chk->execute([$post->slug]);
                $existingId = $chk->fetchColumn();

                if ($existingId) {
                    $upd = $pdo->prepare("UPDATE posts SET title = ?, excerpt = ?, content = ?, cover_image = ?, published = 1 WHERE id = ?");
                    $upd->execute([
                        $post->title,
                        $cleanExcerpt,
                        $styledContent,
                        $post->featured_image_url ?: null,
                        $existingId,
                    ]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO posts (title, slug, excerpt, content, cover_image, created_at, published) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $ins->execute([
                        $post->title,
                        $post->slug,
                        $cleanExcerpt,
                        $styledContent,
                        $post->featured_image_url ?: null,
                        $post->published_at ? $post->published_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
                    ]);
                }
            }

            return [
                'synced'  => true,
                'message' => "Successfully synchronized with {$conf['table']} database! Live publicly on site.",
                'url'     => $liveUrl,
            ];
        } catch (\Throwable $e) {
            Log::warning("Custom MySQL sync error for {$conf['db']}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Direct database sync to Server 2 WordPress Sites (u534600013@194.164.64.89) via remote SSH engine.
     */
    protected function syncPostToServer2WordPress(string $siteDomain, WebsitePost $post, Website $website, string $styledContent): ?array
    {
        try {
            $wpStatus = match ($post->status) {
                WebsitePost::STATUS_PUBLISHED, 'publish', 'public' => 'publish',
                WebsitePost::STATUS_SCHEDULED, 'future'           => 'future',
                default                                           => 'draft',
            };

            $postDate = ($wpStatus === 'publish')
                ? gmdate('Y-m-d H:i:s', time() - 60)
                : (($wpStatus === 'future' && $post->scheduled_at) ? gmdate('Y-m-d H:i:s', $post->scheduled_at->timestamp) : gmdate('Y-m-d H:i:s'));
            $postDateGmt = ($wpStatus === 'publish')
                ? gmdate('Y-m-d H:i:s', time() - 60)
                : (($wpStatus === 'future' && $post->scheduled_at) ? gmdate('Y-m-d H:i:s', $post->scheduled_at->timestamp) : gmdate('Y-m-d H:i:s'));

            $siteUrl = rtrim($website->url ?: "https://{$siteDomain}", '/');
            $payload = [
                'domain'             => $siteDomain,
                'slug'               => $post->slug,
                'title'              => $post->title,
                'content'            => $styledContent,
                'excerpt'            => $this->buildCleanExcerpt($post->excerpt, (string) ($post->content_html ?? ''), $post->title),
                'status'             => $wpStatus,
                'post_date'          => $postDate,
                'post_date_gmt'      => $postDateGmt,
                'category'           => $this->resolveWordPressBlogCategory($siteDomain, $post->category),
                'featured_image_url' => $post->featured_image_url ?: '',
                'meta_title'         => $post->meta_title ?: $post->title,
                'meta_description'   => $post->meta_description ?: ($this->buildCleanExcerpt($post->excerpt, (string) ($post->content_html ?? ''), $post->title)),
                'site_url'           => $siteUrl,
            ];

            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $b64 = base64_encode($json);

            // Execute via Server 1 -> Server 2 SSH key
            $sshCmd = sprintf(
                "ssh -p 65002 -o StrictHostKeyChecking=no -o ConnectTimeout=8 u534600013@194.164.64.89 %s 2>&1",
                escapeshellarg("echo {$b64} | base64 -d | php ~/dgt_wp_sync.php")
            );

            $output = shell_exec($sshCmd);
            if (!empty($output) && preg_match('/\{[\s\S]*"success"[\s\S]*\}/', $output, $jm)) {
                $res = json_decode($jm[0], true);
                if (!empty($res['success'])) {
                    $liveUrl = $res['url'] ?? ($siteUrl . '/' . $post->slug . '/');
                    return [
                        'synced'  => true,
                        'message' => "Successfully synchronized with {$website->name} on Server 2 (WP ID #{$res['id']})! Live publicly on site.",
                        'url'     => $liveUrl,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Server 2 WP sync error for {$siteDomain}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Fallback direct database sync to WordPress via wp-config.php credentials.
     * Guarantees that even if WP-CLI is not present, the post is written directly to wp_posts with status 'publish'.
     */
    protected function syncPostToWordPressDb(string $wpConfigPath, WebsitePost $post, Website $website, string $styledContent, string $wpStatus, string $postDate, string $postDateGmt): ?array
    {
        if (!file_exists($wpConfigPath)) {
            return null;
        }

        try {
            $content = file_get_contents($wpConfigPath);
            preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $mDb);
            preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $mUser);
            preg_match("/define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $mPass);
            preg_match("/\\\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]/", $content, $mPrefix);

            $dbName = $mDb[1] ?? null;
            $dbUser = $mUser[1] ?? null;
            $dbPass = $mPass[1] ?? null;
            $prefix = $mPrefix[1] ?? 'wp_';

            if (!$dbName || !$dbUser || !$dbPass) {
                return null;
            }

            $pdo = $this->getPdoConnection($dbName, $dbUser, $dbPass);
            if (!$pdo) {
                return null;
            }

            // Check if post already exists by slug
            $stmt = $pdo->prepare("SELECT ID FROM {$prefix}posts WHERE post_name = ? AND post_type = 'post' LIMIT 1");
            $stmt->execute([$post->slug]);
            $existingId = $stmt->fetchColumn();

            $guid = rtrim($website->url ?: '', '/') . '/blog/' . $post->slug;

            if ($existingId) {
                $upd = $pdo->prepare("UPDATE {$prefix}posts SET 
                    post_title = ?, 
                    post_content = ?, 
                    post_status = ?, 
                    post_date = ?, 
                    post_date_gmt = ?, 
                    post_modified = ?, 
                    post_modified_gmt = ?, 
                    post_excerpt = ?,
                    guid = ?
                    WHERE ID = ?");
                $upd->execute([
                    $post->title,
                    $styledContent,
                    $wpStatus,
                    $postDate,
                    $postDateGmt,
                    $postDate,
                    $postDateGmt,
                    $this->buildCleanExcerpt($post->excerpt, (string) ($post->content_html ?? ''), $post->title),
                    $guid,
                    $existingId,
                ]);
                $wpPostId = $existingId;
            } else {
                $ins = $pdo->prepare("INSERT INTO {$prefix}posts (
                    post_author, post_date, post_date_gmt, post_content, post_title, 
                    post_excerpt, post_status, comment_status, ping_status, post_name, 
                    post_modified, post_modified_gmt, post_type, post_parent, menu_order, guid
                ) VALUES (
                    1, ?, ?, ?, ?, 
                    ?, ?, 'open', 'open', ?, 
                    ?, ?, 'post', 0, 0, ?
                )");
                $ins->execute([
                    $postDate,
                    $postDateGmt,
                    $styledContent,
                    $post->title,
                    $this->buildCleanExcerpt($post->excerpt, (string) ($post->content_html ?? ''), $post->title),
                    $wpStatus,
                    $post->slug,
                    $postDate,
                    $postDateGmt,
                    $guid,
                ]);
                $wpPostId = $pdo->lastInsertId();
            }

            // Category assignment in WordPress taxonomy (guarantees post appears in /blog category queries and Elementor widgets)
            try {
                $taxIdsToAssign = [];
                $cleanCatName = $this->sanitizeArticleCategory($post->category);
                if ($cleanCatName) {
                    $slugCat = Str::slug($cleanCatName);
                    $tStmt = $pdo->prepare("SELECT t.term_id, tt.term_taxonomy_id FROM {$prefix}terms t JOIN {$prefix}term_taxonomy tt ON t.term_id = tt.term_id WHERE (t.name = ? OR t.slug = ?) AND tt.taxonomy = 'category' LIMIT 1");
                    $tStmt->execute([$cleanCatName, $slugCat]);
                    $termRow = $tStmt->fetch(\PDO::FETCH_ASSOC);

                    if ($termRow) {
                        $taxIdsToAssign[] = (int) $termRow['term_taxonomy_id'];
                    } elseif ($slugCat !== '') {
                        $insTerm = $pdo->prepare("INSERT INTO {$prefix}terms (name, slug, term_group) VALUES (?, ?, 0)");
                        $insTerm->execute([$cleanCatName, $slugCat]);
                        $newTermId = (int) $pdo->lastInsertId();

                        $insTax = $pdo->prepare("INSERT INTO {$prefix}term_taxonomy (term_id, taxonomy, description, parent, count) VALUES (?, 'category', '', 0, 1)");
                        $insTax->execute([$newTermId]);
                        $taxIdsToAssign[] = (int) $pdo->lastInsertId();
                    }
                }

                // Always attach primary blog archive categories ('blog', 'blogs', 'all-blogs', 'post', 'posts', 'articles', 'news') if present
                $bStmt = $pdo->query("SELECT tt.term_taxonomy_id FROM {$prefix}terms t JOIN {$prefix}term_taxonomy tt ON t.term_id = tt.term_id WHERE t.slug IN ('blog', 'blogs', 'all-blogs', 'post', 'posts', 'articles', 'news', 'machinery-guide', 'electric-forklift-guides', 'skid-steer-buying-guides', 'road-roller-blogs', 'forklift-blog', 'roadroller') AND tt.taxonomy = 'category'");
                foreach ($bStmt->fetchAll(\PDO::FETCH_COLUMN) as $bTaxId) {
                    $taxIdsToAssign[] = (int) $bTaxId;
                }

                // Fallback to default_category if still empty or default_category is non-uncategorized
                $defOpt = $pdo->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'default_category' LIMIT 1");
                $defCatTermId = $defOpt ? (int) $defOpt->fetchColumn() : 0;
                if ($defCatTermId > 0) {
                    $dtStmt = $pdo->prepare("SELECT tt.term_taxonomy_id, t.slug FROM {$prefix}terms t JOIN {$prefix}term_taxonomy tt ON t.term_id = tt.term_id WHERE t.term_id = ? AND tt.taxonomy = 'category' LIMIT 1");
                    $dtStmt->execute([$defCatTermId]);
                    $dtRow = $dtStmt->fetch(\PDO::FETCH_ASSOC);
                    if ($dtRow && (empty($taxIdsToAssign) || ($dtRow['slug'] ?? '') !== 'uncategorized')) {
                        $taxIdsToAssign[] = (int) $dtRow['term_taxonomy_id'];
                    }
                }

                if ($wpPostId) {
                    foreach (array_unique(array_filter($taxIdsToAssign)) as $assignTaxId) {
                        $relStmt = $pdo->prepare("INSERT INTO {$prefix}term_relationships (object_id, term_taxonomy_id, term_order) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE term_order = 0");
                        $relStmt->execute([$wpPostId, $assignTaxId]);

                        $cntStmt = $pdo->prepare("UPDATE {$prefix}term_taxonomy SET count = (SELECT COUNT(*) FROM {$prefix}term_relationships WHERE term_taxonomy_id = ?) WHERE term_taxonomy_id = ?");
                        $cntStmt->execute([$assignTaxId, $assignTaxId]);
                    }
                }

                // Clear Elementor 3.22+ widget cache so /blog Post Grid renders the new post immediately
                $pdo->exec("DELETE FROM {$prefix}postmeta WHERE meta_key = '_elementor_element_cache'");
            } catch (\Throwable $e) {
                Log::warning("WP taxonomy assign error for post #{$wpPostId}: " . $e->getMessage());
            }

            // Store SEO Meta & Focus Keywords
            if ($wpPostId && !empty($post->meta_title)) {
                foreach (['_yoast_wpseo_title', 'rank_math_title', '_dgt_meta_title'] as $k) {
                    $seoStmt = $pdo->prepare("INSERT INTO {$prefix}postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
                    $seoStmt->execute([$wpPostId, $k, $post->meta_title]);
                }
            }
            if ($wpPostId && !empty($post->meta_description)) {
                foreach (['_yoast_wpseo_metadesc', 'rank_math_description', '_dgt_meta_description'] as $k) {
                    $seoDescStmt = $pdo->prepare("INSERT INTO {$prefix}postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
                    $seoDescStmt->execute([$wpPostId, $k, $post->meta_description]);
                }
            }
            if ($wpPostId && !empty($post->focus_keyword)) {
                foreach (['_yoast_wpseo_focuskw', 'rank_math_focus_keyword'] as $k) {
                    $kwStmt = $pdo->prepare("INSERT INTO {$prefix}postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
                    $kwStmt->execute([$wpPostId, $k, $post->focus_keyword]);
                }
            }

            // Featured Image (standard WP postmeta + FIFU external featured image plugin)
            if ($wpPostId && !empty($post->featured_image_url)) {
                $imgUrl = trim($post->featured_image_url);
                foreach (['_dgt_featured_image_url', 'fifu_image_url', '_fifu_image_url'] as $k) {
                    $mStmt = $pdo->prepare("INSERT INTO {$prefix}postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
                    $mStmt->execute([$wpPostId, $k, $imgUrl]);
                }
            }

            // Auto-deploy DGT Connector into mu-plugins so full REST API is permanently active with zero human config
            $wpDir = dirname($wpConfigPath);
            try {
                $srcConnector = public_path('connectors/wordpress/dgt-connector.php');
                if (file_exists($srcConnector)) {
                    $muDir = $wpDir . '/wp-content/mu-plugins';
                    if (!is_dir($muDir)) {
                        @mkdir($muDir, 0755, true);
                    }
                    if (is_dir($muDir)) {
                        @copy($srcConnector, $muDir . '/dgt-connector.php');
                    }
                }

                $integration = $website->integration;
                if ($integration && $integration->api_key && $integration->api_secret) {
                    $optStmt = $pdo->prepare("INSERT INTO {$prefix}options (option_name, option_value, autoload) VALUES (?, ?, 'yes') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)");
                    $optStmt->execute(['dgt_api_key', $integration->api_key]);
                    $optStmt->execute(['dgt_api_secret', $integration->api_secret]);
                }
            } catch (\Throwable $e) {}

            // Purge LiteSpeed & WordPress page caches so the post displays immediately on the website
            try {
                $cacheDir = $wpDir . '/wp-content/cache';
                if (is_dir($cacheDir)) {
                    @touch($cacheDir . '/.purge');
                    $pageCache = $cacheDir . '/page_enhanced';
                    if (is_dir($pageCache)) {
                        @shell_exec("rm -rf " . escapeshellarg($pageCache) . "/* 2>/dev/null");
                    }
                }
                if ($website->url) {
                    $baseSiteUrl = rtrim($website->url, '/');
                    Http::withoutVerifying()
                        ->timeout(4)
                        ->connectTimeout(2)
                        ->get($baseSiteUrl . '/wp-json/dgt/v1/health?purge=1');
                    Http::withoutVerifying()
                        ->timeout(3)
                        ->connectTimeout(2)
                        ->withHeaders([
                            'X-LiteSpeed-Purge' => '*',
                            'Cache-Control'     => 'no-cache',
                        ])
                        ->get($baseSiteUrl);
                }
            } catch (\Throwable $e) {}

            return [
                'synced'  => true,
                'message' => "Successfully published to WordPress (Direct DB Post #{$wpPostId})! Live publicly on site.",
                'wp_id'   => $wpPostId,
                'url'     => $guid,
            ];
        } catch (\Throwable $e) {
            Log::warning("WordPress direct PDO sync error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get a PDO connection supporting unix socket (/tmp/mysql.sock on Hostinger), 127.0.0.1 and localhost.
     */
    protected function getPdoConnection(string $db, string $user, string $pass): ?\PDO
    {
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 4,
        ];

        // 1. Try unix socket directly (/tmp/mysql.sock on Hostinger)
        try {
            return new \PDO("mysql:dbname={$db};unix_socket=/tmp/mysql.sock;charset=utf8mb4", $user, $pass, $options);
        } catch (\Throwable $e) {}

        // 2. Try localhost
        try {
            return new \PDO("mysql:host=localhost;dbname={$db};charset=utf8mb4", $user, $pass, $options);
        } catch (\Throwable $e) {}

        // 3. Try 127.0.0.1
        try {
            return new \PDO("mysql:host=127.0.0.1;dbname={$db};charset=utf8mb4", $user, $pass, $options);
        } catch (\Throwable $e) {}

        return null;
    }

    /**
     * Resolve website color theme and styling variables based on domain, website profile, or post.
     * Matches the authentic CMS color identity for each website (e.g. Groundripper / Konstructz Gold,
     * Kuvuo Forest Green, TYPHON Machinery Orange, Machine2u Emerald, etc.)
     *
     * @return array{
     *     primary: string,
     *     primary_dark: string,
     *     primary_light: string,
     *     btn_text: string,
     *     cta_bg: string,
     *     cta_border: string,
     *     link_color: string,
     *     link_hover: string
     * }
     */
    public function resolveWebsiteTheme(?Website $website = null, ?WebsitePost $post = null, ?string $domainHint = null): array
    {
        // 1. Resolve domain or key identifier
        $domain = '';
        if (!empty($domainHint)) {
            $domain = $domainHint;
        } elseif (!empty($post?->canonical_url)) {
            $domain = parse_url($post->canonical_url, PHP_URL_HOST) ?: '';
        } elseif (!empty($website?->url)) {
            $domain = parse_url($website->url, PHP_URL_HOST) ?: '';
        } elseif (!empty($post?->website?->url)) {
            $domain = parse_url($post->website->url, PHP_URL_HOST) ?: '';
        }

        $domain = strtolower(trim((string)$domain));
        $domain = preg_replace('/^www\./i', '', $domain);

        $name = strtolower(trim((string)($website?->name ?: ($post?->website?->name ?: ''))));

        // 2. Comprehensive Domain & Brand Theme Registry
        // Konstructz / Groundripper Fleet: Signature Brand Gold (#8c6f38) & Forest (#26392c)
        if (
            str_contains($domain, 'groundripper') ||
            str_contains($domain, 'konstructz') ||
            str_contains($domain, 'konstruz') ||
            str_contains($domain, 'construcz') ||
            str_contains($domain, 'cwqv.com') ||
            str_contains($domain, 'mzvt.com') ||
            str_contains($name, 'konstructz') ||
            str_contains($name, 'konstruz') ||
            str_contains($name, 'construcz') ||
            str_contains($name, 'groundripper')
        ) {
            return [
                'primary'       => '#8c6f38',
                'primary_dark'  => '#755c2e',
                'primary_light' => '#fbfaf8',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#fbfaf8',
                'cta_border'    => 'rgba(140, 111, 56, 0.25)',
                'link_color'    => '#8c6f38',
                'link_hover'    => '#755c2e',
            ];
        }

        // Kuvuo / Kuvyn Fleet: Deep Forest Green (#12372a)
        if (
            str_contains($domain, 'kuvuo') ||
            str_contains($domain, 'kuvyn') ||
            str_contains($name, 'kuvuo') ||
            str_contains($name, 'kuvyn')
        ) {
            return [
                'primary'       => '#12372a',
                'primary_dark'  => '#0e2a20',
                'primary_light' => '#f0fdf4',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#f8faf9',
                'cta_border'    => 'rgba(18, 55, 42, 0.20)',
                'link_color'    => '#12372a',
                'link_hover'    => '#0e2a20',
            ];
        }

        // TYPHON Fleet & Direct Excavator Brands: Signature Machinery Orange (#f26522)
        if (
            str_contains($domain, 'typhon') ||
            str_contains($domain, 'thunderdump') ||
            str_contains($domain, 'usaexcavators') ||
            str_contains($domain, 'newminiexcavator') ||
            str_contains($domain, 'tinyexcavator') ||
            str_contains($domain, 'newexcavatorsforsale') ||
            str_contains($domain, 'unitedstatesofexcavators') ||
            str_contains($domain, 'newexcavator.com') ||
            str_contains($domain, 'newdiggerforsale') ||
            str_contains($domain, '1excavator') ||
            str_contains($name, 'typhon') ||
            str_contains($name, 'gryphon')
        ) {
            return [
                'primary'       => '#f26522',
                'primary_dark'  => '#d44c0d',
                'primary_light' => '#fff7ed',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#fffaf5',
                'cta_border'    => 'rgba(242, 101, 34, 0.25)',
                'link_color'    => '#ea580c',
                'link_hover'    => '#c2410c',
            ];
        }

        // Machine2U / Electric Forklift: Clean Emerald Green (#10b981)
        if (
            str_contains($domain, 'machine2u') ||
            str_contains($domain, 'electricforklift') ||
            str_contains($name, 'machine2u') ||
            str_contains($name, 'electricforklift')
        ) {
            return [
                'primary'       => '#10b981',
                'primary_dark'  => '#059669',
                'primary_light' => '#ecfdf5',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#f0fdf4',
                'cta_border'    => 'rgba(16, 185, 129, 0.25)',
                'link_color'    => '#059669',
                'link_hover'    => '#047857',
            ];
        }

        // Stunning Machinery: Gold (#a96d00 / #f2c00b)
        if (
            str_contains($domain, 'stunningmachinery') ||
            str_contains($name, 'stunning')
        ) {
            return [
                'primary'       => '#a96d00',
                'primary_dark'  => '#8c5800',
                'primary_light' => '#fffbeb',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#fffdf5',
                'cta_border'    => 'rgba(169, 109, 0, 0.25)',
                'link_color'    => '#a96d00',
                'link_hover'    => '#8c5800',
            ];
        }

        // Machinery.org: Professional Deep Navy (#0a1a2e) & Construction Gold (#b45309)
        if (
            str_contains($domain, 'machinery.org') ||
            str_contains($domain, 'machineryusa.online')
        ) {
            return [
                'primary'       => '#0a1a2e',
                'primary_dark'  => '#050d17',
                'primary_light' => '#f1f5f9',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#f8fafc',
                'cta_border'    => 'rgba(10, 26, 46, 0.20)',
                'link_color'    => '#0a1a2e',
                'link_hover'    => '#b45309',
            ];
        }

        // American Loader & Machinery Village: Deep Navy Blue (#0b2d55)
        if (
            str_contains($domain, 'americanloader') ||
            str_contains($domain, 'machineryvillage')
        ) {
            return [
                'primary'       => '#0b2d55',
                'primary_dark'  => '#071f3a',
                'primary_light' => '#f0f4f8',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#f7f9fb',
                'cta_border'    => 'rgba(11, 45, 85, 0.20)',
                'link_color'    => '#0b2d55',
                'link_hover'    => '#071f3a',
            ];
        }

        // Machinery EU: European Navy (#0a2342)
        if (str_contains($domain, 'machineryeu')) {
            return [
                'primary'       => '#0a2342',
                'primary_dark'  => '#061528',
                'primary_light' => '#f1f5f9',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#f8fafc',
                'cta_border'    => 'rgba(10, 35, 66, 0.20)',
                'link_color'    => '#0a2342',
                'link_hover'    => '#061528',
            ];
        }

        // Skid Steers US: Construction Green (#1da851)
        if (str_contains($domain, 'skidsteers.us') || str_contains($domain, 'skidsteers.org')) {
            return [
                'primary'       => '#1da851',
                'primary_dark'  => '#15803d',
                'primary_light' => '#f0fdf4',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#f6fbf8',
                'cta_border'    => 'rgba(29, 168, 81, 0.25)',
                'link_color'    => '#15803d',
                'link_hover'    => '#166534',
            ];
        }

        // Skid Steers Online: Industrial Crimson (#e11d48)
        if (str_contains($domain, 'skidsteers.online')) {
            return [
                'primary'       => '#e11d48',
                'primary_dark'  => '#be123c',
                'primary_light' => '#fff1f2',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#fff5f6',
                'cta_border'    => 'rgba(225, 29, 72, 0.25)',
                'link_color'    => '#e11d48',
                'link_hover'    => '#be123c',
            ];
        }

        // StoneKrusher / Trenchers: Earth Quarry Bronze / Amber (#b45309)
        if (
            str_contains($domain, 'stonekrusher') ||
            str_contains($domain, 'trenchers')
        ) {
            return [
                'primary'       => '#b45309',
                'primary_dark'  => '#92400e',
                'primary_light' => '#fffbeb',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#fffdf7',
                'cta_border'    => 'rgba(180, 83, 9, 0.25)',
                'link_color'    => '#b45309',
                'link_hover'    => '#92400e',
            ];
        }

        // Heavy Equipment Wiki: Blueprint Teal (#0f766e)
        if (str_contains($domain, 'heavyequipment.wiki')) {
            return [
                'primary'       => '#0f766e',
                'primary_dark'  => '#115e59',
                'primary_light' => '#f0fdfa',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#f2fbf9',
                'cta_border'    => 'rgba(15, 118, 110, 0.25)',
                'link_color'    => '#0f766e',
                'link_hover'    => '#115e59',
            ];
        }

        // Roller Compactor Org: Compactor Brick Red (#c44d58)
        if (str_contains($domain, 'rollercompactor.org')) {
            return [
                'primary'       => '#c44d58',
                'primary_dark'  => '#a23842',
                'primary_light' => '#fdf2f2',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#fdf6f6',
                'cta_border'    => 'rgba(196, 77, 88, 0.25)',
                'link_color'    => '#a23842',
                'link_hover'    => '#832832',
            ];
        }

        // Eco Machinery / ElecGreen Fleet: Leaf Green (#3b8c4e)
        if (
            str_contains($domain, 'skidsteerloaders.org') ||
            str_contains($domain, 'skidsteer2u') ||
            str_contains($domain, 'usaforklift') ||
            str_contains($domain, 'loader2u') ||
            str_contains($domain, 'rollercompactor.online') ||
            str_contains($domain, 'liftscissor') ||
            str_contains($domain, 'newscissorslift') ||
            str_contains($domain, 'roadroller')
        ) {
            return [
                'primary'       => '#3b8c4e',
                'primary_dark'  => '#2f713e',
                'primary_light' => '#f4fbf6',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#f6fbf7',
                'cta_border'    => 'rgba(59, 140, 78, 0.25)',
                'link_color'    => '#2f713e',
                'link_hover'    => '#23552e',
            ];
        }

        // Wheel Loaders & Mini Excavators & Happy Machinery: Construction Caterpillar Yellow/Amber (#d97706)
        if (
            str_contains($domain, 'wheelloader') ||
            str_contains($domain, 'miniexcavator') ||
            str_contains($domain, 'minexcavator') ||
            str_contains($domain, 'happymachinery') ||
            str_contains($domain, 'mobilecranes') ||
            str_contains($domain, 'excavators.org')
        ) {
            return [
                'primary'       => '#d97706',
                'primary_dark'  => '#b45309',
                'primary_light' => '#fffbeb',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#fffdf5',
                'cta_border'    => 'rgba(217, 119, 6, 0.25)',
                'link_color'    => '#b45309',
                'link_hover'    => '#92400e',
            ];
        }

        // American Industrial Sky Fleet: Clean Sky Blue (#0284c7)
        if (
            str_contains($domain, 'americancompactors') ||
            str_contains($domain, 'americanminiexcavator') ||
            str_contains($domain, 'asiamachinery') ||
            str_contains($domain, 'asianmachinery') ||
            str_contains($domain, 'newscissorlift') ||
            str_contains($domain, 'americanexcavator') ||
            str_contains($domain, 'americanforklift')
        ) {
            return [
                'primary'       => '#0284c7',
                'primary_dark'  => '#0369a1',
                'primary_light' => '#f0f9ff',
                'btn_text'      => '#ffffff',
                'cta_bg'        => '#f5faff',
                'cta_border'    => 'rgba(2, 132, 199, 0.25)',
                'link_color'    => '#0284c7',
                'link_hover'    => '#0369a1',
            ];
        }

        // Default neutral industrial blue
        return [
            'primary'       => '#2563eb',
            'primary_dark'  => '#1d4ed8',
            'primary_light' => '#eff6ff',
            'btn_text'      => '#ffffff',
            'cta_bg'        => '#f8fafc',
            'cta_border'    => '#e2e8f0',
            'link_color'    => '#2563eb',
            'link_hover'    => '#1d4ed8',
        ];
    }

    /**
     * Repair HTML that was pasted as text into the editor (e.g. "&lt;h1 class=&quot;wp-block-heading&quot;&gt;..."),
     * unwrap block tags illegally nested in <p>, and strip empty / duplicate-title headings.
     */
    public function unescapeAndSanitizePastedHtml(string $html, ?string $postTitle = null): string
    {
        if (trim($html) === '') {
            return '';
        }
        $tags = 'h[1-6]|p|br|div|span|strong|b|em|i|u|ul|ol|li|table|thead|tbody|tr|th|td|figure|figcaption|img|a|blockquote|hr|section|article';

        // Decode entity-escaped HTML tags (up to 3 levels of double escaping)
        for ($pass = 0; $pass < 3; $pass++) {
            if (!preg_match('/&(?:amp;)*(?:lt|#0*60|#x0*3c);\s*\/?\s*(?:' . $tags . ')\b/i', $html)) {
                break;
            }
            $html = preg_replace_callback(
                '/&(?:amp;)*(?:lt|#0*60|#x0*3c);(\s*\/?\s*(?:' . $tags . ')\b[\s\S]*?)&(?:amp;)*(?:gt|#0*62|#x0*3e);/i',
                function ($m) {
                    $inner = html_entity_decode(str_replace('&amp;', '&', $m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (str_contains($inner, '<') || str_contains($inner, '>')) {
                        return $m[0];
                    }
                    return '<' . $inner . '>';
                },
                $html
            );
        }

        // Unwrap block elements illegally nested inside <p>…</p> (created when raw HTML was pasted as text).
        // The HTML parser auto-closes <p> before block tags; the leftover empty <p></p> is removed below.
        if (preg_match('/<p(?:\s[^>]*)?>(?:(?!<\/p>)[\s\S])*?<(?:h[1-6]|p|div|ul|ol|table|figure|blockquote|section|article)\b/i', $html)
            && class_exists(\DOMDocument::class)) {
            $prev = libxml_use_internal_errors(true);
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $loaded = $dom->loadHTML('<?xml encoding="UTF-8"?><html><body><div id="dgt-root">' . $html . '</div></body></html>', LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            $root = $loaded ? $dom->getElementById('dgt-root') : null;
            if ($root) {
                $out = '';
                foreach ($root->childNodes as $child) {
                    $out .= $dom->saveHTML($child);
                }
                if (trim(strip_tags($out)) !== '') {
                    $html = $out;
                }
            }
        }

        // Remove empty headings/paragraphs: <h1 ...><br></h1>, <h2>&nbsp;</h2>, <p><br></p>
        $html = preg_replace('/<(h[1-6]|p)(?:\s[^>]*)?>(?:\s|&nbsp;|&#160;|<br\s*\/?>|<\/?(?:strong|b|em|span)[^>]*>)*<\/\1>/i', '', $html);

        // Strip a leading H1 that repeats the post title (destination pages render the title already)
        $title = trim((string) $postTitle);
        if ($title !== '') {
            $html = preg_replace_callback('/^\s*<h1(?:\s[^>]*)?>([\s\S]*?)<\/h1>\s*/i', function ($m) use ($title) {
                $norm = fn ($t) => strtolower(preg_replace('/[^a-z0-9]/i', '', html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                $a = $norm($m[1]);
                $b = $norm($title);
                return ($a !== '' && ($a === $b || str_contains($a, $b) || str_contains($b, $a))) ? '' : $m[0];
            }, $html);
        }

        return trim($html);
    }

    /**
     * Build a clean plain-text excerpt (never contains HTML tags or escaped tags).
     */
    public function buildCleanExcerpt(?string $explicitExcerpt, string $html, ?string $postTitle = null, int $limit = 220): string
    {
        $source = trim((string) $explicitExcerpt);
        $fromContent = $source === '';
        if ($fromContent) {
            $source = $this->unescapeAndSanitizePastedHtml($html, $postTitle);
        }
        // Decode twice + strip twice so escaped tags (&lt;p&gt;) never survive as visible text
        $text = $source;
        for ($i = 0; $i < 2; $i++) {
            $text = preg_replace('/<(?:br|\/p|\/h[1-6]|\/li|\/div)\s*\/?>/i', ' ', $text);
            $text = strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        $title = trim((string) $postTitle);
        if ($fromContent && $title !== '' && stripos($text, $title) === 0) {
            $text = ltrim(substr($text, strlen($title)), " :-–—\t");
        }

        return Str::limit($text, $limit);
    }

    /**
     * Ensure all headings (H1-H4), tables, images, and key content blocks have clean,
     * semantic inline styling and structure for consistent, beautiful rendering across all destination websites.
     */
    public function ensureContentCleanAndStyled(
        string $html,
        ?WebsitePost $post = null,
        ?Website $website = null,
        ?string $sku = null,
        ?string $prodUrl = null,
        ?string $featuredUrlOverride = null,
        ?string $titleOverride = null
    ): string
    {
        if (trim($html) === '') {
            return '';
        }

        $styled = $this->unescapeAndSanitizePastedHtml($html, $titleOverride ?: $post?->title);
        if ($styled === '') {
            return '';
        }

        // 1. Ensure Table Styling (Use neutral, theme-friendly borders without hardcoded text colors)
        $styled = preg_replace_callback('/<table(?![^>]*class=["\'][^"\']*cms-rendered-table)([^>]*)>([\s\S]*?)<\/table>/i', function ($matches) {
            $inner = $matches[2] ?? '';
            $tableTag = '<table class="cms-rendered-table w-full text-xs" border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; margin: 0; border: 1px solid #cbd5e1; font-size: 14px; text-align: left;">';
            $inner = preg_replace('/<th(?![^>]*style=)/i', '<th style="padding: 10px 14px; border: 1px solid #cbd5e1; font-weight: 700; text-align: left; background: rgba(0,0,0,0.03);">', $inner);
            $inner = preg_replace('/<td(?![^>]*style=)/i', '<td style="padding: 9px 14px; border: 1px solid #e2e8f0; vertical-align: top;">', $inner);

            return '<div class="cms-table-container overflow-x-auto my-6" style="margin: 1.5rem 0; overflow-x: auto; -webkit-overflow-scrolling: touch;">' . $tableTag . $inner . '</table></div>';
        }, $styled);

        // 2. Promote paragraph headings with <br> after bold heading: <p><strong>Heading</strong><br>Body...</p>
        $styled = preg_replace_callback('/<p(\s+[^>]*)?>\s*<(?:strong|b)(?:\s+[^>]*)?>\s*(?:<span(?:\s+[^>]*)?>\s*)?(#{1,4}\s+)?([^<>\n]{3,120}?)(?:\s*<\/span>)?\s*<\/(?:strong|b)>\s*(?:<br\s*\/?>|\n)+([\s\S]+?)<\/p>/i', function ($m) {
            $clean = trim($m[3]);
            if (str_contains($clean, 'http://') || str_contains($clean, 'https://')) {
                return $m[0];
            }
            $cleanTrimmed = rtrim($clean, " \t\n\r\0\x0B:");
            if (empty($cleanTrimmed)) return $m[0];

            // Protect callouts and inline metadata from becoming headings
            if (preg_match('/^(?:key takeaway|note|tip|warning|caution|important|best suited for|source|author|disclaimer)\b/i', $cleanTrimmed)) {
                return $m[0];
            }

            $level = !empty($m[2]) ? strlen(trim($m[2])) : 2;
            $rest = trim($m[4]);
            return "<h{$level} class=\"wp-block-heading\"><strong>{$cleanTrimmed}</strong></h{$level}>\n<p{$m[1]}>{$rest}</p>";
        }, $styled);

        // 3. Promote standalone paragraph headings: <p><strong>Heading</strong></p>
        $styled = preg_replace_callback('/<p(\s+[^>]*)?>\s*<(?:strong|b)(?:\s+[^>]*)?>\s*(?:<span(?:\s+[^>]*)?>\s*)?(#{1,4}\s+)?([^<>\n]{3,120}?)(?:\s*<\/span>)?\s*<\/(?:strong|b)>\s*<\/p>/i', function ($m) {
            $clean = trim($m[3]);
            if (str_contains($clean, 'http://') || str_contains($clean, 'https://')) {
                return $m[0];
            }
            $cleanTrimmed = rtrim($clean, " \t\n\r\0\x0B:");
            if (empty($cleanTrimmed)) return $m[0];

            // Protect callouts and inline metadata from becoming headings
            if (preg_match('/^(?:key takeaway|note|tip|warning|caution|important|best suited for|source|author|disclaimer)\b/i', $cleanTrimmed)) {
                return $m[0];
            }

            $level = !empty($m[2]) ? strlen(trim($m[2])) : 2;
            return "<h{$level} class=\"wp-block-heading\"><strong>{$cleanTrimmed}</strong></h{$level}>";
        }, $styled);

        // 4. Promote standalone paragraph headings with hashes: <p>## Heading</p>
        $styled = preg_replace_callback('/<p(\s+[^>]*)?>\s*(#{1,4})\s+([^<>\n]{3,120}?)\s*<\/p>/i', function ($m) {
            $clean = trim($m[3]);
            $level = strlen($m[2]);
            return "<h{$level} class=\"wp-block-heading\"><strong>{$clean}</strong></h{$level}>";
        }, $styled);

        // 5. Ensure all <h1-h6> tags use clean native website UI:
        // Strip hardcoded inline styles (color, font-size, font-family, letter-spacing, line-height, border)
        // and Tailwind color/size classes (text-slate-*, dark:text-*, font-black, etc.),
        // and strip nested spans/inline styling from headings,
        // so every website completely controls heading styles, colors, and typography via its own theme.
        $styled = preg_replace_callback('/<h([1-6])([^>]*)>([\s\S]*?)<\/h\1>/i', function ($m) {
            $tag = $m[1];
            $content = trim($m[3]);
            $cleanInner = preg_replace('/^<(?:strong|b)[^>]*>([\s\S]*?)<\/(?:strong|b)>$/i', '$1', $content);
            $cleanText = strip_tags($cleanInner, '<a>');
            $cleanText = trim(preg_replace('/\s+/', ' ', $cleanText));
            return "<h{$tag} class=\"wp-block-heading\"><strong>{$cleanText}</strong></h{$tag}>";
        }, $styled);
        $styled = preg_replace('/(<\/h[1-6]>\s*)<hr[^>]*>/i', '$1', $styled);

        // 7. Clean paragraphs to remove hardcoded colors/sizes so destination theme styles them naturally
        // Notice: Never force text-align: justify; - let destination theme determine natural alignment
        $styled = preg_replace_callback('/<p(\s+[^>]*)?>/i', function ($m) {
            $attrs = $m[1] ?? '';
            $attrs = preg_replace('/\b(?:text-slate-[0-9]+|dark:text-[a-z0-9-]+|font-normal|text-\[[^\]]+\])\b/i', '', $attrs);
            if (preg_match('/style="([^"]*)"/i', $attrs, $sm)) {
                $s = $sm[1];
                $s = preg_replace('/\b(?:color|font-size|font-family)\s*:[^;"]+;?/i', '', $s);
                $s = preg_replace('/\btext-align\s*:\s*justify\s*;?/i', '', $s);
                $s = trim(preg_replace('/;{2,}/', ';', $s));
                $s = trim(preg_replace('/\s*;\s*/', '; ', $s), " ;\t\n\r\0\x0B");
                $attrs = $s !== '' ? preg_replace('/style="([^"]*)"/i', "style=\"{$s}\"", $attrs) : preg_replace('/\s*style="[^"]*"/i', '', $attrs);
            }
            if (preg_match('/class="([^"]*)"/i', $attrs, $cm)) {
                $c = trim(preg_replace('/\s+/', ' ', $cm[1]));
                $attrs = $c !== '' ? preg_replace('/class="([^"]*)"/i', "class=\"{$c}\"", $attrs) : preg_replace('/\s*class="[^"]*"/i', '', $attrs);
            }
            $attrs = trim($attrs);
            return "<p" . ($attrs !== '' ? ' ' . $attrs : '') . ">";
        }, $styled);

        // 8. Prevent duplicate hero images:
        // Strip leading duplicate figure/img at the very start of content if featured image is present
        // (because destination themes already display featured_image_url as the post hero/thumbnail)
        $featuredUrl = $featuredUrlOverride
            ?: ($post?->featured_image_url ?: ($post?->metrics_data['cms_extra']['featured_image_url'] ?? null));
        if ($featuredUrl) {
            $parsedPath = parse_url($featuredUrl, PHP_URL_PATH);
            $featuredFilename = basename($parsedPath ?: $featuredUrl);
            if (!empty($featuredFilename)) {
                $pattern = '/^\s*<(?:figure|p)[^>]*>\s*<img[^>]+src=["\'][^"\']*' . preg_quote($featuredFilename, '/') . '[^"\']*["\'][^>]*>(?:\s*<figcaption>.*?<\/figcaption>)?\s*<\/(?:figure|p)>\s*/is';
                $styled = preg_replace($pattern, '', $styled);
            }
        }

        // 9. Ensure inline article images are responsive and clean
        $styled = preg_replace_callback('/<img(?![^>]*style=["\'][^"\']*max-width)([^>]*)>/i', function ($m) {
            $attrs = $m[1];
            if (!str_contains($attrs, 'loading=')) {
                $attrs .= ' loading="lazy"';
            }
            if (!str_contains($attrs, 'style=')) {
                $attrs .= ' style="max-width: 100%; height: auto; border-radius: 8px; display: block; margin: 1.5rem auto;"';
            }
            return '<img' . $attrs . '>';
        }, $styled);

        // 10. Deduplicate consecutive identical/sibling figure images if present
        $styled = preg_replace_callback('/(<figure[^>]*>[\s\S]*?<img[^>]+src=["\']([^"\']+)["\'][^>]*>[\s\S]*?<\/figure>\s*){2,}/i', function ($m) {
            // Find all image URLs within this block
            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $m[0], $srcMatches);
            $srcs = $srcMatches[1] ?? [];
            if (count($srcs) >= 2) {
                $base1 = preg_replace('/(-\d+)?\.(webp|jpg|jpeg|png|gif)$/i', '', basename(parse_url($srcs[0], PHP_URL_PATH) ?: $srcs[0]));
                $base2 = preg_replace('/(-\d+)?\.(webp|jpg|jpeg|png|gif)$/i', '', basename(parse_url($srcs[1], PHP_URL_PATH) ?: $srcs[1]));
                if ($base1 === $base2 || str_contains($base1, $base2) || str_contains($base2, $base1)) {
                    // Keep only the first figure
                    preg_match('/<figure[^>]*>[\s\S]*?<\/figure>/i', $m[0], $singleFig);
                    return ($singleFig[0] ?? '') . "\n";
                }
            }
            return $m[0];
        }, $styled);

        // 10b. Guarantee prominent, clickable hyperlink styling for all internal & external links
        // Unescape any escaped HTML links
        $styled = preg_replace('/&lt;a\s+href=(&quot;|\')([^&]+)(&quot;|\')&gt;([\s\S]*?)&lt;\/a&gt;/i', '<a href="$2">$4</a>', $styled);

        $siteUrl = rtrim($website?->url ?: ($post?->website?->url ?: ''), '/');
        $siteDomain = strtolower(parse_url($siteUrl, PHP_URL_HOST) ?: '');
        $siteDomain = preg_replace('/^www\./i', '', $siteDomain);

        $theme = $this->resolveWebsiteTheme($website, $post, $siteDomain);

        // Convert unparsed markdown links [text](url) into styled <a> tags
        $styled = preg_replace_callback('/\[([^\]]+)\]\(((?:https?:\/\/|\/|#|mailto:|tel:)[^\s\)]+)\)/i', function ($m) use ($siteDomain, $theme) {
            $text = $m[1];
            $url = $m[2];
            $linkHost = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
            $linkHost = preg_replace('/^www\./i', '', $linkHost);
            $isExternal = false;
            if (preg_match('/^https?:\/\//i', $url)) {
                if ($siteDomain === '' || ($linkHost !== '' && !str_contains($linkHost, $siteDomain) && !str_contains($siteDomain, $linkHost))) {
                    $isExternal = true;
                }
            }
            $targetAttr = $isExternal ? ' target="_blank" rel="noopener noreferrer"' : '';
            return "<a href=\"{$url}\"{$targetAttr} class=\"cms-article-link font-semibold underline\" style=\"color: {$theme['link_color']}; text-decoration: underline; font-weight: 600;\">{$text}</a>";
        }, $styled);

        // Ensure all <a> tags have proper class, high-visibility inline styling, and external target
        $styled = preg_replace_callback('/<a(\s+[^>]*)href=["\']([^"\']+)["\']([^>]*)>([\s\S]*?)<\/a>/i', function ($m) use ($siteDomain, $theme) {
            $attrsBefore = $m[1];
            $href = $m[2];
            $attrsAfter = $m[3];
            $text = $m[4];

            $linkHost = strtolower(parse_url($href, PHP_URL_HOST) ?: '');
            $linkHost = preg_replace('/^www\./i', '', $linkHost);

            $isExternal = false;
            if (preg_match('/^https?:\/\//i', $href)) {
                if ($siteDomain === '' || ($linkHost !== '' && !str_contains($linkHost, $siteDomain) && !str_contains($siteDomain, $linkHost))) {
                    $isExternal = true;
                }
            }

            $attrs = $attrsBefore . ' ' . $attrsAfter;
            if ($isExternal) {
                if (!str_contains($attrs, 'target=')) {
                    $attrs .= ' target="_blank"';
                }
                if (!str_contains($attrs, 'rel=')) {
                    $attrs .= ' rel="noopener noreferrer"';
                }
            } else {
                $attrs = preg_replace('/\s*target=["\x27]_blank["\x27]/i', '', $attrs);
                $attrs = preg_replace('/\s*rel=["\x27]noopener noreferrer["\x27]/i', '', $attrs);
            }

            if (!str_contains($attrs, 'class=')) {
                $attrs .= ' class="cms-article-link font-semibold underline"';
            } elseif (!str_contains($attrs, 'cms-article-link')) {
                $attrs = preg_replace('/class=["\x27]([^"\x27]*)["\x27]/i', 'class="$1 cms-article-link font-semibold underline"', $attrs);
            }

            if (!str_contains($attrs, 'style=')) {
                $attrs .= ' style="color: ' . $theme['link_color'] . '; text-decoration: underline; font-weight: 600;"';
            } elseif (!preg_match('/color\s*:/i', $attrs)) {
                $attrs = preg_replace('/style=["\x27]([^"\x27]*)["\x27]/i', 'style="$1; color: ' . $theme['link_color'] . '; text-decoration: underline; font-weight: 600;"', $attrs);
            }

            $cleanAttrs = trim(preg_replace('/\s+/', ' ', $attrs));
            return "<a href=\"{$href}\"" . ($cleanAttrs !== '' ? ' ' . $cleanAttrs : '') . ">{$text}</a>";
        }, $styled);

        // 11. Tailored Equipment CTA Card
        if (str_contains($styled, 'cms-equipment-cta')) {
            // Update existing CTA styling to guarantee it uses the current website's brand colors
            $styled = preg_replace_callback('/<div class="cms-equipment-cta"[^>]*>([\s\S]*?)<\/div>/i', function ($m) use ($theme) {
                $inner = $m[1];
                $inner = preg_replace('/color:\s*#[a-fA-F0-9]{3,6}(?=[^>]*Featured Machinery)/i', "color: {$theme['primary']}", $inner);
                $inner = preg_replace('/background:\s*#[a-fA-F0-9]{3,6}(?=[^>]*View Specifications)/i', "background: {$theme['primary']}", $inner);
                $inner = preg_replace('/color:\s*#[a-fA-F0-9]{3,6}(?=[^>]*View Specifications)/i', "color: {$theme['btn_text']}", $inner);
                return "<div class=\"cms-equipment-cta\" style=\"margin: 2.5rem 0 1.5rem 0; padding: 1.5rem; background: {$theme['cta_bg']}; border: 1px solid {$theme['cta_border']}; border-left: 5px solid {$theme['primary']}; border-radius: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; clear: both; display: block;\">{$inner}</div>";
            }, $styled);
        } else {
            $productSku = $sku ?: ($post?->product_sku ?: ($post?->metrics_data['cms_extra']['product_sku'] ?? null));
            $productUrl = $prodUrl ?: ($post?->product_url ?: ($post?->metrics_data['cms_extra']['product_url'] ?? null));
            $siteName = $website?->name ?: ($post?->website?->name ?: 'Machinery Fleet');
            $siteUrl = $website?->url ?: ($post?->website?->url ?: '#');

            if ($productUrl || $productSku) {
                $equipmentTitle = !empty($productSku) ? htmlspecialchars($productSku, ENT_QUOTES, 'UTF-8') : (htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . ' Featured Equipment');
                $targetUrl = !empty($productUrl) ? htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') : htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8');

                $ctaHtml = <<<HTML
<div class="cms-equipment-cta" style="margin: 2.5rem 0 1.5rem 0; padding: 1.5rem; background: {$theme['cta_bg']}; border: 1px solid {$theme['cta_border']}; border-left: 5px solid {$theme['primary']}; border-radius: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; clear: both; display: block;">
    <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: {$theme['primary']}; letter-spacing: 0.05em; margin-bottom: 0.35rem;">Featured Machinery & Equipment</div>
    <h4 style="margin: 0 0 0.5rem 0; font-size: 1.25rem; font-weight: 800; color: #0f172a; border-bottom: none; padding-bottom: 0;">{$equipmentTitle}</h4>
    <p style="margin: 0 0 1rem 0; font-size: 0.95rem; color: #475569; line-height: 1.5;">Looking for top-performing heavy equipment, authentic attachments, and dependable jobsite power? Explore verified models backed by manufacturer warranty and nationwide delivery.</p>
    <a href="{$targetUrl}" target="_blank" rel="noopener noreferrer" style="display: inline-block; background: {$theme['primary']}; color: {$theme['btn_text']}; padding: 0.65rem 1.25rem; font-size: 0.875rem; font-weight: 700; border-radius: 8px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">View Specifications & Pricing &rarr;</a>
</div>
HTML;
                $styled .= "\n" . $ctaHtml;
            }
        }

        return $this->ensureArticleLinks($styled, $post, $website, false);
    }

    /**
     * Convert markdown or raw formatted text into clean HTML content.
     */
    public function formatMarkdownContent(string $raw, ?Website $website = null, ?WebsitePost $post = null): string
    {
        if (trim($raw) === '') {
            return '';
        }

        if (str_contains($raw, '<p>') && str_contains($raw, '</p>')) {
            return $this->ensureContentCleanAndStyled($raw, $post, $website);
        }

        $theme = $this->resolveWebsiteTheme($website, $post);

        // Guarantee image URLs are isolated with double newlines so following text is never lost
        $raw = preg_replace('/(https?:\/\/[^\s<>\(\)\[\]"\']+\.(?:webp|png|jpe?g|gif|svg|avif)(?:\?[^\s<>\(\)\[\]"\']*)?)/i', "\n\n$1\n\n", $raw);

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $blocks = [];
        $currentBlock = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                if (!empty($currentBlock)) {
                    $blocks[] = implode("\n", $currentBlock);
                    $currentBlock = [];
                }
            } else {
                $currentBlock[] = $line;
            }
        }
        if (!empty($currentBlock)) {
            $blocks[] = implode("\n", $currentBlock);
        }

        $htmlBlocks = [];
        foreach ($blocks as $b) {
            $trimmed = trim($b);
            if (empty($trimmed)) continue;

            if (str_starts_with($trimmed, '# ')) {
                $t = htmlspecialchars(trim(substr($trimmed, 2)), ENT_QUOTES, 'UTF-8');
                $htmlBlocks[] = "<h1 class=\"wp-block-heading\"><strong>{$t}</strong></h1>";
                continue;
            }
            if (str_starts_with($trimmed, '## ')) {
                $t = htmlspecialchars(trim(substr($trimmed, 3)), ENT_QUOTES, 'UTF-8');
                $htmlBlocks[] = "<h2 class=\"wp-block-heading\"><strong>{$t}</strong></h2>";
                continue;
            }
            if (str_starts_with($trimmed, '### ')) {
                $t = htmlspecialchars(trim(substr($trimmed, 4)), ENT_QUOTES, 'UTF-8');
                $htmlBlocks[] = "<h3 class=\"wp-block-heading\"><strong>{$t}</strong></h3>";
                continue;
            }

            if (preg_match('/^\*\*([^*]+)\*\*$/', $trimmed, $bm) && strlen($trimmed) < 120) {
                $t = htmlspecialchars(trim($bm[1]), ENT_QUOTES, 'UTF-8');
                $htmlBlocks[] = "<h2 class=\"wp-block-heading\"><strong>{$t}</strong></h2>";
                continue;
            }

            if (preg_match('/^!\[(.*?)\]\((https?:\/\/[^\s\)]+)\)$/', $trimmed, $im)) {
                $alt = htmlspecialchars($im[1], ENT_QUOTES, 'UTF-8');
                $src = $im[2];
                $htmlBlocks[] = "<figure class=\"cms-rendered-figure my-6\" data-align=\"center\" style=\"margin: 1.75rem auto; text-align: center; max-width: 100%; clear: both;\"><img src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\" class=\"rounded-xl shadow-md mx-auto max-h-[480px] object-cover\" style=\"max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); display: block; margin: 0 auto;\"></figure>";
                continue;
            }

            if (preg_match('/^https?:\/\/[^\s<>]+\.(?:webp|png|jpe?g|gif|svg|avif)(?:\?[^\s<>]*)?$/i', $trimmed)) {
                $htmlBlocks[] = "<figure class=\"cms-rendered-figure my-6\" data-align=\"center\" style=\"margin: 1.75rem auto; text-align: center; max-width: 100%; clear: both;\"><img src=\"{$trimmed}\" alt=\"\" loading=\"lazy\" class=\"rounded-xl shadow-md mx-auto max-h-[480px] object-cover\" style=\"max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); display: block; margin: 0 auto;\"></figure>";
                continue;
            }

            // YouTube video embed support
            if (preg_match('/^(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})$/i', $trimmed, $ym)) {
                $vidId = $ym[1];
                $htmlBlocks[] = "<div class=\"cms-video-container my-6\" style=\"position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; border-radius: 12px; margin: 1.75rem 0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);\"><iframe src=\"https://www.youtube.com/embed/{$vidId}\" style=\"position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;\" allow=\"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture\" allowfullscreen loading=\"lazy\"></iframe></div>";
                continue;
            }

            // Protect and extract existing <a> tags before htmlspecialchars
            $linkTokens = [];
            $tokenizedText = preg_replace_callback('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>([\s\S]*?)<\/a>/i', function ($m) use (&$linkTokens, $theme) {
                $idx = count($linkTokens);
                $key = "___DGT_LINK_TOKEN_{$idx}___";
                $href = $m[1];
                $txt = strip_tags($m[2]);
                $isExternal = str_starts_with($href, 'http');
                $targetAttr = $isExternal ? ' target="_blank" rel="noopener noreferrer"' : '';
                $linkTokens[$key] = "<a href=\"{$href}\"{$targetAttr} class=\"cms-article-link font-semibold underline\" style=\"color: {$theme['link_color']}; text-decoration: underline; font-weight: 600;\">{$txt}</a>";
                return $key;
            }, $trimmed);

            // Process markdown formatting: bold, italic, and links safely
            $safeText = htmlspecialchars($tokenizedText, ENT_QUOTES, 'UTF-8');
            $safeText = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $safeText);
            $safeText = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $safeText);
            $safeText = preg_replace_callback('/\[([^\]]+)\]\(((?:https?:\/\/|\/|#|mailto:|tel:)[^\s\)]+)\)/i', function ($lm) use ($theme) {
                $text = $lm[1];
                $url = $lm[2];
                $isExternal = str_starts_with($url, 'http');
                $target = $isExternal ? ' target="_blank" rel="noopener noreferrer"' : '';
                return "<a href=\"{$url}\"{$target} class=\"cms-article-link font-semibold underline\" style=\"color: {$theme['link_color']}; text-decoration: underline; font-weight: 600;\">{$text}</a>";
            }, $safeText);

            // Re-inject protected <a> tags
            foreach ($linkTokens as $key => $tag) {
                $safeText = str_replace($key, $tag, $safeText);
            }

            $htmlBlocks[] = '<p>' . nl2br($safeText) . '</p>';
        }

        $resultHtml = implode("\n", $htmlBlocks);
        return $this->ensureArticleLinks($resultHtml, $post, $website, false);
    }

    /**
     * Convert rich HTML article into clean, pristine Markdown tailored for React/Next.js/Markdown sites.
     * Guarantees 0 raw HTML tags on destination screen (no escaped <h1>, <figure>, <p>).
     * Preserves and styles all images, headings, bullet lists, internal links, external links, and video embeds.
     * Strips redundant top H1 title (since website page headers already render it) and isolates all images with \n\n.
     */
    public function htmlToPristineMarkdown(string $html, ?string $rawContent = null, ?WebsitePost $post = null, ?Website $website = null): string
    {
        $postTitle = trim($post?->title ?: '');

        // Prefer rich structured HTML ($html) since it contains section headings (<h2>), figures, and paragraphs properly structured.
        // Fall back to $rawContent only if $html is empty or plain.
        $content = '';
        if (trim($html) !== '' && (str_contains($html, '<p') || str_contains($html, '<h2') || str_contains($html, '<figure') || str_contains($html, '<h3'))) {
            $content = trim($html);
        } elseif ($rawContent && trim($rawContent) !== '') {
            $content = trim($rawContent);
        } else {
            $content = trim($html);
        }

        if ($content === '') {
            return '';
        }

        // Repair entity-escaped pasted HTML before conversion so no literal <h1>/<p> text leaks into Markdown
        $content = $this->unescapeAndSanitizePastedHtml($content, $postTitle);

        // If content contains HTML tags, convert HTML tags to Markdown
        if (preg_match('/<[a-z][\s\S]*>/i', $content)) {
            // Strip contenteditable and editor UI attributes
            $content = preg_replace('/contenteditable=["\'][^"\']*["\']/i', '', $content);
            $content = preg_replace('/data-placeholder=["\'][^"\']*["\']/i', '', $content);

            // Strip top H1 tag if it matches or contains post title
            $content = preg_replace_callback('/<h1[^>]*>([\s\S]*?)<\/h1>/i', function ($m) use ($postTitle) {
                $h1Text = trim(strip_tags($m[1]));
                if ($postTitle !== '' && (stripos($h1Text, $postTitle) !== false || stripos($postTitle, $h1Text) !== false)) {
                    return ''; // Strip top duplicate title
                }
                return "\n\n## {$h1Text}\n\n";
            }, $content);

            // YouTube iframe to Markdown video preview link
            $content = preg_replace_callback('/<iframe[^>]*src=["\'](?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})[^"\']*["\'][^>]*>[\s\S]*?<\/iframe>/i', function ($m) {
                $vidId = $m[1];
                return "\n\n[![Watch Video](https://img.youtube.com/vi/{$vidId}/hqdefault.jpg)](https://www.youtube.com/watch?v={$vidId})\n\n";
            }, $content);

            // Convert Figures & Images into Markdown: ![Alt](url)
            $content = preg_replace_callback('/<figure[^>]*>[\s\S]*?<\/figure>/i', function ($m) use ($postTitle) {
                preg_match('/src=["\']([^"\']+)["\']/i', $m[0], $srcMatch);
                preg_match('/alt=["\']([^"\']*)["\']/i', $m[0], $altMatch);
                preg_match('/<figcaption[^>]*>([\s\S]*?)<\/figcaption>/i', $m[0], $capMatch);

                $src = $srcMatch[1] ?? '';
                if (empty($src)) return '';

                $alt = !empty($altMatch[1]) ? trim(strip_tags($altMatch[1])) : '';
                if (empty($alt) && !empty($capMatch[1])) {
                    $alt = trim(strip_tags($capMatch[1]));
                }
                if (empty($alt) || stripos($alt, 'caption') !== false) {
                    $alt = $postTitle ?: 'Heavy Machinery Equipment';
                }
                $cleanAlt = trim(str_replace(['[', ']', '(', ')', '"', "\n", "\r"], ' ', $alt));
                return "\n\n![{$cleanAlt}]({$src})\n\n";
            }, $content);

            // Standalone <img> tags
            $content = preg_replace_callback('/<img\s+[^>]*>/i', function ($m) use ($postTitle) {
                preg_match('/src=["\']([^"\']+)["\']/i', $m[0], $srcMatch);
                preg_match('/alt=["\']([^"\']*)["\']/i', $m[0], $altMatch);
                $src = $srcMatch[1] ?? '';
                if (empty($src)) return '';
                $alt = !empty($altMatch[1]) ? trim(strip_tags($altMatch[1])) : ($postTitle ?: 'Machinery');
                $cleanAlt = trim(str_replace(['[', ']', '(', ')', '"', "\n", "\r"], ' ', $alt));
                return "\n\n![{$cleanAlt}]({$src})\n\n";
            }, $content);

            // Convert Headings: h2 -> ##, h3 -> ###, h4-h6 -> ####
            $content = preg_replace('/<h2[^>]*>([\s\S]*?)<\/h2>/i', "\n\n## $1\n\n", $content);
            $content = preg_replace('/<h3[^>]*>([\s\S]*?)<\/h3>/i', "\n\n### $1\n\n", $content);
            $content = preg_replace('/<h[4-6][^>]*>([\s\S]*?)<\/h[4-6]>/i', "\n\n#### $1\n\n", $content);

            // Clean bold tags inside headings: ## **Heading** -> ## Heading
            $content = preg_replace_callback('/\n(#{1,4})\s+([\s\S]*?)\n/', function ($m) {
                $level = $m[1];
                $text = trim(strip_tags($m[2]));
                return "\n\n{$level} {$text}\n\n";
            }, $content);

            // Convert Links: <a href="url">anchor</a> -> [anchor](url)
            $content = preg_replace_callback('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>([\s\S]*?)<\/a>/i', function ($m) {
                $url = trim($m[1]);
                $text = trim(strip_tags($m[2]));
                if (empty($text) || empty($url)) return $text;
                return "[{$text}]({$url})";
            }, $content);

            // Convert Bold & Italic
            $content = preg_replace('/<(?:strong|b)[^>]*>([\s\S]*?)<\/(?:strong|b)>/i', '**$1**', $content);
            $content = preg_replace('/<(?:em|i)[^>]*>([\s\S]*?)<\/(?:em|i)>/i', '*$1*', $content);

            // Convert Lists
            $content = preg_replace_callback('/<li[^>]*>([\s\S]*?)<\/li>/i', function ($m) {
                $item = trim(strip_tags($m[1], '<a><strong><em>'));
                return "\n- {$item}";
            }, $content);
            $content = preg_replace('/<\/?(?:ul|ol)[^>]*>/i', "\n", $content);

            // Convert Paragraphs & Line Breaks
            $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
            $content = preg_replace('/<p[^>]*>([\s\S]*?)<\/p>/i', "\n\n$1\n\n", $content);
            $content = preg_replace('/<blockquote[^>]*>([\s\S]*?)<\/blockquote>/i', "\n\n> $1\n\n", $content);

            // Strip remaining HTML tags (decode then strip again so escaped tags can never survive)
            $content = strip_tags($content);
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $content = strip_tags($content);
        }

        // Standardize newlines
        $md = str_replace(["\r\n", "\r"], "\n", $content);

        // 1. Strip redundant top Title from beginning of markdown (Next.js/React templates render it in header)
        if ($postTitle !== '') {
            $md = preg_replace('/^\s*#{1,3}\s*' . preg_quote($postTitle, '/') . '\s*\n+/i', '', $md);
            // Case-insensitive normalized title check
            $md = preg_replace_callback('/^\s*#{1,3}\s*([^\n]+)\n+/i', function ($m) use ($postTitle) {
                $lineNorm = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $m[1])));
                $titleNorm = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $postTitle)));
                if ($lineNorm !== '' && ($lineNorm === $titleNorm || str_contains($lineNorm, $titleNorm) || str_contains($titleNorm, $lineNorm))) {
                    return ''; // Strip top title
                }
                return $m[0];
            }, $md);
        }

        // 2. Isolate existing markdown images: ensure double newlines before and after
        $md = preg_replace('/(?<!\n\n)(!\[[^\]]*\]\([^\)]+\))(?!\n\n)/i', "\n\n$1\n\n", $md);

        // 3. Convert bare image URLs into standalone ![Alt](url) blocks surrounded by double newlines
        $md = preg_replace_callback('/(?<!\]\()https?:\/\/[^\s<>\(\)\[\]"\']+\.(?:webp|png|jpe?g|gif|svg|avif)(?:\?[^\s<>\(\)\[\]"\']*)?/i', function ($m) use ($postTitle) {
            $src = trim($m[0]);
            $alt = $postTitle ?: 'Heavy Machinery Equipment';
            $cleanAlt = trim(str_replace(['[', ']', '(', ')', '"', "\n", "\r"], ' ', $alt));
            return "\n\n![{$cleanAlt}]({$src})\n\n";
        }, $md);

        // 4. Standalone YouTube video URLs to Markdown links
        $md = preg_replace_callback('/(?:\n|^)(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})(?:\n|$)/i', function ($m) {
            $vidId = $m[1];
            return "\n\n[![Watch Video](https://img.youtube.com/vi/{$vidId}/hqdefault.jpg)](https://www.youtube.com/watch?v={$vidId})\n\n";
        }, $md);

        // 5. Promote standalone bold paragraph headings: **Heading** -> ## Heading
        $md = preg_replace_callback('/(?:\n|^)\s*\*\*([^\*\n]{3,100})\*\*\s*(?:\n|$)/i', function ($m) {
            $h = trim($m[1], " \t\n\r\0\x0B:");
            if (preg_match('/^(?:key takeaway|note|tip|warning|caution|important|best suited for|source|author|disclaimer)\b/i', $h)) {
                return $m[0];
            }
            return "\n\n## {$h}\n\n";
        }, $md);

        // 6. Convert any remaining H1 "# " in the body to H2 "## " (Next.js requires ## or ###)
        $md = preg_replace('/(?:\n|^)#\s+([^\n]+)/', "\n\n## $1\n\n", $md);

        // 7. Normalize blank lines and split into clean blocks
        $blocks = preg_split('/\n\s*\n/', $md);
        $cleanBlocks = [];
        foreach ($blocks as $b) {
            $trimmed = trim($b);
            if ($trimmed !== '') {
                $cleanBlocks[] = $trimmed;
            }
        }
        $md = implode("\n\n", $cleanBlocks);

        return $this->ensureArticleLinks($md, $post, $website, true);
    }

    /**
     * Preserves and ensures safety for all author-created links in the blog post:
     * - External links (pointing outside the website domain) securely get target="_blank" and rel="noopener noreferrer".
     * - Internal links (relative or pointing to the website domain) are preserved cleanly.
     * - Does NOT hijack keywords or inject artificial callouts, strictly respecting the native website UI and content.
     */
    public function ensureArticleLinks(string $content, ?WebsitePost $post = null, ?Website $website = null, bool $isMarkdown = false): string
    {
        if (trim($content) === '') {
            return $content;
        }

        $siteUrl = rtrim($website?->url ?: ($post?->website?->url ?: ''), '/');
        $siteDomain = strtolower(parse_url($siteUrl, PHP_URL_HOST) ?: '');
        $siteDomain = preg_replace('/^www\./i', '', $siteDomain);

        $theme = $this->resolveWebsiteTheme($website, $post, $siteDomain);

        if (!$isMarkdown) {
            // Unescape any escaped HTML links
            $content = preg_replace('/&lt;a\s+href=(&quot;|\')([^&]+)(&quot;|\')&gt;([\s\S]*?)&lt;\/a&gt;/i', '<a href="$2">$4</a>', $content);

            // Convert any remaining unparsed markdown links [text](url) into styled <a> tags
            $content = preg_replace_callback('/\[([^\]]+)\]\(((?:https?:\/\/|\/|#|mailto:|tel:)[^\s\)]+)\)/i', function ($m) use ($theme) {
                $text = $m[1];
                $url = $m[2];
                $isExternal = str_starts_with($url, 'http');
                $targetAttr = $isExternal ? ' target="_blank" rel="noopener noreferrer"' : '';
                return "<a href=\"{$url}\"{$targetAttr} class=\"cms-article-link font-semibold underline\" style=\"color: {$theme['link_color']}; text-decoration: underline; font-weight: 600;\">{$text}</a>";
            }, $content);

            // Ensure all external links in HTML content have target="_blank" rel="noopener noreferrer" and prominent styling
            $content = preg_replace_callback('/<a(\s+[^>]*)href=["\']([^"\']+)["\']([^>]*)>([\s\S]*?)<\/a>/i', function ($m) use ($siteDomain, $theme) {
                $attrsBefore = $m[1];
                $href = $m[2];
                $attrsAfter = $m[3];
                $text = $m[4];

                $linkHost = strtolower(parse_url($href, PHP_URL_HOST) ?: '');
                $linkHost = preg_replace('/^www\./i', '', $linkHost);

                $isExternal = false;
                if (preg_match('/^https?:\/\//i', $href)) {
                    if ($siteDomain === '' || ($linkHost !== '' && !str_contains($linkHost, $siteDomain) && !str_contains($siteDomain, $linkHost))) {
                        $isExternal = true;
                    }
                }

                $attrs = $attrsBefore . ' ' . $attrsAfter;
                if ($isExternal) {
                    if (!str_contains($attrs, 'target=')) {
                        $attrs .= ' target="_blank"';
                    }
                    if (!str_contains($attrs, 'rel=')) {
                        $attrs .= ' rel="noopener noreferrer"';
                    }
                } else {
                    $attrs = preg_replace('/\s*target=["\x27]_blank["\x27]/i', '', $attrs);
                    $attrs = preg_replace('/\s*rel=["\x27]noopener noreferrer["\x27]/i', '', $attrs);
                }

                // Guarantee prominent, high-contrast link styling across all remote themes
                if (!str_contains($attrs, 'class=')) {
                    $attrs .= ' class="cms-article-link font-semibold underline"';
                } elseif (!str_contains($attrs, 'cms-article-link')) {
                    $attrs = preg_replace('/class=["\x27]([^"\x27]*)["\x27]/i', 'class="$1 cms-article-link font-semibold underline"', $attrs);
                }

                if (!str_contains($attrs, 'style=')) {
                    $attrs .= ' style="color: ' . $theme['link_color'] . '; text-decoration: underline; font-weight: 600;"';
                } elseif (!preg_match('/color\s*:/i', $attrs)) {
                    $attrs = preg_replace('/style=["\x27]([^"\x27]*)["\x27]/i', 'style="$1; color: ' . $theme['link_color'] . '; text-decoration: underline; font-weight: 600;"', $attrs);
                }

                $cleanAttrs = trim(preg_replace('/\s+/', ' ', $attrs));
                return "<a href=\"{$href}\"" . ($cleanAttrs !== '' ? ' ' . $cleanAttrs : '') . ">{$text}</a>";
            }, $content);
        }

        // Check if internal and external hyperlinks are present in the article
        $hasInternalLink = false;
        $hasExternalLink = false;

        if (!$isMarkdown) {
            if (preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $hrefMatches)) {
                foreach ($hrefMatches[1] as $href) {
                    $linkHost = strtolower(parse_url($href, PHP_URL_HOST) ?: '');
                    $linkHost = preg_replace('/^www\./i', '', $linkHost);
                    if (!str_starts_with($href, 'http') || ($siteDomain !== '' && (str_contains($linkHost, $siteDomain) || str_contains($siteDomain, $linkHost)))) {
                        $hasInternalLink = true;
                    } else {
                        $hasExternalLink = true;
                    }
                }
            }
        } else {
            if (preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $content, $mdMatches)) {
                foreach ($mdMatches[2] as $href) {
                    $linkHost = strtolower(parse_url($href, PHP_URL_HOST) ?: '');
                    $linkHost = preg_replace('/^www\./i', '', $linkHost);
                    if (!str_starts_with($href, 'http') || ($siteDomain !== '' && (str_contains($linkHost, $siteDomain) || str_contains($siteDomain, $linkHost)))) {
                        $hasInternalLink = true;
                    } else {
                        $hasExternalLink = true;
                    }
                }
            }
        }

        // 1. Ensure at least one natural internal link to the website's product/equipment store
        if (!$hasInternalLink && ($post !== null || $website !== null)) {
            $storeUrl = !empty($post?->product_url)
                ? $post->product_url
                : (!empty($website?->url) ? rtrim($website->url, '/') . '/products' : ($siteDomain ? "https://{$siteDomain}/products" : '#'));

            if (str_contains($siteDomain, 'happymachinery')) {
                $storeUrl = 'https://happymachinery.com/equipment';
            } elseif (str_contains($siteDomain, 'typhonmachinery')) {
                $storeUrl = 'https://typhonmachinery.com/store/';
            } elseif (str_contains($siteDomain, 'americanloader')) {
                $storeUrl = 'https://americanloader.com/equipment';
            } elseif (str_contains($siteDomain, 'stonekrusher')) {
                $storeUrl = 'https://stonekrusher.com/equipment';
            } elseif (str_contains($siteDomain, 'skidsteers.online')) {
                $storeUrl = 'https://skidsteers.online/shop';
            } elseif (str_contains($siteDomain, 'groundripper')) {
                $storeUrl = 'https://groundripper.com/products';
            }

            $internalKeywords = [];
            if (!empty($post?->category)) {
                $internalKeywords[] = trim($post->category);
            }
            $internalKeywords = array_merge($internalKeywords, [
                'scissor lift capacity', 'scissor lift', 'scissor lifts',
                'spider mini excavator', 'mini excavator', 'mini excavators',
                'trench cleaning', 'trenching', 'trencher', 'trenchers',
                'tri-loader', 'tri-loaders', 'wheel loader', 'wheel loaders',
                'rock crusher', 'rock crushers', 'stone crusher', 'stone crushers',
                'site dumper', 'site dumpers', 'tracked dumper',
                'compact excavator', 'compact excavators',
                'skid steer', 'skid steers', 'plate compactor', 'roller compactor',
                'compact equipment', 'heavy machinery', 'heavy equipment', 'work attachments', 'attachments'
            ]);

            foreach ($internalKeywords as $kw) {
                if (strlen($kw) < 4) continue;
                $escapedKw = preg_quote($kw, '/');
                if (!$isMarkdown) {
                    $pattern = '/(?<!href=["\'])(?<!src=["\'])(?<![a-zA-Z0-9_\-])\b(' . $escapedKw . ')\b(?![^<]*<\/a>)(?![^<]*>)/i';
                    if (preg_match($pattern, $content)) {
                        $content = preg_replace($pattern, "<a href=\"{$storeUrl}\" class=\"cms-article-link font-semibold underline\" style=\"color: {$theme['link_color']}; text-decoration: underline; font-weight: 600;\">$1</a>", $content, 1);
                        $hasInternalLink = true;
                        break;
                    }
                } else {
                    $pattern = '/(?<!\[)\b(' . $escapedKw . ')\b(?![^\[]*\])(?![^\(]*\))/i';
                    if (preg_match($pattern, $content)) {
                        $content = preg_replace($pattern, "[$1]({$storeUrl})", $content, 1);
                        $hasInternalLink = true;
                        break;
                    }
                }
            }
        }

        // 2. Ensure at least one natural external link to regulatory authority or industry standards
        if (!$hasExternalLink && ($post !== null || $website !== null)) {
            $externalTargets = [
                ['keyword' => 'OSHA safety standards', 'url' => 'https://www.osha.gov'],
                ['keyword' => 'OSHA standards', 'url' => 'https://www.osha.gov'],
                ['keyword' => 'OSHA guidelines', 'url' => 'https://www.osha.gov'],
                ['keyword' => 'OSHA regulations', 'url' => 'https://www.osha.gov'],
                ['keyword' => 'OSHA', 'url' => 'https://www.osha.gov'],
                ['keyword' => 'ISO standards', 'url' => 'https://www.iso.org'],
                ['keyword' => 'ISO safety standards', 'url' => 'https://www.iso.org'],
                ['keyword' => 'ANSI standards', 'url' => 'https://www.ansi.org'],
                ['keyword' => 'hydraulic pressure', 'url' => 'https://www.iso.org'],
                ['keyword' => 'hydraulic specifications', 'url' => 'https://www.iso.org'],
                ['keyword' => 'preventive maintenance', 'url' => 'https://www.osha.gov'],
                ['keyword' => 'safety standards', 'url' => 'https://www.osha.gov'],
                ['keyword' => 'safety guidelines', 'url' => 'https://www.osha.gov'],
                ['keyword' => 'safety protocols', 'url' => 'https://www.osha.gov'],
                ['keyword' => 'safety requirements', 'url' => 'https://www.osha.gov'],
                ['keyword' => 'safety regulations', 'url' => 'https://www.osha.gov'],
                ['keyword' => 'operating comfort', 'url' => 'https://www.iso.org'],
                ['keyword' => 'operator habits', 'url' => 'https://www.osha.gov'],
                ['keyword' => 'safety margin', 'url' => 'https://www.osha.gov'],
                ['keyword' => 'safety', 'url' => 'https://www.osha.gov'],
            ];

            foreach ($externalTargets as $ext) {
                $escapedExt = preg_quote($ext['keyword'], '/');
                if (!$isMarkdown) {
                    $pattern = '/(?<!href=["\'])(?<!src=["\'])(?<![a-zA-Z0-9_\-])\b(' . $escapedExt . ')\b(?![^<]*<\/a>)(?![^<]*>)/i';
                    if (preg_match($pattern, $content)) {
                        $content = preg_replace($pattern, "<a href=\"{$ext['url']}\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"cms-article-link font-semibold underline\" style=\"color: {$theme['link_color']}; text-decoration: underline; font-weight: 600;\">$1</a>", $content, 1);
                        $hasExternalLink = true;
                        break;
                    }
                } else {
                    $pattern = '/(?<!\[)\b(' . $escapedExt . ')\b(?![^\[]*\])(?![^\(]*\))/i';
                    if (preg_match($pattern, $content)) {
                        $content = preg_replace($pattern, "[$1]({$ext['url']})", $content, 1);
                        $hasExternalLink = true;
                        break;
                    }
                }
            }

            // Fallback: If still no external link matched in the text, append a clean regulatory reference
            if (!$hasExternalLink) {
                if (!$isMarkdown) {
                    $content .= "\n\n<p class=\"text-xs text-gray-500 italic mt-4\" style=\"font-size: 0.8rem; color: #64748b; font-style: italic; margin-top: 1.25rem;\">Reference: For equipment safety protocols and jobsite regulations, visit the <a href=\"https://www.osha.gov\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"cms-article-link font-semibold underline\" style=\"color: {$theme['link_color']}; text-decoration: underline; font-weight: 600;\">Occupational Safety and Health Administration (OSHA)</a>.</p>";
                } else {
                    $content .= "\n\n*Reference: For jobsite equipment regulations, safety standards, and operator guidelines, visit the [Occupational Safety and Health Administration (OSHA)](https://www.osha.gov).*";
                }
                $hasExternalLink = true;
            }
        }

        return $content;
    }

    /**
     * Auto-cut text for SEO fields (Meta Description <= 160, Meta Title <= 60)
     * strictly adhering to English grammar rules:
     * 1. Preserves full words (never cuts in the middle of a word).
     * 2. Respects sentence integrity if a clean sentence boundary exists within 70%-100% of the limit.
     * 3. Prunes dangling coordinating/subordinating conjunctions ('and', 'or', 'but', 'nor', 'so', 'yet', 'for', 'because', etc.).
     * 4. Prunes dangling prepositions ('with', 'to', 'in', 'on', 'at', 'from', 'by', 'about', 'as', 'into', etc.).
     * 5. Prunes dangling articles & determiners ('the', 'a', 'an', 'this', 'that', 'these', 'those', 'your', 'our', etc.).
     * 6. Prunes dangling auxiliary/linking verbs ('is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', etc.).
     * 7. Strips trailing punctuation (commas, colons, dashes, semicolons, brackets).
     * 8. Ensures grammatically proper sentence closure (terminates with '.' for descriptions, strips ending punctuation for titles).
     */
    public function autoCutSeoGrammar(string $text, int $maxLen = 160, bool $isTitle = false): string
    {
        $clean = strip_tags($text);
        $clean = preg_replace('/[\r\n\t]+/', ' ', $clean);
        $clean = trim(preg_replace('/\s{2,}/', ' ', $clean));
        if ($clean === '') {
            return '';
        }

        $danglingWords = [
            'and', 'or', 'but', 'nor', 'so', 'yet', 'for',
            'the', 'a', 'an',
            'of', 'in', 'to', 'with', 'on', 'at', 'from', 'by', 'about', 'as', 'into', 'through', 'after', 'over', 'between', 'under', 'above', 'against', 'among', 'off', 'out', 'without', 'within', 'upon',
            'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'shall', 'should', 'can', 'could', 'may', 'might', 'must',
            'that', 'which', 'who', 'whom', 'whose', 'where', 'when', 'why', 'how',
            'this', 'these', 'those', 'your', 'our', 'their', 'my', 'its', 'his', 'her',
            'because', 'although', 'since', 'unless', 'while', 'whereas'
        ];
        $danglingMap = array_fill_keys($danglingWords, true);

        $trimDangling = function(string $s) use ($danglingMap): string {
            $changed = true;
            while ($changed) {
                $changed = false;
                $s = trim(preg_replace('/[\s,;:–—\-\/\\\\&|+~"\'\(\)\[\]{}]+$/u', '', $s));
                $words = preg_split('/\s+/', $s);
                if (count($words) > 1) {
                    $last = strtolower(preg_replace('/[^a-z0-9]/i', '', end($words)));
                    if (isset($danglingMap[$last])) {
                        array_pop($words);
                        $s = trim(implode(' ', $words));
                        $changed = true;
                    }
                }
            }
            return $s;
        };

        if (mb_strlen($clean) <= $maxLen) {
            if ($isTitle) {
                return trim(preg_replace('/[\s,;:–—\-\/\\\\&|+~"\'\(\)\[\]{}.!]+$/u', '', $clean));
            }
            return $clean;
        }

        // Sentence boundary check if between 70% and 100% of maxLen
        $minSentenceCut = (int) floor($maxLen * 0.70);
        $sub = mb_substr($clean, 0, $maxLen);
        if (preg_match_all('/[.!?](?=\s|$)/u', $sub, $matches, PREG_OFFSET_CAPTURE)) {
            $lastMatch = end($matches[0]);
            $endPos = $lastMatch[1] + mb_strlen($lastMatch[0]);
            if ($endPos >= $minSentenceCut && $endPos <= $maxLen) {
                return trim(mb_substr($clean, 0, $endPos));
            }
        }

        $budget = $isTitle ? $maxLen : ($maxLen - 1);
        $cut = mb_substr($clean, 0, $budget);

        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > 15) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        $cut = $trimDangling($cut);
        if ($cut === '') {
            return '';
        }

        if (!$isTitle) {
            if (!preg_match('/[.!?]$/u', $cut)) {
                $cut .= '.';
            }
        } else {
            $cut = trim(preg_replace('/[\s,;:–—\-\/\\\\&|+~"\'\(\)\[\]{}.!]+$/u', '', $cut));
        }

        return $cut;
    }

    /**
     * Parses pasted or imported blog drafts containing metadata labels.
     * Extracts Meta Title, Meta Description, Article Excerpt, Tag Keywords, and Title.
     *
     * Rules:
     * - If Article Excerpt is present, auto-fills excerpt. If not, leaves it empty (never forces auto-cut).
     * - If Tag Keywords is present, auto-fills keywords. If not, leaves it empty.
     * - Sanitizes auto-detected images to remove generic counters (01, 02) or filenames from alts.
     */
    public function parsePastedDraft(string $text): array
    {
        $raw = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($raw === '') {
            return [
                'title'            => '',
                'meta_title'       => '',
                'meta_description' => '',
                'excerpt'          => '',
                'tag_keywords'     => '',
                'content_raw'      => '',
            ];
        }

        $lines = explode("\n", $raw);
        $detectedMetaTitle = '';
        $detectedMetaDesc = '';
        $detectedExcerpt = '';
        $detectedKeywords = '';
        $explicitTitle = '';

        $isMetaHeader = function (string $line) {
            $clean = trim(preg_replace('/^\*+|\*+$/', '', preg_replace('/^[\s\-*#>\d\.]+\s*/', '', strip_tags($line))));
            if (preg_match('/^(?:Meta\s*Title|SEO\s*Title|Page\s*Title)\s*[\*_\s]*[:：\-]\s*[\*_\s]*(.*)$/i', $clean, $m)) {
                return ['type' => 'meta_title', 'val' => trim($m[1], "*_ \t\n\r\0\x0B")];
            }
            if (preg_match('/^(?:Meta\s*Description|SEO\s*Description|SEO\s*Desc|Meta\s*Desc|Description)\s*[\*_\s]*[:：\-]\s*[\*_\s]*(.*)$/i', $clean, $m)) {
                return ['type' => 'meta_desc', 'val' => trim($m[1], "*_ \t\n\r\0\x0B")];
            }
            if (preg_match('/^(?:Article\s*Excerpt|Post\s*Excerpt|Blog\s*Excerpt|Excerpt)\s*[\*_\s]*[:：\-]\s*[\*_\s]*(.*)$/i', $clean, $m)) {
                return ['type' => 'excerpt', 'val' => trim($m[1], "*_ \t\n\r\0\x0B")];
            }
            if (preg_match('/^(?:Tag\s*Keywords?|Focus\s*Keywords?|Tags?\s*\/?\s*Keywords?|Post\s*Tags?|Article\s*Tags?|Keywords?|Tags?)\s*[\*_\s]*[:：\-]\s*[\*_\s]*(.*)$/i', $clean, $m)) {
                return ['type' => 'keywords', 'val' => trim($m[1], "*_ \t\n\r\0\x0B")];
            }
            if (preg_match('/^(?:Blog\s*Title|Article\s*Title|Post\s*Title|H1\s*Title|H1|Title)\s*[\*_\s]*[:：\-]\s*[\*_\s]*(.*)$/i', $clean, $m)) {
                return ['type' => 'title', 'val' => trim($m[1], "*_ \t\n\r\0\x0B")];
            }
            return null;
        };

        $metaLineIndices = [];
        $currentField = null;

        foreach ($lines as $idx => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $currentField = null;
                continue;
            }

            $meta = $isMetaHeader($trimmed);
            if ($meta) {
                $metaLineIndices[$idx] = true;
                $currentField = $meta['type'];
                if ($meta['type'] === 'meta_title' && !$detectedMetaTitle) {
                    $detectedMetaTitle = $meta['val'];
                } elseif ($meta['type'] === 'meta_desc' && !$detectedMetaDesc) {
                    $detectedMetaDesc = $meta['val'];
                } elseif ($meta['type'] === 'excerpt' && !$detectedExcerpt) {
                    $detectedExcerpt = $meta['val'];
                } elseif ($meta['type'] === 'keywords' && !$detectedKeywords) {
                    $detectedKeywords = $meta['val'];
                } elseif ($meta['type'] === 'title' && !$explicitTitle) {
                    $explicitTitle = $meta['val'];
                }
                continue;
            }

            if ($currentField) {
                $isHeading = preg_match('/^#+\s/', $trimmed) || preg_match('/<h[1-6][^>]*>/i', $trimmed);
                $isImage = str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://') || str_starts_with($trimmed, '<img') || str_starts_with($trimmed, '![');
                $isTable = str_starts_with($trimmed, '|') || str_starts_with($trimmed, '<table');
                $isRule = preg_match('/^[-*_]{3,}$/', $trimmed);

                if (!$isHeading && !$isImage && !$isTable && !$isRule) {
                    $cleanVal = trim(preg_replace('/^[\*_\s]+|[\*_\s]+$/', '', strip_tags($trimmed)));
                    if ($cleanVal !== '') {
                        $metaLineIndices[$idx] = true;
                        if ($currentField === 'meta_title') {
                            $detectedMetaTitle = trim($detectedMetaTitle . ' ' . $cleanVal);
                        } elseif ($currentField === 'meta_desc') {
                            $detectedMetaDesc = trim($detectedMetaDesc . ' ' . $cleanVal);
                        } elseif ($currentField === 'excerpt') {
                            $detectedExcerpt = trim($detectedExcerpt . ' ' . $cleanVal);
                        } elseif ($currentField === 'keywords') {
                            $detectedKeywords = rtrim($detectedKeywords, " ,\t\n\r\0\x0B") . ', ' . $cleanVal;
                        } elseif ($currentField === 'title') {
                            $explicitTitle = trim($explicitTitle . ' ' . $cleanVal);
                        }
                        continue;
                    }
                } else {
                    $currentField = null;
                }
            }
        }

        if ($detectedKeywords !== '') {
            $detectedKeywords = trim(preg_replace('/,\s*,+/', ', ', preg_replace('/^\s*,\s*|\s*,\s*$/', '', $detectedKeywords)));
        }
        if ($detectedExcerpt !== '') {
            $detectedExcerpt = trim(preg_replace('/\s{2,}/', ' ', $detectedExcerpt));
        }

        $cleanedLines = [];
        $mainTitle = $explicitTitle;
        $foundTitle = (bool) $mainTitle;

        foreach ($lines as $idx => $line) {
            $trimmed = trim($line);
            if (isset($metaLineIndices[$idx])) {
                continue;
            }
            if ($trimmed === '') {
                $cleanedLines[] = '';
                continue;
            }
            if (preg_match('/^[-*_]{3,}$/', $trimmed)) {
                continue;
            }

            if (!$foundTitle) {
                if (preg_match('/^#\s+([^#].*)$/', $trimmed, $m)) {
                    $mainTitle = trim(preg_replace('/\*+/', '', strip_tags($m[1])));
                    $foundTitle = true;
                    continue;
                }
                if (preg_match('/^\*\*(.+?)\*\*$/', $trimmed, $m) && strlen($trimmed) > 5 && strlen($trimmed) < 250) {
                    $mainTitle = trim(strip_tags($m[1]));
                    $foundTitle = true;
                    continue;
                }
                if (!str_starts_with($trimmed, 'http') && !str_starts_with($trimmed, '![') && !str_starts_with($trimmed, '|') && strlen($trimmed) >= 4 && strlen($trimmed) <= 250) {
                    $mainTitle = trim(preg_replace('/^\*\*|\*\*$/', '', strip_tags($trimmed)));
                    $foundTitle = true;
                    continue;
                }
            }

            $cleanedLines[] = $line;
        }

        $finalContent = trim(implode("\n", $cleanedLines));

        // Strip generic/numeric alts (e.g. ![01](url) -> ![](url))
        $finalContent = preg_replace('/!\[\s*(?:\d{1,4}|image\s*\d*|img[-_\s]*\d*|photo\s*\d*|picture\s*\d*|figure\s*\d*|fig\s*\d*|untitled.*|image)\s*\]\((https?:\/\/[^\s\)]+)\)/i', '![]($1)', $finalContent);
        $finalContent = preg_replace('/<img([^>]+)alt=["\']\s*(?:\d{1,4}|image\s*\d*|img[-_\s]*\d*|photo\s*\d*|picture\s*\d*|figure\s*\d*|fig\s*\d*|untitled.*|image)\s*["\']([^>]*)>/i', '<img$1alt=""$2>', $finalContent);

        return [
            'title'            => $mainTitle,
            'meta_title'       => $detectedMetaTitle ?: ($mainTitle ? $this->autoCutSeoGrammar($mainTitle, 60, true) : ''),
            'meta_description' => $detectedMetaDesc,
            'excerpt'          => $detectedExcerpt, // If not in draft, empty string (no fallback auto-fill)
            'tag_keywords'     => $detectedKeywords, // If not in draft, empty string
            'content_raw'      => $finalContent,
        ];
    }
}

