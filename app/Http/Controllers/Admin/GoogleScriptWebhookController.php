<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleScriptWebhookController extends Controller
{
    /**
     * Receive an email pushed by Google Apps Script.
     * Route: POST /webhook/google-script  (no auth middleware)
     */
    public function receive(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        // ── 1. Validate the secret key ──────────────────────────────────────
        $secret = $payload['secret'] ?? null;
        $senderEmail = $this->extractEmail($payload['account'] ?? '');

        if (! $secret || ! $senderEmail) {
            return response()->json(['error' => 'Missing secret or account'], 400);
        }

        // Find the matching Google Script account by email + secret
        $account = EmailAccount::where('provider', 'google_script')
            ->where('email_address', $senderEmail)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            // Try matching by the from-email if account email differs
            $account = EmailAccount::where('provider', 'google_script')
                ->where('is_active', true)
                ->get()
                ->first(fn($a) => hash_equals($a->password ?? '', $secret));
        }

        if (! $account || ! hash_equals($account->password ?? '', $secret)) {
            return response()->json(['error' => 'Invalid secret or account not found'], 403);
        }

        // ── 2. Parse incoming fields ────────────────────────────────────────
        $fromRaw   = $payload['from'] ?? '';
        $fromName  = $this->extractName($fromRaw);
        $fromEmail = $this->extractEmail($fromRaw);
        $toRaw     = $payload['to'] ?? '';
        $toEmails  = array_map('trim', explode(',', $toRaw));
        $messageId = $payload['message_id'] ?? null;
        $subject   = $payload['subject'] ?? '(no subject)';
        $bodyHtml  = $payload['body_html'] ?? null;
        $bodyText  = $payload['body_text'] ?? null;
        $dateStr   = $payload['date'] ?? null;
        $receivedAt = $dateStr ? \Carbon\Carbon::parse($dateStr) : now();

        // ── 3. Deduplicate by message_id ───────────────────────────────────
        $existing = EmailMessage::where('email_account_id', $account->id)
            ->where('message_id', $messageId)
            ->exists();

        if ($existing) {
            return response()->json(['status' => 'duplicate', 'message' => 'Already imported']);
        }

        // ── 4. Save the email ───────────────────────────────────────────────
        EmailMessage::create([
            'email_account_id' => $account->id,
            'message_uid'      => $messageId,
            'message_id'       => $messageId,
            'from_name'        => $fromName,
            'from_email'       => $fromEmail ?: 'unknown@example.com',
            'to_emails'        => $toEmails,
            'subject'          => $subject,
            'body_html'        => $bodyHtml,
            'body_text'        => $bodyText,
            'folder'           => 'INBOX',
            'is_read'          => false,
            'has_attachments'  => false,
            'received_at'      => $receivedAt,
        ]);

        // Update last synced timestamp
        $account->update(['last_synced_at' => now()]);

        return response()->json(['status' => 'ok', 'message' => 'Email saved']);
    }

    /** Extract the display name from "Name <email>" format */
    private function extractName(string $raw): ?string
    {
        if (preg_match('/^(.+?)\s*<.+>$/', trim($raw), $m)) {
            return trim($m[1], ' "\'');
        }
        return null;
    }

    /** Extract the email address from "Name <email>" or plain "email" */
    private function extractEmail(string $raw): ?string
    {
        if (preg_match('/<([^>]+)>/', $raw, $m)) {
            return strtolower(trim($m[1]));
        }
        $raw = trim($raw);
        return filter_var($raw, FILTER_VALIDATE_EMAIL) ? strtolower($raw) : null;
    }

    /**
     * Receive trashed email IDs to sync deletions.
     * Route: POST /webhook/google-script/sync-trash
     */
    public function syncTrash(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $secret = $payload['secret'] ?? null;
        $senderEmail = $this->extractEmail($payload['account'] ?? '');
        $trashedIds = $payload['trashed_ids'] ?? [];

        if (! $secret || ! $senderEmail) {
            return response()->json(['error' => 'Missing secret or account'], 400);
        }

        $account = EmailAccount::where('provider', 'google_script')
            ->where('email_address', $senderEmail)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            $account = EmailAccount::where('provider', 'google_script')
                ->where('is_active', true)
                ->get()
                ->first(fn($a) => hash_equals($a->password ?? '', $secret));
        }

        if (! $account || ! hash_equals($account->password ?? '', $secret)) {
            return response()->json(['error' => 'Invalid secret or account not found'], 403);
        }

        $deletedCount = 0;
        if (!empty($trashedIds)) {
            // Because Gmail API message IDs might map to our message_uid or message_id.
            // The webhook uses the Google message ID as message_uid and message_id.
            $deletedCount = EmailMessage::where('email_account_id', $account->id)
                ->whereIn('message_uid', $trashedIds)
                ->delete();
        }

        return response()->json(['status' => 'ok', 'deleted' => $deletedCount]);
    }
}
