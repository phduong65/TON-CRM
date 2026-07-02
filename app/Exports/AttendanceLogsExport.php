<?php

namespace App\Exports;

use App\Models\AttendanceLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class AttendanceLogsExport implements FromView, ShouldAutoSize, WithTitle
{
    public function __construct(
        private readonly Request $request,
        private readonly string $rangeLabel,
    ) {
    }

    public function view(): View
    {
        $query = AttendanceLog::with(['employee.branch', 'employee.team', 'shiftSchedule.shift'])
            ->orderBy('work_date')
            ->orderBy('employee_id');

        if ($this->request->filled('branch_id')) {
            $query->whereHas('employee', fn($q) => $q->where('branch_id', $this->request->branch_id));
        }
        if ($this->request->filled('team_id')) {
            $query->whereHas('employee', fn($q) => $q->where('team_id', $this->request->team_id));
        }
        if ($this->request->filled('employee_id')) {
            $query->where('employee_id', $this->request->employee_id);
        }
        if ($this->request->filled('date_from')) {
            $query->whereDate('work_date', '>=', $this->request->date_from);
        }
        if ($this->request->filled('date_to')) {
            $query->whereDate('work_date', '<=', $this->request->date_to);
        }

        return view('exports.attendance-logs', [
            'logs'       => $query->get(),
            'rangeLabel' => $this->rangeLabel,
        ]);
    }

    public function title(): string
    {
        return 'Chấm công';
    }
}
