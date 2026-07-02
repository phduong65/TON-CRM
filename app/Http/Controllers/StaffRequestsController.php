<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffRequestRequest;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use App\Models\StaffRequest;
use App\Models\Team;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Hub "Yêu cầu và Phê duyệt" — gộp hiển thị 6 loại yêu cầu:
 * Lượt chấm công, Công tác/Ra ngoài, Đi muộn về sớm, Nghỉ phép, Thay đổi giờ vào/ra, Đổi ca làm.
 * 4 loại đầu dùng chung bảng staff_requests; Nghỉ phép & Đổi ca làm vẫn dùng bảng/controller
 * riêng đã có sẵn (LeaveRequestsController, ShiftSwapRequestsController) — trang này chỉ
 * gộp danh sách hiển thị + duyệt/từ chối bằng cách gọi đúng route gốc của từng loại.
 */
class StaffRequestsController extends Controller
{
    private const TYPE_LABELS = [
        'attendance_correction' => 'Lượt chấm công',
        'business_trip'         => 'Công tác/Ra ngoài',
        'late_early'            => 'Đi muộn về sớm',
        'leave'                 => 'Nghỉ phép',
        'time_change'           => 'Thay đổi giờ vào/ra',
        'shift_swap'            => 'Đổi ca làm',
    ];

