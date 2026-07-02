<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HolidaysTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'manager']);
        foreach (['view-holidays', 'create-holidays', 'edit-holidays', 'delete-holidays'] as $perm) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
    }

    public function test_manager_can_view_holiday_list(): void
    {
        $response = $this->actingAs($this->manager)->get(route('holidays.index'));
        $response->assertStatus(200);
    }

    public function test_manager_can_create_holiday(): void
    {
        $response = $this->actingAs($this->manager)->post(route('holidays.store'), [
            'date'         => '2026-09-02',
            'name'         => 'Quốc khánh',
            'is_paid'      => '1',
            'bonus_amount' => 500000,
        ]);

        $response->assertRedirect(route('holidays.index'));
        $this->assertDatabaseHas('holidays', ['date' => '2026-09-02', 'name' => 'Quốc khánh', 'is_paid' => true]);
    }

    public function test_manager_can_update_holiday(): void
    {
        $holiday = Holiday::create(['date' => '2026-01-01', 'name' => 'Tết dương lịch', 'is_paid' => true]);

        $response = $this->actingAs($this->manager)->put(route('holidays.update', $holiday), [
            'date' => '2026-01-01',
            'name' => 'Tết dương lịch (sửa)',
            'is_paid' => '1',
        ]);

        $response->assertRedirect(route('holidays.index'));
        $this->assertDatabaseHas('holidays', ['id' => $holiday->id, 'name' => 'Tết dương lịch (sửa)']);
    }

    public function test_manager_can_deactivate_holiday(): void
    {
        $holiday = Holiday::create(['date' => '2026-04-30', 'name' => 'Giải phóng miền Nam', 'is_paid' => true]);

        $response = $this->actingAs($this->manager)->delete(route('holidays.destroy', $holiday));

        $response->assertRedirect();
        $this->assertDatabaseHas('holidays', ['id' => $holiday->id, 'is_active' => false]);
    }

    public function test_duplicate_date_is_rejected(): void
    {
        Holiday::create(['date' => '2026-05-01', 'name' => 'Quốc tế lao động', 'is_paid' => true]);

        $response = $this->actingAs($this->manager)->post(route('holidays.store'), [
            'date' => '2026-05-01',
            'name' => 'Trùng ngày',
            'is_paid' => '1',
        ]);

        $response->assertSessionHasErrors('date');
    }

    public function test_user_without_permission_cannot_view_holidays(): void
    {
        $noPermUser = User::factory()->create();
        $response = $this->actingAs($noPermUser)->get(route('holidays.index'));
        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('holidays.index'));
        $response->assertRedirect(route('login'));
    }
}
