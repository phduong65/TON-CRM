<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\MonthlyEmployeeScore;
use App\Models\Notification;
use App\Models\Penalty;
use App\Models\Reward;
use App\Models\Setting;
use App\Models\ShiftSchedule;
use App\Models\Team;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        if (auth()->user()->hasRole(['admin', 'manager', 'director'])) {
            return $this->adminDashboard();
        }

        return $this->personalDashboard();
    }

    private function adminDashboard()
    {
        $isAdmin = true;
        // Admin/manager/director cũng có thể là một Employee (dùng cho view mobile dùng chung
        // 'dashboard.index' — khối script GPS/thời tiết tham chiếu $employee). Luôn truyền để
        // view không bao giờ gặp "Undefined variable $employee" dù đi qua nhánh nào.
        $employee = auth()->user()->employee()->with(['team', 'branch'])->first();
        $totalEmployees = Employee::where('is_active', true)->count();
        $totalTeams = Team::count();
        $totalBranches = Branch::count();
        $totalViolations = Violation::where('is_active', true)->count();

        $now = now();

        $totalPenaltiesThisMonth = Penalty::whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $totalPenaltiesLastMonth = Penalty::whereMonth('created_at', $now->copy()->subMonth()->month)
            ->whereYear('created_at', $now->copy()->subMonth()->year)
            ->count();

        $pendingPenalties = Penalty::where('status', 'pending')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $canViewPenalties = auth()->user()->can('view-penalties');
        $pendingPenaltiesTotal = $canViewPenalties
            ? Penalty::where('status', 'pending')->count()
            : 0;

        $approvedPenalties = Penalty::where('status', 'approved')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $totalMoneyDeducted = (float) Penalty::where('status', 'approved')
            ->sum('total_money_deducted');

        $serviceCharge = (float) Setting::getValue('service_charge', 0);

        $redzoneThreshold = Setting::getValue('redzone_threshold', 50);

        $redzoneEmployees = Employee::query()
            ->where('is_active', true)
            ->with(['user', 'team', 'branch'])
            ->addSelect([
                'total_score' => DB::table('employee_scores')
                    ->selectRaw('COALESCE(SUM(points), 0)')
                    ->whereColumn('employee_id', 'employees.id'),
            ])
            // Lọc bằng subquery tương quan trong WHERE thay vì HAVING trên alias không-aggregate —
            // HAVING không GROUP BY chỉ chạy trên MySQL, còn SQLite (test) báo lỗi "HAVING clause
            // on a non-aggregate query". Cách này chạy đúng trên cả hai. Binding ? tránh SQL injection.
            ->whereRaw(
                '(select COALESCE(SUM(points), 0) from employee_scores where employee_scores.employee_id = employees.id) < ?',
                [$redzoneThreshold]
            )
            ->orderBy('total_score')
            ->limit(5)
            ->get();

        $redzoneCount = Employee::select('employees.id')
            ->where('employees.is_active', true)
            ->leftJoin('employee_scores', 'employees.id', '=', 'employee_scores.employee_id')
            ->groupBy('employees.id')
            ->havingRaw('COALESCE(SUM(employee_scores.points), 0) < ?', [$redzoneThreshold])
            ->count();

        $recentPenalties = Penalty::with(['employee.user', 'violation'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // A separate, oldest-first queue powers the dashboard's primary review panel.
        // Keep the query permission-scoped even though the route itself is shared by
        // admin, manager, and director roles.
        $pendingApprovalQueue = $canViewPenalties
            ? Penalty::with(['employee.team', 'employee.branch', 'employee.user', 'violation'])
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->limit(8)
                ->get()
            : collect();

        $pendingRewards = Reward::where('status', 'pending')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $totalRewardsThisMonth = Reward::whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $recentRewards = Reward::with(['employee.user', 'rewardType'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $topEmployees = Employee::query()
            ->where('is_active', true)
            ->with(['team', 'branch'])
            ->addSelect([
                'total_score' => DB::table('employee_scores')
                    ->selectRaw('COALESCE(SUM(points), 0)')
                    ->whereColumn('employee_id', 'employees.id'),
            ])
            ->orderByDesc('total_score')
            ->limit(10)
            ->get();

        // ── Chart: Penalty & Reward Trend (6 tháng gần nhất) ─────────────────
        $trendLabels = [];
        $trendTotal = [];
        $trendApproved = [];
        $trendRewards = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = $now->copy()->subMonths($i);
            $trendLabels[] = 'T' . $d->month . '/' . $d->format('y');
            $trendTotal[] = Penalty::whereMonth('created_at', $d->month)->whereYear('created_at', $d->year)->count();
            $trendApproved[] = Penalty::where('status', 'approved')->whereMonth('created_at', $d->month)->whereYear('created_at', $d->year)->count();
            $trendRewards[] = Reward::where('status', 'approved')->whereMonth('created_at', $d->month)->whereYear('created_at', $d->year)->count();
        }
        $penaltyTrend = ['labels' => $trendLabels, 'total' => $trendTotal, 'approved' => $trendApproved, 'rewards' => $trendRewards];

        // ── Chart: Violation Distribution ────────────────────────────────────
        $distRaw = Penalty::select('violations.name', DB::raw('COUNT(penalties.id) as cnt'))
            ->join('violations', 'penalties.violation_id', '=', 'violations.id')
            ->groupBy('violations.id', 'violations.name')
            ->orderByDesc('cnt')
            ->limit(8)
            ->get();

        $violationDist = $distRaw->isNotEmpty()
            ? ['labels' => $distRaw->pluck('name')->toArray(), 'values' => $distRaw->pluck('cnt')->toArray()]
            : ['labels' => ['Không có dữ liệu'], 'values' => [1]];

        // ── Analytics: Preload shared collections (avoid N+1) ─────────────
        $defaultMonthlyScore = (int) Setting::getValue('default_score_per_month', 100);
        $allEmployees = Employee::where('is_active', true)->select('id', 'branch_id', 'team_id')->get();

        $thisMonthScores = MonthlyEmployeeScore::where('month', $now->month)
            ->where('year', $now->year)
            ->select('employee_id', 'final_score', 'deducted_points', 'zone')
            ->get()
            ->keyBy('employee_id');

        $thisMonthPensByEmp = Penalty::where('status', 'approved')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->select('employee_id')
            ->get()
            ->groupBy('employee_id');

        $thisMonthRewsByEmp = Reward::where('status', 'approved')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->select('employee_id')
            ->get()
            ->groupBy('employee_id');

        // ── KPI Strip ─────────────────────────────────────────────────────
        $avgScore = $allEmployees->count() > 0
            ? round($allEmployees->map(fn($e) => $thisMonthScores->get($e->id)?->final_score ?? $defaultMonthlyScore)->avg(), 1)
            : $defaultMonthlyScore;

        $totalPointsDeductedThisMonth = (int) $thisMonthScores->sum('deducted_points');

        $approvalRate = $totalPenaltiesThisMonth > 0
            ? round($approvedPenalties / $totalPenaltiesThisMonth * 100, 1)
            : 0;

        $repeatOffendersCount = Penalty::select('employee_id')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->groupBy('employee_id')
            ->havingRaw('COUNT(*) >= 2')
            ->count();

        // ── Zone Distribution ─────────────────────────────────────────────
        $unrecordedCount = $allEmployees->pluck('id')->diff($thisMonthScores->keys())->count();
        $zoneCounts = $thisMonthScores->groupBy('zone')->map->count();
        $zoneDist = [
            'labels' => ['Greenzone ≥90', 'Yellowzone ≥80', 'Orangezone ≥70', 'Redzone <70'],
            'values' => [
                (int) ($zoneCounts->get('green', 0) + $unrecordedCount),
                (int) $zoneCounts->get('yellow', 0),
                (int) $zoneCounts->get('orange', 0),
                (int) $zoneCounts->get('red', 0),
            ],
            'colors' => ['#10b981', '#eab308', '#f97316', '#f43f5e'],
        ];

        // ── Branch Performance ────────────────────────────────────────────
        $branchPerfData = Branch::all()->map(function ($branch) use ($allEmployees, $thisMonthScores, $thisMonthPensByEmp, $defaultMonthlyScore) {
            $empIds = $allEmployees->where('branch_id', $branch->id)->pluck('id');
            if ($empIds->isEmpty()) {
                return ['name' => $branch->name, 'avg_score' => $defaultMonthlyScore, 'penalty_count' => 0, 'emp_count' => 0];
            }
            $scores = $empIds->map(fn($id) => $thisMonthScores->get($id)?->final_score ?? $defaultMonthlyScore);
            $penCount = $empIds->sum(fn($id) => isset($thisMonthPensByEmp[$id]) ? $thisMonthPensByEmp[$id]->count() : 0);
            return ['name' => $branch->name, 'avg_score' => round($scores->avg(), 1), 'penalty_count' => $penCount, 'emp_count' => $empIds->count()];
        })->values();

        // ── Team Performance ──────────────────────────────────────────────
        $teamPerfData = Team::all()->map(function ($team) use ($allEmployees, $thisMonthScores, $thisMonthPensByEmp, $thisMonthRewsByEmp, $defaultMonthlyScore) {
            $empIds = $allEmployees->where('team_id', $team->id)->pluck('id');
            if ($empIds->isEmpty()) {
                return ['name' => $team->name, 'avg_score' => $defaultMonthlyScore, 'penalty_count' => 0, 'reward_count' => 0, 'emp_count' => 0];
            }
            $scores = $empIds->map(fn($id) => $thisMonthScores->get($id)?->final_score ?? $defaultMonthlyScore);
            $penCount = $empIds->sum(fn($id) => isset($thisMonthPensByEmp[$id]) ? $thisMonthPensByEmp[$id]->count() : 0);
            $rewCount = $empIds->sum(fn($id) => isset($thisMonthRewsByEmp[$id]) ? $thisMonthRewsByEmp[$id]->count() : 0);
            return ['name' => $team->name, 'avg_score' => round($scores->avg(), 1), 'penalty_count' => $penCount, 'reward_count' => $rewCount, 'emp_count' => $empIds->count()];
        })->values();

        // ── Daily Activity (this month) ───────────────────────────────────
        $daySql = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%d', created_at) AS INTEGER)"
            : 'DAY(created_at)';

        $dailyPenCounts = Penalty::whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->select(DB::raw("{$daySql} as day"), DB::raw('COUNT(*) as cnt'))
            ->groupBy(DB::raw($daySql))
            ->pluck('cnt', 'day');
        $dailyRewCounts = Reward::whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->select(DB::raw("{$daySql} as day"), DB::raw('COUNT(*) as cnt'))
            ->groupBy(DB::raw($daySql))
            ->pluck('cnt', 'day');
        $dailyLabels = $dailyPenData = $dailyRewData = [];
        for ($d = 1; $d <= $now->daysInMonth; $d++) {
            $dailyLabels[] = $d;
            $dailyPenData[] = (int) $dailyPenCounts->get($d, 0);
            $dailyRewData[] = (int) $dailyRewCounts->get($d, 0);
        }
        $dailyActivity = ['labels' => $dailyLabels, 'penalties' => $dailyPenData, 'rewards' => $dailyRewData];

        // ── Weekday Distribution ──────────────────────────────────────────
        $dowSql = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%w', created_at) AS INTEGER) + 1"
            : 'DAYOFWEEK(created_at)';

        $wdRaw = Penalty::whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->select(DB::raw("{$dowSql} as dow"), DB::raw('COUNT(*) as cnt'))
            ->groupBy(DB::raw($dowSql))
            ->pluck('cnt', 'dow');
        $weekdayDist = [
            'labels' => ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'],
            'values' => [(int) $wdRaw->get(2, 0), (int) $wdRaw->get(3, 0), (int) $wdRaw->get(4, 0), (int) $wdRaw->get(5, 0), (int) $wdRaw->get(6, 0), (int) $wdRaw->get(7, 0), (int) $wdRaw->get(1, 0)],
        ];

        // ── Avg Score Trend (6 months) ────────────────────────────────────
        $avgScoreTrendLabels = $avgScoreTrendValues = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = $now->copy()->subMonths($i);
            $avgScoreTrendLabels[] = 'T' . $d->month . '/' . $d->format('y');
            $monthAvg = MonthlyEmployeeScore::where('month', $d->month)->where('year', $d->year)->avg('final_score');
            $avgScoreTrendValues[] = $monthAvg !== null ? round($monthAvg, 1) : null;
        }
        $avgScoreTrend = ['labels' => $avgScoreTrendLabels, 'values' => $avgScoreTrendValues];

        // ── Top Violators ─────────────────────────────────────────────────
        $topViolatorsRaw = Penalty::select('employee_id', DB::raw('COUNT(*) as penalty_count'), DB::raw('SUM(total_points_deducted) as total_deducted'))
            ->where('status', 'approved')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->groupBy('employee_id')
            ->orderByDesc('penalty_count')
            ->limit(8)
            ->get();
        $violatorEmpMap = Employee::whereIn('id', $topViolatorsRaw->pluck('employee_id'))->with(['team', 'user'])->get()->keyBy('id');
        $topViolators = $topViolatorsRaw->map(fn($r) => tap($r, fn($r) => $r->employee = $violatorEmpMap->get($r->employee_id)));

        // ── Penalty Status Funnel ─────────────────────────────────────────
        $penaltyFunnel = [
            'total' => Penalty::count(),
            'pending' => Penalty::where('status', 'pending')->count(),
            'approved' => Penalty::where('status', 'approved')->count(),
            'rejected' => Penalty::where('status', 'rejected')->count(),
        ];

        // ── Nhân viên đang trong ca làm (đã check-in, chưa check-out) theo chi nhánh ──
        $onShiftLogs = AttendanceLog::query()
            ->where('work_date', $now->toDateString())
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->whereHas('employee')
            ->with(['employee.branch', 'employee.team', 'employee.user', 'shiftSchedule.shift'])
            ->get();

        $onShiftByBranch = $onShiftLogs
            ->sortBy('check_in_at')
            ->groupBy(fn($log) => $log->employee->branch->name ?? 'Chưa gán chi nhánh')
            ->sortKeys();

        $onShiftTotalCount = $onShiftLogs->count();

        // ── Chấm công hôm nay ───────────────────────────────────────────────
        $todayAttendanceLogs = AttendanceLog::where('work_date', $now->toDateString())->get();
        $checkedInTodayCount = $todayAttendanceLogs->whereNotNull('check_in_at')->count();
        $checkedOutTodayCount = $todayAttendanceLogs->whereNotNull('check_out_at')->count();
        $lateTodayCount = $todayAttendanceLogs->where('late_minutes', '>', 0)->count();
        $scheduledTodayCount = ShiftSchedule::where('work_date', $now->toDateString())
            ->where('status', 'scheduled')
            ->count();
        $notCheckedInTodayCount = max(0, $scheduledTodayCount - $checkedInTodayCount);
        $onTimeTodayCount = max(0, $checkedInTodayCount - $lateTodayCount);

        $lateEmployeesToday = AttendanceLog::where('work_date', $now->toDateString())
            ->where('late_minutes', '>', 0)
            ->whereHas('employee')
            ->with(['employee.branch', 'employee.team', 'employee.user'])
            ->orderByDesc('late_minutes')
            ->limit(8)
            ->get();

        // ── Xu hướng chấm công 7 ngày gần nhất ─────────────────────────────
        $attendanceWeekLabels = $attendanceWeekCheckedIn = $attendanceWeekLate = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $now->copy()->subDays($i);
            $dayLogs = AttendanceLog::where('work_date', $d->toDateString())->get();
            $attendanceWeekLabels[] = $d->format('d/m');
            $attendanceWeekCheckedIn[] = $dayLogs->whereNotNull('check_in_at')->count();
            $attendanceWeekLate[] = $dayLogs->where('late_minutes', '>', 0)->count();
        }
        $attendanceWeekTrend = [
            'labels' => $attendanceWeekLabels,
            'checkedIn' => $attendanceWeekCheckedIn,
            'late' => $attendanceWeekLate,
        ];

        // ── Overview: tỷ lệ chấm công theo ngày trong tuần này (Thứ 2 -> Chủ nhật, mẫu số = ca đã xếp) ──
        $startOfWeek = $now->copy()->startOfWeek(\Carbon\CarbonInterface::MONDAY);
        $endOfWeek = $now->copy()->endOfWeek(\Carbon\CarbonInterface::SUNDAY);
        $weekFrom = $startOfWeek->toDateString();
        $weekTo = $endOfWeek->toDateString();
        $todayStr = $now->toDateString();

        $weekCheckedInByDate = AttendanceLog::whereBetween('work_date', [$weekFrom, $weekTo])
            ->whereNotNull('check_in_at')
            ->get(['work_date'])
            ->countBy(fn($log) => $log->work_date->toDateString());
        $weekScheduledByDate = ShiftSchedule::whereBetween('work_date', [$weekFrom, $weekTo])
            ->where('status', 'scheduled')
            ->get(['work_date'])
            ->countBy(fn($schedule) => $schedule->work_date->toDateString());
        $weekdayNames = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
        $attendanceRateWeek = [
            'days' => [],
            'dates' => [],
            'rates' => [],
            'checkedIn' => [],
            'scheduled' => [],
            'todayIndex' => $now->dayOfWeekIso - 1, // 0 = Thứ 2 ... 6 = Chủ nhật
        ];
        for ($i = 0; $i < 7; $i++) {
            $d = $startOfWeek->copy()->addDays($i);
            $key = $d->toDateString();
            $scheduled = (int) $weekScheduledByDate->get($key, 0);
            $checked = (int) $weekCheckedInByDate->get($key, 0);
            $isFuture = $key > $todayStr;

            $attendanceRateWeek['days'][] = $weekdayNames[$d->dayOfWeek];
            $attendanceRateWeek['dates'][] = $d->format('d/m');
            $attendanceRateWeek['checkedIn'][] = $checked;
            $attendanceRateWeek['scheduled'][] = $scheduled;
            $attendanceRateWeek['rates'][] = $isFuture ? null : ($scheduled > 0 ? (int) round(min(100, $checked / $scheduled * 100)) : null);
        }

        $todayKey = $now->toDateString();
        $todayScheduled = (int) ($weekScheduledByDate->get($todayKey) ?? ShiftSchedule::where('work_date', $todayKey)->where('status', 'scheduled')->count());
        $todayChecked = (int) ($weekCheckedInByDate->get($todayKey) ?? AttendanceLog::where('work_date', $todayKey)->whereNotNull('check_in_at')->count());
        $attendanceRateToday = $todayScheduled > 0 ? (int) round(min(100, $todayChecked / $todayScheduled * 100)) : null;

        $yesterday = $now->copy()->subDay();
        $yesterdayKey = $yesterday->toDateString();
        $yesterdayScheduled = (int) ($weekScheduledByDate->get($yesterdayKey) ?? ShiftSchedule::where('work_date', $yesterdayKey)->where('status', 'scheduled')->count());
        $yesterdayChecked = (int) ($weekCheckedInByDate->get($yesterdayKey) ?? AttendanceLog::where('work_date', $yesterdayKey)->whereNotNull('check_in_at')->count());
        $attendanceRateYesterday = $yesterdayScheduled > 0 ? (int) round(min(100, $yesterdayChecked / $yesterdayScheduled * 100)) : null;

        // ── Overview: xu hướng phiếu phạt / phiếu thưởng 30 ngày ─────────────
        $trend30From = $now->copy()->subDays(29)->startOfDay();
        $pen30ByDate = Penalty::where('created_at', '>=', $trend30From)
            ->get(['created_at'])
            ->countBy(fn($p) => $p->created_at->toDateString());
        $rew30ByDate = Reward::where('created_at', '>=', $trend30From)
            ->get(['created_at'])
            ->countBy(fn($r) => $r->created_at->toDateString());
        $disciplineTrend30 = ['labels' => [], 'penalties' => [], 'rewards' => []];
        for ($i = 29; $i >= 0; $i--) {
            $d = $now->copy()->subDays($i);
            $disciplineTrend30['labels'][] = $d->format('d/m');
            $disciplineTrend30['penalties'][] = (int) $pen30ByDate->get($d->toDateString(), 0);
            $disciplineTrend30['rewards'][] = (int) $rew30ByDate->get($d->toDateString(), 0);
        }

        // ── Overview: số phụ cho KPI (chỉ dùng số có nguồn thật, không giả lập delta) ──
        $allEmployeesCount = Employee::count();
        $newEmployeesThisMonth = Employee::where('is_active', true)
            ->where('created_at', '>=', $now->copy()->startOfMonth())
            ->count();
        $pendingCreatedToday = $canViewPenalties
            ? Penalty::where('status', 'pending')->where('created_at', '>=', $now->copy()->startOfDay())->count()
            : 0;

        // ── Thông báo gần đây ───────────────────────────────────────────────
        $recentNotifications = Notification::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        $unreadNotificationsCount = Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();

        // ── Đăng ký tài khoản chờ phê duyệt ─────────────────────────────────
        $canManageUsers = auth()->user()->can('manage-users');
        $pendingUsers = $canManageUsers
            ? User::where('status', 'pending')->orderBy('created_at', 'desc')->limit(5)->get()
            : collect();
        $pendingUsersCount = $canManageUsers
            ? User::where('status', 'pending')->count()
            : 0;

        return view('dashboard.index', compact(
            'isAdmin',
            'employee',
            'now',
            'totalEmployees',
            'totalTeams',
            'totalBranches',
            'totalViolations',
            'totalMoneyDeducted',
            'serviceCharge',
            'totalPenaltiesThisMonth',
            'totalPenaltiesLastMonth',
            'pendingPenalties',
            'pendingPenaltiesTotal',
            'approvedPenalties',
            'redzoneEmployees',
            'redzoneCount',
            'recentPenalties',
            'pendingApprovalQueue',
            'topEmployees',
            'redzoneThreshold',
            'penaltyTrend',
            'violationDist',
            'pendingRewards',
            'totalRewardsThisMonth',
            'recentRewards',
            // Analytics
            'avgScore',
            'totalPointsDeductedThisMonth',
            'approvalRate',
            'repeatOffendersCount',
            'zoneDist',
            'branchPerfData',
            'teamPerfData',
            'dailyActivity',
            'weekdayDist',
            'avgScoreTrend',
            'topViolators',
            'penaltyFunnel',
            'onShiftByBranch',
            'onShiftTotalCount',
            'checkedInTodayCount',
            'checkedOutTodayCount',
            'lateTodayCount',
            'scheduledTodayCount',
            'notCheckedInTodayCount',
            'onTimeTodayCount',
            'lateEmployeesToday',
            'attendanceWeekTrend',
            'attendanceRateWeek',
            'attendanceRateToday',
            'attendanceRateYesterday',
            'disciplineTrend30',
            'allEmployeesCount',
            'newEmployeesThisMonth',
            'pendingCreatedToday',
            'defaultMonthlyScore',
            'recentNotifications',
            'unreadNotificationsCount',
            'pendingUsers',
            'pendingUsersCount',
        ));
    }

    private function personalDashboard()
    {
        $isAdmin = false;
        $employee = auth()->user()->employee()->with(['team', 'branch', 'position'])->first();
        $redzoneThreshold = Setting::getValue('redzone_threshold', 50);

        $myTotalScore = 0;
        $myPenaltiesCount = 0;
        $myRecentPenalties = collect();
        $myRank = null;
        $totalEmployees = 0;
        $isInRedzone = false;
        $canCheckinAttendance = auth()->user()->can('checkin-attendance');
        $todayShiftSchedules = collect();
        $unscheduledLog = null;
        $myMonthlySchedules = collect();
        $myMonthlyLogs = collect();

        if ($employee) {
            $myTotalScore = (int) $employee->scores()->sum('points');
            $isInRedzone = $myTotalScore < $redzoneThreshold;

            $myPenaltiesCount = Penalty::where('employee_id', $employee->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $myRecentPenalties = Penalty::with(['violation'])
                ->where('employee_id', $employee->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $totalEmployees = Employee::where('is_active', true)->count();

            $rankIndex = Employee::where('is_active', true)
                ->select('employees.id', DB::raw('COALESCE(SUM(employee_scores.points), 0) as total_score'))
                ->leftJoin('employee_scores', 'employees.id', '=', 'employee_scores.employee_id')
                ->groupBy('employees.id')
                ->orderBy('total_score', 'desc')
                ->pluck('id')
                ->search($employee->id);
            $myRank = $rankIndex !== false ? $rankIndex + 1 : null;

            if ($canCheckinAttendance) {
                $today = now()->toDateString();

                $todayShiftSchedules = ShiftSchedule::relevantForEmployeeToday($employee->id, $today)
                    ->with(['shift', 'attendanceLog'])
                    ->get()
                    ->sortBy(function ($schedule) {
                        return $schedule->effectiveShift()?->start_time ?? '00:00:00';
                    })
                    ->values();

                $unscheduledLog = $todayShiftSchedules->isEmpty()
                    ? AttendanceLog::where('employee_id', $employee->id)
                        ->where('work_date', $today)
                        ->whereNull('shift_schedule_id')
                        ->first()
                    : null;
            }

            // Query monthly schedules and logs for GitHub-style tracker
            $myMonthlySchedules = ShiftSchedule::where('employee_id', $employee->id)
                ->whereMonth('work_date', now()->month)
                ->whereYear('work_date', now()->year)
                ->with('attendanceLog')
                ->get()
                ->keyBy(fn($s) => \Carbon\Carbon::parse($s->work_date)->day);

            $myMonthlyLogs = AttendanceLog::where('employee_id', $employee->id)
                ->whereMonth('work_date', now()->month)
                ->whereYear('work_date', now()->year)
                ->get()
                ->keyBy(fn($l) => \Carbon\Carbon::parse($l->work_date)->day);
        }

        $recentNotifications = Notification::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();
        $unreadNotificationsCount = Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();

        return view('dashboard.index', compact(
            'isAdmin',
            'employee',
            'myTotalScore',
            'myPenaltiesCount',
            'myRecentPenalties',
            'myRank',
            'totalEmployees',
            'isInRedzone',
            'redzoneThreshold',
            'recentNotifications',
            'unreadNotificationsCount',
            'canCheckinAttendance',
            'todayShiftSchedules',
            'unscheduledLog',
            'myMonthlySchedules',
            'myMonthlyLogs',
        ));
    }

    public function mobileMenu()
    {
        $userAgent = request()->header('User-Agent', '');
        $isMobile = preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|pocket pc|pocketpc|phone|sony|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent);

        if (session('view_mode') !== 'mobile' && !$isMobile) {
            return redirect()->route('dashboard');
        }

        try {
            $redzoneCount = Employee::whereHas('scores', function ($q) {
                $q->selectRaw('SUM(points) as total')->having(
                    'total',
                    '<',
                    Setting::getValue('redzone_threshold', 50)
                );
            })->count();
        } catch (\Exception $e) {
            $redzoneCount = 0;
        }

        try {
            $unreadNotifCount = Notification::where('user_id', auth()->id())
                ->whereNull('read_at')
                ->count();
        } catch (\Exception $e) {
            $unreadNotifCount = 0;
        }

        return view('menu.index', compact('redzoneCount', 'unreadNotifCount'));
    }
}
