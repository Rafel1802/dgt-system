<?php

use App\Models\ActivityLog;
use App\Models\Card;
use App\Models\CardFile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ensure folder_name column exists
        if (!Schema::hasColumn('card_files', 'folder_name')) {
            Schema::table('card_files', function (Blueprint $table) {
                $table->string('folder_name', 255)->nullable()->after('original_name')->index();
            });
        }

        // 2. Populate folder_name from original_name where path contains slash
        CardFile::where(function ($q) {
            $q->whereNull('folder_name')->orWhere('folder_name', '');
        })
        ->where('original_name', 'like', '%/%')
        ->chunkById(100, function ($files) {
            foreach ($files as $file) {
                $parts = explode('/', $file->original_name, 2);
                $folder = trim($parts[0]);
                if (!empty($folder)) {
                    $file->folder_name = $folder;
                    $file->saveQuietly();
                }
            }
        });

        // 3. Synchronize folder grouping across twin cards in the same sync_group_id
        Card::whereNotNull('sync_group_id')
            ->distinct()
            ->pluck('sync_group_id')
            ->each(function ($syncGroupId) {
                $groupCards = Card::where('sync_group_id', $syncGroupId)->with('files')->get();
                if ($groupCards->count() < 2) {
                    return;
                }

                $folderBySyncId = [];
                $folderByStoredName = [];
                $folderByBaseName = [];

                foreach ($groupCards as $gCard) {
                    foreach ($gCard->files as $f) {
                        $folder = !empty($f->folder_name) ? trim($f->folder_name) : null;
                        if (!$folder && $f->original_name && str_contains($f->original_name, '/')) {
                            $parts = explode('/', $f->original_name, 2);
                            $folder = trim($parts[0]) ?: null;
                        }

                        if ($folder) {
                            $base = basename($f->original_name);
                            if ($f->sync_id) {
                                $folderBySyncId[$f->sync_id] = $folder;
                            }
                            if ($f->stored_name) {
                                $folderByStoredName[$f->stored_name] = $folder;
                            }
                            if ($base) {
                                $folderByBaseName[$base] = $folder;
                            }
                        }
                    }
                }

                if (!empty($folderBySyncId) || !empty($folderByStoredName) || !empty($folderByBaseName)) {
                    foreach ($groupCards as $gCard) {
                        foreach ($gCard->files as $f) {
                            $currentFolder = !empty($f->folder_name) ? trim($f->folder_name) : null;
                            if (!$currentFolder && $f->original_name && str_contains($f->original_name, '/')) {
                                $parts = explode('/', $f->original_name, 2);
                                $currentFolder = trim($parts[0]) ?: null;
                            }

                            if (!$currentFolder) {
                                $matchedFolder = null;
                                $base = basename($f->original_name);

                                if ($f->sync_id && isset($folderBySyncId[$f->sync_id])) {
                                    $matchedFolder = $folderBySyncId[$f->sync_id];
                                } elseif ($f->stored_name && isset($folderByStoredName[$f->stored_name])) {
                                    $matchedFolder = $folderByStoredName[$f->stored_name];
                                } elseif ($base && isset($folderByBaseName[$base])) {
                                    $matchedFolder = $folderByBaseName[$base];
                                }

                                if ($matchedFolder) {
                                    $f->folder_name = $matchedFolder;
                                    $cleanName = basename($f->original_name);
                                    $f->original_name = "{$matchedFolder}/{$cleanName}";
                                    $f->saveQuietly();
                                }
                            }
                        }
                    }
                }
            });

        // 4. Auto-heal any card where ActivityLog shows folder_assigned but files are standalone
        ActivityLog::where('action', 'folder_assigned')
            ->orWhere('description', 'like', '%grouped % files into folder%')
            ->orderBy('created_at')
            ->chunkById(50, function ($logs) {
                foreach ($logs as $log) {
                    if (preg_match('/into folder\s+\*{0,2}([^*]+?)\*{0,2}(?:\s*\(|\s*$)/i', $log->description, $matches)) {
                        $targetFolder = trim($matches[1]);
                        if (empty($targetFolder)) {
                            continue;
                        }

                        $card = Card::find($log->subject_id);
                        if (!$card) {
                            continue;
                        }

                        $cardIds = $card->sync_group_id
                            ? Card::where('sync_group_id', $card->sync_group_id)->pluck('id')->all()
                            : [$card->id];

                        $deletedLater = ActivityLog::whereIn('subject_id', $cardIds)
                            ->where('action', 'folder_deleted')
                            ->where('description', 'like', "%{$targetFolder}%")
                            ->where('created_at', '>', $log->created_at)
                            ->exists();

                        if ($deletedLater) {
                            continue;
                        }

                        $targetCards = Card::whereIn('id', $cardIds)->with('files')->get();
                        foreach ($targetCards as $tCard) {
                            $hasFolderFiles = $tCard->files->contains(function ($file) use ($targetFolder) {
                                return $file->folder_name === $targetFolder || str_starts_with($file->original_name, "{$targetFolder}/");
                            });

                            if (!$hasFolderFiles) {
                                $standaloneFiles = $tCard->files->filter(function ($file) {
                                    return empty($file->folder_name)
                                        && !str_contains($file->original_name, '/')
                                        && $file->disk !== 'url'
                                        && $file->mime_type !== 'link';
                                });

                                foreach ($standaloneFiles as $sf) {
                                    $sf->folder_name = $targetFolder;
                                    $sf->original_name = "{$targetFolder}/{$sf->original_name}";
                                    $sf->saveQuietly();
                                }
                            }
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        // Non-destructive rollback
    }
};
