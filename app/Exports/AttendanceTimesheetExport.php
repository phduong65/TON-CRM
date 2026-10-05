<?php

namespace App\Exports;

use App\Exports\Sheets\AttendanceTimesheetSheetExport;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AttendanceTimesheetExport implements WithMultipleSheets
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

    public function sheets(): array
    {
        return [
            new AttendanceTimesheetSheetExport(
                $this->from,
                $this->to,
                $this->rangeLabel,
                $this->branchId,
                $this->teamId,
                $this->employeeId,
                ['full_time', 'intern'],
                'Nhân viên Full-time',
                false
            ),
            new AttendanceTimesheetSheetExport(
                $this->from,
                $this->to,
                $this->rangeLabel,
                $this->branchId,
                $this->teamId,
                $this->employeeId,
                'part_time',
                'Nhân viên Part-time',
                true
            ),
        ];
    }
}
