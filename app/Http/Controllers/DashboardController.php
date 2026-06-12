<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\MonthlyEmployeeScore;
use App\Models\Penalty;
use App\Models\Reward;
use App\Models\Team;
use App\Models\Setting;
use App\Models\Violation;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        if (auth()->user()->hasRole('admin')) {
            return $this->adminDashboard();
        }

        return $this->personalDashboard();
    }

    private function adminDashboard()
    {
        $isAdmin        = true;
        $totalEmployees = Employee::count();
        $totalTeams     = Team::count();
        $totalBranches  = Branch::count();
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

        $approvedPenalties = Penalty::where('status', 'approved')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $redzoneThreshold = Setting::getValue('redzone_threshold', 50);

        $redzoneEmployees = Employee::select('employees.*', DB::raw('COALESCE(SUM(employee_scores.points), 0) as total_score'))
            ->leftJoin('employee_scores', 'employees.id', '=', 'employee_scores.employee_id')
            ->groupBy('employees.id')
            ->having('total_score', '<', $redzoneThreshold)
            ->orderBy('total_score')
            ->limit(5)
            ->get();

        $redzoneCount = Employee::select('employees.id')
            ->leftJoin('employee_scores', 'employees.id', '=', 'employee_scores.employee_id')
            ->groupBy('employees.id')
            ->havingRaw('COALESCE(SUM(employee_scores.points), 0) < ?', [$redzoneThreshold])
            ->count();

        $recentPenalties = Penalty::with(['employee', 'violation'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $pendingRewards = Reward::where('status', 'pending')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $totalRewardsThisMonth = Reward::whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $recentRewards = Reward::with(['employee', 'rewardType'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $topEmployees = Employee::select('employees.*', DB::raw('COALESCE(SUM(employee_scores.points), 0) as total_score'))
            ->leftJoin('employee_scores', 'employees.id', '=', 'employee_scores.employee_id')
            ->groupBy('employees.id')
            ->orderBy('total_score', 'desc')
            ->limit(10)
            ->get();

        // ── Chart: Penalty & Reward Trend (6 tháng gần nhất) ─────────────────
        $trendLabels   = [];
        $trendTotal    = [];
        $trendApproved = [];
        $trendRewards  = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = $now->copy()->subMonths($i);
            $trendLabels[]   = 'T' . $d->month . '/' . $d->format('y');
            $trendTotal[]    = Penalty::whereMonth('created_at', $d->month)->whereYear('created_at', $d->year)->count();
            $trendApproved[] = Penalty::where('status', 'approved')->whereMonth('created_at', $d->month)->whereYear('created_at', $d->year)->count();
            $trendRewards[]  = Reward::where('status', 'approved')->whereMonth('created_at', $d->month)->whereYear('created_at', $d->year)->count();
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
        $allEmployees        = Employee::select('id', 'branch_id', 'team_id')->get();

        $thisMonthScores = MonthlyEmployeeScore::where('month', $now->month)
            ->where('year', $now->year)
            ->select('employee_id', 'final_score', 'deducted_points', 'zone')
            ->get()
            ->keyBy('employee_id');

        $thisMonthPensByEmp = Penalty::where('status', 'approved')
            ->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)
            ->select('employee_id')->get()->groupBy('employee_id');

        $thisMonthRewsByEmp = Reward::where('status', 'approved')
            ->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)
            ->select('employee_id')->get()->groupBy('employee_id');

        // ── KPI Strip ─────────────────────────────────────────────────────
        $avgScore = $allEmployees->count() > 0
            ? round($allEmployees->map(fn($e) => $thisMonthScores->get($e->id)?->final_score ?? $defaultMonthlyScore)->avg(), 1)
            : $defaultMonthlyScore;

        $totalPointsDeductedThisMonth = (int) $thisMonthScores->sum('deducted_points');

        $approvalRate = $totalPenaltiesThisMonth > 0
            ? round($approvedPenalties / $totalPenaltiesThisMonth * 100, 1)
            : 0;

        $repeatOffendersCount = Penalty::select('employee_id')
            ->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)
            ->groupBy('employee_id')->havingRaw('COUNT(*) >= 2')->count();

        // ── Zone Distribution ─────────────────────────────────────────────
        $unrecordedCount = $allEmployees->pluck('id')->diff($thisMonthScores->keys())->count();
        $zoneCounts      = $thisMonthScores->groupBy('zone')->map->count();
        $zoneDist = [
            'labels' => ['Greenzone ≥90', 'Yellowzone ≥80', 'Orangezone ≥70', 'Redzone <70'],
            'values' => [
                (int)($zoneCounts->get('green', 0) + $unrecordedCount),
                (int)$zoneCounts->get('yellow', 0),
                (int)$zoneCounts->get('orange', 0),
                (int)$zoneCounts->get('red',    0),
            ],
            'colors' => ['#10b981', '#eab308', '#f97316', '#f43f5e'],
        ];

        // ── Branch Performance ────────────────────────────────────────────
        $branchPerfData = Branch::all()->map(function ($branch) use ($allEmployees, $thisMonthScores, $thisMonthPensByEmp, $defaultMonthlyScore) {
            $empIds = $allEmployees->where('branch_id', $branch->id)->pluck('id');
            if ($empIds->isEmpty()) {
                return ['name' => $branch->name, 'avg_score' => $defaultMonthlyScore, 'penalty_count' => 0, 'emp_count' => 0];
            }
            $scores   = $empIds->map(fn($id) => $thisMonthScores->get($id)?->final_score ?? $defaultMonthlyScore);
            $penCount = $empIds->sum(fn($id) => isset($thisMonthPensByEmp[$id]) ? $thisMonthPensByEmp[$id]->count() : 0);
            return ['name' => $branch->name, 'avg_score' => round($scores->avg(), 1), 'penalty_count' => $penCount, 'emp_count' => $empIds->count()];
        })->values();

        // ── Team Performance ──────────────────────────────────────────────
        $teamPerfData = Team::all()->map(function ($team) use ($allEmployees, $thisMonthScores, $thisMonthPensByEmp, $thisMonthRewsByEmp, $defaultMonthlyScore) {
            $empIds = $allEmployees->where('team_id', $team->id)->pluck('id');
            if ($empIds->isEmpty()) {
                return ['name' => $team->name, 'avg_score' => $defaultMonthlyScore, 'penalty_count' => 0, 'reward_count' => 0, 'emp_count' => 0];
            }
            $scores   = $empIds->map(fn($id) => $thisMonthScores->get($id)?->final_score ?? $defaultMonthlyScore);
            $penCount = $empIds->sum(fn($id) => isset($thisMonthPensByEmp[$id]) ? $thisMonthPensByEmp[$id]->count() : 0);
            $rewCount = $empIds->sum(fn($id) => isset($thisMonthRewsByEmp[$id]) ? $thisMonthRewsByEmp[$id]->count() : 0);
            return ['name' => $team->name, 'avg_score' => round($scores->avg(), 1), 'penalty_count' => $penCount, 'reward_count' => $rewCount, 'emp_count' => $empIds->count()];
        })->values();

        // ── Daily Activity (this month) ───────────────────────────────────
        $dailyPenCounts = Penalty::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)
            ->select(DB::raw('DAY(created_at) as day'), DB::raw('COUNT(*) as cnt'))
            ->groupBy(DB::raw('DAY(created_at)'))->pluck('cnt', 'day');
        $dailyRewCounts = Reward::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)
            ->select(DB::raw('DAY(created_at) as day'), DB::raw('COUNT(*) as cnt'))
            ->groupBy(DB::raw('DAY(created_at)'))->pluck('cnt', 'day');
        $dailyLabels = $dailyPenData = $dailyRewData = [];
        for ($d = 1; $d <= $now->daysInMonth; $d++) {
            $dailyLabels[] = $d;
            $dailyPenData[] = (int) $dailyPenCounts->get($d, 0);
            $dailyRewData[] = (int) $dailyRewCounts->get($d, 0);
        }
        $dailyActivity = ['labels' => $dailyLabels, 'penalties' => $dailyPenData, 'rewards' => $dailyRewData];

        // ── Weekday Distribution ──────────────────────────────────────────
        $wdRaw = Penalty::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)
            ->select(DB::raw('DAYOFWEEK(created_at) as dow'), DB::raw('COUNT(*) as cnt'))
            ->groupBy(DB::raw('DAYOFWEEK(created_at)'))->pluck('cnt', 'dow');
        $weekdayDist = [
            'labels' => ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'],
            'values' => [(int)$wdRaw->get(2,0),(int)$wdRaw->get(3,0),(int)$wdRaw->get(4,0),(int)$wdRaw->get(5,0),(int)$wdRaw->get(6,0),(int)$wdRaw->get(7,0),(int)$wdRaw->get(1,0)],
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
            ->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)
            ->groupBy('employee_id')->orderByDesc('penalty_count')->limit(8)->get();
        $violatorEmpMap = Employee::whereIn('id', $topViolatorsRaw->pluck('employee_id'))->with('team')->get()->keyBy('id');
        $topViolators   = $topViolatorsRaw->map(fn($r) => tap($r, fn($r) => $r->employee = $violatorEmpMap->get($r->employee_id)));

        // ── Penalty Status Funnel ─────────────────────────────────────────
        $penaltyFunnel = [
            'total'    => Penalty::count(),
            'pending'  => Penalty::where('status', 'pending')->count(),
            'approved' => Penalty::where('status', 'approved')->count(),
            'rejected' => Penalty::where('status', 'rejected')->count(),
        ];

        return view('dashboard.index', compact(
            'isAdmin',
            'now',
            'totalEmployees',
            'totalTeams',
            'totalBranches',
            'totalViolations',
            'totalPenaltiesThisMonth',
            'totalPenaltiesLastMonth',
            'pendingPenalties',
            'approvedPenalties',
            'redzoneEmployees',
            'redzoneCount',
            'recentPenalties',
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
        ));
    }

    private function personalDashboard()
    {
        $isAdmin = false;
        $employee = auth()->user()->employee()->with(['team', 'branch'])->first();
        $redzoneThreshold = Setting::getValue('redzone_threshold', 50);

        $myTotalScore    = 0;
        $myPenaltiesCount = 0;
        $myRecentPenalties = collect();
        $myRank          = null;
        $totalEmployees  = 0;
        $teamLeaderboard = collect();
        $isInRedzone     = false;

        if ($employee) {
            $myTotalScore = (int) $employee->scores()->sum('points');
            $isInRedzone  = $myTotalScore < $redzoneThreshold;

            $myPenaltiesCount = Penalty::where('employee_id', $employee->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $myRecentPenalties = Penalty::with(['violation'])
                ->where('employee_id', $employee->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $totalEmployees = Employee::count();

            $rankIndex = Employee::select('employees.id', DB::raw('COALESCE(SUM(employee_scores.points), 0) as total_score'))
                ->leftJoin('employee_scores', 'employees.id', '=', 'employee_scores.employee_id')
                ->groupBy('employees.id')
                ->orderBy('total_score', 'desc')
                ->pluck('id')
                ->search($employee->id);
            $myRank = $rankIndex !== false ? $rankIndex + 1 : null;

            if ($employee->team_id) {
                $teamLeaderboard = Employee::select('employees.*', DB::raw('COALESCE(SUM(employee_scores.points), 0) as total_score'))
                    ->leftJoin('employee_scores', 'employees.id', '=', 'employee_scores.employee_id')
                    ->where('employees.team_id', $employee->team_id)
                    ->groupBy('employees.id')
                    ->orderBy('total_score', 'desc')
                    ->limit(5)
                    ->get();
            }
        }

        return view('dashboard.index', compact(
            'isAdmin',
            'employee',
            'myTotalScore',
            'myPenaltiesCount',
            'myRecentPenalties',
            'myRank',
            'totalEmployees',
            'teamLeaderboard',
            'isInRedzone',
            'redzoneThreshold',
        ));
    }
}
