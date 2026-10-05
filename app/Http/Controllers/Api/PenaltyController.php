<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Penalty;
use Illuminate\Http\Request;

class PenaltyController extends Controller
{
    /**
     * Danh sách phiếu phạt của chính nhân viên (là người bị phạt hoặc là thành viên liên đới).
     * App mobile chỉ phục vụ nhân viên thường nên luôn scope theo employee, không có chế độ xem
     * toàn bộ như web (dành cho người có quyền approve-penalties).
     */
    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $penalties = Penalty::with(['violation.regulation', 'approver', 'members'])
            ->where(function ($q) use ($employee) {
                $q->where('employee_id', $employee->id)
                  ->orWhereHas('members', fn($m) => $m->where('employee_id', $employee->id));
            })
            ->orderByDesc('created_at')
            ->paginate(15);

        $penalties->getCollection()->transform(fn(Penalty $p) => $this->formatSummary($p));

        return response()->json($penalties);
    }

    public function show(Request $request, Penalty $penalty)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $canView = $penalty->employee_id === $employee->id
            || $penalty->members()->where('employee_id', $employee->id)->exists();
        abort_unless($canView, 403);

        $penalty->load(['employee', 'violation.regulation', 'approver', 'members.employee', 'attachments']);

        return response()->json([
            'id'                     => $penalty->id,
            'code'                   => $penalty->code ?? '#' . $penalty->id,
            'status'                 => $penalty->status,
            'description'            => $penalty->description,
            'violation'              => $penalty->violation?->name,
            'total_points_deducted'  => $penalty->total_points_deducted,
            'total_money_deducted'   => (float) $penalty->total_money_deducted,
            'rejected_reason'        => $penalty->rejected_reason,
            'approved_at'            => $penalty->approved_at?->toIso8601String(),
            'created_at'             => $penalty->created_at->toIso8601String(),
            'members'                => $penalty->members->map(fn($m) => [
                'employee_name'    => $m->employee?->name,
                'points_deducted'  => $m->points_deducted,
                'money_deducted'   => (float) $m->money_deducted,
                'note'             => $m->note,
            ]),
        ]);
    }

    private function formatSummary(Penalty $penalty): array
    {
        return [
            'id'                    => $penalty->id,
            'code'                  => $penalty->code ?? '#' . $penalty->id,
            'status'                => $penalty->status,
            'violation'             => $penalty->violation?->name,
            'total_points_deducted' => $penalty->total_points_deducted,
            'created_at'            => $penalty->created_at->toIso8601String(),
        ];
    }
}
