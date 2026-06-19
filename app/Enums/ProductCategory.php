<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Excavator  = 'excavator';
    case SkidSteer  = 'skid_steer';
    case Forklift   = 'forklift';
    case Parts      = 'parts';
    case Other      = 'other';

    public function label(): string
    {
        return match($this) {
            self::Excavator => 'Excavator',
            self::SkidSteer => 'Skid Steer',
            self::Forklift  => 'Forklift',
            self::Parts     => 'Parts',
            self::Other     => 'Other',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Excavator => '🏗️',
            self::SkidSteer => '🚜',
            self::Forklift  => '🏭',
            self::Parts     => '⚙️',
            self::Other     => '📦',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Excavator => '#f59e0b',
            self::SkidSteer => '#10b981',
            self::Forklift  => '#3b82f6',
            self::Parts     => '#8b5cf6',
            self::Other     => '#94a3b8',
        };
    }
}
