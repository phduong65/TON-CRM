<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use Illuminate\Http\Request;

class RewardController extends Controller
{
    /**
     * Danh sách phiếu thưởng của chính nhân viên (là người được thưởng hoặc thành viên trong
     * phiếu thưởng theo nhóm/team) — scope theo employee, không có chế độ xem toàn bộ như web.
     */
    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $rewards = Reward::with(['rewardType', 'approver', 'members'])
            ->where(function ($q) use ($employee) {
                $q->where('employee_id', $employee->id)
                  ->orWhereHas('members', fn($m) => $m->where('employee_id', $employee->id));
            })
            ->orderByDesc('created_at')
            ->paginate(15);

        $rewards->getCollection()->transform(fn(Reward $r) => $this->formatSummary($r));

        return response()->json($rewards);
    }

    public function show(Request $request, Reward $reward)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $canView = $reward->employee_id === $employee->id
            || $reward->members()->where('employee_id', $employee->id)->exists();
        abort_unless($canView, 403);

        $reward->load(['rewardType', 'approver', 'members.employee']);

        return response()->json([
            'id'                    => $reward->id,
            'code'                  => $reward->code ?? '#' . $reward->id,
            'status'                => $reward->status,
            'description'           => $reward->description,
            'reward_type'           => $reward->rewardType?->name,
            'total_points_awarded'  => $reward->total_points_awarded,
            'rejected_reason'       => $reward->rejected_reason,
            'approved_at'           => $reward->approved_at?->toIso8601String(),
            'created_at'            => $reward->created_at->toIso8601String(),
            'members'               => $reward->members->map(fn($m) => [
                'employee_name'  => $m->employee?->name,
                'points_awarded' => $m->points_awarded,
                'note'           => $m->note,
            ]),
        ]);
    }

    private function formatSummary(Reward $reward): array
    {
        return [
            'id'                   => $reward->id,
            'code'                 => $reward->code ?? '#' . $reward->id,
            'status'               => $reward->status,
            'reward_type'          => $reward->rewardType?->name,
            'total_points_awarded' => $reward->total_points_awarded,
            'created_at'           => $reward->created_at->toIso8601String(),
        ];
    }
}
