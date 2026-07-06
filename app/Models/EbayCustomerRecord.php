<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EbayCustomerRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ebay_customer_records';

    const TAB_URGENT         = 'urgent_client';
    const TAB_CANCELATION    = 'cancelation_client';
    const TAB_TECHNICAL      = 'technical_issues';
    const TAB_POT_NEGATIVES  = 'potential_negatives';
    const TAB_NEGATIVES      = 'negatives_feedbacks';
    const TAB_NEW_ORDER      = 'new_order';

    protected $fillable = [
        'tab_type',
        'customer_id',
        'n', 'username', 'buyer_name', 'informations', 'email',
        'ebay_store_id', 'order_id', 'summary', 'sku_number',
        'date', 'order_date',
        'attention_required', 'required_attentions', 'updates',
        'status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'date'       => 'date',
        'order_date' => 'date',
    ];

    // ── Tab definitions ───────────────────────────────────────────────────────

    public static function tabs(): array
    {
        return [
            self::TAB_URGENT        => 'Urgent Client',
            self::TAB_CANCELATION   => 'Cancelation Client',
            self::TAB_TECHNICAL     => 'Technical Issues',
            self::TAB_POT_NEGATIVES => 'Potential Negatives Feedbacks',
            self::TAB_NEGATIVES     => 'Negatives Feedbacks',
            self::TAB_NEW_ORDER     => 'New Order',
        ];
    }

    /**
     * Return which columns are relevant for each tab.
     */
    public static function columnsForTab(string $tab): array
    {
        return match($tab) {
            self::TAB_URGENT => [
                'n', 'username', 'buyer_name', 'informations', 'email', 'ebay_store_id',
                'order_id', 'summary', 'attention_required', 'order_date', 'status',
            ],
            self::TAB_CANCELATION => [
                'date', 'username', 'buyer_name', 'ebay_store_id',
                'order_id', 'summary', 'sku_number',
            ],
            self::TAB_TECHNICAL => [
                'date', 'username', 'informations', 'ebay_store_id',
                'order_id', 'summary', 'required_attentions',
            ],
            self::TAB_POT_NEGATIVES => [
                'date', 'username', 'buyer_name', 'ebay_store_id',
                'order_id', 'summary', 'required_attentions',
            ],
            self::TAB_NEGATIVES => [
                'date', 'username', 'buyer_name', 'ebay_store_id',
                'order_id', 'summary', 'required_attentions', 'updates',
            ],
            self::TAB_NEW_ORDER => [
                'date', 'username', 'order_id', 'summary', 'status',
            ],
            default => [],
        };
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(EbayStore::class, 'ebay_store_id')->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTab($query, string $tab): mixed
    {
        return $query->where('tab_type', $tab);
    }

    public function scopeSearch($query, string $term): mixed
    {
        return $query->where(function ($q) use ($term) {
            $q->where('username', 'like', "%{$term}%")
              ->orWhere('buyer_name', 'like', "%{$term}%")
              ->orWhere('order_id', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }
}
