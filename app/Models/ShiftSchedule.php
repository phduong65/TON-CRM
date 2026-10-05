<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShiftSchedule extends Model
{
    protected $fillable = [
        'employee_id',
        'shift_id',
        'branch_id',
        'team_id',
        'work_date',
        'assignment_type',
        'alternative_group_id',
        'batch_id',
        'status',
        'holiday_id',
        'note',
        'assigned_by',
        'custom_start_time',
        'custom_end_time',
        'custom_break_minutes',
        'custom_is_overnight',
        'custom_is_wfh',
        'adjusted_start_time',
        'adjusted_end_time',
    ];

    protected function casts(): array
    {
        return [
            'work_date'                   => 'date:Y-m-d',
            'custom_is_overnight'         => 'boolean',
            'custom_is_wfh'               => 'boolean',
            'checkin_reminder_sent_at'    => 'datetime',
            'checkout_reminder_sent_at'   => 'datetime',
            'checkin_late_alert_sent_at'  => 'datetime',
            'checkout_late_alert_sent_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Danh sách ca "đang diễn ra" cho nhân viên, tách theo mốc 3h sáng:
     * - Trước 3h sáng: CHỈ hiện ca qua đêm của hôm qua (VD 22h-06h) — tránh ca đó "biến mất" ngay
     *   lúc 00:00 dù chưa tới giờ kết thúc, khiến nhân viên không thấy nút / không thể check-out.
     *   Ca của chính "hôm nay" (kể cả ca tự bắt đầu sớm trong khung 0h-3h, VD 01h-09h) KHÔNG hiện
     *   trong khung này — chủ đích để tránh nhầm lẫn/check-in sớm cho ca chưa tới giờ.
     * - Từ 3h sáng trở đi: CHỈ hiện ca của hôm nay, không còn ca qua đêm hôm qua.
     * - Ngoại lệ ở cả hai khung: ca hôm qua đã check-in mà chưa check-out vẫn hiện để check-out được.
     * Hai nhánh trên không gộp (không OR) — dùng chung bởi AttendanceController và DashboardController
     * (widget chấm công nhanh) để tránh 2 nơi định nghĩa lệch nhau logic ca qua đêm.
     */
    public static function relevantForEmployeeToday(int $employeeId, string $today)
    {
        $yesterday      = Carbon::parse($today)->subDay()->toDateString();
        $isEarlyMorning = now()->lessThan(now()->copy()->startOfDay()->addHours(3));

        return static::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'scheduled')
            ->where(function ($query) use ($today, $yesterday, $isEarlyMorning) {
                if ($isEarlyMorning) {
                    $query->where('work_date', $yesterday)
                        ->where(function ($qq) {
                            $qq->where('custom_is_overnight', true)
                                ->orWhereColumn('custom_end_time', '<=', 'custom_start_time')
                                ->orWhereHas('shift', function ($sq) {
                                    $sq->where('is_overnight', true)
                                        ->orWhereColumn('end_time', '<=', 'start_time');
                                });
                        });
                } else {
                    $query->where('work_date', $today);
                }

                // Ca của hôm qua đã check-in mà chưa check-out luôn phải còn hiện (dù không qua đêm
                // hoặc đã qua mốc 3h sáng) — nếu không, nhân viên sang ngày mới sẽ mất nút check-out.
                $query->orWhere(function ($qq) use ($yesterday) {
                    $qq->where('work_date', $yesterday)
                        ->whereHas('attendanceLog', function ($lq) {
                            $lq->whereNotNull('check_in_at')->whereNull('check_out_at');
                        });
                });
            });
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function attendanceAlerts(): HasMany
    {
        return $this->hasMany(AttendanceAlert::class);
    }

    public function alternativeSchedules()
    {
        if (!$this->alternative_group_id) {
            return collect();
        }

        return static::where('employee_id', $this->employee_id)
            ->where('work_date', $this->work_date)
            ->where('alternative_group_id', $this->alternative_group_id)
            ->where('id', '!=', $this->id)
            ->get();
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function attendanceLog(): HasOne
    {
        return $this->hasOne(AttendanceLog::class);
    }

    /**
     * Ca linh hoạt: xếp giờ tuỳ chỉnh cho đúng 1 ngày, không dùng mẫu Shift có sẵn
     * (shift_id null, giờ/nghỉ giữa ca/qua đêm/WFH lấy từ các cột custom_*).
     */
    public function isFlexible(): bool
    {
        return $this->shift_id === null;
    }

    /**
     * Chụp lại thông số giờ ca tại thời điểm chấm công, để ghi vào AttendanceLog — tính công
     * không còn phụ thuộc vào việc ca xếp (ShiftSchedule) này có bị xoá/sửa sau đó hay không
     * (shift_schedule_id dùng nullOnDelete(), xem migration add_shift_snapshot_to_attendance_logs_table).
     *
     * Giờ vào/ra lấy từ effectiveShift() (đã áp dụng adjusted_start_time/adjusted_end_time nếu
     * có nghỉ theo giờ được duyệt) — KHÔNG lấy trực tiếp từ $this->shift để snapshot phản ánh
     * đúng khung giờ còn lại sau khi trừ phần nghỉ.
     */
    public function shiftSnapshotAttributes(): array
    {
        $effective = $this->effectiveShift();
        if (!$effective) {
            return [];
        }

        $hasLeaveAdjustment = $this->adjusted_start_time || $this->adjusted_end_time;

        if ($this->isFlexible() && $this->custom_start_time) {
            return [
                'shift_start_time'          => $effective->start_time,
                'shift_end_time'            => $effective->end_time,
                // Nghỉ theo giờ luôn nghỉ sát mép ca (đầu hoặc cuối) nên phần còn lại là 1 khối
                // làm việc liên tục, không còn giờ nghỉ giữa ca để trừ.
                'shift_break_minutes'       => $hasLeaveAdjustment ? 0 : ($this->custom_break_minutes ?? 0),
                'shift_is_overnight'        => (bool) $this->custom_is_overnight,
                'shift_type'                => 'parttime',
                'shift_standard_work_hours' => 8,
            ];
        }

        if ($this->shift) {
            return [
                'shift_start_time'          => $effective->start_time,
                'shift_end_time'            => $effective->end_time,
                'shift_break_minutes'       => $hasLeaveAdjustment ? 0 : $this->shift->break_minutes,
                'shift_is_overnight'        => (bool) $this->shift->is_overnight,
                'shift_type'                => $this->shift->shift_type,
                'shift_standard_work_hours' => $this->shift->standardWorkHours(),
            ];
        }

        return [];
    }

    /**
     * Shift dùng để tính giờ vào/ra: shift thật nếu có, hoặc Shift tạm (không lưu DB) dựng từ
     * custom_start_time/custom_end_time của ca linh hoạt — không cho phép trễ/sớm (grace = 0).
     * Nếu có nghỉ theo giờ đã được duyệt (adjusted_start_time/adjusted_end_time), ghi đè lên
     * đúng 1 phía tương ứng (giữ nguyên phía còn lại) để phản ánh khung giờ còn lại phải chấm
     * công — xem LeaveRequestsController::approve().
     */
    public function effectiveShift(): ?Shift
    {
        $shift = null;

        if ($this->shift) {
            $shift = $this->shift;
        } elseif ($this->isFlexible() && $this->custom_start_time) {
            $shift = new Shift([
                'start_time'            => $this->custom_start_time,
                'end_time'              => $this->custom_end_time,
                'is_overnight'          => (bool) $this->custom_is_overnight,
                'work_mode'             => $this->custom_is_wfh ? 'wfh' : 'onsite',
                'grace_late_minutes'    => 0,
                'grace_early_minutes'   => 0,
            ]);
        }

        if (!$shift) {
            return null;
        }

        if ($this->adjusted_start_time || $this->adjusted_end_time) {
            $shift = clone $shift;
            if ($this->adjusted_start_time) {
                $shift->start_time = $this->adjusted_start_time;
            }
            if ($this->adjusted_end_time) {
                $shift->end_time = $this->adjusted_end_time;
            }
        }

        return $shift;
    }

    /**
     * Ca đã "bỏ lỡ" — quá giờ kết thúc ca mà nhân viên chưa từng check-in. Dùng để tách hẳn ca
     * này khỏi danh sách "đang diễn ra" trên trang chấm công/dashboard, tránh nhân viên nhầm
     * tưởng vẫn còn check-in được. KHÔNG áp dụng cho check-out: nếu đã check-in (dù trễ), ca vẫn
     * ở trạng thái active cho tới khi check-out xong, bất kể đã quá giờ kết thúc ca hay chưa —
     * check-out muộn vẫn là hành vi hợp lệ, không bị coi là "bỏ lỡ".
     */
    public function isMissed(): bool
    {
        if ($this->attendanceLog?->check_in_at) {
            return false;
        }

        $endAt = $this->endAt();

        return $endAt !== null && now()->isAfter($endAt);
    }

    /**
     * Thời điểm bắt đầu ca theo work_date của chính bản ghi này (không phải "hôm nay") — dùng để
     * tính lịch nhắc/cảnh báo check-in trước và sau giờ vào ca.
     */
    public function startAt(): ?Carbon
    {
        $shift = $this->effectiveShift();
        if (!$shift || !$shift->start_time) {
            return null;
        }

        return Carbon::parse($this->work_date->toDateString() . ' ' . $shift->start_time);
    }

    /**
     * Thời điểm kết thúc ca — cộng thêm 1 ngày nếu là ca qua đêm (giống Shift::durationMinutes()).
     * Suy ra ca qua đêm trực tiếp từ giờ kết thúc <= giờ bắt đầu, KHÔNG chỉ dựa vào cờ
     * is_overnight — cờ này nhập tay (checkbox) nên có thể bị sai, khiến ca thực sự qua đêm
     * (VD 18h-24h) bị tính endAt() trước cả startAt(), làm sai khung giờ chặn/tính công.
     */
    public function endAt(): ?Carbon
    {
        $shift = $this->effectiveShift();
        if (!$shift || !$shift->end_time) {
            return null;
        }

        $start = Carbon::parse($this->work_date->toDateString() . ' ' . $shift->start_time);
        $end   = Carbon::parse($this->work_date->toDateString() . ' ' . $shift->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $end;
    }
}
