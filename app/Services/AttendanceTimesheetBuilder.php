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
 * cộng dồn khi duyệt yêu cầu "Tăng ca" (StaffRequestsController::applyOvertime()).
 */
class AttendanceTimesheetBuilder
{
    public function build(Carbon $from, Carbon $to, ?int $branchId, ?int $teamId, ?int $employeeId, string|array|null $employmentTypes = null): array
    {
        $days = collect();
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $days->push($d->copy());
        }

        // Sắp xếp theo chi nhánh → tên để nhóm nhân viên cùng chi nhánh lại gần nhau trong bảng
        // — cùng nguyên tắc với Báo cáo chấm công (AttendanceLogsExport).
        $employees = Employee::query()
            ->select('employees.*')
            ->leftJoin('branches', 'branches.id', '=', 'employees.branch_id')
            ->with(['branch', 'team', 'position'])
            ->where('employees.is_active', true)
            ->when($branchId, fn($q) => $q->where('employees.branch_id', $branchId))
            ->when($teamId, fn($q) => $q->where('employees.team_id', $teamId))
            ->when($employeeId, fn($q) => $q->where('employees.id', $employeeId))
            ->when($employmentTypes, function ($q) use ($employmentTypes) {
                if (is_array($employmentTypes)) {
                    $q->whereIn('employees.employment_type', $employmentTypes);
                } else {
                    $q->where('employees.employment_type', $employmentTypes);
                }
            })
            ->orderBy('branches.name')
            ->orderBy('employees.name')
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
            'days' => $days,
            'rows' => $rows,
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
        return Holiday::with('teams:id')
            ->where('is_active', true)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn(Holiday $h) => $h->date->toDateString())
            ->all();
    }

    /**
     * Ngày lễ có áp dụng cho nhân viên này không — theo phạm vi (toàn bộ / bộ phận). Dùng để khối
     * văn phòng được nghỉ lễ trong khi tuyến nhà hàng (không thuộc phạm vi) vẫn tính ngày làm bình thường.
     */
    private function holidayAppliesTo(Holiday $holiday, Employee $employee): bool
    {
        if ($holiday->applies_to_all) {
            return true;
        }

        return $employee->team_id !== null && $holiday->teams->contains('id', $employee->team_id);
    }

    private function buildEmployeeRow(Employee $employee, Collection $days, Collection $schedulesByKey, Collection $logsByKey, array $leaveIndex, array $holidayIndex): array
    {
        $dayCells        = [];
        $dayCellsHours   = [];
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
        $workedHoursTotal     = 0.0;

        foreach ($days as $day) {
            $key       = $employee->id . '_' . $day->toDateString();
            $leave     = $leaveIndex[$key] ?? null;
            $rawHoliday = $holidayIndex[$day->toDateString()] ?? null;
            // Chỉ coi là ngày lễ với NV nếu lễ áp dụng cho bộ phận của họ (office nghỉ, nhà hàng làm).
            $holiday   = ($rawHoliday && $this->holidayAppliesTo($rawHoliday, $employee)) ? $rawHoliday : null;

            // Nghỉ cả ngày: không có ca (đã bị huỷ khi duyệt đơn) nên không có gì để chấm công —
            // giữ nguyên hành vi cũ, tính trọn 1 ngày nghỉ.
            // Chỉ "annual" (phép năm) được tính có lương (NC) — mọi loại còn lại (kể cả dữ liệu
            // cũ "sick"/"other" trước khi 2 loại này bị bỏ khỏi hệ thống) đều tính không lương (NK).
            if ($leave && !$leave->is_partial_day) {
                $isUnpaid = !LeaveRequest::isPaidType($leave->type);
                $dayCells[] = $isUnpaid ? '0, NK' : '0, NC';
                $dayCellsHours[] = $isUnpaid ? '0, NK' : '0, NC';
                $isUnpaid ? $unpaidLeaveDays++ : $paidLeaveDays++;
                continue;
            }

            $daySchedules = $schedulesByKey->get($key, collect());
            $dayLogs      = $logsByKey->get($key, collect());

            // Nghỉ theo giờ: ca vẫn còn (chỉ bị điều chỉnh khung giờ, xem ShiftSchedule::effectiveShift())
            // nên vẫn tính công phần đã chấm công thực tế, CỘNG THÊM phần ngày phép tương ứng —
            // không dùng computeCong() bình thường (sẽ tính đủ 1 công cho ca fulltime dù chỉ làm
            // nửa ca) mà cố định phần công = 1 - day_fraction để cộng đúng với phần ngày phép.
            if ($leave && $leave->is_partial_day) {
                $leaveFraction = (float) ($leave->day_fraction ?? 0);
                // Chỉ "annual" (phép năm) được tính có lương (NC) — xem giải thích ở nhánh nghỉ cả ngày trên.
                $isUnpaid      = !LeaveRequest::isPaidType($leave->type);
                $isUnpaid ? $unpaidLeaveDays += $leaveFraction : $paidLeaveDays += $leaveFraction;

                $log = $dayLogs->firstWhere('shift_schedule_id', $leave->shift_schedule_id);
                $workCredit = ($log && $log->check_in_at && $log->check_out_at)
                    ? round(1 - $leaveFraction, 2)
                    : 0.0;
                
                $scheduleForLeave = $daySchedules->firstWhere('id', $leave->shift_schedule_id);
                $workedHoursInDay = 0.0;
                if ($log && $log->check_in_at && $log->check_out_at) {
                    $workedHoursInDay += $log->netWorkedHours($scheduleForLeave?->shift) ?? 0;
                }

                if ($log?->late_minutes > 0) {
                    $lateCount++;
                }
                if ($log?->early_minutes > 0) {
                    $earlyCount++;
                }

                // Đa ca: nghỉ theo giờ chỉ gắn với 1 ca (shift_schedule_id) — các ca CÒN LẠI trong
                // cùng ngày (nếu có) vẫn phải tính công bình thường qua computeCong(), nếu không sẽ
                // bị bỏ sót hoàn toàn (xem lỗi tương tự đã sửa ở ResolvesPartialLeaveIndex).
                $otherSchedules   = $daySchedules->reject(fn($s) => $s->id === $leave->shift_schedule_id);
                $otherWorkday     = 0.0;
                $otherHasOvertime = false;
                foreach ($otherSchedules as $schedule) {
                    $otherLog = $dayLogs->firstWhere('shift_schedule_id', $schedule->id);

                    $overtimeHours = (float) ($otherLog?->overtime_hours ?? 0);
                    if ($overtimeHours > 0) {
                        $overtimeHoursTotal += $overtimeHours;
                        $otherHasOvertime = true;
                    }

                    if (!$otherLog || !$otherLog->check_in_at) {
                        $missingCheckInCount++;
                        $otherWorkday += $otherLog?->overtimeCong($schedule->shift) ?? 0;
                        continue;
                    }
                    if (!$otherLog->check_out_at) {
                        $missingCheckOutCount++;
                        $otherWorkday += $otherLog->overtimeCong($schedule->shift);
                        continue;
                    }

                    $otherWorkday += $otherLog->computeCong($schedule->shift);
                    $hrs = $otherLog->netWorkedHours($schedule->shift) ?? 0;
                    $workedHoursTotal += $hrs;
                    $workedHoursInDay += $hrs;

                    if ($otherLog->late_minutes > 0) {
                        $lateCount++;
                    }
                    if ($otherLog->early_minutes > 0) {
                        $earlyCount++;
                    }
                }
                if ($otherHasOvertime) {
                    $overtimeShiftDays++;
                }

                $totalCredit = round($workCredit + $otherWorkday, 2);

                $leaveTag = $isUnpaid ? 'NK' : 'NC';
                $dayCells[] = "{$totalCredit}, {$leaveTag}+" . round($leaveFraction, 2);
                $dayCellsHours[] = round($workedHoursInDay, 2) . ", {$leaveTag}+" . round($leaveFraction, 2);

                if ($holiday && $holiday->is_paid && $totalCredit > 0) {
                    $holidayWorkdays += $totalCredit;
                } else {
                    $totalWorkdays += $totalCredit;
                }

                continue;
            }

            // Ngày nghỉ lễ (auto): NV được nghỉ đã có bản ghi chấm công nghỉ lễ (source='holiday') do
            // HolidayApplicationService tạo — ca đã bị huỷ. Tính "1, NL" (có lương) + thưởng, bỏ qua
            // xử lý chấm công thường. NV ĐI LÀM ngày lễ KHÔNG có bản ghi này -> đi vào nhánh thường và
            // được cộng "công ngày lễ" từ lượt chấm công thật (xem cuối vòng lặp).
            $holidayLog = $dayLogs->first(fn(AttendanceLog $l) => $l->holiday_id !== null);
            if ($holiday && $holidayLog) {
                if ($holiday->is_paid && !$this->isWeeklyRestDay($day, $employee->is_office)) {
                    $dayCells[]      = '1, NL';
                    $dayCellsHours[] = '0, NL';
                    $holidayDays++;
                    $holidayBonusAmount += (float) ($holiday->bonus_amount ?? 0);
                } else {
                    $dayCells[]      = '';
                    $dayCellsHours[] = '';
                }
                continue;
            }

            if ($daySchedules->isEmpty() && $dayLogs->isEmpty()) {
                // Không cộng "Nghỉ lễ" nếu ngày lễ trùng đúng ngày nghỉ hàng tuần sẵn có của nhân
                // viên (VD lễ rơi vào Chủ nhật, hoặc Thứ 7 với NV văn phòng) — vốn dĩ họ không
                // phải đi làm ngày đó nên không tính thêm công, tránh cộng trùng.
                if ($holiday && $holiday->is_paid && !$this->isWeeklyRestDay($day, $employee->is_office)) {
                    $dayCells[] = '1, NL';
                    $dayCellsHours[] = '0, NL';
                    $holidayDays++;
                    $holidayBonusAmount += (float) ($holiday->bonus_amount ?? 0);
                } else {
                    $dayCells[] = '';
                    $dayCellsHours[] = '';
                }
                continue;
            }

            $workdayInDay  = 0.0;
            $workedHoursInDay = 0.0;
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
                    $hrs = $log->netWorkedHours($schedule->shift) ?? 0;
                    $workedHoursTotal += $hrs;
                    $workedHoursInDay += $hrs;

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
                        $hrs = $log->netWorkedHours() ?? 0;
                        $workedHoursTotal += $hrs;
                        $workedHoursInDay += $hrs;

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
                $dayCellsHours[]  = round($workedHoursInDay, 2) . ', NL';
            } else {
                $totalWorkdays += $workdayInDay;
                $dayCells[]     = $workdayInDay > 0 ? $workdayInDay : 0;
                $dayCellsHours[] = $workedHoursInDay > 0 ? round($workedHoursInDay, 2) : 0;
            }
        }

        $totalWorkdays   = round($totalWorkdays, 2);
        $holidayWorkdays = round($holidayWorkdays, 2);
        $paidLeaveDays   = round($paidLeaveDays, 2);
        $unpaidLeaveDays = round($unpaidLeaveDays, 2);

        return [
            'employee'  => $employee,
            'day_cells' => $dayCells,
            'day_cells_hours' => $dayCellsHours,
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
                'worked_hours'          => round($workedHoursTotal, 2),
                'holiday_bonus_amount'  => $holidayBonusAmount,
                'standard_workdays'     => $this->standardWorkdaysInPeriod($days->first(), $days->last(), $employee->is_office),
            ],
        ];
    }

    /**
     * Số ngày công chuẩn của kỳ báo cáo — tính RIÊNG theo từng nhân viên vì lịch nghỉ hằng tuần
     * khác nhau giữa khối văn phòng và vận hành: NV văn phòng (is_office=true) nghỉ cả Thứ 7 +
     * Chủ nhật (tuần làm 5 ngày); các NV còn lại (nhà hàng/bar/bếp...) chỉ nghỉ Chủ nhật (tuần
     * làm 6 ngày). Trước đây dùng chung 1 con số cho mọi nhân viên — sai cho khối văn phòng.
     */
    private function standardWorkdaysInPeriod(Carbon $from, Carbon $to, bool $isOffice): int
    {
        $count = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if (!$this->isWeeklyRestDay($d, $isOffice)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Ngày nghỉ hàng tuần cố định theo loại nhân viên — dùng chung cho cả "Công chuẩn" và việc
     * chặn cộng trùng "Nghỉ lễ" khi ngày lễ rơi đúng vào ngày nghỉ hàng tuần sẵn có (xem
     * buildEmployeeRow()).
     */
    private function isWeeklyRestDay(Carbon $day, bool $isOffice): bool
    {
        return $isOffice
            ? in_array($day->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true)
            : $day->dayOfWeek === Carbon::SUNDAY;
    }
}
