<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAlert extends Model
{
    protected $fillable = [
        'employee_id',
        'shift_schedule_id',
        'alert_type',
        'status',
        'triggered_at',
        'seen_at',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
            'seen_at'      => 'datetime',
            'resolved_at'  => 'datetime',
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

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'seen']);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function markSeen(): bool
    {
        if ($this->status === 'open') {
            return $this->update([
                'status'  => 'seen',
                'seen_at' => now(),
            ]);
        }

        return false;
    }

    public function resolve(?int $userId = null, ?string $note = null): bool
    {
        return $this->update([
            'status'          => 'resolved',
            'resolved_by'     => $userId,
            'resolved_at'     => now(),
            'resolution_note' => $note ?? $this->resolution_note,
        ]);
    }

    public function excuse(?int $userId = null, ?string $note = null): bool
    {
        return $this->update([
            'status'          => 'excused',
            'resolved_by'     => $userId,
            'resolved_at'     => now(),
            'resolution_note' => $note,
        ]);
    }

    public function alertTypeLabel(): string
    {
        return match ($this->alert_type) {
            'missing_check_in'  => 'Thiếu Check-in',
            'missing_check_out' => 'Thiếu Check-out',
            default             => $this->alert_type,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open'     => 'Chưa xem',
            'seen'     => 'Đã xem (chờ xử lý)',
            'resolved' => 'Đã bổ sung log',
            'excused'  => 'Đã miễn cảnh báo',
            default    => $this->status,
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'open'     => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
            'seen'     => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
            'resolved' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
            'excused'  => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700',
            default    => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        };
    }
}
