<?php

namespace App\Http\Controllers;

use App\Models\Penalty;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->keyBy('key');
        $totalMoneyDeducted = (float) Penalty::where('status', 'approved')->sum('total_money_deducted');
        return view('settings.index', compact('settings', 'totalMoneyDeducted'));
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
