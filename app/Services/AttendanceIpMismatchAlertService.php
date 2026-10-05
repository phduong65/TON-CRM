<?php

namespace App\Services;

use App\Models\AttendanceIpMismatch;
use App\Models\AttendanceLocation;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class AttendanceIpMismatchAlertService
{
    /**
     * Số nhân viên khác nhau bị chặn vì "GPS đúng nhưng IP sai" tại cùng 1 điểm chấm công,
     * trong cùng 1 ngày, trước khi cảnh báo admin rằng IP văn phòng có thể đã đổi.
     */
    private const ALERT_THRESHOLD = 3;

    /**
     * Ghi nhận 1 lượt chấm công thất bại do GPS đúng nhưng IP không khớp WiFi văn phòng, và
     * cảnh báo admin (tối đa 1 lần/ngày/điểm chấm công) nếu đã đủ số nhân viên khác nhau bị
     * chặn hôm nay — dấu hiệu cho thấy `allowed_ips` có thể đã lỗi thời vì ISP đổi IP public.
     */
    public function recordAndMaybeAlert(AttendanceLocation $location, Employee $employee, string $ip): void
    {
        AttendanceIpMismatch::create([
            'attendance_location_id' => $location->id,
            'employee_id'            => $employee->id,
            'ip'                     => $ip,
        ]);

        DB::transaction(function () use ($location, $ip) {
            /** @var AttendanceLocation $location */
            $location = AttendanceLocation::lockForUpdate()->findOrFail($location->id);

            $today         = now()->toDateString();
            $distinctCount = $location->todayIpMismatchEmployeeCount();

            if ($distinctCount < self::ALERT_THRESHOLD) {
                return;
            }

            $alreadyAlertedToday = $location->ip_mismatch_alerted_at
                && $location->ip_mismatch_alerted_at->toDateString() === $today;

            if ($alreadyAlertedToday) {
                return;
            }

            $location->ip_mismatch_alerted_at = now();
            $location->save();

            $body = sprintf(
                'Điểm chấm công "%s" (%s): %d nhân viên có vị trí GPS đúng nhưng IP không khớp WiFi văn phòng hôm nay — IP gần nhất: %s. Có thể IP văn phòng đã đổi, vui lòng kiểm tra và cập nhật danh sách IP.',
                $location->name,
                $location->branch?->name ?? '—',
                $distinctCount,
                $ip
            );

            app(NotificationService::class)->sendToUsersWithPermission(
                'edit-attendance-locations',
                'attendance_ip_mismatch',
                'Có thể IP văn phòng đã đổi',
                $body,
                ['attendance_location_id' => $location->id]
            );
        });
    }

    /**
     * Khi một lượt chấm công 'gps_ip' thành công tại điểm chấm công này (VD: admin vừa sửa
     * `allowed_ips` giữa ngày), coi như sự cố IP đã được xác nhận là hết — xoá các bản ghi
     * mismatch hôm nay và mở lại cooldown cảnh báo, để nếu có đợt sự cố MỚI (VD IP văn phòng
     * đổi lần nữa trong ngày) thì vẫn cần đủ lại `ALERT_THRESHOLD` nhân viên khác nhau trước khi
     * cảnh báo, thay vì bị chặn cả ngày bởi cooldown của sự cố cũ đã xử lý xong.
     *
     * Bỏ qua ngay (không lock, không transaction) nếu điểm chấm công chưa từng được cảnh báo hôm
     * nay — đây là trường hợp phổ biến nhất (mọi lượt chấm công hợp lệ bình thường).
     */
    public function clearAlertIfResolved(int $locationId): void
    {
        $hasActiveAlert = AttendanceLocation::whereKey($locationId)
            ->whereNotNull('ip_mismatch_alerted_at')
            ->exists();

        if (!$hasActiveAlert) {
            return;
        }

        DB::transaction(function () use ($locationId) {
            /** @var AttendanceLocation|null $location */
            $location = AttendanceLocation::lockForUpdate()->find($locationId);

            if (!$location || !$location->ip_mismatch_alerted_at) {
                return;
            }

            $location->ipMismatches()
                ->whereDate('created_at', now()->toDateString())
                ->delete();

            $location->ip_mismatch_alerted_at = null;
            $location->save();
        });
    }
}
