<?php
// Script to retroactively format, style, and re-sync all existing blog posts
// Ensures every hyperlink has high-contrast royal blue styling (#2563eb) and underline

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(App\Services\WebsiteBlogPostService::class);
$posts = App\Models\WebsitePost::orderBy('id', 'desc')->get();

echo "Scanning " . $posts->count() . " blog posts across fleet...\n";
$fixedCount = 0;
$linksEnhanced = 0;
$remoteSyncedCount = 0;

foreach ($posts as $post) {
    $website = $post->website;
    $siteUrl = rtrim($website?->url ?: '', '/');
    $siteDomain = strtolower(parse_url($siteUrl, PHP_URL_HOST) ?: ($website?->name ?: ''));
    $siteDomain = preg_replace('/^www\./i', '', $siteDomain);

    $originalHtml = $post->content_html ?: '';
    if (empty($originalHtml) && !empty($post->raw_content)) {
        $originalHtml = $service->formatMarkdownContent($post->raw_content);
    }

    if (empty($originalHtml)) {
        continue;
    }

    $styled = $originalHtml;

    // 1. Unescape any escaped HTML links (&lt;a href=&quot;...&quot;&gt;)
    $styled = preg_replace('/&lt;a\s+href=(&quot;|\')([^&]+)(&quot;|\')&gt;([\s\S]*?)&lt;\/a&gt;/i', '<a href="$2">$4</a>', $styled);

    // 2. Convert unparsed markdown links [text](url) in HTML into styled <a> tags
    $styled = preg_replace_callback('/\[([^\]]+)\]\(((?:https?:\/\/|\/|#|mailto:|tel:)[^\s\)]+)\)/i', function ($m) use ($siteDomain) {
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
        return "<a href=\"{$url}\"{$targetAttr} class=\"cms-article-link text-blue-600 dark:text-blue-400 font-semibold underline hover:text-blue-800\" style=\"color: #2563eb; text-decoration: underline; font-weight: 600;\">{$text}</a>";
    }, $styled);

    // 3. Restore any links present in raw_content that were stripped during plain-text paste
    if (!empty($post->raw_content) && preg_match_all('/\[([^\]]+)\]\(((?:https?:\/\/|\/|#)[^\s\)]+)\)/i', $post->raw_content, $rm, PREG_SET_ORDER)) {
        foreach ($rm as $match) {
            $linkText = trim($match[1]);
            $linkUrl = trim($match[2]);
            if (empty($linkText) || empty($linkUrl) || strlen($linkText) < 2) continue;

            if (!str_contains($styled, $linkUrl)) {
                $escapedText = preg_quote($linkText, '/');
                $pattern = '/(?<!href=["\'])(?<!>)(?<!\[)\b(' . $escapedText . ')\b(?![^<]*<\/a>)(?!["\'])/i';
                if (preg_match($pattern, $styled)) {
                    $linkHost = strtolower(parse_url($linkUrl, PHP_URL_HOST) ?: '');
                    $linkHost = preg_replace('/^www\./i', '', $linkHost);
                    $isExternal = (str_starts_with($linkUrl, 'http') && ($siteDomain === '' || (!str_contains($linkHost, $siteDomain) && !str_contains($siteDomain, $linkHost))));
                    $targetAttr = $isExternal ? ' target="_blank" rel="noopener noreferrer"' : '';
                    $replacement = "<a href=\"{$linkUrl}\"{$targetAttr} class=\"cms-article-link text-blue-600 dark:text-blue-400 font-semibold underline hover:text-blue-800\" style=\"color: #2563eb; text-decoration: underline; font-weight: 600;\">$1</a>";
                    $styled = preg_replace($pattern, $replacement, $styled, 1);
                }
            }
        }
    }

    // 4. Ensure all existing <a> tags have proper class, high-visibility inline styling, and external target
    $styled = preg_replace_callback('/<a(\s+[^>]*)href=["\']([^"\']+)["\']([^>]*)>([\s\S]*?)<\/a>/i', function ($m) use ($siteDomain) {
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
            $attrs = preg_replace('/\s*target=["\']_blank["\']/i', '', $attrs);
            $attrs = preg_replace('/\s*rel=["\']noopener noreferrer["\']/i', '', $attrs);
        }

        if (!str_contains($attrs, 'class=')) {
            $attrs .= ' class="cms-article-link text-blue-600 dark:text-blue-400 font-semibold underline hover:text-blue-800"';
        } elseif (!str_contains($attrs, 'cms-article-link')) {
            $attrs = preg_replace('/class=["\']([^"\']*)["\']/i', 'class="$1 cms-article-link text-blue-600 dark:text-blue-400 font-semibold underline hover:text-blue-800"', $attrs);
        }

        if (!str_contains($attrs, 'style=')) {
            $attrs .= ' style="color: #2563eb; text-decoration: underline; font-weight: 600;"';
        } elseif (!preg_match('/color\s*:/i', $attrs)) {
            $attrs = preg_replace('/style=["\']([^"\']*)["\']/i', 'style="$1; color: #2563eb; text-decoration: underline; font-weight: 600;"', $attrs);
        }

        $cleanAttrs = trim(preg_replace('/\s+/', ' ', $attrs));
        return "<a href=\"{$href}\"" . ($cleanAttrs !== '' ? ' ' . $cleanAttrs : '') . ">{$text}</a>";
    }, $styled);

    // Check if content changed
    if ($styled !== $originalHtml) {
        $post->updateQuietly(['content_html' => $styled]);
        $fixedCount++;

        preg_match_all('/<a[^>]+>/i', $styled, $matches);
        $linksEnhanced += count($matches[0]);

        // If post is published, re-dispatch to destination site
        if ($website && in_array($post->status, ['published', 'publish', 'public'])) {
            try {
                $dispatchRes = $service->dispatchPostToRemoteWebsite($website, $post);
                if (!empty($dispatchRes['synced'])) {
                    $remoteSyncedCount++;
                }
            } catch (\Throwable $e) {
                // Non-fatal
            }
        }
    }
}

echo "Completed! Repaired and enhanced {$fixedCount} posts with {$linksEnhanced} styled hyperlinks. Re-synced {$remoteSyncedCount} remote published posts.\n";
