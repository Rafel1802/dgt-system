<?php

namespace App\Enums;

enum WebsiteLeadStatus: string
{
    case NewLead          = 'new_lead';
    case Contacted        = 'contacted';
    case Nurturing        = 'nurturing';
    case TechnicalSupport = 'technical_support';
    case Successful       = 'successful';
    case InDelivery       = 'in_delivery';
    case Delivered        = 'delivered';
    case Lost             = 'lost';
    case DelayedShipment  = 'delayed_shipment';
    case MachineReturn    = 'machine_return';

    public function label(): string
    {
        return match($this) {
            self::NewLead          => 'New Lead',
            self::Contacted        => 'Contacted',
            self::Nurturing        => 'Nurturing',
            self::TechnicalSupport => 'Technical Support',
            self::Successful       => 'Successful Lead',
            self::InDelivery       => 'In Delivery',
            self::Delivered        => 'Delivered',
            self::Lost             => 'Lost / Not Interested',
            self::DelayedShipment  => 'Logistic Issues',
            self::MachineReturn    => 'Machine Return',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NewLead          => '#94a3b8',
            self::Contacted        => '#3b82f6',
            self::Nurturing        => '#8b5cf6',
            self::TechnicalSupport => '#f59e0b',
            self::Successful       => '#10b981',
            self::InDelivery       => '#06b6d4',
            self::Delivered        => '#22c55e',
            self::Lost             => '#ef4444',
            self::DelayedShipment  => '#f97316',
            self::MachineReturn    => '#64748b',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::NewLead          => 'badge-slate',
            self::Contacted        => 'badge-sky',
            self::Nurturing        => 'badge-indigo',
            self::TechnicalSupport => 'badge-amber',
            self::Successful       => 'badge-emerald',
            self::InDelivery       => 'badge-cyan',
            self::Delivered        => 'badge-green',
            self::Lost             => 'badge-rose',
            self::DelayedShipment  => 'badge-amber',
            self::MachineReturn    => 'badge-slate',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Lost]);
    }

    public static function pipeline(): array
    {
        return self::cases();
    }
}
