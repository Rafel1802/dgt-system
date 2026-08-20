<?php

namespace App\Support;

use App\Enums\CustomerQueue;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;

/**
 * Cross-domain "this customer has an issue" notifier — Tech Support status
 * changes, eBay negative feedback, and Logistic problems all affect the
 * same shared Customer, so eBay staff and Website/Sales CRM staff should
 * hear about it regardless of which department the issue originated in,
 * the same way Tech Support already hears about it via notifyTechnicians().
 */
class CrmTeamNotifier
{
    private const RECIPIENT_ROLES = ['ebay-team', 'ebay-supervisor', 'sales-crm'];

    // Admin/Supervisor tier that should always be looped in on customer
    // issues and updates, in addition to whichever team roles are relevant.
    private const ADMIN_ROLES = ['admin-crm', 'super-admin'];

    public static function notifyEbayAndSalesTeams(string $type, string $message, string $link, ?int $excludeUserId = null): void
    {
        self::sendToRoles([...self::RECIPIENT_ROLES, ...self::ADMIN_ROLES], $type, $message, $link, $excludeUserId);
    }

    public static function notifyTechCaseStatusChange(\App\Models\TechSupportCase $case, ?User $actor = null): void
    {
        $case->loadMissing(['customer.assignee', 'source']);
        $customerName = $case->customer?->name
            ?? ($case->source instanceof \App\Models\Lead ? $case->source->client_name : null)
            ?? ($case->source instanceof \App\Models\EbayCustomerRecord ? ($case->source->buyer_name ?: $case->source->username) : null)
            ?? 'Customer';
            
        $statusLabel = \App\Models\TechSupportCase::statuses()[$case->status] ?? $case->status;
        $message = "{$customerName}: {$statusLabel}"
            . ($actor ? " · {$actor->name}" : '')
            . ($case->status === \App\Models\TechSupportCase::STATUS_RED ? ' · High priority' : '');
            
        $link = route('crm.tech-support.show', $case);
        
        $roles = ['ebay-team', 'ebay-supervisor', 'sales-crm', 'tech-support', 'admin-crm', 'super-admin', 'boss', 'logistic-supervisor'];
        
        $userIds = [];
        if ($case->assigned_to) $userIds[] = $case->assigned_to;
        if ($case->created_by) $userIds[] = $case->created_by;
        
        self::sendToRoles($roles, 'tech_case_status_changed', $message, $link, $actor?->id, $userIds, $case);
    }

    /**
     * A customer record was updated — notify the CRM supervisor(s) and CRM
     * admins with what changed. Deliberately does NOT also notify the
     * assigned rep here — CustomerController::update() already does that
     * via notifyAssignedRep()/the reassignment-notify block with a more
     * specific message (reassigned/lost/plain edit); notifying them again
     * from here would double up the same edit into two cards.
     */
    public static function notifyCustomerUpdated(Customer $customer, User $actor, array $changes): void
    {
        if (empty($changes)) {
            return;
        }

        $fields = implode(', ', array_keys($changes));
        $message = sprintf(
            '%s updated customer "%s" (#%d) — changed: %s.',
            $actor->name,
            $customer->name,
            $customer->id,
            $fields
        );
        $link = route('crm.customers.show', $customer);

        $recipients = User::where('is_active', true)
            ->where(function ($q) {
                $q->role(self::ADMIN_ROLES)
                    ->orWhere(fn ($q2) => $q2->role('sales-crm')->where('crm_role', 'supervisor'));
            })
            ->get()
            ->reject(fn (User $u) => $u->id === $actor->id);

        foreach ($recipients as $recipient) {
            InstantNotifier::send($recipient, new GenericDatabaseNotification([
                'module'       => 'crm',
                'type'         => 'customer_updated',
                'customer_id'  => $customer->id,
                'message'      => $message,
                'link'         => $link,
                'updated_by'   => $actor->name,
                'updated_at'   => now()->toDateTimeString(),
                'changes'      => $changes,
            ]));
        }
    }

