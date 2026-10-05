<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ShiftSwapRequestController extends Controller
{
    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $query = ShiftSwapRequest::with(['requesterEmployee', 'targetEmployee', 'requesterSchedule.shift', 'targetSchedule.shift', 'reviewer'])
            ->where(fn($q) => $q->where('requester_employee_id', $employee->id)->orWhere('target_employee_id', $employee->id))
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->paginate(15);
        $requests->getCollection()->transform(fn(ShiftSwapRequest $s) => $this->format($s));

        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $employee = $request->user()->employee;
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
        $code = 'SWP-' . now()->format('Ym') . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);

        $swap = ShiftSwapRequest::create([
            'code'                  => $code,
            'requester_employee_id' => $employee->id,
            'requester_schedule_id' => $reqSchedule->id,
            'target_employee_id'    => $tgtSchedule->employee_id,
            'target_schedule_id'    => $tgtSchedule->id,
            'reason'                => $validated['reason'] ?? null,
            'status'                => 'pending',
        ]);

        activity()->causedBy($request->user())
            ->performedOn($swap)
            ->inLog('shift_swap_request')
            ->withProperties(['code' => $code, 'requester' => $employee->name, 'source' => 'mobile'])
            ->log("Gửi yêu cầu đổi ca {$code} — {$employee->name}");

        app(NotificationService::class)->notifyShiftSwapCreated($swap);

        return response()->json($this->format($swap), 201);
    }

    public function destroy(Request $request, ShiftSwapRequest $shiftSwapRequest)
    {
        abort_unless($shiftSwapRequest->requester_employee_id === $request->user()->employee?->id, 403, 'Bạn chỉ có thể huỷ yêu cầu do chính mình tạo.');
        abort_if($shiftSwapRequest->status !== 'pending', 403, 'Chỉ có thể huỷ yêu cầu đang chờ duyệt.');

        $shiftSwapRequest->delete();

        return response()->json(['message' => 'Đã huỷ yêu cầu đổi ca.']);
    }

    private function assertSwappable(ShiftSchedule $reqSchedule, ShiftSchedule $tgtSchedule, int $requesterEmployeeId, int $targetEmployeeId): void
    {
        abort_if($reqSchedule->id === $tgtSchedule->id, 422, 'Không thể đổi ca với chính ca đó.');
        abort_if($requesterEmployeeId === $targetEmployeeId, 422, 'Không thể đổi ca với chính mình.');
        abort_if($reqSchedule->status !== 'scheduled' || $tgtSchedule->status !== 'scheduled', 422, 'Một trong hai ca không còn hiệu lực (đã bị huỷ).');
        abort_if($reqSchedule->work_date->lt(today()) || $tgtSchedule->work_date->lt(today()), 422, 'Không thể đổi ca đã diễn ra trong quá khứ.');

        $targetWouldDuplicate = ShiftSchedule::where('employee_id', $targetEmployeeId)
            ->where('work_date', $reqSchedule->work_date)
            ->where('shift_id', $reqSchedule->shift_id)
            ->where('id', '!=', $tgtSchedule->id)
            ->where('status', 'scheduled')
            ->exists();
        abort_if($targetWouldDuplicate, 422, 'Nhân viên được chọn đã có ca này vào đúng ngày.');

        $requesterWouldDuplicate = ShiftSchedule::where('employee_id', $requesterEmployeeId)
            ->where('work_date', $tgtSchedule->work_date)
            ->where('shift_id', $tgtSchedule->shift_id)
            ->where('id', '!=', $reqSchedule->id)
            ->where('status', 'scheduled')
            ->exists();
        abort_if($requesterWouldDuplicate, 422, 'Bạn đã có ca này vào đúng ngày.');
    }

    private function format(ShiftSwapRequest $s): array
    {
        return [
            'id'               => $s->id,
            'code'             => $s->code,
            'requester'        => $s->requesterEmployee?->name,
            'target'           => $s->targetEmployee?->name,
            'requester_date'   => $s->requesterSchedule?->work_date?->toDateString(),
            'target_date'      => $s->targetSchedule?->work_date?->toDateString(),
            'reason'           => $s->reason,
            'status'           => $s->status,
            'status_label'     => $s->statusLabel(),
            'rejection_reason' => $s->rejection_reason,
            'created_at'       => $s->created_at->toIso8601String(),
        ];
    }
}
