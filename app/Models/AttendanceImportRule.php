<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceImportRule extends Model
{
    protected $fillable = [
        'type',
        'min_minutes',
        'max_minutes',
        'violation_id',
        'label',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'min_minutes' => 'integer',
            'max_minutes' => 'integer',
            'sort_order'  => 'integer',
        ];
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(Violation::class);
    }

    public function getTypeLabel(): string
    {
        return $this->type === 'late' ? 'Đi trễ' : 'Về sớm';
    }

    public function getRangeLabel(): string
    {
        if ($this->max_minutes === null) {
            return "Từ {$this->min_minutes} phút trở lên";
        }
        if ($this->min_minutes === 1) {
            return "Dưới {$this->max_minutes} phút";
        }
        return "Từ {$this->min_minutes} đến {$this->max_minutes} phút";
    }

    /**
     * Find the matching rule for a given type and minute count.
     */
    public static function matchFor(string $type, int $minutes): ?self
    {
        return static::where('type', $type)
            ->where('is_active', true)
            ->where('min_minutes', '<=', $minutes)
            ->where(function ($q) use ($minutes) {
                $q->whereNull('max_minutes')
                  ->orWhere('max_minutes', '>=', $minutes);
            })
            ->orderBy('min_minutes', 'desc') // most specific (highest min) wins
            ->first();
    }

    /**
     * Check if this rule's range overlaps with another rule of same type.
     */
    public function overlapsWithExisting(int $excludeId = 0): bool
    {
        return static::where('type', $this->type)
            ->where('id', '!=', $excludeId)
            ->where('is_active', true)
            ->where(function ($q) {
                $myMax = $this->max_minutes;
                $myMin = $this->min_minutes;

                $q->where(function ($inner) use ($myMin, $myMax) {
                    // existing.min <= myMax (or myMax is null) AND existing.max >= myMin (or existing.max is null)
                    $inner->where('min_minutes', '<=', $myMax ?? PHP_INT_MAX)
                          ->where(function ($x) use ($myMin) {
                              $x->whereNull('max_minutes')
                                ->orWhere('max_minutes', '>=', $myMin);
                          });
                });
            })
            ->exists();
    }
}
