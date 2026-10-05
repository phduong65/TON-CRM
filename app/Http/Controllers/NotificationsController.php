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

        if ($activeCategory) {
            $query->whereIn('type', $categories[$activeCategory]['types']);
        }

        // Số thông báo theo trạng thái đọc cho tab lọc — trong nhóm đang chọn, chưa lọc trạng thái
        $categoryTotal  = (clone $query)->count();
        $categoryUnread = (clone $query)->whereNull('read_at')->count();
        $statusCounts   = ['all' => $categoryTotal, 'unread' => $categoryUnread, 'read' => $categoryTotal - $categoryUnread];

        if ($request->filled('status')) {
            match ($request->status) {
                'unread' => $query->whereNull('read_at'),
                'read'   => $query->whereNotNull('read_at'),
                default  => null,
            };
        }

        $notifications = $query->paginate(20)->withQueryString();
        $unreadCount   = Notification::where('user_id', auth()->id())->whereNull('read_at')->count();
        // Danh sách người nhận chỉ cần cho modal "Tạo thông báo"
        $users         = auth()->user()->can('create-notifications') ? User::orderBy('name')->get(['id', 'name', 'email']) : collect();

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
            'notifications', 'unreadCount', 'statusCounts', 'users', 'categories', 'activeCategory', 'categoryUnreadCounts'
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

    public function show(Request $request, Notification $notification)
    {
        $user = auth()->user();
        $isOwner = (int) $notification->user_id === (int) $user->id;
        abort_if(! $isOwner && ! $user->can('create-notifications'), 403);

        // Chỉ chủ thông báo mới làm thay đổi trạng thái đọc — admin xem hộ không được đánh dấu đã đọc thay
        if ($isOwner) {
            $notification->markAsRead();
        }

        // Điều hướng trong hộp thư của người đang xem: id lớn hơn = mới hơn
        $newer = Notification::where('user_id', $user->id)
            ->where('id', '>', $notification->id)
            ->orderBy('id', 'asc')
            ->first(['id']);

        $older = Notification::where('user_id', $user->id)
            ->where('id', '<', $notification->id)
            ->orderBy('id', 'desc')
            ->first(['id']);

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

        // Tự động chuyển hướng thẳng tới đối tượng đích (phiếu phạt, thưởng, báo cáo...)
        // Trừ khi có tham số ?stay=1 (đang duyệt hộp thư) hoặc thực thể liên kết đã bị xoá
        if (! $linkedDeleted && ! $request->boolean('stay')) {
            $actionUrl = $notification->actionUrl();
            if ($actionUrl) {
                return redirect()->to($actionUrl);
            }
        }

        $notification->loadMissing(['creator', 'user']);

        return view('notifications.show', compact('notification', 'newer', 'older', 'linkedDeleted', 'isOwner'));
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
        $showUrl = route('notifications.show', $notification);
        $notification->delete();

        // Xoá từ chính trang chi tiết → về danh sách (quay lại trang đã xoá sẽ là 404)
        if (rtrim(url()->previous(), '/') === rtrim($showUrl, '/')) {
            return redirect()->route('notifications.index')->with('success', 'Đã xóa thông báo!');
        }

        return back()->with('success', 'Đã xóa thông báo!');
    }

    public function unreadCount()
    {
        $count = Notification::where('user_id', auth()->id())->whereNull('read_at')->count();
        return response()->json(['count' => $count]);
    }
}
