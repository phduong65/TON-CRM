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
use Illuminate\Support\Facades\Validator;
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

        $employeeQuery = Employee::with(['team', 'user'])->where('is_active', true)->orderBy('name');

        if ($request->filled('branch_id')) {
            $employeeQuery->where('branch_id', $request->branch_id);
        }
        if ($request->filled('team_id')) {
            $employeeQuery->where('team_id', $request->team_id);
        }
        if ($request->filled('employee_id')) {
            $employeeQuery->where('id', $request->employee_id);
        }
        if ($request->boolean('no_shift_today')) {
            $today = now()->toDateString();
            $employeeQuery->whereDoesntHave('shiftSchedules', function ($q) use ($today) {
                $q->where('work_date', $today)->where('status', 'scheduled');
            });
        }

        $employees = $employeeQuery->get();

        $viewMode = $request->query('view', 'matrix');
        if (!in_array($viewMode, ['matrix', 'table', 'list'], true)) {
            $viewMode = 'matrix';
        }

        $rawSchedules = ShiftSchedule::with(['shift', 'attendanceLog', 'assignedBy:id,name', 'employee.team', 'employee.branch'])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('status', 'scheduled') // bỏ qua ca đã huỷ (VD do nghỉ phép) — không hiển thị như đang có ca
            ->get();

        $schedules = $rawSchedules
            // Sắp xếp theo giờ bắt đầu thực tế (effectiveShift() — đã áp dụng điều chỉnh nghỉ theo
            // giờ nếu có) TRƯỚC khi group, để nhiều ca cùng ngày của 1 nhân viên hiển thị đúng thứ
            // tự thời gian (VD ca 11h-15h phải đứng trước ca 18h-22h) thay vì theo thứ tự tạo/ID —
            // groupBy() giữ nguyên thứ tự tương đối của collection gốc trong từng nhóm.
            ->sortBy(fn($s) => $s->effectiveShift()?->start_time ?? '')
            ->groupBy(fn($s) => $s->employee_id . '_' . $s->work_date->toDateString());

        $allWeekSchedules = $rawSchedules->sortBy([
            ['work_date', 'asc'],
            [fn($s) => $s->effectiveShift()?->start_time ?? '', 'asc'],
            [fn($s) => $s->employee?->name ?? '', 'asc'],
        ]);

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
            'rawSchedules',
            'allWeekSchedules',
            'overtimeOnlyLogs',
            'days',
            'weekStart',
            'weekEnd',
            'shifts',
            'branches',
            'teams',
            'allEmployees',
            'myEmployee',
            'myUpcomingSchedules',
            'viewMode'
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
            ->with(['employee.branch', 'employee.team', 'employee.user', 'shiftSchedule.shift'])
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
                'avatar_url'      => $log->employee->user?->avatar ? asset($log->employee->user->avatar) : null,
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
            'employee_id'          => $validated['employee_id'],
            'branch_id'            => $employee->branch_id,
            'team_id'              => $employee->team_id,
            'work_date'            => $validated['work_date'],
            'assignment_type'      => 'rotation',
            'alternative_group_id' => $validated['alternative_group_id'] ?? null,
            'status'               => 'scheduled',
            'note'                 => $validated['note'] ?? null,
            'assigned_by'          => auth()->id(),
        ] + $this->flexibleFields($validated, $isFlexible));

        activity()->causedBy(auth()->user())
            ->performedOn($schedule)
            ->inLog('shift_schedule')
            ->withProperties(['employee_code' => $employee->code, 'work_date' => $validated['work_date'], 'shift_id' => $validated['shift_id'] ?? null, 'flexible' => $isFlexible])
            ->log("Xếp ca — {$employee->name}");

        // Kiểm tra cảnh báo định biên nhân sự theo khung giờ (Spec 4.6)
        $coverageWarning = null;
        if ($employee->team_id) {
            $reqs = \App\Models\ShiftCoverageRequirement::where('branch_id', $employee->branch_id)
                ->where('team_id', $employee->team_id)
                ->where('is_active', true)
                ->get();

            $date = Carbon::parse($validated['work_date']);
            $dayReqs = $reqs->filter(fn($r) => $r->isActiveOnDate($date));
            if ($dayReqs->isNotEmpty()) {
                $daySchedules = ShiftSchedule::where('branch_id', $employee->branch_id)
                    ->where('work_date', $date->toDateString())
                    ->where('status', 'scheduled')
                    ->where(fn($q) => $q->where('team_id', $employee->team_id)->orWhere(fn($qq) => $qq->whereNull('team_id')->whereHas('employee', fn($eq) => $eq->where('team_id', $employee->team_id))))
                    ->with(['shift', 'attendanceLog'])
                    ->get();

                $coverageService = app(\App\Services\ShiftCoverageService::class);
                foreach ($dayReqs as $r) {
                    $analysis = $coverageService->analyzeFrameCoverage($r, $daySchedules, $date);
                    if ($analysis['status'] === 'shortage') {
                        $shortageCount = $r->minimum_staff - $analysis['scheduled_coverage'];
                        $coverageWarning = "Lưu ý định biên: Khung \"{$r->name}\" ({$r->start_time}–{$r->end_time}) ngày {$date->format('d/m/Y')} đang thiếu {$shortageCount} người (hiện có {$analysis['scheduled_coverage']}/{$r->minimum_staff} người tối thiểu).";
                        break;
                    }
                }
            }
        }

        $redirect = back()->with('success', 'Đã xếp ca cho nhân viên!');
        if ($coverageWarning) {
            $redirect->with('warning', $coverageWarning);
        }

        return $redirect;
    }

    /**
     * Sửa 1 ca cụ thể đã xếp (đổi ca/ghi chú, hoặc chuyển đổi giữa ca mẫu và ca linh hoạt),
     * dùng trong modal chi tiết ngày.
     */
    public function update(Request $request, ShiftSchedule $shiftSchedule)
    {
        $validated  = $this->validateSchedulePayload($request);
        $isFlexible = empty($validated['shift_id']);

        // Nếu ca đang có khung giờ đã điều chỉnh do nghỉ phép theo giờ được duyệt (adjusted_start_time/
        // adjusted_end_time — xem LeaveRequestsController::approve()), điều chỉnh đó được tính dựa trên
        // giờ ca CŨ. Sửa ca sang shift/giờ khác khiến điều chỉnh cũ không còn đúng nghĩa — phải xoá,
        // nếu không effectiveShift() sẽ đè khung giờ mới bằng điều chỉnh lỗi thời, sai cho ca vừa sửa.
        $hadAdjustment = $shiftSchedule->adjusted_start_time || $shiftSchedule->adjusted_end_time;

        $updateData = [
            'note'                => $validated['note'] ?? null,
            'adjusted_start_time' => null,
            'adjusted_end_time'   => null,
        ];

        if (array_key_exists('alternative_group_id', $validated)) {
            $updateData['alternative_group_id'] = $validated['alternative_group_id'] ?: null;
        }

        $shiftSchedule->update($updateData + $this->flexibleFields($validated, $isFlexible));

        activity()->causedBy(auth()->user())
            ->performedOn($shiftSchedule)
            ->inLog('shift_schedule')
            ->withProperties([
                'employee_code'            => $shiftSchedule->employee?->code,
                'work_date'                => $shiftSchedule->work_date,
                'shift_id'                 => $validated['shift_id'] ?? null,
                'flexible'                 => $isFlexible,
                'cleared_leave_adjustment' => $hadAdjustment,
            ])
            ->log("Sửa ca — {$shiftSchedule->employee?->name}"
                . ($hadAdjustment ? ' (đã xoá điều chỉnh giờ nghỉ phép theo giờ cũ)' : ''));

        return back()->with('success', 'Đã cập nhật ca làm việc!'
            . ($hadAdjustment ? ' Lưu ý: điều chỉnh giờ do nghỉ phép theo giờ trước đó trên ca này đã bị xoá do giờ ca thay đổi.' : ''));
    }

    /**
     * Validate payload xếp/sửa ca đơn lẻ: hoặc chọn shift_id (ca mẫu có sẵn), hoặc gửi
     * custom_start_time/custom_end_time (ca linh hoạt) — bắt buộc đúng 1 trong 2.
     */
    private function validateSchedulePayload(Request $request, array $extraRules = []): array
    {
        return $request->validate($extraRules + [
            'note'                  => 'nullable|string|max:500',
            'alternative_group_id'  => 'nullable|string|max:64',
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
     * Xoá 1 ca — chỉ xoá đúng bản ghi của ngày đó, không đụng tới các ngày khác
     * cùng đợt xếp ca cố định (batch_id). Nếu đây là bản ghi cuối cùng còn lại của
     * đợt thì quy tắc lặp lại tương ứng (nếu có) cũng được huỷ theo để không sinh
     * thêm ca mới cho đợt đã hết bản ghi.
     */
    public function destroy(ShiftSchedule $shiftSchedule)
    {
        $employee = $shiftSchedule->employee;

        $this->deleteSchedules(collect([$shiftSchedule]), cascadeBatch: false);

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
     * viên) và tuần đang xem — cùng phạm vi dữ liệu với index(). Vì luôn giới hạn
     * theo 1 tuần cụ thể, ca thuộc đợt cố định KHÔNG cascade xoá cả đợt (khác
     * destroy()/destroyBulk()) — chỉ xoá đúng các ca trong tuần đang xem, các ngày
     * khác của đợt đó (tuần trước/sau) không bị ảnh hưởng. Xem deleteSchedules().
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

        $result = $this->deleteSchedules($schedules, cascadeBatch: false);

        activity()->causedBy(auth()->user())
            ->inLog('shift_schedule')
            ->withProperties($result + ['week' => $weekStart->toDateString(), 'branch_id' => $request->branch_id, 'team_id' => $request->team_id, 'employee_id' => $request->employee_id])
            ->log('Xoá tất cả ca theo bộ lọc/tuần');

        return back()->with('success', $this->buildBulkDeleteMessage($result));
    }

    /**
     * Xoá ca theo nhân viên hoặc theo đội nhóm (áp dụng cho toàn bộ nhân viên trong
     * nhóm), trong một khoảng thời gian tuỳ chọn: 1 ngày, 1 tháng, khoảng ngày, hoặc
     * toàn bộ (không giới hạn ngày).
     *
     * range_type = day/month/range có giới hạn ngày rõ ràng → ca thuộc đợt cố định
     * chỉ bị xoá đúng phần nằm trong phạm vi đã chọn, KHÔNG cascade xoá cả đợt (tránh
     * xoá nhầm các ngày ngoài phạm vi, từng gây mất lịch xếp ca của những ngày trước
     * đó). Riêng range_type = all (người dùng chủ động chọn "không giới hạn ngày")
     * thì cascade xoá cả đợt như destroy()/destroyBulk(). Xem deleteSchedules().
     */
    public function destroyFiltered(Request $request)
    {
        // Validator::after() được dùng thay vì rule required_without_if (không tồn tại
        // trong Laravel) vì cần kiểm tra "ít nhất 1 trong 2 date_from/date_to phải có" —
        // rule thường (kể cả closure gắn trên field) bị Laravel bỏ qua khi field đó rỗng
        // và có "nullable", nên phải validate chéo trong after() để luôn chạy.
        $validator = Validator::make($request->all(), [
            'scope'          => 'required|in:employee,team',
            'employee_ids'   => 'required_if:scope,employee|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'team_ids'       => 'required_if:scope,team|array|min:1',
            'team_ids.*'     => 'exists:teams,id',
            'range_type'     => 'required|in:day,month,range,all',
            'date'           => 'nullable|required_if:range_type,day|date',
            'month'          => 'nullable|required_if:range_type,month|date_format:Y-m',
            'date_from'      => 'nullable|date',
            'date_to'        => 'nullable|date',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->input('range_type') !== 'range') {
                return;
            }

            $from = $request->input('date_from');
            $to   = $request->input('date_to');

            if (!$from && !$to) {
                $validator->errors()->add('date_from', 'Vui lòng nhập ít nhất từ ngày hoặc đến ngày.');
            } elseif ($from && $to && $to < $from) {
                $validator->errors()->add('date_to', 'Đến ngày phải sau hoặc bằng từ ngày.');
            }
        });

        $validated = $validator->validate();

        $employeeIds = $validated['scope'] === 'team'
            ? Employee::whereIn('team_id', $validated['team_ids'])->pluck('id')
            : collect($validated['employee_ids']);

        if ($employeeIds->isEmpty()) {
            return back()->with('error', 'Không tìm thấy nhân viên phù hợp để xoá ca.');
        }

        $query = ShiftSchedule::whereIn('employee_id', $employeeIds);
        $rangeLabel = 'Tất cả';

        if ($validated['range_type'] === 'day') {
            $query->where('work_date', $validated['date']);
            $rangeLabel = $validated['date'];
        } elseif ($validated['range_type'] === 'month') {
            $month      = Carbon::parse($validated['month'] . '-01');
            $query->whereBetween('work_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()]);
            $rangeLabel = $month->format('m/Y');
        } elseif ($validated['range_type'] === 'range') {
            $from = $validated['date_from'] ?? null;
            $to   = $validated['date_to'] ?? null;

            if ($from && $to) {
                $query->whereBetween('work_date', [$from, $to]);
                $rangeLabel = "{$from} → {$to}";
            } elseif ($from) {
                $query->where('work_date', '>=', $from);
                $rangeLabel = "Từ {$from}";
            } else {
                $query->where('work_date', '<=', $to);
                $rangeLabel = "Đến {$to}";
            }
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            return back()->with('error', 'Không có ca nào phù hợp với điều kiện đã chọn.');
        }

        // range_type=all nghĩa là người dùng chủ động chọn xoá KHÔNG giới hạn ngày cho (các)
        // nhân viên/đội đã chọn — lúc đó cascade cả đợt là đúng ý muốn. Các range_type còn lại
        // (day/month/range) có giới hạn ngày rõ ràng nên phải xoá đúng phạm vi, không cascade.
        $cascadeBatch = $validated['range_type'] === 'all';
        $result = $this->deleteSchedules($schedules, $cascadeBatch);

        activity()->causedBy(auth()->user())
            ->inLog('shift_schedule')
            ->withProperties($result + [
                'scope'          => $validated['scope'],
                'employee_count' => $employeeIds->count(),
                'range_type'     => $validated['range_type'],
                'range'          => $rangeLabel,
            ])
            ->log('Xoá ca theo nhân viên/đội nhóm + khoảng thời gian');

        return back()->with('success', $this->buildBulkDeleteMessage($result));
    }

    /**
     * Xoá 1 tập hợp ShiftSchedule.
     *
     * $cascadeBatch = true (mặc định — dùng cho destroy()/destroyBulk(), nơi người dùng bấm
     * xoá trực tiếp 1 ca hoặc chọn hẳn nhiều ô cụ thể trên lưới, không giới hạn theo ngày):
     * ca nào thuộc đợt cố định (có batch_id) sẽ kéo theo xoá TOÀN BỘ đợt đó (mọi nhân viên,
     * mọi ngày) + huỷ recurrence, đúng như hành vi "Huỷ đợt xếp ca cố định".
     *
     * $cascadeBatch = false (dùng cho destroyFiltered()/destroyAll() khi range_type có giới
     * hạn ngày — day/month/range): CHỈ xoá đúng những bản ghi được truyền vào (đã được lọc theo
     * khoảng ngày + nhân viên/đội ở nơi gọi), tuyệt đối không đụng tới các ngày khác của cùng
     * đợt nằm ngoài phạm vi đã chọn. Recurrence chỉ bị huỷ nếu sau khi xoá, đợt đó không còn
     * bản ghi ShiftSchedule nào (tức phạm vi đã chọn vô tình xoá hết toàn bộ đợt).
     */
    private function deleteSchedules($schedules, bool $cascadeBatch = true): array
    {
        $schedules = $schedules instanceof \Illuminate\Support\Collection ? $schedules : collect($schedules);
        $batchIds  = $schedules->pluck('batch_id')->filter()->unique()->values();
        $singleIds = $schedules->whereNull('batch_id')->pluck('id')->values();

        if ($cascadeBatch) {
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
                    'batch_count'           => $batchIds->count(),
                    'batch_deleted'         => $batchDeleted,
                    'single_deleted'        => $singleDeleted,
                    'recurrences_cancelled' => $batchIds->count(),
                ];
            });
        }

        $batchRowIds = $schedules->whereNotNull('batch_id')->pluck('id')->values();

        return DB::transaction(function () use ($batchIds, $singleIds, $batchRowIds) {
            $singleDeleted = $singleIds->isNotEmpty() ? ShiftSchedule::whereIn('id', $singleIds)->delete() : 0;
            $batchDeleted  = $batchRowIds->isNotEmpty() ? ShiftSchedule::whereIn('id', $batchRowIds)->delete() : 0;

            $recurrencesCancelled = 0;
            foreach ($batchIds as $batchId) {
                if (!ShiftSchedule::where('batch_id', $batchId)->exists()) {
                    ShiftScheduleRecurrence::where('batch_id', $batchId)->delete();
                    $recurrencesCancelled++;
                }
            }

            return [
                'batch_count'           => $batchIds->count(),
                'batch_deleted'         => $batchDeleted,
                'single_deleted'        => $singleDeleted,
                'recurrences_cancelled' => $recurrencesCancelled,
            ];
        });
    }

    private function buildBulkDeleteMessage(array $result): string
    {
        $parts = [];
        if ($result['single_deleted'] > 0) {
            $parts[] = "{$result['single_deleted']} ca lẻ";
        }
        if ($result['batch_deleted'] > 0) {
            $parts[] = "{$result['batch_deleted']} ca thuộc đợt cố định";
        }

        $message = 'Đã xoá: ' . implode(', ', $parts) . '.';

        if (($result['recurrences_cancelled'] ?? 0) > 0) {
            $message .= " Đã huỷ {$result['recurrences_cancelled']} quy tắc lặp lại hàng tuần (không còn ca nào của đợt đó trong phạm vi đã chọn).";
        }

        return $message;
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
