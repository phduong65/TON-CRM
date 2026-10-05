<?php

namespace App\Support\Concerns;

use App\Models\Shift;
use Carbon\Carbon;

trait ResolvesOvernightCheckOutDate
{
    /**
     * Xác định ngày của "giờ ra" khi ghép tay với work_date (sửa/tạo chấm công thủ công, hoặc
     * duyệt yêu cầu "Lượt chấm công"): nếu ca đã xếp là ca qua đêm — suy ra trực tiếp từ
     * end_time <= start_time, KHÔNG chỉ dựa vào cờ is_overnight (nhập tay, có thể sai, xem
     * AttendanceLog::netWorkedHours()) — và giờ ra nhập vào rơi vào "rạng sáng" so với giờ VÀO
     * THỰC TẾ ($checkInTime, nếu biết), giờ ra đó thuộc NGÀY HÔM SAU work_date. VD ca 18h-24h,
     * nhập "12:00 AM"/"01:00 AM" cho giờ ra phải hiểu là rạng sáng hôm sau, không phải cùng ngày
     * với giờ vào ca — nếu không, check_out_at bị lưu TRƯỚC check_in_at, khiến
     * netWorkedHours()/công tính ra 0.
     *
     * So sánh với $checkInTime (giờ vào thực tế đang có/đang sửa) thay vì luôn dùng giờ bắt đầu
     * lý thuyết của ca ($shift->start_time) — nếu giờ vào thực tế lệch hẳn khỏi khung giờ ca (VD
     * chấm bù cho khung giờ hoàn toàn khác ca gốc, như 11h-15h trên ca gốc 17h-01h), so với giờ
     * bắt đầu ca sẽ luôn bị hiểu nhầm là "rạng sáng hôm sau" dù giờ ra thực tế đã sau giờ vào
     * thực tế trong CÙNG một ngày, khiến check_out_at bị đẩy sai sang hôm sau và netWorkedHours()
     * bị kẹp (clamp) lệch theo khung giờ ca gốc. Chỉ rơi về $shift->start_time khi không có giờ
     * vào thực tế nào để đối chiếu (giữ hành vi cũ).
     */
    protected function resolveCheckOutDate(string $workDate, string $checkOutTime, ?Shift $shift, ?string $checkInTime = null): string
    {
        if (!$shift || !$shift->start_time || !$shift->end_time) {
            return $workDate;
        }

        $startHm = substr($shift->start_time, 0, 5);
        $endHm   = substr($shift->end_time, 0, 5);

        if ($endHm > $startHm) {
            return $workDate;
        }

        $referenceHm = $checkInTime !== null ? substr($checkInTime, 0, 5) : $startHm;

        return $checkOutTime <= $referenceHm ? Carbon::parse($workDate)->addDay()->toDateString() : $workDate;
    }
}
