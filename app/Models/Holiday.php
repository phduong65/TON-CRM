<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    protected $fillable = [
        'date',
        'name',
        'is_paid',
        'bonus_amount',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date'         => 'date:Y-m-d',
            'is_paid'      => 'boolean',
            'bonus_amount' => 'decimal:2',
            'is_active'    => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
