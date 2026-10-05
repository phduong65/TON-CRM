<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_receive_token(): void
    {
        $user = User::factory()->create([
            'email'    => 'staff@example.com',
            'password' => 'secret123',
            'status'   => 'active',
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'staff@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'staff@example.com',
            'password' => 'secret123',
            'status'   => 'active',
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'staff@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(422);
    }

    public function test_pending_user_cannot_login(): void
    {
        User::factory()->create([
            'email'    => 'pending@example.com',
            'password' => 'secret123',
            'status'   => 'pending',
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'pending@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422);
    }

    public function test_me_returns_authenticated_user_with_employee(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Employee::create(['user_id' => $user->id, 'code' => 'EMP-01', 'name' => 'Nguyễn Văn A', 'is_active' => true]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/me');

        $response->assertOk()->assertJsonPath('user.employee.code', 'EMP-01');
    }

    public function test_logout_revokes_current_token(): void
    {
        $user  = User::factory()->create(['status' => 'active']);
        $accessToken = $user->createToken('test');

        $this->withHeader('Authorization', "Bearer {$accessToken->plainTextToken}")
            ->postJson('/api/logout')->assertOk();

        // Xác nhận token bị xoá khỏi DB trực tiếp — gọi lại request thứ hai trong cùng 1 test
        // không đáng tin cậy vì Illuminate\Auth\RequestGuard cache resolved user theo instance
        // guard (AuthManager giữ lại cùng 1 guard suốt vòng đời container test), không phản ánh
        // đúng hành vi thực tế (mỗi request thật là 1 request/guard riêng biệt).
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $accessToken->accessToken->id]);
    }

    public function test_guest_cannot_access_protected_route(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }
}
