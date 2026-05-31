<?php

namespace Tests\Feature\Offline;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * TC-OFFLINE: Offline invoice sync — idempotency, UUID deduplication, validation, edge cases.
 */
class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->product = Product::factory()->create(['price' => 50.00, 'quantity' => 100]);
    }

    // ── Basic sync ────────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_sync_offline_invoice(): void
    {
        $uuid = Uuid::uuid4()->toString();

        $response = $this->actingAs($this->cashier)->postJson('/api/offline/sync', [
            'invoices' => [
                [
                    'offline_uuid' => $uuid,
                    'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                    'payment_method' => 'cash',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('synced'));
        $this->assertEquals(0, $response->json('skipped'));
    }

    #[Test]
    public function syncing_same_uuid_twice_is_idempotent(): void
    {
        $uuid = Uuid::uuid4()->toString();

        $payload = [
            'invoices' => [
                [
                    'offline_uuid' => $uuid,
                    'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                    'payment_method' => 'cash',
                ],
            ],
        ];

        // First sync — should create
        $first = $this->actingAs($this->cashier)->postJson('/api/offline/sync', $payload);
        $first->assertStatus(200);
        $this->assertEquals(1, $first->json('synced'));

        // Second sync with same UUID — should be skipped
        $second = $this->actingAs($this->cashier)->postJson('/api/offline/sync', $payload);
        $second->assertStatus(200);
        $this->assertEquals(0, $second->json('synced'));
        $this->assertEquals(1, $second->json('skipped'));
    }

    #[Test]
    public function stock_is_deducted_only_once_for_duplicate_uuid(): void
    {
        $initialQty = $this->product->quantity;
        $uuid = Uuid::uuid4()->toString();

        $payload = [
            'invoices' => [
                [
                    'offline_uuid' => $uuid,
                    'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
                    'payment_method' => 'cash',
                ],
            ],
        ];

        $this->actingAs($this->cashier)->postJson('/api/offline/sync', $payload);
        $this->actingAs($this->cashier)->postJson('/api/offline/sync', $payload); // duplicate

        $this->product->refresh();
        $this->assertEquals($initialQty - 2, $this->product->quantity);
    }

    // ── Batch sync ────────────────────────────────────────────────────────────

    #[Test]
    public function multiple_invoices_can_be_synced_in_one_request(): void
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/offline/sync', [
            'invoices' => [
                [
                    'offline_uuid' => Uuid::uuid4()->toString(),
                    'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                    'payment_method' => 'cash',
                ],
                [
                    'offline_uuid' => Uuid::uuid4()->toString(),
                    'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
                    'payment_method' => 'card',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('synced'));
    }

    #[Test]
    public function batch_with_one_duplicate_syncs_only_new_ones(): void
    {
        $existingUuid = Uuid::uuid4()->toString();

        // Pre-sync one invoice
        $this->actingAs($this->cashier)->postJson('/api/offline/sync', [
            'invoices' => [
                [
                    'offline_uuid' => $existingUuid,
                    'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                    'payment_method' => 'cash',
                ],
            ],
        ]);

        // Sync batch with the already-synced UUID plus a new one
        $response = $this->actingAs($this->cashier)->postJson('/api/offline/sync', [
            'invoices' => [
                [
                    'offline_uuid' => $existingUuid, // duplicate
                    'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                    'payment_method' => 'cash',
                ],
                [
                    'offline_uuid' => Uuid::uuid4()->toString(), // new
                    'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                    'payment_method' => 'cash',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('synced'));
        $this->assertEquals(1, $response->json('skipped'));
    }

    // ── Validation ────────────────────────────────────────────────────────────

    #[Test]
    public function sync_requires_invoices_array(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/offline/sync', [])->assertStatus(422);
    }

    #[Test]
    public function sync_requires_valid_uuid(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/offline/sync', [
            'invoices' => [
                [
                    'offline_uuid' => 'not-a-valid-uuid',
                    'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                    'payment_method' => 'cash',
                ],
            ],
        ])->assertStatus(422);
    }

    #[Test]
    public function sync_requires_at_least_one_item(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/offline/sync', [
            'invoices' => [
                [
                    'offline_uuid' => Uuid::uuid4()->toString(),
                    'items' => [], // empty items
                    'payment_method' => 'cash',
                ],
            ],
        ])->assertStatus(422);
    }

    #[Test]
    public function sync_with_nonexistent_product_fails_validation(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/offline/sync', [
            'invoices' => [
                [
                    'offline_uuid' => Uuid::uuid4()->toString(),
                    'items' => [['product_id' => 99999, 'quantity' => 1]],
                    'payment_method' => 'cash',
                ],
            ],
        ])->assertStatus(422);
    }

    #[Test]
    public function sync_rejects_more_than_100_invoices(): void
    {
        $invoices = [];
        for ($i = 0; $i < 101; $i++) {
            $invoices[] = [
                'offline_uuid' => Uuid::uuid4()->toString(),
                'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
            ];
        }

        $this->actingAs($this->cashier)->postJson('/api/offline/sync', ['invoices' => $invoices])
            ->assertStatus(422);
    }

    #[Test]
    public function unauthenticated_sync_returns_401(): void
    {
        $this->postJson('/api/offline/sync', [
            'invoices' => [
                [
                    'offline_uuid' => Uuid::uuid4()->toString(),
                    'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                    'payment_method' => 'cash',
                ],
            ],
        ])->assertStatus(401);
    }

    // ── Optional fields ───────────────────────────────────────────────────────

    #[Test]
    public function sync_with_discount_succeeds(): void
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/offline/sync', [
            'invoices' => [
                [
                    'offline_uuid' => Uuid::uuid4()->toString(),
                    'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
                    'payment_method' => 'cash',
                    'discount' => 10.00,
                    'cash_received' => 90.00,
                    'notes' => 'عميل مميز',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('synced'));
    }

    #[Test]
    public function sync_response_includes_results_per_invoice(): void
    {
        $uuid = Uuid::uuid4()->toString();

        $response = $this->actingAs($this->cashier)->postJson('/api/offline/sync', [
            'invoices' => [
                [
                    'offline_uuid' => $uuid,
                    'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                    'payment_method' => 'cash',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('results'));
    }
}
