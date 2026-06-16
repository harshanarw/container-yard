<?php

namespace App\Notifications;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification implements ShouldBroadcast
{
    public function __construct(
        public readonly string  $title,
        public readonly string  $body,
        public readonly string  $type  = 'info',
        public readonly ?string $url   = null,
        public readonly ?string $actor = null,
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
            'actor' => $this->actor,
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.new';
    }

    public function broadcastWith(): array
    {
        return [
            'id'    => $this->id,
            'title' => $this->title,
            'body'  => $this->body,
            'type'  => $this->type,
            'url'   => $this->url,
            'actor' => $this->actor,
            'ts'    => now()->timestamp,
        ];
    }
}
