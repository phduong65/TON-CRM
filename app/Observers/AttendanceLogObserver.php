<?php

namespace App\Observers;

use App\Models\AttendanceLog;
use App\Services\TimesheetConfirmationService;

/**
 * Tự động huỷ trạng thái "Đã xác nhận công" của tháng liên quan khi 1 bản ghi chấm công
 * của nhân viên bị tạo/sửa/xoá (VD admin sửa giờ vào/ra ở Báo cáo chấm công sau khi nhân
 * viên đã xác nhận) — đảm bảo xác nhận luôn phản ánh đúng dữ liệu mới nhất. Xem
 * TimesheetConfirmationService::resetIfConfirmed().
 */
class AttendanceLogObserver
{
    public function __construct(private readonly TimesheetConfirmationService $service)
    {
    }

    public function saved(AttendanceLog $log): void
    {
        $this->reset($log);
    }

    public function deleted(AttendanceLog $log): void
    {
        $this->reset($log);
    }

    private function reset(AttendanceLog $log): void
    {
        $date = $log->work_date;
        $this->service->resetIfConfirmed($log->employee_id, $date->month, $date->year);
    }
}
