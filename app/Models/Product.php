<?php

namespace App\Models;

use App\Enums\ProductCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'sku', 'category', 'description',
        'brand', 'model', 'year', 'condition',
        'price', 'currency', 'is_active', 'image', 'created_by',
    ];

    protected $casts = [
        'category'  => ProductCategory::class,
        'is_active' => 'boolean',
        'price'     => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function ebayOffers(): HasMany
    {
        return $this->hasMany(EbayOffer::class);
    }

    public function logistics(): HasMany
    {
        return $this->hasMany(Logistic::class);
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->image) return asset('storage/' . $this->image);
        return "https://ui-avatars.com/api/?name="
            . urlencode($this->category?->icon() . ' ' . $this->name)
            . "&size=64&background=f59e0b&color=fff";
    }

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $cat): mixed
    {
        return $query->where('category', $cat);
    }
}
