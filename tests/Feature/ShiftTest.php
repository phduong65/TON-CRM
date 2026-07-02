<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'manager']);
        foreach (['view-shifts', 'create-shifts', 'edit-shifts', 'delete-shifts'] as $perm) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
    }

    public function test_manager_can_view_shift_list(): void
    {
        $response = $this->actingAs($this->manager)->get(route('shifts.index'));
        $response->assertStatus(200);
    }

    public function test_manager_can_create_shift(): void
    {
        $response = $this->actingAs($this->manager)->post(route('shifts.store'), [
            'code'       => 'CA-HC',
            'name'       => 'Ca hành chính',
            'start_time' => '08:00',
            'end_time'   => '17:00',
            'shift_type' => 'fulltime',
            'work_mode'  => 'onsite',
        ]);

        $response->assertRedirect(route('shifts.index'));
        $this->assertDatabaseHas('shifts', ['code' => 'CA-HC', 'name' => 'Ca hành chính']);
    }

    public function test_manager_can_update_shift(): void
    {
        $shift = Shift::create([
            'code' => 'CA-1', 'name' => 'Ca 1', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);

        $response = $this->actingAs($this->manager)->put(route('shifts.update', $shift), [
            'code'       => 'CA-1',
            'name'       => 'Ca 1 (sửa)',
            'start_time' => '08:30',
            'end_time'   => '17:30',
            'shift_type' => 'fulltime',
            'work_mode'  => 'wfh',
        ]);

        $response->assertRedirect(route('shifts.index'));
        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'name' => 'Ca 1 (sửa)', 'work_mode' => 'wfh']);
    }

    public function test_manager_can_deactivate_shift(): void
    {
        $shift = Shift::create([
            'code' => 'CA-2', 'name' => 'Ca 2', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite',
        ]);

        $response = $this->actingAs($this->manager)->delete(route('shifts.destroy', $shift));

        $response->assertRedirect(route('shifts.index'));
        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'is_active' => false]);
    }

    public function test_user_without_permission_cannot_view_shifts(): void
    {
        $noPermUser = User::factory()->create();
        $response = $this->actingAs($noPermUser)->get(route('shifts.index'));
        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('shifts.index'));
        $response->assertRedirect(route('login'));
    }
}
