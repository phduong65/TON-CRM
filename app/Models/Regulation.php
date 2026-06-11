<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Regulation extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'effective_date' => 'date',
        ];
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }
}
