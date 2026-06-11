<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyEmployeeScore extends Model
{
    protected $fillable = [
        'employee_id',
        'month',
        'year',
        'initial_score',
        'deducted_points',
        'rewarded_points',
        'final_score',
        'zone',
    ];

    protected function casts(): array
    {
        return [
            'month'            => 'integer',
            'year'             => 'integer',
            'initial_score'    => 'integer',
            'deducted_points'  => 'integer',
            'rewarded_points'  => 'integer',
            'final_score'      => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public static function computeZone(int $score): string
    {
        $greenMin  = (int) Setting::getValue('greenzone_min', 90);
        $yellowMin = (int) Setting::getValue('yellowzone_min', 80);
        $orangeMin = (int) Setting::getValue('orangezone_min', 70);

        if ($score >= $greenMin)  return 'green';
        if ($score >= $yellowMin) return 'yellow';
        if ($score >= $orangeMin) return 'orange';
        return 'red';
    }

    public static function zoneLabel(string $zone): string
    {
        return match ($zone) {
            'green'  => 'Greenzone',
            'yellow' => 'Yellowzone',
            'orange' => 'Orangezone',
            'red'    => 'Redzone',
            default  => $zone,
        };
    }

    public static function zoneColor(string $zone): string
    {
        return match ($zone) {
            'green'  => 'emerald',
            'yellow' => 'yellow',
            'orange' => 'orange',
            'red'    => 'red',
            default  => 'slate',
        };
    }

    public static function zoneBadgeClass(string $zone): string
    {
        return match ($zone) {
            'green'  => 'badge-success',
            'yellow' => 'badge-warning',
            'orange' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
            'red'    => 'badge-danger',
            default  => 'badge-neutral',
        };
    }

    /**
     * Ensure a monthly record exists for the given employee/month/year.
     * Returns the existing or newly created record.
     */
    public static function ensureExists(int $employeeId, int $month, int $year): self
    {
        $initialScore = (int) Setting::getValue('default_score_per_month', 100);

        return static::firstOrCreate(
            ['employee_id' => $employeeId, 'month' => $month, 'year' => $year],
            [
                'initial_score'   => $initialScore,
                'deducted_points' => 0,
                'final_score'     => $initialScore,
                'zone'            => 'green',
            ]
        );
    }

    /**
     * Apply point deduction and recalculate zone.
     */
    public function deduct(int $points): void
    {
        $this->deducted_points = $this->deducted_points + $points;
        $this->final_score     = max(0, $this->initial_score + $this->rewarded_points - $this->deducted_points);
        $this->zone            = static::computeZone($this->final_score);
        $this->save();
    }

    /**
     * Apply point reward and recalculate zone.
     */
    public function reward(int $points): void
    {
        $this->rewarded_points = $this->rewarded_points + $points;
        $this->final_score     = max(0, $this->initial_score + $this->rewarded_points - $this->deducted_points);
        $this->zone            = static::computeZone($this->final_score);
        $this->save();
    }
}
