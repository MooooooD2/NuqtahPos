<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Settings — read all, read group, update.
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        if (class_exists(SettingSeeder::class)) {
            $this->seed(SettingSeeder::class);
        } else {
            // Seed a handful of known keys manually
            $defaults = [
                ['key' => 'store_name',      'value' => 'Test Store',    'group' => 'general'],
                ['key' => 'tax_enabled',     'value' => '0',             'group' => 'tax'],
                ['key' => 'tax_rate',        'value' => '14',            'group' => 'tax'],
                ['key' => 'currency',        'value' => 'EGP',           'group' => 'general'],
                ['key' => 'invoice_prefix',  'value' => 'INV',           'group' => 'invoice'],
            ];
            foreach ($defaults as $row) {
                Setting::firstOrCreate(['key' => $row['key']], $row);
            }
        }

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

    // ── Read ─────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_read_all_settings(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/settings')
            ->assertOk()
            ->assertJsonStructure(['settings']);
    }

    #[Test]
    public function cashier_can_read_all_settings(): void
    {
        // Settings are readable by any authenticated user (POS needs them)
        $this->actingAs($this->cashier)
            ->getJson('/api/settings')
            ->assertOk();
    }

    #[Test]
    public function guest_cannot_read_settings(): void
    {
        $this->getJson('/api/settings')->assertUnauthorized();
    }

    // ── Group ─────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_read_settings_by_group(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/settings/group/general')
            ->assertOk()
            ->assertJsonStructure(['settings']);
    }

    #[Test]
    public function invalid_group_returns_bad_request(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/settings/group/invalid_group_xyz')
            ->assertStatus(400);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_update_settings(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/settings', [
                'settings' => [
                    ['key' => 'store_name', 'value' => 'My Updated Store'],
                ],
            ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals('My Updated Store', Setting::get('store_name'));
    }

    #[Test]
    public function cashier_cannot_update_settings(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/settings', [
                'settings' => [
                    ['key' => 'store_name', 'value' => 'Hacked'],
                ],
            ])->assertForbidden();
    }

    #[Test]
    public function update_requires_settings_array(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/settings', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings']);
    }

    #[Test]
    public function update_rejects_non_existent_key(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/settings', [
                'settings' => [
                    ['key' => 'totally_fake_key_xyz', 'value' => 'bad'],
                ],
            ])->assertStatus(422);
    }

    #[Test]
    public function tax_group_settings_are_readable(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/settings/group/tax')
            ->assertOk()
            ->assertJsonStructure(['settings']);
    }
}
