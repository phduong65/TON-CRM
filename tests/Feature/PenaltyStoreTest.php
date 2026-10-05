<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Penalty;
use App\Models\Setting;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PenaltyStoreTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;
    private Employee $employee;
    private Violation $violation;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'default_score_per_month', 'value' => 100]);
        Setting::create(['key' => 'greenzone_min', 'value' => 90]);
        Setting::create(['key' => 'yellowzone_min', 'value' => 80]);
        Setting::create(['key' => 'orangezone_min', 'value' => 70]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => 'create-penalties']);
        Permission::firstOrCreate(['name' => 'approve-penalties']); // referenced by NotificationService::notifyPenaltyCreated()
        $role = Role::firstOrCreate(['name' => 'manager']);
        $role->givePermissionTo($perm);

        $this->creator = User::factory()->create();
        $this->creator->assignRole('manager');

        $this->employee = Employee::create(['code' => 'EMP-001', 'name' => 'Nguyễn Văn A', 'is_active' => true]);

        $this->violation = Violation::create([
            'name' => 'Đi trễ', 'points_deducted' => 20, 'money_deducted' => 0, 'is_active' => true,
        ]);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'violation_id'    => $this->violation->id,
            'employee_id'     => $this->employee->id,
            'points_deducted' => 20,
        ], $overrides);
    }

    public function test_creating_penalty_stores_pending_record_with_generated_code(): void
    {
        $response = $this->actingAs($this->creator)->post(route('penalties.store'), $this->basePayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('penalties', [
            'employee_id'           => $this->employee->id,
            'violation_id'          => $this->violation->id,
            'status'                => 'pending',
            'total_points_deducted' => 20,
            'created_by'            => $this->creator->id,
        ]);
        $this->assertMatchesRegularExpression('/^PNL-\d{6}-\d{4}$/', Penalty::first()->code);
    }

    public function test_money_deducted_defaults_to_zero_when_not_provided(): void
    {
        $this->actingAs($this->creator)->post(route('penalties.store'), $this->basePayload());

        $this->assertEquals(0, (float) Penalty::first()->total_money_deducted);
    }

    public function test_penalty_without_members_creates_no_penalty_members(): void
    {
        $this->actingAs($this->creator)->post(route('penalties.store'), $this->basePayload());

        $this->assertDatabaseCount('penalty_members', 0);
    }

    public function test_penalty_with_members_creates_penalty_member_records(): void
    {
        $member = Employee::create(['code' => 'EMP-002', 'name' => 'Trần Thị B', 'is_active' => true]);

        $this->actingAs($this->creator)->post(route('penalties.store'), $this->basePayload([
            'members' => [
                ['employee_id' => $member->id, 'points_deducted' => 10, 'money_deducted' => 0, 'note' => 'Liên đới'],
            ],
        ]));

        $penalty = Penalty::first();
        $this->assertDatabaseCount('penalty_members', 1);
        $this->assertDatabaseHas('penalty_members', [
            'penalty_id'      => $penalty->id,
            'employee_id'     => $member->id,
            'points_deducted' => 10,
            'note'            => 'Liên đới',
        ]);
    }

    /**
     * StorePenaltyRequest bắt buộc members.*.points_deducted (required) — form thật (create-modal.blade.php)
     * luôn JS-inject giá trị này cho mỗi hàng liên đới nên fallback "?? $request->points_deducted" trong
     * PenaltiesController::store() không bao giờ được dùng tới qua route thật; validation chặn trước khi tới đó.
     */
    public function test_member_missing_points_deducted_fails_validation(): void
    {
        $member = Employee::create(['code' => 'EMP-002', 'name' => 'Trần Thị B', 'is_active' => true]);

        $response = $this->actingAs($this->creator)->post(route('penalties.store'), $this->basePayload([
            'members' => [
                ['employee_id' => $member->id],
            ],
        ]));

        $response->assertSessionHasErrors('members.0.points_deducted');
        $this->assertDatabaseCount('penalties', 0);
    }

    public function test_penalty_can_be_created_with_attachment(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('evidence.jpg', 100, 100)->size(500);

        $response = $this->actingAs($this->creator)->post(route('penalties.store'), $this->basePayload([
            'attachments' => [$file],
        ]));

        $response->assertRedirect();
        $this->assertDatabaseCount('attachments', 1);
    }

    public function test_missing_violation_and_employee_fails_validation(): void
    {
        $response = $this->actingAs($this->creator)->post(route('penalties.store'), [
            'points_deducted' => 20,
        ]);

        $response->assertSessionHasErrors(['violation_id', 'employee_id']);
        $this->assertDatabaseCount('penalties', 0);
    }

    public function test_unauthenticated_cannot_create_penalty(): void
    {
        $response = $this->post(route('penalties.store'), $this->basePayload());

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('penalties', 0);
    }

    public function test_user_without_permission_cannot_create_penalty(): void
    {
        $noPermUser = User::factory()->create();

        $response = $this->actingAs($noPermUser)->post(route('penalties.store'), $this->basePayload());

        $response->assertStatus(403);
        $this->assertDatabaseCount('penalties', 0);
    }

    /**
     * Penalty KHÔNG dùng SoftDeletes — destroy() xoá cứng thật. Sinh code trước đây dựa trên
     * count() số dòng còn lại trong tháng — xoá 1 phiếu ở giữa tháng làm số đếm lùi lại, phiếu
     * mới tạo sẽ trùng "code" (unique constraint) với phiếu chưa xoá, gây crash 500 khi insert.
     * Xem Penalty::nextCode() (dùng MAX số thứ tự thay vì COUNT số dòng).
     */
    public function test_creating_penalty_after_hard_delete_does_not_collide_on_code(): void
    {
        // Mỗi lần tạo dùng points_deducted khác nhau — không phải double-submit thật (đã có guard
        // chặn riêng, xem test_double_submit_creates_only_one_penalty), chỉ để có 3 bản ghi phân
        // biệt cho kịch bản này (guard chống trùng khớp theo employee_id+violation_id+points_deducted).
        $this->actingAs($this->creator)->post(route('penalties.store'), $this->basePayload(['points_deducted' => 21]));
        $this->actingAs($this->creator)->post(route('penalties.store'), $this->basePayload(['points_deducted' => 22]));
        $this->actingAs($this->creator)->post(route('penalties.store'), $this->basePayload(['points_deducted' => 23]));

        Penalty::orderBy('id')->skip(1)->first()->delete(); // xoá cứng phiếu thứ 2

        $response = $this->actingAs($this->creator)->post(route('penalties.store'), $this->basePayload(['points_deducted' => 24]));

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('penalties', 3); // 3 tạo đầu - 1 xoá cứng + 1 vừa tạo
        $codes = Penalty::pluck('code');
        $this->assertEquals($codes->count(), $codes->unique()->count(), 'Code bị trùng giữa các bản ghi.');
    }

    /**
     * Double-click / gửi lại form khi mạng chậm không được tạo 2 phiếu phạt giống hệt nhau.
     * Xem PreventsDuplicateSubmission::wasJustSubmitted() và PenaltiesController::store().
     */
    public function test_double_submit_creates_only_one_penalty(): void
    {
        $payload = $this->basePayload();

        $this->actingAs($this->creator)->post(route('penalties.store'), $payload)->assertRedirect();
        $this->actingAs($this->creator)->post(route('penalties.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('penalties', 1);
    }
}
