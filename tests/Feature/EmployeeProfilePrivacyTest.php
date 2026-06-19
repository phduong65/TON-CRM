<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kiểm tra tính năng bảo mật thông tin điểm/phạt:
 * - Mỗi nhân viên chỉ xem được điểm và lịch sử của chính mình
 * - Admin/Manager xem được tất cả
 * - Người khác không xem được → thông báo bảo mật
 * - Trang /penalties của người khác → 403
 */
class EmployeeProfilePrivacyTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $managerUser;
    private User $staffUserA;
    private User $staffUserB;
    private Employee $employeeA;
    private Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $viewEmployees = Permission::firstOrCreate(['name' => 'view-employees']);

        $adminRole   = Role::firstOrCreate(['name' => 'admin']);
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $staffRole   = Role::firstOrCreate(['name' => 'staff']);

        $adminRole->givePermissionTo($viewEmployees);
        $managerRole->givePermissionTo($viewEmployees);
        $staffRole->givePermissionTo($viewEmployees);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->managerUser = User::factory()->create();
        $this->managerUser->assignRole('manager');

        $this->staffUserA = User::factory()->create();
        $this->staffUserA->assignRole('staff');

        $this->staffUserB = User::factory()->create();
        $this->staffUserB->assignRole('staff');

        $this->employeeA = Employee::create([
            'user_id'   => $this->staffUserA->id,
            'code'      => 'EMP-A01',
            'name'      => 'Nguyễn Văn A',
            'is_active' => true,
        ]);

        $this->employeeB = Employee::create([
            'user_id'   => $this->staffUserB->id,
            'code'      => 'EMP-B02',
            'name'      => 'Trần Thị B',
            'is_active' => true,
        ]);
    }

    // ── Unauthenticated ───────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_view_employee_profile(): void
    {
        $response = $this->get(route('employees.show', $this->employeeA));

        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_cannot_view_employee_penalties_page(): void
    {
        $response = $this->get(route('employees.penalties', $this->employeeA));

        $response->assertRedirect(route('login'));
    }

    // ── employees.show — Admin/Manager xem được dữ liệu nhạy cảm ────────────

    public function test_admin_sees_sensitive_data_on_any_employee_profile(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('employees.show', $this->employeeA));

        $response->assertOk();
        $response->assertSee('Tổng điểm');
        $response->assertSee('Lịch sử xử phạt');
        $response->assertDontSee('Chỉ bản thân nhân viên');
    }

    public function test_manager_sees_sensitive_data_on_any_employee_profile(): void
    {
        $response = $this->actingAs($this->managerUser)
            ->get(route('employees.show', $this->employeeB));

        $response->assertOk();
        $response->assertSee('Tổng điểm');
        $response->assertSee('Lịch sử xử phạt');
        $response->assertDontSee('Chỉ bản thân nhân viên');
    }

    // ── employees.show — Nhân viên xem hồ sơ của chính mình ─────────────────

    public function test_employee_sees_own_sensitive_data(): void
    {
        $response = $this->actingAs($this->staffUserA)
            ->get(route('employees.show', $this->employeeA));

        $response->assertOk();
        $response->assertSee('Tổng điểm');
        $response->assertSee('Lịch sử xử phạt');
        $response->assertDontSee('Chỉ bản thân nhân viên');
    }

    public function test_employee_sees_own_score_history_records(): void
    {
        EmployeeScore::create([
            'employee_id' => $this->employeeA->id,
            'points'      => -15,
            'reason'      => 'Vi phạm nội quy',
            'type'        => 'penalty',
        ]);

        $response = $this->actingAs($this->staffUserA)
            ->get(route('employees.show', $this->employeeA));

        $response->assertOk();
        $response->assertSee('Vi phạm nội quy');
        $response->assertSee('Lịch sử điểm thưởng/phạt');
    }

    // ── employees.show — Nhân viên KHÔNG xem được hồ sơ người khác ───────────

    public function test_employee_cannot_see_sensitive_data_of_another_employee(): void
    {
        $response = $this->actingAs($this->staffUserA)
            ->get(route('employees.show', $this->employeeB));

        $response->assertOk();
        $response->assertSee('Chỉ bản thân nhân viên');
        $response->assertDontSee('Tổng điểm');
        $response->assertDontSee('Lịch sử xử phạt');
        $response->assertDontSee('Lịch sử điểm thưởng/phạt');
    }

    public function test_employee_can_still_see_basic_info_of_another_employee(): void
    {
        $response = $this->actingAs($this->staffUserA)
            ->get(route('employees.show', $this->employeeB));

        $response->assertOk();
        $response->assertSee('Trần Thị B');
    }

    public function test_score_records_of_another_employee_are_not_visible(): void
    {
        EmployeeScore::create([
            'employee_id' => $this->employeeB->id,
            'points'      => -20,
            'reason'      => 'Lý do bí mật của B',
            'type'        => 'penalty',
        ]);

        $response = $this->actingAs($this->staffUserA)
            ->get(route('employees.show', $this->employeeB));

        $response->assertOk();
        $response->assertDontSee('Lý do bí mật của B');
    }

    // ── employees.penalties — Admin/Manager truy cập được mọi trang ──────────

    public function test_admin_can_view_any_employee_penalties_page(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('employees.penalties', $this->employeeA));

        $response->assertOk();
    }

    public function test_manager_can_view_any_employee_penalties_page(): void
    {
        $response = $this->actingAs($this->managerUser)
            ->get(route('employees.penalties', $this->employeeB));

        $response->assertOk();
    }

    // ── employees.penalties — Nhân viên chỉ xem được trang của mình ──────────

    public function test_employee_can_view_own_penalties_page(): void
    {
        $response = $this->actingAs($this->staffUserA)
            ->get(route('employees.penalties', $this->employeeA));

        $response->assertOk();
    }

    public function test_employee_cannot_view_another_employees_penalties_page(): void
    {
        $response = $this->actingAs($this->staffUserA)
            ->get(route('employees.penalties', $this->employeeB));

        $response->assertStatus(403);
    }

    // ── User chưa liên kết Employee không thể xem trang penalties ─────────────

    public function test_staff_without_linked_employee_cannot_view_others_penalties_page(): void
    {
        $unlinkedUser = User::factory()->create();
        $unlinkedUser->assignRole('staff');
        // Không tạo Employee tương ứng → user->employee = null

        $response = $this->actingAs($unlinkedUser)
            ->get(route('employees.penalties', $this->employeeA));

        $response->assertStatus(403);
    }

    public function test_staff_without_linked_employee_sees_privacy_notice_on_profile(): void
    {
        $unlinkedUser = User::factory()->create();
        $unlinkedUser->assignRole('staff');

        $response = $this->actingAs($unlinkedUser)
            ->get(route('employees.show', $this->employeeA));

        $response->assertOk();
        $response->assertSee('Chỉ bản thân nhân viên');
        $response->assertDontSee('Tổng điểm');
    }
}
