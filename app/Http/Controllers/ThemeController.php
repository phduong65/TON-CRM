<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function toggle(Request $request)
    {
        $user = $request->user();
        $user->theme = $user->theme === 'dark' ? 'light' : 'dark';
        $user->save();

        return back()->with('success', 'Đã chuyển đổi giao diện!');
    }
}
