<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\NewUserRegisteredMail;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Tự đăng ký KHÔNG được tự động đăng nhập / cấp quyền truy cập — tài khoản tạo ra ở trạng
     * thái 'pending' (không có vai trò), chỉ có thể đăng nhập sau khi Admin duyệt (gán vai trò +
     * kích hoạt) qua Quản lý người dùng. Admin được báo qua thông báo trong app + email SMTP.
     * Xem LoginController (chặn đăng nhập khi 'pending') và UsersController::update()/reject().
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => 'pending',
        ]);

        app(NotificationService::class)->notifyUserRegistered($user);

        $admins = User::permission('manage-users')->where('status', 'active')->get();

        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new NewUserRegisteredMail($user));
            } catch (\Throwable $e) {
                // Không để lỗi SMTP (chưa cấu hình / sai thông tin) làm hỏng luồng đăng ký —
                // admin vẫn nhận được thông báo trong app dù email gửi thất bại.
                Log::error('Failed to send new-user-registered email: ' . $e->getMessage());
            }
        }

        return redirect()->route('login')->with(
            'status',
            'Đăng ký thành công! Tài khoản của bạn đang chờ quản trị viên duyệt — bạn sẽ nhận được email khi tài khoản được kích hoạt.'
        );
    }
}
