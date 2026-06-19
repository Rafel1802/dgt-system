<?php

namespace App\Enums;

enum EbayLeadStatus: string
{
    case Inquiry             = 'inquiry';
    case OfferReceived       = 'offer_received';
    case WaitingAuthorization= 'waiting_authorization';
    case Authorized          = 'authorized';
    case Rejected            = 'rejected';
    case ConvertedLead       = 'converted_lead';
    case OrderConfirmed      = 'order_confirmed';

    public function label(): string
    {
        return match($this) {
            self::Inquiry              => 'eBay Inquiry',
            self::OfferReceived        => 'Offer Received',
            self::WaitingAuthorization => 'Waiting Authorization',
            self::Authorized           => 'Authorized',
            self::Rejected             => 'Rejected',
            self::ConvertedLead        => 'Converted Lead',
            self::OrderConfirmed       => 'Order Confirmed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Inquiry              => '#94a3b8',
            self::OfferReceived        => '#3b82f6',
            self::WaitingAuthorization => '#f59e0b',
            self::Authorized           => '#8b5cf6',
            self::Rejected             => '#ef4444',
            self::ConvertedLead        => '#10b981',
            self::OrderConfirmed       => '#22c55e',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Inquiry              => 'badge-slate',
            self::OfferReceived        => 'badge-sky',
            self::WaitingAuthorization => 'badge-amber',
            self::Authorized           => 'badge-indigo',
            self::Rejected             => 'badge-rose',
            self::ConvertedLead        => 'badge-emerald',
            self::OrderConfirmed       => 'badge-green',
        };
    }

    public function needsAuth(): bool
    {
        return $this === self::WaitingAuthorization;
    }
}
