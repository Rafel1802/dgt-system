<?php

namespace App\Enums;

enum CardSubLabel: string
{
    // Video
    case ShortVideo    = 'Short Video';
    case LongVideo     = 'Long Video';
    case MarketingVideo = 'Marketing Video';
    case ProductVideo  = 'Product Video';

    // Graphic
    case Banner        = 'Banner';
    case ProductPoster = 'Product Poster';
    case SocialMedia   = 'Social Media';
    case Thumbnail     = 'Thumbnail';

    // eBay Listing
    case NewListing    = 'New Listing';
    case EditListing   = 'Edit Listing';
    case EbaySEO       = 'SEO';
    case PriceUpdate   = 'Price Update';

    // Website Creation
    case LandingPage   = 'Landing Page';
    case ProductPage   = 'Product Page';
    case Blog          = 'Blog';
    case BugFix        = 'Bug Fix';

    public static function videoOptions(): array
    {
        return [self::ShortVideo, self::LongVideo, self::MarketingVideo, self::ProductVideo];
    }

    public static function graphicOptions(): array
    {
        return [self::Banner, self::ProductPoster, self::SocialMedia, self::Thumbnail];
    }

    public static function ebayOptions(): array
    {
        return [self::NewListing, self::EditListing, self::EbaySEO, self::PriceUpdate];
    }

    public static function websiteOptions(): array
    {
        return [self::LandingPage, self::ProductPage, self::Blog, self::BugFix];
    }

    /** Get sub-labels for a given label value string */
    public static function forLabel(string $label): array
    {
        $cardLabel = CardLabel::tryFrom($label);
        if (! $cardLabel) {
            return [];
        }
        return array_map(fn($e) => $e->value, $cardLabel->subLabels());
    }
}
