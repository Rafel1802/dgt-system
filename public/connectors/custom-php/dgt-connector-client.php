<?php
/**
 * DGT Central Website Connector SDK Client (PHP)
 *
 * Use this lightweight client to transmit blog posts, views, and system health
 * from any custom PHP, Laravel, or custom CMS website to DGT Central System.
 *
 * Example usage:
 *   $client = new DgtConnectorClient(
 *       'https://lightcyan-weasel-711536.hostingersite.com',
 *       'dgt_live_YOUR_API_KEY',
 *       'dgt_sec_YOUR_API_SECRET'
 *   );
 *
 *   // 1. Handshake / Test
 *   $res = $client->handshake();
 *
 *   // 2. Transmit blog posts & view counts
 *   $res = $client->syncPosts([
 *       [
 *           'external_id'      => 'post-101',
 *           'title'            => 'Excavator Maintenance Guide',
 *           'canonical_url'    => 'https://example.com/blog/excavator-guide',
 *           'author_name'      => 'John Doe',
 *           'published_at'     => '2026-10-25 14:00:00',
 *           'cumulative_views' => 1420,
 *           'period_views'     => 350,
 *           'analytics_source' => 'custom_counter',
 *       ]
 *   ]);
 */

class DgtConnectorClient {
    private string $baseUrl;
    private string $apiKey;
    private string $apiSecret;

    public function __construct(string $baseUrl, string $apiKey, string $apiSecret) {
        $this->baseUrl   = rtrim($baseUrl, '/');
        $this->apiKey    = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    /**
     * Handshake with DGT Central System.
     */
    public function handshake(): array {
        return $this->request('GET', '/api/v1/connector/handshake');
    }

    /**
     * Send health check ping and environment information.
     */
    public function ping(array $techStack = [], ?string $hostingerAccount = null): array {
        return $this->request('POST', '/api/v1/connector/ping', [
            'tech_stack'        => array_merge([
                'php_version' => PHP_VERSION,
                'os'          => PHP_OS,
                'server'      => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            ], $techStack),
            'hostinger_account' => $hostingerAccount,
        ]);
    }

    /**
     * Verify website domain ownership.
     */
    public function verify(string $method = 'direct_handshake'): array {
        return $this->request('POST', '/api/v1/connector/verify', [
            'method' => $method,
        ]);
    }

    /**
     * Push an array of blog posts and view metrics to DGT Central System.
     */
    public function syncPosts(array $posts, string $source = 'custom_php_client'): array {
        return $this->request('POST', '/api/v1/connector/sync/posts', [
            'posts'       => $posts,
            'sync_source' => $source,
        ]);
    }

    /**
     * Execute signed HTTP request with HMAC SHA-256.
     */
    private function request(string $method, string $path, array $data = []): array {
        $url       = $this->baseUrl . $path;
        $timestamp = (string) time();
        $nonce     = bin2hex(random_bytes(16));
        $payload   = ($method === 'GET' || empty($data)) ? '' : json_encode($data);

        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $this->apiSecret);

        $headers = [
            'X-DGT-Key: ' . $this->apiKey,
            'X-DGT-Timestamp: ' . $timestamp,
            'X-DGT-Nonce: ' . $nonce,
            'X-DGT-Signature: ' . $signature,
            'Accept: application/json',
            'User-Agent: DGT-PHP-Connector-Client/1.0',
        ];

        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'success' => false,
                'error'   => 'curl_error',
                'message' => $error,
            ];
        }

        $json = json_decode($response, true);
        if ($json === null) {
            return [
                'success'   => false,
                'http_code' => $httpCode,
                'raw'       => $response,
            ];
        }

        return $json;
    }
}