    public function index(Request $request)
    {
        $user = auth()->user();
        $canApproveStaff = $user->can('approve-staff-requests');
        $canApproveLeave = $user->can('approve-leave-requests');
        $canApproveSwap  = $user->can('approve-shift-swaps');
        $isApprover      = $canApproveStaff || $canApproveLeave || $canApproveSwap;

        $ownEmployeeId  = $user->employee?->id ?? 0;
        $employeeFilter = $isApprover ? $request->input('employee_id') : $ownEmployeeId;
        $branchFilter   = $isApprover ? $request->input('branch_id') : null;
        $teamFilter     = $isApprover ? $request->input('team_id') : null;
        $statusFilter   = $request->input('status');
        $typeFilter     = $request->input('type');

        $applyEmployeeScope = function ($q) use ($branchFilter, $teamFilter) {
            $branchFilter && $q->where('branch_id', $branchFilter);
            $teamFilter && $q->where('team_id', $teamFilter);
        };

        // Luôn tải đủ cả 6 loại (bỏ qua $typeFilter ở bước này) để đếm số lượng theo từng loại
        // cho đúng bộ lọc nhân viên/chi nhánh/đội nhóm/trạng thái hiện tại — lọc theo loại áp
        // dụng sau khi đã đếm xong, phía dưới.
        $rows = collect();

        $query = LeaveRequest::with(['employee.branch', 'employee.team', 'reviewer']);
        $employeeFilter ? $query->where('employee_id', $employeeFilter) : (!$isApprover && $query->where('employee_id', $ownEmployeeId));
        ($branchFilter || $teamFilter) && $query->whereHas('employee', $applyEmployeeScope);
        $statusFilter && $query->where('status', $statusFilter);
        $rows = $rows->merge($query->get()->map(fn($lr) => $this->normalizeLeave($lr)));

        $query = ShiftSwapRequest::with(['requesterEmployee.branch', 'targetEmployee', 'requesterSchedule.shift', 'targetSchedule.shift', 'reviewer']);
        if ($employeeFilter) {
            $query->where(fn($q) => $q->where('requester_employee_id', $employeeFilter)->orWhere('target_employee_id', $employeeFilter));
        } elseif (!$isApprover) {
            $query->where(fn($q) => $q->where('requester_employee_id', $ownEmployeeId)->orWhere('target_employee_id', $ownEmployeeId));
        }
        if ($branchFilter || $teamFilter) {
            $query->where(fn($q) => $q->whereHas('requesterEmployee', $applyEmployeeScope)->orWhereHas('targetEmployee', $applyEmployeeScope));
        }
        $statusFilter && $query->where('status', $statusFilter);
        $rows = $rows->merge($query->get()->map(fn($swap) => $this->normalizeSwap($swap)));

        $query = StaffRequest::with(['employee.branch', 'employee.team', 'reviewer']);
        $employeeFilter ? $query->where('employee_id', $employeeFilter) : (!$isApprover && $query->where('employee_id', $ownEmployeeId));
        ($branchFilter || $teamFilter) && $query->whereHas('employee', $applyEmployeeScope);
        $statusFilter && $query->where('status', $statusFilter);
        $rows = $rows->merge($query->get()->map(fn($sr) => $this->normalizeStaff($sr)));

        $typeCounts = collect(self::TYPE_LABELS)->keys()->mapWithKeys(
            fn($key) => [$key => $rows->where('type_key', $key)->count()]
        );

        if ($typeFilter) {
            $rows = $rows->where('type_key', $typeFilter)->values();
        }

        $rows = $rows->sort(function ($a, $b) {
            $aPending = $a['status'] === 'pending' ? 0 : 1;
            $bPending = $b['status'] === 'pending' ? 0 : 1;

            return $aPending === $bPending ? $b['created_at'] <=> $a['created_at'] : $aPending <=> $bPending;
        })->values();

        $perPage = 15;
        $page    = max(1, (int) $request->input('page', 1));
        $requests = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Không lọc theo branch/team ở đây — truyền đủ danh sách nhân viên để combobox tự lọc
        // ngay trên trình duyệt khi đổi chi nhánh/đội nhóm (khỏi phải bấm Lọc mới thấy).
        $allEmployees = Employee::where('is_active', true)->orderBy('name')->get();
        $employees    = $isApprover ? $allEmployees : collect();

        $branches = $isApprover ? Branch::where('is_active', true)->orderBy('name')->get() : collect();
        $teams    = $isApprover ? Team::where('is_active', true)->orderBy('name')->get() : collect();

        // Ca đã xếp (status=scheduled) của các nhân viên liên quan, dùng làm dữ liệu cho ô chọn
        // "Ca làm" trong form xin nghỉ — JS lọc theo employee_id + ngày phía client, không cần AJAX.
        $scheduleEmployeeIds = $isApprover ? $employees->pluck('id')->all() : array_filter([$ownEmployeeId]);
        $shiftScheduleOptions = empty($scheduleEmployeeIds) ? collect() : ShiftSchedule::with('shift')
            ->whereIn('employee_id', $scheduleEmployeeIds)
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

        return view('staff-requests.index', compact('requests', 'employees', 'allEmployees', 'branches', 'teams', 'isApprover', 'canApproveStaff', 'canApproveLeave', 'canApproveSwap', 'typeCounts', 'shiftScheduleOptions'));
    }

    private function normalizeLeave(LeaveRequest $lr): array
    {
        return [
            'source'             => 'leave',
            'id'                 => $lr->id,
            'code'               => $lr->code,
            'type_key'           => 'leave',
            'type_label'         => 'Nghỉ phép',
            'employee'           => $lr->employee,
            'work_date_label'    => $lr->date_from->format('d/m/Y') . ' – ' . $lr->date_to->format('d/m/Y') . ' (' . $lr->daysCount() . ' ngày)',
            'summary'            => $lr->typeLabel() . ($lr->reason ? ' · ' . Str::limit($lr->reason, 60) : ''),
            'status'             => $lr->status,
            'status_label'       => $lr->statusLabel(),
            'status_badge'       => $lr->statusBadgeClass(),
            'rejection_reason'   => $lr->rejection_reason,
            'reviewer'           => $lr->reviewer,
            'created_at'         => $lr->created_at,
            'approve_route'      => $lr->status === 'pending' ? route('leave-requests.approve', $lr) : null,
            'reject_route'       => $lr->status === 'pending' ? route('leave-requests.reject', $lr) : null,
            'destroy_route'      => $lr->status === 'pending' ? route('leave-requests.destroy', $lr) : null,
            'can_manage_own'     => $lr->employee?->user_id === auth()->id(),
            'approve_permission' => 'approve-leave-requests',
        ];
    }

