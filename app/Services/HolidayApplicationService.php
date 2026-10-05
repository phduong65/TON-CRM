<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Áp dụng / đảo ngược tác động của 1 ngày nghỉ lễ lên lịch xếp ca + chấm công của các bộ phận được
 * nghỉ (theo phạm vi holiday):
 * - apply(): với mỗi NV được áp dụng và KHÔNG đi làm hôm đó -> huỷ các ca đã xếp (đánh dấu
 *   holiday_id để đảo ngược) và tạo bản ghi chấm công nghỉ lễ (source='holiday', full_credit).
 *   NV đã đi làm (có check-in) -> giữ nguyên, hưởng "công ngày lễ" qua Báo cáo chấm công.
 * - reverse(): khôi phục ca đã huỷ + xoá bản ghi chấm công lễ tự tạo của đúng lễ đó.
 * - reapply(): đảo ngược rồi áp dụng lại (khi đổi ngày/phạm vi/kích hoạt lễ).
 */
class HolidayApplicationService
{
    public function apply(Holiday $holiday): void
    {
        if (!$holiday->is_active) {
            return;
        }

        $date    = $holiday->date->toDateString();
        $teamIds = $holiday->applies_to_all ? null : $holiday->teams()->pluck('teams.id')->all();

        DB::transaction(function () use ($holiday, $date, $teamIds) {
            foreach ($this->coveredEmployees($holiday, $teamIds) as $employee) {
                $this->applyForEmployee($holiday, $employee, $date);
            }
        });
    }

    public function reverse(Holiday $holiday): void
    {
        DB::transaction(function () use ($holiday) {
            // Khôi phục các ca đã bị huỷ vì lễ này về "đã xếp".
            ShiftSchedule::where('holiday_id', $holiday->id)
                ->update(['status' => 'scheduled', 'holiday_id' => null]);

            // Xoá các bản ghi chấm công nghỉ lễ do lễ này tự tạo.
            AttendanceLog::where('holiday_id', $holiday->id)->delete();
        });
    }

    public function reapply(Holiday $holiday): void
    {
        $this->reverse($holiday);
        $this->apply($holiday->fresh());
    }

    /** Nhân viên (đang hoạt động) thuộc phạm vi lễ. */
    private function coveredEmployees(Holiday $holiday, ?array $teamIds): Collection
    {
        $query = Employee::where('is_active', true);

        if (!$holiday->applies_to_all) {
            if (empty($teamIds)) {
                return collect();
            }
            $query->whereIn('team_id', $teamIds);
        }

        return $query->get();
    }

    private function applyForEmployee(Holiday $holiday, Employee $employee, string $date): void
    {
        // Ngày nghỉ hàng tuần sẵn có của NV -> vốn đã off, không tạo công lễ (tránh cộng trùng ở Báo
        // cáo chấm công — xem AttendanceTimesheetBuilder::isWeeklyRestDay()).
        if ($this->isWeeklyRestDay(Carbon::parse($date), (bool) $employee->is_office)) {
            return;
        }

        $dayLogs = AttendanceLog::where('employee_id', $employee->id)
            ->where('work_date', $date)
            ->lockForUpdate()
            ->get();

        // Đã có bản ghi lễ cho NV/ngày này -> idempotent, bỏ qua.
        if ($dayLogs->contains(fn (AttendanceLog $l) => $l->holiday_id !== null)) {
            return;
        }

        $hasCheckIn = $dayLogs->contains(fn (AttendanceLog $l) => $l->check_in_at !== null);

        // Huỷ các ca đã xếp mà NV CHƯA chấm công (đánh dấu holiday_id để đảo ngược). Ca đã có
        // check-in (họ vẫn đi làm ca đó) -> giữ nguyên.
        $schedules = ShiftSchedule::where('employee_id', $employee->id)
            ->where('work_date', $date)
            ->where('status', 'scheduled')
            ->lockForUpdate()
            ->get();

        foreach ($schedules as $schedule) {
            $worked = $dayLogs->contains(fn (AttendanceLog $l) => $l->shift_schedule_id === $schedule->id && $l->check_in_at !== null);
            if (!$worked) {
                $schedule->update(['status' => 'cancelled', 'holiday_id' => $holiday->id]);
            }
        }

        // Chỉ tạo bản ghi chấm công nghỉ lễ khi NV KHÔNG đi làm hôm đó (không có check-in nào). NV có
        // đi làm ngày lễ đã được tính "công ngày lễ" từ chính lượt chấm công thật của họ.
        if (!$hasCheckIn) {
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'holiday_id'  => $holiday->id,
                'source'      => 'holiday',
                'work_date'   => $date,
                'full_credit' => true,
            ]);
        }
    }

    private function isWeeklyRestDay(Carbon $day, bool $isOffice): bool
    {
        return $isOffice
            ? in_array($day->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true)
            : $day->dayOfWeek === Carbon::SUNDAY;
    }
}
