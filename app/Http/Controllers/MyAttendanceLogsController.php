<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Support\Concerns\ResolvesPartialLeaveIndex;
use Illuminate\Http\Request;

class MyAttendanceLogsController extends Controller
{
    use ResolvesPartialLeaveIndex;

    /**
     * Lịch sử chấm công dạng bảng của CHÍNH nhân viên đang đăng nhập — khác với /attendance-logs
     * (view-attendance, dành cho HR/Manager xem của mọi nhân viên). Quyền view-own-attendance.
     */
    public function index(Request $request)
    {
        $employee = auth()->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $query = AttendanceLog::with('shiftSchedule.shift')
            ->where('employee_id', $employee->id)
            ->orderByDesc('work_date');

        if ($request->filled('date_from')) {
            $query->whereDate('work_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('work_date', '<=', $request->date_to);
        }

        $logs              = $query->paginate(20)->withQueryString();
        $partialLeaveIndex = $this->partialLeaveFractionIndex($logs->getCollection());

        return view('my-attendance-logs.index', compact('employee', 'logs', 'partialLeaveIndex'));
    }
}
