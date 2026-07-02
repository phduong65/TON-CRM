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
        'late_minutes',
        'early_minutes',
        'full_credit',
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
}
