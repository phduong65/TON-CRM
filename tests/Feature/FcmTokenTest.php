<?php

namespace Tests\Feature;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FcmTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_fcm_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('fcm-tokens.store'), [
            'token' => 'test-token-abc123',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $user->id,
            'token'   => 'test-token-abc123',
        ]);
    }

    public function test_registering_same_token_transfers_ownership_to_new_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        FcmToken::create(['user_id' => $userA->id, 'token' => 'shared-token']);

        $this->actingAs($userB)->postJson(route('fcm-tokens.store'), ['token' => 'shared-token'])
            ->assertOk();

        $this->assertDatabaseHas('fcm_tokens', ['token' => 'shared-token', 'user_id' => $userB->id]);
        $this->assertEquals(1, FcmToken::where('token', 'shared-token')->count());
    }

    public function test_user_can_delete_own_token(): void
    {
        $user = User::factory()->create();
        FcmToken::create(['user_id' => $user->id, 'token' => 'my-token']);

        $this->actingAs($user)->deleteJson(route('fcm-tokens.destroy'), ['token' => 'my-token'])
            ->assertOk();

        $this->assertDatabaseMissing('fcm_tokens', ['token' => 'my-token']);
    }

    public function test_user_cannot_delete_another_users_token(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        FcmToken::create(['user_id' => $userA->id, 'token' => 'a-token']);

        $this->actingAs($userB)->deleteJson(route('fcm-tokens.destroy'), ['token' => 'a-token'])
            ->assertOk();

        $this->assertDatabaseHas('fcm_tokens', ['token' => 'a-token', 'user_id' => $userA->id]);
    }

    public function test_guest_cannot_register_token(): void
    {
        $response = $this->postJson(route('fcm-tokens.store'), ['token' => 'x']);
        $response->assertStatus(401);
    }

    public function test_service_worker_route_returns_javascript(): void
    {
        $response = $this->get('/firebase-messaging-sw.js');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/javascript');
        $response->assertSee('firebase.initializeApp', false);
    }
}
