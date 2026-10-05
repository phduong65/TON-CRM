<?php

namespace App\Services;

use App\Models\AttendanceAlert;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceAlertService
{
    /**
     * Quét các ca làm việc đã kết thúc để sinh cảnh báo thiếu check-in / check-out.
     * Có thể truyền $employeeId để chỉ quét cho 1 nhân viên cụ thể (VD khi vừa login).
     */
    public function scanAlerts(?int $employeeId = null, int $lookbackDays = 7): array
    {
        $now = now();
        $graceMinutes = (int) Setting::getValue('attendance_grace_checkout_minutes', 30);
        $fromDate = $now->copy()->subDays($lookbackDays)->toDateString();
        $toDate = $now->toDateString();

        $query = ShiftSchedule::with(['shift', 'attendanceLog', 'employee'])
            ->where('status', 'scheduled')
            ->whereBetween('work_date', [$fromDate, $toDate])
            ->when($employeeId, fn($q) => $q->where('employee_id', $employeeId));

        $schedules = $query->get();

        $createdCheckInAlerts = 0;
        $createdCheckOutAlerts = 0;

        // Group theo alternative_group_id để xử lý ca thay thế
        $altGroups = $schedules->whereNotNull('alternative_group_id')
            ->groupBy(fn($s) => $s->employee_id . '_' . $s->work_date->toDateString() . '_' . $s->alternative_group_id);

        foreach ($schedules as $schedule) {
            $endAt = $schedule->endAt();
            if (!$endAt || $now->lessThanOrEqualTo($endAt)) {
                // Ca chưa kết thúc -> Chưa xét cảnh báo
                continue;
            }

            $log = $schedule->attendanceLog;
            $hasCheckIn = (bool) $log?->check_in_at;
            $hasCheckOut = (bool) $log?->check_out_at;

            // 1. Kiểm tra THIẾU CHECK-IN
            if (!$hasCheckIn) {
                // Nếu ca thuộc nhóm thay thế (alternative_group_id):
                if ($schedule->alternative_group_id) {
                    $groupKey = $schedule->employee_id . '_' . $schedule->work_date->toDateString() . '_' . $schedule->alternative_group_id;
                    $groupMembers = $altGroups->get($groupKey, collect());

                    // Nếu có BẤT KỲ ca nào trong nhóm đã check-in -> Nhóm đã hoàn tất, không báo thiếu
                    $anyMemberCheckedIn = $groupMembers->contains(fn($m) => (bool) $m->attendanceLog?->check_in_at);
                    if ($anyMemberCheckedIn) {
                        continue;
                    }

                    // Nếu chưa ai check-in, chỉ cảnh báo khi ca MUỘN NHẤT trong nhóm đã kết thúc
                    $latestEndAt = $groupMembers->map(fn($m) => $m->endAt())->filter()->max();
                    if ($latestEndAt && $now->lessThanOrEqualTo($latestEndAt)) {
                        continue;
                    }
                }

                // Kiểm tra xem đã có đơn nghỉ phép được duyệt bao phủ ca này chưa
                if ($this->isCoveredByApprovedLeave($schedule)) {
                    continue;
                }

                // Sinh cảnh báo thiếu check-in (idempotent)
                $alert = AttendanceAlert::firstOrCreate(
                    [
                        'shift_schedule_id' => $schedule->id,
                        'alert_type'        => 'missing_check_in',
                    ],
                    [
                        'employee_id'  => $schedule->employee_id,
                        'status'       => 'open',
                        'triggered_at' => $now,
                    ]
                );

                if ($alert->wasRecentlyCreated) {
                    $createdCheckInAlerts++;
                }

                // Quy tắc nghiệp vụ: Nếu thiếu check-in thì KHÔNG sinh cảnh báo thiếu check-out
                continue;
            }

            // 2. Kiểm tra THIẾU CHECK-OUT
            // Đã có check-in, nhưng chưa có check-out và đã quá giờ kết thúc + grace period
            $checkoutDeadline = $endAt->copy()->addMinutes($graceMinutes);
            if ($hasCheckIn && !$hasCheckOut && $now->isAfter($checkoutDeadline)) {
                $alert = AttendanceAlert::firstOrCreate(
                    [
                        'shift_schedule_id' => $schedule->id,
                        'alert_type'        => 'missing_check_out',
                    ],
                    [
                        'employee_id'  => $schedule->employee_id,
                        'status'       => 'open',
                        'triggered_at' => $now,
                    ]
                );

                if ($alert->wasRecentlyCreated) {
                    $createdCheckOutAlerts++;
                }
            }
        }

        return [
            'missing_check_in_created'  => $createdCheckInAlerts,
            'missing_check_out_created' => $createdCheckOutAlerts,
        ];
    }

    /**
     * Khi 1 bản ghi attendance log được lưu/cập nhật, tự động giải quyết (resolve) cảnh báo tương ứng.
     */
    public function resolveAlertsOnLog(AttendanceLog $log): void
    {
        if (!$log->shift_schedule_id) {
            return;
        }

        $schedule = ShiftSchedule::find($log->shift_schedule_id);
        if (!$schedule) {
            return;
        }

        // Nếu đã có check-in -> resolve cảnh báo missing_check_in
        if ($log->check_in_at) {
            AttendanceAlert::where('shift_schedule_id', $schedule->id)
                ->where('alert_type', 'missing_check_in')
                ->whereIn('status', ['open', 'seen'])
                ->update([
                    'status'          => 'resolved',
                    'resolved_at'     => now(),
                    'resolved_by'     => auth()->id(),
                    'resolution_note' => 'Tự động giải quyết khi nhân viên/quản lý bổ sung Check-in',
                ]);

            // Nếu thuộc nhóm ca thay thế, các ca khác cùng nhóm cũng được gỡ cảnh báo nếu có
            if ($schedule->alternative_group_id) {
                $otherScheduleIds = ShiftSchedule::where('employee_id', $schedule->employee_id)
                    ->where('work_date', $schedule->work_date)
                    ->where('alternative_group_id', $schedule->alternative_group_id)
                    ->pluck('id');

                AttendanceAlert::whereIn('shift_schedule_id', $otherScheduleIds)
                    ->where('alert_type', 'missing_check_in')
                    ->whereIn('status', ['open', 'seen'])
                    ->update([
                        'status'          => 'resolved',
                        'resolved_at'     => now(),
                        'resolved_by'     => auth()->id(),
                        'resolution_note' => 'Tự động giải quyết do đã chấm công ca thay thế khác trong cùng nhóm',
                    ]);
            }
        }

        // Nếu đã có check-out -> resolve cảnh báo missing_check_out
        if ($log->check_out_at) {
            AttendanceAlert::where('shift_schedule_id', $schedule->id)
                ->where('alert_type', 'missing_check_out')
                ->whereIn('status', ['open', 'seen'])
                ->update([
                    'status'          => 'resolved',
                    'resolved_at'     => now(),
                    'resolved_by'     => auth()->id(),
                    'resolution_note' => 'Tự động giải quyết khi nhân viên/quản lý bổ sung Check-out',
                ]);
        }
    }

    /**
     * Khi đơn nghỉ phép được duyệt, tự động miễn (excuse) cảnh báo các ca bị ảnh hưởng.
     */
    public function excuseAlertsOnLeaveApproved(LeaveRequest $leaveRequest): void
    {
        $employeeId = $leaveRequest->employee_id;
        $fromDate = $leaveRequest->date_from;
        $toDate = $leaveRequest->date_to;

        $scheduleIds = ShiftSchedule::where('employee_id', $employeeId)
            ->whereBetween('work_date', [$fromDate, $toDate])
            ->pluck('id');

        if ($scheduleIds->isNotEmpty()) {
            AttendanceAlert::whereIn('shift_schedule_id', $scheduleIds)
                ->whereIn('status', ['open', 'seen'])
                ->update([
                    'status'          => 'excused',
                    'resolved_at'     => now(),
                    'resolved_by'     => auth()->id(),
                    'resolution_note' => "Tự động miễn do đơn xin nghỉ #{$leaveRequest->code} đã được duyệt.",
                ]);
        }
    }

    /**
     * Lấy danh sách cảnh báo còn mở (open/seen) cho nhân viên để hiển thị banner/modal.
     */
    public function getPendingAlertsForEmployee(int $employeeId, bool $onlyUnseen = false): array
    {
        // Quét cập nhật cảnh báo mới nhất
        $this->scanAlerts($employeeId, 14);

        // $onlyUnseen = true: chỉ lấy cảnh báo nhân viên CHƯA bấm tắt (popup), 'seen' sẽ không hiện lại
        $alerts = AttendanceAlert::with(['shiftSchedule.shift', 'shiftSchedule.branch'])
            ->where('employee_id', $employeeId)
            ->whereIn('status', $onlyUnseen ? ['open'] : ['open', 'seen'])
            ->orderByDesc('triggered_at')
            ->get();

        $missingCheckInCount = $alerts->where('alert_type', 'missing_check_in')->count();
        $missingCheckOutCount = $alerts->where('alert_type', 'missing_check_out')->count();
        $hasUnseen = $alerts->where('status', 'open')->isNotEmpty();

        return [
            'alerts'                  => $alerts,
            'total_count'             => $alerts->count(),
            'missing_check_in_count'  => $missingCheckInCount,
            'missing_check_out_count' => $missingCheckOutCount,
            'has_unseen'              => $hasUnseen,
        ];
    }

    /**
     * Đánh dấu các cảnh báo của nhân viên sang trạng thái 'seen' (đã xem, đóng banner).
     */
    public function dismissAlertsForEmployee(int $employeeId, ?int $alertId = null): int
    {
        $query = AttendanceAlert::where('employee_id', $employeeId)
            ->where('status', 'open');

        if ($alertId) {
            $query->where('id', $alertId);
        }

        return $query->update([
            'status'  => 'seen',
            'seen_at' => now(),
        ]);
    }

    /**
     * Kiểm tra xem ca làm việc có được bao phủ bởi đơn nghỉ phép đã duyệt không.
     */
    private function isCoveredByApprovedLeave(ShiftSchedule $schedule): bool
    {
        $dateStr = $schedule->work_date->toDateString();

        return LeaveRequest::where('employee_id', $schedule->employee_id)
            ->where('status', 'approved')
            ->where('date_from', '<=', $dateStr)
            ->where('date_to', '>=', $dateStr)
            ->exists();
    }
}
