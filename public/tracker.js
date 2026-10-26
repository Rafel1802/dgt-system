/**
 * Central DGT Universal Analytics Tracker
 * Self-healing, framework-agnostic real view beacon for all integrated network websites.
 * Works seamlessly with React, Next.js, Vite, Laravel, WordPress, and Custom PHP.
 */
(function(window, document) {
    'use strict';

    var ENDPOINT = 'https://lightcyan-weasel-711536.hostingersite.com/api/v1/track';
    var PIXEL_ENDPOINT = 'https://lightcyan-weasel-711536.hostingersite.com/api/v1/track.gif';

    function getArticleSlug() {
        // 1. Explicit data attribute on script or article
        var el = document.querySelector('[data-dgt-slug]') || document.querySelector('[data-post-slug]') || document.querySelector('article[data-id]');
        if (el) {
            var s = el.getAttribute('data-dgt-slug') || el.getAttribute('data-post-slug') || el.getAttribute('data-id');
            if (s) return s;
        }

        // 2. OpenGraph or canonical meta
        var canonical = document.querySelector('link[rel="canonical"]');
        var path = canonical ? (new URL(canonical.href, window.location.origin)).pathname : window.location.pathname;

        // 3. Extract slug from URL path (e.g. /blog/my-post -> my-post)
        var segments = path.replace(/^\/+|\/+$/g, '').split('/');
        if (segments.length > 0) {
            return segments[segments.length - 1];
        }
        return '';
    }

    function isBlogPage() {
        var path = window.location.pathname.toLowerCase();
        return path.indexOf('/blog') !== -1 || 
               path.indexOf('/news') !== -1 || 
               path.indexOf('/article') !== -1 || 
               path.indexOf('/post') !== -1 ||
               document.querySelector('article') !== null ||
               document.querySelector('[data-dgt-slug]') !== null;
    }

    function recordView() {
        if (!isBlogPage()) return;

        var slug = getArticleSlug();
        if (!slug || slug === 'blog' || slug === 'news' || slug === 'articles') return;

        var sessionKey = 'dgt_viewed_' + slug;
        try {
            if (window.sessionStorage && window.sessionStorage.getItem(sessionKey)) {
                return; // Already counted this article in this browser session
            }
        } catch (e) {}

        var payload = {
            domain: window.location.hostname.replace(/^www\./i, ''),
            slug: slug,
            url: window.location.href,
            title: document.title || ''
        };

        var sent = false;
        var json = JSON.stringify(payload);

        // Send via navigator.sendBeacon
        if (navigator.sendBeacon) {
            var blob = new Blob([json], { type: 'application/json' });
            sent = navigator.sendBeacon(ENDPOINT, blob);
        }

        // Fallback to fetch
        if (!sent && window.fetch) {
            window.fetch(ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: json,
                keepalive: true,
                mode: 'cors'
            }).catch(function() {
                // Fallback to pixel
                var img = new Image();
                img.src = PIXEL_ENDPOINT + '?domain=' + encodeURIComponent(payload.domain) + '&slug=' + encodeURIComponent(payload.slug);
            });
        }

        try {
            if (window.sessionStorage) {
                window.sessionStorage.setItem(sessionKey, '1');
            }
        } catch (e) {}
    }

    // Trigger on DOMContentLoaded or immediate if already loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', recordView);
    } else {
        setTimeout(recordView, 300);
    }

    // Single-Page Application (SPA) route change listeners (React, Next.js, Vue)
    var lastUrl = window.location.href;
    function checkUrlChange() {
        if (window.location.href !== lastUrl) {
            lastUrl = window.location.href;
            setTimeout(recordView, 500);
        }
    }

    if (window.history && window.history.pushState) {
        var origPushState = window.history.pushState;
        window.history.pushState = function() {
            origPushState.apply(this, arguments);
            checkUrlChange();
        };
        var origReplaceState = window.history.replaceState;
        window.history.replaceState = function() {
            origReplaceState.apply(this, arguments);
            checkUrlChange();
        };
        window.addEventListener('popstate', checkUrlChange);
    }

})(window, document);
