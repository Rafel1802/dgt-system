<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case Lead      = 'lead';
    case Prospect  = 'prospect';
    case Active    = 'active';
    case Inactive  = 'inactive';
    case Lost      = 'lost';
    
    // Tech Support cases
    case NewCase   = 'new';
    case InProgress = 'in_progress';
    case RedCase   = 'red_case';
    case ReturnMachine = 'return_machine';
    case ReturnReceived = 'return_received';
    case Resolved  = 'resolved';
    
    // Logistics cases
    case PickupArranged = 'pickup_arranged';
    case InTransit = 'in_transit';
    case InTransitReturn = 'in_transit_return';
    case Delivered = 'delivered';
    case LogisticDelay = 'logistic_delay';

    public function label(): string
    {
        return match($this) {
            self::Lead     => 'Lead',
            self::Prospect => 'Prospect',
            self::Active   => 'Active Customer',
            self::Inactive => 'Inactive',
            self::Lost     => 'Lost',
            self::NewCase  => 'New Case',
            self::InProgress => 'In Progress',
            self::RedCase  => 'Red Case (Potential Return)',
            self::ReturnMachine => 'Return Machine',
            self::ReturnReceived => 'Return Received',
            self::Resolved => 'Resolved',
            self::PickupArranged => 'Pickup Arranged',
            self::InTransit => 'In Transit',
            self::InTransitReturn => 'Loaded (Return Machine)',
            self::Delivered => 'Delivered',
            self::LogisticDelay => 'Logistic Issue',
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
            self::NewCase  => 'badge-indigo',
            self::InProgress => 'badge-amber',
            self::RedCase  => 'badge-rose',
            self::ReturnMachine => 'badge-rose',
            self::ReturnReceived => 'badge-emerald',
            self::Resolved => 'badge-emerald',
            self::PickupArranged => 'badge-amber',
            self::InTransit => 'badge-blue',
            self::InTransitReturn => 'badge-blue',
            self::Delivered => 'badge-green',
            self::LogisticDelay => 'badge-rose',
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
            self::NewCase  => '#6366f1',
            self::InProgress => '#f59e0b',
            self::RedCase  => '#ef4444',
            self::ReturnMachine => '#e11d48',
            self::ReturnReceived => '#10b981',
            self::Resolved => '#0ea5e9',
            self::PickupArranged => '#3b82f6',
            self::InTransit => '#3b82f6',
            self::Delivered => '#22c55e',
            self::LogisticDelay => '#f59e0b',
        };
    }
}
