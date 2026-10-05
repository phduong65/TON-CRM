<?php

namespace App\Console\Commands;

use App\Models\ShiftSchedule;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Chạy mỗi phút (xem routes/console.php): nhắc nhân viên trước giờ check-in/check-out 5 phút,
 * và cảnh báo nếu quá 10 phút kể từ giờ vào/ra ca mà vẫn chưa chấm công. Mỗi mốc chỉ gửi đúng 1
 * lần/ca nhờ 4 cột *_sent_at trên shift_schedules — an toàn khi lệnh bị trễ hoặc chạy nhiều lần.
 */
class SendShiftAttendanceReminders extends Command
{
    protected $signature = 'attendance:send-shift-reminders';

    protected $description = 'Send check-in/check-out reminders and late alerts for today\'s shift schedules';

    private const REMINDER_MINUTES_BEFORE = 5;
    private const LATE_ALERT_MINUTES_AFTER = 10;

    public function handle(NotificationService $notifications): int
    {
        $now = now();

        $schedules = ShiftSchedule::with(['shift', 'employee.user', 'attendanceLog'])
            ->whereIn('work_date', [$now->copy()->subDay()->toDateString(), $now->toDateString()])
            ->where('status', 'scheduled')
            ->get();

        $sent = 0;

        foreach ($schedules as $schedule) {
            $startAt = $schedule->startAt();
            $endAt   = $schedule->endAt();
            $log     = $schedule->attendanceLog;

            if ($startAt && !$schedule->checkin_reminder_sent_at
                && !($log && $log->check_in_at)
                && $now->betweenIncluded($startAt->copy()->subMinutes(self::REMINDER_MINUTES_BEFORE), $startAt)
            ) {
                $notifications->notifyShiftCheckinReminder($schedule);
                $schedule->checkin_reminder_sent_at = $now;
                $schedule->save();
                $sent++;
            }

            if ($endAt && !$schedule->checkout_reminder_sent_at
                && $log && $log->check_in_at && !$log->check_out_at
                && $now->betweenIncluded($endAt->copy()->subMinutes(self::REMINDER_MINUTES_BEFORE), $endAt)
            ) {
                $notifications->notifyShiftCheckoutReminder($schedule);
                $schedule->checkout_reminder_sent_at = $now;
                $schedule->save();
                $sent++;
            }

            if ($startAt && !$schedule->checkin_late_alert_sent_at
                && !($log && $log->check_in_at)
                && $now->greaterThanOrEqualTo($startAt->copy()->addMinutes(self::LATE_ALERT_MINUTES_AFTER))
            ) {
                $notifications->notifyShiftCheckinMissing($schedule);
                $schedule->checkin_late_alert_sent_at = $now;
                $schedule->save();
                $sent++;
            }

            if ($endAt && !$schedule->checkout_late_alert_sent_at
                && $log && $log->check_in_at && !$log->check_out_at
                && $now->greaterThanOrEqualTo($endAt->copy()->addMinutes(self::LATE_ALERT_MINUTES_AFTER))
            ) {
                $notifications->notifyShiftCheckoutMissing($schedule);
                $schedule->checkout_late_alert_sent_at = $now;
                $schedule->save();
                $sent++;
            }
        }

        $this->info("Processed {$schedules->count()} shift schedule(s), sent {$sent} notification(s).");

        return self::SUCCESS;
    }
}
