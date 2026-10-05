<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    protected $fillable = [
        'employee_id',
        'shift_schedule_id',
        'holiday_id',
        'source',
        'shift_start_time',
        'shift_end_time',
        'shift_break_minutes',
        'shift_is_overnight',
        'shift_type',
        'shift_standard_work_hours',
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
        'late_penalty_id',
        'early_penalty_id',
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
            'shift_break_minutes'       => 'integer',
            'shift_is_overnight'        => 'boolean',
            'shift_standard_work_hours' => 'decimal:2',
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

    public function holiday(): BelongsTo
    {
        return $this->belongsTo(Holiday::class);
    }

    /** Bản ghi chấm công nghỉ lễ tự tạo (khối được nghỉ lễ, không cần check-in). */
    public function isHoliday(): bool
    {
        return $this->source === 'holiday' || $this->holiday_id !== null;
    }

    public function checkInLocation(): BelongsTo
    {
        return $this->belongsTo(AttendanceLocation::class, 'check_in_location_id');
    }

    public function checkOutLocation(): BelongsTo
    {
        return $this->belongsTo(AttendanceLocation::class, 'check_out_location_id');
    }

    public function latePenalty(): BelongsTo
    {
        return $this->belongsTo(Penalty::class, 'late_penalty_id');
    }

    public function earlyPenalty(): BelongsTo
    {
        return $this->belongsTo(Penalty::class, 'early_penalty_id');
    }

    /**
     * True nếu THIẾT BỊ VẬT LÝ dùng lúc check-out khác với lúc check-in. Chỉ mang tính cảnh
     * báo — KHÔNG chặn chấm công. So sánh theo deviceSignature() (nền tảng/dòng máy) chứ
     * KHÔNG so nguyên văn User-Agent — nếu không, đổi trình duyệt (VD Safari → Chrome) trên
     * cùng 1 điện thoại sẽ bị báo nhầm là "khác thiết bị" dù vẫn là 1 máy.
     */
    public function deviceChanged(): bool
    {
        $checkInSignature  = self::deviceSignature($this->check_in_device);
        $checkOutSignature = self::deviceSignature($this->check_out_device);

        return $checkInSignature !== null
            && $checkOutSignature !== null
            && $checkInSignature !== $checkOutSignature;
    }

    /**
     * Rút gọn User-Agent về phần thiết bị/hệ điều hành — thường nằm trong cặp ngoặc đơn đầu
     * tiên (VD "(iPhone; CPU iPhone OS 17_0 like Mac OS X)", "(Linux; Android 13; SM-G991B)").
     * Phần này giữ nguyên dù dùng trình duyệt nào (Safari/Chrome/CriOS/Firefox...) trên cùng
     * một máy, nên so sánh ở đây tránh báo nhầm khi nhân viên chỉ đổi app trình duyệt — vẫn
     * phát hiện đúng khi đổi sang điện thoại/hệ điều hành thực sự khác.
     */
    private static function deviceSignature(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        if (preg_match('/\(([^)]+)\)/', $userAgent, $matches)) {
            return strtolower(trim($matches[1]));
        }

        return strtolower(trim($userAgent));
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
            // Suy ra ca qua đêm trực tiếp từ giờ kết thúc <= giờ bắt đầu, KHÔNG chỉ dựa vào cờ
            // is_overnight (nhập tay, có thể sai/bị bỏ quên) — nếu không, ca thực sự qua đêm
            // (VD 18h-24h) mà cờ bị sai sẽ khiến scheduledEnd nằm TRƯỚC scheduledStart, checkOut
            // sau khi clamp trở thành <= checkIn, và netWorkedHours() trả về 0 dù đã chấm công đủ.
            if ($scheduledEnd->lessThanOrEqualTo($scheduledStart)) {
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
     * Số "công" quy đổi từ giờ làm thực tế — dùng chung cho Báo cáo chấm công, Lịch sử chấm công
     * của tôi, Xuất Excel và Bảng chấm công:
     * - $partialLeaveFraction (ngày đó có đơn nghỉ theo giờ đã duyệt — truyền day_fraction của
     *   đơn vào): công phần đi làm cố định = 1 - tỉ lệ nghỉ (0 nếu chưa chấm công đủ vào/ra phần
     *   ca còn lại).
     * - Mọi loại ca (kể cả Shift::shift_type = fulltime): giờ làm thực tế / giờ công chuẩn của ca
     *   (Shift::standard_work_hours, mặc định 8h nếu không xác định được ca) — đi trễ/về sớm LUÔN
     *   trừ công theo đúng số giờ chấm công thực tế, không còn "1 ca đủ vào-ra = 1 công" cứng như
     *   trước cho ca full-time.
     * - full_credit (đi muộn/về sớm đã được duyệt "Công thường" qua yêu cầu "Đi muộn về sớm"):
     *   CHỈ dùng để tha lỗi late_minutes/early_minutes khỏi bị tính kỷ luật/nhắc nhở (xem
     *   StaffRequestsController::applyLateEarlyForgiveness()) — KHÔNG còn ảnh hưởng tới công,
     *   công luôn tính đúng theo giờ chấm công thực tế dù đã "thả lỗi" trễ/sớm.
     * Giờ tăng ca đã được duyệt (overtime_hours — xem overtimeCong()) luôn được cộng thêm vào
     * kết quả trên, kể cả khi chưa có giờ vào/ra thực tế (VD: tăng ca vào ngày nghỉ).
     */
    public function computeCong(?Shift $shift = null, ?float $partialLeaveFraction = null): ?float
    {
        $shift        = $this->resolveShift($shift);
        $overtimeCong = $this->overtimeCong($shift);

        if ($partialLeaveFraction !== null) {
            $workCredit = ($this->check_in_at && $this->check_out_at) ? round(1 - $partialLeaveFraction, 2) : 0.0;

            return round($workCredit + $overtimeCong, 2);
        }

        $workedHours = $this->netWorkedHours($shift);

        if ($workedHours === null) {
            return $overtimeCong > 0 ? round($overtimeCong, 2) : null;
        }

        $standardHours = $shift?->standardWorkHours() ?? 8.0;
        $workedCong    = $workedHours / $standardHours;

        return round($workedCong + $overtimeCong, 2);
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
     * Trả về Shift để tính công: dùng $shift truyền vào nếu có (đã eager-load, tránh lazy-load).
     * Ưu tiên tiếp theo là snapshot giờ ca đã lưu ngay trên bản ghi này lúc chấm công (cột
     * shift_start_time/shift_end_time/...) — vì shift_schedule_id dùng nullOnDelete(), ShiftSchedule
     * gốc có thể đã bị xoá/sửa lại sau đó (VD xếp lại lịch tuần) khiến quan hệ shiftSchedule->shift
     * không còn đúng hoặc null, làm sai lệch giờ công của dữ liệu lịch sử. Chỉ khi bản ghi cũ chưa
     * có snapshot (tạo trước khi có cột này) mới rơi về shiftSchedule->shift/custom_* như cũ.
     */
    private function resolveShift(?Shift $shift): ?Shift
    {
        if ($shift) {
            return $shift;
        }

        if ($this->shift_start_time) {
            return new Shift([
                'start_time'          => $this->shift_start_time,
                'end_time'            => $this->shift_end_time,
                'break_minutes'       => $this->shift_break_minutes ?? 0,
                'is_overnight'        => (bool) $this->shift_is_overnight,
                'shift_type'          => $this->shift_type,
                'standard_work_hours' => $this->shift_standard_work_hours,
            ]);
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
