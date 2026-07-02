<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\AnnualLeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnualLeaveServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployee(array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'code'            => 'EMP-' . uniqid(),
            'name'            => 'Test Employee',
            'is_active'       => true,
            'employment_type' => 'full_time',
            'is_office'       => true,
        ], $overrides));
    }

    public function test_ineligible_employee_has_zero_entitlement(): void
    {
        $partTime = $this->makeEmployee(['employment_type' => 'part_time', 'is_office' => true]);
        $notOffice = $this->makeEmployee(['employment_type' => 'full_time', 'is_office' => false]);

        $service = new AnnualLeaveService();

        $this->assertEquals(0.0, $service->entitledDays($partTime, now()->year));
        $this->assertEquals(0.0, $service->entitledDays($notOffice, now()->year));
    }

    public function test_eligible_long_tenured_employee_accrues_one_day_per_completed_month(): void
    {
        $employee = $this->makeEmployee(['joined_at' => now()->subYears(2)->toDateString()]);
        $service  = new AnnualLeaveService();

        $expectedMonths = min(12, (int) floor(now()->startOfYear()->diffInMonths(now())));
        $this->assertEquals((float) $expectedMonths, $service->entitledDays($employee, now()->year));
    }

    public function test_entitlement_is_capped_at_twelve_days(): void
    {
        $employee = $this->makeEmployee(['joined_at' => now()->subYears(3)->toDateString()]);
        $service  = new AnnualLeaveService();

        $this->assertLessThanOrEqual(12, $service->entitledDays($employee, now()->year));
    }

    public function test_mid_year_hire_accrues_from_join_date_only(): void
    {
        $joinedAt = now()->startOfYear()->addMonths(2); // hired 2 months into the year
        $employee = $this->makeEmployee(['joined_at' => $joinedAt->toDateString()]);
        $service  = new AnnualLeaveService();

        $expectedMonths = min(12, (int) floor($joinedAt->diffInMonths(now())));
        $this->assertEquals((float) $expectedMonths, $service->entitledDays($employee, now()->year));
    }

    public function test_remaining_days_subtracts_approved_annual_leave_usage(): void
    {
        $employee = $this->makeEmployee(['joined_at' => now()->subYears(2)->toDateString()]);

        LeaveRequest::create([
            'code' => 'LR-1', 'employee_id' => $employee->id,
            'date_from' => now()->subDays(2)->toDateString(), 'date_to' => now()->toDateString(),
            'type' => 'annual', 'reason' => 'Test', 'status' => 'approved',
        ]);

        $service   = new AnnualLeaveService();
        $entitled  = $service->entitledDays($employee, now()->year);
        $remaining = $service->remainingDays($employee, now()->year);

        $this->assertEquals(round($entitled - 3, 2), $remaining);
    }

    public function test_rejected_and_pending_leave_do_not_reduce_balance(): void
    {
        $employee = $this->makeEmployee(['joined_at' => now()->subYears(2)->toDateString()]);

        LeaveRequest::create([
            'code' => 'LR-2', 'employee_id' => $employee->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
            'type' => 'annual', 'reason' => 'Test', 'status' => 'rejected',
        ]);
        LeaveRequest::create([
            'code' => 'LR-3', 'employee_id' => $employee->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(),
            'type' => 'annual', 'reason' => 'Test', 'status' => 'pending',
        ]);

        $service = new AnnualLeaveService();
        $this->assertEquals($service->entitledDays($employee, now()->year), $service->remainingDays($employee, now()->year));
    }
}
