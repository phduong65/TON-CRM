<?php

namespace App\Exports;

use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class ShiftSchedulesExport implements FromView, ShouldAutoSize, WithTitle
{
    public function __construct(
        private readonly Carbon $from,
        private readonly Carbon $to,
        private readonly string $rangeLabel,
        private readonly ?int $branchId = null,
        private readonly ?int $teamId = null,
        private readonly ?int $employeeId = null,
    ) {
    }

    public function view(): View
    {
        $schedules = ShiftSchedule::with(['employee.branch', 'employee.team', 'shift', 'attendanceLog', 'assignedBy:id,name'])
            ->whereBetween('work_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->where('status', 'scheduled')
            ->whereHas('employee', function ($q) {
                if ($this->branchId) {
                    $q->where('branch_id', $this->branchId);
                }
                if ($this->teamId) {
                    $q->where('team_id', $this->teamId);
                }
                if ($this->employeeId) {
                    $q->where('id', $this->employeeId);
                }
            })
            ->orderBy('work_date')
            ->orderBy('employee_id')
            ->get();

        return view('exports.shift-schedules', [
            'schedules'   => $schedules,
            'rangeLabel'  => $this->rangeLabel,
        ]);
    }

    public function title(): string
    {
        return 'Xếp ca';
    }
}
