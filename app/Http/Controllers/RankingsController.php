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

        $teams = Team::withSum('employees as scores_sum', DB::raw('COALESCE((SELECT SUM(points) FROM employee_scores WHERE employee_id IN (SELECT id FROM employees WHERE team_id = teams.id)), 0)'))
            ->orderBy('scores_sum', 'desc')
            ->get();

        return view('rankings.index', compact('employees', 'teams'));
    }
}
