<?php

namespace App\Http\Controllers;

use App\Models\Penalty;
use App\Models\Setting;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(PushNotificationService $pushService)
    {
        $settings = Setting::all()->keyBy('key');
        $totalMoneyDeducted = (float) Penalty::where('status', 'approved')->sum('total_money_deducted');
        $firebaseEnabled = $pushService->isEnabled();
        return view('settings.index', compact('settings', 'totalMoneyDeducted', 'firebaseEnabled'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string|max:255',
        ]);

        foreach ($validated['settings'] as $key => $value) {
            Setting::setValue($key, $value);
        }

        return back()->with('success', 'Cài đặt đã được cập nhật!');
    }

    /**
     * Gửi 1 thông báo đẩy test tới trình duyệt hiện tại của người dùng để kiểm tra kết nối
     * Firebase Cloud Messaging có hoạt động không — trả JSON cho nút "Kiểm tra kết nối" ở
     * trang Cài đặt (fetch() thuần, không reload trang).
     */
    public function testFirebase(Request $request, PushNotificationService $pushService)
    {
        $result = $pushService->testConnection(auth()->id());

        return response()->json($result);
    }

    /**
     * Kiểm tra cron job (Laravel Scheduler) trên server có đang thực sự chạy hay không — dựa vào
     * "nhịp tim" mà routes/console.php ghi lại mỗi phút vào settings.scheduler_heartbeat_at. Nếu
     * cron thật sự chạy mỗi phút, mốc thời gian này không bao giờ trễ quá ~2 phút.
     */
    public function checkScheduler(Request $request)
    {
        $lastHeartbeat = Setting::getValue('scheduler_heartbeat_at');

        if (!$lastHeartbeat) {
            return response()->json(['ok' => false, 'reason' => 'never_ran']);
        }

        $secondsAgo = abs(now()->diffInSeconds(\Carbon\Carbon::parse($lastHeartbeat)));
        $isHealthy  = $secondsAgo <= 150; // cho phép trễ tối đa ~2.5 nhịp (mỗi nhịp = 1 phút)

        return response()->json([
            'ok'                => $isHealthy,
            'reason'            => $isHealthy ? null : 'stale',
            'last_heartbeat_at' => $lastHeartbeat,
            'minutes_ago'       => (int) floor($secondsAgo / 60),
        ]);
    }
}
