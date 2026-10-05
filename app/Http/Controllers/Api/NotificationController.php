<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            match ($request->status) {
                'unread' => $query->whereNull('read_at'),
                'read' => $query->whereNotNull('read_at'),
                default => null,
            };
        }

        $notifications = $query->paginate(20);
        $notifications->getCollection()->transform(fn (Notification $n) => [
            'id' => $n->id,
            'type' => $n->type,
            'type_label' => $n->typeLabel(),
            'title' => $n->title,
            'body' => $n->body,
            'is_unread' => $n->isUnread(),
            // Mobile clients expect this field to always be a JSON object.
            // PHP encodes an empty associative array as [], so normalize it.
            'data' => empty($n->data) ? (object) [] : $n->data,
            'created_at' => $n->created_at->toIso8601String(),
        ]);

        return response()->json($notifications);
    }

    public function unreadCount(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)->whereNull('read_at')->count();

        return response()->json(['count' => $count]);
    }

    public function markRead(Request $request, Notification $notification)
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);
        $notification->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