    /**
     * A customer was routed to a different department queue based on
     * feedback — notify the target queue's team, the assigned rep, and
     * Admin/Supervisor.
     */
    public static function notifyQueueRouted(Customer $customer, User $actor, CustomerQueue $queue, ?string $reason): void
    {
        $message = sprintf(
            '%s routed "%s" (#%d) to the %s%s',
            $actor->name,
            $customer->name,
            $customer->id,
            $queue->label(),
            $reason ? " — {$reason}" : '.'
        );

        self::sendToRoles([...$queue->notifyRoles(), ...self::ADMIN_ROLES], 'customer_routed', $message, route('crm.customers.show', $customer), $actor->id);
    }

    public static function notifyStatusChange(\Illuminate\Database\Eloquent\Model $record, string $previousStatus, string $newStatus, User $actor, string $teamName): void
    {
        $roles = self::ADMIN_ROLES;
        $userIds = [];
        $link = '#';
        $message = '';

        if ($record instanceof \App\Models\TechSupportCase) {
            $roles = array_merge($roles, ['tech-support']);
            $link = route('crm.tech-support.show', $record);
            
            $prevLabel = \App\Models\TechSupportCase::statuses()[$previousStatus] ?? $previousStatus;
            $newLabel = \App\Models\TechSupportCase::statuses()[$newStatus] ?? $newStatus;
            
            $message = sprintf(
                '%s (%s) changed Tech Support Case #%d status from "%s" to "%s".',
                $actor->name, $teamName, $record->id, $prevLabel, $newLabel
            );
            
            if ($record->assigned_to) $userIds[] = $record->assigned_to;
            if ($record->created_by) $userIds[] = $record->created_by;
            
        } elseif ($record instanceof \App\Models\ShipmentCustomer) {
            $roles = array_merge($roles, ['logistic-supervisor', 'sales-crm']);
            $link = $record->shipment_id ? route('crm.logistics.shipments.show', $record->shipment_id) : route('crm.logistics.process-trucking');
            
            $prevLabel = \App\Models\ShipmentCustomer::statuses()[$previousStatus] ?? $previousStatus;
            $newLabel = \App\Models\ShipmentCustomer::statuses()[$newStatus] ?? $newStatus;
            
            $message = sprintf(
                '%s (%s) changed Logistic status for "%s" from "%s" to "%s".',
                $actor->name, $teamName, $record->recipient_name ?? 'Unknown', $prevLabel, $newLabel
            );
            
            if ($record->handler_id) $userIds[] = $record->handler_id;
        } elseif ($record instanceof \App\Models\Lead || $record instanceof \App\Models\Customer || $record instanceof \App\Models\EbayCustomerRecord) {
            $roles = array_merge($roles, ['sales-crm', 'ebay-supervisor']);
            
            $link = $record instanceof \App\Models\Lead 
                ? route('crm.website.show', $record) 
                : ($record instanceof \App\Models\Customer ? route('crm.customers.show', $record) : route('crm.ebay.customers.show', $record));
                
            $prevLabel = $previousStatus instanceof \BackedEnum ? $previousStatus->label() : (\App\Enums\WebsiteLeadStatus::tryFrom($previousStatus)?->label() ?? $previousStatus);
            $newLabel = $newStatus instanceof \BackedEnum ? $newStatus->label() : (\App\Enums\WebsiteLeadStatus::tryFrom($newStatus)?->label() ?? $newStatus);
            
            $name = $record->name ?? ($record->client_name ?? ($record->buyer_name ?? 'Unknown'));
            $message = sprintf(
                '%s (%s) changed status for "%s" from "%s" to "%s".',
                $actor->name, $teamName, $name, $prevLabel, $newLabel
            );
            
            if (isset($record->handled_by)) $userIds[] = $record->handled_by;
            if (isset($record->assigned_to)) $userIds[] = $record->assigned_to;
            if (isset($record->user_id)) $userIds[] = $record->user_id;
        }

        self::sendToRoles($roles, 'status_changed', $message, $link, $actor->id, $userIds, $record);

        // Also broadcast the live UI update event to anyone looking at the page
        $type = 'customer';
        if ($record instanceof \App\Models\Lead) $type = 'website';
        elseif ($record instanceof \App\Models\EbayCustomerRecord) $type = 'ebay';
        elseif ($record instanceof \App\Models\TechSupportCase) $type = 'tech';
        elseif ($record instanceof \App\Models\ShipmentCustomer) $type = 'logistic';

        event(new \App\Events\CustomerStatusUpdatedLive(
            $record->id,
            $newLabel,
            null, // color can be determined by frontend or added later if needed
            $actor->name,
            $teamName,
            $type
        ));
    }

