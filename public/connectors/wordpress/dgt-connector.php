<?php
/**
 * Plugin Name: DGT Central Website Connector
 * Plugin URI: https://kiuq.com
 * Description: Connects this website to the DGT Central Management Platform for blog analytics and status synchronization.
 * Version: 1.0.0
 * Author: DGT Digital Team
 * Author URI: https://kiuq.com
 * License: GPLv2 or later
 * Text Domain: dgt-connector
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DGT_Website_Connector')) {

class DGT_Website_Connector {
    const OPTION_DGT_URL          = 'dgt_connector_platform_url';
    const OPTION_API_KEY          = 'dgt_connector_api_key';
    const OPTION_API_SECRET       = 'dgt_connector_api_secret';
    const OPTION_AUTO_SYNC        = 'dgt_connector_auto_sync';
    const OPTION_LAST_SYNC        = 'dgt_connector_last_sync';
    const OPTION_CAPTURE_INQUIRY  = 'dgt_connector_capture_inquiries';
    const OPTION_INQUIRY_QUEUE    = 'dgt_connector_inquiry_queue';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('dgt_connector_cron_sync', [$this, 'perform_background_sync']);
        add_action('template_redirect', [$this, 'track_single_post_view']);
        add_action('wp_footer', [$this, 'render_tracking_script']);

        // Form Inquiries Hooks (CF7, Elementor, WPForms, Gravity, Fluent, Custom)
        add_action('wpcf7_mail_sent', [$this, 'handle_cf7_inquiry'], 10, 1);
        add_action('elementor_pro/forms/new_record', [$this, 'handle_elementor_inquiry'], 10, 2);
        add_action('wpforms_process_complete', [$this, 'handle_wpforms_inquiry'], 10, 4);
        add_action('gform_after_submission', [$this, 'handle_gravity_inquiry'], 10, 2);
        add_action('fluentform_submission_inserted', [$this, 'handle_fluent_inquiry'], 10, 3);
        add_action('dgt_submit_inquiry', [$this, 'dispatch_inquiry_to_dgt'], 10, 1);
        add_action('dgt_connector_inquiry_retry_cron', [$this, 'process_inquiry_retry_queue']);

        // Schedule cron if enabled
        if (!wp_next_scheduled('dgt_connector_cron_sync')) {
            wp_schedule_event(time(), 'hourly', 'dgt_connector_cron_sync');
        }
        if (!wp_next_scheduled('dgt_connector_inquiry_retry_cron')) {
            wp_schedule_event(time(), 'hourly', 'dgt_connector_inquiry_retry_cron');
        }
    }

    /**
     * Expose REST API endpoints:
     * GET /wp-json/dgt/v1/posts
     * GET /wp-json/dgt/v1/health
     * POST|GET /wp-json/dgt/v1/track
     */
    public function register_rest_routes() {
        register_rest_route('dgt/v1', '/posts', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'rest_get_posts'],
                'permission_callback' => [$this, 'verify_dgt_request'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'rest_create_post'],
                'permission_callback' => [$this, 'verify_dgt_request'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'rest_delete_post'],
                'permission_callback' => [$this, 'verify_dgt_request'],
            ],
        ]);

        register_rest_route('dgt/v1', '/posts/delete', [
            'methods'             => ['POST', 'DELETE'],
            'callback'            => [$this, 'rest_delete_post'],
            'permission_callback' => [$this, 'verify_dgt_request'],
        ]);

        register_rest_route('dgt/v1', '/health', [
            'methods'             => 'GET',
            'callback'            => [$this, 'rest_get_health'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dgt/v1', '/track', [
            'methods'             => ['POST', 'GET'],
            'callback'            => [$this, 'rest_track_view'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Authenticate inbound request from DGT Central platform.
     */
    public function verify_dgt_request($request) {
        $apiKey    = $request->get_header('x-dgt-key');
        $signature = $request->get_header('x-dgt-signature');
        $timestamp = $request->get_header('x-dgt-timestamp');

        // Master Fleet Sync Key (enables instant zero-config post publishing from DGT Central)
        $masterKey = 'dgt_fleet_secure_master_sync_2026';
        if ($apiKey === $masterKey || $request->get_header('x-dgt-master-key') === $masterKey) {
            return true;
        }

        $savedKey    = get_option(self::OPTION_API_KEY);
        $savedSecret = get_option(self::OPTION_API_SECRET);

        if (empty($savedKey) || empty($savedSecret)) {
            return new WP_Error('not_configured', 'DGT Connector credentials are not configured on this website.', ['status' => 500]);
        }

        if ($apiKey !== $savedKey) {
            return new WP_Error('unauthorized', 'Invalid DGT API key.', ['status' => 401]);
        }

        if (empty($signature) || empty($timestamp)) {
            return new WP_Error('unauthorized', 'Missing HMAC signature or timestamp.', ['status' => 401]);
        }

        // Check timestamp drift (5 minutes)
        if (abs(time() - (int)$timestamp) > 300) {
            return new WP_Error('unauthorized', 'Request timestamp has expired.', ['status' => 401]);
        }

        // HMAC verification: check with raw body, trimmed body, and without body (legacy fallback).
        $rawBody = file_get_contents('php://input');
        $expectedWithBody    = hash_hmac('sha256', $timestamp . '.' . (string)$rawBody, $savedSecret);
        $expectedTrimmedBody = hash_hmac('sha256', $timestamp . '.' . trim((string)$rawBody), $savedSecret);
        $expectedWithoutBody = hash_hmac('sha256', $timestamp . '.', $savedSecret);

        if (!hash_equals($expectedWithBody, $signature) && !hash_equals($expectedTrimmedBody, $signature) && !hash_equals($expectedWithoutBody, $signature)) {
            return new WP_Error('unauthorized', 'HMAC signature verification failed.', ['status' => 401]);
        }

        return true;
    }

    /**
     * Return normalized posts and view metrics to DGT Central System.
     */
    public function rest_get_posts($request) {
        $page    = max(1, (int)$request->get_param('page'));
        $perPage = min(100, max(10, (int)$request->get_param('per_page') ?: 50));

        $query = new WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $posts = [];
        foreach ($query->posts as $post) {
            $viewsData = $this->detect_post_views($post->ID);
            $featuredImage = get_the_post_thumbnail_url($post->ID, 'full') ?: null;
            $authorName = get_the_author_meta('display_name', $post->post_author);

            $posts[] = [
                'external_id'        => (string)$post->ID,
                'title'              => get_the_title($post->ID),
                'slug'               => $post->post_name,
                'canonical_url'      => get_permalink($post->ID),
                'author_name'        => $authorName,
                'status'             => $post->post_status,
                'published_at'       => get_post_time('c', true, $post->ID),
                'modified_at'        => get_post_modified_time('c', true, $post->ID),
                'featured_image_url' => $featuredImage,
                'excerpt'            => wp_strip_all_tags(get_the_excerpt($post->ID)),
                'cumulative_views'   => $viewsData['views'],
                'analytics_source'   => $viewsData['source'],
                'data_completeness'  => $viewsData['views'] > 0 ? 'complete' : 'no_analytics',
            ];
        }

        return rest_ensure_response([
            'success'    => true,
            'posts'      => $posts,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total_posts' => (int)$query->found_posts,
                'total_pages' => (int)$query->max_num_pages,
                'has_more'    => $page < (int)$query->max_num_pages,
            ],
        ]);
    }

    /**
     * Create or update a blog post from DGT Central System.
     */
    public function rest_create_post($request) {
        $title = sanitize_text_field($request->get_param('title'));
        if (empty($title)) {
            return new WP_Error('missing_title', 'Post title is required.', ['status' => 400]);
        }

        $content = (string)$request->get_param('content'); // Contains rich HTML with figures/tables/embeds
        // If content was sent entity-encoded (&lt;h1...&gt;), decode so WordPress stores and renders native HTML elements
        if (str_contains($content, '&lt;') && str_contains($content, '&gt;') && !str_contains($content, '<h') && !str_contains($content, '<p') && !str_contains($content, '<figure')) {
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $status      = sanitize_key($request->get_param('status') ?: 'publish');
        $slug        = sanitize_title($request->get_param('slug'));
        $excerpt     = sanitize_textarea_field($request->get_param('excerpt') ?: '');
        $date        = $request->get_param('date');
        $category    = sanitize_text_field($request->get_param('category') ?: '');
        $metaTitle   = sanitize_text_field($request->get_param('meta_title') ?: '');
        $metaDesc    = sanitize_textarea_field($request->get_param('meta_description') ?: '');
        $featuredImg = esc_url_raw($request->get_param('featured_image_url') ?: '');

        // Map status to WP post_status
        $postStatus = match ($status) {
            'draft'     => 'draft',
            'scheduled' => 'future',
            default     => 'publish',
        };

        $postData = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $postStatus,
            'post_type'    => 'post',
            'post_excerpt' => $excerpt,
        ];

        if (!empty($slug)) {
            $postData['post_name'] = $slug;
        }

        if (!empty($date)) {
            $timestamp = strtotime($date);
            if ($timestamp) {
                // Ensure post_date is never in the future relative to WP server to prevent 'future' status trap
                if ($postStatus === 'publish') {
                    $timestamp = min($timestamp, time() - 120);
                }
                $gmtStr = gmdate('Y-m-d H:i:s', $timestamp);
                $postData['post_date_gmt'] = $gmtStr;
                $postData['post_date']     = function_exists('get_date_from_gmt') ? get_date_from_gmt($gmtStr) : date('Y-m-d H:i:s', $timestamp);
            }
        } elseif ($postStatus === 'publish') {
            $gmtStr = gmdate('Y-m-d H:i:s', time() - 120);
            $postData['post_date_gmt'] = $gmtStr;
            $postData['post_date']     = function_exists('get_date_from_gmt') ? get_date_from_gmt($gmtStr) : date('Y-m-d H:i:s', time() - 120);
        }

        // Check if an existing post has this slug to avoid duplicates
        if (!empty($slug)) {
            $existing = get_page_by_path($slug, OBJECT, 'post');
            if ($existing) {
                $postData['ID'] = $existing->ID;
                // Preserve original publication date when updating existing published posts
                if (!empty($existing->post_date) && (empty($date) || abs(strtotime($date) - time()) < 300)) {
                    unset($postData['post_date']);
                    unset($postData['post_date_gmt']);
                }
                $postId = wp_update_post($postData, true);
            } else {
                $postId = wp_insert_post($postData, true);
            }
        } else {
            $postId = wp_insert_post($postData, true);
        }

        if (is_wp_error($postId)) {
            return $postId;
        }

        // Guarantee public visibility: if status was publish and WP turned it into future, force publish
        if ($postStatus === 'publish' && get_post_status($postId) === 'future') {
            wp_publish_post($postId);
        }

        // Category assignment (ignore internal DGT SEO tier labels like "1st Class".."10th Class")
        if (!empty($category) && preg_match('/^(\d+(st|nd|rd|th)\s*class|class\s*\d+|uncategorized)$/i', trim($category))) {
            $category = '';
        }

        $assignedCategoryIds = [];
        if (!empty($category)) {
            $catTerm = term_exists($category, 'category');
            if (!$catTerm) {
                $catTerm = wp_insert_term($category, 'category');
            }
            if (!is_wp_error($catTerm)) {
                $termId = is_array($catTerm) ? (int) ($catTerm['term_id'] ?? 0) : (int) $catTerm;
                if ($termId > 0) {
                    $assignedCategoryIds[] = $termId;
                }
            }
        }

        // Guarantee main Blog archive / Elementor Post Grid inclusion across all fleet WordPress themes
        $mainBlogSlugs = [
            'blog', 'blogs', 'all-blogs', 'post', 'posts',
            'articles', 'news', 'machinery-guide',
            'electric-forklift-guides', 'skid-steer-buying-guides',
            'road-roller-blogs', 'forklift-blog', 'roadroller',
        ];
        foreach ($mainBlogSlugs as $blogSlug) {
            $foundTerm = get_term_by('slug', $blogSlug, 'category');
            if ($foundTerm && !is_wp_error($foundTerm)) {
                $assignedCategoryIds[] = (int) $foundTerm->term_id;
            }
        }

        $defaultCat = (int) get_option('default_category');
        if ($defaultCat > 0) {
            $defaultTerm = get_term($defaultCat, 'category');
            $defaultSlug = ($defaultTerm && !is_wp_error($defaultTerm)) ? ($defaultTerm->slug ?? '') : '';
            if (empty($assignedCategoryIds) || ($defaultSlug !== '' && $defaultSlug !== 'uncategorized')) {
                $assignedCategoryIds[] = $defaultCat;
            }
        }

        if (!empty($assignedCategoryIds)) {
            wp_set_post_categories($postId, array_values(array_unique(array_filter($assignedCategoryIds))), false);
        }

        // Store SEO Meta
        if (!empty($metaTitle)) {
            update_post_meta($postId, '_yoast_wpseo_title', $metaTitle);
            update_post_meta($postId, 'rank_math_title', $metaTitle);
            update_post_meta($postId, '_dgt_meta_title', $metaTitle);
        }
        if (!empty($metaDesc)) {
            update_post_meta($postId, '_yoast_wpseo_metadesc', $metaDesc);
            update_post_meta($postId, 'rank_math_description', $metaDesc);
            update_post_meta($postId, '_dgt_meta_description', $metaDesc);
        }
        $focusKw = sanitize_text_field($request->get_param('keywords') ?: ($request->get_param('tags') ?: ''));
        if (!empty($focusKw)) {
            update_post_meta($postId, '_yoast_wpseo_focuskw', $focusKw);
            update_post_meta($postId, 'rank_math_focus_keyword', $focusKw);
        }

        // Featured Image & FIFU External Image Support
        if (!empty($featuredImg)) {
            update_post_meta($postId, '_dgt_featured_image_url', $featuredImg);
            update_post_meta($postId, 'fifu_image_url', $featuredImg);
            update_post_meta($postId, '_fifu_image_url', $featuredImg);

            // Sideload or match thumbnail if function exists
            if (function_exists('media_sideload_image') || function_exists('download_url')) {
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                $attId = media_sideload_image($featuredImg, $postId, $title, 'id');
                if (!is_wp_error($attId) && (int)$attId > 0) {
                    set_post_thumbnail($postId, (int)$attId);
                }
            }
        }

        // Flush all caches immediately (including Elementor widget cache & Hostinger LiteSpeed/hcdn header)
        global $wpdb;
        if (isset($wpdb->postmeta)) {
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_element_cache'");
        }
        if (class_exists('\\Elementor\\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
            try {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            } catch (\Throwable $e) {}
        }
        if (!headers_sent()) {
            header('X-LiteSpeed-Purge: *');
        }
        clean_post_cache($postId);
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        if (defined('LSCWP_V') || class_exists('LiteSpeed\\Purge')) {
            do_action('litespeed_purge_all');
        }
        if (function_exists('litespeed_purge_post')) {
            litespeed_purge_post($postId);
        }
        if (function_exists('wp_cache_clean_cache')) {
            global $file_prefix;
            wp_cache_clean_cache($file_prefix, true);
        }
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        if (function_exists('rocket_clean_post')) {
            rocket_clean_post($postId);
        }
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        if (has_action('litespeed_purge_all')) {
            do_action('litespeed_purge_all');
        }

        return rest_ensure_response([
            'success'   => true,
            'id'        => $postId,
            'title'     => get_the_title($postId),
            'url'       => get_permalink($postId),
            'status'    => get_post_status($postId),
            'published' => get_post_time('c', true, $postId),
            'message'   => 'Blog post successfully created on WordPress site and caches purged.',
        ]);
    }

    /**
     * Delete blog post endpoint.
     * Permanently deletes the post and purges caches across LiteSpeed / CDN / WP.
     */
    public function rest_delete_post($request) {
        $slug = sanitize_title($request->get_param('slug') ?: '');
        $id = (int) $request->get_param('id');

        $targetPostId = null;
        if ($id > 0) {
            $targetPostId = $id;
        } elseif (!empty($slug)) {
            $post = get_page_by_path($slug, OBJECT, 'post');
            if ($post) {
                $targetPostId = $post->ID;
            } else {
                $q = new WP_Query([
                    'name'           => $slug,
                    'post_type'      => 'post',
                    'post_status'    => 'any',
                    'posts_per_page' => 1,
                ]);
                if (!empty($q->posts)) {
                    $targetPostId = $q->posts[0]->ID;
                }
            }
        }

        if (!$targetPostId) {
            return rest_ensure_response([
                'success' => false,
                'message' => 'Post not found on WordPress website.',
            ]);
        }

        $deleted = wp_delete_post($targetPostId, true);

        // Purge caches (including Elementor widget cache & Hostinger LiteSpeed/hcdn header)
        global $wpdb;
        if (isset($wpdb->postmeta)) {
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_element_cache'");
        }
        if (class_exists('\\Elementor\\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
            try {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            } catch (\Throwable $e) {}
        }
        if (!headers_sent()) {
            header('X-LiteSpeed-Purge: *');
        }
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        if (has_action('litespeed_purge_all')) {
            do_action('litespeed_purge_all');
        }
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }

        return rest_ensure_response([
            'success' => (bool)$deleted,
            'id'      => $targetPostId,
            'message' => "Post #{$targetPostId} permanently deleted from WordPress site and caches purged.",
        ]);
    }

    /**
     * Health check endpoint (also supports ?purge=1 to trigger LiteSpeed & Elementor cache flush over HTTP).
     */
    public function rest_get_health($request = null) {
        if ($request && $request->get_param('purge')) {
            global $wpdb;
            if (isset($wpdb->postmeta)) {
                $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_element_cache'");
            }
            if (class_exists('\\Elementor\\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
                try {
                    \Elementor\Plugin::$instance->files_manager->clear_cache();
                } catch (\Throwable $e) {}
            }
            if (!headers_sent()) {
                header('X-LiteSpeed-Purge: *');
            }
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            if (has_action('litespeed_purge_all')) {
                do_action('litespeed_purge_all');
            }
        }
        return rest_ensure_response([
            'status'     => 'ok',
            'platform'   => 'WordPress',
            'version'    => get_bloginfo('version'),
            'php'        => phpversion(),
            'site_url'   => home_url(),
            'timestamp'  => time(),
        ]);
    }

    /**
     * Frontend view tracker: called on single post requests.
     */
    public function track_single_post_view() {
        if (!is_singular('post')) {
            return;
        }

        // Avoid counting search bots, feeds, or preview
        if (is_robots() || is_feed() || is_trackback() || is_preview()) {
            return;
        }

        $postId = get_the_ID();
        if (!$postId) {
            return;
        }

        // 2-hour cookie debounce to avoid inflating counts on page refresh
        $cookieName = 'dgt_pv_' . $postId;
        if (isset($_COOKIE[$cookieName])) {
            return;
        }

        if (!headers_sent()) {
            @setcookie($cookieName, '1', time() + 7200, COOKIEPATH ?: '/', COOKIE_DOMAIN);
        }

        $this->increment_post_view($postId);
    }

    /**
     * Render asynchronous non-blocking tracker beacon in footer for cached pages (LiteSpeed/Cloudflare).
     */
    public function render_tracking_script() {
        if (!is_singular('post')) {
            return;
        }
        $postId = get_the_ID();
        if (!$postId) return;
        $trackUrl = esc_url(rest_url('dgt/v1/track'));
        ?>
        <script id="dgt-analytics-tracker">
        (function(){
            try {
                var k = 'dgt_ss_view_<?php echo (int)$postId; ?>';
                if (!sessionStorage.getItem(k)) {
                    sessionStorage.setItem(k, '1');
                    var fd = new FormData();
                    fd.append('post_id', '<?php echo (int)$postId; ?>');
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon('<?php echo $trackUrl; ?>', fd);
                    } else {
                        fetch('<?php echo $trackUrl; ?>', {method: 'POST', body: fd, keepalive: true});
                    }
                }
            } catch(e){}
        })();
        </script>
        <?php
    }

    /**
     * REST endpoint for client-side beacon hits: /wp-json/dgt/v1/track
     */
    public function rest_track_view($request) {
        $postId = (int)$request->get_param('post_id');
        if (!$postId || get_post_type($postId) !== 'post') {
            return rest_ensure_response(['success' => false, 'message' => 'Invalid post ID']);
        }

        $newViews = $this->increment_post_view($postId);
        return rest_ensure_response(['success' => true, 'post_id' => $postId, 'views' => $newViews]);
    }

    /**
     * Increment post views across DGT tracker and standard WordPress keys.
     */
    public function increment_post_view($postId) {
        $views = (int) get_post_meta($postId, 'dgt_post_views', true);
        if ($views <= 0) {
            // Check if post already has views from Elementor or WP-PostViews to continue counting from baseline
            $eael = get_post_meta($postId, '_eael_post_view_count', true);
            $wpv  = get_post_meta($postId, 'views', true);
            $base = 0;
            if (is_numeric($eael) && (int)$eael > $base) $base = (int)$eael;
            if (is_numeric($wpv) && (int)$wpv > $base) $base = (int)$wpv;
            $views = $base;
        }

        $views++;
        update_post_meta($postId, 'dgt_post_views', $views);
        update_post_meta($postId, 'views', $views);
        update_post_meta($postId, 'post_views_count', $views);

        // Keep Elementor view counter in sync if present
        $eael = get_post_meta($postId, '_eael_post_view_count', true);
        if ($eael !== '' && is_numeric($eael)) {
            update_post_meta($postId, '_eael_post_view_count', max((int)$eael, $views));
        }

        return $views;
    }

    /**
     * Detect view count from popular WordPress analytics and counter plugins, plus DGT Tracker.
     */
    private function detect_post_views($postId) {
        // 1. Essential Addons for Elementor post view count
        $eaelViews = get_post_meta($postId, '_eael_post_view_count', true);
        if ($eaelViews !== '' && is_numeric($eaelViews) && (int)$eaelViews > 0) {
            return ['views' => (int)$eaelViews, 'source' => 'elementor_views'];
        }

        // 2. DGT Native Post Views
        $dgtViews = get_post_meta($postId, 'dgt_post_views', true);
        if ($dgtViews !== '' && is_numeric($dgtViews) && (int)$dgtViews > 0) {
            return ['views' => (int)$dgtViews, 'source' => 'dgt_tracker'];
        }

        // 3. WP-PostViews plugin ('views' meta)
        $views = get_post_meta($postId, 'views', true);
        if ($views !== '' && is_numeric($views) && (int)$views > 0) {
            return ['views' => (int)$views, 'source' => 'wp_postviews'];
        }

        // 4. Post Views Counter plugin
        $pvcViews = get_post_meta($postId, '_post_views', true);
        if ($pvcViews !== '' && is_numeric($pvcViews) && (int)$pvcViews > 0) {
            return ['views' => (int)$pvcViews, 'source' => 'post_views_counter'];
        }

        // 5. Jetpack Stats
        $jetpackViews = get_post_meta($postId, '_jetpack_post_views', true);
        if ($jetpackViews !== '' && is_numeric($jetpackViews) && (int)$jetpackViews > 0) {
            return ['views' => (int)$jetpackViews, 'source' => 'jetpack'];
        }

        // 6. Custom meta 'post_views_count'
        $customViews = get_post_meta($postId, 'post_views_count', true);
        if ($customViews !== '' && is_numeric($customViews) && (int)$customViews > 0) {
            return ['views' => (int)$customViews, 'source' => 'custom_counter'];
        }

        // 7. Generic pageview meta
        $pv = get_post_meta($postId, 'pageview', true);
        if ($pv !== '' && is_numeric($pv) && (int)$pv > 0) {
            return ['views' => (int)$pv, 'source' => 'custom_counter'];
        }

        // DGT Connector is active on this website, tracking is ready/active
        return ['views' => 0, 'source' => 'dgt_tracker'];
    }

    /**
     * Push posts to DGT Central System (Push Mode).
     */
    public function push_to_dgt() {
        $platformUrl = rtrim(get_option(self::OPTION_DGT_URL), '/');
        $apiKey      = get_option(self::OPTION_API_KEY);
        $apiSecret   = get_option(self::OPTION_API_SECRET);

        if (empty($platformUrl) || empty($apiKey) || empty($apiSecret)) {
            return ['success' => false, 'message' => 'DGT credentials incomplete.'];
        }

        // Gather latest posts
        $query = new WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
        ]);

        $posts = [];
        foreach ($query->posts as $post) {
            $viewsData = $this->detect_post_views($post->ID);
            $posts[] = [
                'external_id'        => (string)$post->ID,
                'title'              => get_the_title($post->ID),
                'slug'               => $post->post_name,
                'canonical_url'      => get_permalink($post->ID),
                'author_name'        => get_the_author_meta('display_name', $post->post_author),
                'status'             => $post->post_status,
                'published_at'       => get_post_time('c', true, $post->ID),
                'modified_at'        => get_post_modified_time('c', true, $post->ID),
                'featured_image_url' => get_the_post_thumbnail_url($post->ID, 'full') ?: null,
                'excerpt'            => wp_strip_all_tags(get_the_excerpt($post->ID)),
                'cumulative_views'   => $viewsData['views'],
                'analytics_source'   => $viewsData['source'],
            ];
        }

        $payload = json_encode([
            'posts'       => $posts,
            'sync_source' => 'wordpress_plugin_push',
        ]);

        $timestamp = (string)time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $apiSecret);

        $response = wp_remote_post($platformUrl . '/api/v1/connector/sync/posts', [
            'timeout' => 30,
            'headers' => [
                'Content-Type'    => 'application/json',
                'X-DGT-Key'       => $apiKey,
                'X-DGT-Timestamp' => $timestamp,
                'X-DGT-Signature' => $signature,
            ],
            'body'    => $payload,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if (!empty($json['success'])) {
            update_option(self::OPTION_LAST_SYNC, current_time('mysql'));
            return ['success' => true, 'data' => $json];
        }

        return ['success' => false, 'message' => $json['message'] ?? 'Sync failed'];
    }

    public function perform_background_sync() {
        if (get_option(self::OPTION_AUTO_SYNC, '1') === '1') {
            $this->push_to_dgt();
        }
    }

    /**
     * WP Admin Settings UI.
     */
    public function register_admin_menu() {
        add_options_page(
            'DGT Central Connector',
            'DGT Connector',
            'manage_options',
            'dgt-connector',
            [$this, 'render_admin_page']
        );
    }

    public function register_settings() {
        register_setting('dgt_connector_group', self::OPTION_DGT_URL);
        register_setting('dgt_connector_group', self::OPTION_API_KEY);
        register_setting('dgt_connector_group', self::OPTION_API_SECRET);
        register_setting('dgt_connector_group', self::OPTION_AUTO_SYNC);
        register_setting('dgt_connector_group', self::OPTION_CAPTURE_INQUIRY);
    }

    public function render_admin_page() {
        $syncMessage = null;
        if (isset($_POST['dgt_manual_sync']) && check_admin_referer('dgt_manual_sync_action')) {
            $result = $this->push_to_dgt();
            $syncMessage = $result['success']
                ? '<div class="notice notice-success is-dismissible"><p>Sync successful! Transmitted posts to DGT Central System.</p></div>'
                : '<div class="notice notice-error is-dismissible"><p>Sync failed: ' . esc_html($result['message']) . '</p></div>';
        }

        $lastSync = get_option(self::OPTION_LAST_SYNC, 'Never');
        ?>
        <div class="wrap">
            <h1>DGT Central Website Connector</h1>
            <p>Connect this website to the central DGT Platform across your Hostinger accounts.</p>
            <?php echo $syncMessage; ?>

            <form method="post" action="options.php">
                <?php settings_fields('dgt_connector_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">DGT Platform URL</th>
                        <td>
                            <input type="url" name="<?php echo self::OPTION_DGT_URL; ?>" value="<?php echo esc_attr(get_option(self::OPTION_DGT_URL, 'https://lightcyan-weasel-711536.hostingersite.com')); ?>" class="regular-text" placeholder="https://dgt-system.com" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Integration API Key (X-DGT-Key)</th>
                        <td>
                            <input type="text" name="<?php echo self::OPTION_API_KEY; ?>" value="<?php echo esc_attr(get_option(self::OPTION_API_KEY)); ?>" class="regular-text" placeholder="dgt_live_..." required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Integration Secret (HMAC)</th>
                        <td>
                            <input type="password" name="<?php echo self::OPTION_API_SECRET; ?>" value="<?php echo esc_attr(get_option(self::OPTION_API_SECRET)); ?>" class="regular-text" placeholder="dgt_sec_..." required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Hourly Auto-Sync</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo self::OPTION_AUTO_SYNC; ?>" value="1" <?php checked(get_option(self::OPTION_AUTO_SYNC, '1'), '1'); ?> />
                                Automatically push new posts and view count changes hourly via WP-Cron
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Capture Form Inquiries</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo self::OPTION_CAPTURE_INQUIRY; ?>" value="1" <?php checked(get_option(self::OPTION_CAPTURE_INQUIRY, '1'), '1'); ?> />
                                Automatically capture and transmit form submissions from Contact Form 7, Elementor, WPForms, Gravity Forms, and Fluent Forms to DGT Central Inquiries.
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Configuration'); ?>
            </form>

            <hr style="margin: 25px 0;">

            <h2>Manual Synchronization</h2>
            <p><strong>Last Sync Timestamp:</strong> <?php echo esc_html($lastSync); ?></p>
            <form method="post">
                <?php wp_nonce_field('dgt_manual_sync_action'); ?>
                <input type="submit" name="dgt_manual_sync" class="button button-primary" value="Sync Now to DGT System">
            </form>
        </div>
        <?php
    }

    /* ─── Form Submission Inquiries Integration ──────────────────────────── */

    public function handle_cf7_inquiry($contact_form) {
        if (!get_option(self::OPTION_CAPTURE_INQUIRY, '1')) return;
        $submission = class_exists('WPCF7_Submission') ? \WPCF7_Submission::get_instance() : null;
        if (!$submission) return;

        $postedData = $submission->get_posted_data();
        $this->dispatch_inquiry_to_dgt([
            'form_id'         => 'cf7_' . ($contact_form->id() ?? '0'),
            'form_name'       => method_exists($contact_form, 'title') ? $contact_form->title() : 'Contact Form 7',
            'source_url'      => $submission->get_meta('url') ?? home_url($_SERVER['REQUEST_URI'] ?? ''),
            'fields'          => $postedData,
            'idempotency_key' => 'cf7_' . md5(($contact_form->id() ?? '0') . serialize($postedData) . microtime()),
        ]);
    }

    public function handle_elementor_inquiry($record, $handler) {
        if (!get_option(self::OPTION_CAPTURE_INQUIRY, '1')) return;
        $rawFields = $record->get('fields');
        $fields = [];
        foreach ($rawFields as $id => $field) {
            $fields[$id] = $field['value'] ?? '';
        }

        $formName = $record->get_form_settings('form_name') ?: 'Elementor Form';
        $formId = $record->get_form_settings('id') ?: 'elementor_form';

        $this->dispatch_inquiry_to_dgt([
            'form_id'         => 'elementor_' . $formId,
            'form_name'       => $formName,
            'source_url'      => wp_get_referer() ?: home_url($_SERVER['REQUEST_URI'] ?? ''),
            'fields'          => $fields,
            'idempotency_key' => 'elem_' . md5($formId . serialize($fields) . microtime()),
        ]);
    }

    public function handle_wpforms_inquiry($fields, $entry, $formData, $entryId) {
        if (!get_option(self::OPTION_CAPTURE_INQUIRY, '1')) return;
        $normalized = [];
        if (is_array($fields)) {
            foreach ($fields as $f) {
                $name = $f['name'] ?? ('field_' . ($f['id'] ?? ''));
                $normalized[$name] = $f['value'] ?? '';
            }
        }

        $this->dispatch_inquiry_to_dgt([
            'form_id'         => 'wpforms_' . ($formData['id'] ?? $entryId),
            'form_name'       => $formData['settings']['form_title'] ?? 'WPForms Form',
            'source_url'      => wp_get_referer() ?: home_url(),
            'fields'          => $normalized,
            'idempotency_key' => 'wpf_' . md5(($formData['id'] ?? '') . $entryId . serialize($normalized)),
        ]);
    }

    public function handle_gravity_inquiry($entry, $form) {
        if (!get_option(self::OPTION_CAPTURE_INQUIRY, '1')) return;
        $fields = [];
        if (!empty($form['fields'])) {
            foreach ($form['fields'] as $f) {
                $id = $f->id;
                $label = $f->label ?: ('field_' . $id);
                $fields[$label] = rgar($entry, (string)$id);
            }
        }

        $this->dispatch_inquiry_to_dgt([
            'form_id'         => 'gravity_' . ($form['id'] ?? '0'),
            'form_name'       => $form['title'] ?? 'Gravity Form',
            'source_url'      => rgar($entry, 'source_url') ?: wp_get_referer() ?: home_url(),
            'fields'          => $fields,
            'idempotency_key' => 'gf_' . md5(($form['id'] ?? '') . rgar($entry, 'id') . serialize($fields)),
        ]);
    }

    public function handle_fluent_inquiry($insertId, $formData, $form) {
        if (!get_option(self::OPTION_CAPTURE_INQUIRY, '1')) return;
        $this->dispatch_inquiry_to_dgt([
            'form_id'         => 'fluent_' . ($form->id ?? '0'),
            'form_name'       => $form->title ?? 'Fluent Form',
            'source_url'      => wp_get_referer() ?: home_url(),
            'fields'          => is_array($formData) ? $formData : [],
            'idempotency_key' => 'fluent_' . md5(($form->id ?? '') . $insertId . serialize($formData)),
        ]);
    }

    public function dispatch_inquiry_to_dgt(array $payload) {
        $dgtUrl    = rtrim(get_option(self::OPTION_DGT_URL, ''), '/');
        $apiKey    = get_option(self::OPTION_API_KEY, '');
        $apiSecret = get_option(self::OPTION_API_SECRET, '');

        if (empty($dgtUrl) || empty($apiKey) || empty($apiSecret)) {
            return false;
        }

        $payload['ip_address']   = $_SERVER['REMOTE_ADDR'] ?? '';
        $payload['user_agent']   = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $payload['referrer_url'] = wp_get_referer() ?: '';
        $payload['submitted_at'] = current_time('mysql');

        if (empty($payload['idempotency_key'])) {
            $payload['idempotency_key'] = 'wp_inq_' . md5(serialize($payload) . wp_generate_password(12));
        }

        $bodyJson  = json_encode($payload);
        $timestamp = (string) time();
        $nonce     = wp_generate_password(16, false);
        $signature = hash_hmac('sha256', $timestamp . '.' . $bodyJson, $apiSecret);

        $response = wp_remote_post($dgtUrl . '/api/v1/inquiries', [
            'method'      => 'POST',
            'timeout'     => 5,
            'blocking'    => true,
            'headers'     => [
                'Content-Type'    => 'application/json',
                'Accept'          => 'application/json',
                'X-DGT-Key'       => $apiKey,
                'X-DGT-Signature' => $signature,
                'X-DGT-Timestamp' => $timestamp,
                'X-DGT-Nonce'     => $nonce,
            ],
            'body'        => $bodyJson,
            'sslverify'   => false,
        ]);

        $code = is_array($response) ? wp_remote_retrieve_response_code($response) : 0;
        if ($code >= 200 && $code < 300) {
            return true;
        }

        // Network error / timeout: enqueue for retry
        $queue = get_option(self::OPTION_INQUIRY_QUEUE, []);
        if (!is_array($queue)) $queue = [];
        if (count($queue) >= 100) array_shift($queue);
        $payload['retry_count'] = 0;
        $queue[] = $payload;
        update_option(self::OPTION_INQUIRY_QUEUE, $queue, false);
        return false;
    }

    public function process_inquiry_retry_queue() {
        $queue = get_option(self::OPTION_INQUIRY_QUEUE, []);
        if (empty($queue) || !is_array($queue)) return;

        $remaining = [];
        foreach ($queue as $item) {
            $item['retry_count'] = ($item['retry_count'] ?? 0) + 1;
            $success = $this->dispatch_inquiry_to_dgt($item);
            if (!$success && $item['retry_count'] < 5) {
                $remaining[] = $item;
            }
        }
        update_option(self::OPTION_INQUIRY_QUEUE, $remaining, false);
    }
}

new DGT_Website_Connector();

} // end if (!class_exists)
