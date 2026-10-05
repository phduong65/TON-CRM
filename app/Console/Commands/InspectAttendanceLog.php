<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Console\Command;

/**
 * In ra toàn bộ thông số ảnh hưởng đến kết quả AttendanceLog::computeCong() của 1 bản ghi —
 * dùng để chẩn đoán khi công tính ra sai mà không có Tinker (một số host tắt shell_exec()
 * khiến PsySH/Tinker không chạy được).
 */
class InspectAttendanceLog extends Command
{
    protected $signature = 'attendance:inspect
                            {employee : Tên (một phần) hoặc ID nhân viên}
                            {date : Ngày công cần xem, định dạng Y-m-d}';

    protected $description = 'In chi tiết 1 bản ghi attendance_logs (full_credit, shift_type, standard_work_hours, giờ vào/ra...) và kết quả computeCong() để chẩn đoán công tính sai';

    public function handle(): int
    {
        $identifier = $this->argument('employee');
        $date       = $this->argument('date');

        $employee = is_numeric($identifier)
            ? Employee::find($identifier)
            : Employee::where('name', 'like', "%{$identifier}%")->first();

        if (!$employee) {
            $this->error("Không tìm thấy nhân viên: {$identifier}");
            return self::FAILURE;
        }

        $this->info("Nhân viên: {$employee->name} (#{$employee->id})");

        $log = AttendanceLog::with('shiftSchedule.shift')
            ->where('employee_id', $employee->id)
            ->where('work_date', $date)
            ->first();

        if (!$log) {
            $this->error("Không có bản ghi attendance_logs cho ngày {$date}.");
            return self::FAILURE;
        }

        $rows = [
            ['id', $log->id],
            ['shift_schedule_id', $log->shift_schedule_id],
            ['work_date', $log->work_date->toDateString()],
            ['check_in_at', (string) $log->check_in_at],
            ['check_out_at', (string) $log->check_out_at],
            ['full_credit', var_export($log->full_credit, true)],
            ['overtime_hours', $log->overtime_hours],
            ['late_minutes', $log->late_minutes],
            ['early_minutes', $log->early_minutes],
            ['--- snapshot trên attendance_logs ---', ''],
            ['shift_start_time', $log->shift_start_time],
            ['shift_end_time', $log->shift_end_time],
            ['shift_break_minutes', $log->shift_break_minutes],
            ['shift_is_overnight', var_export((bool) $log->shift_is_overnight, true)],
            ['shift_type', $log->shift_type],
            ['shift_standard_work_hours', $log->shift_standard_work_hours],
            ['--- ca hiện tại (live, qua ShiftSchedule) ---', ''],
            ['shiftSchedule->shift->name', $log->shiftSchedule?->shift?->name ?? '(không có / đã xoá)'],
            ['shiftSchedule->shift->shift_type', $log->shiftSchedule?->shift?->shift_type ?? '—'],
            ['shiftSchedule->shift->standard_work_hours', $log->shiftSchedule?->shift?->standard_work_hours ?? '—'],
            ['--- kết quả tính ---', ''],
            ['netWorkedHours()', $log->netWorkedHours()],
            ['computeCong()', $log->computeCong()],
        ];

        foreach ($rows as [$label, $value]) {
            $this->line(str_pad($label, 45) . ': ' . $value);
        }

        return self::SUCCESS;
    }
}
