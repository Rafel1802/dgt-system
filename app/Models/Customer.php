<?php

namespace App\Models;

use App\Enums\CustomerQueue;
use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'company', 'job_title', 'website',
        'country', 'state', 'city', 'address', 'postcode',
        'status', 'source', 'pipeline_stage', 'current_queue', 'shipment_delay', 'shipment_delivered',
        'product_interests', 'tags',
        'lifetime_value', 'currency',
        'has_purchased', 'first_purchase_date', 'last_purchase_date', 'total_orders',
        'assigned_to', 'created_by',
        'notes', 'avatar',
    ];

    protected $casts = [
        'status'              => CustomerStatus::class,
        'pipeline_stage'      => DealStage::class,
        'current_queue'       => CustomerQueue::class,
        'shipment_delay'      => 'boolean',
        'shipment_delivered'  => 'boolean',
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

    /** Cross-department queue routing history — see CustomerController::routeToQueue(). */
    public function workflowLogs(): HasMany
    {
        return $this->hasMany(CustomerWorkflowLog::class)->orderByDesc('created_at');
    }

    public function attachments(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function ebayCustomerRecords(): HasMany
    {
        return $this->hasMany(EbayCustomerRecord::class);
    }

    public function ebayOffers(): HasMany
    {
        return $this->hasMany(EbayOffer::class);
    }

    public function logistics(): HasMany
    {
        return $this->hasMany(Logistic::class);
    }

    /** This customer's most recent shipping order — drives the "most recent order" product autofill when adding them to a shipment. */
    public function latestLogistic(): HasOne
    {
        return $this->hasOne(Logistic::class)->latestOfMany();
    }

    public function techSupportCases(): HasMany
    {
        return $this->hasMany(TechSupportCase::class);
    }

    /** Most recent technical support case across ALL of this customer's sources (Website leads + eBay records) — drives the "Resolved"/"(2nd)" badge on the customer profile page. */
    public function latestTechSupportCase(): HasOne
    {
        return $this->hasOne(TechSupportCase::class)->latestOfMany();
    }

    // ─── Accessors ─────────────────────────────────────────────────────────

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = \App\Support\PhoneNumberFormatter::format($value);
    }

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
