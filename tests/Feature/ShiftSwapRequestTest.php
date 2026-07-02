<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShiftSwapRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $userA;
    private User $userB;
    private Employee $employeeA;
    private Employee $employeeB;
    private Shift $shift;
    private ShiftSchedule $scheduleA; // A's Monday shift
    private ShiftSchedule $scheduleB; // B's Wednesday shift

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        foreach (['view-shift-swaps', 'create-shift-swaps', 'approve-shift-swaps'] as $perm) {
            $managerRole->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        foreach (['view-shift-swaps', 'create-shift-swaps'] as $perm) {
            $staffRole->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $this->userA = User::factory()->create();
        $this->userA->assignRole('staff');
        $this->userB = User::factory()->create();
        $this->userB->assignRole('staff');

        $branch = Branch::create(['code' => 'BR-1', 'name' => 'Chi nhánh 1', 'is_active' => true]);

        $this->employeeA = Employee::create(['code' => 'EMP-A', 'name' => 'Nguyễn Văn A', 'user_id' => $this->userA->id, 'branch_id' => $branch->id, 'is_active' => true]);
        $this->employeeB = Employee::create(['code' => 'EMP-B', 'name' => 'Trần Thị B', 'user_id' => $this->userB->id, 'branch_id' => $branch->id, 'is_active' => true]);

        $this->shift = Shift::create(['code' => 'CA-HC', 'name' => 'Ca hành chính', 'start_time' => '08:00', 'end_time' => '17:00', 'work_mode' => 'onsite']);

        $this->scheduleA = ShiftSchedule::create([
            'employee_id' => $this->employeeA->id, 'shift_id' => $this->shift->id, 'branch_id' => $branch->id,
            'work_date' => now()->addDays(1)->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
        $this->scheduleB = ShiftSchedule::create([
            'employee_id' => $this->employeeB->id, 'shift_id' => $this->shift->id, 'branch_id' => $branch->id,
            'work_date' => now()->addDays(3)->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);
    }

    private function storeSwap(User $as, array $overrides = [])
    {
        return $this->actingAs($as)->post(route('shift-swap-requests.store'), array_merge([
            'requester_schedule_id' => $this->scheduleA->id,
            'target_schedule_id'    => $this->scheduleB->id,
            'reason'                => 'Có việc bận',
        ], $overrides));
    }

    public function test_employee_can_create_swap_request(): void
    {
        $response = $this->storeSwap($this->userA);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_swap_requests', [
            'requester_employee_id' => $this->employeeA->id,
            'target_employee_id'    => $this->employeeB->id,
            'requester_schedule_id' => $this->scheduleA->id,
            'target_schedule_id'    => $this->scheduleB->id,
            'status'                => 'pending',
        ]);
    }

    public function test_cannot_offer_a_schedule_that_is_not_own(): void
    {
        // userB tries to offer schedule A (not theirs) while targeting schedule B (their own) — invalid combo
        $response = $this->actingAs($this->userB)->post(route('shift-swap-requests.store'), [
            'requester_schedule_id' => $this->scheduleA->id,
            'target_schedule_id'    => $this->scheduleB->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_swap_with_own_schedule(): void
    {
        $otherOwnSchedule = ShiftSchedule::create([
            'employee_id' => $this->employeeA->id, 'shift_id' => $this->shift->id,
            'work_date' => now()->addDays(2)->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->storeSwap($this->userA, ['target_schedule_id' => $otherOwnSchedule->id]);
        $response->assertStatus(422);
    }

    public function test_cannot_swap_past_schedule(): void
    {
        $pastSchedule = ShiftSchedule::create([
            'employee_id' => $this->employeeB->id, 'shift_id' => $this->shift->id,
            'work_date' => now()->subDays(2)->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->storeSwap($this->userA, ['target_schedule_id' => $pastSchedule->id]);
        $response->assertStatus(422);
    }

    public function test_conflict_when_target_already_has_schedule_on_requester_date(): void
    {
        // B already has a schedule on A's date too
        ShiftSchedule::create([
            'employee_id' => $this->employeeB->id, 'shift_id' => $this->shift->id,
            'work_date' => $this->scheduleA->work_date, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        $response = $this->storeSwap($this->userA);
        $response->assertStatus(422);
    }

    public function test_can_independently_swap_both_shifts_when_employee_has_two_shifts_same_day(): void
    {
        // Employee A có 2 ca khác nhau trong cùng 1 ngày (đa ca) và muốn đổi từng ca cho 2
        // người khác nhau — trước khi sửa, check "đã có ca khác vào đúng ngày này" sẽ chặn
        // nhầm vì coi ca còn lại của chính A là "xung đột".
        $shift2 = Shift::create(['code' => 'CA-TOI', 'name' => 'Ca tối', 'start_time' => '18:00', 'end_time' => '22:00', 'work_mode' => 'onsite']);
        $sameDay = now()->addDays(5)->toDateString();

        $this->scheduleA->update(['work_date' => $sameDay]); // A — Ca hành chính
        $scheduleA2 = ShiftSchedule::create([
            'employee_id' => $this->employeeA->id, 'shift_id' => $shift2->id,
            'work_date' => $sameDay, 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]); // A — Ca tối (cùng ngày)

        $employeeC = Employee::create(['code' => 'EMP-C', 'name' => 'Lê Văn C', 'is_active' => true]);
        $scheduleC = ShiftSchedule::create([
            'employee_id' => $employeeC->id, 'shift_id' => $shift2->id,
            'work_date' => now()->addDays(10)->toDateString(), 'status' => 'scheduled', 'assignment_type' => 'rotation',
        ]);

        // Đơn 1: A đổi Ca hành chính với B
        $response1 = $this->storeSwap($this->userA);
        $response1->assertRedirect();
        $response1->assertSessionHasNoErrors();

        // Đơn 2: A đổi Ca tối với C — KHÔNG được bị chặn bởi việc A đang có ca hành chính cùng ngày
        $response2 = $this->actingAs($this->userA)->post(route('shift-swap-requests.store'), [
            'requester_schedule_id' => $scheduleA2->id,
            'target_schedule_id'    => $scheduleC->id,
            'reason'                => 'Đổi ca tối',
        ]);
        $response2->assertRedirect();
        $response2->assertSessionHasNoErrors();

        $this->assertEquals(2, ShiftSwapRequest::where('requester_employee_id', $this->employeeA->id)->count());

        $swap1 = ShiftSwapRequest::where('requester_schedule_id', $this->scheduleA->id)->firstOrFail();
        $swap2 = ShiftSwapRequest::where('requester_schedule_id', $scheduleA2->id)->firstOrFail();

        $this->actingAs($this->manager)->post(route('shift-swap-requests.approve', $swap1))->assertRedirect();
        $this->actingAs($this->manager)->post(route('shift-swap-requests.approve', $swap2))->assertRedirect();

        $this->assertEquals($this->employeeB->id, $this->scheduleA->fresh()->employee_id);
        $this->assertEquals($employeeC->id, $scheduleA2->fresh()->employee_id);
        $this->assertEquals($this->employeeA->id, $this->scheduleB->fresh()->employee_id);
        $this->assertEquals($this->employeeA->id, $scheduleC->fresh()->employee_id);
    }

    public function test_manager_can_approve_and_schedules_are_swapped(): void
    {
        $this->storeSwap($this->userA);
        $swap = ShiftSwapRequest::first();

        $response = $this->actingAs($this->manager)->post(route('shift-swap-requests.approve', $swap));

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_swap_requests', ['id' => $swap->id, 'status' => 'approved']);

        // A's original schedule (Monday) now belongs to B, and vice versa
        $this->assertEquals($this->employeeB->id, $this->scheduleA->fresh()->employee_id);
        $this->assertEquals($this->employeeA->id, $this->scheduleB->fresh()->employee_id);

        // work_date/shift_id unchanged on both rows
        $this->assertEquals($this->shift->id, $this->scheduleA->fresh()->shift_id);
    }

    public function test_approve_works_when_both_schedules_are_on_the_same_date(): void
    {
        // Ca sáng của A và ca chiều của B cùng 1 ngày — trước đây gây lỗi unique constraint
        // vì update tuần tự tạo trạng thái trung gian trùng (employee_id, work_date).
        $sameDay = now()->addDays(2)->toDateString();
        $this->scheduleA->update(['work_date' => $sameDay]);
        $this->scheduleB->update(['work_date' => $sameDay]);

        $this->storeSwap($this->userA);
        $swap = ShiftSwapRequest::first();

        $response = $this->actingAs($this->manager)->post(route('shift-swap-requests.approve', $swap));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shift_swap_requests', ['id' => $swap->id, 'status' => 'approved']);
        $this->assertEquals($this->employeeB->id, $this->scheduleA->fresh()->employee_id);
        $this->assertEquals($this->employeeA->id, $this->scheduleB->fresh()->employee_id);
        $this->assertEquals($sameDay, $this->scheduleA->fresh()->work_date->toDateString());
        $this->assertEquals($sameDay, $this->scheduleB->fresh()->work_date->toDateString());
    }

    public function test_cannot_approve_twice(): void
    {
        $this->storeSwap($this->userA);
        $swap = ShiftSwapRequest::first();

        $this->actingAs($this->manager)->post(route('shift-swap-requests.approve', $swap))->assertRedirect();
        $response = $this->actingAs($this->manager)->post(route('shift-swap-requests.approve', $swap));

        $response->assertStatus(403);
    }

    public function test_reject_requires_reason(): void
    {
        $this->storeSwap($this->userA);
        $swap = ShiftSwapRequest::first();

        $response = $this->actingAs($this->manager)->post(route('shift-swap-requests.reject', $swap), []);
        $response->assertSessionHasErrors('rejection_reason');

        $response = $this->actingAs($this->manager)->post(route('shift-swap-requests.reject', $swap), [
            'rejection_reason' => 'Không hợp lý',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('shift_swap_requests', ['id' => $swap->id, 'status' => 'rejected']);

        // Schedules unchanged after rejection
        $this->assertEquals($this->employeeA->id, $this->scheduleA->fresh()->employee_id);
        $this->assertEquals($this->employeeB->id, $this->scheduleB->fresh()->employee_id);
    }

    public function test_involved_parties_see_request_but_unrelated_employee_does_not(): void
    {
        $this->storeSwap($this->userA);

        $outsider = User::factory()->create();
        $outsider->assignRole('staff');
        Employee::create(['code' => 'EMP-C', 'name' => 'Lê Văn C', 'user_id' => $outsider->id, 'is_active' => true]);

        $this->actingAs($this->userA)->get(route('shift-swap-requests.index'))->assertSee('SWP-');
        $this->actingAs($this->userB)->get(route('shift-swap-requests.index'))->assertSee('SWP-');
        $this->actingAs($outsider)->get(route('shift-swap-requests.index'))->assertDontSee('SWP-');
        $this->actingAs($this->manager)->get(route('shift-swap-requests.index'))->assertSee('SWP-');
    }

    public function test_employee_without_permission_cannot_approve(): void
    {
        $this->storeSwap($this->userA);
        $swap = ShiftSwapRequest::first();

        $response = $this->actingAs($this->userA)->post(route('shift-swap-requests.approve', $swap));
        $response->assertStatus(403);
    }

    public function test_guest_redirected_to_login(): void
    {
        $response = $this->get(route('shift-swap-requests.index'));
        $response->assertRedirect(route('login'));
    }
}
