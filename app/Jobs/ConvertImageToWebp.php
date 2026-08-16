<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ConvertImageToWebp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $modelClass;
    public $modelId;
    public $column;
    public $attachmentId;
    public $oldPath;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($modelClass, $modelId, $column, $oldPath, $attachmentId = null)
    {
        $this->modelClass = $modelClass;
        $this->modelId = $modelId;
        $this->column = $column;
        $this->oldPath = $oldPath;
        $this->attachmentId = $attachmentId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!Storage::disk('public')->exists($this->oldPath)) {
            return;
        }

        $extension = strtolower(pathinfo($this->oldPath, PATHINFO_EXTENSION));
        if (in_array($extension, ['webp', 'pdf', 'svg'])) {
            return;
        }

        $fullPath = Storage::disk('public')->path($this->oldPath);
        
        // Attempt to create GD image
        $image = null;
        if (in_array($extension, ['jpg', 'jpeg'])) {
            $image = @imagecreatefromjpeg($fullPath);
        } elseif ($extension === 'png') {
            $image = @imagecreatefrompng($fullPath);
            if ($image) {
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }
        } else {
            $content = file_get_contents($fullPath);
            if ($content) {
                $image = @imagecreatefromstring($content);
            }
        }

        if (!$image) {
            Log::warning("ConvertImageToWebp: Could not read image at {$this->oldPath}");
            return;
        }

        if (!$this->modelClass) {
            // Overwrite original file directly, keep extension to avoid complex DB JSON updates
            imagewebp($image, $fullPath, 80);
            imagedestroy($image);
            return;
        }

        $newPath = preg_replace('/\.[a-zA-Z0-9]+$/', '_' . time() . '.webp', $this->oldPath);
        $newFullPath = Storage::disk('public')->path($newPath);

        // Save as webp with 80 quality
        if (imagewebp($image, $newFullPath, 80)) {
            imagedestroy($image);

            // Fetch the model
            $model = $this->modelClass::find($this->modelId);
            if (!$model) {
                // If model was deleted, clean up the new file
                Storage::disk('public')->delete($newPath);
                return;
            }

            $size = filesize($newFullPath);
            $mime = 'image/webp';

            // Update database
            if ($this->attachmentId) {
                // JSON array column
                $attachments = $model->{$this->column} ?? [];
                if (!is_array($attachments)) {
                    $attachments = json_decode($attachments, true) ?? [];
                }

                $updated = false;
                foreach ($attachments as &$attachment) {
                    if (isset($attachment['id']) && $attachment['id'] === $this->attachmentId) {
                        $attachment['path'] = $newPath;
                        $attachment['name'] = preg_replace('/\.[a-zA-Z0-9]+$/', '.webp', $attachment['name'] ?? 'image.webp');
                        $attachment['mime'] = $mime;
                        $attachment['size'] = $size;
                        $updated = true;
                        break;
                    }
                }

                if ($updated) {
                    $model->{$this->column} = array_values($attachments);
                    // Also check if we need to update attachment_path / attachment_name
                    if (isset($model->attachment_path) && $model->attachment_path === $this->oldPath) {
                        $model->attachment_path = $newPath;
                        $model->attachment_name = preg_replace('/\.[a-zA-Z0-9]+$/', '.webp', $model->attachment_name ?? 'image.webp');
                    }
                    $model->save();
                    Storage::disk('public')->delete($this->oldPath);
                } else {
                    // Revert if attachment was not found in array (maybe deleted by user)
                    Storage::disk('public')->delete($newPath);
                }
            } else {
                // Direct column
                if ($model->{$this->column} === $this->oldPath) {
                    $model->{$this->column} = $newPath;
                    // If error_attachment_name exists, update it too
                    if (isset($model->error_attachment_name) && str_ends_with($this->column, 'path')) {
                        $model->error_attachment_name = preg_replace('/\.[a-zA-Z0-9]+$/', '.webp', $model->error_attachment_name ?? 'image.webp');
                    }
                    $model->save();
                    Storage::disk('public')->delete($this->oldPath);
                } else {
                    Storage::disk('public')->delete($newPath);
                }
            }
        } else {
            imagedestroy($image);
        }
    }
}
