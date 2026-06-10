<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RankingsController extends Controller
{
    public function index()
    {
        $employees = Employee::select('employees.*', DB::raw('COALESCE(SUM(employee_scores.points), 0) as total_score'))
            ->leftJoin('employee_scores', 'employees.id', '=', 'employee_scores.employee_id')
            ->groupBy('employees.id')
            ->orderBy('total_score', 'desc')
            ->paginate(20);

        $teams = Team::select('teams.*')
            ->selectSub(
                DB::table('employee_scores')
                    ->selectRaw('COALESCE(SUM(points), 0)')
                    ->whereIn('employee_id', function ($q) {
                        $q->select('id')->from('employees')->whereColumn('team_id', 'teams.id');
                    }),
                'scores_sum'
            )
            ->selectSub(
                DB::table('employees')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('team_id', 'teams.id'),
                'employees_count'
            )
            ->orderBy('scores_sum', 'desc')
            ->get();
        return view('rankings.index', compact('employees', 'teams'));
    }
}
