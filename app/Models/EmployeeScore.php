<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeScore extends Model
{
    protected $fillable = [
        'employee_id',
        'points',
        'reason',
        'type',
        'reference_type',
        'reference_id',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
