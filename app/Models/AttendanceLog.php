<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    protected $fillable = [
        'employee_id',
        'shift_schedule_id',
        'work_date',
        'check_in_at',
        'check_out_at',
        'check_in_method',
        'check_out_method',
        'check_in_lat',
        'check_in_lng',
        'check_out_lat',
        'check_out_lng',
        'check_in_ip',
        'check_out_ip',
        'check_in_location_id',
        'check_out_location_id',
        'check_in_device',
        'check_out_device',
        'late_minutes',
        'early_minutes',
        'full_credit',
        'overtime_hours',
    ];

    protected function casts(): array
    {
        return [
            'work_date'    => 'date:Y-m-d',
            'check_in_at'  => 'datetime',
            'check_out_at' => 'datetime',
            'late_minutes'  => 'integer',
            'early_minutes' => 'integer',
            'full_credit'   => 'boolean',
            'overtime_hours' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shiftSchedule(): BelongsTo
    {
        return $this->belongsTo(ShiftSchedule::class);
    }

    public function checkInLocation(): BelongsTo
    {
        return $this->belongsTo(AttendanceLocation::class, 'check_in_location_id');
    }

    public function checkOutLocation(): BelongsTo
    {
        return $this->belongsTo(AttendanceLocation::class, 'check_out_location_id');
    }

    /**
     * True nếu thiết bị (User-Agent) dùng lúc check-out khác với lúc check-in.
     * Chỉ mang tính cảnh báo — KHÔNG chặn chấm công.
     */
    public function deviceChanged(): bool
    {
        return $this->check_in_device !== null
            && $this->check_out_device !== null
            && $this->check_in_device !== $this->check_out_device;
    }

    /**
     * Số giờ làm thực tế (check-out - check-in), đã trừ giờ nghỉ giữa ca của Shift (nếu có) —
     * VD: ca văn phòng 9h-18h, nghỉ giữa 1 tiếng → 8 giờ công. Chấm công sớm hơn giờ vào ca hoặc
     * check-out muộn hơn giờ ra ca KHÔNG được tính thêm vào đây — giờ vào/ra thực tế bị giới hạn
     * trong đúng khung giờ ca trước khi tính. Tăng ca được cộng công riêng qua overtimeCong()
     * (từ overtime_hours, chỉ ghi nhận khi có yêu cầu "Tăng ca" được duyệt — không tự động suy
     * ra từ giờ chấm công thực tế sớm/muộn hơn ca). Truyền $shift nếu đã có sẵn (đã eager-load)
     * để tránh lazy-load quan hệ shiftSchedule.shift.
     */
    public function netWorkedHours(?Shift $shift = null): ?float
    {
        if (!$this->check_in_at || !$this->check_out_at) {
            return null;
        }

        $shift    = $this->resolveShift($shift);
        $checkIn  = $this->check_in_at;
        $checkOut = $this->check_out_at;

        if ($shift) {
            $scheduledStart = $checkIn->copy()->setTimeFromTimeString((string) $shift->start_time);
            $scheduledEnd   = $checkIn->copy()->setTimeFromTimeString((string) $shift->end_time);
            if ($shift->is_overnight && $scheduledEnd->lessThanOrEqualTo($scheduledStart)) {
                $scheduledEnd->addDay();
            }

            if ($checkIn->lessThan($scheduledStart)) {
                $checkIn = $scheduledStart;
            }
            if ($checkOut->greaterThan($scheduledEnd)) {
                $checkOut = $scheduledEnd;
            }
        }

        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            return 0.0;
        }

        $minutes = $checkIn->diffInMinutes($checkOut);
        $breakMinutes = $shift?->break_minutes ?? 0;

        return max(0, $minutes - $breakMinutes) / 60;
    }

    /**
     * Số "công" quy đổi từ giờ làm thực tế — dùng chung cho Báo cáo chấm công và Bảng chấm công:
     * - full_credit (đi muộn/về sớm đã được duyệt "Công thường"): luôn 1 công.
     * - Ca văn phòng/full-time (Shift::shift_type = fulltime, hoặc không xác định được ca): 1 ca
     *   chấm công đủ vào-ra = 1 công, không quy đổi theo giờ.
     * - Ca part-time: giờ làm thực tế / giờ công chuẩn của ca (Shift::standard_work_hours, mặc
     *   định 8h nếu không xác định được ca).
     * Giờ tăng ca đã được duyệt (overtime_hours — xem overtimeCong()) luôn được cộng thêm vào
     * kết quả trên, kể cả khi chưa có giờ vào/ra thực tế (VD: tăng ca vào ngày nghỉ).
     */
    public function computeCong(?Shift $shift = null): ?float
    {
        $shift        = $this->resolveShift($shift);
        $workedHours  = $this->netWorkedHours($shift);
        $overtimeCong = $this->overtimeCong($shift);

        if ($workedHours === null) {
            return $overtimeCong > 0 ? round($overtimeCong, 2) : null;
        }

        if ($this->full_credit) {
            return round(1.0 + $overtimeCong, 2);
        }

        if ($shift && $shift->isFulltimeCategory()) {
            return round(1.0 + $overtimeCong, 2);
        }

        $standardHours = $shift?->standardWorkHours() ?? 8.0;

        return round(($workedHours / $standardHours) + $overtimeCong, 2);
    }

    /**
     * Phần "công" quy đổi riêng từ giờ tăng ca đã duyệt (yêu cầu "Tăng ca" trong hub Yêu cầu &
     * Phê duyệt) — theo giờ công chuẩn của ca hôm đó (mặc định 8h nếu không xác định được ca),
     * cùng công thức với phần giờ làm thường trong computeCong().
     */
    public function overtimeCong(?Shift $shift = null): float
    {
        $overtimeHours = (float) $this->overtime_hours;
        if ($overtimeHours <= 0) {
            return 0.0;
        }

        $shift         = $this->resolveShift($shift);
        $standardHours = $shift?->standardWorkHours() ?? 8.0;

        return $overtimeHours / $standardHours;
    }

    /**
     * Trả về Shift để tính công: dùng $shift truyền vào nếu có (đã eager-load, tránh lazy-load),
     * nếu không thì lấy từ shiftSchedule->shift. Với ca linh hoạt (shiftSchedule->isFlexible()),
     * shift_id luôn null nên dựng 1 Shift tạm (không lưu DB) từ các cột custom_* của lịch xếp ca,
     * để tái dùng nguyên công thức tính công/giờ đã có trong Shift (overnight, break, tỷ lệ giờ/8h).
     */
    private function resolveShift(?Shift $shift): ?Shift
    {
        if ($shift) {
            return $shift;
        }

        $schedule = $this->shiftSchedule;

        if ($schedule?->isFlexible() && $schedule->custom_start_time) {
            return new Shift([
                'start_time'          => $schedule->custom_start_time,
                'end_time'            => $schedule->custom_end_time,
                'break_minutes'       => $schedule->custom_break_minutes ?? 0,
                'is_overnight'        => (bool) $schedule->custom_is_overnight,
                'shift_type'          => 'parttime',
                'standard_work_hours' => 8,
                'work_mode'           => $schedule->custom_is_wfh ? 'wfh' : 'onsite',
            ]);
        }

        return $schedule?->shift;
    }
}
