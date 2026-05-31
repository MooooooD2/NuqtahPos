<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifies the full attendance cycle:
 *  1. No record → absent / no entry
 *  2. Clock-in via ShiftService → attendance_records row appears with check_out = null
 *  3. Clock-out → check_out is set, hours_worked calculated, status correct
 *  4. The API returns has_checked_out / is_working_now flags correctly
 *  5. Manual check-in endpoint works
 *  6. Manual check-out endpoint works
 */
class AttendanceCycleTest extends TestCase
{
    use RefreshDatabase;

    private ShiftService $shiftService;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shiftService = app(ShiftService::class);

        // Manager with HR permissions (stubbed via role assignment)
        $this->manager = User::factory()->create(['is_active' => true]);
        // Grant manage_hr permission directly (Spatie)
        $perm = \Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'manage_hr', 'guard_name' => 'web'],
        );
        $this->manager->givePermissionTo($perm);
    }

    // ── 1. Clock-in creates an attendance record with check_out null ──────

    /** @test */
    public function clock_in_creates_attendance_record_with_null_checkout(): void
    {
        /** @var User $employee */
        $employee = User::factory()->create(['is_active' => true]);

        $this->actingAs($employee);
        $this->shiftService->clockIn($employee);

        $record = DB::table('attendance_records')
            ->where('user_id', $employee->id)
            ->whereDate('work_date', today())
            ->first();

        $this->assertNotNull($record, 'attendance_records row should be created on clock-in');
        $this->assertNotNull($record->check_in, 'check_in should be set');
        $this->assertNull($record->check_out, 'check_out should still be null after clock-in');
        $this->assertEquals('present', $record->status);
    }

    // ── 2. Clock-out sets check_out and calculates hours ──────────────────

    /** @test */
    public function clock_out_sets_checkout_and_calculates_hours(): void
    {
        /** @var User $employee */
        $employee = User::factory()->create(['is_active' => true]);

        $this->actingAs($employee);
        $this->shiftService->clockIn($employee);

        // Artificially push clock_in back by 8 hours so hours_worked > 0
        DB::table('employee_shifts')
            ->where('user_id', $employee->id)
            ->where('status', 'active')
            ->update(['clock_in_at' => now()->subHours(8)]);

        DB::table('attendance_records')
            ->where('user_id', $employee->id)
            ->whereDate('work_date', today())
            ->update(['check_in' => now()->subHours(8)]);

        $this->shiftService->clockOut($employee);

        $record = DB::table('attendance_records')
            ->where('user_id', $employee->id)
            ->whereDate('work_date', today())
            ->first();

        $this->assertNotNull($record->check_out, 'check_out should be set after clock-out');
        $this->assertGreaterThan(0, $record->hours_worked, 'hours_worked should be positive');
        $this->assertEquals('present', $record->status);
    }

    // ── 3. is_working_now / has_checked_out flags ─────────────────────────

    /** @test */
    public function api_returns_is_working_now_true_when_checked_in_only(): void
    {
        $employee = User::factory()->create(['is_active' => true]);

        // Insert a bare attendance record: check_in set, check_out null
        DB::table('attendance_records')->insert([
            'user_id' => $employee->id,
            'work_date' => today()->toDateString(),
            'check_in' => now()->subHour(),
            'check_out' => null,
            'status' => 'present',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/hr/attendance?date=' . today()->toDateString());

        $response->assertOk();

        $record = collect($response->json('records'))
            ->firstWhere('user_id', $employee->id);

        $this->assertNotNull($record);
        $this->assertTrue($record['is_working_now'], 'is_working_now should be true');
        $this->assertFalse($record['has_checked_out'], 'has_checked_out should be false');
    }

    /** @test */
    public function api_returns_has_checked_out_true_when_checkout_set(): void
    {
        $employee = User::factory()->create(['is_active' => true]);

        DB::table('attendance_records')->insert([
            'user_id' => $employee->id,
            'work_date' => today()->toDateString(),
            'check_in' => now()->subHours(8),
            'check_out' => now(),
            'hours_worked' => 8,
            'status' => 'present',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/hr/attendance?date=' . today()->toDateString());

        $response->assertOk();

        $record = collect($response->json('records'))
            ->firstWhere('user_id', $employee->id);

        $this->assertNotNull($record);
        $this->assertFalse($record['is_working_now'], 'is_working_now should be false after checkout');
        $this->assertTrue($record['has_checked_out'], 'has_checked_out should be true');
    }

    // ── 4. Virtual status filter: working_now ─────────────────────────────

    /** @test */
    public function working_now_filter_returns_only_checked_in_employees(): void
    {
        $working = User::factory()->create(['is_active' => true]);
        $finished = User::factory()->create(['is_active' => true]);
        $absent = User::factory()->create(['is_active' => true]);

        DB::table('attendance_records')->insert([
            ['user_id' => $working->id,  'work_date' => today()->toDateString(), 'check_in' => now()->subHour(),   'check_out' => null,  'hours_worked' => null, 'status' => 'present', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $finished->id, 'work_date' => today()->toDateString(), 'check_in' => now()->subHours(8), 'check_out' => now(), 'hours_worked' => 8,    'status' => 'present', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/hr/attendance?date=' . today()->toDateString() . '&status=working_now');

        $response->assertOk();

        $ids = collect($response->json('records'))->pluck('user_id')->toArray();

        $this->assertContains($working->id, $ids, 'working employee should appear');
        $this->assertNotContains($finished->id, $ids, 'finished employee should NOT appear');
        $this->assertNotContains($absent->id, $ids, 'absent employee should NOT appear');
    }

    /** @test */
    public function checked_out_filter_returns_only_completed_attendance(): void
    {
        $working = User::factory()->create(['is_active' => true]);
        $finished = User::factory()->create(['is_active' => true]);

        DB::table('attendance_records')->insert([
            ['user_id' => $working->id,  'work_date' => today()->toDateString(), 'check_in' => now()->subHour(),   'check_out' => null,  'hours_worked' => null, 'status' => 'present', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $finished->id, 'work_date' => today()->toDateString(), 'check_in' => now()->subHours(8), 'check_out' => now(), 'hours_worked' => 8,    'status' => 'present', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/hr/attendance?date=' . today()->toDateString() . '&status=checked_out');

        $response->assertOk();

        $ids = collect($response->json('records'))->pluck('user_id')->toArray();

        $this->assertContains($finished->id, $ids, 'finished employee should appear');
        $this->assertNotContains($working->id, $ids, 'working employee should NOT appear');
    }

    // ── 5. Manual attendance endpoints ────────────────────────────────────

    /** @test */
    public function manual_checkin_endpoint_creates_record(): void
    {
        $employee = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/hr/attendance/checkin', [
                'user_id' => $employee->id,
                'work_date' => today()->toDateString(),
                'check_in' => '09:00',
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $employee->id,
            'work_date' => today()->toDateString(),
        ]);

        $record = DB::table('attendance_records')
            ->where('user_id', $employee->id)
            ->whereDate('work_date', today())
            ->first();

        $this->assertNull($record->check_out, 'check_out should be null after manual check-in only');
    }

    /** @test */
    public function manual_checkout_endpoint_updates_record(): void
    {
        $employee = User::factory()->create(['is_active' => true]);

        // First create check-in
        DB::table('attendance_records')->insert([
            'user_id' => $employee->id,
            'work_date' => today()->toDateString(),
            'check_in' => today()->setTimeFromTimeString('09:00:00'),
            'check_out' => null,
            'status' => 'present',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/hr/attendance/checkout', [
                'user_id' => $employee->id,
                'work_date' => today()->toDateString(),
                'check_out' => '17:00',
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $record = DB::table('attendance_records')
            ->where('user_id', $employee->id)
            ->whereDate('work_date', today())
            ->first();

        $this->assertNotNull($record->check_out, 'check_out should be set after manual checkout');
        $this->assertGreaterThan(0, $record->hours_worked);
    }

    /** @test */
    public function manual_checkout_fails_when_no_checkin_record_exists(): void
    {
        $employee = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/hr/attendance/checkout', [
                'user_id' => $employee->id,
                'work_date' => today()->toDateString(),
                'check_out' => '17:00',
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }
}
