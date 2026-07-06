<?php

namespace App\Http\Controllers;

use App\Exports\ShiftSchedulesExport;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\ShiftScheduleRecurrence;
use App\Models\Team;
use App\Services\ShiftScheduleGenerator;
use App\Support\Concerns\ResolvesExportDateRange;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ShiftSchedulesController extends Controller
{
    use ResolvesExportDateRange;

    private const WEEKDAYS = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];

    public function index(Request $request)
    {
        $weekStart = $request->filled('week')
            ? Carbon::parse($request->week)->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $days = collect(range(0, 6))->map(fn($i) => $weekStart->copy()->addDays($i));

        $employeeQuery = Employee::with('team')->where('is_active', true)->orderBy('name');

        if ($request->filled('branch_id')) {
            $employeeQuery->where('branch_id', $request->branch_id);
        }
        if ($request->filled('team_id')) {
            $employeeQuery->where('team_id', $request->team_id);
        }
        if ($request->filled('employee_id')) {
            $employeeQuery->where('id', $request->employee_id);
        }

        $employees = $employeeQuery->get();

        $schedules = ShiftSchedule::with(['shift', 'attendanceLog', 'assignedBy:id,name'])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('status', 'scheduled') // bỏ qua ca đã huỷ (VD do nghỉ phép) — không hiển thị như đang có ca
            ->get()
            ->groupBy(fn($s) => $s->employee_id . '_' . $s->work_date->toDateString());

        // Tăng ca đã duyệt cho những ngày KHÔNG có ca đang "scheduled" — không chỉ ngày chưa từng
        // xếp ca (shift_schedule_id null), mà cả ngày đã xếp ca nhưng sau đó bị huỷ (status !=
        // scheduled) trong khi log tăng ca vẫn còn shift_schedule_id trỏ tới ca đã huỷ đó. Cố ý
        // KHÔNG lọc theo shift_schedule_id ở đây — Blade view chỉ tra map này khi ô đó rỗng
        // ($cellSchedules->isEmpty()), nên dù log có shift_schedule_id hay không cũng không bao
        // giờ hiển thị trùng với ô đã có ca hợp lệ. Xem StaffRequestsController::applyOvertime().
        $overtimeOnlyLogs = AttendanceLog::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('overtime_hours', '>', 0)
            ->get()
            ->groupBy(fn($l) => $l->employee_id . '_' . $l->work_date->toDateString());

        $shifts        = Shift::where('is_active', true)->orderBy('name')->get();
        $branches      = Branch::where('is_active', true)->orderBy('name')->get();
        $teams         = Team::where('is_active', true)->orderBy('name')->get();
        $allEmployees  = Employee::where('is_active', true)->orderBy('name')->get();

        // Lịch sắp tới của chính người đang đăng nhập — dùng cho modal "Đổi ca"
        // (chọn ca nào của mình để đề xuất đổi với ca của người khác).
        $myEmployee = auth()->user()->employee;
        $myUpcomingSchedules = $myEmployee
            ? ShiftSchedule::with('shift')
            ->where('employee_id', $myEmployee->id)
            ->where('status', 'scheduled')
            ->where('work_date', '>=', now()->toDateString())
            ->whereNotNull('shift_id') // ca linh hoạt không được đề xuất đổi ca
            ->orderBy('work_date')
            ->get()
            : collect();

        return view('shift-schedules.index', compact(
            'employees',
            'schedules',
            'overtimeOnlyLogs',
            'days',
            'weekStart',
            'weekEnd',
            'shifts',
            'branches',
            'teams',
            'allEmployees',
            'myEmployee',
            'myUpcomingSchedules'
        ));
    }

    /**
     * Danh sách nhân viên đang trong ca làm (đã check-in, chưa check-out) hôm nay —
     * tôn trọng bộ lọc chi nhánh/đội nhóm/nhân viên đang áp dụng trên trang xếp ca.
     * Với mỗi người, trả kèm khung giờ ca và trạng thái "trong ca" (so với start/end
     * của ca, có xử lý ca qua đêm) để phân biệt đang làm đúng giờ hay đã quá giờ ca.
     */
    public function onShiftJson(Request $request)
    {
        $now   = now();
        $today = $now->toDateString();

        $employeeIds = Employee::query()
            ->where('is_active', true)
            ->when($request->filled('branch_id'), fn($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->filled('team_id'), fn($q) => $q->where('team_id', $request->team_id))
            ->when($request->filled('employee_id'), fn($q) => $q->where('id', $request->employee_id))
            ->pluck('id');

        $logs = AttendanceLog::query()
            ->where('work_date', $today)
            ->whereIn('employee_id', $employeeIds)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->with(['employee.branch', 'employee.team', 'shiftSchedule.shift'])
            ->get()
            ->sortBy('check_in_at')
            ->values();

        $employees = $logs->map(function (AttendanceLog $log) use ($now) {
            $schedule      = $log->shiftSchedule;
            $shift         = $schedule?->shift;
            $shiftName     = $shift?->name ?? ($schedule?->isFlexible() ? 'Ca linh hoạt' : null);
            $startTime     = $shift?->start_time ?? $schedule?->custom_start_time;
            $endTime       = $shift?->end_time ?? $schedule?->custom_end_time;
            $isOvernight   = $shift ? $shift->is_overnight : (bool) $schedule?->custom_is_overnight;
            $inShiftWindow = null;

            if ($startTime && $endTime) {
                $start = Carbon::parse($log->work_date->toDateString() . ' ' . $startTime);
                $end   = Carbon::parse($log->work_date->toDateString() . ' ' . $endTime);
                if ($isOvernight && $end->lessThanOrEqualTo($start)) {
                    $end->addDay();
                }
                $inShiftWindow = $now->between($start, $end);
            }

            return [
                'employee_id'     => $log->employee_id,
                'employee_name'   => $log->employee->name,
                'employee_code'   => $log->employee->code,
                'branch'          => $log->employee->branch?->name,
                'team'            => $log->employee->team?->name,
                'shift_name'      => $shiftName,
                'shift_time'      => $startTime && $endTime ? substr($startTime, 0, 5) . '–' . substr($endTime, 0, 5) : null,
                'check_in_at'     => $log->check_in_at->format('H:i'),
                'worked_minutes'  => $log->check_in_at->diffInMinutes($now),
                'in_shift_window' => $inShiftWindow,
            ];
        });

        return response()->json([
            'count'        => $employees->count(),
            'generated_at' => $now->format('H:i:s d/m/Y'),
            'employees'    => $employees,
        ]);
    }

    /**
     * Thêm 1 ca mới cho 1 nhân viên trong 1 ngày (đa ca — không ghi đè ca đã có).
     * Hỗ trợ "ca linh hoạt": không gửi shift_id, thay vào đó gửi custom_start_time/
     * custom_end_time (giờ tuỳ chỉnh, chỉ áp dụng đúng ngày này, không dùng mẫu Shift có sẵn).
     */
    public function store(Request $request)
    {
        $validated = $this->validateSchedulePayload($request, [
            'employee_id' => 'required|exists:employees,id',
            'work_date'   => 'required|date',
        ]);

        $employee   = Employee::findOrFail($validated['employee_id']);
        $isFlexible = empty($validated['shift_id']);

        $schedule = ShiftSchedule::create([
            'employee_id'     => $validated['employee_id'],
            'branch_id'       => $employee->branch_id,
            'work_date'       => $validated['work_date'],
            'assignment_type' => 'rotation',
            'status'          => 'scheduled',
            'note'            => $validated['note'] ?? null,
            'assigned_by'     => auth()->id(),
        ] + $this->flexibleFields($validated, $isFlexible));

        activity()->causedBy(auth()->user())
            ->performedOn($schedule)
            ->inLog('shift_schedule')
            ->withProperties(['employee_code' => $employee->code, 'work_date' => $validated['work_date'], 'shift_id' => $validated['shift_id'] ?? null, 'flexible' => $isFlexible])
            ->log("Xếp ca — {$employee->name}");

        return back()->with('success', 'Đã xếp ca cho nhân viên!');
    }

    /**
     * Sửa 1 ca cụ thể đã xếp (đổi ca/ghi chú, hoặc chuyển đổi giữa ca mẫu và ca linh hoạt),
     * dùng trong modal chi tiết ngày.
     */
    public function update(Request $request, ShiftSchedule $shiftSchedule)
    {
        $validated  = $this->validateSchedulePayload($request);
        $isFlexible = empty($validated['shift_id']);

        $shiftSchedule->update([
            'note' => $validated['note'] ?? null,
        ] + $this->flexibleFields($validated, $isFlexible));

        activity()->causedBy(auth()->user())
            ->performedOn($shiftSchedule)
            ->inLog('shift_schedule')
            ->withProperties(['employee_code' => $shiftSchedule->employee?->code, 'work_date' => $shiftSchedule->work_date, 'shift_id' => $validated['shift_id'] ?? null, 'flexible' => $isFlexible])
            ->log("Sửa ca — {$shiftSchedule->employee?->name}");

        return back()->with('success', 'Đã cập nhật ca làm việc!');
    }

    /**
     * Validate payload xếp/sửa ca đơn lẻ: hoặc chọn shift_id (ca mẫu có sẵn), hoặc gửi
     * custom_start_time/custom_end_time (ca linh hoạt) — bắt buộc đúng 1 trong 2.
     */
    private function validateSchedulePayload(Request $request, array $extraRules = []): array
    {
        return $request->validate($extraRules + [
            'note'                  => 'nullable|string|max:500',
            'shift_id'              => 'nullable|required_without:custom_start_time|exists:shifts,id',
            'custom_start_time'     => 'nullable|required_without:shift_id|date_format:H:i',
            'custom_end_time'       => 'nullable|required_with:custom_start_time|date_format:H:i',
            'custom_break_minutes'  => 'nullable|integer|min:0|max:600',
            'custom_is_overnight'   => 'nullable|boolean',
            'custom_is_wfh'         => 'nullable|boolean',
        ]);
    }

    /**
     * Trả về mảng field shift_id/custom_* phù hợp cho create()/update(), tuỳ theo ca mẫu hay linh hoạt.
     */
    private function flexibleFields(array $validated, bool $isFlexible): array
    {
        if ($isFlexible) {
            return [
                'shift_id'             => null,
                'custom_start_time'    => $validated['custom_start_time'],
                'custom_end_time'      => $validated['custom_end_time'],
                'custom_break_minutes' => $validated['custom_break_minutes'] ?? 0,
                'custom_is_overnight'  => (bool) ($validated['custom_is_overnight'] ?? false),
                'custom_is_wfh'        => (bool) ($validated['custom_is_wfh'] ?? false),
            ];
        }

        return [
            'shift_id'             => $validated['shift_id'],
            'custom_start_time'    => null,
            'custom_end_time'      => null,
            'custom_break_minutes' => null,
            'custom_is_overnight'  => null,
            'custom_is_wfh'        => null,
        ];
    }

    /**
     * Xếp ca cố định hàng loạt: nhiều NV + 1 ca + khoảng ngày + các thứ trong tuần.
     * Nếu không nhập "Đến ngày", đợt trở thành ca lặp lại hàng tuần không giới hạn
     * (xem ShiftScheduleGenerator::HORIZON_WEEKS và GenerateRecurringShiftSchedules).
     * Mọi bản ghi được sinh ra trong đợt (kể cả các tuần sau này) đều mang chung
     * batch_id để có thể xoá cả đợt cùng lúc từ ShiftSchedulesController::destroy().
     */
    public function bulkStore(Request $request, ShiftScheduleGenerator $generator)
    {
        $validated = $request->validate([
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'shift_ids'      => 'required|array|min:1',
            'shift_ids.*'    => 'exists:shifts,id',
            'date_from'      => 'required|date',
            'date_to'        => 'nullable|date|after_or_equal:date_from',
            'weekdays'       => 'required|array|min:1',
            'weekdays.*'     => 'integer|between:1,7',
        ]);

        $employees   = Employee::whereIn('id', $validated['employee_ids'])->get()->keyBy('id');
        $weekdays    = array_map('intval', $validated['weekdays']);
        $shiftIds    = array_map('intval', $validated['shift_ids']);
        $from        = Carbon::parse($validated['date_from']);
        $batchId     = (string) Str::uuid();
        $isRecurring = empty($validated['date_to']);
        $recurrence  = null;

        if ($isRecurring) {
            $to = now()->addWeeks(ShiftScheduleGenerator::HORIZON_WEEKS)->startOfDay();
            if ($from->greaterThan($to)) {
                $to = $from->copy();
            }

            $recurrence = ShiftScheduleRecurrence::create([
                'batch_id'     => $batchId,
                'shift_ids'    => $shiftIds,
                'employee_ids' => array_values($validated['employee_ids']),
                'weekdays'     => $weekdays,
                'starts_on'    => $from->toDateString(),
                'created_by'   => auth()->id(),
                'is_active'    => true,
            ]);
        } else {
            $to = Carbon::parse($validated['date_to']);
        }

        $result = $generator->generateRange($employees, $weekdays, $from, $to, $shiftIds, $batchId, auth()->id());

        if ($recurrence) {
            $recurrence->update(['last_generated_through' => $to->toDateString()]);
        }

        $message = "Đã xếp ca cố định cho {$result['created']} lượt.";
        if ($result['skipped'] > 0) {
            $message .= " Bỏ qua {$result['skipped']} lượt đã có ca sẵn.";
        }
        if ($isRecurring) {
            $message .= ' Ca sẽ tự động lặp lại hàng tuần cho đến khi bạn huỷ.';
        }

        activity()->causedBy(auth()->user())
            ->inLog('shift_schedule')
            ->withProperties([
                'batch_id'       => $batchId,
                'shift_ids'      => $shiftIds,
                'employee_count' => $employees->count(),
                'created'        => $result['created'],
                'skipped'        => $result['skipped'],
                'recurring'      => $isRecurring,
            ])
            ->log($isRecurring ? 'Xếp ca cố định lặp lại hàng tuần (hàng loạt)' : 'Xếp ca cố định (hàng loạt)');

        return back()->with('success', $message);
    }

    /**
     * Xoá 1 ca. Nếu ca đó thuộc một đợt "xếp ca cố định hàng loạt" (có batch_id),
     * xoá toàn bộ đợt — mọi nhân viên, mọi ngày (kể cả các ngày lặp lại trong
     * tương lai) — và huỷ quy tắc lặp lại tương ứng (nếu có) để không sinh thêm ca mới.
     */
    public function destroy(ShiftSchedule $shiftSchedule)
    {
        $employee = $shiftSchedule->employee;
        $batchId  = $shiftSchedule->batch_id;

        $result = $this->deleteSchedules(collect([$shiftSchedule]));

        if ($batchId) {
            activity()->causedBy(auth()->user())
                ->inLog('shift_schedule')
                ->withProperties(['batch_id' => $batchId, 'deleted_count' => $result['batch_deleted'], 'shift_id' => $shiftSchedule->shift_id])
                ->log('Huỷ đợt xếp ca cố định');

            return back()->with('success', "Đã huỷ đợt xếp ca cố định ({$result['batch_deleted']} ca của tất cả nhân viên liên quan)!");
        }

        activity()->causedBy(auth()->user())
            ->inLog('shift_schedule')
            ->withProperties(['employee_code' => $employee?->code, 'work_date' => $shiftSchedule->work_date, 'shift_id' => $shiftSchedule->shift_id])
            ->log("Huỷ ca — {$employee?->name}");

        return back()->with('success', 'Đã huỷ ca làm việc!');
    }

    /**
     * Xoá nhiều ca cùng lúc (chọn nhiều ô trên lưới xếp ca). Ca nào thuộc một đợt
     * xếp ca cố định (batch_id) thì cả đợt đó bị huỷ luôn (giống hành vi xoá lẻ),
     * kể cả khi chỉ có 1 ca trong đợt nằm trong danh sách được chọn.
     */
    public function destroyBulk(Request $request)
    {
        $validated = $request->validate([
            'schedule_ids'   => 'required|array|min:1',
            'schedule_ids.*' => 'exists:shift_schedules,id',
        ]);

        $schedules = ShiftSchedule::whereIn('id', $validated['schedule_ids'])->get();

        $result = $this->deleteSchedules($schedules);

        activity()->causedBy(auth()->user())
            ->inLog('shift_schedule')
            ->withProperties($result)
            ->log('Xoá nhiều ca (chọn từ lưới xếp ca)');

        return back()->with('success', $this->buildBulkDeleteMessage($result));
    }

    /**
     * Xoá toàn bộ ca đang hiển thị trên lưới, theo đúng bộ lọc (chi nhánh/đội/nhân
     * viên) và tuần đang xem — cùng phạm vi dữ liệu với index(). Ca thuộc đợt cố
     * định vẫn cascade xoá cả đợt như xoá lẻ/xoá nhiều.
     */
    public function destroyAll(Request $request)
    {
        $weekStart = $request->filled('week')
            ? Carbon::parse($request->week)->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $employeeQuery = Employee::query()->where('is_active', true);
        if ($request->filled('branch_id')) {
            $employeeQuery->where('branch_id', $request->branch_id);
        }
        if ($request->filled('team_id')) {
            $employeeQuery->where('team_id', $request->team_id);
        }
        if ($request->filled('employee_id')) {
            $employeeQuery->where('id', $request->employee_id);
        }
        $employeeIds = $employeeQuery->pluck('id');

        $schedules = ShiftSchedule::whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('status', 'scheduled')
            ->get();

        if ($schedules->isEmpty()) {
            return back()->with('error', 'Không có ca nào để xoá theo bộ lọc hiện tại.');
        }

        $result = $this->deleteSchedules($schedules);

        activity()->causedBy(auth()->user())
            ->inLog('shift_schedule')
            ->withProperties($result + ['week' => $weekStart->toDateString(), 'branch_id' => $request->branch_id, 'team_id' => $request->team_id, 'employee_id' => $request->employee_id])
            ->log('Xoá tất cả ca theo bộ lọc/tuần');

        return back()->with('success', $this->buildBulkDeleteMessage($result));
    }

    /**
     * Xoá 1 tập hợp ShiftSchedule: ca thuộc đợt cố định (có batch_id) được gom lại
     * xoá cả đợt (kèm huỷ recurrence để không sinh thêm ca mới); ca lẻ xoá trực tiếp.
     */
    private function deleteSchedules($schedules): array
    {
        $schedules = $schedules instanceof \Illuminate\Support\Collection ? $schedules : collect($schedules);
        $batchIds  = $schedules->pluck('batch_id')->filter()->unique()->values();
        $singleIds = $schedules->whereNull('batch_id')->pluck('id')->values();

        return DB::transaction(function () use ($batchIds, $singleIds) {
            $batchDeleted = 0;
            if ($batchIds->isNotEmpty()) {
                ShiftScheduleRecurrence::whereIn('batch_id', $batchIds)->delete();
                $batchDeleted = ShiftSchedule::whereIn('batch_id', $batchIds)->delete();
            }

            $singleDeleted = 0;
            if ($singleIds->isNotEmpty()) {
                $singleDeleted = ShiftSchedule::whereIn('id', $singleIds)->delete();
            }

            return [
                'batch_count'    => $batchIds->count(),
                'batch_deleted'  => $batchDeleted,
                'single_deleted' => $singleDeleted,
            ];
        });
    }

    private function buildBulkDeleteMessage(array $result): string
    {
        $parts = [];
        if ($result['single_deleted'] > 0) {
            $parts[] = "{$result['single_deleted']} ca lẻ";
        }
        if ($result['batch_count'] > 0) {
            $parts[] = "{$result['batch_count']} đợt cố định ({$result['batch_deleted']} ca liên quan)";
        }

        return 'Đã xoá: ' . implode(', ', $parts) . '.';
    }

    /**
     * Xuất Excel danh sách xếp ca — theo tuần / tháng / khoảng ngày tùy chọn.
     */
    public function export(Request $request)
    {
        [$from, $to, $label] = $this->resolveExportDateRange($request);

        $export = new ShiftSchedulesExport(
            $from,
            $to,
            $label,
            $request->integer('branch_id') ?: null,
            $request->integer('team_id') ?: null,
            $request->integer('employee_id') ?: null,
        );

        $filename = 'xep-ca_' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.xlsx';

        activity()->causedBy(auth()->user())
            ->inLog('shift_schedule')
            ->withProperties(['range' => $label])
            ->log('Xuất Excel xếp ca');

        return Excel::download($export, $filename);
    }
}
