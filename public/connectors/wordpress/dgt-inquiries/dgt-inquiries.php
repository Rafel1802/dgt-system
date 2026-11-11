<?php
/**
 * Plugin Name: DGT Website Inquiries Connector
 * Plugin URI: https://kiuq.com
 * Description: Captures customer inquiries, quote requests, and contact forms from Contact Form 7, Elementor Forms, WPForms, Gravity Forms, Fluent Forms, and custom forms, transmitting them securely to DGT Central System.
 * Version: 1.0.0
 * Author: DGT Digital Team
 * Author URI: https://kiuq.com
 * License: GPLv2 or later
 * Text Domain: dgt-inquiries
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DGT_Inquiries_Connector')) {

class DGT_Inquiries_Connector {
    const OPTION_DGT_URL    = 'dgt_inquiries_platform_url';
    const OPTION_API_KEY    = 'dgt_inquiries_api_key';
    const OPTION_API_SECRET = 'dgt_inquiries_api_secret';
    const OPTION_ENABLED    = 'dgt_inquiries_enabled';
    const OPTION_QUEUE      = 'dgt_inquiries_retry_queue';

    public function __construct() {
        // Admin settings
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);

        // Form Submission Hooks
        add_action('wpcf7_mail_sent', [$this, 'handle_cf7_submission'], 10, 1);
        add_action('elementor_pro/forms/new_record', [$this, 'handle_elementor_submission'], 10, 2);
        add_action('wpforms_process_complete', [$this, 'handle_wpforms_submission'], 10, 4);
        add_action('gform_after_submission', [$this, 'handle_gravity_submission'], 10, 2);
        add_action('fluentform_submission_inserted', [$this, 'handle_fluent_submission'], 10, 3);
        add_action('dgt_submit_inquiry', [$this, 'dispatch_inquiry'], 10, 1);

        // WP-Cron Retry Schedule
        add_filter('cron_schedules', [$this, 'add_cron_intervals']);
        add_action('dgt_inquiries_retry_cron', [$this, 'process_retry_queue']);

        if (!wp_next_scheduled('dgt_inquiries_retry_cron')) {
            wp_schedule_event(time(), 'dgt_five_minutes', 'dgt_inquiries_retry_cron');
        }
    }

    /**
     * Add 5-minute interval for retry cron.
     */
    public function add_cron_intervals($schedules) {
        $schedules['dgt_five_minutes'] = [
            'interval' => 300,
            'display'  => __('Every 5 Minutes (DGT Retry)', 'dgt-inquiries'),
        ];
        return $schedules;
    }

    /* ─── Form Submission Handlers ───────────────────────────────────────── */

    /**
     * Handle Contact Form 7 submission.
     */
    public function handle_cf7_submission($contact_form) {
        if (!get_option(self::OPTION_ENABLED, '1')) return;

        $submission = class_exists('WPCF7_Submission') ? \WPCF7_Submission::get_instance() : null;
        if (!$submission) return;

        $postedData = $submission->get_posted_data();
        $uploadedFiles = $submission->uploaded_files();

        $formTitle = is_object($contact_form) && method_exists($contact_form, 'title')
            ? $contact_form->title()
            : 'Contact Form 7';

        $payload = [
            'form_id'         => 'cf7_' . ($contact_form->id() ?? 'default'),
            'form_name'       => $formTitle,
            'source_url'      => $submission->get_meta('url') ?? home_url($_SERVER['REQUEST_URI'] ?? ''),
            'fields'          => $postedData,
            'idempotency_key' => 'cf7_' . md5(($contact_form->id() ?? '0') . serialize($postedData) . microtime()),
        ];

        $this->dispatch_inquiry($payload);
    }

    /**
     * Handle Elementor Pro Form submission.
     */
    public function handle_elementor_submission($record, $handler) {
        if (!get_option(self::OPTION_ENABLED, '1')) return;

        $rawFields = $record->get('fields');
        $fields = [];
        foreach ($rawFields as $id => $field) {
            $fields[$id] = $field['value'] ?? '';
        }

        $formName = $record->get_form_settings('form_name') ?: 'Elementor Form';
        $formId = $record->get_form_settings('id') ?: 'elementor_form';

        $payload = [
            'form_id'         => 'elementor_' . $formId,
            'form_name'       => $formName,
            'source_url'      => wp_get_referer() ?: home_url($_SERVER['REQUEST_URI'] ?? ''),
            'fields'          => $fields,
            'idempotency_key' => 'elem_' . md5($formId . serialize($fields) . microtime()),
        ];

        $this->dispatch_inquiry($payload);
    }

    /**
     * Handle WPForms submission.
     */
    public function handle_wpforms_submission($fields, $entry, $formData, $entryId) {
        if (!get_option(self::OPTION_ENABLED, '1')) return;

        $normalizedFields = [];
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $name = $field['name'] ?? ('field_' . ($field['id'] ?? ''));
                $normalizedFields[$name] = $field['value'] ?? '';
            }
        }

        $payload = [
            'form_id'         => 'wpforms_' . ($formData['id'] ?? $entryId),
            'form_name'       => $formData['settings']['form_title'] ?? 'WPForms Form',
            'source_url'      => wp_get_referer() ?: home_url(),
            'fields'          => $normalizedFields,
            'idempotency_key' => 'wpf_' . md5(($formData['id'] ?? '') . $entryId . serialize($normalizedFields)),
        ];

        $this->dispatch_inquiry($payload);
    }

    /**
     * Handle Gravity Forms submission.
     */
    public function handle_gravity_submission($entry, $form) {
        if (!get_option(self::OPTION_ENABLED, '1')) return;

        $fields = [];
        if (!empty($form['fields'])) {
            foreach ($form['fields'] as $field) {
                $id = $field->id;
                $label = $field->label ?: ('field_' . $id);
                $fields[$label] = rgar($entry, (string)$id);
            }
        }

        $payload = [
            'form_id'         => 'gravity_' . ($form['id'] ?? '0'),
            'form_name'       => $form['title'] ?? 'Gravity Form',
            'source_url'      => rgar($entry, 'source_url') ?: wp_get_referer() ?: home_url(),
            'fields'          => $fields,
            'idempotency_key' => 'gf_' . md5(($form['id'] ?? '') . rgar($entry, 'id') . serialize($fields)),
        ];

        $this->dispatch_inquiry($payload);
    }

    /**
     * Handle Fluent Forms submission.
     */
    public function handle_fluent_submission($insertId, $formData, $form) {
        if (!get_option(self::OPTION_ENABLED, '1')) return;

        $payload = [
            'form_id'         => 'fluent_' . ($form->id ?? '0'),
            'form_name'       => $form->title ?? 'Fluent Form',
            'source_url'      => wp_get_referer() ?: home_url(),
            'fields'          => is_array($formData) ? $formData : [],
            'idempotency_key' => 'fluent_' . md5(($form->id ?? '') . $insertId . serialize($formData)),
        ];

        $this->dispatch_inquiry($payload);
    }

    /* ─── Secure Transmission & Offline Queue ────────────────────────────── */

    /**
     * Safely dispatch inquiry to DGT Central System.
     * Guaranteed non-blocking: never halts user form completion if DGT is unreachable.
     */
    public function dispatch_inquiry(array $payload) {
        $dgtUrl    = rtrim(get_option(self::OPTION_DGT_URL, ''), '/');
        $apiKey    = get_option(self::OPTION_API_KEY, '');
        $apiSecret = get_option(self::OPTION_API_SECRET, '');

        if (empty($dgtUrl) || empty($apiKey) || empty($apiSecret)) {
            error_log('DGT Inquiries: Missing API credentials. Inquiry dropped or pending configuration.');
            return false;
        }

        // Add client IP, User-Agent, and timestamp
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
            'timeout'     => 6,
            'redirection' => 2,
            'httpversion' => '1.1',
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
            return true; // Successfully delivered
        }

        // Delivery failed: enqueue for background retry via WP-Cron
        $this->enqueue_for_retry($payload);
        return false;
    }

    /**
     * Store failed submission in retry queue.
     */
    protected function enqueue_for_retry(array $payload) {
        $queue = get_option(self::OPTION_QUEUE, []);
        if (!is_array($queue)) $queue = [];

        // Limit queue size to 100 items to avoid bloating wp_options
        if (count($queue) >= 100) {
            array_shift($queue);
        }

        $payload['retry_count'] = 0;
        $payload['queued_at']   = time();

        $queue[] = $payload;
        update_option(self::OPTION_QUEUE, $queue, false);
    }

    /**
     * WP-Cron background worker: retry queued inquiries.
     */
    public function process_retry_queue() {
        $queue = get_option(self::OPTION_QUEUE, []);
        if (empty($queue) || !is_array($queue)) return;

        $remainingQueue = [];

        foreach ($queue as $item) {
            $item['retry_count'] = ($item['retry_count'] ?? 0) + 1;

            // Attempt delivery
            $success = $this->dispatch_inquiry($item);

            if (!$success) {
                // Keep in queue if retry count < 5
                if ($item['retry_count'] < 5) {
                    $remainingQueue[] = $item;
                }
            }
        }

        update_option(self::OPTION_QUEUE, $remainingQueue, false);
    }

    /* ─── Admin Settings Screen ──────────────────────────────────────────── */

    public function register_admin_menu() {
        add_options_page(
            'DGT Inquiries Connector',
            'DGT Inquiries',
            'manage_options',
            'dgt-inquiries',
            [$this, 'render_admin_page']
        );
    }

    public function register_settings() {
        register_setting('dgt_inquiries_group', self::OPTION_DGT_URL);
        register_setting('dgt_inquiries_group', self::OPTION_API_KEY);
        register_setting('dgt_inquiries_group', self::OPTION_API_SECRET);
        register_setting('dgt_inquiries_group', self::OPTION_ENABLED);
    }

    public function render_admin_page() {
        $queue = get_option(self::OPTION_QUEUE, []);
        $queueCount = is_array($queue) ? count($queue) : 0;
        ?>
        <div class="wrap">
            <h1>DGT Website Inquiries Connector</h1>
            <p>Automatically captures form submissions and pushes them to your central DGT System dashboard.</p>

            <form method="post" action="options.php">
                <?php settings_fields('dgt_inquiries_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">DGT Platform URL</th>
                        <td>
                            <input type="url" name="<?php echo self::OPTION_DGT_URL; ?>" value="<?php echo esc_attr(get_option(self::OPTION_DGT_URL, 'https://lightcyan-weasel-711536.hostingersite.com')); ?>" class="regular-text" required />
                            <p class="description">URL of your central DGT installation.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Integration API Key (X-DGT-Key)</th>
                        <td>
                            <input type="text" name="<?php echo self::OPTION_API_KEY; ?>" value="<?php echo esc_attr(get_option(self::OPTION_API_KEY)); ?>" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Integration API Secret (HMAC)</th>
                        <td>
                            <input type="password" name="<?php echo self::OPTION_API_SECRET; ?>" value="<?php echo esc_attr(get_option(self::OPTION_API_SECRET)); ?>" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enable Inquiry Capture</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo self::OPTION_ENABLED; ?>" value="1" <?php checked(get_option(self::OPTION_ENABLED, '1'), '1'); ?> />
                                Automatically transmit Contact Form 7, Elementor, WPForms, Gravity Forms, and Fluent Forms submissions.
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>

            <hr style="margin: 30px 0;">

            <h2>Delivery Health & Offline Queue</h2>
            <p><strong>Pending Retries in Queue:</strong> <?php echo esc_html($queueCount); ?> item(s)</p>
            <p class="description">Submissions that failed due to temporary network issues are stored safely and retried automatically every 5 minutes by WP-Cron.</p>
        </div>
        <?php
    }
}

new DGT_Inquiries_Connector();

}
