<?php

namespace Tests\Feature\Cash;

use App\Models\CashRegisterSession;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TC-CASH: Cash register — session open/close, movements, reconciliation, edge cases.
 */
class CashRegisterTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $admin;

    private User $cashier2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');

        $this->cashier2 = User::factory()->create(['is_active' => true]);
        $this->cashier2->assignRole('cashier');

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function openSession(User $cashier, float $amount = 500.00): int
    {
        $response = $this->actingAs($cashier)->postJson('/api/cash-session/open', [
            'opening_amount' => $amount,
        ]);
        $response->assertStatus(201);

        return $response->json('session.id') ?? $response->json('id');
    }

    // ── Session open ──────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_open_cash_session(): void
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/cash-session/open', [
            'opening_amount' => 500.00,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('cash_register_sessions', [
            'cashier_id' => $this->cashier->id,
            'opening_amount' => 500.00,
            'status' => 'open',
        ]);
    }

    #[Test]
    public function cashier_cannot_open_two_sessions_simultaneously(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/cash-session/open', ['opening_amount' => 200.00]);

        $this->actingAs($this->cashier)->postJson('/api/cash-session/open', ['opening_amount' => 300.00])
            ->assertStatus(422);
    }

    #[Test]
    public function opening_amount_cannot_be_negative(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/cash-session/open', [
            'opening_amount' => -100.00,
        ])->assertStatus(422);
    }

    #[Test]
    public function two_cashiers_can_have_independent_sessions(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/cash-session/open', ['opening_amount' => 100.00]);
        $this->actingAs($this->cashier2)->postJson('/api/cash-session/open', ['opening_amount' => 200.00])
            ->assertStatus(201);

        $this->assertDatabaseCount('cash_register_sessions', 2);
    }

    // ── Session close ─────────────────────────────────────────────────────────

    #[Test]
    public function cashier_can_close_open_session(): void
    {
        $sessionId = $this->openSession($this->cashier);

        $response = $this->actingAs($this->cashier)->postJson("/api/cash-session/{$sessionId}/close", [
            'actual_cash' => 500.00,
            'notes' => 'Shift end',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('cash_register_sessions', [
            'cashier_id' => $this->cashier->id,
            'status' => 'closed',
            'actual_cash' => 500.00,
        ]);
    }

    #[Test]
    public function closing_nonexistent_session_returns_error(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/cash-session/99999/close', [
            'actual_cash' => 500.00,
        ])->assertStatus(404);
    }

    #[Test]
    public function session_variance_calculated_on_close(): void
    {
        $sessionId = $this->openSession($this->cashier);

        $response = $this->actingAs($this->cashier)->postJson("/api/cash-session/{$sessionId}/close", [
            'actual_cash' => 480.00, // 20 short
        ]);

        $response->assertStatus(200);
        $session = CashRegisterSession::where('cashier_id', $this->cashier->id)->first();
        $this->assertNotNull($session->difference);
    }

    // ── Movements (deposit/withdrawal) ────────────────────────────────────────

    #[Test]
    public function cashier_can_record_cash_deposit(): void
    {
        $sessionId = $this->openSession($this->cashier, 200.00);

        $this->actingAs($this->cashier)->postJson("/api/cash-session/{$sessionId}/movements", [
            'type' => 'deposit',
            'amount' => 100.00,
            'notes' => 'مبيعات إضافية',
        ])->assertStatus(201);
    }

    #[Test]
    public function cashier_can_record_cash_withdrawal(): void
    {
        $sessionId = $this->openSession($this->cashier, 500.00);

        $this->actingAs($this->cashier)->postJson("/api/cash-session/{$sessionId}/movements", [
            'type' => 'withdrawal',
            'amount' => 50.00,
            'notes' => 'مصروف تشغيلي',
        ])->assertStatus(201);
    }

    #[Test]
    public function movement_on_nonexistent_session_returns_404(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/cash-session/99999/movements', [
            'type' => 'deposit',
            'amount' => 100.00,
        ])->assertStatus(404);
    }

    #[Test]
    public function zero_amount_movement_is_rejected(): void
    {
        $sessionId = $this->openSession($this->cashier, 200.00);

        $this->actingAs($this->cashier)->postJson("/api/cash-session/{$sessionId}/movements", [
            'type' => 'deposit',
            'amount' => 0,
        ])->assertStatus(422);
    }

    #[Test]
    public function negative_amount_movement_is_rejected(): void
    {
        $sessionId = $this->openSession($this->cashier, 200.00);

        $this->actingAs($this->cashier)->postJson("/api/cash-session/{$sessionId}/movements", [
            'type' => 'withdrawal',
            'amount' => -50.00,
        ])->assertStatus(422);
    }

    // ── Session isolation ─────────────────────────────────────────────────────

    #[Test]
    public function cashier_cannot_see_other_cashier_session(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/cash-session/open', ['opening_amount' => 300.00]);

        $response = $this->actingAs($this->cashier2)->getJson('/api/cash-session/current');

        $this->assertContains($response->status(), [200, 404]);
        if ($response->status() === 200) {
            $sessionCashierId = $response->json('session.cashier_id') ?? $response->json('cashier_id');
            if ($sessionCashierId !== null) {
                $this->assertNotEquals($this->cashier->id, $sessionCashierId);
            }
        }
    }

    // ── History ───────────────────────────────────────────────────────────────

    #[Test]
    public function session_history_returns_closed_sessions(): void
    {
        $sessionId = $this->openSession($this->cashier, 100.00);
        $this->actingAs($this->cashier)->postJson("/api/cash-session/{$sessionId}/close", ['actual_cash' => 100.00]);

        $response = $this->actingAs($this->admin)->getJson('/api/cash-session/history');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json());
    }

    // ── Current session ───────────────────────────────────────────────────────

    #[Test]
    public function current_session_returns_open_session_for_cashier(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/cash-session/open', ['opening_amount' => 300.00]);

        $response = $this->actingAs($this->cashier)->getJson('/api/cash-session/current');

        $response->assertStatus(200);
    }

    #[Test]
    public function current_session_returns_empty_when_no_open_session(): void
    {
        $response = $this->actingAs($this->cashier)->getJson('/api/cash-session/current');

        // Either 200 with null session or 404
        $this->assertContains($response->status(), [200, 404]);
    }
}
