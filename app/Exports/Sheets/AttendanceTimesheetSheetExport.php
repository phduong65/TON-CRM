<?php

namespace App\Exports\Sheets;

use App\Services\AttendanceTimesheetBuilder;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class AttendanceTimesheetSheetExport implements FromView, ShouldAutoSize, WithTitle
{
    public function __construct(
        private readonly Carbon $from,
        private readonly Carbon $to,
        private readonly string $rangeLabel,
        private readonly ?int $branchId = null,
        private readonly ?int $teamId = null,
        private readonly ?int $employeeId = null,
        private readonly string|array|null $employmentTypes = null,
        private readonly string $sheetTitle = 'Bảng chấm công',
        private readonly bool $isPartTime = false,
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
            $this->employmentTypes
        );

        return view('exports.attendance-timesheet', [
            'days'       => $data['days'],
            'rows'       => $data['rows'],
            'rangeLabel' => $this->rangeLabel,
            'isPartTime' => $this->isPartTime,
        ]);
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }
}
