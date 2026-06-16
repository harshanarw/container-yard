<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /** AJAX — unread notifications for the current user. */
    public function unread(): JsonResponse
    {
        $user  = auth()->user();
        $count = $user->unreadNotifications()->count();

        $items = $user->unreadNotifications()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($n) => [
                'id'    => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'body'  => $n->data['body']  ?? '',
                'type'  => $n->data['type']  ?? 'info',
                'url'   => $n->data['url']   ?? null,
                'actor' => $n->data['actor'] ?? null,
                'at'    => $n->created_at->diffForHumans(),
                'ts'    => $n->created_at->timestamp,
            ]);

        return response()->json(compact('count', 'items'));
    }

    /** AJAX — mark one notification as read. */
    public function markRead(string $id): JsonResponse
    {
        auth()->user()->notifications()->where('id', $id)->first()?->markAsRead();
        return response()->json(['ok' => true]);
    }

    /** AJAX — mark all notifications as read. */
    public function markAllRead(): JsonResponse
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    /** Full notification history page. */
    public function index()
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->paginate(30);

        return view('notifications.index', compact('notifications'));
    }
}
