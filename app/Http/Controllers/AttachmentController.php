<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    /**
     * Download an attachment.
     */
    public function download(Attachment $attachment)
    {
        if (!Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404, 'File not found in storage.');
        }

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    /**
     * View an attachment inline.
     */
    public function view(Attachment $attachment)
    {
        if (!Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404, 'File not found in storage.');
        }

        return Storage::disk($attachment->disk)->response($attachment->path);
    }

    /**
     * Delete an attachment.
     */
    public function destroy(Attachment $attachment)
    {
        // Check if user has permission to delete (you can customize this)
        // For now, allow any authenticated user to delete.
        if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $attachment->delete();

        return back()->with('success', 'Attachment deleted successfully.');
    }
}
