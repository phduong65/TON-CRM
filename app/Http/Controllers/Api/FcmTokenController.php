<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    /**
     * Đăng ký FCM registration token của app mobile — dùng chung bảng fcm_tokens với Web Push,
     * PushNotificationService::sendToUser() gửi multicast tới mọi token (web lẫn mobile) của user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate(['token' => 'required|string|max:512']);

        FcmToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id'    => $request->user()->id,
                'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: 'mobile-app',
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate(['token' => 'required|string|max:512']);

        FcmToken::where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }
}
