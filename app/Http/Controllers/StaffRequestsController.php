<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffRequestRequest;
use App\Http\Requests\UpdateStaffRequestRequest;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use App\Models\StaffRequest;
use App\Models\Team;
use App\Services\AnnualLeaveService;
use App\Services\NotificationService;
use App\Support\Concerns\PreventsDuplicateSubmission;
use App\Support\Concerns\ResolvesOvernightCheckOutDate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
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
    use PreventsDuplicateSubmission;
    use ResolvesOvernightCheckOutDate;

    private const TYPE_LABELS = [
        'attendance_correction' => 'Lượt chấm công',
        'business_trip'         => 'Công tác/Ra ngoài',
        'late_early'            => 'Đi muộn về sớm',
        'leave'                 => 'Nghỉ phép',
        'time_change'           => 'Thay đổi giờ vào/ra',
        'overtime'              => 'Tăng ca',
        'shift_swap'            => 'Đổi ca làm',
    ];

    /**
     * Các cột AttendanceLog bị các luồng duyệt (attendance_correction/time_change/late_early/
     * overtime) ghi đè — chụp lại đúng nhóm cột này vào reversal_data để đảo ngược khi xoá đơn.
     */
    private const REVERSIBLE_LOG_COLUMNS = [
        'shift_schedule_id', 'shift_start_time', 'shift_end_time', 'shift_break_minutes',
        'shift_is_overnight', 'shift_type', 'shift_standard_work_hours',
        'check_in_at', 'check_out_at', 'check_in_method', 'check_out_method',
        'late_minutes', 'early_minutes', 'full_credit', 'overtime_hours',
    ];

    /** Loại yêu cầu có tác động lên AttendanceLog (cần đảo ngược khi xoá đơn đã duyệt). */
    private const ATTENDANCE_AFFECTING_TYPES = ['attendance_correction', 'time_change', 'late_early', 'overtime'];

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
        // Lọc theo tháng tạo đơn (YYYY-MM) — dùng cho bộ lọc "Tháng" trên giao diện mobile.
        $monthFilter    = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->input('month')) ? $request->input('month') : null;

        $applyEmployeeScope = function ($q) use ($branchFilter, $teamFilter) {
            $branchFilter && $q->where('branch_id', $branchFilter);
            $teamFilter && $q->where('team_id', $teamFilter);
        };

        // Luôn tải đủ cả 6 loại (bỏ qua $typeFilter ở bước này) để đếm số lượng theo từng loại
        // cho đúng bộ lọc nhân viên/chi nhánh/đội nhóm/trạng thái hiện tại — lọc theo loại áp
        // dụng sau khi đã đếm xong, phía dưới.
        $rows = collect();

        $leaveQuery = LeaveRequest::with(['employee.branch', 'employee.team', 'employee.user', 'reviewer']);
        $employeeFilter ? $leaveQuery->where('employee_id', $employeeFilter) : (!$isApprover && $leaveQuery->where('employee_id', $ownEmployeeId));
        ($branchFilter || $teamFilter) && $leaveQuery->whereHas('employee', $applyEmployeeScope);

        $swapQuery = ShiftSwapRequest::with(['requesterEmployee.branch', 'requesterEmployee.user', 'targetEmployee', 'requesterSchedule.shift', 'targetSchedule.shift', 'reviewer']);
        if ($employeeFilter) {
            $swapQuery->where(fn($q) => $q->where('requester_employee_id', $employeeFilter)->orWhere('target_employee_id', $employeeFilter));
        } elseif (!$isApprover) {
            $swapQuery->where(fn($q) => $q->where('requester_employee_id', $ownEmployeeId)->orWhere('target_employee_id', $ownEmployeeId));
        }
        if ($branchFilter || $teamFilter) {
            $swapQuery->where(fn($q) => $q->whereHas('requesterEmployee', $applyEmployeeScope)->orWhereHas('targetEmployee', $applyEmployeeScope));
        }

        $staffQuery = StaffRequest::with(['employee.branch', 'employee.team', 'employee.user', 'reviewer']);
        $employeeFilter ? $staffQuery->where('employee_id', $employeeFilter) : (!$isApprover && $staffQuery->where('employee_id', $ownEmployeeId));
        ($branchFilter || $teamFilter) && $staffQuery->whereHas('employee', $applyEmployeeScope);

        if ($monthFilter) {
            $monthStart = Carbon::createFromFormat('Y-m-d', $monthFilter . '-01')->startOfDay();
            $monthEnd   = $monthStart->copy()->endOfMonth();
            foreach ([$leaveQuery, $swapQuery, $staffQuery] as $query) {
                $query->whereBetween('created_at', [$monthStart, $monthEnd]);
            }
        }

        // Số yêu cầu theo trạng thái cho tab lọc — cùng phạm vi + loại đang chọn, chưa lọc trạng thái.
        // Dùng COUNT … GROUP BY trên từng nguồn thay vì tải toàn bộ lịch sử vào bộ nhớ.
        $statusCounts = [];
        $addStatusCounts = function ($query) use (&$statusCounts) {
            (clone $query)->reorder()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->each(function ($count, $status) use (&$statusCounts) {
                    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + (int) $count;
                });
        };
        if (!$typeFilter || $typeFilter === 'leave') {
            $addStatusCounts($leaveQuery);
        }
        if (!$typeFilter || $typeFilter === 'shift_swap') {
            $addStatusCounts($swapQuery);
        }
        if (!$typeFilter || !in_array($typeFilter, ['leave', 'shift_swap'], true)) {
            $addStatusCounts($typeFilter ? (clone $staffQuery)->where('type', $typeFilter) : $staffQuery);
        }
        $statusCounts = collect($statusCounts);

        foreach ([$leaveQuery, $swapQuery, $staffQuery] as $query) {
            $statusFilter && $query->where('status', $statusFilter);
        }
        $rows = $rows
            ->merge($leaveQuery->get()->map(fn($lr) => $this->normalizeLeave($lr)))
            ->merge($swapQuery->get()->map(fn($swap) => $this->normalizeSwap($swap)))
            ->merge($staffQuery->get()->map(fn($sr) => $this->normalizeStaff($sr)));

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

        // Số ngày phép năm còn lại theo từng nhân viên đủ điều kiện (chính thức + văn phòng),
        // JS hiển thị dưới ô "Loại nghỉ phép" trong form Nghỉ phép của hub khi chọn "annual".
        $annualLeaveService = app(AnnualLeaveService::class);
        $balanceScope = $isApprover ? $allEmployees : Employee::where('id', $ownEmployeeId)->get();
        $annualLeaveBalances = $balanceScope
            ->filter(fn(Employee $e) => $e->isEligibleForAnnualLeave())
            ->mapWithKeys(fn(Employee $e) => [$e->id => $annualLeaveService->remainingDays($e)]);

        // employee_id => is_office — JS dùng để chỉ hiện lựa chọn "Nghỉ theo giờ (nửa ngày)" cho
        // đúng khối văn phòng (nhà hàng/bếp/bar làm trọn ca, không có khái niệm nửa ca). Ca đã xếp
        // cho ô chọn "Ca làm" lấy qua AJAX (LeaveRequestsController::shiftsForRange) mỗi khi đổi
        // ngày/nhân viên, không còn tải sẵn toàn bộ ca của cửa sổ 14 ngày trước - 90 ngày sau.
        $officeFlags = $balanceScope->mapWithKeys(fn(Employee $e) => [$e->id => (bool) $e->is_office]);

        return view('staff-requests.index', compact('requests', 'statusCounts', 'employees', 'allEmployees', 'branches', 'teams', 'isApprover', 'canApproveStaff', 'canApproveLeave', 'canApproveSwap', 'typeCounts', 'annualLeaveBalances', 'officeFlags'));
    }

    public function details(string $type, int $id)
    {
        $user = auth()->user();
        $canApproveStaff = $user->can('approve-staff-requests');
        $canApproveLeave = $user->can('approve-leave-requests');
        $canApproveSwap  = $user->can('approve-shift-swaps');
        $isApprover      = $canApproveStaff || $canApproveLeave || $canApproveSwap;
        $ownEmployeeId  = $user->employee?->id ?? 0;

        if ($type === 'leave') {
            $lr = LeaveRequest::with(['employee.branch', 'employee.team', 'employee.user', 'reviewer', 'shiftSchedules.shift', 'shiftSchedule.shift'])->findOrFail($id);
            if (!$isApprover && $lr->employee_id !== $ownEmployeeId) {
                abort(403);
            }
            $data = $this->normalizeLeave($lr);
        } elseif ($type === 'shift_swap') {
            $swap = ShiftSwapRequest::with(['requesterEmployee.branch', 'requesterEmployee.user', 'targetEmployee', 'requesterSchedule.shift', 'targetSchedule.shift', 'reviewer'])->findOrFail($id);
            if (!$isApprover && $swap->requester_employee_id !== $ownEmployeeId && $swap->target_employee_id !== $ownEmployeeId) {
                abort(403);
            }
            $data = $this->normalizeSwap($swap);
        } else {
            $sr = StaffRequest::with(['employee.branch', 'employee.team', 'employee.user', 'reviewer'])->findOrFail($id);
            if (!$isApprover && $sr->employee_id !== $ownEmployeeId) {
                abort(403);
            }
            $data = $this->normalizeStaff($sr);
        }

        $avatarPath = ($data['employee'] ?? null)?->user?->avatar;
        $data['employee_avatar_url'] = $avatarPath ? asset($avatarPath) : null;

        return response()->json($data);
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
            'summary'            => $lr->typeLabel()
                . ($lr->is_partial_day ? ' · ' . $lr->partialDayLabel() : '')
                . ($lr->reason ? ' · ' . Str::limit($lr->reason, 60) : ''),
            'details'            => array_values(array_filter([
                ['icon' => 'bi-calendar3', 'label' => 'Thời gian', 'value' => $lr->date_from->format('d/m/Y') . ' – ' . $lr->date_to->format('d/m/Y') . ' (' . $lr->daysCount() . ' ngày)'],
                ['icon' => 'bi-file-earmark-text', 'label' => 'Loại đơn', 'value' => $lr->typeLabel() . ($lr->is_partial_day ? ' · ' . $lr->partialDayLabel() : '')],
                $lr->reason ? ['icon' => 'bi-chat-left-text', 'label' => 'Lý do', 'value' => Str::limit($lr->reason, 80)] : null,
            ])),
            'leave_shifts'       => $lr->selectedShifts(),
            'status'             => $lr->status,
            'status_label'       => $lr->statusLabel(),
            'status_badge'       => $lr->statusBadgeClass(),
            'rejection_reason'   => $lr->rejection_reason,
            'reviewer'           => $lr->reviewer,
            'created_at'         => $lr->created_at,
            'approve_route'      => $lr->status === 'pending' ? route('leave-requests.approve', $lr) : null,
            'reject_route'       => $lr->status === 'pending' ? route('leave-requests.reject', $lr) : null,
            'destroy_route'      => $this->hubCanDelete($lr->status, $lr->employee?->user_id === auth()->id(), auth()->user()->can('delete-leave-requests'))
                ? route('leave-requests.destroy', $lr) : null,
            'can_manage_own'     => $lr->status === 'pending' && $lr->employee?->user_id === auth()->id(),
            'can_purge'          => auth()->user()->can('delete-leave-requests'),
            'reversal_note'      => $lr->status === 'approved' ? $this->leaveReversalNote($lr) : null,
            'approve_permission' => 'approve-leave-requests',
        ];
    }

    /**
     * Ai được xoá 1 dòng theo trạng thái (đồng bộ với các destroy()): chờ duyệt → chính chủ hoặc
     * người có delete-*; đã duyệt → CHỈ admin (delete-approved-requests, kèm đảo ngược); từ chối →
     * người có delete-*.
     */
    private function hubCanDelete(string $status, bool $isOwner, bool $canPurge): bool
    {
        return match ($status) {
            'pending'  => $isOwner || $canPurge,
            'approved' => auth()->user()->can('delete-approved-requests'),
            default    => $canPurge,
        };
    }

    private function leaveReversalNote(LeaveRequest $lr): string
    {
        if ($lr->type === 'annual') {
            $days = rtrim(rtrim(number_format((float) $lr->daysCount(), 2, '.', ''), '0'), '.');

            return "Sẽ hoàn {$days} ngày phép năm cho " . ($lr->employee?->name ?? 'nhân viên') . ' và khôi phục lịch đã huỷ.';
        }

        return 'Sẽ khôi phục lịch làm việc đã huỷ (nghỉ không lương — không có phép để hoàn).';
    }

    private function staffReversalNote(StaffRequest $sr): string
    {
        // Không có snapshot (business_trip, "Đi muộn về sớm" duyệt kiểu trừ giờ thực tế, hoặc dữ
        // liệu cũ tạo trước tính năng này) → xoá chỉ gỡ phiếu, không đụng bảng chấm công.
        if (empty($sr->reversal_data['action'])) {
            return 'Không ảnh hưởng bảng chấm công.';
        }

        return match ($sr->type) {
            'overtime'   => 'Sẽ trừ số giờ tăng ca đã cộng vào bảng chấm công.',
            'late_early' => 'Sẽ khôi phục lỗi đi muộn/về sớm đã được tha (tính lại trễ/sớm).',
            'attendance_correction', 'time_change' => 'Sẽ hoàn tác chỉnh sửa lượt chấm công (khôi phục giá trị cũ, hoặc xoá bản ghi do phiếu tạo).',
            default      => 'Không ảnh hưởng bảng chấm công.',
        };
    }

    /** "Ca Sáng (08:00 – 17:00)" cho 1 lịch xếp ca — dùng ở thẻ đơn đổi ca. */
    private function scheduleShiftLabel(?ShiftSchedule $schedule): string
    {
        $shift = $schedule?->effectiveShift();
        if (!$shift) {
            return '—';
        }
        $name = $shift->name ?? ($schedule->isFlexible() ? 'Ca linh hoạt' : 'Ca làm việc');

        return $name . ' (' . substr((string) $shift->start_time, 0, 5) . ' – ' . substr((string) $shift->end_time, 0, 5) . ')';
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
            'details'            => array_values(array_filter([
                ['icon' => 'bi-calendar3', 'label' => 'Ngày áp dụng', 'value' => $swap->requesterSchedule?->work_date?->format('d/m/Y') ?? '—'],
                ['icon' => 'bi-cup-hot', 'label' => 'Từ ca', 'value' => $this->scheduleShiftLabel($swap->requesterSchedule)],
                ['icon' => 'bi-cup-hot', 'label' => 'Sang ca', 'value' => $this->scheduleShiftLabel($swap->targetSchedule)],
                ['icon' => 'bi-person', 'label' => 'Đổi với', 'value' => $swap->targetEmployee?->name ?? '—'],
                $swap->reason ? ['icon' => 'bi-chat-left-text', 'label' => 'Lý do', 'value' => Str::limit($swap->reason, 80)] : null,
            ])),
            'status'             => $swap->status,
            'status_label'       => $swap->statusLabel(),
            'status_badge'       => $swap->statusBadgeClass(),
            'rejection_reason'   => $swap->rejection_reason,
            'reviewer'           => $swap->reviewer,
            'created_at'         => $swap->created_at,
            'approve_route'      => $swap->status === 'pending' ? route('shift-swap-requests.approve', $swap) : null,
            'reject_route'       => $swap->status === 'pending' ? route('shift-swap-requests.reject', $swap) : null,
            'destroy_route'      => $this->hubCanDelete($swap->status, $swap->requesterEmployee?->user_id === auth()->id(), auth()->user()->can('delete-shift-swaps'))
                ? route('shift-swap-requests.destroy', $swap) : null,
            'can_manage_own'     => $swap->status === 'pending' && $swap->requesterEmployee?->user_id === auth()->id(),
            'can_purge'          => auth()->user()->can('delete-shift-swaps'),
            'reversal_note'      => $swap->status === 'approved'
                ? 'Sẽ hoán lịch làm việc trở lại giữa ' . ($swap->requesterEmployee?->name ?? '—') . ' và ' . ($swap->targetEmployee?->name ?? '—') . '.'
                : null,
            'approve_permission' => 'approve-shift-swaps',
        ];
    }

    private function normalizeStaff(StaffRequest $sr): array
    {
        $isOwner    = $sr->employee?->user_id === auth()->id();
        $canEditAny = auth()->user()->can('edit-staff-requests');
        $canEdit    = ($isOwner && $sr->status === 'pending') || $canEditAny;

        return [
            'source'             => 'staff_request',
            'id'                 => $sr->id,
            'code'               => $sr->code,
            'type_key'           => $sr->type,
            'type_label'         => $sr->typeLabel(),
            'employee'           => $sr->employee,
            'work_date_label'    => $sr->work_date->format('d/m/Y'),
            'summary'            => $sr->summary() . ($sr->reason ? ' · ' . Str::limit($sr->reason, 60) : ''),
            'details'            => array_values(array_filter([
                ['icon' => 'bi-calendar3', 'label' => 'Ngày áp dụng', 'value' => $sr->work_date->format('d/m/Y')],
                $sr->summary() !== '—' ? ['icon' => 'bi-clock', 'label' => 'Chi tiết', 'value' => $sr->summary()] : null,
                $sr->reason ? ['icon' => 'bi-chat-left-text', 'label' => 'Lý do', 'value' => Str::limit($sr->reason, 80)] : null,
            ])),
            'status'             => $sr->status,
            'status_label'       => $sr->statusLabel(),
            'status_badge'       => $sr->statusBadgeClass(),
            'correction_outcome_label' => $sr->correctionOutcomeLabel(),
            'rejection_reason'   => $sr->rejection_reason,
            'reviewer'           => $sr->reviewer,
            'created_at'         => $sr->created_at,
            'approve_route'      => $sr->status === 'pending' ? route('staff-requests.approve', $sr) : null,
            'reject_route'       => $sr->status === 'pending' ? route('staff-requests.reject', $sr) : null,
            'destroy_route'      => $this->hubCanDelete($sr->status, $isOwner, auth()->user()->can('delete-staff-requests'))
                ? route('staff-requests.destroy', $sr) : null,
            'can_manage_own'     => $sr->status === 'pending' && $isOwner,
            'can_purge'          => auth()->user()->can('delete-staff-requests'),
            'reversal_note'      => $sr->status === 'approved' ? $this->staffReversalNote($sr) : null,
            'approve_permission' => 'approve-staff-requests',
            // Dữ liệu gọn để JS đổ vào modal Sửa — chỉ truyền khi được phép sửa, tránh lộ payload
            // của những phiếu không liên quan ra HTML không cần thiết.
            'edit_data'          => $canEdit ? [
                'id'          => $sr->id,
                'code'        => $sr->code,
                'type_key'    => $sr->type,
                'type_label'  => $sr->typeLabel(),
                'action'      => route('staff-requests.update', $sr),
                'work_date'   => $sr->work_date->toDateString(),
                'reason'      => $sr->reason,
                'employee_id' => $sr->employee_id,
                'status'      => $sr->status,
                'payload'     => $sr->payload ?? [],
            ] : null,
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

        // Chặn double-submit (double-click / gửi lại khi mạng chậm) tạo trùng 2 yêu cầu giống hệt.
        if ($this->wasJustSubmitted(StaffRequest::class, [
            'employee_id' => $employee->id, 'type' => $type,
            'work_date' => $validated['work_date'], 'reason' => $validated['reason'],
        ])) {
            return back()->with('success', 'Đã gửi yêu cầu, vui lòng chờ phê duyệt!');
        }

        // Ngày đa ca: nhân viên chọn đúng ca cần áp dụng ở form (bắt buộc nếu ngày đó có ≥2 ca —
        // xem StoreStaffRequestRequest::SHIFT_AWARE_TYPES) — lưu vào payload để dùng lại lúc duyệt
        // (applyAttendanceCorrection/applyLateEarlyForgiveness/applyTimeChange), tránh áp nhầm ca.
        $shiftScheduleId = $validated['shift_schedule_id'] ?? null;

        $payload = match ($type) {
            'attendance_correction' => array_filter([
                'check_in_at'       => $validated['check_in_at'] ?? null,
                'check_out_at'      => $validated['check_out_at'] ?? null,
                'shift_schedule_id' => $shiftScheduleId,
            ]),
            'business_trip' => [
                'from_time' => $validated['from_time'],
                'to_time'   => $validated['to_time'],
                'location'  => $validated['location'],
            ],
            'late_early' => array_filter([
                'mode'              => $validated['mode'],
                'minutes'           => (int) $validated['minutes'],
                'shift_schedule_id' => $shiftScheduleId,
            ]),
            'time_change' => array_filter([
                'new_check_in'      => $validated['new_check_in'],
                'new_check_out'     => $validated['new_check_out'],
                'shift_schedule_id' => $shiftScheduleId,
            ]),
            'overtime' => [
                'from_time' => $validated['ot_from_time'],
                'to_time'   => $validated['ot_to_time'],
            ],
        };

        $prefixes = [
            'attendance_correction' => 'ATC',
            'business_trip'         => 'BTR',
            'late_early'            => 'LE',
            'time_change'           => 'TC',
            'overtime'              => 'OT',
        ];

        // withTrashed() bắt buộc: nếu chỉ đếm bản ghi còn sống, xoá 1 phiếu ở giữa tháng sẽ làm
        // số đếm bị lùi lại, sinh trùng "code" với phiếu chưa xoá (code có unique constraint).
        $count = StaffRequest::withTrashed()
            ->where('type', $type)
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

    /**
     * Sửa nội dung 1 phiếu thuộc 4 loại dùng chung bảng staff_requests. Loại phiếu (type) và nhân
     * viên (employee_id) KHÔNG đổi được — chỉ sửa ngày/nội dung/lý do. Cho phép:
     * - Chính chủ (employee.user_id = user hiện tại) khi phiếu đang "Chờ duyệt".
     * - Người có quyền `edit-staff-requests` (admin/manager/director) sửa bất kỳ phiếu nào, kể cả
     *   đã duyệt/từ chối — dùng để sửa sai sót trên phiếu, KHÔNG tự động áp dụng lại vào
     *   AttendanceLog đã ghi nhận lúc duyệt (xem cảnh báo trả về khi phiếu không còn "Chờ duyệt").
     */
    public function update(UpdateStaffRequestRequest $request, StaffRequest $staffRequest)
    {
        $isOwner    = $staffRequest->employee?->user_id === auth()->id();
        $canEditAny = auth()->user()->can('edit-staff-requests');

        abort_unless($isOwner || $canEditAny, 403, 'Bạn không có quyền sửa yêu cầu này.');
        abort_if(!$canEditAny && $staffRequest->status !== 'pending', 403, 'Chỉ có thể sửa yêu cầu đang chờ duyệt.');

        $validated       = $request->validated();
        $type            = $staffRequest->type;
        $shiftScheduleId = $validated['shift_schedule_id'] ?? null;

        $payload = match ($type) {
            'attendance_correction' => array_filter([
                'check_in_at'       => $validated['check_in_at'] ?? null,
                'check_out_at'      => $validated['check_out_at'] ?? null,
                'shift_schedule_id' => $shiftScheduleId,
            ]),
            'business_trip' => [
                'from_time' => $validated['from_time'],
                'to_time'   => $validated['to_time'],
                'location'  => $validated['location'],
            ],
            'late_early' => array_filter([
                'mode'              => $validated['mode'],
                'minutes'           => (int) $validated['minutes'],
                'shift_schedule_id' => $shiftScheduleId,
            ]),
            'time_change' => array_filter([
                'new_check_in'      => $validated['new_check_in'],
                'new_check_out'     => $validated['new_check_out'],
                'shift_schedule_id' => $shiftScheduleId,
            ]),
            'overtime' => [
                'from_time' => $validated['ot_from_time'],
                'to_time'   => $validated['ot_to_time'],
            ],
        };

        $wasProcessed = $staffRequest->status !== 'pending';

        $staffRequest->update([
            'work_date' => $validated['work_date'],
            'payload'   => $payload,
            'reason'    => $validated['reason'],
        ]);

        $staffRequest->loadMissing('employee');
        activity()->causedBy(auth()->user())
            ->performedOn($staffRequest)
            ->inLog('staff_request')
            ->withProperties(['code' => $staffRequest->code, 'type' => $type, 'employee_name' => $staffRequest->employee?->name, 'was_processed' => $wasProcessed])
            ->log("Sửa yêu cầu {$staffRequest->typeLabel()} {$staffRequest->code}");

        return back()->with('success', $wasProcessed
            ? 'Đã cập nhật nội dung phiếu! Lưu ý: phiếu đã được xử lý — thay đổi này KHÔNG tự động áp dụng lại vào bảng chấm công.'
            : 'Đã cập nhật yêu cầu!');
    }

    public function approve(Request $request, StaffRequest $staffRequest)
    {
        $outcome = $request->input('outcome', 'actual');

        DB::transaction(function () use (&$staffRequest, $outcome) {
            $staffRequest = StaffRequest::lockForUpdate()->findOrFail($staffRequest->id);
            abort_if($staffRequest->status !== 'pending', 403, 'Yêu cầu không ở trạng thái chờ duyệt.');

            // reversal_data: ảnh chụp AttendanceLog TRƯỚC khi ghi đè, để xoá đơn đã duyệt có thể đảo
            // ngược chính xác (khôi phục giá trị cũ, hoặc xoá log nếu chính đơn này tạo mới log).
            $reversal = null;

            if ($staffRequest->type === 'attendance_correction') {
                $reversal = $this->applyAttendanceCorrection($staffRequest);
            }

            if ($staffRequest->type === 'late_early' && $outcome === 'normal') {
                $reversal = $this->applyLateEarlyForgiveness($staffRequest);
            }

            if ($staffRequest->type === 'overtime') {
                $reversal = $this->applyOvertime($staffRequest);
            }

            if ($staffRequest->type === 'time_change') {
                $reversal = $this->applyTimeChange($staffRequest);
            }

            $staffRequest->update([
                'status'           => 'approved',
                'approval_outcome' => $staffRequest->type === 'late_early' ? $outcome : null,
                'reversal_data'    => $reversal,
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

    /**
     * Quy tắc xoá theo trạng thái:
     * - Chờ duyệt: chính chủ tự huỷ, HOẶC người có delete-staff-requests xoá.
     * - Từ chối: người có delete-staff-requests xoá (không có tác động để đảo ngược).
     * - Đã duyệt: CHỈ admin (delete-approved-requests) — xoá kèm ĐẢO NGƯỢC tác động lúc duyệt
     *   (khôi phục/xoá bản ghi chấm công, trừ giờ tăng ca, phục hồi lỗi trễ/sớm...). Chặn nếu có
     *   yêu cầu duyệt SAU cùng chạm vào lượt chấm công đó (tránh ghi đè nhầm số liệu mới hơn).
     */
    public function destroy(StaffRequest $staffRequest)
    {
        $isOwner    = $staffRequest->employee?->user_id === auth()->id();
        $canPurge   = auth()->user()->can('delete-staff-requests');
        $canReverse = auth()->user()->can('delete-approved-requests');
        $status     = $staffRequest->status;

        if ($status === 'approved') {
            abort_unless($canReverse, 403, 'Chỉ admin được xoá yêu cầu đã duyệt (thao tác sẽ đảo ngược tác động lúc duyệt).');
            $this->assertReversibleOrAbort($staffRequest);
        } elseif ($status === 'pending') {
            abort_unless($isOwner || $canPurge, 403, 'Bạn không có quyền xoá yêu cầu này.');
        } else { // rejected
            abort_unless($canPurge, 403, 'Bạn không có quyền xoá yêu cầu này.');
        }

        $staffRequest->loadMissing('employee');

        DB::transaction(function () use ($staffRequest, $status) {
            $locked = StaffRequest::lockForUpdate()->findOrFail($staffRequest->id);

            if ($status === 'approved') {
                $this->reverseStaffRequestSideEffects($locked);
            }

            $locked->delete();
        });

        $verb = $status === 'pending' && $isOwner && !$canPurge ? 'huỷ' : 'xoá';
        activity()->causedBy(auth()->user())
            ->performedOn($staffRequest)
            ->inLog('staff_request')
            ->withProperties(['code' => $staffRequest->code, 'status_before' => $status, 'reversed' => $status === 'approved'])
            ->log(ucfirst($verb) . " yêu cầu {$staffRequest->typeLabel()} {$staffRequest->code}"
                . ($status === 'approved' ? ' (đã đảo ngược tác động)' : ''));

        return back()->with('success', 'Đã ' . $verb . ' yêu cầu'
            . ($status === 'approved' ? ' và đảo ngược tác động lúc duyệt.' : '.'));
    }

    /**
     * Chặn xoá đơn đã duyệt (kèm cảnh báo) nếu tác động của nó KHÔNG còn đảo ngược an toàn được —
     * cụ thể khi có 1 yêu cầu đã duyệt MỚI HƠN cùng ghi vào đúng lượt chấm công (log_id) đó: khôi
     * phục ảnh chụp cũ sẽ ghi đè nhầm số liệu mới hơn. Người dùng phải xử lý yêu cầu mới hơn trước.
     */
    private function assertReversibleOrAbort(StaffRequest $staffRequest): void
    {
        $reversal = $staffRequest->reversal_data;
        $logId = $reversal['log_id'] ?? null;

        if (!$logId) {
            return; // business_trip hoặc dữ liệu cũ chưa có snapshot — không có gì để đụng độ.
        }

        $conflict = StaffRequest::where('id', '!=', $staffRequest->id)
            ->where('status', 'approved')
            ->where('employee_id', $staffRequest->employee_id)
            ->where('work_date', $staffRequest->work_date->toDateString())
            ->whereIn('type', self::ATTENDANCE_AFFECTING_TYPES)
            ->get()
            ->contains(fn (StaffRequest $r) => ($r->reversal_data['log_id'] ?? null) === $logId && $r->id > $staffRequest->id);

        abort_if($conflict, 422, 'Không thể xoá: đã có yêu cầu được duyệt SAU cùng tác động lên lượt chấm công này. '
            . 'Vui lòng xoá/ xử lý yêu cầu mới hơn trước, rồi mới xoá yêu cầu này.');
    }

    /**
     * Đảo ngược tác động của 1 yêu cầu đã duyệt lên AttendanceLog theo reversal_data đã chụp lúc
     * duyệt: 'delete_log' (đơn này tạo mới log → xoá), 'restore_log' (đơn này ghi đè log cũ → khôi
     * phục lại nhóm cột đã đổi). Null (business_trip / dữ liệu cũ) → không làm gì.
     */
    private function reverseStaffRequestSideEffects(StaffRequest $staffRequest): void
    {
        $reversal = $staffRequest->reversal_data;
        if (!$reversal || empty($reversal['action']) || empty($reversal['log_id'])) {
            return;
        }

        if ($reversal['action'] === 'delete_log') {
            AttendanceLog::where('id', $reversal['log_id'])->lockForUpdate()->delete();

            return;
        }

        if ($reversal['action'] === 'restore_log') {
            $log = AttendanceLog::lockForUpdate()->find($reversal['log_id']);
            if ($log) {
                $log->update($reversal['before'] ?? []);
            }
        }
    }

    /**
     * Bổ sung/sửa lại AttendanceLog theo giờ vào/ra được duyệt trong yêu cầu "Lượt chấm công".
     * Nếu ngày đó nhân viên có ca xếp sẵn, tính lại trễ/sớm theo đúng giờ ca (grace_late/early_minutes);
     * không có ca (chấm công ngoài lịch) thì không tính trễ/sớm. Giờ ra dùng
     * ResolvesOvernightCheckOutDate::resolveCheckOutDate() để tự chuyển sang ngày hôm sau nếu ca
     * qua đêm và giờ ra nhập vào rơi vào rạng sáng (VD ca 18h-24h, "00:10" phải hiểu là hôm sau).
     */
    private function applyAttendanceCorrection(StaffRequest $staffRequest): array
    {
        $employee = Employee::findOrFail($staffRequest->employee_id);
        $workDate = $staffRequest->work_date->toDateString();
        $payload  = $staffRequest->payload ?? [];

        // Nhân viên đa ca cùng ngày: dùng đúng ca đã chọn lúc gửi yêu cầu (payload.shift_schedule_id,
        // bắt buộc nhập khi ngày đó có ≥2 ca — xem StoreStaffRequestRequest). Rơi về ->first() như cũ
        // chỉ khi không có lựa chọn (ngày chỉ có 1 ca, hoặc yêu cầu tạo trước khi có field này).
        $schedule = !empty($payload['shift_schedule_id'])
            ? ShiftSchedule::with('shift')->where('employee_id', $employee->id)->find($payload['shift_schedule_id'])
            : null;

        $schedule ??= ShiftSchedule::with('shift')
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
            ...($schedule?->shiftSnapshotAttributes() ?? []),
        ];

        if (!empty($payload['check_in_at'])) {
            $checkIn                    = Carbon::parse($workDate . ' ' . $payload['check_in_at']);
            $data['check_in_at']        = $checkIn;
            $data['check_in_method']    = 'manual';
            $data['late_minutes']       = $schedule?->shift ? $this->computeLateMinutesAt($checkIn, $schedule->shift) : 0;
        }

        if (!empty($payload['check_out_at'])) {
            $referenceCheckIn            = $data['check_in_at'] ?? $log?->check_in_at;
            $checkOutDate                = $this->resolveCheckOutDate($workDate, $payload['check_out_at'], $schedule?->shift, $referenceCheckIn?->format('H:i'));
            $checkOut                    = Carbon::parse($checkOutDate . ' ' . $payload['check_out_at']);
            $data['check_out_at']        = $checkOut;
            $data['check_out_method']    = 'manual';
            $data['early_minutes']       = $schedule?->shift ? $this->computeEarlyMinutesAt($checkOut, $schedule->shift) : 0;
        }

        return $this->persistLogMutation($log, $data);
    }

    /**
     * Ghi mutation vào AttendanceLog và trả về ảnh chụp để đảo ngược: nếu log đã tồn tại thì chụp
     * lại nhóm cột bị đổi (restore_log); nếu chưa có thì đây là bên tạo mới (delete_log khi đảo).
     */
    private function persistLogMutation(?AttendanceLog $log, array $data): array
    {
        if ($log) {
            $before = Arr::only($log->getOriginal(), self::REVERSIBLE_LOG_COLUMNS);
            $log->update($data);

            return ['action' => 'restore_log', 'log_id' => $log->id, 'before' => $before];
        }

        $created = AttendanceLog::create($data);

        return ['action' => 'delete_log', 'log_id' => $created->id];
    }

    /**
     * Duyệt yêu cầu "Đi muộn về sớm" với kết quả "Công thường" — tha lỗi late/early minutes và
     * đánh dấu full_credit để Bảng chấm công tính đủ công cho ngày đó, dù giờ chấm thực tế ngắn hơn.
     * Không tạo mới AttendanceLog nếu chưa có (nhân viên phải đã chấm công thì mới có gì để tha lỗi).
     *
     * Nhân viên xếp đa ca trong cùng 1 ngày (VD ca sáng + ca tối) có thể có nhiều AttendanceLog
     * cùng work_date — KHÔNG được lấy đại ->first() (sẽ tha lỗi/full_credit nhầm sang lượt chấm
     * công không liên quan, VD ca sáng đúng giờ, trong khi ca tối thực sự về sớm vẫn bị trừ công
     * như cũ — bug thực tế đã gặp). Ưu tiên đúng ca đã chọn trong payload.shift_schedule_id (bắt
     * buộc nhập khi ngày đó có ≥2 ca — xem StoreStaffRequestRequest); nếu không có (yêu cầu tạo
     * trước khi có field này) thì rơi về đúng lượt đang có late_minutes/early_minutes > 0 khớp với
     * loại yêu cầu (mode); chỉ rơi về lượt đầu tiên khi ngày đó chỉ có 1 lượt chấm công hoặc không
     * lượt nào khớp (dữ liệu cũ/hiếm).
     */
    private function applyLateEarlyForgiveness(StaffRequest $staffRequest): ?array
    {
        $payload = $staffRequest->payload ?? [];
        $workDate = $staffRequest->work_date->toDateString();
        $mode = ($payload['mode'] ?? null) === 'early' ? 'early' : 'late';
        $column = $mode === 'early' ? 'early_minutes' : 'late_minutes';

        $logs = AttendanceLog::where('employee_id', $staffRequest->employee_id)
            ->where('work_date', $workDate)
            ->lockForUpdate()
            ->get();

        if ($logs->isEmpty()) {
            return null;
        }

        $log = null;
        if (!empty($payload['shift_schedule_id'])) {
            $log = $logs->firstWhere('shift_schedule_id', $payload['shift_schedule_id']);
        }

        $log ??= $logs->count() === 1
            ? $logs->first()
            : ($logs->first(fn($l) => $l->$column > 0) ?? $logs->first());

        $before = Arr::only($log->getOriginal(), self::REVERSIBLE_LOG_COLUMNS);

        $data = ['full_credit' => true];

        if ($mode === 'early') {
            $data['early_minutes'] = 0;
        } else {
            $data['late_minutes'] = 0;
        }

        $log->update($data);

        return ['action' => 'restore_log', 'log_id' => $log->id, 'before' => $before];
    }

    /**
     * Duyệt yêu cầu "Tăng ca" — cộng thêm overtime_hours vào AttendanceLog của ngày đó (theo
     * ca đã xếp nếu có, để quy đổi công theo giờ công chuẩn của đúng ca). Nếu ngày đó nhân viên
     * không có ca/chưa chấm công (VD tăng ca vào ngày nghỉ), tự tạo mới 1 log chỉ chứa giờ tăng
     * ca — AttendanceLog::computeCong() vẫn tính ra công dù không có giờ vào/ra thực tế.
     * Cộng dồn (không ghi đè) để nhiều yêu cầu tăng ca duyệt cùng ngày không mất dữ liệu nhau.
     */
    private function applyOvertime(StaffRequest $staffRequest): array
    {
        $workDate      = $staffRequest->work_date->toDateString();
        $overtimeHours = $staffRequest->overtimeHours();

        $schedule = ShiftSchedule::where('employee_id', $staffRequest->employee_id)
            ->where('work_date', $workDate)
            ->where('status', 'scheduled')
            ->first();

        $log = AttendanceLog::where('employee_id', $staffRequest->employee_id)
            ->where('work_date', $workDate)
            ->when($schedule, fn($q) => $q->where('shift_schedule_id', $schedule->id), fn($q) => $q->whereNull('shift_schedule_id'))
            ->lockForUpdate()
            ->first();

        if ($log) {
            $before = Arr::only($log->getOriginal(), self::REVERSIBLE_LOG_COLUMNS);
            $log->update(['overtime_hours' => (float) $log->overtime_hours + $overtimeHours]);

            return ['action' => 'restore_log', 'log_id' => $log->id, 'before' => $before];
        }

        $created = AttendanceLog::create([
            'employee_id'       => $staffRequest->employee_id,
            'shift_schedule_id' => $schedule?->id,
            'work_date'         => $workDate,
            'overtime_hours'    => $overtimeHours,
            ...($schedule?->shiftSnapshotAttributes() ?? []),
        ]);

        return ['action' => 'delete_log', 'log_id' => $created->id];
    }

    /**
     * Bổ sung/sửa lại AttendanceLog theo giờ vào/ra mới được duyệt trong yêu cầu "Thay đổi giờ
     * vào/ra". Vẫn tính late_minutes/early_minutes theo đúng giờ mới so với ca (giống
     * applyAttendanceCorrection()) — yêu cầu này chỉ xác nhận "giờ vào/ra thực tế là X", không tự
     * động tha lỗi trễ/sớm; muốn tha lỗi (tính đủ công) phải duyệt riêng qua "Đi muộn về sớm" với
     * kết quả "Công thường" (full_credit — xem applyLateEarlyForgiveness()). new_check_in/
     * new_check_out luôn cùng ngày work_date (validation bắt buộc new_check_out sau new_check_in).
     */
    private function applyTimeChange(StaffRequest $staffRequest): array
    {
        $employee = Employee::findOrFail($staffRequest->employee_id);
        $workDate = $staffRequest->work_date->toDateString();
        $payload  = $staffRequest->payload ?? [];

        // Xem giải thích ở applyAttendanceCorrection() — cùng nguyên tắc ưu tiên ca đã chọn trong
        // payload cho ngày đa ca, tránh áp nhầm giờ mới sang ca không liên quan.
        $schedule = !empty($payload['shift_schedule_id'])
            ? ShiftSchedule::with('shift')->where('employee_id', $employee->id)->find($payload['shift_schedule_id'])
            : null;

        $schedule ??= ShiftSchedule::with('shift')
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
            ...($schedule?->shiftSnapshotAttributes() ?? []),
        ];

        if (!empty($payload['new_check_in'])) {
            $checkIn                 = Carbon::parse($workDate . ' ' . $payload['new_check_in']);
            $data['check_in_at']     = $checkIn;
            $data['check_in_method'] = 'manual';
            $data['late_minutes']    = $schedule?->shift ? $this->computeLateMinutesAt($checkIn, $schedule->shift) : 0;
        }

        if (!empty($payload['new_check_out'])) {
            $referenceCheckIn          = $data['check_in_at'] ?? $log?->check_in_at;
            $checkOutDate              = $this->resolveCheckOutDate($workDate, $payload['new_check_out'], $schedule?->shift, $referenceCheckIn?->format('H:i'));
            $checkOut                  = Carbon::parse($checkOutDate . ' ' . $payload['new_check_out']);
            $data['check_out_at']      = $checkOut;
            $data['check_out_method']  = 'manual';
            $data['early_minutes']     = $schedule?->shift ? $this->computeEarlyMinutesAt($checkOut, $schedule->shift) : 0;
        }

        return $this->persistLogMutation($log, $data);
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
