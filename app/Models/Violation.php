<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Violation extends Model
{
    protected $fillable = [
        'name',
        'description',
        'severity',
        'regulation_id',
        'penalty_type',
        'points_deducted',
        'money_deducted',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'       => 'boolean',
            'points_deducted' => 'integer',
            'money_deducted'  => 'decimal:2',
        ];
    }

    public function regulation(): BelongsTo
    {
        return $this->belongsTo(Regulation::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }
}
