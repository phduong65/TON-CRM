<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenaltyMember extends Model
{
    protected $fillable = [
        'penalty_id',
        'employee_id',
        'points_deducted',
        'money_deducted',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'points_deducted' => 'integer',
            'money_deducted' => 'decimal:2',
        ];
    }

    public function penalty(): BelongsTo
    {
        return $this->belongsTo(Penalty::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
