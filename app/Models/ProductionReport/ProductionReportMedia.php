<?php

namespace App\Models\ProductionReport;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionReportMedia extends Model
{
    protected $table = 'production_report_media';

    protected $fillable = [
        'production_report_id',
        'source_daily_report_id',
        'uploaded_by',
        'created_by_user_id',
        'team_code',
        'title',
        'description',
        'media_type',
        'project_category',
        'production_date',
        'disk',
        'file_path',
        'thumbnail_path',
        'original_filename',
        'mime_type',
        'file_size',
        'external_url',
        'platform',
        'duration_label',
        'is_highlighted',
        'highlight_category',
        'highlight_order',
    ];

    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'file_size' => 'integer',
            'is_highlighted' => 'boolean',
            'highlight_order' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProductionReport::class, 'production_report_id');
    }

    public function sourceDailyReport(): BelongsTo
    {
        return $this->belongsTo(ProductionReport::class, 'source_daily_report_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    public function isImage(): bool
    {
        if (str_starts_with((string) $this->mime_type, 'image/')) {
            return true;
        }
        $ext = strtolower(pathinfo((string) $this->original_filename, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    public function isVideo(): bool
    {
        if ($this->media_type === 'video' || str_starts_with((string) $this->mime_type, 'video/')) {
            return true;
        }
        $ext = strtolower(pathinfo((string) $this->original_filename, PATHINFO_EXTENSION));
        return in_array($ext, ['mp4', 'mov', 'webm'], true);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf'
            || strtolower(pathinfo((string) $this->original_filename, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function getFormattedSizeAttribute(): string
    {
        if (!$this->file_size) {
            return $this->external_url ? 'External Link' : '—';
        }
        $kb = $this->file_size / 1024;
        if ($kb < 1024) {
            return round($kb, 1) . ' KB';
        }
        return round($kb / 1024, 2) . ' MB';
    }
}

