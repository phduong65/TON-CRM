<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * "Đăng nhập hộ" — admin đăng nhập tạm thời vào tài khoản của một nhân viên khác mà không cần
 * biết mật khẩu thật của họ, dùng session key `impersonator_id` để nhớ danh tính admin gốc và
 * quay lại được. Theo yêu cầu tường minh của người dùng, tính năng này KHÔNG ghi audit log
 * (spatie/laravel-activitylog) — khác với quy ước audit log mặc định của các action khác trong
 * dự án (xem CLAUDE.md).
 */
class ImpersonateController extends Controller
{
    public function store(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Không thể đăng nhập hộ chính tài khoản đang đăng nhập.');
        }

        if (session()->has('impersonator_id')) {
            return back()->with('error', 'Đang trong chế độ đăng nhập hộ — thoát ra trước khi đăng nhập hộ tài khoản khác.');
        }

        if ($user->hasRole('admin')) {
            return back()->with('error', 'Không thể đăng nhập hộ tài khoản quản trị viên khác.');
        }

        if ($user->status !== 'active') {
            return back()->with('error', 'Chỉ có thể đăng nhập hộ tài khoản đang hoạt động.');
        }

        session(['impersonator_id' => auth()->id()]);
        Auth::login($user);

        return redirect()->route('dashboard')->with('success', 'Đang đăng nhập hộ "' . $user->name . '".');
    }

    public function destroy()
    {
        $impersonatorId = session('impersonator_id');
        session()->forget('impersonator_id');

        if (!$impersonatorId) {
            return redirect()->route('dashboard');
        }

        $admin = User::find($impersonatorId);
        if ($admin) {
            Auth::login($admin);
        }

        return redirect()->route('users.index')->with('success', 'Đã thoát chế độ đăng nhập hộ.');
    }
}
