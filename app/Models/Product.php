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
        'name', 'sku', 'category', 'category_id', 'description',
        'brand', 'model', 'year', 'condition',
        'price', 'currency', 'is_active', 'status', 'image',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'category'  => ProductCategory::class,
        'is_active' => 'boolean',
        'price'     => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    /** New category (FK to product_categories table) */
    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ProductCategory::class, 'category_id');
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

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image) {
            if (filter_var($this->image, FILTER_VALIDATE_URL)) {
                return $this->image;
            }
            return asset('storage/' . $this->image);
        }
        return null;
    }

    /** Human-readable category name (prefers new category model, falls back to old enum) */
    public function getCategoryNameAttribute(): string
    {
        return $this->categoryModel?->name ?? ucfirst((string) ($this->category?->label() ?? $this->category ?? ''));
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $cat): mixed
    {
        return $query->where('category', $cat);
    }

    public function scopeByCategoryId($query, int $id): mixed
    {
        return $query->where('category_id', $id);
    }

    public function scopeSearch($query, string $term): mixed
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('sku', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        });
    }
}