    private function normalizeSwap(ShiftSwapRequest $swap): array
    {
        return [
            'source'             => 'shift_swap',
            'id'                 => $swap->id,
            'code'               => $swap->code,
            'type_key'           => 'shift_swap',
            'type_label'         => 'Đổi ca làm',
            'employee'           => $swap->requesterEmployee,
            'work_date_label'    => ($swap->requesterSchedule?->work_date?->format('d/m/Y') ?? '—') . ' ⇄ ' . ($swap->targetSchedule?->work_date?->format('d/m/Y') ?? '—'),
            'summary'            => 'Với ' . ($swap->targetEmployee?->name ?? '—') . ' · ' . ($swap->requesterSchedule?->shift?->name ?? '—') . ' ⇄ ' . ($swap->targetSchedule?->shift?->name ?? '—'),
            'status'             => $swap->status,
            'status_label'       => $swap->statusLabel(),
            'status_badge'       => $swap->statusBadgeClass(),
            'rejection_reason'   => $swap->rejection_reason,
            'reviewer'           => $swap->reviewer,
            'created_at'         => $swap->created_at,
            'approve_route'      => $swap->status === 'pending' ? route('shift-swap-requests.approve', $swap) : null,
            'reject_route'       => $swap->status === 'pending' ? route('shift-swap-requests.reject', $swap) : null,
            'destroy_route'      => $swap->status === 'pending' ? route('shift-swap-requests.destroy', $swap) : null,
            'can_manage_own'     => $swap->requesterEmployee?->user_id === auth()->id(),
            'approve_permission' => 'approve-shift-swaps',
        ];
    }

    private function normalizeStaff(StaffRequest $sr): array
    {
        return [
            'source'             => 'staff_request',
            'id'                 => $sr->id,
            'code'               => $sr->code,
            'type_key'           => $sr->type,
            'type_label'         => $sr->typeLabel(),
            'employee'           => $sr->employee,
            'work_date_label'    => $sr->work_date->format('d/m/Y'),
            'summary'            => $sr->summary() . ($sr->reason ? ' · ' . Str::limit($sr->reason, 60) : ''),
            'status'             => $sr->status,
            'status_label'       => $sr->statusLabel(),
            'status_badge'       => $sr->statusBadgeClass(),
            'rejection_reason'   => $sr->rejection_reason,
            'reviewer'           => $sr->reviewer,
            'created_at'         => $sr->created_at,
            'approve_route'      => $sr->status === 'pending' ? route('staff-requests.approve', $sr) : null,
            'reject_route'       => $sr->status === 'pending' ? route('staff-requests.reject', $sr) : null,
            'destroy_route'      => $sr->status === 'pending' ? route('staff-requests.destroy', $sr) : null,
            'can_manage_own'     => $sr->employee?->user_id === auth()->id(),
            'approve_permission' => 'approve-staff-requests',
        ];
    }

