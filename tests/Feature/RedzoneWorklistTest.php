<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeScore;
use App\Models\MonthlyEmployeeScore;
use App\Models\Penalty;
use App\Models\Setting;
use App\Models\User;
use App\Models\Violation;
use Carbon\Carbon;
use Database\Seeders\RedzonePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Trang Vùng điểm & Redzone (worklist) — quyền, mặc định vùng đỏ, nguyên nhân, cảnh báo liên tiếp. */
class RedzoneWorklistTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Branch $branchA;
    private Branch $branchB;
    private Violation $violation;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 10:00:00');
        foreach (['default_score_per_month' => 100, 'greenzone_min' => 90, 'yellowzone_min' => 80, 'orangezone_min' => 70, 'consecutive_redzone_months' => 2] as $k => $v) {
            Setting::create(['key' => $k, 'value' => $v]);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'manager']);
        (new RedzonePermissionSeeder())->run();

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $this->branchA = Branch::create(['code' => 'A', 'name' => 'Chi nhánh A', 'is_active' => true]);
        $this->branchB = Branch::create(['code' => 'B', 'name' => 'Chi nhánh B', 'is_active' => true]);
        $this->violation = Violation::create(['name' => 'Đi trễ trên 30 phút', 'points_deducted' => 10, 'money_deducted' => 0, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employee(string $name, Branch $branch): Employee
    {
        return Employee::create(['code' => strtoupper(substr(md5($name), 0, 6)), 'name' => $name, 'branch_id' => $branch->id, 'is_active' => true]);
    }

    /** Ghi n lần trừ điểm (mỗi lần $each điểm) từ phiếu phạt của lỗi $this->violation trong tháng hiện tại. */
    private function deduct(Employee $emp, int $times, int $each, ?Carbon $at = null): void
    {
        for ($i = 0; $i < $times; $i++) {
            $penalty = Penalty::create([
                'code' => 'PEN-' . uniqid(), 'employee_id' => $emp->id, 'violation_id' => $this->violation->id,
                'status' => 'approved', 'total_points_deducted' => $each, 'total_money_deducted' => 0, 'created_by' => $this->manager->id,
            ]);
            $score = EmployeeScore::create([
                'employee_id' => $emp->id, 'points' => -$each, 'reason' => 'Phạt', 'type' => 'penalty',
                'reference_type' => Penalty::class, 'reference_id' => $penalty->id,
            ]);
            if ($at) {
                $score->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
            }
        }
    }

    public function test_user_without_view_redzone_permission_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get(route('redzone.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('redzone.index'))->assertRedirect(route('login'));
    }

    public function test_defaults_to_redzone_worklist_with_deduction_reasons(): void
    {
        $red = $this->employee('NV Vùng Đỏ', $this->branchA);       // 100 - 40 = 60 → red
        $green = $this->employee('NV Vùng Xanh', $this->branchA);   // 100 → green
        $this->deduct($red, 4, 10);

        $response = $this->actingAs($this->manager)->get(route('redzone.index'));

        $response->assertOk();
        $response->assertSee('Redzone — Nguy hiểm');
        $response->assertSee('NV Vùng Đỏ');
        $response->assertDontSee('NV Vùng Xanh');
        $response->assertSee('Đi trễ trên 30 phút');
        $response->assertSee('×4 · -40', false);
    }

    public function test_zone_param_switches_list_and_branch_filter_applies(): void
    {
        $this->employee('NV Xanh A', $this->branchA);
        $this->employee('NV Xanh B', $this->branchB);

        $response = $this->actingAs($this->manager)->get(route('redzone.index', ['zone' => 'green', 'branch_id' => $this->branchA->id]));

        $response->assertOk();
        $response->assertSee('NV Xanh A');
        $response->assertDontSee('NV Xanh B');
    }

    public function test_flags_employee_red_for_consecutive_months(): void
    {
        $twice = $this->employee('NV Đỏ Hai Tháng', $this->branchA);
        $once = $this->employee('NV Đỏ Một Tháng', $this->branchA);
        $this->deduct($twice, 4, 10);
        $this->deduct($once, 4, 10);
        // Tháng trước: một người đã lưu zone red, người còn lại tính từ employee_scores (không bị trừ → green)
        MonthlyEmployeeScore::create(['employee_id' => $twice->id, 'month' => 9, 'year' => 2026, 'final_score' => 50, 'deducted_points' => 50, 'zone' => 'red']);

        $response = $this->actingAs($this->manager)->get(route('redzone.index'));

        $response->assertOk();
        $response->assertSee('1 nhân viên ở Redzone 2 tháng liên tiếp');
        $html = preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertMatchesRegularExpression('/NV Đỏ Hai Tháng.*?2 tháng liên tiếp/', $html);
        $this->assertMatchesRegularExpression('/NV Đỏ Một Tháng.*?Tháng đầu/', $html);
    }
}
