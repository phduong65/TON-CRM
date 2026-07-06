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
 * Dựng dữ liệu cho "Bảng chấm công" (báo cáo công dạng lưới NV x ngày). Công thức quy đổi
 * giờ làm thực tế sang "công" cho từng lượt chấm công nằm ở AttendanceLog::computeCong() —
 * dùng chung với cột "Công" ở Báo cáo chấm công (attendance-logs) để 2 màn hình luôn khớp
 * nhau — cộng thêm các cột tổng hợp (ngày công, nghỉ có/không lương, nghỉ lễ, đi trễ/về
 * sớm, quên chấm công...).
 *
 * Tăng ca (overtime_shifts/overtime_hours/extra_hours) lấy từ AttendanceLog::overtime_hours —
 * cộng dồn khi duyệt yêu cầu "Tăng ca" (StaffRequestsController::applyOvertime()). Trạng thái
 * Thử việc thì hệ thống chưa lưu trữ, vẫn giữ nguyên bố cục cột "Chính thức/Thử việc" như file
 * mẫu nhưng cột "Thử việc" luôn trả về 0.
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
        $overtimeShiftDays    = 0;
        $overtimeHoursTotal   = 0.0;

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

            $workdayInDay  = 0.0;
            $dayHasOvertime = false;

            if ($daySchedules->isNotEmpty()) {
                foreach ($daySchedules as $schedule) {
                    $log = $dayLogs->firstWhere('shift_schedule_id', $schedule->id);

                    // Giờ tăng ca đã duyệt (nếu có) vẫn được cộng công dù thiếu chấm công vào/ra —
                    // xem StaffRequestsController::applyOvertime().
                    $overtimeHours = (float) ($log?->overtime_hours ?? 0);
                    if ($overtimeHours > 0) {
                        $overtimeHoursTotal += $overtimeHours;
                        $dayHasOvertime = true;
                    }

                    if (!$log || !$log->check_in_at) {
                        $missingCheckInCount++;
                        $workdayInDay += $log?->overtimeCong($schedule->shift) ?? 0;
                        continue;
                    }
                    if (!$log->check_out_at) {
                        $missingCheckOutCount++;
                        $workdayInDay += $log->overtimeCong($schedule->shift);
                        continue;
                    }

                    // Dùng chung công thức với Báo cáo chấm công (AttendanceLog::computeCong()) —
                    // truyền sẵn $schedule->shift (đã eager-load) để tránh lazy-load quan hệ.
                    // computeCong() đã bao gồm phần công quy đổi từ overtime_hours ở trên.
                    $workdayInDay += $log->computeCong($schedule->shift);

                    if ($log->late_minutes > 0) {
                        $lateCount++;
                    }
                    if ($log->early_minutes > 0) {
                        $earlyCount++;
                    }
                }
            } else {
                // Chấm công ngoài lịch (không có ca xếp trước) — quy đổi theo giờ chuẩn mặc định.
                // Bao gồm cả log chỉ chứa giờ tăng ca (tăng ca vào ngày nghỉ/không có ca).
                foreach ($dayLogs as $log) {
                    $overtimeHours = (float) $log->overtime_hours;
                    if ($overtimeHours > 0) {
                        $overtimeHoursTotal += $overtimeHours;
                        $dayHasOvertime = true;
                    }

                    if ($log->check_in_at && $log->check_out_at) {
                        $workdayInDay += $log->computeCong();

                        if ($log->late_minutes > 0) {
                            $lateCount++;
                        }
                        if ($log->early_minutes > 0) {
                            $earlyCount++;
                        }
                    } else {
                        $workdayInDay += $log->overtimeCong();
                    }
                }
            }

            if ($dayHasOvertime) {
                $overtimeShiftDays++;
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
                'overtime_shifts'       => $overtimeShiftDays,
                'overtime_hours'        => round($overtimeHoursTotal, 2),
                'extra_hours'           => round($overtimeHoursTotal, 2),
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
