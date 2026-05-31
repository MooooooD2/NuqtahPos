<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Accounting — accounts CRUD, journal entries (store / post / reverse).
 */
class JournalEntryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;
    private Account $debitAcc;
    private Account $creditAcc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');

        // Account model uses account_code / account_name / account_type (no factory)
        $this->debitAcc = Account::create([
            'account_code' => 'DEBIT001',
            'account_name' => 'Test Debit Account',
            'account_type' => 'asset',
        ]);
        $this->creditAcc = Account::create([
            'account_code' => 'CREDIT001',
            'account_name' => 'Test Credit Account',
            'account_type' => 'equity',
        ]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    // ── Accounts ──────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_accounts(): void
    {
        // allAccounts() → {success: true, accounts: [...]}
        $this->actingAs($this->admin)
            ->getJson('/api/accounts')
            ->assertOk()
            ->assertJsonStructure(['success', 'accounts']);
    }

    #[Test]
    public function cashier_cannot_list_accounts(): void
    {
        $this->actingAs($this->cashier)
            ->getJson('/api/accounts')
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_create_account(): void
    {
        // StoreAccountRequest validates account_code / account_name / account_type
        $res = $this->actingAs($this->admin)
            ->postJson('/api/accounts', [
                'account_code' => 'PC001',
                'account_name' => 'Petty Cash',
                'account_type' => 'asset',
            ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('accounts', ['account_code' => 'PC001']);
    }

    #[Test]
    public function admin_can_update_account(): void
    {
        // updateAccount validates account_name (not 'name')
        $this->actingAs($this->admin)
            ->putJson("/api/accounts/{$this->debitAcc->id}", ['account_name' => 'Updated Account'])
            ->assertOk();
    }

    // ── Journal Entries ───────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_journal_entries(): void
    {
        // allJournalEntries() → {success: true, entries: {...}}
        $this->actingAs($this->admin)
            ->getJson('/api/journal-entries')
            ->assertOk()
            ->assertJsonStructure(['success', 'entries']);
    }

    #[Test]
    public function admin_can_create_balanced_journal_entry(): void
    {
        // storeJournalEntry() → {success: true, entry: {...}}
        $res = $this->actingAs($this->admin)
            ->postJson('/api/journal-entries', [
                'entry_date' => now()->toDateString(),
                'description' => 'Opening entry',
                'lines' => [
                    ['account_id' => $this->debitAcc->id,  'debit' => 1000, 'credit' => 0],
                    ['account_id' => $this->creditAcc->id, 'debit' => 0,    'credit' => 1000],
                ],
            ]);

        $res->assertStatus(201)
            ->assertJsonStructure(['success', 'entry']);
    }

    #[Test]
    public function unbalanced_entry_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/journal-entries', [
                'entry_date' => now()->toDateString(),
                'lines' => [
                    ['account_id' => $this->debitAcc->id,  'debit' => 500, 'credit' => 0],
                    ['account_id' => $this->creditAcc->id, 'debit' => 0,   'credit' => 300],
                ],
            ])->assertStatus(422);
    }

    #[Test]
    public function future_entry_date_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/journal-entries', [
                'entry_date' => now()->addDays(5)->toDateString(),
                'lines' => [
                    ['account_id' => $this->debitAcc->id,  'debit' => 100, 'credit' => 0],
                    ['account_id' => $this->creditAcc->id, 'debit' => 0,   'credit' => 100],
                ],
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['entry_date']);
    }

    #[Test]
    public function entry_requires_at_least_two_lines(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/journal-entries', [
                'entry_date' => now()->toDateString(),
                'lines' => [
                    ['account_id' => $this->debitAcc->id, 'debit' => 100, 'credit' => 0],
                ],
            ])->assertStatus(422);
    }

    #[Test]
    public function cashier_cannot_create_journal_entry(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/journal-entries', [
                'entry_date' => now()->toDateString(),
                'lines' => [
                    ['account_id' => $this->debitAcc->id,  'debit' => 100, 'credit' => 0],
                    ['account_id' => $this->creditAcc->id, 'debit' => 0,   'credit' => 100],
                ],
            ])->assertForbidden();
    }

    // ── Post & Reverse ────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_post_draft_entry(): void
    {
        // description is required by AccountingService (not nullable in practice)
        $res = $this->actingAs($this->admin)
            ->postJson('/api/journal-entries', [
                'entry_date' => now()->toDateString(),
                'description' => 'Post test entry',
                'lines' => [
                    ['account_id' => $this->debitAcc->id,  'debit' => 200, 'credit' => 0],
                    ['account_id' => $this->creditAcc->id, 'debit' => 0,   'credit' => 200],
                ],
            ]);

        // storeJournalEntry() → {success: true, entry: {...}}
        $entryId = $res->assertStatus(201)->json('entry.id');

        $this->actingAs($this->admin)
            ->postJson("/api/journal-entries/{$entryId}/post")
            ->assertOk();
    }

    #[Test]
    public function admin_can_reverse_posted_entry(): void
    {
        // Create — description required by AccountingService
        $res = $this->actingAs($this->admin)
            ->postJson('/api/journal-entries', [
                'entry_date' => now()->toDateString(),
                'description' => 'Reverse test entry',
                'lines' => [
                    ['account_id' => $this->debitAcc->id,  'debit' => 300, 'credit' => 0],
                    ['account_id' => $this->creditAcc->id, 'debit' => 0,   'credit' => 300],
                ],
            ]);

        $entryId = $res->assertStatus(201)->json('entry.id');

        // Post
        $this->actingAs($this->admin)->postJson("/api/journal-entries/{$entryId}/post");

        // Reverse — controller returns 201 (new reversal entry created)
        $this->actingAs($this->admin)
            ->postJson("/api/journal-entries/{$entryId}/reverse", [
                'description' => 'Reversing test entry',
            ])->assertStatus(201);
    }

    // ── Audit Logs ───────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_view_audit_logs(): void
    {
        // auditLogs() → {success: true, logs: {...}}
        $this->actingAs($this->admin)
            ->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonStructure(['success', 'logs']);
    }
}
