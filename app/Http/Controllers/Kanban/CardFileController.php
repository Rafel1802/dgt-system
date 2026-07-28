<?php

namespace App\Http\Controllers\Kanban;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardFile;
use App\Services\KanbanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CardFileController extends Controller
{
    public function __construct(
        private readonly KanbanService $kanbanService
    ) {}

    /**
     * Upload files to a card (supports multiple).
     * Max 10MB per file. Types: images, pdf, doc, xls, zip.
     */
    public function store(Request $request, Card $card): JsonResponse
    {
        $this->authorize('upload', $card);

        $request->validate([
            'files'   => ['required', 'array', 'max:5'],
            'files.*' => [
                'required',
                'file',
                'max:10240',  // 10MB
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,zip,rar,mp4,mov',
            ],
        ]);

        $uploaded = [];
        foreach ($request->file('files') as $file) {
            $uploaded[] = $this->kanbanService->uploadFile($card, $file, auth()->user());
        }

        return response()->json([
            'success' => true,
            'files'   => collect($uploaded)->map(fn($f) => [
                'id'            => $f->id,
                'original_name' => $f->original_name,
                'formatted_size'=> $f->formatted_size,
                'icon'          => $f->icon,
                'is_image'      => $f->isImage(),
                'url'           => $f->url,
            ]),
        ], 201);
    }

    /**
     * Download / serve a file securely (no direct storage access).
     */
    public function download(CardFile $file): mixed
    {
        $this->authorize('view', $file->card);

        if (! Storage::exists($file->path)) {
            abort(404, 'File not found.');
        }

        return Storage::download($file->path, $file->original_name);
    }

    /**
     * Delete a file from storage and database.
     */
    public function destroy(Card $card, CardFile $file): JsonResponse
    {
        $this->authorize('upload', $card);

        $this->kanbanService->deleteFile($file, auth()->user());

        return response()->json(['success' => true]);
    }
}
