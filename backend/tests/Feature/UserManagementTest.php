<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * User CRUD, toggle-active, and authorization.
 */
class UserManagementTest extends TestCase
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

    // ── List ─────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_users(): void
    {
        $res = $this->actingAs($this->admin)->getJson('/api/users');

        $res->assertOk()
            ->assertJsonStructure(['success', 'users']);
    }

    #[Test]
    public function cashier_cannot_list_users(): void
    {
        $this->actingAs($this->cashier)
            ->getJson('/api/users')
            ->assertForbidden();
    }

    #[Test]
    public function guest_cannot_list_users(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    // ── Create ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_create_user(): void
    {
        $res = $this->actingAs($this->admin)
            ->postJson('/api/users', [
                'username' => 'newuser01',
                'full_name' => 'New User',
                'password' => 'Password1!',
                'role' => 'cashier',
            ]);

        $res->assertStatus(201)
            ->assertJsonStructure(['success', 'user']);

        $this->assertDatabaseHas('users', ['username' => 'newuser01']);
    }

    #[Test]
    public function create_user_requires_username(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/users', [
                'full_name' => 'No Username',
                'password' => 'Password1!',
                'role' => 'cashier',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    #[Test]
    public function create_user_rejects_duplicate_username(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/users', [
                'username' => $this->cashier->username,
                'full_name' => 'Dup User',
                'password' => 'Password1!',
                'role' => 'cashier',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    #[Test]
    public function cashier_cannot_create_user(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/users', [
                'username' => 'hacker',
                'full_name' => 'Hacker',
                'password' => 'Password1!',
                'role' => 'cashier',
            ])->assertForbidden();
    }

    // ── Update ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_update_user(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('cashier');

        $this->actingAs($this->admin)
            ->putJson("/api/users/{$target->id}", [
                'full_name' => 'Updated Name',
                'role' => 'cashier',
            ])->assertOk()
            ->assertJsonPath('user.full_name', 'Updated Name');
    }

    #[Test]
    public function cashier_cannot_update_another_user(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('cashier');

        $this->actingAs($this->cashier)
            ->putJson("/api/users/{$other->id}", [
                'full_name' => 'Pwned',
                'role' => 'cashier',
            ])->assertForbidden();
    }

    // ── Toggle Active ─────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_toggle_cashier_active_status(): void
    {
        $res = $this->actingAs($this->admin)
            ->postJson("/api/users/{$this->cashier->id}/toggle-active");

        $res->assertOk()
            ->assertJsonStructure(['success', 'is_active']);
        $this->assertFalse((bool) $res->json('is_active'));
    }

    #[Test]
    public function cannot_toggle_admin_active_status(): void
    {
        // UserService throws exception for admin users
        $res = $this->actingAs($this->admin)
            ->postJson("/api/users/{$this->admin->id}/toggle-active");

        // Should fail with error
        $res->assertStatus(403);
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_delete_cashier(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('cashier');

        $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$target->id}")
            ->assertOk();

        // User uses SoftDeletes — the row is still in the DB but with deleted_at set
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    #[Test]
    public function cashier_cannot_delete_user(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('cashier');

        $this->actingAs($this->cashier)
            ->deleteJson("/api/users/{$other->id}")
            ->assertForbidden();
    }

    #[Test]
    public function admin_cannot_delete_another_admin(): void
    {
        $otherAdmin = User::factory()->create(['is_active' => true]);
        $otherAdmin->assignRole('admin');

        $res = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$otherAdmin->id}");

        // UserService throws "cannot_delete_admin" — returns 403
        $res->assertStatus(403);
    }
}
