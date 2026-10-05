<?php

namespace App\Http\Controllers;

use App\Exports\MyScheduleExport;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\ShiftSchedule;
use App\Support\Concerns\ResolvesExportDateRange;
use App\Support\Concerns\ResolvesPartialLeaveIndex;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MyScheduleController extends Controller
{
    use ResolvesExportDateRange;
    use ResolvesPartialLeaveIndex;

    /**
     * Trang khung — bản thân lịch được FullCalendar render phía client,
     * dữ liệu sự kiện lấy qua endpoint events() bên dưới (JSON feed).
     */
    public function index(Request $request)
    {
        $employee = auth()->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $month = $request->integer('month', now()->month);
        $year  = $request->integer('year', now()->year);

        if ($month < 1 || $month > 12) {
            $month = now()->month;
        }
        if ($year < 2020 || $year > 2030) {
            $year = now()->year;
        }

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate   = $startDate->copy()->endOfMonth();

        $schedules = ShiftSchedule::with('shift')
            ->where('employee_id', $employee->id)
            ->where('status', 'scheduled')
            ->whereBetween('work_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('work_date', 'asc')
            ->get();

        // Lấy TẤT CẢ lượt chấm công (không keyBy theo ngày — ngày đa ca có nhiều lượt/ngày, keyBy sẽ
        // ghi đè chỉ còn 1, gây hiển thị trùng giờ vào/ra cho mọi ca và tính công thiếu).
        $attendanceLogs = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // Khớp lượt chấm công theo ĐÚNG ca đã xếp (shift_schedule_id); log cũ/chấm công ngoài lịch
        // (không gắn ca) gom theo ngày để fallback khi ngày đó đúng 1 ca.
        $logsByScheduleId = $attendanceLogs->whereNotNull('shift_schedule_id')->keyBy('shift_schedule_id');
        $looseLogsByDate  = $attendanceLogs->whereNull('shift_schedule_id')
            ->groupBy(fn($log) => $log->work_date->toDateString());
        $schedulesByDate  = $schedules->groupBy(fn($s) => $s->work_date->toDateString());

        // scheduleId => AttendanceLog|null — resolve sẵn cho từng ca để view không dùng chung 1 log/ngày.
        $scheduleLogs = [];
        foreach ($schedules as $s) {
            $dateStr = $s->work_date->toDateString();
            $log = $logsByScheduleId->get($s->id);
            if (!$log && ($schedulesByDate->get($dateStr)?->count() === 1)) {
                $log = $looseLogsByDate->get($dateStr)?->first();
            }
            $scheduleLogs[$s->id] = $log;
        }

        $leaveRequests = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('date_from', '<=', $endDate->toDateString())
            ->where('date_to', '>=', $startDate->toDateString())
            ->get();

        $partialLeaveIndex = $this->partialLeaveFractionIndex($attendanceLogs);

        $daysInMonth = [];
        $today = now()->toDateString();

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateStr = $date->toDateString();
            $daySchedules = $schedules->filter(fn($s) => $s->work_date->toDateString() === $dateStr);
            $dayLeaves = $leaveRequests->filter(fn($l) => $date->between($l->date_from, $l->date_to));

            $daysInMonth[$dateStr] = [
                'date' => $date->copy(),
                'schedules' => $daySchedules,
                'leaves' => $dayLeaves,
            ];
        }

        $workedHours = 0.0;
        $cong = 0.0;
        $daysWorked = 0;

        foreach ($attendanceLogs as $log) {
            $hours = $log->netWorkedHours();
            if ($hours !== null) {
                $workedHours += $hours;
                $daysWorked++;
            }
            $leaveFraction = $partialLeaveIndex[$log->employee_id . '_' . $log->work_date->toDateString() . '_' . $log->shift_schedule_id] ?? null;
            $cong += $log->computeCong(null, $leaveFraction) ?? 0;
        }

        $summary = [
            'worked_hours' => round($workedHours, 2),
            'cong' => round($cong, 2),
            'days_worked' => $daysWorked,
        ];

        $prevDate = Carbon::createFromDate($year, $month, 1)->subMonth();
        $nextDate = Carbon::createFromDate($year, $month, 1)->addMonth();

        // Dữ liệu cho giao diện mobile (dải tuần + ca ngày chọn + ca tiếp theo); bản desktop bỏ qua.
        $selectedDate = now();
        if ($request->filled('date')) {
            try {
                $selectedDate = Carbon::parse($request->query('date'));
            } catch (\Throwable) {
                $selectedDate = now();
            }
        }
        $weekStart = $selectedDate->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $weekSchedules = ShiftSchedule::with(['shift', 'attendanceLog', 'branch'])
            ->where('employee_id', $employee->id)
            ->where('status', 'scheduled')
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('work_date')
            ->get()
            ->groupBy(fn($s) => $s->work_date->toDateString());
        $upcomingSchedules = ShiftSchedule::with(['shift', 'branch'])
            ->where('employee_id', $employee->id)
            ->where('status', 'scheduled')
            ->where('work_date', '>', now()->toDateString())
            ->orderBy('work_date')
            ->limit(4)
            ->get();

        return view('my-schedule.index', compact(
            'employee', 'month', 'year', 'daysInMonth', 'scheduleLogs', 'summary', 'partialLeaveIndex', 'prevDate', 'nextDate',
            'selectedDate', 'weekStart', 'weekEnd', 'weekSchedules', 'upcomingSchedules'
        ));
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
            ->get();

        // Khớp lượt chấm công theo ĐÚNG ca (shift_schedule_id); log không gắn ca fallback theo ngày
        // chỉ khi ngày đó đúng 1 ca — tránh 1 log hiện trùng cho mọi ca của ngày đa ca.
        $logsByScheduleId = $attendanceLogs->whereNotNull('shift_schedule_id')->keyBy('shift_schedule_id');
        $looseLogsByDate  = $attendanceLogs->whereNull('shift_schedule_id')
            ->groupBy(fn($log) => $log->work_date->toDateString());
        $schedulesByDate  = $schedules->groupBy(fn($s) => $s->work_date->toDateString());

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
            // effectiveShift(): ca linh hoạt (shift_id null) dựng Shift tạm từ custom_*.
            $shift = $schedule->effectiveShift();
            if (!$shift) {
                continue;
            }
            $shiftName = $schedule->shift?->name ?? 'Ca linh hoạt';

            $colors = $palette[$schedule->shift_id % count($palette)];
            $bg     = $shift->color ? $shift->color . '1a' : $colors['bg'];
            $border = $shift->color ?: $colors['border'];
            $text   = $shift->color ?: $colors['text'];

            $timeRange = substr($shift->start_time, 0, 5) . '–' . substr($shift->end_time, 0, 5);
            $dateKey   = $schedule->work_date->toDateString();
            $log       = $logsByScheduleId->get($schedule->id);
            if (!$log && ($schedulesByDate->get($dateKey)?->count() === 1)) {
                $log = $looseLogsByDate->get($dateKey)?->first();
            }

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
                'title'           => ($shift->isWfh() ? '🏠 ' : '') . $shiftName .' (' . $timeRange . ')',
                'start'           => $dateKey,
                'allDay'          => true,
                'backgroundColor' => $bg,
                'borderColor'     => $border,
                'textColor'       => $text,
                'extendedProps'   => [
                    'type'             => 'shift',
                    'shiftCode'        => $schedule->shift?->code,
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
     * Tổng hợp giờ làm/công của CHÍNH nhân viên đang đăng nhập trong khoảng ngày đang xem trên
     * lịch (FullCalendar gọi lại mỗi khi đổi tháng/tuần) — quyền view-own-attendance, tách biệt
     * với view-attendance (xem của mọi nhân viên, dành cho HR/Manager ở /attendance-logs).
     */
    public function attendanceSummary(Request $request)
    {
        $employee = auth()->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $start = $request->filled('start') ? Carbon::parse($request->start) : now()->startOfMonth();
        $end   = $request->filled('end') ? Carbon::parse($request->end) : now()->endOfMonth();

        $logs = AttendanceLog::with('shiftSchedule.shift')
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $partialLeaveIndex = $this->partialLeaveFractionIndex($logs);

        $workedHours = 0.0;
        $cong        = 0.0;
        $daysWorked  = 0;

        foreach ($logs as $log) {
            $hours = $log->netWorkedHours();
            if ($hours !== null) {
                $workedHours += $hours;
                $daysWorked++;
            }
            $leaveFraction = $partialLeaveIndex[$log->employee_id . '_' . $log->work_date->toDateString() . '_' . $log->shift_schedule_id] ?? null;
            $cong += $log->computeCong(null, $leaveFraction) ?? 0;
        }

        return response()->json([
            'worked_hours' => round($workedHours, 2),
            'cong'         => round($cong, 2),
            'days_worked'  => $daysWorked,
        ]);
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
