<?php

namespace App\Services;

use App\Models\Chat\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PusherBeamsService
{
    protected string $instanceId;
    protected ?string $secretKey;

    public function __construct()
    {
        $this->instanceId = config('services.pusher_beams.instance_id', '9bc62035-82e2-48e0-8818-3355c2a76a10');
        $this->secretKey  = config('services.pusher_beams.secret_key');
    }

    public function isConfigured(): bool
    {
        return !empty($this->instanceId);
    }

    public function hasSecretKey(): bool
    {
        return !empty($this->secretKey);
    }

    /**
     * Publish a web push notification to a list of interests.
     */
    public function publishToInterests(array $interests, string $title, string $body, ?string $deepLink = null, ?string $icon = null): bool
    {
        if (!$this->hasSecretKey()) {
            Log::debug("Pusher Beams publish skipped: PUSHER_BEAMS_SECRET_KEY not set.");
            return false;
        }

        $url = "https://{$this->instanceId}.pushnotifications.pusher.com/publish_api/v1/instances/{$this->instanceId}/publishes";

        $payload = [
            'interests' => array_values(array_unique($interests)),
            'web' => [
                'notification' => [
                    'title'     => $title,
                    'body'      => $body,
                    'icon'      => $icon ?: asset('favicon.ico'),
                    'deep_link' => $deepLink ?: url('/chat'),
                ],
            ],
        ];

        try {
            $resp = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$this->secretKey}",
            ])->timeout(1.2)->connectTimeout(0.8)->post($url, $payload);

            if ($resp->successful()) {
                Log::info("Pusher Beams publish succeeded for interests: " . implode(', ', $interests));
                return true;
            } else {
                Log::warning("Pusher Beams publish error: " . $resp->body());
                return false;
            }
        } catch (\Throwable $e) {
            Log::warning("Pusher Beams publish exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Dispatch push notification for a new chat message to recipient members.
     */
    public function notifyChatMessage(ChatMessage $message): void
    {
        if (!$this->hasSecretKey()) return;

        $conv = $message->conversation;
        if (!$conv) return;

        $sender = $message->user;
        $senderName = $sender?->name ?? 'DGT Chat';
        $body = $message->content ?: ($message->attachments()->exists() ? 'Sent an attachment' : 'New message');

        // Collect recipient user interests
        $memberIds = $conv->members()
            ->where('user_id', '!=', $message->user_id)
            ->pluck('user_id')
            ->map(fn($id) => "user-{$id}")
            ->toArray();

        if (empty($memberIds)) return;

        $this->publishToInterests(
            $memberIds,
            $senderName,
            $body,
            url('/chat?conversation=' . $conv->id),
            $sender?->avatar_url ? url($sender->avatar_url) : asset('favicon.ico')
        );
    }

    /**
     * Dispatch push notification for a new website inquiry / quote.
     */
    public function notifyNewInquiry(\App\Models\WebsiteInquiry $inquiry): void
    {
        $siteName = $inquiry->website?->name ?: 'Website';
        $title = "🔔 New Inquiry: {$siteName}";
        $customer = $inquiry->customer_name ?: 'Visitor';
        $subject = $inquiry->subject ?: 'New quote / contact request';
        $body = "{$customer}: {$subject}";

        $this->publishToInterests(
            ['dgt-all', 'hello'],
            $title,
            $body,
            url('/websites/inquiries')
        );
    }
}
