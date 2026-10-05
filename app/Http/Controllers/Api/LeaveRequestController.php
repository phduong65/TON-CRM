<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\ShiftSchedule;
use App\Services\AnnualLeaveService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    /**
     * App mobile chỉ phục vụ nhân viên thường (xem quyết định trong CLAUDE.md/Requests) —
     * luôn tạo/xem đơn cho chính mình, không có chế độ "tạo hộ" như trên web.
     */
    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $query = LeaveRequest::with('reviewer')
            ->where('employee_id', $employee->id)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->paginate(15);
        $requests->getCollection()->transform(fn(LeaveRequest $lr) => $this->format($lr));

        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $validated = $request->validate([
            'date_from'            => 'required|date',
            'date_to'              => 'required|date|after_or_equal:date_from',
            'type'                 => 'required|in:annual,unpaid',
            'shift_schedule_id'    => 'nullable|integer|exists:shift_schedules,id',
            'reason'               => 'required|string|max:1000',
            'handover_employee_id' => 'nullable|exists:employees,id',
            'handover_phone'       => 'nullable|string|max:20',
            'handover_note'        => 'nullable|string|max:1000',
        ]);

        if ($validated['type'] === 'annual') {
            if (!$employee->isEligibleForAnnualLeave()) {
                return response()->json([
                    'message' => 'Nhân viên không đủ điều kiện nghỉ phép năm. Vui lòng chọn loại nghỉ khác.',
                ], 422);
            }

            $requestedDays = Carbon::parse($validated['date_from'])->diffInDays($validated['date_to']) + 1;
            // Dùng đúng năm của date_from — xem LeaveRequestsController::store() cho giải thích đầy đủ.
            $leaveYear = Carbon::parse($validated['date_from'])->year;
            $remaining = app(AnnualLeaveService::class)->remainingDays($employee, $leaveYear);

            if ($requestedDays > $remaining) {
                return response()->json([
                    'message' => "Không đủ số ngày phép năm còn lại (còn {$remaining} ngày, đang xin {$requestedDays} ngày).",
                ], 422);
            }
        }

        if (!empty($validated['shift_schedule_id'])) {
            $ownsSchedule = ShiftSchedule::where('id', $validated['shift_schedule_id'])
                ->where('employee_id', $employee->id)
                ->exists();
            abort_unless($ownsSchedule, 422, 'Ca làm đã chọn không thuộc về bạn.');
        }

        // withTrashed() bắt buộc: nếu chỉ đếm bản ghi còn sống, xoá 1 đơn ở giữa tháng sẽ làm
        // số đếm bị lùi lại, sinh trùng "code" với đơn chưa xoá (code có unique constraint).
        $count = LeaveRequest::withTrashed()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;
        $code = 'LR-' . now()->format('Ym') . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);

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

        activity()->causedBy($request->user())
            ->performedOn($leaveRequest)
            ->inLog('leave_request')
            ->withProperties(['code' => $code, 'employee_name' => $employee->name, 'source' => 'mobile'])
            ->log("Gửi đơn xin nghỉ {$code} — {$employee->name}");

        app(NotificationService::class)->notifyLeaveRequestCreated($leaveRequest);

        return response()->json($this->format($leaveRequest), 201);
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest)
    {
        abort_unless($leaveRequest->employee_id === $request->user()->employee?->id, 403, 'Bạn chỉ có thể huỷ đơn của chính mình.');
        abort_if($leaveRequest->status !== 'pending', 403, 'Chỉ có thể huỷ đơn đang chờ duyệt.');

        $leaveRequest->delete();

        return response()->json(['message' => 'Đã huỷ đơn xin nghỉ.']);
    }

    private function format(LeaveRequest $lr): array
    {
        return [
            'id'               => $lr->id,
            'code'             => $lr->code,
            'date_from'        => $lr->date_from->toDateString(),
            'date_to'          => $lr->date_to->toDateString(),
            'days_count'       => $lr->daysCount(),
            'type'             => $lr->type,
            'type_label'       => $lr->typeLabel(),
            'reason'           => $lr->reason,
            'status'           => $lr->status,
            'status_label'     => $lr->statusLabel(),
            'rejection_reason' => $lr->rejection_reason,
            'reviewed_at'      => $lr->reviewed_at?->toIso8601String(),
            'created_at'       => $lr->created_at->toIso8601String(),
        ];
    }
}
