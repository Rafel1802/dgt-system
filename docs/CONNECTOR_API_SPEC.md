# DGT Central Website Connector API Specification (v1)

## Overview
The DGT Central Website Integration Platform provides a standardized, versioned API (`/api/v1/connector/*`) enabling external websites hosted across multiple Hostinger accounts (or elsewhere) to synchronize blog posts, view metrics, technology stack information, and health status with the central DGT System.

- **Base URL (Production)**: `https://YOUR-DGT-DOMAIN.com/api/v1/connector`
- **Current Version**: `v1`
- **Protocol**: HTTPS only
- **Data Format**: JSON (`Content-Type: application/json`, `Accept: application/json`)
- **Rate Limit**: 60 requests per minute per IP / API Key

---

## Authentication & Security

All requests to the Connector API must authenticate via **HMAC-SHA256** server-to-server signature or Bearer token with per-site credentials.

### Required Headers (HMAC-SHA256 — Recommended)

| Header | Description | Example |
| :--- | :--- | :--- |
| `X-DGT-Key` | The site's unique, revocable API Key | `dgt_live_9a7bc2810f3e...` |
| `X-DGT-Timestamp` | Current Unix timestamp (in seconds) | `1729868400` |
| `X-DGT-Nonce` | Unique cryptographic random string (replay defense) | `c7f9e8a01b2c3d4e5f6a` |
| `X-DGT-Signature` | HMAC-SHA256 hexadecimal hash | `9e82f7d3a...` |

### Signature Generation Formula
```php
$stringToSign = $timestamp . '.' . $rawJsonBody;
// Note: For GET requests or requests with an empty body, $rawJsonBody is an empty string ("")
$signature = hash_hmac('sha256', $stringToSign, $apiSecret);
```

### Security Rules
1. **Replay Protection**: Timestamps must fall within $\pm 300$ seconds ($\pm 5$ minutes) of DGT Central server time. Requests with expired timestamps are rejected with HTTP 401 (`timestamp_expired`).
2. **Nonce Cache**: Nonces are cached for 300 seconds; duplicate nonces are rejected with HTTP 401 (`replay_detected`).
3. **Website Ownership Verification**: The authenticated `X-DGT-Key` strictly resolves the target website record. If a client supplies `website_id` in the request, it **must** match the owner of the API key; otherwise, the request is rejected with HTTP 403 (`website_mismatch`).
4. **Secret Storage**: All API secrets are encrypted at rest with AES-256-CBC in the DGT database and are never logged or returned in responses.

---

## API Endpoints

### 1. Handshake
Verify credentials, connection health, and obtain server capabilities.

- **Method**: `GET`
- **Path**: `/handshake`
- **Headers**: `X-DGT-Key`, `X-DGT-Timestamp`, `X-DGT-Signature`

#### Success Response (`HTTP 200 OK`)
```json
{
  "success": true,
  "message": "Handshake successful. Connected to DGT Central System.",
  "website_id": 10,
  "website_name": "Mini Excavator",
  "domain": "miniexcavator.org",
  "is_verified": true,
  "cms_status": "connected",
  "analytics_status": "connected",
  "server_time": 1729868400,
  "server_timezone": "Asia/Phnom_Penh",
  "api_version": "v1",
  "supported_features": [
    "posts_sync",
    "views_tracking",
    "daily_snapshots",
    "health_ping",
    "tech_detection"
  ]
}
```

---

### 2. Domain Verification
Verify ownership of the connected domain.

- **Method**: `POST`
- **Path**: `/verify`
- **Headers**: `X-DGT-Key`, `X-DGT-Timestamp`, `X-DGT-Signature`
- **Request Body**:
```json
{
  "method": "direct_handshake"
}
```

#### Success Response (`HTTP 200 OK`)
```json
{
  "success": true,
  "message": "Website 'Mini Excavator' successfully verified.",
  "website_id": 10,
  "verified_at": "2026-10-25T14:30:00+07:00"
}
```

---

### 3. Environment & Health Ping
Send technology stack details and health telemetry.

- **Method**: `POST`
- **Path**: `/ping`
- **Headers**: `X-DGT-Key`, `X-DGT-Timestamp`, `X-DGT-Signature`
- **Request Body**:
```json
{
  "tech_stack": {
    "cms": "WordPress",
    "cms_version": "6.6.2",
    "php_version": "8.3.10",
    "web_server": "LiteSpeed",
    "framework": null
  },
  "connector_type": "wordpress_plugin",
  "hostinger_account": "Hostinger Account 1"
}
```

#### Success Response (`HTTP 200 OK`)
```json
{
  "success": true,
  "status": "pong",
  "server_time": 1729868400,
  "website_id": 10,
  "last_sync_at": "2026-10-25T14:00:00+07:00"
}
```

---

### 4. Blog Posts & Views Synchronization (Push Mode)
Transmit blog posts, canonical URLs, publication dates, and view counts.

- **Method**: `POST`
- **Path**: `/sync/posts`
- **Headers**: `X-DGT-Key`, `X-DGT-Timestamp`, `X-DGT-Signature`
- **Request Body**:
```json
{
  "sync_source": "wordpress_plugin_push",
  "posts": [
    {
      "external_id": "1042",
      "title": "Essential Mini Excavator Safety Protocols for 2026",
      "slug": "mini-excavator-safety-protocols-2026",
      "canonical_url": "https://miniexcavator.org/blog/safety-protocols",
      "author_name": "Sreypich Meas",
      "status": "published",
      "published_at": "2026-10-24 09:30:00",
      "modified_at": "2026-10-25 11:15:00",
      "featured_image_url": "https://miniexcavator.org/wp-content/uploads/safety.webp",
      "excerpt": "Comprehensive guide on daily walkaround inspection and hydraulic maintenance.",
      "cumulative_views": 1845,
      "period_views": 420,
      "page_views": 1845,
      "unique_visitors": 1210,
      "analytics_source": "wp_postviews",
      "daily_snapshots": [
        {
          "date": "2026-10-24",
          "views": 210,
          "page_views": 210,
          "unique_visitors": 140
        },
        {
          "date": "2026-10-25",
          "views": 210,
          "page_views": 210,
          "unique_visitors": 155
        }
      ]
    }
  ]
}
```

#### Success Response (`HTTP 200 OK`)
```json
{
  "success": true,
  "website_id": 10,
  "total_received": 1,
  "items_synced": 1,
  "items_updated": 0,
  "items_skipped": 0,
  "errors": [],
  "synced_at": "2026-10-25T14:35:12+07:00"
}
```

#### Idempotency Behavior
Repeated calls with the same post content and metrics compute an identical `sync_hash` and skip unnecessary database writes (`items_skipped: 1`). Posts are never duplicated and view counts are never artificially inflated.

---

## Error Codes & Responses

| Status Code | Error Identifier | Description |
| :--- | :--- | :--- |
| `401 Unauthorized` | `unauthorized` | Missing API key in request headers |
| `401 Unauthorized` | `invalid_key` | API key is invalid, inactive, or revoked |
| `401 Unauthorized` | `invalid_signature` | HMAC signature does not match payload |
| `401 Unauthorized` | `timestamp_expired` | Timestamp drift exceeds 300 seconds |
| `401 Unauthorized` | `replay_detected` | Nonce has already been consumed |
| `403 Forbidden` | `website_mismatch` | Client claims website ID not belonging to key |
| `422 Unprocessable` | `validation_error` | Missing required fields in `posts` array |
| `429 Too Many Req` | `rate_limited` | Exceeded 60 requests per minute limit |
| `500 Server Error` | `server_error` | Unexpected server execution exception |
