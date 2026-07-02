<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceLocationRequest;
use App\Http\Requests\UpdateAttendanceLocationRequest;
use App\Models\AttendanceLocation;
use App\Models\Branch;
use Illuminate\Http\Request;

class AttendanceLocationsController extends Controller
{
    public function index(Request $request)
    {
        $query = AttendanceLocation::with('branch')->orderBy('name');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $locations = $query->paginate(15)->withQueryString();
        $branches  = Branch::where('is_active', true)->orderBy('name')->get();

        return view('attendance-locations.index', compact('locations', 'branches'));
    }

    public function store(StoreAttendanceLocationRequest $request)
    {
        $data = $request->validated();
        $data['allowed_ips'] = $request->allowedIpsArray();

        AttendanceLocation::create($data);

        return redirect()->route('attendance-locations.index')->with('success', 'Đã tạo điểm chấm công!');
    }

    public function update(UpdateAttendanceLocationRequest $request, AttendanceLocation $attendanceLocation)
    {
        $data = $request->validated();
        $data['allowed_ips'] = $request->allowedIpsArray();

        $attendanceLocation->update($data);

        return redirect()->route('attendance-locations.index')->with('success', 'Đã cập nhật điểm chấm công!');
    }

    public function destroy(AttendanceLocation $attendanceLocation)
    {
        $attendanceLocation->update(['is_active' => false]);

        return redirect()->route('attendance-locations.index')->with('success', 'Đã vô hiệu hóa điểm chấm công!');
    }
}
