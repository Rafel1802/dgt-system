<?php

namespace App\Enums;

enum WebsiteLeadStatus: string
{
    case NewLead          = 'new_lead';
    case TechnicalSupport = 'technical_support'; // Represents "New Tech Case"
    case TechInProgress   = 'tech_in_progress';
    case TechRedCase      = 'tech_red_case';
    case Resolved         = 'resolved';
    case Successful       = 'successful';
    case InTransit        = 'in_transit';
    case InDelivery       = 'in_delivery';
    case Delivered        = 'delivered';
    case Lost             = 'lost';
    case DelayedShipment  = 'delayed_shipment';
    case MachineReturn    = 'machine_return';

    public function label(): string
    {
        return match($this) {
            self::NewLead          => 'New Lead',
            self::TechnicalSupport => 'Technical Support',
            self::TechInProgress   => 'Tech Support: In Progress',
            self::TechRedCase      => 'Tech Support: High Priority',
            self::Resolved         => 'Resolved',
            self::Successful       => 'Successful Lead',
            self::InTransit        => 'In Transit (Auto-synced from Logistic)',
            self::InDelivery       => 'In Delivery (Auto-synced from Logistic)',
            self::Delivered        => 'Delivered (Auto-synced from Logistic)',
            self::Lost             => 'Lost / Not Interested',
            self::DelayedShipment  => 'Logistic Issues (Auto-synced from Logistic)',
            self::MachineReturn    => 'Machine Return (Auto-synced from Tech/Logistic)',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NewLead          => '#94a3b8', // slate
            self::TechnicalSupport => '#8b5cf6', // purple
            self::TechInProgress   => '#f59e0b', // amber
            self::TechRedCase      => '#ef4444', // red
            self::Resolved         => '#0ea5e9', // sky
            self::Successful       => '#0ea5e9', // sky
            self::InTransit        => '#3b82f6', // blue
            self::InDelivery       => '#06b6d4', // cyan
            self::Delivered        => '#22c55e', // green
            self::Lost             => '#ef4444', // red
            self::DelayedShipment  => '#ef4444', // red
            self::MachineReturn    => '#e11d48', // rose
        };
    }

    /**
     * Determines if this status is a core, manually clickable pipeline stage 
     * on the Website CRM interface.
     */
    public function isManualPipelineStage(): bool
    {
        return match($this) {
            self::NewLead,
            self::TechnicalSupport,
            self::Successful,
            self::Lost => true,
            default => false,
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::NewLead          => 'badge-slate',
            self::TechnicalSupport => 'badge-indigo',
            self::TechInProgress   => 'badge-amber',
            self::TechRedCase      => 'badge-rose',
            self::Resolved         => 'badge-emerald',
            self::Successful       => 'badge-emerald',
            self::InTransit        => 'badge-blue',
            self::InDelivery       => 'badge-cyan',
            self::Delivered        => 'badge-green',
            self::Lost             => 'badge-rose',
            self::DelayedShipment  => 'badge-amber',
            self::MachineReturn    => 'badge-rose',
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
