<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\MonthlyEmployeeScore;
use App\Models\Setting;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RankingsController extends Controller
{
    public function index(Request $request)
    {
        $defaultScore = (int) Setting::getValue('default_score_per_month', 100);

        // ── All-time employee ranking (sum of monthly final_score + surplus) ──
        $employees = Employee::where('is_active', true)
            ->with(['branch', 'team', 'monthlyScores'])
            ->get()
            ->map(function ($emp) use ($defaultScore) {
                $scores = $emp->monthlyScores;
                // Employees with no records yet default to the current default score
                // (prevents non-penalised employees from showing 0 and ranking last)
                $emp->alltime_score   = $scores->isEmpty() ? $defaultScore : $scores->sum('final_score');
                $emp->alltime_surplus = $scores->sum('surplus_points');
                $currentRecord = $scores
                    ->where('month', now()->month)
                    ->where('year', now()->year)
                    ->first();
                $emp->zone = $currentRecord ? $currentRecord->zone : 'green';
                return $emp;
            })
            ->sortBy([
                ['alltime_score', 'desc'],
                ['alltime_surplus', 'desc'],
            ])
            ->values();

        // Assign rank numbers for all-time (tied = same rank)
        [$rank, $prevScore, $prevSurplus, $count] = [0, null, null, 0];
        foreach ($employees as $emp) {
            $count++;
            if ($emp->alltime_score !== $prevScore || $emp->alltime_surplus !== $prevSurplus) {
                $rank = $count;
            }
            $emp->rank     = $rank;
            $prevScore     = $emp->alltime_score;
            $prevSurplus   = $emp->alltime_surplus;
        }

        // ── Team ranking ──────────────────────────────────────────────────────
        $teams = Team::select([
                'teams.id', 'teams.code', 'teams.name', 'teams.branch_id',
                'teams.description', 'teams.is_active', 'teams.created_at', 'teams.updated_at',
            ])
            ->selectRaw('COALESCE(SUM(employee_scores.points), 0) / NULLIF(COUNT(DISTINCT employees.id), 0) as average_score')
            ->selectRaw('COUNT(DISTINCT employees.id) as employees_count')
            ->leftJoin('employees', 'employees.team_id', '=', 'teams.id')
            ->leftJoin('employee_scores', 'employee_scores.employee_id', '=', 'employees.id')
            ->groupBy('teams.id', 'teams.code', 'teams.name', 'teams.branch_id', 'teams.description', 'teams.is_active', 'teams.created_at', 'teams.updated_at')
            ->with('branch')
            ->orderByDesc('average_score')
            ->get();

        // ── Monthly award ─────────────────────────────────────────────────────
        $evalMonth = (int) ($request->eval_month ?? now()->month);
        $evalYear  = (int) ($request->eval_year  ?? now()->year);

        $monthlyRanking  = $this->buildMonthlyRanking($evalMonth, $evalYear, $defaultScore);
        $employeeOfMonth = $monthlyRanking->first();

        // ── Yearly award ──────────────────────────────────────────────────────
        $evalYearOnly  = (int) ($request->eval_year_only ?? now()->year);
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
        // Pre-fetch all monthly records for this month to avoid N+1
        $scoreMap = MonthlyEmployeeScore::where('month', $month)
            ->where('year', $year)
            ->get()
            ->keyBy('employee_id');

        $ranked = Employee::where('is_active', true)
            ->with(['branch', 'team'])
            ->get()
            ->map(function ($emp) use ($scoreMap, $defaultScore) {
                $record = $scoreMap->get($emp->id);

                $emp->display_score  = $record ? $record->final_score     : $defaultScore;
                $emp->surplus_points = $record ? $record->surplus_points  : 0;
                $emp->deducted       = $record ? $record->deducted_points : 0;
                $emp->rewarded       = $record ? $record->rewarded_points : 0;
                $emp->zone           = $record ? $record->zone            : 'green';
                return $emp;
            })
            ->sortBy([
                ['display_score', 'desc'],
                ['surplus_points', 'desc'],
            ])
            ->values();

        // Assign rank numbers (tied employees get same rank)
        [$rank, $prevScore, $prevSurplus, $count] = [0, null, null, 0];
        foreach ($ranked as $emp) {
            $count++;
            if ($emp->display_score !== $prevScore || $emp->surplus_points !== $prevSurplus) {
                $rank = $count;
            }
            $emp->rank   = $rank;
            $prevScore   = $emp->display_score;
            $prevSurplus = $emp->surplus_points;
        }

        return $ranked;
    }

    private function buildYearlyRanking(int $year, int $defaultScore)
    {
        $ranked = Employee::where('is_active', true)
            ->with(['branch', 'team', 'monthlyScores' => fn($q) => $q->where('year', $year)])
            ->get()
            ->map(function ($emp) use ($defaultScore) {
                $records = $emp->monthlyScores;

                $avgScore    = $records->count() > 0 ? round($records->avg('final_score'), 1)    : $defaultScore;
                $avgSurplus  = $records->count() > 0 ? round($records->avg('surplus_points'), 1) : 0;
                $monthsInRed  = $records->where('zone', 'red')->count();
                $monthsLogged = $records->count();

                $emp->display_score  = $avgScore;
                $emp->surplus_points = $avgSurplus;
                $emp->months_logged  = $monthsLogged;
                $emp->months_in_red  = $monthsInRed;
                $emp->zone           = MonthlyEmployeeScore::computeZone((int) $avgScore);
                return $emp;
            })
            ->sortBy([
                ['display_score', 'desc'],
                ['surplus_points', 'desc'],
            ])
            ->values();

        // Assign rank numbers (tied employees get same rank)
        [$rank, $prevScore, $prevSurplus, $count] = [0, null, null, 0];
        foreach ($ranked as $emp) {
            $count++;
            if ($emp->display_score !== $prevScore || $emp->surplus_points !== $prevSurplus) {
                $rank = $count;
            }
            $emp->rank   = $rank;
            $prevScore   = $emp->display_score;
            $prevSurplus = $emp->surplus_points;
        }

        return $ranked;
    }
}
