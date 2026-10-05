<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShiftScheduleLookupApiTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Employee $employeeA;
    private Employee $employeeB;
    private Shift $shift;
    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'staff']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-shift-swaps']));

        $this->userA = User::factory()->create(['status' => 'active']);
        $this->userA->assignRole('staff');
        $this->tokenA = $this->userA->createToken('test')->plainTextToken;

        $userB = User::factory()->create(['status' => 'active']);
        $this->employeeA = Employee::create(['code' => 'EMP-A', 'name' => 'A', 'user_id' => $this->userA->id, 'is_active' => true]);
        $this->employeeB = Employee::create(['code' => 'EMP-B', 'name' => 'B', 'user_id' => $userB->id, 'is_active' => true]);

        $this->shift = Shift::create(['code' => 'CA-HC', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);
    }

    private function authA()
    {
        return $this->withHeader('Authorization', "Bearer {$this->tokenA}");
    }

    public function test_lookup_returns_other_employees_schedule_excluding_own(): void
    {
        $date = now()->addDay()->toDateString();

        ShiftSchedule::create(['employee_id' => $this->employeeA->id, 'shift_id' => $this->shift->id, 'work_date' => $date, 'assignment_type' => 'rotation', 'status' => 'scheduled']);
        ShiftSchedule::create(['employee_id' => $this->employeeB->id, 'shift_id' => $this->shift->id, 'work_date' => $date, 'assignment_type' => 'rotation', 'status' => 'scheduled']);

        $response = $this->authA()->getJson("/api/shift-schedules/lookup?date={$date}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('EMP-B', $data[0]['employee_code']);
    }

    public function test_lookup_rejects_past_date(): void
    {
        $response = $this->authA()->getJson('/api/shift-schedules/lookup?date=' . now()->subDay()->toDateString());

        $response->assertStatus(422);
    }

    public function test_lookup_excludes_flexible_schedules(): void
    {
        $date = now()->addDay()->toDateString();

        ShiftSchedule::create([
            'employee_id' => $this->employeeB->id, 'shift_id' => null, 'work_date' => $date,
            'assignment_type' => 'rotation', 'status' => 'scheduled',
            'custom_start_time' => '08:00', 'custom_end_time' => '17:00',
        ]);

        $response = $this->authA()->getJson("/api/shift-schedules/lookup?date={$date}");

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }
}
