<?php

namespace App\Http\Controllers;

use App\Exports\MyScheduleExport;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\ShiftSchedule;
use App\Support\Concerns\ResolvesExportDateRange;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MyScheduleController extends Controller
{
    use ResolvesExportDateRange;

    /**
     * Trang khung — bản thân lịch được FullCalendar render phía client,
     * dữ liệu sự kiện lấy qua endpoint events() bên dưới (JSON feed).
     */
    public function index()
    {
        $employee = auth()->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        return view('my-schedule.index', compact('employee'));
    }

    /**
     * JSON feed cho FullCalendar — FullCalendar tự động gọi GET với query
     * ?start=...&end=... (ISO8601) mỗi khi người dùng đổi tháng/tuần trên UI.
     */
    public function events(Request $request)
    {
        $employee = auth()->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $start = $request->filled('start') ? Carbon::parse($request->start) : now()->startOfMonth();
        $end   = $request->filled('end') ? Carbon::parse($request->end) : now()->endOfMonth();

        $events = [];

        $schedules = ShiftSchedule::with('shift')
            ->where('employee_id', $employee->id)
            ->where('status', 'scheduled')
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $attendanceLogs = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn($log) => $log->work_date->toDateString());

        // Bảng màu nhạt xoay vòng theo shift_id — dùng khi ca chưa cấu hình màu riêng.
        $palette = [
            ['bg' => '#eff6ff', 'border' => '#38bdf8', 'text' => '#0369a1'],
            ['bg' => '#ecfdf5', 'border' => '#34d399', 'text' => '#047857'],
            ['bg' => '#fffbeb', 'border' => '#fbbf24', 'text' => '#92400e'],
            ['bg' => '#f5f3ff', 'border' => '#a78bfa', 'text' => '#5b21b6'],
            ['bg' => '#fff1f2', 'border' => '#fb7185', 'text' => '#be123c'],
            ['bg' => '#ecfeff', 'border' => '#22d3ee', 'text' => '#0e7490'],
        ];

        $today = now()->toDateString();

        foreach ($schedules as $schedule) {
            $shift = $schedule->shift;
            if (!$shift) {
                continue;
            }

            $colors = $palette[$schedule->shift_id % count($palette)];
            $bg     = $shift->color ? $shift->color . '1a' : $colors['bg'];
            $border = $shift->color ?: $colors['border'];
            $text   = $shift->color ?: $colors['text'];

            $timeRange = substr($shift->start_time, 0, 5) . '–' . substr($shift->end_time, 0, 5);
            $dateKey   = $schedule->work_date->toDateString();
            $log       = $attendanceLogs->get($dateKey);

            // Xác định trạng thái chấm công của ngày này để hiển thị ngay trên lịch:
            // completed (đã check-in + check-out), in_progress (mới check-in), missed (đã qua ngày mà
            // chưa chấm công), upcoming (hôm nay/tương lai, chưa tới lúc hoặc chưa cần chấm công).
            if ($log && $log->check_in_at && $log->check_out_at) {
                $attendanceStatus = 'completed';
            } elseif ($log && $log->check_in_at) {
                $attendanceStatus = 'in_progress';
            } elseif ($dateKey < $today) {
                $attendanceStatus = 'missed';
            } else {
                $attendanceStatus = 'upcoming';
            }

            $events[] = [
                'title'           => ($shift->isWfh() ? '🏠 ' : '') . $shift->name . ' (' . $timeRange . ')',
                'start'           => $dateKey,
                'allDay'          => true,
                'backgroundColor' => $bg,
                'borderColor'     => $border,
                'textColor'       => $text,
                'extendedProps'   => [
                    'type'             => 'shift',
                    'shiftCode'        => $shift->code,
                    'timeRange'        => $timeRange,
                    'wfh'              => $shift->isWfh(),
                    'attendanceStatus' => $attendanceStatus,
                    'checkInAt'        => $log?->check_in_at?->format('H:i'),
                    'checkOutAt'       => $log?->check_out_at?->format('H:i'),
                    'lateMinutes'      => $log?->late_minutes ?? 0,
                    'earlyMinutes'     => $log?->early_minutes ?? 0,
                ],
            ];
        }

        $leaveRequests = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('date_from', '<=', $end->toDateString())
            ->where('date_to', '>=', $start->toDateString())
            ->get();

        foreach ($leaveRequests as $leave) {
            $events[] = [
                'title'           => '✈ ' . $leave->typeLabel(),
                'start'           => $leave->date_from->toDateString(),
                // FullCalendar coi 'end' là mốc kết thúc không bao gồm (exclusive) cho sự kiện allDay nhiều ngày.
                'end'             => $leave->date_to->copy()->addDay()->toDateString(),
                'allDay'          => true,
                'backgroundColor' => '#f1f5f9',
                'borderColor'     => '#94a3b8',
                'textColor'       => '#475569',
                'extendedProps'   => [
                    'type'   => 'leave',
                    'reason' => $leave->reason,
                ],
            ];
        }

        return response()->json($events, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Xuất Excel lịch làm việc cá nhân — theo tuần / tháng / khoảng ngày tùy chọn.
     */
    public function export(Request $request)
    {
        $employee = auth()->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        [$from, $to, $label] = $this->resolveExportDateRange($request);

        $filename = 'lich-lam-viec_' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.xlsx';

        activity()->causedBy(auth()->user())
            ->inLog('shift_schedule')
            ->withProperties(['range' => $label])
            ->log("Xuất Excel lịch làm việc — {$employee->name}");

        return Excel::download(new MyScheduleExport($employee, $from, $to, $label), $filename);
    }
}
