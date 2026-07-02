<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Dựng dữ liệu cho "Bảng chấm công" (báo cáo công dạng lưới NV x ngày), theo
 * mẫu bảng công truyền thống: mỗi ô ngày là số "công" quy đổi từ giờ làm thực
 * tế trên giờ công chuẩn của ca (Shift::standard_work_hours) — trừ ca loại
 * fulltime/văn phòng (Shift::shift_type = fulltime) luôn tính flat 1 công cho
 * 1 ca chấm công đủ vào-ra, không quy đổi theo giờ — cộng các cột tổng hợp
 * (ngày công, nghỉ có/không lương, nghỉ lễ, đi trễ/về sớm, quên chấm công...).
 *
 * Các khái niệm hệ thống chưa lưu trữ (trạng thái Thử việc, tăng ca) được giữ
 * nguyên bố cục cột như file mẫu nhưng luôn trả về 0 — theo yêu cầu người
 * dùng khi xác nhận phạm vi tính năng.
 */
class AttendanceTimesheetBuilder
{
    public function build(Carbon $from, Carbon $to, ?int $branchId, ?int $teamId, ?int $employeeId): array
    {
        $days = collect();
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $days->push($d->copy());
        }

        $employees = Employee::with(['branch', 'team'])
            ->where('is_active', true)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($teamId, fn($q) => $q->where('team_id', $teamId))
            ->when($employeeId, fn($q) => $q->where('id', $employeeId))
            ->orderBy('name')
            ->get();

        $employeeIds = $employees->pluck('id');

