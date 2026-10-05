<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $credentials = $request->only('email', 'password');

        // Luôn ghi nhớ đăng nhập (remember_token) — nhân viên dùng thiết bị dùng chung tại
        // chi nhánh, không nên bắt họ tự chọn "Ghi nhớ đăng nhập"; tương tự cách Facebook/Google
        // giữ đăng nhập lâu dài bằng cookie riêng, tách biệt với session hết hạn theo hoạt động.
        if (Auth::attempt($credentials, true)) {
            $user = Auth::user();
            $status = $user->status;

            if ($status !== 'active') {
                Auth::logout();
                $request->session()->invalidate();

                $message = match ($status) {
                    'pending' => 'Tài khoản của bạn đang chờ quản trị viên duyệt. Vui lòng đợi email xác nhận trước khi đăng nhập.',
                    default   => 'Tài khoản của bạn đã bị tạm khóa. Vui lòng liên hệ quản trị viên.',
                };

                throw ValidationException::withMessages(['email' => $message]);
            }

            $request->session()->regenerate();

            if ($user->hasRole('admin')) {
                $lat = $request->input('lat');
                $lng = $request->input('lng');

                activity()->causedBy($user)
                    ->performedOn($user)
                    ->inLog('login')
                    ->withProperties([
                        'ip'       => $request->ip(),
                        'device'   => $request->userAgent(),
                        'location' => ($lat !== null && $lng !== null) ? "$lat, $lng" : 'Không xác định',
                    ])
                    ->log('Đăng nhập tài khoản quản trị viên: ' . $user->name);
            }

            return redirect()->intended('/dashboard');
        }

        throw ValidationException::withMessages([
            'email' => __('Email hoặc mật khẩu không chính xác.'),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
