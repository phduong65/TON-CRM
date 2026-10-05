<?php

namespace App\Services;

use App\Models\AttendanceImportRule;
use App\Models\AttendanceLog;
use App\Models\Penalty;

class AttendanceAutoPenaltyService
{
    /**
     * Tự động tạo phiếu phạt "pending" khi chấm công trễ/về sớm vượt ngưỡng đã cấu hình tại
     * Import chấm công (AttendanceImportRule) — cùng bảng ngưỡng, cùng cách khớp (matchFor())
     * và cùng kiểu phiếu phạt mà AttendanceImportController::confirm() tạo ra, chỉ khác nguồn
     * kích hoạt là check-in/check-out thực tế thay vì import file.
     *
     * Idempotent qua late_penalty_id/early_penalty_id trên AttendanceLog: mỗi bản ghi chấm công
     * chỉ tự tạo tối đa 1 phiếu phạt cho mỗi loại (trễ/sớm), kể cả khi được gọi lại nhiều lần.
     */
    public function createIfNeeded(AttendanceLog $log, string $type, int $minutes): ?Penalty
    {
        $column = $type === 'late' ? 'late_penalty_id' : 'early_penalty_id';

        if ($minutes <= 0 || $log->{$column}) {
            return null;
        }

        $rule = AttendanceImportRule::matchFor($type, $minutes);
        if (!$rule || !$rule->violation) {
            return null;
        }

        $code = Penalty::nextCode();

        $typeLabel = $type === 'late' ? "Đi trễ {$minutes} phút" : "Về sớm {$minutes} phút";
        $ruleLabel = $rule->label ? " [{$rule->label}]" : '';
        $dateStr   = ' — Ngày: ' . $log->work_date->format('d/m/Y');

        $penalty = Penalty::create([
            'code'                  => $code,
            'created_by'            => auth()->id(),
            'employee_id'           => $log->employee_id,
            'violation_id'          => $rule->violation->id,
            'description'           => "[Tự động - Chấm công]{$ruleLabel} {$typeLabel}{$dateStr}",
            'status'                => 'pending',
            'total_points_deducted' => $rule->violation->points_deducted,
            'total_money_deducted'  => $rule->violation->money_deducted ?? 0,
        ]);

        $log->update([$column => $penalty->id]);

        $penalty->loadMissing(['violation', 'employee']);

        activity()->causedBy(auth()->user())
            ->performedOn($penalty)
            ->inLog('penalty')
            ->withProperties([
                'code'            => $penalty->code,
                'employee_name'   => $penalty->employee?->name,
                'employee_code'   => $penalty->employee?->code,
                'violation'       => $penalty->violation?->name,
                'points_deducted' => $penalty->total_points_deducted,
                'rule_label'      => $rule->label,
                'minutes'         => $minutes,
                'source'          => 'attendance_checkinout',
            ])
            ->log("Tự động tạo phiếu phạt {$penalty->code} từ chấm công — {$penalty->employee?->name} — {$typeLabel}");

        app(NotificationService::class)->notifyPenaltyCreated($penalty);

        return $penalty;
    }
}
