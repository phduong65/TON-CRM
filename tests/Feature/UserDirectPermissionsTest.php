<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Quyền riêng (direct permissions) của người dùng qua modal Thêm/Sửa ở /users.
 * Trước đây modal Sửa không gửi permissions[] nên mỗi lần lưu đều xoá sạch quyền riêng.
 */
class UserDirectPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $target;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'admin'])
            ->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-users']));
        Role::firstOrCreate(['name' => 'staff']);
        Permission::firstOrCreate(['name' => 'view-reports']);
        Permission::firstOrCreate(['name' => 'export-attendance']);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');

        $this->target = User::factory()->create(['status' => 'active']);
        $this->target->assignRole('staff');
        $this->target->givePermissionTo('view-reports');
    }

    private function updatePayload(array $extra = []): array
    {
        return array_merge([
            'name'  => 'Tên mới',
            'email' => $this->target->email,
            'role'  => 'staff',
        ], $extra);
    }

    public function test_update_without_sync_flag_keeps_direct_permissions(): void
    {
        $this->actingAs($this->admin)
            ->put(route('users.update', $this->target), $this->updatePayload())
            ->assertRedirect();

        $this->assertEquals(['view-reports'], $this->target->fresh()->getDirectPermissions()->pluck('name')->all());
    }

    public function test_update_with_sync_flag_replaces_direct_permissions(): void
    {
        $this->actingAs($this->admin)
            ->put(route('users.update', $this->target), $this->updatePayload([
                'sync_permissions' => 1,
                'permissions'      => ['export-attendance'],
            ]))
            ->assertRedirect();

        $this->assertEquals(['export-attendance'], $this->target->fresh()->getDirectPermissions()->pluck('name')->all());
    }

    public function test_update_with_sync_flag_and_no_permissions_clears_direct_permissions(): void
    {
        $this->actingAs($this->admin)
            ->put(route('users.update', $this->target), $this->updatePayload(['sync_permissions' => 1]))
            ->assertRedirect();

        $this->assertCount(0, $this->target->fresh()->getDirectPermissions());
    }

    public function test_store_assigns_direct_permissions(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name'                  => 'Người dùng mới',
                'email'                 => 'moi@example.com',
                'password'              => 'password123',
                'password_confirmation' => 'password123',
                'role'                  => 'staff',
                'permissions'           => ['view-reports'],
            ])
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'moi@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('staff'));
        $this->assertEquals(['view-reports'], $user->getDirectPermissions()->pluck('name')->all());
    }

    public function test_index_renders_redesigned_modals_with_permission_picker(): void
    {
        $response = $this->actingAs($this->admin)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('id="editUserPerms"', false);
        $response->assertSee('id="createUserPerms"', false);
        $response->assertSee('name="sync_permissions" value="1"', false);
        $response->assertSee('data-role-perms', false);
    }

    public function test_user_without_permission_cannot_update_user(): void
    {
        $noPerm = User::factory()->create(['status' => 'active']);

        $this->actingAs($noPerm)
            ->put(route('users.update', $this->target), $this->updatePayload(['sync_permissions' => 1]))
            ->assertForbidden();

        $this->assertEquals(['view-reports'], $this->target->fresh()->getDirectPermissions()->pluck('name')->all());
    }

    public function test_create_and_edit_pages_no_longer_exist(): void
    {
        // /users/create khớp PUT|DELETE /users/{user} → 405; /users/{id}/edit → 404. Cả hai đều không vào được.
        $this->assertFalse($this->actingAs($this->admin)->get('/users/create')->isSuccessful());
        $this->assertFalse($this->actingAs($this->admin)->get('/users/' . $this->target->id . '/edit')->isSuccessful());
    }

    public function test_index_filters_by_employee_status(): void
    {
        $activeUser = User::factory()->create(['name' => 'Active User', 'status' => 'active']);
        Employee::create(['code' => 'EMP-ACT', 'name' => 'Active User', 'user_id' => $activeUser->id, 'is_active' => true]);

        $resignedUser = User::factory()->create(['name' => 'Resigned User', 'status' => 'active']);
        Employee::create(['code' => 'EMP-RES', 'name' => 'Resigned User', 'user_id' => $resignedUser->id, 'is_active' => false]);

        $unlinkedUser = User::factory()->create(['name' => 'Unlinked User', 'status' => 'active']);

        // Mặc định index lọc nhân viên đang làm việc
        $resDefault = $this->actingAs($this->admin)->get(route('users.index'));
        $resDefault->assertOk();
        $resDefault->assertSee('Active User');
        $resDefault->assertDontSee('Resigned User');
        $resDefault->assertDontSee('Unlinked User');

        // Filter all (Tất cả)
        $resAll = $this->actingAs($this->admin)->get(route('users.index', ['employee_status' => 'all']));
        $resAll->assertOk();
        $resAll->assertSee('Active User');
        $resAll->assertSee('Resigned User');
        $resAll->assertSee('Unlinked User');

        // Filter resigned employees (Đã nghỉ)
        $resResigned = $this->actingAs($this->admin)->get(route('users.index', ['employee_status' => 'resigned']));
        $resResigned->assertOk();
        $resResigned->assertSee('Resigned User');
        $resResigned->assertDontSee('Active User');
        $resResigned->assertDontSee('Unlinked User');

        // Filter unlinked (Chưa liên kết)
        $resUnlinked = $this->actingAs($this->admin)->get(route('users.index', ['employee_status' => 'unlinked']));
        $resUnlinked->assertOk();
        $resUnlinked->assertSee('Unlinked User');
        $resUnlinked->assertDontSee('Active User');
        $resUnlinked->assertDontSee('Resigned User');
    }

    public function test_index_renders_employee_status_column_and_filter(): void
    {
        $response = $this->actingAs($this->admin)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('status-tabs', false);
        $response->assertSee('employee_status=all', false);
        $response->assertSee('Đang làm');
        $response->assertSee('TT Nhân viên');
        $response->assertSee('type-chips', false);
        // Không còn select status hay nút Lọc
        $response->assertDontSee('Trạng thái TK: Tất cả', false);
        $response->assertDontSee('<button type="submit" class="btn-secondary', false);
    }
}
