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

        $this->logActivity($case->customer_id, 'New Technical Case', 'A new technical support case was opened.');

        $this->notifyTechnicians($case, 'New tech case' . ($case->order_id ? " · #{$case->order_id}" : ''), 'tech_case_new', auth()->id());

        event(new \App\Events\TechSupportCaseStatusUpdated($case, auth()->id()));
        \Illuminate\Support\Facades\Cache::forget('tech_support.index_stats');

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

        $this->notifyTechnicians($case, "Tech case reopened · {$ordinal} occurrence", 'tech_case_new', auth()->id());

        event(new \App\Events\TechSupportCaseStatusUpdated($case, auth()->id()));
        \Illuminate\Support\Facades\Cache::forget('tech_support.index_stats');

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
        if (empty($caseIds) || ! auth()->check()) {
            return;
        }

        $ids = array_map('intval', $caseIds);

        // Already scoped to this user's unread rows only (small set) — filter in PHP
        // so SQLite tests and MySQL both work without brittle JSON path differences.
        auth()->user()->unreadNotifications()
            ->where('data', 'like', '%tech_case_call_completed%')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->filter(fn (DatabaseNotification $n) => in_array((int) ($n->data['case_id'] ?? 0), $ids, true))
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
            $notification = new GenericDatabaseNotification([
                'module'  => 'crm',
                'type'    => $type,
                'case_id' => $case->id,
                'message' => $message,
                'link'    => route('crm.tech-support.show', $case),
            ]);

            if ($case->assigned_to) {
                if ((int) $recipient->id === (int) $case->assigned_to) {
                    InstantNotifier::send($recipient, $notification);
                }
            } else {
                InstantNotifier::send($recipient, $notification);
            }
        }
    }

    /**
     * Move a case to a new status, applying the required business rules:
     * In Progress = acknowledged (clears the "new case" notification unread
     * state), Resolved = stamps resolved_at and triggers the eBay sync.
     */
    public function changeStatus(TechSupportCase $case, string $newStatus, ?User $actor = null, ?string $note = null): TechSupportCase
    {
        $oldStatus = $case->status;
        if ($oldStatus === $newStatus) {
            return $case;
        }

        $case->status = $newStatus;

        if ($newStatus === TechSupportCase::STATUS_IN_PROGRESS && ! $case->acknowledged_at) {
            $case->acknowledged_at = now();

            // Scope by case_id first (more selective than two full-table LIKEs).
            $caseId = (int) $case->id;
            DatabaseNotification::whereNull('read_at')
                ->where(function ($q) use ($caseId) {
                    $q->where('data', 'like', '%"case_id":' . $caseId . '%')
                        ->orWhere('data', 'like', '%"case_id":"' . $caseId . '"%');
                })
                ->where('data', 'like', '%tech_case_new%')
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

        // Sync the status globally across the CRM (Customer DB, Website Leads, eBay Records)
        $this->syncToSources($case, $newStatus);

        if ($newStatus === TechSupportCase::STATUS_RESOLVED) {
            $this->logActivity($case->customer_id, 'Case Resolved', 'Technical support case marked resolved.');

            if ($note) {
                TechSupportCaseLog::create([
                    'tech_support_case_id' => $case->id,
                    'user_id'              => $actor?->id,
                    'type'                 => TechSupportCaseLog::TYPE_RESOLVED,
                    'note'                 => $note,
                ]);
            }
        }
        // eBay and Website/Sales CRM staff both regularly deal with this
        // same customer outside of Tech Support — a status change here
        // (especially Red Case or Resolved) is worth them knowing about
        // without needing to separately check the Tech Support queue.
        CrmTeamNotifier::notifyTechCaseStatusChange($case, $actor);

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

        // Notify after the HTTP response so Request Call feels instant.
        // Recipient set matches prior logic: sales-crm OR website canDeleteCrmRecords
        // (super-admin, boss, admin-crm / CRM supervisor) — queried by role, not
        // "load every active user then filter in PHP".
        $caseId = $case->id;
        $orderId = $case->order_id;
        $assigneeId = $case->assigned_to;
        $callRequestId = $callRequest->id;
        $requestedAt = now()->toIso8601String();
        $caseLink = route('crm.tech-support.show', $case);
        $callListLink = route('crm.website.call-requests.index');

        $notify = function () use (
            $caseId, $orderId, $assigneeId, $callRequestId, $customerName,
            $note, $requestedAt, $caseLink, $callListLink
        ) {
            if ($assigneeId) {
                $assignee = User::find($assigneeId);
                if ($assignee) {
                    InstantNotifier::send($assignee, new GenericDatabaseNotification([
                        'module'        => 'crm',
                        'type'          => 'tech_case_call_request',
                        'case_id'       => $caseId,
                        'message'       => "Call requested · {$customerName}" . ($orderId ? " · #{$orderId}" : ''),
                        'customer_name' => $customerName,
                        'order_id'      => $orderId,
                        'requested_at'  => $requestedAt,
                        'link'          => $caseLink,
                    ]));
                }
            }

            // Website CRM + supervisors who canDeleteCrmRecords('website').
            $recipients = User::role(['sales-crm', 'super-admin', 'boss', 'admin-crm'])
                ->where('is_active', true)
                ->get()
                ->unique('id');

            $shortNote = \Illuminate\Support\Str::limit(trim($note), 48, '…');
            foreach ($recipients as $recipient) {
                InstantNotifier::send($recipient, new GenericDatabaseNotification([
                    'module'          => 'crm',
                    'type'            => 'call_request_new',
                    'call_request_id' => $callRequestId,
                    'message'         => "Call request · {$customerName}" . ($shortNote !== '' ? " · {$shortNote}" : ''),
                    'customer_name'   => $customerName,
                    'requested_at'    => $requestedAt,
                    'link'            => $callListLink,
                ]));
            }
        };

        if (app()->runningUnitTests()) {
            $notify();
        } else {
            dispatch($notify)->afterResponse();
        }

        $this->logActivity($case->customer_id, 'Request Call', "Call requested for {$customerName}.");
        Cache::forget('crm.lookup.pending_call_requests');

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

        $shortOutcome = \Illuminate\Support\Str::limit(trim($note), 40, '…');
        $message = 'Call done' . ($actor ? " · {$actor->name}" : '')
            . ($shortOutcome !== '' ? " · {$shortOutcome}" : '');

        // Fan-out after the HTTP response so "Mark Called" returns immediately
        // instead of waiting for N InstantNotifier sends (DB + Pusher) to every
        // tech-support user. Same recipients as before, minus the actor (they
        // just performed the action — no need to notify themselves).
        $caseId = $case->id;
        $assigneeId = $case->assigned_to;
        $actorId = $actor?->id;
        $link = route('crm.tech-support.show', $case);

        $notify = function () use ($caseId, $assigneeId, $actorId, $message, $link) {
            $case = TechSupportCase::find($caseId);
            if (! $case) {
                return;
            }

            $exclude = array_values(array_filter([$assigneeId, $actorId]));

            if ($assigneeId && $assigneeId !== $actorId) {
                $assignee = User::find($assigneeId);
                if ($assignee) {
                    InstantNotifier::send($assignee, new GenericDatabaseNotification([
                        'module'  => 'crm',
                        'type'    => 'tech_case_call_completed',
                        'case_id' => $caseId,
                        'message' => $message,
                        'link'    => $link,
                    ]));
                }
            }

            // Wider tech team, excluding assignee (already notified) and actor.
            $recipients = User::role('tech-support')->where('is_active', true)->get();
            if (! empty($exclude)) {
                $recipients = $recipients->reject(fn (User $u) => in_array($u->id, $exclude, true));
            }
            foreach ($recipients as $recipient) {
                InstantNotifier::send($recipient, new GenericDatabaseNotification([
                    'module'  => 'crm',
                    'type'    => 'tech_case_call_completed',
                    'case_id' => $caseId,
                    'message' => $message,
                    'link'    => $link,
                ]));
            }
        };

        if (app()->runningUnitTests()) {
            $notify();
        } else {
            dispatch($notify)->afterResponse();
        }

        Cache::forget('crm.lookup.pending_call_requests');
        Cache::forget('unread_call_completed_' . ($actorId ?? 0));
    }

    /**
     * Internal-only eBay sync: this app has no outbound eBay marketplace API,
     * so "syncing" means flipping the linked EbayCustomerRecord's own
     * tech_resolved flag (the field that already represents this exact
     * concept) rather than calling an external service.
     */
    public function syncToSources(TechSupportCase $case, string $status): void
    {
        // 1. Sync to Customer DB
        if ($case->customer) {
            $customerStatus = \App\Enums\CustomerStatus::tryFrom($status);
            if ($customerStatus) {
                $case->customer->update(['status' => $customerStatus]);
            }
        }

        // 2. Sync to Website Lead
        if ($case->source_type === Lead::class && $case->source) {
            $leadStatus = match ($status) {
                TechSupportCase::STATUS_NEW => WebsiteLeadStatus::TechnicalIssues,
                TechSupportCase::STATUS_IN_PROGRESS => WebsiteLeadStatus::TechnicalIssues,
                TechSupportCase::STATUS_RED => WebsiteLeadStatus::PotentialReturn,
                TechSupportCase::STATUS_RETURN_MACHINE => WebsiteLeadStatus::ApproveReturn,
                TechSupportCase::STATUS_RESOLVED => WebsiteLeadStatus::Resolve,
                default => null,
            };

            if ($leadStatus) {
                $updates = ['status' => $leadStatus];
                if ($status === TechSupportCase::STATUS_RESOLVED) {
                    $updates['tech_resolved'] = true;
                    $updates['tech_resolved_at'] = now();
                } else {
                    $updates['tech_resolved'] = false;
                    $updates['tech_resolved_at'] = null;
                }
                
                // Use updateQuietly to prevent Lead from re-creating a Tech Case via boot hooks
                $case->source->updateQuietly($updates);
                
                event(new \App\Events\CustomerStatusUpdatedLive('website', $case->source->id, $leadStatus->label(), auth()->user()?->name ?? 'System', 'Tech Support'));
            }
        }

        // 3. Sync to eBay
        $ebayRecord = null;
        if ($case->source_type === EbayCustomerRecord::class) {
            $ebayRecord = EbayCustomerRecord::find($case->source_id);
        } elseif ($case->customer) {
            $ebayRecord = app(\App\Services\CrmCustomerMatchService::class)->findEbayRecordByContact($case->customer->email, $case->customer->phone);
        }

        if ($ebayRecord) {
            $ebayTab = match ($status) {
                TechSupportCase::STATUS_RESOLVED => EbayCustomerRecord::TAB_RESOLVED,
                default => EbayCustomerRecord::TAB_TECHNICAL,
            };

            $ebayUpdates = ['tab_type' => $ebayTab];
            
            if ($status === TechSupportCase::STATUS_RESOLVED) {
                $ebayUpdates['tech_resolved'] = true;
                $ebayUpdates['tech_resolved_at'] = now();
            } else {
                $ebayUpdates['tech_resolved'] = false;
                $ebayUpdates['tech_resolved_at'] = null;
            }

            $ebayRecord->updateQuietly($ebayUpdates);

            EbayCustomerStatusHistory::create([
                'ebay_customer_record_id' => $ebayRecord->id,
                'status'                  => $ebayTab,
                'changed_by'              => auth()->id(),
                'changed_at'              => now(),
            ]);
            
            if ($status === TechSupportCase::STATUS_RESOLVED) {
                $case->updateQuietly(['ebay_synced_at' => now()]);
            } else {
                $case->updateQuietly(['ebay_synced_at' => null]);
            }

            event(new \App\Events\CustomerStatusUpdatedLive('ebay', $ebayRecord->id, EbayCustomerRecord::tabs()[$ebayTab], auth()->user()?->name ?? 'System', 'Tech Support'));
        }
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
