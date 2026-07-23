<?php

namespace App\Services;

use App\Enums\WebsiteLeadStatus;
use App\Models\CallRequest;
use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\EbayCustomerStatusHistory;
use App\Models\Lead;
use App\Models\TechSupportCase;
use App\Models\TechSupportCaseLog;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Support\CrmTeamNotifier;
use App\Support\InstantNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;

/**
 * Owns the Technical Support case lifecycle: auto-creation from Lead/eBay
 * status changes, status transitions (with their acknowledge/resolve/eBay-sync
 * side effects), follow-up logging, call requests, and call completion.
 * Every mutating action also appends a Customer::interactions() entry so the
 * customer's activity timeline stays complete across all CRM sources.
 */
class TechSupportCaseService
{
    /**
     * Create (or reopen) a case when a Lead/eBay record enters the
     * Technical Support status. If an open case already exists, no-op. If
     * a resolved case already exists for this exact source, it's reopened
     * in place instead of creating a second case row — a repeat customer
     * would otherwise show up as a duplicate row on the customer list, so
     * each new occurrence is logged on the existing case's own timeline
     * instead (see reopenCase()). $note is the staff-typed reason for the
     * status change (required by WebsiteCrmController::updateStatus() for
     * Technical Support) — when given, it becomes the case's own timeline
     * entry instead of generic auto-text.
     */
    public function createCaseFor(Lead|EbayCustomerRecord $source, ?string $note = null): ?TechSupportCase
    {
        $existing = TechSupportCase::where('source_type', get_class($source))
            ->where('source_id', $source->id)
            ->latest('id')
            ->first();

        if ($existing && $existing->status !== TechSupportCase::STATUS_RESOLVED) {
            return null;
        }

        if ($existing) {
            return $this->reopenCase($existing, $source, $note);
        }

        $case = TechSupportCase::create([
            'source_type'      => get_class($source),
            'source_id'        => $source->id,
            'customer_id'      => $source->customer_id,
            'order_id'         => $source instanceof EbayCustomerRecord ? $source->order_id : null,
            'status'           => TechSupportCase::STATUS_NEW,
            'occurrence_count' => 1,
            'created_by'       => auth()->id(),
        ]);

        if ($note) {
            TechSupportCaseLog::create([
                'tech_support_case_id' => $case->id,
                'user_id'               => auth()->id(),
                'type'                  => TechSupportCaseLog::TYPE_FOLLOW_UP,
                'note'                  => $note,
            ]);
        }

        $this->logActivity($case->customer_id, 'Technical Case Created', 'A new technical support case was created' . ($case->order_id ? " for order {$case->order_id}." : '.'));

        $this->notifyTechnicians($case, 'New technical support case' . ($case->order_id ? " for order {$case->order_id}" : '') . '.');

        return $case;
    }

    /**
     * Reopen a resolved case for a repeat technical issue instead of
     * creating a second case row for the same customer — bumps
     * occurrence_count and logs the new occurrence on the case's own
     * timeline (visible on the case detail page as a "New Issue Reported"
     * entry) so support staff can see the full repeat-issue history in one
     * place. Uses the staff-typed $note when given, falling back to
     * generic text for status changes with no note UI (e.g. eBay).
     */
    private function reopenCase(TechSupportCase $case, Lead|EbayCustomerRecord $source, ?string $note = null): TechSupportCase
    {
        // The source's tech_resolved flag was set true by the *previous*
        // resolution — clear it so it accurately reflects that this
        // occurrence is unresolved again. updateQuietly() avoids
        // re-triggering the source's own "entered Technical Support" boot
        // hook, which would try to create yet another case for a source
        // that's already being reopened here.
        $source->updateQuietly([
            'tech_resolved'    => false,
            'tech_resolved_at' => null,
        ]);

        $occurrence = $case->occurrence_count + 1;

        $case->update([
            'status'           => TechSupportCase::STATUS_NEW,
            'occurrence_count' => $occurrence,
            'acknowledged_at'  => null,
            'resolved_at'      => null,
            'order_id'         => $source instanceof EbayCustomerRecord ? ($source->order_id ?? $case->order_id) : $case->order_id,
        ]);

        $ordinal = TechSupportCase::ordinal($occurrence);
        $logNote = $note
            ? "({$ordinal} occurrence) {$note}"
            : "Customer reported a new technical issue — this is the {$ordinal} occurrence for this case.";

        TechSupportCaseLog::create([
            'tech_support_case_id' => $case->id,
            'user_id'               => auth()->id(),
            'type'                  => TechSupportCaseLog::TYPE_REOPENED,
            'note'                  => $logNote,
        ]);

        $this->logActivity($case->customer_id, 'Technical Case Reopened', "New technical issue reported ({$ordinal} occurrence).");

        $this->notifyTechnicians($case, "New technical issue reported ({$ordinal} occurrence) for the same customer.");

        return $case;
    }

