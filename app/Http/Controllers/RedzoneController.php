<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeScore;
use App\Models\MonthlyEmployeeScore;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RedzoneController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->hasRole(['admin', 'manager'])) {
            abort(403, 'Chỉ admin và quản lý mới có thể xem trang này.');
        }

        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year  ?? now()->year);

        $month = max(1, min(12, $month));

        $defaultScore      = (int) Setting::getValue('default_score_per_month', 100);
        $greenMin          = (int) Setting::getValue('greenzone_min', 90);
        $yellowMin         = (int) Setting::getValue('yellowzone_min', 80);
        $orangeMin         = (int) Setting::getValue('orangezone_min', 70);
        $consecutiveMonths = (int) Setting::getValue('consecutive_redzone_months', 2);

        // Single query: total deducted points per employee for this month
        // Source of truth: employee_scores (penalty type, negative points)
        $deductionsByEmp = EmployeeScore::where('type', 'penalty')
            ->where('points', '<', 0)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(ABS(points)) as total_deducted')
            ->pluck('total_deducted', 'employee_id')
            ->map(fn($v) => (int) $v);

        $employees = Employee::where('is_active', true)
            ->with(['branch', 'team'])
            ->get()
            ->map(function ($emp) use ($defaultScore, $deductionsByEmp) {
                $deducted = $deductionsByEmp->get($emp->id, 0);
                $final    = max(0, $defaultScore - $deducted);

                $emp->monthly_deducted    = $deducted;
                $emp->monthly_final_score = $final;
                $emp->zone                = MonthlyEmployeeScore::computeZone($final);
                return $emp;
            });

        $byZone = [
            'green'  => $employees->where('zone', 'green')->sortByDesc('monthly_final_score')->values(),
            'yellow' => $employees->where('zone', 'yellow')->sortByDesc('monthly_final_score')->values(),
            'orange' => $employees->where('zone', 'orange')->sortByDesc('monthly_final_score')->values(),
            'red'    => $employees->where('zone', 'red')->sortBy('monthly_final_score')->values(),
        ];

        $consecutiveRedzoneIds = $this->findConsecutiveRedzoneIds(
            $byZone['red']->pluck('id'),
            $consecutiveMonths,
            $month,
            $year,
            $defaultScore
        );

        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $d = Carbon::now()->subMonths($i);
            $monthOptions[] = ['month' => $d->month, 'year' => $d->year, 'label' => $d->format('m/Y')];
        }

        return view('redzone.index', compact(
            'byZone', 'month', 'year',
            'greenMin', 'yellowMin', 'orangeMin',
            'consecutiveMonths', 'consecutiveRedzoneIds',
            'defaultScore', 'monthOptions'
        ));
    }

    private function findConsecutiveRedzoneIds(
        $employeeIds,
        int $months,
        int $currentMonth,
        int $currentYear,
        int $defaultScore
    ): array {
        if ($months < 2 || $employeeIds->isEmpty()) {
            return [];
        }

        $flagged = [];
        $ref = Carbon::create($currentYear, $currentMonth, 1);

        // Build list of prev months to check
        $prevMonths = [];
        for ($i = 1; $i < $months; $i++) {
            $d = $ref->copy()->subMonths($i);
            $prevMonths[] = ['month' => $d->month, 'year' => $d->year];
        }

        foreach ($employeeIds as $empId) {
            $allRed = true;
            foreach ($prevMonths as $pm) {
                // Check monthly_employee_scores first (fast), fallback to computing from employee_scores
                $stored = MonthlyEmployeeScore::where([
                    'employee_id' => $empId,
                    'month'       => $pm['month'],
                    'year'        => $pm['year'],
                ])->value('zone');

                if ($stored !== null) {
                    if ($stored !== 'red') { $allRed = false; break; }
                    continue;
                }

                // No stored record — compute from employee_scores
                $deducted = (int) EmployeeScore::where('employee_id', $empId)
                    ->where('type', 'penalty')
                    ->where('points', '<', 0)
                    ->whereMonth('created_at', $pm['month'])
                    ->whereYear('created_at', $pm['year'])
                    ->sum(DB::raw('ABS(points)'));

                $zone = MonthlyEmployeeScore::computeZone(max(0, $defaultScore - $deducted));
                if ($zone !== 'red') { $allRed = false; break; }
            }

            if ($allRed) {
                $flagged[] = $empId;
            }
        }

        return $flagged;
    }
}
