<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\MonthlyEmployeeScore;
use App\Models\Setting;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RankingsController extends Controller
{
    public function index(Request $request)
    {
        $defaultScore = (int) Setting::getValue('default_score_per_month', 100);

        // ── All-time employee ranking (all records, scroll in view) ──────────
        $employees = Employee::select('employees.*', DB::raw('COALESCE(SUM(employee_scores.points), 0) as total_score'))
            ->leftJoin('employee_scores', 'employees.id', '=', 'employee_scores.employee_id')
            ->with(['branch', 'team'])
            ->groupBy('employees.id')
            ->orderByDesc('total_score')
            ->get();

        // Attach current-month zone to each all-time employee row
        $monthlyZoneMap = MonthlyEmployeeScore::where('month', now()->month)
            ->where('year', now()->year)
            ->pluck('zone', 'employee_id');
        $employees->transform(function ($emp) use ($monthlyZoneMap) {
            $emp->zone = $monthlyZoneMap->get($emp->id, 'green');
            return $emp;
        });

        // ── Team ranking ──────────────────────────────────────────────────────
        $teams = Team::select('teams.*')
            ->selectRaw('COALESCE(SUM(employee_scores.points), 0) / NULLIF(COUNT(DISTINCT employees.id), 0) as average_score')
            ->selectRaw('COUNT(DISTINCT employees.id) as employees_count')
            ->leftJoin('employees', 'employees.team_id', '=', 'teams.id')
            ->leftJoin('employee_scores', 'employee_scores.employee_id', '=', 'employees.id')
            ->groupBy('teams.id')
            ->with('branch')
            ->orderByDesc('average_score')
            ->get();

        // ── Monthly award ─────────────────────────────────────────────────────
        $evalMonth = (int) ($request->eval_month ?? now()->month);
        $evalYear  = (int) ($request->eval_year  ?? now()->year);

        $monthlyRanking = $this->buildMonthlyRanking($evalMonth, $evalYear, $defaultScore);
        $employeeOfMonth = $monthlyRanking->first();

        // ── Yearly award ──────────────────────────────────────────────────────
        $evalYearOnly = (int) ($request->eval_year_only ?? now()->year);
        $yearlyRanking = $this->buildYearlyRanking($evalYearOnly, $defaultScore);
        $employeeOfYear = $yearlyRanking->first();

        // ── Month options for selector ────────────────────────────────────────
        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $d = Carbon::now()->subMonths($i);
            $monthOptions[] = [
                'month' => $d->month,
                'year'  => $d->year,
                'label' => $d->translatedFormat('m/Y'),
            ];
        }

        $yearOptions = range(now()->year, max(now()->year - 4, 2024));

        return view('rankings.index', compact(
            'employees', 'teams',
            'monthlyRanking', 'employeeOfMonth', 'evalMonth', 'evalYear',
            'yearlyRanking',  'employeeOfYear',  'evalYearOnly',
            'monthOptions', 'yearOptions', 'defaultScore'
        ));
    }

    private function buildMonthlyRanking(int $month, int $year, int $defaultScore)
    {
        return Employee::where('is_active', true)
            ->with(['branch', 'team'])
            ->get()
            ->map(function ($emp) use ($month, $year, $defaultScore) {
                $record = MonthlyEmployeeScore::where([
                    'employee_id' => $emp->id,
                    'month'       => $month,
                    'year'        => $year,
                ])->first();

                $emp->display_score  = $record ? $record->final_score     : $defaultScore;
                $emp->deducted       = $record ? $record->deducted_points  : 0;
                $emp->rewarded       = $record ? ($record->rewarded_points ?? 0) : 0;
                $emp->zone           = $record ? $record->zone             : 'green';
                return $emp;
            })
            ->sortByDesc('display_score')
            ->values();
    }

    private function buildYearlyRanking(int $year, int $defaultScore)
    {
        return Employee::where('is_active', true)
            ->with(['branch', 'team'])
            ->get()
            ->map(function ($emp) use ($year, $defaultScore) {
                $records = MonthlyEmployeeScore::where('employee_id', $emp->id)
                    ->where('year', $year)
                    ->get();

                $avgScore     = $records->count() > 0 ? round($records->avg('final_score'), 1) : $defaultScore;
                $monthsInRed  = $records->where('zone', 'red')->count();
                $monthsLogged = $records->count();

                $emp->display_score  = $avgScore;
                $emp->months_logged  = $monthsLogged;
                $emp->months_in_red  = $monthsInRed;
                $emp->zone           = MonthlyEmployeeScore::computeZone((int) $avgScore);
                return $emp;
            })
            ->sortByDesc('display_score')
            ->values();
    }
}
