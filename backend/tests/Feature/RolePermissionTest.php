<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Role & Permission management — CRUD, sync, user assignment.
 */
class RolePermissionTest extends TestCase
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

    // ── List Roles ────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_roles(): void
    {
        // getRoles() → {success: true, roles: [...]}
        $this->actingAs($this->admin)
            ->getJson('/api/roles')
            ->assertOk()
            ->assertJsonStructure(['success', 'roles']);
    }

    #[Test]
    public function cashier_cannot_list_roles(): void
    {
        $this->actingAs($this->cashier)
            ->getJson('/api/roles')
            ->assertForbidden();
    }

    // ── List Permissions ──────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_permissions(): void
    {
        // getPermissions() → {success: true, permissions: [...]}
        $this->actingAs($this->admin)
            ->getJson('/api/permissions')
            ->assertOk()
            ->assertJsonStructure(['success', 'permissions']);
    }

    // ── Create Role ───────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_create_role(): void
    {
        // storeRole() only validates 'name' and 'guard_name'; returns {success: true, role: {...}}
        $res = $this->actingAs($this->admin)
            ->postJson('/api/roles', [
                'name' => 'supervisor',
            ]);

        $res->assertStatus(201)
            ->assertJsonStructure(['success', 'role']);

        $this->assertDatabaseHas('roles', ['name' => 'supervisor']);
    }

    #[Test]
    public function role_name_must_be_unique(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/roles', ['name' => 'admin'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function cashier_cannot_create_role(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/roles', ['name' => 'hacked_role'])
            ->assertForbidden();
    }

    // ── Update Role ───────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_update_role(): void
    {
        $role = Role::create(['name' => 'tester', 'guard_name' => 'web']);

        // updateRole() validates 'name' as required
        $this->actingAs($this->admin)
            ->putJson("/api/roles/{$role->id}", ['name' => 'qa_tester'])
            ->assertOk();
    }

    // ── Sync Permissions ──────────────────────────────────────────────────────

    #[Test]
    public function admin_can_sync_permissions_on_role(): void
    {
        $role = Role::create(['name' => 'reporter', 'guard_name' => 'web']);

        $this->actingAs($this->admin)
            ->postJson("/api/roles/{$role->id}/permissions", [
                'permissions' => ['view_reports'],
            ])->assertOk();

        $this->assertTrue($role->fresh()->hasPermissionTo('view_reports'));
    }

    // ── Delete Role ───────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_delete_custom_role(): void
    {
        $role = Role::create(['name' => 'to_delete', 'guard_name' => 'web']);

        $this->actingAs($this->admin)
            ->deleteJson("/api/roles/{$role->id}")
            ->assertOk();

        $this->assertDatabaseMissing('roles', ['name' => 'to_delete']);
    }

    // ── User Roles ────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_user_roles(): void
    {
        // getUserRoles() → {success: true, roles: [...], all_roles: [...]}
        $this->actingAs($this->admin)
            ->getJson("/api/users/{$this->cashier->id}/roles")
            ->assertOk()
            ->assertJsonStructure(['success', 'roles', 'all_roles']);
    }

    #[Test]
    public function admin_can_assign_role_to_user(): void
    {
        $target = User::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)
            ->postJson("/api/users/{$target->id}/roles", ['role' => 'cashier'])
            ->assertOk();

        $this->assertTrue($target->fresh()->hasRole('cashier'));
    }
}
