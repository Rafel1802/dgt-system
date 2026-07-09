<?php

namespace App\Services;

use App\Models\CallRequest;
use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\EbayCustomerStatusHistory;
use App\Models\Lead;
use App\Models\TechSupportCase;
use App\Models\TechSupportCaseLog;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Owns the Technical Support case lifecycle: auto-creation from Lead/eBay
 * status changes, status transitions (with their acknowledge/resolve/eBay-sync
 * side effects), follow-up logging, call requests, and call completion.
 * Every mutating action also appends a Customer::interactions() entry so the
 * customer's activity timeline stays complete across all CRM sources.
 */
class TechSupportCaseService
{
    /** Auto-create a case when a Lead/eBay record enters the Technical Support status, unless an open one already exists. */
    public function createCaseFor(Lead|EbayCustomerRecord $source): ?TechSupportCase
    {
        $alreadyOpen = TechSupportCase::where('source_type', get_class($source))
            ->where('source_id', $source->id)
            ->where('status', '!=', TechSupportCase::STATUS_RESOLVED)
            ->exists();

        if ($alreadyOpen) {
            return null;
        }

        $case = TechSupportCase::create([
            'source_type' => get_class($source),
            'source_id'   => $source->id,
            'customer_id' => $source->customer_id,
            'order_id'    => $source instanceof EbayCustomerRecord ? $source->order_id : null,
            'status'      => TechSupportCase::STATUS_NEW,
            'created_by'  => auth()->id(),
        ]);

        $this->logActivity($case->customer_id, 'Technical Case Created', 'A new technical support case was created' . ($case->order_id ? " for order {$case->order_id}." : '.'));

        foreach (User::role('tech-support')->where('is_active', true)->get() as $recipient) {
            $recipient->notify(new GenericDatabaseNotification([
                'type'    => 'tech_case_new',
                'case_id' => $case->id,
                'message' => 'New technical support case' . ($case->order_id ? " for order {$case->order_id}" : '') . '.',
                'link'    => route('crm.tech-support.show', $case),
            ]));
        }

        return $case;
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

            DatabaseNotification::where('data->case_id', $case->id)
                ->where('data->type', 'tech_case_new')
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
        }

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

    /** Raise a callback request against this case and notify the assigned technician. */
    public function requestCall(TechSupportCase $case, ?User $actor = null): CallRequest
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
            'note'         => 'Call requested for order ' . ($case->order_id ?? 'N/A'),
            'requested_by' => $actor?->id,
        ]);

        if ($case->assigned_to) {
            $case->assignee?->notify(new GenericDatabaseNotification([
                'type'          => 'tech_case_call_request',
                'case_id'       => $case->id,
                'message'       => "Call requested for {$customerName}" . ($case->order_id ? " (Order #{$case->order_id})" : '') . '.',
                'customer_name' => $customerName,
                'order_id'      => $case->order_id,
                'requested_at'  => now()->toIso8601String(),
                'link'          => route('crm.tech-support.show', $case),
            ]));
        }

        $this->logActivity($case->customer_id, 'Request Call', "Call requested for {$customerName}.");

        return $callRequest;
    }

    /** Fulfill a call request: requires a summary, logs it as a follow-up, optionally updates the case status. */
    public function completeCall(TechSupportCase $case, CallRequest $callRequest, ?User $actor, string $summary, ?string $newStatus = null): void
    {
        $callRequest->update([
            'fulfilled'    => true,
            'fulfilled_at' => now(),
            'fulfilled_by' => $actor?->id,
        ]);

        TechSupportCaseLog::create([
            'tech_support_case_id' => $case->id,
            'user_id'               => $actor?->id,
            'type'                  => TechSupportCaseLog::TYPE_CALL_COMPLETED,
            'note'                  => $summary,
        ]);

        $this->logActivity($case->customer_id, 'Call Completed', $summary);

        if ($newStatus) {
            $this->changeStatus($case, $newStatus, $actor);
        }
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
