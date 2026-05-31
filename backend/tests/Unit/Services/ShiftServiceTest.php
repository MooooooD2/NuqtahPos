<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\EmployeeShift;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 2 — Shift Service Unit Tests
 */
class ShiftServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShiftService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ShiftService::class);
    }

    #[Test]
    public function it_can_clock_in_a_user(): void
    {
        $user = User::factory()->create();

        $shift = $this->service->clockIn($user);

        $this->assertInstanceOf(EmployeeShift::class, $shift);
        $this->assertEquals('active', $shift->status);
        $this->assertNotNull($shift->clock_in_at);
    }

    #[Test]
    public function it_prevents_double_clock_in(): void
    {
        $user = User::factory()->create();
        $this->service->clockIn($user);

        $this->expectException(RuntimeException::class);
        $this->service->clockIn($user);
    }

    #[Test]
    public function it_can_clock_out_an_active_shift(): void
    {
        $user = User::factory()->create();
        $this->service->clockIn($user);

        $shift = $this->service->clockOut($user, ['cash_collected' => 500]);

        $this->assertEquals('completed', $shift->status);
        $this->assertNotNull($shift->clock_out_at);
        $this->assertNotNull($shift->hours_worked);
    }

    #[Test]
    public function it_returns_null_for_user_with_no_active_shift(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->service->activeShift($user));
    }
}
