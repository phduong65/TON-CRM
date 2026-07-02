<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class EmployeeReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'reporter_employee_id',
        'reported_employee_id',
        'type',
        'team_id',
        'violation_id',
        'description',
        'evidence_note',
        'evidence_files',
        'status',
        'reward_points',
        'deducted_points',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at'    => 'datetime',
            'reward_points'  => 'integer',
            'evidence_files' => 'array',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporter_employee_id');
    }

    public function reported(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reported_employee_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(EmployeeReportMember::class, 'employee_report_id');
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(Violation::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'team'  => 'Cả nhóm',
            'joint' => 'Liên đới',
            default => 'Cá nhân',
        };
    }

    /**
     * All employees targeted by this report (primary + members), deduplicated.
     */
    public function targetEmployees(): Collection
    {
        $this->loadMissing(['reported', 'members.employee']);

        return collect([$this->reported])
            ->merge($this->members->pluck('employee'))
            ->filter()
            ->unique('id')
            ->values();
    }

    /** Target employees that actually get points deducted (Admin/Director are exempt). */
    public function chargeableTargetEmployees(): Collection
    {
        return $this->targetEmployees()->reject(fn($e) => $e->isExemptFromScoring())->values();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'  => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'rejected' => 'Từ chối',
            default    => $this->status,
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'pending'  => 'badge-warning',
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            default    => 'badge-neutral',
        };
    }
}
