<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DeviceSession;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Device Sessions — list and revoke.
 */
class DeviceSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    private function createSession(User $user): DeviceSession
    {
        return DeviceSession::create([
            'user_id' => $user->id,
            'session_token' => Str::random(40),
            'device_name' => 'Test Device',
            'device_type' => 'desktop',
            'ip_address' => '127.0.0.1',
            'last_active_at' => now(),
        ]);
    }

    // ── List ─────────────────────────────────────────────────────────────────

    #[Test]
    public function authenticated_user_can_list_own_device_sessions(): void
    {
        $this->createSession($this->cashier);

        // list() returns a plain JSON array, not {sessions: [...]}
        $this->actingAs($this->cashier)
            ->getJson('/api/device-sessions')
            ->assertOk()
            ->assertJsonIsArray();
    }

    #[Test]
    public function guest_cannot_list_device_sessions(): void
    {
        $this->getJson('/api/device-sessions')->assertUnauthorized();
    }

    // ── Revoke ────────────────────────────────────────────────────────────────

    #[Test]
    public function user_can_revoke_own_device_session(): void
    {
        $session = $this->createSession($this->cashier);

        $this->actingAs($this->cashier)
            ->deleteJson("/api/device-sessions/{$session->id}")
            ->assertOk();

        // is_active is a computed attribute: is_null(revoked_at)
        $this->assertFalse($session->fresh()->is_active);
    }

    #[Test]
    public function admin_can_revoke_any_device_session(): void
    {
        $session = $this->createSession($this->cashier);

        // Admin bypass: controller passes null userId for admins so service skips ownership check
        $this->actingAs($this->admin)
            ->deleteJson("/api/device-sessions/{$session->id}")
            ->assertOk();
    }

    #[Test]
    public function user_cannot_revoke_another_users_session(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('cashier');
        $session = $this->createSession($other);

        // Service returns false → controller returns 404 (session "not found" for this user)
        $this->actingAs($this->cashier)
            ->deleteJson("/api/device-sessions/{$session->id}")
            ->assertNotFound();
    }

    #[Test]
    public function user_can_revoke_all_own_sessions(): void
    {
        $this->createSession($this->cashier);
        $this->createSession($this->cashier);

        // revokeAll() returns JSON {message: "N session(s) revoked"}
        $this->actingAs($this->cashier)
            ->deleteJson('/api/device-sessions/revoke-all')
            ->assertOk();
    }
}
