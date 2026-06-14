<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotification;

class NotificationService
{
    public static function notify(
        User    $user,
        string  $title,
        string  $body,
        string  $type = 'info',
        ?string $url  = null
    ): void {
        $user->notify(new SystemNotification($title, $body, $type, $url));
    }

    /** Notify all active users — use sparingly (iterates every user). */
    public static function notifyAll(
        string  $title,
        string  $body,
        string  $type = 'info',
        ?string $url  = null
    ): void {
        User::where('status', 'active')->each(
            fn ($u) => static::notify($u, $title, $body, $type, $url)
        );
    }
}
