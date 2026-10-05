<?php

namespace App\Console\Commands;

use App\Services\AttendanceAlertService;
use Illuminate\Console\Command;

class ScanAttendanceAlertsCommand extends Command
{
    protected $signature = 'attendance:scan-alerts {--employee= : Quét riêng cho 1 ID nhân viên} {--days=7 : Số ngày quét lùi về trước}';

    protected $description = 'Quét các ca đã hết hạn để tạo cảnh báo thiếu Check-in / Check-out';

    public function handle(AttendanceAlertService $alertService): int
    {
        $employeeId = $this->option('employee') ? (int) $this->option('employee') : null;
        $days = (int) $this->option('days') ?: 7;

        $this->info("Bắt đầu quét cảnh báo chấm công (lùi {$days} ngày)...");

        $result = $alertService->scanAlerts($employeeId, $days);

        $this->info("Hoàn tất quét cảnh báo:");
        $this->line("- Cảnh báo thiếu check-in mới: {$result['missing_check_in_created']}");
        $this->line("- Cảnh báo thiếu check-out mới: {$result['missing_check_out_created']}");

        return self::SUCCESS;
    }
}
