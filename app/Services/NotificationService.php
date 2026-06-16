<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Auth;

class NotificationService
{
    public static function notify(
        User    $user,
        string  $title,
        string  $body,
        string  $type = 'info',
        ?string $url  = null
    ): void {
        // Capture actor synchronously — Auth state may not be available
        // once inside the terminating callback.
        $actor = static::resolveActorLabel();

        app()->terminating(
            fn () => $user->notify(new SystemNotification($title, $body, $type, $url, $actor))
        );
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

    /** Returns "First Last · Role" for the currently authenticated user, or null. */
    private static function resolveActorLabel(): ?string
    {
        $actor = Auth::user();
        if (! $actor) {
            return null;
        }

        $role = $actor->role ? ucwords(str_replace('_', ' ', $actor->role)) : null;

        return $role ? "{$actor->full_name} · {$role}" : $actor->full_name;
    }
}
