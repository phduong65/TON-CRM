<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Regulation extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'penalty_type',
        'default_points',
        'default_money',
        'is_active',
        'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'effective_date' => 'date',
            'default_points' => 'integer',
            'default_money' => 'decimal:2',
        ];
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }
}
