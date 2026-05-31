<?php

namespace Tests\Unit\Services;

use App\Services\SequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SequenceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The migration already seeds these rows — just reset values to 0 before each test.
        DB::table('sequences')->whereIn('name', ['invoice', 'purchase', 'return'])->update(['value' => 0]);
    }

    public function test_generates_invoice_number_with_correct_format(): void
    {
        $number = SequenceService::next('invoice');
        $date = now()->format('Ymd');

        $this->assertStringStartsWith("INV-{$date}-", $number);
        $this->assertStringEndsWith('000001', $number);
    }

    public function test_increments_correctly_on_each_call(): void
    {
        $first = SequenceService::next('invoice');
        $second = SequenceService::next('invoice');
        $third = SequenceService::next('invoice');

        $this->assertStringEndsWith('000001', $first);
        $this->assertStringEndsWith('000002', $second);
        $this->assertStringEndsWith('000003', $third);
    }

    public function test_different_sequences_are_independent(): void
    {
        $inv = SequenceService::next('invoice');
        $po = SequenceService::next('purchase');
        $ret = SequenceService::next('return');

        $this->assertStringStartsWith('INV-', $inv);
        $this->assertStringStartsWith('PO-', $po);
        $this->assertStringStartsWith('RET-', $ret);

        // All start at 1 independently
        $this->assertStringEndsWith('000001', $inv);
        $this->assertStringEndsWith('000001', $po);
        $this->assertStringEndsWith('000001', $ret);
    }

    public function test_custom_prefix_overrides_db_prefix(): void
    {
        $number = SequenceService::next('invoice', 'CUSTOM');
        $this->assertStringStartsWith('CUSTOM-', $number);
    }
}
