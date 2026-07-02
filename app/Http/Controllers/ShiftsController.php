<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\UpdateShiftRequest;
use App\Models\Branch;
use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftsController extends Controller
{
    public function index(Request $request)
    {
        $query = Shift::with('branch')->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
        }

        if ($request->filled('work_mode')) {
            $query->where('work_mode', $request->work_mode);
        }

        if ($request->filled('shift_type')) {
            $query->where('shift_type', $request->shift_type);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $shifts   = $query->paginate(15)->withQueryString();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        return view('shifts.index', compact('shifts', 'branches'));
    }

    public function store(StoreShiftRequest $request)
    {
        Shift::create($request->validated());

        return redirect()->route('shifts.index')->with('success', 'Đã tạo ca làm việc!');
    }

    public function update(UpdateShiftRequest $request, Shift $shift)
    {
        $shift->update($request->validated());

        return redirect()->route('shifts.index')->with('success', 'Đã cập nhật ca làm việc!');
    }

    public function destroy(Shift $shift)
    {
        $shift->update(['is_active' => false]);

        return redirect()->route('shifts.index')->with('success', 'Đã vô hiệu hóa ca làm việc!');
    }
}
