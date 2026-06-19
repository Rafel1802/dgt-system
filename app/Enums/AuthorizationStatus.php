<?php

namespace App\Enums;

enum AuthorizationStatus: string
{
    case Pending     = 'pending';
    case Approved    = 'approved';
    case Rejected    = 'rejected';
    case Negotiation = 'negotiation';

    public function label(): string
    {
        return match($this) {
            self::Pending     => 'Pending Review',
            self::Approved    => 'Approved',
            self::Rejected    => 'Rejected',
            self::Negotiation => 'Need Negotiation',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Pending     => 'badge-amber',
            self::Approved    => 'badge-emerald',
            self::Rejected    => 'badge-rose',
            self::Negotiation => 'badge-indigo',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending     => '#f59e0b',
            self::Approved    => '#10b981',
            self::Rejected    => '#ef4444',
            self::Negotiation => '#6366f1',
        };
    }
}
