<?php

namespace App\Enums;

enum WebsiteLeadStatus: string
{
    case NewInquiry       = 'new_inquiry';
    case Contacted        = 'contacted';
    case LostInterest     = 'lost_interest';
    case SuccessfulLead   = 'successful_lead';
    case PendingDelivery  = 'pending_delivery';
    case Loaded           = 'loaded';
    case Delivered        = 'delivered';
    case TechnicalIssues  = 'technical_issues';
    case PotentialReturn  = 'potential_return';
    case ApproveReturn    = 'approve_return';
    case ReturnReceived   = 'return_received';
    case Resolve          = 'resolve';

    public function label(): string
    {
        return match($this) {
            self::NewInquiry       => 'New Inquiry',
            self::Contacted        => 'Contacted',
            self::LostInterest     => 'Lost Interest',
            self::SuccessfulLead   => 'Successful Lead',
            self::PendingDelivery  => 'Pending Delivery',
            self::Loaded           => 'Loaded',
            self::Delivered        => 'Delivered',
            self::TechnicalIssues  => 'Technical Issues',
            self::PotentialReturn  => 'Potential Return',
            self::ApproveReturn    => 'Approve Return',
            self::ReturnReceived   => 'Return Received',
            self::Resolve          => 'Resolve',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NewInquiry       => '#94a3b8', // slate
            self::Contacted        => '#6366f1', // indigo
            self::LostInterest     => '#e11d48', // rose
            self::SuccessfulLead   => '#10b981', // emerald
            self::PendingDelivery  => '#f59e0b', // amber
            self::Loaded           => '#3b82f6', // blue
            self::Delivered        => '#22c55e', // green
            self::TechnicalIssues  => '#ef4444', // red
            self::PotentialReturn  => '#f97316', // orange
            self::ApproveReturn    => '#dc2626', // red
            self::ReturnReceived   => '#64748b', // slate
            self::Resolve          => '#10b981', // emerald
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::NewInquiry       => 'badge-slate',
            self::Contacted        => 'badge-indigo',
            self::LostInterest     => 'badge-rose',
            self::SuccessfulLead   => 'badge-emerald',
            self::PendingDelivery  => 'badge-amber',
            self::Loaded         => 'badge-blue',
            self::Delivered        => 'badge-green',
            self::TechnicalIssues  => 'badge-rose',
            self::PotentialReturn  => 'badge-orange',
            self::ApproveReturn    => 'badge-red',
            self::ReturnReceived   => 'badge-slate',
            self::Resolve          => 'badge-emerald',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::LostInterest, self::ReturnReceived, self::Resolve]);
    }

    public static function pipeline(): array
    {
        return self::cases();
    }
}
