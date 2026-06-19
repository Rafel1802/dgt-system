<?php

namespace App\Enums;

enum LogisticStatus: string
{
    case OrderConfirmed  = 'order_confirmed';
    case ClientVerified  = 'client_verified';
    case LoadingArranged = 'loading_arranged';
    case TruckSearching  = 'truck_searching';
    case TruckConfirmed  = 'truck_confirmed';
    case ShippingStarted = 'shipping_started';
    case TrackingReceived= 'tracking_received';
    case InTransit       = 'in_transit';
    case Delivered       = 'delivered';
    case Problem         = 'problem';

    public function label(): string
    {
        return match($this) {
            self::OrderConfirmed  => 'Order Confirmed',
            self::ClientVerified  => 'Client Verified',
            self::LoadingArranged => 'Loading Arranged',
            self::TruckSearching  => 'Truck Searching',
            self::TruckConfirmed  => 'Truck Confirmed',
            self::ShippingStarted => 'Shipping Started',
            self::TrackingReceived=> 'Tracking Received',
            self::InTransit       => 'In Transit',
            self::Delivered       => 'Delivered',
            self::Problem         => 'Problem / Delay',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::OrderConfirmed  => '#94a3b8',
            self::ClientVerified  => '#3b82f6',
            self::LoadingArranged => '#8b5cf6',
            self::TruckSearching  => '#f59e0b',
            self::TruckConfirmed  => '#f97316',
            self::ShippingStarted => '#06b6d4',
            self::TrackingReceived=> '#6366f1',
            self::InTransit       => '#0ea5e9',
            self::Delivered       => '#22c55e',
            self::Problem         => '#ef4444',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::OrderConfirmed  => 'badge-slate',
            self::ClientVerified  => 'badge-sky',
            self::LoadingArranged => 'badge-indigo',
            self::TruckSearching  => 'badge-amber',
            self::TruckConfirmed  => 'badge-orange',
            self::ShippingStarted => 'badge-cyan',
            self::TrackingReceived=> 'badge-violet',
            self::InTransit       => 'badge-blue',
            self::Delivered       => 'badge-emerald',
            self::Problem         => 'badge-rose',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::OrderConfirmed  => '📋',
            self::ClientVerified  => '✅',
            self::LoadingArranged => '📦',
            self::TruckSearching  => '🔍',
            self::TruckConfirmed  => '🚛',
            self::ShippingStarted => '🚀',
            self::TrackingReceived=> '🔢',
            self::InTransit       => '🛣️',
            self::Delivered       => '🎉',
            self::Problem         => '⚠️',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Problem]);
    }
}
