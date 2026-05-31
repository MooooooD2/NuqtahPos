<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashbackRule;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cashback balance, redemption, history, and rules management.
 */
class CashbackTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');

        // cashback_balance is NOT in $fillable (only the service may change it)
        // Use a direct DB update to seed the test balance without bypassing the guard everywhere.
        $this->customer = Customer::create([
            'code' => 'CUST001',
            'name' => 'Test Customer',
            'is_active' => true,
        ]);
        \Illuminate\Support\Facades\DB::table('customers')
            ->where('id', $this->customer->id)
            ->update(['cashback_balance' => 100.00]);
        $this->customer->refresh();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    private function makeRule(bool $active = false): CashbackRule
    {
        return CashbackRule::create([
            'name' => 'Rule ' . uniqid(),
            'percentage' => 5,
            'is_active' => $active,
        ]);
    }

    // ── Balance ───────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_check_customer_cashback_balance(): void
    {
        $res = $this->actingAs($this->cashier)
            ->getJson("/api/cashback/customer/{$this->customer->id}");

        $res->assertOk()
            ->assertJsonStructure(['customer_id', 'balance', 'active_rate']);

        // JSON encodes whole-number floats as integers; use assertEquals (non-strict)
        $this->assertEquals(100, $res->json('balance'));
    }

    #[Test]
    public function guest_cannot_access_cashback(): void
    {
        $this->getJson("/api/cashback/customer/{$this->customer->id}")
            ->assertUnauthorized();
    }

    // ── Redeem ────────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_redeem_cashback(): void
    {
        $res = $this->actingAs($this->cashier)
            ->postJson('/api/cashback/redeem', [
                'customer_id' => $this->customer->id,
                'amount' => 50.00,
            ]);

        $res->assertOk()
            ->assertJsonStructure(['redeemed', 'new_balance']);

        $this->assertEquals(50, $res->json('new_balance'));
    }

    #[Test]
    public function cannot_redeem_more_than_balance(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/cashback/redeem', [
                'customer_id' => $this->customer->id,
                'amount' => 999.00,   // exceeds balance of 100
            ])->assertStatus(422);
    }

    #[Test]
    public function redeem_requires_valid_customer(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/cashback/redeem', [
                'customer_id' => 99999,
                'amount' => 10.00,
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    #[Test]
    public function redeem_amount_must_be_positive(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/cashback/redeem', [
                'customer_id' => $this->customer->id,
                'amount' => 0,
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    // ── History ───────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_get_cashback_history(): void
    {
        $this->actingAs($this->cashier)
            ->getJson("/api/cashback/history?customer_id={$this->customer->id}")
            ->assertOk()
            ->assertJsonStructure(['transactions', 'balance']);
    }

    #[Test]
    public function cashback_history_requires_customer_id(): void
    {
        $this->actingAs($this->cashier)
            ->getJson('/api/cashback/history')
            ->assertStatus(422);
    }

    // ── Rules ─────────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_list_cashback_rules(): void
    {
        $this->makeRule();

        $this->actingAs($this->cashier)
            ->getJson('/api/cashback/rules')
            ->assertOk()
            ->assertJsonCount(1);
    }

    #[Test]
    public function cashier_can_create_cashback_rule(): void
    {
        $res = $this->actingAs($this->cashier)
            ->postJson('/api/cashback/rules', [
                'name' => '5% on all purchases',
                'percentage' => 5,
                'min_purchase' => 50,
            ]);

        $res->assertStatus(201)
            ->assertJsonStructure(['rule']);

        $this->assertDatabaseHas('cashback_rules', ['name' => '5% on all purchases']);
    }

    #[Test]
    public function cashback_rule_percentage_must_be_positive(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/cashback/rules', [
                'name' => 'Invalid',
                'percentage' => 0,
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['percentage']);
    }

    #[Test]
    public function creating_new_rule_deactivates_previous_rules(): void
    {
        $this->makeRule(true);

        $this->actingAs($this->cashier)
            ->postJson('/api/cashback/rules', [
                'name' => 'New Active Rule',
                'percentage' => 3,
            ])->assertStatus(201);

        $this->assertEquals(1, CashbackRule::where('is_active', true)->count());
    }
}
