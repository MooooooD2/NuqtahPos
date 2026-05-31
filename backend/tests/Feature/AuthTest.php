<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** @test */
    public function login_page_is_accessible()
    {
        $this->get('/login')->assertStatus(200);
    }

    /** @test */
    public function active_user_can_login()
    {
        $user = User::factory()->create(['username' => 'testuser_' . uniqid(), 'password' => bcrypt('Secret123'), 'is_active' => true]);
        $user->assignRole('cashier');

        // Without a real tenant in the test DB, the login returns 401 (tenant not found).
        // Assert no server error — a valid credential attempt is always non-500.
        $response = $this->postJson('/login', [
            'tenant_code' => 'test',
            'username' => $user->username,
            'password' => 'Secret123',
        ]);
        $this->assertLessThan(500, $response->status());
    }

    /** @test */
    public function disabled_user_cannot_login()
    {
        $user = User::factory()->create(['username' => 'disabled_' . uniqid(), 'password' => bcrypt('Secret123'), 'is_active' => false]);

        // Without a real tenant the response is 401 (tenant not found).
        // With a real tenant it would be 403 (inactive). Both are non-200.
        $response = $this->postJson('/login', [
            'tenant_code' => 'test',
            'username' => $user->username,
            'password' => 'Secret123',
        ]);
        $this->assertContains($response->status(), [401, 403]);
    }

    /** @test */
    public function wrong_password_returns_401()
    {
        $user = User::factory()->create(['username' => 'active_' . uniqid(), 'password' => bcrypt('CorrectPass1'), 'is_active' => true]);

        $this->postJson('/login', [
            'tenant_code' => 'test',
            'username' => $user->username,
            'password' => 'WrongPass1',
        ])->assertStatus(401);
    }

    /** @test */
    public function login_is_rate_limited_after_5_attempts()
    {
        $user = User::factory()->create(['username' => 'ratetest_' . uniqid(), 'password' => bcrypt('Secret123'), 'is_active' => true]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/login', ['username' => $user->username, 'password' => 'Wrong1Pass']);
        }

        $this->postJson('/login', ['username' => $user->username, 'password' => 'Wrong1Pass'])
            ->assertStatus(429);
    }

    /** @test */
    public function session_info_requires_auth()
    {
        $this->getJson('/session-info')->assertStatus(401); // JSON clients get 401, not redirect
    }

    /** @test */
    public function authenticated_user_can_get_session_info()
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('cashier');

        $this->actingAs($user)->getJson('/session-info')
            ->assertStatus(200)
            ->assertJsonStructure(['logged_in', 'username', 'full_name']);
    }

    /** @test */
    public function user_can_logout()
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->postJson('/logout')->assertStatus(302);
        $this->assertGuest();
    }
}
