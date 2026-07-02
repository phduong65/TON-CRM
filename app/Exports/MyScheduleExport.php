<?php

namespace App\Exports;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class MyScheduleExport implements FromView, ShouldAutoSize, WithTitle
{
    public function __construct(
        private readonly Employee $employee,
        private readonly Carbon $from,
        private readonly Carbon $to,
        private readonly string $rangeLabel,
    ) {
    }

    public function view(): View
    {
        $schedules = ShiftSchedule::with('shift')
            ->where('employee_id', $this->employee->id)
            ->where('status', 'scheduled')
            ->whereBetween('work_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->orderBy('work_date')
            ->get();

        $attendanceLogs = AttendanceLog::where('employee_id', $this->employee->id)
            ->whereBetween('work_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->get()
            ->keyBy(fn($log) => $log->work_date->toDateString());

        $leaveRequests = LeaveRequest::where('employee_id', $this->employee->id)
            ->where('status', 'approved')
            ->where('date_from', '<=', $this->to->toDateString())
            ->where('date_to', '>=', $this->from->toDateString())
            ->get();

        $today = now()->toDateString();
        $rows  = [];

        foreach ($schedules as $schedule) {
            $shift = $schedule->shift;
            if (!$shift) {
                continue;
            }

            $dateKey = $schedule->work_date->toDateString();
            $log     = $attendanceLogs->get($dateKey);

            if ($log && $log->check_in_at && $log->check_out_at) {
                $status = 'Đã hoàn thành';
            } elseif ($log && $log->check_in_at) {
                $status = 'Đang trong ca';
            } elseif ($dateKey < $today) {
                $status = 'Chưa chấm công';
            } else {
                $status = 'Sắp tới';
            }

            $rows[] = [
                'date'        => $schedule->work_date,
                'type'        => 'Ca làm việc',
                'label'       => $shift->name . ($shift->isWfh() ? ' (WFH)' : ''),
                'time'        => substr($shift->start_time, 0, 5) . '–' . substr($shift->end_time, 0, 5),
                'status'      => $status,
                'checkIn'     => $log?->check_in_at?->format('H:i:s'),
                'checkOut'    => $log?->check_out_at?->format('H:i:s'),
                'lateMinutes' => $log?->late_minutes ?? 0,
                'earlyMinutes'=> $log?->early_minutes ?? 0,
            ];
        }

        foreach ($leaveRequests as $leave) {
            $rows[] = [
                'date'        => $leave->date_from,
                'type'        => 'Nghỉ phép',
                'label'       => $leave->typeLabel() . ($leave->date_to->ne($leave->date_from) ? ' (đến ' . $leave->date_to->format('d/m/Y') . ')' : ''),
                'time'        => '—',
                'status'      => 'Đã duyệt',
                'checkIn'     => null,
                'checkOut'    => null,
                'lateMinutes' => 0,
                'earlyMinutes'=> 0,
            ];
        }

        usort($rows, fn($a, $b) => $a['date']->lte($b['date']) ? -1 : 1);

        return view('exports.my-schedule', [
            'rows'       => $rows,
            'employee'   => $this->employee,
            'rangeLabel' => $this->rangeLabel,
        ]);
    }

    public function title(): string
    {
        return 'Lịch làm việc';
    }
}
