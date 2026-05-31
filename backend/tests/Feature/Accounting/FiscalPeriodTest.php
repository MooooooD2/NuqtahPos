<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fiscal Period — create, list, current, close.
 */
class FiscalPeriodTest extends TestCase
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

    /** Helper: create FiscalPeriod directly (no factory). */
    private function makePeriod(array $overrides = []): FiscalPeriod
    {
        return FiscalPeriod::create(array_merge([
            'name' => 'Test Period ' . uniqid(),
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ], $overrides));
    }

    // ── List ─────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_fiscal_periods(): void
    {
        // index() returns a raw JSON array (collection)
        $this->actingAs($this->admin)
            ->getJson('/api/fiscal-periods')
            ->assertOk()
            ->assertJsonIsArray();
    }

    #[Test]
    public function cashier_cannot_list_fiscal_periods(): void
    {
        $this->actingAs($this->cashier)
            ->getJson('/api/fiscal-periods')
            ->assertForbidden();
    }

    // ── Current ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_get_current_fiscal_period(): void
    {
        // FiscalPeriod model uses start_date / end_date (not starts_at / ends_at)
        $this->makePeriod([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/fiscal-periods/current')
            ->assertOk();
    }

    // ── Create ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_create_fiscal_period(): void
    {
        // store() returns 201 + plain model (no success wrapper)
        $res = $this->actingAs($this->admin)
            ->postJson('/api/fiscal-periods', [
                'name' => 'Q1 2027',
                'start_date' => '2027-01-01',
                'end_date' => '2027-03-31',
            ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('fiscal_periods', ['name' => 'Q1 2027']);
    }

    #[Test]
    public function fiscal_period_requires_name_and_dates(): void
    {
        // Validated fields: name, start_date, end_date
        $this->actingAs($this->admin)
            ->postJson('/api/fiscal-periods', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'start_date', 'end_date']);
    }

    #[Test]
    public function end_date_must_be_after_start_date(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/fiscal-periods', [
                'name' => 'Bad Period',
                'start_date' => '2027-06-01',
                'end_date' => '2027-05-01',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    #[Test]
    public function cashier_cannot_create_fiscal_period(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/fiscal-periods', [
                'name' => 'Hack Period',
                'start_date' => '2027-01-01',
                'end_date' => '2027-03-31',
            ])->assertForbidden();
    }

    // ── Close ─────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_close_open_fiscal_period(): void
    {
        $startDate = now()->subMonths(2)->startOfMonth()->toDateString();
        $endDate = now()->subMonth()->endOfMonth()->toDateString();

        $period = $this->makePeriod([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        // close() requires retained_earnings_account_id (equity type)
        $retainedEarnings = Account::create([
            'account_code' => 'RE001',
            'account_name' => 'Retained Earnings',
            'account_type' => 'equity',
        ]);

        // PeriodClosingService::buildClosingLines() only considers accounts with parent_id.
        // Create a parent revenue account and a child revenue account.
        $revParent = Account::create([
            'account_code' => 'REV000',
            'account_name' => 'Revenue (Parent)',
            'account_type' => 'revenue',
        ]);
        $revenueAcc = Account::create([
            'account_code' => 'REV001',
            'account_name' => 'Sales Revenue',
            'account_type' => 'revenue',
            'parent_id' => $revParent->id,
        ]);
        $cashAcc = Account::create([
            'account_code' => 'CASH001',
            'account_name' => 'Cash',
            'account_type' => 'asset',
        ]);

        // Create a journal entry within the period so buildClosingLines finds revenue activity
        $entryDate = now()->subMonths(2)->startOfMonth()->addDays(5)->toDateString();
        $je = JournalEntry::create([
            'entry_number' => 'JE-CLOSE-TEST',
            'entry_date' => $entryDate,
            'description' => 'Test revenue for closing',
            'created_by' => $this->admin->id,
            'is_posted' => false,
        ]);
        JournalEntryLine::create(['entry_id' => $je->id, 'account_id' => $cashAcc->id,    'debit' => 1000, 'credit' => 0]);
        JournalEntryLine::create(['entry_id' => $je->id, 'account_id' => $revenueAcc->id, 'debit' => 0,    'credit' => 1000]);

        $this->actingAs($this->admin)
            ->postJson("/api/fiscal-periods/{$period->id}/close", [
                'retained_earnings_account_id' => $retainedEarnings->id,
            ])->assertOk();

        $this->assertEquals('closed', $period->fresh()->status);
    }

    // ── Preview Close ─────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_preview_fiscal_period_close(): void
    {
        $period = $this->makePeriod([
            'start_date' => '2027-01-01',
            'end_date' => '2027-03-31',
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/fiscal-periods/{$period->id}/preview-close")
            ->assertOk();
    }
}
