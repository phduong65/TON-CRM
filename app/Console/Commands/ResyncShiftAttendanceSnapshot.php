<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Đồng bộ lại giờ ca đã "chụp" (snapshot) trên attendance_logs (shift_start_time,
 * shift_end_time, shift_break_minutes, shift_is_overnight, shift_type,
 * shift_standard_work_hours) theo đúng thông số hiện tại của Shift/ShiftSchedule.
 *
 * Snapshot này vốn được cố ý đóng băng lúc check-in (xem
 * ShiftSchedule::shiftSnapshotAttributes()) để công không bị viết lại nếu sau này ai
 * đó sửa/xoá ca — đây là hành vi mặc định và KHÔNG bị lệnh này thay đổi. Lệnh này chỉ
 * dùng khi cần chủ động đồng bộ lại các bản ghi cũ, ví dụ sửa lỗi nhập liệu (nhập nhầm
 * standard_work_hours) muốn áp dụng lại giá trị đúng cho các lượt chấm công đã có.
 */
class ResyncShiftAttendanceSnapshot extends Command
{
    protected $signature = 'attendance:resync-shift
                            {shift : ID hoặc code của ca (Shift) cần đồng bộ lại}
                            {--from= : Chỉ đồng bộ các bản ghi từ ngày này (Y-m-d)}
                            {--to=   : Chỉ đồng bộ các bản ghi đến ngày này (Y-m-d)}
                            {--dry-run : Chỉ liệt kê thay đổi, không ghi vào DB}';

    protected $description = 'Đồng bộ lại giờ ca đã snapshot trên attendance_logs theo đúng thông số hiện tại của Shift (dùng khi sửa lỗi nhập liệu trên ca cần áp dụng lại cho các lượt chấm công đã có)';

    public function handle(): int
    {
        $identifier = $this->argument('shift');

        $shift = is_numeric($identifier)
            ? Shift::withTrashed()->find($identifier)
            : Shift::withTrashed()->where('code', $identifier)->first();

        if (!$shift) {
            $this->error("Không tìm thấy ca với ID/code: {$identifier}");
            return self::FAILURE;
        }

        $this->info("Ca: {$shift->name} ({$shift->code}) — standard_work_hours hiện tại: {$shift->standardWorkHours()}h, giờ: {$shift->start_time}-{$shift->end_time}, break: {$shift->break_minutes}p");

        $scheduleIds = ShiftSchedule::where('shift_id', $shift->id)->pluck('id');

        if ($scheduleIds->isEmpty()) {
            $this->info('Không có lượt xếp ca nào dùng ca này.');
            return self::SUCCESS;
        }

        $query = AttendanceLog::whereIn('shift_schedule_id', $scheduleIds)
            ->with('shiftSchedule')
            ->orderBy('work_date');

        if ($from = $this->option('from')) {
            $query->where('work_date', '>=', $from);
        }
        if ($to = $this->option('to')) {
            $query->where('work_date', '<=', $to);
        }

        $logs = $query->get();

        if ($logs->isEmpty()) {
            $this->info('Không có bản ghi chấm công nào trong phạm vi cần đồng bộ.');
            return self::SUCCESS;
        }

        $dryRun  = (bool) $this->option('dry-run');
        $changes = [];

        foreach ($logs as $log) {
            $schedule = $log->shiftSchedule;
            if (!$schedule) {
                continue;
            }

            $snapshot = $schedule->shiftSnapshotAttributes();
            if (empty($snapshot)) {
                continue;
            }

            $diff = [];
            foreach ($snapshot as $key => $value) {
                if ((string) $log->{$key} !== (string) $value) {
                    $diff[$key] = [$log->{$key}, $value];
                }
            }

            if (empty($diff)) {
                continue;
            }

            $changes[] = ['log' => $log, 'snapshot' => $snapshot, 'diff' => $diff];

            $diffLabel = collect($diff)->map(fn($v, $k) => "{$k}: " . ($v[0] ?? 'null') . ' → ' . ($v[1] ?? 'null'))->implode(', ');
            $this->line("  #{$log->id} (NV #{$log->employee_id}, {$log->work_date->toDateString()}): {$diffLabel}");
        }

        if (empty($changes)) {
            $this->info('Tất cả bản ghi đã khớp với thông số hiện tại của ca — không có gì cần đồng bộ.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('[DRY-RUN] ' . count($changes) . ' bản ghi sẽ được đồng bộ. Chạy lại không kèm --dry-run để áp dụng.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Áp dụng đồng bộ cho ' . count($changes) . ' bản ghi chấm công trên?', true)) {
            $this->warn('Đã huỷ.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $change) {
                $change['log']->update($change['snapshot']);
            }
        });

        activity()
            ->inLog('shift')
            ->withProperties([
                'shift_code'     => $shift->code,
                'shift_name'     => $shift->name,
                'updated_logs'   => count($changes),
                'attendance_log_ids' => collect($changes)->pluck('log.id')->all(),
            ])
            ->log("Đồng bộ lại giờ công snapshot cho ca {$shift->name} (CLI attendance:resync-shift)");

        $this->info('Đã đồng bộ ' . count($changes) . ' bản ghi chấm công.');

        return self::SUCCESS;
    }
}
