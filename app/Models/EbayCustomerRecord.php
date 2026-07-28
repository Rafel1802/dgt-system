<?php

namespace App\Models;

use App\Services\TechSupportCaseService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class EbayCustomerRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ebay_customer_records';

    protected static function booted(): void
    {
        static::created(function (self $record) {
            if ($record->shouldCreateTechSupportCase()) {
                app(TechSupportCaseService::class)->createCaseFor($record);
            }
        });

        static::updated(function (self $record) {
            if (
                ($record->wasChanged('tab_type') || $record->wasChanged('negative_feedback_causes'))
                && $record->shouldCreateTechSupportCase()
            ) {
                app(TechSupportCaseService::class)->createCaseFor($record);
            }
        });
    }

    /**
     * True when this record needs a real Tech Support case: either directly
     * in the Technical Issues category, or in one of the negative-feedback
     * categories with "Technical" checked as a cause — both funnel into the
     * same shared case system Website CRM leads already use (assignable,
     * shows on the Tech Support page, gets notifications), rather than
     * leaving a technical negative-feedback report as just a tag on this
     * record with nowhere for Tech Support to actually see or work it.
     * createCaseFor() is itself idempotent (no-ops if an open case already
     * exists, reopens a resolved one instead of duplicating), so it's safe
     * to call this on every save that still matches, not just the first.
     */
    public function shouldCreateTechSupportCase(): bool
    {
        if ($this->tab_type === self::TAB_TECHNICAL) {
            return true;
        }

        return in_array($this->tab_type, [self::TAB_POT_NEGATIVES, self::TAB_NEGATIVES], true)
            && in_array('Technical', $this->negative_feedback_causes ?? [], true);
    }

    const TAB_URGENT         = 'urgent_client';
    const TAB_CANCELATION    = 'cancelation_client';
    const TAB_TECHNICAL      = 'technical_issues';
    const TAB_POT_NEGATIVES  = 'potential_negatives';
    const TAB_NEGATIVES      = 'negatives_feedbacks';
    const TAB_NEW_ORDER      = 'new_order';
    const TAB_RESOLVED       = 'resolved';

    protected $fillable = [
        'tab_type',
        'customer_id',
        'n', 'username', 'buyer_name', 'informations', 'email', 'phone',
        'ebay_store_id', 'order_id', 'summary', 'sku_number',
        'date', 'order_date',
        'attention_required', 'required_attentions', 'updates',
        'status', 'shipment_delay', 'shipment_delivered',
        'negative_feedback_causes', 'negative_feedback_resolved', 'negative_feedback_resolved_at',
        'tech_resolved', 'tech_resolved_at',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'date'                          => 'date',
        'order_date'                    => 'date',
        'shipment_delay'                => 'boolean',
        'shipment_delivered'            => 'boolean',
        'negative_feedback_causes'      => 'array',
        'negative_feedback_resolved'    => 'boolean',
        'negative_feedback_resolved_at' => 'date',
        'tech_resolved'                 => 'boolean',
        'tech_resolved_at'              => 'date',
    ];

    const NEGATIVE_FEEDBACK_CAUSES = ['Technical', 'Logistic issues', 'Customer service'];

    /** Logistic-issues color, shared with the shipment_delay flag badge and the "Logistic issues" cause. */
    const LOGISTIC_ISSUES_COLOR = '#f97316';

    /** Delivered color, shared with the shipment_delivered flag badge — matches ShipmentCustomer::colorForStatus('delivered'). */
    const DELIVERED_COLOR = '#22c55e';

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
            self::TAB_RESOLVED      => 'Resolved',
        ];
    }

    /** Color for each status/category, for badge styling. */
    public static function tabColor(string $tab): string
    {
        return match ($tab) {
            self::TAB_URGENT        => '#f59e0b', // amber
            self::TAB_CANCELATION   => '#94a3b8', // slate
            self::TAB_TECHNICAL     => '#ef4444', // red
            self::TAB_POT_NEGATIVES => '#f97316', // orange
            self::TAB_NEGATIVES     => '#dc2626', // deep red
            self::TAB_NEW_ORDER     => '#10b981', // emerald
            self::TAB_RESOLVED      => '#0ea5e9', // sky
            default => '#6366f1',
        };
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
            self::TAB_RESOLVED => [
                'date', 'username', 'buyer_name', 'informations', 'ebay_store_id', 'order_id', 'summary', 'status',
            ],
            default => [],
        };
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = \App\Support\PhoneNumberFormatter::format($value);
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

    public function handlerHistory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EbayCustomerHandlerHistory::class)->orderByDesc('started_at');
    }

    public function statusHistory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EbayCustomerStatusHistory::class)->orderByDesc('changed_at');
    }

    public function followUps(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EbayCustomerFollowUp::class)->orderByDesc('contacted_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(EbayCustomerOrder::class)->orderByDesc('ordered_at');
    }

    /** Most recent order only — used by list/directory paths that only need purchase date. */
    public function latestOrder(): HasOne
    {
        return $this->hasOne(EbayCustomerOrder::class)->latestOfMany('ordered_at');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** The (at most one) technical support case tracking this record's Technical Issues occurrences — see TechSupportCaseService::createCaseFor(). */
    public function techSupportCase(): MorphOne
    {
        return $this->morphOne(TechSupportCase::class, 'source')->latestOfMany();
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getCurrentHandlerAttribute(): ?User
    {
        return $this->handlerHistory->firstWhere('ended_at', null)?->user;
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
