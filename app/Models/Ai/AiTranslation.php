<?php

namespace App\Models\Ai;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTranslation extends Model
{
    protected $table = 'ai_translations';

    protected $fillable = [
        'user_id',
        'source_language',
        'detected_language',
        'target_language',
        'translation_mode',
        'source_text',
        'translated_text',
        'extracted_ocr_text',
        'advanced_options',
        'provider_model',
        'is_favorite',
    ];

    protected $casts = [
        'advanced_options' => 'array',
        'is_favorite'      => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
