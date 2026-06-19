<?php

namespace App\Models;

use App\Enums\AuthorizationStatus;
use App\Enums\EbayLeadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EbayOffer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id', 'handled_by', 'product_id',
        'ebay_username', 'ebay_message_id', 'ebay_item_id',
        'client_name', 'client_email',
        'inquiry_notes', 'offer_details',
        'offer_amount', 'final_amount', 'currency', 'payment_status',
        'status', 'authorization_status',
        'authorized_by', 'authorized_at', 'authorization_notes',
        'received_at',
    ];

    protected $casts = [
        'status'               => EbayLeadStatus::class,
        'authorization_status' => AuthorizationStatus::class,
        'offer_amount'         => 'decimal:2',
        'final_amount'         => 'decimal:2',
        'received_at'          => 'datetime',
        'authorized_at'        => 'datetime',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by')->withTrashed();
    }

    public function order(): HasOne
    {
        return $this->hasOne(EbayOrder::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeWaitingAuthorization($query): mixed
    {
        return $query->where('authorization_status', AuthorizationStatus::Pending->value)
            ->where('status', EbayLeadStatus::WaitingAuthorization->value);
    }

    public function scopeSearch($query, string $term): mixed
    {
        return $query->where(function ($q) use ($term) {
            $q->where('client_name', 'like', "%{$term}%")
              ->orWhere('ebay_username', 'like', "%{$term}%")
              ->orWhere('client_email', 'like', "%{$term}%")
              ->orWhere('ebay_item_id', 'like', "%{$term}%");
        });
    }
}
