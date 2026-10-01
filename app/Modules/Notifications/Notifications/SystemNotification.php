<?php

namespace App\Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $data;

    /**
     * @param array $data ['title' => '..', 'body' => '..', 'url' => '..', 'icon' => '..', 'type' => '..']
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }


    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }


    public function toDatabase(object $notifiable): array
    {
        return $this->data;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'data' => $this->data,
            'created_at' => now()->toIso8601String(),
        ]);
    }
}