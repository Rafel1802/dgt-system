<?php

namespace App\Models\GoogleSearchConsole;

use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleSearchConsoleDailyMetric extends Model
{
    use HasFactory;

    protected $table = 'google_search_console_daily_metrics';

    protected $fillable = [
        'website_id',
        'site_url',
        'data_date',
        'clicks',
        'impressions',
        'ctr',
        'position',
        'search_type',
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
