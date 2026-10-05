<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Branch;
use App\Models\Position;
use App\Models\Team;
use App\Models\User;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Setting;
use App\Models\MonthlyEmployeeScore;
use App\Services\AnnualLeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeesController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(['branch', 'team', 'position', 'user'])
            ->orderByDesc('is_active')
            ->orderByDesc('updated_at');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === '1');
        }

        $employees = $query->paginate(15)->withQueryString();
        $branches  = Branch::orderBy('name')->get();
        $teams     = Team::orderBy('name')->get();
        $positions = Position::where('is_active', true)->orderBy('name')->get();
        return view('employees.index', compact('employees', 'branches', 'teams', 'positions'));
    }

    public function create()
    {
        $branches = Branch::orderBy('name')->get();
        $teams = Team::orderBy('name')->get();
        $positions = Position::where('is_active', true)->orderBy('name')->get();
        return view('employees.form', compact('branches', 'teams', 'positions'));
    }

    public function store(StoreEmployeeRequest $request)
    {
        $plainPassword = Str::random(10);

        $defaultPoints = (int) Setting::getValue('default_points', 100);

        $employee = DB::transaction(function () use ($request, $plainPassword, $defaultPoints) {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => $plainPassword,
            ]);

            if (\Spatie\Permission\Models\Role::where('name', 'staff')->exists()) {
                $user->assignRole('staff');
            }

            $employee = Employee::create(array_merge(
                $request->validated(),
                ['user_id' => $user->id]
            ));

            $employee->scores()->create([
                'points' => $defaultPoints,
                'reason' => 'Điểm khởi điểm',
                'type'   => 'adjustment',
            ]);

            return $employee;
        });

        $employee->loadMissing(['branch', 'team', 'position']);
        activity()
            ->performedOn($employee)
            ->causedBy(auth()->user())
            ->inLog('employee')
            ->withProperties([
                'code'     => $employee->code,
                'name'     => $employee->name,
                'email'    => $employee->email,
                'position' => $employee->position?->name,
                'branch'   => $employee->branch?->name,
                'team'     => $employee->team?->name,
            ])
            ->log('Tạo nhân viên ' . $employee->name . ' (' . $employee->code . ')');

        return redirect()->route('employees.show', $employee)
            ->with('new_account', [
                'name'     => $employee->name,
                'code'     => $employee->code,
                'email'    => $employee->email,
                'password' => $plainPassword,
            ]);
    }

    public function show(Employee $employee)
    {
        $user = auth()->user();
        $canViewSensitive = $user->hasRole(['admin', 'manager', 'director'])
            || $user->employee?->id === $employee->id;

        $employee->load(['branch', 'team', 'position']);
        $annualLeave = null;
        $performanceStats = null;
        if ($canViewSensitive) {
            $employee->load(['scores' => fn($q) => $q->latest(), 'penalties' => function ($q) {
                $q->with('violation')->latest();
            }]);

            $defaultScore = (int) Setting::getValue('default_score_per_month', 100);
            $totalScore = (int) $employee->total_score;
            $zone = MonthlyEmployeeScore::computeZone($totalScore);

            $penalties = $employee->penalties;
            $totalPenaltiesCount = $penalties->count();
            $pendingPenaltiesCount = $penalties->where('status', 'pending')->count();
            $pendingPoints = (int) $penalties->where('status', 'pending')->sum('total_points_deducted');
            $approvedPoints = (int) $penalties->where('status', 'approved')->sum('total_points_deducted');

            // Top violation patterns
            $violationPatterns = $penalties->groupBy(fn($p) => $p->violation?->name ?? 'Lỗi khác')
                ->map(fn($grp, $name) => [
                    'name'   => $name,
                    'count'  => $grp->count(),
                    'points' => (int) $grp->sum('total_points_deducted'),
                ])
                ->sortByDesc('count')
                ->take(3)
                ->values();

            // Total rewarded points (points > 0 and not initial 100)
            $rewardedPoints = (int) $employee->scores
                ->where('points', '>', 0)
                ->filter(fn($sc) => !str_contains(mb_strtolower($sc->reason ?? ''), 'khởi tạo') && $sc->points !== 100)
                ->sum('points');

            // Total deducted points from scores
            $deductedPoints = (int) abs($employee->scores->where('points', '<', 0)->sum('points'));

            // Calculate monthly trend vs previous month or net changes
            $now = now();
            $prevMonth = $now->copy()->subMonth();
            $prevRecord = MonthlyEmployeeScore::where('employee_id', $employee->id)
                ->where('month', $prevMonth->month)
                ->where('year', $prevMonth->year)
                ->first();

            if ($prevRecord && $prevRecord->final_score > 0) {
                $trendDelta = $totalScore - (int)$prevRecord->final_score;
            } else {
                $thisMonthNet = (int) $employee->scores
                    ->filter(function ($sc) use ($now) {
                        return $sc->created_at->month === $now->month
                            && $sc->created_at->year === $now->year
                            && !str_contains(mb_strtolower($sc->reason ?? ''), 'khởi tạo')
                            && $sc->points !== 100;
                    })
                    ->sum('points');
                $trendDelta = $thisMonthNet;
            }
            $trendLabel = ($trendDelta > 0 ? "+{$trendDelta}" : "{$trendDelta}") . ' tháng này';

            $performanceStats = [
                'default_score'           => $defaultScore,
                'total_score'             => $totalScore,
                'score_percent'           => $defaultScore > 0 ? min(100, max(0, round(($totalScore / $defaultScore) * 100))) : 0,
                'zone'                    => $zone,
                'zone_label'              => MonthlyEmployeeScore::zoneLabel($zone),
                'zone_badge'              => MonthlyEmployeeScore::zoneBadgeClass($zone),
                'monthly_trend_delta'     => $trendDelta,
                'monthly_trend_label'     => $trendLabel,
                'total_penalties_count'   => $totalPenaltiesCount,
                'pending_penalties_count' => $pendingPenaltiesCount,
                'pending_points'          => $pendingPoints,
                'approved_points'         => $approvedPoints,
                'rewarded_points'         => $rewardedPoints,
                'deducted_points'         => $deductedPoints,
                'total_penalties_deducted'=> $approvedPoints,
                'violation_patterns'      => $violationPatterns,
            ];

            if ($employee->isEligibleForAnnualLeave()) {
                $annualLeaveService = app(AnnualLeaveService::class);
                $year = (int) now()->year;
                $annualLeave = [
                    'year'      => $year,
                    'entitled'  => $annualLeaveService->entitledDays($employee, $year),
                    'used'      => $annualLeaveService->usedDays($employee, $year),
                    'remaining' => $annualLeaveService->remainingDays($employee, $year),
                ];
            }
        }
        return view('employees.show', compact('employee', 'canViewSensitive', 'annualLeave', 'performanceStats'));
    }

    public function edit(Employee $employee)
    {
        $branches = Branch::orderBy('name')->get();
        $teams = Team::orderBy('name')->get();
        $positions = Position::where('is_active', true)->orderBy('name')->get();
        return view('employees.form', compact('employee', 'branches', 'teams', 'positions'));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());
        $employee->refresh()->loadMissing(['branch', 'team', 'position']);

        $employee->user?->update(['status' => $employee->is_active ? 'active' : 'inactive']);
        activity()
            ->performedOn($employee)
            ->causedBy(auth()->user())
            ->inLog('employee')
            ->withProperties([
                'code'     => $employee->code,
                'name'     => $employee->name,
                'position' => $employee->position?->name,
                'branch'   => $employee->branch?->name,
                'team'     => $employee->team?->name,
            ])
            ->log('Cập nhật nhân viên ' . $employee->name . ' (' . $employee->code . ')');

        return redirect()->route('employees.index')
            ->with('success', 'Thông tin nhân viên đã được cập nhật!');
    }

    public function destroy(Employee $employee, Request $request)
    {
        $employee->loadMissing(['branch', 'user']);

        if ($request->input('_delete_type') === 'permanent') {
            DB::transaction(function () use ($employee) {
                $user = $employee->user;
                activity()
                    ->performedOn($employee)
                    ->causedBy(auth()->user())
                    ->inLog('employee')
                    ->withProperties([
                        'code'   => $employee->code,
                        'name'   => $employee->name,
                        'branch' => $employee->branch?->name,
                    ])
                    ->log('Xóa nhân viên ' . $employee->name . ' (' . $employee->code . ')');

                $employee->delete();
                $user?->delete();
            });

            return redirect()->route('employees.index')
                ->with('success', 'Nhân viên đã được xóa khỏi hệ thống!');
        }

        // Default: mark as resigned
        activity()
            ->performedOn($employee)
            ->causedBy(auth()->user())
            ->inLog('employee')
            ->withProperties([
                'code'   => $employee->code,
                'name'   => $employee->name,
                'branch' => $employee->branch?->name,
            ])
            ->log('Đánh dấu nghỉ việc nhân viên ' . $employee->name . ' (' . $employee->code . ')');

        $employee->update(['is_active' => false]);
        $employee->user?->update(['status' => 'inactive']);

        return redirect()->route('employees.index')
            ->with('success', 'Nhân viên đã được đánh dấu nghỉ việc!');
    }

    public function penalties(Employee $employee)
    {
        $user = auth()->user();
        $isOwnProfile = $user->employee?->id === $employee->id;
        $isPrivileged = $user->hasRole(['admin', 'manager', 'director']);

        if (!$isOwnProfile && !$isPrivileged) {
            abort(403, 'Bạn không có quyền xem lịch sử vi phạm của nhân viên khác.');
        }

        $penalties = $employee->penalties()
            ->with(['violation', 'approver'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        return view('employees.penalties', compact('employee', 'penalties'));
    }
}
