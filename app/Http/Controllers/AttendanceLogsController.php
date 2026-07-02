<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceLogsExport;
use App\Exports\AttendanceTimesheetExport;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Team;
use App\Support\Concerns\ResolvesExportDateRange;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceLogsController extends Controller
{
    use ResolvesExportDateRange;

    public function index(Request $request)
    {
        $query = AttendanceLog::with(['employee.branch', 'employee.team', 'shiftSchedule.shift'])
            ->orderByDesc('work_date');

        if ($request->filled('branch_id')) {
            $query->whereHas('employee', fn($q) => $q->where('branch_id', $request->branch_id));
        }

        if ($request->filled('team_id')) {
            $query->whereHas('employee', fn($q) => $q->where('team_id', $request->team_id));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('work_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('work_date', '<=', $request->date_to);
        }

        $logs      = $query->paginate(20)->withQueryString();
        $branches  = Branch::where('is_active', true)->orderBy('name')->get();
        $teams     = Team::where('is_active', true)->orderBy('name')->get();
        $employees = Employee::where('is_active', true)->orderBy('name')->get();

        return view('attendance-logs.index', compact('logs', 'branches', 'teams', 'employees'));
    }

    /**
     * Xuất Excel báo cáo chấm công — áp dụng đúng bộ lọc hiện tại của trang
     * (chi nhánh/đội nhóm/nhân viên/khoảng ngày), không phân trang.
     */
    public function export(Request $request)
    {
        $label = $request->filled('date_from') || $request->filled('date_to')
            ? ($request->date_from ?: '...') . ' – ' . ($request->date_to ?: '...')
            : 'Toàn bộ thời gian';

        $filename = 'bao-cao-cham-cong_' . now()->format('Ymd_His') . '.xlsx';

        activity()->causedBy(auth()->user())
            ->inLog('attendance')
            ->withProperties(['range' => $label])
            ->log('Xuất Excel báo cáo chấm công');

        return Excel::download(new AttendanceLogsExport($request, $label), $filename);
    }

    /**
     * Xuất "Bảng chấm công" dạng lưới NV x ngày (tương tự bảng công truyền thống) —
     * theo tuần / theo tháng / theo khoảng ngày tùy chọn (xem ResolvesExportDateRange).
     */
    public function exportTimesheet(Request $request)
    {
        [$from, $to, $label] = $this->resolveExportDateRange($request);

        $export = new AttendanceTimesheetExport(
            $from,
            $to,
            $label,
            $request->integer('branch_id') ?: null,
            $request->integer('team_id') ?: null,
            $request->integer('employee_id') ?: null,
        );

        $filename = 'bang-cham-cong_' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.xlsx';

        activity()->causedBy(auth()->user())
            ->inLog('attendance')
            ->withProperties(['range' => $label])
            ->log('Xuất Excel bảng chấm công');

        return Excel::download($export, $filename);
    }
}
