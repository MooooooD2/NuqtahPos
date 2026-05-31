<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PriceRule;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dynamic Pricing Rules — CRUD, toggle, evaluate.
 */
class DynamicPricingTest extends TestCase
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

    private function makeRule(array $overrides = []): PriceRule
    {
        return PriceRule::create(array_merge([
            'name' => 'Test Rule ' . uniqid(),
            'rule_type' => 'happy_hour',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ], $overrides));
    }

    private function validRulePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Happy Hour',
            'rule_type' => 'happy_hour',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'time_start' => '18:00',
            'time_end' => '20:00',
            'is_active' => true,
        ], $overrides);
    }

    // ── List ─────────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_list_pricing_rules(): void
    {
        $this->makeRule();
        $this->makeRule();

        $this->actingAs($this->cashier)
            ->getJson('/api/pricing-rules')
            ->assertOk();
    }

    #[Test]
    public function guest_cannot_access_pricing_rules(): void
    {
        $this->getJson('/api/pricing-rules')->assertUnauthorized();
    }

    // ── Create ───────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_create_pricing_rule(): void
    {
        $res = $this->actingAs($this->cashier)
            ->postJson('/api/pricing-rules', $this->validRulePayload());

        $res->assertStatus(201)
            ->assertJsonStructure(['rule']);

        $this->assertDatabaseHas('price_rules', ['name' => 'Happy Hour']);
    }

    #[Test]
    public function pricing_rule_requires_name(): void
    {
        $payload = $this->validRulePayload();
        unset($payload['name']);

        $this->actingAs($this->cashier)
            ->postJson('/api/pricing-rules', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function pricing_rule_requires_valid_type(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/pricing-rules', $this->validRulePayload(['rule_type' => 'magic']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rule_type']);
    }

    #[Test]
    public function pricing_rule_requires_valid_discount_type(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/pricing-rules', $this->validRulePayload(['discount_type' => 'negative']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_type']);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_update_pricing_rule(): void
    {
        $rule = $this->makeRule();

        $this->actingAs($this->cashier)
            ->putJson("/api/pricing-rules/{$rule->id}", $this->validRulePayload(['name' => 'Updated Rule']))
            ->assertOk()
            ->assertJsonPath('rule.name', 'Updated Rule');
    }

    // ── Toggle ────────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_toggle_pricing_rule(): void
    {
        $rule = $this->makeRule(['is_active' => true]);

        $res = $this->actingAs($this->cashier)
            ->patchJson("/api/pricing-rules/{$rule->id}/toggle");

        $res->assertOk()->assertJsonPath('is_active', false);
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_delete_pricing_rule(): void
    {
        $rule = $this->makeRule();

        $this->actingAs($this->cashier)
            ->deleteJson("/api/pricing-rules/{$rule->id}")
            ->assertOk();

        $this->assertDatabaseMissing('price_rules', ['id' => $rule->id]);
    }

    // ── Evaluate ─────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_evaluate_pricing_for_products(): void
    {
        $product = Product::factory()->create(['price' => 100.00]);

        $res = $this->actingAs($this->cashier)
            ->postJson('/api/pricing-rules/evaluate', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $res->assertOk()
            ->assertJsonStructure(['prices', 'happy_hour_active']);
    }

    #[Test]
    public function evaluate_requires_items_array(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/pricing-rules/evaluate', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }
}
