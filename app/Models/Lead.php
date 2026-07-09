<?php

namespace App\Models;

use App\Enums\InquirySource;
use App\Enums\LeadTemperature;
use App\Enums\WebsiteLeadStatus;
use App\Services\TechSupportCaseService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::created(function (self $lead) {
            if ($lead->status === WebsiteLeadStatus::TechnicalSupport) {
                app(TechSupportCaseService::class)->createCaseFor($lead);
            }
        });

        static::updated(function (self $lead) {
            if ($lead->wasChanged('status') && $lead->status === WebsiteLeadStatus::TechnicalSupport) {
                app(TechSupportCaseService::class)->createCaseFor($lead);
            }
        });
    }

    protected $fillable = [
        'customer_id', 'handled_by', 'assigned_to',
        'client_name', 'client_phone', 'client_email', 'client_whatsapp',
        'source', 'product_interested', 'product_id', 'inquiry_details', 'received_at',
        'status', 'temperature',
        'follow_up_notes', 'follow_up_date', 'next_action',
        'converted', 'converted_at', 'lost_reason',
        'tech_resolved', 'tech_resolved_at',
    ];

    protected $casts = [
        'source'       => InquirySource::class,
        'status'       => WebsiteLeadStatus::class,
        'temperature'  => LeadTemperature::class,
        'received_at'  => 'datetime',
        'converted_at' => 'datetime',
        'follow_up_date'=> 'date',
        'converted'    => 'boolean',
        'tech_resolved'    => 'boolean',
        'tech_resolved_at' => 'date',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(LeadFollowUp::class)->orderByDesc('contacted_at');
    }

    public function products(): HasMany
    {
        return $this->hasMany(LeadProduct::class);
    }

    public function logistic(): HasMany
    {
        return $this->hasMany(Logistic::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeHot($query): mixed
    {
        return $query->where('temperature', LeadTemperature::Hot->value);
    }

    public function scopeFollowUpDue($query): mixed
    {
        return $query->where('follow_up_date', '<=', today())
            ->whereNotIn('status', [
                WebsiteLeadStatus::Delivered->value,
                WebsiteLeadStatus::Lost->value,
            ]);
    }

    public function scopeActive($query): mixed
    {
        return $query->whereNotIn('status', [
            WebsiteLeadStatus::Delivered->value,
            WebsiteLeadStatus::Lost->value,
        ]);
    }

    public function scopeBySource($query, string $source): mixed
    {
        return $query->where('source', $source);
    }

    public function scopeSearch($query, string $term): mixed
    {
        return $query->where(function ($q) use ($term) {
            $q->where('client_name', 'like', "%{$term}%")
              ->orWhere('client_phone', 'like', "%{$term}%")
              ->orWhere('client_email', 'like', "%{$term}%");
        });
    }

    public function scopeTechnicalIssuesOpen($query): mixed
    {
        return $query->where('status', WebsiteLeadStatus::TechnicalSupport->value)
            ->where('tech_resolved', false);
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getIsOverdueAttribute(): bool
    {
        return $this->follow_up_date
            && $this->follow_up_date->isPast()
            && ! $this->status?->isTerminal();
    }
}
