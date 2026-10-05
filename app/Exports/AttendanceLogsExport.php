<?php

namespace App\Exports;

use App\Models\AttendanceLog;
use App\Support\Concerns\ResolvesPartialLeaveIndex;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class AttendanceLogsExport implements FromView, ShouldAutoSize, WithTitle
{
    use ResolvesPartialLeaveIndex;

    public function __construct(
        private readonly Request $request,
        private readonly string $rangeLabel,
    ) {
    }

    public function view(): View
    {
        // Sắp xếp theo chi nhánh → tên nhân viên → ngày để nhóm các lượt chấm công cùng chi
        // nhánh lại gần nhau, dễ nhìn và quản lý hơn khi xuất báo cáo nhiều nhân viên — thay vì
        // chỉ theo ngày như trước (xen kẽ nhân viên các chi nhánh khác nhau).
        $query = AttendanceLog::query()
            ->select('attendance_logs.*')
            ->leftJoin('employees', 'employees.id', '=', 'attendance_logs.employee_id')
            ->leftJoin('branches', 'branches.id', '=', 'employees.branch_id')
            ->with(['employee.branch', 'employee.team', 'employee.position', 'shiftSchedule.shift'])
            ->orderBy('branches.name')
            ->orderBy('employees.name')
            ->orderBy('attendance_logs.work_date');

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

        $logs = $query->get();

        return view('exports.attendance-logs', [
            'logs'              => $logs,
            'rangeLabel'        => $this->rangeLabel,
            'partialLeaveIndex' => $this->partialLeaveFractionIndex($logs),
        ]);
    }

    public function title(): string
    {
        return 'Chấm công';
    }
}
