<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\CardComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CardMentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $card;
    public $comment;
    public $mentioner;

    public function __construct(Card $card, CardComment $comment, User $mentioner)
    {
        $this->card = $card;
        $this->comment = $comment;
        $this->mentioner = $mentioner;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type'        => 'card_mention',
            'title'       => 'You were mentioned',
            'message'     => "{$this->mentioner->name} mentioned you in a comment on card \"{$this->card->title}\"",
            'url'         => route('boards.show', $this->card->board->slug) . "?card={$this->card->id}",
            'icon'        => 'M15 11.25l1.5 1.5.75-.75V8.758l2.276-.61a3 3 0 10-3.675-3.675l-.61 2.277H12l-.75.75 1.5 1.5M7.11 9.69a9.006 9.006 0 00-6.104 2.89A5.992 5.992 0 0110.15 18a6.002 6.002 0 013.91-4.707 9.007 9.007 0 002.89-6.103M15 15.75v3A2.25 2.25 0 0112.75 21h-9A2.25 2.25 0 011.5 18.75v-9A2.25 2.25 0 013.75 7.5h3',
            'color'       => 'blue',
            'sender_name' => $this->mentioner->name,
            'sender_id'   => $this->mentioner->id,
            'avatar_url'  => $this->mentioner->avatar_url,
        ];
    }
}
