<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftCoverageRequirement extends Model
{
    protected $fillable = [
        'branch_id',
        'team_id',
        'name',
        'days_of_week',
        'start_time',
        'end_time',
        'minimum_staff',
        'target_staff',
        'effective_from',
        'effective_until',
        'shift_id',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week'    => 'array',
            'minimum_staff'   => 'integer',
            'target_staff'    => 'integer',
            'effective_from'  => 'date:Y-m-d',
            'effective_until' => 'date:Y-m-d',
            'is_active'       => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOvernight(): bool
    {
        $start = substr((string) $this->start_time, 0, 5);
        $end   = substr((string) $this->end_time, 0, 5);

        return $end <= $start || $end === '00:00';
    }

    public function appliesToDayOfWeek(int $isoDayOfWeek): bool
    {
        return in_array($isoDayOfWeek, $this->days_of_week ?? [], true);
    }

    public function isActiveOnDate(Carbon|string $date): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $carbon = is_string($date) ? Carbon::parse($date) : $date->copy();
        $dateStr = $carbon->toDateString();

        if ($this->effective_from && $this->effective_from->toDateString() > $dateStr) {
            return false;
        }

        if ($this->effective_until && $this->effective_until->toDateString() < $dateStr) {
            return false;
        }

        return $this->appliesToDayOfWeek($carbon->dayOfWeekIso);
    }

    public function daysLabel(): string
    {
        $map = [
            1 => 'T2',
            2 => 'T3',
            3 => 'T4',
            4 => 'T5',
            5 => 'T6',
            6 => 'T7',
            7 => 'CN',
        ];

        $days = collect($this->days_of_week ?? [])->sort()->map(fn($d) => $map[$d] ?? "T{$d}")->values();

        if ($days->count() === 7) {
            return 'Cả tuần (T2–CN)';
        }

        if ($days->count() === 5 && $days->all() === ['T2', 'T3', 'T4', 'T5', 'T6']) {
            return 'T2–T6';
        }

        return $days->implode(', ');
    }
}
