<?php

namespace App\Enums;

enum LeadTemperature: string
{
    case Cold = 'cold';
    case Warm = 'warm';
    case Hot  = 'hot';

    public function label(): string
    {
        return match($this) {
            self::Cold => 'Cold',
            self::Warm => 'Warm',
            self::Hot  => 'Hot',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Cold => '🧊',
            self::Warm => '🌤️',
            self::Hot  => '🔥',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Cold => 'badge-slate',
            self::Warm => 'badge-amber',
            self::Hot  => 'badge-rose',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Cold => '#94a3b8',
            self::Warm => '#f59e0b',
            self::Hot  => '#ef4444',
        };
    }
}
