<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NoteChecklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'note_id',
        'title',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(NoteChecklistItem::class, 'checklist_id');
    }
}
