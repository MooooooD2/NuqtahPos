<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AccountingService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountingService $service;

    private Account $debitAccount;

    private Account $creditAccount;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('sequences')->where('name', 'journal')->update(['value' => 0]);

        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);

        $this->service = app(AccountingService::class);

        $this->debitAccount = Account::create([
            'account_code' => '1001',
            'account_name' => 'Cash',
            'account_type' => 'asset',
            'balance' => 0,
        ]);

        $this->creditAccount = Account::create([
            'account_code' => '4001',
            'account_name' => 'Revenue',
            'account_type' => 'revenue',
            'balance' => 0,
        ]);
    }

    private function validEntryData(float $amount = 1000.00): array
    {
        return [
            'entry_date' => today()->toDateString(),
            'description' => 'Test journal entry',
            'lines' => [
                ['account_id' => $this->debitAccount->id,  'debit' => $amount, 'credit' => 0],
                ['account_id' => $this->creditAccount->id, 'debit' => 0, 'credit' => $amount],
            ],
        ];
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_creates_balanced_journal_entry(): void
    {
        $entry = $this->service->createJournalEntry($this->validEntryData());

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertCount(2, $entry->lines);
        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id]);
    }

    public function test_journal_number_has_expected_format(): void
    {
        $entry = $this->service->createJournalEntry($this->validEntryData());

        // Sequence produces JE-YYYYMMDD-NNNNNN
        $this->assertMatchesRegularExpression('/^JE-\d{8}-\d{6}$/', $entry->entry_number);
    }

    public function test_unbalanced_entry_throws_exception(): void
    {
        $data = [
            'entry_date' => today()->toDateString(),
            'description' => 'Unbalanced entry',
            'lines' => [
                ['account_id' => $this->debitAccount->id,  'debit' => 1000, 'credit' => 0],
                ['account_id' => $this->creditAccount->id, 'debit' => 0,    'credit' => 800], // mismatch
            ],
        ];

        $this->expectException(Exception::class);

        $this->service->createJournalEntry($data);
    }

    public function test_unbalanced_entry_does_not_persist(): void
    {
        $data = [
            'entry_date' => today()->toDateString(),
            'description' => 'Unbalanced entry',
            'lines' => [
                ['account_id' => $this->debitAccount->id,  'debit' => 500, 'credit' => 0],
                ['account_id' => $this->creditAccount->id, 'debit' => 0,   'credit' => 999],
            ],
        ];

        try {
            $this->service->createJournalEntry($data);
        } catch (Exception) {
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_entry_lines', 0);
    }

    public function test_account_balances_update_after_entry(): void
    {
        $this->service->createJournalEntry($this->validEntryData(500.00));

        $debit = $this->debitAccount->fresh();
        $credit = $this->creditAccount->fresh();

        // Asset debited → balance increases; Revenue credited → balance increases
        $this->assertGreaterThan(0, $debit->balance + $credit->balance);
    }

    // ── Fiscal period locking ─────────────────────────────────────────────────

    public function test_entry_in_closed_period_is_rejected(): void
    {
        FiscalPeriod::create([
            'name' => 'January 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => auth()->id(),
        ]);

        $data = $this->validEntryData();
        $data['entry_date'] = '2026-01-15'; // inside the closed period

        $this->expectException(Exception::class);

        $this->service->createJournalEntry($data);
    }

    public function test_entry_in_open_period_is_allowed(): void
    {
        FiscalPeriod::create([
            'name' => 'May 2026',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'status' => 'open',
        ]);

        $data = $this->validEntryData();
        $data['entry_date'] = '2026-05-10';

        $entry = $this->service->createJournalEntry($data);

        $this->assertInstanceOf(JournalEntry::class, $entry);
    }

    // ── Post (lock) ───────────────────────────────────────────────────────────

    public function test_posting_marks_entry_as_posted(): void
    {
        $entry = $this->service->createJournalEntry($this->validEntryData());

        $this->assertFalse((bool) $entry->is_posted);

        $posted = $this->service->postEntry($entry);

        $this->assertTrue((bool) $posted->is_posted);
        $this->assertNotNull($posted->posted_at);
    }

    public function test_posting_already_posted_entry_throws(): void
    {
        $entry = $this->service->createJournalEntry($this->validEntryData());
        $this->service->postEntry($entry);

        $this->expectException(Exception::class);

        $this->service->postEntry($entry->fresh());
    }

    // ── Reverse ───────────────────────────────────────────────────────────────

    public function test_reversing_entry_creates_mirror_entry(): void
    {
        $original = $this->service->createJournalEntry($this->validEntryData(300.00));
        $this->service->postEntry($original);

        $reversal = $this->service->reverseEntry($original->fresh(), 'Correction');

        $this->assertInstanceOf(JournalEntry::class, $reversal);
        $this->assertCount(2, $reversal->lines);

        // Reversal lines should be mirror: original debit becomes credit and vice versa
        $origDebit = $original->lines->where('account_id', $this->debitAccount->id)->first();
        $reversalLine = $reversal->lines->where('account_id', $this->debitAccount->id)->first();

        $this->assertEquals((float) $origDebit->debit, (float) $reversalLine->credit);
        $this->assertEquals((float) $origDebit->credit, (float) $reversalLine->debit);
    }

    public function test_only_posted_entries_can_be_reversed(): void
    {
        $entry = $this->service->createJournalEntry($this->validEntryData());
        // Not posted — reversal should fail

        $this->expectException(Exception::class);

        $this->service->reverseEntry($entry, 'Should fail');
    }
}
