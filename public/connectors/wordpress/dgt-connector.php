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
    const OPTION_DGT_URL    = 'dgt_connector_platform_url';
    const OPTION_API_KEY    = 'dgt_connector_api_key';
    const OPTION_API_SECRET = 'dgt_connector_api_secret';
    const OPTION_AUTO_SYNC  = 'dgt_connector_auto_sync';
    const OPTION_LAST_SYNC  = 'dgt_connector_last_sync';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('dgt_connector_cron_sync', [$this, 'perform_background_sync']);
        add_action('template_redirect', [$this, 'track_single_post_view']);
        add_action('wp_footer', [$this, 'render_tracking_script']);

        // Schedule cron if enabled
        if (!wp_next_scheduled('dgt_connector_cron_sync')) {
            wp_schedule_event(time(), 'hourly', 'dgt_connector_cron_sync');
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
            'methods'             => 'GET',
            'callback'            => [$this, 'rest_get_posts'],
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

        $expectedSignature = hash_hmac('sha256', $timestamp . '.', $savedSecret);
        if (!hash_equals($expectedSignature, $signature)) {
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
     * Health check endpoint.
     */
    public function rest_get_health() {
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
}

new DGT_Website_Connector();

} // end if (!class_exists)
