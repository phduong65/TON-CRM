<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PositionsController extends Controller
{
    public function index(Request $request)
    {
        $query = Position::withCount('employees')->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('name', 'like', "%$s%");
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === '1');
        }

        $positions = $query->paginate(15)->withQueryString();

        return view('positions.index', compact('positions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:150', Rule::unique('positions', 'name')->whereNull('deleted_at')],
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Position::create($validated);

        return redirect()->route('positions.index')->with('success', 'Đã thêm chức danh!');
    }

    public function update(Request $request, Position $position)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:150', Rule::unique('positions', 'name')->ignore($position->id)->whereNull('deleted_at')],
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $position->update($validated);

        return redirect()->route('positions.index')->with('success', 'Đã cập nhật chức danh!');
    }

    public function destroy(Position $position)
    {
        if ($position->employees()->exists()) {
            $position->update(['is_active' => false]);

            return back()->with('success', 'Chức danh đang được gán cho nhân viên — đã vô hiệu hóa thay vì xóa.');
        }

        $position->delete();

        return back()->with('success', 'Đã xóa chức danh!');
    }
}
