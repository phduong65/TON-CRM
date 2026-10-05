<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyEmployeeScore;
use App\Models\Setting;
use Illuminate\Http\Request;

class ScoreController extends Controller
{
    /**
     * Điểm tháng hiện tại của chính nhân viên (khởi tạo record nếu chưa có, giống logic
     * MonthlyEmployeeScore::ensureExists dùng ở các luồng trừ/cộng điểm phía web).
     */
    public function current(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $record = MonthlyEmployeeScore::ensureExists($employee->id, (int) now()->month, (int) now()->year);

        return response()->json([
            'month'           => $record->month,
            'year'            => $record->year,
            'initial_score'   => $record->initial_score,
            'deducted_points' => $record->deducted_points,
            'rewarded_points' => $record->rewarded_points,
            'surplus_points'  => $record->surplus_points,
            'final_score'     => $record->final_score,
            'zone'            => $record->zone,
            'zone_label'      => MonthlyEmployeeScore::zoneLabel($record->zone),
        ]);
    }

    /**
     * Lịch sử điểm theo tháng của chính nhân viên (mặc định 12 tháng gần nhất).
     */
    public function history(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $limit = min(24, max(1, (int) $request->input('limit', 12)));

        $records = MonthlyEmployeeScore::where('employee_id', $employee->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit($limit)
            ->get()
            ->map(fn(MonthlyEmployeeScore $r) => [
                'month'           => $r->month,
                'year'            => $r->year,
                'deducted_points' => $r->deducted_points,
                'rewarded_points' => $r->rewarded_points,
                'surplus_points'  => $r->surplus_points,
                'final_score'     => $r->final_score,
                'zone'            => $r->zone,
                'zone_label'      => MonthlyEmployeeScore::zoneLabel($r->zone),
            ]);

        return response()->json(['data' => $records]);
    }

    /**
     * Xếp hạng cá nhân trong tháng đang xem (mặc định tháng hiện tại) — vị trí của chính nhân
     * viên trong bảng xếp hạng toàn công ty, không trả về toàn bộ danh sách nhân viên khác.
     */
    public function ranking(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(!$employee, 403, 'Tài khoản của bạn chưa được gắn với hồ sơ nhân viên.');

        $month = (int) ($request->input('month') ?? now()->month);
        $year  = (int) ($request->input('year') ?? now()->year);
        $defaultScore = (int) Setting::getValue('default_score_per_month', 100);

        $scoreMap = MonthlyEmployeeScore::where('month', $month)->where('year', $year)->get()->keyBy('employee_id');

        $ranked = \App\Models\Employee::where('is_active', true)
            ->whereDoesntHave('user', fn($q) => $q->whereHas('roles', fn($r) => $r->whereIn('name', ['director', 'admin'])))
            ->get()
            ->map(function ($emp) use ($scoreMap, $defaultScore) {
                $record = $scoreMap->get($emp->id);
                $emp->display_score  = $record ? $record->final_score : $defaultScore;
                $emp->surplus_points = $record ? $record->surplus_points : 0;
                return $emp;
            })
            ->sortBy([['display_score', 'desc'], ['surplus_points', 'desc']])
            ->values();

        [$rank, $prevScore, $prevSurplus, $count, $myRank, $myScore] = [0, null, null, 0, null, null];
        foreach ($ranked as $emp) {
            $count++;
            if ($emp->display_score !== $prevScore || $emp->surplus_points !== $prevSurplus) {
                $rank = $count;
            }
            if ($emp->id === $employee->id) {
                $myRank  = $rank;
                $myScore = $emp->display_score;
            }
            $prevScore   = $emp->display_score;
            $prevSurplus = $emp->surplus_points;
        }

        return response()->json([
            'month'          => $month,
            'year'           => $year,
            'rank'           => $myRank,
            'total_employees' => $ranked->count(),
            'score'          => $myScore,
        ]);
    }
}
