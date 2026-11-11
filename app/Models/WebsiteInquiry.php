<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteInquiry extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'website_inquiries';

    // ── Inquiry Types ──────────────────────────────────────────────────────────
    const TYPE_QUOTE_REQUEST        = 'quote_request';
    const TYPE_CONTACT              = 'contact';
    const TYPE_PRODUCT_QUESTION     = 'product_question';
    const TYPE_PRICING_AVAILABILITY = 'pricing_availability';
    const TYPE_TECHNICAL_SUPPORT    = 'technical_support';
    const TYPE_SALES                = 'sales';
    const TYPE_PARTNERSHIP          = 'partnership';
    const TYPE_DEALER_DISTRIBUTOR   = 'dealer_distributor';
    const TYPE_GENERAL              = 'general';
    const TYPE_CUSTOM_FORM          = 'custom_form';

    const INQUIRY_TYPES = [
        self::TYPE_QUOTE_REQUEST        => 'Quote Request',
        self::TYPE_CONTACT              => 'Contact Inquiry',
        self::TYPE_PRODUCT_QUESTION     => 'Product Question',
        self::TYPE_PRICING_AVAILABILITY => 'Pricing & Availability',
        self::TYPE_TECHNICAL_SUPPORT    => 'Technical Support',
        self::TYPE_SALES                => 'Sales Inquiry',
        self::TYPE_PARTNERSHIP          => 'Partnership',
        self::TYPE_DEALER_DISTRIBUTOR   => 'Dealer & Distributor',
        self::TYPE_GENERAL              => 'General Inquiry',
        self::TYPE_CUSTOM_FORM          => 'Custom Form Submission',
    ];

    // ── Inquiry Statuses ───────────────────────────────────────────────────────
    const STATUS_NEW                = 'new';
    const STATUS_UNREAD             = 'unread';
    const STATUS_PENDING            = 'pending';
    const STATUS_ASSIGNED           = 'assigned';
    const STATUS_IN_PROGRESS        = 'in_progress';
    const STATUS_REPLIED            = 'replied';
    const STATUS_FOLLOW_UP_REQUIRED = 'follow_up_required';
    const STATUS_RESOLVED           = 'resolved';
    const STATUS_CLOSED             = 'closed';
    const STATUS_SPAM               = 'spam';

    const STATUSES = [
        self::STATUS_NEW                => 'New',
        self::STATUS_UNREAD             => 'Unread',
        self::STATUS_PENDING            => 'Pending',
        self::STATUS_ASSIGNED           => 'Assigned',
        self::STATUS_IN_PROGRESS        => 'In Progress',
        self::STATUS_REPLIED            => 'Replied',
        self::STATUS_FOLLOW_UP_REQUIRED => 'Follow-up Required',
        self::STATUS_RESOLVED           => 'Resolved',
        self::STATUS_CLOSED             => 'Closed',
        self::STATUS_SPAM               => 'Spam',
    ];

    protected $fillable = [
        'website_id',
        'inquiry_type',
        'status',
        'is_read',
        'form_id',
        'form_name',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_company',
        'subject',
        'message',
        'product_service',
        'source_url',
        'custom_fields',
        'attachments',
        'ip_address',
        'user_agent',
        'referrer_url',
        'idempotency_key',
        'assigned_to',
        'assigned_at',
        'last_replied_at',
        'resolved_at',
        'closed_at',
        'submitted_at',
    ];

    protected $casts = [
        'custom_fields'   => 'array',
        'attachments'     => 'array',
        'is_read'         => 'boolean',
        'assigned_at'     => 'datetime',
        'last_replied_at' => 'datetime',
        'resolved_at'     => 'datetime',
        'closed_at'       => 'datetime',
        'submitted_at'    => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WebsiteInquiryActivity::class, 'inquiry_id')->latest();
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeUnread($query)
    {
        return $query->where('is_read', false)->where('status', '!=', self::STATUS_SPAM);
    }

    public function scopeByStatus($query, ?string $status)
    {
        if ($status && array_key_exists($status, self::STATUSES)) {
            return $query->where('status', $status);
        }
        return $query;
    }

    public function scopeByType($query, ?string $type)
    {
        if ($type && array_key_exists($type, self::INQUIRY_TYPES)) {
            return $query->where('inquiry_type', $type);
        }
        return $query;
    }

    public function scopeByWebsite($query, ?int $websiteId)
    {
        if ($websiteId) {
            return $query->where('website_id', $websiteId);
        }
        return $query;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('customer_name', 'like', "%{$term}%")
              ->orWhere('customer_email', 'like', "%{$term}%")
              ->orWhere('customer_phone', 'like', "%{$term}%")
              ->orWhere('customer_company', 'like', "%{$term}%")
              ->orWhere('subject', 'like', "%{$term}%")
              ->orWhere('message', 'like', "%{$term}%")
              ->orWhere('product_service', 'like', "%{$term}%")
              ->orWhere('form_name', 'like', "%{$term}%");
        });
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getTypeLabelAttribute(): string
    {
        return self::INQUIRY_TYPES[$this->inquiry_type] ?? ucfirst(str_replace('_', ' ', $this->inquiry_type));
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_NEW                => 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
            self::STATUS_UNREAD             => 'bg-indigo-100 text-indigo-800 border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800',
            self::STATUS_PENDING            => 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
            self::STATUS_ASSIGNED           => 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-800',
            self::STATUS_IN_PROGRESS        => 'bg-cyan-100 text-cyan-800 border-cyan-200 dark:bg-cyan-900/30 dark:text-cyan-300 dark:border-cyan-800',
            self::STATUS_REPLIED            => 'bg-teal-100 text-teal-800 border-teal-200 dark:bg-teal-900/30 dark:text-teal-300 dark:border-teal-800',
            self::STATUS_FOLLOW_UP_REQUIRED => 'bg-rose-100 text-rose-800 border-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800',
            self::STATUS_RESOLVED           => 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
            self::STATUS_CLOSED             => 'bg-slate-100 text-slate-800 border-slate-200 dark:bg-slate-800/40 dark:text-slate-300 dark:border-slate-700',
            self::STATUS_SPAM               => 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800',
            default                         => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }
}