    /**
     * Clear the current user's unread "call completed" notifications for the
     * given cases — viewing the case itself, or the customer that case
     * belongs to (their unified profile, eBay record, or Website lead page),
     * all count as having seen the outcome. Called from every one of those
     * "view" actions, not just the case page, so the "New" badge on the Tech
     * Support list clears wherever staff actually looked at the result.
     */
    public function markCallCompletedNotificationsRead(array $caseIds): void
    {
        if (empty($caseIds)) {
            return;
        }

        auth()->user()->unreadNotifications()
            ->where('data', 'like', '%tech_case_call_completed%')
            ->get()
            ->filter(fn (DatabaseNotification $n) => in_array($n->data['case_id'] ?? null, $caseIds))
            ->each->markAsRead();

        // The sidebar badge count (layouts/app.blade.php) caches this per
        // user for 5 minutes for performance — without forgetting it here,
        // the badge would keep showing a stale unread count for up to 5
        // minutes after staff actually looked at the result, even though
        // the notifications above are now marked read.
        Cache::forget('unread_call_completed_' . auth()->id());
    }

    /** Broadcast to the whole Technical Support team. $excludeUserId skips someone who's already getting their own personal notification for this same event, so they don't see it twice. */
    private function notifyTechnicians(TechSupportCase $case, string $message, string $type = 'tech_case_new', ?int $excludeUserId = null): void
    {
        $recipients = User::role('tech-support')->where('is_active', true)->get();
        if ($excludeUserId) {
            $recipients = $recipients->reject(fn (User $u) => $u->id === $excludeUserId);
        }

        foreach ($recipients as $recipient) {
            InstantNotifier::send($recipient, new GenericDatabaseNotification([
                'module'  => 'crm',
                'type'    => $type,
                'case_id' => $case->id,
                'message' => $message,
                'link'    => route('crm.tech-support.show', $case),
            ]));
        }
    }

