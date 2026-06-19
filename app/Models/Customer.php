<?php

namespace App\Models;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'company', 'job_title', 'website',
        'country', 'state', 'city', 'address', 'postcode',
        'status', 'source', 'pipeline_stage',
        'product_interests', 'tags',
        'lifetime_value', 'currency',
        'has_purchased', 'first_purchase_date', 'last_purchase_date', 'total_orders',
        'assigned_to', 'created_by',
        'notes', 'avatar',
    ];

    protected $casts = [
        'status'              => CustomerStatus::class,
        'pipeline_stage'      => DealStage::class,
        'product_interests'   => 'array',
        'tags'                => 'array',
        'has_purchased'       => 'boolean',
        'first_purchase_date' => 'date',
        'last_purchase_date'  => 'date',
        'lifetime_value'      => 'decimal:2',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(CustomerInteraction::class)->orderByDesc('interacted_at');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class)->orderByDesc('created_at');
    }

    // ─── Accessors ─────────────────────────────────────────────────────────

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        $initials = collect(explode(' ', $this->name))
            ->take(2)->map(fn($w) => strtoupper($w[0]))->join('+');
        return "https://ui-avatars.com/api/?name={$initials}&size=64&background=6366f1&color=fff&bold=true";
    }

    public function getFormattedValueAttribute(): string
    {
        return number_format($this->lifetime_value, 2) . ' ' . $this->currency;
    }

    public function getInitialsAttribute(): string
    {
        return collect(explode(' ', $this->name))
            ->take(2)->map(fn($w) => strtoupper($w[0]))->join('');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────

    public function scopeSearch($query, string $term): mixed
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('company', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    public function scopeByStatus($query, string $status): mixed
    {
        return $query->where('status', $status);
    }

    public function scopeBySource($query, string $source): mixed
    {
        return $query->where('source', $source);
    }

    public function scopeActive($query): mixed
    {
        return $query->whereIn('status', ['lead', 'prospect', 'active']);
    }

    public function scopeAssignedTo($query, int $userId): mixed
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopePurchased($query): mixed
    {
        return $query->where('has_purchased', true);
    }
}
