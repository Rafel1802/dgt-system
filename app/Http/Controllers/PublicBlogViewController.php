<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\WebsitePost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicBlogViewController extends Controller
{
    /**
     * Display a published blog post publicly by website domain and post slug.
     */
    public function showByDomainSlug(string $domain, string $slug): View
    {
        $cleanDomain = strtolower(preg_replace('/^www\./i', '', trim($domain)));

        // Locate website by URL or name
        $website = Website::where(function ($q) use ($cleanDomain) {
            $q->where('url', 'like', "%{$cleanDomain}%")
              ->orWhere('name', 'like', "%{$cleanDomain}%");
        })->first();

        $postQuery = WebsitePost::where('slug', $slug);
        if ($website) {
            $postQuery->where('website_id', $website->id);
        }

        $post = $postQuery->latest('id')->first();

        // If not found by domain constraint, fallback to finding by slug across any site
        if (!$post) {
            $post = WebsitePost::where('slug', $slug)->latest('id')->firstOrFail();
            $website = $post->website;
        }

        return $this->renderArticleView($post, $website);
    }

    /**
     * Display a published blog post publicly by ID.
     */
    public function showById(WebsitePost $post): View
    {
        $website = $post->website;
        return $this->renderArticleView($post, $website);
    }

    /**
     * Render the high-end, responsive public article view.
     */
    protected function renderArticleView(WebsitePost $post, ?Website $website): View
    {
        $cmsExtra = $post->metrics_data['cms_extra'] ?? [];
        $contentHtml = $post->content_html ?: ($cmsExtra['content_html'] ?? '');

        if (empty($contentHtml)) {
            $contentHtml = nl2br(e($post->raw_content ?: $post->excerpt ?: 'No content available for this article.'));
        } else {
            // Deduplicate: Ensure the first image is not duplicated between featured cover and body
            if (empty($post->featured_image_url)) {
                $leadingImg = $this->extractLeadingImage($contentHtml);
                if ($leadingImg) {
                    $post->featured_image_url = $leadingImg;
                    $contentHtml = $this->removeLeadingDuplicateImage($contentHtml, $leadingImg);
                }
            } else {
                $contentHtml = $this->removeLeadingDuplicateImage($contentHtml, $post->featured_image_url);
            }
        }

        $siteName = $website ? ($website->name ?: $website->clean_domain) : ($post->author_name ?: 'DGT Media');
        $siteUrl  = $website ? $website->url : url('/');
        $authorName = $post->author_name ?: $siteName;

        // Calculate approximate reading time
        $wordCount = str_word_count(strip_tags($contentHtml));
        $readingMinutes = max(1, (int) ceil($wordCount / 200));

        // Related articles from the same website
        $relatedPosts = WebsitePost::where('website_id', $post->website_id)
            ->where('id', '!=', $post->id)
            ->whereIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public'])
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('public.blog.show', compact(
            'post',
            'website',
            'contentHtml',
            'siteName',
            'siteUrl',
            'authorName',
            'readingMinutes',
            'wordCount',
            'relatedPosts'
        ));
    }

    /**
     * Public JSON feed for external embedding or headless frontends.
     */
    public function apiPosts(string $domain, Request $request): JsonResponse
    {
        $cleanDomain = strtolower(preg_replace('/^www\./i', '', trim($domain)));
        $website = Website::where('url', 'like', "%{$cleanDomain}%")
            ->orWhere('name', 'like', "%{$cleanDomain}%")
            ->first();

        if (!$website) {
            return response()->json(['success' => false, 'message' => 'Website not found'], 404);
        }

        $posts = WebsitePost::where('website_id', $website->id)
            ->whereIn('status', [WebsitePost::STATUS_PUBLISHED, 'publish', 'public'])
            ->latest('published_at')
            ->paginate((int) $request->get('limit', 20));

        return response()->json([
            'success'     => true,
            'website'     => [
                'name'     => $website->name,
                'url'      => $website->url,
                'category' => $website->category,
            ],
            'total'       => $posts->total(),
            'posts'       => $posts->items(),
        ]);
    }

    /**
     * Remove duplicate hero image from the beginning of the article content.
     */
    protected function removeLeadingDuplicateImage(string $html, ?string $featuredImageUrl): string
    {
        if (empty($featuredImageUrl) || trim($html) === '') {
            return $html;
        }

        $featuredPath = parse_url($featuredImageUrl, PHP_URL_PATH) ?: $featuredImageUrl;
        $featuredFile = strtolower(basename($featuredPath));

        // Strip leading empty paragraphs / breaks
        $html = preg_replace('/^\s*(?:<p>\s*(?:&nbsp;|\s)*<\/p>|<br\s*\/?>|\s)+/i', '', $html);

        // 1. Remove leading <figure> or <div><figure> containing the featured image
        $patternFigure = '/^\s*(?:<div[^>]*>\s*)?<figure[^>]*>.*?<img[^>]+src=["\']([^"\']+)["\'].*?<\/figure>(?:\s*<\/div>)?/is';
        if (preg_match($patternFigure, $html, $matches)) {
            $matchedFile = strtolower(basename(parse_url($matches[1], PHP_URL_PATH) ?: $matches[1]));
            if ($matchedFile === $featuredFile || str_contains($matches[1], $featuredFile) || str_contains($featuredImageUrl, $matchedFile)) {
                $html = preg_replace($patternFigure, '', $html, 1);
                return trim($html);
            }
        }

        // 2. Remove leading <p><img ...></p>
        $patternP = '/^\s*<p[^>]*>\s*<img[^>]+src=["\']([^"\']+)["\'][^>]*>\s*<\/p>/is';
        if (preg_match($patternP, $html, $matches)) {
            $matchedFile = strtolower(basename(parse_url($matches[1], PHP_URL_PATH) ?: $matches[1]));
            if ($matchedFile === $featuredFile || str_contains($matches[1], $featuredFile) || str_contains($featuredImageUrl, $matchedFile)) {
                $html = preg_replace($patternP, '', $html, 1);
                return trim($html);
            }
        }

        // 3. Remove leading standalone <img ...>
        $patternImg = '/^\s*<img[^>]+src=["\']([^"\']+)["\'][^>]*>/is';
        if (preg_match($patternImg, $html, $matches)) {
            $matchedFile = strtolower(basename(parse_url($matches[1], PHP_URL_PATH) ?: $matches[1]));
            if ($matchedFile === $featuredFile || str_contains($matches[1], $featuredFile) || str_contains($featuredImageUrl, $matchedFile)) {
                $html = preg_replace($patternImg, '', $html, 1);
                return trim($html);
            }
        }

        // 4. Fallback: Search anywhere within the first 2500 chars for a figure/p/img with this exact image filename
        if (strlen($featuredFile) > 3) {
            $prefix = substr($html, 0, 2500);
            $patternAny = '/<(?:figure|p)[^>]*>.*?<img[^>]+src=["\'][^"\']*' . preg_quote($featuredFile, '/') . '[^"\']*["\'].*?<\/(?:figure|p)>/is';
            if (preg_match($patternAny, $prefix)) {
                $prefix = preg_replace($patternAny, '', $prefix, 1);
                $html = $prefix . substr($html, 2500);
            }
        }

        return trim($html);
    }

    /**
     * Extract the first image src from the content if present.
     */
    protected function extractLeadingImage(string $html): ?string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', substr($html, 0, 2500), $m)) {
            return $m[1];
        }
        return null;
    }
}
