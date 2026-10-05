<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftSwapRequestsController extends Controller
{
    public function index(Request $request)
    {
        $isApprover = auth()->user()->can('approve-shift-swaps');

        $query = ShiftSwapRequest::with([
            'requesterEmployee', 'targetEmployee',
            'requesterSchedule.shift', 'targetSchedule.shift', 'reviewer',
        ])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at');

        if (!$isApprover) {
            $employeeId = auth()->user()->employee?->id;
            $query->where(function ($q) use ($employeeId) {
                $q->where('requester_employee_id', $employeeId ?? 0)
                  ->orWhere('target_employee_id', $employeeId ?? 0);
            });
        } elseif ($request->filled('employee_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('requester_employee_id', $request->employee_id)
                  ->orWhere('target_employee_id', $request->employee_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $swapRequests = $query->paginate(15)->withQueryString();
        $employees    = $isApprover ? Employee::where('is_active', true)->orderBy('name')->get() : collect();

        return view('shift-swap-requests.index', compact('swapRequests', 'employees', 'isApprover'));
    }

    public function store(Request $request)
    {
        $employee = auth()->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $validated = $request->validate([
            'requester_schedule_id' => 'required|exists:shift_schedules,id',
            'target_schedule_id'    => 'required|exists:shift_schedules,id',
            'reason'                => 'nullable|string|max:1000',
        ]);

        $reqSchedule = ShiftSchedule::findOrFail($validated['requester_schedule_id']);
        $tgtSchedule = ShiftSchedule::findOrFail($validated['target_schedule_id']);

        abort_if($reqSchedule->isFlexible() || $tgtSchedule->isFlexible(), 422, 'Không thể đề xuất đổi ca linh hoạt.');
        abort_unless($reqSchedule->employee_id === $employee->id, 403, 'Bạn chỉ có thể đề xuất đổi ca của chính mình.');

        $this->assertSwappable($reqSchedule, $tgtSchedule, $employee->id, $tgtSchedule->employee_id);

        $hasPendingConflict = ShiftSwapRequest::where('status', 'pending')
            ->where(function ($q) use ($reqSchedule, $tgtSchedule) {
                $q->whereIn('requester_schedule_id', [$reqSchedule->id, $tgtSchedule->id])
                  ->orWhereIn('target_schedule_id', [$reqSchedule->id, $tgtSchedule->id]);
            })->exists();
        abort_if($hasPendingConflict, 422, 'Một trong hai ca đang có yêu cầu đổi ca khác chờ xử lý.');

        // withTrashed() bắt buộc: nếu chỉ đếm bản ghi còn sống, xoá 1 yêu cầu ở giữa tháng sẽ làm
        // số đếm bị lùi lại, sinh trùng "code" với yêu cầu chưa xoá (code có unique constraint).
        $count = ShiftSwapRequest::withTrashed()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;
        $code = 'SWP-' . now()->format('Ym') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $swap = ShiftSwapRequest::create([
            'code'                   => $code,
            'requester_employee_id'  => $employee->id,
            'requester_schedule_id'  => $reqSchedule->id,
            'target_employee_id'     => $tgtSchedule->employee_id,
            'target_schedule_id'     => $tgtSchedule->id,
            'reason'                 => $validated['reason'] ?? null,
            'status'                 => 'pending',
        ]);

        activity()->causedBy(auth()->user())
            ->performedOn($swap)
            ->inLog('shift_swap_request')
            ->withProperties(['code' => $code, 'requester' => $employee->name])
            ->log("Gửi yêu cầu đổi ca {$code} — {$employee->name}");

        app(NotificationService::class)->notifyShiftSwapCreated($swap);

        return back()->with('success', 'Đã gửi yêu cầu đổi ca, vui lòng chờ phê duyệt!');
    }

    public function approve(ShiftSwapRequest $shiftSwapRequest)
    {
        DB::transaction(function () use (&$shiftSwapRequest) {
            $swap = ShiftSwapRequest::lockForUpdate()->findOrFail($shiftSwapRequest->id);
            abort_if($swap->status !== 'pending', 403, 'Yêu cầu đổi ca không ở trạng thái chờ duyệt.');

            $ids = collect([$swap->requester_schedule_id, $swap->target_schedule_id])->sort()->values();
            $schedules = ShiftSchedule::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            $reqSchedule = $schedules->get($swap->requester_schedule_id);
            $tgtSchedule = $schedules->get($swap->target_schedule_id);
            abort_if(!$reqSchedule || !$tgtSchedule, 422, 'Ca làm việc liên quan không còn tồn tại.');

            $this->assertSwappable($reqSchedule, $tgtSchedule, $swap->requester_employee_id, $swap->target_employee_id);

            $requesterEmployee = Employee::findOrFail($swap->requester_employee_id);
            $targetEmployee    = Employee::findOrFail($swap->target_employee_id);

            // Hoán đổi ai sở hữu 2 dòng lịch — ngày & ca giữ nguyên, chỉ đổi employee_id (+ branch_id denormalized).
            // Nhân viên có thể có nhiều ca/ngày (đa ca) nên không còn ràng buộc unique(employee_id, work_date)
            // ở tầng DB — có thể update trực tiếp tuần tự, không cần "gửi tạm" qua ngày placeholder nữa.
            $reqSchedule->update(['employee_id' => $targetEmployee->id, 'branch_id' => $targetEmployee->branch_id]);
            $tgtSchedule->update(['employee_id' => $requesterEmployee->id, 'branch_id' => $requesterEmployee->branch_id]);

            $swap->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);

            $shiftSwapRequest = $swap;
        });

        $shiftSwapRequest->loadMissing(['requesterEmployee', 'targetEmployee']);
        activity()->causedBy(auth()->user())
            ->performedOn($shiftSwapRequest)
            ->inLog('shift_swap_request')
            ->withProperties([
                'code'      => $shiftSwapRequest->code,
                'requester' => $shiftSwapRequest->requesterEmployee?->name,
                'target'    => $shiftSwapRequest->targetEmployee?->name,
            ])
            ->log("Duyệt đổi ca {$shiftSwapRequest->code}");

        app(NotificationService::class)->notifyShiftSwapApproved($shiftSwapRequest);

        return back()->with('success', 'Đã duyệt yêu cầu đổi ca và hoán đổi lịch làm việc!');
    }

    public function reject(Request $request, ShiftSwapRequest $shiftSwapRequest)
    {
        abort_if($shiftSwapRequest->status !== 'pending', 403, 'Yêu cầu đổi ca không ở trạng thái chờ duyệt.');

        $request->validate(['rejection_reason' => 'required|string|max:500']);

        $shiftSwapRequest->update([
            'status'           => 'rejected',
            'reviewed_by'      => auth()->id(),
            'reviewed_at'      => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        activity()->causedBy(auth()->user())
            ->performedOn($shiftSwapRequest)
            ->inLog('shift_swap_request')
            ->withProperties(['code' => $shiftSwapRequest->code, 'reason' => $request->rejection_reason])
            ->log("Từ chối đổi ca {$shiftSwapRequest->code}");

        app(NotificationService::class)->notifyShiftSwapRejected($shiftSwapRequest, $request->rejection_reason);

        return back()->with('success', 'Đã từ chối yêu cầu đổi ca.');
    }

    /**
     * Quy tắc xoá theo trạng thái:
     * - Chờ duyệt: chính chủ tự huỷ, HOẶC người có delete-shift-swaps xoá.
     * - Từ chối: người có delete-shift-swaps xoá (không có gì để đảo ngược).
     * - Đã duyệt: CHỈ admin (delete-approved-requests) — xoá kèm HOÁN LỊCH TRỞ LẠI chủ cũ. Chặn nếu
     *   2 ca không còn ở đúng trạng thái đã hoán (VD đã bị 1 đơn đổi ca/nghỉ khác thay đổi tiếp).
     */
    public function destroy(ShiftSwapRequest $shiftSwapRequest)
    {
        $isOwner    = $shiftSwapRequest->requesterEmployee?->user_id === auth()->id();
        $canPurge   = auth()->user()->can('delete-shift-swaps');
        $canReverse = auth()->user()->can('delete-approved-requests');
        $status     = $shiftSwapRequest->status;

        if ($status === 'approved') {
            abort_unless($canReverse, 403, 'Chỉ admin được xoá yêu cầu đã duyệt (thao tác sẽ hoán lịch trở lại chủ cũ).');
        } elseif ($status === 'pending') {
            abort_unless($isOwner || $canPurge, 403, 'Bạn không có quyền xoá yêu cầu này.');
        } else { // rejected
            abort_unless($canPurge, 403, 'Bạn không có quyền xoá yêu cầu này.');
        }

        DB::transaction(function () use ($shiftSwapRequest, $status) {
            $swap = ShiftSwapRequest::lockForUpdate()->findOrFail($shiftSwapRequest->id);

            if ($status === 'approved') {
                $this->reverseSwap($swap);
            }

            $swap->delete();
        });

        $verb = $status === 'pending' && $isOwner && !$canPurge ? 'huỷ' : 'xoá';
        activity()->causedBy(auth()->user())
            ->performedOn($shiftSwapRequest)
            ->inLog('shift_swap_request')
            ->withProperties(['code' => $shiftSwapRequest->code, 'status_before' => $status, 'reversed' => $status === 'approved'])
            ->log(ucfirst($verb) . " đổi ca {$shiftSwapRequest->code}" . ($status === 'approved' ? ' (đã hoán lịch trở lại)' : ''));

        return back()->with('success', 'Đã ' . $verb . ' yêu cầu đổi ca'
            . ($status === 'approved' ? ' và hoán lịch trở lại chủ cũ.' : '.'));
    }

    /**
     * Hoán quyền sở hữu 2 dòng lịch trở lại chủ ban đầu (đảo ngược approve()). Chặn nếu 2 ca không
     * còn ở đúng trạng thái đã hoán — nghĩa là đã có thao tác khác (đổi ca/nghỉ) tác động tiếp,
     * hoán ngược lúc này sẽ sai chủ sở hữu.
     */
    private function reverseSwap(ShiftSwapRequest $swap): void
    {
        $ids = collect([$swap->requester_schedule_id, $swap->target_schedule_id])->sort()->values();
        $schedules = ShiftSchedule::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

        $reqSchedule = $schedules->get($swap->requester_schedule_id);
        $tgtSchedule = $schedules->get($swap->target_schedule_id);

        abort_if(!$reqSchedule || !$tgtSchedule, 422, 'Không thể hoán lịch trở lại: một trong hai ca không còn tồn tại.');

        // Sau khi duyệt: requester_schedule thuộc target, target_schedule thuộc requester. Nếu không
        // còn đúng như vậy nghĩa là đã bị thao tác khác thay đổi tiếp -> chặn để tránh hoán sai.
        $stillSwapped = $reqSchedule->employee_id === $swap->target_employee_id
            && $tgtSchedule->employee_id === $swap->requester_employee_id;

        abort_unless($stillSwapped, 422, 'Không thể hoán lịch trở lại: 2 ca đã bị thay đổi bởi thao tác khác sau khi đổi. '
            . 'Vui lòng xử lý thao tác đó trước, hoặc chỉnh lịch thủ công.');

        $requesterEmployee = Employee::findOrFail($swap->requester_employee_id);
        $targetEmployee    = Employee::findOrFail($swap->target_employee_id);

        $reqSchedule->update(['employee_id' => $requesterEmployee->id, 'branch_id' => $requesterEmployee->branch_id]);
        $tgtSchedule->update(['employee_id' => $targetEmployee->id, 'branch_id' => $targetEmployee->branch_id]);
    }

    /**
     * Kiểm tra 2 lịch có hợp lệ để đổi cho nhau hay không — dùng cả lúc tạo đơn lẫn lúc duyệt
     * (lúc duyệt phải re-check vì trạng thái có thể đã đổi khác từ lúc tạo đơn).
     */
    private function assertSwappable(
        ShiftSchedule $reqSchedule,
        ShiftSchedule $tgtSchedule,
        int $requesterEmployeeId,
        int $targetEmployeeId
    ): void {
        abort_if($reqSchedule->isFlexible() || $tgtSchedule->isFlexible(), 422, 'Không thể đổi ca linh hoạt.');
        abort_if($reqSchedule->id === $tgtSchedule->id, 422, 'Không thể đổi ca với chính ca đó.');
        abort_if($requesterEmployeeId === $targetEmployeeId, 422, 'Không thể đổi ca với chính mình.');
        abort_if($reqSchedule->employee_id !== $requesterEmployeeId, 422, 'Ca này không còn thuộc về người yêu cầu.');
        abort_if($tgtSchedule->employee_id !== $targetEmployeeId, 422, 'Ca này không còn thuộc về nhân viên được chọn.');
        abort_if($reqSchedule->status !== 'scheduled' || $tgtSchedule->status !== 'scheduled', 422, 'Một trong hai ca không còn hiệu lực (đã bị huỷ).');
        abort_if($reqSchedule->work_date->lt(today()) || $tgtSchedule->work_date->lt(today()), 422, 'Không thể đổi ca đã diễn ra trong quá khứ.');

        // Ca đã bị điều chỉnh giờ do một đơn nghỉ theo giờ được duyệt (xem LeaveRequestsController::approve())
        // gắn liền với ĐÚNG nhân viên đã được duyệt — đổi ca sẽ khiến người nhận ca thừa hưởng nhầm
        // khung giờ đã điều chỉnh của người khác. Chặn hẳn thay vì âm thầm xoá/giữ sai.
        abort_if($reqSchedule->adjusted_start_time || $reqSchedule->adjusted_end_time, 422, 'Ca này đã được điều chỉnh giờ do nghỉ phép theo giờ đã duyệt, không thể đổi ca.');
        abort_if($tgtSchedule->adjusted_start_time || $tgtSchedule->adjusted_end_time, 422, 'Ca được chọn đã được điều chỉnh giờ do nghỉ phép theo giờ đã duyệt, không thể đổi ca.');

        // Lưu ý: một nhân viên có thể có NHIỀU ca khác nhau trong cùng 1 ngày (đa ca) — điều đó hợp lệ,
        // không phải xung đột. Chỉ chặn khi kết quả đổi ca khiến 1 người có 2 dòng TRÙNG shift_id trong
        // cùng 1 ngày (VD: đang có sẵn "Ca sáng" hôm đó, đổi ca lại nhận thêm 1 "Ca sáng" khác — vô nghĩa).
        $targetWouldDuplicate = ShiftSchedule::where('employee_id', $targetEmployeeId)
            ->where('work_date', $reqSchedule->work_date)
            ->where('shift_id', $reqSchedule->shift_id)
            ->where('id', '!=', $tgtSchedule->id)
            ->where('status', 'scheduled')
            ->exists();
        abort_if($targetWouldDuplicate, 422, 'Nhân viên được chọn đã có ca "' . $reqSchedule->shift?->name . '" vào đúng ngày này.');

        $requesterWouldDuplicate = ShiftSchedule::where('employee_id', $requesterEmployeeId)
            ->where('work_date', $tgtSchedule->work_date)
            ->where('shift_id', $tgtSchedule->shift_id)
            ->where('id', '!=', $reqSchedule->id)
            ->where('status', 'scheduled')
            ->exists();
        abort_if($requesterWouldDuplicate, 422, 'Bạn đã có ca "' . $tgtSchedule->shift?->name . '" vào đúng ngày này.');
    }
}
