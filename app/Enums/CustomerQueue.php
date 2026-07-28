<?php

namespace App\Enums;

/**
 * Cross-department workflow queue a customer can be routed to based on
 * feedback (see CustomerController::routeToQueue()). Independent of the
 * per-domain status machines (WebsiteLeadStatus, EbayCustomerRecord tab_type,
 * TechSupportCase, ShipmentCustomer status) — this only tracks handoffs
 * between departments and their history (customer_workflow_logs).
 */
enum CustomerQueue: string
{
    case Technical = 'technical';
    case Logistics = 'logistics';
    case Sales     = 'sales';
    case FollowUp  = 'follow_up';

    public function label(): string
    {
        return match ($this) {
            self::Technical => 'Technical Queue',
            self::Logistics => 'Logistics Queue',
            self::Sales     => 'Sales Queue',
            self::FollowUp  => 'Follow-up Queue',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Technical => 'badge-amber',
            self::Logistics => 'badge-sky',
            self::Sales     => 'badge-emerald',
            self::FollowUp  => 'badge-indigo',
        };
    }

    /** Roles to notify when a customer is routed into this queue. */
    public function notifyRoles(): array
    {
        return match ($this) {
            self::Technical => ['tech-support'],
            self::Logistics => ['logistic-team', 'logistic-supervisor'],
            self::Sales     => ['sales-crm'],
            self::FollowUp  => ['sales-crm', 'ebay-team'],
        };
    }

    /** Maps the spec's example feedback categories to a target queue. */
    public static function fromFeedbackCategory(string $category): ?self
    {
        return match ($category) {
            'Technical Issue'    => self::Technical,
            'Delivery Issue'     => self::Logistics,
            'Sales Inquiry'      => self::Sales,
            'General Follow-up'  => self::FollowUp,
            default              => self::tryFrom($category),
        };
    }
}
