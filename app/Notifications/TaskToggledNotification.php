<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TaskToggledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Card $card,
        public readonly User $supervisor,
        public readonly bool $isApproved
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $statusStr = $this->isApproved ? 'approved' : 'un-approved';
        $titleStr  = $this->isApproved ? 'Task approved' : 'Task un-approved';

        $listName = current(array_filter([$this->card->list?->name, $this->card->boardList?->name, 'Final Captions'])) ?: 'Final Captions';

        return [
            'module'      => 'digital',
            'card_id'     => $this->card->id,
            'card_title'  => $this->card->title,
            'approved_by' => $this->supervisor->name,
            'type'        => 'task_toggled',
            'title'       => $titleStr,
            'message'     => "Supervisor {$this->supervisor->name} {$statusStr} the card '{$this->card->title}' on list '{$listName}'",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }
}
