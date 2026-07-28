<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteFollowUp extends Model
{
    const TYPES = [
        'blog_post'    => 'Blog Post',
        'indexed_page' => 'Indexed Page',
        'website_page' => 'Website Page',
        'other'        => 'Other',
    ];

    const QC_STATUSES = [
        'pending'  => 'Pending',
        'checked'  => 'Checked',
        'approved' => 'Approved',
    ];

    const INDEXED_OPTIONS = [
        'yes'     => 'Yes',
        'no'      => 'No',
        'pending' => 'Pending',
    ];

    protected $fillable = [
        'website_id',
        'type',
        'title',
        'url',
        'google_indexed',
        'note',
        'assigned_to',
        'qc_status',
        'qc_checked_by',
        'qc_checked_at',
        'created_by',
    ];

    protected $casts = [
        'qc_checked_at' => 'datetime',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function qcChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_checked_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function getQcStatusBadgeClass(): string
    {
        return match ($this->qc_status) {
            'approved' => 'bg-emerald-100 text-emerald-700',
            'checked'  => 'bg-blue-100 text-blue-700',
            default    => 'bg-slate-100 text-slate-600',
        };
    }
}
