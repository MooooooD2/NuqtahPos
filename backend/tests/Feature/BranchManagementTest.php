<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Branch CRUD — admin-only resource.
 */
class BranchManagementTest extends TestCase
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

    private function makeBranch(string $code = 'BR01', array $extra = []): Branch
    {
        return Branch::create(array_merge([
            'name' => "Branch {$code}",
            'code' => $code,
            'is_active' => true,
            'is_default' => false,
        ], $extra));
    }

    // ── List ─────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_branches(): void
    {
        $this->makeBranch('LB01');
        $this->makeBranch('LB02');

        $this->actingAs($this->admin)
            ->getJson('/api/branches')
            ->assertOk();
    }

    #[Test]
    public function cashier_cannot_list_branches(): void
    {
        $this->actingAs($this->cashier)
            ->getJson('/api/branches')
            ->assertForbidden();
    }

    #[Test]
    public function guest_cannot_access_branches(): void
    {
        $this->getJson('/api/branches')->assertUnauthorized();
    }

    // ── Create ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_create_branch(): void
    {
        $res = $this->actingAs($this->admin)
            ->postJson('/api/branches', [
                'name' => 'Downtown Branch',
                'code' => 'DT01',
                'address' => '123 Main St',
                'phone' => '0101234567',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('branches', ['code' => 'DT01']);
    }

    #[Test]
    public function branch_requires_name_and_code(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/branches', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code']);
    }

    #[Test]
    public function branch_code_must_be_unique(): void
    {
        $this->makeBranch('DUPE');

        $this->actingAs($this->admin)
            ->postJson('/api/branches', [
                'name' => 'Another Branch',
                'code' => 'DUPE',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function cashier_cannot_create_branch(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/branches', ['name' => 'Bad', 'code' => 'X01'])
            ->assertForbidden();
    }

    // ── Update ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_update_branch(): void
    {
        $branch = $this->makeBranch('UPD1', ['name' => 'Old Name']);

        $this->actingAs($this->admin)
            ->putJson("/api/branches/{$branch->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('branch.name', 'New Name');
    }

    #[Test]
    public function update_allows_same_code_on_same_branch(): void
    {
        $branch = $this->makeBranch('SAME');

        $this->actingAs($this->admin)
            ->putJson("/api/branches/{$branch->id}", [
                'name' => 'Changed Name',
                'code' => 'SAME',
            ])->assertOk();
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_delete_branch(): void
    {
        $branch = $this->makeBranch('DEL1');

        $this->actingAs($this->admin)
            ->deleteJson("/api/branches/{$branch->id}")
            ->assertOk();

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    #[Test]
    public function cashier_cannot_delete_branch(): void
    {
        $branch = $this->makeBranch('ND01');

        $this->actingAs($this->cashier)
            ->deleteJson("/api/branches/{$branch->id}")
            ->assertForbidden();
    }
}
