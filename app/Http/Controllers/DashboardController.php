<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Penalty;
use App\Models\Team;
use App\Models\Setting;
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
        $isAdmin = true;
        $totalEmployees = Employee::count();
        $totalTeams = Team::count();

        $totalPenaltiesThisMonth = Penalty::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
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

        $topEmployees = Employee::select('employees.*', DB::raw('COALESCE(SUM(employee_scores.points), 0) as total_score'))
            ->leftJoin('employee_scores', 'employees.id', '=', 'employee_scores.employee_id')
            ->groupBy('employees.id')
            ->orderBy('total_score', 'desc')
            ->limit(10)
            ->get();

        return view('dashboard.index', compact(
            'isAdmin',
            'totalEmployees',
            'totalTeams',
            'totalPenaltiesThisMonth',
            'redzoneEmployees',
            'redzoneCount',
            'recentPenalties',
            'topEmployees',
            'redzoneThreshold',
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
