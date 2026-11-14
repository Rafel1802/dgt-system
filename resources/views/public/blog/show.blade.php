@php
    $theme = $theme ?? app(\App\Services\WebsiteBlogPostService::class)->resolveWebsiteTheme($website ?? null, $post ?? null, $siteUrl ?? ($post?->canonical_url ?? ''));
    $primaryColor = $theme['primary'] ?? '#ea580c';
    $primaryDarkColor = $theme['primary_dark'] ?? '#c2410c';
    $primaryLightColor = $theme['primary_light'] ?? '#fff7ed';
    $linkColor = $theme['link_color'] ?? $primaryColor;
    $linkHoverColor = $theme['link_hover'] ?? $primaryDarkColor;
@endphp
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $post->meta_title ?: $post->title }} - {{ $siteName }}</title>
    <meta name="description" content="{{ $post->meta_description ?: $post->excerpt }}">
    @if(!empty($post->tag_keywords))
    <meta name="keywords" content="{{ $post->tag_keywords }}">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $post->meta_title ?: $post->title }}">
    <meta property="og:description" content="{{ $post->meta_description ?: $post->excerpt }}">
    @if($post->featured_image_url)
    <meta property="og:image" content="{{ $post->featured_image_url }}">
    @endif
    <meta property="og:site_name" content="{{ $siteName }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="{{ $post->meta_title ?: $post->title }}">
    <meta property="twitter:description" content="{{ $post->meta_description ?: $post->excerpt }}">
    @if($post->featured_image_url)
    <meta property="twitter:image" content="{{ $post->featured_image_url }}">
    @endif

    <!-- Google Fonts & Tailwind CDN for 100% standalone zero-dependency instant rendering -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                        serif: ['"Newsreader"', 'Georgia', 'serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    }
                }
            }
        }
    </script>

    <style>
        /* Reading Progress Bar */
        #reading-progress {
            position: fixed;
            top: 0;
            left: 0;
            height: 3.5px;
            background: linear-gradient(90deg, {{ $primaryColor }}, {{ $linkHoverColor }});
            z-index: 100;
            width: 0%;
            transition: width 0.1s ease-out;
        }

        /* Article Typography & Formatting */
        .article-content {
            font-size: 1.125rem;
            line-height: 1.85;
            color: #1e293b;
        }
        .dark .article-content {
            color: #cbd5e1;
        }
        .article-content p {
            margin-bottom: 1.65rem;
        }
        /* Hyperlink typography & contrast - strictly adheres to website brand theme */
        .article-content a,
        .article-content .cms-article-link {
            color: {{ $linkColor }};
            text-decoration: underline;
            text-underline-offset: 3px;
            font-weight: 600;
            transition: color 0.15s ease-in-out, text-decoration-color 0.15s ease-in-out;
        }
        .article-content a:hover,
        .article-content .cms-article-link:hover {
            color: {{ $linkHoverColor }};
            text-decoration: underline;
        }
        .dark .article-content a,
        .dark .article-content .cms-article-link {
            color: {{ $linkColor }};
        }
        .dark .article-content a:hover,
        .dark .article-content .cms-article-link:hover {
            color: {{ $linkHoverColor }};
        }
        .article-content h2 {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-top: 2.75rem;
            margin-bottom: 1.15rem;
            color: #0f172a;
            line-height: 1.3;
        }
        .dark .article-content h2 {
            color: #f8fafc;
        }
        .article-content h3 {
            font-size: 1.35rem;
            font-weight: 700;
            margin-top: 2rem;
            margin-bottom: 0.85rem;
            color: #1e293b;
            line-height: 1.35;
        }
        .dark .article-content h3 {
            color: #f1f5f9;
        }
        .article-content ul, .article-content ol {
            margin-bottom: 1.65rem;
            padding-left: 1.75rem;
        }
        .article-content ul { list-style-type: disc; }
        .article-content ol { list-style-type: decimal; }
        .article-content li {
            margin-bottom: 0.5rem;
        }
        .article-content blockquote {
            border-left: 4px solid #6366f1;
            padding-left: 1.25rem;
            font-style: italic;
            margin: 2rem 0;
            color: #475569;
            background: rgba(99, 102, 241, 0.04);
            padding: 1rem 1.25rem;
            border-radius: 0 0.75rem 0.75rem 0;
        }
        .dark .article-content blockquote {
            color: #94a3b8;
            background: rgba(99, 102, 241, 0.1);
        }
        /* Tables in content */
        .article-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
            font-size: 0.95rem;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .article-content th, .article-content td {
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        .dark .article-content th, .dark .article-content td {
            border-color: #334155;
        }
        .article-content th {
            background: #f8fafc;
            font-weight: 700;
            color: #0f172a;
        }
        .dark .article-content th {
            background: #1e293b;
            color: #f8fafc;
        }
        .article-content tr:nth-child(even) td {
            background: #fafafa;
        }
        .dark .article-content tr:nth-child(even) td {
            background: rgba(255,255,255,0.02);
        }
        /* Responsive Images */
        .article-content img {
            max-width: 100%;
            height: auto;
            border-radius: 0.85rem;
            margin: 2rem auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            display: block;
        }
        .article-content figure {
            margin: 2.25rem 0;
        }
        .article-content figcaption {
            font-size: 0.85rem;
            text-align: center;
            color: #64748b;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body class="bg-[#fcfdfd] dark:bg-[#0b0f17] text-slate-900 dark:text-slate-100 antialiased font-sans transition-colors duration-200">

    <div id="reading-progress"></div>

    @if(!empty($isPreview))
    <!-- ── Staging Preview Notification Banner (10-Minute Expiring Link) ── -->
    <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-sky-600 text-white text-xs font-bold py-2.5 px-4 shadow-lg sticky top-0 z-50 border-b border-white/15">
        <div class="max-w-4xl mx-auto flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white/20 text-white text-xs font-black">👁</span>
                <span class="tracking-tight"><strong>LIVE BLOG PREVIEW:</strong> Previewing article for <u>{{ $siteName }}</u>.</span>
            </div>
            <div class="flex items-center flex-wrap gap-2">
                <!-- 10-Minute Countdown Badge -->
                <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-black/35 border border-white/20 text-white font-mono text-[11px] shadow-inner">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="opacity-90">Expires in:</span>
                    <span id="preview-countdown" class="font-extrabold text-amber-300">10:00</span>
                </div>

                @if(!empty($previewShareUrl))
                <!-- Copy 10m Share Link Button -->
                <button type="button" onclick="copyPreviewLink()" id="btn-copy-preview"
                        class="px-2.5 py-1 rounded-lg bg-white/20 hover:bg-white/30 text-white text-[11px] font-bold transition flex items-center gap-1.5 cursor-pointer shadow-sm">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                    <span id="btn-copy-preview-text">Copy 10m Link</span>
                </button>
                @endif

                <button type="button" onclick="window.close()" class="px-3 py-1 rounded-lg bg-white text-slate-900 hover:bg-slate-100 text-[11px] font-black transition-all cursor-pointer shadow-sm">
                    Close Preview ✕
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- ── Top Header Navigation Bar ── -->
    <header class="sticky top-0 z-40 bg-white/90 dark:bg-[#0b0f17]/90 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ $siteUrl }}" class="flex items-center gap-2.5 group">
                    @if($website && $website->logo_path)
                        <img src="{{ $website->logo_path }}" alt="{{ $siteName }}" class="h-8 w-auto max-w-[130px] object-contain">
                    @else
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center text-white font-black text-sm shadow-md" style="background: {{ $primaryColor }};">
                            {{ substr($siteName, 0, 1) }}
                        </div>
                    @endif
                    <div class="flex flex-col">
                        <span class="font-extrabold text-sm tracking-tight text-slate-900 dark:text-white transition" onmouseover="this.style.color='{{ $primaryColor }}'" onmouseout="this.style.color=''">{{ $siteName }}</span>
                        @if($website && $website->category)
                        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">{{ $website->category }}</span>
                        @endif
                    </div>
                </a>
            </div>

            <div class="flex items-center gap-2.5">
                <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 text-xs font-bold border border-emerald-200 dark:border-emerald-800">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Live Article
                </span>

                <!-- Dark Mode Toggle Button -->
                <button type="button" onclick="toggleDarkMode()" class="p-2 rounded-xl text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition" title="Toggle Dark/Light Mode">
                    <svg id="theme-sun" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <svg id="theme-moon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                </button>

                <!-- Share Link Button -->
                <button type="button" onclick="copyShareUrl()" class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-slate-100 dark:hover:bg-white text-white dark:text-slate-900 text-xs font-bold transition shadow-sm flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    <span>Share</span>
                </button>
            </div>
        </div>
    </header>

    <!-- ── Main Article Container ── -->
    <main class="max-w-3xl mx-auto px-4 sm:px-6 py-8 sm:py-12">

        <!-- Category & Breadcrumb -->
        <div class="flex items-center gap-2 mb-4 text-xs font-bold text-slate-500">
            <a href="{{ $siteUrl }}" class="transition" onmouseover="this.style.color='{{ $primaryColor }}'" onmouseout="this.style.color=''">{{ $siteName }}</a>
            <span>/</span>
            <span class="px-2.5 py-0.5 rounded-md font-extrabold uppercase tracking-wider text-[11px]" style="background: {{ $primaryLightColor }}; color: {{ $primaryColor }};">
                {{ $post->category ?: 'Editorial Insights' }}
            </span>
        </div>

        <!-- Article Headline (H1) -->
        <h1 class="text-3xl sm:text-4xl lg:text-[2.75rem] font-extrabold text-slate-950 dark:text-white tracking-tight leading-[1.2] mb-6 font-serif">
            {{ $post->title }}
        </h1>

        <!-- Article Excerpt / Subheading -->
        @if(!empty($post->excerpt))
        <p class="text-lg sm:text-xl text-slate-600 dark:text-slate-300 font-serif italic leading-relaxed mb-8 border-l-2 border-slate-300 dark:border-slate-700 pl-4">
            {{ $post->excerpt }}
        </p>
        @endif

        <!-- Author, Date & Reading Metrics -->
        <div class="flex flex-wrap items-center justify-between gap-4 py-4 border-y border-slate-200 dark:border-slate-800/80 mb-8 text-xs text-slate-500 dark:text-slate-400">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-slate-200 to-slate-300 dark:from-slate-700 dark:to-slate-800 flex items-center justify-center font-bold text-sm text-slate-700 dark:text-slate-200 ring-2 ring-white dark:ring-slate-900 shadow-sm">
                    {{ substr($authorName, 0, 1) }}
                </div>
                <div>
                    <div class="font-extrabold text-sm text-slate-900 dark:text-white">{{ $authorName }}</div>
                    <div class="text-[11px] text-slate-400">
                        {{ $post->published_at ? $post->published_at->format('F d, Y') : date('F d, Y') }} · {{ $readingMinutes }} min read ({{ number_format($wordCount) }} words)
                    </div>
                </div>
            </div>

            @if(!empty($post->playlist) && $post->playlist !== 'None (Standalone Article)')
            <div class="px-3 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 font-bold text-xs border border-indigo-200/60 dark:border-indigo-800/60 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <span>{{ $post->playlist }}</span>
            </div>
            @endif
        </div>

        <!-- Featured Cover Image -->
        @if(!empty($post->featured_image_url))
        <figure class="mb-10">
            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="w-full h-auto max-h-[520px] object-cover rounded-2xl shadow-lg ring-1 ring-slate-900/5">
            @if(!empty($post->cover_alt))
            <figcaption class="text-center text-xs text-slate-400 mt-2 font-medium italic">
                {{ $post->cover_alt }}
            </figcaption>
            @endif
        </figure>
        @endif

        <!-- Main Formatted Article Content -->
        <article class="article-content prose prose-slate dark:prose-invert max-w-none">
            {!! $contentHtml !!}
        </article>

        <!-- Tag Keywords Section -->
        @if(!empty($post->tag_keywords))
        <div class="mt-12 pt-6 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mr-1">Topics:</span>
            @foreach(explode(',', $post->tag_keywords) as $tag)
                @if(trim($tag))
                <span class="px-3 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                    #{{ trim($tag) }}
                </span>
                @endif
            @endforeach
        </div>
        @endif

        <!-- ── Author Bio Box ── -->
        <div class="mt-12 p-6 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center sm:items-start gap-4">
            <div class="w-14 h-14 rounded-full flex items-center justify-center text-white font-extrabold text-xl shadow-md shrink-0" style="background: {{ $primaryColor }};">
                {{ substr($authorName, 0, 1) }}
            </div>
            <div class="space-y-1 text-center sm:text-left">
                <h4 class="font-extrabold text-base text-slate-900 dark:text-white">{{ $authorName }}</h4>
                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                    Official editorial team contributor for {{ $siteName }}. Covering technical specifications, commercial equipment operations, fleet safety, and heavy machinery maintenance insights.
                </p>
                @if($website && $website->url)
                <div class="pt-2">
                    <a href="{{ $website->url }}" target="_blank" rel="noopener noreferrer" class="text-xs font-bold hover:underline inline-flex items-center gap-1" style="color: {{ $linkColor }};">
                        Visit {{ $siteName }} ↗
                    </a>
                </div>
                @endif
            </div>
        </div>

        <!-- ── Related Articles ── -->
        @if($relatedPosts->isNotEmpty())
        <div class="mt-14 pt-8 border-t border-slate-200 dark:border-slate-800">
            <h3 class="text-xl font-extrabold text-slate-900 dark:text-white mb-6">More from {{ $siteName }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                @foreach($relatedPosts as $rel)
                <a href="{{ route('public.blog.show', ['domain' => $website ? $website->clean_domain : 'site', 'slug' => $rel->slug]) }}" class="group block p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-blue-500 hover:shadow-md transition">
                    @if($rel->featured_image_url)
                    <img src="{{ $rel->featured_image_url }}" alt="{{ $rel->title }}" class="w-full h-32 object-cover rounded-lg mb-3">
                    @endif
                    <h5 class="font-bold text-xs line-clamp-2 text-slate-900 dark:text-white group-hover:text-blue-600 transition leading-snug">
                        {{ $rel->title }}
                    </h5>
                    <div class="text-[10px] text-slate-400 mt-2 font-medium">
                        {{ $rel->published_at ? $rel->published_at->format('M d, Y') : 'Published' }}
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif

    </main>

    <!-- ── Public Footer ── -->
    <footer class="mt-16 border-t border-slate-200 dark:border-slate-800/80 py-8 text-center text-xs text-slate-400">
        <p>© {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
        <p class="mt-1 text-[11px] text-slate-400">Published via DGT Central Fleet Publishing Engine</p>
    </footer>

    <!-- Toast Notification Container -->
    <div id="toast" class="fixed bottom-6 right-6 z-50 hidden px-4 py-2.5 rounded-xl bg-slate-900 text-white font-bold text-xs shadow-2xl items-center gap-2 border border-slate-700 animate-in"></div>

    <script>
        // Reading Progress Indicator
        window.addEventListener('scroll', () => {
            const h = document.documentElement,
                  b = document.body,
                  st = 'scrollTop',
                  sh = 'scrollHeight';
            const percent = (h[st]||b[st]) / ((h[sh]||b[sh]) - h.clientHeight) * 100;
            document.getElementById('reading-progress').style.width = percent + '%';
        });

        // Dark Mode Logic
        function initTheme() {
            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
                document.getElementById('theme-sun').classList.remove('hidden');
                document.getElementById('theme-moon').classList.add('hidden');
            } else {
                document.documentElement.classList.remove('dark');
                document.getElementById('theme-sun').classList.add('hidden');
                document.getElementById('theme-moon').classList.remove('hidden');
            }
        }
        function toggleDarkMode() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                document.getElementById('theme-sun').classList.add('hidden');
                document.getElementById('theme-moon').classList.remove('hidden');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                document.getElementById('theme-sun').classList.remove('hidden');
                document.getElementById('theme-moon').classList.add('hidden');
            }
        }
        initTheme();

        // Share URL Copy
        function copyShareUrl() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                const toast = document.getElementById('toast');
                toast.innerText = '✓ Article link copied to clipboard!';
                toast.classList.remove('hidden');
                toast.classList.add('flex');
                setTimeout(() => {
                    toast.classList.add('hidden');
                    toast.classList.remove('flex');
                }, 3000);
            });
        }

        @if(!empty($isPreview) && !empty($previewExpiresAt))
        // ── 10-Minute Expiring Preview Timer ──
        const previewExpiresAtMs = {{ (int) $previewExpiresAt }} * 1000;
        function updatePreviewCountdown() {
            const now = Date.now();
            const remaining = Math.max(0, Math.floor((previewExpiresAtMs - now) / 1000));
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            const el = document.getElementById('preview-countdown');
            if (el) {
                el.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                if (remaining <= 60) {
                    el.classList.remove('text-amber-300');
                    el.classList.add('text-red-300', 'animate-pulse');
                }
            }
            if (remaining <= 0) {
                clearInterval(previewTimerInterval);
                if (el) el.textContent = 'Expired';
                setTimeout(() => { window.location.reload(); }, 2000);
            }
        }
        const previewTimerInterval = setInterval(updatePreviewCountdown, 1000);
        updatePreviewCountdown();

        function copyPreviewLink() {
            const url = "{{ $previewShareUrl ?? '' }}";
            if (!url) return;
            navigator.clipboard.writeText(url).then(() => {
                const txt = document.getElementById('btn-copy-preview-text');
                if (txt) {
                    const old = txt.textContent;
                    txt.textContent = '✓ Copied!';
                    setTimeout(() => { txt.textContent = old; }, 2000);
                }
            }).catch(() => {
                prompt('Copy 10-minute preview link:', url);
            });
        }
        @endif
    </script>
</body>
</html>
