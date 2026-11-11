<?php

namespace App\Jobs\Chat;

use App\Models\Chat\ChatMessageAttachment;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ConvertChatImageToWebp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $attachmentId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $attachmentId)
    {
        $this->attachmentId = $attachmentId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!function_exists('imagewebp')) {
            return;
        }

        $attachment = ChatMessageAttachment::find($this->attachmentId);
        if (!$attachment) {
            return;
        }

        // Only convert raster non-webp image formats (preserve SVG vectors and GIF animations)
        if (!str_starts_with($attachment->file_type, 'image/') || in_array($attachment->file_type, ['image/webp', 'image/svg+xml', 'image/gif'])) {
            return;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($attachment->file_path)) {
            return;
        }

        $originalFullPath = $disk->path($attachment->file_path);

        try {
            $rawContent = @file_get_contents($originalFullPath);
            if (!$rawContent) {
                return;
            }

            $gdImage = @imagecreatefromstring($rawContent);
            if (!$gdImage) {
                return;
            }

            imagealphablending($gdImage, false);
            imagesavealpha($gdImage, true);

            $pathInfo = pathinfo($attachment->file_path);
            $webpRelative = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
            $webpFullPath = $disk->path($webpRelative);

            $success = @imagewebp($gdImage, $webpFullPath, 82);
            imagedestroy($gdImage);

            if ($success && file_exists($webpFullPath) && filesize($webpFullPath) > 0) {
                $newSize = filesize($webpFullPath);

                if ($originalFullPath !== $webpFullPath && file_exists($originalFullPath)) {
                    @unlink($originalFullPath);
                }

                $newFileName = pathinfo($attachment->file_name, PATHINFO_FILENAME) . '.webp';

                $attachment->update([
                    'file_path' => $webpRelative,
                    'file_name' => $newFileName,
                    'file_type' => 'image/webp',
                    'file_size' => $newSize,
                ]);

                Log::info("Chat attachment #{$attachment->id} converted to WebP successfully.");
            }
        } catch (Exception $e) {
            Log::warning("ConvertChatImageToWebp job error: " . $e->getMessage());
        }
    }
}
