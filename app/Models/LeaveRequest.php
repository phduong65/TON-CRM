<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'employee_id',
        'date_from',
        'date_to',
        'shift_schedule_id',
        'type',
        'reason',
        'handover_to',
        'handover_employee_id',
        'handover_phone',
        'handover_note',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'date_from'   => 'date:Y-m-d',
            'date_to'     => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function shiftSchedule(): BelongsTo
    {
        return $this->belongsTo(ShiftSchedule::class);
    }

    public function handoverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'handover_employee_id');
    }

    public function daysCount(): int
    {
        return $this->date_from->diffInDays($this->date_to) + 1;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'annual' => 'Nghỉ phép năm',
            'unpaid' => 'Nghỉ không lương',
            'sick'   => 'Nghỉ ốm',
            default  => 'Khác',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'  => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'rejected' => 'Từ chối',
            default    => $this->status,
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'pending'  => 'badge-warning',
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            default    => 'badge-neutral',
        };
    }
}
