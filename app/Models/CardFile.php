<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;

class CardFile extends Model
{
    protected $fillable = [
        'card_id',
        'uploaded_by',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'mime_type',
        'size',
        'sync_id',
    ];

    protected static function booted()
    {
        static::created(function ($file) {
            if (\App\Models\Card::$isSyncing) {
                return;
            }
            $card = $file->card;
            if ($card && $card->sync_group_id) {
                \App\Models\Card::$isSyncing = true;
                try {
                    if (!$file->sync_id) {
                        $file->sync_id = (string) \Illuminate\Support\Str::uuid();
                        $file->save();
                    }
                    $otherCards = \App\Models\Card::where('sync_group_id', $card->sync_group_id)
                        ->where('id', '!=', $card->id)
                        ->get();
                    foreach ($otherCards as $otherCard) {
                        if (!\App\Models\CardFile::where('card_id', $otherCard->id)->where('sync_id', $file->sync_id)->exists()) {
                            // Copy the file physically in storage
                            $newPath = "kanban/{$otherCard->id}/{$file->stored_name}";
                            if (\Illuminate\Support\Facades\Storage::exists($file->path)) {
                                \Illuminate\Support\Facades\Storage::copy($file->path, $newPath);
                            }
                            
                            \App\Models\CardFile::create([
                                'card_id' => $otherCard->id,
                                'uploaded_by' => $file->uploaded_by ?? auth()->id() ?? 1,
                                'original_name' => $file->original_name ?? '',
                                'stored_name' => $file->stored_name ?? '',
                                'disk' => $file->disk ?? 'local',
                                'path' => $newPath,
                                'mime_type' => $file->mime_type,
                                'size' => $file->size ?? 0,
                                'sync_id' => $file->sync_id,
                            ]);
                        }
                    }
                } finally {
                    \App\Models\Card::$isSyncing = false;
                }
            }
        });

        static::deleted(function ($file) {
            if (\App\Models\Card::$isSyncing) {
                return;
            }
            if ($file->sync_id) {
                \App\Models\Card::$isSyncing = true;
                try {
                    $otherFiles = \App\Models\CardFile::where('sync_id', $file->sync_id)
                        ->where('id', '!=', $file->id)
                        ->get();
                    foreach ($otherFiles as $otherFile) {
                        if (\Illuminate\Support\Facades\Storage::exists($otherFile->path)) {
                            \Illuminate\Support\Facades\Storage::delete($otherFile->path);
                        }
                        $otherFile->delete();
                    }
                } finally {
                    \App\Models\Card::$isSyncing = false;
                }
            }
        });
    }

    protected $casts = [
        'size' => 'integer',
    ];

    protected $appends = [
        'url',
        'download_url',
        'preview_url',
        'formatted_size',
        'icon',
        'is_image',
        'is_video',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function getUrlAttribute(): string
    {
        if ($this->disk === 'url') {
            return $this->path;
        }

        return $this->download_url;
    }

    public function getDownloadUrlAttribute(): string
    {
        if ($this->disk === 'url') {
            return $this->path;
        }

        if (Route::has('boards.cards.files.download')) {
            return route('boards.cards.files.download', ['card' => $this->card_id, 'file' => $this->id]);
        }

        return route('kanban.files.download', $this->id);
    }

    public function getPreviewUrlAttribute(): string
    {
        if ($this->disk === 'url') {
            return $this->path;
        }

        if (Route::has('boards.cards.files.preview')) {
            return route('boards.cards.files.preview', ['card' => $this->card_id, 'file' => $this->id]);
        }

        return $this->download_url;
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    public function getIconAttribute(): string
    {
        $mime = $this->mime_type ?? '';

        if (str_starts_with($mime, 'image/')) return 'image';
        if ($mime === 'application/pdf') return 'pdf';
        if (str_contains($mime, 'word') || str_contains($mime, 'document')) return 'doc';
        if (str_contains($mime, 'sheet') || str_contains($mime, 'excel')) return 'xls';
        if (str_contains($mime, 'zip') || str_contains($mime, 'rar')) return 'zip';

        return 'file';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function getIsImageAttribute(): bool
    {
        return $this->isImage();
    }

    public function getIsVideoAttribute(): bool
    {
        $mime = $this->mime_type ?? '';
        if (str_starts_with($mime, 'video/')) {
            return true;
        }

        $name = strtolower($this->original_name ?? '');
        $videoExts = ['.mp4', '.mov', '.webm', '.avi', '.mkv', '.wmv', '.flv', '.m4v', '.3gp'];
        foreach ($videoExts as $ext) {
            if (str_ends_with($name, $ext)) {
                return true;
            }
        }

        $url = strtolower($this->path ?? '');
        if (str_contains($url, 'drive.google.com')) {
            $nonVideoExts = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx', '.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg', '.zip', '.rar', '.7z'];
            foreach ($nonVideoExts as $ext) {
                if (str_ends_with($name, $ext)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }
}