    public static function notifyHandlerChange(\Illuminate\Database\Eloquent\Model $record, User $newHandler, User $actor, string $teamName): void
    {
        $link = '#';
        if ($record instanceof \App\Models\EbayCustomerRecord) {
            $link = route('crm.ebay.customers.show', $record);
        } elseif ($record instanceof \App\Models\Customer) {
            $link = route('crm.customers.show', $record);
        } elseif ($record instanceof \App\Models\Lead) {
            $link = route('crm.website.show', $record);
        }
        
        $name = $record->name ?? ($record->client_name ?? ($record->buyer_name ?? 'Unknown'));
        $message = sprintf(
            '%s (%s) assigned you to handle "%s".',
            $actor->name, $teamName, $name
        );
        
        // Notify the specific new handler, plus admins
        self::sendToRoles(self::ADMIN_ROLES, 'handler_changed', $message, $link, $actor->id, [$newHandler->id], $record);
    }

    public static function notifyBulkStatusChange(int $count, string $newStatus, User $actor, string $teamName, string $link): void
    {
        $roles = array_merge(self::ADMIN_ROLES, ['logistic-supervisor', 'sales-crm']);
        $newLabel = \App\Models\ShipmentCustomer::statuses()[$newStatus] ?? $newStatus;
        
        $message = sprintf(
            '%s (%s) updated %d customers to "%s".',
            $actor->name, $teamName, $count, $newLabel
        );
        
        self::sendToRoles($roles, 'status_changed', $message, $link, $actor->id);
    }

    private static function sendToRoles(array $roles, string $type, string $message, string $link, ?int $excludeUserId = null, array $userIds = [], ?\Illuminate\Database\Eloquent\Model $record = null): void
    {
        $recipients = User::where('is_active', true)
            ->where(function ($q) use ($roles, $userIds) {
                if (!empty($roles)) {
                    $q->role($roles);
                }
                if (!empty($userIds)) {
                    $q->orWhereIn('id', $userIds);
                }
            })->get();

        if ($excludeUserId) {
            $recipients = $recipients->reject(fn (User $u) => $u->id === $excludeUserId);
        }

        foreach ($recipients as $recipient) {
            $userLink = $link;
            
            if ($record) {
                if ($recipient->hasRole('tech-support')) {
                    if ($record instanceof \App\Models\Lead || $record instanceof \App\Models\Customer || $record instanceof \App\Models\EbayCustomerRecord) {
                        $techCase = $record->techSupportCase()->first();
                        if ($techCase) {
                            $userLink = route('crm.tech-support.show', $techCase);
                        }
                    }
                } elseif ($recipient->hasRole(['sales-crm', 'ebay-supervisor'])) {
                    if ($record instanceof \App\Models\TechSupportCase) {
                        $source = $record->source;
                        if ($source instanceof \App\Models\Lead) {
                            $userLink = route('crm.website.show', $source);
                        } elseif ($source instanceof \App\Models\Customer) {
                            $userLink = route('crm.customers.show', $source);
                        } elseif ($source instanceof \App\Models\EbayCustomerRecord) {
                            $userLink = route('crm.ebay.customers.show', $source);
                        }
                    }
                }
            }

            InstantNotifier::send($recipient, new GenericDatabaseNotification([
                'module'  => 'crm',
                'type'    => $type,
                'message' => $message,
                'link'    => $userLink,
            ]));
        }
    }
}
