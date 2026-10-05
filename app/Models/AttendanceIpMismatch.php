<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceIpMismatch extends Model
{
    protected $fillable = [
        'attendance_location_id',
        'employee_id',
        'ip',
    ];

    public function attendanceLocation(): BelongsTo
    {
        return $this->belongsTo(AttendanceLocation::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
