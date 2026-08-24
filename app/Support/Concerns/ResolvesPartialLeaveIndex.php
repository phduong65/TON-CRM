<?php

namespace App\Support\Concerns;

use App\Models\LeaveRequest;
use Illuminate\Support\Collection;

trait ResolvesPartialLeaveIndex
{
    /**
     * Map "employeeId_Y-m-d_shiftScheduleId" => day_fraction (float) cho đơn "Nghỉ theo ca cụ
     * thể" đã duyệt — dùng để truyền vào AttendanceLog::computeCong($shift, $partialLeaveFraction)
     * sao cho các trang hiển thị "Công" theo từng lượt chấm công (Báo cáo chấm công, Lịch sử chấm
     * công của tôi, Xuất Excel) tính đúng công phần đi làm còn lại của ca, thay vì tính đủ 1 công
     * cho ca full-time dù nhân viên đã xin nghỉ đúng ca đó.
     *
     * Khoá index PHẢI gồm cả shift_schedule_id, không chỉ employeeId_date: nhân viên xếp đa ca
     * trong cùng 1 ngày (VD ca sáng + ca tối) có thể chỉ xin nghỉ MỘT trong các ca đó. Nếu khoá
     * theo employeeId_date, fraction của đơn nghỉ (gắn với ca sáng) sẽ bị áp nhầm sang cả bản ghi
     * chấm công của ca tối không liên quan — bug thực tế: nhân viên đi làm đủ ca tối (chấm công
     * đầy đủ) vẫn bị tính "0 công" vì đơn nghỉ ca sáng đè lên.
     *
     * Đơn kiểu mới (từ khi có chọn nhiều ca) gắn 1..N ca qua bảng phụ
     * leave_request_shift_schedules, mỗi ca có ngày (lấy từ chính ShiftSchedule::work_date, có thể
     * khác ngày nhau trong cùng 1 đơn) và day_fraction riêng. Đơn kiểu cũ (tạo trước khi có tính
     * năng này) chỉ có đúng 1 ca qua cột shift_schedule_id, luôn cùng ngày với date_from.
     *
     * @param Collection $logs Collection các AttendanceLog cần build index (đã load employee_id, work_date)
     */
    protected function partialLeaveFractionIndex(Collection $logs): array
    {
        if ($logs->isEmpty()) {
            return [];
        }

        $employeeIds = $logs->pluck('employee_id')->unique()->values();
        $dates       = $logs->pluck('work_date')->map(fn($d) => $d->toDateString())->unique();

        if ($dates->isEmpty()) {
            return [];
        }

        $leaves = LeaveRequest::whereIn('employee_id', $employeeIds)
            ->where('is_partial_day', true)
            ->where('status', 'approved')
            ->where('date_from', '<=', $dates->max())
            ->where('date_to', '>=', $dates->min())
            ->with('shiftSchedules:id,work_date')
            ->get(['id', 'employee_id', 'date_from', 'day_fraction', 'shift_schedule_id']);

        $index = [];
        foreach ($leaves as $leave) {
            if ($leave->shiftSchedules->isNotEmpty()) {
                foreach ($leave->shiftSchedules as $schedule) {
                    $key = $leave->employee_id . '_' . $schedule->work_date->toDateString() . '_' . $schedule->id;
                    $index[$key] = (float) $schedule->pivot->day_fraction;
                }
                continue;
            }

            // Dữ liệu cũ — chỉ 1 ca, luôn cùng ngày date_from.
            $key = $leave->employee_id . '_' . $leave->date_from->toDateString() . '_' . $leave->shift_schedule_id;
            $index[$key] = (float) $leave->day_fraction;
        }

        return $index;
    }
}
