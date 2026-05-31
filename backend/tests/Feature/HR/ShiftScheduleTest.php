<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for the Shift Schedule API:
 *  GET  /api/hr/shifts/schedule     – returns weekly grid
 *  POST /api/hr/shifts/schedule     – assigns an employee to a shift
 *  GET  /api/hr/shifts/templates    – lists active shift templates
 */
class ShiftScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $hrManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hrManager = User::factory()->create(['is_active' => true]);

        foreach (['manage_hr', 'view_hr', 'view_shifts', 'manage_shifts'] as $perm) {
            $p = \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => $perm, 'guard_name' => 'web'],
            );
            $this->hrManager->givePermissionTo($p);
        }
    }

    // ── Schedule retrieval ────────────────────────────────────────────────

    /** @test */
    public function schedule_endpoint_returns_week_structure(): void
    {
        $weekStart = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->toDateString();

        $response = $this->actingAs($this->hrManager)
            ->getJson("/api/hr/shifts/schedule?week_start={$weekStart}");

        $response->assertOk()
            ->assertJsonStructure([
                'week_start',
                'week_end',
                'days',
                'shifts',
            ]);

        $this->assertCount(7, $response->json('days'), 'Schedule should span 7 days');
        $this->assertEquals($weekStart, $response->json('week_start'));
    }

    /** @test */
    public function schedule_returns_empty_shifts_when_none_assigned(): void
    {
        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/hr/shifts/schedule');

        $response->assertOk();
        $this->assertEmpty($response->json('shifts'));
    }

    /** @test */
    public function schedule_returns_assigned_shifts_for_week(): void
    {
        $employee = User::factory()->create(['is_active' => true]);
        $weekStart = now()->startOfWeek(\Carbon\Carbon::SATURDAY)->toDateString();

        DB::table('employee_shifts')->insert([
            'user_id' => $employee->id,
            'shift_date' => $weekStart,
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->hrManager)
            ->getJson("/api/hr/shifts/schedule?week_start={$weekStart}");

        $response->assertOk();
        $this->assertCount(1, $response->json('shifts'));
        $this->assertEquals($employee->id, $response->json('shifts.0.user_id'));
    }

    // ── Assign shift ──────────────────────────────────────────────────────

    /** @test */
    public function can_assign_shift_to_employee(): void
    {
        $employee = User::factory()->create(['is_active' => true]);
        $date = now()->addDay()->toDateString();

        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/hr/shifts/schedule', [
                'user_id' => $employee->id,
                'shift_date' => $date,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('employee_shifts', [
            'user_id' => $employee->id,
            'shift_date' => $date,
            'status' => 'scheduled',
        ]);
    }

    /** @test */
    public function cannot_assign_duplicate_shift_same_day(): void
    {
        $employee = User::factory()->create(['is_active' => true]);
        $date = now()->addDay()->toDateString();

        DB::table('employee_shifts')->insert([
            'user_id' => $employee->id,
            'shift_date' => $date,
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/hr/shifts/schedule', [
                'user_id' => $employee->id,
                'shift_date' => $date,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function assign_shift_requires_user_id_and_date(): void
    {
        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/hr/shifts/schedule', []);

        $response->assertStatus(422); // validation failure
    }

    // ── Templates ─────────────────────────────────────────────────────────

    /** @test */
    public function templates_endpoint_returns_active_templates(): void
    {
        // Insert an active and an inactive template
        DB::table('shift_templates')->insert([
            ['name' => 'Morning',  'start_time' => '08:00:00', 'end_time' => '16:00:00', 'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Archived', 'start_time' => '22:00:00', 'end_time' => '06:00:00', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/hr/shifts/templates');

        $response->assertOk();
        $names = collect($response->json('templates'))->pluck('name');

        $this->assertContains('Morning', $names->toArray());
        $this->assertNotContains('Archived', $names->toArray());
    }

    // ── Branch filter ─────────────────────────────────────────────────────

    /** @test */
    public function schedule_can_be_filtered_by_branch(): void
    {
        $branchA = DB::table('branches')->insertGetId(['name' => 'Branch A', 'code' => 'BRA', 'created_at' => now(), 'updated_at' => now()]);
        $branchB = DB::table('branches')->insertGetId(['name' => 'Branch B', 'code' => 'BRB', 'created_at' => now(), 'updated_at' => now()]);

        $empA = User::factory()->create(['is_active' => true, 'branch_id' => $branchA]);
        $empB = User::factory()->create(['is_active' => true, 'branch_id' => $branchB]);

        $date = now()->toDateString();
        DB::table('employee_shifts')->insert([
            ['user_id' => $empA->id, 'branch_id' => $branchA, 'shift_date' => $date, 'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $empB->id, 'branch_id' => $branchB, 'shift_date' => $date, 'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($this->hrManager)
            ->getJson("/api/hr/shifts/schedule?branch_id={$branchA}");

        $response->assertOk();
        $userIds = collect($response->json('shifts'))->pluck('user_id');

        $this->assertContains($empA->id, $userIds->toArray());
        $this->assertNotContains($empB->id, $userIds->toArray());
    }
}
