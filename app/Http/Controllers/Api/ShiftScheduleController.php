<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftSchedule;
use Illuminate\Http\Request;

class ShiftScheduleController extends Controller
{
    /**
     * Lịch xếp ca của chính nhân viên trong khoảng ngày (mặc định tháng hiện tại).
     */
    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $from = $request->filled('from') ? $request->date('from') : now()->startOfMonth();
        $to   = $request->filled('to') ? $request->date('to') : now()->endOfMonth();

        $schedules = ShiftSchedule::with(['shift', 'attendanceLog'])
            ->where('employee_id', $employee->id)
            ->where('status', 'scheduled')
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('work_date')
            ->get()
            ->map(function (ShiftSchedule $s) {
                $effectiveShift = $s->effectiveShift();

                return [
                    'id'        => $s->id,
                    'work_date' => $s->work_date->toDateString(),
                    'is_flexible' => $s->isFlexible(),
                    'shift' => $effectiveShift ? [
                        'name'         => $s->shift?->name ?? 'Ca linh hoạt',
                        'start_time'   => $effectiveShift->start_time,
                        'end_time'     => $effectiveShift->end_time,
                        'is_wfh'       => $effectiveShift->isWfh(),
                        'is_overnight' => (bool) $effectiveShift->is_overnight,
                    ] : null,
                    'checked_in'  => (bool) $s->attendanceLog?->check_in_at,
                    'checked_out' => (bool) $s->attendanceLog?->check_out_at,
                ];
            });

        return response()->json(['data' => $schedules]);
    }

    /**
     * Tra cứu ca đã xếp của ĐỒNG NGHIỆP (không phải bản thân) vào một ngày cụ thể — dùng cho
     * form "Đổi ca" trên mobile để chọn ca của người khác làm target_schedule_id. Chỉ trả về
     * dữ liệu tối thiểu (không lộ thông tin nhạy cảm), chỉ những ca hợp lệ để đổi (giống điều
     * kiện trong ShiftSwapRequestsController::assertSwappable — scheduled, không linh hoạt,
     * không phải quá khứ).
     */
    public function lookup(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $validated = $request->validate(['date' => 'required|date']);
        $date = $validated['date'];

        abort_if(\Illuminate\Support\Carbon::parse($date)->lt(today()), 422, 'Không thể đổi ca đã diễn ra trong quá khứ.');

        $schedules = ShiftSchedule::with(['shift', 'employee'])
            ->whereHas('employee', fn($q) => $q->where('is_active', true))
            ->where('employee_id', '!=', $employee->id)
            ->where('status', 'scheduled')
            ->whereNotNull('shift_id')
            ->where('work_date', $date)
            ->orderBy('employee_id')
            ->get()
            ->map(fn(ShiftSchedule $s) => [
                'id'            => $s->id,
                'employee_id'   => $s->employee_id,
                'employee_name' => $s->employee?->name,
                'employee_code' => $s->employee?->code,
                'shift_name'    => $s->shift?->name,
                'start_time'    => $s->shift?->start_time,
                'end_time'      => $s->shift?->end_time,
            ]);

        return response()->json(['data' => $schedules]);
    }
}
