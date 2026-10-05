<?php

namespace App\Http\Controllers;

use App\Models\AttendanceAlert;
use App\Models\Branch;
use App\Models\Team;
use App\Services\ShiftCoverageService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OperationalScheduleController extends Controller
{
    public function __construct(protected ShiftCoverageService $coverageService)
    {
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        // Phạm vi chi nhánh theo quyền người dùng
        $branchQuery = Branch::where('is_active', true)->orderBy('name');
        if ($user->hasRole('manager') && $user->employee?->branch_id) {
            $branchQuery->where('id', $user->employee->branch_id);
        }

        $branches = $branchQuery->get();
        abort_if($branches->isEmpty(), 403, 'Bạn không có quyền truy cập chi nhánh nào.');

        $selectedBranchId = $request->filled('branch_id')
            ? (int) $request->branch_id
            : ($user->employee?->branch_id ?: $branches->first()->id);

        // Đảm bảo branch được chọn nằm trong danh sách được phép
        if (!$branches->pluck('id')->contains($selectedBranchId)) {
            $selectedBranchId = $branches->first()->id;
        }

        // Danh sách bộ phận theo branch được chọn
        $teamQuery = Team::where('branch_id', $selectedBranchId)->where('is_active', true)->orderBy('name');
        if ($user->hasRole('team_leader') && $user->employee?->team_id) {
            $teamQuery->where('id', $user->employee->team_id);
        }
        $teams = $teamQuery->get();

        $selectedTeamId = $request->filled('team_id') ? (int) $request->team_id : null;
        if ($user->hasRole('team_leader') && $user->employee?->team_id) {
            $selectedTeamId = $user->employee->team_id;
        }

        // Xác định tuần hiển thị (Thứ 2 -> Chủ nhật)
        $weekStart = $request->filled('week')
            ? Carbon::parse($request->week)->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);

        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $prevWeek = $weekStart->copy()->subWeek()->toDateString();
        $nextWeek = $weekStart->copy()->addWeek()->toDateString();
        $thisWeek = now()->startOfWeek(Carbon::MONDAY)->toDateString();

        // Tính toán ma trận định biên vận hành
        $coverageData = $this->coverageService->getWeeklyCoverage(
            $selectedBranchId,
            $selectedTeamId,
            $weekStart
        );

        // Số cảnh báo chấm công đang mở tại chi nhánh này
        $openAlertsCount = AttendanceAlert::whereIn('status', ['open', 'seen'])
            ->whereHas('employee', fn($eq) => $eq->where('branch_id', $selectedBranchId))
            ->count();

        $viewMode = $request->get('view', 'table');
        if (!in_array($viewMode, ['table', 'list', 'cards'])) {
            $viewMode = 'table';
        }

        // Quy tắc định biên theo từng bộ phận trong tuần này để phục vụ hiển thị dạng bảng
        $teamRequirements = \App\Models\ShiftCoverageRequirement::where('branch_id', $selectedBranchId)
            ->where('is_active', true)
            ->where('effective_from', '<=', $weekEnd->toDateString())
            ->where(function ($q) use ($weekStart) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $weekStart->toDateString());
            })
            ->when($selectedTeamId, fn($q) => $q->where('team_id', $selectedTeamId))
            ->orderBy('start_time')
            ->get()
            ->groupBy('team_id');

        return view('operational-schedule.index', [
            'branches'                => $branches,
            'teams'                   => $teams,
            'selectedBranchId'        => $selectedBranchId,
            'selectedTeamId'          => $selectedTeamId,
            'viewMode'                => $viewMode,
            'teamRequirements'        => $teamRequirements,
            'weekStart'               => $weekStart,
            'weekEnd'                 => $weekEnd,
            'prevWeek'                => $prevWeek,
            'nextWeek'                => $nextWeek,
            'thisWeek'                => $thisWeek,
            'days'                    => $coverageData['days'],
            'dailyCoverage'           => $coverageData['dailyCoverage'] ?? $coverageData['daily_coverage'],
            'totalApprovedLeavesWeek' => $coverageData['total_approved_leaves_week'],
            'totalShortageFramesWeek' => $coverageData['total_shortage_frames_week'],
            'openAlertsCount'         => $openAlertsCount,
        ]);
    }
}
