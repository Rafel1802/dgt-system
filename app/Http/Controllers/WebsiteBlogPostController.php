<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\WebsitePost;
use App\Services\WebsiteBlogPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class WebsiteBlogPostController extends Controller
{
    public function __construct(
        private readonly WebsiteBlogPostService $blogPostService
    ) {}

    /**
     * Display the Blog Post CMS page with Website Picker, Smart Paste Editor, and Latest 10 Posts.
     */
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403, 'Access denied. You do not have permission to access the Website system.');

        $selectedWebsiteId = $request->get('website_id');

        // Fetch all active websites with their integration & handler info (cached 5 min for instant loads)
        $websites = Cache::remember('dgt_active_websites_for_cms', 300, function () {
            return Website::where(function ($q) {
                $q->where('is_archived', false)->orWhereNull('is_archived');
            })
                ->with(['integration', 'handler:id,name,email,avatar'])
                ->orderBy('name')
                ->get()
                ->map(function (Website $w) {
                    $connector = $w->integration?->connector_type ?: 'central_api';
                    $cmsStatus = $w->integration?->cms_status ?: 'ready';

                    $cleanDomain = preg_replace('/^www\./i', '', parse_url($w->url, PHP_URL_HOST) ?: $w->name);
                    $handlerName = (string) ($w->handler?->name ?: 'Unassigned');
                    $initial = strtoupper(substr($handlerName, 0, 1));
                    $logo = $w->logo_src ?: "https://www.google.com/s2/favicons?domain={$cleanDomain}&sz=64";
                    $favicon = "https://www.google.com/s2/favicons?domain={$cleanDomain}&sz=64";

                    return [
                        'id'              => (int) $w->id,
                        'name'            => (string) $w->name,
                        'url'             => (string) $w->url,
                        'clean_domain'    => $cleanDomain,
                        'category'        => (string) ($w->category ?: 'General'),
                        'logo'            => (string) $logo,
                        'favicon'         => (string) $favicon,
                        'handler_id'      => $w->handled_by ? (int) $w->handled_by : null,
                        'handler_name'    => $handlerName,
                        'handler_avatar'  => (string) ($w->handler?->avatar_url ?: ''),
                        'handler_initial' => $initial,
                        'connector_type'  => (string) $connector,
                        'cms_status'      => (string) $cmsStatus,
                        'has_connector'   => (bool) ($w->integration && $w->integration->is_active),
                    ];
                })
                ->all();
        });

        $selectedWebsite = null;
        if ($selectedWebsiteId) {
            $selectedWebsite = collect($websites)->firstWhere('id', (int) $selectedWebsiteId);
        }

        // Build rich, tailored website profiles for every site (cached 5 min for instant loads)
        $websiteProfiles = Cache::remember('dgt_website_profiles_for_cms', 300, function () {
            $websiteModels = Website::where(function ($q) {
                $q->where('is_archived', false)->orWhereNull('is_archived');
            })->with('handler:id,name,avatar')->get();
            $profiles = [];
            foreach ($websiteModels as $wm) {
                $profiles[$wm->id] = $this->resolveWebsiteProfile($wm);
            }
            return $profiles;
        });

        // Distinct categories across the fleet for autocomplete
        $allCategories = Cache::remember('dgt_fleet_categories_list', 300, function () {
            return Website::whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->filter()
                ->sort()
                ->values()
                ->all();
        });

        // Latest posts across all fleet websites (optimized 30-post hydration for instant zero-lag page loading)
        // CRITICAL: Always include recent drafts and scheduled posts so filtering by Drafts or Scheduled shows them immediately!
        $latestPosts = Cache::remember('dgt_latest_posts_cms_v2', 180, function () {
            $latest = $this->blogPostService->getPosts(null, null, 30);
            $drafts = $this->blogPostService->getPosts(null, 'draft', 15);
            $scheduled = $this->blogPostService->getPosts(null, 'scheduled', 15);

            return collect(array_merge($drafts, $scheduled, $latest))
                ->reject(function ($p) {
                    $title = strtolower($p['title'] ?? '');
                    $author = strtolower($p['author_name'] ?? '');
                    $img = strtolower($p['featured_image_url'] ?? '');
                    return str_contains($title, 'casino') || str_contains($title, 'spins') || str_contains($title, 'slott')
                        || str_contains($title, 'universal heavy machinery') || str_contains($title, 'fleet integration verification') || str_contains($title, 'diagnostic fleet connectivity')
                        || str_contains($img, '1579829366248')
                        || in_array($author, ['hansa hood', 'me nas', 'michael cerrick', 'monita en', 'john vicas', 'fleet safety board']);
                })
                ->unique('id')
                ->values()
                ->all();
        });

        // Current website profile
        $currentProfile = $selectedWebsiteId && isset($websiteProfiles[$selectedWebsiteId])
            ? $websiteProfiles[$selectedWebsiteId]
            : (!empty($websiteProfiles) ? reset($websiteProfiles) : null);

        // Summary stats for dashboard front view (cleanly grouped into 3 statuses)
        $stats = Cache::remember('dgt_cms_posts_summary_stats', 60, function () use ($websites) {
            return [
                'total'       => WebsitePost::count(),
                'published'   => WebsitePost::whereIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public'])->count(),
                'draft'       => WebsitePost::where(function ($q) {
                    $q->whereNotIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public', WebsitePost::STATUS_SCHEDULED, 'future'])
                        ->orWhereNull('status');
                })->count(),
                'scheduled'   => WebsitePost::whereIn('status', [WebsitePost::STATUS_SCHEDULED, 'future'])->count(),
                'sites_count' => count($websites),
            ];
        });

        return view('websites.posts.index', compact(
            'websites',
            'selectedWebsite',
            'selectedWebsiteId',
            'websiteProfiles',
            'currentProfile',
            'allCategories',
            'latestPosts',
            'stats'
        ));
    }

    /**
     * Render an authentic public blog post preview in a new tab without saving to database.
     */
    public function previewStaging(Request $request)
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403, 'Access denied.');

        $websiteId = $request->input('website_id');
        $website = $websiteId ? Website::find($websiteId) : null;

        $title = trim((string) $request->input('title', 'Untitled Article'));
        if (empty($title)) {
            $title = 'Untitled Article';
        }

        $slug = $request->input('slug') ?: \Illuminate\Support\Str::slug($title);
        $contentHtml = (string) $request->input('content_html', '');
        $rawContent = (string) $request->input('raw_content', '');

        if (empty($contentHtml) && !empty($rawContent)) {
            $contentHtml = $this->blogPostService->formatMarkdownContent($rawContent);
        }

        if (empty($contentHtml)) {
            $contentHtml = '<p class="text-slate-500 italic">No article content written yet. Write content in the editor to see your live preview here.</p>';
        }

        $featuredImageUrl = $request->input('featured_image_url');
        if (empty($featuredImageUrl)) {
            preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $contentHtml, $m);
            if (!empty($m[1])) {
                $featuredImageUrl = $m[1];
            }
        }

        // Deduplicate leading image if featured image matches
        if ($featuredImageUrl) {
            $contentHtml = preg_replace('/<p[^>]*>\s*<img[^>]+src=["\']' . preg_quote($featuredImageUrl, '/') . '["\'][^>]*>\s*<\/p>/i', '', $contentHtml, 1);
        }

        $post = new WebsitePost([
            'website_id'         => $website?->id,
            'title'              => $title,
            'slug'               => $slug,
            'content_html'       => $contentHtml,
            'raw_content'        => $rawContent,
            'featured_image_url' => $featuredImageUrl,
            'category'           => $request->input('category', 'General'),
            'author_name'        => $request->input('author_name', $website?->name ?: auth()->user()->name),
            'meta_title'         => $request->input('meta_title', $title),
            'meta_description'   => $request->input('meta_description', \Illuminate\Support\Str::limit(strip_tags($contentHtml), 155)),
            'tag_keywords'       => $request->input('tag_keywords'),
            'status'             => 'preview',
            'published_at'       => now(),
        ]);

        if ($website) {
            $post->setRelation('website', $website);
        }

        $siteName = $website ? ($website->name ?: $website->clean_domain) : 'DGT Media';
        $siteUrl = $website ? $website->url : url('/');
        $authorName = $post->author_name ?: $siteName;

        $wordCount = str_word_count(strip_tags($contentHtml));
        $readingMinutes = max(1, (int) ceil($wordCount / 200));

        $relatedPosts = $website
            ? WebsitePost::where('website_id', $website->id)
                ->whereIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public'])
                ->latest('id')
                ->take(3)
                ->get()
            : collect();

        $isPreview = true;

        return view('public.blog.show', compact(
            'post',
            'website',
            'contentHtml',
            'siteName',
            'siteUrl',
            'authorName',
            'readingMinutes',
            'wordCount',
            'relatedPosts',
            'isPreview'
        ));
    }

    /**
     * Generate a secure 10-minute preview link and cache the transient article payload.
     */
    public function generatePreviewToken(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403, 'Access denied.');

        $websiteId = $request->input('website_id');
        $title = trim((string) $request->input('title', 'Untitled Article'));
        if (empty($title)) {
            $title = 'Untitled Article';
        }

        $slug = $request->input('slug') ?: Str::slug($title);
        $contentHtml = (string) $request->input('content_html', '');
        $rawContent = (string) $request->input('raw_content', '');

        if (empty($contentHtml) && !empty($rawContent)) {
            $contentHtml = $this->blogPostService->formatMarkdownContent($rawContent);
        }

        if (empty($contentHtml)) {
            $contentHtml = '<p class="text-slate-500 italic">No article content written yet. Write content in the editor to see your live preview here.</p>';
        }

        $featuredImageUrl = $request->input('featured_image_url');
        if (empty($featuredImageUrl)) {
            preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $contentHtml, $m);
            if (!empty($m[1])) {
                $featuredImageUrl = $m[1];
            }
        }

        if ($featuredImageUrl) {
            $contentHtml = preg_replace('/<p[^>]*>\s*<img[^>]+src=["\']' . preg_quote($featuredImageUrl, '/') . '["\'][^>]*>\s*<\/p>/i', '', $contentHtml, 1);
        }

        $token = Str::random(40);
        $expiresAt = now()->addMinutes(10);

        $payload = [
            'website_id'         => $websiteId,
            'title'              => $title,
            'slug'               => $slug,
            'content_html'       => $contentHtml,
            'raw_content'        => $rawContent,
            'featured_image_url' => $featuredImageUrl,
            'category'           => $request->input('category', 'General'),
            'author_name'        => $request->input('author_name'),
            'meta_title'         => $request->input('meta_title', $title),
            'meta_description'   => $request->input('meta_description', Str::limit(strip_tags($contentHtml), 155)),
            'tag_keywords'       => $request->input('tag_keywords'),
            'created_by'         => auth()->id(),
            'expires_at'         => $expiresAt->timestamp,
        ];

        Cache::put("blog_preview_{$token}", $payload, $expiresAt);

        $previewUrl = route('public.blog.preview', ['token' => $token]);

        return response()->json([
            'success'     => true,
            'token'       => $token,
            'preview_url' => $previewUrl,
            'expires_at'  => $expiresAt->toISOString(),
            'expires_in'  => 600,
        ]);
    }

    /**
     * Display a live public article preview via a 10-minute temporary token.
     */
    public function showPreviewByToken(string $token)
    {
        $payload = Cache::get("blog_preview_{$token}");

        if (!$payload) {
            return response()->view('public.blog.expired', [
                'token' => $token,
            ], 410);
        }

        $website = !empty($payload['website_id']) ? Website::find($payload['website_id']) : null;

        $post = new WebsitePost([
            'website_id'         => $website?->id,
            'title'              => $payload['title'] ?? 'Untitled Article',
            'slug'               => $payload['slug'] ?? 'untitled',
            'content_html'       => $payload['content_html'] ?? '',
            'raw_content'        => $payload['raw_content'] ?? '',
            'featured_image_url' => $payload['featured_image_url'] ?? null,
            'category'           => $payload['category'] ?? 'General',
            'author_name'        => $payload['author_name'] ?: ($website?->name ?: 'DGT Media'),
            'meta_title'         => $payload['meta_title'] ?? $payload['title'],
            'meta_description'   => $payload['meta_description'] ?? '',
            'tag_keywords'       => $payload['tag_keywords'] ?? '',
            'status'             => 'preview',
            'published_at'       => now(),
        ]);

        if ($website) {
            $post->setRelation('website', $website);
        }

        $contentHtml = $payload['content_html'] ?? '';
        $siteName = $website ? ($website->name ?: $website->clean_domain) : 'DGT Media';
        $siteUrl = $website ? $website->url : url('/');
        $authorName = $post->author_name ?: $siteName;

        $wordCount = str_word_count(strip_tags($contentHtml));
        $readingMinutes = max(1, (int) ceil($wordCount / 200));

        $relatedPosts = $website
            ? WebsitePost::where('website_id', $website->id)
                ->whereIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public'])
                ->latest('id')
                ->take(3)
                ->get()
            : collect();

        $isPreview = true;
        $previewToken = $token;
        $previewExpiresAt = $payload['expires_at'] ?? (time() + 600);
        $previewShareUrl = route('public.blog.preview', ['token' => $token]);

        return view('public.blog.show', compact(
            'post',
            'website',
            'contentHtml',
            'siteName',
            'siteUrl',
            'authorName',
            'readingMinutes',
            'wordCount',
            'relatedPosts',
            'isPreview',
            'previewToken',
            'previewExpiresAt',
            'previewShareUrl'
        ));
    }

    /**
     * Store or update a blog post via AJAX from the CMS editor.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403);

        $validated = $request->validate([
            'website_id'         => 'required|integer|exists:websites,id',
            'title'              => 'required|string|max:500',
            'slug'               => 'nullable|string|max:500',
            'content_html'       => 'required|string',
            'raw_content'        => 'nullable|string',
            'status'             => 'required|in:published,draft,scheduled,publish,public,future',
            'published_at'       => 'nullable|date',
            'scheduled_at'       => 'nullable|date',
            'featured_image_url' => 'nullable|string|max:1000',
            'cover_alt'          => 'nullable|string|max:500',
            'excerpt'            => 'nullable|string',
            'author_name'        => 'nullable|string|max:255',
            'category'           => 'nullable|string|max:255',
            'playlist'           => 'nullable|string|max:255',
            'video_url'          => 'nullable|string|max:1000',
            'product_sku'        => 'nullable|string|max:255',
            'product_url'        => 'nullable|string|max:1000',
            'meta_title'         => 'nullable|string|max:500',
            'meta_description'   => 'nullable|string',
            'tag_keywords'       => 'nullable|string|max:1000',
            'focus_keyword'      => 'nullable|string|max:255',
            'post_id'            => 'nullable|integer',
        ]);

        try {
            $result = $this->blogPostService->saveBlogPost($validated, auth()->id());
            Cache::forget('dgt_cms_posts_summary_stats');
            Cache::forget('dgt_latest_500_posts_cms');
            Cache::forget('dgt_latest_10_posts_all');
            Cache::forget('dgt_latest_10_posts_' . $validated['website_id']);
            $latestPosts = $this->blogPostService->getPosts(null, null, 500);

            $stats = [
                'total'       => WebsitePost::count(),
                'published'   => WebsitePost::whereIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public'])->count(),
                'draft'       => WebsitePost::where(function ($q) {
                    $q->whereNotIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public', WebsitePost::STATUS_SCHEDULED, 'future'])
                        ->orWhereNull('status');
                })->count(),
                'scheduled'   => WebsitePost::whereIn('status', [WebsitePost::STATUS_SCHEDULED, 'future'])->count(),
                'sites_count' => Website::where(fn($q) => $q->where('is_archived', false)->orWhereNull('is_archived'))->count(),
            ];

            $remoteSynced = $result['remote_synced'] ?? false;
            $successMsg = $remoteSynced
                ? 'Blog post saved and published live! ' . $result['remote_message']
                : 'Blog post saved in DGT Central. ' . $result['remote_message'];

            return response()->json([
                'success'        => true,
                'message'        => $successMsg,
                'post'           => [
                    'id'            => $result['post']->id,
                    'title'         => $result['post']->title,
                    'slug'          => $result['post']->slug,
                    'canonical_url' => $result['post']->canonical_url,
                    'status'        => $result['post']->status,
                ],
                'remote_synced'  => $remoteSynced,
                'remote_message' => $result['remote_message'],
                'remote_url'     => $result['remote_url'],
                'sync_warning'   => (!$remoteSynced && empty($result['remote_url'])) ? 'Post not yet live on website. Please configure the integration in Website Settings.' : null,
                'latest_posts'   => $latestPosts,
                'stats'          => $stats,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving blog post: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Store or update multiple blog posts in a single batch (One-click multi-article posting).
     */
    public function batchStore(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403);

        $validated = $request->validate([
            'posts'                      => 'required|array|min:1',
            'posts.*.website_id'         => 'required|integer|exists:websites,id',
            'posts.*.title'              => 'required|string|max:500',
            'posts.*.slug'               => 'nullable|string|max:500',
            'posts.*.content_html'       => 'required|string',
            'posts.*.raw_content'        => 'nullable|string',
            'posts.*.status'             => 'required|in:published,draft,scheduled,publish,public,future',
            'posts.*.published_at'       => 'nullable|date',
            'posts.*.scheduled_at'       => 'nullable|date',
            'posts.*.featured_image_url' => 'nullable|string|max:1000',
            'posts.*.cover_alt'          => 'nullable|string|max:500',
            'posts.*.excerpt'            => 'nullable|string',
            'posts.*.author_name'        => 'nullable|string|max:255',
            'posts.*.category'           => 'nullable|string|max:255',
            'posts.*.playlist'           => 'nullable|string|max:255',
            'posts.*.video_url'          => 'nullable|string|max:1000',
            'posts.*.product_sku'        => 'nullable|string|max:255',
            'posts.*.product_url'        => 'nullable|string|max:1000',
            'posts.*.meta_title'         => 'nullable|string|max:500',
            'posts.*.meta_description'   => 'nullable|string',
            'posts.*.tag_keywords'       => 'nullable|string|max:1000',
            'posts.*.focus_keyword'      => 'nullable|string|max:255',
            'posts.*.post_id'            => 'nullable|integer',
        ]);

        $results = [];
        $affectedWebsiteIds = [];

        try {
            foreach ($validated['posts'] as $postData) {
                $res = $this->blogPostService->saveBlogPost($postData, auth()->id());
                $affectedWebsiteIds[] = (int) $postData['website_id'];
                $results[] = [
                    'id'             => $res['post']->id,
                    'title'          => $res['post']->title,
                    'slug'           => $res['post']->slug,
                    'website_id'     => $res['post']->website_id,
                    'status'         => $res['post']->status,
                    'remote_synced'  => $res['remote_synced'],
                    'remote_message' => $res['remote_message'],
                    'remote_url'     => $res['remote_url'],
                ];
            }

            Cache::forget('dgt_cms_posts_summary_stats');
            Cache::forget('dgt_latest_10_posts_all');
            foreach (array_unique($affectedWebsiteIds) as $wid) {
                Cache::forget('dgt_latest_10_posts_' . $wid);
            }

            $latestPosts = $this->blogPostService->getPosts(null, null, 500);

            $stats = [
                'total'       => WebsitePost::count(),
                'published'   => WebsitePost::whereIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public'])->count(),
                'draft'       => WebsitePost::where(function ($q) {
                    $q->whereNotIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public', WebsitePost::STATUS_SCHEDULED, 'future'])
                        ->orWhereNull('status');
                })->count(),
                'scheduled'   => WebsitePost::whereIn('status', [WebsitePost::STATUS_SCHEDULED, 'future'])->count(),
                'sites_count' => Website::where(fn($q) => $q->where('is_archived', false)->orWhereNull('is_archived'))->count(),
            ];

            return response()->json([
                'success'      => true,
                'message'      => count($results) . ' article(s) processed and published successfully!',
                'results'      => $results,
                'posts'        => $results,
                'latest_posts' => $latestPosts,
                'stats'        => $stats,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error batch processing articles: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Retrieve blog posts dynamically (AJAX) supporting status (published, draft, scheduled, all),
     * flexible per-page limit (10, 20, 30, 50, all), website_id, and search query.
     */
    public function latest10(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403);

        $siteId = $request->get('website_id') ? (int) $request->get('website_id') : null;
        $status = $request->get('status');
        $limit  = $request->get('limit', 10);
        $search = $request->get('search');

        $posts = $this->blogPostService->getPosts($siteId, $status, $limit, $search);

        return response()->json([
            'success' => true,
            'posts'   => $posts,
            'total'   => count($posts),
        ]);
    }

    /**
     * Retrieve a specific blog post for loading into the CMS editor.
     */
    public function show(WebsitePost $post): JsonResponse
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403);

        $post->load(['website:id,name,url,category']);

        $cmsExtra = $post->metrics_data['cms_extra'] ?? [];

        $contentHtml = $post->content_html ?: ($cmsExtra['content_html'] ?? null);
        $rawContent  = $post->raw_content ?: ($cmsExtra['raw_content'] ?? null);

        // Guarantee paragraphs are never empty: synthesizes from excerpt or title if blank
        if (empty($contentHtml) && empty($rawContent)) {
            $title = trim((string) $post->title) ?: 'Equipment Guide';
            $excerpt = trim((string) $post->excerpt);
            $siteCleanName = preg_replace('/\.(com|org|net|eu|io|co|us|online|wiki)$/i', '', $post->website?->name ?: 'Industry');
            $cleanSiteName = ucwords(str_replace(['-', '_'], ' ', $siteCleanName)) ?: 'Editorial Team';

            if (!empty($excerpt)) {
                $rawContent = "{$excerpt}\n\n## Overview & Practical Applications\nUnderstanding proper operation, maintenance guidelines, and equipment specifications is vital for achieving peak performance and high durability on the job site.\n\n## Operational Guidelines & Maintenance\nAlways perform pre-shift inspections, monitor hydraulic and engine fluid levels, and adhere to standard safety protocols recommended by {$cleanSiteName}.\n\n## Summary\nConsistent maintenance and disciplined operational routines ensure long-term reliability and minimize equipment downtime.";
            } else {
                $rawContent = "Welcome to this in-depth guide on {$title}. This article explores the core features, operational insights, and recommended techniques to help you make informed decisions.\n\n## Key Technical Specifications & Highlights\nReview the primary engineering characteristics, build quality, and operational capabilities designed to meet heavy-duty project requirements.\n\n## Best Practices & Safety Protocols\nFollow standardized site safety measures, verify daily operating checklists, and maintain proper operating parameters to maximize productivity.\n\n## Conclusion\nStay tuned to {$cleanSiteName} for ongoing updates, technical specifications, and expert industry advice.";
            }
            $contentHtml = nl2br($rawContent);
        } elseif (empty($rawContent) && !empty($contentHtml)) {
            $rawContent = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</h1>', '</h2>', '</h3>'], "\n\n", $contentHtml));
        } elseif (empty($contentHtml) && !empty($rawContent)) {
            $contentHtml = nl2br($rawContent);
        }

        return response()->json([
            'success' => true,
            'post'    => [
                'id'                 => $post->id,
                'website_id'         => $post->website_id,
                'website_name'       => $post->website?->name,
                'title'              => $post->title,
                'slug'               => $post->slug,
                'canonical_url'      => $post->canonical_url,
                'author_name'        => $post->author_name,
                'status'             => $post->status,
                'published_at'       => $post->published_at?->format('Y-m-d\TH:i'),
                'scheduled_at'       => $post->scheduled_at?->format('Y-m-d\TH:i'),
                'featured_image_url' => $post->featured_image_url,
                'cover_alt'          => $cmsExtra['cover_alt'] ?? null,
                'excerpt'            => $post->excerpt,
                'content_html'       => $contentHtml,
                'raw_content'        => $rawContent,
                'meta_title'         => $post->meta_title,
                'meta_description'   => $post->meta_description,
                'focus_keyword'      => $cmsExtra['focus_keyword'] ?? null,
                'category'           => $post->category,
                'playlist'           => $post->playlist,
                'video_url'          => $cmsExtra['video_url'] ?? null,
                'product_sku'        => $cmsExtra['product_sku'] ?? null,
                'product_url'        => $cmsExtra['product_url'] ?? null,
            ],
        ]);
    }

    /**
     * Delete a blog post.
     */
    public function destroy(WebsitePost $post): JsonResponse
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403);

        $siteId = $post->website_id;
        $title = $post->title;

        // Dispatches remote deletion so post is removed from destination website too!
        try {
            $this->blogPostService->deleteRemotePost($post);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Remote post deletion warning for post #{$post->id}: " . $e->getMessage());
        }

        $post->delete();

        Cache::forget('dgt_latest_10_posts_all');
        Cache::forget('dgt_latest_10_posts_' . $siteId);
        Cache::forget('dgt_cms_posts_summary_stats');

        $latestPosts = $this->blogPostService->getPosts(null, null, 500);

        $stats = [
            'total'       => WebsitePost::count(),
            'published'   => WebsitePost::whereIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public'])->count(),
            'draft'       => WebsitePost::where(function ($q) {
                $q->whereNotIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public', WebsitePost::STATUS_SCHEDULED, 'future'])
                    ->orWhereNull('status');
            })->count(),
            'scheduled'   => WebsitePost::whereIn('status', [WebsitePost::STATUS_SCHEDULED, 'future'])->count(),
            'sites_count' => Website::where(fn($q) => $q->where('is_archived', false)->orWhereNull('is_archived'))->count(),
        ];

        return response()->json([
            'success'      => true,
            'message'      => "Blog post \"{$title}\" has been removed.",
            'latest_posts' => $latestPosts,
            'stats'        => $stats,
        ]);
    }

    /**
     * Public / Connector API: Allow ANY remote or framework website to fetch its latest posts.
     * Can query by website ID or domain name.
     */
    public function apiGetWebsitePosts($website, Request $request): JsonResponse
    {
        if (!($website instanceof Website)) {
            $found = is_numeric($website)
                ? Website::find($website)
                : Website::where('url', 'like', "%{$website}%")->orWhere('name', 'like', "%{$website}%")->first();

            if (!$found) {
                return response()->json([
                    'success' => false,
                    'message' => 'Website not found.',
                ], 404);
            }
            $website = $found;
        }

        $limit = min(50, max(1, (int) $request->get('limit', 10)));
        $rawStatus = $request->get('status', WebsitePost::STATUS_PUBLISHED);
        $status = WebsitePost::normalizeStatus($rawStatus);

        $postsQuery = WebsitePost::where('website_id', $website->id);
        if ($status === WebsitePost::STATUS_PUBLISHED) {
            $postsQuery->whereIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public']);
        } elseif ($status === WebsitePost::STATUS_SCHEDULED) {
            $postsQuery->whereIn('status', [WebsitePost::STATUS_SCHEDULED, 'future']);
        } else {
            $postsQuery->whereNotIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public', WebsitePost::STATUS_SCHEDULED, 'future']);
        }

        $posts = $postsQuery
            ->orderByDesc('published_at')
            ->take($limit)
            ->get([
                'id',
                'external_id',
                'title',
                'slug',
                'canonical_url',
                'author_name',
                'status',
                'published_at',
                'featured_image_url',
                'excerpt',
                'content_html',
                'category',
                'playlist',
                'meta_title',
                'meta_description',
                'cumulative_views',
            ]);

        return response()->json([
            'success'      => true,
            'website'      => [
                'id'   => $website->id,
                'name' => $website->name,
                'url'  => $website->url,
            ],
            'total_posts'  => $posts->count(),
            'posts'        => $posts,
        ]);
    }

    /**
     * Public / Connector API: Retrieve single post by slug for framework websites.
     */
    public function apiGetWebsitePostBySlug($website, string $slug): JsonResponse
    {
        if (!($website instanceof Website)) {
            $found = is_numeric($website)
                ? Website::find($website)
                : Website::where('url', 'like', "%{$website}%")->orWhere('name', 'like', "%{$website}%")->first();

            if (!$found) {
                return response()->json([
                    'success' => false,
                    'message' => 'Website not found.',
                ], 404);
            }
            $website = $found;
        }

        $post = WebsitePost::where('website_id', $website->id)
            ->where('slug', $slug)
            ->first();

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found on this website.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'website' => [
                'id'   => $website->id,
                'name' => $website->name,
                'url'  => $website->url,
            ],
            'post'    => $post,
        ]);
    }

    /**
     * Public RSS 2.0 Feed for ANY website across the fleet (WordPress, Astro, Webflow, Shopify, custom PHP).
     */
    public function apiGetWebsiteRssFeed($website): \Illuminate\Http\Response
    {
        if (!($website instanceof Website)) {
            $found = is_numeric($website)
                ? Website::find($website)
                : Website::where('url', 'like', "%{$website}%")->orWhere('name', 'like', "%{$website}%")->first();

            if (!$found) {
                return response('Website not found', 404);
            }
            $website = $found;
        }

        $posts = WebsitePost::where('website_id', $website->id)
            ->whereIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public'])
            ->orderByDesc('published_at')
            ->take(30)
            ->get();

        $siteUrl = rtrim($website->url, '/');
        $feedTitle = htmlspecialchars($website->name . ' - Blog Posts', ENT_XML1);
        $feedLink = htmlspecialchars($siteUrl, ENT_XML1);
        $feedDesc = htmlspecialchars("Latest editorial and technical blog posts for {$website->name}", ENT_XML1);
        $buildDate = now()->toRfc2822String();

        $itemsXml = '';
        foreach ($posts as $post) {
            $itemTitle = htmlspecialchars($post->title, ENT_XML1);
            $itemLink = htmlspecialchars($post->canonical_url ?: "{$siteUrl}/blog/{$post->slug}", ENT_XML1);
            $itemDesc = htmlspecialchars($post->excerpt ?: Str::limit(strip_tags($post->content_html), 250), ENT_XML1);
            $itemDate = ($post->published_at ?: $post->created_at)->toRfc2822String();
            $author = htmlspecialchars($post->author_name ?: $website->name, ENT_XML1);
            $cat = htmlspecialchars($post->category ?: 'General', ENT_XML1);
            $contentHtml = $post->content_html ?: '';

            $itemsXml .= "\n    <item>\n"
                       . "      <title>{$itemTitle}</title>\n"
                       . "      <link>{$itemLink}</link>\n"
                       . "      <guid isPermaLink=\"true\">{$itemLink}</guid>\n"
                       . "      <pubDate>{$itemDate}</pubDate>\n"
                       . "      <category>{$cat}</category>\n"
                       . "      <dc:creator><![CDATA[{$author}]]></dc:creator>\n"
                       . "      <description>{$itemDesc}</description>\n"
                       . "      <content:encoded><![CDATA[{$contentHtml}]]></content:encoded>\n"
                       . "    </item>";
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n"
             . "<rss version=\"2.0\"\n"
             . "     xmlns:content=\"http://purl.org/rss/1.0/modules/content/\"\n"
             . "     xmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n"
             . "     xmlns:atom=\"http://www.w3.org/2005/Atom\">\n"
             . "  <channel>\n"
             . "    <title>{$feedTitle}</title>\n"
             . "    <link>{$feedLink}</link>\n"
             . "    <description>{$feedDesc}</description>\n"
             . "    <language>en-US</language>\n"
             . "    <lastBuildDate>{$buildDate}</lastBuildDate>\n"
             . $itemsXml . "\n"
             . "  </channel>\n"
             . "</rss>";

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }

    /**
     * Build rich, tailored website profile containing real authors, real categories,
     * topic series, feature flags (has_series, has_store_product), and domain prefixes.
     */
    public function resolveWebsiteProfile(Website $website): array
    {
        $domain = parse_url($website->url, PHP_URL_HOST) ?: Str::slug($website->name) . '.com';
        $domain = preg_replace('/^www\./i', '', $domain);

        // Pre-defined profiles based on live production domains and operational setups
        $profiles = [
            'happymachinery.com' => [
                'default_author'   => 'Happy Machinery',
                'authors'          => ['Happy Machinery'],
                'categories'       => ['Jobsite Productivity', 'Equipment Maintenance', 'Attachments & Tools', 'Compact Loaders', 'Skid Steer Comparisons', 'Hydraulic Systems'],
                'series'           => ['None (Standalone Article)', 'Mini Skid Steer Operations & Safety Guide', 'Hydraulic System Maintenance & Troubleshooting', 'Jobsite Productivity & Attachment Selection'],
                'has_series'       => true,
                'has_store_product'=> true,
                'has_video'        => true,
                'product_models'   => ['HM-SKID900 Stand-On Loader', 'HM-MINI1000 Tracked Dumper', 'HM-ATTACH Hydraulic Breaker'],
                'store_url'        => 'https://happymachinery.com/equipment',
                'slug_prefix'      => 'happymachinery.com/blog/',
            ],
            'typhonmachinery.com' => [
                'default_author'   => 'TYPHON Machinery',
                'authors'          => ['TYPHON Machinery'],
                'categories'       => ['Heavy Compact Excavators', 'Mini Excavators & Diggers', 'Kubota Diesel Power', 'Hydraulics & Piston Pumps', 'Excavator Attachments & Thumbs', 'Site Preparation & Trenching', 'Maintenance & Inverted Boom Care'],
                'series'           => ['None (Standalone Article)', 'KUVUO Series Field Performance Guides', 'Heavy Compact Excavator Sizing & Engineering', 'Kubota Powertrain & Hydraulic Diagnostics', 'Excavator Attachment Masterclass'],
                'has_series'       => true,
                'has_store_product'=> true,
                'has_video'        => true,
                'product_models'   => ['TYPHON KUVUO 4.0 (TYPH-KUVUO)', 'TYPHON KUVUO 2.7 KONSTRUCTZ (TYPH-KUVUO27)', 'TYPHON STORM 1.0 Mini Excavator', 'TYPHON TERROR 1.8 Mini Excavator'],
                'store_url'        => 'https://typhonmachinery.com/store/',
                'slug_prefix'      => 'typhonmachinery.com/store/',
            ],
            'americancompactors.com' => [
                'default_author'   => 'American Compactors',
                'authors'          => ['American Compactors'],
                'categories'       => ['Heavy Compact Excavators', 'Plate Compactors', 'Rammers & Rollers', 'Soil Compaction', 'Jobsite Equipment'],
                'series'           => ['None (Standalone Article)', 'Heavy Compact Excavator Sizing & Performance Guide'],
                'has_series'       => true,
                'has_store_product'=> true,
                'has_video'        => true,
                'product_models'   => ['American Compactors 2.7-Ton', 'Plate Compactor Pro'],
                'store_url'        => 'https://americancompactors.com/equipment',
                'slug_prefix'      => 'americancompactors.com/blog/',
            ],
            'stonekrusher.com' => [
                'default_author'   => 'StoneKrusher',
                'authors'          => ['StoneKrusher'],
                'categories'       => ['Jaw Crushers & Plants', 'Cone & Impact Crushers', 'Vibrating Screen Decks', 'Quarry & Mining Operations', 'Aggregate Sizing & Crushing Tips', 'Equipment Wear Parts & Maintenance'],
                'series'           => ['None (Standalone Article)', 'Complete Crushing & Screening Plant Operation Guide', 'Quarry Aggregate Optimization'],
                'has_series'       => true,
                'has_store_product'=> true,
                'has_video'        => true,
                'product_models'   => ['StoneKrusher Jaw Plant SK-J100', 'Mobile Cone Crusher SK-C200', 'Heavy Screening Plant SK-S300'],
                'store_url'        => 'https://stonekrusher.com/equipment',
                'slug_prefix'      => 'stonekrusher.com/blog/',
            ],
            'americanloader.com' => [
                'default_author'   => 'American Loader',
                'authors'          => ['American Loader'],
                'categories'       => ['Mini Track Loaders', 'Stand-On Skid Steers', 'Loader Attachment Guide', 'Hydraulic Flow & Auxiliaries', 'Jobsite Clearing & Landscaping'],
                'series'           => ['None (Standalone Article)', 'Stand-on Mini Skid Steer Fleet Guide', 'Hydraulic Flow Optimization'],
                'has_series'       => true,
                'has_store_product'=> true,
                'has_video'        => true,
                'product_models'   => ['American Loader AL-850', 'AL-Track Pro 1000', 'High-Flow Trencher Attachment'],
                'store_url'        => 'https://americanloader.com/catalog',
                'slug_prefix'      => 'americanloader.com/blog/',
            ],
            'skidsteers.us' => [
                'default_author'   => 'SkidSteers',
                'authors'          => ['SkidSteers'],
                'categories'       => ['Compact Track Loaders', 'Wheeled Skid Steers', 'Attachments & Implements', 'Undercarriage & Rubber Tracks', 'Safety & Operator Certification'],
                'series'           => ['None (Standalone Article)', 'Track Loader Maintenance Masterclass', 'Skid Steer Buying Guides'],
                'has_series'       => true,
                'has_store_product'=> true,
                'has_video'        => true,
                'product_models'   => ['Track Loader Rubber Tracks', 'Hydraulic Auger Drive', 'Standard Dirt Bucket'],
                'store_url'        => 'https://skidsteers.us/shop',
                'slug_prefix'      => 'skidsteers.us/blog/',
            ],
            'skidsteers.online' => [
                'default_author'   => 'SkidSteers.com',
                'authors'          => ['SkidSteers.com'],
                'categories'       => ['Compact Track Loaders', 'Wheeled Skid Steers', 'Attachments & Implements', 'Undercarriage & Rubber Tracks'],
                'series'           => ['None (Standalone Article)', 'Track Loader Maintenance Masterclass'],
                'has_series'       => true,
                'has_store_product'=> true,
                'has_video'        => true,
                'product_models'   => ['Track Loader Rubber Tracks', 'Hydraulic Auger Drive'],
                'store_url'        => 'https://skidsteers.online/shop',
                'slug_prefix'      => 'skidsteers.online/blog/',
            ],
            'groundripper.com' => [
                'default_author'   => 'GroundRipper',
                'authors'          => ['GroundRipper'],
                'categories'       => ['Trenchers & Pipe Layers', 'Underground Utility Installation', 'Ground Ripper Teeth & Wear Parts', 'Subsurface Excavation'],
                'series'           => ['None (Standalone Article)', 'Deep Trenching & Pipe Laying Manual'],
                'has_series'       => true,
                'has_store_product'=> true,
                'has_video'        => true,
                'product_models'   => ['GroundRipper GR-50 Utility Trencher', 'Heavy Duty Frost Chain', 'Rock Teeth Set'],
                'store_url'        => 'https://groundripper.com/products',
                'slug_prefix'      => 'groundripper.com/blog/',
            ],
            'heavyequipment.wiki' => [
                'default_author'   => 'Heavy Equipment Wiki',
                'authors'          => ['Heavy Equipment Wiki'],
                'categories'       => ['Excavators & Diggers', 'Loaders & Skid Steers', 'Trenchers & Utilities', 'Hydraulic Engineering Principles', 'Engine & Powertrain Specs', 'Safety Standards & OSHA Guidelines'],
                'series'           => [],
                'has_series'       => false,
                'has_store_product'=> false,
                'has_video'        => true,
                'product_models'   => [],
                'store_url'        => '',
                'slug_prefix'      => 'heavyequipment.wiki/article/',
            ],
            'konstructz.com' => [
                'default_author'   => 'Konstructz',
                'authors'          => ['Konstructz'],
                'categories'       => ['Heavy Compact Machinery', 'Commercial Site Prep', 'Hydraulic Attachments', 'Field Safety Protocols'],
                'series'           => ['None (Standalone Article)', 'KONSTRUCTZ Jobsite Protocols'],
                'has_series'       => true,
                'has_store_product'=> true,
                'has_video'        => true,
                'product_models'   => ['KONSTRUCTZ 2.7 Mini Excavator', 'Site Hauler SH-500'],
                'store_url'        => 'https://konstructz.com/store',
                'slug_prefix'      => 'konstructz.com/blog/',
            ],
            'machineryeu.com' => [
                'default_author'   => 'MachineryEU',
                'authors'          => ['MachineryEU'],
                'categories'       => ['Compact Excavators Europe', 'CE Certification & Standards', 'Euro Stage V Diesels', 'Electric Compact Equipment'],
                'series'           => ['None (Standalone Article)', 'Euro Stage V Machinery Transition'],
                'has_series'       => true,
                'has_store_product'=> true,
                'has_video'        => true,
                'product_models'   => ['Euro Compact 2.0', 'Stage V Digger Pro'],
                'store_url'        => 'https://machineryeu.com/equipment',
                'slug_prefix'      => 'machineryeu.com/blog/',
            ],
            'kuvuo.net' => [
                'default_author'   => 'KUVUO',
                'authors'          => ['KUVUO'],
                'categories'       => ['KUVUO Mini Excavators', 'Kubota Power Systems', 'Inverted Boom Engineering', 'Hydraulic Piston Pumps'],
                'series'           => ['None (Standalone Article)', 'KUVUO Technical Field Manuals'],
                'has_series'       => true,
                'has_store_product'=> true,
                'has_video'        => true,
                'product_models'   => ['KUVUO 4.0 Track Excavator', 'KUVUO 2.7 Inverted Boom'],
                'store_url'        => 'https://kuvuo.net/models',
                'slug_prefix'      => 'kuvuo.net/blog/',
            ],
            'thunderdump.com' => [
                'default_author'   => 'ThunderDump',
                'authors'          => ['ThunderDump'],
                'categories'       => ['Dump Trailers & Dumpers', 'Site Hauling & Earthmoving', 'Hydraulic Hoist Maintenance', 'Fleet Efficiency'],
                'series'           => ['None (Standalone Article)', 'Heavy Dump Trailer Operations'],
                'has_series'       => true,
                'has_store_product'=> true,
                'has_video'        => true,
                'product_models'   => ['ThunderDump 14K Heavy Trailer', 'Tracked Hydraulic Dumper'],
                'store_url'        => 'https://thunderdump.com/inventory',
                'slug_prefix'      => 'thunderdump.com/blog/',
            ],
            'stunningmachinery.com' => [
                'default_author'   => 'Stunning Machinery',
                'authors'          => ['Stunning Machinery'],
                'categories'       => ['Machine Innovations & Releases', 'Operator Walkthroughs', 'Jobsite Machinery Reviews', 'Equipment Buying Guides'],
                'series'           => ['None (Standalone Article)', 'Flagship Heavy Machinery Reviews'],
                'has_series'       => true,
                'has_store_product'=> true,
                'has_video'        => true,
                'product_models'   => ['Featured Heavy Excavator', 'Tracked Loader Premium'],
                'store_url'        => 'https://stunningmachinery.com/showcase',
                'slug_prefix'      => 'stunningmachinery.com/blog/',
            ],
            'machinery.org' => [
                'default_author'   => 'Machinery.org',
                'authors'          => ['Machinery.org'],
                'categories'       => ['Global Equipment Trends', 'Earthmoving Machinery', 'Agricultural Implements', 'Hydraulic Innovations'],
                'series'           => ['None (Standalone Article)', 'Global Equipment Engineering Series'],
                'has_series'       => true,
                'has_store_product'=> false,
                'has_video'        => true,
                'product_models'   => [],
                'store_url'        => '',
                'slug_prefix'      => 'machinery.org/blog/',
            ],
            'asiamachinery.org' => [
                'default_author'   => 'Asia Machinery',
                'authors'          => ['Asia Machinery'],
                'categories'       => ['Equipment Guides', 'Mini Excavators', 'Attachments', 'Maintenance & Care'],
                'series'           => ['None (Standalone Article)'],
                'has_series'       => false,
                'has_store_product'=> false,
                'has_video'        => true,
                'product_models'   => [],
                'store_url'        => 'https://asiamachinery.org/',
                'slug_prefix'      => 'asiamachinery.org/',
            ],
        ];

        // Find match by exact domain or subdomain (prevent naive substring collision)
        $matched = null;
        if (isset($profiles[$domain])) {
            $matched = $profiles[$domain];
        } else {
            foreach ($profiles as $key => $prof) {
                if ($domain === $key || str_ends_with($domain, '.' . $key)) {
                    $matched = $prof;
                    break;
                }
            }
        }

        if (!$matched) {
            $siteCleanName = preg_replace('/\.(com|org|net|eu|io|co|us|online|wiki)$/i', '', $website->name ?: 'Machinery');
            $cleanAuthor = trim($siteCleanName);
            $cat = $website->category ?: 'General';
            $matched = [
                'default_author'   => $cleanAuthor,
                'authors'          => [$cleanAuthor],
                'categories'       => [$cat, 'Equipment Guides', 'Maintenance & Care', 'Jobsite Operations'],
                'series'           => [],
                'has_series'       => false,
                'has_store_product'=> false,
                'has_video'        => true,
                'product_models'   => [],
                'store_url'        => '',
                'slug_prefix'      => $domain . '/blog/',
            ];
        }

        // Each website uses strictly its ONLY ONE dedicated real website author (no handler names, no long lists)
        $cleanAuthor = $matched['default_author'] ?? trim(ucwords(str_replace(['-', '_'], ' ', preg_replace('/\.(com|org|net|eu|io|co|us|online|wiki)$/i', '', $website->name ?: 'Website'))));
        $matched['default_author'] = $cleanAuthor ?: 'Website Team';
        $matched['authors'] = [$matched['default_author']];

        try {

            $existingCats = WebsitePost::where('website_id', $website->id)
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->filter()
                ->values()
                ->all();
            if (!empty($existingCats)) {
                $matched['categories'] = array_values(array_unique(array_merge($matched['categories'], $existingCats)));
            }
        } catch (\Throwable $e) {}

        return array_merge([
            'id'     => $website->id,
            'name'   => $website->name,
            'domain' => $domain,
            'url'    => $website->url,
        ], $matched);
    }
}
