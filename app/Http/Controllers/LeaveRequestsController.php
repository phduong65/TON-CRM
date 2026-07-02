<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ShiftSchedule;
use App\Services\AnnualLeaveService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveRequestsController extends Controller
{
    public function index(Request $request)
    {
        $isApprover = auth()->user()->can('approve-leave-requests');

        $query = LeaveRequest::with(['employee.branch', 'reviewer'])
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

        // Trang này chỉ tạo đơn cho chính mình — nạp sẵn các ca đã xếp (gần đây/sắp tới) của
        // nhân viên hiện tại để JS lọc theo ngày, hiển thị trong ô "Ca làm" của form tạo đơn.
        $ownEmployeeId  = auth()->user()->employee?->id;
        $ownShiftSchedules = $ownEmployeeId ? $this->shiftScheduleOptions([$ownEmployeeId]) : collect();

        // Số ngày phép năm còn lại theo từng nhân viên đủ điều kiện (chính thức + văn phòng),
        // JS hiển thị dưới ô "Loại nghỉ phép" khi chọn "Nghỉ phép năm" — khỏi cần gọi AJAX.
        $annualLeaveService = app(AnnualLeaveService::class);
        $balanceScope = $isApprover ? $employees : Employee::where('id', $ownEmployeeId)->get();
        $annualLeaveBalances = $balanceScope
            ->filter(fn(Employee $e) => $e->isEligibleForAnnualLeave())
            ->mapWithKeys(fn(Employee $e) => [$e->id => $annualLeaveService->remainingDays($e)]);

        return view('leave-requests.index', compact('leaveRequests', 'employees', 'allEmployees', 'isApprover', 'ownShiftSchedules', 'annualLeaveBalances'));
    }

    /**
     * Danh sách ca đã xếp (status=scheduled) trong khoảng ngày quanh hiện tại, dùng làm dữ liệu
     * cho ô chọn "Ca làm" trong form xin nghỉ — JS lọc theo employee_id + ngày phía client.
     */
    private function shiftScheduleOptions(array $employeeIds)
    {
        return ShiftSchedule::with('shift')
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'scheduled')
            ->whereBetween('work_date', [now()->subDays(14)->toDateString(), now()->addDays(90)->toDateString()])
            ->orderBy('work_date')
            ->get()
            ->map(fn($s) => [
                'id'          => $s->id,
                'employee_id' => $s->employee_id,
                'date'        => $s->work_date->toDateString(),
                'label'       => $s->work_date->format('d/m/Y') . ' — ' . $s->shift->name,
            ]);
    }

    public function store(Request $request)
    {
        // Approver (quyền duyệt bất kỳ loại nào trong hub Yêu cầu & Phê duyệt) được chọn nhân viên
        // khác để tạo hộ đơn nghỉ; nhân viên thường luôn tạo cho chính mình.
        $isApprover = auth()->user()->can('approve-leave-requests')
            || auth()->user()->can('approve-staff-requests')
            || auth()->user()->can('approve-shift-swaps');

        $validated = $request->validate([
            'employee_id'       => ($isApprover ? 'required' : 'nullable') . '|exists:employees,id',
            'date_from'         => 'required|date',
            'date_to'           => 'required|date|after_or_equal:date_from',
            'type'              => 'required|in:annual,unpaid,sick,other',
            'shift_schedule_id'    => 'nullable|integer|exists:shift_schedules,id',
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

        if ($validated['type'] === 'annual') {
            abort_unless($employee->isEligibleForAnnualLeave(), 422,
                'Nhân viên không đủ điều kiện nghỉ phép năm (chỉ áp dụng NV chính thức, văn phòng).');

            $requestedDays = Carbon::parse($validated['date_from'])->diffInDays($validated['date_to']) + 1;
            $remaining     = app(AnnualLeaveService::class)->remainingDays($employee);
            abort_if($requestedDays > $remaining, 422, "Không đủ số ngày phép năm còn lại (còn {$remaining} ngày).");
        }

        if (!empty($validated['shift_schedule_id'])) {
            $ownsSchedule = ShiftSchedule::where('id', $validated['shift_schedule_id'])
                ->where('employee_id', $employee->id)
                ->exists();
            abort_unless($ownsSchedule, 422, 'Ca làm đã chọn không thuộc về nhân viên này.');
        }

        $count = LeaveRequest::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;
        $code = 'LR-' . now()->format('Ym') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $leaveRequest = LeaveRequest::create([
            'code'                 => $code,
            'employee_id'          => $employee->id,
            'date_from'            => $validated['date_from'],
            'date_to'              => $validated['date_to'],
            'type'                 => $validated['type'],
            'shift_schedule_id'    => $validated['shift_schedule_id'] ?? null,
            'reason'               => $validated['reason'],
            'handover_employee_id' => $validated['handover_employee_id'] ?? null,
            'handover_phone'       => $validated['handover_phone'] ?? null,
            'handover_note'        => $validated['handover_note'] ?? null,
            'status'               => 'pending',
        ]);

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

            // Huỷ các ca đã xếp của nhân viên trong khoảng ngày nghỉ để lưới xếp ca phản ánh đúng.
            ShiftSchedule::where('employee_id', $leaveRequest->employee_id)
                ->whereBetween('work_date', [$leaveRequest->date_from, $leaveRequest->date_to])
                ->where('status', 'scheduled')
                ->update(['status' => 'cancelled']);
        });

        $leaveRequest->loadMissing('employee');
        activity()->causedBy(auth()->user())
            ->performedOn($leaveRequest)
            ->inLog('leave_request')
            ->withProperties(['code' => $leaveRequest->code, 'employee_name' => $leaveRequest->employee?->name])
            ->log("Duyệt đơn xin nghỉ {$leaveRequest->code}");

        app(NotificationService::class)->notifyLeaveRequestApproved($leaveRequest);

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

    public function destroy(LeaveRequest $leaveRequest)
    {
        abort_unless($leaveRequest->employee?->user_id === auth()->id(), 403, 'Bạn chỉ có thể huỷ đơn của chính mình.');
        abort_if($leaveRequest->status !== 'pending', 403, 'Chỉ có thể huỷ đơn đang chờ duyệt.');

        $leaveRequest->delete();

        return back()->with('success', 'Đã huỷ đơn xin nghỉ.');
    }
}