    public function store(StoreStaffRequestRequest $request)
    {
        $validated = $request->validated();
        $type      = $validated['type'];

        // Approver (quyền duyệt bất kỳ loại nào trong hub) được chọn nhân viên khác để tạo hộ;
        // nhân viên thường luôn tạo cho chính mình — bỏ qua employee_id nếu có gửi lên.
        if ($request->userIsApprover() && !empty($validated['employee_id'])) {
            $employee = Employee::findOrFail($validated['employee_id']);
        } else {
            $employee = auth()->user()->employee;
            abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');
        }

        $payload = match ($type) {
            'attendance_correction' => array_filter([
                'check_in_at'  => $validated['check_in_at'] ?? null,
                'check_out_at' => $validated['check_out_at'] ?? null,
            ]),
            'business_trip' => [
                'from_time' => $validated['from_time'],
                'to_time'   => $validated['to_time'],
                'location'  => $validated['location'],
            ],
            'late_early' => [
                'mode'    => $validated['mode'],
                'minutes' => (int) $validated['minutes'],
            ],
            'time_change' => [
                'new_check_in'  => $validated['new_check_in'],
                'new_check_out' => $validated['new_check_out'],
            ],
        };

        $prefixes = [
            'attendance_correction' => 'ATC',
            'business_trip'         => 'BTR',
            'late_early'            => 'LE',
            'time_change'           => 'TC',
        ];

        $count = StaffRequest::where('type', $type)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;
        $code = $prefixes[$type] . '-' . now()->format('Ym') . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);

        $staffRequest = StaffRequest::create([
            'code'        => $code,
            'employee_id' => $employee->id,
            'type'        => $type,
            'work_date'   => $validated['work_date'],
            'payload'     => $payload,
            'reason'      => $validated['reason'],
            'status'      => 'pending',
        ]);

        activity()->causedBy(auth()->user())
            ->performedOn($staffRequest)
            ->inLog('staff_request')
            ->withProperties(['code' => $code, 'type' => $type, 'employee_name' => $employee->name])
            ->log("Gửi yêu cầu {$staffRequest->typeLabel()} {$code} — {$employee->name}");

        app(NotificationService::class)->notifyStaffRequestCreated($staffRequest);

