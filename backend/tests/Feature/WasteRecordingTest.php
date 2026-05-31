<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Waste / Spoilage recording — store, history, authorization.
 */
class WasteRecordingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');

        $this->product = Product::factory()->create(['quantity' => 20, 'cost_price' => 10]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_record_waste(): void
    {
        $res = $this->actingAs($this->admin)
            ->postJson('/api/waste', [
                'product_id' => $this->product->id,
                'quantity' => 3,
                'reason' => 'expired',
                'notes' => 'Batch expired on shelf',
            ]);

        $res->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('waste_records', [
            'product_id' => $this->product->id,
            'reason' => 'expired',
        ]);
    }

    #[Test]
    public function waste_without_notes_is_accepted(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/waste', [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'reason' => 'damaged',
            ])->assertOk();
    }

    #[Test]
    public function waste_deducts_stock(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/waste', [
                'product_id' => $this->product->id,
                'quantity' => 5,
                'reason' => 'damaged',
            ])->assertOk();

        $this->assertEquals(15, $this->product->fresh()->quantity);
    }

    #[Test]
    public function cannot_record_waste_exceeding_stock(): void
    {
        // Controller now returns 422 for insufficient stock
        $this->actingAs($this->admin)
            ->postJson('/api/waste', [
                'product_id' => $this->product->id,
                'quantity' => 100,   // more than the 20 in stock
                'reason' => 'expired',
            ])->assertStatus(422);
    }

    #[Test]
    public function waste_requires_valid_reason(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/waste', [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'reason' => 'aliens_ate_it',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    #[Test]
    public function waste_requires_product_id(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/waste', [
                'quantity' => 1,
                'reason' => 'expired',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    #[Test]
    public function cashier_cannot_record_waste(): void
    {
        // Route middleware: permission:add_stock — cashier does not have it
        $this->actingAs($this->cashier)
            ->postJson('/api/waste', [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'reason' => 'expired',
            ])->assertForbidden();
    }

    #[Test]
    public function guest_cannot_record_waste(): void
    {
        $this->postJson('/api/waste', [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'reason' => 'expired',
        ])->assertUnauthorized();
    }

    // ── History ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_view_waste_history(): void
    {
        // Record one first so history is non-empty
        $this->actingAs($this->admin)->postJson('/api/waste', [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'reason' => 'expired',
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/waste')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    #[Test]
    public function history_can_be_filtered_by_date(): void
    {
        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $this->actingAs($this->admin)
            ->getJson("/api/waste?start_date={$from}&end_date={$to}")
            ->assertOk();
    }

    #[Test]
    public function history_rejects_invalid_date_range(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/waste?start_date=2026-06-01&end_date=2026-05-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }
}
