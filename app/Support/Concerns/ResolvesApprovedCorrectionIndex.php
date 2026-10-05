<?php

namespace App\Support\Concerns;

use App\Models\StaffRequest;
use Illuminate\Support\Collection;

trait ResolvesApprovedCorrectionIndex
{
    /**
     * Map "employeeId_Y-m-d_shiftScheduleId" (hoặc "..._any" nếu yêu cầu không gắn ca cụ thể) =>
     * true cho các yêu cầu "Đi muộn về sớm" (kết quả "Trừ giờ thực tế") / "Thay đổi giờ vào/ra" đã
     * duyệt — dùng để hiển thị badge "Ghi nhận" trên Bảng chấm công cho AttendanceLog có trễ/sớm
     * NHƯNG đã có đơn xin phép được duyệt (không tha lỗi, chỉ là đã ghi nhận lý do). Khác với
     * "Đã tha lỗi" (full_credit=true) đã có sẵn ngay trên cột AttendanceLog, không cần tra
     * thêm — late_early duyệt "Công thường" nên KHÔNG có trong index này (xem điều kiện bên dưới).
     *
     * @param Collection $logs Collection các AttendanceLog cần build index (đã load employee_id, work_date, shift_schedule_id)
     */
    protected function approvedCorrectionIndex(Collection $logs): array
    {
        if ($logs->isEmpty()) {
            return [];
        }

        $employeeIds = $logs->pluck('employee_id')->unique()->values();
        $dates       = $logs->pluck('work_date')->map(fn($d) => $d->toDateString())->unique();

        if ($dates->isEmpty()) {
            return [];
        }

        $requests = StaffRequest::whereIn('employee_id', $employeeIds)
            ->whereIn('type', ['late_early', 'time_change'])
            ->where('status', 'approved')
            ->where('work_date', '<=', $dates->max())
            ->where('work_date', '>=', $dates->min())
            ->get(['employee_id', 'work_date', 'type', 'approval_outcome', 'payload']);

        $index = [];
        foreach ($requests as $r) {
            if ($r->type === 'late_early' && $r->approval_outcome === 'normal') {
                continue;
            }

            $scheduleId = $r->payload['shift_schedule_id'] ?? null;
            $key        = $r->employee_id . '_' . $r->work_date->toDateString() . '_' . ($scheduleId ?? 'any');
            $index[$key] = true;
        }

        return $index;
    }
}
