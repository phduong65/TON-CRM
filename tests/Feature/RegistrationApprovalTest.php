<?php

namespace Tests\Feature;

use App\Mail\AccountApprovedMail;
use App\Mail\AccountRejectedMail;
use App\Mail\NewUserRegisteredMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Đăng ký tự do (routes/auth.php — register) không còn tự động đăng nhập / cấp quyền truy cập.
 * Tài khoản tạo ra ở trạng thái 'pending' — chỉ dùng được sau khi Admin duyệt (gán vai trò qua
 * UsersController::update()) hoặc bị Admin từ chối (UsersController::reject()).
 */
class RegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-users']));

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');
    }

    // ── Đăng ký ──────────────────────────────────────────────────────────

    public function test_registration_creates_pending_account_without_role(): void
    {
        Mail::fake();

        $response = $this->post(route('register'), [
            'name'                  => 'Nguyễn Văn Test',
            'email'                 => 'test-register@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'test-register@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('pending', $user->status);
        $this->assertTrue($user->roles->isEmpty());
        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_registration_notifies_admin_in_app_and_via_email(): void
    {
        Mail::fake();

        $this->post(route('register'), [
            'name'                  => 'Nguyễn Văn Test',
            'email'                 => 'test-register2@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'test-register2@example.com')->first();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin->id,
            'type'    => 'account_registered',
        ]);

        Mail::assertSent(NewUserRegisteredMail::class, function ($mail) use ($user) {
            return $mail->hasTo($this->admin->email) && $mail->registeredUser->is($user);
        });
    }

    public function test_pending_user_cannot_login(): void
    {
        $user = User::factory()->create(['status' => 'pending', 'password' => bcrypt('password123')]);

        $response = $this->post(route('login'), [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['status' => 'inactive', 'password' => bcrypt('password123')]);

        $response = $this->post(route('login'), [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_active_user_can_login(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => bcrypt('password123')]);

        $response = $this->post(route('login'), [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    // ── Duyệt (approve) ──────────────────────────────────────────────────

    public function test_admin_approving_pending_user_assigns_role_and_activates(): void
    {
        Mail::fake();
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $pending = User::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->admin)->put(route('users.update', $pending), [
            'name'  => $pending->name,
            'email' => $pending->email,
            'role'  => 'staff',
        ]);

        $response->assertRedirect(route('users.index'));
        $pending->refresh();

        $this->assertSame('active', $pending->status);
        $this->assertTrue($pending->hasRole('staff'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $pending->id,
            'type'    => 'account_approved',
        ]);
        Mail::assertSent(AccountApprovedMail::class, fn($mail) => $mail->hasTo($pending->email));
    }

    public function test_approved_user_can_then_login(): void
    {
        Mail::fake();
        Role::firstOrCreate(['name' => 'staff']);
        $pending = User::factory()->create(['status' => 'pending', 'password' => bcrypt('password123')]);

        $this->actingAs($this->admin)->put(route('users.update', $pending), [
            'name'  => $pending->name,
            'email' => $pending->email,
            'role'  => 'staff',
        ]);

        auth()->logout();

        $response = $this->post(route('login'), [
            'email'    => $pending->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($pending);
    }

    // ── Từ chối (reject) ─────────────────────────────────────────────────

    public function test_admin_can_reject_pending_user(): void
    {
        Mail::fake();
        $pending = User::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->admin)->post(route('users.reject', $pending));

        $response->assertRedirect();
        $pending->refresh();

        $this->assertSame('inactive', $pending->status);
        Mail::assertSent(AccountRejectedMail::class, fn($mail) => $mail->hasTo($pending->email));
    }

    public function test_cannot_reject_a_non_pending_user(): void
    {
        $active = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($this->admin)->post(route('users.reject', $active));

        $response->assertSessionHas('error');
        $active->refresh();
        $this->assertSame('active', $active->status);
    }

    public function test_toggle_status_rejects_pending_account(): void
    {
        $pending = User::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->admin)->post(route('users.toggleStatus', $pending));

        $response->assertSessionHas('error');
        $pending->refresh();
        $this->assertSame('pending', $pending->status);
    }

    public function test_non_admin_cannot_approve_or_reject(): void
    {
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffUser = User::factory()->create(['status' => 'active']);
        $staffUser->assignRole('staff');

        $pending = User::factory()->create(['status' => 'pending']);

        $this->actingAs($staffUser)->put(route('users.update', $pending), [
            'name' => $pending->name, 'email' => $pending->email, 'role' => 'staff',
        ])->assertStatus(403);

        $this->actingAs($staffUser)->post(route('users.reject', $pending))->assertStatus(403);
    }
}
