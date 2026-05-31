<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    /** @test */
    public function admin_can_create_supplier()
    {
        $this->actingAs($this->admin)
            ->postJson('/api/suppliers', [
                'name' => 'مورد اختبار',
                'phone' => '0512345678',
                'email' => 'supplier@test.com',
            ])->assertStatus(201);

        $this->assertDatabaseHas('suppliers', ['name' => 'مورد اختبار']);
    }

    /** @test */
    public function cashier_cannot_create_supplier()
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/suppliers', ['name' => 'مورد اختبار'])
            ->assertStatus(403);
    }

    /** @test */
    public function duplicate_phone_is_rejected()
    {
        Supplier::factory()->create(['phone' => '0512345678']);

        $this->actingAs($this->admin)
            ->postJson('/api/suppliers', ['name' => 'مورد آخر', 'phone' => '0512345678'])
            ->assertStatus(422);
    }

    /** @test */
    public function invalid_email_is_rejected()
    {
        $this->actingAs($this->admin)
            ->postJson('/api/suppliers', ['name' => 'مورد', 'email' => 'not-an-email'])
            ->assertStatus(422);
    }

    /** @test */
    public function response_does_not_leak_deleted_at()
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/suppliers')
            ->assertStatus(200);

        $data = $response->json('suppliers.data.0');
        $this->assertArrayNotHasKey('deleted_at', $data ?? []);
    }
}
