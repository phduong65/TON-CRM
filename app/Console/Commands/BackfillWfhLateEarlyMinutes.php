<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sửa dữ liệu lịch sử: trước khi vá lỗi ở AttendanceController::checkIn()/checkOut(), ca WFH luôn
 * bị ghi late_minutes/early_minutes = 0 (bỏ qua tính trễ/sớm thay vì chỉ bỏ qua xác thực GPS/WiFi).
 * Lệnh này tính lại 2 cột đó cho các AttendanceLog đã chấm công qua WFH, dựa theo đúng công thức ở
 * AttendanceController::computeLateMinutes()/computeEarlyMinutes() nhưng áp cho thời điểm check-in/
 * check-out đã ghi nhận (thay vì now()).
 *
 * CHỦ Ý KHÔNG tạo phiếu phạt hồi tố (AttendanceAutoPenaltyService) cho các lượt trễ/sớm phát hiện
 * được — chỉ sửa lại số liệu hiển thị/báo cáo cho đúng, tránh nhân viên bị phạt bất ngờ vì lỗi hệ
 * thống, không phải lỗi của họ.
 *
 * Usage: php artisan attendance:backfill-wfh-late-early --dry-run
 */
class BackfillWfhLateEarlyMinutes extends Command
{
    protected $signature = 'attendance:backfill-wfh-late-early
                            {--dry-run : Chỉ liệt kê thay đổi, không ghi vào DB}';

    protected $description = 'Tính lại late_minutes/early_minutes cho các lượt chấm công WFH cũ bị bug ghi luôn = 0 (KHÔNG tạo phạt hồi tố)';

    public function handle(): int
    {
        $logs = AttendanceLog::where(function ($q) {
            $q->where('check_in_method', 'wfh')->orWhere('check_out_method', 'wfh');
        })
            ->with('shiftSchedule.shift')
            ->orderBy('work_date')
            ->get();

        if ($logs->isEmpty()) {
            $this->info('Không có lượt chấm công WFH nào.');
            return self::SUCCESS;
        }

        $dryRun  = (bool) $this->option('dry-run');
        $changes = [];
        $skipped = 0;

        foreach ($logs as $log) {
            $schedule = $log->shiftSchedule;
            if (!$schedule) {
                $skipped++;
                continue;
            }

            $data = [];

            if ($log->check_in_method === 'wfh' && $log->check_in_at) {
                $newLate = $this->computeLateMinutesAt($schedule, $log->check_in_at);
                if ($newLate !== (int) $log->late_minutes) {
                    $data['late_minutes'] = $newLate;
                }
            }

            if ($log->check_out_method === 'wfh' && $log->check_out_at) {
                $newEarly = $this->computeEarlyMinutesAt($schedule, $log->check_out_at);
                if ($newEarly !== (int) $log->early_minutes) {
                    $data['early_minutes'] = $newEarly;
                }
            }

            if (empty($data)) {
                continue;
            }

            $changes[] = ['log' => $log, 'data' => $data];

            $diffLabel = collect($data)->map(fn($v, $k) => "{$k}: {$log->{$k}} → {$v}")->implode(', ');
            $this->line("  #{$log->id} (NV #{$log->employee_id}, {$log->work_date->toDateString()}): {$diffLabel}");
        }

        if ($skipped > 0) {
            $this->warn("Bỏ qua {$skipped} bản ghi không còn liên kết ShiftSchedule (đã bị xoá) — không đủ dữ liệu để tính lại chính xác.");
        }

        if (empty($changes)) {
            $this->info('Không có bản ghi nào cần cập nhật.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('[DRY-RUN] ' . count($changes) . ' bản ghi sẽ được cập nhật. Chạy lại không kèm --dry-run để áp dụng.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Cập nhật late_minutes/early_minutes cho ' . count($changes) . ' bản ghi trên? (KHÔNG tạo phiếu phạt hồi tố)', true)) {
            $this->warn('Đã huỷ.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $change) {
                $change['log']->update($change['data']);
            }
        });

        activity()
            ->inLog('attendance')
            ->withProperties([
                'updated_logs'        => count($changes),
                'attendance_log_ids'  => collect($changes)->pluck('log.id')->all(),
            ])
            ->log('Backfill late_minutes/early_minutes cho lượt chấm công WFH cũ (CLI attendance:backfill-wfh-late-early) — không tạo phạt hồi tố');

        $this->info('Đã cập nhật ' . count($changes) . ' bản ghi.');

        return self::SUCCESS;
    }

    /**
     * Xem AttendanceController::computeLateMinutes() — cùng công thức, chỉ khác tham số $at truyền
     * vào (thời điểm check-in đã ghi nhận) thay vì luôn dùng now().
     */
    private function computeLateMinutesAt($shiftSchedule, Carbon $at): int
    {
        $shift = $shiftSchedule->effectiveShift();
        $start = $shiftSchedule->startAt();
        if (!$shift || !$start) {
            return 0;
        }

        if ($at->lessThanOrEqualTo($start)) {
            return 0;
        }

        return max(0, $start->diffInMinutes($at) - ($shift->grace_late_minutes ?? 0));
    }

    /**
     * Xem AttendanceController::computeEarlyMinutes() — cùng công thức, chỉ khác tham số $at truyền
     * vào (thời điểm check-out đã ghi nhận) thay vì luôn dùng now().
     */
    private function computeEarlyMinutesAt($shiftSchedule, Carbon $at): int
    {
        $shift = $shiftSchedule->effectiveShift();
        $end   = $shiftSchedule->endAt();
        if (!$shift || !$end) {
            return 0;
        }

        if ($at->greaterThanOrEqualTo($end)) {
            return 0;
        }

        return max(0, $at->diffInMinutes($end) - ($shift->grace_early_minutes ?? 0));
    }
}
