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
        'is_comment_image',
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
                            // Copy the file physically in storage if it's a real file
                            if ($file->disk !== 'url' && $file->mime_type !== 'link') {
                                $newPath = "kanban/{$otherCard->id}/{$file->stored_name}";
                                if (\Illuminate\Support\Facades\Storage::exists($file->path)) {
                                    \Illuminate\Support\Facades\Storage::copy($file->path, $newPath);
                                }
                            } else {
                                $newPath = $file->path;
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
                                'is_comment_image' => $file->is_comment_image ?? false,
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
        'is_comment_image' => 'boolean',
    ];

    protected $appends = [
        'url',
        'download_url',
        'preview_url',
        'embed_url',
        'thumbnail_url',
        'formatted_size',
        'icon',
        'is_image',
        'is_video',
        'is_canva',
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

    public function getIsCanvaAttribute(): bool
    {
        $raw = strtolower(($this->path ?? '') . ' ' . ($this->stored_name ?? '') . ' ' . ($this->original_name ?? ''));
        return str_contains($raw, 'canva.com') || str_contains($raw, 'canva.link') || str_contains($raw, 'canva.me') || str_contains($raw, 'canva.site');
    }

    public function getIsVideoAttribute(): bool
    {
        $mime = strtolower($this->mime_type ?? '');
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

        $url = strtolower($this->path ?? $this->stored_name ?? '');
        if (str_contains($url, 'drive.google.com')) {
            $nonVideoExts = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx', '.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg', '.zip', '.rar', '.7z'];
            foreach ($nonVideoExts as $ext) {
                if (str_ends_with($name, $ext)) {
                    return false;
                }
            }
            return true;
        }

        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be') || str_contains($url, 'loom.com') || str_contains($url, 'vimeo.com')) {
            return true;
        }

        return false;
    }

    public function getEmbedUrlAttribute(): ?string
    {
        $rawUrl = $this->path ?? $this->stored_name ?? '';
        if (empty($rawUrl)) {
            return null;
        }

        // Google Drive
        if (str_contains($rawUrl, 'drive.google.com')) {
            // Check for /file/d/{id}/
            if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/i', $rawUrl, $matches)) {
                return "https://drive.google.com/file/d/{$matches[1]}/preview";
            }
            // Check for id={id}
            if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/i', $rawUrl, $matches)) {
                return "https://drive.google.com/file/d/{$matches[1]}/preview";
            }
            // Fallback: strip query and replace /view with /preview
            $clean = explode('?', $rawUrl)[0];
            return str_replace('/view', '/preview', $clean);
        }

        // YouTube
        if (str_contains($rawUrl, 'youtube.com') || str_contains($rawUrl, 'youtu.be')) {
            if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/i', $rawUrl, $matches)) {
                return "https://www.youtube.com/embed/{$matches[1]}";
            }
        }

        // Loom
        if (str_contains($rawUrl, 'loom.com')) {
            if (preg_match('/loom\.com\/share\/([a-zA-Z0-9]+)/i', $rawUrl, $matches)) {
                return "https://www.loom.com/embed/{$matches[1]}";
            }
        }

        // Vimeo
        if (str_contains($rawUrl, 'vimeo.com')) {
            if (preg_match('/vimeo\.com\/(\d+)/i', $rawUrl, $matches)) {
                return "https://player.vimeo.com/video/{$matches[1]}";
            }
        }

        // Canva
        if (str_contains($rawUrl, 'canva.com') || str_contains($rawUrl, 'canva.link') || str_contains($rawUrl, 'canva.me') || str_contains($rawUrl, 'canva.site')) {
            // If it's a short link (e.g. https://canva.link/xyz), resolve the real design URL
            if (preg_match('#canva\.(link|me|site)/[a-zA-Z0-9_-]+#i', $rawUrl) || !str_contains($rawUrl, '/design/')) {
                $resolved = self::resolveCanvaEmbedUrl($rawUrl);
                if ($resolved) {
                    return $resolved;
                }
            }

            if (preg_match('#/design/([a-zA-Z0-9_-]+)(?:/([a-zA-Z0-9_-]+))?(?:/([a-zA-Z0-9_-]+))?#i', $rawUrl, $m)) {
                $id = $m[1];
                $p2 = $m[2] ?? '';
                $p3 = $m[3] ?? '';
                if (in_array($p2, ['view', 'edit', 'watch', 'present', ''])) {
                    return "https://www.canva.com/design/{$id}/view?embed";
                } elseif (in_array($p3, ['view', 'edit', 'watch', 'present', ''])) {
                    return "https://www.canva.com/design/{$id}/{$p2}/view?embed";
                } else {
                    return "https://www.canva.com/design/{$id}/view?embed";
                }
            }
            if (!str_contains($rawUrl, '?embed') && !str_contains($rawUrl, '&embed')) {
                return rtrim($rawUrl, '/') . (str_contains($rawUrl, '?') ? '&embed' : '?embed');
            }
            return $rawUrl;
        }

        // Direct video files
        if ($this->is_video && $this->disk !== 'url') {
            return $this->preview_url ?: $this->download_url;
        }

        return null;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        $rawUrl = $this->path ?? $this->stored_name ?? '';
        if (empty($rawUrl)) {
            return null;
        }

        // Google Drive video thumbnail
        if (str_contains($rawUrl, 'drive.google.com')) {
            if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/i', $rawUrl, $matches)) {
                return "https://drive.google.com/thumbnail?id={$matches[1]}&sz=w320";
            }
            if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/i', $rawUrl, $matches)) {
                return "https://drive.google.com/thumbnail?id={$matches[1]}&sz=w320";
            }
        }

        // YouTube video thumbnail
        if (str_contains($rawUrl, 'youtube.com') || str_contains($rawUrl, 'youtu.be')) {
            if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/i', $rawUrl, $matches)) {
                return "https://img.youtube.com/vi/{$matches[1]}/mqdefault.jpg";
            }
        }

        if ($this->isImage()) {
            return $this->preview_url ?: $this->url;
        }

        return null;
    }

    /**
     * Resolve any shortened or share Canva URL (e.g. canva.link/xyz) into its
     * canonical presentation embed URL (canva.com/design/{id}/{token}/view?embed).
     */
    public static function resolveCanvaEmbedUrl(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        // If it already is a full design URL, transform it directly without network call
        if (preg_match('#/design/([a-zA-Z0-9_-]+)(?:/([a-zA-Z0-9_-]+))?(?:/([a-zA-Z0-9_-]+))?#i', $url, $m)) {
            $id = $m[1];
            $p2 = $m[2] ?? '';
            $p3 = $m[3] ?? '';
            if (in_array($p2, ['view', 'edit', 'watch', 'present', ''])) {
                return "https://www.canva.com/design/{$id}/view?embed";
            } elseif (in_array($p3, ['view', 'edit', 'watch', 'present', ''])) {
                return "https://www.canva.com/design/{$id}/{$p2}/view?embed";
            } else {
                return "https://www.canva.com/design/{$id}/view?embed";
            }
        }

        $cacheKey = 'canva_embed_' . md5($url);
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400 * 30, function () use ($url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ]);
            curl_exec($ch);
            $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);

            if ($redirectUrl && preg_match('#/design/([a-zA-Z0-9_-]+)(?:/([a-zA-Z0-9_-]+))?(?:/([a-zA-Z0-9_-]+))?#i', $redirectUrl, $m)) {
                $id = $m[1];
                $p2 = $m[2] ?? '';
                $p3 = $m[3] ?? '';
                if (in_array($p2, ['view', 'edit', 'watch', 'present', ''])) {
                    return "https://www.canva.com/design/{$id}/view?embed";
                } elseif (in_array($p3, ['view', 'edit', 'watch', 'present', ''])) {
                    return "https://www.canva.com/design/{$id}/{$p2}/view?embed";
                } else {
                    return "https://www.canva.com/design/{$id}/view?embed";
                }
            }

            return null;
        });
    }

    /**
     * Get Canva embed details including embed URL and total page count.
     */
    public static function getCanvaDetails(string $url): array
    {
        $embedUrl = self::resolveCanvaEmbedUrl($url);
        if (!$embedUrl) {
            return [
                'embed_url' => null,
                'total_pages' => 16,
            ];
        }

        $cacheKey = 'canva_details_v3_' . md5($embedUrl);
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400 * 30, function () use ($embedUrl) {
            $totalPages = 16;
            try {
                $ch = curl_init($embedUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 4,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                ]);
                $html = curl_exec($ch);
                curl_close($ch);
                if ($html) {
                    $start = strpos($html, "JSON.parse('");
                    if ($start !== false) {
                        $start += strlen("JSON.parse('");
                        $end = strpos($html, "'); window['flags']", $start);
                        if ($end === false) {
                            $end = strpos($html, "');", $start);
                        }
                        if ($end !== false) {
                            $raw = substr($html, $start, $end - $start);
                            $decoded = stripcslashes($raw);
                            $data = json_decode($decoded, true);
                            if ($data) {
                                $pages = $data['page']['C']['D']['A']['A'] ?? [];
                                if (is_array($pages) && count($pages) > 0) {
                                    $totalPages = count($pages);
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Silently fallback to default 16
            }

            return [
                'embed_url' => $embedUrl,
                'total_pages' => max(1, $totalPages),
            ];
        });
    }
}

