<?php

declare(strict_types=1);

namespace Tests\Feature\Phase11;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 11 — Mobile API v1 Tests
 */
class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_requires_credentials_to_login(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password', 'device_name']);
    }

    #[Test]
    public function it_rejects_invalid_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
            'device_name' => 'TestDevice',
        ])->assertStatus(422);
    }

    #[Test]
    public function it_issues_a_token_on_valid_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'device_name' => 'TestDevice',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user']);
    }

    #[Test]
    public function authenticated_user_can_get_me(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    #[Test]
    public function unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    #[Test]
    public function staff_can_get_dashboard_when_authenticated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/staff/dashboard')->assertOk();
    }
}
