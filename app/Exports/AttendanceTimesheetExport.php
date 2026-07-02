<?php

namespace App\Exports;

use App\Services\AttendanceTimesheetBuilder;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class AttendanceTimesheetExport implements FromView, ShouldAutoSize, WithTitle
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
        $data = (new AttendanceTimesheetBuilder())->build(
            $this->from,
            $this->to,
            $this->branchId,
            $this->teamId,
            $this->employeeId,
        );

        return view('exports.attendance-timesheet', [
            'days'             => $data['days'],
            'rows'             => $data['rows'],
            'standardWorkdays' => $data['standard_workdays'],
            'rangeLabel'       => $this->rangeLabel,
        ]);
    }

    public function title(): string
    {
        return 'Bảng chấm công';
    }
}
