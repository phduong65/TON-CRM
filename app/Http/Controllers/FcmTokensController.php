<?php

namespace App\Http\Controllers;

use App\Models\FcmToken;
use Illuminate\Http\Request;

class FcmTokensController extends Controller
{
    /**
     * Đăng ký (hoặc cập nhật lại chủ sở hữu) 1 FCM registration token của trình duyệt hiện tại
     * cho user đang đăng nhập — hành động cá nhân, không cần permission riêng.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:512',
        ]);

        FcmToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id'    => auth()->id(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Huỷ đăng ký nhận thông báo đẩy trên trình duyệt hiện tại (khi user tắt nút thông báo).
     * Chỉ xoá token thuộc về chính user đang đăng nhập.
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:512',
        ]);

        FcmToken::where('token', $validated['token'])
            ->where('user_id', auth()->id())
            ->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Service worker Firebase Messaging — render Blade thành JS thuần (KHÔNG qua Vite, cần
     * URL ổn định/không hash để trình duyệt cache & scope đúng gốc "/"). Public, không qua
     * middleware auth (fetch service worker không kèm session cookie theo cách thông thường).
     */
    public function serviceWorker()
    {
        return response()
            ->view('firebase-messaging-sw', ['config' => config('services.firebase.web')])
            ->header('Content-Type', 'application/javascript');
    }
}
