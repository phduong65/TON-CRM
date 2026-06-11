<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
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

        // ── Chart: Penalty Trend (6 tháng gần nhất) ──────────────────────────
        $trendLabels   = [];
        $trendTotal    = [];
        $trendApproved = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = $now->copy()->subMonths($i);
            $trendLabels[]   = 'T' . $d->month . '/' . $d->format('y');
            $trendTotal[]    = Penalty::whereMonth('created_at', $d->month)->whereYear('created_at', $d->year)->count();
            $trendApproved[] = Penalty::where('status', 'approved')->whereMonth('created_at', $d->month)->whereYear('created_at', $d->year)->count();
        }
        $penaltyTrend = ['labels' => $trendLabels, 'total' => $trendTotal, 'approved' => $trendApproved];

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

        return view('dashboard.index', compact(
            'isAdmin',
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