        return back()->with('success', 'Đã gửi yêu cầu, vui lòng chờ phê duyệt!');
    }

    public function approve(Request $request, StaffRequest $staffRequest)
    {
        $outcome = $request->input('outcome', 'actual');

        DB::transaction(function () use (&$staffRequest, $outcome) {
            $staffRequest = StaffRequest::lockForUpdate()->findOrFail($staffRequest->id);
            abort_if($staffRequest->status !== 'pending', 403, 'Yêu cầu không ở trạng thái chờ duyệt.');

            if ($staffRequest->type === 'attendance_correction') {
                $this->applyAttendanceCorrection($staffRequest);
            }

            if ($staffRequest->type === 'late_early' && $outcome === 'normal') {
                $this->applyLateEarlyForgiveness($staffRequest);
            }

            $staffRequest->update([
                'status'           => 'approved',
                'approval_outcome' => $staffRequest->type === 'late_early' ? $outcome : null,
                'reviewed_by'      => auth()->id(),
                'reviewed_at'      => now(),
            ]);
        });

        $staffRequest->loadMissing('employee');
        activity()->causedBy(auth()->user())
            ->performedOn($staffRequest)
            ->inLog('staff_request')
            ->withProperties(['code' => $staffRequest->code, 'type' => $staffRequest->type, 'employee_name' => $staffRequest->employee?->name])
            ->log("Duyệt yêu cầu {$staffRequest->typeLabel()} {$staffRequest->code}");

        app(NotificationService::class)->notifyStaffRequestApproved($staffRequest);

        return back()->with('success', 'Đã duyệt yêu cầu!');
    }

    public function reject(Request $request, StaffRequest $staffRequest)
    {
        abort_if($staffRequest->status !== 'pending', 403, 'Yêu cầu không ở trạng thái chờ duyệt.');

        $request->validate(['rejection_reason' => 'required|string|max:500']);

        $staffRequest->update([
            'status'           => 'rejected',
            'reviewed_by'      => auth()->id(),
            'reviewed_at'      => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        $staffRequest->loadMissing('employee');
        activity()->causedBy(auth()->user())
            ->performedOn($staffRequest)
            ->inLog('staff_request')
            ->withProperties(['code' => $staffRequest->code, 'reason' => $request->rejection_reason])
            ->log("Từ chối yêu cầu {$staffRequest->typeLabel()} {$staffRequest->code}");

        app(NotificationService::class)->notifyStaffRequestRejected($staffRequest, $request->rejection_reason);

        return back()->with('success', 'Đã từ chối yêu cầu.');
    }

    public function destroy(StaffRequest $staffRequest)
    {
        abort_unless($staffRequest->employee?->user_id === auth()->id(), 403, 'Bạn chỉ có thể huỷ yêu cầu của chính mình.');
        abort_if($staffRequest->status !== 'pending', 403, 'Chỉ có thể huỷ yêu cầu đang chờ duyệt.');

        $staffRequest->delete();

        return back()->with('success', 'Đã huỷ yêu cầu.');
    }

    /**
     * Bổ sung/sửa lại AttendanceLog theo giờ vào/ra được duyệt trong yêu cầu "Lượt chấm công".
     * Nếu ngày đó nhân viên có ca xếp sẵn, tính lại trễ/sớm theo đúng giờ ca (grace_late/early_minutes);
     * không có ca (chấm công ngoài lịch) thì không tính trễ/sớm.
     */
    private function applyAttendanceCorrection(StaffRequest $staffRequest): void
    {
        $employee = Employee::findOrFail($staffRequest->employee_id);
        $workDate = $staffRequest->work_date->toDateString();
        $payload  = $staffRequest->payload ?? [];

        $schedule = ShiftSchedule::with('shift')
            ->where('employee_id', $employee->id)
            ->where('work_date', $workDate)
            ->where('status', 'scheduled')
            ->first();

        $log = AttendanceLog::where('employee_id', $employee->id)
            ->where('work_date', $workDate)
            ->when($schedule, fn($q) => $q->where('shift_schedule_id', $schedule->id), fn($q) => $q->whereNull('shift_schedule_id'))
            ->lockForUpdate()
            ->first();

        $data = [
            'employee_id'       => $employee->id,
            'shift_schedule_id' => $schedule?->id,
            'work_date'         => $workDate,
        ];

        if (!empty($payload['check_in_at'])) {
            $checkIn                    = Carbon::parse($workDate . ' ' . $payload['check_in_at']);
            $data['check_in_at']        = $checkIn;
            $data['check_in_method']    = 'manual';
            $data['late_minutes']       = $schedule?->shift ? $this->computeLateMinutesAt($checkIn, $schedule->shift) : 0;
        }

        if (!empty($payload['check_out_at'])) {
            $checkOut                   = Carbon::parse($workDate . ' ' . $payload['check_out_at']);
            $data['check_out_at']       = $checkOut;
            $data['check_out_method']   = 'manual';
            $data['early_minutes']      = $schedule?->shift ? $this->computeEarlyMinutesAt($checkOut, $schedule->shift) : 0;
        }

        if ($log) {
            $log->update($data);
        } else {
            AttendanceLog::create($data);
        }
    }

    /**
     * Duyệt yêu cầu "Đi muộn về sớm" với kết quả "Công thường" — tha lỗi late/early minutes và
     * đánh dấu full_credit để Bảng chấm công tính đủ công cho ngày đó, dù giờ chấm thực tế ngắn hơn.
     * Không tạo mới AttendanceLog nếu chưa có (nhân viên phải đã chấm công thì mới có gì để tha lỗi).
     */
    private function applyLateEarlyForgiveness(StaffRequest $staffRequest): void
    {
        $payload = $staffRequest->payload ?? [];
        $workDate = $staffRequest->work_date->toDateString();

        $log = AttendanceLog::where('employee_id', $staffRequest->employee_id)
            ->where('work_date', $workDate)
            ->lockForUpdate()
            ->first();

        if (!$log) {
            return;
        }

        $data = ['full_credit' => true];

        if (($payload['mode'] ?? null) === 'early') {
            $data['early_minutes'] = 0;
        } else {
            $data['late_minutes'] = 0;
        }

        $log->update($data);
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
