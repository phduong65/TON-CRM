<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Penalty;
use App\Models\Team;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
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
            'totalEmployees',
            'totalTeams',
            'totalPenaltiesThisMonth',
            'redzoneEmployees',
            'redzoneCount',
            'recentPenalties',
            'topEmployees'
        ));
    }
}
