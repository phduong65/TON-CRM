<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftScheduleRecurrence extends Model
{
    protected $fillable = [
        'batch_id',
        'shift_ids',
        'employee_ids',
        'weekdays',
        'starts_on',
        'last_generated_through',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'shift_ids'                => 'array',
            'employee_ids'             => 'array',
            'weekdays'                 => 'array',
            'starts_on'                => 'date:Y-m-d',
            'last_generated_through'   => 'date:Y-m-d',
            'is_active'                => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
