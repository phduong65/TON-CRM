<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\Carbon;

/**
 * Phép năm: chỉ NV chính thức + văn phòng (Employee::isEligibleForAnnualLeave), cộng dồn
 * 1 ngày/tháng làm việc trọn vẹn, tối đa 12 ngày/năm — tính trực tiếp từ dữ liệu nguồn
 * (joined_at + LeaveRequest đã duyệt), không lưu sổ cộng dồn riêng.
 */
class AnnualLeaveService
{
    public function entitledDays(Employee $employee, int $year): float
    {
        if (!$employee->isEligibleForAnnualLeave()) {
            return 0.0;
        }

        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd   = Carbon::create($year, 12, 31)->endOfDay();

        $start = $employee->joined_at && $employee->joined_at->greaterThan($yearStart)
            ? $employee->joined_at->copy()
            : $yearStart;

        $end = now()->lessThan($yearEnd) ? now() : $yearEnd;

        if ($end->lessThan($start)) {
            return 0.0;
        }

        $completedMonths = (int) floor($start->diffInMonths($end));

        return (float) min(12, max(0, $completedMonths));
    }

    public function usedDays(Employee $employee, int $year): float
    {
        return (float) LeaveRequest::where('employee_id', $employee->id)
            ->where('type', 'annual')
            ->where('status', 'approved')
            ->whereYear('date_from', $year)
            ->get()
            ->sum(fn (LeaveRequest $lr) => $lr->daysCount());
    }

    public function remainingDays(Employee $employee, ?int $year = null): float
    {
        $year ??= (int) now()->year;

        return round($this->entitledDays($employee, $year) - $this->usedDays($employee, $year), 2);
    }
}
