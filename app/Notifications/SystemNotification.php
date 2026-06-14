<?php

namespace App\Notifications;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification implements ShouldBroadcast
{
    public function __construct(
        public readonly string  $title,
        public readonly string  $body,
        public readonly string  $type = 'info',
        public readonly ?string $url  = null,
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database'];
        if (config('broadcasting.default') !== 'null') {
            $channels[] = 'broadcast';
        }
        return $channels;
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
            'type'  => $this->type,
            'url'   => $this->url,
        ];
    }

    public function broadcastOn($notifiable): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.' . $notifiable->id);
    }

    public function broadcastAs(): string
    {
        return 'notification.new';
    }

    public function broadcastWith($notifiable): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
            'type'  => $this->type,
            'url'   => $this->url,
            'ts'    => now()->timestamp,
        ];
    }
}
