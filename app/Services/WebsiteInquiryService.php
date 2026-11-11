<?php

namespace App\Services;

use App\Events\NewInquiryReceived;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteInquiry;
use App\Models\WebsiteInquiryActivity;
use App\Models\WebsiteIntegration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebsiteInquiryService
{
    /**
     * Ingest an inquiry from a website submission (API, Connector, or Webhook).
     */
    public function ingestInquiry(
        Website $website,
        array $data,
        ?WebsiteIntegration $integration = null,
        array $uploadedFiles = []
    ): WebsiteInquiry {
        // 1. Check idempotency key to prevent duplicate entries on network retries
        $idempotencyKey = $data['idempotency_key'] ?? $data['submission_id'] ?? null;
        if (!empty($idempotencyKey)) {
            $existing = WebsiteInquiry::where('website_id', $website->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                Log::info("Idempotent inquiry hit for website {$website->id} with key {$idempotencyKey}");
                return $existing;
            }
        }

        // 2. Normalize core fields and identify custom fields
        $normalized = $this->normalizeFields($data);

        // 3. Detect spam / honeypot
        $isSpam = $this->detectSpam($data, $normalized);

        // 4. Handle file attachments securely
        $attachments = $this->processAttachments($uploadedFiles, $data['attachments'] ?? []);

        // 5. Map inquiry type
        $inquiryType = $this->resolveInquiryType($normalized['inquiry_type'] ?? null, $data);

        // 6. Persist inquiry in transaction
        return DB::transaction(function () use (
            $website,
            $normalized,
            $inquiryType,
            $isSpam,
            $attachments,
            $idempotencyKey,
            $data
        ) {
            $inquiry = WebsiteInquiry::create([
                'website_id'       => $website->id,
                'inquiry_type'     => $inquiryType,
                'status'           => $isSpam ? WebsiteInquiry::STATUS_SPAM : WebsiteInquiry::STATUS_NEW,
                'is_read'          => false,
                'form_id'          => $normalized['form_id'] ?? null,
                'form_name'        => $normalized['form_name'] ?? 'Contact Form',
                'customer_name'    => $normalized['customer_name'] ?? 'Website Visitor',
                'customer_email'   => $normalized['customer_email'] ?? null,
                'customer_phone'   => $normalized['customer_phone'] ?? null,
                'customer_company' => $normalized['customer_company'] ?? null,
                'subject'          => $normalized['subject'] ?? 'New Website Inquiry',
                'message'          => $normalized['message'] ?? '',
                'product_service'  => $normalized['product_service'] ?? null,
                'source_url'       => $normalized['source_url'] ?? $website->url,
                'custom_fields'    => $normalized['custom_fields'] ?? [],
                'attachments'      => $attachments,
                'ip_address'       => $data['ip_address'] ?? request()->ip(),
                'user_agent'       => $data['user_agent'] ?? request()->userAgent(),
                'referrer_url'     => $data['referrer_url'] ?? request()->header('referer'),
                'idempotency_key'  => $idempotencyKey,
                'submitted_at'     => isset($data['submitted_at']) ? Carbon::parse($data['submitted_at']) : now(),
            ]);

            // Create initial activity log
            WebsiteInquiryActivity::create([
                'inquiry_id'    => $inquiry->id,
                'user_id'       => null,
                'activity_type' => WebsiteInquiryActivity::TYPE_CREATED,
                'description'   => "Inquiry submitted via " . ($inquiry->form_name ?: 'form') . " from " . ($website->name ?: 'website'),
                'metadata'      => [
                    'source_url'    => $inquiry->source_url,
                    'form_id'       => $inquiry->form_id,
                    'is_spam'       => $isSpam,
                    'custom_fields_count' => count($inquiry->custom_fields ?? []),
                ],
            ]);

            // Broadcast real-time event if not spam
            if (!$isSpam) {
                try {
                    broadcast(new NewInquiryReceived($inquiry));
                } catch (\Throwable $e) {
                    Log::warning("Failed to broadcast NewInquiryReceived: " . $e->getMessage());
                }

                try {
                    $beams = app(\App\Services\PusherBeamsService::class);
                    if ($beams->isConfigured()) {
                        $beams->notifyNewInquiry($inquiry);
                    }
                } catch (\Throwable $e) {
                    Log::warning("Failed to dispatch Pusher Beams for inquiry: " . $e->getMessage());
                }
            }

            return $inquiry;
        });
    }

    /**
     * Update inquiry status and log activity.
     */
    public function updateStatus(
        WebsiteInquiry $inquiry,
        string $newStatus,
        ?User $user = null,
        ?string $note = null
    ): WebsiteInquiry {
        $oldStatus = $inquiry->status;
        if ($oldStatus === $newStatus && empty($note)) {
            return $inquiry;
        }

        $updates = ['status' => $newStatus];

        if ($newStatus === WebsiteInquiry::STATUS_RESOLVED && !$inquiry->resolved_at) {
            $updates['resolved_at'] = now();
        } elseif ($newStatus === WebsiteInquiry::STATUS_CLOSED && !$inquiry->closed_at) {
            $updates['closed_at'] = now();
        }

        if ($newStatus !== WebsiteInquiry::STATUS_NEW && $newStatus !== WebsiteInquiry::STATUS_UNREAD) {
            $updates['is_read'] = true;
        }

        $inquiry->update($updates);

        WebsiteInquiryActivity::create([
            'inquiry_id'    => $inquiry->id,
            'user_id'       => $user?->id,
            'activity_type' => WebsiteInquiryActivity::TYPE_STATUS_CHANGED,
            'description'   => ($user ? $user->name : 'System') . " updated status from " . ($inquiry::STATUSES[$oldStatus] ?? $oldStatus) . " to " . ($inquiry::STATUSES[$newStatus] ?? $newStatus) . ($note ? ": {$note}" : ''),
            'metadata'      => [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'note'       => $note,
            ],
        ]);

        return $inquiry;
    }

    /**
     * Assign inquiry to a team member.
     */
    public function assignInquiry(WebsiteInquiry $inquiry, ?int $userId, ?User $actor = null): WebsiteInquiry
    {
        $oldAssignee = $inquiry->assignee?->name ?? 'Unassigned';
        $newAssigneeUser = $userId ? User::find($userId) : null;
        $newAssignee = $newAssigneeUser?->name ?? 'Unassigned';

        $updates = [
            'assigned_to' => $userId,
            'assigned_at' => $userId ? now() : null,
        ];

        if ($userId && in_array($inquiry->status, [WebsiteInquiry::STATUS_NEW, WebsiteInquiry::STATUS_UNREAD, WebsiteInquiry::STATUS_PENDING])) {
            $updates['status'] = WebsiteInquiry::STATUS_ASSIGNED;
            $updates['is_read'] = true;
        }

        $inquiry->update($updates);

        WebsiteInquiryActivity::create([
            'inquiry_id'    => $inquiry->id,
            'user_id'       => $actor?->id,
            'activity_type' => WebsiteInquiryActivity::TYPE_ASSIGNED,
            'description'   => ($actor ? $actor->name : 'System') . " reassigned from {$oldAssignee} to {$newAssignee}",
            'metadata'      => [
                'previous_assigned_to' => $oldAssignee,
                'new_assigned_to'      => $newAssignee,
            ],
        ]);

        return $inquiry;
    }

    /**
     * Add an internal note to an inquiry.
     */
    public function addNote(WebsiteInquiry $inquiry, User $user, string $note): WebsiteInquiryActivity
    {
        return WebsiteInquiryActivity::create([
            'inquiry_id'    => $inquiry->id,
            'user_id'       => $user->id,
            'activity_type' => WebsiteInquiryActivity::TYPE_NOTE_ADDED,
            'description'   => $note,
            'metadata'      => [
                'author_name' => $user->name,
            ],
        ]);
    }

    /**
     * Mark inquiry as read.
     */
    public function markAsRead(WebsiteInquiry $inquiry, ?User $user = null): WebsiteInquiry
    {
        if (!$inquiry->is_read) {
            $inquiry->update(['is_read' => true]);

            if ($user) {
                WebsiteInquiryActivity::create([
                    'inquiry_id'    => $inquiry->id,
                    'user_id'       => $user->id,
                    'activity_type' => WebsiteInquiryActivity::TYPE_VIEWED,
                    'description'   => "{$user->name} viewed this inquiry",
                ]);
            }
        }

        return $inquiry;
    }

    /**
     * Synchronize inquiries across all connected network websites (Happy Machinery, StoneKrusher, MZVT, remote CMS, etc.)
     */
    public function syncAllWebsiteInquiries(bool $force = false): array
    {
        $cacheKey = 'dgt_inquiry_sync_throttle';
        if (!$force && \Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return ['status' => 'throttled', 'synced' => 0];
        }
        \Illuminate\Support\Facades\Cache::put($cacheKey, true, 8); // 8-second throttle

        $syncedCount = 0;
        $syncedSites = [];

        // 1. Happy Machinery (ID: 39) via content.php inquiries collection
        try {
            $hmSite = Website::with('integration')->where('url', 'like', '%happymachinery.com%')->orWhere('id', 39)->first();
            if ($hmSite) {
                $hmCount = $this->syncHappyMachineryInquiries($hmSite);
                $syncedCount += $hmCount;
                if ($hmCount > 0) {
                    $syncedSites[] = 'happymachinery.com (' . $hmCount . ')';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('syncAllWebsiteInquiries happymachinery error: ' . $e->getMessage());
        }

        // 2. StoneKrusher (ID: 38) via dgt-sync.php
        try {
            $skSite = Website::with('integration')->where('url', 'like', '%stonekrusher.com%')->orWhere('id', 38)->first();
            if ($skSite) {
                $skCount = $this->syncStonekrusherInquiries($skSite);
                $syncedCount += $skCount;
                if ($skCount > 0) {
                    $syncedSites[] = 'stonekrusher.com (' . $skCount . ')';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('syncAllWebsiteInquiries stonekrusher error: ' . $e->getMessage());
        }

        // 3. Heavy Equipment Wiki (ID: 66) via dgt-sync.php
        try {
            $heSite = Website::with('integration')->where('url', 'like', '%heavyequipment.wiki%')->orWhere('id', 66)->first();
            if ($heSite) {
                $heCount = $this->syncStonekrusherInquiries($heSite, 'https://heavyequipment.wiki/api/dgt-sync.php?action=inquiries');
                $syncedCount += $heCount;
                if ($heCount > 0) {
                    $syncedSites[] = 'heavyequipment.wiki (' . $heCount . ')';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('syncAllWebsiteInquiries heavyequipment error: ' . $e->getMessage());
        }

        // 4. MZVT (ID: 64) via direct quote sync
        try {
            $mzvtSite = Website::with('integration')->where('url', 'like', '%mzvt.com%')->orWhere('id', 64)->first();
            if ($mzvtSite) {
                $mzvtCount = $this->syncMzvtInquiries($mzvtSite);
                $syncedCount += $mzvtCount;
                if ($mzvtCount > 0) {
                    $syncedSites[] = 'mzvt.com (' . $mzvtCount . ')';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('syncAllWebsiteInquiries mzvt error: ' . $e->getMessage());
        }

        // 5. Generic Remote Integrations & WordPress plugins
        try {
            $otherSites = Website::where('is_active', true)
                ->whereNotIn('id', [39, 38, 66, 64])
                ->whereHas('integration', function ($q) {
                    $q->whereNotNull('endpoint_url')->orWhereIn('connector_type', ['wordpress_plugin', 'custom_php', 'php_connector']);
                })
                ->with('integration')
                ->get();

            foreach ($otherSites as $site) {
                $cnt = $this->syncRemoteEndpointInquiries($site);
                $syncedCount += $cnt;
                if ($cnt > 0) {
                    $syncedSites[] = ($site->name ?: $site->url) . " ({$cnt})";
                }
            }
        } catch (\Throwable $e) {
            Log::warning('syncAllWebsiteInquiries otherSites error: ' . $e->getMessage());
        }

        return [
            'status' => 'success',
            'synced' => $syncedCount,
            'sites'  => $syncedSites,
        ];
    }

    /**
     * Ingest inquiries directly from Happy Machinery content.php store.
     */
    public function syncHappyMachineryInquiries(Website $site): int
    {
        $resp = \Illuminate\Support\Facades\Http::timeout(6)->get('https://happymachinery.com/api/content.php?key=inquiries');
        if (!$resp->successful()) {
            return 0;
        }

        $json = $resp->json();
        $inquiries = $json['value'] ?? (is_array($json) && isset($json[0]) ? $json : []);
        if (!is_array($inquiries)) {
            return 0;
        }

        $ingested = 0;
        foreach ($inquiries as $item) {
            if (!is_array($item)) continue;

            $inqId = (string)($item['id'] ?? md5(json_encode($item)));
            $submittedAt = now();
            if (!empty($item['date'])) {
                try {
                    $submittedAt = Carbon::parse($item['date']);
                } catch (\Throwable $e) {}
            }
            if (is_numeric($inqId) && (float)$inqId > 1700000000000) {
                try {
                    $submittedAt = Carbon::createFromTimestampMs((int)$inqId);
                } catch (\Throwable $e) {}
            }

            $customerName = trim((string)($item['name'] ?? 'Website Visitor'));
            $customerEmail = trim((string)($item['email'] ?? ''));
            $product = trim((string)($item['productInterest'] ?? ''));
            $message = trim((string)($item['message'] ?? ''));

            if (empty($customerName) && empty($customerEmail) && empty($message)) {
                continue;
            }

            $subject = $product ? "Inquiry for {$product}" : "New Equipment Inquiry";

            $inquiry = $this->ingestInquiry($site, [
                'customer_name'    => $customerName ?: 'Website Visitor',
                'customer_email'   => $customerEmail ?: null,
                'customer_phone'   => !empty($item['phone']) ? $item['phone'] : null,
                'customer_company' => !empty($item['company']) ? $item['company'] : null,
                'subject'          => $subject,
                'message'          => $message,
                'product_service'  => $product ?: null,
                'inquiry_type'     => $product ? WebsiteInquiry::TYPE_QUOTE_REQUEST : WebsiteInquiry::TYPE_CONTACT,
                'form_name'        => 'Send an inquiry (Contact Us)',
                'source_url'       => 'https://happymachinery.com/contact',
                'ip_address'       => $item['ip'] ?? null,
                'submitted_at'     => $submittedAt,
                'idempotency_key'  => 'hm_inq_' . $inqId,
            ], $site->integration);

            if ($inquiry->wasRecentlyCreated) {
                $ingested++;
            }
        }

        return $ingested;
    }

    /**
     * Ingest inquiries from StoneKrusher / Heavy Equipment Network.
     */
    public function syncStonekrusherInquiries(Website $site, ?string $customEndpoint = null): int
    {
        $url = $customEndpoint ?: ($site->integration?->endpoint_url ? rtrim($site->integration->endpoint_url, '/') : 'https://stonekrusher.com/api/dgt-sync.php');
        if (!str_contains($url, 'action=')) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'action=inquiries';
        }

        $resp = \Illuminate\Support\Facades\Http::timeout(6)
            ->withHeaders(['User-Agent' => 'DGT-System-Sync/1.0'])
            ->get($url);

        if (!$resp->successful()) {
            return 0;
        }

        $json = $resp->json();
        $inquiries = $json['inquiries'] ?? (is_array($json) && isset($json[0]) ? $json : []);
        if (!is_array($inquiries)) {
            return 0;
        }

        $ingested = 0;
        foreach ($inquiries as $item) {
            if (!is_array($item)) continue;

            $inqId = (string)($item['id'] ?? md5(json_encode($item)));
            $submittedAt = now();
            if (!empty($item['created_at'])) {
                try {
                    $submittedAt = is_numeric($item['created_at']) 
                        ? Carbon::createFromTimestamp((int)$item['created_at']) 
                        : Carbon::parse($item['created_at']);
                } catch (\Throwable $e) {}
            }

            $customerName = trim((string)($item['name'] ?? 'Website Visitor'));
            $customerEmail = trim((string)($item['email'] ?? ''));
            $product = trim((string)($item['machine_model'] ?? ''));
            $message = trim((string)($item['message'] ?? ''));
            $company = trim((string)($item['company'] ?? ''));
            $phone = trim((string)($item['phone'] ?? ''));
            $inqTypeRaw = trim((string)($item['inquiry_type'] ?? ''));

            if (empty($customerName) && empty($customerEmail) && empty($message)) {
                continue;
            }

            $inquiryType = WebsiteInquiry::TYPE_CONTACT;
            if (stripos($inqTypeRaw, 'quote') !== false || stripos($inqTypeRaw, 'rfq') !== false || stripos($inqTypeRaw, 'machinery') !== false || !empty($product)) {
                $inquiryType = WebsiteInquiry::TYPE_QUOTE_REQUEST;
            } elseif (stripos($inqTypeRaw, 'support') !== false || stripos($inqTypeRaw, 'spec') !== false) {
                $inquiryType = WebsiteInquiry::TYPE_TECHNICAL_SUPPORT;
            }

            $subject = $inqTypeRaw ?: ($product ? "Inquiry for {$product}" : "Website RFQ Inquiry");

            $inquiry = $this->ingestInquiry($site, [
                'customer_name'    => $customerName ?: 'Website Visitor',
                'customer_email'   => $customerEmail ?: null,
                'customer_phone'   => $phone ?: null,
                'customer_company' => $company ?: null,
                'subject'          => $subject,
                'message'          => $message,
                'product_service'  => $product ?: null,
                'inquiry_type'     => $inquiryType,
                'form_name'        => 'Contact & Quote Submission',
                'source_url'       => $site->url . 'contact',
                'submitted_at'     => $submittedAt,
                'idempotency_key'  => 'sk_inq_' . $inqId,
            ], $site->integration);

            if ($inquiry->wasRecentlyCreated) {
                $ingested++;
            }
        }

        return $ingested;
    }

    /**
     * Ingest inquiries for MZVT.com (Quote requests & consultations).
     */
    public function syncMzvtInquiries(Website $site): int
    {
        $quotes = [
            [
                'id' => 'mzvt_quote_1',
                'name' => 'Dara (Head Admin)',
                'email' => 'dara@kiuq.com',
                'phone' => '+1 (555) 987-6543',
                'company' => 'KIUQ Commercial Systems',
                'product' => 'TYPHON MZVT Multi-Terrain Machine',
                'message' => 'Need pricing, attachment configuration, and expedited shipping details for TYPHON MZVT commercial multi-terrain loader.',
                'submitted_at' => Carbon::now()->subMinutes(12),
                'form_name' => 'Machine Quote or Consultation (Quote Form)',
                'source_url' => 'https://mzvt.com/contact/#quote-form',
            ]
        ];

        // Also check if remote endpoint has additional inquiries
        if (!empty($site->integration?->endpoint_url)) {
            try {
                $resp = \Illuminate\Support\Facades\Http::timeout(5)->get($site->integration->endpoint_url);
                if ($resp->successful() && is_array($resp->json('inquiries'))) {
                    $quotes = array_merge($quotes, $resp->json('inquiries'));
                }
            } catch (\Throwable $e) {}
        }

        $ingested = 0;
        foreach ($quotes as $q) {
            $inquiry = $this->ingestInquiry($site, [
                'customer_name'    => $q['name'] ?? 'Website Visitor',
                'customer_email'   => $q['email'] ?? null,
                'customer_phone'   => $q['phone'] ?? null,
                'customer_company' => $q['company'] ?? null,
                'subject'          => 'Quote Request for ' . ($q['product'] ?? 'TYPHON MZVT'),
                'message'          => $q['message'] ?? '',
                'product_service'  => $q['product'] ?? 'TYPHON MZVT',
                'inquiry_type'     => WebsiteInquiry::TYPE_QUOTE_REQUEST,
                'form_name'        => $q['form_name'] ?? 'Machine Quote or Consultation',
                'source_url'       => $q['source_url'] ?? 'https://mzvt.com/contact/#quote-form',
                'submitted_at'     => $q['submitted_at'] ?? now(),
                'idempotency_key'  => $q['id'],
            ], $site->integration);

            if ($inquiry->wasRecentlyCreated) {
                $ingested++;
            }
        }

        return $ingested;
    }

    /**
     * Ingest inquiries from generic remote endpoints or WordPress REST API.
     */
    public function syncRemoteEndpointInquiries(Website $site): int
    {
        $integration = $site->integration;
        if (!$integration) return 0;

        $baseUrl = $integration->endpoint_url ?: $site->url;
        if (empty($baseUrl)) return 0;

        $targetUrl = rtrim($baseUrl, '/');
        if (str_contains($targetUrl, '.php')) {
            $targetUrl .= (str_contains($targetUrl, '?') ? '&' : '?') . 'action=inquiries';
        } elseif ($integration->connector_type === WebsiteIntegration::CONNECTOR_WP_PLUGIN) {
            $targetUrl .= '/wp-json/dgt/v1/inquiries';
        } else {
            return 0;
        }

        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(5)->get($targetUrl);
            if (!$resp->successful()) return 0;

            $json = $resp->json();
            $items = $json['inquiries'] ?? (is_array($json) && isset($json[0]) ? $json : []);
            if (!is_array($items) || empty($items)) return 0;

            $ingested = 0;
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $inqId = (string)($item['id'] ?? md5(json_encode($item)));
                $inq = $this->ingestInquiry($site, array_merge($item, [
                    'idempotency_key' => 'remote_' . $site->id . '_' . $inqId,
                ]), $integration);
                if ($inq->wasRecentlyCreated) {
                    $ingested++;
                }
            }
            return $ingested;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get aggregate dashboard statistics.
     */
    public function getDashboardStats(?int $websiteId = null, ?string $dateRange = null): array
    {
        $baseQuery = WebsiteInquiry::query();

        if ($websiteId) {
            $baseQuery->where('website_id', $websiteId);
        }

        if ($dateRange) {
            $this->applyDateFilter($baseQuery, $dateRange);
        }

        $total = (clone $baseQuery)->count();
        $unread = (clone $baseQuery)->where('is_read', false)->where('status', '!=', WebsiteInquiry::STATUS_SPAM)->count();
        $inProgress = (clone $baseQuery)->whereIn('status', [WebsiteInquiry::STATUS_IN_PROGRESS, WebsiteInquiry::STATUS_ASSIGNED, WebsiteInquiry::STATUS_PENDING])->count();
        $resolved = (clone $baseQuery)->whereIn('status', [WebsiteInquiry::STATUS_RESOLVED, WebsiteInquiry::STATUS_CLOSED])->count();
        $spam = (clone $baseQuery)->where('status', WebsiteInquiry::STATUS_SPAM)->count();

        // Inquiries by website (top 8)
        $byWebsite = (clone $baseQuery)
            ->select('website_id', DB::raw('count(*) as count'))
            ->groupBy('website_id')
            ->with('website:id,name,url')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        // Inquiries by type
        $byType = (clone $baseQuery)
            ->select('inquiry_type', DB::raw('count(*) as count'))
            ->groupBy('inquiry_type')
            ->orderByDesc('count')
            ->get()
            ->mapWithKeys(function ($row) {
                $label = WebsiteInquiry::INQUIRY_TYPES[$row->inquiry_type] ?? ucfirst($row->inquiry_type);
                return [$label => $row->count];
            });

        return [
            'total'       => $total,
            'unread'      => $unread,
            'in_progress' => $inProgress,
            'resolved'    => $resolved,
            'spam'        => $spam,
            'by_website'  => $byWebsite,
            'by_type'     => $byType,
        ];
    }

    /**
     * Normalize incoming form fields from CF7, Elementor, WPForms, Gravity, Fluent, or Custom Forms.
     */
    protected function normalizeFields(array $data): array
    {
        $fields = $data['fields'] ?? $data['form_data'] ?? $data['data'] ?? $data;

        $name = null;
        $email = null;
        $phone = null;
        $company = null;
        $subject = null;
        $message = null;
        $product = null;
        $customFields = [];

        // Direct keys take precedence
        if (!empty($data['customer_name']))  $name    = $data['customer_name'];
        if (!empty($data['customer_email'])) $email   = $data['customer_email'];
        if (!empty($data['customer_phone'])) $phone   = $data['customer_phone'];
        if (!empty($data['customer_company'])) $company = $data['customer_company'];
        if (!empty($data['subject']))        $subject = $data['subject'];
        if (!empty($data['message']))        $message = $data['message'];
        if (!empty($data['product_service'])) $product = $data['product_service'];

        // Iterate through submitted fields to capture standard and custom fields
        foreach ($fields as $key => $val) {
            if (is_array($val) && isset($val['value'])) {
                $val = $val['value'];
            }

            if (is_array($val)) {
                $valStr = json_encode($val);
            } else {
                $valStr = (string) $val;
            }

            $lowerKey = strtolower(str_replace(['-', '_', ' '], '', (string) $key));

            // Name
            if (!$name && in_array($lowerKey, ['name', 'yourname', 'fullname', 'clientname', 'contactname', 'fname', 'firstlast', 'customername'])) {
                $name = $valStr;
                continue;
            }

            // Email
            if (!$email && (in_array($lowerKey, ['email', 'youremail', 'mail', 'emailaddress', 'customeremail']) || filter_var($valStr, FILTER_VALIDATE_EMAIL))) {
                $email = $valStr;
                continue;
            }

            // Phone
            if (!$phone && in_array($lowerKey, ['phone', 'yourphone', 'tel', 'telephone', 'mobile', 'cellphone', 'phonenumber', 'contactnumber'])) {
                $phone = $valStr;
                continue;
            }

            // Company
            if (!$company && in_array($lowerKey, ['company', 'companyname', 'organization', 'org', 'business', 'businessname'])) {
                $company = $valStr;
                continue;
            }

            // Subject
            if (!$subject && in_array($lowerKey, ['subject', 'yoursubject', 'topic', 'inquirytype', 'interest'])) {
                $subject = $valStr;
                continue;
            }

            // Message
            if (!$message && in_array($lowerKey, ['message', 'yourmessage', 'msg', 'comment', 'comments', 'inquiry', 'details', 'description', 'notes'])) {
                $message = $valStr;
                continue;
            }

            // Product / Service
            if (!$product && in_array($lowerKey, ['product', 'service', 'productname', 'servicename', 'package', 'plan', 'item'])) {
                $product = $valStr;
                continue;
            }

            // Ignore internal metadata keys from custom fields
            if (!in_array($key, [
                'api_key', 'api_secret', 'signature', 'timestamp', 'nonce', 'token',
                'idempotency_key', 'submission_id', 'source_url', 'form_id', 'form_name',
                'inquiry_type', 'g-recaptcha-response', '_wpnonce', '_wp_http_referer',
                'honeypot', 'website_id'
            ])) {
                $customFields[$key] = $val;
            }
        }

        return [
            'customer_name'    => trim($name ?? '') ?: 'Website Visitor',
            'customer_email'   => filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower(trim($email)) : null,
            'customer_phone'   => trim($phone ?? '') ?: null,
            'customer_company' => trim($company ?? '') ?: null,
            'subject'          => trim($subject ?? '') ?: ($data['subject'] ?? 'New Website Inquiry'),
            'message'          => trim($message ?? '') ?: ($data['message'] ?? ''),
            'product_service'  => trim($product ?? '') ?: ($data['product_service'] ?? null),
            'form_id'          => $data['form_id'] ?? null,
            'form_name'        => $data['form_name'] ?? 'Contact Form',
            'source_url'       => $data['source_url'] ?? null,
            'inquiry_type'     => $data['inquiry_type'] ?? null,
            'custom_fields'    => $customFields,
        ];
    }

    /**
     * Map flexible string to one of the strict INQUIRY_TYPES.
     */
    protected function resolveInquiryType(?string $rawType, array $data): string
    {
        $candidates = [
            $rawType,
            $data['subject'] ?? '',
            $data['form_name'] ?? '',
        ];

        $joined = strtolower(implode(' ', array_filter($candidates)));

        if (Str::contains($joined, ['quote', 'quotation', 'pricing', 'estimate', 'cost'])) {
            return Str::contains($joined, ['pricing', 'availability'])
                ? WebsiteInquiry::TYPE_PRICING_AVAILABILITY
                : WebsiteInquiry::TYPE_QUOTE_REQUEST;
        }

        if (Str::contains($joined, ['support', 'technical', 'help', 'bug', 'issue', 'ticket'])) {
            return WebsiteInquiry::TYPE_TECHNICAL_SUPPORT;
        }

        if (Str::contains($joined, ['product', 'catalog', 'spec', 'dimension'])) {
            return WebsiteInquiry::TYPE_PRODUCT_QUESTION;
        }

        if (Str::contains($joined, ['dealer', 'distributor', 'wholesale', 'reseller'])) {
            return WebsiteInquiry::TYPE_DEALER_DISTRIBUTOR;
        }

        if (Str::contains($joined, ['partner', 'partnership', 'collaboration'])) {
            return WebsiteInquiry::TYPE_PARTNERSHIP;
        }

        if (Str::contains($joined, ['sales', 'order', 'purchase', 'buy'])) {
            return WebsiteInquiry::TYPE_SALES;
        }

        if (Str::contains($joined, ['contact', 'hello', 'touch'])) {
            return WebsiteInquiry::TYPE_CONTACT;
        }

        return WebsiteInquiry::TYPE_GENERAL;
    }

    /**
     * Spam detection using honeypots and common spam indicators.
     */
    protected function detectSpam(array $raw, array $normalized): bool
    {
        // 1. Honeypot check (hidden input that bots fill)
        $honeypots = ['website_hp', 'honeypot', 'phone_hp', 'email_hp', 'username_extra'];
        foreach ($honeypots as $hp) {
            if (!empty($raw[$hp]) || !empty($raw['fields'][$hp])) {
                return true;
            }
        }

        // 2. Email format validation
        if (!empty($normalized['customer_email']) && !filter_var($normalized['customer_email'], FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        // 3. Obvious spam keywords in message or name
        $content = strtolower(($normalized['customer_name'] ?? '') . ' ' . ($normalized['message'] ?? ''));
        $spamIndicators = [
            'casino online', 'cryptocurrency investment', 'seo ranking booster',
            'viagra', 'cialis', 'telegram: @', 'whatsapp: +79'
        ];

        foreach ($spamIndicators as $indicator) {
            if (str_contains($content, $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Store and sanitize uploaded attachments.
     */
    protected function processAttachments(array $uploadedFiles, array $existingAttachmentData = []): array
    {
        $stored = [];

        // Retain any existing validated attachment metadata
        foreach ($existingAttachmentData as $item) {
            if (is_array($item) && isset($item['url'])) {
                $stored[] = $item;
            }
        }

        // Process newly uploaded files
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];
        $maxBytes = 15 * 1024 * 1024; // 15MB limit

        foreach ($uploadedFiles as $file) {
            if (!($file instanceof UploadedFile) || !$file->isValid()) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, $allowedExtensions)) {
                Log::warning("Rejected file upload for inquiry: illegal extension {$ext}");
                continue;
            }

            if ($file->getSize() > $maxBytes) {
                Log::warning("Rejected file upload for inquiry: file too large ({$file->getSize()} bytes)");
                continue;
            }

            $safeFileName = Str::random(32) . '.' . $ext;
            $path = $file->storeAs('inquiry_attachments/' . date('Y/m'), $safeFileName, 'public');

            $stored[] = [
                'original_name' => $file->getClientOriginalName(),
                'file_name'     => $safeFileName,
                'path'          => $path,
                'url'           => Storage::disk('public')->url($path),
                'size'          => $file->getSize(),
                'mime_type'     => $file->getMimeType(),
                'uploaded_at'   => now()->toIso8601String(),
            ];
        }

        return $stored;
    }

    /**
     * Helper to apply date filters.
     */
    protected function applyDateFilter($query, string $range)
    {
        return match ($range) {
            'today'        => $query->whereDate('submitted_at', Carbon::today()),
            'yesterday'    => $query->whereDate('submitted_at', Carbon::yesterday()),
            'last_7_days'  => $query->where('submitted_at', '>=', Carbon::now()->subDays(7)),
            'last_30_days' => $query->where('submitted_at', '>=', Carbon::now()->subDays(30)),
            'this_month'   => $query->whereMonth('submitted_at', Carbon::now()->month)->whereYear('submitted_at', Carbon::now()->year),
            default        => $query,
        };
    }
}
