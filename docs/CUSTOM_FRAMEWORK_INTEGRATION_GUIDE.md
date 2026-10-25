# Custom Framework & PHP Website Integration Guide

This guide describes how to connect custom Laravel, Core PHP, React, Next.js, or Node.js applications to the DGT Central Integration Platform.

---

## 1. PHP SDK Integration (Laravel / Core PHP)

A standalone SDK client is available at:
`https://YOUR-DGT-DOMAIN.com/connectors/custom-php/dgt-connector-client.php`

### Example Setup (Core PHP / Custom MVC)
```php
require_once __DIR__ . '/dgt-connector-client.php';

$dgt = new DgtConnectorClient(
    'https://YOUR-DGT-DOMAIN.com',
    'dgt_live_REPLACE_WITH_YOUR_KEY',
    'dgt_sec_REPLACE_WITH_YOUR_SECRET'
);

// 1. Handshake / Health Check
$handshake = $dgt->handshake();
if (!$handshake['success']) {
    die("Connection failed: " . ($handshake['message'] ?? 'Unknown error'));
}

// 2. Synchronize Blog Posts & Views
$response = $dgt->syncPosts([
    [
        'external_id'      => 'article-45',
        'title'            => 'Crawler Excavator Maintenance Checklist',
        'slug'             => 'crawler-excavator-maintenance',
        'canonical_url'    => 'https://yoursite.com/blog/crawler-excavator-maintenance',
        'author_name'      => 'Technical Team',
        'status'           => 'published',
        'published_at'     => '2026-10-25 10:00:00',
        'cumulative_views' => 950,
        'period_views'     => 220,
        'analytics_source' => 'custom_counter',
        'daily_snapshots'  => [
            ['date' => '2026-10-24', 'views' => 110, 'unique_visitors' => 70],
            ['date' => '2026-10-25', 'views' => 110, 'unique_visitors' => 85]
        ]
    ]
]);

echo "Synced " . $response['items_synced'] . " posts to DGT Central.";
```

---

## 2. Laravel Integration Example

In a Laravel website project, create an Artisan command scheduled to push data to DGT:

```php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Post;

class PushBlogsToDgtCommand extends Command
{
    protected $signature = 'dgt:push-blogs';

    public function handle()
    {
        $platformUrl = config('services.dgt.url', 'https://YOUR-DGT-DOMAIN.com');
        $apiKey      = config('services.dgt.key', 'dgt_live_REPLACE_WITH_YOUR_KEY');
        $apiSecret   = config('services.dgt.secret', 'dgt_sec_REPLACE_WITH_YOUR_SECRET');

        $posts = Post::published()->get()->map(function ($p) {
            return [
                'external_id'      => (string) $p->id,
                'title'            => $p->title,
                'slug'             => $p->slug,
                'canonical_url'    => url('/blog/' . $p->slug),
                'author_name'      => $p->author?->name ?? 'Staff',
                'status'           => 'published',
                'published_at'     => $p->created_at->toIso8601String(),
                'cumulative_views' => (int) $p->view_count,
                'analytics_source' => 'custom_counter',
            ];
        })->toArray();

        $payload   = json_encode(['posts' => $posts, 'sync_source' => 'laravel_scheduled_push']);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $apiSecret);

        $response = Http::timeout(30)->withHeaders([
            'Content-Type'    => 'application/json',
            'X-DGT-Key'       => $apiKey,
            'X-DGT-Timestamp' => $timestamp,
            'X-DGT-Signature' => $signature,
        ])->post($platformUrl . '/api/v1/connector/sync/posts', $posts);

        $this->info("DGT Push result: " . $response->body());
    }
}
```

---

## 3. Node.js / Next.js Integration Example

For React, Next.js, or Express backends, use Node's native `crypto` module:

```javascript
const crypto = require('crypto');

async function syncPostsToDgt(posts) {
  const dgtUrl = 'https://YOUR-DGT-DOMAIN.com';
  const apiKey = 'dgt_live_REPLACE_WITH_YOUR_KEY';
  const apiSecret = 'dgt_sec_REPLACE_WITH_YOUR_SECRET';

  const body = JSON.stringify({
    posts: posts,
    sync_source: 'nextjs_api_push'
  });

  const timestamp = Math.floor(Date.now() / 1000).toString();
  const nonce = crypto.randomBytes(16).toString('hex');
  const signature = crypto
    .createHmac('sha256', apiSecret)
    .update(`${timestamp}.${body}`)
    .digest('hex');

  const response = await fetch(`${dgtUrl}/api/v1/connector/sync/posts`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-DGT-Key': apiKey,
      'X-DGT-Timestamp': timestamp,
      'X-DGT-Nonce': nonce,
      'X-DGT-Signature': signature,
    },
    body: body,
  });

  const result = await response.json();
  console.log('DGT Sync Response:', result);
  return result;
}
```