    /**
     * Move a case to a new status, applying the required business rules:
     * In Progress = acknowledged (clears the "new case" notification unread
     * state), Resolved = stamps resolved_at and triggers the eBay sync.
     */
    public function changeStatus(TechSupportCase $case, string $newStatus, ?User $actor = null): TechSupportCase
    {
        $oldStatus = $case->status;
        if ($oldStatus === $newStatus) {
            return $case;
        }

        $case->status = $newStatus;

        if ($newStatus === TechSupportCase::STATUS_IN_PROGRESS && ! $case->acknowledged_at) {
            $case->acknowledged_at = now();

            DatabaseNotification::where('data', 'like', '%"case_id":' . $case->id . '%')
                ->where('data', 'like', '%tech_case_new%')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        if ($newStatus === TechSupportCase::STATUS_RESOLVED) {
            $case->resolved_at = now();
        }

        $case->save();

        $labels = TechSupportCase::statuses();
        $this->logActivity(
            $case->customer_id,
            'Technical Status Changed',
            'Status changed from ' . ($labels[$oldStatus] ?? $oldStatus) . ' to ' . ($labels[$newStatus] ?? $newStatus) . ($actor ? ' by ' . $actor->name : '') . '.'
        );

        if ($newStatus === TechSupportCase::STATUS_RESOLVED) {
            $this->logActivity($case->customer_id, 'Case Resolved', 'Technical support case marked resolved.');
            $this->syncToEbay($case);
            $this->syncLeadResolved($case);
        } elseif ($oldStatus === TechSupportCase::STATUS_RESOLVED) {
            // Case reopened (Resolved → In Progress / Red Case / New) — undo
            // the eBay sync so the record shows as Technical Issues again
            // instead of staying stuck on Resolved.
            $this->revertEbaySync($case);
            $this->revertLeadResolved($case);
        }

        // eBay and Website/Sales CRM staff both regularly deal with this
        // same customer outside of Tech Support — a status change here
        // (especially Red Case or Resolved) is worth them knowing about
        // without needing to separately check the Tech Support queue.
        $customerName = $case->customer?->name
            ?? ($case->source instanceof Lead ? $case->source->client_name : null)
            ?? ($case->source instanceof EbayCustomerRecord ? ($case->source->buyer_name ?: $case->source->username) : null)
            ?? 'Customer';
        $assignedStaffName = $case->customer?->assignee?->name ?? 'Unassigned';
        $latestNote = $case->logs()->latest()->value('note');
        $priority = $newStatus === TechSupportCase::STATUS_RED ? 'High' : 'Normal';
        CrmTeamNotifier::notifyEbayAndSalesTeams(
            'tech_case_status_changed',
            "Technical case for {$customerName} changed to " . ($labels[$newStatus] ?? $newStatus) . ($actor ? " by {$actor->name}" : '') . '.'
                . " Assigned: {$assignedStaffName}. Priority: {$priority}."
                . ($latestNote ? ' Latest note: ' . \Illuminate\Support\Str::limit($latestNote, 100) . '.' : '')
                . ' ' . now()->format('d M Y, g:ia') . '.',
            route('crm.tech-support.show', $case),
            $actor?->id
        );

        return $case;
    }

    /** Append an immutable follow-up log entry, with an optional attachment. */
    public function addFollowUp(TechSupportCase $case, ?User $actor, string $note, ?UploadedFile $file = null): TechSupportCaseLog
    {
        $log = TechSupportCaseLog::create([
            'tech_support_case_id' => $case->id,
            'user_id'               => $actor?->id,
            'type'                  => TechSupportCaseLog::TYPE_FOLLOW_UP,
            'note'                  => $note,
        ]);

        if ($file) {
            $path = $file->store('tech_support_attachments', 'public');
            $log->attachments()->create([
                'uploaded_by'   => $actor?->id,
                'filename'      => basename($path),
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getClientMimeType(),
                'file_size'     => $file->getSize(),
                'disk'          => 'public',
                'path'          => $path,
            ]);
        }

        $this->logActivity($case->customer_id, 'Follow-up Added', $note);

        return $log;
    }

    /** Raise a callback request against this case and notify the assigned technician. Requires a note explaining why the call is needed, so Website CRM knows what to say. */
    public function requestCall(TechSupportCase $case, ?User $actor, string $note): CallRequest
    {
        $customerName = $case->customer?->name
            ?? ($case->source instanceof Lead ? $case->source->client_name : null)
            ?? ($case->source instanceof EbayCustomerRecord ? ($case->source->buyer_name ?: $case->source->username) : null)
            ?? 'Customer';

        $callRequest = CallRequest::create([
            'source_type'  => TechSupportCase::class,
            'source_id'    => $case->id,
            'name'         => $customerName,
            'phone'        => $case->customer?->phone,
            'note'         => $note,
            'requested_by' => $actor?->id,
        ]);

        if ($case->assigned_to && $case->assignee) {
            InstantNotifier::send($case->assignee, new GenericDatabaseNotification([
                'module'        => 'crm',
                'type'          => 'tech_case_call_request',
                'case_id'       => $case->id,
                'message'       => "Call requested for {$customerName}" . ($case->order_id ? " (Order #{$case->order_id})" : '') . '.',
                'customer_name' => $customerName,
                'order_id'      => $case->order_id,
                'requested_at'  => now()->toIso8601String(),
                'link'          => route('crm.tech-support.show', $case),
            ]));
        }

        // Website CRM is the team that actually makes the call — notify them
        // too so a new call request shows up on their sidebar bell, not just
        // logged on a page they'd have to remember to check. Also notify the
        // supervisor tier (CRM/eBay/Logistic Supervisor, boss, super-admin —
        // same set as User::canDeleteCrmRecords()) so they have the same
        // visibility into CRM staff activity as sales-crm reps, not just
        // access to the pages. unique('id') avoids double-notifying anyone
        // who qualifies under both loops (e.g. a sales-crm CRM Supervisor).
        $recipients = User::where('is_active', true)->get()
            ->filter(fn (User $u) => $u->hasRole('sales-crm') || $u->canDeleteCrmRecords('website'))
            ->unique('id');

        foreach ($recipients as $recipient) {
            InstantNotifier::send($recipient, new GenericDatabaseNotification([
                'module'        => 'crm',
                'type'          => 'call_request_new',
                'call_request_id' => $callRequest->id,
                'message'       => "New call request for {$customerName}: {$note}",
                'customer_name' => $customerName,
                'requested_at'  => now()->toIso8601String(),
                'link'          => route('crm.website.call-requests.index'),
            ]));
        }

        $this->logActivity($case->customer_id, 'Request Call', "Call requested for {$customerName}.");

        return $callRequest;
    }

    /**
     * Called by Website CRM (not Tech Support — only the team that actually
     * made the call can close it) once they've called the customer back.
     * Logs the outcome onto the case's own Follow-Up feed so Tech Support
     * sees it without needing to check the Call Requests page separately.
     */
    public function logCallCompletedOnCase(TechSupportCase $case, ?User $actor, string $note, ?string $requestNote = null): void
    {
        // Folds the original call-request reason into the same log entry —
        // the case page used to show fulfilled call requests in their own
        // separate "Call Requests" card, duplicating this Follow-Up Log
        // entry; that card is gone now, so this is the only place the
        // outcome (and the reason the call was needed) is recorded.
        $logNote = $requestNote
            ? "Re: {$requestNote}\n\nOutcome: {$note}"
            : $note;

        TechSupportCaseLog::create([
            'tech_support_case_id' => $case->id,
            'user_id'               => $actor?->id,
            'type'                  => TechSupportCaseLog::TYPE_CALL_COMPLETED,
            'note'                  => $logNote,
        ]);

        $this->logActivity($case->customer_id, 'Call Completed', $note);

        $message = 'Call completed' . ($actor ? " by {$actor->name}" : '') . ": {$note}";

        if ($case->assigned_to && $case->assignee) {
            InstantNotifier::send($case->assignee, new GenericDatabaseNotification([
                'module'  => 'crm',
                'type'    => 'tech_case_call_completed',
                'case_id' => $case->id,
                'message' => $message,
                'link'    => route('crm.tech-support.show', $case),
            ]));
        }

        // Also broadcast to the wider Technical Support team, not just the
        // specific assignee — otherwise a completed call went unnoticed
        // whenever the case had no assignee yet, and nobody besides that one
        // person ever saw it on an assigned case either. Excludes the
        // assignee since they already got the personal notification above.
        $this->notifyTechnicians($case, $message, 'tech_case_call_completed', $case->assigned_to);
    }

    /**
     * Internal-only eBay sync: this app has no outbound eBay marketplace API,
     * so "syncing" means flipping the linked EbayCustomerRecord's own
     * tech_resolved flag (the field that already represents this exact
     * concept) rather than calling an external service.
     */
    public function syncToEbay(TechSupportCase $case): void
    {
        if ($case->source_type === EbayCustomerRecord::class) {
            $ebayRecord = EbayCustomerRecord::find($case->source_id);
        } else {
            $customer = $case->customer;
            $ebayRecord = $customer
                ? app(CrmCustomerMatchService::class)->findEbayRecordByContact($customer->email, $customer->phone)
                : null;
        }

        if (! $ebayRecord) {
            return;
        }

        $ebayRecord->update([
            'tab_type'         => EbayCustomerRecord::TAB_RESOLVED,
            'tech_resolved'    => true,
            'tech_resolved_at' => now(),
        ]);

        EbayCustomerStatusHistory::create([
            'ebay_customer_record_id' => $ebayRecord->id,
            'status'                  => EbayCustomerRecord::TAB_RESOLVED,
            'changed_by'              => auth()->id(),
            'changed_at'              => now(),
        ]);

        $case->update(['ebay_synced_at' => now()]);

        $this->logActivity($case->customer_id, 'eBay Synchronization Completed', 'Linked eBay record marked resolved after case resolution.');
    }

    /**
     * Undo syncToEbay() when a resolved case is reopened: the linked eBay
     * record goes back to the Technical Issues tab and its tech_resolved
     * flag clears. Only touches the record if it's still sitting on Resolved
     * (the state syncToEbay() itself produced) — if staff have since moved
     * it to some other tab manually, that manual choice is left alone.
     * Uses updateQuietly() so this doesn't re-trigger the record's own
     * "entered Technical Issues" hook, which would otherwise try to create a
     * second case for a source that already has this one reopened.
     */
    private function revertEbaySync(TechSupportCase $case): void
    {
        if ($case->source_type === EbayCustomerRecord::class) {
            $ebayRecord = EbayCustomerRecord::find($case->source_id);
        } else {
            $customer = $case->customer;
            $ebayRecord = $customer
                ? app(CrmCustomerMatchService::class)->findEbayRecordByContact($customer->email, $customer->phone)
                : null;
        }

        if (! $ebayRecord || $ebayRecord->tab_type !== EbayCustomerRecord::TAB_RESOLVED) {
            return;
        }

        $ebayRecord->updateQuietly([
            'tab_type'         => EbayCustomerRecord::TAB_TECHNICAL,
            'tech_resolved'    => false,
            'tech_resolved_at' => null,
        ]);

        EbayCustomerStatusHistory::create([
            'ebay_customer_record_id' => $ebayRecord->id,
            'status'                  => EbayCustomerRecord::TAB_TECHNICAL,
            'changed_by'              => auth()->id(),
            'changed_at'              => now(),
        ]);

        $case->update(['ebay_synced_at' => null]);

        $this->logActivity($case->customer_id, 'eBay Synchronization Reverted', 'Case reopened — linked eBay record marked Technical Issues again.');
    }

    /**
     * When the case's source is a Website Lead, move its pipeline status to
     * Resolved and set tech_resolved — the Website CRM equivalent of
     * syncToEbay() flipping EbayCustomerRecord.tab_type/tech_resolved
     * above. A regular (non-quiet) update is fine here: moving status *away
     * from* TechnicalSupport never matches the booted() hook's "entered
     * Technical Support" condition, so this can't spawn a duplicate case.
     */
    private function syncLeadResolved(TechSupportCase $case): void
    {
        if ($case->source_type !== Lead::class || ! $case->source) {
            return;
        }

        $case->source->update([
            'status'           => WebsiteLeadStatus::Resolved,
            'tech_resolved'    => true,
            'tech_resolved_at' => now(),
        ]);
    }

    /**
     * Undo syncLeadResolved() when a resolved case is reopened: moves the
     * lead's status back to Technical Support and clears tech_resolved.
     * Only touches status if it's still sitting on the Resolved state
     * syncLeadResolved() itself produced — if staff have since moved it to
     * some other status manually, that manual choice is left alone (mirrors
     * revertEbaySync()'s tab_type !== TAB_RESOLVED guard above). Uses
     * updateQuietly() so this doesn't re-trigger the lead's own "entered
     * Technical Support" hook, which would otherwise try to create a second
     * case for a source that already has this one reopened.
     */
    private function revertLeadResolved(TechSupportCase $case): void
    {
        if ($case->source_type !== Lead::class || ! $case->source?->tech_resolved) {
            return;
        }

        $lead = $case->source;
        $updates = ['tech_resolved' => false, 'tech_resolved_at' => null];
        if ($lead->status === WebsiteLeadStatus::Resolved) {
            $updates['status'] = WebsiteLeadStatus::TechnicalSupport;
        }

        $lead->updateQuietly($updates);
    }

    private function logActivity(?int $customerId, string $subject, string $content): void
    {
        if (! $customerId) {
            return;
        }

        Customer::find($customerId)?->interactions()->create([
            'user_id'       => auth()->id(),
            'type'          => 'note',
            'subject'       => $subject,
            'content'       => $content,
            'outcome'       => 'neutral',
            'interacted_at' => now(),
        ]);
    }
}
