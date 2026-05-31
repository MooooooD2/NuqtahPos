<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CRM Activities — CRUD, follow-ups, stats.
 */
class CrmTest extends TestCase
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

        $this->customer = Customer::create([
            'code' => 'CUST001',
            'name' => 'Test Customer',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    private function makeActivity(array $overrides = []): CrmActivity
    {
        return CrmActivity::create(array_merge([
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'type' => 'call',
            'outcome' => 'neutral',
        ], $overrides));
    }

    // ── List Activities ───────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_customer_activities(): void
    {
        $this->makeActivity();
        $this->makeActivity();
        $this->makeActivity();

        $this->actingAs($this->admin)
            ->getJson("/api/crm/customers/{$this->customer->id}/activities")
            ->assertOk()
            ->assertJsonCount(3);
    }

    #[Test]
    public function cashier_cannot_access_crm(): void
    {
        $this->actingAs($this->cashier)
            ->getJson("/api/crm/customers/{$this->customer->id}/activities")
            ->assertForbidden();
    }

    #[Test]
    public function guest_cannot_access_crm(): void
    {
        $this->getJson("/api/crm/customers/{$this->customer->id}/activities")
            ->assertUnauthorized();
    }

    // ── Create Activity ───────────────────────────────────────────────────────

    #[Test]
    public function admin_can_create_crm_activity(): void
    {
        $res = $this->actingAs($this->admin)
            ->postJson('/api/crm/activities', [
                'customer_id' => $this->customer->id,
                'type' => 'call',
                'subject' => 'Follow up on order',
                'notes' => 'Customer satisfied',
                'outcome' => 'positive',
            ]);

        $res->assertStatus(201)
            ->assertJsonStructure(['activity']);

        $this->assertDatabaseHas('crm_activities', [
            'customer_id' => $this->customer->id,
            'type' => 'call',
        ]);
    }

    #[Test]
    public function activity_requires_valid_type(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/crm/activities', [
                'customer_id' => $this->customer->id,
                'type' => 'smoke_signal',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function activity_customer_must_exist(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/crm/activities', [
                'customer_id' => 99999,
                'type' => 'call',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    // ── Update Activity ───────────────────────────────────────────────────────

    #[Test]
    public function admin_can_update_crm_activity(): void
    {
        $activity = $this->makeActivity();

        $this->actingAs($this->admin)
            ->putJson("/api/crm/activities/{$activity->id}", [
                'notes' => 'Updated notes',
                'outcome' => 'positive',
            ])->assertOk()
            ->assertJsonPath('activity.outcome', 'positive');
    }

    // ── Delete Activity ───────────────────────────────────────────────────────

    #[Test]
    public function admin_can_delete_crm_activity(): void
    {
        $activity = $this->makeActivity();

        $this->actingAs($this->admin)
            ->deleteJson("/api/crm/activities/{$activity->id}")
            ->assertOk();

        $this->assertSoftDeleted('crm_activities', ['id' => $activity->id]);
    }

    // ── Follow-ups ────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_pending_follow_ups(): void
    {
        // Pending: has scheduled_at, no completed_at, outcome = 'pending', type = follow_up
        $this->makeActivity([
            'type' => 'follow_up',
            'outcome' => 'pending',
            'scheduled_at' => now()->addDay(),
            'completed_at' => null,
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/crm/follow-ups')
            ->assertOk()
            ->assertJsonCount(1);
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_crm_stats(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/crm/stats')
            ->assertOk()
            ->assertJsonStructure([
                'total_customers', 'new_this_month', 'pending_followups',
                'top_customers',
            ]);
    }
}
