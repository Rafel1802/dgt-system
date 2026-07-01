<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TruckingCompany extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_name', 'pic_name', 'phone', 'email',
        'address', 'notes', 'handled_by', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }

    public function logistics(): HasMany
    {
        return $this->hasMany(Logistic::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term): mixed
    {
        return $query->where(function ($q) use ($term) {
            $q->where('company_name', 'like', "%{$term}%")
              ->orWhere('pic_name', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}
