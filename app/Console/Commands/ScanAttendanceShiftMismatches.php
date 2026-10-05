<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use Illuminate\Console\Command;

/**
 * Quét toàn bộ attendance_logs để tìm các bản ghi nghi ngờ tính công sai do:
 * 1. "rollover_suspect" — check_out_at cách check_in_at hơn 1 ngày lịch — ca qua đêm hợp lệ tối
 *    đa chỉ lệch 1 ngày, lệch nhiều hơn gần như chắc chắn là dữ liệu hỏng (có thể do bug ngày-
 *    tháng cũ ở ResolvesOvernightCheckOutDate trước khi được sửa).
 * 2. "clamped_to_zero" — có check-in/check-out thực tế cách nhau > 15 phút nhưng
 *    AttendanceLog::netWorkedHours() (đã kẹp theo khung giờ ca đang gán) lại ra 0 — dấu hiệu
 *    khoảng thời gian chấm công thực tế nằm HOÀN TOÀN ngoài khung giờ ca đang xếp cho ngày đó
 *    (xem AttendanceLog::netWorkedHours()).
 *
 * Chỉ đọc dữ liệu, không sửa gì — dùng để khoanh vùng các bản ghi cần admin xem lại thủ công
 * (đổi lại Xếp ca cho đúng ngày, rồi tạo lại chấm công — xem hướng dẫn đã trao đổi).
 */
class ScanAttendanceShiftMismatches extends Command
{
    protected $signature = 'attendance:scan-shift-mismatches
                            {--from= : Chỉ quét work_date từ ngày này (Y-m-d)}
                            {--to=   : Chỉ quét work_date đến ngày này (Y-m-d)}
                            {--limit=200 : Số dòng tối đa in ra (không giới hạn số bản ghi được quét)}';

    protected $description = 'Quét attendance_logs để tìm bản ghi nghi ngờ tính công sai do lệch ngày check-out hoặc giờ chấm công nằm ngoài khung giờ ca đang xếp';

    public function handle(): int
    {
        $query = AttendanceLog::with(['employee:id,name', 'shiftSchedule.shift:id,name,start_time,end_time'])
            ->whereNotNull('check_in_at')
            ->whereNotNull('check_out_at')
            ->orderBy('work_date');

        if ($from = $this->option('from')) {
            $query->where('work_date', '>=', $from);
        }
        if ($to = $this->option('to')) {
            $query->where('work_date', '<=', $to);
        }

        $limit   = (int) $this->option('limit');
        $scanned = 0;
        $flagged = [];

        $query->chunkById(500, function ($logs) use (&$scanned, &$flagged) {
            foreach ($logs as $log) {
                $scanned++;

                $rawMinutes = $log->check_out_at->greaterThan($log->check_in_at)
                    ? $log->check_in_at->diffInMinutes($log->check_out_at)
                    : -$log->check_out_at->diffInMinutes($log->check_in_at);

                $dayGap = $log->check_in_at->copy()->startOfDay()
                    ->diffInDays($log->check_out_at->copy()->startOfDay());

                $netWorked = $log->netWorkedHours();

                $issues = [];

                if ($dayGap > 1) {
                    $issues[] = 'rollover_suspect (lệch ' . $dayGap . ' ngày lịch)';
                }

                if ($rawMinutes > 15 && $netWorked !== null && $netWorked <= 0.0) {
                    $issues[] = 'clamped_to_zero (thực tế cách nhau ' . round($rawMinutes / 60, 2) . 'h nhưng công tính ra 0)';
                }

                if (empty($issues)) {
                    continue;
                }

                $shift = $log->shiftSchedule?->shift;

                $flagged[] = [
                    'id'         => $log->id,
                    'employee'   => $log->employee?->name ?? "#{$log->employee_id}",
                    'work_date'  => $log->work_date->toDateString(),
                    'shift'      => $shift?->name ?? ($log->shift_start_time ? '(ca đã xoá/snapshot)' : '—'),
                    'shift_win'  => $log->shift_start_time && $log->shift_end_time
                        ? substr($log->shift_start_time, 0, 5) . '-' . substr($log->shift_end_time, 0, 5)
                        : '—',
                    'check_in'   => $log->check_in_at->format('Y-m-d H:i'),
                    'check_out'  => $log->check_out_at->format('Y-m-d H:i'),
                    'net_hours'  => $netWorked,
                    'cong'       => $log->computeCong(),
                    'issues'     => implode('; ', $issues),
                ];
            }
        }, 'id');

        $this->info("Đã quét {$scanned} bản ghi chấm công.");

        if (empty($flagged)) {
            $this->info('Không phát hiện bản ghi nào nghi ngờ.');
            return self::SUCCESS;
        }

        $this->warn(count($flagged) . ' bản ghi nghi ngờ:');

        $rows = array_slice($flagged, 0, $limit);

        $this->table(
            ['ID', 'Nhân viên', 'Ngày', 'Ca', 'Khung giờ ca', 'Check-in', 'Check-out', 'Giờ công', 'Công', 'Vấn đề'],
            array_map(fn($r) => [
                $r['id'], $r['employee'], $r['work_date'], $r['shift'], $r['shift_win'],
                $r['check_in'], $r['check_out'], $r['net_hours'], $r['cong'], $r['issues'],
            ], $rows)
        );

        if (count($flagged) > $limit) {
            $this->warn('... còn ' . (count($flagged) - $limit) . ' bản ghi nữa không hiển thị hết, tăng --limit để xem thêm.');
        }

        return self::SUCCESS;
    }
}
