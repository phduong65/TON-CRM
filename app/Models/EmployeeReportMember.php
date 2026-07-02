<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeReportMember extends Model
{
    protected $fillable = [
        'employee_report_id',
        'employee_id',
        'points_deducted',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(EmployeeReport::class, 'employee_report_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
