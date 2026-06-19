<?php

namespace App\Enums;

enum CardPriority: string
{
    case Low    = 'low';
    case Medium = 'medium';
    case High   = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match($this) {
            self::Low    => 'Low',
            self::Medium => 'Medium',
            self::High   => 'High',
            self::Urgent => 'Urgent',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Low    => 'badge-slate',
            self::Medium => 'badge-sky',
            self::High   => 'badge-amber',
            self::Urgent => 'badge-rose',
        };
    }

    public function dotColor(): string
    {
        return match($this) {
            self::Low    => '#94a3b8',
            self::Medium => '#0ea5e9',
            self::High   => '#f59e0b',
            self::Urgent => '#ef4444',
        };
    }

    public function sortOrder(): int
    {
        return match($this) {
            self::Urgent => 1,
            self::High   => 2,
            self::Medium => 3,
            self::Low    => 4,
        };
    }
}
