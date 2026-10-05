<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceLogsExport;
use App\Exports\AttendanceTimesheetExport;
use App\Http\Requests\StoreAttendanceLogRequest;
use App\Http\Requests\UpdateAttendanceLogRequest;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\Team;
use App\Support\Concerns\ResolvesApprovedCorrectionIndex;
use App\Support\Concerns\ResolvesExportDateRange;
use App\Support\Concerns\ResolvesOvernightCheckOutDate;
use App\Support\Concerns\ResolvesPartialLeaveIndex;
use App\Services\AttendanceAlertService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceLogsController extends Controller
{
    use ResolvesApprovedCorrectionIndex;
    use ResolvesExportDateRange;
    use ResolvesOvernightCheckOutDate;
    use ResolvesPartialLeaveIndex;

    public function index(Request $request)
    {
        $query = AttendanceLog::with(['employee.branch', 'employee.team', 'employee.user', 'shiftSchedule.shift'])
            ->orderByDesc('work_date');

        if ($request->filled('branch_id')) {
            $query->whereHas('employee', fn($q) => $q->where('branch_id', $request->branch_id));
        }

        if ($request->filled('team_id')) {
            $query->whereHas('employee', fn($q) => $q->where('team_id', $request->team_id));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Mặc định ưu tiên hiển thị chấm công HÔM NAY ngay khi vào trang (chưa chọn bộ lọc nào) —
        // để admin thấy ngay tình hình chấm công trong ngày thay vì lẫn vào toàn bộ lịch sử. Chỉ
        // áp dụng khi KHÔNG có bất kỳ bộ lọc nào khác (kể cả date_from/date_to) và chưa bấm
        // "Xem tất cả" (?all=1) — ngay khi chọn bất kỳ bộ lọc nào, tôn trọng đúng lựa chọn của admin.
        $isDefaultTodayView = $this->isDefaultTodayView($request);

        if ($isDefaultTodayView) {
            $query->whereDate('work_date', now()->toDateString());
        } else {
            if ($request->filled('date_from')) {
                $query->whereDate('work_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('work_date', '<=', $request->date_to);
            }
        }

        $logs             = $query->paginate(20)->withQueryString();
        $partialLeaveIndex = $this->partialLeaveFractionIndex($logs->getCollection());
        $approvedCorrectionIndex = $this->approvedCorrectionIndex($logs->getCollection());
        $branches         = Branch::where('is_active', true)->orderBy('name')->get();
        $teams            = Team::where('is_active', true)->orderBy('name')->get();
        $employees        = Employee::where('is_active', true)->orderBy('name')->get();

        return view('attendance-logs.index', compact('logs', 'branches', 'teams', 'employees', 'isDefaultTodayView', 'partialLeaveIndex', 'approvedCorrectionIndex'));
    }

    /**
     * Trang báo cáo chưa chọn bộ lọc nào và chưa bấm "Xem tất cả" (?all=1) → chỉ hiển thị hôm nay.
     * Dùng chung cho index() và export() để file xuất khớp đúng dữ liệu đang xem.
     */
    private function isDefaultTodayView(Request $request): bool
    {
        return !$request->filled('branch_id')
            && !$request->filled('team_id')
            && !$request->filled('employee_id')
            && !$request->filled('date_from')
            && !$request->filled('date_to')
            && !$request->boolean('all');
    }

    /**
     * Xuất Excel báo cáo chấm công — áp dụng đúng bộ lọc hiện tại của trang
     * (chi nhánh/đội nhóm/nhân viên/khoảng ngày), không phân trang. Không có bộ lọc nào
     * (chế độ mặc định của trang) → chỉ xuất hôm nay, giống những gì đang hiển thị.
     */
    public function export(Request $request)
    {
        if ($this->isDefaultTodayView($request)) {
            $today = now()->toDateString();
            $request->merge(['date_from' => $today, 'date_to' => $today]);
            $label = 'Hôm nay ' . now()->format('d/m/Y');
        } else {
            $label = $request->filled('date_from') || $request->filled('date_to')
                ? ($request->date_from ?: '...') . ' – ' . ($request->date_to ?: '...')
                : 'Toàn bộ thời gian';
        }

        $filename = 'bao-cao-cham-cong_' . now()->format('Ymd_His') . '.xlsx';

        activity()->causedBy(auth()->user())
            ->inLog('attendance')
            ->withProperties(['range' => $label])
            ->log('Xuất Excel báo cáo chấm công');

        return Excel::download(new AttendanceLogsExport($request, $label), $filename);
    }

    /**
     * Xuất "Bảng chấm công" dạng lưới NV x ngày (tương tự bảng công truyền thống) —
     * theo tuần / theo tháng / theo khoảng ngày tùy chọn (xem ResolvesExportDateRange).
     */
    public function exportTimesheet(Request $request)
    {
        [$from, $to, $label] = $this->resolveExportDateRange($request);

        $export = new AttendanceTimesheetExport(
            $from,
            $to,
            $label,
            $request->integer('branch_id') ?: null,
            $request->integer('team_id') ?: null,
            $request->integer('employee_id') ?: null,
        );

        $filename = 'bang-cham-cong_' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.xlsx';

        activity()->causedBy(auth()->user())
            ->inLog('attendance')
            ->withProperties(['range' => $label])
            ->log('Xuất Excel bảng chấm công');

        return Excel::download($export, $filename);
    }

    /**
     * Danh sách ca đã xếp của 1 nhân viên vào 1 ngày cụ thể — dùng để đổ vào dropdown "Ca" trong
     * modal "Chấm công hộ" (fetch() từ JS khi admin chọn nhân viên/ngày). Cùng quyền
     * `create-attendance-logs` vì chỉ phục vụ tính năng này.
     */
    public function employeeShifts(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'work_date'   => 'required|date',
        ]);

        $schedules = ShiftSchedule::with(['shift', 'attendanceLog'])
            ->where('employee_id', $request->employee_id)
            ->where('work_date', $request->work_date)
            ->where('status', 'scheduled')
            ->orderBy('id')
            ->get()
            ->map(function (ShiftSchedule $s) {
                $shift = $s->effectiveShift();
                $range = $shift?->start_time ? substr($shift->start_time, 0, 5) . '–' . substr($shift->end_time, 0, 5) : '';

                return [
                    'id'                => $s->id,
                    'label'             => trim(($s->shift?->name ?? 'Ca linh hoạt') . ($range ? " ({$range})" : '')),
                    'has_attendance_log' => (bool) $s->attendanceLog,
                ];
            });

        return response()->json(['data' => $schedules]);
    }

    /**
     * Chấm công hộ — admin tạo mới 1 bản ghi chấm công cho nhân viên quên chấm công (`create-
     * attendance-logs`, xem AttendanceLogEditPermissionSeeder). Không qua xác thực GPS/IP (admin
     * nhập tay giờ vào/ra), dùng đúng ca admin đã chọn (shift_schedule_id, xem employeeShifts() +
     * dropdown "Ca" trong modal) để tính lại late_minutes/early_minutes theo cùng công thức với
     * StaffRequestsController::applyAttendanceCorrection(). Chỉ tạo bản ghi MỚI — nếu đã có bản
     * ghi cho đúng nhân viên/ngày/ca đó thì báo lỗi, yêu cầu dùng chức năng "Sửa" (update()) thay
     * vì tạo trùng.
     */
    public function store(StoreAttendanceLogRequest $request)
    {
        $validated = $request->validated();
        $employee  = Employee::findOrFail($validated['employee_id']);
        $workDate  = $validated['work_date'];

        $schedule = $this->resolveShiftScheduleForManualLog($employee->id, $workDate, $validated['shift_schedule_id'] ?? null);

        $log = DB::transaction(function () use ($employee, $workDate, $schedule, $validated) {
            $exists = AttendanceLog::where('employee_id', $employee->id)
                ->where('work_date', $workDate)
                ->when($schedule, fn($q) => $q->where('shift_schedule_id', $schedule->id), fn($q) => $q->whereNull('shift_schedule_id'))
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Nhân viên này đã có bản ghi chấm công cho ngày đã chọn — dùng chức năng "Sửa" thay vì tạo mới.',
                ]);
            }

            $data = [
                'employee_id'       => $employee->id,
                'shift_schedule_id' => $schedule?->id,
                'work_date'         => $workDate,
                ...($schedule?->shiftSnapshotAttributes() ?? []),
            ];

            if (!empty($validated['check_in_at'])) {
                $checkIn                 = Carbon::parse($workDate . ' ' . $validated['check_in_at']);
                $data['check_in_at']     = $checkIn;
                $data['check_in_method'] = 'manual';
                $data['late_minutes']    = $schedule?->shift ? $this->computeLateMinutesAt($checkIn, $schedule->shift) : 0;
            }

            if (!empty($validated['check_out_at'])) {
                $checkOutDate              = $this->resolveCheckOutDate($workDate, $validated['check_out_at'], $schedule?->shift, $validated['check_in_at'] ?? null);
                $checkOut                  = Carbon::parse($checkOutDate . ' ' . $validated['check_out_at']);
                $data['check_out_at']      = $checkOut;
                $data['check_out_method']  = 'manual';
                $data['early_minutes']     = $schedule?->shift ? $this->computeEarlyMinutesAt($checkOut, $schedule->shift) : 0;
            }

            return AttendanceLog::create($data);
        });

        activity()->causedBy(auth()->user())
            ->performedOn($log)
            ->inLog('attendance')
            ->withProperties([
                'employee_code' => $employee->code,
                'work_date'     => $workDate,
                'check_in_at'   => $validated['check_in_at'] ?? null,
                'check_out_at'  => $validated['check_out_at'] ?? null,
                'ip'            => $request->ip(),
                'device'        => $request->userAgent(),
            ])
            ->log("Admin chấm công hộ — {$employee->name}");

        app(AttendanceAlertService::class)->resolveAlertsOnLog($log);

        return back()->with('success', 'Đã tạo chấm công hộ!');
    }

    /**
     * Sửa trực tiếp giờ vào/ra của 1 bản ghi chấm công — chỉ admin (`edit-attendance-logs`, xem
     * AttendanceLogEditPermissionSeeder). Khác với luồng "Lượt chấm công" trong Yêu cầu & Phê duyệt
     * (nhân viên gửi yêu cầu → được duyệt mới áp dụng), đây là sửa trực tiếp không qua phê duyệt —
     * dùng cùng cách tính lại late_minutes/early_minutes theo giờ ca (nếu có) như
     * StaffRequestsController::applyAttendanceCorrection() để nhất quán.
     */
    public function update(UpdateAttendanceLogRequest $request, AttendanceLog $attendanceLog)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($attendanceLog, $validated) {
            $log = AttendanceLog::lockForUpdate()->findOrFail($attendanceLog->id);
            $shift = $log->shiftSchedule?->shift;
            $workDate = $log->work_date->toDateString();

            $data = [];

            if (array_key_exists('check_in_at', $validated)) {
                if ($validated['check_in_at']) {
                    $checkIn                 = Carbon::parse($workDate . ' ' . $validated['check_in_at']);
                    $data['check_in_at']     = $checkIn;
                    $data['check_in_method'] = 'manual';
                    $data['late_minutes']    = $shift ? $this->computeLateMinutesAt($checkIn, $shift) : 0;
                } else {
                    $data['check_in_at']     = null;
                    $data['check_in_method'] = null;
                    $data['late_minutes']    = 0;
                }
            }

            if (array_key_exists('check_out_at', $validated)) {
                if ($validated['check_out_at']) {
                    $referenceCheckIn          = array_key_exists('check_in_at', $data) ? $data['check_in_at'] : $log->check_in_at;
                    $checkOutDate              = $this->resolveCheckOutDate($workDate, $validated['check_out_at'], $shift, $referenceCheckIn?->format('H:i'));
                    $checkOut                  = Carbon::parse($checkOutDate . ' ' . $validated['check_out_at']);
                    $data['check_out_at']      = $checkOut;
                    $data['check_out_method']  = 'manual';
                    $data['early_minutes']     = $shift ? $this->computeEarlyMinutesAt($checkOut, $shift) : 0;
                } else {
                    $data['check_out_at']     = null;
                    $data['check_out_method'] = null;
                    $data['early_minutes']    = 0;
                }
            }

            $log->update($data);
        });

        activity()->causedBy(auth()->user())
            ->performedOn($attendanceLog)
            ->inLog('attendance')
            ->withProperties([
                'employee_code' => $attendanceLog->employee?->code,
                'work_date'     => $attendanceLog->work_date->toDateString(),
                'check_in_at'   => $validated['check_in_at'] ?? null,
                'check_out_at'  => $validated['check_out_at'] ?? null,
            ])
            ->log("Admin sửa bản ghi chấm công — {$attendanceLog->employee?->name}");

        app(AttendanceAlertService::class)->resolveAlertsOnLog($attendanceLog->fresh());

        return back()->with('success', 'Đã cập nhật bản ghi chấm công!');
    }

    /**
     * Xoá hẳn 1 bản ghi chấm công — chỉ admin (`delete-attendance-logs`, xem
     * AttendanceLogEditPermissionSeeder). attendance_logs không dùng soft delete nên đây là
     * xoá vĩnh viễn. Bản ghi này cũng chính là dữ liệu hiển thị ở "Lịch sử chấm công" của
     * nhân viên (my-attendance-logs) — cùng 1 bảng, không cần đồng bộ riêng.
     */
    public function destroy(AttendanceLog $attendanceLog)
    {
        $attendanceLog->loadMissing('employee');
        $employeeName = $attendanceLog->employee?->name;
        $employeeCode = $attendanceLog->employee?->code;
        $workDate     = $attendanceLog->work_date->toDateString();

        $attendanceLog->delete();

        activity()->causedBy(auth()->user())
            ->inLog('attendance')
            ->withProperties(['employee_code' => $employeeCode, 'work_date' => $workDate])
            ->log("Admin xoá bản ghi chấm công — {$employeeName}");

        return back()->with('success', 'Đã xoá bản ghi chấm công!');
    }

    /**
     * Xác định ca cần chấm công hộ — cùng nguyên tắc với
     * AttendanceController::resolveShiftScheduleForCheck() (nhân viên tự chấm công):
     * - Nếu admin có chọn shift_schedule_id: dùng đúng ca đó (phải thuộc về nhân viên & đúng ngày).
     * - Nếu không chọn: chỉ tự suy ra khi nhân viên có 0 hoặc đúng 1 ca hôm đó. Có ≥2 ca mà không
     *   chỉ định rõ thì bắt buộc admin phải chọn qua dropdown "Ca" (đổ dữ liệu từ employeeShifts()).
     */
    private function resolveShiftScheduleForManualLog(int $employeeId, string $workDate, ?int $requestedId): ?ShiftSchedule
    {
        if ($requestedId) {
            $schedule = ShiftSchedule::with('shift')
                ->where('id', $requestedId)
                ->where('employee_id', $employeeId)
                ->where('work_date', $workDate)
                ->first();

            if (!$schedule) {
                throw ValidationException::withMessages(['shift_schedule_id' => 'Ca không hợp lệ.']);
            }

            return $schedule;
        }

        $todaySchedules = ShiftSchedule::with('shift')
            ->where('employee_id', $employeeId)
            ->where('work_date', $workDate)
            ->where('status', 'scheduled')
            ->get();

        if ($todaySchedules->count() > 1) {
            throw ValidationException::withMessages(['shift_schedule_id' => 'Nhân viên có nhiều ca vào ngày này, vui lòng chọn ca cần chấm công hộ.']);
        }

        return $todaySchedules->first();
    }

    private function computeLateMinutesAt(Carbon $at, Shift $shift): int
    {
        $start = $at->copy()->setTimeFromTimeString((string) $shift->start_time);
        if ($at->lessThanOrEqualTo($start)) {
            return 0;
        }

        return max(0, $start->diffInMinutes($at) - $shift->grace_late_minutes);
    }

    private function computeEarlyMinutesAt(Carbon $at, Shift $shift): int
    {
        $end = $at->copy()->setTimeFromTimeString((string) $shift->end_time);
        if ($at->greaterThanOrEqualTo($end)) {
            return 0;
        }

        return max(0, $at->diffInMinutes($end) - $shift->grace_early_minutes);
    }
}
