<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    protected $fillable = [
        'attachable_type', 'attachable_id',
        'uploaded_by', 'filename', 'original_name',
        'mime_type', 'file_size', 'disk', 'path', 'label',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function getUrlAttribute(): string
    {
        return route('attachments.download', $this->id);
    }

    public function getViewUrlAttribute(): string
    {
        return route('attachments.view', $this->id);
    }

    public function getFormattedSizeAttribute(): string
    {
        if (! $this->file_size) return '—';
        $kb = $this->file_size / 1024;
        if ($kb < 1024) return round($kb, 1) . ' KB';
        return round($kb / 1024, 2) . ' MB';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }
}
