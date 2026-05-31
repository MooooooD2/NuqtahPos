<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Customer Group CRUD — warehouse+ access.
 */
class CustomerGroupTest extends TestCase
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

    private function makeGroup(string $name = 'Test Group', array $extra = []): CustomerGroup
    {
        return CustomerGroup::create(array_merge([
            'name' => $name,
            'is_active' => true,
        ], $extra));
    }

    // ── List ─────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_customer_groups(): void
    {
        $this->makeGroup('Gold');
        $this->makeGroup('Silver');

        $this->actingAs($this->admin)
            ->getJson('/api/customer-groups')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    #[Test]
    public function cashier_cannot_list_customer_groups(): void
    {
        $this->actingAs($this->cashier)
            ->getJson('/api/customer-groups')
            ->assertForbidden();
    }

    // ── Create ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_create_customer_group(): void
    {
        $res = $this->actingAs($this->admin)
            ->postJson('/api/customer-groups', [
                'name' => 'VIP Customers',
                'discount_percent' => 15,
                'price_level' => 'vip',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('group.name', 'VIP Customers');

        $this->assertDatabaseHas('customer_groups', ['name' => 'VIP Customers']);
    }

    #[Test]
    public function group_name_must_be_unique(): void
    {
        $this->makeGroup('Gold');

        $this->actingAs($this->admin)
            ->postJson('/api/customer-groups', ['name' => 'Gold'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function discount_percent_must_be_0_to_100(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/customer-groups', [
                'name' => 'Silly Group',
                'discount_percent' => 150,
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['discount_percent']);
    }

    #[Test]
    public function price_level_must_be_valid(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/customer-groups', [
                'name' => 'Bad Level',
                'price_level' => 'platinum',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['price_level']);
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_view_single_group(): void
    {
        $group = $this->makeGroup('Platinum');

        $this->actingAs($this->admin)
            ->getJson("/api/customer-groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('group.name', 'Platinum');
    }

    // ── Update ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_update_customer_group(): void
    {
        $group = $this->makeGroup('Silver');

        $this->actingAs($this->admin)
            ->putJson("/api/customer-groups/{$group->id}", ['name' => 'Silver Plus'])
            ->assertOk()
            ->assertJsonPath('group.name', 'Silver Plus');
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_delete_empty_group(): void
    {
        $group = $this->makeGroup('Empty Group');

        $this->actingAs($this->admin)
            ->deleteJson("/api/customer-groups/{$group->id}")
            ->assertOk();

        $this->assertDatabaseMissing('customer_groups', ['id' => $group->id]);
    }

    #[Test]
    public function cannot_delete_group_with_customers(): void
    {
        $group = $this->makeGroup('Busy Group');

        Customer::create([
            'name' => 'Test Customer',
            'code' => 'CUST001',
            'customer_group_id' => $group->id,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/customer-groups/{$group->id}")
            ->assertStatus(422);
    }
}
