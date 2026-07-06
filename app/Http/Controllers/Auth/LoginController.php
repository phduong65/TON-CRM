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
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $status = Auth::user()->status;

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