        $schedulesByKey = ShiftSchedule::with('shift')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'scheduled')
            ->get()
            ->groupBy(fn($s) => $s->employee_id . '_' . $s->work_date->toDateString());

        $logsByKey = AttendanceLog::whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy(fn($l) => $l->employee_id . '_' . $l->work_date->toDateString());

        $leaveIndex   = $this->buildLeaveIndex($employeeIds, $from, $to);
        $holidayIndex = $this->buildHolidayIndex($from, $to);

        $rows = $employees->map(function (Employee $employee) use ($days, $schedulesByKey, $logsByKey, $leaveIndex, $holidayIndex) {
            return $this->buildEmployeeRow($employee, $days, $schedulesByKey, $logsByKey, $leaveIndex, $holidayIndex);
        })->values();

        return [
            'days'              => $days,
            'rows'              => $rows,
            'standard_workdays' => $this->standardWorkdaysInPeriod($from, $to),
        ];
    }

    /**
     * Map "employeeId_Y-m-d" => LeaveRequest đã duyệt phủ ngày đó (nếu có).
     */
    private function buildLeaveIndex(Collection $employeeIds, Carbon $from, Carbon $to): array
    {
        $leaves = LeaveRequest::whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->where('date_from', '<=', $to->toDateString())
            ->where('date_to', '>=', $from->toDateString())
            ->get();

        $index = [];
        foreach ($leaves as $leave) {
            $start = $leave->date_from->greaterThan($from) ? $leave->date_from->copy() : $from->copy();
            $end   = $leave->date_to->lessThan($to) ? $leave->date_to->copy() : $to->copy();

            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $index[$leave->employee_id . '_' . $d->toDateString()] = $leave;
            }
        }

        return $index;
    }

    /**
     * Map "Y-m-d" => Holiday đang hoạt động trong khoảng ngày báo cáo.
     */
    private function buildHolidayIndex(Carbon $from, Carbon $to): array
    {
        return Holiday::where('is_active', true)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn(Holiday $h) => $h->date->toDateString())
            ->all();
    }

    private function buildEmployeeRow(Employee $employee, Collection $days, Collection $schedulesByKey, Collection $logsByKey, array $leaveIndex, array $holidayIndex): array
    {
        $dayCells        = [];
        $totalWorkdays   = 0.0;
        $holidayWorkdays = 0.0;
        $paidLeaveDays   = 0;
        $unpaidLeaveDays = 0;
        $holidayDays     = 0;
        $holidayBonusAmount = 0.0;
        $lateCount       = 0;
        $earlyCount      = 0;
        $missingCheckInCount  = 0;
        $missingCheckOutCount = 0;

        foreach ($days as $day) {
            $key     = $employee->id . '_' . $day->toDateString();
            $leave   = $leaveIndex[$key] ?? null;
            $holiday = $holidayIndex[$day->toDateString()] ?? null;

            if ($leave) {
                $isUnpaid = $leave->type === 'unpaid';
                $dayCells[] = $isUnpaid ? '0, NK' : '0, NC';
                $isUnpaid ? $unpaidLeaveDays++ : $paidLeaveDays++;
                continue;
            }

            $daySchedules = $schedulesByKey->get($key, collect());
            $dayLogs      = $logsByKey->get($key, collect());

            if ($daySchedules->isEmpty() && $dayLogs->isEmpty()) {
                if ($holiday && $holiday->is_paid) {
                    $dayCells[] = '1, NL';
                    $holidayDays++;
                    $holidayBonusAmount += (float) ($holiday->bonus_amount ?? 0);
                } else {
                    $dayCells[] = '';
                }
                continue;
            }

            $workdayInDay = 0.0;

            if ($daySchedules->isNotEmpty()) {
                foreach ($daySchedules as $schedule) {
                    $log = $dayLogs->firstWhere('shift_schedule_id', $schedule->id);

                    if (!$log || !$log->check_in_at) {
                        $missingCheckInCount++;
                        continue;
                    }
                    if (!$log->check_out_at) {
                        $missingCheckOutCount++;
                        continue;
                    }

                    if ($log->full_credit) {
                        // Đi muộn/về sớm đã được duyệt "Công thường" — tính đủ công dù giờ chấm thực tế ngắn hơn.
                        $credit = 1.0;
                    } elseif ($schedule->shift?->isFulltimeCategory() ?? true) {
                        // Ca văn phòng/full-time: 1 ca chấm công đủ vào-ra = 1 công, không quy đổi theo giờ.
                        $credit = 1.0;
                    } else {
                        $standardHours = $schedule->shift->standardWorkHours();
                        $workedHours   = $log->check_in_at->diffInMinutes($log->check_out_at) / 60;
                        $credit        = round($workedHours / $standardHours, 2);
                    }

                    $workdayInDay += $credit;

                    if ($log->late_minutes > 0) {
                        $lateCount++;
                    }
                    if ($log->early_minutes > 0) {
                        $earlyCount++;
                    }
                }
            } else {
                // Chấm công ngoài lịch (không có ca xếp trước) — quy đổi theo giờ chuẩn mặc định.
                foreach ($dayLogs as $log) {
                    if ($log->check_in_at && $log->check_out_at) {
                        $workedHours   = $log->check_in_at->diffInMinutes($log->check_out_at) / 60;
                        $workdayInDay += $log->full_credit ? 1.0 : round($workedHours / 8.0, 2);

                        if ($log->late_minutes > 0) {
                            $lateCount++;
                        }
                        if ($log->early_minutes > 0) {
                            $earlyCount++;
                        }
                    }
                }
            }

            if ($holiday && $holiday->is_paid && $workdayInDay > 0) {
                // Đi làm đúng vào ngày nghỉ lễ — tính vào "Ngày công thực tế nghỉ lễ", không phải công thường.
                $holidayWorkdays += $workdayInDay;
                $dayCells[]       = $workdayInDay . ', NL';
            } else {
                $totalWorkdays += $workdayInDay;
                $dayCells[]     = $workdayInDay > 0 ? $workdayInDay : 0;
            }
        }

        $totalWorkdays   = round($totalWorkdays, 2);
        $holidayWorkdays = round($holidayWorkdays, 2);

        return [
            'employee'  => $employee,
            'day_cells' => $dayCells,
            'summary'   => [
                'actual_workdays'       => $totalWorkdays,
                'holiday_workdays'      => $holidayWorkdays,
                'total_actual_workdays' => round($totalWorkdays + $holidayWorkdays, 2),
                'paid_leave_days'       => $paidLeaveDays,
                'unpaid_leave_days'     => $unpaidLeaveDays,
                'holiday_days'          => $holidayDays,
                'payroll_workdays'      => round($totalWorkdays + $holidayWorkdays + $paidLeaveDays + $holidayDays, 2),
                'late_count'            => $lateCount,
                'early_count'           => $earlyCount,
                'missing_total'         => $missingCheckInCount + $missingCheckOutCount,
                'missing_check_in'      => $missingCheckInCount,
                'missing_check_out'     => $missingCheckOutCount,
                'overtime_shifts'       => 0,
                'overtime_hours'        => 0,
                'extra_hours'           => 0,
                'holiday_bonus_amount'  => $holidayBonusAmount,
            ],
        ];
    }

    /**
     * Số ngày công chuẩn của kỳ báo cáo — quy ước tuần làm 6 ngày (nghỉ Chủ nhật),
     * áp dụng chung cho mọi nhân viên trong bảng (giống cột "Công chuẩn" ở file mẫu).
     */
    private function standardWorkdaysInPeriod(Carbon $from, Carbon $to): int
    {
        $count = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if ($d->dayOfWeek !== Carbon::SUNDAY) {
                $count++;
            }
        }

        return $count;
    }
}
