<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Holiday extends Model
{
    protected $fillable = [
        'date',
        'name',
        'applies_to_all',
        'is_paid',
        'bonus_amount',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date'           => 'date:Y-m-d',
            'applies_to_all' => 'boolean',
            'is_paid'        => 'boolean',
            'bonus_amount'   => 'decimal:2',
            'is_active'      => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Các bộ phận (team) mà ngày lễ áp dụng — mỗi team đã gắn 1 chi nhánh. */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'holiday_teams')->withTimestamps();
    }

    /**
     * Ngày lễ này có áp dụng cho nhân viên $employee không: hoặc áp dụng toàn công ty
     * (applies_to_all), hoặc bộ phận (team_id) của nhân viên nằm trong danh sách team đã chọn.
     * Truyền sẵn $teamIds (đã pluck) để tránh N+1 khi lặp qua nhiều nhân viên.
     */
    public function appliesToEmployee(Employee $employee, ?array $teamIds = null): bool
    {
        if ($this->applies_to_all) {
            return true;
        }

        $teamIds ??= $this->teams()->pluck('teams.id')->all();

        return $employee->team_id !== null && in_array($employee->team_id, $teamIds, true);
    }
}
