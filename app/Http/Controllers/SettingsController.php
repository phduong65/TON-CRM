<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->keyBy('key');
        return view('settings.index', compact('settings'));
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
}
