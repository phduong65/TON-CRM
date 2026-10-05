<?php

namespace App\Http\Controllers;

use App\Models\AttendanceAlert;
use App\Models\Branch;
use App\Models\Team;
use App\Services\AttendanceAlertService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceAlertsController extends Controller
{
    public function __construct(protected AttendanceAlertService $alertService)
    {
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $teams = Team::where('is_active', true)->orderBy('name')->get();

        $query = AttendanceAlert::with([
            'employee.team',
            'employee.branch',
            'shiftSchedule.shift',
            'shiftSchedule.branch',
            'resolvedBy',
        ]);

        // Phân quyền chi nhánh / team
        if ($user->hasRole('manager') && $user->employee?->branch_id) {
            $query->whereHas('employee', fn($eq) => $eq->where('branch_id', $user->employee->branch_id));
        } elseif ($user->hasRole('team_leader') && $user->employee?->team_id) {
            $query->whereHas('employee', fn($eq) => $eq->where('team_id', $user->employee->team_id));
        }

        // Bộ lọc chi nhánh
        if ($request->filled('branch_id')) {
            $query->whereHas('employee', fn($eq) => $eq->where('branch_id', $request->branch_id));
        }

        // Bộ lọc bộ phận
        if ($request->filled('team_id')) {
            $query->whereHas('employee', fn($eq) => $eq->where('team_id', $request->team_id));
        }

        // Bộ lọc trạng thái
        $status = $request->input('status', 'unresolved');
        if ($status === 'unresolved') {
            $query->whereIn('status', ['open', 'seen']);
        } elseif ($status !== 'all' && in_array($status, ['open', 'seen', 'resolved', 'excused'])) {
            $query->where('status', $status);
        }

        // Bộ lọc loại cảnh báo
        if ($request->filled('alert_type') && in_array($request->alert_type, ['missing_check_in', 'missing_check_out'])) {
            $query->where('alert_type', $request->alert_type);
        }

        // Bộ lọc khoảng ngày
        if ($request->filled('date_from')) {
            $query->whereDate('triggered_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('triggered_at', '<=', $request->date_to);
        }

        // Tìm kiếm theo tên / mã nhân viên
        if ($request->filled('search')) {
            $kw = $request->search;
            $query->whereHas('employee', function ($eq) use ($kw) {
                $eq->where('name', 'like', "%{$kw}%")
                    ->orWhere('code', 'like', "%{$kw}%");
            });
        }

        // KPI Counts
        $statsQuery = AttendanceAlert::query();
        if ($user->hasRole('manager') && $user->employee?->branch_id) {
            $statsQuery->whereHas('employee', fn($eq) => $eq->where('branch_id', $user->employee->branch_id));
        } elseif ($user->hasRole('team_leader') && $user->employee?->team_id) {
            $statsQuery->whereHas('employee', fn($eq) => $eq->where('team_id', $user->employee->team_id));
        }

        $totalOpen = (clone $statsQuery)->whereIn('status', ['open', 'seen'])->count();
        $totalMissingCheckIn = (clone $statsQuery)->whereIn('status', ['open', 'seen'])->where('alert_type', 'missing_check_in')->count();
        $totalMissingCheckOut = (clone $statsQuery)->whereIn('status', ['open', 'seen'])->where('alert_type', 'missing_check_out')->count();
        $totalResolved = (clone $statsQuery)->where('status', 'resolved')->count();
        $totalExcused = (clone $statsQuery)->where('status', 'excused')->count();

        $alerts = $query->orderByDesc('triggered_at')->paginate(25)->withQueryString();

        return view('attendance-alerts.index', compact(
            'alerts',
            'branches',
            'teams',
            'status',
            'totalOpen',
            'totalMissingCheckIn',
            'totalMissingCheckOut',
            'totalResolved',
            'totalExcused'
        ));
    }

    /**
     * Miễn cảnh báo thiếu chấm công cho nhân viên.
     */
    public function excuse(Request $request, AttendanceAlert $attendanceAlert)
    {
        $request->validate([
            'resolution_note' => 'required|string|max:500',
        ]);

        $attendanceAlert->excuse(auth()->id(), $request->resolution_note);

        activity()->causedBy(auth()->user())
            ->performedOn($attendanceAlert)
            ->inLog('attendance_alert')
            ->withProperties(['note' => $request->resolution_note])
            ->log("Miễn cảnh báo chấm công: {$attendanceAlert->alertTypeLabel()} cho {$attendanceAlert->employee?->name}");

        return back()->with('success', 'Đã miễn cảnh báo thành công!');
    }

    /**
     * Nhân viên đóng banner / đánh dấu đã xem cảnh báo.
     */
    public function dismiss(Request $request, AttendanceAlert $attendanceAlert)
    {
        $employee = auth()->user()->employee;
        abort_if(!$employee || $attendanceAlert->employee_id !== $employee->id, 403);

        $attendanceAlert->markSeen();

        return response()->json(['success' => true]);
    }

    /**
     * Nhân viên đóng tất cả cảnh báo đang mở trong phiên đăng nhập.
     */
    public function dismissAll(Request $request)
    {
        $employee = auth()->user()->employee;
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'No employee profile'], 400);
        }

        $this->alertService->dismissAlertsForEmployee($employee->id);

        return response()->json(['success' => true]);
    }

    /**
     * Lấy danh sách cảnh báo của chính nhân viên đang đăng nhập (dành cho popup/banner).
     */
    public function myAlerts(Request $request)
    {
        $employee = auth()->user()->employee;
        if (!$employee) {
            return response()->json(['alerts' => [], 'total_count' => 0]);
        }

        $result = $this->alertService->getPendingAlertsForEmployee($employee->id);

        $formattedAlerts = $result['alerts']->map(function ($a) {
            $eff = $a->shiftSchedule?->effectiveShift();
            $timeStr = $eff ? (substr($eff->start_time, 0, 5) . ' – ' . substr($eff->end_time, 0, 5)) : '';

            return [
                'id'          => $a->id,
                'alert_type'  => $a->alert_type,
                'type_label'  => $a->alertTypeLabel(),
                'status'      => $a->status,
                'work_date'   => $a->shiftSchedule?->work_date?->format('d/m/Y'),
                'shift_name'  => $a->shiftSchedule?->shift?->name ?? 'Ca linh hoạt',
                'time_range'  => $timeStr,
                'branch_name' => $a->shiftSchedule?->branch?->name ?? '',
            ];
        });

        return response()->json([
            'alerts'                  => $formattedAlerts,
            'total_count'             => $result['total_count'],
            'missing_check_in_count'  => $result['missing_check_in_count'],
            'missing_check_out_count' => $result['missing_check_out_count'],
            'has_unseen'              => $result['has_unseen'],
        ]);
    }
}
