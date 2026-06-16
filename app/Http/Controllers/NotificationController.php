<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

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

    /** Mark one notification as read (AJAX or form POST). */
    public function markRead(string $id): JsonResponse|RedirectResponse
    {
        auth()->user()->notifications()->where('id', $id)->first()?->markAsRead();
        if (request()->ajax()) {
            return response()->json(['ok' => true]);
        }
        return back();
    }

    /** Mark all notifications as read (AJAX or form POST). */
    public function markAllRead(): JsonResponse|RedirectResponse
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        if (request()->ajax()) {
            return response()->json(['ok' => true]);
        }
        return back();
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
