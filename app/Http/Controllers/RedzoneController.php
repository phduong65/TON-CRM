<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeScore;
use App\Models\MonthlyEmployeeScore;
use App\Models\Penalty;
use App\Models\Setting;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Vùng điểm & Redzone — worklist theo tháng. Quyền: route middleware `can:view-redzone`.
 */
class RedzoneController extends Controller
{
    private const ZONES = ['red', 'orange', 'yellow', 'green'];

    public function index(Request $request)
    {
        $month = max(1, min(12, (int) ($request->month ?? now()->month)));
        $year  = (int) ($request->year ?? now()->year);
        $zone  = in_array($request->zone, self::ZONES, true) ? $request->zone : 'red';

        $defaultScore      = (int) Setting::getValue('default_score_per_month', 100);
        $greenMin          = (int) Setting::getValue('greenzone_min', 90);
        $yellowMin         = (int) Setting::getValue('yellowzone_min', 80);
        $orangeMin         = (int) Setting::getValue('orangezone_min', 70);
        $consecutiveMonths = (int) Setting::getValue('consecutive_redzone_months', 2);

        $period = Carbon::create($year, $month, 1);
        $prev   = $period->copy()->subMonth();

        // Điểm trừ theo nhân viên — nguồn: employee_scores (type penalty, điểm âm)
        $deductionsByEmp     = $this->deductionsByEmployee($month, $year);
        $prevDeductionsByEmp = $this->deductionsByEmployee($prev->month, $prev->year);

        $employees = Employee::where('is_active', true)
            ->with(['branch', 'team', 'user'])
            ->when($request->filled('branch_id'), fn($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('team_id'), fn($q) => $q->where('team_id', $request->integer('team_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(fn($qq) => $qq->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
            })
            ->get()
            ->map(function ($emp) use ($defaultScore, $deductionsByEmp, $prevDeductionsByEmp) {
                $deducted = $deductionsByEmp->get($emp->id, 0);
                $emp->monthly_deducted    = $deducted;
                $emp->monthly_final_score = max(0, $defaultScore - $deducted);
                $emp->prev_final_score    = max(0, $defaultScore - $prevDeductionsByEmp->get($emp->id, 0));
                $emp->score_change        = $emp->monthly_final_score - $emp->prev_final_score;
                $emp->zone                = MonthlyEmployeeScore::computeZone($emp->monthly_final_score);
                return $emp;
            });

        $zoneCounts = collect(self::ZONES)->mapWithKeys(fn($z) => [$z => $employees->where('zone', $z)->count()]);

        // Danh sách của vùng đang xem: vùng xấu xếp điểm thấp lên trước (ưu tiên xử lý), vùng tốt xếp điểm cao trước
        $zoneEmployees = $employees->where('zone', $zone);
        $zoneEmployees = (in_array($zone, ['red', 'orange'], true)
            ? $zoneEmployees->sortBy('monthly_final_score')
            : $zoneEmployees->sortByDesc('monthly_final_score'))->values();

        $redIds = $employees->where('zone', 'red')->pluck('id');
        $consecutiveRedzoneIds = $this->findConsecutiveRedzoneIds($redIds, $consecutiveMonths, $period, $defaultScore);

        $reasonsByEmp = $this->deductionReasons($zoneEmployees->pluck('id'), $month, $year);

        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $d = Carbon::now()->subMonths($i);
            $monthOptions[] = ['month' => $d->month, 'year' => $d->year, 'label' => 'Tháng ' . $d->format('m/Y')];
        }

        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $teams = Team::where('is_active', true)
            ->when($request->filled('branch_id'), fn($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderBy('name')->get(['id', 'name']);

        return view('redzone.index', compact(
            'zone', 'zoneCounts', 'zoneEmployees', 'reasonsByEmp',
            'month', 'year', 'greenMin', 'yellowMin', 'orangeMin', 'defaultScore',
            'consecutiveMonths', 'consecutiveRedzoneIds', 'monthOptions', 'branches', 'teams'
        ));
    }

    private function deductionsByEmployee(int $month, int $year): Collection
    {
        return EmployeeScore::where('type', 'penalty')
            ->where('points', '<', 0)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(ABS(points)) as total_deducted')
            ->pluck('total_deducted', 'employee_id')
            ->map(fn($v) => (int) $v);
    }

    /**
     * Nguyên nhân bị trừ điểm trong tháng, gom theo lỗi vi phạm (từ phiếu phạt gốc), nhiều điểm nhất trước.
     * @return Collection<int, Collection<int, array{name:string,count:int,points:int}>>
     */
    private function deductionReasons(Collection $employeeIds, int $month, int $year): Collection
    {
        if ($employeeIds->isEmpty()) {
            return collect();
        }

        $rows = EmployeeScore::where('type', 'penalty')
            ->where('points', '<', 0)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->whereIn('employee_id', $employeeIds)
            ->get(['employee_id', 'points', 'reason', 'reference_type', 'reference_id']);

        $penaltyIds = $rows->where('reference_type', Penalty::class)->pluck('reference_id')->filter()->unique();
        $violationByPenalty = Penalty::with('violation:id,name')
            ->whereIn('id', $penaltyIds)
            ->get(['id', 'violation_id'])
            ->mapWithKeys(fn($p) => [$p->id => $p->violation?->name]);

        return $rows->groupBy('employee_id')->map(function ($empRows) use ($violationByPenalty) {
            return $empRows
                ->groupBy(function ($r) use ($violationByPenalty) {
                    $name = $r->reference_type === Penalty::class ? ($violationByPenalty[$r->reference_id] ?? null) : null;
                    return $name ?: Str::limit($r->reason ?: 'Khác', 40);
                })
                ->map(fn($g, $name) => ['name' => $name, 'count' => $g->count(), 'points' => (int) abs($g->sum('points'))])
                ->sortByDesc('points')
                ->values();
        });
    }

    /**
     * Nhân viên Redzone tháng này và cả (N-1) tháng liền trước. Ưu tiên zone đã lưu trong
     * monthly_employee_scores; tháng chưa có bản ghi thì tính từ employee_scores.
     * Gộp truy vấn theo cả nhóm nhân viên thay vì 1 truy vấn/nhân viên/tháng.
     */
    private function findConsecutiveRedzoneIds(Collection $employeeIds, int $months, Carbon $period, int $defaultScore): array
    {
        if ($months < 2 || $employeeIds->isEmpty()) {
            return [];
        }

        $prevMonths = collect(range(1, $months - 1))->map(fn($i) => $period->copy()->subMonths($i));

        $stored = MonthlyEmployeeScore::whereIn('employee_id', $employeeIds)
            ->where(function ($q) use ($prevMonths) {
                foreach ($prevMonths as $d) {
                    $q->orWhere(fn($qq) => $qq->where('month', $d->month)->where('year', $d->year));
                }
            })
            ->get(['employee_id', 'month', 'year', 'zone'])
            ->keyBy(fn($r) => "{$r->employee_id}-{$r->month}-{$r->year}");

        $computed = EmployeeScore::where('type', 'penalty')
            ->where('points', '<', 0)
            ->whereIn('employee_id', $employeeIds)
            ->where('created_at', '>=', $prevMonths->last()->copy()->startOfMonth())
            ->where('created_at', '<', $period->copy()->startOfMonth())
            ->get(['employee_id', 'points', 'created_at'])
            ->groupBy(fn($r) => "{$r->employee_id}-{$r->created_at->month}-{$r->created_at->year}")
            ->map(fn($g) => (int) abs($g->sum('points')));

        return $employeeIds->filter(function ($empId) use ($prevMonths, $stored, $computed, $defaultScore) {
            foreach ($prevMonths as $d) {
                $key = "{$empId}-{$d->month}-{$d->year}";
                $zone = $stored[$key]->zone
                    ?? MonthlyEmployeeScore::computeZone(max(0, $defaultScore - ($computed[$key] ?? 0)));
                if ($zone !== 'red') {
                    return false;
                }
            }
            return true;
        })->values()->all();
    }
}
