<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RedzoneController extends Controller
{
    public function index()
    {
        $threshold = Setting::getValue('redzone_threshold', 50);

        $employees = Employee::select('employees.*', DB::raw('COALESCE(SUM(employee_scores.points), 0) as total_score'))
            ->leftJoin('employee_scores', 'employees.id', '=', 'employee_scores.employee_id')
            ->groupBy('employees.id')
            ->having('total_score', '<', $threshold)
            ->orderBy('total_score', 'asc')
            ->paginate(15);

        $threshold = (int) $threshold;

        return view('redzone.index', compact('employees', 'threshold'));
    }
}
