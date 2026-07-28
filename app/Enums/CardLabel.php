<?php

namespace App\Enums;

enum CardLabel: string
{
    case Video           = 'Video';
    case Graphic         = 'Graphic';
    case EbayListing     = 'eBay Listing';
    case WebsiteCreation = 'Website Creation';
    case CRM             = 'CRM';
    case Sales           = 'Sales';

    public function color(): string
    {
        return match($this) {
            self::Video           => '#7c3aed', // Violet
            self::Graphic         => '#0284c7', // Sky
            self::EbayListing     => '#d97706', // Amber
            self::WebsiteCreation => '#059669', // Emerald
            self::CRM             => '#db2777', // Pink
            self::Sales           => '#dc2626', // Red
        };
    }

    public function bgColor(): string
    {
        return match($this) {
            self::Video           => '#ede9fe',
            self::Graphic         => '#e0f2fe',
            self::EbayListing     => '#fef3c7',
            self::WebsiteCreation => '#d1fae5',
            self::CRM             => '#fce7f3',
            self::Sales           => '#fee2e2',
        };
    }

    /** Sub-labels available for this label */
    public function subLabels(): array
    {
        return match($this) {
            self::Video           => CardSubLabel::videoOptions(),
            self::Graphic         => CardSubLabel::graphicOptions(),
            self::EbayListing     => CardSubLabel::ebayOptions(),
            self::WebsiteCreation => CardSubLabel::websiteOptions(),
            self::CRM, self::Sales => [],
        };
    }

    public static function options(): array
    {
        return array_map(fn($e) => [
            'value'    => $e->value,
            'label'    => $e->value,
            'color'    => $e->color(),
            'bg_color' => $e->bgColor(),
        ], self::cases());
    }
}
