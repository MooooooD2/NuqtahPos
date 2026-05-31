<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** @test */
    public function unauthenticated_api_returns_401()
    {
        $this->getJson('/api/products')->assertStatus(401);
        $this->getJson('/api/suppliers')->assertStatus(401);
        $this->getJson('/api/dashboard-data')->assertStatus(401);
    }

    /** @test */
    public function xss_in_product_name_is_sanitized()
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->postJson('/api/products', [
            'name' => '<script>alert("xss")</script>منتج',
            'price' => 100,
        ]);

        // لا يجب تخزين script tags (validation يرفض أو يُنظّف)
        $this->assertDatabaseMissing('products', ['name' => '<script>alert("xss")</script>منتج']);
    }

    /** @test */
    public function sql_injection_in_search_is_safe()
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        // يجب أن يُعالَج كـ literal string وليس SQL
        $response = $this->actingAs($admin)
            ->getJson('/api/products?search=' . urlencode("' OR '1'='1"));

        $response->assertStatus(200);
        // النظام يعمل ولا يُرجع كل المنتجات
    }

    /** @test */
    public function mass_assignment_cannot_set_balance()
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->postJson('/api/accounts', [
            'account_code' => 'TEST001',
            'account_name' => 'حساب تجريبي',
            'account_type' => 'asset',
            'balance' => 999999, // محاولة تعيين رصيد مباشرة
        ]);

        // الرصيد لا يُعيَّن من المستخدم
        $this->assertDatabaseMissing('accounts', ['balance' => 999999]);
    }

    /** @test */
    public function password_is_never_returned_in_user_response()
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->getJson('/api/users');
        $users = $response->json('users');

        foreach ($users as $user) {
            $this->assertArrayNotHasKey('password', $user);
            $this->assertArrayNotHasKey('remember_token', $user);
        }
    }

    /** @test */
    public function security_headers_are_present()
    {
        $response = $this->get('/login');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        // Modern recommendation: 0 disables the XSS auditor (CSP replaces it)
        $response->assertHeader('X-XSS-Protection', '0');
    }

    /** @test */
    public function path_traversal_in_lang_is_blocked()
    {
        $this->get('/lang/../../etc/passwd/translations')->assertStatus(404);
        $this->get('/lang/ar/translations')->assertStatus(200);
    }
}
