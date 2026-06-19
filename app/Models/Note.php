<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'folder_id',
        'type',
        'team',
        'title',
        'content',
        'plain_text',
        'is_pinned',
        'is_favorite',
        'is_archived',
        'last_edited_by',
        'position',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_favorite' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(NoteFolder::class, 'folder_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NoteAttachment::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(NoteChecklist::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(NoteActivity::class);
    }
}
