<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\CardFile;
use App\Models\User;
use App\Jobs\SendTaskApprovalEmailJob;
use App\Jobs\SendTaskRejectionEmailJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KanbanService
{
    /**
     * Get all cards grouped by status for the board view.
     * Respects role-based visibility.
     */
    public function getBoardData(User $user): array
    {
        $query = Card::with([
            'creator:id,name,avatar',
            'assignees:id,name,avatar',
            'checklists.items',
            'files',
            'comments',
        ])->orderBy('position');

        // Staff/digital-team only see their own cards + assigned cards
        if ($user->hasAnyRole(['staff', 'digital-team', 'sales-crm'])) {
            $query->forUser($user->id);
        }

        $cards = $query->get();

        $columns = [];
        foreach (CardStatus::columns() as $status) {
            $columns[$status->value] = [
                'status'  => $status,
                'cards'   => $cards->where('status', $status)->values(),
            ];
        }

        return $columns;
    }

    /**
     * Create a new card with assignees.
     */
    public function createCard(array $data, User $creator): Card
    {
        return DB::transaction(function () use ($data, $creator) {
            $card = Card::create([
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'label'       => $data['label'],
                'sub_label'   => $data['sub_label'] ?? null,
                'priority'    => $data['priority'] ?? 'medium',
                'status'      => CardStatus::Todo->value,
                'deadline'    => $data['deadline'] ?? null,
                'created_by'  => $creator->id,
                'position'    => Card::where('status', CardStatus::Todo->value)->max('position') + 1,
            ]);

            // Assign users
            if (! empty($data['assignees'])) {
                $card->assignees()->attach($data['assignees'], ['assigned_at' => now()]);
            }

            // System comment
            $this->addSystemComment($card, $creator, "Task created by {$creator->name}.");

            return $card;
        });
    }

    /**
     * Update card details.
     */
    public function updateCard(Card $card, array $data, User $updater): Card
    {
        return DB::transaction(function () use ($card, $data, $updater) {
            $old = $card->only(['title', 'label', 'priority', 'deadline']);

            $card->update([
                'title'       => $data['title'],
                'description' => $data['description'] ?? $card->description,
                'label'       => $data['label'],
                'sub_label'   => $data['sub_label'] ?? null,
                'priority'    => $data['priority'] ?? $card->priority,
                'deadline'    => $data['deadline'] ?? $card->deadline,
            ]);

            // Sync assignees
            if (array_key_exists('assignees', $data)) {
                $card->assignees()->sync(
                    collect($data['assignees'])->mapWithKeys(fn($id) => [$id => ['assigned_at' => now()]])->all()
                );
            }

            $changes = [];
            if ($old['title'] !== $data['title']) $changes[] = "title updated";
            if ($old['label'] !== $data['label']) $changes[] = "label changed to {$data['label']}";

            if ($changes) {
                $this->addSystemComment($card, $updater, "Card updated by {$updater->name}: " . implode(', ', $changes) . '.');
            }

            return $card->fresh();
        });
    }

    /**
     * Move card to a new status (drag-drop or button action).
     * Validates the transition is allowed for this user's role.
     */
    public function moveCard(Card $card, CardStatus $newStatus, int $position, User $mover): Card
    {
        return DB::transaction(function () use ($card, $newStatus, $position, $mover) {
            $oldStatus = $card->status;

            $card->update([
                'status'   => $newStatus->value,
                'position' => $position,
            ]);

            $this->addSystemComment(
                $card,
                $mover,
                "Card moved from **{$oldStatus->label()}** to **{$newStatus->label()}** by {$mover->name}."
            );

            return $card;
        });
    }

    /**
     * Supervisor approves a card.
     * Sends email notification to all Boss-role users.
     */
    public function approveCard(Card $card, User $supervisor): Card
    {
        return DB::transaction(function () use ($card, $supervisor) {
            $card->update([
                'status'      => CardStatus::Approved->value,
                'approved_by' => $supervisor->id,
                'approved_at' => now(),
                'reviewed_by' => $supervisor->id,
                'reviewed_at' => now(),
            ]);

            $this->addSystemComment($card, $supervisor, "✅ Task **approved** by {$supervisor->name}.");

            // Dispatch a queued job to email Boss users + creator
            SendTaskApprovalEmailJob::dispatch($card, $supervisor)
                ->onQueue('emails');

            return $card;
        });
    }

    /**
     * Supervisor rejects a card and notifies the creator.
     */
    public function rejectCard(Card $card, User $supervisor, string $reason): Card
    {
        return DB::transaction(function () use ($card, $supervisor, $reason) {
            $card->update([
                'status'           => CardStatus::Rejected->value,
                'rejection_reason' => $reason,
                'reviewed_by'      => $supervisor->id,
                'reviewed_at'      => now(),
            ]);

            $this->addSystemComment(
                $card,
                $supervisor,
                "❌ Task **rejected** by {$supervisor->name}. Reason: {$reason}"
            );

            // Dispatch queued job to notify creator
            SendTaskRejectionEmailJob::dispatch($card, $supervisor, $reason)
                ->onQueue('emails');

            return $card;
        });
    }

    /**
     * Upload a file attachment to a card.
     */
    public function uploadFile(Card $card, UploadedFile $file, User $uploader): CardFile
    {
        $disk = config('filesystems.default', 'local');
        $storedName = $file->hashName();
        $path = $file->storeAs("kanban/{$card->id}", $storedName, $disk);

        $cardFile = CardFile::create([
            'card_id'       => $card->id,
            'uploaded_by'   => $uploader->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_name'   => $storedName,
            'disk'          => $disk,
            'path'          => $path,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
        ]);

        return $cardFile;
    }

    /**
     * Delete a file from storage and DB.
     */
    public function deleteFile(CardFile $file, User $user): void
    {
        Storage::delete($file->path);
        $file->delete();
    }

    /**
     * Reorder cards within a column after a drag-drop.
     */
    public function reorderCards(array $orderedIds, CardStatus $status): void
    {
        foreach ($orderedIds as $position => $cardId) {
            Card::where('id', $cardId)->where('status', $status->value)
                ->update(['position' => $position + 1]);
        }
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function addSystemComment(Card $card, User $user, string $message): void
    {
        $card->comments()->create([
            'user_id'   => $user->id,
            'content'   => $message,
            'is_system' => true,
        ]);
    }
}
