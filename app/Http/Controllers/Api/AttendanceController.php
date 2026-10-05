<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\ShiftSchedule;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Ca hôm nay của nhân viên + trạng thái chấm công từng ca — dữ liệu cho màn hình chấm công
     * của app. Check-in/out thực tế dùng chung route với web (App\Http\Controllers\AttendanceController)
     * vì logic xác thực GPS+IP là như nhau, chỉ khác guard (session vs sanctum token).
     */
    public function today(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $today = now()->toDateString();

        $shiftSchedules = ShiftSchedule::with(['shift', 'attendanceLog'])
            ->where('employee_id', $employee->id)
            ->where('work_date', $today)
            ->where('status', 'scheduled')
            ->orderBy('id')
            ->get()
            ->map(function (ShiftSchedule $s) {
                $effectiveShift = $s->effectiveShift();

                return [
                    'id'          => $s->id,
                    'is_flexible' => $s->isFlexible(),
                    'shift'       => $effectiveShift ? [
                        'name'       => $s->shift?->name ?? 'Ca linh hoạt',
                        'start_time' => $effectiveShift->start_time,
                        'end_time'   => $effectiveShift->end_time,
                        'is_wfh'     => $effectiveShift->isWfh(),
                    ] : null,
                    'attendance_log' => $s->attendanceLog ? $this->formatLog($s->attendanceLog) : null,
                ];
            });

        $unscheduledLog = $shiftSchedules->isEmpty()
            ? AttendanceLog::where('employee_id', $employee->id)
                ->where('work_date', $today)
                ->whereNull('shift_schedule_id')
                ->first()
            : null;

        return response()->json([
            'shift_schedules'  => $shiftSchedules,
            'unscheduled_log'  => $unscheduledLog ? $this->formatLog($unscheduledLog) : null,
        ]);
    }

    /**
     * Lịch sử chấm công của chính nhân viên trong khoảng ngày (mặc định tháng hiện tại).
     */
    public function history(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $from = $request->filled('from') ? $request->date('from') : now()->startOfMonth();
        $to   = $request->filled('to') ? $request->date('to') : now()->endOfMonth();

        $logs = AttendanceLog::with('shiftSchedule.shift')
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('work_date')
            ->paginate(30);

        $logs->getCollection()->transform(fn(AttendanceLog $log) => $this->formatLog($log));

        return response()->json($logs);
    }

    private function formatLog(AttendanceLog $log): array
    {
        return [
            'id'               => $log->id,
            'work_date'        => $log->work_date->toDateString(),
            'check_in_at'      => $log->check_in_at?->toIso8601String(),
            'check_out_at'     => $log->check_out_at?->toIso8601String(),
            'check_in_method'  => $log->check_in_method,
            'check_out_method' => $log->check_out_method,
            'late_minutes'     => $log->late_minutes,
            'early_minutes'    => $log->early_minutes,
            'overtime_hours'   => (float) $log->overtime_hours,
            'device_changed'   => $log->deviceChanged(),
            'shift_name'       => $log->shiftSchedule?->shift?->name,
        ];
    }
}
