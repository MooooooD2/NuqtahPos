<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HeldInvoice;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Held Invoice — hold, list active, resume, discard.
 */
class HeldInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private User $admin;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');

        $this->product = Product::factory()->create(['price' => 50.00, 'quantity' => 10]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    /** Payload for the POST /api/held-invoices endpoint. */
    private function holdPayload(): array
    {
        return [
            'notes' => 'Table 3 order',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => 'Test Product',
                    'quantity' => 2,
                    'price' => 50.00,
                ],
            ],
        ];
    }

    /** Create a HeldInvoice row directly (no factory). */
    private function makeHeldInvoice(int $cashierId, string $status = 'held'): HeldInvoice
    {
        return HeldInvoice::create([
            'hold_number' => 'HLD-' . uniqid(),
            'cashier_id' => $cashierId,
            'cashier_name' => 'Test Cashier',
            'cart_data' => [
                'items' => $this->holdPayload()['items'],
                'discount' => 0,
            ],
            'subtotal' => 100.00,
            'discount_amount' => 0.00,
            'total' => 100.00,
            'status' => $status,
        ]);
    }

    // ── List ─────────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_list_active_held_invoices(): void
    {
        $this->actingAs($this->cashier)
            ->getJson('/api/held-invoices')
            ->assertOk()
            ->assertJsonStructure(['success', 'held_invoices']);
    }

    #[Test]
    public function guest_cannot_access_held_invoices(): void
    {
        $this->getJson('/api/held-invoices')->assertUnauthorized();
    }

    // ── Hold ─────────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_hold_an_invoice(): void
    {
        $res = $this->actingAs($this->cashier)
            ->postJson('/api/held-invoices', $this->holdPayload());

        $res->assertStatus(201)
            ->assertJsonStructure(['success', 'held_invoice']);

        $this->assertDatabaseHas('held_invoices', ['cashier_id' => $this->cashier->id]);
    }

    #[Test]
    public function holding_invoice_requires_items(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/held-invoices', ['items' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    // ── Resume ────────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_resume_held_invoice(): void
    {
        $held = $this->makeHeldInvoice($this->cashier->id);

        $this->actingAs($this->cashier)
            ->postJson("/api/held-invoices/{$held->id}/resume")
            ->assertOk()
            ->assertJsonStructure(['success', 'held_invoice']);
    }

    // ── Discard ───────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_discard_own_held_invoice(): void
    {
        $held = $this->makeHeldInvoice($this->cashier->id);

        $this->actingAs($this->cashier)
            ->deleteJson("/api/held-invoices/{$held->id}")
            ->assertOk();

        // Discard soft-updates status to 'discarded' (record stays in DB)
        $this->assertDatabaseHas('held_invoices', ['id' => $held->id, 'status' => 'discarded']);
    }

    #[Test]
    public function cashier_cannot_discard_another_cashiers_held_invoice(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('cashier');

        $held = $this->makeHeldInvoice($other->id);

        $this->actingAs($this->cashier)
            ->deleteJson("/api/held-invoices/{$held->id}")
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_discard_any_held_invoice(): void
    {
        $held = $this->makeHeldInvoice($this->cashier->id);

        $this->actingAs($this->admin)
            ->deleteJson("/api/held-invoices/{$held->id}")
            ->assertOk();

        $this->assertDatabaseHas('held_invoices', ['id' => $held->id, 'status' => 'discarded']);
    }
}
