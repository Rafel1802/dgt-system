<?php

namespace App\Console\Commands\Chat;

use App\Models\Chat\ChatMessageAttachment;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ConvertChatImagesToWebp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:convert-images-webp {--force : Convert without waiting for 5-minute age} {--limit=100 : Maximum number of images to process in one run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically converts chat image attachments older than 5 minutes to WebP format to save server storage space.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!function_exists('imagewebp')) {
            $this->error('GD extension with WebP support is not available on this server.');
            return self::FAILURE;
        }

        $query = ChatMessageAttachment::where('file_type', 'like', 'image/%')
            ->where('file_type', '!=', 'image/webp')
            ->whereNotNull('file_path');

        if (!$this->option('force')) {
            $query->where('created_at', '<=', now()->subMinutes(5));
        }

        $limit = (int) $this->option('limit');
        $attachments = $query->limit($limit)->get();

        if ($attachments->isEmpty()) {
            $this->info('No eligible chat images found for WebP conversion.');
            return self::SUCCESS;
        }

        $this->info("Processing {$attachments->count()} chat images for WebP compression...");

        $convertedCount = 0;
        $totalBytesSaved = 0;

        foreach ($attachments as $attachment) {
            $originalRelative = $attachment->file_path;
            $disk = Storage::disk('public');

            if (!$disk->exists($originalRelative)) {
                continue;
            }

            $originalFullPath = $disk->path($originalRelative);
            $originalSize = @filesize($originalFullPath) ?: $attachment->file_size;

            try {
                $rawContent = @file_get_contents($originalFullPath);
                if (!$rawContent) {
                    continue;
                }

                $gdImage = @imagecreatefromstring($rawContent);
                if (!$gdImage) {
                    continue;
                }

                // Preserve transparency for PNG and alpha-channel images
                imagealphablending($gdImage, false);
                imagesavealpha($gdImage, true);

                $pathInfo = pathinfo($originalRelative);
                $webpRelative = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
                $webpFullPath = $disk->path($webpRelative);

                // Save as WebP with high quality (82%) compression
                $success = @imagewebp($gdImage, $webpFullPath, 82);
                imagedestroy($gdImage);

                if ($success && file_exists($webpFullPath) && filesize($webpFullPath) > 0) {
                    $newSize = filesize($webpFullPath);

                    // If not the same file path, delete the older original file
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

                    $bytesSaved = max(0, $originalSize - $newSize);
                    $totalBytesSaved += $bytesSaved;
                    $convertedCount++;
                }
            } catch (Exception $e) {
                Log::warning("WebP conversion failed for chat attachment #{$attachment->id}: " . $e->getMessage());
            }
        }

        $formattedSaved = self::formatBytes($totalBytesSaved);
        $this->info("Successfully converted {$convertedCount} image(s) to WebP. Saved: {$formattedSaved} of disk storage.");

        return self::SUCCESS;
    }

    /**
     * Format bytes into readable format.
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
