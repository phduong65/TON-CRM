<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Penalty;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Hộp thư thông báo sau redesign (WF-005) + các lỗi đã sửa ở trang chi tiết. */
class NotificationsPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin'])->givePermissionTo(Permission::firstOrCreate(['name' => 'create-notifications']));

        $this->owner = User::factory()->create(['status' => 'active']);
        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');
    }

    private function notify(User $user, string $title, bool $read = false, string $type = 'general'): Notification
    {
        return Notification::create([
            'user_id' => $user->id, 'type' => $type, 'title' => $title, 'body' => 'Nội dung',
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_index_shows_read_status_tabs_with_counts_and_filters_unread(): void
    {
        $this->notify($this->owner, 'TB chưa đọc 1');
        $this->notify($this->owner, 'TB chưa đọc 2');
        $this->notify($this->owner, 'TB đã đọc', true);

        $all = preg_replace('/\s+/', ' ', $this->actingAs($this->owner)->get(route('notifications.index'))->assertOk()->getContent());
        $this->assertStringContainsString('Tất cả <span class="status-tab-count">3</span>', $all);
        $this->assertStringContainsString('Chưa đọc <span class="status-tab-count">2</span>', $all);
        $this->assertStringContainsString('Đã đọc <span class="status-tab-count">1</span>', $all);

        // Chỉ xét vùng danh sách (menu chuông trên topbar cũng liệt kê thông báo gần đây)
        $html = $this->actingAs($this->owner)->get(route('notifications.index', ['status' => 'unread']))->assertOk()->getContent();
        $list = \Illuminate\Support\Str::between($html, 'aria-label="Danh sách thông báo"', '</ul>');
        $this->assertStringContainsString('TB chưa đọc 1', $list);
        $this->assertStringNotContainsString('TB đã đọc', $list);
    }

    public function test_index_does_not_expose_user_list_without_permission(): void
    {
        $this->actingAs($this->owner)->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('id="createNotificationModal"', false)
            ->assertDontSee($this->admin->email);
    }

    public function test_owner_opening_notification_marks_it_read(): void
    {
        $n = $this->notify($this->owner, 'Của tôi');

        $this->actingAs($this->owner)->get(route('notifications.show', $n))->assertOk();

        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_admin_viewing_someone_elses_notification_does_not_mark_it_read(): void
    {
        $n = $this->notify($this->owner, 'Của nhân viên');

        $this->actingAs($this->admin)->get(route('notifications.show', $n))
            ->assertOk()
            ->assertSee('trạng thái đọc không thay đổi');

        $this->assertNull($n->fresh()->read_at);
    }

    public function test_other_user_without_permission_cannot_view_notification(): void
    {
        $n = $this->notify($this->owner, 'Riêng tư');
        $stranger = User::factory()->create(['status' => 'active']);

        $this->actingAs($stranger)->get(route('notifications.show', $n))->assertForbidden();
    }

    public function test_show_links_newer_and_older_notifications_correctly(): void
    {
        $older = $this->notify($this->owner, 'Cũ');
        $current = $this->notify($this->owner, 'Giữa');
        $newer = $this->notify($this->owner, 'Mới');

        $html = $this->actingAs($this->owner)->get(route('notifications.show', $current))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('notifications.show', $newer), '#') . '"[^>]*title="Thông báo mới hơn"#', $html);
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('notifications.show', $older), '#') . '"[^>]*title="Thông báo cũ hơn"#', $html);
    }

    public function test_deleting_from_detail_page_redirects_to_inbox(): void
    {
        $n = $this->notify($this->owner, 'Sẽ xoá');

        $this->actingAs($this->owner)
            ->from(route('notifications.show', $n))
            ->delete(route('notifications.destroy', $n))
            ->assertRedirect(route('notifications.index'));

        $this->assertDatabaseMissing('notifications', ['id' => $n->id]);
    }

    public function test_deleting_from_inbox_returns_back(): void
    {
        $n = $this->notify($this->owner, 'Xoá từ danh sách');

        $this->actingAs($this->owner)
            ->from(route('notifications.index', ['status' => 'unread']))
            ->delete(route('notifications.destroy', $n))
            ->assertRedirect(route('notifications.index', ['status' => 'unread']));
    }

    private function createSamplePenalty(): Penalty
    {
        $branch = Branch::create(['name' => 'Chi nhánh 1', 'code' => 'CN1', 'address' => 'HN']);
        $employee = Employee::create([
            'name' => 'NV Test', 'code' => 'NV001', 'email' => 'nv@test.com',
            'branch_id' => $branch->id, 'is_active' => true,
        ]);
        $violation = Violation::create(['name' => 'Đi trễ', 'points_deducted' => 5, 'money_deducted' => 0, 'is_active' => true]);

        return Penalty::create([
            'code' => 'PEN-TEST',
            'employee_id' => $employee->id,
            'violation_id' => $violation->id,
            'status' => 'pending',
            'total_points_deducted' => 5,
            'total_money_deducted' => 0,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_opening_notification_with_action_url_redirects_directly_to_target(): void
    {
        $penalty = $this->createSamplePenalty();

        $n = Notification::create([
            'user_id' => $this->owner->id,
            'type'    => 'penalty_created',
            'title'   => 'Phiếu phạt cần duyệt',
            'body'    => 'Có phiếu phạt mới',
            'data'    => ['penalty_id' => $penalty->id],
        ]);

        $response = $this->actingAs($this->owner)->get(route('notifications.show', $n));

        $response->assertRedirect(route('penalties.show', $penalty));
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_opening_notification_with_stay_param_does_not_redirect(): void
    {
        $penalty = $this->createSamplePenalty();

        $n = Notification::create([
            'user_id' => $this->owner->id,
            'type'    => 'penalty_created',
            'title'   => 'Phiếu phạt cần duyệt',
            'body'    => 'Có phiếu phạt mới',
            'data'    => ['penalty_id' => $penalty->id],
        ]);

        $response = $this->actingAs($this->owner)->get(route('notifications.show', ['notification' => $n, 'stay' => 1]));

        $response->assertOk();
        $this->assertNotNull($n->fresh()->read_at);
    }
}
