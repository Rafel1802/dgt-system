<?php

namespace App\Models\Ai;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReport extends Model
{
    use HasFactory;

    protected $table = 'ai_reports';

    protected $fillable = [
        'user_id',
        'title',
        'category',
        'date_from',
        'date_to',
        'filters',
        'stats_data',
        'analysis_text',
        'file_pdf_path',
        'file_csv_path',
    ];

    protected $casts = [
        'date_from'  => 'date',
        'date_to'    => 'date',
        'filters'    => 'array',
        'stats_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
