<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class EbayStore extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_name', 'logo_url', 'store_url', 'ebay_username', 'handled_by', 'notes', 'is_active', 'total_sales',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'total_sales' => 'decimal:2',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }

    public function offers(): HasMany
    {
        return $this->hasMany(EbayOffer::class, 'store_id');
    }

    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(EbayOrder::class, EbayOffer::class, 'store_id', 'ebay_offer_id');
    }

    public function customerRecords(): HasMany
    {
        return $this->hasMany(EbayCustomerRecord::class, 'ebay_store_id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term): mixed
    {
        return $query->where(function ($q) use ($term) {
            $q->where('store_name', 'like', "%{$term}%")
              ->orWhere('ebay_username', 'like', "%{$term}%");
        });
    }
}
