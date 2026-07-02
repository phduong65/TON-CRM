<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidaysController extends Controller
{
    public function index(Request $request)
    {
        $query = Holiday::orderByDesc('date');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('name', 'like', "%$s%");
        }

        if ($request->filled('year')) {
            $query->whereYear('date', $request->year);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === '1');
        }

        $holidays = $query->paginate(15)->withQueryString();

        return view('holidays.index', compact('holidays'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'         => 'required|date|unique:holidays,date',
            'name'         => 'required|string|max:150',
            'is_paid'      => 'boolean',
            'bonus_amount' => 'nullable|numeric|min:0',
            'is_active'    => 'boolean',
        ]);

        $validated['is_paid']    = $request->boolean('is_paid', true);
        $validated['is_active']  = $request->boolean('is_active', true);
        $validated['created_by'] = auth()->id();

        Holiday::create($validated);

        return redirect()->route('holidays.index')->with('success', 'Đã thêm ngày nghỉ lễ!');
    }

    public function update(Request $request, Holiday $holiday)
    {
        $validated = $request->validate([
            'date'         => 'required|date|unique:holidays,date,' . $holiday->id,
            'name'         => 'required|string|max:150',
            'is_paid'      => 'boolean',
            'bonus_amount' => 'nullable|numeric|min:0',
            'is_active'    => 'boolean',
        ]);

        $validated['is_paid']   = $request->boolean('is_paid');
        $validated['is_active'] = $request->boolean('is_active');

        $holiday->update($validated);

        return redirect()->route('holidays.index')->with('success', 'Đã cập nhật ngày nghỉ lễ!');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->update(['is_active' => false]);

        return back()->with('success', 'Đã vô hiệu hóa ngày nghỉ lễ!');
    }
}
