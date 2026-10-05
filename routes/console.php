<?php

use App\Console\Commands\CheckConsecutiveRedzone;
use App\Console\Commands\GenerateRecurringShiftSchedules;
use App\Console\Commands\ResetMonthlyScores;
use App\Console\Commands\SendShiftAttendanceReminders;
use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled tasks ──────────────────────────────────────────────────────────

// 1. Reset (initialize) monthly scores on the 1st of every month at midnight
Schedule::command(ResetMonthlyScores::class)->monthlyOn(1, '00:00');

// 2. Check for consecutive-redzone employees on the 2nd of every month
//    (runs after reset so the new month's record is already seeded)
Schedule::command(CheckConsecutiveRedzone::class)->monthlyOn(2, '08:00');

// 3. Extend active recurring fixed-shift assignments (đợt xếp ca cố định
//    không có ngày kết thúc) by rolling the generation window forward daily.
Schedule::command(GenerateRecurringShiftSchedules::class)->dailyAt('01:30');

// 4. Nhắc check-in/check-out trước giờ 5 phút + cảnh báo trễ 10 phút — cần chạy mỗi phút để
//    kịp đúng các mốc thời gian ngắn này (xem SendShiftAttendanceReminders::REMINDER/LATE_ALERT).
Schedule::command(SendShiftAttendanceReminders::class)->everyMinute()->withoutOverlapping();

// 4b. Quét cảnh báo thiếu check-in/check-out của các ca đã kết thúc (hôm qua và các ngày trước)
//     mỗi sáng, để sáng ra trang /attendance-alerts và popup nhân viên đã có sẵn cảnh báo
//     mà không phụ thuộc việc nhân viên có đăng nhập hay không (xem AttendanceAlertService::scanAlerts()).
Schedule::command('attendance:scan-alerts --days=7')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->name('attendance-scan-alerts');

// 5. "Nhịp tim" của scheduler — ghi lại thời điểm schedule:run thực sự được gọi, để nút
//    "Kiểm tra Cron Job" ở trang Cài đặt biết cron trên server có đang chạy hay không
//    (xem SettingsController::checkScheduler()). Luôn chạy mỗi phút, độc lập với 4 lệnh trên.
Schedule::call(fn () => Setting::setValue('scheduler_heartbeat_at', now()->toDateTimeString()))
    ->everyMinute()
    ->name('scheduler-heartbeat');
