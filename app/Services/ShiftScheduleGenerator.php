<?php

namespace App\Services;

use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sinh các bản ghi shift_schedules (ca cố định) cho một tập nhân viên,
 * trong một khoảng ngày, theo các thứ trong tuần chỉ định.
 * Dùng chung bởi ShiftSchedulesController::bulkStore (đợt có ngày kết thúc
 * hoặc khởi tạo đợt lặp lại) và GenerateRecurringShiftSchedules (mở rộng
 * cửa sổ lặp lại hàng đêm cho các đợt chưa có ngày kết thúc).
 */
class ShiftScheduleGenerator
{
    /** Số tuần luôn được sinh sẵn trước cho các đợt lặp lại không giới hạn. */
    public const HORIZON_WEEKS = 12;

    /**
     * @param array<int> $shiftIds Một hoặc nhiều ca — mỗi ca được xếp riêng (đa ca) cho từng
     *                             nhân viên/ngày phù hợp. Trùng chính xác (employee, ngày, ca)
     *                             thì bỏ qua; khác ca thì vẫn thêm mới bên cạnh ca đã có.
     */
    public function generateRange(
        Collection $employees,
        array $weekdays,
        Carbon $from,
        Carbon $to,
        array $shiftIds,
        ?string $batchId,
        ?int $assignedBy,
    ): array {
        $created = 0;
        $skipped = 0;

        if ($from->greaterThan($to)) {
            return ['created' => 0, 'skipped' => 0];
        }

        DB::transaction(function () use ($employees, $weekdays, $from, $to, $shiftIds, $batchId, $assignedBy, &$created, &$skipped) {
            for ($date = $from->copy(); $date->lessThanOrEqualTo($to); $date->addDay()) {
                if (!in_array($date->isoWeekday(), $weekdays, true)) {
                    continue;
                }

                foreach ($employees as $employee) {
                    foreach ($shiftIds as $shiftId) {
                        $exists = ShiftSchedule::where('employee_id', $employee->id)
                            ->where('work_date', $date->toDateString())
                            ->where('shift_id', $shiftId)
                            ->exists();

                        if ($exists) {
                            $skipped++;
                            continue;
                        }

                        ShiftSchedule::create([
                            'employee_id'     => $employee->id,
                            'shift_id'        => $shiftId,
                            'branch_id'       => $employee->branch_id,
                            'work_date'       => $date->toDateString(),
                            'assignment_type' => 'fixed',
                            'status'          => 'scheduled',
                            'batch_id'        => $batchId,
                            'assigned_by'     => $assignedBy,
                        ]);
                        $created++;
                    }
                }
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }
}
