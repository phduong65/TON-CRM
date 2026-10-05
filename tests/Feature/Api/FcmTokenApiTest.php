<?php

namespace Tests\Feature\Api;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FcmTokenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_fcm_token(): void
    {
        $user  = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/fcm-tokens', ['token' => 'mobile-token-abc']);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('fcm_tokens', ['token' => 'mobile-token-abc', 'user_id' => $user->id]);
    }

    public function test_can_unregister_own_fcm_token(): void
    {
        $user  = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;
        FcmToken::create(['user_id' => $user->id, 'token' => 'mobile-token-abc']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/fcm-tokens', ['token' => 'mobile-token-abc']);

        $response->assertOk();
        $this->assertDatabaseMissing('fcm_tokens', ['token' => 'mobile-token-abc']);
    }

    public function test_cannot_unregister_another_users_token(): void
    {
        $owner  = User::factory()->create(['status' => 'active']);
        $intruder = User::factory()->create(['status' => 'active']);
        $intruderToken = $intruder->createToken('test')->plainTextToken;
        FcmToken::create(['user_id' => $owner->id, 'token' => 'owner-token']);

        $this->withHeader('Authorization', "Bearer {$intruderToken}")
            ->deleteJson('/api/fcm-tokens', ['token' => 'owner-token'])
            ->assertOk();

        // Xoá "thành công" (không lỗi) nhưng token vẫn còn nguyên vì where('user_id', ...) không khớp.
        $this->assertDatabaseHas('fcm_tokens', ['token' => 'owner-token', 'user_id' => $owner->id]);
    }
}
