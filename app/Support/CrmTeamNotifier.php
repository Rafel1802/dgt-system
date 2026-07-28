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

    private static function sendToRoles(array $roles, string $type, string $message, string $link, ?int $excludeUserId = null): void
    {
        $recipients = User::role($roles)->where('is_active', true)->get();

        if ($excludeUserId) {
            $recipients = $recipients->reject(fn (User $u) => $u->id === $excludeUserId);
        }

        foreach ($recipients as $recipient) {
            InstantNotifier::send($recipient, new GenericDatabaseNotification([
                'module'  => 'crm',
                'type'    => $type,
                'message' => $message,
                'link'    => $link,
            ]));
        }
    }
}
