<?php

namespace App\Models\Kpi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiReviewItem extends Model
{
    protected $table = 'kpi_review_items';

    protected $fillable = [
        'review_id',
        'assignment_item_id',
        'score',
        'weight',
        'weighted_score',
        'comments',
    ];

    protected $casts = [
        'score' => 'float',
        'weight' => 'float',
        'weighted_score' => 'float',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(KpiReview::class, 'review_id');
    }

    public function assignmentItem(): BelongsTo
    {
        return $this->belongsTo(KpiAssignmentItem::class, 'assignment_item_id');
    }
}
