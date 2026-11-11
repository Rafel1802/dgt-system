<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessageAttachment extends Model
{
    use HasFactory;

    protected $table = 'chat_message_attachments';

    protected $appends = [
        'formatted_size',
        'download_url',
        'view_url',
        'file_url',
    ];

    protected $fillable = [
        'message_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'width',
        'height',
        'duration_seconds',
        'waveform_data',
        'thumbnail_path',
    ];

    protected $casts = [
        'file_size'        => 'integer',
        'width'            => 'integer',
        'height'           => 'integer',
        'duration_seconds' => 'integer',
        'waveform_data'    => 'array',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    /**
     * Get a human-readable file size string.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 0) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Get the download URL for authenticated members.
     */
    public function getDownloadUrlAttribute(): string
    {
        try {
            return route('chat.attachments.download', ['attachment' => $this->id]);
        } catch (\Throwable $e) {
            return '/chat/attachments/' . $this->id . '/download';
        }
    }

    /**
     * Get the streamable/viewable URL for authenticated members.
     */
    public function getViewUrlAttribute(): string
    {
        try {
            return route('chat.attachments.view', ['attachment' => $this->id]);
        } catch (\Throwable $e) {
            return '/chat/attachments/' . $this->id . '/view';
        }
    }

    /**
     * Get direct file URL from storage.
     */
    public function getFileUrlAttribute(): string
    {
        if (!empty($this->file_path)) {
            if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://') || str_starts_with($this->file_path, '/')) {
                return $this->file_path;
            }
            return asset('storage/' . ltrim($this->file_path, '/'));
        }
        return $this->view_url;
    }
}
