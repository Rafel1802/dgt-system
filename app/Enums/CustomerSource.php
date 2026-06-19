<?php

namespace App\Enums;

enum CustomerSource: string
{
    case Website    = 'website';
    case Referral   = 'referral';
    case Ebay       = 'ebay';
    case SocialMedia = 'social_media';
    case ColdCall   = 'cold_call';
    case WalkIn     = 'walk_in';
    case Email      = 'email';
    case Trade      = 'trade_show';
    case Other      = 'other';

    public function label(): string
    {
        return match($this) {
            self::Website     => 'Website',
            self::Referral    => 'Referral',
            self::Ebay        => 'eBay',
            self::SocialMedia => 'Social Media',
            self::ColdCall    => 'Cold Call',
            self::WalkIn      => 'Walk In',
            self::Email       => 'Email Campaign',
            self::Trade       => 'Trade Show',
            self::Other       => 'Other',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Website     => '🌐',
            self::Referral    => '👥',
            self::Ebay        => '🛒',
            self::SocialMedia => '📱',
            self::ColdCall    => '📞',
            self::WalkIn      => '🚶',
            self::Email       => '📧',
            self::Trade       => '🏢',
            self::Other       => '❓',
        };
    }
}
