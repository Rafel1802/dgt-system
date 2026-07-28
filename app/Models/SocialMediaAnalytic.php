<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class SocialMediaAnalytic extends Model
{
    protected $fillable = [
        'date_from',
        'date_to',
        'file_path',
        'original_name',
        'uploaded_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to'   => 'date',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(
            SocialMediaClass::class,
            'social_media_analytic_class',
            'social_media_analytic_id',
            'social_media_class_id'
        )->withTimestamps();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /** Human-readable date range label */
    public function dateRangeLabel(): string
    {
        return $this->date_from->format('d M Y') . ' - ' . $this->date_to->format('d M Y');
    }

    /** Full storage URL for serving/download */
    public function storageUrl(): string
    {
        return Storage::url($this->file_path);
    }

    /** Absolute path for ZipArchive */
    public function absolutePath(): string
    {
        return Storage::path($this->file_path);
    }

    public function fileExists(): bool
    {
        return Storage::exists($this->file_path);
    }
}
