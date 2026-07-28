<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case Lead      = 'lead';
    case Prospect  = 'prospect';
    case Active    = 'active';
    case Inactive  = 'inactive';
    case Lost      = 'lost';

    public function label(): string
    {
        return match($this) {
            self::Lead     => 'Lead',
            self::Prospect => 'Prospect',
            self::Active   => 'Active Customer',
            self::Inactive => 'Inactive',
            self::Lost     => 'Lost',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Lead     => 'badge-sky',
            self::Prospect => 'badge-indigo',
            self::Active   => 'badge-emerald',
            self::Inactive => 'badge-slate',
            self::Lost     => 'badge-rose',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Lead     => '#0ea5e9',
            self::Prospect => '#6366f1',
            self::Active   => '#10b981',
            self::Inactive => '#94a3b8',
            self::Lost     => '#ef4444',
        };
    }
}
