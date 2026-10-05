<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\TimesheetConfirmation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tổng hợp bảng công tháng cho tính năng "Xác nhận công" (dùng chung
 * AttendanceTimesheetBuilder — nguồn dữ liệu duy nhất cho số ngày làm/giờ làm/đi trễ/nghỉ
 * có-không lương, để luôn khớp với Báo cáo chấm công và Bảng chấm công Excel) và quản lý
 * trạng thái xác nhận (tự xác nhận / xác nhận hộ / huỷ xác nhận / tự động reset).
 */
class TimesheetConfirmationService
{
    public function __construct(private readonly AttendanceTimesheetBuilder $builder)
    {
    }

    public function summaryFor(Employee $employee, int $month, int $year): array
    {
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to   = $from->copy()->endOfMonth();

        $built = $this->builder->build($from, $to, null, null, $employee->id);
        $row   = $built['rows']->first();

        $lateMinutesTotal  = (int) AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->sum('late_minutes');
        $earlyMinutesTotal = (int) AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->sum('early_minutes');

        $leaveDetail = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('date_from', '<=', $to->toDateString())
            ->where('date_to', '>=', $from->toDateString())
            ->orderBy('date_from')
            ->get()
            ->map(fn (LeaveRequest $leave) => [
                'date_from'     => $leave->date_from,
                'date_to'       => $leave->date_to,
                'type'          => $leave->type,
                'type_label'    => $leave->typeLabel(),
                'is_paid'       => LeaveRequest::isPaidType($leave->type),
                'days'          => $leave->daysCount(),
                'is_partial_day' => $leave->is_partial_day,
                'partial_label' => $leave->partialDayLabel(),
            ]);

        $logsByDate = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->with('shiftSchedule.shift')
            // Sắp theo giờ vào thực tế để ngày có nhiều ca (đa ca) hiển thị ĐÚNG thứ tự thời gian
            // (ca sớm hơn đứng trước) — nếu không, thứ tự mặc định theo id/created_at có thể sai
            // (VD ca tối check-in trước ca sáng của cùng ngày do được chấm công muộn hơn thực tế).
            // Bản ghi thiếu check-in (chưa chấm công) xếp cuối cùng thay vì lên đầu.
            ->orderByRaw('check_in_at IS NULL, check_in_at')
            ->get()
            ->groupBy(fn (AttendanceLog $log) => $log->work_date->toDateString());

        $confirmation = TimesheetConfirmation::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        return [
            'from'               => $from,
            'to'                 => $to,
            'days'               => $built['days'],
            'day_cells'          => $row['day_cells'] ?? [],
            'day_cells_hours'    => $row['day_cells_hours'] ?? [],
            'logs_by_date'       => $logsByDate,
            'summary'            => $row['summary'] ?? null,
            'late_minutes_total' => $lateMinutesTotal,
            'early_minutes_total' => $earlyMinutesTotal,
            'leave_detail'       => $leaveDetail,
            'confirmation'       => $confirmation,
        ];
    }

    public function confirm(Employee $employee, int $month, int $year, User $actor, bool $isProxy): TimesheetConfirmation
    {
        return DB::transaction(function () use ($employee, $month, $year, $actor, $isProxy) {
            $confirmation = $this->lockedRow($employee->id, $month, $year)
                ?? new TimesheetConfirmation([
                    'employee_id' => $employee->id,
                    'month'       => $month,
                    'year'        => $year,
                ]);

            $confirmation->fill([
                'status'             => 'confirmed',
                'confirmed_by'       => $actor->id,
                'confirmed_at'       => now(),
                'is_proxy_confirmed' => $isProxy,
            ])->save();

            return $confirmation;
        });
    }

    public function unconfirm(Employee $employee, int $month, int $year): TimesheetConfirmation
    {
        return DB::transaction(function () use ($employee, $month, $year) {
            $confirmation = $this->lockedRow($employee->id, $month, $year)
                ?? new TimesheetConfirmation([
                    'employee_id' => $employee->id,
                    'month'       => $month,
                    'year'        => $year,
                ]);

            $confirmation->fill([
                'status'             => 'pending',
                'confirmed_by'       => null,
                'confirmed_at'       => null,
                'is_proxy_confirmed' => false,
            ])->save();

            return $confirmation;
        });
    }

    /**
     * Gọi từ AttendanceLogObserver khi 1 bản ghi chấm công của nhân viên bị tạo/sửa/xoá —
     * nếu tháng đó đã được xác nhận thì reset về "chưa xác nhận" để không hiển thị công
     * đã xác nhận nhưng thực ra dữ liệu vừa thay đổi. No-op nếu chưa từng xác nhận, tránh
     * tạo bản ghi 'pending' rác cho những tháng chưa ai đụng tới.
     */
    public function resetIfConfirmed(int $employeeId, int $month, int $year): void
    {
        DB::transaction(function () use ($employeeId, $month, $year) {
            $confirmation = $this->lockedRow($employeeId, $month, $year);

            if ($confirmation && $confirmation->status === 'confirmed') {
                $confirmation->update([
                    'status'             => 'pending',
                    'confirmed_by'       => null,
                    'confirmed_at'       => null,
                    'is_proxy_confirmed' => false,
                ]);
            }
        });
    }

    private function lockedRow(int $employeeId, int $month, int $year): ?TimesheetConfirmation
    {
        return TimesheetConfirmation::where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();
    }
}
