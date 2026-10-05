<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Services\AnnualLeaveService;
use App\Services\AttendanceAlertService;
use App\Services\NotificationService;
use App\Support\Concerns\PreventsDuplicateSubmission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveRequestsController extends Controller
{
    use PreventsDuplicateSubmission;

    public function index(Request $request)
    {
        // "Approver" ở đây là bất kỳ ai có quyền duyệt một trong các loại yêu cầu thuộc hub
        // Yêu cầu & Phê duyệt (nghỉ phép / yêu cầu khác / đổi ca) — đồng bộ với store() bên dưới,
        // để admin/HR/manager luôn thấy được ô chọn nhân viên và tạo đơn hộ người khác.
        $isApprover = auth()->user()->can('approve-leave-requests')
            || auth()->user()->can('approve-staff-requests')
            || auth()->user()->can('approve-shift-swaps');

        $query = LeaveRequest::with(['employee.branch', 'employee.user', 'reviewer', 'shiftSchedules.shift', 'shiftSchedule.shift'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at');

        if (!$isApprover) {
            $employeeId = auth()->user()->employee?->id;
            $query->where('employee_id', $employeeId ?? 0);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leaveRequests = $query->paginate(15)->withQueryString();
        $allEmployees  = Employee::where('is_active', true)->orderBy('name')->get();
        $employees     = $isApprover ? $allEmployees : collect();

        // Số ngày phép năm còn lại theo từng nhân viên đủ điều kiện (chính thức + văn phòng),
        // JS hiển thị dưới ô "Loại nghỉ phép" khi chọn "Nghỉ phép năm" — khỏi cần gọi AJAX.
        $ownEmployeeId = auth()->user()->employee?->id;
        $annualLeaveService = app(AnnualLeaveService::class);
        $balanceScope = $isApprover ? $employees : Employee::where('id', $ownEmployeeId)->get();
        $annualLeaveBalances = $balanceScope
            ->filter(fn(Employee $e) => $e->isEligibleForAnnualLeave())
            ->mapWithKeys(fn(Employee $e) => [$e->id => $annualLeaveService->remainingDays($e)]);

        // employee_id => is_office — JS dùng để chỉ hiện lựa chọn "Nghỉ nửa ngày (theo giờ)" cho
        // đúng khối văn phòng (nhà hàng/bếp/bar theo ca trọn, không có khái niệm nửa ca).
        $officeFlags = $balanceScope->mapWithKeys(fn(Employee $e) => [$e->id => (bool) $e->is_office]);

        return view('leave-requests.index', compact('leaveRequests', 'employees', 'allEmployees', 'isApprover', 'annualLeaveBalances', 'officeFlags'));
    }

    /**
     * Danh sách ca đã xếp (status=scheduled) của 1 nhân viên trong khoảng [date_from, date_to] —
     * gọi qua AJAX mỗi khi người dùng đổi ngày/nhân viên trên form xin nghỉ, dùng làm dữ liệu cho
     * ô chọn nhiều ca cụ thể cần nghỉ (có thể trải dài nhiều ngày, VD NV part-time 2 ngày có 4 ca
     * nhưng chỉ muốn nghỉ 3 ca). Không giới hạn theo cửa sổ ngày cố định — tra đúng khoảng đã chọn.
     */
    public function shiftsForRange(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'date_from'   => 'required|date',
            'date_to'     => 'required|date|after_or_equal:date_from',
        ]);

        $isApprover = auth()->user()->can('approve-leave-requests')
            || auth()->user()->can('approve-staff-requests')
            || auth()->user()->can('approve-shift-swaps');

        if (!$isApprover) {
            $ownEmployeeId = auth()->user()->employee?->id;
            abort_unless($ownEmployeeId && (int) $validated['employee_id'] === (int) $ownEmployeeId, 403);
        }

        $options = ShiftSchedule::with('shift')
            ->where('employee_id', $validated['employee_id'])
            ->whereBetween('work_date', [$validated['date_from'], $validated['date_to']])
            ->where('status', 'scheduled')
            ->orderBy('work_date')
            ->get()
            ->map(fn($s) => $this->mapShiftScheduleOption($s));

        return response()->json(['options' => $options]);
    }

    private function mapShiftScheduleOption(ShiftSchedule $s): array
    {
        $shift = $s->effectiveShift();
        // Ca linh hoạt (shift_id null, VD gán WFH đột xuất) không có $s->shift — dùng tên
        // dự phòng từ effectiveShift() thay vì $s->shift->name (null, gây nhãn rỗng).
        $shiftName = $s->shift?->name ?? ($shift?->work_mode === 'wfh' ? 'WFH (ca linh hoạt)' : 'Ca linh hoạt');

        return [
            'id'          => $s->id,
            'employee_id' => $s->employee_id,
            'date'        => $s->work_date->toDateString(),
            'label'       => $s->work_date->format('d/m/Y') . ' — ' . $shiftName,
            'shift_type'  => $shift?->shift_type ?? 'fulltime',
            // Dùng để giới hạn khung giờ nghỉ nửa ngày (mode "custom_time") trong đúng khoảng ca —
            // và gợi ý mặc định "Nghỉ buổi sáng"/"Nghỉ buổi chiều" ở phía client.
            'start_time'  => $shift?->start_time ? substr($shift->start_time, 0, 5) : null,
            'end_time'    => $shift?->end_time ? substr($shift->end_time, 0, 5) : null,
            // Điểm chia nửa ca theo giờ công thực (đã tính giờ nghỉ) — nút "Nghỉ buổi sáng/chiều"
            // dùng làm ranh giới thay vì hardcode 12:00/13:00.
            'split_time'    => $shift?->halfDaySplitTime(),
            'break_start'   => $shift?->break_start_time ? substr($shift->break_start_time, 0, 5) : null,
            'break_minutes' => (int) ($shift?->break_minutes ?? 0),
            // Ca này đã bị 1 đơn nghỉ theo giờ ĐÃ DUYỆT cắt bớt khung giờ (adjusted_*): start_time/
            // end_time ở trên là khung giờ CÒN LẠI, không phải khung giờ ca gốc. Client hiển thị chú
            // thích để người dùng không nhầm "ca 09:00–18:00 sao chỉ chọn được từ 12:00".
            'leave_adjusted' => (bool) ($s->adjusted_start_time || $s->adjusted_end_time),
        ];
    }

    public function store(Request $request)
    {
        // Approver (quyền duyệt bất kỳ loại nào trong hub Yêu cầu & Phê duyệt) được chọn nhân viên
        // khác để tạo hộ đơn nghỉ; nhân viên thường luôn tạo cho chính mình.
        $isApprover = auth()->user()->can('approve-leave-requests')
            || auth()->user()->can('approve-staff-requests')
            || auth()->user()->can('approve-shift-swaps');

        $validated = $request->validate([
            'employee_id'          => ($isApprover ? 'required' : 'nullable') . '|exists:employees,id',
            'date_from'            => 'required|date',
            'date_to'              => 'required|date|after_or_equal:date_from',
            'type'                 => 'required|in:annual,unpaid',
            'is_partial_day'       => 'nullable|boolean',
            'partial_mode'         => 'nullable|in:shifts,custom_time',
            'shift_schedule_ids'   => 'nullable|array',
            'shift_schedule_ids.*' => 'integer|exists:shift_schedules,id',
            'shift_schedule_id'    => 'nullable|integer|exists:shift_schedules,id',
            'from_time'            => 'nullable|date_format:H:i|required_if:partial_mode,custom_time',
            'to_time'              => 'nullable|date_format:H:i|after:from_time|required_if:partial_mode,custom_time',
            'reason'               => 'required|string|max:1000',
            'handover_employee_id' => 'nullable|exists:employees,id',
            'handover_phone'       => 'nullable|string|max:20',
            'handover_note'        => 'nullable|string|max:1000',
        ], [
            'employee_id.required' => 'Vui lòng chọn nhân viên.',
        ]);

        if ($isApprover && !empty($validated['employee_id'])) {
            $employee = Employee::findOrFail($validated['employee_id']);
        } else {
            $employee = auth()->user()->employee;
            abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');
        }

        // Chặn double-submit (double-click / gửi lại khi mạng chậm) tạo trùng 2 đơn giống hệt.
        if ($this->wasJustSubmitted(LeaveRequest::class, [
            'employee_id' => $employee->id, 'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'], 'type' => $validated['type'],
        ])) {
            return back()->with('success', 'Đã gửi đơn xin nghỉ, vui lòng chờ phê duyệt!');
        }

        $isPartialDay = (bool) ($validated['is_partial_day'] ?? false);
        $partialMode  = $validated['partial_mode'] ?? 'shifts';
        $dayFraction  = null;
        // scheduleId => tỉ lệ ngày phép của riêng ca đó — dùng lại khi gắn vào bảng phụ bên dưới,
        // tránh tính lại (mỗi ca cần 1 query tổng phút các ca cùng ngày, tính 2 lần sẽ lãng phí).
        $perScheduleFraction = [];
        // Chỉ set khi partial_mode=custom_time — dữ liệu ghi thẳng vào leave_requests (không qua
        // bảng phụ leave_request_shift_schedules, vì chỉ áp dụng đúng 1 ca/1 ngày).
        $customTimeFields = null;

        if ($isPartialDay && $partialMode === 'custom_time') {
            // Nghỉ nửa ngày theo khung giờ cụ thể (VD 09:00–12:00) trong 1 ca duy nhất — chỉ áp
            // dụng khối văn phòng (NV nhà hàng/bếp/bar làm trọn ca, không có khái niệm nửa ca).
            // Khác với "shifts" mode (chọn nguyên nhiều ca, có thể nhiều ngày): mode này chỉ cho 1
            // ca/1 ngày vì day_fraction dựa trên khung giờ thủ công, không nhân được lên nhiều ca.
            abort_unless($employee->is_office, 422, 'Nghỉ nửa ngày theo giờ chỉ áp dụng cho nhân viên khối văn phòng.');

            if ($validated['date_from'] !== $validated['date_to']) {
                return back()->withInput()->withErrors([
                    'to_time' => 'Nghỉ nửa ngày theo giờ chỉ áp dụng cho đúng 1 ngày — vui lòng đặt "Đến ngày" trùng "Ngày bắt đầu".',
                ]);
            }

            $schedule = ShiftSchedule::with('shift')
                ->where('id', $validated['shift_schedule_id'] ?? 0)
                ->where('employee_id', $employee->id)
                ->where('work_date', $validated['date_from'])
                ->where('status', 'scheduled')
                ->first();

            if (!$schedule) {
                return back()->withInput()->withErrors([
                    'shift_schedule_id' => 'Vui lòng chọn ca cần nghỉ nửa ngày — không tìm thấy ca đã xếp phù hợp cho ngày này.',
                ]);
            }

            $shift = $schedule->effectiveShift();

            if (!$shift || !$shift->start_time || !$shift->end_time) {
                return back()->withInput()->withErrors([
                    'shift_schedule_id' => 'Không xác định được khung giờ của ca đã chọn — vui lòng liên hệ quản lý xếp ca trước.',
                ]);
            }

            $fromTime   = $validated['from_time'];
            $toTime     = $validated['to_time'];
            $shiftStart = substr($shift->start_time, 0, 5);
            $shiftEnd   = substr($shift->end_time, 0, 5);

            if ($fromTime < $shiftStart || $toTime > $shiftEnd) {
                return back()->withInput()->withErrors([
                    'to_time' => "Khung giờ nghỉ phải nằm trong khung giờ ca đã chọn ({$shiftStart}–{$shiftEnd}).",
                ]);
            }

            // day_fraction theo GIỜ CÔNG thực (đã trừ giờ nghỉ giữa ca), không theo giờ đồng hồ —
            // nhờ vậy nghỉ nửa ngày ca 09:00–18:00 (nghỉ 12:00–13:00): sáng 09–14 và chiều 14–18
            // đều ra đúng 0.5. Mẫu số là tổng giờ công thực của ca (durationMinutes = span - break).
            $netWorkMinutes = $shift->durationMinutes();
            $offWorkMinutes = $shift->workMinutesInWindow($fromTime, $toTime);
            $dayFraction    = $netWorkMinutes > 0 ? round(min(1, $offWorkMinutes / $netWorkMinutes), 2) : 0.0;

            $customTimeFields = [
                'shift_schedule_id' => $schedule->id,
                'from_time'         => $fromTime,
                'to_time'           => $toTime,
            ];
        } elseif ($isPartialDay) {
            $scheduleIds = $validated['shift_schedule_ids'] ?? [];

            if (empty($scheduleIds)) {
                return back()->withInput()->withErrors([
                    'shift_schedule_ids' => 'Vui lòng chọn ít nhất 1 ca cần nghỉ.',
                ]);
            }

            $selectedSchedules = ShiftSchedule::with('shift')
                ->whereIn('id', $scheduleIds)
                ->where('employee_id', $employee->id)
                ->whereBetween('work_date', [$validated['date_from'], $validated['date_to']])
                ->get();

            if ($selectedSchedules->count() !== count(array_unique($scheduleIds))) {
                return back()->withInput()->withErrors([
                    'shift_schedule_ids' => 'Một số ca đã chọn không hợp lệ — không thuộc nhân viên này hoặc nằm ngoài khoảng ngày đã chọn.',
                ]);
            }

            foreach ($selectedSchedules->groupBy(fn($s) => $s->work_date->toDateString()) as $workDate => $schedulesOnDate) {
                // Mẫu số = tổng phút TẤT CẢ ca đã xếp trong ngày đó (không chỉ các ca được chọn) —
                // để nghỉ 1 trong N ca của 1 ngày chỉ trừ đúng tỉ lệ, không trừ nguyên 1 ngày phép.
                $totalMinutesThatDay = ShiftSchedule::where('employee_id', $employee->id)
                    ->where('work_date', $workDate)
                    ->where('status', 'scheduled')
                    ->get()
                    ->sum(fn(ShiftSchedule $s) => $this->shiftSpanMinutes($s->effectiveShift()));

                foreach ($schedulesOnDate as $schedule) {
                    $shift = $schedule->effectiveShift();

                    if (!$shift || !$shift->start_time || !$shift->end_time) {
                        return back()->withInput()->withErrors([
                            'shift_schedule_ids' => "Không xác định được khung giờ của ca ngày {$schedule->work_date->format('d/m/Y')} — vui lòng liên hệ quản lý xếp ca trước.",
                        ]);
                    }

                    $minutes = $this->shiftSpanMinutes($shift);
                    $perScheduleFraction[$schedule->id] = $totalMinutesThatDay > 0
                        ? round(min(1, $minutes / $totalMinutesThatDay), 2)
                        : 0.0;
                }
            }

            $dayFraction = round(array_sum($perScheduleFraction), 2);
        }

        if ($validated['type'] === 'annual') {
            if (!$employee->isEligibleForAnnualLeave()) {
                return back()->withInput()->withErrors([
                    'type' => 'Nhân viên không đủ điều kiện nghỉ phép năm (chỉ áp dụng NV chính thức, văn phòng). '
                        . 'Vui lòng chọn loại nghỉ khác: Nghỉ không lương.',
                ]);
            }

            $requestedDays = $isPartialDay
                ? $dayFraction
                : Carbon::parse($validated['date_from'])->diffInDays($validated['date_to']) + 1;
            // Dùng đúng năm của date_from — remainingDays() mặc định lấy năm hiện tại, nếu không
            // truyền rõ thì xin nghỉ ngày thuộc năm khác (VD xin từ tháng 12 cho đầu năm sau) sẽ bị
            // kiểm tra nhầm số dư của năm hiện tại thay vì năm thực sự áp dụng đơn nghỉ đó.
            $leaveYear = Carbon::parse($validated['date_from'])->year;
            $remaining = app(AnnualLeaveService::class)->remainingDays($employee, $leaveYear);

            if ($requestedDays > $remaining) {
                return back()->withInput()->withErrors([
                    'type' => "Không đủ số ngày phép năm còn lại (còn {$remaining} ngày, đang xin {$requestedDays} ngày). "
                        . 'Vui lòng chọn loại nghỉ khác (VD: Nghỉ không lương) hoặc giảm số ngày xin nghỉ.',
                ]);
            }
        }

        // withTrashed() bắt buộc: nếu chỉ đếm bản ghi còn sống, xoá 1 đơn ở giữa tháng sẽ làm
        // số đếm bị lùi lại, sinh trùng "code" với đơn chưa xoá (code có unique constraint).
        $count = LeaveRequest::withTrashed()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;
        $code = 'LR-' . now()->format('Ym') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $leaveRequest = LeaveRequest::create([
            'code'                 => $code,
            'employee_id'          => $employee->id,
            'date_from'            => $validated['date_from'],
            'date_to'              => $validated['date_to'],
            'is_partial_day'       => $isPartialDay,
            'day_fraction'         => $dayFraction,
            'shift_schedule_id'    => $customTimeFields['shift_schedule_id'] ?? null,
            'from_time'            => $customTimeFields['from_time'] ?? null,
            'to_time'              => $customTimeFields['to_time'] ?? null,
            'type'                 => $validated['type'],
            'reason'               => $validated['reason'],
            'handover_employee_id' => $validated['handover_employee_id'] ?? null,
            'handover_phone'       => $validated['handover_phone'] ?? null,
            'handover_note'        => $validated['handover_note'] ?? null,
            'status'               => 'pending',
        ]);

        if ($isPartialDay && $partialMode !== 'custom_time') {
            $leaveRequest->shiftSchedules()->attach(
                collect($perScheduleFraction)->mapWithKeys(fn($fraction, $id) => [$id => ['day_fraction' => $fraction]])->all()
            );
        }

        activity()->causedBy(auth()->user())
            ->performedOn($leaveRequest)
            ->inLog('leave_request')
            ->withProperties(['code' => $code, 'employee_name' => $employee->name])
            ->log("Gửi đơn xin nghỉ {$code} — {$employee->name}");

        app(NotificationService::class)->notifyLeaveRequestCreated($leaveRequest);

        return back()->with('success', 'Đã gửi đơn xin nghỉ, vui lòng chờ phê duyệt!');
    }

    public function approve(LeaveRequest $leaveRequest)
    {
        DB::transaction(function () use (&$leaveRequest) {
            $leaveRequest = LeaveRequest::lockForUpdate()->findOrFail($leaveRequest->id);
            abort_if($leaveRequest->status !== 'pending', 403, 'Đơn xin nghỉ không ở trạng thái chờ duyệt.');

            $leaveRequest->update([
                'status'      => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $selectedScheduleIds = $leaveRequest->shiftSchedules()->pluck('shift_schedules.id');

            if ($leaveRequest->is_partial_day && $selectedScheduleIds->isNotEmpty()) {
                // Nghỉ theo ca cụ thể (có thể nhiều ca, nhiều ngày) — huỷ đúng các ca đã chọn,
                // các ca khác trong cùng ngày/khoảng ngày của nhân viên không bị ảnh hưởng.
                ShiftSchedule::whereIn('id', $selectedScheduleIds)
                    ->where('status', 'scheduled')
                    ->update(['status' => 'cancelled']);
            } elseif ($leaveRequest->is_partial_day && $leaveRequest->shift_schedule_id) {
                // Dữ liệu cũ (tạo trước khi có tính năng chọn nhiều ca) — nghỉ theo giờ thủ công
                // trong đúng 1 ca, có thể chỉ nửa ca chứ không trọn ca.
                $schedule = ShiftSchedule::lockForUpdate()->find($leaveRequest->shift_schedule_id);
                $shift    = $schedule?->effectiveShift();

                if ($schedule && $shift) {
                    // substr(...,0,5) ở cả 2 vế: cột from_time/to_time là DB type TIME, đọc lại từ
                    // DB có thể trả về "09:00:00" (kèm giây) dù lúc lưu chỉ có "09:00".
                    $isWholeShiftOff = substr((string) $leaveRequest->from_time, 0, 5) === substr($shift->start_time, 0, 5)
                        && substr((string) $leaveRequest->to_time, 0, 5) === substr($shift->end_time, 0, 5);

                    if ($isWholeShiftOff) {
                        $schedule->update(['status' => 'cancelled']);
                    } else {
                        // Nghỉ theo giờ (nửa ca đầu/cuối): KHÔNG huỷ ca — chỉ điều chỉnh khung giờ
                        // còn lại phải chấm công (xem ShiftSchedule::effectiveShift()), để nhân
                        // viên vẫn phải vào/ra đúng phần ca chưa nghỉ, không bị tính trễ/sớm cho
                        // phần đã được duyệt nghỉ.
                        $schedule->update($this->resolveAdjustedWindow($schedule->work_date->toDateString(), $shift, $leaveRequest->from_time, $leaveRequest->to_time));
                    }
                }
            } else {
                // Huỷ các ca đã xếp của nhân viên trong khoảng ngày nghỉ để lưới xếp ca phản ánh đúng.
                ShiftSchedule::where('employee_id', $leaveRequest->employee_id)
                    ->whereBetween('work_date', [$leaveRequest->date_from, $leaveRequest->date_to])
                    ->where('status', 'scheduled')
                    ->update(['status' => 'cancelled']);
            }
        });

        $leaveRequest->loadMissing('employee');
        activity()->causedBy(auth()->user())
            ->performedOn($leaveRequest)
            ->inLog('leave_request')
            ->withProperties(['code' => $leaveRequest->code, 'employee_name' => $leaveRequest->employee?->name])
            ->log("Duyệt đơn xin nghỉ {$leaveRequest->code}");

        app(NotificationService::class)->notifyLeaveRequestApproved($leaveRequest);
        app(AttendanceAlertService::class)->excuseAlertsOnLeaveApproved($leaveRequest);

        return back()->with('success', 'Đã duyệt đơn xin nghỉ!');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        abort_if($leaveRequest->status !== 'pending', 403, 'Đơn xin nghỉ không ở trạng thái chờ duyệt.');

        $request->validate(['rejection_reason' => 'required|string|max:500']);

        $leaveRequest->update([
            'status'            => 'rejected',
            'reviewed_by'       => auth()->id(),
            'reviewed_at'       => now(),
            'rejection_reason'  => $request->rejection_reason,
        ]);

        $leaveRequest->loadMissing('employee');
        activity()->causedBy(auth()->user())
            ->performedOn($leaveRequest)
            ->inLog('leave_request')
            ->withProperties(['code' => $leaveRequest->code, 'reason' => $request->rejection_reason])
            ->log("Từ chối đơn xin nghỉ {$leaveRequest->code}");

        app(NotificationService::class)->notifyLeaveRequestRejected($leaveRequest, $request->rejection_reason);

        return back()->with('success', 'Đã từ chối đơn xin nghỉ.');
    }

    /**
     * Chính chủ tự huỷ đơn của mình khi còn "Chờ duyệt", HOẶC người có quyền
     * delete-leave-requests (quản lý/admin) xoá bất kỳ đơn nào bất kể trạng thái.
     */
    public function destroy(LeaveRequest $leaveRequest)
    {
        // Quy tắc xoá theo trạng thái (đồng bộ với StaffRequests/ShiftSwap):
        // - Chờ duyệt: chính chủ tự huỷ, HOẶC người có delete-leave-requests xoá.
        // - Từ chối: người có delete-leave-requests xoá (không có gì để đảo ngược).
        // - Đã duyệt: CHỈ admin (delete-approved-requests) — xoá kèm hoàn phép + khôi phục lịch.
        $isOwner    = $leaveRequest->employee?->user_id === auth()->id();
        $canPurge   = auth()->user()->can('delete-leave-requests');
        $canReverse = auth()->user()->can('delete-approved-requests');
        $status     = $leaveRequest->status;

        if ($status === 'approved') {
            abort_unless($canReverse, 403, 'Chỉ admin được xoá đơn đã duyệt (thao tác sẽ hoàn phép và khôi phục lịch).');
        } elseif ($status === 'pending') {
            abort_unless($isOwner || $canPurge, 403, 'Bạn không có quyền xoá đơn này.');
        } else { // rejected
            abort_unless($canPurge, 403, 'Bạn không có quyền xoá đơn này.');
        }

        $leaveRequest->loadMissing('employee');

        // Xoá đơn ĐÃ DUYỆT phải hoàn tác toàn bộ ảnh hưởng lúc duyệt: (1) khôi phục các ca đã bị
        // huỷ/điều chỉnh khung giờ về "đã xếp" để lịch nhân viên trở lại như trước; (2) hoàn số ngày
        // phép năm (nếu là nghỉ có lương). Quỹ phép năm tính realtime từ các đơn CHƯA xoá
        // (AnnualLeaveService::usedDays), nên soft delete tự động trả lại số ngày — chỉ cần tính
        // trước để thông báo cho người dùng.
        $wasApproved  = $status === 'approved';
        $refundedDays = ($wasApproved && $leaveRequest->type === 'annual') ? $leaveRequest->daysCount() : 0.0;

        DB::transaction(function () use ($leaveRequest, $wasApproved) {
            $locked = LeaveRequest::lockForUpdate()->findOrFail($leaveRequest->id);

            if ($wasApproved) {
                $this->restoreSchedulesForApprovedLeave($locked);
            }

            $locked->delete();
        });

        $verb = ($status === 'pending' && $isOwner && !$canPurge) ? 'huỷ' : 'xoá';
        activity()->causedBy(auth()->user())
            ->performedOn($leaveRequest)
            ->inLog('leave_request')
            ->withProperties(['code' => $leaveRequest->code, 'refunded_days' => $refundedDays])
            ->log(ucfirst($verb) . " đơn xin nghỉ {$leaveRequest->code}"
                . ($refundedDays > 0 ? " — hoàn {$this->formatDays($refundedDays)} ngày phép năm" : ''));

        $message = 'Đã ' . $verb . ' đơn xin nghỉ.';
        if ($refundedDays > 0) {
            $message .= ' Đã hoàn ' . $this->formatDays($refundedDays) . ' ngày phép năm về cho '
                . ($leaveRequest->employee?->name ?? 'nhân viên') . '.';
        }

        return back()->with('success', $message);
    }

    /**
     * Hoàn tác các ca bị ảnh hưởng khi duyệt đơn nghỉ (đảo ngược LeaveRequestsController::approve):
     * ca bị huỷ → về "đã xếp", ca bị điều chỉnh khung giờ (nghỉ nửa ca) → xoá adjusted_start/end.
     * Bỏ qua ca vẫn đang được phủ bởi 1 đơn nghỉ ĐÃ DUYỆT khác (tránh "mở lại" ca đúng ra phải
     * tiếp tục nghỉ vì trùng ngày với đơn khác).
     */
    private function restoreSchedulesForApprovedLeave(LeaveRequest $leaveRequest): void
    {
        $restore = function (ShiftSchedule $schedule) use ($leaveRequest): void {
            if ($this->scheduleCoveredByOtherApprovedLeave($schedule, $leaveRequest->id)) {
                return;
            }
            $schedule->update([
                'status'              => 'scheduled',
                'adjusted_start_time' => null,
                'adjusted_end_time'   => null,
            ]);
        };

        // Nghỉ theo ca cụ thể (có thể nhiều ca/nhiều ngày) — khôi phục đúng các ca đã bị huỷ.
        $pivotIds = $leaveRequest->shiftSchedules()->pluck('shift_schedules.id');
        if ($pivotIds->isNotEmpty()) {
            ShiftSchedule::whereIn('id', $pivotIds)->lockForUpdate()->get()->each($restore);
            return;
        }

        // Nghỉ nửa ngày theo giờ / dữ liệu cũ (1 ca): ca có thể đã bị huỷ trọn ca hoặc chỉ bị điều
        // chỉnh khung giờ (status vẫn "scheduled" + adjusted_*), nên xử lý không lọc theo status.
        if ($leaveRequest->shift_schedule_id) {
            $schedule = ShiftSchedule::lockForUpdate()->find($leaveRequest->shift_schedule_id);
            if ($schedule) {
                $restore($schedule);
            }
            return;
        }

        // Nghỉ cả ngày — khôi phục các ca trong khoảng ngày đã bị huỷ lúc duyệt.
        ShiftSchedule::where('employee_id', $leaveRequest->employee_id)
            ->whereBetween('work_date', [$leaveRequest->date_from, $leaveRequest->date_to])
            ->where('status', 'cancelled')
            ->lockForUpdate()
            ->get()
            ->each($restore);
    }

    /**
     * Ca này có đang được phủ bởi 1 đơn nghỉ ĐÃ DUYỆT khác (≠ đơn đang xoá) không — dùng để không
     * "mở lại" nhầm ca vốn vẫn phải tiếp tục nghỉ theo đơn khác.
     */
    private function scheduleCoveredByOtherApprovedLeave(ShiftSchedule $schedule, int $excludeLeaveId): bool
    {
        $base = LeaveRequest::where('status', 'approved')->where('id', '!=', $excludeLeaveId);

        $viaShift = (clone $base)->where(function ($q) use ($schedule) {
            $q->where('shift_schedule_id', $schedule->id)
                ->orWhereHas('shiftSchedules', fn($qq) => $qq->where('shift_schedules.id', $schedule->id));
        })->exists();

        if ($viaShift) {
            return true;
        }

        // Đơn nghỉ CẢ NGÀY khác phủ đúng ngày làm việc của ca này.
        return (clone $base)
            ->where('employee_id', $schedule->employee_id)
            ->where('is_partial_day', false)
            ->whereDate('date_from', '<=', $schedule->work_date->toDateString())
            ->whereDate('date_to', '>=', $schedule->work_date->toDateString())
            ->exists();
    }

    /** "0.5" / "1" / "1.5" — số ngày phép gọn (bỏ số 0 thừa) cho thông báo. */
    private function formatDays(float $days): string
    {
        return rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.');
    }

    /**
     * Tổng số phút của 1 ca (giờ ra - giờ vào, tự cộng thêm 1 ngày nếu ca qua đêm) — dùng làm tử
     * số/mẫu số khi tính tỉ lệ ngày phép bị trừ cho từng ca được chọn nghỉ.
     */
    private function shiftSpanMinutes(?Shift $shift): int
    {
        if (!$shift || !$shift->start_time || !$shift->end_time) {
            return 0;
        }

        $start = Carbon::parse($shift->start_time);
        $end   = Carbon::parse($shift->end_time);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end);
    }

    /**
     * Khung giờ còn lại phải chấm công sau khi trừ phần đã được duyệt nghỉ — chỉ ghi đè đúng 1
     * phía (đầu hoặc cuối ca), giữ nguyên phía còn lại theo giờ ca gốc.
     */
    private function resolveAdjustedWindow(string $workDate, Shift $shift, string $fromTime, string $toTime): array
    {
        $shiftStart = Carbon::parse($workDate . ' ' . $shift->start_time);
        $leaveFrom  = Carbon::parse($workDate . ' ' . $fromTime);

        if ($leaveFrom->lessThanOrEqualTo($shiftStart)) {
            // Nghỉ từ đầu ca -> phần còn lại bắt đầu từ giờ kết thúc nghỉ.
            return ['adjusted_start_time' => $toTime, 'adjusted_end_time' => null];
        }

        // Nghỉ đến hết ca -> phần còn lại kết thúc tại giờ bắt đầu nghỉ.
        return ['adjusted_start_time' => null, 'adjusted_end_time' => $fromTime];
    }
}
