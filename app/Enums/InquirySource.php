<?php

namespace App\Enums;

enum InquirySource: string
{
    case Phone    = 'phone';
    case Facebook = 'facebook';
    case Email    = 'email';
    case WhatsApp = 'whatsapp';
    case Website  = 'website';
    case Manual   = 'manual';

    public function label(): string
    {
        return match($this) {
            self::Phone    => 'Phone',
            self::Facebook => 'Facebook',
            self::Email    => 'Email',
            self::WhatsApp => 'WhatsApp',
            self::Website  => 'Website Form',
            self::Manual   => 'Manual Entry',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Phone    => '📞',
            self::Facebook => '📘',
            self::Email    => '📧',
            self::WhatsApp => '💬',
            self::Website  => '🌐',
            self::Manual   => '✏️',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Phone    => '#10b981',
            self::Facebook => '#3b82f6',
            self::Email    => '#6366f1',
            self::WhatsApp => '#22c55e',
            self::Website  => '#8b5cf6',
            self::Manual   => '#94a3b8',
        };
    }
}
