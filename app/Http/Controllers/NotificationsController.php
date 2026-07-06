<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationsController extends Controller
{
    public function index(Request $request)
    {
        $categories = Notification::categories();
        $activeCategory = $request->filled('category') && isset($categories[$request->category])
            ? $request->category
            : null;

        $query = Notification::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            match ($request->status) {
                'unread' => $query->whereNull('read_at'),
                'read'   => $query->whereNotNull('read_at'),
                default  => null,
            };
        }

        if ($activeCategory) {
            $query->whereIn('type', $categories[$activeCategory]['types']);
        }

        $notifications = $query->paginate(20)->withQueryString();
        $unreadCount   = Notification::where('user_id', auth()->id())->whereNull('read_at')->count();
        $users         = User::orderBy('name')->get();

        // Số chưa đọc theo từng type, gộp lại theo tab để hiển thị badge — 1 query duy nhất
        // thay vì lặp count() cho từng danh mục.
        $unreadByType = Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $categoryUnreadCounts = collect($categories)->map(
            fn($cat) => collect($cat['types'])->sum(fn($type) => $unreadByType[$type] ?? 0)
        );

        return view('notifications.index', compact(
            'notifications', 'unreadCount', 'users', 'categories', 'activeCategory', 'categoryUnreadCounts'
        ));
    }

    public function store(Request $request, NotificationService $service)
    {
        $validated = $request->validate([
            'target'  => 'required|in:all,user',
            'user_id' => 'required_if:target,user|nullable|exists:users,id',
            'title'   => 'required|string|max:255',
            'body'    => 'nullable|string|max:1000',
        ]);

        if ($validated['target'] === 'all') {
            $count = $service->sendToAll('general', $validated['title'], $validated['body'] ?? '', [], auth()->id());
            $msg   = "Đã gửi thông báo đến {$count} người dùng!";
        } else {
            $service->sendToUser(
                (int) $validated['user_id'],
                'general',
                $validated['title'],
                $validated['body'] ?? '',
                [],
                auth()->id()
            );
            $msg = 'Đã gửi thông báo!';
        }

        return back()->with('success', $msg);
    }

    public function show(Notification $notification)
    {
        $user = auth()->user();
        abort_if((int) $notification->user_id !== (int) $user->id && ! $user->can('create-notifications'), 403);
        $notification->markAsRead();

        $prev = Notification::where('user_id', auth()->id())
            ->where('id', '>', $notification->id)
            ->orderBy('id', 'asc')
            ->first();

        $next = Notification::where('user_id', auth()->id())
            ->where('id', '<', $notification->id)
            ->orderBy('id', 'desc')
            ->first();

        // Check if the linked entity still exists (may have been deleted)
        $linkedDeleted = false;
        $data = $notification->data ?? [];
        if (isset($data['penalty_id'])) {
            $linkedDeleted = ! \App\Models\Penalty::find($data['penalty_id']);
        } elseif (isset($data['reward_id'])) {
            $linkedDeleted = ! \App\Models\Reward::withTrashed()->find($data['reward_id']);
        } elseif (isset($data['report_id'])) {
            $linkedDeleted = ! \App\Models\EmployeeReport::withTrashed()->find($data['report_id']);
        }

        return view('notifications.show', compact('notification', 'prev', 'next', 'linkedDeleted'));
    }

    public function markRead(Notification $notification)
    {
        $user = auth()->user();
        abort_if((int) $notification->user_id !== (int) $user->id && ! $user->can('create-notifications'), 403);
        $notification->markAsRead();

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function markAllRead()
    {
        Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Đã đánh dấu tất cả là đã đọc!');
    }

    public function destroy(Notification $notification)
    {
        $user = auth()->user();
        abort_if((int) $notification->user_id !== (int) $user->id && ! $user->can('create-notifications'), 403);
        $notification->delete();

        return back()->with('success', 'Đã xóa thông báo!');
    }

    public function unreadCount()
    {
        $count = Notification::where('user_id', auth()->id())->whereNull('read_at')->count();
        return response()->json(['count' => $count]);
    }
}
