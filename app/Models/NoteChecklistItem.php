<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_id',
        'content',
        'is_completed',
        'position',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(NoteChecklist::class, 'checklist_id');
    }
}
