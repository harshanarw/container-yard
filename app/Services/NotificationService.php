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
        // Resolve the acting user synchronously — Auth state may not be
        // reliably available once we're inside the terminating callback.
        $body = static::appendActor($body);

        // Defer until after the HTTP response is sent so the WebSocket message
        // arrives on the redirected page, not on the form page being left.
        app()->terminating(
            fn () => $user->notify(new SystemNotification($title, $body, $type, $url))
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

    /** Append "— by First Last (Role)" naming whoever triggered the action. */
    private static function appendActor(string $body): string
    {
        $actor = Auth::user();
        if (! $actor) {
            return $body;
        }

        $role  = $actor->role ? ucwords(str_replace('_', ' ', $actor->role)) : null;
        $label = $role ? "{$actor->full_name} ({$role})" : $actor->full_name;

        return $body ? "{$body} — by {$label}" : "by {$label}";
    }
}
