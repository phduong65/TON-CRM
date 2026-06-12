<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\MonthlyEmployeeScore;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyEmployeeScoreTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployee(): Employee
    {
        return Employee::create([
            'code'     => 'EMP-' . uniqid(),
            'name'     => 'Test Employee',
            'is_active' => true,
        ]);
    }

    private function makeScore(Employee $employee, int $initial = 100): MonthlyEmployeeScore
    {
        return MonthlyEmployeeScore::create([
            'employee_id'    => $employee->id,
            'month'          => now()->month,
            'year'           => now()->year,
            'initial_score'  => $initial,
            'deducted_points' => 0,
            'rewarded_points' => 0,
            'final_score'    => $initial,
            'zone'           => 'green',
        ]);
    }

    // ── deduct() ─────────────────────────────────────────────────────────────

    public function test_deduct_reduces_final_score(): void
    {
        $score = $this->makeScore($this->makeEmployee());

        $score->deduct(20);

        $score->refresh();
        $this->assertEquals(80, $score->final_score);
        $this->assertEquals(20, $score->deducted_points);
    }

    public function test_deduct_cannot_go_below_zero(): void
    {
        $score = $this->makeScore($this->makeEmployee(), 30);

        $score->deduct(50);

        $score->refresh();
        $this->assertEquals(0, $score->final_score);
    }

    public function test_sequential_deductions_accumulate_correctly(): void
    {
        $score = $this->makeScore($this->makeEmployee());

        $score->deduct(10);
        $score->refresh();
        $score->deduct(15);
        $score->refresh();

        $this->assertEquals(75, $score->final_score);
        $this->assertEquals(25, $score->deducted_points);
    }

    public function test_deduct_updates_zone_to_yellow(): void
    {
        Setting::create(['key' => 'greenzone_min', 'value' => 90]);
        Setting::create(['key' => 'yellowzone_min', 'value' => 80]);
        Setting::create(['key' => 'orangezone_min', 'value' => 70]);

        $score = $this->makeScore($this->makeEmployee());

        $score->deduct(15); // 100 - 15 = 85 → yellowzone

        $score->refresh();
        $this->assertEquals('yellow', $score->zone);
    }

    public function test_deduct_updates_zone_to_red(): void
    {
        Setting::create(['key' => 'greenzone_min', 'value' => 90]);
        Setting::create(['key' => 'yellowzone_min', 'value' => 80]);
        Setting::create(['key' => 'orangezone_min', 'value' => 70]);

        $score = $this->makeScore($this->makeEmployee());

        $score->deduct(40); // 100 - 40 = 60 → redzone

        $score->refresh();
        $this->assertEquals('red', $score->zone);
    }

    // ── reward() ─────────────────────────────────────────────────────────────

    public function test_reward_increases_final_score(): void
    {
        $score = $this->makeScore($this->makeEmployee());

        $score->reward(10);

        $score->refresh();
        $this->assertEquals(110, $score->final_score);
        $this->assertEquals(10, $score->rewarded_points);
    }

    public function test_sequential_rewards_accumulate_correctly(): void
    {
        $score = $this->makeScore($this->makeEmployee());

        $score->reward(5);
        $score->refresh();
        $score->reward(5);
        $score->refresh();

        $this->assertEquals(110, $score->final_score);
        $this->assertEquals(10, $score->rewarded_points);
    }

    public function test_deduct_and_reward_interact_correctly(): void
    {
        $score = $this->makeScore($this->makeEmployee());

        $score->deduct(30); // 100 - 30 = 70
        $score->refresh();
        $score->reward(10); // 100 + 10 - 30 = 80
        $score->refresh();

        $this->assertEquals(80, $score->final_score);
    }

    // ── computeZone() ────────────────────────────────────────────────────────

    public function test_compute_zone_green(): void
    {
        Setting::create(['key' => 'greenzone_min', 'value' => 90]);
        Setting::create(['key' => 'yellowzone_min', 'value' => 80]);
        Setting::create(['key' => 'orangezone_min', 'value' => 70]);

        $this->assertEquals('green', MonthlyEmployeeScore::computeZone(100));
        $this->assertEquals('green', MonthlyEmployeeScore::computeZone(90));
    }

    public function test_compute_zone_yellow(): void
    {
        Setting::create(['key' => 'greenzone_min', 'value' => 90]);
        Setting::create(['key' => 'yellowzone_min', 'value' => 80]);
        Setting::create(['key' => 'orangezone_min', 'value' => 70]);

        $this->assertEquals('yellow', MonthlyEmployeeScore::computeZone(89));
        $this->assertEquals('yellow', MonthlyEmployeeScore::computeZone(80));
    }

    public function test_compute_zone_orange(): void
    {
        Setting::create(['key' => 'greenzone_min', 'value' => 90]);
        Setting::create(['key' => 'yellowzone_min', 'value' => 80]);
        Setting::create(['key' => 'orangezone_min', 'value' => 70]);

        $this->assertEquals('orange', MonthlyEmployeeScore::computeZone(79));
        $this->assertEquals('orange', MonthlyEmployeeScore::computeZone(70));
    }

    public function test_compute_zone_red(): void
    {
        Setting::create(['key' => 'greenzone_min', 'value' => 90]);
        Setting::create(['key' => 'yellowzone_min', 'value' => 80]);
        Setting::create(['key' => 'orangezone_min', 'value' => 70]);

        $this->assertEquals('red', MonthlyEmployeeScore::computeZone(69));
        $this->assertEquals('red', MonthlyEmployeeScore::computeZone(0));
    }

    // ── ensureExists() ───────────────────────────────────────────────────────

    public function test_ensure_exists_creates_record_when_none(): void
    {
        Setting::create(['key' => 'default_score_per_month', 'value' => 100]);
        $employee = $this->makeEmployee();

        $score = MonthlyEmployeeScore::ensureExists($employee->id, now()->month, now()->year);

        $this->assertInstanceOf(MonthlyEmployeeScore::class, $score);
        $this->assertEquals(100, $score->initial_score);
        $this->assertEquals(0, $score->deducted_points);
        $this->assertDatabaseHas('monthly_employee_scores', [
            'employee_id' => $employee->id,
            'month'       => now()->month,
            'year'        => now()->year,
        ]);
    }

    public function test_ensure_exists_returns_existing_record_without_overwriting(): void
    {
        $employee = $this->makeEmployee();
        $existing = $this->makeScore($employee, 100);
        $existing->deduct(30);

        $fetched = MonthlyEmployeeScore::ensureExists($employee->id, now()->month, now()->year);

        $this->assertEquals($existing->id, $fetched->id);
        $this->assertEquals(30, $fetched->fresh()->deducted_points);
    }

    public function test_ensure_exists_uses_default_score_from_settings(): void
    {
        Setting::create(['key' => 'default_score_per_month', 'value' => 120]);
        $employee = $this->makeEmployee();

        $score = MonthlyEmployeeScore::ensureExists($employee->id, now()->month, now()->year);

        $this->assertEquals(120, $score->initial_score);
        $this->assertEquals(120, $score->final_score);
    }
}
