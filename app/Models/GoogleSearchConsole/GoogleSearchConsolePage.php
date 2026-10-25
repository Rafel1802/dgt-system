<?php

namespace App\Models\GoogleSearchConsole;

use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleSearchConsolePage extends Model
{
    use HasFactory;

    protected $table = 'google_search_console_pages';

    protected $fillable = [
        'website_id',
        'site_url',
        'data_date',
        'page',
        'page_hash',
        'clicks',
        'impressions',
        'ctr',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'data_date'   => 'date',
            'clicks'      => 'integer',
            'impressions' => 'integer',
            'ctr'         => 'float',
            'position'    => 'float',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id');
    }
}
