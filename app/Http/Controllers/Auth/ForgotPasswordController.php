<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showLinkRequestForm()
    {
        return view('auth.passwords.email');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // In a production app, send password reset email here.
        // For now, redirect back with a status message.
        return back()->with('status', 'Nếu email tồn tại trong hệ thống, bạn sẽ nhận được link đặt lại mật khẩu.');
    }
}
