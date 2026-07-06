<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\UpdateShiftRequest;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\ShiftScheduleRecurrence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftsController extends Controller
{
    public function index(Request $request)
    {
        $query = Shift::with('branch')->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
        }

        if ($request->filled('work_mode')) {
            $query->where('work_mode', $request->work_mode);
        }

        if ($request->filled('shift_type')) {
            $query->where('shift_type', $request->shift_type);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $shifts   = $query->paginate(15)->withQueryString();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        return view('shifts.index', compact('shifts', 'branches'));
    }

    public function store(StoreShiftRequest $request)
    {
        Shift::create($request->validated());

        return redirect()->route('shifts.index')->with('success', 'Đã tạo ca làm việc!');
    }

    public function update(UpdateShiftRequest $request, Shift $shift)
    {
        $shift->update($request->validated());

        return redirect()->route('shifts.index')->with('success', 'Đã cập nhật ca làm việc!');
    }

    /**
     * Xoá hẳn 1 ca làm việc khỏi DB (forceDelete — không phải soft-delete). Mọi
     * lịch xếp ca (quá khứ & tương lai, của mọi nhân viên) dùng ca này, cùng
     * toàn bộ lịch sử chấm công (attendance_logs) gắn với các lịch xếp ca đó,
     * đều bị xoá hẳn theo. Đợt xếp ca cố định (recurrence) tham chiếu ca này
     * cũng được dọn: bỏ ca khỏi danh sách shift_ids, hoặc xoá cả đợt nếu đó là
     * ca duy nhất trong đợt.
     */
    public function destroy(Shift $shift)
    {
        $result = DB::transaction(function () use ($shift) {
            $scheduleIds = ShiftSchedule::where('shift_id', $shift->id)->pluck('id');

            $deletedAttendanceLogs = AttendanceLog::whereIn('shift_schedule_id', $scheduleIds)->delete();
            $deletedSchedules      = ShiftSchedule::whereIn('id', $scheduleIds)->delete();

            $recurrences         = ShiftScheduleRecurrence::whereJsonContains('shift_ids', $shift->id)->get();
            $deletedRecurrences  = 0;
            $updatedRecurrences  = 0;

            foreach ($recurrences as $recurrence) {
                $remainingShiftIds = array_values(array_diff(
                    array_map('intval', $recurrence->shift_ids),
                    [(int) $shift->id]
                ));

                if (empty($remainingShiftIds)) {
                    $recurrence->delete();
                    $deletedRecurrences++;
                } else {
                    $recurrence->update(['shift_ids' => $remainingShiftIds]);
                    $updatedRecurrences++;
                }
            }

            $shift->forceDelete();

            return [
                'deleted_schedules'       => $deletedSchedules,
                'deleted_attendance_logs' => $deletedAttendanceLogs,
                'deleted_recurrences'     => $deletedRecurrences,
                'updated_recurrences'     => $updatedRecurrences,
            ];
        });

        activity()->causedBy(auth()->user())
            ->inLog('shift')
            ->withProperties(['shift_code' => $shift->code, 'shift_name' => $shift->name] + $result)
            ->log("Xoá ca làm việc — {$shift->name}");

        $message = "Đã xoá hẳn ca \"{$shift->name}\"";
        if ($result['deleted_schedules'] > 0) {
            $message .= " và {$result['deleted_schedules']} lượt xếp ca liên quan (của tất cả nhân viên)";
        }
        if ($result['deleted_attendance_logs'] > 0) {
            $message .= ", {$result['deleted_attendance_logs']} bản ghi chấm công liên quan";
        }
        $message .= '!';

        return redirect()->route('shifts.index')->with('success', $message);
    }
}
